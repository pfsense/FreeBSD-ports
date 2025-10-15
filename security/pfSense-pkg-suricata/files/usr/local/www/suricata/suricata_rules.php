<?php
/*
 * suricata_rules.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2024 Bill Meeks
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

global $g, $rebuild_rules;

$suricatadir = SURICATADIR;
$rules_map = array();
$pconfig = array();
$filterrules = FALSE;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

// If postback is from system function print_apply_box(),
// then we won't have our customary $_POST['id'] and
// $_POST['openruleset'] fields set in the response,
// but the system function will pass back a
// $_POST['if'] field we can use instead.
if (!is_numericint($id)) {
	if (isset($_POST['if'])) {
		// Split the posted string at the '|' delimiter
		$response = explode('|', $_POST['if']);
		$id = $response[0];
		$_POST['openruleset'] = $response[1];
	}
	else {
		$id = 0;
	}
}

$a_rule = config_get_path("installedpackages/suricata/rule/{$id}", []);

if (!empty($a_rule)) {
	$pconfig['interface'] = $a_rule['interface'];
	$pconfig['rulesets'] = $a_rule['rulesets'];
	$pconfig['customrules'] = base64_decode($a_rule['customrules']);
}

function add_title_attribute($tag, $title) {

	/********************************
	 * This function adds a "title" *
	 * attribute to the passed tag  *
	 * and sets the value to the    *
	 * value specified by "$title". *
	 ********************************/
	$result = "";
	if (empty($tag)) {
		// If passed an empty element tag, then
		// just create a <span> tag with title
		$result = "<span title=\"" . $title . "\">";
	}
	else {
		// Find the ending ">" for the element tag
		$pos = strpos($tag, ">");
		if ($pos !== false) {
			// We found the ">" delimter, so add "title"
			// attribute and close the element tag
			$result = substr($tag, 0, $pos) . " title=\"" . $title . "\">";
		}
		else {
			// We did not find the ">" delimiter, so
			// something is wrong, just return the
			// tag "as-is"
			$result = $tag;
		}
	}
	return $result;
}

/* convert fake interfaces to real */
$if_real = get_real_interface($pconfig['interface']);
$suricata_uuid = $a_rule['uuid'];
$suricatacfgdir = "{$suricatadir}suricata_{$suricata_uuid}_{$if_real}";
$suricata_rules_dir = SURICATA_RULES_DIR;
$snortdownload = config_get_path('installedpackages/suricata/config/0/enable_vrt_rules');
$emergingdownload = config_get_path('installedpackages/suricata/config/0/enable_etopen_rules');
$etpro = config_get_path('installedpackages/suricata/config/0/enable_etpro_rules');
$categories = explode("||", $pconfig['rulesets']);

// Get any automatic rule category enable/disable modifications
// if auto-SID Mgmt is enabled, and adjust the available rulesets
// in the CATEGORY drop-down box as necessary by removing disabled
// categories and adding enabled ones.
$cat_mods = suricata_sid_mgmt_auto_categories($a_rule, FALSE);
foreach ($cat_mods as $k => $v) {
	switch ($v) {
		case 'disabled':
			if (($key = array_search($k, $categories, true)) !== FALSE)
				unset($categories[$key]);
			break;

		case 'enabled':
			if (!in_array($k, $categories))
				$categories[] = $k;
			break;

		default:
			break;
	}
}

// Add custom Categories list items for User Forced rules
$categories[] = "User Forced Enabled Rules";
$categories[] = "User Forced Disabled Rules";

// Only add custom ALERT or DROP Action Rules
// option if blocking is enabled.
if ($a_rule['blockoffenders'] == 'on') {
	$categories[] = "User Forced ALERT Action Rules";

	// Show custom DROP rules only if using Inline IPS
	// mode or "Block Drops Only" option.
	if ($a_rule['block_drops_only'] == 'on' || $a_rule['ips_mode'] == 'ips_mode_inline') {
		$categories[] = "User Forced DROP Action Rules";
	}
}

// Only add custom REJECT option if using IPS Inline Mode with blocking enabled
if ($a_rule['ips_mode'] == 'ips_mode_inline' && $a_rule['blockoffenders'] == 'on') {
	$categories[] = "User Forced REJECT Action Rules";
}

// Add custom Category to view all Active Rules
// on the interface.
$categories[] = "Active Rules";

// See if we should open a specific ruleset or
// just default to the first one in the list.
if ($_GET['openruleset'])
	$currentruleset = htmlspecialchars($_GET['openruleset'], ENT_QUOTES | ENT_HTML401);
elseif ($_POST['selectbox'])
	$currentruleset = $_POST['selectbox'];
elseif ($_POST['openruleset'])
	$currentruleset = $_POST['openruleset'];
else
	$currentruleset = $categories[array_key_first($categories)];

$currentruleset = basename($currentruleset);

// If we don't have any Category to display, then
// default to showing the Custom Rules text control.
if (empty($categories) && ($currentruleset != "custom.rules") && ($currentruleset != "Auto-Flowbit Rules")) {
	if (!empty($a_rule['ips_policy']))
		$currentruleset = "IPS Policy - " . ucfirst($a_rule['ips_policy']);
	else
		$currentruleset = "custom.rules";
}

// One last sanity check -- if the rules directory is empty, or we were
// not passed a ruleset name to load, default to loading custom rules.
$tmp = glob("{$suricata_rules_dir}*.rules");
if (empty($tmp) || empty($currentruleset))
	$currentruleset = "custom.rules";

$ruledir = SURICATA_RULES_DIR;
$rulefile = "{$ruledir}{$currentruleset}";

if ($currentruleset != 'custom.rules') {
	// Read the currently selected rules file into our rules map array.
	// There are a few special cases possible, so test and adjust as
	// necessary to get the correct set of rules to display.

	// If it is the auto-flowbits file, set the full path.
	if ($currentruleset == "Auto-Flowbit Rules") {
		$rules_map = suricata_load_rules_map("{$suricatacfgdir}/rules/" . FLOWBITS_FILENAME);
	}
	// Test for the special case of an IPS Policy file
	// and load the selected policy's rules.
	elseif (substr($currentruleset, 0, 10) == "IPS Policy") {
		$rules_map = suricata_load_vrt_policy($a_rule['ips_policy'], $a_rule['ips_policy_mode']);
	}
	// Test for the special case of "Active Rules".  This
	// displays all currently active rules for the
	// interface.
	elseif ($currentruleset == "Active Rules") {
		$rules_map = suricata_load_rules_map("{$suricatacfgdir}/rules/");
	}
	// Test for the special cases of "User Forced" rules
	// and load the required rules for display.
	elseif ($currentruleset == "User Forced Enabled Rules") {
		// Search and display forced enabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Suricata rules path to each entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . $v;
		}
		$rule_files[] = "{$suricatacfgdir}/rules/" . FLOWBITS_FILENAME;
		$rule_files[] = "{$suricatacfgdir}/rules/custom.rules";
		$rules_map = suricata_get_filtered_rules($rule_files, suricata_load_sid_mods($a_rule['rule_sid_on']));
	}
	elseif ($currentruleset == "User Forced Disabled Rules") {
		// Search and display forced disabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Suricata rules path to each entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . $v;
		}
		$rule_files[] = "{$suricatacfgdir}/rules/" . FLOWBITS_FILENAME;
		$rule_files[] = "{$suricatacfgdir}/rules/custom.rules";
		$rules_map = suricata_get_filtered_rules($rule_files, suricata_load_sid_mods($a_rule['rule_sid_off']));
	}
	elseif ($currentruleset == "User Forced ALERT Action Rules") {
		$rules_map = suricata_get_filtered_rules("{$suricatacfgdir}/rules/", suricata_load_sid_mods($a_rule['rule_sid_force_alert']));
	}
	elseif ($currentruleset == "User Forced DROP Action Rules") {
		$rules_map = suricata_get_filtered_rules("{$suricatacfgdir}/rules/", suricata_load_sid_mods($a_rule['rule_sid_force_drop']));
	}
	elseif ($currentruleset == "User Forced REJECT Action Rules") {
		$rules_map = suricata_get_filtered_rules("{$suricatacfgdir}/rules/", suricata_load_sid_mods($a_rule['rule_sid_force_reject']));
	}
	// If it's not a special case, and we can't find
	// the given rule file, then notify the user.
	elseif (!file_exists($rulefile)) {
		$input_errors[] = gettext("{$currentruleset} seems to be missing!!! Please verify rules files have been downloaded, then go to the Categories tab and save the rule set again.");
	}
	// Not a special case, and we have the matching
	// rule file, so load it up for display.
	else {
		$rules_map = suricata_load_rules_map($rulefile);
	}
}

/* Process the current category rules through any auto SID MGMT changes if enabled */
suricata_auto_sid_mgmt($rules_map, $a_rule, FALSE);

/* Load up our enablesid and disablesid arrays with manually enabled or disabled SIDs */
$enablesid = suricata_load_sid_mods($a_rule['rule_sid_on']);
$disablesid = suricata_load_sid_mods($a_rule['rule_sid_off']);
suricata_modify_sids($rules_map, $a_rule);

/* Load up our rule action arrays with manually changed SID actions */
$alertsid = suricata_load_sid_mods($a_rule['rule_sid_force_alert']);
$dropsid = suricata_load_sid_mods($a_rule['rule_sid_force_drop']);
$rejectsid = suricata_load_sid_mods($a_rule['rule_sid_force_reject']);
suricata_modify_sids_action($rules_map, $a_rule);

/* Process AJAX request to view content of a specific rule */
if ($_POST['action'] == 'loadRule') {
	if (isset($_POST['gid']) && isset($_POST['sid'])) {
		$gid = $_POST['gid'];
		$sid = $_POST['sid'];
		$rule_text = base64_encode($rules_map[$gid][$sid]['rule']);
	}
	else {
		$rule_text = base64_encode(gettext('Invalid rule signature - no matching rule was found!'));
	}
	if (strpos($currentruleset, 'snort_') !== false) {
		$rule_link = "https://www.snort.org/rule_docs/{$gid}-{$sid}";
	} else {
		$rule_link = "";
	}	
	$response = array('rule_text' => $rule_text, 'rule_link' => $rule_link);
	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}

if (isset($_POST['rule_state_save']) && isset($_POST['ruleStateOptions']) && is_numeric($_POST['sid']) && is_numeric($_POST['gid']) && !empty($rules_map)) {

	// Get the GID:SID tags embedded in the clicked rule icon.
	$gid = $_POST['gid'];
	$sid = $_POST['sid'];

	// Get the posted rule state
	$state = $_POST['ruleStateOptions'];

	// Use the user-desired rule state to set or clear
	// entries in the Forced Rule State arrays stored
	// in the firewall config.xml configuration file.

	switch ($state) {
		case "state_default":
			// Return the rule to it's default state
			// by removing all state override entries.
			array_del_path($enablesid, "{$gid}/{$sid}");
			array_del_path($disablesid, "{$gid}/{$sid}");

			// Restore the default state flag so we
			// can display state properly on RULES
			// page without needing to reload the
			// entire set of rules.
			if (array_get_path($rules_map, "{$gid}/{$sid}")) {
				$rules_map[$gid][$sid]['disabled'] = !$rules_map[$gid][$sid]['default_state'];
			}
			break;

		case "state_enabled":
			array_del_path($disablesid, "{$gid}/{$sid}");
			array_set_path($enablesid, "{$gid}/{$sid}", 'enablesid');
			break;

		case "state_disabled":
			array_del_path($enablesid, "{$gid}/{$sid}");
			array_set_path($disablesid, "{$gid}/{$sid}", 'disablesid');
			break;

		default:
			$input_errors[] = gettext("WARNING - unknown rule state of '{$state}' passed in $_POST parameter.  No change made to rule state.");
	}

	// Write the updated enablesid and disablesid values to the config file.
	$tmp = "";
	foreach (array_keys($enablesid) as $k1) {
		foreach (array_keys($enablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule['rule_sid_on'] = $tmp;
	else
		unset($a_rule['rule_sid_on']);

	$tmp = "";
	foreach (array_keys($disablesid) as $k1) {
		foreach (array_keys($disablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule['rule_sid_off'] = $tmp;
	else
		unset($a_rule['rule_sid_off']);

	/* Update the config.xml file. */
	config_set_path("installedpackages/suricata/rule/{$id}", $a_rule);
	write_config("Suricata pkg: modified state for rule {$gid}:{$sid} on {$a_rule['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');

	// Update our in-memory rules map with the changes just saved
	// to the Suricata configuration file.
	suricata_modify_sids($rules_map, $a_rule);

	// Set a scroll-to anchor location
	$anchor = "rule_{$gid}_{$sid}";
}
elseif (isset($_POST['rule_action_save']) && isset($_POST['ruleActionOptions']) && is_numeric($_POST['sid']) && is_numeric($_POST['gid']) && !empty($rules_map)) {

	// Get the GID:SID tags embedded in the clicked rule icon.
	$gid = $_POST['gid'];
	$sid = $_POST['sid'];

	// Get the posted rule action
	$action = $_POST['ruleActionOptions'];

	// Put the target SID in the appropriate lists of modified
	// SID actions based on the requested action; if default
	// action is requested, remove the SID from all SID modified
	// action lists.
	switch ($action) {
		case "action_default":
			$rules_map[$gid][$sid]['action'] = $rules_map[$gid][$sid]['default_action'];
			array_del_path($alertsid, "{$gid}/{$sid}");
			array_del_path($dropsid, "{$gid}/{$sid}");
			array_del_path($rejectsid, "{$gid}/{$sid}");
			break;

		case "action_alert":
			$rules_map[$gid][$sid]['action'] = $rules_map[$gid][$sid]['alert'];
			array_set_path($alertsid, "{$gid}/{$sid}", "alertsid");
			array_del_path($dropsid, "{$gid}/{$sid}");
			array_del_path($rejectsid, "{$gid}/{$sid}");
			break;

		case "action_drop":
			$rules_map[$gid][$sid]['action'] = $rules_map[$gid][$sid]['drop'];
			array_set_path($dropsid, "{$gid}/{$sid}", "dropsid");
			array_del_path($alertsid, "{$gid}/{$sid}");
			array_del_path($rejectsid, "{$gid}/{$sid}");
			break;

		case "action_reject":
			$rules_map[$gid][$sid]['action'] = $rules_map[$gid][$sid]['reject'];
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
			$a_rule['rule_sid_force_alert'] = $tmp;
		else
			unset($a_rule['rule_sid_force_alert']);

		$tmp = "";
		foreach (array_keys($dropsid) as $k1) {
			foreach (array_keys($dropsid[$k1]) as $k2)
				$tmp .= "{$k1}:{$k2}||";
		}
		$tmp = rtrim($tmp, "||");

		if (!empty($tmp))
			$a_rule['rule_sid_force_drop'] = $tmp;
		else
			unset($a_rule['rule_sid_force_drop']);

		$tmp = "";
		foreach (array_keys($rejectsid) as $k1) {
			foreach (array_keys($rejectsid[$k1]) as $k2)
				$tmp .= "{$k1}:{$k2}||";
		}
		$tmp = rtrim($tmp, "||");

		if (!empty($tmp))
			$a_rule['rule_sid_force_reject'] = $tmp;
		else
			unset($a_rule['rule_sid_force_reject']);

		/* Update the config.xml file. */
		config_set_path("installedpackages/suricata/rule/{$id}", $a_rule);
		write_config("Suricata pkg: modified action for rule {$gid}:{$sid} on {$a_rule['interface']}.");

		// We changed a rule action, remind user to apply the changes
		mark_subsystem_dirty('suricata_rules');

		// Update our in-memory rules map with the changes just saved
		// to the Suricata configuration file.
		suricata_modify_sids_action($rules_map, $a_rule);

		// Set a scroll-to anchor location
		$anchor = "rule_{$gid}_{$sid}";
	}
}
elseif (isset($_POST['disable_all']) && !empty($rules_map)) {
	// Mark all rules in the currently selected category "disabled".
	foreach (array_keys($rules_map) as $k1) {
		foreach (array_keys($rules_map[$k1]) as $k2) {
			array_del_path($enablesid, "{$k1}/{$k2}");
			array_set_path($disablesid, "{$k1}/{$k2}", 'disablesid');
		}
	}

	// Write the updated enablesid and disablesid values to the config file.
	$tmp = "";
	foreach (array_keys($enablesid) as $k1) {
		foreach (array_keys($enablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule['rule_sid_on'] = $tmp;
	else
		unset($a_rule['rule_sid_on']);

	$tmp = "";
	foreach (array_keys($disablesid) as $k1) {
		foreach (array_keys($disablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule['rule_sid_off'] = $tmp;
	else
		unset($a_rule['rule_sid_off']);

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');
	config_set_path("installedpackages/suricata/rule/{$id}", $a_rule);
	write_config("Suricata pkg: disabled all rules in category {$currentruleset} for {$a_rule['interface']}.");

	// Update our in-memory rules map with the changes just saved
	// to the Suricata configuration file.
	suricata_modify_sids($rules_map, $a_rule);
}
elseif (isset($_POST['enable_all']) && !empty($rules_map)) {

	// Mark all rules in the currently selected category "enabled".
	foreach (array_keys($rules_map) as $k1) {
		foreach (array_keys($rules_map[$k1]) as $k2) {
			array_del_path($disablesid, "{$k1}/{$k2}");
			array_set_path($enablesid, "{$k1}/{$k2}", 'enablesid');
		}
	}
	// Write the updated enablesid and disablesid values to the config file.
	$tmp = "";
	foreach (array_keys($enablesid) as $k1) {
		foreach (array_keys($enablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule['rule_sid_on'] = $tmp;
	else
		unset($a_rule['rule_sid_on']);

	$tmp = "";
	foreach (array_keys($disablesid) as $k1) {
		foreach (array_keys($disablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule['rule_sid_off'] = $tmp;
	else
		unset($a_rule['rule_sid_off']);

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');
	config_set_path("installedpackages/suricata/rule/{$id}", $a_rule);
	write_config("Suricata pkg: enable all rules in category {$currentruleset} for {$a_rule['interface']}.");

	// Update our in-memory rules map with the changes just saved
	// to the Suricata configuration file.
	suricata_modify_sids($rules_map, $a_rule);
}
elseif (isset($_POST['resetcategory']) && !empty($rules_map)) {

	// Reset any modified SIDs in the current rule category to their defaults.
	foreach (array_keys($rules_map) as $k1) {
		foreach (array_keys($rules_map[$k1]) as $k2) {
			array_del_path($enablesid, "{$k1}/{$k2}");
			array_del_path($disablesid, "{$k1}/{$k2}");
			array_del_path($alertsid, "{$k1}/{$k2}");
			array_del_path($dropsid, "{$k1}/{$k2}");
			array_del_path($rejectsid, "{$k1}/{$k2}");
		}
	}

	// Write the updated enablesid and disablesid values to the config file.
	$tmp = "";
	foreach (array_keys($enablesid) as $k1) {
		foreach (array_keys($enablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule['rule_sid_on'] = $tmp;
	else
		unset($a_rule['rule_sid_on']);

	$tmp = "";
	foreach (array_keys($disablesid) as $k1) {
		foreach (array_keys($disablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule['rule_sid_off'] = $tmp;
	else
		unset($a_rule['rule_sid_off']);

	// Write the updated alertsid, dropsid and rejectsid values to the config file.
	$tmp = "";
	foreach (array_keys($alertsid) as $k1) {
		foreach (array_keys($alertsid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule['rule_sid_force_alert'] = $tmp;
	else
		unset($a_rule['rule_sid_force_alert']);

	$tmp = "";
	foreach (array_keys($dropsid) as $k1) {
		foreach (array_keys($dropsid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule['rule_sid_force_drop'] = $tmp;
	else
		unset($a_rule['rule_sid_force_drop']);

	$tmp = "";
	foreach (array_keys($rejectsid) as $k1) {
		foreach (array_keys($rejectsid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule['rule_sid_force_reject'] = $tmp;
	else
		unset($a_rule['rule_sid_force_reject']);

	// We changed a rule state or action, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');
	config_set_path("installedpackages/suricata/rule/{$id}", $a_rule);
	write_config("Suricata pkg: remove rule state/action changes for category {$currentruleset} on {$a_rule['interface']}.");

	// Reload the rules so we can accurately show content after
	// resetting any user overrides.
	// Test for the auto-flowbits file.
	if ($currentruleset == "Auto-Flowbit Rules") {
		$rulefile = "{$suricatacfgdir}/rules/" . FLOWBITS_FILENAME;
		$rules_map = suricata_load_rules_map($rulefile);
	}
	// Test for the special case of an IPS Policy file
	// and load the selected policy's rules.
	elseif (substr($currentruleset, 0, 10) == "IPS Policy") {
		$rules_map = suricata_load_vrt_policy($a_rule['ips_policy'], $a_rule['ips_policy_mode']);
	}
	// Test for the special case of "Active Rules".  This
	// displays all currently active rules for the
	// interface.
	elseif ($currentruleset == "Active Rules") {
		$rules_map = suricata_load_rules_map("{$suricatacfgdir}/rules/");
	}
	// Test for the special cases of "User Forced" rules
	// and load the required rules for display.
	elseif ($currentruleset == "User Forced Enabled Rules") {
		// Search and display forced enabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Suricata rules path to each entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . $v;
		}
		$rule_files[] = "{$suricatacfgdir}/rules/" . FLOWBITS_FILENAME;
		$rule_files[] = "{$suricatacfgdir}/rules/custom.rules";
		$rules_map = suricata_get_filtered_rules($rule_files, suricata_load_sid_mods($a_rule['rule_sid_on']));
	}
	elseif ($currentruleset == "User Forced Disabled Rules") {
		// Search and display forced disabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Suricata rules path to each entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . $v;
		}
		$rule_files[] = "{$suricatacfgdir}/rules/" . FLOWBITS_FILENAME;
		$rule_files[] = "{$suricatacfgdir}/rules/custom.rules";
		$rules_map = suricata_get_filtered_rules($rule_files, suricata_load_sid_mods($a_rule['rule_sid_off']));
	}
	elseif ($currentruleset == "User Forced ALERT Action Rules") {
		$rules_map = suricata_get_filtered_rules("{$suricatacfgdir}/rules/", suricata_load_sid_mods($a_rule['rule_sid_force_alert']));
	}
	elseif ($currentruleset == "User Forced DROP Action Rules") {
		$rules_map = suricata_get_filtered_rules("{$suricatacfgdir}/rules/", suricata_load_sid_mods($a_rule['rule_sid_force_drop']));
	}
	elseif ($currentruleset == "User Forced REJECT Action Rules") {
		$rules_map = suricata_get_filtered_rules("{$suricatacfgdir}/rules/", suricata_load_sid_mods($a_rule['rule_sid_force_reject']));
	}
	// If it's not a special case, and we can't find
	// the given rule file, then notify the user.
	elseif (!file_exists($rulefile)) {
		$input_errors[] = gettext("{$currentruleset} seems to be missing!!! Please verify rules files have been downloaded, then go to the Categories tab and save the rule set again.");
	}
	// Not a special case, and we have the matching
	// rule file, so load it up for display.
	else {
		$rules_map = suricata_load_rules_map($rulefile);
	}
}
elseif (isset($_POST['resetall']) && !empty($rules_map)) {

	// Remove all modified SIDs from config.xml and save the changes.
	unset($a_rule['rule_sid_on']);
	unset($a_rule['rule_sid_off']);
	unset($a_rule['rule_sid_force_alert']);
	unset($a_rule['rule_sid_force_drop']);
	unset($a_rule['rule_sid_force_reject']);

	// We changed a rule state or action, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');

	/* Update the config.xml file. */
	config_set_path("installedpackages/suricata/rule/{$id}", $a_rule);
	config_set_path("installedpackages/suricata/rule/{$id}", $a_rule);
	write_config("Suricata pkg: remove all rule state/action changes for {$a_rule['interface']}.");

	// Reload the rules so we can accurately show content after
	// resetting any user overrides.
	// If it is the auto-flowbits file, set the full path.
	if ($currentruleset == "Auto-Flowbit Rules") {
		$rulefile = "{$suricatacfgdir}/rules/" . FLOWBITS_FILENAME;
	}
	// Test for the special case of an IPS Policy file
	// and load the selected policy's rules.
	elseif (substr($currentruleset, 0, 10) == "IPS Policy") {
		$rules_map = suricata_load_vrt_policy($a_rule['ips_policy'], $a_rule['ips_policy_mode']);
	}
	// Test for the special case of "Active Rules".  This
	// displays all currently active rules for the
	// interface.
	elseif ($currentruleset == "Active Rules") {
		$rules_map = suricata_load_rules_map("{$suricatacfgdir}/rules/");
	}
	// Test for the special cases of "User Forced" rules
	// and load the required rules for display.
	elseif ($currentruleset == "User Forced Enabled Rules") {
		// Search and display forced enabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Suricata rules path to each entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . $v;
		}
		$rules_file[] = "{$suricatacfgdir}/rules/" . FLOWBITS_FILENAME;
		$rules_file[] = "{$suricatacfgdir}/rules/custom.rules";
		$rules_map = suricata_get_filtered_rules($rule_files, suricata_load_sid_mods($a_rule['rule_sid_on']));
	}
	elseif ($currentruleset == "User Forced Disabled Rules") {
		// Search and display forced disabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Suricata rules path to each entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . $v;
		}
		$rules_file[] = "{$suricatacfgdir}/rules/" . FLOWBITS_FILENAME;
		$rules_file[] = "{$suricatacfgdir}/rules/custom.rules";
		$rules_map = suricata_get_filtered_rules($rule_files, suricata_load_sid_mods($a_rule['rule_sid_off']));
	}
	elseif ($currentruleset == "User Forced ALERT Action Rules") {
		$rules_map = suricata_get_filtered_rules("{$suricatacfgdir}/rules/", suricata_load_sid_mods($a_rule['rule_sid_force_alert']));
	}
	elseif ($currentruleset == "User Forced DROP Action Rules") {
		$rules_map = suricata_get_filtered_rules("{$suricatacfgdir}/rules/", suricata_load_sid_mods($a_rule['rule_sid_force_drop']));
	}
	elseif ($currentruleset == "User Forced REJECT Action Rules") {
		$rules_map = suricata_get_filtered_rules("{$suricatacfgdir}/rules/", suricata_load_sid_mods($a_rule['rule_sid_force_reject']));
	}
	// If it's not a special case, and we can't find
	// the given rule file, then notify the user.
	elseif (!file_exists($rulefile)) {
		$input_errors[] = gettext("{$currentruleset} seems to be missing!!! Please verify rules files have been downloaded, then go to the Categories tab and save the rule set again.");
	}
	// Not a special case, and we have the matching
	// rule file, so load it up for display.
	else {
		$rules_map = suricata_load_rules_map($rulefile);
	}
}
elseif (isset($_POST['clear'])) {
	unset($a_rule['customrules']);
	config_set_path("installedpackages/suricata/rule/{$id}", $a_rule);
	write_config("Suricata pkg: clear all custom rules for {$a_rule['interface']}.");
	$rebuild_rules = true;
	suricata_generate_yaml($a_rule);
	$rebuild_rules = false;
	$pconfig['customrules'] = '';

	// Sync to configured CARP slaves if any are enabled
	suricata_sync_on_changes();
}
elseif (isset($_POST['cancel'])) {
	$pconfig['customrules'] = base64_decode($a_rule['customrules']);
	clear_subsystem_dirty('suricata_rules');
}
elseif (isset($_POST['save'])) {
	$pconfig['customrules'] = $_POST['customrules'];
	if ($_POST['customrules'])
		$a_rule['customrules'] = base64_encode(str_replace("\r\n", "\n", $_POST['customrules']));
	else
		unset($a_rule['customrules']);
	config_set_path("installedpackages/suricata/rule/{$id}", $a_rule);
	write_config("Suricata pkg: save modified custom rules for {$a_rule['interface']}.");
	$rebuild_rules = true;
	suricata_generate_yaml($a_rule);
	$rebuild_rules = false;
	/* Signal Suricata to "live reload" the rules */
	suricata_reload_config($a_rule);
	clear_subsystem_dirty('suricata_rules');

	// Sync to configured CARP slaves if any are enabled
	suricata_sync_on_changes();
}
elseif ($_POST['filterrules_submit']) {
	// Set flag for filtering rules
	$filterrules = TRUE;
	$filterfieldsarray = array();
	$filterfieldsarray['show_enabled'] = $_POST['filterrules_enabled'] ? $_POST['filterrules_enabled'] : null;
	$filterfieldsarray['show_disabled'] = $_POST['filterrules_disabled'] ? $_POST['filterrules_disabled'] : null;
	if ($a_rule['blockoffenders'] == 'on'){
		$filterfieldsarray['show_drop'] = $_POST['filterrules_drop'] ? $_POST['filterrules_drop'] : null;
	}
	if ($a_rule['ips_mode'] == 'ips_mode_inline' && $a_rule['blockoffenders'] == 'on') {
		$filterfieldsarray['show_reject'] = $_POST['filterrules_reject'] ? $_POST['filterrules_reject'] : null;
	}
}
elseif ($_POST['filterrules_clear']) {
	$filterfieldsarray = array();
	$filterrules = TRUE;
}
elseif (isset($_POST['apply'])) {

	/* Save new configuration */
	config_set_path("installedpackages/suricata/rule/{$id}", $a_rule);
	write_config("Suricata pkg: new rules configuration for {$a_rule['interface']}.");

	/*************************************************/
	/* Update the suricata.yaml file and rebuild the */
	/* rules for this interface.                     */
	/*************************************************/
	$rebuild_rules = true;
	suricata_generate_yaml($a_rule);
	$rebuild_rules = false;

	/* Signal Suricata to "live reload" the rules */
	suricata_reload_config($a_rule);

	// We have saved changes and done a soft restart, so clear "dirty" flag
	clear_subsystem_dirty('suricata_rules');

	// Sync to configured CARP slaves if any are enabled
	suricata_sync_on_changes();
}

function build_cat_list() {
	global $categories, $a_rule, $snortdownload, $emergingdownload, $etpro;

	$list = array();

	$files = $categories;

	if ($a_rule['ips_policy_enable'] == 'on')
		$files[] = "IPS Policy - " . ucfirst($a_rule['ips_policy']);

	if ($a_rule['autoflowbitrules'] == 'on')
		$files[] = "Auto-Flowbit Rules";

	natcasesort($files);

	foreach ($files as $value) {
		if ($snortdownload != 'on' && substr($value, 0, mb_strlen(VRT_FILE_PREFIX)) == VRT_FILE_PREFIX)
			continue;
		if ($emergingdownload != 'on' && substr($value, 0, mb_strlen(ET_OPEN_FILE_PREFIX)) == ET_OPEN_FILE_PREFIX)
			continue;
		if ($etpro != 'on' && substr($value, 0, mb_strlen(ET_PRO_FILE_PREFIX)) == ET_PRO_FILE_PREFIX)
			continue;
		if (empty($value))
			continue;

		$list[$value] = $value;
	}

	return(['custom.rules' => 'custom.rules'] + $list);
}

$if_friendly = convert_friendly_interface_to_friendly_descr($pconfig['interface']);
$pglinks = array("", "/suricata/suricata_interfaces.php", "/suricata/suricata_interfaces_edit.php?id={$id}", "@self");
$pgtitle = array("Services", "Suricata", "Interface Settings", "{$if_friendly} - Rules");
include_once("head.inc");

if (is_subsystem_dirty('suricata_rules')) {
	$_POST['if'] = $id . "|" . $currentruleset;
	print_apply_box(gettext("A change has been made to a rule state or action.") . "<br/>" . gettext("Click APPLY when finished to send the changes to the running configuration."));
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg);
}

?>

<form action="/suricata/suricata_rules.php" method="post" enctype="multipart/form-data" class="form-horizontal" name="iform" id="iform">
<input type='hidden' name='id' id='id' value='<?=$id;?>'/>
<input type='hidden' name='openruleset' id='openruleset' value='<?=$currentruleset;?>'/>
<input type='hidden' name='sid' id='sid' value=''/>
<input type='hidden' name='gid' id='gid' value=''/>

<?php
$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), true, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php?instance={$id}");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php?instance={$id}");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$id}");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");;
$tab_array = array();
$tab_array[] = array($menu_iface . gettext("Settings"), false, "/suricata/suricata_interfaces_edit.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Categories"), false, "/suricata/suricata_rulesets.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Rules"), true, "/suricata/suricata_rules.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Flow/Stream"), false, "/suricata/suricata_flow_stream.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("App Parsers"), false, "/suricata/suricata_app_parsers.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Variables"), false, "/suricata/suricata_define_vars.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/suricata/suricata_ip_reputation.php?id={$id}");
display_top_tabs($tab_array, true);

$section = new Form_Section("Available Rule Categories");
$group = new Form_Group("Category");
$group->add(new Form_Select(
	'selectbox',
	'Category',
	$currentruleset,
	build_cat_list()
))->setHelp("Select the rule category to view and manage.");

// Don't show the VIEW ALL button when displaying Custom Rules,
// Active Rules or any of the "User Forced" special categories.
if ($currentruleset != 'custom.rules' && $currentruleset != 'Active Rules' && strpos($currentruleset, 'User Forced ') === FALSE) {
	$group->add(new Form_Button(
		'',
		'View All',
		'javascript:wopen(\'/suricata/suricata_rules_edit.php?id=' . $id . '&openruleset=' . $currentruleset . '\',\'FileViewer\');',
		'fa-regular fa-file-lines'
	))->removeClass("btn-default")->addClass("btn-sm btn-success")->setAttribute('title', gettext("View raw text for all rules in selected category"));
}
$section->add($group);
print($section);

if ($currentruleset == 'custom.rules') :
		$section = new Form_Section('Defined Custom Rules');
		$section->addInput(new Form_Textarea(
			'customrules',
			'',
			base64_decode($a_rule['customrules'])
		))->addClass('row-fluid')->setRows('18')->setAttribute('wrap', 'off')->setAttribute('style', 'max-width: 100%; width: 100%;');
		print($section);
?>
		<nav class="action-buttons">
			<button type="submit" id="save" name="save" class="btn btn-primary btn-sm" title="<?=gettext('Save custom rules for this interface');?>">
				<i class="fa-solid fa-save icon-embed-btn"></i>
				<?=gettext('Save');?>
			</button>
			<button type="submit" id="cancel" name="cancel" class="btn btn-warning btn-sm" title="<?=gettext('Cancel changes and return to last page');?>">
				<?=gettext('Cancel');?>
			</button>
			<button type="submit" id="clear" name="clear" class="btn btn-danger btn-sm" title="<?=gettext('Deletes all custom rules for this interface');?>">
				<i class="fa-solid fa-trash-can icon-embed-btn"></i>
				<?=gettext('Clear');?>
			</button>
		</nav>

<?php else: ?>

<?php
$section = new Form_Section('Rule Signature ID (SID) Enable/Disable Overrides');
$group = new Form_Group('SID Actions');
$group->add(new Form_Button(
	'apply',
	'Apply',
	null,
	'fa-solid fa-save'
))->setAttribute('title', gettext('Apply changes made on this tab and rebuild the interface rules'))->addClass('btn-primary btn-sm');
$group->add(new Form_Button(
	'resetall',
	'Reset All',
	null,
	'fa-solid fa-arrow-rotate-right'
))->setAttribute('title', gettext('Remove user overrides for all rule categories'))->addClass('btn-sm btn-warning');
$group->add(new Form_Button(
	'resetcategory',
	'Reset Current',
	null,
	'fa-solid fa-arrow-rotate-right'
))->setAttribute('title', gettext('Remove user overrides for only the currently selected category'))->addClass('btn-sm btn-warning');
$group->add(new Form_Button(
	'disable_all',
	'Disable All',
	null,
	'fa-regular fa-circle-xmark'
))->setAttribute('title', gettext('Disable all rules in the currently selected category'))->addClass('btn-sm btn-danger');
$group->add(new Form_Button(
	'enable_all',
	'Enable All',
	null,
	'fa-regular fa-circle-check'
))->setAttribute('title', gettext('Enable all rules in the currently selected category'))->addClass('btn-sm btn-success');
if ($currentruleset == 'Auto-Flowbit Rules') {
	$msg = '<b>' . gettext('Note: ') . '</b>' . gettext('You should not disable flowbit rules!  Add Suppress List entries for them instead by ');
	$msg .= '<a href="/suricata/suricata_rules_flowbits.php?id=' . $id . '" title="' . gettext('Add Suppress List entry for Flowbit Rule') . '">';
	$msg .= gettext('clicking here.') . '</a>';
	$group->setHelp('When finished, click APPLY to save and send any SID state/action changes made on this tab to Suricata.<br/>' . $msg);
}
else {
	$group->setHelp('When finished, click APPLY to save and send any SID state/action changes made on this tab to Suricata.');
}
$section->add($group);
print($section);

// ========== Start Rule filter Panel =========================================
if ($filterrules) {
	$section = new Form_Section("Rules View Filter", "rulesfilter", COLLAPSIBLE|SEC_OPEN);
}
else {
	$section = new Form_Section("Rules View Filter", "rulesfilter", COLLAPSIBLE|SEC_CLOSED);
}
$group = new Form_Group('');
$group->add(new Form_Checkbox(
	'filterrules_enabled',
	'Enabled Rules',
	'Enabled Rules',
	$filterfieldsarray['show_enabled'] == 'on' ? true:false,
	'on'
));
$group->add(new Form_Checkbox(
	'filterrules_disabled',
	'Disabled Rules',
	'Disabled Rules',
	$filterfieldsarray['show_disabled'] == 'on' ? true:false,
	'on'
));

// Show DROP and REJECT filters for Inline IPS Mode operation
if ($a_rule['blockoffenders'] == 'on') {
	$group->add(new Form_Checkbox(
		'filterrules_drop',
		'Drop Rules',
		'Drop Rules',
		$filterfieldsarray['show_drop'] == 'on' ? true:false,
		'on'
	));
}
if ($a_rule['ips_mode'] == 'ips_mode_inline' && $a_rule['blockoffenders'] == 'on') {
	$group->add(new Form_Checkbox(
		'filterrules_reject',
		'Reject Rules',
		'Reject Rules',
		$filterfieldsarray['show_reject'] == 'on' ? true:false,
		'on'
	));
}
$section->add($group);

// Add APPLY and CLEAR buttons
$group = new Form_Group('');
$group->add(new Form_Button(
	'filterrules_submit',
	'Apply Filter',
	null,
	'fa-solid fa-filter'
))->removeClass("btn-primary")
  ->addClass("btn-sm btn-success");
$group->add(new Form_Button(
	'filterrules_clear',
	'Clear Filter',
	null,
	'fa-regular fa-trash-can'
))->removeclass("btn-primary")
  ->addClass("btn-sm btn-danger no-confirm");
$section->add($group);
print($section);
// ========== End Rule filter Panel ===========================================

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Rule Signature ID (SID) Enable/Disable Overrides")?></h2></div>
	<div class="panel-body table-responsive">

		<div class="content table-responsive">
			<table>
				<tbody>
					<tr>
						<td><b><?=gettext('Legend: ');?></b></td>
						<td style="padding-left: 8px;"><i class="fa-regular fa-circle-check text-success"></i></td><td style="padding-left: 4px;"><small><?=gettext('Default Enabled');?></small></td>
						<td style="padding-left: 8px;"><i class="fa-solid fa-check-circle text-success"></i></td><td style="padding-left: 4px;"><small><?=gettext('Enabled by user');?></small></td>
						<td style="padding-left: 8px;"><i class="fa-brands fa-adn text-success"></i></td><td style="padding-left: 4px;"><small><?=gettext('Auto-enabled by SID Mgmt');?></small></td>
						<td style="padding-left: 8px;"><i class="fa-brands fa-adn text-warning"></i></td><td style="padding-left: 4px;"><small><?=gettext('Action/content modified by SID Mgmt');?></small></td>
						<td style="padding-left: 8px;"><i class="fa-solid fa-exclamation-triangle text-warning"></i></td><td style="padding-left: 4px;"><small><?=gettext('Rule action is alert');?></small></td>
						<td style="padding-left: 8px;"><i class="fa-solid fa-exclamation-triangle text-success"></i></td><td style="padding-left: 4px;"><small><?=gettext('Rule contains noalert option');?></small></td>
					</tr>
					<tr>
						<td></td>
						<td style="padding-left: 8px;"><i class="fa-regular fa-circle-xmark text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Default Disabled');?></small></td>
						<td style="padding-left: 8px;"><i class="fa-solid fa-times-circle text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Disabled by user');?></small></td>
						<td style="padding-left: 8px;"><i class="fa-brands fa-adn text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Auto-disabled by SID Mgmt');?></small></td>
						<td></td><td></td>
				<?php if ($a_rule['blockoffenders'] == 'on') : ?>
						<td style="padding-left: 8px;"><i class="fa-solid fa-thumbs-down text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Rule action is drop');?></small></td>
					<?php if ($a_rule['ips_mode'] == 'ips_mode_inline') : ?>
						<td style="padding-left: 8px;"><i class="fa-regular fa-hand text-warning"></i></td><td style="padding-left: 4px;"><small><?=gettext('Rule action is reject');?></small></td>
					<?php else : ?>
						<td></td><td></td>
					<?php endif; ?>
				<?php else : ?>
						<td></td><td></td>
				<?php endif; ?>
						<td></td><td></td>
					</tr>
				</tbody>
			</table>
		</div>

		<table style="table-layout: fixed; width: 100%;" class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<colgroup>
				<col width="5%">
				<col width="5%">
				<col width="4%">
				<col width="9%">
				<col width="5%">
				<col width="15%">
				<col width="12%">
				<col width="15%">
				<col width="12%">
				<col>
			</colgroup>
			<thead>
			   <tr class="sortableHeaderRowIdentifier">
				<th data-sortable="false"><?=gettext("State");?></th>
				<th data-sortable="false"><?=gettext("Action");?></th>
				<th data-sortable="true" data-sortable-type="numeric"><?=gettext("GID");?></th>
				<th data-sortable="true" data-sortable-type="numeric"><?=gettext("SID");?></th>
				<th data-sortable="true" data-sortable-type="alpha"><?=gettext("Proto");?></th>
				<th data-sortable="true" data-sortable-type="alpha"><?=gettext("Source");?></th>
				<th data-sortable="true" data-sortable-type="alpha"><?=gettext("SPort");?></th>
				<th data-sortable="true" data-sortable-type="alpha"><?=gettext("Destination");?></th>
				<th data-sortable="true" data-sortable-type="alpha"><?=gettext("DPort");?></th>
				<th data-sortable="true" data-sortable-type="alpha"><?=gettext("Message");?></th>
			   </tr>
			</thead>
			<tbody>
				<?php
					$counter = $enable_cnt = $disable_cnt = $user_enable_cnt = $user_disable_cnt = $managed_count = 0;
					if (is_array($rules_map) && !empty($rules_map)) {
						foreach ($rules_map as $k1 => $rulem) {
							if (!is_array($rulem)) {
								$rulem = array();
							}
							foreach ($rulem as $k2 => $v) {
								$sid = $k2;
								$gid = $k1;
								$ruleset = $currentruleset;
								$style = "";

								// Apply rule state and action filters if filtering is enabled
								if ($filterrules) {
									if (isset($filterfieldsarray['show_disabled'])) {
										if (($v['disabled'] == 0 || isset($enablesid[$gid][$sid])) && !isset($disablesid[$gid][$sid])) {
											continue;
										}
									}
									if (isset($filterfieldsarray['show_enabled'])) {
										if ($v['disabled'] == 1 || isset($disablesid[$gid][$sid])) {
											continue;
										}
									}
									if (isset($filterfieldsarray['show_drop']) && $v['action'] != "drop") {
										continue;
									}
									if (isset($filterfieldsarray['show_reject']) && $v['action'] != "reject") {
										continue;
									}
								}

								// Determine which icons to display in the first column for rule state.
								// See if the rule is auto-managed by the SID MGMT tab feature
								if ($v['managed'] == 1) {
									if ($v['disabled'] == 1 && $v['state_toggled'] == 1) {
										$textss = '<span class="text-muted">';
										$textse = '</span>';
										$iconb_class = 'class="fa-brands fa-adn text-danger text-left"';
										$title = gettext("Auto-disabled by settings on SID Mgmt tab");
									}
									elseif ($v['disabled'] == 0 && $v['state_toggled'] == 1) {
										$textss = $textse = "";
										$iconb_class = 'class="fa-brands fa-adn text-success text-left"';
										$title = gettext("Auto-enabled by settings on SID Mgmt tab");
									}
									$managed_count++;
								}
								// See if the rule is in our list of user-disabled overrides
								if (isset($disablesid[$gid][$sid])) {
									$textss = "<span class=\"text-muted\">";
									$textse = "</span>";
									$disable_cnt++;
									$user_disable_cnt++;
									$iconb_class = 'class="fa-solid fa-times-circle text-danger text-left"';
									$title = gettext("Disabled by user. Click to change rule state");
								}
								// See if the rule is in our list of user-enabled overrides
								elseif (isset($enablesid[$gid][$sid])) {
									$textss = $textse = "";
									$enable_cnt++;
									$user_enable_cnt++;
									$iconb_class = 'class="fa-solid fa-check-circle text-success text-left"';
									$title = gettext("Enabled by user. Click to change rules state");
								}

								// These last two checks handle normal cases of default-enabled or default disabled rules
								// with no user overrides.
								elseif (($v['disabled'] == 1) && ($v['state_toggled'] == 0) && (!isset($enablesid[$gid][$sid]))) {
									$textss = "<span class=\"text-muted\">";
									$textse = "</span>";
									$disable_cnt++;
									$iconb_class = 'class="fa-regular fa-circle-xmark text-danger text-left"';
									$title = gettext("Disabled by default. Click to change rule state");
								}
								elseif ($v['disabled'] == 0 && $v['state_toggled'] == 0) {
									$textss = $textse = "";
									$enable_cnt++;
									$iconb_class = 'class="fa-regular fa-circle-check text-success text-left"';
									$title = gettext("Enabled by default.");
								}

								// Determine which icon to display in the second column for rule action.
								// Default to ALERT icon.
								$textss = $textse = "";
								$iconact_class = 'class="fa-solid fa-exclamation-triangle text-warning text-center"';
								$title_act = gettext("Rule will alert on traffic when triggered.");
								if ($v['action'] == 'drop' && $a_rule['blockoffenders'] == 'on') {
									$iconact_class = 'class="fa-solid fa-thumbs-down text-danger text-center"';
									$title_act = gettext("Rule will drop traffic when triggered.");
								}
								elseif ($v['action'] == 'reject' && $a_rule['ips_mode'] == 'ips_mode_inline' && $a_rule['blockoffenders'] == 'on') {
									$iconact_class = 'class="fa-regular fa-hand text-warning text-center"';
									$title_act = gettext("Rule will reject traffic when triggered.");
								}
								if ($a_rule['blockoffenders'] == 'on') {
									$title_act .= gettext("  Click to change rule action.");
								}

								// Rules with "noalert;" option enabled get special treatment
								if ($v['noalert'] == 1) {
									$iconact_class = 'class="fa-solid fa-exclamation-triangle text-success text-center"';
									$title_act = gettext("Rule contains the 'noalert;' and/or 'flowbits:noalert;' options.");
								}

								// Pick off the first section of the rule (prior to the start of the MSG field),
								// and then use a REGX split to isolate the remaining fields into an array.
								$tmp = substr($v['rule'], 0, strpos($v['rule'], "("));
								$tmp = trim(preg_replace('/^\s*#+\s*/', '', $tmp));
								$rule_content = preg_split('/[\s]+/', $tmp);

								// Create custom <span> tags for the fields we truncate so we can 
								// have a "title" attribute for tooltips to show the full string.
								$srcspan = add_title_attribute($textss, $rule_content[2]);
								$srcprtspan = add_title_attribute($textss, $rule_content[3]);
								$dstspan = add_title_attribute($textss, $rule_content[5]);
								$dstprtspan = add_title_attribute($textss, $rule_content[6]);

								$protocol = $rule_content[1];         //protocol field
								$source = $rule_content[2];           //source field
								$source_port = $rule_content[3];      //source port field
								$destination = $rule_content[5];      //destination field
								$destination_port = $rule_content[6]; //destination port field
								$message = suricata_get_msg($v['rule']); // description field
								$sid_tooltip = gettext("View the raw text for this rule");

								// Show text of "noalert;" flagged rules in Bootstrap SUCCESS color
								if ($v['noalert'] == 1) {
									$tag_class = ' class="text-success" ';
								} else {
									$tag_class = "";
								}
					?>
								<tr class="text-nowrap">
									<td><?=$textss; ?>
										<a id="rule_<?=$gid; ?>_<?=$sid; ?>" href="#" onClick="toggleState('<?=$sid; ?>', '<?=$gid; ?>');" 
										<?=$iconb_class; ?> title="<?=$title; ?>"></a><?=$textse; ?>
						<?php if ($v['managed'] == 1 && $v['modified'] == 1) : ?>
										<i class="fa-brands fa-adn text-warning text-left" title="<?=gettext('Action or content modified by settings on SID Mgmt tab'); ?>"></i><?=$textse; ?>
						<?php endif; ?>
									</td>

						<?php if ($a_rule['blockoffenders'] == 'on' && $v['noalert'] == 0) : ?>
								       <td><?=$textss; ?><a id="rule_<?=$gid; ?>_<?=$sid; ?>_action" href="#" onClick="toggleAction('<?=$sid; ?>', '<?=$gid; ?>');" 
										<?=$iconact_class; ?> title="<?=$title_act; ?>"></a><?=$textse; ?>
								       </td>
						<?php else : ?>
								       <td><?=$textss; ?><i <?=$iconact_class; ?> title="<?=$title_act; ?>"></i><?=$textse; ?>
								       </td>
						<?php endif; ?>

								       <td ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
										<?=$textss . $gid . $textse;?>
								       </td>
								       <td ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
										<a href="javascript: void(0)" 
										onclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');" 
										title="<?=$sid_tooltip;?>"><?=$textss . $sid . $textse;?></a>
								       </td>
								       <td <?=$tag_class;?> ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
										<?=$textss . $protocol . $textse;?>
							       	       </td>
								       <td <?=$tag_class;?> style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
										<?=$srcspan . $source;?></span>
								       </td>
								       <td <?=$tag_class;?> style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
										<?=$srcprtspan . $source_port;?></span>
								       </td>
								       <td <?=$tag_class;?> style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
										<?=$dstspan . $destination;?></span>
								       </td>
								       <td <?=$tag_class;?> style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
									       <?=$dstprtspan . $destination_port;?></span>
								       </td>
									<td <?=$tag_class;?> style="word-wrap:break-word; white-space:normal" ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
										<?=$textss . $message . $textse;?>
								       </td>
								</tr>
				<?php
								$counter++;
							}
						}
						unset($rulem, $v);
					}
				?>
		    </tbody>
		</table>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Category Rules Summary")?></h2></div>
	<div class="panel-body">
		<div class="text-info content">
			<b><?=gettext("Total Rules: ");?></b><?=gettext($counter);?>&nbsp;&nbsp;&nbsp;&nbsp; 
			<b><?=gettext("Default Enabled: ");?></b><?=gettext($enable_cnt);?>&nbsp;&nbsp;&nbsp;&nbsp;
			<b><?=gettext("Default Disabled: ");?></b><?=gettext($disable_cnt);?>&nbsp;&nbsp;&nbsp;&nbsp;
			<b><?=gettext("User Enabled: ");?></b><?=gettext($user_enable_cnt);?>&nbsp;&nbsp;&nbsp;&nbsp;
			<b><?=gettext("User Disabled: ");?></b><?=gettext($user_disable_cnt);?>&nbsp;&nbsp;&nbsp;&nbsp;
			<b><?=gettext("Auto-Managed: ");?></b><?=gettext($managed_count);?>
		</div>
	</div>
</div>
<?php endif;?>

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
					<input type="radio" name="ruleActionOptions" id="action_default" value="action_default"> <span class = "label label-default">Default</span>
				</label>
				<label class="radio-inline">
					<input type="radio" name="ruleActionOptions" id="action_alert" value="action_alert"> <span class = "label label-warning">ALERT</span>
				</label>
				<label class="radio-inline">
					<input type="radio" name="ruleActionOptions" id="action_drop" value="action_drop"> <span class = "label label-danger">DROP</span>
				</label>

		<?php if ($a_rule['ips_mode'] == 'ips_mode_inline' && $a_rule['blockoffenders'] == 'on') : ?>
				<label class="radio-inline">
					<input type="radio" name="ruleActionOptions" id="action_reject" value="action_reject"> <span class = "label label-warning">REJECT</span>
				</label>
		<?php endif; ?>
				<br /><br />
					<p><?=gettext("Choosing 'Default' will return the rule action to the original value specified by the rule author.  Note this is usually ALERT.");?></p>
			</div>
			<div class="modal-footer">
				<button type="submit" class="btn btn-sm btn-primary" id="rule_action_save" name="rule_action_save" value="<?=gettext("Save");?>" title="<?=gettext("Save changes and close selector");?>">
					<i class="fa-solid fa-save icon-embed-btn"></i>
					<?=gettext("Save");?>
				</button>
				<button type="button" class="btn btn-sm btn-warning" id="cancel_sid_action" name="cancel_sid_action" value="<?=gettext("Cancel");?>" data-dismiss="modal" title="<?=gettext("Abandon changes and quit selector");?>">
					<?=gettext("Cancel");?>
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Modal Rule SID state selector window -->
<div class="modal fade" role="dialog" id="sid_state_selector">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
				<h3 class="modal-title"><?=gettext("Rule State Selection")?></h3>
			</div>
			<div class="modal-body">
				<h4><?=gettext("Choose desired rule state from selections below: ");?></h4>
				<label class="radio-inline">
					<input type="radio" name="ruleStateOptions" id="state_default" value="state_default"> <span class = "label label-default">Default</span>
				</label>
				<label class="radio-inline">
					<input type="radio" name="ruleStateOptions" id="state_enabled" value="state_enabled"> <span class = "label label-success">Enabled</span>
				</label>
				<label class="radio-inline">
					<input type="radio" name="ruleStateOptions" id="state_disabled" value="state_disabled"> <span class = "label label-danger">Disabled</span>
				</label>
				<br /><br />
					<p><?=gettext("Choosing 'Default' will return the rule state to the original state specified by the rule package author.");?></p>
			</div>
			<div class="modal-footer">
				<button type="submit" class="btn btn-sm btn-primary" id="rule_state_save" name="rule_state_save" value="<?=gettext("Save");?>" title="<?=gettext("Save changes and close selector");?>">
					<i class="fa-solid fa-save icon-embed-btn"></i>
					<?=gettext("Save");?>
				</button>
				<button type="button" class="btn btn-sm btn-warning" id="cancel_state_action" name="cancelcancel_state_action" value="<?=gettext("Cancel");?>" data-dismiss="modal" title="<?=gettext("Abandon changes and quit selector");?>">
					<?=gettext("Cancel");?>
				</button>
			</div>
		</div>
	</div>
</div>

</form>

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

<script language="javascript" type="text/javascript">
//<![CDATA[

function toggleState(sid, gid) {
	$('#sid').val(sid);
	$('#gid').val(gid);
	$('#openruleset').val($('#selectbox').val());
	$('#sid_state_selector').modal('show');
}

function toggleAction(sid, gid) {
	if ($('#rule_'+gid+'_'+sid).hasClass('text-success')) {
		$('#sid').val(sid);
		$('#gid').val(gid);
		$('#openruleset').val($('#selectbox').val());
		$('#sid_action_selector').modal('show');
	}
	else {
		alert("Rule is disabled, so changing ACTION is meaningless and thus is disabled for this rule.");
	}
}

function wopen(url, name)
{
    var win = window.open(url,
        name,
       'location=no, menubar=no, ' +
       'status=no, toolbar=no, scrollbars=yes, resizable=yes');
    win.focus();
}

function showRuleContents(gid, sid) {
		// Show the modal dialog with rule text
		$('#rulesviewer_text').text("...Loading...");
		$('#rulesviewer').modal('show');
		$('#modal_rule_category').html($('#selectbox').val());

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
		$('#rulesviewer_text').text(atob(response.rule_text));
		$('#rulesviewer_text').attr('readonly', true);
		if (response.rule_link) {
			$('#modal_rule_link_text').text('Snort Rule Doc: ');
			$('#modal_rule_link').attr('href', response.rule_link);
			$('#modal_rule_link').text(response.rule_link);
		}
}

events.push(function() {

	function go()
	{
		var ruleset = $('#selectbox').find('option:selected').val();
		if (ruleset) {
			$('#openruleset').val(ruleset);
			$('#iform').submit();
		}
	}

	// ---------- Click handlers -------------------------------------------------------

	$('#selectbox').on('change', function() {
		go();
	});

	$('#filterrules_enabled').click(function() {
		$('#filterrules_disabled').prop("checked", false);
	});

	$('#filterrules_disabled').click(function() {
		$('#filterrules_enabled').prop("checked", false);
	});

	$('#filterrules_drop').click(function() {
		$('#filterrules_reject').prop("checked", false);
	});

	$('#filterrules_reject').click(function() {
		$('#filterrules_drop').prop("checked", false);
	});

	<?php if (!empty($anchor)): ?>
		// Scroll the last enabled/disabled SID into view
		window.location.hash = "<?=$anchor; ?>";
		window.scrollBy(0,-60); 
	<?php endif;?>

});
//]]>
</script>

<?php include("foot.inc"); ?>

