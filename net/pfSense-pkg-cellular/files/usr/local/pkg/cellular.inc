<?php
/*
 * cellular.inc
 *
 * part of pfSense (https://www.pfsense.org)
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

$shortcut_section = "cellular";
require_once("globals.inc");
require_once("config.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");
require_once("service-utils.inc");
require_once("notices.inc");

function cellular_sync() {

	global $config;

	$python_bin = '/usr/local/bin/python2.7';
	$interface_bin = '/usr/local/sbin/cellular';

	// exec("$python_bin $interface_bin signal", $sig);
	$cellular = $config['installedpackages']['cellular']['config'][0];

	$device = "";
	$vtp = $cellular["port"];

	if (isset($vtp) && $vtp != "default") {
		$device = "-d ".$vtp;
	}

	$baudrate = "";
	$vtbr = $cellular["baudrate"];

	if (isset($vtbr) && $vtbr != "default") {
		$baudrate = "-b ".$vtbr;
	}

	$timeout = "";
	$vtto = $cellular["timeout"];

	if (isset($vtto) && $vtto != "default" && $vtto !=  "") {
		$timeout = "-t ".$vtto;
	}

	foreach (["port", "baudrate", "timeout", "initstring"] as $key) {
		$key_val = ($cellular[$key] != "default" && $cellular[$key] != "") ? $cellular[$key] : "";
		exec("$python_bin $interface_bin $device $baudrate $timeout setcfg Interface $key $key_val");
	}
}

function get_devices() {

	$serialports = glob("/dev/cua[a-zA-Z][0-9]{.[0-9],.[0-9][0-9],[0-9],[0-9].[0-9],[0-9].[0-9][0-9]}", GLOB_BRACE);
	$out = array();

	foreach ($serialports as $port) {
		$spl = split("/", $port);
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

	/// little workaround for dynamic field (content) generation
	global $pkg;
	global $config;

	$cellular = $config['installedpackages']['cellular']['config'][0];

	if (isset($pkg) && array_key_exists('fields', $pkg)) {

		$python_bin = '/usr/local/bin/python2.7';
		$interface_bin = '/usr/local/sbin/cellular';

		$custom_str = "I; +CSQ; +COPS?; +CGPADDR";

		if (isset($cellular["statuscmd"]) && $cellular["statuscmd"] != "") {
		    $custom_str = $cellular["statuscmd"];
		}

		exec("$python_bin $interface_bin custom '" . $custom_str . "' -v", $sig);

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
?>
