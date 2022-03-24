<?php
/*
 * pfblockerng_alerts.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2022 BBcan177@gmail.com
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
$aglobal_array = array(	'pfbunicnt' => 200, 'pfbdenycnt' => 25, 'pfbpermitcnt' => 25, 'pfbmatchcnt' => 25,
			'pfbdnscnt' => 25, 'pfbdnsreplycnt' => 200,
			'ipfilterlimitentries' => 100, 'dnsblfilterlimitentries' => 100, 'dnsfilterlimitentries' => 100); 

init_config_arr(array('installedpackages', 'pfblockerngglobal'));
$pfb['aglobal'] = &$config['installedpackages']['pfblockerngglobal'];

$alertrefresh	= $pfb['aglobal']['alertrefresh']	!= ''	? $pfb['aglobal']['alertrefresh']	: 'on';
$pfbpageload	= $pfb['aglobal']['pfbpageload']	!= ''	? $pfb['aglobal']['pfbpageload']	: 'unified';
$pfbmaxtable	= $pfb['aglobal']['pfbmaxtable']	!= ''	? $pfb['aglobal']['pfbmaxtable']	: '1000';
$pfbreplytypes	= explode(',', $pfb['aglobal']['pfbreplytypes'])?: array();
$pfbreplyrec	= explode(',', $pfb['aglobal']['pfbreplyrec'])	?: array();

// Unified Log - Light Theme
$pfb['uniblock']	= $pfb['aglobal']['uniblock']		?: '#FFF9C4';
$pfb['unipermit']	= $pfb['aglobal']['unipermit']		?: '#80CBC4';
$pfb['unimatch']	= $pfb['aglobal']['unimatch']		?: '#B3E5FC';
$pfb['unidnsbl']	= $pfb['aglobal']['unidnsbl']		?: '#EF9A9A';
$pfb['unireply']	= $pfb['aglobal']['unireply']		?: '#E8E8E8';

// Unified Log - Dark Theme
$pfb['uniblock2']	= $pfb['aglobal']['uniblock2']		?: '#83791D';
$pfb['unipermit2']	= $pfb['aglobal']['unipermit2']		?: '#3B8780';	
$pfb['unimatch2']	= $pfb['aglobal']['unimatch2']		?: '#42809D';
$pfb['unidnsbl2']	= $pfb['aglobal']['unidnsbl2']		?: '#E84E4E';
$pfb['unireply2']	= $pfb['aglobal']['unireply2']		?: '#54585E';

$pfbchartcnt	= $pfb['aglobal']['pfbchartcnt']		?: '24';
$pfbchartstyle	= $pfb['aglobal']['pfbchartstyle']		?: 'twotone';
$pfbchart1	= $pfb['aglobal']['pfbchart1']			?: '#0C6197';
$pfbchart2	= $pfb['aglobal']['pfbchart2']			?: '#7A7A7A';
$pfbblockstat	= explode(',', $pfb['aglobal']['pfbblockstat']) ?: array();
$pfbpermitstat	= explode(',', $pfb['aglobal']['pfbpermitstat'])?: array();
$pfbmatchstat	= explode(',', $pfb['aglobal']['pfbmatchstat'])	?: array();
$pfbdnsblstat	= explode(',', $pfb['aglobal']['pfbdnsblstat'])	?: array();
$pfbdnsblreplystat = explode(',', $pfb['aglobal']['pfbdnsblreplystat']) ?: array();

foreach ($aglobal_array as $type => $value) {
	${"$type"} = $pfb['aglobal'][$type] != '' ? $pfb['aglobal'][$type] : $value;
}

$alert_view	= 'alert';
$alert_summary	= FALSE;
$active		= array('alerts' => TRUE);

if (isset($_GET) && isset($_GET['view']) || isset($_REQUEST) && isset($_REQUEST['alert_view'])) {
	switch($_GET['view'] != '' ? $_GET['view'] : $_REQUEST['alert_view']) {
		case 'dnsbl_stat':
			$alert_view	= 'dnsbl_stat';
			$alert_log	= $pfb['dnslog'];
			$alert_title	= 'DNSBL Block';
			$active		= array('dnsbl' => TRUE);
			break;
		case 'dnsbl_reply_stat':
			$alert_view	= 'dnsbl_reply_stat';
			$alert_log	= $pfb['dnsreplylog'];
			$alert_title	= 'DNS Reply Stats';
			$active		= array('dnsbl_reply_stat' => TRUE);
			break;
		case 'ip_block_stat':
			$alert_view	= 'ip_block_stat';
			$alert_log	= $pfb['ip_blocklog'];
			$alert_title	= 'IP Block';
			$active		= array('ip_block' => TRUE);
			break;
		case 'ip_permit_stat':
			$alert_view	= 'ip_permit_stat';
			$alert_log	= $pfb['ip_permitlog'];
			$alert_title	= 'IP Permit';
			$active		= array('ip_permit' => TRUE);
			break;
		case 'ip_match_stat':
			$alert_view	= 'ip_match_stat';
			$alert_log	= $pfb['ip_matchlog'];
			$alert_title	= 'IP Match';
			$active		= array('ip_match' => TRUE);
			break;
		case 'reply':
			$alert_view	= 'reply';
			$alert_log	= $pfb['dnsreplylog'];
			$alert_title	= 'DNS Reply';
			$active		= array('reply' => TRUE);
			break;
		case 'unified':
			$alert_view	= 'unified';
			$alert_log	= $pfb['unilog'];
			$alert_title	= 'Unified Logs';
			$active		= array('unified' => TRUE);
			break;
		default:
			$alert_view	= 'alert';
			break;
	}

	if (!in_array($alert_view, array('reply', 'unified', 'alert'))) {
		$alert_summary = TRUE;
	}
}

// Collect all Whitelist/Suppression/Permit/Exclusion customlists
if (!$alert_summary) {

	$clists = array();
	foreach (array('ipwhitelist4' => 4, 'ipwhitelist6' => 6, 'dnsbl' => 'dnsbl') as $type => $vtype) {
		$c_config = $clists[$type] = array();

		if ($vtype == 'dnsbl') {
			$c_config = $config['installedpackages']['pfblockerngdnsbl'];
		} else {
			$c_config = $config['installedpackages']['pfblockernglistsv' . $vtype];
		}

		if (isset($c_config) &&
		    !empty($c_config['config'])) {

			foreach ($c_config['config'] as $row => $data) {
				if (strpos($data['action'], 'Permit') !== FALSE || $data['action'] == 'unbound') {

					if ($type == 'dnsbl') {
						$lname = "DNSBL_{$data['aliasname']}";
						$clists[$type][$lname]['base64'] = &$config['installedpackages']['pfblockerngdnsbl']['config'][$row]['custom'];

						// Collect Global DNSBL Logging type, or Group logging setting
						$g_log = $config['installedpackages']['pfblockerngdnsblsettings']['config'][0]['global_log'] ?: '';
						if (empty($g_log)) {
							$d_log = $config['installedpackages']['pfblockerngdnsbl']['config'][$row]['logging'];
						} else {
							$d_log = $g_log;
						}

						if ($d_log == 'disabled_log') {
							$d_type = '0';
						} elseif ($d_log == 'enabled') {
							$d_type = '1';
						} else {
							$d_type = '2';
						}
						$clists[$type][$lname]['log'] = $d_type;
					} else {
						$lname = "pfB_{$data['aliasname']}_v{$vtype}";
						$clists[$type][$lname]['base64'] = &$config['installedpackages']['pfblockernglistsv' . $vtype]['config'][$row]['custom'];
					}
					$clists[$type][$lname]['data']	= array();

					$clists[$type]['options'][] = $lname;	// List of all Permit Aliases/DNSBL Customlists

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
			if ($type == 'dnsbl') {
				$clists[$type]['options'][] = "Create new DNSBL Group";
			} else {
				$clists[$type]['options'][] = "Create new pfB_Whitelist_v{$vtype}";
			}
		}
	}

	init_config_arr(array('installedpackages', 'pfblockerngipsettings', 'config', 0));
	init_config_arr(array('installedpackages', 'pfblockerngdnsblsettings', 'config', 0));

	$config['installedpackages']['pfblockerngipsettings']['config'][0]['v4suppression'] = 
		$config['installedpackages']['pfblockerngipsettings']['config'][0]['v4suppression'] ?: '';

	$config['installedpackages']['pfblockerngdnsblsettings']['config'][0]['suppression'] =
		$config['installedpackages']['pfblockerngdnsblsettings']['config'][0]['suppression'] ?: '';

	$config['installedpackages']['pfblockerngdnsblsettings']['config'][0]['tldexclusion'] =
		$config['installedpackages']['pfblockerngdnsblsettings']['config'][0]['tldexclusion'] ?: '';

	foreach (array('ipsuppression', 'dnsblwhitelist', 'tldexclusion') as $key => $type) {

		if (!is_array($clists[$type])) {
			$clists[$type] = array();
		}

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
			$filterfieldsarray[0][13]	= pfb_filter($_REQUEST['filterip'], 1);
			$pfbdnscnt			= 0;
		}
		else {
			$filterfieldsarray[1][13]	= pfb_filter($_REQUEST['filterdnsbl'], 1);
			$pfbdenycnt			= $pfbpermitcnt = $pfbmatchcnt = 0;
		}
		$pfb['filterlogentries']		= TRUE;
	}
	else {
		$pfb['filterlogentries']		= FALSE;
	}

	// Re-enable any Alert 'filter settings' on page refresh
	if (isset($_REQUEST['refresh'])) {
		$refresharr = unserialize(urldecode($_REQUEST['refresh']));
		if (isset($refresharr)) {
			foreach ($refresharr as $id => $row) {
				foreach ($row as $key => $type) {
					if (is_int($key)) {
						$filterfieldsarray[$id][$key] = pfb_filter($type, 1);
					}
				}
			}
		}
		$pfb['filterlogentries']	= TRUE;
	}
}

if (isset($_POST) && !empty($_POST)) {

	// Save Alerts tab customizations
	if (isset($_POST['save'])) {

		$pfb['aglobal']['alertrefresh']	= $_POST['alertrefresh']			?: 'off';
		$pfb['aglobal']['pfbextdns']	= $_POST['pfbextdns']				?: '8.8.8.8';
		$pfb['aglobal']['pfbreplytypes']= implode(',', (array)$_POST['pfbreplytypes'])	?: '';
		$pfb['aglobal']['pfbreplyrec']	= implode(',', (array)$_POST['pfbreplyrec'])	?: '';

		// Unified Log - Light Theme
		$pfb['aglobal']['uniblock']	= $_POST['uniblock']				?: '#FFF9C4';
		$pfb['aglobal']['unipermit']	= $_POST['unipermit']				?: '#80CBC4';
		$pfb['aglobal']['unimatch']	= $_POST['unimatch']				?: '#B3E5FC';
		$pfb['aglobal']['unidnsbl']	= $_POST['unidnsbl']				?: '#EF9A9A';
		$pfb['aglobal']['unireply']	= $_POST['unireply']				?: '#E8E8E8';

		// Unified Log - Dark Theme
		$pfb['aglobal']['uniblock2']	= $_POST['uniblock2']				?: '#83791D';
		$pfb['aglobal']['unipermit2']	= $_POST['unipermit2']				?: '#3B8780';
		$pfb['aglobal']['unimatch2']	= $_POST['unimatch2']				?: '#42809D';
		$pfb['aglobal']['unidnsbl2']	= $_POST['unidnsbl2']				?: '#E84E4E';
		$pfb['aglobal']['unireply2']	= $_POST['unireply2']				?: '#54585E';


		$pfb['aglobal']['pfbchartcnt']	= $_POST['pfbchartcnt']				?: '24';
		$pfb['aglobal']['pfbchartstyle']= $_POST['pfbchartstyle']			?: 'twotone';
		$pfb['aglobal']['pfbchart1']	= $_POST['pfbchart1']				?: '#0C6197';
		$pfb['aglobal']['pfbchart2']	= $_POST['pfbchart2']				?: '#7A7A7A';
		$pfb['aglobal']['pfbpageload']	= $_POST['pfbpageload']				?: 'unified';
		$pfb['aglobal']['pfbmaxtable']	= $_POST['pfbmaxtable']				?: '1000';
		$pfb['aglobal']['pfbblockstat']	= implode(',', (array)$_POST['pfbblockstat'])	?: '';
		$pfb['aglobal']['pfbpermitstat']= implode(',', (array)$_POST['pfbpermitstat'])	?: '';
		$pfb['aglobal']['pfbmatchstat']	= implode(',', (array)$_POST['pfbmatchstat'])	?: '';
		$pfb['aglobal']['pfbdnsblstat']	= implode(',', (array)$_POST['pfbdnsblstat'])	?: '';
		$pfb['aglobal']['pfbdnsblreplystat'] = implode(',', (array)$_POST['pfbdnsblreplystat'])   ?: '';

		foreach ($aglobal_array as $type => $value) {
			if (ctype_digit($_POST[$type])) {
				$pfb['aglobal'][$type] = $_POST[$type];
			}
		}

		// Remove obsolete XML tag
		if (isset($pfb['aglobal']['hostlookup'])) {
			unset($pfb['aglobal']['hostlookup']);
		}

		$pageview = htmlspecialchars(trim(strstr($_POST['save'], ' ', FALSE)));
		write_config('pfBlockerNG: Update ALERT tab settings.');
		header("Location: /pfblockerng/pfblockerng_alerts.php?view={$pageview}");
		exit;
	}

	$filter_type = array();
	foreach ($_POST as $key => $post) {
		if (!empty($post) && strpos($key, 'filterlogentries_') !== FALSE) {
			$f_type = substr(substr($key, strrpos($key, '_') + 1), 0, 2);
			if ($f_type != 'cl' && $f_type != 'su') {
				$filter_type[$f_type] = '';
			}
		}
	}

	// Collect 'Filter selection' from 'Alert Statistics' Filter action and convert to existing filter fields
	if (!isset($_POST['filterlogentries_submit'])) {

		$f_value = key($filter_type);
		if (!empty($f_value)) {
			$ftypes = array();
			switch ($f_value) {
				case 'ip':
					$ftypes = array('ipdate' => 'ipdate', 'ipinterface' => 'ipint', 'ipprotocol' => 'ipproto', 'ipsrcipin' => 'ipsrcip',
							'ipsrcipout' => 'ipsrcip', 'ipdstipin' => 'ipdstip', 'ipdstipout' => 'ipdstip',
							'ipsrcport' => 'ipsrcport', 'ipdstport' => 'ipdstport', 'ipdirection' => '', 'ipgeoip' => 'ipgeoip',
							'ipaliasname' => 'ipalias', 'ipfeed' => 'ipfeed', 'ipasn' => 'ipasn' );
					break;
				case 'dn':
				case 'py':
					$ftypes = array('dnsblwebtype' => 'dnsbltype', 'dnsbldate' => 'dnsbldate', 'dnsbldatehr' => 'dnsbldate',
							'dnsbldatehrmin' => 'dnsbldate', 'dnsbldomain' => 'dnsbldomain', 'dnsbltld' => 'dnsbldomain',
							'dnsblip' => 'dnsblsrcip', 'dnsblagent' => 'dnsbltype', 'dnsblmode' => 'dnsblmode',
							'dnsblevald' => 'dnsbldomain', 'dnsblfeed' => 'dnsblfeed', 'dnsblgpblock' => 'dnsblgroup',
							'dnsblgptotal' => 'dnsblgroup', 'dnsbltype' => 'dnsbltype' );
					break;
				case 're':
					$ftypes = array('replydate' => 'replydate', 'replytype' => 'replytype', 'replyorec' => 'replyorec',
							'replyrec' => 'replyrec', 'replyttl' => 'replyttl', 'replygeoip' => 'replygeoip',
							'replydomain' => 'replydomain', 'replytld' => 'replydomain', 'replytld2' => 'replydomain',
							'replytld3' => 'replydomain', 'replydstip' => 'replydstip', 'replysrcip' => 'replysrcip',
							'replysrcipd' => 'replydomain');
			}

			foreach ($ftypes as $submit_type => $final_type) {
				if (isset($_POST['filterlogentries_submit_' . $submit_type]) && !empty($_POST['filterlogentries_submit_' . $submit_type])) {
					$final_type = $ftypes[$submit_type];

					// Split SRC/DST In/Outbound field into two filter fields (IP/GeoIP)
					if ($submit_type == 'replysrcipd') {
						$data = explode(',', $_POST['filterlogentries_submit_' . $submit_type]);
						$_POST['filterlogentries_' . $final_type]	= pfb_filter($data[0], 1);
						$_POST['filterlogentries_replysrcip']		= pfb_filter($data[1], 1);
					}
					elseif ($submit_type == 'replydstipd') {
						$data = explode(',', $_POST['filterlogentries_submit_' . $submit_type]);
						$_POST['filterlogentries_' . $final_type]	= pfb_filter($data[0], 1);
						$_POST['filterlogentries_replydstip']		= pfb_filter($data[1], 1);
					}
					elseif (strpos($submit_type, 'ipsrcip') !== FALSE || strpos($submit_type, 'ipdstip') !== FALSE) {
						$data = explode(',', $_POST['filterlogentries_submit_' . $submit_type]);
						$_POST['filterlogentries_' . $final_type]	= pfb_filter($data[0], 1);
						$_POST['filterlogentries_ipgeoip']		= pfb_filter($data[1], 1);
					}
					else {
						$_POST['filterlogentries_' . $final_type] = pfb_filter($_POST['filterlogentries_submit_' . $submit_type], 1);
					}

					// Apply POST setting
					$_POST['filterlogentries_submit'] = 'Apply Filter';
				}
			}
		}
	}

	// Filter Alerts based on user defined 'filter settings'
	if (isset($_POST['filterlogentries_submit']) && $_POST['filterlogentries_submit'] == 'Apply Filter' && !empty($filter_type)) {

		$pfb['filterlogentries'] = TRUE;
		$filterfieldsarray	= array();
		$filterfieldsarray[0]	= array();
		$filterfieldsarray[1]	= array();
		$filterfieldsarray[2]	= array();

		$f_arr = array();
		foreach ($filter_type as $ftype => $value) {
			switch ($ftype) {
				case 'ip':
					$f_arr = array( 0 => 'iprule',
							2 => 'ipint',
							6 => 'ipproto',
							7 => 'ipsrcip',
							8 => 'ipdstip',
							9 => 'ipsrcport',
							10 => 'ipdstport',
							12 => 'ipgeoip',
							13 => 'ipalias',
							15 => 'ipfeed',
							16 => 'ipdsthostname',
							17 => 'ipsrchostname',
							18 => 'ipasn',
							99 => 'ipdate');
					break;
				case 'dn':
				case 'py':
					$f_arr = array( 2 => 'dnsblint',
							7 => 'dnsblsrcip',
							8 => 'dnsbldomain',
							13 => 'dnsblgroup',
							15 => 'dnsblfeed',
							17 => 'dnsblsrchostname',
							19 => 'dnsbltype',
							20 => 'dnsblmode',
							99 => 'dnsbldate');
					break;
				case 're':
					$f_arr = array( 81 => 'replytype',
							82 => 'replyorec',
							83 => 'replyrec',
							84 => 'replyttl',
							85 => 'replydomain',
							86 => 'replysrcip',
							87 => 'replydstip',
							88 => 'replygeoip',
							89 => 'replydate');
					break;
			}

			foreach ($f_arr as $key => $atype) {
				$atype = pfb_filter($_POST['filterlogentries_' . "{$atype}"], 1);
				if ($key == 6) {
					$atype = strtolower("{$atype}");
				}

				switch ($ftype) {
					case 'ip':
						$filterfieldsarray[0][$key] = $atype ?: NULL;
						break;
					case 'dn':
					case 'py':
						$filterfieldsarray[1][$key] = $atype ?: NULL;
						break;
					case 're':
						$filterfieldsarray[2][$key] = $atype ?: NULL;
						break;
				}
			}
		}

		// Remove blank entries in Filter Fields Array
		$filterfieldsarray[0]	= array_filter($filterfieldsarray[0]);
		$filterfieldsarray[1]	= array_filter($filterfieldsarray[1]);
		$filterfieldsarray[2]	= array_filter($filterfieldsarray[2]);
		$filterfieldsarray	= array_filter($filterfieldsarray);
	}

	// Clear Filter Alerts
	if (isset($_POST['filterlogentries_clear']) && !empty($_POST['filterlogentries_clear'])) {
		$pfb['filterlogentries'] = FALSE;
		$filterfieldsarray = array();
	}



	// Add an IPv4 (/32 or /24 only) to the suppression customlist
	elseif (isset($_POST['addsuppress']) && !empty($_POST['addsuppress'])) {

		$cidr = '';
		if ($_POST['cidr'] == '32' || $_POST['cidr'] == '24') {
			$cidr = $_POST['cidr'];
		}
		$ip	= is_ipaddrv4($_POST['ip']) ? $_POST['ip'] : '';
		$table	= pfb_filter($_POST['table'], 1);
		$descr	= pfb_filter($_POST['descr'], 1);

		// If IP is not valid or CIDR field is empty, exit
		if (empty($ip) || empty($cidr)) {
			$savemsg = gettext('Cannot Suppress: IPv4 not valid or CIDR value missing');
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}

		$savemsg1 = "Host IP address {$ip}";
		$ix = ip_explode($ip);	// Explode IP into evaluation strings
		if ($cidr == '32') {
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
			$cidr = '24';
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
			if ($cidr == '24') {
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

	// Add Domain to DNSBL Customlist
	elseif (isset($_POST['dnsbl_add']) && !empty($_POST['dnsbl_add'])) {

		$domain	= pfb_filter($_POST['domain'], 1);
		$list	= pfb_filter($_POST['dnsbl_customlist'], 1);

		// If Domain or customlist field is empty, exit.
		if (empty($domain) || empty($list)) {
			$savemsg = gettext('Cannot Add domain to DNSBL Group customlist - Domain name or customlist value missing');
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}

		$descr	= pfb_filter($_POST['descr'], 1);
		$group	= preg_replace('/DNSBL_/', '', $list, 1) . '_custom';

		// Collect Global DNSBL Logging type, or Group logging setting
		$dnsbl_add_log_type = $clists['dnsbl'][$list]['log'];
		if (!in_array($dnsbl_add_log_type, array('0', '1', '2'))) {
			$dnsbl_add_log_type = '1';
		}
	
		if ($_POST['dnsbl_wildcard'] == 'true') {
			@file_put_contents($pfb['unbound_py_zone'], ",{$domain},,{$dnsbl_add_log_type},{$group},{$list}\n", FILE_APPEND);
		} else {
			@file_put_contents($pfb['unbound_py_data'], ",{$domain},,{$dnsbl_add_log_type},{$group},{$list}\n", FILE_APPEND);
		}

		$savemsg = gettext(" Added domain [ {$domain} ] to the DNSBL Group [ $list ] customlist. You may need to flush your OS/Browser DNS Cache!");

		// Save changes
		if (!isset($clists['dnsbl'][$list]['data'][$domain])) {
			$data = '';
			if (isset($clists['dnsbl'][$list]) && is_array($clists['dnsbl'][$list]['data'])) {
				foreach ($clists['dnsbl'][$list]['data'] as $line) {
					$data .= "{$line}";
				}
			}

			if (!empty($descr)) {
				$data .= "{$domain} # {$descr}\r\n";
			} else {
				$data .= "{$domain}\r\n";
			}
			$clists['dnsbl'][$list]['base64'] = base64_encode($data);
			write_config("pfBlockerNG: Added [ {$domain} ] to DNSBL Group [ {$list} ] customlist");
			pfb_reload_unbound('enabled', FALSE, TRUE);
		}
		else {
			$savemsg = gettext("Domain [ {$domain} ] already exists in DNSBL Group [ $list ] customlist");
		}
		$return_page = pfb_filter($_POST['alert_view'], 1);
		header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}&view={$return_page}");
		exit;
	}

	// Add Domain/CNAME(s) to the DNSBL Whitelist customlist or TLD Exclusion customlist
	elseif (isset($_POST['addwhitelistdom']) && !empty($_POST['addwhitelistdom'])) {

		$domain		= pfb_filter($_POST['domain'], 1);
		$table		= pfb_filter($_POST['table'], 1);

		// If Domain or Table field is empty, exit.
		if (empty($domain) || empty($table)) {
			$savemsg = gettext('Cannot Whitelist - Domain name or DNSBL Table value missing');
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}

		$descr		= pfb_filter($_POST['descr'], 1);

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
				if ($pfb['dnsbl_py_blacklist']) {
					$dnsbl_remove = ".{$domain},,\n,{$domain},,\n";
				} else {
					$dnsbl_remove = ".{$domain} 60\n\"{$domain} 60\n";
				}
			} else {
				if ($pfb['dnsbl_py_blacklist']) {
					$dnsbl_remove = ",{$domain},,\n,www.{$domain},,\n";
				} else {
					$dnsbl_remove = "\"{$domain} 60\n\"www.{$domain} 60\n";
				}
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
				$removed	= "{$domain} | ";

				$cnt = (count($cname_list) -1);
				foreach ($cname_list as $key => $cname) {
					$removed .= "{$cname} | ";

					if ($pfb['dnsbl_py_blacklist']) {
						$dnsbl_remove .= ",{$cname},,\n";
					} else {
						$dnsbl_remove .= "\"{$cname} 60\n";
					}

					if ($wildcard) {
						$whitelist	.= '.';

						if ($pfb['dnsbl_py_blacklist']) {
							$dnsbl_remove .= ".{$cname},,\n";
						} else {
							$dnsbl_remove .= ".{$cname} 60\n";
						}
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
				if (isset($clists['dnsblwhitelist']) && is_array($clists['dnsblwhitelist']['data'])) {
					foreach ($clists['dnsblwhitelist']['data'] as $line) {
						$data .= "{$line}";
					}
				}
				$data .= "{$whitelist}\r\n";
				$clists['dnsblwhitelist']['base64'] = base64_encode($data);
				write_config("pfBlockerNG: Added [ {$domain} ] to DNSBL Whitelist");
			}

			// Create tempfile for DNSBL Whitelisting
			$tmp = tempnam('/tmp', 'dnsbl_alert_');

			// Save DNSBL Whitelist file of Domain/CNAME(s)
			@file_put_contents("{$tmp}.adup", $dnsbl_remove, LOCK_EX);

			if (file_exists("{$tmp}.adup") && filesize("{$tmp}.adup") > 0) {
				if ($pfb['dnsbl_py_blacklist']) {
					exec("{$pfb['grep']} -vF -f {$tmp}.adup {$pfb['unbound_py_data']} > {$tmp}.tmp; mv -f {$tmp}.tmp {$pfb['unbound_py_data']}");
					exec("{$pfb['grep']} -vF -f {$tmp}.adup {$pfb['unbound_py_zone']} > {$tmp}.tmp; mv -f {$tmp}.tmp {$pfb['unbound_py_zone']}");
					pfb_unbound_python_whitelist('alerts');
					pfb_reload_unbound('enabled', FALSE);
				} else {
					// Collect all matching whitelisted Domain/CNAME(s)
					exec("{$pfb['grep']} -F -f {$tmp}.adup {$pfb['dnsbl_file']}.conf > {$tmp}.supp 2>&1");

					// Remove Whitelisted Domain from Unbound database
					exec("{$pfb['grep']} -vF -f {$tmp}.adup {$pfb['dnsbl_file']}.conf > {$tmp}.tmp; mv -f {$tmp}.tmp {$pfb['dnsbl_file']}.conf");

					// Remove Whitelisted Domain from DNSBL Feed
					exec("{$pfb['grep']} -vF -f {$tmp}.adup {$pfb['dnsdir']}/{$table}.txt > {$tmp}.tmp; mv -f {$tmp}.tmp {$pfb['dnsdir']}/{$table}.txt");
				}
			}

			// Remove all Whitelisted Domain/CNAME(s) from Unbound using unbound-control
			if (!$pfb['dnsbl_py_blacklist'] && file_exists("{$tmp}.supp") && filesize("{$tmp}.supp") > 0) {

				exec("{$pfb['grep']} 'local-zone:' {$tmp}.supp | {$pfb['cut']} -d '\"' -f2 > {$tmp}.zone 2>&1");
				exec("{$pfb['grep']} '^local-data:' {$tmp}.supp | {$pfb['cut']} -d ' ' -f2 | tr -d '\"' > {$tmp}.data 2>&1");

				if (file_exists("{$tmp}.zone") && filesize("{$tmp}.zone") > 0) {
					exec("{$pfb['chroot_cmd']} local_zones_remove < {$tmp}.zone 2>&1");
				}
				if (file_exists("{$tmp}.data") && filesize("{$tmp}.data") > 0) {
					exec("{$pfb['chroot_cmd']} local_datas_remove < {$tmp}.data 2>&1");
				}
			}
			unlink_if_exists("{$tmp}*");

			// Flush any Domain/CNAME(s) entries in Unbound Resolver Cache
			exec("{$pfb['chroot_cmd']} flush {$domain} 2>&1");
			exec("{$pfb['chroot_cmd']} flush 'www.{$domain}' 2>&1");
			if (!empty($cname_list)) {
				foreach ($cname_list as $cname) {
					exec("{$pfb['chroot_cmd']} flush {$cname} 2>&1");
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
	elseif (isset($_POST['entry_delete']) && !empty($_POST['entry_delete'])) {

		$entry = pfb_filter($_POST['domain'], 1);
		if (empty($entry)) {
			$savemsg = gettext('Cannot Delete entry, value missing.');
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}

		$pfb_found = TRUE;
		$dnsbl_py_changes = FALSE;

		switch ($_POST['entry_delete']) {
			case 'delete_domain':
				$savemsg = "The Domain [ {$entry} ] has been deleted from the DNSBL Whitelist!";
				if (isset($clists['dnsblwhitelist']['data'][$entry]) ||
				    isset($clists['dnsblwhitelist']['data']['www' . $entry])) {

					if (isset($clists['dnsblwhitelist']['data'][$entry])) {
						unset($clists['dnsblwhitelist']['data'][$entry]);
					}

					if (isset($clists['dnsblwhitelist']['data']['www' . $entry])) {
						unset($clists['dnsblwhitelist']['data']['www' . $entry]);
					}

					if ($pfb['dnsbl_py_blacklist']) {
						@file_put_contents($pfb['unbound_py_data'], ",{$entry},,1,,\n", FILE_APPEND);
						$dnsbl_py_changes = TRUE;
					} else {
						exec("{$pfb['chroot_cmd']} local_data {$entry} '60 IN A {$pfb['dnsbl_vip']}' 2>&1");
					}
				}
			case 'delete_domainwildcard':
				$type = 'DNSBL Whitelist';
				if ($_POST['entry_delete'] == 'delete_domainwildcard') {
					$savemsg = "The Wildcard Domain [ .{$entry} ] has been deleted from the {$type} customlist!";
					if (isset($clists['dnsblwhitelist']['data']['.' . $entry]) ||
					    isset($clists['dnsblwhitelist']['data'][$entry])) {

						if (isset($clists['dnsblwhitelist']['data']['.' . $entry])) {
							unset($clists['dnsblwhitelist']['data']['.' . $entry]);
						}

						if (isset($clists['dnsblwhitelist']['data'][$entry])) {
							unset($clists['dnsblwhitelist']['data'][$entry]);
						}

						if ($pfb['dnsbl_py_blacklist']) {
							@file_put_contents($pfb['unbound_py_zone'], ",{$entry},,1,,\n", FILE_APPEND);
							$dnsbl_py_changes = TRUE;
						} else {
							exec("{$pfb['chroot_cmd']} local_zone {$entry} redirect 2>&1");
							exec("{$pfb['chroot_cmd']} local_data {$entry} '60 IN A {$pfb['dnsbl_vip']}' 2>&1");
						}
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
				$table	= pfb_filter($_POST['table'], 1);
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
				$table = pfb_filter($_POST['table'], 1);
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
			if ($dnsbl_py_changes) {
				pfb_unbound_python_whitelist('alerts');
				pfb_reload_unbound('enabled', FALSE);
			}
		}
		header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
		exit;
	}

	// Unlock/Lock DNSBL events
	elseif (isset($_POST['dnsbl_remove']) && !empty($_POST['dnsbl_remove'])) {

		$domain		= pfb_filter($_POST['domain'], 1);
		$dnsbl_type	= pfb_filter($_POST['dnsbl_type'], 1);

		if (!empty($dnsbl_type)) {
			if (strpos($dnsbl_type, 'TLD') !== FALSE) {
				$dnsbl_type = 'TLD';
			} elseif (strpos($dnsbl_type, 'DNSBL') !== FALSE) {
				$dnsbl_type = 'DNSBL';
			}
		}

		// If Domain or DNSBL type field is empty, exit.
		if (empty($domain) || empty($dnsbl_type)) {
			$savemsg = gettext('Cannot Lock/Unlock - Domain name or DNSBL Type value missing');
			header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
			exit;
		}

		// For DNSBL python - Lock/unlock - Collect missing Feed/Group Name
		if ($pfb['dnsbl_py_blacklist'] && $_POST['dnsbl_remove'] != 'unlock' && $dnsbl_type != 'python') {
			$log_type = '1';
			$pfb_feed = $pfb_group = 'Unknown';
			if (file_exists("{$pfb['dnsbl_unlock']}.data")) {
				$find_unlock_data = exec("{$pfb['grep']} -shm1 ',{$domain},,' {$pfb['dnsbl_unlock']}.data 2>&1");
				if (!empty($find_unlock_data)) {
					exec("{$pfb['sed']} -i '' 's/,{$domain},,.*//' {$pfb['dnsbl_unlock']}.data 2>&1");
					$u_ex		= explode(',', $find_unlock_data);
					$log_type	= $u_ex[3];
					$pfb_feed	= $u_ex[4];
					$pfb_group	= $u_ex[5];
				}
			}
		}

		// Unlock Domain
		if ($_POST['dnsbl_remove'] == 'unlock') {
			$cmd = 'local_data_remove';
			$py_file = $pfb['unbound_py_data'];
			if ($dnsbl_type == 'TLD') {
				$cmd = 'local_zone_remove';
				$py_file = $pfb['unbound_py_zone'];
			}

			if ($pfb['dnsbl_py_blacklist']) {
				if ($dnsbl_type == 'python') {
					@file_put_contents($pfb['unbound_py_wh'], "{$domain},0\n", FILE_APPEND | LOCK_EX);
				}
				else {
					$tmp = tempnam('/tmp', 'dnsbl_alert_');
					@file_put_contents("{$tmp}.adup", ",{$domain},,\n", LOCK_EX);
					exec("{$pfb['grep']} -F -f {$tmp}.adup {$py_file} >> {$pfb['dnsbl_unlock']}.data"); // Store DNSBL Feed/Group Data
					exec("{$pfb['grep']} -vF -f {$tmp}.adup {$py_file} > {$tmp}.tmp; mv -f {$tmp}.tmp {$py_file}");
					unlink_if_exists("{$tmp}*");
				}
				pfb_reload_unbound('enabled', FALSE);
			} else {
				exec("{$pfb['chroot_cmd']} {$cmd} {$domain} 2>&1");
			}

			exec("{$pfb['chroot_cmd']} flush {$domain} 2>&1");

			// Query for CNAME(s)
			exec("/usr/bin/drill {$domain} @{$pfb['extdns']} | /usr/bin/awk '/CNAME/ {sub(\"\.$\", \"\", $5); print $5;}'", $cname_list);
			if (!empty($cname_list)) {
				foreach ($cname_list as $cname) {
					exec("{$pfb['chroot_cmd']} flush {$cname} 2>&1");
				}
			}

			// Add Domain to unlock file
			pfb_unlock('unlock', 'dnsbl', $domain, $dnsbl_type, $dnsbl_unlock);
			$savemsg = "The Domain [ {$domain} ] has been temporarily Unlocked from DNSBL!";
		}

		// Lock Domain
		elseif ($_POST['dnsbl_remove'] == 'lock') {
			if ($pfb['dnsbl_py_blacklist']) {
				if ($dnsbl_type == 'python') {
					pfb_unbound_python_whitelist('alerts');
				}
				else {
					if ($dnsbl_type == 'TLD') {
						$py_file = $pfb['unbound_py_zone'];
					} else {
						$py_file = $pfb['unbound_py_data'];
					}

					@file_put_contents($py_file, ",{$domain},,{$log_type},{$pfb_feed},{$pfb_group}\n", FILE_APPEND);
				}
				pfb_reload_unbound('enabled', FALSE);
			}
			else {
				if ($dnsbl_type == 'TLD') {
					exec("{$pfb['chroot_cmd']} local_zone {$domain} redirect 2>&1");
				}
				exec("{$pfb['chroot_cmd']} local_data {$domain} '60 IN A {$pfb['dnsbl_vip']}' 2>&1");
			}

			// Remove Domain from unlock file
			pfb_unlock('lock', 'dnsbl', $domain, $dnsbl_type, $dnsbl_unlock);
			$savemsg = "The Domain [ {$domain} ] has been Locked into DNSBL!";
		}
		elseif ($_POST['dnsbl_remove'] == 'relock') {

			if ($pfb['dnsbl_py_blacklist']) {
				if ($dnsbl_type == 'python') {
					pfb_unbound_python_whitelist('alerts');
				}
				else {
					if ($dnsbl_type == 'TLD') {
						$py_file = $pfb['unbound_py_zone'];
					} else {
						$py_file = $pfb['unbound_py_data'];
					}
					@file_put_contents($py_file, ",{$domain},,{$log_type},{$pfb_feed},{$pfb_group}\n", FILE_APPEND);
				}
				pfb_reload_unbound('enabled', FALSE);
			}
			else {
				if ($dnsbl_type == 'TLD') {
					exec("{$pfb['chroot_cmd']} local_zone {$domain} redirect 2>&1");
				}
				exec("{$pfb['chroot_cmd']} local_data {$domain} '60 IN A {$pfb['dnsbl_vip']}' 2>&1");
			}

			// Add Domain to unlock file
			pfb_unlock('unlock', 'dnsbl', $domain, $dnsbl_type, $dnsbl_unlock);
			$savemsg = "The Domain [ {$domain} ] has been temporarily Re-Locked into DNSBL!";
		}
		elseif ($_POST['dnsbl_remove'] == 'reunlock') {

			if ($pfb['dnsbl_py_blacklist']) {
				if ($dnsbl_type == 'python') {
					pfb_unbound_python_whitelist('alerts');
				}
				else {
					if ($dnsbl_type == 'TLD') {
						$py_file = $pfb['unbound_py_zone'];
					} else {
						$py_file = $pfb['unbound_py_data'];
					}
					@file_put_contents($py_file, ",{$domain},,{$log_type},{$pfb_feed},{$pfb_group}\n", FILE_APPEND);
				}
				pfb_reload_unbound('enabled', FALSE);
			}
			else {
				$cmd = 'local_data_remove';
				if ($dnsbl_type == 'TLD') {
					$cmd = 'local_zone_remove';
				}
				exec("{$pfb['chroot_cmd']} {$cmd} {$domain} 2>&1");
				exec("{$pfb['chroot_cmd']} flush {$domain} 2>&1");
			}

			// Remove Domain from unlock file
			pfb_unlock('lock', 'dnsbl', $domain, $dnsbl_type, $dnsbl_unlock);
			$savemsg = "The Domain [ {$domain} ] has been Unlocked from DNSBL!";
		}
		header("Location: /pfblockerng/pfblockerng_alerts.php?savemsg={$savemsg}");
		exit;
	}

	// Unlock/Lock IP events
	elseif (isset($_POST['ip_remove']) && !empty($_POST['ip_remove'])) {

		$ip	= is_ipaddr($_POST['ip']) ? $_POST['ip'] : '';
		$table	= pfb_filter($_POST['table'], 1);

		// If IP or table field is empty, exit.
		if ((empty($ip) && !is_subnet($ip)) || empty($table)) {
			$savemsg = gettext('Cannot Lock/Unlock - IP Invalid or table missing');
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
	elseif (isset($_POST['ip_white']) && $_POST['ip_white'] == 'true') {

		$ip	= is_ipaddr($_POST['ip']) ? $_POST['ip'] : '';
		$table	= pfb_filter($_POST['table'], 1);
		$descr	= pfb_filter($_POST['descr'], 1);

		$vtype = '6';
		if (strpos($table, '_v4') !== FALSE) {
			$vtype = '4';
		}

		// If IP or table field is empty, exit.
		if (empty($ip) || empty($table)) {
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
			@file_put_contents("{$pfb['aliasdir']}/{$table}.txt", "\n{$ip}", LOCK_EX);

			if (!empty($descr)) {
				$whitelist_string = "{$ip} # {$descr}\r\n";
			} else {
				$whitelist_string = "{$ip}\r\n";
			}

			$data = '';
			if (isset($clists['ipwhitelist' . $vtype][$table]['data']) && is_array($clists['ipwhitelist' . $vtype][$table]['data'])) {
				foreach ($clists['ipwhitelist' . $vtype][$table]['data'] as $line) {
					$data .= "{$line}";
				}
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


// Array of Log Types for Alerts Filter
if ($pfb['filterlogentries']) {
	$filter_unified = array('Block', 'Permit', 'Match', 'DNSBL', 'DNSBL-python', 'DNS-reply');

	// IP/DNSBL/DNS Reply filter events
	if (isset($filterfieldsarray[0]) && isset($filterfieldsarray[1]) && isset($filterfieldsarray[2])) {
		// Filter for all Unified Log types

		$alert_view	= 'unified';
		$active		= array('unified' => TRUE);
	}

	// IP/DNSBL filter events
	elseif (isset($filterfieldsarray[0]) && isset($filterfieldsarray[1])) {
		if ($alert_view == 'reply') {
			$pfbdnscnt = 0;
		} else {
			$pfbdnsreplycnt = 0;
		}
		unset($filter_unified[5]);

		$alert_view	= 'alert';
		$active		= array('alerts' => TRUE);
	}

	// IP/DNS Reply filter events
	elseif (isset($filterfieldsarray[0]) && isset($filterfieldsarray[2])) {
		$pfbdnscnt = 0;
		unset($filter_unified[3], $filter_unified[4]);

		$alert_view	= 'unified';
		$active		= array('unified' => TRUE);
	}

	// DNSBL/DNS Reply filter events
	elseif (isset($filterfieldsarray[1]) && isset($filterfieldsarray[2])) {
		$pfbdenycnt = $pfbpermitcnt = $pfbmatchcnt = 0;
		unset($filter_unified[0], $filter_unified[1], $filter_unified[2]);

		$alert_view	= 'unified';
		$active		= array('unified' => TRUE);
	}

	// IP filter events
	elseif (isset($filterfieldsarray[0])) {
		$pfbdnscnt = $pfbdnsreplycnt = 0;
		unset($filter_unified[3], $filter_unified[4], $filter_unified[5]);

		$alert_view	= 'alert';
		$active		= array('alerts' => TRUE);
	}

	// DNSBL filter events
	elseif (isset($filterfieldsarray[1])) {
		$pfbdenycnt = $pfbpermitcnt = $pfbmatchcnt = 0;
		if ($alert_view == 'reply') {
			$pfbdnscnt = 0;
		} else {
			$pfbdnsreplycnt = 0;
		}
		unset($filter_unified[0], $filter_unified[1], $filter_unified[2], $filter_unified[5]);

		$alert_view	= 'alert';
		$active		= array('alerts' => TRUE);
	}

	// DNS Reply filter events
	elseif (isset($filterfieldsarray[2])) {
		$pfbdenycnt = $pfbpermitcnt = $pfbmatchcnt = $pfbdnscnt = 0;
		unset($filter_unified[0], $filter_unified[1], $filter_unified[2], $filter_unified[3], $filter_unified[4]);

		$alert_view	= 'reply';
		$active		= array('reply' => TRUE);
	}

	if (!empty($filter_unified)) {
		$filter_unified = array_flip($filter_unified);
	}

	// Add Unbound Mode - DNSBL Modes to Unified Filter
	if (isset($filter_unified['DNSBL'])) {
		$filter_unified['DNSBL-1x1'] = '';
		$filter_unified['DNSBL-Full'] = '';
		$filter_unified['DNSBL-HTTPS'] = '';
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

	if ($alert_view == 'dnsbl_stat') {
		$stat_info = array(	'dnsblwebtype'  => 1,
					'dnsbldate'	=> 2,
					'dnsblchart'	=> 2,
					'dnsbldatehr'	=> 2,
					'dnsbldatehrmin'=> 2,
					'dnsbldomain'	=> 3,
					'dnsbltld'	=> 3,
					'dnsblip'	=> 4,
					'dnsblagent'	=> 5,
					'dnsblmode'	=> 6,
					'dnsblevald'	=> 8,
					'dnsblfeed'	=> 9);
	}
	elseif ($alert_view == 'dnsbl_reply_stat') {
		$stat_info = array(	'replydate'	=> 2,
					'replychart'	=> 2,
					'replytype'	=> 3,
					'replyorec'	=> 4,
					'replyrec'	=> 5,
					'replyttl'	=> 6,
					'replydomain'	=> 7,
					'replytld'	=> 7,
					'replytld2'	=> 7,
					'replytld3'	=> 7,
					'replysrcipd'	=> 7,
					'replysrcip'	=> 8,
					'replydstip'	=> 9,
					'replygeoip'	=> 10);
	}
	else {
		$stat_info = array(	'ipdate'	=> 1,
					'ipchart'	=> 1,
					'ipinterface'	=> 4,
					'ipprotocol'	=> 8,
					'ipsrcipin'	=> 9,
					'ipsrcipout'	=> 9,
					'ipdstipin'	=> 10,
					'ipdstipout'	=> 10,
					'ipsrcport'	=> 11,
					'ipdstport'	=> 12,
					'ipdirection'	=> 13,
					'ipgeoip'	=> 14,
					'ipaliasname'	=> 15,
					'ipfeed'	=> 17,
					'ipasn'		=> 20);
	}

	$su_cmd		= "sort | uniq -c";
	$grep_cmd	= "{$pfb['grep']} -v";
	$sss_cmd	= "sort | uniq -c | {$pfb['sed']} 's/^ *//' | sort -nr";

	$chart_cmd = "awk '{\$1=\$1} 1' | awk -F ' ' '{print \$2 \" \" \$3 \" \(\" \$4 \"\),\" \$1}' >> /usr/local/www/pfblockerng/chart_stats.csv";

	$alert_stats = array();
	$alert_stats[$alert_view] = array();

	// Skip processing hidden Stats
	$stat_hidden = array();
	if ($alert_view == 'dnsbl_stat') {
		$stat_hidden = $pfbdnsblstat;
	} elseif ($alert_view == 'dnsbl_reply_stat') {
		$stat_hidden = $pfbdnsblreplystat;
	} elseif ($alert_view == 'ip_block_stat') {
		$stat_hidden = $pfbblockstat;
	} elseif ($alert_view == 'ip_permit_stat') {
		$stat_hidden = $pfbpermitstat;
	} elseif ($alert_view == 'ip_match_stat') {
		$stat_hidden = $pfbmatchstat;
	}
	if (!empty($stat_hidden)) {
		$stat_hidden = array_flip($stat_hidden);
	}

	foreach ($stat_info as $stat_type => $column) {
		if (isset($stat_hidden[$stat_type])) {
			continue;
		}

		if (file_exists($alert_log)) {

			$cut_cmd = "{$pfb['cut']} -d ',' -f{$column}";

			if ($alert_view != 'dnsbl_stat') {
				$unknown_msg = 'Unknown';
			} else {
				$unknown_msg = $pfb['dnsbl_py_blacklist'] ? 'DNSBL Webserver/VIP' : 'Not available for HTTPS alerts';
			}

			$agent_cmd = '';
			if ($stat_type == 'dnsblagent') {
				$agent_cmd = "cut -d '|' -f3 | ";
			}

			$stats = array();
			switch ($stat_type) {
				case 'ipdate':
				case 'dnsbldate':
				case 'replydate':
					exec("{$cut_cmd} {$alert_log} | cut -d ' ' -f1-2 | uniq -c 2>&1", $stats);
					$stats = array_reverse($stats);
					break;
				case 'dnsbldatehr':
					exec("{$cut_cmd} {$alert_log} | cut -d ':' -f1 | sort | uniq -c | sort -nr 2>&1", $stats);
					break;
				case 'dnsbldatehrmin':
					exec("{$cut_cmd} {$alert_log} | cut -d ':' -f1,2 | sort | uniq -c | sort -nr 2>&1", $stats);
					break;
				case 'dnsblchart':
				case 'replychart':
				case 'ipchart':
					exec("echo 'edate,ecount' > /usr/local/www/pfblockerng/chart_stats.csv");
					exec("{$cut_cmd} {$alert_log} | cut -d ':' -f1 | uniq -c | {$chart_cmd} 2>&1");
					break;
				case 'ipsrcipin':
					exec("{$cut_cmd},13,14,18 {$alert_log} | {$grep_cmd} ',out,' | {$sss_cmd} | {$pfb['sed']} 's/,in,/,/' 2>&1", $stats);
					break;
				case 'ipsrcipout':
					exec("{$cut_cmd},13,14 {$alert_log} | {$grep_cmd} ',in,' | {$sss_cmd} | {$pfb['sed']} 's/,out,/,/' 2>&1", $stats);
 					break;
				case 'ipdstipin':
					exec("{$cut_cmd},13,14 {$alert_log} | {$grep_cmd} ',out,' | {$sss_cmd} | {$pfb['sed']} 's/,in,/,/' 2>&1", $stats);
					break;
				case 'ipdstipout':
					exec("{$cut_cmd},13,14,18 {$alert_log} | {$grep_cmd} ',in,' | {$sss_cmd} | {$pfb['sed']} 's/,out,/,/' 2>&1", $stats);
					break;
				case 'dnsbltld':
				case 'replytld':
					exec("{$cut_cmd} {$alert_log} | awk -F. 'NF>1' | rev | cut -d '.' -f1 | rev | sort | uniq -c | sort -nr 2>&1", $stats);
					break;
				case 'replytld2':
					exec("{$cut_cmd} {$alert_log} | rev | cut -d '.' -f1,2 | awk -F. 'NF>1' | rev | sort | uniq -c | sort -nr 2>&1", $stats);
					break;
				case 'replytld3':
					exec("{$cut_cmd} {$alert_log} | rev | cut -d '.' -f1,2,3 | awk -F. 'NF>2' | rev | sort | uniq -c | sort -nr 2>&1", $stats);
					break;
				case 'ipsrcport':
					exec("{$cut_cmd} {$alert_log} | {$su_cmd} | {$pfb['awk']} -F ' ' '\$2 <= 1024 || \$2 ~ /[a-zA-Z]/' | sort -nr 2>&1", $stats);
					break;
				case 'replyttl':
					exec("{$cut_cmd} {$alert_log} | {$pfb['grep']} -v ',cache,' | {$su_cmd} | sort -nr 2>&1", $stats);
					break;
				case 'dnsbldomain':
					exec("{$cut_cmd},6 {$alert_log} | {$su_cmd} | sort -nr 2>&1", $stats);
					break;
				case 'dnsblevald':
					exec("{$cut_cmd},6 {$alert_log} | {$pfb['grep']} 'TLD' | {$su_cmd} | sort -nr 2>&1", $stats);
					break;
				case 'replysrcipd':
					exec("{$cut_cmd},8 {$alert_log} | {$su_cmd} | sort -nr 2>&1", $stats);
					break;
				default:
					exec("{$cut_cmd} {$alert_log} | {$agent_cmd} {$su_cmd} | sort -nr 2>&1", $stats);
					break;
			}

			if (!empty($stats)) {
				foreach($stats as $key => $line) {

					// Remove last column for '-' and '+' indicator
					$eol = substr($line, -2);
					if ($eol == ' -' || $eol == ' +') {
						continue;
					}

					$data = array_map('trim', explode(' ', trim($line), 2));
					$alert_stats[$alert_view][$stat_type][$data[1] ?: $unknown_msg] = $data[0] ?: 0;
				}
			}
			else {
				$alert_stats[$alert_view][$stat_type] = array();
				if ($alert_view == 'dnsbl_stat') {
					$alert_stats[$alert_view]['dnsblgptotal'] = array();
					$alert_stats[$alert_view]['dnsblgpblock'] = array();
				}
			}
			$alert_stats['count'][$alert_view] = exec("{$pfb['grep']} -c ^ {$alert_log} 2>&1") ?: 0;
		}
		else {
			$alert_stats[$alert_view][$stat_type]	= array();
			$alert_stats['count'][$alert_view]	= 0;
			if ($alert_view == 'dnsbl_stat') {
				$alert_stats[$alert_view]['dnsblgptotal'] = array();
				$alert_stats[$alert_view]['dnsblgpblock'] = array();
			}

			if ($stat_type == 'dnsblchart' || $stat_type == 'replychart' || $stat_type == 'ipchart') {
				exec("echo 'edate,ecount' > /usr/local/www/pfblockerng/chart_stats.csv");
			}
		}
	}

	// Collect DNSBL widget statistics
	if ($alert_view == 'dnsbl_stat') {
		$alert_stats[$alert_view]['dnsblgptotal'] = array();
		$alert_stats[$alert_view]['dnsblgpblock'] = array();

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
						$alert_stats[$alert_view]['dnsblgptotal'][$res['groupname']] = $res['entries'];
						$alert_stats[$alert_view]['dnsblgpblock'][$res['groupname']] = $res['counter'];
					}

					array_multisort($alert_stats[$alert_view]['dnsblgptotal'], SORT_DESC, 1);
					array_multisort($alert_stats[$alert_view]['dnsblgpblock'], SORT_DESC, 1);
				}
			}
			$db_handle->close();
			unset($db_handle);
		}
	}
}


// Function to Filter Alerts report on user defined input
function pfb_match_filter_field($flent, $fields) {

	if (isset($fields)) {
		foreach ($fields as $key => $field) {

			$not_filter = FALSE;
			if (substr($field, 0, 1) == '!') {
				$not_filter = TRUE;
				$field = substr($field, 1);
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

			// Remove 'AS' characters from ASN queries
			if ($key == 18) {
				$field_regex = str_replace('AS', '', $field_regex);
			}

			if ($not_filter) {
				if (@preg_match("/{$field_regex}/i", $flent[$key])) {
					return FALSE;
				}
			} else {
				if (!@preg_match("/{$field_regex}/i", $flent[$key])) {
					return FALSE;
				}
			}
		}
	}
	return TRUE;
}

// Function to collect DNSBL Log event details based on Blocking mode field
function dnsbl_log_details($fields) {
	global $clists;

	$isTLD = $isCNAME = $isPython = $isExclusion = FALSE;
	$pfb_python = $wt_line = '';

	if (strpos($fields[5], 'TLD') !== FALSE) {
		$isTLD		= TRUE;
	}
	if (strpos($fields[5], '_CNAME') !== FALSE) {
		$isCNAME	= TRUE;
		$pfb_python	= "&nbsp;<i class=\"fa fa-bolt\" title=\"CNAME Validation\"></i>";
	}
	if (strpos($fields[5], 'Python') !== FALSE) {
		$isPython	= TRUE;
		$pfb_python	= "&nbsp;<i class=\"fa fa-bolt\" title=\"{$fields[5]}\"></i>";
	}

	// Select blocked Domain or Evaluated Domain
	$qdomain = $fields[2];
	if ($isTLD || $isCNAME) {
		$qdomain = $fields[7];
	}

	// Determine if blocked Domain is a TLD Exclusion
	if ($isTLD && isset($clists['tldexclusion']['data'][$fields[7]])) {
		$wt_line = rtrim($clists['tldexclusion']['data'][$fields[7]], "\x00..\x1F");
		$isExclusion = TRUE;
	}

	return array($isTLD, $isCNAME, $isPython, $isExclusion, $pfb_python, $qdomain, $wt_line);
}


// Function to determine Whitelist type for DNSBL and DNS Reply
function dnsbl_whitelist_type($fields, $clists, $isExclusion, $isTLD, $qdomain) {
	global $pfb;

	$isWhitelist_found = FALSE;

	$ex_dom = $s_txt = '';
	if ($isExclusion) {
		$s_txt  = "Note:&emsp;The following Domain is in the TLD Exclusion customlist:\n\n"
			. "TLD Exclusion:&emsp;[ {$wt_line} ]\n\n"
			. "&#8226; TLD Exclusions require a Force Reload when a Domain is initially added.\n"
			. "&#8226; To remove this Domain from the TLD Exclusion customlist, Click 'OK'";

		$ex_dom = '&nbsp;<i class="fa fa-trash-o no-confirm icon-pointer icon-primary" id="DNSBLWT|'
			. 'delete_exclusion|' . $fields[7] . '" title="' . $s_txt . '"></i>';
	}

	$supp_dom = $s_txt = '';
	// Default Whitelist text for DNSBL/TLD domains
	if ($isTLD) {
		$s_txt  = "Note:&emsp;The following Domain was Wildcard blocked via TLD.\n\n"
			. "Blocked Domain:&emsp;&emsp;[ {$fields[2]} ]\n"
			. "Evaluated Domain:&emsp;&nbsp;[ {$fields[7]} ]\n\n"
			. "DNSBL Groupname:&emsp;[ {$fields[6]} ]\n"
			. "DNSBL Feedname:&emsp;&nbsp;&nbsp;[ {$fields[8]} ]\n\n";

		if ($pfb['dnsbl_py_blacklist']) {
			$s_txt .= "Whitelist [ {$fields[2]} ]\n\n"
				. "Note:&emsp;This will immediately remove the blocked Domain\n"
				. "&emsp;&emsp;&emsp;&nbsp;and associated CNAMES from DNSBL.\n" 
				. "&emsp;&emsp;&emsp;&nbsp;(CNAMES: Define the external DNS server in Alert settings\n"
				. "&emsp;&emsp;&emsp;&nbsp;&nbsp;and ensure that the Resolver has access to the External DNS server.)\n\n"
				. "Whitelisting Options:\n\n"
				. "1) Wildcard whitelist [ .{$fields[2]} ]\n"
				. "2) Whitelist only [ {$fields[2]} ]\n";
		} else {
			$s_txt .= "Whitelisting Options:\n\n"
				. "1) Wildcard whitelist [ .{$fields[7]} ]\n"
				. "&emsp;This will immediately remove the blocked Domain/CNAMES from DNSBL.\n"
				. "&emsp;(CNAMES: Define the external DNS server in Alert settings\n"
				. "&emsp;&nbsp;and ensure that the Resolver has access to the External DNS server.)\n\n"
				. "2) Add [ {$fields[7]} ] to the 'TLD Exclusion customlist'\n"
				. "&emsp;A Force Reload-DNSBL is Required!\n"
				. "&emsp;After a Reload any new blocked Domains can be Whitelisted at that time.";
		}
	}
	else {
		$s_txt = "Whitelist [ {$fields[2]} ]\n\n"
			. "Note:&emsp;This will immediately remove the blocked Domain\n"
			. "&emsp;&emsp;&emsp;&nbsp;and associated CNAMES from DNSBL.\n"
			. "&emsp;&emsp;&emsp;&nbsp;(CNAMES: Define the external DNS server in Alert settings\n"
			. "&emsp;&emsp;&emsp;&nbsp;&nbsp;and ensure that the Resolver has access to the External DNS server.)\n\n"
			. "Whitelisting Options:\n\n"
			. "1) Wildcard whitelist [ .{$fields[2]} ]\n"
			. "2) Whitelist only [ {$fields[2]} ]\n";
	}

	// Determine if Domain is blocked via TLD Blacklist
	if ($fields[5] != 'DNSBL_TLD') {

		// Remove Whitelist Icon for 'Unknown'
		if ($fields[6] != 'Unknown') {
		
			// Default - Domain not in Whitelist
			$supp_dom = '<i class="fa fa-plus icon-pointer icon-primary" id="DNSBLWT|' . 'add|'
					. $fields[7] . '|' . $fields[8] . '" title="' . $s_txt . '"></i>';
		}

		// Determine if Blocked Domain is in DNSBL Whitelist
		if (isset($clists['dnsblwhitelist']['data'][$fields[2]])) {
			$w_line = rtrim($clists['dnsblwhitelist']['data'][$fields[2]], "\x00..\x1F");
			$isWhitelist_found = TRUE;

			// Verify if the Whitelisted Domain matches the Evaluated Domain
			if ($fields[2] == $qdomain || $fields[6] == 'Unknown') {
				$s_txt = "Note:&emsp;The following Domain exists in the DNSBL Whitelist:\n\n"
					. "Whitelisted:&emsp;[ {$w_line} ]\n\n"
					. "To remove this Domain from the DNSBL Whitelist, press 'OK'";
			} else {
				$s_txt = "Note:&emsp;The following Domain exists in the DNSBL Whitelist:\n\n"
					. "Whitelisted:&emsp;[ {$w_line} ]\n\n"
					. "However it is still being Wildcard blocked by the following Domain:\n"
					. "Whitelisted:&emsp;[ {$qdomain} ]\n\n"
					. "To remove this Domain [ {$fields[2]} ] from the DNSBL Whitelist"
					. ", Click 'OK'";
			}
			$supp_dom = '<i class="fa fa-trash no-confirm icon-pointer icon-primary" id="DNSBLWT|'
					. 'delete_domain|' . $fields[2] . '" title="' . $s_txt . '"></i>';
		}

		// Determine if Blocked Domain is in DNSBL Whitelist (prefixed by a "dot" )
		elseif (!empty($clists['dnsblwhitelist']['data'])) {

			$q_wdomain = ltrim($fields[7], '.');	// Is this needed?
			$dparts	= explode('.', $q_wdomain);
			$dcnt	= count($dparts);
			for ($i=$dcnt; $i > 0; $i--) {

				$d_query = implode('.', array_slice($dparts, -$i, $i, TRUE));
				if (isset($clists['dnsblwhitelist']['data']['.' . $d_query])) {
					$w_line = rtrim($clists['dnsblwhitelist']['data']['.' . $d_query], "\x00..\x1F");
					$isWhitelist_found = TRUE;

					if ($d_query == $qdomain || $fields[6] == 'Unknown') {
						$s_txt = "Note:&emsp;The following Domain exists"
							. " in the DNSBL Whitelist:\n\n"
							. "Whitelisted:&emsp;[ {$w_line} ]\n\n"
							. "To remove this Domain from the DNSBL Whitelist,"
							. " press 'OK'";
					} else {
						$s_txt = "Note:&emsp;The following Domain exists in the"
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
							. '" title="' . $s_txt . '"></i>';
					break;
				}
			}
		}

		// Root Domain blocking all Sub-Domains and is not in whitelist and not in TLD Exclusion
		if ($isTLD && !$isWhitelist_found && !$isExclusion) {
			$supp_dom = '<i class="fa fa-plus-circle icon-pointer icon-primary" id="DNSBLWT|' . 'add|'
				. $fields[7] . '|' . $fields[8] . '|' . $fields[5] . '" title="' . $s_txt . '"></i>';
		}
	}

	// Whole TLD is blocked
	else {
		$s_txt  = "Note:&emsp;The following Domain was blocked via 'DNSBL TLD' (TLD Blacklist):\n\n"
			. "Blocked Domain:&emsp;&emsp;[ {$fields[2]} ]\n"
			. "Evaluated Domain:&emsp;&nbsp;[ {$fields[7]} ]\n\n"
			. "Add [ {$fields[2]} ] to the TLD Whitelist?";

		$supp_dom = '<i class="fa fa-hand-stop-o icon-pointer icon-primary" id="DNSBLWT|' . 'tld|'
			. $fields[2] . '|' . $fields[7] . '" title="' . $s_txt . '"></i>';
	}

	return array ($supp_dom, $ex_dom, $isWhitelist_found);
}


// Function to convert dnsbl.log -> Reports Tab
function convert_dnsbl_log($mode, $fields) {
	global $pfb, $config, $local_hosts, $dnsbl_int, $filterfieldsarray, $clists, $dnsbl_unlock, $dup, $counter,
		$pfbentries, $skipcount, $dnsblfilterlimit, $dnsblfilterlimitentries;

	if ($dnsblfilterlimit) {
		return TRUE;
	}

	/* dnsbl.log Fields Reference

		[0]	= DNSBL prefix - Python mode: 'DNSBL-python' | Unbound Mode: 'DNSBL-Full', 'DNSBL-1x1' or 'DNSBL-HTTPS'
		[1]	= Date/Timestamp
		[2]	= Domain name
		[3]	= Source IP
		[4]	= DNSBL Type - Python mode: 'Python', 'HSTS' Suffix A/AAAA | Unbound Mode: URL/Referer/URI/Agent String
		[5]	= DNSBL Mode - 'DNSBL', 'TLD', 'DNSBL TLD' Suffix A/AAAA/CNAME
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
		[19]	= DNSBL Type 
		[20]	= DNSBL Mode
		[99]	= Date/Timestamp		*/

	// Remove 'Unknown' for Agent field
	if ($fields[4] == 'Unknown') {
		$fields[4] = '';
	}

	// Determine event parameters
	list ( $isTLD, $isCNAME, $isPython, $isExclusion, $pfb_python, $qdomain, $wt_line ) = dnsbl_log_details($fields);

	// Determine Whitelist type
	list ( $supp_dom, $ex_dom, $isWhitelist_found ) = dnsbl_whitelist_type($fields, $clists, $isExclusion, $isTLD, $qdomain);

	$isMatch = TRUE;
	$p_group = $p_domain = $p_feed = $p_mode = '';

	// Collect current details about domain
	if (!$isPython && !$isWhitelist_found) {
		$domain_details = pfb_dnsbl_parse('alerts', $qdomain, '', '');
		$pfb_mode	= $domain_details['pfb_mode']	?: 'Unknown';
		$pfb_group	= $domain_details['pfb_group']	?: 'Unknown';
		$pfb_final	= $domain_details['pfb_final']	?: 'Unknown';
		$pfb_feed	= $domain_details['pfb_feed']	?: 'Unknown';

		// Determine if log entry 'Group' has changed
		if ("{$fields[6]}" != "{$pfb_group}") {
			$isMatch	= FALSE;
			$p_group	= $fields[6];
			$fields[6]	= $pfb_group;
		}

		// Determine if log entry 'Domain' has changed
		if ("{$fields[7]}" != "{$pfb_final}") {
			$isMatch	= FALSE;
			$p_domain	= $fields[7];
			$fields[7]	= $pfb_final;
		}

		// Determine if log entry 'Feed' has changed
		if ("{$fields[8]}" != "{$pfb_feed}") {
			$isMatch	= FALSE;
			$p_feed		= $fields[8];
			$fields[8]	= $pfb_feed;
		}

		// Determine if log entry 'Blocking mode' has changed
		if ($isTLD) {
			if (strpos($pfb_mode, 'TLD') === FALSE) {
				$isMatch	= FALSE;
				$p_mode		= $fields[5];
				$fields[5]	= $pfb_mode;
			}
		} else {
			if (strpos($pfb_mode, 'DNSBL') === FALSE) {
				$isMatch	= FALSE;
				$p_mode		= $fields[5];
				$fields[5]	= $pfb_mode;
			}
		}
	}

	// On failed Match verification, re-evaluate parameters
	if (!$isMatch) {
		list ( $isTLD, $isCNAME, $isPython, $isExclusion, $pfb_python, $qdomain, $wt_line ) = dnsbl_log_details($fields);
		list ( $supp_dom, $ex_dom, $isWhitelist_found ) = dnsbl_whitelist_type($fields, $clists, $isExclusion, $isTLD, $qdomain);
	}

	// Filter Field array
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
	$hostname = $local_hosts[$fields[3]] ?: '';
	if (!empty($hostname)) {
		if (strlen($hostname) >= 25) {
			$h_title	= $hostname;
			$hostname	= substr($hostname, 0, 24) . "<small>...</small>";
		}

		$pfbalertdnsbl[7]	= $fields[3];
		$pfbalertdnsbl[17]	= "<span title=\"{$h_title}\">{$hostname}</span>";
	} else {
		$pfbalertdnsbl[7]	= $fields[3];
		$pfbalertdnsbl[17]	= '';
	}

	if (!empty($p_domain)) {
		if (strpos($p_domain, 'xn--') !== FALSE) {
			$p_domain = "{$p_domain} [" . idn_to_utf8($p_domain) . "]";
		}

		if (strlen($p_domain) >= ($mode != 'unified' ? 60 : 40)) {
			$p_domain = "<s title=\"Previous Domain: {$p_domain}\">" . substr($p_domain, 0, ($mode != 'unified' ? 59 : 39))
					. "</s><small>...</small><br />";
		} else {
			$p_domain = "<s title=\"Previous Domain: {$p_domain}\">{$p_domain}</s><br />";
		}
	}

	$f2 = $fields[2];
	if (strpos($f2, 'xn--') !== FALSE) {
		$f2 = "{$f2} [" . idn_to_utf8($f2) . "]";
	}
	if (strlen($f2) >= ($mode != 'unified' ? 60 : 40)) {
		$f2 = substr($f2, 0, ($mode != 'unified' ? 59 : 39)) . "<small>...</small>";
	}

	if ($isCNAME) {
		$f7 = $fields[7];
		if (strpos($f7, 'xn--') !== FALSE) {
			$f7		= "{$f7} [" . idn_to_utf8($f7) . "]";
		}
		if (strlen($f7) >= ($mode != 'unified' ? 52 : 32)) {
			$f7		= substr($f7, 0, ($mode != 'unified' ? 51 : 31)) . "<small>...</small>";
		}
		$pfbalertdnsbl[8]	= "{$p_domain}Domain: {$f2}<br />CNAME: {$f7}";
	} else {
		$pfbalertdnsbl[8]	= "{$p_domain}{$f2}";
	}

	$f_g_title = '';

	// Add Title - Header line to Feed/Group
	if ($fields[6] == 'Unknown') {
		if ($isTLD) {
			$f_g_title = "The domain: [ {$fields[7]} ] is not currently listed in DNSBL as a TLD wildcard blocked domain.";
		} else {
			$f_g_title = 'The domain is not currently listed in DNSBL!';
		}
	}
	else {
		$f_g_title = "The Feed and Group that blocked the indicated Domain:";
	}

	if (!empty($p_feed)) {
		$f_g_title .= "&#013;Previous Feed: {$p_feed}";
		if (strlen($p_feed) >= 25) {
			$p_feed	= substr($p_feed, 0, 24) . "<small>...</small>";
		}
		$p_feed	= "<s>{$p_feed}</s><br />";
	}
	if (!empty($fields[8])) {
		$f_g_title .= "&#013;Feed: {$fields[8]}";
	}
	if (!empty($p_group)) {
		$f_g_title .= "&#013;Previous Group: {$p_group}";
		if (strlen($p_group) >= 25) {
			$p_group = substr($p_group, 0, 24) . "<small>...</small>";
		}
		$p_group = "<s>{$p_group}</s><br />";
	}
	if (!empty($fields[6])) {
		$f_g_title .= "&#013;Group: {$fields[6]}";
	}

	$pfbalertdnsbl[13]	= "{$p_group}{$fields[6]}";
	$pfbalertdnsbl[15]	= "{$p_feed}{$fields[8]}";

	if (!empty($fields[4])) {
		if (strlen($fields[4]) >= 25) {
			$f4 = substr($fields[4], 0, 24) . "<small>...</small>";
			$fields[4] = "<span title=\"{$fields[4]}\">{$f4}</span>";
		}
		$pfbalertdnsbl[19] = "{$fields[0]} | {$fields[4]}";
	} else {
		$pfbalertdnsbl[19] = "{$fields[0]}";
	}

	if (!empty($p_mode)) {
		$p_mode = "<s title=\"Previous Blocking Mode\">{$p_mode}</s> | ";
	}
	$pfbalertdnsbl[20]	= "{$p_mode}{$fields[5]}";

	$pfbalertdnsbl[99]	= $fields[1];	// Timestamp

	// If alerts filtering is selected, process filters as required.
	if ($pfb['filterlogentries']) {
		if (empty($filterfieldsarray[1])) {
			return FALSE;
		}
		if (!pfb_match_filter_field($pfbalertdnsbl, $filterfieldsarray[1])) {
			return FALSE;
		}
		if ($dnsblfilterlimitentries != 0 && $counter[$mode == 'Unified' && !$pfb['filterlogentries'] ? 'Unified' : 'DNSBL'] >= $dnsblfilterlimitentries) {
			$dnsblfilterlimit = TRUE;
			return TRUE;
		}
	}
	else {
		if ($counter[$mode == 'Unified' && !$pfb['filterlogentries'] ? 'Unified' : 'DNSBL'] >= $pfbentries) {
			$dnsblfilterlimit = TRUE;
			return TRUE;
		}
	}
	$counter[$mode == 'Unified' && !$pfb['filterlogentries'] ? 'Unified' : 'DNSBL']++;

	// Determine Whitelist type
	list($supp_dom, $ex_dom, $isWhitelist_found) = dnsbl_whitelist_type($fields, $clists, $isExclusion, $isTLD, $qdomain);

	// Lock/Unlock Domain Icon
	$s_txt = '';
	$unlock_dom = '&nbsp;&nbsp;&nbsp;';
	if (($fields[6] != 'Unknown' && !$pfb['dnsbl_py_blacklist'] && $isMatch) || ($pfb['dnsbl_py_blacklist'])) {

		if ((!$pfb['dnsbl_py_blacklist'] && $fields[5] != 'DNSBL_TLD') || $pfb['dnsbl_py_blacklist']) {

			$tnote = "\n\nNote:&emsp;&#8226; Unlocking Domain(s) is temporary and may be automatically\n"
				. "&emsp;&emsp;&emsp;&emsp;re-locked on a Cron or Force command with an Unbound Reload!\n"
				. "&emsp;&emsp;&emsp;&nbsp;&#8226; Review Threat Source ( i ) Icon for Domain details.\n"
				. "&emsp;&emsp;&emsp;&nbsp;&#8226; Clear your Browser and OS cache after each Lock/Unlock!";

			if ($isPython) {
				$unlock_type = 'python';
			} else {
				$unlock_type = $fields[5];
			}

			if (!isset($dnsbl_unlock[$qdomain])) {
				if ($isWhitelist_found) {
					$s_txt = "\n\nNote:&emsp;The following Domain exists in the DNSBL Whitelist:\n\n"
						. "Whitelisted:&emsp;[ {$qdomain} ]\n\n"
						. "This Domain can be temporarily Relocked into DNSBL\n"
						. "by selecting the Unlock Icon!";

					$unlock_dom = '<i class="fa fa-unlock icon-primary text-warning" id="DNSBL_RELCK|'
							. $qdomain . '|' . $unlock_type . '" title="' . $s_txt . '"></i>';
				}
				else {
					$unlock_dom = '<i class="fa fa-lock icon-primary text-danger" id="DNSBL_ULCK|'
							. $qdomain . '|' . $unlock_type
							. '" title="Unlock Domain: [ ' . $qdomain . '] from DNSBL?' . $tnote . '" ></i>';
				}
			} else {
				if ($isWhitelist_found) {
					$s_txt = "\n\nNote:&emsp;The following Domain exists in the DNSBL Whitelist:\n\n"
						. "Whitelisted:&emsp;[ {$w_line} ]\n\n"
						. "Unlock this Domain by selecting the Unlock Icon!";

					$unlock_dom = '<i class="fa fa-lock icon-primary text-warning" id="DNSBL_REULCK|'
						. $qdomain . '|' . $unlock_type . '" title="' . $s_txt . '"></i>';
				}
				else {
					$unlock_dom = '<i class="fa fa-unlock icon-primary text-primary" id="DNSBL_LCK|'
						. $qdomain . '|' . $unlock_type . '" title="Re-Lock Domain: ['
						. $qdomain . ' ] back into DNSBL?' . $tnote . '" ></i>';
				}
			}
		}
	}

	// Add 'https' icon to Domains as required.
	$pfb_https = '';
	if ($fields[0] == 'DNSBL-HTTPS') {
		$pfb_https = "&nbsp;<i class=\"fa fa-key icon-pointer\" title=\"Note: HTTPS - URL/URI/UA are not collected at this time!\"></i>";
	}

	// Threat Lookup Icon
	$alert_dom = '';
	if ($fields[6] != 'Unknown') {
		$alert_dom = '<a class="fa fa-info icon-pointer icon-primary" title="Click for Threat Domain Lookup." target="_blank" ' .
				'href="/pfblockerng/pfblockerng_threats.php?domain=' . $qdomain . '"></a>';
	}

	$dup_cnt = '';
	if ($dup['DNSBL'] != 0) {
		$dup_cnt = "<span title=\"Total additional duplicate event count(s) [ {$dup['DNSBL']} ]\"> [{$dup['DNSBL']}]</span>";
		$dup['DNSBL'] = 0;
	}

	if ($mode != 'Unified') {
		print ("<tr>
			<td>{$pfbalertdnsbl[99]}{$dup_cnt}</td>
			<td>{$pfbalertdnsbl[2]}</td>
			<td>{$pfbalertdnsbl[7]}<br /><small>{$pfbalertdnsbl[17]}</small></td>
			<td style=\"white-space: nowrap;\">{$unlock_dom}&nbsp;{$alert_dom}&nbsp;{$supp_dom}{$ex_dom}</td>
			<td title=\"{$domain_title}\">{$pfbalertdnsbl[8]}<small>&emsp;[ {$pfbalertdnsbl[20]} ]</small> {$pfb_https}{$pfb_python}
				<br /><small>{$pfbalertdnsbl[19]}</small></td>
			<td title=\"{$f_g_title}\">{$pfbalertdnsbl[15]}<br /><small>{$pfbalertdnsbl[13]}</small></td>
			</tr>");
	}
	else {
		$bg = strpos($config['system']['webgui']['webguicss'], 'dark') ? $pfb['unidnsbl2'] : $pfb['unidnsbl'];
		if ($bg == 'none') {
			$bg = '';
		}

		print ("<tr title=\"DNSBL Event\" style=\"background-color:{$bg}\">
			<td style=\"white-space: nowrap;\">{$pfbalertdnsbl[99]}{$dup_cnt}</td>
			<td></td>
			<td>{$pfbalertdnsbl[7]}<br /><small>{$pfbalertdnsbl[17]}</small></td>
			<td style=\"white-space: nowrap;\"><small>{$pfbalertdnsbl[20]}</small> {$pfb_https}{$pfb_python}
				<br /><small>{$pfbalertdnsbl[19]}</small></td>
			<td>{$pfbalertdnsbl[2]}</td>
			<td style=\"white-space: nowrap;\">{$unlock_dom}&nbsp;{$alert_dom}&nbsp;{$supp_dom}{$ex_dom}</td>
			<td title=\"{$domain_title}\">{$pfbalertdnsbl[8]}</td>
			<td title=\"{$f_g_title}\">{$pfbalertdnsbl[15]}<br /><small>{$pfbalertdnsbl[13]}</small></td>
			<td></td>
			</tr>");
	}
	return FALSE;
}


// Function to convert dns_reply.log -> Reports Tab
function convert_dns_reply_log($mode, $fields) {
	global $pfb, $config, $local_hosts, $filterfieldsarray, $clists, $counter, $pfbentries, $skipcount, $dnsfilterlimit, $dnsfilterlimitentries;

	if ($dnsfilterlimit) {
		return TRUE;
	}

	$pfbalertreply		= array();
	$pfbalertreply[81]	= $fields[2];	// DNS Reply Type
	$pfbalertreply[82]	= $fields[3];	// DNS Reply Orig Type 
	$pfbalertreply[83]	= $fields[4];	// DNS Reply Final Type 
	$pfbalertreply[84]	= $fields[5];	// DNS Reply TTL 
	$pfbalertreply[85]	= $fields[6];	// DNS Reply Domain
	$pfbalertreply[86]	= $fields[7];	// DNS Reply SRC IP
	$pfbalertreply[87]	= $fields[8];	// DNS Reply DST IP
	$pfbalertreply[88]	= $fields[9];	// DNS Reply GeoIP
	$pfbalertreply[89]	= $fields[1];   // DNS Reply Timestamp

	// If alerts filtering is selected, process filters as required.
	if ($pfb['filterlogentries']) {
		if (empty($filterfieldsarray[2])) {
			return FALSE;
		}
		if (!pfb_match_filter_field($pfbalertreply, $filterfieldsarray[2])) {
			return FALSE;
		}
		if ($dnsfilterlimitentries != 0 && $counter[$mode == 'Unified' && !$pfb['filterlogentries'] ? 'Unified' : 'DNS'] >= $dnsfilterlimitentries) {
			$dnsfilterlimit = TRUE;
			return TRUE;
		}
	} else {
		if ($counter[$mode == 'Unified' && !$pfb['filterlogentries'] ? 'Unified' : 'DNS'] >= $pfbentries) {
			$dnsfilterlimit = TRUE;
			return TRUE;
		}
	}
	$counter[$mode == 'Unified' && !$pfb['filterlogentries'] ? 'Unified' : 'DNS']++;

	$hostname = $local_hosts[$fields[7]] ?: '';
	if (!empty($hostname) && strlen($hostname) >= 25) {
		$title_hostname = $hostname;
		$hostname	= substr($hostname, 0, 24) . "<small>...</small>";
	}

	// Determine if Domain is a TLD Exclusion
	$isExclusion = FALSE;
	if (isset($clists['tldexclusion']['data'][$fields[7]])) {
		$isExclusion = TRUE;
	}

	// Python_control command
	if (strpos($fields[6], 'python_control') !== FALSE) {
		$cc_color = 'blue';
		if (strpos($fields[8], 'not authorized') !== FALSE) {
			$cc_color = 'red';
		}
		$icons = "<i class=\"fa fa-cog\" title=\"Python_control command\" style=\"color: {$cc_color}\"></i>";
	}

	// Determine Whitelist type
	else {

		$dns_fields = array ('2' => $fields[6], '6' => 'Unknown');
		list($supp_dom, $ex_dom, $isWhitelist_found) = dnsbl_whitelist_type($dns_fields, $clists, $isExclusion, FALSE, $fields[6]);

		// Threat Lookup Icon
		$icons = '<a class="fa fa-info icon-pointer icon-primary" title="Click for Threat Domain Lookup." target="_blank" ' .
				'href="/pfblockerng/pfblockerng_threats.php?domain=' . $fields[6] . '"></a>';

		if (!empty($supp_dom)) {
			$icons .= "&nbsp;{$supp_dom}";
		}

		// Default - Add to Blacklist
		else {
			$icons .= '&nbsp;<i class="fa fa-plus icon-pointer icon-primary" id="DNSBLWT|' . 'dnsbl_add|'
				. $fields[6] . '|' . implode('|', $clists['dnsbl']['options']) . '" title="'
				. "Add Domain [ {$fields[6]} ] to DNSBL" . '"></i>';
		}

		if (!empty($ex_dom)) {
			$icons .= "&nbsp;{$ex_dom}";
		}
	}

	// Truncate long TTLs
	$pfb_title5 = '';
	if (strlen($fields[5]) >= 6) {
		$pfb_title5	= $fields[5];
		$fields[5]	= substr($fields[5], 0, 5) . "<small>...</small>";
	}

	// Truncate long Domain names
	$pfb_title6 = '';
	if (strlen($fields[6]) >= ($mode != 'unified' ? 45 : 30)) {
		$pfb_title6	= $fields[6];
		$fields[6]	= substr($fields[6], 0, ($mode != 'unified' ? 44 : 29)) . "<small>...</small>";
	}

	// Truncate long Resolved names
	$pfb_title8 = '';
	if (strlen($fields[8]) >= 17) {
		$pfb_title8	= $fields[8];
		$fields[8]	= substr($fields[8], 0, 16) . "<small>...</small>";
	}

	if ($mode != 'Unified') {
		print ("<tr>
			<td>{$fields[1]}</td>
			<td title=\"{$title_hostname}\">{$fields[7]}<br /><small>{$hostname}</small></td>
			<td style=\"text-align: center\">{$fields[2]}</td>
			<td style=\"text-align: center\">{$fields[3]}</td>
			<td style=\"text-align: center\">{$fields[4]}</td>
			<td style=\"white-space: nowrap;\">{$icons}</td>
			<td title=\"{$pfb_title6}\">{$fields[6]}</td>
			<td title=\"{$pfb_title5}\">{$fields[5]}</td>
			<td title=\"{$pfb_title8}\">{$fields[8]}</td>
			<td>{$fields[9]}</td>
			</tr>");
	}
	else {
		$style_bg = '';
		$title = 'DNS Reply Event';
		if ($fields[7] == '127.0.0.1') {
			$bg = strpos($config['system']['webgui']['webguicss'], 'dark') ? $pfb['unireply2'] : $pfb['unireply'];
			if ($bg != 'none') {
				$style_bg = "style=\"background-color:{$bg}\"";
			}
			$title = 'DNS Reply (Resolver) Event';
		}

		print ("<tr title=\"{$title}\" {$style_bg}>
			<td style=\"white-space: nowrap;\">{$fields[1]}</td>
			<td></td>
			<td title=\"{$title_hostname}\">{$fields[7]}<br /><small>{$hostname}</small></td>
			<td>{$fields[2]}<br /><small>{$fields[3]} | {$fields[4]}</small></td>
			<td title=\"{$pfb_title5}\"><small>{$fields[5]}</small></td>
			<td style=\"text-align: center; white-space: nowrap;\">{$icons}</td>
			<td title=\"{$pfb_title6}\">{$fields[6]}</td>
			<td title=\"{$pfb_title8}\">{$fields[8]}</td>
			<td>{$fields[9]}</td>
			</tr>");
	}
	return FALSE;
}


// Function to convert IP Logs (ip_block, ip_permit and ip_match).log -> Reports Tab
function convert_ip_log($mode, $fields, $p_query_port, $rtype) {
	global $pfb, $config, $continents, $filterfieldsarray, $clists, $ip_unlock, $counter, $pfbentries, $skipcount, $dup, $ipfilterlimit, $ipfilterlimitentries;

	if ($ipfilterlimit) {
		return array(TRUE, '');
	}

	$alert_ip = $pfb_query = $pfb_matchtitle = '';
	$src_icons = $dst_icons = $feed_new = $eval_new = $alias_new = '';

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
		[17]	= Client Hostname
		[18]	= ASN					*/

	// If alerts filtering is selected, process filters as required.
	if ($pfb['filterlogentries']) {
		if (empty($filterfieldsarray[0])) {
			return array(FALSE, '');
		}
		if (!pfb_match_filter_field($fields, $filterfieldsarray[0])) {
			$dup[$mode == 'Unified' ? 'Unified' && !$pfb['filterlogentries'] : $rtype] = 0;
			return array(FALSE, '');
		}
		if ($ipfilterlimitentries != 0 && $counter[$mode == 'Unified' && !$pfb['filterlogentries'] ? 'Unified' : $rtype] >= $ipfilterlimitentries) {
			$ipfilterlimit = TRUE;
			return array(TRUE, '');
		}
	}
	else {
		if ($counter[$mode == 'Unified' && !$pfb['filterlogentries'] ? 'Unified' : $rtype] >= $pfbentries) {
			$ipfilterlimit = TRUE;
			return array(TRUE, '');
		}
	}

	$counter[$mode == 'Unified' && !$pfb['filterlogentries'] ? 'Unified' : $rtype]++;

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
		$folder = "{$pfb['ccdir']}/*.txt";
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

	// ASN - Add to GeoIP column
	if ($pfb['asn_reporting'] != 'disabled' && !empty($fields[18]) && $fields[18] != 'Unknown') {

		if (strpos($fields[18], '| ') !== FALSE) {
			$asn = explode('| ', $fields[18], 3);
			$fields[18] = "<span title=\"| " . $asn[2] . "\">AS{$asn[1]}</span>";
		} else {
			$asn = explode(' ', $fields[18], 2);
			$fields[18] = "<span title=\"" . $asn[1] . "\">AS{$asn[0]}</span>";
		}
	}
	else {
		$fields[18] = '';
	}

	// Determine if a different IP/CIDR is now alerting on this host
	if (empty($validate)) {
		$pfb_query = find_reported_header($host, $folder);

		if ($pfb_query[0] == 'Unknown') {
			$feed_new	= 'Not listed!';
			$pfb_matchtitle = 'This IP is not currently listed!';
		} else {
			$feed_new	= $pfb_query[0];
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

		// Determine if IP is in a new Aliastable
		$q_ip = str_replace('.', '\.', $eval_new);
		$validate = exec("/usr/bin/find {$pfb['aliasdir']}/*.txt -type f | xargs {$pfb['grep']} '^{$q_ip}' 2>&1");
		$validate = ltrim(strrchr(strstr(strstr($validate, ':', TRUE), '.txt', TRUE), '/'), '/');
		if ($validate != $fields[13]) {
			$alias_new = $validate;
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

	$eval_ip = $fields[14];
	if (!empty($eval_new)) {
		$eval_ip = $eval_new;
	}

	$alert_ip = '<a class="fa fa-info icon-pointer icon-primary" target="_blank" href="/pfblockerng/pfblockerng_threats.php?host=' .
			$host . '" title="Click for Threat source IP Lookup for [ ' . $host . ' ]"></a>';

	// Suppression Icon
	$supp_ip = $unlock_ip = '&nbsp;&nbsp;&nbsp;';
	if ($rtype == 'Block' && $pfb_ipv4 && !$pfb_geoip && $mask_suppression) {

		$v4suppression32 = $v4suppression24 = FALSE; 
		if ($pfb['supp'] == 'on') {
			if (isset($clists['ipsuppression']['data'][$host . '/32'])) {
				$w_line = rtrim($clists['ipsuppression']['data'][$host . '/32'], "\x00..\x1F");
				$v4suppression32 = TRUE;
			}

			$ix = ip_explode($host);
			if (isset($clists['ipsuppression']['data'][$ix[5]])) {
				$w_line = rtrim($clists['ipsuppression']['data'][$ix[5]], "\x00..\x1F");
				$v4suppression24 = TRUE;
			}
		}

		// Host is not in the Suppression List
		if (!$v4suppression32 && !$v4suppression24) {

			// Check if host is in a Permit Whitelist Alias
			if ($clists['ipwhitelist' . $vtype]) {
				$pfb_found = FALSE;
				foreach ($clists['ipwhitelist' . $vtype] as $atype => $permit_list) {
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
							. "Evaluated IP:&emsp;[ {$eval_ip} ]\n"
							. "IP Aliasname:&emsp;[ {$atype} ]\n\n"

							. "To remove this IP from the Whitelist, press 'OK'";

					$supp_ip = '<i class="fa fa-trash no-confirm icon-pointer" id="DNSBLWT|' . 'delete_ipwhitelist|' . $host
							. '|' . $atype . '" title="' . $supp_ip_txt . '"></i>';
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
						. "Evaluated IP:&emsp;&nbsp;[ {$eval_ip} ]\n\n"
						. "IP Aliasname:&emsp;[ {$table} ]\n"
						. "IP Feedname:&emsp;&nbsp;[ "
						. (!empty($feed_new) ? $feed_new : $fields[15]) . " ]\n\n"

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
					. "Evaluated IP:&emsp;[ {$eval_ip} ]\n\n"

					. "To remove this IP from the Suppression list, press 'OK'";

			$supp_ip = '<i class="fa fa-trash no-confirm icon-pointer" id="DNSBLWT|' . 'delete_ip|' . $host
					. '|' . $table . '" title="' . $supp_ip_txt . '"></i>&emsp;';
		}
	}

	// Unlock/Lock Icon
	if ($rtype == 'Block' && ($mask_suppression || $mask_unlock)) {
		$tnote = "\n\nNote:\n&emsp;&emsp;&#8226; Unlocking IP(s) is temporary and may be automatically\n"
			. "&emsp;&emsp;&emsp;re-locked on a Cron or Force command!\n"
			. "&emsp;&emsp;&#8226; Review Threat Source ( i ) Icons for further IP details.";

		if (!isset($ip_unlock[$eval_ip])) {
			$unlock_ip = '<i class="fa fa-lock icon-primary text-danger" id="IPULCK|' . $eval_ip . '|'  . $table
					. '" title="Unlock IP: [ ' . $eval_ip . ' ] from Aliastable [ ' . $table . ' ]?'
					. $tnote . '" ></i>';
		} else {
			$unlock_ip = '<i class="fa fa-unlock icon-primary text-primary" id="IPLCK|' . $eval_ip . '|' . $table
					. '" title="Re-Lock IP: [ ' . $eval_ip . ' ] back into Aliastable [ ' . $table . ' ]?'
					. $tnote . '" ></i>';
		}
	}

	// IP Whitelist Icon
	if (!$mask_suppression) {
		if ($clists['ipwhitelist' . $vtype]) {

			$pfb_found = FALSE;
			foreach ($clists['ipwhitelist' . $vtype] as $atype => $permit_list) {
				if (isset($permit_list['data'][$host])) {
					$w_line = rtrim($permit_list['data'][$host], "\x00..\x1F");
					$pfb_found = TRUE;
					break;
				}
			}

			if ($pfb_found) {
				$supp_ip_txt = "Note:&emsp;The following IPv{$vtype} addresss is in a Permit Alias:\n\n"
						. "Permitted IP:&emsp;[ {$w_line} ]\n"
						. "Evaluated IP:&emsp;[ {$eval_ip} ]\n"
						. "IP Aliasname:&emsp;[ {$atype} ]\n\n"

						. "To remove this IP from the Whitelist, press 'OK'";

				$supp_ip = '<i class="fa fa-trash no-confirm icon-pointer" id="DNSBLWT|' . 'delete_ipwhitelist|' . $host
						. '|' . $atype . '" title="' . $supp_ip_txt . '"></i>';
			}
			else {
				$supp_ip_txt  = "Note:&emsp;The following IPv{$vtype} was blocked:\n\n"
						. "Blocked IP:&emsp;&emsp;[ {$host} ]\n"
						. "Evaluated IP:&emsp;&nbsp;[ {$eval_ip} ]\n\n"
						. "IP Aliasname:&emsp;[ {$table} ]\n"
						. "IP Feedname:&emsp;&nbsp;[ "
						. (!empty($feed_new) ? $feed_new : $fields[15]) . " ]\n\n"

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
		if ($rtype == 'Block') {
			$src_icons	= "{$unlock_ip}&nbsp;{$alert_ip}&nbsp;{$supp_ip}";
		} elseif ($rtype == 'Match') {
			$src_icons	= "{$alert_ip}&nbsp;";
		}
	}

	// Outbound event
	else {
		if ($rtype == 'Block') {
			$dst_icons	= "{$unlock_ip}&nbsp;{$alert_ip}&nbsp;{$supp_ip}";
		} elseif ($rtype == 'Match') {
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

	if (strlen($fields[15]) >= 17) {
		if (!empty($pfb_matchtitle)) {
			$pfb_matchtitle .= '&#013;';
		}
		$pfb_matchtitle .= "Feed: {$fields[15]}";
		$fields[15]	= substr($fields[15], 0, 16) . "<small>...</small>";
	}
	if (strlen($feed_new) >= 17) {
		if (!empty($pfb_matchtitle)) {
			$pfb_matchtitle .= '&#013;';
		}
		$pfb_matchtitle .= "Feed new: {$feed_new}";
		$feed_new	= substr($feed_new, 0, 16) . "<small>...</small>";
	}

	if (!empty($feed_new)) {
		$fields[15]	= "<s>{$fields[15]}</s><br />{$feed_new}";
	}

	if (!empty($eval_new)) {
		$fields[14]	= "<s>{$fields[14]}</s><br />{$eval_new}";
	}

	if (empty($fields[16])) {
		$fields[16] = 'Unknown';
	}
	elseif (strlen($fields[16]) >= 22) {
		$fields[16] = "<span title=\"{$fields[16]}\">" . substr($fields[16], 0, 21) . "<small>...</small></span>";
	}

	if (!empty($alias_new)) {
		$rule = "<s>{$fields[13]}</s><br />{$alias_new}<br /><small>({$fields[0]})</small>";
	} else {
		$rule = "{$fields[13]}<br /><small>({$fields[0]})</small>";
	}

	$dup_cnt = '';
	if ($dup[$rtype] != 0) {
		$dup_cnt = "<span title=\"Total additional duplicate event count(s) [ {$dup[$rtype]} ]\"> [{$dup[$rtype]}]</span>";
		$dup[$rtype] = 0;
	}

	if ($mode != 'Unified') {
		print ("<tr>
			<td>{$fields[99]}{$dup_cnt}</td>
			<td>{$fields[2]}</td>
			<td>{$rule}</td>
			<td>{$fields[6]}</td>
			<td>{$src_icons}</td>
			<td>{$fields[97]}{$srcport}<br /><small>{$hostname['src']}</small></td>
			<td>{$dst_icons}</td>
			<td>{$fields[98]}{$dstport}&emsp;{$query_port}<br /><small>{$hostname['dst']}</small></td>
			<td>{$fields[12]}<br />{$fields[18]}</td>
			<td title=\"{$pfb_matchtitle}\">{$fields[15]}<br /><small>{$fields[14]}</small></td>
			</tr>");
	}
	else {
		switch($rtype) {
			case 'Block':
				$bg = strpos($config['system']['webgui']['webguicss'], 'dark') ? $pfb['uniblock2'] : $pfb['uniblock'];
				break;
			case 'Permit':
				$bg = strpos($config['system']['webgui']['webguicss'], 'dark') ? $pfb['unipermit2'] : $pfb['unipermit'];
				break;
			case 'Match':
				$bg = strpos($config['system']['webgui']['webguicss'], 'dark') ? $pfb['unimatch2'] : $pfb['unimatch'];
				break;
		}

		if ($bg == 'none') {
			$bg = '';
		}

		print ("<tr title=\"IP {$rtype} Event\" style=\"background-color:{$bg}\">
			<td style=\"white-space: nowrap;\">{$fields[99]}{$dup_cnt}</td>
			<td style=\"white-space: nowrap; text-align: center;\"\>{$src_icons}</td>
			<td>{$fields[97]}{$srcport}<br /><small>{$hostname['src']}</small></td>
			<td>{$rule}&emsp;<small>{$fields[6]}</small></td>
			<td><small>{$fields[2]}</small></td>
			<td style=\"white-space: nowrap; text-align: center;\">{$dst_icons}</td>
			<td>{$fields[98]}{$dstport}&emsp;{$query_port}<br /><small>{$hostname['dst']}</small></td>
			<td title=\"{$pfb_matchtitle}\">{$fields[15]}<br /><small>{$fields[14]}</small></td>
			<td>{$fields[12]}<br />{$fields[18]}</td>
			</tr>");
	}

	// Collect Previous SRC port
	$p_query_port = $fields[10];

	return array(FALSE, $p_query_port);
}


$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('Alerts'));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', '@self');
include_once('head.inc');

// Define default Alerts Tab href link (Top row)
$get_req = pfb_alerts_default_page();

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
$tab_array[] = array(gettext('Unified'),		$active['unified'],	'/pfblockerng/pfblockerng_alerts.php?view=unified');
$tab_array[] = array(gettext('Alerts'),			$active['alerts'],	'/pfblockerng/pfblockerng_alerts.php');
$tab_array[] = array(gettext('IP Block Stats'),		$active['ip_block'],	'/pfblockerng/pfblockerng_alerts.php?view=ip_block_stat');
$tab_array[] = array(gettext('IP Permit Stats'),	$active['ip_permit'],	'/pfblockerng/pfblockerng_alerts.php?view=ip_permit_stat');
$tab_array[] = array(gettext('IP Match Stats'),		$active['ip_match'],	'/pfblockerng/pfblockerng_alerts.php?view=ip_match_stat');

if ($pfb['dnsbl_mode'] == 'dnsbl_python') {
	$tab_array[] = array(gettext('DNS Reply'),		$active['reply'],		'/pfblockerng/pfblockerng_alerts.php?view=reply');
	$tab_array[] = array(gettext('DNS Reply Stats'),	$active['dnsbl_reply_stat'],	'/pfblockerng/pfblockerng_alerts.php?view=dnsbl_reply_stat');
}
$tab_array[] = array(gettext('DNSBL Block Stats'),	$active['dnsbl'],	'/pfblockerng/pfblockerng_alerts.php?view=dnsbl_stat');
display_top_tabs($tab_array, true);

// Create Form
$form = new Form(false);
$form->setAction('/pfblockerng/pfblockerng_alerts.php');

if ($alert_summary && strpos($alert_view, 'ip_') !== FALSE) {
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
	'pfbunicnt',
	'Unified',
	'number',
	$pfbunicnt,
	['min' => 0, 'max' => 5000]
))->setHelp('Unified')->setAttribute('title', 'Enter number of \'Unified\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Input(
	'pfbdenycnt',
	'Deny',
	'number',
	$pfbdenycnt,
	['min' => 0, 'max' => 5000]
))->setHelp('IP Deny')->setAttribute('title', 'Enter number of \'Deny\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Input(
	'pfbdnscnt',
	'DNSBL',
	'number',
	$pfbdnscnt,
	['min' => 0, 'max' => 5000]
))->setHelp('DNSBL')->setAttribute('title', 'Enter number of \'DNSBL\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Input(
	'pfbdnsreplycnt',
	'DNS Reply',
	'number',
	$pfbdnsreplycnt,
	['min' => 0, 'max' => 5000]
))->setHelp('DNS Reply')->setAttribute('title', 'Enter number of \'DNS Reply\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Input(
	'pfbpermitcnt',
	'Permit',
	'number',
	$pfbpermitcnt,
	['min' => 0, 'max' => 5000]
))->setHelp('IP Permit')->setAttribute('title', 'Enter number of \'Permit\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Input(
	'pfbmatchcnt',
	'Match',
	'number',
	$pfbmatchcnt,
	['min' => 0, 'max' => 5000]
))->setHelp('IP Match')->setAttribute('title', 'Enter number of \'Match\' log entries to view. Set to \'0\' to disable');

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
		'Save ' . $alert_view,
		null,
		'fa-save'
	);
	$btn_save->removeClass('btn-primary')->addClass('btn-primary btn-xs');
	$group->add(new Form_StaticText(
		NULL,
		$btn_save
	));
}
$section->add($group);

$chart_events = array(	'24' => '24 Hrs (~1 Day)',
			'48' => '48 Hrs (~2 Days)',
			'72' => '72 Hrs (~3 Days)',
			'96' => '96 Hrs (~4 Days)',
			'120' => '120 Hrs (~5 Days)',
			'144' => '144 Hrs (~6 Days)',
			'168' => '168 Hrs (~1 week)',
			'336' => '336 Hrs (~2 weeks)',
			'672' => '672 Hrs (~1 Month)',
			'1344' => '1344 Hrs (~2 Months)',
			'2016' => '2016 Hrs (~3 Months)',
			'2688' => '2688 Hrs (~4 Months)',
			'4032' => '4032 Hrs (~6 Months)',
			'8064' => '8064 Hrs (~1 Year)',
			'max' => 'Unlimited' );

if ($pfb['dnsbl'] == 'on') {
	$group = new Form_Group(NULL);
	$group->add(new Form_Select(
		'pfbpageload',
		'Default page',
		$pfbpageload,
		[	'unified'		=> 'Unified Log',
			'default'		=> 'Alerts Tab',
			'ip_block_stat'		=> 'IP Block Stats',
			'ip_permit_stat'	=> 'IP Permit Stats',
			'ip_match_stat'		=> 'IP Match Stats',
			'reply'			=> 'DNS Reply',
			'dnsbl_reply_stat'	=> 'DNS Reply Stats',
			'dnsbl_stat'		=> 'DNSBL Block Stats']
	))->setHelp('Select the initial page to load')->setAttribute('style', 'width: auto');

	$group->add(new Form_Select(
		'pfbmaxtable',
		'',
		$pfbmaxtable,
		[	'100'	=> '100',
			'1000'	=> '1,000',
			'2000'	=> '2,000',
			'3000'	=> '3,000',
			'4000'	=> '4,000',
			'5000'	=> '5,000',
			'6000'	=> '6,000',
			'7000'	=> '7,000',
			'8000'	=> '8,000',
			'9000'	=> '9,000',
			'10000'	=> '10,000',
			'max'	=> 'No limit' ]
	))->setHelp('Select the maximum Stat Table entries to display');

	$group->add(new Form_Select(
		'pfbextdns',
		'DNS lookup',
		$pfb['extdns'],
		[	'8.8.4.4'		=> 'Google 8.8.4.4',
			'8.8.8.8'		=> 'Google 8.8.8.8',
			'208.67.220.220'	=> 'OpenDNS 208.67.220.220',
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
			'1.0.0.1'		=> 'Cloudflare 1.0.0.1',
			'77.88.8.8'		=> 'Yandex 77.88.8.8',
			'77.88.8.1'		=> 'Yandex 77.88.8.1'
		]
	))->setHelp('Select the DNS server for the DNSBL Whitelist CNAME lookup')
	  ->setAttribute('style', 'width: auto');
	$section->add($group);

	$group = new Form_Group(NULL);
	$group->add(new Form_Input(
		'ipfilterlimitentries',
		'IP Filter Limit',
		'number',
		$ipfilterlimitentries,
		['min' => 0, 'max' => 2000]
	))->setHelp('IP Filter Limit Entries')
	  ->setAttribute('title', 'Enter number of \'Filter Limit Entries\' to view. Set to \'0\' to disable');

	$group->add(new Form_Input(
		'dnsblfilterlimitentries',
		'DNSBL Filter Limit',
		'number',
		$dnsblfilterlimitentries,
		['min' => 0, 'max' => 2000]
	))->setHelp('DNSBL Filter Limit Entries')
	  ->setAttribute('title', 'Enter number of \'DNSBL Filter Limit Entries\' to view. Set to \'0\' to disable');

	$group->add(new Form_Input(
		'dnsfilterlimitentries',
		'DNS Reply Filter Limit',
		'number',
		$dnsfilterlimitentries,
		['min' => 0, 'max' => 2000]
	))->setHelp('DNS Reply Filter Limit Entries')
	  ->setAttribute('title', 'Enter number of \'DNS Reply Filter Limit Entries\' to view. Set to \'0\' to disable');
	$section->add($group);

	$group = new Form_Group('Unified Log: Light Background Theme. Enter \'none\' to disable.');
	$group->add(new Form_Input(
		'uniblock',
		'',
		'text',
		$pfb['uniblock'],
		['placeholder' => '#FFF9C4']
	))->setHelp('IP Block Event color')
	  ->setAttribute('style', "background: {$pfb['uniblock']}")
	  ->setWidth(2);

	$group->add(new Form_Input(
		'unipermit',
		'',
		'text',
		$pfb['unipermit'],
		['placeholder' => '#80CBC4']
	))->setHelp('IP Permit Event color')
	  ->setAttribute('style', "background: {$pfb['unipermit']}")
	  ->setWidth(2);

	$group->add(new Form_Input(
		'unimatch',
		'',
		'text',
		$pfb['unimatch'],
		['placeholder' => '#B3E5FC']
	))->setHelp('IP Match Event color')
	  ->setAttribute('style', "background: {$pfb['unimatch']}")
	  ->setWidth(2);

	$group->add(new Form_Input(
		'unidnsbl',
		'',
		'text',
		$pfb['unidnsbl'],
		['placeholder' => '#EF9A9A']
	))->setHelp('DNSBL Block Event color')
	  ->setAttribute('style', "background: {$pfb['unidnsbl']}")
	  ->setWidth(2);

	$group->add(new Form_Input(
		'unireply',
		'',
		'text',
		$pfb['unireply'],
		['placeholder' => '#E8E8E8']
	))->setHelp('DNS Reply Event color (Resolver only)')
	  ->setAttribute('style', "background: {$pfb['unireply']}")
	  ->setWidth(2);
	$section->add($group);

	$group = new Form_Group('Unified Log: Dark Background Theme. Enter \'none\' to disable.');
	$group->add(new Form_Input(
		'uniblock2',
		'',
		'text',
		$pfb['uniblock2'],
		['placeholder' => '#83791D']
	))->setHelp('IP Block Event color')
	  ->setAttribute('style', "background: {$pfb['uniblock2']}; color: white;")
	  ->setWidth(2);

	$group->add(new Form_Input(
		'unipermit2',
		'',
		'text',
		$pfb['unipermit2'],
		['placeholder' => '#3B8780']
	))->setHelp('IP Permit Event color')
	  ->setAttribute('style', "background: {$pfb['unipermit2']}; color: white;")
	  ->setWidth(2);

	$group->add(new Form_Input(
		'unimatch2',
		'',
		'text',
		$pfb['unimatch2'],
		['placeholder' => '#42809D']
	))->setHelp('IP Match Event color')
	  ->setAttribute('style', "background: {$pfb['unimatch2']}; color: white;")
	  ->setWidth(2);

	$group->add(new Form_Input(
		'unidnsbl2',
		'',
		'text',
		$pfb['unidnsbl2'],
		['placeholder' => '#E84E4E']
	))->setHelp('DNSBL Block Event color')
	  ->setAttribute('style', "background: {$pfb['unidnsbl2']}; color: white;")
	  ->setWidth(2);

	$group->add(new Form_Input(
		'unireply2',
		'',
		'text',
		$pfb['unireply2'],
		['placeholder' => '#54585E']
	))->setHelp('DNS Reply Event color')
	  ->setAttribute('style', "background: {$pfb['unireply2']}; color: white;")
	  ->setWidth(2);
	$section->add($group);

	if ($pfb['dnsbl_mode'] == 'dnsbl_python') {
		$group = new Form_Group('DNS Reply Log Options');
		$group->add(new Form_Select(
			'pfbreplytypes',
			'',
			$pfbreplytypes,
			[	'resolver' => 'resolver', 'reply' => 'reply', 'cache' => 'cache', 'local' => 'local',
				'servfail' => 'servfail', 'Unknown' => 'Unknown' ],
			TRUE
		))->setHelp('DNS Reply Type Suppress')
		  ->setAttribute('title', 'Select the DNS Types to suppress from the DNS Reply Log')
		  ->setWidth(2)
		  ->setAttribute('size', 6);

		$group->add(new Form_Select(
			'pfbreplyrec',
			'',
			$pfbreplyrec,
			[	'A' => 'A', 'AAAA'=> 'AAAA', 'CNAME' => 'CNAME', 'DNSKEY' => 'DNSKEY', 'DS' => 'DS', 'KEY' => 'KEY', 'MX' => 'MX',
				'NAPTR' => 'NAPTR', 'NS' => 'NS', 'NSEC3' => 'NSEC3', 'PTR' => 'PTR', 'SOA' => 'SOA', 'SRV' => 'SRV', 'TXT' => 'TXT',
				'TYPE65' => 'TYPE65', 'Unknown' => 'Unknown' ],
			TRUE
		))->setHelp('DNS Reply Record Suppress')
		  ->setWidth(2)
		  ->setAttribute('title', 'Select the DNS Record Types to suppress from the DNS Reply Log')
		  ->setAttribute('size', 16);
		$section->add($group);
	}

	$group = new Form_Group('Event Timeline Options');
	$group->add(new Form_Select(
		'pfbchartcnt',
		'',
		$pfbchartcnt,
		$chart_events
	))->setHelp('Chart Statistics - Number of logged hours to chart')
	  ->setWidth(4)
	  ->setAttribute('title', 'Select the Number of logged hours to chart')
	  ->setAttribute('size', 15);

	$group->add(new Form_Select(
		'pfbchartstyle',
		'',
		$pfbchartstyle,
		[ 'twotone' => 'Two-Tone', 'greyscale' => 'Grey-Scale', 'multi' => 'Multi-Color' ]
	))->setHelp('Chart Color Style')
	  ->setAttribute('title', 'Select the Event Timeline Chart color style')
	  ->setWidth(2)
	  ->setAttribute('size', 3);

	$group->add(new Form_Input(
		'pfbchart1',
		'',
		'text',
		$pfbchart1,
		['placeholder' => '#0C6197']
	))->setHelp('Two-Tone<br />Zero Hour bar color')
	  ->setAttribute('style', "background: {$pfbchart1}; color: white;")
	  ->setWidth(2);

	$group->add(new Form_Input(
		'pfbchart2',
		'',
		'text',
		$pfbchart2,
		['placeholder' => '#7A7A7A']
	))->setHelp('Two-Tone<br />Other Hour bar color')
	  ->setAttribute('style', "background: {$pfbchart2}; color: white;")
	  ->setWidth(2);
	$section->add($group);

	$ip_stats_array = array('ipchart'	=> 'IP Event Timeline',
				'srcipin'	=> 'Top SRC IP Inbound',
				'srcipout'	=> 'Top SRC IP Outbound',
				'dstipin'	=> 'Top DST IP Inbound',
				'dstipout'	=> 'Top DST IP Outbound',
				'srcport'	=> 'Top SRC Port',
				'dstport'	=> 'Top DST Port',
				'geoip'		=> 'Top GeoIP',
				'asn'		=> 'Top ASN',
				'aliasname'	=> 'Top Aliasname',
				'feed'		=> 'Top Feed',
				'interface'	=> 'Top Interface',
				'protocol'	=> 'Top Protocol',
				'direction'	=> 'Top Direction',
				'date'		=> 'Historical Summary');
	$table_size = count($ip_stats_array);

	$group = new Form_Group('Statistics Options');
	$group->add(new Form_Select(
		'pfbblockstat',
		'Disabled IP Block Stats',
		$pfbblockstat,
		$ip_stats_array,
		TRUE
	))->setHelp("Select the <strong>IP Block</strong> Stat table(s) to hide")
	  ->setAttribute('style', 'width: auto; overflow: hidden;')
	  ->setAttribute('size', $table_size);

	$group->add(new Form_Select(
		'pfbpermitstat',
		'Disabled IP Permit Stats',
		$pfbpermitstat,
		$ip_stats_array,
		TRUE
	))->setHelp("Select the <strong>IP Permit</strong> Stat table(s) to hide")
	  ->setAttribute('style', 'width: auto; overflow: hidden;')
	  ->setAttribute('size', $table_size);

	$group->add(new Form_Select(
		'pfbmatchstat',
		'Disabled IP Match Stats',
		$pfbmatchstat,
		$ip_stats_array,
		TRUE
	))->setHelp("Select the <strong>Match Stat</strong> table(s) to hide")
	  ->setAttribute('style', 'width: auto; overflow: hidden;')
	  ->setAttribute('size', $table_size);

	$group->add(new Form_Select(
		'pfbdnsblstat',
		'Disabled DNSBL Stats',
		$pfbdnsblstat,
		[	'dnsblchart'	=> 'DNSBL Event Timeline',
			'dnsbldomain'	=> 'Top Blocked Domain',
			'dnsblevald'	=> 'Top Blocked Eval\'d',
			'dnsblgptotal'	=> 'Top Group Count',
			'dnsblgpblock'	=> 'Top Blocked Group',
			'dnsblfeed'	=> 'Top Blocked Feed',
			'dnsblip'	=> 'Top Source IP',
			'dnsblagent'	=> $pfb['dnsbl_py_blacklist'] ? 'Top Blocking mode' : 'Top User-Agent',
			'dnsbltld'	=> 'Top TLD',
			'dnsblwebtype'	=> 'Top Webpage Types',
			'dnsblmode'	=> 'Top DNSBL Modes',
			'dnsbldatehr'	=> 'Top Date/Hr',
			'dnsbldatehrmin'=> 'Top Date/Hr/Min',
			'dnsbldate'	=> 'Top Date' ],
		TRUE
	))->setHelp("Select the <strong>DNSBL Stat</strong> table(s) to hide")
	  ->setAttribute('style', 'width: auto; overflow: hidden;')
	  ->setAttribute('size', $table_size);

	$group->add(new Form_Select(
		'pfbdnsblreplystat',
		'Disabled DNS Reply Stats',
		$pfbdnsblreplystat,
		[	'replychart'	=> 'Reply Event Timeline',
			'replytype'	=> 'Top Reply Type',
			'replyorec'	=> 'Top Reply Orig Record',
			'replyrec'	=> 'Top Reply Record',
			'replyttl'	=> 'Top TTL',
			'replydomain'	=> 'Top Reply Domain',
			'replytld'	=> 'Top Reply TLD',
			'replytld2'	=> 'Top Reply TLD 2nd level',
			'replytld3'	=> 'Top Reply TLD 3rd level',
			'replysrcip'	=> 'Top Reply SRC IP',
			'replydstip'	=> 'Top Reply DST IP',
			'replysrcipd'	=> 'Top Reply SRC IP/Domain',
			'replydate'	=> 'Top Date' ],
		TRUE
	))->setHelp("Select the <strong>DNS Reply Stat</strong> table(s) to hide")
	  ->setAttribute('style', 'width: auto; overflow: hidden;')
	  ->setAttribute('size', $table_size);
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
}

if (!$alert_summary && ($alert_title != 'DNS Reply' || $pfb['filterlogentries'])) {

	$group = new Form_Group('IP');
	$group->add(new Form_Input(
		'filterlogentries_ipdate',
		'IP - Date',
		'text',
		$filterfieldsarray[0][99]
	))->setAttribute('title', 'Enter filter \'Date\'.');

	$group->add(new Form_Input(
		'filterlogentries_ipint',
		'IP - Interface',
		'text',
		$filterfieldsarray[0][2]
	))->setAttribute('title', 'Enter filter \'Interface\'.');

	$group->add(new Form_Input(
		'filterlogentries_iprule',
		'IP - Rule Number Only',
		'text',
		$filterfieldsarray[0][0]
	))->setAttribute('title', 'Enter filter \'Rule Number\' only.');

	$group->add(new Form_Input(
		'filterlogentries_ipproto',
		'IP - Protocol',
		'text',
		$filterfieldsarray[0][6]
	))->setAttribute('title', 'Enter filter \'Protocol\'.');
	$section->add($group);

	$group = new Form_Group(NULL);
	$group->add(new Form_Input(
		'filterlogentries_ipsrcip',
		'IP - Source Address',
		'text',
		$filterfieldsarray[0][7]
	))->setAttribute('title', 'Enter filter \'Source IP Address\'.');

	$group->add(new Form_Input(
		'filterlogentries_ipsrchostname',
		'IP - Source Hostname',
		'text',
		$filterfieldsarray[0][17]
	))->setAttribute('title', 'Enter filter \'Source Hostname\'.');

	$group->add(new Form_Input(
		'filterlogentries_ipsrcport',
		'IP - Source:Port',
		'text',
		$filterfieldsarray[0][9]
	))->setAttribute('title', 'Enter filter \'Source:Port\'.');
	$section->add($group);

	$group = new Form_Group(NULL);
	$group->add(new Form_Input(
		'filterlogentries_ipdstip',
		'IP - Destination Address',
		'text',
		$filterfieldsarray[0][8]
	))->setAttribute('title', 'Enter filter \'Destination IP Address\'.');

	$group->add(new Form_Input(
		'filterlogentries_ipdsthostname',
		'IP - Destination Hostname',
		'text',
		$filterfieldsarray[0][16]
	))->setAttribute('title', 'Enter filter \'Destination Hostname\'.');

	$group->add(new Form_Input(
		'filterlogentries_ipdstport',
		'IP - Destination:Port',
		'text',
		$filterfieldsarray[0][10]
	))->setAttribute('title', 'Enter filter \'Destination:Port\'.');
	$section->add($group);

	$group = new Form_Group(NULL);
	$group->add(new Form_Input(
		'filterlogentries_ipfeed',
		'IP - Feed',
		'text',
		$filterfieldsarray[0][15]
	))->setAttribute('title', 'Enter filter \'Feed name\'.');

	$group->add(new Form_Input(
		'filterlogentries_ipalias',
		'IP - Alias',
		'text',
		$filterfieldsarray[0][13]
	))->setAttribute('title', 'Enter filter \'Aliasname\'.');

	$group->add(new Form_Input(
		'filterlogentries_ipgeoip',
		'IP - GeoIP',
		'text',
		$filterfieldsarray[0][12]
	))->setAttribute('title', 'Enter filter \'GeoIP\'.')
	  ->setwidth(2);

	$group->add(new Form_Input(
		'filterlogentries_ipasn',
		'IP - ASN',
		'text',
		$filterfieldsarray[0][18]
	))->setAttribute('title', 'Enter filter \'ASN\'.')
	  ->setwidth(2);
	$section->add($group);

	if ($pfb['dnsbl'] == 'on') {
		$group = new Form_Group('DNSBL');
		$group->add(new Form_Input(
			'filterlogentries_dnsbldate',
			'DNSBL - Date',
			'text',
			$filterfieldsarray[1][99]
		))->setAttribute('title', 'Enter filter \'Date\'.');

		$group->add(new Form_Input(
			'filterlogentries_dnsblint',
			'DNSBL - Interface',
			'text',
			$filterfieldsarray[1][2]
		))->setAttribute('title', 'Enter filter \'Interface\'.');
		$section->add($group);

		$group = new Form_Group(NULL);
		$group->add(new Form_Input(
			'filterlogentries_dnsbldomain',
			'DNSBL - Domain',
			'text',
			$filterfieldsarray[1][8]
		))->setAttribute('title', 'Enter filter \'Enter filter \'Domain\'.');

		$group->add(new Form_Input(
			'filterlogentries_dnsblsrcip',
			'DNSBL - Source Address',
			'text',
			$filterfieldsarray[1][7]
		))->setAttribute('title', 'Enter filter \'Source IP Address\'.');

		$group->add(new Form_Input(
			'filterlogentries_dnsblsrchostname',
			'DNSBL - Source Hostname',
			'text',
			$filterfieldsarray[1][17]
		))->setAttribute('title', 'Enter filter \'Source Hostname\'.');
		$section->add($group);

		$group = new Form_Group(NULL);
		$group->add(new Form_Input(
			'filterlogentries_dnsblfeed',
			'DNSBL - Feed',
			'text',
			$filterfieldsarray[1][15]
		))->setAttribute('title', 'Enter filter \'Feed name\'.');

		$group->add(new Form_Input(
			'filterlogentries_dnsblgroup',
			'DNSBL - Group',
			'text',
			$filterfieldsarray[1][13]
		))->setAttribute('title', 'Enter filter \'Group name\'.');
		$section->add($group);

		$f19_title = ($pfb['dnsbl_mode'] == 'dnsbl_python' ? gettext("DNSBL: Blocking Type") : gettext("DNSBL: Domain/Referer|URI|Agent"));
		$group = new Form_Group(NULL);
		$group->add(new Form_Input(
			'filterlogentries_dnsbltype',
			$f19_title,
			'text',
			$filterfieldsarray[1][19]
		))->setAttribute('title', "Enter filter '{$f19_title}'.");

		$group->add(new Form_Input(
			'filterlogentries_dnsblmode',
			'DNSBL - Blocking Mode',
			'text',
			$filterfieldsarray[1][20]
		))->setAttribute('title', 'Enter filter \'DNSBL Blocking Mode (ie: DNSBL/TLD)\'.');
		$section->add($group);
	}
}

if (!$alert_summary && ($alert_title == 'DNS Reply' || $alert_title == 'Unified Logs' || $pfb['filterlogentries'])) {

	if ($pfb['dnsbl_mode'] == 'dnsbl_python') {
		$group = new Form_Group('DNS Reply');
		$group->add(new Form_Input(
			'filterlogentries_replydate',
			'Reply - Date',
			'text',
			$filterfieldsarray[2][89]
		))->setAttribute('title', 'Enter filter \'DNS Reply Date\'.');

		$group->add(new Form_Input(
			'filterlogentries_replydomain',
			'Reply - Domain',
			'text',
			$filterfieldsarray[2][85]
		))->setAttribute('title', 'Enter filter \'DNS Reply Domain\'.');
		$section->add($group);

		$group = new Form_Group(NULL);
		$group->add(new Form_Input(
			'filterlogentries_replysrcip',
			'Reply - Source IP',
			'text',
			$filterfieldsarray[2][86]
		))->setAttribute('title', 'Enter filter \'DNS Reply SRC IP\'.');

		$group->add(new Form_Input(
			'filterlogentries_replydstip',
			'Reply - Resolved IP',
			'text',
			$filterfieldsarray[2][87]
		))->setAttribute('title', 'Enter filter \'DNS Resolved IP\'.');

		$group->add(new Form_Input(
			'filterlogentries_replygeoip',
			'Reply - GeoIP',
			'text',
			$filterfieldsarray[2][88]
		))->setAttribute('title', 'Enter filter \'DNS Reply GeoIP\'.')
		  ->setwidth(2);
		$section->add($group);

		$group = new Form_Group(NULL);
		$group->add(new Form_Input(
			'filterlogentries_replytype',
			'Reply - Type',
			'text',
			$filterfieldsarray[2][81]
		))->setAttribute('title', 'Enter filter \'DNS Reply Type\'.');

		$group->add(new Form_Input(
			'filterlogentries_replyorec',
			'Reply - Original Record',
			'text',
			$filterfieldsarray[2][82]
		))->setAttribute('title', 'Enter filter \'DNS Reply Orig Record\'.');

		$group->add(new Form_Input(
			'filterlogentries_replyrec',
			'Reply - DNS Record',
			'text',
			$filterfieldsarray[2][83]
		))->setAttribute('title', 'Enter filter \'DNS Reply Record\'.');

		$group->add(new Form_Input(
			'filterlogentries_replyttl',
			'Reply - TTL',
			'text',
			$filterfieldsarray[2][84]
		))->setAttribute('title', 'Enter filter \'DNS Reply TTL\'.');
		$section->add($group);
	}
}

if (!$alert_summary) {
	$group = new Form_Group(NULL);
	$btnsubmit = new Form_Button(
		'filterlogentries_submit',
		'Apply Filter',
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
		$btnsubmit
	))->setwidth(1);

	$group->add(new Form_StaticText(
		'',
		$btnclear
	))->setwidth(1);
	$section->add($group);

	if ($pfb['filterlogentries']) {
		$group = new Form_Group(NULL);
		$group->add(new Form_StaticText(
			'',
			'( Save disabled during <strong>Apply Filter</strong>)'
			. '&emsp;<div class="infoblock">'
			. '<h6>Regex Style Matching Only! <a href="https://regexr.com/" target="_blank">Regular Expression Help link</a>. '
			. 'Precede with exclamation (!) as first character to exclude match.)</h6>'
			. '<h6>Example: ( ^80$ - Match Port 80, ^80$|^8080$ - Match both port 80 & 8080 )</h6>'
			. '</div>'
		));
		$section->add($group);
	}

	$form->addGlobal(new Form_Input('domain', 'domain', 'hidden', ''));
	$form->addGlobal(new Form_Input('dnsbl_customlist', 'dnsbl_customlist', 'hidden', ''));
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
	$form->addGlobal(new Form_Input('dnsbl_add', 'dnsbl_add', 'hidden', ''));
	$form->addGlobal(new Form_Input('ip_remove', 'ip_remove', 'hidden', ''));
	$form->addGlobal(new Form_Input('ip_white', 'ip_white', 'hidden', ''));
	$form->addGlobal(new Form_Input('alert_view', 'alert_view', 'hidden', $alert_view));
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

	// Create four output windows 'Deny', 'DNSBL', 'Permit' and 'Match' -> 'Alerts Tab'
	// or Create DNS Reply Tab
	// or Create Unified Log Tab

	$skipcount 	= 0;
	$counter 	= array('Deny' => 0, 'Permit' => 0, 'Match' => 0, 'Unified' => 0, 'DNSBL' => 0, 'DNS' => 0);
	$dup		= array('Deny' => 0, 'Permit' => 0, 'Match' => 0, 'DNSBL' => 0, 'Unified' => 0);

	// Suppress user-defined reply types
	if (isset($pfbreplytypes) && !empty($pfbreplytypes[0])) {
		$pfbreplytypes = array_flip($pfbreplytypes);
	} else {
		unset($pfbreplytypes);
	}

	if (isset($pfbreplyrec) && !empty($pfbreplyrec[0])) {
		$pfbreplyrec = array_flip($pfbreplyrec);
	} else {
		unset($pfbreplyrec);
	}

	foreach (array (	'Deny'		=> "{$pfb['ip_blocklog']}",
				'DNSBL Block'	=> "{$pfb['dnslog']}",
				'DNSBL Python'	=> "{$pfb['dnslog']}",
				'DNS Reply'	=> "{$pfb['dnsreplylog']}",
				'Permit'	=> "{$pfb['ip_permitlog']}",
				'Match'		=> "{$pfb['ip_matchlog']}",
				'Unified'	=> "{$pfb['unilog']}") as $logtype => $pfb_log ):

		// Validate Alert view and Log type
		switch ($alert_view) {
			case 'alert':
				if ($pfb['dnsbl'] == 'on') {
					$pfbentries = "{$pfbdnscnt}";
					if ($pfb['filterlogentries'] && $dnsblfilterlimitentries != 0) {
						$pfbentries = $dnsblfilterlimitentries;
					}

					if ($logtype == 'DNSBL Block') {
						if ($pfb['dnsbl_py_blacklist']) {
							continue 2;
						}
						break;
					}
					elseif ($logtype == 'DNSBL Python') {
						if (!$pfb['dnsbl_py_blacklist']) {
							continue 2;
						}
						break;
					}
				}

				if ($logtype == 'Deny') {
					$rtype = 'Block';
					$pfbentries = "{$pfbdenycnt}";
					if ($pfb['filterlogentries'] && $ipfilterlimitentries != 0) {
						$pfbentries = $ipfilterlimitentries;
					}
					break;
				}
				elseif ($logtype == 'Permit') {
					$rtype = 'Permit';
					$pfbentries = "{$pfbpermitcnt}";
					if ($pfb['filterlogentries'] && $ipfilterlimitentries != 0) {
						$pfbentries = $ipfilterlimitentries;
					}
					break;
				}
				elseif ($logtype == 'Match') {
					$rtype = 'Match';
					$pfbentries = "{$pfbmatchcnt}";
					if ($pfb['filterlogentries'] && $ipfilterlimitentries != 0) {
						$pfbentries = $ipfilterlimitentries;
					}
					break;
				}
				continue 2;
			case 'reply':
				if ($logtype == 'DNS Reply') {
					$pfbentries = "{$pfbdnsreplycnt}";
					if ($pfb['filterlogentries'] && $dnsfilterlimitentries != 0) {
						$pfbentries = $dnsfilterlimitentries;
					}
					break;
				}
				continue 2;
			case 'unified':
				if ($logtype == 'Unified') {
					$pfbentries = "{$pfbunicnt}";
					break;
				}
				continue 2;
			default:
				continue 2;
		}

		// Skip table output if $pfbentries is zero.
		if ($pfbentries == 0 && $skipcount != 5) {
			$skipcount++;
			continue;
		}

		$ipfilterlimit = $dnsblfilterlimit = $dnsfilterlimit = FALSE;
		?>

<div class="panel panel-default" style="width: 100%;">
	<div class="panel-heading">
		<h2 class="panel-title">
			<? if ($alertrefresh == 'on'): ?>
			<i class="fa fa-pause-circle icon-primary" id="PauseRefresh" " title="Pause Alerts Refresh"></i>&nbsp;
			<? endif; ?>
			<?=gettext($logtype)?><small>-&nbsp;<?=gettext('Last')?>&nbsp;<?=$pfbentries?>&nbsp;<?=gettext('Alert Entries')?></small>
		</h2>
	</div>
	<div class="panel-body">
		<div class="table-responsive">
		<table style="width: 100%;" class="table table-striped table-hover table-compact sortable-theme-bootstrap" data-sortable>

	<?php
		// Create Unified Report
		if ($logtype == 'Unified' && file_exists("{$pfb_log}")) {
	?>
			<thead>
				<tr>
					<th style="max-width:5%;"><?=gettext("Date")?></th>
					<th style="max-width:1%;"><!----- Buttons -----></th>
					<th style="max-width:10%;"><?=gettext("SRC")?></th>
					<th style="max-width:3%;"><?=gettext("Rule|Mode/Type")?></th>
					<th style="max-width:20%;"><?=gettext("IF/TTL")?></th>
					<th style="max-width:1%;"><!----- Buttons -----></th>
					<th style="max-width:20%;"><?=gettext("Destination")?></th>
					<th style="max-width:20%;"><?=gettext("Resolved/Feed")?></th>
					<th style="max-width:20%;"><?=gettext("GeoIP")?></th>
				</tr>
			</thead>
			<tbody>
	<?php
			exec("/usr/bin/tail -r {$pfb_log} > {$pfb_log}.rev 2>&1");
			if (($handle = @fopen("{$pfb_log}.rev", 'r')) !== FALSE) {
				while (($fields = @fgetcsv($handle)) !== FALSE) {

					// Filter Unified Log for specific Log Types
					if ($pfb['filterlogentries'] && !isset($filter_unified[$fields[0]])) {
						continue;
					}

					switch ($fields[0]) {
						case 'DNSBL-Full':
						case 'DNSBL-1x1':
						case 'DNSBL-HTTPS':
						case 'DNSBL-python':
							convert_dnsbl_log('Unified', $fields);
							break;
						case 'DNS-reply':

							// Suppress user-defined reply types
							if (isset($pfbreplytypes) && isset($pfbreplytypes[$fields[2]])) {
								continue 2;
							}

							// Suppress user-defined DNS Records
							if (isset($pfbreplyrec) && (isset($pfbreplyrec[$fields[3]]) || isset($pfbreplyrec[$fields[4]]))) {
								continue 2;
							}

							convert_dns_reply_log('Unified', $fields);
							break;
						case 'Block':
							$rtype = 'Block';
						case 'Permit':
							$rtype = empty($rtype) ? 'Permit' : $rtype;
						case 'Match':
							$rtype = empty($rtype) ? 'Match' : $rtype;
							array_shift($fields); // Remove Unified log prefix field
							convert_ip_log('Unified', $fields, $p_query_port, $rtype);
							break;
					}
				}
			}
		}

		// Process dns array for DNSBL and generate output
		if (($logtype == 'DNSBL Block' || $logtype == 'DNSBL Python') && file_exists("{$pfb_log}")) {
	?>
			<thead>
				<tr>
					<th><?=gettext("Date")?></th>
					<th><?=gettext("IF")?></th>
					<th><?=gettext("Source")?></th>
					<th style="width: 5.3%;"><!----- Buttons -----></th>
					<th><?=$logtype == 'DNSBL Python' ? gettext("Domain/Block mode") : gettext("Domain/Referer|URI|Agent")?></th>
					<th><?=gettext("Feed/Group")?></th>
				</tr>
			</thead>
			<tbody>
	<?php
			exec("/usr/bin/tail -r {$pfb_log} > {$pfb_log}.rev 2>&1");
			if (($handle = @fopen("{$pfb_log}.rev", 'r')) !== FALSE) {
				while (($fields = @fgetcsv($handle)) !== FALSE) {

					// Remove and record duplicate entries
					if ($fields[9] == '-') {
						$dup['DNSBL']++;
						continue;
					}
					if (convert_dnsbl_log('non_unified', $fields)) {
						break;
					}
					$dup['DNSBL'] = 0;
				}
			}
		}
		@fclose($handle);
		unlink_if_exists("{$pfb_log}.rev");

		// Process DNS Reply log and generate output
		if ($logtype == 'DNS Reply' && file_exists("{$pfb_log}")) {
	?>
			<thead>
				<tr>
					<th style="width:10%"><?=gettext("Date")?></th>
					<th style="width:10%"><?=gettext("Source")?></th>
					<th style="width:3%"><?=gettext("Reply Type")?></th>
					<th style="width:3%"><?=gettext("Orig Record")?></th>
					<th style="width:3%"><?=gettext("DNS Record")?></th>
					<th style="width:1%"><!----- Buttons -----></th>
					<th style="width:15%"><?=gettext("Domain")?></th>
					<th style="width:4%" title="TTL remaining"><?=gettext("TTL")?></th>
					<th style="width:15%"><?=gettext("Resolved")?></th>
					<th style="width:3%"><?=gettext("GeoIP")?></th>
				</tr>
			</thead>
			<tbody>
	<?php
			exec("/usr/bin/tail -r {$pfb_log} > {$pfb_log}.rev 2>&1");
			if (($handle = @fopen("{$pfb_log}.rev", 'r')) !== FALSE) {
				while (($fields = @fgetcsv($handle)) !== FALSE) {

					// Suppress user-defined reply types
					if (isset($pfbreplytypes) && isset($pfbreplytypes[$fields[2]])) {
						continue;
					}

					// Suppress user-defined DNS Records
					if (isset($pfbreplyrec) && (isset($pfbreplyrec[$fields[3]]) || isset($pfbreplyrec[$fields[4]]))) {
						continue;
					}

					if (convert_dns_reply_log('non_unified', $fields)) {
						break;
					}
				}
			}
			@fclose($handle);
			unlink_if_exists("{$pfb_log}.rev");
		}

		// Process Deny/Permit/Match and generate output
		if (($logtype == 'Deny' || $logtype == 'Permit' || $logtype == 'Match') && file_exists("{$pfb_log}")) {

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
				<th><?=$pfb['asn_reporting'] != 'disabled' ? gettext("GeoIP/ASN") : gettext("GeoIP")?></th>
				<th><?=gettext("Feed")?></th>
			</tr>
		</thead>
		<tbody>
	<?php

			$p_query_port = '';
			exec("/usr/bin/tail -r {$pfb_log} > {$pfb_log}.rev 2>&1");
			if (($handle = @fopen("{$pfb_log}.rev", 'r')) !== FALSE) {
				while (($fields = @fgetcsv($handle)) !== FALSE) {
					$last_fld = array_pop($fields);

					// Remove and record duplicate entries
					if ($last_fld == '-') {
						$dup[$rtype]++;
						continue;
					}

					$convert_ip = convert_ip_log('non_unified', $fields, $p_query_port, $rtype);
					if ($convert_ip[0]) {
						break;
					} else {
						$p_query_port	= $convert_ip[1];
					}
				}
			}
			@fclose($handle);
			unlink_if_exists("{$pfb_log}.rev");
		}
	?>
		</tbody>
		<tfoot>
	<?php
		switch ($logtype) {
			case 'Deny':
			case 'Permit':
			case 'Match':
				$colspan = "colspan='10'";
				$fcounter = $counter[$rtype];
				$pfbfilterlimit = $ipfilterlimit;
				break;
			case 'DNSBL Block':
			case 'DNSBL Python':
				$colspan = "colspan='7'";
				$fcounter = $counter['DNSBL'];
				$pfbfilterlimit = $dnsblfilterlimit;
				break;
			case 'DNS Reply':
				$colspan = "colspan='7'";
				$fcounter = $counter['DNS'];
				$pfbfilterlimit = $dnsfilterlimit;
				break;
			case 'Unified':
				$colspan = "colspan='7'";
				$fcounter = $counter['Unified'];

				if ($pfb['filterlogentries']) {
					$pfbfilterlimit = FALSE;
					if ($ipfilterlimit && $dnsblfilterlimit && $dnsfilterlimit) {
						$pfbfilterlimit = TRUE;
					} 
				}
				break;
		}

		if ($pfb['filterlogentries']) {
			foreach ($counter as $c) {
				$fcounter += $c;
			}
		}

		// Print final table info
		$msg = '';
		if ($pfbfilterlimit) {
			$msg = " - Filter Limit setting reached.";
		} elseif (!$pfb['filterlogentries'] && $pfbentries != $fcounter) {
			$msg = ' - Insufficient Alerts found.';
		}

		if ($logtype == 'Unified') {
			$fcounter = "{$fcounter} (IP/DNSBL/DNS Reply)";
		}

		print ("			<td {$colspan} style='font-size:10px; background-color: #F0F0F0;' >Found {$fcounter} Alert Entries{$msg}</td>");
		$fcounter = 0; $msg = '';
	?>

		</tfoot>
	</table>
	</div>
</div>
</div>
	<?php
		endforeach;	// End - Create four output windows ('Deny', 'DNSBL', 'Permit' and 'Match') or DNS Reply or Unified Log
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
if (!$pfb['filterlogentries']):?>

<form action="/pfblockerng/pfblockerng_alerts.php" method="post" name="iform_stats" id="iform_stats" class="form-horizontal">
<script src="../vendor/d3/d3.min.js"></script>
<script src="../vendor/d3pie/d3pie.min.js"></script>
<script src="../vendor/nvd3/nv.d3.js"></script>
<link href="../vendor/nvd3/nv.d3.css" media="screen, projection" rel="stylesheet" type="text/css">

<div class="panel panel-default">
<div class="panel-heading">
	<h2 class="panel-title">
		<?=$alert_title;?> Statistics&emsp;<small>Total event(s):
		&emsp;[ <?=$alert_stats['count'][$alert_view];?> ]</small>
	</h2>
</div>
</div>

<div class="panel-body">
<?php
$segcolors = array(	"#2484c1", "#65a620", "#7b6888", "#a05d56", "#961a1a", "#d8d23a", "#e98125", "#d0743c", "#635222", "#6ada6a",
			"#0c6197", "#7d9058", "#207f33", "#44b9b0", "#bca44a", "#e4a14b", "#a3acb2", "#8cc3e9", "#69a6f9", "#5b388f" );

if ($alert_summary && $alert_view == 'dnsbl_stat') {
$stats = array( 'dnsblchart'	=> array("DNSBL Event Timeline&emsp;<small>(Last <span id=\"range\">{$pfbchartcnt}</span> hours)</small>",'','', FALSE, ''),
		'dnsbldomain'	=> array('Top Blocked Domain',			'Found', 'Blocked Domain(s)',	TRUE, 'domain'),
		'dnsblevald'	=> array('Top Blocked Evaluated Domain (TLD)',	'Found', 'Blocked Domain(s)',	TRUE, 'domain'),
		'dnsblgptotal'	=> array('Top Group Count',			'Found', 'DNSBL Group(s)',	FALSE, ''),
		'dnsblgpblock'	=> array('Top Blocked Group',			'Found', 'DNSBL Group(s)',	FALSE, ''),
		'dnsblfeed'	=> array('Top Feed',				'Found', 'Feed(s)',		FALSE, ''),
		'dnsblip'	=> array('Top Source IP',			'Found', 'Source IP(s)',	FALSE, ''),
		'dnsblagent'	=> array($pfb['dnsbl_py_blacklist'] ? 'Blocking Type' : 'Top User-Agent', 'Found',
					$pfb['dnsbl_py_blacklist'] ? 'Type(s)' : 'User-Agent(s)',		FALSE, ''),
		'dnsbltld'	=> array('Top TLD',				'Found', 'TLD(s)',		FALSE, ''),
		'dnsblwebtype'	=> array('Top Blocked Webpage Types',		'Found', 'Blocked Webpage Type(s)',	FALSE, ''),
		'dnsblmode'	=> array('Top DNSBL Modes',			'Found', 'Blocked DNSBL Mode(s)',	FALSE, ''),
		'dnsbldatehr'	=> array('Top Date/Hr',				'Found', 'Date/Hr segment(s)',		FALSE, ''),
		'dnsbldatehrmin'=> array('Top Date/Hr/Min',			'Found', 'Date/Hr/Min segment(s)',	FALSE, ''),
		'dnsbldate'	=> array('Top Date',				'Found', 'day(s) of logs',		FALSE, '') );
}
elseif ($alert_summary && $alert_view == 'dnsbl_reply_stat') {
$stats = array( 'replychart'	=> array("Reply Event Timeline&emsp;<small>(Last <span id=\"range\">{$pfbchartcnt}</span> hours)</small>",'','', FALSE, ''),
			'replydomain'	=> array('Top Reply Domain',			'Found', 'Reply Domain(s)',	FALSE, 'domain'),
			'replytld'	=> array('Top Reply TLD',			'Found', 'Reply TLD(s)',	FALSE, ''),
			'replytld2'	=> array('Top Reply TLD 2nd level',		'Found', 'Reply TLD(s)',	FALSE, ''),
			'replytld3'	=> array('Top Reply TLD 3rd level',		'Found', 'Reply TLD(s)',	FALSE, ''),
			'replysrcip'	=> array('Top Reply SRC IP',			'Found', 'SRC IP(s)',		FALSE, ''),
			'replydstip'	=> array('Top Reply DST IP',			'Found', 'IP(s)',		FALSE, 'host'),
			'replysrcipd'	=> array('Top Reply SRC IP/Domain',		'Found', 'Domain/IP(s)',	FALSE, 'domain'),
			'replytype'	=> array('Top Reply Type',			'Found', 'Reply Type(s)',	FALSE, ''),
			'replyorec'	=> array('Top Reply Orig Record',		'Found', 'Reply Record(s)',	FALSE, ''),
			'replyrec'	=> array('Top Reply Record',			'Found', 'Reply Record(s)',	FALSE, ''),
			'replyttl'	=> array('Top Reply TTL',			'Found', 'Reply TTL(s)',	FALSE, ''),
			'replygeoip'	=> array('Top Reply GeoIP',			'Found', 'Reply GeoIP(s)',	FALSE, ''),
			'replydate'	=> array('Top Date',				'Found', 'day(s) of logs',	FALSE, ''));
}
else {
	$stats = array(	'ipchart'	=> array("IP Event Timeline&emsp;<small>(Last <span id=\"range\">{$pfbchartcnt}</span> hours)</small>",'','', FALSE, ''),
			'ipsrcipin'	=> array("Top SRC IP Inbound (by GeoIP)",	'Found', 'SRC IP(s)',		TRUE, 'host'),
			'ipsrcipout'	=> array("Top SRC IP Outbound (by GeoIP)",	'Found', 'SRC IP(s)',		TRUE, 'host'),
			'ipdstipin'	=> array("Top DST IP Inbound (by GeoIP)",	'Found', 'DST IP(s)',		TRUE, 'host'),
			'ipdstipout'	=> array("Top DST IP Outbound (by GeoIP)",	'Found', 'DST IP(s)',		TRUE, 'host'),
			'ipsrcport'	=> array("Top SRC Port (1-1024 only)",		'Found', 'SRC Port(s)',		FALSE, ''),
			'ipdstport'	=> array("Top DST Port",			'Found', 'DST Port(s)',		FALSE, ''),
			'ipgeoip'	=> array("Top GeoIP",				'Found', 'GeoIP(s)',		FALSE, ''),
			'ipasn'		=> array("Top ASN",				'Found', 'ASN(s)',		FALSE, ''),
			'ipaliasname'	=> array("Top Aliasname",			'Found', 'Aliasname(s)',	FALSE, ''),
			'ipfeed'	=> array("Top Feed",				'Found', 'Feed{s)',		FALSE, ''),
			'ipinterface'	=> array("Top Interface",			'Found', 'Interface(s)',	FALSE, ''),
			'ipprotocol'	=> array("Top Protocol",			'Found', 'Protocol(s)',		FALSE, ''),
			'ipdirection'	=> array("Top Direction",			'Found', 'Direction(s)',	FALSE, ''),
			'ipdate'	=> array("Top Date",				'Found', 'day(s) of logs',	FALSE, ''));
}

foreach ($stats as $stat_type => $stype):

	if ($stat_type == 'ipasn' && $pfb['asn_reporting'] == 'disabled') {
		continue;
	}
	elseif ($alert_view == 'dnsbl_stat' && substr($stat_type, 0,6) == 'python' && $pfb['dnsbl_mode'] != 'dnsbl_python') {
		continue;
	}

	$topcount = $sumlines = 0;
	if (!empty($alert_stats[$alert_view][$stat_type])) {
		$topcount = count($alert_stats[$alert_view][$stat_type]);
		$sumlines = array_sum($alert_stats[$alert_view][$stat_type]);
	}

	$height = 30;
	if ($topcount > 0) {
		$height = 390;
	}

	$collapse_status = 'in';
	if (isset($stat_hidden[$stat_type])) {
		$collapse_status = 'out';
		continue;
	}
?>

<div class="panel panel-default" id="Alert_Stats_<?=$stat_type?>" style="display: inline-block; width: 100%;">
	<div class="panel-heading">
		<h2 class="panel-title">
			<? if ($alertrefresh == 'on'): ?>
			<i class="fa fa-pause-circle icon-primary" id="PauseRefresh" " title="Pause Alerts Refresh"></i>&nbsp;
			<? endif; ?>

			<?=$stype[0]?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#Alert_Stats_<?=$stat_type?>_panel-body" id="Alert_Stats_A_<?=$stat_type?>">
					<i class="fa fa-plus-circle"></i>
				</a>
			</span>
		</h2>
		</div>

		<div class="panel-body collapse <?=$collapse_status?>" id="Alert_Stats_<?=$stat_type?>_panel-body" style="overflow-x: auto;">

			<?php if ($stat_type == 'dnsblchart' || $stat_type == 'replychart' || $stat_type == 'ipchart'): ?>

			<div id="chart" class="d3-chart" style="overflow: hidden;">

				<!-- Date range dropdown menu -->
				<div class="btn-group navbar-right" style="margin-right: 10px;">
					<ul class="navbar-nav">
						<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="true">
							Date Range <span class="caret"></span>
						</a>
						<ul class="dropdown-menu" role="menu" style="padding: 1px 1px 1px 1px; font-size: smaller;">
							<?php foreach ($chart_events as $event => $type):?>
							<li id="chartEvent" value="<?=$event?>">
								<a href="#" class="navlnk"><?=$type?></a>
							</li>
							<?php endforeach;?>
						</ul>
					</ul>
				</div>

				<!-- Chart SVG -->
				<svg></svg>
			</div>
			<?php d3_chart($pfbchartcnt, $pfbchartstyle, $pfbchart1, $pfbchart2); ?>

			<?php else: ?>

			<div style="height: <?=$height;?>px; width: 50%; float: left; overflow-y: scroll;">
			<table class="table table-responsive table-bordered table-striped table-hover table-compact sortable-theme-bootstrap" data-sortable>

				<thead>
					<tr>
						<th style="width: 10%;"><!--  Action buttons --></th>
						<th style="width: 10%; text-align: center;"><?=gettext("Count")?></th>

						<?php if ($stype[3]): ?>
						<th style="width: 2%; text-align: center;">
							<?
							$column_title = 'GeoIP';
							if ($stat_type == 'dnsbldomain' || $stat_type == 'dnsblevald') {
								$column_title = 'Type';
							} elseif ($stat_type == 'replysrcipd') {
								$column_title = 'SRC IP';
							}
							?>
							<?=gettext($column_title);?></th>
						<?php endif; ?>

						<th><small><?=$stype[1] . "&emsp;[ {$topcount} ]&emsp;" . $stype[2]?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if (!empty($alert_stats[$alert_view][$stat_type])) {

						$table_entries = 0;
						$max_table_entries = FALSE;
						foreach ($alert_stats[$alert_view][$stat_type] as $data => $data_count) {

							if ($pfbmaxtable != 'max') {
								if ($table_entries > $pfbmaxtable) {
									$max_table_entries = TRUE;
									break;
								}
								$table_entries++;
							}

							$alert_event = $btnsubmit = $query_port = $hostname = '';
							$subdata = array();

							$filter_value = $data;
							if ($stat_type == 'dnsbltld' || $stat_type == 'replytld' ||
							    $stat_type == 'replytld2'|| $stat_type == 'replytld3' ) {
								$filter_value = "\.{$data}$";
							}
							elseif ($stat_type == 'ipsrcport' || $stat_type == 'ipdstport' ||
								$stat_type == 'replyorec' || $stat_type == 'replyrec') {
								$filter_value = "^{$data}$";
							}
							elseif ($stat_type == 'replyttl') {
								if (strlen($data) > 8) {
									$filter_value = "^15\d{6,10}";
								} else {
									$filter_value = "^{$data}$";
								}
							}
							elseif ($stat_type == 'ipasn') {
								if ($data == 'null') {
									continue;
								} elseif ($data != 'Unknown' && !ctype_digit($data)) { 
									if (strpos($data, '| ') !== FALSE) {
										$ex		= explode('| ', $data, 3);
										$filter_value	= $ex[1];
										$data		= "{$ex[1]} | {$ex[2]}";
									} else {
										$ex		= explode(' ', $data, 2);
										$filter_value	= $ex[0];
										$data		= "{$ex[0]} | {$ex[1]}";
									}
								}
							}
							elseif ($stat_type == 'dnsbldomain') {
								$ex = explode(',', $data, 2);
								$filter_value = $ex[0];
							}
							elseif ($stat_type == 'dnsblevald') {
								$ex = explode(',', $data, 2);
								$filter_value = $ex[1];
							}

							if ($stat_type != 'ipdirection') {
								$btnsubmit = '<button type="submit" class="fa fa-filter button-icon"'
										. " name=\"filterlogentries_submit_{$stat_type}\""
										. " id=\"filterlogentries_submit_{$stat_type}\""
										. " value=\"{$filter_value}\" title=\"Filter Alerts for [ {$data} ]\"></button>";
							}

							// Collect GeoIP or DNSBL Type classification
							if ($stype[3]) {
								$subdata = explode(',', $data);
								if ($stat_type == 'dnsblevald') {
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

							if ($stat_type == 'dnsbldatehr' || $stat_type == 'dnsbldatehrmin') {
								$d = explode (' ', $data);
								$data = "{$d[0]} {$d[1]}&emsp;({$d[2]})";
							}

							if (!empty($data) && $data != 'Not available for HTTPS alerts') {

								// Report Local hostname if found
								if ($stat_type == 'ipsrcipout' || $stat_type == 'ipdstipin') {
									if (isset($local_hosts[$data])) {
										$hostname = "&emsp;<small>( {$local_hosts[$data]} )</small>";
									}
								}

								// Get external IP hostname and Resolved hostname
								elseif ($stat_type == 'ipsrcipin' || $stat_type == 'ipdstipout') {
									$title = '';
									if (strlen($subdata[2]) >= 45) {
										$title = "title=\"{$subdata[2]}\"";
										$subdata[2] = substr($subdata[2], 0, 45) . "<small>...</small>";
									}
									$hostname = "<br /><span $title}><small>{$subdata[2]}</small></span>";
								}
							}

							if ($stat_type == 'dnsblagent' && $data == 'Unknown') {
								$data = $pfb['dnsbl_py_blacklist'] ? 'DNSBL Webserver/VIP' : 'Not available for HTTPS alerts';
							}

							if ($stat_type == 'ipdstport') {
								$query_port = '&nbsp;<a class="fa fa-search icon-pointer" target="_blank"'
										. ' href="/pfblockerng/pfblockerng_threats.php?port=' . $data
										. '" title="Click for Threat Port Lookup [ ' . $data . ' ]"></a>';
							}

							elseif ($stat_type == 'ipdirection') {
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

			<!-- Display max table extry limit, if found -->
			<?php if ($max_table_entries): ?>
			<div>
				&emsp;
				<span class="text-warning" style="font-size:12px; background-color: #424242;"
					title="Table limit reached! Setting can be modified in widget Alert Settings, but may slow page refresh time.">
					<small>Displaying [ <?= $pfbmaxtable; ?> ] entries.</small>
				</span>
			</div>
			<?php endif; ?>

			<?php endif; ?>

		</div>
	</div>
	<?php endforeach; ?>
	</div>
</div>
</form>
	<?php endif;
endif;

// Refresh page every 60 secs
if ($alertrefresh == 'on') {

	$pageview = '?';
	if ($pfb['filterlogentries']) {
		$pageview = '&';
	}

	if (!empty($alert_view) && $alert_view != 'alert') {
		$pageview .= "view={$alert_view}";
	} else {
		$pageview = '';
	}

	$pfSense_url = '';
	if ($_SERVER['REQUEST_SCHEME'] == 'http' || $_SERVER['REQUEST_SCHEME'] == 'https') {
		$http_host	= pfb_filter($_SERVER['HTTP_HOST'], 1);
		$pfSense_url	= "{$_SERVER['REQUEST_SCHEME']}://{$http_host}";
	}

	// Refresh page with 'Filter options', if defined
	if ($pfb['filterlogentries']) {
		$refreshentries = urlencode(serialize($filterfieldsarray));
		print ("<meta id=\"AlertRefresh\" http-equiv=\"refresh\" content=\"60;url={$pfSense_url}/pfblockerng/pfblockerng_alerts.php?refresh={$refreshentries}{$pageview}\" />\n");
	}

	// Refresh page
	else {
		print ("<meta id=\"AlertRefresh\" http-equiv=\"refresh\" content=\"60;url={$pfSense_url}/pfblockerng/pfblockerng_alerts.php{$pageview}\" />\n");
	}
}

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
<?php
}

function d3_chart($pfbchartcnt, $pfbchartstyle, $pfbchart1, $pfbchart2) {

?>
<script type="text/javascript">
//<![CDATA[

var max_entries = "<?=$pfbchartcnt;?>"
var chart_style = "<?=$pfbchartstyle;?>"
var chart_c1	= "<?=$pfbchart1;?>"
var chart_c2	= "<?=$pfbchart2;?>"

build_chart(max_entries);

function build_chart(max_entries) {

	d3.select('#range').text(max_entries);
	d3.select("#svg").remove();
	d3.select('#chart').append('svg').attr('id', 'svg');

	var chart = new d3.csv('chart_stats.csv', function(error, data) {
		series1 = []
		data = data.slice(-max_entries);

		// Add filler bars if under 24 bars 
		var cnt = data.length
		if (cnt != 0 && cnt < 24) {
			for (i = (24 - cnt); i > 0; i--) {
				var filler = { edate: "Placeholder " +i, ecount: "0", series: "0" }
				data.push(filler)
			}
		}

		data.forEach(function (d){
			d.ecount = +d.ecount
			series1.push(d)
		})

		var finalData = [{
				key: "Series 1",
				values: series1,
				color: "#0000ff"
				}];

		nv.addGraph(function() {
			var chart = nv.models.discreteBarChart()
				.margin({top: 5, left: 65, right: 25, bottom: 60})
				.x(function (d) { return d.edate })
				.y(function (d) { return d.ecount })
				.showYAxis(true)
				.showXAxis(true);

			chart.xAxis
				.tickPadding(10)
				.axisLabel('Date (Hr) [ Found: ' + cnt + ' hours ]');

			chart.yAxis
				.tickFormat(d3.format('.0f'))
				.tickPadding(10)
				.axisLabel('Event(s)');

			//  Pantone Color Institute "Color of the Year"
			if (chart_style == 'multi') {
				var colors = [	"#9BB7D4", "#C74375", "#BF1932", "#7BC4C4", "#E2583E", "#53B0AE",
						"#DECDBE", "#9B1B30", "#5A5B9F", "#F0C05A", "#45B5AA", "#D94F70",
						"#DD4124", "#009473", "#B163A3", "#955251", "#F7CAC9", "#92A8D1",
						"#88B04B", "#5F4B8B", "#FF6F61", "#2484C1", "#65A620", "#7B6888" ];
			}

			// Greyscale
			else {
				var colors = [	"#E0E0E0", "#DCDCDC", "#D8D8D8", "#D3D3D3", "#D0D0D0", "#C8C8C8",
						"#C0C0C0", "#BEBEBE", "#B8B8B8", "#B0B0B0", "#A9A9A9", "#A8A8A8",
						"#A0A0A0", "#989898", "#909090", "#888888", "#808080", "#787878",
						"#707070", "#696969", "#686868", "#606060", "#585858", "#505050" ];
			}

			chart.color(function(d) {
				if (chart_style == 'greyscale' || chart_style == 'multi') {
					return colors[Number(d.edate.slice(-3,-1)).toString()];
				}
				else {
					if (d.edate.slice(-3,-1) == '00') {
						return chart_c1;
					} else {
						return chart_c2;
					}
				}
			});

			d3.select('#chart svg')
				.datum(finalData)
				.transition().duration(300)
				.call(chart);

			// Hide xAxis Labels
			xCnt = Math.max(3, Math.round((series1.length * 0.17) / 10) * 10)
			d3.selectAll(".tick text").attr("class", function(d,i) {
				if (isNaN(d) && i % xCnt != 0) {
					d3.select(this).style("opacity", 0);
				} else {
					d3.select(this).style("opacity", 1);
				}
			});

			nv.utils.windowResize(function() { chart.update() });
			return chart;
		});
	})
}
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

function dnsbl_customlist() {

	if (domain && dnsbl_customlist) {
		$('#dnsbl_add').val('true');
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

	// Confirm if the Suppression option is enabled
	var is_supp = "<?=$pfb['supp']?>";
	if (is_supp != 'on') {
		alert('The IP Suppression option has not been enabled. Please enable this option in the IP Tab to suppress this IP.');
		$(this).dialog('close');
	}

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

	if (mode == 'dnsbl') {
		title_text = 'Whitelist';
	} else {
		title_text = 'Block';
	}

	$('<div></Div>').appendTo('body')
	.html('<div><h6>Do you want to add a description?</h6></div>')
	.dialog({
		modal: true,
		autoOpen: true,
		resizable: false,
		closeOnEscape: true,
		width: 'auto',
		title: title_text + ' description:',
		position: { my: 'top', at: 'top' },
		buttons: {
			Yes: function () {
				var description = prompt('Please enter ' + title_text + ' description');
				$('#descr').val(description);
				$(this).dialog('close');
				if (mode == 'dnsbl') {
					dnsbl_whitelist();
				} else if (mode == 'ip') {
					ip_whitelist();
				} else {
					dnsbl_customlist();
				}
			},
			No: function () {
				$(this).dialog('close');
				if (mode == 'dnsbl') {
					dnsbl_whitelist();
				} else if (mode == 'ip') {
					ip_whitelist();
				} else {
					dnsbl_customlist();
				}
			},
			'Cancel': function (event, ui) {
				$(this).dialog('close');
			}
		}
	}).css('background-color','#ffd700');
	$("div[role=dialog]").find('button').addClass('btn-info btn-xs');
}


function select_whitelist(mode, permit_list) {

	var buttons = {};
	$.each(permit_list, function(index, val) {
		buttons[index + ') ' + val] = function() {
								// Rename 'Create new IP Whitelist'
								if (val.indexOf("Create new") >= 0) {
									val = val.replace('Create new ', 'NEW_');
								}
								if (mode == 'ip') {
									$('#table').val(val);
								} else {
									$('#dnsbl_customlist').val(val);
								}
								$(this).dialog('close');
								add_description(mode);
							};
	});
	buttons['Cancel'] = function() { $(this).dialog('close'); };

	if (mode == 'ip') {
		s_title = 'Whitelist';
		d_title = 'Select a Permit Whitelist Alias:';
	} else {
		s_title = 'DNSBL Customlist';
		d_title = 'Select a DNSBL Customlist Group:';
	}

	$('<div></div>').appendTo('body')
	.html('<div><h6>Select ' + s_title + ':</h6></div>')
	.dialog({
		modal: true,
		autoOpen: true,
		resizable: false,
		closeOnEscape: true,
		width: 'auto',
		title: d_title,
		position: { my: 'top', at: 'top' },
		width: 750,
		buttons: buttons
	}).css('background-color','#ffd700');
	$("div[role=dialog]").find('button').addClass('btn-info btn-xs');
}

// Change filterfield input fields to lightgrey
function pfb_chg_filerfields_bkgd() {
	$("[id^='filterlogentries_']").each(function() {

		if ($(this).attr("id").indexOf("submit") == -1 && $(this).val() != 'Apply Filter' && $(this).val() != 'Clear Filter') {
			if ($(this).val() != '') {
				$(this).css({"background-color": "#1976D2", "color": "white"});
			} else {
				$(this).css({"background-color": "", "color": "black"});
			}
		}
	});
}

events.push(function() {

	pfb_chg_filerfields_bkgd();
	$("[id^='filterlogentries_']").autocomplete({
		change: function(event,ui) {
			pfb_chg_filerfields_bkgd();
		}
	});

	// Rebuild D3 Chart on date range change
	$('[id=chartEvent]').click(function() {
		build_chart($(this).attr('value'));
	})

	// Pause Alert tab auto-refresh 
	$('[id=PauseRefresh]').click(function() {
		var metaId = $('meta[id=AlertRefresh]');
		var pr = $('[id=PauseRefresh]');

		if (metaId.attr('http-equiv') == 'refresh') {
			metaId.removeAttr('http-equiv');
			pr.removeClass('fa fa-pause-circle').addClass('fa fa-undo').attr('title', 'Resume Alerts Refresh');
			window.stop();
		} else {
			metaId.attr('http-equiv', 'refresh');
			pr.removeClass('fa fa-undo').addClass('fa fa-pause-circle').attr('title', 'Pause Alerts Refresh');
		}
	})

	// Redraw d3pie chart when table window was previously collapsed
	$('[id^=Alert_Stats_A_]').click(function() {

		// collect name of piechart to redraw
		var pieChart = this.id.replace('Alert_Stats_A_', '');

		if (pieChart == 'ipsrcipin') {
			pieChart_ipsrcipin.redraw();
		} else if (pieChart == 'ipsrcipout') {
			pieChart_ipsrcipout.redraw();
		} else if (pieChart == 'ipdstipin') {
			pieChart_ipdstipin.redraw();
		} else if (pieChart == 'ipdstipout') {
			pieChart_ipdstipout.redraw();
		} else if (pieChart == 'ipsrcport') {
			pieChart_ipsrcport.redraw();
		} else if (pieChart == 'ipdstport') {
			pieChart_ipdstport.redraw();
		} else if (pieChart == 'ipgeoip') {
			pieChart_ipgeoip.redraw();
		} else if (pieChart == 'ipasn') {
			pieChart_ipasn.redraw();
		} else if (pieChart == 'ipaliasname') {
			pieChart_ipaliasname.redraw();
		} else if (pieChart == 'ipfeed') {
			pieChart_ipfeed.redraw();
		} else if (pieChart == 'ipinterface') {
			pieChart_ipinterface.redraw();
		} else if (pieChart == 'ipprotocol') {
			pieChart_ipprotocol.redraw();
		} else if (pieChart == 'ipdirection') {
			pieChart_ipdirection.redraw();
		} else if (pieChart == 'ipdate') {
			pieChart_ipdate.redraw();

		} else if (pieChart == 'dnsbldomain') {
			pieChart_dnsbldomain.redraw();
		} else if (pieChart == 'dnsblevald') {
			pieChart_dnsblevald.redraw();
		} else if (pieChart == 'dnsblgptotal') {
			pieChart_dnsblgptotal.redraw();
		} else if (pieChart == 'dnsblgpblock') {
			pieChart_dnsblgblock.redraw();
		} else if (pieChart == 'dnsblfeed') {
			pieChart_dnsblfeed.redraw();
		} else if (pieChart == 'dnsblip') {
			pieChart_dnsblip.redraw();
		} else if (pieChart == 'dnsblagent') {
			pieChart_dnsblagent.redraw();
		} else if (pieChart == 'dnsbltld') {
			pieChart_dnsbltld.redraw();
		} else if (pieChart == 'dnsblwebtype') {
			pieChart_dnsblwebtype.redraw();
		} else if (pieChart == 'dnsblmode') {
			pieChart_dnsblmode.redraw();
		} else if (pieChart == 'dnsbldatehr') {
			pieChart_dnsbldatehr.redraw();
		} else if (pieChart == 'dnsbldatehrmin') {
			pieChart_dnsbldatehrmin.redraw();

		} else if (pieChart == 'replyorec') {
			pieChart_replyorec.redraw();
		} else if (pieChart == 'replyrec') {
			pieChart_replyrec.redraw();
		} else if (pieChart == 'replyttl') {
			pieChart_replyttl.redraw();
		} else if (pieChart == 'replydomain') {
			pieChart_replydomain.redraw();
		} else if (pieChart == 'replytld') {
			pieChart_replytld.redraw();
		} else if (pieChart == 'replytld2') {
			pieChart_replytld2.redraw();
		} else if (pieChart == 'replytld3') {
			pieChart_replytld3.redraw();
		} else if (pieChart == 'replysrcip') {
			pieChart_replysrcip.redraw();
		} else if (pieChart == 'replysrcipd') {
			pieChart_replysrcipd.redraw();
		} else if (pieChart == 'replydstip') {
			pieChart_replydstip.redraw();
		} else if (pieChart == 'replygeoip') {
			pieChart_replygeoip.redraw();
		} else if (pieChart == 'replydate') {
			pieChart_replydate.redraw();
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
					button_text = 'Whitelist';
					descr_type = 'dnsbl';
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
					descr_type = 'dnsbl';
					break;
				case 'dnsbl_add':
					button_text = 'Block';
					blocktype = 'dnsbl';
					descr_type = 'dns_reply';
					var dnsbl_customlist = arr.splice(3)
					break;
				default:
					return;
			}

			var buttons = {};
			buttons['1. Wildcard ' + button_text] = function() {
					$('#dnsbl_wildcard').val('true');
					$(this).dialog('close');

					if (DNSBLWT_Type == 'dnsbl_add') {
						select_whitelist('dns_reply', dnsbl_customlist);
					} else {
						add_description(descr_type);
					}
				};

			if (blocktype != 'TLD') {
				msg = 'Do you wish to Wildcard ' + button_text + ' [ .' + arr[2] + ' ] or only ' + button_text + ' [ ' + arr[2] + ' ]?';
				buttons['2. ' + button_text] = function() {
							$(this).dialog('close');
							if (DNSBLWT_Type == 'dnsbl_add') {
								select_whitelist('dns_reply', dnsbl_customlist);
							} else {
								add_description(descr_type);
							}
						};
			}
			else {
				msg = 'Do you wish to Wildcard Whitelist [ .' + arr[2] + ' ] or add it to the TLD Exclusion customlist?';
				buttons['2. Exclude'] = function() {
							$('#dnsbl_exclude').val('true');
							$(this).dialog('close');
							add_description(descr_type);
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
				title: 'Domain ' + button_text + 'ing:',
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
								select_whitelist('ip', permit_list);
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

	$('[id^=PFBIPWHITE]').click(function(event) {
		if (confirm(event.target.title)) {
			$('meta[http-equiv=refresh]').remove();
			var arr = this.id.split('|');
			$('#ip').val(arr[1]);

			var permit_list = arr.splice(2);
			select_whitelist('ip', permit_list);
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
