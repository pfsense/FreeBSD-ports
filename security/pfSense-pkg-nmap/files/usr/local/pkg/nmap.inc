<?
/*
 * nmap.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2006 Bill Marquette <bill.marquette@gmail.com>
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

require_once("pkg-utils.inc");
require_once("util.inc");

function nmap_custom_php_validation_command($post, &$input_errors) {
	if (empty($post['hostname'])) {
		$input_errors[] = gettext("You must enter an IP address to scan.");
	} elseif (!(is_ipaddr($post['hostname']) || is_subnet($post['hostname']) || is_hostname($post['hostname']))) {
		$input_errors[] = gettext("You must enter a valid IP address to scan.");
	}

	if(!empty($post['interface'])) {
		$interfaces = get_configured_interface_with_descr();
		if (!array_key_exists($post['interface'], $interfaces)) {
			$input_errors[] = gettext("Invalid interface.");
		}
	}
}

function nmap_custom_add_php_command() {
	$fp = "/root/";
	$fn = "nmap.result";
	/* check if nmap scan is already running */
	$processcheck = (trim(shell_exec("/bin/ps axw -O pid= | /usr/bin/grep 'tee {$fp}{$fn}' | /usr/bin/egrep -v '(pflog|grep)'")));

	$processisrunning = ($processcheck != "");

	if ($processisrunning) {
		echo "<strong>Nmap is already running. Please wait for current scan to complete</strong><br />";
		echo '<br /><br /><button class="btn btn-info" type="button" value="' . gettext("Back to Nmap") . '" onclick="history.back()"><i class="fa fa-undo icon-embed-btn"></i> ' . gettext("Back to Nmap") . '</button>';
	} else {
		$nmap_options = "";

		if (is_ipaddrv6($_POST['hostname']) || is_subnetv6($_POST['hostname'])) {
			$nmap_options .= " -6";
		}

		switch($_POST['scanmethod']) {
			case 'syn':
				$nmap_options .= " -sS";
				break;
			case 'connect':
				$nmap_options .= " -sT";
				break;
			case 'icmp':
				$nmap_options .= " -sP";
				break;
			case 'udp':
				$nmap_options .= " -sU";
				break;
			case 'arp':
				$nmap_options .= " -sP -PR";
				break;
		}

		if ($_POST['noping']) {
			$nmap_options .= " -P0";
		}
		if ($_POST['servicever']) {
			$nmap_options .= " -sV";
		}
		if ($_POST['osdetect']) {
			$nmap_options .= " -O";
		}
		if (!empty($_POST['interface'])) {
			$nmap_options .= " -e " . get_real_interface($_POST['interface']);
		}

		$nmap_options .= " " . escapeshellarg($_POST['hostname']);
		$cmd = "/usr/local/bin/nmap {$nmap_options} | /usr/bin/tee {$fp}{$fn}";
		echo "<strong>Running: {$cmd}</strong><br />";
		mwexec_bg($cmd);
		echo "<strong>Check results in View Results TAB</strong><br />";
		echo '<br /><br /><button class="btn btn-info" type="button" value="' . gettext("Back to Nmap") . '" onclick="history.back()"><i class="fa fa-undo icon-embed-btn"></i> ' . gettext("Back to Nmap") . '</button>';
	}
}

function nmap_get_interfaces() {
	global $config;
	$interfaces = get_configured_interface_with_descr();
	$nmap_ifs = array(array("name" => "Any", "value" => ""));
	foreach ($interfaces as $iface => $ifacename) {
		$tmp["name"]  = $ifacename;
		$tmp["value"] = $iface;
		$nmap_ifs[] = $tmp;
	}

	foreach (array('server', 'client') as $mode) {
		if (is_array($config['openvpn']["openvpn-{$mode}"])) {
			foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
				if (!isset($setting['disable'])) {
					$tmp["name"]  = gettext("OpenVPN") . " " . $mode . ": " . htmlspecialchars($setting['description']);
					$tmp["value"] = 'ovpn' . substr($mode, 0, 1) . $setting['vpnid'];
					$nmap_ifs[] = $tmp;
				}
			}
		}
	}

	return $nmap_ifs;
}

?>
