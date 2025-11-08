<?php
/*
 * frr_rip.inc
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

function frr_generate_config_rip_interfaces() {
	global $rip_passive_interfaces, $frr_integrated_config;
	$frr_ripd_conf = config_get_path('installedpackages/frrripd/config/0', []);

	/* Setup interface entries */
	foreach (config_get_path('installedpackages/frrripdinterfaces/config', []) as $conf) {
		$realif = get_real_interface($conf['interface']);
		if (empty($realif)) {
			continue;
		}
		/* Create interface string if it doesn't already exist */
		if (!isset($frr_integrated_config['interfaces'][$realif])) {
			$frr_integrated_config['interfaces'][$realif] = "";
		}

		if (!empty($conf['descr'])) {
			$frr_integrated_config['interfaces_descr'][$realif]['ripd'] = $conf['descr'];
		}
		if (!empty($conf['splithorizon'])) {
			if ($conf['splithorizon'] == 'splithorizon') {
				$frr_integrated_config['interfaces'][$realif] .= " ip rip split-horizon\n";
			} elseif ($conf['splithorizon'] == 'reversepoison') {
				$frr_integrated_config['interfaces'][$realif] .= " ip rip split-horizon poisoned-reverse\n";
			}
		}
		if (!empty($conf['sendbroadcast'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ip rip v2-broadcast\n";
		}
		if (!empty($conf['sendversion'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ip rip send version {$conf['sendversion']}\n";
		}
		if (!empty($conf['receiveversion'])) {
			$frr_integrated_config['interfaces'][$realif] .= " ip rip receive version {$conf['receiveversion']}\n";
		}
		if (!empty($conf['passive'])) {
			$rip_passive_interfaces[] = $realif;
		}

		if (!empty($conf['password'])) {
			switch ($conf['authtype']) {
				case "digest":
					$frr_integrated_config['interfaces'][$realif] .= " ip rip authentication mode md5\n";
					$frr_integrated_config['interfaces'][$realif] .= " ip rip authentication key-chain RIP_{$conf['interface']}\n";
					$frr_integrated_config['zebra'] .= "!\nkey chain RIP_{$conf['interface']}\n key 1\n  key-string {$conf['password']}\n";
					break;
				case "simple":
					$frr_integrated_config['interfaces'][$realif] .= " ip rip authentication mode text\n";
					$frr_integrated_config['interfaces'][$realif] .= " ip rip authentication string " . substr($conf['password'], 0, 16) . "\n";
					break;
				default:
					break;
			}
		}
	}
}

function frr_generate_config_rip_neighbors() {
	$neiconf= "";
	foreach (config_get_path('installedpackages/frrripdneighbors/config', []) as $conf) {
		$neiconf .= " neighbor {$conf['neighbor']}\n" ;
	}
	return $neiconf;
}

function frr_generate_config_rip_redistribute($frr_ripd_conf, $variablename, $sourcename) {
	$redistconf = "";
	if ($frr_ripd_conf[$variablename]) {
		$redistconf .= " redistribute {$sourcename}";
		if (!empty($frr_ripd_conf[$variablename . '_routemap']) &&
			($frr_ripd_conf[$variablename . '_routemap'] != 'none')) {
			$redistconf .= " route-map {$frr_ripd_conf[$variablename . '_routemap']}";
		}
		if (!empty($frr_ripd_conf[$variablename . '_metric']) &&
			($frr_ripd_conf[$variablename . '_metric'] != 'none')) {
			$redistconf .= " metric {$frr_ripd_conf[$variablename . '_metric']}";
		}
		if (!empty($frr_ripd_conf[$variablename . '_metrictype']) &&
			($frr_ripd_conf[$variablename . '_metrictype'] != 'none')) {
			$redistconf .= " metric-type {$frr_ripd_conf[$variablename . '_metrictype']}";
		}
		$redistconf .= "\n";
		if (!empty($frr_ripd_conf[$variablename . '_distlist']) &&
			($frr_ripd_conf[$variablename . '_distlist'] != 'none')) {
			$redistconf .= " distribute-list {$frr_ripd_conf[$variablename . '_distlist']} out {$sourcename}\n";
		}
	}
	return $redistconf;
}

function frr_generate_config_rip() {
	global $frr_config_base, $frr_integrated_config, $rip_passive_interfaces;

	/* Populate FRR Global Settings */
	$frr_conf = config_get_path('installedpackages/frr/config/0', []);
	if (empty($frr_conf)) {
		/* If FRR is not configured, stop. */
		frr_package_log("RIPd: No FRR global config data found.");
		return null;
	}

	/* Populate FRR RIP Settings */
	$frr_ripd_conf = config_get_path('installedpackages/frrripd/config/0', []);

	if (!empty(config_get_path('installedpackages/frrglobalraw/config/0/ripd'))) {
		/* If the user has supplied a raw configuration, use that instead of the GUI configuration. */
		$frr_integrated_config['ripd'] = str_replace("\r", "", base64_decode(config_get_path('installedpackages/frrglobalraw/config/0/ripd')));
	} else {
		/* If there is no raw configuration and no GUI configuration, stop. */
		if (empty($frr_ripd_conf)) {
			frr_package_log("RIPd: No config data found.");
			return;
		} elseif (empty($frr_ripd_conf['enable'])) {
			/* RIP daemon is not enabled. */
			frr_package_log("RIPd: RIP disabled.");
			return;
		} elseif (empty($frr_conf['enable'])) {
			frr_package_log("RIPd: FRR master disabled.");
			return;
		}
		$frr_integrated_config['ripd'] = "";

		$redist = "";
		$noredist = "";
		/* Add entries for manually-defined networks */
		if (is_array($frr_ripd_conf['row'])) {
			foreach ($frr_ripd_conf['row'] as $redistr) {
				if (empty($redistr['routevalue'])) {
					continue;
				}
				$redist .= " network {$redistr['routevalue']}\n";
			}
		}

		/* RIP Settings */
		$frr_integrated_config['ripd'] .= "router rip\n";

		/* Route Redistribution */
		$frr_integrated_config['ripd'] .= frr_generate_config_rip_redistribute($frr_ripd_conf, 'redistributeconnectedsubnets', 'connected');
		$frr_integrated_config['ripd'] .= frr_generate_config_rip_redistribute($frr_ripd_conf, 'redistributekernel', 'kernel');
		$frr_integrated_config['ripd'] .= frr_generate_config_rip_redistribute($frr_ripd_conf, 'redistributebgp', 'bgp');
		$frr_integrated_config['ripd'] .= frr_generate_config_rip_redistribute($frr_ripd_conf, 'redistributeospf', 'ospf');
		$frr_integrated_config['ripd'] .= frr_generate_config_rip_redistribute($frr_ripd_conf, 'redistributestatic', 'static');

		if ($frr_ripd_conf['redistributedefaultroute']) {
			$frr_integrated_config['ripd'] .= " default-information originate\n";
		}

		if ($frr_ripd_conf['ripversion']) {
			$frr_integrated_config['ripd'] .= " version {$frr_ripd_conf['ripversion']}\n";
		}

		/* Interface Settings */
		/* Interface settings do not return a string as they are an accumulation of all protocols */
		$rip_passive_interfaces = array();
		frr_generate_config_rip_interfaces();

		if (is_array($rip_passive_interfaces)) {
			foreach ($rip_passive_interfaces as $pint)
				$frr_integrated_config['ripd'] .= " passive-interface {$pint}\n";
		}

		if ($frr_ripd_conf['inoffsetlist'] && ($frr_ripd_conf['inoffsetlist'] != "none") &&
		    is_numeric($frr_ripd_conf['inmetricvalue'])) {
			$frr_integrated_config['ripd'] .= " offset-list {$frr_ripd_conf['inoffsetlist']} in {$frr_ripd_conf['inmetricvalue']}\n";
		}

		if ($frr_ripd_conf['outoffsetlist'] && ($frr_ripd_conf['outoffsetlist'] != "none") &&
		    is_numeric($frr_ripd_conf['outmetricvalue'])) {
			$frr_integrated_config['ripd'] .= " offset-list {$frr_ripd_conf['outoffsetlist']} out {$frr_ripd_conf['outmetricvalue']}\n";
		}

		if (!empty($redist)) {
			$frr_integrated_config['ripd'] .= $redist;
		}

		/* Neighbor Settings */
		$frr_integrated_config['ripd'] .= frr_generate_config_rip_neighbors();
	}
}
