<?php
/*
 * snort_rules.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008-2009 Robert Zelaya
 * Copyright (c) 2022 Bill Meeks
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

global $g, $rebuild_rules;

$snortdir = SNORTDIR;
$snortbindir = SNORT_BINDIR;
$rules_map = array();
$categories = array();
$pconfig = array();

$a_rule = config_get_path('installedpackages/snortglobal/rule', []);

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

// If postback is from system function print_apply_box(),
// then we won't have our customary $_POST['id'] and
// $_POST['openruleset'] fields set in the response,
// but the system function will pass back a
// $_POST['if'] field we can use instead.
if (is_null($id)) {
	if (isset($_POST['if'])) {
		// Split the posted string at the '|' delimiter
		$response = explode('|', $_POST['if']);
		$id = $response[0];
		$_POST['openruleset'] = $response[1];
	}
	else {
		header("Location: /snort/snort_interfaces.php");
		exit;
	}
}

if (isset($id) && isset($a_rule[$id])) {
	$pconfig['interface'] = $a_rule[$id]['interface'];
	$pconfig['rulesets'] = $a_rule[$id]['rulesets'];
	$pconfig['sensitive_data'] = $a_rule[$id]['sensitive_data'] == 'on' ? 'on' : 'off';
}

// Convert named interfaces to real
$if_real = get_real_interface($pconfig['interface']);
$snort_uuid = $a_rule[$id]['uuid'];
$snortcfgdir = "{$snortdir}/snort_{$snort_uuid}_{$if_real}";
$snortdownload = config_get_path('installedpackages/snortglobal/snortdownload');
$snortcommunitydownload = config_get_path('installedpackages/snortglobal/snortcommunityrules') == 'on' ? 'on' : 'off';
$emergingdownload = config_get_path('installedpackages/snortglobal/emergingthreats');
$etprodownload = config_get_path('installedpackages/snortglobal/emergingthreats_pro');
$appidownload = config_get_path('installedpackages/snortglobal/openappid_rules_detectors');

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


// Add any previously saved rules files to the categories array
if (!empty($pconfig['rulesets']))
	$categories = explode("||", $pconfig['rulesets']);

// add the standard rules files to the categories array
$categories[] = "custom.rules";
$categories[] = "decoder.rules";
$categories[] = "preprocessor.rules";

// Add Sensitive-Data rules only if corresponding preprocessor is enabled
if ($pconfig['sensitive_data'] == 'on') {
	$categories[] = "sensitive-data.rules";
}

// Get any automatic rule category enable/disable modifications
// if auto-SID Mgmt is enabled, and adjust the available rulesets
// in the CATEGORY drop-down box as necessary.
$cat_mods = snort_sid_mgmt_auto_categories($a_rule[$id], FALSE);
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

// Add any enabled IPS-Policy and Auto-Flowbits File
if (!empty($a_rule[$id]['ips_policy']))
	$categories[] = "IPS Policy - " . ucfirst($a_rule[$id]['ips_policy']);
if ($a_rule[$id]['autoflowbitrules'] == 'on')
	$categories[] = "Auto-Flowbit Rules";
natcasesort($categories);

// Only add custom ALERT, DROP or REJECT Action Rules
// option if blocking is enabled.
if ($a_rule[$id]['blockoffenders7'] == 'on') {
	$categories[] = "User Forced ALERT Action Rules";

	// Show custom DROP  and REJECT rules only if using Inline IPS mode.
	if ($a_rule[$id]['ips_mode'] == 'ips_mode_inline') {
		$categories[] = "User Forced DROP Action Rules";
		$categories[] = "User Forced REJECT Action Rules";
	}
}

// Add custom Category to view all Active Rules
// on the interface at the bottom of the list.
$categories[] = "Active Rules";

if (isset($_POST['openruleset']))
	$currentruleset = $_POST['openruleset'];
elseif (isset($_GET['openruleset']))
	$currentruleset = htmlspecialchars($_GET['openruleset']);
else
	$currentruleset = $categories[array_key_first($categories)];

$currentruleset = basename($currentruleset);

// One last sanity check -- if the rules directory is empty, default to loading custom rules
$tmp = glob("{$snortdir}/rules/*.rules");
if (empty($tmp))
	$currentruleset = "custom.rules";

$ruledir = "{$snortdir}/rules";
$rulefile = "{$snortdir}/rules/{$currentruleset}";
if ($currentruleset != 'custom.rules') {
	// Read the currently selected rules file into our rules map array.
	// There are a few special cases possible, so test and adjust as
	// necessary to get the correct set of rules to display.

	// If it is the auto-flowbits file, set the full path.
	if ($currentruleset == "Auto-Flowbit Rules")
		$rules_map = snort_load_rules_map("{$snortcfgdir}/rules/" . FLOWBITS_FILENAME);

	// Test for the special case of an IPS Policy file.
	elseif (substr($currentruleset, 0, 10) == "IPS Policy")
		$rules_map = snort_load_vrt_policy($a_rule[$id]['ips_policy'], $a_rule[$id]['ips_policy_mode']);

	// Test for preproc_rules file and set the full path.
	elseif (file_exists("{$snortdir}/preproc_rules/{$currentruleset}"))
		$rules_map = snort_load_rules_map("{$snortdir}/preproc_rules/{$currentruleset}");

	// Test for the special case of "Active Rules".  This
	// displays all currently active rules for the
	// interface.
	elseif ($currentruleset == "Active Rules") {
		$rules_map = snort_load_rules_map("{$snortcfgdir}/rules/");
	}

	// Test for the special cases of "User Forced" rules
	// and load the required rules for display.
	elseif ($currentruleset == "User Forced Enabled Rules") {
		// Search and display forced enabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Snort rules path to each enabled category entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . "/" . $v;
		}

		// Include the preprocessor, decoder and sensitive data rules
		$rule_files[] = "{$snortcfgdir}/preproc_rules/decoder.rules";
		$rule_files[] = "{$snortcfgdir}/preproc_rules/preprocessor.rules";
		$rule_files[] = "{$snortcfgdir}/preproc_rules/sensitive-data.rules";

		// Finally, include any custom rules and auto-flowbits rules
		$rule_files[] = "{$snortcfgdir}/rules/" . FLOWBITS_FILENAME;
		$rule_files[] = "{$snortcfgdir}/rules/custom.rules";

		// Now filter the array of rules against the list of user-forced
		// enabled GID:SID pairs.
		$rules_map = snort_get_filtered_rules($rule_files, snort_load_sid_mods($a_rule[$id]['rule_sid_on']));
	}
	elseif ($currentruleset == "User Forced Disabled Rules") {
		// Search and display forced disabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Snort rules path to each enabled category entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . "/" . $v;
		}

		// Include the preprocessor, decoder and sensitive data rules
		$rule_files[] = "{$snortcfgdir}/preproc_rules/decoder.rules";
		$rule_files[] = "{$snortcfgdir}/preproc_rules/preprocessor.rules";
		$rule_files[] = "{$snortcfgdir}/preproc_rules/sensitive-data.rules";

		// Finally, include any custom rules and auto-flowbits rules
		$rule_files[] = "{$snortcfgdir}/rules/" . FLOWBITS_FILENAME;
		$rule_files[] = "{$snortcfgdir}/rules/custom.rules";

		// Now filter the array of rules against the list of user-forced
		// diabled GID:SID pairs.
		$rules_map = snort_get_filtered_rules($rule_files, snort_load_sid_mods($a_rule[$id]['rule_sid_off']));
	}
	elseif ($currentruleset == "User Forced ALERT Action Rules") {
		$rules_map = snort_get_filtered_rules("{$snortcfgdir}/rules/", snort_load_sid_mods($a_rule[$id]['rule_sid_force_alert']));
	}
	elseif ($currentruleset == "User Forced DROP Action Rules") {
		$rules_map = snort_get_filtered_rules("{$snortcfgdir}/rules/", snort_load_sid_mods($a_rule[$id]['rule_sid_force_drop']));
	}
	elseif ($currentruleset == "User Forced REJECT Action Rules") {
		$rules_map = snort_get_filtered_rules("{$snortcfgdir}/rules/", snort_load_sid_mods($a_rule[$id]['rule_sid_force_reject']));
	}
	// If it's not a special case, and we can't find
	// the given rule file, then notify the user.
	elseif (!file_exists($rulefile)) {
		$input_errors[] = gettext("{$currentruleset} seems to be missing!!! Please verify rules files have been downloaded, then go to the Categories tab and save the rule set again.");
	}
	// Not a special case, and we have the matching
	// rule file, so load it up for display.
	else {
		$rules_map = snort_load_rules_map($rulefile);
	}
}

// Process the current category rules through any auto SID MGMT changes if enabled
snort_auto_sid_mgmt($rules_map, $a_rule[$id], FALSE);

// Load up our enablesid and disablesid arrays with enabled or disabled SIDs
$enablesid = snort_load_sid_mods($a_rule[$id]['rule_sid_on']);
$disablesid = snort_load_sid_mods($a_rule[$id]['rule_sid_off']);

/* Load up our rule action arrays with manually changed SID actions */
$alertsid = snort_load_sid_mods($a_rule[$id]['rule_sid_force_alert']);
$dropsid = snort_load_sid_mods($a_rule[$id]['rule_sid_force_drop']);
$rejectsid = snort_load_sid_mods($a_rule[$id]['rule_sid_force_reject']);
snort_modify_sids_action($rules_map, $a_rule[$id]);

/* Process AJAX request to view content of a specific rule */
if ($_POST['action'] == 'loadRule') {
	if (isset($_POST['gid']) && isset($_POST['sid'])) {
		$gid = $_POST['gid'];
		$sid = $_POST['sid'];
		print(base64_encode($rules_map[$gid][$sid]['rule']));
	}
	else {
		print(base64_encode(gettext('Invalid rule signature - no matching rule was found!')));
	}
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
			if (isset($enablesid[$gid][$sid])) {
				unset($enablesid[$gid][$sid]);
			}
			if (isset($disablesid[$gid][$sid])) {
				unset($disablesid[$gid][$sid]);
			}
			// Restore the default state flag so we
			// can display state properly on RULES
			// page without needing to reload the
			// entire set of rules.
			if (isset($rules_map[$gid][$sid])) {
				$rules_map[$gid][$sid]['disabled'] = !$rules_map[$gid][$sid]['default_state'];
			}
			break;

		case "state_enabled":
			if (isset($disablesid[$gid][$sid])) {
				unset($disablesid[$gid][$sid]);
			}
			$enablesid[$gid][$sid] = "enablesid";
			break;

		case "state_disabled":
			if (isset($enablesid[$gid][$sid])) {
				unset($enablesid[$gid][$sid]);
			}
			$disablesid[$gid][$sid] = "disablesid";
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
		$a_rule[$id]['rule_sid_on'] = $tmp;
	else
		unset($a_rule[$id]['rule_sid_on']);

	$tmp = "";
	foreach (array_keys($disablesid) as $k1) {
		foreach (array_keys($disablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule[$id]['rule_sid_off'] = $tmp;
	else
		unset($a_rule[$id]['rule_sid_off']);

	/* Update the config.xml file. */
	config_set_path('installedpackages/snortglobal/rule', $a_rule);
	write_config("Snort pkg: modified state for rule {$gid}:{$sid} on {$a_rule[$id]['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('snort_rules_state');

	// Update our in-memory rules map with the changes just saved
	// to the Snort configuration file.
	snort_modify_sids($rules_map, $a_rule[$id]);

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
			if (isset($alertsid[$gid][$sid])) {
				unset($alertsid[$gid][$sid]);
			}
			if (isset($dropsid[$gid][$sid])) {
				unset($dropsid[$gid][$sid]);
			}
			if (isset($rejectsid[$gid][$sid])) {
				unset($rejectsid[$gid][$sid]);
			}
			break;

		case "action_alert":
			$rules_map[$gid][$sid]['action'] = $rules_map[$gid][$sid]['alert'];
			if (!is_array($alertsid[$gid])) {
				$alertsid[$gid] = array();
			}
			if (!is_array($alertsid[$gid][$sid])) {
				$alertsid[$gid][$sid] = array();
			}
			$alertsid[$gid][$sid] = "alertsid";
			if (isset($dropsid[$gid][$sid])) {
				unset($dropsid[$gid][$sid]);
			}
			if (isset($rejectsid[$gid][$sid])) {
				unset($rejectsid[$gid][$sid]);
			}
			break;

		case "action_drop":
			$rules_map[$gid][$sid]['action'] = $rules_map[$gid][$sid]['drop'];
			if (!is_array($dropsid[$gid])) {
				$dropsid[$gid] = array();
			}
			if (!is_array($dropsid[$gid][$sid])) {
				$dropsid[$gid][$sid] = array();
			}
			$dropsid[$gid][$sid] = "dropsid";
			if (isset($alertsid[$gid][$sid])) {
				unset($alertsid[$gid][$sid]);
			}
			if (isset($rejectsid[$gid][$sid])) {
				unset($rejectsid[$gid][$sid]);
			}
			break;

		case "action_reject":
			$rules_map[$gid][$sid]['action'] = $rules_map[$gid][$sid]['reject'];
			if (!is_array($rejectsid[$gid])) {
				$rejectsid[$gid] = array();
			}
			if (!is_array($rejectsid[$gid][$sid])) {
				$rejectsid[$gid][$sid] = array();
			}
			$rejectsid[$gid][$sid] = "rejectsid";
			if (isset($alertsid[$gid][$sid])) {
				unset($alertsid[$gid][$sid]);
			}
			if (isset($dropsid[$gid][$sid])) {
				unset($dropsid[$gid][$sid]);
			}
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
			$a_rule[$id]['rule_sid_force_alert'] = $tmp;
		else
			unset($a_rule[$id]['rule_sid_force_alert']);

		$tmp = "";
		foreach (array_keys($dropsid) as $k1) {
			foreach (array_keys($dropsid[$k1]) as $k2)
				$tmp .= "{$k1}:{$k2}||";
		}
		$tmp = rtrim($tmp, "||");

		if (!empty($tmp))
			$a_rule[$id]['rule_sid_force_drop'] = $tmp;
		else
			unset($a_rule[$id]['rule_sid_force_drop']);

		$tmp = "";
		foreach (array_keys($rejectsid) as $k1) {
			foreach (array_keys($rejectsid[$k1]) as $k2)
				$tmp .= "{$k1}:{$k2}||";
		}
		$tmp = rtrim($tmp, "||");

		if (!empty($tmp))
			$a_rule[$id]['rule_sid_force_reject'] = $tmp;
		else
			unset($a_rule[$id]['rule_sid_force_reject']);

		/* Update the config.xml file. */
		config_set_path('installedpackages/snortglobal/rule', $a_rule);
		write_config("Snort pkg: modified action for rule {$gid}:{$sid} on {$a_rule[$id]['interface']}.");

		// We changed a rule action, remind user to apply the changes
		mark_subsystem_dirty('snort_rules_action');

		// Update our in-memory rules map with the changes just saved
		// to the Snort configuration file.
		snort_modify_sids_action($rules_map, $a_rule[$id]);

		// Set a scroll-to anchor location
		$anchor = "rule_{$gid}_{$sid}";
	}
}
elseif ($_POST['disable_all'] && !empty($rules_map)) {

	// Mark all rules in the currently selected category "disabled".
	foreach (array_keys($rules_map) as $k1) {
		foreach (array_keys($rules_map[$k1]) as $k2) {
			if (isset($enablesid[$k1][$k2]))
				unset($enablesid[$k1][$k2]);
			$disablesid[$k1][$k2] = "disablesid";
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
		$a_rule[$id]['rule_sid_on'] = $tmp;
	else				
		unset($a_rule[$id]['rule_sid_on']);

	$tmp = "";
	foreach (array_keys($disablesid) as $k1) {
		foreach (array_keys($disablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule[$id]['rule_sid_off'] = $tmp;
	else				
		unset($a_rule[$id]['rule_sid_off']);

	// Write updated configuration
	config_set_path('installedpackages/snortglobal/rule', $a_rule);
	write_config("Snort pkg: disabled all rules in category {$currentruleset} for {$a_rule[$id]['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('snort_rules_state');

	// Update our in-memory rules map with the changes just saved
	// to the Snort configuration file.
	snort_modify_sids($rules_map, $a_rule[$id]);
}
elseif ($_POST['enable_all'] && !empty($rules_map)) {

	// Mark all rules in the currently selected category "enabled".
	foreach (array_keys($rules_map) as $k1) {
		foreach (array_keys($rules_map[$k1]) as $k2) {
			if (isset($disablesid[$k1][$k2]))
				unset($disablesid[$k1][$k2]);
			$enablesid[$k1][$k2] = "enablesid";
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
		$a_rule[$id]['rule_sid_on'] = $tmp;
	else				
		unset($a_rule[$id]['rule_sid_on']);

	$tmp = "";
	foreach (array_keys($disablesid) as $k1) {
		foreach (array_keys($disablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule[$id]['rule_sid_off'] = $tmp;
	else				
		unset($a_rule[$id]['rule_sid_off']);

	//Write updated configuration
	config_set_path('installedpackages/snortglobal/rule', $a_rule);
	write_config("Snort pkg: enable all rules in category {$currentruleset} for {$a_rule[$id]['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('snort_rules_state');

	// Update our in-memory rules map with the changes just saved
	// to the Snort configuration file.
	snort_modify_sids($rules_map, $a_rule[$id]);
}
elseif ($_POST['resetcategory'] && !empty($rules_map)) {

	// Reset any modified SIDs in the current rule category to their defaults.
	foreach (array_keys($rules_map) as $k1) {
		foreach (array_keys($rules_map[$k1]) as $k2) {
			if (isset($enablesid[$k1][$k2]))
				unset($enablesid[$k1][$k2]);
			if (isset($disablesid[$k1][$k2]))
				unset($disablesid[$k1][$k2]);
			if (isset($alertsid[$k1][$k2]))
				unset($alertsid[$k1][$k2]);
			if (isset($dropsid[$k1][$k2]))
				unset($dropsid[$k1][$k2]);
			if (isset($rejectsid[$k1][$k2]))
				unset($rejectsid[$k1][$k2]);
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
		$a_rule[$id]['rule_sid_on'] = $tmp;
	else				
		unset($a_rule[$id]['rule_sid_on']);

	$tmp = "";
	foreach (array_keys($disablesid) as $k1) {
		foreach (array_keys($disablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule[$id]['rule_sid_off'] = $tmp;
	else				
		unset($a_rule[$id]['rule_sid_off']);

	// Write the updated alertsid, dropsid and rejectsid values to the config file.
	$tmp = "";
	foreach (array_keys($alertsid) as $k1) {
		foreach (array_keys($alertsid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule[$id]['rule_sid_force_alert'] = $tmp;
	else
		unset($a_rule[$id]['rule_sid_force_alert']);

	$tmp = "";
	foreach (array_keys($dropsid) as $k1) {
		foreach (array_keys($dropsid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule[$id]['rule_sid_force_drop'] = $tmp;
	else
		unset($a_rule[$id]['rule_sid_force_drop']);

	$tmp = "";
	foreach (array_keys($rejectsid) as $k1) {
		foreach (array_keys($rejectsid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_rule[$id]['rule_sid_force_reject'] = $tmp;
	else
		unset($a_rule[$id]['rule_sid_force_reject']);

	// Write updated configuration
	config_set_path('installedpackages/snortglobal/rule', $a_rule);
	write_config("Snort pkg: remove enablesid/disablesid changes for category {$currentruleset} on {$a_rule[$id]['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('snort_rules_reset');

	// Reload the rules so we can accurately show content after
	// resetting any user overrides.
	// If it is the auto-flowbits file, set the full path.
	if ($currentruleset == "Auto-Flowbit Rules") {
		$rulefile = "{$snortcfgdir}/rules/" . FLOWBITS_FILENAME;
	}
	// Test for the special case of an IPS Policy file
	// and load the selected policy's rules.
	elseif (substr($currentruleset, 0, 10) == "IPS Policy") {
		$rules_map = snort_load_vrt_policy($a_rule[$id]['ips_policy'], $a_rule[$id]['ips_policy_mode']);
	}
	// Test for the special case of "Active Rules".  This
	// displays all currently active rules for the
	// interface.
	elseif ($currentruleset == "Active Rules") {
		$rules_map = snort_load_rules_map("{$snortcfgdir}/rules/");
	}
	// Test for the special cases of "User Forced" rules
	// and load the required rules for display.
	elseif ($currentruleset == "User Forced Enabled Rules") {
		// Search and display forced enabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Snort rules path to each entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . "/" . $v;
		}
		$rule_files[] = "{$snortcfgdir}/rules/" . FLOWBITS_FILENAME;
		$rule_files[] = "{$snortcfgdir}/rules/custom.rules";
		$rules_map = snort_get_filtered_rules($rule_files, snort_load_sid_mods($a_rule[$id]['rule_sid_on']));
	}
	elseif ($currentruleset == "User Forced Disabled Rules") {
		// Search and display forced disabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Snort rules path to each entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . "/" . $v;
		}
		$rule_files[] = "{$snortcfgdir}/rules/" . FLOWBITS_FILENAME;
		$rule_files[] = "{$snortcfgdir}/rules/custom.rules";
		$rules_map = snort_get_filtered_rules($rule_files, snort_load_sid_mods($a_rule[$id]['rule_sid_off']));
	}
	elseif ($currentruleset == "User Forced ALERT Action Rules") {
		$rules_map = snort_get_filtered_rules("{$snortcfgdir}/rules/", snort_load_sid_mods($a_rule[$id]['rule_sid_force_alert']));
	}
	elseif ($currentruleset == "User Forced DROP Action Rules") {
		$rules_map = snort_get_filtered_rules("{$snortcfgdir}/rules/", snort_load_sid_mods($a_rule[$id]['rule_sid_force_drop']));
	}
	elseif ($currentruleset == "User Forced REJECT Action Rules") {
		$rules_map = snort_get_filtered_rules("{$snortcfgdir}/rules/", snort_load_sid_mods($a_rule[$id]['rule_sid_force_reject']));
	}
	// If it's not a special case, and we can't find
	// the given rule file, then notify the user.
	elseif (!file_exists($rulefile)) {
		$input_errors[] = gettext("{$currentruleset} seems to be missing!!! Please verify rules files have been downloaded, then go to the Categories tab and save the rule set again.");
	}
	// Not a special case, and we have the matching
	// rule file, so load it up for display.
	else {
		$rules_map = snort_load_rules_map($rulefile);
	}
}
elseif ($_POST['resetall'] && !empty($rules_map)) {

	// Remove all modified SIDs from config.xml and save the changes.
	unset($a_rule[$id]['rule_sid_on']);
	unset($a_rule[$id]['rule_sid_off']);
	unset($a_rule[$id]['rule_sid_force_alert']);
	unset($a_rule[$id]['rule_sid_force_drop']);
	unset($a_rule[$id]['rule_sid_force_reject']);

	/* Update the config.xml file. */
	config_set_path('installedpackages/snortglobal/rule', $a_rule);
	write_config("Snort pkg: remove all enablesid/disablesid changes for {$a_rule[$id]['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('snort_rules_reset');

	// Reload the rules so we can accurately show content after
	// resetting any user overrides.
	// If it is the auto-flowbits file, set the full path.
	if ($currentruleset == "Auto-Flowbit Rules") {
		$rulefile = "{$snortcfgdir}/rules/" . FLOWBITS_FILENAME;
	}
	// Test for the special case of an IPS Policy file
	// and load the selected policy's rules.
	elseif (substr($currentruleset, 0, 10) == "IPS Policy") {
		$rules_map = snort_load_vrt_policy($a_rule[$id]['ips_policy'], $a_rule[$id]['ips_policy_mode']);
	}
	// Test for the special case of "Active Rules".  This
	// displays all currently active rules for the
	// interface.
	elseif ($currentruleset == "Active Rules") {
		$rules_map = snort_load_rules_map("{$snortcfgdir}/rules/");
	}
	// Test for the special cases of "User Forced" rules
	// and load the required rules for display.
	elseif ($currentruleset == "User Forced Enabled Rules") {
		// Search and display forced enabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Snort rules path to each entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . "/" . $v;
		}
		$rule_files[] = "{$snortcfgdir}/rules/" . FLOWBITS_FILENAME;
		$rule_files[] = "{$snortcfgdir}/rules/custom.rules";
		$rules_map = snort_get_filtered_rules($rule_files, snort_load_sid_mods($a_rule[$id]['rule_sid_on']));
	}
	elseif ($currentruleset == "User Forced Disabled Rules") {
		// Search and display forced disabled rules only from
		// the enabled rule categories for this interface.
		$rule_files = explode("||", $pconfig['rulesets']);

		// Prepend the Snort rules path to each entry.
		foreach ($rule_files as $k => $v) {
			$rule_files[$k] = $ruledir . "/" . $v;
		}
		$rule_files[] = "{$snortcfgdir}/rules/" . FLOWBITS_FILENAME;
		$rule_files[] = "{$snortcfgdir}/rules/custom.rules";
		$rules_map = snort_get_filtered_rules($rule_files, snort_load_sid_mods($a_rule[$id]['rule_sid_off']));
	}
	elseif ($currentruleset == "User Forced ALERT Action Rules") {
		$rules_map = snort_get_filtered_rules("{$snortcfgdir}/rules/", snort_load_sid_mods($a_rule[$id]['rule_sid_force_alert']));
	}
	elseif ($currentruleset == "User Forced DROP Action Rules") {
		$rules_map = snort_get_filtered_rules("{$snortcfgdir}/rules/", snort_load_sid_mods($a_rule[$id]['rule_sid_force_drop']));
	}
	elseif ($currentruleset == "User Forced REJECT Action Rules") {
		$rules_map = snort_get_filtered_rules("{$snortcfgdir}/rules/", snort_load_sid_mods($a_rule[$id]['rule_sid_force_reject']));
	}

	// If it's not a special case, and we can't find
	// the given rule file, then notify the user.
	elseif (!file_exists($rulefile)) {
		$input_errors[] = gettext("{$currentruleset} seems to be missing!!! Please verify rules files have been downloaded, then go to the Categories tab and save the rule set again.");
	}
	// Not a special case, and we have the matching
	// rule file, so load it up for display.
	else {
		$rules_map = snort_load_rules_map($rulefile);
	}
}
elseif (isset($_POST['cancel'])) {
	$pconfig['customrules'] = base64_decode($a_rule[$id]['customrules']);
	clear_subsystem_dirty('snort_rules_reset');
	clear_subsystem_dirty('snort_rules_state');
	clear_subsystem_dirty('snort_rules_action');
}
elseif (isset($_POST['clear'])) {
	unset($a_rule[$id]['customrules']);
	config_set_path('installedpackages/snortglobal/rule', $a_rule);
	write_config("Snort pkg: clear all custom rules for {$a_rule[$id]['interface']}.");
	$rebuild_rules = true;
	snort_generate_conf($a_rule[$id]);
	$rebuild_rules = false;
	$pconfig['customrules'] = '';

	// Sync to configured CARP slaves if any are enabled
	snort_sync_on_changes();
}
elseif (isset($_POST['save'])) {
	$pconfig['customrules'] = $_POST['customrules'];
	if ($_POST['customrules'])
		$a_rule[$id]['customrules'] = base64_encode(str_replace("\r\n", "\n", $_POST['customrules']));
	else
		unset($a_rule[$id]['customrules']);
	config_set_path('installedpackages/snortglobal/rule', $a_rule);
	write_config("Snort pkg: save modified custom rules for {$a_rule[$id]['interface']}.");
	$rebuild_rules = true;
	snort_generate_conf($a_rule[$id]);
	$rebuild_rules = false;
	$output = "";
	$retcode = "";
	exec("{$snortbindir}snort -T -c {$snortdir}/snort_{$snort_uuid}_{$if_real}/snort.conf 2>&1", $output, $retcode);
	if (intval($retcode) != 0) {
		$error = "";
		$start = count($output);
		$end = $start - 4;
		for($i = $start; $i > $end; $i--)
			$error .= $output[$i];
		$input_errors[] = "Custom rules have errors:\n {$error}";
	}
	else {
		// Soft-restart Snort to live-load new rules
		snort_reload_config($a_rule[$id]);
		$savemsg = gettext("Custom rules validated successfully and any active Snort process on this interface has been signaled to live-load the new rules.");
	}

	clear_subsystem_dirty('snort_rules_reset');
	clear_subsystem_dirty('snort_rules_state');
	clear_subsystem_dirty('snort_rules_action');

	// Sync to configured CARP slaves if any are enabled
	snort_sync_on_changes();
}
elseif ($_POST['filterrules_submit']) {
	// Set flag for filtering rules
	$filterrules = TRUE;
	$filterfieldsarray = array();
	$filterfieldsarray['show_enabled'] = $_POST['filterrules_enabled'] ? $_POST['filterrules_enabled'] : null;
	$filterfieldsarray['show_disabled'] = $_POST['filterrules_disabled'] ? $_POST['filterrules_disabled'] : null;
}
elseif ($_POST['filterrules_clear']) {
	$filterfieldsarray = array();
	$filterrules = TRUE;
}
elseif ($_POST['apply']) {
	/* Save new configuration */
	config_set_path('installedpackages/snortglobal/rule', $a_rule);
	write_config("Snort pkg: save new rules configuration for {$a_rule[$id]['interface']}.");

	/*************************************************/
	/* Update the snort conf file and rebuild the    */
	/* rules for this interface.                     */
	/*************************************************/
	$rebuild_rules = true;
	snort_generate_conf($a_rule[$id]);
	$rebuild_rules = false;

	// Soft-restart Snort to live-load new rules
	snort_reload_config($a_rule[$id]);

	// We have saved changes and done a soft restart, so clear "dirty" flags
	clear_subsystem_dirty('snort_rules_reset');
	clear_subsystem_dirty('snort_rules_state');
	clear_subsystem_dirty('snort_rules_action');

	// Sync to configured CARP slaves if any are enabled
	snort_sync_on_changes();

	if (snort_is_running($a_rule[$id]['uuid']))
		$savemsg = gettext("Snort is 'live-reloading' the new rule set.");
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_rule[$id]['interface']);
if (empty($if_friendly)) {
	$if_friendly = "None";
}
$pglinks = array("", "/snort/snort_interfaces.php", "/snort/snort_interfaces_edit.php?id={$id}", "@self");
$pgtitle = array("Services", "Snort", "Interface Settings", "{$if_friendly} - Rules");
include("head.inc");

// Display error messages if we have any
if ($input_errors) {
        print_input_errors($input_errors);
}

// Display save message if we have one
if ($savemsg) {
        print_info_box($savemsg);
}

?>

<form action="/snort/snort_rules.php" method="post" enctype="multipart/form-data" class="form-horizontal" name="iform" id="iform">
<input type='hidden' name='id' id='id' value='<?=$id;?>'/>
<input type='hidden' name='openruleset' id='openruleset' value='<?=$currentruleset;?>'/>
<input type='hidden' name='sid' id='sid' value=''/>
<input type='hidden' name='gid' id='gid' value=''/>

<?php
if (is_subsystem_dirty('snort_rules_state')) {
	$_POST['if'] = $id . "|" . $currentruleset;
	print_info_box('<p>' . gettext("A change has been made to a rule state.") . '<br/>' . gettext("Click APPLY when finished to send the changes to the running configuration.") . '</p>');
}
if (is_subsystem_dirty('snort_rules_action')) {
	$_POST['if'] = $id . "|" . $currentruleset;
	print_info_box('<p>' . gettext("A change has been made to a rule action.") . '<br/>' . gettext("Click APPLY when finished to send the changes to the running configuration.") . '</p>');
}
if (is_subsystem_dirty('snort_rules_reset')) {
	$_POST['if'] = $id . "|" . $currentruleset;
	print_info_box('<p>' . gettext("A rule category has been reset to vendor defaults.") . '<br/>' . gettext("Click APPLY when finished to send the changes to the running configuration.") . '</p>');
}

$tab_array = array();
	$tab_array[] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
	$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php?instance={$id}");
	$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
	$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);

$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
	$tab_array = array();
	$tab_array[] = array($menu_iface . gettext("Settings"), false, "/snort/snort_interfaces_edit.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Categories"), false, "/snort/snort_rulesets.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Rules"), true, "/snort/snort_rules.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Variables"), false, "/snort/snort_define_servers.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Preprocs"), false, "/snort/snort_preprocessors.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/snort/snort_ip_reputation.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
display_top_tabs($tab_array, true, 'nav nav-tabs');

$choices = array();
foreach ($categories as $value) {
	if ($snortdownload != 'on' && substr($value, 0, mb_strlen(VRT_FILE_PREFIX)) == VRT_FILE_PREFIX)
		continue;
	if ($emergingdownload != 'on' && substr($value, 0, mb_strlen(ET_OPEN_FILE_PREFIX)) == ET_OPEN_FILE_PREFIX)
		continue;
	if ($etprodownload != 'on' && substr($value, 0, mb_strlen(ET_PRO_FILE_PREFIX)) == ET_PRO_FILE_PREFIX)
		continue;
	if ($snortcommunitydownload != 'on' && substr($value, 0, mb_strlen(GPL_FILE_PREFIX)) == GPL_FILE_PREFIX)
		continue;
	if ($appidownload != 'on' && substr($value, 0, mb_strlen(OPENAPPID_FILE_PREFIX)) == OPENAPPID_FILE_PREFIX)
		continue;
	if (empty($value))
		continue;
	$choices[$value] = $value;
}
$section = new Form_Section('Available Rule Categories');
$section->addInput(new Form_Select(
	'selectbox',
	'Category Selection:',
	$currentruleset,
	$choices
))->setHelp('Select the rule category to view and manage.');
print($section);

if ($currentruleset == 'custom.rules') :
		$section = new Form_Section('Defined Custom Rules');
		$section->addInput(new Form_Textarea(
			'customrules',
			'',
			base64_decode($a_rule[$id]['customrules'])
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
	$msg .= '<a href="snort_rules_flowbits.php?id=' . $id . '" title="' . gettext('Add Suppress List entry for Flowbit Rule') . '">';
	$msg .= gettext('clicking here.') . '</a>';
	$group->setHelp('When finished, click APPLY to save and send any SID enable/disable changes made on this tab to Snort.<br/>' . $msg);
}
else {
	$group->setHelp('When finished, click APPLY to save and send any SID enable/disable changes made on this tab to Snort.');
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
	'Show Enabled Rules',
	'Show enabled rules',
	$filterfieldsarray['show_enabled'] == 'on' ? true:false,
	'on'
));
$group->add(new Form_Checkbox(
	'filterrules_disabled',
	'Show Disabled Rules',
	'Show disabled rules',
	$filterfieldsarray['show_disabled'] == 'on' ? true:false,
	'on'
));
$group->add(new Form_Button(
	'filterrules_submit',
	'Filter',
	null,
	'fa-solid fa-filter'
))->setHelp("Apply filter")
  ->removeClass("btn-primary")
  ->addClass("btn-sm btn-success");
$group->add(new Form_Button(
	'filterrules_clear',
	'Clear',
	null,
	'fa-regular fa-trash-can'
))->setHelp("Remove all filters")
  ->removeclass("btn-primary")
  ->addClass("btn-sm btn-danger no-confirm");
$section->add($group);
print($section);
// ========== End Rule filter Panel ===========================================
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Selected Category's Rules")?></h2></div>
	<div class="panel-body">
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
				<?php if ($a_rule[$id]['ips_mode'] == 'ips_mode_inline' && $a_rule[$id]['blockoffenders7'] == 'on') : ?>
						<td style="padding-left: 8px;"><i class="fa-regular fa-hand text-warning"></i></td><td style="padding-left: 4px;"><small><?=gettext('Rule action is reject');?></small></td>
				<?php else : ?>
						<td><td></td></td>
				<?php endif; ?>
					</tr>
					<tr>
						<td></td>
						<td style="padding-left: 8px;"><i class="fa-regular fa-circle-xmark text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Default Disabled');?></small></td>
						<td style="padding-left: 8px;"><i class="fa-solid fa-times-circle text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Disabled by user');?></small></td>
						<td style="padding-left: 8px;"><i class="fa-brands fa-adn text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Auto-disabled by SID Mgmt');?></small></td>
						<td><td></td></td>
				<?php if ($a_rule[$id]['blockoffenders7'] == 'on' && $a_rule[$id]['ips_mode'] == 'ips_mode_inline') : ?>
						<td style="padding-left: 8px;"><i class="fa-solid fa-thumbs-down text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Rule action is drop');?></small></td>
				<?php else : ?>
						<td><td></td></td>
				<?php endif; ?>
						<td><td></td></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="table-responsive">

			<?php if ($currentruleset != 'decoder.rules' && $currentruleset != 'preprocessor.rules'): ?>

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
							<col axis="string">
						</colgroup>
						<thead>
						   <tr class="sortableHeaderRowIdentifier">
							<th data-sortable="false"><?=gettext("State");?></th>
							<th data-sortable="false"><?=gettext("Action");?></th>
							<th data-sortable="true" data-sortable-type="numeric"><?=gettext("GID"); ?></th>
							<th data-sortable="true" data-sortable-type="numeric"><?=gettext("SID"); ?></th>
							<th data-sortable="true" data-sortable-type="alpha"><?=gettext("Proto"); ?></th>
							<th data-sortable="true" data-sortable-type="alpha"><?=gettext("Source"); ?></th>
							<th data-sortable="true" data-sortable-type="alpha"><?=gettext("SPort"); ?></th>
							<th data-sortable="true" data-sortable-type="alpha"><?=gettext("Destination"); ?></th>
							<th data-sortable="true" data-sortable-type="alpha"><?=gettext("DPort"); ?></th>
							<th data-sortable="true" data-sortable-type="alpha"><?=gettext("Message"); ?></th>
						   </tr>
						</thead>
						<tbody>
					<?php
						$counter = $enable_cnt = $disable_cnt = $user_enable_cnt = $user_disable_cnt = $managed_count = 0;
						foreach ($rules_map as $k1 => $rulem) {
							foreach ($rulem as $k2 => $v) {
								$sid = $k2;
								$gid = $k1;
								$ruleset = $currentruleset;
								$style = "";

								// Apply rule state filters if filtering is enabled
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
									$title = gettext("Force-Disabled by user. Click to change rule state");
								}
								// See if the rule is in our list of user-enabled overrides
								elseif (isset($enablesid[$gid][$sid])) {
									$textss = $textse = "";
									$enable_cnt++;
									$user_enable_cnt++;
									$iconb_class = 'class="fa-solid fa-check-circle text-success text-left"';
									$title = gettext("Force-Enabled by user. Click to change rule state");
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
									$title = gettext("Enabled by default. Click to change rule state");
								}

								// Determine which icon to display in the second column for rule action.
								// Default to ALERT icon.
								$textss = $textse = "";
								$iconact_class = 'class="fa-solid fa-exclamation-triangle text-warning text-center"';
								if (isset($alertsid[$gid][$sid]) && $a_rule[$id]['blockoffenders7'] == 'on' && $a_rule[$id]['ips_mode'] == 'ips_mode_inline') {
									$title_act = gettext("Rule action is User-Forced to alert on traffic when triggered.");
								}
								else {
									$title_act = gettext("Rule will alert on traffic when triggered.");
								}
								if ($v['action'] == 'drop' && $a_rule[$id]['blockoffenders7'] == 'on' && $a_rule[$id]['ips_mode'] == 'ips_mode_inline') {
									$iconact_class = 'class="fa-solid fa-thumbs-down text-danger text-center"';
									if (isset($dropsid[$gid][$sid])) {
										$title_act = gettext("Rule action is User-Forced to drop traffic when triggered.");
									}
									else {
										$title_act = gettext("Rule will drop traffic when triggered.");
									}
								}
								elseif ($v['action'] == 'reject' && $a_rule[$id]['ips_mode'] == 'ips_mode_inline' && $a_rule[$id]['blockoffenders7'] == 'on') {
									$iconact_class = 'class="fa-regular fa-hand text-warning text-center"';
									if (isset($rejectsid[$gid][$sid])) {
										$title_act = gettext("Rule action is User-Forced to reject traffic when triggered.");
									}
									else {
										$title_act = gettext("Rule will reject traffic when triggered.");
									}
								}
								if ($a_rule[$id]['blockoffenders7'] == 'on' && $a_rule[$id]['ips_mode'] == 'ips_mode_inline') {
									$title_act .= gettext("  Click to change rule action.");
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
								$message = snort_get_msg($v['rule']); // description field
								$sid_tooltip = gettext("View the raw text for this rule");
					?>
							<tr class="text-nowrap">
								<td><?=$textss; ?>
									<a id="rule_<?=$gid; ?>_<?=$sid; ?>" href="#" onClick="toggleState('<?=$sid; ?>', '<?=$gid; ?>');" 
									<?=$iconb_class; ?> title="<?=$title; ?>"></a><?=$textse; ?>
							<?php if ($v['managed'] == 1 && $v['modified'] == 1) : ?>
									<i class="fa-brands fa-adn text-warning text-left" title="<?=gettext('Action or content modified by settings on SID Mgmt tab'); ?>"></i><?=$textse; ?>
							<?php endif; ?>
								</td>
							<?php if ($a_rule[$id]['blockoffenders7'] == 'on' && $a_rule[$id]['ips_mode'] == 'ips_mode_inline') : ?>
								<td><?=$textss; ?><a id="rule_<?=$gid; ?>_<?=$sid; ?>_action" href="#" onClick="toggleAction('<?=$sid; ?>', '<?=$gid; ?>');" 
									<?=$iconact_class; ?> title="<?=$title_act; ?>"></a><?=$textse; ?>
								</td>
							<?php else : ?>
								<td><?=$textss; ?><i <?=$iconact_class; ?> title="<?=$title_act; ?>"></i><?=$textse; ?>
								</td>
							<?php endif; ?>
							       <td ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
									<?=$textss . $gid . $textse; ?>
							       </td>
							       <td ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
									<a href="javascript: void(0)" 
									onclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');" 
									title="<?=$sid_tooltip; ?>"><?=$textss . $sid . $textse; ?></a>
							       </td>
							       <td ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
									<?=$textss . $protocol . $textse; ?>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
									<?=$srcspan . $source; ?></span>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
									<?=$srcprtspan . $source_port; ?></span>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
									<?=$dstspan . $destination; ?></span>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
								       <?=$dstprtspan . $destination_port; ?></span>
							       </td>
								<td style="word-wrap:break-word; white-space:normal" ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
									<?=$textss . $message . $textse; ?>
							       </td>
							</tr>
						<?php
								$counter++;
							}
						}
						unset($rulem, $v);
						?>
					    </tbody>
					</table>

			<?php else: ?>

					<table style="table-layout: fixed; width: 100%;" class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
						<colgroup>
							<col width="5%">
							<col width="5%">
							<col width="5%">
							<col width="7%">
							<col width="22%">
							<col width="16%">
							<col align="left" axis="string">
						</colgroup>
						<thead>
						   <tr class="sortableHeaderRowIdentifier">
							<th data-sortable="false"><?=gettext("State"); ?></th>
							<th data-sortable="false"><?=gettext("Action");?></th>
							<th data-sortable="true" data-sortable-type="numeric"><?=gettext("GID"); ?></th>
							<th data-sortable="true" data-sortable-type="numeric"><?=gettext("SID"); ?></th>
							<th data-sortable="true" data-sortable-type="alpha"><?=gettext("Classification"); ?></th>
							<th data-sortable="true" data-sortable-type="alpha"><?=gettext("IPS Policy"); ?></th>
							<th data-sortable="true" data-sortable-type="alpha"><?=gettext("Message"); ?></th>
						   </tr>
						</thead>
						<tbody>
							<?php
								$counter = $enable_cnt = $disable_cnt = $user_enable_cnt = $user_disable_cnt = $managed_count = 0;
								foreach ($rules_map as $k1 => $rulem) {
									foreach ($rulem as $k2 => $v) {
										$ruleset = $currentruleset;
										$sid = $k2;
										$gid = $k1;
										$style = "";

										// Apply rule state filters if filtering is enabled
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
											$title = gettext("Force-Disabled by user. Click to change rule state");
										}
										// See if the rule is in our list of user-enabled overrides
										elseif (isset($enablesid[$gid][$sid])) {
											$textss = $textse = "";
											$enable_cnt++;
											$user_enable_cnt++;
											$iconb_class = 'class="fa-solid fa-check-circle text-success text-left"';
											$title = gettext("Force-Enabled by user. Click to change rule state");
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
											$title = gettext("Enabled by default. Click to change rule state");
										}

										// Determine which icon to display in the second column for rule action.
										// Default to ALERT icon.
										$textss = $textse = "";
										$iconact_class = 'class="fa-solid fa-exclamation-triangle text-warning text-center"';
										if (isset($alertsid[$gid][$sid]) && $a_rule[$id]['blockoffenders7'] == 'on' && $a_rule[$id]['ips_mode'] == 'ips_mode_inline') {
											$title_act = gettext("Rule action is User-Forced to alert on traffic when triggered.");
										}
										else {
											$title_act = gettext("Rule will alert on traffic when triggered.");
										}
										if ($v['action'] == 'drop' && $a_rule[$id]['blockoffenders7'] == 'on' && $a_rule[$id]['ips_mode'] == 'ips_mode_inline') {
											$iconact_class = 'class="fa-solid fa-thumbs-down text-danger text-center"';
											if (isset($dropsid[$gid][$sid])) {
												$title_act = gettext("Rule action is User-Forced to drop traffic when triggered.");
											}
											else {
												$title_act = gettext("Rule will drop traffic when triggered.");
											}
										}
										elseif ($v['action'] == 'reject' && $a_rule[$id]['ips_mode'] == 'ips_mode_inline' && $a_rule[$id]['blockoffenders7'] == 'on') {
											$iconact_class = 'class="fa-regular fa-hand text-warning text-center"';
											if (isset($rejectsid[$gid][$sid])) {
												$title_act = gettext("Rule action is User-Forced to reject traffic when triggered.");
											}
											else {
												$title_act = gettext("Rule will reject traffic when triggered.");
											}
										}
										if ($a_rule[$id]['blockoffenders7'] == 'on' && $a_rule[$id]['ips_mode'] == 'ips_mode_inline') {
											$title_act .= gettext("  Click to change rule action.");
										}

										$message = snort_get_msg($v['rule']);
										$matches = array();
										if (preg_match('/(?:classtype\b\s*:)\s*(\S*\s*;)/iU', $v['rule'], $matches))
											$classtype = trim($matches[1], " ;");
										else
											$classtype = "No Classtype Defined";
										$matches = array();
										if (preg_match_all('/(\S*-ips)(?:\s*drop|alert)(?:,|\s*|;)/i', $v['rule'], $matches))
											$policy = implode("<br/>", $matches[1]);
										else
											$policy = "none";
							?>
									<tr class="text-nowrap">
										<td><?=$textss; ?>
											<a id="rule_<?=$gid; ?>_<?=$sid; ?>" href="#" onClick="toggleState('<?=$sid; ?>', '<?=$gid; ?>');" 
											<?=$iconb_class; ?> title="<?=$title; ?>"></a><?=$textse; ?>
									<?php if ($v['managed'] == 1 && $v['modified'] == 1) : ?>
											<i class="fa-brands fa-adn text-warning text-left" title="<?=gettext('Action or content modified by settings on SID Mgmt tab'); ?>"></i><?=$textse; ?>
									<?php endif; ?>
										</td>
									<?php if ($a_rule[$id]['blockoffenders7'] == 'on' && $a_rule[$id]['ips_mode'] == 'ips_mode_inline') : ?>
										<td><?=$textss; ?><a id="rule_<?=$gid; ?>_<?=$sid; ?>_action" href="#" onClick="toggleAction('<?=$sid; ?>', '<?=$gid; ?>');" 
											<?=$iconact_class; ?> title="<?=$title_act; ?>"></a><?=$textse; ?>
										</td>
									<?php else : ?>
										<td><?=$textss; ?><i <?=$iconact_class; ?> title="<?=$title_act; ?>"></i><?=$textse; ?>
										</td>
									<?php endif; ?>

									       <td ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
											<?=$textss . $gid . $textse; ?>
									       </td>
									       <td ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
											<a href="javascript: void(0)" 
											onclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');" 
											title="<?=$sid_tooltip; ?>"><?=$textss . $sid . $textse; ?></a>
									       </td>
										<td ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
											<?=$textss . $classtype; ?></span>
							       			</td>
							       			<td ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
								       			<?=$textss . $policy; ?></span>
								       		</td>
										<td style="word-wrap:break-word; white-space:normal" ondblclick="showRuleContents('<?=$gid; ?>','<?=$sid; ?>');">
											<?=$textss . $message . $textse; ?>
							       			</td>
									</tr>
							<?php
										$counter++;
									}
								}
							unset($rulem, $v);
							?>
						</tbody>
					</table>

			<?php endif;?>
		</div>
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

		<?php if ($a_rule[$id]['ips_mode'] == 'ips_mode_inline' && $a_rule[$id]['blockoffenders7'] == 'on') : ?>
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
				<button type="button" class="btn btn-sm btn-warning" id="cancel" name="cancel" value="<?=gettext("Cancel");?>" data-dismiss="modal" title="<?=gettext("Abandon changes and quit selector");?>">
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
				<button type="button" class="btn btn-sm btn-warning" id="cancel" name="cancel" value="<?=gettext("Cancel");?>" data-dismiss="modal" title="<?=gettext("Abandon changes and quit selector");?>">
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
));
$modal->addInput(new Form_StaticText (
	'GID:SID',
	'<div class="text-left" id="modal_rule_gid_sid"></div>'
));
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
	$('#modal_rule_gid_sid').html(gid + ':' + sid);

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
	$('#rulesviewer_text').text(atob(req.responseText));
	$('#rulesviewer_text').attr('readonly', true);
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

	<?php if (!empty($anchor)): ?>
		// Scroll the last enabled/disabled SID into view
		window.location.hash = "<?=$anchor; ?>";
		window.scrollBy(0,-60); 
	<?php endif;?>

});
//]]>
</script>

<?php include("foot.inc"); ?>
