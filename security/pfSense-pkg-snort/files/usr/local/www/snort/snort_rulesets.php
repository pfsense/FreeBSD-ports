<?php
/*
 * snort_rulesets.php
 *
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya
 * Copyright (C) 2011 Ermal Luci
 * Copyright (C) 2014 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
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
require_once("/usr/local/pkg/snort/snort.inc");

global $g, $rebuild_rules;

$snortdir = SNORTDIR;
$flowbit_rules_file = FLOWBITS_FILENAME;

if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}
$a_nat = &$config['installedpackages']['snortglobal']['rule'];

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
        header("Location: /snort/snort_interfaces.php");
        exit;
}

if (isset($id) && $a_nat[$id]) {
	$pconfig['enable'] = $a_nat[$id]['enable'];
	$pconfig['interface'] = $a_nat[$id]['interface'];
	$pconfig['rulesets'] = $a_nat[$id]['rulesets'];
	if (empty($a_nat[$id]['autoflowbitrules']))
		$pconfig['autoflowbitrules'] = 'on';
	else
		$pconfig['autoflowbitrules'] = $a_nat[$id]['autoflowbitrules'] == 'on' ? 'on' : 'off';;
	$pconfig['ips_policy_enable'] = $a_nat[$id]['ips_policy_enable'] == 'on' ? 'on' : 'off';;
	$pconfig['ips_policy'] = $a_nat[$id]['ips_policy'];
}

$if_real = get_real_interface($pconfig['interface']);
$snort_uuid = $a_nat[$id]['uuid'];
$snortdownload = $config['installedpackages']['snortglobal']['snortdownload'] == 'on' ? 'on' : 'off';
$emergingdownload = $config['installedpackages']['snortglobal']['emergingthreats'] == 'on' ? 'on' : 'off';
$etpro = $config['installedpackages']['snortglobal']['emergingthreats_pro'] == 'on' ? 'on' : 'off';
$snortcommunitydownload = $config['installedpackages']['snortglobal']['snortcommunityrules'] == 'on' ? 'on' : 'off';

$no_emerging_files = false;
$no_snort_files = false;
$no_community_files = false;

/* Test rule categories currently downloaded to $SNORTDIR/rules and set appropriate flags */
if (($etpro == 'off' || empty($etpro)) && $emergingdownload == 'on') {
	$test = glob("{$snortdir}/rules/" . ET_OPEN_FILE_PREFIX . "*.rules");
	$et_type = "ET Open";
}
elseif ($etpro == 'on' && ($emergingdownload == 'off' || empty($emergingdownload))) {
	$test = glob("{$snortdir}/rules/" . ET_PRO_FILE_PREFIX . "*.rules");
	$et_type = "ET Pro";
}
if (empty($test))
	$no_emerging_files = true;
$test = glob("{$snortdir}/rules/" . VRT_FILE_PREFIX . "*.rules");
if (empty($test))
	$no_snort_files = true;
if (!file_exists("{$snortdir}/rules/" . GPL_FILE_PREFIX . "community.rules"))
	$no_community_files = true;

if (($snortdownload == 'off') || ($a_nat[$id]['ips_policy_enable'] != 'on'))
	$policy_select_disable = "disabled";

// If a Snort VRT policy is enabled and selected, remove all Snort VRT
// rules from the configured rule sets to allow automatic selection.
if ($a_nat[$id]['ips_policy_enable'] == 'on') {
	if (isset($a_nat[$id]['ips_policy'])) {
		$disable_vrt_rules = "disabled";
		$enabled_sets = explode("||", $a_nat[$id]['rulesets']);

		foreach ($enabled_sets as $k => $v) {
			if (substr($v, 0, 6) == "snort_")
				unset($enabled_sets[$k]);
		}
		$a_nat[$id]['rulesets'] = implode("||", $enabled_sets);
	}
}
else
	$disable_vrt_rules = "";

if (!empty($a_nat[$id]['rulesets']))
	$enabled_rulesets_array = explode("||", $a_nat[$id]['rulesets']);
else
	$enabled_rulesets_array = array();

if ($_POST["save"]) {

	if ($_POST['ips_policy_enable'] == "on") {
		$a_nat[$id]['ips_policy_enable'] = 'on';
		$a_nat[$id]['ips_policy'] = $_POST['ips_policy'];
	}
	else {
		$a_nat[$id]['ips_policy_enable'] = 'off';
		unset($a_nat[$id]['ips_policy']);
	}

	$enabled_items = "";
	if (is_array($_POST['toenable']))
		$enabled_items = implode("||", $_POST['toenable']);
	else
		$enabled_items = $_POST['toenable'];

	$a_nat[$id]['rulesets'] = $enabled_items;

	if ($_POST['autoflowbits'] == "on")
		$a_nat[$id]['autoflowbitrules'] = 'on';
	else {
		$a_nat[$id]['autoflowbitrules'] = 'off';
		if (file_exists("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/{$flowbit_rules_file}"))
			unlink_if_exists("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/{$flowbit_rules_file}");
	}

	write_config("Snort pkg: save enabled rule categories for {$a_nat[$id]['interface']}.");

	/*************************************************/
	/* Update the snort conf file and rebuild the    */
	/* rules for this interface.                     */
	/*************************************************/
	$rebuild_rules = true;
	conf_mount_rw();
	snort_generate_conf($a_nat[$id]);
	conf_mount_ro();
	$rebuild_rules = false;

	/* Soft-restart Snort to live-load new rules */
	snort_reload_config($a_nat[$id]);

	$pconfig = $_POST;
	$enabled_rulesets_array = explode("||", $enabled_items);
	if (snort_is_running($snort_uuid, $if_real))
		$savemsg = gettext("Snort is 'live-reloading' the new rule set.");

	// Sync to configured CARP slaves if any are enabled
	snort_sync_on_changes();
}

if ($_POST['unselectall']) {
	$a_nat[$id]['rulesets'] = "";

	if ($_POST['ips_policy_enable'] == "on") {
		$a_nat[$id]['ips_policy_enable'] = 'on';
		$a_nat[$id]['ips_policy'] = $_POST['ips_policy'];
	}
	else {
		$a_nat[$id]['ips_policy_enable'] = 'off';
		unset($a_nat[$id]['ips_policy']);
	}

	$pconfig['autoflowbits'] = $_POST['autoflowbits'];
	$pconfig['ips_policy_enable'] = $_POST['ips_policy_enable'];
	$pconfig['ips_policy'] = $_POST['ips_policy'];
	$enabled_rulesets_array = array();

	$savemsg = gettext("All rule categories have been de-selected.  ");
	if ($pconfig['ips_policy_enable'] == 'on')
		$savemsg .= gettext("Only the rules included in the selected IPS Policy will be used.");
	else
		$savemsg .= gettext("There currently are no inspection rules enabled for this Snort instance!");
}

if ($_POST['selectall']) {
	if ($_POST['ips_policy_enable'] == "on") {
		$a_nat[$id]['ips_policy_enable'] = 'on';
		$a_nat[$id]['ips_policy'] = $_POST['ips_policy'];
	}
	else {
		$a_nat[$id]['ips_policy_enable'] = 'off';
		unset($a_nat[$id]['ips_policy']);
	}

	$pconfig['autoflowbits'] = $_POST['autoflowbits'];
	$pconfig['ips_policy_enable'] = $_POST['ips_policy_enable'];
	$pconfig['ips_policy'] = $_POST['ips_policy'];

	$enabled_rulesets_array = array();

	if ($emergingdownload == 'on') {
		$files = glob("{$snortdir}/rules/" . ET_OPEN_FILE_PREFIX . "*.rules");
		foreach ($files as $file)
			$enabled_rulesets_array[] = basename($file);
	}
	elseif ($etpro == 'on') {
		$files = glob("{$snortdir}/rules/" . ET_PRO_FILE_PREFIX . "*.rules");
		foreach ($files as $file)
			$enabled_rulesets_array[] = basename($file);
	}

	if ($snortcommunitydownload == 'on') {
		$files = glob("{$snortdir}/rules/" . GPL_FILE_PREFIX . "community.rules");
		foreach ($files as $file)
			$enabled_rulesets_array[] = basename($file);
	}

	/* Include the Snort VRT rules only if enabled and no IPS policy is set */
	if ($snortdownload == 'on' && $a_nat[$id]['ips_policy_enable'] == 'off') {
		$files = glob("{$snortdir}/rules/" . VRT_FILE_PREFIX . "*.rules");
		foreach ($files as $file)
			$enabled_rulesets_array[] = basename($file);
	}
}

// Get any automatic rule category enable/disable modifications
// if auto-SID Mgmt is enabled.
$cat_mods = snort_sid_mgmt_auto_categories($a_nat[$id], FALSE);

// Enable the VIEW button for auto-flowbits file if we have a valid flowbits file
if ($a_nat[$id]['autoflowbitrules'] == 'on') {
	if (file_exists("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/{$flowbit_rules_file}") &&
	    filesize("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/{$flowbit_rules_file}") > 0) {
		$btn_view_flowb_rules = " title=\"" . gettext("View flowbit-required rules") . "\"";
	}
	else
		$btn_view_flowb_rules = " disabled";
}
else
	$btn_view_flowb_rules = " disabled";

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']);
$pgtitle = gettext("Snort: Interface {$if_friendly} - Categories");
include_once("head.inc");
?>

<body link="#000000" vlink="#000000" alink="#000000">

<?php
include("fbegin.inc"); 

/* Display message */
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg);
}

?>

<form action="snort_rulesets.php" method="post" name="iform" id="iform">
<input type="hidden" name="id" id="id" value="<?=$id;?>" />
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php    
	$tab_array = array();
	$tab_array[0] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
	$tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
	$tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
	$tab_array[3] = array(gettext("Alerts"), false, "/snort/snort_alerts.php?instance={$id}");
	$tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[5] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
	$tab_array[6] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
	$tab_array[7] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
	$tab_array[8] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[9] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
	$tab_array[10] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
	display_top_tabs($tab_array, true);
	echo '</td></tr>';
	echo '<tr><td class="tabnavtbl">';
	$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
	$tab_array = array();
	$tab_array[] = array($menu_iface . gettext("Settings"), false, "/snort/snort_interfaces_edit.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Categories"), true, "/snort/snort_rulesets.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Rules"), false, "/snort/snort_rules.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Variables"), false, "/snort/snort_define_servers.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Preprocs"), false, "/snort/snort_preprocessors.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/snort/snort_barnyard.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/snort/snort_ip_reputation.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
	display_top_tabs($tab_array, true);
?>
</td></tr>
<tr>
	<td>
	<div id="mainarea">
	<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
<?php 
	$isrulesfolderempty = glob("{$snortdir}/rules/*.rules");
	$iscfgdirempty = array();
	if (file_exists("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/custom.rules"))
		$iscfgdirempty = (array)("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/custom.rules");
	if (empty($isrulesfolderempty)):
?>
		<tr>
			<td class="vexpl"><br/>
		<?php printf(gettext("# The rules directory is empty:  %s%s/rules%s"), '<strong>',$snortdir,'</strong>'); ?> <br/><br/>
		<?php echo gettext("Please go to the ") . '<a href="snort_download_updates.php"><strong>' . gettext("Updates") . 
			'</strong></a>' . gettext(" tab to download the rules configured on the ") . 
			'<a href="snort_interfaces_global.php"><strong>' . gettext("Global") . 
			'</strong></a>' . gettext(" tab."); ?>
			</td>
		</tr>
<?php else: 
	$colspan = 6;
	if ($emergingdownload != 'on')
		$colspan -= 2;
	if ($snortdownload != 'on')
		$colspan -= 4;

?>
		<tr>
			<td>
			<table width="100%" border="0"
				cellpadding="0" cellspacing="0">
			<tr>
				<td colspan="6" class="listtopic"><?php echo gettext("Automatic flowbit resolution"); ?><br/></td>
			</tr>
			<tr>
				<td colspan="6" valign="center" class="listn">
					<table width="100%" border="0" cellpadding="2" cellspacing="0">
					   <tr>
						<td width="15%" class="listn"><?php echo gettext("Resolve Flowbits"); ?></td>
						<td width="85%"><input name="autoflowbits" id="autoflowbitrules" type="checkbox" value="on" 
						<?php if ($pconfig['autoflowbitrules'] == "on") echo "checked"; ?>/>
						&nbsp;&nbsp;<span class="vexpl"><?php echo gettext("If checked, Snort will auto-enable rules required for checked flowbits.  ");
						echo gettext("The Default is "); ?><strong><?php echo gettext("Checked."); ?></strong></span></td>
					   </tr>
					   <tr>
						<td width="15%" class="vncell">&nbsp;</td>
						<td width="85%" class="vtable">
						<?php echo gettext("Snort will examine the enabled rules in your chosen " .
						"rule categories for checked flowbits.  Any rules that set these dependent flowbits will " .
						"be automatically enabled and added to the list of files in the interface rules directory."); ?><br/></td>
					   </tr>
					   <tr>
						<td width="15%" class="listn"><?php echo gettext("Auto Flowbit Rules"); ?></td>
						<td width="85%"><input type="button" class="formbtns" value="View" onclick="parent.location='snort_rules_flowbits.php?id=<?=$id;?>&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'" <?php echo $btn_view_flowb_rules; ?>/>
						&nbsp;&nbsp;<span class="vexpl"><?php echo gettext("Click to view auto-enabled rules required to satisfy flowbit dependencies"); ?></span></td>
					   </tr>
					   <tr>
						<td width="15%">&nbsp;</td>
						<td width="85%">
						<?php echo "<span class=\"red\"><strong>" . gettext("Note:  ") . "</strong></span>" . gettext("Auto-enabled rules generating unwanted alerts should have their GID:SID added to the Suppression List for the interface."); ?>
						<br/></td>
					   </tr>
					</table>
				</td>
			</tr>
			<tr>
				<td colspan="6" class="listtopic"><?php echo gettext("Snort VRT IPS Policy selection"); ?><br/></td>
			</tr>
			<tr>
				<td colspan="6" valign="center" class="listn">
					<table width="100%" border="0" cellpadding="2" cellspacing="0">
					   <tr>
						<td width="15%" class="listn"><?php echo gettext("Use IPS Policy"); ?></td>
						<td width="85%"><input name="ips_policy_enable" id="ips_policy_enable" type="checkbox" value="on" <?php if ($pconfig['ips_policy_enable'] == "on") echo "checked "; ?>
						<?php if ($snortdownload == "off") echo "disabled " ?> onClick="enable_change()"/>&nbsp;&nbsp;<span class="vexpl">
						<?php echo gettext("If checked, Snort will use rules from one of three pre-defined IPS policies."); ?></span></td>
					   </tr>
					   <tr>
						<td width="15%" class="vncell" id="ips_col1">&nbsp;</td>
						<td width="85%" class="vtable" id="ips_col2">
  						<?php echo "<span class=\"red\"><strong>" . gettext("Note:  ") . "</strong></span>" . gettext("You must enable download of the Snort VRT rules to enable and use this option."); ?>
						<?php echo gettext("Selecting this option disables manual selection of Snort VRT categories in the list below, " .
						"although Emerging Threats categories may still be selected if enabled on the Global Settings tab.  " .
						"These will be added to the pre-defined Snort IPS policy rules from the Snort VRT."); ?><br/></td>
					   </tr>
					   <tr id="ips_row1">
						<td width="15%" class="listn"><?php echo gettext("IPS Policy Selection"); ?></td>
						<td width="85%"><select name="ips_policy" class="formselect" <?=$policy_select_disable?> >
									<option value="connectivity" <?php if ($pconfig['ips_policy'] == "connected") echo "selected"; ?>><?php echo gettext("Connectivity"); ?></option>
									<option value="balanced" <?php if ($pconfig['ips_policy'] == "balanced") echo "selected"; ?>><?php echo gettext("Balanced"); ?></option>
									<option value="security" <?php if ($pconfig['ips_policy'] == "security") echo "selected"; ?>><?php echo gettext("Security"); ?></option>
								</select>
						&nbsp;&nbsp;<span class="vexpl"><?php echo gettext("Snort IPS policies are:  Connectivity, Balanced or Security."); ?></span></td>
					   </tr>
					   <tr id="ips_row2">
						<td width="15%">&nbsp;</td>
						<td width="85%">
						<?php echo gettext("Connectivity blocks most major threats with few or no false positives.  " . 
						"Balanced is a good starter policy.  It is speedy, has good base coverage level, and covers " . 
						"most threats of the day.  It includes all rules in Connectivity." . 
						"Security is a stringent policy.  It contains everything in the first two " .
						"plus policy-type rules such as Flash in an Excel file."); ?><br/></td>
					   </tr>
					</table>
				</td>
			</tr>
			<tr>
				<td colspan="6" class="listtopic"><?php echo gettext("Select the rulesets Snort will load at startup"); ?><br/></td>
			</tr>
			<tr>
				<td colspan="6">
					<table width="95%" style="margin-left: auto; margin-right: auto;" border="0" cellpadding="2" cellspacing="0">
						<tbody>
						<tr height="32px">
							<td style="vertical-align: middle;"><input value="Select All" class="formbtns" type="submit" name="selectall" id="selectall" title="<?php echo gettext("Add all to enforcing rules"); ?>"/></td>
							<td style="vertical-align: middle;"><input value="Unselect All" class="formbtns" type="submit" name="unselectall" id="unselectall" title="<?php echo gettext("Remove all from enforcing rules"); ?>"/></td>
							<td style="vertical-align: middle;"><input value=" Save " class="formbtns" type="submit" name="save" id="save" title="<?php echo gettext("Save changes to enforcing rules and rebuild"); ?>"/></td>
							<td style="vertical-align: middle;"><span class="vexpl"><?php echo gettext("Click to save changes and auto-resolve flowbit rules (if option is selected above)"); ?></span></td>
						</tr>
					<?php if (!empty($cat_mods)): ?>
						<tr height="20px">
							<td colspan="4" style="vertical-align: middle;"><img style="vertical-align: text-top;" src="../themes/<?=$g['theme'];?>/images/icons/icon_advanced.gif" width="11" height="11" border="0" />
							<?=gettext("- Category is auto-enabled by SID Mgmt conf files");?>&nbsp;&nbsp;&nbsp;
							<img style="opacity: 0.4; filter: alpha(opacity=40); vertical-align: text-top;" src="../themes/<?=$g['theme'];?>/images/icons/icon_advanced.gif" width="11" height="11" border="0" />
							<?=gettext("- Category is auto-disabled by SID Mgmt conf files");?></td>
						</tr>
					<?php endif; ?>
						</tbody>
					</table>
				</td>
			</tr>
			<?php if ($no_community_files)
				$msg_community = "NOTE: Snort Community Rules have not been downloaded.  Perform a Rules Update to enable them.";
			      else
				$msg_community = "Snort GPLv2 Community Rules (VRT certified)";
			      $community_rules_file = GPL_FILE_PREFIX . "community.rules";
			?>
			<?php if ($snortcommunitydownload == 'on'): ?>
			<tr>
				<td width="5%" class="listhdrr"><?php echo gettext("Enabled"); ?></td>
				<td colspan="5" class="listhdrr"><?php echo gettext('Ruleset: Snort GPLv2 Community Rules');?></td>
			</tr>
			<?php if (isset($cat_mods[$community_rules_file])): ?>
				<?php if ($cat_mods[$community_rules_file] == 'enabled') : ?>
					<tr>
						<td width="5%" class="listr" style="text-align: center;">
						<img src="../themes/<?=$g['theme'];?>/images/icons/icon_advanced.gif" width="11" height="11" border="0" title="<?=gettext("Auto-managed by settings on SID Mgmt tab");?>" /></td>
						<td colspan="5" class="listr"><a href='snort_rules.php?id=<?=$id;?>&openruleset=<?=$community_rules_file;?>'><?=gettext("{$msg_community}");?></a></td>
					</tr>
				<?php else: ?>
					<tr>
						<td width="5%" class="listr" style="text-align: center;">
						<img style="opacity: 0.4; filter: alpha(opacity=40);" src="../themes/<?=$g['theme'];?>/images/icons/icon_advanced.gif" width="11" height="11" border="0" title="<?=gettext("Auto-managed by settings on SID Mgmt tab");?>" /></td>
						<td colspan="5" class="listr"><?=gettext("{$msg_community}"); ?></td>
					</tr>
				<?php endif; ?>
			<?php elseif (in_array($community_rules_file, $enabled_rulesets_array)): ?>
			<tr>
				<td width="5%" class="listr" style="text-align: center;">
				<input type="checkbox" name="toenable[]" value="<?=$community_rules_file;?>" checked="checked"/></td>
				<td colspan="5" class="listr"><a href='snort_rules.php?id=<?=$id;?>&openruleset=<?=$community_rules_file;?>'><?php echo gettext("{$msg_community}"); ?></a></td>
			</tr>
			<?php else: ?>
			<tr>
				<td width="5%" class="listr" style="text-align: center;">
				<input type="checkbox" name="toenable[]" value="<?=$community_rules_file;?>" <?php if ($snortcommunitydownload == 'off') echo "disabled"; ?>/></td>
				<td colspan="5" class="listr"><?php echo gettext("{$msg_community}"); ?></td>
			</tr>
			<?php endif; ?>
			<?php endif; ?>
			<?php if ($no_emerging_files && ($emergingdownload == 'on' || $etpro == 'on'))
				  $msg_emerging = "have not been downloaded.";
			      else
				  $msg_emerging = "are not enabled.";
			      if ($no_snort_files && $snortdownload == 'on')
				  $msg_snort = "have not been downloaded.";
			      else
				  $msg_snort = "are not enabled.";
			?>
			<tr>
				<?php if ($emergingdownload == 'on' && !$no_emerging_files): ?>
					<td width="5%" class="listhdrr" align="center"><?php echo gettext("Enabled"); ?></td>
					<td width="25%" class="listhdrr"><?php echo gettext('Ruleset: ET Open Rules');?></td>
				<?php elseif ($etpro == 'on' && !$no_emerging_files): ?>
					<td width="5%" class="listhdrr" align="center"><?php echo gettext("Enabled"); ?></td>
					<td width="25%" class="listhdrr"><?php echo gettext('Ruleset: ET Pro Rules');?></td>
				<?php else: ?>
					<td colspan="2" align="center" width="30%" class="listhdrr"><?php echo gettext("{$et_type} rules {$msg_emerging}"); ?></td>
				<?php endif; ?>
				<?php if ($snortdownload == 'on' && !$no_snort_files): ?>
					<td width="5%" class="listhdrr" align="center"><?php echo gettext("Enabled"); ?></td>
					<td width="25%" class="listhdrr"><?php echo gettext('Ruleset: Snort Text Rules');?></td>
					<td width="5%" class="listhdrr" align="center"><?php echo gettext("Enabled"); ?></td>
					<td width="25%" class="listhdrr"><?php echo gettext('Ruleset: Snort SO Rules');?></td>
				<?php else: ?>
					<td colspan="4" align="center" width="60%" class="listhdrr"><?php echo gettext("Snort VRT rules {$msg_snort}"); ?></td>
				<?php endif; ?>
				</tr>
			<?php
				$emergingrules = array();
				$snortsorules = array();
				$snortrules = array();
				if (empty($isrulesfolderempty))
					$dh  = opendir("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/");
				else
					$dh  = opendir("{$snortdir}/rules/");
				while (false !== ($filename = readdir($dh))) {
					$filename = basename($filename);
					if (substr($filename, -5) != "rules")
						continue;
					if (strstr($filename, ET_OPEN_FILE_PREFIX) && $emergingdownload == 'on')
						$emergingrules[] = $filename;
					else if (strstr($filename, ET_PRO_FILE_PREFIX) && $etpro == 'on')
						$emergingrules[] = $filename;
					else if (strstr($filename, VRT_FILE_PREFIX) && $snortdownload == 'on') {
						if (strstr($filename, ".so.rules"))
							$snortsorules[] = $filename;
						else
							$snortrules[] = $filename;
					}
				}
				sort($emergingrules);
				sort($snortsorules);
				sort($snortrules);
				$i = count($emergingrules);
				if ($i < count($snortsorules))
					$i = count($snortsorules);
				if ($i < count($snortrules))
					$i = count($snortrules);

				for ($j = 0; $j < $i; $j++) {
					echo "<tr>\n";
					if (!empty($emergingrules[$j])) {
						$file = $emergingrules[$j];
						echo "<td width='5%' class='listr' align=\"center\">";
						if(is_array($enabled_rulesets_array)) {
							if(in_array($file, $enabled_rulesets_array) && !isset($cat_mods[$file]))
								$CHECKED = " checked=\"checked\"";
							else
								$CHECKED = "";
						} else
							$CHECKED = "";
						if (isset($cat_mods[$file])) {
							if (in_array($file, $enabled_rulesets_array))
								echo "<input type='hidden' name='toenable[]' value='{$file}' />\n";
							if ($cat_mods[$file] == 'enabled') {
								$CHECKED = "enabled";
								echo "	\n<img src=\"../themes/{$g['theme']}/images/icons/icon_advanced.gif\" width=\"11\" height=\"11\" border=\"0\" title=\"" . gettext("Auto-enabled by settings on SID Mgmt tab") . "\" />\n";
							}
							else {
								echo "	\n<img style=\"opacity: 0.4; filter: alpha(opacity=40);\" src=\"../themes/{$g['theme']}/images/icons/icon_advanced.gif\" width=\"11\" height=\"11\" border=\"0\" title=\"" . gettext("Auto-disabled by settings on SID Mgmt tab") . "\" />\n";
							}
						}
						else {
							echo "	\n<input type='checkbox' name='toenable[]' value='{$file}' {$CHECKED} />\n";
						}
						echo "</td>\n";
						echo "<td class='listr' width='25%' >\n";
						if (empty($CHECKED))
							echo $file;
						else
							echo "<a href='snort_rules.php?id={$id}&openruleset=" . urlencode($file) . "'>{$file}</a>\n";
						echo "</td>\n";
					} else
						echo "<td class='listbggrey' width='30%' colspan='2'><br/></td>\n";

					if (!empty($snortrules[$j])) {
						$file = $snortrules[$j];
						echo "<td class='listr' width='5%' align=\"center\">";
						if(is_array($enabled_rulesets_array)) {
							if (!empty($disable_vrt_rules))
								$CHECKED = $disable_vrt_rules;
							elseif(in_array($file, $enabled_rulesets_array) && !isset($cat_mods[$file]))
								$CHECKED = " checked=\"checked\"";
							else
								$CHECKED = "";
						} else
							$CHECKED = "";
						if (isset($cat_mods[$file])) {
							if (in_array($file, $enabled_rulesets_array))
								echo "<input type='hidden' name='toenable[]' value='{$file}' />\n";
							if ($cat_mods[$file] == 'enabled') {
								$CHECKED = "enabled";
								echo "	\n<img src=\"../themes/{$g['theme']}/images/icons/icon_advanced.gif\" width=\"11\" height=\"11\" border=\"0\" title=\"" . gettext("Auto-enabled by settings on SID Mgmt tab") . "\" />\n";
							}
							else {
								echo "	\n<img style=\"opacity: 0.4; filter: alpha(opacity=40);\" src=\"../themes/{$g['theme']}/images/icons/icon_advanced.gif\" width=\"11\" height=\"11\" border=\"0\" title=\"" . gettext("Auto-disabled by settings on SID Mgmt tab") . "\" />\n";
							}
						}
						else {
							echo "	\n<input type='checkbox' name='toenable[]' value='{$file}' {$CHECKED} />\n";
						}
						echo "</td>\n";
						echo "<td class='listr' width='25%' >\n";
						if (empty($CHECKED) || $CHECKED == "disabled")
							echo $file;
						else
							echo "<a href='snort_rules.php?id={$id}&openruleset=" . urlencode($file) . "'>{$file}</a>\n";
						echo "</td>\n";
					} else
						echo "<td class='listbggrey' width='30%' colspan='2'><br/></td>\n";

					if (!empty($snortsorules[$j])) {
						$file = $snortsorules[$j];
						echo "<td class='listr' width='5%' align=\"center\" valign=\"top\">";
						if(is_array($enabled_rulesets_array)) {
							if (!empty($disable_vrt_rules))
								$CHECKED = $disable_vrt_rules;
							elseif(in_array($file, $enabled_rulesets_array) && !isset($cat_mods[$file]))
								$CHECKED = " checked=\"checked\"";
							else
								$CHECKED = "";
						} else
							$CHECKED = "";
						if (isset($cat_mods[$file])) {
							if (in_array($file, $enabled_rulesets_array))
								echo "<input type='hidden' name='toenable[]' value='{$file}' />\n";
							if ($cat_mods[$file] == 'enabled') {
								$CHECKED = "enabled";
								echo "	\n<img src=\"../themes/{$g['theme']}/images/icons/icon_advanced.gif\" width=\"11\" height=\"11\" border=\"0\" title=\"" . gettext("Auto-enabled by settings on SID Mgmt tab") . "\" />\n";
							}
							else {
								echo "	\n<img style=\"opacity: 0.4; filter: alpha(opacity=40);\" src=\"../themes/{$g['theme']}/images/icons/icon_advanced.gif\" width=\"11\" height=\"11\" border=\"0\" title=\"" . gettext("Auto-disabled by settings on SID Mgmt tab") . "\" />\n";
							}
						}
						else {
							echo "	\n<input type='checkbox' name='toenable[]' value='{$file}' {$CHECKED} />\n";
						}
						echo "</td>\n";
						echo "<td class='listr' width='25%' >\n";
						if (empty($CHECKED) || $CHECKED == "disabled")
							echo $file;
						else
							echo "<a href='snort_rules.php?id={$id}&openruleset=" . urlencode($file) . "'>{$file}</a>\n";
						echo "</td>\n";
					} else
						echo "<td class='listbggrey' width='30%' colspan='2'><br/></td>\n";
				echo "</tr>\n";
			}
		?>
	</table>
	</td>
</tr>
<tr>
<td colspan="6" class="vexpl">&nbsp;<br/></td>
</tr>
			<tr>
				<td colspan="6" align="center" valign="middle">
				<input value="Save" type="submit" name="save" id="save" class="formbtn" title="<?php echo gettext("Click to Save changes and rebuild rules");?>"/></td>
			</tr>
<?php endif; ?>
</table>
</div>
</td>
</tr>
</table>
</form>
<?php
include("fend.inc");
?>

<script language="javascript" type="text/javascript">

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

function enable_change()
{
 var endis = !(document.iform.ips_policy_enable.checked);
 document.iform.ips_policy.disabled=endis;

 if (endis) {
	document.getElementById("ips_row1").style.display="none";
	document.getElementById("ips_row2").style.display="none";
	document.getElementById("ips_col1").className="vexpl";
	document.getElementById("ips_col2").className="vexpl";
 }
 else {
	document.getElementById("ips_row1").style.display="table-row";
	document.getElementById("ips_row2").style.display="table-row";
	document.getElementById("ips_col1").className="vncell";
	document.getElementById("ips_col2").className="vtable";
 }
 for (var i = 0; i < document.iform.elements.length; i++) {
    if (document.iform.elements[i].type == 'checkbox') {
       var str = document.iform.elements[i].value;
       if (str.substr(0,6) == "snort_")
          document.iform.elements[i].disabled = !(endis);
    }
 }
}

// Set initial state of dynamic HTML form controls
enable_change();

</script>

</body>
</html>
