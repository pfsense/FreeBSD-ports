<?php
/*
 * cellular.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2016 Voleatech GmbH, Fabian Schweinfurth
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
require_once("pkg-utils.inc");
require_once("util.inc");
require_once('services.inc');

$shortcut_section = "cellular";

define('PYTHON_BIN', '%%PYTHON_CMD%%');
define('INTERFACE_BIN', '/usr/local/sbin/cellular');


function cellular_sync() {

	global $config;

	if (is_array($config['installedpackages']['cellular']['config'][0])) {
		$cellular = $config['installedpackages']['cellular']['config'][0];
	} else {
		return;
	}

	$device = "";
	$vtp = $cellular["port"];

	if (isset($vtp) && $vtp != "default") {
		$device = "-d " . $vtp;
	}

	$baudrate = "";
	$vtbr = $cellular["baudrate"];

	if (isset($vtbr) && $vtbr != "default") {
		$baudrate = "-b " . $vtbr;
	}

	$timeout = "";
	$vtto = $cellular["timeout"];

	if (isset($vtto) && $vtto != "default" && $vtto !=  "") {
		$timeout = "-t " . $vtto;
	}

	foreach (["port", "baudrate", "timeout", "initstring"] as $key) {
		$key_val = ($cellular[$key] != "default" && $cellular[$key] != "") ? $cellular[$key] : "";
		exec(PYTHON_BIN . ' ' . INTERFACE_BIN . ' setcfg Interface ' . $key . ' ' . escapeshellarg($key_val));
	}

	#Cron
	if ($cellular["enable_cellular_check"] == "on") {

		$check_interface = '-i ' . escapeshellarg($cellular["check_interface"]);
		$check_interval = $cellular["check_interval"];
		$enable_reset_modem = '';

		if ($cellular["enable_reset_modem"]) {
			$enable_reset_modem = '-r ' . escapeshellarg($cellular['reset_count']);
		}

		if ($check_interval == 1) {
			$check_interval = '*';
		} else if ($check_interval == 60) {
			$check_interval = '0';
		} else {
			$check_interval = "*/$check_interval";
		}

		$command = '/usr/local/bin/php /usr/local/sbin/cellular_check.php ' . $check_interface . ' ' . $enable_reset_modem;

		#Check if the script changed so we need to remove any entry before adding it
		if (is_array($config['cron']) && is_array($config['cron']['item'])) {

			$is_installed = false;

			foreach ($config['cron']['item'] as $item) {
					if (strstr($item['command'], $command)) {
							$is_installed = true;
							break;
					}
			}

			if (!$is_installed) {
				install_cron_job('cellular_check.php', false);
			}
		}

		install_cron_job($command, true, $check_interval, "*", "*", "*", "*", "root");
	} else {
		install_cron_job('cellular_check.php', false);
	}
}

function get_devices() {

	$serialports = glob("/dev/cua[a-zA-Z][0-9]{.[0-9],.[0-9][0-9],[0-9],[0-9].[0-9],[0-9].[0-9][0-9]}", GLOB_BRACE);
	$out = array();

	foreach ($serialports as $port) {
		$spl = explode("/", $port);
		$out[$spl[2]] = array("name" => $port, "value" => $spl[2]);
	}

	return $out;
}

function get_baudrates() {

	$poss = array("50", "75", "110", "134", "150", "200", "300", "600", "1200","1800", "2400", "4800", "9600", "19200", "38400", "57600", "115200");
	$out = array();

	foreach ($poss as $p) {
		$out[$p] = array("name" => $p, "value" => $p);
	}

	return $out;
}

function cellular_before_everything() {

	global $pkg;
	global $config;

	if (is_array($config['installedpackages']['cellular']['config'][0])) {
		$cellular = $config['installedpackages']['cellular']['config'][0];
	} else {
		$cellular = array();
	}

	// little workaround for dynamic field (content) generation
	if (isset($pkg) && array_key_exists('fields', $pkg)) {

		$custom_str = $cellular["statuscmd"] ?: "I; +CSQ; +COPS?; +CGPADDR";

		exec(PYTHON_BIN . ' ' . INTERFACE_BIN . " custom " . escapeshellarg($custom_str) . " -v", $sig);

		$my_pkg = &$pkg['fields']['field'];
		foreach ($my_pkg as &$fld) {

			if ($fld['fieldname'] == "info") {
				// result lines are in array form
				$fld_descr_html = array_reduce($sig,
				function($c, $i) {
					if ($c != "") {
						return "$c<br>$i";
					} else {
						return "$i";
					}
				});

				if (strpos($fld_descr_html, "ERROR") === FALSE) {
					$fld['description'] = "$fld_descr_html";
				} else {
					$fld['description'] = "Error";
				}
			}
		}
	}
}

function cellular_validate_input($post, &$input_errors) {

	global $_POST;

	// Reset
	if (isset($post['reset'])) {
			exec(PYTHON_BIN . ' ' . INTERFACE_BIN . " custom " . escapeshellarg('+CFUN=1,1') . " -v", $sig);
	}

	// Timeout
	if ($post['timeout'] != '' && !is_numericint($post['timeout'])) {
		$input_errors[] = "The 'Timeout' field must contain an integer value.";
	}

	// Custom Status AT Command
	if ($post['statuscmd'] != '') {
		if (!preg_match('/^[A-Z0-9 _,;+=?"*#^]*$/', $post['statuscmd'])) {
			$input_errors[] = "The 'Custom Status AT Command' field may only contain A-Z, 0-9 and some other characters matching the /^[A-Z0-9 _,;+=?\"*#]*$/ regex.";
		}
	}

}

?>
