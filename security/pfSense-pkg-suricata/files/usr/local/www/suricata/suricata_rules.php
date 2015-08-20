<?php
/*
 * suricata_rules.php
 *
 * Significant portions of this code are based on original work done
 * for the Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
 * All rights reserved.
 *
 * Adapted for Suricata by:
 * Copyright (C) 2014 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:

 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
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

if ($_POST['toggle'] && is_numeric($_POST['sid']) && is_numeric($_POST['gid']) && !empty($rules_map)) {

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

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');

	write_config("Suricata pkg: disabled all rules in category {$currentruleset} for {$a_rule[$id]['interface']}.");
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

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');

	write_config("Suricata pkg: enable all rules in category {$currentruleset} for {$a_rule[$id]['interface']}.");
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

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');

	write_config("Suricata pkg: remove enablesid/disablesid changes for category {$currentruleset} on {$a_rule[$id]['interface']}.");
}
elseif ($_POST['resetall'] && !empty($rules_map)) {

	// Remove all modified SIDs from config.xml and save the changes.
	unset($a_rule[$id]['rule_sid_on']);
	unset($a_rule[$id]['rule_sid_off']);

	// We changed a rule state, remind user to apply the changes
	mark_subsystem_dirty('suricata_rules');

	/* Update the config.xml file. */
	write_config("Suricata pkg: remove all enablesid/disablesid changes for {$a_rule[$id]['interface']}.");
}
elseif ($_POST['clear']) {
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
elseif ($_POST['cancel']) {
	$pconfig['customrules'] = base64_decode($a_rule[$id]['customrules']);
	clear_subsystem_dirty('suricata_rules');
}
elseif ($_POST['save']) {
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
elseif ($_POST['apply']) {

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

include_once("head.inc");

$if_friendly = convert_friendly_interface_to_friendly_descr($pconfig['interface']);
$pgtitle = gettext("Suricata: Interface {$if_friendly} - Rules: {$currentruleset}");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>

<form action='/suricata/suricata_rules.php' method='post' name='iform' id='iform'>
<input type='hidden' name='id' id='id' value='<?=$id;?>'/>
<input type='hidden' name='openruleset' id='openruleset' value='<?=$currentruleset;?>'/>
<input type='hidden' name='sid' id='sid' value=''/>
<input type='hidden' name='gid' id='gid' value=''/>

<?php if (is_subsystem_dirty('suricata_rules')): ?><p>
<?php print_info_box_np(gettext("A change has been made to a rule state.") . "<br/>" . gettext("Click APPLY when finished to send the changes to the running configuration."));?>
<?php endif; ?>
<?php
/* Display error or save messages if present */
if ($input_errors) {
        print_input_errors($input_errors);
}

if ($savemsg) {
        print_info_box($savemsg);
}
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tbody>
	<tr><td>
	<?php
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
		echo '</td></tr>';
		echo '<tr><td class="tabnavtbl">';
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
	?>
	</td></tr>
	<tr><td><div id="mainarea">
		<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="4" cellspacing="0">
			<tbody>
			<tr>
				<td class="listtopic"><?php echo gettext("Available Rule Categories"); ?></td>
			</tr>
			<tr>
				<td class="vncell" height="30px"><strong><?php echo gettext("Category:"); ?></strong>&nbsp;&nbsp;
					<select id="selectbox" name="selectbox" class="formselect" onChange="go();">
					<option value='custom.rules'>custom.rules</option>
					<?php
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
							echo "<option value='{$value}' ";
							if ($value == $currentruleset)
								echo "selected";
							echo ">{$value}</option>\n";
							}
					?>
					</select>&nbsp;&nbsp;&nbsp;<?php echo gettext("Select the rule category to view"); ?>
				</td>
			</tr>

		<?php if ($currentruleset == 'custom.rules'): ?>
			<tr>
				<td class="listtopic"><?php echo gettext("Defined Custom Rules"); ?></td>
			</tr>
			<tr>
				<td valign="top" class="vtable">
					<textarea wrap="soft" cols="90" rows="40" name="customrules"><?=$pconfig['customrules'];?></textarea>
				</td>
			</tr>
			<tr>
				<td>
					<input name="save" type="submit" class="formbtn" id="save" value="<?php echo gettext(" Save "); ?>" title=" <?php echo gettext("Save custom rules"); ?>"/>&nbsp;&nbsp;
					<input name="cancel" type="submit" class="formbtn" id="cancel" value="<?php echo gettext("Cancel"); ?>" title="<?php echo gettext("Cancel all changes made prior to last save"); ?>"/>&nbsp;&nbsp;
					<input name="clear" type="submit" class="formbtn" id="clear" value="<?php echo gettext("Clear"); ?>" onclick="return confirm('<?php echo gettext("This will erase all custom rules for the interface.  Are you sure?"); ?>')" title="<?php echo gettext("Deletes all custom rules"); ?>"/>
				</td>
			</tr>
		<?php else: ?>
			<tr>
				<td class="listtopic"><?php echo gettext("Rule Signature ID (SID) Enable/Disable Overrides"); ?></td>
			</tr>
			<tr>
				<td class="vncell">
					<table width="100%" align="center" border="0" cellpadding="0" cellspacing="0">
						<tbody>
						<tr>
							<td rowspan="5" width="48%" valign="middle"><input type="submit" name="apply" id="apply" value="<?php echo gettext("Apply"); ?>" class="formbtn" 
							title="<?php echo gettext("Click to rebuild the rules with your changes"); ?>"/><br/><br/>
							<span class="vexpl"><span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . 
							gettext("When finished, click APPLY to send any SID enable/disable changes made on this tab to the running Suricata process."); ?></span></td>
							<td class="vexpl" valign="middle"><?php echo "<input type='image' name='resetcategory[]' 
							src=\"../themes/{$g['theme']}/images/icons/icon_x.gif\" width=\"15\" height=\"15\" 
							onmouseout='this.src=\"../themes/{$g['theme']}/images/icons/icon_x.gif\"' 
							onmouseover='this.src=\"../themes/{$g['theme']}/images/icons/icon_x_mo.gif\"' border='0' 
							title='" . gettext("Click to remove enable/disable changes for rules in the selected category only") . "'/>"?>
							&nbsp;&nbsp;<?php echo gettext("Remove Enable/Disable changes in the current Category"); ?></td>
						</tr>
						<tr>
							<td class="vexpl" valign="middle"><?php echo "<input type='image' name='resetall[]'  
							src=\"../themes/{$g['theme']}/images/icons/icon_x.gif\" width=\"15\" height=\"15\" 
							onmouseout='this.src=\"../themes/{$g['theme']}/images/icons/icon_x.gif\"' 
							onmouseover='this.src=\"../themes/{$g['theme']}/images/icons/icon_x_mo.gif\"' border='0' 
							title='" . gettext("Click to remove all enable/disable changes for rules in all categories") . "'/>"?>
							&nbsp;&nbsp;<?php echo gettext("Remove all Enable/Disable changes in all Categories"); ?></td>
						</tr>
						<tr>
							<td class="vexpl" valign="middle"><?php echo "<input type='image' name='disable_all[]'  
							src=\"../themes/{$g['theme']}/images/icons/icon_x.gif\" width=\"15\" height=\"15\" 
							onmouseout='this.src=\"../themes/{$g['theme']}/images/icons/icon_x.gif\"' 
							onmouseover='this.src=\"../themes/{$g['theme']}/images/icons/icon_x_mo.gif\"' border='0' 
							title='" . gettext("Click to disable all rules in the selected category") . "'/>"?>
							&nbsp;&nbsp;<?php echo gettext("Disable all rules in the current Category"); ?></td>
						</tr>
						<tr>
							<td class="vexpl" valign="middle"><?php echo "<input type='image' name='enable_all[]'  
							src=\"../themes/{$g['theme']}/images/icons/icon_plus.gif\" width=\"15\" height=\"15\" 
							onmouseout='this.src=\"../themes/{$g['theme']}/images/icons/icon_plus.gif\"' 
							onmouseover='this.src=\"../themes/{$g['theme']}/images/icons/icon_plus_mo.gif\"' border='0' 
							title='" . gettext("Click to enable all rules in the selected category") . "'/>"?>
							&nbsp;&nbsp;<?php echo gettext("Enable all rules in the current Category"); ?></td>
						</tr>
						<tr>
							<td class="vexpl" valign="middle"><a href="javascript: void(0)" 
							onclick="wopen('suricata_rules_edit.php?id=<?=$id;?>&openruleset=<?=$currentruleset;?>','FileViewer',800,600)">
							<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_service_restart.gif" width="15" height="15" <?php
							echo "onmouseover='this.src=\"../themes/{$g['theme']}/images/icons/icon_services_restart_mo.gif\"' 
							onmouseout='this.src=\"../themes/{$g['theme']}/images/icons/icon_service_restart.gif\"' ";?>				
							title="<?php echo gettext("Click to view full text of all the category rules"); ?>" width="17" height="17" border="0"></a>
							&nbsp;&nbsp;<?php echo gettext("View full file contents for the current Category"); ?></td>
						</tr>
						<?php if ($currentruleset == 'Auto-Flowbit Rules'): ?>
						<tr>
							<td colspan="3">&nbsp;</td>
						</tr>
						<tr>
							<td colspan="3" class="vexpl" align="center"><?php echo "<span class=\"red\"><b>" . gettext("WARNING: ") . "</b></span>" . 
							gettext("You should not disable flowbit rules!  Add Suppress List entries for them instead by ") . 
							"<a href='suricata_rules_flowbits.php?id={$id}' title=\"" . gettext("Add Suppress List entry for Flowbit Rule") . "\">" . 
							gettext("clicking here") . ".</a>";?></td>
						</tr>
						<?php endif;?>
						</tbody>
					</table>
				</td>
			</tr>
			<tr>
				<td class="listtopic"><?php echo gettext("Selected Category's Rules"); ?></td>
			</tr>
			<tr>
				<td>
					<table id="myTable" class="sortable" style="table-layout: fixed;" width="100%" border="0" cellpadding="0" cellspacing="0">
						<colgroup>
							<col width="16" align="center" valign="middle">
							<col width="6%" align="center" axis="number">
							<col width="9%" align="center" axis="number">
							<col width="52" align="center" axis="string">
							<col width="14%" align="center" axis="string">
							<col width="10%" align="center" axis="string">
							<col width="14%" align="center" axis="string">
							<col width="10%" align="center" axis="string">
							<col axis="string">
						</colgroup>
						<thead>
						   <tr class="sortableHeaderRowIdentifier">
							<th class="list sorttable_nosort">&nbsp;</th>
							<th class="listhdrr"><?php echo gettext("GID"); ?></th>
							<th class="listhdrr"><?php echo gettext("SID"); ?></th>
							<th class="listhdrr"><?php echo gettext("Proto"); ?></th>
							<th class="listhdrr"><?php echo gettext("Source"); ?></th>
							<th class="listhdrr"><?php echo gettext("SPort"); ?></th>
							<th class="listhdrr"><?php echo gettext("Destination"); ?></th>
							<th class="listhdrr"><?php echo gettext("DPort"); ?></th>
							<th class="listhdrr"><?php echo gettext("Message"); ?></th>
						   </tr>
						</thead>
						<tbody>

					<?php
						$counter = $enable_cnt = $disable_cnt = $user_enable_cnt = $user_disable_cnt = $managed_count = 0;
						foreach ($rules_map as $k1 => $rulem) {
							foreach ($rulem as $k2 => $v) {
								$sid = suricata_get_sid($v['rule']);
								$gid = suricata_get_gid($v['rule']);
								$ruleset = $currentruleset;
								$style = "";

								if ($v['managed'] == 1) {
									if ($v['disabled'] == 1) {
										$textss = "<span class=\"gray\">";
										$textse = "</span>";
										$style= "style=\"opacity: 0.4; filter: alpha(opacity=40);\"";
										$title = gettext("Auto-disabled by settings on SID Mgmt tab");
									}
									else {
										$textss = $textse = "";
										$ruleset = "suricata.rules";
										$title = gettext("Auto-managed by settings on SID Mgmt tab");
									}
									$iconb = "icon_advanced.gif";
									$managed_count++;
								}
								elseif (isset($disablesid[$gid][$sid])) {
									$textss = "<span class=\"gray\">";
									$textse = "</span>";
									$iconb = "icon_reject_d.gif";
									$disable_cnt++;
									$user_disable_cnt++;
									$title = gettext("Disabled by user. Click to toggle to enabled state");
								}
								elseif (($v['disabled'] == 1) && (!isset($enablesid[$gid][$sid]))) {
									$textss = "<span class=\"gray\">";
									$textse = "</span>";
									$iconb = "icon_block_d.gif";
									$disable_cnt++;
									$title = gettext("Disabled by default. Click to toggle to enabled state");
								}
								elseif (isset($enablesid[$gid][$sid])) {
									$textss = $textse = "";
									$iconb = "icon_reject.gif";
									$enable_cnt++;
									$user_enable_cnt++;
									$title = gettext("Enabled by user. Click to toggle to disabled state");
								}
								else {
									$textss = $textse = "";
									$iconb = "icon_block.gif";
									$enable_cnt++;
									$title = gettext("Enabled by default. Click to toggle to disabled state");
								}

								// Pick off the first section of the rule (prior to the start of the MSG field),
								// and then use a REGX split to isolate the remaining fields into an array.
								$tmp = substr($v['rule'], 0, strpos($v['rule'], "("));
								$tmp = trim(preg_replace('/^\s*#+\s*/', '', $tmp));
								$rule_content = preg_split('/[\s]+/', $tmp);

								// Create custom <span> tags for some of the fields so we can 
								// have a "title" attribute for tooltips to show the full string.
								$srcspan = add_title_attribute($textss, $rule_content[2]);
								$srcprtspan = add_title_attribute($textss, $rule_content[3]);
								$dstspan = add_title_attribute($textss, $rule_content[5]);
								$dstprtspan = add_title_attribute($textss, $rule_content[6]);
								$protocol = $rule_content[1]; //protocol field
								$source = $rule_content[2]; //source field
								$source_port = $rule_content[3]; //source port field
								$destination = $rule_content[5]; //destination field
								$destination_port = $rule_content[6]; //destination port field
								$message = suricata_get_msg($v['rule']);
								$sid_tooltip = gettext("View the raw text for this rule");

								echo "<tr><td class=\"listt\" style=\"align:center;\" valign=\"middle\">{$textss}";

								if ($v['managed'] == 1) {
									echo "<img {$style} src=\"../themes/{$g['theme']}/images/icons/{$iconb}\" width=\"11\" height=\"11\" border=\"0\" 
									title='{$title}'/>{$textse}";
								}
								else {
									echo "<a id=\"rule_{$gid}_{$sid}\" href='#'><input type=\"image\" onClick=\"document.getElementById('sid').value='{$sid}';
									document.getElementById('gid').value='{$gid}';\" 
									src=\"../themes/{$g['theme']}/images/icons/{$iconb}\" width=\"11\" height=\"11\" border=\"0\"  
									title='{$title}' name=\"toggle[]\"/></a>{$textse}";
								}
							       echo "</td>

							       <td class=\"listr\" style=\"text-align:center;\" ondblclick=\"wopen('suricata_rules_edit.php?id={$id}&openruleset={$ruleset}&sid={$sid}&gid={$gid}','FileViewer',800,600);\">
									{$textss}{$gid}{$textse}
							       </td>
							       <td class=\"listr\" style=\"text-align:center;\" ondblclick=\"wopen('suricata_rules_edit.php?id={$id}&openruleset={$ruleset}&sid={$sid}&gid={$gid}','FileViewer',800,600);\">
									<a href=\"javascript: void(0)\" 
									onclick=\"wopen('suricata_rules_edit.php?id={$id}&openruleset={$ruleset}&sid={$sid}&gid={$gid}','FileViewer',800,600);\" 
									title='{$sid_tooltip}'>{$textss}{$sid}{$textse}</a>
							       </td>
							       <td class=\"listr\" style=\"text-align:center;\" ondblclick=\"wopen('suricata_rules_edit.php?id={$id}&openruleset={$ruleset}&sid={$sid}&gid={$gid}','FileViewer',800,600);\">
									{$textss}{$protocol}{$textse}
							       </td>
							       <td class=\"listr ellipsis\" nowrap style=\"text-align:center;\" ondblclick=\"wopen('suricata_rules_edit.php?id={$id}&openruleset={$ruleset}&sid={$sid}&gid={$gid}','FileViewer',800,600);\">
									{$srcspan}{$source}</span>
							       </td>
							       <td class=\"listr ellipsis\" nowrap style=\"text-align:center;\" ondblclick=\"wopen('suricata_rules_edit.php?id={$id}&openruleset={$ruleset}&sid={$sid}&gid={$gid}','FileViewer',800,600);\">
									{$srcprtspan}{$source_port}</span>
							       </td>
							       <td class=\"listr ellipsis\" nowrap style=\"text-align:center;\" ondblclick=\"wopen('suricata_rules_edit.php?id={$id}&openruleset={$ruleset}&sid={$sid}&gid={$gid}','FileViewer',800,600);\">
									{$dstspan}{$destination}</span>
							       </td>
							       <td class=\"listr ellipsis\" nowrap style=\"text-align:center;\" ondblclick=\"wopen('suricata_rules_edit.php?id={$id}&openruleset={$ruleset}&sid={$sid}&gid={$gid}','FileViewer',800,600);\">
								       {$dstprtspan}{$destination_port}</span>
							       </td>
								<td class=\"listbg\" style=\"word-wrap:break-word; whitespace:pre-line;\" ondblclick=\"wopen('suricata_rules_edit.php?id={$id}&openruleset={$ruleset}&sid={$sid}&gid={$gid}','FileViewer',800,600);\"> 
									{$textss}{$message}{$textse}
							       </td>
							</tr>";
								$counter++;
							}
						}
						unset($rulem, $v); ?>
					    </tbody>
					</table>
				</td>
			</tr>
			<tr>
				<td>
					<table width="100%" border="0" cellspacing="0" cellpadding="1">
						<tbody>
						<tr>
							<td width="16"></td>
							<td class="vexpl" height="35" valign="top">
							<strong><?php echo gettext("---  Category Rules Summary  ---") . "</strong><br/>" . 
							gettext("Total Rules: {$counter}") . "&nbsp;&nbsp;&nbsp;&nbsp;" . 
							gettext("Enabled: {$enable_cnt}") . "&nbsp;&nbsp;&nbsp;&nbsp;" . 
							gettext("Disabled: {$disable_cnt}") . "&nbsp;&nbsp;&nbsp;&nbsp;" . 
							gettext("User Enabled: {$user_enable_cnt}") . "&nbsp;&nbsp;&nbsp;&nbsp;" . 
							gettext("User Disabled: {$user_disable_cnt}") . "&nbsp;&nbsp;&nbsp;&nbsp;" . 
							gettext("Auto-Managed: {$managed_count}"); ?></td>
						</tr>
						<tr>
							<td width="16"><img src="../themes/<?= $g['theme']; ?>/images/icons/icon_block.gif"
								width="11" height="11"></td>
							<td><?php echo gettext("Rule default is Enabled"); ?></td>
						</tr>
						<tr>
							<td width="16"><img src="../themes/<?= $g['theme']; ?>/images/icons/icon_block_d.gif"
								width="11" height="11"></td>
							<td nowrap><?php echo gettext("Rule default is Disabled"); ?></td>
						</tr>
						<tr>
							<td width="16"><img src="../themes/<?= $g['theme']; ?>/images/icons/icon_reject.gif"
								width="11" height="11"></td>
							<td nowrap><?php echo gettext("Rule changed to Enabled by user"); ?></td>
						</tr>
						<tr>
							<td width="16"><img src="../themes/<?= $g['theme']; ?>/images/icons/icon_reject_d.gif"
								width="11" height="11"></td>
							<td nowrap><?php echo gettext("Rule changed to Disabled by user"); ?></td>
						</tr>
					<?php if (!empty($cat_mods)): ?>
						<tr>
							<td width="16"><img src="../themes/<?= $g['theme']; ?>/images/icons/icon_advanced.gif"
								width="11" height="11"></td>
							<td nowrap><?php echo gettext("Rule auto-enabled by files configured on SID Mgmt tab"); ?></td>
						</tr>
						<tr>
							<td width="16"><img style="opacity: 0.4; filter: alpha(opacity=40);" src="../themes/<?= $g['theme']; ?>/images/icons/icon_advanced.gif"
								width="11" height="11"></td>
							<td nowrap><?php echo gettext("Rule auto-disabled by files configured on SID Mgmt tab"); ?></td>
						</tr>
					<?php endif; ?>
						</tbody>
					</table>
				</td>
			</tr>
		<?php endif;?>
			</tbody>
		</table>
	</div>
	</td>
	</tr>
	</tbody>
</table>
</form>
<script language="javascript" type="text/javascript">
function go()
{
    var box = document.getElementById("selectbox");
    var ruleset = box.options[box.selectedIndex].value;
    if (ruleset) 
	document.getElementById("openruleset").value = ruleset;
    document.getElementById("iform").submit();
}

function wopen(url, name, w, h)
{
// Fudge factors for window decoration space.
// In my tests these work well on all platforms & browsers.
    w += 32;
    h += 96;
    var win = window.open(url,
        name, 
       'width=' + w + ', height=' + h + ', ' +
       'location=no, menubar=no, ' +
       'status=no, toolbar=no, scrollbars=yes, resizable=yes');
    win.resizeTo(w, h);
    win.focus();
}

<?php if (!empty($anchor)): ?>
    // Scroll the last enabled/disabled SID into view
    window.location.hash = "<?=$anchor; ?>";
    window.scrollBy(0,-60); 

<?php endif;?>
</script>
<?php include("fend.inc"); ?>

</body>
</html>
