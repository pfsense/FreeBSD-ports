<?php
/*
 * snort_log_mgmt.php
 *
 * Portions of this code are based on original work done for the
 * Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
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
require_once("/usr/local/pkg/snort/snort.inc");

global $g;

$snortdir = SNORTDIR;

$pconfig = array();

// Grab saved settings from configuration
$pconfig['enable_log_mgmt'] = $config['installedpackages']['snortglobal']['enable_log_mgmt'] == 'on' ? 'on' : 'off';
$pconfig['clearlogs'] = $config['installedpackages']['snortglobal']['clearlogs'];
$pconfig['snortloglimit'] = $config['installedpackages']['snortglobal']['snortloglimit'];
$pconfig['snortloglimitsize'] = $config['installedpackages']['snortglobal']['snortloglimitsize'];
$pconfig['alert_log_limit_size'] = $config['installedpackages']['snortglobal']['alert_log_limit_size'];
$pconfig['alert_log_retention'] = $config['installedpackages']['snortglobal']['alert_log_retention'];
$pconfig['stats_log_limit_size'] = $config['installedpackages']['snortglobal']['stats_log_limit_size'];
$pconfig['stats_log_retention'] = $config['installedpackages']['snortglobal']['stats_log_retention'];
$pconfig['sid_changes_log_limit_size'] = $config['installedpackages']['snortglobal']['sid_changes_log_limit_size'];
$pconfig['sid_changes_log_retention'] = $config['installedpackages']['snortglobal']['sid_changes_log_retention'];
$pconfig['event_pkts_log_limit_size'] = '0';
$pconfig['event_pkts_log_retention'] = $config['installedpackages']['snortglobal']['event_pkts_log_retention'];
$pconfig['appid_stats_log_limit_size'] = $config['installedpackages']['snortglobal']['appid_stats_log_limit_size'];
$pconfig['appid_stats_log_retention'] = $config['installedpackages']['snortglobal']['appid_stats_log_retention'];

// Load up some arrays with selection values (we use these later).
// The keys in the $retentions array are the retention period
// converted to hours.  The keys in the $log_sizes array are
// the file size limits in KB. 
$retentions = array( '0' => gettext('KEEP ALL'), '24' => gettext('1 DAY'), '168' => gettext('7 DAYS'), '336' => gettext('14 DAYS'), 
		     '720' => gettext('30 DAYS'), '1080' => gettext("45 DAYS"), '2160' => gettext('90 DAYS'), '4320' => gettext('180 DAYS'), 
		     '8766' => gettext('1 YEAR'), '26298' => gettext("3 YEARS") );
$log_sizes = array( '0' => gettext('NO LIMIT'), '50' => gettext('50 KB'), '150' => gettext('150 KB'), '250' => gettext('250 KB'), 
		    '500' => gettext('500 KB'), '750' => gettext('750 KB'), '1000' => gettext('1 MB'), '2000' => gettext('2 MB'), 
		    '5000' => gettext("5 MB"), '10000' => gettext("10 MB") );

// Set sensible defaults for any unset parameters
if (empty($pconfig['snortloglimit']))
	$pconfig['snortloglimit'] = 'on';
if (empty($pconfig['snortloglimitsize'])) {
	// Set limit to 20% of slice that is unused */
	$pconfig['snortloglimitsize'] = round(exec('df -k /var | grep -v "Filesystem" | awk \'{print $4}\'') * .20 / 1024);
}

// Set default retention periods for rotated logs
if (!isset($pconfig['alert_log_retention']))
	$pconfig['alert_log_retention'] = "336";
if (!isset($pconfig['stats_log_retention']))
	$pconfig['stats_log_retention'] = "168";
if (!isset($pconfig['sid_changes_log_retention']))
	$pconfig['sid_changes_log_retention'] = "336";
if (!isset($pconfig['event_pkts_log_retention']))
	$pconfig['event_pkts_log_retention'] = "336";
if (!isset($pconfig['appid_stats_log_retention']))
	$pconfig['appid_stats_log_retention'] = "168";

// Set default log file size limits
if (!isset($pconfig['alert_log_limit_size']))
	$pconfig['alert_log_limit_size'] = "500";
if (!isset($pconfig['stats_log_limit_size']))
	$pconfig['stats_log_limit_size'] = "500";
if (!isset($pconfig['sid_changes_log_limit_size']))
	$pconfig['sid_changes_log_limit_size'] = "250";
if (!isset($pconfig['appid_stats_log_limit_size']))
	$pconfig['appid_stats_log_limit_size'] = "1000";

if ($_POST['ResetAll']) {

	// Reset all settings to their defaults
	$pconfig['alert_log_retention'] = "336";
	$pconfig['stats_log_retention'] = "168";
	$pconfig['sid_changes_log_retention'] = "336";
	$pconfig['event_pkts_log_retention'] = "336";
	$pconfig['appid_stats_log_retention'] = "168";

	$pconfig['alert_log_limit_size'] = "500";
	$pconfig['stats_log_limit_size'] = "500";
	$pconfig['sid_changes_log_limit_size'] = "250";
	$pconfig['event_pkts_log_limit_size'] = "0";
	$pconfig['appid_stats_log_limit_size'] = "1000";

	/* Log a message at the top of the page to inform the user */
	$savemsg = gettext("All log management settings on this page have been reset to their defaults.  Click APPLY if you wish to keep these new settings.");
}

if ($_POST["save"] || $_POST['apply']) {
	if ($_POST['enable_log_mgmt'] != 'on') {
		$config['installedpackages']['snortglobal']['enable_log_mgmt'] = $_POST['enable_log_mgmt'] ? 'on' :'off';
		write_config("Snort pkg: saved updated configuration for LOGS MGMT.");
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();

		/* forces page to reload new settings */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_log_mgmt.php");
		exit;
	} 

	if ($_POST['snortloglimit'] == 'on') {
		if (!is_numericint($_POST['snortloglimitsize']) || $_POST['snortloglimitsize'] < 1)
			$input_errors[] = gettext("The 'Log Directory Size Limit' must be an integer value greater than zero.");
	}

	if (!$input_errors) {
		$config['installedpackages']['snortglobal']['enable_log_mgmt'] = $_POST['enable_log_mgmt'] ? 'on' :'off';
		$config['installedpackages']['snortglobal']['clearlogs'] = $_POST['clearlogs'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['snortloglimit'] = $_POST['snortloglimit'];
		$config['installedpackages']['snortglobal']['snortloglimitsize'] = $_POST['snortloglimitsize'];
		$config['installedpackages']['snortglobal']['alert_log_limit_size'] = $_POST['alert_log_limit_size'];
		$config['installedpackages']['snortglobal']['alert_log_retention'] = $_POST['alert_log_retention'];
		$config['installedpackages']['snortglobal']['stats_log_limit_size'] = $_POST['stats_log_limit_size'];
		$config['installedpackages']['snortglobal']['stats_log_retention'] = $_POST['stats_log_retention'];
		$config['installedpackages']['snortglobal']['sid_changes_log_limit_size'] = $_POST['sid_changes_log_limit_size'];
		$config['installedpackages']['snortglobal']['sid_changes_log_retention'] = $_POST['sid_changes_log_retention'];
		$config['installedpackages']['snortglobal']['event_pkts_log_limit_size'] = $_POST['event_pkts_log_limit_size'];
		$config['installedpackages']['snortglobal']['event_pkts_log_retention'] = $_POST['event_pkts_log_retention'];
		$config['installedpackages']['snortglobal']['appid_stats_log_limit_size'] = $_POST['appid_stats_log_limit_size'];
		$config['installedpackages']['snortglobal']['appid_stats_log_retention'] = $_POST['appid_stats_log_retention'];

		write_config("Snort pkg: saved updated configuration for LOGS MGMT.");
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();

		/* forces page to reload new settings */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_log_mgmt.php");
		exit;
	}
}

$pgtitle = gettext("Snort: Log Management");
include_once("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000">

<?php
include_once("fbegin.inc");

/* Display Alert message, under form tag or no refresh */
if ($input_errors)
	print_input_errors($input_errors);
?>

<form action="snort_log_mgmt.php" method="post" enctype="multipart/form-data" name="iform" id="iform">

<?php
if ($savemsg) {
	/* Display save message */
	print_info_box($savemsg);
}
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
	$tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
	$tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
	$tab_array[3] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
	$tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[5] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
	$tab_array[6] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
	$tab_array[7] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
	$tab_array[8] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[9] = array(gettext("Log Mgmt"), true, "/snort/snort_log_mgmt.php");
	$tab_array[10] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
	display_top_tabs($tab_array, true);
?>
</td></tr>
<tr>
	<td>
	<div id="mainarea">
	<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
<tr>
	<td colspan="2" valign="top" class="listtopic"><?php echo gettext("General Settings"); ?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Remove Snort Log Files During Package Uninstall"); ?></td>
	<td width="78%" class="vtable"><input name="clearlogs" id="clearlogs" type="checkbox" value="yes"
	<?php if ($config['installedpackages']['snortglobal']['clearlogs']=="on") echo " checked"; ?>/>&nbsp;
	<?php echo gettext("Snort log files will be removed when the Snort package is uninstalled."); ?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Auto Log Management"); ?></td>
	<td width="78%" class="vtable"><input name="enable_log_mgmt" id="enable_log_mgmt" type="checkbox" value="on"
	<?php if ($config['installedpackages']['snortglobal']['enable_log_mgmt']=="on") echo " checked"; ?> onClick="enable_change();"/>&nbsp;
	<?php echo gettext("Enable automatic unattended management of Snort logs using parameters specified below."); ?><br/>
	<span class="red"><strong><?=gettext("Note: ") . "</strong></span>" . gettext("This must be be enabled in order to set Log Size and Retention Limits below.");?>
	</td>
</tr>
<tr>
	<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Logs Directory Size Limit"); ?></td>
</tr>
<tr>
<?php $snortlogCurrentDSKsize = round(exec('df -k /var | grep -v "Filesystem" | awk \'{print $4}\'') / 1024); ?>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Log Directory Size " .
	"Limit"); ?><br/><br/><br/><br/><br/><br/><br/>
	<span class="red"><strong><?php echo gettext("Note:"); ?></strong></span><br/>
	<?php echo gettext("Available space is"); ?> <strong><?php echo $snortlogCurrentDSKsize; ?>&nbsp;MB</strong></td>
	<td width="78%" class="vtable">
		<table cellpadding="0" cellspacing="0">
			<tr>
				<td colspan="2" class="vexpl"><input name="snortloglimit" type="radio" id="snortloglimit_on" value="on" 
					<?php if($pconfig['snortloglimit']=='on') echo 'checked'; ?> onClick="enable_change_dirSize();"/>
					&nbsp;<strong><?php echo gettext("Enable"); ?></strong> <?php echo gettext("directory size limit"); ?> (<strong><?php echo gettext("Default"); ?></strong>)</td>
			</tr>
			<tr>
				<td colspan="2" class="vexpl"><input name="snortloglimit" type="radio" id="snortloglimit_off" value="off" 
					<?php if($pconfig['snortloglimit']=='off') echo 'checked'; ?> onClick="enable_change_dirSize();"/>
					&nbsp;<strong><?php echo gettext("Disable"); ?></strong>
					<?php echo gettext("directory size limit"); ?><br/>
				<br/><span class="red"><strong><?=gettext("Note: ");?></strong></span><?=gettext("this setting imposes a hard-limit on the combined log directory size of all Snort interfaces.  ") . 
				gettext("When the size limit set is reached, rotated logs for all interfaces will be removed, and any active logs pruned to zero-length.");?>
				<br/><br/>
				<span class="red"><strong><?php echo gettext("Warning:"); ?></strong></span> <?php echo gettext("NanoBSD " .
				"should use no more than 10MB of space."); ?></td>
			</tr>
		</table>
		<table width="100%" border="0" cellpadding="2" cellspacing="0">
			<tr>
				<td class="vexpl"><?php echo gettext("Size in ") . "<strong>" . gettext("MB:") . "</strong>";?>&nbsp;
				<input name="snortloglimitsize" type="text" class="formfld unknown" id="snortloglimitsize" size="10" value="<?=htmlspecialchars($pconfig['snortloglimitsize']);?>"/>
				&nbsp;<?php echo gettext("Default is ") . "<strong>" . gettext("20%") . "</strong>" . gettext(" of available space.");?></td>
			</tr>
		</table>
	</td>
</tr>
<tr>
	<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Log Size and Retention Limits"); ?></td>
</tr>
<tr>
	<td class="vncell" valign="top" width="22%"><?php echo gettext("Text Log Settings");?></td>
	<td class="vtable" width="78%">
		<table width="100%" border="0" cellpadding="2" cellspacing="0">
			<colgroup>
				<col style="width: 15%;">
				<col style="width: 18%;">
				<col style="width: 18%;">
				<col>
			</colgroup>
			<thead>
				<tr>
					<th class="listhdrr"><?=gettext("Log Name");?></th>
					<th class="listhdrr"><?=gettext("Max Size");?></th>
					<th class="listhdrr"><?=gettext("Retention");?></th>
					<th class="listhdrr"><?=gettext("Log Description");?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td class="listbg">alert</td>
					<td class="listr" align="center"><select name="alert_log_limit_size" class="formselect" id="alert_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['alert_log_limit_size']) echo " selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="alert_log_retention" class="formselect" id="alert_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['alert_log_retention']) echo " selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("Snort alerts and event details");?></td>
				</tr>
				<tr>
					<td class="listbg">appid-stats</td>
					<td class="listr" align="center"><select name="appid_stats_log_limit_size" class="formselect" id="appid_stats_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['appid_stats_log_limit_size']) echo " selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="appid_stats_log_retention" class="formselect" id="appid_stats_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['appid_stats_log_retention']) echo " selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("Application ID statistics");?></td>
				</tr>
				<tr>
					<td class="listbg">event pcaps</td>
					<td class="listr" align="center"><select name="event_pkts_log_limit_size" class="formselect" id="event_pkts_log_limit_size">
							<option value="0" selected>NO LIMIT</option>
						</select>
					</td>
					<td class="listr" align="center"><select name="event_pkts_log_retention" class="formselect" id="event_pkts_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['event_pkts_log_retention']) echo " selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("Snort alert related packet captures");?></td>
				</tr>
				<tr>
					<td class="listbg">sid_changes</td>
					<td class="listr" align="center"><select name="sid_changes_log_limit_size" class="formselect" id="sid_changes_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['sid_changes_log_limit_size']) echo "selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="sid_changes_log_retention" class="formselect" id="sid_changes_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['sid_changes_log_retention']) echo " selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("SID changes made by SID Mgmt conf files");?></td>
				</tr>
				<tr>
					<td class="listbg">stats</td>
					<td class="listr" align="center"><select name="stats_log_limit_size" class="formselect" id="stats_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['stats_log_limit_size']) echo " selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="stats_log_retention" class="formselect" id="stats_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['stats_log_retention']) echo " selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("Snort performance statistics");?></td>
				</tr>
			</tbody>
		</table>
		<br/><?=gettext("Settings will be ignored for any log in the list above not enabled on the Interface Settings tab. ") . 
		gettext("When a log reaches the Max Size limit, it will be rotated and tagged with a timestamp.  The Retention period determines ") . 
		gettext("how long rotated logs are kept before they are automatically deleted.");?>
	</td>
</tr>
<tr>
	<td width="22%"></td>
	<td width="78%" class="vexpl"><input name="save" type="submit" class="formbtn" value="Save"/>
		&nbsp;&nbsp;&nbsp;&nbsp;<input name="ResetAll" type="submit" class="formbtn" value="Reset" title="<?php echo 
		gettext("Reset all settings to defaults") . "\" onclick=\"return confirm('" . 
		gettext("WARNING:  This will reset ALL Log Management settings to their defaults.  Click OK to continue or CANCEL to quit.") . 
		"');\""; ?>/><br/>
	<br/><span class="red"><strong><?php echo gettext("Note:");?></strong>&nbsp;
	</span><?php echo gettext("Changing any settings on this page will affect all Snort-configured interfaces.");?></td>
</tr>
	</table>
</div><br/>
</td></tr>
</table>
</form>

<script language="JavaScript">
function enable_change() {
	var endis = !(document.iform.enable_log_mgmt.checked);
	document.iform.alert_log_limit_size.disabled = endis;
	document.iform.alert_log_retention.disabled = endis;
	document.iform.stats_log_limit_size.disabled = endis;
	document.iform.stats_log_retention.disabled = endis;
	document.iform.sid_changes_log_retention.disabled = endis;
	document.iform.sid_changes_log_limit_size.disabled = endis;
	document.iform.event_pkts_log_limit_size.disabled = endis;
	document.iform.event_pkts_log_retention.disabled = endis;
}

function enable_change_dirSize() {
	var endis = !(document.getElementById('snortloglimit_on').checked);
	document.getElementById('snortloglimitsize').disabled = endis;
}

enable_change();
enable_change_dirSize();
</script>

<?php include("fend.inc"); ?>

</body>
</html>
