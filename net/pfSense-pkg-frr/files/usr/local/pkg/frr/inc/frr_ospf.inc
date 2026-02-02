<?php
/*
 * frr_ospf.inc
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

function frr_generate_config_ospf_areas() {
	global $frr_config_base, $frr_auto_config_warning;

	/* Populate FRR OSPF Settings */
	$frr_ospfd_conf = config_get_path('installedpackages/frrospfd/config/0', []);

	$defined_areas = array();
	$auto_areas = array();
	$aconf = "";

	/* Automatic Area: Default */
	if (!isset($auto_areas[$frr_ospfd_conf['defaultarea']]) ||
	    !is_array($auto_areas[$frr_ospfd_conf['defaultarea']])) {
		$auto_areas[$frr_ospfd_conf['defaultarea']] = array();
	}
	$auto_areas[$frr_ospfd_conf['defaultarea']]['area'] = $frr_ospfd_conf['defaultarea'];
	$auto_areas[$frr_ospfd_conf['defaultarea']]['type'] = $frr_ospfd_conf['defaultareatype'];

	/* Load defined areas */
	foreach (config_get_path('installedpackages/frrospfdareas/config', []) as $conf) {
		$defined_areas[$conf['area']] = $conf;
	}

	/* Copy automatic areas over only if they do not have custom settings */
	foreach ($auto_areas as $aa) {
		if (!array_key_exists($aa['area'], $defined_areas)) {
			$defined_areas[$aa['area']] = $aa;
		}
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
			case "nssa":
				$areatype .= "nssa";
				break;
			case "nssanosum":
				$areatype .= "nssa no-summary";
				break;
			default:
				$areatype = "";
		}
		if (!empty($areatype)) {
			$aconf .= " area {$conf['area']} {$areatype}\n";
		}

		/* defaultcost */
		if (!empty($conf['defaultcost'])) {
			$aconf .= " area {$conf['area']} default-cost {$conf['defaultcost']}\n";
		}

		/* shortcut */
		if (!empty($conf['shortcut'])) {
			$aconf .= " area {$conf['area']} shortcut {$conf['shortcut']}\n";
		}

		/* authentication */
		switch ($conf['authtype']) {
			case "digest":
				$aconf .= " area {$conf['area']} authentication message-digest\n";
				break;
			case "simple":
				$aconf .= " area {$conf['area']} authentication\n";
				break;
			default:
				break;
		}

		/* Route Summarization */
		if (is_array($conf['row'])) {
			foreach ($conf['row'] as $range) {
				if (empty($range['rangeprefix'])) {
					continue;
				}
				$aconf .= " area {$conf['area']} range {$range['rangeprefix']}";
				if (!empty($range['rangenotadvertise'])) {
					$aconf .= " not-advertise";
				} elseif (!empty($range['rangecost'])) {
					$aconf .= " cost {$range['rangecost']}";
				} elseif (!empty($range['subprefix'])) {
					$aconf .= " substitute {$range['subprefix']}";
				}
				$aconf .= "\n";
			}
		}

		/* exportlist */
		if (!empty($conf['exportlist']) && ($conf['exportlist'] != "none")) {
			$aconf .= " area {$conf['area']} export-list {$conf['exportlist']}\n";
		}

		/* filterlist_out */
		if (!empty($conf['filterlist_out']) && ($conf['filterlist_out'] != "none")) {
			$aconf .= " area {$conf['area']} filter-list prefix {$conf['filterlist_out']} out\n";
		}

		/* filterlist_in */
		if (!empty($conf['filterlist_in']) && ($conf['filterlist_in'] != "none")) {
			$aconf .= " area {$conf['area']} filter-list prefix {$conf['filterlist_in']} in\n";
		}

		/* importlist */
		if (!empty($conf['importlist']) && ($conf['importlist'] != "none")) {
			$aconf .= " area {$conf['area']} import-list {$conf['importlist']}\n";
		}
	}

	return $aconf;
}

function frr_generate_config_ospf_interfaces() {
	global $passive_interfaces, $frr_integrated_config;

	/* Populate FRR OSPF Settings */
	$frr_ospfd_conf = config_get_path('installedpackages/frrospfd/config/0', []);

	/* Setup interface entries to define network types, costs, etc. */
	foreach (config_get_path('installedpackages/frrospfdinterfaces/config', []) as $conf) {
		$realif = get_real_interface($conf['interface']);
		if (empty($realif)) {
			continue;
		}
		/* Create interface string if it doesn't already exist */
		if (!isset($frr_integrated_config['interfaces'][$realif])) {
			$frr_integrated_config['interfaces'][$realif] = "";
		}

		if (!empty($conf['descr'])) {
			$frr_integrated_config['interfaces_descr'][$realif]['ospfd'] = $conf['descr'];
		}
		if (!empty($conf['networktype'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ip ospf network {$conf['networktype']}\n";
		}
		if (!empty($conf['metric'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ip ospf cost {$conf['metric']}\n";
		}

		if (!empty($conf['password'])) {
			switch ($conf['authtype']) {
				case "digest":
					$frr_integrated_config['interfaces'][$realif] .= " ip ospf authentication message-digest\n";
					$frr_integrated_config['interfaces'][$realif] .= " ip ospf message-digest-key 1 md5 " . substr($conf['password'], 0, 16) . "\n";
					break;
				case "simple":
					$frr_integrated_config['interfaces'][$realif] .= " ip ospf authentication\n";
					$frr_integrated_config['interfaces'][$realif] .= " ip ospf authentication-key " . substr($conf['password'], 0, 8) . "\n";
					break;
				case "null":
					$frr_integrated_config['interfaces'][$realif] .= " ip ospf authentication null\n";
					break;
				default:
					break;
			}
		}

		if (!empty($conf['routerpriorityelections']) || ($conf['routerpriorityelections'] == "0")) {
			$frr_integrated_config['interfaces'][$realif] .= " ip ospf priority {$conf['routerpriorityelections']}\n";
		}
		if (!empty($conf['retransmitinterval'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ip ospf retransmit-interval {$conf['retransmitinterval']}\n";
		}

		if (!empty($conf['minimalhello'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ip ospf dead-interval minimal hello-multiplier {$conf['minimalhello']}\n";
		} else {
			if (!empty($conf['deadtimer'])) {
				$frr_integrated_config['interfaces'][$realif] .= " ip ospf dead-interval {$conf['deadtimer']}\n";
			}
			if (!empty($conf['hellointervalinseconds'])) {
				$frr_integrated_config['interfaces'][$realif] .= " ip ospf hello-interval {$conf['hellointervalinseconds']}\n";
			}
		}

		if (!empty($conf['bfdenable'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ip ospf bfd\n";
		}

		if (!empty($conf['mtuignore'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ip ospf mtu-ignore\n";
		}
		if (!empty($conf['passive'])) {
			$passive_interfaces[] = $realif;
		}
		list($interface_ip, $interface_subnet, $subnet) = frr_get_interfaceinfo($conf['interface']);
		if (empty($interface_ip)) {
			continue;
		}
		$area = (strlen($conf['interfacearea']) > 0) ? $conf['interfacearea'] : $frr_ospfd_conf['defaultarea'];
		$frr_integrated_config['interfaces'][$realif] .= " ip ospf area {$area}\n";
	}
}

function frr_generate_config_ospf_neighbors() {
	$neiconf= "";

	foreach (config_get_path('installedpackages/frrospfdneighbors/config', []) as $conf) {
		$neiconf .= " neighbor {$conf['neighbor']}" ;
		if (!empty($conf['priority'])) {
			$neiconf .= " priority {$conf['priority']}";
		}
		if (!empty($conf['pollinginterval'])) {
			$neiconf .= " poll-interval {$conf['pollinginterval']}";
		}
		$neiconf .= "\n" ;
	}
	return $neiconf;
}

function frr_generate_config_ospf_redistribute($frr_ospfd_conf, $variablename, $sourcename) {
	$redistconf = "";
	if ($frr_ospfd_conf[$variablename]) {
		$redistconf .= " redistribute {$sourcename}";
		if (!empty($frr_ospfd_conf[$variablename . '_routemap']) &&
			($frr_ospfd_conf[$variablename . '_routemap'] != 'none')) {
			$redistconf .= " route-map {$frr_ospfd_conf[$variablename . '_routemap']}";
		}
		if (!empty($frr_ospfd_conf[$variablename . '_metric']) &&
			($frr_ospfd_conf[$variablename . '_metric'] != 'none')) {
			$redistconf .= " metric {$frr_ospfd_conf[$variablename . '_metric']}";
		}
		if (!empty($frr_ospfd_conf[$variablename . '_metrictype']) &&
			($frr_ospfd_conf[$variablename . '_metrictype'] != 'none')) {
			$redistconf .= " metric-type {$frr_ospfd_conf[$variablename . '_metrictype']}";
		}
		$redistconf .= "\n";
		if (!empty($frr_ospfd_conf[$variablename . '_distlist']) &&
			($frr_ospfd_conf[$variablename . '_distlist'] != 'none')) {
			$redistconf .= " distribute-list {$frr_ospfd_conf[$variablename . '_distlist']} out {$sourcename}\n";
		}
	}
	return $redistconf;
}

function frr_generate_config_ospf() {
	global $frr_config_base, $frr_integrated_config, $passive_interfaces;

	/* Populate FRR Global Settings */
	$frr_conf = config_get_path('installedpackages/frr/config/0', []);
	if (empty($frr_conf)) {
		/* If FRR is not configured, stop. */
		frr_package_log("OSPFd: No FRR global config data found.");
		return null;
	}

	/* Populate FRR OSPF Settings */
	$frr_ospfd_conf = config_get_path('installedpackages/frrospfd/config/0', []);

	if (!empty(config_get_path('installedpackages/frrglobalraw/config/0/ospfd'))) {
		/* If the user has supplied a raw configuration, use that instead of the GUI configuration. */
		$frr_integrated_config['ospfd'] = str_replace("\r", "", base64_decode(config_get_path('installedpackages/frrglobalraw/config/0/ospfd')));
	} else {
		/* If there is no raw configuration and no GUI configuration, stop. */
		if (empty($frr_ospfd_conf)) {
			frr_package_log("OSPFd: No config data found.");
			return;
		} elseif (empty($frr_ospfd_conf['enable'])) {
			/* OSPF daemon is not enabled. */
			frr_package_log("OSPFd: OSPF disabled.");
			return;
		} elseif (empty($frr_conf['enable'])) {
			/* FRR is disabled or BFD Daemon is not enabled. */
			frr_package_log("OSPFd: FRR master disabled.");
			return;
		}

		$frr_integrated_config['ospfd'] = "";

		$redist = "";
		$noredist = "";
		/* Add entries for manually-defined networks */
		if (is_array($frr_ospfd_conf['row'])) {
			foreach ($frr_ospfd_conf['row'] as $redistr) {
				if (empty($redistr['routevalue'])) {
					continue;
				}
				$area = ($redistr['routearea'] == "") ? $frr_ospfd_conf['defaultarea'] : $redistr['routearea'];
				$redist .= " network {$redistr['routevalue']} area {$area}\n";
			}
		}
		/* OSPF Settings */
		$frr_integrated_config['ospfd'] .= "router ospf\n";
		/* If the router ID is defined in BGP, use that, otherwise try to use
		 * the global router ID, if one is set. */
		if (is_ipaddrv4($frr_ospfd_conf['routerid'])) {
			$frr_integrated_config['ospfd'] .= " ospf router-id {$frr_ospfd_conf['routerid']}\n";
		} elseif (is_ipaddrv4($frr_conf['routerid'])) {
			$frr_integrated_config['ospfd'] .= " ospf router-id {$frr_conf['routerid']}\n";
		}

		if ($frr_conf['logging'] && $frr_ospfd_conf['adjacencylog']) {
			$frr_integrated_config['ospfd'] .= " log-adjacency-changes detail\n";
		}

		/* Route Redistribution */
		$frr_integrated_config['ospfd'] .= frr_generate_config_ospf_redistribute($frr_ospfd_conf, 'redistributeconnectedsubnets', 'connected');
		$frr_integrated_config['ospfd'] .= frr_generate_config_ospf_redistribute($frr_ospfd_conf, 'redistributekernel', 'kernel');
		$frr_integrated_config['ospfd'] .= frr_generate_config_ospf_redistribute($frr_ospfd_conf, 'redistributebgp', 'bgp');
		$frr_integrated_config['ospfd'] .= frr_generate_config_ospf_redistribute($frr_ospfd_conf, 'redistributerip', 'rip');
		$frr_integrated_config['ospfd'] .= frr_generate_config_ospf_redistribute($frr_ospfd_conf, 'redistributestatic', 'static');
		if ($frr_ospfd_conf['redistributedefaultroute']) {
			$frr_integrated_config['ospfd'] .= " default-information originate";
			if (!empty($frr_ospfd_conf['redistributedefaultroute_routemap']) &&
				($frr_ospfd_conf['redistributedefaultroute_routemap'] != 'none')) {
				$frr_integrated_config['ospfd'] .= " route-map {$frr_ospfd_conf['redistributedefaultroute_routemap']}";
			}
			if (!empty($frr_ospfd_conf['redistributedefaultroute_metric']) &&
				($frr_ospfd_conf['redistributedefaultroute_metric'] != 'none')) {
				$frr_integrated_config['ospfd'] .= " metric {$frr_ospfd_conf['redistributedefaultroute_metric']}";
			}
			if (!empty($frr_ospfd_conf['redistributedefaultroute_metrictype']) &&
				($frr_ospfd_conf['redistributedefaultroute_metrictype'] != 'none')) {
				$frr_integrated_config['ospfd'] .= " metric-type {$frr_ospfd_conf['redistributedefaultroute_metrictype']}";
			}
			$frr_integrated_config['ospfd'] .= "\n";
		}

		if ($frr_ospfd_conf['spfholdtime'] || $frr_ospfd_conf['spfdelay']) {
			$spf_minhold = ($frr_ospfd_conf['spfholdtime']) ? $frr_ospfd_conf['spfholdtime'] : 1000;
			$spf_maxhold = $spf_minhold * 10;
			$spf_delay = ($frr_ospfd_conf['spfdelay']) ? $frr_ospfd_conf['spfdelay'] : 200;
			$frr_integrated_config['ospfd'] .= " timers throttle spf {$spf_delay} {$spf_minhold} {$spf_maxhold}\n";
		}
		if ($frr_ospfd_conf['rfc1583']) {
			$frr_integrated_config['ospfd'] .= " ospf rfc1583compatibility\n";
		}
		if ($frr_ospfd_conf['opaquelsa']) {
			$frr_integrated_config['ospfd'] .= " capability opaque\n";
		}
		if (!empty($frr_ospfd_conf['writemultiplier'])) {
			$frr_integrated_config['ospfd'] .= " ospf write-multiplier {$frr_ospfd_conf['writemultiplier']}\n";
		}
		if (!empty($frr_ospfd_conf['referencebandwidth'])) {
			$frr_integrated_config['ospfd'] .= " auto-cost reference-bandwidth {$frr_ospfd_conf['referencebandwidth']}\n";
		}
		if ($frr_ospfd_conf['max_metric_administrative']) {
			$frr_integrated_config['ospfd'] .= " max-metric router-lsa administrative\n";
		}
		if (!empty($frr_ospfd_conf['max_metric_startup'])) {
			$frr_integrated_config['ospfd'] .= " max-metric router-lsa on-startup {$frr_ospfd_conf['max_metric_startup']}\n";
		}
		if (!empty($frr_ospfd_conf['max_metric_shutdown'])) {
			$frr_integrated_config['ospfd'] .= " max-metric router-lsa on-shutdown {$frr_ospfd_conf['max_metric_shutdown']}\n";
		}
		if (!empty($frr_ospfd_conf['abrtype']) && ($frr_ospfd_conf['abrtype'] <> 'cisco')) {
			$frr_integrated_config['ospfd'] .= " ospf abr-type {$frr_ospfd_conf['abrtype']}\n";
		}

		/* Interface Settings */
		/* Interface settings do not return a string as they are an accumulation of all protocols */
		$passive_interfaces = array();
		frr_generate_config_ospf_interfaces();

		if (is_array($passive_interfaces)) {
			foreach ($passive_interfaces as $pint)
				$frr_integrated_config['ospfd'] .= " passive-interface {$pint}\n";
		}

		if (!empty($redist)) {
			$frr_integrated_config['ospfd'] .= $redist;
		}

		/* Area Settings */
		$frr_integrated_config['ospfd'] .= frr_generate_config_ospf_areas();

		/* Neighbor Settings */
		$frr_integrated_config['ospfd'] .= frr_generate_config_ospf_neighbors();

	}
}
