<?php
/*
 * routed.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006 Bill Marquette
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
