<?php
/*
 * snort_ip_reputation.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2019-2025 Rubicon Communications, LLC (Netgate)
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

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
	header("Location: /snort/snort_interfaces.php");
	exit;
}

$a_nat = config_get_path("installedpackages/snortglobal/rule/{$id}", []);

// Init 'blist_files' and 'wlist_files' arrays for the interface
array_init_path($a_nat, 'blist_files/item');
array_init_path($a_nat, 'wlist_files/item');

$pconfig = $a_nat;
$iprep_path = SNORT_IPREP_PATH;
$if_real = get_real_interface($a_nat['interface']);
$snort_uuid = config_get_path("installedpackages/snortglobal/rule/{$id}/uuid");

// Set sensible defaults for any empty parameters
if (empty($pconfig['iprep_memcap']))
	$pconfig['iprep_memcap'] = '500';
if (empty($pconfig['iprep_priority']))
	$pconfig['iprep_priority'] = 'whitelist';
if (empty($pconfig['iprep_nested_ip']))
	$pconfig['iprep_nested_ip'] = 'inner';
if (empty($pconfig['iprep_white']))
	$pconfig['iprep_white'] = 'unblack';

if ($_POST['mode'] == 'blist_add' && isset($_POST['iplist'])) {
	$pconfig = $_POST;

	// Test the supplied IP List file to see if it exists
	if (file_exists($iprep_path . basename($_POST['iplist']))) {
		// See if the file is already assigned to the interface
		foreach ($a_nat['blist_files']['item'] as $f) {
			if ($f == basename($_POST['iplist'])) {
				$input_errors[] = gettext("The file {$f} is already assigned as a blacklist file.");
				break;
			}
		}
		if (!$input_errors) {
			$a_nat['blist_files']['item'][] = basename($_POST['iplist']);
			config_set_path("installedpackages/snortglobal/rule/{$id}", $a_nat);
			write_config("Snort pkg: added new blacklist file for IP REPUTATION preprocessor.");
			mark_subsystem_dirty('snort_iprep');
		}
	}
	else
		$input_errors[] = gettext("The file '{$_POST['iplist']}' could not be found.");

	$pconfig['blist_files'] = $a_nat['blist_files'];
	$pconfig['wlist_files'] = $a_nat['wlist_files'];
}

if ($_POST['mode'] == 'wlist_add' && isset($_POST['iplist'])) {
	$pconfig = $_POST;

	// Test the supplied IP List file to see if it exists
	if (file_exists($iprep_path . basename($_POST['iplist']))) {
		// See if the file is already assigned to the interface
		foreach ($a_nat['wlist_files']['item'] as $f) {
			if ($f == basename($_POST['iplist'])) {
				$input_errors[] = gettext("The file {$f} is already assigned as a whitelist file.");
				break;
			}
		}
		if (!$input_errors) {
			$a_nat['wlist_files']['item'][] = basename($_POST['iplist']);
			config_set_path("installedpackages/snortglobal/rule/{$id}", $a_nat);
			write_config("Snort pkg: added new whitelist file for IP REPUTATION preprocessor.");
			mark_subsystem_dirty('snort_iprep');
		}
	}
	else
		$input_errors[] = gettext("The file '{$_POST['iplist']}' could not be found.");

	$pconfig['blist_files'] = $a_nat['blist_files'];
	$pconfig['wlist_files'] = $a_nat['wlist_files'];
}

if ($_POST['mode'] == 'blist_del' && is_numericint($_POST['list_id'])) {
	$pconfig = $_POST;
	unset($a_nat['blist_files']['item'][$_POST['list_id']]);
	config_set_path("installedpackages/snortglobal/rule/{$id}", $a_nat);
	write_config("Snort pkg: deleted blacklist file for IP REPUTATION preprocessor.");
	mark_subsystem_dirty('snort_iprep');
	$pconfig['blist_files'] = $a_nat['blist_files'];
	$pconfig['wlist_files'] = $a_nat['wlist_files'];
}

if ($_POST['mode'] == 'wlist_del' && is_numericint($_POST['list_id'])) {
	$pconfig = $_POST;
	unset($a_nat['wlist_files']['item'][$_POST['list_id']]);
	config_set_path("installedpackages/snortglobal/rule/{$id}", $a_nat);
	write_config("Snort pkg: deleted whitelist file for IP REPUTATION preprocessor.");
	mark_subsystem_dirty('snort_iprep');
	$pconfig['wlist_files'] = $a_nat['wlist_files'];
	$pconfig['blist_files'] = $a_nat['blist_files'];
}

if ($_POST['apply']) {
	// Apply changes to IP Reputation lists for the interface
	$rebuild_rules = false;
	snort_generate_conf($a_nat);

	// If Snort is already running, must restart to change IP REP preprocessor configuration.
	if (snort_is_running($a_nat['uuid'])) {
		logger(LOG_NOTICE, localize_text("restarting on interface %s due to IP REP preprocessor configuration change.", convert_real_interface_to_friendly_descr($if_real)), LOG_PREFIX_PKG_SNORT);
		snort_stop($a_nat, $if_real);
		snort_start($a_nat, $if_real, TRUE);
	}

	// Sync to configured CARP slaves if any are enabled
	snort_sync_on_changes();

	// We have saved changes and done a soft restart, so clear "dirty" flag
	clear_subsystem_dirty('snort_iprep');

	$pconfig['blist_files'] = $a_nat['blist_files'];
	$pconfig['wlist_files'] = $a_nat['wlist_files'];
}

if ($_POST['save']) {

	$natent = array();
	$natent = $pconfig;

	if (!is_numericint($_POST['iprep_memcap']) || strval($_POST['iprep_memcap']) < 1 || strval($_POST['iprep_memcap']) > 4095)
		$input_errors[] = gettext("The value for Memory Cap must be an integer between 1 and 4095.");

	// if no errors write to conf
	if (!$input_errors) {

		$natent['reputation_preproc'] = $_POST['reputation_preproc'] ? 'on' : 'off';
		$natent['iprep_scan_local'] = $_POST['iprep_scan_local'] ? 'on' : 'off';
		$natent['iprep_memcap'] = $_POST['iprep_memcap'];
		$natent['iprep_priority'] = $_POST['iprep_priority'];
		$natent['iprep_nested_ip'] = $_POST['iprep_nested_ip'];
		$natent['iprep_white'] = $_POST['iprep_white'];

		$a_nat = $natent;
		config_set_path("installedpackages/snortglobal/rule/{$id}", $a_nat);
		write_config("Snort pkg: modified IP REPUTATION preprocessor settings for {$a_nat['interface']}.");

		// Update the snort conf file for this interface
		$rebuild_rules = false;
		snort_generate_conf($a_nat);

		// If Snort is already running, must restart to change IP REP preprocessor configuration.
		if (snort_is_running($a_nat['uuid'])) {
			logger(LOG_NOTICE, localize_text("restarting on interface %s due to IP REP preprocessor configuration change.", convert_real_interface_to_friendly_descr($if_real)), LOG_PREFIX_PKG_SNORT);
			snort_stop($a_nat, $if_real);
			snort_start($a_nat, $if_real, TRUE);
			$savemsg = gettext("Snort has been restarted on interface " . convert_real_interface_to_friendly_descr($if_real) . " because IP Reputation preprocessor changes require a restart.");
		}

		// Sync to configured CARP slaves if any are enabled
		snort_sync_on_changes();

		// We have saved changes and done a soft restart, so clear "dirty" flag
		clear_subsystem_dirty('snort_iprep');
		$pconfig = $natent;
		$pconfig['blist_files'] = $a_nat['blist_files'];
		$pconfig['wlist_files'] = $a_nat['wlist_files'];
	}
	else {
		$pconfig = $_POST;
		$pconfig['blist_files'] = $a_nat['blist_files'];
		$pconfig['wlist_files'] = $a_nat['wlist_files'];
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat['interface']);
if (empty($if_friendly)) {
	$if_friendly = "None";
}
$pglinks = array("", "/snort/snort_interfaces.php", "/snort/snort_interfaces_edit.php?id={$id}", "@self");
$pgtitle = array("Services", "Snort", "Interface Settings", "{$if_friendly} - IP Reputation");
include("head.inc");

/* Display Alert message */
if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

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
$tab_array[] = array($menu_iface . gettext(" Settings"), false, "/snort/snort_interfaces_edit.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Categories"), false, "/snort/snort_rulesets.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Rules"), false, "/snort/snort_rules.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Variables"), false, "/snort/snort_define_servers.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Preprocs"), false, "/snort/snort_preprocessors.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), true, "/snort/snort_ip_reputation.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
display_top_tabs($tab_array, true);
?>

<form action="snort_ip_reputation.php" method="post" enctype="multipart/form-data" name="iform" id="iform" class="form-horizontal">
<input name="id" type="hidden" value="<?=$id;?>" />
<input type="hidden" id="mode" name="mode" value="" />
<input name="iplist" id="iplist" type="hidden" value="" />
<input name="list_id" id="list_id" type="hidden" value="" />

<?php

if (is_subsystem_dirty('snort_iprep')) {
	$msg  = '<div class="pull-left">';
	$msg .= sprintf(gettext('A change has been made to blacklist or whitelist file assignments.%sYou must apply the changes in order for them to take effect.'), '<br/>');
	$msg .= '</div>';
	$msg .= '<div class="pull-right"><button type="submit" class="btn btn-default btn-warning" name="apply" value="Apply Changes">Apply Changes</button></div>';
	print '<div class="alert-warning clearfix" role="alert">' . $msg . '<br/></div>';
}

$section = new Form_Section('IP Reputation Preprocessor Configuration');
$section->addInput(new Form_Checkbox(
	'reputation_preproc',
	'Enable IP Reputation',
	'Use IP Reputation Lists on this interface.  Default is Not Checked.',
	$pconfig['reputation_preproc'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Input(
	'iprep_memcap',
	'Memory Cap',
	'text',
	$pconfig['iprep_memcap']
))->setHelp('Maximum memory in megabytes (MB) supported for IP Reputation Lists. Default is 500.  The minimum value is 1 MB and the maximum is 4095 MB.  Enter an integer value between 1 and 4095.');
$group = new Form_Group('Scan Local');
$group->add(new Form_Checkbox(
	'iprep_scan_local',
	'',
	'Scan RFC 1918 addresses on this interface.  Default is Not Checked.',
	$pconfig['iprep_scan_local'] == 'on' ? true:false,
	'on'
))->setHelp('When checked, Snort will inspect addresses in the 10/8, 172.16/12 and 192.168/16 ranges defined in RFC 1918.  If these address ranges are used in your internal network, and this instance is on an internal interface, this option should usually be enabled (checked).');
$section->add($group);
$section->addInput(new Form_Select(
	'iprep_nested_ip',
	'Nested IP',
	$pconfig['iprep_nested_ip'],
	array( 'inner' => 'Inner', 'outer' => 'Outer', 'both' => 'Both')
))->setHelp('Specify which IP address to use for whitelist/blacklist matching when there is IP encapsulation. Default is Inner.');
$section->addInput(new Form_Select(
	'iprep_priority',
	'Priority',
	$pconfig['iprep_priority'],
	array( 'blacklist' => 'Blacklist', 'whitelist' => 'Whitelist')
))->setHelp('Specify which list has priority when source/destination is on blacklist while destination/source is on whitelist. Default is Whitelist.');
$section->addInput(new Form_Select(
	'iprep_white',
	'Whitelist Meaning',
	$pconfig['iprep_white'],
	array( 'unblack' => 'Unblack', 'trust' => 'Trust')
))->setHelp('Specify the meaning of whitelist. "Unblack" unblacks blacklisted IP addresses and routes them for further inspection.  "Trust" means the packet bypasses all further Snort detection.  Default is "Unblack".');

$btnsave = new Form_Button(
	'save',
	'Save',
	null,
	'fa-solid fa-save'
);
$btnsave->addClass('btn-primary')->addClass('btn-default');
$btnsave->setAttribute('title', gettext('Save configuration and live-reload the running Snort configuration'));
$section->addInput(new Form_StaticText(
	null,
	$btnsave
));

print($section);

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("IP Reputation List Management")?></h2></div>
	<div class="panel-body">

		<div class="table-responsive">

			<table class="table table-condensed">
				<tbody>
				<tr>
					<td><b><?=gettext("Blacklist Files"); ?></b></td>
					<td>
					<!-- blist_chooser -->
					<div id="blistChooser" name="blistChooser" style="display:none; border:1px dashed gray; width:98%;"></div>
						<table class="table table-striped table-hover table-condensed">
							<thead>
								<tr>
									<th><?=gettext("Blacklist Filename"); ?></th>
									<th><?=gettext("Modification Time"); ?></th>
									<th><button type="button" class="btn btn-sm btn-success" name="blist_add" id="blist_add" title="<?=gettext('Assign a blacklist file');?>">
										<i class="fa-solid fa-plus icon-embed-btn"></i>
										<?=gettext("Add");?></button>
									</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach($pconfig['blist_files']['item'] as $k => $f):
									$class = "";
									if (!file_exists("{$iprep_path}{$f}")) {
										$filedate = gettext("Unknown -- file missing");
										$class = 'class="text-danger"';
									}
									else
										$filedate = date('M-d Y   g:i a', filemtime("{$iprep_path}{$f}"));
							 ?>
								<tr>
									<td <?=$class;?>><?=htmlspecialchars($f);?></td>
									<td <?=$class;?>><?=$filedate;?></td>
									<td>
										<i class="fa-solid fa-trash-can icon-pointer text-info" onClick="$('#list_id').val('<?=$k;?>');$('#mode').val('blist_del');$('#iform').submit();" 
									 	title="<?=gettext('Remove this blacklist file');?>"></i>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
							<tfoot>
								<tr>
									<td colspan="2"><span class="text-info"><strong><?=gettext("Note: ");?></strong>
									<?=gettext("changes to blacklist assignments are immediately saved.");?></span></td>
								</tr>
							</tfoot>
						</table>
					</td>
				</tr>
				<tr>
					<td><b><?=gettext("Whitelist Files"); ?></b></td>
					<td>
					<!-- wlist_chooser -->
					<div id="wlistChooser" name="wlistChooser" style="display:none; border:1px dashed gray; width:98%;"></div>
						<table class="table table-striped table-hover table-condensed">
							<thead>
								<tr>
									<th><?=gettext("Whitelist Filename"); ?></th>
									<th><?=gettext("Modification Time"); ?></th>
									<th><button type="button" class="btn btn-sm btn-success" name="wlist_add" id="wlist_add" title="<?=gettext('Assign a whitelist file');?>">
										<i class="fa-solid fa-plus icon-embed-btn"></i>
										<?=gettext("Add");?></button>
									</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach($pconfig['wlist_files']['item'] as $k => $f):
									$class = "";
									if (!file_exists("{$iprep_path}{$f}")) {
										$filedate = gettext("Unknown -- file missing");
										$class = 'class="text-danger"';
									}
									else
										$filedate = date('M-d Y   g:i a', filemtime("{$iprep_path}{$f}"));
							 ?>
								<tr>
									<td <?=$class;?>><?=htmlspecialchars($f);?></td>
									<td <?=$class;?>><?=$filedate;?></td>
									<td>
										<i class="fa-solid fa-trash-can icon-pointer text-info" onClick="$('#list_id').val('<?=$k;?>');$('#mode').val('wlist_del');$('#iform').submit();" 
										title="<?=gettext('Remove this whitelist file');?>"></i>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
							<tfoot>
								<tr>
									<td colspan="2"><span class="text-info"><strong><?=gettext("Note: ");?></strong>
									<?=gettext("changes to whitelist assignments are immediately saved.");?></span></td>
								</tr>
							</tfoot>
						</table>
					</td>
				</tr>
				</tbody>
			</table>

		</div>

	</div>
</div>
</form>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

	function blistChoose() {
		$('#blistChooser').fadeIn('250');
		if($('fbCurrentDir'))
			$('#fbCurrentDir').html("Loading ...");

		$.ajax(
			"/snort/snort_iprep_list_browser.php", {
				type: "post",
				data: "container=blistChooser&target=iplist",
				complete: blistComplete
			}
		);
	}

	function wlistChoose() {
		$('#wlistChooser').fadeIn('250');
		if($('fbCurrentDir'))
			$('#fbCurrentDir').html("Loading ...");

		$.ajax(
			"/snort/snort_iprep_list_browser.php", {
				type: "post",
				data: "container=wlistChooser&target=iplist",
				complete: wlistComplete
			}
		);
	}

	function blistComplete(req) {
		$('#blistChooser').html(req.responseText);

		var actions = {
			fbClose: function() {
				$("#blistChooser").hide();
			},

			fbFile:  function() {
				$("#iplist").val(this.id);
				$("#mode").val('blist_add');
				$(form).submit();
			 }
		}

		for(var type in actions) {
			$("#blistChooser ." + type).each(
				function() {
				$(this).click(actions[type]);
				$(this).css("cursor","pointer");
				}
			);
		}
	}

	function wlistComplete(req) {
		$('#wlistChooser').html(req.responseText);

		var actions = {
			fbClose: function() {
				$("#wlistChooser").hide();
			},

			fbFile:  function() {
				$("#iplist").val(this.id);
				$("#mode").val('wlist_add');
				$(form).submit();
			 }
		}

		for(var type in actions) {
			$("#wlistChooser ." + type).each(
				function() {
				$(this).click(actions[type]);
				$(this).css("cursor","pointer");
				}
			);
		}
	}

	// ---------- Click handlers ---------------------------------------------------------

	$('#blist_add').click(function() {
				$('#blistChooser').fadeIn('250');
				blistChoose();
	});

	$('#wlist_add').click(function() {
				$('#wlistChooser').fadeIn('250');
				wlistChoose();
	});

});

//]]>
</script>

<?php include("foot.inc"); ?>

