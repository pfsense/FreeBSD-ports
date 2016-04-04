<?php
/* $Id$ */
/*
 * snort_download_updates.php
 * part of pfSense
 *
 * Copyright (C) 2004 Scott Ullrich
 * Copyright (C) 2011-2012 Ermal Luci
 * Copyright (C) 2015 Bill Meeks
 * All rights reserved.
 *
 * part of m0n0wall as reboot.php (http://m0n0.ch/wall)
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

/* Define some locally required variables from Snort constants */
$snortdir = SNORTDIR;
$snortbinver = SNORT_BIN_VERSION;
$snortbinver = str_replace(".", "", $snortbinver);

$snort_rules_file = "snortrules-snapshot-{$snortbinver}.tar.gz";
$snort_community_rules_filename = SNORT_GPLV2_DNLD_FILENAME;
$snort_openappid_filename = SNORT_OPENAPPID_DNLD_FILENAME;

$snortdownload = $config['installedpackages']['snortglobal']['snortdownload'];
$emergingthreats = $config['installedpackages']['snortglobal']['emergingthreats'];
$etpro = $config['installedpackages']['snortglobal']['emergingthreats_pro'];
$snortcommunityrules = $config['installedpackages']['snortglobal']['snortcommunityrules'];
$openappid_detectors = $config['installedpackages']['snortglobal']['openappid_detectors'];

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
if (file_exists("{$snortdir}/{$snort_openappid_filename}.md5") && $openappid_detectors == 'on') {
	$openappid_detectors_sig_chk_local = file_get_contents("{$snortdir}/{$snort_openappid_filename}.md5");
	$openappid_detectors_sig_date = date(DATE_RFC850, filemtime("{$snortdir}/{$snort_openappid_filename}.md5"));
}

// Load the Rules Update Logfile if requested
if ($_REQUEST['ajax']) {
	if (file_exists(SNORT_RULES_UPD_LOGFILE)) {
		$contents = file_get_contents(SNORT_RULES_UPD_LOGFILE);
	}
	else {
		$contents = gettext("*** Rules Update logfile not found! ***");
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
	
	// Go download the updates
	include("/usr/local/pkg/snort/snort_check_for_rule_updates.php");

	// Reload the page to update displayed values
	header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
	header( 'Cache-Control: no-store, no-cache, must-revalidate' );
	header( 'Cache-Control: post-check=0, pre-check=0', false );
	header( 'Pragma: no-cache' );
	header("Location: /snort/snort_download_updates.php");
	return;
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
	'Checking for updated rule sets may take a while ... please wait ' . '<i class="content fa fa-spinner fa-pulse fa-lg text-center text-info"></i>'
));
$form->add($modal);

$form->add($section);

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

	function getRuleUpdateLog() {
		var ajaxRequest;

		ajaxRequest = $.ajax({
			url: "/snort/snort_download_updates.php",
			type: "post",
			data: { ajax: "ajax" }
		});

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {

			// Write the log file to the "logtext" textarea
			$('#logtext').text(response);
			$('#logtext').attr('readonly', true);
		});
	}

	function doRuleUpdates(mode) {
		var ajaxRequest;
		if (typeof mode == "undefined") {
			var mode = "update";
		}

		// Show the "please wait" modal
		$('#updrulesdlg').modal('show');

		ajaxRequest = $.ajax({
			url: "/snort/snort_download_updates.php",
			type: "post",
			data: { mode: mode }
		});

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {

			// Close the "please wait" modal
			$('#updrulesdlg').modal('hide');
		});
	}

	$('#vwupdlog').on('shown.bs.modal', function() {
		getRuleUpdateLog();
	});

	//-- Click handlers ---------------------------------
	$('#update').click(function() {
		doRuleUpdates('update');
	});

	$('#force').click(function() {
		doRuleUpdates('force');
	});

});
//]]>
</script>

<?php include("foot.inc"); ?>
