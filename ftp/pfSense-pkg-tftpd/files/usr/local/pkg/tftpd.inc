<?php
/*
 * tftpd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2017 Rubicon Communications, LLC (Netgate)
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
require_once("pfsense-utils.inc");
require_once("service-utils.inc");
require_once("util.inc");

/* Helper function for files listing */
function tftp_byte_convert($bytes) {
	if ($bytes <= 0) {
		return '0 Byte';
	}
	$convention = 1000;
	$s = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB');
	$e = floor(log($bytes, $convention));
	return round($bytes/pow($convention, $e), 2) . ' ' . $s[$e];
}

/* Create backup of the TFTP server directory */
function tftp_create_backup($trigger_download = false) {
	global $backup_dir, $backup_path, $files_dir;

	conf_mount_rw();
	safe_mkdir("{$backup_dir}");
	if (mwexec("/usr/bin/tar -czC / -f {$backup_path} {$files_dir}") || !file_exists("{$backup_path}")) {
		header("Location: tftp_files.php?savemsg=Backup+failed.&result=alert-warning");
	} elseif ($trigger_download == false) {
		header("Location: tftp_files.php?savemsg=Backup+has+been+created");
	}
	conf_mount_ro();
}

function install_package_tftpd() {
	if (is_array($config['installedpackages']['tftpd'])) {
		$tftpd_conf = &$config['installedpackages']['tftpd']['config'][0];
	} else {
		$tftpd_conf = array();
	}
	$datadir = ($tftpd_conf['datadir'] ?: '/tftpboot');
	safe_mkdir("{$datadir}");
	unlink_if_exists("/usr/local/etc/rc.d/tftpd");
}

function deinstall_package_tftpd() {
	if (is_array($config['installedpackages']['tftpd'])) {
		$tftpd_conf = &$config['installedpackages']['tftpd']['config'][0];
	} else {
		$tftpd_conf = array();
	}
	$datadir = ($tftpd_conf['datadir'] ?: '/tftpboot');
	// Will only get removed when empty
	@rmdir("{$datadir}");
}

function sync_package_tftpd() {
	global $g, $config;

	conf_mount_rw();

	if (is_array($config['installedpackages']['tftpd'])) {
		$tftpd_conf = &$config['installedpackages']['tftpd']['config'][0];
	} else {
		$tftpd_conf = array();
	}

	/* if turned off in package settings, stop service, remove rc script and do nothing else */
	if ($tftpd_conf['tftpd_enable'] != "on") {
		if (is_service_running('tftpd')) {
			stop_service("tftpd");
			sleep(3);
		}
		unlink_if_exists('/usr/local/etc/rc.d/tftpd.sh');
		return;
	}

	// Root directory
	$datadir = $tftpd_conf['datadir'];

	// TFTP Server Bind IP
	if (!empty($tftpd_conf['tftpd_ip'])) {
		$address = $tftpd_conf['tftpd_ip'];
		if (is_ipaddrv6($address)) {
			$address = "-a [{$address}]";
		} else {
			$address = "-a {$address}";
		}
	}

	$pidfile = "{$g['varrun_path']}/tftpd-hpa.pid";

	// IPv4 Only?
	if ($tftpd_conf['tftpd_ipv4only'] == "on") {
		$options = "-4";
	}

	// Max Block Size
	if (!empty($tftpd_conf['tftpd_blocksize'])) {
		$options .= " -B {$tftpd_conf['tftpd_blocksize']}";
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
	if ($tftpd_conf['tftpd_enable'] == "on") {
		start_service("tftpd");
		sleep(3);
	}

	conf_mount_ro();
}

function validate_form_tftpd($post, &$input_errors) {
	if ($post['datadir'] && !is_dir($post['datadir'])) {
		$input_errors[] = 'Directory for files does not exist!';
	}

	if ($post['datadir'] == '/') {
		$input_errors[] = 'Setting "/" as directory for files is not allowed!';
	}

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
