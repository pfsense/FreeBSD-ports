<?php
/*
 * frr_ospf.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2020 Rubicon Communications, LLC (Netgate)
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
	global $config, $frr_config_base, $frr_auto_config_warning;
	if (is_array($config['installedpackages']['frrospfd']['config'])) {
		$ospfd_conf = &$config['installedpackages']['frrospfd']['config'][0];
	}

	$defined_areas = array();
	$auto_areas = array();
	$conffile = "";

	/* Automatic Area: Default */
	if (!isset($auto_areas[$ospfd_conf['area']]) || !is_array($auto_areas[$ospfd_conf['area']])) {
		$auto_areas[$ospfd_conf['area']] = array();
	}
	$auto_areas[$ospfd_conf['area']]['area'] = $ospfd_conf['defaultarea'];
	$auto_areas[$ospfd_conf['area']]['type'] = $ospfd_conf['defaultareatype'];

	/* Automatic Areas: Interfaces with Authentication */
	init_config_arr(array('installedpackages', 'frrospfdinterfaces', 'config'));
	foreach ($config['installedpackages']['frrospfdinterfaces']['config'] as $conf) {
		$realif = get_real_interface($conf['interface']);
		if (empty($realif)) {
			continue;
		}

		/* Use default area if the interface area is empty */
		$ifarea = (!empty($conf['interfacearea'])) ? $conf['interfacearea'] : $ospfd_conf['area'];

		if (!isset($auto_areas[$ifarea]) || !is_array($auto_areas[$ifarea])) {
			$auto_areas[$ifarea] = array();
		}

		/* If this interface wants authentication, define the area as needing authentication */
		if (!empty($conf['password']) && in_array($conf['authtype'], array('digest', 'simple'))) {
			$auto_areas[$ifarea]['area'] = $ifarea;
			$auto_areas[$ifarea]['authtype'] = $conf['authtype'];
		}
	}

	/* Load defined areas */
	init_config_arr(array('installedpackages', 'frrospfdareas', 'config'));
	foreach ($config['installedpackages']['frrospfdareas']['config'] as $conf) {
		$defined_areas[$conf['area']] = $conf;

		/* Check if an automatic area needs authentication that isn't set here. */
		if (empty($defined_areas[$conf['area']]['authtype']) &&
		    array_key_exists($conf['area'], $auto_areas) &&
		    in_array($auto_areas[$conf['area']]['authtype'], array('digest', 'simple'))) {
			$defined_areas[$conf['area']]['authtype'] = $auto_areas[$conf['area']]['authtype'];
		}
	}

	/* Copy automatic areas over only if they do not have custom settings */
	foreach ($auto_areas as $aa) {
		if (!array_key_exists($aa['area'], $defined_areas)) {
			$defined_areas[$aa['area']] = $aa;
		}
	}

	/* Process all areas */
	foreach ($defined_areas as $thisarea => $conf) {
		/* type */
		$areatype = "  area {$conf['area']}";
		switch ($conf['type']) {
			case "stub":
				$areatype .= " stub";
				break;
			case "nosum":
				$areatype .= " stub no-summary";
				break;
			case "nssa":
				$areatype .= " nssa";
				break;
			case "nssanosum":
				$areatype .= " nssa no-summary";
				break;
			default:
				$areatype = "";
		}
		if (!empty($areatype)) {
			$conffile .= "{$areatype}\n";
		}

		/* defaultcost */
		if (!empty($conf['defaultcost'])) {
			$conffile .= "  area {$conf['area']} default-cost {$conf['defaultcost']}\n";
		}

		/* shortcut */
		if (!empty($conf['shortcut'])) {
			$conffile .= "  area {$conf['area']} shortcut {$conf['shortcut']}\n";
		}

		/* authentication */
		switch ($conf['authtype']) {
			case "digest":
				$conffile .= "  area {$conf['area']} authentication message-digest\n";
				break;
			case "simple":
				$conffile .= "  area {$conf['area']} authentication\n";
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
				$conffile .= "  area {$conf['area']} range {$range['rangeprefix']}";
				if (!empty($range['rangenotadvertise'])) {
					$conffile .= " not-advertise";
				}
				if (!empty($range['rangecost'])) {
					$conffile .= " cost {$range['rangecost']}";
				}
				if (!empty($range['subprefix'])) {
					$conffile .= " substitute {$range['subprefix']}";
				}
				$conffile .= "\n";
			}
		}

		/* exportlist */
		if (!empty($conf['exportlist']) && ($conf['exportlist'] != "none")) {
			$conffile .= "  area {$conf['area']} export-list {$conf['exportlist']}\n";
		}

		/* importlist */
		if (!empty($conf['importlist']) && ($conf['importlist'] != "none")) {
			$conffile .= "  area {$conf['area']} import-list {$conf['importlist']}\n";
		}

		/* filterlist_out */
		if (!empty($conf['filterlist_out']) && ($conf['filterlist_out'] != "none")) {
			$conffile .= "  area {$conf['area']} filter-list prefix {$conf['filterlist_out']} out\n";
		}

		/* filterlist_in */
		if (!empty($conf['filterlist_in']) && ($conf['filterlist_in'] != "none")) {
			$conffile .= "  area {$conf['area']} filter-list prefix {$conf['filterlist_in']} in\n";
		}
	}

	return $conffile;
}

function frr_generate_config_ospf_interfaces() {
	global $config, $passive_interfaces;
	if (is_array($config['installedpackages']['frrospfd']['config'])) {
		$ospfd_conf = &$config['installedpackages']['frrospfd']['config'][0];
	}

	$conffile = "";
	/* Setup interface entries to define network types, costs, etc. */
	init_config_arr(array('installedpackages', 'frrospfdinterfaces', 'config'));
	foreach ($config['installedpackages']['frrospfdinterfaces']['config'] as $conf) {
		$realif = get_real_interface($conf['interface']);
		if (empty($realif)) {
			continue;
		}
		$conffile .= "interface {$realif}\n" ;
		if (!empty($conf['networktype'])) {
			$conffile .= "  ip ospf network {$conf['networktype']}\n";
		}
		if (!empty($conf['metric'])) {
			$conffile .= "  ip ospf cost {$conf['metric']}\n";
		}

		if (!empty($conf['password'])) {
			switch ($conf['authtype']) {
				case "digest":
					$conffile .= "  ip ospf authentication message-digest\n";
					$conffile .= "  ip ospf message-digest-key 1 md5 " . substr($conf['password'], 0, 16) . "\n";
					break;
				case "simple":
					$conffile .= "  ip ospf authentication-key " . substr($conf['password'], 0, 8) . "\n";
					break;
				case "null":
					$conffile .= "  ip ospf authentication null\n";
					break;
				default:
					break;
			}
		}

		if (!empty($conf['routerpriorityelections']) || ($conf['routerpriorityelections'] == "0")) {
			$conffile .= "  ip ospf priority {$conf['routerpriorityelections']}\n";
		}
		if (!empty($conf['retransmitinterval'])) {
			$conffile .= "  ip ospf retransmit-interval {$conf['retransmitinterval']}\n";
		}

		if (!empty($conf['minimalhello'])) {
			$conffile .= "  ip ospf dead-interval minimal hello-multiplier {$conf['minimalhello']}\n";
		} else {
			if (!empty($conf['deadtimer'])) {
				$conffile .= "  ip ospf dead-interval {$conf['deadtimer']}\n";
			}
			if (!empty($conf['hellointervalinseconds'])) {
				$conffile .= "  ip ospf hello-interval {$conf['hellointervalinseconds']}\n";
			}
		}

		if (!empty($conf['bfdenable'])) {
			$conffile .= "  ip ospf bfd\n";
		}

		if (!empty($conf['mtuignore'])) {
			$conffile .= "  ip ospf mtu-ignore\n";
		}
		if (!empty($conf['passive'])) {
			$passive_interfaces[] = $realif;
		}
		list($interface_ip, $interface_subnet, $subnet) = frr_get_interfaceinfo($conf['interface']);
		if (empty($interface_ip)) {
			continue;
		}
		$area = (strlen($conf['interfacearea']) > 0) ? $conf['interfacearea'] : $ospfd_conf['defaultarea'];
		$conffile .= "  ip ospf area {$area}\n";
	}
	return $conffile;
}

function frr_generate_config_ospf_neighbors() {
	global $config;
	$conffile = "";

	init_config_arr(array('installedpackages', 'frrospfdneighbors', 'config'));
	foreach ($config['installedpackages']['frrospfdneighbors']['config'] as $conf) {
		$conffile .= "  neighbor {$conf['neighbor']}" ;
		if (!empty($conf['priority'])) {
			$conffile .= " priority {$conf['priority']}";
		}
		if (!empty($conf['pollinginterval'])) {
			$conffile .= " poll-interval {$conf['pollinginterval']}";
		}
		$conffile .= "\n" ;
	}
	return $conffile;
}

function frr_generate_config_ospf_redistribute($ospfd_conf, $variablename, $sourcename) {
	$redistconf = "";
	if ($ospfd_conf[$variablename]) {
		$redistconf .= "  redistribute {$sourcename}";
		if (!empty($ospfd_conf[$variablename . '_routemap']) &&
		    ($ospfd_conf[$variablename . '_routemap'] != 'none')) {
			$redistconf .= " route-map {$ospfd_conf[$variablename . '_routemap']}";
		}
		if (!empty($ospfd_conf[$variablename . '_metric']) &&
		    ($ospfd_conf[$variablename . '_metric'] != 'none')) {
			$redistconf .= " metric {$ospfd_conf[$variablename . '_metric']}";
		}
		if (!empty($ospfd_conf[$variablename . '_metrictype']) &&
		    ($ospfd_conf[$variablename . '_metrictype'] != 'none')) {
			$redistconf .= " metric-type {$ospfd_conf[$variablename . '_metrictype']}";
		}
		$redistconf .= "\n";
		if (!empty($ospfd_conf[$variablename . '_distlist']) &&
		    ($ospfd_conf[$variablename . '_distlist'] != 'none')) {
			$redistconf .= "  distribute-list {$ospfd_conf[$variablename . '_distlist']} out {$sourcename}\n";
		}
	}
	return $redistconf;
}

function frr_generate_config_ospf() {
	global $config, $frr_config_base, $frr_auto_config_warning;
	unlink_if_exists("{$frr_config_base}/ospfd.conf");
	/* Populate FRR Global Settings */
	if (is_array($config['installedpackages']['frr']['config'])) {
		$frr_conf = &$config['installedpackages']['frr']['config'][0];
	} else {
		/* If FRR is not configured, stop. */
		return null;
	}
	/* Populate FRR OSPF Settings */
	if (is_array($config['installedpackages']['frrospfd']['config'])) {
		$ospfd_conf = &$config['installedpackages']['frrospfd']['config'][0];
	}

	if (isset($config['installedpackages']['frrglobalraw']['config'][0]['ospfd']) &&
	    !empty($config['installedpackages']['frrglobalraw']['config'][0]['ospfd'])) {
		/* If the user has supplied a raw configuration, use that instead of the GUI configuration. */
		$conffile = str_replace("\r", "", base64_decode($config['installedpackages']['frrglobalraw']['config'][0]['ospfd']));
	} else {
		/* If there is no raw configuration and no GUI configuration, stop. */
		if (empty($ospfd_conf)) {
			log_error("FRR OSPFd: No config data found.");
			return;
		} elseif (empty($frr_conf['enable']) || empty($ospfd_conf['enable'])) {
			/* FRR is disabled or OSPF is not enabled. */
			return;
		}
		$conffile = $frr_auto_config_warning;

		/* Pull the password and logging option from the global settings */
		if ($frr_conf['password']) {
			$conffile .= "password {$frr_conf['password']}\n";
		}
		if ($frr_conf['logging']) {
			$conffile .= "log syslog\n";
		}
		/* SNMP AgentX Support*/
		if ($ospfd_conf['enablesnmpagentx']) {
			$conffile .= "agentx\n";
		}

		/* Interface Settings */
		$passive_interfaces = array();
		global $passive_interfaces;

		$conffile .= frr_generate_config_ospf_interfaces();

		$redist = "";
		$noredist = "";
		/* Add entries for manually-defined networks */
		if (is_array($ospfd_conf['row'])) {
			foreach ($ospfd_conf['row'] as $redistr) {
				if (empty($redistr['routevalue'])) {
					continue;
				}
				$area = ($redistr['routearea'] == "") ? $ospfd_conf['defaultarea'] : $redistr['routearea'];
				$redist .= "  network {$redistr['routevalue']} area {$area}\n";
			}
		}
		/* OSPF Settings */
		$conffile .= "\nrouter ospf\n";
		/* If the router ID is defined in BGP, use that, otherwise try to use
		 * the global router ID, if one is set. */
		if (is_ipaddrv4($ospfd_conf['routerid'])) {
			$conffile .= "  ospf router-id {$ospfd_conf['routerid']}\n";
		} elseif (is_ipaddrv4($frr_conf['routerid'])) {
			$conffile .= "  ospf router-id {$frr_conf['routerid']}\n";
		}

		$conffile .= frr_generate_config_ospf_areas();
		$conffile .= frr_generate_config_ospf_neighbors();

		if ($frr_conf['logging'] && $ospfd_conf['adjacencylog']) {
			$conffile .= "  log-adjacency-changes detail\n";
		}

		/* Route Redistribution */
		$conffile .= frr_generate_config_ospf_redistribute($ospfd_conf, 'redistributeconnectedsubnets', 'connected');
		$conffile .= frr_generate_config_ospf_redistribute($ospfd_conf, 'redistributekernel', 'kernel');
		$conffile .= frr_generate_config_ospf_redistribute($ospfd_conf, 'redistributebgp', 'bgp');
		$conffile .= frr_generate_config_ospf_redistribute($ospfd_conf, 'redistributestatic', 'static');
		if ($ospfd_conf['redistributedefaultroute']) {
			$conffile .= "  default-information originate";
			if (!empty($ospfd_conf['redistributedefaultroute_routemap']) &&
			    ($ospfd_conf['redistributedefaultroute_routemap'] != 'none')) {
				$conffile .= " route-map {$ospfd_conf['redistributedefaultroute_routemap']}";
			}
			if (!empty($ospfd_conf['redistributedefaultroute_metric']) &&
			    ($ospfd_conf['redistributedefaultroute_metric'] != 'none')) {
				$conffile .= " metric {$ospfd_conf['redistributedefaultroute_metric']}";
			}
			if (!empty($ospfd_conf['redistributedefaultroute_metrictype']) &&
			    ($ospfd_conf['redistributedefaultroute_metrictype'] != 'none')) {
				$conffile .= " metric-type {$ospfd_conf['redistributedefaultroute_metrictype']}";
			}
			$conffile .= "\n";
		}

		if ($ospfd_conf['spfholdtime'] || $ospfd_conf['spfdelay']) {
			$spf_minhold = ($ospfd_conf['spfholdtime']) ? $ospfd_conf['spfholdtime'] : 1000;
			$spf_maxhold = $spf_minhold * 10;
			$spf_delay = ($ospfd_conf['spfdelay']) ? $ospfd_conf['spfdelay'] : 200;
			$conffile .= "  timers throttle spf {$spf_delay} {$spf_minhold} {$spf_maxhold}\n";
		}
		if ($ospfd_conf['rfc1583']) {
			$conffile .= "  ospf rfc1583compatibility\n";
		}
		if ($ospfd_conf['opaquelsa']) {
			$conffile .= "  capability opaque\n";
		}
		if (!empty($ospfd_conf['writemultiplier'])) {
			$conffile .= "  ospf write-multiplier {$ospfd_conf['writemultiplier']}\n";
		}
		if (!empty($ospfd_conf['referencebandwidth'])) {
			$conffile .= "  auto-cost reference-bandwidth {$ospfd_conf['referencebandwidth']}\n";
		}
		if ($ospfd_conf['max_metric_administrative']) {
			$conffile .= "  max-metric router-lsa administrative\n";
		}
		if (!empty($ospfd_conf['max_metric_startup'])) {
			$conffile .= "  max-metric router-lsa on-startup {$ospfd_conf['max_metric_startup']}\n";
		}
		if (!empty($ospfd_conf['max_metric_shutdown'])) {
			$conffile .= "  max-metric router-lsa on-shutdown {$ospfd_conf['max_metric_shutdown']}\n";
		}
		if (!empty($ospfd_conf['abrtype'])) {
			$conffile .= "  ospf abr-type {$ospfd_conf['abrtype']}\n";
		}

		if (is_array($passive_interfaces)) {
			foreach ($passive_interfaces as $pint)
				$conffile .= "  passive-interface {$pint}\n";
		}

		if (!empty($redist)) {
			$conffile .= $redist;
		}

		/* Access Lists */
		$conffile .= frr_zebra_generate_accesslists();
		/* Prefix Lists */
		$conffile .= frr_zebra_generate_prefixlists();
		/* Route maps. */
		$conffile .= frr_zebra_generate_routemaps();

	}
	safe_mkdir($frr_config_base);
	file_put_contents("{$frr_config_base}/ospfd.conf", $conffile);
}
