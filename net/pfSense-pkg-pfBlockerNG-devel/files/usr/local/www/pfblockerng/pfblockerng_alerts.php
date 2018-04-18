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
		$pfb['aglobal']['pfbblockstat']	= implode(',', (array)htmlspecialchars($_POST['pfbblockstat']))	?: '';
		$pfb['aglobal']['pfbpermitstat']= implode(',', (array)htmlspecialchars($_POST['pfbpermitstat']))?: '';
		$pfb['aglobal']['pfbmatchstat']	= implode(',', (array)htmlspecialchars($_POST['pfbmatchstat']))	?: '';
		$pfb['aglobal']['pfbdnsblstat']	= implode(',', (array)htmlspecialchars($_POST['pfbdnsblstat']))	?: '';

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
					$whitelist = "{$domain} # {$descr}\nwww.{$domain} # {$descr}";
				}
			} else {
				if ($wildcard) {
					$whitelist = ".{$domain}";
				} else {
					$whitelist = "{$domain}\nwww.{$domain}";
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

$skipcount = $counter = $resolvecounter = 0;
// Create three output windows 'Deny', 'DNSBL', 'Permit' and 'Match'-->
foreach (array (	'Deny'		=> "{$pfb['denydir']}/* {$pfb['nativedir']}/*",
			'DNSBL'		=> "{$pfb['dnsdir']}",
			'Permit'	=> "{$pfb['permitdir']}/* {$pfb['nativedir']}/*",
			'Match'		=> "{$pfb['matchdir']}/* {$pfb['nativedir']}/*" ) as $type => $pfbfolder ):

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
			$rtype = 'unkn(%u)';
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
					<th><?=gettext("List")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	$dns_array = $final = array();
	$pdomain = '';
	if (file_exists("{$pfb['dnslog']}")) {
		if (($handle = @fopen("{$pfb['dnslog']}", 'r')) !== FALSE) {
			while (($line = fgetcsv($handle)) !== FALSE) {

				// Define missing data for HTTPS alerts
				if ($line[0] == 'DNSBL Reject HTTPS') {
					$line[3] = '<small>Unknown</small>';
					$line[4] = ' Not available for HTTPS alerts';
				}

				// Remove duplicate domain/srcips
				if (("{$line[2]}{$line[3]}") != $pdomain) {
					$final[] = $line;
				}
				$pdomain = "{$line[2]}{$line[3]}";
			}
		}
		@fclose($handle);

		if (!empty($final)) {
			$dns_array = array_slice(array_reverse($final), 0, $pfbentries);
		}
	}

	if (!empty($dns_array)) {

		// Collect TLD Blacklist and remove any leading/trailing 'dot'
		$tld_blacklist = array_flip(pfbng_text_area_decode($pfb['dnsblconfig']['tldblacklist'], TRUE, FALSE));
		if (!empty($tld_blacklist)) {
			foreach ($tld_blacklist as $tld => $key) {
				unset($tld_blacklist[$tld]);
				$tld_blacklist[trim($tld, '.')] = '';
			}
		}

		foreach ($dns_array as $aline) {

			// Determine interface name based on Source IP address
			$pfbalertdnsbl[1] = 'LAN';		// Define LAN Interface as 'default'
			if (!empty($dnsbl_int)) {
				foreach ($dnsbl_int as $subnet) {
					if (strpos($aline[3], 'Unknown') !== FALSE) {
						$pfbalertdnsbl[1] = 'Unknown';
						break;
					} elseif (ip_in_subnet($aline[3], $subnet[0])) {
						$pfbalertdnsbl[1] = "{$subnet[1]}";
						break;
					}
				}
			}

			$pfbalertdnsbl[99]	= $aline[1];	// Timestamp

			// SRC IP Address and Hostname
			if (isset($local_hosts[$aline[3]])) {
				$pfbalertdnsbl[7] = "{$aline[3]}<br /><small>{$local_hosts[$aline[3]]}</small>";
			} else {
				$pfbalertdnsbl[7] = $aline[3];
			}

			$pfbalertdnsbl[8]	= $aline[2];	// Blocked Domain
			$pfbalertdnsbl[90]	= $aline[4];	// DNSBL URL

			// Add 'https' icon to Domains as required.
			$pfb_https = '';
			if (strpos($aline[4], 'https://') !== FALSE || strpos($aline[4], 'Not available for HTTPS alerts') !== FALSE) {
				$pfb_https = '&nbsp;<i class="fa fa-key icon-pointer" title="HTTPS alerts are not fully logged due to Browser security"></i>';
			}

			// If alerts filtering is selected, process filters as required.
			if ($pfb['filterlogentries'] && !pfb_match_filter_field($pfbalertdnsbl, $filterfieldsarray)) {
				continue;
			}

			// Collect the list that contains the blocked Domain
			$pfb_tld	= FALSE;
			$domain		= $domain_final = $pfbalertdnsbl[8];
			$domainparse	= str_replace('.', '\.', $domain);
			$sed_cmd	= "{$pfb['sed']} -e 's/^.*[a-zA-Z]\///' -e 's/:.*//' -e 's/\..*/ /'";
			$dquery		= " \"{$domainparse} 60";
			$pfb_query	= exec("{$pfb['grep']} -Hm1 '{$dquery}' {$pfb['dnsdir']}/*.txt | {$sed_cmd}");

			$pfb_alias = '';
			if (!empty($pfb_query)) {
				$pfb_alias = exec("{$pfb['grep']} -Hm1 '{$dquery}' {$pfb['dnsalias']}/* | {$sed_cmd}");
			}

			if (empty($pfb_query)) {
				$dparts = explode('.', $domain);
				unset($dparts[0]);
				$dcnt	= count($dparts);
				$dtld	= end($dparts);

				// Determine if TLD exists in TLD Blacklist
				if (isset($tld_blacklist[$dtld])) {
					$pfb_query	= 'DNSBL_TLD';
				}

				// Search Sub-Domains for match
				elseif (is_numeric($dcnt)) {

					for ($i=0; $i < ($dcnt -1); $i++) {
						$domainparse	= str_replace('.', '\.', implode('.', $dparts));
						$dquery		= " \"{$domainparse} 60";
						$pfb_query	= exec("{$pfb['grep']} -Hm1 '{$dquery}' {$pfb['dnsdir']}/*.txt | {$sed_cmd}");

						// Collect Alias Group name
						if (!empty($pfb_query)) {
							$pfb_alias	= exec("{$pfb['grep']} -Hm1 '{$dquery}' {$pfb['dnsalias']}/* | {$sed_cmd}");
							$domain_final	= str_replace('\.', '.', $domainparse);
							$pfb_tld	= TRUE;
							break;
						}
						unset($dparts[$i]);
					}
				}
			}

			if (empty($pfb_query)) {
				$pfb_query = 'no match';
				$pfb_alias = '';
			}

			$pfb_matchtitle = "The DNSBL Feed and Alias that blocked the indicated Domain.";

			// Truncate long list names
			if (strlen($pfb_query) >= 17 || strlen($pfb_alias) >= 25) {
				$pfb_matchtitle = "Feed: {$pfb_query} | Alias: {$pfb_alias}";
				$pfb_query	= substr($pfb_query, 0, 16) . '...';
				$pfb_alias	= substr($pfb_alias, 0, 24) . '...';
			}

			$alert_dom = '<a class="fa fa-info icon-pointer icon-primary" title="Click for Threat Source Lookup." ' .
					'href="/pfblockerng/pfblockerng_threats.php?domain=' . $domain_final . '"></a>';

			$supp_dom_txt = '';
			if ($pfb_tld) {
				$supp_dom_txt  = "The whole Domain/Sub-Domains of [ {$domain_final} ] is being blocked via TLD.\n\n";
				$supp_dom_txt .= "Whitelisting Options:\n";
				$supp_dom_txt .= "- Whitelist only this Domain\n";
				$supp_dom_txt .= "- Whitelist the entire Domain/Sub-Domains\n";
				$supp_dom_txt .= "- Manually add this Domain to the 'TLD Exclusion customlist' which will bypass\n";
				$supp_dom_txt .= "the TLD process and only block the listed Sub-Domains only. (A Force Reload-DNSBL required!)\n\n";
			}

			$supp_dom_txt .= "Clicking this Whitelist Icon, will immediately remove the blocked Domain from DNSBL.\n";
			$supp_dom_txt .= "CNAMES will also be whitelisted, if found. (Google DNS @8.8.8.8 is used to collect the CNAMES)\n\n";
			$supp_dom_txt .= "To manually add Domain(s), edit the 'Custom Domain Whitelist' in the DNSBL tab.\n";
			$supp_dom_txt .= "Manual entries require a 'Force Reload - DNSBL' to take effect";

			// Determine if Domain exists in Whitelist
			if ($pfb_query != 'DNSBL_TLD') {
				
				// Default - Domain not in Whitelist
				$supp_dom = '<i class="fa fa-plus icon-pointer icon-primary" id="DNSBLSUP' .
						$domain_final . '|' . $pfb_query . '" title="' . $supp_dom_txt . '"></i>';

				// Root Domain blocking all Sub-Domains
				if ($pfb_tld) {
					$supp_dom = '<i class="fa fa-plus-circle icon-pointer icon-primary" id="DNSBLSUP' .
						$domain_final . '|' . $pfb_query . '" title="' . $supp_dom_txt . '"></i>';
				}

				// Determine if Alerted Domain is in Whitelist
				elseif (in_array($domain_final, $dnssupp_ex)) {
					$supp_dom = '<i class="fa fa-plus-square-o icon-pointer"' .
						' title="This Domain is already in the DNSBL WhiteList"></i>&emsp;';
				}

				// Determine if Alerted Domain is in Whitelist (prefixed by a "dot" )
				elseif (!empty($dnssupp_ex_tld)) {
					$dparts	= explode('.', $domain_final);
					$dcnt	= count($dparts);
					for ($i=$dcnt; $i > 0; $i--) {
						$d_query = implode('.', array_slice($dparts, -$i, $i, TRUE));
						if (isset($dnssupp_ex_tld[$d_query])) {
							$supp_dom = '<i class="fa fa-minus-square-o icon-pointer" title="The following Domain [ ' .
									$d_query . ' ] is already in the DNSBL WhiteList"></i>&emsp;';
							break;
						}

						// Remove Whitelist Icon for 'no match'
						if ($pfb_query == 'no match') {
							$supp_dom = '';
						}
					}
				}

				// Remove Whitelist Icon for 'no match'
				elseif ($pfb_query == 'no match') {
					$supp_dom = '';
				}
			}
			else {
				// Whole TLD is blocked
				$supp_dom = "<i class=\"fa fa-hand-stop-o\" title=\"The whole TLD [ {$dtld} ] is being blocked.\"></i>";
			}

			// Truncate long URLs
			$url_title = '';
			if (strlen($pfbalertdnsbl[90]) >= 72) {
				$url_title = "{$pfbalertdnsbl[90]}";
				$pfbalertdnsbl[90] = substr(str_replace(array('?', '-'), '', $pfbalertdnsbl[90]), 0, 69) . '...';
			}

			print ("<tr>
				<td>{$pfbalertdnsbl[99]}</td>
				<td><small>{$pfbalertdnsbl[1]}</small></td>
				<td>{$pfbalertdnsbl[7]}</td>
				<td>{$alert_dom} {$supp_dom}</td>
				<td  title=\"{$url_title}\">{$pfbalertdnsbl[8]} {$pfb_https}
					<br />&nbsp;&nbsp;<small>{$pfbalertdnsbl[90]}</small></td>
				<td title=\"{$pfb_matchtitle}\">{$pfb_query}
					<br /><small>{$pfb_alias}</small></td></tr>");
			$counter++;
		}
	}
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
					<th><?=gettext("CC")?></th>
					<th><?=gettext("List")?></th>
				</tr>
			</thead>
			<tbody>
<?php
}

// Process fields array for: Deny/Permit/Match and generate output
if (!empty($fields_array[$type]) && !empty($rule_list) && $type != 'DNSBL') {
	foreach ($fields_array[$type] as $fields) {
		$rulenum = $alert_ip = $supp_ip = $pfb_query = $src_icons = $dst_icons = '';

		/* Fields_array Reference	[0]	= Rulenum			[6]	= Protocol
						[1]	= Real Interface		[7]	= SRC IP
						[2]	= Friendly Interface Name	[8]	= DST IP
						[3]	= Action			[9]	= SRC Port
						[4]	= Version			[10]	= DST Port
						[5]	= Protocol ID			[11]	= Flags
						[99]	= Timestamp	*/

		$rulenum = $fields[0];
		if ($counter < $pfbentries) {
			// Cleanup port output
			if ($fields[6] == 'ICMP' || $fields[6] == 'ICMPV6') {
				$srcport = '';
			} else {
				$srcport = ":{$fields[9]}";
				$dstport = ":{$fields[10]}";
			}

			// Don't add suppress icon to Country block lines
			if (in_array(substr($rule_list[$rulenum]['name'], 0, -3), $continents)) {
				$pfb_query = 'Country';
			}

			// Add DNS resolve and Suppression icons to external IPs only. GeoIP code to external IPs only.
			if (in_array($fields[8], $pfb_local) || ip_in_pfb_localsub($fields[8])) {
				// Destination is gateway/NAT/VIP
				$rule = "{$rule_list[$rulenum]['name']}<br /><small>({$rulenum})</small>";
				$host = $fields[7];

				$alert_ip = '<a class="fa fa-info icon-pointer icon-primary" href="/pfblockerng/pfblockerng_threats.php?host=' .
						$host . '" title="Click for Threat Source Lookup."></a>';

				if ($pfb_query != 'Country' && $rtype == 'block' && $pfb['supp'] == 'on') {
					$supp_ip = '<i class="fa fa-plus icon-pointer icon-primary"' .
						'id="PFBIPSUP' . $host . '|' . $rule_list[$rulenum]['name'] . '" title="Add IP to Suppress List"></i>';
				}

				if ($rtype == 'block' && $hostlookup == 'on') {
					$hostname = getpfbhostname('src', $fields[7], $counter, $fields[8]);
				} else {
					$hostname = array('src' => '', 'dst' => '');
				}

				$src_icons_1	= "{$alert_ip}&nbsp;{$supp_ip}";
				$src_icons_2	= "{$alert_ip}";
				$dst_icons_1	= '';
				$dst_icons_2	= '';

			} else {
				// Outbound
				$rule = "{$rule_list[$rulenum]['name']}<br /><small>({$rulenum})</small>";
				$host = $fields[8];

				$alert_ip = '<a class="fa fa-info icon-pointer icon-primary" href="/pfblockerng/pfblockerng_threats.php?host=' .
						$host . '" title="Click for Threat Source Lookup."></a>';

				if ($pfb_query != 'Country' && $rtype == 'block' && $pfb['supp'] == 'on') {
					$supp_ip = '<i class="fa fa-plus icon-pointer icon-primary"' .
						'id="PFBIPSUP' . $host . '|' . $rule_list[$rulenum]['name'] . '" title="Add IP to Suppress List"></i>';
				}

				if ($rtype == 'block' && $hostlookup == 'on') {
					$hostname = getpfbhostname('dst', $fields[8], $counter, $fields[7]);
				} else {
					$hostname = array('src' => '', 'dst' => '');
				}

				$src_icons_1	= '';
				$src_icons_2	= '';
				$dst_icons_1	= "{$alert_ip}&nbsp;{$supp_ip}&nbsp;";
				$dst_icons_2	= "{$alert_ip}";
			}

			// Determine Country code of host
			if (is_ipaddrv4($host)) {
				$country = substr(exec("{$pathgeoip} -f {$pathgeoipdat} {$host}"), 23, 2);
			} else {
				$country = substr(exec("{$pathgeoip6} -f {$pathgeoipdat6} {$host}"), 26, 2);
			}

			// Find the header which alerted this host
			if ($pfb_query != 'Country') {
				if (strpos($rule, 'pfB_DNSBLIP') !== FALSE) {
					$pfb_match[1] = 'DNSBLIP';	// Default pfB_DNSBLIP
					$pfb_match[2] = '';
				}
				else {
					$pfb_query = find_reported_header($host, $pfbfolder, FALSE);

					// Report specific ET IQRisk details
					if ($pfb['et_header'] && strpos($pfb_query[1], "{$et_header}") !== FALSE) {
						$ET_orig = $pfb_query;
						$pfb_query = find_reported_header($host, "{$pfb['etdir']}/*", FALSE);

						// On 'no match', ET IQRisk category is unknown.
						if ($pfb_query[1] == 'no match') {
							$pfb_query = $ET_orig;
						}
					}

					// Split list column into two lines.
					$pfb_match[1] = "{$pfb_query[1]}";
					$pfb_match[2] = "{$pfb_query[0]}";

					// Remove Suppression Icon for 'no match' and all subnets except for '/32 & /24'.
					if ($pfb_query[2]) {
						$src_icons = $src_icons_1;
						$dst_icons = $dst_icons_1;
					} else {
						$src_icons = $src_icons_2;
						$dst_icons = $dst_icons_2;
					}
				}
			}
			else {
				$pfb_match[1] = 'Country';
				$pfb_match[2] = '';
				$src_icons = $src_icons_2;	// Remove 'suppress icon'
				$dst_icons = $dst_icons_2;
			}

			// Add []'s to IPv6 addresses and add a zero-width space as soft-break opportunity after each colon if we have an IPv6 address (from Snort)
			if ($fields[4] == '6') {
				$fields[97] = '[' . str_replace(':', ':&#8203;', $fields[7]) . ']';
				$fields[98] = '[' . str_replace(':', ':&#8203;', $fields[8]) . ']';
			}
			else {
				$fields[97] = $fields[7];
				$fields[98] = $fields[8];
			}

			// Truncate long list names
			$pfb_matchtitle = "Country Block rules cannot be Suppressed.\n\n To allow a particular Country IP, either remove the particular Country or add the host\nto a Permit Alias in the Firewall tab.\n\nIf the IP is not listed beside the list, this means that the block is a /32 entry.\nOnly /32 or /24 CIDR hosts can be suppressed.\n\nIf (Duplication) Checking is not enabled. You may see /24 and /32 CIDR blocks for a given blocked host";

			if (strlen($pfb_match[1]) >= 17) {
				$pfb_matchtitle = $pfb_match[1];
				$pfb_match[1]	= substr($pfb_match[1], 0, 16) . '...';
			}

			print ("<tr>
				<td>{$fields[99]}</td>
				<td><small>{$fields[2]}</small></td>
				<td>{$rule}</td>
				<td><small>{$fields[6]}</small></td>
				<td>{$src_icons}</td>
				<td>{$fields[97]}{$srcport}<br /><small>{$hostname['src']}</small></td>
				<td>{$dst_icons}</td>
				<td>{$fields[98]}{$dstport}<br /><small>{$hostname['dst']}</small></td>
				<td>{$country}</td>
				<td title=\"{$pfb_matchtitle}\">{$pfb_match[1]}<br /><small>{$pfb_match[2]}</small></td></tr>");
			$counter++;
			if ($rtype == 'block') {
				$resolvecounter = $counter;
			}
		}
	}
}

		// Print final table info
			$msg = '';
			if ($pfbentries != $counter) {
				$msg = ' - Insufficient Firewall Alerts found.';
			}
			if ($type == 'DNSBL') {
				$colspan = "colspan='7'";
			} else {
				$colspan = "colspan='10'";
			}

			print ("<td {$colspan} style='font-size:10px; background-color: #F0F0F0;' >Found {$counter} Alert Entries {$msg}</td>");
			$counter = 0; $msg = '';
?>
			</tbody>
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
	<div class="alert alert-info clearfix" role="alert"><div class="pull-left">
		<dl class="dl-horizontal responsive">
			<dt><?=gettext('Icon')?></dt>
				<dd><?=gettext('Legend')?></dd>
			<dt><i class="fa fa-info">&nbsp;</i></dt>
				<dd><?=gettext('Links to Threat Source lookups');?></dd>
			<dt><i class="fa fa-plus"></i></dt>
				<dd><?=gettext('Whitelist a IP/Domain');?></dd>
			<dt><i class="fa fa-plus-circle"></i></dt>
				<dd><?=gettext('Whitelist a TLD Domain');?></dd>
			<dt><i class="fa fa-plus-square-o"></i></dt>
				<dd><?=gettext('Domain is already Whitelisted');?></dd>
			<dt><i class="fa fa-minus-square-o"></i></dt>
				<dd><?=gettext('Domain is already Whitelisted (Custom Whitelist entry prefixed by a \'Dot\')');?></dd>
			<dt><i class="fa fa-hand-stop-o"></i></dt>
				<dd><?=gettext('Domain is blocked by a whole TLD');?></dd>
		</dl>
	</div></div>
</div>

<?php include('foot.inc');?>
<script type="text/javascript">
//<![CDATA[

// Auto-resolve of alerted hostnames
function findhostnames(counter) {
	getip = $('#gethostname_' + counter).attr('name');
	geturl = "/pfblockerng/pfblockerng_alerts_ar.php";
	$.get( geturl, { "getpfhostname": getip } )
	.done(function( data ) {
			$('#gethostname_' + counter).prop('title' , data );
			var str = data;
			if(str.length > 32) str = str.substring(0,29)+"...";
			$('#gethostname_' + counter).html( str );
		}
	)
}

var alertlines = "<?=$resolvecounter?>";
var autoresolve = "<?=$hostlookup?>";
if ( autoresolve == 'on' ) {
	for (alertcount = 0; alertcount < alertlines; alertcount++) {
		setTimeout(findhostnames(alertcount), 30);
	}
}

function dnsbl_whitelist() {
	if (domain && table) {
		$('#addsuppressdom').val('true');
		$('form').submit();
	}
}

function add_description() {

	$('<div></div>').appendTo('body')
	.html('<div><h6>Do you want to add a description for this Whitelist ?</h6></div>')
	.dialog({
		modal: true,
		autoOpen: true,
		resizable: false,
		closeOnEscape: true,
		width: 'auto',
		title: 'Domain Whitelist description:',
		position: { my: 'top', at: 'top' },
		buttons: {
			Yes: function () {
				var description = prompt("Please enter Whitelist description");
				$('#descr').val(description);
				$(this).dialog("close");
				dnsbl_whitelist();
			},
			No: function () {
				$(this).dialog("close");
				dnsbl_whitelist();
			},
			'Cancel Whitelist': function (event, ui) {
				$(this).dialog("close");
			}
		}
	}).css('background-color','#ffd700');
	$("div[role=dialog]").find('button').addClass('btn-info btn-xs');
}


events.push(function() {

	$('[id^=DNSBLSUP]').click(function(event) {
		if (confirm(event.target.title)) {

			$('meta[http-equiv=refresh]').remove();
			var domaintable = this.id.replace("DNSBLSUP", "");
			var arr = domaintable.split('|');	// Split domaintable into (Domain/Table)
			$('#domain').val(arr[0]);
			$('#table').val(arr[1]);

			$('<div></div>').appendTo('body')
			.html('<div><h6>Do you wish to Whitelist *ALL* Sub-Domains of [ ' + arr[0] + ' ] ?</h6></div>')
			.dialog({
				modal: true,
				autoOpen: true,
				resizable: false,
				closeOnEscape: true,
				width: 'auto',
				title: 'Domain Whitelisting:',
				position: { my: 'top', at: 'top' },
				buttons: {
					Yes: function () {
						$('#dnsbl_supp_type').val(true);
						$(this).dialog("close");
						add_description();
					},
					No: function () {
						$('#dnsbl_supp_type').val(false);
						$(this).dialog("close");
						add_description();
					}
				}
			}).css('background-color','#ffd700');
			$("div[role=dialog]").find('button').addClass('btn-info btn-xs');
		}
	});

	$('[id^=PFBIPSUP]').click(function(event) {
		if (confirm(event.target.title)) {
			$('meta[http-equiv=refresh]').remove();
			var iprule = this.id.replace("PFBIPSUP", "");
			var arr = iprule.split('|');	// Split iprule into (IP/Rulename)
			$('#ip').val(arr[0]);
			$('#table').val(arr[1]);

			var description = prompt("Please enter Suppression description");
			$('#descr').val(description);

			if (description.value != "") {
				var cidr = prompt("Please enter CIDR [ 32 or 24 CIDR only supported ]","32");
				$('#cidr').val(cidr);

				if (arr[0] && arr[1] && description && cidr) {
					$('#addsuppress').val('true');
					$('form').submit();
				}
			}
		}
	});
});

//]]>
</script>
