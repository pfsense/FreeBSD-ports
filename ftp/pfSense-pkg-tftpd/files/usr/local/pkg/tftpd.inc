<?php
/*
	tftpd.inc
	Copyright (C) 2016 Stefan Seidel
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
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
	rmdir("/tftpboot");
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
	filter_configure();

	conf_mount_ro();
}

function validate_form_tftpd($post, &$input_errors) {
	if ($post['datadir'] && !is_dir($post['datadir'])) {
		$input_errors[] = 'Directory for files does not exist!';
	}
}

?>
