<?php
/*
 * snort_rulesets.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009 Robert Zelaya
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
$openappid_rulesdownload = $config['installedpackages']['snortglobal']['openappid_rules_detectors'] == 'on' ? 'on' : 'off';

$no_emerging_files = false;
$no_snort_files = false;
$no_community_files = false;
$no_openappid_files = false;

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
$test = glob("{$snortdir}/rules/" . OPENAPPID_FILE_PREFIX . "*.rules");
if (empty($test))
	$no_openappid_files = true;
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

if (isset($_POST["save"])) {

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

	if ($_POST['autoflowbitrules'] == "on")
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

if (isset($_POST['unselectall'])) {
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

if (isset($_POST['selectall'])) {
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
	if ($openappid_rulesdownload == 'on') {
		$files = glob("{$snortdir}/rules/" . OPENAPPID_FILE_PREFIX . "*.rules");
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
// and the Auto-Flowbits option is enabled.
if ($a_nat[$id]['autoflowbitrules'] == 'on') {
	if (file_exists("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/{$flowbit_rules_file}") &&
	    filesize("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/{$flowbit_rules_file}") > 0) {
		$btn_view_flowb_rules = TRUE;
	}
	else
		$btn_view_flowb_rules = FALSE;
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']);
$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Categories"), gettext("{$if_friendly}"));
include_once("head.inc");

/* Display message */
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg);
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
	$tab_array[] = array($menu_iface . gettext("Categories"), true, "/snort/snort_rulesets.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Rules"), false, "/snort/snort_rules.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Variables"), false, "/snort/snort_define_servers.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Preprocs"), false, "/snort/snort_preprocessors.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/snort/snort_barnyard.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/snort/snort_ip_reputation.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
display_top_tabs($tab_array, true, 'nav nav-tabs');
?>

<?php
$isrulesfolderempty = glob("{$snortdir}/rules/*.rules");
$iscfgdirempty = array();
if (file_exists("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/custom.rules"))
	$iscfgdirempty = (array)("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/custom.rules");

// If no rules category files are downloaded and no custom.rules file exists, 
// then print a helpful message and bail out.
if (count($isrulesfolderempty) < 1 && count($iscfgdirempty) < 1) {
	$section = new Form_Section('Snort Rulesets (Categories) Selection');
	$section->addInput(new Form_StaticText(
		'',
		'<span class="help-block">The rules directory is empty.  You should go to the <a href="snort_interfaces_global.php">GLOBAL SETTINGS</a> ' .
		' tab and enable one or more provider rule sets, then go to the ' . 
		'<a href="snort_download_updates.php">UPDATES</a> tab and download the enabled rule sets.</span>'
	));
	print($section);
	include("foot.inc");
	return;
}	
?>

<form action="/snort/snort_rulesets.php" method="post" enctype="multipart/form-data" name="iform" id="iform" class="form-horizontal">
<input type="hidden" name="id" id="id" value="<?=$id;?>" />

<?php
$section = new Form_Section('Automatic Flowbit Resolution');

$group = new Form_Group('Resolve Flowbits');
$group->add(new Form_Checkbox(
	'autoflowbitrules',
	'Resolve Flowbits',
	'If checked, Snort will auto-enable rules required for checked flowbits. Default is Checked.',
	$pconfig['autoflowbitrules'] == 'on' ? true:false,
	'on'
))->setHelp('Snort will examine the enabled rules in your chosen rule categories for checked flowbits. ' . 
	    'Any rules that set these dependent flowbits will be automatically enabled and added to the list of files in the ' . 
	    'interface rules directory.');
$section->add($group);

if ($btn_view_flowb_rules == TRUE) {
	$btn_viewFlowbits = new Form_Button(
		'view',
		'View',
		'snort_rules_flowbits.php?id=' . $id . '&returl=' . urlencode($_SERVER['PHP_SELF']),
		'fa-file-text-o'
	);
	$btn_viewFlowbits->removeClass('btn-primary')->addClass('btn-info')->addClass('btn-sm');
	$group = new Form_Group('Auto-Flowbit Rules');
	$group->add($btn_viewFlowbits)->setHelp('Disabling auto-flowbit rules is strongly discouraged for security reasons. ' . 
						'Auto-enabled flowbit rules that generate unwanted alerts should have their ' . 
						'GID:SID added to the Suppression List for the interface instead of being disabled.');
	$section->add($group);
}

print($section);

if ($snortdownload == "on") {
	$section = new Form_Section('Snort VRT IPS Policy Selection');
	$section->addInput(new Form_Checkbox(
		'ips_policy_enable',
		'Use IPS Policy',
		'If checked, Snort will use rules from one of three pre-defined IPS policies in the Snort VRT rules. Default is Not Checked.',
		$pconfig['ips_policy_enable'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_StaticText(
	null,
	'<span class="help-block">Selecting this option disables manual selection of Snort VRT categories in the list below, ' . 
		'although Emerging Threats categories may still be selected if enabled on the Global Settings tab.  These ' . 
		'will be added to the pre-defined Snort IPS policy rules from the Snort VRT.</span>'
	));
	$section->addInput(new Form_Select(
		'ips_policy',
		'IPS Policy Selection',
		$pconfig['ips_policy'],
		array('connectivity' => 'Connectivity', 'balanced' => 'Balanced', 'security' => 'Security')
	))->setHelp('Snort IPS policies are:  Connectivity, Balanced or Security.');

	$section->addInput(new Form_StaticText(
		'',
		'<span class="help-block">Connectivity blocks most major threats with few or no false positives. ' . 
		'Balanced is a good starter policy. It is speedy, has good base coverage level, and covers ' . 
		'most threats of the day.  It includes all rules in Connectivity. Security is a stringent ' . 
		'policy.  It contains everything in the first two plus policy-type rules such as a Flash object in an Excel file.</span>'
	));
	print($section);
}

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Select the rulesets (Categories) Snort will load at startup")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive col-sm-12">
			<i class="fa fa-adn text-success"></i>&nbsp;<?=gettext('- Category is auto-enabled by SID Mgmt conf files'); ?><br/>
			<i class="fa fa-adn text-danger"></i>&nbsp;<?=gettext('- Category is auto-disabled by SID Mgmt conf files'); ?>
		</div>
		<nav class="action-buttons">
			<button type="submit" id="selectall" name="selectall" class="btn btn-info btn-sm" title="<?=gettext('Add all categories to enforcing rules');?>">
				<?=gettext('Select All');?>
			</button>
			<button type="submit" id="unselectall" name="unselectall" class="btn btn-warning btn-sm" title="<?=gettext('Remove all categories from enforcing rules');?>">
				<?=gettext('Unselect All');?>
			</button>
			<button type="submit" id="save" name="save" class="btn btn-primary btn-sm" title="<?=gettext('Click to Save changes and rebuild rules');?>">
				<i class="fa fa-save icon-embed-btn"></i>
				<?=gettext('Save');?>
			</button>
		</nav>

<!-- Process GPLv2 Community Rules if enabled -->
			<?php if ($no_community_files)
				$msg_community = gettext("NOTE: Snort Community Rules have not been downloaded.  Perform a Rules Update to enable them.");
			      else
				$msg_community = gettext("Snort GPLv2 Community Rules (VRT certified)");
			      $community_rules_file = gettext(GPL_FILE_PREFIX . "community.rules");
			?>

	<?php if ($snortcommunitydownload == 'on'): ?>
		<div class="table-responsive col-sm-12">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("Enabled"); ?></th>
						<th><?=gettext('Ruleset: Snort GPLv2 Community Rules'); ?></th>
						<th></th>
						<th></th>
						<th></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
			<?php if (isset($cat_mods[$community_rules_file])): ?>
				<?php if ($cat_mods[$community_rules_file] == 'enabled') : ?>
					<tr>
						<td>
							<i class="fa fa-adn text-success" title="<?=gettext('Auto-disabled by settings on SID Mgmt tab'); ?>"></i>
						</td>
						<td colspan="5">
							<a href='snort_rules.php?id=<?=$id;?>&openruleset=<?=$community_rules_file;?>'><?=gettext('{$msg_community}');?></a>
						</td>
					</tr>
				<?php else: ?>
					<tr>
						<td>
							<i class="fa fa-adn text-danger" title="<?=gettext("Auto-enabled by settings on SID Mgmt tab");?>"><i>
						</td>
						<td colspan="5">
							<?=gettext("{$msg_community}"); ?>
						</td>
					</tr>
				<?php endif; ?>
			<?php elseif (in_array($community_rules_file, $enabled_rulesets_array)): ?>
				<tr>
					<td>
						<input type="checkbox" name="toenable[]" value="<?=$community_rules_file;?>" checked="checked"/>
					</td>
					<td colspan="5">
						<a href='snort_rules.php?id=<?=$id;?>&openruleset=<?=$community_rules_file;?>'><?php echo gettext("{$msg_community}"); ?></a>
					</td>
				</tr>
			<?php else: ?>
				<tr>
					<td>
						<input type="checkbox" name="toenable[]" value="<?=$community_rules_file; ?>" />
					</td>
					<td colspan="5">
						<?=gettext("{$msg_community}"); ?>
					</td>
				</tr>
			<?php endif; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
<!-- End of GPLv2 Community rules -->

<!-- Set strings for rules file state of "not enabled" or "not downloaded" -->
			<?php if ($no_emerging_files && ($emergingdownload == 'on' || $etpro == 'on'))
				  $msg_emerging = "have not been downloaded.";
			      else
				  $msg_emerging = "are not enabled.";
			      if ($no_snort_files && $snortdownload == 'on')
				  $msg_snort = "have not been downloaded.";
			      else
				  $msg_snort = "are not enabled.";
			      if ($no_openappid_files && $openappid_rulesdownload == 'on')
				  $msg_snort = "have not been downloaded.";	
			      else
				  $msg_snort = "are not enabled.";
			?>
<!-- End of rules file state -->

<!-- Write out the header row -->
		<div class="table-responsive col-sm-12">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
					<?php if ($emergingdownload == 'on' && !$no_emerging_files): ?>
						<th><?=gettext("Enabled"); ?></th>
						<th><?=gettext('Ruleset: ET Open Rules');?></th>
					<?php elseif ($etpro == 'on' && !$no_emerging_files): ?>
						<th><?=gettext("Enabled"); ?></th>
						<th><?=gettext('Ruleset: ET Pro Rules');?></th>
					<?php else: ?>
						<th colspan="2"><?=gettext("{$et_type} rules {$msg_emerging}"); ?></th>
					<?php endif; ?>
					<?php if ($snortdownload == 'on' && !$no_snort_files): ?>
						<th><?=gettext("Enabled"); ?></th>
						<th><?=gettext('Ruleset: Snort Text Rules');?></th>
						<th><?=gettext("Enabled"); ?></th>
						<th><?=gettext('Ruleset: Snort SO Rules');?></th>
					<?php else: ?>
						<th colspan="4"><?=gettext("Snort VRT rules {$msg_snort}"); ?></th>
					<?php endif; ?>
					<?php if ($openappid_rulesdownload == 'on' && !$no_openappid_files): ?>
						<th><?=gettext("Enabled"); ?></th>
						<th><?=gettext('Ruleset: Snort OPENAPPI Rules');?></th>
						<?php else: ?>
						<th colspan="4"><?=gettext("Snort OPENAPPID rules {$msg_snort}"); ?></th>
					<?php endif; ?>
					</tr>
				</thead>
<!-- End of header row -->
				<tbody>
			<?php
				$emergingrules = array();
				$snortsorules = array();
				$snortrules = array();
				$openappidrules = array();
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
					else if (strstr($filename, OPENAPPID_FILE_PREFIX) && $openappid_rulesdownload == 'on')
						$openappidrules[] = $filename;
				}

				// Sort the rules file names alphabetically
				sort($emergingrules);
				sort($snortsorules);
				sort($snortrules);
				sort($openappidrules);
				// Now find the provider rules group with the most files 
				// and use that as the max interator value.
				$i = count($emergingrules);
				if ($i < count($snortsorules))
					$i = count($snortsorules);
				if ($i < count($snortrules))
					$i = count($snortrules);
				if ($i < count($openappidrules))
					$i = count($openappidrules);
				// Walk the rules file names arrays and output the
				// the file names and associated form controls in 
				// an HTML table.
				for ($j = 0; $j < $i; $j++) {
					echo "<tr>\n";
					if (!empty($emergingrules[$j])) {
						$file = $emergingrules[$j];
						echo "<td>";
						if(is_array($enabled_rulesets_array)) {
							if(in_array($file, $enabled_rulesets_array) && !isset($cat_mods[$file]))
								$CHECKED = " checked=\"checked\"";
							else
								$CHECKED = "";
						} else
							$CHECKED = "";

						// If the rule category file is covered by a SID mgmt configuration, 
						// place an appropriate icon beside the category.
						if (isset($cat_mods[$file])) {
							// If the category is part of the enabled rulesets array, 
							// make sure we include a hidden field to reference it 
							// so we do not unset it during a post-back.
							if (in_array($file, $enabled_rulesets_array))
								echo "<input type='hidden' name='toenable[]' value='{$file}' />\n";
							if ($cat_mods[$file] == 'enabled') {
								$CHECKED = "enabled";
								echo "	\n<i class=\"fa fa-adn text-success\" title=\"" . gettext('Auto-enabled by settings on SID Mgmt tab') . "></i>\n";
							}
							else {
								echo "	\n<i class=\"fa fa-adn text-danger\" title=\"" . gettext('Auto-disabled by settings on SID Mgmt tab') . "></i>\n";
							}
						}
						else {
							echo "	\n<input type=\"checkbox\" name=\"toenable[]\" value=\"{$file}\" {$CHECKED} />\n";
						}
						echo "</td>\n";
						echo "<td>\n";
						if (empty($CHECKED))
							echo $file;
						else
							echo "<a href='snort_rules.php?id={$id}&openruleset=" . urlencode($file) . "'>{$file}</a>\n";
						echo "</td>\n";
					} else
						echo "<td colspan='2'><br/></td>\n";

					if (!empty($snortrules[$j])) {
						$file = $snortrules[$j];
						echo "<td>";
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
								echo "	\n<i class=\"fa fa-adn text-success\" title=\"" . gettext('Auto-enabled by settings on SID Mgmt tab') . "></i>\n";
							}
							else {
								echo "	\n<i class=\"fa fa-adn text-danger\" title=\"" . gettext('Auto-disabled by settings on SID Mgmt tab') . "></i>\n";
							}
						}
						else {
							echo "	\n<input type='checkbox' name='toenable[]' value='{$file}' {$CHECKED} />\n";
						}
						echo "</td>\n";
						echo "<td>\n";
						if (empty($CHECKED) || $CHECKED == "disabled")
							echo $file;
						else
							echo "<a href='snort_rules.php?id={$id}&openruleset=" . urlencode($file) . "'>{$file}</a>\n";
						echo "</td>\n";
					} else
						echo "<td colspan='2'><br/></td>\n";

					if (!empty($snortsorules[$j])) {
						$file = $snortsorules[$j];
						echo "<td>";
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
								echo "	\n<i class=\"fa fa-adn text-success\" title=\"" . gettext('Auto-enabled by settings on SID Mgmt tab') . "></i>\n";
							}
							else {
								echo "	\n<i class=\"fa fa-adn text-danger\" title=\"" . gettext('Auto-disabled by settings on SID Mgmt tab') . "></i>\n";
							}
						}
						else {
							echo "	\n<input type='checkbox' name='toenable[]' value='{$file}' {$CHECKED} />\n";
						}
						echo "</td>\n";
						echo "<td>\n";
						if (empty($CHECKED) || $CHECKED == "disabled")
							echo $file;
						else
							echo "<a href='snort_rules.php?id={$id}&openruleset=" . urlencode($file) . "'>{$file}</a>\n";
						echo "</td>\n";
					} else
						echo "<td colspan='2'><br/></td>\n";
					if (!empty($openappidrules[$j])) {
						$file = $openappidrules[$j];
						echo "<td>";
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
						echo "  \n<i class=\"fa fa-adn text-success\" title=\"" . gettext('Auto-enabled by settings on SID Mgmt
tab') . "></i>\n";
					}
					else {
					echo "  \n<i class=\"fa fa-adn text-danger\" title=\"" . gettext('Auto-disabled by settings on SID Mgmt
tab') . "></i>\n";
					}
				}
				else {
					echo "  \n<input type=\"checkbox\" name=\"toenable[]\" value=\"{$file}\" {$CHECKED} />\n";
				}
				echo "</td>\n";
				echo "<td>\n";
				if (empty($CHECKED))
					echo $file;
				else
					echo "<a href='snort_rules.php?id={$id}&openruleset=" . urlencode($file) . "'>{$file}</a>\n";
				echo "</td>\n";
			} else
				echo "<td colspan='2'><br/></td>\n";
			echo "</tr>\n";
				}
			?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="col-sm-10 col-sm-offset-2">
	<button type="submit" id="save" name="save" class="btn btn-primary btn-sm" title="<?=gettext('Click to Save changes and rebuild rules');?>">
		<i class="fa fa-save icon-embed-btn"></i>
		<?=gettext('Save');?>
	</button>
</div>

</form>

<?php if ($snortdownload == "on") : ?>
	<script language="javascript" type="text/javascript">
	//<![CDATA[

		function enable_change()
		{
 			var endis = !(($('#ips_policy_enable').prop('checked')));
			disableInput('ips_policy', endis);

		 	for (var i = 0; i < document.iform.elements.length; i++) {
			    if (document.iform.elements[i].type == 'checkbox') {
			       var str = document.iform.elements[i].value;
			       if (str.substr(0,6) == "snort_")
        			  document.iform.elements[i].disabled = !(endis);
			    }
 			}
		}

	events.push(function(){

		// ---------- Click handlers -------------------------------------------------------

		$('#ips_policy_enable').click(function() {
			enable_change();
		});

		// Set initial state of dynamic HTML form controls
		enable_change();

	});
	//]]>
	</script>
<?php endif; ?>

<?php include("foot.inc"); ?>
