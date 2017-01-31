<?php
/*
 * snort_rules.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008-2009 Robert Zelaya
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

require_once("guiconfig.inc");
require_once("/usr/local/pkg/snort/snort.inc");

global $g, $rebuild_rules;

$snortdir = SNORTDIR;
$snortbindir = SNORT_PBI_BINDIR;
$rules_map = array();
$categories = array();
$pconfig = array();

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
$a_rule = &$config['installedpackages']['snortglobal']['rule'];

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
	header("Location: /snort/snort_interfaces.php");
	exit;
}

if (isset($id) && isset($a_rule[$id])) {
	$pconfig['interface'] = $a_rule[$id]['interface'];
	$pconfig['rulesets'] = $a_rule[$id]['rulesets'];
}

// Convert named interfaces to real
$if_real = get_real_interface($pconfig['interface']);
$snort_uuid = $a_rule[$id]['uuid'];
$snortcfgdir = "{$snortdir}/snort_{$snort_uuid}_{$if_real}";
$snortdownload = $config['installedpackages']['snortglobal']['snortdownload'];
$snortcommunitydownload = $config['installedpackages']['snortglobal']['snortcommunityrules'] == 'on' ? 'on' : 'off';
$emergingdownload = $config['installedpackages']['snortglobal']['emergingthreats'];
$etprodownload = $config['installedpackages']['snortglobal']['emergingthreats_pro'];
$appidownload = $config['installedpackages']['snortglobal']['openappid_rules_detectors'];

// Load a RULES file raw text if requested via Ajax to populate a Modal dialog
if ($_REQUEST['ajax']) {
	$contents = '';

	if ($_POST['openruleset']) {
		$file = htmlspecialchars($_POST['openruleset'], ENT_QUOTES);
	}
	else {
		print(gettext('INTERNAL ERROR!  No rules file was specified in postback.'));
		exit;
	}

	// Correct displayed file title if necessary
	if ($file == "Auto-Flowbit Rules")
		$displayfile = FLOWBITS_FILENAME;
	else
		$displayfile = $file;

	// Read the contents of the argument passed to us.
	// It may be an IPS policy string, an individual SID,
	// a standard rules file, or a complete file name.
	// Test for the special case of an IPS Policy file.
	if (substr($file, 0, 10) == "IPS Policy") {
		$rules_map = snort_load_vrt_policy(strtolower(trim(substr($file, strpos($file, "-")+1))));
		if (isset($_POST['sid']) && is_numericint($_POST['sid']) && isset($_POST['gid']) && is_numericint($_POST['gid'])) {
			$contents = $rules_map[$_POST['gid']][trim($_POST['sid'])]['rule'];
		}
		else {
			$contents = "# Snort IPS Policy - " . ucfirst(trim(substr($file, strpos($file, "-")+1))) . "\n\n";
			foreach (array_keys($rules_map) as $k1) {
				foreach (array_keys($rules_map[$k1]) as $k2) {
					$contents .= "# Category: " . $rules_map[$k1][$k2]['category'] . "   SID: {$k2}\n";
					$contents .= $rules_map[$k1][$k2]['rule'] . "\n";
				}
			}
		}
		unset($rules_map);
	}
	// Is it a SID to load the rule text from?
	elseif (isset($_POST['sid']) && is_numericint($_POST['sid']) && isset($_POST['gid']) && is_numericint($_POST['gid'])) {
		// If flowbit rule, point to interface-specific file
		if ($file == "Auto-Flowbit Rules")
			$rules_map = snort_load_rules_map("{$snortcfgdir}/rules/" . FLOWBITS_FILENAME);
		elseif (file_exists("{$snortdir}/preproc_rules/{$file}"))
			$rules_map = snort_load_rules_map("{$snortdir}/preproc_rules/{$file}");
		else
			$rules_map = snort_load_rules_map("{$snortdir}/rules/{$file}");
		$contents = $rules_map[$_POST['gid']][trim($_POST['sid'])]['rule'];
	}
	// Is it our special flowbit rules file?
	elseif ($file == "Auto-Flowbit Rules")
		$contents = file_get_contents("{$snortcfgdir}/rules/{$flowbit_rules_file}");
	// Is it a rules file in the ../rules/ directory?
	elseif (file_exists("{$snortdir}/rules/{$file}"))
		$contents = file_get_contents("{$snortdir}/rules/{$file}");
	// Is it a rules file in the ../preproc_rules/ directory?
	elseif (file_exists("{$snortdir}/preproc_rules/{$file}"))
		$contents = file_get_contents("{$snortdir}/preproc_rules/{$file}");
	// Is it a disabled preprocessor auto-rules-disable file?
	elseif (file_exists("{$snortlogdir}/{$file}"))
		$contents = file_get_contents("{$snortlogdir}/{$file}");
	// It is not something we can display, so exit.
	else
		$contents = gettext("Unable to open file: {$displayfile}");

	print(gettext($contents));
	exit;
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


// Add any previously saved rules files to the categories array
if (!empty($pconfig['rulesets']))
	$categories = explode("||", $pconfig['rulesets']);

// add the standard rules files to the categories array
$categories[] = "custom.rules";
$categories[] = "decoder.rules";
$categories[] = "preprocessor.rules";
$categories[] = "sensitive-data.rules";

// Get any automatic rule category enable/disable modifications
// if auto-SID Mgmt is enabled, and adjust the available rulesets
// in the CATEGORY drop-down box as necessary.
$cat_mods = snort_sid_mgmt_auto_categories($a_rule[$id], FALSE);
foreach ($cat_mods as $k => $v) {
	switch ($v) {
		case 'disabled':
			if (($key = array_search($k, $categories)) !== FALSE)
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

// Add any enabled IPS-Policy and Auto-Flowbits File
if (!empty($a_rule[$id]['ips_policy']))
	$categories[] = "IPS Policy - " . ucfirst($a_rule[$id]['ips_policy']);
if ($a_rule[$id]['autoflowbitrules'] == 'on')
	$categories[] = "Auto-Flowbit Rules";
natcasesort($categories);

if (isset($_POST['openruleset']))
	$currentruleset = $_POST['openruleset'];
elseif (isset($_GET['openruleset']))
	$currentruleset = htmlspecialchars($_GET['openruleset']);
else
	$currentruleset = $categories[key($categories)];

// One last sanity check -- if the rules directory is empty, default to loading custom rules
$tmp = glob("{$snortdir}/rules/*.rules");
if (empty($tmp))
	$currentruleset = "custom.rules";

$rulefile = "{$snortdir}/rules/{$currentruleset}";
if ($currentruleset != 'custom.rules') {
	// Read the current rules file into our rules map array.
	// If it is the auto-flowbits file, set the full path.
	if ($currentruleset == "Auto-Flowbit Rules")
		$rules_map = snort_load_rules_map("{$snortcfgdir}/rules/" . FLOWBITS_FILENAME);
	// Test for the special case of an IPS Policy file.
	elseif (substr($currentruleset, 0, 10) == "IPS Policy")
		$rules_map = snort_load_vrt_policy($a_rule[$id]['ips_policy']);
	// Test for preproc_rules file and set the full path.
	elseif (file_exists("{$snortdir}/preproc_rules/{$currentruleset}"))
		$rules_map = snort_load_rules_map("{$snortdir}/preproc_rules/{$currentruleset}");
	// Test for existence of regular text rules file and load it.
	elseif (file_exists($rulefile))
		$rules_map = snort_load_rules_map($rulefile);
	else
		$input_errors[] = gettext("{$currentruleset} seems to be missing!!! Please verify rules files have been downloaded, then go to the Categories tab and save the rule set again.");
}

// Process the current category rules through any auto SID MGMT changes if enabled
snort_auto_sid_mgmt($rules_map, $a_rule[$id], FALSE);

// Load up our enablesid and disablesid arrays with enabled or disabled SIDs
$enablesid = snort_load_sid_mods($a_rule[$id]['rule_sid_on']);
$disablesid = snort_load_sid_mods($a_rule[$id]['rule_sid_off']);

if ($_POST['toggle'] && is_numeric($_POST['sid']) && is_numeric($_POST['gid']) && !empty($rules_map)) {

	// Get the GID:SID tags embedded in the clicked rule icon.
	$gid = $_POST['gid'];
	$sid = $_POST['sid'];

	// See if the target SID is in our list of modified SIDs,
	// and toggle if present; otherwise, add it to the
	// appropriate modified SID list.
	if (isset($enablesid[$gid][$sid])) {
		unset($enablesid[$gid][$sid]);
		$disablesid[$gid][$sid] = "disablesid";
	}
	elseif (isset($disablesid[$gid][$sid])) {
		unset($disablesid[$gid][$sid]);
		$enablesid[$gid][$sid] = "enablesid";
	}
	else {
		if ($rules_map[$gid][$sid]['disabled'] == 1)
			$enablesid[$gid][$sid] = "enablesid";
		else
			$disablesid[$gid][$sid] = "disablesid";
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
	write_config("Snort pkg: modified state for rule {$gid}:{$sid} on {$a_rule[$id]['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('snort_rules');

	// Set a scroll-to anchor location
	$anchor = "rule_{$gid}_{$sid}";
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

	write_config("Snort pkg: disabled all rules in category {$currentruleset} for {$a_rule[$id]['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('snort_rules');
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

	write_config("Snort pkg: enable all rules in category {$currentruleset} for {$a_rule[$id]['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('snort_rules');
}
elseif ($_POST['resetcategory'] && !empty($rules_map)) {

	// Reset any modified SIDs in the current rule category to their defaults.
	foreach (array_keys($rules_map) as $k1) {
		foreach (array_keys($rules_map[$k1]) as $k2) {
			if (isset($enablesid[$k1][$k2]))
				unset($enablesid[$k1][$k2]);
			if (isset($disablesid[$k1][$k2]))
				unset($disablesid[$k1][$k2]);
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

	write_config("Snort pkg: remove enablesid/disablesid changes for category {$currentruleset} on {$a_rule[$id]['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('snort_rules');
}
elseif ($_POST['resetall'] && !empty($rules_map)) {

	// Remove all modified SIDs from config.xml and save the changes.
	unset($a_rule[$id]['rule_sid_on']);
	unset($a_rule[$id]['rule_sid_off']);

	/* Update the config.xml file. */
	write_config("Snort pkg: remove all enablesid/disablesid changes for {$a_rule[$id]['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('snort_rules');
}
elseif (isset($_POST['cancel'])) {
	$pconfig['customrules'] = base64_decode($a_rule[$id]['customrules']);
	clear_subsystem_dirty('snort_rules');
}
elseif (isset($_POST['clear'])) {
	unset($a_rule[$id]['customrules']);
	write_config("Snort pkg: clear all custom rules for {$a_rule[$id]['interface']}.");
	$rebuild_rules = true;
	conf_mount_rw();
	snort_generate_conf($a_rule[$id]);
	conf_mount_ro();
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
	write_config("Snort pkg: save modified custom rules for {$a_rule[$id]['interface']}.");
	$rebuild_rules = true;
	conf_mount_rw();
	snort_generate_conf($a_rule[$id]);
	conf_mount_ro();
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
		$savemsg = gettext("Custom rules validated successfully and any active Snort process on this interface has been signalled to live-load the new rules.");
	}

	clear_subsystem_dirty('snort_rules');

	// Sync to configured CARP slaves if any are enabled
	snort_sync_on_changes();
}
elseif ($_POST['apply']) {
	/* Save new configuration */
	write_config("Snort pkg: save new rules configuration for {$a_rule[$id]['interface']}.");

	/*************************************************/
	/* Update the snort conf file and rebuild the    */
	/* rules for this interface.                     */
	/*************************************************/
	$rebuild_rules = true;
	conf_mount_rw();
	snort_generate_conf($a_rule[$id]);
	conf_mount_ro();
	$rebuild_rules = false;

	// Soft-restart Snort to live-load new rules
	snort_reload_config($a_rule[$id]);

	// We have saved changes and done a soft restart, so clear "dirty" flag
	clear_subsystem_dirty('snort_rules');

	// Sync to configured CARP slaves if any are enabled
	snort_sync_on_changes();

	if (snort_is_running($snort_uuid, $if_real))
		$savemsg = gettext("Snort is 'live-reloading' the new rule set.");
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_rule[$id]['interface']);
$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Rules"), gettext("{$if_friendly}"));
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
if (is_subsystem_dirty('snort_rules')) {
	print_info_box('<p>' . gettext("A change has been made to a rule state.") . '<br/>' . gettext("Click APPLY when finished to send the changes to the running configuration.") . '</p>');
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
	$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/snort/snort_barnyard.php?id={$id}");
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
				<i class="fa fa-save icon-embed-btn"></i>
				<?=gettext('Save');?>
			</button>
			<button type="submit" id="cancel" name="cancel" class="btn btn-warning btn-sm" title="<?=gettext('Cancel changes and return to last page');?>">
				<?=gettext('Cancel');?>
			</button>
			<button type="submit" id="clear" name="clear" class="btn btn-danger btn-sm" title="<?=gettext('Deletes all custom rules for this interface');?>">
				<i class="fa fa-trash icon-embed-btn"></i>
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
	'fa-save'
))->setAttribute('title', gettext('Apply changes made on this tab and rebuild the interface rules'))->addClass('btn-primary btn-sm');
$group->add(new Form_Button(
	'resetall',
	'Reset All',
	null,
	'fa-repeat'
))->setAttribute('title', gettext('Remove user overrides for all rule categories'))->addClass('btn-sm btn-warning');
$group->add(new Form_Button(
	'resetcategory',
	'Reset Current',
	null,
	'fa-repeat'
))->setAttribute('title', gettext('Remove user overrides for only the currently selected category'))->addClass('btn-sm btn-warning');
$group->add(new Form_Button(
	'disable_all',
	'Disable All',
	null,
	'fa-times-circle-o'
))->setAttribute('title', gettext('Disable all rules in the currently selected category'))->addClass('btn-sm btn-danger');
$group->add(new Form_Button(
	'enable_all',
	'Enable All',
	null,
	'fa-check-circle-o'
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

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Selected Category's Rules")?></h2></div>
	<div class="panel-body">
		<div class="content table-responsive">
			<table>
				<tbody>
					<tr>
						<td><b><?=gettext('Legend: ');?></b></td>
						<td style="padding-left: 8px;"><i class="fa fa-check-circle-o text-success"></i></td><td style="padding-left: 4px;"><small><?=gettext('Default Enabled');?></small></td>
						<td style="padding-left: 8px;"><i class="fa fa-check-circle text-success"></i></td><td style="padding-left: 4px;"><small><?=gettext('Enabled by user');?></small></td>
						<td style="padding-left: 8px;"><i class="fa fa-adn text-success"></i></td><td style="padding-left: 4px;"><small><?=gettext('Auto-enabled by SID Mgmt');?></small></td>
					</tr>
					<tr>
						<td></td>
						<td style="padding-left: 8px;"><i class="fa fa-times-circle-o text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Default Disabled');?></small></td>
						<td style="padding-left: 8px;"><i class="fa fa-times-circle text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Disabled by user');?></small></td>
						<td style="padding-left: 8px;"><i class="fa fa-adn text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Auto-disabled by SID Mgmt');?></small></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="table-responsive">

			<?php if ($currentruleset != 'decoder.rules' && $currentruleset != 'preprocessor.rules'): ?>

					<table style="table-layout: fixed; width: 100%;" class="table table-striped table-hover table-condensed">
						<colgroup>
							<col width="3%">
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
							<th class="sorttable_nosort">&nbsp;</th>
							<th><?=gettext("GID"); ?></th>
							<th><?=gettext("SID"); ?></th>
							<th><?=gettext("Proto"); ?></th>
							<th><?=gettext("Source"); ?></th>
							<th><?=gettext("SPort"); ?></th>
							<th><?=gettext("Destination"); ?></th>
							<th><?=gettext("DPort"); ?></th>
							<th><?=gettext("Message"); ?></th>
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

								if ($v['managed'] == 1) {
									if ($v['disabled'] == 1) {
										$textss = '<span class="text-muted">';
										$textse = '</span>';
										$iconb_class = 'class="fa fa-adn text-danger text-left"';
										$title = gettext("Auto-disabled by settings on SID Mgmt tab");
									}
									else {
										$textss = $textse = "";
										$ruleset = "snort.rules";
										$iconb_class = 'class="fa fa-adn text-success text-left"';
										$title = gettext("Auto-managed by settings on SID Mgmt tab");
									}
									$iconb = "icon_advanced.gif";
									$managed_count++;
								}
								elseif (isset($disablesid[$gid][$sid])) {
									$textss = "<span class=\"text-muted\">";
									$textse = "</span>";
									$disable_cnt++;
									$user_disable_cnt++;
									$iconb_class = 'class="fa fa-times-circle text-danger text-left"';
									$title = gettext("Disabled by user. Click to toggle to enabled state");
								}
								elseif (($v['disabled'] == 1) && (!isset($enablesid[$gid][$sid]))) {
									$textss = "<span class=\"text-muted\">";
									$textse = "</span>";
									$disable_cnt++;
									$iconb_class = 'class="fa fa-times-circle-o text-danger text-left"';
									$title = gettext("Disabled by default. Click to toggle to enabled state");
								}
								elseif (isset($enablesid[$gid][$sid])) {
									$textss = $textse = "";
									$enable_cnt++;
									$user_enable_cnt++;
									$iconb_class = 'class="fa fa-check-circle text-success text-left"';
									$title = gettext("Enabled by user. Click to toggle to disabled state");
								}
								else {
									$textss = $textse = "";
									$enable_cnt++;
									$iconb_class = 'class="fa fa-check-circle-o text-success text-left"';
									$title = gettext("Enabled by default. Click to toggle to disabled state");
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
						<?php if ($v['managed'] == 1) : ?>
									<i <?=$iconb_class; ?> title="<?=$title; ?>"</i><?=$textse; ?>
						<?php else : ?>
									<a id="rule_<?=$gid; ?>_<?=$sid; ?>" href="#" onClick="doToggle('<?=$gid; ?>', '<?=$sid; ?>');" 
									<?=$iconb_class; ?> title="<?=$title; ?>"></a><?=$textse; ?>
						<?php endif; ?>
							       </td>
							       <td ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
									<?=$textss . $gid . $textse; ?>
							       </td>
							       <td ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
									<a href="javascript: void(0)" 
									onclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');" 
									title="<?=$sid_tooltip; ?>"><?=$textss . $sid . $textse; ?></a>
							       </td>
							       <td ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
									<?=$textss . $protocol . $textse; ?>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
									<?=$srcspan . $source; ?></span>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
									<?=$srcprtspan . $source_port; ?></span>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
									<?=$dstspan . $destination; ?></span>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
								       <?=$dstprtspan . $destination_port; ?></span>
							       </td>
								<td style="word-wrap:break-word; white-space:normal" ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
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

					<table style="table-layout: fixed; width: 100%;" class="table table-striped table-hover table-condensed">
						<colgroup>
							<col width="4%">
							<col width="5%">
							<col width="7%">
							<col width="22%">
							<col width="16%">
							<col align="left" axis="string">
						</colgroup>
						<thead>
						   <tr class="sortableHeaderRowIdentifier">
							<th sorttable_nosort>&nbsp;</th>
							<th><?=gettext("GID"); ?></th>
							<th><?=gettext("SID"); ?></th>
							<th><?=gettext("Classification"); ?></th>
							<th><?=gettext("IPS Policy"); ?></th>
							<th><?=gettext("Message"); ?></th>
						   </tr>
						</thead>
						<tbody>
							<?php
								$counter = $enable_cnt = $disable_cnt = $user_enable_cnt = $user_disable_cnt = $managed_count = 0;
								foreach ($rules_map as $k1 => $rulem) {
									foreach ($rulem as $k2 => $v) {
										$ruleset = $currentruleset;
										$sid = snort_get_sid($v['rule']);
										$gid = snort_get_gid($v['rule']);

										if ($v['managed'] == 1) {
											if ($v['disabled'] == 1) {
												$textss = "<span class=\"text-muted\">";
												$textse = "</span>";
												$iconb_class = 'class="fa fa-adn text-danger text-left"';
												$title = gettext("Auto-disabled by settings on SID Mgmt tab");
											}
											else {
												$textss = $textse = "";
												$ruleset = "snort.rules";
												$iconb_class = 'class="fa fa-adn text-success text-left"';
												$title = gettext("Auto-managed by settings on SID Mgmt tab");
											}
											$managed_count++;
										}
										elseif (isset($disablesid[$gid][$sid])) {
											$textss = "<span class=\"text-muted\">";
											$textse = "</span>";
											$disable_cnt++;
											$user_disable_cnt++;
											$iconb_class = 'class="fa fa-times-circle text-danger text-left"';
											$title = gettext("Disabled by user. Click to toggle to enabled state");
										}
										elseif (($v['disabled'] == 1) && (!isset($enablesid[$gid][$sid]))) {
											$textss = "<span class=\"text-muted\">";
											$textse = "</span>";
											$disable_cnt++;
											$iconb_class = 'class="fa fa-times-circle-o text-danger text-left"';
											$title = gettext("Disabled by default. Click to toggle to enabled state");
										}
										elseif (isset($enablesid[$gid][$sid])) {
											$textss = $textse = "";
											$enable_cnt++;
											$user_enable_cnt++;
											$iconb_class = 'class="fa fa-check-circle text-success text-left"';
											$title = gettext("Enabled by user. Click to toggle to disabled state");
										}
										else {
											$textss = $textse = "";
											$enable_cnt++;
											$iconb_class = 'class="fa fa-check-circle-o text-success text-left"';
											$title = gettext("Enabled by default. Click to toggle to disabled state");
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
								<?php if ($v['managed'] == 1) : ?>
											<i {$iconb_class} title='{$title}'</i>{$textse}";
								<?php else : ?>
											<a id="rule_<?=$gid; ?>_<?=$sid; ?>" href="#" onClick="doToggle('<?=$gid; ?>', '<?=$sid; ?>');" 
											<?=$iconb_class; ?> title="<?=$title; ?>"</a><?=$textse; ?>
								<?php endif; ?>
									       </td>
									       <td ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
											<?=$textss . $gid . $textse; ?>
									       </td>
									       <td ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
											<a href="javascript: void(0)" 
											onclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');" 
											title="<?=$sid_tooltip; ?>"><?=$textss . $sid . $textse; ?></a>
									       </td>
										<td ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
											<?=$textss . $classtype; ?></span>
							       			</td>
							       			<td ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
								       			<?=$textss . $policy; ?></span>
								       		</td>
										<td style="word-wrap:break-word; white-space:normal" ondblclick="getRuleFileContents('<?=$gid; ?>','<?=$sid; ?>');">
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

</form>

<?php
// Create a Modal object to display raw text of user-clicked rules
$form = new Form(FALSE);
$modal = new Modal('View Rules Raw Text', 'rulesviewer', 'large', 'Close');
$modal->addInput(new Form_Textarea (
	'rulesviewer_text',
	'Rule Text',
	'...Loading...'
))->removeClass('form-control')->addClass('row-fluid col-sm-12')->setAttribute('rows', '25')->setAttribute('wrap', 'soft');
$form->add($modal);
print($form);
?>

<script type="text/javascript">
//<![CDATA[

	function doToggle(gid, sid) {
		$('#gid').val(gid);
		$('#sid').val(sid);
		$('#iform').append('<input type="hidden" name="toggle" id="toggle" value="1"/>');
		$('#iform').submit();
	}

	function getRuleFileContents(gid, sid) {
		var ajaxRequest;

		ajaxRequest = $.ajax({
			url: "/snort/snort_rules.php",
			type: "post",
			data: { ajax: "ajax", 
				id: $('#id').val(),
				openruleset: $('#selectbox').val(),
				gid: gid,
				sid: sid
			}
		});

		// Display the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {

			$('#rulesviewer').modal('show');

			// Write the list contents to the text control
			$('#rulesviewer_text').text(response);
			$('#rulesviewer_text').attr('readonly', true);
		});
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

	<?php if (!empty($anchor)): ?>
		// Scroll the last enabled/disabled SID into view
		window.location.hash = "<?=$anchor; ?>";
		window.scrollBy(0,-60); 
	<?php endif;?>

});
//]]>
</script>

<?php include("foot.inc"); ?>
