<?php
/*
 * tftpd.inc
 *
 * part of pfSense (https://www.pfsense.org)
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

if (!function_exists("filter_configure")) {
	require_once("filter.inc");
}
require_once("globals.inc");
require_once("interfaces.inc");
require_once("pfsense-utils.inc");
require_once("service-utils.inc");
require_once("util.inc");

function install_package_tftpd() {
	safe_mkdir("/tftpboot");
}

function deinstall_package_tftpd() {
	@rmdir("/tftpboot");
}

function sync_package_tftpd() {
	global $config;

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
	
	$datadir = $tftpd_conf['datadir'];
	
	if (!is_dir($datadir)) {
		log_error("TFTP files directory {$datadir} does not exist.");
		return;
	} 

	write_rcfile(array(
		"file" => "tftpd.sh",
		"start" => "/usr/local/libexec/in.tftpd -l -s {$datadir}",
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

	filter_configure();
}

function validate_form_tftpd($post, &$input_errors) {
	if ($post['datadir'] && !is_dir($post['datadir'])) {
		$input_errors[] = 'Directory for files does not exist!';
	}
}

?>
