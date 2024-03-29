<?php
/*
 * snort_alerts.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2023 Bill Meeks
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

require_once("guiconfig.inc");
require_once("/usr/local/pkg/snort/snort.inc");

$supplist = array();
$alertsid = array();
$dropsid = array();
$rejectsid = array();
$snortlogdir = SNORTLOGDIR;
$filterlogentries = FALSE;

function snort_is_alert_globally_suppressed($list, $gid, $sid) {

	/************************************************/
	/* Checks the passed $gid:$sid to see if it has */
	/* been globally suppressed.  If true, then any */
	/* "track by_src" or "track by_dst" options are */
	/* disabled since they are overridden by the    */
	/* global suppression of the $gid:$sid.         */
	/************************************************/

	/* If entry has a child array, then it's by src or dst ip. */
	/* So if there is a child array or the keys are not set,   */
	/* then this gid:sid is not globally suppressed.           */
	if (is_array($list[$gid][$sid]))
		return false;
	elseif (!isset($list[$gid][$sid]))
		return false;
	else
		return true;
}

function snort_add_supplist_entry($suppress) {

	/************************************************/
	/* Adds the passed entry to the Suppress List   */
	/* for the active interface.  If a Suppress     */
	/* List is defined for the interface, it is     */
	/* used.  If no list is defined, a new default  */
	/* list is created using the interface name.    */
	/*                                              */
	/* On Entry:                                    */
	/*   $suppress --> suppression entry text       */
	/*                                              */
	/* Returns:                                     */
	/*   TRUE if successful or FALSE on failure     */
	/************************************************/

	global $instanceid;

	$a_suppress = config_get_path('installedpackages/snortglobal/suppress/item', []);

	$found_list = false;

	/* If no Suppress List is set for the interface, then create one with the interface name */
	if (empty(config_get_path("installedpackages/snortglobal/rule/{$instanceid}/suppresslistname", '')) || config_get_path("installedpackages/snortglobal/rule/{$instanceid}/suppresslistname", '') == 'default') {
		$s_list = array();
		$s_list['uuid'] = uniqid();
		$s_list['name'] = config_get_path("installedpackages/snortglobal/rule/{$instanceid}/interface") . "suppress" . "_" . $s_list['uuid'];
		$s_list['descr']  =  "Auto-generated list for Alert suppression";
		$s_list['suppresspassthru'] = base64_encode($suppress);
		$a_suppress[] = $s_list;
		config_set_path("installedpackages/snortglobal/rule/{$instanceid}/suppresslistname", $s_list['name']);
		$found_list = true;
		$list_name = $s_list['name'];
	} else {
		/* If we get here, a Suppress List is defined for the interface so see if we can find it */
		foreach ($a_suppress as $a_id => $alist) {
			if ($alist['name'] == config_get_path("installedpackages/snortglobal/rule/{$instanceid}/suppresslistname", '')) {
				$found_list = true;
				$list_name = $alist['name'];
				if (!empty($alist['suppresspassthru'])) {
					$tmplist = base64_decode($alist['suppresspassthru']);
					$tmplist .= "\n{$suppress}";
					$alist['suppresspassthru'] = base64_encode($tmplist);
					$a_suppress[$a_id] = $alist;
				}
				else {
					$alist['suppresspassthru'] = base64_encode($suppress);
					$a_suppress[$a_id] = $alist;
				}
			}
		}
	}

	/* If we created a new list or updated an existing one, save the change, */
	/* tell Snort to load it, and return true; otherwise return false.       */
	if ($found_list) {
		config_set_path('installedpackages/snortglobal/suppress/item', $a_suppress);
		write_config("Snort pkg: modified Suppress List {$list_name}.");
		sync_snort_package_config();
		snort_reload_config(config_get_path("installedpackages/snortglobal/rule/{$instanceid}", ''));
		return true;
	}
	else
		return false;
}

function snort_escape_filter_regex($filtertext) {
	/* If the caller (user) has not already put a backslash before a slash, to escape it in the regex, */
	/* then this will do it. Take out any "\/" already there, then turn all ordinary "/" into "\/".  */
	return str_replace('/', '\/', str_replace('\/', '/', $filtertext));
}

function snort_match_filter_field($flent, $fields, $exact_match = FALSE) {
	foreach ($fields as $key => $field) {
		if ($field == null)
			continue;

		// Only match whole field string when
		// performing an exact match.
		if ($exact_match) {
			if ($flent[$key] == $field) {
				return true;
			}
			else {
				return false;
			}
		}

		if ((strpos($field, '!') === 0)) {
			$field = substr($field, 1);
			$field_regex = snort_escape_filter_regex($field);
			if (@preg_match("/{$field_regex}/i", $flent[$key]))
				return false;
		}
		else {
			$field_regex = snort_escape_filter_regex($field);
			if (!@preg_match("/{$field_regex}/i", $flent[$key]))
				return false;
		}
	}
	return true;
}


if (isset($_POST['instance']) && is_numericint($_POST['instance']))
	$instanceid = $_POST['instance'];
elseif (isset($_GET['instance']) && is_numericint($_GET['instance']))
	$instanceid = htmlspecialchars($_GET['instance']);
elseif (isset($_POST['id']))
	$instanceid = $_POST['id'];

if (empty($instanceid) || !is_numericint($instanceid))
	$instanceid = 0;

$a_instance = config_get_path("installedpackages/snortglobal/rule/{$instanceid}", []);
$snort_uuid = $a_instance['uuid'];
$if_real = get_real_interface($a_instance['interface']);

// Load up the arrays of force-enabled and force-disabled SIDs
$enablesid = snort_load_sid_mods($a_instance['rule_sid_on']);
$disablesid = snort_load_sid_mods($a_instance['rule_sid_off']);

// Load up the arrays of forced-alert, forced-drop or forced-reject
// rules as applicable to the current IPS mode.
if ($a_instance['blockoffenders7'] == 'on' && $a_instance['ips_mode'] == 'ips_mode_inline') {
	$alertsid = snort_load_sid_mods($a_instance['rule_sid_force_alert']);
	$dropsid = snort_load_sid_mods($a_instance['rule_sid_force_drop']);

	// REJECT forcing is only applicable to Inline IPS Mode
	if ($a_instance['ips_mode'] == 'ips_mode_inline' ) {
		$rejectsid = snort_load_sid_mods($a_instance['rule_sid_force_reject']);
	}
}

$pconfig = array();
$pconfig['instance'] = $instanceid;
if (config_get_path('installedpackages/snortglobal/alertsblocks')) {
	$pconfig['arefresh'] = config_get_path('installedpackages/snortglobal/alertsblocks/arefresh');
	$pconfig['alertnumber'] = config_get_path('installedpackages/snortglobal/alertsblocks/alertnumber');
}

if (empty($pconfig['alertnumber']))
	$pconfig['alertnumber'] = '250';
if (empty($pconfig['arefresh']))
	$pconfig['arefresh'] = 'off';
$anentries = $pconfig['alertnumber'];

# --- AJAX REVERSE DNS RESOLVE Start ---
if (isset($_POST['resolve'])) {
	$ip = strtolower($_POST['resolve']);
	$res = (is_ipaddr($ip) ? gethostbyaddr($ip) : '');
	
	if ($res && $res != $ip)
		$response = array('resolve_ip' => $ip, 'resolve_text' => $res);
	else
		$response = array('resolve_ip' => $ip, 'resolve_text' => gettext("Cannot resolve"));
	
	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}
# --- AJAX REVERSE DNS RESOLVE End ---

# Check for persisted filtering of alerts log entries and populate
# the required $filterfieldsarray when persisting filtered entries.
if ($_POST['persist_filter'] == "yes" && !empty($_POST['persist_filter_content'])) {
	$filterlogentries = TRUE;
	$persist_filter_log_entries = "yes";
	$filterlogentries_exact_match = $_POST['persist_filter_exact_match'];
	$filterfieldsarray = json_decode($_POST['persist_filter_content'], TRUE);
}
else {
	$filterlogentries = FALSE;
	$persist_filter_log_entries = "";
	$filterfieldsarray = array();
}

if ($_POST['filterlogentries_submit']) {
	// Set flag for filtering alert entries
	$filterlogentries = TRUE;
	$persist_filter_log_entries = "yes";

	// Set 'exact match only' flag if enabled
	if ($_POST['filterlogentries_exact_match'] == 'on') {
		$filterlogentries_exact_match = TRUE;
	}
	else {
		$filterlogentries_exact_match = FALSE;
	}

	// -- IMPORTANT --
	// Note the order of these fields must match the order decoded from the alerts log
	$filterfieldsarray = array();
	$filterfieldsarray[0] = $_POST['filterlogentries_time'] ? $_POST['filterlogentries_time'] : null;
	$filterfieldsarray[1] = $_POST['filterlogentries_gid'] ? $_POST['filterlogentries_gid'] : null;
	$filterfieldsarray[2] = $_POST['filterlogentries_sid'] ? $_POST['filterlogentries_sid'] : null;
	$filterfieldsarray[3] = null;
	$filterfieldsarray[4] = $_POST['filterlogentries_description'] ? $_POST['filterlogentries_description'] : null;
	$filterfieldsarray[5] = $_POST['filterlogentries_protocol'] ? $_POST['filterlogentries_protocol'] : null;
	// Remove any zero-length spaces added to the IP address that could creep in from a copy-paste operation
	$filterfieldsarray[6] = $_POST['filterlogentries_sourceipaddress'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_sourceipaddress']) : null;
	$filterfieldsarray[7] = $_POST['filterlogentries_sourceport'] ? $_POST['filterlogentries_sourceport'] : null;
	// Remove any zero-length spaces added to the IP address that could creep in from a copy-paste operation
	$filterfieldsarray[8] = $_POST['filterlogentries_destinationipaddress'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_destinationipaddress']) : null;
	$filterfieldsarray[9] = $_POST['filterlogentries_destinationport'] ? $_POST['filterlogentries_destinationport'] : null;
	$filterfieldsarray[10] = null;
	$filterfieldsarray[11] = $_POST['filterlogentries_classification'] ? $_POST['filterlogentries_classification'] : null;
	$filterfieldsarray[12] = $_POST['filterlogentries_priority'] ? $_POST['filterlogentries_priority'] : null;
	$filterfieldsarray[13] = $_POST['filterlogentries_action'] ? $_POST['filterlogentries_action'] : null;
	$filterfieldsarray[14] = null;
}

if ($_POST['filterlogentries_clear']) {
	$filterlogentries = TRUE;
	$persist_filter_log_entries = "";
	$filterfieldsarray = array();
}

if ($_POST['save']) {
	config_set_path('installedpackages/snortglobal/alertsblocks/arefresh', $_POST['arefresh'] ? 'on' : 'off');
	config_set_path('installedpackages/snortglobal/alertsblocks/alertnumber', $_POST['alertnumber']);
	config_set_path("installedpackages/snortglobal/rule/{$instanceid}", $a_instance);
	write_config("Snort pkg: updated ALERTS tab settings.");
	header("Location: /snort/snort_alerts.php?instance={$instanceid}");
	exit;
}

if ($_POST['mode'] == 'todelete') {
	$ip = "";
	if($_POST['ip']) {
		$ip = $_POST['ip'];
		if (is_ipaddr($_POST['ip'])) {
			exec("/sbin/pfctl -t snort2c -T delete {$ip}");
			$savemsg = gettext("Host IP address {$ip} has been removed from the Blocked Hosts Table.");
		}
	}
}

if (($_POST['mode'] == 'addsuppress_srcip' || $_POST['mode'] == 'addsuppress_dstip' || $_POST['mode'] == 'addsuppress') && is_numeric($_POST['sidid']) && is_numeric($_POST['gen_id'])) {
	if ($_POST['mode'] == 'addsuppress_srcip')
		$method = "by_src";
	elseif ($_POST['mode'] == 'addsuppress_dstip')
		$method = "by_dst";
	else
		$method ="all";

	// See which kind of Suppress Entry to create
	switch ($method) {
		case "all":
			if (empty($_POST['descr']))
				$suppress = "suppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}\n";
			else
				$suppress = "#{$_POST['descr']}\nsuppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}\n";
			$success = gettext("An entry for 'suppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}' has been added to the Suppress List.");
			break;
		case "by_src":
		case "by_dst":
			// Check for valid IP addresses, exit if not valid
			if (is_ipaddr($_POST['ip'])) {
				if (empty($_POST['descr']))
					$suppress = "suppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}, track {$method}, ip {$_POST['ip']}\n";
				else  
					$suppress = "#{$_POST['descr']}\nsuppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}, track {$method}, ip {$_POST['ip']}\n";
				$success = gettext("An entry for 'suppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}, track {$method}, ip {$_POST['ip']}' has been added to the Suppress List.");
			}
			else {
				$input_errors[] = gettext("An invalid IP address was passed as a Suppress List parameter.");
			}
			break;
		default:
			header("Location: /snort/snort_alerts.php?instance={$instanceid}");
			exit;
	}

	if (!$input_errors) {
		/* Add the new entry to the Suppress List and signal Snort to reload config */
		if (snort_add_supplist_entry($suppress)) {
			snort_reload_config($a_instance);
			$savemsg = $success;
			/* Give Snort a couple seconds to reload the configuration */
			sleep(2);
		}
		else
			$input_errors[] = gettext("Suppress List '{$a_instance['suppresslistname']}' is defined for this interface, but it could not be found!");
	}
}

if ($_POST['mode'] == 'togglesid' && is_numeric($_POST['sidid']) && is_numeric($_POST['gen_id'])) {
	// Get the GID and SID tags embedded in the clicked rule icon.
	$gid = $_POST['gen_id'];
	$sid= $_POST['sidid'];

	// See if the target SID is in our list of modified SIDs,
	// and toggle it if present.
	array_del_path($enablesid, "{$gid}/{$sid}");
	if (array_get_path($disablesid, "{$gid}/{$sid}")) {
		array_del_path($disablesid, "{$gid}/{$sid}");
	} else {
		array_set_path($disablesid, "{$gid}/{$sid}", 'disablesid');
	}

	// Write the updated enablesid and disablesid values to the config file.
	$tmp = "";
	foreach (array_keys($enablesid) as $k1) {
		foreach (array_keys($enablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_instance['rule_sid_on'] = $tmp;
	else				
		unset($a_instance['rule_sid_on']);

	$tmp = "";
	foreach (array_keys($disablesid) as $k1) {
		foreach (array_keys($disablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_instance['rule_sid_off'] = $tmp;
	else				
		unset($a_instance['rule_sid_off']);

	/* Update the config.xml file. */
	config_set_path("installedpackages/snortglobal/rule/{$instanceid}", $a_instance);
	write_config("Snort pkg: User-forced rule state override applied for rule {$gid}:{$sid} on ALERTS tab for interface {$a_instance['interface']}.");

	/*************************************************/
	/* Update the snort.conf file and rebuild the    */
	/* rules for this interface.                     */
	/*************************************************/
	$rebuild_rules = true;
	snort_generate_conf($a_instance);
	$rebuild_rules = false;

	/* Soft-restart Snort to live-load the new rules */
	snort_reload_config($a_instance);

	/* Give Snort a couple seconds to reload the configuration */
	sleep(2);

	$savemsg = gettext("The state for rule {$gid}:{$sid} has been modified.  Snort is 'live-reloading' the new rules list.  Please wait at least 15 secs for the process to complete before toggling additional rules.");
}

if (isset($_POST['rule_action_save']) && $_POST['mode'] == "toggle_action" && isset($_POST['ruleActionOptions']) && is_numeric($_POST['sidid']) && is_numeric($_POST['gen_id'])) {

	// Get the GID:SID tags embedded in the clicked rule icon.
	$gid = $_POST['gen_id'];
	$sid = $_POST['sidid'];

	// Get the posted rule action
	$action = $_POST['ruleActionOptions'];

	// Put the target SID in the appropriate lists of modified
	// SID actions based on the requested action; if default
	// action is requested, remove the SID from all SID modified
	// action lists.
	switch ($action) {
		case "action_default":
			array_del_path($alertsid, "{$gid}/{$sid}");
			array_del_path($dropsid, "{$gid}/{$sid}");
			array_del_path($rejectsid, "{$gid}/{$sid}");
			break;

		case "action_alert":
			array_set_path($alertsid, "{$gid}/{$sid}", "alertsid");
			array_del_path($dropsid, "{$gid}/{$sid}");
			array_del_path($rejectsid, "{$gid}/{$sid}");
			break;

		case "action_drop":
			array_set_path($dropsid, "{$gid}/{$sid}", "dropsid");
			array_del_path($alertsid, "{$gid}/{$sid}");
			array_del_path($rejectsid, "{$gid}/{$sid}");
			break;

		case "action_reject":
			array_set_path($rejectsid, "{$gid}/{$sid}", "rejectsid");
			array_del_path($alertsid, "{$gid}/{$sid}");
			array_del_path($dropsid, "{$gid}/{$sid}");
			break;

		default:
			$input_errors[] = gettext("WARNING - unknown rule action of '{$action}' passed in $_POST parameter.  No change made to rule action.");
	}

	if (!$input_errors) {
		// Write the updated forced rule action values to the config file.
		$tmp = "";
		foreach (array_keys($alertsid) as $k1) {
			foreach (array_keys($alertsid[$k1]) as $k2)
				$tmp .= "{$k1}:{$k2}||";
		}
		$tmp = rtrim($tmp, "||");

		if (!empty($tmp))
			$a_instance['rule_sid_force_alert'] = $tmp;
		else
			unset($a_instance['rule_sid_force_alert']);

		$tmp = "";
		foreach (array_keys($dropsid) as $k1) {
			foreach (array_keys($dropsid[$k1]) as $k2)
				$tmp .= "{$k1}:{$k2}||";
		}
		$tmp = rtrim($tmp, "||");

		if (!empty($tmp))
			$a_instance['rule_sid_force_drop'] = $tmp;
		else
			unset($a_instance['rule_sid_force_drop']);

		$tmp = "";
		foreach (array_keys($rejectsid) as $k1) {
			foreach (array_keys($rejectsid[$k1]) as $k2)
				$tmp .= "{$k1}:{$k2}||";
		}
		$tmp = rtrim($tmp, "||");

		if (!empty($tmp))
			$a_instance['rule_sid_force_reject'] = $tmp;
		else
			unset($a_instance['rule_sid_force_reject']);

		/* Update the config.xml file. */
		config_set_path("installedpackages/snortglobal/rule/{$instanceid}", $a_instance);
		write_config("Snort pkg: User-forced rule action override applied for rule {$gid}:{$sid} on ALERTS tab for interface {$a_instance['interface']}.");

		/*************************************************/
		/* Update the snort.conf file and rebuild the    */
		/* rules for this interface.                     */
		/*************************************************/
		$rebuild_rules = true;
		snort_generate_conf($a_instance);
		$rebuild_rules = false;

		/* Signal Snort to live-load the new rules */
		snort_reload_config($a_instance);

		// Sync to configured CARP slaves if any are enabled
		snort_sync_on_changes();

		$savemsg = gettext("The action for rule {$gid}:{$sid} has been modified.  Snort is 'live-reloading' the new rules list.  Please wait at least 15 secs for the process to complete before toggling additional rule actions.");
	}
}

if ($_POST['clear']) {
	snort_post_delete_logs($snort_uuid);
	file_put_contents("{$snortlogdir}/snort_{$if_real}{$snort_uuid}/alert", "");
	/* XXX: This is needed if snort is run as snort user */
	mwexec("/bin/chmod 660 {$snortlogdir}/*", true);
	if (file_exists("{$g['varrun_path']}/snort_{$snort_uuid}.pid"))
		mwexec("/bin/pkill -HUP -F {$g['varrun_path']}/snort_{$snort_uuid}.pid -a");
	unset($a_instance);
	header("Location: /snort/snort_alerts.php?instance={$instanceid}");
	exit;
}

if ($_POST['download']) {
	$save_date = date("Y-m-d-H-i-s");
	$file_name = "snort_logs_{$save_date}_{$if_real}.tar.gz";
	exec("cd {$snortlogdir}/snort_{$if_real}{$snort_uuid} && /usr/bin/tar -czf {$g['tmp_path']}/{$file_name} alert*");

	if (file_exists("{$g['tmp_path']}/{$file_name}")) {
		ob_start(); //important or other posts will fail
		if (isset($_SERVER['HTTPS'])) {
			header('Pragma: ');
			header('Cache-Control: ');
		} else {
			header("Pragma: private");
			header("Cache-Control: private, must-revalidate");
		}
		header("Content-Type: application/octet-stream");
		header("Content-length: " . filesize("{$g['tmp_path']}/{$file_name}"));
		header("Content-disposition: attachment; filename=" . $file_name);
		ob_end_clean(); //important or other post will fail
		readfile("{$g['tmp_path']}/{$file_name}");

		// Clean up the temp file
		unlink_if_exists("{$g['tmp_path']}/{$file_name}");
	}
	else
		$savemsg = gettext("An error occurred while creating archive");
}

// Load up an array with the current Suppression List GID,SID values
$supplist = snort_load_suppress_sigs($a_instance, true);

// Load up an array with the configured Snort interfaces
$interfaces = array();
foreach (config_get_path('installedpackages/snortglobal/rule', []) as $id => $instance) {
	$interfaces[$id] = convert_friendly_interface_to_friendly_descr($instance['interface']) . " (" . get_real_interface($instance['interface']) . ")";
}

$pglinks = array("", "/snort/snort_interfaces.php", "@self");
$pgtitle = array("Services", "Snort", "Alerts");
include("head.inc");

/* refresh every 60 secs */
if ($pconfig['arefresh'] == 'on')
	print '<meta http-equiv="refresh" content="60;url=/snort/snort_alerts.php?instance=' . $instanceid . '" />';

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$form = new Form(false);
$form->setAttribute('name', 'formalert')->setAttribute('id', 'formalert');

$section = new Form_Section('Alert Log View Settings');
$group = new Form_Group('Interface to Inspect');
$group->add(new Form_Select(
	'instance',
	'Instance to Inspect',
	$pconfig['instance'],
	$interfaces
))->setHelp('Choose interface..');
$group->add(new Form_Checkbox(
	'arefresh',
	'Refresh',
	'Auto-refresh view',
	$pconfig['arefresh'] == 'on' ? true:false,
	'on'
));
$group->add(new Form_Input(
	'alertnumber',
	'Lines to Display',
	'text',
	$pconfig['alertnumber']
))->setHelp('Alert lines to display.');
$group->add(new Form_Button(
	'save',
	'Save',
	null,
	'fa-solid fa-save'
))->addClass('btn-primary btn-sm')->setAttribute('title', gettext('Save auto-refresh and view settings'));
$section->add($group);

$btn_dnload = new Form_Button(
	'download',
	'Download',
	null,
	'fa-solid fa-download'
);
$btn_dnload->removeClass('btn-primary')->addClass('btn-success')->addClass('btn-sm')->setAttribute('title', gettext('Download interface log files as a gzip archive'));
$btn_clear = new Form_Button(
	'clear',
	'Clear',
	null,
	'fa-solid fa-trash-can'
);
$btn_clear->removeClass('btn-primary')->addClass('btn-danger')->addClass('btn-sm')->setAttribute('title', gettext('Clear all interface log files')); 

$group = new Form_Group('Alert Log Actions');
$group->add(new Form_StaticText(
	null,
	$btn_dnload . $btn_clear
));
$section->add($group);

$form->add($section);

// ========== BEGIN Log filter Panel =============================================================
if ($filterlogentries) {
	$section = new Form_Section('Alert Log View Filter', 'alertlogfilter', COLLAPSIBLE|SEC_OPEN);
}
else {
	$section = new Form_Section('Alert Log View Filter', 'alertlogfilter', COLLAPSIBLE|SEC_CLOSED);
}

$group = new Form_Group('');
$group->add(new Form_Input(
	'filterlogentries_sourceipaddress',
	null,
	'text',
	$filterfieldsarray[6]
))->setHelp('Source IP Address');
$group->add(new Form_Input(
	'filterlogentries_sourceport',
	null,
	'text',
	$filterfieldsarray[7]
))->setHelp('Source Port');
$group->add(new Form_Input(
	'filterlogentries_destinationipaddress',
	null,
	'text',
	$filterfieldsarray[8]
))->setHelp('Destination IP Address');
$group->add(new Form_Input(
	'filterlogentries_destinationport',
	null,
	'text',
	$filterfieldsarray[9]
))->setHelp('Destination Port');
$group->add(new Form_Input(
	'filterlogentries_protocol',
	null,
	'text',
	$filterfieldsarray[5]
))->setHelp('Protocol');
$section->add($group);

$group = new Form_Group('');
$group->add(new Form_Input(
	'filterlogentries_time',
	null,
	'text',
	$filterfieldsarray[0]
))->setHelp('Date');
$group->add(new Form_Input(
	'filterlogentries_priority',
	null,
	'text',
	$filterfieldsarray[12]
))->setHelp('Priority');
$group->add(new Form_Input(
	'filterlogentries_gid',
	null,
	'text',
	$filterfieldsarray[1]
))->setHelp('GID');
$group->add(new Form_Input(
	'filterlogentries_sid',
	null,
	'text',
	$filterfieldsarray[2]
))->setHelp('SID');
$section->add($group);

$group = new Form_Group('');
$group->add(new Form_Input(
	'filterlogentries_description',
	null,
	'text',
	$filterfieldsarray[4]
))->setHelp('Description');
$group->add(new Form_Input(
	'filterlogentries_classification',
	null,
	'text',
	$filterfieldsarray[11]
))->setHelp('Classification');
$group->add(new Form_Select(
	'filterlogentries_action',
	null,
	$filterfieldsarray[13],
	array( 0 => "", "alert" => "Alert", "drop" => "Drop", "log" => "Log", "pass" => "Pass", "reject" => "Reject", "sdrop" => "SDrop" )
))->setHelp('Action');
$group->add(new Form_Checkbox(
	'filterlogentries_exact_match',
	'Exact Match Only',
	null,
	$filterlogentries_exact_match == "on" ? true:false,
	'on'
))->setHelp('Exact Match');
$group->add(new Form_Button(
	'filterlogentries_submit',
	' ' . 'Filter',
	null,
	'fa-solid fa-filter'
))->removeClass('btn-primary')->addClass('btn-success')->addClass('btn-sm');
$group->add(new Form_Button(
	'filterlogentries_clear',
	' ' . 'Clear',
	null,
	'fa-regular fa-trash-can'
))->removeClass('btn-primary')->addClass('btn-warning')->addClass('btn-sm');

$section->add($group);

$form->add($section);
// ========== END Log filter Panel =============================================================

// ========== Hidden form controls ==============
if (isset($instanceid)) {
	$form->addGlobal(new Form_Input(
		'id',
		'id',
		'hidden',
		$instanceid
	));
}
$form->addGlobal(new Form_Input(
	'mode',
	'mode',
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'sidid',
	'sidid',
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'gen_id',
	'gen_id',
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'ip',
	'ip',
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'descr',
	'descr',
	'hidden',
	''
));
if ($persist_filter_log_entries == "yes") {
	$form->addGlobal(new Form_Input(
		'persist_filter',
		'persist_filter',
		'hidden',
		$persist_filter_log_entries
	));

	$form->addGlobal(new Form_Input(
		'persist_filter_exact_match',
		'persist_filter_exact_match',
		'hidden',
		$filterlogentries_exact_match
	));

	// Pass the $filterfieldsarray variable as serialized data
	$form->addGlobal(new Form_Input(
		'persist_filter_content',
		'persist_filter_content',
		'hidden',
		json_encode($filterfieldsarray)
	));
}

$tab_array = array();
	$tab_array[0] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
	$tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
	$tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
	$tab_array[3] = array(gettext("Alerts"), true, "/snort/snort_alerts.php?instance={$instanceid}");
	$tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[5] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
	$tab_array[6] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
	$tab_array[7] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
	$tab_array[8] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[9] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
	$tab_array[10] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);

print $form;

?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title" id="alert_panel_title">
			<?php
				if (!$filterfieldsarray)
					printf(gettext("Last %s Alert Log Entries"), $pconfig['alertnumber']);
				else
					print $anentries. ' ' . gettext('Matched Log Entries') . ' ';
			?>
		</h2>
	</div>
	<div class="panel-body">
	   <div class="table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
			   <tr class="sortableHeaderRowIdentifier text-nowrap">
				<th data-sortable-type="date"><?=gettext("Date"); ?></th>
				<th><?=gettext("Action"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("Pri"); ?></th>
				<th><?=gettext("Proto"); ?></th>
				<th><?=gettext("Class"); ?></th>
				<th><?=gettext("Source IP"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("SPort"); ?></th>
				<th><?=gettext("Destination IP"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("DPort"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("GID:SID"); ?></th>
				<th data-sortable-type="alpha"><?=gettext("Description"); ?></th>
			   </tr>
			</thead>
		<tbody>
	<?php

/* make sure alert file exists */
if (file_exists("{$snortlogdir}/snort_{$if_real}{$snort_uuid}/alert")) {
	exec("tail -{$anentries} -r {$snortlogdir}/snort_{$if_real}{$snort_uuid}/alert > {$g['tmp_path']}/alert_{$snort_uuid}");
	if (file_exists("{$g['tmp_path']}/alert_{$snort_uuid}")) {
		$tmpblocked = array_flip(snort_get_blocked_ips());
		$counter = 0;
		/*                 0         1           2      3      4    5    6    7      8     9    10        11         12      13       14      */
		/* File format timestamp,sig_generator,sig_id,sig_rev,msg,proto,src,srcport,dst,dstport,id,classification,priority,action,disposition */
		$fd = fopen("{$g['tmp_path']}/alert_{$snort_uuid}", "r");
		while (($fields = fgetcsv($fd, 1000, ',', '"')) !== FALSE) {
			if(count($fields) < 14 || count($fields) > 15)
				continue;

			if ($filterlogentries && !snort_match_filter_field($fields, $filterfieldsarray, $filterlogentries_exact_match)) {
				continue;
			}

			/* Time */
			$alert_time = substr($fields[0], strpos($fields[0], '-')+1, -8);
			/* Date */
			$alert_date = substr($fields[0], 0, strpos($fields[0], '-'));
			if (($event_date = strtotime($alert_date)) !== false) {
				$alert_date = date('Y-m-d', $event_date);
			}
			/* Description */
			$alert_descr = $fields[4];
			$alert_descr_url = urlencode($fields[4]);
			/* Priority */
			$alert_priority = $fields[12];
			/* Protocol */
			$alert_proto = $fields[5];
			/* Action */
			if (isset($fields[13]) && $a_instance['ips_mode'] == 'ips_mode_inline' && $a_instance['blockoffenders7'] == 'on') {

				switch ($fields[13]) {

					case "alert":
						$alert_action = '<i class="fa-solid fa-exclamation-triangle icon-pointer text-warning text-center" title="';
						if (isset($alertsid[$fields[1]][$fields[2]])) {
							$alert_action .= gettext("Rule action is User-Forced to ALERT. Click to force a different action for this rule.");
						}
						else {
							$alert_action .= gettext("Rule action is ALERT. Click to force a different action for this rule.");
						}
						break;

					case "drop":
						$alert_action = '<i class="fa-solid fa-thumbs-down icon-pointer text-danger text-center" title="';
						if (isset($dropsid[$fields[1]][$fields[2]])) {
							$alert_action .= gettext("Rule action is User-Forced to DROP. Click to force a different action for this rule.");
						}
						else {
							$alert_action .=  gettext("Rule action is DROP. Click to force a different action for this rule.");
						}
						break;

					case "reject":
						$alert_action = '<i class="fa-regular fa-hand icon-pointer text-warning text-center" title="';
						if (isset($rejectsid[$fields[1]][$fields[2]])) {
							$alert_action .= gettext("Rule action is User-Forced to REJECT. Click to force a different action for this rule.");
						}
						else {
							$alert_action .= gettext("Rule action is REJECT. Click to force a different action for this rule.");
						}
						break;

					case "sdrop":
						$alert_action = '<i class="fa-regular fa-thumbs-down icon-pointer text-danger text-center" title="' . gettext("Rule action is SDROP. Click to force a different action for this rule.");
						break;

					case "log":
						$alert_action = '<i class="fa-solid fa-tasks icon-pointer text-center" title="' . gettext("Rule action is LOG. Click to force a different action for this rule.") . '"</i>';
						break;

					case "pass":
						$alert_action = '<i class="fa-solid fa-thumbs-up icon-pointer text-success text-center" title="' . gettext("Rule action is PASS. Click to force a different action for this rule.");
						break;

					default:
						$alert_action = '<i class="fa-solid fa-question-circle icon-pointer text-danger text-center" title="' . gettext("Rule action is unrecognized!. Click to force a different action for this rule.");
				}
				$alert_action .= '" onClick="toggleAction(\'' . $fields[1] . '\', \'' . $fields[2] . '\');"</i>';
			}
			else {
				$alert_action = '<i class="fa-solid fa-exclamation-triangle text-warning text-center" title="' . gettext("Rule action is ALERT.") . '"</i>';
			}
			/* Disposition (not currently used, so just set to "Allow") */
			$alert_disposition = isset($fields[14])?$fields[14]:gettext("Allow");

			/* IP SRC */
			$alert_ip_src = $fields[6];
			if (!empty($alert_ip_src)) {
				/* Add zero-width space as soft-break opportunity after each colon if we have an IPv6 address */
				$alert_ip_src = str_replace(":", ":&#8203;", $alert_ip_src);

				/* Add Reverse DNS lookup icons */
				$alert_ip_src .= '<br/>';
				$alert_ip_src .= '<i class="fa-solid fa-search icon-pointer" onclick="javascript:resolve_with_ajax(\'' . $fields[6] . '\');" title="' . gettext("Click to resolve") . '" alt="Reverse Resolve with DNS"></i>';

				/* Add icons for auto-adding to Suppress List if appropriate */
				if (!snort_is_alert_globally_suppressed($supplist, $fields[1], $fields[2]) && 
				    !isset($supplist[$fields[1]][$fields[2]]['by_src'][$fields[6]])) {

					$alert_ip_src .= "&nbsp;&nbsp;<i class=\"fa-regular fa-square-plus icon-pointer\" title=\"" . gettext('Add this alert to the Suppress List and track by_src IP') . '"';
					$alert_ip_src .= " onClick=\"encRuleSig('{$fields[1]}','{$fields[2]}','{$fields[6]}','{$alert_descr}');$('#mode').val('addsuppress_srcip');$('#formalert').submit();\"></i>";
				}
				elseif (isset($supplist[$fields[1]][$fields[2]]['by_src'][$fields[6]])) {
					$alert_ip_src .= '&nbsp;&nbsp;<i class="fa-solid fa-info-circle"';
					$alert_ip_src .= ' title="' . gettext("This alert track by_src IP is already in the Suppress List") . '"></i>';	
				}
				/* Add icon for auto-removing from Blocked Table if required */
				if (isset($tmpblocked[$fields[6]])) {
					$alert_ip_src .= "&nbsp;&nbsp;<i class=\"fa-solid fa-times icon-pointer text-danger\" onClick=\"$('#ip').val('{$fields[6]}');$('#mode').val('todelete');$('#formalert').submit();\"";
					$alert_ip_src .= ' title="' . gettext("Remove host from Blocked Table") . '"></i>';
				}
			}

			/* IP SRC Port */
			$alert_src_p = $fields[7];

			/* IP Destination */
			$alert_ip_dst = $fields[8];

			if (!empty($alert_ip_dst)) {
				/* Add zero-width space as soft-break opportunity after each colon if we have an IPv6 address */
				$alert_ip_dst = str_replace(":", ":&#8203;", $alert_ip_dst);

				/* Add Reverse DNS lookup icons */
				$alert_ip_dst .= "<br/>";
				$alert_ip_dst .= '<i class="fa-solid fa-search icon-pointer" onclick="javascript:resolve_with_ajax(\'' . $fields[8] . '\');" title="' . gettext("Click to resolve") . '" alt="Reverse Resolve with DNS"></i>';

				/* Add icons for auto-adding to Suppress List if appropriate */
				if (!snort_is_alert_globally_suppressed($supplist, $fields[1], $fields[2]) && 
				    !isset($supplist[$fields[1]][$fields[2]]['by_dst'][$fields[8]])) {
					$alert_ip_dst .= "&nbsp;&nbsp;<i class=\"fa-regular fa-square-plus icon-pointer\" onClick=\"encRuleSig('{$fields[1]}','{$fields[2]}','{$fields[8]}','{$alert_descr}');$('#mode').val('addsuppress_dstip');$('#formalert').submit();\"";
					$alert_ip_dst .= ' title="' . gettext("Add this alert to the Suppress List and track by_dst IP") . '"></i>';	
				}
				elseif (isset($supplist[$fields[1]][$fields[2]]['by_dst'][$fields[8]])) {
					$alert_ip_dst .= '&nbsp;&nbsp;<i class="fa-solid fa-info-circle"';
					$alert_ip_dst .= ' title="' . gettext("This alert track by_dst IP is already in the Suppress List") . '"></i>';	
				}
				/* Add icon for auto-removing from Blocked Table if required */
				if (isset($tmpblocked[$fields[8]])) {
					$alert_ip_dst .= "&nbsp;&nbsp;<i name=\"todelete[]\" class=\"fa-solid fa-times icon-pointer text-danger\" onClick=\"$('#ip').val('{$fields[8]}');$('#mode').val('todelete');$('#formalert').submit();\" ";
					$alert_ip_dst .= ' title="' . gettext("Remove host from Blocked Table") . '"></i>';
				}
			}

			/* IP DST Port */
			$alert_dst_p = $fields[9];

			/* GID:SID */
			$alert_sid_str = "{$fields[1]}:{$fields[2]}";
			if (!snort_is_alert_globally_suppressed($supplist, $fields[1], $fields[2])) {
				$sidsupplink = "<i class=\"fa-regular fa-square-plus icon-pointer\" onClick=\"encRuleSig('{$fields[1]}','{$fields[2]}','','{$alert_descr}');$('#mode').val('addsuppress');$('#formalert').submit();\"";
				$sidsupplink .= ' title="' . gettext("Add this alert to the Suppress List") . '"></i>';	
			}
			else {
				$sidsupplink = '<i class="fa-solid fa-info-circle"';
				$sidsupplink .= ' title="' . gettext("This alert is already in the Suppress List") . '"></i>';	
			}
			/* Add icon for toggling rule state */
			if (isset($disablesid[$fields[1]][$fields[2]])) {
				$sid_dsbl_link = "<i class=\"fa-solid fa-times-circle icon-pointer text-warning\" onClick=\"encRuleSig('{$fields[1]}','{$fields[2]}','','');$('#mode').val('togglesid');$('#formalert').submit();\"";
				$sid_dsbl_link .= ' title="' . gettext("Rule is forced to a disabled state. Click to remove the force-disable action from this rule.") . '"></i>';
			}
			else {
				$sid_dsbl_link = "<i class=\"fa-solid fa-times icon-pointer text-danger\" onClick=\"encRuleSig('{$fields[1]}','{$fields[2]}','','');$('#mode').val('togglesid');$('#formalert').submit();\"";
				$sid_dsbl_link .= ' title="' . gettext("Force-disable this rule and remove it from current rules set.") . '"></i>';
			}

			/* Add icon for toggling rule action if applicable to current mode */
			if ($a_instance['blockoffenders7'] == 'on' && $a_instance['ips_mode'] == 'ips_mode_inline') {
				$sid_action_link = "<i class=\"fa-regular fa-pen-to-square icon-pointer text-info\" onClick=\"toggleAction('{$fields[1]}', '{$fields[2]}');\"";
				$sid_action_link .= ' title="' . gettext("Click to force a different action for this rule.") . '"></i>';
			}
			else {
				$sid_action_link = '';
			}

			/* DESCRIPTION */
			$alert_class = $fields[11];

			/* Snort database GID:SID link, see https://redmine.pfsense.org/issues/12221 */
			$link_sid_str = "https://www.snort.org/rule_docs/{$fields[1]}-{$fields[2]}";

			/* Write out a table row */
?>
			<tr class="text-nowrap">
				<td><?=$alert_date; ?><br/><?=$alert_time; ?></td>
				<td><?=$alert_action; ?></td>
				<td><?=$alert_priority; ?></td>
				<td><?=$alert_proto; ?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_class; ?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_ip_src; ?></td>
				<td><?=$alert_src_p; ?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_ip_dst;?></td>
				<td><?=$alert_dst_p; ?></td>
				<td><a target="_blank" href="<?=$link_sid_str; ?>"><?=$alert_sid_str; ?></a><br/><?=$sidsupplink; ?>&nbsp;&nbsp;<?=$sid_dsbl_link; ?>&nbsp;&nbsp;<?=$sid_action_link; ?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_descr; ?></td>
			</tr>
<?php
			$counter++;
		}
		fclose($fd);
		unlink_if_exists("{$g['tmp_path']}/alert_{$snort_uuid}");
	}
}
?>
		</tbody>
	</table>
</div>
</div>
</div>

<?php if ($a_instance['blockoffenders7'] == 'on') : ?>
	<!-- Modal Rule SID action selector window -->
	<div class="modal fade" role="dialog" id="sid_action_selector">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
					<h3 class="modal-title"><?=gettext("Rule Action Selection")?></h3>
				</div>
				<div class="modal-body">
					<h4><?=gettext("Choose desired rule action from selections below: ");?></h4>
					<label class="radio-inline">
						<input type="radio" form="formalert" name="ruleActionOptions" id="action_default" value="action_default"> <span class = "label label-default">Default</span>
					</label>
					<label class="radio-inline">
						<input type="radio" form="formalert" name="ruleActionOptions" id="action_alert" value="action_alert"> <span class = "label label-warning">ALERT</span>
					</label>
					<label class="radio-inline">
						<input type="radio" form="formalert" name="ruleActionOptions" id="action_drop" value="action_drop"> <span class = "label label-danger">DROP</span>
					</label>

			<?php if ($a_instance['ips_mode'] == 'ips_mode_inline' && $a_instance['blockoffenders7'] == 'on') : ?>
					<label class="radio-inline">
						<input type="radio" form="formalert" name="ruleActionOptions" id="action_reject" value="action_reject"> <span class = "label label-warning">REJECT</span>
					</label>
			<?php endif; ?>
					<br /><br />
						<p><?=gettext("Choosing 'Default' will return the rule action to the original value specified by the rule author.  Note this is usually ALERT.");?></p>
				</div>
				<div class="modal-footer">
					<button type="submit" form="formalert" class="btn btn-sm btn-primary" id="rule_action_save" name="rule_action_save" value="<?=gettext("Save");?>" title="<?=gettext("Save changes and close selector");?>" onClick="$('#sid_action_selector').modal('hide');">
						<i class="fa-solid fa-save icon-embed-btn"></i>
						<?=gettext("Save");?>
					</button>
					<button type="button" class="btn btn-sm btn-warning" id="cancel" name="cancel" value="<?=gettext("Cancel");?>" data-dismiss="modal" title="<?=gettext("Abandon changes and quit selector");?>">
						<?=gettext("Cancel");?>
					</button>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>
<?php unset($a_instance); ?>

<script type="text/javascript">
//<![CDATA[

	//-- This function stuffs the passed GID, SID and other values into
	//-- hidden Form Fields for postback.
	function encRuleSig(rulegid,rulesid,srcip,ruledescr) {
		if (typeof srcipip === 'undefined')
			var srcipip = '';
		if (typeof ruledescr === 'undefined')
			var ruledescr = '';
		$('#sidid').val(rulesid);
		$('#gen_id').val(rulegid);
		$('#ip').val(srcip);
		$('#descr').val(ruledescr);
	}

	function toggleAction(gid, sid) {
		$('#sidid').val(sid);
		$('#gen_id').val(gid);
		$('#mode').val('toggle_action');
		$('#sid_action_selector').modal('show');
	}

	//-- The following AJAX code was borrowed from the diag_logs_filter.php --
	//-- file in pfSense.  See copyright info at top of this page.          --
	function resolve_with_ajax(ip_to_resolve) {
		var url = "/snort/snort_alerts.php";

	jQuery.ajax(
		url,
		{
			method: 'post',
			dataType: 'json',
			data: {
				resolve: ip_to_resolve,
				},
			complete: resolve_ip_callback
		});

	}

	function resolve_ip_callback(transport) {
		var response = jQuery.parseJSON(transport.responseText);
		var msg = 'IP address "' + response.resolve_ip + '" resolves to\n';
		alert(msg + 'host "' + htmlspecialchars(response.resolve_text) + '"');
	}

	// From http://stackoverflow.com/questions/5499078/fastest-method-to-escape-html-tags-as-html-entities
	function htmlspecialchars(str) {
		return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
	}

events.push(function() {

	//-- Click handlers ------------------------------------------------------
	$('#instance').on('change', function() {
		$('#formalert').submit();
	});

	<?php if ($filterlogentries && count($filterfieldsarray)): ?>
	// Set the number of filter matches if filtering alerts view
	$('#alert_panel_title').text("<?=$counter . ' ' . gettext('Matched Entries from Active Log (filtered view)') . ' '; ?>");
	<?php elseif ($counter == $anentries): ?>
	$('#alert_panel_title').text("<?=gettext('Most Recent') . ' ' . $counter . ' ' . gettext('Entries from Active Log') . ' '; ?>");
	<?php else: ?>
	$('#alert_panel_title').text("<?=$counter . ' ' . gettext('Entries in Active Log') . ' '; ?>");
	<?php endif;?>
});
//]]>
</script>

<?php include("foot.inc"); ?>

