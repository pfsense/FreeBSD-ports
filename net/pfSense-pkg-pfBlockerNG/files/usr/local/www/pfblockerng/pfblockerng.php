<?php
/*
	pfBlockerNG.php

	pfBlockerNG
	Copyright (c) 2015-2016 BBcan177@gmail.com
	All rights reserved.

	Based upon pfBlocker by
	Copyright (c) 2011-2012 Marcello Coutinho
	All rights reserved.

	Hour Schedule Convertor code by
	Snort Package
	Copyright (c) 2016 Bill Meeks

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

require_once('util.inc');
require_once('functions.inc');
require_once('pkg-utils.inc');
require_once('globals.inc');
require_once('services.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng_extra.inc');	// 'include functions' not yet merged into pfSense

global $config, $g, $pfb;

// Extras - MaxMind/Alexa Download URLs/filenames/settings
$pfb['extras'][0]['url']	= 'http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz';
$pfb['extras'][0]['file_dwn']	= 'GeoIP.dat.gz';
$pfb['extras'][0]['file']	= 'GeoIP.dat';
$pfb['extras'][0]['folder']	= "{$pfb['geoipshare']}";

$pfb['extras'][1]['url']	= 'http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz';
$pfb['extras'][1]['file_dwn']	= 'GeoIPv6.dat.gz';
$pfb['extras'][1]['file']	= 'GeoIPv6.dat';
$pfb['extras'][1]['folder']	= "{$pfb['geoipshare']}";

$pfb['extras'][2]['url']	= 'http://geolite.maxmind.com/download/geoip/database/GeoIPCountryCSV.zip';
$pfb['extras'][2]['file_dwn']	= 'GeoIPCountryCSV.zip';
$pfb['extras'][2]['file']	= 'GeoIPCountryWhois.csv';
$pfb['extras'][2]['folder']	= "{$pfb['geoipshare']}";

$pfb['extras'][3]['url']	= 'http://dev.maxmind.com/static/csv/codes/country_continent.csv';
$pfb['extras'][3]['file_dwn']	= 'country_continent.csv';
$pfb['extras'][3]['file']	= 'country_continent.csv';
$pfb['extras'][3]['folder']	= "{$pfb['geoipshare']}";

$pfb['extras'][4]['url']	= 'http://geolite.maxmind.com/download/geoip/database/GeoIPv6.csv.gz';
$pfb['extras'][4]['file_dwn']	= 'GeoIPv6.csv.gz';
$pfb['extras'][4]['file']	= 'GeoIPv6.csv';
$pfb['extras'][4]['folder']	= "{$pfb['geoipshare']}";

$pfb['extras'][5]['url']	= 'https://s3.amazonaws.com/alexa-static/top-1m.csv.zip';
$pfb['extras'][5]['file_dwn']	= 'top-1m.csv.zip';
$pfb['extras'][5]['file']	= 'top-1m.csv';
$pfb['extras'][5]['folder']	= "{$pfb['dbdir']}";


// Call include file and collect updated Global settings
if (in_array($argv[1], array('update', 'updateip', 'updatednsbl', 'dc', 'bu', 'uc', 'gc', 'al', 'cron'))) {
	pfb_global();

	// Script Arguments
	switch($argv[1]) {
		case 'cron':		// Sync 'cron'
			pfblockerng_sync_cron();
			break;
		case 'updateip':	// Sync 'Force Reload IP only'
		case 'updatednsbl':	// Sync 'Force Reload DNSBL only'
			sync_package_pfblockerng($argv[1]);
			break;
		case 'update':		// Sync 'Force update'
			sync_package_pfblockerng('cron');
			break;
		case 'dc':		// Update Extras - MaxMind/Alexa database files
			$pfb['maxmind_install'] = TRUE;

			// If 'General Tab' skip MaxMind download setting if checked, only download binary updates for Reputation/Alerts page.
			if (!empty($pfb['cc'])) {
				unset($pfb['extras'][2], $pfb['extras'][3], $pfb['extras'][4]);
			}

			// Skip Alexa update, if disabled
			if ($pfb['dnsbl_alexa'] != 'on') {
				unset($pfb['extras'][5]);
			}

			// Proceed with conversion of MaxMind files on download success
			if (pfblockerng_download_extras()) {
				pfblockerng_uc_countries();
				pfblockerng_get_countries();
			}
			unset($pfb['maxmind_install']);
			break;
		case 'bu':		// Update MaxMind binary database files only.
			unset($pfb['extras'][2], $pfb['extras'][3], $pfb['extras'][4], $pfb['extras'][5]);
			pfblockerng_download_extras();
			break;
		case 'al':		// Update Alexa database only.
			unset($pfb['extras'][0], $pfb['extras'][1], $pfb['extras'][2], $pfb['extras'][3], $pfb['extras'][4]);
			pfblockerng_download_extras();
			break;
		case 'uc':		// Update MaxMind ISO files from local database files.
			pfblockerng_uc_countries();
			break;
		case 'gc':		// Update Continent XML files.
			pfblockerng_get_countries();
			break;
	}
}


// Determine if source list file has an updated timestamp
function pfb_update_check($header, $list_url, $pfbfolder, $pfborig, $pflex, $format) {
	global $config, $pfb;

	$log = "[ {$header} ]\n";
	pfb_logger("{$log}", 1);
	$pfb['cron_update'] = FALSE;

	// Call function to get all previous download fails
	pfb_failures();

	if ($pfb['skipfeed'] != 0) {
		// Determine if previous download fails have exceeded threshold.
		if ($pfb['failed'][$header] >= $pfb['skipfeed']) {
			$log = "  Max daily download failure attempts exceeded. Clear widget 'failed downloads' to reset.\n\n";
			pfb_logger("{$log}", 1);
			unlink_if_exists("{$pfbfolder}/{$header}.fail");
			return;
		}
	}

	// Attempt download, when a previous 'fail' file marker is found.
	if (file_exists("{$pfbfolder}/{$header}.fail")) {
		$log = "\t\t\tPrevious download failed.\tRe-attempt download\n";
		pfb_logger("{$log}", 1);
		$pfb['update_cron'] = TRUE;
		unlink_if_exists("{$pfbfolder}/{$header}.txt");
		return;
	}

	// Check if List file doesn't exist or Format is 'whois'.
	if (!file_exists("{$pfbfolder}/{$header}.txt") || $format == 'whois') {
		$log = "\t\t\t\t\t\t\tUpdate found\n";
		pfb_logger("{$log}", 1);
		$pfb['update_cron'] = TRUE;
		return;
	}

	$host = @parse_url($list_url);
	$local_file = "{$pfborig}/{$header}.orig";

	// Compare previously downloaded file timestamp with remote timestamp
	if (file_exists($local_file)) {
		if ($format == 'rsync') {
			$log = "\t\t\t\t( rsync )\t\tUpdate found\n";
			pfb_logger("{$log}", 1);
			$pfb['update_cron'] = TRUE;
			unlink_if_exists("{$pfbfolder}/{$header}.txt");
			return;
		}

		// Determine if URL is Remote or Local
		if (in_array($host['host'], array('127.0.0.1', $pfb['iplocal'], ''))) {
			clearstatcache();
			$remote_tds = gmdate('D, d M Y H:i:s T', @filemtime($list_url));
		}
		else {
			// Download URL headers and compare previously downloaded file with remote timestamp
			if (($ch = curl_init($list_url))) {
				curl_setopt_array($ch, $pfb['curl_defaults']);		// Load curl default settings
				curl_setopt($ch, CURLOPT_NOBODY, true);			// Exclude the body from the output
				curl_setopt($ch, CURLOPT_TIMEOUT, 60);

				// Allow downgrade of cURL settings if user configured
				if ($pflex == 'Flex') {
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
					curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1.2, TLSv1, SSLv3');
				}

				// Try up to 3 times to download the file before giving up
				for ($retries = 1; $retries <= 3; $retries++) {
					if (curl_exec($ch)) {
						$remote_stamp_raw = curl_getinfo($ch, CURLINFO_FILETIME);
						break;	// Break on success
					}
					sleep(3);
				}
				if ($remote_stamp_raw != -1) {
					$remote_tds = gmdate('D, d M Y H:i:s T', $remote_stamp_raw);
				}
			}
			else {
				$remote_stamp_raw = -1;
			}
			curl_close($ch);
		}

		// If remote timestamp not found, Attempt md5 comparison
		if ($remote_stamp_raw == -1) {
			// Collect md5 checksums
			$remote_md5	= @md5_file($list_url);
			$local_md5	= @md5_file($local_file);

			if ($remote_md5 != $local_md5) {
				$log = "\t\t\t\t( md5 changed )\t\tUpdate found\n";
				pfb_logger("{$log}", 1);
				$pfb['update_cron'] = TRUE;
				unlink_if_exists("{$pfbfolder}/{$header}.txt");
				return;
			}
			else {
				$log = "\t( No remote timestamp/md5 unchanged )\t\tUpdate not required\n";
				pfb_logger("{$log}", 1);
				return;
			}
		}
		else {
			$log = "  Remote timestamp: {$remote_tds}\n";
			pfb_logger("{$log}", 1);
			clearstatcache();
			$local_tds = gmdate('D, d M Y H:i:s T', @filemtime($local_file));
			$log = "  Local  timestamp: {$local_tds}\t";
			pfb_logger("{$log}", 1);
	
			if ("{$remote_tds}" != "{$local_tds}") {
				$pfb['cron_update'] = TRUE;
			}
			else {
				$log = "Update not required\n";
				pfb_logger("{$log}", 1);
				$pfb['cron_update'] = FALSE;
			}
		}
	} else {
		$pfb['cron_update'] = TRUE;
	}

	if ($pfb['cron_update']) {
		// Trigger CRON process if updates are found.
		$pfb['update_cron'] = TRUE;

		$log = "Update found\n";
		pfb_logger("{$log}", 1);
		unlink_if_exists("{$pfbfolder}/{$header}.txt");
	}
	return;
}


// Download Extras - MaxMind/Alexa feeds via cURL
function pfblockerng_download_extras($timeout=600) {
	global $pfb;
	$pfberror = FALSE;

	pfb_logger("\nDownload Process Starting [ NOW ]\n", 3);
	foreach ($pfb['extras'] as $feed) {
		$file_dwn = "{$feed['folder']}/{$feed['file_dwn']}";
		if (!pfb_download($feed['url'], $file_dwn, FALSE, "{$feed['folder']}/{$feed['file']}", '', 3, '', $timeout)) {
			$log = "\nFailed to Download {$feed['file']}\n";
			pfb_logger("{$log}", 3);

			// On install, if error found when downloading MaxMind Continent lists
			// Return error to install process to download archive from pfSense package repo
			if ($pfb['maxmind_install']) {
				$pfberror = TRUE;
			}
		}
	}
	pfb_logger("Download Process Ended [ NOW ]\n\n", 3);

	if ($pfberror) {
		return FALSE;
	} else {
		return TRUE;
	}
}


// Function to update Lists/Feeds as per Cron
function pfblockerng_sync_cron() {
	global $config, $pfb, $pfbarr;

	// Call base hour converter
	$pfb_sch = pfb_cron_base_hour();

	$hour = date('G');
	$dow  = date('N');
	$pfb['update_cron'] = FALSE;
	$log = " CRON  PROCESS  START [ NOW ]\n";
	pfb_logger("{$log}", 1);

	$list_type = array('pfblockernglistsv4' => '_v4', 'pfblockernglistsv6' => '_v6', 'pfblockerngdnsbl' => '_v4', 'pfblockerngdnsbleasylist' => '_v4');
	foreach ($list_type as $ip_type => $vtype) {
		if (!empty($config['installedpackages'][$ip_type]['config'])) {
			foreach ($config['installedpackages'][$ip_type]['config'] as $list) {
				if (isset($list['row']) && $list['action'] != 'Disabled' && $list['cron'] != 'Never') {
					foreach ($list['row'] as $row) {
						if (!empty($row['url']) && $row['state'] != 'Disabled') {

							if ($vtype == '_v4') {
								$header = "{$row['header']}";
							} else {
								$header = "{$row['header']}_v6";
							}

							// Determine folder location for alias (return array $pfbarr)
							pfb_determine_list_detail($list['action'], '', '', '');
							$pfbfolder	= $pfbarr['folder'];
							$pfborig	= $pfbarr['orig'];

							// Bypass update if state is defined as 'Hold' and list file exists
							if ($row['state'] == 'Hold' && file_exists("{$pfbfolder}/{$header}.txt")) {
								continue;
							}

							// Allow cURL SSL downgrade if user configured.
							$pflex = FALSE;
							if ($row['state'] == 'Flex') {
								$pflex = TRUE;
							}

							switch ($list['cron']) {
								case 'EveryDay':
									if ($hour == $pfb['24hour']) {
										pfb_update_check($header, $row['url'], $pfbfolder, $pfborig, $pflex, $row['format']);
									}
									break;
								case 'Weekly':
									if ($hour == $pfb['24hour'] && $dow == $list['dow']) {
										pfb_update_check($header, $row['url'], $pfbfolder, $pfborig, $pflex, $row['format']);
									}
									break;
								default:
									if ($pfb['interval'] == '1' || in_array($hour, $pfb_sch)) {
										pfb_update_check($header, $row['url'], $pfbfolder, $pfborig, $pflex, $row['format']);
									}
									break;
							}
						}
					}
				}
			}
		}
	}

	// If no lists require updates, check if Continents are configured and update accordingly.
	if (!$pfb['update_cron']) {
		foreach ($pfb['continents'] as $continent => $pfb_alias) {
			if (isset($config['installedpackages']['pfblockerng' . strtolower(str_replace(' ', '', $continent))]['config'])) {
				$continent_config = $config['installedpackages']['pfblockerng' . strtolower(str_replace(' ', '', $continent))]['config'][0];
				if ($continent_config['action'] != 'Disabled') {
					$pfb['update_cron'] = TRUE;
					break;
				}
			}
		}
	}

	if ($pfb['update_cron']) {
		sync_package_pfblockerng('cron');
		$pfb['update_cron'] = FALSE;
	} else {
		sync_package_pfblockerng('noupdates');
		$log = "\n  No Updates required.\n CRON  PROCESS  ENDED\n UPDATE PROCESS ENDED\n";
		pfb_logger("{$log}", 1);
	}

	// Call log mgmt function
	// If Update GUI 'Manual view' is selected. Last output will be missed. So sleep for 5 secs.
	sleep(5);
	pfb_log_mgmt();
}


// Function to process the downloaded MaxMind database and format into Continent txt files.
function pfblockerng_uc_countries() {
	global $g, $pfb;

	$maxmind_cont	= "{$pfb['geoipshare']}/country_continent.csv";
	$maxmind_cc4	= "{$pfb['geoipshare']}/GeoIPCountryWhois.csv";
	$maxmind_cc6	= "{$pfb['geoipshare']}/GeoIPv6.csv";
	
	// Create folders if not exist
	$folder_array = array ("{$pfb['dbdir']}", "{$pfb['logdir']}", "{$pfb['ccdir']}");
	foreach ($folder_array as $folder) {
		safe_mkdir ("{$folder}", 0755);
	}

	$now = date('m/d/y G:i:s', time());
	$log = "Country code update Start [ NOW ]\n";
	if (!$g['pfblockerng_install']) {
		print ("Country code update Start [ {$now} ]\n");
	}
	pfb_logger("{$log}", 3);

	if (!file_exists($maxmind_cont) || !file_exists($maxmind_cc4) || !file_exists($maxmind_cc6)) {
		$log = " [ MAXMIND UPDATE FAIL, CSV missing, using previous Country code database \n";
		if (!$g['pfblockerng_install']) {
			print ("{$log}");
		}
		pfb_logger("{$log}", 3);
		return;
	}

	// Save Date/Time stamp to MaxMind version file
	$local_tds4	 = @gmdate('D, d M Y H:i:s T', @filemtime($maxmind_cc4));
	$local_tds6	 = @gmdate('D, d M Y H:i:s T', @filemtime($maxmind_cc6));
	$maxmind_ver	 = "MaxMind GeoLite Date/Time Stamps\n";
	$maxmind_ver	.= "Local_v4 \tLast-Modified: {$local_tds4}\n";
	$maxmind_ver	.= "Local_v6 \tLast-Modified: {$local_tds6}\n";
	@file_put_contents("{$pfb['logdir']}/maxmind_ver", $maxmind_ver, LOCK_EX);

	// Collect ISO codes for each Continent
	$log = " Converting MaxMind Country databases for pfBlockerNG.\n";
	if (!$g['pfblockerng_install']) {
		print ("{$log}");
	}
	pfb_logger("{$log}", 3);

	$cont_array = array();
	if (($handle = @fopen("{$maxmind_cont}", 'r')) !== FALSE) {
		while (($cc = @fgetcsv($handle)) !== FALSE) {
			$cc_key = $cc[0];
			$cont_key = $cc[1];

			switch ($cont_key) {
				case 'AF':
					$cont_array[0]['continent'] = 'Africa';
					$cont_array[0]['iso']  .= "{$cc_key},";
					$cont_array[0]['file4'] = "{$pfb['ccdir']}/Africa_v4.txt";
					$cont_array[0]['file6'] = "{$pfb['ccdir']}/Africa_v6.txt";
					break;
				case 'AS':
					$cont_array[1]['continent'] = 'Asia';
					$cont_array[1]['iso']  .= "{$cc_key},";
					$cont_array[1]['file4'] = "{$pfb['ccdir']}/Asia_v4.txt";
					$cont_array[1]['file6'] = "{$pfb['ccdir']}/Asia_v6.txt";
					break;
				case 'EU':
					$cont_array[2]['continent'] = 'Europe';
					$cont_array[2]['iso']  .= "{$cc_key},";
					$cont_array[2]['file4'] = "{$pfb['ccdir']}/Europe_v4.txt";
					$cont_array[2]['file6'] = "{$pfb['ccdir']}/Europe_v6.txt";
					break;
				case 'NA':
					$cont_array[3]['continent'] = 'North America';
					$cont_array[3]['iso']  .= "{$cc_key},";
					$cont_array[3]['file4'] = "{$pfb['ccdir']}/North_America_v4.txt";
					$cont_array[3]['file6'] = "{$pfb['ccdir']}/North_America_v6.txt";
					break;
				case 'OC':
					$cont_array[4]['continent'] = 'Oceania';
					$cont_array[4]['iso']  .= "{$cc_key},";
					$cont_array[4]['file4'] = "{$pfb['ccdir']}/Oceania_v4.txt";
					$cont_array[4]['file6'] = "{$pfb['ccdir']}/Oceania_v6.txt";
					break;
				case 'SA':
					$cont_array[5]['continent'] = 'South America';
					$cont_array[5]['iso']  .= "{$cc_key},";
					$cont_array[5]['file4'] = "{$pfb['ccdir']}/South_America_v4.txt";
					$cont_array[5]['file6'] = "{$pfb['ccdir']}/South_America_v6.txt";
					break;
			}
		}
	}
	unset($cc);
	@fclose($handle);

	// Add Maxmind Anonymous Proxy and Satellite Providers to array
	$cont_array[6]['continent']	= 'Proxy and Satellite';
	$cont_array[6]['iso']		= 'A1,A2';
	$cont_array[6]['file4'] 	= "{$pfb['ccdir']}/Proxy_Satellite_v4.txt";
	$cont_array[6]['file6'] 	= "{$pfb['ccdir']}/Proxy_Satellite_v6.txt";

	// Patch for missing CCodes
	$cont_array[0]['iso'] .= 'SS';
	$cont_array[3]['iso'] .= 'BQ,CW,SX';

	sort($cont_array);

	// Collect Country ISO data and sort to Continent arrays (IPv4 and IPv6)
	foreach (array('4', '6') as $type) {
		$log = " Processing ISO IPv{$type} Continent/Country Data\n";
		print ("{$log}");
		pfb_logger("{$log}", 3);

		if ($type == '4') {
			$maxmind_cc = "{$pfb['geoipshare']}/GeoIPCountryWhois.csv";
		} else {
			$maxmind_cc = "{$pfb['geoipshare']}/GeoIPv6.csv";
		}
		$iptype = "ip{$type}";
		$filetype = "file{$type}";

		if (($handle = @fopen("{$maxmind_cc}", 'r')) !== FALSE) {
			while (($cc = @fgetcsv($handle)) !== FALSE) {
				$cc_key		= $cc[4];
				$country_key	= $cc[5];
				$a_cidr		= implode(',', ip_range_to_subnet_array($cc[0], $cc[1]));
				foreach ($cont_array as $key => $iso) {
					if (strpos($iso['iso'], $cc_key) !== FALSE) {
						$cont_array[$key][$cc_key][$iptype]  .= "{$a_cidr},";
						$cont_array[$key][$cc_key]['country'] = $country_key;
						continue;
					}
				}
			}
		}
		unset($cc);
		@fclose($handle);

		// Build Continent files
		foreach ($cont_array as $key => $iso) {
			$header		= $pfb_file = $iso_key = '';
			$header		.= '# Generated from MaxMind Inc. on: ' . date('m/d/y G:i:s', time()) . "\n";
			$header		.= "# Continent IPv{$type}: {$cont_array[$key]['continent']}\n";
			$pfb_file	= $cont_array[$key][$filetype];
			$iso_key	= array_keys($iso);

			foreach ($iso_key as $ikey) {
				if (strlen($ikey) == 2) {
					$header .= "# Country: {$iso[$ikey]['country']}\n";
					$header .= "# ISO Code: {$ikey}\n";
					$header .= '# Total Networks: ' . substr_count($iso[$ikey][$iptype], ',') . "\n";
					$header .= str_replace(',', "\n", $iso[$ikey][$iptype]);
					$iso[$ikey][$iptype] = '';
				}
			}
			@file_put_contents($pfb_file, $header, LOCK_EX);
		}
	}
}


// Function to process Continent txt files and create Country ISO files and to Generate GUI XML files.
function pfblockerng_get_countries() {
	global $g, $pfb;

	$files = array (	'Africa'		=> "{$pfb['ccdir']}/Africa_v4.txt",
				'Asia'			=> "{$pfb['ccdir']}/Asia_v4.txt",
				'Europe'		=> "{$pfb['ccdir']}/Europe_v4.txt",
				'North America'		=> "{$pfb['ccdir']}/North_America_v4.txt",
				'Oceania'		=> "{$pfb['ccdir']}/Oceania_v4.txt",
				'South America'		=> "{$pfb['ccdir']}/South_America_v4.txt",
				'Proxy and Satellite'	=> "{$pfb['ccdir']}/Proxy_Satellite_v4.txt"
				);

	// Collect data to generate new continent XML files.
	$log = " Creating pfBlockerNG Continent XML files\n";
	if (!$g['pfblockerng_install']) {
		print ("{$log}");
	}
	pfb_logger("{$log}", 3);

	foreach ($files as $cont => $file) {
		// Process the following for IPv4 and IPv6
		foreach (array('4', '6') as $type) {
			$log = " IPv{$type} {$cont}\n";
			print ("{$log}");
			pfb_logger("{$log}", 3);

			if ($type == '6') {
				$file = str_replace('v4', 'v6', $file);
			}
			$convert		= explode("\n", file_get_contents($file));
			$cont_name		= str_replace(' ', '', $cont);
			$cont_name_lower	= strtolower($cont_name);
			$active			= array("$cont" => '<active/>');
			$lastkey		= count($convert) - 1;
			$pfb['complete']	= FALSE;
			$keycount		= 1;
			$total			= 0;
			$xml_data		= '';

			foreach ($convert as $line) {
				if (substr($line, 0, 1) == '#') {
					if ($pfb['complete']) {
						${'coptions' . $type}[] = "{$country}-{$isocode} ({$total})</name><value>{$isocode}</value></option>";
						// Only collect IPv4 for Reputation Tab
						if ($type == '4') {
							$roptions4[] = "{$country}-{$isocode} ({$total})</name><value>{$isocode}</value></option>";
						}
						// Save ISO data
						@file_put_contents("{$pfb['ccdir']}/{$isocode}_v{$type}.txt", $xml_data, LOCK_EX);

						// Clear variables and restart Continent collection process
						unset($total, $xml_data);
						$pfb['complete'] = FALSE;
					}
					// Don't collect Countries with null data
					if (strpos($line, 'Total Networks: 0') !== FALSE) {
						continue;
					}
					if (strpos($line, 'Country: ') !== FALSE) {
						$country = str_replace('# Country: ', '', $line);
					}
					if (strpos($line, 'ISO Code: ') !== FALSE) {
						$isocode = str_replace('# ISO Code: ', '', $line);
					}

				}
				elseif (substr($line, 0, 1) != '#') {
					$total++;
					if (!empty($line)) {
						$xml_data .= "{$line}\n";
					}
					$pfb['complete'] = TRUE;
				}

				// Save last EOF ISO IP data
				if ($keycount == $lastkey) {
					// Don't collect Countries with null data
					if (strpos($line, 'Total Networks: 0') !== FALSE) {
						continue;
					}
					${'coptions' . $type}[] = "{$country}-{$isocode} ({$total})</name><value>{$isocode}</value></option>";
					if ($type == '4') {
						$roptions4[] = "{$country}-{$isocode} ({$total})</name><value>{$isocode}</value></option>";
					}
					@file_put_contents("{$pfb['ccdir']}/{$isocode}_v{$type}.txt", $xml_data, LOCK_EX);
					unset($total, $xml_data);
				}
				$keycount++;
			}
			unset ($ips, $convert);

			// Sort IP Countries alphabetically and build XML <option> data for Continents tab
			if (!empty(${'coptions' . $type})) {
				sort(${'coptions' . $type}, SORT_STRING);
				${'ftotal' . $type} = count(${'coptions' . $type});
				$count = 1;
				${'options' . $type} = '';

				foreach (${'coptions' . $type} as $option) {
					if ($count == 1) {
						${'options' . $type} .= "\t<option><name>{$option}\n";
						$count++;
						continue;
					}
					if (${'ftotal' . $type} == $count) {
						${'options' . $type} .= "\t\t\t\t<option><name>{$option}";
					} else {
						${'options' . $type} .= "\t\t\t\t<option><name>{$option}\n";
					}
					$count++;
				}
			}
			unset(${'coptions' . $type});
		}

$xml = <<<EOF
<?xml version="1.0" encoding="utf-8" ?>
<!DOCTYPE packagegui SYSTEM "../schema/packages.dtd">
<?xml-stylesheet type="text/xsl" href="../xsl/package.xsl"?>
<packagegui>
	<copyright>
	<![CDATA[
/* ========================================================================== */
/*
	pfblockerng_{$cont_name}.xml

	pfBlockerNG
	Copyright (C) 2015-2016 BBcan177@gmail.com
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
	<name>pfblockerng{$cont_name_lower}</name>
	<title>Firewall/pfBlockerNG</title>
	<include_file>/usr/local/pkg/pfblockerng/pfblockerng.inc</include_file>
	<addedit_string>pfBlockerNG: Save {$cont} settings</addedit_string>
	<savehelp><![CDATA[<strong>Click to SAVE Settings and/or Rule edits.&emsp;Changes are applied via CRON or
		'Force Update'</strong>]]>
	</savehelp>
	<menu>
		<name>pfBlockerNG: {$cont_name}</name>
		<section>Firewall</section>
		<url>pkg_edit.php?xml=pfblockerng_{$cont_name_lower}.xml</url>
	</menu>
		<tabs>
		<tab>
			<text>General</text>
			<url>/pkg_edit.php?xml=pfblockerng.xml</url>
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
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_reputation.xml</url>
		</tab>
		<tab>
			<text>IPv4</text>
			<url>/pkg.php?xml=/pfblockerng/pfblockerng_v4lists.xml</url>
		</tab>
		<tab>
			<text>IPv6</text>
			<url>/pkg.php?xml=/pfblockerng/pfblockerng_v6lists.xml</url>
		</tab>
		<tab>
			<text>DNSBL</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_dnsbl.xml</url>
		</tab>
		<tab>
			<text>Country</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_top20.xml</url>
		</tab>
		<tab>
			<text>Top 20</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_top20.xml</url>
			<tab_level>2</tab_level>
			{$active['top']}
		</tab>
		<tab>
			<text>Africa</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Africa.xml</url>
			<tab_level>2</tab_level>
			{$active['Africa']}
		</tab>
		<tab>
			<text>Asia</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Asia.xml</url>
			<tab_level>2</tab_level>
			{$active['Asia']}
		</tab>
		<tab>
			<text>Europe</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Europe.xml</url>
			<tab_level>2</tab_level>
			{$active['Europe']}
		</tab>
		<tab>
			<text>North America</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_NorthAmerica.xml</url>
			<tab_level>2</tab_level>
			{$active['North America']}
		</tab>
		<tab>
			<text>Oceania</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Oceania.xml</url>
			<tab_level>2</tab_level>
			{$active['Oceania']}
		</tab>
		<tab>
			<text>South America</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_SouthAmerica.xml</url>
			<tab_level>2</tab_level>
			{$active['South America']}
		</tab>
		<tab>
			<text>Proxy and Satellite</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_ProxyandSatellite.xml</url>
			<tab_level>2</tab_level>
			{$active['Proxy and Satellite']}
		</tab>
		<tab>
			<text>Logs</text>
			<url>/pfblockerng/pfblockerng_log.php</url>
		</tab>
		<tab>
			<text>Sync</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_sync.xml</url>
		</tab>
		</tabs>
	<fields>
		<field>
			<name><![CDATA[Continent {$cont}&emsp; (Geolite Data by MaxMind Inc. - ISO 3166)]]></name>
			<type>listtopic</type>
		</field>
		<field>
			<fielddescr>LINKS</fielddescr>
			<description><![CDATA[<a href="/firewall_aliases.php">Firewall Alias</a>&emsp;
				<a href="/firewall_rules.php">Firewall Rules</a>&emsp;<a href="status_logs_filter.php">Firewall Logs</a>]]>
			</description>
			<type>info</type>
		</field>
		<field>
			<fieldname>countries4</fieldname>
			<fielddescr><![CDATA[<strong><center>Countries</center></strong><br />
				<center>Use CTRL&nbsp;+&nbsp;CLICK to select/unselect countries</center>]]>
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
			<description><![CDATA[<center>IPv4 Countries</center><br />]]></description>
			<combinefields>begin</combinefields>
		</field>

EOF;
} else {
	$xml .= <<<EOF
			<description><![CDATA[IPv4 Countries<br />]]></description>
		</field>

EOF;
}

// Skip IPv6 when Null data found
if (!empty (${'options6'})) {
	$xml .= <<<EOF
		<field>
			<fieldname>countries6</fieldname>
			<description><![CDATA[<center>IPv6 Countries</center><br />]]></description>
			<type>select</type>
			<options>
			${'options6'}
			</options>
			<size>${'ftotal6'}</size>
			<multiple/>
			<combinefields>end</combinefields>
		</field>

EOF;
}

$xml .= <<<EOF
		<field>
			<fielddescr>List Action</fielddescr>
			<description><![CDATA[Select the <strong>Action</strong> for Firewall Rules on lists you have selected.<br />
				Default: <strong>Disabled</strong><div class="infoblock">
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
				<ul><li><strong>Options &emsp;- Alias Deny,&nbsp; Alias Permit,&nbsp; Alias Match,&nbsp; Alias Native</strong></li><br />
				<li>'Alias Deny' can use De-Duplication and Reputation Processes if configured.</li><br />
				<li>'Alias Permit' and 'Alias Match' will be saved in the Same folder as the other Permit/Match Auto-Rules</li><br />
				<li>'Alias Native' lists are kept in their Native format without any modifications.</li></ul>
				<span class="text-danger">Note: </span><ul>When manually creating 'Alias' type firewall rules;
				<strong>Do not add</strong> (pfB_) to the start of the rule description, use (pfb_) (Lowercase prefix). Manually created
				 'Alias' rules with 'pfB_' in the description will be auto-removed by package when 'Auto' rules are defined.</ul></div>]]>
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
			<collapse>closed</collapse>
		</field>
		<field>
			<type>info</type>
			<description><![CDATA[<span class="text-danger">Note:</span>&nbsp; In general, Auto-Rules are created as follows:<br />
				<dl class="dl-horizontal">
					<dt>Inbound</dt><dd>'any' port, 'any' protocol, 'any' destination and 'any' gateway</dd>
				</dl>
				Configuring the Adv. Inbound Rule settings, will allow for more customization of the Inbound Auto-Rules.]]>
			</description>
		</field>
		<field>
			<fielddescr>Invert Source</fielddescr>
			<fieldname>autoaddrnot_in</fieldname>
			<sethelp><![CDATA[<strong>Invert</strong> - Option to invert the sense of the match.
				ie - Not (!) Source Address(es)]]>
			</sethelp>
			<type>checkbox</type>
		</field>
		<field>
			<fielddescr>Custom Port</fielddescr>
			<fieldname>autoports_in</fieldname>
			<type>checkbox</type>
			<sethelp>Enable</sethelp>
			<enablefields>aliasports_in</enablefields>
			<combinefields>begin</combinefields>
		</field>
		<field>
			<fielddescr>Custom Port</fielddescr>
			<fieldname>aliasports_in</fieldname>
			<description><![CDATA[<a target="_blank" href="/firewall_aliases.php?tab=port">Click Here to add/edit Aliases</a>
				Do not manually enter port numbers.<br />Do not use 'pfB_' in the Port Alias name.]]>
			</description>
			<width>6</width>
			<type>aliases</type>
			<typealiases>port</typealiases>
			<combinefields>end</combinefields>
		</field>
		<field>
			<fielddescr>Custom Destination</fielddescr>
			<fieldname>autoaddr_in</fieldname>
			<type>checkbox</type>
			<sethelp>Enable</sethelp>
			<enablefields>aliasaddr_in,autonot_in</enablefields>
			<combinefields>begin</combinefields>
		</field>
		<field>
			<fielddescr>Invert</fielddescr>
			<fieldname>autonot_in</fieldname>
			<type>checkbox</type>
			<sethelp>Invert</sethelp>
			<combinefields/>
		</field>
		<field>
			<fieldname>aliasaddr_in</fieldname>
			<fielddescr>Custom Destination</fielddescr>
			<description><![CDATA[<a target="_blank" href="/firewall_aliases.php?tab=ip">Click Here to add/edit Aliases</a>
				Do not manually enter Addresses(es).<br />Do not use 'pfB_' in the 'IP Network Type' Alias name.<br />
				Select 'invert' to invert the sense of the match. ie - Not (!) Destination Address(es)]]>
			</description>
			<width>6</width>
			<type>aliases</type>
			<typealiases>network</typealiases>
			<combinefields>end</combinefields>
		</field>
		<field>
			<fielddescr>Custom Protocol</fielddescr>
			<fieldname>autoproto_in</fieldname>
			<description><![CDATA[<strong>Default: any</strong><br />Select the Protocol used for Inbound Firewall Rule(s).<br />
				Do not use 'any' with Adv. Inbound Rules as it will bypass these settings!]]></description>
			<type>select</type>
			<options>
				<option><name>any</name><value></value></option>
				<option><name>TCP</name><value>tcp</value></option>
				<option><name>UDP</name><value>udp</value></option>
				<option><name>TCP/UDP</name><value>tcp/udp</value></option>
			</options>
			<default_value></default_value>
		</field>
		<field>
			<fielddescr>Custom Gateway</fielddescr>
			<fieldname>agateway_in</fieldname>
			<description><![CDATA[Select alternate Gateway or keep 'default' setting.]]></description>
			<type>select_source</type>
			<source><![CDATA[pfb_get_gateways()]]></source>
			<source_name>name</source_name>
			<source_value>name</source_value>
			<default_value>default</default_value>
			<show_disable_value>default</show_disable_value>
		</field>
		<field>
			<name>Advanced Outbound Firewall Rule Settings</name>
			<type>listtopic</type>
			<collapse>closed</collapse>
		</field>
		<field>
			<type>info</type>
			<description><![CDATA[<span class="text-danger">Note:</span>&nbsp; In general, Auto-Rules are created as follows:<br />
				<dl class="dl-horizontal">
					<dt>Outbound</dt><dd>'any' port, 'any' protocol, 'any' destination and 'any' gateway</dd>
				</dl>
				Configuring the Adv. Outbound Rule settings, will allow for more customization of the Outbound Auto-Rules.]]>
			</description>
		</field>
		<field>
			<fielddescr>Invert Source</fielddescr>
			<fieldname>autoaddrnot_out</fieldname>
			<sethelp><![CDATA[<strong>Invert</strong> - Option to invert the sense of the match.
				ie - Not (!) Source Address(es)]]>
			</sethelp>
			<type>checkbox</type>
		</field>
		<field>
			<fielddescr>Custom Port</fielddescr>
			<fieldname>autoports_out</fieldname>
			<type>checkbox</type>
			<sethelp>Enable</sethelp>
			<enablefields>aliasports_out</enablefields>
			<combinefields>begin</combinefields>
		</field>
		<field>
			<fielddescr>Custom Port</fielddescr>
			<fieldname>aliasports_out</fieldname>
			<description><![CDATA[<a target="_blank" href="/firewall_aliases.php?tab=port">Click Here to add/edit Aliases</a>
				Do not manually enter port numbers.<br />Do not use 'pfB_' in the Port Alias name.]]>
			</description>
			<width>6</width>
			<type>aliases</type>
			<typealiases>port</typealiases>
			<combinefields>end</combinefields>
		</field>
		<field>
			<fielddescr>Custom Source</fielddescr>
			<fieldname>autoaddr_out</fieldname>
			<type>checkbox</type>
			<sethelp>Enable</sethelp>
			<enablefields>aliasaddr_out,autonot_out</enablefields>
			<combinefields>begin</combinefields>
		</field>
		<field>
			<fielddescr>Invert</fielddescr>
			<fieldname>autonot_out</fieldname>
			<type>checkbox</type>
			<sethelp>Invert</sethelp>
			<combinefields/>
		</field>
		<field>
			<fieldname>aliasaddr_out</fieldname>
			<fielddescr>Custom Source</fielddescr>
			<description><![CDATA[<a target="_blank" href="/firewall_aliases.php?tab=ip">Click Here to add/edit Aliases</a>
				Do not manually enter Addresses(es).<br />Do not use 'pfB_' in the 'IP Network Type' Alias name.<br />
				Select 'invert' to invert the sense of the match. ie - Not (!) Source Address(es)]]>
			</description>
			<width>6</width>
			<type>aliases</type>
			<typealiases>network</typealiases>
			<combinefields>end</combinefields>
		</field>
		<field>
			<fielddescr>Custom Protocol</fielddescr>
			<fieldname>autoproto_out</fieldname>
			<description><![CDATA[<strong>Default: any</strong><br />Select the Protocol used for Outbound Firewall Rule(s).<br />
				Do not use 'any' with Adv. Outbound Rules as it will bypass these settings!]]></description>
			<type>select</type>
			<options>
				<option><name>any</name><value></value></option>
				<option><name>TCP</name><value>tcp</value></option>
				<option><name>UDP</name><value>udp</value></option>
				<option><name>TCP/UDP</name><value>tcp/udp</value></option>
			</options>
			<default_value></default_value>
		</field>
		<field>
			<fielddescr>Custom Gateway</fielddescr>
			<fieldname>agateway_out</fieldname>
			<description><![CDATA[Select alternate Gateway or keep 'default' setting.]]></description>
			<type>select_source</type>
			<source><![CDATA[pfb_get_gateways()]]></source>
			<source_name>name</source_name>
			<source_value>name</source_value>
			<default_value>default</default_value>
			<show_disable_value>default</show_disable_value>
		</field>
	</fields>
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

		// Update each Continent XML file.
		@file_put_contents('/usr/local/pkg/pfblockerng/pfblockerng_'.$cont_name.'.xml', $xml,LOCK_EX);

		// Unset Arrays
		unset (${'options4'}, ${'options6'}, $xml);

	}	// End foreach 'Six Continents and Proxy/Satellite' update XML process

	// Sort Countries IPv4 alphabetically and build XML <option> data for Reputation tab (IPv6 not used by ET IQRisk)

	sort($roptions4, SORT_STRING);
	$eoa = count($roptions4);
	$etoptions = '';
	$count = 1;

	foreach ($roptions4 as $option4) {
		if ($count == 1) {
			$et_options .= "\t<option><name>{$option4}\n"; $count++; continue;
		}
		if ($eoa == $count) {
			$et_options .= "\t\t\t\t<option><name>{$option4}";
		} else {
			$et_options .= "\t\t\t\t<option><name>{$option4}\n";
		}
		$count++;
	}

// Update pfBlockerNG_Reputation.xml file with Country Code changes

$xmlrep = <<<EOF
<?xml version="1.0" encoding="utf-8" ?>
<!DOCTYPE packagegui SYSTEM "../schema/packages.dtd">
<?xml-stylesheet type="text/xsl" href="../xsl/package.xsl"?>
<packagegui>
	<copyright>
	<![CDATA[
/* ========================================================================== */
/*
	pfBlockerNG_Reputation.xml

	pfBlockerNG
	Copyright (C) 2015-2016 BBcan177@gmail.com
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
	<name>pfblockerngreputation</name>
	<title>Firewall/pfBlockerNG</title>
	<include_file>/usr/local/pkg/pfblockerng/pfblockerng.inc</include_file>
	<addedit_string>pfBlockerNG: Save Reputation Settings</addedit_string>
	<savehelp><![CDATA[<strong>Click to SAVE Settings and/or Rule edits.&emsp;Changes are applied via CRON or
		'Force Update'</strong>]]>
	</savehelp>
	<menu>
		<name>pfBlockerNG</name>
		<section>Firewall</section>
		<url>pkg_edit.php?xml=pfblockerng.xml</url>
	</menu>
	<tabs>
		<tab>
			<text>General</text>
			<url>/pkg_edit.php?xml=pfblockerng.xml</url>
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
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_reputation.xml</url>
			<active/>
		</tab>
		<tab>
			<text>IPv4</text>
			<url>/pkg.php?xml=/pfblockerng/pfblockerng_v4lists.xml</url>
		</tab>
		<tab>
			<text>IPv6</text>
			<url>/pkg.php?xml=/pfblockerng/pfblockerng_v6lists.xml</url>
		</tab>
		<tab>
			<text>DNSBL</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_dnsbl.xml</url>
		</tab>
		<tab>
			<text>Country</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_top20.xml</url>
		</tab>
		<tab>
			<text>Logs</text>
			<url>/pfblockerng/pfblockerng_log.php</url>
		</tab>
		<tab>
			<text>Sync</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_sync.xml</url>
		</tab>
	</tabs>
	<fields>
		<field>
			<name>IPv4 Reputation</name>
			<type>listtopic</type>
		</field>
		<field>
			<fielddescr>LINKS</fielddescr>
			<description><![CDATA[<a href="/firewall_aliases.php">Firewall Alias</a>&emsp;
				<a href="/firewall_rules.php">Firewall Rules</a>&emsp;<a href="status_logs_filter.php">Firewall Logs</a>]]>
			</description>
			<type>info</type>
		</field>
		<field>
			<fielddescr><![CDATA[<strong>Why Reputation Matters:</strong>]]></fielddescr>
			<type>info</type>
			<description><![CDATA[By Enabling '<strong>Reputation</strong>', each Blocklist will be analyzed for Repeat Offenders in each IP Range.
				<div class="infoblock"><ul>Example: &emsp;x.x.x.1, x.x.x.2, x.x.x.3, x.x.x.4, x.x.x.5<br />
				No. of <strong> Repeat Offending IPs </strong> [ &nbsp;<strong>5</strong>&nbsp; ], in a Blocklist within the same IP Range.</ul>
				With '<strong>Reputation</strong> enabled, these 5 IPs will be removed and a single
				<strong>x.x.x.0/24</strong> Block is used.<br />
				This will completely Block/Reject this particular range from your Firewall.<br /><br />
				Selecting Blocklists from various Threat Sources will help to highlight Repeat Offending IP Ranges,<br />
				Its Important to select a Broad Range of Blocklists that cover different types of Malicious Activity.<br /><br />
				You *may* experience some False Positives. Add any False Positive IPs manually to the<br />
				<strong>pfBlockerNGSuppress Alias</strong> or use the "+" suppression Icon in the Alerts TAB<br /><br />
				To help mitigate False Positives 'Countries' can be '<strong>Excluded</strong>' from this Process. (Refer to Country Code Settings)
				<br /><br />Enabling <strong>De-Duplication</strong> is highly recommended before utilizing 'Reputation' processes.</div>]]>
			</description>
		</field>
		<field>
			<fielddescr><![CDATA[<strong>Individual List Reputation</strong>]]></fielddescr>
			<type>info</type>
		</field>
		<field>
			<fielddescr><![CDATA[Enable Max]]></fielddescr>
			<fieldname>enable_rep</fieldname>
			<type>checkbox</type>
			<sethelp><![CDATA[Enables Search for Repeat Offenders in a /24 Range on <strong>Each Individual Blocklist</strong>]]></sethelp>
		</field>
		<field>
			<fielddescr><![CDATA[&emsp;[ <strong>Max</strong> ] Setting]]></fielddescr>
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
			<fielddescr><![CDATA[<strong>Collective List Reputation</strong>]]></fielddescr>
			<type>info</type>
			<description></description>
		</field>
		<field>
			<type>info</type>
			<description><![CDATA[Once all Blocklists are Downloaded, these two 'additional' processes <strong>[ pMax ] and [ dMax ]</strong><br />
				Can be used to Further analyze for Repeat Offenders.<div class="infoblock">
				<ul>Analyzing All Blocklists as a Whole:</ul>
				<ul><strong>[ pMax ]</strong> will analyze for Repeat Offenders in each IP Range but will not use the Country Exclusion.<br />
				Default is 50 IPs in any Range. Having 50 Repeat Offenders IPs in any Range will Block the entire Range.<br /><br /></ul>
				<ul><strong>[ dMax ]</strong> will analyze for Repeat Offenders in each IP Range. Country Exclusions will be applied.<br />
				Default is 5 IPs in any Range.</ul>
				Note: <strong>MAX</strong> performs on individual Blocklists, while <strong>pMAX / dMAX</strong>
				perform on all Lists together.</div>]]>
			</description>
		</field>
		<field>
			<fielddescr>Enable pMAX</fielddescr>
			<fieldname>enable_pdup</fieldname>
			<type>checkbox</type>
			<sethelp><![CDATA[Enables Search for Repeat Offenders in All BlockLists, <strong>Without</strong> Country Code Exclusion]]>
			</sethelp>
		</field>
		<field>
			<fielddescr><![CDATA[&emsp;[ <strong>pMax</strong> ] Setting]]></fielddescr>
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
			<sethelp><![CDATA[Enables Search for Repeat Offenders in All BlockLists <strong>Using</strong> Country Code Exclusion]]>
			</sethelp>
		</field>
		<field>
			<fielddescr><![CDATA[&emsp;[ <strong>dMax</strong> ] Setting]]></fielddescr>
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
			<name>Country Code Settings (max/dMax)</name>
			<type>listtopic</type>
		</field>
		<field>
			<type>info</type>
			<description><![CDATA[When performing Queries for Repeat Offenders, you can choose to <strong>ignore</strong> Repeat Offenders in select
				Countries. The Original Blocklisted IPs remain intact. All other Repeat Offending Country Ranges will be processed.
				<div class="infoblock">Define Repeat Offending Ranges [ <strong>Action</strong> ] Available settings are:<br />
				<ul><strong>Ignore</strong>: Repeat Offenders that are in the 'ccwhite' category will be 'Ignored' (Default)</ul>
				<ul><strong>Block:</strong> Repeat Offenders are set to Block the entire Repeat Offending Range(s)</ul>
				<ul><strong>Match:</strong> Repeat Offenders are added to a 'Match' List which can be used in a Floating Match Rule<br />
				Selecting 'Match' will consume more processing time, so only select this option if you enable Rules for it.</ul>
				'<strong>ccwhite</strong>' are Countries that are Selected to be excluded from the Repeat Offenders Search.<br />
				'<strong>ccblack</strong>' are all other Countries that are not selected.<br /><br />
				To use '<strong>Match</strong>' Lists, Create a new 'Alias'
				and select one of the <strong>Action 'Match'</strong> Formats and<br /> enter the 'Localfile' as:
				<ul>/var/db/pfblockerng/match/matchdedup.txt</ul></div>]]>
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
				<br />Geolite Data by: <br />MaxMind Inc.&emsp;(ISO 3166)]]></fielddescr>
			<fieldname>ccexclude</fieldname>
			<description>
				<![CDATA[Select Countries you want to <strong>Exclude</strong> from the Reputation Process.<br />
				<strong>Use CTRL&nbsp;+&nbsp;CLICK to select/unselect countries</strong>]]>
			</description>
			<type>select</type>
			<options>
			{$et_options}
			</options>
			<size>20</size>
			<multiple/>
		</field>
		<field>
			<name>Proofpoint ET IQRISK IPv4 Reputation</name>
			<type>listtopic</type>
			<collapse>closed</collapse>
		</field>
		<field>
			<fielddescr>Subscription Pro. Blocklist</fielddescr>
			<type>info</type>
			<description><![CDATA[<strong>Proofpoint ET IQRisk</strong> is a Subscription Professional Reputation List.<br /><br />
					<strong>The URL must include the name 'iprepdata.txt' for the filename.</strong><br />
					ET IQRisk Blocklist must be entered in the Lists Tab using the following example:
					<ul>https://rules.emergingthreatspro.com/XXXXXXXXXXXXXXXX/reputation/iprepdata.txt.gz</ul>
					Select the <strong>ET IQRisk'</strong> format. The URL should use the .gz File Type.<br />
					Enter your "ETPRO" code in URL. Further information can be found @
					<a target="_blank" href="https://www.proofpoint.com/us/solutions/products/threat-intelligence">Proofpoint IQRisk</a><br /><br />
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
				<strong>Use CTRL&nbsp;+&nbsp;CLICK to select/unselect Categories</strong>
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
				<strong>Use CTRL&nbsp;+&nbsp;CLICK to select/unselect Categories</strong>
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
	</fields>
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
	$log = " Saving pfBlockerNG Reputation TAB\n";
	print ("{$log}");
	pfb_logger("{$log}", 3);

	// Save pfBlockerng_reputation.xml file
	@file_put_contents('/usr/local/pkg/pfblockerng/pfblockerng_reputation.xml', $xmlrep, LOCK_EX);

	$now = date('m/d/y G.i:s', time());
	$log = "Country Code Update Ended - [ NOW ]\n\n";
	if (!$g['pfblockerng_install']) {
		print ("Country Code Update Ended - [ {$now} ]\n\n");
	}
	pfb_logger("{$log}", 3);

	// Unset arrays
	unset($roptions4, $et_options, $xmlrep);
}
?>
