<?php
/*
	ladvd.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2014 Andrea Tuccia
	Copyright (C) 2014 Ermal Luçi
	Copyright (C) 2015 ESF, LLC
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
require_once("globals.inc");
require_once("util.inc");
require_once("functions.inc");
require_once("pkg-utils.inc");

function ladvd_resync_config() {
	global $config;

	if (!is_array($config['installedpackages']['ladvd'])) {
		return;
	}
	if (!is_array($config['installedpackages']['ladvd']['config'])) {
		return;
	}

	$ladvd_conf = &$config['installedpackages']['ladvd']['config'][0];
	conf_mount_rw();

	/* ladvd is turned off in package settings */
	if (empty($ladvd_conf['enable'])) {
		if (is_service_running("ladvd")) {
			 stop_service("ladvd");
		}
		unlink_if_exists("/usr/local/etc/rc.d/ladvd.sh");
		return;
	}

	$cmdline = "";

	if (!empty($ladvd_conf['autoenable'])) {
		$cmdline .= "-a ";
	}

	if (!empty($ladvd_conf['silent'])) {
		$cmdline .= "-s ";
	}

	if (!empty($ladvd_conf['management'])) {
		$cmdline .= "-m " . escapeshellarg(get_real_interface($ladvd_conf['management'])) . " ";
	}

	if (!empty($ladvd_conf['location'])) {
		$cmdline .= "-l '{$ladvd_conf['location']}' ";
	}

	if (!empty($ladvd_conf['lldp'])) {
		$cmdline .= "-L ";
	}

	if (!empty($ladvd_conf['cdp'])) {
		$cmdline .= "-C ";
	}

	if (!empty($ladvd_conf['edp'])) {
		$cmdline .= "-E ";
	}

	if (!empty($ladvd_conf['fdp'])) {
		$cmdline .= "-F ";
	}

	if (!empty($ladvd_conf['ndp'])) {
		$cmdline .= "-N ";
	}

	$ifaces = explode(",", $ladvd_conf['iface_array']);
	$ifaces = array_map('get_real_interface', $ifaces);
	$cmdline .= implode(" ", $ifaces);

	write_rcfile(array(
		"file" => "ladvd.sh",
		"start" => "/usr/local/sbin/ladvd {$cmdline}",
		"stop" => "/usr/bin/killall -9 ladvd"
		)
	);

	if (is_service_running("ladvd")) {
		restart_service("ladvd");
	} else {
		start_service("ladvd");
	}

	sleep(1);
	conf_mount_ro();
}

?>
