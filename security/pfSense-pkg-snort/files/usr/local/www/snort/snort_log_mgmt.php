<?php
/*
 * snort_log_mgmt.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2018 Bill Meeks
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

global $g;

$snortdir = SNORTDIR;

$pconfig = array();

// Grab saved settings from configuration
if (isset($_POST['save']))
	$pconfig = $_POST;
else {
	$pconfig['enable_log_mgmt'] = $config['installedpackages']['snortglobal']['enable_log_mgmt'] == "on" ? 'on' : 'off';
	$pconfig['clearlogs'] = $config['installedpackages']['snortglobal']['clearlogs'] == "on" ? 'on' : 'off';
	$pconfig['snortloglimit'] = $config['installedpackages']['snortglobal']['snortloglimit'] == "on" ? 'on' : 'off';
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
}
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

if (isset($_POST['ResetAll'])) {

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

if (isset($_POST['save']) || isset($_POST['apply'])) {
	if ($_POST['enable_log_mgmt'] != 'on') {
		$config['installedpackages']['snortglobal']['enable_log_mgmt'] = 'off';
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
		$config['installedpackages']['snortglobal']['enable_log_mgmt'] = $_POST['enable_log_mgmt'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['clearlogs'] = $_POST['clearlogs'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['snortloglimit'] = $_POST['snortloglimit'] ? 'on' : 'off';
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

$pgtitle = array(gettext('Services'), gettext('Snort'), gettext('Log Management'));
include_once("head.inc");

/* Display Alert message, under form tag or no refresh */
if ($input_errors)
	print_input_errors($input_errors);
?>

<form action="snort_log_mgmt.php" method="post" enctype="multipart/form-data" name="iform" id="iform" class="form-horizontal">

<?php
if ($savemsg) {
	/* Display save message */
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
$tab_array[] = array(gettext("Log Mgmt"), true, "/snort/snort_log_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);

$section = new Form_Section('General Settings');
$section->addInput(new Form_Checkbox(
	'clearlogs',
	'Remove Snort Logs On Package Uninstall',
	'Snort log files will be removed when the Snort package is uninstalled.',
	$pconfig['clearlogs'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'enable_log_mgmt',
	'Auto Log Management',
	'Enable automatic unattended management of Snort logs using parameters specified below.',
	$pconfig['enable_log_mgmt'] == 'on' ? true:false,
	'on'
));
print($section);

$section = new Form_Section('Log Directory Size Limit');
$section->addInput(new Form_Checkbox(
	'snortloglimit',
	'Log Directory Size Limit',
	'Enable Directory Size Limit',
	$pconfig['snortloglimit'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Input(
	'snortloglimitsize',
	'Log Limit Size in MB',
	'text',
	$pconfig['snortloglimitsize']
))->setHelp('This setting imposes a hard-limit on the combined log directory size of all Snort interfaces.  When the size limit set is reached, rotated logs for all interfaces will be removed, and any active logs pruned to zero-length.   (default is 20% of available free disk space)');
print ($section);

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Log Size and Retention Limits")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive col-sm-12">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("Log Name");?></th>
						<th><?=gettext("Max Size");?></th>
						<th><?=gettext("Retention");?></th>
						<th><?=gettext("Log Description");?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>alert</td>
						<td><select name="alert_log_limit_size" class="form-control" id="alert_log_limit_size">
							<?php foreach ($log_sizes as $k => $l): ?>
								<option value="<?=$k;?>"
								<?php if ($k == $pconfig['alert_log_limit_size']) echo " selected"; ?>>
									<?=htmlspecialchars($l);?></option>
							<?php endforeach; ?>
							</select>
						</td>
						<td><select name="alert_log_retention" class="form-control" id="alert_log_retention">
							<?php foreach ($retentions as $k => $p): ?>
								<option value="<?=$k;?>"
								<?php if ($k == $pconfig['alert_log_retention']) echo " selected"; ?>>
									<?=htmlspecialchars($p);?></option>
							<?php endforeach; ?>
							</select>
						</td>
						<td><?=gettext("Snort alerts and event details");?></td>
					</tr>
					<tr>
						<td>appid-stats</td>
						<td><select name="appid_stats_log_limit_size" class="form-control" id="appid_stats_log_limit_size">
							<?php foreach ($log_sizes as $k => $l): ?>
								<option value="<?=$k;?>"
								<?php if ($k == $pconfig['appid_stats_log_limit_size']) echo " selected"; ?>>
									<?=htmlspecialchars($l);?></option>
							<?php endforeach; ?>
							</select>
						</td>
						<td><select name="appid_stats_log_retention" class="form-control" id="appid_stats_log_retention">
							<?php foreach ($retentions as $k => $p): ?>
								<option value="<?=$k;?>"
								<?php if ($k == $pconfig['appid_stats_log_retention']) echo " selected"; ?>>
									<?=htmlspecialchars($p);?></option>
							<?php endforeach; ?>
							</select>
						</td>
						<td><?=gettext("Application ID statistics");?></td>
					</tr>
					<tr>
						<td>event pcaps</td>
						<td><select name="event_pkts_log_limit_size" class="form-control" id="event_pkts_log_limit_size">
								<option value="0" selected>NO LIMIT</option>
							</select>
						</td>
						<td><select name="event_pkts_log_retention" class="form-control" id="event_pkts_log_retention">
							<?php foreach ($retentions as $k => $p): ?>
								<option value="<?=$k;?>"
								<?php if ($k == $pconfig['event_pkts_log_retention']) echo " selected"; ?>>
									<?=htmlspecialchars($p);?></option>
							<?php endforeach; ?>
							</select>
						</td>
						<td><?=gettext("Snort alert related packet captures");?></td>
					</tr>
					<tr>
						<td>sid_changes</td>
						<td><select name="sid_changes_log_limit_size" class="form-control" id="sid_changes_log_limit_size">
							<?php foreach ($log_sizes as $k => $l): ?>
								<option value="<?=$k;?>"
								<?php if ($k == $pconfig['sid_changes_log_limit_size']) echo "selected"; ?>>
									<?=htmlspecialchars($l);?></option>
							<?php endforeach; ?>
							</select>
						</td>
						<td><select name="sid_changes_log_retention" class="form-control" id="sid_changes_log_retention">
							<?php foreach ($retentions as $k => $p): ?>
								<option value="<?=$k;?>"
								<?php if ($k == $pconfig['sid_changes_log_retention']) echo " selected"; ?>>
									<?=htmlspecialchars($p);?></option>
							<?php endforeach; ?>
							</select>
						</td>
						<td><?=gettext("SID changes made by SID Mgmt conf files");?></td>
					</tr>
					<tr>
						<td>stats</td>
						<td><select name="stats_log_limit_size" class="form-control" id="stats_log_limit_size">
							<?php foreach ($log_sizes as $k => $l): ?>
								<option value="<?=$k;?>"
								<?php if ($k == $pconfig['stats_log_limit_size']) echo " selected"; ?>>
									<?=htmlspecialchars($l);?></option>
							<?php endforeach; ?>
							</select>
						</td>
						<td><select name="stats_log_retention" class="form-control" id="stats_log_retention">
							<?php foreach ($retentions as $k => $p): ?>
								<option value="<?=$k;?>"
								<?php if ($k == $pconfig['stats_log_retention']) echo " selected"; ?>>
									<?=htmlspecialchars($p);?></option>
							<?php endforeach; ?>
							</select>
						</td>
						<td><?=gettext("Snort performance statistics");?></td>
					</tr>
				</tbody>
			</table>
			<div class="col-sm-12">
				<span class="help-block">
				<?=gettext("Settings will be ignored for any log in the list above not enabled on the Interface Settings tab. " . 
					"When a log reaches the Max Size limit, it will be rotated and tagged with a timestamp.  The Retention " . 
					"period determines how long rotated logs are kept before they are automatically deleted."); ?>
				</span>
			</div>
		</div>
	</div>
</div>
<div class="col-sm-10 col-sm-offset-2">
	<button type="submit" id="save" name="save" class="btn btn-primary btn-sm" title="<?=gettext('Save Log Management configuration');?>">
		<i class="fa fa-save icon-embed-btn"></i>
		<?=gettext(' Save');?>
	</button>
	<button type="submit" id="ResetAll" name="ResetAll" class="btn btn-warning btn-sm" title="<?=gettext('Reset all settings to defaults');?>">
		<i class="fa fa-refresh icon-embed-btn"></i>
		<?=gettext(' Reset');?>
	</button>
</div>
</form>

<script language="JavaScript">
//<![CDATA[
events.push(function(){

	function enable_change() {
		var endis = ! $('#enable_log_mgmt').prop('checked');
		document.iform.alert_log_limit_size.disabled = endis;
		document.iform.alert_log_retention.disabled = endis;
		document.iform.appid_stats_log_limit_size.disabled = endis;
		document.iform.appid_stats_log_retention.disabled = endis;
		document.iform.stats_log_limit_size.disabled = endis;
		document.iform.stats_log_retention.disabled = endis;
		document.iform.sid_changes_log_retention.disabled = endis;
		document.iform.sid_changes_log_limit_size.disabled = endis;
		document.iform.event_pkts_log_limit_size.disabled = endis;
		document.iform.event_pkts_log_retention.disabled = endis;
	}

	function enable_change_dirSize() {
		var endis = ! $('#snortloglimit').prop('checked');
		document.getElementById('snortloglimitsize').disabled = endis;
	}

	// ---------- Click checkbox handlers -------------------------------------------------------
	// When 'enable_log_mgmt' is clicked, disable/enable the other page form controls
	$('#enable_log_mgmt').click(function() {
		enable_change();
	});

	// When 'snortloglimit_on' is clicked, disable/enable the other page form controls
	$('#snortloglimit').click(function() {
		enable_change_dirSize();
	});

	//---------- Click button handlers ----------------------------------------------------------
	// Get confirmation if RESET button is clicked
	$('#ResetAll').click(function() {
		return confirm('<?=gettext("WARNING: This will reset ALL Log Management settings to their defaults. Click OK to continue or CANCEL to quit."); ?>');
	});

	enable_change();
	enable_change_dirSize();

});
//]]>
</script>

<?php
include("foot.inc");
?>

