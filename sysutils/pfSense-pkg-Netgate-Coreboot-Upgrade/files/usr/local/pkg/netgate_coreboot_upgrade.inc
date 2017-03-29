<?php
/*
 * netgate_coreboot_upgrade.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017 Rubicon Communications, LLC (Netgate)
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

define(COREBOOT_PATH, "%%DATADIR%%/roms/");

function get_coreboot_model() {
	$specplatform = system_identify_specific_platform();

	switch($specplatform['name']) {
	case 'RCC-DFF':
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
	default:
		return '';
	}
}

function is_netgate_hw() {
	return !empty(get_coreboot_model());
}

function get_coreboot_details($coreboot) {
	$details = array();

	if (!preg_match('/^ADI_(RCC|RCCVE|DFF2)-([\d.]{11})-/', $coreboot,
	    $matches)) {
		return $details;
	}

	if (!isset($matches[2])) {
		return $details;
	}

	$details['model'] = $matches[1];
	$details['version'] = $matches[2];

	return $details;
}

function get_current_coreboot_details() {
	$coreboot = exec("/bin/kenv -q smbios.bios.version 2>/dev/null");

	return get_coreboot_details($coreboot);
}

function get_new_coreboot_details() {
	$rom_names = array(
	    'RCC' => '%%COREBOOT_RCC_ROM%%',
	    'RCCVE' => '%%COREBOOT_RCCVE_ROM%%',
	    'DFF2' => '%%COREBOOT_DFF2_ROM%%',
	);
	
	$model = get_coreboot_model();
	$details = array();
	`echo "model: {$model}" >> /tmp/lala`;
	`echo "rom_names[model]: {$rom_names[$model]}" >> /tmp/lala`;
	$path = COREBOOT_PATH . $rom_names[$model];
	`echo "path: {$path}" >> /tmp/lala`;

	if (empty($model) || empty($rom_names[$model]) ||
	    !file_exists(COREBOOT_PATH . $rom_names[$model])) {
		return $details;
	}

	$details = get_coreboot_details($rom_names[$model]);
	$details['rom_path'] = COREBOOT_PATH . $rom_names[$model];

	return $details;
}

function upgrade_coreboot($coreboot, &$output) {
	global $g;

	putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:" .
	    "/usr/local/sbin");

	$_gc = exec("/usr/local/sbin/adi_flash_util -u " .
	    $coreboot['rom_path'] . ' 2>&1', $output, $rc);

	$output = implode("\n", $output);

	@file_put_contents("{$g['conf_path']}/netgate_coreboot_upgrade.log",
	    $output);

	return ($rc == 0);
}
