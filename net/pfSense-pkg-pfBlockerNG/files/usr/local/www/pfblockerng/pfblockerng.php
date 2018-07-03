<?php
/*
 * pfblockerng.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2016 BBcan177@gmail.com
 * All rights reserved.
 *
 * Originally based upon pfBlocker by
 * Copyright (c) 2011 Marcello Coutinho
 * All rights reserved.
 *
 * Hour Schedule Convertor code by Snort Package
 * Copyright (c) 2016 Bill Meeks
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

require_once('util.inc');
require_once('functions.inc');
require_once('pkg-utils.inc');
require_once('globals.inc');
require_once('services.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng_extra.inc');	// 'include functions' not yet merged into pfSense

global $config, $g, $pfb;

// Extras - MaxMind/Alexa Download URLs/filenames/settings
$pfb['extras'][0]['url']	= 'https://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz';
$pfb['extras'][0]['file_dwn']	= 'GeoIP.dat.gz';
$pfb['extras'][0]['file']	= 'GeoIP.dat';
$pfb['extras'][0]['folder']	= "{$pfb['geoipshare']}";

$pfb['extras'][1]['url']	= 'https://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz';
$pfb['extras'][1]['file_dwn']	= 'GeoIPv6.dat.gz';
$pfb['extras'][1]['file']	= 'GeoIPv6.dat';
$pfb['extras'][1]['folder']	= "{$pfb['geoipshare']}";

$pfb['extras'][2]['url']	= 'https://geolite.maxmind.com/download/geoip/database/GeoLite2-Country-CSV.zip';
$pfb['extras'][2]['file_dwn']	= 'GeoLite2-Country-CSV.zip';
$pfb['extras'][2]['file']	= '';
$pfb['extras'][2]['folder']	= "{$pfb['geoipshare']}";

$pfb['extras'][3]['url']	= 'https://s3.amazonaws.com/alexa-static/top-1m.csv.zip';
$pfb['extras'][3]['file_dwn']	= 'top-1m.csv.zip';
$pfb['extras'][3]['file']	= 'top-1m.csv';
$pfb['extras'][3]['folder']	= "{$pfb['dbdir']}";

// Call include file and collect updated Global settings
if (in_array($argv[1], array('update', 'updateip', 'updatednsbl', 'dc', 'dcc', 'bu', 'uc', 'gc', 'al', 'cron', 'ugc'))) {
	pfb_global();

	$pfb['extras_update'] = FALSE;  // Flag when Extras (MaxMind/Alexa) are updateded via cron job

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
		case 'dcc':

			// 'dcc' called via Cron job
			if ($argv[1] == 'dcc') {

				// Only update on first Tuesday of each month
				if (date('D') != 'Tue') {
					exit;
				}
				$pfb['extras_update'] = TRUE;
			}

			// If 'General Tab' skip MaxMind download setting if checked, only download binary updates for Reputation/Alerts page.
			if (!empty($pfb['cc'])) {
				unset($pfb['extras'][2]);
			}

			// Skip Alexa update, if disabled
			if ($pfb['dnsbl_alexa'] != 'on') {
				unset($pfb['extras'][3]);
			}

			// Proceed with conversion of MaxMind files on download success
			if (empty($pfb['cc']) && pfblockerng_download_extras()) {
				pfblockerng_uc_countries();
				pfblockerng_get_countries();
			}

			break;
		case 'bu':		// Update MaxMind binary database files only.
			unset($pfb['extras'][2], $pfb['extras'][3]);
			pfblockerng_download_extras();
			break;
		case 'al':		// Update Alexa database only.
			unset($pfb['extras'][0], $pfb['extras'][1], $pfb['extras'][2]);
			pfblockerng_download_extras();
			break;
		case 'uc':		// Update MaxMind ISO files from local database files.
			pfblockerng_uc_countries();
			break;
		case 'gc':		// Update Continent XML files.
			pfblockerng_get_countries();
			break;
		case 'ugc':
			pfblockerng_uc_countries();
			pfblockerng_get_countries();

			if (!empty($argv[2]) && !empty($argv[3])) {
				file_notice('pfBlockerNG', "The MaxMind GeoIP Locale has been changed from [ {$argv[2]} ]"
						. " to [ {$argv[3]} ]", gettext('MaxMind Locale Changed'), '', 0);
			}
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

			// On Extras update (MaxMind and Alexa), if error found when downloading MaxMind Country database
			// return error to update process
			if ($feed['file_dwn'] == 'GeoLite2-Country-CSV.zip') {
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

							// Attempt download, when a previous 'fail' file marker is found.
							if (file_exists("{$pfbfolder}/{$header}.fail")) {
								pfb_update_check($header, $row['url'], $pfbfolder, $pfborig, $pflex, $row['format']);
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

	// Create folders if not exist
	$folder_array = array ("{$pfb['dbdir']}", "{$pfb['logdir']}", "{$pfb['ccdir']}");
	foreach ($folder_array as $folder) {
		safe_mkdir ("{$folder}", 0755);
	}

	$log = "Country code update Start [ NOW ]\n";
	pfb_logger("{$log}", 4);

	$maxmind_cont = "{$pfb['geoipshare']}/GeoLite2-Country-Locations-{$pfb['maxmind_locale']}.csv";
	if (!file_exists($maxmind_cont)) {
		$log = " [ MAXMIND UPDATE FAIL, Language File Missing, using previous Country code database ] [ NOW ]\n";
		pfb_logger("{$log}", 4); 
		return;
	}

	// Save Date/Time stamp to MaxMind version file
	$local_tds	 = @gmdate('D, d M Y H:i:s T', @filemtime($maxmind_cont));
	$maxmind_ver	 = "MaxMind GeoLite2 Date/Time Stamp\n";
	$maxmind_ver	.= "Last-Modified: {$local_tds}\n";
	@file_put_contents("{$pfb['logdir']}/maxmind_ver", $maxmind_ver, LOCK_EX);

	// Collect ISO codes for each Continent
	$log = " Converting MaxMind Country databases for pfBlockerNG.\n";
	pfb_logger("{$log}", 4);

	// Remove any previous working files
	rmdir_recursive("{$pfb['ccdir_tmp']}");
	safe_mkdir("{$pfb['ccdir_tmp']}");

	rmdir_recursive("{$pfb['ccdir']}");
	safe_mkdir("{$pfb['ccdir']}");

	$pfb_geoip = array();

	$top_20 = array('CN', 'RU', 'JP', 'UA', 'GB', 'DE', 'BR', 'FR', 'IN', 'TR',
			'IT', 'KR', 'PL', 'ES', 'VN', 'AR', 'CO', 'TW', 'MX', 'CL');

	// Read GeoLite2 database and create array by geoname_ids
	if (($handle = @fopen("{$maxmind_cont}", 'r')) !== FALSE) {
		while (($cc = @fgetcsv($handle)) !== FALSE) {

			if ($cc[0] == 'geoname_id') {
				continue;
			}

			/*	Sample MaxMind lines:
				geoname_id,locale_code,continent_code,continent_name,country_iso_code,country_name
				49518,en,AF,Africa,RW,Rwanda	*/

			if (!empty($cc[0]) && !empty($cc[1]) && !empty($cc[2]) && !empty($cc[3]) && !empty($cc[4]) && !empty($cc[5])) {
				$pfb_geoip['country'][$cc[0]] = array('id' => $cc[0], 'continent' => $cc[3], 'name' => $cc[5], 'iso' => array("{$cc[4]}"));

				// Collect English Continent name for filenames only
				if ($cc[1] != 'en') {
					$geoip_en	= str_replace("Locations-{$pfb['maxmind_locale']}", 'Locations-en', $maxmind_cont);
					$continent_en	= exec("{$pfb['grep']} -m1 ',en,{$cc[2]}' {$geoip_en} | {$pfb['cut']} -d',' -f4");
				} else {
					$continent_en	= "{$cc[3]}";
				}
				$continent_en = str_replace(array(' ', '"'), array('_', ''), $continent_en);
				$pfb_geoip['country'][$cc[0]]['continent_en'] = "{$continent_en}";

				// Collect data for TOP 20 tab
				if (in_array($cc[4], $top_20)) {
					$order = array_keys($top_20, $cc[4]);
					$top20 = 'A' . str_pad($order[0], 5, '0', STR_PAD_LEFT);
					$pfb_geoip['country'][$top20] = array('name' => $cc[5], 'iso' => $cc[4], 'id' => $cc[0]);
				}
			}
		}

		unset($cc);
		@fclose($handle);
	}

	// Add 'Proxy and Satellite' geoname_ids
	$pfb_geoip['country']['proxy']		= array('continent' => 'Proxy and Satellite', 'name' => 'Proxy', 'iso' => array('A1'),
							'continent_en' => 'Proxy_and_Satellite');
	$pfb_geoip['country']['satellite']	= array('continent' => 'Proxy and Satellite', 'name' => 'Satellite', 'iso' => array('A2'),
							'continent_en' => 'Proxy_and_Satellite');

	// Add 'Asia/Europe' undefined geoname_ids
	$pfb_geoip['country']['6255147']	= array('continent' => 'Asia', 'name' => 'AA ASIA UNDEFINED', 'iso' => array('6255147'),
							'continent_en' => 'Asia');
	$pfb_geoip['country']['6255148']	= array('continent' => 'Europe', 'name' => 'AA EUROPE UNDEFINED', 'iso' => array('6255148'),
							'continent_en' => 'Europe');

	ksort($pfb_geoip['country'], SORT_NATURAL);

	// Collect Country ISO data and sort to Continent arrays (IPv4 and IPv6)
	foreach (array('4', '6') as $type) {
	
		$log = " Processing ISO IPv{$type} Continent/Country Data [ NOW ]\n";
		pfb_logger("{$log}", 4);

		$geoip_dup = 0;		// Count of Geoname_ids which have both a different 'Registered and Represented' geoname_id

		$maxmind_cc = "{$pfb['geoipshare']}/GeoLite2-Country-Blocks-IPv{$type}.csv";
		if (($handle = @fopen("{$maxmind_cc}", 'r')) !== FALSE) {
			while (($cc = @fgetcsv($handle)) !== FALSE) {

				/*	Sample lines:
					Network,geoname_id,registered_country_geoname_id,represented_country_geoname_id,is_anonymous_proxy,is_satellite_provider
					1.0.0.0/24,2077456,2077456,,0,0		*/

				if ($cc[0] == 'network') {
					continue;
				}

				$iso = $iso_rep = '';

				// Is Anonymous Proxy?
				if ($cc[4] == 1) {

					if (!empty($cc[1])) {
						$iso = "A1_{$pfb_geoip['country']['proxy']['iso'][0]}";
					}
					if (!empty($cc[2]) && $cc[1] != $cc[2]) {
						$geoip_dup++;
						$iso_rep = "A1_{$pfb_geoip['country'][$cc[2]]['iso'][0]}_rep";
					}
					if (empty($cc[1]) && empty($cc[2])) {
						$iso = 'A1';
					}
					$cc[2] = 'proxy';	// Re-define variable
				}

				// Is Satellite Provider?
				elseif ($cc[5] == 1) {

					if (!empty($cc[1])) {
						$iso = "A2_{$pfb_geoip['country']['satellite']['iso'][0]}";
					}
					if (!empty($cc[2]) && $cc[1] != $cc[2]) {
						$geoip_dup++;
						$iso_rep = "A2_{$pfb_geoip['country'][$cc[2]]['iso'][0]}_rep";
					}
					if (empty($cc[1]) && empty($cc[2])) {
						$iso = 'A2';
					}
					$cc[2] = 'satellite';	// Re-define variable
				}
				else {
					if (!empty($cc[1])) {
						$iso = "{$pfb_geoip['country'][$cc[1]]['iso'][0]}";
					}
					if (!empty($cc[2]) && $cc[1] != $cc[2]) {
						$geoip_dup++;
						$iso_rep = "{$pfb_geoip['country'][$cc[2]]['iso'][0]}_rep";
					}
				}

				// Add 'ISO Represented' to Country ISO list
				if (!empty($iso_rep) && !empty($cc[2])) {

					// Only add if not existing
					if (!isset($pfb_geoip['country'][$cc[2]]) || !in_array($iso_rep, $pfb_geoip['country'][$cc[2]]['iso'])) {
						$pfb_geoip['country'][$cc[2]]['iso'][] = "{$iso_rep}";
					}
				}

				// Add placeholder for 'undefined ISO Represented' to Country ISO list
				elseif ($type == '4' && empty($iso_rep)) {

					foreach (array('' => $cc[1], 'A1_' => 'proxy', 'A2_' => 'satellite') as $reptype => $iso_placeholder) {

						if (!empty($cc[1])) {
							$iso_rep_placeholder = "{$reptype}{$pfb_geoip['country'][$cc[1]]['iso'][0]}_rep";

							// Only add if not existing
							if (!isset($pfb_geoip['country'][$iso_placeholder])
							    || !in_array($iso_rep_placeholder, $pfb_geoip['country'][$iso_placeholder]['iso'])) {

								$pfb_geoip['country'][$iso_placeholder]['iso'][] = "{$iso_rep_placeholder}";
							}
						}
					}
                                }

				// Save 'ISO Registered Network' to ISO file
				if (!empty($iso) && !empty($cc[0])) {
					$file = "{$pfb['ccdir_tmp']}/{$iso}_v{$type}.txt";
					@file_put_contents("{$file}", "{$cc[0]}\n", FILE_APPEND | LOCK_EX);
				}

				// Save ISO 'Represented Network' to ISO file
				if (!empty($iso_rep) && !empty($cc[0])) {
					$file = "{$pfb['ccdir_tmp']}/{$iso_rep}_v{$type}.txt";
					@file_put_contents("{$file}", "{$cc[0]}\n", FILE_APPEND | LOCK_EX);
				}
			}

			// Report number of Geoname_ids which have both a different 'Registered and Represented' geoname_id
			if ($geoip_dup != 0) {
				@file_put_contents("{$pfb['logdir']}/maxmind_ver", "Duplicate Represented IP{$type} Networks: {$geoip_dup}\n", FILE_APPEND | LOCK_EX);
			}

			// Create Continent txt files
			if (!empty($pfb_geoip['country'])) {
				foreach ($pfb_geoip['country'] as $key => $geoip) {

					// Save 'TOP 20' data
					if (strpos($key, 'A000') !== FALSE) {
						$pfb_file = "{$pfb['ccdir']}/Top_20_v{$type}.info";

						if (!file_exists($pfb_file)) {
							$header  = '# Generated from MaxMind Inc. on: ' . date('m/d/y G:i:s', time()) . "\n";
							$header .= "# Continent IPv{$type}: TopSpammers\n";
							$header .= "# Continent en: TopSpammers\n";
							@file_put_contents($pfb_file, $header, LOCK_EX);
						}

						$iso_header  = "# Country: {$geoip['name']} ({$geoip['id']})\n";
						$iso_header .= "# ISO Code: {$geoip['iso']}\n";
						$iso_header .= "# Total Networks: Top20\n";
						$iso_header .= "Top20\n";

						// Add any 'TOP 20' Represented ISOs Networks
						if (file_exists("{$pfb['ccdir_tmp']}/{$geoip['iso']}_rep_v{$type}.txt")) {
							$iso_header .= "# Country: {$geoip['name']} ({$geoip['id']})\n";
							$iso_header .= "# ISO Code: {$geoip['iso']}_rep\n";
							$iso_header .= "# Total Networks: Top20\n";
							$iso_header .= "Top20\n";
						}
						@file_put_contents($pfb_file, $iso_header, FILE_APPEND | LOCK_EX);
					}

					else {
						if (!empty($geoip['continent_en'])) {

							$pfb_file = "{$pfb['ccdir']}/{$geoip['continent_en']}_v{$type}.txt";
							if (!file_exists($pfb_file)) {
								$header  = '# Generated from MaxMind Inc. on: ' . date('m/d/y G:i:s', time()) . "\n";
								$header .= "# Continent IPv{$type}: {$geoip['continent']}\n";
								$header .= "# Continent en: {$geoip['continent_en']}\n";
								@file_put_contents($pfb_file, $header, LOCK_EX);
							}

							if (!empty($geoip['iso'])) {
								foreach ($geoip['iso'] as $iso) {

									$iso_file = "{$pfb['ccdir_tmp']}/{$iso}_v{$type}.txt";
									$geoip_id = '';
									if (!empty($geoip['id'])) {
										$geoip_id = " [{$geoip['id']}]";
									}

									if (file_exists($iso_file)) {
										$networks = exec("{$pfb['grep']} -c ^ {$iso_file} 2>&1");
										$iso_header  = "# Country: {$geoip['name']}{$geoip_id}\n";
										$iso_header .= "# ISO Code: {$iso}\n";
										$iso_header .= "# Total Networks: {$networks}\n";
										@file_put_contents($pfb_file, $iso_header, FILE_APPEND | LOCK_EX);

										// Concat ISO Networks to Continent file
										exec("{$pfb['cat']} {$iso_file} >> {$pfb_file} 2>&1");
									}
									else {
										// Create placeholder file for undefined 'ISO Represented'
										$iso_header  = "# Country: {$geoip['name']}{$geoip_id}\n";
										$iso_header .= "# ISO Code: {$iso}\n";
										$iso_header .= "# Total Networks: NA\n";
										@file_put_contents($pfb_file, $iso_header, FILE_APPEND | LOCK_EX);
									}
								}

								// Reset ISOs to original setting (Remove any Represented ISOs)
								$pfb_geoip['country'][$key]['iso'] = array($pfb_geoip['country'][$key]['iso'][0]);
							}
							else {
								$log = "\n Missing ISO data: {$geoip['continent']}";
								pfb_logger("{$log}", 4);
								
							}
						}
						else {
							$log = "\n Failed to create Continent file: {$geoip['continent']}";
							pfb_logger("{$log}", 4);
						}
					}
				}
			}
			unset($cc);
			@fclose($handle);
		}
		else {
			$log = "\n Failed to load file: {$maxmind_cc}\n";
			pfb_logger("{$log}", 4);
		}
	}
	unset($pfb_geoip);
	rmdir_recursive("{$pfb['ccdir_tmp']}");
}


// Function to process Continent txt files and create Country ISO files and to Generate GUI XML files.
function pfblockerng_get_countries() {
	global $g, $pfb;

	$geoip_files = array (	'Africa'		=> "{$pfb['ccdir']}/Africa_v4.txt",
				'Antarctica'		=> "{$pfb['ccdir']}/Antarctica_v4.txt",
				'Asia'			=> "{$pfb['ccdir']}/Asia_v4.txt",
				'Europe'		=> "{$pfb['ccdir']}/Europe_v4.txt",
				'North America'		=> "{$pfb['ccdir']}/North_America_v4.txt",
				'Oceania'		=> "{$pfb['ccdir']}/Oceania_v4.txt",
				'South America'		=> "{$pfb['ccdir']}/South_America_v4.txt",
				'Proxy and Satellite'	=> "{$pfb['ccdir']}/Proxy_and_Satellite_v4.txt",
				'TOP 20'		=> "{$pfb['ccdir']}/Top_20_v4.info"
				);

	// Collect data to generate new continent XML files.
	$log = " Creating pfBlockerNG Continent XML files\n";
	pfb_logger("{$log}", 4);

	foreach ($geoip_files as $cont => $file) {

		// Process the following for IPv4 and IPv6
		foreach (array('4', '6') as $type) {

			$cont_length = strlen($cont);
			if ($cont_length < 8) {
				$tab = "\t\t\t";
			} elseif ($cont_length < 19) {
				$tab = "\t\t";
			} else {
				$tab = "\t";
			}

			$log = " IPv{$type} {$cont}{$tab} [ NOW ]\n";
			pfb_logger("{$log}", 4);

			if ($type == '6') {
				$file = str_replace('v4', 'v6', $file);
			}

			$active			= array("{$cont}" => '<active/>');
			$lastline		= exec("{$pfb['grep']} -c ^ {$file}") ?: 0;
			$pfb['complete']	= FALSE;
			$linenum		= 1;
			$total			= 0;

			if (($handle = @fopen("{$file}", 'r')) !== FALSE) {
				while (($line = @fgets($handle, 1024)) !== FALSE) {

					$line = trim($line);
					if (substr($line, 0, 1) == '#') {
						if ($pfb['complete']) {
							if (file_exists("{$pfb['ccdir']}/{$isocode}_v{$type}.txt")) {
								${'coptions' . $type}[] = "{$country} {$isocode} ({$total})</name><value>{$isocode}</value></option>";
								// Only collect IPv4 for Reputation Tab
								if ($type == '4' && strpos($isocode, '_rep') === FALSE) {
									$roptions4[] = "{$country} {$isocode} ({$total})</name><value>{$isocode}</value></option>";
								}
							}

							// Clear variables and restart Continent collection process
							$total = 0;
							$pfb['complete'] = FALSE;
						}

						if (strpos($line, 'Continent IPv') !== FALSE) {
							$continent = trim(str_replace(':', '', strstr($line, ':', FALSE)));
							// $geoip_title[$cont]	= "{$continent}";	// Not yet implemented
						}
						if (strpos($line, 'Continent en:') !== FALSE) {
							$cont_name		= trim(str_replace(':', '', strstr($line, ':', FALSE)));
							$cont_name_lower	= strtolower($cont_name);
						}
						if (strpos($line, 'Country: ') !== FALSE) {
							$country = str_replace('# Country: ', '', $line);
						}
						if (strpos($line, 'ISO Code: ') !== FALSE) {
							$isocode = str_replace('# ISO Code: ', '', $line);

							// Remove previous ISO file
							if ($cont != 'TOP 20') {
								unlink_if_exists("{$pfb['ccdir']}/{$isocode}_v{$type}.txt");
							}
						}

						// Create placeholder for null ISO Data or 'undefined ISO Represented'
						if (strpos($line, 'Total Networks: 0') !== FALSE ||
						    ($type == '4' && strpos($line, 'Total Networks: NA') !== FALSE)) {
							$pfb['complete'] = TRUE;
							@file_put_contents("{$pfb['ccdir']}/{$isocode}_v{$type}.txt", '', LOCK_EX);
						}
					}

					elseif (substr($line, 0, 1) != '#') {
						if ($cont == 'TOP 20') {
							$total = exec("{$pfb['grep']} -c ^ {$pfb['ccdir']}/{$isocode}_v{$type}.txt 2>&1");
						} else {
							$total++;
							if (!empty($line)) {
								@file_put_contents("{$pfb['ccdir']}/{$isocode}_v{$type}.txt", "{$line}\n", FILE_APPEND | LOCK_EX);
							}
						}
						$pfb['complete'] = TRUE;
					}

					// Save last EOF ISO IP data
					if ($linenum == $lastline) {
						// Create placeholder for null ISO Data or 'undefined ISO Represented'
						if (strpos($line, 'Total Networks: 0') !== FALSE ||
						    ($type == '4' && strpos($line, 'Total Networks: NA') !== FALSE)) {
							@file_put_contents("{$pfb['ccdir']}/{$isocode}_v{$type}.txt", '', LOCK_EX);
						}

						if (file_exists("{$pfb['ccdir']}/{$isocode}_v{$type}.txt")) {
							${'coptions' . $type}[] = "{$country} {$isocode} ({$total})</name><value>{$isocode}</value></option>";
							if ($type == '4' && strpos($isocode, '_rep') === FALSE) {
								$roptions4[] = "{$country} {$isocode} ({$total})</name><value>{$isocode}</value></option>";
							}
						}
					}
					$linenum++;
				}
			}
			@fclose($handle);

			// Sort IP Countries alphabetically and build XML <option> data for Continents tab
			if (!empty(${'coptions' . $type})) {
				if ($cont != 'TOP 20') {
					sort(${'coptions' . $type}, SORT_STRING);
				}
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
	<savehelp><![CDATA[<strong>Click to SAVE Settings and/or Rule edits.&emsp;Changes are applied via CRON or
		'Force Update'</strong>]]>
	</savehelp>
	<menu>
		<name>pfBlockerNG: {$continent}</name>
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
			<text>GeoIP</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_TopSpammers.xml</url>
		</tab>
		<tab>
			<text>Top 20</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_TopSpammers.xml</url>
			<tab_level>2</tab_level>
			{$active['TOP 20']}
		</tab>
		<tab>
			<text>Africa</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Africa.xml</url>
			<tab_level>2</tab_level>
			{$active['Africa']}
		</tab>
		<tab>
			<text>Antarctica</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Antarctica.xml</url>
			<tab_level>2</tab_level>
			{$active['Antarctica']}
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
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_North_America.xml</url>
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
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_South_America.xml</url>
			<tab_level>2</tab_level>
			{$active['South America']}
		</tab>
		<tab>
			<text>Proxy and Satellite</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_Proxy_and_Satellite.xml</url>
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
			<name><![CDATA[Continent - {$continent} &emsp;(GeoIP data by MaxMind Inc. - GeoLite2)]]></name>
			<type>listtopic</type>
		</field>
		<field>
			<fielddescr>NOTES</fielddescr>
			<description><![CDATA[Click here for IMPORTANT info on:&emsp;
				<a target="_blank" href="https://dev.maxmind.com/geoip/geoip2/whats-new-in-geoip2/">
				<span class="text-danger"><strong>What's new in GeoIP2</strong></span></a><br /><br /> 

				<span class="text-danger"><strong>Note:&emsp;</strong></span>
				pfSense by default implicitly blocks all unsolicited inbound traffic to the WAN interface.<br />
				Therefore adding GeoIP based firewall rules to the WAN will <strong>not</strong> provide any benefit, unless there are
				open WAN ports.<br /><br />
				It's also <strong>not</strong> recommended to block the 'world', instead consider rules to 'Permit' traffic from
				selected Countries only.<br />
				Also consider protecting just the specific open WAN ports and it's just as important to protect the outbound LAN traffic.]]>
			</description>
			<type>info</type>
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
			<fielddescr>Countries - use CTRL+CLICK to select/unselect Countries (New: Represented IPs)</fielddescr>
			<type>select</type>
			<options>
			${'options4'}
			</options>
			<size>${'ftotal4'}</size>
			<width>4</width>
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
			<width>4</width>
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
			<fielddescr>Custom DST Port</fielddescr>
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
			<fielddescr>Invert Destination</fielddescr>
			<fieldname>autoaddrnot_out</fieldname>
			<sethelp><![CDATA[<strong>Invert</strong> - Option to invert the sense of the match.
				ie - Not (!) Destination Address(es)]]>
			</sethelp>
			<type>checkbox</type>
		</field>
		<field>
			<fielddescr>Custom DST Port</fielddescr>
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
		@file_put_contents("/usr/local/pkg/pfblockerng/pfblockerng_{$cont_name}.xml", $xml, LOCK_EX);

		// Unset Arrays
		unset(${'options4'}, ${'options6'}, $xml);

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
			<text>GeoIP</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_TopSpammers.xml</url>
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
			<fielddescr>IPv4 Country Exclusion</fielddescr>
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
	$log = " pfBlockerNG Reputation Tab\n";
	pfb_logger("{$log}", 4);

	// Save pfBlockerng_reputation.xml file
	@file_put_contents('/usr/local/pkg/pfblockerng/pfblockerng_reputation.xml', $xmlrep, LOCK_EX);

	$log = "Country Code Update Ended [ NOW ]\n\n";
	pfb_logger("{$log}", 4);

	// Unset arrays
	unset($roptions4, $et_options, $xmlrep);

	// Save MaxMind GeoIP Language Locale to file
	@file_put_contents("{$pfb['dbdir']}/GeoIP_Locale", "{$pfb['maxmind_locale']}\n", LOCK_EX);
}
?>
