<?php
/*
 * pfblockerng_alerts.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2018 BBcan177@gmail.com
 * All rights reserved.
 *
 * Parts based on works from Snort_alerts.php
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
require_once('guiconfig.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');

global $config, $g, $pfb;
pfb_global();

// Alerts tab customizations
$aglobal_array = array('pfbdenycnt' => 25, 'pfbpermitcnt' => 5, 'pfbmatchcnt' => 5, 'pfbdnscnt' => 5, 'pfbfilterlimitentries' => 100);
$pfb['aglobal'] = &$config['installedpackages']['pfblockerngglobal'];
if (!is_array($pfb['aglobal'])) {
	$pfb['aglobal'] = array();
}
$alertrefresh	= $pfb['aglobal']['alertrefresh']	!= ''	? $pfb['aglobal']['alertrefresh']	: 'on';
$pfbpageload	= $pfb['aglobal']['pfbpageload']	!= ''	? $pfb['aglobal']['pfbpageload']	: 'default';
$pfbblockstat	= explode(',', $pfb['aglobal']['pfbblockstat']) ?: array();
$pfbpermitstat	= explode(',', $pfb['aglobal']['pfbpermitstat'])?: array();
$pfbmatchstat	= explode(',', $pfb['aglobal']['pfbmatchstat'])	?: array();
$pfbdnsblstat	= explode(',', $pfb['aglobal']['pfbdnsblstat'])	?: array();

foreach ($aglobal_array as $type => $value) {
	${"$type"} = $pfb['aglobal'][$type] != '' ? $pfb['aglobal'][$type] : $value;
}

$alert_view	= '';
$alert_summary	= FALSE;
$active		= array('alerts' => TRUE);

if (isset($_GET) && isset($_GET['view'])) {
	if ($_GET['view'] == 'dnsbl_stat') {
		$alert_view	= 'dnsbl_stat';
		$alert_log	= $pfb['dnslog'];
		$alert_title	= 'DNSBL Block';
		$active		= array('dnsbl' => TRUE);
	}
	elseif ($_GET['view'] == 'ip_block_stat') {
		$alert_view	= 'ip_block_stat';
		$alert_log	= $pfb['ip_blocklog'];
		$alert_title	= 'IP Block';
		$active		= array('ip_block' => TRUE);
	}
	elseif ($_GET['view'] == 'ip_permit_stat') {
		$alert_view	= 'ip_permit_stat';
		$alert_log	= $pfb['ip_permitlog'];
		$alert_title	= 'IP Permit';
		$active		= array('ip_permit' => TRUE);
	}
	elseif ($_GET['view'] == 'ip_match_stat') {
		$alert_view	= 'ip_match_stat';
		$alert_log	= $pfb['ip_matchlog'];
		$alert_title	= 'IP Match';
		$active		= array('ip_match' => TRUE);
	}

	if (!empty($alert_view)) {
		$alert_summary = TRUE;
	}
}

// Collect all Whitelist/Suppression/Permit/Exclusion customlists
if (!$alert_summary) {

	$clists = array();
	foreach (array('ipwhitelist4' => 4, 'ipwhitelist6' => 6) as $type => $vtype) {
		$clists[$type] = array();

		if (isset($config['installedpackages']['pfblockernglistsv' . $vtype]) &&
		    !empty($config['installedpackages']['pfblockernglistsv' . $vtype]['config'])) {

			foreach ($config['installedpackages']['pfblockernglistsv' . $vtype]['config'] as $row => $data) {
				if (strpos($data['action'], 'Permit') !== FALSE) {

					$lname				= "pfB_{$data['aliasname']}_v{$vtype}";
					$clists[$type]['options'][]	= $lname;	// List of all Permit Aliases

					$clists[$type][$lname]['base64']= &$config['installedpackages']['pfblockernglistsv' . $vtype]['config'][$row]['custom'];
					$clists[$type][$lname]['data']	= array();

					$decoded = pfbng_text_area_decode($data['custom'], TRUE, TRUE);
					if (!empty($decoded)) {
						foreach ($decoded as $line) {

							// Create string (Domain and Comment if found)
							if (isset($line[1])) {
								$clists[$type][$lname]['data'][$line[0]] = "{$line[0]} {$line[1]}\r\n";
							} else {
								$line[0] = trim($line[0]);
								$clists[$type][$lname]['data'][$line[0]] = "{$line[0]}\r\n";
							}
						}
					}
				}
			}
		}

		// Add Default pfBlockerNG IP Whitelist
		if (empty($clists[$type]['options'])) {
			$clists[$type]['options'][] = "Create new pfB_Whitelist_v{$vtype}";
		}
	}

	if (!is_array($config['installedpackages']['pfblockerngipsettings']['config'])) {
		$config['installedpackages']['pfblockerngipsettings']['config'] = array();
	}

	if (!is_array($config['installedpackages']['pfblockerngdnsblsettings']['config'])) {
		$config['installedpackages']['pfblockerngdnsblsettings']['config'] = array();
	}

	if (!is_array($config['installedpackages']['pfblockerngipsettings']['config'][0])) {
		$config['installedpackages']['pfblockerngipsettings']['config'][0] = array();
	}

	if (!is_array($config['installedpackages']['pfblockerngdnsblsettings']['config'][0])) {
		$config['installedpackages']['pfblockerngdnsblsettings']['config'][0] = array();
	}

	if (!is_array($config['installedpackages']['pfblockerngipsettings']['config'][0]['v4suppression'])) {
		$config['installedpackages']['pfblockerngipsettings']['config'][0]['v4suppression'] = array();
	}

	if (!is_array($config['installedpackages']['pfblockerngipsettings']['config'][0]['suppression'])) {
		$config['installedpackages']['pfblockerngipsettings']['config'][0]['suppression'] = array();
	}

	if (!is_array($config['installedpackages']['pfblockerngipsettings']['config'][0]['tldexclusion'])) {
		$config['installedpackages']['pfblockerngipsettings']['config'][0]['tldexclusion'] = array();
	}

	foreach (array('ipsuppression', 'dnsblwhitelist', 'tldexclusion') as $key => $type) {
		if ($key == 0) {
			$clists[$type]['base64'] = &$config['installedpackages']['pfblockerngipsettings']['config'][0]['v4suppression'];
		} elseif ($key == 1) {
			$clists[$type]['base64'] = &$config['installedpackages']['pfblockerngdnsblsettings']['config'][0]['suppression'];
		} elseif ($key == 2) {
			$clists[$type]['base64'] = &$config['installedpackages']['pfblockerngdnsblsettings']['config'][0]['tldexclusion'];
		}

		$clists[$type]['data']		= array();
		if (isset($clists[$type]['base64']) && !empty($clists[$type]['base64'])) {
			$decoded = pfbng_text_area_decode($clists[$type]['base64'], TRUE, TRUE);
			if (!empty($decoded)) {
				foreach ($decoded as $line) {

					// Create string (Domain and Comment if found)
					if (isset($line[1])) {
						$clists[$type]['data'][$line[0]] = "{$line[0]} {$line[1]}\r\n";
					} else {
						$line[0] = trim($line[0]);
						$clists[$type]['data'][$line[0]] = "{$line[0]}\r\n";
					}
				}
			}
		}
	}
}

// Collect all existing unlocked Domains
$dnsbl_unlock	= pfb_unlock('read', 'dnsbl', '', '', '');

// Collect all existing unlocked IPs
$ip_unlock	= pfb_unlock('read', 'ip', '', '', '');

if (isset($_REQUEST)) {

	// Define alerts log filter rollup window variable and collect widget alert pivot details
	if (isset($_REQUEST['filterip']) || isset($_REQUEST['filterdnsbl'])) {

		if (isset($_REQUEST['filterip'])) {
			$filterfieldsarray[13]		= htmlspecialchars($_REQUEST['filterip']);
			$pfbdnscnt			= 0;
		}
		else {
			$filterfieldsarray[13]	= htmlspecialchars($_REQUEST['filterdnsbl']);
			$pfbdenycnt		= $pfbpermitcnt = $pfbmatchcnt = 0;
		}
		$pfb['filterlogentries']	= TRUE;
	}
	else {
		$pfb['filterlogentries']	= FALSE;
	}

	// Re-enable any Alert 'filter settings' on page refresh
	if (isset($_REQUEST['refresh'])) {
		$refresharr = unserialize(urldecode($_REQUEST['refresh']));
		if (isset($refresharr)) {
			foreach ($refresharr as $key => $type) {
				$filterfieldsarray[htmlspecialchars($key)] = htmlspecialchars($type) ?: null;
			}
		}
		$pfb['filterlogentries']	= TRUE;
	}
}


if (isset($_POST) && !empty($_POST)) {

	// Save Alerts tab customizations
	if (isset($_POST['save'])) {

		$pfb['aglobal']['alertrefresh']	= htmlspecialchars($_POST['alertrefresh'])			?: 'off';
		$pfb['aglobal']['pfbextdns']	= htmlspecialchars($_POST['pfbextdns'])				?: '8.8.8.8';
		$pfb['aglobal']['pfbpageload']	= htmlspecialchars($_POST['pfbpageload'])			?: 'default';
		$pfb['aglobal']['pfbblockstat']	= htmlspecialchars(implode(',', (array)$_POST['pfbblockstat']))	?: '';
		$pfb['aglobal']['pfbpermitstat']= htmlspecialchars(implode(',', (array)$_POST['pfbpermitstat']))?: '';
		$pfb['aglobal']['pfbmatchstat']	= htmlspecialchars(implode(',', (array)$_POST['pfbmatchstat']))	?: '';
		$pfb['aglobal']['pfbdnsblstat']	= htmlspecialchars(implode(',', (array)$_POST['pfbdnsblstat']))	?: '';


		foreach ($aglobal_array as $type => $value) {
			if (ctype_digit(htmlspecialchars($_POST[$type]))) {
				$pfb['aglobal'][$type] = htmlspecialchars($_POST[$type]);
			}

		}

		// Remove obsolete xml tag
		if (isset($pfb['aglobal']['hostlookup'])) {
			unset($pfb['aglobal']['hostlookup']);
		}

		write_config('pfBlockerNG: Update ALERT tab settings.');
		header("Location: /pfblockerng/pfblockerng_alerts.php");
		exit;
	}

	// Collect 'Filter selection' from 'Alert Statistics' Filter action and convert to existing filter fields
	foreach (array( 'srcipin' => 'srcip', 'srcipout' => 'srcip', 'dstipin' => 'dstip', 'dstipout' => 'dstip', 'srcport' => 'srcport',
			'dstport' => 'dstport', 'geoip' => 'geoip', 'aliasname' => 'alias', 'feed' => 'feed', 'dfeed' => 'feed',
			'dnsbltype' => 'feed', 'interface' => 'int', 'protocol' => 'proto', 'direction' => '', 'date' => 'date',
			'domain' => 'dstip', 'evald' => 'dstip', 'ip' => 'srcip', 'agent' => 'dnsbl', 'webtype' => 'dnsbl',
			'date' => 'date', 'datehr' => 'date', 'datehrmin' => 'date', 'tld' => 'dstip', 'groupblock' => 'alias','grouptotal' => 'alias')
			 as $submit_type => $filter_type) {

		if (isset($_POST['filterlogentries_submit_' . $submit_type]) && !empty($_POST['filterlogentries_submit_' . $submit_type])) {

			// Split SRC/DST In/Outbound field into two filter fields (IP/GeoIP)
			if (strpos($submit_type, 'srcip') !== FALSE || strpos($submit_type, 'dstip') !== FALSE) {

				$data = explode(',', $_POST['filterlogentries_submit_' . $submit_type]);
				$_POST['filterlogentries_' . $filter_type]	= htmlspecialchars($data[0]);
				$_POST['filterlogentries_geoip']		= htmlspecialchars($data[1]);
			}
			else {
				$_POST['filterlogentries_' . $filter_type] = htmlspecialchars($_POST['filterlogentries_submit_' . $submit_type]);
			}
			$_POST['filterlogentries_submit'] = 'Apply Filter';
			break;
		}
	}

	// Filter Alerts based on user defined 'filter settings'
	if (isset($_POST['filterlogentries_submit']) && $_POST['filterlogentries_submit'] == 'Apply Filter') {
		$pfb['filterlogentries'] = TRUE;
		$filterfieldsarray = array();

		// Determine which tables to show and limit entries as defined

		// Use widget entries setting
		if (isset($_REQUEST['rule'])) {
			;
		}

		// Limit Alerts Filter to 'Filter Limit entries' setting
		elseif ($pfbfilterlimitentries != 0) {
			$pfbdenycnt = $pfbpermitcnt = $pfbmatchcnt = $pfbfilterlimitentries;
		}

		$submit_dnsbl = array_flip(array('domain', 'evald', 'dfeed', 'dnsbltype', 'ip', 'agent', 'tld', 'groupblock', 'grouptotal',
							'webtype', 'datehr', 'datehrmin'));
		foreach ($_POST as $key => $post) {
			if (strpos($key, 'filterlogentries_submit_') !== FALSE) {
				$submit_type = substr($key, strrpos($key, '_') + 1);

				// Filter for DNSBL Events
				if (isset($submit_dnsbl[$submit_type])) {
					$pfbdenycnt = $pfbpermitcnt = $pfbmatchcnt = 0;
				}

				// Filter for IP Events
				else {
					$pfbdnscnt = 0;
				}
				break;
			}
		}

		foreach (array( 0 => 'rule', 2 => 'int', 6 => 'proto', 7 => 'srcip', 8 => 'dstip',
				9 => 'srcport', 10 => 'dstport', 12 => 'geoip', 13 => 'alias',
				15 => 'feed', 90 => 'dnsbl', 99 => 'date') as $key => $type) {

			$type = htmlspecialchars($_POST['filterlogentries_' . "{$type}"]) ?: null;
			if ($key == 6) {
				$type = strtolower("{$type}");
			}

			$filterfieldsarray[$key] = $type ?: null;
		}
	}

	// Clear Filter Alerts
	if (isset($_POST['filterlogentries_clear']) && !empty($_POST['filterlogentries_clear'])) {
		$pfb['filterlogentries'] = FALSE;
		$filterfieldsarray = array();
	}

	// Add an IPv4 (/32 or /24 only) to the suppression customlist
	if (isset($_POST['addsuppress']) && !empty($_POST['addsuppress'])) {

		$ip	= htmlspecialchars($_POST['ip']);
		$cidr	= htmlspecialchars($_POST['cidr']);
		$table	= htmlspecialchars($_POST['table']);
		$descr	= htmlspecialchars($_POST['descr']);

		// If IP is not valid or CIDR field is empty, exit
		if (!is_ipaddrv4($ip) || empty($cidr)) {
			$savemsg = gettext('Cannot Suppress: IPv4 not valid or CIDR value missing');
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}

		$savemsg1 = "Host IP address {$ip}";
		$ix = ip_explode($ip);	// Explode IP into evaluation strings
		if ($cidr == 32) {
			$pfb_pfctl = exec("{$pfb['pfctl']} -t {$table} -T show | grep {$ip} 2>&1");
			if (!empty($pfb_pfctl)) {
				$savemsg2 = ' : Removed /32 entry';
				exec("{$pfb['pfctl']} -t {$table} -T delete {$ip} 2>&1");
			}
			else {
				$pfb_pfctl = array();
				exec("{$pfb['pfctl']} -t {$table} -T delete {$ix[5]} 2>&1", $pfb_pfctl);
				if (preg_grep("/1\/1 addresses deleted/", $pfb_pfctl)) {
					$savemsg2 = ' : Removed /24 entry, added 254 addr';
					for ($k=0; $k <= 255; $k++) {
						if ($k != $ix[4]) {
							exec("{$pfb['pfctl']} -t {$table} -T add {$ix[6]}{$k} 2>&1");
						}
					}
				}
				else {
					$savemsg = gettext("Not Suppressed. Host IP address {$ip} is blocked by a CIDR other than /24");
					header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
					exit;
				}
			}
		}
		else {
			$cidr = 24;
			$savemsg2 = ' : Removed /24 entry';
			$pfb_pfctl = array();
			exec("{$pfb['pfctl']} -t {$table} -T delete {$ix[5]} 2>&1", $pfb_pfctl);
			if (!preg_grep("/1\/1 addresses deleted/", $pfb_pfctl)) {
				$savemsg2 = ' : Removed all entries';
				// Remove 0-255 IP address from alias table
				for ($j=0; $j <= 255; $j++) {
					exec("{$pfb['pfctl']} -t {$table} -T delete {$ix[6]}{$j} 2>&1");
				}
			}
		}

		// Save IP to the v4 Suppression List
		if (isset($clists['ipsuppression']['data'][$ix[5]]) || isset($clists['ipsuppression']['data'][$ip . '/32'])) {
			$savemsg = gettext("Host IP address {$ip} already exists in the IPv4 Suppression customlist.");
		} else {
			$v4suppression_dat = '';
			if ($cidr == 24) {
				$v4suppression_dat .= "{$ix[5]}";
			} else {
				$v4suppression_dat .= "{$ip}/32";
			}

			if (!empty($descr)) {
				$v4suppression_dat .= " # {$descr}";
			}

			$savemsg = gettext($savemsg1) . gettext($savemsg2) . gettext(' and added to the IPv4 Suppression customlist.');
			$pfbupdate = TRUE;
		}

		if ($pfbupdate) {
			$data = '';
			foreach ($clists['ipsuppression']['data'] as $line) {
				$data .= "{$line}";
			}
			$data .= "{$v4suppression_dat}\r\n";
			$clists['ipsuppression']['base64'] = base64_encode($data);
			write_config("pfBlockerNG: Added {$ip} to the IPv4 Suppression customlist");
			pfb_create_suppression_file();	// Create pfbsuppression.txt
		}
		header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
		exit;
	}

	// Add Domain/CNAME(s) to the DNSBL Whitelist customlist or TLD Exclusion customlist
	if (isset($_POST['addwhitelistdom']) && !empty($_POST['addwhitelistdom'])) {

		$domain		= htmlspecialchars($_POST['domain']);
		$table		= htmlspecialchars($_POST['table']);

		// If Domain or Table field is empty, exit.
		if (empty($domain) || empty($table)) {
			$savemsg = gettext('Cannot Whitelist - Domain name or DNSBL Table value missing');
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}

		$descr		= htmlspecialchars($_POST['descr']);

		$wildcard = FALSE;
		if ($_POST['dnsbl_wildcard'] == 'true') {
			$wildcard = TRUE;
		}

		$dnsbl_exclude = FALSE;
		if ($_POST['dnsbl_exclude'] == 'true') {
			$dnsbl_exclude = TRUE;
		}

		// Query for CNAME(s)
		$cname_list = array();
		exec("/usr/bin/drill {$domain} @{$pfb['extdns']} | /usr/bin/awk '/CNAME/ {sub(\"\.$\", \"\", $5); print $5;}' 2>&1", $cname_list);

		// Remove 'www.' prefix
		if (substr($domain, 0, 4) == 'www.') {
			$domain = substr($domain, 4);
		}

		// Whitelist Domain/CNAME(s)
		if (!$dnsbl_exclude) {

			// Collect Domains/Sub-Domains to Whitelist (used by grep -vF -f cmd to remove Domain/Sub-Domains)
			if ($wildcard) {
				$dnsbl_remove	= ".{$domain} 60\n\"{$domain} 60\n";
			} else {
				$dnsbl_remove	= "\"{$domain} 60\n\"www.{$domain} 60\n";
			}

			if (!empty($descr)) {
				if ($wildcard) {
					$whitelist = ".{$domain} # {$descr}";
				} else {
					$whitelist = "{$domain} # {$descr}\r\nwww.{$domain} # {$descr}";
				}
			} else {
				if ($wildcard) {
					$whitelist = ".{$domain}";
				} else {
					$whitelist = "{$domain}\r\nwww.{$domain}";
				}
			}

			// Remove 'Domain and CNAME(s)' from Unbound Resolver pfb_dnsbl.conf file
			if (!empty($cname_list)) {
				$whitelist	.= "\r\n";
				$removed	 = "{$domain} | ";

				$cnt = (count($cname_list) -1);
				foreach ($cname_list as $key => $cname) {
					$removed	.= "{$cname} | ";
					$dnsbl_remove	.= "\"{$cname} 60\n";

					if ($wildcard) {
						$whitelist	.= '.';
						$dnsbl_remove	.= ".{$cname} 60\n";
					}
					$whitelist .= "{$cname} # CNAME for ({$domain})";

					if ($cnt != $key) {
						$whitelist .= "\r\n";
					}
				}
				$savemsg = gettext('Removed - Domain|CNAME(s) | ') . "{$removed}";
			}
			else {
				$savemsg = gettext('Removed Domain: [ ') . "{$domain}" . ' ]';
			}
			$savemsg .= gettext(" from DNSBL. You may need to flush your OS/Browser DNS Cache!");

			// Save changes
			if (!isset($clists['dnsblwhitelist']['data'][$domain])) {
				$data = '';
				foreach ($clists['dnsblwhitelist']['data'] as $line) {
					$data .= "{$line}";
				}
				$data .= "{$whitelist}\r\n";
				$clists['dnsblwhitelist']['base64'] = base64_encode($data);
				write_config("pfBlockerNG: Added [ {$domain} ] to DNSBL Whitelist");
			}

			// Create tempfile for DNSBL Whitelisting
			$tmp = tempnam('/tmp', 'dnsbl_alert_');

			// Save DNSBL Whitelist file of Domain/CNAME(s)
			@file_put_contents("{$tmp}.adup", $dnsbl_remove, LOCK_EX);

			// Collect all matching whitelisted Domain/CNAME(s)
			if (file_exists("{$tmp}.adup") && filesize("{$tmp}.adup") > 0) {
				exec("{$pfb['grep']} -F -f {$tmp}.adup {$pfb['dnsbl_file']}.conf > {$tmp}.supp 2>&1");
			}

			if (file_exists("{$tmp}.supp") && filesize("{$tmp}.supp") > 0) {

				exec("{$pfb['grep']} 'local-zone:' {$tmp}.supp | {$pfb['cut']} -d '\"' -f2 > {$tmp}.zone 2>&1");
				exec("{$pfb['grep']} '^local-data:' {$tmp}.supp | {$pfb['cut']} -d ' ' -f2 | tr -d '\"' > {$tmp}.data 2>&1");

				// Remove all Whitelisted Domain/CNAME(s) from Unbound using unbound-control
				$chroot_cmd = "chroot -u unbound -g unbound / /usr/local/sbin/unbound-control -c {$g['unbound_chroot_path']}/unbound.conf";

				if (file_exists("{$tmp}.zone") && filesize("{$tmp}.zone") > 0) {
					exec("{$chroot_cmd} local_zones_remove < {$tmp}.zone 2>&1");
				}
				if (file_exists("{$tmp}.data") && filesize("{$tmp}.data") > 0) {
					exec("{$chroot_cmd} local_datas_remove < {$tmp}.data 2>&1");
				}
			}
			unlink_if_exists("{$tmp}*");

			// Flush any Domain/CNAME(s) entries in Unbound Resolver Cache
			exec("{$chroot_cmd} flush {$domain} 2>&1");
			if (!empty($cname_list)) {
				foreach ($cname_list as $cname) {
					exec("{$chroot_cmd} flush {$cname}. 2>&1");
				}
			}
		}

		// Save Domain/CNAME(s) to the TLD Exclusion customlist
		else {
			$excluded = "{$domain} | ";
			if (!empty($descr)) {
				$exclude_string = "{$domain} # {$descr}";
			} else {
				$exclude_string = "{$domain}";
			}

			// Process CNAME(s)
			if (!empty($cname_list)) {
				$exclude_string .= "\r\n";
				$cnt = (count($cname_list) -1);

				foreach ($cname_list as $key => $cname) {
					$excluded	.= "{$cname} | ";
					$exclude_string .= "{$cname} # CNAME for ({$domain})";

					if ($cnt != $key) {
						$exclude_string .= "\r\n";
					}
				}
				$savemsg = gettext('Added Domain|CNAME(s) | ') . "{$excluded} ]";
			}
			else {
				$savemsg = gettext('Added Domain [ ') . "{$domain} ]";
			}
			$savemsg .= gettext(" to the TLD Exclusion customlist.");

			if (!isset($clists['tldexclusion']['data'][$domain])) {
				$data = '';
				foreach ($clists['tldexclusion']['data'] as $line) {
					$data .= "{$line}";
				}
				$data .= "{$exclude_string}\r\n";
				$clists['tldexclusion']['base64'] = base64_encode($data);
				write_config("pfBlockerNG: Added [ {$domain} ] to DNSBL TLD Exclusion customlist.");
			}
		}
		header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
		exit;
	}

	// Delete entry from customlists (IP Suppression, DNSBL Whitelist, TLD Exclusion and IPv4/6 Permit Customlists)
	if (isset($_POST['entry_delete']) && !empty($_POST['entry_delete'])) {

		$entry = htmlspecialchars($_POST['domain']);
		if (empty($entry)) {
			$savemsg = gettext('Cannot Delete entry, value missing.');
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}

		$chroot_cmd = "chroot -u unbound -g unbound / /usr/local/sbin/unbound-control -c {$g['unbound_chroot_path']}/unbound.conf";

		$pfb_found = TRUE;
		switch ($_POST['entry_delete']) {
			case 'delete_domain':
				$savemsg = "The Domain [ {$entry} ] has been deleted from the DNSBL Whitelist!";
				if (isset($clists['dnsblwhitelist']['data'][$entry])) {
					unset($clists['dnsblwhitelist']['data'][$entry]);
					exec("{$chroot_cmd} local_data {$entry} '60 IN A {$pfb['dnsbl_vip']}' 2>&1");
				}
			case 'delete_domainwildcard':
				$type = 'DNSBL Whitelist';
				if ($_POST['entry_delete'] == 'delete_domainwildcard') {
					$savemsg = "The Wildcard Domain [ .{$entry} ] has been deleted from the {$type} customlist!";
					if (isset($clists['dnsblwhitelist']['data']['.' . $entry])) {
						unset($clists['dnsblwhitelist']['data']['.' . $entry]);
						exec("{$chroot_cmd} local_zone {$entry} redirect 2>&1");
						exec("{$chroot_cmd} local_data {$entry} '60 IN A {$pfb['dnsbl_vip']}' 2>&1");
					}
				}

				// Remove Domain from unlock file
				pfb_unlock('lock', 'dnsbl', $entry, '', $dnsbl_unlock);

				$data = '';
				foreach ($clists['dnsblwhitelist']['data'] as $line) {
					// Delete any associated CNAME entries
					if (strpos($line, "({$entry})") === FALSE) {
						$data .= "{$line}";
					}
				}
				$clists['dnsblwhitelist']['base64'] = base64_encode($data);
				break;
			case 'delete_exclusion':
				$type = 'TLD Exclusion';
				$savemsg = "The Domain [ {$entry} ] has been deleted from the {$type} customlist!";
				if (isset($clists['tldexclusion']['data'][$entry])) {
					unset($clists['tldexclusion']['data'][$entry]);
				}
				$data = '';
				foreach ($clists['tldexclusion']['data'] as $line) {
					$data .= "{$line}";
				}
				$clists['tldexclusion']['base64'] = base64_encode($data);
				break;
			case 'delete_ip':
				if (!is_ipaddrv4($entry)) {
					$pfb_found	= FALSE;
					$savemsg	= "IPv4: [ {$entry} ] is not valid and cannot be suppressed!";
					break;
				}

				$type	= 'IPv4 Suppression';
				$table	= htmlspecialchars($_POST['table']);
				$ix	= ip_explode($entry);	// Explode IP into evaluation strings

				// Check if IP has 255 single entries (User suppressed /32 for a /24 Blocked IP)
				$pfb_pfctl = array();
				$ip_cnt = exec("{$pfb['pfctl']} -t {$table} -T show | {$pfb['grep']} {$ix[6]} | {$pfb['grep']} -c ^ 2>&1");

				$ip_revert = '';
				// Remove /32 Suppressed IP in Suppression customlist and Re-Add /32 Blocked IP to Aliastable
				if (isset($clists['ipsuppression']['data'][$entry . '/32'])) {
					unset($clists['ipsuppression']['data'][$entry . '/32']);

					$ip_revert = "{$entry}/32";
					exec("{$pfb['pfctl']} -t {$table} -T add {$ip_revert} 2>&1");

					// Remove 0-255 IP address from aliastable excluding single entry
					if ($ip_cnt >= 255) {
						for ($k=0; $k <= 255; $k++) {
							if ($k != $ix[4]) {
								exec("{$pfb['pfctl']} -t {$table} -T delete {$ix[6]}{$k} 2>&1");
							}
						}
					}
				}

				// Remove /24 Suppressed IP in Suppression customlist and Re-Add /24 Blocked IP to Aliastable
				elseif (isset($clists['ipsuppression']['data'][$ix[5]])) {
					unset($clists['ipsuppression']['data'][$ix[5]]);

					$ip_revert = $ix[5];
					exec("{$pfb['pfctl']} -t {$table} -T add {$ip_revert} 2>&1");
				}

				if (!empty($ip_revert)) {
					$data = '';
					foreach ($clists['ipsuppression']['data'] as $line) {
						$data .= "{$line}";
					}
					$clists['ipsuppression']['base64'] = base64_encode($data);
					$savemsg = "Removed [ {$ip_revert} ] from {$type} customlist and re-added it back into the aliastable [ {$table} ]";
				}
				else {
					$savemsg = "IP: [ {$entry} ] was not found in {$type} customlist!";
				}
				break;
			case 'delete_ipwhitelist':
				$table = htmlspecialchars($_POST['table']);
				$vtype = 6;
				if (strpos($table, '_v4')) {
					$vtype = 4;
				}
				$type = "IPv{$vtype} Permit {$table}";

				if (isset($clists['ipwhitelist' . $vtype][$table]['data'][$entry])) {
					unset($clists['ipwhitelist' . $vtype][$table]['data'][$entry]);
					exec("{$pfb['pfctl']} -t {$table} -T delete {$entry} 2>&1");

					$data = '';
					foreach ($clists['ipwhitelist' . $vtype][$table]['data'] as $line) {
						$data .= "{$line}";
					}
					$clists['ipwhitelist' . $vtype][$table]['base64'] = base64_encode($data);
					$aname = substr(substr($table, 4),0, -3);					// Remove 'pfB_' and '_v4'
					touch("{$pfb['permitdir']}/{$aname}_custom_v{$vtype}.update");			// Set Flag for Cron/Update process
					$savemsg = "The IP [ {$entry} ] has been deleted from the [ {$table} ] Permit Alias customlist.";
				}
				else {
					$savemsg = "IP: [ {$entry} ] was not found in {$type} customlist!";
				}
				break;
			default:
				$pfb_found = FALSE;
				break;
		}

		if ($pfb_found) {
			write_config("pfBlockerNG: Deleted [ {$entry} ] from {$type} customlist");
		}
		header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
		exit;
	}

	// Unlock/Lock DNSBL events
	if (isset($_POST['dnsbl_remove']) && !empty($_POST['dnsbl_remove'])) {

		$domain		= htmlspecialchars($_POST['domain']);
		$dnsbl_type	= htmlspecialchars($_POST['dnsbl_type']);

		// If Domain or DNSBL type field is empty, exit.
		if (empty($domain) || empty($dnsbl_type)) {
			$savemsg = gettext('Cannot Lock/Unlock - Domain name or DNSBL Type value missing');
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}

		$chroot_cmd = "chroot -u unbound -g unbound / /usr/local/sbin/unbound-control -c {$g['unbound_chroot_path']}/unbound.conf";
		// Unlock Domain
		if ($_POST['dnsbl_remove'] == 'unlock') {
			$cmd = 'local_data_remove';
			if ($dnsbl_type == 'TLD') {
				$cmd = 'local_zone_remove';
			}
			exec("{$chroot_cmd} {$cmd} {$domain} 2>&1");
			exec("{$chroot_cmd} flush {$domain} 2>&1");

			// Query for CNAME(s)
			exec("/usr/bin/drill {$domain} @{$pfb['extdns']} | /usr/bin/awk '/CNAME/ {sub(\"\.$\", \"\", $5); print $5;}'", $cname_list);
			if (!empty($cname_list)) {
				foreach ($cname_list as $cname) {
					exec("{$chroot_cmd} flush {$cname}. 2>&1");
				}
			}

			// Add Domain to unlock file
			pfb_unlock('unlock', 'dnsbl', $domain, $dnsbl_type, $dnsbl_unlock);
			$savemsg = "The Domain [ {$domain} ] has been temporarily Unlocked from DNSBL!";
		}

		// Lock Domain
		elseif ($_POST['dnsbl_remove'] == 'lock') {
			if ($dnsbl_type == 'TLD') {
				exec("{$chroot_cmd} local_zone {$domain} redirect 2>&1");
			}
			exec("{$chroot_cmd} local_data {$domain} '60 IN A {$pfb['dnsbl_vip']}' 2>&1");

			// Remove Domain from unlock file
			pfb_unlock('lock', 'dnsbl', $domain, $dnsbl_type, $dnsbl_unlock);
			$savemsg = "The Domain [ {$domain} ] has been Locked into DNSBL!";
		}
		elseif ($_POST['dnsbl_remove'] == 'relock') {
			if ($dnsbl_type == 'TLD') {
				exec("{$chroot_cmd} local_zone {$domain} redirect 2>&1");
			}
			exec("{$chroot_cmd} local_data {$domain} '60 IN A {$pfb['dnsbl_vip']}' 2>&1");

			// Add Domain to unlock file
			pfb_unlock('unlock', 'dnsbl', $domain, $dnsbl_type, $dnsbl_unlock);
			$savemsg = "The Domain [ {$domain} ] has been temporarily Re-Locked into DNSBL!";
		}
		elseif ($_POST['dnsbl_remove'] == 'reunlock') {
			$cmd = 'local_data_remove';
			if ($dnsbl_type == 'TLD') {
				$cmd = 'local_zone_remove';
			}
			exec("{$chroot_cmd} {$cmd} {$domain} 2>&1");
			exec("{$chroot_cmd} flush {$domain} 2>&1");

			// Remove Domain from unlock file
			pfb_unlock('lock', 'dnsbl', $domain, $dnsbl_type, $dnsbl_unlock);
			$savemsg = "The Domain [ {$domain} ] has been Unlocked from DNSBL!";
		}
		header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
		exit;
	}

	// Unlock/Lock IP events
	if (isset($_POST['ip_remove']) && !empty($_POST['ip_remove'])) {

		$ip	= htmlspecialchars($_POST['ip']);
		$table	= htmlspecialchars($_POST['table']);

		// If IP or table field is empty, exit.
		if ((!is_ipaddr($ip) && !is_subnet($ip)) || empty($table)) {
			$savemsg = gettext('Cannot Lock/Unlock - IP or table missing');
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}

		// Unlock IP
		if ($_POST['ip_remove'] == 'unlock') {
			exec("{$pfb['pfctl']} -t {$table} -T delete {$ip} 2>&1");
			pfb_unlock('unlock', 'ip', $ip, $table, $ip_unlock);
			$savemsg = "The IP [ {$ip} ] has been temporarily Unlocked from table [ {$table} ]!";
		}

		// Lock IP
		elseif ($_POST['ip_remove'] == 'lock') {
			exec("{$pfb['pfctl']} -t {$table} -T add {$ip} 2>&1");
			pfb_unlock('lock', 'ip', $ip, $table, $ip_unlock);
			$savemsg = "The IP [ {$ip} ] has been re-locked into table [ {$table} ]!";
		}
		header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
		exit;
	}

	// Whitelist IP events
	if (isset($_POST['ip_white']) && $_POST['ip_white'] == 'true') {

		$ip	= htmlspecialchars($_POST['ip']);
		$table	= htmlspecialchars($_POST['table']);
		$descr	= htmlspecialchars($_POST['descr']);

		$vtype = 6;
		if (strpos($table, '_v4')) {
			$vtype = 4;
		}

		// If IP or table field is empty, exit.
		if (!is_ipaddr($ip) || empty($table)) {
			$savemsg = gettext('Cannot Whitelist - IP address or Whitelist missing');
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}

		// Create new IP Whitelist Alias
		if (substr($table, 0, 4) == 'NEW_') {
			$table = substr($table, 4);
			header("Location: /pfblockerng/pfblockerng_category_edit.php?type=ipv{$vtype}&act=addgroup&atype=Whitelist|{$ip}|{$descr}#Customlist");
			exit;
		}

		if (!isset($clists['ipwhitelist' . $vtype][$table]['data'][$ip])) {
			exec("{$pfb['pfctl']} -t {$table} -T add {$ip} 2>&1");
			$descr = htmlspecialchars($_POST['descr']) ?: '';

			if (!empty($descr)) {
				$whitelist_string = "{$ip} # {$descr}\r\n";
			} else {
				$whitelist_string = "{$ip}\r\n";
			}

			$data = '';
			foreach ($clists['ipwhitelist' . $vtype][$table]['data'] as $line) {
				$data .= "{$line}";
			}
			$data .= "{$whitelist_string}";

			$clists['ipwhitelist' . $vtype][$table]['base64'] = base64_encode($data);
			write_config("pfBlockerNG: Added [ {$ip} ] to [ {$table} ] Whitelist");

			$aname = substr(substr($table, 4),0, -3);					// Remove 'pfB_' and '_v4'
			touch("{$pfb['permitdir']}/{$aname}_custom_v{$vtype}.update");			// Set Flag for Cron/Update process

			$savemsg = "The IP [ {$ip} ] has been added to the [ {$table} ] Permit Alias customlist.";
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}
	}
}


// Define common variables and arrays for report tables
$continents	= array_flip(array('pfB_Africa', 'pfB_Antarctica', 'pfB_Asia', 'pfB_Europe', 'pfB_NAmerica', 'pfB_Oceania', 'pfB_SAmerica', 'pfB_Top'));

$supp_ip_txt	= "Clicking this Suppression Icon, will immediately remove the block."
		. "\n\nNote:"
		. "\n1) The Host will be added to the IPv4 Suppression custom list."
		. "\n2) Only 32 or 24 CIDR IPs can be suppressed with the '+' icon."
		. "\n3) Suppressing a /32 CIDR is better than suppressing the full /24"
		. "\n4) Manual entries to the 'IPv4 Suppression' custom list will not immediately remove existing blocked hosts"
		. "\n&emsp;and will require a Force Reload to remove the blocked hosts.";

// Collect Interfaces
$dnsbl_int = array();
if (is_array($config['interfaces'])) {
	foreach ($config['interfaces'] as $int) {
		if ($int['ipaddr'] != 'dhcp' && !empty($int['ipaddr']) && !empty($int['subnet'])) {
			$dnsbl_int[] = array("{$int['ipaddr']}/{$int['subnet']}", "{$int['descr']}");
		}
	}
}

// Collect DHCP hostnames/IPs
$local_hosts = pfb_collect_localhosts();

// Collect Alert Statistics
if ($alert_summary) {

	if ($alert_view != 'dnsbl_stat') {
		$stat_info = array(	'date'		=> 1,
					'interface'	=> 4,
					'protocol'	=> 8,
					'srcipin'	=> 9,
					'srcipout'	=> 9,
					'dstipin'	=> 10,
					'dstipout'	=> 10,
					'srcport'	=> 11,
					'dstport'	=> 12,
					'direction'	=> 13,
					'geoip'		=> 14,
					'aliasname'	=> 15,
					'feed'		=> 17);
	} else {
		$stat_info = array(	'webtype'	=> 1,
					'date'		=> 2,
					'datehr'	=> 2,
					'datehrmin'	=> 2,
					'domain'	=> 3,
					'tld'		=> 3,
					'ip'		=> 4,
					'agent'		=> 5,
					'dnsbltype'	=> 6,
					'evald'		=> 8,
					'dfeed'		=> 9);
	}

	$su_cmd		= "sort | uniq -c";
	$grep_cmd	= "{$pfb['grep']} -v";
	$sss_cmd	= "sort | uniq -c | {$pfb['sed']} 's/^ *//' | sort -nr";

	$alert_stats = array();
	foreach ($stat_info as $stat_type => $column) {
		if (file_exists($alert_log)) {

			$cut_cmd = "{$pfb['cut']} -d ',' -f{$column}";

			if ($alert_view != 'dnsbl_stat') {
				$unknown_msg = 'Unknown';
			} else {
				$unknown_msg = 'Not available for HTTPS alerts';
			}

			$agent_cmd = '';
			if ($stat_type == 'agent') {
				$agent_cmd = "cut -d '|' -f3 | ";
			}

			$stats = array();
			switch ($stat_type) {
				case 'date':
					exec("{$cut_cmd} {$alert_log} | cut -d ' ' -f1-2 | uniq -c 2>&1", $stats);
					$stats = array_reverse($stats);
					break;
				case 'datehr':
					exec("{$cut_cmd} {$alert_log} | cut -d ':' -f1 | sort | uniq -c | sort -nr 2>&1", $stats);
					break;
				case 'datehrmin':
					exec("{$cut_cmd} {$alert_log} | cut -d ':' -f1,2 | sort | uniq -c | sort -nr 2>&1", $stats);
					break;
				case 'srcipin':
					exec("{$cut_cmd},13,14,18 {$alert_log} | {$grep_cmd} ',out,' | {$sss_cmd} | {$pfb['sed']} 's/,in,/,/' 2>&1", $stats);
					break;
				case 'srcipout':
					exec("{$cut_cmd},13,14 {$alert_log} | {$grep_cmd} ',in,' | {$sss_cmd} | {$pfb['sed']} 's/,out,/,/' 2>&1", $stats);
 					break;
				case 'dstipin':
					exec("{$cut_cmd},13,14 {$alert_log} | {$grep_cmd} ',out,' | {$sss_cmd} | {$pfb['sed']} 's/,in,/,/' 2>&1", $stats);
					break;
				case 'dstipout':
					exec("{$cut_cmd},13,14,18 {$alert_log} | {$grep_cmd} ',in,' | {$sss_cmd} | {$pfb['sed']} 's/,out,/,/' 2>&1", $stats);
					break;
				case 'tld':
					exec("{$cut_cmd} {$alert_log} | rev | cut -d '.' -f1 | rev | sort | uniq -c | sort -nr 2>&1", $stats);
					break;
				case 'srcport':
					exec("{$cut_cmd} {$alert_log} | {$su_cmd} | {$pfb['awk']} -F ' ' '\$2 <= 1024 || \$2 ~ /[a-zA-Z]/' | sort -nr 2>&1", $stats);
					break;
				case 'domain':
				case 'evald':
					exec("{$cut_cmd},6 {$alert_log} | {$su_cmd} | sort -nr 2>&1", $stats);
					break;
				default:
					exec("{$cut_cmd} {$alert_log} | {$agent_cmd} {$su_cmd} | sort -nr 2>&1", $stats);
					break;
			}

			if (!empty($stats)) {
				foreach($stats as $key => $line) {
					$data = array_map('trim', explode(' ', trim($line), 2));
					$alert_stats[$alert_view][$stat_type][$data[1] ?: $unknown_msg] = $data[0] ?: 0;
				}
			}
			else {
				$alert_stats[$alert_view][$stat_type] = array();
				if ($alert_view == 'dnsbl_stat') {
					$alert_stats[$alert_view]['grouptotal'] = array();
					$alert_stats[$alert_view]['groupblock'] = array();
				}
			}
			$alert_stats['count'][$alert_view] = exec("{$pfb['grep']} -c ^ {$alert_log} 2>&1") ?: 0;
		}
		else {
			$alert_stats[$alert_view][$stat_type]	= array();
			$alert_stats['count'][$alert_view]	= 0;
			if ($alert_view == 'dnsbl_stat') {
				$alert_stats[$alert_view]['grouptotal'] = array();
				$alert_stats[$alert_view]['groupblock'] = array();
			}
		}

		// Collect DNSBL widget statistics
		if ($alert_view == 'dnsbl_stat') {
			$alert_stats[$alert_view]['grouptotal'] = array();
			$alert_stats[$alert_view]['groupblock'] = array();

			if (file_exists($pfb['dnsbl_info'])) {
				$db_handle = pfb_open_sqlite(1, 'Report Stats');
				if ($db_handle) {
					$result = $db_handle->query("SELECT * FROM dnsbl;");
					if ($result) {
						while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
							if ($res['entries'] == 'disabled') {
								$res['entries']		= 0;
								$res['groupname']	= "{$res['groupname']}&emsp;(Disabled)";
							}
							$alert_stats[$alert_view]['grouptotal'][$res['groupname']] = $res['entries'];
							$alert_stats[$alert_view]['groupblock'][$res['groupname']] = $res['counter'];
						}

						array_multisort($alert_stats[$alert_view]['grouptotal'], SORT_DESC, 1);
						array_multisort($alert_stats[$alert_view]['groupblock'], SORT_DESC, 1);
					}
				}
				$db_handle->close();
				unset($db_handle);
			}
		}
	}
}


// Function to Filter Alerts report on user defined input
function pfb_match_filter_field($flent, $fields) {

	if (isset($fields)) {
		foreach ($fields as $key => $field) {
			if (empty($field)) {
				continue;
			}

			$field_regex = str_replace('/', '\/', str_replace('\/', '/', $field));
			if (strpos($field_regex, '(') !== FALSE) {
				$field_regex = str_replace('(', '\(', str_replace('\(', '(', $field_regex));
				$field_regex = str_replace(')', '\)', str_replace('\)', ')', $field_regex));
			}
			if (strpos($field_regex, '[') !== FALSE) {
				$field_regex = str_replace(']', '\]', str_replace('\]', ']', $field_regex));
				$field_regex = str_replace('[', '\[', str_replace('\[', '[', $field_regex));
			}

			if (strpos($field, '!') !== FALSE) {
				$field = substr($field, 1);
				if (@preg_match("/{$field_regex}/i", $flent[$key])) {
					return FALSE;
				}
			}
			else {
				if (!@preg_match("/{$field_regex}/i", $flent[$key])) {
					return FALSE;
				}
			}
		}
	}
	return TRUE;
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('Alerts'));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', '@self');
include_once('head.inc');

// Define default Alerts Tab href link (Top row)
$get_req = pfb_alerts_default_page();

// refresh every 60 secs
if ($alertrefresh == 'on') {

	if ($pfb['filterlogentries']) {
		// Refresh page with 'Filter options' if defined.
		$refreshentries = urlencode(serialize($filterfieldsarray));
		print ("<meta http-equiv=\"refresh\" content=\"60;url=/pfblockerng/pfblockerng_alerts.php?refresh={$refreshentries}\" />\n");
	} elseif ($alert_summary) {
		print ("<meta http-equiv=\"refresh\" content=\"60;url=/pfblockerng/pfblockerng_alerts.php?view={$alert_view}\" />\n");
	} else {
		print ("<meta http-equiv=\"refresh\" content=\"60;url=/pfblockerng/pfblockerng_alerts.php\" />\n");
	}
}

if (isset($savemsg)) {
	print_info_box($savemsg);
}

if (isset($_REQUEST['savemsg'])) {
	$savemsg = htmlspecialchars($_REQUEST['savemsg']);
	print_info_box($savemsg);
}

$tab_array   = array();
$tab_array[] = array(gettext('General'),	false,	'/pfblockerng/pfblockerng_general.php');
$tab_array[] = array(gettext('IP'),		false,	'/pfblockerng/pfblockerng_ip.php');
$tab_array[] = array(gettext('DNSBL'),		false,	'/pfblockerng/pfblockerng_dnsbl.php');
$tab_array[] = array(gettext('Update'),		false,	'/pfblockerng/pfblockerng_update.php');
$tab_array[] = array(gettext('Reports'),	true,	"/pfblockerng/pfblockerng_alerts.php{$get_req}");
$tab_array[] = array(gettext('Feeds'),		false,	'/pfblockerng/pfblockerng_feeds.php');
$tab_array[] = array(gettext('Logs'),		false,	'/pfblockerng/pfblockerng_log.php');
$tab_array[] = array(gettext('Sync'),		false,	'/pfblockerng/pfblockerng_sync.php');
display_top_tabs($tab_array, true);

$tab_array   = array();
$tab_array[] = array(gettext('Alerts'),			$active['alerts'],	'/pfblockerng/pfblockerng_alerts.php');
$tab_array[] = array(gettext('IP Block Stats'),		$active['ip_block'],	'/pfblockerng/pfblockerng_alerts.php?view=ip_block_stat');
$tab_array[] = array(gettext('IP Permit Stats'),	$active['ip_permit'],	'/pfblockerng/pfblockerng_alerts.php?view=ip_permit_stat');
$tab_array[] = array(gettext('IP Match Stats'),		$active['ip_match'],	'/pfblockerng/pfblockerng_alerts.php?view=ip_match_stat');
$tab_array[] = array(gettext('DNSBL Stats'),		$active['dnsbl'],	'/pfblockerng/pfblockerng_alerts.php?view=dnsbl_stat');
display_top_tabs($tab_array, true);

// Create Form
$form = new Form(false);
$form->setAction('/pfblockerng/pfblockerng_alerts.php');

if (!$alert_summary) {
	// Build 'Shortcut Links' section
	$section = new Form_Section(NULL);
	$section->addInput(new Form_StaticText(
		'Links',
		'<small>'
		. '<a href="/firewall_aliases.php" target="_blank">Firewall Alias</a>&emsp;'
		. '<a href="/firewall_rules.php" target="_blank">Firewall Rules</a>&emsp;'
		. '<a href="/status_logs_filter.php" target="_blank">Firewall Logs</a></small>'
		. "{$extra_txt}"
	));
	$form->add($section);
}

$section = new Form_Section('Alert Settings', 'alertsettings', COLLAPSIBLE|SEC_CLOSED);
$form->add($section);

// Build 'Alert Settings' group section
$group = new Form_Group('Settings');
$group->add(new Form_Input(
	'pfbdenycnt',
	'Deny',
	'number',
	$pfbdenycnt,
	['min' => 0, 'max' => 1000]
))->setHelp('Deny')->setAttribute('title', 'Enter number of \'Deny\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Input(
	'pfbdnscnt',
	'DNSBL',
	'number',
	$pfbdnscnt,
	['min' => 0, 'max' => 1000]
))->setHelp('DNSBL')->setAttribute('title', 'Enter number of \'DNSBL\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Input(
	'pfbpermitcnt',
	'Permit',
	'number',
	$pfbpermitcnt,
	['min' => 0, 'max' => 1000]
))->setHelp('Permit')->setAttribute('title', 'Enter number of \'Permit\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Input(
	'pfbmatchcnt',
	'Match',
	'number',
	$pfbmatchcnt,
	['min' => 0, 'max' => 1000]
))->setHelp('Match')->setAttribute('title', 'Enter number of \'Match\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Checkbox(
	'alertrefresh',
	'Auto-Refresh',
	NULL,
	($alertrefresh == 'on' ? TRUE : FALSE),
	'on'
))->setHelp('Auto&nbsp;Refresh')->setAttribute('title', 'Select to \'Auto-Refresh\' Alerts page every 60 seconds.');

// Remove 'Save' button when Alert Filtering is enabled to avoid saving incorrect filter entries
if (!$pfb['filterlogentries']) {
	$btn_save = new Form_Button(
		'save',
		'Save Settings',
		NULL,
		'fa-save'
	);
	$btn_save->removeClass('btn-primary')->addClass('btn-primary btn-xs');
	$group->add(new Form_StaticText(
		NULL,
		$btn_save
	));
}
$section->add($group);

if ($pfb['dnsbl'] == 'on') {
	$group = new Form_Group(NULL);
	$group->add(new Form_Select(
		'pfbpageload',
		'Default page',
		$pfbpageload,
		[	'default'		=> 'Alerts Tab',
			'ip_block_stat'		=> 'IP Block Stats',
			'ip_permit_stat'	=> 'IP Permit Stats',
			'ip_match_stat'		=> 'IP Match Stats',
			'dnsbl_stat'		=> 'DNSBL Stats' ]
	))->setHelp('Select the initial page to load')->setAttribute('style', 'width: auto');

	$group->add(new Form_Input(
		'pfbfilterlimitentries',
		'Filter Limit Entries',
		'number',
		$pfbfilterlimitentries,
		['min' => 0, 'max' => 1000]
	))->setHelp('Filter Limit Entries')
	  ->setAttribute('style', 'width: auto')
	  ->setAttribute('title', 'Enter number of \'Filter Limit Entries\' to view. Set to \'0\' to disable');

	$group->add(new Form_Select(
		'pfbextdns',
		'DNS lookup',
		$pfb['extdns'],
		[	'8.8.4.4'		=> 'Google 8.8.4.4',
			'8.8.8.8'		=> 'Google 8.8.8.8',
			'208.67.222.220'	=> 'OpenDNS 208.67.222.220',
			'208.67.222.222'	=> 'OpenDNS 208.67.222.222',
			'84.200.69.80'		=> 'DNS Watch 84.200.69.80',
			'84.200.70.40'		=> 'DNS Watch 84.200.70.40',
			'37.235.1.174'		=> 'FreeDNS 37.235.1.174',
			'37.235.1.177'		=> 'FreeDNS 37.235.1.177',
			'91.239.100.100'	=> 'UncensoredDNS 91.239.100.100',
			'89.233.43.71'		=> 'UncensoredDNS 89.233.43.71',
			'9.9.9.9'		=> 'Quad9 9.9.9.9',
			'149.112.112.112'	=> 'Quad9 149.112.112.112',
			'1.1.1.1'		=> 'Cloudflare 1.1.1.1',
			'1.0.0.1'		=> 'Cloudflare 1.0.0.1'
		]
	))->setHelp('Select the DNS server for the DNSBL Whitelist CNAME lookup')
	  ->setAttribute('style', 'width: auto');
	$section->add($group);

	$group = new Form_Group('Alert Statistics');
	$group->add(new Form_Select(
		'pfbblockstat',
		'Disabled IP Block Stats',
		$pfbblockstat,
		[	'srcipin' => 'Top SRC IP Inbound', 'srcipout' => 'Top SRC IP Outbound',
			'dstipin' => 'Top DST IP Inbound', 'dstipout' => 'Top DST IP Outbound',
			'srcport' => 'Top SRC Port', 'dstport' => 'Top DST Port',
			'geoip' => 'Top GeoIP', 'aliasname' => 'Top Aliasname',
			'feed' => 'Top Feed', 'interface' => 'Top Interface',
			'protocol' => 'Top Protocol', 'direction' => 'Top Direction', 'date' => 'Historical Summary' ],
		TRUE
	))->setHelp("Select the <strong>IP Block</strong> Stat table(s) to hide")
	  ->setAttribute('style', 'width: auto; overflow: hidden;')
	  ->setAttribute('size', 11);

	$group->add(new Form_Select(
		'pfbpermitstat',
		'Disabled IP Permit Stats',
		$pfbpermitstat,
		[	'srcipin' => 'Top SRC IP Inbound', 'srcipout' => 'Top SRC IP Outbound',
			'dstipin' => 'Top DST IP Inbound', 'dstipout' => 'Top DST IP Outbound',
			'srcport' => 'Top SRC Port', 'dstport' => 'Top DST Port',
			'geoip' => 'Top GeoIP', 'aliasname' => 'Top Aliasname',
			'feed' => 'Top Feed', 'interface' => 'Top Interface',
			'protocol' => 'Top Protocol', 'direction' => 'Top Direction', 'date' => 'Historical Summary' ],
		TRUE
	))->setHelp("Select the <strong>IP Permit</strong> Stat table(s) to hide")
	  ->setAttribute('style', 'width: auto; overflow: hidden;')
	  ->setAttribute('size', 11);

	$group->add(new Form_Select(
		'pfbmatchstat',
		'Disabled IP Match Stats',
		$pfbmatchstat,
		[	'srcipin' => 'Top SRC IP Inbound', 'srcipout' => 'Top SRC IP Outbound',
			'dstipin' => 'Top DST IP Inbound', 'dstipout' => 'Top DST IP Outbound',
			'srcport' => 'Top SRC Port', 'dstport' => 'Top DST Port',
			'geoip' => 'Top GeoIP', 'aliasname' => 'Top Aliasname',
			'feed' => 'Top Feed', 'interface' => 'Top Interface',
			'protocol' => 'Top Protocol', 'direction' => 'Top Direction', 'date' => 'Historical Summary' ],
		TRUE
	))->setHelp("Select the <strong>Match Stat</strong> table(s) to hide")
	  ->setAttribute('style', 'width: auto; overflow: hidden;')
	  ->setAttribute('size', 11);

	$group->add(new Form_Select(
		'pfbdnsblstat',
		'Disabled DNSBL Stats',
		$pfbdnsblstat,
		[	'domain' => 'Top Blocked Domain', 'evald' => 'Top Blocked Eval\'d', 'grouptotal' => 'Top Group Count',
			'groupblock' => 'Top Blocked Group', 'dfeed' => 'Top Blocked Feed', 'ip' => 'Top Source IP', 'agent' => 'Top User-Agent',
			'tld' => 'Top TLD', 'webtype' => 'Top Webpage Types', 'dnsbltype' => 'Top DNSBL Types',
			'datehr' => 'Top Date/Hr', 'datehrmin' => 'Top Date/Hr/Min', 'date' => 'Historical Summary' ],
		TRUE
	))->setHelp("Select the <strong>DNSBL Stat</strong> table(s) to hide")
	  ->setAttribute('style', 'width: auto; overflow: hidden;')
	  ->setAttribute('size', 13);
	$section->add($group);
}

if (!$alert_summary) {

	// Build 'Alert Filter' group section
	$filterstatus = SEC_CLOSED;
	if ($pfb['filterlogentries']) {
		$filterstatus = SEC_OPEN;
	}
	$section = new Form_Section('Alert Filter', 'alertfilter', COLLAPSIBLE|$filterstatus);
	$form->add($section);

	$group = new Form_Group(NULL);
	$group->add(new Form_Input(
		'filterlogentries_date',
		'Date',
		'text',
		$filterfieldsarray[99]
	))->setAttribute('title', 'Enter filter \'Date\'.');

	$group->add(new Form_Input(
		'filterlogentries_srcip',
		'Source IP Address',
		'text',
		$filterfieldsarray[7]
	))->setAttribute('title', 'Enter filter \'Source IP Address\'.');

	$group->add(new Form_Input(
		'filterlogentries_srcport',
		'Source:Port',
		'text',
		$filterfieldsarray[9]
	))->setAttribute('title', 'Enter filter \'Source:Port\'.');

	$group->add(new Form_Input(
		'filterlogentries_int',
		'Interface',
		'text',
		$filterfieldsarray[2]
	))->setAttribute('title', 'Enter filter \'Interface\'.');
	$section->add($group);

	$group = new Form_Group(NULL);
	$group->add(new Form_Input(
		'filterlogentries_rule',
		'Rule Number Only',
		'text',
		$filterfieldsarray[0]
	))->setAttribute('title', 'Enter filter \'Rule Number\' only.');

	$group->add(new Form_Input(
		'filterlogentries_dstip',
		'Dest. IP/Domain Name',
		'text',
		$filterfieldsarray[8]
	))->setAttribute('title', 'Enter filter \'Destination IP Address/Domain Name\'.');

	$group->add(new Form_Input(
		'filterlogentries_dstport',
		'Destination:Port',
		'text',
		$filterfieldsarray[10]
	))->setAttribute('title', 'Enter filter \'Destination:Port\'.');

	$group->add(new Form_Input(
		'filterlogentries_proto',
		'Protocol',
		'text',
		$filterfieldsarray[6]
	))->setAttribute('title', 'Enter filter \'Protocol\'.');
	$section->add($group);

	$group = new Form_Group(NULL);
	$group->add(new Form_Input(
		'filterlogentries_alias',
		'Aliasname',
		'text',
		$filterfieldsarray[13]
	))->setAttribute('title', 'Enter filter \'Aliasname\'.');

	$group->add(new Form_Input(
		'filterlogentries_feed',
		'Feed',
		'text',
		$filterfieldsarray[15]
	))->setAttribute('title', 'Enter filter \'Feed name\'.');

	$group->add(new Form_Input(
		'filterlogentries_geoip',
		'GeoIP',
		'text',
		$filterfieldsarray[12]
	))->setAttribute('title', 'Enter filter \'GeoIP\'.')
	  ->setwidth(2);
	$section->add($group);

	if ($pfb['dnsbl'] == 'on') {
		$section->addInput(new Form_Input(
			'filterlogentries_dnsbl',
			'',
			'text',
			$filterfieldsarray[90],
			['placeholder' => 'DNSBL User-Agent/URI']
		))->setAttribute('title', 'Enter filter \'DNSBL User-Agent/URI\'.');
	}

	$group = new Form_Group(NULL);
	$btnsubmit = new Form_Button(
		'filterlogentries_submit',
		gettext('Apply Filter'),
		NULL,
		'fa-filter'
	);
	$btnsubmit->removeClass('btn-primary')->addClass('btn-primary btn-xs');

	$btnclear = new Form_Button(
		'filterlogentries_clear',
		gettext('Clear Filter'),
		NULL,
		'fa-filter fa-rotate-180'
	);
	$btnclear->removeClass('btn-primary')->addClass('btn-danger btn-xs');

	$group->add(new Form_StaticText(
		'',
		$btnsubmit . $btnclear
		. '&emsp;<div class="infoblock">'
		. '<h6>Regex Style Matching Only! <a href="https://regexr.com/" target="_blank">Regular Expression Help link</a>. '
		. 'Precede with exclamation (!) as first character to exclude match.)</h6>'
		. '<h6>Example: ( ^80$ - Match Port 80, ^80$|^8080$ - Match both port 80 & 8080 )</h6>'
		. '</div>'
	));

	if ($pfb['filterlogentries']) {
		$group->add(new Form_StaticText(
			'',
			'( Save disabled during <strong>Apply Filter</strong>)'
		));
	}
	$section->add($group);

	$form->addGlobal(new Form_Input('domain', 'domain', 'hidden', ''));
	$form->addGlobal(new Form_Input('table', 'table', 'hidden', ''));
	$form->addGlobal(new Form_Input('descr', 'descr', 'hidden', ''));
	$form->addGlobal(new Form_Input('cidr', 'cidr', 'hidden', ''));
	$form->addGlobal(new Form_Input('ip', 'ip', 'hidden', ''));
	$form->addGlobal(new Form_Input('addsuppress', 'addsuppress', 'hidden', ''));
	$form->addGlobal(new Form_Input('addwhitelistdom', 'addwhitelistdom', 'hidden', ''));
	$form->addGlobal(new Form_Input('entry_delete', 'entry_delete', 'hidden', ''));
	$form->addGlobal(new Form_Input('dnsbl_wildcard', 'dnsbl_wildcard', 'hidden', ''));
	$form->addGlobal(new Form_Input('dnsbl_exclude', 'dnsbl_exclude', 'hidden', ''));
	$form->addGlobal(new Form_Input('dnsbl_remove', 'dnsbl_remove', 'hidden', ''));
	$form->addGlobal(new Form_Input('dnsbl_type', 'dnsbl_type', 'hidden', ''));
	$form->addGlobal(new Form_Input('ip_remove', 'ip_remove', 'hidden', ''));
	$form->addGlobal(new Form_Input('ip_white', 'ip_white', 'hidden', ''));
}
print($form);

if (!$alert_summary):

	// Print Unlocked IPs and Domain table
	if (!empty($ip_unlock) || !empty($dnsbl_unlock)): ?>

<div class="panel panel-default" style="display: inline-block; width: 100%;">
	<div class="panel-heading">
		<h2 class="panel-title">&nbsp;<?=gettext('Unlocked IP(s) & Domain(s)')?></h2>
	</div>

	<?php
		$height = min( max( array( max(count($ip_unlock), 1), max(count($dnsbl_unlock), 1))) * 63, 200);
		foreach (array( array($ip_unlock, 'IP', 'Table'),
				array($dnsbl_unlock, 'Domain', 'Type')) as $key => $data):

			$float = $key %2 ? 'right' : 'left';
	?>

	<div style="float: <?=$float;?>; width: 50%; height: <?=$height;?>px; overflow-y: scroll;">
		<div class="panel-body">
			<table class="table table-striped table-hover table-compact sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Unlocked {$data[1]}(s)")?></th>
					<th><?=gettext("{$data[2]}")?></th>
				</tr>
			</thead>
			<tbody>
	<?php
			foreach ($data[0] as $entry => $type) {
				if ($key == 0) {
					$unlock = '<i class="fa fa-unlock icon-primary text-primary" id="IPLCK|' . $entry . '|' . $type
							. '" title="Re-Lock ' . $data[1] . ': [ ' . $entry . ' ] back into Aliastable [ ' . $type . ' ]? "></i>';

					$alert = '<a class="fa fa-info icon-pointer icon-primary" target="_blank"'
							. ' href="/pfblockerng/pfblockerng_threats.php?host='
							. $entry . '" title="Click for Threat source IP Lookup for [ ' . $entry . ' ]"></a>';
				} else {
					$unlock = '<i class="fa fa-unlock icon-primary text-primary" id="DNSBL_LCK|' . $entry . '|' . $type
							. '" title="Re-Lock ' . $data[1] . ': [ ' . $entry . ' ] back into DNSBL? "></i>';

					$alert = '<a class="fa fa-info icon-pointer icon-primary" target="_blank"'
							. ' href="/pfblockerng/pfblockerng_threats.php?domain='
							. $entry . '" title="Click for Threat source Domain Lookup for [ ' . $entry . ' ]"></a>';
				}

				print ("<tr><td>&nbsp;{$alert}&emsp;{$unlock}&emsp;{$entry}</td><td>{$type}</td></tr>");
			}
	?>
			</tbody>
			</table>
		</div>
	</div>
		<?php endforeach; ?>
</div>
	<?php
	endif; // End Print Unlocked IPs and Domain table

	$skipcount = $counter = 0;
	// Create three output windows 'Deny', 'DNSBL', 'Permit' and 'Match'
	foreach (array (	'Deny'		=> "{$pfb['ip_blocklog']}",
				'DNSBL'		=> "{$pfb['dnslog']}",
				'Permit'	=> "{$pfb['ip_permitlog']}",
				'Match'		=> "{$pfb['ip_matchlog']}" ) as $type => $pfb_log ):

		switch($type) {
			case 'Deny':
				$rtype = 'block';
				$pfbentries = "{$pfbdenycnt}";
				break;
			case 'Permit':
				$rtype = 'pass';
				$pfbentries = "{$pfbpermitcnt}";
				break;
			case 'Match':
				$rtype = 'match';
				$pfbentries = "{$pfbmatchcnt}";
				break;
			case 'DNSBL':
				$pfbentries = "{$pfbdnscnt}";
		}

		// Skip table output if $pfbentries is zero.
		if ($pfbentries == 0 && $skipcount != 3) {
			$skipcount++;
			continue;
		}

		if ($pfb['filterlogentries']) {
			$pfbentries = $pfbfilterlimitentries;
		}

		$pfbfilterlimit = FALSE;
		?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">&nbsp;<?=gettext($type)?>
			<small>-&nbsp;<?=gettext('Last')?>&nbsp;<?=$pfbentries?>&nbsp;<?=gettext('Alert Entries')?></small>
		</h2>
	</div>
	<div class="panel-body">
		<div class="table-responsive">
		<table class="table table-striped table-hover table-compact sortable-theme-bootstrap" data-sortable>

<?php
// Process dns array for DNSBL and generate output
if ($pfb['dnsbl'] == 'on' && $type == 'DNSBL') {
?>
			<thead>
				<tr>
					<th><?=gettext("Date")?></th>
					<th><?=gettext("IF")?></th>
					<th><?=gettext("Source")?></th>
					<th><!----- Buttons -----></th>
					<th><?=gettext("Domain/Referer|URI|Agent")?></th>
					<th><?=gettext("Feed")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	if (file_exists("{$pfb_log}")) {

		// Collect TLD Blacklist and remove any leading/trailing 'dot'
		$tld_blacklist = pfbng_text_area_decode($pfb['dnsblconfig']['tldblacklist'], TRUE, FALSE);
		if (!empty($tld_blacklist)) {
			$tld_blacklist = array_flip($tld_blacklist);
			foreach ($tld_blacklist as $tld => $key) {
				unset($tld_blacklist[$tld]);
				$tld_blacklist[trim($tld, '.')] = '';
			}
		}

		// Determine if DNSBL_TLD file exists
		$dnsbl_tld_txt = FALSE;
		if (file_exists("{$pfb['dnsbl_tld_txt']}")) {
			$dnsbl_tld_txt = TRUE;
		}

		$dup = 0;
		exec("/usr/bin/tail -r {$pfb_log} > {$pfb_log}.rev 2>&1");
		if (($handle = @fopen("{$pfb_log}.rev", 'r')) !== FALSE) {
			while (($fields = @fgetcsv($handle)) !== FALSE) {

				/* dnsbl.log Fields Reference

					[0]	= DNSBL block type - 'DNSBL Reject' or 'DNSBL Reject HTTPS'
					[1]	= Date/Timestamp
					[2]	= Domain name
					[3]	= Source IP
					[4]	= URL/Referer/URI/Agent String
					[5]	= DNSBL Type (DNSBL/TLD/DNSBL TLD)
					[6]	= Group Name
					[7]	= Evaluated Domain/TLD
					[8]	= Feed Name
					[9]	= Duplicate ID indicator / Count

				pfbalertdnsbl fields array reference (Used for filter functionality)

					[2]	= Interface
					[7]	= SRC IP address
					[8]	= Domain name
					[13]	= Group Name
					[15]	= Feed Name
					[90]	= Referer/URI/Agent String
					[99]	= Date/Timestamp		*/

				// Remove and record duplicate entries
				if ($fields[9] == '-') {
					$dup++;
					continue;
				}

				// Remove 'Unknown' for Agent field
				if ($fields[4] == 'Unknown') {
					$fields[4] = '';
				}

				// Add Blocked webpage type to Agent/URI String
				$fields[4] = "{$fields[0]} | {$fields[4]}";

				$pfbalertdnsbl = array();

				// Determine interface name based on Source IP address
				$pfbalertdnsbl[2] = 'LAN';		// Define LAN Interface as 'default'
				if (!empty($dnsbl_int)) {
					foreach ($dnsbl_int as $subnet) {
						if (strpos($fields[3], 'Unknown') !== FALSE) {
							$pfbalertdnsbl[2] = 'Unknown';
							break;
						} elseif (ip_in_subnet($fields[3], $subnet[0])) {
							$pfbalertdnsbl[2] = "{$subnet[1]}";
							break;
						}
					}
				}

				// SRC IP Address and Hostname
				if (isset($local_hosts[$fields[3]])) {
					$pfbalertdnsbl[7] = "{$fields[3]}<br /><small>{$local_hosts[$fields[3]]}</small>";
				} else {
					$pfbalertdnsbl[7] = $fields[3];
				}

				$pfbalertdnsbl[8]	= $fields[2];	// Blocked Domain

				// Remove any ':' Port addresses
				if (strpos($pfbalertdnsbl[8], ':') !== FALSE) {
					$pfbalertdnsbl[8] = strstr($pfbalertdnsbl[8], ':', TRUE);
				}

				$pfbalertdnsbl[13]	= $fields[6];
				$pfbalertdnsbl[15]	= $fields[8];
				$pfbalertdnsbl[90]	= $fields[4];	// DNSBL URL
				$pfbalertdnsbl[99]	= $fields[1];	// Timestamp

				// Add 'https' icon to Domains as required.
				$pfb_https = '';
				if ($fields[0] == 'DNSBL-HTTPS') {
					$pfb_https = '&nbsp;<i class="fa fa-key icon-pointer"
							title="Note: HTTPS - URL/URI/User Agent strings are not collected at this time!"></i>';
				}

				// If alerts filtering is selected, process filters as required.
				if ($pfb['filterlogentries']) {
					if (!pfb_match_filter_field($pfbalertdnsbl, $filterfieldsarray)) {
						continue;
					}
					if ($pfbfilterlimitentries != 0 && $counter >= $pfbfilterlimitentries) {
						$pfbfilterlimit = TRUE;
						break;
					}
				} else {
					if ($counter >= $pfbentries) {
						break;
					}
				}
				$counter++;

				// Select blocked Domain or Evaluated Domain
				$qdomain = $fields[2];
				if ($fields[5] == 'TLD') {
					$qdomain = $fields[7];
				}

				// Determine if blocked Domain is now in a TLD Exclusion
				$pfb_exclusion = FALSE;
				if (isset($clists['tldexclusion']['data'][$fields[7]])) {
					$wt_line = rtrim($clists['tldexclusion']['data'][$fields[7]], "\x00..\x1F");
					$pfb_exclusion = TRUE;
				}

				// Determine if blocked Domain still exists
				$pfb_query = '';
				if ($fields[6] != 'Unknown') {

					if ($fields[5] != 'DNSBL_TLD') {
						$pfb_query = pfb_parse_line(exec("{$pfb['grep']} -sHm1 '\"{$qdomain} 60' {$pfb['dnsdir']}/* 2>&1"));
					}
					elseif ($dnsbl_tld_txt) {
						exec("/usr/bin/grep -l '^{$fields[7]}$' {$pfb['dnsbl_tld_txt']} 2>&1", $match);
						if (!empty($match[0])) {
							$pfb_query = 'DNSBL_TLD';
						}
					}
				}
				$pfb_matchtitle	= 'The DNSBL Feed and Group that blocked the indicated Domain.';

				// Truncate long list names
				if (strlen($fields[8]) >= 17 || strlen($fields[6]) >= 25) {
					$pfb_matchtitle = "Feed: {$fields[8]} | Alias: {$fields[6]}";
					$fields[8]	= substr($fields[8], 0, 16) . '...';
					$fields[6]	= substr($fields[6], 0, 24) . '...';
				}

				// Threat Lookup Icon
				$alert_dom = '';
				if ($fields[6] != 'Unknown') {
					$alert_dom = '<a class="fa fa-info icon-pointer icon-primary" title="Click for Threat Domain Lookup." target="_blank" ' .
							'href="/pfblockerng/pfblockerng_threats.php?domain=' . $qdomain . '"></a>';
				}

				if (!$pfb_exclusion) {

					$supp_dom_txt = '';
					if ($fields[5] == 'TLD' && !$pfb_exclusion) {
						$supp_dom_txt  = "Note:&emsp;The following Domain was blocked via TLD.\n\n"
								. "Blocked Domain:&emsp;&emsp;[ {$fields[2]} ]\n"
								. "Evaluated Domain:&emsp;&nbsp;[ {$fields[7]} ]\n\n"
								. "DNSBL Groupname:&emsp;[ {$fields[6]} ]\n"
								. "DNSBL Feedname:&emsp;&nbsp;&nbsp;[ {$fields[8]} ]\n\n"

								. "Whitelisting Options:\n\n"
								. "1) Wildcard whitelist [ .{$fields[7]} ]\n"
								. "&emsp;This will immediately remove the blocked Domain/CNAMES from DNSBL.\n"
								. "&emsp;(CNAMES: Define the external DNS server in Alert settings.)\n\n"
								. "2) Add [ {$fields[7]} ] to the 'TLD Exclusion customlist'\n"
								. "&emsp;A Force Reload-DNSBL is Required!\n"
								. "&emsp;After a Reload any new blocked Domains can be Whitelisted at that time.";
					}
					else {
						$supp_dom_txt = "Whitelist [ {$fields[2]} ]\n\n"
								. "Note:&emsp;This will immediately remove the blocked Domain\n"
								. "&emsp;&emsp;&emsp;&nbsp;and associated CNAMES from DNSBL.\n"
								. "&emsp;&emsp;&emsp;&nbsp;(CNAMES: Define the external DNS server in Alert settings.)\n\n"
								. "Whitelisting Options:\n\n"
								. "1) Wildcard whitelist [ .{$fields[2]} ]\n"
								. "2) Whitelist only [ {$fields[2]} ]\n";
					}

					// Determine if Domain is blocked via TLD Blacklist
					$pfb_found = FALSE;
					if ($fields[5] != 'DNSBL_TLD') {

						// Remove Whitelist Icon for 'Unknown'
						if ($fields[6] == 'Unknown') {
							$supp_dom = '';
						}
						else {
							// Default - Domain not in Whitelist
							$supp_dom = '<i class="fa fa-plus icon-pointer icon-primary" id="DNSBLWT|' . 'add|'
									. $fields[7] . '|' . $fields[8] . '" title="' . $supp_dom_txt . '"></i>';

							// Determine if Alerted Domain is in DNSBL Whitelist
							if (isset($clists['dnsblwhitelist']['data'][$fields[2]])) {
								$w_line = rtrim($clists['dnsblwhitelist']['data'][$fields[2]], "\x00..\x1F");

								$pfb_found = TRUE;
								if (!empty($pfb_query)) {
									$supp_dom_txt = "Note:&emsp;The following Domain exists in the DNSBL Whitelist:\n\n"
											. "Whitelisted:&emsp;[ {$w_line} ]\n\n"
											. "To remove this Domain from the DNSBL Whitelist, press 'OK'";
								} else {
									$supp_dom_txt = "Note:&emsp;The following Domain exists in the DNSBL Whitelist:\n\n"
											. "Whitelisted:&emsp;[ {$w_line} ]\n\n"
											. "However it is still being Wildcard blocked by the following Domain:\n"
											. "Whitelisted:&emsp;[ {$qdomain} ]\n\n"
											. "To remove this Domain [ {$fields[2]} ] from the DNSBL Whitelist"
											. ", Click 'OK'";
								}
								$supp_dom = '<i class="fa fa-trash no-confirm icon-pointer icon-primary" id="DNSBLWT|'
										. 'delete_domain|' . $fields[2] . '" title="' . $supp_dom_txt . '"></i>';
							}

							// Determine if Alerted Domain is in DNSBL Whitelist (prefixed by a "dot" )
							elseif (!empty($clists['dnsblwhitelist']['data'])) {

								$q_wdomain = ltrim($fields[7], '.');
								$dparts	= explode('.', $q_wdomain);
								$dcnt	= count($dparts);
								for ($i=$dcnt; $i > 0; $i--) {

									$d_query = implode('.', array_slice($dparts, -$i, $i, TRUE));
									if (isset($clists['dnsblwhitelist']['data']['.' . $d_query])) {
										$w_line = rtrim($clists['dnsblwhitelist']['data']['.' . $d_query], "\x00..\x1F");

										$pfb_found = TRUE;
										if (!empty($pfb_query)) {
											$supp_dom_txt = "Note:&emsp;The following Domain exists"
													. " in the DNSBL Whitelist:\n\n"
													. "Whitelisted:&emsp;[ {$w_line} ]\n\n"
													. "To remove this Domain from the DNSBL Whitelist,"
													. " press 'OK'";
										} else {
											$supp_dom_txt = "Note:&emsp;The following Domain exists in the"
													. " DNSBL Whitelist:\n\n"
													. "Whitelisted:&emsp;[ {$w_line} ]\n\n"
													. "However it is still being Wildcard blocked"
													. " by the following Domain:\n"
													. "Whitelisted:&emsp;[ {$qdomain} ]\n\n"
													. "To remove this Domain [ {$d_query} ]"
													. "from the DNSBL Whitelist, Click 'OK'";
										}
										$supp_dom = '<i class="fa fa-trash no-confirm icon-pointer icon-primary"'
													. ' id="DNSBLWT|' . "delete_domainwildcard|" . $d_query
													. '" title="' . $supp_dom_txt . '"></i>';
										break;
									}
								}
							}

							// Root Domain blocking all Sub-Domains and is not in whitelist and not in TLD Exclusion
							if ($fields[5] != 'DNSBL' && !$pfb_found && !$pfb_exclusion) {
								$supp_dom = '<i class="fa fa-plus-circle icon-pointer icon-primary" id="DNSBLWT|' . 'add|'
									. $fields[7] . '|' . $fields[8] . '|' . $fields[5]
									. '" title="' . $supp_dom_txt . '"></i>';
							}
						}
					}

					// Whole TLD is blocked
					else {
						$supp_dom_txt  = "Note:&emsp;The following Domain was blocked via DNSBL TLD:\n\n"
								. "Blocked Domain:&emsp;&emsp;[ {$fields[2]} ]\n"
								. "Evaluated Domain:&emsp;&nbsp;[ {$fields[7]} ]\n\n"
								. "Add [ {$fields[2]} ] to the TLD Whitelist?";

						$supp_dom = '<i class="fa fa-hand-stop-o icon-pointer icon-primary" id="DNSBLWT|' . 'tld|'
								. $fields[2] . '|' . $fields[7] . '" title="' . $supp_dom_txt . '"></i>';

					}
				}
				else {
					$supp_dom_txt  = "Note:&emsp;The following Domain is in the TLD Exclusion customlist:\n\n"
							. "TLD Exclusion:&emsp;[ {$wt_line} ]\n\n"
							. "&#8226; TLD Exclusions require a Force Reload when a Domain is initially added.\n"
							. "&#8226; To remove this Domain from the TLD Exclusion customlist, Click 'OK'";

					$supp_dom = '<i class="fa fa-trash-o no-confirm icon-pointer icon-primary" id="DNSBLWT|'
							. 'delete_exclusion|' . $fields[7] . '" title="' . $supp_dom_txt . '"></i>';
				}

				// Lock/Unlock Domain Icon
				$unlock_dom = '&nbsp;&nbsp;&nbsp;';
				if ($fields[6] != 'Unknown' && !empty($pfb_query)) {

					if ($fields[5] != 'DNSBL_TLD') {

						$tnote = "\n\nNote:&emsp;&#8226; Unlocking Domain(s) is temporary and may be automatically\n"
							. "&emsp;&emsp;&emsp;&emsp;re-locked on a Cron or Force command with an Unbound Reload!\n"
							. "&emsp;&emsp;&emsp;&nbsp;&#8226; Review Threat Source ( i ) Icon for Domain details.\n"
							. "&emsp;&emsp;&emsp;&nbsp;&#8226; Clear your Browser and OS cache after each Lock/Unlock!";

						if (!isset($dnsbl_unlock[$qdomain])) {

							if ($pfb_found) {
								$supp_dom_txt = "\n\nNote:&emsp;The following Domain exists in the DNSBL Whitelist:\n\n"
										. "Whitelisted:&emsp;[ {$w_line} ]\n\n"
										. "This Domain can be Temporarily Relocked into DNSBL\n"
										. "by selecting the Unlock Icon!";

								$unlock_dom = '<i class="fa fa-unlock icon-primary text-warning" id="DNSBL_RELCK|'
										. $qdomain . '|' . $fields[5] . '" title="' . $supp_dom_txt . '"></i>';
							}
							else {
								$unlock_dom = '<i class="fa fa-lock icon-primary text-danger" id="DNSBL_ULCK|'
										. $qdomain . '|' . $fields[5]
										. '" title="Unlock Domain: [ ' . $qdomain . '] from DNSBL?' . $tnote . '" ></i>';
							}
						} else {
							if ($pfb_found) {
								$supp_dom_txt = "\n\nNote:&emsp;The following Domain exists in the DNSBL Whitelist:\n\n"
										. "Whitelisted:&emsp;[ {$w_line} ]\n\n"
										. "Unlock this Domain by selecting the Unlock Icon!";

								$unlock_dom = '<i class="fa fa-lock icon-primary text-warning" id="DNSBL_REULCK|'
										. $qdomain . '|' . $fields[5] . '" title="' . $supp_dom_txt . '"></i>';
							}
							else {
								$unlock_dom = '<i class="fa fa-unlock icon-primary text-primary" id="DNSBL_LCK|'
										. $qdomain . '|' . $fields[5] . '" title="Re-Lock Domain: ['
										. $qdomain . ' ] back into DNSBL?' . $tnote . '" ></i>';
							}
						}
					}
				}

				// Add strike html tag if Domain is not listed anymore
				$strike1 = $strike2 = '';
				if ($fields[6] != 'Unknown' && empty($pfb_query)) {
					$strike1 = '<s>';
					$strike2 = '</s>';
					$pfb_matchtitle = 'The domain is not currently listed in DNSBL!';
				}

				// Truncate long URLs
				$url_title = '';
				if (strlen($pfbalertdnsbl[90]) >= 72) {
					$url_title = "{$pfbalertdnsbl[90]}";
					$pfbalertdnsbl[90] = substr(str_replace(array('?', '-'), '', $pfbalertdnsbl[90]), 0, 69) . '...';
				}

				$dup_cnt = '';
				if ($dup != 0) {
					$dup_cnt = "<span title=\"Total additional duplicate event count(s) [ {$dup} ]\"> [{$dup}]</span>";
					$dup = 0;
				}

				print ("<tr>
					<td>{$pfbalertdnsbl[99]}{$dup_cnt}</td>
					<td><small>{$pfbalertdnsbl[2]}</small></td>
					<td>{$pfbalertdnsbl[7]}</td>
					<td>{$unlock_dom}&nbsp;{$alert_dom}&nbsp;{$supp_dom}</td>
					<td  title=\"{$url_title}\">{$pfbalertdnsbl[8]}<small>&emsp;[ {$fields[5]} ]</small> {$pfb_https}
						<br />&nbsp;&nbsp;<small>{$pfbalertdnsbl[90]}</small></td>
					<td title=\"{$pfb_matchtitle}\">{$strike1}{$fields[8]}{$strike2}
						<br /><small>{$strike1}{$fields[6]}{$strike2}</small></td></tr>");
			}
		}
	}
	@fclose($handle);
	unlink_if_exists("{$pfb_log}.rev");
}

if ($type != 'DNSBL') {
?>
			<thead>
				<tr>
					<th><?=gettext("Date")?></th>
					<th><?=gettext("IF")?></th>
					<th><?=gettext("Rule")?></th>
					<th><?=gettext("Proto")?></th>
					<th><!----- Buttons -----></th>
					<th><?=gettext("Source")?></th>
					<th><!----- Buttons -----></th>
					<th><?=gettext("Destination")?></th>
					<th><?=gettext("GeoIP")?></th>
					<th><?=gettext("Feed")?></th>
				</tr>
			</thead>
			<tbody>
<?php
}

// Process Deny/Permit/Match and generate output
if ($type != 'DNSBL' && file_exists("{$pfb_log}")) {

	$dup = 0;
	exec("/usr/bin/tail -r {$pfb_log} > {$pfb_log}.rev 2>&1");
	if (($handle = @fopen("{$pfb_log}.rev", 'r')) !== FALSE) {
		while (($fields = @fgetcsv($handle)) !== FALSE) {

			$last_fld = array_pop($fields);

			// Remove and record duplicate entries
			if ($last_fld == '-') {
				$dup++;
				continue;
			}

			$alert_ip = $pfb_query = $pfb_matchtitle = '';
			$src_icons = $dst_icons = $strike1 = $strike2 = $alias_new = $eval_new = '';

			// Reorder timestamp field for Filter fields functionality
			$fields[99] = array_shift($fields);

			/* Fields Reference

				(Removed and re-ordered)
				[0]	=> [99] = Date/TimestamP
				[18]	 	= Duplicate ID indicator / Count

				(Final $fields array reference)
				[0]	= Rulenum
				[1]	= Real Interface
				[2]	= Friendly Interface name
				[3]	= Action
				[4]	= Version
				[5]	= Protocol ID
				[6]	= Protocol
				[7]	= SRC IP
				[8]	= DST IP
				[9]	= SRC Port
				[10]	= DST Port
				[11]	= Direction
				[12]	= GeoIP code
				[13]	= IP Alias Name
				[14]	= IP evaluated
				[15]	= Feed Name
				[16]	= gethostbyaddr resolved hostname
				[17]	= Client Hostname			*/

			// If alerts filtering is selected, process filters as required.
			if ($pfb['filterlogentries']) {
				if (!pfb_match_filter_field($fields, $filterfieldsarray)) {
					$dup = 0;
					continue;
				}
				if ($pfbfilterlimitentries != 0 && $counter >= $pfbfilterlimitentries) {
					$pfbfilterlimit = TRUE;
					break;
				}
			}
			else {
				if ($counter >= $pfbentries) {
					break;
				}
			}
			$counter++;

			// Cleanup port output
			if ($fields[6] == 'ICMP' || $fields[6] == 'ICMPV6') {
				$srcport = '';
			} else {
				$srcport = ":{$fields[9]}";
				$dstport = ":{$fields[10]}";
			}

			// IPv4 or IPv6 event
			$pfb_ipv4 = FALSE;
			$vtype = 6;
			if ($fields[4] == 4) {
				$pfb_ipv4 = TRUE;
				$vtype = 4;
			}

			// GeoIP event
			$pfb_geoip = FALSE;
			if (isset($continents[ substr($fields[13], 0, -3) ])) {
				$pfb_geoip = TRUE;
			}

			// Determine if Proofpoint IQRisk based Feed (Based on ':' in Feedname - Defines Feed Category)
			$et_header = FALSE;
			if (strpos($fields[15], ':') !== FALSE) {
				$data = explode(':', $fields[15]);
				$fields[15]	= $data[1];
				$et_header	= TRUE;
			}

			// Inbound event
			if ($fields[11] == 'in') {
				$host		= $fields[7];
				$hostname	= array( 'src' => $fields[16], 'dst' => $fields[17] );
			}

			// Outbound event
			else {
				$host		= $fields[8];
				$hostname	= array( 'src' => $fields[17], 'dst' => $fields[16] );
			}

			// Determine folder type
			$query = "| {$pfb['grep']} {$fields[15]}.txt";
			if ($pfb_geoip) {
				$folder = "{$pfb['aliasdir']}/pfB_*.txt";
			} elseif ($et_header) {
				$folder = "{$pfb['etdir']}/*.txt";
				$query = '';
			} elseif ($fields[3] == 'block') {
				$folder = "{$pfb['denydir']}/*.txt {$pfb['nativedir']}/*.txt";
			} elseif ($fields[3] == 'pass') {
				$folder = "{$pfb['permitdir']}/*.txt {$pfb['nativedir']}/*.txt";
			} elseif ($fields[3] == 'match') {
				$folder = "{$pfb['matchdir']}/*.txt {$pfb['nativedir']}/*.txt";
			}

			// IPv4 IP address mask
			$mask = '';
			if ($pfb_ipv4) {
				$mask = strstr($fields[14], '/', FALSE) ?: '/32';
			}

			// Determine if event IP still exists in Feed Aliastable
			$validate = '';
			if ($fields[14] != 'Unknown' && $fields[15] != 'Unknown') {
				$q_ip = str_replace('.', '\.', $fields[14]);
				$validate = exec("/usr/bin/find {$folder} -type f {$query} | xargs {$pfb['grep']} '^{$q_ip}' 2>&1");
			}

			// Determine if a different IP/CIDR is now alerting on this host
			if (empty($validate)) {
				$pfb_query = find_reported_header($host, $folder);

				$strike1 = '<s>';
				$strike2 = '</s><br />';

				if ($pfb_query[0] == 'Unknown') {
					$alias_new	= 'Not listed!';
					$pfb_matchtitle = 'This IP is not currently listed!';
				}
				else {
					if (strlen($pfb_query[0]) >= 17) {
						$alias_new = substr($pfb_query[0], 0, 16) . '...';
					} else {
						$alias_new = $pfb_query[0];
					}
				}

				if ($pfb_query[1] == 'Unknown') {
					$mask		= '';
					$eval_new	= 'Not listed!';
				}
				else {
					if ($pfb_ipv4) {
						$mx	= explode('/', $pfb_query[1]);
						$mask	= empty($mx[1]) ? '/32' : "/{$mx[1]}";
					}
					$eval_new	= $pfb_query[1];
				}
			}

			$mask_suppression	= FALSE;
			$mask_unlock		= FALSE;

			if ($pfb_ipv4) {
				if ($mask == '/32' || $mask == '/24') {
					$mask_suppression = TRUE;
				} elseif (substr($mask, strrpos($mask, '/') + 1) > 24) {
					$mask_unlock = TRUE;
				}
			}

			$table = $fields[13];
			if (!empty($alias_new)) {
				$table = $alias_new;
			}

			$alert_ip = '<a class="fa fa-info icon-pointer icon-primary" target="_blank" href="/pfblockerng/pfblockerng_threats.php?host=' .
					$host . '" title="Click for Threat source IP Lookup for [ ' . $host . ' ]"></a>';

			// Suppression Icon
			$supp_ip = $unlock_ip = '&nbsp;&nbsp;&nbsp;';
			if ($pfb['supp'] == 'on' && $rtype == 'block' && $pfb_ipv4 && !$pfb_geoip && $mask_suppression) {

				$v4suppression32 = FALSE;
				if (isset($clists['ipsuppression']['data'][$host . '/32'])) {
					$w_line = rtrim($clists['ipsuppression']['data'][$host . '/32'], "\x00..\x1F");
					$v4suppression32 = TRUE;
				}

				$v4suppression24 = FALSE;
				$ix = ip_explode($host);
				if (isset($clists['ipsuppression']['data'][$ix[5]])) {
					$w_line = rtrim($clists['ipsuppression']['data'][$ix[5]], "\x00..\x1F");
					$v4suppression24 = TRUE;
				}

				// Host is not in the Suppression List
				if (!$v4suppression32 && !$v4suppression24) {

					// Check if host is in a Permit Whitelist Alias
					if ($clists['ipwhitelist' . $vtype]) {
						$pfb_found = FALSE;
						foreach ($clists['ipwhitelist' . $vtype] as $type => $permit_list) {
							if (isset($permit_list['data'][$host])) {
								$w_line = rtrim($permit_list['data'][$host], "\x00..\x1F");
								$pfb_found = TRUE;
								break;
							}
						}

						// Host found in a Permit Whitelist Alias
						if ($pfb_found) {
							$supp_ip_txt = "Note:&emsp;The following IPv{$vtype} addresss is in a Permit Alias:\n\n"
									. "Permitted IP:&emsp;[ {$w_line} ]\n"
									. "Evaluated IP:&emsp;[ {$fields[14]} ]\n"
									. "IP Aliasname:&emsp;[ {$type} ]\n\n"

									. "To remove this IP from the Whitelist, press 'OK'";

							$supp_ip = '<i class="fa fa-trash no-confirm icon-pointer" id="DNSBLWT|' . 'delete_ipwhitelist|' . $host
									. '|' . $type . '" title="' . $supp_ip_txt . '"></i>';
						}
					}

					// Add Suppression/Whitelist Icon
					if (!$pfb_found) {
						$permit_option = '';
						if ($clists['ipwhitelist' . $vtype]) {
							$permit_option = '|' . implode('|', $clists['ipwhitelist' . $vtype]['options']);
						}

						$supp_ip_txt  = "Note:&emsp;The following IPv{$vtype} was blocked:\n\n"
								. "Blocked IP:&emsp;&emsp;[ {$host} ]\n"
								. "Evaluated IP:&emsp;&nbsp;[ {$fields[14]} ]\n\n"
								. "IP Aliasname:&emsp;[ {$table} ]\n"
								. "IP Feedname:&emsp;&nbsp;[ "
								. (!empty($eval_new) ? $eval_new : $fields[15]) . " ]\n\n"

								. "Whitelisting Options:\n\n"
								. "1) Suppress the IP. This will immediately remove the IP\n"
								. "&emsp;and keep the IP suppressed until its removed from the customlist\n\n"
								. "2) Whitelist the IP to an existing 'Permit' Alias customlist. Ensure that this\n"
								. "&emsp;Permit Alias/Rule is above the Block/Reject rules (Rule Order option)\n\n"
								. "&emsp;If no 'Whitelist' is found, a default 'Whitelist' will be created.\n"
								. "&emsp;A Force Update is required to add the associated Firewall Permit Rule!\n\n"
								. "Click 'OK' to continue";

						$supp_ip = '<i class="fa fa-plus icon-pointer icon-primary" id="PFBIPSUP|' . 'add|' . $host
								. '|' . $table . $permit_option
								. '" title="' . $supp_ip_txt . '"></i>';
					}
				}
				else {
					$supp_ip_txt = "Note:&emsp;The following IPv{$vtype} addresss is in a IP Suppression list:\n\n"
							. "Suppressed IP:&emsp;[ {$w_line} ]\n"
							. "Evaluated IP:&emsp;[ {$fields[14]} ]\n\n"

							. "To remove this IP from the Suppression list, press 'OK'";

					$supp_ip = '<i class="fa fa-trash no-confirm icon-pointer" id="DNSBLWT|' . 'delete_ip|' . $host
							. '|' . $table . '" title="' . $supp_ip_txt . '"></i>&emsp;';
				}
			}

			// Unlock/Lock Icon
			if ($rtype == 'block' && ($mask_suppression || $mask_unlock)) {
				$tnote = "\n\nNote:\n&emsp;&emsp;&#8226; Unlocking IP(s) is temporary and may be automatically\n"
					. "&emsp;&emsp;&emsp;re-locked on a Cron or Force command!\n"
					. "&emsp;&emsp;&#8226; Review Threat Source ( i ) Icons for further IP details.";

				if (!isset($ip_unlock[$fields[14]])) {
					$unlock_ip = '<i class="fa fa-lock icon-primary text-danger" id="IPULCK|' . $fields[14] . '|'  . $table
							. '" title="Unlock IP: [ ' . $fields[14] . ' ] from Aliastable [ ' . $table . ' ]?'
							. $tnote . '" ></i>';
				} else {
					$unlock_ip = '<i class="fa fa-unlock icon-primary text-primary" id="IPLCK|' . $fields[14] . '|' . $table
							. '" title="Re-Lock IP: [ ' . $fields[14] . ' ] back into Aliastable [ ' . $table . ' ]?'
							. $tnote . '" ></i>';
				}
			}

			// IP Whitelist Icon
			if (!$mask_suppression) {
				if ($clists['ipwhitelist' . $vtype]) {

					$pfb_found = FALSE;
					foreach ($clists['ipwhitelist' . $vtype] as $type => $permit_list) {
						if (isset($permit_list['data'][$host])) {
							$w_line = rtrim($permit_list['data'][$host], "\x00..\x1F");
							$pfb_found = TRUE;
							break;
						}
					}

					if ($pfb_found) {
						$supp_ip_txt = "Note:&emsp;The following IPv{$vtype} addresss is in a Permit Alias:\n\n"
								. "Permitted IP:&emsp;[ {$w_line} ]\n"
								. "Evaluated IP:&emsp;[ {$fields[14]} ]\n"
								. "IP Aliasname:&emsp;[ {$type} ]\n\n"

								. "To remove this IP from the Whitelist, press 'OK'";

						$supp_ip = '<i class="fa fa-trash no-confirm icon-pointer" id="DNSBLWT|' . 'delete_ipwhitelist|' . $host
								. '|' . $type . '" title="' . $supp_ip_txt . '"></i>';
					}
					else {
						$supp_ip_txt  = "Note:&emsp;The following IPv{$vtype} was blocked:\n\n"
								. "Blocked IP:&emsp;&emsp;[ {$host} ]\n"
								. "Evaluated IP:&emsp;&nbsp;[ {$fields[14]} ]\n\n"
								. "IP Aliasname:&emsp;[ {$table} ]\n"
								. "IP Feedname:&emsp;&nbsp;[ "
								. (!empty($eval_new) ? $eval_new : $fields[15]) . " ]\n\n"

								. "Whitelisting details:\n\n"
								. "&#8226; To permit access to this Blocked IP, you can add it to any\n"
								. "&emsp;existing 'Permit' Alias.\n\n"
								. "&emsp;If no 'Whitelist' is found, a default 'Whitelist' will be created.\n"
								. "&emsp;A Force Update is required to add the associated Firewall Permit Rule!\n\n"
								. "&#8226; Ensure that this Permit Alias/Rule is above the "
								. "Block/Reject rules\n&emsp;(Rule Order option)\n\n"
								. "Click 'OK' to continue";

						$supp_ip = '<i class="fa fa-plus-circle icon-pointer" id="PFBIPWHITE|' . $host
								. '|' . implode('|', $clists['ipwhitelist' . $vtype]['options'])
								. '" title="' . $supp_ip_txt . '"></i>';
					}
				}
			}

			// Remove Suppression Icon for 'Not Listed' events
			if ($eval_new == 'Not listed!') {
				$supp_ip = '';
			}

			// Threat port lookup
			$query_port = '';
			if ($p_query_port != $fields[10]) {
				$query_port = '<a class="fa fa-search icon-pointer" target="_blank" '
						. 'href="/pfblockerng/pfblockerng_threats.php?port=' . $fields[10]
						. '" title="Click for Threat Port Lookup [ ' . $fields[10] . ' ]"></a>';
			}

			// Inbound event
			$src_icons = $dst_icons = '&emsp;&emsp;&emsp;';
			if ($fields[11] == 'in') {
				if ($rtype == 'block') {
					$src_icons	= "{$unlock_ip}&nbsp;{$alert_ip}&nbsp;{$supp_ip}";
				} elseif ($rtype == 'match') {
					$src_icons	= "{$alert_ip}&nbsp;";
				}
			}

			// Outbound event
			else {
				if ($rtype == 'block') {
					$dst_icons	= "{$unlock_ip}&nbsp;{$alert_ip}&nbsp;{$supp_ip}";
				} elseif ($rtype == 'match') {
					$dst_icons	= "{$alert_ip}";
				}
			}

			// Add []'s to IPv6 addresses and add a zero-width space as soft-break opportunity after each colon if we have an IPv6 address (from Snort)
			if ($fields[4] == 6) {
				$fields[97] = '[' . str_replace(':', ':&#8203;', $fields[7]) . ']';
				$fields[98] = '[' . str_replace(':', ':&#8203;', $fields[8]) . ']';
			}
			else {
				$fields[97] = $fields[7];
				$fields[98] = $fields[8];
			}

			if (empty($alias_new) && strlen($fields[15]) >= 17) {
				$pfb_matchtitle = " [ {$fields[15]} ]";
				$fields[15]	= substr($fields[15], 0, 16) . '...';
			}
			elseif (!empty($alias_new) && strlen($alias_new) >= 17) {
				$pfb_matchtitle = " [ {$alias_new} ]";
				$alias_new	= substr($alias_new, 0, 16) . '...';
			}

			if (empty($fields[16])) {
				$fields[16] = 'Unknown';
			}
			elseif (strlen($fields[16]) >= 22) {
				$fields[16] = "<span title=\"{$fields[16]}\">" . substr($fields[16], 0, 21) . '...</span>';
			}

			$rule = "{$fields[13]}<br /><small>({$fields[0]})</small>";

			$dup_cnt = '';
			if ($dup != 0) {
				$dup_cnt = "<span title=\"Total additional duplicate event count(s) [ {$dup} ]\"> [{$dup}]</span>";
				$dup = 0;
			}

			print ("<tr>
				<td>{$fields[99]}{$dup_cnt}</td>
				<td><small>{$fields[2]}</small></td>
				<td>{$rule}</td>
				<td><small>{$fields[6]}</small></td>
				<td>{$src_icons}</td>
				<td>{$fields[97]}{$srcport}<br /><small>{$hostname['src']}</small></td>
				<td>{$dst_icons}</td>
				<td>{$fields[98]}{$dstport}&emsp;{$query_port}<br /><small>{$hostname['dst']}</small></td>
				<td>{$fields[12]}</td>
				<td title=\"{$pfb_matchtitle}\">{$strike1}{$fields[15]}{$strike2}{$alias_new}<br />
					<small>{$strike1}{$fields[14]}{$strike2}{$eval_new}</small></td></tr>");

			// Collect Previous SRC port
			$p_query_port = $fields[10];
		}
	}
	@fclose($handle);
	unlink_if_exists("{$pfb_log}.rev");
}
?>
			</tbody>
			<tfoot>
<?php
			// Print final table info
			$msg = '';
			if ($pfbfilterlimit) {
				$msg = " - Filter Limit setting reached.";
			} elseif (!$pfb['filterlogentries'] && $pfbentries != $counter) {
				$msg = ' - Insufficient Alerts found.';
			}
			if ($type == 'DNSBL') {
				$colspan = "colspan='7'";
			} else {
				$colspan = "colspan='10'";
			}

			print ("<td {$colspan} style='font-size:10px; background-color: #F0F0F0;' >Found {$counter} Alert Entries {$msg}</td>");
			$counter = 0; $msg = '';
?>
			</tfoot>
		</table>
		</div>
	</div>
</div>
<?php
endforeach;	// End - Create four output windows ('Deny', 'DNSBL', 'Permit' and 'Match')
unset($fields_array);
?>

<!-- Show Icon Legend -->
<div class="infoblock">
	<div class="alert alert-info clearfix" role="alert">
		<dl class="dl-horizontal responsive">
			<dt><?=gettext('Icon')?></dt>
				<dd><?=gettext('Legend')?></dd>
			<dt><i class="fa fa-info">&nbsp;</i></dt>
				<dd><?=gettext('Links to Threat Source lookups');?></dd>
			<dt><i class="fa fa-plus"></i></dt>
				<dd><?=gettext('Whitelist a IP/Domain');?></dd>
			<dt><i class="fa fa-plus-circle"></i></dt>
				<dd><?=gettext('Whitelist 1) A GeoIP or large CIDR IP or 2) A TLD Domain');?></dd>
			<dt><i class="fa fa-hand-stop-o"></i></dt>
				<dd><?=gettext('Domain is blocked by a whole TLD');?></dd>
			<dt><i class="fa fa-trash"></i></dt>
				<dd><?=gettext('IP/Domain is already Whitelisted');?></dd>
			<dt><i class="fa fa-trash-o"></i></dt>
				<dd><?=gettext('Domain is in the TLD Exclusion customlist');?></dd>
			<dt><i class="fa fa-lock text-danger"></i></dt>
				<dd><?=gettext('IP/Domain is locked');?></dd>
			<dt><i class="fa fa-unlock"></i></dt>
				<dd><?=gettext('IP/Domain is unlocked');?></dd>
		</dl>
	</div>
</div>

<?php

elseif ($alert_summary):

	// Print Statistics table/graphs
	if (!$pfb['filterlogentries']): ?>

<form action="/pfblockerng/pfblockerng_alerts.php" method="post" name="iform_stats" id="iform_stats" class="form-horizontal">
<script src="../vendor/d3/d3.min.js"></script>
<script src="../vendor/d3pie/d3pie.min.js"></script>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">&nbsp;<?=$alert_title;?> Statistics&emsp;<small>Total event(s):
			&emsp;[ <?=$alert_stats['count'][$alert_view];?> ]</small></h2>
	</div>
</div>

<div class="panel-body">
	<?php
	$segcolors = array(	"#2484c1", "#65a620", "#7b6888", "#a05d56", "#961a1a", "#d8d23a", "#e98125", "#d0743c", "#635222", "#6ada6a",
				"#0c6197", "#7d9058", "#207f33", "#44b9b0", "#bca44a", "#e4a14b", "#a3acb2", "#8cc3e9", "#69a6f9", "#5b388f" );

	if ($alert_summary && $alert_view != 'dnsbl_stat') {
		$stats = array( 'srcipin'	=> array("Top SRC IP Inbound (by GeoIP)",	'Found', 'SRC IP(s)',		TRUE, 'host'),
				'srcipout'	=> array("Top SRC IP Outbound (by GeoIP)",	'Found', 'SRC IP(s)',		TRUE, 'host'),
				'dstipin'	=> array("Top DST IP Inbound (by GeoIP)",	'Found', 'DST IP(s)',		TRUE, 'host'),
				'dstipout'	=> array("Top DST IP Outbound (by GeoIP)",	'Found', 'DST IP(s)',		TRUE, 'host'),
				'srcport'	=> array("Top SRC Port (1-1024 only)",		'Found', 'SRC Port(s)',		FALSE, ''),
				'dstport'	=> array("Top DST Port",			'Found', 'DST Port(s)',		FALSE, ''),
				'geoip'		=> array("Top GeoIP",				'Found', 'GeoIP(s)',		FALSE, ''),
				'aliasname'	=> array("Top Aliasname",			'Found', 'Aliasname(s)',	FALSE, ''),
				'feed'		=> array("Top Feed",				'Found', 'Feed{s)',		FALSE, ''),
				'interface'	=> array("Top Interface",			'Found', 'Interface(s)',	FALSE, ''),
				'protocol'	=> array("Top Protocol",			'Found', 'Protocol(s)',		FALSE, ''),
				'direction'	=> array("Top Direction",			'Found', 'Direction(s)',	FALSE, ''),
				'date'		=> array("Historical Summary",			'Found', 'day(s) of logs',	FALSE, ''));
	}
	else {
		$stats = array( 'domain'	=> array('Top Blocked Domain',			'Found', 'Blocked Domain(s)',	TRUE, 'domain'),
				'evald'		=> array('Top Blocked Evaluated Domain (TLD)',	'Found', 'Blocked Domain(s)',	TRUE, 'domain'),
				'grouptotal'	=> array('Top Group Count',			'Found', 'DNSBL Group(s)',	FALSE, ''),
				'groupblock'	=> array('Top Blocked Group',			'Found', 'DNSBL Group(s)',	FALSE, ''),
				'dfeed'		=> array('Top Feed',				'Found', 'Feed(s)',		FALSE, ''),
				'ip'		=> array('Top Source IP',			'Found', 'Source IP(s)',	FALSE, ''),
				'agent'		=> array('Top User-Agent',			'Found', 'User-Agent(s)',	FALSE, ''),
				'tld'		=> array('Top TLD',				'Found', 'TLD(s)',		FALSE, ''),
				'webtype'	=> array('Top Blocked Webpage Types',		'Found', 'Blocked Webpage Type(s)',	FALSE, ''),
				'dnsbltype'	=> array('Top DNSBL Types',			'Found', 'Blocked DNSBL Type(s)',	FALSE, ''),
				'datehr'	=> array('Top Date/Hr',				'Found', 'Date/Hr segment(s)',		FALSE, ''),
				'datehrmin' 	=> array('Top Date/Hr/Min',			'Found', 'Date/Hr/Min segment(s)',	FALSE, ''),
				'date'		=> array('Historical Summary',			'Found', 'day(s) of logs',		FALSE, ''));
	}

	foreach ($stats as $stat_type => $stype):

		$topcount = count($alert_stats[$alert_view][$stat_type]);
		$sumlines = array_sum($alert_stats[$alert_view][$stat_type]);

		$height = 30;
		if ($topcount > 0) {
			$height = 390;
		}

		if ($alert_view == 'dnsbl_stat') {
			$stat_hidden = $pfbdnsblstat;
		} elseif ($alert_view == 'ip_block_stat') {
			$stat_hidden = $pfbblockstat;
		} elseif ($alert_view == 'ip_permit_stat') {
			$stat_hidden = $pfbpermitstat;
		} elseif ($alert_view == 'ip_match_stat') {
			$stat_hidden = $pfbmatchstat;
		}

		$collapse_status = 'in';
		if (in_array($stat_type, $stat_hidden)) {
			$collapse_status = 'out';
			continue;
		}
	?>

	<div class="panel panel-default" id="Alert_Stats_<?=$stat_type?>" style="display: inline-block; width: 100%;">
		<div class="panel-heading">
			<h2 class="panel-title"><?=$stype[0]?>
				<span class="widget-heading-icon pull-right">
					<a data-toggle="collapse" href="#Alert_Stats_<?=$stat_type?>_panel-body" id="Alert_Stats_A_<?=$stat_type?>">
						<i class="fa fa-plus-circle"></i>
					</a>
				</span>
			</h2>
		</div>

		<div class="panel-body collapse <?=$collapse_status?>" id="Alert_Stats_<?=$stat_type?>_panel-body" style="overflow-x: auto;">
			<div style="height: <?=$height;?>px; width: 50%; float: left; overflow-y: scroll;">
			<table class="table table-responsive table-bordered table-striped table-hover table-compact sortable-theme-bootstrap" data-sortable>

				<thead>
					<tr>
						<th style="width: 10%;"><!--  Action buttons --></th>
						<th style="width: 10%; text-align: center;"><?=gettext("Count")?></th>

						<?php if ($stype[3]): ?>
						<th style="width: 2%; text-align: center;">
							<?=gettext(($stat_type == 'domain' || $stat_type == 'evald') ? 'Type' : 'GeoIP');?></th>
						<?php endif; ?>

						<th><small><?=$stype[1] . "&emsp;[ {$topcount} ]&emsp;" . $stype[2]?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if (!empty($alert_stats)) {
						foreach ($alert_stats[$alert_view][$stat_type] as $data => $data_count) {
							$alert_event = $btnsubmit = $query_port = $hostname = '';
							$subdata = array();

							$filter_value = $data;
							if ($stat_type == 'tld') {
								$filter_value = "\.{$data}$";
							}
							elseif ($stat_type == 'srcport' || $stat_type == 'dstport') {
								$filter_value = "^{$data}$";
							}

							if ($stat_type != 'direction') {
								$btnsubmit = '<button type="submit" class="fa fa-filter button-icon"'
										. " name=\"filterlogentries_submit_{$stat_type}\""
										. " id=\"filterlogentries_submit_{$stat_type}\""
										. " value=\"{$filter_value}\" title=\"Filter Alerts for [ {$data} ]\"></button>";
							}

							// Collect GeoIP or DNSBL Type classification
							if ($stype[3]) {
								$subdata = explode(',', $data);
								if ($stat_type == 'evald') {

									// Skip DNSBL events
									if ($subdata[0] == 'DNSBL') {
										unset($alert_stats[$alert_view][$stat_type][$data]);
										continue;
									}

									$data		= $subdata[1];
									$subdata[1]	= $subdata[0];
								}
								else {
									$data = $subdata[0];
								}

								$alert_event = '<a class="fa fa-info icon-pointer icon-primary"'
										. ' title="Click for Threat Lookup." target="_blank"'
										. ' href="/pfblockerng/pfblockerng_threats.php?' . $stype[4] . '=' . $data . '"></a>';
							}

							if ($stat_type == 'datehr' || $stat_type == 'datehrmin') {
								$d = explode (' ', $data);
								$data = "{$d[0]} {$d[1]}&emsp;({$d[2]})";
							}

							if (!empty($data) && $data != 'Not available for HTTPS alerts') {

								// Report Local hostname if found
								if ($stat_type == 'ip' || $stat_type == 'srcipout' || $stat_type == 'dstipin') {
									if (isset($local_hosts[$data])) {
										$hostname = "&emsp;<small>( {$local_hosts[$data]} )</small>";
									}
								}

								// Get external IP hostname and Resolved hostname
								elseif ($stat_type == 'srcipin' || $stat_type == 'dstipout') {
									$title = '';
									if (strlen($subdata[2]) >= 45) {
										$title = "title=\"{$subdata[2]}\"";
										$subdata[2] = substr($subdata[2], 0, 45) . '...';
									}
									$hostname = "<br /><span $title}><small>{$subdata[2]}</small></span>";
								}
							}

							if ($stat_type == 'agent' && $data == 'Unknown') {
								$data = 'Not available for HTTPS alerts';
							}

							if ($stat_type == 'dstport') {
								$query_port = '&nbsp;<a class="fa fa-search icon-pointer" target="_blank"'
										. ' href="/pfblockerng/pfblockerng_threats.php?port=' . $data
										. '" title="Click for Threat Port Lookup [ ' . $data . ' ]"></a>';
							}

							elseif ($stat_type == 'direction') {
								if ($data == 'in') {
									$data = 'Inbound packets';
								} else {
									$data = 'Outbound packets';
								}
							}

							if (empty($data)) {
								$data = 'Unknown';
							}

							$td_type = '';
							if ($stype[3]) {
								$td_type = "<td style=\"text-align: center;\">{$subdata[1]}</td>";
							}

							print ("<tr>
								<td style=\"text-align: center; white-space: nowrap;\">{$alert_event}{$query_port}{$btnsubmit}</td>
								<td style=\"text-align: right; padding-right: 15px;\">{$data_count}</td>
								{$td_type}
								<td style=\"white-space: nowrap;\">{$data}{$hostname}</td></tr>");
						}
					}
					?>
				</tbody>
			</table>
			</div>

			<div style="height: <?=$height;?>px; width: 50%; float: right;">
				<div id="pieChart_<?=$stat_type?>">
				<?php
					if ($topcount > 9) {
						$alert_stats[$alert_view][$stat_type] = array_slice($alert_stats[$alert_view][$stat_type], 0, 10, TRUE);
					}

					if (!empty($alert_stats[$alert_view][$stat_type])) {
						pie_block($alert_stats[$alert_view][$stat_type], $stat_type, $topcount, 10, $segcolors);
					}
				?>
				</div>
			</div>
		</div>
	</div>
	<?php endforeach; ?>
	</div>
</div>
</form>
	<?php endif;
endif;

function pfb_cmp($a, $b) {
	if ($a == $b) {
		return 0;
	}
	return ($a < $b) ? 1 : -1;
}

function pie_block($summary, $stat_type, $sumlines, $numsegments, $segcolors) {

?>
<script type="text/javascript">
//<![CDATA[

var pieChart_<?=$stat_type?> = new d3pie("pieChart_<?=$stat_type?>", {
	"size": {
		"canvasHeight": 390,
		"canvasWidth": 560,
		"pieInnerRadius": 60,
		"pieOuterRadius": "78%"
	},
	"data": {
		"sortOrder": "value-asc",
		"content": [
<?php
	uasort($summary, 'pfb_cmp');
	$k = array_keys($summary);
	$numentries = 0;
	for ($i = 0; $i < ($numsegments-1); $i++) {
		if ($k[$i]) {
			$numentries++;
			if ($i > 0) {
				print(",\r\n");
			}

			// Don't add 0 values
			if ($summary[$k[$i]] == 0) {
				$summary[$k[$i]] = 0.1;
			}

			print("{");
			print('"label": "' . $k[$i] . '", "value": ');
			print($summary[$k[$i]]);
			print(', "color": "' . $segcolors[$i % $numsegments] . '"');
			print("}");
		}
	}

	$balance = $sumlines - $numentries;
	if ($balance > 0) {
		print(",\r\n");
		print("{");
		print('"label": "Other", "value": ');
		print($balance);
		print(', "color": "' . $segcolors[$i % $numsegments] . '"');
		print("}");
	}
?>
		]
	},
	"labels": {
		"outer": {
			"pieDistance": 25
		},
		"inner": {
			"format": "percentage",
			"hideWhenLessThanPercentage": 3
		},
		"mainLabel": {
			"font": "verdana",
			"fontSize": 14
		},
		"percentage": {
			"color": "#ffffff",
			"font": "verdana",
			"fontSize": 10,
			"decimalPlaces": 0
		},
		"value": {
			"color": "#adadad",
			"font": "verdana",
			"fontSize": 15
		},
		"lines": {
			"enabled": true,
			"style": "curved",
			"color": "segment"
		},
		"truncation": {
			"enabled": true,
			truncateLength: 15
		}
	},
	"effects": {
		"load": {
			"speed": 300
		},
		"pullOutSegmentOnClick": {
			"effect": "linear",
			"speed": 400,
			"size": 20
		},
		highlightSegmentOnMouseover: true,
		highlightLuminosity: -0.7
	},
	tooltips: {
		enabled: true,
		type: "placeholder",
		string: "{label}: {percentage}% ({value})",
		placeholderParser: null,
		styles: {
			fadeInSpeed: 250,
			backgroundColor: "#000000",
			backgroundOpacity: 0.5,
			color: "#f7f7f7",
			borderRadius: 2,
			font: "verdana",
			fontSize: 14,
			padding: 4
		}
	},
	"misc": {
		"gradient": {
			"enabled": true,
			"percentage": 50
		},
		"pieCenterOffset": {
			"x": 0,
			"y": 0
		},
		colors: {
			background: null,
			segmentStroke: "#ffffff"
		}
	}
});
//]]>
</script>
<?php } ?>

<?php include('foot.inc');?>
<script type="text/javascript">
//<![CDATA[

function dnsbl_whitelist() {

	if (domain && table) {
		$('#addwhitelistdom').val('true');
		$('form').submit();
	}
}

function ip_whitelist() {

	if (ip && table) {
		$('#ip_white').val('true');
		$('form').submit();
	}
}

function ip_suppression() {

	var description = prompt('Please enter Suppression description');
	$('#descr').val(description);

	if (cidr.value != '' && ip && table) {
		$('#addsuppress').val('true');
		$('form').submit();
	}
}

function ip_suppression_type() {

	var buttons = {};
	buttons['Suppress /32'] = function() {
						$('#cidr').val('32');
						$(this).dialog('close');
						ip_suppression();
						};
	buttons['Suppress /24'] = function() {
						$('#cidr').val('24');
						$(this).dialog('close');
						ip_suppression();
						};
	buttons['Cancel'] = function() { $(this).dialog('close'); };

	$('<div></div>').appendTo('body')
	.html('<div><h6>Select Suppression Mask:</h6></div>')
	.dialog({
		modal: true,
		autoOpen: true,
		resizable: false,
		closeOnEscape: true,
		width: 'auto',
		title: 'Select a Suppression Mask:',
		position: { my: 'top', at: 'top' },
		buttons: buttons
	}).css('background-color','#ffd700');
	$("div[role=dialog]").find('button').addClass('btn-info btn-xs');
}

function add_description(mode) {

	$('<div></Div>').appendTo('body')
	.html('<div><h6>Do you want to add a description?</h6></div>')
	.dialog({
		modal: true,
		autoOpen: true,
		resizable: false,
		closeOnEscape: true,
		width: 'auto',
		title: 'Whitelist description:',
		position: { my: 'top', at: 'top' },
		buttons: {
			Yes: function () {
				var description = prompt('Please enter Whitelist description');
				$('#descr').val(description);
				$(this).dialog('close');
				if (mode == 'dnsbl') {
					dnsbl_whitelist();
				} else {
					ip_whitelist();
				}
			},
			No: function () {
				$(this).dialog('close');
				if (mode == 'dnsbl') {
					dnsbl_whitelist();
				} else {
					ip_whitelist();
				}
			},
			'Cancel Whitelist': function (event, ui) {
				$(this).dialog('close');
			}
		}
	}).css('background-color','#ffd700');
	$("div[role=dialog]").find('button').addClass('btn-info btn-xs');
}


function select_whitelist(permit_list) {

	var buttons = {};
	$.each(permit_list, function(index, val) {
		buttons[index + ') ' + val] = function() {
								// Rename 'Create new IP Whitelist'
								if (val.indexOf("Create new") >= 0) {
									val = val.replace('Create new ', 'NEW_');
								}

								$('#table').val(val);
								$(this).dialog('close');
								add_description('ip');
							};
	});
	buttons['Cancel'] = function() { $(this).dialog('close'); };

	$('<div></div>').appendTo('body')
	.html('<div><h6>Select Whitelist:</h6></div>')
	.dialog({
		modal: true,
		autoOpen: true,
		resizable: false,
		closeOnEscape: true,
		width: 'auto',
		title: 'Select a Permit Whitelist Alias:',
		position: { my: 'top', at: 'top' },
		buttons: buttons
	}).css('background-color','#ffd700');
	$("div[role=dialog]").find('button').addClass('btn-info btn-xs');
}


events.push(function() {

	// Redraw d3pie chart when table window was previously collapsed
	$('[id^=Alert_Stats_A_]').click(function() {

		// collect name of piechart to redraw
		var pieChart = this.id.replace('Alert_Stats_A_', '');

		if (pieChart == 'srcipin') {
			pieChart_srcipin.redraw();
		} else if (pieChart == 'srcipout') {
			pieChart_srcipout.redraw();
		} else if (pieChart == 'dstipin') {
			pieChart_dstipin.redraw();
		} else if (pieChart == 'dstipout') {
			pieChart_dstipout.redraw();
		} else if (pieChart == 'srcport') {
			pieChart_srcport.redraw();
		} else if (pieChart == 'dstport') {
			pieChart_dstport.redraw();
		} else if (pieChart == 'geoip') {
			pieChart_geoip.redraw();
		} else if (pieChart == 'aliasname') {
			pieChart_aliasname.redraw();
		} else if (pieChart == 'feed') {
			pieChart_feed.redraw();
		} else if (pieChart == 'interface') {
			pieChart_interface.redraw();
		} else if (pieChart == 'protocol') {
			pieChart_protocol.redraw();
		} else if (pieChart == 'direction') {
			pieChart_direction.redraw();

		} else if (pieChart == 'date') {
			pieChart_date.redraw();

		} else if (pieChart == 'domain') {
			pieChart_domain.redraw();
		} else if (pieChart == 'evald') {
			pieChart_evald.redraw();
		} else if (pieChart == 'grouptotal') {
			pieChart_grouptotal.redraw();
		} else if (pieChart == 'groupblock') {
			pieChart_groupblock.redraw();
		} else if (pieChart == 'dfeed') {
			pieChart_dfeed.redraw();
		} else if (pieChart == 'ip') {
			pieChart_ip.redraw();
		} else if (pieChart == 'agent') {
			pieChart_agent.redraw();
		} else if (pieChart == 'tld') {
			pieChart_tld.redraw();
		} else if (pieChart == 'webtype') {
			pieChart_webtype.redraw();
		} else if (pieChart == 'dnsbltype') {
			pieChart_dnsbltype.redraw();
		} else if (pieChart == 'datehr') {
			pieChart_datehr.redraw();
		} else if (pieChart == 'datehrmin') {
			pieChart_datehrmin.redraw();
		}
	})

	$('[id^=DNSBLWT]').click(function(event) {
		if (confirm(event.target.title)) {
			$('meta[http-equiv=refresh]').remove();
			var arr = this.id.split('|');

			var DNSBLWT_Type = arr[1];	// add/delete/exclude/TLD
			$('#domain').val(arr[2]);	// Domain or IP
			if (typeof arr[2] === 'undefined') {
				return;
			}
			var blocktype = '';		// Types (DNSBL/TLD/DNSBL TLD)

			switch (DNSBLWT_Type) {
				case 'add':
					$('#table').val(arr[3]);	// Feed Name
					var blocktype = arr[4];
					break;
				case 'delete_domain':
				case 'delete_domainwildcard':
				case 'delete_exclusion':
				case 'delete_ip':
				case 'delete_ipwhitelist':
					if (DNSBLWT_Type == 'delete_ipwhitelist' || DNSBLWT_Type == 'delete_ip') {
						$('#table').val(arr[3]);
					}
					$('#entry_delete').val(DNSBLWT_Type);
					$('form').submit();
					return;
				case 'tld':
					$('#table').val(arr[3]);
					var blocktype = arr[4];
					break;
				default:
					return;
			}

			var buttons = {
					'1. Wildcard Whitelist': function() {
						$('#dnsbl_wildcard').val('true');
						$(this).dialog('close');
						add_description('dnsbl');
						}
					};

			if (blocktype != 'TLD') {
				msg = 'Do you wish to Wildcard Whitelist [ .' + arr[2] + ' ] or only Whitelist [ ' + arr[2] + ' ]?';
				buttons['2. Whitelist'] = function() {
							$(this).dialog('close');
							add_description('dnsbl');
						};
			}
			else {
				msg = 'Do you wish to Wildcard Whitelist [ .' + arr[2] + ' ] or add it to the TLD Exclusion customlist?';
				buttons['2. Exclude'] = function() {
							$('#dnsbl_exclude').val('true');
							$(this).dialog('close');
							add_description('dnsbl');
						};
			}
			buttons['Cancel'] = function() { $(this).dialog('close'); };

			$('<div></div>').appendTo('body')
			.html('<div><h6>' + msg + '</h6></div>')
			.dialog({
				modal: true,
				autoOpen: true,
				resizable: false,
				closeOnEscape: true,
				width: 'auto',
				title: 'Domain Whitelisting:',
				position: { my: 'top', at: 'top' },
				buttons: buttons
			}).css('background-color','#ffd700');
			$("div[role=dialog]").find('button').addClass('btn-info btn-xs');
		}
	});

	$('[id^=PFBIPSUP]').click(function(event) {
		if (confirm(event.target.title)) {
			$('meta[http-equiv=refresh]').remove();
			var arr = this.id.split('|');
			$('#ip').val(arr[2]);
			$('#table').val(arr[3]);

			var permit_list = arr.splice(4);
			if (permit_list) {

				$('<div></Div>').appendTo('body')
				.html('<div><h6>Do you want to Suppress or Add to a Permit Whitelist Alias?</h6></div>')
				.dialog({
					modal: true,
					autoOpen: true,
					resizable: false,
					closeOnEscape: true,
					width: 'auto',
					title: 'Whitelist Description:',
					position: { my: 'top', at: 'top' },
					buttons: {
						'1) Suppress': function () {
								$(this).dialog('close');
								ip_suppression_type();
						},
						'2) Whitelist': function () {
								$(this).dialog('close');
								select_whitelist(permit_list);
						},
						'Cancel': function (event, ui) {
							$(this).dialog('close');
						}
					}
				}).css('background-color','#ffd700');
				$("div[role=dialog]").find('button').addClass('btn-info btn-xs');
			}
			else {
				ip_suppression_type();
			}
		}
	});

	$('[id^=PFBIPWHITE').click(function(event) {
		if (confirm(event.target.title)) {
			$('meta[http-equiv=refresh]').remove();
			var arr = this.id.split('|');
			$('#ip').val(arr[1]);

			var permit_list = arr.splice(2);
			select_whitelist(permit_list);
		}
	});

	$('[id^=DNSBL_ULCK]').click(function(event) {
		if (confirm(event.target.title)) {
			var arr = this.id.split('|');
			$('#domain').val(arr[1]);
			$('#dnsbl_type').val(arr[2]);

			$('#dnsbl_remove').val('unlock');
			$('form').submit();
		}
	});

	$('[id^=DNSBL_LCK]').click(function(event) {
		if (confirm(event.target.title)) {
			var arr = this.id.split('|');
			$('#domain').val(arr[1]);
			$('#dnsbl_type').val(arr[2]);

			$('#dnsbl_remove').val('lock');
			$('form').submit();
		}
	});

	$('[id^=DNSBL_RELCK]').click(function(event) {
		if (confirm(event.target.title)) {
			var arr = this.id.split('|');
			$('#domain').val(arr[1]);
			$('#dnsbl_type').val(arr[2]);

			$('#dnsbl_remove').val('relock');
			$('form').submit();
		}
	});

	$('[id^=DNSBL_REULCK]').click(function(event) {
		if (confirm(event.target.title)) {
			var arr = this.id.split('|');
			$('#domain').val(arr[1]);
			$('#dnsbl_type').val(arr[2]);

			$('#dnsbl_remove').val('reunlock');
			$('form').submit();
		}
	});

	$('[id^=IPULCK]').click(function(event) {
		if (confirm(event.target.title)) {
			var arr = this.id.split('|');
			$('#ip').val(arr[1]);
			$('#table').val(arr[2]);

			$('#ip_remove').val('unlock');
			$('form').submit();
		}
	});

	$('[id^=IPLCK]').click(function(event) {
		if (confirm(event.target.title)) {
			var arr = this.id.split('|');
			$('#ip').val(arr[1]);
			$('#table').val(arr[2]);

			$('#ip_remove').val('lock');
			$('form').submit();
		}
	});
});

//]]>
</script>
