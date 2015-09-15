<?php
/*
	pfBlockerNG.inc

	pfBlockerNG
	Copyright (C) 2015 BBcan177@gmail.com
	All rights reserved.

	part of the Postfix package for pfSense
	Copyright (C) 2010 Erik Fonnesbeck
	Based upon pfBlocker by
	Copyright (C) 2011-2012 Marcello Coutinho
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:


	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.


	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.

*/

//error_reporting(E_ALL);

require_once("util.inc");
require_once("functions.inc");
require_once("pkg-utils.inc");
require_once("pfsense-utils.inc");
require_once("globals.inc");
require_once("services.inc");

// [ $pfb ] pfBlockerNG Global Array for Paths and Variables. This needs to be called to get the Updated Settings.
function pfb_global() {
	global $g,$config,$pfb;

	$pfs_version = substr(trim(file_get_contents("/etc/version")),0,3);

	if ($pfs_version == "2.2") {
		$prefix = "/usr/pbi/pfblockerng-" . php_uname("m");
	} else {
		$prefix = "/usr/local";
	}

	// Folders
	$pfb['dbdir']		= "{$g['vardb_path']}/pfblockerng";
	$pfb['aliasdir']	= "{$g['vardb_path']}/aliastables";
	$pfb['logdir']		= "{$g['varlog_path']}/pfblockerng";
	$pfb['etdir']		= "{$pfb['dbdir']}/ET";
	$pfb['nativedir']	= "{$pfb['dbdir']}/native";
	$pfb['denydir']		= "{$pfb['dbdir']}/deny";
	$pfb['matchdir']	= "{$pfb['dbdir']}/match";
	$pfb['permitdir']	= "{$pfb['dbdir']}/permit";
	$pfb['origdir']		= "{$pfb['dbdir']}/original";
	$pfb['ccdir']		= "{$prefix}/share/GeoIP";

	// Create Folders if not Exist.
	$folder_array = array ("{$pfb['dbdir']}","{$pfb['logdir']}","{$pfb['ccdir']}","{$pfb['origdir']}","{$pfb['nativedir']}","{$pfb['denydir']}","{$pfb['matchdir']}","{$pfb['permitdir']}","{$pfb['aliasdir']}");
	foreach ($folder_array as $folder) {
		safe_mkdir ("{$folder}",0755);
	}

	// Files
	$pfb['master']		= "{$pfb['dbdir']}/masterfile";
	$pfb['errlog']		= "{$pfb['logdir']}/error.log";
	$pfb['geolog']		= "{$pfb['logdir']}/geoip.log";
	$pfb['log']		= "{$pfb['logdir']}/pfblockerng.log";
	$pfb['supptxt']		= "{$pfb['dbdir']}/pfbsuppression.txt";
	$pfb['script']		= 'sh /usr/local/pkg/pfblockerng/pfblockerng.sh';
	$pfb['aliasarchive']	= "{$prefix}/etc/aliastables.tar.bz2";

	// General Variables
	$pfb['config']		= $config['installedpackages']['pfblockerng']['config'][0];

	// Enable/Disable of pfBlockerNG
	$pfb['enable']		= $pfb['config']['enable_cb'];
	// Keep Blocklists on pfBlockerNG Disable
	$pfb['keep']		= $pfb['config']['pfb_keep'];
	// Enable Suppression
	$pfb['supp']		= $pfb['config']['suppression'];
	// Max Lines in pfblockerng.log file
	$pfb['logmax']		= $pfb['config']['log_maxlines'];
	// Lan IP Address
	$pfb['iplocal']		= $config['interfaces']['lan']['ipaddr'];
	// Disable Country Database CRON Updates
	$pfb['cc']		= $pfb['config']['database_cc'];

	// User Defined CRON Start Minute
	$pfb['min']		= $pfb['config']['pfb_min'];
	// Start hour of the Scheduler
	$pfb['hour']		= $pfb['config']['pfb_hour'];
	// Hour cycle for Scheduler
	$pfb['interval']	= $pfb['config']['pfb_interval'];
	// Start hour of the 'Once a day' Schedule
	$pfb['24hour']		= $pfb['config']['pfb_dailystart'];

	// Set pfBlockerNG to Disabled on 'Re-Install'
	if (isset($pfb['install']) && $pfb['install']) {
		$pfb['enable'] = "";
		$pfb['install'] = FALSE;
	}
}

pfb_global();

// Set Max PHP Memory Setting
$uname = posix_uname();
if ($uname['machine'] == 'amd64') {
	ini_set('memory_limit', '256M');
}


// Function to decode to Alias Custom entry box.
function pfbng_text_area_decode($text) {
	$customlist = explode("\r\n", base64_decode($text));
	foreach ($customlist as $line) {
		if (substr(trim($line), 0, 1) != '#' && !empty($line)) {
			if (strpos($line, '#')) {
				$custom .= trim(strstr($line, '#', TRUE)) . "\n";
			} else {
				$custom .= $line . "\n";
			}
		}
	}
	return $custom;
}


// Manage Log File Line Limit
function pfb_log_mgmt() {
	global $pfb;
	pfb_global();

	if ($pfb['logmax'] == "nolimit") {
		// Skip Log Mgmt
	} else {
		if (file_exists($pfb['log'])) {
			exec("/usr/bin/tail -n {$pfb['logmax']} {$pfb['log']} > /tmp/pfblog; /bin/mv -f /tmp/pfblog {$pfb['log']}");
		}
	}
}


// Record Log Messsages to pfBlockerNG Log File and/or Error Log File.
function pfb_logger($log, $type) {
	global $g,$pfb,$pfbarr;

	$now = date("m/d/y G:i:s", time());

	// Only log timestamp if new
	if (preg_match("/NOW/", $log)) {
		if ($now == $pfb['pnow']) {
			$log = str_replace("[ NOW ]", "", "{$log}");
		} else {
			$log = str_replace("NOW", $now, "{$log}");
		}
		$pfb['pnow'] = "{$now}";
	}

	if ($type == 2) {
		@file_put_contents("{$pfb['log']}", "{$log}", FILE_APPEND);
		@file_put_contents("{$pfb['errlog']}", "{$log}", FILE_APPEND);
	} elseif ($type == 3) {
		@file_put_contents("{$pfb['geolog']}", "{$log}", FILE_APPEND);
	} else {
		@file_put_contents("{$pfb['log']}", "{$log}", FILE_APPEND);
	}
}


// Determine 'List' Details
function pfb_determine_list_detail($list="", $header_url="", $confconfig="", $key="") {
	global $pfb,$pfbarr,$config;
	$pfbarr = array();

	if (in_array($list,array('Match_Both','Match_Inbound','Match_Outbound','Alias_Match'))) {
		$pfbarr['skip']		= FALSE;
		$pfbarr['folder']	= "{$pfb['matchdir']}";
	} elseif (in_array($list,array('Permit_Both','Permit_Inbound','Permit_Outbound','Alias_Permit'))) {
		$pfbarr['skip']		= FALSE;
		$pfbarr['folder']	= "{$pfb['permitdir']}";
	} elseif ($list == "Alias_Native") {
		$pfbarr['skip']		= FALSE;
		$pfbarr['folder']	= "{$pfb['nativedir']}";
	} else {
		// Deny
		$pfbarr['skip']		= TRUE;
		$pfbarr['folder']	= "{$pfb['denydir']}";
	}

	// Collect proper Alias Table Description (Alias Only vs AutoRules)
	if (preg_match("/Alias/", $list)) {
		$pfbarr['descr'] = "";
	} else {
		$pfbarr['descr'] = " Auto ";
	}

	// Determine length of Header to format log Output
	if (strlen($header_url) > 19) {
		$pfbarr['logtab'] = "";
	}
	elseif (strlen($header_url) > 11) {
		$pfbarr['logtab'] = "\t";
	}
	elseif (strlen($header_url) < 4) {
		$pfbarr['logtab'] = "\t\t\t";
	}
	else {
		$pfbarr['logtab'] = "\t\t";
	}

	if ($confconfig != "") {
		// Configure Autoports/Protocol and Auto Destination if required.
		$autotype = array( 'autoports' => 'aliasports', 'autodest' => 'aliasdest');
		$aports = ""; $adest = "";
		$pfbarr['aproto'] = $config['installedpackages'][$confconfig]['config'][$key]['autoproto'];
		foreach ($autotype as $akey => $atype) {
			if ($config['installedpackages'][$confconfig]['config'][$key][$akey] == "on" && is_array($config['aliases']['alias'])) {
				foreach ($config['aliases']['alias'] as $palias) {
					if ($palias['name'] == $config['installedpackages'][$confconfig]['config'][$key][$atype]) {
						if (!empty($palias['address'])) {
							switch($akey) {
								case "autoports":
									$pfbarr['aports'] = $config['installedpackages'][$confconfig]['config'][$key][$atype];
									break;
								case "autodest":
									$pfbarr['adest'] = $config['installedpackages'][$confconfig]['config'][$key][$atype];
									break;
							}
						}
					}
				}
			}
		}
	}
	return $pfbarr;
}


// Determine if Cron Task requires updating
function pfblockerng_cron_exists($crontask, $pfb_min, $pfb_hour) {
	global $config;

	if (is_array($config['cron']['item'])) {
		foreach ($config['cron']['item'] as $item) {
			if (strpos($item['command'], $crontask) !== FALSE) {
				if ($item['minute'] != $pfb_min) {
					return FALSE;
				}
				if ($pfb_hour == 'maxmind' && !empty($item['hour'])) {
					// Maxmind hour is randomized. Skip comparison.
					return TRUE;
				}
				if ($item['hour'] != $pfb_hour) {
					return FALSE;
				}
				return TRUE;
			}
		}
	}
	return FALSE;
}


// Calculate the cron task base hour setting
function pfb_cron_base_hour() {
	global $pfb;

	if ($pfb['interval'] == 1) {
		return;
	}

	if ($pfb['interval'] == 2) {
		// 2 Hour Schedule Converter
		$shour = intval(substr($pfb['hour'], 0, 2));
		$sch2 = strval($shour);
		for ($i=0; $i<11; $i++) {
			$shour += 2;
			if ($shour >= 24)
				$shour -= 24;
			$sch2 .= "," . strval($shour);
		}
		$sch2 = explode(",", $sch2);
		sort($sch2);
		return $sch2;
	}

	if ($pfb['interval'] == 3) {
		// 3 Hour Schedule Converter
		$shour = intval(substr($pfb['hour'], 0, 2));
		$sch3 = strval($shour);
		for ($i=0; $i<7; $i++) {
			$shour += 3;
			if ($shour >= 24)
				$shour -= 24;
			$sch3 .= "," . strval($shour);
		}
		$sch3 = explode(",", $sch3);
		sort($sch3);
		return $sch3;
	}

	if ($pfb['interval'] == 4) {
		// 4 Hour Schedule Converter
		$shour = intval(substr($pfb['hour'], 0, 2));
		$sch4 = strval($shour);
		for ($i=0; $i<5; $i++) {
			$shour += 4;
			if ($shour >= 24)
				$shour -= 24;
			$sch4 .= "," . strval($shour);
		}
		$sch4 = explode(",", $sch4);
		sort($sch4);
		return $sch4;
	}

	if ($pfb['interval'] == 6) {
		// 6 Hour Schedule Converter
		$shour = intval(substr($pfb['hour'], 0, 2));
		$sch6 = strval($shour);
		for ($i=0; $i<3; $i++) {
			$shour += 6;
			if ($shour >= 24)
				$shour -= 24;
			$sch6 .= "," . strval($shour);
		}
		$sch6 = explode(",", $sch6);
		sort($sch6);
		return $sch6;
	}

	if ($pfb['interval'] == 8) {
		// 8 Hour Schedule Converter
		$shour = intval(substr($pfb['hour'], 0, 2));
		$sch8 = strval($shour);
		for ($i=0; $i<2; $i++) {
			$shour += 8;
			if ($shour >= 24)
				$shour -= 24;
			$sch8 .= "," . strval($shour);
		}
		$sch8 = explode(",", $sch8);
		sort($sch8);
		return $sch8;
	}

	if ($pfb['interval'] == 12) {
		// 12 Hour Schedule Converter
		$shour = intval(substr($pfb['hour'], 0, 2));
		$sch12 = strval($shour) . ",";
		$shour += 12;
		if ($shour >= 24)
			$shour -= 24;
		$sch12 .= strval($shour);
		$sch12 = explode(",", $sch12);
		sort($sch12);
		return $sch12;
	}

	if ($pfb['interval'] == 24) {
		return array($pfb['24hour']);
	} 

	// Default to hourly schedule
	$pfb['interval'] = 1;
	return;
}


// Create Suppression Alias
function pfb_create_suppression_alias() {
	global $config;

	// Collect existing pfsense alias(s)
	if (is_array($config['aliases']['alias'])) {
		foreach($config['aliases']['alias'] as $exalias) {
			$new_aliases[] = $exalias;
		}
	}
	// Create New pfBlockerNGSuppress Alias
	$new_aliases[] = array( "name"		=> "pfBlockerNGSuppress",
				"address"	=> "",
				"descr"		=> "pfBlockerNG Suppression List (24|32 CIDR only)",
				"type"		=> "network",
				"detail"	=> ""
				);
	$config['aliases']['alias'] = $new_aliases;
	$pfb['cron_mod'] = TRUE;
}


// Create Suppression file from Alias
function pfb_create_suppression_file() {
	global $config,$pfb;

	// Find pfBlockerNGSuppress Array ID Number
	$pfb['found'] = FALSE;
	if (is_array($config['aliases']['alias'])) {
		$pfb_id = 0;
		foreach ($config['aliases']['alias'] as $alias) {
			if ($alias['name'] == "pfBlockerNGSuppress") {
				$pfb['found'] = TRUE;
				break;
			}
			$pfb_id++;
		}

		if ($pfb['found']) {
			$pfb_suppress = str_replace(" ", "\n", $config['aliases']['alias'][$pfb_id]['address']);
			if (!empty($pfb_suppress)) {
				@file_put_contents("{$pfb['supptxt']}", $pfb_suppress, LOCK_EX);
			} else {
				unlink_if_exists("{$pfb['supptxt']}");
			}
		} else {
			// Delete Suppression File if Alias is Empty.
			unlink_if_exists("{$pfb['supptxt']}");
		}
	}

	// Call Function to Create Suppression Alias.
	if (!$pfb['found']) {
		pfb_create_suppression_alias();
	}
}


// IPv6 Range to CIDR function used courtesey from:
// https://github.com/stilez/pfsense-leases/blob/50cc0fa81dba5fe91bcddaea016c245d1b8479cc/etc/inc/util.inc
function ip_range_to_subnet_array_temp2($ip1, $ip2) {

	if (is_ipaddrv4($ip1) && is_ipaddrv4($ip2)) {
		$proto = 'ipv4';  // for clarity
		$bits = 32;
		$ip1bin = decbin(ip2long32($ip1));
		$ip2bin = decbin(ip2long32($ip2));
	} elseif (is_ipaddrv6($ip1) && is_ipaddrv6($ip2)) {
		$proto = 'ipv6';
		$bits = 128;
		$ip1bin = Net_IPv6::_ip2Bin($ip1);
		$ip2bin = Net_IPv6::_ip2Bin($ip2);
	} else
		return array();

	// it's *crucial* that binary strings are guaranteed the expected length;  do this for certainty even though for IPv6 it's redundant
	$ip1bin = str_pad($ip1bin, $bits, '0', STR_PAD_LEFT);
	$ip2bin = str_pad($ip2bin, $bits, '0', STR_PAD_LEFT);

	if ($ip1bin === $ip2bin)
		return array($ip1 . '/' . $bits);
	
	if (strcmp($ip1bin, $ip2bin) > 0)
		list ($ip1bin, $ip2bin) = array($ip2bin, $ip1bin);  // swap contents of ip1 <= ip2

	$rangesubnets = array();
	$netsize = 0;

	do {
		// at loop start, $ip1 is guaranteed strictly less than $ip2 (important for edge case trapping and preventing accidental binary wrapround)
		// which means the assignments $ip1 += 1 and $ip2 -= 1 will always be "binary-wrapround-safe"

		// step #1 if start ip (as shifted) ends in any '1's, then it must have a single cidr to itself (any cidr would include the '0' below it)
		
		if (substr($ip1bin, -1, 1) == '1') {
			// the start ip must be in a separate one-IP cidr range
			$new_subnet_ip = substr($ip1bin, $netsize, $bits - $netsize) . str_repeat('0', $netsize);
			$rangesubnets[$new_subnet_ip] = $bits - $netsize;
			$n = strrpos($ip1bin, '0');  //can't be all 1's
			$ip1bin = ($n == 0 ? '' : substr($ip1bin, 0, $n)) . '1' . str_repeat('0', $bits - $n - 1);  // BINARY VERSION OF $ip1 += 1
		} 

		// step #2, if end ip (as shifted) ends in any zeros then that must have a cidr to itself (as cidr cant span the 1->0 gap)
		
		if (substr($ip2bin, -1, 1) == '0') {
			// the end ip must be in a separate one-IP cidr range
			$new_subnet_ip = substr($ip2bin, $netsize, $bits - $netsize) . str_repeat('0', $netsize);
			$rangesubnets[$new_subnet_ip] = $bits - $netsize;
			$n = strrpos($ip2bin, '1');  //can't be all 0's
			$ip2bin = ($n == 0 ? '' : substr($ip2bin, 0, $n)) . '0' . str_repeat('1', $bits - $n - 1);  // BINARY VERSION OF $ip2 -= 1
			// already checked for the edge case where end = start+1 and start ends in 0x1, above, so it's safe
		}

		// this is the only edge case arising from increment/decrement.
		// it happens if the range at start of loop is exactly 2 adjacent ips, that spanned the 1->0 gap. (we will have enumerated both by now)
		
		if (strcmp($ip2bin, $ip1bin) < 0)
			continue;

		// step #3 the start and end ip MUST now end in '0's and '1's respectively
		// so we have a non-trivial range AND the last N bits are no longer important for CIDR purposes.

		$shift = $bits - max(strrpos($ip1bin, '0'), strrpos($ip2bin, '1'));  // num of low bits which are '0' in ip1 and '1' in ip2
		$ip1bin = str_repeat('0', $shift) . substr($ip1bin, 0, $bits - $shift);
		$ip2bin = str_repeat('0', $shift) . substr($ip2bin, 0, $bits - $shift);
		$netsize += $shift;
		if ($ip1bin === $ip2bin) {
			// we're done.
			$new_subnet_ip = substr($ip1bin, $netsize, $bits - $netsize) . str_repeat('0', $netsize);
			$rangesubnets[$new_subnet_ip] = $bits - $netsize;
			continue;
		}
		
		// at this point there's still a remaining range, and either startip ends with '1', or endip ends with '0'. So repeat cycle.
	} while (strcmp($ip1bin, $ip2bin) < 0);

	// subnets are ordered by bit size. Re sort by IP ("naturally") and convert back to IPv4/IPv6

	ksort($rangesubnets, SORT_STRING);
	$out = array();

	foreach ($rangesubnets as $ip => $netmask) {
		if ($proto == 'ipv4') {
			$i = str_split($ip, 8);
			$out[] = implode('.', array( bindec($i[0]),bindec($i[1]),bindec($i[2]),bindec($i[3]))) . '/' . $netmask;
		} else
			$out[] = Net_IPv6::compress(Net_IPv6::_bin2Ip($ip)) . '/' . $netmask;
	}

	return $out;
}


// Archive Aliastables for NanoBSD and RAMDisk Installations
function pfb_aliastables($mode) {
	global $g,$config,$pfb;
	$earlyshellcmd = "/usr/local/pkg/pfblockerng/pfblockerng.sh aliastables";
	$msg = "";

	// Only Execute function if Platform is NanoBSD or Ramdisks are used.
	if (($g['platform'] != "pfSense") || isset($config['system']['use_mfs_tmpvar'])) {
		conf_mount_rw();
		if ($mode == "update") {
			// Archive Aliastable Folder
			exec ("cd {$pfb['aliasdir']}; ls -A pfB_*.txt && /usr/bin/tar -jcvf {$pfb['aliasarchive']} pfB_*.txt >/dev/null 2>&1");
			$msg = "\n\nArchiving Aliastable Folder\n";
		}
		elseif ($mode == "conf") {
			// Check conf file for earlyshellcmd
			if (is_array($config['system']['earlyshellcmd'])) {
				$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
				if (!preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd)) {
					$a_earlyshellcmd[] = "{$earlyshellcmd}";
					$msg = "\n** Adding earlyshellcmd **\n";
				}
			}
			else {
				$config['system']['earlyshellcmd'] = "{$earlyshellcmd}";
				$msg = "\n** Adding earlyshellcmd **\n";
			}
		}
		conf_mount_ro();
	}
	else {
		if (file_exists("{$pfb['aliasarchive']}")) {
			// Remove Aliastables archive if found.
			conf_mount_rw();
			@unlink_if_exists("{$pfb['aliasarchive']}");
			conf_mount_ro();
		}
		// Remove earlyshellcmd if found.
		if (is_array($config['system']['earlyshellcmd'])) {
			$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
			if (preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd)) {
				$a_earlyshellcmd = preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd, PREG_GREP_INVERT);
				$msg = "\n** Removing earlyshellcmd **\n";
			}
		}
	}

	if ($msg != "") {
		pfb_logger("{$msg}","1");
		$pfb['cron_mod'] = TRUE;
	}
}


// Main pfBlockerNG Function
function sync_package_pfblockerng($cron = "") {

	global $g,$config,$pfb,$pfbarr;
	pfb_global();
	$pfb['cron_mod'] = FALSE;	// Flag to check for mods to the config.xml file.

	// Detect Boot Process or Update via CRON
	if (isset($_POST) && $cron == "") {
		if (!preg_match("/\w+/",$_POST['__csrf_magic'])) {
			log_error("[pfBlockerNG] Sync terminated during boot process.");
			return;
		}
	}
	log_error("[pfBlockerNG] Starting sync process.");

	// Force Update - Set 'Save' variable when 'No Updates' found.
	if ($cron == "noupdates") {
		$pfb['save'] = TRUE;
	}

	// Start of pfBlockerNG Logging to 'pfblockerng.log'
	if ($pfb['enable'] == "on" && !$pfb['save']) {
		$log = " UPDATE PROCESS START [ NOW ]\n";
		pfb_logger("{$log}","1");
	} else {
		if ($cron != "noupdates") {
			$log = "\n**Saving Configuration [ NOW ] ...\n";
			pfb_logger("{$log}","1");
		}
	}

	// Call function for NanoBSD/Ramdisk processes.
	pfb_aliastables("conf");

	// Collect pfSense Max Table Size Entry
	if (empty($config['system']['maximumtableentries'])) {
		// If Table limit not defined, set Default to 2M
		$config['system']['maximumtableentries'] = "2000000";
		$pfb['cron_mod'] = TRUE;
	}
	$pfb['table_limit'] = $config['system']['maximumtableentries'];

	// Collect local web gui configuration
	$pfb['weblocal'] = ($config['system']['webgui']['protocol'] != "" ? $config['system']['webgui']['protocol'] : "http");
	$pfb['port'] = $config['system']['webgui']['port'];
	if ($pfb['port'] == "") {
		if ($config['system']['webgui']['protocol'] == "http") {
			$pfb['port'] = "80";
		} else {
			$pfb['port'] = "443";
		}
	}
	$pfb['weblocal'] .= "://127.0.0.1:{$pfb['port']}/pfblockerng/pfblockerng.php";

	// Define Inbound/Outbound Action is not user selected.
	$pfb['deny_action_inbound'] = ($pfb['config']['inbound_deny_action'] != "" ? $pfb['config']['inbound_deny_action'] : "block");
	$pfb['deny_action_outbound'] = ($pfb['config']['outbound_deny_action'] != "" ? $pfb['config']['outbound_deny_action'] : "reject");

	// Reloads Existing Blocklists without Downloading New Lists
	$pfb['reuse']	= $pfb['config']['pfb_reuse'];
	// Enable OpenVPN AutoRules
	$pfb['openvpn']	= $pfb['config']['openvpn_action'];
	// Enable/Disable Floating Auto-Rules
	$pfb['float']	= $pfb['config']['enable_float'];
	// Enable Remove of Duplicate IPs utilizing Grepcidr
	$pfb['dup']	= $pfb['config']['enable_dup'];
	// Order of the Auto-Rules
	$pfb['order']	= $pfb['config']['pass_order'];
	// Suffix used for Auto-Rules
	$pfb['suffix']	= $pfb['config']['autorule_suffix'];

	// Reputation Variables
	$pfb['config_rep'] = $config['installedpackages']['pfblockerngreputation']['config'][0];

	// Enable/Disable Reputation
	$pfb['rep']	= $pfb['config_rep']['enable_rep'];
	// Enable/Disable 'pDup'
	$pfb['pdup']	= $pfb['config_rep']['enable_pdup'];
	// Enable/Disable 'dDup'
	$pfb['dedup']	= ($pfb['config_rep']['enable_dedup'] != "" ? $pfb['config_rep']['enable_dedup'] : "x");
	// 'Max' variable setting for Reputation
	$pfb['max']	= ($pfb['config_rep']['p24_max_var'] != "" ? $pfb['config_rep']['p24_max_var'] : "x");
	// 'dMax' variable setting for Reputation
	$pfb['dmax']	= ($pfb['config_rep']['p24_dmax_var'] != "" ? $pfb['config_rep']['p24_dmax_var'] : "x");
	// 'pMax' variable setting for Reputation
	$pfb['pmax']	= ($pfb['config_rep']['p24_pmax_var'] != "" ? $pfb['config_rep']['p24_pmax_var'] : "x");
	// Action for Whitelist Country Category
	$pfb['ccwhite']	= $pfb['config_rep']['ccwhite'];
	// Action for Blacklist Country Category
	$pfb['ccblack']	= $pfb['config_rep']['ccblack'];
	// List of Countries in the Whitelist Category
	$pfb['ccexclude']= ($pfb['config_rep']['ccexclude'] != "" ? $pfb['config_rep']['ccexclude'] : "x");
	// Emerging Threats IQRisk Block Categories
	$pfb['etblock']	= ($pfb['config_rep']['etblock'] != "" ? $pfb['config_rep']['etblock'] : "x");
	// Emerging Threats IQRisk Match Categories
	$pfb['etmatch']	= ($pfb['config_rep']['etmatch'] != "" ? $pfb['config_rep']['etmatch'] : "x");
	// Perform a Force Update on ET Categories
	$pfb['etupdate']= $pfb['config_rep']['et_update'];

	// Variables

	// Starting Variable to Skip rep, pdup and dedeup functions if no changes are required
	$pfb['dupcheck'] = FALSE;
	// $pfb['save'] is used to determine if User pressed "Save" Button to avoid Collision with CRON.
	// This is defined in each pfBlockerNG XML Files


	#################################
	#	Configure ARRAYS	#
	#################################

	$continents = array (	"Africa"		=> "pfB_Africa",
				"Antartica"		=> "pfB_Antartica",
				"Asia"			=> "pfB_Asia",
				"Europe"		=> "pfB_Europe",
				"North America"		=> "pfB_NAmerica",
				"Oceania"		=> "pfB_Oceania",
				"South America"		=> "pfB_SAmerica",
				"Top Spammers"		=> "pfB_Top",
				"Proxy and Satellite"	=> "pfB_PS"
				);

	// create rules vars and arrays
	// Array used to Collect Changes to Aliases to be saved to Config
	$new_aliases		= array();
	$new_aliases_list	= array();
	$continent_existing	= array();
	$continent_new		= array();
	$permit_inbound		= array();
	$permit_outbound	= array();
	$deny_inbound		= array();
	$deny_outbound		= array();
	// An Array of all Aliases (Active and non-Active)
	$aliases_list		= array();
	// This is an Array of Aliases that Have Updated Lists via CRON/Force Update when 'Reputation' disabled.
	$pfb_alias_lists	= array();
	// This is an Array of All Active Aliases used when 'Reputation' enabled
	$pfb_alias_lists_all = array();

	// Base Rule Array
	$base_rule_reg = array( "id"		=> "",
				"tag"		=> "",
				"tagged"	=> "",
				"max"		=> "",
				"max-src-nodes"	=> "",
				"max-src-conn"	=> "",
				"max-src-states"=> "",
				"statetimeout"	=> "",
				"statetype"	=> "keep state",
				"os"		=> ""
				);

	// Floating Rules, Base Rule Array
	$base_rule_float = array("id"		=> "",
				"tag"		=> "",
				"tagged"	=> "",
				"quick"		=> "yes",
				"floating"	=> "yes",
				"max"		=> "",
				"max-src-nodes"	=> "",
				"max-src-conn"	=> "",
				"max-src-states"=> "",
				"statetimeout"	=> "",
				"statetype"	=> "keep state",
				"os"		=> ""
				);


	#########################################
	#	Configure Rule Suffix		#
	#########################################

	// Discover if any Rules are AutoRules (If no AutoRules found, $pfb['autorules'] is FALSE, Skip Rules Re-Order )
	// To configure Auto Rule Suffix. pfBlockerNG must be disabled to change Suffix and to avoid Duplicate Rules
	$pfb['autorules'] = FALSE;
	$pfb['found'] = FALSE;
	foreach ($continents as $continent => $pfb_alias) {
		if (is_array($config['installedpackages']['pfblockerng' . strtolower(preg_replace('/ /','',$continent))]['config'])) {
			$continent_config = $config['installedpackages']['pfblockerng' . strtolower(preg_replace('/ /','',$continent))]['config'][0];
			if ($continent_config['action'] != "Disabled" && in_array($continent_config['action'],array('Deny_Both','Deny_Inbound','Deny_Outbound','Match_Both','Match_Inbound','Match_Outbound','Permit_Both','Permit_Inbound','Permit_Outbound'))) {
				$pfb['autorules'] = TRUE;
				$pfb['found'] = TRUE;
				break;
			}
		}
	}

	$list_type = array ("pfblockernglistsv4", "pfblockernglistsv6");
	foreach ($list_type as $ip_type) {
		if ($config['installedpackages'][$ip_type]['config'] != "" && !$pfb['found']) {
			foreach($config['installedpackages'][$ip_type]['config'] as $list) {
				if ($list['action'] != "Disabled" && in_array($list['action'],array('Deny_Both','Deny_Inbound','Deny_Outbound','Match_Both','Match_Inbound','Match_Outbound','Permit_Both','Permit_Inbound','Permit_Outbound'))) {
					$pfb['autorules'] = TRUE;
					break;
				}
			}
		}
	}

	// Configure Auto Rule Suffix. pfBlockerNG must be disabled to change Suffix and to avoid Duplicate Rules
	// Count Number of Rules with 'pfB_'
	$count = 0;
	if (is_array($config['filter']['rule'])) {
		foreach ($config['filter']['rule'] as $rule) {
			// Collect any pre-existing Suffix
			if (preg_match("/pfB_\w+(\s.*)/",$rule['descr'], $pfb_suffix_real) && $count == 0) {
				$pfb_suffix_match = $pfb_suffix_real[1];
			}
			// Query for Existing pfB Rules
			if (preg_match("/pfB_/",$rule['descr'])) {
				$count++;
				break;
			}
		}
	}

	// Change Suffix only if No pfB Rules Found and Auto Rules are Enabled.
	if ($pfb['autorules'] && $count == 0) {
		switch ($pfb['suffix']) {
			case "autorule":
				$pfb['suffix'] = " auto rule";
				break;
			case "standard":
				$pfb['suffix'] = "";
				break;
			case "ar":
				$pfb['suffix'] = " AR";
				break;
		}
	} else {
		if ($pfb['autorules']) {
			// Use existing Suffix Match
			$pfb['suffix'] = $pfb_suffix_match;
		} else {
			// Leave Rule Suffix 'Blank'
			$pfb['suffix'] = "";
		}
	}


	#########################################################
	#	Configure INBOUND/OUTBOUND INTERFACES		#
	#########################################################

	// Collect pfSense Interface Order
	$ifaces = get_configured_interface_list();

	if (!empty($pfb['config']['inbound_interface'])) {
		// Sort Interface Array to match pfSense Interface order to allow Floating Rules to populate.
		$selected_interfaces = explode(",",$pfb['config']['inbound_interface']);
		// Sort pfBlockerNG Interface order to pfSense Interface Order
		$sort_interfaces = array_intersect($ifaces, $selected_interfaces);
		$implode_interfaces = ltrim(implode(",",$sort_interfaces), ",");
		// CSV String for Inbound Interfaces for 'pfB_' Match Rules 
		$pfb['inbound_floating'] = $implode_interfaces;
		$pfb['inbound_interfaces_float'] = explode(" ",$implode_interfaces);

		// Assign Inbound Base Rule/Interfaces
		if ($pfb['float'] == "on") {
			// Define Base Firewall Floating Rules Settings
			$base_rule = $base_rule_float;
			$pfb['inbound_interfaces'] = $pfb['inbound_interfaces_float'];
		} else {
			// Define Base Firewall Rules Settings
			$base_rule = $base_rule_reg;
			$pfb['inbound_interfaces'] = explode(",",$pfb['config']['inbound_interface']);
		}
	} else {
		// Define Empty Variable/Array
		$pfb['inbound_interfaces_float'] = "";
		$pfb['inbound_interfaces'] = array();
	}

	if (!empty($pfb['config']['outbound_interface'])) {
		// Sort Interface Array to match pfSense Interface order to allow Floating Rules to populate.
		$selected_interfaces = explode(",",$pfb['config']['outbound_interface']);
		// Sort pfBlockerNG Interface order to pfSense Interface Order
		$sort_interfaces = array_intersect($ifaces, $selected_interfaces);
		// If OpenVPN Interfaces are not in dropdown menu
		if ($pfb['openvpn'] == "on" && $config['openvpn']['openvpn-server'] || $pfb['openvpn'] == "on" && $config['openvpn']['openvpn-client']) {
			if (!in_array("openvpn",$sort_interfaces)) {
				array_push($sort_interfaces, "openvpn");
			}
		}
		$implode_interfaces = ltrim(implode(",",$sort_interfaces), ",");
		// CSV String for Outbound Interfaces for 'pfB_' Match Rules
		$pfb['outbound_floating'] = $implode_interfaces;
		$pfb['outbound_interfaces_float'] = explode(" ",$implode_interfaces);

		// Assign Outbound Base Rule/Interfaces
		if ($pfb['float'] == "on") {
			$base_rule = $base_rule_float;
			$pfb['outbound_interfaces'] = $pfb['outbound_interfaces_float'];
		} else {
			$base_rule = $base_rule_reg;
			$pfb['outbound_interfaces'] = explode(",",$pfb['config']['outbound_interface']);
			// If OpenVPN Interfaces are not in dropdown menu
			if ($pfb['openvpn'] == "on" && $config['openvpn']['openvpn-server'] || $pfb['openvpn'] == "on" && $config['openvpn']['openvpn-client']) {
				if (!in_array("openvpn",$sort_interfaces)) {
					array_push($pfb['outbound_interfaces'], "openvpn");
				}
			}
		}
	} else {
		// Define Empty Variable/Array
		$pfb['outbound_interfaces_float'] = "";
		$pfb['outbound_interfaces'] = array();
	}


	#################################################
	#	Clear Removed Lists from Masterfiles	#
	#################################################

	// Process to keep Masterfiles in Sync with Valid Lists from config.conf file.
	$pfb['sync_master'] = TRUE;

	// Don't execute this function when pfBlockerNG is Disabled and 'Keep Blocklists' is enabled.
	if ($pfb['enable'] == "" && $pfb['keep'] == "on") {
		$pfb['sync_master'] = FALSE;
	}

	if ($pfb['sync_master']) {
		$pfb['existing']['match']['type']	= "match";
		$pfb['existing']['permit']['type']	= "permit";
		$pfb['existing']['deny']['type']	= "deny";
		$pfb['existing']['native']['type']	= "native";
		$pfb['existing']['match']['folder']	= "{$pfb['matchdir']}";
		$pfb['existing']['permit']['folder']	= "{$pfb['permitdir']}";
		$pfb['existing']['deny']['folder']	= "{$pfb['denydir']}";
		$pfb['existing']['native']['folder']	= "{$pfb['nativedir']}";
		$pfb['actual']['match']['type']		= "match";
		$pfb['actual']['permit']['type']	= "permit";
		$pfb['actual']['deny']['type']		= "deny";
		$pfb['actual']['native']['type']	= "native";
		$pfb['actual']['match']['folder']	= "{$pfb['matchdir']}";
		$pfb['actual']['permit']['folder']	= "{$pfb['permitdir']}";
		$pfb['actual']['deny']['folder']	= "{$pfb['denydir']}";
		$pfb['actual']['native']['folder']	= "{$pfb['nativedir']}";

		// Find all Enabled Continents Lists
		foreach ($continents as $continent => $pfb_alias) {
			if (is_array($config['installedpackages']['pfblockerng' . strtolower(preg_replace('/ /','',$continent))]['config']) && $pfb['enable'] == "on") {
				$continent_config = $config['installedpackages']['pfblockerng' . strtolower(preg_replace('/ /','',$continent))]['config'][0];
				if ($continent_config['action'] != "Disabled") {
					$cont_type = array ("countries4" => "_v4", "countries6" => "_v6");
					foreach ($cont_type as $c_type => $vtype) {
						if ($continent_config[$c_type] != "") {
							// Set Parameters for 'Match', 'Permit', 'Native' and 'Deny'
							if (in_array($continent_config['action'],array('Match_Both','Match_Inbound','Match_Outbound','Alias_Match'))) {
								$pfb['existing']['match'][] = "{$pfb_alias}{$vtype}";
							} elseif (in_array($continent_config['action'],array('Permit_Both','Permit_Inbound','Permit_Outbound','Alias_Permit'))){
								$pfb['existing']['permit'][] = "{$pfb_alias}{$vtype}";
							} elseif ($continent_config['action'] == "Alias_Native") {
								$pfb['existing']['native'][] = "{$pfb_alias}{$vtype}";
							} else {
								$pfb['existing']['deny'][] = "{$pfb_alias}{$vtype},";	// Add Trailing ','
							}
						}
					}
				}
			}
		}

		// Find all Enabled IPv4/IPv6 Lists
		$list_type = array ("pfblockernglistsv4" => "_v4", "pfblockernglistsv6" => "_v6");
		foreach ($list_type as $ip_type => $vtype) {
			if ($config['installedpackages'][$ip_type]['config'] != "" && $pfb['enable'] == "on") {
				foreach ($config['installedpackages'][$ip_type]['config'] as $list) {
					if (is_array($list['row']) && $list['action'] != "Disabled") {
						foreach ($list['row'] as $row) {
							if ($vtype == "_v4") {
								$pfb_alias = "{$row['header']}";
							} else {
								$pfb_alias = "{$row['header']}_v6";
							}
							// Collect Enabled Lists
							if ($row['url'] != "" && $row['state'] != "Disabled") {
								// Set Parameters for 'Match', 'Permit', 'Native' and 'Deny'
								if (in_array($list['action'],array('Match_Both','Match_Inbound','Match_Outbound','Alias_Match'))) {
									$pfb['existing']['match'][] = "{$pfb_alias}";
								} elseif (in_array($list['action'],array('Permit_Both','Permit_Inbound','Permit_Outbound','Alias_Permit'))) {
									$pfb['existing']['permit'][] = "{$pfb_alias}";
								} elseif ($list['action'] == "Alias_Native") {
									$pfb['existing']['native'][] = "{$pfb_alias}";
								} else {
									$pfb['existing']['deny'][] = "{$pfb_alias},";	// Add Trailing ','
								}
							}
						}
					}
				}
			}
		}

		// Find all Enabled IPv4 'Custom List' Header Names and Check if 'Emerging Threats Update' and 'Custom List Update' Needs Force Updating
		$list_type = array ("pfblockernglistsv4" => "_v4", "pfblockernglistsv6" => "_v6");
		foreach ($list_type as $ip_type => $vtype) {
			if ($config['installedpackages'][$ip_type]['config'] != "" && $pfb['enable'] == "on") {
				$count = -1;
				foreach ($config['installedpackages'][$ip_type]['config'] as $list) {
					if (is_array($list['row']) && $list['action'] != "Disabled") {
						$count++;
						// Check if 'Emerging Threats Update' Needs Updating before next CRON Event.
						if (is_array($list['row']) && $row['state'] != "Disabled" && $pfb['etupdate'] == "enabled" && $vtype == "_v4") {
							foreach ($list['row'] as $row) {
								$aliasname = $row['header'];
								if ($row['format'] == "et") {
									unlink_if_exists("{$pfb['denydir']}/{$aliasname}.txt");
									$config['installedpackages']['pfblockerngreputation']['config'][0]['et_update'] = "disabled";
									$pfb['cron_mod'] = TRUE;
									break;
								}
							}
						}
					}

					// Collect Enabled Custom List Box Aliases
					if (pfbng_text_area_decode($list['custom']) != "") {
						if ($vtype == "_v4") {
							$pfb_alias = "{$list['aliasname']}_custom";
						} else {
							$pfb_alias = "{$list['aliasname']}_custom_v6";
						}
						// Determine Folder Location for 'List'
						if (in_array($list['action'],array('Match_Both','Match_Inbound','Match_Outbound','Alias_Match'))) {
							$pfb['existing']['match'][] = "{$pfb_alias}";
							$pfbfolder = "{$pfb['matchdir']}";
						} elseif (in_array($list['action'],array('Permit_Both','Permit_Inbound','Permit_Outbound','Alias_Permit'))) {
							$pfb['existing']['permit'][] = "{$pfb_alias}";
							$pfbfolder = "{$pfb['permitdir']}";
						} elseif ($list['action'] == "Alias_Native") {
							$pfb['existing']['native'][] = "{$pfb_alias}";
							$pfbfolder = "{$pfb['nativedir']}";
						} else {
							$pfb['existing']['deny'][] = "{$pfb_alias},";	// Add Trailing ','
							$pfbfolder = "{$pfb['denydir']}";
						}
						// Determine if 'Custom List' Needs Force Updating before next CRON Event.
						if ($list['custom_update'] == "enabled") {
							unlink_if_exists("{$pfbfolder}/{$pfb_alias}.txt");
							// Uncheck 'Enabled' in List 'Custom_update' Setting
							$config['installedpackages'][$ip_type]['config'][$count]['custom_update'] = "disabled";
							$pfb['cron_mod'] = TRUE;
						}
					}
				}
			}
		}

		// Collect all .txt file Names for each List Type
		$list_types = array('match' => $pfb['matchdir'], 'permit' => $pfb['permitdir'], 'deny' => $pfb['denydir'], 'native' => $pfb['nativedir']);
		foreach ($list_types as $type => $pfbfolder) {
			$pfb_files = glob("$pfbfolder/*.txt");
			foreach ($pfb_files as $pfb_list) {
				$pfb_file = basename($pfb_list,".txt");
				if ($type == "deny") {
					$pfb['actual'][$type][] = "{$pfb_file},";	// Add Trailing ','
				} else {
					$pfb['actual'][$type][] = "{$pfb_file}";
				}
			}
		}

		// Flag to execute pfctl and Rules Ordering
		$pfb['remove'] = FALSE;
		// Execute Final Summary as a List was Removed
		$pfb['summary'] = FALSE;

		// Process to Remove Lists from Masterfile/DB Folder if they do not Exist
		if (isset($pfb['existing'])) {
			foreach ($pfb['existing'] as $pfb_exist) {
				$existing_type = $pfb_exist['type'];
				$pfbfolder = $pfb_exist['folder'];
				foreach ($pfb['actual'] as $pfb_act) {
					$actual_type = $pfb_act['type'];
					if ($existing_type == $actual_type) {
						switch ($existing_type) {
							case "deny":
								$results = array_diff($pfb_act, $pfb_exist);
								$f_result = implode($results);
								if ($f_result != "") {
									$log = "[ Removing List(s) : {$f_result} ]\n";
									pfb_logger("{$log}","1");
									// Script to Remove un-associated Lists
									exec ("{$pfb['script']} remove x x x {$f_result} >> {$pfb['log']} 2>&1");
									$pfb['summary'] = TRUE;
									$pfb['remove'] = TRUE;
								}
								break;
							case "match":
							case "permit":
							case "native":
								$results = array_diff($pfb_act, $pfb_exist);
								// This variable ($f_result) used in next section below.
								$f_result = implode($results);
								if (!empty($results)) {
									foreach ($results as $pfb_result) {
										$log = "[ Removing List : {$pfb_result} ]\n";
										pfb_logger("{$log}","1");
										unlink_if_exists("{$pfbfolder}/{$pfb_result}.txt");
									}
									$pfb['summary'] = TRUE;
									$pfb['remove'] = TRUE;
								}
								break;
						}

						// Allow rebuilding of changed Alias to purge 'SKIP' Lists (when pfBlockerNG is enabled)
						$list_type = array ("pfblockernglistsv4" => "_v4", "pfblockernglistsv6" => "_v6");
						foreach ($list_type as $ip_type => $vtype) {
							if ($f_result != "" && $pfb['enable'] == "on") {
								foreach ($results as $removed_header) {
									if ($config['installedpackages'][$ip_type]['config'] != "") {
										foreach ($config['installedpackages'][$ip_type]['config'] as $list) {
											$alias = "pfB_" . preg_replace("/\W/","",$list['aliasname']);
											if (is_array($list['row'])) {
												foreach ($list['row'] as $row) {
													$removed = rtrim($removed_header, ',');
													if ($row['header'] == $removed) {
														$pfb['summary'] = TRUE;
														$pfb['remove'] = TRUE;
														// Add Alias to Update Array
														$pfb_alias_lists[] = "{$alias}";
														$pfb_alias_lists_all[] = "{$alias}";
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}

	#########################################################
	#	Clear Match/Pass/ET/Original Files/Folders	#
	#########################################################

	// When pfBlockerNG is Disabled and 'Keep Blocklists' is Disabled.
	if ($pfb['enable'] == "" && $pfb['keep'] == "" && !$pfb['install']) {
		$log = "\n  Removing DB Files/Folders \n";
		pfb_logger("{$log}","1");

		unlink_if_exists("{$pfb['dbdir']}/masterfile");
		unlink_if_exists("{$pfb['dbdir']}/mastercat");
		unlink_if_exists("{$pfb['supptxt']}");
		rmdir_recursive("{$pfb['origdir']}");
		rmdir_recursive("{$pfb['matchdir']}");
		rmdir_recursive("{$pfb['permitdir']}");
		rmdir_recursive("{$pfb['denydir']}");
		rmdir_recursive("{$pfb['nativedir']}");
		rmdir_recursive("{$pfb['etdir']}");
	}


	#################################################
	#	Create IP Suppression Txt File		#
	#################################################

	if ($pfb['enable'] == "on" && $pfb['supp'] == "on") {
		pfb_create_suppression_file();
	}


	#################################
	#	Assign Countries	#
	#################################

	foreach ($continents as $continent => $pfb_alias) {
		if (is_array($config['installedpackages']['pfblockerng' . strtolower(preg_replace('/ /','',$continent))]['config'])) {
			$continent_config = $config['installedpackages']['pfblockerng' . strtolower(preg_replace('/ /','',$continent))]['config'][0];
			$cc_name = 'pfblockerng' . strtolower(preg_replace('/ /','',$continent));
			if ($continent_config['action'] != "Disabled" && $pfb['enable'] == "on") {

				// Determine if Continent Lists require Action (IPv4 and IPv6)
				$cont_type = array ("countries4" => "_v4", "countries6" => "_v6");
				foreach ($cont_type as $c_type => $vtype) {

					// Determine 'List' details (return array $pfbarr)
					pfb_determine_list_detail($continent_config['action'], "{$pfb_alias}{$vtype}", $cc_name, "0");
					$pfb['skip']	= $pfbarr['skip'];
					$pfb_descr	= $pfbarr['descr'];
					$pfbfolder	= $pfbarr['folder'];
					$log_tab	= $pfbarr['logtab'];
					$aports		= $pfbarr['aports'];
					$adest		= $pfbarr['adest'];
					$aproto		= $pfbarr['aproto'];

					$continent = "";
					if ($continent_config[$c_type] != "") {

						// Collect Selected ISO Country Files
						foreach (explode(",", $continent_config[$c_type]) as $iso) {
							if ($iso != "" && file_exists($pfb['ccdir'] .'/' . $iso . $vtype . '.txt')) {
								$continent .= file_get_contents ($pfb['ccdir'] . '/' . $iso . $vtype . '.txt');
							}
						}

						if (file_exists($pfb['origdir'] . '/' . $pfb_alias . $vtype . '.orig')) {
							$continent_existing = preg_replace('/\s/', '', file ($pfb['origdir'] . '/' . $pfb_alias . $vtype . '.orig'));
						}
						// Collect New Continent Data for comparison. Cleanup Array for Comparison
						$continent_new = preg_split ('/$\R?^/m', $continent);
						$line = count ( $continent_new ) - 1;
						$match = $continent_new[$line];
						$continent_new[$line] = rtrim($match, "\n");

						// Check if pfBlockerNG pfctl Continent Tables are Empty (pfBlockerNG was Disabled w/ "keep", then Re-enabled)
						$pfctlck = exec ("/sbin/pfctl -vvsTables | grep -A1 {$pfb_alias}{$vtype} | awk '/Addresses/ {s+=$2}; END {print s}'");
						if (empty($pfctlck) && file_exists($pfbfolder . '/' . $pfb_alias . $vtype . '.txt')) {
							$file_cont = file_get_contents($pfbfolder . '/' . $pfb_alias . $vtype . '.txt');
							@file_put_contents($pfb['aliasdir'] . '/' . $pfb_alias . $vtype . '.txt',$file_cont, LOCK_EX);
							// PFCTL - Update Only Aliases that have been updated. ('Reputation' Disabled)
							$pfb_alias_lists[] = "{$pfb_alias}{$vtype}";
						}

						// Collect Active Alias Lists (Used for pfctl Update when 'Reputation' is enabled).
						$pfb_alias_lists_all[] = "{$pfb_alias}{$vtype}";

						// Compare Existing (Original File) and New Continent Data
						if ($continent_new === $continent_existing && !empty($pfctlck) && file_exists($pfbfolder . '/' . $pfb_alias . $vtype . '.txt') && $pfb['reuse'] == "") {
							if (!$pfb['save']) {
								$log = "\n[ {$pfb_alias}{$vtype} ]{$log_tab} exists, Reloading File [ NOW ]";
								pfb_logger("{$log}","1");
							}
						} else {
							// Do not proceed with Changes on User 'Save'
							if (!$pfb['save']) {
								$log = "\n[ {$pfb_alias}{$vtype} ]{$log_tab} Changes Found... Updating \n";
								pfb_logger("{$log}","1");

								// Test to Skip d-dup and p-dup functions when changes are found.
								$pfb['dupcheck'] = TRUE;

								$pfb_alias_lists[] = "{$pfb_alias}{$vtype}";

								// Script to call Duplication Check Process only on IPv4
								if ($pfb['dup'] == "on" && $pfb['skip'] && $vtype == "_v4") {
									// Copy Continent Data to 'lists' folder for duplication processing
									@file_put_contents($pfb['origdir'] . '/' . $pfb_alias . $vtype . '.orig',$continent, LOCK_EX);
									@file_put_contents($pfb['denydir'] . '/' . $pfb_alias . $vtype . '.txt',$continent, LOCK_EX);
									exec ("{$pfb['script']} continent {$pfb_alias}{$vtype} >> {$pfb['log']} 2>&1");
									$continent = file_get_contents($pfbfolder . '/' . $pfb_alias . $vtype . '.txt');
									@file_put_contents($pfb['aliasdir'] . '/' . $pfb_alias . $vtype . '.txt',$continent, LOCK_EX);
								} else {
									@file_put_contents($pfbfolder . '/' . $pfb_alias . $vtype . '.txt',$continent, LOCK_EX);
									@file_put_contents($pfb['origdir'] . '/' . $pfb_alias . $vtype . '.orig',$continent, LOCK_EX);
									@file_put_contents($pfb['aliasdir'] . '/' . $pfb_alias . $vtype . '.txt',$continent, LOCK_EX);
								}

								// Check if File Exists and is > 0 in Size and Save alias file
								$file_chk = "0";
								$cont_chk = "{$pfbfolder}/{$pfb_alias}{$vtype}.txt";
								if (file_exists($cont_chk) && @filesize($cont_chk) > 0) {
									$file_chk = exec ("/usr/bin/grep -cv '^#\|^$' {$cont_chk}");
								}

								if ($file_chk == "0" || $file_chk == "1") {
									$new_file = "1.1.1.1\n";
									@file_put_contents($pfbfolder . '/' . $pfb_alias . $vtype . '.txt', $new_file, LOCK_EX);
									@file_put_contents($pfb['aliasdir'] . "/" . $pfb_alias . $vtype . ".txt", $new_file, LOCK_EX);
									$log = "[ {$pfb_alias}{$vtype} ] Found no Unique IPs, Adding '1.1.1.1' to avoid Empty File\n";
									pfb_logger("{$log}","1");
								}
							}
						}

						if (file_exists($pfbfolder . '/' . $pfb_alias . $vtype . '.txt')) {
							// Create alias config
							$new_aliases_list[] = "{$pfb_alias}{$vtype}";

							$pfb_contlog = $continent_config['aliaslog'];

							$new_aliases[] = array( "name"		=> "{$pfb_alias}{$vtype}",
										"url"		=> "{$pfb['weblocal']}?pfb={$pfb_alias}{$vtype}",
										"updatefreq"	=> "32",
										"address"	=> "",
										"descr"		=> "pfBlockerNG {$vtype} {$pfb_descr} Country Alias",
										"type"		=> "urltable",
										"detail"	=> "DO NOT EDIT THIS ALIAS"
										);

							// Create rule if action permits
							switch ($continent_config['action']) {
								case "Deny_Both":
								case "Deny_Outbound":
									$rule = $base_rule;
									$rule['type'] = "{$pfb['deny_action_outbound']}";
									if ($vtype == "_v6")
										$rule['ipprotocol'] = "inet6";
									if ($pfb['float'] == "on")
										$rule['direction'] = "any";
									$rule['descr']= "{$pfb_alias}{$vtype}{$pfb['suffix']}";
									$rule['source'] = array("any" => "");
									$rule['destination'] = array("address" => "{$pfb_alias}{$vtype}");
									if ($pfb['config']['enable_log'] == "on" || $pfb_contlog == "enabled")
										$rule['log'] = "";
									$deny_outbound[] = $rule;
									if ($continent_config['action'] != "Deny_Both")
										break;
								case "Deny_Inbound":
									$rule = $base_rule;
									$rule['type'] = "{$pfb['deny_action_inbound']}";
									if ($vtype == "_v6")
										$rule['ipprotocol'] = "inet6";
									if ($pfb['float'] == "on")
										$rule['direction'] = "any";
									$rule['descr'] = "{$pfb_alias}{$vtype}{$pfb['suffix']}";
									$rule['source'] = array("address" => "{$pfb_alias}{$vtype}");
									if (!empty($adest) && !empty($aports)) {
										$rule['destination'] = array("address" => "{$adest}", "port" => "{$aports}");
									} elseif (!empty($adest) && empty($aports)) {
										$rule['destination'] = array("address" => "{$adest}");
									} elseif (empty($adest) && !empty($aports)) {
										$rule['destination'] = array("any" => "", "port" => "{$aports}");
									} else {
										$rule['destination'] = array("any" => "");
									}
									if (!empty($adest) && $continent_config['autonot'] == "on")
										$rule['destination']['not'] = "";
									if (!empty($aproto))
										$rule['protocol'] = "{$aproto}";
									if ($pfb['config']['enable_log'] == "on" || $pfb_contlog == "enabled")
										$rule['log'] = "";
									$deny_inbound[] = $rule;
									break;
								case "Permit_Both":
								case "Permit_Outbound":
									$rule = $base_rule;
									$rule['type'] = "pass";
									if ($vtype == "_v6")
										$rule['ipprotocol'] = "inet6";
									if ($pfb['float'] == "on")
										$rule['direction'] = "any";
									$rule['descr'] = "{$pfb_alias}{$vtype}{$pfb['suffix']}";
									$rule['source'] = array ("any" => "");
									$rule['destination'] = array("address" => "{$pfb_alias}{$vtype}");
									if ($pfb['config']['enable_log'] == "on" || $pfb_contlog == "enabled")
										$rule['log'] = "";
									$permit_outbound[] = $rule;
									if ($continent_config['action'] != "Permit_Both")
										break;
								case "Permit_Inbound":
									$rule = $base_rule;
									$rule['type'] = "pass";
									if ($vtype == "_v6")
										$rule['ipprotocol'] = "inet6";
									if ($pfb['float'] == "on")
										$rule['direction'] = "any";
									$rule['descr'] = "{$pfb_alias}{$vtype}{$pfb['suffix']}";
									$rule['source'] = array("address"=> "{$pfb_alias}{$vtype}");
									if (!empty($adest) && !empty($aports)) {
										$rule['destination'] = array("address" => "{$adest}", "port" => "{$aports}");
									} elseif (!empty($adest) && empty($aports)) {
										$rule['destination'] = array("address" => "{$adest}");
									} elseif (empty($adest) && !empty($aports)) {
										$rule['destination'] = array("any" => "", "port" => "{$aports}");
									} else {
										$rule['destination'] = array("any" => "");
									}
									if (!empty($adest) && $continent_config['autonot'] == "on")
										$rule['destination']['not'] = "";
									if (!empty($aproto))
										$rule['protocol'] = "{$aproto}";
									if ($pfb['config']['enable_log'] == "on" || $pfb_contlog == "enabled")
										$rule['log'] = "";
									$permit_inbound[] = $rule;
									break;
								case "Match_Both":
								case "Match_Outbound":
									$rule = $base_rule_float;
									$rule['type'] = "match";
									if ($vtype == "_v6")
										$rule['ipprotocol'] = "inet6";
									$rule['direction'] = "any";
									$rule['descr'] = "{$pfb_alias}{$vtype}{$pfb['suffix']}";
									$rule['source'] = array("any" => "");
									$rule['destination'] = array("address" => "{$pfb_alias}{$vtype}");
									if ($pfb['config']['enable_log'] == "on" || $pfb_contlog == "enabled")
										$rule['log'] = "";
									$match_outbound[] = $rule;
									if ($list['action'] != "Match_Both")
										break;
								case "Match_Inbound":
									$rule = $base_rule_float;
									$rule['type'] = "match";
									if ($vtype == "_v6")
										$rule['ipprotocol'] = "inet6";
									$rule['direction'] = "any";
									$rule['descr'] = "{$pfb_alias}{$vtype}{$pfb['suffix']}";
									$rule['source'] = array ("address" => "{$pfb_alias}{$vtype}");
									if (!empty($adest) && !empty($aports)) {
										$rule['destination'] = array("address" => "{$adest}", "port" => "{$aports}");
									} elseif (!empty($adest) && empty($aports)) {
										$rule['destination'] = array("address" => "{$adest}");
									} elseif (empty($adest) && !empty($aports)) {
										$rule['destination'] = array("any" => "", "port" => "{$aports}");
									} else {
										$rule['destination'] = array("any" => "");
									}
									if (!empty($adest) && $continent_config['autonot'] == "on")
										$rule['destination']['not'] = "";
									if (!empty($aproto))
										$rule['protocol'] = "{$aproto}";
									if ($pfb['config']['enable_log'] == "on" || $pfb_contlog == "enabled")
										$rule['log'] = "";
									$match_inbound[] = $rule;
									break;
							}
						} else {
							// unlink continent list if any
							unlink_if_exists($pfb['aliasdir'] . '/' . $pfb_alias . $vtype . '.txt');
						}
					}
				}
			}
			// mark pfctl aliastable for cleanup
			if (!in_array($pfb_alias, $aliases_list)) {
				$aliases_list[] = "{$pfb_alias}{$vtype}";
			}
		}
	}
	// UNSET variables
	unset ($continent, $continent_existing, $continent_new);

	#################################################
	#	Download and Collect IPv4/IPv6 lists	#
	#################################################

	// IPv4 REGEX Definitions
	$pfb['range']	= '/((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))-((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))/';
	$pfb['block']	= '/(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[ 0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.([0]{1})\s+/';
	$pfb['cidr']	= '/(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)?\/([0-9]{2}|[0-9]{1})/';
	$pfb['single']	= '/(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\s+/';
	$pfb['s_html']	= '/(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/';

	// IPv4 preg_replace Regex Filter array
	$pfb_ipreg = array();
	$pfb_ipreg[0] = '/\b0+(?=\d)/';					// Remove any Leading Zeros in each Octet
	$pfb_ipreg[1] = '/\s/';						// Remove any Whitespaces
	$pfb_ipreg[2] = '/\/32/';					// Remove any /32 CIDR
	$pfb_ipreg[3] = '/127\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/';	// Remove any Loopback Addresses 127/8
	$pfb_ipreg[4] = '/0\.0\.0\.0/';					// Remove 0.0.0.0

	// IPv6 REGEX Definitions  -- ** Still Needs some Adjustment on Regex Definition for IPv6 **
	// https://mebsd.com/coding-snipits/php-regex-ipv6-with-preg_match.html
	$pattern1 = '([A-Fa-f0-9]{1,4}:){7}[A-Fa-f0-9]{1,4}';
	$pattern2 = '[A-Fa-f0-9]{1,4}::([A-Fa-f0-9]{1,4}:){0,5}[A-Fa-f0-9]{1,4}';
	$pattern3 = '([A-Fa-f0-9]{1,4}:){2}:([A-Fa-f0-9]{1,4}:){0,4}[A-Fa-f0-9]{1,4}';
	$pattern4 = '([A-Fa-f0-9]{1,4}:){3}:([A-Fa-f0-9]{1,4}:){0,3}[A-Fa-f0-9]{1,4}';
	$pattern5 = '([A-Fa-f0-9]{1,4}:){4}:([A-Fa-f0-9]{1,4}:){0,2}[A-Fa-f0-9]{1,4}';
	$pattern6 = '([A-Fa-f0-9]{1,4}:){5}:([A-Fa-f0-9]{1,4}:){0,1}[A-Fa-f0-9]{1,4}';
	$pattern7 = '([A-Fa-f0-9]{1,4}:){6}:[A-Fa-f0-9]{1,4}';
	$pattern8 = '[A-Fa-f0-9]{1,4}:[A-Fa-f0-9]{1,4}:[A-Fa-f0-9]{1,4}::\/[0-9]{2}';
	$pattern9 = '[A-Fa-f0-9]{1,4}:([A-Fa-f0-9]{1,4}::)\/[0-9]{2}';
	$pattern10 = '[A-Fa-f0-9]{1,4}::\/[0-9]{2}';
	$pfb['ipv6'] = "/($pattern1)|($pattern2)|($pattern3)|($pattern4)|($pattern5)|($pattern6)|($pattern7)|($pattern8)|($pattern9)|($pattern10)/";

	$pfb['supp_update'] = FALSE;
	$list_type = array ("pfblockernglistsv4" => "_v4", "pfblockernglistsv6" => "_v6");
	foreach ($list_type as $ip_type => $vtype) {
		if ($config['installedpackages'][$ip_type]['config'] != "") {
			foreach ($config['installedpackages'][$ip_type]['config'] as $list) {
				if ($list['action'] != "Disabled" && $pfb['enable'] == "on" && !$pfb['save'] && is_array($list['row'])) {
					// capture Alias Name
					$alias = "pfB_" . preg_replace("/\W/","",$list['aliasname']);
					foreach ($list['row'] as $row) {
						if ($row['url'] != "" && $row['state'] != "Disabled") {

							if ($vtype == "_v4") {
								$header_url = "{$row['header']}";
							} else {
								$header_url = "{$row['header']}_v6";
							}

							// Determine 'List' details (return array $pfbarr)
							pfb_determine_list_detail($list['action'], $header_url, "", "");
							$pfb['skip']	= $pfbarr['skip'];
							$pfbfolder	= $pfbarr['folder'];
							$log_tab	= $pfbarr['logtab'];

							// Empty Header Field Validation Check
							if (empty($header_url) || preg_match("/\W/",$header_url)) {
								$log = "\n [ {$row['url']} ]\n ** TERMINATED - Header contains Blank/International/Special or Spaces\n";
								pfb_logger("{$log}","2");
								continue;
							}

							// Collect Active Alias List (Used for pfctl Update when 'Reputation' is enabled.
							$pfb_alias_lists_all[] = "{$alias}";

							if (file_exists($pfbfolder . '/' . $header_url . '.txt') && $pfb['reuse'] == "") {
								if ($row['state'] == "Hold") {
									$log = "\n[ {$header_url} ]{$log_tab} Static Hold [ NOW ]";
								} else {
									$log = "\n[ {$header_url} ]{$log_tab} exists, Reloading File [ NOW ]";
								}
								pfb_logger("{$log}","1");
							} else {
								if ($pfb['reuse'] == "on" && file_exists($pfb['origdir'] . '/' . $header_url . '.orig')) {
									$log = "\n[ {$header_url} ]{$log_tab} Using Previously Downloaded File [ NOW ]";
								} else {
									$log = "\n[ {$header_url} ]{$log_tab} Downloading New File [ NOW ]";
								}
								pfb_logger("{$log}","1");

								$list_url = "{$row['url']}";
								if (!$pfb['reuse'] == "on") {
									// Perform Remote URL Date/Time Stamp checks
									$host = @parse_url($row['url']);
									if ($row['format'] != "rsync" || $row['format'] != "html") {
										if ($host['host'] == "127.0.0.1" || $host['host'] == $pfb['iplocal'] || empty($host['host'])) {
											$remote_tds = "local";
										} else {
											$remote_tds = @implode(preg_grep("/Last-Modified/", get_headers($list_url)));
											$remote_tds = preg_replace("/^Last-Modified: /","", $remote_tds);
										}
									}
								}

								$url_list = array();
								if ($row['format'] == "gz" || $row['format'] == "gz_2") {
									$file_dwn = "{$pfb['origdir']}/{$header_url}.gz";
									if ($pfb['reuse'] == "on" && file_exists($file_dwn)) {
										// File Exists/Reuse
									} else {
										$url_gz = "{$row['url']}";
										$file_gz = @file_get_contents($url_gz);
										@file_put_contents($file_dwn, $file_gz, LOCK_EX);
										if ($remote_tds == "local")
											$remote_tds = gmdate ("D, d M Y H:i:s T", filemtime($file_dwn));
										$remote_stamp = strtotime($remote_tds);
										if (!empty($remote_stamp) && file_exists($file_dwn))
											touch ($file_dwn, $remote_stamp);
									}
									$url_list = @gzfile($file_dwn);
								}

								// IBlock Large Files mixed with IPs and Domains. PHP mem of 256M can't handle very large Files.
								if ($row['format'] == "gz_lg") {
									$file_dwn = "{$pfb['origdir']}/{$header_url}.gz";
									if ($pfb['reuse'] == "on" && file_exists($file_dwn)) {
										// File Exists/Reuse
									} else {
										$url_gz = "{$row['url']}";
										$file_gz = @file_get_contents($url_gz);
										@file_put_contents($file_dwn, $file_gz, LOCK_EX);
										exec ("/usr/bin/gunzip -c {$file_dwn} | /usr/bin/sed 's/^.*://' | /usr/bin/grep -v '[a-zA-Z]\|^$\|^#' > {$pfb['origdir']}/{$header_url}.orig");
										if ($remote_tds == "local")
											$remote_tds = gmdate ("D, d M Y H:i:s T", filemtime($file_dwn));
										$remote_stamp = strtotime($remote_tds);
										if (!empty($remote_stamp) && file_exists($file_dwn))
											touch ($file_dwn, $remote_stamp);
									}
									$url_list = @file($pfb['origdir'] . '/' . $header_url . '.orig');
								}

								elseif ($row['format'] == "zip") {
									$file_dwn = "{$pfb['origdir']}/{$header_url}.zip";
									if ($pfb['reuse'] == "on" && file_exists($file_dwn)) {
										// File Exists/Reuse
									} else {
										$url_zip = "{$row['url']}";
										if (!$file_zip = @file_get_contents($url_zip)) {
											$error = error_get_last();
											$log = "\n [ {$header_url} ] {$error['message']}\n";
											pfb_logger("{$log}","2");
										} else {
											@file_put_contents($file_dwn, $file_zip, LOCK_EX);
											if ($remote_tds == "local")
												$remote_tds = gmdate ("D, d M Y H:i:s T", filemtime($file_dwn));
											$remote_stamp = strtotime($remote_tds);
											if (!empty($remote_stamp) && file_exists($file_dwn))
												touch ($file_dwn, $remote_stamp);
										}
									}
									$zip_out = "{$pfb['origdir']}/{$header_url}.orig";
									exec ("/usr/bin/tar -xOf {$file_dwn} | tr ',' '\n' > {$zip_out}");
									$url_list = @file($zip_out);
								}

								elseif ($row['format'] == "et") {
									$file_dwn = "{$pfb['origdir']}/{$header_url}.gz";
									// Script to Call ET IQRISK Process
									if ($pfb['reuse'] == "on" && file_exists($file_dwn)) {
										// File Exists/Reuse
									} else {
										$url_et = "{$row['url']}";
										$file_et = @file_get_contents($url_et);
										@file_put_contents($file_dwn, $file_et, LOCK_EX);
										if ($remote_tds == "local")
											$remote_tds = gmdate ("D, d M Y H:i:s T", filemtime($file_dwn));
										$remote_stamp = strtotime($remote_tds);
										if (!empty($remote_stamp) && file_exists($file_dwn))
											touch ($file_dwn, $remote_stamp);
									}
									exec ("{$pfb['script']} et {$header_url} x x x x x {$pfb['etblock']} {$pfb['etmatch']} >> {$pfb['log']} 2>&1");
									$url_list = @file($pfb['origdir'] . '/' . $header_url . '.orig');
								}

								elseif ($row['format'] == "xlsx") {
									$file_dwn = "{$pfb['origdir']}/{$header_url}.zip";
									// Script to Call XLSX Process
									if ($pfb['reuse'] == "on" && file_exists($file_dwn)) {
										// File Exists/Reuse
									} else {
										$url_xlsx = "{$row['url']}";
										$file_xlsx = @file_get_contents($url_xlsx);
										@file_put_contents($file_dwn, $file_xlsx, LOCK_EX);
										if ($remote_tds == "local")
											$remote_tds = gmdate ("D, d M Y H:i:s T", filemtime($file_dwn));
										$remote_stamp = strtotime($remote_tds);
										if (!empty($remote_stamp) && file_exists($file_dwn))
											touch ($file_dwn, $remote_stamp);
									}
									exec ("{$pfb['script']} xlsx {$header_url} >> {$pfb['log']} 2>&1");
									$url_list = @file($pfb['origdir'] . '/' . $header_url . '.orig');
								}

								elseif ($row['format'] == "txt") {
									$file_dwn = "{$pfb['origdir']}/{$header_url}.orig";
									if ($pfb['reuse'] == "on" && file_exists($file_dwn)) {
										$url_list = @file($file_dwn);
									} else {
										$url_other = @file($row['url']);
										$url_list = $url_other;
										@file_put_contents($file_dwn, $url_other, LOCK_EX);
										if ($remote_tds == "local")
											$remote_tds = gmdate ("D, d M Y H:i:s T", filemtime($file_dwn));
										$remote_stamp = strtotime($remote_tds);
										if (!empty($remote_stamp) && file_exists($file_dwn))
											touch ($file_dwn, $remote_stamp);
									}
								}

								elseif ($row['format'] == "html" || $row['format'] == "block") {
									$file_dwn = "{$pfb['origdir']}/{$header_url}.raw";
									if ($pfb['reuse'] == "on" && file_exists($file_dwn)) {
										// File Exists/Reuse
										$return = 0;
									} else {
										$url_html = "{$row['url']}";
										exec ("/usr/bin/fetch -v -o {$file_dwn} -T 20 '{$url_html}'",$output,$return);
									}
									if ($return == 0)
										$url_list = @file($file_dwn);
								}

								elseif ($row['format'] == "rsync") {
									$file_dwn = "{$pfb['origdir']}/{$header_url}.orig";
									if ($pfb['reuse'] == "on" && file_exists($file_dwn)) {
										// File Exists/Reuse
									} else {
										$url_rsync = "{$row['url']}";
										exec ("/usr/local/bin/rsync --timeout=5 {$url_rsync} {$file_dwn}");
									}
									$url_list = @file($file_dwn);
								}

								// extract range lists
								$new_file = "";
								if (!empty($url_list)) {
									if ($row['format'] == "gz" && $vtype == "_v4") {
										foreach ($url_list as $line) {
											if (!preg_match("/^#/", $line)) {
												// Network range 192.168.0.0-192.168.0.254
												if (preg_match($pfb['range'],$line,$matches)) {
													$a_cidr = ip_range_to_subnet_array_temp2($matches[1],$matches[2]);
													if (!empty($a_cidr)) {
														foreach ($a_cidr as $cidr) {
															$new_file .= preg_replace($pfb_ipreg,'',$cidr) . "\n";
														}
													}
												}
											}
										}
									}

									elseif ($row['format'] == "block" && $vtype == "_v4") {
										foreach ($url_list as $line) {
											if (!preg_match("/^#/", $line)) {
												// Block Type '218.77.79.0	218.77.79.255   24'
												if (preg_match($pfb['block'],$line,$matches)) {
													$new_file .= preg_replace($pfb_ipreg, '',$matches[0]) . "/24\n";
												}
											}
										}
									}

									elseif ($row['format'] == "html" && $vtype == "_v4") {
										foreach ($url_list as $line) {
											if (!preg_match("/^#/", $line)) {
												// CIDR format 192.168.0.0/16
												if (preg_match($pfb['cidr'],$line,$matches)) {
													$new_file .= preg_replace($pfb_ipreg, '',$matches[0]) . "\n";
												}
												// Single ip addresses
												elseif (preg_match($pfb['s_html'],$line,$matches)) {
													$new_file .= preg_replace($pfb_ipreg, '',$matches[0]) . "\n";
												}
											}
										}
									} 

									elseif ($vtype == "_v6") {
										foreach ($url_list as $line) {
											if (!preg_match("/^#/", $line)) {
												// IPv6 Regex Match
												if (preg_match($pfb['ipv6'],$line,$matches)) {
													$new_file .= preg_replace($pfb_ipreg, '',$matches[0]) . "\n";
												}
											}
										}
									}

									else {
										foreach ($url_list as $line) {
											if (!preg_match("/^#/", $line)) {
												// Network range 192.168.0.0-192.168.0.254
												if (preg_match($pfb['range'],$line,$matches)) {
													$a_cidr = ip_range_to_subnet_array_temp2($matches[1],$matches[2]);
													if (!empty($a_cidr)) {
														foreach ($a_cidr as $cidr) {
															$new_file .= preg_replace($pfb_ipreg,'',$cidr) . "\n";
														}
													}
												}
												// CIDR format 192.168.0.0/16
												elseif (preg_match($pfb['cidr'],$line,$matches)) {
													$new_file .= preg_replace($pfb_ipreg, '',$matches[0]) . "\n";
												}
												// Single ip addresses
												elseif (preg_match($pfb['single'],$line,$matches)) {
													$new_file .= preg_replace($pfb_ipreg, '',$matches[0]) . "\n";
												}
											}
										}
									}
								}

								// Check to see if Blocklist actually Failed Download or has no IPs listed.
								if ($row['format'] == "html" || $row['format'] == "block") {
									$url_chk = $file_dwn;
								} else {
									$url_chk = "{$pfb['origdir']}/{$header_url}.orig";
								}

								// Check if File Exists and is > 0 in Size
								$file_chk = "";
								if (file_exists($url_chk) && @filesize($url_chk) > 0) {
									$file_chk = exec ("/usr/bin/grep -cv '^#\|^$' {$url_chk}");
								}

								if ($file_chk == "0") {
									$new_file = "1.1.1.1\n";
									$url_other = $new_file;
									$log = "[ {$header_url} ] Found no IPs, Adding '1.1.1.1' to avoid Download FAIL\n";
									pfb_logger("{$log}","1");
								}

								if ($new_file != "") {
									if ($row['format'] == "gz" || $row['format'] == "gz_2" || $row['format'] == "html" || $row['format'] == "block") {
										// Re-Save these formats as original file
										$url_other = $new_file;
										@file_put_contents($pfb['origdir'] . '/' . $header_url . '.orig',$url_other, LOCK_EX);
									}

									// Save List to '.txt' format in appropriate Folder
									@file_put_contents($pfbfolder . '/' .$header_url . '.txt',$new_file, LOCK_EX);

									if ($pfb['rep'] == "on" && $pfb['skip'] && $vtype == "_v4") {
										// Script to Call p24 Process
										exec ("{$pfb['script']} p24 {$header_url} {$pfb['max']} {$pfb['dedup']} {$pfb['ccexclude']} {$pfb['ccwhite']} {$pfb['ccblack']} >> {$pfb['log']} 2>&1");
									}

									if ($pfb['dup'] == "on" && $pfb['skip'] && $vtype == "_v4") {
										// Script to call Duplication Check Process
										exec ("{$pfb['script']} duplicate {$header_url} >> {$pfb['log']} 2>&1");
									}

									// PFCTL - Update Only Aliases that have been updated only.
									$pfb_alias_lists[] = "{$alias}";
									// Launch d-dup and p-dup functions when changes are found.
									if ($pfb['skip'] && $vtype == "_v4") {
										$pfb['dupcheck'] = TRUE;
									}
									// Enable Suppression Process due to Updates
									if ($pfb['supp'] == "on" && $vtype == "_v4") {
										$pfb['supp_update'] = TRUE;
									}
								} else {
									// Log FAILED Downloads and Check if Firewall or Snort/Suricata is Blocking Host
									$log = "\n [ {$alias} {$header_url} ] Download FAIL [ NOW ]\n";
									pfb_logger("{$log}","2");

									// Rebuild Previous List File from contents of Masterfile
									if ($pfb['skip'] && $vtype == "_v4") {
										// Search with trailing Whitespace to match exact Header in Masterfile
										$header_url2 = $header_url . "[[:space:]]";
										$file_chk = exec ("/usr/bin/grep {$header_url2} {$pfb['master']} | grep -c ^");

										if (!file_exists($pfbfolder . '/' . $header_url . '.txt') && @$file_chk > 0 && file_exists($pfb['master'])) {
											$log = " [ {$alias} {$header_url} ] Found: {$file_chk} Line(s), Restoring previous List from Master \n";
											pfb_logger("{$log}","2");
											exec ("/usr/bin/grep {$header_url2} {$pfb['master']} | cut -d' ' -f2 > {$pfbfolder}/{$header_url}.txt");
										}
									}
									// A "Space" string Variable
									$sp = " ";
									$ip = @gethostbyname($host['host']);
									$ip2 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", "\"^$1\.$2\.$3\.\"", $ip);

									// Only Perform these Checks if they are not "localfiles"
									if ($host['host'] == "127.0.0.1" || $host['host'] == $pfb['iplocal'] || empty($host['host'])) {
										$log = " [ {$alias} {$header_url} ] Local File Failure \n";
										pfb_logger("{$log}","2");
									} else {
										// only perform these steps if an 'IP' is found.
										if (!empty($ip)) {
											// Query for Exact IP Match
											$result_b1 = array();
											$pfb_b1 = exec ("/usr/bin/grep ^{$ip} {$pfbfolder}/*", $result_b1);
											// Query for First Three IP Octet Matches
											$result_b2 = array();
											$pfb_b2 = exec ("/usr/bin/grep {$ip2} {$pfbfolder}/*", $result_b2);
											// Query Snort/Suricata snort2c IP Block Table
											$snort_pfb = exec("/sbin/pfctl -t snort2c -T show | grep {$ip}");

											// If an exact IP Match is not found report any First Three IP Octets.
											if (!empty($result_b1)) {
												$final_b1 = implode("\n ", $result_b1);
												$log = " [ {$alias} {$header_url}, {$ip} ] Firewall IP Block Found in : \n{$sp}{$final_b1}\n";
												pfb_logger("{$log}","2");
											} else {
												if (!empty($result_b2)) {
													$final_b2 = implode("\n ", $result_b2);
													$log = " [ {$alias} {$header_url}, {$ip} ] *Potential* Firewall IP Block Found in : \n{$sp}{$final_b2}\n";
													pfb_logger("{$log}","2");
												}
											}
											if (!empty($snort_pfb)) {
												$log = " [ {$alias} {$header_url}, {$ip} ] snort2c IP Block Found in : [ {$snort_pfb} ]\n";
												pfb_logger("{$log}","2");
											}
										} else {
											$log = " [ {$alias} {$header_url} ] No host IP found \n";
											pfb_logger("{$log}","2");
										}	
									}
								}
								// UNSET variables
								unset ($file_gz,$file_zip,$file_et,$file_xlsx,$url_other,$url_list);
							}
						}
					}
					// check custom network list
					if (pfbng_text_area_decode($list['custom']) != "") {

						if ($vtype == "_v4") {
							$aliascustom = "{$list['aliasname']}_custom";
						} else {
							$aliascustom = "{$list['aliasname']}_custom_v6";
						}

						// Collect Active Alias List (Used for pfctl Update when 'Reputation' is enabled.
						$pfb_alias_lists_all[] = "{$alias}";

						// Determine 'List' details (return array $pfbarr)
						pfb_determine_list_detail($list['action'], $aliascustom, "", "");
						$pfb['skip']	= $pfbarr['skip'];
						$pfbfolder	= $pfbarr['folder'];
						$log_tab	= $pfbarr['logtab'];

						if (file_exists($pfbfolder . '/' . $aliascustom . '.txt') && $pfb['reuse'] == "") {
							$log = "\n[ {$aliascustom} ]{$log_tab} exists, Reloading File [ NOW ]";
							pfb_logger("{$log}","1");
						} else {
							$url_list = array();
							$log = "\n[ {$aliascustom} ]{$log_tab} Loading Custom File [ NOW ]\n";
							pfb_logger("{$log}","1");

							$custom_list = pfbng_text_area_decode($list['custom']) . "\n";
							@file_put_contents($pfb['origdir'] . '/' . $aliascustom . '.orig', $custom_list, LOCK_EX);
							$url_list = @file($pfb['origdir'] . '/' . $aliascustom . '.orig');

							$new_file = "";
							if (!empty($url_list)) {
								foreach ($url_list as $line) {
									if ($vtype == "_v4") {
										// Network range 192.168.0.0-192.168.0.254
										if (preg_match($pfb['range'],$line,$matches)) {
											$a_cidr = ip_range_to_subnet_array_temp2($matches[1],$matches[2]);
											if (!empty($a_cidr)) {
												foreach ($a_cidr as $cidr) {
													$new_file .= preg_replace($pfb_ipreg, '',$cidr) . "\n";
												}
											}
										}
										// CIDR format 192.168.0.0/16
										elseif (preg_match($pfb['cidr'],$line,$matches)) {
											$new_file .= preg_replace($pfb_ipreg, '',$matches[0]) . "\n";
										}
										// Single ip addresses
										elseif (preg_match($pfb['s_html'],$line,$matches)) {
											$new_file .= preg_replace($pfb_ipreg, '',$matches[0]) . "\n";
										}
									} else {
										// IPv6 Regex
										if (preg_match($pfb['ipv6'],$line,$matches)) {
											$new_file .= preg_replace($pfb_ipreg, '',$matches[0]) . "\n";
										}
									}
								}

							}
							if ($new_file != "") {	
								// PFCTL - Collect Only Aliases that have been updated only.
								$pfb_alias_lists[] = "{$alias}";
								// Collect Updated lists for Suppression Process
								@file_put_contents($pfbfolder . '/'. $aliascustom . '.txt',$new_file, LOCK_EX);
								// Enable Suppression Process due to Updates
								if ($pfb['supp'] == "on" && $vtype == "_v4") {
									$pfb['supp_update'] = TRUE;
								}
								if ($pfb['rep'] == "on" && $pfb['skip'] && $vtype == "_v4") {
									// Script to Call p24 Process
									exec ("{$pfb['script']} p24 {$aliascustom} {$pfb['max']} {$pfb['dedup']} {$pfb['ccexclude']} {$pfb['ccwhite']} {$pfb['ccblack']} >> {$pfb['log']}  2>&1");
								}
								if ($pfb['dup'] == "on" && $pfb['skip'] && $vtype == "_v4") {
									// Script to call Duplication Check Process
									exec ("{$pfb['script']} duplicate {$aliascustom} >> {$pfb['log']} 2>&1");
								}
							} else {
								$log = "[ {$aliascustom} ] Custom List Error ]\n";
								pfb_logger("{$log}","1");
							}
						}
					}
				}
			}
		}
	}


	#################################
	#	REPUTATION PROCESSES	#
	#################################

	// IP Reputation processes (pdup and ddup)
	if ($pfb['pdup'] == "on" && $pfb['dupcheck'] && !$pfb['save'] && $pfb['enable'] == "on") {
		// Script to run pdup process
		exec ("{$pfb['script']} pdup x {$pfb['pmax']}  >> {$pfb['log']} 2>&1");
	}
	if ($pfb['dedup'] == "on" && $pfb['dupcheck'] && !$pfb['save'] && $pfb['enable'] == "on") {
		// Script to run dedup process
		exec ("{$pfb['script']} dedup x {$pfb['dmax']} {$pfb['dedup']} {$pfb['ccexclude']} {$pfb['ccwhite']} {$pfb['ccblack']} >> {$pfb['log']} 2>&1");
	}

	#################################
	#	CONFIGURE ALIASES	#
	#################################

	$list_type = array ("pfblockernglistsv4" => "_v4", "pfblockernglistsv6" => "_v6");
	foreach ($list_type as $ip_type => $vtype) {
		if ($config['installedpackages'][$ip_type]['config'] != "" && $pfb['enable'] == "on") {
			$runonce = 0;
			foreach ($config['installedpackages'][$ip_type]['config'] as $key => $list) {
				$alias = "pfB_" . preg_replace("/\W/","",$list['aliasname']);

				// Determine 'List' details (return array $pfbarr)
				pfb_determine_list_detail($list['action'], "", $ip_type, $key);
				$pfb['skip']	= $pfbarr['skip'];
				$pfb_descr	= $pfbarr['descr'];
				$pfbfolder	= $pfbarr['folder'];
				$aports		= $pfbarr['aports'];
				$adest		= $pfbarr['adest'];
				$aproto		= $pfbarr['aproto'];

				// Re-Save Only Aliases that have been updated only.
				// When 'Reputation' is used, all Aliases need to be Updated.
				$final_alias = array();
				if ($pfb['dedup'] == "on" || $pfb['pdup'] == "on") {
					if (!empty($pfb_alias_lists_all)) {
						$final_alias = array_unique($pfb_alias_lists_all);
					}
				} else {
					if (!empty($pfb_alias_lists)) {
						$final_alias = array_unique($pfb_alias_lists);
					}
				}

				if ($list['action'] != "Disabled") {
					// remove empty lists files if any
					if (is_array($list['row'])) {
						$update = 0;
						${$alias} = "";
						foreach ($list['row'] as $row) {
							if ($row['url'] != "" && $row['state'] != "Disabled") {
								if ($vtype == "_v4") {
									$header_url = "{$row['header']}";
								} else {
									$header_url = "{$row['header']}_v6";
								}
								$pfctlck = exec ("/sbin/pfctl -vvsTables | grep -A1 {$alias} | awk '/Addresses/ {s+=$2}; END {print s}'");

								// Update Alias if List File Exists and its been updated or if the Alias URL Table is Empty.
								if (file_exists($pfbfolder . "/" . $header_url . ".txt") && in_array($alias, $final_alias) || file_exists($pfbfolder . "/" . $header_url . ".txt") && empty($pfctlck)) {
									// Script to run Suppression process (Print Header Only)
									if ($pfb['supp'] == "on" && $vtype == "_v4" && $runonce == 0 && $pfb['supp_update']) {
										exec ("{$pfb['script']} suppress x x x suppressheader >> {$pfb['log']} 2>&1");
										$runonce++;
									}
									// Script to run Suppression Process (Body)
									if ($pfb['supp'] == "on" && $vtype == "_v4" && $pfb['supp_update']) {
										if ($pfb['dup'] == "on" || !$pfb['skip']) {
											// Execute if Duplication Process is Enabled or List is Permit or Match
											exec ("{$pfb['script']} suppress x x x {$header_url}\|{$pfbfolder}/ >> {$pfb['log']} 2>&1");
										} else {
											// Execute if Duplication Process is Disabled
											exec ("{$pfb['script']} suppress x x off {$header_url}\|{$pfbfolder}/ >> {$pfb['log']} 2>&1");
										}
									}
									${$alias} .= file_get_contents($pfbfolder . '/' . $header_url . '.txt');
									$update++;
								}
							}
						}
					}

					// check custom network list
					if ($vtype == "_v4") {
						$aliasname = "{$list['aliasname']}_custom";
					} else {
						$aliasname = "{$list['aliasname']}_custom_v6";
					}

					// Update Alias if List File Exists and its been updated or if the Alias URL Table is Empty.
					$pfctlck = exec ("/sbin/pfctl -vvsTables | grep -A1 {$alias} | awk '/Addresses/ {s+=$2}; END {print s}'");

					if (pfbng_text_area_decode($list['custom']) != "") {
						if (file_exists($pfbfolder . "/" . $aliasname . ".txt") && in_array($alias, $final_alias) || file_exists($pfbfolder . "/" . $aliasname . ".txt") && empty($pfctlck)) {
							${$alias} .= file_get_contents($pfbfolder . '/' . $aliasname . '.txt');
							$update++;
						}
					}
					// Determine Validity of Alias URL Tables/Rules. ie: Don't create Empty URL Tables or Aliases
					if (${$alias} == "" && empty($pfctlck)) {
						unlink_if_exists($pfb['aliasdir'] . '/' . $alias. '.txt');
					} else {
						// Save Only Aliases that have been updated.
						if ($update > 0) {
							@file_put_contents($pfb['aliasdir'] . '/' . $alias. '.txt',${$alias}, LOCK_EX);
						}

						$alias_log = $list['aliaslog'];
						// create alias
						$new_aliases_list[] = "{$alias}";

						$new_aliases[] = array(	"name"		=> "{$alias}",
									"url"		=> "{$pfb['weblocal']}?pfb={$alias}",
									"updatefreq"	=> "32",
									"address"	=> "",
									"descr"		=> "pfBlockerNG {$pfb_descr} List Alias",
									"type"		=> "urltable",
									"detail"	=> "DO NOT EDIT THIS ALIAS"
									);

						// Create rule if action permits
						switch ($list['action']) {
							case "Deny_Both":
							case "Deny_Outbound":
								$rule = $base_rule;
								$rule['type'] = "{$pfb['deny_action_outbound']}";
								if ($vtype == "_v6")
									$rule['ipprotocol'] = "inet6";
								if ($pfb['float'] == "on")
									$rule['direction'] = "any";
								$rule['descr'] = "{$alias}{$pfb['suffix']}";
								$rule['source'] = array ("any" => "");
								$rule['destination'] = array ("address" => "{$alias}");
								if ($pfb['config']['enable_log'] == "on" || $alias_log == "enabled")
									$rule['log'] = "";
								$deny_outbound[] = $rule;
								if ($list['action'] != "Deny_Both")
									break;
							case "Deny_Inbound":
								$rule = $base_rule;
								$rule['type'] = "{$pfb['deny_action_inbound']}";
								if ($vtype == "_v6")
									$rule['ipprotocol'] = "inet6";
								if ($pfb['float'] == "on")
									$rule['direction'] = "any";
									$rule['descr'] = "{$alias}{$pfb['suffix']}";
								$rule['source'] = array("address" => "{$alias}");
								if (!empty($adest) && !empty($aports)) {
									$rule['destination'] = array ("address" => "{$adest}", "port" => "{$aports}");
								} elseif (!empty($adest) && empty($aports)) {
									$rule['destination'] = array ("address" => "{$adest}");
								} elseif (empty($adest) && !empty($aports)) {
									$rule['destination'] = array ("any" => "", "port" => "{$aports}");
								} else {
									$rule['destination'] = array ("any" => "");
								}
								if (!empty($adest) && $list['autonot'] == "on")
									$rule['destination']['not'] = "";
								if (!empty($aproto))
									$rule['protocol'] = "{$aproto}";
								if ($pfb['config']['enable_log'] == "on" || $alias_log == "enabled")
									$rule['log'] = "";
								$deny_inbound[] = $rule;
								break;
							case "Permit_Both":
							case "Permit_Outbound":
								$rule = $base_rule;
								$rule['type'] = "pass";
								if ($vtype == "_v6")
									$rule['ipprotocol'] = "inet6";
								if ($pfb['float'] == "on")
									$rule['direction'] = "any";
								$rule['descr'] = "{$alias}{$pfb['suffix']}";
								$rule['source'] = array ("any" => "");
								$rule['destination'] = array ("address" => "{$alias}");
								if ($pfb['config']['enable_log'] == "on" || $alias_log == "enabled")
									$rule['log'] = "";
								$permit_outbound[] = $rule;
								if ($list['action'] != "Permit_Both")
									break;
							case "Permit_Inbound":
								$rule = $base_rule;
								$rule['type'] = "pass";
								if ($vtype == "_v6")
									$rule['ipprotocol'] = "inet6";
								if ($pfb['float'] == "on")
									$rule['direction'] = "any";
								$rule['descr'] = "{$alias}{$pfb['suffix']}";
								$rule['source'] = array ("address" => "{$alias}");
								if (!empty($adest) && !empty($aports)) {
									$rule['destination'] = array ("address" => "{$adest}", "port" => "{$aports}");
								} elseif (!empty($adest) && empty($aports)) {
									$rule['destination'] = array ("address" => "{$adest}");
								} elseif (empty($adest) && !empty($aports)) {
									$rule['destination'] = array ("any" => "", "port" => "{$aports}");
								} else {
									$rule['destination'] = array ("any" => "");
								}
								if (!empty($adest) && $list['autonot'] == "on")
									$rule['destination']['not'] = "";
								if (!empty($aproto))
									$rule['protocol'] = "{$aproto}";
								if ($pfb['config']['enable_log'] == "on" || $alias_log == "enabled")
									$rule['log'] = "";
								$permit_inbound[] = $rule;
								break;
							case "Match_Both":
							case "Match_Outbound":
								$rule = $base_rule_float;
								$rule['type'] = "match";
								if ($vtype == "_v6")
									$rule['ipprotocol'] = "inet6";
								$rule['direction'] = "any";
								$rule['descr'] = "{$alias}{$pfb['suffix']}";
								$rule['source'] = array ("any" => "");
								$rule['destination'] = array ("address" => "{$alias}");
								if ($pfb['config']['enable_log'] == "on" || $alias_log == "enabled")
									$rule['log'] = "";
								$match_outbound[] = $rule;
								if ($list['action'] != "Match_Both")
									break;
							case "Match_Inbound":
								$rule = $base_rule_float;
								$rule['type'] = "match";
								if ($vtype == "_v6")
									$rule['ipprotocol'] = "inet6";
								$rule['direction'] = "any";
								$rule['descr'] = "{$alias}{$pfb['suffix']}";
								$rule['source'] = array ("address" => "{$alias}");
								if (!empty($adest) && !empty($aports)) {
									$rule['destination'] = array ("address" => "{$adest}", "port" => "{$aports}");
								} elseif (!empty($adest) && empty($aports)) {
									$rule['destination'] = array ("address" => "{$adest}");
								} elseif (empty($adest) && !empty($aports)) {
									$rule['destination'] = array ("any" => "", "port" => "{$aports}");
								} else {
									$rule['destination'] = array ("any" => "");
								}
								if (!empty($adest) && $list['autonot'] == "on")
									$rule['destination']['not'] = "";
								if (!empty($aproto))
									$rule['protocol'] = "{$aproto}";
								if ($pfb['config']['enable_log'] == "on" || $alias_log == "enabled")
									$rule['log'] = "";
								$match_inbound[] = $rule;
								break;
						}
					}
					// mark pfctl aliastable for cleanup
					if (!in_array($alias, $aliases_list)) {
						$aliases_list[] = "{$alias}";
					}
				} else {
					// unlink previous pfblockerNG alias list if any
					unlink_if_exists($pfb['aliasdir'] . '/' . $alias . '.txt');
				}
			}
		}
	}
	// Clear Variables
	${$alias} = "";


	#########################################
	#	UPDATE pfSense ALIAS TABLES	#
	#########################################

	// update pfsense alias table
	if (is_array($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $cbalias) {
			if (substr($cbalias['name'], 0, 4) == 'pfB_') {
				// mark pfctl aliastable for cleaning
				if (!in_array($cbalias['name'], $aliases_list)) {
					$aliases_list[] = $cbalias['name'];	// mark aliastable for cleaning
				}
				// remove previous aliastable file if alias is not defined any more
				if (!in_array($cbalias['name'], $new_aliases_list)) {
					unlink_if_exists($pfb['aliasdir'] . '/' . $cbalias['name'] . ".txt");
				}
			} else {
				$new_aliases[] = $cbalias;

				// Check Table Size
				if (file_exists($pfb['aliasdir'] . '/' . $alias . '.txt') && $message == "") {
					preg_match("/(\d+)/",exec("/usr/bin/grep -c ^ " . $pfb['aliasdir'] . '/' . $alias . '.txt'),$matches);
				}
				if (($matches[1] * 2.1) >= $pfb['table_limit']) {
					// alias table too large
					$message = "{$alias} alias table is too large. Reduce networks in list or increase 'Firewall Maximum Table Entries' value to at least " . (int)($matches[1] * 2.1) . ' in "system - advanced - Firewall/NAT" . ';
				}
			}
		}
	}

	// apply new alias table to xml
	if ($message == "") {
		$config['aliases']['alias'] = $new_aliases;
		$pfb['cron_mod'] = TRUE;
	}
	// UNSET Variables
	unset($new_aliases, $cbalias);


	#########################
	#	Assign Rules	#
	#########################

	// Only Execute if AutoRules are defined or if an Alias has been removed.
	if ($pfb['autorules'] || $pfb['enable'] == "" || $pfb['remove']) {
		if (count($deny_inbound) > 0 || count($permit_inbound) > 0 || count($match_inbound) > 0) {
			if ($pfb['inbound_interfaces'] == "") {
				$message = "Unable to apply rules. Inbound Interface option not configured.";
			}
		}
		if (count($deny_outbound) > 0 || count($permit_outbound) > 0 || count($match_outbound) > 0) {
			if ($pfb['outbound_interfaces'] == "") {
				$message = "Unable to apply rules. Outbound Interface option not configured.";
			}
		}

		if ($message == "") {
			$new_rules	= array();
			$permit_rules	= array();
			$match_rules	= array();
			$other_rules	= array();
			$fpermit_rules	= array();
			$fmatch_rules	= array();
			$fother_rules	= array();

			// Collect All Existing Rules
			$rules = $config['filter']['rule'];
			// Collect Existing pfSense Rules 'Pass', 'Match' and 'Other' pfSense rules into new Arrays.
			if (!empty($rules)) {
				foreach ($rules as $rule) {
					if (!preg_match("/pfB_.*" . $pfb['suffix'] . "/",$rule['descr'])) {
						// Floating rules collection 'Floating Pass/Match'. Balance to 'other'
						if ($pfb['float'] == "on") {
							if ($rule['type'] == "pass" && $rule['floating'] == "yes") {
								$fpermit_rules[] = $rule;
							} elseif ($rule['type'] == "match" && $rule['floating'] == "yes") {
								$fmatch_rules[] = $rule;
							} elseif ($rule['floating'] == "yes") {
								$fother_rules[] = $rule;
							} else {
								$other_rules[] = $rule;
							}
						} else {
							// Collect only 'Selected Inbound and Outbound Interfaces'. Balance to 'Other'
							if (in_array($rule['interface'],$pfb['inbound_interfaces']) || in_array($rule['interface'],$pfb['outbound_interfaces'])) {
								// Floating Rules 'off'. Collect 'Floating Other', Balance to 'Other'
								if ($rule['floating'] == "yes") {
									$fother_rules[] = $rule;
								} elseif ($rule['type'] == "pass") { 
									if ($pfb['order'] == "order_0") {
										$other_rules[] = $rule;
									} else {
										$permit_rules[] = $rule;
									}
								} elseif ($rule['type'] == "match") {
									if ($pfb['order'] == "order_0") {
										$other_rules[] = $rule;
									} else {
										$match_rules[] = $rule;
									}
								} else {
									$other_rules[] = $rule;
								}
							} else {
								if ($rule['floating'] == "yes") {
									$fother_rules[] = $rule;
								} else {
									$other_rules[] = $rule;
								}
							}
						}
					}
				}
			}

			#################################################################################
			#			PASS/MATCH RULES ORDER(p/m)				#
			#  ORDER 0 - pfBlockerNG / All other Rules					#
			#  ORDER 1 - pfSense (p/m) / pfBlockerNG (p/m) / pfBlockerNG Block/Reject	#
			#  ORDER 2 - pfBlockerNG (p/m) / pfSense (p/m) / pfBlockerNG Block/Reject	#
			#  ORDER 3 - pfBlockerNG (p/m) / pfBlockerNG Block/Reject / pfSense (p/m)	#
			#################################################################################

			if ($pfb['float'] == "") {
				if (!empty($fother_rules)) {
					foreach ($fother_rules as $cb_rules) {
						$new_rules[] = $cb_rules;
					}
				}
			}
			if (!empty($fpermit_rules) && $pfb['order'] == "order_1") {
				foreach ($fpermit_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if (!empty($fmatch_rules) && $pfb['order'] == "order_1") {
				foreach ($fmatch_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}

			// Define Inbound Interface Rules
			if (!empty($pfb['inbound_interfaces'])) {
				$counter = 0;
				foreach ($pfb['inbound_interfaces'] as $inbound_interface) {
					if (!empty($permit_rules) && $pfb['order'] == "order_1") {
						foreach ($permit_rules as $cb_rules) {
							if ($cb_rules['interface'] == $inbound_interface)
								$new_rules[] = $cb_rules;
						}
					}
					if (!empty($match_rules) && $pfb['order'] == "order_1") {
						foreach ($match_rules as $cb_rules) {
							if ($cb_rules['interface'] == $inbound_interface)
								$new_rules[] = $cb_rules;
						}
					}
					// Match Inbound Rules defined as Floating Only.
					if (!empty($match_inbound) && $counter == 0) {
						foreach ($match_inbound as $cb_rules) {
							$cb_rules['interface'] = $pfb['inbound_floating'];
							$new_rules[] = $cb_rules;
							$counter ++;
						}
					}
					if (!empty($permit_inbound)) {
						foreach ($permit_inbound as $cb_rules) {
							$cb_rules['interface'] = $inbound_interface;
							$new_rules[] = $cb_rules;
						}
					}
					if (!empty($fpermit_rules) && $pfb['order'] == "order_2") {
						foreach ($fpermit_rules as $cb_rules) {
							$new_rules[] = $cb_rules;
						}
					}
					if (!empty($fmatch_rules) && $pfb['order'] == "order_2") {
						foreach ($fmatch_rules as $cb_rules) {
							$new_rules[] = $cb_rules;
						}
					}
					if (!empty($permit_rules) && $pfb['order'] == "order_2") {
						foreach ($permit_rules as $cb_rules) {
							if ($cb_rules['interface'] == $inbound_interface)
								$new_rules[] = $cb_rules;
						}
					}
					if (!empty($match_rules) && $pfb['order'] == "order_2") {
						foreach ($match_rules as $cb_rules) {
							if ($cb_rules['interface'] == $inbound_interface)
								$new_rules[] = $cb_rules;
						}
					}
					if (!empty($deny_inbound)) {
						foreach ($deny_inbound as $cb_rules) {
							$cb_rules['interface'] = $inbound_interface;
							$new_rules[] = $cb_rules;
						}
					}
				}
			}

			// Define Outbound Interface Rules
			if (!empty($pfb['outbound_interfaces'])) {
				$counter = 0;
				foreach ($pfb['outbound_interfaces'] as $outbound_interface) {
					if (!empty($permit_rules) && $pfb['order'] == "order_1") {
						foreach ($permit_rules as $cb_rules) {
							if ($cb_rules['interface'] == $outbound_interface)
								$new_rules[] = $cb_rules;
						}
					}
					if (!empty($match_rules) && $pfb['order'] == "order_1") {
						foreach ($match_rules as $cb_rules) {
							if ($cb_rules['interface'] == $outbound_interface)
								$new_rules[] = $cb_rules;
						}
					}
					// Match Outbound Rules defined as Floating Only.
					if (!empty($match_outbound) && $counter == 0) {
						foreach ($match_outbound as $cb_rules) {
							$cb_rules['interface'] = $pfb['outbound_floating'];
							$new_rules[] = $cb_rules;
							$counter++;
						}
					}
					if (!empty($permit_outbound)) {
						foreach ($permit_outbound as $cb_rules) {
							$cb_rules['interface'] = $outbound_interface;
							$new_rules[] = $cb_rules;
						}
					}
					if (!empty($permit_rules) && $pfb['order'] == "order_2") {
						foreach ($permit_rules as $cb_rules) {
							if ($cb_rules['interface'] == $outbound_interface)
								$new_rules[] = $cb_rules;
						}
					}
					if (!empty($match_rules) && $pfb['order'] == "order_2") {
						foreach ($match_rules as $cb_rules) {
							if ($cb_rules['interface'] == $outbound_interface)
								$new_rules[] = $cb_rules;
						}
					}
					if (!empty($deny_outbound)) {
						foreach ($deny_outbound as $cb_rules) {
							$cb_rules['interface'] = $outbound_interface;
							$new_rules[] = $cb_rules;
						}
					}
				}
			}

			if (!empty($fpermit_rules) && $pfb['order'] == "order_0") {
				foreach ($fpermit_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if (!empty($fmatch_rules) && $pfb['order'] == "order_0") {
				foreach ($fmatch_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if (!empty($fpermit_rules) && $pfb['order'] == "order_3") {
				foreach ($fpermit_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if (!empty($fmatch_rules) && $pfb['order'] == "order_3") {
				foreach ($fmatch_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if (!empty($permit_rules) && $pfb['order'] == "order_3") {
				foreach ($permit_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if (!empty($match_rules) && $pfb['order'] == "order_3") {
				foreach ($match_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if ($pfb['float'] == "on") {
				if (!empty($fother_rules)) {
					foreach ($fother_rules as $cb_rules) {
						$new_rules[] = $cb_rules;
					}
				}
			}
			if (!empty($other_rules)) {
				foreach ($other_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}

			// Save New Rule Order to Config
			$config['filter']['rule'] = $new_rules;
		}
		if (!empty($message)) {
			$log = "\n {$message}\n";
			pfb_logger("{$log}","1");
		}

		// UNSET arrays 
		unset ($cb_rules,$permit_inbound,$permit_outbound,$deny_inbound,$deny_outbound,$match_inbound,$match_outbound);
		unset ($other_rules,$fother_rules,$permit_rules,$fpermit_rules,$match_rules,$fmatch_rules);
	}

	// Set flag to Update config file.
	if ($pfb['autorules'] && $rules != $new_rules) {
		$pfb['cron_mod'] = TRUE;
	}

	#################################
	#	pfSense Integration	#
	#################################

	// If 'Rule Changes' are found, utilize the 'filter_configure()' function, if not, utilize 'pfctl replace' command
	if ($pfb['autorules'] && $rules != $new_rules || $pfb['enable'] == "" || $pfb['remove']) {
		require_once("filter.inc");

		if (!$pfb['save']) {
			$log = "\n===[  Aliastables / Rules  ]================================\n\n";
			pfb_logger("{$log}","1");

			$log = "Firewall Rule Changes Found, Applying Filter Reload\n";
			pfb_logger("{$log}","1");
		}

		// Remove all pfBlockerNG Alias tables
		if (!empty($aliases_list)) {
			foreach ($aliases_list as $table) {
				exec ("/sbin/pfctl -t " . escapeshellarg($table) . " -T kill 2>&1", $pfb_null);
			}
		}

		// load filter file which will create the pfctl tables
		filter_configure();

		// Call function for NanoBSD/Ramdisk processes.
		pfb_aliastables("update");
	} else {
		// Don't Execute on User 'Save'
		if (!$pfb['save']) {

			$log = "\n\n===[  Aliastables / Rules  ]================================\n\n";
			pfb_logger("{$log}","1");

			$log = "No Changes to Firewall Rules, Skipping Filter Reload\n";
			pfb_logger("{$log}","1");

			// Re-Save Only Aliases that have been updated only.
			// When 'Reputation' is used, all Aliases Need to be Updated.
			$final_alias = array();
			if ($pfb['dedup'] == "on" || $pfb['pdup'] == "on") {
				if (!empty($pfb_alias_lists_all)) {
					$final_alias = array_unique($pfb_alias_lists_all);
				}
			} else {
				if (!empty($pfb_alias_lists)) {
					$final_alias = array_unique($pfb_alias_lists);
				}
			}

			if (!empty($final_alias)) {
				foreach ($final_alias as $final) {
					$log = "\n Updating: {$final}\n";
					pfb_logger("{$log}","1");
					$result_pfctl = "";
					if (file_exists("{$pfb['aliasdir']}/{$final}.txt")) {
						exec ("/sbin/pfctl -t " . escapeshellarg($final) . " -T replace -f " . $pfb['aliasdir'] . "/" . escapeshellarg($final) . ".txt 2>&1", $result_pfctl);
						$log = implode($result_pfctl);
					}
					else {
						$log = "Aliastable file not found\n";
					}
					pfb_logger("{$log}","1");
				}
				pfb_logger("\n","1");

				// Call function for NanoBSD/Ramdisk processes.
				pfb_aliastables("update");
			} else {
				$log = "No Changes to Aliases, Skipping pfctl Update\n";
				pfb_logger("{$log}","1");
			}
		}
	}
	// UNSET Variables
	unset($rules, $new_rules);

	// sync config
	pfblockerng_sync_on_changes();


	#################################
	#	FINAL REPORTING		#
	#################################

	// Only run with CRON or Force Invoked Process
	if ((!$pfb['save'] && $pfb['dupcheck'] && $pfb['enable'] == "on") || $pfb['summary']) {
		// Script to run Final Script Processes.
		exec ("{$pfb['script']} closing {$pfb['dup']} >> {$pfb['log']} 2>&1");
	}

	if ($pfb['enable'] == "on" && !$pfb['save'] || $pfb['summary']) {
		$log = "\n UPDATE PROCESS ENDED [ NOW ]\n";
		pfb_logger("{$log}","1");
	}


	#########################################
	#	Define/Apply CRON Jobs		#
	#########################################

	// Replace Cron job with any User Changes to $pfb_min
	if ($pfb['enable'] == "on") {
		// Define pfBlockerNG CRON Job
		$pfb_cmd	= "/usr/local/bin/php-cgi /usr/local/www/pfblockerng/pfblockerng.php cron >> {$pfb['log']} 2>&1";
		// $pfb['min'] ( User Defined Variable. Variable defined at start of Script )

		// Define Cron hour (Cron Interval & Start Hour)
		if ($pfb['interval'] == 1) {
			$pfb_hour = "*";
		} elseif ($pfb['interval'] == 24) {
			$pfb_hour = $pfb['24hour'];
		} else {
			$pfb_hour = implode(",", pfb_cron_base_hour());
		}

		$pfb_mday	= "*";
		$pfb_month	= "*";
		$pfb_wday	= "*";
		$pfb_who	= "root";

		// Determine if Cron Task requires updating
		if (!pfblockerng_cron_exists($pfb_cmd, $pfb['min'], $pfb_hour)) {
			install_cron_job($pfb_cmd, true, $pfb['min'], $pfb_hour, $pfb_mday, $pfb_month, $pfb_wday, $pfb_who);
		}
	}
	else {
		// Clear any existing pfBlockerNG Cron Jobs
		install_cron_job("pfblockerng.php cron", false);
	}

	if ($pfb['enable'] == "on") {
		// Define pfBlockerNG MaxMind CRON Job
		$pfb_gcmd = "/usr/local/bin/php-cgi /usr/local/www/pfblockerng/pfblockerng.php dc >> {$pfb['geolog']} 2>&1";
		// MaxMind GeoIP Cron Hour is randomized between 0-23 Hour to minimize effect on MaxMind Website
		$pfb_gmin	= "0";
		$pfb_ghour	= rand(0,23);
		$pfb_gmday	= "1,2,3,4,5,6,7";
		$pfb_gmonth	= "*";
		$pfb_gwday	= "2";
		$pfb_gwho	= "root";

		// Determine if Cron Task requires updating
		if (!pfblockerng_cron_exists($pfb_gcmd, $pfb_gmin, 'maxmind')) {
			install_cron_job($pfb_gcmd, true, $pfb_gmin, $pfb_ghour, $pfb_gmday, $pfb_gmonth, $pfb_gwday, $pfb_gwho);
		}
	}
	else {
		// Clear any existing pfBlockerNG Cron Jobs
		install_cron_job("pfblockerng.php dc", false);
	}


	#################################
	#	Closing Processes	#
	#################################

	// uncheck Reusing Existing Downloads Check box
	if (!$pfb['save'] && $pfb['enable'] == "on" && $pfb['reuse'] == "on") {
		$config['installedpackages']['pfblockerng']['config'][0]['pfb_reuse'] = "";
		$pfb['cron_mod'] = TRUE;
	}

	// Only save config.xml changes if changes are found.
	// Temporay to ensure all conditions are defined before fully enabling this feature
	if ($pfb['cron_mod'] || !$pfb['cron_mod']) {
		write_config("pfBlockerNG: Save settings");
	}
}


function pfblockerng_validate_input($post, &$input_errors) {
	global $config;

	foreach ($post as $key => $value) {

		if (substr($key, 0, 3) == "url" && is_numeric( substr($key, 3, (strlen($key) - 3))) ) {
			if (empty($value)) {
				$input_url_empty = TRUE;
				continue;
			}
			if (substr($value, 0, 1) == ' ') {
				$input_errors[] = "Leading whitespace not allowed in URL field";
			}
		}

		if (substr($key, 0, 6) == "header" && is_numeric( substr($key, 6, (strlen($key) - 6))) ) {
			if ($input_url_empty && empty($value)) {
				$input_url_empty = FALSE;
				continue;
			}
			if ($input_url_empty && !empty($value)) {
				$input_errors[] = "No URL Defined.";
			}
			if (substr($value, 0, 1) == ' ' || empty($value)) {
				$input_errors[] = "Header field must be defined.";
			}
		}
	}
}

function pfblockerng_php_deinstall_command() {
	require_once("config.inc");
	global $config,$pfb;

	// Set these two variables to Disable pfBlockerNG on De-Install
	$pfb['save'] = TRUE;
	$pfb['install'] = TRUE;
	sync_package_pfblockerng();
	rmdir_recursive("/usr/local/pkg/pfblockerng");
	rmdir_recursive("/usr/local/www/pfblockerng");

	// Maintain pfBlockerNG Settings and Database Files if $pfb['keep'] is ON.
	if ($pfb['keep'] != "on") {
		// Remove pfBlockerNG Log and DB Folder
		rmdir_recursive("{$pfb['dbdir']}");
		rmdir_recursive("{$pfb['logdir']}");

		// Remove Aliastables archive and earlyshellcmd if found.
		@unlink_if_exists("{$pfb['aliasarchive']}");
		if (is_array($config['system']['earlyshellcmd'])) {
			$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
			if (preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd)) {
				$a_earlyshellcmd = preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd, PREG_GREP_INVERT);
			}
		}

		// Remove Settings from Config
		if (is_array($config['installedpackages']['pfblockerng']))
			unset($config['installedpackages']['pfblockerng']);
		if (is_array($config['installedpackages']['pfblockerngglobal']))
			unset($config['installedpackages']['pfblockerngglobal']);
		if (is_array($config['installedpackages']['pfblockerngsync']))
			unset($config['installedpackages']['pfblockerngsync']);
		if (is_array($config['installedpackages']['pfblockerngreputation']))
			unset($config['installedpackages']['pfblockerngreputation']);
		if (is_array($config['installedpackages']['pfblockernglistsv4']))
			unset($config['installedpackages']['pfblockernglistsv4']);
		if (is_array($config['installedpackages']['pfblockernglistsv6']))
			unset($config['installedpackages']['pfblockernglistsv6']);
		if (is_array($config['installedpackages']['pfblockerngafrica']))
			unset($config['installedpackages']['pfblockerngafrica']);
		if (is_array($config['installedpackages']['pfblockerngantartica']))
			unset($config['installedpackages']['pfblockerngantartica']);
		if (is_array($config['installedpackages']['pfblockerngasia']))
			unset($config['installedpackages']['pfblockerngasia']);
		if (is_array($config['installedpackages']['pfblockerngeurope']))
			unset($config['installedpackages']['pfblockerngeurope']);
		if (is_array($config['installedpackages']['pfblockerngnorthamerica']))
			unset($config['installedpackages']['pfblockerngnorthamerica']);
		if (is_array($config['installedpackages']['pfblockerngoceania']))
			unset($config['installedpackages']['pfblockerngoceania']);
		if (is_array($config['installedpackages']['pfblockerngsouthamerica']))
			unset($config['installedpackages']['pfblockerngsouthamerica']);
		if (is_array($config['installedpackages']['pfblockerngtopspammers']))
			unset($config['installedpackages']['pfblockerngtopspammers']);
		if (is_array($config['installedpackages']['pfblockerngproxyandsatellite']))
			unset($config['installedpackages']['pfblockerngproxyandsatellite']);
	}

	// Remove Widget (code from Snort deinstall)
	$pfb['widgets'] = $config['widgets']['sequence'];
	if (!empty($pfb['widgets'])) {
		$widgetlist = explode(",", $pfb['widgets']);
		foreach ($widgetlist as $key => $widget) {
			if (strstr($widget, "pfblockerng-container")) {
				unset($widgetlist[$key]);
			}
		}
		$config['widgets']['sequence'] = implode(",", $widgetlist);
	}
	update_output_window(gettext("pfBlockerNG has been Uninstalled"));
}

/* Uses XMLRPC to synchronize the changes to a remote node */
function pfblockerng_sync_on_changes() {
	global $config, $g, $pfb_sync;

	// Create Array of Sync Settings and exit if Sync is Disabled.
	if (is_array($config['installedpackages']['pfblockerngsync']['config'][0])) {
		$pfb_sync = $config['installedpackages']['pfblockerngsync']['config'][0];
		if ($pfb_sync['varsynconchanges'] == "disabled" || $pfb_sync['varsynconchanges'] == "") {
			return;
		}
		$synctimeout = $pfb_sync['varsynctimeout'];
	} else {
		return;
	}

	log_error("[pfBlockerNG] XMLRPC sync is starting.");

	if (is_array($config['installedpackages']['pfblockerngsync']['config'])) {
		switch ($pfb_sync['varsynconchanges']) {
			case "manual":
				if (is_array($pfb_sync['row'])) {
					$rs = $pfb_sync['row'];
				} else {
					log_error("[pfBlockerNG] Manual XMLRPC sync is enabled but there are no replication targets configured.");
					return;
				}
				break;
			case "auto":
				if (is_array($config['installedpackages']['carpsettings']) && is_array($config['installedpackages']['carpsettings']['config'])) {
					$system_carp = $config['installedpackages']['carpsettings']['config'][0];
					$rs[0]['varsyncipaddress'] = $system_carp['synchronizetoip'];
					$rs[0]['varsyncusername'] = $system_carp['username'];
					$rs[0]['varsyncpassword'] = $system_carp['password'];

					// XMLRPC sync is currently only supported over connections using the same protocol and port as this system
					if ($config['system']['webgui']['protocol'] == "http") {
						$rs[0]['varsyncprotocol'] = "http";
					} else {
						$rs[0]['varsyncprotocol'] = "https";
					}

					if ($system_carp['synchronizetoip'] == "") {
						log_error("[pfBlockerNG] XMLRPC sync is enabled but there is no sync IP address configured.");
						return;
					}
				} else {
					log_error("[pfBlockerNG] Auto XMLRPC sync is enabled but there are no replication targets configured.");
					return;
				}
				break;
			default:
				return;
				break;
		}
		if (is_array($rs)) {
			foreach ($rs as $sh) {
				// Only Sync Enabled Replication Targets
				if ($sh['varsyncdestinenable'] == "ON") {
					$sync_to_ip = $sh['varsyncipaddress'];
					$port = $sh['varsyncport'];
					$password = htmlspecialchars($sh['varsyncpassword']);
					$protocol = $sh['varsyncprotocol'];

					if (!empty($sh['varsyncusername'])) {
						$username = $sh['varsyncusername'];
					} else {
						$username = "admin";
					}

					pfblockerng_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout);
				}
			}
			if ($success) {
				log_error("[pfBlockerNG] XMLRPC sync completed successfully.");
			}
		}
	}
}


/* Do the actual XMLRPC sync */
function pfblockerng_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout) {
	global $config, $g, $pfb_sync;
	$success = TRUE;

	/* Exit on missing parameters */
	if (empty($sync_to_ip) || empty($password)) {
		log_error("[pfBlockerNG] XMLRPC sync parameter missing (host IP or password) ... aborting xmlrpc sync");
		$success = FALSE;
		return $success;
	}

	/* Do not attempt a package sync while booting up or installing package */
	if ($g['booting'] || $g['pfblockerng_postinstall']) {
		log_error("[pfBlockerNG] XMLRPC sync to Replication targets terminated during boot up or during package reinstallation.");
		$success = FALSE;
		return $success;
	}

	// Validate Replication Target IP Address and Port Settings
	if (!is_ipaddr($sync_to_ip) || !is_port($port)) {
		log_error("[pfBlockerNG] XMLRPC sync terminated due to mis-configured Replication Target IP Address or Port settings.");
		$success = FALSE;
		return $success;
	}

	/* Test key variables and set defaults if empty */
	if (empty($synctimeout)) {
		$synctimeout = 150;
	}
	$url = "{$protocol}://{$sync_to_ip}";

	if ($port == "") { $port = $config['system']['webgui']['port']; };
	/* If port is empty lets rely on the protocol selection */
	if ($port == "") {
		if ($config['system']['webgui']['protocol'] == "http") {
			$port = "80";
		} else {
			$port = "443";
		}
	}
	/* xml will hold the sections to sync */
	$xml = array();
	// If User Disabled, remove 'General Tab Customizations' from Sync
	if ($config['installedpackages']['pfblockerngsync']['config'][0]['syncinterfaces'] == "") {
		if (is_array($config['installedpackages']['pfblockerng']))
			$xml['pfblockerng']		= $config['installedpackages']['pfblockerng'];
	}
	if (is_array($config['installedpackages']['pfblockerngreputation']))
		$xml['pfblockerngreputation']		= $config['installedpackages']['pfblockerngreputation'];
	if (is_array($config['installedpackages']['pfblockernglistsv4']))
		$xml['pfblockernglistsv4']		= $config['installedpackages']['pfblockernglistsv4'];
	if (is_array($config['installedpackages']['pfblockernglistsv6']))
		$xml['pfblockernglistsv6']		= $config['installedpackages']['pfblockernglistsv6'];
	if (is_array($config['installedpackages']['pfblockerngtopspammers']))
		$xml['pfblockerngtopspammers']		= $config['installedpackages']['pfblockerngtopspammers'];
	if (is_array($config['installedpackages']['pfblockerngafrica']))
		$xml['pfblockerngafrica']		= $config['installedpackages']['pfblockerngafrica'];
	if (is_array($config['installedpackages']['pfblockerngantartica']))
		$xml['pfblockerngantartica']		= $config['installedpackages']['pfblockerngantartica'];
	if (is_array($config['installedpackages']['pfblockerngasia']))
		$xml['pfblockerngasia']			= $config['installedpackages']['pfblockerngasia'];
	if (is_array($config['installedpackages']['pfblockerngeurope']))
		$xml['pfblockerngeurope']		= $config['installedpackages']['pfblockerngeurope'];
	if (is_array($config['installedpackages']['pfblockerngnorthamerica']))
		$xml['pfblockerngnorthamerica']		= $config['installedpackages']['pfblockerngnorthamerica'];
	if (is_array($config['installedpackages']['pfblockerngoceania']))
		$xml['pfblockerngoceania']		= $config['installedpackages']['pfblockerngoceania'];
	if (is_array($config['installedpackages']['pfblockerngsouthamerica']))
		$xml['pfblockerngsouthamerica']		= $config['installedpackages']['pfblockerngsouthamerica'];
	if (is_array($config['installedpackages']['pfblockerngproxyandsatellite']))
		$xml['pfblockerngproxyandsatellite']	= $config['installedpackages']['pfblockerngproxyandsatellite'];

	/* assemble xmlrpc payload */
	$params = array(XML_RPC_encode($password), XML_RPC_encode($xml));

	/* set a few variables needed for sync code borrowed from filter.inc */
	log_error("[pfBlockerNG] XMLRPC syncing to {$url}:{$port}.");
	$method = 'pfsense.merge_installedpackages_section_xmlrpc';
	$msg = new XML_RPC_Message($method, $params);
	$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
	$cli->setCredentials($username, $password);
	if ($g['debug']) {
		$cli->setDebug(1);
	}

	/* send our XMLRPC message and timeout after defined sync timeout value */
	$resp = $cli->send($msg, $synctimeout);
	$error = "";
	if (!$resp) {
		log_error("[pfBlockerNG] XMLRPC communications error occurred while attempting sync with {$url}:{$port}.");
		file_notice("sync_settings", $error, "pfBlockerNG Settings Sync", "");
		$success = FALSE;
		return $success;
	} elseif ($resp->faultCode()) {
		$cli->setDebug(1);
		$resp = $cli->send($msg, $synctimeout);
		log_error("[pfBlockerNG] XMLRPC Error received while attempting sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString());
		file_notice("sync_settings", $error, "pfBlockerNG Settings Sync", "");
		$success = FALSE;
		return $success;
	} else {
		log_error("[pfBlockerNG] XMLRPC sync successfully completed with {$url}:{$port}.");
	}
	return $success;
}
?>
