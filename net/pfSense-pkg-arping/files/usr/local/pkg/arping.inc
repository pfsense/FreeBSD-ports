<?php
/*
 * arping.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
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

function arping_package_php_command() {
	require_once("util.inc");
	
	$button = '<br /><br /><button class="btn btn-info" type="button" value="' . gettext("Back to ARPing") . '" onclick="history.back()"><i class="fa-solid fa-undo icon-embed-btn"></i> ' . gettext("Back to ARPing") . '</button>';
	$int_count = (is_numeric($_POST['count']) ? (abs(intval($_POST['count']))) : 3);
	$arping_options = " -v -c {$int_count}";
	if (!empty($_POST['interface'])) {
		$iface = convert_friendly_interface_to_real_interface_name($_POST['interface']);
		$arping_options .= " -i {$iface}";
	}
	if (is_ipaddr($_POST['pinghost']) || is_hostname($_POST['pinghost']) || is_macaddr($_POST['pinghost'])) {
		$arping_options .= " " . $_POST['pinghost'];
		echo "<strong>arping {$arping_options} output:</strong><br /><br />";
		system("/usr/local/sbin/arping {$arping_options}");
		echo "{$button}";
	} else {
		echo "<div class=\"errmsg\">ERROR: Invalid input. Supplied address must be a valid hostname, IP or MAC address.</div>";
		echo "{$button}";
	}
	exit;
}
?>
