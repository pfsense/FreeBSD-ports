<?php
/*
	lcdproc_client.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2007-2009 Seth Mos <seth.mos@dds.nl>
	Copyright (C) 2009 Scott Ullrich
	Copyright (C) 2011 Michele Di Maria
	Copyright (C) 2015 ESF, LLC
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
require_once("config.inc");
require_once("functions.inc");
require_once("interfaces.inc");
require_once("/usr/local/pkg/lcdproc.inc");

function get_pfstate() {
	global $config;
	$matches = "";
	if (isset($config['system']['maximumstates']) and $config['system']['maximumstates'] > 0) {
		$maxstates = "/{$config['system']['maximumstates']}";
	} else {
		$maxstates = "/". pfsense_default_state_size();
	}
	$curentries = shell_exec('/sbin/pfctl -si | /usr/bin/grep current');
	if (preg_match("/([0-9]+)/", $curentries, $matches)) {
		$curentries = $matches[1];
	}
	return $curentries . $maxstates;
}

function disk_usage() {
	$dfout = "";
	exec("/bin/df -h | /usr/bin/grep -w '/' | /usr/bin/awk '{ print $5 }' | /usr/bin/cut -d '%' -f 1", $dfout);
	$diskusage = trim($dfout[0]);

	return $diskusage;
}

function mem_usage() {
	$memory = "";
	exec("/sbin/sysctl -n vm.stats.vm.v_page_count vm.stats.vm.v_inactive_count " .
		"vm.stats.vm.v_cache_count vm.stats.vm.v_free_count", $memory);

	$totalMem = $memory[0];
	$availMem = $memory[1] + $memory[2] + $memory[3];
	$usedMem = $totalMem - $availMem;
	$memUsage = round(($usedMem * 100) / $totalMem, 0);

	return $memUsage;
}

/* Calculates non-idle CPU time and returns as a percentage */
function cpu_usage() {
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

function get_uptime_stats() {
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

function get_loadavg_stats() {
	exec("/usr/bin/uptime", $output, $ret);
	if (stristr($output[0], "day")) {
		$temp = explode(" ", $output[0]);
		$status = "$temp[11] $temp[12] $temp[13]";
	} else {
		$temp = explode(" ", $output[0]);
		$status = "$temp[10] $temp[11] $temp[12]";
	}
	return($status);
}

function get_mbuf_stats() {
	exec("/usr/bin/netstat -mb | /usr/bin/grep \"mbufs in use\" | /usr/bin/awk '{ print $1 }' | /usr/bin/cut -d\"/\" -f1", $mbufs_inuse);
	exec("/usr/bin/netstat -mb | /usr/bin/grep \"mbufs in use\" | /usr/bin/awk '{ print $1 }' | /usr/bin/cut -d\"/\" -f3", $mbufs_total);
	$status = "$mbufs_inuse[0] \/ $mbufs_total[0]";
	return($status);
}

function get_version() {
	global $g;
	$version = @file_get_contents("/etc/version");
	$version = trim($version);
	return("{$g['product_name']} {$version}");
}

// Returns the max frequency in Mhz, or false if powerd is not supported.
// powerd is not supported on all systems - "no cpufreq(4) support" https://redmine.pfsense.org/issues/5739
function get_cpu_maxfrequency() {
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
function get_cpu_currentfrequency() {
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

function get_cpufrequency() {
	$maxfreq = get_cpu_maxfrequency();
	if ($maxfreq === false) {
		return "no cpufreq(4) support";
	} else {
		$curfreq = get_cpu_currentfrequency();
		return "$curfreq\/$maxfreq Mhz";
	}
}

function get_interfaces_stats() {
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

function get_slbd_stats() {
	global $g;
	global $config;

	if (!is_array($config['load_balancer']['lbpool'])) {
		$config['load_balancer']['lbpool'] = array();
	}
	$a_pool = &$config['load_balancer']['lbpool'];

	$slbd_logfile = "{$g['varlog_path']}/slbd.log";

	$nentries = $config['syslog']['nentries'];
	if (!$nentries) {
		$nentries = 50;
	}

	$now = time();
	$year = date("Y");
	$pstatus = "";
	$i = 0;
	foreach ($a_pool as $vipent) {
		$pstatus[] = "{$vipent['name']}";
		if ($vipent['type'] == "gateway") {
			$poolfile = "{$g['tmp_path']}/{$vipent['name']}.pool";
			if (file_exists("$poolfile")) {
				$poolstatus = file_get_contents("$poolfile");
			} else {
				continue;
			}
			foreach ((array) $vipent['servers'] as $server) {
				$lastchange = "";
				$svr = explode("|", $server);
				$monitorip = $svr[1];
				if (stristr($poolstatus, $monitorip)) {
					$online = "Up";
				} else {
					$online = "Down";
				}
				$pstatus[] = strtoupper($svr[0]) ." [{$online}]";
			}
		} else {
			$pstatus[] = "{$vipent['monitor']}";
		}
	}
	if (count($a_pool) == 0) {
		$pstatus[] = "Disabled";
	}
	$status = implode(", ", $pstatus);
	return($status);
}

function get_carp_stats() {
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
			$carp_int = find_carp_interface($ipaddress);
			$status = get_carp_interface_status($carp_int);
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

function get_ipsec_tunnel_sad() {
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

function get_ipsec_tunnel_src($tunnel) {
	global $g, $config, $sad;
	$if = "WAN";
	if ($tunnel['interface']) {
		$if = $tunnel['interface'];
		$realinterface = convert_friendly_interface_to_real_interface_name($if);
		$interfaceip = find_interface_ip($realinterface);
	}
	return $interfaceip;
}

function output_ipsec_tunnel_status($tunnel) {
	global $g, $config, $sad;
	$if = "WAN";
	$interfaceip = get_ipsec_tunnel_src($tunnel);
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

function get_ipsec_stats() {
	global $g, $config, $sad;
	$sad = array();
	$sad = get_ipsec_tunnel_sad();

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

			if (output_ipsec_tunnel_status($tunnel)) {
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

function send_lcd_commands($lcd, $lcd_cmds) {
	if (!is_array($lcd_cmds) || (empty($lcd_cmds))) {
		lcdproc_warn("Failed to interpret lcd commands");
		return;
	}
	while (($cmd_output = fgets($lcd, 8000)) !== false) {
		if (preg_match("/^huh?/", $cmd_output)) {
			lcdproc_notice("LCDd output: \"$cmd_output\". Executed \"$lcd_cmd\"");
		}
	}
	foreach ($lcd_cmds as $lcd_cmd) {
		if (! fwrite($lcd, "$lcd_cmd\n")) {
			lcdproc_warn("Connection to LCDd process lost $errstr ($errno)");
			$lcdproc_connect_errors++;
			return false;
		}
	}
	return true;
}

function get_lcdpanel_width() {
	global $config;
	$lcdproc_size_config = $config['installedpackages']['lcdproc']['config'][0];
	if (is_null($lcdproc_size_config['size'])) {
		return "16";
	} else {
		$dimensions = explode("x", $lcdproc_size_config['size']);
		return $dimensions[0];
	}
}

function get_lcdpanel_height() {
	global $config;
	$lcdproc_size_config = $config['installedpackages']['lcdproc']['config'][0];
	if (is_null($lcdproc_size_config['size'])) {
		return "2";
	} else {
		$dimensions = explode("x", $lcdproc_size_config['size']);
		return $dimensions[1];
	}
}

function get_lcdpanel_refresh_frequency() {
	global $config;
	$lcdproc_size_config = $config['installedpackages']['lcdproc']['config'][0];
	$value = $lcdproc_size_config['refresh_frequency'];
	if (is_null($value)) {
		return "5";
	} else {
		return $value;
	}
}

function outputled_enabled_CFontz633() {
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

function outputled_carp() {
	/* Returns the status of CARP for the box.
	Assumes ALL CARP status are the same for all the intefaces.
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
			$carp_int = find_carp_interface($carp['subnet']);
			$status = get_carp_interface_status($carp_int);
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

function outputled_gateway() {
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

function build_interface_traffic_stats_list() {
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

		calculate_interfaceBytesPerSecond_sinceLastChecked($interfaceName, $interfaceStats, $in_Bps, $out_Bps);

		$entry = array();
		$entry['descr']       = $description;

		$entry['in_Bps']      = $in_Bps;
		$entry['out_Bps']     = $out_Bps;
		$entry['total_Bps']   = $in_Bps + $out_Bps;

		$entry['in_bytes']    = $interfaceStats['inbytes'];
		$entry['out_bytes']   = $interfaceStats['outbytes'];
		$entry['total_bytes'] = $interfaceStats['inbytes'] + $interfaceStats['outbytes'];

		$result[$interface['if']] = $entry;
	}
	return $result;
}

function sort_interface_list_by_bytesToday(&$interfaceTrafficStatsList) {
	uasort($interfaceTrafficStatsList, "cmp_total_bytes");
}

function sort_interface_list_by_bps(&$interfaceTrafficStatsList) {
	uasort($interfaceTrafficStatsList, "cmp_total_Bps");
}

function cmp_total_Bps($a, $b)
{
	if ($a['total_Bps'] == $b['total_Bps']) return 0;

	return ($a['total_Bps'] < $b['total_Bps']) ? 1 : -1;
}

function cmp_total_bytes($a, $b)
{
	if ($a['total_bytes'] == $b['total_bytes']) return 0;

	return ($a['total_bytes'] < $b['total_bytes']) ? 1 : -1;
}

function calculate_interfaceBytesPerSecond_sinceLastChecked($interfaceName, $interfaceStats, &$in_Bps, &$out_Bps) {
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

function format_interface_string($interfaceEntry, $in_key, $out_key, $output_in_bits, $outputLength) {

	if ($output_in_bits) {
		$speed = " " . format_toSpeedInBits_shortForm($interfaceEntry[$in_key]) . "/" . format_toSpeedInBits_shortForm($interfaceEntry[$out_key]);
	} else {
		$speed = " " . format_toSizeInBytes_shortForm($interfaceEntry[$in_key]) . "/" . format_toSizeInBytes_shortForm($interfaceEntry[$out_key]);
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

function format_toSizeInBytes_shortForm($size_in_bytes) {
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

function format_toSpeedInBits_shortForm($speed_in_bytes) {
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

function format_toSpeedInBits_longForm($speed_in_bytes) {
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

function get_traffic_stats($interface_traffic_list, &$in_data, &$out_data){

	global $config;
	$lcdproc_screen_config = $config['installedpackages']['lcdprocscreens']['config'][0];
	/* read the configured interface */
	$ifnum = $lcdproc_screen_config['scr_traffic_interface'];
	/* get the real interface name (code from ifstats.php)*/
	$realif = get_real_interface($ifnum);
	if(!$realif) $realif = $ifnum; // Need for IPSec case interface.

	$interfaceEntry = $interface_traffic_list[$realif];

	$in_data  = "IN:  " . format_toSpeedInBits_longForm($interfaceEntry['in_Bps']);
	$out_data = "OUT: " . format_toSpeedInBits_longForm($interfaceEntry['out_Bps']);
}

function get_top_interfaces_by_bps($interfaceTrafficList, $lcdpanel_width, $lcdpanel_height) {

	$result = array();

	if (count($interfaceTrafficList) < $lcdpanel_height) {
		// All the interfaces will fit on the screen, so use the same sort order as
		// the bytes_today screen, so that the interfaces stay in one place (much easier to read)
		sort_interface_list_by_bytesToday($interfaceTrafficList);
	} else {
		// We can't show all the interfaces, so show the ones with the most traffic
		sort_interface_list_by_bps($interfaceTrafficList);
	}

	foreach($interfaceTrafficList as $interfaceEntry) {
		$result[] = format_interface_string($interfaceEntry, 'in_Bps', 'out_Bps', true, $lcdpanel_width);
	}
	return $result;
}

function get_top_interfaces_by_bytes_today($interfaceTrafficList, $lcdpanel_width) {

	$result = array();

	sort_interface_list_by_bytesToday($interfaceTrafficList);

	foreach($interfaceTrafficList as $interfaceEntry) {
		$result[] = format_interface_string($interfaceEntry, 'in_bytes', 'out_bytes', false, $lcdpanel_width);
	}

	return $result;
}

function convert_bandwidth_to_shortform($bytes_string) {
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

function get_bandwidth_by_ip() {

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
			$host['in']     = convert_bandwidth_to_shortform($hostData[1]);
			$host['out']    = convert_bandwidth_to_shortform($hostData[2]);
			$host['in/out'] = $host['in'] . '/' . $host['out'];
			$result[] = $host;
		}
	}
	if (count($result) === 0 && strlen($output) > 1) {
		$result['error'] = $output;
	}
	
	return $result;
}

function add_summary_declaration(&$lcd_cmds, $name) {
	$lcdpanel_height = get_lcdpanel_height();
	$lcdpanel_width = get_lcdpanel_width();
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

function add_summary_values(&$lcd_cmds, $name, $lcd_summary_data) {
	if ($lcd_summary_data != "") {
		$lcd_cmds[] = "widget_set $name text_summary 1 4 \"{$lcd_summary_data}\"";
	}
}

function build_interface($lcd) {
	global $g;
	global $config;
	$lcdproc_screens_config = $config['installedpackages']['lcdprocscreens']['config'][0];
	$lcdpanel_width  = get_lcdpanel_width();
	$lcdpanel_height = get_lcdpanel_height();
	$refresh_frequency = get_lcdpanel_refresh_frequency() * 8;

	$lcd_cmds = array();
	$lcd_cmds[] = "hello";
	$lcd_cmds[] = "client_set name pfSense";

	/* process screens to display */
	if (is_array($lcdproc_screens_config)) {
		foreach ($lcdproc_screens_config as $name => $screen) {

			// Every time I restarted lcdproc, more duplicate screens would get created on the LCD,
			// deleting screens before building them seems to automatically avoid this without any
			// side-effects that I've noticed.
			$lcd_cmds[] = "screen_del $name";

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
					case "scr_slbd":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt scroller";
						$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"+ Load Balancer\"";
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
					case "scr_traffic":
						$lcd_cmds[] = "screen_add $name";
						$lcd_cmds[] = "screen_set $name heartbeat off";
						$lcd_cmds[] = "screen_set $name name $name";
						$lcd_cmds[] = "screen_set $name duration $refresh_frequency";
						$lcd_cmds[] = "widget_add $name title_wdgt string";
						$lcd_cmds[] = "widget_add $name text_wdgt string";
						break;
					case "scr_top_interfaces_by_bps":
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

				}
				if ($includeSummary) add_summary_declaration($lcd_cmds, $name);
			}
		}
	}
	send_lcd_commands($lcd, $lcd_cmds);
}

function loop_status($lcd) {
	global $g;
	global $config;
	global $lcdproc_connect_errors;
	$lcdproc_screens_config = $config['installedpackages']['lcdprocscreens']['config'][0];
	$lcdpanel_width = get_lcdpanel_width();
	$lcdpanel_height = get_lcdpanel_height();
	if (empty($g['product_name'])) {
		$g['product_name'] = "pfSense";
	}

	$refresh_frequency = get_lcdpanel_refresh_frequency();
	/* keep a counter to see how many times we can loop */
	$loopCounter = 1;
	while ($loopCounter) {
		/* prepare the summary data */
		if ($lcdpanel_height >= "4") {
			$summary_states = explode("/", get_pfstate());
			$lcd_summary_data = sprintf("%02d%% %02d%% %6d", cpu_usage(), mem_usage(), $summary_states[0]);
			if ($lcdpanel_width > "16") {
				/* Include the CPU frequency as a percentage */
				$maxfreq = get_cpu_maxfrequency();
				if ($maxfreq === false || $maxfreq == 0) {
					$lcd_summary_data .= "  N/A"; // powerd not available on all systems - https://redmine.pfsense.org/issues/5739
				} else {
					$lcd_summary_data .= sprintf(" %3d%%", get_cpu_currentfrequency() / $maxfreq * 100);				
				}
			}
		} else {
			$lcd_summary_data = "";
		}

		$lcd_cmds = array();
		$interfaceTrafficList = null;

		/* initializes the widget counter */
		$widget_counter = 0;

		/* controls the output leds */
		if (outputled_enabled_CFontz633()) {
			$led_output_value = 0;
			/* LED 1: Interface status */
			if (substr_count(get_interfaces_stats(), "Down") > 0 ) {
				$led_output_value = $led_output_value + pow(2, 4);
			} else {
				$led_output_value = $led_output_value + pow(2, 0);
			}
			/* LED 2: CARP status */
			switch (outputled_carp()) {
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
			if (cpu_usage() > 50) {
				$led_output_value = $led_output_value + pow(2, 6);
			} else {
				$led_output_value = $led_output_value + pow(2, 2);
			}
			/* LED 4: Gateway status */
			switch (outputled_gateway()) {
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
					$version = get_version();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$version}\"";
					break;
				case "scr_time":
					$time = date("n/j/Y H:i");
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$time}\"";
					break;
				case "scr_uptime":
					$uptime = get_uptime_stats();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$uptime}\"";
					break;
				case "scr_hostname":
					exec("/bin/hostname", $output, $ret);
					$hostname = $output[0];
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$hostname}\"";
					break;
				case "scr_system":
					$processor = cpu_usage();
					$memory = mem_usage();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"CPU {$processor}%, Mem {$memory}%\"";
					break;
				case "scr_disk":
					$disk = disk_usage();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"Disk {$disk}%\"";
					break;
				case "scr_load":
					$loadavg = get_loadavg_stats();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$loadavg}\"";
					break;
				case "scr_states":
					$states = get_pfstate();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"Cur/Max {$states}\"";
					break;
				case "scr_carp":
					$carp = get_carp_stats();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$carp}\"";
					break;
				case "scr_ipsec":
					$ipsec = get_ipsec_stats();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$ipsec}\"";
					break;
				case "scr_slbd":
					$slbd = get_slbd_stats();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$slbd}\"";
					break;
				case "scr_interfaces":
					$interfaces = get_interfaces_stats();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$interfaces}\"";
					break;
				case "scr_mbuf":
					$mbufstats = get_mbuf_stats();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$mbufstats}\"";
					break;
				case "scr_cpufrequency":
					$cpufreq = get_cpufrequency();
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 $lcdpanel_width 2 h 4 \"{$cpufreq}\"";
					break;
				case "scr_traffic":
					if ($interfaceTrafficList == null) $interfaceTrafficList = build_interface_traffic_stats_list(); // We only want build_interface_traffic_stats_list() to be called once per loop, and only if it's needed
					get_traffic_stats($interfaceTrafficList, $in_data, $out_data);
					$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"{$in_data}\"";
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 \"{$out_data}\"";
					break;
				case "scr_top_interfaces_by_bps":
					if ($interfaceTrafficList == null) $interfaceTrafficList = build_interface_traffic_stats_list(); // We only want build_interface_traffic_stats_list() to be called once per loop, and only if it's needed
					$interfaceTrafficStrings = get_top_interfaces_by_bps($interfaceTrafficList, $lcdpanel_width, $lcdpanel_height);

					$title = ($lcdpanel_width >= 20) ? "Interface bps IN/OUT" : "Intf. bps IN/OUT";
					$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"{$title}\"";

					for($i = 0; $i < ($lcdpanel_height - 1) && i < count($interfaceTrafficStrings); $i++) {

						$lcd_cmds[] = "widget_set $name text_wdgt{$i} 1 " . ($i + 2) . " \"{$interfaceTrafficStrings[$i]}\"";
					}
					$updateSummary = false;
					break;
				case "scr_top_interfaces_by_bytes_today":
					if ($interfaceTrafficList == null) $interfaceTrafficList = build_interface_traffic_stats_list(); // We only want build_interface_traffic_stats_list() to be called once per loop, and only if it's needed
					$interfaceTrafficStrings = get_top_interfaces_by_bytes_today($interfaceTrafficList, $lcdpanel_width);

					$title = ($lcdpanel_width >= 20) ? "Total today   IN/OUT" : "Today   IN / OUT";
					$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"{$title}\"";

					for($i = 0; $i < ($lcdpanel_height - 1) && i < count($interfaceTrafficStrings); $i++) {

						$lcd_cmds[] = "widget_set $name text_wdgt{$i} 1 " . ($i + 2) . " \"{$interfaceTrafficStrings[$i]}\"";
					}
					$updateSummary = false;
					break;
				case "scr_traffic_by_address":
					$title = ($lcdpanel_width >= 20) ? "Host       IN / OUT" : "Host   IN / OUT";
					$lcd_cmds[] = "widget_set $name title_wdgt 2 1 \"{$title}\"";
					$lcd_cmds[] = "widget_set $name heart_wdgt 1 1 \"" . (($loopCounter & 1) == 0 ? "HEART_OPEN" : "HEART_FILLED") . "\""; // Indicate each time the list has been updated
								
					$traffic = get_bandwidth_by_ip();
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
						for($i = 0; $i < ($lcdpanel_height - 1) && i < count($traffic); $i++) {
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
			}
			if ($name != "scr_traffic_interface" && substr($name, 0, 23) != 'scr_traffic_by_address_') {	// "scr_traffic_interface" isn't a real screen, it's a parameter for the "scr_traffic" screen
				$widget_counter++;
				if ($updateSummary) add_summary_values($lcd_cmds, $name, $lcd_summary_data);
			}
		}
		if (send_lcd_commands($lcd, $lcd_cmds)) {
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
/* Initialize the global error counter */
$lcdproc_connect_errors = 0;
$lcdproc_max_connect_errors = 3;
/* Connect to the LCDd port and interface with the LCD */
while ($lcdproc_connect_errors <= $lcdproc_max_connect_errors) {
	lcdproc_warn("Start client procedure. Error counter: ($lcdproc_connect_errors)");
	sleep(1);
	$lcd = fsockopen(LCDPROC_HOST, LCDPROC_PORT, $errno, $errstr, 10);
	stream_set_timeout($lcd, 0 , 25000); // Sets the socket timeout as 25ms
	if (!$lcd) {
		lcdproc_warn("Failed to connect to LCDd process $errstr ($errno)");
		$lcdproc_connect_errors++;
	} else {
		/* Allow the script to run forever (0) */
		set_time_limit(0);
		build_interface($lcd);
		loop_status($lcd);
		fclose($lcd);
	}
}
if ($lcdproc_connect_errors >= $lcdproc_max_connect_errors) {
	lcdproc_warn("Too many errors, the client ends.");
}
?>
