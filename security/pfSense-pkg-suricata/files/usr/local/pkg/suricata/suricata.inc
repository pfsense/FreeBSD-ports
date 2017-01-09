<?php
/*
 * suricata.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2016 Bill Meeks
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
require("/usr/local/pkg/suricata/suricata_defs.inc");

global $g, $config;

// Suricata GUI needs some extra PHP memory space to manipulate large rules arrays
ini_set("memory_limit", "512M");

function suricata_generate_id() {
	global $config;

	$suricatacfg = $config['installedpackages']['suricata']['rule'];
	while (true) {
		$suricata_uuid = mt_rand(1, 65535);
		foreach ($suricatacfg as $value) {
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

function suricata_barnyard_stop($suricatacfg, $if_real) {
	global $g;

	$suricata_uuid = $suricatacfg['uuid'];
	if (isvalidpid("{$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid")) {
		log_error("[Suricata] Barnyard2 STOP for {$suricatacfg['descr']}({$if_real})...");
		killbypid("{$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid");
	}
}

function suricata_stop($suricatacfg, $if_real) {
	global $g;

	$suricata_uuid = $suricatacfg['uuid'];
	if (isvalidpid("{$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid")) {
		log_error("[Suricata] Suricata STOP for {$suricatacfg['descr']}({$if_real})...");
		killbypid("{$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid");
		sleep(1);

		// For some reason Suricata seems to need a double TERM signal to actually shutdown
		if (isvalidpid("{$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid")) {
			killbypid("{$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid");
		}
	}
	// Make sure the PID file is actually deleted so Suricata will restart without error
	unlink_if_exists("{$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid");

	// Stop Barnyard2 on the interface if running
	suricata_barnyard_stop($suricatacfg, $if_real);
}

function suricata_barnyard_start($suricatacfg, $if_real) {
	global $g;

	$suricata_uuid = $suricatacfg['uuid'];
	$suricatadir = SURICATADIR . "suricata_{$suricata_uuid}_{$if_real}";
	$suricatalogdir = SURICATALOGDIR . "suricata_{$if_real}{$suricata_uuid}";
	$suricatabindir = SURICATA_PBI_BINDIR;

	if ($suricatacfg['barnyard_enable'] == 'on') {
		log_error("[Suricata] Barnyard2 START for {$suricatacfg['descr']}({$if_real})...");
		mwexec_bg("{$suricatabindir}barnyard2 -r {$suricata_uuid} -f unified2.alert --pid-path {$g['varrun_path']} --nolock-pidfile -c {$suricatadir}/barnyard2.conf -d {$suricatalogdir} -D -q");
	}
}

function suricata_start($suricatacfg, $if_real) {
	global $g;

	$suricatadir = SURICATADIR;
	$suricata_uuid = $suricatacfg['uuid'];
	$suricatabindir = SURICATA_PBI_BINDIR;

	if ($suricatacfg['enable'] == 'on') {
		$run_mode = $suricatacfg['ips_mode'] == 'ips_mode_inline' ? '--netmap' : '-i ' . $if_real;
		log_error("[Suricata] Suricata START for {$suricatacfg['descr']}({$if_real})...");
		mwexec_bg("{$suricatabindir}suricata {$run_mode} -D -c {$suricatadir}suricata_{$suricata_uuid}_{$if_real}/suricata.yaml --pidfile {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid");
	} else {
		return;
	}

	// Start Barnyard2 if enabled on the interface
	suricata_barnyard_start($suricatacfg, $if_real);
}

function suricata_start_all_interfaces($background=FALSE) {

	/*************************************************************/
	/* This function starts all configured and enabled Suricata  */
	/* interfaces.                                               */
	/*************************************************************/

	global $g, $config;

	/* do nothing if no Suricata interfaces active */
	if (!is_array($config['installedpackages']['suricata']['rule'])) {
		return;
	}

	foreach ($config['installedpackages']['suricata']['rule'] as $suricatacfg) {
		if ($suricatacfg['enable'] != 'on') {
			continue;
		}
		suricata_start($suricatacfg, get_real_interface($suricatacfg['interface']));
	}
}

function suricata_stop_all_interfaces() {

	/*************************************************************/
	/* This function stops all configured Suricata interfaces.   */
	/*************************************************************/

	global $g, $config;

	/* do nothing if no Suricata interfaces active */
	if (!is_array($config['installedpackages']['suricata']['rule'])) {
		return;
	}

	foreach ($config['installedpackages']['suricata']['rule'] as $suricatacfg) {
		suricata_stop($suricatacfg, get_real_interface($suricatacfg['interface']));
	}
}

function suricata_restart_all_interfaces() {

	/*************************************************************/
	/* This function stops all configured Suricata interfaces    */
	/* and restarts enabled Suricata interfaces.                 */
	/*************************************************************/

	global $g, $config;

	/* do nothing if no Suricata interfaces active */
	if (!is_array($config['installedpackages']['suricata']['rule'])) {
		return;
	}

	suricata_stop_all_interfaces();
	sleep(2);
	suricata_start_all_interfaces(TRUE);
}

function suricata_reload_config($suricatacfg, $signal="USR2") {

	/**************************************************************/
	/* This function sends the passed SIGNAL to the Suricata      */
	/* instance on the passed interface to cause Suricata to      */
	/* reload and parse the running configuration without         */
	/* impacting packet processing.  It also executes the reload  */
	/* as a background process and returns control immediately    */
	/* to the caller.                                             */
	/*                                                            */
	/*  $signal = USR2 (default) parses and reloads config.       */
	/**************************************************************/
	global $g;

	$suricatadir = SURICATADIR;
	$suricata_uuid = $suricatacfg['uuid'];
	$if_real = get_real_interface($suricatacfg['interface']);

	/******************************************************/
	/* Only send the SIGUSR2 if Suricata is running and   */
	/* we can find a valid PID for the process.           */
	/******************************************************/
	if (isvalidpid("{$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid")) {
		log_error("[Suricata] Suricata LIVE RULE RELOAD initiated for {$suricatacfg['descr']} ({$if_real})...");
		mwexec_bg("/bin/pkill -{$signal} -F {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid");
	}
}

function suricata_barnyard_reload_config($suricatacfg, $signal="HUP") {

	/**************************************************************/
	/* This function sends the passed SIGNAL to the Barnyard2     */
	/* instance on the passed interface to cause Barnyard to      */
	/* reload and parse the running configuration without         */
	/* impacting packet processing.  It also executes the reload  */
	/* as a background process and returns control immediately    */
	/* to the caller.                                             */
	/*                                                            */
	/*  $signal = HUP (default) parses and reloads config.        */
	/**************************************************************/
	global $g;

	$suricatadir = SURICATADIR;
	$suricata_uuid = $suricatacfg['uuid'];
	$if_real = get_real_interface($suricatacfg['interface']);

	/******************************************************/
	/* Only send the SIGHUP if Barnyard2 is running and   */
	/* we can find a valid PID for the process.           */
	/******************************************************/
	if (isvalidpid("{$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid")) {
		log_error("[Suricata] Barnyard2 CONFIG RELOAD initiated for {$suricatacfg['descr']} ({$if_real})...");
		mwexec_bg("/bin/pkill -{$signal} -F {$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid");
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

/* func builds custom Pass Lists */
function suricata_find_list($find_name, $type = 'passlist') {
	global $config;

	$suricataglob = $config['installedpackages']['suricata'];
	if (!is_array($suricataglob[$type])) {
		return "";
	}
	if (!is_array($suricataglob[$type]['item'])) {
		return "";
	}

	foreach ($suricataglob[$type]['item'] as $value) {
		if ($value['name'] == $find_name) {
			return $value;
		}
	}

	return array();
}

function suricata_build_list($suricatacfg, $listname = "", $passlist = false, $externallist = false) {

	/***********************************************************/
	/* The default is to build a HOME_NET variable unless      */
	/* '$passlist' is set to 'true' when calling.             */
	/***********************************************************/

	global $config, $g, $aliastable, $filterdns;
	$home_net = array();

	if (!$externallist && ($listname == 'default' || empty($listname))) {
		$localnet = 'yes'; $wanip = 'yes'; $wangw = 'yes'; $wandns = 'yes'; $vips = 'yes'; $vpns = 'yes';
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
		if (!empty($list['address']) && is_alias($list['address'])) {
			$home_net = explode(" ", trim(filter_expand_alias($list['address'])));
		}
	}

	// Always add loopback to HOME_NET and passlist
	if (!$externallist) {
		if (!in_array("127.0.0.1/32", $home_net)) {
			$home_net[] = "127.0.0.1/32";
		}
		if (!in_array("::1/128", $home_net)) {
			$home_net[] = "::1/128";
		}
	}

	/********************************************************************/
	/* Always put the interface running Suricata in HOME_NET and        */
	/* pass list unless it's the WAN.  WAN options are handled further  */
	/* down.  If the user specifically chose not to include LOCAL_NETS  */
	/* in the PASS LIST, then do not include the Suricata interface     */
	/* subnet in the PASS LIST. We do include the actual LAN interface  */
	/* IP for Suricata, though, to prevent locking out the firewall.    */
	/********************************************************************/
	$suricataip = get_interface_ip($suricatacfg['interface']);
	if (($externallist && $localnet == 'yes') || (!$externallist && (!$passlist || $localnet == 'yes' || empty($localnet)))) {
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
	elseif (!$externallist && $localnet != 'yes') {
		if (is_ipaddrv4($suricataip)) {
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
	if (($externallist && $localnet == 'yes') || (!$externallist && (!$passlist || $localnet == 'yes' || empty($localnet)))) {
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
	elseif (!$externallist && $localnet != 'yes') {
		if (is_ipaddrv6($suricataip)) {
			if (!in_array($suricataip . "/128", $home_net)) {
				$home_net[] = $suricataip . "/128";
			}
		}
	}

	// Add link-local address if user included locally-attached networks
	$suricataip = get_interface_linklocal($suricatacfg['interface']);
	if (!empty($suricataip) && $localnet == 'yes') {
		// Trim off the interface designation (e.g., %em1) if present
		if (strpos($suricataip, "%") !== FALSE) {
			$suricataip = substr($suricataip, 0, strpos($suricataip, "%"));
		}
		if (!in_array($suricataip . "/128", $home_net)) {
			$home_net[] = $suricataip . "/128";
		}
	}

	if (($$externallist && $localnet == 'yes') || (!$externallist && (!$passlist || $localnet == 'yes' || empty($localnet)))) {		
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

			// Add link-local address
			$suricataip = get_interface_linklocal($int);
			if (!empty($suricataip)) {
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

	if ($wanip == 'yes') {
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
		// Explicitly grab the WAN Link-Local address
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

	if ($wandns == 'yes') {
		// Add DNS server for WAN interface to Pass List
		$dns_servers = get_dns_servers();
		foreach ($dns_servers as $dns) {
			if (is_ipaddrv4($dns)) {
				$dns .= "/32";
			} elseif (is_ipaddrv6($dns)) {
				$dns .= "/128";
			}
			if ($dns && !in_array($dns, $home_net)) {
				$home_net[] = $dns;
			}
		}
	}

	if($vips == 'yes') {
		// iterate all vips and add to passlist
		if (is_array($config['virtualip']) && is_array($config['virtualip']['vip'])) {
			foreach($config['virtualip']['vip'] as $vip) {
				if ($vip['subnet']) {
					if (!in_array("{$vip['subnet']}/{$vip['subnet_bits']}", $home_net)) {
						$home_net[] = "{$vip['subnet']}/{$vip['subnet_bits']}";
					}
				}
			}
		}
	}

	// Grab a list of vpns enabled - these come back as CIDR mask networks
	if ($vpns == 'yes') {
		$vpns_list = filter_get_vpns_list();
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

	$valresult = array();
	foreach ($home_net as $vald) { 
		if (empty($vald)) {
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

	global $config, $g;

	if (!is_array($config['cron'])) {
		$config['cron'] = array();
	}
	if (!is_array($config['cron']['item'])) {
		$config['cron']['item'] = array();
	}

	foreach($config['cron']['item'] as $item) {
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
	global $config, $g;

	// If called with FALSE as argument, then we're removing 
	// the existing job.
	if ($should_install == FALSE) {
		if (suricata_cron_job_exists("suricata_check_for_rule_updates.php", FALSE)) {
			install_cron_job("suricata_check_for_rule_updates.php", false);
		}
		return;
	}

	// Get auto-rule update parameter from configuration
	$suricata_rules_up_info_ck = $config['installedpackages']['suricata']['config'][0]['autoruleupdate'];

	// See if a customized start time has been set for rule file updates
	if (!empty($config['installedpackages']['suricata']['config'][0]['autoruleupdatetime'])) {
		$suricata_rules_upd_time = $config['installedpackages']['suricata']['config'][0]['autoruleupdatetime'];
	} else {
		$suricata_rules_upd_time = "00:03";
	}

	if ($suricata_rules_up_info_ck == "6h_up") {
		$suricata_rules_up_min = intval(substr($suricata_rules_upd_time, -2));
		$hour = intval(substr($suricata_rules_upd_time, 0, 2));
		$suricata_rules_up_hr = strval($hour);
		for ($i=0; $i<3; $i++) {
			$hour += 6;
			if ($hour > 24) {
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
		if ($hour > 24) {
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
	global $config, $g;
	$suri_pf_table = SURICATA_PF_TABLE;

	// See if simply removing existing "expiretable" job for Suricata
	if ($should_install == FALSE) {
		if (suricata_cron_job_exists("{$suri_pf_table}", FALSE)) {
			install_cron_job("{$suri_pf_table}", false);
		}
		return;
	}

	$suricata_rm_blocked_info_ck = $config['installedpackages']['suricata']['config'][0]['rm_blocked'];

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
	global $config, $g;

	$suricatadir = SURICATADIR;
	$rcdir = RCFILEPREFIX;

	// Do not start config build if there are no Suricata-configured interfaces
	if (!is_array($config['installedpackages']['suricata']['rule']) || count($config['installedpackages']['suricata']['rule']) < 1) {
		return;
	}

	$suricataconf = $config['installedpackages']['suricata']['rule'];
	foreach ($suricataconf as $value) {
		/* Skip configuration of any disabled interface */
		if ($value['enable'] != 'on') {
			continue;
		}

		// create a suricata.yaml file for interface
		suricata_generate_yaml($value);

		// create barnyard2.conf file for interface
		if ($value['barnyard_enable'] == 'on') {
			suricata_generate_barnyard2_conf($value, get_real_interface($value['interface']));
		}
	}

	// create suricata bootup file suricata.sh
	suricata_create_rc();

	// setup the log directory size check job if enabled
	suricata_loglimit_install_cron(true);

	// setup the suricata rules update job if enabled
	suricata_rules_up_install_cron($config['installedpackages']['suricata']['config'][0]['autoruleupdate'] != "never_up" ? true : false);

	// set the suricata blocked hosts time
	suricata_rm_blocked_install_cron($config['installedpackages']['suricata']['config'][0]['rm_blocked'] != "never_b" ? true : false);

	// Do not attempt package sync if reinstalling package or booting
	if (!isset($g['suricata_postinstall']) && !$g['booting']) {
		suricata_sync_on_changes();
	}
}

function suricata_load_suppress_sigs($suricatacfg, $track_by=false) {

	global $config;

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

	if (!is_array($config['installedpackages']['suricata'])) {
		return;
	}
	if (!is_array($config['installedpackages']['suricata']['suppress'])) {
		return;
	}
	if (!is_array($config['installedpackages']['suricata']['suppress']['item'])) {
		return;
	}
	$a_suppress = $config['installedpackages']['suricata']['suppress']['item'];

	foreach ($a_suppress as $a_id => $alist) {
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
							if (!is_array($suppress[$genid])) {
								$suppress[$genid] = array();
							}
							if (!is_array($suppress[$genid][$sigid])) {
								$suppress[$genid][$sigid] = array();
							}
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
								if (!is_array($suppress[$genid])) {
									$suppress[$genid] = array();
								}
								if (!is_array($suppress[$genid][$sigid])) {
									$suppress[$genid][$sigid] = array();
								}
								if (!is_array($suppress[$genid][$sigid][$whichip])) {
									$suppress[$genid][$sigid][$whichip] = array();
								}
								if (!is_array($suppress[$genid][$sigid][$whichip][$ip])) {
									$suppress[$genid][$sigid][$whichip][$ip] = array();
								}
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
								if (!is_array($suppress[$genid])) {
									$suppress[$genid] = array();
								}
								if (!is_array($suppress[$genid][$sigid])) {
									$suppress[$genid][$sigid] = array();
								}
								if (!is_array($suppress[$genid][$sigid][$whichip])) {
									$suppress[$genid][$sigid][$whichip] = array();
								}
								if (!is_array($suppress[$genid][$sigid][$whichip][$ip])) {
									$suppress[$genid][$sigid][$whichip][$ip] = array();
								}
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
	unset($alist);
	return $suppress;
}

function suricata_post_delete_logs($suricata_uuid = 0) {

	/***********************************************/
	/* This function cleans up related log files   */
	/* for the passed instance.  These include     */
	/* Barnyard2 unified2 logs and pcap logs.      */
	/***********************************************/

	global $config, $g;

	// do nothing if no Suricata interfaces active
	if (!is_array($config['installedpackages']['suricata']['rule'])) {
		return;
	}

	foreach ($config['installedpackages']['suricata']['rule'] as $value) {
		if ($value['uuid'] != $suricata_uuid) {
			continue;
		}
		$if_real = get_real_interface($value['interface']);
		$suricata_log_dir = SURICATALOGDIR . "suricata_{$if_real}{$suricata_uuid}";

		if ($if_real != '') {
			/* Clean-up Barnyard2 files if any exist */
			$filelist = glob("{$suricata_log_dir}/unified2.alert.*");
			// Keep most recent file
			unset($filelist[count($filelist) - 1]);
			foreach ($filelist as $file) {
				unlink_if_exists($file);
			}

			/* Clean-up Barnyard2 archived files if any exist */
			$filelist = glob("{$suricata_log_dir}/barnyard2/archive/unified2.alert.*");
			foreach ($filelist as $file) {
				unlink_if_exists($file);
			}

			/* Clean-up packet capture files if any exist */
			$filelist = glob("{$suricata_log_dir}/log.pcap.*");
			// Keep most recent file
			unset($filelist[count($filelist) - 1]);
			foreach ($filelist as $file) {
				unlink_if_exists($file);
			}
			unset($filelist);
		}
	}
}

/* This returns size of passed directory or file in 1024-byte blocks */
function suricata_Getdirsize($node) {
	if(!is_readable($node)) {
		return false;
	}

	$blah = exec( "/usr/bin/du -kdc $node" );
	return substr( $blah, 0, strpos($blah, 9) );
}

function suricata_build_sid_msg_map($rules_path, $sid_file) {

	/*************************************************************/
	/* This function reads all the rules file in the passed      */
	/* $rules_path variable and produces a properly formatted    */
	/* sid-msg.map v2 file for use by Suricata and barnyard2.    */
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
	 *  map[gid][sid]['rule']['category']['action']['disabled']['managed']['flowbits']
	 *
	 *  where:
	 *   gid           = Generator ID from rule, or 1 if general text 
	 *                   rule
	 *   sid           = Signature ID from rule
         *   rule          = Complete rule text
	 *   category      = File name of file containing the rule
	 *   action        = alert, drop, reject or pass
	 *   disabled      = 1 if rule is disabled (commented out), 0 if 
	 *                   rule is enabled
	 *   managed       = 1 if rule is auto-managed by SID MGMT process,
	 *                   0 if not auto-managed 
	 *   state_toggled = 1 if rule was toggled by SID MGMT process,
	 *                   0 if not toggled
	 *   modified      = 1 if rule action or content is modified by SID MGMT process,
	 *                   0 if not modified
	 *   flowbits      = Array of applicable flowbits if rule contains 
	 *                   flowbits options
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
			if (!is_array($map_ref[$gid])) {
				$map_ref[$gid] = array();
			}
			if (!is_array($map_ref[$gid][$sid])) {
				$map_ref[$gid][$sid] = array();
			}			
			$map_ref[$gid][$sid]['rule'] = $rule;
			$map_ref[$gid][$sid]['category'] = basename($file, ".rules");
			$map_ref[$gid][$sid]['state_toggled'] = 0;
			$map_ref[$gid][$sid]['modified'] = 0;

			// Grab the rule action
			$matches = array();
			if (preg_match('/^\s*#*\s*(alert|drop|pass|reject)/i', $rule, $matches)) {
				$map_ref[$gid][$sid]['action'] = $matches[1];
			} else {
				$map_ref[$gid][$sid]['action'] = "";
			}

			// Determine if default state is "disabled"
			if (preg_match('/^\s*\#+/', $rule)) {
				$map_ref[$gid][$sid]['disabled'] = 1;
			} else {
				$map_ref[$gid][$sid]['disabled'] = 0;
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
							if (!is_array($checked_flowbits[$t])) {
								$checked_flowbits[$t] = array();
							}
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
							if (!is_array($set_flowbits[$t])) {
								$set_flowbits[$t] = array();
							}
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

function suricata_find_flowbit_required_rules($rules, $unchecked_flowbits) {

	/********************************************************/
	/* This function finds all rules that must be enabled   */
	/* in order to satisfy the "checked flowbits" used by   */
	/* the currently enabled rules.  It returns the list    */
	/* of required rules in an array.                       */
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
						if (!is_array($required_flowbits_rules[$k1])) {
							$required_flowbits_rules[$k1] = array();
						}
						if (!is_array($required_flowbits_rules[$k1][$k2])) {
							$required_flowbits_rules[$k1][$k2] = array();
						}
						$required_flowbits_rules[$k1][$k2]['category'] = $rule2['category'];
						if ($rule2['disabled'] == 0) {
							// If not disabled, just return the rule text "as is"
							$required_flowbits_rules[$k1][$k2]['rule'] = ltrim($rule2['rule']);
						} else {
							// If rule is disabled, remove leading '#' to enable it
							$required_flowbits_rules[$k1][$k2]['rule'] = ltrim(substr($rule2['rule'], strpos($rule2['rule'], "#") + 1));
							$required_flowbits_rules[$k1][$k2]['disabled'] = 0;
						}
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
		log_error(gettext("[Suricata] WARNING: Flowbit resolution not done - no rules in {$suricatadir}rules/ ..."));
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
	$required_rules = suricata_find_flowbit_required_rules($rules, $delta_flowbits);

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

function suricata_load_vrt_policy($policy, $all_rules=null) {

	/************************************************/
	/* This function returns an array of all rules  */
	/* marked with the passed in $policy metadata.  */
	/*                                              */
	/*    $policy --> desired VRT security policy   */
	/*                  1.  connectivity            */
	/*                  2.  balanced                */
	/*                  3.  security                */
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
		$suricata_vrt_files = glob("{$suricatadir}rules/{$suricata_file_pattern}");
		$all_rules = suricata_load_rules_map($suricata_vrt_files);
	}

	// Now walk the rules list and find all those that are
	// defined as active for the chosen security policy.
	foreach ($all_rules as $k1 => $arulem) {
		foreach ($arulem as $k2 => $arulem2) {
			if (strripos($arulem2['rule'], "policy {$policy}-ips") !== false) {
				if (!preg_match('/flowbits\s*:\s*noalert/i', $arulem2['rule'])) {
					if (!is_array($vrt_policy_rules[$k1])) {
						$vrt_policy_rules[$k1] = array();
					}
					if (!is_array($vrt_policy_rules[$k1][$k2])) {
						$vrt_policy_rules[$k1][$k2] = array();
					}
					$vrt_policy_rules[$k1][$k2] = $arulem2;

					// Enable the policy rule if disabled
					if ($arulem2['disabled'] == 1) {
						$vrt_policy_rules[$k1][$k2]['rule'] = ltrim(substr($arulem2['rule'], strpos($arulem2['rule'], "#") + 1));
						$vrt_policy_rules[$k1][$k2]['disabled'] = 0;
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

function suricata_parse_sidconf_file($sidconf_file) {

	/**********************************************/
	/* This function loads and processes the file */
	/* specified by '$sidconf_file'.  The file is */
	/* assumed to contain valid instructions for  */
	/* matching rule SIDs as supported by the     */
	/* Oinkmaster and PulledPork utilities.       */
	/*                                            */
	/*  $sidconf_file ==> full path and name of   */
	/*                    file to process         */
	/*                                            */
	/*        Returns ==> an array containing     */
	/*                    SID modifier tokens     */
	/**********************************************/

	$buf = "";
	$sid_mods = array();

	$fd = fopen("{$sidconf_file}", "r");
	if ($fd == FALSE) {
		log_error("[Suricata] Failed to open SID MGMT file '{$sidconf_file}' for processing.");
		return $sid_mods;
	}

	// Read and parse the conf file line-by-line
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

		// Now split the SID mod arguments at the commas, if more than one
		// per line, and add to our $sid_mods array.
		$line = explode(",", $buf);
		foreach ($line as $ent) {
			$sid_mods[] = trim($ent);
		}
	}

	// Close the file, release unneeded memory and return
	// the array of SID mod tokens parsed from the file.
	fclose($fd);
	unset($line, $buf);
	return $sid_mods;
}

function suricata_sid_mgmt_auto_categories($suricatacfg, $log_results = FALSE) {

	/****************************************************/
	/* This function parses any auto-SID conf files     */
	/* configured for the interface and returns an      */
	/* array of rule categories adjusted from the       */
	/* ['enabled_rulesets'] element in the config for   */
	/* the interface in accordance with the contents    */
	/* of the SID Mgmt conf files.                      */
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

	global $config;
	$suricata_sidmods_dir = SURICATA_SID_MODS_PATH;
	$sid_mods = array();
	$enables = array();
	$disables = array();
	$drops = array();

	// Check if auto-mgmt of SIDs is enabled, exit if not
	if ($config['installedpackages']['suricata']['config'][0]['auto_manage_sids'] != 'on') {
		return array();
	}
	if (empty($suricatacfg['disable_sid_file']) && empty($suricatacfg['enable_sid_file']) && empty($suricatacfg['drop_sid_file'])) {
		return array();
	}

	// Configure the interface's logging subdirectory if log results is enabled
	if ($log_results == TRUE) {
		$log_file = SURICATALOGDIR . $suricatalogdir . "suricata_" . get_real_interface($suricatacfg['interface']) . "{$suricatacfg['uuid']}/sid_changes.log";
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
					error_log(gettext("Processing disable_sid file: {$suricatacfg['disable_sid_file']}\n"), 3, $log_file);
				}

				// Attempt to open the 'disable_sid_file' for the interface
				if (!file_exists("{$suricata_sidmods_dir}{$suricatacfg['disable_sid_file']}")) {
					log_error(gettext("[Suricata] Error - unable to open 'disable_sid_file' \"{$suricatacfg['disable_sid_file']}\" specified for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])));
					if ($log_results == TRUE) {
						error_log(gettext("Unable to open disable_sid file \"{$suricatacfg['disable_sid_file']}\".\n"), 3, $log_file);
					}
				} else {
					$sid_mods = suricata_parse_sidconf_file("{$suricata_sidmods_dir}{$suricatacfg['disable_sid_file']}");
				}

				if (!empty($sid_mods)) {
					$disables = suricata_get_auto_category_mods($enabled_cats, $sid_mods, "disable", $log_results, $log_file);
				} elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in file \"{$suricatacfg['disable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			if (!empty($suricatacfg['enable_sid_file'])) {
				if ($log_results == TRUE) {
					error_log(gettext("Processing enable_sid file: {$suricatacfg['enable_sid_file']}\n"), 3, $log_file);
				}

				// Attempt to open the 'enable_sid_file' for the interface
				if (!file_exists("{$suricata_sidmods_dir}{$suricatacfg['enable_sid_file']}")) {
					log_error(gettext("[Suricata] Error - unable to open 'enable_sid_file' \"{$suricatacfg['enable_sid_file']}\" specified for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])));
					if ($log_results == TRUE) {
						error_log(gettext("Unable to open enable_sid file \"{$suricatacfg['enable_sid_file']}\".\n"), 3, $log_file);
					}
				} else {
					$sid_mods = suricata_parse_sidconf_file("{$suricata_sidmods_dir}{$suricatacfg['enable_sid_file']}");
				}

				if (!empty($sid_mods)) {
					$enables = suricata_get_auto_category_mods($enabled_cats, $sid_mods, "enable", $log_results, $log_file);
				} elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in file \"{$suricatacfg['enable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			break;

		case "enable_disable":
			if (!empty($suricatacfg['enable_sid_file'])) {
				if ($log_results == TRUE) {
					error_log(gettext("Processing enable_sid file: {$suricatacfg['enable_sid_file']}\n"), 3, $log_file);
				}

				// Attempt to open the 'enable_sid_file' for the interface
				if (!file_exists("{$suricata_sidmods_dir}{$suricatacfg['enable_sid_file']}")) {
					log_error(gettext("[Suricata] Error - unable to open 'enable_sid_file' \"{$suricatacfg['enable_sid_file']}\" specified for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])));
					if ($log_results == TRUE) {
						error_log(gettext("Unable to open enable_sid file \"{$suricatacfg['enable_sid_file']}\".\n"), 3, $log_file);
					}
				} else {
					$sid_mods = suricata_parse_sidconf_file("{$suricata_sidmods_dir}{$suricatacfg['enable_sid_file']}");
				}

				if (!empty($sid_mods)) {
					$enables = suricata_get_auto_category_mods($enabled_cats, $sid_mods, "enable", $log_results, $log_file);
				} elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in file \"{$suricatacfg['enable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			if (!empty($suricatacfg['disable_sid_file'])) {
				if ($log_results == TRUE) {
					error_log(gettext("Processing disable_sid file: {$suricatacfg['disable_sid_file']}\n"), 3, $log_file);
				}

				// Attempt to open the 'disable_sid_file' for the interface
				if (!file_exists("{$suricata_sidmods_dir}{$suricatacfg['disable_sid_file']}")) {
					log_error(gettext("[Suricata] Error - unable to open 'disable_sid_file' \"{$suricatacfg['disable_sid_file']}\" specified for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])));
					if ($log_results == TRUE) {
						error_log(gettext("Unable to open disable_sid file \"{$suricatacfg['disable_sid_file']}\".\n"), 3, $log_file);
					}
				}
				else {
					$sid_mods = suricata_parse_sidconf_file("{$suricata_sidmods_dir}{$suricatacfg['disable_sid_file']}");
				}

				if (!empty($sid_mods)) {
					$disables = suricata_get_auto_category_mods($enabled_cats, $sid_mods, "disable", $log_results, $log_file);
				} elseif ($log_results == TRUE && !empty($log_file)) {
					error_log(gettext("WARNING: no valid SID match tokens found in file \"{$suricatacfg['disable_sid_file']}\".\n"), 3, $log_file);
				}
			}
			break;

		default:
			log_error(gettext("[Suricata] Unrecognized 'sid_state_order' value.  Skipping auto CATEGORY mgmt step for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])));
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

	$suricatadir = SURICATADIR;
	$all_cats = array();
	$changes = array();
	$counter = 0;
	$matchcount = 0;

	// Get a list of all possible categories by loading all rules files
	foreach (array( VRT_FILE_PREFIX, ET_OPEN_FILE_PREFIX, ET_PRO_FILE_PREFIX, GPL_FILE_PREFIX ) as $prefix) {
		$files = glob("{$suricatadir}rules/{$prefix}*.rules");
		foreach ($files as $file) {
			$all_cats[] = basename($file);
		}
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
	/*                  'enable', 'disable' or    */
	/*		    'drop'.
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

	// If no rules in $rule_map or mods in $sid_mods,
	// then nothing to do.
	if (empty($rule_map) || empty($sid_mods)) {
		return $sids;
	}

	// Validate the action keyword as we only accept
	// 'enable', 'disable' and 'drop' as valid.
	switch ($action) {

		case "enable":
			$log_action = 'enabled';
			break;

		case "disable":
			$log_action = 'disabled';
			break;

		case "drop":
			$log_action = 'drop';
			break;

		default:
			log_error(gettext("[Suricata] Error - unknown action '{$action}' supplied to suricata_modify_sid_state() function...no SIDs modified."));
			return $sids;
	}

	// Walk the SID mod tokens and decode each one.
	// Place all matching GID:SID combos along with
	// the requestion $action into our $sids[] array.
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
				elseif ($action == 'drop' && $rule_map[$k1][$k2]['action'] != 'drop') {
					if ($tmp = preg_replace('/alert/', 'drop', $rule_map[$k1][$k2]['rule'], 1)) {
						$rule_map[$k1][$k2]['rule'] = $tmp;
						$rule_map[$k1][$k2]['action'] = 'drop';
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
		error_log(gettext("    Changed state for {$changecount} SIDs to '{$log_action}'.\n"), 3, $log_file);
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

function suricata_process_enablesid(&$rule_map, $suricatacfg, $log_results = FALSE, $log_file = NULL) {

	/**********************************************/
	/* This function loads and processes the file */
	/* specified by 'enable_sid_file' for the     */
	/* interface.  The file is assumed to be a    */
	/* valid enablesid.conf file containing       */
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

	$suricata_sidmods_dir = SURICATA_SID_MODS_PATH;
	$suricatalogdir = SURICATALOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map)) {
		return;
	}

	// Attempt to open the 'enable_sid_file' for the interface
	if (!file_exists("{$suricata_sidmods_dir}{$suricatacfg['enable_sid_file']}")) {
		log_error(gettext("[Suricata] Error - unable to open 'enable_sid_file' \"{$suricatacfg['enable_sid_file']}\" specified for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])));
		return;
	}
	else
		$sid_mods = suricata_parse_sidconf_file("{$suricata_sidmods_dir}{$suricatacfg['enable_sid_file']}");

	if (!empty($sid_mods))
		suricata_modify_sid_state($rule_map, $sid_mods, "enable", $log_results, $log_file);
	elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in file \"{$suricatacfg['enable_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function suricata_process_disablesid(&$rule_map, $suricatacfg, $log_results = FALSE, $log_file = NULL) {

	/**********************************************/
	/* This function loads and processes the file */
	/* specified by 'disable_sid_file' for the    */
	/* interface.  The file is assumed to be a    */
	/* valid disablesid.conf file containing      */
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

	$suricata_sidmods_dir = SURICATA_SID_MODS_PATH;
	$suricatalogdir = SURICATALOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map)) {
		return;
	}

	// Attempt to open the 'disable_sid_file' for the interface
	if (!file_exists("{$suricata_sidmods_dir}{$suricatacfg['disable_sid_file']}")) {
		log_error(gettext("[Suricata] Error - unable to open 'disable_sid_file' \"{$suricatacfg['disable_sid_file']}\" specified for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])));
		return;
	} else {
		$sid_mods = suricata_parse_sidconf_file("{$suricata_sidmods_dir}{$suricatacfg['disable_sid_file']}");
	}

	if (!empty($sid_mods)) {
		suricata_modify_sid_state($rule_map, $sid_mods, "disable", $log_results, $log_file);
	} elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in file \"{$suricatacfg['disable_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function suricata_process_modifysid(&$rule_map, $suricatacfg, $log_results = FALSE, $log_file = NULL) {

	/**********************************************/
	/* This function loads and processes the file */
	/* specified by 'modify_sid_file' for the     */
	/* interface.  The file is assumed to be a    */
	/* valid modifysid.conf file containing       */
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

	$suricata_sidmods_dir = SURICATA_SID_MODS_PATH;
	$suricatalogdir = SURICATALOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map)) {
		return;
	}

	// Attempt to open the 'modify_sid_file' for the interface
	if (!file_exists("{$suricata_sidmods_dir}{$suricatacfg['modify_sid_file']}")) {
		log_error(gettext("[Suricata] Error - unable to open 'modify_sid_file' \"{$suricatacfg['modify_sid_file']}\" specified for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])));
		return;
	} else {
		$sid_mods = suricata_parse_sidconf_file("{$suricata_sidmods_dir}{$suricatacfg['modify_sid_file']}");
	}

	if (!empty($sid_mods)) {
		suricata_modify_sid_content($rule_map, $sid_mods, $log_results, $log_file);
	} elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in file \"{$suricatacfg['modify_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function suricata_process_dropsid(&$rule_map, $suricatacfg, $log_results = FALSE, $log_file = NULL) {

	/**********************************************/
	/* This function loads and processes the file */
	/* specified by 'drop_sid_file' for the       */
	/* interface.  The file is assumed to be a    */
	/* valid dropsid.conf file containing         */
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

	$suricata_sidmods_dir = SURICATA_SID_MODS_PATH;
	$suricatalogdir = SURICATALOGDIR;
	$sid_mods = array();

	// If no rules in $rule_map, then nothing to do
	if (empty($rule_map)) {
		return;
	}

	// Attempt to open the 'drop_sid_file' for the interface
	if (!file_exists("{$suricata_sidmods_dir}{$suricatacfg['drop_sid_file']}")) {
		log_error(gettext("[Suricata] Error - unable to open 'drop_sid_file' \"{$suricatacfg['drop_sid_file']}\" specified for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])));
		return;
	} else {
		$sid_mods = suricata_parse_sidconf_file("{$suricata_sidmods_dir}{$suricatacfg['drop_sid_file']}");
	}

	if (!empty($sid_mods)) {
		suricata_modify_sid_state($rule_map, $sid_mods, "drop", $log_results, $log_file);
	} elseif ($log_results == TRUE && !empty($log_file)) {
		error_log(gettext("WARNING: no valid SID match tokens found in file \"{$suricatacfg['drop_sid_file']}\".\n"), 3, $log_file);
	}

	unset($sid_mods);
}

function suricata_auto_sid_mgmt(&$rule_map, $suricatacfg, $log_results = FALSE) {

	/**************************************************/
	/* This function modifies the rules in the        */
	/* passed rule_map array based on values in the   */
	/* files 'enable_sid_file', 'disable_sid_file'    */
	/* 'modify_sid_file' abd 'drop_sid_file' for      */
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

	global $config;
	$result = FALSE;

	// Configure the interface's logging subdirectory if log results is enabled
	if ($log_results == TRUE) {
		$log_file = SURICATALOGDIR . $suricatalogdir . "suricata_" . get_real_interface($suricatacfg['interface']) . "{$suricatacfg['uuid']}/sid_changes.log";
	} else {
		$log_file = NULL;
	}

	// Check if auto-mgmt of SIDs is enabled and files are specified
	// for the interface.
	if ($config['installedpackages']['suricata']['config'][0]['auto_manage_sids'] == 'on' &&
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
						error_log(gettext("Processing disable_sid file: {$suricatacfg['disable_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_disablesid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['enable_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing enable_sid file: {$suricatacfg['enable_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_enablesid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['modify_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing modify_sid file: {$suricatacfg['modify_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_modifysid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['drop_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing drop_sid file: {$suricatacfg['drop_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_dropsid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				$result = TRUE;
				break;

			case "enable_disable":
				if (!empty($suricatacfg['enable_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing enable_sid file: {$suricatacfg['enable_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_enablesid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['disable_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing disable_sid file: {$suricatacfg['disable_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_disablesid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['modify_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing modify_sid file: {$suricatacfg['modify_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_modifysid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				if (!empty($suricatacfg['drop_sid_file'])) {
					if ($log_results == TRUE) {
						error_log(gettext("Processing drop_sid file: {$suricatacfg['drop_sid_file']}\n"), 3, $log_file);
					}
					suricata_process_dropsid($rule_map, $suricatacfg, $log_results, $log_file);
				}
				$result = TRUE;
				break;

			default:
				log_error(gettext("[Suricata] Unrecognized 'sid_state_order' value.  Skipping auto SID mgmt step for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface'])));
				if ($log_results == TRUE) {
					error_log(gettext("ERROR: unrecognized 'sid_state_order' value.  Skipping auto SID mgmt step for ") . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']). ".\n", 3, $log_file);
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
			if (!is_array($result[$match[1]])) {
				$result[$match[1]] = array();
			}
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
	$enablesid = suricata_load_sid_mods($suricatacfg['rule_sid_on'], "enablesid");
	$disablesid = suricata_load_sid_mods($suricatacfg['rule_sid_off'], "disablesid");

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

	global $config, $rebuild_rules;

	$suricatadir = SURICATADIR;
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
	log_error(gettext("[Suricata] Updating rules configuration for: " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']) . " ..."));

	// Get any automatic rule category enable/disable modifications
	// if auto-SID Mgmt is enabled and conf files exist for the interface.
	$cat_mods = suricata_sid_mgmt_auto_categories($suricatacfg, TRUE);

	// Only rebuild rules if some are selected or an IPS Policy is enabled
	if (!empty($suricatacfg['rulesets']) || $suricatacfg['ips_policy_enable'] == 'on' || !empty($cat_mods)) {
		$no_rules_defined = false;

		// Load up all the rules into a Rules Map array.
		$all_rules = suricata_load_rules_map("{$suricatadir}rules/");

		// Create an array with the filenames of the enabled
		// rule category files if we have any.
		if (!empty($suricatacfg['rulesets']) || !empty($cat_mods)) {
			// First get all the user-enabled category files
			if (!empty($suricatacfg['rulesets'])) {
				foreach (explode("||", $suricatacfg['rulesets']) as $file){
						$category = basename($file, ".rules");
						if (!is_array($enabled_files[$category])) {
							$enabled_files[$category] = array();
						}
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
						if (!is_array($enabled_rules[$k1])) {
							$enabled_rules[$k1] = array();
						}
						if (!is_array($enabled_rules[$k1][$k2])) {
							$enabled_rules[$k1][$k2] = array();
						}
						$enabled_rules[$k1][$k2]['rule'] = $v['rule'];
						$enabled_rules[$k1][$k2]['category'] = $v['category'];
						$enabled_rules[$k1][$k2]['disabled'] = $v['disabled'];
						$enabled_rules[$k1][$k2]['flowbits'] = $v['flowbits'];
					}
				}
			}

			// Release memory we no longer need.
			unset($enabled_files, $cat_mods, $rulem, $v);
		}

		// Check if a pre-defined Snort VRT policy is selected. If so,
		// add all the VRT policy rules to our enforcing rule set.
		if (!empty($suricatacfg['ips_policy'])) {
			$policy_rules = suricata_load_vrt_policy($suricatacfg['ips_policy'], $all_rules);
			foreach ($policy_rules as $k1 => $policy) {
				foreach ($policy as $k2 => $p) {
					if (!is_array($enabled_rules[$k1])) {
						$enabled_rules[$k1] = array();
					}
					if (!is_array($enabled_rules[$k1][$k2])) {
						$enabled_rules[$k1][$k2] = array();
					}
					$enabled_rules[$k1][$k2]['rule'] = $p['rule'];
					$enabled_rules[$k1][$k2]['category'] = $p['category'];
					$enabled_rules[$k1][$k2]['disabled'] = $p['disabled'];
					$enabled_rules[$k1][$k2]['flowbits'] = $p['flowbits'];
				}
			}
			unset($policy_rules, $policy, $p);
		}

		// Process any enablesid or disablesid modifications for the selected rules.
		// Do the auto-SID managment first, if enabled, then do any manual SID state changes.
		suricata_auto_sid_mgmt($enabled_rules, $suricatacfg, TRUE);
		suricata_modify_sids($enabled_rules, $suricatacfg);

		// Write the enforcing rules file to the Suricata interface's "rules" directory.
		suricata_write_enforcing_rules_file($enabled_rules, "{$suricatacfgdir}/rules/{$suricata_enforcing_rules_file}");

		// If auto-flowbit resolution is enabled, generate the dependent flowbits rules file.
		if ($suricatacfg['autoflowbitrules'] == 'on') {
			log_error('[Suricata] Enabling any flowbit-required rules for: ' . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']) . '...');
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
		unset($all_rules);
	}
	// If no rule categories were enabled, then use auto-SID management if enabled, since it may enable some rules
	elseif ($config['installedpackages']['suricata']['config'][0]['auto_manage_sids'] == 'on' &&
		(!empty($suricatacfg['disable_sid_file']) || !empty($suricatacfg['enable_sid_file']) || 
		!empty($suricatacfg['modify_sid_file']))) {

		suricata_auto_sid_mgmt($enabled_rules, $suricatacfg, TRUE);
		if (!empty($enabled_rules)) {
			// Auto-SID management generated some rules, so use them
			$no_rules_defined = false;
			suricata_modify_sids($enabled_rules, $suricatacfg);

			// Write the enforcing rules file to the Suricata interface's "rules" directory.
			suricata_write_enforcing_rules_file($enabled_rules, "{$suricatacfgdir}/rules/{$suricata_enforcing_rules_file}");

			// If auto-flowbit resolution is enabled, generate the dependent flowbits rules file.
			if ($suricatacfg['autoflowbitrules'] == 'on') {
				log_error('[Suricata] Enabling any flowbit-required rules for: ' . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']) . '...');

				// Load up all rules into a Rules Map array for flowbits assessment
				$all_rules = suricata_load_rules_map("{$suricatadir}rules/");
				$fbits = suricata_resolve_flowbits($all_rules, $enabled_rules);

				// Check for and disable any flowbit-required rules the
				// user has manually forced to a disabled state.
				suricata_modify_sids($fbits, $suricatacfg);
				suricata_write_flowbit_rules_file($fbits, "{$suricatacfgdir}/rules/{$flowbit_rules_file}");
				unset($all_rules, $fbits);
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

	if (!empty($suricatacfg['customrules'])) {
		@file_put_contents("{$suricatacfgdir}/rules/custom.rules", base64_decode($suricatacfg['customrules']));
		$no_rules_defined = false;
	}
	else {
		@file_put_contents("{$suricatacfgdir}/rules/custom.rules", "");
	}

	// Log a warning if the interface has no rules defined or enabled
	if ($no_rules_defined) {
		log_error(gettext("[Suricata] Warning - no text rules selected for: " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']) . " ..."));
	}

	// Build a new sid-msg.map file from the enabled
	// rules and copy it to the interface directory.
	log_error(gettext("[Suricata] Building new sid-msg.map file for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']) . "..."));
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

	global $config, $g;

	$suricatadir = SURICATADIR;
	$suricatalogdir = SURICATALOGDIR;
	$suricatabindir = SURICATA_PBI_BINDIR;
	$rcdir = RCFILEPREFIX;	

	// If no interfaces are configured for Suricata, exit
	if (!is_array($config['installedpackages']['suricata']['rule'])) {
		return;
	}
	$suricataconf = $config['installedpackages']['suricata']['rule'];
	if (empty($suricataconf)) {
		return;
	}

	// At least one interface is configured, so OK
	$start_suricata_iface_start = array();
	$start_suricata_iface_stop = array();

	// Loop thru each configured interface and build
	// the shell script.
	foreach ($suricataconf as $value) {
		// Skip disabled Suricata interfaces
		if ($value['enable'] != 'on') {
			continue;
		}
		$suricata_uuid = $value['uuid'];
		$if_real = get_real_interface($value['interface']);
		$run_mode = $value['ips_mode'] == 'ips_mode_inline' ? '--netmap' : '-i ' . $if_real;

		$start_barnyard = <<<EOE

	if [ ! -f {$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid ]; then
		pid=`/bin/pgrep -fn "barnyard2 -r {$suricata_uuid} "`
	else
		pid=`/bin/pgrep -F {$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid`
	fi

	if [ -z \$pid ]; then
		/usr/bin/logger -p daemon.info -i -t SuricataStartup "Barnyard2 START for {$value['descr']}({$suricata_uuid}_{$if_real})..."
		{$suricatabindir}/barnyard2 -r {$suricata_uuid} -f unified2.alert --pid-path {$g['varrun_path']} --nolock-pidfile -c {$suricatadir}suricata_{$suricata_uuid}_{$if_real}/barnyard2.conf -d {$suricatalogdir}suricata_{$if_real}{$suricata_uuid} -D -q > /dev/null 2>&1
	fi
EOE;
		$stop_barnyard2 = <<<EOE

	if [ -f {$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid ]; then
		/usr/bin/logger -p daemon.info -i -t SuricataStartup "Barnyard2 STOP for {$value['descr']}({$suricata_uuid}_{$if_real})..."
		pid=`/bin/pgrep -F {$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid`
                /bin/pkill -TERM -F {$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid
		time=0 timeout=30
		while /bin/kill -TERM \$pid 2>/dev/null; do
			sleep 1
			time=\$((time+1))
			if [ \$time -gt \$timeout ]; then
				break
			fi
		done
		if [ -f {$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid ]; then
			/bin/rm {$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid
		fi
	else
		pid=`/bin/pgrep -fn "barnyard2 -r {$suricata_uuid} "`
		if [ ! -z \$pid ]; then
			/bin/pkill -TERM -fn "barnyard2 -r {$suricata_uuid} "
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
		if ($value['barnyard_enable'] == 'on') {
			$start_barnyard2 = $start_barnyard;
		} else {
			$start_barnyard2 = $stop_barnyard2;
		}

		$start_suricata_iface_start[] = <<<EOE

	## Start suricata on {$value['descr']} ({$if_real}) ##
	if [ ! -f {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid ]; then
		pid=`/bin/pgrep -fn "suricata {$run_mode} -D -c {$suricatadir}suricata_{$suricata_uuid}_{$if_real}/suricata.yaml "`
	else
		pid=`/bin/pgrep -F {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid`
	fi

	if [ -z \$pid ]; then
		/usr/bin/logger -p daemon.info -i -t SuricataStartup "Suricata START for {$value['descr']}({$suricata_uuid}_{$if_real})..."
		{$suricatabindir}suricata {$run_mode} -D -c {$suricatadir}suricata_{$suricata_uuid}_{$if_real}/suricata.yaml --pidfile {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid > /dev/null 2>&1
	fi

	sleep 1
	{$start_barnyard2}
EOE;

		$start_suricata_iface_stop[] = <<<EOE

	if [ -f {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid ]; then
		pid=`/bin/pgrep -F {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid`
		/usr/bin/logger -p daemon.info -i -t SuricataStartup "Suricata STOP for {$value['descr']}({$suricata_uuid}_{$if_real})..."
		/bin/pkill -TERM -F {$g['varrun_path']}/suricata_{$if_real}{$suricata_uuid}.pid
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
			/usr/bin/logger -p daemon.info -i -t SuricataStartup "Suricata STOP for {$value['descr']}({$suricata_uuid}_{$if_real})..."
			/bin/pkill -TERM -fn "suricata -i {$if_real} "
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

	sleep 1
	{$stop_barnyard2}
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
			/usr/bin/logger -p daemon.info -i -t SuricataStartup "Ignoring additional START command since Suricata is already starting..."
		fi
		;;
	stop)
		rc_stop
		;;
	restart)
		rc_stop
		rc_start
		;;
esac

EOD;

	// Write out the suricata.sh script file
	@file_put_contents("{$rcdir}suricata.sh", $suricata_sh_text);
	@chmod("{$rcdir}suricata.sh", 0755);
	unset($suricata_sh_text);
}

function suricata_generate_barnyard2_conf($suricatacfg, $if_real) {
	global $config, $g;

	$suricata_uuid = $suricatacfg['uuid'];
	$suricatadir = SURICATADIR . "suricata_{$suricata_uuid}_{$if_real}";
	$suricatalogdir = SURICATALOGDIR . "suricata_{$if_real}{$suricata_uuid}";

	// Create required directories for barnyard2 if missing
	if (!is_dir("{$suricatalogdir}/barnyard2"))
		safe_mkdir("{$suricatalogdir}/barnyard2");
	if (!is_dir("{$suricatalogdir}/barnyard2/archive"))
		safe_mkdir("{$suricatalogdir}/barnyard2/archive");

	// Create the barnyard2 waldo file if missing
	if (!file_exists("{$suricatalogdir}/barnyard2/{$suricata_uuid}_{$if_real}.waldo")) {
		@touch("{$suricatalogdir}/barnyard2/{$suricata_uuid}_{$if_real}.waldo");
		mwexec("/bin/chmod 770 {$suricatalogdir}/barnyard2/{$suricata_uuid}_{$if_real}.waldo", true);
	}

	// If there is no gen-msg.map file present, create an
	// empty one so Barnyard2 will at least start.
	if (!file_exists("{$suricatadir}/gen-msg.map"))
		@file_put_contents("{$suricatadir}/gen-msg.map", "");

	if (!empty($suricatacfg['barnyard_sensor_name'])) {
		$suricatabarnyardlog_hostname_info_chk = $suricatacfg['barnyard_sensor_name'];
	} else {
		$suricatabarnyardlog_hostname_info_chk = php_uname("n");
	}

	// Set general config parameters
	$gen_configs = "config quiet\nconfig daemon\nconfig decode_data_link\nconfig alert_with_interface_name\nconfig event_cache_size: 4096";
	if ($suricatacfg['barnyard_show_year'] == 'on') {
		$gen_configs .= "\nconfig show_year";
	}
	if ($suricatacfg['barnyard_obfuscate_ip'] == 'on') {
		$gen_configs .= "\nconfig obfuscate";
	}
	if ($suricatacfg['barnyard_dump_payload'] == 'on') {
		$gen_configs .= "\nconfig dump_payload";
	}
	if ($suricatacfg['barnyard_archive_enable'] == 'on') {
		$gen_configs .= "\nconfig archivedir:          {$suricatalogdir}/barnyard2/archive";
	}

	// Set output plugins
	$suricatabarnyardlog_output_plugins = "";
	if ($suricatacfg['barnyard_mysql_enable'] == 'on') {
		$by2_dbpwd = base64_decode($suricatacfg['barnyard_dbpwd']);
		$suricatabarnyardlog_output_plugins .= "# database: log to a MySQL DB\noutput database: alert, mysql, ";
		$suricatabarnyardlog_output_plugins .= "user={$suricatacfg['barnyard_dbuser']} password={$by2_dbpwd} ";
		$suricatabarnyardlog_output_plugins .= "dbname={$suricatacfg['barnyard_dbname']} host={$suricatacfg['barnyard_dbhost']}";
		if (isset($suricatacfg['barnyard_sensor_name']) && strlen($suricatacfg['barnyard_sensor_name']) > 0) {
			$suricatabarnyardlog_output_plugins .= " sensor_name={$suricatacfg['barnyard_sensor_name']}";
		}
		if ($suricatacfg['barnyard_disable_sig_ref_tbl'] == 'on') {
			$suricatabarnyardlog_output_plugins .= " disable_signature_reference_table";
		}
		$suricatabarnyardlog_output_plugins .= "\n\n";
	}
	if ($suricatacfg['barnyard_syslog_enable'] == 'on') {
		$suricatabarnyardlog_output_plugins .= "# syslog_full: log to a syslog receiver\n";
		$suricatabarnyardlog_output_plugins .= "output alert_syslog_full: sensor_name {$suricatabarnyardlog_hostname_info_chk}, ";
		if ($suricatacfg['barnyard_syslog_local'] == 'on') {
			$suricatabarnyardlog_output_plugins .= "local, log_facility {$suricatacfg['barnyard_syslog_facility']}, log_priority {$suricatacfg['barnyard_syslog_priority']}\n\n";
		} else {
			$suricatabarnyardlog_output_plugins .= "server {$suricatacfg['barnyard_syslog_rhost']}, protocol {$suricatacfg['barnyard_syslog_proto']}, ";
			$suricatabarnyardlog_output_plugins .= "port {$suricatacfg['barnyard_syslog_dport']}, operation_mode {$suricatacfg['barnyard_syslog_opmode']}, ";
			$suricatabarnyardlog_output_plugins .= "log_facility {$suricatacfg['barnyard_syslog_facility']}, log_priority {$suricatacfg['barnyard_syslog_priority']}\n\n";
		}
	}
	if ($suricatacfg['barnyard_bro_ids_enable'] == 'on') {
		$suricatabarnyardlog_output_plugins .= "# alert_bro: log to a Bro-IDS receiver\n";
		$suricatabarnyardlog_output_plugins .= "output alert_bro: {$suricatacfg['barnyard_bro_ids_rhost']}:{$suricatacfg['barnyard_bro_ids_dport']}\n";
	}

	// Trim leading and trailing newlines and spaces
	$suricatabarnyardlog_output_plugins = rtrim($suricatabarnyardlog_output_plugins, "\n");	

	// User pass-through arguments
	$suricatabarnyardlog_config_pass_thru = str_replace("\r", "", base64_decode($suricatacfg['barnconfigpassthru']));

	// Create the conf file as a text string
	$barnyard2_conf_text = <<<EOD

#   barnyard2.conf
#   barnyard2 can be found at http://www.securixlive.com/barnyard2/index.php
#

## General Barnyard2 settings ##
{$gen_configs}
config reference_file:	    {$suricatadir}/reference.config
config classification_file: {$suricatadir}/classification.config
config sid_file:	    {$suricatadir}/sid-msg.map
config gen_file:            {$suricatadir}/gen-msg.map
config hostname:            {$suricatabarnyardlog_hostname_info_chk}
config interface:           {$if_real}
config waldo_file:          {$suricatalogdir}/barnyard2/{$suricata_uuid}_{$if_real}.waldo
config logdir:              {$suricatalogdir}

## START user pass through ##
{$suricatabarnyardlog_config_pass_thru}
## END user pass through ##

## Setup input plugins ##
input unified2

## Setup output plugins ##
{$suricatabarnyardlog_output_plugins}

EOD;

	/* Write out barnyard2_conf text string to disk */
	@file_put_contents("{$suricatadir}/barnyard2.conf", $barnyard2_conf_text);
	unset($barnyard2_conf_text);
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

	global $config, $g;

	$suricatadir = SURICATADIR;
	$suricatalogdir = SURICATALOGDIR;
	$flowbit_rules_file = FLOWBITS_FILENAME;
	$suricata_enforcing_rules_file = SURICATA_ENFORCING_RULES_FILENAME;
	$if_real = get_real_interface($suricatacfg['interface']);
	$suricata_uuid = $suricatacfg['uuid'];
	$suricatacfgdir = "{$suricatadir}suricata_{$suricata_uuid}_{$if_real}";

	if (!is_array($config['installedpackages']['suricata']['rule'])) {
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

	global $config;
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
	log_error(gettext("[Suricata] Removed {$count} obsoleted rules category files."));

	// Now remove any dead rules files from the interface configurations
	if (!empty($cats) && is_array($config['installedpackages']['suricata']['rule'])) {
		foreach ($config['installedpackages']['suricata']['rule'] as &$iface) {
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
	}

	// Clean up
	unset($cats, $enabled_rules);
}

/* Uses XMLRPC to synchronize the changes to a remote node */
function suricata_sync_on_changes() {
	global $config, $g;

	/* Do not attempt a package sync while booting up or installing package */
	if ($g['booting'] || $g['suricata_postinstall'] == TRUE) {
		log_error("[suricata] Skipping XMLRPC sync when booting up or during package reinstallation.");
		return;
	}

	if (is_array($config['installedpackages']['suricatasync']['config'])) {
		$suricata_sync = $config['installedpackages']['suricatasync']['config'][0];
		$synconchanges = $suricata_sync['varsynconchanges'];
		$synctimeout = $suricata_sync['varsynctimeout'] ?: '150';
		$syncdownloadrules = $suricata_sync['vardownloadrules'];
		switch ($synconchanges) {
			case "manual":
				if (is_array($suricata_sync['row'])) {
					$rs = $suricata_sync['row'];
				} else {
					log_error("[suricata] XMLRPC sync is enabled but there are no hosts configured as replication targets.");
					return;
				}
				break;
			case "auto":
				if (is_array($config['hasync'])) {
					$system_carp = $config['hasync'];
					$rs[0]['varsyncipaddress'] = $system_carp['synchronizetoip'];
					$rs[0]['varsyncusername'] = $system_carp['username'];
					$rs[0]['varsyncpassword'] = $system_carp['password'];
					$rs[0]['varsyncsuricatastart'] = FALSE;
					$rs[0]['varsyncdestinenable'] = FALSE;
					// XMLRPC sync is currently only supported over connections using the same protocol and port as this system
					if ($config['system']['webgui']['protocol'] == "http") {
						$rs[0]['varsyncprotocol'] = "http";
						$rs[0]['varsyncport'] = $config['system']['webgui']['port'] ?: '80';
					} else {
						$rs[0]['varsyncprotocol'] = "https";
						$rs[0]['varsyncport'] = $config['system']['webgui']['port'] ?: '443';
					}
					if ($system_carp['synchronizetoip'] == "") {
						log_error("[suricata] XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets.");
						return;
					} else {
						$rs[0]['varsyncdestinenable'] = TRUE;
					}
				} else {
					log_error("[suricata] XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets.");
					return;
				}
				break;			
			default:
				return;
				break;
		}
		if (is_array($rs)) {
			log_error("[suricata] XMLRPC sync is starting.");
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
						log_error("[suricata] XMLRPC sync with '{$sync_to_ip}' aborted due to the following error(s): {$error}");
					}
				}
			}
			log_error("[suricata] XMLRPC sync completed.");
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
	global $config, $g;

	/* Do not attempt a package sync while booting up or installing package */
	if ($g['booting'] || isset($g['suricata_postinstall'])) {
		log_error("[suricata] Skipping XMLRPC sync when booting up or during package reinstallation.");
		return;
	}

	if ($username == "" || $password == "" || $sync_to_ip == "" || $port == "" || $protocol == "") {
		log_error("[suricata] A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync");
		return;
	}

	/**************************************************/
	/* Send over the <suricata> portion of config.xml */
	/* $xml will hold the section to sync.            */
	/**************************************************/
	$xml = array();
	$xml['suricata'] = $config['installedpackages']['suricata'];
	
	$downloadrulescmd = "";
	if ($syncdownloadrules == "yes") {
		$downloadrulescmd = "log_error(gettext(\"[suricata] XMLRPC pkg sync: Update of downloaded rule sets requested...\"));\n";
		$downloadrulescmd .= "\tinclude_once(\"/usr/local/pkg/suricata/suricata_check_for_rule_updates.php\");\n";
	}
	$suricatastart = "";
	if ($syncstartsuricata == "ON") {
		$suricatastart = "log_error(gettext(\"[suricata] XMLRPC pkg sync: Checking Suricata status...\"));\n";
		$suricatastart .= "\tif (!is_process_running(\"suricata\")) {\n";
		$suricatastart .= "\t\tlog_error(gettext(\"[suricata] XMLRPC pkg sync: Suricata not running. Sending a start command...\"));\n";
		$suricatastart .= "\t\t\$sh_script = RCFILEPREFIX . \"suricata.sh\";\n";
		$suricatastart .= "\t\tmwexec_bg(\"{\$sh_script} start\");\n\t}\n";
		$suricatastart .= "\telse {\n\t\tlog_error(gettext(\"[suricata] XMLRPC pkg sync: Suricata is running...\"));\n\t}\n";
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
	log_error(gettext("[suricata] XMLRPC pkg sync: Generating suricata.yaml file using Master Host settings..."));
	\$rebuild_rules = true;
	conf_mount_rw();
	sync_suricata_package_config();
	conf_mount_ro();
	\$rebuild_rules = false;
	{$suricatastart}
	log_error(gettext("[suricata] XMLRPC pkg sync process on this host is complete..."));
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
	
	if (pf_version() >= "2.4") {
		// xmlrpc cannot encode NULL objects/arrays..
		foreach($xml as $xmlkey => $xmlvalue) {
			if (gettype($xmlvalue) == "NULL") {
				$xml[$xmlkey] = array();
			}
		}
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
		
		$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section', $xml, $synctimeout);
		$resp = $rpc_client->xmlrpc_exec_php($execcmd, $synctimeout);
		$resp = $rpc_client->xmlrpc_exec_php($execcmd2, $synctimeout);
	} else {
		// pfSense before 2.4
		require_once('xmlrpc.inc');
	
		// Take care of IPv6 literal address
		if (is_ipaddrv6($sync_to_ip)) {
			$sync_to_ip = "[{$sync_to_ip}]";
		}

		$url = "{$protocol}://{$sync_to_ip}";

		/*************************************************/
		/* Send over any auto-SID management files       */
		/*************************************************/
		$sid_files = glob(SURICATA_SID_MODS_PATH . '*');
		foreach ($sid_files as $file) {
			$content = base64_encode(file_get_contents($file));
			$payload = "@file_put_contents('{$file}', base64_decode('{$content}'));";

			/* assemble xmlrpc payload */
			$method = 'pfsense.exec_php';
			$params = array( XML_RPC_encode($password), XML_RPC_encode($payload) );

			log_error("[suricata] XMLRPC sync sending auto-SID conf files to {$url}:{$port}.");
			$msg = new XML_RPC_Message($method, $params);
			$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
			$cli->setCredentials($username, $password);
			$resp = $cli->send($msg, $synctimeout);
			$error = "";
			if (!$resp) {
				$error = "A communications error occurred while attempting Suricata XMLRPC sync with {$url}:{$port}.  Failed to transfer file: " . basename($file);
				log_error($error);
				file_notice("sync_settings", $error, "Suricata Settings Sync", "");
			} elseif ($resp->faultCode()) {
				$error = "An error code was received while attempting Suricata XMLRPC sync with {$url}:{$port}. Failed to transfer file: " . basename($file) . " - Code " . $resp->faultCode() . ": " . $resp->faultString();
				log_error($error);
				file_notice("sync_settings", $error, "Suricata Settings Sync", "");
			}
		}

		if (!empty($sid_files) && $error == "") {
			log_error("[suricata] XMLRPC sync auto-SID conf files success with {$url}:{$port} (pfsense.exec_php).");
		}

		/*************************************************/
		/* Send over any IPREP IP List files             */
		/*************************************************/
		$iprep_files = glob(SURICATA_IPREP_PATH . '*');
		foreach ($iprep_files as $file) {
			$content = base64_encode(file_get_contents($file));
			$payload = "@file_put_contents('{$file}', base64_decode('{$content}'));";

			/* assemble xmlrpc payload */
			$method = 'pfsense.exec_php';
			$params = array( XML_RPC_encode($password), XML_RPC_encode($payload) );

			log_error("[suricata] Suricata XMLRPC sync sending IPREP files to {$url}:{$port}.");
			$msg = new XML_RPC_Message($method, $params);
			$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
			$cli->setCredentials($username, $password);
			$resp = $cli->send($msg, $synctimeout);
			$error = "";
			if (!$resp) {
				$error = "A communications error occurred while attempting Suricata XMLRPC sync with {$url}:{$port}.  Failed to transfer file: " . basename($file);
				log_error($error);
				file_notice("sync_settings", $error, "Suricata Settings Sync", "");
			} elseif ($resp->faultCode()) {
				$error = "An error code was received while attempting Suricata XMLRPC sync with {$url}:{$port}. Failed to transfer file: " . basename($file) . " - Code " . $resp->faultCode() . ": " . $resp->faultString();
				log_error($error);
				file_notice("sync_settings", $error, "Suricata Settings Sync", "");
			}
		}

		if (!empty($iprep_files) && $error == "") {
			log_error("[suricata] XMLRPC sync IPREP files success with {$url}:{$port} (pfsense.exec_php).");
		}

		/* assemble xmlrpc payload */
		$params = array(
			XML_RPC_encode($password),
			XML_RPC_encode($xml)
		);

		log_error("[suricata] Beginning package configuration XMLRPC sync to {$url}:{$port}.");
		$method = 'pfsense.merge_installedpackages_section_xmlrpc';
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);

		/* send our XMLRPC message and timeout after defined sync timeout value*/
		$resp = $cli->send($msg, $synctimeout);
		if (!$resp) {
			$error = "A communications error occurred while attempting Suricata XMLRPC sync with {$url}:{$port}.";
			log_error($error);
			file_notice("sync_settings", $error, "Suricata Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$error = "An error code was received while attempting Suricata XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error($error);
			file_notice("sync_settings", $error, "Suricata Settings Sync", "");
		} else {
			log_error("[suricata] Package configuration XMLRPC sync successfully completed with {$url}:{$port}.");
		}

		/* assemble xmlrpc payload */
		$method = 'pfsense.exec_php';
		$params = array(
			XML_RPC_encode($password),
			XML_RPC_encode($execcmd)
		);

		log_error("[suricata] XMLRPC sync sending reload configuration cmd set as a file to {$url}:{$port}.");
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		$resp = $cli->send($msg, $synctimeout);
		if (!$resp) {
			$error = "A communications error occurred while attempting Suricata XMLRPC sync with {$url}:{$port} (pfsense.exec_php).";
			log_error($error);
			file_notice("sync_settings", $error, "Suricata Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$error = "An error code was received while attempting Suricata XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error($error);
			file_notice("sync_settings", $error, "Suricata Settings Sync", "");
		} else {
			log_error("[suricata] XMLRPC sync reload configuration success with {$url}:{$port} (pfsense.exec_php).");
		}

		$params2 = array(
			XML_RPC_encode($password),
			XML_RPC_encode($execcmd2)
		);
		log_error("[suricata] XMLRPC sync sending {$url}:{$port} cmd to execute configuration reload.");
		$msg2 = new XML_RPC_Message($method, $params2);
		$resp = $cli->send($msg2, $synctimeout);
		if (!$resp) {
			$error = "A communications error occurred while attempting Suricata XMLRPC sync with {$url}:{$port} (pfsense.exec_php).";
			log_error($error);
			file_notice("sync_settings", $error, "Suricata Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$error = "An error code was received while attempting Suricata XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error($error);
			file_notice("sync_settings", $error, "Suricata Settings Sync", "");
		} else {
			log_error("[suricata] XMLRPC sync reload configuration success with {$url}:{$port} (pfsense.exec_php).");
		}
	}
}
