<?php
/*
 * lcdproc_client.php
 *
 * part of pfSense (https://www.pfsense.org/)
 * Copyright (c) 2016-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016 Treer
 * Copyright (c) 2011 Michele Di Maria
 * Copyright (c) 2007-2009 Seth Mos <seth.mos@dds.nl>
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
require_once("config.inc");
require_once("functions.inc");
require_once("interfaces.inc");
require_once("/usr/local/pkg/lcdproc.inc");
require_once("system.inc");
require_once("includes/functions.inc.php");
if (file_exists("/usr/local/pkg/nut/nut.inc")) {
	require_once("/usr/local/pkg/nut/nut.inc");
}

/* Calculates non-idle CPU time and returns as a percentage */
function lcdproc_get_cpu_stats() {
	$duration = 250000;
	$diff = array('user', 'nice', 'sys', 'intr', 'idle');
	$cpuTicks = array_combine($diff, explode(" ", shell_exec('/sbin/sysctl -n kern.cp_time')));
	usleep($duration);
	$cpuTicks2 = array_combine($diff, explode(" ", shell_exec('/sbin/sysctl -n kern.cp_time')));

	$totalStart = array_sum($cpuTicks);
	$totalEnd = array_sum($cpuTicks2);

	// Something wrapped ?!?!
	if ($totalEnd <= $totalStart) {
		return 0;
	}

	// Calculate total cycles used
	$totalUsed = ($totalEnd - $totalStart) - ($cpuTicks2['idle'] - $cpuTicks['idle']);

	// Calculate the percentage used
	$cpuUsage = floor(100 * ($totalUsed / ($totalEnd - $totalStart)));

	return $cpuUsage;
}

function lcdproc_get_uptime_stats() {
	exec("/usr/bin/uptime", $output, $ret);
	$temp = explode(",", $output[0]);
	if (stristr($output[0], "day")) {
		$status = "$temp[0] $temp[1]";
	} else {
		$status = "$temp[0] ";
	}
	$status = trim(str_replace("  ", " ", $status));
	$status = substr($status, strpos($status, "up ") + 3);

	return($status);
}

// Returns CPU temperature if available from the system 
function lcdproc_get_cpu_temperature() {
	global $config;
	$lcdproc_screens_config = $config['installedpackages']['lcdprocscreens']['config'][0];
	$unit = $lcdproc_screens_config['scr_cputemperature_unit'];

	$temp_out = "";
	$temp_out = get_temp(); // Use function from includes/functions.inc.php
	if ($temp_out !== "") {
		switch ($unit) {
			case "c":
				return $temp_out . "C";
				break;
			case "f":
				$cputemperature = ($temp_out * 1.8) + 32;
				return $cputemperature . "F";
				break;
			default:
				break;
		}
	} else {
		// sysctl probably returned "unknown oid" 
		return 'CPU Temp N/A';
	}
}

// NUT UPS Status
function lcdproc_get_nut_ups_status() {
	if (function_exists('nut_ups_status')) {
		$status = nut_ups_status();
		return $status;
	} else {
		return false;
	}
}

function lcdproc_get_version() {
	global $g;
	$version = @file_get_contents("/etc/version");
	$version = trim($version);
	return("{$g['product_name']} {$version}");
}

// Returns the max frequency in Mhz, or false if powerd is not supported.
// powerd is not supported on all systems - "no cpufreq(4) support" https://redmine.pfsense.org/issues/5739
function lcdproc_get_cpu_maxfrequency() {
	$execRet = 0;
	exec("/sbin/sysctl -n dev.cpu.0.freq_levels", $cpufreqs, $execRet);
	if ($execRet === 0) {
		$cpufreqs = explode(" ", trim($cpufreqs[0]));
		$maxfreqs = explode("/", $cpufreqs[0]);
		return $maxfreqs[0];
	} else {
		// sysctrl probably returned "unknown oid 'dev.cpu.0.freq_levels'", 
		// see https://redmine.pfsense.org/issues/5739 
		return false;
	}
}

// Returns the current frequency in Mhz, or false if powerd is not supported.
// powerd is not supported on all systems - "no cpufreq(4) support" https://redmine.pfsense.org/issues/5739
function lcdproc_get_cpu_currentfrequency() {
	$execRet = 0;
	exec("/sbin/sysctl -n dev.cpu.0.freq", $curfreq, $execRet);
	if ($execRet === 0) {
		return trim($curfreq[0]);
	} else {
		// sysctrl probably returned "unknown oid 'dev.cpu.0.freq'", 
		// see https://redmine.pfsense.org/issues/5739 
		return false;
	}
}

function lcdproc_get_cpufrequency() {
	$maxfreq = lcdproc_get_cpu_maxfrequency();
	if ($maxfreq === false) {
		return "no cpufreq(4) support";
	} else {
		$curfreq = lcdproc_get_cpu_currentfrequency();
		return "$curfreq\/$maxfreq Mhz";
	}
}

function lcdproc_get_interfaces_stats() {
	global $g;
	global $config;
	$ifstatus = array();
	$i = 0;
	$ifdescrs = array('wan' => 'WAN', 'lan' => 'LAN');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
	}
	foreach ($ifdescrs as $ifdescr => $ifname) {
		$ifinfo = get_interface_info($ifdescr);
		if ($ifinfo['status'] == "up") {
			$online = "Up";
		} else {
			$online = "Down";
		}
		if (!empty($ifinfo['ipaddr'])) {
			$ip = htmlspecialchars($ifinfo['ipaddr']);
		} else {
			$ip = "-";
		}
		$ifstatus[] = htmlspecialchars($ifname) ." [$online]";
	}
	$status = " ". implode(", ", $ifstatus);
	return($status);
}

function lcdproc_get_carp_stats() {
	global $g;
	global $config;

	if (is_array($config['virtualip']['vip'])) {
		$carpint = 0;
		$initcount = 0;
		$mastercount = 0;
		$backupcount = 0;
		foreach ($config['virtualip']['vip'] as $carp) {
			if ($carp['mode'] != "carp") {
				continue;
			}
			$ipaddress = $carp['subnet'];
			$password = $carp['password'];
			$netmask = $carp['subnet_bits'];
			$vhid = $carp['vhid'];
			$advskew = $carp['advskew'];
			$status = get_carp_interface_status("_vip{$carp['uniqid']}");
			switch ($status) {
				case "MASTER":
					$mastercount++;
					break;
				case "BACKUP":
					$backupcount++;
					break;
				case "INIT":
					$initcount++;
					break;
			}
		}
		$status = "M/B/I {$mastercount}/{$backupcount}/{$initcount}";
	} else {
		$status = "CARP Disabled";
	}
	return($status);
}

function lcdproc_get_ipsec_tunnel_sad() {
	/* query SAD */
	if (file_exists("/usr/local/sbin/setkey")) {
		$fd = @popen("/usr/local/sbin/setkey -D", "r");
	} else {
		$fd = @popen("/sbin/setkey -D", "r");
	}
	$sad = array();
	if ($fd) {
		while (!feof($fd)) {
			$line = chop(fgets($fd));
			if (!$line) {
				continue;
			}
			if ($line == "No SAD entries.") {
				break;
			}
			if ($line[0] != "\t") {
				if (is_array($cursa)) {
					$sad[] = $cursa;
					$cursa = array();
				}
				list($cursa['src'],$cursa['dst']) = explode(" ", $line);
				$i = 0;
			} else {
				$linea = explode(" ", trim($line));
				if ($i == 1) {
					$cursa['proto'] = $linea[0];
					$cursa['spi'] = substr($linea[2], strpos($linea[2], "x")+1, -1);
				} else if ($i == 2) {
					$cursa['ealgo'] = $linea[1];
				} else if ($i == 3) {
					$cursa['aalgo'] = $linea[1];
				}
			}
			$i++;
		}
		if (is_array($cursa) && count($cursa)) {
			$sad[] = $cursa;
		}
		pclose($fd);
	}
	return($sad);
}

function lcdproc_get_ipsec_tunnel_src($tunnel) {
	global $g, $config, $sad;
	$if = "WAN";
	if ($tunnel['interface']) {
		$if = $tunnel['interface'];
		$realinterface = convert_friendly_interface_to_real_interface_name($if);
		$interfaceip = find_interface_ip($realinterface);
	}
	return $interfaceip;
}

function lcdproc_output_ipsec_tunnel_status($tunnel) {
	global $g, $config, $sad;
	$if = "WAN";
	$interfaceip = lcdproc_get_ipsec_tunnel_src($tunnel);
	$foundsrc = false;
	$founddst = false;

	if (!is_array($sad)) {
		/* we have no sad array, bail */
		return(false);
	}
	foreach ($sad as $sa) {
		if ($sa['src'] == $interfaceip) {
			$foundsrc = true;
		}
		if ($sa['dst'] == $tunnel['remote-gateway']) {
			$founddst = true;
		}
	}
	if ($foundsrc && $founddst) {
		/* tunnel is up */
		$iconfn = "pass";
		return(true);
	} else {
		/* tunnel is down */
		$iconfn = "reject";
		return(false);
	}
}

function lcdproc_get_ipsec_stats() {
	global $g, $config, $sad;
	$sad = array();
	$sad = lcdproc_get_ipsec_tunnel_sad();

	$activecounter = 0;
	$inactivecounter = 0;

	if ($config['ipsec']['tunnel']) {
		foreach ($config['ipsec']['tunnel'] as $tunnel) {
			$ipsecstatus = false;

			$tun_disabled = "false";
			$foundsrc = false;
			$founddst = false;

			if (isset($tunnel['disabled'])) {
				$tun_disabled = "true";
				continue;
			}

			if (lcdproc_output_ipsec_tunnel_status($tunnel)) {
				/* tunnel is up */
				$iconfn = "true";
				$activecounter++;
			} else {
				/* tunnel is down */
				$iconfn = "false";
				$inactivecounter++;
			}
		}
	}

	if (is_array($config['ipsec']['tunnel'])) {
		$status = "Up/Down $activecounter/$inactivecounter";
	} else {
		$status = "IPSEC Disabled";
	}
	return($status);
}

function lcdproc_send_lcd_commands($lcd, $lcd_cmds) {
	if (!is_array($lcd_cmds) || (empty($lcd_cmds))) {
		lcdproc_warn("Failed to interpret lcd commands");
		return;
	}
	lcdproc_get_lcd_messages($lcd);
	foreach ($lcd_cmds as $lcd_cmd) {
		if (! fwrite($lcd, "$lcd_cmd\n")) {
			lcdproc_warn("Connection to LCDd process lost $errstr ($errno)");
			$lcdproc_connect_errors++;
			return false;
		}
	}
	return true;
}

function lcdproc_get_lcd_messages($lcd) {
	while (($cmd_output = fgets($lcd, 8000)) !== false) {
		if (preg_match("/^huh?/", $cmd_output)) {
			lcdproc_notice("LCDd output: \"$cmd_output\". Executed \"$lcd_cmd\"");
		}
		if (lcdproc_cmenu_enabled()) {
			if (preg_match("/^menuevent select r_ask_yes/", $cmd_output)) {
				lcdproc_notice("init REBOOT!");
				system_reboot();
			}
			if (preg_match("/^menuevent select s_ask_yes/", $cmd_output)) {
				lcdproc_notice("init SHUTDOWN!");
				system_halt();
			}
		}
	}
}

function lcdproc_get_lcdpanel_width() {
	global $config;
	$lcdproc_size_config = $config['installedpackages']['lcdproc']['config'][0];
	if (is_null($lcdproc_size_config['size'])) {
		return "16";
	} else {
		$dimensions = explode("x", $lcdproc_size_config['size']);
		return $dimensions[0];
	}
}

function lcdproc_get_lcdpanel_height() {
	global $config;
	$lcdproc_size_config = $config['installedpackages']['lcdproc']['config'][0];
	if (is_null($lcdproc_size_config['size'])) {
		return "2";
	} else {
		$dimensions = explode("x", $lcdproc_size_config['size']);
		return $dimensions[1];
	}
}

function lcdproc_get_lcdpanel_refresh_frequency() {
	global $config;
	$lcdproc_size_config = $config['installedpackages']['lcdproc']['config'][0];
	$value = $lcdproc_size_config['refresh_frequency'];
	if (is_null($value)) {
		return "5";
	} else {
		return $value;
	}
}

function lcdproc_outputled_enabled_CFontz633() {
	global $config;
	$lcdproc_config = $config['installedpackages']['lcdproc']['config'][0];
	$value = $lcdproc_config['outputleds'];
	if (is_null($value)) {
		return false;
	} else {
		if ($value && $lcdproc_config['driver'] == "CFontz633")	{
			return true;
		} else if ($value && $lcdproc_config['driver'] == "CFontzPacket") {
			return true;
		} else {
			return false;
		}
	}
}

function lcdproc_cmenu_enabled() {
	global $config;
	$lcdproc_config = $config['installedpackages']['lcdproc']['config'][0];
	if (!isset($lcdproc_config['controlmenu'])) {
		return false;
	}
	return true;
}

function lcdproc_outputled_carp() {
	/* Returns the status of CARP for the box.
	Assumes ALL CARP status are the same for all the interfaces.
		-1 = CARP Disabled
		0  = CARP on Backup
		1  = CARP on Master */
	global $g;
	global $config;

	if (is_array($config['virtualip']['vip'])) {
		$carpint = 0;
		foreach ($config['virtualip']['vip'] as $carp) {
			if ($carp['mode'] != "carp") {
				 continue;
			}
			$status = get_carp_interface_status("_vip{$carp['uniqid']}");
			
			switch($status) {
				case "MASTER":
					return 1;
					break;
				case "BACKUP":
					return 0;
					break;
			}
		}
	} else {
		return -1;
	}
}

function lcdproc_outputled_gateway() {
	/* Returns the status of the gateways.
		-1 = No gateway defined
		0  = At least 1 gateway down or with issues
		1  = All gateway up */
	global $g;
	global $config;
	$a_gateways = return_gateways_array();
	$gateways_status = array();
	$gateways_status = return_gateways_status(true);
	foreach ($a_gateways as $gname => $gateway) {
		if ($gateways_status[$gname]['status'] != "none") {
			return 0;
		}
	}
	return 1;
}

function lcdproc_build_interface_link_list() {
	// Returns a dictionary of all the interfaces along with their
	// link and address information, keyed on the interface description.
	global $config;

	$result = array();
	$ifList = get_configured_interface_with_descr();

	foreach($ifList as $ifdescr => $ifname) {

		// get the interface link infos
		$ifinfo = get_interface_info($ifdescr);

		$entry = array();
		$entry['name'] = $ifname;
		$entry['mac'] = $ifinfo['macaddr'];

		if (($ifinfo['status'] == "up") ||
		    ($ifinfo['status'] == "associated")) {

			$entry['status'] = "up";
		} else {
			$entry['status'] = "down";
		}

		if (($ifinfo['pppoelink'] == "up") ||
		    ($ifinfo['pptplink']  == "up") ||
		    ($ifinfo['l2tplink']  == "up")) {

			$entry['link'] = sprintf(gettext("Uptime %s"), $ifinfo['ppp_uptime']);
		} else {
			$entry['link'] = $ifinfo['media'];
		}

		$entry['v4addr'] = (empty($ifinfo['ipaddr'])) ?
			"n/a" : $ifinfo['ipaddr'];

		$entry['v6addr'] = (empty($ifinfo['ipaddrv6'])) ?
			"n/a" : $ifinfo['ipaddrv6'];

		$result[$ifdescr] = $entry;
	}
	return $result;
}

function lcdproc_build_interface_traffic_stats_list() {
	// Returns a dictionary of all the interfaces along with their in/out
	// traffic stats, keyed on the interface name.
	global $config;

	$result = array();
	$interfaceList = get_configured_interface_with_descr();

	foreach($interfaceList as $key => $description) {	
		
		// get the interface stats (code from ifstats.php)
		$interface      = $config['interfaces'][$key];
		$interfaceName  = $interface['if'];
		$interfaceStats = pfSense_get_interface_stats($interfaceName);

		lcdproc_calculate_interfaceBytesPerSecond_sinceLastChecked($interfaceName, $interfaceStats, $in_Bps, $out_Bps);
		lcdproc_calculate_bytesToday($interfaceName, $interfaceStats, $in_bytesToday, $out_bytesToday);

		$entry = array();
		$entry['descr']       = $description;

		$entry['in_Bps']      = $in_Bps;
		$entry['out_Bps']     = $out_Bps;
		$entry['total_Bps']   = $in_Bps + $out_Bps;

		$entry['in_bytes']    = $interfaceStats['inbytes'];
		$entry['out_bytes']   = $interfaceStats['outbytes'];
		$entry['total_bytes'] = $interfaceStats['inbytes'] + $interfaceStats['outbytes'];

		$entry['in_bytes_today']    = $in_bytesToday;
		$entry['out_bytes_today']   = $out_bytesToday;
		$entry['total_bytes_today'] = $in_bytesToday + $out_bytesToday;
		
		$result[$interface['if']] = $entry;
	}
	return $result;
}

function lcdproc_sort_interface_list_by_bytes_today(&$interfaceTrafficStatsList) {
	uasort($interfaceTrafficStatsList, "lcdproc_cmp_total_bytes_today");
}

function lcdproc_sort_interface_list_by_total_bytes(&$interfaceTrafficStatsList) {
	uasort($interfaceTrafficStatsList, "lcdproc_cmp_total_bytes");
}

function lcdproc_sort_interface_list_by_bps(&$interfaceTrafficStatsList) {
	uasort($interfaceTrafficStatsList, "lcdproc_cmp_total_Bps");
}

function lcdproc_cmp_total_Bps($a, $b)
{
	if ($a['total_Bps'] == $b['total_Bps']) return 0;
	return ($a['total_Bps'] < $b['total_Bps']) ? 1 : -1;
}

function lcdproc_cmp_total_bytes($a, $b)
{
	if ($a['total_bytes'] == $b['total_bytes']) return 0;
	return ($a['total_bytes'] < $b['total_bytes']) ? 1 : -1;
}

function lcdproc_cmp_total_bytes_today($a, $b)
{
	if ($a['total_bytes_today'] == $b['total_bytes_today']) return 0;
	return ($a['total_bytes_today'] < $b['total_bytes_today']) ? 1 : -1;
}

function lcdproc_calculate_interfaceBytesPerSecond_sinceLastChecked($interfaceName, $interfaceStats, &$in_Bps, &$out_Bps) {
	// calculates the average bytes-per-second (in & out) for the interface
	// during the interval between now and the last time this method was invoked for
	// the interface. So avoid invoking this method needlessly, or you'll end up
	// measuring meaningless periods.

	global $traffic_last_ugmt, $traffic_last_ifin, $traffic_last_ifout;

	// get the current time (code from ifstats.php)
	$temp = gettimeofday();
	$timing = (double)$temp["sec"] + (double)$temp["usec"] / 1000000.0;

	// calculate the traffic stats
	$deltatime = $timing - $traffic_last_ugmt[$interfaceName];
	$in_Bps  = ((double)$interfaceStats['inbytes']  - $traffic_last_ifin[$interfaceName])  / $deltatime;
	$out_Bps = ((double)$interfaceStats['outbytes'] - $traffic_last_ifout[$interfaceName]) / $deltatime;

	$traffic_last_ugmt[$interfaceName]  = $timing;
	$traffic_last_ifin[$interfaceName]  = (double)$interfaceStats['inbytes'];
	$traffic_last_ifout[$interfaceName] = (double)$interfaceStats['outbytes'];
}

function lcdproc_calculate_bytesToday($interfaceName, $interfaceStats, &$in_bytesToday, &$out_bytesToday) {

	global $traffic_last_hour, $traffic_startOfDay_ifin, $traffic_startOfDay_ifout;
	
	$hourOfDay = getdate()['hours'];
	
	if (!isset($traffic_last_hour[$interfaceName]) || ($hourOfDay < $traffic_last_hour[$interfaceName])) {
		$traffic_startOfDay_ifin[$interfaceName]  = (double)$interfaceStats['inbytes'];
		$traffic_startOfDay_ifout[$interfaceName] = (double)$interfaceStats['outbytes'];	
	}
	$traffic_last_hour[$interfaceName] = $hourOfDay;
	
	$in_bytesToday  = ((double)$interfaceStats['inbytes']  - $traffic_startOfDay_ifin[$interfaceName]);
	$out_bytesToday = ((double)$interfaceStats['outbytes'] - $traffic_startOfDay_ifout[$interfaceName]);
}


function lcdproc_format_interface_string($interfaceEntry, $in_key, $out_key, $output_in_bits, $outputLength) {

	if ($output_in_bits) {
		$speed = " " . lcdproc_format_toSpeedInBits_shortForm($interfaceEntry[$in_key]) . "/" . lcdproc_format_toSpeedInBits_shortForm($interfaceEntry[$out_key]);
	} else {
		$speed = " " . lcdproc_format_toSizeInBytes_shortForm($interfaceEntry[$in_key]) . "/" . lcdproc_format_toSizeInBytes_shortForm($interfaceEntry[$out_key]);
	}

	$nameLength = $outputLength - strlen($speed);

	if ($nameLength < 0) {
		// owch - speed doesn't even fit on the lcd
		$speed = substr(trim($speed), 0, $outputLength);
		$name = '';
	} else {
		$name = substr($interfaceEntry['descr'], 0, $nameLength);
		$name = str_pad($name, $nameLength);
	}

	return $name . $speed;
}

function lcdproc_format_toSizeInBytes_shortForm($size_in_bytes) {
	// format a byte count into a string with two significant figures or more and a unit
	//
	// Data sizes are normally specified in KB - powers of 1024, so return KB rather than kB

	if ($size_in_bytes < (1024 * 1024)) {
		$unit = "K";
		$unitSize = $size_in_bytes / 1024;
	} else if ($size_in_bytes < (1024 * 1024 * 1024)) {
		$unit = "M";
		$unitSize = $size_in_bytes / (1024 * 1024);
	} else {
		$unit = "G";
		$unitSize = $size_in_bytes / (1024 * 1024 * 1024);
	}

	$showDecimalPlace = $unitSize < 10 && round($unitSize, 1) != round($unitSize);

	return sprintf($showDecimalPlace ? "%1.1f" : "%1.0f", $unitSize) . $unit;
}

function lcdproc_format_toSpeedInBits_shortForm($speed_in_bytes) {
	// format a byte-count into a bit-count string with two significant figures or more, and a unit.
	//
	// The decimal SI kilobot definition of 1 kbit/s = 1000 bit/s, is used uniformly in the
	// context of telecommunication transmission, so return kb rather than Kb

	if ($speed_in_bytes < 125000) {
		$unit = "k";
		$unitSpeed = $speed_in_bytes / 125;
	} else if ($speed_in_bytes < 125000000) {
		$unit = "m";
		$unitSpeed = $speed_in_bytes / 125000;
	} else {
		$unit = "g";
		$unitSpeed = $speed_in_bytes / 125000000;
	}

	$showDecimalPlace = $unitSpeed < 10 && round($unitSpeed, 1) != round($unitSpeed);

	return sprintf($showDecimalPlace ? "%1.1f" : "%1.0f", $unitSpeed) . $unit;
}

function lcdproc_format_toSpeedInBits_longForm($speed_in_bytes) {
	/* format speed in bits/sec, input: bytes/sec
	Code from: graph.php ported to PHP

	The decimal SI kilobot definition of 1 kbit/s = 1000 bit/s, is used uniformly in the
	context of telecommunication transmission, so return kb rather than Kb */

	if ($speed_in_bytes < 125000)
		{return sprintf("%5.1f kbps", $speed_in_bytes / 125);}
	if ($speed_in_bytes < 125000000)
		{return sprintf("%5.1f mbps", $speed_in_bytes / 125000);}
	// else
	return sprintf("%5.1f gbps", $speed_in_bytes / 125000000);
}

function lcdproc_get_traffic_stats($interface_traffic_list, &$in_data, &$out_data){

	global $config;
	$lcdproc_screen_config = $config['installedpackages']['lcdprocscreens']['config'][0];
	/* read the configured interface */
	$ifnum = $lcdproc_screen_config['scr_traffic_interface'];
	/* get the real interface name (code from ifstats.php)*/
	$realif = get_real_interface($ifnum);
	if(!$realif) $realif = $ifnum; // Need for IPSec case interface.

	$interfaceEntry = $interface_traffic_list[$realif];

	$in_data  = "IN:  " . lcdproc_format_toSpeedInBits_longForm($interfaceEntry['in_Bps']);
	$out_data = "OUT: " . lcdproc_format_toSpeedInBits_longForm($interfaceEntry['out_Bps']);
}

function lcdproc_get_top_interfaces_by_bps($interfaceTrafficList, $lcdpanel_width, $lcdpanel_height) {

	$result = array();

	if (count($interfaceTrafficList) < $lcdpanel_height) {
		// All the interfaces will fit on the screen, so use the same sort order as
		// the bytes_today screen, so that the interfaces stay in one place (much easier to read)
		lcdproc_sort_interface_list_by_total_bytes($interfaceTrafficList);
	} else {
		// We can't show all the interfaces, so show the ones with the most traffic
		lcdproc_sort_interface_list_by_bps($interfaceTrafficList);
	}

	foreach($interfaceTrafficList as $interfaceEntry) {
		$result[] = lcdproc_format_interface_string($interfaceEntry, 'in_Bps', 'out_Bps', true, $lcdpanel_width);
	}
	return $result;
}

function lcdproc_get_top_interfaces_by_bytes_today($interfaceTrafficList, $lcdpanel_width) {

	$result = array();
	
	if (count($interfaceTrafficList) < $lcdpanel_height) {
		// All the interfaces will fit on the screen, so use the same sort order as
		// the bytes_today screen and the bps screen, so that the interfaces stay in 
		// one place (much easier to read)
		lcdproc_sort_interface_list_by_total_bytes($interfaceTrafficList);
	} else {
		// We can't show all the interfaces, so show the ones with the most traffic	today
		lcdproc_sort_interface_list_by_bytes_today($interfaceTrafficList);
	}

	foreach($interfaceTrafficList as $interfaceEntry) {
		$result[] = lcdproc_format_interface_string($interfaceEntry, 'in_bytes_today', 'out_bytes_today', false, $lcdpanel_width);
	}
	return $result;
}

function lcdproc_get_top_interfaces_by_total_bytes($interfaceTrafficList, $lcdpanel_width) {

	$result = array();
	lcdproc_sort_interface_list_by_total_bytes($interfaceTrafficList);

	foreach($interfaceTrafficList as $interfaceEntry) {
		$result[] = lcdproc_format_interface_string($interfaceEntry, 'in_bytes', 'out_bytes', false, $lcdpanel_width);
	}
	return $result;
}


function lcdproc_convert_bandwidth_to_shortform($bytes_string) {
	// Shorten values from bandwidth_by_ip.php, which have the form 
	// "168.16k", "10.31k", "0.00".
	// The unit is preserved, but decimal point is dropped for 10 or
	// higher, and only 1 decimal place is kept for lower than 10.
	// So "168.16k", "10.31k", "0.00" becomes "168k", "10k", "0"
	
	if ($bytes_string == "0.00") return "0";
	
	$decimalPos = strpos($bytes_string, '.');
	if ($decimalPos == 1) {
		// allow 1 decimal place
		return substr($bytes_string, 0, 3) . substr($bytes_string, 4);	
	} elseif ($decimalPos > 1) {
		// remove the decimal places
		return substr($bytes_string, 0, $decimalPos) . substr($bytes_string, $decimalPos + 3);	
	} else {
		// Our format assumptions are wrong
		return $bytes_string;
	}
}

function lcdproc_get_bandwidth_by_ip() {

	global $config;
	
	$result = array();
	$lcdproc_screens_config = $config['installedpackages']['lcdprocscreens']['config'][0];

	$lan          = $lcdproc_screens_config['scr_traffic_by_address_if'];
	$sort         = $lcdproc_screens_config['scr_traffic_by_address_sort'];
	$filter       = $lcdproc_screens_config['scr_traffic_by_address_filter'];
	$hostipformat = $lcdproc_screens_config['scr_traffic_by_address_hostipformat'];

	// ideally we would use /usr/local/www/bandwidth_by_ip.php, but it requires a 
	// logged-in authenticated user session, so use a local copy instead that's outside
	// the www directory and doesn't require an authenticated user session.
	$output = shell_exec("/usr/local/bin/php-cgi -f /usr/local/pkg/lcdproc_bandwidth_by_ip.php if=$lan sort=$sort filter=$filter hostipformat=$hostipformat");	
	
	$hostLines = explode("|", $output);
	foreach($hostLines as $hostLine) {
		$hostData = explode(";", $hostLine);
		if (count($hostData) == 3) {
			$host = array();
			$host['name']   = $hostData[0];
			$host['in']     = lcdproc_convert_bandwidth_to_shortform($hostData[1]);
			$host['out']    = lcdproc_convert_bandwidth_to_shortform($hostData[2]);
			$host['in/out'] = $host['in'] . '/' . $host['out'];
			$result[] = $host;
		}
	}
	if (count($result) === 0 && strlen($output) > 1) {
		$result['error'] = $output;
	}
	
	return $result;
}

function lcdproc_add_summary_declaration(&$lcd_cmds, $name) {
	$lcdpanel_height = lcdproc_get_lcdpanel_height();
	$lcdpanel_width = lcdproc_get_lcdpanel_width();
	if ($lcdpanel_height >= "4") {
		$lcd_cmds[] = "widget_add $name title_summary string";
		$lcd_cmds[] = "widget_add $name text_summary string";
		if ($lcdpanel_width > "16") {
			$lcd_cmds[] = "widget_set $name title_summary 1 3 \"CPU MEM STATES FREQ\"";
		} else {
			$lcd_cmds[] = "widget_set $name title_summary 1 3 \"CPU MEM STATES\"";
		}
	}
}

function lcdproc_add_summary_values(&$lcd_cmds, $name, $lcd_summary_data) {
	if ($lcd_summary_data != "") {
		$lcd_cmds[] = "widget_set $name text_summary 1 4 \"{$lcd_summary_data}\"";
	}
}

function lcdproc_build_interface($lcd) {
	global $g;
	global $config;
	$lcdproc_screens_config = $config['installedpackages']['lcdprocscreens']['config'][0];
	$lcdpanel_width  = lcdproc_get_lcdpanel_width();
	$lcdpanel_height = lcdproc_get_lcdpanel_height();
	$refresh_frequency = lcdproc_get_lcdpanel_refresh_frequency() * 8;

	$lcd_cmds = array();
	$lcd_cmds[] = "hello";
	$lcd_cmds[] = "client_set name pfSense";

	/* setup pfsense control menu */
	if (lcdproc_cmenu_enabled()) {
		$lcd_cmds[] = 'menu_add_item "" reboot_menu menu "Reboot"';
		$lcd_cmds[] = 'menu_add_item "reboot_menu" r_ask_no action "No" -next _close_';
		$lcd_cmds[] = 'menu_add_item "reboot_menu" r_ask_yes action "Yes" -next _quit_';

		$lcd_cmds[] = 'menu_add_item "" shutdown_menu menu "Shutdown"';
		$lcd_cmds[] = 'menu_add_item "shutdown_menu" s_ask_no action "No" -next _close_';
		$lcd_cmds[] = 'menu_add_item "shutdown_menu" s_ask_yes action "Yes" -next _quit_';
	}

	/* process screens to display */
	if (is_array($lcdproc_screens_config)) {
		foreach ($lcdproc_screens_config as $name => $screen) {

			if ($screen == "on" || $screen == "yes" ) {

				$includeSummary = true;

				switch($name) {
					case "scr_version":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"Welcome to\"";
						break;
					case "scr_time":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ System Time\"";
						break;
					case "scr_uptime":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ System Uptime\"";
						break;
					case "scr_hostname":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ System Name\"";
						break;
					case "scr_system":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ System Stats\"";
						break;
					case "scr_disk":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ Disk Use\"";
						break;
					case "scr_load":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ Load Averages\"";
						break;
					case "scr_states":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ Traffic States\"";
						break;
					case "scr_carp":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ CARP State\"";
						break;
					case "scr_ipsec":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ IPsec Tunnels\"";
						break;
					case "scr_interfaces":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ Interfaces\"";
						break;
					case "scr_mbuf":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ MBuf Usage\"";
						break;
					case "scr_cpufrequency":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ CPU Frequency\"";
						break;
					case "scr_cputemperature":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						break;
					case "scr_nut_ups":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name summary_wdgt scroller";
						$lcd_cmds[] = "widget_add $name runtime_wdgt scroller";
						$lcd_cmds[] = "widget_add $name batt_wdgt string";
						$lcd_cmds[] = "widget_add $name load_wdgt string";
						$includeSummary = false; // this screen needs all the lines
						break;
					case "scr_traffic":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt string";
						break;
					case "scr_top_interfaces_by_bps":
					case "scr_top_interfaces_by_total_bytes":
					case "scr_top_interfaces_by_bytes_today":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";

						for($i = 0; $i < ($lcdpanel_height - 1); $i++) {
							$lcd_cmds[] = "widget_add $name text_wdgt{$i} string";
						}
						$includeSummary = false; // this screen needs all the lines
						break;
					case "scr_interfaces_link":
						$ifLinkList = lcdproc_build_interface_link_list();
						foreach ($ifLinkList as $ifdescr => $iflink) {
							$s_name = $name . $ifdescr;
							$ifname = $iflink['name'] . ":";
							$lcd_cmds[] = "screen_add $s_name";
							$lcd_cmds[] = "screen_set $s_name heartbeat off";
							$lcd_cmds[] = "screen_set $s_name name \"$name.$ifdescr\"";
							$lcd_cmds[] = "screen_set $s_name duration $refresh_frequency";
							$lcd_cmds[] = "widget_add $s_name ifname_wdgt string";
							$lcd_cmds[] = "widget_set $s_name ifname_wdgt 1 1 \"$ifname\"";
							$lcd_cmds[] = "widget_add $s_name link_wdgt scroller";
							$lcd_cmds[] = "widget_add $s_name v4l_wdgt string";
							$lcd_cmds[] = "widget_set $s_name v4l_wdgt 1 2 \"v4:\"";
							$lcd_cmds[] = "widget_add $s_name v4a_wdgt scroller";
							$lcd_cmds[] = "widget_add $s_name v6l_wdgt string";
							$lcd_cmds[] = "widget_set $s_name v6l_wdgt 1 3 \"v6:\"";
							$lcd_cmds[] = "widget_add $s_name v6a_wdgt scroller";
							$lcd_cmds[] = "widget_add $s_name macl_wdgt string";
							$lcd_cmds[] = "widget_set $s_name macl_wdgt 1 4 \"m:\"";
							$lcd_cmds[] = "widget_add $s_name maca_wdgt scroller";
							$includeSummary = false; // this screen needs all the lines
						}
						break;
					case "scr_traffic_by_address":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name heart_wdgt icon";

						for($i = 0; $i < ($lcdpanel_height - 1); $i++) {
							$lcd_cmds[] = "widget_add $name descr_wdgt{$i} scroller";
							$lcd_cmds[] = "widget_add $name data_wdgt{$i} string";
						}
						$includeSummary = false; // this screen needs all the lines
						break;
					default:
						break;
				}
				if ($includeSummary) lcdproc_add_summary_declaration($lcd_cmds, $name);
			}
		}
	}
	lcdproc_send_lcd_commands($lcd, $lcd_cmds);
}

function lcdproc_loop_status($lcd) {
	global $g;
	global $config;
	global $lcdproc_connect_errors;
	$lcdproc_screens_config = $config['installedpackages']['lcdprocscreens']['config'][0];
	$lcdpanel_width = lcdproc_get_lcdpanel_width();
	$lcdpanel_height = lcdproc_get_lcdpanel_height();
	if (empty($g['product_name'])) {
		$g['product_name'] = "pfSense";
	}

	$refresh_frequency = lcdproc_get_lcdpanel_refresh_frequency();
	/* keep a counter to see how many times we can loop */
	$loopCounter = 1;
	while ($loopCounter) {
		/* prepare the summary data */
		if ($lcdpanel_height >= "4") {
			$summary_states = explode("/", get_pfstate());
			$lcd_summary_data = sprintf("%02d%% %02d%% %6d", lcdproc_get_cpu_stats(), mem_usage(), $summary_states[0]);
			if ($lcdpanel_width > "16") {
				/* Include the CPU frequency as a percentage */
				$maxfreq = lcdproc_get_cpu_maxfrequency();
				if ($maxfreq === false || $maxfreq == 0) {
					$lcd_summary_data .= "  N/A"; // powerd not available on all systems - https://redmine.pfsense.org/issues/5739
				} else {
					$lcd_summary_data .= sprintf(" %3d%%", lcdproc_get_cpu_currentfrequency() / $maxfreq * 100);
				}
			}
		} else {
			$lcd_summary_data = "";
		}

		$lcd_cmds = array();
		$interfaceTrafficList = null;
		$ifLinkList = null;

		/* initializes the widget counter */
		$widget_counter = 0;

		/* controls the output leds */
		if (lcdproc_outputled_enabled_CFontz633()) {
			$led_output_value = 0;
			/* LED 1: Interface status */
			if (substr_count(lcdproc_get_interfaces_stats(), "Down") > 0 ) {
				$led_output_value = $led_output_value + pow(2, 4);
			} else {
				$led_output_value = $led_output_value + pow(2, 0);
			}
			/* LED 2: CARP status */
			switch (lcdproc_outputled_carp()) {
				/* CARP disabled */
				case -1:
					break;
				/* CARP on Backup */
				case 0:
					$led_output_value = $led_output_value + pow(2, 1);
					break;
				/* CARP on Master */
				case 1:
					$led_output_value = $led_output_value + pow(2, 5);
			}
			/* LED 3: CPU Usage */
			if (lcdproc_get_cpu_stats() > 50) {
				$led_output_value = $led_output_value + pow(2, 6);
			} else {
				$led_output_value = $led_output_value + pow(2, 2);
			}
			/* LED 4: Gateway status */
			switch (lcdproc_outputled_gateway()) {
				/* Gateways not configured */
				case -1:
					break;
				/* Gateway down or with issues */
				case 0:
					$led_output_value = $led_output_value + pow(2, 7);
					break;
				/* All Gateways up */
				case 1:
					$led_output_value = $led_output_value + pow(2, 3);
			}
			/* Sends the command to the panel */
			$lcd_cmds[] = "output {$led_output_value}";
		}

		/* process screens to display */
		foreach ((array) $lcdproc_screens_config as $name => $screen) {
			if ($screen != "on" && $screen != "yes") {
				continue;
			}

			$updateSummary = true;

			switch($name) {
				case "scr_version":
					$version = lcdproc_get_version();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$version}\"";
					break;
				case "scr_time":
					$time = date("n/j/Y H:i");
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$time}\"";
					break;
				case "scr_uptime":
					$uptime = lcdproc_get_uptime_stats();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$uptime}\"";
					break;
				case "scr_hostname":
					exec("/bin/hostname", $output, $ret);
					$hostname = $output[0];
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$hostname}\"";
					break;
				case "scr_system":
					$processor = lcdproc_get_cpu_stats();
					$memory = mem_usage();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"CPU {$processor}%, Mem {$memory}%\"";
					break;
				case "scr_disk":
					$disk = disk_usage();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"Disk {$disk}%\"";
					break;
				case "scr_load":
					$loadavg = get_load_average();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$loadavg}\"";
					break;
				case "scr_states":
					$states = get_pfstate();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"Cur/Max {$states}\"";
					break;
				case "scr_carp":
					$carp = lcdproc_get_carp_stats();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$carp}\"";
					break;
				case "scr_ipsec":
					$ipsec = lcdproc_get_ipsec_stats();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$ipsec}\"";
					break;
				case "scr_interfaces":
					$interfaces = lcdproc_get_interfaces_stats();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$interfaces}\"";
					break;
				case "scr_mbuf":
					get_mbuf($mbuf, $mbufpercent);
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$mbufpercent}%, ${mbuf}\"";
					break;
				case "scr_cpufrequency":
					$cpufreq = lcdproc_get_cpufrequency();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$cpufreq}\"";
					break;
				case "scr_cputemperature":
					$cputemperature = lcdproc_get_cpu_temperature();
					$title = ($lcdpanel_width >= 20) ? "+ CPU Temperature" : "+ CPU Temp";
					$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"{$title}\"";
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$cputemperature}\"";
					break;
				case "scr_nut_ups":
					$ups_status = lcdproc_get_nut_ups_status();
					if ($ups_status === false) {
						$lcd_cmds[] = "widget_set $name summary_wdgt 1 1 $lcdpanel_width 2 h 4 \"NUT Not Installed\"";
					} else {
						if ($ups_status['_hms'] == null && $ups_status['ups.load'] == null && $ups_status['battery.charge'] == null) {
							$lcd_cmds[] = "widget_set $name summary_wdgt 1 1 $lcdpanel_width 2 h 3 \"{$ups_status['_summary']}\"";
							// Try to clear lines 
							switch($lcdpanel_height) {
								case 2:
									$lcd_cmds[] = "widget_set $name runtime_wdgt 1 2 $lcdpanel_width 2 h 3 \"\"";
									break;
								case 4:
									$lcd_cmds[] = "widget_set $name runtime_wdgt 1 2 $lcdpanel_width 2 h 3 \"\"";
									$lcd_cmds[] = "widget_set $name batt_wdgt 1 3 \"\"";
									$lcd_cmds[] = "widget_set $name load_wdgt 1 4 \"\"";
									break;
								default:
									$lcd_cmds[] = "widget_set $name runtime_wdgt 1 2 $lcdpanel_width 2 h 3 \"\"";
									break;
							}
						} else {
							$runtime = ($ups_status['_hms'] == null) ? "VOLTS: {$ups_status['battery.voltage']}/{$ups_status['battery.voltage.low']}" : "HMS: {$ups_status['_hms']}";
							switch($lcdpanel_height) {
								case 1:
									$lcd_cmds[] = "widget_set $name summary_wdgt 1 1 $lcdpanel_width 2 h 2 \"{$ups_status['_summary']} {$runtime} BATT:{$ups_status['battery.charge']}% LOAD:{$ups_status['ups.load']}%\"";
									break;
								case 2:
									$lcd_cmds[] = "widget_set $name summary_wdgt 1 1 $lcdpanel_width 2 h 3 \"{$ups_status['_summary']}\"";
									$lcd_cmds[] = "widget_set $name runtime_wdgt 1 2 $lcdpanel_width 2 h 3 \"{$runtime} BATT:{$ups_status['battery.charge']}% LOAD:{$ups_status['ups.load']}%\"";
									break;
								case 4:
									$lcd_cmds[] = "widget_set $name summary_wdgt 1 1 $lcdpanel_width 2 h 3 \"{$ups_status['_summary']}\"";
									$lcd_cmds[] = "widget_set $name runtime_wdgt 1 2 $lcdpanel_width 2 h 3 \"{$runtime}\"";
									$lcd_cmds[] = "widget_set $name batt_wdgt 1 3 \"BATT: {$ups_status['battery.charge']}%\"";
									$lcd_cmds[] = "widget_set $name load_wdgt 1 4 \"LOAD: {$ups_status['ups.load']}%\"";
									break;
								default:
									// Handle a future LCD height by playing it safe with 2 rows and scrolling
									$lcd_cmds[] = "widget_set $name summary_wdgt 1 1 $lcdpanel_width 2 h 3 \"{$ups_status['_summary']}\"";
									$lcd_cmds[] = "widget_set $name runtime_wdgt 1 2 $lcdpanel_width 2 h 3 \"{$runtime} BATT:{$ups_status['battery.charge']}% LOAD:{$ups_status['ups.load']}%\"";
									break;
							}
						}
					}
					$updateSummary = false;
					break;
				case "scr_traffic":
					if ($interfaceTrafficList == null) $interfaceTrafficList = lcdproc_build_interface_traffic_stats_list(); // We only want lcdproc_build_interface_traffic_stats_list() to be called once per loop, and only if it's needed
					lcdproc_get_traffic_stats($interfaceTrafficList, $in_data, $out_data);
					$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"{$in_data}\"";
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 \"{$out_data}\"";
					break;
				case "scr_top_interfaces_by_bps":
					if ($interfaceTrafficList == null) $interfaceTrafficList = lcdproc_build_interface_traffic_stats_list(); // We only want lcdproc_build_interface_traffic_stats_list() to be called once per loop, and only if it's needed
					$interfaceTrafficStrings = lcdproc_get_top_interfaces_by_bps($interfaceTrafficList, $lcdpanel_width, $lcdpanel_height);

					$title = ($lcdpanel_width >= 20) ? "Interface bps IN/OUT" : "Intf. bps IN/OUT";
					$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"{$title}\"";

					for($i = 0; $i < ($lcdpanel_height - 1) && $i < count($interfaceTrafficStrings); $i++) {

						$lcd_cmds[] = "widget_set $name text_wdgt{$i} 1 " . ($i + 2) . " \"{$interfaceTrafficStrings[$i]}\"";
					}
					$updateSummary = false;
					break;
				case "scr_top_interfaces_by_bytes_today":
					if ($interfaceTrafficList == null) $interfaceTrafficList = lcdproc_build_interface_traffic_stats_list(); // We only want lcdproc_build_interface_traffic_stats_list() to be called once per loop, and only if it's needed
					$interfaceTrafficStrings = lcdproc_get_top_interfaces_by_bytes_today($interfaceTrafficList, $lcdpanel_width);

					$title = ($lcdpanel_width >= 20) ? "Total today   IN/OUT" : "Today   IN / OUT";
					$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"{$title}\"";

					for($i = 0; $i < ($lcdpanel_height - 1) && $i < count($interfaceTrafficStrings); $i++) {

						$lcd_cmds[] = "widget_set $name text_wdgt{$i} 1 " . ($i + 2) . " \"{$interfaceTrafficStrings[$i]}\"";
					}
					$updateSummary = false;
					break;
				case "scr_top_interfaces_by_total_bytes":
					if ($interfaceTrafficList == null) $interfaceTrafficList = lcdproc_build_interface_traffic_stats_list(); // We only want lcdproc_build_interface_traffic_stats_list() to be called once per loop, and only if it's needed
					$interfaceTrafficStrings = lcdproc_get_top_interfaces_by_total_bytes($interfaceTrafficList, $lcdpanel_width);

					$title = ($lcdpanel_width >= 20) ? "Total         IN/OUT" : "Total   IN / OUT";
					$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"{$title}\"";

					for($i = 0; $i < ($lcdpanel_height - 1) && $i < count($interfaceTrafficStrings); $i++) {

						$lcd_cmds[] = "widget_set $name text_wdgt{$i} 1 " . ($i + 2) . " \"{$interfaceTrafficStrings[$i]}\"";
					}
					$updateSummary = false;
					break;
				case "scr_interfaces_link":
					// We only want lcdproc_build_interface_link_list() to be
					// called once per loop, and only if it's needed
					if ($ifLinkList == null) {
						$ifLinkList = lcdproc_build_interface_link_list();
					}

					foreach ($ifLinkList as $ifdescr => $iflink) {
						$s_name = $name . $ifdescr;
						$ifname = $iflink['name'] . ":";
						$l_str = ($iflink['status'] == "down") ? "down" : $iflink['link'];

						$lcd_cmds[] = "widget_set $s_name ifname_wdgt 1 1 \"$ifname\"";

						$lcd_cmds[] = "widget_set $s_name link_wdgt " .
							(strlen($iflink['name']) + 3) . " 1 " .
							$lcdpanel_width . " 1 h 4 \"" . $l_str . "\"";

						$lcd_cmds[] = "widget_set $s_name v4a_wdgt 5 2 " .
							$lcdpanel_width . " 2 h 4 \"" .
							$iflink['v4addr'] . "\"";

						$lcd_cmds[] = "widget_set $s_name v6a_wdgt 5 3 " .
							$lcdpanel_width . " 3 h 4 \"" .
							$iflink['v6addr'] . "\"";

						$lcd_cmds[] = "widget_set $s_name maca_wdgt 4 4 " .
							$lcdpanel_width . " 4 h 4 \"" .
							$iflink['mac'] . "\"";
					}
					$updateSummary = false;
					break;
				case "scr_traffic_by_address":
					$title = ($lcdpanel_width >= 20) ? "Host       IN / OUT" : "Host   IN / OUT";
					$lcd_cmds[] = "widget_set $name title_wdgt 2 1 \"{$title}\"";
					$lcd_cmds[] = "widget_set $name heart_wdgt 1 1 \"" . (($loopCounter & 1) == 0 ? "HEART_OPEN" : "HEART_FILLED") . "\""; // Indicate each time the list has been updated
								
					$traffic = lcdproc_get_bandwidth_by_ip();
					$clearLinesFrom = 0;
					
					if (isset($traffic['error'])) {
						if ($traffic['error'] === "no info") {
							// not really an error - there's likely just no traffic
						} else {
							// traffic info not available, display the error message instead
							$lcd_cmds[] = "widget_set $name descr_wdgt0 1 2 $lcdpanel_width 2 h 2 \"Error: {$traffic['error']}\"";
							$lcd_cmds[] = "widget_set $name data_wdgt0 1 2 \"\"";							
							$clearLinesFrom = 1;
						}												
					} else {
						for($i = 0; $i < ($lcdpanel_height - 1) && $i < count($traffic); $i++) {
							$speeds = $traffic[$i]['in/out'];
							$left = $lcdpanel_width - strlen($speeds);							
							$lcd_cmds[] = "widget_set $name data_wdgt{$i} " . ($left + 1) . " " . ($i + 2) . " \"{$speeds}\"";							
							$lcd_cmds[] = "widget_set $name descr_wdgt{$i} 1 " . ($i + 2) . " " . ($left - 1) . " " . ($i + 2) . " h 2 \"{$traffic[$i]['name']}\"";						
							$clearLinesFrom = $i + 1;
						}
					}		
					for($i = $clearLinesFrom; $i < ($lcdpanel_height - 1); $i++) {
						$lcd_cmds[] = "widget_set $name descr_wdgt{$i} 1 2 1 2 h 2 \"\"";
						$lcd_cmds[] = "widget_set $name  data_wdgt{$i} 1 2         \"\"";							
					}
					$updateSummary = false;
					break;
				default:
					break;
			}
			if ($name != "scr_traffic_interface" && substr($name, 0, 23) != 'scr_traffic_by_address_') {	// "scr_traffic_interface" isn't a real screen, it's a parameter for the "scr_traffic" screen
				$widget_counter++;
				if ($updateSummary) lcdproc_add_summary_values($lcd_cmds, $name, $lcd_summary_data);
			}
		}
		if (lcdproc_send_lcd_commands($lcd, $lcd_cmds)) {
			$lcdproc_connect_errors = 0; // Reset the error counter
		} else {
			//an error occurred
			return;
		}
		if (($refresh_frequency * $widget_counter) > 5) {
			// If LCD is waiting 10 seconds on each screen, for example, then we can update the data of 
			// of a screen while its being displayed.
			sleep(5);
		} else {
			sleep($refresh_frequency * $widget_counter);
		}
		$loopCounter++;
	}
}

/* Initialize the wan traffic counters */
$traffic_last_ugmt  = array();
$traffic_last_ifin  = array();
$traffic_last_ifout = array();

$traffic_last_hour        = array();
$traffic_startOfDay_ifin  = array();
$traffic_startOfDay_ifout = array();

/* Initialize the global error counter */
$lcdproc_connect_errors = 0;
$lcdproc_max_connect_errors = 3;
/* Connect to the LCDd port and interface with the LCD */
while ($lcdproc_connect_errors <= $lcdproc_max_connect_errors) {
	lcdproc_warn("Start client procedure. Error counter: ($lcdproc_connect_errors)");
	sleep(1);
	$lcd = fsockopen(LCDPROC_HOST, LCDPROC_PORT, $errno, $errstr, 10);
	if (!$lcd) {
		lcdproc_warn("Failed to connect to LCDd process $errstr ($errno)");
		$lcdproc_connect_errors++;
	} else {
		stream_set_timeout($lcd, 0 , 25000); // Sets the socket timeout as 25ms
		/* Allow the script to run forever (0) */
		set_time_limit(0);
		lcdproc_build_interface($lcd);
		lcdproc_loop_status($lcd);
		fclose($lcd);
	}
}
if ($lcdproc_connect_errors >= $lcdproc_max_connect_errors) {
	lcdproc_warn("Too many errors, the client ends.");
}
?>
