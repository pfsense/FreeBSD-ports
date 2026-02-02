<?php
/*
 * suricata.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2025 Bill Meeks
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

require_once("pfsense-utils.inc");
require_once("config.inc");
require_once("globals.inc");
require_once("functions.inc");
require_once("services.inc");
require_once("service-utils.inc");
require_once("pkg-utils.inc");
require_once("filter.inc");
require_once("notices.inc");
require_once("util.inc");
require_once("xmlrpc_client.inc");
require_once("openvpn.inc");
require("/usr/local/pkg/suricata/suricata_defs.inc");

global $g;

// Suricata GUI needs at least 512MB to manipulate large rules arrays
ini_set("memory_limit", config_get_path('system/php_memory_limit', '512') . "M");

function suricata_is_single_addr_alias($alias) {
	/***************************************************/
	/* This function evaluates the passed Alias to     */
	/* determine if it represents a single IP address, */
	/* or a network in CIDR form, and returns TRUE if  */
	/* the condition is met, and FALSE if not.         */
	/*                                                 */
	/* On Entry: $alias ==> Alias to be evaluated      */
	/*  Returns: TRUE if Alias represents a single     */
	/*           IP address or network, and FALSE      */
	/*           if not.                               */
	/***************************************************/

	/* If spaces in expanded Alias, it's not a single entity */
	if (strpos(trim(filter_expand_alias($alias)), " ") !== false)
		return false;
	else
		return true;
}

function suricata_generate_id() {

	while (true) {
		$suricata_uuid = mt_rand(1, 65535);
		foreach (config_get_path('installedpackages/suricata/rule', []) as $value) {
			if ($value['uuid'] == $suricata_uuid) {
				continue 2;
			}
		}
		break;
	}

	return $suricata_uuid;
}

function suricata_is_running($suricata_uuid, $if_real, $type = 'suricata') {
	global $g;
	return isvalidpid("{$g['varrun_path']}/{$type}_{$if_real}{$suricata_uuid}.pid");
}

function suricata_stop($suricatacfg, $if_real) {
	global $g;
	$suricata_uuid = $suricatacfg['uuid'];
	$suricata_ctrl_sock = "{$g['varrun_path']}/suricata-ctrl-socket-{$suricata_uuid}";

	logger(LOG_NOTICE, localize_text("Suricata STOP for %s(%s)...", $suricatacfg['descr'], $if_real), LOG_PREFIX_PKG_SURICATA);

	// Attempt to use UNIX control socket interface if present
	if (file_exists($suricata_ctrl_sock)) {
		if (mwexec("/usr/local/bin/suricatasc -c shutdown {$suricata_ctrl_sock}") == 0) {
			// Give the Suricata daemon time to stop and cleanup before returning
			sleep(3);
			return;
		}
	} elseif (isvalidpid("{$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid")) {
		killbypid("{$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid");
		// Give the Suricata daemon time to stop and cleanup before returning
		sleep(3);
		return;
	} else {
		if (!isvalidpid("{$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid")) {
			// Make sure any zombie PID file is deleted so Suricata will start later without error
			unlink_if_exists("{$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid");
		}
	}
}

function suricata_start($suricatacfg, $if_real) {
	global $g;

	$suricatadir = SURICATADIR;
	$suricata_uuid = $suricatacfg['uuid'];
	$suricatalogdir = SURICATALOGDIR . "suricata_{$if_real}{$suricata_uuid}";
	$suricatabindir = SURICATA_PBI_BINDIR;

	if ($suricatacfg['enable'] == 'on' && $if_real <> "") {
		// Truncate suricata.log file for this interface.
		file_put_contents("{$suricatalogdir}/suricata.log", '');
		$run_mode = $suricatacfg['ips_mode'] == 'ips_mode_inline' && $suricatacfg['blockoffenders'] == 'on' ? '--netmap' : '-i ' . $if_real;
		$verbose_logging = $suricatacfg['enable_verbose_logging'] == 'on' ? "-vv" : "";
		logger(LOG_NOTICE, localize_text("Suricata START for %s(%s)...", $suricatacfg['descr'], $if_real), LOG_PREFIX_PKG_SURICATA);
		mwexec_bg("{$suricatabindir}suricata {$run_mode} -D -c {$suricatadir}suricata_{$suricata_uuid}_{$if_real}/suricata.yaml --pidfile {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid {$verbose_logging}");
	} else {
		return;
	}
}

function suricata_start_all_interfaces($background=FALSE) {

	/*************************************************************/
	/* This function starts all configured and enabled Suricata  */
	/* interfaces.                                               */
	/*************************************************************/

	foreach (config_get_path('installedpackages/suricata/rule', []) as $suricatacfg) {
		if ($suricatacfg['enable'] != 'on' || get_real_interface($suricatacfg['interface']) == "") {
			continue;
		}
		suricata_start($suricatacfg, get_real_interface($suricatacfg['interface']));
	}
}

function suricata_stop_all_interfaces() {

	/*************************************************************/
	/* This function stops all configured Suricata interfaces.   */
	/*************************************************************/

	foreach (config_get_path('installedpackages/suricata/rule', []) as $suricatacfg) {
		suricata_stop($suricatacfg, get_real_interface($suricatacfg['interface']));
	}
}

function suricata_restart_all_interfaces() {

	/*************************************************************/
	/* This function stops all configured Suricata interfaces    */
	/* and restarts enabled Suricata interfaces.                 */
	/*************************************************************/

	/* do nothing if no Suricata interfaces configured */
	if (count(config_get_path('installedpackages/suricata/rule', [])) < 1) {
		return;
	}

	suricata_stop_all_interfaces();
	sleep(2);
	suricata_start_all_interfaces(TRUE);
}

function suricata_reload_config($suricatacfg, $signal=SIGUSR2) {

	/**************************************************************/
	/* This function sends the passed SIGNAL to the Suricata      */
	/* instance on the passed interface to cause Suricata to      */
	/* reload and parse the running configuration without         */
	/* impacting packet processing.  It also executes the reload  */
	/* as a background process and returns control immediately    */
	/* to the caller.                                             */
	/*                                                            */
	/*  $signal = SIGUSR2 (default) parses and reloads rules.     */
	/**************************************************************/
	global $g;

	$suricata_uuid = $suricatacfg['uuid'];
	$if_real = get_real_interface($suricatacfg['interface']);
	$suricata_ctrl_sock = "{$g['varrun_path']}/suricata-ctrl-socket-{$suricata_uuid}";

	/******************************************************/
	/* Skip disabled Suricata instances or instances      */
	/* whose pfSense physical interface has been removed. */
	/******************************************************/
	if (($suricatacfg['enable'] != 'on') || ($if_real == "")) {
		return;
	}

	/******************************************************/
	/* Only send the SIGNAL if Suricata is running and    */
	/* we can find a valid PID for the process.           */
	/******************************************************/
	if (isvalidpid("{$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid")) {
		switch ($signal) {
			case SIGHUP:
				logger(LOG_NOTICE, localize_text("Suricata LOGS ROTATION initiated for %s(%s)...", $suricatacfg['descr'], $if_real), LOG_PREFIX_PKG_SURICATA);
				if (mwexec("/usr/local/bin/suricatasc -c reopen-log-files {$suricata_ctrl_sock}") != 0) {
					logger(LOG_WARNING, localize_text("Suricata LOGS ROTATION command failed to execute!"), LOG_PREFIX_PKG_SURICATA);
				}
				break;

			case SIGUSR2:
				logger(LOG_NOTICE, localize_text("Suricata LIVE RULE RELOAD initiated for %s(%s)...", $suricatacfg['descr'], $if_real), LOG_PREFIX_PKG_SURICATA);
				if (mwexec("/usr/local/bin/suricatasc -c ruleset-reload-nonblocking {$suricata_ctrl_sock}") != 0) {
					logger(LOG_WARNING, localize_text("Suricata LIVE RULE RELOAD command failed to execute!"), LOG_PREFIX_PKG_SURICATA);
				}
				break;

			case SIGTERM:
			case SIGINT:
				logger(LOG_NOTICE, localize_text("Suricata SHUTDOWN initiated for %s(%s)...", $suricatacfg['descr'], $if_real), LOG_PREFIX_PKG_SURICATA);
				if (mwexec("/usr/local/bin/suricatasc -c shutdown {$suricata_ctrl_sock}") != 0) {
					logger(LOG_WARNING, localize_text("Suricata SHUTDOWN command failed to execute!"), LOG_PREFIX_PKG_SURICATA);
				}
				break;

			default:
				logger(LOG_NOTICE, localize_text("Suricata signalled with {$signal} for %s(%s)...", $suricatacfg['descr'], $if_real), LOG_PREFIX_PKG_SURICATA);
				// Send the SIGNAL to the Suricata process
				mwexec_bg("/bin/pkill -{$signal} -F {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid");
		}
	}
}

function suricata_get_blocked_ips() {

	$suri_pf_table = SURICATA_PF_TABLE;
	$blocked_ips = "";

	exec("/sbin/pfctl -t {$suri_pf_table} -T show", $blocked_ips);

	$blocked_ips_array = array();
	if (!empty($blocked_ips)) {
		if (is_array($blocked_ips)) {
			foreach ($blocked_ips as $blocked_ip) {
				if (empty($blocked_ip)) {
					continue;
				}
				$blocked_ips_array[] = trim($blocked_ip, " \n\t");
			}
		}
	}
	return $blocked_ips_array;
}

function suricata_get_supported_netmap_queues($if_real) {

	/*******************************************************/
	/* This function attempts to parse the 'dmesg.boot'    */ 
	/* file to find the number of netmap queues available  */
	/* on the NIC hosting the passed real interface.       */
	/*                                                     */
	/* On Entry: $if_real = NIC driver to query            */
	/* Returns: lesser of registered TX or RX queues       */
	/*******************************************************/

	global $g;
	$threads_param = 1;
	$handle = @fopen("{$g['varlog_path']}/dmesg.boot", "r");
	if ($handle) {
		while (!feof($handle)) {
			$buffer = fgets($handle);
			if(strpos($buffer, "{$if_real}: netmap queues/slots:") !== FALSE) {
				$matches = array();
				if (preg_match('/\bTX\s*(\d+)\/\d+,\s*\bRX\s*(\d+)\/\d+/', $buffer, $matches)) {
					if (intval($matches[1]) < intval($matches[2])) {
						$threads_param = $matches[1];
					}
					else {
						$threads_param = $matches[2];
					}
					if ($threads_param < 2) {
						$threads_param = 1;
					}
				}
				break;
			}
		}
		fclose($handle);
	}
	return $threads_param;
}

/* func finds custom Suppress or Pass Lists */
function suricata_find_list($find_name, $type = 'passlist') {

	foreach (config_get_path("installedpackages/suricata/{$type}/item", []) as $value) {
		if ($value['name'] == $find_name) {
			return $value;
		}
	}

	return array();
}

function suricata_build_list($suricatacfg, $listname = "", $passlist = false, $externallist = false) {

	/***********************************************************/
	/* The default is to build a HOME_NET variable unless      */
	/* '$passlist' is set to 'true' when calling.              */
	/*                                                         */
	/* When '$passlist' is TRUE, a Pass List is built.         */
	/* When '$externalist' is TRUE, the EXTERNAL_NET variable  */
	/* is built.                                               */
	/*                                                         */
	/* Returns: an array of IP addresses/subnets               */
	/***********************************************************/

	global $g, $aliastable, $filterdns;
	$home_net = array();

	// If passed an empty interface configuration array, then
	// exit and return an empty list array. This would be
	// an unexpected condition.
	if (empty($suricatacfg))
		return $home_net;

	// Determine which type of list we are to generate
	if (!$externallist && ($listname == 'default' || empty($listname))) {
		// When using inline IPS mode, exclude VPNs, VIPs,
		// locally-attached network segments and the WAN IP from
		// the default Pass List as this will kill all alerts
		// and allow all traffic to pass!  We do include
		// locally-attached networks, VPNs, VIPs and the WAN IP
		// when building only the HOME_NET variable.
		if ($suricatacfg['ips_mode'] == 'ips_mode_inline' && $suricatacfg['blockoffenders'] == 'on' && $passlist == TRUE) {
			$localnet = 'no';
			$wanip = 'no';
			$vpns = 'no';
			$vips = 'no';
		}
		else {
			$localnet = 'yes';
			$wanip = 'yes';
			$vpns = 'yes';
			$vips = 'yes';
		}

		// Default the remaining Pass List/HOME_NET items to 'yes'.
		$wangw = 'yes'; $wandns = 'yes';
	}
	else {
		$list = suricata_find_list($listname);
		if (empty($list)) {
			return $list;
		}
		$localnet = $list['localnets'];
		$wanip = $list['wanips'];
		$wangw = $list['wangateips'];
		$wandns = $list['wandnsips'];
		$vips = $list['vips'];
		$vpns = $list['vpnips'];

		// Process any custom IPs or aliases defined for the list.
		// We allow dynamically updated aliases only for a Pass
		// List. So for Pass Lists, we test for null strings from
		// filter_expand_alias() and assume the passed alias is a
		// FQDN or other dynamically updated alias.
		foreach (array_get_path($list, 'address/item', []) as $addr) {
			if (!$passlist) {
				if (is_alias($addr) && (alias_get_type($addr) == "host" || alias_get_type($addr) == "network")) {
					$home_net = array_merge($home_net, explode(" ", trim(filter_expand_alias($addr))));
				} elseif (is_ipaddr($addr) || is_subnet($addr)) {
					$home_net[] = $addr;
				}
			} elseif ($passlist) {
				// Only accept "host", "network", or "urltable" aliases, otherwise treat as direct IP
				if (is_alias($addr) &&
					(alias_get_type($addr) == "host" ||
					 alias_get_type($addr) == "network" ||
					 alias_get_type($addr) == "urltable")) {
					$tmp = trim(filter_expand_alias($addr));
					if (strlen($tmp) > 0 && alias_get_type($addr) !== "urltable") {
						$home_net = array_merge($home_net, explode(" ", $tmp));
					} elseif (!in_array($addr, $home_net)) {
						$home_net[] = $addr;
					}
				} elseif (is_ipaddr($addr) || is_subnet($addr)) {
					$home_net[] = $addr;
				}
			}
		}
	}

	// Always add loopback addresses to HOME_NET
	if (!$externallist && !$passlist) {
		if (!in_array("127.0.0.1/32", $home_net)) {
			$home_net[] = "127.0.0.1/32";
		}
		if (!in_array("::1/128", $home_net)) {
			$home_net[] = "::1/128";
		}
	}

	/********************************************************************/
	/* Always put the interface running Suricata in HOME_NET unless     */
	/* it's the WAN.  WAN options are handled further down. If the      */
	/* user specifically chose not to include LOCAL_NETS in the         */
	/* PASS LIST, then do not include the Suricata interface subnet     */
	/* in the PASS LIST. The specific interface IP for the Suricata     */
	/* instance is automatically added by the underlying binary to      */
	/* a built-in list to prevent locking out the firewall interface.   */
	/********************************************************************/
	$suricataip = get_interface_ip($suricatacfg['interface']);
	if (($externallist && $localnet == 'yes') || (!$externallist && !$passlist && ($localnet == 'yes' || empty($localnet)))) {
		if (is_ipaddrv4($suricataip)) {
			if ($suricatacfg['interface'] <> "wan") {
				if ($sn = get_interface_subnet($suricatacfg['interface'])) {
					$ip = gen_subnet($suricataip, $sn) . "/{$sn}";
					if (!in_array($ip, $home_net)) {
						$home_net[] = $ip;
					}
				}
			}
		}
	}
	elseif (!$externallist && !$passlist && $localnet != 'yes') {
		if (is_ipaddrv4($suricataip) && $suricatacfg['interface'] <> "wan") {
			if (!in_array($suricataip . "/32", $home_net)) {
				$home_net[] = $suricataip . "/32";
			}
		}
	}

	// Grab the IPv6 address if we have one assigned
	$suricataip = get_interface_ipv6($suricatacfg['interface']);
	// Trim off the interface designation (e.g., %em1) if present
	if (strpos($suricataip, "%") !== FALSE) {
		$suricataip = substr($suricataip, 0, strpos($suricataip, "%"));
	}
	if (($externallist && $localnet == 'yes') || (!$externallist && !$passlist && ($localnet == 'yes' || empty($localnet)))) {
		if (is_ipaddrv6($suricataip)) {
			if ($suricatacfg['interface'] <> "wan") {
				if ($sn = get_interface_subnetv6($suricatacfg['interface'])) {
					$ip = gen_subnetv6($suricataip, $sn). "/{$sn}";
					if (!in_array($ip, $home_net)) {
						$home_net[] = $ip;
					}
				}
			}
		}
	}
	elseif (!$externallist && !$passlist && $localnet != 'yes') {
		if (is_ipaddrv6($suricataip) && $suricatacfg['interface'] <> "wan") {
			if (!in_array($suricataip . "/128", $home_net)) {
				$home_net[] = $suricataip . "/128";
			}
		}
	}

	// Add link-local IPv6 addresses if user included locally-attached networks
	$suricataip = get_interface_linklocal($suricatacfg['interface']);
	if (!empty($suricataip) && !$passlist && $localnet == 'yes') {
		// Trim off the interface designation (e.g., %em1) if present
		if (strpos($suricataip, "%") !== FALSE) {
			$suricataip = substr($suricataip, 0, strpos($suricataip, "%"));
		}
		if (!in_array($suricataip . "/128", $home_net)) {
			$home_net[] = $suricataip . "/128";
		}
	}

	// Now find all the locally-attached network subnets and add them to the
	// list if user chose to include Local Networks.
	if (($externallist && $localnet == 'yes') || ($passlist && ($localnet == 'yes' || empty($localnet))) || (!$externallist && !$passlist && ($localnet == 'yes' || empty($localnet)))) {		
		/*************************************************************************/
		/*  Iterate through the interface list and write out pass list items and */
		/*  also compile a HOME_NET list of all local interfaces for suricata.   */
		/*  Skip the WAN interface as we do not typically want that whole subnet */
		/*  whitelisted (just the i/f IP itself which was handled earlier).      */
		/*************************************************************************/
		$int_array = get_configured_interface_list();
		foreach ($int_array as $int) {
			if ($int == "wan") {
				continue;
			}
			$subnet = get_interface_ip($int);
			if (is_ipaddrv4($subnet)) {
				if ($sn = get_interface_subnet($int)) {
					$ip = gen_subnet($subnet, $sn) . "/{$sn}";
					if (!in_array($ip, $home_net)) {
						$home_net[] = $ip;
					}
				}
			}

			$subnet = get_interface_ipv6($int);
			// Trim off the interface designation (e.g., %em1) if present
			if (strpos($subnet, "%") !== FALSE) {
				$subnet = substr($subnet, 0, strpos($subnet, "%"));
			}
			if (is_ipaddrv6($subnet)) {
				if ($sn = get_interface_subnetv6($int)) {
					$ip = gen_subnetv6($subnet, $sn). "/{$sn}";
					if (!in_array($ip, $home_net)) {
						$home_net[] = $ip;
					}
				}
			}

			// Add link-local IPv6 addresses
			$suricataip = get_interface_linklocal($int);
			if (!empty($suricataip) && !$passlist) {
				// Trim off the interface designation (e.g., %em1) if present
				if (strpos($suricataip, "%") !== FALSE) {
					$suricataip = substr($suricataip, 0, strpos($suricataip, "%"));
				}
				if (!in_array($suricataip . "/128", $home_net)) {
					$home_net[] = $suricataip . "/128";
				}
			}
		}
	}

	// If user chose to include the WAN IP, then do so.
	if ($wanip == 'yes' && !$passlist) {
		$ip = get_interface_ip("wan");
		if (is_ipaddrv4($ip)) {
			if (!in_array($ip . "/32", $home_net)) {
				$home_net[] = $ip . "/32";
			}
		}
		$ip = get_interface_ipv6("wan");
		// Trim off the interface designation (e.g., %em1) if present
		if (strpos($ip, "%") !== FALSE) {
			$ip = substr($ip, 0, strpos($ip, "%"));
		}
		if (is_ipaddrv6($ip)) {
			if (!in_array($ip . "/128", $home_net)) {
				$home_net[] = $ip . "/128";
			}
		}
		// Explicitly grab the WAN Link-Local IPv6 address
		$ip = get_interface_linklocal("wan");
		if (!empty($ip)) {
			// Trim off the interface designation (e.g., %em1) if present
			if (strpos($ip, "%") !== FALSE) {
				$ip = substr($ip, 0, strpos($ip, "%"));
			}
			if (!in_array($ip . "/128", $home_net)) {
				$home_net[] = $ip . "/128";
			}
		}
	}

	// If the user chose to include WAN gateways, then do so.
	if ($wangw == 'yes') {
		// Grab the default gateway if set
		$default_gw = exec("/sbin/route -n get default |grep 'gateway:' | /usr/bin/awk '{ print $2 }'");
		if (is_ipaddrv4($default_gw) && !in_array($default_gw . "/32", $home_net)) {
			$home_net[] = $default_gw . "/32";
		}
		if (is_ipaddrv6($default_gw) && !in_array($default_gw . "/128", $home_net)) {
			$home_net[] = $default_gw . "/128";
		}

		// Get any other interface gateway and put in $HOME_NET if not there already
		$gw = get_interface_gateway($suricatacfg['interface']);
		if (is_ipaddrv4($gw) && !in_array($gw . "/32", $home_net)) {
			$home_net[] = $gw . "/32";
		}
		$gw = get_interface_gateway_v6($suricatacfg['interface']);
		// Trim off the interface designation (e.g., %em1) if present
		if (strpos($gw, "%") !== FALSE) {
			$gw = substr($gw, 0, strpos($gw, "%"));
		}
		if (is_ipaddrv6($gw) && !in_array($gw . "/128", $home_net)) {
			$home_net[] = $gw . "/128";
		}
	}

	// If the user chose to include WAN DNS servers, then do so.
	if ($wandns == 'yes') {
		// Add DNS server for WAN interface to Pass List
		$dns_servers = get_dns_nameservers(false, true);
		foreach ($dns_servers as $dns) {
			if (is_ipaddrv4($dns)) {
				$dns .= "/32";
			} else {
				$dns .= "/128";
			}
			if (!in_array($dns, $home_net)) {
				$home_net[] = $dns;
			}
		}
	}

	// If the user chose to include Virtual IPs, then do so.
	if($vips == 'yes') {
		// iterate all vips and add to passlist
		foreach(config_get_path('virtualip/vip', []) as $vip) {
			if (is_ipaddrv4($vip['subnet'])) {
				$ip = gen_subnet($vip['subnet'], $vip['subnet_bits']) . "/{$vip['subnet_bits']}";
			} else {
				$ip = gen_subnetv6($vip['subnet'], $vip['subnet_bits']) . "/{$vip['subnet_bits']}";
			}
			if (!in_array($ip, $home_net)) {
				$home_net[] = $ip;
			}
		}
	}

	// If the user chose to include VPNs, then grab a list of 
	// vpns enabled and include them.  These come back as CIDR
	// mask networks.
	if ($vpns == 'yes') {
		$vpns_list = suricata_get_vpns_list();
		if (!empty($vpns_list)) {
			// Convert the returned space-delimited string to an array
			// and then add each VPN address to our HOME_NET array.
			$vpns = explode(" ", $vpns_list);
			foreach ($vpns as $vpn) {
				$home_net[] = trim($vpn);
			}
			unset($vpns, $vpns_list);
		}
	}

	// Validate the HOME_NET entries
	$valresult = array();
	foreach ($home_net as $vald) { 
		if (empty($vald) || (!is_subnet($vald) && !is_ipaddr($vald) && !is_alias($vald))) {
			continue;
		}
		$vald = trim($vald);
		if (empty($valresult[$vald])) {
			$valresult[$vald] = $vald;
		}
	}

	// Release memory no longer required
	unset($home_net);

	// Sort the list and return it
	natsort($valresult);
	return $valresult;
}

function suricata_cron_job_exists($crontask, $match_time=FALSE, $minute="0", $hour="*", $monthday="*", $month="*", $weekday="*", $who="root") {

	/************************************************************
	 * This function iterates the cron[] array in the config    *
	 * to determine if the passed $crontask entry exists.  It   *
	 * returns TRUE if the $crontask already exists, or FALSE   *
	 * if there is no match.                                    *
	 *                                                          *
	 * The $match_time flag, when set, causes a test of the     *
	 * configured task execution times along with the task      *
	 * when checking for a match.                               *
	 *                                                          *
	 * We use this to prevent unneccessary config writes if     *
	 * the $crontask already exists.                            *
	 ************************************************************/

	foreach(config_get_path('cron/item', []) as $item) {
		if(strpos($item['command'], $crontask) !== FALSE) {
			if ($match_time) {
				if ($item['minute'] != $minute) {
					return FALSE;
				}
				if ($item['hour'] != $hour) {
					return FALSE;
				}
				if ($item['mday'] != $monthday) {
					return FALSE;
				}
				if ($item['month'] != $month) {
					return FALSE;
				}
				if ($item['wday'] != $weekday) {
					return FALSE;
				}
				if ($item['who'] != $who) {
					return FALSE;
				}
			}
			return TRUE;
		}
	}
	return FALSE;
}

function suricata_rules_up_install_cron($should_install=true) {

	// If called with FALSE as argument, then we're removing 
	// the existing job.
	if ($should_install == FALSE) {
		if (suricata_cron_job_exists("suricata_check_for_rule_updates.php", FALSE)) {
			install_cron_job("suricata_check_for_rule_updates.php", false);
		}
		return;
	}

	// Get auto-rule update parameter from configuration
	$suricata_rules_up_info_ck = config_get_path('installedpackages/suricata/config/0/autoruleupdate', '');

	// See if a customized start time has been set for rule file updates
	if (config_get_path('installedpackages/suricata/config/0/autoruleupdatetime')) {
		$suricata_rules_upd_time = config_get_path('installedpackages/suricata/config/0/autoruleupdatetime');
	} else {
		$suricata_rules_upd_time = "00:" . str_pad(strval(random_int(0,59)), 2, "00", STR_PAD_LEFT);
	}

	if ($suricata_rules_up_info_ck == "6h_up") {
		$suricata_rules_up_min = intval(substr($suricata_rules_upd_time, -2));
		$hour = intval(substr($suricata_rules_upd_time, 0, 2));
		$suricata_rules_up_hr = strval($hour);
		for ($i=0; $i<3; $i++) {
			$hour += 6;
			if ($hour > 23) {
				$hour -= 24;
			}
			$suricata_rules_up_hr .= "," . strval($hour);
		}
		$suricata_rules_up_mday = "*";
		$suricata_rules_up_month = "*";
		$suricata_rules_up_wday = "*";
	}
	if ($suricata_rules_up_info_ck == "12h_up") {
		$suricata_rules_up_min = intval(substr($suricata_rules_upd_time, -2));
		$hour = intval(substr($suricata_rules_upd_time, 0, 2));
		$suricata_rules_up_hr = strval($hour) . ",";
		$hour += 12;
		if ($hour > 23) {
			$hour -= 24;
		}
		$suricata_rules_up_hr .= strval($hour);
		$suricata_rules_up_mday = "*";
		$suricata_rules_up_month = "*";
		$suricata_rules_up_wday = "*";
	}
	if ($suricata_rules_up_info_ck == "1d_up") {
		$suricata_rules_up_min = intval(substr($suricata_rules_upd_time, -2));
		$suricata_rules_up_hr = intval(substr($suricata_rules_upd_time, 0, 2));
		$suricata_rules_up_mday = "*/1";
		$suricata_rules_up_month = "*";
		$suricata_rules_up_wday = "*";
	}
	if ($suricata_rules_up_info_ck == "4d_up") {
		$suricata_rules_up_min = intval(substr($suricata_rules_upd_time, -2));
		$suricata_rules_up_hr = intval(substr($suricata_rules_upd_time, 0, 2));
		$suricata_rules_up_mday = "*/4";
		$suricata_rules_up_month = "*";
		$suricata_rules_up_wday = "*";
	}
	if ($suricata_rules_up_info_ck == "7d_up") {
		$suricata_rules_up_min = intval(substr($suricata_rules_upd_time, -2));
		$suricata_rules_up_hr = intval(substr($suricata_rules_upd_time, 0, 2));
		$suricata_rules_up_mday = "*/7";
		$suricata_rules_up_month = "*";
		$suricata_rules_up_wday = "*";
	}
	if ($suricata_rules_up_info_ck == "28d_up") {
		$suricata_rules_up_min = intval(substr($suricata_rules_upd_time, -2));
		$suricata_rules_up_hr = intval(substr($suricata_rules_upd_time, 0, 2));
		$suricata_rules_up_mday = "*/28";
		$suricata_rules_up_month = "*";
		$suricata_rules_up_wday = "*";
	}

	// Construct the basic cron command task
	$command = "/usr/bin/nice -n20 /usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_check_for_rule_updates.php";

	// If there are no changes in the cron job command string from the existing job, then exit
	if (suricata_cron_job_exists($command, TRUE, $suricata_rules_up_min, $suricata_rules_up_hr, $suricata_rules_up_mday, $suricata_rules_up_month, $suricata_rules_up_wday, "root")) {
		return;
	}

	// Else install the new or updated cron job by removing the
	// existing job first, then installing the new or updated job.
	install_cron_job("suricata_check_for_rule_updates.php", false);
	install_cron_job($command, $should_install, $suricata_rules_up_min, $suricata_rules_up_hr, $suricata_rules_up_mday, $suricata_rules_up_month, $suricata_rules_up_wday, "root");
}

function suricata_loglimit_install_cron($should_install=true) {

	// See if simply removing existing "loglimit" job for Suricata
	if ($should_install == FALSE) {
		if (suricata_cron_job_exists("suricata/suricata_check_cron_misc.inc", FALSE)) {
			install_cron_job("suricata_check_cron_misc.inc", false);
		}
		return;
	}

	// If there are no changes in the cron job command string from the existing job, then exit.
	if ($should_install && suricata_cron_job_exists("/usr/local/pkg/suricata/suricata_check_cron_misc.inc", TRUE, "*/5")) {
		return;
	}

	// Else install the new or updated cron job by removing the
	// existing job first, then installing the new or updated job.
	install_cron_job("suricata_check_cron_misc.inc", false);
	install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_check_cron_misc.inc", $should_install, "*/5");
}

function suricata_rm_blocked_install_cron($should_install) {
	$suri_pf_table = SURICATA_PF_TABLE;

	// See if simply removing existing "expiretable" job for Suricata
	if ($should_install == FALSE) {
		if (suricata_cron_job_exists("{$suri_pf_table}", FALSE)) {
			install_cron_job("{$suri_pf_table}", false);
		}
		return;
	}

	$suricata_rm_blocked_info_ck = config_get_path('installedpackages/suricata/config/0/rm_blocked', '');

	if ($suricata_rm_blocked_info_ck == "15m_b") {
		$suricata_rm_blocked_min = "*/1";
		$suricata_rm_blocked_hr = "*";
		$suricata_rm_blocked_mday = "*";
		$suricata_rm_blocked_month = "*";
		$suricata_rm_blocked_wday = "*";
		$suricata_rm_blocked_expire = "900";
	}
	if ($suricata_rm_blocked_info_ck == "30m_b") {
		$suricata_rm_blocked_min = "*/5";
		$suricata_rm_blocked_hr = "*";
		$suricata_rm_blocked_mday = "*";
		$suricata_rm_blocked_month = "*";
		$suricata_rm_blocked_wday = "*";
		$suricata_rm_blocked_expire = "1800";
	}
	if ($suricata_rm_blocked_info_ck == "1h_b") {
		$suricata_rm_blocked_min = "*/5";
		$suricata_rm_blocked_hr = "*";
		$suricata_rm_blocked_mday = "*";
		$suricata_rm_blocked_month = "*";
		$suricata_rm_blocked_wday = "*";
		$suricata_rm_blocked_expire = "3600";
	}
	if ($suricata_rm_blocked_info_ck == "3h_b") {
		$suricata_rm_blocked_min = "*/5";
		$suricata_rm_blocked_hr = "*";
		$suricata_rm_blocked_mday = "*";
		$suricata_rm_blocked_month = "*";
		$suricata_rm_blocked_wday = "*";
		$suricata_rm_blocked_expire = "10800";
	}
	if ($suricata_rm_blocked_info_ck == "6h_b") {
		$suricata_rm_blocked_min = "*/5";
		$suricata_rm_blocked_hr = "*";
		$suricata_rm_blocked_mday = "*";
		$suricata_rm_blocked_month = "*";
		$suricata_rm_blocked_wday = "*";
		$suricata_rm_blocked_expire = "21600";
	}
	if ($suricata_rm_blocked_info_ck == "12h_b") {
		$suricata_rm_blocked_min = "*/5";
		$suricata_rm_blocked_hr = "*";
		$suricata_rm_blocked_mday = "*";
		$suricata_rm_blocked_month = "*";
		$suricata_rm_blocked_wday = "*";
		$suricata_rm_blocked_expire = "43200";
	}
	if ($suricata_rm_blocked_info_ck == "1d_b") {
		$suricata_rm_blocked_min = "*/5";
		$suricata_rm_blocked_hr = "*";
		$suricata_rm_blocked_mday = "*";
		$suricata_rm_blocked_month = "*";
		$suricata_rm_blocked_wday = "*";
		$suricata_rm_blocked_expire = "86400";
	}
	if ($suricata_rm_blocked_info_ck == "4d_b") {
		$suricata_rm_blocked_min = "*/5";
		$suricata_rm_blocked_hr = "*";
		$suricata_rm_blocked_mday = "*";
		$suricata_rm_blocked_month = "*";
		$suricata_rm_blocked_wday = "*";
		$suricata_rm_blocked_expire = "345600";
	}
	if ($suricata_rm_blocked_info_ck == "7d_b") {
		$suricata_rm_blocked_min = "*/5";
		$suricata_rm_blocked_hr = "*";
		$suricata_rm_blocked_mday = "*";
		$suricata_rm_blocked_month = "*";
		$suricata_rm_blocked_wday = "*";
		$suricata_rm_blocked_expire = "604800";
	}
	if ($suricata_rm_blocked_info_ck == "28d_b") {
		$suricata_rm_blocked_min = "*/5";
		$suricata_rm_blocked_hr = "*";
		$suricata_rm_blocked_mday = "*";
		$suricata_rm_blocked_month = "*";
		$suricata_rm_blocked_wday = "*";
		$suricata_rm_blocked_expire = "2419200";
	}

	// Construct the basic cron command task
	$command = "/usr/bin/nice -n20 /sbin/pfctl -q -t {$suri_pf_table} -T expire {$suricata_rm_blocked_expire}";

	// If there are no changes in the cron job command string from the existing job, then exit.
	if (suricata_cron_job_exists($command, TRUE, $suricata_rm_blocked_min, $suricata_rm_blocked_hr, $suricata_rm_blocked_mday, $suricata_rm_blocked_month, $suricata_rm_blocked_wday, "root")) {
		return;
	}

	// Else install the new or updated cron job by removing the
	// existing job first, then installing the new or updated job.
	install_cron_job("{$suri_pf_table}", false);
	install_cron_job($command, $should_install, $suricata_rm_blocked_min, $suricata_rm_blocked_hr, $suricata_rm_blocked_mday, $suricata_rm_blocked_month, $suricata_rm_blocked_wday, "root");
}

function sync_suricata_package_config() {
	global $g;

	$suricatadir = SURICATADIR;
	$rcdir = RCFILEPREFIX;

	/* Create required log and db directories in /var on each sync, in case /var is in RAM. */
	safe_mkdir(SURICATALOGDIR);
	safe_mkdir(SURICATA_IPREP_PATH);
	safe_mkdir(SURICATA_SID_MODS_PATH);

	// Do not start config build if there are no Suricata-configured interfaces
	if (count(config_get_path('installedpackages/suricata/rule', [])) < 1) {
		return;
	}

	foreach (config_get_path('installedpackages/suricata/rule', []) as $value) {
		/* Skip configuration of any disabled instance or */
		/* an instance whose assigned physical interface  */
		/* has been removed.                              */
		if (($value['enable'] != 'on') || (get_real_interface($value['interface']) == "")) {
			continue;
		}

		// create a suricata.yaml file for interface
		suricata_generate_yaml($value);
	}

	// create suricata bootup file suricata.sh
	suricata_create_rc();

	// setup the log directory size check job if enabled
	suricata_loglimit_install_cron(true);

	// setup the suricata rules update job if enabled
	suricata_rules_up_install_cron(config_get_path('installedpackages/suricata/config/0/autoruleupdate', '') != "never_up" ? true : false);

	// set the suricata blocked hosts time
	suricata_rm_blocked_install_cron(config_get_path('installedpackages/suricata/config/0/rm_blocked', '') != "never_b" ? true : false);

	// Do not attempt package sync if reinstalling package or booting
	if (!isset($g['suricata_postinstall']) && !is_platform_booting()) {
		suricata_sync_on_changes();
	}
}

function suricata_load_suppress_sigs($suricatacfg, $track_by=false) {

	/**********************************************************/
	/* This function loads the GEN_ID and SIG_ID for all the  */
	/* suppressed alert entries from the Suppression List of  */
	/* the passed Suricata interface.  The results are        */
	/* returned in an array with GEN_ID and SIG_ID as the     */
	/* primary keys.  Any "track by_src" or "track by_dst"    */
	/* entries in the Suppression List are tacked on as       */
	/* additional keys in the array along with the IP address */
	/* in either IPv4 or IPv6 format when $track_by is passed */
	/* as true.                                               */
	/*                                                        */
	/* Sample returned array:                                 */
	/*  $suppress[1][2069] = "suppress"                       */
	/*  $suppress[1][2070]['by_src']['10.1.1.5'] = "suppress" */
	/*  $suppress[1][2070]['by_dst']['10.1.1.6'] = "suppress" */
	/*                                                        */
	/**********************************************************/

	$suppress = array();

	foreach (config_get_path('installedpackages/suricata/suppress/item', []) as $a_id => $alist) {
		if ($alist['name'] == $suricatacfg['suppresslistname']) {
			if (!empty($alist['suppresspassthru'])) {
				$tmplist = str_replace("\r", "", base64_decode($alist['suppresspassthru']));
				$tmp = explode("\n", $tmplist);
				foreach ($tmp as $line) {
					// Skip any blank lines
					if (trim($line, " \n") == "") {
						continue;
					}
					// Skip any comment lines
					if (preg_match('/^\s*#/', $line)) {
						continue;
					}
					/* See if entry suppresses GID:SID for all hosts */
					if (preg_match('/\s*suppress\s*gen_id\b\s*(\d+),\s*sig_id\b\s*(\d+)\s*$/i', $line, $matches)) {
						$genid = $matches[1];
						$sigid = $matches[2];
						if (!empty($genid) && !empty($sigid)) {
							array_set_path($suppress, "{$genid}/{$sigid}", "suppress");
						}
					}

					/* Get "track by IP" entries if requested */
					if ($track_by) {
						/* See if entry suppresses only by SRC or DST IPv4 address */
						if (preg_match('/\s*suppress\s*gen_id\b\s*(\d+),\s*sig_id\b\s*(\d+),\s*track\s*(by_src|by_dst),\s*ip\s*(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s*$/i', $line, $matches)) {
							$genid = $matches[1];
							$sigid = $matches[2];
							$whichip = trim($matches[3]);
							$ip = $matches[4];
							if (!empty($genid) && !empty($sigid) && !empty($whichip) && !empty($ip)) {
								array_set_path($suppress, "{$genid}/{$sigid}/{$whichip}/{$ip}", "suppress");
							}
						}
						/* See if entry suppresses only by SRC or DST IPv6 address */
						if (preg_match('/\s*suppress\s*gen_id\b\s*(\d+),\s*sig_id\b\s*(\d+),\s*track\s*(by_src|by_dst),\s*ip\s*([0-9a-f\.:]+)\s*$/i', $line, $matches)) {
							$genid = $matches[1];
							$sigid = $matches[2];
							$whichip = trim($matches[3]);
							$ip = trim($matches[4]);
							if (!empty($genid) && !empty($sigid) && !empty($whichip) && !empty($ip)) {
								array_set_path($suppress, "{$genid}/{$sigid}/{$whichip}/{$ip}", "suppress");
							}
						}
					}
				}
				unset($tmp);
			}
			break;
		}
	}
	return $suppress;
}

/* This returns size of passed directory or file in 1024-byte blocks */
function suricata_Getdirsize($node) {
	if(!is_readable($node)) {
		return false;
	}

	$blah = exec( "/usr/bin/du -kdc $node" );
	return substr( $blah, 0, strpos($blah, chr(9) ) );
}

function suricata_build_sid_msg_map($rules_path, $sid_file) {

	/*************************************************************/
	/* This function reads all the rules file in the passed      */
	/* $rules_path variable and produces a properly formatted    */
	/* sid-msg.map v2 file for use by Suricata.                  */
	/*                                                           */
	/* This function produces the new v2 format sid-msg.map      */
	/* with the field layout as follows:                         */
	/*                                                           */
	/*  GID || SID || REV || CLASSTYPE || PRI || MSG || REF ...  */
	/*                                                           */
	/*  On Entry: $rules_path --> array or directory of files    */
	/*                            or a single file containing    */
	/*                            the rules to read.             */
	/*              $sid_file --> the complete destination path  */
	/*                            and filename for the output    */
	/*                            sid-msg.map file.              */
	/*************************************************************/

	$sidMap = array();
	$rule_files = array();

	// First check if we were passed a directory, a single file
	// or an array of filenames to read. Set our $rule_files
	// variable accordingly. If we can't figure it out, return
	// and don't write a sid-msg.map file.
	if (is_string($rules_path)) {
		if (is_dir($rules_path)) {
			$rule_files = glob($rules_path . "*.rules");
		} elseif (is_file($rules_path)) {
			$rule_files = (array)$rules_path;
		}
	}
	elseif (is_array($rules_path)) {
		$rule_files = $rules_path;
	} else {
		return;
	}

	// Read the rule files into an array, then iterate the list
	foreach ($rule_files as $file) {

		// Don't process files with "deleted" in the filename
		if (stristr($file, "deleted")) {
			continue;
		}

		// Read the file into an array, skipping missing files.
		if (!file_exists($file)) {
			continue;
		}

		$rules_array = file($file, FILE_SKIP_EMPTY_LINES);
		$record = "";
		$b_Multiline = false;

		// Read and process each line from the rules in the current file
		foreach ($rules_array as $rule) {

			// Skip any non-rule lines unless we're in multiline mode.
			if (!preg_match('/^\s*#*\s*(alert|drop|pass)/i', $rule) && !$b_Multiline) {
				continue;
			}

			// Test for a multi-line rule, and reassemble the
			// pieces back into a single line.
			if (preg_match('/\\\\s*[\n]$/m', $rule)) {
				$rule = substr($rule, 0, strrpos($rule, '\\'));
				$record .= $rule;
				$b_Multiline = true;
				continue;
			}
			// If the last segment of a multiline rule, then
			// append it onto the previous parts to form a
			// single-line rule for further processing below.
			elseif (!preg_match('/\\\\s*[\n]$/m', $rule) && $b_Multiline) {
				$record .= $rule;
				$rule = $record;
			}
			$b_Multiline = false;
			$record = "";

			// Parse the rule to find sid and any references.
			$gid = '1';             // default to 1 for regular rules
			$sid = '';
			$rev = '';
			$classtype = 'NOCLASS'; // required default for v2 format
			$priority = '0';        // required default for v2 format
			$msg = '';
			$matches = '';
			$sidEntry = '';
			if (preg_match('/\bmsg\s*:\s*"(.+?)"\s*;/i', $rule, $matches)) {
				$msg = trim($matches[1]);
			}
			if (preg_match('/\bsid\s*:\s*(\d+)\s*;/i', $rule, $matches)) {
				$sid = trim($matches[1]);
			}
			if (preg_match('/\bgid\s*:\s*(\d+)\s*;/i', $rule, $matches)) {
				$gid = trim($matches[1]);
			}
			if (preg_match('/\brev\s*:\s*([^\;]+)/i', $rule, $matches)) {
				$rev = trim($matches[1]);
			}
			if (preg_match('/\bclasstype\s*:\s*([^\;]+)/i', $rule, $matches)) {
				$classtype = trim($matches[1]);
			}
			if (preg_match('/\bpriority\s*:\s*([^\;]+)/i', $rule, $matches)) {
				$priority = trim($matches[1]);
			}

			if (!empty($gid) && !empty($sid) && !empty($msg)) {
				$sidEntry = $gid . ' || ' . $sid . ' || ' . $rev . ' || ' . $classtype . ' || ';
				$sidEntry .= $priority . ' || ' . $msg;
				preg_match_all('/\breference\s*:\s*([^\;]+)/i', $rule, $matches);
				foreach ($matches[1] as $ref) {
					$sidEntry .= " || " . trim($ref);
				}
				$sidEntry .= "\n";
				$sidMap[] = $sidEntry;
			}
		}
        }
	// Sort the generated sid-msg map
	natcasesort($sidMap);

	// Now print the result to the supplied file
	@file_put_contents($sid_file, "#v2\n# sid-msg.map file auto-generated by Suricata.\n\n");
	@file_put_contents($sid_file, array_values($sidMap), FILE_APPEND);
}

function suricata_merge_reference_configs($cfg_in, $cfg_out) {

	/***********************************************************/
	/* This function takes a list of "reference.config" files  */
	/* in the $cfg_in array and merges them into a single      */
	/* file specified by $cfg_out.  The merging is done so     */
	/* no duplication of lines occurs in the output file.      */
	/***********************************************************/

	$outMap = array();
	foreach ($cfg_in as $file) {
		if (!file_exists($file)) {
			continue;
		}
		$in = file($file, FILE_SKIP_EMPTY_LINES);
		foreach ($in as $line) {
			/* Skip comment lines  */
			if (preg_match('/^\s*#/', $line)) {
				continue;
			}
			if (preg_match('/(\:)\s*(\w+)\s*(.*)/', $line, $matches)) {
				if (!empty($matches[2]) && !empty($matches[3])) {
					$matches[2] = trim($matches[2]);
					if (!array_key_exists($matches[2], $outMap)) {
						if (!is_array($outMap[$matches[2]])) {
							$outMap[$matches[2]] = array();
						}
						$outMap[$matches[2]] = trim($matches[3]);
					}
				}
			}
		}             
	}
	// Sort the new reference map.
	uksort($outMap,'strnatcasecmp');

	// Do NOT write an empty references.config file, just
	// exit instead.
	if (empty($outMap)) {
		return false;
	}

	// Format and write it to the supplied output file.
	$format = "config reference: %-12s %s\n";
	foreach ($outMap as $key=>$value) {
		$outMap[$key] = sprintf($format, $key, $value);
	}
	@file_put_contents($cfg_out, array_values($outMap));
	return true;
}

function suricata_merge_classification_configs($cfg_in, $cfg_out) {

	/************************************************************/
	/* This function takes a list of "classification.config"    */
	/* files in the $cfg_in array and merges them into a        */
	/* single file specified by $cfg_out.  The merging is done  */
	/* so no duplication of lines occurs in the output file.    */
	/************************************************************/

	$outMap = array();
	foreach ($cfg_in as $file) {
		if (!file_exists($file)) {
			continue;
		}
		$in = file($file, FILE_SKIP_EMPTY_LINES);
		foreach ($in as $line) {
			if (preg_match('/(.*:)(\s*.*),(.*),(.*)/', $line, $matches)) {
				/* Skip comment lines  */
				if (preg_match('/^\s*#/', $line)) {
					continue;
				}
				if (!empty($matches[2]) && !empty($matches[3]) && !empty($matches[4])) {
					$matches[2] = trim($matches[2]);
					if (!array_key_exists($matches[2], $outMap)) {
						if (!is_array($outMap[$matches[2]])) {
							$outMap[$matches[2]] = array();
						}
						$outMap[$matches[2]] = trim($matches[3]) . "," . trim($matches[4]);
					}
				}
			}
		}             
	}
	// Sort the new classification map.
	uksort($outMap,'strnatcasecmp');

	// Do NOT write an empty classification.config file, just
	// exit instead.
	if (empty($outMap)) {
		return false;
	}

	// Format and write it to the supplied output file.
	$format = "config classification: %s,%s\n";
	foreach ($outMap as $key=>$value) {
		$outMap[$key] = sprintf($format, $key, $value);
	}
	@file_put_contents($cfg_out, array_values($outMap));
	return true;
}

function suricata_load_rules_map($rules_path) {

	/***************************************************************/
	/* This function loads and returns an array with all the rules */
	/* found in the *.rules files in the passed rules path.        */
	/*                                                             */
	/* $rules_path can be:                                         */
	/*      a directory (assumed to contain *.rules files)         */
	/*      a filename (identifying a specific *.rules file)       */
	/*      an array of filenames (identifying *.rules files)      */
	/***************************************************************/

	$map_ref = array();
	$rule_files = array();

	if (empty($rules_path)) {
		return $map_ref;
	}

	/************************************************************************************
	 * Read all the rules into the map array.
	 * The structure of the map array is:
	 *
	 *  map[gid][sid]['rule']['category']['action']['disabled']['managed']['noalert']
         *     ['default_state']['default_action']['state_toggled']['modified']['flowbits']
	 *
	 *  where:
	 *   gid            = Generator ID from rule, or 1 if general text rule
	 *   sid            = Signature ID from rule
         *   rule           = Complete rule text
	 *   category       = File name of file containing the rule
	 *   action         = alert, drop, reject or pass
	 *   disabled       = 1 if rule is disabled (commented out), 0 if 
	 *                    rule is enabled
	 *   managed        = 1 if rule is auto-managed by SID MGMT process,
	 *                    0 if not auto-managed
	 *   noalert        = 1 if rule contains "noalert" or "flowbits:noalert"
	 *		        options
	 *   default_state  = 1 if rule is default enabled, 0 if default disabled
	 *   default_action = alert, drop, reject or pass  
	 *   state_toggled  = 1 if rule was toggled by SID MGMT process,
	 *                    0 if not toggled
	 *   modified       = 1 if rule action or content is modified by SID MGMT or
	 *                      IPS Policy process,
	 *                    0 if not modified
	 *   flowbits       = Array of applicable flowbits if rule contains 
	 *                    flowbits options
	 ************************************************************************************/

	// First check if we were passed a directory, a single file
	// or an array of filenames to read. Set our $rule_files
	// variable accordingly. If we can't figure it out, return
	// an empty rules map array.
	if (is_string($rules_path)) {
		if (is_dir($rules_path)) {
			$rule_files = glob($rules_path . "*.rules");
		} elseif (is_file($rules_path)) {
			$rule_files = (array)$rules_path;
		}
	}
	elseif (is_array($rules_path)) {
		$rule_files = $rules_path;
	} else {
		return $map_ref;
	}

        // Read the rule files into an array, then iterate the list
	// to process the rules from the files one-by-one.
	foreach ($rule_files as $file) {

		// Don't process files with "deleted" in the filename.
		if (stristr($file, "deleted")) {
			continue;
		}

		// Read the file contents into an array, skipping
		// missing files.
		if (!file_exists($file)) {
			continue;
		}

		$rules_array = file($file, FILE_SKIP_EMPTY_LINES);
		$record = "";
		$b_Multiline = false;

		// Read and process each line from the rules in the
		// current file into an array.
		foreach ($rules_array as $rule) {

			// Skip any lines that may be just spaces.
			if (trim($rule, " \n") == "") {
				continue;
			}

			// Skip any non-rule lines unless we're in
			// multiline mode.
			if (!preg_match('/^\s*#*\s*(alert|drop|pass|reject)/i', $rule) && !$b_Multiline) {
				continue;
			}

			// Test for a multi-line rule; loop and reassemble
			// the pieces back into a single line.
			if (preg_match('/\\\\s*[\n]$/m', $rule)) {
				$rule = substr($rule, 0, strrpos($rule, '\\'));
				$record .= $rule;
				$b_Multiline = true;
				continue;
			}
			// If the last segment of a multiline rule, then
			// append it onto the previous parts to form a
			// single-line rule for further processing below.
			elseif (!preg_match('/\\\\s*[\n]$/m', $rule) && $b_Multiline) {
				$record .= $rule;
				$rule = $record;
			}

			// We have an actual single-line rule, or else a
			// re-assembled multiline rule that is now a
			// single-line rule, so store it in our rules map.

			// Get and test the SID.  If we don't find one,
			// ignore and skip this rule as it is invalid.
			$sid = suricata_get_sid($rule);
			if (empty($sid)) {
				$b_Multiline = false;
				$record = "";
				continue;
			}

			$gid = suricata_get_gid($rule);
			array_init_path($map_ref, "{$gid}/{$sid}");
			$map_ref[$gid][$sid]['rule'] = $rule;
			$map_ref[$gid][$sid]['category'] = basename($file, ".rules");
			$map_ref[$gid][$sid]['state_toggled'] = 0;
			$map_ref[$gid][$sid]['modified'] = 0;
			$map_ref[$gid][$sid]['managed'] = 0;

			// Check for "noalert;" rule option
			if (strpos($rule, 'noalert;') !== FALSE) {
				$map_ref[$gid][$sid]['noalert'] = 1;
			} else {
				$map_ref[$gid][$sid]['noalert'] = 0;
			}				

			// Grab the rule action
			$matches = array();
			if (preg_match('/^\s*#*\s*(alert|drop|pass|reject)/i', $rule, $matches)) {
				$map_ref[$gid][$sid]['action'] = $matches[1];
				$map_ref[$gid][$sid]['default_action'] = $matches[1];
			} else {
				$map_ref[$gid][$sid]['action'] = "";
			}

			// Determine if default state is "disabled"
			if (preg_match('/^\s*\#+/', $rule)) {
				$map_ref[$gid][$sid]['disabled'] = 1;
				$map_ref[$gid][$sid]['default_state'] = 0;
			} else {
				$map_ref[$gid][$sid]['disabled'] = 0;
				$map_ref[$gid][$sid]['default_state'] = 1;
			}

			// Grab any associated flowbits from the rule.
			$map_ref[$gid][$sid]['flowbits'] = suricata_get_flowbits($rule);
			
			// Reset our local flag and record variables
			// for the next rule in the set.
			$b_Multiline = false;
			$record = "";
		}

		// Zero out our processing array and get the next file.
		unset($rules_array);
	}
	return $map_ref;
}

function suricata_get_gid($rule) {

	/****************************************************************/
	/* If a gid is defined, then return it, else default to "1" for */
	/* general text rules match.                                    */
	/****************************************************************/

	if (preg_match('/\bgid\s*:\s*(\d+)\s*;/i', $rule, $matches)) {
		return trim($matches[1]);
	} else {
		return "1";
	}
}

function suricata_get_sid($rule) {

	/***************************************************************/
	/* If a sid is defined, then return it, else default to an     */
	/* empty value.                                                */
	/***************************************************************/

	if (preg_match('/\bsid\s*:\s*(\d+)\s*;/i', $rule, $matches)) {
		return trim($matches[1]);
	} else {
		return "";
	}
}

function suricata_get_msg($rule) {

	/**************************************************************/
	/* Return the MSG section of the passed rule as a string.     */
	/**************************************************************/

	$msg = "";
	if (preg_match('/\bmsg\s*:\s*"(.+?)"\s*;/i', $rule, $matches)) {
		$msg = trim($matches[1]);
	}
	return $msg;
}

function suricata_get_flowbits($rule) {

	/*************************************************************/
	/* This will pull out "flowbits:" options from the rule text */
	/* and return them in an array (minus the "flowbits:" part). */
	/*************************************************************/

	$flowbits = array();

	// Grab any "flowbits:set, setx, unset, isset or toggle" options first.
	// Examine flowbits targets for logical operators to capture all targets. 
	if (preg_match_all('/flowbits\b\s*:\s*(set|setx|unset|toggle|isset|isnotset)\s*,([^;]+)/i', $rule, $matches)) {
		$i = -1;
		while (++$i < count($matches[1])) {
			$action = trim($matches[1][$i]);
			$target = preg_split('/[&|]/', $matches[2][$i]);
			foreach ($target as $t) {
				$flowbits[] = "{$action}," . trim($t);
			}
		}
	}

	// Include the "flowbits:noalert or reset" options, if present.
	if (preg_match_all('/flowbits\b\s*:\s*(noalert|reset)\b/i', $rule, $matches)) {
		$i = -1;
		while (++$i < count($matches[1])) {
			$flowbits[] = trim($matches[1][$i]);
		}
	}

	return $flowbits;
}

function suricata_get_checked_flowbits($rules_map) {

	/*************************************************************/
	/* This function checks all the currently enabled rules to   */
	/* find any checked flowbits, and returns the checked        */
	/* flowbit names in an array.                                */
	/*************************************************************/

	$checked_flowbits = array();
	foreach ($rules_map as $rulem) {
		if (!is_array($rulem)) {
			continue;
		}
		foreach ($rulem as $rulem2) {
			if (!is_array($rulem2)) {
				continue;
			}
			if ($rulem2['disabled'] == 1) {
				continue;
			}
			if (empty($rulem2['flowbits'])) {
				continue;
			}
			if (!is_array($rulem2['flowbits'])) {
				continue;
			}
			foreach ($rulem2['flowbits'] as $flowbit) {
				if (empty($flowbit)) {
					continue;
				}
				// If no comma in flowbits option, then  skip it.
				$pos = strpos($flowbit, ",");
				if ($pos === false) {
					continue;
				}
				$action = substr(strtolower($flowbit), 0, $pos);
				if ($action == "isset" || $action == "isnotset") {
					$target = preg_split('/[&|]/', substr($flowbit, $pos + 1));
					foreach ($target as $t) {
						if (!empty($t) && !isset($checked_flowbits[$t])) {
							array_init_path($checked_flowbits, $t);
							$checked_flowbits[$t] = $action;
						}
					}
				}
			}
		}
	}
	unset($rulem, $rulem2);
	return $checked_flowbits;
}

function suricata_get_set_flowbits($rules_map) {

	/*********************************************************/
	/* This function checks all the currently enabled rules  */
	/* to find any set flowbits, and returns the flowbit     */
	/* names in an array.                                    */
	/*********************************************************/

	$set_flowbits = array();
	foreach ($rules_map as $rulem) {
		if (!is_array($rulem)) {
			continue;
		}
		foreach ($rulem as $rulem2) {
			if ($rulem2['disabled'] == 1) {
				continue;
			}
			if (empty($rulem2['flowbits'])) {
				continue;
			}
			if (!is_array($rulem2['flowbits'])) {
				continue;
			}
			foreach ($rulem2['flowbits'] as $flowbit) {
				if (empty($flowbit)) {
					continue;
				}
				/* If no comma in flowbits option, then  skip it. */
				$pos = strpos($flowbit, ",");
				if ($pos === false) {
					continue;
				}
				$action = substr(strtolower($flowbit), 0, $pos);
				if ($action == "set" || $action == "toggle" || $action == "setx") {
					$target = preg_split('/[&|]/', substr($flowbit, $pos + 1));
					foreach ($target as $t) {
						if (!empty($t) && !isset($set_flowbits[$t])) {
							array_init_path($set_flowbits, $t);
							$set_flowbits[$t] = $action;
						}
					}
				}
			}
		}
	}
	unset($rulem, $rulem2);
	return $set_flowbits;
}

function suricata_find_flowbit_required_rules($rules, $active_rules, $unchecked_flowbits) {

	/********************************************************/
	/* This function finds all rules that must be enabled   */
	/* in order to satisfy the "checked flowbits" used by   */
	/* the currently enabled rules.  It returns the list    */
	/* of required rules in an array.                       */
	/*                                                      */
	/*           $rules     = rules_map array of all rules  */
	/*        $active_rules = rules map array of active     */
	/*			  rules.			*/
	/*  $unchecked_flowbits = array of flowbit strings with */
	/*                        unmatched set/isset pairings  */
	/*                                                      */
	/*              Returns = rules_map array consisting    */
	/*                        of additional rules needed    */
	/*                        to satisfy all set/isset      */
	/*                        flowbit pairings              */
	/********************************************************/

	$required_flowbits_rules = array();
	foreach ($rules as $k1 => $rule) {
		if (!is_array($rule)) {
			continue;
		}
		foreach ($rule as $k2 => $rule2) {
			if (empty($rule2['flowbits'])) {
				continue;
			}
			if (!is_array($rule2['flowbits'])) {
				continue;
			}
			foreach ($rule2['flowbits'] as $flowbit) {
				if (empty($flowbit)) {
					continue;
				}
				$action = substr($flowbit, 0, strpos($flowbit, ","));
				if (!strcasecmp(substr($action, 0, 3), "set")) {
					$tmp = substr($flowbit, strpos($flowbit, ",") +1 );
					if (!empty($tmp) && isset($unchecked_flowbits[$tmp])) {
						array_init_path($required_flowbits_rules, "{$k1}/{$k2}");
						$required_flowbits_rules[$k1][$k2]['noalert'] = $rule2['noalert'];
						if ($rule2['disabled'] == 0) {
							// If not disabled, just return the rule text "as is"
							$required_flowbits_rules[$k1][$k2]['rule'] = ltrim($rule2['rule']);
							$required_flowbits_rules[$k1][$k2]['disabled'] = $rule2['disabled'];
						} else {
							// Rule is disabled, so remove leading '#' to enable it and add "flowbits:noalert;"
							// tag to prevent alerts from the previously disabled rule if not already present.
							$required_flowbits_rules[$k1][$k2]['rule'] = ltrim(substr($rule2['rule'], strpos($rule2['rule'], "#") + 1));
							if ($rule2['noalert'] == 0) {
								$required_flowbits_rules[$k1][$k2]['rule'] = substr($required_flowbits_rules[$k1][$k2]['rule'], 0, strrpos($required_flowbits_rules[$k1][$k2]['rule'], ")")) . " flowbits:noalert;)";
								$required_flowbits_rules[$k1][$k2]['noalert'] = 1;
							}
							$required_flowbits_rules[$k1][$k2]['disabled'] = 0;
						}

						// If the required rule is not already part of the enabled rules,
						// then add "flowbits:noalert" tag if not present to prevent alert
						// from this rule added by flowbits resolution logic.
						if (!isset($active_rules[$k1][$k2]) && $required_flowbits_rules[$k1][$k2]['noalert'] == 0) {
								$required_flowbits_rules[$k1][$k2]['rule'] = substr($required_flowbits_rules[$k1][$k2]['rule'], 0, strrpos($required_flowbits_rules[$k1][$k2]['rule'], ")")) . " flowbits:noalert;)";
							$required_flowbits_rules[$k1][$k2]['noalert'] = 1;
						}

						// Copy over the remaining rule_map entries for this rule
						$required_flowbits_rules[$k1][$k2]['category'] = $rule2['category'];
						$required_flowbits_rules[$k1][$k2]['action'] = $rule2['action'];
						$required_flowbits_rules[$k1][$k2]['managed'] = $rule2['managed'];
						$required_flowbits_rules[$k1][$k2]['modified'] = $rule2['modified'];
						$required_flowbits_rules[$k1][$k2]['default_state'] = $rule2['default_state'];
						$required_flowbits_rules[$k1][$k2]['default_action'] = $rule2['default_action'];
						$required_flowbits_rules[$k1][$k2]['state_toggled'] = $rule2['state_toggled'];
						$required_flowbits_rules[$k1][$k2]['flowbits'] = $rule2['flowbits'];
					}
				}
			}
		}
	}
	unset($rule, $rule2);

	return $required_flowbits_rules;
}

function suricata_resolve_flowbits($rules, $active_rules) {

	/******************************************************/
	/* This function auto-resolves flowbit requirements   */
	/* by finding all checked flowbits in the currently   */
	/* enabled rules, and then making sure all the "set"  */
	/* flowbit rules for those "checked" flowbits are     */
	/* enabled.  For any that are not enabled, they are   */
	/* copied to an array, enabled, and returned.         */
	/*                                                    */
	/* $active_rules --> Rules Map array containing       */
	/*                   the current rules for the        */
	/*                   interface to resolve flowbit     */
	/*                   dependencies for.                */
	/*                                                    */
	/*        $rules --> Rules Map array containing       */
	/*                   all the available rules.         */
	/******************************************************/

	$suricatadir = SURICATADIR;

	// Check $rules array to be sure it is filled.
	if (empty($rules)) {
		logger(LOG_WARNING, localize_text("Flowbit resolution not done - no rules in %s ...", SURICATA_RULES_DIR), LOG_PREFIX_PKG_SURICATA);
		return array();
	}

	// First, find all the "checked" and "set" flowbits.
	$checked_flowbits = suricata_get_checked_flowbits($active_rules);
	$set_flowbits = suricata_get_set_flowbits($active_rules);

	// Next find any "checked" flowbits without matching
	// "set" flowbit rules in the enabled rule set.
	$delta_flowbits = array_diff_key($checked_flowbits, $set_flowbits);

	// Cleanup and release the memory we no longer need.
	unset($checked_flowbits);
	unset($set_flowbits);

	// Now find all the needed "set flowbit" rules from
	// the master list of all rules.
	$required_rules = suricata_find_flowbit_required_rules($rules, $active_rules, $delta_flowbits);

	// Cleanup and release memory we no longer need.
	unset($delta_flowbits);

	return $required_rules;
}

function suricata_write_flowbit_rules_file($flowbit_rules, $rule_file) {

	/************************************************/
	/* This function takes an array of rules in the */
	/* rules_map format and writes them to the file */
	/* given.                                       */
	/*                                              */
	/* $flowbit_rules --> array of flowbit-required */
	/*                    rules.                    */
	/*                                              */
	/*     $rule_file --> filename to write the     */
	/*                    flowbit-required rules    */
	/*                    to.                       */
	/************************************************/

	$flowbit_rules_file = FLOWBITS_FILENAME;

	// See if we were passed a directory or full
	// filename to write the rules to, and adjust
	// the destination argument accordingly.
	if (is_dir($rule_file)) {
		$rule_file = rtrim($rule_file, '/')."/{$flowbit_rules_file}";
	}

	if (empty($flowbit_rules)) {
		@file_put_contents($rule_file, "");
		return;
	}

	$fp = fopen($rule_file, "w");
	if ($fp) {
		@fwrite($fp, "# These rules set flowbits checked by your other enabled rules.  If the\n");
		@fwrite($fp, "# dependent flowbits are not set, then some of your chosen rules may\n");
		@fwrite($fp, "# not fire.  Enabling all rules that set these dependent flowbits ensures\n");
		@fwrite($fp, "# your chosen rules fire as intended.\n#\n"); 
		@fwrite($fp, "# If you wish to prevent alerts from any of these rules, add the GID:SID\n");
		@fwrite($fp, "# of the rule to the Suppression List for the interface.\n");
		foreach ($flowbit_rules as $k1 => $rule) {
			foreach ($rule as $k2 => $rule2) {
				@fwrite($fp, "\n# Category: {$rule2['category']}");
				@fwrite($fp, "   GID:{$k1}  SID:{$k2}\n");
				@fwrite($fp, $rule2['rule']);
			}
		}
		fclose($fp);
	}
}

function suricata_load_vrt_policy($policy, $mode='alert', $all_rules=null) {

	/************************************************/
	/* This function returns an array of all rules  */
	/* marked with the passed in $policy metadata.  */
	/*                                              */
	/*    $policy --> desired VRT security policy   */
	/*                  1. connectivity             */
	/*                  2. balanced                 */
	/*                  3. security                 */
	/*                                              */
	/*      $mode --> determines rule action        */
	/*                  1. alert = all rule alert   */
	/*                  2. policy = rule action     */
	/*                              set according   */
	/*                              policy spec.    */
	/*                                              */
	/* $all_rules --> optional Rules Map array of   */
	/*                rules to scan for policy.     */
	/*                If not provided, then an      */
	/*                array will be created.        */
	/************************************************/

	$suricatadir = SURICATADIR;
	$vrt_policy_rules = array();

	// Load a map of all the VRT rules if we were
	// not passed a pre-loaded one to use.
	if (is_null($all_rules)) {
		/* Since only Snort VRT rules have IPS Policy metadata, */
		/* limit our search to just those files.                */
		$suricata_file_pattern = VRT_FILE_PREFIX . "*.rules";
		$suricata_vrt_files = glob(SURICATA_RULES_DIR . "{$suricata_file_pattern}");
		$all_rules = suricata_load_rules_map($suricata_vrt_files);
	}

	// Now walk the rules list and find all those that are
	// defined as active for the chosen security policy.
	foreach ($all_rules as $k1 => $arulem) {
		foreach ($arulem as $k2 => $arulem2) {
			if (strripos($arulem2['rule'], "policy {$policy}-ips") !== false) {
				if (!preg_match('/flowbits\s*:\s*noalert/i', $arulem2['rule'])) {
					array_init_path($vrt_policy_rules, "{$k1}/{$k2}");
					$vrt_policy_rules[$k1][$k2] = $arulem2;

					// Enable the policy rule if disabled
					if ($arulem2['disabled'] == 1) {
						$vrt_policy_rules[$k1][$k2]['rule'] = ltrim(substr($arulem2['rule'], strpos($arulem2['rule'], "#") + 1));
						$vrt_policy_rules[$k1][$k2]['disabled'] = 0;
					}

					// If policy mode is enabled, grab the suggested action
					// for this policy and set it as the rule action.
					if ($mode == 'policy') {
						$matches = array();
						if (preg_match('/' . "policy {$policy}-ips" . '([^,|^;]*)/', $arulem2['rule'], $matches)) {
							if ($tmp = preg_replace('/^\s*alert\s/', trim($matches[1]) . ' ', $vrt_policy_rules[$k1][$k2]['rule'], 1)) {
								$vrt_policy_rules[$k1][$k2]['rule'] = $tmp;
								$vrt_policy_rules[$k1][$k2]['action'] = trim($matches[1]);
								$vrt_policy_rules[$k1][$k2]['modified'] = 1;
							}
						}
					}
				}
			}
		}
	}

	// Release memory we no longer need.
	unset($arulem, $arulem2);

	// Return all the rules that match the policy.
	return $vrt_policy_rules;
}

function suricata_parse_sidconf_file($sidconf_file,$split_lines=TRUE) {

	/**********************************************/
	/* This function loads and processes the list */
	/* specified by '$sidconf_file'.  The list is */
	/* assumed to contain valid instructions for  */
	/* matching rule SIDs as supported by the     */
	/* Oinkmaster and PulledPork utilities.       */
	/*                                            */
	/*  $sidconf_file ==> name of SID Mgmt        */
	/*                    list to process         */
	/*                                            */
	/*  $split_lines ==> determines whether lines */
	/*                   should be split at       */
	/*                   commas into multiple     */
	/*                   sid modification	      */
	/*                                            */
	/*        Returns ==> an array containing     */
	/*                    SID modifier tokens     */
	/**********************************************/

	$buf = "";
	$sid_mods = array();
	$list = array();

	// Find the list we need
	foreach(config_get_path('installedpackages/suricata/sid_mgmt_lists/item', []) as $item) {
		if ($item['name'] == $sidconf_file) {
			$list = $item;
			break;
		}
	}

	// If for some unexpected reason we fail to find the list, bail and
	// return an emtpy array.
	if (count($list) < 1) {
		return $sid_mods;
	}

	// Decode the list contents into a PHP temp buffer
	// we can read like a normal file.
	$fd = fopen("php://temp", "r+");
	if ($fd == FALSE) {
		logger(LOG_ERR, localize_text("Failed to open SID MGMT list '5s' for processing.", $sidconf_file), LOG_PREFIX_PKG_SURICATA);
		return $sid_mods;
	}
	fwrite($fd, base64_decode($list['content']));
	rewind($fd);

	// Read and parse the conf list line-by-line
	while (($buf = fgets($fd)) !== FALSE) {
		$line = array();

		// Skip any lines that may be just spaces.
		if (trim($buf, " \r\n") == "") {
			continue;
		}

		// Skip line with leading "#" since it's a comment
		if (preg_match('/^\s*#/', $buf)) {
			continue;
		}

		// Trim off any trailing comment
		$line = explode("#", $buf);

		// Trim leading and trailing spaces plus newline and any carriage returns
		$buf = trim($line[0], ' \r\n');

		if ($split_lines) {
			// If split mode split the SID mod arguments at the commas, if more than one
			// per line, and add to our $sid_mods array.
			$line = explode(",", $buf);
			foreach ($line as $ent) {
				$sid_mods[] = trim($ent);
			}
		}
		else{
			//Otherwise add 1 line as 1 modification to the $sid_mods array
			$sid_mods[] = $buf;
		}
	}

	// Close the file, release unneeded memory and return
	// the array of SID mod tokens parsed from the file.
	fclose($fd);
	unset($list, $line, $buf);
	return $sid_mods;
}

function suricata_sid_mgmt_list_exist($sid_mgmt_list) {

	/****************************************************/
	/* This function tests whether or not the passed    */
	/* automatic SID MGMT list exists in the config     */
	/* file for the firewall.                           */
	/*                                                  */
	/*   $sid_mgmt_list ==> name of SID Mgmt List       */
	/*                                                  */
	/*          Returns ==> TRUE if list exists, or     */
	/*                      FALSE if not found          */
	/*                                                  */
	/****************************************************/

	foreach(config_get_path('installedpackages/suricata/sid_mgmt_lists/item', []) as $list) {
		if ($list['name'] == $sid_mgmt_list) {
			return TRUE;
		}
	}
	return FALSE;
}

function suricata_sid_mgmt_auto_categories($suricatacfg, $log_results = FALSE) {

	/****************************************************/
	/* This function parses any auto-SID conf lists     */
	/* configured for the interface and returns an      */
	/* array of rule categories adjusted from the       */
	/* ['enabled_rulesets'] element in the config for   */
	/* the interface in accordance with the contents    */
	/* of the SID Mgmt conf lists.                      */
	/*                                                  */
	/* The returned array shows which files should be   */
	/* removed and which should be added to the list    */
	/* used when building the enforcing ruleset.        */
	/*                                                  */
	/*  $suricatacfg ==> pointer to interface           */
	/*                   configuration info             */
	/*  $log_results ==> [optional] log results to      */
	/*                   'sid_changes.log' in the       */
	/*                   interface directory in         */
	/*                   /var/log/suricata when TRUE    */
	/*                                                  */
	/*       Returns ==> array of category file names   */
	/*                   for the interface.  The keys   */
	/*                   are category file names and    */
	/*                   the corresponding values show  */
	/*                   if the file should be added    */
	/*                   or removed from the enabled    */
	/*                   rulesets list.                 */
	/*                                                  */
	/*                    Example -                     */
	/*                      $changes[file] = 'enabled'  */
	/*                                                  */
	/****************************************************/

	$sid_mods = array();
	$enables = array();
	$disables = array();
	$drops = array();

	// Check if auto-mgmt of SIDs is enabled, exit if not
	if (config_get_path('installedpackages/suricata/config/0/auto_manage_sids', '')!= 'on') {
		return array();
	}
	if (empty($suricatacfg['disable_sid_file']) && empty($suricatacfg['enable_sid_file']) && empty($suricatacfg['drop_sid_file'])) {
		return array();
	}

	// Configure the interface's logging subdirectory if log results is enabled
	if ($log_results == TRUE) {
		$log_file = SURICATALOGDIR . "suricata_" . get_real_interface($suricatacfg['interface']) . "{$suricatacfg['uuid']}/sid_changes.log";
	} else {
		$log_file = NULL;
	}

	// Get the list of currently enabled categories for the interface
	if (!empty($suricatacfg['rulesets'])) {
		$enabled_cats = explode("||", $suricatacfg['rulesets']);
	}

	if ($log_results == TRUE) {
		error_log(gettext("********************************************************\n"), 3, $log_file);
		error_log(gettext("Starting auto RULE CATEGORY management for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']) ."\n"), 3, $log_file);
		error_log(gettext("Start Time: " . date("Y-m-d H:i:s") . "\n"), 3, $log_file);
	}

	switch ($suricatacfg['sid_state_order']) {
		case "disable_enable":
			if (!empty($suricatacfg['disable_sid_file'])) {
				if ($log_results == TRUE) {
					error_log(gettext("Processing disable_sid list: {$suricatacfg['disable_sid_file']}\n"), 3, $log_file);
				}

				// Attempt to open the 'disable_sid_file' for the interface
				// Verify the assigned SID Mgmt List still exists in the firewall configuration
				if (!suricata_sid_mgmt_list_exist($suricatacfg['disable_sid_file'])) {
					logger(LOG_ERR, localize_text("unable to open disable_sid list \"%s\" specified for %s", $suricatacfg['disable_sid_file'], convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
					if ($log_results == TRUE) {
						error_log(gettext("Unable to find disable_sid list \"{$suricatacfg['disable_sid_file']}\".\n"), 3, $log_file);
					}
				} else {
					$sid_mods = suricata_parse_sidconf_file($suricatacfg['disable_sid_file']);
				}

				if (!empty($sid_mods)) {
					$disables = suricata_get_auto_category_mods($enabled_cats, $sid_mods, "disable", $log_results, $log_file);
				} elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in list \"{$suricatacfg['disable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			if (!empty($suricatacfg['enable_sid_file'])) {
				if ($log_results == TRUE) {
					error_log(gettext("Processing enable_sid list: {$suricatacfg['enable_sid_file']}\n"), 3, $log_file);
				}

				// Attempt to open the 'enable_sid_file' for the interface
				if (!suricata_sid_mgmt_list_exist($suricatacfg['enable_sid_file'])) {
					logger(LOG_ERR, localize_text("unable to open enable_sid list \"%s\" specified for %s", $suricatacfg['enable_sid_file'], convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
					if ($log_results == TRUE) {
						error_log(gettext("Unable to find enable_sid list \"{$suricatacfg['enable_sid_file']}\".\n"), 3, $log_file);
					}
				} else {
					$sid_mods = suricata_parse_sidconf_file($suricatacfg['enable_sid_file']);
				}

				if (!empty($sid_mods)) {
					$enables = suricata_get_auto_category_mods($enabled_cats, $sid_mods, "enable", $log_results, $log_file);
				} elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in list \"{$suricatacfg['enable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			break;

		case "enable_disable":
			if (!empty($suricatacfg['enable_sid_file'])) {
				if ($log_results == TRUE) {
					error_log(gettext("Processing enable_sid list: {$suricatacfg['enable_sid_file']}\n"), 3, $log_file);
				}

				// Attempt to open the 'enable_sid_file' for the interface
				if (!suricata_sid_mgmt_list_exist($suricatacfg['enable_sid_file'])) {
					logger(LOG_ERR, localize_text("unable to find enable_sid list \"%s\" specified for %s", $suricatacfg['enable_sid_file'], convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
					if ($log_results == TRUE) {
						error_log(gettext("Unable to open enable_sid list \"{$suricatacfg['enable_sid_file']}\".\n"), 3, $log_file);
					}
				} else {
					$sid_mods = suricata_parse_sidconf_file($suricatacfg['enable_sid_file']);
				}

				if (!empty($sid_mods)) {
					$enables = suricata_get_auto_category_mods($enabled_cats, $sid_mods, "enable", $log_results, $log_file);
				} elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in list \"{$suricatacfg['enable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			if (!empty($suricatacfg['disable_sid_file'])) {
				if ($log_results == TRUE) {
					error_log(gettext("Processing disable_sid list: {$suricatacfg['disable_sid_file']}\n"), 3, $log_file);
				}

				// Attempt to open the 'disable_sid_file' for the interface
				if (!suricata_sid_mgmt_list_exist($suricatacfg['disable_sid_file'])) {
					logger(LOG_ERR, localize_text("unable to open disable_sid list \"%s\" specified for %s", $suricatacfg['disable_sid_file'], convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
					if ($log_results == TRUE) {
						error_log(gettext("Unable to find disable_sid list \"{$suricatacfg['disable_sid_file']}\".\n"), 3, $log_file);
					}
				}
				else {
					$sid_mods = suricata_parse_sidconf_file($suricatacfg['disable_sid_file']);
				}

				if (!empty($sid_mods)) {
					$disables = suricata_get_auto_category_mods($enabled_cats, $sid_mods, "disable", $log_results, $log_file);
				} elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in list \"{$suricatacfg['disable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			break;

		default:
			logger(LOG_ERR, localize_text("Unrecognized '%s' value.  Skipping auto CATEGORY mgmt step for ", 'sid_state_order', convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
			if ($log_results == TRUE) {
				error_log(gettext("ERROR: unrecognized 'sid_state_order' value.  Skipping auto CATEGORY mgmt step for ") . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']). ".\n", 3, $log_file);
			}
	}

	if ($log_results == TRUE) {
		error_log(gettext("End Time: " . date("Y-m-d H:i:s") . "\n"), 3, $log_file);
		error_log(gettext("********************************************************\n\n"), 3, $log_file);
	}

	// Return the required rule category modifications as an array;
	return array_merge($enables, $disables);
}

function suricata_get_auto_category_mods($categories, $sid_mods, $action, $log_results = FALSE, $log_file = NULL) {

	/****************************************************/
	/* This function parses the provided SID mod tokens */
	/* in $sid_mods and returns an array of category    */
	/* files that must be added ('enabled') or removed  */
	/* ('disabled') from the provided $categories list  */
	/* of enabled rule categories as determined by the  */
	/* content of the SID Mgmt tokens in $sid_mods.     */
	/*                                                  */
	/* The returned array shows which files should be   */
	/* removed and which should be added to the list    */
	/* used when building the enforcing ruleset.        */
	/*                                                  */
	/*   $categories ==> array of currently enabled     */
	/*                   ruleset categories             */
	/*     $sid_mods ==> array of SID modification      */
	/*                   tokens                         */
	/*       $action ==> modification action for        */
	/*                   matching category targets:     */
	/*                   'enable' or 'disable'          */
	/*  $log_results ==> [optional] 'yes' to log        */
	/*                   results to $log_file           */
	/*     $log_file ==> full path and filename of log  */
	/*                   file to write to               */
	/*                                                  */
	/*       Returns ==> array of category file names   */
	/*                   for the interface.  The keys   */
	/*                   are category file names and    */
	/*                   the corresponding values show  */
	/*                   if the file should be added    */
	/*                   or removed from the enabled    */
	/*                   rulesets list.                 */
	/*                                                  */
	/*                    Example -                     */
	/*                      $changes[file] = 'enabled'  */
	/*                                                  */
	/****************************************************/

	$all_cats = array();
	$changes = array();
	$counter = 0;
	$matchcount = 0;

	// Get a list of all possible categories by loading all rules files
	$files = glob(SURICATA_RULES_DIR . "*.rules");
	foreach ($files as $file) {
		$all_cats[] = basename($file);
	}

	// Walk the SID mod tokens and decode looking for rule
	// category enable/disable changes.
	foreach ($sid_mods as $tok) {
		$matches = array();
		// Test the SID token for a GID:SID range and skip if true
		if (preg_match('/^(\d+):(\d+)-\1:(\d+)/', $tok)) {
			continue;
		// Test the token for a single GID:SID and skip if true
		} elseif (preg_match('/^(\d+):(\d+)$/', $tok)) {
			continue;
		// Test the token for the PCRE: keyword and skip if true
		} elseif (preg_match('/(^pcre\:)(.+)/i', $tok)) {
			continue;
		// Test the token for the MS reference keyword and skip if true
		} elseif (preg_match('/^MS\d+-.+/i', $tok)) {
			continue;
		// Test the token for other keywords delimited with a colon and skip if true
		} elseif (preg_match('/^[a-xA-X]+\:.+/', $tok)) {
			continue;
		// Test the SID token for a rule category name.  Anything that
		// failed to match above is considered a potential category name.
		} elseif (preg_match('/[a-xA-X]+(-|\w).*/', $tok, $matches)) {
			$counter++;
			$regex = "/" . preg_quote(trim($matches[0]), '/') . "/i";
			// Search through the $all_cats array for any matches to the regex
			$matches = preg_grep($regex, $all_cats);

			// See if any matches are in the $categories array
			foreach ($matches as $cat) {
				switch ($action) {
					case 'enable':
						if (!isset($changes[$cat])) {
							$changes[$cat] = 'enabled';
							if ($log_results == TRUE && !empty($log_file)) {
								error_log(gettext("    Enabled rule category: {$cat}\n"), 3, $log_file);
							}
							$matchcount++;
						}
						break;

					case 'disable':
						if (!isset($changes[$cat])) {
							$changes[$cat] = 'disabled';
							if ($log_results == TRUE && !empty($log_file)) {
								error_log(gettext("    Disabled rule category: {$cat}\n"), 3, $log_file);
							}
							$matchcount++;
						}
						break;

					default:
						break;
				}
			}
		}
		else {
			if ($log_results == TRUE && !empty($log_file)) {
				error_log(gettext("WARNING: unrecognized token '{$tok}' encountered while processing an automatic SID MGMT file.\n"), 3, $log_file);
			}
		}
	}

	if ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("    Parsed {$counter} potential Rule Categories to match from the list of tokens.\n"), 3, $log_file);
		error_log(gettext("    " . ucfirst($action) . "d {$matchcount} matching Rule Categories.\n"), 3, $log_file);
	}

	// Release memory no longer needed
	unset($all_cats, $matches);

	// Return array of rule category file changes
	return $changes;
}

function suricata_modify_sid_state(&$rule_map, $sid_mods, $action, $log_results = FALSE, $log_file = NULL) {

	/**********************************************/
	/* This function walks the provided array of  */
	/* SID modification tokens and locates the    */
	/* target SID or SIDs in the $rule_map array. */
	/* It then performs the change specified by   */
	/* $action on the target SID or SIDs.         */
	/*                                            */
	/*    $rule_map ==> reference to array of     */
	/*                  current rules             */
	/*    $sid_mods ==> array of SID modification */
	/*                  tokens                    */
	/*      $action ==> modification action for   */
	/*                  matching SID targets:     */
	/*                  'enable', 'disable',      */
	/*		    'drop' or 'reject'.       */
	/* $log_results ==> [optional] 'yes' to log   */
	/*                   results to $log_file     */
	/*    $log_file ==> full path and filename    */
	/*                  of log file to write to   */
	/*                                            */
	/*    On Return ==> $rule_map array modified  */
	/*                  by changing state for     */
	/*                  matching SIDs.            */
	/*                                            */
	/*                  Returns a two-dimension   */
	/*                  array of matching GID:SID */
	/*                  pairs.                    */
	/**********************************************/

	$sids = array();
	$log_action = '';
	$log_what = '';

	// If no rules in $rule_map or mods in $sid_mods,
	// then nothing to do.
	if (empty($rule_map) || empty($sid_mods)) {
		return $sids;
	}

	// Validate the action keyword as we only accept
	// 'enable', 'disable' and 'drop' as valid.
	switch ($action) {

		case "enable":
			$log_what = 'state';
			$log_action = 'enabled';
			break;

		case "disable":
			$log_what = 'state';
			$log_action = 'disabled';
			break;

		case "drop":
			$log_what = 'action';
			$log_action = 'drop';
			break;

		case "reject":
			$log_what = 'action';
			$log_action = 'reject';
			break;

		default:
			logger(LOG_WARNING, localize_text("unknown action '%s' supplied to %s function...no SIDs modified.", $action, 'suricata_modify_sid_state()'), LOG_PREFIX_PKG_SURICATA);
			return $sids;
	}

	// Walk the SID mod tokens and decode each one.
	// Place all matching GID:SID combos along with
	// the requested $action into our $sids[] array.
	foreach ($sid_mods as $tok) {
		$matches = array();
		// Test the SID token for a GID:SID range
		if (preg_match('/^(\d+):(\d+)-\1:(\d+)/', $tok, $matches)) {
			// It was a range, so find all the intervening SIDs
			$gid = trim($matches[1]);
			$lsid = trim($matches[2]);
			$usid = trim($matches[3]);
			$sids[$gid][$lsid] = $action;
			while ($lsid < $usid) {
				$lsid++;
				$sids[$gid][$lsid] = $action;
			}
		}
		// Test the SID token for a single GID:SID
		elseif (preg_match('/^(\d+):(\d+)$/', $tok, $matches)) {
			// It's a single GID:SID, so grab it
			$sids[$matches[1]][$matches[2]] = $action;
		}
		// Test the SID token for the PCRE: keyword
		elseif (preg_match('/(^pcre\:)(.+)/i', $tok, $matches)) {
			$regex = '/' . $matches[2] . '/i';

			// Now search through the $rule_map in the 'rule'
			// element for any matches to the regex and get
			// the GID:SID.
			foreach ($rule_map as $k1 => $rulem) {
				foreach ($rulem as $k2 => $v) {
					if (preg_match($regex, $v['rule'])) {
						$sids[$k1][$k2] = $action;
					}
				}
			}
		}
		// Test the SID token for the MS reference keyword
		elseif (preg_match('/^MS\d+-.+/i', $tok, $matches)) {
			$regex = "/" . $matches[0] . "/i";

			// Now search through the $rule_map in the 'rule'
			// element for any matches to the regex and get
			// the GID:SID.
			foreach ($rule_map as $k1 => $rulem) {
				foreach ($rulem as $k2 => $v) {
					if (preg_match($regex, $v['rule'])) {
						$sids[$k1][$k2] = $action;
					}
				}
			}
		}
		// Test the SID token for other keywords delimited with a colon
		elseif (preg_match('/^[a-xA-X]+\:.+/', $tok, $matches)) {
			$regex = "/" . str_replace(':', ",", preg_quote($matches[0], '/')) . "/i";

			// Now search through the $rule_map in the 'rule'
			// element for any matches to the regex and get
			// the GID:SID.
			foreach ($rule_map as $k1 => $rulem) {
				foreach ($rulem as $k2 => $v) {
					if (preg_match($regex, $v['rule'])) {
						$sids[$k1][$k2] = $action;
					}
				}
			}
		}
		// Test the SID token for a rule category name.  Anything that
		// failed to match above is considered a potential category name.
		elseif (preg_match('/[a-xA-X]+(-|\w).*/', $tok, $matches)) {
			$regex = "/" . preg_quote(trim($matches[0]), '/') . "/i";
			// Now search through the $rule_map in the 'category'
			// element for any matches to the regex and get
			// the GID:SID.
			foreach ($rule_map as $k1 => $rulem) {
				foreach ($rulem as $k2 => $v) {
					if (preg_match($regex, $v['category'] . ".rules")) {
						$sids[$k1][$k2] = $action;
					}
				}
			}
		}
		else {
			if ($log_results == TRUE && !empty($log_file)) {
				error_log(gettext("WARNING: unrecognized token '{$tok}' encountered while processing an automatic SID MGMT file.\n"), 3, $log_file);
			}
		}
	}

	// Now walk our $sids[] array populated above and change
	// the state or action of all the matching GID:SID pairs 
	// in the $rule_map array passed to us.
	$modcount = $changecount = 0;
	$counter = count($sids, COUNT_RECURSIVE) - count($sids);

	if ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("    Parsed {$counter} potential SIDs to match from the provided list of tokens.\n"), 3, $log_file);
	}

	foreach (array_keys($sids) as $k1) {
		foreach (array_keys($sids[$k1]) as $k2) {
			if (isset($rule_map[$k1][$k2])) {
				if ($action == 'enable' && $rule_map[$k1][$k2]['disabled'] == 1) {
					$rule_map[$k1][$k2]['rule'] = ltrim($rule_map[$k1][$k2]['rule'], " \t#");
					$rule_map[$k1][$k2]['disabled'] = 0;
					$rule_map[$k1][$k2]['managed'] = 1;
					$rule_map[$k1][$k2]['state_toggled'] = 1;
					$changecount++;
					$modcount++;
				}
				elseif ($action == 'disable' && $rule_map[$k1][$k2]['disabled'] == 0) {
					$rule_map[$k1][$k2]['rule'] = "# " . $rule_map[$k1][$k2]['rule'];
					$rule_map[$k1][$k2]['disabled'] = 1;
					$rule_map[$k1][$k2]['managed'] = 1;
					$rule_map[$k1][$k2]['state_toggled'] = 1;
					$changecount++;
					$modcount++;
				}
				elseif ($action == 'drop' && $rule_map[$k1][$k2]['action'] != 'drop' && !in_array('noalert', $rule_map[$k1][$k2]['flowbits']) && $rule_map[$k1][$k2]['noalert'] == 0) {
					if ($tmp = preg_replace('/\s*alert\s*/', 'drop ', $rule_map[$k1][$k2]['rule'], 1)) {
						$rule_map[$k1][$k2]['rule'] = $tmp;
						$rule_map[$k1][$k2]['action'] = 'drop';
						$rule_map[$k1][$k2]['managed'] = 1;
						$rule_map[$k1][$k2]['modified'] = 1;
						$changecount++;
						$modcount++;
					}
				}
				elseif ($action == 'reject' && $rule_map[$k1][$k2]['action'] != 'reject' && !in_array('noalert', $rule_map[$k1][$k2]['flowbits']) && $rule_map[$k1][$k2]['noalert'] == 0) {
					if ($tmp = preg_replace('/\s*alert\s*/', 'reject ', $rule_map[$k1][$k2]['rule'], 1)) {
						$rule_map[$k1][$k2]['rule'] = $tmp;
						$rule_map[$k1][$k2]['action'] = 'reject';
						$rule_map[$k1][$k2]['managed'] = 1;
						$rule_map[$k1][$k2]['modified'] = 1;
						$changecount++;
						$modcount++;
					}
				}
			}
		}
	}

	if ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("    Found {$modcount} matching SIDs in the active rules.\n"), 3, $log_file);
		error_log(gettext("    Changed {$log_what} for {$changecount} SIDs to '{$log_action}'.\n"), 3, $log_file);
	}

	// Return the array of matching SIDs
	return $sids;
}

function suricata_modify_sid_content(&$rule_map, $sid_mods, $log_results = FALSE, $log_file = NULL) {

	/************************************************/
	/* This function walks the provided array of    */
	/* SID modification tokens and locates the      */
	/* target SID or SIDs in the $rule_map array.   */
	/* It then modifies the content of the target   */
	/* SID or SIDs. Modifications are only valid    */
	/* for normal GID=1 text rules.                 */
	/*                                              */
	/*     $rule_map ==> reference to array of      */
	/*                   current rules              */
	/*     $sid_mods ==> array of SID modification  */
	/*                   tokens                     */
	/*  $log_results ==> [optional] 'yes' to log    */
	/*                   results to $log_file       */
	/*     $log_file ==> full path and filename     */
	/*                   of log file to write to    */
	/*                                              */
	/*     On Return ==> $rule_map array modified   */
	/*                   by changing content for    */
	/*                   matching SIDs.             */
	/*                                              */
	/*                   Returns a two-dimension    */
	/*                   array of matching          */
	/*                   GID:SID pairs.             */
	/************************************************/

	$sids = array();
	$tokencounter = $modcount = $modifiedcount = 0;

	// If no rules in $rule_map or mods in $sid_mods,
	// then nothing to do.
	if (empty($rule_map) || empty($sid_mods)) {
		return $sids;
	}

	// Walk the SID mod tokens and decode each one
	foreach ($sid_mods as $tok) {
		$matches = array();
		if (preg_match('/((?<sid_category_list> (?<sid_category_plus_comma>[\da-z-_*]+,)* (?<last_sid_cageory>[\da-z-_*]+)) \s+)? "(?<from>.*)" \s+ "(?<to>.*)"/x', $tok, $matches)) {
			// If a valid config token is found. A valid token is:
			// An optional group of a comma separated list(sid_category_list) of SIDs(numbers) or categories(lowercase chars,numbers,- or _) plus whitespaces followed by
			// a search term(from) framed by double quotes followed
			// whitespaces followed by
			// a replacement(to) framed by double quotes

			$tokencounter++;
			$from = '/' . preg_quote($matches['from'], '/') . '/';
			$to = $matches['to'];
			$count = 0;

			if ($matches['sid_category_list'] == "") {
				// If the sidlist is empty it's the same as a wildcard, therefore create a list with just one wildcard item
				$sidlist = array("*");
			}
			else {
				// Otherwise split the list
				$sidlist = explode(",", $matches['sid_category_list']);
			}


			// Walk over all sids
			foreach ($sidlist as $sid) {
				if (isset($rule_map[1][$sid])) {
					// If sid is present change modify it
					$modcount++;
					$rule_map[1][$sid]['rule'] = preg_replace($from, $to, $rule_map[1][$sid]['rule'], -1, $count);
					if ($count > 0) {
						$rule_map[1][$sid]['managed'] = 1;
						$rule_map[1][$sid]['modified'] = 1;
						$sids[1][$sid] = 'modify';
						$modifiedcount++;
					}
				}
				else {
					// Otherwise it's a wildcard or category modification
					if (is_array($rule_map)) {
						// If rule_map is an array walk over it to find all rules which need be be modified
						foreach ($rule_map[1] as $k2 => $v) {
							if ($sid == "*" || $v['category'] == $sid) {
								// If a wildcard changed is handled or the category is matching the rule needs to get modified
								$modcount++;
								$rule_map[1][$k2]['rule'] = preg_replace($from, $to, $v['rule'], -1, $count);
								if ($count > 0) {
									$rule_map[1][$k2]['managed'] = 1;
									$rule_map[1][$k2]['modified'] = 1;
									$sids[1][$k2] = 'modify';
									$modifiedcount++;
								}
							}
						}
					}
				}
			}
		}
		else {
			if ($log_results == TRUE && !empty($log_file))
				error_log(gettext("WARNING: unrecognized token '{$tok}' encountered while processing an automatic SID MGMT file.\n"), 3, $log_file);
		}
	}

	if ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("    Parsed {$tokencounter} potential SIDs to match from the provided list of tokens.\n"), 3, $log_file);
		error_log(gettext("    Found {$modcount} matching SIDs in the active rules.\n"), 3, $log_file);
		error_log(gettext("    Modified rule text for {$modifiedcount} SIDs.\n"), 3, $log_file);
	}

	// Return the array of matching SIDs
	return $sids;
}

function suricata_process_enablesid(&$rule_map, $suricatacfg, $log_results = FALSE, $log_file = NULL) {

	/**********************************************/
	/* This function loads and processes the list */
	/* specified by 'enable_sid_file' for the     */
	/* interface.  The list is assumed to be a    */
	/* valid enablesid.conf list containing       */
	/* instructions for enabling matching rule    */
	/* SIDs.                                      */
	/*                                            */
	/*     $rule_map ==> reference to array of    */
	/*                   current rules            */
	/*  $suricatacfg ==> interface config params  */
	/*  $log_results ==> [optional] 'yes' to log  */
	/*                   results to $log_file     */
	/*     $log_file ==> full path and filename   */
	/*                   of log file to write to  */
	/*                                            */
	/*     On Return ==> suitably modified        */
	/*                   $rule_map array          */
	/**********************************************/

	$suricatalogdir = SURICATALOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map)) {
		return;
	}

	// Verify the 'enable_sid' list for the interface exists
	if (!suricata_sid_mgmt_list_exist($suricatacfg['enable_sid_file'])) {
		logger(LOG_ERR, localize_text("unable to find enable_sid list \"%s\" specified for %s", $suricatacfg['enable_sid_file'], convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
		return;
	}
	else
		$sid_mods = suricata_parse_sidconf_file($suricatacfg['enable_sid_file']);

	if (!empty($sid_mods))
		suricata_modify_sid_state($rule_map, $sid_mods, "enable", $log_results, $log_file);
	elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in list \"{$suricatacfg['enable_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function suricata_process_disablesid(&$rule_map, $suricatacfg, $log_results = FALSE, $log_file = NULL) {

	/**********************************************/
	/* This function loads and processes the list */
	/* specified by 'disable_sid_file' for the    */
	/* interface.  The list is assumed to be a    */
	/* valid disablesid.conf list containing      */
	/* instructions for disabling matching rule   */
	/* SIDs.                                      */
	/*                                            */
	/*     $rule_map ==> reference to array of    */
	/*                   current rules            */
	/*  $suricatacfg ==> interface config params  */
	/*  $log_results ==> [optional] 'yes' to log  */
	/*                   results to $log_file     */
	/*     $log_file ==> full path and filename   */
	/*                   of log file to write to  */
	/*                                            */
	/*     On Return ==> suitably modified        */
	/*                   $rule_map array          */
	/**********************************************/

	$suricatalogdir = SURICATALOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map)) {
		return;
	}

	// Verify the 'disable_sid' list for the interface exists
	if (!suricata_sid_mgmt_list_exist($suricatacfg['disable_sid_file'])) {
		logger(LOG_ERR, localize_text("unable to find disable_sid list \"%s\" specified for %s", $suricatacfg['disable_sid_file'], convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
		return;
	} else {
		$sid_mods = suricata_parse_sidconf_file($suricatacfg['disable_sid_file']);
	}

	if (!empty($sid_mods)) {
		suricata_modify_sid_state($rule_map, $sid_mods, "disable", $log_results, $log_file);
	} elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in list \"{$suricatacfg['disable_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function suricata_process_modifysid(&$rule_map, $suricatacfg, $log_results = FALSE, $log_file = NULL) {

	/**********************************************/
	/* This function loads and processes the list */
	/* specified by 'modify_sid_file' for the     */
	/* interface.  The list is assumed to be a    */
	/* valid modifysid.conf list containing       */
	/* instructions for modifying matching rule   */
	/* SIDs.                                      */
	/*                                            */
	/*     $rule_map ==> reference to array of    */
	/*                   current rules            */
	/*  $suricatacfg ==> interface config params  */
	/*  $log_results ==> [optional] 'yes' to log  */
	/*                   results to $log_file     */
	/*     $log_file ==> full path and filename   */
	/*                   of log file to write to  */
	/*                                            */
	/*     On Return ==> suitably modified        */
	/*                   $rule_map array          */
	/**********************************************/

	$suricatalogdir = SURICATALOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map)) {
		return;
	}

	// Verify the 'modify_sid' list for the interface exists
	if (!suricata_sid_mgmt_list_exist($suricatacfg['modify_sid_file'])) {
		logger(LOG_ERR, localize_text("unable to find modify_sid list \"%s\" specified for %s", $suricatacfg['modify_sid_file'], convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
		return;
	} else {
		$sid_mods = suricata_parse_sidconf_file($suricatacfg['modify_sid_file'],FALSE);
	}

	if (!empty($sid_mods)) {
		suricata_modify_sid_content($rule_map, $sid_mods, $log_results, $log_file);
	} elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in list \"{$suricatacfg['modify_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function suricata_process_dropsid(&$rule_map, $suricatacfg, $log_results = FALSE, $log_file = NULL) {

	/**********************************************/
	/* This function loads and processes the list */
	/* specified by 'drop_sid_file' for the       */
	/* interface.  The list is assumed to be a    */
	/* valid dropsid.conf list containing         */
	/* instructions for modifying the action for  */
	/* matching rule SIDs.                        */
	/*                                            */
	/*     $rule_map ==> reference to array of    */
	/*                   current rules            */
	/*  $suricatacfg ==> interface config params  */
	/*  $log_results ==> [optional] 'yes' to log  */
	/*                   results to $log_file     */
	/*     $log_file ==> full path and filename   */
	/*                   of log file to write to  */
	/*                                            */
	/*     On Return ==> suitably modified        */
	/*                   $rule_map array          */
	/**********************************************/

	$suricatalogdir = SURICATALOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map)) {
		return;
	}

	// Verify the 'drop_sid' list for the interface exists
	if (!suricata_sid_mgmt_list_exist($suricatacfg['drop_sid_file'])) {
		logger(LOG_ERR, localize_text("unable to find drop_sid list \"%s\" specified for %s", $suricatacfg['drop_sid_file'], convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
		return;
	} else {
		$sid_mods = suricata_parse_sidconf_file($suricatacfg['drop_sid_file']);
	}

	if (!empty($sid_mods)) {
		suricata_modify_sid_state($rule_map, $sid_mods, "drop", $log_results, $log_file);
	} elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in list \"{$suricatacfg['drop_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function suricata_process_rejectsid(&$rule_map, $suricatacfg, $log_results = FALSE, $log_file = NULL) {

	/**********************************************/
	/* This function loads and processes the list */
	/* specified by 'reject_sid_file' for the     */
	/* interface.  The list is assumed to be a    */
	/* valid rejectsid.conf list containing       */
	/* instructions for modifying the action for  */
	/* matching rule SIDs.                        */
	/*                                            */
	/*     $rule_map ==> reference to array of    */
	/*                   current rules            */
	/*  $suricatacfg ==> interface config params  */
	/*  $log_results ==> [optional] 'yes' to log  */
	/*                   results to $log_file     */
	/*     $log_file ==> full path and filename   */
	/*                   of log file to write to  */
	/*                                            */
	/*     On Return ==> suitably modified        */
	/*                   $rule_map array          */
	/**********************************************/

	$suricatalogdir = SURICATALOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map)) {
		return;
	}

	// Verify the 'reject_sid' list for the interface exists
	if (!suricata_sid_mgmt_list_exist($suricatacfg['reject_sid_file'])) {
		logger(LOG_ERR, localize_text("unable to find reject_sid list \"%s\" specified for %s", $suricatacfg['reject_sid_file'], convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
		return;
	} else {
		$sid_mods = suricata_parse_sidconf_file($suricatacfg['reject_sid_file']);
	}

	if (!empty($sid_mods)) {
		suricata_modify_sid_state($rule_map, $sid_mods, "reject", $log_results, $log_file);
	} elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in list \"{$suricatacfg['reject_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function suricata_auto_sid_mgmt(&$rule_map, $suricatacfg, $log_results = FALSE) {

	/**************************************************/
	/* This function modifies the rules in the        */
	/* passed rule_map array based on values in the   */
	/* files 'enable_sid_file', 'disable_sid_file'    */
	/* 'modify_sid_file' and 'drop_sid_file' for      */
	/* the interface.                                 */
	/*                                                */
	/* If auto-mgmt of SIDs is enabled via the        */
	/* settings on the UPDATE RULES tab, then the     */
	/* rules are processed against these settings.    */
	/*                                                */
	/*     $rule_map ==> array of current rules       */
	/*  $suricatacfg ==> interface config settings    */
	/*  $log_results ==> [optional] log results to    */
	/*                   'sid_changes.log' in the     */
	/*                   interface directory in       */
	/*                   /var/log/suricata when TRUE  */
	/*                                                */
	/*       Returns ==> TRUE if rules were changed;  */
	/*                   otherwise FALSE              */
	/**************************************************/

	$result = FALSE;

	// Configure the interface's logging subdirectory if log results is enabled
	if ($log_results == TRUE) {
		$log_file = SURICATALOGDIR . "suricata_" . get_real_interface($suricatacfg['interface']) . "{$suricatacfg['uuid']}/sid_changes.log";
	} else {
		$log_file = NULL;
	}

	// Check if auto-mgmt of SIDs is enabled and files are specified
	// for the interface.
	if (config_get_path('installedpackages/suricata/config/0/auto_manage_sids', '') == 'on' &&
	    (!empty($suricatacfg['disable_sid_file']) || !empty($suricatacfg['enable_sid_file']) || 
	    !empty($suricatacfg['modify_sid_file']) || !empty($suricatacfg['drop_sid_file']))) {
		if ($log_results == TRUE) {
			error_log(gettext("********************************************************\n"), 3, $log_file);
			error_log(gettext("Starting auto SID management for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']) ."\n"), 3, $log_file);
			error_log(gettext("Start Time: " . date("Y-m-d H:i:s") . "\n"), 3, $log_file);
		}

		switch ($suricatacfg['sid_state_order']) {
			case "disable_enable":
				if (!empty($suricatacfg['disable_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing disable_sid list: {$suricatacfg['disable_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_disablesid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['enable_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing enable_sid list: {$suricatacfg['enable_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_enablesid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['modify_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing modify_sid list: {$suricatacfg['modify_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_modifysid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['drop_sid_file']) && ($suricatacfg['blockoffenders'] == 'on' && ($suricatacfg['block_drops_only'] == 'on' || $suricatacfg['ips_mode'] == 'ips_mode_inline'))) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing drop_sid list: {$suricatacfg['drop_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_dropsid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['reject_sid_file']) && $suricatacfg['blockoffenders'] == 'on' && $suricatacfg['ips_mode'] == 'ips_mode_inline') {
					if ($log_results == TRUE) {
						error_log(gettext("Processing reject_sid list: {$suricatacfg['reject_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_rejectsid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				$result = TRUE;
				break;

			case "enable_disable":
				if (!empty($suricatacfg['enable_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing enable_sid list: {$suricatacfg['enable_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_enablesid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['disable_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing disable_sid list: {$suricatacfg['disable_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_disablesid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['modify_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing modify_sid list: {$suricatacfg['modify_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_modifysid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['drop_sid_file']) && ($suricatacfg['blockoffenders'] == 'on' && ($suricatacfg['block_drops_only'] == 'on' || $suricatacfg['ips_mode'] == 'ips_mode_inline'))) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing drop_sid list: {$suricatacfg['drop_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_dropsid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['reject_sid_file']) && $suricatacfg['blockoffenders'] == 'on' && $suricatacfg['ips_mode'] == 'ips_mode_inline') {
					if ($log_results == TRUE) {
						error_log(gettext("Processing reject_sid list: {$suricatacfg['reject_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_rejectsid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				$result = TRUE;
				break;

			default:
				logger(LOG_WARNING, localize_text("Unrecognized '%s' value.  Skipping auto SID mgmt step for %s", 'sid_state_order', convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
				if ($log_results == TRUE) {
					error_log(gettext("WARNING: unrecognized 'sid_state_order' value.  Skipping auto SID mgmt step for ") . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']). ".\n", 3, $log_file);
				}
				$result = FALSE;
		}

		if ($log_results == TRUE) {
			error_log(gettext("End Time: " . date("Y-m-d H:i:s") . "\n"), 3, $log_file);
			error_log(gettext("********************************************************\n\n"), 3, $log_file);
		}
	}
	return $result;
}

function suricata_load_sid_mods($sids) {

	/*****************************************/
	/* This function parses the string of    */
	/* SID values in $sids and returns an    */
	/* array with the SID as the key and     */
	/* value.  The SID values in $sids are   */
	/* assumed to be delimited by "||".      */
	/*                                       */
	/* $sids ==> string of SID values from   */
	/*           saved config file.          */
	/*                                       */
	/* Returns ==> a multidimensional array  */
	/*             with GID and SID as the   */
	/*             keys ($result[GID][SID])  */
	/*****************************************/

	$result = array();
	if (empty($sids)) {
		return $result;
	}
	$tmp = explode("||", $sids);
	foreach ($tmp as $v) {
		if (preg_match('/(\d+)\s*:\s*(\d+)/', $v, $match)) {
			array_init_path($result, "{$match[1]}/{$match[2]}");
			$result[$match[1]][$match[2]] = "{$match[1]}:{$match[2]}";
		}
	}
	unset($tmp);

	return $result;
}

function suricata_modify_sids(&$rule_map, $suricatacfg) {

	/***********************************************/
	/* This function modifies the rules in the     */
	/* passed rules_map array based on values in   */
	/* the enablesid/disablesid configuration      */
	/* parameters for the interface.               */
	/*                                             */
	/*  $rule_map = array of current rules         */
	/*  $suricatacfg = interface config settings   */
	/***********************************************/

	if (!isset($suricatacfg['rule_sid_on']) && 
	    !isset($suricatacfg['rule_sid_off'])) {
		return;
	}

	// Load up our enablesid and disablesid
	// arrays with lists of modified SIDs.
	$enablesid = suricata_load_sid_mods($suricatacfg['rule_sid_on']);
	$disablesid = suricata_load_sid_mods($suricatacfg['rule_sid_off']);

	/* Turn on any rules that need to be     */
	/* forced "on" with enablesid mods.      */
	if (!empty($enablesid)) {
		foreach ($rule_map as $k1 => $rulem) {
			foreach ($rulem as $k2 => $v) {
				if (isset($enablesid[$k1][$k2]) && $v['disabled'] == 1) {
					$rule_map[$k1][$k2]['rule'] = ltrim($v['rule'], " \t#");
					$rule_map[$k1][$k2]['disabled'] = 0;
				}
			}
		}
	}

	/* Turn off any rules that need to be    */
	/* forced "off" with disablesid mods.    */
	if (!empty($disablesid)) {
		foreach ($rule_map as $k1 => $rulem) {
			foreach ($rulem as $k2 => $v) {
				if (isset($disablesid[$k1][$k2]) && $v['disabled'] == 0) {
					$rule_map[$k1][$k2]['rule'] = "# " . $v['rule'];
					$rule_map[$k1][$k2]['disabled'] = 1;
				}
			}
		}
	}
	unset($enablesid, $disablesid);
}

function suricata_modify_sids_action(&$rule_map, $suricatacfg) {

	/***********************************************/
	/* This function modifies the rules in the     */
	/* passed rules_map array based on values in   */
	/* the alertsid/dropsid configuration          */
	/* parameters for the interface.               */
	/*                                             */
	/*  $rule_map = array of current rules         */
	/*  $suricatacfg = interface config settings   */
	/***********************************************/

	if (!isset($suricatacfg['rule_sid_force_alert']) && 
	    !isset($suricatacfg['rule_sid_force_drop']) && 
	    !isset($suricatacfg['rule_sid_force_reject'])) {
		return;
	}

	/* Load up our SID forced action arrays with manually changed SID actions */
	$alertsid = suricata_load_sid_mods($suricatacfg['rule_sid_force_alert']);

	/* DROP is only applicable when blocking offenders is enabled and when    */
	/* 'block drops only' is enabled, or when using Inline IPS Mode.          */
	if ($suricatacfg['blockoffenders'] == 'on' && ($suricatacfg['block_drops_only'] == 'on' || $suricatacfg['ips_mode'] == 'ips_mode_inline')) {
		$dropsid = suricata_load_sid_mods($suricatacfg['rule_sid_force_drop']);
	}
	else {
		$dropsid = array();
	}

	/* REJECT is only applicable when blocking offenders is enabled and when  */
	/* Inline IPS Mode is also enabled.                                       */
	if ($suricatacfg['blockoffenders'] == 'on' && $suricatacfg['ips_mode'] == 'ips_mode_inline') {
		$rejectsid = suricata_load_sid_mods($suricatacfg['rule_sid_force_reject']);
	}
	else {
		$rejectsid = array();
	}

	/* Change action for any rules that need to be */
	/* forced to "alert" with alertsid mods.       */
	if (!empty($alertsid)) {
		foreach ($rule_map as $k1 => $rulem) {
			foreach ($rulem as $k2 => $v) {
				if (isset($alertsid[$k1][$k2]) && $v['action'] != 'alert') {
					$matches = array();
					if (preg_match('/^\s*#*\s*(drop|pass|reject)/i', $v['rule'], $matches)) {
						$txt_regx = '/^\s*' . "{$matches[1]}" . '\s/';
						if ($tmp = preg_replace($txt_regx, 'alert ', $v['rule'], 1)) {
							$rule_map[$k1][$k2]['rule'] = $tmp;
							$rule_map[$k1][$k2]['action'] = 'alert';
						}
					}
				}
			}
		}
	}

	/* Change action for any rules that need to be */
	/* forced to "drop" with dropsid mods.         */
	if (!empty($dropsid)) {
		foreach ($rule_map as $k1 => $rulem) {
			foreach ($rulem as $k2 => $v) {
				if (isset($dropsid[$k1][$k2]) && $v['action'] != 'drop') {
					$matches = array();
					if (preg_match('/^\s*#*\s*(alert|pass|reject)/i', $v['rule'], $matches)) {
						$txt_regx = '/^\s*' . "{$matches[1]}" . '\s/';
						if ($tmp = preg_replace($txt_regx, 'drop ', $v['rule'], 1)) {
							$rule_map[$k1][$k2]['rule'] = $tmp;
							$rule_map[$k1][$k2]['action'] = 'drop';
						}
					}
				}
			}
		}
	}

	/* Change action for any rules that need to be */
	/* forced to "reject" with rejectsid mods.     */
	if (!empty($rejectsid)) {
		foreach ($rule_map as $k1 => $rulem) {
			foreach ($rulem as $k2 => $v) {
				if (isset($rejectsid[$k1][$k2]) && $v['action'] != 'reject') {
					$matches = array();
					if (preg_match('/^\s*#*\s*(alert|pass|drop)/i', $v['rule'], $matches)) {
						$txt_regx = '/^\s*' . "{$matches[1]}" . '\s/';
						if ($tmp = preg_replace($txt_regx, 'reject ', $v['rule'], 1)) {
							$rule_map[$k1][$k2]['rule'] = $tmp;
							$rule_map[$k1][$k2]['action'] = 'reject';
						}
					}
				}
			}
		}
	}

	unset($alertsid, $dropsid, $rejectsid);
}

function suricata_get_filtered_rules($rules, $filter) {

	/************************************************/
	/* This function returns a rules_map array of   */
	/* of rules filtered using the GID:SID pairs    */
	/* found in the passed filter criteria array.   */
	/*                                              */
	/*   $rules --> file, a directory or an array   */
	/*              containing rules files to scan  */
	/*                                              */
	/*  $filter --> array of GID:SID pairs to use   */
	/*              for filtering returned rules    */
	/*              map array.                      */
	/*                                              */
	/*  Returns --> rules_map array                 */
	/************************************************/

	$tmp_map = array();
	$filtered_map = array();

	// If the filter array is empty, just
	// return an empty result to save time
	// since nothing would match.
	if (empty($filter)) {
		return $filtered_map;
	}

	// Load up all the rules from the location passed
	$tmp_map = suricata_load_rules_map($rules);

	// Now walk the loaded filter list and copy
	// matching GID:SID rules from the target list
	// over to the filtered array.
	foreach ($filter as $k1 => $rulem) {
		foreach($rulem as $k2 => $v) {
			if (array_get_path($tmp_map, "{$k1}/{$k2}") !== null) {
				array_init_path($filtered_map, "{$k1}/{$k2}");
				$filtered_map[$k1][$k2]['rule'] = $tmp_map[$k1][$k2]['rule'];
				$filtered_map[$k1][$k2]['category'] = $tmp_map[$k1][$k2]['category'];
				$filtered_map[$k1][$k2]['action'] = $tmp_map[$k1][$k2]['action'];
				$filtered_map[$k1][$k2]['modified'] = $tmp_map[$k1][$k2]['modified'];
				$filtered_map[$k1][$k2]['disabled'] = $tmp_map[$k1][$k2]['disabled'];
				$filtered_map[$k1][$k2]['flowbits'] = $tmp_map[$k1][$k2]['flowbits'];
				$filtered_map[$k1][$k2]['managed'] = $tmp_map[$k1][$k2]['managed'];
				$filtered_map[$k1][$k2]['state_toggled'] = $tmp_map[$k1][$k2]['state_toggled'];
				$filtered_map[$k1][$k2]['default_state'] = $tmp_map[$k1][$k2]['default_state'];
				$filtered_map[$k1][$k2]['default_action'] = $tmp_map[$k1][$k2]['default_action'];
				$filtered_map[$k1][$k2]['noalert'] = $tmp_map[$k1][$k2]['noalert'];
			}
		}
	}

	// Clean up and return the filtered list of rules
	unset($tmp_map);
	return $filtered_map;
}

function suricata_prepare_rule_files($suricatacfg, $suricatacfgdir) {

	/***********************************************************/
	/* This function builds a new set of enforcing rules for   */
	/* Suricata and writes them to disk.                       */
	/*                                                         */
	/*    $suricatacfg --> pointer to applicable section of    */
	/*                  config.xml containing settings for     */
	/*                  the interface.                         */
	/*                                                         */
	/* $suricatacfgdir --> pointer to physical directory on    */
	/*                  disk where Suricata configuration is   */
	/*                  to be written.                         */
	/***********************************************************/

	global $rebuild_rules;

	$flowbit_rules_file = FLOWBITS_FILENAME;
	$suricata_enforcing_rules_file = SURICATA_ENFORCING_RULES_FILENAME;
	$enabled_rules = array();
	$enabled_files = array();
	$all_rules = array();
	$cat_mods = array();
	$no_rules_defined = true;

	// If there is no reason to rebuild the rules, exit to save time.
	if (!$rebuild_rules)
		return;

	// Log a message for rules rebuild in progress
	logger(LOG_NOTICE, localize_text("Updating rules configuration for: %s ...", convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);

	// Get any automatic rule category enable/disable modifications
	// if auto-SID Mgmt is enabled and conf files exist for the interface.
	$cat_mods = suricata_sid_mgmt_auto_categories($suricatacfg, TRUE);

	// Load up all the rules into a Rules Map array.
	$all_rules = suricata_load_rules_map(SURICATA_RULES_DIR);

	// Only rebuild rules if some are selected or an IPS Policy is enabled
	if (!empty($suricatacfg['rulesets']) || $suricatacfg['ips_policy_enable'] == 'on' || !empty($cat_mods)) {
		$no_rules_defined = false;

		// Create an array with the filenames of the enabled
		// rule category files if we have any.
		if (!empty($suricatacfg['rulesets']) || !empty($cat_mods)) {
			// First get all the user-enabled category files
			if (!empty($suricatacfg['rulesets'])) {
				foreach (explode("||", $suricatacfg['rulesets']) as $file){
						$category = basename($file, ".rules");
						array_init_path($enabled_files, $category);
						$enabled_files[$category] = $file;
				}
			}

			// Now adjust the list using any required changes as
			// determined by auto-SID Mgmt policy files.
			if (!empty($cat_mods)) {
				foreach ($cat_mods as $k => $action) {
					$key = basename($k, ".rules");
					switch ($action) {
						case 'enabled':
							if (!isset($enabled_files[$key])) {
								$enabled_files[$key] = $k;
							}
							break;

						case 'disabled':
							if (isset($enabled_files[$key])) {
								unset($enabled_files[$key]);
							}
							break;

						default:
							break;
					}
				}
			}

			/****************************************************/
			/* Walk the ALL_RULES map array and copy the rules  */
			/* matching our selected file categories to the     */
			/* ENABLED_RULES map array.                         */
			/****************************************************/
			foreach ($all_rules as $k1 => $rulem) {
				foreach ($rulem as $k2 => $v) {
					if (isset($enabled_files[$v['category']])) {
						array_init_path($enabled_rules, "{$k1}/{$k2}");
						$enabled_rules[$k1][$k2]['rule'] = $v['rule'];
						$enabled_rules[$k1][$k2]['category'] = $v['category'];
						$enabled_rules[$k1][$k2]['disabled'] = $v['disabled'];
						$enabled_rules[$k1][$k2]['action'] = $v['action'];
						$enabled_rules[$k1][$k2]['flowbits'] = $v['flowbits'];
						$enabled_rules[$k1][$k2]['managed'] = $v['managed'];
						$enabled_rules[$k1][$k2]['default_state'] = $v['default_state'];
						$enabled_rules[$k1][$k2]['default_action'] = $v['default_action'];
						$enabled_rules[$k1][$k2]['state_toggled'] = $v['state_toggled'];
						$enabled_rules[$k1][$k2]['noalert'] = $v['noalert'];
						$enabled_rules[$k1][$k2]['modified'] = $v['modified'];
					}
				}
			}

			// Release memory we no longer need.
			unset($enabled_files, $cat_mods, $rulem, $v);
		}

		// Check if a pre-defined Snort VRT policy is selected. If so,
		// add all the VRT policy rules to our enforcing rule set.
		if ($suricatacfg['ips_policy_enable'] == 'on' && !empty($suricatacfg['ips_policy'])) {
			if ($suricatacfg['blockoffenders'] == 'on' && ($suricatacfg['ips_mode'] == 'ips_mode_inline' || $suricatacfg['block_drops_only'] == 'on')) {
				$policy_mode = $suricatacfg['ips_policy_mode'];
			}
			else {
				$policy_mode = 'alert';
			}
			$policy_rules = suricata_load_vrt_policy($suricatacfg['ips_policy'], $policy_mode, $all_rules);
			foreach ($policy_rules as $k1 => $policy) {
				foreach ($policy as $k2 => $p) {
					array_init_path($enabled_rules, "{$k1}/{$k2}");
					$enabled_rules[$k1][$k2]['rule'] = $p['rule'];
					$enabled_rules[$k1][$k2]['category'] = $p['category'];
					$enabled_rules[$k1][$k2]['disabled'] = $p['disabled'];
					$enabled_rules[$k1][$k2]['action'] = $p['action'];
					$enabled_rules[$k1][$k2]['flowbits'] = $p['flowbits'];
					$enabled_rules[$k1][$k2]['managed'] = $p['managed'];
					$enabled_rules[$k1][$k2]['default_state'] = $p['default_state'];
					$enabled_rules[$k1][$k2]['default_action'] = $p['default_action'];
					$enabled_rules[$k1][$k2]['state_toggled'] = $p['state_toggled'];
					$enabled_rules[$k1][$k2]['noalert'] = $p['noalert'];
					$enabled_rules[$k1][$k2]['modified'] = $p['modified'];
				}
			}
			unset($policy_rules, $policy, $p);
		}

		// Process any enablesid, disablesid, alertsid or dropsid modifications for the selected rules.
		// Do the auto-SID managment first, if enabled, then do any manual SID state changes.
		suricata_auto_sid_mgmt($enabled_rules, $suricatacfg, TRUE);
		suricata_modify_sids($enabled_rules, $suricatacfg);
		suricata_modify_sids_action($enabled_rules, $suricatacfg);

		// Write the enforcing rules file to the Suricata interface's "rules" directory.
		suricata_write_enforcing_rules_file($enabled_rules, "{$suricatacfgdir}/rules/{$suricata_enforcing_rules_file}");

		// If auto-flowbit resolution is enabled, generate the dependent flowbits rules file.
		if ($suricatacfg['autoflowbitrules'] == 'on') {
			logger(LOG_NOTICE, localize_text('Enabling any flowbit-required rules for: %s...', convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
			$fbits = suricata_resolve_flowbits($all_rules, $enabled_rules);

			// Check for and disable any flowbit-required rules the user has
			// manually forced to a disabled state.
			suricata_modify_sids($fbits, $suricatacfg);
			suricata_write_flowbit_rules_file($fbits, "{$suricatacfgdir}/rules/{$flowbit_rules_file}");
			unset($fbits);
		} else {
			// Just put an empty file to always have the file present
			suricata_write_flowbit_rules_file(array(), "{$suricatacfgdir}/rules/{$flowbit_rules_file}");
		}
	}
	// If no rule categories were enabled, then use auto-SID management if enabled, since it may enable some rules
	elseif (config_get_path('installedpackages/suricata/config/0/auto_manage_sids', '') == 'on' &&
		(!empty($suricatacfg['disable_sid_file']) || !empty($suricatacfg['enable_sid_file']) || 
		!empty($suricatacfg['modify_sid_file']))) {

		suricata_auto_sid_mgmt($enabled_rules, $suricatacfg, TRUE);
		if (!empty($enabled_rules)) {
			// Auto-SID management generated some rules, so use them but
			// apply any user-specified overrides for state or action.
			$no_rules_defined = false;
			suricata_modify_sids($enabled_rules, $suricatacfg);
			suricata_modify_sids_action($enabled_rules, $suricatacfg);

			// Write the enforcing rules file to the Suricata interface's "rules" directory.
			suricata_write_enforcing_rules_file($enabled_rules, "{$suricatacfgdir}/rules/{$suricata_enforcing_rules_file}");

			// If auto-flowbit resolution is enabled, generate the dependent flowbits rules file.
			if ($suricatacfg['autoflowbitrules'] == 'on') {
				logger(LOG_NOTICE, localize_text('Enabling any flowbit-required rules for: %s...', convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);

				// Load up all rules into a Rules Map array for flowbits assessment
				$fbits = suricata_resolve_flowbits($all_rules, $enabled_rules);

				// Check for and disable any flowbit-required rules the
				// user has manually forced to a disabled state.
				suricata_modify_sids($fbits, $suricatacfg);
				suricata_write_flowbit_rules_file($fbits, "{$suricatacfgdir}/rules/{$flowbit_rules_file}");
				unset($fbits);
			} else {
				// Just put an empty file to always have the file present
				suricata_write_flowbit_rules_file(array(), "{$suricatacfgdir}/rules/{$flowbit_rules_file}");
			}
		}
		else {
			suricata_write_enforcing_rules_file(array(), "{$suricatacfgdir}/rules/{$suricata_enforcing_rules_file}");
			suricata_write_flowbit_rules_file(array(), "{$suricatacfgdir}/rules/{$flowbit_rules_file}");
		}
	}
	 else {
		suricata_write_enforcing_rules_file(array(), "{$suricatacfgdir}/rules/{$suricata_enforcing_rules_file}");
		suricata_write_flowbit_rules_file(array(), "{$suricatacfgdir}/rules/{$flowbit_rules_file}");
	}

	unset($all_rules);

	if (!empty($suricatacfg['customrules'])) {
		@file_put_contents("{$suricatacfgdir}/rules/custom.rules", base64_decode($suricatacfg['customrules']));
		$no_rules_defined = false;
	}
	else {
		@file_put_contents("{$suricatacfgdir}/rules/custom.rules", "");
	}

	// Log a warning if the interface has no rules defined or enabled
	if ($no_rules_defined) {
		logger(LOG_WARNING, localize_text("no text rules selected for: %s ...", convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
	}

	// Build a new sid-msg.map file from the enabled
	// rules and copy it to the interface directory.
	logger(LOG_NOTICE, localize_text("Building new %s file for %s...", 'sid-msg.map', convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])), LOG_PREFIX_PKG_SURICATA);
	suricata_build_sid_msg_map("{$suricatacfgdir}/rules/", "{$suricatacfgdir}/sid-msg.map");
}


function suricata_write_enforcing_rules_file($rule_map, $rule_path) {

	/************************************************/
	/* This function takes a rules map array of     */
	/* the rules chosen for the active rule set     */
	/* and writes them out to the passed path.      */
	/*                                              */
	/*  $rule_map --> Rules Map array of rules to   */
	/*                write to disk.                */
	/*                                              */
	/* $rule_path --> filename or directory where   */
	/*                rules file will be written.   */
	/************************************************/

	$rule_file = "/" . SURICATA_ENFORCING_RULES_FILENAME;

	// See if we were passed a directory or full
	// filename to write the rules to, and adjust
	// the destination argument accordingly.
	if (is_dir($rule_path)) {
		$rule_file = rtrim($rule_path, '/').$rule_file;
	} else {
		$rule_file = $rule_path;
	}

	// If the $rule_map array is empty, then exit.
	if (empty($rule_map)) {
		file_put_contents($rule_file, "");
		return;
	}

	$fp = fopen($rule_file, "w");
	if ($fp) {
		@fwrite($fp, "# These rules are your current set of enforced rules for the protected\n");
		@fwrite($fp, "# interface.  This list was compiled from the categories selected on the\n");
		@fwrite($fp, "# CATEGORIES tab of the Suricata configuration for the interface and/or any\n");
		@fwrite($fp, "# chosen Snort VRT pre-defined IPS Policy.\n#\n");
		@fwrite($fp, "# Any enablesid or disablesid customizations you made have been applied\n");
		@fwrite($fp, "# to the rules in this file.\n\n");
		foreach ($rule_map as $rulem) {
			foreach ($rulem as $rulem2) {
				/* No reason to write disabled rules to enforcing file, so skip them. */
				if ($rulem2['disabled'] == 1) {
					continue;
				}
				@fwrite($fp, $rulem2['rule']);
			}
		}
		fclose($fp);
	}
}

function suricata_create_rc() {

	/************************************************************/
	/* This function builds the /usr/local/etc/rc.d/suricata.sh */
	/* shell script for starting and stopping Suricata.  The    */
	/* script is rebuilt on each package sync operation and     */
	/* after any changes to suricata.conf saved in the GUI.     */
	/************************************************************/

	global $g;

	$suricatadir = SURICATADIR;
	$suricatalogdir = SURICATALOGDIR;
	$suricatabindir = SURICATA_PBI_BINDIR;
	$rcdir = RCFILEPREFIX;
	$no_enabled_interface = TRUE;
	$LOG_PREFIX_PKG_SURICATA = LOG_PREFIX_PKG_SURICATA;

	// If no interfaces are configured for Suricata, remove any
	// existing shell script and exit.
	if (count(config_get_path('installedpackages/suricata/rule', [])) < 1) {
		unlink_if_exists("{$rcdir}suricata.sh");
		return;
	}

	// At least one interface is configured, so OK
	$start_suricata_iface_start = array();
	$start_suricata_iface_stop = array();

	// Loop thru each configured interface and build
	// the shell script.
	foreach (config_get_path('installedpackages/suricata/rule', []) as $value) {

		// Attempt to grab physical interface
		// for this Suricata instance.
		$if_real = get_real_interface($value['interface']);

		// Skip disabled Suricata instances
		// or instances whose pfSense physical
		// interface has been removed.
		if (($value['enable'] != 'on') || ($if_real == "")) {
			continue;
		}

		// We have at least one enabled Suricata interface
		$no_enabled_interface = FALSE;
		$suricata_uuid = $value['uuid'];
		$run_mode = $value['ips_mode'] == 'ips_mode_inline' && $value['blockoffenders'] == 'on' ? '--netmap' : '-i ' . $if_real;
		$verbose_logging = $value['enable_verbose_logging'] == 'on' ? "-vv" : "";

		$start_suricata_iface_start[] = <<<EOE

	## Start suricata on {$value['descr']} ({$if_real}) ##
	if [ ! -f {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid ]; then
		pid=`/bin/pgrep -fn "suricata {$run_mode} -D -c {$suricatadir}suricata_{$suricata_uuid}_{$if_real}/suricata.yaml "`
	else
		pid=`/bin/pgrep -F {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid`
	fi

	if [ -z \$pid ]; then
		/bin/cp /dev/null {$suricatalogdir}suricata_{$if_real}{$suricata_uuid}/suricata.log
		/usr/bin/logger -p daemon.info -i -t SuricataStartup "INFO [{$LOG_PREFIX_PKG_SURICATA}] START for {$value['descr']}({$suricata_uuid}_{$if_real})..."
		{$suricatabindir}suricata {$run_mode} -D -c {$suricatadir}suricata_{$suricata_uuid}_{$if_real}/suricata.yaml --pidfile {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid {$verbose_logging} > /dev/null 2>&1
	fi

EOE;

		$start_suricata_iface_stop[] = <<<EOE

	if [ -f {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid ]; then
		pid=`/bin/pgrep -F {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid`
		/usr/bin/logger -p daemon.info -i -t SuricataStartup "INFO [{$LOG_PREFIX_PKG_SURICATA}] STOP for {$value['descr']}({$suricata_uuid}_{$if_real})..."
		if [ -f {$g['varrun_path']}/suricata-ctrl-socket-{$suricata_uuid} ]; then
			/usr/local/bin/suricatasc -c shutdown {$g['varrun_path']}/suricata-ctrl-socket-{$suricata_uuid} 2>/dev/null
		else
			/bin/pkill -TERM -F {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid
		fi
		time=0 timeout=30
		while /bin/kill -TERM \$pid 2>/dev/null; do
			sleep 1
			time=\$((time+1))
			if [ \$time -gt \$timeout ]; then
				break
			fi
		done
		if [ -f {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid ]; then
			/bin/rm {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid
		fi
	else
		pid=`/bin/pgrep -fn "suricata {$run_mode} -D -c {$suricatadir}suricata_{$suricata_uuid}_{$if_real}/suricata.yaml "`
		if [ ! -z \$pid ]; then
			/usr/bin/logger -p daemon.info -i -t SuricataStartup "INFO [{$LOG_PREFIX_PKG_SURICATA}] STOP for {$value['descr']}({$suricata_uuid}_{$if_real})..."
			if [ -f {$g['varrun_path']}/suricata-ctrl-socket-{$suricata_uuid} ]; then
				/usr/local/bin/suricatasc -c shutdown {$g['varrun_path']}/suricata-ctrl-socket-{$suricata_uuid} 2>/dev/null
			else
				/bin/pkill -TERM -fn "suricata {$run_mode} -D -c {$suricatadir}suricata_{$suricata_uuid}_{$if_real}/suricata.yaml "
			fi
			time=0 timeout=30
			while /bin/kill -TERM \$pid 2>/dev/null; do
				sleep 1
				time=\$((time+1))
				if [ \$time -gt \$timeout ]; then
					break
				fi
			done
		fi
	fi

EOE;
	}

	$rc_start = implode("\n", $start_suricata_iface_start);
	$rc_stop = implode("\n", $start_suricata_iface_stop);

	$suricata_sh_text = <<<EOD
#!/bin/sh
########
# This file was automatically generated
# by the pfSense service handler.
######## Start of main suricata.sh

rc_start() {
	### Make sure libraries path cache is up2date
	/etc/rc.d/ldconfig start

	### Lock out other start signals until we are done
	/usr/bin/touch {$g['varrun_path']}/suricata_pkg_starting.lck
	{$rc_start}

	### Remove the lock since we have started all interfaces
	if [ -f {$g['varrun_path']}/suricata_pkg_starting.lck ]; then
		/bin/rm {$g['varrun_path']}/suricata_pkg_starting.lck
	fi
}

rc_stop() {
	{$rc_stop}
}

case $1 in
	start)
		if [ ! -f {$g['varrun_path']}/suricata_pkg_starting.lck ]; then
			rc_start
		else
			/usr/bin/logger -p daemon.info -i -t SuricataStartup "INFO [{$LOG_PREFIX_PKG_SURICATA}] Ignoring spurious START command since Suricata is already starting..."
		fi
		;;
	stop)
		rc_stop
		;;
	restart)
		rc_stop
		sleep 2
		rc_start
		;;
esac

EOD;

	if (!$no_enabled_interface) {
		// Write out the suricata.sh script file
		@file_put_contents("{$rcdir}suricata.sh", $suricata_sh_text);
		@chmod("{$rcdir}suricata.sh", 0755);
	} else {
		unlink_if_exists("{$rcdir}suricata.sh");
	}

	unset($suricata_sh_text);
}

function suricata_generate_yaml($suricatacfg) {

	/************************************************************/
	/* This function generates the suricata.yaml configuration  */
	/* file for Suricata on the passed interface instance.  The */
	/* code uses two included files: one that contains most of  */
	/* the PHP code, and another that provides the template for */
	/* generating the configuration file.  Using two include    */
	/* files works around the "require_once()" caching issues   */
	/* that can prevent new changes in this code from being     */
	/* available during package installs on pfSense.            */
	/*                                                          */
	/* On Entry: suricatacfg --> Suricata instance info in      */
	/*                           the config.xml master config   */
	/*                           file.                          */
	/************************************************************/

	global $g;

	$suricatadir = SURICATADIR;
	$suricatalogdir = SURICATALOGDIR;
	$flowbit_rules_file = FLOWBITS_FILENAME;
	$suricata_enforcing_rules_file = SURICATA_ENFORCING_RULES_FILENAME;
	$if_real = get_real_interface($suricatacfg['interface']);
	$suricata_uuid = $suricatacfg['uuid'];
	$suricatacfgdir = "{$suricatadir}suricata_{$suricata_uuid}_{$if_real}";

	// Nothing to do if no Suricata interfaces configured, so exit
	if (count(config_get_path('installedpackages/suricata/rule', [])) < 1) {
		return;
	}

	// Pull in the PHP code that generates the suricata.yaml file
	// variables that will be substitued further down below.
	include("/usr/local/pkg/suricata/suricata_generate_yaml.php");

	// Pull in the boilerplate template for the suricata.yaml
	// configuration file.  The contents of the template along
	// with substituted variables are stored in $suricata_conf_text
	// (which is defined in the included file).
	include("/usr/local/pkg/suricata/suricata_yaml_template.inc");

	// Now write out the conf file using $suricata_conf_text contents
	@file_put_contents("{$suricatacfgdir}/suricata.yaml", $suricata_conf_text); 
	unset($suricata_conf_text);
}

function suricata_remove_dead_rules() {

	/*********************************************************/
	/* This function removes dead and deprecated rules       */
	/* category files from the base Suricata rules directory */
	/* and from the RULESETS setting of each interface.      */
	/* The file "deprecated_rules", if it exists, is used    */
	/* to determine which rules files to remove.             */
	/*********************************************************/

	$rulesdir = SURICATADIR . "rules/";
	$count = 0;
	$cats = array();

	// If there is no "deprecated_rules" file, then exit
	if (!file_exists("/usr/local/pkg/suricata/deprecated_rules")) {
		return;
	}

	// Open a SplFileObject to read in deprecated rules
	$file = new SplFileObject("/usr/local/pkg/suricata/deprecated_rules");
	$file->setFlags(SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
	while (!$file->eof()) {
		$line = $file->fgets();

		// Skip any lines with just spaces
		if (trim($line) == "") {
			continue;
		}

		// Skip any comment lines starting with '#'
		if (preg_match('/^\s*\#+/', $line)) {
			continue;
		}

		$cats[] = $line;
	}

	// Close the SplFileObject since we are finished with it
	$file = null;

	// Delete any dead rules files from the Suricata RULES directory
	foreach ($cats as $file) {
		if (file_exists("{$rulesdir}{$file}")) {
			$count++;
		}
		unlink_if_exists("{$rulesdir}{$file}");
	}

	// Log how many obsoleted files were removed
	logger(LOG_NOTICE, localize_text("Removed %d obsoleted rules category files.", $count), LOG_PREFIX_PKG_SURICATA);

	// Now remove any dead rules files from the interface configurations
	if (!empty($cats)) {
		$a_ifaces = config_get_path('installedpackages/suricata/rule', []);
		foreach ($a_ifaces as &$iface) {
			$enabled_rules = explode("||", $iface['rulesets']);
			foreach ($enabled_rules as $k => $v) {
				foreach ($cats as $d) {
					if (strpos(trim($v), $d) !== false) {
						unset($enabled_rules[$k]);
					}
				}
			}
			$iface['rulesets'] = implode("||", $enabled_rules);
		}

		// Write the updated ruleset configuration
		config_set_path('installedpackages/suricata/rule', $a_ifaces);
	}

	// Clean up
	unset($cats, $enabled_rules);
}

/* Uses XMLRPC to synchronize the changes to a remote node */
function suricata_sync_on_changes() {
	global $g;

	/* Do not attempt a package sync while booting up or installing package */
	if (is_platform_booting() || $g['suricata_postinstall'] == TRUE) {
		logger(LOG_NOTICE, localize_text("Skipping XMLRPC sync when booting up or during package reinstallation."), LOG_PREFIX_PKG_SURICATA);
		return;
	}

	if (config_get_path('installedpackages/suricatasync/config/0')) {
		$suricata_sync = config_get_path('installedpackages/suricatasync/config/0');
		$synconchanges = $suricata_sync['varsynconchanges'];
		$synctimeout = $suricata_sync['varsynctimeout'] ?: '150';
		$syncdownloadrules = $suricata_sync['vardownloadrules'];
		switch ($synconchanges) {
			case "manual":
				if (is_array($suricata_sync['row'])) {
					$rs = $suricata_sync['row'];
				} else {
					logger(LOG_NOTICE, localize_text("XMLRPC sync is enabled but there are no hosts configured as replication targets."), LOG_PREFIX_PKG_SURICATA);
					return;
				}
				break;
			case "auto":
				if (config_get_path('hasync')) {
					$system_carp = config_get_path('hasync');
					$rs[0]['varsyncipaddress'] = $system_carp['synchronizetoip'];
					$rs[0]['varsyncusername'] = $system_carp['username'];
					$rs[0]['varsyncpassword'] = $system_carp['password'];
					$rs[0]['varsyncsuricatastart'] = FALSE;
					$rs[0]['varsyncdestinenable'] = FALSE;
					// XMLRPC sync is currently only supported over connections using the same protocol and port as this system
					if (config_get_path('system/webgui/protocol', '') == "http") {
						$rs[0]['varsyncprotocol'] = "http";
						$rs[0]['varsyncport'] = config_get_path('system/webgui/port') ?: '80';
					} else {
						$rs[0]['varsyncprotocol'] = "https";
						$rs[0]['varsyncport'] = config_get_path('system/webgui/port') ?: '443';
					}
					if ($system_carp['synchronizetoip'] == "") {
						logger(LOG_NOTICE, localize_text("XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets."), LOG_PREFIX_PKG_SURICATA);
						return;
					} else {
						$rs[0]['varsyncdestinenable'] = TRUE;
					}
				} else {
					logger(LOG_NOTICE, localize_text("XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets."), LOG_PREFIX_PKG_SURICATA);
					return;
				}
				break;			
			default:
				return;
				break;
		}
		if (is_array($rs)) {
			logger(LOG_NOTICE, localize_text("XMLRPC sync is starting."), LOG_PREFIX_PKG_SURICATA);
			foreach ($rs as $sh) {
				// Only sync enabled replication targets
				if ($sh['varsyncdestinenable']) {
					if ($sh['varsyncsuricatastart']) {
						$syncstartsuricata = $sh['varsyncsuricatastart'];
					} else {
						$syncstartsuricata = "OFF";
					}
					$sync_to_ip = $sh['varsyncipaddress'];
					$password = $sh['varsyncpassword'];
					$port = $sh['varsyncport'];
					$protocol = $sh['varsyncprotocol'];
					$error = '';
					$success = TRUE;
					$username = $sh['varsyncusername'] ?: 'admin';
					if ($password == "") {
						$error = "Password parameter is empty. ";
						$success = FALSE;
					}
					if (!is_ipaddr($sync_to_ip) && !is_hostname($sync_to_ip) && !is_domain($sync_to_ip)) {
						$error .= "Misconfigured Replication Target IP Address. ";
						$success = FALSE;
					}
					if (!is_port($port)) {
						$error .= "Misconfigured Replication Target Port. ";
						$success = FALSE;
					}
					if ($success) {
						suricata_do_xmlrpc_sync($syncdownloadrules, $sync_to_ip, $port, $protocol, $username, $password, $synctimeout, $syncstartsuricata);
					} else {
						logger(LOG_ERR, localize_text("XMLRPC sync with '%s' aborted due to the following error(s): %s", $sync_to_ip, $error), LOG_PREFIX_PKG_SURICATA);
					}
				}
			}
			logger(LOG_NOTICE, localize_text("XMLRPC sync completed."), LOG_PREFIX_PKG_SURICATA);
		}
 	}
}

if(!function_exists('pf_version')) {
	function pf_version() {
		return substr(trim(file_get_contents("/etc/version")), 0, 3);
	}
}

/* Do the actual XMLRPC sync */
function suricata_do_xmlrpc_sync($syncdownloadrules, $sync_to_ip, $port, $protocol, $username, $password, $synctimeout, $syncstartsuricata) {
	global $g;

	/* Do not attempt a package sync while booting up or installing package */
	if (is_platform_booting() || isset($g['suricata_postinstall'])) {
		logger(LOG_NOTICE, localize_text("Skipping XMLRPC sync when booting up or during package reinstallation."), LOG_PREFIX_PKG_SURICATA);
		return;
	}

	if ($username == "" || $password == "" || $sync_to_ip == "" || $port == "" || $protocol == "") {
		logger(LOG_ERR, localize_text("A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync"), LOG_PREFIX_PKG_SURICATA);
		return;
	}

	$downloadrulescmd = "";
	if ($syncdownloadrules == "yes") {
		$downloadrulescmd = "logger(LOG_NOTICE, localize_text(\"XMLRPC pkg sync: Update of downloaded rule sets requested...\"), LOG_PREFIX_PKG_SURICATA);\n";
		$downloadrulescmd .= "\tinclude_once(\"/usr/local/pkg/suricata/suricata_check_for_rule_updates.php\");\n";
	}
	$suricatastart = "";
	if ($syncstartsuricata == "ON") {
		$suricatastart = "logger(LOG_NOTICE, localize_text(\"XMLRPC pkg sync: Checking Suricata status...\"), LOG_PREFIX_PKG_SURICATA);\n";
		$suricatastart .= "\tif (!is_process_running(\"suricata\")) {\n";
		$suricatastart .= "\t\tlogger(LOG_NOTICE, localize_text(\"XMLRPC pkg sync: Suricata not running. Sending a start command...\"), LOG_PREFIX_PKG_SURICATA);\n";
		$suricatastart .= "\t\t\$sh_script = RCFILEPREFIX . \"suricata.sh\";\n";
		$suricatastart .= "\t\tmwexec_bg(\"{\$sh_script} start\");\n\t}\n";
		$suricatastart .= "\telse {\n\t\tlogger(LOG_NOTICE, localize_text(\"XMLRPC pkg sync: Suricata is running...\"), LOG_PREFIX_PKG_SURICATA);\n\t}\n";
	}

	/*************************************************/
	/* Build a series of commands as a PHP file for  */
	/* the secondary host to execute to load the new */
	/* settings.                                     */
	/*************************************************/
	$suricata_sync_cmd = <<<EOD
	<?php
	require_once("/usr/local/pkg/suricata/suricata.inc");
	require_once("service-utils.inc");
	global \$g, \$rebuild_rules, \$suricata_gui_include, \$pkg_interface;
	\$orig_pkg_interface = \$pkg_interface;
	\$g["suricata_postinstall"] = true;
	\$g["suricata_sync_in_progress"] = true;
	\$suricata_gui_include = false;
	\$pkg_interface = "console";
	{$downloadrulescmd}
	unset(\$g["suricata_postinstall"]);
	logger(LOG_NOTICE, localize_text("XMLRPC pkg sync: Generating %s file using Master Host settings...", 'suricata.yaml'), LOG_PREFIX_PKG_SURICATA);
	\$rebuild_rules = true;
	sync_suricata_package_config();
	\$rebuild_rules = false;
	{$suricatastart}
	logger(LOG_NOTICE, localize_text("XMLRPC pkg sync process on this host is complete..."), LOG_PREFIX_PKG_SURICATA);
	\$pkg_interface = \$orig_pkg_interface;
	unset(\$g["suricata_sync_in_progress"]);
	return true;
	?>

EOD;

	/*************************************************/
	/* First, have target host write the commands    */ 
	/* to a PHP file in the /tmp directory.          */
	/*************************************************/
	$execcmd = "file_put_contents('/tmp/suricata_sync_cmds.php', '{$suricata_sync_cmd}');";
	
	/*************************************************/
	/* Now assemble a command to execute the         */
	/* previously sent PHP file in the background.   */
	/*************************************************/
	$execcmd2 = "exec(\"/usr/local/bin/php-cgi -f '/tmp/suricata_sync_cmds.php' > /dev/null 2>&1 &\");";
	
	$synctimeout = intval($synctimeout);
	$rpc_client = new pfsense_xmlrpc_client();
	$rpc_client->setConnectionData($sync_to_ip, $port, $username, $password, $protocol);
	
	/*************************************************/
	/* Send over any auto-SID management files       */
	/*************************************************/
	$sid_files = glob(SURICATA_SID_MODS_PATH . '*');
	foreach ($sid_files as $file) {
		$content = base64_encode(file_get_contents($file));
		$payload = "@file_put_contents('{$file}', base64_decode('{$content}'));";
		$resp = $rpc_client->xmlrpc_exec_php($payload, $synctimeout);
	}
	/*************************************************/
	/* Send over any IPREP IP List files             */
	/*************************************************/
	$iprep_files = glob(SURICATA_IPREP_PATH . '*');
	foreach ($iprep_files as $file) {
		$content = base64_encode(file_get_contents($file));
		$payload = "@file_put_contents('{$file}', base64_decode('{$content}'));";
		$resp = $rpc_client->xmlrpc_exec_php($payload, $synctimeout);
	}

	/*************************************************/
	/* Sync the configuration in manual sync mode.   */
	/* For HA the configuration itself is instead    */
	/* synced via the XMLRPC plugins - the function  */
	/* suricata_do_xmlrpc_sync() is called after.    */
	/*************************************************/
	if (config_get_path('installedpackages/suricatasync/config/0/varsynconchanges') != 'auto') {
		/**************************************************/
		/* Send over the <suricata> portion of config.xml */
		/* $xml will hold the section to sync.            */
		/**************************************************/
		$xml = array();
		$xml['suricata'] = config_get_path('installedpackages/suricata');

		// xmlrpc cannot encode NULL objects/arrays..
		foreach($xml as $xmlkey => $xmlvalue) {
			if (gettype($xmlvalue) == "NULL") {
				$xml[$xmlkey] = array();
			}
		}

		$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section', $xml, $synctimeout);
	}

	$resp = $rpc_client->xmlrpc_exec_php($execcmd, $synctimeout);
	$resp = $rpc_client->xmlrpc_exec_php($execcmd2, $synctimeout);
}

function suricata_plugin_xmlrpc_send() {
	if (config_get_path('installedpackages/suricatasync/config/0/varsynconchanges') != 'auto') {
		return [];
	}

	return [
		'installedpackages/suricatasync/config/0/varsynconchanges',
		'installedpackages/suricata'
	];
}

function suricata_plugin_xmlrpc_recv($new_sections) {
	if (array_get_path($new_sections, 'installedpackages/suricatasync/config/0/varsynconchanges') != 'auto') {
		return [];
	}

	$section_paths = [
		'installedpackages/suricata'
	];

	$ret = [
		'xmlrpc_recv_result' => false
	];
	foreach ($section_paths as $path) {
		$old_section = config_get_path($path, []);
		$new_section = array_get_path($new_sections, $path, []);

		// Don't save empty configuration sections.
		if (empty($old_section) && empty($new_section)) {
			continue;
		}
		// Ignore unchanged configuration sections.
		if ($old_section === $new_section) {
			continue;
		}

		$ret[$path] = array_merge($old_section, $new_section);
		$ret['xmlrpc_recv_result'] = true;
	}

	return $ret;
}

function suricata_get_vpns_list() {

	$vpns = "";
	$vpns_arr = array();

	/* IPsec */
	if (!function_exists('ipsec_enabled')) {
		require_once("ipsec.inc");
	}
	if (ipsec_enabled()) {
		if (config_path_enabled('ipsec/client')) {
			/* Virtual Address Pool */
			if (config_path_enabled('ipsec/client', 'pool_address') &&
			    config_path_enabled('ipsec/client', 'pool_netbits')) {
				$client_subnet = config_get_path('ipsec/client/pool_address') . '/' . config_get_path('ipsec/client/pool_netbits');
				if (is_subnetv4($client_subnet)) {
					 $vpns_arr[] = $client_subnet;
				}
			}
			/* Virtual IPv6 Address Pool */
			if (config_path_enabled('ipsec/client', 'pool_address_v6') &&
			    config_path_enabled('ipsec/client', 'pool_netbits_v6')) {
				$client_subnet = config_get_path('ipsec/client/pool_address_v6') . '/' . config_get_path('ipsec/client/pool_netbits_v6');
				if (is_subnetv6($client_subnet)) {
					$vpns_arr[] = text_to_compressed_ip6($client_subnet);
				}
			}
			/* Mobile warriors */
			if (config_path_enabled('ipsec', 'mobilekey')) {
				foreach (config_get_path('ipsec/mobilekey', []) as $key) {
					if (!empty($key['pool_address']) &&
					    !empty($key['pool_netbits'])) {
						$vpns_subnet = "{$key['pool_address']}/{$key['pool_netbits']}";
						if (is_subnetv4($vpns_subnet)) {
							$vpns_arr[] = $vpns_subnet;
						}
					}
				}
			}
		}
		/* Site-to-Site IPsec */
		if (config_get_path('ipsec/phase2')) {
			foreach (config_get_path('ipsec/phase2', []) as $ph2ent) {
				if ((!isset($ph2ent['mobile'])) && ($ph2ent['mode'] != 'transport') &&
				    !isset($ph2ent['disabled'])) {
					if (!array_get_path($ph2ent, 'remoteid')) {
						continue;
					}
					$ph2ent['remoteid']['mode'] = $ph2ent['mode'];
					$vpns_subnet = ipsec_idinfo_to_cidr($ph2ent['remoteid']);
					if (is_subnetv4($vpns_subnet)) {
						$vpns_arr[] = $vpns_subnet;
					}
					if (is_subnetv6($vpns_subnet)) {
						$vpns_arr[] = text_to_compressed_ip6($vpns_subnet);
					}
				}
			}
		}
	}
	/* OpenVPN */
	foreach (array('client', 'server') as $type) {
		foreach (config_get_path("openvpn/openvpn-{$type}", []) as $settings) {
			if (is_array($settings)) {
				if (!array_path_enabled($settings, 'disable')) {
					$remote_networks = explode(',', $settings['remote_network']);
					foreach ($remote_networks as $remote_network) {
						if (function_exists('openvpn_gen_tunnel_network')) {
							$vpns_arr[] = implode('/', openvpn_gen_tunnel_network($remote_network));
						} elseif (is_subnetv4($remote_network)) {
							$vpns_arr[] = $remote_network;
						}
					}
					if (function_exists('openvpn_gen_tunnel_network')) {
						$vpns_arr[] = implode('/', openvpn_gen_tunnel_network($settings['tunnel_network']));
					} elseif (is_subnetv4($settings['tunnel_network'])) {
						$vpns_arr[] = $settings['tunnel_network'];
					}
					if (isset($settings['remote_networkv6'])) {
						$remote_networks = explode(',', $settings['remote_networkv6']);
						foreach ($remote_networks as $remote_network) {
							if (function_exists('openvpn_gen_tunnel_network')) {
								$vpns_arr[] = implode('/', openvpn_gen_tunnel_network($remote_network));
							} elseif (is_subnetv6($remote_network)) {
								$vpns_arr[] = text_to_compressed_ip6($remote_network);
							}
						}
						if (function_exists('openvpn_gen_tunnel_network')) {
							$vpns_arr[] = implode('/', openvpn_gen_tunnel_network($settings['tunnel_networkv6']));
						} elseif (is_subnetv6($settings['tunnel_networkv6'])) {
							$vpns_arr[] = text_to_compressed_ip6($settings['tunnel_networkv6']);
						}
					}
				}
			}
		}
	}
	// OpenVPN CSO
	foreach (config_get_path('openvpn/openvpn-csc', []) as $ovpnent) {
		if (is_array($ovpnent) && !array_path_enabled($ovpnent, 'disable')) {
			if (!empty($ovpnent['tunnel_network'])) {
				if (function_exists('openvpn_gen_tunnel_network')) {
					$vpns_arr[] = implode('/', openvpn_gen_tunnel_network($ovpnent['tunnel_network']));
				} else {
					$vpns_arr[] = $ovpnent['tunnel_network'];
				}
			}
			if (!empty($ovpnent['tunnel_networkv6'])) {
				if (function_exists('openvpn_gen_tunnel_network')) {
					$vpns_arr[] = implode('/', openvpn_gen_tunnel_network($ovpnent['tunnel_networkv6']));
				} else {
					$vpns_arr[] = $ovpnent['tunnel_networkv6'];
				}
			}
		}
	}
	/* PPPoE Server */
	foreach (config_get_path('pppoes/pppoe', []) as $pppoe) {
		if ($pppoe['mode'] == "server") {
			if (is_ipaddrv4($pppoe['remoteip'])) {
				$pppoesub = gen_subnetv4($pppoe['remoteip'], $pppoe['pppoe_subnet']);
				if (is_subnetv4($pppoesub)) {
					$vpns_arr[] = $pppoesub;
				}
			}
		}
	}
	/* L2TP Server */
	if (config_get_path('l2tp/mode') == "server") {
		$l2tp_net = config_get_path('l2tp/remoteip') . '/' . config_get_path('l2tp/l2tp_subnet');
		if (is_subnetv4($l2tp_net)) {
			$vpns_arr[] = $l2tp_net;
		}
	}
	/* WireGuard */
	if (function_exists('wg_get_tunnel_networks')) {
		foreach (wg_get_tunnel_networks() as $wgn) {
			$vpns_arr[] = $wgn;
		}
	}

	if (!empty($vpns_arr)) {
		$vpns = implode(" ", array_diff($vpns_arr, array("0.0.0.0/0", "::/0")));
	}

	return $vpns;
}

function suricata_listfiles($dir) {
	$files = array();
	foreach (scandir($dir) as $file) {
		if (($file != '.') && ($file != '..')) {
			$path = $dir . '/' . $file;
			if (is_dir($path)) {
				$files = array_merge($files, suricata_listfiles($path));
			} else {
				$files[] = $path;
			}
		}
	}
	return($files);
}
