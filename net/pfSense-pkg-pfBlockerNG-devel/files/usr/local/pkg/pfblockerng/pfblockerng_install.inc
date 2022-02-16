<?php
/*
 * pfblockerng_install.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2021 BBcan177@gmail.com
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

global $config, $g, $pfb;
pfb_global();

// Set 'Install flag' to skip sync process during installations.
$g['pfblockerng_install'] = TRUE;

// MaxMind Database is no longer pre-installed during package installation
update_status("\nMaxMind GeoIP databases are not pre-installed during installation.\nTo utilize the MaxMind GeoIP functionalities, you will be required to register for a free MaxMind user account and access key. Review the IP tab: MaxMind Settings for more details.\n\n");

$pfb['widgets'] = $config['widgets']['sequence'];
if ($pfb['keep'] == 'on' && isset($pfb['widgets']) && strpos($pfb['widgets'], 'pfblockerng') !== FALSE) {
	update_status("\nRestoring previous pfBlockerNG Widget settings...");

	// Fix incorrect Dashboard widget sequence
	if (strpos($pfb['widgets'], ':show') !== FALSE) {
		$pfb['widgets'] = str_replace(':show', ':open:0', $pfb['widgets']);
		update_status("Fix incorrect Dashboard widget sequence...");
	}

	$config['widgets']['sequence'] = $pfb['widgets'];
	write_config('pfBlockerNG: Save widget');
} else {
	update_status("\nAdding pfBlockerNG Widget to the Dashboard...");
	$widgets = $config['widgets']['sequence'];
	if (strpos($widgets, 'pfblockerng') === FALSE) {
		if (empty($widgets)) {
			$config['widgets']['sequence'] = 'pfblockerng:col2:open:0';
		} else {
			$config['widgets']['sequence'] .= ',pfblockerng:col2:open:0';
		}
		write_config('pfBlockerNG: Save widget');
	}
}

// Create Firewall filter service and link required executables
update_status(" done.\n\nCreating Firewall filter service...");
pfb_filter_service();
stop_service('pfb_filter');
update_status(" done.\nRenew Firewall filter executables...");
unlink_if_exists('/usr/local/bin/php_pfb');
link('/usr/local/bin/php', '/usr/local/bin/php_pfb');

// clog is not required for pfSense 2.5 and above
unlink_if_exists('/usr/local/sbin/clog_pfb');
unlink_if_exists('/usr/bin/tail_pfb');
if (substr(trim(file_get_contents('/etc/version')), 0, 3) < '2.5' && file_exists('/usr/local/sbin/clog')) {
	link('/usr/local/sbin/clog', '/usr/local/sbin/clog_pfb');
} else {
	link('/usr/bin/tail', '/usr/bin/tail_pfb');
}

update_status(" done.\nStarting Firewall filter Service...");
start_service('pfb_filter');

// Create DNSBL service and link required executables
update_status(" done.\n\nCreating DNSBL service...");
pfb_dnsbl_service();
stop_service('pfb_dnsbl');
update_status(" done.\nRenew DNSBL lighttpd executable...");
unlink_if_exists('/usr/local/sbin/lighttpd_pfb');
link('/usr/local/sbin/lighttpd', '/usr/local/sbin/lighttpd_pfb');
update_status(" done.\n");

// Create DNSBL Lighttpd config file and DNSBL Certificate and restart DNSBL Service
if (!empty($pfb['dnsbl_port']) && !empty($pfb['dnsbl_port_ssl'])) {

	update_status("Creating DNSBL web server config ...");
	pfb_create_lighttpd();
	update_status(" done.\n");

	unlink_if_exists($pfb['dnsbl_cert']);
	update_status("Creating DNSBL Certificate...");
	pfb_create_dnsbl_cert();
	update_status(" done.\n");
}

update_status("Starting DNSBL Service...");
start_service('pfb_dnsbl');
update_status(" done.\n\n");

// Replace 'default' DNSBL active blocked webpage
if (!file_exists('/usr/local/www/pfblockerng/www/dnsbl_active.php') ||
    (isset($config['installedpackages']['pfblockerngdnsblsettings']) &&
    $config['installedpackages']['pfblockerngdnsblsettings']['config'][0]['dnsbl_webpage'] == 'dnsbl_default.php')) {
	@copy('/usr/local/www/pfblockerng/www/dnsbl_default.php', '/usr/local/www/pfblockerng/www/dnsbl_active.php');
}



// *** [ UPGRADE previous settings ] ***
update_status("Upgrading previous settings:\n");

// Remove discontinued pfBlockerNG CRON job
if (is_array($config['cron']['item'])) {
	$pfb_cron = &$config['cron']['item'];
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
update_status(" Adv. Inbound firewall rule settings...");

$ufound = FALSE;
$upgrade_type = array('pfblockernglistsv4', 'pfblockernglistsv6', 'pfblockerngdnsblsettings', 'pfblockerngafrica', 'pfblockerngantarctica',
		    'pfblockerngasia', 'pfblockerngeurope', 'pfblockerngnorthamerica', 'pfblockerngoceania', 'pfblockerngsouthamerica',
		    'pfblockerngtopspammers', 'pfblockerngproxyandsatellite');

foreach ($upgrade_type as $type) {
	if (is_array($config['installedpackages'][$type]['config'])) {
		$conf_config = &$config['installedpackages'][$type]['config'];

		// Remove any empty '<config></config>' XML tags
		if (empty($conf_config[0])) {
			unset($conf_config[0]);
			$ufound = TRUE;
		}

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
update_status(" OpenVPN/IPSec interface selections...");
$ufound = FALSE;

if (is_array($config['installedpackages']['pfblockerng']['config'][0])) {
	$pfb_interfaces = &$config['installedpackages']['pfblockerng']['config'][0];
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

				$pfb_interfaces['outbound_interface'] .= "{$csv_string}openvpn";
				$ufound = TRUE;
				$u_msg .= ' OpenVPN (Outbound) &';
			}

			if (isset($config['openvpn']['openvpn-client'])) {
				if (strpos($pfb_interfaces['outbound_interface'], 'openvpn') === FALSE && !$ufound) {
					$csv_string = '';
					if (isset($pfb_interfaces['outbound_interface'])) {
						$csv_string = ',';
					}
					$pfb_interfaces['outbound_interface'] .= "{$csv_string}openvpn";
					$ufound = TRUE;
					$u_msg .= ' OpenVPN (Outbound) &';
				}

				if (strpos($pfb_interfaces['inbound_interface'], 'openvpn') === FALSE) {
					$csv_string = '';
					if (isset($pfb_interfaces['inbound_interface'])) {
						$csv_string = ',';
					}
					$pfb_interfaces['inbound_interface'] .= "{$csv_string}openvpn";
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
			$pfb_interfaces['outbound_interface'] .= "{$csv_string}enc0";
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
}

if ($ufound) {
	$u_msg = rtrim($u_msg, '&');
	update_status(" saving new changes for {$u_msg} ] ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
}

// Upgrade ET IQRisk categories
update_status(" Proofpoint/ET IQRisk settings...");
$ufound = FALSE;

if (is_array($config['installedpackages']['pfblockerngreputation']['config'][0])) {
	$pfb_iqrisk = &$config['installedpackages']['pfblockerngreputation']['config'][0];

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
update_status(" General Tab -> IP Tab settings...");

if (is_array($config['installedpackages']['pfblockerng']['config'][0]) &&
    !is_array($config['installedpackages']['pfblockerngipsettings']['config'][0])) {

	$pfb['gconfig'] = &$config['installedpackages']['pfblockerng']['config'][0];

	init_config_arr(array('installedpackages', 'pfblockerngipsettings', 'config', 0));
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
update_status(" pfBlockerNGSuppress Alias -> IPv4 Suppression Customlist...");
$ufound = FALSE;

if (is_array($config['installedpackages']['pfblockerngipsettings']['config'][0]) &&
    !isset($config['installedpackages']['pfblockerngipsettings']['config'][0]['v4suppression'])) {

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
					if ($stmt) {
						$stmt->bindValue(':group0', $group[0], SQLITE3_TEXT);
						$stmt->bindValue(':group1', $group[1], SQLITE3_TEXT);
						$stmt->bindValue(':group2', $group[2], SQLITE3_TEXT);
						$stmt->bindValue(':group3', $group[3], SQLITE3_INTEGER);
						$stmt->execute();
					}
				}
			}
		}
	}
	pfb_close_sqlite($db_handle);
}
unlink_if_exists('/var/db/pfblockerng/dnsbl_info');

// Move dnsbl_levent.sqlite -> /var/unbound folder
$u_found = FALSE;
if (file_exists('/var/db/pfblockerng/dnsbl_levent.sqlite') && !file_exists($pfb['dnsbl_resolver'])) {
	$ufound = TRUE;
	@copy('/var/db/pfblockerng/dnsbl_levent.sqlite', $pfb['dnsbl_resolver']);
	@chown($pfb['dnsbl_resolver'], 'unbound');
	@chgrp($pfb['dnsbl_resolver'], 'unbound');
}
unlink_if_exists('/var/db/pfblockerng/dnsbl_levent.sqlite');

// Move dnsbl.sqlite -> /var/unbound folder
if (file_exists('/var/db/pfblockerng/dnsbl.sqlite') && !file_exists($pfb['dnsbl_info'])) {
	$ufound = TRUE;
	@copy('/var/db/pfblockerng/dnsbl.sqlite', $pfb['dnsbl_info']);
	@chown($pfb['dnsbl_info'], 'unbound');
	@chgrp($pfb['dnsbl_info'], 'unbound');
}
unlink_if_exists('/var/db/pfblockerng/dnsbl.sqlite');

// Update unbound python files
if ($pfb['dnsbl_mode'] == 'dnsbl_python') {
	@copy("/usr/local/pkg/pfblockerng/pfb_unbound.py", $g['unbound_chroot_path']);
	@copy("/usr/local/pkg/pfblockerng/pfb_unbound_include.inc", $g['unbound_chroot_path']);
	@copy("/usr/local/pkg/pfblockerng/pfb_py_hsts.txt", $g['unbound_chroot_path']);
}

if ($ufound) {
	$final = pfb_stop_start_unbound('');
}

// Upgrade EasyList to new Format
update_status(" Upgrading previous EasyLists to new format...");
$ufound = FALSE;

if (is_array($config['installedpackages']['pfblockerngdnsbleasylist']) &&
    !empty($config['installedpackages']['pfblockerngdnsbleasylist'])) {

	// Collect all enabled EasyLists
	$enabled	= array();
	$ex_easylists	= $config['installedpackages']['pfblockerngdnsbleasylist']['config'][0];
	if (is_array($ex_easylists['row'])) {
		foreach ($ex_easylists['row'] as $easylist) {
			if ($easylist['state'] == 'Enabled') {
				$enabled[$easylist['header']] = '';
			}
		}
	}

	// Load all pfBlockerNG Feeds
	if (!empty($enabled)) {
		$feed_info_raw = json_decode(@file_get_contents("{$pfb['feeds']}"), TRUE);
		if (json_last_error() === JSON_ERROR_NONE && is_array($feed_info_raw)) {

			$add			= array();
			$add['aliasname']	= $ex_easylists['aliasname']	?: 'EasyList';
			$add['description']	= $ex_easylists['description']	?: '';
			$add['row']		= array();

			foreach ($feed_info_raw['dnsbl']['EasyList'] as $easylists) {
				if (is_array($easylists)) {
					foreach ($easylists as $easylist) {
						if (isset($enabled[$easylist['header']])) {
							$add['row'][] = array(	'format'	=> 'auto',
										'state'		=> 'Enabled',
										'url'		=> $easylist['url'],
										'header'	=> $easylist['header']);
							$ufound = TRUE;
						}
					}
				}
			}
		}
	}

	if ($ufound) {
		$add['action']	= $ex_easylists['action'];
		$add['cron']	= $ex_easylists['cron'];
		$add['dow']	= $ex_easylists['dow'];
		$add['logging']	= $ex_easylists['logging'];
		$add['order']	= $ex_easylists['order'];

		init_config_arr(array('installedpackages', 'pfblockerngdnsbl', 'config'));
		array_unshift($config['installedpackages']['pfblockerngdnsbl']['config'], $add);
	}

	// Remove Previous EasyList configuration
	unset($config['installedpackages']['pfblockerngdnsbleasylist']);
}

if ($ufound) {
	update_status(" saving new changes ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
}

// Firefox Doh settings to new DoH settings
update_status(" Upgrading previous Firefox DoH to new format...");
$ufound = FALSE;

if (is_array($config['installedpackages']['pfblockerngsafesearch']) &&
    !empty($config['installedpackages']['pfblockerngsafesearch'])) {

	$doh_config = &$config['installedpackages']['pfblockerngsafesearch'];
	if (isset($doh_config['safesearch_firefoxdoh'])) {
		$ufound = TRUE;

		$doh_config['safesearch_doh'] = $doh_config['safesearch_firefoxdoh'];
		if ($doh_config['safesearch_firefoxdoh'] == 'Enable') {
			$doh_config['safesearch_doh_list'] = 'use-application-dns.net';
		}
		unset($doh_config['safesearch_firefoxdoh']);
	}
}

if ($ufound) {
	update_status(" saving new changes ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
}

// Move MaxMind Key to new configuration location
update_status(" MaxMind License Key configuration setting...");
$ufound = FALSE;

if (is_array($config['installedpackages']['pfblockerng']['config'][0]) &&
    !isset($config['installedpackages']['pfblockerngipsettings']['config'][0]['maxmind_key'])) {

	$maxmind_config = &$config['installedpackages']['pfblockerng']['config'][0];
	foreach (array('maxmind_key', 'maxmind_locale', 'database_cc') as $m_setting) {
		if (isset($maxmind_config[$m_setting])) {
			$ufound = TRUE;
			$config['installedpackages']['pfblockerngipsettings']['config'][0][$m_setting] = $maxmind_config[$m_setting];
			unset($maxmind_config[$m_setting]);
		}
	}
}

if ($ufound) {
	update_status(" saving new changes ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
}

unset($g['pfblockerng_install']);	// Remove 'Install flag'
update_status("Upgrading... done\n\nCustom commands completed ... ");

write_config('[pfBlockerNG] Save installation settings');
return TRUE;

?>
