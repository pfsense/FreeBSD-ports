<?php
/*
 * pimd_validation.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2020 Rubicon Communications, LLC (Netgate)
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

function pimd_validate_intrange($value, $min, $max) {
	if (is_numericint($value) && ($value >= $min) && ($value <= $max)) {
		return $value;
	} else {
		return null;
	}
}

function pimd_validate_intrange_input($descr, $value, $min, $max) {
	global $input_errors;
	if (($value <> "") && pimd_validate_intrange($value, $min, $max) == null) {
		$input_errors[] = sprintf(gettext("%s must be an integer between %s and %s."), $descr, $min, $max);
	}
}

function pimd_validate_input_general() {
	global $config, $input_errors;

	/* interfacemode (none, all) */
	if (!in_array($_POST['interfacemode'], array('none', 'all'))) {
		$input_errors[] = gettext('Invalid Default Bind selection');
	}

	/* loglevel (none, error, warning, notice, info, debug) */
	if (!in_array($_POST['loglevel'], array('none', 'error', 'warning', 'notice', 'info', 'debug'))) {
		$input_errors[] = gettext('Invalid Log Level selection');
	}

	/* carpstatusvip (in pimd_get_carp_list() values) */
	if (!in_array($_POST['carpstatusvip'], pimd_get_list_values(pimd_get_carp_list()))) {
		$input_errors[] = gettext('Invalid CARP Status VIP selection');
	}

	/* default_route_distance (1-255, Default: 101) */
	pimd_validate_intrange_input("Default Route Distance", $_POST['default_route_distance'], 1, 255);

	/* default_route_metric (1-1024, Default: 1024) */
	pimd_validate_intrange_input("Default Route Metric", $_POST['default_route_metric'], 0, 1024);

	/* igmp_query_interval (1-65535, Default: 12) */
	pimd_validate_intrange_input("IGMP Query Interval", $_POST['igmp_query_interval'], 1, 65535);

	/* igmp_querier_timeout (8-65535, Default: 42) */
	pimd_validate_intrange_input("IGMP Querier Timeout", $_POST['igmp_querier_timeout'], 8, 65535);

	/* hello_interval (30-18724, Default: 30) */
	pimd_validate_intrange_input("Hello Interval", $_POST['hello_interval'], 30, 18724);

	/* spt_type (default, rate, packets, infinity) */
	if (!in_array($_POST['spt_type'], array('default', 'rate', 'packets', 'infinity'))) {
		$input_errors[] = gettext('Invalid Threshold Type selection');
	}

	/* spt_value */
	pimd_validate_intrange_input("SPT Value", $_POST['spt_value'], 0, 16777214);

	/* spt_interval (1-65535, Default 100) */
	pimd_validate_intrange_input("SPT Interval", $_POST['spt_interval'], 1, 65535);

}

function pimd_validate_input_interfaces() {
	global $config, $input_errors;

	/* Interface name */
	if (!in_array($_POST['interface'], pimd_get_list_values(pimd_get_interfaces(false)))) {
		$input_errors[] = gettext('Invalid Interface selection');
	}

	/* Enable/Disable -- Selection, not checkbox, since behavior can be inverted */
	if (!in_array($_POST['enable'], array('default', 'enable', 'disable'))) {
		$input_errors[] = gettext('Invalid Enable selection');
	}

	/* IGMP Version (Default, 3, 2) */
	if (!in_array($_POST['igmpversion'], array('default', '3', '2'))) {
		$input_errors[] = gettext('Invalid IGMP Version selection');
	}

	/* dr-priority <1-4294967294> (Default: 1) */
	pimd_validate_intrange_input("DR Priority", $_POST['dr_priority'], 1, 4294967294);

	/* ttl-threshold <1-255> (Default: 1) */
	pimd_validate_intrange_input("TTL Threshold", $_POST['ttl_threshold'], 1, 255);

	/* distance <1-255> (Default: Global default-route-distance) */
	pimd_validate_intrange_input("Route Distance", $_POST['distance'], 1, 255);

	/* metric <1-1024> (Default: Global default-route-metric) */
	pimd_validate_intrange_input("Route Metric", $_POST['metric'], 1, 1024);

	/* altnet <network/len> */
	if (!empty($_POST['altnet']) && !is_v4($_POST['altnet'])) {
		$input_errors[] = gettext('Invalid Alternative Network value');
	}
	/* scoped <network/len> */
	if (!empty($_POST['scoped']) && !is_v4($_POST['scoped'])) {
		$input_errors[] = gettext('Invalid Multicast Scope value');
	}

	if (empty($input_errors)) {
		$_POST['ifname'] = pimd_find_interface_name($_POST['interface']);
	}
}

function pimd_validate_input_bsrcandidate() {
	global $config, $input_errors;

	/* Interface name */
	if (!in_array($_POST['interface'], pimd_get_list_values(pimd_get_interfaces()))) {
		$input_errors[] = gettext('Invalid Interface selection');
	}
	/* priority <0-255> (Default: 0) */
	pimd_validate_intrange_input("Priority", $_POST['priority'], 0, 255);

	if (empty($input_errors)) {
		$_POST['ifname'] = pimd_find_interface_name($_POST['interface']);
	}

}

function pimd_validate_input_rpcandidate() {
	global $config, $input_errors, $pkg;

	/* Interface name */
	if (!in_array($_POST['interface'], pimd_get_list_values(pimd_get_interfaces()))) {
		$input_errors[] = gettext('Invalid Interface selection');
	}
	/* priority <0-255> (Default: 0) */
	pimd_validate_intrange_input("Priority", $_POST['priority'], 0, 255);

	/* time <10-16383> (Default: 30) */
	pimd_validate_intrange_input("Time", $_POST['time'], 10, 16383);

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

	foreach ($pkgarr['row'] as $idx => $row) {
		foreach (array_keys($row) as $keyname) {
			if (($keyname == 'groupprefix') && !is_v4($_POST["{$keyname}{$idx}"])) {
				$input_errors[] = gettext('Invalid Group Prefix value');
			}
		}
	}

	if (empty($input_errors)) {
		$_POST['ifname'] = pimd_find_interface_name($_POST['interface']);
	}

}

function pimd_validate_input_rpaddress() {
	global $config, $input_errors;
	/* RP Address */
	if (!empty($_POST['address']) && !is_ipaddrv4($_POST['address'])) {
		$input_errors[] = gettext('Invalid RP Address');
	}
	/* group (network/len) */
	if (!empty($_POST['mcgroup']) && !is_v4($_POST['mcgroup'])) {
		$input_errors[] = gettext('Invalid Multicast Group value');
	}
}

