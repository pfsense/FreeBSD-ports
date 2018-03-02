<?php
/*
 * frr_ospf.inc
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

function frr_generate_config_ospf() {
	global $config, $frr_config_base, $frr_auto_config_warning;
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

		/* Interface Settings */
		$passive_interfaces = array();
		$interface_networks = array();
		$authsettings = "";

		/* Setup interface entries to define network types, costs, etc. */
		if (is_array($config['installedpackages']['frrospfdinterfaces']['config'])) {
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
				if (!empty($conf['hellointervalinseconds'])) {
					$conffile .= "  ip ospf hello-interval {$conf['hellointervalinseconds']}\n";
				}

				/* If there is a password defined, setup authentication for this interface and also a block for the main OSPF section. */
				if ($conf['md5password'] && !empty($conf['password'])) {
					$conffile .= "  ip ospf authentication message-digest\n";
					$conffile .= "  ip ospf message-digest-key 1 md5 " . substr($conf['password'], 0, 15) . "\n";

					$authsettings .= "area ";
					$authsettings .= (empty($conf['interfacearea'])) ? $conf['interfacearea'] : $ospfd_conf['area'];
					$authsettings .= " authentication message-digest\n";

				} elseif (!empty($conf['password'])) {
					$conffile .= "  ip ospf authentication-key " . substr($conf['password'], 0, 8) . "\n";

					$authsettings .= "area ";
					$authsettings .= (empty($conf['interfacearea'])) ? $conf['interfacearea'] : $ospfd_conf['area'];
					$authsettings .= " authentication\n";
				}

				if (!empty($conf['routerpriorityelections']) || ($conf['routerpriorityelections'] == "0")) {
					$conffile .= "  ip ospf priority {$conf['routerpriorityelections']}\n";
				}
				if (!empty($conf['retransmitinterval'])) {
					$conffile .= "  ip ospf retransmit-interval {$conf['retransmitinterval']}\n";
				}
				if (!empty($conf['deadtimer'])) {
					$conffile .= "  ip ospf dead-interval {$conf['deadtimer']}\n";
				}
				if (!empty($conf['passive'])) {
					$passive_interfaces[] = $realif;
				}
				list($interface_ip, $interface_subnet, $subnet) = frr_get_interfaceinfo($conf['interface']);
				if (empty($interface_ip)) {
					continue;
				}
				if (!empty($conf['interfacearea'])) {
					$interface_networks[] = array( "subnet" => "{$subnet}/{$interface_subnet}", "area" => $conf['interfacearea']);
				} else {
					$interface_networks[] = array( "subnet" => "{$subnet}/{$interface_subnet}", "area" => $ospfd_conf['area']);
				}
			}
		}
		$redist = "";
		$noredist = "";
		/* Add entries for manually-defined networks */
		if (is_array($ospfd_conf['row'])) {
			foreach ($ospfd_conf['row'] as $redistr) {
				if (empty($redistr['routevalue'])) {
					continue;
				}
				if (isset($redistr['redistribute'])) {
					/* Add this to the "do not redistribute" ACL */
					$noredist .= "access-list dnr-list deny {$redistr['routevalue']}\n";
				} else {
					$area = ($redistr['routearea'] == "") ? $ospfd_conf['area'] : $redistr['routearea'];
					$redist .= "  network {$redistr['routevalue']} area {$area}\n";
				}
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

		$stub_start = "  area {$ospfd_conf['area']} ";
		switch($ospfd_conf['updatefib']) {
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
		if ($ospfd_conf['logging'] && $ospfd_conf['adjacencylog']) {
			$conffile .= "  log-adjacency-changes detail\n";
		}

		/* When defining the redistribute directives, also specify a
		 * "Do not redistribute" route-map to filter. */
		if ($ospfd_conf['redistributeconnectedsubnets']) {
			$conffile .= "  redistribute connected route-map DNR\n";
		}
		if ($ospfd_conf['redistributestatic']) {
			$conffile .= "  redistribute static route-map DNR\n";
		}
		if ($ospfd_conf['redistributekernel']) {
			$conffile .= "  redistribute kernel route-map DNR\n";
		}
		if ($ospfd_conf['redistributebgp']) {
			$conffile .= "  redistribute bgp route-map DNR\n";
		}
		if ($ospfd_conf['redistributedefaultroute']) {
			$conffile .= "  default-information originate\n";
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
		if (is_array($passive_interfaces)) {
			foreach ($passive_interfaces as $pint)
				$conffile .= "  passive-interface {$pint}\n";
		}
		if (is_array($interface_networks)) {
			foreach ($interface_networks as $ifn) {
				if (is_subnet($ifn['subnet'])) {
					$conffile .= "  network {$ifn['subnet']} area {$ifn['area']}\n";
				}
			}
		}

		if (!empty($authsettings)) {
			$conffile .= $authsettings;
		}

		if (!empty($redist)) {
			$conffile .= $redist;
		}
		/* Setup a "do not redistribute" list in a couple different ways:
		 * 1: Add networks to a distribute-list so they won't go to other protocols
		 * 2: Add networks to a route-map which is used to filter redistribution
		 */
		if (!empty($noredist)) {
			$conffile .= "  area {$ospfd_conf['area']} export-list dnr-list\n";
			$conffile .= "  distribute-list dnr-list out connected\n";
			$conffile .= "  distribute-list dnr-list out kernel\n";
			$conffile .= "  distribute-list dnr-list out static\n";

			$conffile .= $noredist;
		}
		$conffile .= "access-list dnr-list permit any\n";
		$conffile .= "route-map DNR permit 10\n";
		$conffile .= "  match ip address dnr-list\n";

	}
	safe_mkdir($frr_config_base);
	file_put_contents("{$frr_config_base}/ospfd.conf", $conffile);
}
