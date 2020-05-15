<?php
/*
 * lldpd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2018 Denny Page
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

define("LLDPD_RCFILE", "/usr/local/etc/rc.d/lldpd.sh");
define("LLDPD_DAEMON", "/usr/local/sbin/lldpd");
define("LLDPD_CLIENT", "/usr/local/sbin/lldpcli");

$shortcut_section = "lldpd";


function lldpd_sync_config() {
	global $config;

	if (is_service_running('lldpd')) {
		log_error("Stopping service lldpd");
		stop_service('lldpd');
	}

	$lldpd_config = &$config['installedpackages']['lldpd']['config'][0];
	if (isset($lldpd_config['enable'])) {
		$enable = $lldpd_config['enable'];
	} else {
		$enable = 'no';
	}

	if ($enable !== 'yes') {
		unlink_if_exists(LLDPD_RCFILE);
		return;
	}

	$cmd = LLDPD_DAEMON;
	
	/* LLDP protocol */
	switch ($lldpd_config['lldp_proto']) {
		case 'active':
			$cmd .= ' -l';
			break;
		case 'disabled':
			$cmd .= ' -ll';
			break;
		default:
	}
	
	/* CDP protocol */
	switch ($lldpd_config['cdp_proto']) {
		case 'passive':
			$cmd .= ' -c';
			break;
		case 'passive_v2_only':
			$cmd .= ' -cccc';
			break;
		case 'active_v1_passive_v2':
			$cmd .= ' -cc';
			break;
		case 'active_v2_passive_v1':
			$cmd .= ' -ccc';
			break;
		case 'active_v2_only':
			$cmd .= ' -ccccc';
			break;
		default:
	}
	
	/* EDP protocol */
	switch ($lldpd_config['edp_proto']) {
		case 'passive':
			$cmd .= ' -e';
			break;
		case 'active':
			$cmd .= ' -ee';
			break;
		default:
	}
	
	/* FDP protocol */
	switch ($lldpd_config['fdp_proto']) {
		case 'passive':
			$cmd .= ' -f';
			break;
		case 'active':
			$cmd .= ' -ff';
			break;
		default:
	}
	
	/* NDP protocol */
	switch ($lldpd_config['ndp_proto']) {
		case 'passive':
			$cmd .= ' -s';
			break;
		case 'active':
			$cmd .= ' -ss';
			break;
		default:
	}
	
	/* Interface list */
	$interfaces = array();
	$int_switch = array();
	if (file_exists("/etc/inc/switch.inc")) {
		require_once('switch.inc');
		if (!empty(switch_get_devices())) {
			$int_switch = switch_get_uplink_ports();
		}
	}
	foreach (explode(',', $lldpd_config['interfaces']) as $i) {
		foreach (get_parent_physical_interface($i) as $p) {
			if (!empty($int_switch) && in_array($p, $int_switch)) {
				$int = get_real_interface($i);
				$interfaces[$int] = $int;
			} else {
				$interfaces[$p] = $p;
			}
		}
	}
	$interfaces = array_unique($interfaces);
	if (count($interfaces)) {
		$cmd .= ' -I ' . escapeshellarg(implode(',', $interfaces));
	}
	
	/* Receive-only flag */
	if ($lldpd_config['receiveonly'] == 'yes') {
		$cmd .= ' -r';
	} else {
		/* Chassis interface */
		$chassis_int = get_parent_physical_interface($lldpd_config['chassis']);
		if (!empty($int_switch) && empty(array_diff($int_switch, $chassis_int))) {
			$chassis_int = array(get_real_interface($lldpd_config['chassis']));
		}
		$chassis = implode(',', $chassis_int);
		if (isset($chassis)) {
			$cmd .= ' -C ' . escapeshellarg($chassis);
		}
	
		/* Management ip address */
		$ipv4 = get_interface_ip($lldpd_config['management']);
		$ipv6 = get_interface_ipv6($lldpd_config['management']);
		$addrs = array();
		if (isset($ipv4)) {
			array_push($addrs, $ipv4);
		}
		if (isset($ipv6)) {
			array_push($addrs, $ipv6);
		}
		if (!empty($addrs))
		{
			$cmd .= ' -m ' . escapeshellarg(implode(',', $addrs));
		}
	}

	if ($lldpd_config['agentx'] == 'yes') {
		$cmd .= ' -x';
	}

	/* Write the rc script */
	$start = "	$cmd";
	$stop = "	/usr/bin/killall -q lldpd";
	write_rcfile(array(
		"file" => "lldpd.sh",
		"start" => $start,
		"stop" => $stop
		)
	);

	if (platform_booting()) {
		return;
	}

	log_error("Starting service lldpd");
	start_service('lldpd');
}


function lldpd_deinstall_command() {
	if (is_service_running('lldpd')) {
		log_error("Stopping service lldpd");
		stop_service('lldpd');
	}

	unlink_if_exists(LLDPD_RCFILE);
}
