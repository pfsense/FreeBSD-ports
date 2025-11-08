<?php
/*
 * suricata_alerts.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2025 Bill Meeks
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
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g;
$supplist = array();
$suri_pf_table = SURICATA_PF_TABLE;

function suricata_is_alert_globally_suppressed($list, $gid, $sid) {

	/************************************************/
	/* Checks the passed $gid:$sid to see if it has */
	/* been globally suppressed.  If true, then any */
	/* "track by_src" or "track by_dst" options are */
	/* disabled since they are overridden by the    */
	/* global suppression of the $gid:$sid.         */
	/************************************************/

	/* If entry at array key [GID][SID] is set to the
	 * string "suppress", then the rule is globally
	 * suppressed, otherwise it will  have a child
	 * array for track "by_src" or "by_dst".
     */
	if (array_get_path($list, "{$gid}/{$sid}", "") == "suppress")
		return true;
	else
		return false;
}

function suricata_add_supplist_entry($suppress) {

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

	$a_suppress = config_get_path('installedpackages/suricata/suppress/item', []);
	$found_list = false;

	/* If no Suppress List is set for the interface, then create one with the interface name */
	if (empty(config_get_path("installedpackages/suricata/rule/{$instanceid}/suppresslistname", '')) || config_get_path("installedpackages/suricata/rule/{$instanceid}/suppresslistname", '') == 'default') {
		$s_list = array();
		$s_list['uuid'] = uniqid();
		$s_list['name'] = config_get_path("installedpackages/suricata/rule/{$instanceid}/interface") . "suppress" . "_" . $s_list['uuid'];
		$s_list['descr']  =  "Auto-generated list for Alert suppression";
		$s_list['suppresspassthru'] = base64_encode($suppress);
		$a_suppress[] = $s_list;
		config_set_path("installedpackages/suricata/rule/{$instanceid}/suppresslistname", $s_list['name']);
		$found_list = true;
	} else {
		/* If we get here, a Suppress List is defined for the interface so see if we can find it */
		foreach ($a_suppress as $a_id => $alist) {
			if ($alist['name'] == config_get_path("installedpackages/suricata/rule/{$instanceid}/suppresslistname", '')) {
				$found_list = true;
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

	/* If we created a new list or updated an existing one, save the change */
	/* and return true; otherwise return false.                             */
	if ($found_list) {
		config_set_path('installedpackages/suricata/suppress/item', $a_suppress);
		write_config("Suricata pkg: saved change to Suppress List " . $s_list['name'] . " from ALERTS tab.");
		sync_suricata_package_config();
		return true;
	}
	else
		return false;
}

function suricata_escape_filter_regex($filtertext) {
	/* If the caller (user) has not already put a backslash before a slash, to escape it in the regex, */
	/* then this will do it. Take out any "\/" already there, then turn all ordinary "/" into "\/".  */
	return str_replace('/', '\/', str_replace('\/', '/', $filtertext));
}

function suricata_match_filter_field($flent, $fields, $exact_match = FALSE) {
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
			$field_regex = suricata_escape_filter_regex($field);
			if (@preg_match("/{$field_regex}/i", $flent[$key]))
				return false;
		}
		else {
			$field_regex = suricata_escape_filter_regex($field);
			if (!@preg_match("/{$field_regex}/i", $flent[$key]))
				return false;
		}
	}
	return true;
}

if (isset($_POST['instance']) && is_numericint($_POST['instance']))
	$instanceid = $_POST['instance'];
// This is for the auto-refresh so we can  stay on the same interface
elseif (isset($_GET['instance']) && is_numericint($_GET['instance']))
	$instanceid = $_GET['instance'];

if (!is_numericint($instanceid))
	$instanceid = 0;

$a_instance = config_get_path("installedpackages/suricata/rule/{$instanceid}", []);
$suricata_uuid = $a_instance['uuid'];
$if_real = get_real_interface($a_instance['interface']);
$suricatalogdir = SURICATALOGDIR;
$suricatadir = SURICATADIR;

// Load up the arrays of force-enabled and force-disabled SIDs
$enablesid = suricata_load_sid_mods($a_instance['rule_sid_on']);
$disablesid = suricata_load_sid_mods($a_instance['rule_sid_off']);

// Load up the arrays of forced-alert, forced-drop or forced-reject
// rules as applicable to the current IPS mode.
if ($a_instance['blockoffenders'] == 'on' && ($a_instance['ips_mode'] == 'ips_mode_inline' || $a_instance['block_drops_only'] == 'on')) {
	$alertsid = suricata_load_sid_mods($a_instance['rule_sid_force_alert']);
	$dropsid = suricata_load_sid_mods($a_instance['rule_sid_force_drop']);

	// REJECT forcing is only applicable to Inline IPS Mode
	if ($a_instance['ips_mode'] == 'ips_mode_inline' ) {
		$rejectsid = suricata_load_sid_mods($a_instance['rule_sid_force_reject']);
	}
	else {
		$rejectsid = array();
	}
}

$pconfig = array();
$pconfig['arefresh'] = config_get_path('installedpackages/suricata/alertsblocks/arefresh', 'on');
$pconfig['alertnumber'] = config_get_path('installedpackages/suricata/alertsblocks/alertnumber', '250');
$anentries = (int)$pconfig['alertnumber'];

# --- AJAX REVERSE DNS RESOLVE Start ---
if (isset($_POST['resolve'])) {
	$ip = strtolower($_POST['resolve']);
	$res = (is_ipaddr($ip) ? gethostbyaddr($ip) : '');
	if (strpos($res, 'xn--') !== false) {
		$res = idn_to_utf8($res);
	}

	if ($res && $res != $ip)
		$response = array('resolve_ip' => $ip, 'resolve_text' => $res);
	else
		$response = array('resolve_ip' => $ip, 'resolve_text' => gettext("Cannot resolve"));

	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}
# --- AJAX REVERSE DNS RESOLVE End ---

# --- AJAX GEOIP CHECK Start ---
if (isset($_POST['geoip'])) {
	$ip = strtolower($_POST['geoip']);
	if (is_ipaddr($ip)) {
		$url = "https://api.hackertarget.com/geoip/?q={$ip}";
		$conn = curl_init("https://api.hackertarget.com/geoip/?q={$ip}");
		curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($conn, CURLOPT_FRESH_CONNECT,  true);
		curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
		set_curlproxy($conn);
		$res = curl_exec($conn);
		curl_close($conn);
	} else {
		$res = '';
	}

	if ($res && $res != $ip && !preg_match('/error/', $res))
		$response = array('geoip_text' => $res);
	else
		$response = array('geoip_text' => gettext("Cannot check {$ip}"));

	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}
# --- AJAX GEOIP CHECK End ---

# --- AJAX RULE LOOKUP Start ---
if (isset($_POST['rulelookup2'])) {
	list($gid, $sid) = explode(':', $_POST['rulelookup']);
	foreach (glob("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}/rules/*") as $rule) {
		$fd = fopen($rule, "r");
		$buf = "";
		while (($buf = fgets($fd)) !== FALSE) {
			$matches = array();
			preg_match('/sid\:([0-9]+);/i', $buf, $matches);
			if ($sid == $matches[1]) {
				preg_match('/gid\:([0-9]+);/i', $buf, $matches);
				if (($gid == $matches[1]) ||
				    (empty($matches[1]) && ($gid == 1))) {
					$res = $buf;
					break 2;
				}
			}
		}
	}

	if ($res)
		$response = array('gidsid' => $_POST['rulelookup'], 'rule_text' => $res);
	else
		$response = array('gidsid' => $_POST['rulelookup'], 'rule_text' => gettext("Unable to find the rule"));

	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}
# --- AJAX RULE LOOKUP End ---

# --- AJAX RULE LOOKUP Start ---
if ($_POST['action'] == 'loadRule') {
	$currentruleset = '';
	if (isset($_POST['gid']) && isset($_POST['sid'])) {
		$gid = $_POST['gid'];
		$sid = $_POST['sid'];
		$rules = array_merge(glob(SURICATA_RULES_DIR . "/*.rules"), array("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}/rules/custom.rules", "{$suricatadir}suricata_{$suricata_uuid}_{$if_real}/rules/flowbit-required.rules"));
		foreach ($rules as $rule) {
			$rules_map = suricata_load_rules_map($rule);
			if ($rules_map[$gid][$sid]['rule']) {
				$rule_text = base64_encode($rules_map[$gid][$sid]['rule']);
				$currentruleset = basename($rule);
				break;
			}
		}
	} else {
		$rule_text = base64_encode(gettext('Invalid rule signature - no matching rule was found!'));
	}
	if (strpos($currentruleset, 'snort_') !== false) {
		$rule_link = "https://www.snort.org/rule_docs/{$gid}-{$sid}";
	} else {
		$rule_link = '';
	}	
	$response = array('rule_text' => $rule_text, 'rule_link' => $rule_link, 'category' => $currentruleset);
	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}
# --- AJAX RULE LOOKUP End ---

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
	// Set flags for filtering alert log entries
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
	$filterfieldsarray['time'] = $_POST['filterlogentries_time'] ? $_POST['filterlogentries_time'] : null;
	if ($a_instance['ips_mode'] == 'ips_mode_inline') {
		if ($_POST['filterlogentries_action_drop']) {
			$filterfieldsarray['action'] = $_POST['filterlogentries_action_drop'] ? $_POST['filterlogentries_action_drop'] : null;
		}
		elseif ($_POST['filterlogentries_action_ndrop']) {
			$filterfieldsarray['action'] = $_POST['filterlogentries_action_ndrop'] ? $_POST['filterlogentries_action_ndrop'] : null;
		}
	}
	else {
		$filterfieldsarray['action'] = null;
	}
	$filterfieldsarray['gid'] = $_POST['filterlogentries_gid'] ? $_POST['filterlogentries_gid'] : null;
	$filterfieldsarray['sid'] = $_POST['filterlogentries_sid'] ? $_POST['filterlogentries_sid'] : null;
	$filterfieldsarray['rev'] = null;
	$filterfieldsarray['msg'] = $_POST['filterlogentries_description'] ? $_POST['filterlogentries_description'] : null;
	$filterfieldsarray['class'] = $_POST['filterlogentries_classification'] ? $_POST['filterlogentries_classification'] : null;
	$filterfieldsarray['priority'] = $_POST['filterlogentries_priority'] ? $_POST['filterlogentries_priority'] : null;
	$filterfieldsarray['proto'] = $_POST['filterlogentries_protocol'] ? $_POST['filterlogentries_protocol'] : null;
	// Remove any zero-length spaces added to the IP address that could creep in from a copy-paste operation
	$filterfieldsarray['src'] = $_POST['filterlogentries_sourceipaddress'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_sourceipaddress']) : null;
	$filterfieldsarray['sport'] = $_POST['filterlogentries_sourceport'] ? $_POST['filterlogentries_sourceport'] : null;
	// Remove any zero-length spaces added to the IP address that could creep in from a copy-paste operation
	$filterfieldsarray['dst'] = $_POST['filterlogentries_destinationipaddress'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_destinationipaddress']) : null;
	$filterfieldsarray['dport'] = $_POST['filterlogentries_destinationport'] ? $_POST['filterlogentries_destinationport'] : null;
}

if ($_POST['filterlogentries_clear']) {
	$filterfieldsarray = array();
	$filterlogentries = TRUE;
	$persist_filter_log_entries = "";
}

if ($_POST['save']) {
	config_set_path('installedpackages/suricata/alertsblocks/arefresh', $_POST['arefresh'] ? 'on' : 'off');
	config_set_path('installedpackages/suricata/alertsblocks/alertnumber', $_POST['alertnumber']);

	write_config("Suricata pkg: saved change to ALERTS tab configuration.");

	header("Location: /suricata/suricata_alerts.php?instance={$instanceid}");
	exit;
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
		config_set_path("installedpackages/suricata/rule/{$instanceid}", $a_instance);
		write_config("Suricata pkg: User-forced rule action override applied for rule {$gid}:{$sid} on ALERTS tab for interface {$a_instance['interface']}.");

		/*************************************************/
		/* Update the suricata.yaml file and rebuild the */
		/* rules for this interface.                     */
		/*************************************************/
		$rebuild_rules = true;
		suricata_generate_yaml($a_instance);
		$rebuild_rules = false;

		/* Signal Suricata to live-load the new rules */
		suricata_reload_config($a_instance);

		// Sync to configured CARP slaves if any are enabled
		suricata_sync_on_changes();

		$savemsg = gettext("The action for rule {$gid}:{$sid} has been modified.  Suricata is 'live-reloading' the new rules list.  Please wait at least 15 secs for the process to complete before toggling additional rule actions.");
	}
}

if ($_POST['mode']=='unblock' && $_POST['ip']) {
	if (is_ipaddr($_POST['ip'])) {
		exec("/sbin/pfctl -t {$suri_pf_table} -T delete {$_POST['ip']}");
		$savemsg = gettext("Host IP address {$_POST['ip']} has been removed from the Blocked Table.");
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
			$success = gettext("An entry for 'suppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}' has been added to the Suppress List.  Suricata is 'live-reloading' the new rules list.  Please wait at least 15 secs for the process to complete before toggling additional rule actions.");
			break;
		case "by_src":
		case "by_dst":
			// Check for valid IP addresses, exit if not valid
			if (is_ipaddr($_POST['ip'])) {
				if (empty($_POST['descr']))
					$suppress = "suppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}, track {$method}, ip {$_POST['ip']}\n";
				else
					$suppress = "#{$_POST['descr']}\nsuppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}, track {$method}, ip {$_POST['ip']}\n";
				$success = gettext("An entry for 'suppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}, track {$method}, ip {$_POST['ip']}' has been added to the Suppress List.  Suricata is 'live-reloading' the new rules list.  Please wait at least 15 secs for the process to complete before toggling additional rule actions.");
			}
			else {
				header("Location: /suricata/suricata_alerts.php");
				exit;
			}
			break;
		default:
			header("Location: /suricata/suricata_alerts.php");
			exit;
	}

	/* Add the new entry to the Suppress List and signal Suricata to reload config */
	if (suricata_add_supplist_entry($suppress)) {
		suricata_reload_config($a_instance);

		// See if this Suppress List is assigned to any other interface
		// and signal that interface to reload its configuration if true.
		foreach (config_get_path('installedpackages/suricata/rule', []) as $insid => $insconf) {
			if (($insid != $instanceid) &&
			    ($a_instance['suppresslistname'] == $insconf['suppresslistname'])) {
				suricata_reload_config($insconf);
			}
		}
		$savemsg = $success;

		// Sync to configured CARP slaves if any are enabled
		suricata_sync_on_changes();
		sleep(2);
	}
	else
		$input_errors[] = gettext("Suppress List '{$a_instance['suppresslistname']}' is defined for this interface, but it could not be found!");
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
	config_set_path("installedpackages/suricata/rule/{$instanceid}", $a_instance);
	write_config("Suricata pkg: User-forced rule state override applied for rule {$gid}:{$sid} on ALERTS tab for interface {$a_instance['interface']}.");

	/*************************************************/
	/* Update the suricata.yaml file and rebuild the */
	/* rules for this interface.                     */
	/*************************************************/
	$rebuild_rules = true;
	suricata_generate_yaml($a_instance);
	$rebuild_rules = false;

	/* Signal Suricata to live-load the new rules */
	suricata_reload_config($a_instance);

	// Sync to configured CARP slaves if any are enabled
	suricata_sync_on_changes();
	sleep(2);

	$savemsg = gettext("The state for rule {$gid}:{$sid} has been modified.  Suricata is 'live-reloading' the new rules list.  Please wait at least 15 secs for the process to complete before toggling additional rules.");
}

if ($_POST['clear']) {

	// Truncate the active alerts.log file
	$fd = @fopen("{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}/alerts.log", "r+");
	if ($fd !== FALSE) {
		ftruncate($fd, 0);
		fclose($fd);
	}

	// Signal the Suricata instance that logs have been rotated
	suricata_reload_config($a_instance, SIGHUP);

	/* XXX: This is needed if suricata is run as suricata user */
	mwexec('/bin/chmod 660 {$suricatalogdir}*', true);
	header("Location: /suricata/suricata_alerts.php?instance={$instanceid}");
	exit;
}

if ($_POST['download']) {
	$save_date = date("Y-m-d-H-i-s");
	$file_name = "suricata_logs_{$save_date}_{$if_real}.tar.gz";
	exec("cd {$suricatalogdir}suricata_{$if_real}{$suricata_uuid} && /usr/bin/tar -czf {$g['tmp_path']}/{$file_name} alert*");

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
		header("Content-disposition: attachment; filename = {$file_name}");
		ob_end_clean(); //important or other post will fail
		readfile("{$g['tmp_path']}/{$file_name}");

		// Clean up the temp file
		unlink_if_exists("{$g['tmp_path']}/{$file_name}");
	}
	else
		$savemsg = gettext("An error occurred while creating archive");
}

/* Load up an array with the current Suppression List GID,SID values */
$supplist = suricata_load_suppress_sigs($a_instance, true);

function build_instance_list() {
	$list = array();

	foreach (config_get_path('installedpackages/suricata/rule', []) as $id => $instance) {
		$list[$id] = '(' . convert_friendly_interface_to_friendly_descr($instance['interface']) . ') ' . $instance['descr'];
	}

	return($list);
}

function build_logfile_list() {
	global $suricatalogdir;

	$list = array();

	$logs = array( "alerts.log", "block.log", "dns.log", "eve.json", "files-json.log", "http.log", "sid_changes.log", "stats.log", "suricata.log", "tls.log" );
	foreach ($logs as $log) {
		$list[$suricatalogdir . $log] = $log;
	}

	return($list);
}

$pglinks = array("", "/suricata/suricata_interfaces.php", "@self");
$pgtitle = array("Services", "Suricata", "Alerts");
include_once("head.inc");


/* refresh every 60 secs */
if ($pconfig['arefresh'] == 'on')
	print '<meta http-equiv="refresh" content="60;url=/suricata/suricata_alerts.php?instance=' . $instanceid . '" />';

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), true, "/suricata/suricata_alerts.php");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$instanceid}");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

$form = new Form(false);
$form->setAttribute('name', 'formalert')->setAttribute('id', 'formalert');

$section = new Form_Section('Alert Log View Settings');

$section->addInput(new Form_Select(
	'instance',
	'Instance to View',
	$instanceid,
	build_instance_list()
))->setHelp('Choose which instance alerts you want to inspect.');

$group = new Form_Group('Save or Remove Logs');

$group->add(new Form_Button(
	'download',
	'Download',
	null,
	'fa-solid fa-download'
))->removeClass('btn-default')->addClass('btn-info btn-sm')
  ->setHelp('All alert log files for selected interface will be downloaded');

$group->add(new Form_Button(
	'clear',
	'Clear',
	null,
	'fa-solid fa-trash-can'
))->removeClass('btn-default')->addClass('btn-danger btn-sm')
  ->setHelp('Clear the currently active Alerts log file');

$section->add($group);

$group = new Form_Group('Save Settings');

$group->add(new Form_Button(
	'save',
	'Save',
	null,
	'fa-solid fa-save'
))->removeClass('btn-default')->addClass('btn-success btn-sm')
  ->setHelp('Save auto-refresh and view settings');

$group->add(new Form_Checkbox(
	'arefresh',
	null,
	'Refresh',
	$pconfig['arefresh'] == 'on' ? true:false,
	'on'
))->setHelp('Default is ON');

$group->add(new Form_Input(
	'alertnumber',
	'Alert Entries',
	'number',
	$anentries
	))->setHelp('Number of alerts to display. Default is 250');

$section->add($group);

$form->add($section);

// ========== Log filter Panel =============================================================
if ($filterlogentries && count($filterfieldsarray)) {
	$section = new Form_Section("Alert Log View Filter", "alertfilter", COLLAPSIBLE|SEC_OPEN);
}
else {
	$section = new Form_Section("Alert Log View Filter", "alertfilter", COLLAPSIBLE|SEC_CLOSED);
}

$group = new Form_Group('');

$group->add(new Form_Input(
	'filterlogentries_time',
	'Date',
	'text',
	$filterfieldsarray['time']
))->setHelp("Date");

$group->add(new Form_Input(
	'filterlogentries_sourceipaddress',
	'Source IP Address',
	'text',
	$filterfieldsarray['src']
))->setHelp("Source IP Address");

$group->add(new Form_Input(
	'filterlogentries_sourceport',
	'Source Port',
	'text',
	$filterfieldsarray['sport']
))->setHelp("Source Port");

$group->add(new Form_Input(
	'filterlogentries_description',
	'Description',
	'text',
	$filterfieldsarray['msg']
))->setHelp("Description");

$group->add(new Form_Input(
	'filterlogentries_gid',
	'GID',
	'text',
	$filterfieldsarray['gid']
))->setHelp("GID");

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Input(
	'filterlogentries_priority',
	'Priority',
	'text',
	$filterfieldsarray['priority']
))->setHelp("Priority");

$group->add(new Form_Input(
	'filterlogentries_destinationipaddress',
	'Destination IP Address',
	'text',
	$filterfieldsarray['dst']
))->setHelp("Destination IP Address");

$group->add(new Form_Input(
	'filterlogentries_destinationport',
	'Port',
	'text',
	$filterfieldsarray['dport']
))->setHelp("Destination Port");

$group->add(new Form_Input(
	'filterlogentries_classification',
	'Classification',
	'text',
	$filterfieldsarray['class']
))->setHelp("Classification");

$group->add(new Form_Input(
	'filterlogentries_sid',
	'SID',
	'text',
	$filterfieldsarray['sid']
))->setHelp("SID");

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Input(
	'filterlogentries_protocol',
	'Protocol',
	'text',
	$filterfieldsarray['proto']
))->setHelp("Protocol");

if ($a_instance['ips_mode'] == 'ips_mode_inline') {
	$group->add(new Form_Checkbox(
		'filterlogentries_action_drop',
		'Dropped',
		null,
		$filterfieldsarray['action'] == "Drop" ? true:false,
		'Drop'
	))->setHelp('Dropped');

	$group->add(new Form_Checkbox(
		'filterlogentries_action_ndrop',
		'Not Dropped',
		null,
		$filterfieldsarray['action'] == "!Drop" ? true:false,
		'!Drop'
	))->setHelp('Not Dropped');
}

$group->add(new Form_Checkbox(
	'filterlogentries_exact_match',
	'Exact Match Only',
	null,
	$filterlogentries_exact_match == "on" ? true:false,
	'on'
))->setHelp('Exact Match');

$section->add($group);

$group = new Form_Group('');
$group->add(new Form_Button(
	'filterlogentries_submit',
	'Apply Filter',
	null,
	'fa-solid fa-filter'
))->removeClass("btn-primary btn-default")
  ->addClass("btn-success btn-sm");

$group->add(new Form_Button(
	'filterlogentries_clear',
	'Clear Filter',
	null,
	'fa-regular fa-trash-can'
))->removeclass("btn-primary btn-default")
  ->addClass("btn-danger no-confirm btn-sm");

$section->add($group);

$form->add($section);

// ========== Hidden controls ==============
$form->addGlobal(new Form_Input(
	'sidid',
	null,
	'hidden',
	''
));

$form->addGlobal(new Form_Input(
	'ip',
	null,
	'hidden',
	''
));

$form->addGlobal(new Form_Input(
	'gen_id',
	null,
	'hidden',
	''
));

$form->addGlobal(new Form_Input(
	'mode',
	'mode',
	'hidden',
	''
));

$form->addGlobal(new Form_Input(
	'descr',
	null,
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

print($form);

if ($filterlogentries && count($filterfieldsarray)) {
	$sectitle = sprintf("Last %s Alert Entries. (Most recent entries are listed first)  ** FILTERED VIEW **  clear filter to see all entries", $anentries);
} else {
	$sectitle = sprintf("Last %s Alert Entries. (Most recent entries are listed first)", $anentries);
}

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=sprintf($sectitle)?></h2></div>
	<div class="panel-body table-responsive">
	<?php if ($a_instance['ips_mode'] == 'ips_mode_inline' || $a_instance['block_drops_only'] == 'on') : ?>
		<div class="content table-responsive">
			<span class="text-info"><b><?=gettext('Note: ');?></b><?=gettext('Alerts triggered by DROP rules that resulted in dropped (blocked) packets are shown with ');?>
			<span class="text-danger"><?=gettext('highlighted ');?></span><?=gettext('rows below.');?><span>
		</div>
	<?php endif; ?>
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
			   <tr class="sortableHeaderRowIdentifier text-nowrap">
				<th data-sortable-type="date"><?=gettext("Date"); ?></th>
				<th><?=gettext("Action"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("Pri"); ?></th>
				<th><?=gettext("Proto"); ?></th>
				<th><?=gettext("Class"); ?></th>
				<th><?=gettext("Src"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("SPort"); ?></th>
				<th><?=gettext("Dst"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("DPort"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("GID:SID"); ?></th>
				<th data-sortable-type="alpha"><?=gettext("Description"); ?></th>
			   </tr>
			</thead>
			<tbody>
	<?php

/* make sure alert file exists */
if (file_exists("{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}/alerts.log")) {
	exec("tail -{$anentries} -r {$suricatalogdir}suricata_{$if_real}{$suricata_uuid}/alerts.log > {$g['tmp_path']}/alerts_suricata{$suricata_uuid}");
	if (file_exists("{$g['tmp_path']}/alerts_suricata{$suricata_uuid}")) {
		$tmpblocked = array_flip(suricata_get_blocked_ips());
		$counter = 0;

		/*************** FORMAT without CSV patch -- ALERT -- ***********************************************************************************/
		/* Line format: timestamp  action[**] [gid:sid:rev] msg [**] [Classification: class] [Priority: pri] {proto} src:srcport -> dst:dstport */
		/*             0          1           2   3   4    5                         6                 7     8      9   10         11  12       */
		/****************************************************************************************************************************************/

		/**************** FORMAT without CSV patch -- DECODER EVENT -- **************************************************************************/
		/* Line format: timestamp  action[**] [gid:sid:rev] msg [**] [Classification: class] [Priority: pri] [**] [Raw pkt: ...]                */
		/*              0          1           2   3   4    5                         6                 7                                       */
		/************** *************************************************************************************************************************/

		$fd = fopen("{$g['tmp_path']}/alerts_suricata{$suricata_uuid}", "r");
		$buf = "";
		while (($buf = fgets($fd)) !== FALSE) {
			$fields = array();
			$tmp = array();
			$decoder_event = FALSE;

			// Drop any invalid line read from the log excerpt
			if (empty(trim($buf)))
				continue;

			/**************************************************************/
			/* Parse alert log entry to find the parts we want to display */
			/**************************************************************/

			// Field 0 is the event timestamp
			$fields['time'] = substr($buf, 0, strpos($buf, '  '));

			// Field 1 is the rule action (value is '**' when mode is not inline IPS or 'block-drops-only')
			if (($a_instance['ips_mode'] == 'ips_mode_inline'  || $a_instance['block_drops_only'] == 'on') && preg_match('/\[([A-Z]+)\]\s/i', $buf, $tmp)) {
				$fields['action'] = trim($tmp[1]);
			}
			else {
				$fields['action'] = null;
			}

			// The regular expression match below returns an array as follows:
			// [2] => GID, [3] => SID, [4] => REV, [5] => MSG, [6] => CLASSIFICATION, [7] = PRIORITY
			preg_match('/\[\*{2}\]\s\[((\d+):(\d+):(\d+))\]\s(.*)\[\*{2}\]\s\[Classification:\s(.*)\]\s\[Priority:\s(\d+)\]\s/', $buf, $tmp);
			$fields['gid'] = trim($tmp[2]);
			$fields['sid'] = trim($tmp[3]);
			$fields['rev'] = trim($tmp[4]);
			$fields['msg'] = trim($tmp[5]);
			$fields['class'] = trim($tmp[6]);
			$fields['priority'] = trim($tmp[7]);

			// The regular expression match below looks for the PROTO, SRC and DST fields
			// and returns an array as follows:
			// [1] = PROTO, [2] => SRC:SPORT [3] => DST:DPORT
			if (preg_match('/\{(.*)\}\s(.*)\s->\s(.*)/', $buf, $tmp)) {
				// Get PROTO
				$fields['proto'] = trim($tmp[1]);

				// Get SRC
				$fields['src'] = trim(substr($tmp[2], 0, strrpos($tmp[2], ':')));
				if (is_ipaddrv6($fields['src']))
					$fields['src'] = inet_ntop(inet_pton($fields['src']));

				// Get SPORT
				$fields['sport'] = trim(substr($tmp[2], strrpos($tmp[2], ':') + 1));

				// Get DST
				$fields['dst'] = trim(substr($tmp[3], 0, strrpos($tmp[3], ':')));
				if (is_ipaddrv6($fields['dst']))
					$fields['dst'] = inet_ntop(inet_pton($fields['dst']));

				// Get DPORT
				$fields['dport'] = trim(substr($tmp[3], strrpos($tmp[3], ':') + 1));
			}
			else {
				// If no PROTO nor IP ADDR, then this is a DECODER EVENT
				$decoder_event = TRUE;
				$fields['proto'] = gettext("n/a");
				$fields['sport'] = gettext("n/a");
				$fields['dport'] = gettext("n/a");
			}

			// Create a DateTime object from the event timestamp that
			// we can use to easily manipulate output formats.
			try {
				$event_tm = date_create_from_format("m/d/Y-H:i:s.u", $fields['time']);
			} catch (Exception $e) {
				logger(LOG_WARNING, localize_text("found invalid timestamp entry in current alerts.log, the line will be ignored and skipped."), LOG_PREFIX_PKG_SURICATA);
				continue;
			}

			// Check the 'CATEGORY' field for the text "(null)" and
			// substitute "Not Assigned".
			if ($fields['class'] == "(null)")
				$fields['class'] = gettext("Not Assigned");

			// PHP date_format issues a bogus warning even though $event_tm really is an object
			// Suppress it with @
			@$fields['time'] = date_format($event_tm, "m/d/Y") . " " . date_format($event_tm, "H:i:s");

			if ($filterlogentries && !suricata_match_filter_field($fields, $filterfieldsarray, $filterlogentries_exact_match)) {
				continue;
			}

			/* Time */
			@$alert_time = date_format($event_tm, "H:i:s");
			/* Date */
			@$alert_date = date_format($event_tm, "m/d/Y");
			/* Description */
			$alert_descr = $fields['msg'];
			$alert_descr_url = urlencode($fields['msg']);
			/* Priority */
			$alert_priority = $fields['priority'];
			/* Protocol */
			$alert_proto = $fields['proto'];

			/* Action */
			if (isset($fields['action']) && $a_instance['blockoffenders'] == 'on' && ($a_instance['ips_mode'] == 'ips_mode_inline' || $a_instance['block_drops_only'] == 'on')) {

				switch ($fields['action']) {

					case "Drop":
					case "wDrop":
						if (isset($dropsid[$fields['gid']][$fields['sid']])) {
							$alert_action = '<i class="fa-solid fa-thumbs-down icon-pointer text-danger text-center" title="';
							$alert_action .= gettext("Rule action is User-Forced to DROP. Click to force a different action for this rule.");
						}
						elseif ($a_instance['ips_mode'] == 'ips_mode_inline' && isset($rejectsid[$fields['gid']][$fields['sid']])) {
							$alert_action = '<i class="fa-regular fa-hand icon-pointer text-warning text-center" title="';
							$alert_action .= gettext("Rule action is User-Forced to REJECT. Click to force a different action for this rule.");
						}
						else {
							$alert_action = '<i class="fa-solid fa-thumbs-down icon-pointer text-danger text-center" title="';
							$alert_action .=  gettext("Rule action is DROP. Click to force a different action for this rule.");
						}
						break;

					default:
						$alert_action = '<i class="fa-solid fa-question-circle icon-pointer text-danger text-center" title="' . gettext("Rule action is unrecognized!. Click to force a different action for this rule.");
				}
				$alert_action .= '" onClick="toggleAction(\'' . $fields['gid'] . '\', \'' . $fields['sid'] . '\');"</i>';
			}
			else {
				if ($a_instance['blockoffenders'] == 'on' && ($a_instance['ips_mode'] == 'ips_mode_inline' || $a_instance['block_drops_only'] == 'on')) {
					$alert_action = '<i class="fa-solid fa-exclamation-triangle icon-pointer text-warning text-center" title="' . gettext("Rule action is ALERT.");
					$alert_action .= '" onClick="toggleAction(\'' . $fields['gid'] . '\', \'' . $fields['sid'] . '\');"</i>';
				}
				else {
					$alert_action = '<i class="fa-solid fa-exclamation-triangle text-warning text-center" title="' . gettext("Rule action is ALERT.") . '"</i>';
				}
			}

			/* IP SRC */
			if ($decoder_event == FALSE) {
				$alert_ip_src = $fields['src'];
				/* Add zero-width space as soft-break opportunity after each colon if we have an IPv6 address */
				$alert_ip_src = str_replace(":", ":&#8203;", $alert_ip_src);
				/* Add Reverse DNS lookup icon */
				$alert_ip_src .= '<br /><i class="fa-solid fa-search" onclick="javascript:resolve_with_ajax(\'' . $fields['src'] . '\');" title="';
				$alert_ip_src .= gettext("Resolve host via reverse DNS lookup") . "\"  alt=\"Icon Reverse Resolve with DNS\" ";
				$alert_ip_src .= " style=\"cursor: pointer;\"></i>";
				/* Add GeoIP check icon */
				if (!is_private_ip($fields['src']) && (substr($fields['src'], 0, 2) != 'fc') &&
				    (substr($fields['src'], 0, 2) != 'fd')) {
					$alert_ip_src .= '&nbsp;&nbsp;<i class="fa-solid fa-globe" onclick="javascript:geoip_with_ajax(\'' . $fields['src'] . '\');" title="';
					$alert_ip_src .= gettext("Check host GeoIP data") . "\"  alt=\"Icon Check host GeoIP\" ";
					$alert_ip_src .= " style=\"cursor: pointer;\"></i>";
				}
				/* Add icons for auto-adding to Suppress List if appropriate */
				if (!suricata_is_alert_globally_suppressed($supplist, $fields['gid'], $fields['sid']) &&
				    !isset($supplist[$fields['gid']][$fields['sid']]['by_src'][$fields['src']])) {
					$alert_ip_src .= "&nbsp;&nbsp;<i class=\"fa-regular fa-square-plus icon-pointer\" title=\"" . gettext('Add this alert to the Suppress List and track by_src IP') . '"';
					$alert_ip_src .= " onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','{$fields['src']}','{$alert_descr}');$('#mode').val('addsuppress_srcip');$('#formalert').submit();\"></i>";
				}
				elseif (isset($supplist[$fields['gid']][$fields['sid']]['by_src'][$fields['src']])) {
					$alert_ip_src .= '&nbsp;&nbsp;<i class="fa-solid fa-info-circle" ';
					$alert_ip_src .= 'title="' . gettext("This alert track by_src IP is already in the Suppress List") . '"></i>';
				}
				/* Add icon for auto-removing from Blocked Table if required */
				if (isset($tmpblocked[$fields['src']])) {
					$alert_ip_src .= "&nbsp;&nbsp;<i class=\"fa-solid fa-times icon-pointer text-danger\" onClick=\"$('#ip').val('{$fields['src']}');$('#mode').val('unblock');$('#formalert').submit();\"";
					$alert_ip_src .= ' title="' . gettext("Remove host from Blocked Table") . '"></i>';
				}
			}
			else {
				if (preg_match('/\s\[Raw pkt:(.*)\]/', $buf, $tmp))
					$alert_ip_src = "<div title='[Raw pkt: {$tmp[1]}]'>" . gettext("Decoder Event") . "</div>";
				else
					$alert_ip_src = gettext("Decoder Event");
			}

			/* IP SRC Port */
			$alert_src_p = $fields['sport'];

			/* IP DST */
			if ($decoder_event == FALSE) {
				$alert_ip_dst = $fields['dst'];
				/* Add zero-width space as soft-break opportunity after each colon if we have an IPv6 address */
				$alert_ip_dst = str_replace(":", ":&#8203;", $alert_ip_dst);
				/* Add Reverse DNS lookup icons */
				$alert_ip_dst .= "<br /><i class=\"fa-solid fa-search\" onclick=\"javascript:resolve_with_ajax('{$fields['dst']}');\" title=\"";
				$alert_ip_dst .= gettext("Resolve host via reverse DNS lookup") . "\" alt=\"Icon Reverse Resolve with DNS\" ";
				$alert_ip_dst .= " style=\"cursor: pointer;\"></i>";
				/* Add GeoIP check icon */
				if (!is_private_ip($fields['dst']) && (substr($fields['dst'], 0, 2) != 'fc') &&
				    (substr($fields['dst'], 0, 2) != 'fd')) {
					$alert_ip_dst .= '&nbsp;&nbsp;<i class="fa-solid fa-globe" onclick="javascript:geoip_with_ajax(\'' . $fields['dst'] . '\');" title="';
					$alert_ip_dst .= gettext("Check host GeoIP data") . "\"  alt=\"Icon Check host GeoIP\" ";
					$alert_ip_dst .= " style=\"cursor: pointer;\"></i>";
				}
				/* Add icons for auto-adding to Suppress List if appropriate */
				if (!suricata_is_alert_globally_suppressed($supplist, $fields['gid'], $fields['sid']) &&
				    !isset($supplist[$fields['gid']][$fields['sid']]['by_dst'][$fields['dst']])) {
					$alert_ip_dst .= "&nbsp;&nbsp;<i class=\"fa-regular fa-square-plus icon-pointer\" onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','{$fields['dst']}','{$alert_descr}');$('#mode').val('addsuppress_dstip');$('#formalert').submit();\"";
					$alert_ip_dst .= ' title="' . gettext("Add this alert to the Suppress List and track by_dst IP") . '"></i>';
				}
				elseif (isset($supplist[$fields['gid']][$fields['sid']]['by_dst'][$fields['dst']])) {
					$alert_ip_dst .= '&nbsp;<i class="fa-solid fa-info-circle" ';
					$alert_ip_dst .= 'title="' . gettext("This alert track by_dst IP is already in the Suppress List") . '"></i>';
				}

				/* Add icon for auto-removing from Blocked Table if required */
				if (isset($tmpblocked[$fields['dst']])) {
					$alert_ip_dst .= '&nbsp;&nbsp;<i name="todelete[]" class="fa-solid fa-times icon-pointer text-danger" onClick="$(\'#ip\').val(\'' . $fields['dst'] . '\');$(\'#mode\').val(\'unblock\');$(\'#formalert\').submit();" ';
					$alert_ip_dst .= ' title="' . gettext("Remove host from Blocked Table") . '"></i>';
				}
			}
			else {
				$alert_ip_dst = gettext("n/a");
			}

			/* IP DST Port */
			$alert_dst_p = $fields['dport'];

			/* SID */
			$alert_sid_str = '<a onclick="javascript:showRuleContents(\'' .
				    $fields['gid'] . '\',\'' . $fields['sid'] . '\');" title="' .
				    gettext("Show the rule") . '" style="cursor: pointer;" >' .
		       		    $fields['gid'] . ':' . $fields['sid'] . '</a>';
			if (!suricata_is_alert_globally_suppressed($supplist, $fields['gid'], $fields['sid'])) {
				$sidsupplink = "<i class=\"fa-regular fa-square-plus icon-pointer\" onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','','{$alert_descr}');$('#mode').val('addsuppress');$('#formalert').submit();\"";
				$sidsupplink .= ' title="' . gettext("Add this alert to the Suppress List") . '"></i>';
			}
			else {
				$sidsupplink = '&nbsp;<i class="fa-solid fa-info-circle" ';
				$sidsupplink .= "title='" . gettext("This alert is already in the Suppress List") . "'></i>";
			}
			/* Add icon for toggling rule state */
			if (isset($disablesid[$fields['gid']][$fields['sid']])) {
				$sid_dsbl_link = "<i class=\"fa-solid fa-times-circle icon-pointer text-warning\" onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','','');$('#mode').val('togglesid');$('#formalert').submit();\"";
				$sid_dsbl_link .= ' title="' . gettext("Rule is forced to a disabled state. Click to remove the force-disable action from this rule.") . '"></i>';
			}
			else {
				$sid_dsbl_link = "<i class=\"fa-solid fa-times icon-pointer text-danger\" onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','','');$('#mode').val('togglesid');$('#formalert').submit();\"";
				$sid_dsbl_link .= ' title="' . gettext("Force-disable this rule and remove it from current rules set.") . '"></i>';
			}

			/* Add icon for toggling rule action if applicable to current mode */
			if ($a_instance['blockoffenders'] == 'on') {
				if ($a_instance['block_drops_only'] == 'on' || $a_instance['ips_mode'] == 'ips_mode_inline') {
					$sid_action_link = "<i class=\"fa-regular fa-pen-to-square icon-pointer text-info\" onClick=\"toggleAction('{$fields['gid']}', '{$fields['sid']}');\"";
					$sid_action_link .= ' title="' . gettext("Click to force a different action for this rule.") . '"></i>';
					if (isset($alertsid[$fields['gid']][$fields['sid']])) {
						$sid_action_link = "<i class=\"fa-solid fa-exclamation-triangle icon-pointer text-warning\" onClick=\"toggleAction('{$fields['gid']}', '{$fields['sid']}');\"";
						$sid_action_link .= ' title="' . gettext("Rule is forced to ALERT. Click to change the action for this rule.") . '"></i>';
					}
					if (isset($rejectsid[$fields['gid']][$fields['sid']])) {
						$sid_action_link = "<i class=\"fa-regular fa-hand icon-pointer text-warning\" onClick=\"toggleAction('{$fields['gid']}', '{$fields['sid']}');\"";
						$sid_action_link .= ' title="' . gettext("Rule is forced to REJECT. Click to change the action for this rule.") . '"></i>';
					}
					if (isset($dropsid[$fields['gid']][$fields['sid']])) {
						$sid_action_link = "<i class=\"fa-solid fa-thumbs-down icon-pointer text-danger\" onClick=\"toggleAction('{$fields['gid']}', '{$fields['sid']}');\"";
						$sid_action_link .= ' title="' . gettext("Rule is forced to DROP. Click to change the action for this rule.") . '"></i>';
					}
				}
			}
			else {
				$sid_action_link = '';
			}

			/* DESCRIPTION */
			$alert_class = $fields['class'];
	?>
	<?php if ($fields['action']) : ?>
			<tr class="text-danger">
	<?php else : ?>
			<tr>
	<?php endif; ?>
				<td><?=$alert_date;?><br/><?=$alert_time;?></td>
				<td><?=$alert_action; ?></td>
				<td><?=$alert_priority;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_proto;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_class;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_ip_src;?></td>
				<td><?=$alert_src_p;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_ip_dst;?></td>
				<td><?=$alert_dst_p;?></td>
				<td><?=$alert_sid_str;?><br/><?=$sidsupplink;?>&nbsp;&nbsp;<?=$sid_dsbl_link;?>&nbsp;&nbsp;<?=$sid_action_link;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_descr;?></td>
			</tr>
	<?php
			$counter++;
		}
		unset($fields, $buf, $tmp);
		fclose($fd);
		unlink_if_exists("{$g['tmp_path']}/alerts_suricata{$suricata_uuid}");
	}
}
	?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($a_instance['blockoffenders'] == 'on') : ?>
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

			<?php if ($a_instance['ips_mode'] == 'ips_mode_inline' && $a_instance['blockoffenders'] == 'on') : ?>
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

<?php
// Create a Modal object to display text of user-clicked rules
$form = new Form(FALSE);
$modal = new Modal('View Rules Text', 'rulesviewer', 'large', 'Close');
$modal->addInput(new Form_StaticText (
	'Category',
	'<div class="text-left" id="modal_rule_category"></div>'
))->setHelp('<span id="modal_rule_link_text"></span><a id="modal_rule_link" target="_blank"></a>');
$modal->addInput(new Form_Textarea (
	'rulesviewer_text',
	'Rule Text',
	'...Loading...'
))->removeClass('form-control')->addClass('row-fluid col-sm-10')->setAttribute('rows', '10')->setAttribute('wrap', 'soft');
$form->add($modal);
print($form);
?>

<script type="text/javascript">
//<![CDATA[

//-- This function stuffs the passed GID, SID and other values into
//-- hidden Form Fields for postback.
function encRuleSig(rulegid,rulesid,srcip,ruledescr) {

	if (typeof srcipip === 'undefined') {
		var srcipip = '';
	}

	if (typeof ruledescr === 'undefined'){
		var ruledescr = '';
	}

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

function enable_showFilter() {
	document.getElementById("filter_enable_row").style.display="none";
	document.getElementById("filter_options_row").style.display="table-row";
}

function enable_hideFilter() {
	document.getElementById("filter_enable_row").style.display="table-row";
	document.getElementById("filter_options_row").style.display="none";
}

</script>

<!-- The following AJAX code was borrowed from the diag_logs_filter.php -->
<!-- file in pfSense.  See copyright info at top of this page.          -->
<script type="text/javascript">
//<![CDATA[
function resolve_with_ajax(ip_to_resolve) {
	var url = "/suricata/suricata_alerts.php";

	$.ajax(
		url,
		{
			type: 'post',
			dataType: 'json',
			data: {
				resolve: ip_to_resolve,
			      },
			complete: resolve_ip_callback
		});
}

function resolve_ip_callback(transport) {
	var response = $.parseJSON(transport.responseText);
	var msg = 'IP address "' + response.resolve_ip + '" resolves to\n';
	alert(msg + 'host "' + htmlspecialchars(response.resolve_text) + '"');
}

function geoip_with_ajax(ip_to_check) {
	var url = "/suricata/suricata_alerts.php";

	$.ajax(
		url,
		{
			type: 'post',
			dataType: 'json',
			data: {
				geoip: ip_to_check,
			      },
			complete: geoip_callback
		});
}

function geoip_callback(transport) {
	var response = $.parseJSON(transport.responseText);
	alert(htmlspecialchars(response.geoip_text));
}

function showRuleContents(gid, sid) {
		// Show the modal dialog with rule text
		$('#rulesviewer_text').text("...Loading...");
		$('#rulesviewer').modal('show');
		$('#modal_rule_category').text("...Loading...");
		$('#modal_rule_link_text').text('');
		$('#modal_rule_link').attr('href', '');
		$('#modal_rule_link').text('');

		$.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>",
			{
				type: 'post',
				data: {
					sid:         sid,
					gid:         gid,
					id:	     $('#id').val(),
					openruleset: $('#selectbox').val(),
					action:      'loadRule'
				},
				complete: loadComplete
			}
		);
}

function loadComplete(req) {
		var response = $.parseJSON(req.responseText);
		$('#modal_rule_category').html(response.category);
		$('#rulesviewer_text').text(atob(response.rule_text));
		$('#rulesviewer_text').attr('readonly', true);
		if (response.rule_link) {
			$('#modal_rule_link_text').text('Snort Rule Doc: ');
			$('#modal_rule_link').attr('href', response.rule_link);
			$('#modal_rule_link').text(response.rule_link);
		}
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

	// When 'filterlogentries_action_drop' is clicked, uncheck 'filterlogentries_action_ndrop' control
	$('#filterlogentries_action_drop').click(function() {
		$('#filterlogentries_action_ndrop').prop('checked', false);
	});

	// When 'filterlogentries_action_ndrop' is clicked, uncheck 'filterlogentries_action_drop' control
	$('#filterlogentries_action_ndrop').click(function() {
		$('#filterlogentries_action_drop').prop('checked', false);
	});

});
//]]>
</script>
<?php
include("foot.inc");
?>

