<?php
/*
 * netgate_firmware_upgrade.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2021 Rubicon Communications, LLC (Netgate)
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
require_once("system.inc");

function get_firmware_model() {
	$specplatform = system_identify_specific_platform();

	switch($specplatform['name']) {
	case 'RCC-DFF':
	case 'SG-2220':
		return 'DFF2';
		break;
	case 'SG-2440':
	case 'SG-4860':
	case 'SG-8860':
	case 'RCC-VE':
		return 'RCCVE';
		break;
	case 'RCC':
		return 'RCC';
		break;
	case 'PLCC-B':
		return 'PLCC';
		break;
	case 'Netgate-6100':
		return 'CORDOBA';
		break;
	default:
		return '';
	}
}

function is_netgate_hw() {
	return !empty(get_firmware_model());
}

function get_firmware_details($firmware) {
	$details = array();

	if (preg_match('/^(CORDOBA)-([\d.]{11}\w)/', $firmware, $matches)) {
		return(array('model' => $matches[1], 'version' => $matches[2]));
	}

	if (!preg_match('/^ADI_(RCC|RCCVE|DFF2|PLCC)-([\d.]{11})/',
	    $firmware, $matches)) {
		return $details;
	}

	if (!isset($matches[2])) {
		return $details;
	}

	$details['model'] = $matches[1];
	$details['version'] = $matches[2];

	return $details;
}

function get_current_firmware_details() {
	$firmware = exec("/bin/kenv -q smbios.bios.version 2>/dev/null");

	return get_firmware_details($firmware);
}

function get_new_firmware_details() {
	$firmware_path = "%%DATADIR%%/roms/";

	$rom_names = array(
	    'RCC' => '%%COREBOOT_RCC_ROM%%',
	    'RCCVE' => '%%COREBOOT_RCCVE_ROM%%',
	    'DFF2' => '%%COREBOOT_DFF2_ROM%%',
	    'PLCC' => '%%COREBOOT_PLCCB_ROM%%',
	    'CORDOBA' => '%%BLINKBOOT_NETGATE_6100%%',
	);

	$model = get_firmware_model();
	$details = array();
	$path = $firmware_path . $rom_names[$model];

	if (empty($model) || empty($rom_names[$model]) ||
	    !file_exists($firmware_path . $rom_names[$model])) {
		return $details;
	}

	$details = get_firmware_details($rom_names[$model]);
	$details['rom_path'] = $firmware_path . $rom_names[$model];

	return $details;
}

function upgrade_firmware($firmware, &$output) {
	global $g;

	putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:" .
	    "/usr/local/sbin");

	if ($firmware['model'] == 'CORDOBA') {
		$_gc = exec("/usr/local/sbin/blinkboot-upgrade.sh " .
		    $firmware['rom_path'] . ' 2>&1', $output, $rc);
	} elseif ($firmware['model'] == 'PLCC') {
		$_gc = exec("/usr/local/sbin/adi_smbios_util -w -u " .
		    $firmware['rom_path'] . ' 2>&1', $output, $rc);
	} else {
		$_gc = exec("/usr/local/sbin/adi_flash_util -u " .
		    $firmware['rom_path'] . ' 2>&1', $output, $rc);
	}

	$output = implode("\n", $output);

	@file_put_contents("{$g['conf_path']}/netgate_firmware_upgrade.log",
	    $output);

	return ($rc == 0);
}
