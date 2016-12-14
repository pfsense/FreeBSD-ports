<?php
/*
 * snort_download_updates.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

/* Define some locally required variables from Snort constants */
$snortdir = SNORTDIR;
$snortbinver = SNORT_BIN_VERSION;
$snortbinver = str_replace(".", "", $snortbinver);

$snort_rules_file = "snortrules-snapshot-{$snortbinver}.tar.gz";
$snort_community_rules_filename = SNORT_GPLV2_DNLD_FILENAME;
$snort_openappid_filename = SNORT_OPENAPPID_DNLD_FILENAME;
$snort_openappid_rules_filename = SNORT_OPENAPPID_RULES_FILENAME;

$snortdownload = $config['installedpackages']['snortglobal']['snortdownload'];
$emergingthreats = $config['installedpackages']['snortglobal']['emergingthreats'];
$etpro = $config['installedpackages']['snortglobal']['emergingthreats_pro'];
$snortcommunityrules = $config['installedpackages']['snortglobal']['snortcommunityrules'];
$openappid_detectors = $config['installedpackages']['snortglobal']['openappid_detectors'];
$openappid_rules_detectors = $config['installedpackages']['snortglobal']['openappid_rules_detectors'];

/* Get last update information if available */
if (!empty($config['installedpackages']['snortglobal']['last_rule_upd_time']))
	$last_rule_upd_time = date('M-d Y H:i', $config['installedpackages']['snortglobal']['last_rule_upd_time']);
else
	$last_rule_upd_time = gettext("Unknown");
if (!empty($config['installedpackages']['snortglobal']['last_rule_upd_status']))
	$last_rule_upd_status = htmlspecialchars($config['installedpackages']['snortglobal']['last_rule_upd_status']);
else
	$last_rule_upd_status = gettext("Unknown");

if ($etpro == "on") {
	$emergingthreats_filename = SNORT_ETPRO_DNLD_FILENAME;
	$et_name = gettext("Emerging Threats Pro Rules");
}
else {
	$emergingthreats_filename = SNORT_ET_DNLD_FILENAME;
	$et_name = gettext("Emerging Threats Open Rules");
}

/* quick md5 chk of downloaded rules */
if ($snortdownload == 'on') {
	$snort_org_sig_chk_local = gettext("Not Downloaded");
	$snort_org_sig_date = gettext("Not Downloaded");
}
else {
	$snort_org_sig_chk_local = gettext("Not Enabled");
	$snort_org_sig_date = gettext("Not Enabled");
}
if (file_exists("{$snortdir}/{$snort_rules_file}.md5") && $snortdownload == 'on') {
	$snort_org_sig_chk_local = file_get_contents("{$snortdir}/{$snort_rules_file}.md5");
	$snort_org_sig_date = date(DATE_RFC850, filemtime("{$snortdir}/{$snort_rules_file}.md5"));
}

if ($etpro == "on" || $emergingthreats == "on") {
	$emergingt_net_sig_chk_local = gettext("Not Downloaded");
	$emergingt_net_sig_date = gettext("Not Downloaded");
}
else {
	$emergingt_net_sig_chk_local = gettext("Not Enabled");
	$emergingt_net_sig_date = gettext("Not Enabled");
}
if (file_exists("{$snortdir}/{$emergingthreats_filename}.md5") && ($etpro == "on" || $emergingthreats == "on")) {
	$emergingt_net_sig_chk_local = file_get_contents("{$snortdir}/{$emergingthreats_filename}.md5");
	$emergingt_net_sig_date = date(DATE_RFC850, filemtime("{$snortdir}/{$emergingthreats_filename}.md5"));
}

if ($snortcommunityrules == 'on') {
	$snort_community_sig_chk_local = gettext("Not Downloaded");
	$snort_community_sig_date = gettext("Not Downloaded");
}
else {
	$snort_community_sig_chk_local = gettext("Not Enabled");
	$snort_community_sig_date = gettext("Not Enabled");
}
if (file_exists("{$snortdir}/{$snort_community_rules_filename}.md5") && $snortcommunityrules == 'on') {
	$snort_community_sig_chk_local = file_get_contents("{$snortdir}/{$snort_community_rules_filename}.md5");
	$snort_community_sig_date = date(DATE_RFC850, filemtime("{$snortdir}/{$snort_community_rules_filename}.md5"));
}

if ($openappid_detectors == 'on') {
	$openappid_detectors_sig_chk_local = gettext("Not Downloaded");
	$openappid_detectors_sig_date = gettext("Not Downloaded");
}
else {
	$openappid_detectors_sig_chk_local = gettext("Not Enabled");
	$openappid_detectors_sig_date = gettext("Not Enabled");
}
if ($openappid_rules_detectors == 'on') {
        $openappid_detectors_rules_sig_chk_local = gettext("Not Downloaded");
        $openappid_detectors_rules_sig_date = gettext("Not Downloaded");
}
else {
        $openappid_detectors_rules_sig_chk_local = gettext("Not Enabled");
        $openappid_detectors_rules_sig_date = gettext("Not Enabled");
}

if (file_exists("{$snortdir}/{$snort_openappid_filename}.md5") && $openappid_detectors == 'on') {
	$openappid_detectors_sig_chk_local = file_get_contents("{$snortdir}/{$snort_openappid_filename}.md5");
	$openappid_detectors_sig_date = date(DATE_RFC850, filemtime("{$snortdir}/{$snort_openappid_filename}.md5"));
}

if (file_exists("{$snortdir}/{$snort_openappid_rules_filename}.md5") && $openappid_rules_detectors == 'on') {
        $openappid_detectors_rules_sig_chk_local = file_get_contents("{$snortdir}/{$snort_openappid_rules_filename}.md5");
        $openappid_detectors_rules_sig_date = date(DATE_RFC850, filemtime("{$snortdir}/{$snort_openappid_rules_filename}.md5"));
}
// Check status of the background rules update process (when launched)
if ($_REQUEST['ajax'] == 'status') {
	if (is_numeric($_REQUEST['pid'])) {
		// Check for the PID launched as the rules update task
		$rc = shell_exec("/bin/ps -o pid= -p {$_REQUEST['pid']}");
		if (!empty($rc)) {
			print("RUNNING");
		} else {
			print("DONE");
		}
	} else {
		print("DONE");
	}
	exit;
}

// Load the Rules Update Logfile if requested
if ($_REQUEST['ajax'] == 'getlog') {
	if (file_exists(SNORT_RULES_UPD_LOGFILE)) {
		$contents = file_get_contents(SNORT_RULES_UPD_LOGFILE);
	}
	else {
		$contents = gettext("*** Rules Update logfile is empty! ***");
	}
	print($contents);
	exit;
}

// Check for postback to see if we should clear the update log file.
if (isset($_POST['clear'])) {
	unlink_if_exists(SNORT_RULES_UPD_LOGFILE);
	$savemsg = gettext("Snort Rules Update logfile has been cleared.");
}

if (isset($_POST['mode'])) {
	if ($_POST['mode'] == 'force') {
		// Mount file system R/W since we need to remove files
		conf_mount_rw();

		// Remove the existing MD5 signature files to force a download
		unlink_if_exists("{$snortdir}/{$emergingthreats_filename}.md5");
		unlink_if_exists("{$snortdir}/{$snort_community_rules_filename}.md5");
		unlink_if_exists("{$snortdir}/{$snort_rules_file}.md5");
		unlink_if_exists("{$snortdir}/{$snort_openappid_filename}.md5");

		// Revert file system to R/O.
		conf_mount_ro();
	}
	
	// Launch a background process to download the updates
	$upd_pid = 0;
	$upd_pid = mwexec_bg("/usr/local/bin/php-cgi -f /usr/local/pkg/snort/snort_check_for_rule_updates.php");
	print($upd_pid);

	// If we failed to launch our background process, throw up an error for the user.
	if ($upd_pid == 0) {
		$input_errors[] = gettext("Failed to launch the background rules package update routine!  Rules update will not be done.");
	} else {
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Update Rules"));
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
	$tab_array[] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
	$tab_array[] = array(gettext("Updates"), true, "/snort/snort_download_updates.php");
	$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
	$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
	$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Installed Rule Set MD5 Signature")?></h2></div>
	<div class="panel-body">
		<div class="content table-responsive">
			<table class="table table-striped table-condensed">
				<thead>
					<tr>
						<th><?=gettext("Rule Set Name/Publisher");?></th>
						<th><?=gettext("MD5 Signature Hash");?></th>
						<th><?=gettext("MD5 Signature Date");?></th>
					</tr>
				</thead>
				<tbody>
				<tr>
					<td><?=gettext("Snort VRT Rules");?></td>
					<td><?=trim($snort_org_sig_chk_local);?></td>
					<td><?=gettext($snort_org_sig_date);?></td>
				</tr>
				<tr>
					<td><?=gettext("Snort GPLv2 Community Rules");?></td>
					<td><?=trim($snort_community_sig_chk_local);?></td>
					<td><?=gettext($snort_community_sig_date);?></td>
				</tr>
				<tr>
					<td><?=$et_name;?></td>
					<td><?=trim($emergingt_net_sig_chk_local);?></td>
					<td><?=gettext($emergingt_net_sig_date);?></td>
				</tr>
				<tr>
					<td><?=gettext("Snort OpenAppID Detectors");?></td>
					<td><?=trim($openappid_detectors_sig_chk_local);?></td>
					<td><?=gettext($openappid_detectors_sig_date);?></td>
				</tr>
				<tr>
                                        <td><?=gettext("Snort OpenAppID RULES Detectors");?></td>
                                        <td><?=trim($openappid_detectors_rules_sig_chk_local);?></td>
                                        <td><?=gettext($openappid_detectors_rules_sig_date);?></td>
                                </tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php

$form = new Form(false);
$section = new Form_Section('Update Your Rule Set');
$group = new Form_Group('Last Update');

if (stristr('success', $last_rule_upd_status)) {
	$last_rule_upd_status = '<span class="bg-success text-capitalize">' . $last_rule_upd_status . '</span>';
}
else {
	$last_rule_upd_status = '<span class="bg-danger text-capitalize">' . $last_rule_upd_status . '</span>';
}

$group->add(new Form_StaticText(
	'',
	$last_rule_upd_time . '<span class="col-sm-offset-1"><b>Result: </b>' . $last_rule_upd_status . '</span>'
));
$section->add($group);

$group = new Form_Group('Update Rules');
$group->add(new Form_Button(
	'update',
	'Update Rules',
	'#',
	'fa-check'
))->removeClass('btn-primary')->addClass('btn-info')->addClass('btn-sm')->setAttribute('title', gettext("Check for and install only new updates"));
$group->add(new Form_Button(
	'force',
	'Force Update',
	'#',
	'fa-download'
))->removeClass('btn-primary')->addClass('btn-warning')->addClass('btn-sm')->setAttribute('title', gettext("Force an update of all enabled rule sets"));
$group->setHelp('Click UPDATE RULES to check for and automatically apply any new posted updates for selected rules packages.  Clicking FORCE UPDATE ' . 
		'will zero out the MD5 hashes and force the download and application of the latest versions of the enabled rules packages.');
$section->add($group);

$form->add($section);

$section = new Form_Section('Manage Rule Set Log');
$group = new Form_Group('');

$group->add(new Form_Button(
	'view',
	'View Log',
	'#',
	'fa-file-text-o'
))->removeClass('btn-primary')->addClass('btn-info')->addClass('btn-sm')->setAttribute('title', gettext('View rules update log'))->setAttribute('data-target', '#vwupdlog')->setAttribute('data-toggle', 'modal');

$group->add(new Form_Button(
	'clear',
	'Clear Log',
	null,
	'fa-trash'
))->removeClass('btn-primary')->addClass('btn-danger')->addClass('btn-sm')->setAttribute('title', gettext('Clear rules update log'));
$group->setHelp('The log file is limited to 1024K in size and is automatically cleared when that limit is exceeded.');
$section->add($group);

$group = new Form_Group('Logfile Size');
if (file_exists(SNORT_RULES_UPD_LOGFILE)) {
	$group->add(new Form_StaticText(
		'Logfile Size:',
		format_bytes(filesize(SNORT_RULES_UPD_LOGFILE))
	));
}
else {
	$group->add(new Form_StaticText(
		'Logfile Size:',
		'Log file is empty'
	));
}
$section->add($group);
$form->add($section);

// Create a Modal Dialog for displaying logfile contents when VIEW button is clicked
$modal = new Modal('Rules Update Log', 'vwupdlog', 'large', 'Close');
$modal->addInput(new Form_Textarea (
	'logtext',
	'',
	'...Loading...'
))->removeClass('form-control')->addClass('row-fluid col-sm-10')->setAttribute('rows', '10')->setAttribute('wrap', 'off');
$form->add($modal);

// Create a Modal Dialog for displaying a spinning icon "please wait" message while
// updating the rule sets
$modal = new Modal('Rules Update Task', 'updrulesdlg', false, 'Close');
$modal->addInput(new Form_StaticText (
	null,
	'Updating rule sets may take a while ... please wait for the process to complete.<br/><br/>This dialog will auto-close when the update is finished.<br/><br/>' . 
	'<i class="content fa fa-spinner fa-pulse fa-lg text-center text-info"></i>'
));
$form->add($modal);

print($form);
?>

<script type="text/javascript">
//<![CDATA[

function getRuleUpdateLog() {
	var ajaxRequest3;
	ajaxRequest3 = $.ajax({
		url: "/snort/snort_download_updates.php",
		type: "post",
		data: { ajax: 'getlog' }
	});

	ajaxRequest3.done(function (response, textStatus, jqXHR) {
		// Write the log file to the "logtext" textarea
		$('#logtext').text(response);
		$('#logtext').attr('readonly', true);
	});
}

function checkUpdateStatus(pid) {
	//See if update process is still running
	var repeat = true;
	var ajaxRequest2;
	ajaxRequest2 = $.ajax({
		url: "snort_download_updates.php",
		type: "post",
		data: { ajax: 'status',
			pid: pid
		      }
	});

	ajaxRequest2.done(function (response, textStatus, jqXHR) {
		if (response == "DONE") {
			// Close the "please wait" modal
			$('#updrulesdlg').modal('hide');
			repeat = false;

			// Reload the page to refresh displayed data
			location.reload(true);
		}
		else {
			repeat = true;
		}
		if (repeat) {
			setTimeout(function(){
				checkUpdateStatus(pid);
				}, 1000);
		}
	});
}

function doRuleUpdates(mode) {
	var ajaxRequest1;
	if (typeof mode == "undefined") {
		var mode = "update";
	}

	// Show the "please wait" modal
	$('#updrulesdlg').modal('show');

	ajaxRequest1 = $.ajax({
		url: "/snort/snort_download_updates.php",
		type: "post",
		data: { mode: mode }
	});

	ajaxRequest1.done(function (response, textStatus, jqXHR) {
		checkUpdateStatus(response);
	});
}

events.push(function(){

	$('#vwupdlog').on('shown.bs.modal', function() {
		getRuleUpdateLog();
	});

	//-- Click handlers ---------------------------------
	$('#update').click(function() {
		doRuleUpdates('update');
		return false;
	});

	$('#force').click(function() {
		doRuleUpdates('force');
		return false;
	});

});
//]]>
</script>

<?php include("foot.inc"); ?>
