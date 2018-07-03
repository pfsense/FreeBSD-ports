<?php
/*
 * pfblockerng_install.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2018 BBcan177@gmail.com
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

require_once('pfsense-utils.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');
require_once('/usr/local/www/pfblockerng/pfblockerng.php');

global $config, $g, $pfb;
pfb_global();

// Set 'Install flag' to skip sync process during installations.
$g['pfblockerng_install'] = TRUE;

// Only download MaxMind Database if files do not exist
$maxmind_verify = 0;

if (!file_exists("{$pfb['geoipshare']}/{$pfb['extras'][0]['file']}") ||
    !file_exists("{$pfb['geoipshare']}/{$pfb['extras'][1]['file']}") ||
    !file_exists("{$pfb['geoipshare']}/GeoLite2-Country-Blocks-IPv4.csv") ||
    !file_exists("{$pfb['dbdir']}/geoip.txt") ||
    !file_exists("{$pfb['ccdir']}/Top_Spammers_v4.info")) {

	update_status("\nDownloading MaxMind GeoIP databases. This may take a minute...\n");

	// Remove Alexa and Blacklist category feeds from download process
	$pfb['extras'] = array_slice($pfb['extras'], 0, 3) ?: array();

	foreach ($pfb['extras'] as $feed) {

		$file_dwn = "{$pfb['geoipshare']}/{$feed['file_dwn']}";
		if (($fhandle = @fopen("{$file_dwn}", 'w')) !== FALSE) {
			if (empty($feed['file'])) {
				$feed['file'] = 'GeoLite2-Country-CSV.zip';
			}
			update_status(" {$feed['file']}...");
			if (!($ch = curl_init("{$feed['url']}"))) {
				update_status(" Failed to create cURL resource.\n");
				break;
			}

			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 Chrome/43.0.2357.65 Safari/537.36');
			curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1.3, TLSv1.2');
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($ch, CURLOPT_FILE, $fhandle);
			curl_setopt($ch, CURLOPT_TIMEOUT, 600);

			// Attempt 3 Downloads before failing.
			for ($retries = 1; $retries <= 3; $retries++) {
				if (curl_exec($ch)) {
					break;	// Break on success
				}

				$curl_error = curl_errno($ch);
				update_status(" cURL Error: {$curl_error}. ", 1);
			}

			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($http_status == '200 OK') {
				if (strpos($file_dwn, '.gz') !== FALSE) {
					exec("/usr/bin/gunzip -dfq {$file_dwn} {$pfb['geoipshare']}");
				} elseif (strpos($file_dwn, '.zip') !== FALSE) {
					exec("/usr/bin/tar -xf {$file_dwn} --strip=1 -C {$pfb['geoipshare']}");
				}
				unlink_if_exists("{$file_dwn}");
				update_status(" done.\n");
				$maxmind_verify += 1;
			}
			else {
				update_status(" Failed!\n");
				break;
			}
		}
		curl_close($ch);
		@fclose($fhandle);
	}

	if ($maxmind_verify != 3) {
		update_status("MaxMind download Failed! Fetching MaxMind archive from pfSense package repo...\n");
		// Fetch archived MaxMind database
		$url = 'https://packages.pfsense.org/packages/config/pfblockerng/countrycodes.tar.bz2';
		$retval = '';
		exec("/usr/bin/fetch -o /tmp/countrycodes.tar.bz2 {$url}", $output, $retval);
		if ($retval == 0) {
			// Uncompress archived GeoIP code file
			$retval = '';
			exec("/usr/bin/tar -jx -C {$pfb['ccdir']} -f /tmp/countrycodes.tar.bz2", $output, $retval);
			if ($retval == 0) {
				$maxmind_verify = 3;
				update_status("Fetch done.\n");
			} else {
				update_status("Fetch Failed!\n");
			}
		}
		else {
			update_status(" Failed!\n");
		}
	}
	else {
		update_status("Downloading MaxMind GeoIP databases... done.\n");
	}

	// Proceed with conversion of MaxMind files on download success
	if ($maxmind_verify == 3) {
		update_status("Converting MaxMind GeoIP databases for pfBlockerNG.\n This may take a few minutes...\n");
		pfblockerng_uc_countries();
		update_status("Creating pfBlockerNG Continent PHP files...\n");
		pfblockerng_get_countries();
	}
	else {
		update_status("\n\nMaxMind update has Failed! Please fix connectivity and re-install package again!\n\n");
	}
}
else {
	update_status("\nMaxMind GeoIP databases previously downloaded.\n");
}


if ($pfb['keep'] == 'on' && isset($pfb['widgets']) && strpos($pfb['widgets'], 'pfblockerng-container') !== FALSE) {
	update_status("Restoring previous pfBlockerNG Widget settings...");
	// Restore previous widget setting if 'keep' is enabled.
	$config['widgets']['sequence'] = $pfb['widgets'];
	write_config('pfBlockerNG: Save widget');
} else {
	update_status("Adding pfBlockerNG Widget to the Dashboard...");
	$widgets = $config['widgets']['sequence'];
	if (strpos($widgets, 'pfblockerng-container') === FALSE) {
		if (empty($widgets)) {
			$config['widgets']['sequence'] = 'pfblockerng-container:col2:show';
		} else {
			$config['widgets']['sequence'] .= ',pfblockerng-container:col2:show';
		}
		write_config('pfBlockerNG: Save widget');
	}
}

// Create Firewall filter service and link required executables
update_status("\nRemove any existing and create links for Firewall filter executables...");
unlink_if_exists('/usr/local/bin/php_pfb');
link('/usr/local/bin/php', '/usr/local/bin/php_pfb');
unlink_if_exists('/usr/local/sbin/clog_pfb');
link('/usr/local/sbin/clog', '/usr/local/sbin/clog_pfb');

update_status(" done.\nCreating Firewall filter service...");
pfb_filter_service();
update_status(" done.\n");

// Create DNSBL service and link required executables
update_status("Remove any existing and create link for DNSBL lighttpd executable...");
unlink_if_exists('/usr/local/sbin/lighttpd_pfb');
link('/usr/local/sbin/lighttpd', '/usr/local/sbin/lighttpd_pfb');

update_status(" done.\nCreating DNSBL service...");
pfb_dnsbl_service();
update_status(" done.\n");

// Create DNSBL Lighttpd config file and DNSBL Certificate and restart DNSBL Service
if (!empty($pfb['dnsbl_port']) && !empty($pfb['dnsbl_port_ssl'])) {

	update_status("Creating DNSBL web server config ...");
	pfb_create_lighttpd();
	update_status(" done.\n");

	update_status("Creating DNSBL Certificate...");
	pfb_create_dnsbl_cert();
	update_status(" done.\n");

	update_status("Re-starting DNSBL Service...");
	restart_service('pfb_dnsbl');
	update_status(" done.\n");
}

// Replace 'default' DNSBL active blocked webpage
if (!file_exists('/usr/local/www/pfblockerng/www/dnsbl_active.php') ||
    (isset($config['installedpackages']['pfblockerngdnsblsettings']) &&
    $config['installedpackages']['pfblockerngdnsblsettings']['config'][0]['dnsbl_webpage'] == 'dnsbl_default.php')) {
	@copy('/usr/local/www/pfblockerng/www/dnsbl_default.php', '/usr/local/www/pfblockerng/www/dnsbl_active.php');
}



// *** [ UPGRADE changed settings ] ***



// Remove discontinued pfBlockerNG CRON job
$pfb_cron = &$config['cron']['item'];
if (isset($pfb_cron)) {
	foreach ($pfb_cron as $key => $item) {
		if (strpos($item['command'], 'pfblockerng.php dc >>') !== FALSE) {
			unset($pfb_cron[$key]);
		}
	}
}

// Remove incorrect xml setting
if (isset($config['installedpackages']['pfblockerngantartica'])) {
	unset($config['installedpackages']['pfblockerngantartica']);
}

// Upgrade Adv. Inbound settings to new variable names.
update_status("Upgrading Adv. Inbound firewall rule settings ...");

$ufound = FALSE;
$upgrade_type = array('pfblockernglistsv4', 'pfblockernglistsv6', 'pfblockerngdnsblsettings', 'pfblockerngafrica', 'pfblockerngantarctica',
		    'pfblockerngasia', 'pfblockerngeurope', 'pfblockerngnorthamerica', 'pfblockerngoceania', 'pfblockerngsouthamerica',
		    'pfblockerngtopspammers', 'pfblockerngproxyandsatellite');

foreach ($upgrade_type as $type) {
	$conf_config = &$config['installedpackages'][$type]['config'];
	if (isset($conf_config)) {
		foreach ($conf_config as $key => $utype) {
			if (isset($utype['autoports'])) {
				$ufound = TRUE;
				if ($utype['autoports'] == 'on' && !empty($utype['aliasports']) && !isset($conf_config[$key]['autoports_in'])) {
					$conf_config[$key]['autoports_in'] = 'on';
					$conf_config[$key]['aliasports_in'] = $utype['aliasports'];
				}
				unset($conf_config[$key]['autoports']);
				unset($conf_config[$key]['aliasports']);
			}
			if (isset($utype['autodest'])) {
				$ufound = TRUE;
				if ($utype['autodest'] == 'on' && !empty($utype['aliasdest']) && !isset($conf_config[$key]['autoaddr_in'])) {
					$conf_config[$key]['autoaddr_in'] = 'on';
					$conf_config[$key]['aliasaddr_in'] = $utype['aliasdest'];
				}
				unset($conf_config[$key]['autodest']);
				unset($conf_config[$key]['aliasdest']);
			}
			if (isset($utype['autonot'])) {
				$ufound = TRUE;
				if ($utype['autonot'] == 'on' && !isset($conf_config[$key]['autonot_in'])) {
					$conf_config[$key]['autonot_in'] = $utype['autonot'];
				}
				unset($conf_config[$key]['autonot']);
			}
			if (isset($utype['autoproto'])) {
				$ufound = TRUE;
				$conf_config[$key]['autoproto_in'] = $utype['autoproto'];
				unset($conf_config[$key]['autoproto']);
			}
		}
	}
}

if ($ufound) {
	update_status(" saving new changes ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
}

// Upgrade OpenVPN and IPSec 'checkbox options' to pfBlockerNG In/Out interface selections
update_status("Upgrading OpenVPN/IPSec interface selections...");

$pfb_interfaces = &$config['installedpackages']['pfblockerng']['config'][0];
$ufound = FALSE;
if (!empty($pfb['config'])) {

	$u_msg = '[';

	// pfBlockerNG OpenVPN interface selection upgrade
	if (isset($pfb_interfaces['openvpn_action']) && $pfb_interfaces['openvpn_action'] == 'on') {

		if (isset($config['openvpn']['openvpn-server']) &&
		    strpos($pfb_interfaces['outbound_interface'], 'openvpn') === FALSE) {
	
			$csv_string = '';
			if (isset($pfb_interfaces['outbound_interface'])) {
				$csv_string = ',';
			}

			$pfb_interfaces['outbound_interface']	.= "{$csv_string}openvpn";
			$ufound = TRUE;
			$u_msg .= ' OpenVPN (Outbound) &';  
		}

		if (isset($config['openvpn']['openvpn-client'])) {
			if (strpos($pfb_interfaces['outbound_interface'], 'openvpn') === FALSE && !$ufound) {

				$csv_string = '';
				if (isset($pfb_interfaces['outbound_interface'])) {
					$csv_string = ',';
				}
				$pfb_interfaces['outbound_interface']	.= "{$csv_string}openvpn";
				$ufound = TRUE;
				$u_msg .= ' OpenVPN (Outbound) &';
			}

			if (strpos($pfb_interfaces['inbound_interface'], 'openvpn') === FALSE) {
				$csv_string = '';
				if (isset($pfb_interfaces['inbound_interface'])) {
					$csv_string = ',';
				}
				$pfb_interfaces['inbound_interface']	.= "{$csv_string}openvpn";
				$ufound = TRUE;
				$u_msg .= ' OpenVPN (Inbound) &';
			}
		}
	}

	// pfBlockerNG IPSec interface selection upgrade
	if (isset($pfb['config']['ipsec_action']) &&
	    $pfb_interfaces['ipsec_action'] == 'on' &&
	    isset($config['ipsec']) &&
	    !empty($config['ipsec']) &&
	    strpos($pfb_interfaces['outbound_interface'], 'enc0') === FALSE) {

		$csv_string = '';
		if (isset($pfb_interfaces['outbound_interface'])) {
			$csv_string = ',';
		}
		$pfb_interfaces['outbound_interface']	.= "{$csv_string}enc0";
		$ufound = TRUE;
		$u_msg .= ' IPSec (Outbound)';
	}

	if (isset($pfb_interfaces['openvpn_action'])) {
		unset($pfb_interfaces['openvpn_action']);
	}
	if (isset($pfb_interfaces['ipsec_action'])) {
		unset($pfb_interfaces['ipsec_action']);
	}
}

if ($ufound) {
	$u_msg = rtrim($u_msg, '&');
	update_status(" saving new changes for {$u_msg} ] ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
}

// Upgrade EasyList/EasyPrivacy category settings to new format
update_status("Upgrading EasyList/Easyprivacy category settings...");

$pfb_easylist = &$config['installedpackages']['pfblockerngdnsbleasylist']['config'][0];
$ufound = FALSE;
if (isset($pfb_easylist) && isset($pfb_easylist['easycat'])) {
	$easylist	= array();
	$easycat	= explode(',', $pfb_easylist['easycat']);
	foreach (array('ea' => 0, 'eap' => 0, 'aa' => 0, 'aap' => 0, 'epts' => 1, 'epti' => 1) as $option => $row) {
		if (in_array($option, $easycat)) {
			$easylist[$row][] = $option;
			$ufound = TRUE;
		}
	}
}

if ($ufound) {
	foreach ($easylist as $row => $options) {
		if (!isset($pfb_easylist['row'][$row]['easycat'])) {
			$pfb_easylist['row'][$row]['easycat'] = implode(',', $options);
		}
	}

	unset($pfb_easylist['easycat']);
	update_status(" saving new changes ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
} 

// Upgrade ET IQRisk categories
$pfb_iqrisk = &$config['installedpackages']['pfblockerngreputation']['config'][0];
update_status("Upgrading Proofpoint/ET IQRisk settings...");

$ufound = FALSE;
$et_type = array();
foreach (array('etblock', 'etmatch') as $type) {
	if (isset($pfb_iqrisk[$type])) {

		$et_cat = explode(',', $pfb_iqrisk[$type]);
		foreach ($et_cat as $cat) {
			switch ($cat) {
				case 'ET_P2P':
					$cat = 'ET_P2Pnode';
					$ufound = TRUE;
					break;
				case 'ET_DDos':
					$cat = 'ET_DDostarget';
					$ufound = TRUE;
					break;
				case 'ET_DDosattack':
					$cat = 'ET_DDosattacker';
					$ufound = TRUE;
					break;
				case 'ET_Unknown':
					$ufound = TRUE;
					continue 2;
			}
			$et_type[$type][] = $cat;
		}
	}
}

if ($ufound) {
	update_status(" saving new changes ... done.\n");
	foreach ($et_type as $type => $cats) {
		$pfb_iqrisk[$type] = implode(',', $cats);
	}
} else {
	update_status(" no changes required ... done.\n");
}

// Move General Tab 'IP settings' to new IP tab
update_status("Upgrading General Tab -> IP Tab settings...");
if (!isset($config['installedpackages']['pfblockerngipsettings'])) {
	$pfb['gconfig'] = &$config['installedpackages']['pfblockerng']['config'][0];
	$pfb['iconfig'] = &$config['installedpackages']['pfblockerngipsettings']['config'][0];

	$settings = array(	'enable_dup', 'enable_agg', 'suppression', 'enable_log', 'maxmind_locale', 'database_cc',
				'inbound_interface', 'inbound_deny_action', 'outbound_interface', 'outbound_deny_action',
				'enable_float', 'pass_order', 'autorule_suffix', 'killstates' );

	foreach ($settings as $setting) {
		$pfb['iconfig'][$setting] = $pfb['gconfig'][$setting] ?: '';
		if (isset($pfb['gconfig'][$setting])) {
			unset($pfb['gconfig'][$setting]);
		}
	}
	update_status(" saving new changes ... done.\n");
}
else {
	update_status(" no changes required ... done.\n");
}

// Upgrade pfBlockerNGSuppress alias to IPv4 Suppression custom list
$ufound = FALSE;
update_status("Upgrading pfBlockerNGSuppress Alias -> IPv4 Suppression Customlist...");
if (!isset($config['installedpackages']['pfblockerngipsettings']['config'][0]['v4suppression'])) {

	$customlist = '';
	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $key => $alias) {

			if ($alias['name'] == 'pfBlockerNGSuppress') {

				$slist = array(explode(' ', $alias['address']), explode('||', $alias['detail']));
				array_multisort($slist[0], SORT_ASC, SORT_NUMERIC, $slist[1]);

				foreach ($slist as $key => $data) {
					if ($key == 0) {
						foreach ($data as $key2 => $line) {
							if (isset($slist[1][$key2]) && !empty($slist[1][$key2])) {
								$customlist .= "{$line} # {$slist[1][$key2]}\r\n";
							} else {
								$customlist .= "{$line}\r\n";
							}
						}
					}
				}
				$config['installedpackages']['pfblockerngipsettings']['config'][0]['v4suppression'] = base64_encode($customlist) ?: '';
				// unset($config['aliases']['alias'][$key]);
				$ufound = TRUE;
				break;
			}
		}
	}
}

if ($ufound) {
	update_status(" saving new changes ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
}

// Convert dnsbl_info CSV file to SQLite3 database format
if (file_exists('/var/db/pfblockerng/dnsbl_info') &&
    !file_exists('/var/db/pfblockerng/dnsbl.sqlite') &&
    function_exists('pfb_open_sqlite')) {

	$db_handle = pfb_open_sqlite(1, 'Convert dnsbl_info');
	if ($db_handle) {

		$dnsbl_info = array_map('str_getcsv', @file('/var/db/pfblockerng/dnsbl_info'));
		if (!empty($dnsbl_info)) {
			foreach ($dnsbl_info as $group) {
				if (substr($group[0], 0, 1) != '#') {
					$db_update = "INSERT INTO dnsbl ( groupname, timestamp, entries, counter )"
							. " VALUES ( :group0, :group1, :group2, :group3 );\n";

					$stmt = $db_handle->prepare($db_update);
					$stmt->bindValue(':group0', $group[0], SQLITE3_TEXT);
					$stmt->bindValue(':group1', $group[1], SQLITE3_TEXT);
					$stmt->bindValue(':group2', $group[2], SQLITE3_TEXT);
					$stmt->bindValue(':group3', $group[3], SQLITE3_INTEGER);
					$stmt->execute();
				}
			}
		}
	}
	pfb_close_sqlite($db_handle);
}
unlink_if_exists('/var/db/pfblockerng/dnsbl_info');

unset($g['pfblockerng_install']);	// Remove 'Install flag'
update_status("Custom commands completed ... ");

write_config('[pfBlockerNG] Save installation settings');
return TRUE;

?>
