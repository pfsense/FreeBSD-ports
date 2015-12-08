<?php
/*
	routed.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2006 Bill Marquette
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
require_once("config.inc");
require_once("interfaces.inc");
require_once("service-utils.inc");
require_once("util.inc");

function setup_routed() {
	global $config;

	if (!is_array($config['installedpackages']['routed'])) {
		return;
	}
	if (!is_array($config['installedpackages']['routed']['config'])) {
		return;
	}

	$gw = "";
	conf_mount_rw();

	if (isset($config['installedpackages']['routed']['config'][0]['enable']) &&
	    $config['installedpackages']['routed']['config'][0]['enable'] == "on") {
			/* if user selected individual interfaces */
			$ifarr = array_flip(explode(",", $config['installedpackages']['routed']['config'][0]['iface_array']));
			$allifs = get_interface_arr();
			if (!empty($ifarr)) {
				foreach ($allifs as $ifname) {
					$friendly_ifname = convert_real_interface_to_friendly_interface_name($ifname);
					if (array_key_exists($friendly_ifname, $ifarr)) {
						$gw .= setup_etc_gateways($ifname, 'enable');
					} else {
						$gw .= setup_etc_gateways($ifname, 'disable');
					}
				}
			} else {
				/* setup for all interfaces */
				$gw = setup_etc_gateways();
			}
			file_put_contents("/etc/gateways", $gw);
			routed_write_rcfile();
			if (is_service_running("routed")) {
				restart_service("routed");
			} else {
				start_service("routed");
			}
	} else {
		if (is_service_running("routed")) {
			 stop_service("routed");
		}
		unlink_if_exists("/etc/gateways");
		unlink_if_exists("/usr/local/etc/rc.d/routed.sh");
	}

	conf_mount_ro();
}

function setup_etc_gateways($iface = "", $mode = "") {
	global $config;

	$ret = "";
	if ($iface != "") {
		$realif = convert_friendly_interface_to_real_interface_name($iface);
		if (!empty($realif)) {
			$ret = "if={$realif} ";
		}
	}

	switch($mode) {
		case "enable":
			if ($config['installedpackages']['routed']['config'][0]['ripversion'] == "2") {
				$ret .= "ripv2 ";
				$passwd = $config['installedpackages']['routed']['config'][0]['passwd'];
				if ($passwd != "") {
					$ret .= "passwd={$passwd} ";
				}
				$add_no_ag = $config['installedpackages']['routed']['config'][0]['enable_no_ag'];
				$add_no_super_ag = $config['installedpackages']['routed']['config'][0]['enable_no_super_ag'];
				if ($add_no_ag == "on") {
					$ret .= "no_ag ";
				}
				if ($add_no_super_ag == "on") {
					$ret .= "no_super_ag ";
				}
			}
			break;
		case "disable":
			$ret .= "no_rip_out no_solicit no_rdisc no_rdisc_adv";
			break;
		default:
			break;
	}
	$ret .= "\n";

	return $ret;
}

function routed_write_rcfile() {
	write_rcfile(array(
		"file" => "routed.sh",
		"start" => "/usr/bin/nohup /sbin/routed > /dev/null 2>&1 &",
		"stop" => "/bin/pkill routed"
		)
	);
}

function routed_deinstall_command() {
	unlink_if_exists("/etc/gateways");
	unlink_if_exists("/usr/local/etc/rc.d/routed.sh");
}

?>
