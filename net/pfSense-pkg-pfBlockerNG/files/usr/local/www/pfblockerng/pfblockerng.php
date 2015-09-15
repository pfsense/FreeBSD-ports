<?php
/*
	pfBlockerNG.php

	pfBlockerNG
	Copyright (C) 2015 BBcan177@gmail.com
	All rights reserved.

	Based upon pfBlocker by
	Copyright (C) 2011-2012 Marcello Coutinho
	All rights reserved.

	Hour Schedule Convertor code by
	Snort Package
	Copyright (c) 2015 Bill Meeks

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

require_once("util.inc");
require_once("functions.inc");
require_once("pkg-utils.inc");
require_once("globals.inc");
require_once("services.inc");

// Call Include File and Collect updated Global Settings
if (in_array($argv[1], array( 'update','dc','uc','gc','cron' ))) {
	require_once("/usr/local/pkg/pfblockerng/pfblockerng.inc");
	pfb_global();
}


// IPv6 Range to CIDR function used courtesey from:
// https://github.com/stilez/pfsense-leases/blob/50cc0fa81dba5fe91bcddaea016c245d1b8479cc/etc/inc/util.inc
function ip_range_to_subnet_array_temp($ip1, $ip2) {

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

// Set php Memory Limit
$uname = posix_uname();
if ($uname['machine'] == "amd64") {
	ini_set('memory_limit', '256M');
}

function pfb_update_check($header_url, $list_url, $url_format, $pfbfolder) {
	global $pfb;
	$pfb['cron_update'] = FALSE;

	if ($url_format == "rsync" || $url_format == "html") {
		$log = "[ {$header_url} ]\n  Skipping timestamp query\n";
		pfb_logger("{$log}","1");
		$pfb['cron_update'] = TRUE;
	}

	switch ($url_format) {
		case "gz":
		case "gz_2":
		case "gz_lg":
		case "et":
			$type = '.gz';
			break;
		case "zip":
		case "xlsx":
			$type = '.zip';
			break;
		case "txt":
			$type = '.orig';
			break;
		case "html":
		case "block":
			$type = '.raw';
			break;
	}

	$log = "[ {$header_url} ]\n";
	pfb_logger("{$log}","1");
	$host = @parse_url($list_url);
	$local_file = "{$pfb['origdir']}/{$header_url}{$type}";
	if (file_exists($local_file)) {
		// Determine if URL is Remote or Local
		if ($host['host'] == "127.0.0.1" || $host['host'] == $pfb['iplocal'] || empty($host['host'])) {
			$remote_tds = gmdate ("D, d M Y H:i:s T", filemtime($list_url));
		} else {
			$remote_tds = @implode(preg_grep("/Last-Modified/", get_headers($list_url)));
			$remote_tds = preg_replace("/^Last-Modified: /","", $remote_tds);
		}

		$log = "  Remote timestamp: {$remote_tds}\n";
		pfb_logger("{$log}","1");
		$local_tds = gmdate ("D, d M Y H:i:s T", filemtime($local_file));
		$log = "  Local  timestamp: {$local_tds}\n";
		pfb_logger("{$log}","1");
		if ("{$remote_tds}" != "{$local_tds}") {
			$pfb['cron_update'] = TRUE;
		} else {
			$log = "  Remote file unchanged. Download Terminated\n";
			pfb_logger("{$log}","1");
			$pfb['cron_update'] = FALSE;
		}
	} else {
		$pfb['cron_update'] = TRUE;
	}

	if ($pfb['cron_update']) {
		// Trigger CRON Process if Updates are Found.
		$pfb['update_cron'] = TRUE;

		$log = "  Updates Found\n";
		pfb_logger("{$log}","1");
		unlink_if_exists($pfbfolder . '/' . $header_url . '.txt');
	}
}

if ($argv[1] == 'update') {
	sync_package_pfblockerng("cron");
}

if ($argv[1] == 'dc') {
	// (Options - 'bu' Binary Update for Reputation/Alerts Page, 'all' for Country update and 'bu' options.
	if ($pfb['cc'] == "") {
		exec("/bin/sh /usr/local/pkg/pfblockerng/geoipupdate.sh all >> {$pfb['geolog']} 2>&1");
	} else {
		exec("/bin/sh /usr/local/pkg/pfblockerng/geoipupdate.sh bu >> {$pfb['geolog']} 2>&1");
	}
	pfblockerng_uc_countries();
	pfblockerng_get_countries();

	// Remove Original Maxmind Database Files
	@unlink_if_exists("{$pfb['dbdir']}/GeoIPCountryCSV.zip");
	@unlink_if_exists("{$pfb['dbdir']}/GeoIPCountryWhois.csv");
	@unlink_if_exists("{$pfb['dbdir']}/GeoIPv6.csv");
	@unlink_if_exists("{$pfb['dbdir']}/country_continent.csv");
}

if ($argv[1] == 'uc') {
	pfblockerng_uc_countries();
}

if ($argv[1] == 'gc') {
	pfblockerng_get_countries();
}

if ($argv[1] == 'cron') {

	// Call Base Hour converter
	$pfb_sch = pfb_cron_base_hour();

	$hour = date('G');
	$dow  = date('N');
	$pfb['update_cron'] = FALSE;
	$log = " CRON  PROCESS  START [ NOW ]\n";
	pfb_logger("{$log}","1");

	$list_type = array ("pfblockernglistsv4" => "_v4", "pfblockernglistsv6" => "_v6");
	foreach ($list_type as $ip_type => $vtype) {
		if ($config['installedpackages'][$ip_type]['config'] != "") {
			foreach ($config['installedpackages'][$ip_type]['config'] as $list) {
				if (is_array($list['row']) && $list['action'] != "Disabled") {
					foreach ($list['row'] as $row) {
						if ($row['url'] != "" && $row['state'] != "Disabled") {

							if ($vtype == "_v4") {
								$header_url = "{$row['header']}";
							} else {
								$header_url = "{$row['header']}_v6";
							}

							// Determine Folder Location for Alias (return array $pfbarr)
							pfb_determine_list_detail($list['action'], "", "", "");
							$pfbfolder = $pfbarr['folder'];

							$list_cron = $list['cron'];
							$list_url = $row['url'];
							$header_dow = $list['dow'];
							$url_format = $row['format'];

							// Bypass update if state is defined as "Hold" and list file exists
							if (file_exists($pfbfolder . '/' . $header_url . '.txt') && $row['state'] == "Hold") {
								continue;
							}

							// Check if List file exists, if not found run Update
							if (!file_exists($pfbfolder . '/' . $header_url . '.txt')) {
								$log = "  Updates Found\n";
								pfb_logger("{$log}","1");
								$pfb['update_cron'] = TRUE;
								continue;
							}

							switch ($list_cron) {
								case "EveryDay":
									if ($hour == $pfb['24hour']) {
										pfb_update_check($header_url, $list_url, $url_format, $pfbfolder);
									}
									break;
								case "Weekly":
									if ($hour == $pfb['24hour'] && $dow == $header_dow) {
										pfb_update_check($header_url, $list_url, $url_format, $pfbfolder);
									}
									break;
								default:
									if ($pfb['interval'] == "1" || in_array($hour, $pfb_sch)) {
										pfb_update_check($header_url, $list_url, $url_format, $pfbfolder);
									}
									break;
							}
						}
					}
				}
			}
		}
	}

	// If Continents are Defined, continue with Update Process to determine if further changes are required.
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

	if (!$pfb['update_cron']) {
		foreach ($continents as $continent => $pfb_alias) {
			if (is_array($config['installedpackages']['pfblockerng' . strtolower(preg_replace('/ /','',$continent))]['config'])) {
				$continent_config = $config['installedpackages']['pfblockerng' . strtolower(preg_replace('/ /','',$continent))]['config'][0];
				if ($continent_config['action'] != "Disabled" && $pfb['enable'] == "on") {
					$pfb['update_cron'] = TRUE;
					break;
				}
			}
		}
	}

	if ($pfb['update_cron']) {
		sync_package_pfblockerng("cron");
	} else {
		sync_package_pfblockerng("noupdates");
		$log = "\n  No Updates required.\n CRON  PROCESS  ENDED\n UPDATE PROCESS ENDED\n";
		pfb_logger("{$log}","1");
	}

	// Call Log Mgmt Function
	// If Update GUI 'Manual view' is selected. Last output will be missed. So sleep for 5 secs.
	sleep(5);
	pfb_log_mgmt();
}


// Function to process the downloaded Maxmind Database and format into Continent txt files.
function pfblockerng_uc_countries() {
	global $g,$pfb;

	$maxmind_cont	= "{$pfb['dbdir']}/country_continent.csv";
	$maxmind_cc4	= "{$pfb['dbdir']}/GeoIPCountryWhois.csv";
	$maxmind_cc6	= "{$pfb['dbdir']}/GeoIPv6.csv";
	
	// Create Folders if not Exist
	$folder_array = array ("{$pfb['dbdir']}","{$pfb['logdir']}","{$pfb['ccdir']}");
	foreach ($folder_array as $folder) {
		safe_mkdir ("{$folder}",0755);
	}

	$now = date("m/d/y G:i:s", time());
	$log = "Country Code Update Start - [ NOW ]\n\n";
	print "Country Code Update Start - [ $now ]\n\n";
	pfb_logger("{$log}","3");

	if (!file_exists($maxmind_cont) || !file_exists($maxmind_cc4) || !file_exists($maxmind_cc6)) {
		$log = " [ MAXMIND UPDATE FAIL, CSV Missing, using Previous Country Code Database \n";
		print $log;
		pfb_logger("{$log}","3");
		return;
	}

	// Save Date/Time Stamp to MaxMind version file
	$maxmind_ver	= "MaxMind GeoLite Date/Time Stamps \n\n";
	$remote_tds	= @implode(preg_grep("/Last-Modified/", get_headers("http://geolite.maxmind.com/download/geoip/database/GeoIPCountryCSV.zip")));
	$maxmind_ver	.= "MaxMind_v4 \t" . $remote_tds . "\n";
	$local_tds	= @gmdate ("D, d M Y H:i:s T", filemtime($maxmind_cc4));
	$maxmind_ver	.= "Local_v4 \tLast-Modified: " . $local_tds . "\n\n";
	$remote_tds	= @implode(preg_grep("/Last-Modified/", get_headers("http://geolite.maxmind.com/download/geoip/database/GeoIPv6.csv.gz")));
	$maxmind_ver	.= "MaxMind_v6 \t" . $remote_tds . "\n";
	$local_tds	= @gmdate ("D, d M Y H:i:s T", filemtime($maxmind_cc6));
	$maxmind_ver	.= "Local_v6 \tLast-Modified: " . $local_tds . "\n";
	$maxmind_ver	.= "\nThese Timestamps should *match* \n";
	@file_put_contents("{$pfb['logdir']}/maxmind_ver", $maxmind_ver);

	// Collect ISO Codes for Each Continent
	$log = "Processing Continent Data\n";
	print $log;
	pfb_logger("{$log}","3");

	$cont_array = array ( array($AF),array($AS),array($EU),array($NA),array($OC),array($SA),array($AX));
	if (($handle = fopen("{$maxmind_cont}",'r')) !== FALSE) {
		while (($cc = fgetcsv($handle)) !== FALSE) {

			$cc_key = $cc[0];
			$cont_key = $cc[1];
			switch ($cont_key) {
				case "AF":
					$cont_array[0]['continent'] = "Africa";
					$cont_array[0]['iso'] .= "{$cc_key},";
					$cont_array[0]['file4'] = "{$pfb['ccdir']}/Africa_v4.txt";
					$cont_array[0]['file6'] = "{$pfb['ccdir']}/Africa_v6.txt";
					break;
				case "AS":
					$cont_array[1]['continent'] = "Asia";
					$cont_array[1]['iso'] .= "{$cc_key},";
					$cont_array[1]['file4'] = "{$pfb['ccdir']}/Asia_v4.txt";
					$cont_array[1]['file6'] = "{$pfb['ccdir']}/Asia_v6.txt";
					break;
				case "EU":
					$cont_array[2]['continent'] = "Europe";
					$cont_array[2]['iso'] .= "{$cc_key},";
					$cont_array[2]['file4'] = "{$pfb['ccdir']}/Europe_v4.txt";
					$cont_array[2]['file6'] = "{$pfb['ccdir']}/Europe_v6.txt";
					break;
				case "NA":
					$cont_array[3]['continent'] = "North America";
					$cont_array[3]['iso'] .= "{$cc_key},";
					$cont_array[3]['file4'] = "{$pfb['ccdir']}/North_America_v4.txt";
					$cont_array[3]['file6'] = "{$pfb['ccdir']}/North_America_v6.txt";
					break;
				case "OC":
					$cont_array[4]['continent'] = "Oceania";
					$cont_array[4]['iso'] .= "{$cc_key},";
					$cont_array[4]['file4'] = "{$pfb['ccdir']}/Oceania_v4.txt";
					$cont_array[4]['file6'] = "{$pfb['ccdir']}/Oceania_v6.txt";
					break;
				case "SA":
					$cont_array[5]['continent'] = "South America";
					$cont_array[5]['iso'] .= "{$cc_key},";
					$cont_array[5]['file4'] = "{$pfb['ccdir']}/South_America_v4.txt";
					$cont_array[5]['file6'] = "{$pfb['ccdir']}/South_America_v6.txt";
					break;
			}
		}
	}
	unset($cc);
	fclose($handle);

	// Add Maxmind Anonymous Proxy and Satellite Providers to array
	$cont_array[6]['continent']	= "Proxy and Satellite";
	$cont_array[6]['iso']		= "A1,A2";
	$cont_array[6]['file4'] 	= "{$pfb['ccdir']}/Proxy_Satellite_v4.txt";
	$cont_array[6]['file6'] 	= "{$pfb['ccdir']}/Proxy_Satellite_v6.txt";

	// Collect Country ISO data and sort to Continent arrays (IPv4 and IPv6)
	foreach (array("4", "6") as $type) {
		$log = "Processing ISO IPv{$type} Continent/Country Data\n";
		print $log;
		pfb_logger("{$log}","3");

		if ($type == "4") {
			$maxmind_cc = "{$pfb['dbdir']}/GeoIPCountryWhois.csv";
		} else {
			$maxmind_cc = "{$pfb['dbdir']}/GeoIPv6.csv";
		}
		$iptype = "ip{$type}";
		$filetype = "file{$type}";

		if (($handle = fopen("{$maxmind_cc}",'r')) !== FALSE) {
			while (($cc = fgetcsv($handle)) !== FALSE) {
				$cc_key		= $cc[4];
				$country_key	= $cc[5];
				$a_cidr		= implode(",", ip_range_to_subnet_array_temp($cc[0],$cc[1]));
				$counter = 0;
				foreach ($cont_array as $iso) {
					if (preg_match("/\b$cc_key\b/", $iso['iso'])) {
						$cont_array[$counter][$cc_key][$iptype] .= $a_cidr . ",";
						$cont_array[$counter][$cc_key]['country'] = $country_key;
						continue;
					}
					$counter++;
				}
			}
		}
		unset($cc);
		fclose($handle);

		// Build Continent Files
		$counter = 0;
		foreach ($cont_array as $iso) {
			$header		= "";
			$pfb_file	= "";
			$iso_key	= "";
			$header		.= "# Generated from MaxMind Inc. on: " . date("m/d/y G:i:s", time()) . "\n";
			$header		.= "# Continent IPv{$type}: " . $cont_array[$counter]['continent'] . "\n";
			$pfb_file	= $cont_array[$counter][$filetype];
			$iso_key	= array_keys($iso);
			foreach ($iso_key as $key) {
				if (preg_match("/[A-Z]{2}|A1|A2/", $key)) {
					$header .= "# Country: " . $iso[$key]['country'] . "\n";
					$header .= "# ISO Code: " . $key . "\n";
					$header .= "# Total Networks: " . substr_count($iso[$key][$iptype], ",") . "\n";
					$header .= str_replace(",", "\n", $iso[$key][$iptype]);
					$iso[$key][$iptype] = "";
				}
			}
			$counter++;
			@file_put_contents($pfb_file, $header, LOCK_EX);
		}
	}
}


// Function to process Continent txt files and create Country ISO files and to Generate GUI XML files.
function pfblockerng_get_countries() {
	global $g,$pfb;

	$files = array (	"Africa"		=> "{$pfb['ccdir']}/Africa_v4.txt",
				"Asia"			=> "{$pfb['ccdir']}/Asia_v4.txt",
				"Europe"		=> "{$pfb['ccdir']}/Europe_v4.txt",
				"North America"		=> "{$pfb['ccdir']}/North_America_v4.txt",
				"Oceania"		=> "{$pfb['ccdir']}/Oceania_v4.txt",
				"South America"		=> "{$pfb['ccdir']}/South_America_v4.txt",
				"Proxy and Satellite"	=> "{$pfb['ccdir']}/Proxy_Satellite_v4.txt"
				);

	// Collect Data to generate new continent XML Files.
	$log = "Building pfBlockerNG XML Files \n";
	print $log;
	pfb_logger("{$log}","3");

	foreach ($files as $cont => $file) {
		// Process the following for IPv4 and IPv6
		foreach (array("4", "6") as $type) {
			$log = "IPv{$type} " . $cont . "\n";
			print $log;
			pfb_logger("{$log}","3");

			if ($type == "6")
				$file = preg_replace("/v4/", "v6", $file);
			$convert		= explode("\n", file_get_contents($file));
			$cont_name		= preg_replace("/ /", "", $cont);
			$cont_name_lower	= strtolower($cont_name);
			$active			= array("$cont" => '<active/>');
			$lastkey		= count ($convert) - 1;
			$pfb['complete']	= FALSE;
			$keycount		= 1;
			$total			= 0;

			foreach ($convert as $line) {
				if (preg_match("/#/",$line)) {
					if ($pfb['complete']) {
						${'coptions' . $type}[] = $country . '-' . $isocode . ' ('. $total .') ' . ' </name><value>' . $isocode . '</value></option>';
						// Only collect IPv4 for Reputation Tab 
						if ($type == "4")
							$roptions4[] = $country . '-' . $isocode . ' ('. $total .') ' . ' </name><value>' . $isocode . '</value></option>';

						// Save ISO data
						@file_put_contents($pfb['ccdir'] . '/' . $isocode . '_v' . $type . '.txt', $xml_data, LOCK_EX);

						// Clear variables and restart Continent collection process
						unset($total, $xml_data);
						$pfb['complete'] = FALSE;
					}
					if (preg_match("/Total Networks: 0/", $line)) { continue;}	// Don't Display Countries with Null Data
					if (preg_match("/Country:\s(.*)/",$line, $matches)) { $country = $matches[1];}
					if (preg_match("/ISO Code:\s(.*)/",$line, $matches)) { $isocode = $matches[1];}
				}
				elseif (!preg_match("/#/",$line)) {
					$total++;
					if (!empty($line))
						$xml_data .= $line . "\n";
					$pfb['complete'] = TRUE;
				}

				// Save last EOF ISO IP data
				if ($keycount == $lastkey) {
					if (preg_match("/Total Networks: 0/", $line)) { continue;}	// Dont Display Countries with Null Data
					${'coptions' . $type}[] = $country . '-' . $isocode . ' ('. $total .') ' . ' </name><value>' . $isocode . '</value></option>';
					if ($type == "4")
						$roptions4[] = $country . '-' . $isocode . ' ('. $total .') ' . ' </name><value>' . $isocode . '</value></option>';
					@file_put_contents($pfb['ccdir'] . '/' . $isocode . '_v' . $type . '.txt', $xml_data, LOCK_EX);
					unset($total, $xml_data);
				}
				$keycount++;
			}
			unset ($ips, $convert);

			// Sort IP Countries alphabetically and build XML <option> data for Continents tab
			if (!empty (${'coptions' . $type})) {
				sort(${'coptions' . $type}, SORT_STRING);
				${'ftotal' . $type} = count(${'coptions' . $type});
				$count = 1;
				${'options' . $type} = "";

				foreach (${'coptions' . $type} as $option) {
					if ($count == 1) { ${'options' . $type} .= "\t" . '<option><name>' . $option . "\n"; $count++; continue;}
					if (${'ftotal' . $type} == $count) {
						${'options' . $type} .= "\t\t\t\t" . '<option><name>' . $option;
					} else {
						${'options' . $type} .= "\t\t\t\t" . '<option><name>' . $option . "\n";
					}
					$count++;
				}
			}
			unset (${'coptions' . $type});
		}

$xml = <<<EOF
<?xml version="1.0" encoding="utf-8" ?>
<!DOCTYPE packagegui SYSTEM "./schema/packages.dtd">
<?xml-stylesheet type="text/xsl" href="./xsl/package.xsl"?>
<packagegui>
	<copyright>
	<![CDATA[
/* \$Id\$ */
/* ========================================================================== */
/*
	pfblockerng_{$cont_name}.xml

	pfBlockerNG
	Copyright (C) 2015 BBcan177@gmail.com
	All rights reserved.

	Based upon pfblocker for pfSense
	Copyright (C) 2011 Marcello Coutinho
	All rights reserved.
*/
/* ========================================================================== */
/*
	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:


	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.


	THIS SOFTWARE IS PROVIDED ``AS IS`` AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
/* ========================================================================== */
]]>
	</copyright>
	<description>Describe your package here</description>
	<requirements>Describe your package requirements here</requirements>
	<faq>Currently there are no FAQ items provided.</faq>
	<name>pfblockerng{$cont_name_lower}</name>
	<version>1.0</version>
	<title>pfBlockerNG: {$cont}</title>
	<include_file>/usr/local/pkg/pfblockerng/pfblockerng.inc</include_file>
	<addedit_string>pfBlockerNG: Save {$cont} settings</addedit_string>
	<menu>
		<name>pfBlockerNG: {$cont_name}</name>
		<tooltiptext>Configure pfBlockerNG</tooltiptext>
		<section>Firewall</section>
		<url>pkg_edit.php?xml=pfblockerng_{$cont_name_lower}.xml&amp;id=0</url>
	</menu>
		<tabs>
		<tab>
			<text>General</text>
			<url>/pkg_edit.php?xml=pfblockerng.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>Update</text>
			<url>/pfblockerng/pfblockerng_update.php</url>
		</tab>
		<tab>
			<text>Alerts</text>
			<url>/pfblockerng/pfblockerng_alerts.php</url>
		</tab>
		<tab>
			<text>Reputation</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_reputation.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>IPv4</text>
			<url>/pkg.php?xml=/pfblockerng/pfblockerng_v4lists.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>IPv6</text>
			<url>/pkg.php?xml=/pfblockerng/pfblockerng_v6lists.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>Top 20</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_top20.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>Africa</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Africa.xml&amp;id=0</url>
			{$active['Africa']}
		</tab>
		<tab>
			<text>Asia</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Asia.xml&amp;id=0</url>
			{$active['Asia']}
		</tab>
		<tab>
			<text>Europe</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Europe.xml&amp;id=0</url>
			{$active['Europe']}
		</tab>
		<tab>
			<text>N.A.</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_NorthAmerica.xml&amp;id=0</url>
			{$active['North America']}
		</tab>
		<tab>
			<text>Oceania</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Oceania.xml&amp;id=0</url>
			{$active['Oceania']}
		</tab>
		<tab>
			<text>S.A.</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_SouthAmerica.xml&amp;id=0</url>
			{$active['South America']}
		</tab>
		<tab>
			<text>P.S.</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_ProxyandSatellite.xml&amp;id=0</url>
			{$active['Proxy and Satellite']}
		</tab>
		<tab>
			<text>Logs</text>
			<url>/pfblockerng/pfblockerng_log.php</url>
		</tab>
		<tab>
			<text>Sync</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_sync.xml&amp;id=0</url>
		</tab>
		</tabs>
	<fields>
		<field>
			<name><![CDATA[Continent {$cont}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (Geolite Data by MaxMind Inc. - ISO 3166)]]></name>
			<type>listtopic</type>
		</field>
		<field>
			<fielddescr>LINKS</fielddescr>
			<description><![CDATA[<a href="/firewall_aliases.php">Firewall Alias</a> &nbsp;&nbsp;&nbsp;
				<a href="/firewall_rules.php">Firewall Rules</a> &nbsp;&nbsp;&nbsp; <a href="diag_logs_filter.php">Firewall Logs</a>]]>
			</description>
			<type>info</type>
		</field>
		<field>
			<fieldname>countries4</fieldname>
			<fielddescr><![CDATA[<strong><center>Countries</center></strong><br />
				<center>Use CTRL + CLICK to unselect countries</center>]]>
			</fielddescr>
			<type>select</type>
			<options>
			${'options4'}
			</options>
			<size>${'ftotal4'}</size>
			<multiple/>

EOF;

// Adjust combinefields variable if IPv6 is empty.
if (!empty (${'options6'})) {
	$xml .= <<<EOF
			<description><![CDATA[<center><br />IPv4 Countries</center>]]></description>
			<usecolspan2/>
			<combinefields>begin</combinefields>
		</field>

EOF;
} else {
	$xml .= <<<EOF
			<description><![CDATA[<br />IPv4 Countries]]></description>
		</field>

EOF;
}

// Skip IPv6 when Null data found
if (!empty (${'options6'})) {
	$xml .= <<<EOF
		<field>
			<fieldname>countries6</fieldname>
			<description><![CDATA[<br /><center>IPv6 Countries</center>]]></description>
			<type>select</type>
			<options>
			${'options6'}
			</options>
			<size>${'ftotal6'}</size>
			<multiple/>
			<usecolspan2/>
			<dontdisplayname/>
			<combinefields>end</combinefields>
		</field>

EOF;
}

$xml .= <<<EOF
		<field>
			<fielddescr>List Action</fielddescr>
			<description><![CDATA[<br />Default: <strong>Disabled</strong><br /><br />
				Select the <strong>Action</strong> for Firewall Rules on lists you have selected.<br /><br />
				<strong><u>'Disabled' Rules:</u></strong> Disables selection and does nothing to selected Alias.<br /><br />

				<strong><u>'Deny' Rules:</u></strong><br />
				'Deny' rules create high priority 'block' or 'reject' rules on the stated interfaces. They don't change the 'pass' rules on other
				interfaces. Typical uses of 'Deny' rules are:<br />
				<ul><li><strong>Deny Both</strong> - blocks all traffic in both directions, if the source or destination IP is in the block list</li>
				<li><strong>Deny Inbound/Deny Outbound</strong> - blocks all traffic in one direction <u>unless</u> it is part of a session started by
				traffic sent in the other direction. Does not affect traffic in the other direction. </li>
				<li>One way 'Deny' rules can be used to selectively block <u>unsolicited</u> incoming (new session) packets in one direction, while
				still allowing <u>deliberate</u> outgoing sessions to be created in the other direction.</li></ul>
				<strong><u>'Permit' Rules:</u></strong><br />
				'Permit' rules create high priority 'pass' rules on the stated interfaces. They are the opposite of Deny rules, and don't create
				any 'blocking' effect anywhere. They have priority over all Deny rules. Typical uses of 'Permit' rules are:<br />
				<ul><li><strong>To ensure</strong> that traffic to/from the listed IPs will <u>always</u> be allowed in the stated directions. They
				override <u>almost all other</u> Firewall rules on the stated interfaces.</li>
				<li><strong>To act as a whitelist</strong> for Deny rule exceptions, for example if a large IP range or pre-created blocklist blocks a
				few IPs that should be accessible.</li></ul>
				<strong><u>'Match' Rules:</u></strong><br />
				'Match' or 'Log' only the traffic on the stated interfaces. This does not Block or Reject. It just Logs the traffic.
				<ul><li><strong>Match Both</strong> - Matches all traffic in both directions, if the source or destination IP is in the list.</li>
				<li><strong>Match Inbound/Match Outbound</strong> - Matches all traffic in one direction only.</li></ul>
				<strong><u>'Alias' Rules:</u></strong><br />
				<strong>'Alias'</strong> rules create an <a href="/firewall_aliases.php">alias</a> for the list (and do nothing else).
				This enables a pfBlockerNG list to be used by name, in any firewall rule or pfSense function, as desired.
				<ul><li><strong>Options &nbsp;&nbsp; - Alias Deny,&nbsp; Alias Permit,&nbsp; Alias Match,&nbsp; Alias Native</strong></li><br />
				<li>'Alias Deny' can use De-Duplication and Reputation Processes if configured.</li><br />
				<li>'Alias Permit' and 'Alias Match' will be saved in the Same folder as the other Permit/Match Auto-Rules</li><br />
				<li>'Alias Native' lists are kept in their Native format without any modifications.</li></ul>
				<strong>When using 'Alias' rules, change (pfB_) to ( pfb_ ) in the beginning of rule description and use the 'Exact' spelling of
				the Alias (no trailing Whitespace)</strong> Custom 'Alias' rules with 'pfB_  xxx' description will be removed by package if
				using Auto Rule Creation.<br /><br /><strong>Tip</strong>: You can create the Auto Rules and remove "<u>auto rule</u>" from the Rule
				Descriptions, then disable Auto Rules. This method will 'KEEP' these rules from being 'Deleted' which will allow editing for a Custom
				Alias Configuration<br />]]>
			</description>
			<fieldname>action</fieldname>
			<type>select</type>
			<options>
				<option><name>Disabled</name><value>Disabled</value></option>
				<option><name>Deny Inbound</name><value>Deny_Inbound</value></option>
				<option><name>Deny Outbound</name><value>Deny_Outbound</value></option>
				<option><name>Deny Both</name><value>Deny_Both</value></option>
				<option><name>Permit Inbound</name><value>Permit_Inbound</value></option>
				<option><name>Permit Outbound</name><value>Permit_Outbound</value></option>
				<option><name>Permit Both</name><value>Permit_Both</value></option>
				<option><name>Match Inbound</name><value>Match_Inbound</value></option>
				<option><name>Match Outbound</name><value>Match_Outbound</value></option>
				<option><name>Match Both</name><value>Match_Both</value></option>
				<option><name>Alias Deny</name><value>Alias_Deny</value></option>
				<option><name>Alias Permit</name><value>Alias_Permit</value></option>
				<option><name>Alias Match</name><value>Alias_Match</value></option>
				<option><name>Alias Native</name><value>Alias_Native</value></option>
			</options>
		</field>
		<field>
			<fielddescr>Enable Logging</fielddescr>
			<fieldname>aliaslog</fieldname>
			<description><![CDATA[Default: <strong>Enable</strong><br />
				Select - Logging to Status: System Logs: FIREWALL ( Log )<br />
				This can be overriden by the 'Global Logging' Option in the General Tab.]]>
			</description>
			<type>select</type>
			<options>
				<option><name>Enable</name><value>enabled</value></option>
				<option><name>Disable</name><value>disabled</value></option>
			</options>
		</field>
		<field>
			<name>Advanced Inbound Firewall Rule Settings</name>
			<type>listtopic</type>
		</field>
		<field>
			<type>info</type>
			<description><![CDATA[<font color='red'>Note: </font>In general Auto-Rules are created as follows:<br />
				<ul>Inbound &nbsp;&nbsp;- 'any' port, 'any' protocol and 'any' destination<br />
				Outbound - 'any' port, 'any' protocol and 'any' destination address in the lists</ul>
				Configuring the Adv. Inbound Rule settings, will allow for more customization of the Inbound Auto-Rules.<br />
				<strong>Select the pfSense 'Port' and/or 'Destination' Alias below:</strong>]]>
			</description>
		</field>
		<field>
			<fieldname>autoports</fieldname>
			<fielddescr>Enable Custom Port</fielddescr>
			<type>checkbox</type>
			<enablefields>aliasports</enablefields>
			<usecolspan2/>
			<combinefields>begin</combinefields>
		</field>
		<field>
			<fielddescr>Define Alias</fielddescr>
			<fieldname>aliasports</fieldname>
			<description><![CDATA[<a href="/firewall_aliases.php?tab=port">Click Here to add/edit Aliases</a>
				Do not manually enter port numbers. <br />Do not use 'pfB_' in the Port Alias name.]]>
			</description>
			<size>21</size>
			<type>aliases</type>
			<typealiases>port</typealiases>
			<dontdisplayname/>
			<usecolspan2/>
			<combinefields>end</combinefields>
		</field>
		<field>
			<fieldname>autodest</fieldname>
			<fielddescr>Enable Custom Destination</fielddescr>
			<type>checkbox</type>
			<enablefields>aliasdest,autonot</enablefields>
			<usecolspan2/>
			<combinefields>begin</combinefields>
		</field>
		<field>
			<fieldname>aliasdest</fieldname>
			<description><![CDATA[<a href="/firewall_aliases.php?tab=ip">Click Here to add/edit Aliases</a>
				Do not manually enter Addresses(es). <br />Do not use 'pfB_' in the 'IP Network Type' Alias name.]]>
			</description>
			<size>21</size>
			<type>aliases</type>
			<typealiases>network</typealiases>
			<dontdisplayname/>
			<usecolspan2/>
			<combinefields/>
		</field>
		<field>
			<fielddescr>Invert</fielddescr>
			<fieldname>autonot</fieldname>
			<description><![CDATA[<div style="padding-left: 22px;"><strong>Invert</strong> - Option to invert the sense of the match.<br />
				ie - Not (!) Destination Address(es)</div>]]>
			</description>
			<type>checkbox</type>
			<dontdisplayname/>
			<usecolspan2/>
			<combinefields>end</combinefields>
		</field>
		<field>
			<fielddescr>Custom Protocol</fielddescr>
			<fieldname>autoproto</fieldname>
			<description><![CDATA[<strong>Default: any</strong><br />Select the Protocol used for Inbound Firewall Rule(s).]]></description>
			<type>select</type>
			<options>
				<option><name>any</name><value></value></option>
				<option><name>TCP</name><value>tcp</value></option>
				<option><name>UDP</name><value>udp</value></option>
				<option><name>TCP/UDP</name><value>tcp/udp</value></option>
			</options>
			<size>4</size>
			<default_value></default_value>
		</field>
		<field>
			<name><![CDATA[<center>Click to SAVE Settings and/or Rule Edits. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Changes are Applied via CRON or
				'Force Update'</center>]]></name>
			<type>listtopic</type>
		</field>
	</fields>
	<custom_php_install_command>
		pfblockerng_php_install_command();
	</custom_php_install_command>
	<custom_php_deinstall_command>
		pfblockerng_php_deinstall_command();
	</custom_php_deinstall_command>
	<custom_php_validation_command>
		pfblockerng_validate_input(\$_POST, \$input_errors);
	</custom_php_validation_command>
	<custom_php_resync_config_command>
		global \$pfb;
		\$pfb['save'] = TRUE;
		sync_package_pfblockerng();
	</custom_php_resync_config_command>
</packagegui>
EOF;

		// Update Each Continent XML file.
		@file_put_contents('/usr/local/pkg/pfblockerng/pfblockerng_'.$cont_name.'.xml',$xml,LOCK_EX);

		// Unset Arrays
		unset (${'options4'}, ${'options6'}, $xml);

	}	// End foreach 'Six Continents and Proxy/Satellite' Update XML Process

	// Sort Countries IPv4 Alphabetically and Build XML <option> Data for Reputation Tab (IPv6 not used by ET IQRisk)

	sort($roptions4, SORT_STRING);
	$eoa = count($roptions4);
	$count = 1;
	$etoptions = "";

	foreach ($roptions4 as $option4) {
		if ($count == 1) { $et_options .= "\t" . '<option><name>' . $option4 . "\n"; $count++; continue; }
		if ($eoa == $count) {
			$et_options .= "\t\t\t\t" . '<option><name>' . $option4;
		} else {
			$et_options .= "\t\t\t\t" . '<option><name>' . $option4 . "\n";
		}
		$count++;
	}

// Update pfBlockerNG_Reputation.xml file with Country Code Changes

$xmlrep = <<<EOF
<?xml version="1.0" encoding="utf-8" ?>
<!DOCTYPE packagegui SYSTEM "./schema/packages.dtd">
<?xml-stylesheet type="text/xsl" href="./xsl/package.xsl"?>
<packagegui>
	<copyright>
	<![CDATA[
/* \$Id\$ */
/* ========================================================================== */
/*
	pfBlockerNG_Reputation.xml

	pfBlockerNG
	Copyright (C) 2015 BBcan177@gmail.com
	All rights reserved.

	Based upon pfblocker for pfSense
	Copyright (C) 2011 Marcello Coutinho
	All rights reserved.

*/
/* ========================================================================== */
/*
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
	]]>
	</copyright>
	<description>Describe your package here</description>
	<requirements>Describe your package requirements here</requirements>
	<faq>Currently there are no FAQ items provided.</faq>
	<name>pfblockerngreputation</name>
	<version>1.0</version>
	<title>pfBlockerNG: IPv4 Reputation</title>
	<include_file>/usr/local/pkg/pfblockerng/pfblockerng.inc</include_file>
	<addedit_string>pfBlockerNG: Save Reputation Settings</addedit_string>
	<menu>
		<name>pfBlockerNG</name>
		<tooltiptext>Configure pfblockerNG</tooltiptext>
		<section>Firewall</section>
		<url>pkg_edit.php?xml=pfblockerng.xml&amp;id=0</url>
	</menu>
	<tabs>
		<tab>
			<text>General</text>
			<url>/pkg_edit.php?xml=pfblockerng.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>Update</text>
			<url>/pfblockerng/pfblockerng_update.php</url>
		</tab>
		<tab>
			<text>Alerts</text>
			<url>/pfblockerng/pfblockerng_alerts.php</url>
		</tab>
		<tab>
			<text>Reputation</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_reputation.xml&amp;id=0</url>
			<active/>
		</tab>
		<tab>
			<text>IPv4</text>
			<url>/pkg.php?xml=/pfblockerng/pfblockerng_v4lists.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>IPv6</text>
			<url>/pkg.php?xml=/pfblockerng/pfblockerng_v6lists.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>Top 20</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_top20.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>Africa</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Africa.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>Asia</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Asia.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>Europe</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Europe.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>N.A.</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_NorthAmerica.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>Oceania</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Oceania.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>S.A.</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_SouthAmerica.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>P.S.</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_ProxyandSatellite.xml&amp;id=0</url>
		</tab>
		<tab>
			<text>Logs</text>
			<url>/pfblockerng/pfblockerng_log.php</url>
		</tab>
		<tab>
			<text>Sync</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_sync.xml&amp;id=0</url>
		</tab>
	</tabs>
	<fields>
		<field>
			<name>IPv4 Reputation Preface</name>
			<type>listtopic</type>
		</field>
		<field>
			<fielddescr>LINKS</fielddescr>
			<description><![CDATA[<a href="/firewall_aliases.php">Firewall Alias</a> &nbsp;&nbsp;&nbsp;
				<a href="/firewall_rules.php">Firewall Rules</a> &nbsp;&nbsp;&nbsp; <a href="diag_logs_filter.php">Firewall Logs</a>]]>
			</description>
			<type>info</type>
		</field>
		<field>
			<fielddescr><![CDATA[<strong>Why Reputation Matters:</strong>]]></fielddescr>
			<type>info</type>
			<description><![CDATA[By Enabling '<strong>Reputation</strong>', each Blocklist will be analyzed for Repeat Offenders in each IP Range.
				<ul>Example: &nbsp;&nbsp; x.x.x.1, x.x.x.2, x.x.x.3, x.x.x.4, x.x.x.5<br />
				No. of <strong> Repeat Offending IPs </strong> [ &nbsp;<strong>5</strong>&nbsp; ], in a Blocklist within the same IP Range.</ul>
				With '<strong>Reputation</strong> enabled, these 5 IPs will be removed and a single
				<strong>x.x.x.0/24</strong> Block is used.<br />
				This will completely Block/Reject this particular range from your Firewall.<br /><br />
				Selecting Blocklists from various Threat Sources will help to highlight Repeat Offending IP Ranges,<br />
				Its Important to select a Broad Range of Blocklists that cover different types of Malicious Activity.<br /><br />
				You *may* experience some False Positives. Add any False Positive IPs manually to the<br />
				<strong>pfBlockerNGSuppress Alias</strong> or use the "+" suppression Icon in the Alerts TAB<br /><br />
				To help mitigate False Positives 'Countries' can be '<strong>Excluded</strong>' from this Process. (Refer to Country Code Settings)
				<br /><br />Enabling <strong>De-Duplication</strong> is highly recommended before utilizing 'Reputation' processes.]]>
			</description>
		</field>
		<field>
			<name>Reputation Settings:</name>
			<type>listtopic</type>
		</field>
		<field>
			<fielddescr><![CDATA[<br /><strong>Individual List Reputation</strong><br /><br />]]></fielddescr>
			<type>info</type>
			<description></description>
		</field>
		<field>
			<fielddescr><![CDATA[Enable Max]]></fielddescr>
			<fieldname>enable_rep</fieldname>
			<type>checkbox</type>
			<description><![CDATA[Enables Search for Repeat Offenders in a /24 Range on <strong>Each Individual Blocklist</strong>]]></description>
		</field>
		<field>
			<fielddescr><![CDATA[&nbsp;&nbsp;&nbsp;[ <strong>Max</strong> ] Setting]]></fielddescr>
			<fieldname>p24_max_var</fieldname>
			<description><![CDATA[Default: <strong>5</strong><br />
				Maximum number of Repeat Offenders allowed in a Single IP Range]]></description>
			<type>select</type>
			<options>
				<option><name>5</name><value>5</value></option>
				<option><name>10</name><value>10</value></option>
				<option><name>15</name><value>15</value></option>
				<option><name>20</name><value>20</value></option>
				<option><name>25</name><value>25</value></option>
				<option><name>50</name><value>50</value></option>
			</options>
		</field>
		<field>
			<fielddescr><![CDATA[<br /><strong>Collective List Reputation</strong><br /><br />]]></fielddescr>
			<type>info</type>
			<description></description>
		</field>
		<field>
			<type>info</type>
			<description><![CDATA[Once all Blocklists are Downloaded, these two 'additional' processes <strong>[ pMax ] and [ dMax ]</strong><br />
				Can be used to Further analyze for Repeat Offenders.<br />
				<ul>Analyzing All Blocklists as a Whole:</ul>
				<ul><strong>[ pMax ]</strong> will analyze for Repeat Offenders in each IP Range but will not use the Country Exclusion.<br />
				Default is 50 IPs in any Range. Having 50 Repeat Offenders IPs in any Range will Block the entire Range.<br /><br /></ul>
				<ul><strong>[ dMax ]</strong> will analyze for Repeat Offenders in each IP Range. Country Exclusions will be applied.<br />
				Default is 5 IPs in any Range.</ul>
				Note: <strong>MAX</strong> performs on individual Blocklists, while <strong>pMAX / dMAX</strong>
				perform on all Lists together.<br />]]>
			</description>
		</field>
		<field>
			<fielddescr>Enable pMAX</fielddescr>
			<fieldname>enable_pdup</fieldname>
			<type>checkbox</type>
			<description><![CDATA[Enables Search for Repeat Offenders in All BlockLists, <strong>Without</strong> Country Code Exclusion]]>
			</description>
		</field>
		<field>
			<fielddescr><![CDATA[&nbsp;&nbsp;&nbsp;[ <strong>pMax</strong> ] Setting]]></fielddescr>
			<fieldname>p24_pmax_var</fieldname>
			<description><![CDATA[Default: <strong>50</strong><br />Maximum number of Repeat Offenders]]></description>
			<type>select</type>
			<options>
				<option><name>50</name><value>50</value></option>
				<option><name>25</name><value>25</value></option>
				<option><name>20</name><value>20</value></option>
				<option><name>15</name><value>15</value></option>
				<option><name>10</name><value>10</value></option>
				<option><name>5</name><value>5</value></option>
			</options>
		</field>
		<field>
			<fielddescr>Enable dMAX</fielddescr>
			<fieldname>enable_dedup</fieldname>
			<type>checkbox</type>
			<description><![CDATA[Enables Search for Repeat Offenders in All BlockLists <strong>Using</strong> Country Code Exclusion]]>
			</description>
		</field>
		<field>
			<fielddescr><![CDATA[&nbsp;&nbsp;&nbsp;[ <strong>dMax</strong> ] Setting]]></fielddescr>
			<fieldname>p24_dmax_var</fieldname>
			<description><![CDATA[Default: <strong>5</strong><br />
				Maximum number of Repeat Offenders]]></description>
			<type>select</type>
			<options>
				<option><name>5</name><value>5</value></option>
				<option><name>10</name><value>10</value></option>
				<option><name>15</name><value>15</value></option>
				<option><name>20</name><value>20</value></option>
				<option><name>25</name><value>25</value></option>
				<option><name>50</name><value>50</value></option>
			</options>
		</field>
		<field>
			<name>Country Code Settings</name>
			<type>listtopic</type>
		</field>
		<field>
			<type>info</type>
			<description><![CDATA[When performing Queries for Repeat Offenders, you can choose to <strong>ignore</strong> Repeat Offenders in select
				Countries. The Original Blocklisted IPs remain intact. All other Repeat Offending Country Ranges will be processed.<br /><br />
				Define Repeat Offending Ranges [ <strong>Action</strong> ] Available settings are:<br />
				<ul><strong>Ignore</strong>: Repeat Offenders that are in the 'ccwhite' category will be 'Ignored' (Default)</ul>
				<ul><strong>Block:</strong> Repeat Offenders are set to Block the entire Repeat Offending Range(s)</ul>
				<ul><strong>Match:</strong> Repeat Offenders are added to a 'Match' List which can be used in a Floating Match Rule<br />
				Selecting 'Match' will consume more processing time, so only select this option if you enable Rules for it.</ul>
				'<strong>ccwhite</strong>' are Countries that are Selected to be excluded from the Repeat Offenders Search.<br />
				'<strong>ccblack</strong>' are all other Countries that are not selected.<br /><br />
				To use '<strong>Match</strong>' Lists, Create a new 'Alias'
				and select one of the <strong>Action 'Match'</strong> Formats and<br /> enter the 'Localfile' as:
				<ul>/var/db/pfblockerng/match/matchdedup.txt</ul>]]>
			</description>
		</field>
		<field>
			<fielddescr>ccwhite Action:</fielddescr>
			<fieldname>ccwhite</fieldname>
			<description><![CDATA[Default: <strong>Ignore</strong><br />
				Select the 'Action' format for ccwhite]]>
			</description>
			<type>select</type>
			<options>
				<option><name>Ignore</name><value>ignore</value></option>
				<option><name>Match</name><value>match</value></option>
			</options>
		</field>
		<field>
			<fielddescr>ccblack Action:</fielddescr>
			<fieldname>ccblack</fieldname>
			<description><![CDATA[Default: <strong>Block</strong><br />
				Select the 'Action' format for ccblack]]>
			</description>
			<type>select</type>
			<options>
				<option><name>Block</name><value>block</value></option>
				<option><name>Match</name><value>match</value></option>
			</options>
		</field>
		<field>
			<fielddescr><![CDATA[<br /><strong>IPv4</strong><br />Country Exclusion<br />
				<br />Geolite Data by: <br />MaxMind Inc.&nbsp;&nbsp;(ISO 3166)]]></fielddescr>
			<fieldname>ccexclude</fieldname>
			<description>
				<![CDATA[Select Countries you want to <strong>Exclude</strong> from the Reputation Process.<br />
				<strong>Use CTRL + CLICK to unselect countries</strong>]]>
			</description>
			<type>select</type>
			<options>
			{$et_options}
			</options>
			<size>20</size>
			<multiple/>
		</field>
		<field>
			<name>Emerging Threats IQRISK IPv4 Reputation</name>
			<type>listtopic</type>
		</field>
		<field>
			<fielddescr>Subscription Pro. Blocklist</fielddescr>
			<type>info</type>
			<description><![CDATA[<strong>Emerging Threats IQRisk</strong> is a Subscription Professional Reputation List.<br /><br />
					ET IQRisk Blocklist must be entered in the Lists Tab using the following example:
					<ul>https://rules.emergingthreatspro.com/XXXXXXXXXXXXXXXX/reputation/iprepdata.txt.gz</ul>
					Select the <strong>ET IQRisk'</strong> format. The URL should use the .gz File Type.<br />
					Enter your "ETPRO" code in URL. Further information can be found @
					<a target=_new href='http://emergingthreats.net/solutions/iqrisk-suite/'>ET IQRisk IP Reputation</a><br /><br />
					To use <strong>'Match'</strong> Lists, Create a new 'Alias' and select one of the <strong>
					Action 'Match'</strong> Formats and <br />
					enter the 'Localfile' as: <ul>/var/db/pfblockerng/match/ETMatch.txt</ul>
					ET IQRisk Individual Match Lists can be found in the following folder:<br />
					<ul>/var/db/pfblockerng/ET</ul> ]]>
			</description>
		</field>
		<field>
			<fielddescr>ET IQRisk Header Name</fielddescr>
			<fieldname>et_header</fieldname>
			<type>input</type>
			<description><![CDATA[Enter the 'Header Name' referenced in the IPv4 List TAB for ET IQRisk IPRep.<br />
				This will be used to improve the Alerts TAB reporting for ET IPRep.]]>
			</description>
		</field>
		<field>
			<fielddescr>ET IQRISK BLOCK LISTS</fielddescr>
			<fieldname>etblock</fieldname>
			<description>
				<![CDATA[Select Lists you want to BLOCK.<br />
				<strong>Use CTRL + CLICK to unselect Categories</strong>
				<br /><br />Any Changes will take effect at the Next Scheduled CRON Task]]>
			</description>
			<type>select</type>
			<options>
				<option><name>ET CNC</name><value>ET_Cnc</value></option>
				<option><name>ET BOT</name><value>ET_Bot</value></option>
				<option><name>ET SPAM</name><value>ET_Spam</value></option>
				<option><name>ET DROP</name><value>ET_Drop</value></option>
				<option><name>ET Spyware CNC</name><value>ET_Spywarecnc</value></option>
				<option><name>ET Online Gaming</name><value>ET_Onlinegaming</value></option>
				<option><name>ET DrivebySRC</name><value>ET_Drivebysrc</value></option>
				<option><name>ET Chat Server</name><value>ET_Chatserver</value></option>
				<option><name>ET TOR Node</name><value>ET_Tornode</value></option>
				<option><name>ET Compromised</name><value>ET_Compromised</value></option>
				<option><name>ET P2P</name><value>ET_P2P</value></option>
				<option><name>ET Proxy</name><value>ET_Proxy</value></option>
				<option><name>ET IP Check</name><value>ET_Ipcheck</value></option>
				<option><name>ET Utility</name><value>ET_Utility</value></option>
				<option><name>ET DOS</name><value>ET_DDos</value></option>
				<option><name>ET Scanner</name><value>ET_Scanner</value></option>
				<option><name>ET Brute</name><value>ET_Brute</value></option>
				<option><name>ET Fake AV</name><value>ET_Fakeav</value></option>
				<option><name>ET DYN DNS</name><value>ET_Dyndns</value></option>
				<option><name>ET Undersireable</name><value>ET_Undesireable</value></option>
				<option><name>ET Abuse TLD</name><value>ET_Abusedtld</value></option>
				<option><name>ET SelfSigned SSL</name><value>ET_Selfsignedssl</value></option>
				<option><name>ET Blackhole</name><value>ET_Blackhole</value></option>
				<option><name>ET RAS</name><value>ET_RAS</value></option>
				<option><name>ET P2P CNC</name><value>ET_P2Pcnc</value></option>
				<option><name>ET Shared Hosting</name><value>ET_Sharedhosting</value></option>
				<option><name>ET Parking</name><value>ET_Parking</value></option>
				<option><name>ET VPN</name><value>ET_VPN</value></option>
				<option><name>ET EXE Source</name><value>ET_Exesource</value></option>
				<option><name>ET Mobile CNC</name><value>ET_Mobilecnc</value></option>
				<option><name>ET Mobile Spyware</name><value>ET_Mobilespyware</value></option>
				<option><name>ET Skype Node</name><value>ET_Skypenode</value></option>
				<option><name>ET Bitcoin</name><value>ET_Bitcoin</value></option>
				<option><name>ET DOS Attack</name><value>ET_DDosattack</value></option>
				<option><name>Unknown</name><value>ET_Unknown</value></option>
			</options>
			<size>35</size>
			<multiple/>
		</field>
		<field>
			<fielddescr>ET IQRISK Match LISTS</fielddescr>
			<fieldname>etmatch</fieldname>
			<description>
				<![CDATA[Select Lists you want to MATCH.<br />
				<strong>Use CTRL + CLICK to unselect Categories</strong>
				<br /><br />Any Changes will take effect at the Next Scheduled CRON Task]]>
			</description>
			<type>select</type>
			<options>
				<option><name>ET CNC</name><value>ET_Cnc</value></option>
				<option><name>ET BOT</name><value>ET_Bot</value></option>
				<option><name>ET SPAM</name><value>ET_Spam</value></option>
				<option><name>ET DROP</name><value>ET_Drop</value></option>
				<option><name>ET Spyware CNC</name><value>ET_Spywarecnc</value></option>
				<option><name>ET Online Gaming</name><value>ET_Onlinegaming</value></option>
				<option><name>ET DrivebySRC</name><value>ET_Drivebysrc</value></option>
				<option><name>ET Chat Server</name><value>ET_Chatserver</value></option>
				<option><name>ET TOR Node</name><value>ET_Tornode</value></option>
				<option><name>ET Compromised</name><value>ET_Compromised</value></option>
				<option><name>ET P2P</name><value>ET_P2P</value></option>
				<option><name>ET Proxy</name><value>ET_Proxy</value></option>
				<option><name>ET IP Check</name><value>ET_Ipcheck</value></option>
				<option><name>ET Utility</name><value>ET_Utility</value></option>
				<option><name>ET DOS</name><value>ET_DDos</value></option>
				<option><name>ET Scanner</name><value>ET_Scanner</value></option>
				<option><name>ET Brute</name><value>ET_Brute</value></option>
				<option><name>ET Fake AV</name><value>ET_Fakeav</value></option>
				<option><name>ET DYN DNS</name><value>ET_Dyndns</value></option>
				<option><name>ET Undersireable</name><value>ET_Undesireable</value></option>
				<option><name>ET Abuse TLD</name><value>ET_Abusedtld</value></option>
				<option><name>ET SelfSigned SSL</name><value>ET_Selfsignedssl</value></option>
				<option><name>ET Blackhole</name><value>ET_Blackhole</value></option>
				<option><name>ET RAS</name><value>ET_RAS</value></option>
				<option><name>ET P2P CNC</name><value>ET_P2Pcnc</value></option>
				<option><name>ET Shared Hosting</name><value>ET_Sharedhosting</value></option>
				<option><name>ET Parking</name><value>ET_Parking</value></option>
				<option><name>ET VPN</name><value>ET_VPN</value></option>
				<option><name>ET EXE Source</name><value>ET_Exesource</value></option>
				<option><name>ET Mobile CNC</name><value>ET_Mobilecnc</value></option>
				<option><name>ET Mobile Spyware</name><value>ET_Mobilespyware</value></option>
				<option><name>ET Skype Node</name><value>ET_Skypenode</value></option>
				<option><name>ET Bitcoin</name><value>ET_Bitcoin</value></option>
				<option><name>ET DOS Attack</name><value>ET_DDosattack</value></option>
				<option><name>Unknown</name><value>ET_Unknown</value></option>
			</options>
			<size>35</size>
			<multiple/>
		</field>
		<field>
			<fielddescr>Update ET Categories</fielddescr>
			<fieldname>et_update</fieldname>
			<description><![CDATA[Default: <strong>Disable</strong><br />
				Select - Enable ET Update if Category Changes are Made.<br />
				You can perform a 'Force Update' to enable these changes.<br />
				Cron will also resync this list at the next Scheduled Update.]]>
			</description>
			<type>select</type>
			<options>
				<option><name>Disable</name><value>disabled</value></option>
				<option><name>Enable</name><value>enabled</value></option>
			</options>
		</field>
		<field>
			<name><![CDATA[<center>Click to SAVE Settings and/or Rule Edits. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Changes are Applied via CRON or
				'Force Update'</center>]]></name>
			<type>listtopic</type>
		</field>
	</fields>
	<custom_php_install_command>
		pfblockerng_php_install_command();
	</custom_php_install_command>
	<custom_php_deinstall_command>
		pfblockerng_php_deinstall_command();
	</custom_php_deinstall_command>
	<custom_php_validation_command>
		pfblockerng_validate_input(\$_POST, \$input_errors);
	</custom_php_validation_command>
	<custom_php_resync_config_command>
		global \$pfb;
		\$pfb['save'] = TRUE;
		sync_package_pfblockerng();
	</custom_php_resync_config_command>
</packagegui>
EOF;
	$log = "Saving pfBlockerNG Reputation TAB \n";
	print $log;
	pfb_logger("{$log}","3");

	// Save pfBlockerng_reputation.xml file
	@file_put_contents('/usr/local/pkg/pfblockerng/pfblockerng_reputation.xml', $xmlrep, LOCK_EX);

	$log = "\n Country Code - XML File Update completed.\n";
	print $log;
	pfb_logger("{$log}","3");
	$now = date("m/d/y G.i:s", time());
	$log = "Country Code Update Ended - [ NOW ]\n";
	print "Country Code Update Ended - [ $now ]\n";
	pfb_logger("{$log}","3");

	// Unset Arrays
	unset ($roptions4, $et_options, $xmlrep);
}
?>