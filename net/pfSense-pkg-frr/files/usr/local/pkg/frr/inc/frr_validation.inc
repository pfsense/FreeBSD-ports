<?php
/*
 * frr_validation.inc
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

/* General use validation functions */

/* The definiteion of a WORD in FRR is not very clear, make it a general
 * function so that we can alter the validation later.
 */
function frr_validate_word($word) {
	/* For the time being, use is_hostname() because any hostname is a valid
	 * WORD, though it is technically too strict as a WORD can include some
	 * things that a hostname cannot. If a better set of validation rules
	 * is discovered, change this validation later. */
	return is_hostname($word);
}

/* Validate a community as AS:VAL pairs */
function frr_validate_community_asvalpair($community) {
	list ($as, $val) = explode(":", $community);
	if (is_numeric($as) && is_numeric($val) &&
	    (($as >= 0) && ($as <= 65535)) &&
	    (($val >= 0) && ($val <= 65535))) {
		return true;
	}
	return false;
}

function frr_validate_ulong($value, $startat = 0) {
	/* 0-4294967295 */
	return (strlen($value) > 0) && frr_validate_intrange($value, $startat, 4294967295);
}

function frr_validate_asnum($value) {
	return frr_validate_ulong($value, 1);
}

function frr_validate_intrange($value, $min, $max) {
	if (is_numericint($value) && ($value >= $min) && ($value <= $max)) {
		return $value;
	} else {
		return null;
	}
}

function frr_validate_community_asval_list($arr) {
	global $frr_well_known_communities;
	foreach (explode(' ', $arr) as $avp) {
		if (!frr_validate_community_asvalpair($avp) && !in_array($avp, $frr_well_known_communities)) {
			/* If there is an invalid community, then fail. */
			return false;
		}
	}
	return true;
}

function frr_validate_bgp_origin($name) {
	$valid_origins = array('egp', 'igp', 'incomplete');
	if (in_array($name, $valid_origins)) {
		return true;
	} else {
		return false;
	}
}

function frr_validate_bgp_sourceproto($name) {
	$valid_sources = array('bgp', 'ospf', 'ospf6', 'connected', 'kernel', 'static', 'system', 'rip', 'ripng', 'isis');
	if (in_array($name, $valid_sources)) {
		return true;
	} else {
		return false;
	}
}

/* GUI input validation functions, one per page */

function frr_global_validate_input() {
	global $config, $input_errors;

	if ($_POST['password'] <> "" && (strpos($_POST['password'], "'") !== false)) {
		$input_errors[] = "Password cannot contain a single quote (')";
	}
	if (!empty($_POST['routerid']) && !is_ipaddrv4($_POST['routerid'])) {
		$input_errors[] = "Router ID must be an address.";
	}
}

/* Read in rowhelper data from $_POST, sort it by sequence number, and put it back in POST. */
function frr_sort_rows_by_seq() {
	global $config, $pkg;
	$pkgarr = array();

	/* Inside this foreach was lifted from pkg_edit.php. It reads rowhelper values
	 * from $_POST which are separate and end in a number (e.g. name0, name1),
	 * into a typical array. */
	foreach ($pkg['fields']['field'] as $fields) {
		if ($fields['type'] == "rowhelper") {
			$rowhelpername="row";
			foreach ($fields['rowhelper']['rowhelperfield'] as $rowhelperfield) {
				foreach ($_POST as $key => $value) {
					$matches = array();
					if (preg_match("/^{$rowhelperfield['fieldname']}(\d+)$/", $key, $matches)) {
						if ($rowhelperfield['type'] == "textarea") {
							$pkgarr[$rowhelpername][$matches[1]][$rowhelperfield['fieldname']] = unixnewlines($value);
						} else {
							$pkgarr[$rowhelpername][$matches[1]][$rowhelperfield['fieldname']] = $value;
						}
					}
				}
			}
		}
	}

	/* Sort the rowhelper data by "seq" column */
	frr_array_seq_sort($pkgarr['row']);

	/* Take the sorted data and push it back into $_POST before it gets saved. */
	foreach ($pkgarr['row'] as $idx => $row) {
		foreach (array_keys($row) as $keyname) {
			$_POST["{$keyname}{$idx}"] = $row[$keyname];
		}
	}
}

function frr_global_acls_validate_input() {
	global $config, $input_errors, $pkg;
	frr_sort_rows_by_seq();

	/* Do not allow a name of: dnr-list */
}

function frr_global_prefixes_validate_input() {
	global $config, $input_errors, $pkg;
	frr_sort_rows_by_seq();

	/* Do not allow a name of: dnr6-list */
}

function frr_global_routemaps_validate_input() {
	global $config, $input_errors;

	/* Do not allow a name of: ACCEPTFILTER, DNR, DNR6 */
}

function frr_global_raw_validate_input() {
	global $config, $input_errors;

	/* Run commands with -C, grep -c '\*\*\* error' */
}

function frr_ospf_interfaces_validate_input() {
	global $config, $id, $input_errors;

	if ($config['installedpackages']['frrospfdinterfaces']['config']) {
		foreach ($config['installedpackages']['frrospfdinterfaces']['config'] as $index => $conf) {
			if ($index == 0) {
				continue;
			}
			if ($id != $index && $conf['interface'] == $_POST['interface']) {
				$input_errors[] = "Interface {$_POST['interface']} is already configured.";
			}
		}
	}
	if ($_POST['md5password'] && empty($_POST['password'])) {
		$input_errors[] = "Please input a password.";
	}
}

function frr_ospf_validate_input() {
	global $config, $input_errors;

	if (!empty($_POST['routerid']) && !is_ipaddr($_POST['routerid'])) {
		$input_errors[] = "Router ID must be an address.";
	}
	if (!is_ipaddr($_POST['area'])) {
		$input_errors[] = "Area needs to be a valid ip_address.";
	}
	if ($_POST['spfholdtime'] <> "" && ($_POST['spfholdtime'] < 1 || $_POST['spfholdtime'] > 5)) {
		$input_errors[] = "SPF holdtime needs to be between 1 and 5.";
	}
	if ($_POST['spfdelay'] <> "" && ($_POST['spfdelay'] < 1 || $_POST['spfdelay'] > 10)) {
		$input_errors[] = "SPF delay needs to be between 1 and 10.";
	}
	if (!$config['installedpackages']['frrospfdinterfaces']['config']) {
		$input_errors[] = "Please select an interface to use for FRR OSPFd.";
	}
}

function frr_ospf6_interfaces_validate_input() {
	global $config, $g, $input_errors;
}

function frr_ospf6_validate_input() {
	global $config, $g, $input_errors;
}


function frr_bgp_validate_input() {
	global $config, $g, $input_errors;

	if (!empty($_POST['asnum']) && !is_numeric($_POST['asnum'])) {
		$input_errors[] = "AS must be entered as a number only.";
	}

	if (!empty($_POST['routerid']) && !is_ipaddr($_POST['routerid'])) {
		$input_errors[] = "Router ID must be an IP address.";
	}

	if (!empty($_POST['holdtime']) && !is_numeric($_POST['holdtime'])) {
		$input_errors[] = "Holdtime must be entered as a number.";
	}

	if (!empty($_POST['listenip']) && !is_ipaddr($_POST['listenip'])) {
		$input_errors[] = "Listen IP must be an IP address or blank to bind to all IP addresses.";
	}
}

function frr_bgp_advanced_validate_input() {
	global $config, $input_errors;
}
function frr_bgp_neighbors_validate_input() {
	global $config, $input_errors;
}
function frr_bgp_aspaths_validate_input() {
	global $config, $input_errors, $pkg;
	frr_sort_rows_by_seq();
}
function frr_bgp_communities_validate_input() {
	global $config, $input_errors, $pkg;
	frr_sort_rows_by_seq();
}

function frr_bgp_validate_group() {
	global $config, $g, $id, $input_errors;

	if (!is_numeric($_POST['remoteas'])) {
		$input_errors[] = "Remote AS must be entered as a number only.";
	}

	if ($_POST['name'] == "") {
		$input_errors[] = "A name is required.";
	}
}
