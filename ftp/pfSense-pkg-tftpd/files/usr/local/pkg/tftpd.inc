<?php
/*
 * tftpd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016 Stefan Seidel
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

require_once("globals.inc");
require_once("notices.inc");
require_once("pfsense-utils.inc");
require_once("service-utils.inc");
require_once("util.inc");

/* Define some locations */
define('BACKUP_DIR', '/root/backup');
define('BACKUP_FILENAME', 'tftp.bak.tgz');
define('BACKUP_PATH', BACKUP_DIR . '/' . BACKUP_FILENAME);
/* Change FILES_DIR below if you really need TFTP files directory elsewhere.
 * Note: Such modifications are completely unsupported!
 */
define('FILES_DIR', '/tftpboot');
define('LOG_PREFIX_PKG_TFTPD', 'tftpd');

/* Create backup of the TFTP server directory */
function tftp_create_backup($trigger_download = false) {
	safe_mkdir(BACKUP_DIR);
	if (mwexec("/usr/bin/tar -czC / -f " . BACKUP_PATH . " " . FILES_DIR) || !file_exists(BACKUP_PATH)) {
		header("Location: tftp_files.php?savemsg=Backup+failed.&result=alert-warning");
	} elseif ($trigger_download == false) {
		header("Location: tftp_files.php?savemsg=Backup+has+been+created");
	}
}

/* Restore backup of the TFTP server directory */
function tftp_restore_backup() {
	if (file_exists(BACKUP_PATH)) {
		mwexec("/usr/bin/tar -xpzC / -f " . BACKUP_PATH);
		header("Location: tftp_files.php?savemsg=Backup+has+been+restored.");
	} else {
		header("Location: tftp_files.php?savemsg=Restore+failed.+Backup+file+not+found.&result=alert-warning");
	}
}

/* Only allow to download files under the FILES_DIR */
function tftp_filesdir_bounds_check($filename, $error_msg) {
	$basedirlength = strlen(FILES_DIR);
	if (substr($filename, 0, $basedirlength) !== FILES_DIR) {
		logger(LOG_ERR, $error_msg, LOG_PREFIX_PKG_TFTPD);
		file_notice("tftpd", "{$error_msg}", "Packages");
		return false;
	} else {
		return true;
	}
}

function install_package_tftpd() {
	safe_mkdir(FILES_DIR);
	chown(FILES_DIR, 'nobody');
	chgrp(FILES_DIR, 'nobody');
	chown(FILES_DIR . '/*', 'nobody');
	chgrp(FILES_DIR . '/*', 'nobody');
	upgrade_config_tftpd();
}

function deinstall_package_tftpd() {
	// Will only get removed when empty
	@rmdir(FILES_DIR);
}

function upgrade_config_tftpd() {
	// FILES_DIR is not configurable any more
	$tftpd_conf = config_get_path('installedpackages/tftpd/config/0', []);
	if (!empty($tftpd_conf['datadir']) && $tftpd_conf['datadir'] !== FILES_DIR) {
		file_notice("tftpd", "Please, move your TFTP server files from {$tftpd_conf['datadir']} to /tftpboot", "Packages");
		config_del_path('installedpackages/tftpd/config/0/datadir');
	}
}

function sync_package_tftpd() {
	global $g;


	$tftpd_conf = config_get_path('installedpackages/tftpd/config/0', []);

	/* if turned off in package settings, stop service, remove rc script and do nothing else */
	if ($tftpd_conf['enable'] != "on") {
		if (is_service_running('tftpd')) {
			stop_service("tftpd");
			sleep(3);
		}
		unlink_if_exists('/usr/local/etc/rc.d/tftpd.sh');
		return;
	}

	// Root directory
	$datadir = FILES_DIR;

	// TFTP Server Bind IP
	if (!empty($tftpd_conf['tftpd_ip'])) {
		$address = $tftpd_conf['tftpd_ip'];
		if (is_ipaddrv6($address)) {
			$address = "-a [{$address}]";
		} elseif (is_ipaddrv4($address)) {
			$address = "-a {$address}";
		} else {
			$address = "";
		}
	}

	$pidfile = "{$g['varrun_path']}/tftpd-hpa.pid";

	// IPv4 Only?
	if ($tftpd_conf['tftpd_ipv4only'] == "on") {
		$options = "-4";
	}

	// Max Block Size
	if (!empty($tftpd_conf['tftpd_blocksize'])) {
		$options .= " -B " . escapeshellarg($tftpd_conf['tftpd_blocksize']);
	}

	// Logging (RRQ/WRQ)
	if ($tftpd_conf['tftpd_logging'] == "on") {
		$options .= " -v ";
	}

	// Allow to upload files
	if ($tftpd_conf['tftpd_create'] == "on") {
		$options .= " -c -p ";
	}

	write_rcfile(array(
		"file" => "tftpd.sh",
		"start" => "/usr/local/libexec/in.tftpd -l -s {$datadir} {$address} -P {$pidfile} {$options}",
		"stop" => "/usr/bin/killall in.tftpd"
		)
	);

	if (is_service_running('tftpd')) {
		stop_service("tftpd");
		sleep(3);
	}
	/* Only (re)start the service when it is enabled */
	if ($tftpd_conf['enable'] == "on") {
		start_service("tftpd");
		sleep(3);
	}

}

function validate_form_tftpd($post, &$input_errors) {
	if ($post['tftpd_ip']) {
		if ($post['tftpd_ipv4only'] && !is_ipaddrv4($post['tftpd_ip'])) {
			$input_errors[] = 'TFTP Server Bind IP must be a valid IPv4 address!';
		} elseif (!is_ipaddr($post['tftpd_ip'])) {
			$input_errors[] = 'TFTP Server Bind IP must be a valid IP address!';
		}
		if (!is_ipaddr_configured($post['tftpd_ip'])) {
			$input_errors[] = "{$post['tftpd_ip']} TFTP Server Bind IP must be a valid, locally configured IP address!";
		}
	}

	if ($post['tftpd_blocksize']) {
		if (!is_numericint($post['tftpd_blocksize']) || ($post['tftpd_blocksize'] < 512) || ($post['tftpd_blocksize'] > 65464)) {
			$input_errors[] = 'Max Block Size must be an integer with a permitted range from 512 to 65464!';
		}
	}
}

?>
