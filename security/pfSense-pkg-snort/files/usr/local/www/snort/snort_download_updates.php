<?php
/*
 * snort_download_updates.php
 * part of pfSense
 *
 * Copyright (C) 2004 Scott Ullrich
 * Copyright (C) 2011-2012 Ermal Luci
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
$snort_rules_upd_log = SNORT_RULES_UPD_LOGFILE;
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

/* Check for postback to see if we should clear the update log file. */
if (isset($_POST['clear'])) {
	unlink_if_exists($snort_rules_upd_log);
}

if (isset($_POST['update'])) {
	header("Location: /snort/snort_download_rules.php");
	exit;
}

if ($_POST['force']) {
	// Mount file system R/W since we need to remove files
	conf_mount_rw();

	// Remove the existing MD5 signature files to force a download
	unlink_if_exists("{$snortdir}/{$emergingthreats_filename}.md5");
	unlink_if_exists("{$snortdir}/{$snort_community_rules_filename}.md5");
	unlink_if_exists("{$snortdir}/{$snort_rules_file}.md5");
	unlink_if_exists("{$snortdir}/{$snort_openappid_filename}.md5");

	// Revert file system to R/O.
	conf_mount_ro();
	
	// Go download the updates
	header("Location: /snort/snort_download_rules.php");
	exit;
}

/* check for logfile */
$snort_rules_upd_logfile_chk = 'no';
if (file_exists("{$snort_rules_upd_log}"))
	$snort_rules_upd_logfile_chk = 'yes';

if ($_POST['view']&& $snort_rules_upd_logfile_chk == 'yes') {
	$contents = @file_get_contents($snort_rules_upd_log);
	if (empty($contents))
		$input_errors[] = gettext("Unable to read log file: {$snort_rules_upd_log}");
}

if ($_POST['hide'])
	$contents = "";

$pgtitle = gettext("Snort: Updates");
include_once("head.inc");
?>

<body link="#000000" vlink="#000000" alink="#000000">

<?php include("fbegin.inc"); ?>

<form action="snort_download_updates.php" method="post" name="iform" id="iform">

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
        $tab_array = array();
        $tab_array[0] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
        $tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
        $tab_array[2] = array(gettext("Updates"), true, "/snort/snort_download_updates.php");
        $tab_array[3] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
        $tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[5] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
        $tab_array[6] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
	$tab_array[7] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
	$tab_array[8] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[9] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
	$tab_array[10] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
        display_top_tabs($tab_array, true);
?>
</td></tr>
<tr>
		<td>
		<div id="mainarea">
		<table id="maintable4" class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td valign="top" class="listtopic" align="center"><?php echo gettext("INSTALLED RULE SET MD5 SIGNATURE");?></td>
			</tr>
			<tr>
				<td align="center"><br/>
				<table width="95%" border="0" cellpadding="2" cellspacing="2">
					<thead>
						<tr>
							<th class="listhdrr"><?=gettext("Rule Set Name/Publisher");?></th>
							<th class="listhdrr"><?=gettext("MD5 Signature Hash");?></th>
							<th class="listhdrr"><?=gettext("MD5 Signature Date");?></th>
						</tr>
					</thead>
					<tr>
						<td align="center" class="vncell vexpl"><b><?=gettext("Snort VRT Rules");?></b></td>
						<td align="center" class="vncell vexpl"><? echo trim($snort_org_sig_chk_local);?></td>
						<td align="center" class="vncell vexpl"><?php echo gettext($snort_org_sig_date);?></td>
					</tr>
					<tr>
						<td align="center" class="vncell vexpl"><b><?=gettext("Snort GPLv2 Community Rules");?></b></td>
						<td align="center" class="vncell vexpl"><? echo trim($snort_community_sig_chk_local);?></td>
						<td align="center" class="vncell vexpl"><?php echo gettext($snort_community_sig_date);?></td>
					</tr>
					<tr>
						<td align="center" class="vncell vexpl"><b><?=$et_name;?></b></td>
						<td align="center" class="vncell vexpl"><? echo trim($emergingt_net_sig_chk_local);?></td>
						<td align="center" class="vncell vexpl"><?php echo gettext($emergingt_net_sig_date);?></td>
					</tr>
					<tr>
						<td align="center" class="vncell vexpl"><b><?=gettext("Snort OpenAppID Detectors");?></b></td>
						<td align="center" class="vncell vexpl"><? echo trim($openappid_detectors_sig_chk_local);?></td>
						<td align="center" class="vncell vexpl"><?php echo gettext($openappid_detectors_sig_date);?></td>
					</tr>
				</table><br/>
				</td>
			</tr>
			<tr>
				<td valign="top" class="listtopic" align="center"><?php echo gettext("UPDATE YOUR RULE SET");?></td>
			</tr>
			<tr>
				<td align="center">
					<table width="45%" border="0" cellpadding="0" cellspacing="0">
						<tbody>
						<tr>
							<td class="list" align="right"><strong><?php echo gettext("Last Update:");?></strong></td>
							<td class="list" align="left"><?php echo $last_rule_upd_time;?></td>
						</tr>
						<tr>
							<td class="list" align="right"><strong><?php echo gettext("Result:");?></strong></td>
							<td class="list" align="left"><?php echo $last_rule_upd_status;?></td>
						</tr>
						</tbody>
					</table>
				</td>
			</tr>
			<tr>
				<td align="center">
					<?php if ($snortdownload != 'on' && $emergingthreats != 'on' && $etpro != 'on'): ?>
						<br/><button disabled="disabled"><?=gettext("Check");?></button>&nbsp;&nbsp;&nbsp;&nbsp;
						<button disabled="disabled"><?=gettext("Force");?></button>
						<br/>
						<p style="text-align:center;" class="vexpl">
						<font class="red"><b><?php echo gettext("WARNING:");?></b></font>&nbsp;
						<?php echo gettext('No rule types have been selected for download. ') . 
						gettext('Visit the ') . '<a href="/snort/snort_interfaces_global.php">Global Settings Tab</a>' . gettext(' to select rule types.'); ?>
						<br/></p>
					<?php else: ?>
						<br/>
						<input type="submit" value="<?=gettext("Update");?>" name="update" id="update" class="formbtn" 
						title="<?php echo gettext("Check for and apply new update to enabled rule sets"); ?>"/>&nbsp;&nbsp;&nbsp;&nbsp;
						<input type="submit" value="<?=gettext("Force");?>" name="force" id="force" class="formbtn" 
						title="<?=gettext("Force an update of all enabled rule sets");?>" 
						onclick="return confirm('<?=gettext("This will zero-out the MD5 hashes to force a fresh download of enabled rule sets.  Click OK to continue or CANCEL to quit");?>');"/>
						<br/><br/>
					<?php endif; ?>
				</td>
			</tr>

			<tr>
				<td valign="top" class="listtopic" align="center"><?php echo gettext("MANAGE RULE SET LOG");?></td>
			</tr>
			<tr>
				<td align="center" valign="middle" class="vexpl">
					<?php if ($snort_rules_upd_logfile_chk == 'yes'): ?>
						<br/>
					<?php if (!empty($contents)): ?>
						<input type="submit" value="<?php echo gettext("Hide"); ?>" name="hide" id="hide" class="formbtn" 
						title="<?php echo gettext("Hide rules update log"); ?>"/>
					<?php else: ?>
						<input type="submit" value="<?php echo gettext("View"); ?>" name="view" id="view" class="formbtn" 
						title="<?php echo gettext("View rules update log"); ?>"/>
					<?php endif; ?>
						&nbsp;&nbsp;&nbsp;&nbsp;
						<input type="submit" value="<?php echo gettext("Clear"); ?>" name="clear" id="clear" class="formbtn" 
						title="<?php echo gettext("Clear rules update log"); ?>" onClick="return confirm('Are you sure you want to delete the log contents?\nOK to confirm, or CANCEL to quit');"/>
						<br/>
					<?php else: ?>
						<br/>
						<button disabled='disabled'><?php echo gettext("View Log"); ?></button><br/><?php echo gettext("Log is empty."); ?><br/>
					<?php endif; ?>
					<br/><?php echo gettext("The log file is limited to 1024K in size and automatically clears when the limit is exceeded."); ?><br/><br/>
				</td>
			</tr>
			<?php if (!empty($contents)): ?>
				<tr>
					<td valign="top" class="listtopic" align="center"><?php echo gettext("RULE SET UPDATE LOG");?></td>
				</tr>
				<tr>
					<td align="center">
						<div style="background: #eeeeee; width:100%; height:100%;" id="textareaitem"><!-- NOTE: The opening *and* the closing textarea tag must be on the same line. -->
							<textarea style="width:100%; height:100%;" readonly wrap="off" rows="24" cols="80" name="logtext"><?=$contents;?></textarea>
						</div>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<td align="center">
					<span class="vexpl"><br/>
					<span class="red"><b><?php echo gettext("NOTE:"); ?></b></span>
					&nbsp;<a href="http://www.snort.org/" target="_blank"><?php echo gettext("Snort.org") . "</a>" . 
					gettext(" and ") . "<a href=\"http://www.emergingthreats.net/\" target=\"_blank\">" . gettext("EmergingThreats.net") . "</a>" . 
					gettext(" will go down from time to time. Please be patient."); ?></span><br/>
				</td>
			</tr>
		</table>
		</div>
		<br>
		</td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
