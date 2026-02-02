<?php
/*
 * snort.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009-2010 Robert Zelaya
 * Copyright (c) 2013-2024 Bill Meeks
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
require_once("functions.inc");
require_once("service-utils.inc"); // Need this to get RCFILEPREFIX definition
require_once("pkg-utils.inc");
require_once("filter.inc");
require_once("xmlrpc_client.inc");
require("/usr/local/pkg/snort/snort_defs.inc");

// Snort GUI needs at least 512 MB to manipulate large rules arrays
ini_set("memory_limit", config_get_path('system/php_memory_limit', '512') . "M");

// Explicitly declare this as global so it works through function call includes 
global $g, $rebuild_rules;

/* Rebuild Rules Flag -- if "true", rebuild enforcing rules and flowbit-rules files */
$rebuild_rules = false;

function snort_is_single_addr_alias($alias) {
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

function snort_expand_port_range($ports, $delim = ',') {
	/**************************************************/
	/* This function examines the passed ports string */
	/* and expands any embedded port ranges into the  */
	/* individual ports separated by the specified    */
	/* delimiter.  A port range is indicated by a     */
	/* colon in the string.                           */
	/*                                                */
	/* On Entry: $ports ==> string to be evaluated    */
	/*                      with {$delim} separating  */
	/*                      the port values.          */
	/*  Returns: string with any encountered port     */
	/*           ranges expanded and the values       */
	/*           delimited by {$delim}.               */
	/**************************************************/

	$value = "";

	// Split the incoming string on the specified delimiter
	$tmp = explode($delim, $ports);

	// Look for any included port range and expand it
	foreach ($tmp as $val) {
		if (is_portrange($val)) {
			$start = strtok($val, ":");
			$end = strtok(":");
			if ($end !== false) {
				$val = $start . $delim;
				for ($i = intval($start) + 1; $i < intval($end); $i++)
					$val .= strval($i) . $delim;
				$val .= $end;
			}
		}
		$value .= $val . $delim;
	}

	// Remove any trailing delimiter in return value
	return trim($value, $delim);
}

function snort_get_blocked_ips() {
	$blocked_ips = array();
	exec('/sbin/pfctl -t snort2c -T show', $blocked_ips);
	$blocked_ips_array = array();
	if (!empty($blocked_ips)) {
		$blocked_ips_array = array();
		if (is_array($blocked_ips)) {
			foreach ($blocked_ips as $blocked_ip) {
				if (empty($blocked_ip))
					continue;
				$blocked_ips_array[] = trim($blocked_ip, " \n\t");
			}
		}
	}

	return $blocked_ips_array;
}

function snort_generate_id() {

	while (true) {
		$snort_uuid = mt_rand(1, 65535);
		foreach (config_get_path('installedpackages/snortglobal/rule', []) as $value) {
			if ($value['uuid'] == $snort_uuid)
				continue 2;
		}
		break;
	}

	return $snort_uuid;
}

function snort_load_suppress_sigs($snortcfg, $track_by=false) {

	/**********************************************************/
	/* This function loads the GEN_ID and SIG_ID for all the  */
	/* suppressed alert entries from the Suppression List of  */
	/* the passed Snort interface.  The results are returned  */
	/* in an array with GEN_ID and SIG_ID as the primary      */
	/* keys.  Any "track by_src" or "track by_dst" entries    */
	/* in the Suppression List are tacked on as additional    */
	/* keys in the array along with the IP address in either  */
	/* IPv4 or IPv6 format when $track_by is passed as true.  */
	/*                                                        */
	/* Sample returned array:                                 */
	/*  $suppress[1][2069] = "suppress"                       */
	/*  $suppress[1][2070]['by_src']['10.1.1.5'] = "suppress" */
	/*  $suppress[1][2070]['by_dst']['10.1.1.6'] = "suppress" */
	/*                                                        */
	/**********************************************************/

	$suppress = array();

	foreach (config_get_path('installedpackages/snortglobal/suppress/item', []) as $a_id => $alist) {
		if ($alist['name'] == $snortcfg['suppresslistname']) {
			if (!empty($alist['suppresspassthru'])) {
				$tmplist = str_replace("\r", "", base64_decode($alist['suppresspassthru']));
				$tmp = explode("\n", $tmplist);
				foreach ($tmp as $line) {
					// Skip any blank lines
					if (trim($line, " \n") == "")
						continue;
					// Skip any comment lines
					if (preg_match('/^\s*#/', $line))
						continue;
					/* See if entry suppresses GID:SID for all hosts */
					if (preg_match('/\s*suppress\s*gen_id\b\s*(\d+),\s*sig_id\b\s*(\d+)\s*$/i', $line, $matches)) {
						$genid = $matches[1];
						$sigid = $matches[2];
						if (!empty($genid) && !empty($sigid)) {
							if (!is_array($suppress[$genid]))
								$suppress[$genid] = array();
							if (!is_array($suppress[$genid][$sigid]))
								$suppress[$genid][$sigid] = array();
							$suppress[$genid][$sigid] = "suppress";
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
								if (!is_array($suppress[$genid]))
									$suppress[$genid] = array();
								if (!is_array($suppress[$genid][$sigid]))
									$suppress[$genid][$sigid] = array();
								if (!is_array($suppress[$genid][$sigid][$whichip]))
									$suppress[$genid][$sigid][$whichip] = array();
								if (!is_array($suppress[$genid][$sigid][$whichip][$ip]))
									$suppress[$genid][$sigid][$whichip][$ip] = array();
								$suppress[$genid][$sigid][$whichip][$ip] = "suppress";
							}
						}
						/* See if entry suppresses only by SRC or DST IPv6 address */
						if (preg_match('/\s*suppress\s*gen_id\b\s*(\d+),\s*sig_id\b\s*(\d+),\s*track\s*(by_src|by_dst),\s*ip\s*([0-9a-f\.:]+)\s*$/i', $line, $matches)) {
							$genid = $matches[1];
							$sigid = $matches[2];
							$whichip = trim($matches[3]);
							$ip = trim($matches[4]);
							if (!empty($genid) && !empty($sigid) && !empty($whichip) && !empty($ip)) {
								if (!is_array($suppress[$genid]))
									$suppress[$genid] = array();
								if (!is_array($suppress[$genid][$sigid]))
									$suppress[$genid][$sigid] = array();
								if (!is_array($suppress[$genid][$sigid][$whichip]))
									$suppress[$genid][$sigid][$whichip] = array();
								if (!is_array($suppress[$genid][$sigid][$whichip][$ip]))
									$suppress[$genid][$sigid][$whichip][$ip] = array();
								$suppress[$genid][$sigid][$whichip][$ip] = "suppress";
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

/* func finds custom lists by name and type (defaults to 'whitelist') */
function snort_find_list($find_name, $type = 'whitelist') {

	foreach (config_get_path("installedpackages/snortglobal/{$type}/item", []) as $value) {
		if ($value['name'] == $find_name)
			return $value;
	}

	return array();
}

/* func builds custom whitelists and the HOME_NET variable */
function snort_build_list($snortcfg, $listname = "", $whitelist = false, $externallist = false) {

	/***********************************************************/
	/* The default is to build a HOME_NET variable unless      */
	/* '$whitelist' or '$externallist is set to TRUE when      */
	/* calling.                                                */
	/*                                                         */
	/* When '$whitelist' is TRUE, a Pass List is built.        */
	/* When '$externalist' is TRUE, the EXTERNAL_NET variable  */
	/* is built.                                               */
	/***********************************************************/

	global $g, $aliastable, $filterdns;
	$home_net = array();

	// If passed an empty interface config array, then
	// exit returning an empty list array. This is an
	// unexpected condition.
	if (empty($snortcfg))
		return $home_net;

	if (!$externallist && ($listname == 'default' || empty($listname))) {
		$localnet = 'yes'; $wanip = 'yes'; $wangw = 'yes'; $wandns = 'yes'; $vips = 'yes'; $vpns = 'yes';
	}
	else {
                $list = snort_find_list($listname);
                if (empty($list))
                        return $list;
		$localnet = $list['localnets'];
		$wanip = 'yes';
		$wangw = $list['wangateips'];
		$wandns = $list['wandnsips'];
		$vips = $list['vips'];
		$vpns = $list['vpnips'];

		// Process any custom IPs or aliases defined for the list.
		// We allow dynamically updated aliases only for a Pass
		// List. So for Pass Lists, we test for null strings from
		// filter_expand_alias() and assume the passed alias is a
		// FQDN or other dynamically updated alias.
		if (array_get_path($list, 'address/item')) {
			foreach ($list['address']['item'] as $addr) {
				if (!$whitelist) {
					if (is_alias($addr) && (alias_get_type($addr) == "host" || alias_get_type($addr) == "network")) {
						$home_net = array_merge($home_net, explode(" ", trim(filter_expand_alias($addr))));
					} elseif (is_ipaddr($addr) || is_subnet($addr)) {
						$home_net[] = $addr;
					}
				} elseif ($whitelist) {
					if (is_alias($addr) && (alias_get_type($addr) == "host" || alias_get_type($addr) == "network")) {
						$tmp = trim(filter_expand_alias($addr));
						if ($tmp <> "") {
							$home_net = array_merge($home_net, explode(" ", $tmp));
						} else {
							$home_net[] = $addr;
						}
					} elseif (is_ipaddr($addr) || is_subnet($addr)) {
						$home_net[] = $addr;
					}
				}
			}
		}
	}

	/* Always add loopback addresses to HOME_NET and whitelist */
	if (!$externallist) {
		if (!in_array("127.0.0.1", $home_net))
			$home_net[] = "127.0.0.1";
		if (!in_array("::1", $home_net))
			$home_net[] = "::1";
	}

	/********************************************************************/
	/* Always put the interface running Snort in HOME_NET and whitelist */
	/* unless it's the WAN.  WAN options are handled further down.      */
	/* If the user specifically chose not to include LOCAL_NETS in the  */
	/* WHITELIST, then do not include the Snort interface subnet in the */
	/* WHITELIST. We do include the actual LAN interface IP for Snort,  */
	/* though, to prevent locking out the firewall itself.              */
	/********************************************************************/
	$snortip = get_interface_ip($snortcfg['interface']);
	if (($externallist && $localnet == 'yes') || (!$externallist && $whitelist && ($localnet == 'yes' || empty($localnet)))) {
		if (is_ipaddr($snortip)) {
			if ($snortcfg['interface'] <> "wan") {
				if ($sn = get_interface_subnet($snortcfg['interface'])) {
					$ip = gen_subnet($snortip, $sn) . "/{$sn}";
					if (!in_array($ip, $home_net))
						$home_net[] = $ip;
				}
			}
		}
	}
	elseif (!$externallist && $localnet != 'yes') {
		if (is_ipaddrv4($snortip)) {
			if (!in_array($snortip, $home_net))
				$home_net[] = $snortip;
		}
	}

	// Grab the IPv6 address if we have one assigned
	$snortip = get_interface_ipv6($snortcfg['interface']);
	// Trim off the interface designation (e.g., %em1) if present
	if (strpos($snortip, "%") !== FALSE)
		$snortip = substr($snortip, 0, strpos($snortip, "%"));
	if (($externallist && $localnet == 'yes') || ($whitelist && ($localnet == 'yes' || empty($localnet))) || (!$externallist && !$whitelist && ($localnet == 'yes' || empty($localnet)))) {
		if (is_ipaddrv6($snortip)) {
			if ($snortcfg['interface'] <> "wan") {
				if ($sn = get_interface_subnetv6($snortcfg['interface'])) {
					$ip = gen_subnetv6($snortip, $sn). "/{$sn}";
					if (!in_array($ip, $home_net))
						$home_net[] = $ip;
				}
			}
		}
	}
	elseif (!$externallist && $localnet != 'yes') {
		if (is_ipaddrv6($snortip)) {
			if (!in_array($snortip, $home_net))
				$home_net[] = $snortip;
		}
	}

	// Add link-local address if user included locally-attached networks
	$snortip = get_interface_linklocal($snortcfg['interface']);
	if (!empty($snortip) && $localnet == 'yes') {
		// Trim off the interface designation (e.g., %em1) if present
		if (strpos($snortip, "%") !== FALSE)
			$snortip = substr($snortip, 0, strpos($snortip, "%"));
		if (!in_array($snortip, $home_net))
			$home_net[] = $snortip;
	}

	if (($externallist && $localnet == 'yes') || ($whitelist && ($localnet == 'yes' || empty($localnet))) || (!$externallist && !$whitelist && ($localnet == 'yes' || empty($localnet)))) {
		/*************************************************************************/
		/*  Iterate through the interface list and write out whitelist items and */
		/*  also compile a HOME_NET list of all the local interfaces for snort.  */
		/*  Skip the WAN interface as we do not typically want that whole subnet */
		/*  whitelisted (just the i/f IP itself which was handled earlier).      */
		/*************************************************************************/
		$int_array = get_configured_interface_list();
		foreach ($int_array as $int) {
			if ($int == "wan")
				continue;
			$subnet = get_interface_ip($int);
			if (is_ipaddrv4($subnet)) {
				if ($sn = get_interface_subnet($int)) {
					$ip = gen_subnet($subnet, $sn) . "/{$sn}";
					if (!in_array($ip, $home_net))
						$home_net[] = $ip;
				}
			}

			$subnet = get_interface_ipv6($int);
			// Trim off the interface designation (e.g., %em1) if present
			if (strpos($subnet, "%") !== FALSE)
				$subnet = substr($subnet, 0, strpos($subnet, "%"));
			if (is_ipaddrv6($subnet)) {
				if ($sn = get_interface_subnetv6($int)) {
					$ip = gen_subnetv6($subnet, $sn). "/{$sn}";
					if (!in_array($ip, $home_net))
						$home_net[] = $ip;
				}
			}

			// Add link-local address
			$snortip = get_interface_linklocal($int);
			if (!empty($snortip)) {
				// Trim off the interface designation (e.g., %em1) if present
				if (strpos($snortip, "%") !== FALSE)
					$snortip = substr($snortip, 0, strpos($snortip, "%"));
				if (!in_array($snortip, $home_net))
					$home_net[] = $snortip;
			}
		}
	}

	if ($wanip == 'yes') {
		$ip = get_interface_ip("wan");
		if (is_ipaddrv4($ip)) {
			if (!in_array($ip, $home_net))
				$home_net[] = $ip;
		}
		$ip = get_interface_ipv6("wan");
		// Trim off the interface designation (e.g., %em1) if present
		if (strpos($ip, "%") !== FALSE)
			$ip = substr($ip, 0, strpos($ip, "%"));
		if (is_ipaddrv6($ip)) {
			if (!in_array($ip, $home_net))
				$home_net[] = $ip;
		}
		// Explicitly grab the WAN Link-Local address
		$snortip = get_interface_linklocal("wan");
		if (!empty($snortip)) {
			// Trim off the interface designation (e.g., %em1) if present
			if (strpos($snortip, "%") !== FALSE)
				$snortip = substr($snortip, 0, strpos($snortip, "%"));
			if (!in_array($snortip, $home_net))
				$home_net[] = $snortip;
		}
	}

	if ($wangw == 'yes') {
		/* Grab the default gateway if set */
		$default_gw = exec("/sbin/route -n get default |grep 'gateway:' | /usr/bin/awk '{ print $2 }'");
		if (is_ipaddrv4($default_gw) && !in_array($default_gw, $home_net))
			$home_net[] = $default_gw;
		if (is_ipaddrv6($default_gw) && !in_array($default_gw, $home_net))
			$home_net[] = $default_gw;

		/* Get any other interface gateway and put in $HOME_NET if not there already */
		$gw = get_interface_gateway($snortcfg['interface']);
		if (is_ipaddrv4($gw) && !in_array($gw, $home_net))
			$home_net[] = $gw;
		$gw = get_interface_gateway_v6($snortcfg['interface']);
		// Trim off the interface designation (e.g., %em1) if present
		if (strpos($gw, "%") !== FALSE)
			$gw = substr($gw, 0, strpos($gw, "%"));
		if (is_ipaddrv6($gw) && !in_array($gw, $home_net))
			$home_net[] = $gw;
	}

	if ($wandns == 'yes') {
		/* Add DNS server for WAN interface to whitelist */
		$dns_servers = get_dns_servers();
		foreach ($dns_servers as $dns) {
			if ($dns && !in_array($dns, $home_net))
				$home_net[] = $dns;
		}
	}

	if($vips == 'yes') {
		/* iterate all vips and add to whitelist */
		foreach(config_get_path('virtualip/vip', []) as $vip) {
			if ($vip['subnet']) {
				if (!in_array("{$vip['subnet']}/{$vip['subnet_bits']}", $home_net))
					$home_net[] = "{$vip['subnet']}/{$vip['subnet_bits']}";
			}
		}
	}

	// Grab a list of vpns enabled - these come back as CIDR mask networks
	if ($vpns == 'yes') {
		$vpns_list = snort_get_vpns_list();
		if (!empty($vpns_list)) {
			/* Convert the returned space-delimited string to an array */
			/* and then add each VPN address to our HOME_NET array.    */
			$vpns = explode(" ", $vpns_list);
			foreach ($vpns as $vpn)
				$home_net[] = trim($vpn);
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

	/* Release memory no longer required */
	unset($home_net);

	/* Sort the list and return it */
	natsort($valresult);
	return $valresult;
}

/* Checks to see if service is running by looking for PID filename matching the UUID */
function snort_is_running($uuid) {
	global $g;

	return isvalidpid("{$g['varrun_path']}/snort_{$uuid}.pid");
}

function snort_stop($snortcfg, $if_real) {
	global $g;
	$snort_uuid = $snortcfg['uuid'];

	if (isvalidpid("{$g['varrun_path']}/snort_{$snort_uuid}.pid")) {
		logger(LOG_NOTICE, localize_text("Snort STOP for %s(%s)...", convert_real_interface_to_friendly_descr($if_real), $if_real), LOG_PREFIX_PKG_SNORT);
		killbypid("{$g['varrun_path']}/snort_{$snort_uuid}.pid");

		// Now wait up to 10 seconds for Snort to actually stop and clear its PID file
		$count = 0;
		do {
			if (!isvalidpid("{$g['varrun_path']}/snort_{$snort_uuid}.pid"))
				break;
			sleep(1);
			$count++;
		} while ($count < 10);
	}
	unlink_if_exists("{$g['varrun_path']}/snort_{$snort_uuid}.pid");
}

function snort_start($snortcfg, $if_real, $background=FALSE) {
	global $g;

	$snortdir = SNORTDIR;
	$snortlogdir = SNORTLOGDIR;
	$snort_uuid = $snortcfg['uuid'];
	$snortbindir = SNORT_BINDIR;

	if (config_get_path('installedpackages/snortglobal/verbose_logging') == "on")
		$quiet = "";
	else
		$quiet = "-q --suppress-config-log";

	// If Snort is already running on this interface, stop it before restarting
	if (snort_is_running($snort_uuid)) {
		snort_stop($snortcfg, $if_real);
	}

	if ($snortcfg['enable'] == 'on' && $if_real <> "" && !isvalidpid("{$g['varrun_path']}/snort_{$snort_uuid}.pid")) {

		// Adjust the interface specification and DAQ type according to operating mode
		if ($snortcfg['ips_mode'] == "ips_mode_inline" && $snortcfg['blockoffenders7'] == "on") {
			$iface = $if_real . "^:" . $if_real;
			$daq_type = "-Q --daq netmap";
		}
		else {
			$iface = $if_real;
			$daq_type = "--daq pcap --daq-mode passive --treat-drop-as-alert";
		}

		logger(LOG_NOTICE, localize_text("Snort START for %s(%s)...", convert_real_interface_to_friendly_descr($if_real), $if_real), LOG_PREFIX_PKG_SNORT);
		if ($background)
			mwexec_bg("{$snortbindir}snort -R _{$snort_uuid} -M -D {$quiet} {$daq_type} -l {$snortlogdir}/snort_{$if_real}{$snort_uuid} --pid-path {$g['varrun_path']} --nolock-pidfile --no-interface-pidfile -G {$snort_uuid} -c {$snortdir}/snort_{$snort_uuid}_{$if_real}/snort.conf -i {$iface}");
		else
			mwexec("{$snortbindir}snort -R _{$snort_uuid} -M -D {$quiet} {$daq_type} -l {$snortlogdir}/snort_{$if_real}{$snort_uuid} --pid-path {$g['varrun_path']} --nolock-pidfile --no-interface-pidfile -G {$snort_uuid} -c {$snortdir}/snort_{$snort_uuid}_{$if_real}/snort.conf -i {$iface}");
	}
}

function snort_start_all_interfaces($background=FALSE) {

	/*************************************************************/
	/* This function starts all configured and enabled Snort     */
	/* interfaces.                                               */
	/*************************************************************/
	foreach (config_get_path('installedpackages/snortglobal/rule', []) as $snortcfg) {
		if ($snortcfg['enable'] != 'on' || get_real_interface($snortcfg['interface']) == "")
			continue;
		snort_start($snortcfg, get_real_interface($snortcfg['interface']), $background);
	}
}

function snort_stop_all_interfaces() {

	/*************************************************************/
	/* This function stops all configured Snort interfaces.      */
	/*************************************************************/
	foreach (config_get_path('installedpackages/snortglobal/rule', []) as $snortcfg) {
		snort_stop($snortcfg, get_real_interface($snortcfg['interface']));
	}
}

function snort_restart_all_interfaces() {

	/*************************************************************/
	/* This function stops all configured Snort interfaces and   */
	/* restarts enabled Snort interfaces.                        */
	/*************************************************************/
	snort_stop_all_interfaces();
	sleep(2);
	snort_start_all_interfaces(TRUE);
}

function snort_reload_config($snortcfg, $signal="SIGHUP") {

	/*************************************************************/
	/* This function sends the passed SIGNAL to the Snort        */
	/* instance on the passed interface to cause Snort to        */
	/* reload and parse the running configuration without        */
	/* stopping packet processing.  It also executes the         */
	/* the reload as a background process and returns control    */
	/* immediately to the caller.                                */
	/*                                                           */
	/*  $signal = SIGHUP (default) parses and reloads config.    */
	/*            SIGURG updates Host Attribute Table.           */
	/*************************************************************/
	global $g;

	$snortdir = SNORTDIR;
	$snort_uuid = $snortcfg['uuid'];
	$if_real = get_real_interface($snortcfg['interface']);

	/******************************************************/
	/* Only send the SIGHUP if Snort is running and we    */
	/* can find a valid PID for the process.              */
	/******************************************************/
	if (isvalidpid("{$g['varrun_path']}/snort_{$snort_uuid}.pid")) {
		logger(LOG_NOTICE, localize_text("Snort RELOAD CONFIG for %s(%s)...", convert_real_interface_to_friendly_descr($if_real), $if_real), LOG_PREFIX_PKG_SNORT);
		mwexec_bg("/bin/pkill -{$signal} -F {$g['varrun_path']}/snort_{$snort_uuid}.pid");
	}
}

/*
 this code block is for deleting logs while keeping the newest file,
 snort is linked to these files while running, do not take the easy way out
 by touch and rm, snort will lose sync and not log.
*/
function snort_post_delete_logs($snort_uuid = 0) {
	global $g;

	foreach (config_get_path('installedpackages/snortglobal/rule', []) as $value) {
		if ($value['uuid'] != $snort_uuid)
			continue;
		$if_real = get_real_interface($value['interface']);
		$snort_log_dir = SNORTLOGDIR . "/snort_{$if_real}{$snort_uuid}";

		if ($if_real != '') {
			/* Clean-up rotated unified2 log files if any exist */
			$filelist = glob("{$snort_log_dir}/*{$snort_uuid}_{$if_real}.u2.*");

			/* Skip the most recently rotated log */
			unset($filelist[count($filelist) - 1]);
			foreach ($filelist as $file)
				@unlink($file);

			/* Clean-up packet capture files if any exist */
			unlink_if_exists("{$snort_log_dir}/snort.log.*");

			/* Clean-up stats file if enabled */
			if ($value['perform_stat'] == 'on')
				@file_put_contents("{$snort_log_dir}/{$if_real}.stats", "");
		}
	}
}

/* This returns size of passed directory or file in 1024-byte blocks */
function snort_Getdirsize($node) {
	if(!is_readable($node))
		return false;

	$blah = exec( "/usr/bin/du -kdc $node" );
	return substr( $blah, 0, strpos($blah, chr(9)) );
}

function snort_cron_job_exists($crontask, $match_time=FALSE, $minute="0", $hour="*", $monthday="*", $month="*", $weekday="*", $who="root") {

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

	global $g;

	foreach(config_get_path('cron/item', []) as $item) {
		if(strpos($item['command'], $crontask) !== FALSE) {
			if ($match_time) {
				if ($item['minute'] != $minute)
					return FALSE;
				if ($item['hour'] != $hour)
					return FALSE;
				if ($item['mday'] != $monthday)
					return FALSE;
				if ($item['month'] != $month)
					return FALSE;
				if ($item['wday'] != $weekday)
					return FALSE;
				if ($item['who'] != $who)
					return FALSE;
			}
			return TRUE;
		}
	}
	return FALSE;
}

function snort_snortloglimit_install_cron($should_install=TRUE) {

	// See if simply removing existing "loglimit" job for Snort
	if ($should_install == FALSE) {
		if (snort_cron_job_exists("snort/snort_check_cron_misc.inc", FALSE))
			install_cron_job("snort_check_cron_misc.inc", false);
		return;
	}

	// If there are no changes in the cron job command string from the existing job, then exit.
	if ($should_install && snort_cron_job_exists("/usr/local/pkg/snort/snort_check_cron_misc.inc", TRUE, "*/5"))
		return;

	// Else install the new or updated cron job by removing the
	// existing job first, then installing the new or updated job.
	install_cron_job("snort_check_cron_misc.inc", false);
	install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php -f /usr/local/pkg/snort/snort_check_cron_misc.inc", $should_install, "*/5");
}

function snort_rm_blocked_install_cron($should_install) {
	global $g;

	// See if simply removing existing "expiretable" job for Snort
	if ($should_install == FALSE) {
		if (snort_cron_job_exists("snort2c", FALSE))
			install_cron_job("snort2c", false);
		return;
	}

	// Grab the configured interval from our configuration
	$snort_rm_blocked_info_ck = config_get_path('installedpackages/snortglobal/rm_blocked');

	if ($snort_rm_blocked_info_ck == "15m_b") {
		$snort_rm_blocked_min = "*/2";
		$snort_rm_blocked_hr = "*";
		$snort_rm_blocked_mday = "*";
		$snort_rm_blocked_month = "*";
		$snort_rm_blocked_wday = "*";
		$snort_rm_blocked_expire = "900";
	}
	if ($snort_rm_blocked_info_ck == "30m_b") {
		$snort_rm_blocked_min = "*/5";
		$snort_rm_blocked_hr = "*";
		$snort_rm_blocked_mday = "*";
		$snort_rm_blocked_month = "*";
		$snort_rm_blocked_wday = "*";
		$snort_rm_blocked_expire = "1800";
	}
	if ($snort_rm_blocked_info_ck == "1h_b") {
		$snort_rm_blocked_min = "*/5";
		$snort_rm_blocked_hr = "*";
		$snort_rm_blocked_mday = "*";
		$snort_rm_blocked_month = "*";
		$snort_rm_blocked_wday = "*";
		$snort_rm_blocked_expire = "3600";
	}
	if ($snort_rm_blocked_info_ck == "3h_b") {
		$snort_rm_blocked_min = "*/15";
		$snort_rm_blocked_hr = "*";
		$snort_rm_blocked_mday = "*";
		$snort_rm_blocked_month = "*";
		$snort_rm_blocked_wday = "*";
		$snort_rm_blocked_expire = "10800";
	}
	if ($snort_rm_blocked_info_ck == "6h_b") {
		$snort_rm_blocked_min = "*/30";
		$snort_rm_blocked_hr = "*";
		$snort_rm_blocked_mday = "*";
		$snort_rm_blocked_month = "*";
		$snort_rm_blocked_wday = "*";
		$snort_rm_blocked_expire = "21600";
	}
	if ($snort_rm_blocked_info_ck == "12h_b") {
		$snort_rm_blocked_min = "2";
		$snort_rm_blocked_hr = "*/1";
		$snort_rm_blocked_mday = "*";
		$snort_rm_blocked_month = "*";
		$snort_rm_blocked_wday = "*";
		$snort_rm_blocked_expire = "43200";
	}
	if ($snort_rm_blocked_info_ck == "1d_b") {
		$snort_rm_blocked_min = "2";
		$snort_rm_blocked_hr = "*/2";
		$snort_rm_blocked_mday = "*";
		$snort_rm_blocked_month = "*";
		$snort_rm_blocked_wday = "*";
		$snort_rm_blocked_expire = "86400";
	}
	if ($snort_rm_blocked_info_ck == "4d_b") {
		$snort_rm_blocked_min = "2";
		$snort_rm_blocked_hr = "*/8";
		$snort_rm_blocked_mday = "*";
		$snort_rm_blocked_month = "*";
		$snort_rm_blocked_wday = "*";
		$snort_rm_blocked_expire = "345600";
	}
	if ($snort_rm_blocked_info_ck == "7d_b") {
		$snort_rm_blocked_min = "2";
		$snort_rm_blocked_hr = "*/14";
		$snort_rm_blocked_mday = "*";
		$snort_rm_blocked_month = "*";
		$snort_rm_blocked_wday = "*";
		$snort_rm_blocked_expire = "604800";
	}
	if ($snort_rm_blocked_info_ck == "28d_b") {
		$snort_rm_blocked_min = "2";
		$snort_rm_blocked_hr = "0";
		$snort_rm_blocked_mday = "*/2";
		$snort_rm_blocked_month = "*";
		$snort_rm_blocked_wday = "*";
		$snort_rm_blocked_expire = "2419200";
	}

	// Construct the basic cron command task
	$command = "/usr/bin/nice -n20 /sbin/pfctl -q -t snort2c -T expire {$snort_rm_blocked_expire}";

	// If there are no changes in the cron job command string from the existing job, then exit.
	if (snort_cron_job_exists($command, TRUE, $snort_rm_blocked_min, $snort_rm_blocked_hr, $snort_rm_blocked_mday, $snort_rm_blocked_month, $snort_rm_blocked_wday, "root"))
		return;

	// Else install the new or updated cron job
	if ($should_install) {
		// Remove the existing job first, then install the new or updated job
		install_cron_job("snort2c", false);
		install_cron_job($command, true, $snort_rm_blocked_min, $snort_rm_blocked_hr, $snort_rm_blocked_mday, $snort_rm_blocked_month, $snort_rm_blocked_wday, "root");
	}
}

/* func to install snort update */
function snort_rules_up_install_cron($should_install) {
	global $g;

	// If called with FALSE as argument, then we're removing 
	// the existing job.
	if ($should_install == FALSE) {
		if (snort_cron_job_exists("snort_check_for_rule_updates.php", FALSE))
			install_cron_job("snort_check_for_rule_updates.php", false);
		return;
	}

	// Grab the configured update interval from our configuration
	$snort_rules_up_info_ck = config_get_path('installedpackages/snortglobal/autorulesupdate7');

	/* See if a customized start time has been set for rule file updates */
	if (!empty(config_get_path('installedpackages/snortglobal/rule_update_starttime')))
		$snort_rules_upd_time = config_get_path('installedpackages/snortglobal/rule_update_starttime');
	else
		$snort_rules_upd_time = "00:" . str_pad(strval(random_int(0,59)), 2, "00", STR_PAD_LEFT);

	if ($snort_rules_up_info_ck == "6h_up") {
		$snort_rules_up_min = intval(substr($snort_rules_upd_time, -2));
		$hour = intval(substr($snort_rules_upd_time, 0, 2));
		$snort_rules_up_hr = strval($hour);
		for ($i=0; $i<3; $i++) {
			$hour += 6;
			if ($hour > 23)
				$hour -= 24;
			$snort_rules_up_hr .= "," . strval($hour);
		}
		$snort_rules_up_mday = "*";
		$snort_rules_up_month = "*";
		$snort_rules_up_wday = "*";
	}
	if ($snort_rules_up_info_ck == "12h_up") {
		$snort_rules_up_min = intval(substr($snort_rules_upd_time, -2));
		$hour = intval(substr($snort_rules_upd_time, 0, 2));
		$snort_rules_up_hr = strval($hour) . ",";
		$hour += 12;
		if ($hour > 23)
			$hour -= 24;
		$snort_rules_up_hr .= strval($hour);
		$snort_rules_up_mday = "*";
		$snort_rules_up_month = "*";
		$snort_rules_up_wday = "*";
	}
	if ($snort_rules_up_info_ck == "1d_up") {
		$snort_rules_up_min = intval(substr($snort_rules_upd_time, -2));
		$snort_rules_up_hr = intval(substr($snort_rules_upd_time, 0, 2));
		$snort_rules_up_mday = "*/1";
		$snort_rules_up_month = "*";
		$snort_rules_up_wday = "*";
	}
	if ($snort_rules_up_info_ck == "4d_up") {
		$snort_rules_up_min = intval(substr($snort_rules_upd_time, -2));
		$snort_rules_up_hr = intval(substr($snort_rules_upd_time, 0, 2));
		$snort_rules_up_mday = "*/4";
		$snort_rules_up_month = "*";
		$snort_rules_up_wday = "*";
	}
	if ($snort_rules_up_info_ck == "7d_up") {
		$snort_rules_up_min = intval(substr($snort_rules_upd_time, -2));
		$snort_rules_up_hr = intval(substr($snort_rules_upd_time, 0, 2));
		$snort_rules_up_mday = "*/7";
		$snort_rules_up_month = "*";
		$snort_rules_up_wday = "*";
	}
	if ($snort_rules_up_info_ck == "28d_up") {
		$snort_rules_up_min = intval(substr($snort_rules_upd_time, -2));
		$snort_rules_up_hr = intval(substr($snort_rules_upd_time, 0, 2));
		$snort_rules_up_mday = "*/28";
		$snort_rules_up_month = "*";
		$snort_rules_up_wday = "*";
	}

	// Construct the basic cron command task
	$command = "/usr/bin/nice -n20 /usr/local/bin/php -f /usr/local/pkg/snort/snort_check_for_rule_updates.php";

	// If there are no changes in the cron job command string from the existing job, then exit
	if (snort_cron_job_exists($command, TRUE, $snort_rules_up_min, $snort_rules_up_hr, $snort_rules_up_mday, $snort_rules_up_month, $snort_rules_up_wday, "root"))
		return;

	// Else install the new or updated cron job
	if ($should_install) {
		// Remove the existing job first, then install the new or updated job
		install_cron_job("snort_check_for_rule_updates.php", false);
		install_cron_job($command, $should_install, $snort_rules_up_min, $snort_rules_up_hr, $snort_rules_up_mday, $snort_rules_up_month, $snort_rules_up_wday, "root");
	}
}

/* Only run when all ifaces needed to sync */
function sync_snort_package_config() {
	global $g;
	global $rebuild_rules;

	$snortdir = SNORTDIR;
	$rcdir = RCFILEPREFIX;

	/* Create required log and db directories in /var on each sync, in case /var is in RAM. */
	safe_mkdir(SNORTLOGDIR);
	safe_mkdir(SNORT_IPREP_PATH);
	safe_mkdir(SNORT_SID_MODS_PATH);
	safe_mkdir(SNORT_APPID_ODP_PATH);

	/* Iterate configured interfaces and generate a snort.conf for each */
	foreach (config_get_path('installedpackages/snortglobal/rule', []) as $value) {
		/* Skip configuration of any disabled instance or */
		/* an instance whose assigned physical interface  */
		/* has been removed.                              */
		if (($value['enable'] != 'on') || (get_real_interface($value['interface']) == ""))
			continue;

		/* create a snort.conf file for interface */
		snort_generate_conf($value);
	}

	/* create snort bootup file snort.sh */
	snort_create_rc();

	snort_snortloglimit_install_cron(true);

	/* set the snort block hosts time IMPORTANT */
	snort_rm_blocked_install_cron(config_get_path('installedpackages/snortglobal/rm_blocked') != "never_b" ? true : false);

	/* set the snort rules update time */
	snort_rules_up_install_cron(config_get_path('installedpackages/snortglobal/autorulesupdate7') != "never_up" ? true : false);

	/* Do not attempt package sync if reinstalling package or booting */
	if (!$g['snort_postinstall'] && !is_platform_booting())
		snort_sync_on_changes();
}

function snort_build_sid_msg_map($rules_path, $sid_file) {

	/*************************************************************/
	/* This function reads all the rules file in the passed      */
	/* $rules_path variable and produces a properly formatted    */
	/* sid-msg.map v2 file for use by Snort.                     */
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

	/* First check if we were passed a directory, a single file  */
	/* or an array of filenames to read. Set our $rule_files     */
	/* variable accordingly. If we can't figure it out, return   */
	/* and don't write a sid-msg.map file.                       */
	if (is_string($rules_path)) {
		if (is_dir($rules_path))
			$rule_files = glob($rules_path . "*.rules");
		elseif (is_file($rules_path))
			$rule_files = (array)$rules_path;
	}
	elseif (is_array($rules_path))
		$rule_files = $rules_path;
	else
		return;

	/* Read the rule files into an array, then iterate the list */
	foreach ($rule_files as $file) {

		/* Don't process files with "deleted" in the filename */
		if (stristr($file, "deleted"))
			continue;

		/* Read the file into an array, skipping missing files. */
		if (!file_exists($file))
			continue;

		$rules_array = file($file, FILE_SKIP_EMPTY_LINES);
		$record = "";
		$b_Multiline = false;

		/* Read and process each line from the rules in the current file */
		foreach ($rules_array as $rule) {

			/* Skip any non-rule lines unless we're in multiline mode. */
			if (!preg_match('/^\s*#*\s*(alert|drop|pass)/i', $rule) && !$b_Multiline)
				continue;

			/* Test for a multi-line rule, and reassemble the  */
			/* pieces back into a single line.                 */
			if (preg_match('/\\\\s*[\n]$/m', $rule)) {
				$rule = substr($rule, 0, strrpos($rule, '\\'));
				$record .= $rule;
				$b_Multiline = true;
				continue;
			}
			/* If the last segment of a multiline rule, then   */
			/* append it onto the previous parts to form a     */
			/* single-line rule for further processing below.  */
			elseif (!preg_match('/\\\\s*[\n]$/m', $rule) && $b_Multiline) {
				$record .= $rule;
				$rule = $record;
			}
			$b_Multiline = false;
			$record = "";

			/* Parse the rule to find sid and any references.  */
			$gid = '1';             // default to 1 for regular rules
			$sid = '';
			$rev = '';
			$classtype = 'NOCLASS'; // required default for v2 format
			$priority = '0';        // required default for v2 format
			$msg = '';
			$matches = '';
			$sidEntry = '';
			if (preg_match('/\bmsg\s*:\s*"(.+?)"\s*;/i', $rule, $matches))
				$msg = trim($matches[1]);
			if (preg_match('/\bsid\s*:\s*(\d+)\s*;/i', $rule, $matches))
				$sid = trim($matches[1]);
			if (preg_match('/\bgid\s*:\s*(\d+)\s*;/i', $rule, $matches))
				$gid = trim($matches[1]);
			if (preg_match('/\brev\s*:\s*([^\;]+)/i', $rule, $matches))
				$rev = trim($matches[1]);
			if (preg_match('/\bclasstype\s*:\s*([^\;]+)/i', $rule, $matches))
				$classtype = trim($matches[1]);
			if (preg_match('/\bpriority\s*:\s*([^\;]+)/i', $rule, $matches))
				$priority = trim($matches[1]);

			if (!empty($gid) && !empty($sid) && !empty($msg)) {
				$sidEntry = $gid . ' || ' . $sid . ' || ' . $rev . ' || ' . $classtype . ' || ';
				$sidEntry .= $priority . ' || ' . $msg;
				preg_match_all('/\breference\s*:\s*([^\;]+)/i', $rule, $matches);
				foreach ($matches[1] as $ref)
					$sidEntry .= " || " . trim($ref);
				$sidEntry .= "\n";
				$sidMap[] = $sidEntry;
			}
		}
        }
	/* Sort the generated sid-msg map */
	natcasesort($sidMap);

	/* Now print the result to the supplied file */
	@file_put_contents($sid_file, "#v2\n# sid-msg.map file auto-generated by Snort.\n\n");
	@file_put_contents($sid_file, array_values($sidMap), FILE_APPEND);
}

function snort_merge_reference_configs($cfg_in, $cfg_out) {

	/***********************************************************/
	/* This function takes a list of "reference.config" files  */
	/* in the $cfg_in array and merges them into a single      */
	/* file specified by $cfg_out.  The merging is done so     */
	/* no duplication of lines occurs in the output file.      */
	/***********************************************************/

        $outMap = array();
        foreach ($cfg_in as $file) {
	     if (!file_exists($file))
			continue;
                $in = file($file, FILE_SKIP_EMPTY_LINES);
                foreach ($in as $line) {
                        /* Skip comment lines  */
                        if (preg_match('/^\s*#/', $line))
                                continue;
                        if (preg_match('/(\:)\s*(\w+)\s*(.*)/', $line, $matches)) {
                                if (!empty($matches[2]) && !empty($matches[3])) {
                                        $matches[2] = trim($matches[2]);
                                        if (!array_key_exists($matches[2], $outMap)) {
						if (!is_array($outMap[$matches[2]]))
							$outMap[$matches[2]] = array();
                                                $outMap[$matches[2]] = trim($matches[3]);
					}
                                }
                        }
                }             
        }
        /* Sort the new reference map.  */
        uksort($outMap,'strnatcasecmp');

	/**********************************************************/
	/* Do NOT write an empty references.config file, just     */
	/* exit instead.                                          */
	/**********************************************************/
	if (empty($outMap))
		return false;

        /* Format and write it to the supplied output file.  */
        $format = "config reference: %-12s %s\n";
        foreach ($outMap as $key=>$value)
                $outMap[$key] = sprintf($format, $key, $value);
        @file_put_contents($cfg_out, array_values($outMap));
	return true;
}

function snort_merge_classification_configs($cfg_in, $cfg_out) {

	/************************************************************/
	/* This function takes a list of "classification.config"    */
	/* files in the $cfg_in array and merges them into a        */
	/* single file specified by $cfg_out.  The merging is done  */
	/* so no duplication of lines occurs in the output file.    */
	/************************************************************/

        $outMap = array();
        foreach ($cfg_in as $file) {
	     if (!file_exists($file))
			continue;
                $in = file($file, FILE_SKIP_EMPTY_LINES);
                foreach ($in as $line) {
                        if (preg_match('/(.*:)(\s*.*),(.*),(.*)/', $line, $matches)) {
                                /* Skip comment lines  */
                                if (preg_match('/^\s*#/', $line))
                                        continue;
                                if (!empty($matches[2]) && !empty($matches[3]) && !empty($matches[4])) {
                                        $matches[2] = trim($matches[2]);
                                        if (!array_key_exists($matches[2], $outMap)) {
						if (!is_array($outMap[$matches[2]]))
							$outMap[$matches[2]] = array();
                                                $outMap[$matches[2]] = trim($matches[3]) . "," . trim($matches[4]);
					}
                                }
                        }
                }             
        }
        /* Sort the new classification map.  */
        uksort($outMap,'strnatcasecmp');

	/**********************************************************/
	/* Do NOT write an empty classification.config file, just */
	/* exit instead.                                          */
	/**********************************************************/
	if (empty($outMap))
		return false;

        /* Format and write it to the supplied output file.  */
        $format = "config classification: %s,%s\n";
        foreach ($outMap as $key=>$value)
                $outMap[$key] = sprintf($format, $key, $value);
        @file_put_contents($cfg_out, array_values($outMap));
	return true;
}

function snort_load_rules_map($rules_path) {

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

	if (empty($rules_path))
		return $map_ref;

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
	 *   noalert        = 1 if rule contains "noalert" or "flowbits:noalert" options
	 *                    0 if not
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

	/* First check if we were passed a directory, a single file   */
	/* or an array of filenames to read. Set our $rule_files      */
	/* variable accordingly. If we can't figure it out, return    */
	/* an empty rules map array.                                  */
	if (is_string($rules_path)) {
		if (is_dir($rules_path))
			$rule_files = glob($rules_path . "*.rules");
		elseif (is_file($rules_path))
			$rule_files = (array)$rules_path;
	}
	elseif (is_array($rules_path))
		$rule_files = $rules_path;
	else
		return $map_ref;

        /* Read the rule files into an array, then iterate the list   */
	/* to process the rules from the files one-by-one.            */
	foreach ($rule_files as $file) {

		/* Don't process files with "deleted" in the filename. */
		if (stristr($file, "deleted"))
			continue;

		/* Read the file contents into an array, skipping    */
		/* missing files.                                    */
		if (!file_exists($file))
			continue;

		$rules_array = file($file, FILE_SKIP_EMPTY_LINES);
		$record = "";
		$b_Multiline = false;

		/* Read and process each line from the rules in the */
		/* current file into an array.                      */
		foreach ($rules_array as $rule) {

			/* Skip any lines that may be just spaces.  */
			if (trim($rule, " \n") == "")
				continue;

			/* Skip any non-rule lines unless we're in  */
			/* multiline mode.			    */
			if (!preg_match('/^\s*#*\s*(alert|log|pass|drop|reject|sdrop)/i', $rule) && !$b_Multiline)
				continue;

			/* Test for a multi-line rule; loop and reassemble */
			/* the pieces back into a single line.             */
			if (preg_match('/\\\\s*[\n]$/m', $rule)) {
				$rule = substr($rule, 0, strrpos($rule, '\\'));
				$record .= $rule;
				$b_Multiline = true;
				continue;
			}
			/* If the last segment of a multiline rule, then   */
			/* append it onto the previous parts to form a     */
			/* single-line rule for further processing below.  */
			elseif (!preg_match('/\\\\s*[\n]$/m', $rule) && $b_Multiline) {
				$record .= $rule;
				$rule = $record;
			}

			/* We have an actual single-line rule, or else a   */
			/* re-assembled multiline rule that is now a       */
			/* single-line rule, so store it in our rules map. */

			/* Get and test the SID.  If we don't find one,    */
			/* ignore and skip this rule as it is invalid.     */
			$sid = snort_get_sid($rule);
			if (empty($sid)) {
				$b_Multiline = false;
				$record = "";
				continue;
			}

			$gid = snort_get_gid($rule);
			if (!is_array($map_ref[$gid]))
				$map_ref[$gid] = array();
			if (!is_array($map_ref[$gid][$sid]))
				$map_ref[$gid][$sid] = array();
			$map_ref[$gid][$sid]['rule'] = $rule;
			$map_ref[$gid][$sid]['category'] = basename($file, ".rules");
			$map_ref[$gid][$sid]['state_toggled'] = 0;
			$map_ref[$gid][$sid]['modified'] = 0;
			$map_ref[$gid][$sid]['managed'] = 0;

			if (preg_match('/^\s*\#+/', $rule))
				$map_ref[$gid][$sid]['disabled'] = 1;
			else
				$map_ref[$gid][$sid]['disabled'] = 0;

			// Check for "noalert;" rule option
			if (strpos($rule, 'noalert;') !== FALSE) {
				$map_ref[$gid][$sid]['noalert'] = 1;
			} else {
				$map_ref[$gid][$sid]['noalert'] = 0;
			}				


			/* Grab the rule action (this is for a future option) */
			$matches = array();
			if (preg_match('/^\s*#*\s*(alert|log|pass|drop|reject|sdrop)/i', $rule, $matches)) {
				$map_ref[$gid][$sid]['action'] = $matches[1];
				$map_ref[$gid][$sid]['default_action'] = $matches[1];
			}
			else {
				$map_ref[$gid][$sid]['action'] = "";
			}

			// Determine if default state is "disabled"
			// or "enabled".
			if (preg_match('/^\s*\#+/', $rule)) {
				$map_ref[$gid][$sid]['disabled'] = 1;
				$map_ref[$gid][$sid]['default_state'] = 0;
			} else {
				$map_ref[$gid][$sid]['disabled'] = 0;
				$map_ref[$gid][$sid]['default_state'] = 1;
			}

			/* Grab any associated flowbits from the rule.     */
			$map_ref[$gid][$sid]['flowbits'] = snort_get_flowbits($rule);
			
			/* Reset our local flag and record variables       */
			/* for the next rule in the set.                   */
			$b_Multiline = false;
			$record = "";
		}

		/* Zero out our processing array and get the next file.    */
		unset($rules_array);
	}
	return $map_ref;
}

function snort_get_gid($rule) {

	/****************************************************************/
	/* If a gid is defined, then return it, else default to "1" for */
	/* general text rules match.                                    */
	/****************************************************************/

	if (preg_match('/\bgid\s*:\s*(\d+)\s*;/i', $rule, $matches))
		return trim($matches[1]);
	else
		return "1";
}

function snort_get_sid($rule) {

	/***************************************************************/
	/* If a sid is defined, then return it, else default to an     */
	/* empty value.                                                */
	/***************************************************************/

	if (preg_match('/\bsid\s*:\s*(\d+)\s*;/i', $rule, $matches))
		return trim($matches[1]);
	else
		return "";
}

function snort_get_msg($rule) {

	/**************************************************************/
	/* Return the MSG section of the passed rule as a string.     */
	/**************************************************************/

	$msg = "";
	if (preg_match('/\bmsg\s*:\s*"(.+?)"\s*;/i', $rule, $matches))
		$msg = trim($matches[1]);
	return $msg;
}

function snort_get_flowbits($rule) {

	/*************************************************************/
	/* This will pull out "flowbits:" options from the rule text */
	/* and return them in an array (minus the "flowbits:" part). */
	/*************************************************************/

	$flowbits = array();

	/* Grab any "flowbits:set, setx, unset, isset or toggle" options first.  */
	/* Examine flowbits targets for logical operators to capture all targets */ 
	if (preg_match_all('/flowbits\b\s*:\s*(set|setx|unset|toggle|isset|isnotset)\s*,([^;]+)/i', $rule, $matches)) {
		$i = -1;
		while (++$i < count($matches[1])) {
			$action = trim($matches[1][$i]);
			$target = preg_split('/[&|]/', $matches[2][$i]);
			foreach ($target as $t)
				$flowbits[] = "{$action}," . trim($t);
		}
	}

	/* Include the "flowbits:noalert or reset" options, if present. */
	if (preg_match_all('/flowbits\b\s*:\s*(noalert|reset)\b/i', $rule, $matches)) {
		$i = -1;
		while (++$i < count($matches[1])) {
			$flowbits[] = trim($matches[1][$i]);
		}
	}

	return $flowbits;
}

function snort_get_checked_flowbits($rules_map) {

	/*************************************************************/
	/* This function checks all the currently enabled rules to   */
	/* find any checked flowbits, and returns the checked        */
	/* flowbit names in an array.                                */
	/*************************************************************/

	$checked_flowbits = array();
	foreach ($rules_map as $rulem) {
		if (!is_array($rulem))
			continue;
		foreach ($rulem as $rulem2) {
			if (!is_array($rulem2))
				continue;
			if ($rulem2['disabled'] == 1)
				continue;
			if (empty($rulem2['flowbits']))
				continue;
			if (!is_array($rulem2['flowbits']))
				continue;
			foreach ($rulem2['flowbits'] as $flowbit) {
				if (empty($flowbit))
					continue;
				/* If no comma in flowbits option, then  skip it. */
				$pos = strpos($flowbit, ",");
				if ($pos === false)
					continue;
				$action = substr(strtolower($flowbit), 0, $pos);
				if ($action == "isset" || $action == "isnotset") {
					$target = preg_split('/[&|]/', substr($flowbit, $pos + 1));
					foreach ($target as $t)
						if (!empty($t) && !isset($checked_flowbits[$t])) {
							if (!is_array($checked_flowbits[$t]))
								$checked_flowbits[$t] = array();
							$checked_flowbits[$t] = $action;
						}
				}
			}
		}
	}
	unset($rulem, $rulem2);

	return $checked_flowbits;
}

function snort_get_set_flowbits($rules_map) {

	/*********************************************************/
	/* This function checks all the currently enabled rules  */
	/* to find any set flowbits, and returns the flowbit     */
	/* names in an array.                                    */
	/*********************************************************/

	$set_flowbits = array();
	foreach ($rules_map as $rulem) {
		if (!is_array($rulem))
			continue;
		foreach ($rulem as $rulem2) {
			if ($rulem2['disabled'] == 1)
				continue;
			if (empty($rulem2['flowbits']))
				continue;
			if (!is_array($rulem2['flowbits']))
				continue;
			foreach ($rulem2['flowbits'] as $flowbit) {
				if (empty($flowbit))
					continue;
				/* If no comma in flowbits option, then  skip it. */
				$pos = strpos($flowbit, ",");
				if ($pos === false)
					continue;
				$action = substr(strtolower($flowbit), 0, $pos);
				if ($action == "set" || $action == "toggle" || $action == "setx") {
					$target = preg_split('/[&|]/', substr($flowbit, $pos + 1));
					foreach ($target as $t)
						if (!empty($t) && !isset($set_flowbits[$t])) {
							if (!is_array($set_flowbits[$t]))
								$set_flowbits[$t] = array();
							$set_flowbits[$t] = $action;
						}
				}
			}
		}
	}
	unset($rulem, $rulem2);

	return $set_flowbits;
}

function snort_find_flowbit_required_rules($rules, $active_rules, $unchecked_flowbits) {

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
		if (!is_array($rule))
			continue;
		foreach ($rule as $k2 => $rule2) {
			if (empty($rule2['flowbits']))
				continue;
			if (!is_array($rule2['flowbits']))
				continue;
			foreach ($rule2['flowbits'] as $flowbit) {
				if (empty($flowbit))
					continue;
				$action = substr($flowbit, 0, strpos($flowbit, ","));
				if (!strcasecmp(substr($action, 0, 3), "set")) {
					$tmp = substr($flowbit, strpos($flowbit, ",") +1 );
					if (!empty($tmp) && isset($unchecked_flowbits[$tmp])) {
						if (!is_array($required_flowbits_rules[$k1]))
							$required_flowbits_rules[$k1] = array();
						if (!is_array($required_flowbits_rules[$k1][$k2]))
							$required_flowbits_rules[$k1][$k2] = array();
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

function snort_resolve_flowbits($rules, $active_rules) {

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

	$snortdir = SNORTDIR;

	/* Check $rules array to be sure it is filled.    */
	if (empty($rules)) {
		logger(LOG_WARNING, localize_text("Flowbit resolution not done - no rules in %s ...", "{$snortdir}/rules/"), LOG_PREFIX_PKG_SNORT);
		return array();
	}

	/* First, find all the "checked" and "set" flowbits.  */
	$checked_flowbits = snort_get_checked_flowbits($active_rules);
	$set_flowbits = snort_get_set_flowbits($active_rules);

	/* Next find any "checked" flowbits without matching  */
	/* "set" flowbit rules in the enabled rule set.       */
	$delta_flowbits = array_diff_key($checked_flowbits, $set_flowbits);

	/* Cleanup and release the memory we no longer need.  */
	unset($checked_flowbits);
	unset($set_flowbits);

	/* Now find all the needed "set flowbit" rules from   */
	/* the master list of all rules.                      */
	$required_rules = snort_find_flowbit_required_rules($rules, $active_rules, $delta_flowbits);

	/* Cleanup and release memory we no longer need.      */
	unset($delta_flowbits);

	return $required_rules;
}

function snort_write_flowbit_rules_file($flowbit_rules, $rule_file) {

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

	/* See if we were passed a directory or full    */
	/* filename to write the rules to, and adjust   */
	/* the destination argument accordingly.        */
	if (is_dir($rule_file))
		$rule_file = rtrim($rule_file, '/')."/{$flowbit_rules_file}";

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

function snort_load_vrt_policy($policy, $mode='alert', $all_rules=null) {

	/************************************************/
	/* This function returns an array of all rules  */
	/* marked with the passed in $policy metadata.  */
	/*                                              */
	/*    $policy --> desired Snort security policy */
	/*                  1.  connectivity            */
	/*                  2.  balanced                */
	/*                  3.  security                */
	/*                  4.  max-detect              */
	/*                                              */
	/*      $mode --> determines rule action        */
	/*                  1. alert = all rules alert  */
	/*                  2. policy = rule action     */
	/*                              set according   */
	/*                              policy spec.    */
	/*                                              */
	/* $all_rules --> optional Rules Map array of   */
	/*                rules to scan for policy.     */
	/*                If not provided, then an      */
	/*                array will be created.        */
	/************************************************/

	$snortdir = SNORTDIR;
	$vrt_policy_rules = array();

	/* Load a map of all the Snort rules if we were */
	/* not passed a pre-loaded one to use.          */
	if (is_null($all_rules)) {
		/* Since only Snort Subscriber rules have IPS Policy */
		/* metadata, limit our search to just those files.   */
		$snort_vrt_files = glob("{$snortdir}/rules/snort_*.rules");
		$all_rules = snort_load_rules_map($snort_vrt_files);
	}

	/* Now walk the rules list and find all those that are */
	/* defined as active for the chosen security policy.   */
	foreach ($all_rules as $k1 => $arulem) {
		foreach ($arulem as $k2 => $arulem2) {
			if (strripos($arulem2['rule'], "policy {$policy}-ips") !== false) {
				if (!preg_match('/flowbits\s*:\s*noalert/i', $arulem2['rule'])) {
					if (!is_array($vrt_policy_rules[$k1]))
						$vrt_policy_rules[$k1] = array();
					if (!is_array($vrt_policy_rules[$k1][$k2]))
						$vrt_policy_rules[$k1][$k2] = array();
					$vrt_policy_rules[$k1][$k2] = $arulem2;

					/* Enable the policy rule if disabled  */
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

	/* Release memory we no longer need. */
	unset($arulem, $arulem2);

	/* Return all the rules that match the policy. */
	return $vrt_policy_rules;
}

function snort_write_enforcing_rules_file($rule_map, $rule_path) {

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

	$rule_file = "/" . SNORT_ENFORCING_RULES_FILENAME;

	/* See if we were passed a directory or full    */
	/* filename to write the rules to, and adjust   */
	/* the destination argument accordingly.        */
	if (is_dir($rule_path))
		$rule_file = rtrim($rule_path, '/').$rule_file;
	else
		$rule_file = $rule_path;

	/* If the $rule_map array is empty, then exit.  */
	if (empty($rule_map)) {
		@file_put_contents($rule_file, "");
		return;
	}

	$fp = fopen($rule_file, "w");
	if ($fp) {
		@fwrite($fp, "# These rules are your current set of enforced rules for the protected\n");
		@fwrite($fp, "# interface.  This list was compiled from the categories selected on the\n");
		@fwrite($fp, "# CATEGORIES tab of the Snort configuration for the interface and/or any\n");
		@fwrite($fp, "# chosen Snort Subscriber Rules pre-defined IPS Policy.\n#\n");
		@fwrite($fp, "# Any enablesid or disablesid customizations you made have been applied\n");
		@fwrite($fp, "# to the rules in this file.\n\n");
		foreach ($rule_map as $rulem) {
			foreach ($rulem as $rulem2) {
				/* No reason to write disabled rules to enforcing file, so skip them. */
				if ($rulem2['disabled'] == 1)
					continue;
				@fwrite($fp, $rulem2['rule']);
			}
		}
		fclose($fp);
	}
}

function snort_parse_sidconf_file($sidconf_file,$split_lines=TRUE) {

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
	foreach(config_get_path('installedpackages/snortglobal/sid_mgmt_lists/item', []) as $item) {
		if ($item['name'] == $sidconf_file) {
			$list = $item;
			break;
		}
	}

	// Decode the list contents into a PHP temp buffer
	// we can read like a normal file.
	$fd = fopen("php://temp", "r+");
	if ($fd == FALSE) {
		logger(LOG_ERR, localize_text("Failed to open SID MGMT list '%s' for processing.", $sidconf_file), LOG_PREFIX_PKG_SNORT);
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

function snort_sid_mgmt_list_exist($sid_mgmt_list) {

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

	foreach(config_get_path('installedpackages/snortglobal/sid_mgmt_lists/item', []) as $list) {
		if ($list['name'] == $sid_mgmt_list) {
			return TRUE;
		}
	}
	return FALSE;
}

function snort_process_dropsid(&$rule_map, $snortcfg, $log_results = FALSE, $log_file = NULL) {

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
	/*  $snortcfg ==> interface config params     */
	/*  $log_results ==> [optional] 'yes' to log  */
	/*                   results to $log_file     */
	/*     $log_file ==> full path and filename   */
	/*                   of log file to write to  */
	/*                                            */
	/*     On Return ==> suitably modified        */
	/*                   $rule_map array          */
	/**********************************************/

	$snortlogdir = SNORTLOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map)) {
		return;
	}

	// Verify the 'drop_sid' list for the interface exists
	if (!snort_sid_mgmt_list_exist($snortcfg['drop_sid_file'])) {
		logger(LOG_ERR, localize_text("unable to find drop_sid list \"%s\" specified for %s", $snortcfg['drop_sid_file'], convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
		return;
	} else {
		$sid_mods = snort_parse_sidconf_file($snortcfg['drop_sid_file']);
	}

	if (!empty($sid_mods)) {
		snort_modify_sid_state($rule_map, $sid_mods, "drop", $log_results, $log_file);
	} elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in list \"{$snortcfg['drop_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function snort_process_rejectsid(&$rule_map, $snortcfg, $log_results = FALSE, $log_file = NULL) {

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
	/*  $snortcfg ==> interface config params     */
	/*  $log_results ==> [optional] 'yes' to log  */
	/*                   results to $log_file     */
	/*     $log_file ==> full path and filename   */
	/*                   of log file to write to  */
	/*                                            */
	/*     On Return ==> suitably modified        */
	/*                   $rule_map array          */
	/**********************************************/

	$snortlogdir = SNORTLOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map)) {
		return;
	}

	// Verify the 'reject_sid' list for the interface exists
	if (!snort_sid_mgmt_list_exist($snortcfg['reject_sid_file'])) {
		logger(LOG_ERR, localize_text("unable to find reject_sid list \"%s\" specified for %s", $snortcfg['reject_sid_file'], convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
		return;
	} else {
		$sid_mods = snort_parse_sidconf_file($snortcfg['reject_sid_file']);
	}

	if (!empty($sid_mods)) {
		snort_modify_sid_state($rule_map, $sid_mods, "reject", $log_results, $log_file);
	} elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in list \"{$snortcfg['reject_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function snort_sid_mgmt_auto_categories($snortcfg, $log_results = FALSE) {

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
	/*  $snortcfg    ==> pointer to interface           */
	/*                   configuration info             */
	/*  $log_results ==> [optional] log results to      */
	/*                   'sid_changes.log' in the       */
	/*                   interface directory in         */
	/*                   /var/log/snort when TRUE       */
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

	// Check if auto-mgmt of SIDs is enabled, exit if not
	if (config_get_path('installedpackages/snortglobal/auto_manage_sids') != 'on')
		return array();
	if (empty($snortcfg['disable_sid_file']) && empty($snortcfg['enable_sid_file']))
		return array();

	// Configure the interface's logging subdirectory if log results is enabled
	if ($log_results == TRUE)
		$log_file = SNORTLOGDIR . "/snort_" . get_real_interface($snortcfg['interface']) . "{$snortcfg['uuid']}/sid_changes.log";
	else
		$log_file = NULL;

	// Get the list of currently enabled categories for the interface
	$rulesets = "";
	if (!empty($snortcfg['rulesets'])) {
		$rulesets = $snortcfg['rulesets'];
	}

	/* If an IPS Policy is enabled, then it will select which  */
	/* built-in rules get used. Otherwise, include them.       */
	if ($snortcfg['ips_policy_enable'] != 'on' || empty($snortcfg['ips_policy'])) {
		$rulesets .= "||decoder.rules||preprocessor.rules||sensitive-data.rules";
	}
	$enabled_cats = explode("||", $rulesets);

	if ($log_results == TRUE) {
		error_log(gettext("********************************************************\n"), 3, $log_file);
		error_log(gettext("Starting auto RULE CATEGORY management for " . convert_friendly_interface_to_friendly_descr($snortcfg['interface']) ."\n"), 3, $log_file);
		error_log(gettext("Start Time: " . date("Y-m-d H:i:s") . "\n"), 3, $log_file);
	}

	switch ($snortcfg['sid_state_order']) {
		case "disable_enable":
			if (!empty($snortcfg['disable_sid_file'])) {
				if ($log_results == TRUE)
					error_log(gettext("Processing disable_sid list: {$snortcfg['disable_sid_file']}\n"), 3, $log_file);

				// Attempt to open the 'disable_sid_file' for the interface
				// Verify the assigned SID Mgmt List still exists in the firewall configuration
				if (!snort_sid_mgmt_list_exist($snortcfg['disable_sid_file'])) {
					logger(LOG_ERR, localize_text("unable to open disable_sid list \"%s\" specified for %s", $snortcfg['disable_sid_file'], convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
					if ($log_results == TRUE) {
						error_log(gettext("Unable to find disable_sid list \"{$snortcfg['disable_sid_file']}\".\n"), 3, $log_file);
					}
				} else {
					$sid_mods = snort_parse_sidconf_file($snortcfg['disable_sid_file']);
				}

				if (!empty($sid_mods))
					$disables = snort_get_auto_category_mods($enabled_cats, $sid_mods, "disable", $log_results, $log_file);
				elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in list \"{$snortcfg['disable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			if (!empty($snortcfg['enable_sid_file'])) {
				if ($log_results == TRUE)
					error_log(gettext("Processing enable_sid list: {$snortcfg['enable_sid_file']}\n"), 3, $log_file);

				// Attempt to open the 'enable_sid_file' for the interface
				if (!snort_sid_mgmt_list_exist($snortcfg['enable_sid_file'])) {
					logger(LOG_ERR, localize_text("unable to open enable_sid list \"%s\" specified for %s", $snortcfg['enable_sid_file'], convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
					if ($log_results == TRUE) {
						error_log(gettext("Unable to find enable_sid list \"{$snortcfg['enable_sid_file']}\".\n"), 3, $log_file);
					}
				} else {
					$sid_mods = snort_parse_sidconf_file($snortcfg['enable_sid_file']);
				}

				if (!empty($sid_mods)) {
					$enables = snort_get_auto_category_mods($enabled_cats, $sid_mods, "enable", $log_results, $log_file);
				} elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in list \"{$snortcfg['enable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			break;

		case "enable_disable":
			if (!empty($snortcfg['enable_sid_file'])) {
				if ($log_results == TRUE)
					error_log(gettext("Processing enable_sid list: {$snortcfg['enable_sid_file']}\n"), 3, $log_file);

				// Attempt to open the 'enable_sid_file' for the interface
				if (!snort_sid_mgmt_list_exist($snortcfg['enable_sid_file'])) {
					logger(LOG_ERR, localize_text("unable to find enable_sid list \"%s\" specified for %s", $snortcfg['enable_sid_file'], convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
					if ($log_results == TRUE) {
						error_log(gettext("Unable to open enable_sid list \"{$snortcfg['enable_sid_file']}\".\n"), 3, $log_file);
					}
				} else {
					$sid_mods = snort_parse_sidconf_file($snortcfg['enable_sid_file']);
				}

				if (!empty($sid_mods)) {
					$enables = snort_get_auto_category_mods($enabled_cats, $sid_mods, "enable", $log_results, $log_file);
				} elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in list \"{$snortcfg['enable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			if (!empty($snortcfg['disable_sid_file'])) {
				if ($log_results == TRUE) {
					error_log(gettext("Processing disable_sid list: {$snortcfg['disable_sid_file']}\n"), 3, $log_file);
				}

				// Attempt to open the 'disable_sid_file' for the interface
				if (!snort_sid_mgmt_list_exist($snortcfg['disable_sid_file'])) {
					logger(LOG_ERR, localize_text("unable to open disable_sid list \"%s\" specified for %s", $snortcfg['disable_sid_file'], convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
					if ($log_results == TRUE) {
						error_log(gettext("Unable to find disable_sid list \"{$snortcfg['disable_sid_file']}\".\n"), 3, $log_file);
					}
				}
				else {
					$sid_mods = snort_parse_sidconf_file($snortcfg['disable_sid_file']);
				}

				if (!empty($sid_mods)) {
					$disables = snort_get_auto_category_mods($enabled_cats, $sid_mods, "disable", $log_results, $log_file);
				} elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in list \"{$snortcfg['disable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			break;

		default:
			logger(LOG_ALERT, localize_text("Unrecognized '%s' value.  Skipping auto CATEGORY mgmt step for %s", 'sid_state_order', convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
			if ($log_results == TRUE) {
				error_log(gettext("ERROR: unrecognized 'sid_state_order' value.  Skipping auto CATEGORY mgmt step for ") . convert_friendly_interface_to_friendly_descr($snortcfg['interface']). ".\n", 3, $log_file);
			}
	}

	if ($log_results == TRUE) {
		error_log(gettext("End Time: " . date("Y-m-d H:i:s") . "\n"), 3, $log_file);
		error_log(gettext("********************************************************\n\n"), 3, $log_file);
	}

	// Return the required rule category modifications as an array;
	return array_merge($enables, $disables);
}

function snort_get_auto_category_mods($categories, $sid_mods, $action, $log_results = FALSE, $log_file = NULL) {

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

	$snortdir = SNORTDIR;
	$all_cats = array();
	$changes = array();
	$counter = 0;
	$matchcount = 0;

	// Get a list of all possible categories by loading all rules files
	$files = glob("{$snortdir}/rules/*.rules");
	foreach ($files as $file)
		$all_cats[] = basename($file);

	// Add the built-in rules files to list of possible categories
	$all_cats[] = "decoder.rules";
	$all_cats[] = "preprocessor.rules";
	$all_cats[] = "sensitive-data.rules";

	// Walk the SID mod tokens and decode looking for rule
	// category enable/disable changes.
	foreach ($sid_mods as $tok) {
		$matches = array();
		// Test the SID token for a GID:SID range and skip if true
		if (preg_match('/^(\d+):(\d+)-\1:(\d+)/', $tok))
			continue;
		// Test the token for a single GID:SID and skip if true
		elseif (preg_match('/^(\d+):(\d+)$/', $tok))
			continue;
		// Test the token for the PCRE: keyword and skip if true
		elseif (preg_match('/(^pcre\:)(.+)/i', $tok))
			continue;
		// Test the token for the MS reference keyword and skip if true
		elseif (preg_match('/^MS\d+-.+/i', $tok))
			continue;
		// Test the token for other keywords delimited with a colon and skip if true
		elseif (preg_match('/^[a-xA-X]+\:.+/', $tok))
			continue;
		// Test the SID token for a rule category name.  Anything that
		// failed to match above is considered a potential category name.
		elseif (preg_match('/[a-xA-X]+(-|\w).*/', $tok, $matches)) {
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
							if ($log_results == TRUE && !empty($log_file))
								error_log(gettext("    Enabled rule category: {$cat}\n"), 3, $log_file);
							$matchcount++;
						}
						break;

					case 'disable':
						if (!isset($changes[$cat])) {
							$changes[$cat] = 'disabled';
							if ($log_results == TRUE && !empty($log_file))
								error_log(gettext("    Disabled rule category: {$cat}\n"), 3, $log_file);
							$matchcount++;
						}
						break;

					default:
						break;
				}
			}
		}
		else {
			if ($log_results == TRUE && !empty($log_file))
				error_log(gettext("WARNING: unrecognized token '{$tok}' encountered while processing an automatic SID MGMT file.\n"), 3, $log_file);
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

function snort_modify_sid_state(&$rule_map, $sid_mods, $action, $log_results = FALSE, $log_file = NULL) {

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
	/*		            'drop' or 'reject'.       */
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

	// If no rules in $rule_map or mods in $sid_mods,
	// then nothing to do.
	if (empty($rule_map) || empty($sid_mods))
		return $sids;

	// Validate the action keyword as we only accept
	// 'enable' and 'disable' as valid.
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
			logger(LOG_ALERT, localize_text("unknown action '%s' supplied to %s function...no SIDs modified.", $action, 'snort_modify_sid_state()'), LOG_PREFIX_PKG_SNORT);
			return $sids;
	}

	// Walk the SID mod tokens and decode each one
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
			$regex = '/' . preg_quote($matches[2], '/') . '/i';

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
			$regex = "/" . preg_quote($matches[0], '/') . "/i";

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
			if ($log_results == TRUE && !empty($log_file))
				error_log(gettext("WARNING: unrecognized token '{$tok}' encountered while processing an automatic SID MGMT file.\n"), 3, $log_file);
		}
	}

	// Change state of all the matching GID:SID pairs we found
	// above in the $rule_map array passed to us.
	$modcount = $changecount = 0;
	$counter = count($sids, COUNT_RECURSIVE) - count($sids);

	if ($log_results == TRUE && !empty($log_file))
		error_log(gettext("    Parsed {$counter} potential SIDs to match from the provided list of tokens.\n"), 3, $log_file);

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

function snort_modify_sid_content(&$rule_map, $sid_mods, $log_results = FALSE, $log_file = NULL) {

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
	if (empty($rule_map) || empty($sid_mods))
		return $sids;

	// Walk the SID mod tokens and decode each one
	foreach ($sid_mods as $tok) {
		$matches = array();
		if (preg_match('/([\d+|,|\*]*)\s+"(.+)"\s+"(.*)"/', $tok, $matches)) {
			$tokencounter++;
			$sidlist = explode(",", $matches[1]);
			$from = '/' . preg_quote($matches[2], '/') . '/';
			$to = $matches[3];
			$count = 0;

			// Now walk the provided rule map and make the modifications
			if ($matches[1] == "*") {
				// If wildcard '*' provided for SID, then check them all
				foreach ($rule_map[1] as $rulem) {
					foreach ($rulem as $k2 => $v) {
						$modcount++;
						$rule_map[1][$k2]['rule'] = preg_replace($from, $to, $v['rule'], -1, $count);
						if ($count > 0) {
							$rule_map[1][$k2]['managed'] = 1;
							$sids[1][$k2] = 'modify';
							$modifiedcount++;
						}
					}
				}
			}
			else {
				// Otherwise just check the provided SIDs
				foreach ($sidlist as $sid) {
					if (isset($rule_map[1][$sid])) {
						$modcount++;
						$rule_map[1][$sid]['rule'] = preg_replace($from, $to, $rule_map[1][$sid]['rule'], -1, $count);
						if ($count > 0) {
							$rule_map[1][$sid]['managed'] = 1;
							$sids[1][$sid] = 'modify';
							$modifiedcount++;
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

function snort_process_enablesid(&$rule_map, $snortcfg, $log_results = FALSE, $log_file = NULL) {

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
	/*  $snortcfg    ==> interface config params  */
	/*  $log_results ==> [optional] 'yes' to log  */
	/*                   results to $log_file     */
	/*     $log_file ==> full path and filename   */
	/*                   of log file to write to  */
	/*                                            */
	/*     On Return ==> suitably modified        */
	/*                   $rule_map array          */
	/**********************************************/

	$snortlogdir = SNORTLOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map))
		return;

	// Verify the 'enable_sid' list for the interface exists
	if (!snort_sid_mgmt_list_exist($snortcfg['enable_sid_file'])) {
		logger(LOG_ERR, localize_text("unable to find enable_sid list \"%s\" specified for %s", $snortcfg['enable_sid_file'], convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
		return;
	}
	else
		$sid_mods = snort_parse_sidconf_file("{$snortcfg['enable_sid_file']}");

	if (!empty($sid_mods))
		snort_modify_sid_state($rule_map, $sid_mods, "enable", $log_results, $log_file);
	elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in list \"{$snortcfg['enable_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function snort_process_disablesid(&$rule_map, $snortcfg, $log_results = FALSE, $log_file = NULL) {

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
	/*  $snortcfg    ==> interface config params  */
	/*  $log_results ==> [optional] 'yes' to log  */
	/*                   results to $log_file     */
	/*     $log_file ==> full path and filename   */
	/*                   of log file to write to  */
	/*                                            */
	/*     On Return ==> suitably modified        */
	/*                   $rule_map array          */
	/**********************************************/

	$snortlogdir = SNORTLOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map))
		return;

	// Verify the 'disable_sid' list for the interface exists
	if (!snort_sid_mgmt_list_exist($snortcfg['disable_sid_file'])) {
		logger(LOG_ERR, localize_text("unable to find disable_sid list \"%s\" specified for %s", $snortcfg['disable_sid_file'], convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
		return;
	}
	else {
		$sid_mods = snort_parse_sidconf_file("{$snortcfg['disable_sid_file']}");
	}

	if (!empty($sid_mods))
		snort_modify_sid_state($rule_map, $sid_mods, "disable", $log_results, $log_file);
	elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in file \"{$snortcfg['disable_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function snort_process_modifysid(&$rule_map, $snortcfg, $log_results = FALSE, $log_file = NULL) {

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
	/*  $snortcfg    ==> interface config params  */
	/*  $log_results ==> [optional] 'yes' to log  */
	/*                   results to $log_file     */
	/*     $log_file ==> full path and filename   */
	/*                   of log file to write to  */
	/*                                            */
	/*     On Return ==> suitably modified        */
	/*                   $rule_map array          */
	/**********************************************/

	$snortlogdir = SNORTLOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map))
		return;

	// Verify the 'modify_sid' list for the interface exists
	if (!snort_sid_mgmt_list_exist($snortcfg['modify_sid_file'])) {
		logger(LOG_ERR, localize_text("unable to find modify_sid list \"%s\" specified for %s", $snortcfg['modify_sid_file'], convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
		return;
	} else {
		$sid_mods = snort_parse_sidconf_file($snortcfg['modify_sid_file'],FALSE);
	}

	if (!empty($sid_mods))
		snort_modify_sid_content($rule_map, $sid_mods, $log_results, $log_file);
	elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in file \"{$snortcfg['modify_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function snort_auto_sid_mgmt(&$rule_map, $snortcfg, $log_results = FALSE) {

	/**************************************************/
	/* This function modifies the rules in the        */
	/* passed rule_map array based on values in the   */
	/* lists 'enable_sid_file', 'disable_sid_file'    */
	/* and 'modify_sid_file' for the interface.       */
	/*                                                */
	/* If auto-mgmt of SIDs is enabled via the        */
	/* settings on the UPDATE RULES tab, then the     */
	/* rules are processed against these settings.    */
	/*                                                */
	/*     $rule_map ==> array of current rules       */
	/*  $snortcfg ==> interface config settings       */
	/*  $log_results ==> [optional] log results to    */
	/*                   'sid_changes.log' in the     */
	/*                   interface directory in       */
	/*                   /var/log/snort when TRUE     */
	/*                                                */
	/*       Returns ==> TRUE if rules were changed;  */
	/*                   otherwise FALSE              */
	/**************************************************/

	$result = FALSE;

	// Configure the interface's logging subdirectory if log results is enabled
	if ($log_results == TRUE)
		$log_file = SNORTLOGDIR . "/snort_" . get_real_interface($snortcfg['interface']) . "{$snortcfg['uuid']}/sid_changes.log";
	else
		$log_file = NULL;

	// Check if auto-mgmt of SIDs is enabled and files are specified
	// for the interface.
	if (config_get_path('installedpackages/snortglobal/auto_manage_sids') == 'on' &&
	    (!empty($snortcfg['disable_sid_file']) || !empty($snortcfg['enable_sid_file']) || 
	    !empty($snortcfg['modify_sid_file']) || !empty($snortcfg['drop_sid_file']) || !empty($snortcfg['reject_sid_file']))) {
		if ($log_results == TRUE) {
			error_log(gettext("********************************************************\n"), 3, $log_file);
			error_log(gettext("Starting auto SID management for " . convert_friendly_interface_to_friendly_descr($snortcfg['interface']) ."\n"), 3, $log_file);
			error_log(gettext("Start Time: " . date("Y-m-d H:i:s") . "\n"), 3, $log_file);
		}

		switch ($snortcfg['sid_state_order']) {
			case "disable_enable":
				if (!empty($snortcfg['disable_sid_file'])) {
					if ($log_results == TRUE)
						error_log(gettext("Processing disable_sid list: {$snortcfg['disable_sid_file']}\n"), 3, $log_file);
					snort_process_disablesid($rule_map, $snortcfg, $log_results, $log_file);
				}
				if (!empty($snortcfg['enable_sid_file'])) {
					if ($log_results == TRUE)
						error_log(gettext("Processing enable_sid list: {$snortcfg['enable_sid_file']}\n"), 3, $log_file);
					snort_process_enablesid($rule_map, $snortcfg, $log_results, $log_file);
				}
				if (!empty($snortcfg['modify_sid_file'])) {
					if ($log_results == TRUE)
						error_log(gettext("Processing modify_sid list: {$snortcfg['modify_sid_file']}\n"), 3, $log_file);
					snort_process_modifysid($rule_map, $snortcfg, $log_results, $log_file);
				}
				if (!empty($snortcfg['drop_sid_file']) && ($snortcfg['blockoffenders7'] == 'on' && $snortcfg['ips_mode'] == 'ips_mode_inline')) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing drop_sid list: {$snortcfg['drop_sid_file']}\n"), 3, $log_file);
					}
					snort_process_dropsid($rule_map, $snortcfg, $log_results, $log_file);
				}
				if (!empty($snortcfg['reject_sid_file']) && $snortcfg['blockoffenders7'] == 'on' && $snortcfg['ips_mode'] == 'ips_mode_inline') {
					if ($log_results == TRUE) {
						error_log(gettext("Processing reject_sid list: {$snortcfg['reject_sid_file']}\n"), 3, $log_file);
					}
					snort_process_rejectsid($rule_map, $snortcfg, $log_results, $log_file);
				}
				$result = TRUE;
				break;

			case "enable_disable":
				if (!empty($snortcfg['enable_sid_file'])) {
					if ($log_results == TRUE)
						error_log(gettext("Processing enable_sid list: {$snortcfg['enable_sid_file']}\n"), 3, $log_file);
					snort_process_enablesid($rule_map, $snortcfg, $log_results, $log_file);
				}
				if (!empty($snortcfg['disable_sid_file'])) {
					if ($log_results == TRUE)
						error_log(gettext("Processing disable_sid list: {$snortcfg['disable_sid_file']}\n"), 3, $log_file);
					snort_process_disablesid($rule_map, $snortcfg, $log_results, $log_file);
				}
				if (!empty($snortcfg['modify_sid_file'])) {
					if ($log_results == TRUE)
						error_log(gettext("Processing modify_sid list: {$snortcfg['modify_sid_file']}\n"), 3, $log_file);
					snort_process_modifysid($rule_map, $snortcfg, $log_results, $log_file);
				}
				if (!empty($snortcfg['drop_sid_file']) && ($snortcfg['blockoffenders7'] == 'on' && $snortcfg['ips_mode'] == 'ips_mode_inline')) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing drop_sid list: {$snortcfg['drop_sid_file']}\n"), 3, $log_file);
					}
					snort_process_dropsid($rule_map, $snortcfg, $log_results, $log_file);
				}
				if (!empty($snortcfg['reject_sid_file']) && $snortcfg['blockoffenders7'] == 'on' && $snortcfg['ips_mode'] == 'ips_mode_inline') {
					if ($log_results == TRUE) {
						error_log(gettext("Processing reject_sid list: {$snortcfg['reject_sid_file']}\n"), 3, $log_file);
					}
					snort_process_rejectsid($rule_map, $snortcfg, $log_results, $log_file);
				}
				$result = TRUE;
				break;

			default:
				logger(LOG_ALERT, localize_text("Unrecognized 'sid_state_order' value.  Skipping auto SID mgmt step for %s", convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
				if ($log_results == TRUE) {
					error_log(gettext("ERROR: unrecognized 'sid_state_order' value.  Skipping auto SID mgmt step for ") . convert_friendly_interface_to_friendly_descr($snortcfg['interface']). ".\n", 3, $log_file);
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

function snort_load_sid_mods($sids) {

	/*****************************************/
	/* This function parses the string of    */
	/* GID:SID values in $sids and returns   */
	/* an array with the GID and SID as the  */
	/* keys.  The values in $sids are        */
	/* assumed to be delimited by "||".      */
	/*                                       */
	/* $sids   ==> string of GID:SID values  */
	/*             from the config file.     */
	/*                                       */
	/* Returns ==> a multidimensional array  */
	/*             with GID and SID as the   */
	/*             keys ($result[GID][SID])  */
	/*****************************************/

	$result = array();
	if (empty($sids))
		return $result;
	$tmp = explode("||", $sids);
	foreach ($tmp as $v) {
		if (preg_match('/(\d+)\s*:\s*(\d+)/', $v, $match)) {
			if (!is_array($result[$match[1]]))
				$result[$match[1]] = array();
			if (!is_array($result[$match[1]][$match[2]]))
				$result[$match[1]][$match[2]] = array();
			$result[$match[1]][$match[2]] = "{$match[1]}:{$match[2]}";
		}
	}
	unset($tmp);
	return $result;
}

function snort_modify_sids(&$rule_map, $snortcfg) {

	/*****************************************/
	/* This function modifies the rules in   */
	/* the passed rules_map array based on   */
	/* values in the enablesid/disablesid    */
	/* configuration parameters.             */
	/*                                       */
	/*  $rule_map = array of current rules   */
	/*  $snortcfg = config settings          */
	/*****************************************/

	if (!isset($snortcfg['rule_sid_on']) && !isset($snortcfg['rule_sid_off']))
		return;

	/* Load up our enablesid and disablesid  */
	/* arrays with lists of modified SIDs    */
	$enablesid = snort_load_sid_mods($snortcfg['rule_sid_on']);
	$disablesid = snort_load_sid_mods($snortcfg['rule_sid_off']);

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

function snort_modify_sids_action(&$rule_map, $snortcfg) {

	/***********************************************/
	/* This function modifies the rules in the     */
	/* passed rules_map array based on values in   */
	/* the alertsid/dropsid configuration          */
	/* parameters for the interface.               */
	/*                                             */
	/*  $rule_map = array of current rules         */
	/*  $snortcfg = interface config settings      */
	/***********************************************/

	if (!isset($snortcfg['rule_sid_force_alert']) && 
	    !isset($snortcfg['rule_sid_force_drop']) && 
	    !isset($snortcfg['rule_sid_force_reject'])) {
		return;
	}

	/* Load up our SID forced action arrays with manually changed SID actions */
	$alertsid = snort_load_sid_mods($snortcfg['rule_sid_force_alert']);

	/* DROP is only applicable when blocking offenders is enabled and when    */
	/* 'block drops only' is enabled, or when using Inline IPS Mode.          */
	if ($snortcfg['blockoffenders7'] == 'on' && $snortcfg['ips_mode'] == 'ips_mode_inline') {
		$dropsid = snort_load_sid_mods($snortcfg['rule_sid_force_drop']);
	}
	else {
		$dropsid = array();
	}

	/* REJECT is only applicable when blocking offenders is enabled and when  */
	/* Inline IPS Mode is also enabled.                                       */
	if ($snortcfg['blockoffenders7'] == 'on' && $snortcfg['ips_mode'] == 'ips_mode_inline') {
		$rejectsid = snort_load_sid_mods($snortcfg['rule_sid_force_reject']);
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

function snort_get_filtered_rules($rules, $filter) {

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
	$tmp_map = snort_load_rules_map($rules);

	// Now walk the loaded filter list and copy
	// matching GID:SID rules from the target list
	// over to the filtered array.
	foreach ($filter as $k1 => $rulem) {
		foreach($rulem as $k2 => $v) {
			if (isset($tmp_map[$k1][$k2])) {
				if (!is_array($filtered_map[$k1])) {
					$filtered_map[$k1] = array();
				}
				if (!is_array($filtered_map[$k1][$k2])) {
					$filtered_map[$k1][$k2] = array();
				}
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

function snort_create_rc() {

	/*********************************************************/
	/* This function builds the /usr/local/etc/rc.d/snort.sh */
	/* shell script for starting and stopping Snort.  The    */
	/* script is rebuilt on each package sync operation and  */
	/* after any changes to snort.conf saved in the GUI.     */
	/*********************************************************/

	global $g;

	$snortdir = SNORTDIR;
	$snortlogdir = SNORTLOGDIR;
	$snortbindir = SNORT_BINDIR;
	$rcdir = RCFILEPREFIX;	
	$no_enabled_interface = TRUE;
	$LOG_PREFIX_PKG_SNORT = LOG_PREFIX_PKG_SNORT;

	// If no interfaces are configured for Snort, exit
	if (count(config_get_path('installedpackages/snortglobal/rule', [])) < 1) {
		unlink_if_exists("{$rcdir}snort.sh");
		return;
	}

	// See whether or not to enable detailed startup logging
	if (config_get_path('installedpackages/snortglobal/verbose_logging') == "on")
		$quiet = "";
	else
		$quiet = "-q --suppress-config-log";

	// At least one interface is configured, so OK
	$start_snort_iface_start = array();
	$start_snort_iface_stop = array();
	$no_enabled_interface = FALSE;

	// Loop thru each configured interface and build
	// the shell script.
	foreach (config_get_path('installedpackages/snortglobal/rule', []) as $value) {

		// Attempt to grab physical interface
		// for this Snort instance.
		$if_real = get_real_interface($value['interface']);

		// Skip disabled Snort instances or
		// intances whose pfSense physical
		// interface has been removed.
		if (($value['enable'] <> 'on') || ($if_real == ""))
			continue;
		$snort_uuid = $value['uuid'];

		// Adjust the interface specification and DAQ type according to operating mode
		if ($value['ips_mode'] == "ips_mode_inline" && $value['blockoffenders7'] == "on") {
			$iface = $if_real . "^:" . $if_real;
			$daq_type = "-Q --daq netmap";
		}
		else {
			$iface = $if_real;
			$daq_type = "--daq pcap --daq-mode passive --treat-drop-as-alert";
		}

		$start_snort_iface_start[] = <<<EOE

	# Start snort for {$value['descr']}
	if [ ! -f {$g['varrun_path']}/snort_{$snort_uuid}.pid ]; then
		pid=`/bin/pgrep -fn "snort -R {$snort_uuid} "`
	else
		pid=`/bin/pgrep -F {$g['varrun_path']}/snort_{$snort_uuid}.pid`
	fi

	if [ -z \$pid ]; then
		/usr/bin/logger -p daemon.info -i -t SnortStartup "INFO [{$LOG_PREFIX_PKG_SNORT}] START for {$value['descr']}({$if_real})..."
		{$snortbindir}snort -R _{$snort_uuid} -M -D {$quiet} {$daq_type} -l {$snortlogdir}/snort_{$if_real}{$snort_uuid} --pid-path {$g['varrun_path']} --nolock-pidfile --no-interface-pidfile -G {$snort_uuid} -c {$snortdir}/snort_{$snort_uuid}_{$if_real}/snort.conf -i {$iface} > /dev/null 2>&1
	fi

EOE;

		$start_snort_iface_stop[] = <<<EOE

	# Stop snort for {$value['descr']}
	if [ -f {$g['varrun_path']}/snort_{$snort_uuid}.pid ]; then
		pid=`/bin/pgrep -F {$g['varrun_path']}/snort_{$snort_uuid}.pid`
		/usr/bin/logger -p daemon.info -i -t SnortStartup "INFO [{$LOG_PREFIX_PKG_SNORT}] STOP for {$value['descr']}({$if_real})..."
		/bin/pkill -F {$g['varrun_path']}/snort_{$snort_uuid}.pid -a
		time=0 timeout=30
		while kill -0 \$pid 2>/dev/null; do
			sleep 1
			time=\$((time+1))
			if [ \$time -gt \$timeout ]; then
				break
			fi
		done
		if [ -f {$g['varrun_path']}/snort_{$snort_uuid}.pid ]; then
			/bin/rm {$g['varrun_path']}/snort_{$snort_uuid}.pid
		fi
	else
		pid=`/bin/pgrep -fn "snort -R {$snort_uuid} "`
		if [ ! -z \$pid ]; then
			/usr/bin/logger -p daemon.info -i -t SnortStartup "INFO [{$LOG_PREFIX_PKG_SNORT}] STOP for {$value['descr']}({$snort_uuid}_{$if_real})..."
			/bin/pkill -fn "snort -R {$if_real} "
			time=0 timeout=30
			while kill -0 \$pid 2>/dev/null; do
				sleep 1
				time=\$((time+1))
				if [ \$time -gt \$timeout ]; then
					break
				fi
			done
		fi
	fi

	sleep 1
EOE;
	}

	$rc_start = implode("\n", $start_snort_iface_start);
	$rc_stop = implode("\n", $start_snort_iface_stop);

	$snort_sh_text = <<<EOD
#!/bin/sh
########
# This file was automatically generated
# by the pfSense service handler.
######## Start of main snort.sh

rc_start() {

	### Lock out other start signals until we are done
	/usr/bin/touch {$g['varrun_path']}/snort_pkg_starting.lck
	{$rc_start}

	### Remove the lock since we have started all interfaces
	if [ -f {$g['varrun_path']}/snort_pkg_starting.lck ]; then
		sleep 2
		/bin/rm {$g['varrun_path']}/snort_pkg_starting.lck
	fi
}

rc_stop() {
	{$rc_stop}
}

case $1 in
	start)
		if [ ! -f {$g['varrun_path']}/snort_pkg_starting.lck ]; then
			rc_start
		else
			/usr/bin/logger -p daemon.info -i -t SnortStartup "INFO [{$LOG_PREFIX_PKG_SNORT}] Ignoring additional START command since Snort is already starting..."
		fi
		;;
	stop)
		rc_stop
		;;
	restart)
		if [ ! -f {$g['varrun_path']}/snort_pkg_starting.lck ]; then
			rc_stop
			rc_start
		else
			/usr/bin/logger -p daemon.info -i -t SnortRestart "INFO [{$LOG_PREFIX_PKG_SNORT}] Ignoring RESTART command since Snort is already starting..."
		fi
		;;
esac

EOD;

	/* write out snort.sh if enabled interfaces are present */
	if (!$no_enabled_interface) {
		// Write out the snort.sh script file
		@file_put_contents("{$rcdir}snort.sh", $snort_sh_text);
		@chmod("{$rcdir}snort.sh", 0755);
	} else {
		unlink_if_exists("{$rcdir}snort.sh");
	}
}

function snort_prepare_rule_files($snortcfg, $snortcfgdir) {

	/***********************************************************/
	/* This function builds a new set of enforcing rules for   */
	/* Snort and writes them to disk.                          */
	/*                                                         */
	/*    $snortcfg --> pointer to applicable section of       */
	/*                  config.xml containing settings for     */
	/*                  the interface.                         */
	/*                                                         */
	/* $snortcfgdir --> pointer to physical directory on       */
	/*                  disk where Snort configuration is      */
	/*                  to be written.                         */
	/***********************************************************/

	global $g, $rebuild_rules;

	$snortdir = SNORTDIR;
	$flowbit_rules_file = FLOWBITS_FILENAME;
	$snort_enforcing_rules_file = SNORT_ENFORCING_RULES_FILENAME;
	$enabled_files = array();
	$all_rules = array();
	$cat_mods = array();
	$no_rules_defined = true;
	$enabled_rules = array();

	/* If there is no reason to rebuild the rules, exit to save time. */
	if (!$rebuild_rules)
		return;

	/* Log a message for rules rebuild in progress */
	logger(LOG_NOTICE, localize_text("Updating rules configuration for: %s ...", convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);

	// Get any automatic rule category enable/disable modifications
	// if auto-SID Mgmt is enabled and conf files exist for the interface.
	$cat_mods = snort_sid_mgmt_auto_categories($snortcfg, TRUE);

	/* Enable all, some or none of the SDF rules depending on setting. */
	if ($snortcfg['sensitive_data'] == 'on' && $snortcfg['protect_preproc_rules'] != 'on') {
		if (file_exists(SNORTDIR."/preproc_rules/sensitive-data.rules")) {
			$sdf_alert_pattern="(".preg_replace("/,/","|",$snortcfg['sdf_alert_data_type']).")";
			$sd_tmp_file=file(SNORTDIR."/preproc_rules/sensitive-data.rules");
			$sd_tmp_new_file="";
			foreach ($sd_tmp_file as $sd_tmp_line)
				$sd_tmp_new_file.=preg_match("/$sdf_alert_pattern/i",$sd_tmp_line) ? $sd_tmp_line : "";
			@file_put_contents("{$snortcfgdir}/preproc_rules/sensitive-data.rules",$sd_tmp_new_file,LOCK_EX);
		}
	}
	elseif ($snortcfg['sensitive_data'] != 'on' && $snortcfg['protect_preproc_rules'] != 'on') {
		/* Setting is "off", so disable all SDF rules. */
		$sedcmd = '/^alert.*classtype:sdf/s/^/#/';
		@file_put_contents("{$g['tmp_path']}/sedcmd", $sedcmd);
		mwexec("/usr/bin/sed -I '' -f {$g['tmp_path']}/sedcmd {$snortcfgdir}/preproc_rules/sensitive-data.rules");
		@unlink("{$g['tmp_path']}/sedcmd");
	}

	/* Create an array of rules filenames from the text    */
	/* and preprocessor rules, then load rules map array.  */
	$rules_files = array_merge(glob($snortcfgdir . "/preproc_rules/*.rules"), glob($snortdir . "/rules/*.rules"));
	$all_rules = snort_load_rules_map($rules_files);

	/* Create an array with the filenames of the enabled rule    */
	/* category files if we have any (plus the built-in rules).  */
	$rulesets = "";
	if (!empty($snortcfg['rulesets'])) {
		$rulesets = $snortcfg['rulesets'];
	}
	/* If an IPS Policy is enabled, then it will select which    */
	/* built-in rules get used. Otherwise, include all of them.  */
	if ($snortcfg['ips_policy_enable'] != 'on' || empty($snortcfg['ips_policy'])) {
		$rulesets .= "||decoder.rules||preprocessor.rules||sensitive-data.rules";
	}

	/* Build a list of enabled category files. */
	foreach (explode("||", $rulesets) as $file){
			$category = basename($file, ".rules");
			if (!is_array($enabled_files[$category])) {
				$enabled_files[$category] = array();
			}
			$enabled_files[$category] = $file;
	}

	// Now adjust the list using any required changes as
	// determined by auto-SID Mgmt policy files.
	if (!empty($cat_mods)) {
		foreach ($cat_mods as $k => $action) {
			$key = basename($k, ".rules");
			switch ($action) {
				case 'enabled':
					if (!isset($enabled_files[$key]))
						$enabled_files[$key] = $k;
					break;

				case 'disabled':
					if (isset($enabled_files[$key]))
						unset($enabled_files[$key]);
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
				if (!is_array($enabled_rules[$k1]))
					$enabled_rules[$k1] = array();
				if (!is_array($enabled_rules[$k1][$k2]))
					$enabled_rules[$k1][$k2] = array();
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

	/* Release memory we no longer need. */
	unset($enabled_files, $cat_mods, $rulem, $v);

	/* Check if a pre-defined Snort Subscriber rules policy is selected. */
	/* If so, add all the VRT policy rules to our enforcing rule set.    */
	if ($snortcfg['ips_policy_enable'] == 'on' && !empty($snortcfg['ips_policy'])) {
		if ($snortcfg['blockoffenders7'] == 'on' && $snortcfg['ips_mode'] == 'ips_mode_inline') {
			$policy_mode = $snortcfg['ips_policy_mode'];
		} else {
			$policy_mode = 'alert';
		}
		$policy_rules = snort_load_vrt_policy($snortcfg['ips_policy'], $policy_mode, $all_rules);
		foreach ($policy_rules as $k1 => $policy) {
			foreach ($policy as $k2 => $p) {
				if (!is_array($enabled_rules[$k1]))
					$enabled_rules[$k1] = array();
				if (!is_array($enabled_rules[$k1][$k2]))
					$enabled_rules[$k1][$k2] = array();
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

	// Process any enablesid or disablesid modifications for the selected rules.
	// Do the auto-SID managment first, if enabled, then do user-forced SID state changes.
	snort_auto_sid_mgmt($enabled_rules, $snortcfg, TRUE);
	snort_modify_sids($enabled_rules, $snortcfg);
	snort_modify_sids_action($enabled_rules, $snortcfg);

	/* Check for and disable any rules dependent upon disabled preprocessors if  */
	/* this option is enabled for the interface.                                 */
	if ($snortcfg['preproc_auto_rule_disable'] == "on") {
		logger(LOG_NOTICE, localize_text('Checking for rules dependent on disabled preprocessors for: %s...', convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
		snort_filter_preproc_rules($snortcfg, $enabled_rules);
	}

	/* Write the enforcing rules file to the Snort interface's "rules" directory. */
	if (!empty($enabled_rules)) {
		snort_write_enforcing_rules_file($enabled_rules, "{$snortcfgdir}/rules/{$snort_enforcing_rules_file}");
	} else {
		snort_write_enforcing_rules_file(array(), "{$snortcfgdir}/rules/{$snort_enforcing_rules_file}");
	}

	/* If auto-flowbit resolution is enabled, generate the dependent flowbits rules file. */
	if ($snortcfg['autoflowbitrules'] == 'on' && !empty($enabled_rules)) {
		logger(LOG_NOTICE, localize_text('Enabling any flowbit-required rules for: %s...', convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
		$fbits = snort_resolve_flowbits($all_rules, $enabled_rules);

		/* Check for and disable any flowbit-required rules the user has  */
		/* manually forced to a disabled state.                           */
		if (!empty($fbits))
			snort_modify_sids($fbits, $snortcfg);

		/* Check for and disable any flowbit-required rules dependent upon     */
		/* disabled preprocessors if this option is enabled for the interface. */
		if ($snortcfg['preproc_auto_rule_disable'] == "on") {
			logger(LOG_NOTICE, localize_text('Checking flowbit rules dependent on disabled preprocessors for: %s...', convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
			snort_filter_preproc_rules($snortcfg, $fbits, true);
		}
		snort_write_flowbit_rules_file($fbits, "{$snortcfgdir}/rules/{$flowbit_rules_file}");
		unset($fbits);
	} else {
		/* Just put an empty file to always have the file present */
		snort_write_flowbit_rules_file(array(), "{$snortcfgdir}/rules/{$flowbit_rules_file}");
	}

	unset($all_rules);

	if (!empty($snortcfg['customrules'])) {
		@file_put_contents("{$snortcfgdir}/rules/custom.rules", base64_decode($snortcfg['customrules']));
	} else {
		@file_put_contents("{$snortcfgdir}/rules/custom.rules", "");
	}

	/* Build a new sid-msg.map file from the enabled */
	/* rules and copy it to the interface directory. */
	logger(LOG_NOTICE, localize_text("Building new sid-msg.map file for %s...", convert_friendly_interface_to_friendly_descr($snortcfg['interface'])), LOG_PREFIX_PKG_SNORT);
	snort_build_sid_msg_map("{$snortcfgdir}/rules/", "{$snortcfgdir}/sid-msg.map");
}

function snort_filter_preproc_rules($snortcfg, &$active_rules, $persist_log = false) {

	/**************************************************/
	/* This function checks the $active_rules array   */
	/* for rule options dependent upon preprocessors. */
	/* Rules with rule options dependent upon any     */
	/* non-enabled preprocessors are disabled to stop */
	/* start-up errors from unknown rule options.     */
	/*                                                */
	/* $snortcfg     --> config parameters array for  */
	/*                   the interface.               */
	/*                                                */
	/* $active_rules --> rules_map array of enabled   */
	/*                   rules for the interface.     */
	/*                                                */
	/* $persist_log  --> flag indicating if new log   */
	/*                   file should be created or    */
	/*                   the existing one appended    */
	/*                   to.                          */
	/*						  */
	/* NOTE: This feature must be enabled in the GUI  */
	/*       by the user.  Use of this feature can    */
	/*       severely degrade Snort's ability to      */
	/*       detect threats by disabling potentially  */
	/*       crucial detection rules.                 */
	/**************************************************/

	$snortlogdir = SNORTLOGDIR;
	$disabled_count = 0;
	$log_msg = array();

	/* Check if no rules or if this option is disabled */
	if (empty($active_rules) || $snortcfg['preproc_auto_rule_disable'] <> 'on')
		return;

	/***************************************************
	 * Construct an array of rule options with their   *
	 * associated preprocessors.                       *
	 *                                                 *
	 * IMPORTANT -- Keep this part of the code current *
	 * with changes to preprocessor rule options in    *
	 * Snort Subscriber rules.                         *
	 *                                                 *
	 *                                                 *
	 * Format of array is:                             *
	 *    "rule_option" => "dependent_preprocessor"    *
	 *                                                 *
	 * Last Update: 10/30/2014                         *
	 *                                                 *
	 * Added: appid: detection option                  *
	 *                                                 *
	 ***************************************************/
	$rule_opts_preprocs = array("ssl_version:" => "ssl_preproc","ssl_state:" => "ssl_preproc",
				    "service ssl" => "ssl_preproc", "service ftp" => "ftp_preprocessor",
				    "service telnet" => "ftp_preprocessor", "service dns" => "dns_preprocessor",
		 		    "dce_iface:" => "dce_rpc_2", "dce_opnum:" => "dce_rpc_2",
				    "dce_stub_data;" => "dce_rpc_2", "sd_pattern:" => "sensitive_data",
				    "sip_method:" => "sip_preproc", "sip_stat_code:" => "sip_preproc",
				    "sip_header;" => "sip_preproc", "sip_body;" => "sip_preproc",
				    "gtp_type:" => "gtp_preproc", "gtp_info:" => "gtp_preproc",
				    "gtp_version:" => "gtp_preproc", "modbus_func:" => "modbus_preproc",
				    "modbus_unit:" => "modbus_preproc", "modbus_data;" => "modbus_preproc",
				    "dnp3_func:" => "dnp3_preproc", "dnp3_obj:" => "dnp3_preproc",
				    "dnp3_ind:" => "dnp3_preproc", "dnp3_data;" => "dnp3_preproc",
				    "http_client_body;" => "http_inspect", "http_cookie;" => "http_inspect",
				    "http_raw_cookie;" => "http_inspect", "http_header;" => "http_inspect",
				    "http_raw_header;" => "http_inspect", "http_method;" => "http_inspect",
				    "http_uri;" => "http_inspect", "http_raw_uri;" => "http_inspect",
				    "http_stat_code;" => "http_inspect", "http_stat_msg;" => "http_inspect",
				    "uricontent:" => "http_inspect", "urilen:" => "http_inspect",
				    "http_encode;" => "http_inspect", "service http" => "http_inspect",
				    "service imap" => "imap_preproc", "service pop2" => "pop_preproc",
				    "service pop3" => "pop_preproc", "service smtp" => "smtp_preprocessor", 
				    "appid:" => "appid_preproc" );

	/***************************************************
	 * Iterate the enabled rules, and check for rule   *
	 * options that depend on disabled preprocessors.  *
	 * Disable any of these preprocessor-dependent     *
	 * rules we find.  Once we find at least one       *
	 * reason to disable the rule, stop further checks *
	 * and go to the next rule.                        *
	 ***************************************************/
	foreach ($active_rules as $k1 => $rulem) {
		foreach ($rulem as $k2 => $v) {

			/* If rule is already disabled, skip it. */
			if ($v['disabled'] == 1)
				continue;

			foreach ($rule_opts_preprocs as $opt => $preproc) {
				$pcre = "/\s*\b" . preg_quote($opt) . "/i";
				if (($snortcfg[$preproc] != 'on') && preg_match($pcre, $v['rule'])) {
					$active_rules[$k1][$k2]['rule'] = "# " . $v['rule'];
					$active_rules[$k1][$k2]['disabled'] = 1;
					$disabled_count++;

					/* Accumulate auto-disabled rules for logging */
					$tmp = $active_rules[$k1][$k2]['category'] . ",";
					$tmp .= "{$k1}:{$k2},{$preproc},{$opt}";
					$log_msg[] = $tmp;
					break;
				}
			}
		}
	}

	/* Release memory we no longer need. */
	unset($rulem, $v, $preproc);

	/***************************************************************/
	/* If we are persisting the log from the last pass, then open  */
	/* the log file in append mode.  Otherwise open in overwrite   */
	/* to clear the log in case we have zero disabled rules.       */
	/*							       */
	/* Typically "persist log" mode is used on the second pass     */
	/* when flowbit-required rules are being assessed after the    */
	/* primary enforcing rules have been evaluated.                */
	/***************************************************************/
	$iface = convert_friendly_interface_to_friendly_descr($snortcfg['interface']);
	$file = "{$snortlogdir}/{$iface}_disabled_preproc_rules.log";
	if ($persist_log)
		$fp = fopen($file, 'a');
	else
		$fp = fopen($file, 'w');

	/***************************************************/
	/* Log a warning if we auto-disabled any rules     */
	/* just so the user is aware protection is less    */
	/* than optimal with the preprocessors disabled.   */
	/***************************************************/
	if ($disabled_count > 0) {
		logger(LOG_WARNING, localize_text("auto-disabled %s rules due to disabled preprocessor dependencies.", $disabled_count), LOG_PREFIX_PKG_SNORT);
		natcasesort($log_msg);
		if ($fp) {
			/* Only write the header when not persisting the log */
			if (!$persist_log) {
				@fwrite($fp, "#\n# Run Time: " . date("Y-m-d H:i:s") . "\n#\n");
				@fwrite($fp, "#\n# These rules were auto-disabled because they contain options or operators\n");
				@fwrite($fp, "# dependent on preprocessors that are currently NOT ENABLED on the Preprocessors\n");
				@fwrite($fp, "# tab.  Without these dependent preprocessors enabled, Snort would fail to start\n");
				@fwrite($fp, "# if the rules listed below were enabled.  Therefore the listed rules have been\n");
				@fwrite($fp, "# automatically disabled.  This behavior is controlled by the Auto-Rule Disable\n");
				@fwrite($fp, "# feature on the Preprocessors tab.\n#\n");
				@fwrite($fp, "# WARNING: Using the auto-disable rule feature is not recommended because it can\n");
				@fwrite($fp, "# significantly reduce the threat detection capabilities of Snort!\n#");
				@fwrite($fp, "\n# In the list below, the PREPROCESSOR column is the disabled preprocessor that\n");
				@fwrite($fp, "# triggered the auto-disable of the rule represented by GID:SID.  The RULE OPTION\n");
				@fwrite($fp, "# column shows the specific rule option or content modifier contained within\n");
				@fwrite($fp, "# the rule text that requires the preprocessor be enabled in order to execute.\n#");
				@fwrite($fp, "\n# RULE CATEGORY                 GID:SID     PREPROCESSOR          RULE OPTION\n");
			}
			foreach ($log_msg as $m) {
				$tmp = explode(",", $m);
				@fwrite($fp, sprintf("%-30s  %-10s  %-20s  %s", $tmp[0], $tmp[1], $tmp[2], $tmp[3]) . "\n");
			}
		}
		logger(LOG_NOTICE, localize_text("See '%s' for list of auto-disabled rules.", $file), LOG_PREFIX_PKG_SNORT);
		unset($log_msg);
	}
	if ($fp)
		fclose($fp);
}

function snort_generate_conf($snortcfg) {

	/********************************************************/
	/* This function generates the snort.conf file for the  */
	/* passed interface using stored values from the Snort  */
	/* package configuration.                               */
	/********************************************************/

	global $g, $rebuild_rules;

	// Exit if there are no configured Snort interfaces
	if (count(config_get_path('installedpackages/snortglobal/rule', [])) < 1)
		return;

	$snortdir = SNORTDIR;
	$snortlibdir = SNORT_BASEDIR . "lib";
	$snortlogdir = SNORTLOGDIR;
	$flowbit_rules_file = FLOWBITS_FILENAME;
	$snort_enforcing_rules_file = SNORT_ENFORCING_RULES_FILENAME;

	$if_real = get_real_interface($snortcfg['interface']);
	$snort_uuid = $snortcfg['uuid'];
	$snortcfgdir = "{$snortdir}/snort_{$snort_uuid}_{$if_real}";

	// Pull in the PHP code that generates required string variables
	include("/usr/local/pkg/snort/snort_generate_conf.php");

	// Pull in the boilerplate template for the snort.conf
	// configuration file.  The contents of the template along
	// with substituted variables is stored in $snort_conf_text
	// (which is defined in the included file).
	include("/usr/local/pkg/snort/snort_conf_template.inc");

	// Write out snort.conf file using contents of $snort_conf_text
	@file_put_contents("{$snortcfgdir}/snort.conf", $snort_conf_text);

	// Create the actual rules files and save them in the interface directory
	snort_prepare_rule_files($snortcfg, $snortcfgdir);

	// Clean up variables we no longer need and free memory
	unset($snort_conf_text, $selected_rules_sections, $suppress_file_name, $snort_misc_include_rules, $spoink_type, $snortunifiedlog_type, $alertsystemlog_type);
	unset($home_net, $external_net, $ipvardef, $portvardef);
}

function snort_remove_dead_rules() {

	/********************************************************/
	/* This function removes dead and deprecated rules      */
	/* category files from the base Snort rules directory   */
	/* and from the RULESETS setting of each interface.     */
	/* The file "deprecated_rules", if it exists, is used   */
	/* to determine which rules files to remove.            */
	/********************************************************/

	global $g;
	$rulesdir = SNORTDIR . "/rules/";
	$count = 0;
	$cats = array();

	// If there is no "deprecated_rules" file, then exit
	if (!file_exists("/usr/local/pkg/snort/deprecated_rules"))
		return;

	// Open a SplFileObject to read in deprecated rules
	$file = new SplFileObject("/usr/local/pkg/snort/deprecated_rules");
	$file->setFlags(SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
	while (!$file->eof()) {
		$line = $file->fgets();

		// Skip any lines with just spaces
		if (trim($line) == "")
			continue;

		// Skip any comment lines starting with '#'
		if (preg_match('/^\s*\#+/', $line))
			continue;

		$cats[] = $line;
	}

	// Close the SplFileObject since we are finished with it
	$file = null;

	// Delete any dead rules files from the Snort RULES directory
	foreach ($cats as $file) {
		if (file_exists("{$rulesdir}{$file}"))
			$count++;
		unlink_if_exists("{$rulesdir}{$file}");
	}

	// Log how many obsoleted files were removed
	logger(LOG_NOTICE, localize_text("Removed %d obsoleted rules category files.", $count), LOG_PREFIX_PKG_SNORT);

	// Now remove any dead rules files from the interface configurations
	if (!empty($cats)) {
		$a_ifaces = config_get_path('installedpackages/snortglobal/rule', []);
		foreach ($a_ifaces as &$iface) {
			$enabled_rules = explode("||", $iface['rulesets']);
			foreach ($enabled_rules as $k => $v) {
				foreach ($cats as $d) {
					if (strpos(trim($v), $d) !== false)
						unset($enabled_rules[$k]);
				}
			}
			$iface['rulesets'] = implode("||", $enabled_rules);
		}
		// Update stored configuration
		config_set_path('installedpackages/snortglobal/rule', $a_ifaces);
	}

	// Clean up
	unset($cats, $enabled_rules);
}

/* Uses XMLRPC to synchronize the changes to a remote node */
function snort_sync_on_changes() {
	global $g;

	/* Do not attempt a package sync while booting up or installing package */
	if (is_platform_booting() || $g['snort_postinstall']) {
		logger(LOG_NOTICE, localize_text("Skipping XMLRPC sync when booting up or during package reinstallation."), LOG_PREFIX_PKG_SNORT);
		return;
	}

	if (config_get_path('installedpackages/snortsync/config/0')) {
		$snort_sync = config_get_path('installedpackages/snortsync/config/0');
		$synconchanges = $snort_sync['varsynconchanges'];
		$synctimeout = $snort_sync['varsynctimeout'] ?: '150';
		$syncdownloadrules = $snort_sync['vardownloadrules'];
		switch ($synconchanges){
			case "manual":
				if (is_array($snort_sync['row'])){
					$rs=$snort_sync['row'];
				} else {
					logger(LOG_NOTICE, localize_text("XMLRPC sync is enabled but there are no hosts configured as replication targets."), LOG_PREFIX_PKG_SNORT);
					return;
				}
				break;
			case "auto":
				if (config_get_path('hasync')) {
					$system_carp = config_get_path('hasync');
					$rs[0]['varsyncipaddress'] = $system_carp['synchronizetoip'];
					$rs[0]['varsyncusername'] = $system_carp['username'];
					$rs[0]['varsyncpassword'] = $system_carp['password'];
					$rs[0]['varsyncsnortstart'] = FALSE;
					$rs[0]['varsyncdestinenable'] = FALSE;
					// XMLRPC sync is currently only supported over connections using the same protocol and port as this system
					if (config_get_path('system/webgui/protocol') == "http") {
						$rs[0]['varsyncprotocol'] = "http";
						$rs[0]['varsyncport'] = config_get_path('system/webgui/port') ?: '80';
					} else {
						$rs[0]['varsyncprotocol'] = "https";
						$rs[0]['varsyncport'] = config_get_path('system/webgui/port') ?: '443';
					}
					if ($system_carp['synchronizetoip'] == "") {
						logger(LOG_NOTICE, localize_text("XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets."), LOG_PREFIX_PKG_SNORT);
						return;
					} else {
						$rs[0]['varsyncdestinenable'] = TRUE;
					}
				} else {
					logger(LOG_NOTICE, localize_text("XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets."), LOG_PREFIX_PKG_SNORT);
					return;
				}
				break;			
			default:
				return;
				break;
		}
		if (is_array($rs)){
			logger(LOG_NOTICE, localize_text("XMLRPC sync is starting."), LOG_PREFIX_PKG_SNORT);
			foreach ($rs as $sh){
				// Only sync enabled replication targets
				if ($sh['varsyncdestinenable']) {
					if ($sh['varsyncsnortstart']) {
						$syncstartsnort = $sh['varsyncsnortstart'];
					} else {
						$syncstartsnort = "OFF";
					}
					$sync_to_ip = $sh['varsyncipaddress'];
					$port = $sh['varsyncport'];
					$password = $sh['varsyncpassword'];
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
						snort_do_xmlrpc_sync($syncdownloadrules, $sync_to_ip, $port, $protocol, $username, $password, $synctimeout, $syncstartsnort);
					} else {
						logger(LOG_ERR, localize_text("XMLRPC sync with '%s' aborted due to the following error(s): %s", $sync_to_ip, $error), LOG_PREFIX_PKG_SNORT);
					}
				}
			}
			logger(LOG_NOTICE, localize_text("XMLRPC sync completed."), LOG_PREFIX_PKG_SNORT);
		}
 	}
}

if(!function_exists('pf_version')) {
	function pf_version() {
		return substr(trim(file_get_contents("/etc/version")), 0, 3);
	}
}

/* Do the actual XMLRPC sync */
function snort_do_xmlrpc_sync($syncdownloadrules, $sync_to_ip, $port, $protocol, $username, $password, $synctimeout, $syncstartsnort) {
	global $g;

	/* Do not attempt a package sync while booting up or installing package */
	if (is_platform_booting() || isset($g['snort_postinstall'])) {
		logger(LOG_NOTICE, localize_text("Skipping XMLRPC sync when booting up or during package reinstallation."), LOG_PREFIX_PKG_SNORT);
		return;
	}

	if ($username == "" || $password == "" || $sync_to_ip == "" || $port == "" || $protocol == "") {
		logger(LOG_ERR, localize_text("A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync"), LOG_PREFIX_PKG_SNORT);
		return;
	}

	$downloadrulescmd = "";
	if ($syncdownloadrules == "yes") {
		$downloadrulescmd = "logger(LOG_NOTICE, localize_text(\"XMLRPC pkg sync: Update of downloaded rule sets requested...\"), LOG_PREFIX_PKG_SNORT);\n";
		$downloadrulescmd .= "\tinclude_once(\"/usr/local/pkg/snort/snort_check_for_rule_updates.php\");\n";
	}
	$snortstart = "";
	if ($syncstartsnort == "ON") {
		$snortstart = "logger(LOG_NOTICE, localize_text(\"XMLRPC pkg sync: Checking Snort status...\"), LOG_PREFIX_PKG_SNORT);\n";
		$snortstart .= "\tif (!is_process_running(\"snort\")) {\n";
		$snortstart .= "\t\tlogger(LOG_NOTICE, localize_text(\"XMLRPC pkg sync: Snort not running. Sending a start command...\"), LOG_PREFIX_PKG_SNORT);\n";
		$snortstart .= "\t\t\$sh_script = RCFILEPREFIX . \"snort.sh\";\n";
		$snortstart .= "\t\tmwexec_bg(\"{\$sh_script} start\");\n\t}\n";
		$snortstart .= "\telse {\n\t\tlogger(LOG_NOTICE, localize_text(\"XMLRPC pkg sync: Snort is running...\"), LOG_PREFIX_PKG_SNORT);\n\t}\n";
	}

	/*************************************************/
	/* Build a series of commands as a PHP file for  */
	/* the secondary host to execute to load the new */
	/* settings.                                     */
	/*************************************************/
	$snort_sync_cmd = <<<EOD
	<?php
	require_once("/usr/local/pkg/snort/snort.inc");
	require_once("service-utils.inc");
	global \$g, \$rebuild_rules, \$snort_gui_include, \$pkg_interface;
	\$orig_pkg_interface = \$pkg_interface;
	\$g["snort_postinstall"] = true;
	\$g["snort_sync_in_progress"] = true;
	\$snort_gui_include = false;
	\$pkg_interface = "console";
	{$downloadrulescmd}
	unset(\$g["snort_postinstall"]);
	logger(LOG_NOTICE, localize_text("XMLRPC pkg sync: Generating %s file using Master Host settings...", 'snort.conf'), LOG_PREFIX_PKG_SNORT);
	\$rebuild_rules = true;
	sync_snort_package_config();
	\$rebuild_rules = false;
	{$snortstart}
	logger(LOG_NOTICE, localize_text("XMLRPC pkg sync process on this host is complete..."), LOG_PREFIX_PKG_SNORT);
	\$pkg_interface = \$orig_pkg_interface;
	unset(\$g["snort_sync_in_progress"]);
	return true;
	?>

EOD;

	/*************************************************/
	/* First, have target host write the commands    */ 
	/* to a PHP file in the /tmp directory.          */
	/*************************************************/
	$execcmd = "file_put_contents('/tmp/snort_sync_cmds.php', '{$snort_sync_cmd}');";
	
	/*************************************************/
	/* Now assemble a command to execute the         */
	/* previously sent PHP file in the background.   */
	/*************************************************/
	$execcmd2 = "exec(\"/usr/local/bin/php-cgi -f '/tmp/snort_sync_cmds.php' > /dev/null 2>&1 &\");";

	$synctimeout = intval($synctimeout);
	$rpc_client = new pfsense_xmlrpc_client();
	$rpc_client->setConnectionData($sync_to_ip, $port, $username, $password, $protocol);
	
	/*************************************************/
	/* Send over any auto-SID management files       */
	/*************************************************/
	$sid_files = glob(SNORT_SID_MODS_PATH . '*');
	foreach ($sid_files as $file) {
		$content = base64_encode(file_get_contents($file));
		$payload = "@file_put_contents('{$file}', base64_decode('{$content}'));";
		$resp = $rpc_client->xmlrpc_exec_php($payload, $synctimeout);
	}
	/*************************************************/
	/* Send over any IPREP IP List files             */
	/*************************************************/
	$iprep_files = glob(SNORT_IPREP_PATH . '*');
	foreach ($iprep_files as $file) {
		$content = base64_encode(file_get_contents($file));
		$payload = "@file_put_contents('{$file}', base64_decode('{$content}'));";
		$resp = $rpc_client->xmlrpc_exec_php($payload, $synctimeout);
	}

	/*************************************************/
	/* Sync the configuration in manual sync mode.   */
	/* For HA the configuration itself is instead    */
	/* synced via the XMLRPC plugins - the function  */
	/* snort_do_xmlrpc_sync() is called after.       */
	/*************************************************/
	if (config_get_path('installedpackages/snortsync/config/0/varsynconchanges') != 'auto') {
		/*****************************************************/
		/* Send over the <snortglobal> portion of config.xml */
		/* $xml will hold the section to sync.               */
		/*****************************************************/
		$xml = array();
		$xml['snortglobal'] = config_get_path('installedpackages/snortglobal');

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

function snort_plugin_xmlrpc_send() {
	if (config_get_path('installedpackages/snortsync/config/0/varsynconchanges') != 'auto') {
		return [];
	}

	return [
		'installedpackages/snortsync/config/0/varsynconchanges',
		'installedpackages/snortglobal'
	];
}

function snort_plugin_xmlrpc_recv($new_sections) {
	if (array_get_path($new_sections, 'installedpackages/snortsync/config/0/varsynconchanges') != 'auto') {
		return [];
	}

	$section_paths = [
		'installedpackages/snortglobal'
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

function snort_get_vpns_list() {

	$vpns = "";
	$vpns_arr = array();

	/* IPsec */
	if (!function_exists('ipsec_enabled')) {
		require_once("ipsec.inc");
	}
	if (ipsec_enabled()) {
		if (config_get_path('ipsec/client') && config_path_enabled('ipsec/client')) {
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
			if (is_array($settings) && !empty($settings)) {
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

?>
