<?php
/*
 * andwatch.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2025 Denny Page
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once("config.inc");
require_once("functions.inc");
require_once("util.inc");
require_once("service-utils.inc");

define('LOG_PREFIX_PKG_ANDWATCH', 'ANDwatch');

const ANDWATCH_SVC_NAME = 'andwatchd';
const ANDWATCH_RC_FILE = '/usr/local/etc/rc.d/andwatch.sh';
const ANDWATCHD_CMD = '/usr/local/bin/andwatchd';
const ANDWATCHD_NOTIFY_CMD = '/usr/local/pkg/andwatch-notify.php';
const ANDWATCHD_PID_PREFIX = '/var/run/andwatch-';
const ANDWATCH_MA_DB_FILE = '/var/db/andwatch/ma_db.sqlite';
const ANDWATCH_UPDATE_MA_CMD = '/usr/local/bin/andwatch-update-ma';
const ANDWATCH_QUERY_CMD = '/usr/local/bin/andwatch-query';

$shortcut_section = 'andwatch';

// Is ANDwatch enabled?
function andwatch_enabled() {
	return (config_get_path('installedpackages/andwatch/enable', false));
}


// Write the rc file
function andwatch_write_rcfile() {
	// Get the current configuration
	$current_config = config_get_path('installedpackages/andwatch');

	// Get the real interface names
	$interfaces = array();
	foreach (array_filter(explode(',', array_get_path($current_config, 'active_interfaces', ''))) as $interface) {
		$interface_name = get_real_interface($interface);
		if (!isset($interface_name)) {
			continue;
		}
		$interfaces[$interface] = $interface_name;
	}

	// Ensure the assignments database exists
	$start = "if [ \! -f " . ANDWATCH_MA_DB_FILE . " ]\n";
	$start .= "\tthen\n";
	$start .= "\t\techo Updating MAC Assignments database\n";
	$start .= "\t\t" . ANDWATCH_UPDATE_MA_CMD . "\n";
	$start .= "\tfi\n\n";

	// Individual daemons
	$start .= "\techo Starting ANDwatch daemons";
	foreach ($interfaces as $interface => $real_interface) {
		// Basic configuration with syslog and pid file
		$cmd = ANDWATCHD_CMD . " -s -p " . ANDWATCHD_PID_PREFIX . "{$real_interface}.pid";

		// Notifications
		if (array_get_path($current_config, "interfaces/{$interface}/notifications", false)) {
			$cmd .= " -n " . ANDWATCHD_NOTIFY_CMD;
		}

		// Expiration (andwatchd default is 30)
		$expiration = array_get_path($current_config, "interfaces/{$interface}/expiration", '30');
		if (is_numericint($expiration) && ($expiration != '30')) {
			$cmd .= " -O {$expiration}";
		}

		// PCAP filter
		$filter = null;
		switch (array_get_path($current_config, "interfaces/{$interface}/pcap_filter", 'none')) {
			case 'link-local':
				$filter = "'not net 169.254.0.0/16 and not net fe80::0/10'";
				break;
			case 'link-local-unique':
				$filter = "'not net 169.254.0.0/16 and not net fe80::0/10 and not net fc00::0/7'";
				break;
			case 'custom':
				$filter = escapeshellarg(trim(array_get_path($current_config, "interfaces/{$interface}/custom_filter")));
				break;
		}
		if (!empty($filter)) {
			$cmd .= " -F " . $filter;
		}

		$cmd .= " {$real_interface}";
		$start .= "\n\t{$cmd}";
	}

	// Write the rc file
	$stop = '/usr/bin/killall ' . ANDWATCH_SVC_NAME;
	write_rcfile(array(
		"file" => "andwatch.sh",
		"start" => $start,
		"stop" => $stop
		)
	);
}


// Sync the config
function andwatch_sync_config() {
	// Stop the service if it is currently running
	if (is_service_running(ANDWATCH_SVC_NAME)) {
		logger(LOG_INFO, localize_text("Stopping service"), LOG_PREFIX_PKG_ANDWATCH);
		stop_service(ANDWATCH_SVC_NAME);
	}

	// If the service is now disabled, remove the cron job and rc file
	if (!andwatch_enabled()) {
		install_cron_job(ANDWATCH_UPDATE_MA_CMD, false);
		unlink_if_exists(ANDWATCH_RC_FILE);
		return;
	}

	// Set a cron job to update the MAC registration database
 	$cron_already_installed = false;
 	foreach (config_get_path('cron/item', []) as $item) {
 		if ($item['command'] == ANDWATCH_UPDATE_MA_CMD) {
 			$cron_already_installed = true;
 			break;
 		}
 	}
 	if ($cron_already_installed == false) {
 		install_cron_job(ANDWATCH_UPDATE_MA_CMD, true, rand(0,59), rand(0,23), '1,15', '*', '*', 'root', true);
 	}

	// Write the rc file
	andwatch_write_rcfile();

	// Take no further action during platform boot
	if (is_platform_booting()) {
		return;
	}

	// Start the service
	logger(LOG_INFO, localize_text("Starting service"), LOG_PREFIX_PKG_ANDWATCH);
	start_service(ANDWATCH_SVC_NAME);
}


// Clean up on deinstall
function andwatch_deinstall_command() {
	if (is_service_running(ANDWATCH_SVC_NAME)) {
		logger(LOG_INFO, localize_text("Stopping service"), LOG_PREFIX_PKG_ANDWATCH);
		stop_service(ANDWATCH_SVC_NAME);
	}

	install_cron_job(ANDWATCH_UPDATE_MA_CMD, false);
	unlink_if_exists(ANDWATCH_RC_FILE);
}


// Run a query
function andwatch_query_interface($ifname, $all = false)
{
	$entries = array();

	// Build the query command
	$cmd = ANDWATCH_QUERY_CMD;
	if ($all) {
		$cmd .= ' -a';
	}
	$cmd .= ' ' . get_real_interface($ifname);

	// Run the query
	$pipe = popen($cmd, 'r');
	if ($pipe) {
		while ($line = fgets($pipe)) {
			list($date, $time, $age, $hostname, $ipaddr, $hwaddr, $org) = sscanf(trim($line), '%s %s %s %s %s %s %[^$]s');
			$entry = [
				'datetime' => "$date $time",
				'age' => $age,
				'hostname' => $hostname,
				'ipaddr' => $ipaddr,
				'hwaddr' => $hwaddr,
				'org' => $org
			];
			$entries[] = $entry;
		}
		pclose($pipe);
	}

	return $entries;
}

?>
