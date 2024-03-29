<?php
/*
 * ladvd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014 Andrea Tuccia
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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

	/* ladvd is turned off in package settings */
	if (empty($ladvd_conf['enable'])) {
		if (is_service_running("ladvd")) {
			 stop_service("ladvd");
		}
		unlink_if_exists("/usr/local/etc/rc.d/ladvd.sh");
		return;
	}

	$cmdline = "-r ";

	if (!empty($ladvd_conf['autoenable'])) {
		$cmdline .= "-a ";
	}

	if (!empty($ladvd_conf['silent'])) {
		$cmdline .= "-s ";
	}

	if (!empty($ladvd_conf['infdescr'])) {
		$cmdline .= "-z ";
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
	$ifs = array();
	foreach ($ifaces as $if) {
		$ifs = array_merge($ifs, get_parent_physical_interface($if));
	}
	$ifaces = array_unique($ifs);
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
}

?>
