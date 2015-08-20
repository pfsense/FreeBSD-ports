<?php
/*
 * suricata_logs_mgmt.php
 *
 * Portions of this code are based on original work done for the
 * Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
 * All rights reserved.
 *
 * Adapted for Suricata by:
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
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g;

$suricatadir = SURICATADIR;

$pconfig = array();

// Grab saved settings from configuration
$pconfig['enable_log_mgmt'] = $config['installedpackages']['suricata']['config'][0]['enable_log_mgmt'] == 'on' ? 'on' : 'off';
$pconfig['clearlogs'] = $config['installedpackages']['suricata']['config'][0]['clearlogs'];
$pconfig['suricataloglimit'] = $config['installedpackages']['suricata']['config'][0]['suricataloglimit'];
$pconfig['suricataloglimitsize'] = $config['installedpackages']['suricata']['config'][0]['suricataloglimitsize'];
$pconfig['alert_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['alert_log_limit_size'];
$pconfig['alert_log_retention'] = $config['installedpackages']['suricata']['config'][0]['alert_log_retention'];
$pconfig['block_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['block_log_limit_size'];
$pconfig['block_log_retention'] = $config['installedpackages']['suricata']['config'][0]['block_log_retention'];
$pconfig['files_json_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['files_json_log_limit_size'];
$pconfig['files_json_log_retention'] = $config['installedpackages']['suricata']['config'][0]['files_json_log_retention'];
$pconfig['http_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['http_log_limit_size'];
$pconfig['http_log_retention'] = $config['installedpackages']['suricata']['config'][0]['http_log_retention'];
$pconfig['stats_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['stats_log_limit_size'];
$pconfig['stats_log_retention'] = $config['installedpackages']['suricata']['config'][0]['stats_log_retention'];
$pconfig['tls_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['tls_log_limit_size'];
$pconfig['tls_log_retention'] = $config['installedpackages']['suricata']['config'][0]['tls_log_retention'];
$pconfig['unified2_log_limit'] = $config['installedpackages']['suricata']['config'][0]['unified2_log_limit'];
$pconfig['u2_archive_log_retention'] = $config['installedpackages']['suricata']['config'][0]['u2_archive_log_retention'];
$pconfig['file_store_retention'] = $config['installedpackages']['suricata']['config'][0]['file_store_retention'];
$pconfig['tls_certs_store_retention'] = $config['installedpackages']['suricata']['config'][0]['tls_certs_store_retention'];
$pconfig['dns_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['dns_log_limit_size'];
$pconfig['dns_log_retention'] = $config['installedpackages']['suricata']['config'][0]['dns_log_retention'];
$pconfig['eve_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['eve_log_limit_size'];
$pconfig['eve_log_retention'] = $config['installedpackages']['suricata']['config'][0]['eve_log_retention'];
$pconfig['sid_changes_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['sid_changes_log_limit_size'];
$pconfig['sid_changes_log_retention'] = $config['installedpackages']['suricata']['config'][0]['sid_changes_log_retention'];

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
if (empty($pconfig['suricataloglimit']))
	$pconfig['suricataloglimit'] = 'on';
if (empty($pconfig['suricataloglimitsize'])) {
	// Set limit to 20% of slice that is unused */
	$pconfig['suricataloglimitsize'] = round(exec('df -k /var | grep -v "Filesystem" | awk \'{print $4}\'') * .20 / 1024);
}

// Set default retention periods for rotated logs
if (!isset($pconfig['alert_log_retention']))
	$pconfig['alert_log_retention'] = "336";
if (!isset($pconfig['block_log_retention']))
	$pconfig['block_log_retention'] = "336";
if (!isset($pconfig['files_json_log_retention']))
	$pconfig['files_json_log_retention'] = "168";
if (!isset($pconfig['http_log_retention']))
	$pconfig['http_log_retention'] = "168";
if (!isset($pconfig['dns_log_retention']))
	$pconfig['dns_log_retention'] = "168";
if (!isset($pconfig['stats_log_retention']))
	$pconfig['stats_log_retention'] = "168";
if (!isset($pconfig['tls_log_retention']))
	$pconfig['tls_log_retention'] = "336";
if (!isset($pconfig['u2_archive_log_retention']))
	$pconfig['u2_archive_log_retention'] = "168";
if (!isset($pconfig['file_store_retention']))
	$pconfig['file_store_retention'] = "168";
if (!isset($pconfig['tls_certs_store_retention']))
	$pconfig['tls_certs_store_retention'] = "168";
if (!isset($pconfig['eve_log_retention']))
	$pconfig['eve_log_retention'] = "168";
if (!isset($pconfig['sid_changes_log_retention']))
	$pconfig['sid_changes_log_retention'] = "336";

// Set default log file size limits
if (!isset($pconfig['alert_log_limit_size']))
	$pconfig['alert_log_limit_size'] = "500";
if (!isset($pconfig['block_log_limit_size']))
	$pconfig['block_log_limit_size'] = "500";
if (!isset($pconfig['files_json_log_limit_size']))
	$pconfig['files_json_log_limit_size'] = "1000";
if (!isset($pconfig['http_log_limit_size']))
	$pconfig['http_log_limit_size'] = "1000";
if (!isset($pconfig['dns_log_limit_size']))
	$pconfig['dns_log_limit_size'] = "750";
if (!isset($pconfig['stats_log_limit_size']))
	$pconfig['stats_log_limit_size'] = "500";
if (!isset($pconfig['tls_log_limit_size']))
	$pconfig['tls_log_limit_size'] = "500";
if (!isset($pconfig['unified2_log_limit']))
	$pconfig['unified2_log_limit'] = "32";
if (!isset($pconfig['eve_log_limit_size']))
	$pconfig['eve_log_limit_size'] = "5000";
if (!isset($pconfig['sid_changes_log_limit_size']))
	$pconfig['sid_changes_log_limit_size'] = "250";

if ($_POST['ResetAll']) {

	// Reset all settings to their defaults
	$pconfig['alert_log_retention'] = "336";
	$pconfig['block_log_retention'] = "336";
	$pconfig['files_json_log_retention'] = "168";
	$pconfig['http_log_retention'] = "168";
	$pconfig['dns_log_retention'] = "168";
	$pconfig['stats_log_retention'] = "168";
	$pconfig['tls_log_retention'] = "336";
	$pconfig['u2_archive_log_retention'] = "168";
	$pconfig['file_store_retention'] = "168";
	$pconfig['tls_certs_store_retention'] = "168";
	$pconfig['eve_log_retention'] = "168";
	$pconfig['sid_changes_log_retention'] = "336";

	$pconfig['alert_log_limit_size'] = "500";
	$pconfig['block_log_limit_size'] = "500";
	$pconfig['files_json_log_limit_size'] = "1000";
	$pconfig['http_log_limit_size'] = "1000";
	$pconfig['dns_log_limit_size'] = "750";
	$pconfig['stats_log_limit_size'] = "500";
	$pconfig['tls_log_limit_size'] = "500";
	$pconfig['unified2_log_limit'] = "32";
	$pconfig['eve_log_limit_size'] = "5000";
	$pconfig['sid_changes_log_limit_size'] = "250";

	/* Log a message at the top of the page to inform the user */
	$savemsg = gettext("All log management settings on this page have been reset to their defaults.  Click APPLY if you wish to keep these new settings.");
}

if ($_POST["save"] || $_POST['apply']) {
	if ($_POST['enable_log_mgmt'] != 'on') {
		$config['installedpackages']['suricata']['config'][0]['enable_log_mgmt'] = $_POST['enable_log_mgmt'] ? 'on' :'off';
		write_config("Suricata pkg: saved updated configuration for LOGS MGMT.");
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();

		/* forces page to reload new settings */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /suricata/suricata_logs_mgmt.php");
		exit;
	} 

	if ($_POST['suricataloglimit'] == 'on') {
		if (!is_numericint($_POST['suricataloglimitsize']) || $_POST['suricataloglimitsize'] < 1)
			$input_errors[] = gettext("The 'Log Directory Size Limit' must be an integer value greater than zero.");
	}

	// Validate unified2 log file limit
	if (!is_numericint($_POST['unified2_log_limit']) || $_POST['unified2_log_limit'] < 1)
			$input_errors[] = gettext("The value for 'Unified2 Log Limit' must be an integer value greater than zero.");

	if (!$input_errors) {
		$config['installedpackages']['suricata']['config'][0]['enable_log_mgmt'] = $_POST['enable_log_mgmt'] ? 'on' :'off';
		$config['installedpackages']['suricata']['config'][0]['clearlogs'] = $_POST['clearlogs'] ? 'on' : 'off';
		$config['installedpackages']['suricata']['config'][0]['suricataloglimit'] = $_POST['suricataloglimit'];
		$config['installedpackages']['suricata']['config'][0]['suricataloglimitsize'] = $_POST['suricataloglimitsize'];
		$config['installedpackages']['suricata']['config'][0]['alert_log_limit_size'] = $_POST['alert_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['alert_log_retention'] = $_POST['alert_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['block_log_limit_size'] = $_POST['block_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['block_log_retention'] = $_POST['block_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['files_json_log_limit_size'] = $_POST['files_json_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['files_json_log_retention'] = $_POST['files_json_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['http_log_limit_size'] = $_POST['http_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['http_log_retention'] = $_POST['http_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['stats_log_limit_size'] = $_POST['stats_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['stats_log_retention'] = $_POST['stats_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['tls_log_limit_size'] = $_POST['tls_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['tls_log_retention'] = $_POST['tls_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['unified2_log_limit'] = $_POST['unified2_log_limit'];
		$config['installedpackages']['suricata']['config'][0]['u2_archive_log_retention'] = $_POST['u2_archive_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['file_store_retention'] = $_POST['file_store_retention'];
		$config['installedpackages']['suricata']['config'][0]['tls_certs_store_retention'] = $_POST['tls_certs_store_retention'];
		$config['installedpackages']['suricata']['config'][0]['dns_log_limit_size'] = $_POST['dns_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['dns_log_retention'] = $_POST['dns_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['eve_log_limit_size'] = $_POST['eve_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['eve_log_retention'] = $_POST['eve_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['sid_changes_log_limit_size'] = $_POST['sid_changes_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['sid_changes_log_retention'] = $_POST['sid_changes_log_retention'];

		write_config("Suricata pkg: saved updated configuration for LOGS MGMT.");
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();

		/* forces page to reload new settings */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /suricata/suricata_logs_mgmt.php");
		exit;
	}
}

$pgtitle = gettext("Suricata: Logs Management");
include_once("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000">

<?php
include_once("fbegin.inc");

/* Display Alert message, under form tag or no refresh */
if ($input_errors)
	print_input_errors($input_errors);
?>

<form action="suricata_logs_mgmt.php" method="post" enctype="multipart/form-data" name="iform" id="iform">

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
        $tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
        $tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
	$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
	$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
	$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
	$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php");
	$tab_array[] = array(gettext("Logs Mgmt"), true, "/suricata/suricata_logs_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
	$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
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
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Remove Suricata Log Files During Package Uninstall"); ?></td>
	<td width="78%" class="vtable"><input name="clearlogs" id="clearlogs" type="checkbox" value="yes"
	<?php if ($config['installedpackages']['suricata']['config'][0]['clearlogs']=="on") echo " checked"; ?>/>&nbsp;
	<?php echo gettext("Suricata log files will be removed when the Suricata package is uninstalled."); ?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Auto Log Management"); ?></td>
	<td width="78%" class="vtable"><input name="enable_log_mgmt" id="enable_log_mgmt" type="checkbox" value="on"
	<?php if ($config['installedpackages']['suricata']['config'][0]['enable_log_mgmt']=="on") echo " checked"; ?> onClick="enable_change();"/>&nbsp;
	<?php echo gettext("Enable automatic unattended management of Suricata logs using parameters specified below."); ?><br/>
	<span class="red"><strong><?=gettext("Note: ") . "</strong></span>" . gettext("This must be be enabled in order to set Log Size and Retention Limits below.");?>
	</td>
</tr>
<tr>
	<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Logs Directory Size Limit"); ?></td>
</tr>
<tr>
<?php $suricatalogCurrentDSKsize = round(exec('df -k /var | grep -v "Filesystem" | awk \'{print $4}\'') / 1024); ?>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Log Directory Size " .
	"Limit"); ?><br/><br/><br/><br/><br/><br/><br/>
	<span class="red"><strong><?php echo gettext("Note:"); ?></strong></span><br/>
	<?php echo gettext("Available space is"); ?> <strong><?php echo $suricatalogCurrentDSKsize; ?>&nbsp;MB</strong></td>
	<td width="78%" class="vtable">
		<table cellpadding="0" cellspacing="0">
			<tr>
				<td colspan="2" class="vexpl"><input name="suricataloglimit" type="radio" id="suricataloglimit_on" value="on" 
					<?php if($pconfig['suricataloglimit']=='on') echo 'checked'; ?> onClick="enable_change_dirSize();"/>
					&nbsp;<strong><?php echo gettext("Enable"); ?></strong> <?php echo gettext("directory size limit"); ?> (<strong><?php echo gettext("Default"); ?></strong>)</td>
			</tr>
			<tr>
				<td colspan="2" class="vexpl"><input name="suricataloglimit" type="radio" id="suricataloglimit_off" value="off" 
					<?php if($pconfig['suricataloglimit']=='off') echo 'checked'; ?> onClick="enable_change_dirSize();"/>
					&nbsp;<strong><?php echo gettext("Disable"); ?></strong>
					<?php echo gettext("directory size limit"); ?><br/>
				<br/><span class="red"><strong><?=gettext("Note: ");?></strong></span><?=gettext("this setting imposes a hard-limit on the combined log directory size of all Suricata interfaces.  ") . 
				gettext("When the size limit set is reached, rotated logs for all interfaces will be removed, and any active logs pruned to zero-length.");?>
				<br/><br/>
				<span class="red"><strong><?php echo gettext("Warning:"); ?></strong></span> <?php echo gettext("NanoBSD " .
				"should use no more than 10MB of space."); ?></td>
			</tr>
		</table>
		<table width="100%" border="0" cellpadding="2" cellspacing="0">
			<tr>
				<td class="vexpl"><?php echo gettext("Size in ") . "<strong>" . gettext("MB:") . "</strong>";?>&nbsp;
				<input name="suricataloglimitsize" type="text" class="formfld unknown" id="suricataloglimitsize" size="10" value="<?=htmlspecialchars($pconfig['suricataloglimitsize']);?>"/>
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
					<td class="listbg">alerts</td>
					<td class="listr" align="center"><select name="alert_log_limit_size" class="formselect" id="alert_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['alert_log_limit_size']) echo "selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="alert_log_retention" class="formselect" id="alert_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['alert_log_retention']) echo "selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("Suricata alerts and event details");?></td>
				</tr>
				<tr>
					<td class="listbg">block</td>
					<td class="listr" align="center"><select name="block_log_limit_size" class="formselect" id="block_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['block_log_limit_size']) echo "selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="block_log_retention" class="formselect" id="block_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['block_log_retention']) echo "selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("Suricata blocked IPs and event details");?></td>
				</tr>
				<tr>
					<td class="listbg">dns</td>
					<td class="listr" align="center"><select name="dns_log_limit_size" class="formselect" id="dns_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['dns_log_limit_size']) echo "selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="dns_log_retention" class="formselect" id="dns_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['dns_log_retention']) echo "selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("DNS request/reply details");?></td>
				</tr>
				<tr>
					<td class="listbg">eve-json</td>
					<td class="listr" align="center"><select name="eve_log_limit_size" class="formselect" id="eve_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['eve_log_limit_size']) echo "selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="eve_log_retention" class="formselect" id="eve_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['eve_log_retention']) echo "selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("Eve-JSON (JavaScript Object Notation) data");?></td>
				</tr>
				<tr>
					<td class="listbg">files-json</td>
					<td class="listr" align="center"><select name="files_json_log_limit_size" class="formselect" id="files_json_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['files_json_log_limit_size']) echo "selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="files_json_log_retention" class="formselect" id="files_json_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['files_json_log_retention']) echo "selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("Captured files info in JSON format");?></td>
				</tr>
				<tr>
					<td class="listbg">http</td>
					<td class="listr" align="center"><select name="http_log_limit_size" class="formselect" id="http_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['http_log_limit_size']) echo "selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="http_log_retention" class="formselect" id="http_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['http_log_retention']) echo "selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("Captured HTTP events and session info");?></td>
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
							<?php if ($k == $pconfig['sid_changes_log_retention']) echo "selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("Log of SID changes made by SID Mgmt conf files");?></td>
				</tr>

				<tr>
					<td class="listbg">stats</td>
					<td class="listr" align="center"><select name="stats_log_limit_size" class="formselect" id="stats_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['stats_log_limit_size']) echo "selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="stats_log_retention" class="formselect" id="stats_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['stats_log_retention']) echo "selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("Suricata performance statistics");?></td>
				</tr>
				<tr>
					<td class="listbg">tls</td>
					<td class="listr" align="center"><select name="tls_log_limit_size" class="formselect" id="tls_log_limit_size">
						<?php foreach ($log_sizes as $k => $l): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['tls_log_limit_size']) echo "selected"; ?>>
								<?=htmlspecialchars($l);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listr" align="center"><select name="tls_log_retention" class="formselect" id="tls_log_retention">
						<?php foreach ($retentions as $k => $p): ?>
							<option value="<?=$k;?>"
							<?php if ($k == $pconfig['tls_log_retention']) echo "selected"; ?>>
								<?=htmlspecialchars($p);?></option>
						<?php endforeach; ?>
						</select>
					</td>
					<td class="listbg"><?=gettext("SMTP TLS handshake details");?></td>
				</tr>
			</tbody>
		</table>
		<br/><?=gettext("Settings will be ignored for any log in the list above not enabled on the Interface Settings tab. ") . 
		gettext("When a log reaches the Max Size limit, it will be rotated and tagged with a timestamp.  The Retention period determines ") . 
		gettext("how long rotated logs are kept before they are automatically deleted.");?>
	</td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Unified2 Log Limit"); ?></td>
	<td width="78%" class="vtable">
		<input name="unified2_log_limit" type="text" class="formfld unknown" 
		id="unified2_log_limit" size="10" value="<?=htmlspecialchars($pconfig['unified2_log_limit']);?>"/>
		&nbsp;<?php echo gettext("Log file size limit in megabytes (MB). Default is "); ?><strong><?=gettext("32 MB.");?></strong><br/>
		<?php echo gettext("This sets the maximum size for a unified2 log file before it is rotated and a new one created."); ?>
	</td>
</tr>
<tr>
	<td class="vncell" width="22%" valign="top"><?=gettext("Unified2 Archived Log Retention Period");?></td>
	<td width="78%" class="vtable"><select name="u2_archive_log_retention" class="formselect" id="u2_archive_log_retention">
		<?php foreach ($retentions as $k => $p): ?>
			<option value="<?=$k;?>"
			<?php if ($k == $pconfig['u2_archive_log_retention']) echo "selected"; ?>>
				<?=htmlspecialchars($p);?></option>
		<?php endforeach; ?>
		</select>&nbsp;<?=gettext("Choose retention period for archived Barnyard2 binary log files. Default is ") . "<strong>" . gettext("7 days."). "</strong>";?><br/><br/>
		<?=gettext("When Barnyard2 output is enabled, Suricata writes event data to a binary format file that Barnyard2 reads and processes. ") . 
		gettext("When finished processing a file, Barnyard2 moves it to an archive folder.  This setting determines how long files ") . 
		gettext("remain in the archive folder before they are automatically deleted.");?>
	</td>
</tr>
<tr>
	<td class="vncell" width="22%" valign="top"><?=gettext("Captured Files Retention Period");?></td>
	<td width="78%" class="vtable"><select name="file_store_retention" class="formselect" id="file_store_retention">
		<?php foreach ($retentions as $k => $p): ?>
			<option value="<?=$k;?>"
			<?php if ($k == $pconfig['file_store_retention']) echo "selected"; ?>>
				<?=htmlspecialchars($p);?></option>
		<?php endforeach; ?>
		</select>&nbsp;<?=gettext("Choose retention period for captured files in File Store. Default is ") . "<strong>" . gettext("7 days."). "</strong>";?><br/><br/>
		<?=gettext("When file capture and store is enabled, Suricata captures downloaded files from HTTP sessions and stores them, along with metadata, ") . 
		gettext("for later analysis.  This setting determines how long files remain in the File Store folder before they are automatically deleted.");?>
	</td>
</tr>
<tr>
	<td class="vncell" width="22%" valign="top"><?=gettext("Captured TLS Certs Retention Period");?></td>
	<td width="78%" class="vtable"><select name="tls_certs_store_retention" class="formselect" id="tls_certs_store_retention">
		<?php foreach ($retentions as $k => $p): ?>
			<option value="<?=$k;?>"
			<?php if ($k == $pconfig['tls_certs_store_retention']) echo "selected"; ?>>
				<?=htmlspecialchars($p);?></option>
		<?php endforeach; ?>
		</select>&nbsp;<?=gettext("Choose retention period for captured TLS Certs. Default is ") . "<strong>" . gettext("7 days."). "</strong>";?><br/><br/>
		<?=gettext("When custom rules with tls.store are enabled, Suricata captures Certificates, along with metadata, ") . 
		gettext("for later analysis.  This setting determines how long files remain in the Certs folder before they are automatically deleted.");?>
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
	</span><?php echo gettext("Changing any settings on this page will affect all Suricata-configured interfaces.");?></td>
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
	document.iform.block_log_limit_size.disabled = endis;
	document.iform.block_log_retention.disabled = endis;
	document.iform.files_json_log_limit_size.disabled = endis;
	document.iform.files_json_log_retention.disabled = endis;
	document.iform.http_log_limit_size.disabled = endis;
	document.iform.http_log_retention.disabled = endis;
	document.iform.stats_log_limit_size.disabled = endis;
	document.iform.stats_log_retention.disabled = endis;
	document.iform.tls_log_limit_size.disabled = endis;
	document.iform.tls_log_retention.disabled = endis;
	document.iform.unified2_log_limit.disabled = endis;
	document.iform.u2_archive_log_retention.disabled = endis;
	document.iform.file_store_retention.disabled = endis;
	document.iform.dns_log_retention.disabled = endis;
	document.iform.dns_log_limit_size.disabled = endis;
	document.iform.eve_log_retention.disabled = endis;
	document.iform.eve_log_limit_size.disabled = endis;
	document.iform.sid_changes_log_retention.disabled = endis;
	document.iform.sid_changes_log_limit_size.disabled = endis;
}

function enable_change_dirSize() {
	var endis = !(document.getElementById('suricataloglimit_on').checked);
	document.getElementById('suricataloglimitsize').disabled = endis;
}

enable_change();
enable_change_dirSize();
</script>

<?php include("fend.inc"); ?>

</body>
</html>
