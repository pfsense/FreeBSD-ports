<?php
/*
 * frr_ospf6.inc
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

function frr_generate_config_ospf6() {
	global $config, $frr_config_base, $frr_auto_config_warning;
	/* Populate FRR Global Settings */
	if (is_array($config['installedpackages']['frr']['config'])) {
		$frr_conf = &$config['installedpackages']['frr']['config'][0];
	} else {
		/* If FRR is not configured, stop. */
		return null;
	}
	/* Populate FRR OSPF6 Settings */
	if (is_array($config['installedpackages']['frrospf6d']['config'])) {
		$ospf6d_conf = &$config['installedpackages']['frrospf6d']['config'][0];
	}

	if (isset($config['installedpackages']['frrglobalraw']['config'][0]['ospf6d']) &&
	    !empty($config['installedpackages']['frrglobalraw']['config'][0]['ospf6d'])) {
		/* If the user has supplied a raw configuration, use that instead of the GUI configuration. */
		$conffile = str_replace("\r", "", base64_decode($config['installedpackages']['frrglobalraw']['config'][0]['ospf6d']));
	} else {
		/* If there is no raw configuration and no GUI configuration, stop. */
		if (empty($ospf6d_conf)) {
			log_error("FRR OSPF6d: No config data found.");
			return;
		} elseif (empty($frr_conf['enable']) || empty($ospf6d_conf['enable'])) {
			/* FRR is disabled or OSPF6 is not enabled. */
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

		/* Interface Settings */
		$passive_interfaces = array();
		$interface_networks = array();

		$router_interface_areas = "";
		/* Setup interface entries to define network types, costs, etc. */
		if (is_array($config['installedpackages']['frrospf6dinterfaces']['config'])) {
			foreach ($config['installedpackages']['frrospf6dinterfaces']['config'] as $conf) {
				$realif = get_real_interface($conf['interface']);
				if (empty($realif)) {
					continue;
				}
				$conffile .= "interface {$realif}\n" ;
				if (!empty($conf['networktype'])) {
					$conffile .= "  ipv6 ospf6 network {$conf['networktype']}\n";
				}
				if (!empty($conf['metric'])) {
					$conffile .= "  ipv6 ospf6 cost {$conf['metric']}\n";
				}
				if (!empty($conf['hellointervalinseconds'])) {
					$conffile .= "  ipv6 ospf6 hello-interval {$conf['hellointervalinseconds']}\n";
				}
				if (!empty($conf['routerpriorityelections']) || ($conf['routerpriorityelections'] == "0")) {
					$conffile .= "  ipv6 ospf6 priority {$conf['routerpriorityelections']}\n";
				}
				if (!empty($conf['retransmitinterval'])) {
					$conffile .= "  ipv6 ospf6 retransmit-interval {$conf['retransmitinterval']}\n";
				}
				if (!empty($conf['deadtimer'])) {
					$conffile .= "  ipv6 ospf6 dead-interval {$conf['deadtimer']}\n";
				}
				if (!empty($conf['passive'])) {
					$conffile .= "  ipv6 ospf6 passive\n";
				}
				list($interface_ip, $interface_subnet, $subnet) = frr_get_interfaceinfo($conf['interface'], true);
				if (empty($interface_ip)) {
					continue;
				}
				$interface_area = (strlen($conf['interfacearea']) > 0) ? $conf['interfacearea'] : $ospf6d_conf['area'];
				$router_interface_areas .= "  interface {$realif} area {$interface_area}\n";
				$interface_networks[] = array( "subnet" => "{$subnet}/{$interface_subnet}", "area" => $interface_area);
			}
		}
		$redist = "";
		$noredist = "";
		/* Add entries for manually-defined networks */
		if (is_array($ospf6d_conf['row'])) {
			foreach ($ospf6d_conf['row'] as $redistr) {
				if (empty($redistr['routevalue'])) {
					continue;
				}
				if (isset($redistr['redistribute'])) {
					/* Add this to the "do not redistribute" ACL */
					$noredist .= "  access-list dnr-list deny {$redistr['routevalue']}\n";
				} else {
					$area = ($redistr['routearea'] == "") ? $ospf6d_conf['area'] : $redistr['routearea'];
					$cost = (is_numeric($redistr['routearea']) && ($redistr['routearea'] >= 0)) ? $redistr['routearea']: 1;
					$redist .= "  area {$area} range {$redistr['routevalue']} cost {$cost}\n";
				}
			}
		}
		/* OSPF6 Settings */
		$conffile .= "\nrouter ospf6\n";
		/* If the router ID is defined in BGP, use that, otherwise try to use
		 * the global router ID, if one is set. */
		if (is_ipaddrv4($ospf6d_conf['routerid'])) {
			$conffile .= "  router-id {$ospf6d_conf['routerid']}\n";
		} elseif (is_ipaddrv4($frr_conf['routerid'])) {
			$conffile .= "  router-id {$frr_conf['routerid']}\n";
		}

		$conffile .= $router_interface_areas;

		$stub_start = "  area {$ospf6d_conf['area']} ";
		switch($ospf6d_conf['updatefib']) {
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
			$conffile .= "{$stub_start}\n";
		}
		if ($ospf6d_conf['logging'] && $ospf6d_conf['adjacencylog']) {
			$conffile .= "  log-adjacency-changes detail\n";
		}

		/* When defining the redistribute directives, also specify a
		 * "Do not redistribute" route-map to filter. */
		if ($ospf6d_conf['redistributeconnectedsubnets']) {
			$conffile .= "  redistribute connected route-map DNR6\n";
		}
		if ($ospf6d_conf['redistributestatic']) {
			$conffile .= "  redistribute static route-map DNR6\n";
		}
		if ($ospf6d_conf['redistributekernel']) {
			$conffile .= "  redistribute kernel route-map DNR6\n";
		}
		if ($ospf6d_conf['spfholdtime'] || $ospf6d_conf['spfdelay']) {
			$spf_minhold = ($ospf6d_conf['spfholdtime']) ? $ospf6d_conf['spfholdtime'] : 1000;
			$spf_maxhold = $spf_minhold * 10;
			$spf_delay = ($ospf6d_conf['spfdelay']) ? $ospf6d_conf['spfdelay'] : 200;
			$conffile .= "  timers throttle spf {$spf_delay} {$spf_minhold} {$spf_maxhold}\n";
		}
		if (is_array($interface_networks)) {
			foreach ($interface_networks as $ifn) {
				if (is_subnetv6($ifn['subnet'])) {
					$cost = (is_numeric($conf['metric']) && ($conf['metric'] >= 0)) ? $conf['metric'] : 1;
					$conffile .= "  area {$ifn['area']} range {$ifn['subnet']} cost {$cost}\n";
					$conffile .= "  area {$ifn['area']} export-list dnr6-list\n";
				}
			}
		}
		if (!empty($redist)) {
			$conffile .= $redist;
		}

		/* Setup a "do not redistribute" list in a couple different ways:
		 * 1: Add networks to a distribute-list so they won't go to other protocols
		 * 2: Add networks to a route-map which is used to filter redistribution
		 */
		if (!empty($noredist)) {
			$conffile .= $noredist;
		}
		$conffile .= "ipv6 prefix-list dnr6-list permit any\n";
		$conffile .= "route-map DNR6 permit 10\n";
		$conffile .= "  match ipv6 address prefix-list dnr6-list\n";

	}
	safe_mkdir($frr_config_base);
	file_put_contents("{$frr_config_base}/ospf6d.conf", $conffile);
}
