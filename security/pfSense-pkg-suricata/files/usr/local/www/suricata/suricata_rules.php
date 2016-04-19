<?php
/*
 * suricata_rules.php
 *
*  Copyright (c)  2004-2016  Electric Sheep Fencing, LLC. All rights reserved.
*
*  Redistribution and use in source and binary forms, with or without modification,
*  are permitted provided that the following conditions are met:
*
*  1. Redistributions of source code must retain the above copyright notice,
*      this list of conditions and the following disclaimer.
*
*  2. Redistributions in binary form must reproduce the above copyright
*      notice, this list of conditions and the following disclaimer in
*      the documentation and/or other materials provided with the
*      distribution.
*
*  3. All advertising materials mentioning features or use of this software
*      must display the following acknowledgment:
*      "This product includes software developed by the pfSense Project
*       for use in the pfSense software distribution. (http://www.pfsense.org/).
*
*  4. The names "pfSense" and "pfSense Project" must not be used to
*       endorse or promote products derived from this software without
*       prior written permission. For written permission, please contact
*       coreteam@pfsense.org.
*
*  5. Products derived from this software may not be called "pfSense"
*      nor may "pfSense" appear in their names without prior written
*      permission of the Electric Sheep Fencing, LLC.
*
*  6. Redistributions of any form whatsoever must retain the following
*      acknowledgment:
*
*  "This product includes software developed by the pfSense Project
*  for use in the pfSense software distribution (http://www.pfsense.org/).
*
*  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
*  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
*  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
*  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
*  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
*  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
*  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
*  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
*  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
*  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
*  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
*  OF THE POSSIBILITY OF SUCH DAMAGE.
*
*
* Portions of this code are based on original work done for the Snort package for pfSense by the following contributors:
*
* Copyright (C) 2003-2004 Manuel Kasper
* Copyright (C) 2005 Bill Marquette
* Copyright (C) 2006 Scott Ullrich (copyright assigned to ESF)
* Copyright (C) 2009 Robert Zelaya Sr. Developer
* Copyright (C) 2012 Ermal Luci  (copyright assigned to ESF)
* Copyright (C) 2016 Bill Meeks
*
*/

require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g, $config, $rebuild_rules;

$suricatadir = SURICATADIR;
$rules_map = array();
$pconfig = array();

if (!is_array($config['installedpackages']['suricata']['rule']))
	$config['installedpackages']['suricata']['rule'] = array();
$a_rule = &$config['installedpackages']['suricata']['rule'];

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
	$id = 0;
}

if (isset($id) && $a_rule[$id]) {
	$pconfig['interface'] = $a_rule[$id]['interface'];
	$pconfig['rulesets'] = $a_rule[$id]['rulesets'];
	$pconfig['customrules'] = base64_decode($a_rule[$id]['customrules']);
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
$suricata_uuid = $a_rule[$id]['uuid'];
$suricatacfgdir = "{$suricatadir}suricata_{$suricata_uuid}_{$if_real}";
$snortdownload = $config['installedpackages']['suricata']['config'][0]['enable_vrt_rules'];
$emergingdownload = $config['installedpackages']['suricata']['config'][0]['enable_etopen_rules'];
$etpro = $config['installedpackages']['suricata']['config'][0]['enable_etpro_rules'];
$categories = explode("||", $pconfig['rulesets']);

// Get any automatic rule category enable/disable modifications
// if auto-SID Mgmt is enabled, and adjust the available rulesets
// in the CATEGORY drop-down box as necessary.
$cat_mods = suricata_sid_mgmt_auto_categories($a_rule[$id], FALSE);
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

if ($_GET['openruleset'])
	$currentruleset = htmlspecialchars($_GET['openruleset'], ENT_QUOTES | ENT_HTML401);
elseif ($_POST['selectbox'])
	$currentruleset = $_POST['selectbox'];
elseif ($_POST['openruleset'])
	$currentruleset = $_POST['openruleset'];
else
	$currentruleset = $categories[0];

if (empty($categories[0]) && ($currentruleset != "custom.rules") && ($currentruleset != "Auto-Flowbit Rules")) {
	if (!empty($a_rule[$id]['ips_policy']))
		$currentruleset = "IPS Policy - " . ucfirst($a_rule[$id]['ips_policy']);
	else
		$currentruleset = "custom.rules";
}

/* One last sanity check -- if the rules directory is empty, default to loading custom rules */
$tmp = glob("{$suricatadir}rules/*.rules");
if (empty($tmp))
	$currentruleset = "custom.rules";

$ruledir = "{$suricatadir}rules";
$rulefile = "{$ruledir}/{$currentruleset}";
if ($currentruleset != 'custom.rules') {
	// Read the current rules file into our rules map array.
	// If it is the auto-flowbits file, set the full path.
	if ($currentruleset == "Auto-Flowbit Rules")
		$rulefile = "{$suricatacfgdir}/rules/" . FLOWBITS_FILENAME;
	// Test for the special case of an IPS Policy file.
	if (substr($currentruleset, 0, 10) == "IPS Policy")
		$rules_map = suricata_load_vrt_policy($a_rule[$id]['ips_policy']);
	elseif (!file_exists($rulefile))
		$input_errors[] = gettext("{$currentruleset} seems to be missing!!! Please verify rules files have been downloaded, then go to the Categories tab and save the rule set again.");
	else
		$rules_map = suricata_load_rules_map($rulefile);
}

/* Process the current category rules through any auto SID MGMT changes if enabled */
suricata_auto_sid_mgmt($rules_map, $a_rule[$id], FALSE);

/* Load up our enablesid and disablesid arrays with manually enabled or disabled SIDs */
$enablesid = suricata_load_sid_mods($a_rule[$id]['rule_sid_on']);
$disablesid = suricata_load_sid_mods($a_rule[$id]['rule_sid_off']);

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

if (isset($_POST['toggle']) && is_numeric($_POST['sid']) && is_numeric($_POST['gid']) && !empty($rules_map)) {

	// Get the GID:SID tags embedded in the clicked rule icon.
	$gid = $_POST['gid'];
	$sid = $_POST['sid'];

	// See if the target SID is in our list of modified SIDs,
	// and toggle it opposite state if present; otherwise,
	// add it to the appropriate modified SID list.
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
	write_config("Suricata pkg: modified state for rule {$gid}:{$sid} on {$a_rule[$id]['interface']}.");

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');

	// Set a scroll-to anchor location
	$anchor = "rule_{$gid}_{$sid}";
}
elseif (isset($_POST['disable_all']) && !empty($rules_map)) {

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

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');

	write_config("Suricata pkg: disabled all rules in category {$currentruleset} for {$a_rule[$id]['interface']}.");
}
elseif (isset($_POST['enable_all']) && !empty($rules_map)) {

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

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');

	write_config("Suricata pkg: enable all rules in category {$currentruleset} for {$a_rule[$id]['interface']}.");
}
elseif (isset($_POST['resetcategory']) && !empty($rules_map)) {

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

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');

	write_config("Suricata pkg: remove enablesid/disablesid changes for category {$currentruleset} on {$a_rule[$id]['interface']}.");
}
elseif (isset($_POST['resetall']) && !empty($rules_map)) {

	// Remove all modified SIDs from config.xml and save the changes.
	unset($a_rule[$id]['rule_sid_on']);
	unset($a_rule[$id]['rule_sid_off']);

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');

	/* Update the config.xml file. */
	write_config("Suricata pkg: remove all enablesid/disablesid changes for {$a_rule[$id]['interface']}.");
}
elseif (isset($_POST['clear'])) {
	unset($a_rule[$id]['customrules']);
	write_config("Suricata pkg: clear all custom rules for {$a_rule[$id]['interface']}.");
	$rebuild_rules = true;
	conf_mount_rw();
	suricata_generate_yaml($a_rule[$id]);
	conf_mount_ro();
	$rebuild_rules = false;
	$pconfig['customrules'] = '';

	// Sync to configured CARP slaves if any are enabled
	suricata_sync_on_changes();
}
elseif (isset($_POST['cancel'])) {
	$pconfig['customrules'] = base64_decode($a_rule[$id]['customrules']);
	clear_subsystem_dirty('suricata_rules');
}
elseif (isset($_POST['save'])) {
	$pconfig['customrules'] = $_POST['customrules'];
	if ($_POST['customrules'])
		$a_rule[$id]['customrules'] = base64_encode(str_replace("\r\n", "\n", $_POST['customrules']));
	else
		unset($a_rule[$id]['customrules']);
	write_config("Suricata pkg: save modified custom rules for {$a_rule[$id]['interface']}.");
	$rebuild_rules = true;
	conf_mount_rw();
	suricata_generate_yaml($a_rule[$id]);
	conf_mount_ro();
	$rebuild_rules = false;
	/* Signal Suricata to "live reload" the rules */
	suricata_reload_config($a_rule[$id]);
	clear_subsystem_dirty('suricata_rules');

	// Sync to configured CARP slaves if any are enabled
	suricata_sync_on_changes();
}
elseif (isset($_POST['apply'])) {

	/* Save new configuration */
	write_config("Suricata pkg: new rules configuration for {$a_rule[$id]['interface']}.");

	/*************************************************/
	/* Update the suricata.yaml file and rebuild the */
	/* rules for this interface.                     */
	/*************************************************/
	$rebuild_rules = true;
	conf_mount_rw();
	suricata_generate_yaml($a_rule[$id]);
	conf_mount_ro();
	$rebuild_rules = false;

	/* Signal Suricata to "live reload" the rules */
	suricata_reload_config($a_rule[$id]);

	// We have saved changes and done a soft restart, so clear "dirty" flag
	clear_subsystem_dirty('suricata_rules');

	// Sync to configured CARP slaves if any are enabled
	suricata_sync_on_changes();
}

function build_cat_list() {
	global $categories, $a_rule, $id, $snortdownload, $emergingdownload, $etpro;

	$list = array();

	$files = $categories;

	if ($a_rule[$id]['ips_policy_enable'] == 'on')
		$files[] = "IPS Policy - " . ucfirst($a_rule[$id]['ips_policy']);

	if ($a_rule[$id]['autoflowbitrules'] == 'on')
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
$pgtitle = array(gettext("Suricata"), gettext("Interface ") . $if_friendly, gettext("Rules: ") . $currentruleset);
include_once("head.inc");
?>

<form action="/suricata/suricata_rules.php" method="post" enctype="multipart/form-data" class="form-horizontal" name="iform" id="iform">
<input type='hidden' name='id' id='id' value='<?=$id;?>'/>
<input type='hidden' name='openruleset' id='openruleset' value='<?=$currentruleset;?>'/>
<input type='hidden' name='sid' id='sid' value=''/>
<input type='hidden' name='gid' id='gid' value=''/>

<?php
if (is_subsystem_dirty('suricata_rules')) {
	print_apply_box(gettext("A change has been made to a rule state.") . "<br/>" . gettext("Click APPLY when finished to send the changes to the running configuration."));
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), true, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php?instance={$id}");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
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
$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/suricata/suricata_barnyard.php?id={$id}");
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
if ($currentruleset != 'custom.rules') {
	$group->add(new Form_Button(
		'',
		'View All',
		'javascript:wopen(\'/suricata/suricata_rules_edit.php?id=' . $id . '&openruleset=' . $currentruleset . '\',\'FileViewer\');',
		'fa-file-text-o'
	))->removeClass("btn-default")->addClass("btn-sm btn-success")->setAttribute('title', gettext("View raw text for all rules in selected category"));
}
$section->add($group);
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
	$msg .= '<a href="/suricata/suricata_rules_flowbits.php?id=' . $id . '" title="' . gettext('Add Suppress List entry for Flowbit Rule') . '">';
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
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Rule Signature ID (SID) Enable/Disable Overrides")?></h2></div>
	<div class="panel-body table-responsive">

		<div class="content table-responsive">
			<table>
				<tbody>
					<tr>
						<td><b><?=gettext('Legend: ');?></b></td>
						<td style="padding-left: 8px;"><i class="fa fa-check-circle-o text-success"></i></td><td style="padding-left: 4px;"><small><?=gettext('Default Enabled');?></small></td>
						<td style="padding-left: 8px;"><i class="fa fa-check-circle text-success"></i></td><td style="padding-left: 4px;"><small><?=gettext('Enabled by user');?></small></td>
						<td style="padding-left: 8px;"><i class="fa fa-adn text-success"></i></td><td style="padding-left: 4px;"><small><?=gettext('Auto-enabled by SID Mgmt');?></small></td>
						<td style="padding-left: 8px;"><i class="fa fa-adn text-warning"></i></td><td style="padding-left: 4px;"><small><?=gettext('Action and/or content modified by SID Mgmt');?></small></td>
					</tr>
					<tr>
						<td></td>
						<td style="padding-left: 8px;"><i class="fa fa-times-circle-o text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Default Disabled');?></small></td>
						<td style="padding-left: 8px;"><i class="fa fa-times-circle text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Disabled by user');?></small></td>
						<td style="padding-left: 8px;"><i class="fa fa-adn text-danger"></i></td><td style="padding-left: 4px;"><small><?=gettext('Auto-disabled by SID Mgmt');?></small></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>

		<table  style="table-layout: fixed; width: 100%;" class="table table-striped table-hover table-condensed">
			<colgroup>
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
			   <tr>
				<th><?=gettext("State");?></th>
				<th><?=gettext("GID");?></th>
				<th><?=gettext("SID");?></th>
				<th><?=gettext("Proto");?></th>
				<th><?=gettext("Source");?></th>
				<th><?=gettext("SPort");?></th>
				<th><?=gettext("Destination");?></th>
				<th><?=gettext("DPort");?></th>
				<th><?=gettext("Message");?></th>
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

							// Determine which icons to display in the first column for rule state.
							// See if the rule is auto-managed by the SID MGMT tab feature
							if ($v['managed'] == 1) {
								if ($v['disabled'] == 1 && $v['state_toggled'] == 1) {
									$textss = '<span class="text-muted">';
									$textse = '</span>';
									$iconb_class = 'class="fa fa-adn text-danger text-left"';
									$title = gettext("Auto-disabled by settings on SID Mgmt tab");
								}
								elseif ($v['disabled'] == 0 && $v['state_toggled'] == 1) {
									$textss = $textse = "";
									$iconb_class = 'class="fa fa-adn text-success text-left"';
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
								$iconb_class = 'class="fa fa-times-circle text-danger text-left"';
								$title = gettext("Disabled by user. Click to toggle to enabled state");
							}
							// See if the rule is in our list of user-enabled overrides
							elseif (isset($enablesid[$gid][$sid])) {
								$textss = $textse = "";
								$enable_cnt++;
								$user_enable_cnt++;
								$iconb_class = 'class="fa fa-check-circle text-success text-left"';
								$title = gettext("Enabled by user. Click to toggle to disabled state");
							}
							// These last two checks handle normal cases of default-enabled or default disabled rules
							// with no user overrides.
							elseif (($v['disabled'] == 1) && ($v['state_toggled'] == 0) && (!isset($enablesid[$gid][$sid]))) {
								$textss = "<span class=\"text-muted\">";
								$textse = "</span>";
								$disable_cnt++;
								$iconb_class = 'class="fa fa-times-circle-o text-danger text-left"';
								$title = gettext("Disabled by default. Click to toggle to enabled state");
							}
							elseif ($v['disabled'] == 0 && $v['state_toggled'] == 0) {
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
							$message = suricata_get_msg($v['rule']); // description field
							$sid_tooltip = gettext("View the raw text for this rule");
				?>
							<tr class="text-nowrap">
								<td><?=$textss; ?>
									<a id="rule_<?=$gid; ?>_<?=$sid; ?>" href="#" onClick="toggleRule('<?=$sid; ?>', '<?=$gid; ?>');" 
									<?=$iconb_class; ?> title="<?=$title; ?>"></a><?=$textse; ?>
				<?php if ($v['managed'] == 1 && $v['modified'] == 1) : ?>
								<i class="fa fa-adn text-warning text-left" title="<?=gettext('Action or content modified by settings on SID Mgmt tab'); ?>"></i><?=$textse; ?>
				<?php endif; ?>
								</td>
							       <td ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
									<?=$textss . $gid . $textse;?>
							       </td>
							       <td ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
									<a href="javascript: void(0)" 
									onclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');" 
									title="<?=$sid_tooltip;?>"><?=$textss . $sid . $textse;?></a>
							       </td>
							       <td ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
									<?=$textss . $protocol . $textse;?>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
									<?=$srcspan . $source;?></span>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
									<?=$srcprtspan . $source_port;?></span>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
									<?=$dstspan . $destination;?></span>
							       </td>
							       <td style="text-overflow: ellipsis; overflow: hidden; white-space:no-wrap" ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
								       <?=$dstprtspan . $destination_port;?></span>
							       </td>
								<td style="word-wrap:break-word; white-space:normal" ondblclick="showRuleContents('<?=$gid;?>','<?=$sid;?>');">
									<?=$textss . $message . $textse;?>
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
// Create a Modal object to display text of user-clicked rules
$form = new Form(FALSE);
$modal = new Modal('View Rules Text', 'rulesviewer', 'large', 'Close');
$modal->addInput(new Form_StaticText (
	'Category',
	'<div class="text-left" id="modal_rule_category"></div>'
));
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

function toggleRule(sid, gid) {
	$('#sid').val(sid);
	$('#gid').val(gid);
	$('#openruleset').val($('#selectbox').val());
	$('<input name="toggle" value="1" />').appendTo($('#iform'));
	$('#iform').submit();
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
		$('#rulesviewer').modal('show');
		$('#modal_rule_category').html($('#selectbox').val());

		$.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>",
			{
				type: 'post',
				data: {
					sid:         sid,
					gid:         gid,
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

	<?php if (!empty($anchor)): ?>
		// Scroll the last enabled/disabled SID into view
		window.location.hash = "<?=$anchor; ?>";
		window.scrollBy(0,-60); 
	<?php endif;?>

});
//]]>
</script>

<?php include("foot.inc"); ?>

