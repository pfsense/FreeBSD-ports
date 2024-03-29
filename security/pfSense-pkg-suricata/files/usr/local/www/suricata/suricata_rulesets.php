<?php
/*
 * suricata_rulesets.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
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
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g, $rebuild_rules;

$suricatadir = SURICATADIR;
$suricata_rules_dir = SURICATA_RULES_DIR;
$flowbit_rules_file = FLOWBITS_FILENAME;

// Array of default events rules for Suricata
$default_rules = SURICATA_DEFAULT_RULES;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);
if (is_null($id))
	$id = 0;

$a_nat = config_get_path("installedpackages/suricata/rule/{$id}", []);

if (!empty($a_nat)) {
	$pconfig['autoflowbits'] = $a_nat['autoflowbitrules'];
	$pconfig['ips_policy_enable'] = $a_nat['ips_policy_enable'];
	$pconfig['ips_policy'] = $a_nat['ips_policy'];
	$pconfig['ips_policy_mode'] = $a_nat['ips_policy_mode'];
}

$if_real = get_real_interface($a_nat['interface']);
$suricata_uuid = $a_nat['uuid'];
$snortdownload = config_get_path('installedpackages/suricata/config/0/enable_vrt_rules') == 'on' ? 'on' : 'off';
$emergingdownload = config_get_path('installedpackages/suricata/config/0/enable_etopen_rules') == 'on' ? 'on' : 'off';
$etpro = config_get_path('installedpackages/suricata/config/0/enable_etpro_rules') == 'on' ? 'on' : 'off';
$snortcommunitydownload = config_get_path('installedpackages/suricata/config/0/snortcommunityrules') == 'on' ? 'on' : 'off';
$feodotrackerdownload = config_get_path('installedpackages/suricata/config/0/enable_feodo_botnet_c2_rules') == 'on' ? 'on' : 'off';
$sslbldownload = config_get_path('installedpackages/suricata/config/0/enable_abuse_ssl_blacklist_rules') == 'on' ? 'on' : 'off';
$enable_extra_rules = config_get_path('installedpackages/suricata/config/0/enable_extra_rules') == "on" ? 'on' : 'off';
$extra_rules = config_get_path('installedpackages/suricata/config/0/extra_rules/rule', []);

$no_emerging_files = false;
$no_snort_files = false;
$inline_ips_mode = $a_nat['ips_mode'] == 'ips_mode_inline' ? true:false;
$ips_policy_mode_enable = $a_nat['block_drops_only'] == 'on' ? true:false;

$enabled_rulesets_array = explode("||", $a_nat['rulesets']);

/* Test rule categories currently downloaded to SURICATA_RULES_DIR and set appropriate flags */
if ($emergingdownload == 'on') {
	$test = glob("{$suricata_rules_dir}" . ET_OPEN_FILE_PREFIX . "*.rules");
	$et_type = "ET Open";
}
elseif ($etpro == 'on') {
	$test = glob("{$suricata_rules_dir}" . ET_PRO_FILE_PREFIX . "*.rules");
	$et_type = "ET Pro";
}
else
	$et_type = "Emerging Threats";

if (empty($test))
	$no_emerging_files = true;

$test = glob("{$suricata_rules_dir}" . VRT_FILE_PREFIX . "*.rules");
if (empty($test))
	$no_snort_files = true;

if (!file_exists("{$suricata_rules_dir}" . GPL_FILE_PREFIX . "community.rules"))
	$no_community_files = true;

if (!file_exists("{$suricata_rules_dir}" . "feodotracker.rules"))
	$no_feodotracker_files = true;

if (!file_exists("{$suricata_rules_dir}" . "sslblacklist_tls_cert.rules"))
	$no_sslbl_files = true;

// If a Snort rules policy is enabled and selected, remove all Snort
// rules from the configured rule sets to allow automatic selection.
if ($a_nat['ips_policy_enable'] == 'on') {
	if (isset($a_nat['ips_policy'])) {
		$disable_vrt_rules = "disabled";
		$enabled_sets = explode("||", $a_nat['rulesets']);

		foreach ($enabled_sets as $k => $v) {
			if (substr($v, 0, 6) == "suricata_")
				unset($enabled_sets[$k]);
		}
		$a_nat['rulesets'] = implode("||", $enabled_sets);
	}
}
else
	$disable_vrt_rules = "";

if (isset($_POST["save"])) {
	if ($_POST['ips_policy_enable'] == "on") {
		$a_nat['ips_policy_enable'] = 'on';
		$a_nat['ips_policy'] = $_POST['ips_policy'];
		$a_nat['ips_policy_mode'] = $_POST['ips_policy_mode'];
	}
	else {
		$a_nat['ips_policy_enable'] = 'off';
		unset($a_nat['ips_policy']);
		unset($a_nat['ips_policy_mode']);
	}

	if (is_array($_POST['toenable']))
		$enabled_items = implode("||", $_POST['toenable']);
	else
		$enabled_items =  "{$_POST['toenable']}";

	$a_nat['rulesets'] = $enabled_items;

	if ($_POST['autoflowbits'] == "on") {
		$a_nat['autoflowbitrules'] = 'on';
	}
	else {
		$a_nat['autoflowbitrules'] = 'off';
		// Need this here so the GUI renders correctly after saving
		$_POST['autoflowbits'] = "off";
		unlink_if_exists("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}/rules/{$flowbit_rules_file}");
	}

	config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
	write_config("Suricata pkg: save enabled rule categories for {$a_nat['interface']}.");

	/*************************************************/
	/* Update the suricata.yaml file and rebuild the */
	/* rules for this interface.                     */
	/*************************************************/
	$rebuild_rules = true;
	suricata_generate_yaml($a_nat);
	$rebuild_rules = false;

	/* Signal Suricata to "live reload" the rules */
	suricata_reload_config($a_nat);

	$pconfig = $_POST;
	$enabled_rulesets_array = explode("||", $enabled_items);
	if (suricata_is_running($suricata_uuid, $if_real))
		$savemsg = gettext("Suricata is 'live-loading' the new rule set on this interface.");

	// Sync to configured CARP slaves if any are enabled
	suricata_sync_on_changes();
} elseif (isset($_POST['unselectall'])) {
	if ($_POST['ips_policy_enable'] == "on") {
		$a_nat['ips_policy_enable'] = 'on';
		$a_nat['ips_policy'] = $_POST['ips_policy'];
		$a_nat['ips_policy_mode'] = $_POST['ips_policy_mode'];
	}
	else {
		$a_nat['ips_policy_enable'] = 'off';
		unset($a_nat['ips_policy']);
		unset($a_nat['ips_policy_mode']);
	}

	$pconfig['autoflowbits'] = $_POST['autoflowbits'];
	$pconfig['ips_policy_enable'] = $_POST['ips_policy_enable'];
	$pconfig['ips_policy'] = $_POST['ips_policy'];
	$pconfig['ips_policy_mode'] = $_POST['ips_policy_mode'];

	// Remove all the rules
	$enabled_rulesets_array = array();

	$savemsg = gettext("All rule categories, including default events rules, have been de-selected.  ");
	if ($_POST['ips_policy_enable'] == "on")
		$savemsg .= gettext("Only the rules included in the selected IPS Policy will be used.");
	else
		$savemsg .= gettext("There currently are no inspection rules enabled for this Suricata instance!");
} elseif (isset($_POST['selectall'])) {
	if ($_POST['ips_policy_enable'] == "on") {
		$a_nat['ips_policy_enable'] = 'on';
		$a_nat['ips_policy'] = $_POST['ips_policy'];
		$a_nat['ips_policy_mode'] = $_POST['ips_policy_mode'];
	}
	else {
		$a_nat['ips_policy_enable'] = 'off';
		unset($a_nat['ips_policy']);
		unset($a_nat['ips_policy_mode']);
	}

	$pconfig['autoflowbits'] = $_POST['autoflowbits'];
	$pconfig['ips_policy_enable'] = $_POST['ips_policy_enable'];
	$pconfig['ips_policy'] = $_POST['ips_policy'];
	$pconfig['ips_policy_mode'] = $_POST['ips_policy_mode'];

	// Start with the required default events and files rules
	$enabled_rulesets_array = $default_rules;

	if ($emergingdownload == 'on') {
		$files = glob("{$suricata_rules_dir}" . ET_OPEN_FILE_PREFIX . "*.rules");
		foreach ($files as $file)
			$enabled_rulesets_array[] = basename($file);
	}
	elseif ($etpro == 'on') {
		$files = glob("{$suricata_rules_dir}" . ET_PRO_FILE_PREFIX . "*.rules");
		foreach ($files as $file)
			$enabled_rulesets_array[] = basename($file);
	}

	if ($snortcommunitydownload == 'on') {
		$files = glob("{$suricata_rules_dir}" . GPL_FILE_PREFIX . "community.rules");
		foreach ($files as $file)
			$enabled_rulesets_array[] = basename($file);
	}

	if ($feodotrackerdownload == 'on') {
		$enabled_rulesets_array[] = "feodotracker.rules";
	}

	if ($sslbldownload == 'on') {
		$enabled_rulesets_array[] = "sslblacklist_tls_cert.rules";
	}

	/* Include the Snort rules only if enabled and no IPS policy is set */
	if ($snortdownload == 'on' && empty($_POST['ips_policy_enable'])) {
		$files = glob("{$suricata_rules_dir}" . VRT_FILE_PREFIX . "*.rules");
		foreach ($files as $file)
			$enabled_rulesets_array[] = basename($file);
	}

	if ($enable_extra_rules == 'on') {
		$files = glob("{$suricata_rules_dir}" . EXTRARULE_FILE_PREFIX . "*.rules");
		foreach ($files as $file)
			$enabled_rulesets_array[] = basename($file);
	}
}

// Get any automatic rule category enable/disable modifications
// if auto-SID Mgmt is enabled.
$cat_mods = suricata_sid_mgmt_auto_categories($a_nat, FALSE);

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat['interface']);
$pglinks = array("", "/suricata/suricata_interfaces.php", "/suricata/suricata_interfaces_edit.php?id={$id}", "@self");
$pgtitle = array("Services", "Suricata", "Interface Settings", "{$if_friendly} - Categories");

include_once("head.inc");

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
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php?instance={$id}");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$id}");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
$tab_array = array();
$tab_array[] = array($menu_iface . gettext("Settings"), false, "/suricata/suricata_interfaces_edit.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Categories"), true, "/suricata/suricata_rulesets.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Rules"), false, "/suricata/suricata_rules.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Flow/Stream"), false, "/suricata/suricata_flow_stream.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("App Parsers"), false, "/suricata/suricata_app_parsers.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Variables"), false, "/suricata/suricata_define_vars.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/suricata/suricata_ip_reputation.php?id={$id}");
display_top_tabs($tab_array, true);

$isrulesfolderempty = glob("{$suricata_rules_dir}*.rules");
$iscfgdirempty = array();

if (file_exists("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}/rules/custom.rules")) {
	$iscfgdirempty = (array)("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}/rules/custom.rules");
}

if (empty($isrulesfolderempty)):
	print_info_box(sprintf(gettext("# The rules directory is empty:  %s%srules%s"), '<strong>', $suricatadir,'</strong>') . "<br/><br/>" .
		gettext("Please go to the ") . '<a href="suricata_download_updates.php"><strong>' . gettext("Updates") .
		'</strong></a>' . gettext(" tab to download the rules configured on the ") .
		'<a href="suricata_interfaces_global.php"><strong>' . gettext("Global") .
		'</strong></a>' . gettext(" tab."), 'warning');

else:
?>
<form action="/suricata/suricata_rulesets.php" method="post" enctype="multipart/form-data" name="iform" id="iform" class="form-horizontal">
<input type="hidden" name="id" id="id" value="<?=$id;?>" />
<?php

	$section = new Form_Section("Automatic flowbit resolution");

	$section->addInput(new Form_Checkbox(
		'autoflowbits',
		'Resolve Flowbits',
		'Auto-enable rules required for checked flowbits',
		$pconfig['autoflowbits'] != 'off' ? true : false,
		'on'
	))->setHelp(' Default is Checked. Suricata will examine the enabled rules in your chosen rule categories for checked flowbits. ' .
					'Any rules that set these dependent flowbits will be automatically enabled and added to the list of files in the interface rules directory.');

	$viewbtn = new Form_Button(
		'View',
		'View',
		'suricata_rules_flowbits.php?id=' . $id . '&returl=' . urlencode($_SERVER['PHP_SELF']),
		'fa-regular fa-file-lines'
	);

	$viewbtn->removeClass('btn-primary')->addClass('btn-success btn-sm')
	  ->setHelp('Click to view auto-enabled rules required to satisfy flowbit dependencies' . '<br /><br />' .
	  			'<span class="text-danger"><strong>' . gettext('Note:  ') . '</strong></span>' .
	  			gettext('Auto-enabled rules generating unwanted alerts should have their GID:SID added to the Suppression List for the interface.'));


	// See if we have any Auto-Flowbit rules and enable
	// the VIEW button if we do.
	if ($pconfig['autoflowbits'] == 'on') {
		if (file_exists("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}/rules/{$flowbit_rules_file}") &&
		    filesize("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}/rules/{$flowbit_rules_file}") > 0) {
			$viewbtn->setAttribute('title', gettext("View flowbit-required rules"));
		}
		else
			$viewbtn->setDisabled();
	}
	else
		$viewbtn->setDisabled();

	$section->addInput(new Form_StaticText(
		'View rules',
		$viewbtn
	));

	print($section);

	if ($snortdownload == 'on') {

		$section = new Form_Section("Snort IPS Policy selection");
		$chkips = new Form_Checkbox(
			'ips_policy_enable',
			'Use IPS Policy',
			'Use rules from one of three pre-defined Snort IPS policies',
			($a_nat['ips_policy_enable'] == "on"),
			'on'
		);
		$chkips->setHelp('<span class="text-danger"><strong>' . gettext("Note:  ") . '</strong></span>' . gettext('You must be using the Snort rules to use this option.' . '<br />' .
					'Selecting this option disables manual selection of Snort rules categories in the list below, ' .
						'although Emerging Threats categories may still be selected if enabled on the Global Settings tab.  ' .
						'These will be added to the pre-defined Snort IPS policy rules from the Snort rules set.'));
		$section->addInput($chkips);
		$section->addInput(new Form_Select(
			'ips_policy',
			'IPS Policy Selection',
			$pconfig['ips_policy'],
			array(	'connectivity' => 'Connectivity',
				'balanced'  => 'Balanced',
				'security'  => 'Security',
				'max-detect' => 'Maximum Detection')
			))->setHelp('Connectivity blocks most major threats with few or no false positives. Balanced is a good starter policy. ' .
						'It is speedy, has good base coverage level, and covers most threats of the day. It includes all rules in Connectivity. Security is a stringent policy. ' .
						'It contains everything in the first two plus policy-type rules such as Flash in an Excel file.  Maximum Detection encompasses vulnerabilities from 2005 ' .
						'or later with a CVSS score of at least 7.5 along with critical malware and exploit kit rules.  The Maximum Detection policy favors detection over rated ' .
						'throughput. In some situations this policy can and will cause significant throughput reductions.');
		$section->addInput(new Form_Select(
			'ips_policy_mode',
			'IPS Policy Mode',
			$pconfig['ips_policy_mode'],
			array(  'alert' => 'Alert',
				'policy'  => 'Policy')
			))->setHelp('When Policy is selected, this will automatically change the action for rules in the selected IPS Policy from their default action of alert to the action specified ' .
					'in the policy metadata (typically drop, but may be alert for some policy rules).');

		print($section);
	}

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Select the rulesets (Categories) Suricata will load at startup")?></h2></div>
	<div class="panel-body">
	<div class="table-responsive col-sm-12">
		<table class="table-condensed">
			<thead>
				<tr>
					<th></th>
					<th></th>
				</tr>
			<thead>
			<tbody>
				<tr>
					<td>
						<i class="fa-brands fa-adn text-success"></i>&nbsp;<?=gettext('- Category is auto-enabled by SID Mgmt conf files'); ?><br/>
						<i class="fa-brands fa-adn text-danger"></i>&nbsp;<?=gettext('- Category is auto-disabled by SID Mgmt conf files'); ?>
					</td>
				</tr>
			</tbody>
		</table>
		<nav class="action-buttons">
			<button type="submit" id="selectall" name="selectall" class="btn btn-info btn-sm" title="<?=gettext('Add all categories to enforcing rules');?>">
				<?=gettext('Select All');?>
			</button>
			<button type="submit" id="unselectall" name="unselectall" class="btn btn-warning btn-sm" title="<?=gettext('Remove all categories from enforcing rules');?>">
				<?=gettext('Unselect All');?>
			</button>
			<button type="submit" id="save" name="save" class="btn btn-primary btn-sm" title="<?=gettext('Click to Save changes and rebuild rules');?>">
				<i class="fa-solid fa-save icon-embed-btn"></i>
				<?=gettext(' Save');?>
			</button>
		</nav>
	</div>

<!-- Display options for GPLv2 Community, Feodo Tracker and SSL Blacklist Rules -->
	<div class="table-responsive col-sm-12">
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><?=gettext("Enabled"); ?></th>
					<th><?=gettext('Ruleset:'); ?></th>
					<th colspan="2">&nbsp;</th>
				</tr>
			</thead>
			<tbody>

<!-- Process GPLv2 Community Rules if enabled -->
	<?php	if ($no_community_files)
				$msg_community = gettext("NOTE: Snort GPLv2 Community Rules have not been downloaded.  Perform a Rules Update to enable them.");
			else
				$msg_community = gettext("Snort GPLv2 Community Rules (Talos-certified)");
	      	$community_rules_file = gettext(GPL_FILE_PREFIX . "community.rules");
	?>

	<?php if ($snortcommunitydownload == 'on'): ?>
			<?php if (isset($cat_mods[$community_rules_file])): ?>
				<?php if ($cat_mods[$community_rules_file] == 'enabled') : ?>
					<tr>
						<td>
							<i class="fa-brands fa-adn text-success" title="<?=gettext('Auto-enabled by settings on SID Mgmt tab'); ?>"></i>
						</td>
						<td colspan="3">
						<?php if ($no_community_files): ?>
							<?php echo $msg_community; ?>
						<?php else: ?>
							<a href='suricata_rules.php?id=<?=$id;?>&openruleset=<?=$community_rules_file;?>'><?=$msg_community;?></a>
						<?php endif; ?>
						</td>
					</tr>
				<?php else: ?>
					<tr>
						<td>
							<i class="fa-brands fa-adn text-danger" title="<?=gettext("Auto-disabled by settings on SID Mgmt tab");?>"><i>
						</td>
						<td colspan="3">
						<?php if ($no_community_files): ?>
							<?php echo $msg_community; ?>
						<?php else: ?>
							<a href='suricata_rules_edit.php?id=<?=$id;?>&openruleset=<?=$community_rules_file;?>' target='_blank' rel='noopener noreferrer'><?=$msg_community; ?></a>
						<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
			<?php elseif (in_array($community_rules_file, $enabled_rulesets_array)): ?>
				<tr>
					<td>
						<input type="checkbox" name="toenable[]" value="<?=$community_rules_file;?>" checked="checked"/>
					</td>
					<td colspan="3">
						<?php if ($no_community_files): ?>
							<?php echo $msg_community; ?>
						<?php else: ?>
							<a href='suricata_rules.php?id=<?=$id;?>&openruleset=<?=$community_rules_file;?>'><?php echo $msg_community; ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php else: ?>
				<tr>
					<td>
						<input type="checkbox" name="toenable[]" value="<?=$community_rules_file; ?>" />
					</td>
					<td colspan="3">
						<?php if ($no_community_files): ?>
							<?php echo $msg_community; ?>
						<?php else: ?>
							<a href='suricata_rules_edit.php?id=<?=$id;?>&openruleset=<?=$community_rules_file;?>' target='_blank' rel='noopener noreferrer'><?=$msg_community; ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
	<?php endif;
?>
<!-- End of GPLv2 Community rules -->

<!-- Process Feodo Tracker Rules if enabled -->
	<?php if ($no_feodotracker_files)
			$msg_feodotracker = gettext("NOTE: Feodo Tracker Botnet C2 IP Rules have not been downloaded.  Perform a Rules Update to enable them.");
	      else
			$msg_feodotracker = gettext("Feodo Tracker Botnet C2 IP Rules");
		  $feodotracker_rules_file = gettext("feodotracker.rules");
	?>
	<?php if ($feodotrackerdownload == 'on'): ?>
			<?php if (isset($cat_mods[$feodotracker_rules_file])): ?>
				<?php if ($cat_mods[$feodotracker_rules_file] == 'enabled') : ?>
					<tr>
						<td>
							<i class="fa-brands fa-adn text-success" title="<?=gettext('Auto-enabled by settings on SID Mgmt tab'); ?>"></i>
						</td>
						<td colspan="3">
						<?php if ($no_feodotracker_files): ?>
							<?php echo $msg_feodotracker; ?>
						<?php else: ?>
							<a href='suricata_rules.php?id=<?=$id;?>&openruleset=<?=$feodotracker_rules_file;?>'><?=$msg_feodotracker;?></a>
						<?php endif; ?>
						</td>
					</tr>
				<?php else: ?>
					<tr>
						<td>
							<i class="fa-brands fa-adn text-danger" title="<?=gettext("Auto-disabled by settings on SID Mgmt tab");?>"><i>
						</td>
						<td colspan="3">
						<?php if ($no_feodotracker_files): ?>
							<?php echo $msg_feodotracker; ?>
						<?php else: ?>
							<a href='suricata_rules_edit.php?id=<?=$id;?>&openruleset=<?=$feodotracker_rules_file;?>' target='_blank' rel='noopener noreferrer'><?=$msg_feodotracker; ?></a>
						<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
			<?php elseif (in_array($feodotracker_rules_file, $enabled_rulesets_array)): ?>
				<tr>
					<td>
						<input type="checkbox" name="toenable[]" value="<?=$feodotracker_rules_file;?>" checked="checked"/>
					</td>
					<td colspan="3">
						<?php if ($no_feodotracker_files): ?>
							<?php echo $msg_feodotracker; ?>
						<?php else: ?>
							<a href='suricata_rules.php?id=<?=$id;?>&openruleset=<?=$feodotracker_rules_file;?>'><?php echo $msg_feodotracker; ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php else: ?>
				<tr>
					<td>
						<input type="checkbox" name="toenable[]" value="<?=$feodotracker_rules_file; ?>" />
					</td>
					<td colspan="3">
						<?php if ($no_feodotracker_files): ?>
							<?php echo $msg_feodotracker; ?>
						<?php else: ?>
							<a href='suricata_rules_edit.php?id=<?=$id;?>&openruleset=<?=$feodotracker_rules_file;?>' target='_blank' rel='noopener noreferrer'><?=$msg_feodotracker; ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
	<?php endif;
?>
<!-- End of Feodo Tracker rules -->

<!-- Process ABUSE.ch SSL Blacklist Rules if enabled -->
	<?php if ($no_sslbl_files)
			$msg_sslbl = gettext("NOTE: ABUSE.ch SSL Blacklist Rules have not been downloaded.  Perform a Rules Update to enable them.");
	      else
			$msg_sslbl = gettext("ABUSE.ch SSL Blacklist Rules");
		  $sslbl_rules_file = gettext("sslblacklist_tls_cert.rules");
	?>
	<?php if ($sslbldownload == 'on'): ?>
			<?php if (isset($cat_mods[$sslbl_rules_file])): ?>
				<?php if ($cat_mods[$sslbl_rules_file] == 'enabled') : ?>
					<tr>
						<td>
							<i class="fa-brands fa-adn text-success" title="<?=gettext('Auto-enabled by settings on SID Mgmt tab'); ?>"></i>
						</td>
						<td colspan="3">
						<?php if ($no_sslbl_files): ?>
							<?php echo $msg_sslbl; ?>
						<?php else: ?>
							<a href='suricata_rules.php?id=<?=$id;?>&openruleset=<?=$sslbl_rules_file;?>'><?=$msg_sslbl;?></a>
						<?php endif; ?>
						</td>
					</tr>
				<?php else: ?>
					<tr>
						<td>
							<i class="fa-brands fa-adn text-danger" title="<?=gettext("Auto-disabled by settings on SID Mgmt tab");?>"><i>
						</td>
						<td colspan="3">
						<?php if ($no_sslbl_files): ?>
							<?php echo $msg_sslbl; ?>
						<?php else: ?>
							<a href='suricata_rules_edit.php?id=<?=$id;?>&openruleset=<?=$sslbl_rules_file;?>' target='_blank' rel='noopener noreferrer'><?=$msg_sslbl; ?></a>
						<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
			<?php elseif (in_array($sslbl_rules_file, $enabled_rulesets_array)): ?>
				<tr>
					<td>
						<input type="checkbox" name="toenable[]" value="<?=$sslbl_rules_file;?>" checked="checked"/>
					</td>
					<td colspan="3">
						<?php if ($no_sslbl_files): ?>
							<?php echo $msg_sslbl; ?>
						<?php else: ?>
							<a href='suricata_rules.php?id=<?=$id;?>&openruleset=<?=$sslbl_rules_file;?>'><?php echo $msg_sslbl; ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php else: ?>
				<tr>
					<td>
						<input type="checkbox" name="toenable[]" value="<?=$sslbl_rules_file; ?>" />
					</td>
					<td colspan="3">
						<?php if ($no_sslbl_files): ?>
							<?php echo $msg_sslbl; ?>
						<?php else: ?>
							<a href='suricata_rules_edit.php?id=<?=$id;?>&openruleset=<?=$sslbl_rules_file;?>' target='_blank' rel='noopener noreferrer'><?=$msg_sslbl; ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
	<?php endif;
?>
<!-- End of ABUSE.ch SSL Blacklist rules -->

<!-- End of processing for GPLv2, Feodo Tracker and SSL Blacklist rules, so close table tags -->
			</tbody>
		</table>
	</div>

<!-- Set strings for rules file state of "not enabled" or "not downloaded" -->
			<?php if ($no_emerging_files && ($emergingdownload == 'on' || $etpro == 'on'))
				  $msg_emerging = "have not been downloaded.";
			      else
				  $msg_emerging = "are not enabled.";
			      if ($no_snort_files && $snortdownload == 'on')
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
						<th><?=gettext("Enabled"); ?></th>
						<th><?=gettext("Ruleset: Default Rules"); ?></th>
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
					<?php else: ?>
						<th colspan="2"><?=gettext("Snort Rules {$msg_snort}"); ?></th>
					<?php endif; ?>
					</tr>
				</thead>
<!-- End of header row -->

				<tbody>
<?php

				$emergingrules = array();
				$snortrules = array();
				if (empty($isrulesfolderempty))
					$dh  = opendir("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}/rules/");
				else
					$dh  = opendir("{$suricata_rules_dir}");

				while (false !== ($filename = readdir($dh))) {
					$filename = basename($filename);
					if (substr($filename, -5) != "rules")
						continue;
					if (strstr($filename, ET_OPEN_FILE_PREFIX) && $emergingdownload == 'on')
						$emergingrules[] = $filename;
					else if (strstr($filename, ET_PRO_FILE_PREFIX) && $etpro == 'on')
						$emergingrules[] = $filename;
					else if (strstr($filename, VRT_FILE_PREFIX) && $snortdownload == 'on') {
						$snortrules[] = $filename;
					}
				}

				sort($default_rules);
				sort($emergingrules);
				sort($snortrules);

				// Find the largest array to determine the max number of rows
				$i = count($default_rules);
				if ($i < count($emergingrules))
					$i = count($emergingrules);
				if ($i < count($snortrules))
					$i = count($snortrules);

				// Walk the rules file names arrays and output the
				// the file names and associated form controls in
				// an HTML table.
				for ($j = 0; $j < $i; $j++) {
					echo "<tr>\n";
				/* Begin DEFAULT RULES */
					if (!empty($default_rules[$j])) {
						$file = $default_rules[$j];
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
								echo "	\n<i class=\"fa-brands fa-adn text-success\" title=\"" . gettext('Auto-enabled by settings on SID Mgmt tab') . "\"></i>\n";
							}
							elseif ($cat_mods[$file] == 'disabled') {
								echo "	\n<i class=\"fa-brands fa-adn text-danger\" title=\"" . gettext('Auto-disabled by settings on SID Mgmt tab') . "\"></i>\n";
							}
						}
						else {
							echo "	\n<input type=\"checkbox\" name=\"toenable[]\" value=\"{$file}\" {$CHECKED} />\n";
						}
						echo "</td>\n";
						echo "<td>\n";
						if (empty($CHECKED))
							echo "<a href='suricata_rules_edit.php?id={$id}&openruleset=" . urlencode($file) . "' target='_blank' rel='noopener noreferrer'>{$file}</a>\n";
						else
							echo "<a href='suricata_rules.php?id={$id}&openruleset=" . urlencode($file) . "'>{$file}</a>\n";
						echo "</td>\n";
					} else
						echo "<td colspan='2'><br/></td>\n";
				/* End DEFAULT RULES */

				/* Begin EMERGING THREATS RULES */
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
								echo "	\n<i class=\"fa-brands fa-adn text-success\" title=\"" . gettext('Auto-enabled by settings on SID Mgmt tab') . "\"></i>\n";
							}
							elseif ($cat_mods[$file] == 'disabled') {
								echo "	\n<i class=\"fa-brands fa-adn text-danger\" title=\"" . gettext('Auto-disabled by settings on SID Mgmt tab') . "\"></i>\n";
							}
						}
						else {
							echo "	\n<input type=\"checkbox\" name=\"toenable[]\" value=\"{$file}\" {$CHECKED} />\n";
						}
						echo "</td>\n";
						echo "<td>\n";
						if (empty($CHECKED))
							echo "<a href='suricata_rules_edit.php?id={$id}&openruleset=" . urlencode($file) . "' target='_blank' rel='noopener noreferrer'>{$file}</a>\n";
						else
							echo "<a href='suricata_rules.php?id={$id}&openruleset=" . urlencode($file) . "'>{$file}</a>\n";
						echo "</td>\n";
					} else
						echo "<td colspan='2'><br/></td>\n";
				/* End EMERGING THREATS RULES */

				/* Begin SNORT VRT RULES */
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
								echo "	\n<i class=\"fa-brands fa-adn text-success\" title=\"" . gettext('Auto-enabled by settings on SID Mgmt tab') . "\"></i>\n";
							}
							else {
								echo "	\n<i class=\"fa-brands fa-adn text-danger\" title=\"" . gettext('Auto-disabled by settings on SID Mgmt tab') . "\"></i>\n";
							}
						}
						else {
							if ($CHECKED == "disabled") {
								echo "	\n<input type='checkbox' name='toenable[]' value='{$file}' {$CHECKED} title='" . gettext('Disabled because an IPS Policy is selected') . "' />\n";
							}
							else {
								echo "	\n<input type='checkbox' name='toenable[]' value='{$file}' {$CHECKED} />\n";
							}
						}
						echo "</td>\n";
						echo "<td>\n";
						if (empty($CHECKED) || $CHECKED == "disabled")
							echo "<a href='suricata_rules_edit.php?id={$id}&openruleset=" . urlencode($file) . "' target='_blank' rel='noopener noreferrer'>{$file}</a>\n";
						else
							echo "<a href='suricata_rules.php?id={$id}&openruleset=" . urlencode($file) . "'>{$file}</a>\n";
						echo "</td>\n";
					} else
						echo "<td colspan='2'><br/></td>\n";
				/* End SNORT VRT RULES */

					echo "</tr>\n";
				}
			?>
				</tbody>
			</table>
		</div>
<?php
if (($enable_extra_rules == 'on') && !empty($extra_rules)) {
?>
		<div class="table-responsive col-sm-12">
			<table class="table table-striped table-hover table-condensed">
<?php
foreach ($extra_rules as $exrule) {
	$format = (substr($exrule['url'], strrpos($exrule['url'], 'rules')) == 'rules') ? ".rules" : ".tar.gz";
	$rulesfilename = EXTRARULE_FILE_PREFIX . $exrule['name'] . $format;
?>
				<thead>
					<tr>
						<th><?=gettext("Enabled"); ?></th>
						<th><?=gettext("Extra Ruleset: {$exrule['name']}");?></th>
					</tr>
				</thead>
				<tbody>
<?php
				$extrarules = array();
				if (empty($isrulesfolderempty)) {
					$dh  = opendir("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}/rules/");
				} else {
					$dh  = opendir("{$suricata_rules_dir}");
				}

				while (false !== ($filename = readdir($dh))) {
					$filename = basename($filename);
					if (substr($filename, -5) != "rules") {
						continue;
					}
					preg_match("/" . EXTRARULE_FILE_PREFIX . "([A-Za-z0-9_]+)/", $filename, $matches);
					if ($exrule['name'] == $matches[1]) {
						$extrarules[] = $filename;
					}
				}

				sort($extrarules);
				$i = count($extrarules);

				// Walk the rules file names arrays and output the
				// the file names and associated form controls in
				// an HTML table.

				for ($j = 0; $j < $i; $j++) {
					echo "<tr>\n";
					if (!empty($extrarules[$j])) {
						$file = $extrarules[$j];
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
								echo "	\n<i class=\"fa-brands fa-adn text-success\" title=\"" . gettext('Auto-enabled by settings on SID Mgmt tab') . "\"></i>\n";
							}
							elseif ($cat_mods[$file] == 'disabled') {
								echo "	\n<i class=\"fa-brands fa-adn text-danger\" title=\"" . gettext('Auto-disabled by settings on SID Mgmt tab') . "\"></i>\n";
							}
						}
						else {
							echo "	\n<input type=\"checkbox\" name=\"toenable[]\" value=\"{$file}\" {$CHECKED} />\n";
						}
						echo "</td>\n";
						echo "<td>\n";
						if (empty($CHECKED))
							echo "<a href='suricata_rules_edit.php?id={$id}&openruleset=" . urlencode($file) . "' target='_blank' rel='noopener noreferrer'>{$file}</a>\n";
						else
							echo "<a href='suricata_rules.php?id={$id}&openruleset=" . urlencode($file) . "'>{$file}</a>\n";
						echo "</td>\n";
					} else
						echo "<td colspan='2'><br/></td>\n";
					echo "</tr>\n";
				}
			?>
				</tbody>
<?php
}
?>
			</table>
		</div>
<?php
}
?>
	</div>
</div>

<div class="table-responsive col-sm-12">
	<nav class="action-buttons">
		<button type="submit" id="save" name="save" class="btn btn-primary btn-sm" title="<?=gettext('Click to Save changes and rebuild rules');?>">
			<i class="fa-solid fa-save icon-embed-btn"></i>
			<?=gettext(' Save');?>
		</button>
	</nav>
</div>

</form>

<script language="javascript" type="text/javascript">
//<![CDATA[

events.push(function() {

	function enable_change()
	{
		var endis = !($('#ips_policy_enable').prop('checked'));

		hideInput('ips_policy', endis);
	<?php if ($inline_ips_mode || $ips_policy_mode_enable): ?>
			hideInput('ips_policy_mode', endis);
	<?php else: ?>
			hideInput('ips_policy_mode', true);
	<?php endif;?>

		$('input[type="checkbox"]').each(function() {
			var str = $(this).val();

			if (str.substr(0,6) == "snort_") {
				$(this).attr('disabled', !endis);
				if (!endis) {
					$(this).prop('title', 'Disabled because an IPS Policy is selected');
				}
				else {
					$(this).prop('title', '');
				}
			}
		});
	}

	//------- Click handlers -----------------------------------------
	//
	$('#ips_policy_enable').click(function() {
		enable_change();
	});

	// Set initial state of dynamic HTML form controls
	enable_change();

});
//]]>
</script>
<?php
endif;
include("foot.inc");
