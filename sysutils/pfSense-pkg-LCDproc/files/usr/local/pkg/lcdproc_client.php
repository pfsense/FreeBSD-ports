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

function get_cpufrequency() {
	$cpufreqs = "";
	exec("/sbin/sysctl -n dev.cpu.0.freq_levels", $cpufreqs);
	$cpufreqs = explode(" ", trim($cpufreqs[0]));
	$maxfreq = explode("/", $cpufreqs[0]);
	$maxfreq = $maxfreq[0];
	$curfreq = "";
	exec("/sbin/sysctl -n dev.cpu.0.freq", $curfreq);
	$curfreq = trim($curfreq[0]);
	$status = "$curfreq\/$maxfreq Mhz";
	return($status);
}

function get_cpufrequency_perc() {
	$cpufreqs = "";
	exec("/sbin/sysctl -n dev.cpu.0.freq_levels", $cpufreqs);
	$cpufreqs = explode(" ", trim($cpufreqs[0]));
	$maxfreq = explode("/", $cpufreqs[0]);
	$maxfreq = $maxfreq[0];
	$curfreq = "";
	exec("/sbin/sysctl -n dev.cpu.0.freq", $curfreq);
	$curfreq = trim($curfreq[0]);
	$status = $curfreq/$maxfreq * 100;
	return($status);
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

function get_traffic_stats(&$in_data, &$out_data){
	global $config;
	global $traffic_last_ugmt, $traffic_last_ifin, $traffic_last_ifout;
	$lcdproc_screen_config = $config['installedpackages']['lcdprocscreens']['config'][0];
	/* read the configured interface */
	$ifnum = $lcdproc_screen_config['scr_traffic_interface'];
	/* get the real interface name (code from ifstats.php)*/
	$realif = get_real_interface($ifnum);
	if (!$realif) {
		$realif = $ifnum;
	}
	// Need for IPSec case interface.
	/* get the interface stats (code from ifstats.php)*/
	$ifinfo = pfSense_get_interface_stats($realif);
	/* get the current time (code from ifstats.php)*/
	$temp = gettimeofday();
	$timing = (double)$temp["sec"] + (double)$temp["usec"] / 1000000.0;
	/* calculate the traffic stats */
	$deltatime = $timing - $traffic_last_ugmt;
	$in_data = "IN:  " . formatSpeedBits(((double)$ifinfo['inbytes']-$traffic_last_ifin)/$deltatime);
	$out_data = "OUT: " . formatSpeedBits(((double)$ifinfo['outbytes']-$traffic_last_ifout)/$deltatime);
	$traffic_last_ugmt = $timing;
	$traffic_last_ifin = (double)$ifinfo['inbytes'];
	$traffic_last_ifout = (double)$ifinfo['outbytes'];
}

function formatSpeedBits($speed) {
	/* format speed in bits/sec, input: bytes/sec
	Code from: graph.php ported to PHP */
	if ($speed < 125000) {
		return sprintf("%5.1f Kbps", $speed / 125);
	}
	if ($speed < 125000000) {
		return sprintf("%5.1f Mbps", $speed / 125000);
	}
	return sprintf("%5.1f Gbps", $speed / 125000000);
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
	$refresh_frequency = get_lcdpanel_refresh_frequency() * 8;

	$lcd_cmds = array();
	$lcd_cmds[] = "hello";
	$lcd_cmds[] = "client_set name pfSense";

	/* process screens to display */
	if (is_array($lcdproc_screens_config)) {
		foreach ($lcdproc_screens_config as $name => $screen) {
			if ($screen == "on" || $screen == "yes" ) {
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
				}
				add_summary_declaration($lcd_cmds, $name);
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
	$i = 1;
	while ($i) {
		/* prepare the summary data */
		if ($lcdpanel_height >= "4") {
			$summary_states = explode("/", get_pfstate());
			$lcd_summary_data = sprintf("%02d%% %02d%% %6d", cpu_usage(), mem_usage(), $summary_states[0]);
			if ($lcdpanel_width > "16") {
				$lcd_summary_data = $lcd_summary_data . sprintf(" %3d%%", get_cpufrequency_perc());
			}
		} else {
			$lcd_summary_data = "";
		}

		$lcd_cmds = array();

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
					get_traffic_stats($in_data, $out_data);
					$lcd_cmds[] = "widget_set $name title_wdgt 1 1 \"{$in_data}\"";
					$lcd_cmds[] = "widget_set $name text_wdgt 1 2 \"{$out_data}\"";
					break;
			}
			if ($name != "scr_traffic_interface") {
				$widget_counter++;
				add_summary_values($lcd_cmds, $name, $lcd_summary_data);
			}
		}
		if (send_lcd_commands($lcd, $lcd_cmds)) {
			$lcdproc_connect_errors = 0; // Reset the error counter
		} else {
			//an error occurred
			return;
		}
		if (($refresh_frequency * $widget_counter) > 5) {
			sleep(5);
		} else {
			sleep($refresh_frequency * $widget_counter);
		}
		$i++;
	}
}

/* Initialize the wan traffic counters */
$traffic_last_ugmt = 0;
$traffic_last_ifin = 0;
$traffic_last_ifout = 0;
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
