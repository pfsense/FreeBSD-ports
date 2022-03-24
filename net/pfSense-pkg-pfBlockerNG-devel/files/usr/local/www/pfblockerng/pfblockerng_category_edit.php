<?php
/*
 * pfblockerng_category_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2022 Rubicon Communications, LLC (Netgate)
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

require_once('guiconfig.inc');
require_once('globals.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');

global $config, $group, $pfb;
pfb_global();

$rowdata	= array();
$rowid		= 0;
$action		= $chg_state = '';
$disable_move	= FALSE;

if ($_GET) {
	if (isset($_GET['rowid']) && ctype_digit($_GET['rowid'])) {
		$rowid = $_GET['rowid'];
	}
	if (isset($_GET['type'])) {
		$gtype = $_GET['type'];
	}
	if (isset($_GET['act'])) {
		if ($_GET['act'] == 'add') {
			$action = 'add';
		} elseif ($_GET['act'] == 'addgroup') {
			$action = 'addgroup';
		}
	}
	if (isset($_GET['atype'])) {
		$atype = htmlspecialchars($_GET['atype']);
	}
}

if ($_POST) {
	if (isset($_POST['id']) && ctype_digit($_POST['id'])) {
		$id = $_POST['id'];
	}
	if (isset($_POST['rowid']) && ctype_digit($_POST['rowid'])) {
		$rowid = $_POST['rowid'];
	}
	if (isset($_POST['type'])) {
		$gtype = $_POST['type'];
	}
	if (isset($_POST['act'])) {
		if ($_POST['act'] == 'add') {
			$action = 'add';
		} elseif ($_POST['act'] == 'addgroup') {
			$action = 'addgroup';
		}
	}
	if (isset($_POST['atype'])) {
		$atype = htmlspecialchars($_POST['atype']);
	}
	if (isset($_POST['chgstate']) && $_POST['chgstate'] == 'Enable All') {
		$chg_state = TRUE;
	}

	if (isset($_POST['Lmove']) && isset($_POST['Xmove'])) {
		$Lmove = $_POST['Lmove'];
		$Xmove = $_POST['Xmove'];
	}
}

// Define variables for page
if (!empty($gtype)) {

	// Set 'active' GUI Tabs
	$active = array('ip' => FALSE, 'ipv4' => FALSE, 'ipv6' => FALSE, 'dnsbl' => FALSE, 'feeds' => FALSE);

	switch ($gtype) {
		case 'ipv4':
			$type		= 'IPv4';
			$conf_type	= 'pfblockernglistsv4';
			$suffix		= '_v4';
			$active		= array('ip' => TRUE, 'ipv4' => TRUE);
			break;
		case 'ipv6':
			$type		= 'IPv6';
			$conf_type	= 'pfblockernglistsv6';
			$suffix		= '_v6';
			$active		= array('ip' => TRUE, 'ipv6' => TRUE);
			break;
		case 'dnsbl':
			$type		= 'DNSBL';
			$conf_type	= 'pfblockerngdnsbl';
			$suffix		= '';
			$active		= array('dnsbl' => TRUE, 'feeds' => TRUE);
			break;
	}
}

if (($action == 'add' || $action == 'addgroup') && !empty($atype) && !isset($_POST['save'])) {

	$pfb_found	= FALSE;
	$disable_move	= TRUE;
	init_config_arr(array('installedpackages', $conf_type, 'config'));
	$rowdata	= $config['installedpackages'][$conf_type]['config'];
	$all_group = $new_group = array();

	$feed_info = convert_feeds_json();			// Load/convert Feeds (w/alternative aliasname(s), if user-configured
	if (is_array($feed_info) &&
	    substr($atype, 0, 9) != 'Whitelist') {
		foreach ($feed_info as $ftype => $info) {

			if (empty($info) || $gtype != $ftype) {
				continue;
			}

			foreach ($info as $aliasname => $data) {

				if (!isset($data['feeds'])) {
					continue;
				}

				foreach ($data['feeds'] as $feed) {

					// If an alternate URL is defined, add applicable URL
					if (isset($feed['alternate'])) {
						init_config_arr(array('installedpackages', 'pfblockerngglobal'));
						$selected = $config['installedpackages']['pfblockerngglobal']['feed_alt_' . strtolower($feed['header'])];
						$selected = str_replace('alt_', '', $selected);

						if ($feed['header'] != $selected) {
							foreach ($feed['alternate'] as $alt) {
								if ($alt['header'] == $selected) {
									$feed['header'] = $alt['header'];
									$feed['url']	= $alt['url'];
								}
							}
						}
					}

					if ($action == 'add' && $atype == $feed['header']) {

						// Find rowid
						if (!empty($rowdata)) {
							foreach ($rowdata as $rowid => $row) {
								if ($row['aliasname'] == $aliasname) {
									$pfb_found	= TRUE;
									$a_url		= $feed['url'];
									$a_header	= $feed['header'];
									break 4;
								}
							}
						}

						// No existing Alias found (add single feed to new Alias)
						$a_aliasname	= $aliasname;
						$a_description	= $data['description'];
						$a_cron		= $data['cron'];
						$a_url		= $feed['url'];
						$a_header	= $feed['header'];
					}
					elseif ($action == 'addgroup' && $atype == $aliasname) {

						$a_aliasname	= $aliasname;
						$a_description	= $data['description'];
						$a_cron		= $data['cron'];

						$new_group = array ( array(	'state' => 'Disabled',
										'url'	=> $feed['url'],
										'header'=> $feed['header'] ));
						$all_group = array_merge($all_group, $new_group);
					}
				}
			}
		}
	}

	if ($action == 'add') {

		// If not found, create new Alias/Group
		if (!$pfb_found) {

			if (isset($rowdata[0]) && !empty($rowdata[0])) {
				$rowid++;		// Create new row
			}
			$rowdata[$rowid]['aliasname']	= $a_aliasname;
			$rowdata[$rowid]['description']	= $a_description;
			$rowdata[$rowid]['cron']	= $a_cron;
		}
		if (!is_array($rowdata[$rowid]['row'])) {
			$rowdata[$rowid]['row'] = array();
		}
		$rowdata[$rowid]['row'][] = array(	'state' => 'Disabled',
							'url'	=> $a_url,
							'header'=> $a_header );
	}
	elseif ($action == 'addgroup') {
		$rowid = count($rowdata);

		// Create new IP Whitelist Alias via Reports Tab
		if (substr($atype, 0, 9) == 'Whitelist') {
			$rowdata[$rowid]['aliasname']	= 'Whitelist';
			$rowdata[$rowid]['description']	= 'pfBlockerNG Whitelist';
			$rowdata[$rowid]['cron']	= 'EveryDay';
			$rowdata[$rowid]['action']	= 'Permit_Outbound';

			// Extract Whitelisted IP and Description
			$data = explode('|', $atype);
			if (!empty($data[2])) {
				$custom_line = "{$data[1]} # {$data[2]}";
			} else {
				$custom_line = "{$data[1]}";
			}
			$rowdata[$rowid]['custom']	= base64_encode("{$custom_line}");
		}

		// Create new Alias via Feeds Tab
		else {
			$rowdata[$rowid]['aliasname']	= $a_aliasname;
			$rowdata[$rowid]['description']	= $a_description;
			$rowdata[$rowid]['cron']	= $a_cron;
			$rowdata[$rowid]['row']		= $all_group;
		}
	}
}

$pgtype = 'IP'; $l_pgtype = 'ip';
$pg_url = '/pfblockerng/pfblockerng_category.php?type=ipv4';

if ($gtype == 'dnsbl') {
	$pgtype = 'DNSBL'; $l_pgtype = 'dnsbl';
	$pg_url = '/pfblockerng/pfblockerng_dnsbl.php';
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext($pgtype), gettext($type));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', "{$pg_url}", '@self');

include_once('head.inc');
init_config_arr(array('installedpackages', $conf_type, 'config', 0));

// Validate input fields
if ($_POST && isset($_POST['save'])) {

	$pconfig = $_POST;
	$line = 1;
	if (isset($input_errors)) {
		unset($input_errors);
	}
	if (isset($savemsg)) {
		unset($savemsg);
	}

	if (isset($_REQUEST['savemsg'])) {
		unset($_REQUEST['savemsg']);
	}

	if (empty($_POST['aliasname'])) {
		$input_errors[] = 'Info: Name field must be defined.';
	}

	if (preg_match("/\W/", $_POST['aliasname'])) {
		$input_errors[] = 'Info: Name field cannot contain spaces, special or international characters.';
	}

	// IPv4/6 Aliasnames cannot exceed 31 characters in PF. ( pfB_ + aliasname + _v? )
	if ($gtype == 'ipv4' || $gtype == 'ipv6') {
		$len_post = strlen($_POST['aliasname']);
		if ($len_post > 24) {
			$input_errors[] = "Info: Name field cannot exceed 24 characters. [ {$len_post} characters submitted. ]";
		}
	}

	foreach ($_POST as $key => $value) {

		// Validate URL and Header field when a List is enabled
		if (strpos($key, 'state-') !== FALSE) {
			$k_field	= explode('-', $key);
			$key_1		= htmlspecialchars($k_field[1]);

			if ($value != 'Disabled' && empty($_POST["url-{$key_1}"])) {
				$input_errors[] = "{$type} Source Definitions, Line {$line}: Source field must be defined.";
			}

			if ($value != 'Disabled' && empty($_POST["header-{$key_1}"])) {
				$input_errors[] = "{$type} Source Definitions, Line {$line}: Header field must be defined.";
			}

			if ($value != 'Disabled' && preg_match("/\W/", $_POST["header-{$key_1}"])) {
				$input_errors[] = "{$type} Source Definitions, Line {$line}: "
							. "Header field cannot contain spaces, special or international characters.";
			}

			if ($value != 'Disabled' && strpos($_POST["url-{$key_1}"], '_API_KEY_') !== FALSE) {
				$input_errors[] = "{$type} Source Definitions, Line {$line}: "
							. "API key not defined! Add your subscripton API Key to the Source field URL or disable/remove feed.";
			}
			$line++;
		}

		// Validate MaxMind License Key
		if ($value == 'geoip' && strpos($key, 'format-') !== FALSE && empty($pfb['maxmind_key'])) {
			$input_errors[] = "{$type} Source Definitions, Line {$line}: "
				. 'MaxMind now requires a License Key! Review the IP tab: MaxMind settings for more information.';
		}
	}

	// Validate Adv. firewall rule settings
	foreach (array(	'aliasports_in' => 'Port In', 'aliasaddr_in' => 'Destination In',
			'aliasports_out' => 'Port Out', 'aliasaddr_out' => 'Destination Out') as $value => $auto_dir) {
		if (!empty($_POST[$value]) && !is_alias($_POST[$value])) {
			$input_errors[] = "Settings: Advanced {$auto_dir}bound Alias error - Must use an existing Alias";
		}
	}

	// Validate Adv. firewall rule 'Protocol' setting
	if (!empty($_POST['autoports_in']) || !empty($_POST['autoaddr_in'])) {
		if (empty($_POST['autoproto_in'])) {
			$input_errors[] = "Settings: Protocol setting cannot be set to 'Default' with Advanced Inbound firewall rule settings.";
		}
	}
	if (!empty($_POST['autoports_out']) || !empty($_POST['autoaddr_out'])) {
		if (empty($_POST['autoproto_out'])) {
			$input_errors[] = "Settings: Protocol setting cannot be set to 'Default' with Advanced Outbound firewall rule settings.";
		}
	}

	// Force 'Alias Native' setting to any Alias with 'Advanced Inbound/Outbound -Invert src/dst' settings.
	// This will bypass Deduplication and Reputation features.
	if ($_POST['action'] != 'Disabled') {
		if ($_POST['autoaddrnot_in'] == 'on' && $_POST['action'] != 'Alias_Native') {
			$input_errors[] = "Action setting must be defined as 'Alias Native' when using 'Invert Source'"
						. " with Advanced Inbound firewall rule settings.";
		}

		if ($_POST['autoaddrnot_out'] == 'on' && $_POST['action'] != 'Alias_Native') {
			$input_errors[] = "Action setting must be defined as 'Alias Native' when using 'Invert Destination'"
						. " with Advanced Outbound firewall rule settings.";
		}
	}

	// Avoid creating a permit rule on WAN with 'any'
	if ($_POST['action'] == 'Permit_Inbound' || $_POST['action'] == 'Permit_Both') {
		$pfb_warning = FALSE;
		if ($_POST['autoproto_in'] == '') {
			$pfb_warning = TRUE;
			$input_errors[] = "Warning: When using an Action setting of 'Permit Inbound or Permit Both',"
					. " you must configure the 'Advanced Inbound Custom Protocol' setting. The current setting of 'Any' is not allowed.";
		}
		if ($_POST['aliasports_in'] == '' && $_POST['aliasaddr_in'] == '') {
			$pfb_warning = TRUE;
			$input_errors[] = "Warning:  When using an Action setting of 'Permit Inbound or Permit Both',"
					. " you must configure at least one of 'Advanced Inbound Custom Port/Destination' settings.";
		}
		if ($pfb_warning) {
			$input_errors[] = '';
			$input_errors[] = '===> WARNING <===';
			$input_errors[] = "Improper Permit rules on the WAN can catastrophically impact the security of your network!";
		}
	}

	if (!$input_errors) {

		if (!is_array($config['installedpackages'][$conf_type]['config'][$rowid])) {
			$config['installedpackages'][$conf_type]['config'][$rowid] = array();
		}

		$config['installedpackages'][$conf_type]['config'][$rowid]['aliasname']		= $_POST['aliasname']			?: '';
		$config['installedpackages'][$conf_type]['config'][$rowid]['description']	= pfb_filter($_POST['description'], 1)	?: '';

		$config['installedpackages'][$conf_type]['config'][$rowid]['action']		= $_POST['action']			?: 'Disabled';
		$config['installedpackages'][$conf_type]['config'][$rowid]['cron']		= $_POST['cron']			?: 'Never';
		$config['installedpackages'][$conf_type]['config'][$rowid]['dow']		= $_POST['dow']				?: '';
		$config['installedpackages'][$conf_type]['config'][$rowid]['sort']		= $_POST['sort']			?: 'sort';

		if ($gtype == 'ipv4' || $gtype == 'ipv6') {
			$config['installedpackages'][$conf_type]['config'][$rowid]['aliaslog']		= $_POST['aliaslog']		?: 'enabled';
			$config['installedpackages'][$conf_type]['config'][$rowid]['stateremoval']	= $_POST['stateremoval']	?: 'enabled';

			$config['installedpackages'][$conf_type]['config'][$rowid]['autoaddrnot_in']	= $_POST['autoaddrnot_in']	?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['autoports_in']	= $_POST['autoports_in']	?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['aliasports_in']	= $_POST['aliasports_in']	?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['autoaddr_in']	= $_POST['autoaddr_in']		?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['autonot_in']	= $_POST['autonot_in']		?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['aliasaddr_in']	= $_POST['aliasaddr_in']	?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['autoproto_in']	= $_POST['autoproto_in']	?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['agateway_in']	= $_POST['agateway_in']		?: 'default';

			$config['installedpackages'][$conf_type]['config'][$rowid]['autoaddrnot_out']	= $_POST['autoaddrnot_out']	?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['autoports_out']	= $_POST['autoports_out']	?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['aliasports_out']	= $_POST['aliasports_out']	?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['autoaddr_out']	= $_POST['autoaddr_out']	?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['autonot_out']	= $_POST['autonot_out']		?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['aliasaddr_out']	= $_POST['aliasaddr_out']	?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['autoproto_out']	= $_POST['autoproto_out']	?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['agateway_out']	= $_POST['agateway_out']	?: 'default';

			$config['installedpackages'][$conf_type]['config'][$rowid]['suppression_cidr']	= $_POST['suppression_cidr']	?: 'Disabled';
			$config['installedpackages'][$conf_type]['config'][$rowid]['script_pre']	= $_POST['script_pre']		?: '';
			$config['installedpackages'][$conf_type]['config'][$rowid]['script_post']	= $_POST['script_post']		?: '';

			$config['installedpackages'][$conf_type]['config'][$rowid]['whois_convert']	= $_POST['whois_convert']	?: '';
		}
		else {
			$config['installedpackages'][$conf_type]['config'][$rowid]['logging']		= $_POST['logging']		?: 'Enabled';
			$config['installedpackages'][$conf_type]['config'][$rowid]['order']		= $_POST['order']		?: 'default';
			$config['installedpackages'][$conf_type]['config'][$rowid]['filter_alexa']	= $_POST['filter_alexa']	?: '';
		}

		// Set flag to update CustomList on next Cron|Force update|Force reload
		if (base64_decode($config['installedpackages'][$conf_type]['config'][$rowid]['custom']) != $_POST['custom']) {
			$action = $_POST['action'];
			$aname  = $_POST['aliasname'];

			pfb_determine_list_detail($action, '', $conf_type, $rowid);
			touch("{$pfbarr['folder']}/{$aname}_custom{$suffix}.update");
		}

		init_config_arr(array('installedpackages', $conf_type, 'config', $rowid));
		$config['installedpackages'][$conf_type]['config'][$rowid]['custom']			= base64_encode($_POST['custom']) ?: '';

		$rowhelper_exist = array();

		foreach ($_POST as $key => $value) {

			// Parse 'rowhelper' tables and save new values
			if (strpos($key, '-') !== FALSE) {
				$k_field = explode('-', $key);

				// Collect all rowhelper keys
				$rowhelper_exist[$k_field[1]] = '';

				if ($k_field[0] == 'url' && in_array($_POST["format-{$k_field[1]}"], array( 'whois', 'asn', 'geoip' ))) {
					$value = pfb_filter($value, 1);
				} elseif ($k_field[0] == 'url') {
					$value = pfb_filter($value, 2);
				} else {
					$value = htmlspecialchars($value);
				}

				init_config_arr(array('installedpackages', $conf_type, 'config', $rowid, 'row', $k_field[1]));
				$config['installedpackages'][$conf_type]['config'][$rowid]['row'][$k_field[1]][$k_field[0]] = $value;
			}
		}

		// Remove all undefined rowhelpers
		if (is_array($config['installedpackages'][$conf_type]['config'][$rowid]['row'])) {
			foreach ($config['installedpackages'][$conf_type]['config'][$rowid]['row'] as $r_key => $row) {
				if (!isset($rowhelper_exist[$r_key])) {
					unset($config['installedpackages'][$conf_type]['config'][$rowid]['row'][$r_key]);
				}
			}
		}

		// Remove unused xml tag
		if (isset($config['installedpackages'][$conf_type]['config'][$rowid]['infolists'])) {
			unset($config['installedpackages'][$conf_type]['config'][$rowid]['infolists']);
		}

		init_config_arr(array('installedpackages', $conf_type, 'config', $rowid));
		$name = $config['installedpackages'][$conf_type]['config'][$rowid]['aliasname'] ?: 'Unknown';
		$savemsg = "Saved [ Type:{$type}, Name:{$name} ] configuration";
		write_config("pfBlockerNG: {$savemsg}");
		header("Location: /pfblockerng/pfblockerng_category_edit.php?type={$gtype}&rowid={$rowid}&savemsg={$savemsg}");
		exit;
	}
	else {
		print_input_errors($input_errors);

		// Restore $_POST data on input errors
		foreach ($_POST as $key => $value) {
			if (strpos($key, '-') !== FALSE) {
				$k_field = explode('-', $key);
				$rowdata[$rowid]['row'][$k_field[1]][$k_field[0]] = $value;
			}
			else {
				$pconfig[$key] = $value;
			}
		}
	}
}
else {

	if ($action == 'addgroup' || $action == 'add') {
		;
	} else {
		init_config_arr(array('installedpackages', $conf_type, 'config'));
		$rowdata = &$config['installedpackages'][$conf_type]['config'];
	}

	$pconfig = array();

	$pconfig['aliasname']		= $rowdata[$rowid]['aliasname'];
	$pconfig['description']		= $rowdata[$rowid]['description'];
	$pconfig['action']		= $rowdata[$rowid]['action'];
	$pconfig['cron']		= $rowdata[$rowid]['cron'];
	$pconfig['dow']			= $rowdata[$rowid]['dow'];
	$pconfig['sort']		= $rowdata[$rowid]['sort'];

	if ($gtype == 'ipv4' || $gtype == 'ipv6') {
		$pconfig['aliaslog']		= $rowdata[$rowid]['aliaslog'];
		$pconfig['stateremoval']	= $rowdata[$rowid]['stateremoval'];

		$pconfig['autoaddrnot_in']	= $rowdata[$rowid]['autoaddrnot_in'];
		$pconfig['autoports_in']	= $rowdata[$rowid]['autoports_in'];
		$pconfig['aliasports_in']	= $rowdata[$rowid]['aliasports_in'];
		$pconfig['autoaddr_in']		= $rowdata[$rowid]['autoaddr_in'];
		$pconfig['autonot_in']		= $rowdata[$rowid]['autonot_in'];
		$pconfig['aliasaddr_in']	= $rowdata[$rowid]['aliasaddr_in'];
		$pconfig['autoproto_in']	= $rowdata[$rowid]['autoproto_in'];
		$pconfig['agateway_in']		= $rowdata[$rowid]['agateway_in'];

		$pconfig['autoaddrnot_out']	= $rowdata[$rowid]['autoaddrnot_out'];
		$pconfig['autoports_out']	= $rowdata[$rowid]['autoports_out'];
		$pconfig['aliasports_out']	= $rowdata[$rowid]['aliasports_out'];
		$pconfig['autoaddr_out']	= $rowdata[$rowid]['autoaddr_out'];
		$pconfig['autonot_out']		= $rowdata[$rowid]['autonot_out'];
		$pconfig['aliasaddr_out']	= $rowdata[$rowid]['aliasaddr_out'];
		$pconfig['autoproto_out']	= $rowdata[$rowid]['autoproto_out'];
		$pconfig['agateway_out']	= $rowdata[$rowid]['agateway_out'];

		$pconfig['suppression_cidr']	= $rowdata[$rowid]['suppression_cidr'];
		$pconfig['script_pre']		= $rowdata[$rowid]['script_pre'];
		$pconfig['script_post']		= $rowdata[$rowid]['script_post'];

		$pconfig['whois_convert']	= $rowdata[$rowid]['whois_convert'];
	}
	else {
		$pconfig['logging']		= $rowdata[$rowid]['logging'];
		$pconfig['order']		= $rowdata[$rowid]['order'];
		$pconfig['filter_alexa']	= $rowdata[$rowid]['filter_alexa'];
	}

	$pconfig['custom']		= base64_decode($rowdata[$rowid]['custom']);
}


// Move selected table row(s) to anchor row
if (isset($Lmove) and isset($Xmove) && isset($rowdata[$rowid]['row'])) {

	$disable_move	= TRUE;
	$move = $final	= array();
	foreach ($rowdata[$rowid]['row'] as $key => $row) {
		if (isset($Lmove[$key])) {
			$move[] = $row;	// Collect row(s) to move

			$pre = TRUE;
			if ($Lmove[$key] > $Xmove) {
				$pre = FALSE;
			}
		}
	}

	foreach ($rowdata[$rowid]['row'] as $key => $row) {

		// Skip moved row(s)
		if (isset($Lmove[$key]) && $Xmove != $key) {
			continue;
		}

		if ($Xmove == $key) {
			if ($pre && $Lmove[$key] != $Xmove) {
				$final[] = $row;
			}

			$final = array_merge($final, $move);

			if (!$pre && $Lmove[$key] != $Xmove) {
				$final[] = $row;
			}
			continue;
		}
		$final[] = $row;
	}

	$rowdata[$rowid]['row'] = $final;
	$savemsg = 'The selected row(s) have been moved.';
	write_config("pfBlockerNG: {$gtype} - Rows(s) moved");
	header("Location: /pfblockerng/pfblockerng_category_edit.php?type={$gtype}&rowid={$rowid}&savemsg={$savemsg}");
}

// Define default Alerts Tab href link (Top row)
$get_req = pfb_alerts_default_page();

$tab_array	= array();
$tab_array[]	= array(gettext('General'),	false,			'/pfblockerng/pfblockerng_general.php');
$tab_array[]	= array(gettext('IP'),		$active['ip'],		'/pfblockerng/pfblockerng_ip.php');
$tab_array[]	= array(gettext('DNSBL'),	$active['dnsbl'],	'/pfblockerng/pfblockerng_dnsbl.php');
$tab_array[]	= array(gettext('Update'),	false,			'/pfblockerng/pfblockerng_update.php');
$tab_array[]	= array(gettext('Reports'),	false,			"/pfblockerng/pfblockerng_alerts.php{$get_req}");
$tab_array[]	= array(gettext('Feeds'),	false,			'/pfblockerng/pfblockerng_feeds.php');
$tab_array[]	= array(gettext('Logs'),	false,			'/pfblockerng/pfblockerng_log.php');
$tab_array[]	= array(gettext('Sync'),	false,			'/pfblockerng/pfblockerng_sync.php');
display_top_tabs($tab_array, true);

$tab_array 	= array();
if ($gtype == 'ipv4' || $gtype == 'ipv6') {
	$tab_array[]	= array(gettext('IPv4'),	$active['ipv4'],	'/pfblockerng/pfblockerng_category.php?type=ipv4');
	$tab_array[]	= array(gettext('IPv6'),	$active['ipv6'],	'/pfblockerng/pfblockerng_category.php?type=ipv6');
	$tab_array[]	= array(gettext('GeoIP'),	false,			'/pfblockerng/pfblockerng_category.php?type=geoip');
	$tab_array[]	= array(gettext('Reputation'),	false,			'/pfblockerng/pfblockerng_reputation.php');
}
else {
	$tab_array[]	= array(gettext('DNSBL Groups'),	$active['feeds'],	'/pfblockerng/pfblockerng_category.php?type=dnsbl');
	$tab_array[]	= array(gettext('DNSBL Category'),	false,			'/pfblockerng/pfblockerng_blacklist.php');
	$tab_array[]	= array(gettext('DNSBL SafeSearch'),	false,			'/pfblockerng/pfblockerng_safesearch.php');
}
display_top_tabs($tab_array, true);

if (empty($gtype)) {
	print ('No Category type selected.');
	exit;
}

if (isset($savemsg)) {
	print_info_box($savemsg);
}

if (isset($_REQUEST['savemsg'])) {
	$savemsg = htmlspecialchars($_REQUEST['savemsg']);
	print_info_box($savemsg);
}

$form = new Form("Save {$type} Settings");
$form->addGlobal(new Form_Input('atype', 'atype', 'hidden', "{$atype}"));
$form->addGlobal(new Form_Input('type', 'type', 'hidden', "{$gtype}"));
$form->addGlobal(new Form_Input('rowid', 'rowid', 'hidden', "{$rowid}"));
$form->addGlobal(new Form_Input('act', 'act', 'hidden', "{$action}"));
$form->addGlobal(new Form_Input('id', 'id', 'hidden', "{$id}"));

// Build 'Shortcut Links' section
$section = new Form_Section('Info');
$section->addInput(new Form_StaticText(
	'Links',
	'<small>'
	. '<a href="/firewall_aliases.php" target="_blank">Firewall Aliases</a>&emsp;'
	. '<a href="/firewall_rules.php" target="_blank">Firewall Rules</a>&emsp;'
	. '<a href="/status_logs_filter.php" target="_blank">Firewall Logs</a></small>'
));

$group = new Form_Group('Name / Description');
$group->add(new Form_Input(
	'aliasname',
	'Name (No spaces or special/Int. chars)',
	'text',
	$pconfig['aliasname']
))->setWidth(3);

$group->add(new Form_Input(
	'description',
	'Description',
	'text',
	$pconfig['description']
))->setWidth(6);
$section->add($group);

$length_txt = '';
if ($gtype == 'ipv4' || $gtype == 'ipv6') {
	$length_txt = "&nbsp;( Max 24 characters )";
}

$section->addInput(new Form_StaticText(
	NULL,
	"Enter Name{$length_txt} and Description."
	. '<div class="infoblock alert-info clearfix">'
	. 'Do not prefix the Alias Name with <strong>pfB_</strong> or <strong>pfb_</strong><br />'
	. 'Do not add a <strong>_v4</strong> or <strong>_v6</strong> suffix to the Alias Name.<br />'
	. '<strong>Names must be unique.</strong><br />'
	. '<strong>International, special or space characters are not allowed.</strong>'
	. '</div>'));

$form->add($section);

// Build 'Source Definitions' section
$section = new Form_Section("{$type} Source Definitions");

// Add empty row placeholder if no rows defined
if (empty($rowdata[$rowid]['row'])) {
	$rowdata = array();
	$rowdata[$rowid]['row'] = array (	array(	'format'	=> 'Auto',
							'state' 	=> 'Disabled',
							'url'		=> '',
							'header'	=> '' ) );
}

// Sort row by Header/Label field followed by Enabled/Disabled State settings
if (empty($rowdata[$rowid]['sort']) || $rowdata[$rowid]['sort'] == 'sort') {
	$new_disabled = $new_enabled = array();
	foreach ($rowdata[$rowid]['row'] as $key => $data) {
		if ($data['state'] == 'Disabled') {
			$new_disabled[$data['header']] = $data;
		} else {
			$new_enabled[$data['header']] = $data;
		}
	}
	ksort($new_disabled, SORT_NATURAL | SORT_FLAG_CASE);
	ksort($new_enabled, SORT_NATURAL | SORT_FLAG_CASE);

	$new = $new_enabled + $new_disabled;
	foreach ($new as $key => $data) {
		$final[] = $data;
	}
	$rowdata[$rowid]['row'] = $final;
}

$numrows	= (count($rowdata[$rowid]['row']) -1) ?: 0;
$rowcounter	= 0;
$failed		= '';	// Failed download help text

foreach ($rowdata[$rowid] as $tags) {

	if (!isset($tags[$rowcounter]['state'])) {
		continue;
	}

	foreach ($tags as $r_id => $row) {

		$line_label = 'XXXX';	// Used to signal JQuery removal of html label column (To allow utilizing of full page width)

		$group = new Form_Group($line_label);
		$group->addClass('repeatable');

		if ($rowdata[$rowid]['sort'] == 'no-sort') {

			$move_anchor = "<input type=\"checkbox\" name=\"Lmove[{$r_id}]\" value=\"{$r_id}\" id=\"{$r_id}\" />
						<button type=\"submit\" class=\"fa fa-anchor button-icon\" name=\"Xmove\" value=\"{$r_id}\" id=\"{$r_id}\"
						title=\"Move checked entries before this anchor\"></button>";

			$group->add(new Form_StaticText(
					'',
					"&nbsp;<sub>" . str_replace('X', '&nbsp; ', str_pad($r_id +1, 2, 'X', STR_PAD_LEFT)) . "</sub>&nbsp;" . $move_anchor
			))->setWidth(1);
		}

		$formats = '';
		if ($gtype == 'ipv4' || $gtype == 'ipv6') {
			$formats = array( 'auto' => 'Auto', 'geoip' => 'GeoIP', 'regex' => 'Regex', 'whois' => 'Whois', 'asn' => 'ASN', 'rsync' => 'RSync' );
		} elseif ($gtype == 'dnsbl') {
			$formats = array( 'auto' => 'Auto', 'rsync' => 'RSync' );
		}

		if (!empty($formats)) {
			$group->add(new Form_Select(
					'format-' . $r_id,
					'',
					$row['format'],
					$formats
			))->setHelp(($numrows == $rowcounter) ? 'Format' : NULL)
			  ->setAttribute('size', 1)
			  ->setAttribute('style', 'width: auto')
			  ->setAttribute('onclick', '')
			  ->setWidth(1);
		}

		// Enable state field (via POST)
		if ($chg_state) {
			$row['state'] = 'Enabled';
		}

		$group->add(new Form_Select(
			'state-' . $r_id,
			'',
			$row['state'],
			['Enabled' => 'ON', 'Disabled' => 'OFF', 'Hold' => 'HOLD', 'Flex' => 'FLEX']
		))->setHelp(($numrows == $rowcounter) ? 'State' : NULL)
		  ->setAttribute('style', 'width: auto')
		  ->setAttribute('size', 1)
		  ->setWidth(1);

		$group->add(new Form_Input(
				'url-' . $r_id,
				'',
				'text',
				$row['url']
		))->setHelp(($numrows == $rowcounter) ? 'Source' : NULL)
		  ->setWidth(5);

		// Indicate any failed downloads with yellow select field background
		if (strpos($pconfig['action'], 'Deny_') !== FALSE) {
			$folder = "{$pfb['denydir']}";
		} elseif (strpos($pconfig['action'], 'Permit_') !== FALSE) {
			$folder = "{$pfb['permitdir']}";
		} elseif (strpos($pconfig['action'], 'Match_') !== FALSE) {
			$folder = "{$pfb['matchdir']}";
		} elseif (strpos($pconfig['action'], 'unbound') !== FALSE) {
			$folder = "{$pfb['dnsdir']}";
		} else {
			$folder = FALSE;
		}

		$failed_bg = '';
		if ($folder && file_exists("{$folder}/{$row['header']}{$suffix}.fail")) {
			$failed_bg = 'background-color: #FFFF00;';
			$failed = "<span style=\"color: black; background-color: #FFFF00; border-style: groove;\">&emsp;Failed download(s) highlighted in yellow.&emsp;</span>";
		}

		$group->add(new Form_Input(
				'header-' . $r_id,
				'',
				'text',
				$row['header']
		))->setHelp(($numrows == $rowcounter) ? 'Header/Label' : NULL)
		  ->setAttribute('style', $failed_bg)
		  ->setWidth(3);

		// Delete row button
		$group->add(new Form_Button(
			'deleterow' . $rowcounter,
			'Delete',
			NULL,
			'fa-trash'
		))->removeClass('btn-primary')
		  ->addClass('btn-warning btn-xs')->setWidth(1);

		$rowcounter++;
		$section->add($group);
	}
}

// Build Guideline text
$infotxt = '<dl class="dl-horizontal">';

if ($gtype == 'ipv4' || $gtype == 'ipv6') {
	$infotxt .= '<dt>Format:</dt>
			<dd>Select the Format type:
			<dl class="dl-horizontal">
				<dt>Auto:</dt><dd>Default parser</dd>
				<dt>GeoIP:</dt><dd>GeoIP Country ISO (autocomplete form)</dd>
				<dt>Regex:</dt><dd>\'Regex\' style parsing (ie: html Lists)</dd>
				<dt>Whois:</dt><dd>Convert a Domain name into its respective IP addresses.</dd>
				<dt>ASN:</dt><dd>Convert an ASN into its respective IP addresses.<br />
							ASN list via cidr-report.org (autocomplete form - 3 character minimum)</dd>
				<dt>Rsync:</dt><dd>RSync Lists</dd>
			</dl>';
}

$infotxt .= '	</dd>
	<dt>State:</dt>
		<dd>Select the run State:
			<dl class="dl-horizontal">
				<dt>On:</dt><dd>Enable List</dd>
				<dt>Off:</dt><dd>Disable List</dd>
				<dt>Hold:</dt><dd>Download List only once</dd>
				<dt>Flex:</dt><dd>Downgrade the SSL Connection (Not Recommended)</dd>
			</dl>
		</dd>
	<dt>Source:</dt>
		<dd>Select Source type:
			<dl class="dl-horizontal">
				<dt>URL:</dt>
		';

if ($gtype == 'ipv4' || $gtype == 'ipv6') {
	$infotxt .= '			<dd>External link to source&emsp;(ie:&nbsp;
						<a target="_blank" href="https://rules.emergingthreats.net/blockrules/compromised-ips.txt">ET Compromised</a>,
						<a target="_blank" href="https://rules.emergingthreats.net/fwrules/emerging-Block-IPs.txt">ET Blocked</a>,
						<a target="_blank" href="https://www.spamhaus.org/drop/drop.txt">Spamhaus Drop</a>)
					</dd>
				<dt>Local file:</dt>
					<dd>http(s)://127.0.0.1/filename
						&emsp;<strong>or</strong>&emsp; /var/db/pfblockerng/filename
					</dd>
				<dt>GeoIP ISO:</dt>
					<dd>Utilize the autocomplete <strong>GeoIP Format</strong> option
						or manually enter the full URL as /usr/local/share/GeoIP/cc/US_v4.txt
						&emsp;(Change \'US\' to required code)
					</dd>
				<dt>Whois:</dt><dd>Domain name to IP Address&emsp;(ie: facebook.com)<br />
					Note: This will only return a partial list of resolved IPs for each Domain!</dd>
				<dt>ASN:</dt><dd>ASN to IP Address&emsp;(ie: AS32934)
						&emsp;(<a target="_blank" href="https://asn.cymru.com/">Click for IP<->ASN Lookup via Team Cymru.com</a>)</dd>
			</dl>
		</dd>';
}
else {
	$infotxt .= '			<dd>External link to source&emsp;(ie:&nbsp;
						<a target="_blank" href="https://pgl.yoyo.org/adservers/serverlist.php?hostformat=;showintro=0">yoyo</a>,&nbsp;
						<a target="_blank" href="https://someonewhocares.org/hosts/hosts">SomeoneWhoCares</a>,&nbsp;
						<a target="_blank" href="https://adaway.org/hosts.txt">Adaway</a>)
					</dd>
				<dt>Local file:</dt>
					<dd>http(s)://127.0.0.1/filename &emsp;<strong>or</strong>&emsp; /var/db/pfblockerng/filename
					</dd>
			</dl>
		</dd>';
}

$infotxt .= '<dt>Header/Label:</dt>
		<dd>This field must be <u>unique.</u> This names the file and is referenced in the widget.
			&emsp;(ie: Spamhaus_drop, Spamhaus_edrop)
		</dd>';

if ($gtype == 'ipv4' || $gtype == 'ipv6') {
	$infotxt .= '<dt>Note:</dt>
			<dd>Source lists musts follow the syntax below:<br />
				<strong>Network ranges:</strong>172.16.1.0-172.16.1.255&emsp;
				<strong>IP Address:</strong>172.16.1.10&emsp;
				<strong>CIDR:</strong>172.16.1.0/24
			</dd>';
}

$infotxt .= '	</dl>';

// Guideline infoblock
$section->addInput(new Form_StaticText(
		'',
		"{$failed}"
		. '&emsp;Click here for Guidelines ---><div class="infoblock alert-info clearfix">'
		. $infotxt . '</div>'
));

// Add 'Change state' and 'Add Row' buttons
$btnadd = '';
$btnadd = new Form_Button(
	'addrow',
	'Add',
	null,
	'fa-plus'
);
$btnadd->removeClass('btn-primary')
	->addClass('btn-xs btn-success')
	->setAttribute('title', "Add new entry to {$type} Source Definition table");

$btnstate = new Form_Button(
	'chgstate',
	'Enable All',
	NULL,
	'fa-toggle-on'
);
$btnstate->removeClass('btn-primary')
	 ->addClass('btn-primary btn-xs')
	 ->setAttribute('title', 'Click to Enable all State fields');

$group = new Form_Group(NULL);
$group->add(new Form_StaticText(
	'',
	$btnadd . '&emsp;' . $btnstate));
$section->add($group);

// Print Customization section
if ($gtype == 'ipv4' || $gtype == 'ipv6') {
	$list_array = array(	'Disabled' => 'Disabled', 'Deny_Inbound' => 'Deny Inbound', 'Deny_Outbound' => 'Deny Outbound',
				'Deny_Both' => 'Deny Both', 'Permit_Inbound' => 'Permit Inbound', 'Permit_Outbound' => 'Permit Outbound',
				'Permit_Both' => 'Permit Both', 'Match_Inbound' => 'Match Inbound', 'Match_Outbound' => 'Match Outbound',
				'Match_Both' => 'Match Both', 'Alias_Deny' => 'Alias Deny', 'Alias_Permit' => 'Alias Permit',
				'Alias_Match' => 'Alias Match', 'Alias_Native' => 'Alias Native'
				);

	$action_txt = "Default: <strong>Disabled</strong>
			<br />For Non-Alias type rules you must define the appropriate <strong>Firewall 'Auto' Rule Order</strong> option.
			<br />Click here for more info -->
			<div class=\"infoblock alert-info clearfix\">
				Select the <strong>Action</strong> for Firewall Rules on lists you have selected.<br /><br />

				<strong><u>'Disabled' Rules:</u></strong> Disables selection and does nothing to selected Alias.<br /><br />

				<strong><u>'Deny' Rules:</u></strong><br />
				'Deny' rules create high priority 'block' or 'reject' rules on the stated interfaces. They don't change the 'pass' rules on other
				interfaces. Typical uses of 'Deny' rules are:<br />
					<ul>
						<li><strong>Deny Both</strong> -
							blocks all traffic in both directions, if the source or destination IP is in the block list
						</li>
						<li><strong>Deny Inbound/Deny Outbound</strong> -
							blocks all traffic in one direction <u>unless</u> it is part of a session started by
							traffic sent in the other direction. Does not affect traffic in the other direction.
						</li>
						<li>One way 'Deny' rules can be used to selectively block <u>unsolicited</u> incoming
							(new session) packets in one direction, while still allowing <u>deliberate</u> outgoing
							sessions to be created in the other direction.
						</li>
					</ul>

				<strong><u>'Permit' Rules:</u></strong><br />
				'Permit' rules create high priority 'pass' rules on the stated interfaces. They are the opposite of Deny rules, and don't create
				any 'blocking' effect anywhere. They have priority over all Deny rules. Typical uses of 'Permit' rules are:<br />
					<ul>
						<li><strong>To ensure</strong> that traffic to/from the listed IPs will <u>always</u> be allowed in the
							stated directions. They override <u>almost all other</u> Firewall rules on the stated interfaces.
						</li>
						<li><strong>To act as a whitelist</strong> for Deny rule exceptions, for example if a large IP range
							or pre-created blocklist blocks a few IPs that should be accessible.
						</li>
					</ul>

				<strong><u>'Match' Rules:</u></strong><br />
				'Match' or 'Log' only the traffic on the stated interfaces. This does not Block or Reject. It just Logs the traffic.
				<ul>
					<li><strong>Match Both</strong> - Matches all traffic in both directions,
						if the source or destination IP is in the list.
					</li>
					<li><strong>Match Inbound/Match Outbound</strong> - Matches all traffic in one direction only.
					</li>
				</ul>

				<strong><u>'Alias' Rules:</u></strong><br />
				<strong>'Alias'</strong> rules create an <a href=\"/firewall_aliases.php\">alias</a> for the list (and do nothing else).
				This enables a pfBlockerNG list to be used by name, in any firewall rule or pfSense function, as desired.
					<ul>
						<li><strong>Options - Alias Deny,&nbsp; Alias Permit,&nbsp; Alias Match,&nbsp; Alias Native</strong></li>
						<li>'Alias Deny' can use De-Duplication and Reputation Processes if configured.</li>
						<li>'Alias Permit' and 'Alias Match' will be saved in the Same folder as the other Permit/Match Auto-Rules</li>
						<li>'Alias Native' lists are kept in their Native format without any modifications.</li></ul>

				<span class=\"text-danger\">Note: </span><ul>
					When manually creating 'Alias' type firewall rules; Prefix the Firewall rule Description with <strong>pfb_</strong>
					This will ensure that that Dashboard widget reports those statistics correctly. <strong>Do not</strong> 
					prefix with (pfB_) as those Rules will be auto-removed by package when 'Auto' rules are defined.</ul>
			</div>";
}
else {
	$list_array = array(	'Disabled' => 'Disabled', 'unbound' => 'Unbound' );
	$action_txt = "Default: <strong>Disabled</strong><br />Select <strong>Unbound</strong> to enable 'Domain Name' blocking for this Alias.";
}

$form->add($section);

$section = new Form_Section('Settings');
$section->addInput(new Form_Select(
	'action',
	'Action',
	$pconfig['action'],
	$list_array
))->setHelp($action_txt)
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'cron',
	'Update Frequency',
	$pconfig['cron'],
	[	'Never' => 'Never', '01hour' => 'Every hour', '02hours' => 'Every 2 hours', '03hours' => 'Every 3 hours',
		'04hours' => 'Every 4 hours', '06hours' => 'Every 6 hours', '08hours' => 'Every 8 hours',
		'12hours' => 'Every 12 hours', 'EveryDay' => 'Once a day', 'Weekly' => 'Weekly'
	]
))->setHelp('Default: <strong>Never</strong><br />'
		. 'Select how often List files will be downloaded. <strong>This must be within the Cron Interval/Start Hour settings.</strong>')
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'dow',
	'Weekly (Day of Week)',
	$pconfig['dow'],
	['1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday', '7' => 'Sunday']
))->setHelp('Default: <strong>Monday</strong><br />Select the \'Weekly\' ( Day of the Week ) to Update <br />'
		. 'This is only required for the \'Weekly\' Frequency Selection. The 24 Hour Download \'Time\' will be used.')
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'sort',
	'Auto-Sort Header field',
	$pconfig['sort'],
	['sort' => 'Enable auto-sort', 'no-sort' => 'Disable auto-sort']
))->setHelp('Automatic sorting of the Header/Label field grouped by the Enabled/Disabled State field setting.')
  ->setAttribute('style', 'width: auto');

if ($gtype == 'ipv4' || $gtype == 'ipv6') {

	$section->addInput(new Form_Select(
		'aliaslog',
		'Enable Logging',
		$pconfig['aliaslog'],
		['enabled' => 'Enabled', 'disabled' => 'Disabled']
	))->setHelp('Default: <strong>Enable</strong><br />Select - Logging to Status: System Logs: FIREWALL ( Log )<br />'
		. 'This can be overriden by the \'Global Logging\' Option in the General Tab.')
	  ->setAttribute('style', 'width: auto');

	$section->addInput(new Form_Select(
		'stateremoval',
		'States Removal',
		$pconfig['stateremoval'],
		['enabled' => 'Enabled', 'disabled' => 'Disabled']
	))->setHelp('With the \'Kill States\' option (General Tab), you can disable States removal for this Alias.');

	$form->add($section);

	// Print Advanced Firewall Rule Settings (Inbound and Outbound) section
	foreach (array( 'In' => 'Source', 'Out' => 'Destination') as $adv_mode => $adv_type) {

		$advmode = strtolower($adv_mode);

		// Collect all pfSense 'Port' Aliases
		$portslist = $networkslist = '';
		if (!empty($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $alias) {
				if ($alias['type'] == 'port') {
					$portslist .= "{$alias['name']},";
				} elseif ($alias['type'] == 'network') {
					$networkslist .= "{$alias['name']},";
				}
			}
		}
		$ports_list	= trim($portslist, ',');
		$networks_list	= trim($networkslist, ',');

		$section = new Form_Section("Advanced {$adv_mode}bound Firewall Rule Settings", "adv{$advmode}boundsettings", COLLAPSIBLE|SEC_CLOSED);
		$section->addInput(new Form_StaticText(
			NULL,
			"<span class=\"text-danger\">Note:</span>&nbsp; In general, Auto-Rules are created as follows:<br />
				<dl class=\"dl-horizontal\">
					<dt>{$adv_mode}bound</dt><dd>'any' port, 'any' protocol, 'any' destination and 'any' gateway</dd>
				</dl>
				Configuring the Adv. {$adv_mode}bound Rule settings, will allow for more customization of the {$adv_mode}bound Auto-Rules."));

		$section->addInput(new Form_Checkbox(
			'autoaddrnot_' . $advmode,
			"Invert {$adv_type}",
			NULL,
			$pconfig['autoaddrnot_' . $advmode] === 'on' ? true:false,
			'on'
		))->setHelp("Option to invert the sense of the match. ie - Not (!) {$adv_type} Address(es)");

		$group = new Form_Group("Custom DST Port");
		$group->add(new Form_Checkbox(
			'autoports_' . $advmode,
			'Custom DST Port',
			NULL,
			$pconfig['autoports_' . $advmode] === 'on' ? true:false,
			'on'
		))->setHelp('Enable')
		  ->setWidth(2);

		$group->add(new Form_Input(
			'aliasports_' . $advmode,
			'Custom Port',
			'text',
			$pconfig["aliasports_{$advmode}"]
		))->setHelp('<a target="_blank" href="/firewall_aliases.php?tab=port">Click Here to add/edit Aliases</a>
				Do not manually enter port numbers.<br />Do not use \'pfB_\' in the Port Alias name.')
		  ->setWidth(8);
		$section->add($group);

		if ($adv_type == 'Source') {
			$custom_location = 'Destination';
		} else {
			$custom_location = 'Source';
		}

		$group = new Form_Group("Custom {$custom_location}");
		$group->add(new Form_Checkbox(
			'autoaddr_' . $advmode,
			"Custom {$custom_location}",
			NULL,
			$pconfig["autoaddr_{$advmode}"] === 'on' ? true:false,
			'on'
		))->setHelp('Enable')->setWidth(1);

		$group->add(new Form_Checkbox(
			'autonot_' . $advmode,
			NULL,
			NULL,
			$pconfig["autonot_{$advmode}"] === 'on' ? true:false,
			'on'
		))->setHelp('Invert')->setWidth(1);

		$group->add(new Form_Input(
			'aliasaddr_' . $advmode,
			"Custom {$custom_location}",
			'text',
			$pconfig['aliasaddr_' . $advmode]
		))->sethelp('<a target="_blank" href="/firewall_aliases.php?tab=ip">Click Here to add/edit Aliases</a>'
			. 'Do not manually enter Addresses(es).<br />Do not use \'pfB_\' in the \'IP Network Type\' Alias name.<br />'
			. "Select 'invert' to invert the sense of the match. ie - Not (!) {$custom_location} Address(es)")
		  ->setWidth(8);
		$section->add($group);

		$group = new Form_Group('Custom Protocol');
		$group->add(new Form_Select(
			'autoproto_' . $advmode,
			NULL,
			$pconfig['autoproto_' . $advmode],
			['' => 'any', 'tcp' => 'TCP', 'udp' => 'UDP', 'tcp/udp' => 'TCP/UDP']
		))->setHelp("<strong>Default: any</strong><br />Select the Protocol used for {$adv_mode}bound Firewall Rule(s).<br />"
				. "<span class=\"text-danger\">Note:</span>&nbsp;Do not use 'any' with Adv. {$adv_mode}bound Rules as it will bypass these settings!");
		$section->add($group);

		$group = new Form_Group('Custom Gateway');
		$group->add(new Form_Select(
			'agateway_' . $advmode,
			NULL,
			$pconfig['agateway_' . $advmode],
			pfb_get_gateways()
		))->setHelp('Select alternate Gateway or keep \'default\' setting.');

		$section->add($group);
		$form->add($section);
	}

	// Collect pre/post processing scripts
	$listpre = $listpost = array();
	$indexdir = '/usr/local/pkg/pfblockerng/';
	if (is_dir("{$indexdir}")) {
		
		if ($gtype == 'ipv4' || $gtype == 'ipv6') {
			$list_prefix = 'ip';
		} else {
			$list_prefix = 'dnsbl';
		}

		$list = glob("{$indexdir}/{$list_prefix}_pre_*.{sh,py}", GLOB_BRACE);
		if (!empty($list)) {
			foreach ($list as $line) {
				$file = pathinfo($line, PATHINFO_BASENAME);
				$l = array($file => $file);
				$listpre = array_merge($listpre, $l);
			}
		}

		$list = glob("{$indexdir}/{$list_prefix}_post_*.{sh,py}", GLOB_BRACE);
		if (!empty($list)) {
			foreach ($list as $line) {
				$file = pathinfo($line, PATHINFO_BASENAME);
				$l = array($file => $file);
				$listpost = array_merge($listpost, $l);
			}
		}
	}

	$listpre = array_merge(array('' => 'None'), $listpre);
	$listpre_size = count($listpre) ?: '1';

	$listpost = array_merge(array('' => 'None'), $listpost);
	$listpost_size = count($listpost) ?: '1';

	if ($gtype == 'ipv4') {

		// Print Advanced Tunables section
		$section = new Form_Section('Advanced Tuneables', 'advancedtunable', COLLAPSIBLE|SEC_CLOSED);
		$section->addInput(new Form_StaticText(
			NULL,
			'These are \'Advanced\' settings and are typically best left at Default settings!')
		);

		$list = array('Disabled' => 'Disabled') + array_combine(range(1, 17, -1), range(1, 17, -1));

		$section->addInput(new Form_Select(
			'suppression_cidr',
			'Suppression CIDR Limit',
			$pconfig['suppression_cidr'],
			$list
		))->setHelp('When suppression is enabled, this option will limit the CIDR block for this entire IPv4 Alias'
				. '(Excluding the Custom List IP addresses)<br />Default: <strong>Disabled</strong> (No CIDR limit)')
		  ->setAttribute('style', 'width: auto');

		$section->addInput(new Form_Select(
			'script_pre',
			'Pre-process Script',
			$pconfig['script_pre'],
			$listpre
		))->sethelp("Pre-processing Shell script after download.<br />"
		  	. "Script location: /usr/local/pkg/pfblockerng/<strong>ip_pre_SCRIPT NAME.sh|py</strong> or <strong>dnsbl_pre_SCRIPT NAME.sh|py</strong>")
		  ->setAttribute('style', 'width: auto')
		  ->setAttribute('size', $listpre_size);

		$section->addInput(new Form_Select(
			'script_post',
			'Post-process Script',
			$pconfig['script_post'],
			$listpost
		))->sethelp("Post-processing Shell script after download.<br />"
			. "Script location: /usr/local/pkg/pfblockerng/<strong>ip_post_SCRIPT NAME.sh|py</strong> or <strong>dnsbl_post_SCRIPT name.sh|py</strong>")
		  ->setAttribute('style', 'width: auto')
		  ->setAttribute('size', $listpost_size);

		$form->add($section);
	}
}
else {
	$section->addInput(new Form_Select(
		'order',
		'Group Order',
		$pconfig['order'],
		['default' => 'Default', 'primary' => 'Primary']
	))->setHelp('Default: <strong>Default</strong><br />'
			. 'When set as \'Primary\', this DNSBL Group will be processed before all other DNSBL Groups/Category(s)')
	  ->setAttribute('style', 'width: auto');

	if ($pfb['dnsbl_py_blacklist']) {
		$log_text = 'Default: <strong>DNSBL WebServer/VIP</strong><br />'
				. '&#8226 <strong>DNSBL WebServer/VIP</strong>, Domains are sinkholed to the DNSBL VIP and logged via the DNSBL WebServer.<br />'
				. '&#8226 <strong>Null Blocking (no logging)</strong>, Utilize \'0.0.0.0\' with no logging.<br />'
				. '&#8226 <strong>Null Blocking (logging)</strong>, Utilize \'0.0.0.0\' with logging.<br /><br />'
				. 'Blocked domains will be reported to the Alert/Python Block Table.<br />'
				. 'Enabling the "Global Logging/Blocking mode" in the DNSBL Tab will override this setting!<br />'
				. 'A \'Force Reload - DNSBL\' is required for changes to take effect';

		$log_options = ['enabled'	=> 'DNSBL WebServer/VIP',
				'disabled'	=> 'Null Blocking (no logging)',
				'disabled_log'	=> 'Null Blocking (logging)'];
	} else {
		$log_text = 'Default: <strong>Enabled</strong><br />'
				. '&#8226 When \'Enabled\', Domains are sinkholed to the DNSBL VIP and logged via the DNSBL WebServer.<br />'
				. '&#8226 When \'Disabled\', <strong>\'0.0.0.0\'</strong> will be used instead of the DNSBL VIP.<br />'
				. 'Enabling the "Global Logging/Blocking mode" in the DNSBL Tab will override this setting!<br />'
				. 'A \'Force Reload - DNSBL\' is required for changes to take effect';

		$log_options = ['enabled'	=> 'DNSBL WebServer/VIP',
				'disabled'	=> 'Null Blocking (no logging)'];
	}

	$section->addInput(new Form_Select(
		'logging',
		'Logging / Blocking Mode',
		$pconfig['logging'],
		$log_options
	))->setHelp($log_text)
	  ->setAttribute('style', 'width: auto');

	$section->addInput(new Form_Checkbox(
		'filter_alexa',
		'TOP1M Whitelist',
		'Enable',
		$pconfig['filter_alexa'] === 'on' ? true:false,
		'on'
	))->setHelp('Filter Group via TOP1M');

	$form->add($section);
}

if ($gtype == 'ipv4' || $gtype == 'ipv6') {

	$custom_txt = "<span class=\"text-danger\">Note: </span>&nbsp;Custom List can be used in <strong>ONE</strong> of two ways:<br />
			<ul>
				1. {$type} addresses entered directly into the custom list, as per the required format.<br />
				2. Domain names or AS numbers, which will be converted into their respective {$type} addresses.
			</ul>";
}
else {
	$custom_txt = "No Regex Entries Allowed!
			<div class=\"infoblock alert-info clearfix\">
				Enter one &emsp; <strong>'Domain Name'</strong> &emsp; per line<br /><br />
				You may use '<strong>#</strong>' after any Domain name to add comments. example ( ads.google.com # Block Google Ads )<br />
				This List is stored as 'Base64' format in the config.xml file.
			</div>";
}

$custom_txt = '<div id="Customlist"></div>' . $custom_txt;

// Print Custom List TextArea section
$section = new Form_Section("{$type} Custom_List", str_replace(' ', '', $type) . 'customlist', COLLAPSIBLE|SEC_CLOSED);
$section->addInput(new Form_StaticText(
	NULL,
	$custom_txt));

if ($gtype == 'ipv4' || $gtype == 'ipv6') {
	$section->addInput(new Form_Checkbox(
		'whois_convert',
		'Enable Domain/AS',
		NULL,
		$pconfig['whois_convert'] === 'on' ? true:false,
		'on'
	));

	// Collect list of GeoIP ISOs for Source field lookup
	$geoip_isos = '';
	if (file_exists("{$pfb['geoip_isos']}")) {
		$geoip_isos = trim(@file_get_contents("{$pfb['geoip_isos']}"));
	}
}

// Create page anchor for IP Suppression List
$section->addInput(new Form_StaticText(
	NULL,
	'<div id="Custom"></div>'));

$section->addInput(new Form_Textarea(
	'custom',
	'',
	$pconfig['custom']
))->removeClass('form-control')
  ->addClass('row-fluid col-sm-12')
  ->setAttribute('rows', '30')
  ->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa;');

$form->add($section);
print ($form);

if ($gtype == 'dnsbl') {
	print_callout('<p><strong>Click to SAVE Settings and/or Rule edits.&emsp;Changes are applied via CRON or \'Force Update|Reload\' only!</strong><br /><br />
			DNSBL Category Feeds are processed first, followed by the DNSBL Groups.<br />
			DNSBL Groups can be prioritized first, by selecting the \'Group Order\' option.</p>');
}
else {
	print_callout('<p><strong>Setting changes are applied via CRON or \'Force Update|Reload\' only!</strong></p>');
}

?>
<script type="text/javascript">
//<![CDATA[

var gtype = "<?=$gtype?>";
var disable_move = "<?=$disable_move?>";
var pagetype = null;

if (gtype == 'ipv4' || gtype == 'ipv6') {

	var pagetype = 'advanced';

	var action = "<?=$action;?>";
	var atype = "<?=$atype;?>";

	// Auto-Complete for Adv. In/Out Address Select boxes
	var plist = "<?=$ports_list?>";
	var portsarray = plist.split(',');
	var nlist = "<?=$networks_list?>";
	var networksarray = nlist.split(',');

	// GeoIP ISOs Auto-Complete for Source (URL) field lookup
	var geoip = "<?=$geoip_isos?>";
	var geoiparray = geoip.split(',');

	// ASN Auto-Complete for Source (URL) field lookup
	var asnlist = "<?=$pfb['asn_list']?>";
}
else if (gtype == 'dnsbl') {
	var pagetype = 'dnsbl';
}

//]]
</script>
<script src="pfBlockerNG.js" type="text/javascript"></script>
<?php include('foot.inc');?>
