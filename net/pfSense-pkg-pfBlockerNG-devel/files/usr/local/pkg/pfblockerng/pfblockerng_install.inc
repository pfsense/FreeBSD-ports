<?php
/*
 * pfblockerng_install.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2024 BBcan177@gmail.com
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

global $g, $pfb;
pfb_global();

// Set 'Install flag' to skip sync process during installations.
$g['pfblockerng_install'] = TRUE;

// Migrate old VIP configuration.
$pfb_dnsbl_config = config_get_path('installedpackages/pfblockerngdnsblsettings/config/0', []);
if (
	isset($pfb_dnsbl_config['pfb_dnsvip']) ||
	isset($pfb_dnsbl_config['pfb_dnsblv6']) ||
	isset($pfb_dnsbl_config['pfb_dnsvip_type']) ||
	isset($pfb_dnsbl_config['pfb_dnsvip_vhid']) ||
	isset($pfb_dnsbl_config['pfb_dnsvip_base']) ||
	isset($pfb_dnsbl_config['pfb_dnsvip_skew']) ||
	isset($pfb_dnsbl_config['pfb_dnsvip_pass'])
) {
	// Update existing VIPs.
	$pfb_configured_vip_id = null;
	$vips_config = config_get_path('virtualip/vip', []);
	foreach ($vips_config as &$vip) {
		if (!str_starts_with($vip['descr'], 'pfB DNSBL')) {
			continue;
		}

		$vip['descr'] = 'pfBlockerNG DNSBL';
		$vip['uniqid'] = $vip['uniqid'] ?? uniqid();

		$vip_id = "_vip{$vip['uniqid']}";
		if ($pfb_dnsbl_config['pfb_dnsvip'] == $vip['subnet']) {
			$pfb_configured_vip_id = $vip_id;
		}
		if (is_ipaddrv4($vip['subnet'])) {
			$pfb_dnsbl_config['pfb_dnsvip4'] = $vip_id;
		} else {
			$pfb_dnsbl_config['pfb_dnsvip6'] = $vip_id;
		}
	}
	unset($vip);
	config_set_path('virtualip/vip', $vips_config);

	// If DNSBL is not configured, don't set new config.
	if (
		isset($pfb_dnsbl_config['pfb_dnsvip4']) &&
		(!isset($pfb_dnsbl_config['pfb_dnsvip']) || !is_ipaddrv4($pfb_dnsbl_config['pfb_dnsvip']) ||
			!is_string($pfb_dnsbl_config['pfb_dnsvip4']) ||
			!str_starts_with($pfb_dnsbl_config['pfb_dnsvip4'], '_vip'))
	) {
		unset($pfb_dnsbl_config['pfb_dnsvip4']);
	}
	if (
		isset($pfb_dnsbl_config['pfb_dnsvip6']) &&
		(($pfb_dnsbl_config['pfb_dnsblv6'] != 'on') ||
			!is_string($pfb_dnsbl_config['pfb_dnsvip6']) ||
			!str_starts_with($pfb_dnsbl_config['pfb_dnsvip6'], '_vip'))
	) {
		unset($pfb_dnsbl_config['pfb_dnsvip6']);
	}

	// Remove old configuration.
	unset(
		$pfb_dnsbl_config['pfb_dnsvip'],
		$pfb_dnsbl_config['pfb_dnsblv6'],
		$pfb_dnsbl_config['pfb_dnsvip_type'],
		$pfb_dnsbl_config['pfb_dnsvip_vhid'],
		$pfb_dnsbl_config['pfb_dnsvip_base'],
		$pfb_dnsbl_config['pfb_dnsvip_skew'],
		$pfb_dnsbl_config['pfb_dnsvip_pass']
	);
	// Reset saved wizard configuration.
	config_del_path('pfblockerng_wizard');

	config_set_path('installedpackages/pfblockerngdnsblsettings/config/0', $pfb_dnsbl_config);
	pfb_global();
}

// MaxMind Database is no longer pre-installed during package installation
if (empty($pfb['maxmind_key'])) {
	update_status("\nMaxMind GeoIP databases are not pre-installed during installation.\nTo utilize the MaxMind GeoIP functionalities, you will be required to register for a free MaxMind user account and access key. Review the IP tab: MaxMind Settings for more details.\n\n");
}
else {
	// Update any PHP changes to GeoIP Tabs
	update_status("\nRebuilding GeoIP tabs...");
	pfblockerng_get_countries();
	update_status(" done.");
}

// Create Reputation tab
if (!file_exists('/usr/local/www/pfblockerng/pfblockerng_reputation.php') || empty($pfb['maxmind_key'])) {
	update_status("\nRebuild Reputation tab...");
	pfb_build_reputation_tab();
	update_status(" done.");
}

$widgets = config_get_path('widgets/sequence');
if (strpos($widgets, 'pfblockerng') === FALSE) {
	update_status("\nAdding pfBlockerNG Widget to the Dashboard...");
	if (empty($widgets)) {
		config_set_path('widgets/sequence', 'pfblockerng:col2:open:0');
	} else {
		config_set_path('widgets/sequence', $widgets . ',pfblockerng:col2:open:0');
	}
	update_status(" done.");
}

// Create Firewall filter service and link required executables
update_status("\nCreating Firewall filter service...");
pfb_filter_service();
stop_service('pfb_filter');
update_status(" done.\nRenew Firewall filter executables...");
unlink_if_exists('/usr/local/bin/php_pfb');
link('/usr/local/bin/php', '/usr/local/bin/php_pfb');

// clog is not required for pfSense 2.5 and above
unlink_if_exists('/usr/local/sbin/clog_pfb');
unlink_if_exists('/usr/bin/tail_pfb');
link('/usr/bin/tail', '/usr/bin/tail_pfb');

// Remove cache databases
unlink_if_exists("{$pfb['asn_cache']}");
unlink_if_exists("{$pfb['ip_cache']}");
unlink_if_exists("{$pfb['dnsbl_cache']}");

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
if (!empty($pfb['dnsbl_port']) && !empty($pfb['dnsbl_port_ssl']) && ($pfb['dnsbl'] == 'on')) {

	update_status("Creating DNSBL web server config ...");
	pfb_create_lighttpd();
	update_status(" done.\n");

	unlink_if_exists($pfb['dnsbl_cert']);
	update_status("Creating DNSBL Certificate...");
	pfb_create_dnsbl_cert();
	update_status(" done.\n");

	update_status("Starting DNSBL Service...");
	start_service('pfb_dnsbl');
	update_status(" done.\n\n");
}

// Replace 'default' DNSBL active blocked webpage
if (!file_exists('/usr/local/www/pfblockerng/www/dnsbl_active.php') ||
    config_get_path('installedpackages/pfblockerngdnsblsettings/config/0/dnsblwebpage', '') == 'dnsbl_default.php') {
    @copy('/usr/local/www/pfblockerng/www/dnsbl_default.php', '/usr/local/www/pfblockerng/www/dnsbl_active.php');
}



// *** [ UPGRADE previous settings ] ***
update_status("Upgrading previous settings:\n");

// Remove discontinued pfBlockerNG CRON job
foreach (config_get_path('cron/item', []) as $key => $item) {
	if (strpos($item['command'], 'pfblockerng.php dc >>') !== FALSE) {
		config_del_path("cron/item/{$key}");
	}
}

// Remove incorrect xml setting
config_del_path('installedpackages/pfblockerngantartica');

// Upgrade Adv. Inbound settings to new variable names.
update_status(" Adv. Inbound firewall rule settings...");

$ufound = FALSE;
$upgrade_type = array('pfblockernglistsv4', 'pfblockernglistsv6', 'pfblockerngdnsblsettings', 'pfblockerngafrica', 'pfblockerngantarctica',
		    'pfblockerngasia', 'pfblockerngeurope', 'pfblockerngnorthamerica', 'pfblockerngoceania', 'pfblockerngsouthamerica',
		    'pfblockerngtopspammers', 'pfblockerngproxyandsatellite');

foreach ($upgrade_type as $type) {
	if (is_array(config_get_path("installedpackages/{$type}/config"))) {
		// Remove any empty '<config></config>' XML tags
		if (empty(config_get_path("installedpackages/{$type}/config/0"))) {
			config_del_path("installedpackages/{$type}/config/0");
			$ufound = TRUE;
		}

		foreach (config_get_path("installedpackages/{$type}/config", []) as $key => $utype) {
			if (isset($utype['autoports'])) {
				$ufound = TRUE;
				if ($utype['autoports'] == 'on' && !empty($utype['aliasports']) && !isset($conf_config[$key]['autoports_in'])) {
					config_set_path("installedpackages/{$type}/config/{$key}/autoports_in", 'on');
					config_set_path("installedpackages/{$type}/config/{$key}/aliasports_in", $utype['aliasports']);
				}
				config_del_path("installedpackages/{$type}/config/{$key}/autoports");;
				config_del_path("installedpackages/{$type}/config/{$key}/aliasports");;
			}
			if (isset($utype['autodest'])) {
				$ufound = TRUE;
				if ($utype['autodest'] == 'on' && !empty($utype['aliasdest']) && !isset($conf_config[$key]['autoaddr_in'])) {
					config_set_path("installedpackages/{$type}/config/{$key}/autoaddr_in", 'on');
					config_set_path("installedpackages/{$type}/config/{$key}/aliasaddr_in", $utype['aliasdest']);
				}
				config_del_path("installedpackages/{$type}/config/{$key}/autodest");;
				config_del_path("installedpackages/{$type}/config/{$key}/aliasdest");;
			}
			if (isset($utype['autonot'])) {
				$ufound = TRUE;
				if ($utype['autonot'] == 'on' && !isset($conf_config[$key]['autonot_in'])) {
					config_set_path("installedpackages/{$type}/config/{$key}/autonot_in", $utype['autonot']);
				}
				config_del_path("installedpackages/{$type}/config/{$key}/autonot");;
			}
			if (isset($utype['autoproto'])) {
				$ufound = TRUE;
				config_set_path("installedpackages/{$type}/config/{$key}/autoproto_in", $utype['autoproto']);
				config_del_path("installedpackages/{$type}/config/{$key}/autoproto");;
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

$pfb_interfaces = config_get_path('installedpackages/pfblockerng/config/0');
if (is_array($pfb_interfaces)) {
	if (!empty($pfb['config'])) {

		$u_msg = '[';

		// pfBlockerNG OpenVPN interface selection upgrade
		if (array_get_path($pfb_interfaces, 'openvpn_action', '') == 'on') {

			if (config_get_path('openvpn/openvpn-server') != null &&
			    strpos($pfb_interfaces['outbound_interface'], 'openvpn') === FALSE) {

				$csv_string = '';
				if (isset($pfb_interfaces['outbound_interface'])) {
					$csv_string = ',';
				}

				$pfb_interfaces['outbound_interface'] .= "{$csv_string}openvpn";
				$ufound = TRUE;
				$u_msg .= ' OpenVPN (Outbound) &';
			}

			if (config_get_path('openvpn/openvpn-client') != null) {
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
		    !empty(config_get_path('ipsec')) &&
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
		config_set_path('installedpackages/pfblockerng/config/0', $pfb_interfaces);
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

$et_type = [];
$pfb_iqrisk = config_get_path('installedpackages/pfblockerngreputation/config/0');
if (is_array($pfb_iqrisk)) {
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
	config_set_path('installedpackages/pfblockerngreputation/config/0', $pfb_iqrisk);
} else {
	update_status(" no changes required ... done.\n");
}

// Move General Tab 'IP settings' to new IP tab
update_status(" General Tab -> IP Tab settings...");

if (config_get_path('installedpackages/pfblockerng/config/0') != null &&
    config_get_path('installedpackages/pfblockerngipsettings/config/0') == null) {

	$pfb['gconfig'] = config_get_path('installedpackages/pfblockerng/config/0', []);

	$pfb['iconfig'] = config_get_path('installedpackages/pfblockerngipsettings/config/0', []);

	$settings = array(	'enable_dup', 'enable_agg', 'suppression', 'enable_log', 'maxmind_locale', 'database_cc',
				'inbound_interface', 'inbound_deny_action', 'outbound_interface', 'outbound_deny_action',
				'enable_float', 'pass_order', 'autorule_suffix', 'killstates' );

	foreach ($settings as $setting) {
		$pfb['iconfig'][$setting] = $pfb['gconfig'][$setting] ?: '';
		config_set_path("installedpackages/pfblockerngipsettings/config/0/{$setting}", $pfb['iconfig'][$setting]);
		if (isset($pfb['gconfig'][$setting])) {
			unset($pfb['gconfig'][$setting]);
			config_del_path("installedpackages/pfblockerng/config/0/{$setting}");
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

if (config_get_path('installedpackages/pfblockerngipsettings/config/0/v4suppression') == null) {
	$customlist = '';
	foreach (config_get_path('aliases/alias', []) as $key => $alias) {
		if ($alias['name'] == 'pfBlockerNGSuppress') {

			$slist = array(explode(' ', $alias['address']), explode('||', $alias['detail']));
			array_multisort($slist[0], SORT_ASC, SORT_NUMERIC, $slist[1]);

			foreach ($slist as $key => $data) {
				if ($key == 0) {
					foreach ($data as $key2 => $line) {
						if (!empty(array_get_path($slist, "1/{$key2}"))) {
							$customlist .= "{$line} # " . array_get_path($slist, "1/{$key2}") . "\r\n";
						} else {
							$customlist .= "{$line}\r\n";
						}
					}
				}
			}
			config_set_path('installedpackages/pfblockerngipsettings/config/0/v4suppression',  base64_encode($customlist) ?: '');
			// unset($config['aliases']['alias'][$key]);
			$ufound = TRUE;
			break;
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

		$dnsbl_info = @file('/var/db/pfblockerng/dnsbl_info');
		if (!empty($dnsbl_info)) {
			foreach ($dnsbl_info as $group) {
				$group = str_getcsv($group, ',', '"', "\\");
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

/* Update unbound python files.
 * The target directory may not exist yet e.g. after an upgrade with
 * RAM Disks enabled; defer the file copy to pfb_unbound_dnsbl(). */
if (($pfb['dnsbl_mode'] == 'dnsbl_python') && is_dir(g_get('unbound_chroot_path'))) {
	@copy("/usr/local/pkg/pfblockerng/pfb_unbound.py", "{$g['unbound_chroot_path']}/pfb_unbound.py");
	@copy("/usr/local/pkg/pfblockerng/pfb_unbound_include.inc", "{$g['unbound_chroot_path']}/pfb_unbound_include.inc");
	@copy("/usr/local/pkg/pfblockerng/pfb_py_hsts.txt", "{$g['unbound_chroot_path']}/pfb_py_hsts.txt");
}

if ($ufound) {
	$final = pfb_stop_start_unbound('');
}

// Upgrade EasyList to new Format
update_status(" Upgrading previous EasyLists to new format...");
$ufound = FALSE;

if (!empty(config_get_path('installedpackages/pfblockerngdnsbleasylist', []))) {

	// Collect all enabled EasyLists
	$enabled	= array();
	$ex_easylists	= config_get_path('installedpackages/pfblockerngdnsbleasylist/config/0', []);

	foreach (array_get_path($ex_easylists, 'row', []) as $easylist) {
		if ($easylist['state'] == 'Enabled') {
				$enabled[$easylist['header']] = '';
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

		$dnsblcfg = config_get_path('installedpackages/pfblockerngdnsbl/config', []);
		$dnsblcfg[] = $add;
		config_set_path('installedpackages/pfblockerngdnsbl/config', $dnsblcfg);
	}

	// Remove Previous EasyList configuration
	config_del_path('installedpackages/pfblockerngdnsbleasylist');
}

if ($ufound) {
	update_status(" saving new changes ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
}

// Firefox Doh settings to new DoH settings
update_status(" Upgrading previous Firefox DoH to new format...");
$ufound = FALSE;

$doh_config = config_get_path('installedpackages/pfblockerngsafesearch', []);
if (!empty($doh_config)) {
	if (isset($doh_config['safesearch_firefoxdoh'])) {
		$ufound = TRUE;

		$doh_config['safesearch_doh'] = $doh_config['safesearch_firefoxdoh'];
		if ($doh_config['safesearch_firefoxdoh'] == 'Enable') {
			$doh_config['safesearch_doh_list'] = 'use-application-dns.net';
		}
		unset($doh_config['safesearch_firefoxdoh']);
		config_set_path('installedpackages/pfblockerngsafesearch', $doh_config);
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

if (config_get_path('installedpackages/pfblockerng/config/0') != null &&
	config_get_path('installedpackages/pfblockerngipsettings/config/0/maxmind_key') == null) {
	$maxmind_config = config_get_path('installedpackages/pfblockerng/config/0', []);
	foreach (array('maxmind_key', 'maxmind_locale', 'database_cc') as $m_setting) {
		if (isset($maxmind_config[$m_setting])) {
			$ufound = TRUE;
			config_set_path("installedpackages/pfblockerngipsettings/config/0/{$m_setting}", $maxmind_config[$m_setting]);
			config_del_path("installedpackages/pfblockerng/config/0/{$m_setting}");
		}
	}
}

if ($ufound) {
	update_status(" saving new changes ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
}

// Validate widget pfBlockerNG clear [ dnsbl/IP ] counter CRON jobs
update_status(" Validating Widget cron settings...");
$ufound = FALSE;

foreach (array('clearip', 'cleardnsbl') as $type) {
	if (config_get_path("installedpackages/pfblockerngglobal/widget-{$type}") != null) {

		// Remove incorrect cron entries
		$pfb_cmd_esc = "/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php '{$type}' >/dev/null 2>&1";
		if (pfblockerng_cron_exists($pfb_cmd_esc, '0', '0', '*', '*')) {
			$ufound = TRUE;
			install_cron_job("pfblockerng.php '{$type}'", false);
		}
		if (pfblockerng_cron_exists($pfb_cmd_esc, '0', '0', '*', '7')) {
			$ufound = TRUE;
			install_cron_job("pfblockerng.php '{$type}'", false);
		}

		// Add correct cron entry, if enabled
		if (config_get_path("installedpackages/pfblockerngglobal/widget-{$type}") != 'never') {

			$pfb_day = '*';
			if (config_get_path("installedpackages/pfblockerngglobal/widget-{$type}") == 'weekly') {
				$pfb_day = '7';
			}

			$pfb_cmd = "/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php {$type} >/dev/null 2>&1";
			if (!pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', $pfb_day)) {
				$ufound = TRUE;
				install_cron_job($pfb_cmd, true, '0', '0', '*', '*', $pfb_day, 'root', false);
			}
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
