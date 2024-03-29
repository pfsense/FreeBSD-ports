<?php
/*
 * frr_validation.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2024 Rubicon Communications, LLC (Netgate)
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

function frr_validate_intrange_input($descr, $value, $min, $max) {
	global $input_errors;
	if (($value <> "") && frr_validate_intrange($value, $min, $max) == null) {
		$input_errors[] = sprintf(gettext("%s must be an integer between %s and %s."), $descr, $min, $max);
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
	global $input_errors;

	if (($_POST['password'] <> "") && (strpos($_POST['password'], "'") !== false)) {
		$input_errors[] = "Password cannot contain a single quote (')";
	}
	if (!empty($_POST['routerid']) && !is_ipaddrv4($_POST['routerid'])) {
		$input_errors[] = "Router ID must be an address.";
	}
	foreach ($_POST as $key => $value) {
		$matches = array();
		if (preg_match("/^routevalue(\d+)$/", $key, $matches)) {
			if (isset($_POST["routevalue{$matches[1]}"]) &&
			    !empty($_POST["routevalue{$matches[1]}"]) &&
			    !is_subnet($_POST["routevalue{$matches[1]}"])) {
				$input_errors[] = "Route in row {$matches[1]} must be a subnet.";
			}
		}
	}
}

/* Read in rowhelper data from $_POST, sort it by sequence number, and put it back in POST. */
function frr_sort_rows_by_seq() {
	global $pkg;
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
	global $input_errors, $pkg;
	frr_sort_rows_by_seq();

	if (empty($_POST['name'])) {
		$input_errors[] = "A name is required.";
	}

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['name'])) {
		$input_errors[] = "Access list name may only contain the characters a-z, A-Z, 0-9, '-', '_' and '.'";
	}

	switch ($_POST['type']) {
		case "standard":
			if (!is_numeric($_POST['name']) || !(frr_validate_intrange($_POST['name'], 1, 99) || frr_validate_intrange($_POST['name'], 1300, 1999))) {
				$input_errors[] = "Standard type ACLs must have a numeric name in the range 1-99 or 1300-1999.";
			}
			if ($_POST["iptype"] != 'IPv4') {
				$input_errors[] = "Standard type ACLs are only supported for IPv4.";
			}
			break;
		case "extended":
			if (!is_numeric($_POST['name']) || !(frr_validate_intrange($_POST['name'], 100, 199) || frr_validate_intrange($_POST['name'], 2000, 2699))) {
				$input_errors[] = "Extended type ACLs must have a numeric name in the range 100-199 or 2000-2699.";
			}
			if ($_POST["iptype"] != 'IPv4') {
				$input_errors[] = "Extended type ACLs are only supported for IPv4.";
			}
			break;
		case "zebra":
			if (is_numeric($_POST['name'])) {
				$input_errors[] = "Zebra type ACLs must have a text name.";
			}
			break;
	}

	foreach ($_POST as $key => $value) {
		$matches = array();
		if (preg_match("/^seq(\d+)$/", $key, $matches)) {
			if (!is_numeric($_POST["seq{$matches[1]}"])) {
				$input_errors[] = "Sequence in row {$matches[1]} must be a number.";
			}
		}
		if (preg_match("/^action(\d+)$/", $key, $matches)) {
			if (!in_array($_POST["action{$matches[1]}"], array('deny', 'permit'))) {
				$input_errors[] = "Action in row {$matches[1]} must be Deny or Permit.";
			}
		}
		if (preg_match("/^source(\d+)$/", $key, $matches)) {
			if (!is_subnet($_POST["source{$matches[1]}"]) && (($_POST["source{$matches[1]}"] != 'any') && !($_POST["sourceany{$matches[1]}"]))) {
				$input_errors[] = "Source in row {$matches[1]} must be a subnet.";
			}
			if ((is_subnetv4($_POST["source{$matches[1]}"]) && ($_POST["iptype"] != 'IPv4')) ||
				(is_subnetv6($_POST["source{$matches[1]}"]) && ($_POST["iptype"] != 'IPv6'))) {
				$input_errors[] = "Source in row {$matches[1]} IP Type mismatch.";
			}
		}
		if (preg_match("/^destination(\d+)$/", $key, $matches)) {
			if ($_POST['type'] == 'extended') {
				if (!is_subnet($_POST["destination{$matches[1]}"]) && (($_POST["destination{$matches[1]}"] != 'any')  && !($_POST["destinationany{$matches[1]}"])))  {
					$input_errors[] = "Destination in row {$matches[1]} must be a subnet.";
				}
				if ((is_subnetv4($_POST["destination{$matches[1]}"]) && ($_POST["iptype"] != 'IPv4')) ||
					(is_subnetv6($_POST["destination{$matches[1]}"]) && ($_POST["iptype"] != 'IPv6'))) {
					$input_errors[] = "Destination in row {$matches[1]} IP Type mismatch.";
				}
			} else {
				if (!empty($_POST["destination{$matches[1]}"]) || !empty($_POST["destinationany{$matches[1]}"])) {
					$input_errors[] = "Destinations are only supported with Extended type ACLs.";
				}
			}
		}
	}
}

function frr_global_prefixes_validate_input() {
	global $input_errors, $pkg;
	frr_sort_rows_by_seq();

	if (empty($_POST['name'])) {
		$input_errors[] = "A name is required.";
	}

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['name'])) {
		$input_errors[] = "Prefix List name may only contain the characters a-z, A-Z, 0-9, '-', '_' and '.'";
	}

	/* Do not allow a name of: ACCEPTFILTER and ACCEPTFILTER6 */
	if (($_POST['name'] == 'ACCEPTFILTER') || ($_POST['name'] == 'ACCEPTFILTER6')) {
		$input_errors[] = "Prefix List name 'ACCEPTFILTER' and 'ACCEPTFILTER6' is not allowed.";
	}

	foreach ($_POST as $key => $value) {
		$matches = array();
		if (preg_match("/^seq(\d+)$/", $key, $matches)) {
			if (!is_numeric($_POST["seq{$matches[1]}"])) {
				$input_errors[] = "Sequence in row {$matches[1]} must be a number.";
			}
		}
		if (preg_match("/^action(\d+)$/", $key, $matches)) {
			if (!in_array($_POST["action{$matches[1]}"], array('deny', 'permit'))) {
				$input_errors[] = "Action in row {$matches[1]} must be Deny or Permit.";
			}
		}
		if (preg_match("/^source(\d+)$/", $key, $matches)) {
			if (!is_subnet($_POST["source{$matches[1]}"]) && (($_POST["source{$matches[1]}"] != 'any') && !($_POST["any{$matches[1]}"]))) {
				$input_errors[] = "Network in row {$matches[1]} must be a subnet.";
			}
			if ((is_subnetv4($_POST["source{$matches[1]}"]) && ($_POST["iptype"] != 'IPv4')) ||
				(is_subnetv6($_POST["source{$matches[1]}"]) && ($_POST["iptype"] != 'IPv6'))) {
				$input_errors[] = "Network in row {$matches[1]} IP Type mismatch.";
			}
		}
		if (preg_match("/^ge(\d+)$/", $key, $matches)) {
			if (!empty($_POST["ge{$matches[1]}"]) && !is_numeric($_POST["ge{$matches[1]}"])) {
				$input_errors[] = "Minimum prefix in row {$matches[1]} must be a number.";
			} elseif (isset($_POST["le{$matches[1]}"]) &&
					is_numeric($_POST["le{$matches[1]}"]) &&
					( (int) $_POST["ge{$matches[1]}"] + 1 >= (int) $_POST["le{$matches[1]}"] )) {
				$input_errors[] = "Minimum prefix in row {$matches[1]} must be less than maximum, and differ by more than 1.";
			}
		}
		if (preg_match("/^le(\d+)$/", $key, $matches)) {
			if (!empty($_POST["le{$matches[1]}"]) && !is_numeric($_POST["le{$matches[1]}"])) {
				$input_errors[] = "Maximum prefix in row {$matches[1]} must be a number.";
			}
		}
	}
}

function frr_global_routemaps_validate_input() {
	global $input_errors;

	if (empty($_POST['name'])) {
		$input_errors[] = "A name is required.";
	}

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['name'])) {
		$input_errors[] = "Route map name may only contain the characters a-z, A-Z, 0-9, '-', '_' and '.'";
	}

	/* Do not allow a name of: ACCEPTFILTER */
	if ($_POST['name'] == 'ACCEPTFILTER') {
		$input_errors[] = "Route map name 'ACCEPTFILTER' is not allowed.";
	}
}

function frr_global_raw_validate_input() {
	global $input_errors;

	/* Run commands with -C, grep -c '\*\*\* error' */
}

function frr_ospf_areas_validate_input() {
	global $id, $input_errors;
	/* area must be an IP address */
	if (!(is_ipaddr($_POST['area']) || ctype_digit($_POST['area']))) {
		$input_errors[] = "Area must be a valid IP address or integer number.";
	}

	/* type must be ('none', 'stub', 'nosum', 'nssa', 'nssanosum') */
	if (!empty($_POST['type']) && !in_array($_POST['type'], array('none', 'stub', 'nosum', 'nssa', 'nssanosum'))) {
		$input_errors[] = "Invalid Area Type.";
	}

	/* defaultcost 0-16777215 */
	frr_validate_intrange_input("Default Route Cost", $_POST['defaultcost'], 0, 16777215);

	/* authentication must be ("", "digest", "simple") */
	if (!empty($_POST['authtype']) && !in_array($_POST['authtype'], array('digest', 'simple'))) {
		$input_errors[] = "Invalid Authentication type.";
	}

	/* range array */
	foreach ($_POST as $key => $value) {
		$matches = array();

		/* rangeprefix must be a subnet */
		if (preg_match("/^rangeprefix(\d+)$/", $key, $matches)) {
			if (!empty($_POST["rangeprefix{$matches[1]}"]) &&
				!is_subnetv4($_POST["rangeprefix{$matches[1]}"])) {
				$input_errors[] = "Summary Prefix in row {$matches[1]} must be a subnet.";
			}
		}

		/* rangecost 0-16777215 */
		if (preg_match("/^rangecost(\d+)$/", $key, $matches)) {
			frr_validate_intrange_input("Summary Cost in row {$matches[1]}", $_POST['rangecost{$matches[1]}'], 0, 16777215);
			if (($_POST['rangecost{$matches[1]}'] <> "") &&
				(!empty($_POST['notadvertise{$matches[1]}']) ||
				!empty($_POST['subprefix{$matches[1]}']))) {
				$input_errors[] = "Only one of Do Not Advertise, Cost, or Substitution Prefix may be chosen in row {$matches[1]}.";
			}
		}

		/* subprefix must be a subnet */
		if (preg_match("/^subprefix(\d+)$/", $key, $matches)) {
			if (($_POST['subprefix{$matches[1]}'] <> "") &&
				!is_subnetv4($_POST["subprefix{$matches[1]}"])) {
				$input_errors[] = "Substitute Prefix in row {$matches[1]} must be a subnet.";
			}
			if (!empty($_POST['subprefix{$matches[1]}']) &&
				(!empty($_POST['notadvertise{$matches[1]}']) ||
				!empty($_POST['rangecost{$matches[1]}']))) {
				$input_errors[] = "Only one of Do Not Advertise, Cost, or Substitution Prefix may be chosen in row {$matches[1]}.";
			}
		}
	}
}

function frr_ospf_neighbors_validate_input() {
	global $id, $input_errors;
	if (!is_ipaddrv4($_POST['neighbor'])) {
		$input_errors[] = "Neighbor must be a valid IPv4 address.";
	}

	frr_validate_intrange_input("Neighbor Priority", $_POST['priority'], 0, 255);
	frr_validate_intrange_input("Dead Neighbor Polling interval", $_POST['pollinginterval'], 1, 65535);
}

function frr_ospf_interfaces_validate_input() {
	global $id, $input_errors;

	foreach (config_get_path('installedpackages/frrospfdinterfaces/config', []) as $index => $conf) {
		if ($index == 0) {
			continue;
		}
		if ($id != $index && $conf['interface'] == $_POST['interface']) {
			$input_errors[] = "Interface {$_POST['interface']} is already configured.";
		}
	}
	frr_validate_intrange_input("Cost", $_POST['metric'], 1, 65535);
	if ((strlen($_POST['interfacearea']) > 0) && !(is_ipaddr($_POST['interfacearea']) || ctype_digit($_POST['interfacearea']))) {
		$input_errors[] = "Area must be a valid IP address or integer number.";
	}
	if ((strlen($_POST['interfacearea']) == 0) && (strlen(config_get_path('installedpackages/frrospfd/config/0/defaultarea')) == 0)) {
		$input_errors[] = "Area must either be set on the interface or a default area set for the OSPF instance.";
	}
	if (!empty($_POST['networktype']) && !in_array($_POST['networktype'], array('broadcast', 'non-broadcast', 'point-to-multipoint', 'point-to-point'))) {
		$input_errors[] = "Invalid interface type.";
	}
	if (!empty($_POST['authtype']) && !in_array($_POST['authtype'], array('digest', 'simple', 'null'))) {
		$input_errors[] = "Invalid authentication type.";
	}
	switch ($_POST['authtype']) {
		case "digest":
			if (empty($_POST['password'])) {
				$input_errors[] = "Please input a password.";
			} elseif (strlen($_POST['password']) > 16) {
				$input_errors[] = "Message Digest Password must be 16 characters or less.";
			}
			break;
		case "simple":
			if (empty($_POST['password'])) {
				$input_errors[] = "Please input a password.";
			} elseif (strlen($_POST['password']) > 8) {
				$input_errors[] = "Simple Password must be 8 characters or less.";
			}
			break;
		default:
			break;
	}
	frr_validate_intrange_input("Router Priority", $_POST['routerpriorityelections'], 0, 255);
	frr_validate_intrange_input("Retransmit Interval", $_POST['retransmitinterval'], 3, 65535);
	frr_validate_intrange_input("Hello Interval", $_POST['hellointervalinseconds'], 1, 65535);
	frr_validate_intrange_input("Dead Interval", $_POST['deadtimer'], 1, 65535);
	frr_validate_intrange_input("Minimal Hello", $_POST['minimalhello'], 1, 10);
	if (!empty($_POST['minimalhello']) && (!empty($_POST['deadtimer']) || !empty($_POST['hellointervalinseconds']))) {
		$input_errors[] = "Minimal Hello suppresses Hello Interval and Dead Interval.";
	}
}

function frr_ospf_validate_input() {
	global $input_errors;

	if (!empty($_POST['routerid']) && !is_ipaddr($_POST['routerid'])) {
		$input_errors[] = "Router ID must be an address.";
	}

	if (strlen($_POST['defaultarea']) > 0) {
		if (!(is_ipaddr($_POST['defaultarea']) || ctype_digit($_POST['defaultarea']))) {
			$input_errors[] = "Default Area must be a valid IP address or integer number.";
		}
	} else {
		$interfaces_no_area = array();
		foreach (config_get_path('installedpackages/frrospfdinterfaces/config', []) as $interface) {
			if (strlen($interface['interfacearea']) == 0) {
				$interfaces_no_area[] = $interface['interface'];
			}
		}
		if (!empty($interfaces_no_area)) {
			$interfaces_no_area = implode(" ", $interfaces_no_area);
			$input_errors[] = "Default Area cannot be cleared as there are interfaces configured without specifically defined areas: {$interfaces_no_area}";
		}
	}

	for ($x = 0; $x < 99; $x++) {
		if ((isset($_POST["routevalue{$x}"]) && !empty($_POST["routevalue{$x}"])) ||
		    (isset($_POST["routearea{$x}"]) && !empty($_POST["routearea{$x}"]))) {
			if (!is_subnetv4($_POST["routevalue{$x}"])) {
				$input_errors[] = "OSPF Network must be an IPv4 subnet.";
			}
			if (!is_ipaddrv4($_POST["routearea{$x}"])) {
				$input_errors[] = "Area ID must be an IPv4 address.";
			}
		} else {
			break;
		}
	}

	/* spfholdtime 0-60000 */
	frr_validate_intrange_input("SPF Hold Time", $_POST['spfholdtime'], 0, 60000);
	/* spfdelay 0-600000 */
	frr_validate_intrange_input("SPF Delay", $_POST['spfdelay'], 0, 60000);

	/* redistributeconnectedsubnets_metric (0-16777214) */
	frr_validate_intrange_input("Connected Metric", $_POST['redistributeconnectedsubnets_metric'], 0, 16777214);
	/* redistributeconnectedsubnets_metrictype (1-2) */
	frr_validate_intrange_input("Connected Metric Type", $_POST['redistributeconnectedsubnets_metrictype'], 1, 2);

	/* redistributekernel_metric (0-16777214) */
	frr_validate_intrange_input("Kernel Metric", $_POST['redistributekernel_metric'], 0, 16777214);
	/* redistributekernel_metrictype (1-2) */
	frr_validate_intrange_input("Kernel Metric Type", $_POST['redistributekernel_metrictype'], 1, 2);

	/* redistributebgp_metric (0-16777214) */
	frr_validate_intrange_input("BGP Metric", $_POST['redistributebgp_metric'], 0, 16777214);
	/* redistributebgp_metrictype (1-2) */
	frr_validate_intrange_input("BGP Metric Type", $_POST['redistributebgp_metrictype'], 1, 2);

	/* redistributestatic_metric (0-16777214) */
	frr_validate_intrange_input("Static Metric", $_POST['redistributestatic_metric'], 0, 16777214);
	/* redistributestatic_metrictype (1-2) */
	frr_validate_intrange_input("Static Metric Type", $_POST['redistributestatic_metrictype'], 1, 2);

	/* redistributedefaultroute_metric (0-16777214) */
	frr_validate_intrange_input("Default Route Metric", $_POST['redistributedefaultroute_metric'], 0, 16777214);
	/* redistributedefaultroute_metrictype (1-2) */
	frr_validate_intrange_input("Default Route Metric Type", $_POST['redistributedefaultroute_metrictype'], 1, 2);

	/* referencebandwidth (1-4294967) */
	frr_validate_intrange_input("Reference Bandwidth", $_POST['referencebandwidth'], 1, 4294967);
	/* max_metric_startup (5-86400) */
	frr_validate_intrange_input("Max Metric Seconds at Startup", $_POST['max_metric_startup'], 5, 86400);
	/* max_metric_shutdown (5-86400) */
	frr_validate_intrange_input("Max Metric Seconds at Shutdown", $_POST['max_metric_shutdown'], 5, 86400);
	/* writemultiplier (1-100) */
	frr_validate_intrange_input("Write Multiplier", $_POST['writemultiplier'], 1, 100);
}

function frr_ospf6_interfaces_validate_input() {
	global $g, $input_errors;

	if ((strlen($_POST['interfacearea']) > 0) && (!(is_ipaddr($_POST['interfacearea']) || ctype_digit($_POST['interfacearea'])))) {
		$input_errors[] = "Area must be a valid IP address or integer number.";
	}

	if ((strlen($_POST['interfacearea']) == 0) && (strlen(config_get_path('installedpackages/frrospf6d/config/0/defaultarea')) == 0)) {
		$input_errors[] = "Area must either be set on the interface or a default given for the OSPFv3 instance.";
	}
}

function frr_ospf6_areas_validate_input() {
	global $id, $input_errors;
	/* area must be an IP address */
	if (!(is_ipaddr($_POST['area']) || ctype_digit($_POST['area']))) {
		$input_errors[] = "Area must be a valid IP address or integer number.";
	}

	/* type must be ('none', 'stub', 'nosum', 'nssa', 'nssanosum') */
	if (!empty($_POST['type']) && !in_array($_POST['type'], array('none', 'stub', 'nosum', 'nssa', 'nssanosum'))) {
		$input_errors[] = "Invalid Area Type.";
	}

	/* range array */
	foreach ($_POST as $key => $value) {
		$matches = array();

		/* rangeprefix must be a subnet */
		if (preg_match("/^rangeprefix(\d+)$/", $key, $matches)) {
			if (!empty($_POST["rangeprefix{$matches[1]}"]) &&
				!is_subnetv6($_POST["rangeprefix{$matches[1]}"])) {
				$input_errors[] = "Summary Prefix in row {$matches[1]} must be a subnet.";
			}
		}

		/* rangecost 0-16777215 */
		if (preg_match("/^rangecost(\d+)$/", $key, $matches)) {
			frr_validate_intrange_input("Summary Cost in row {$matches[1]}", $_POST['rangecost{$matches[1]}'], 0, 16777215);
			if (($_POST['rangecost{$matches[1]}'] <> "") &&
				(!empty($_POST['notadvertise{$matches[1]}']) ||
				!empty($_POST['subprefix{$matches[1]}']))) {
				$input_errors[] = "Only one of Do Not Advertise, Cost, or Substitution Prefix may be chosen in row {$matches[1]}.";
			}
		}

		/* subprefix must be a subnet */
		if (preg_match("/^subprefix(\d+)$/", $key, $matches)) {
			if (($_POST['subprefix{$matches[1]}'] <> "") &&
				!is_subnetv6($_POST["subprefix{$matches[1]}"])) {
				$input_errors[] = "Substitute Prefix in row {$matches[1]} must be a subnet.";
			}
			if (!empty($_POST['subprefix{$matches[1]}']) &&
				(!empty($_POST['notadvertise{$matches[1]}']) ||
				!empty($_POST['rangecost{$matches[1]}']))) {
				$input_errors[] = "Only one of Do Not Advertise, Cost, or Substitution Prefix may be chosen in row {$matches[1]}.";
			}
		}
	}
}

function frr_ospf6_validate_input() {
	global $g, $input_errors;

	if (!empty($_POST['routerid']) && !is_ipaddr($_POST['routerid'])) {
		$input_errors[] = "Router ID must be an IP address.";
	}

	if (strlen($_POST['defaultarea']) > 0) {
		if (!(is_ipaddr($_POST['defaultarea']) || ctype_digit($_POST['defaultarea']))) {
			$input_errors[] = "Default Area must be a valid IP address or integer number.";
		}
	} else {
		$interfaces_no_area = array();
		foreach (config_get_path('installedpackages/frrospf6dinterfaces/config', []) as $interface) {
			if (strlen($interface['interfacearea']) == 0) {
				$interfaces_no_area[] = $interface['interface'];
			}
		}
		if (!empty($interfaces_no_area)) {
			$interfaces_no_area = implode(" ", $interfaces_no_area);
			$input_errors[] = "Default Area cannot be cleared as there are interfaces configured without specifically defined areas: {$interfaces_no_area}";
		}
	}
}

function frr_bgp_validate_input() {
	global $g, $input_errors;

	if (!empty($_POST['enable']) && empty($_POST['asnum'])) {
		$input_errors[] = "AS must be provided if BGP is enabled.";
	}

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

	if (strlen($_POST['rpkipollingperiod']) > 0) {
		if (!is_numeric($_POST['rpkipollingperiod'])) {
			$input_errors[] = "Polling period must be a number.";
		}
		/*polling_period (1-86400)*/
		frr_validate_intrange_input("Polling period", $_POST['rpkipollingperiod'], 1, 86400);
	}

	if (strlen($_POST['rpkiexpireinterval']) > 0) {
		if (!is_numeric($_POST['rpkiexpireinterval'])) {
			$input_errors[] = "Expire interval must be a number.";
		}
		/*expire_interval (600-172800)*/
		frr_validate_intrange_input("Expire interval", $_POST['rpkiexpireinterval'], 600, 172800);
	}

	if (strlen($_POST['rpkiretryinterval']) > 0) {
		if (!is_numeric($_POST['rpkiretryinterval'])) {
			$input_errors[] = "Retry interval must be a number.";
		}
		/*retry_interval (1-7200)*/
		frr_validate_intrange_input("Polling period", $_POST['rpkiretryinterval'], 1, 7200);
	}
}

function frr_bgp_advanced_validate_input() {
	global $input_errors;
}

function frr_bgp_neighbors_validate_input() {
	global $input_errors;
}

function frr_bgp_aspaths_validate_input() {
	global $input_errors, $pkg;
	frr_sort_rows_by_seq();
}

function frr_bgp_communities_validate_input() {
	global $input_errors, $pkg;
	frr_sort_rows_by_seq();
}

function frr_bgp_validate_group() {
	global $g, $id, $input_errors;

	if (!is_numeric($_POST['remoteas'])) {
		$input_errors[] = "Remote AS must be entered as a number only.";
	}

	if ($_POST['name'] == "") {
		$input_errors[] = "A name is required.";
	}
}

function frr_bgp_rpki_cache_server_validate_input() {
	global $input_errors;

	if (strlen($_POST['port']) > 0) {
		if (!is_numeric($_POST['port'])) {
			$input_errors[] = "Port must be a number.";
		}
		/*preference (1-255)*/
		frr_validate_intrange_input("Port", $_POST['port'], 1, 65535);
	}

	if (strlen($_POST['preference']) > 0) {
		if (!is_numeric($_POST['preference'])) {
			$input_errors[] = "Preference must be a number.";
		}
		/*preference (1-255)*/
		frr_validate_intrange_input("Preference", $_POST['preference'], 1, 255);
	}
}

function frr_bfd_validate_input() {
	global $input_errors;
}

function frr_bfd_peer_validate_input() {
	global $input_errors;

	if (!empty($_POST['detectmultiplier'])) {
		if (!is_numeric($_POST['detectmultiplier'])) {
			$input_errors[] = "Detect multiplier must be a number.";
		}
		/*detect-multiplier (2-255)*/
		frr_validate_intrange_input("Detect multiplier", $_POST['detectmultiplier'], 2, 255);
	}

	if (!empty($_POST['receiveinterval'])) {
		if (!is_numeric($_POST['receiveinterval'])) {
			$input_errors[] = "Receive interval must be a number.";
		}
		/*receive-interval (10-60000)*/
		frr_validate_intrange_input("Receive interval", $_POST['receiveinterval'], 10, 60000);
	}

	if (!empty($_POST['transmitinterval'])) {
		if (!is_numeric($_POST['transmitinterval'])) {
			$input_errors[] = "Transmit interval must be a number.";
		}
		/*transmit-interval (10-60000)*/
		frr_validate_intrange_input("Transmit interval", $_POST['transmitinterval'], 10, 60000);
	}

	if (!empty($_POST['echointerval'])) {
		if (!is_numeric($_POST['echointerval'])) {
			$input_errors[] = "Echo interval must be a number.";
		}
		/*echo-interval (10-60000)*/
		frr_validate_intrange_input("Echo interval", $_POST['echointerval'], 10, 60000);
	}

	if (!empty($_POST['minimumttl'])) {
		if (!is_numeric($_POST['minimumttl'])) {
			$input_errors[] = "Minimum TTL must be a number.";
		}
		/*minimum-ttl (1-254)*/
		frr_validate_intrange_input("Minimum TTL", $_POST['minimumttl'], 1, 254);
	}
}

function frr_bfd_profile_validate_input() {
	global $input_errors;

	if (!empty($_POST['detectmultiplier'])) {
		if (!is_numeric($_POST['detectmultiplier'])) {
			$input_errors[] = "Detect multiplier must be a number.";
		}
		/*detect-multiplier (2-255)*/
		frr_validate_intrange_input("Detect multiplier", $_POST['detectmultiplier'], 2, 255);
	}

	if (!empty($_POST['receiveinterval'])) {
		if (!is_numeric($_POST['receiveinterval'])) {
			$input_errors[] = "Receive interval must be a number.";
		}
		/*receive-interval (10-60000)*/
		frr_validate_intrange_input("Receive interval", $_POST['receiveinterval'], 10, 60000);
	}

	if (!empty($_POST['transmitinterval'])) {
		if (!is_numeric($_POST['transmitinterval'])) {
			$input_errors[] = "Transmit interval must be a number.";
		}
		/*transmit-interval (10-60000)*/
		frr_validate_intrange_input("Transmit interval", $_POST['transmitinterval'], 10, 60000);
	}

	if (!empty($_POST['echointerval'])) {
		if (!is_numeric($_POST['echointerval'])) {
			$input_errors[] = "Echo interval must be a number.";
		}
		/*echo-interval (10-60000)*/
		frr_validate_intrange_input("Echo interval", $_POST['echointerval'], 10, 60000);
	}

	if (!empty($_POST['minimumttl'])) {
		if (!is_numeric($_POST['minimumttl'])) {
			$input_errors[] = "Minimum TTL must be a number.";
		}
		/*minimum-ttl (1-254)*/
		frr_validate_intrange_input("Minimum TTL", $_POST['minimumttl'], 1, 254);
	}
}

function frr_rip_validate_input() {
	global $input_errors;

	for ($x = 0; $x < 99; $x++) {
		if (isset($_POST["routevalue{$x}"]) && !empty($_POST["routevalue{$x}"]) &&
		    !is_subnetv4($_POST["routevalue{$x}"])) {
			$input_errors[] = "RIP Network must be an IPv4 subnet.";
		} else {
			break;
		}
	}

	frr_validate_intrange_input("Connected Metric", $_POST['redistributeconnectedsubnets_metric'], 0, 16);
	frr_validate_intrange_input("Kernel Metric", $_POST['redistributekernel_metric'], 0, 16);
	frr_validate_intrange_input("BGP Metric", $_POST['redistributebgp_metric'], 0, 16);
	frr_validate_intrange_input("BGP Metric", $_POST['redistributeospf_metric'], 0, 16);
	frr_validate_intrange_input("Static Metric", $_POST['redistributestatic_metric'], 0, 16);
	frr_validate_intrange_input("Default Route Metric", $_POST['redistributedefaultroute_metric'], 0, 16);
}

function frr_rip_interfaces_validate_input() {
	global $id, $input_errors;

	foreach (config_get_path('installedpackages/frrripdinterfaces/config', []) as $index => $conf) {
		if ($index == 0) {
			continue;
		}
		if ($id != $index && $conf['interface'] == $_POST['interface']) {
			$input_errors[] = "Interface {$_POST['interface']} is already configured.";
		}
	}
	if (!empty($_POST['authtype']) && !in_array($_POST['authtype'], array('digest', 'simple', 'null'))) {
		$input_errors[] = "Invalid authentication type.";
	}
	if (!empty($_POST['authtype']) && empty($_POST['password'])) {
		$input_errors[] = "Please input a password.";
	} elseif (!empty($_POST['authtype']) && (strlen($_POST['password']) > 16)) {
		$input_errors[] = "Message Digest Password must be 16 characters or less.";
	}
}

function frr_rip_neighbors_validate_input() {
	global $id, $input_errors;
	if (!is_ipaddrv4($_POST['neighbor'])) {
		$input_errors[] = "Neighbor must be a valid IPv4 address.";
	}
}
