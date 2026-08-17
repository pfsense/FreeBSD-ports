<?php
/*
 * chrony.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2026 Denny Page
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
require_once("util.inc");
require_once("service-utils.inc");

define('LOG_PREFIX_PKG_CHRONY', 'Chrony');

// Misc constants
const CHRONY_SVC_NAME = 'chronyd';
const CHRONY_CMD = '/usr/local/sbin/chronyd';
const CHRONY_RC_FILE = '/usr/local/etc/rc.d/chronyd.sh';
const CHRONY_CONF_FILE = '/usr/local/etc/chrony.conf';
const CHRONY_DB_DIR = '/var/db/chrony';
const CHRONY_NTS_CERT_FILE = CHRONY_DB_DIR . '/ntsservercert.pem';
const CHRONY_NTS_KEY_FILE = CHRONY_DB_DIR . '/ntsserverkey.pem';
const CHRONY_USER = 'ntpd';

// Configuration tokens
const CHRONY_CONF_TOK_ENABLE = 'enable';
const CHRONY_CONF_TOK_IPPROTO = 'ipproto';
const CHRONY_CONF_TOK_LOCAL = 'local';
const CHRONY_CONF_TOK_LOCAL_ORPHAN = 'orphan';
const CHRONY_CONF_TOK_LOCAL_STRATUM = 'stratum';
const CHRONY_CONF_TOK_NTS_CERT = 'nts_cert';
const CHRONY_CONF_TOK_SOURCE = 'ntpserver';
const CHRONY_CONF_TOK_SOURCE_DISABLED = 'disabled';
const CHRONY_CONF_TOK_SOURCE_NAME = 'name';
const CHRONY_CONF_TOK_SOURCE_IPPROTO = 'ipproto';
const CHRONY_CONF_TOK_SOURCE_TYPE = 'type';
const CHRONY_CONF_TOK_SOURCE_MAXSOURCES = 'maxsources';
const CHRONY_CONF_TOK_SOURCE_POLLMIN = 'pollmin';
const CHRONY_CONF_TOK_SOURCE_POLLMAX = 'pollmax';
const CHRONY_CONF_TOK_SOURCE_FILTER = 'filter';
const CHRONY_CONF_TOK_SOURCE_XLEAVE = 'xleave';
const CHRONY_CONF_TOK_SOURCE_PRIORITY = 'priority';
const CHRONY_CONF_TOK_SOURCE_REQUIRE = 'require';
const CHRONY_CONF_TOK_SOURCE_NTS = 'nts';
const CHRONY_CONF_TOK_SOURCE_DESC = 'desc';

// Configuration paths
const CHRONY_CONF_PATH = 'installedpackages/chrony';
const CHRONY_CONF_PATH_ENABLE = CHRONY_CONF_PATH . '/' . CHRONY_CONF_TOK_ENABLE;
const CHRONY_CONF_PATH_IPPROTO = CHRONY_CONF_PATH . '/' . CHRONY_CONF_TOK_IPPROTO;
const CHRONY_CONF_PATH_NTS_CERT = CHRONY_CONF_PATH . '/' . CHRONY_CONF_TOK_NTS_CERT;
const CHRONY_CONF_PATH_LOCAL = CHRONY_CONF_PATH . '/' . CHRONY_CONF_TOK_LOCAL;
const CHRONY_CONF_PATH_ORPHAN = CHRONY_CONF_PATH . '/' . CHRONY_CONF_TOK_LOCAL_ORPHAN;
const CHRONY_CONF_PATH_STRATUM = CHRONY_CONF_PATH . '/' . CHRONY_CONF_TOK_LOCAL_STRATUM;
const CHRONY_CONF_PATH_SOURCE = CHRONY_CONF_PATH . '/' . CHRONY_CONF_TOK_SOURCE;


// Is Chrony enabled?
function chrony_enabled() {
	return (config_get_path(CHRONY_CONF_PATH_ENABLE, false));
}

// What IP protocols are configured?
function chrony_ip_protocols() {
	return (config_get_path(CHRONY_CONF_PATH_IPPROTO, 'any'));
}

// Report certificate use
function chrony_plugin_certificates($pluginparams) {
	$result = array();
	if ($pluginparams['type'] == 'certificates' && $pluginparams['event'] == 'used_certificates') {
		$chrony_nts_cert = config_get_path(CHRONY_CONF_PATH_NTS_CERT, 'none');
		if ($chrony_nts_cert != 'none') {
			$result['pkgname'] = 'Chrony';
			$result['certificatelist'] = array();
			$result['certificatelist'][$chrony_nts_cert][] = ['usedby' => 'Chrony - NTS'];
		}
	}
	return $result;
}

// Write the NTS certificate files
function chrony_write_cert_files($cert_id) {
	// Look up the certificate
	$cert = lookup_cert($cert_id);
	$cert = $cert['item'];
	if ($cert == null) {
		return false;
	}

	// Open the certificate files
	$cert_fd = fopen(CHRONY_NTS_CERT_FILE, 'w');
	if (!$cert_fd) {
		logger(LOG_ERR, localize_text('Could not write file %s', CHRONY_NTS_CERT_FILE), LOG_PREFIX_PKG_CHRONY);
		return false;
	}
	$key_fd = fopen(CHRONY_NTS_KEY_FILE, 'w');
	if (!$key_fd) {
		logger(LOG_ERR, localize_text('Could not write file %s', CHRONY_NTS_KEY_FILE), LOG_PREFIX_PKG_CHRONY);
		fclose($cert_fd);
		return false;
	}

	// Ensure correct permissions and ownership for the certificate files
	chmod(CHRONY_NTS_CERT_FILE, 0400);
	chmod(CHRONY_NTS_KEY_FILE, 0400);
	chown(CHRONY_NTS_CERT_FILE, CHRONY_USER);
	chown(CHRONY_NTS_KEY_FILE, CHRONY_USER);

	// Write the certificate files
	fwrite($cert_fd, base64_decode($cert['crt']) . "\n" . ca_chain($cert));
	fwrite($key_fd, base64_decode($cert['prv']));

	fclose($cert_fd);
	fclose($key_fd);
	return true;
}

// Write the config and rc files
function chrony_write_files() {
	// Ensure the chrony directory exits
	mkdir(CHRONY_DB_DIR, 0755);
	chown(CHRONY_DB_DIR, CHRONY_USER);

	// Get the current configuration
	$current_config = config_get_path(CHRONY_CONF_PATH, []);

	// Initialize the config file
	$file_array = array();
	$file_array[] = '# This file is generated by the pfSense Chrony package.';
	$file_array[] = '# Do not edit this file, it will be overwritten automatically.';
	$file_array[] = '';

	$file_array[] = '# NTP Sources';
	foreach (array_get_path($current_config, CHRONY_CONF_TOK_SOURCE, []) as $id => $source):
		if (!isset($source[CHRONY_CONF_TOK_SOURCE_DISABLED])) {
			// Source type and name
			$type = $source[CHRONY_CONF_TOK_SOURCE_TYPE];
			switch ($type) {
				case 'pool':
					$line = 'pool ';
					break;
				case 'server':
					$line = 'server ';
					break;
				case 'peer':
					$line = 'peer ';
					break;
			}
			$line .= $source[CHRONY_CONF_TOK_SOURCE_NAME];

			// Source IP protocol
			switch ($source[CHRONY_CONF_TOK_SOURCE_IPPROTO]) {
				case 'ipv4':
					$line .= ' ipv4';
					break;
				case 'ipv6':
					$line .= ' ipv6';
					break;
			}

			// Min and max polling intervals
			$line .= ' minpoll ' . $source[CHRONY_CONF_TOK_SOURCE_POLLMIN];
			$line .= ' maxpoll ' . $source[CHRONY_CONF_TOK_SOURCE_POLLMAX];

			// Median filter
			if ($source[CHRONY_CONF_TOK_SOURCE_FILTER]) {
				$line .= ' filter ' . $source[CHRONY_CONF_TOK_SOURCE_FILTER];
			}

			// iburst for non-peer sources
			if ($type != 'peer') {
				$line .= ' iburst';
			}

			// Interleaved mode
			if ($source[CHRONY_CONF_TOK_SOURCE_XLEAVE]) {
				$line .= ' xleave';
			}

			// Priority
			switch ($source[CHRONY_CONF_TOK_SOURCE_PRIORITY]) {
				case 'prefer':
					$line .= ' prefer';
					break;
				case 'noselect':
					$line .= ' noselect';
					break;
				case 'trust':
					$line .= ' trust';
					break;
			}

			// Require
			if ($source[CHRONY_CONF_TOK_SOURCE_REQUIRE]) {
				$line .= ' require';
			}

			// Max sources for pools
			if ($type == 'pool' && $source[CHRONY_CONF_TOK_SOURCE_MAXSOURCES] != 4) {
				$line .= ' maxsources ' . $source[CHRONY_CONF_TOK_SOURCE_MAXSOURCES];
			}

			// NTS
			if ($source[CHRONY_CONF_TOK_SOURCE_NTS]) {
				$line .= ' nts';
			}

			$file_array[] = $line;
		}
	endforeach;
	$file_array[] = '';

	if ($current_config[CHRONY_CONF_TOK_LOCAL]) {
		$file_array[] = '# Local reference mode.';
		$line = 'local';
		if ($current_config[CHRONY_CONF_TOK_LOCAL_ORPHAN]) {
			$line .= ' orphan';
		}
		if ($current_config[CHRONY_CONF_TOK_LOCAL_STRATUM] != 10) {
			$line .= ' stratum ' . $current_config[CHRONY_CONF_TOK_LOCAL_STRATUM];
		}
		$file_array[] = $line;
		$file_array[] = '';
	}

	if ($current_config[CHRONY_CONF_TOK_NTS_CERT] != 'none') {
		if (chrony_write_cert_files($current_config[CHRONY_CONF_TOK_NTS_CERT])) {
			$file_array[] = 'ntsservercert ' . CHRONY_NTS_CERT_FILE;
			$file_array[] = 'ntsserverkey ' . CHRONY_NTS_KEY_FILE;
			$file_array[] = '';
		}
	}

	$file_array[] = '# Access restrctions are implemented using firewall rules.';
	$file_array[] = 'allow';
	$file_array[] = '';

	$file_array[] = '# Record the rate at which the system clock gains/losses time.';
	$file_array[] = 'driftfile ' . CHRONY_DB_DIR . '/driftfile';
	$file_array[] = '';

	$file_array[] = '# Keep measurements across restarts.';
	$file_array[] = 'dumpdir ' . CHRONY_DB_DIR;
	$file_array[] = '';

	$file_array[] = '# Keep NTS cookies and keys.';
	$file_array[] = 'ntsdumpdir ' . CHRONY_DB_DIR;
	$file_array[] = '';

	$file_array[] = '# Allow the system clock to be stepped in the first three';
	$file_array[] = '# updates if the offset is larger than 1 second.';
	$file_array[] = 'makestep 1.0 3';
	$file_array[] = '';

	// Write the config file
	if (!file_put_contents(CHRONY_CONF_FILE, implode("\n", $file_array))) {
		logger(LOG_ERR, localize_text('Could not write file %s', CHRONY_CONF_FILE), LOG_PREFIX_PKG_CHRONY);
	}

	// Write the rc file
	$start = CHRONY_CMD . ' -r';	// Load sample history on startup
	if ($current_config[CHRONY_CONF_TOK_IPPROTO] == 'ipv4') {
		$start .= ' -4';	// Use IPv4 only
	}
	if ($current_config[CHRONY_CONF_TOK_IPPROTO] == 'ipv6') {
		$start .= ' -6';	// Use IPv6 only
	}

	$start .= ' -f ' . CHRONY_CONF_FILE;
	$stop = '/usr/bin/killall ' . CHRONY_SVC_NAME;
	write_rcfile(array(
		"file" => "chronyd.sh",
		"start" => $start,
		"stop" => $stop
		)
	);
}

// Synchronize the running config with the current settings
function chrony_sync_config() {
	// Stop the service if it is currently running
	if (is_service_running(CHRONY_SVC_NAME)) {
		logger(LOG_INFO, localize_text('Stopping service'), LOG_PREFIX_PKG_CHRONY);
		stop_service(CHRONY_SVC_NAME);
	}

	// If the service is now disabled, remove the config and rc files and return
	if (!chrony_enabled()) {
		unlink_if_exists(CHRONY_RC_FILE);
		unlink_if_exists(CHRONY_CONF_FILE);
		return;
	}

	// Write the config and rc files
	chrony_write_files();

	// Take no further action during platform boot
	if (is_platform_booting()) {
		return;
	}

	// Start the service
	logger(LOG_INFO, localize_text('Starting service'), LOG_PREFIX_PKG_CHRONY);
	start_service(CHRONY_SVC_NAME);
}

// Clean up on deinstall
function chrony_deinstall_command() {
	if (is_service_running(CHRONY_SVC_NAME)) {
		log_error("Stopping service Chrony Daemon");
		stop_service(CHRONY_SVC_NAME);
	}
	unlink_if_exists(CHRONY_RC_FILE);
	unlink_if_exists(CHRONY_CONF_FILE);
}
