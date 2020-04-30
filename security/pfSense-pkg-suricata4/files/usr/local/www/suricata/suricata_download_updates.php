<?php
/*
 * suricata_download_updates.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2019 Bill Meeks
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
require_once("/usr/local/pkg/suricata/suricata_defs.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

/* Define some locally required variables from Suricata constants */
$suricatadir = SURICATADIR;
$suricata_rules_upd_log = SURICATA_RULES_UPD_LOGFILE;

$snortdownload = $config['installedpackages']['suricata']['config'][0]['enable_vrt_rules'];
$emergingthreats = $config['installedpackages']['suricata']['config'][0]['enable_etopen_rules'];
$etpro = $config['installedpackages']['suricata']['config'][0]['enable_etpro_rules'];
$snortcommunityrules = $config['installedpackages']['suricata']['config'][0]['snortcommunityrules'];

/* Get last update information if available */
if (file_exists(SURICATADIR . "rulesupd_status")) {
	$status = explode("|", file_get_contents(SURICATADIR . "rulesupd_status"));
	$last_rule_upd_time = date('M-d Y H:i', $status[0]);
	$last_rule_upd_status = gettext($status[1]);
}
else {
	$last_rule_upd_time = gettext("Unknown");
	$last_rule_upd_status = gettext("Unknown");
}

// Check for any custom URLs and extract custom filenames
// if present, else use package default values.
if ($config['installedpackages']['suricata']['config'][0]['enable_snort_custom_url'] == 'on') {
	$snort_rules_file = trim(substr($config['installedpackages']['suricata']['config'][0]['snort_custom_url'], strrpos($config['installedpackages']['suricata']['config'][0]['snort_custom_url'], '/') + 1));
}
else {
	$snort_rules_file = $config['installedpackages']['suricata']['config'][0]['snort_rules_file'];
}
if ($config['installedpackages']['suricata']['config'][0]['enable_gplv2_custom_url'] == 'on') {
	$snort_community_rules_filename = trim(substr($config['installedpackages']['suricata']['config'][0]['gplv2_custom_url'], strrpos($config['installedpackages']['suricata']['config'][0]['gplv2_custom_url'], '/') + 1));
}
else {
	$snort_community_rules_filename = GPLV2_DNLD_FILENAME;
}
if ($etpro == "on") {
	$et_name = "Emerging Threats Pro Rules";
	if ($config['installedpackages']['suricata']['config'][0]['enable_etpro_custom_url'] == 'on') {
		$emergingthreats_filename = trim(substr($config['installedpackages']['suricata']['config'][0]['etpro_custom_rule_url'], strrpos($config['installedpackages']['suricata']['config'][0]['etpro_custom_rule_url'], '/') + 1));
	}
	else {
		$emergingthreats_filename = ETPRO_DNLD_FILENAME;
	}
}
else {
	$et_name = "Emerging Threats Open Rules";
	if ($config['installedpackages']['suricata']['config'][0]['enable_etopen_custom_url'] == 'on') {
		$emergingthreats_filename = trim(substr($config['installedpackages']['suricata']['config'][0]['etopen_custom_rule_url'], strrpos($config['installedpackages']['suricata']['config'][0]['etopen_custom_rule_url'], '/') + 1));
	}
	else {
		$emergingthreats_filename = ET_DNLD_FILENAME;
	}
}

/* quick md5 chk of downloaded rules */
if ($snortdownload == 'on') {
	$snort_org_sig_chk_local = 'Not Downloaded';
	$snort_org_sig_date = 'Not Downloaded';
}
else {
	$snort_org_sig_chk_local = 'Not Enabled';
	$snort_org_sig_date = 'Not Enabled';
}
if ($snortdownload == 'on' && file_exists("{$suricatadir}{$snort_rules_file}.md5")){
	$snort_org_sig_chk_local = file_get_contents("{$suricatadir}{$snort_rules_file}.md5");
	$snort_org_sig_date = date(DATE_RFC850, filemtime("{$suricatadir}{$snort_rules_file}.md5"));
}

if ($etpro == "on" || $emergingthreats == "on") {
	$emergingt_net_sig_chk_local = 'Not Downloaded';
	$emergingt_net_sig_date = 'Not Downloaded';
}
else {
	$emergingt_net_sig_chk_local = 'Not Enabled';
	$emergingt_net_sig_date = 'Not Enabled';
}
if (($etpro == "on" || $emergingthreats == "on") && file_exists("{$suricatadir}{$emergingthreats_filename}.md5")) {
	$emergingt_net_sig_chk_local = file_get_contents("{$suricatadir}{$emergingthreats_filename}.md5");
	$emergingt_net_sig_date = date(DATE_RFC850, filemtime("{$suricatadir}{$emergingthreats_filename}.md5"));
}

if ($snortcommunityrules == 'on') {
	$snort_community_sig_chk_local = 'Not Downloaded';
	$snort_community_sig_sig_date = 'Not Downloaded';
}
else {
	$snort_community_sig_chk_local = 'Not Enabled';
	$snort_community_sig_sig_date = 'Not Enabled';
}
if ($snortcommunityrules == 'on' && file_exists("{$suricatadir}{$snort_community_rules_filename}.md5")) {
	$snort_community_sig_chk_local = file_get_contents("{$suricatadir}{$snort_community_rules_filename}.md5");
	$snort_community_sig_sig_date = date(DATE_RFC850, filemtime("{$suricatadir}{$snort_community_rules_filename}.md5"));
}

/* Check for postback to see if we should clear the update log file. */
if ($_POST['clear']) {
	if (file_exists(SURICATA_RULES_UPD_LOGFILE)) {
		file_put_contents(SURICATA_RULES_UPD_LOGFILE, "");
	}
}

if ($_REQUEST['updatemode']) {
	if ($_REQUEST['updatemode'] == 'force') {
		// Remove the existing MD5 signature files to force a download
		unlink_if_exists("{$suricatadir}{$emergingthreats_filename}.md5");
		unlink_if_exists("{$suricatadir}{$snort_community_rules_filename}.md5");
		unlink_if_exists("{$suricatadir}{$snort_rules_file}.md5");
	}

	// Launch a background process to download the updates
	$upd_pid = 0;
	$upd_pid = mwexec_bg("/usr/local/bin/php -f /usr/local/pkg/suricata/suricata_check_for_rule_updates.php");
	print($upd_pid);

	// If we failed to launch our background process, throw up an error for the user.
	if ($upd_pid == 0) {
		$input_errors[] = gettext("Failed to launch the background rules package update routine!  Rules update will not be done.");
	} else {
		exit;
	}
}

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

/* check for logfile */
if (file_exists("{$suricata_rules_upd_log}")) {
	if (filesize("{$suricata_rules_upd_log}") > 0) {
		$suricata_rules_upd_log_chk = 'yes';
	}
}
else {
	$suricata_rules_upd_log_chk = 'no';
}

if ($_POST['view']&& $suricata_rules_upd_log_chk == 'yes') {
	$contents = file_get_contents($suricata_rules_upd_log);
	if ($contents === FALSE) {
		$input_errors[] = gettext("Unable to read log file: {$suricata_rules_upd_log}");
	}
}

if ($_POST['hide'])
	$contents = "";

$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Update Rules Set Files"));
include_once("head.inc");
?>

<?php
	/* Display Alert message */
	if ($input_errors) {
		print_input_errors($input_errors);
	}

	if ($savemsg) {
		print_info_box($savemsg);
	}
?>

<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
	$tab_array[] = array(gettext("Updates"), true, "/suricata/suricata_download_updates.php");
	$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
	$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
	$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
	$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php");
	$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
	$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
	display_top_tabs($tab_array, true);
?>

<form action="suricata_download_updates.php" enctype="multipart/form-data" class="form-horizontal" method="post" name="iform" id="iform">

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("INSTALLED RULE SET MD5 SIGNATURES")?></h2></div>
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
					<td><b><?=$et_name;?></b></td>
					<td><?=trim($emergingt_net_sig_chk_local);?></td>
					<td><?=gettext($emergingt_net_sig_date);?></td>
				</tr>
				<tr>
					<td><b><?=gettext("Snort Subscriber Rules");?></b></td>
					<td><?=trim($snort_org_sig_chk_local);?></td>
					<td><?=gettext($snort_org_sig_date);?></td>
				</tr>
				<tr>
					<td><b><?=gettext("Snort GPLv2 Community Rules");?></b></td>
					<td><?=trim($snort_community_sig_chk_local);?></td>
					<td><?=gettext($snort_community_sig_sig_date);?></td>
				</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("UPDATE YOUR RULE SET")?></h2></div>
	<div class="panel-body">
		<div class="content">
			<p>
				<strong><?=gettext("Last Update:");?></strong> <?=$last_rule_upd_time;?><br />
				<strong><?=gettext("Result:");?></strong> <?=$last_rule_upd_status?>
			</p>
			<p>
				<?php if ($snortdownload != 'on' && $emergingthreats != 'on' && $etpro != 'on'): ?>
					<br/><button class="btn btn-primary" disabled>
						<i class="fa fa-check icon-embed-btn"></i>
						<?=gettext("Update"); ?>
					</button>&nbsp;&nbsp;&nbsp;&nbsp;
					<button class="btn btn-warning" disabled>
						<i class="fa fa-download icon-embed-btn"></i>
						<?=gettext("Force"); ?>
					</button>
					<br/>
					<p style="text-align:center;">
					<span class="text-danger"><strong><?=gettext("WARNING:")?></strong></span>
					<?=gettext('No rule types have been selected for download. ') . gettext('Visit the ') . '<a href="/suricata/suricata_global.php">Global Settings Tab</a>' . gettext(' to select rule types.'); ?></p>
				<?php else: ?>
					<br/>
					<button name="update" id="update" class="btn btn-primary"
						title="<?=gettext("Check for and apply new update to enabled rule sets"); ?>">
						<i id="updbtn" class="fa fa-check icon-embed-btn"></i>
						<?=gettext("Update"); ?>
					</button>&nbsp;&nbsp;&nbsp;&nbsp;
					<button name="force" id="force" class="btn btn-warning" title="<?=gettext("Force an update of all enabled rule sets")?>">
						<i id="forcebtn" class="fa fa-download icon-embed-btn"></i>
						<?=gettext("Force"); ?>
					</button>
					<br/><br/>
				<?php endif; ?>
			</p>
		</div>
	</div>
</div>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("MANAGE RULE SET LOG")?></h2></div>
	<div class="panel-body">
		<div class="content">
			<p>
				<?php if ($suricata_rules_upd_log_chk == 'yes'): ?>
				<?php if (!empty($contents)): ?>
					<button type="submit" value="<?=gettext("Hide"); ?>" name="hide" id="hide" class="btn btn-info" title="<?=gettext("Hide rules update log"); ?>">
						<i class="fa fa-close icon-embed-btn"></i>
						<?=gettext("Hide"); ?>
					</button>
				<?php else: ?>
					<button type="submit" value="<?=gettext("View"); ?>" name="view" id="view" class="btn btn-info" title="<?=gettext("View rules update log"); ?>">
						<i class="fa fa-file-text-o icon-embed-btn"></i>
						<?=gettext("View"); ?>
					</button>
				<?php endif; ?>
					&nbsp;&nbsp;&nbsp;&nbsp;
					<button type="submit" value="<?=gettext("Clear"); ?>" name="clear" id="clear" class="btn btn-danger" title="<?=gettext("Clear rules update log"); ?>">
						<i class="fa fa-trash icon-embed-btn"></i>
						<?=gettext("Clear"); ?>
					</button>
					<br/>
				<?php else: ?>
					<button class="btn btn-info" disabled>
						<i class="fa fa-file-text-o icon-embed-btn"></i>
						<?=gettext("View Log"); ?>
					</button><br/><?=gettext("Log is empty."); ?><br/>
				<?php endif; ?>
				<br/><?=gettext("The log file is limited to 1024K in size and automatically clears when the limit is exceeded."); ?><br/><br/>
			</p>

			<?php if (!empty($contents)): ?>
				<p><?=gettext("RULE SET UPDATE LOG")?></p>

				<div style="background: #eeeeee; width:100%; height:100%;" id="textareaitem">
					<textarea style="width:100%; height:100%;" readonly wrap="off" rows="20" cols="80" name="logtext"><?=$contents?></textarea>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
</form>

<?php

// Create a Modal Dialog for displaying a spinning icon "please wait" message while
// updating the rule sets
$form = new Form(FALSE);
$modal = new Modal('Rules Update Task', 'updrulesdlg', false, 'Close');
$modal->addInput(new Form_StaticText (
	null,
	'Updating rule sets may take a while ... please wait for the process to complete.<br/><br/>This dialog will auto-close when the update is finished.<br/><br/>' .
	'<i class="content fa fa-spinner fa-pulse fa-lg text-center text-info"></i>'
));
$form->add($modal);
print($form);
?>

<div class="infoblock">
	<?=print_info_box('<strong>NOTE:</strong> <a href="http://www.snort.org/" target="_blank">Snort.org</a> and <a href="http://www.emergingthreats.net/" target="_blank">EmergingThreats.net</a> will go down from time to time. Please be patient.', 'info')?>
</div>

<script type="text/javascript">
//<![CDATA[

function checkUpdateStatus(pid) {
	//See if update process is still running
	var repeat = true;
	var ajaxRequest2;
	var processID = pid;
	ajaxRequest2 = $.ajax({
		url: "suricata_download_updates.php",
		type: "post",
		data: { ajax: 'status',
			pid: processID
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
				}, 500);
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

	if (mode == "update") {
		$('#updbtn').toggleClass('fa-check fa-spinner');
	}
	if (mode == "force") {
		$('#forcebtn').toggleClass('fa-download fa-spinner');
	}

	ajaxRequest1 = $.ajax({
		url: "suricata_download_updates.php",
		type: "post",
		data: { updatemode: mode }
	});

	// Deal with the results of the above ajax call
	ajaxRequest1.done(function (response, textStatus, jqXHR) {
		checkUpdateStatus(response);
	});
}

events.push(function(){

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

