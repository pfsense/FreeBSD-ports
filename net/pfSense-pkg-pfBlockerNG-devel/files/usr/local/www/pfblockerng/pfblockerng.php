<?php
/*
 * pfblockerng.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2018 BBcan177@gmail.com
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

if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' && $_REQUEST && $_REQUEST['pfb']) {

	$query = htmlspecialchars($_REQUEST['pfb']);
	$file = "/var/db/aliastables/{$query}.txt";
	if (file_exists($file)) {
		$return = file_get_contents($file);
		print $return;
	}
	exit;
}

require_once('util.inc');
require_once('functions.inc');
require_once('pkg-utils.inc');
require_once('globals.inc');
require_once('services.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng_extra.inc');	// 'include functions' not yet merged into pfSense

global $config, $g, $pfb;

// Clear IP/DNSBL counters via CRON
if (isset($argv[1])) {
	if ($argv[1] == 'clearip') {
		pfBlockerNG_clearip();
		exit;
	}
	elseif ($argv[1] == 'cleardnsbl') {
		pfBlockerNG_cleardnsbl('clearall');
		exit;
	}
}

// Extras - MaxMind/TOP1M Download URLs/filenames/settings
$pfb['extras'][0]['url']	= 'https://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz';
$pfb['extras'][0]['file_dwn']	= 'GeoIP.dat.gz';
$pfb['extras'][0]['file']	= 'GeoIP.dat';
$pfb['extras'][0]['folder']	= "{$pfb['geoipshare']}";
$pfb['extras'][0]['type']	= 'geoip';

$pfb['extras'][1]['url']	= 'https://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz';
$pfb['extras'][1]['file_dwn']	= 'GeoIPv6.dat.gz';
$pfb['extras'][1]['file']	= 'GeoIPv6.dat';
$pfb['extras'][1]['folder']	= "{$pfb['geoipshare']}";
$pfb['extras'][1]['type']	= 'geoip';

$pfb['extras'][2]['url']	= 'https://geolite.maxmind.com/download/geoip/database/GeoLite2-Country-CSV.zip';
$pfb['extras'][2]['file_dwn']	= 'GeoLite2-Country-CSV.zip';
$pfb['extras'][2]['file']	= '';
$pfb['extras'][2]['folder']	= "{$pfb['geoipshare']}";
$pfb['extras'][2]['type']	= 'geoip';

if ($pfb['dnsbl_alexatype'] == 'Alexa') {
	$pfb['extras'][3]['url']	= 'https://s3.amazonaws.com/alexa-static/top-1m.csv.zip';
} else {
	$pfb['extras'][3]['url']	= 'https://s3-us-west-1.amazonaws.com/umbrella-static/top-1m.csv.zip';
}
$pfb['extras'][3]['file_dwn']	= 'top-1m.csv.zip';
$pfb['extras'][3]['file']	= 'top-1m.csv';
$pfb['extras'][3]['folder']	= "{$pfb['dbdir']}";
$pfb['extras'][3]['type']	= 'top1m';


if ($argv[1] == 'bl' || $argv[1] == 'bls') {

	if (!empty($argv[2]) && $pfb['blconfig'] &&
	    !empty($pfb['blconfig']['blacklist_selected']) &&
	    isset($pfb['blconfig']['item'])) {

		$key = 4;
		$selected = array_flip(explode(',', $argv[2])) ?: array();
		foreach ($pfb['blconfig']['item'] as $item) {
			if (isset($selected[$item['xml']])) {
				$pfb['extras'][$key]['url']		= $item['feed'];
				$pfb['extras'][$key]['name']		= $item['title'];
				$pfb['extras'][$key]['file_dwn']	= pathinfo($item['feed'], PATHINFO_BASENAME);
				$pfb['extras'][$key]['file']		= pathinfo($item['feed'], PATHINFO_BASENAME);
				$pfb['extras'][$key]['folder']		= "{$pfb['dbdir']}";
				$pfb['extras'][$key]['type']		= 'blacklist';

				if (isset($item['username']) && isset($item['password'])) {
					$pfb['extras'][$key]['username'] = $item['username'];
					$pfb['extras'][$key]['password'] = $item['password'];
				}

				// Patch UT1 filename
				if ($item['feed'] == 'ftp://ftp.ut-capitole.fr/pub/reseau/cache/squidguard_contrib/blacklists.tar.gz') {
					$pfb['extras'][$key]['file_dwn'] = $pfb['extras'][$key]['file'] = 'ut1.tar.gz';
				}
				$key++;
			}
		}
	}
}

// Call include file and collect updated Global settings
if (in_array($argv[1], array('update', 'updateip', 'updatednsbl', 'dc', 'dcc', 'bu', 'uc', 'gc', 'al', 'bl', 'bls', 'cron', 'ugc'))) {
	pfb_global();

	$pfb['extras_update'] = FALSE;  // Flag when Extras (MaxMind/TOP1M) are updateded via cron job

	// Script Arguments
	switch($argv[1]) {
		case 'cron':		// Sync 'cron'
			syslog(LOG_NOTICE, '[pfBlockerNG] Starting cron process.');
			pfblockerng_sync_cron();
			break;
		case 'updateip':	// Sync 'Force Reload IP only'
		case 'updatednsbl':	// Sync 'Force Reload DNSBL only'
			sync_package_pfblockerng($argv[1]);
			break;
		case 'update':		// Sync 'Force update'
			sync_package_pfblockerng('cron');
			break;
		case 'dc':		// Update Extras - MaxMind/TOP1M database files
		case 'dcc':

			// 'dcc' called via Cron job
			if ($argv[1] == 'dcc') {

				// Only update on first Tuesday of each month (Delay till Thurs to allow for MaxMind late releases)
				if (date('D') != 'Thu') {
					exit;
				}
				$pfb['extras_update'] = TRUE;
			}

			// If 'General Tab' skip MaxMind download setting if checked, only download binary updates for Reputation/Alerts page.
			if (!empty($pfb['cc'])) {
				unset($pfb['extras'][2]);
			}

			// Skip TOP1M update, if disabled
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
		case 'al':		// Update TOP1M database only.
			unset($pfb['extras'][0], $pfb['extras'][1], $pfb['extras'][2]);
			pfblockerng_download_extras();
			break;
		case 'bl':		// Update DNSBL Category database(s) only.
		case 'bls':
			unset($pfb['extras'][0], $pfb['extras'][1], $pfb['extras'][2], $pfb['extras'][3]);

			if (empty($pfb['extras'][4])) {
				break;
			}

			// 'bls' called via 'Force Update|Reload'
			if ($argv[1] == 'bls') {
				$pfb_return = pfblockerng_download_extras(600, 'blacklist');
				return $pfb_return;
			}
			else {
				pfblockerng_download_extras();
			}
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

	$log = "[ {$header} ] [ NOW ]\n";
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
		touch("{$pfbfolder}/{$header}.update");
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
			touch("{$pfbfolder}/{$header}.update");
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
					$remote_stamp_raw = -1;
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
			curl_close($ch);
		}

		// If remote timestamp not found, Attempt md5 comparison
		if ($remote_stamp_raw == -1) {

			// Download Feed to compare md5's. If update required, downloaded md5 file will be used instead of downloading twice
			if (pfb_download($list_url, "{$pfborig}/{$header}.md5", $pflex, $header, '', 1, '', 300, 'md5', '', '')) {

				// Collect md5 checksums
				$remote_md5	= @md5_file("{$pfborig}/{$header}.md5.raw");
				$local_md5	= @md5_file($local_file);

				if ($remote_md5 != $local_md5) {
					$log = "\n\t\t\t\t( md5 changed )\t\tUpdate found\n";
					pfb_logger("{$log}", 1);
					$pfb['update_cron'] = TRUE;
					touch("{$pfbfolder}/{$header}.update");
					return;
				}
				else {
					$log = "\n\t\t\t\t( md5 unchanged )\tUpdate not required\n";
					pfb_logger("{$log}", 1);
					unlink_if_exists("{$pfborig}/{$header}.md5.raw");
					return;
				}
			}
			else {
				$log = "\n\tFailed to download Feed for md5 comparison!\tUpdate skipped\n";
				unlink_if_exists("{$pfborig}/{$header}.md5.raw");
				touch("{$pfbfolder}/{$header}.fail");
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
		touch("{$pfbfolder}/{$header}.update");
	}
	return;
}


// Download Extras - MaxMind/TOP1M/Category feeds via cURL
function pfblockerng_download_extras($timeout=600, $type='') {
	global $pfb;

	$pfb_return	= '';
	$pfb_error	= FALSE;

	pfb_logger("\nDownload Process Starting [ NOW ]\n", 3);
	foreach ($pfb['extras'] as $feed) {

		if (empty($feed)) {
			continue;
		}

		$file_dwn		= "{$feed['folder']}/{$feed['file_dwn']}";
		$feed['username']	= $feed['username'] ?: '';
		$feed['password']	= $feed['password'] ?: '';

		if (!pfb_download($feed['url'], $file_dwn, FALSE, "{$feed['folder']}/{$feed['file']}", '', 3, '', $timeout, $feed['type'], 
		    $feed['username'], $feed['password'])) {

			$log = "\nFailed to Download {$feed['file']}\n";
			pfb_logger("{$log}", 3);

			// On Extras update (MaxMind and TOP1M), if error found when downloading MaxMind Country database
			// return error to update process
			if ($feed['file_dwn'] == 'GeoLite2-Country-CSV.zip') {
				$pfb_error = TRUE;
			}

			if ($type == 'blacklist') {
				$pfb_return .= "\t{$feed['name']} ... Failed\n";
			}
		}
		else {
			if ($type == 'blacklist') {
				$pfb_return .= "\t{$feed['name']} ... Completed\n";
			}
		}
	}
	pfb_logger("Download Process Ended [ NOW ]\n\n", 3);

	if ($type == 'blacklist') {
		print "{$pfb_return}";
	} else {
		if ($pfb_error) {
			return FALSE;
		} else {
			return TRUE;
		}
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
	foreach ($list_type as $ltype => $vtype) {
		if (!empty($config['installedpackages'][$ltype]['config'])) {
			foreach ($config['installedpackages'][$ltype]['config'] as $list) {
				if (isset($list['row']) && $list['action'] != 'Disabled' && $list['cron'] != 'Never') {
					foreach ($list['row'] as $row) {
						if (!empty($row['url']) && $row['state'] != 'Disabled') {

							if (in_array($ltype, array('pfblockerngdnsbl', 'pfblockerngdnsbleasylist'))) {
								$header = "{$row['header']}";
							} else {
								$header = "{$row['header']}{$vtype}";
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

	// Remove any previous tmp working files
	rmdir_recursive("{$pfb['ccdir_tmp']}");
	safe_mkdir("{$pfb['ccdir_tmp']}");

	$pfb_geoip = array();
	$top_20 = array_flip( array('CN', 'RU', 'JP', 'UA', 'GB', 'DE', 'BR', 'FR', 'IN', 'TR',
			'IT', 'KR', 'PL', 'ES', 'VN', 'AR', 'CO', 'TW', 'MX', 'CL') );

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
				if (isset($top_20[$cc[4]])) {
					$top20 = 'A' . str_pad($top_20[$cc[4]], 5, '0', STR_PAD_LEFT);
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

	// List of all known Countries via Geonames.org (Used to validate MaxMind Country listings)
	$pfb_geoip_all = array( '3041565'	=> array ( 'iso' => 'AD', 'name' => 'Andorra',			'continent' => 'Europe' ),
				'290557'	=> array ( 'iso' => 'AE', 'name' => 'United Arab Emirates',	'continent' => 'Asia' ),
				'1149361'	=> array ( 'iso' => 'AF', 'name' => 'Afghanistan',		'continent' => 'Asia' ),
				'3576396'	=> array ( 'iso' => 'AG', 'name' => 'Antigua and Barbuda',	'continent' => 'North America' ),
				'3573511'	=> array ( 'iso' => 'AI', 'name' => 'Anguilla',			'continent' => 'North America' ),
				'783754'	=> array ( 'iso' => 'AL', 'name' => 'Albania',			'continent' => 'Europe' ),
				'174982'	=> array ( 'iso' => 'AM', 'name' => 'Armenia',			'continent' => 'Asia' ),
				'3351879'	=> array ( 'iso' => 'AO', 'name' => 'Angola',			'continent' => 'Africa' ),
				'6697173'	=> array ( 'iso' => 'AQ', 'name' => 'Antarctica',		'continent' => 'Antarctica' ),
				'3865483'	=> array ( 'iso' => 'AR', 'name' => 'Argentina',		'continent' => 'South America' ),
				'5880801'	=> array ( 'iso' => 'AS', 'name' => 'American Samoa',		'continent' => 'Oceania' ),
				'2782113'	=> array ( 'iso' => 'AT', 'name' => 'Austria',			'continent' => 'Europe' ),
				'2077456'	=> array ( 'iso' => 'AU', 'name' => 'Australia',		'continent' => 'Oceania' ),
				'3577279'	=> array ( 'iso' => 'AW', 'name' => 'Aruba',			'continent' => 'North America' ),
				'661882'	=> array ( 'iso' => 'AX', 'name' => 'Aland Islands',		'continent' => 'Europe' ),
				'587116'	=> array ( 'iso' => 'AZ', 'name' => 'Azerbaijan',		'continent' => 'Asia' ),
				'3277605'	=> array ( 'iso' => 'BA', 'name' => 'Bosnia and Herzegovina',	'continent' => 'Europe' ),
				'3374084'	=> array ( 'iso' => 'BB', 'name' => 'Barbados',			'continent' => 'North America' ),
				'1210997'	=> array ( 'iso' => 'BD', 'name' => 'Bangladesh',		'continent' => 'Asia' ),
				'2802361'	=> array ( 'iso' => 'BE', 'name' => 'Belgium',			'continent' => 'Europe' ),
				'2361809'	=> array ( 'iso' => 'BF', 'name' => 'Burkina Faso',		'continent' => 'Africa' ),
				'732800'	=> array ( 'iso' => 'BG', 'name' => 'Bulgaria',			'continent' => 'Europe' ),
				'290291'	=> array ( 'iso' => 'BH', 'name' => 'Bahrain',			'continent' => 'Asia' ),
				'433561'	=> array ( 'iso' => 'BI', 'name' => 'Burundi',			'continent' => 'Africa' ),
				'2395170'	=> array ( 'iso' => 'BJ', 'name' => 'Benin',			'continent' => 'Africa' ),
				'3578476'	=> array ( 'iso' => 'BL', 'name' => 'Saint Barthelemy',		'continent' => 'North America' ),
				'3573345'	=> array ( 'iso' => 'BM', 'name' => 'Bermuda',			'continent' => 'North America' ),
				'1820814'	=> array ( 'iso' => 'BN', 'name' => 'Brunei',			'continent' => 'Asia' ),
				'3923057'	=> array ( 'iso' => 'BO', 'name' => 'Bolivia',			'continent' => 'South America' ),
				'7626844'	=> array ( 'iso' => 'BQ', 'name' => 'Bonaire, Saint Eustatius and Saba ', 'continent' => 'North America' ),
				'3469034'	=> array ( 'iso' => 'BR', 'name' => 'Brazil',			'continent' => 'South America' ),
				'3572887'	=> array ( 'iso' => 'BS', 'name' => 'Bahamas',			'continent' => 'North America' ),
				'1252634'	=> array ( 'iso' => 'BT', 'name' => 'Bhutan',			'continent' => 'Asia' ),
				'3371123'	=> array ( 'iso' => 'BV', 'name' => 'Bouvet Island',		'continent' => 'Antarctica' ),
				'933860'	=> array ( 'iso' => 'BW', 'name' => 'Botswana',			'continent' => 'Africa' ),
				'630336'	=> array ( 'iso' => 'BY', 'name' => 'Belarus',			'continent' => 'Europe' ),
				'3582678'	=> array ( 'iso' => 'BZ', 'name' => 'Belize',			'continent' => 'North America' ),
				'6251999'	=> array ( 'iso' => 'CA', 'name' => 'Canada',			'continent' => 'North America' ),
				'1547376'	=> array ( 'iso' => 'CC', 'name' => 'Cocos Islands',		'continent' => 'Asia' ),
				'203312'	=> array ( 'iso' => 'CD', 'name' => 'Democratic Republic of the Congo', 'continent' => 'Africa' ),
				'239880'	=> array ( 'iso' => 'CF', 'name' => 'Central African Republic',	'continent' => 'Africa' ),
				'2260494'	=> array ( 'iso' => 'CG', 'name' => 'Republic of the Congo',	'continent' => 'Africa' ),
				'2658434'	=> array ( 'iso' => 'CH', 'name' => 'Switzerland',		'continent' => 'Europe' ),
				'2287781'	=> array ( 'iso' => 'CI', 'name' => 'Ivory Coast',		'continent' => 'Africa' ),
				'1899402'	=> array ( 'iso' => 'CK', 'name' => 'Cook Islands',		'continent' => 'Oceania' ),
				'3895114'	=> array ( 'iso' => 'CL', 'name' => 'Chile',			'continent' => 'South America' ),
				'2233387'	=> array ( 'iso' => 'CM', 'name' => 'Cameroon',			'continent' => 'Africa' ),
				'1814991'	=> array ( 'iso' => 'CN', 'name' => 'China',			'continent' => 'Asia' ),
				'3686110'	=> array ( 'iso' => 'CO', 'name' => 'Colombia',			'continent' => 'South America' ),
				'3624060'	=> array ( 'iso' => 'CR', 'name' => 'Costa Rica',		'continent' => 'North America' ),
				'3562981'	=> array ( 'iso' => 'CU', 'name' => 'Cuba',			'continent' => 'North America' ),
				'3374766'	=> array ( 'iso' => 'CV', 'name' => 'Cape Verde',		'continent' => 'Africa' ),
				'7626836'	=> array ( 'iso' => 'CW', 'name' => 'Curacao',			'continent' => 'North America' ),
				'2078138'	=> array ( 'iso' => 'CX', 'name' => 'Christmas Island',		'continent' => 'Asia' ),
				'146669'	=> array ( 'iso' => 'CY', 'name' => 'Cyprus',			'continent' => 'Europe' ),
				'3077311'	=> array ( 'iso' => 'CZ', 'name' => 'Czechia',			'continent' => 'Europe' ),
				'2921044'	=> array ( 'iso' => 'DE', 'name' => 'Germany',			'continent' => 'Europe' ),
				'223816'	=> array ( 'iso' => 'DJ', 'name' => 'Djibouti',			'continent' => 'Africa' ),
				'2623032'	=> array ( 'iso' => 'DK', 'name' => 'Denmark',			'continent' => 'Europe' ),
				'3575830'	=> array ( 'iso' => 'DM', 'name' => 'Dominica',			'continent' => 'North America' ),
				'3508796'	=> array ( 'iso' => 'DO', 'name' => 'Dominican Republic',	'continent' => 'North America' ),
				'2589581'	=> array ( 'iso' => 'DZ', 'name' => 'Algeria',			'continent' => 'Africa' ),
				'3658394'	=> array ( 'iso' => 'EC', 'name' => 'Ecuador',			'continent' => 'South America' ),
				'453733'	=> array ( 'iso' => 'EE', 'name' => 'Estonia',			'continent' => 'Europe' ),
				'357994'	=> array ( 'iso' => 'EG', 'name' => 'Egypt',			'continent' => 'Africa' ),
				'2461445'	=> array ( 'iso' => 'EH', 'name' => 'Western Sahara',		'continent' => 'Africa' ),
				'338010'	=> array ( 'iso' => 'ER', 'name' => 'Eritrea',			'continent' => 'Africa' ),
				'2510769'	=> array ( 'iso' => 'ES', 'name' => 'Spain',			'continent' => 'Europe' ),
				'337996'	=> array ( 'iso' => 'ET', 'name' => 'Ethiopia',			'continent' => 'Africa' ),
				'660013'	=> array ( 'iso' => 'FI', 'name' => 'Finland',			'continent' => 'Europe' ),
				'2205218'	=> array ( 'iso' => 'FJ', 'name' => 'Fiji',			'continent' => 'Oceania' ),
				'3474414'	=> array ( 'iso' => 'FK', 'name' => 'Falkland Islands',		'continent' => 'South America' ),
				'2081918'	=> array ( 'iso' => 'FM', 'name' => 'Micronesia',		'continent' => 'Oceania' ),
				'2622320'	=> array ( 'iso' => 'FO', 'name' => 'Faroe Islands',		'continent' => 'Europe' ),
				'3017382'	=> array ( 'iso' => 'FR', 'name' => 'France',			'continent' => 'Europe' ),
				'2400553'	=> array ( 'iso' => 'GA', 'name' => 'Gabon',			'continent' => 'Africa' ),
				'2635167'	=> array ( 'iso' => 'GB', 'name' => 'United Kingdom',		'continent' => 'Europe' ),
				'3580239'	=> array ( 'iso' => 'GD', 'name' => 'Grenada',			'continent' => 'North America' ),
				'614540'	=> array ( 'iso' => 'GE', 'name' => 'Georgia',			'continent' => 'Asia' ),
				'3381670'	=> array ( 'iso' => 'GF', 'name' => 'French Guiana',		'continent' => 'South America' ),
				'3042362'	=> array ( 'iso' => 'GG', 'name' => 'Guernsey',			'continent' => 'Europe' ),
				'2300660'	=> array ( 'iso' => 'GH', 'name' => 'Ghana',			'continent' => 'Africa' ),
				'2411586'	=> array ( 'iso' => 'GI', 'name' => 'Gibraltar',		'continent' => 'Europe' ),
				'3425505'	=> array ( 'iso' => 'GL', 'name' => 'Greenland',		'continent' => 'North America' ),
				'2413451'	=> array ( 'iso' => 'GM', 'name' => 'Gambia',			'continent' => 'Africa' ),
				'2420477'	=> array ( 'iso' => 'GN', 'name' => 'Guinea',			'continent' => 'Africa' ),
				'3579143'	=> array ( 'iso' => 'GP', 'name' => 'Guadeloupe',		'continent' => 'North America' ),
				'2309096'	=> array ( 'iso' => 'GQ', 'name' => 'Equatorial Guinea',	'continent' => 'Africa' ),
				'390903'	=> array ( 'iso' => 'GR', 'name' => 'Greece',			'continent' => 'Europe' ),
				'3474415'	=> array ( 'iso' => 'GS', 'name' => 'South Georgia and the South Sandwich Islands', 'continent' => 'Antarctica' ),
				'3595528'	=> array ( 'iso' => 'GT', 'name' => 'Guatemala',		'continent' => 'North America' ),
				'4043988'	=> array ( 'iso' => 'GU', 'name' => 'Guam',			'continent' => 'Oceania' ),
				'2372248'	=> array ( 'iso' => 'GW', 'name' => 'Guinea-Bissau',		'continent' => 'Africa' ),
				'3378535'	=> array ( 'iso' => 'GY', 'name' => 'Guyana',			'continent' => 'South America' ),
				'1819730'	=> array ( 'iso' => 'HK', 'name' => 'Hong Kong',		'continent' => 'Asia' ),
				'1547314'	=> array ( 'iso' => 'HM', 'name' => 'Heard Island and McDonald Islands', 'continent' => 'Antarctica' ),
				'3608932'	=> array ( 'iso' => 'HN', 'name' => 'Honduras',			'continent' => 'North America' ),
				'3202326'	=> array ( 'iso' => 'HR', 'name' => 'Croatia',			'continent' => 'Europe' ),
				'3723988'	=> array ( 'iso' => 'HT', 'name' => 'Haiti',			'continent' => 'North America' ),
				'719819'	=> array ( 'iso' => 'HU', 'name' => 'Hungary',			'continent' => 'Europe' ),
				'1643084'	=> array ( 'iso' => 'ID', 'name' => 'Indonesia',		'continent' => 'Asia' ),
				'2963597'	=> array ( 'iso' => 'IE', 'name' => 'Ireland',			'continent' => 'Europe' ),
				'294640'	=> array ( 'iso' => 'IL', 'name' => 'Israel',			'continent' => 'Asia' ),
				'3042225'	=> array ( 'iso' => 'IM', 'name' => 'Isle of Man',		'continent' => 'Europe' ),
				'1269750'	=> array ( 'iso' => 'IN', 'name' => 'India',			'continent' => 'Asia' ),
				'1282588'	=> array ( 'iso' => 'IO', 'name' => 'British Indian Ocean Territory', 'continent' => 'Asia' ),
				'99237'		=> array ( 'iso' => 'IQ', 'name' => 'Iraq',			'continent' => 'Asia' ),
				'130758'	=> array ( 'iso' => 'IR', 'name' => 'Iran',			'continent' => 'Asia' ),
				'2629691'	=> array ( 'iso' => 'IS', 'name' => 'Iceland',			'continent' => 'Europe' ),
				'3175395'	=> array ( 'iso' => 'IT', 'name' => 'Italy',			'continent' => 'Europe' ),
				'3042142'	=> array ( 'iso' => 'JE', 'name' => 'Jersey',			'continent' => 'Europe' ),
				'3489940'	=> array ( 'iso' => 'JM', 'name' => 'Jamaica',			'continent' => 'North America' ),
				'248816'	=> array ( 'iso' => 'JO', 'name' => 'Jordan',			'continent' => 'Asia' ),
				'1861060'	=> array ( 'iso' => 'JP', 'name' => 'Japan',			'continent' => 'Asia' ),
				'192950'	=> array ( 'iso' => 'KE', 'name' => 'Kenya',			'continent' => 'Africa' ),
				'1527747'	=> array ( 'iso' => 'KG', 'name' => 'Kyrgyzstan',		'continent' => 'Asia' ),
				'1831722'	=> array ( 'iso' => 'KH', 'name' => 'Cambodia',			'continent' => 'Asia' ),
				'4030945'	=> array ( 'iso' => 'KI', 'name' => 'Kiribati',			'continent' => 'Oceania' ),
				'921929'	=> array ( 'iso' => 'KM', 'name' => 'Comoros',			'continent' => 'Africa' ),
				'3575174'	=> array ( 'iso' => 'KN', 'name' => 'Saint Kitts and Nevis',	'continent' => 'North America' ),
				'1873107'	=> array ( 'iso' => 'KP', 'name' => 'North Korea',		'continent' => 'Asia' ),
				'1835841'	=> array ( 'iso' => 'KR', 'name' => 'South Korea',		'continent' => 'Asia' ),
				'831053'	=> array ( 'iso' => 'XK', 'name' => 'Kosovo',			'continent' => 'Europe' ),
				'285570'	=> array ( 'iso' => 'KW', 'name' => 'Kuwait',			'continent' => 'Asia' ),
				'3580718'	=> array ( 'iso' => 'KY', 'name' => 'Cayman Islands',		'continent' => 'North America' ),
				'1522867'	=> array ( 'iso' => 'KZ', 'name' => 'Kazakhstan',		'continent' => 'Asia' ),
				'1655842'	=> array ( 'iso' => 'LA', 'name' => 'Laos',			'continent' => 'Asia' ),
				'272103'	=> array ( 'iso' => 'LB', 'name' => 'Lebanon',			'continent' => 'Asia' ),
				'3576468'	=> array ( 'iso' => 'LC', 'name' => 'Saint Lucia',		'continent' => 'North America' ),
				'3042058'	=> array ( 'iso' => 'LI', 'name' => 'Liechtenstein',		'continent' => 'Europe' ),
				'1227603'	=> array ( 'iso' => 'LK', 'name' => 'Sri Lanka',		'continent' => 'Asia' ),
				'2275384'	=> array ( 'iso' => 'LR', 'name' => 'Liberia',			'continent' => 'Africa' ),
				'932692'	=> array ( 'iso' => 'LS', 'name' => 'Lesotho',			'continent' => 'Africa' ),
				'597427'	=> array ( 'iso' => 'LT', 'name' => 'Lithuania',		'continent' => 'Europe' ),
				'2960313'	=> array ( 'iso' => 'LU', 'name' => 'Luxembourg',		'continent' => 'Europe' ),
				'458258'	=> array ( 'iso' => 'LV', 'name' => 'Latvia',			'continent' => 'Europe' ),
				'2215636'	=> array ( 'iso' => 'LY', 'name' => 'Libya',			'continent' => 'Africa' ),
				'2542007'	=> array ( 'iso' => 'MA', 'name' => 'Morocco',			'continent' => 'Africa' ),
				'2993457'	=> array ( 'iso' => 'MC', 'name' => 'Monaco',			'continent' => 'Europe' ),
				'617790'	=> array ( 'iso' => 'MD', 'name' => 'Moldova',			'continent' => 'Europe' ),
				'3194884'	=> array ( 'iso' => 'ME', 'name' => 'Montenegro',		'continent' => 'Europe' ),
				'3578421'	=> array ( 'iso' => 'MF', 'name' => 'Saint Martin',		'continent' => 'North America' ),
				'1062947'	=> array ( 'iso' => 'MG', 'name' => 'Madagascar',		'continent' => 'Africa' ),
				'2080185'	=> array ( 'iso' => 'MH', 'name' => 'Marshall Islands',		'continent' => 'Oceania' ),
				'718075'	=> array ( 'iso' => 'MK', 'name' => 'Macedonia',		'continent' => 'Europe' ),
				'2453866'	=> array ( 'iso' => 'ML', 'name' => 'Mali',			'continent' => 'Africa' ),
				'1327865'	=> array ( 'iso' => 'MM', 'name' => 'Myanmar',			'continent' => 'Asia' ),
				'2029969'	=> array ( 'iso' => 'MN', 'name' => 'Mongolia',			'continent' => 'Asia' ),
				'1821275'	=> array ( 'iso' => 'MO', 'name' => 'Macao',			'continent' => 'Asia' ),
				'4041468'	=> array ( 'iso' => 'MP', 'name' => 'Northern Mariana Islands',	'continent' => 'Oceania' ),
				'3570311'	=> array ( 'iso' => 'MQ', 'name' => 'Martinique',		'continent' => 'North America' ),
				'2378080'	=> array ( 'iso' => 'MR', 'name' => 'Mauritania',		'continent' => 'Africa' ),
				'3578097'	=> array ( 'iso' => 'MS', 'name' => 'Montserrat',		'continent' => 'North America' ),
				'2562770'	=> array ( 'iso' => 'MT', 'name' => 'Malta',			'continent' => 'Europe' ),
				'934292'	=> array ( 'iso' => 'MU', 'name' => 'Mauritius',		'continent' => 'Africa' ),
				'1282028'	=> array ( 'iso' => 'MV', 'name' => 'Maldives',			'continent' => 'Asia' ),
				'927384'	=> array ( 'iso' => 'MW', 'name' => 'Malawi',			'continent' => 'Africa' ),
				'3996063'	=> array ( 'iso' => 'MX', 'name' => 'Mexico',			'continent' => 'North America' ),
				'1733045'	=> array ( 'iso' => 'MY', 'name' => 'Malaysia',			'continent' => 'Asia' ),
				'1036973'	=> array ( 'iso' => 'MZ', 'name' => 'Mozambique',		'continent' => 'Africa' ),
				'3355338'	=> array ( 'iso' => 'NA', 'name' => 'Namibia',			'continent' => 'Africa' ),
				'2139685'	=> array ( 'iso' => 'NC', 'name' => 'New Caledonia',		'continent' => 'Oceania' ),
				'2440476'	=> array ( 'iso' => 'NE', 'name' => 'Niger',			'continent' => 'Africa' ),
				'2155115'	=> array ( 'iso' => 'NF', 'name' => 'Norfolk Island',		'continent' => 'Oceania' ),
				'2328926'	=> array ( 'iso' => 'NG', 'name' => 'Nigeria',			'continent' => 'Africa' ),
				'3617476'	=> array ( 'iso' => 'NI', 'name' => 'Nicaragua',		'continent' => 'North America' ),
				'2750405'	=> array ( 'iso' => 'NL', 'name' => 'Netherlands',		'continent' => 'Europe' ),
				'3144096'	=> array ( 'iso' => 'NO', 'name' => 'Norway',			'continent' => 'Europe' ),
				'1282988'	=> array ( 'iso' => 'NP', 'name' => 'Nepal',			'continent' => 'Asia' ),
				'2110425'	=> array ( 'iso' => 'NR', 'name' => 'Nauru',			'continent' => 'Oceania' ),
				'4036232'	=> array ( 'iso' => 'NU', 'name' => 'Niue',			'continent' => 'Oceania' ),
				'2186224'	=> array ( 'iso' => 'NZ', 'name' => 'New Zealand',		'continent' => 'Oceania' ),
				'286963'	=> array ( 'iso' => 'OM', 'name' => 'Oman',			'continent' => 'Asia' ),
				'3703430'	=> array ( 'iso' => 'PA', 'name' => 'Panama',			'continent' => 'North America' ),
				'3932488'	=> array ( 'iso' => 'PE', 'name' => 'Peru',			'continent' => 'South America' ),
				'4030656'	=> array ( 'iso' => 'PF', 'name' => 'French Polynesia',		'continent' => 'Oceania' ),
				'2088628'	=> array ( 'iso' => 'PG', 'name' => 'Papua New Guinea',		'continent' => 'Oceania' ),
				'1694008'	=> array ( 'iso' => 'PH', 'name' => 'Philippines',		'continent' => 'Asia' ),
				'1168579'	=> array ( 'iso' => 'PK', 'name' => 'Pakistan',			'continent' => 'Asia' ),
				'798544'	=> array ( 'iso' => 'PL', 'name' => 'Poland',			'continent' => 'Europe' ),
				'3424932'	=> array ( 'iso' => 'PM', 'name' => 'Saint Pierre and Miquelon','continent' => 'North America' ),
				'4030699'	=> array ( 'iso' => 'PN', 'name' => 'Pitcairn',			'continent' => 'Oceania' ),
				'4566966'	=> array ( 'iso' => 'PR', 'name' => 'Puerto Rico',		'continent' => 'North America' ),
				'6254930'	=> array ( 'iso' => 'PS', 'name' => 'Palestinian Territory',	'continent' => 'Asia' ),
				'2264397'	=> array ( 'iso' => 'PT', 'name' => 'Portugal',			'continent' => 'Europe' ),
				'1559582'	=> array ( 'iso' => 'PW', 'name' => 'Palau',			'continent' => 'Oceania' ),
				'3437598'	=> array ( 'iso' => 'PY', 'name' => 'Paraguay',			'continent' => 'South America' ),
				'289688'	=> array ( 'iso' => 'QA', 'name' => 'Qatar',			'continent' => 'Asia' ),
				'935317'	=> array ( 'iso' => 'RE', 'name' => 'Reunion',			'continent' => 'Africa' ),
				'798549'	=> array ( 'iso' => 'RO', 'name' => 'Romania',			'continent' => 'Europe' ),
				'6290252'	=> array ( 'iso' => 'RS', 'name' => 'Serbia',			'continent' => 'Europe' ),
				'2017370'	=> array ( 'iso' => 'RU', 'name' => 'Russia',			'continent' => 'Europe' ),
				'49518'		=> array ( 'iso' => 'RW', 'name' => 'Rwanda',			'continent' => 'Africa' ),
				'102358'	=> array ( 'iso' => 'SA', 'name' => 'Saudi Arabia',		'continent' => 'Asia' ),
				'2103350'	=> array ( 'iso' => 'SB', 'name' => 'Solomon Islands',		'continent' => 'Oceania' ),
				'241170'	=> array ( 'iso' => 'SC', 'name' => 'Seychelles',		'continent' => 'Africa' ),
				'366755'	=> array ( 'iso' => 'SD', 'name' => 'Sudan',			'continent' => 'Africa' ),
				'7909807'	=> array ( 'iso' => 'SS', 'name' => 'South Sudan',		'continent' => 'Africa' ),
				'2661886'	=> array ( 'iso' => 'SE', 'name' => 'Sweden',			'continent' => 'Europe' ),
				'1880251'	=> array ( 'iso' => 'SG', 'name' => 'Singapore',		'continent' => 'Asia' ),
				'3370751'	=> array ( 'iso' => 'SH', 'name' => 'Saint Helena',		'continent' => 'Africa' ),
				'3190538'	=> array ( 'iso' => 'SI', 'name' => 'Slovenia',			'continent' => 'Europe' ),
				'607072'	=> array ( 'iso' => 'SJ', 'name' => 'Svalbard and Jan Mayen',	'continent' => 'Europe' ),
				'3057568'	=> array ( 'iso' => 'SK', 'name' => 'Slovakia',			'continent' => 'Europe' ),
				'2403846'	=> array ( 'iso' => 'SL', 'name' => 'Sierra Leone',		'continent' => 'Africa' ),
				'3168068'	=> array ( 'iso' => 'SM', 'name' => 'San Marino',		'continent' => 'Europe' ),
				'2245662'	=> array ( 'iso' => 'SN', 'name' => 'Senegal',			'continent' => 'Africa' ),
				'51537'		=> array ( 'iso' => 'SO', 'name' => 'Somalia',			'continent' => 'Africa' ),
				'3382998'	=> array ( 'iso' => 'SR', 'name' => 'Suriname',			'continent' => 'South America' ),
				'2410758'	=> array ( 'iso' => 'ST', 'name' => 'Sao Tome and Principe',	'continent' => 'Africa' ),
				'3585968'	=> array ( 'iso' => 'SV', 'name' => 'El Salvador',		'continent' => 'North America' ),
				'7609695'	=> array ( 'iso' => 'SX', 'name' => 'Sint Maarten',		'continent' => 'North America' ),
				'163843'	=> array ( 'iso' => 'SY', 'name' => 'Syria',			'continent' => 'Asia' ),
				'934841'	=> array ( 'iso' => 'SZ', 'name' => 'Swaziland',		'continent' => 'Africa' ),
				'3576916'	=> array ( 'iso' => 'TC', 'name' => 'Turks and Caicos Islands','continent' => 'North America' ),
				'2434508'	=> array ( 'iso' => 'TD', 'name' => 'Chad',			'continent' => 'Africa' ),
				'1546748'	=> array ( 'iso' => 'TF', 'name' => 'French Southern Territories', 'continent' => 'Antarctica' ),
				'2363686'	=> array ( 'iso' => 'TG', 'name' => 'Togo',			'continent' => 'Africa' ),
				'1605651'	=> array ( 'iso' => 'TH', 'name' => 'Thailand',			'continent' => 'Asia' ),
				'1220409'	=> array ( 'iso' => 'TJ', 'name' => 'Tajikistan',		'continent' => 'Asia' ),
				'4031074'	=> array ( 'iso' => 'TK', 'name' => 'Tokelau',			'continent' => 'Oceania' ),
				'1966436'	=> array ( 'iso' => 'TL', 'name' => 'East Timor',		'continent' => 'Oceania' ),
				'1218197'	=> array ( 'iso' => 'TM', 'name' => 'Turkmenistan',		'continent' => 'Asia' ),
				'2464461'	=> array ( 'iso' => 'TN', 'name' => 'Tunisia',			'continent' => 'Africa' ),
				'4032283'	=> array ( 'iso' => 'TO', 'name' => 'Tonga',			'continent' => 'Oceania' ),
				'298795'	=> array ( 'iso' => 'TR', 'name' => 'Turkey',			'continent' => 'Asia' ),
				'3573591'	=> array ( 'iso' => 'TT', 'name' => 'Trinidad and Tobago',	'continent' => 'North America' ),
				'2110297'	=> array ( 'iso' => 'TV', 'name' => 'Tuvalu',			'continent' => 'Oceania' ),
				'1668284'	=> array ( 'iso' => 'TW', 'name' => 'Taiwan',			'continent' => 'Asia' ),
				'149590'	=> array ( 'iso' => 'TZ', 'name' => 'Tanzania',			'continent' => 'Africa' ),
				'690791'	=> array ( 'iso' => 'UA', 'name' => 'Ukraine',			'continent' => 'Europe' ),
				'226074'	=> array ( 'iso' => 'UG', 'name' => 'Uganda',			'continent' => 'Africa' ),
				'5854968'	=> array ( 'iso' => 'UM', 'name' => 'United States Minor Outlying Islands', 'continent' => 'Oceania' ),
				'6252001'	=> array ( 'iso' => 'US', 'name' => 'United States',		'continent' => 'North America' ),
				'3439705'	=> array ( 'iso' => 'UY', 'name' => 'Uruguay',			'continent' => 'South America' ),
				'1512440'	=> array ( 'iso' => 'UZ', 'name' => 'Uzbekistan',		'continent' => 'Asia' ),
				'3164670'	=> array ( 'iso' => 'VA', 'name' => 'Vatican',			'continent' => 'Europe' ),
				'3577815'	=> array ( 'iso' => 'VC', 'name' => 'Saint Vincent and the Grenadines', 'continent' => 'North America' ),
				'3625428'	=> array ( 'iso' => 'VE', 'name' => 'Venezuela',		'continent' => 'South America' ),
				'3577718'	=> array ( 'iso' => 'VG', 'name' => 'British Virgin Islands',	'continent' => 'North America' ),
				'4796775'	=> array ( 'iso' => 'VI', 'name' => 'U.S. Virgin Islands',	'continent' => 'North America' ),
				'1562822'	=> array ( 'iso' => 'VN', 'name' => 'Vietnam',			'continent' => 'Asia' ),
				'2134431'	=> array ( 'iso' => 'VU', 'name' => 'Vanuatu',			'continent' => 'Oceania' ),
				'4034749'	=> array ( 'iso' => 'WF', 'name' => 'Wallis and Futuna',	'continent' => 'Oceania' ),
				'4034894'	=> array ( 'iso' => 'WS', 'name' => 'Samoa',			'continent' => 'Oceania' ),
				'69543'		=> array ( 'iso' => 'YE', 'name' => 'Yemen',			'continent' => 'Asia' ),
				'1024031'	=> array ( 'iso' => 'YT', 'name' => 'Mayotte',			'continent' => 'Africa' ),
				'953987'	=> array ( 'iso' => 'ZA', 'name' => 'South Africa',		'continent' => 'Africa' ),
				'895949'	=> array ( 'iso' => 'ZM', 'name' => 'Zambia',			'continent' => 'Africa' ),
				'878675'	=> array ( 'iso' => 'ZW', 'name' => 'Zimbabwe',			'continent' => 'Africa' )
				);

	// Remove previous list of GeoIP ISOs for IPv4/6 Source Field lookup
	unlink_if_exists("{$pfb['geoip_isos']}");

	// Determine if any Countries are missing from the MaxMind Database
	foreach ($pfb_geoip_all as $iso => $cc) {

		// Create list of GeoIP ISOs for IPv4/6 Source Field lookup
		@file_put_contents("{$pfb['geoip_isos']}", "{$cc['iso']} [ {$cc['name']} ],{$cc['iso']}_rep [ {$cc['name']} ],", FILE_APPEND | LOCK_EX);

		// Add missing Country as a 'placeholder'
		if (!isset($pfb_geoip['country'][$iso])) {
			$continent_en = str_replace(array(' ', '"'), array('_', ''), $cc['continent']);

			$pfb_geoip['country'][$iso] = array (	'missing_iso' => TRUE, 'id' => $iso, 'name' => $cc['name'],
								'iso' => array ( "{$cc['iso']}", "{$cc['iso']}_rep" ),
								'continent' => $cc['continent'], 'continent_en' => $continent_en);

			$pfb_geoip['country']['proxy']['iso'][]		= "A1_{$cc['iso']}_rep";
			$pfb_geoip['country']['satellite']['iso'][]	= "A2_{$cc['iso']}_rep";
		}
	}

	// Add Continents to GeoIP ISOs for IPv4/6 Source Field lookup
	@file_put_contents("{$pfb['geoip_isos']}", 'Africa,Antarctica,Asia,Europe,North_America,Oceania,South_America,Proxy_and_Satellite', FILE_APPEND | LOCK_EX);

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
				if ($type == 4) {

					// Remove all Countries listed by MaxMind from list of all known Countries
					if (isset($pfb_geoip_all[$cc[1]])) {
						unset($pfb_geoip_all[$cc[1]]);
					}

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
						if (!isset($pfb_geoip['country'][$cc[2]]) ||
						    !in_array($iso_rep, $pfb_geoip['country'][$cc[2]]['iso'])) {
							$pfb_geoip['country'][$cc[2]]['iso'][] = "{$iso_rep}";
						}
					}

					// Add placeholders for 'undefined ISO Represented' to Country ISO list
					if (!empty($cc[1])) {
						foreach (array( '' => $cc[1], 'A1_' => 'proxy', 'A2_' => 'satellite' ) as $reptype => $iso_placeholder) {
							$iso_rep_placeholder = "{$reptype}{$pfb_geoip['country'][$cc[1]]['iso'][0]}_rep";

							// Only add if not existing
							if (!isset($pfb_geoip['country'][$iso_placeholder]) ||
							    !in_array($iso_rep_placeholder, $pfb_geoip['country'][$iso_placeholder]['iso'])) {
								$pfb_geoip['country'][$iso_placeholder]['iso'][] = "{$iso_rep_placeholder}";
							}
						}
					}

					// Save ISO 'Represented Network' to ISO file
					if (!empty($iso_rep) && !empty($cc[0])) {
						$file = "{$pfb['ccdir_tmp']}/{$iso_rep}_v{$type}.txt";
						@file_put_contents("{$file}", "{$cc[0]}\n", FILE_APPEND | LOCK_EX);
					}
				}
				else {
					if (!empty($cc[1])) {
						$iso = "{$pfb_geoip['country'][$cc[1]]['iso'][0]}";
					}
				}

				// Save 'ISO Registered Network' to ISO file
				if (!empty($iso) && !empty($cc[0])) {
					$file = "{$pfb['ccdir_tmp']}/{$iso}_v{$type}.txt";
					@file_put_contents("{$file}", "{$cc[0]}\n", FILE_APPEND | LOCK_EX);
				}
			}

			// For IPv4 - Add A1 & A2 placeholders for any Countries that MaxMind has not listed any data
			if ($type == 4) {
				if (!empty($pfb_geoip_all)) {
					foreach ($pfb_geoip_all as $cc) {
						foreach (array( 'A1_' => 'proxy', 'A2_' => 'satellite' ) as $reptype => $iso_placeholder) {
							$pfb_geoip['country'][$iso_placeholder]['iso'][] = "{$reptype}{$cc['iso']}_rep";
						}
					}
				}
				unset($pfb_geoip_all);
			}

			// Report number of Geoname_ids which have both a different 'Registered and Represented' geoname_id
			if ($geoip_dup != 0) {
				@file_put_contents("{$pfb['logdir']}/maxmind_ver", "Duplicate Represented IP{$type} Networks: {$geoip_dup}\n", FILE_APPEND | LOCK_EX);
			}

			// Delete previous GeoIP Continent files
			array_map('unlink_if_exists', array(	"{$pfb['ccdir']}/Top_Spammers_v{$type}.info",
								"{$pfb['ccdir']}/Africa_v{$type}.txt",
								"{$pfb['ccdir']}/Antarctica_v{$type}.txt",
								"{$pfb['ccdir']}/Asia_v{$type}.txt",
								"{$pfb['ccdir']}/Europe_v{$type}.txt",
								"{$pfb['ccdir']}/*_America_v{$type}.txt",
								"{$pfb['ccdir']}/Oceania_v{$type}.txt",
								"{$pfb['ccdir']}/Proxy_and_Satellite_v{$type}.txt" ));

			// Create Continent txt files
			if (!empty($pfb_geoip['country'])) {
				foreach ($pfb_geoip['country'] as $key => $geoip) {

					// Save 'TOP 20' data
					if (strpos($key, 'A000') !== FALSE) {
						$pfb_file = "{$pfb['ccdir']}/Top_Spammers_v{$type}.info";

						if (!file_exists($pfb_file)) {
							$header  = '# Generated from MaxMind Inc. on: ' . date('m/d/y G:i:s', time()) . "\n";
							$header .= "# Continent IPv{$type}: Top_Spammers\n";
							$header .= "# Continent en: Top_Spammers\n";
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
										// Create placeholder file for undefined 'ISO Represented' or undefined Countries
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
