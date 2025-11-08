<?php
/*
 * frr_ospf6.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2025 Rubicon Communications, LLC (Netgate)
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

require_once("frr/inc/frr_ospf.inc");

function frr_generate_config_ospf6_areas() {
	global $frr_auto_config_warning;

	/* Populate FRR OSPF6 Settings */
	$frr_ospf6d_conf = config_get_path('installedpackages/frrospf6d/config/0', []);

	$defined_areas = array();
	$auto_areas = array();
	$conffile = "";

	/* Interface: Area/Range mapping configuration */
	foreach (config_get_path('installedpackages/frrospf6dinterfaces/config', []) as $conf) {
		$realif = get_real_interface($conf['interface']);
		if (empty($realif)) {
			continue;
		}
		list($interface_ip, $interface_subnet, $subnet) = frr_get_interfaceinfo($conf['interface'], true);
		$interface_area = (strlen($conf['interfacearea']) > 0) ? $conf['interfacearea'] : $frr_ospf6d_conf['defaultarea'];
		if (is_subnetv6("{$subnet}/{$interface_subnet}")) {
			$cost = (is_numeric($conf['metric']) && ($conf['metric'] >= 0)) ? $conf['metric'] : 1;
			$conffile .= " area {$interface_area} range {$subnet}/{$interface_subnet} cost {$cost}\n";
		}
	}

	/* Automatic Area: Default */
	if (!isset($auto_areas[$frr_ospf6d_conf['defaultarea']]) ||
	    !is_array($auto_areas[$frr_ospf6d_conf['defaultarea']])) {
		$auto_areas[$frr_ospf6d_conf['defaultarea']] = array();
	}
	$auto_areas[$frr_ospf6d_conf['defaultarea']]['area'] = $frr_ospf6d_conf['defaultarea'];
	$auto_areas[$frr_ospf6d_conf['defaultarea']]['type'] = $frr_ospf6d_conf['defaultareatype'];

	/* Copy automatic areas over only if they do not have custom settings */
	foreach ($auto_areas as $aa) {
		if (!array_key_exists($aa['area'], $defined_areas)) {
			$defined_areas[$aa['area']] = $aa;
		}
	}

	/* Load defined areas */
	foreach (config_get_path('installedpackages/frrospfd6areas/config', []) as $conf) {
		$defined_areas[$conf['area']] = $conf;
	}

	/* Process all areas */
	ksort($defined_areas);
	foreach ($defined_areas as $thisarea => $conf) {
		/* type */
		$areatype = "";
		switch ($conf['type']) {
			case "stub":
				$areatype .= "stub";
				break;
			case "nosum":
				$areatype .= "stub no-summary";
				break;
			/* FRR does not currently support NSSA for OSPFv3 */
			/* This has been left in and commented to add support easily at a later date */
			// case "nssa":
			// 	$areatype .= " nssa";
			// 	break;
			// case "nssanosum":
			// 	$areatype .= " nssa no-summary";
			// 	break;
			default:
				$areatype = "";
		}
		if (!empty($areatype)) {
			$conffile .=  " area {$conf['area']} {$areatype}\n";
		}

		/* Route Summarization */
		if (is_array($conf['row'])) {
			foreach ($conf['row'] as $range) {
				if (empty($range['rangeprefix'])) {
					continue;
				}
				$conffile .= " area {$conf['area']} range {$range['rangeprefix']}";
				if (!empty($range['rangenotadvertise'])) {
					$conffile .= " not-advertise";
				} elseif (!empty($range['rangecost'])) {
					$conffile .= " cost {$range['rangecost']}";
				}
				$conffile .= "\n";
			}
		}

		/* exportlist */
		if (!empty($conf['exportlist']) && ($conf['exportlist'] != "none")) {
			$conffile .= " area {$conf['area']} export-list {$conf['exportlist']}\n";
		}

		/* filterlist_out */
		if (!empty($conf['filterlist_out']) && ($conf['filterlist_out'] != "none")) {
			$conffile .= " area {$conf['area']} filter-list prefix {$conf['filterlist_out']} out\n";
		}

		/* filterlist_in */
		if (!empty($conf['filterlist_in']) && ($conf['filterlist_in'] != "none")) {
			$conffile .= " area {$conf['area']} filter-list prefix {$conf['filterlist_in']} in\n";
		}

		/* importlist */
		if (!empty($conf['importlist']) && ($conf['importlist'] != "none")) {
			$conffile .= " area {$conf['area']} import-list {$conf['importlist']}\n";
		}
	}

	return $conffile;
}

function frr_generate_config_ospf6_interfaces() {
	global $frr_integrated_config;

	/* Populate FRR OSPF6 Settings */
	$frr_ospf6d_conf = config_get_path('installedpackages/frrospf6d/config/0', []);

	$router_interface_areas = "";

	/* Setup interface entries to define network types, costs, etc. */
	foreach (config_get_path('installedpackages/frrospf6dinterfaces/config', []) as $conf) {
		$realif = get_real_interface($conf['interface']);
		if (empty($realif)) {
			continue;
		}
		/* Create interface string if it doesn't already exist */
		if (!isset($frr_integrated_config['interfaces'][$realif])) {
			$frr_integrated_config['interfaces'][$realif] = "";
		}

		/* Generate interface specific configuration */
		if (!empty($conf['descr'])) {
			$frr_integrated_config['interfaces_descr'][$realif]['ospf6d'] = $conf['descr'];
		}
		if (isset($conf['instanceid']) && (strlen($conf['instanceid']) > 0)) {
			$frr_integrated_config['interfaces'][$realif] .= " ipv6 ospf6 instance-id {$conf['instanceid']}\n";
		}
		if (!empty($conf['networktype'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ipv6 ospf6 network {$conf['networktype']}\n";
		}
		if (!empty($conf['metric'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ipv6 ospf6 cost {$conf['metric']}\n";
		}
		if (!empty($conf['deadtimer'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ipv6 ospf6 dead-interval {$conf['deadtimer']}\n";
		}
		if (!empty($conf['hellointervalinseconds'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ipv6 ospf6 hello-interval {$conf['hellointervalinseconds']}\n";
		}
		if (!empty($conf['routerpriorityelections']) || ($conf['routerpriorityelections'] == "0")) {
			$frr_integrated_config['interfaces'][$realif] .= " ipv6 ospf6 priority {$conf['routerpriorityelections']}\n";
		}
		if (!empty($conf['retransmitinterval'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ipv6 ospf6 retransmit-interval {$conf['retransmitinterval']}\n";
		}
		if (!empty($conf['passive'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ipv6 ospf6 passive\n";
		}
		if (!empty($conf['mtuignore'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ipv6 ospf6 mtu-ignore\n";
		}
		if (!empty($conf['bfdenable'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ipv6 ospf6 bfd\n";
		}

		/* Add area/instance mapping to main OSPFv3 configuration area */
		$interface_area = (strlen($conf['interfacearea']) > 0) ? $conf['interfacearea'] : $frr_ospf6d_conf['defaultarea'];
		$frr_integrated_config["ospf6d"] .= " interface {$realif} area {$interface_area}\n";
	}
}

function frr_generate_config_ospf6() {
	global $frr_integrated_config;

	/* Populate FRR Global Settings */
	$frr_conf = config_get_path('installedpackages/frr/config/0', []);
	if (empty($frr_conf)) {
		/* If FRR is not configured, stop. */
		frr_package_log("OSPF6d: No FRR global config data found.");
		return null;
	}

	/* Populate FRR OSPF6 Settings */
	$frr_ospf6d_conf = config_get_path('installedpackages/frrospf6d/config/0', []);

	if (!empty(config_get_path('installedpackages/frrglobalraw/config/0/ospf6d'))) {
		/* If the user has supplied a raw configuration, use that instead of the GUI configuration. */
		$frr_integrated_config['ospf6d'] = str_replace("\r", "", base64_decode(config_get_path('installedpackages/frrglobalraw/config/0/ospf6d')));
	} else {
		/* If there is no raw configuration and no GUI configuration, stop. */
		if (empty($frr_ospf6d_conf)) {
			frr_package_log("OSPF6d: No config data found.");
			return;
		} elseif (empty($frr_ospf6d_conf['enable'])) {
			/* OSPF6 daemon is not enabled. */
			frr_package_log("OSPF6d: OSPF6 disabled.");
			return;
		} elseif (empty($frr_conf['enable'])) {
			/* FRR is disabled or BFD Daemon is not enabled. */
			frr_package_log("OSPF6d: FRR master disabled.");
			return;
		}

		$frr_integrated_config['ospf6d'] = "";

		$redist = "";
		$noredist = "";
		/* Add entries for manually-defined networks */
		if (is_array($frr_ospf6d_conf['row'])) {
			foreach ($frr_ospf6d_conf['row'] as $redistr) {
				if (empty($redistr['routevalue'])) {
					continue;
				}
				$area = ($redistr['routearea'] == "") ? $frr_ospf6d_conf['defaultarea'] : $redistr['routearea'];
				$cost = (is_numeric($redistr['routearea']) && ($redistr['routearea'] >= 0)) ? $redistr['routearea']: 1;
				$redist .= " area {$area} range {$redistr['routevalue']} cost {$cost}\n";
			}
		}
		/* OSPF6 Settings */
		$frr_integrated_config['ospf6d'] .= "router ospf6\n";
		/* If the router ID is defined, use that, otherwise try to use
		 * the global router ID, if one is set. */
		if (is_ipaddrv4($frr_ospf6d_conf['routerid'])) {
			$frr_integrated_config['ospf6d'] .= " ospf6 router-id {$frr_ospf6d_conf['routerid']}\n";
		} elseif (is_ipaddrv4($frr_conf['routerid'])) {
			$frr_integrated_config['ospf6d'] .= " ospf6 router-id {$frr_conf['routerid']}\n";
		}

		$stub_start = " area {$frr_ospf6d_conf['defaultarea']} ";
		switch($frr_ospf6d_conf['updatefib']) {
			case "on":
			case "stub":
				$stub_start .= "stub";
				break;
			case "nosum":
				$stub_start .= "stub no-summary";
				break;
			case "nssa":
				$stub_start .= "nssa";
				break;
			default:
				$stub_start = "";
		}
		if (!empty($stub_start)) {
			$frr_integrated_config['ospf6d'] .= "{$stub_start}\n";
		}
		if ($frr_conf['logging'] && $frr_ospf6d_conf['adjacencylog']) {
			$frr_integrated_config['ospf6d'] .= " log-adjacency-changes detail\n";
		}

		/* Route Redistribution */
		$frr_integrated_config['ospf6d'] .= frr_generate_config_ospf_redistribute($frr_ospf6d_conf, 'redistributeconnectedsubnets', 'connected');
		$frr_integrated_config['ospf6d'] .= frr_generate_config_ospf_redistribute($frr_ospf6d_conf, 'redistributekernel', 'kernel');
		$frr_integrated_config['ospf6d'] .= frr_generate_config_ospf_redistribute($frr_ospf6d_conf, 'redistributebgp', 'bgp');
		$frr_integrated_config['ospf6d'] .= frr_generate_config_ospf_redistribute($frr_ospf6d_conf, 'redistributestatic', 'static');

		if ($frr_ospf6d_conf['spfholdtime'] || $frr_ospf6d_conf['spfdelay']) {
			$spf_minhold = ($frr_ospf6d_conf['spfholdtime']) ? $frr_ospf6d_conf['spfholdtime'] : 1000;
			$spf_maxhold = $spf_minhold * 10;
			$spf_delay = ($frr_ospf6d_conf['spfdelay']) ? $frr_ospf6d_conf['spfdelay'] : 200;
			$frr_integrated_config['ospf6d'] .= " timers throttle spf {$spf_delay} {$spf_minhold} {$spf_maxhold}\n";
		}

		if (!empty($frr_ospf6d_conf['referencebandwidth'])) {
			$frr_integrated_config['ospf6d'] .= " auto-cost reference-bandwidth {$frr_ospf6d_conf['referencebandwidth']}\n";
		}

		if (!empty($frr_ospf6d_conf['distance'])) {
			$frr_integrated_config['ospf6d'] .= " distance {$frr_ospf6d_conf['distance']}\n";
		}

		$odist = "";
		if (!empty($frr_ospf6d_conf['distance_external'])) {
			$odist .= " external {$frr_ospf6d_conf['distance']}";
		}
		if (!empty($frr_ospf6d_conf['distance_interarea'])) {
			$odist .= " inter-area {$frr_ospf6d_conf['distance_interarea']}";
		}
		if (!empty($frr_ospf6d_conf['distance_intraarea'])) {
			$odist .= " intra-area {$frr_ospf6d_conf['distance_intraarea']}";
		}

		if (!empty($odist)) {
			$frr_integrated_config['ospf6d'] .= " distance ospf6{$odist}\n";
		}

		if (!empty($redist)) {
			$frr_integrated_config['ospf6d'] .= $redist;
		}

		/* exportlist */
		if (!empty($frr_ospf6d_conf['exportlist']) && ($frr_ospf6d_conf['exportlist'] != "none")) {
			$frr_integrated_config['ospf6d'] .= " area {$frr_ospf6d_conf['defaultarea']} export-list {$frr_ospf6d_conf['exportlist']}\n";
		}

		/* importlist */
		if (!empty($frr_ospf6d_conf['importlist']) && ($frr_ospf6d_conf['importlist'] != "none")) {
			$frr_integrated_config['ospf6d'] .= " area {$frr_ospf6d_conf['defaultarea']} import-list {$frr_ospf6d_conf['importlist']}\n";
		}

		/* filterlist_out */
		if (!empty($frr_ospf6d_conf['filterlist_out']) && ($frr_ospf6d_conf['filterlist_out'] != "none")) {
			$frr_integrated_config['ospf6d'] .= " area {$frr_ospf6d_conf['defaultarea']} filter-list prefix {$frr_ospf6d_conf['filterlist_out']} out\n";
		}

		/* filterlist_in */
		if (!empty($frr_ospf6d_conf['filterlist_in']) && ($frr_ospf6d_conf['filterlist_in'] != "none")) {
			$frr_integrated_config['ospf6d'] .= " area {$frr_ospf6d_conf['defaultarea']} filter-list prefix {$frr_ospf6d_conf['filterlist_in']} in\n";
		}

		/* Area Settings */
		$frr_integrated_config['ospf6d'] .= frr_generate_config_ospf6_areas();

		/* Interface Settings */
		/* Interface settings do not return a string as they are an accumulation of all protocols */
		frr_generate_config_ospf6_interfaces();
	}
}
