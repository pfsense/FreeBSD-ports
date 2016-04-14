<?php
/*
* suricata_download_updates.php
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

/* Define some locally required variables from Suricata constants */
$suricatadir = SURICATADIR;
$suricata_rules_upd_log = SURICATA_RULES_UPD_LOGFILE;

$snortdownload = $config['installedpackages']['suricata']['config'][0]['enable_vrt_rules'];
$emergingthreats = $config['installedpackages']['suricata']['config'][0]['enable_etopen_rules'];
$etpro = $config['installedpackages']['suricata']['config'][0]['enable_etpro_rules'];
$snortcommunityrules = $config['installedpackages']['suricata']['config'][0]['snortcommunityrules'];
$snort_rules_file = $config['installedpackages']['suricata']['config'][0]['snort_rules_file'];

/* Get last update information if available */
if (!empty($config['installedpackages']['suricata']['config'][0]['last_rule_upd_time']))
	$last_rule_upd_time = date('M-d Y H:i', $config['installedpackages']['suricata']['config'][0]['last_rule_upd_time']);
else
	$last_rule_upd_time = gettext("Unknown");
if (!empty($config['installedpackages']['suricata']['config'][0]['last_rule_upd_status']))
	$last_rule_upd_status = htmlspecialchars($config['installedpackages']['suricata']['config'][0]['last_rule_upd_status']);
else
	$last_rule_upd_status = gettext("Unknown");

$snort_community_rules_filename = GPLV2_DNLD_FILENAME;

if ($etpro == "on") {
	$emergingthreats_filename = ETPRO_DNLD_FILENAME;
	$et_name = "Emerging Threats Pro Rules";
}
else {
	$emergingthreats_filename = ET_DNLD_FILENAME;
	$et_name = "Emerging Threats Open Rules";
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
	if (file_exists("{$suricata_rules_upd_log}"))
		unlink_if_exists("{$suricata_rules_upd_log}");
}

if (isset($_POST['mode'])) {
	if ($_POST['mode'] == 'force') {
		// Mount file system R/W since we need to remove files
		conf_mount_rw();

		// Remove the existing MD5 signature files to force a download
		unlink_if_exists("{$suricatadir}{$emergingthreats_filename}.md5");
		unlink_if_exists("{$suricatadir}{$snort_community_rules_filename}.md5");
		unlink_if_exists("{$suricatadir}{$snort_rules_file}.md5");

		// Revert file system to R/O.
		conf_mount_ro();
	}
	
	// Go download the updates
	include("/usr/local/pkg/suricata/suricata_check_for_rule_updates.php");

	// Reload the page to update displayed values
	print '<script type="text/javascript">window.location = "/suricata/suricata_download_updates.php";</script>';
	return;
}

/* check for logfile */
if (file_exists("{$suricata_rules_upd_log}"))
	$suricata_rules_upd_log_chk = 'yes';
else
	$suricata_rules_upd_log_chk = 'no';

if ($_POST['view']&& $suricata_rules_upd_log_chk == 'yes') {
	$contents = @file_get_contents($suricata_rules_upd_log);
	if (empty($contents))
		$input_errors[] = gettext("Unable to read log file: {$suricata_rules_upd_log}");
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

<form action="suricata_download_updates.php" enctype="multipart/form-data" class="form-horizontal" method="post" name="iform" id="iform">

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
					<td><b><?=gettext("Snort VRT Rules");?></b></td>
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
					<br/><button class="btn btn-primary" disabled="disabled">
						<i class="fa fa-check icon-embed-btn"></i>
						<?=gettext("Update"); ?>
					</button>&nbsp;&nbsp;&nbsp;&nbsp;
					<button class="btn btn-warning" disabled="disabled">
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
						<i class="fa fa-check icon-embed-btn"></i>
						<?=gettext("Update"); ?>
					</button>&nbsp;&nbsp;&nbsp;&nbsp;
					<button name="force" id="force" class="btn btn-warning" title="<?=gettext("Force an update of all enabled rule sets")?>">
						<i class="fa fa-download icon-embed-btn"></i>
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
	'Checking for updated rule sets may take a while ... please wait ' . '<i class="content fa fa-spinner fa-pulse fa-lg text-center text-info"></i>'
));
$form->add($modal);
print($form);
?>

<div class="infoblock">
	<?=print_info_box('<strong>NOTE:</strong> <a href="http://www.snort.org/" target="_blank">Snort.org</a> and <a href="http://www.emergingthreats.net/" target="_blank">EmergingThreats.net</a> will go down from time to time. Please be patient.', info)?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

	function doRuleUpdates(mode) {
		var ajaxRequest;
		if (typeof mode == "undefined") {
			var mode = "update";
		}

		// Show the "please wait" modal
		$('#updrulesdlg').modal('show');

		ajaxRequest = $.ajax({
			url: "/suricata/suricata_download_updates.php",
			type: "post",
			data: { mode: mode }
		});

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {

			// Close the "please wait" modal
			$('#updrulesdlg').modal('hide');
		});
	}
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

