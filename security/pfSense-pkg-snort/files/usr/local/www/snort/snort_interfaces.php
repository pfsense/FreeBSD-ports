<?php
/*
 * snort_interfaces.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2025 Rubicon Communications, LLC (Netgate)
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

$snortdir = SNORTDIR;
$snortlogdir = SNORTLOGDIR;
$rcdir = RCFILEPREFIX;
$snort_starting = array();

$a_nat = config_get_path('installedpackages/snortglobal/rule', []);

// Calculate the index of the next added Snort interface
$id_gen = count($a_nat);

// Get list of configured firewall interfaces
$ifaces = get_configured_interface_list();

/* Ajax call to periodically check Snort status */
/* on each configured interface.                */
if ($_POST['status'] == 'check') {
	$list = array();

	// Iterate configured Snort interfaces and get status of each
	// into an associative array.  Return the array to the Ajax
	// caller as a JSON object.
	$i = 0;
	foreach ($a_nat as $intf) {
		// Skip status update for any missing real interface
		if (($if_real = get_real_interface($intf['interface'])) == "") {
			continue;
		}
		$intf_key = "snort_" . $if_real;
		$stop_lck_file = "{$g['varrun_path']}/{$intf_key}_stopping.lck";
		$start_lck_file = "{$g['varrun_path']}/{$intf_key}_starting.lck";

		if (!snort_is_running($intf['uuid'])) {
			unlink_if_exists($stop_lck_file);
		}

		if ($intf['enable'] == "on") {
			if (snort_is_running($intf['uuid']) && !file_exists($stop_lck_file)) {
				$list[$intf_key] = "RUNNING";
				unlink_if_exists($start_lck_file);
				unset($snort_starting[$i]);
			}
			elseif (file_exists($stop_lck_file)) {
				$list[$intf_key] = "STOPPING";
				unlink_if_exists($start_lck_file);
				unset($snort_starting[$i]);
			}
			elseif (file_exists("{$g['varrun_path']}/{$intf_key}_starting.lck") || file_exists("{$g['varrun_path']}/snort_pkg_starting.lck")) {
				$list[$intf_key] = "STARTING";
				unlink_if_exists($stop_lck_file);
				$snort_starting[$i] = TRUE;
			}
			else {
				$list[$intf_key] = "STOPPED";
				unlink_if_exists($stop_lck_file);
				unlink_if_exists($start_lck_file);
				unset($snort_starting[$i]);
			}
		}
		else {
			$list[$intf_key] = "DISABLED";
		}
		$i++;
	}

	// Return a JSON encoded array as the page output
	echo json_encode($list);
	exit;
}

if (isset($_POST['del_x'])) {
	/* Delete selected Snort interfaces */
	if (count(array_get_path($_POST, 'rule', [])) > 0) {
		foreach ($_POST['rule'] as $rulei) {
			$snort_uuid = $a_nat[$rulei]['uuid'];
			$if_real = get_real_interface($a_nat[$rulei]['interface']);

			// Check that we still have the real interface defined in pfSense.
			// The real interface will return as an empty string if it has
			// been removed in pfSense.
			if ($if_real == "") {
				rmdir_recursive("{$snortlogdir}/snort_*{$snort_uuid}");
				rmdir_recursive("{$snortdir}/snort_{$snort_uuid}_*");
				logger(LOG_NOTICE, localize_text("Deleted the Snort instance on a previously removed pfSense interface per user request..."), LOG_PREFIX_PKG_SNORT);
			}
			else {
				$if_friendly = convert_friendly_interface_to_friendly_descr($snortcfg['interface']);
				logger(LOG_NOTICE, localize_text("Stopping Snort on %s(%s) due to Snort instance deletion...", $if_friendly, $if_real), LOG_PREFIX_PKG_SNORT);
				snort_stop($a_nat[$rulei], $if_real);
				rmdir_recursive("{$snortlogdir}/snort_{$if_real}{$snort_uuid}");
				rmdir_recursive("{$snortdir}/snort_{$snort_uuid}_{$if_real}");
				logger(LOG_NOTICE, localize_text("Deleted Snort instance on %s(%s) per user request...", $if_friendly, $if_real), LOG_PREFIX_PKG_SNORT);
			}

			// Finally delete the interface's config entry entirely
			unset($a_nat[$rulei]);
		}
	  
		/* If all the Snort interfaces are removed, then unset the interfaces config array. */
		if (empty($a_nat))
			config_del_path('installedpackages/snortglobal/rule');

		// Save updated configuration
		config_set_path('installedpackages/snortglobal/rule', $a_nat);
		write_config("Snort pkg: deleted one or more Snort interfaces.");
		sleep(2);
		sync_snort_package_config();
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_interfaces.php");
		exit;
	}
}
else {
	unset($delbtn_list);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/ldel_(\d+)/", $pn, $matches)) {
			$delbtn_list = $matches[1];
		}
	}
	if (is_numeric($delbtn_list) && $a_nat[$delbtn_list]) {
		$snort_uuid = $a_nat[$delbtn_list]['uuid'];
		$if_real = get_real_interface($a_nat[$delbtn_list]['interface']);

		// Check that we still have the real interface defined in pfSense.
		// The real interface will return as an empty string if it has
		// been removed in pfSense.
		if ($if_real == "") {
			rmdir_recursive("{$snortlogdir}/snort_*{$snort_uuid}");
			rmdir_recursive("{$snortdir}/snort_{$snort_uuid}_*");
			logger(LOG_NOTICE, localize_text("Deleted the Snort instance on a previously removed pfSense interface per user request..."), LOG_PREFIX_PKG_SNORT);
		}
		else {
			$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$delbtn_list]['interface']);
			logger(LOG_NOTICE, localize_text("Stopping Snort on %s(%s) due to interface deletion...", $if_friendly, $if_real), LOG_PREFIX_PKG_SNORT);
			snort_stop($a_nat[$delbtn_list], $if_real);
			rmdir_recursive("{$snortlogdir}/snort_{$if_real}{$snort_uuid}");
			rmdir_recursive("{$snortdir}/snort_{$snort_uuid}_{$if_real}");
			logger(LOG_NOTICE, localize_text("Deleted Snort instance on %s(%s) per user request...", $if_friendly, $if_real), LOG_PREFIX_PKG_SNORT);
		}

		// Finally delete the interface's config entry entirely
		unset($a_nat[$delbtn_list]);

		// Save updated configuration
		config_set_path('installedpackages/snortglobal/rule', $a_nat);
		write_config("Snort pkg: deleted one or more Snort interfaces.");
		sleep(2);
		sync_snort_package_config();
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_interfaces.php");
		exit;
	}
}

/* start/stop snort */
if ($_POST['toggle'] && is_numericint($_POST['id'])) {
	$snortcfg = config_get_path("installedpackages/snortglobal/rule/{$_POST['id']}", []);
	$if_real = get_real_interface($snortcfg['interface']);
	$if_friendly = convert_friendly_interface_to_friendly_descr($snortcfg['interface']);
	$id = $_POST['id'];
	$start_lck_file = "{$g['varrun_path']}/snort_{$if_real}_starting.lck";
	$stop_lck_file = "{$g['varrun_path']}/snort_{$if_real}_stopping.lck";

	// Snort can take several seconds to startup, so to
	// make the GUI more responsive, startup commands are
	// are executed as a background process.  The commands
	// are written to a PHP file in the 'tmp_path' which
	// is executed by a PHP command line session launched
	// as a background task.

	// Create steps for the background task to start Snort.
	// These commands will be handed off to a CLI PHP session
	// for background execution in a self-deleting PHP file.
	$snort_start_cmd = <<<EOD
	<?php
	require_once('/usr/local/pkg/snort/snort.inc');
	require_once('service-utils.inc');
	global \$g, \$rebuild_rules;
	\$snortcfg = config_get_path('installedpackages/snortglobal/rule/{$id}', []);
	\$rebuild_rules = true;
	touch('{$start_lck_file}');
	sync_snort_package_config();
	\$rebuild_rules = false;
	snort_start(\$snortcfg, '{$if_real}');
	unlink_if_exists('{$start_lck_file}');
	unlink(__FILE__);
	?>
EOD;

	switch ($_POST['toggle']) {
		case 'start':
			unlink_if_exists($stop_lck_file);
			file_put_contents("{$g['tmp_path']}/snort_{$if_real}_startcmd.php", $snort_start_cmd);
			if (snort_is_running($snortcfg['uuid'])) {
				logger(LOG_NOTICE, localize_text("Restarting Snort on %s(%s) per user request...", $if_friendly, $if_real), LOG_PREFIX_PKG_SNORT);
				snort_stop($snortcfg, $if_real);
				mwexec_bg("/usr/local/bin/php -f {$g['tmp_path']}/snort_{$if_real}_startcmd.php");
			}
			else {
				logger(LOG_NOTICE, localize_text("Starting Snort on %s(%s) per user request...", $if_friendly, $if_real), LOG_PREFIX_PKG_SNORT);
				mwexec_bg("/usr/local/bin/php -f {$g['tmp_path']}/snort_{$if_real}_startcmd.php");
			}
			$snort_starting[$id] = TRUE;
			break;
		case 'stop':
			if (snort_is_running($snortcfg['uuid'])) {
				touch($stop_lck_file);
				logger(LOG_NOTICE, localize_text("Stopping Snort on %s(%s) per user request...", $if_friendly, $if_real), LOG_PREFIX_PKG_SNORT);
				snort_stop($snortcfg, $if_real);
			}
			unset($snort_starting[$id]);
			unlink_if_exists($start_lck_file);
			break;
		default:
			unset($snort_starting[$id]);
			unlink_if_exists($start_lck_file);
			unlink_if_exists($stop_lck_file);
	}
	unset($snort_start_cmd);
}

$pgtitle = array(gettext('Services'), gettext('Snort'), gettext('Interfaces'));
include_once("head.inc");

/* Display Alert message */
if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg);
?>

<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
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

<form action="snort_interfaces.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<input type="hidden" name="id" id="id" value="">
<input type="hidden" name="toggle" id="toggle" value="">

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Interface Settings Overview")?></h2></div>
	<div class="panel-body">
		<div class=" content table-responsive">
			<table id="maintable" class="table table-striped table-hover table-condensed">
				<thead>
				<tr id="frheader">
					<th>&nbsp;</th>
					<th><?=gettext("Interface"); ?></th>
					<th><?=gettext("Snort Status"); ?></th>
					<th><?=gettext("Pattern Match"); ?></th>
					<th><?=gettext("Blocking Mode"); ?></th>
					<th><?=gettext("Description"); ?></th>
					<th><?=gettext("Actions"); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php $nnats = $i = 0;

				// Turn on buffering to speed up rendering
				ini_set('output_buffering','true');

				// Start buffering to fix display lag issues in IE9 and IE10
				ob_start(null, 0);

				/* If no interfaces are defined, then turn off the "no rules" warning */
				$no_rules_footnote = false;
				if ($id_gen == 0)
					$no_rules = false;
				else
					$no_rules = true;

				foreach ($a_nat as $i => $natent): ?>
				<tr id="fr<?=$i?>">
				<?php
					/* Convert fake interfaces to real and check if iface is up. */
					/* A null real interface indicates it has been removed from system. */
					if (($if_real = get_real_interface($natent['interface'])) == "") {
						$natent['enable'] = "off";
						$natend_friendly = gettext("Missing (removed?)");
					}
					else {
						$natend_friendly = convert_friendly_interface_to_friendly_descr($natent['interface']) . " ({$if_real})";
					}
					$snort_uuid = $natent['uuid'];
					$start_lck_file = "{$g['varrun_path']}/snort_{$if_real}_starting.lck";
					$stop_lck_file = "{$g['varrun_path']}/snort_{$if_real}_stopping.lck";

					/* See if interface has any rules defined and set boolean flag */
					$no_rules = true;
					if (isset($natent['customrules']) && !empty($natent['customrules']))
						$no_rules = false;
					if (isset($natent['rulesets']) && !empty($natent['rulesets']))
						$no_rules = false;
					if (isset($natent['ips_policy']) && !empty($natent['ips_policy']))
						$no_rules = false;
					/* Do not display the "no rules" warning if interface disabled */
					if ($natent['enable'] == "off")
						$no_rules = false;
					if ($no_rules)
						$no_rules_footnote = true;
				?>
					<td>
						<input type="checkbox" id="frc<?=$i?>" name="rule[]" value="<?=$i?>" onClick="fr_bgcolor('<?=$i?>')" style="margin: 0; padding: 0;">
					</td>
					<td id="frd<?=$i?>" ondblclick="document.location='snort_interfaces_edit.php?id=<?=$i?>';">
						<?php
							echo $natend_friendly;
						?>
					</td>
					<td id="frd<?=$i?>" ondblclick="document.location='snort_interfaces_edit.php?id=<?=$i?>';">
						<?php if ($natent['enable'] == 'on') : ?>
							<?php if (snort_is_running($snort_uuid) && !file_exists($stop_lck_file)) : ?>
								<i id="snort_<?=$if_real;?>" class="fa-solid fa-check-circle text-success" title="<?=gettext('snort is running on this interface');?>"></i>
								&nbsp;
								<i id="snort_<?=$if_real;?>_restart" class="fa-solid fa-arrow-rotate-right icon-pointer text-info" onclick="javascript:snort_iface_toggle($(this), 'start', '<?=$i?>');" title="<?=gettext('Restart snort on this interface');?>"></i>
								<i id="snort_<?=$if_real;?>_start" class="fa-solid fa-play-circle icon-pointer text-info hidden" onclick="javascript:snort_iface_toggle($(this), 'start', '<?=$i?>');" title="<?=gettext('Start snort on this interface');?>"></i>
								<i id="snort_<?=$if_real;?>_stop" class="fa-regular fa-circle-stop icon-pointer text-info" onclick="javascript:snort_iface_toggle($(this), 'stop', '<?=$i?>');" title="<?=gettext('Stop snort on this interface');?>"></i>
							<?php elseif ($snort_starting[$i] == TRUE || file_exists($start_lck_file) || file_exists("{$g['varrun_path']}/snort_pkg_starting.lck")) : ?>
								<i id="snort_<?=$if_real;?>" class="fa-solid fa-cog fa-spin text-info" title="<?=gettext('snort is starting on this interface');?>"></i>
								&nbsp;
								<i id="snort_<?=$if_real;?>_restart" class="fa-solid fa-arrow-rotate-right icon-pointer text-info hidden" onclick="javascript:snort_iface_toggle($(this), 'start', '<?=$i?>');" title="<?=gettext('Restart snort on this interface');?>"></i>
								<i id="snort_<?=$if_real;?>_start" class="fa-solid fa-play-circle icon-pointer text-info hidden" onclick="javascript:snort_iface_toggle($(this), 'start', '<?=$i?>');" title="<?=gettext('Start snort on this interface');?>"></i>
								<i id="snort_<?=$if_real?>_stop" class="fa-regular fa-circle-stop icon-pointer text-info" onclick="javascript:snort_iface_toggle($(this), 'stop', '<?=$i?>');" title="<?=gettext('Stop snort on this interface');?>"></i>
							<?php else: ?>
								<i id="snort_<?=$if_real;?>" class="fa-solid fa-times-circle text-danger" title="<?=gettext('snort is stopped on this interface');?>"></i>
								&nbsp;
								<i id="snort_<?=$if_real;?>_restart" class="fa-solid fa-arrow-rotate-right icon-pointer text-info hidden" onclick="javascript:snort_iface_toggle($(this), 'start', '<?=$i?>');" title="<?=gettext('Restart snort on this interface');?>"></i>
								<i id="snort_<?=$if_real;?>_start" class="fa-solid fa-play-circle icon-pointer text-info" onclick="javascript:snort_iface_toggle($(this), 'start', '<?=$i?>');" title="<?=gettext('Start snort on this interface');?>"></i>
								<i id="snort_<?=$if_real;?>_stop" class="fa-regular fa-circle-stop icon-pointer text-info hidden" onclick="javascript:snort_iface_toggle($(this), 'stop', '<?=$i?>');" title="<?=gettext('Stop snort on this interface');?>"></i>
							<?php endif; ?>
						<?php else : ?>
							<?=gettext('DISABLED');?>&nbsp;
						<?php endif; ?>
					</td>
					<td id="frd<?=$i?>" ondblclick="document.location='snort_interfaces_edit.php?id=<?=$i?>';">
						<?php if ($natent['performance'] != "") : ?>
							<?=gettext(strtoupper($natent['performance']))?>
						<?php else: ?>
							<?=gettext('UNKNOWN');?>
						<?php endif; ?>
					</td>
					<td id="frd<?=$i?>" ondblclick="document.location='snort_interfaces_edit.php?id=<?=$i?>';">
						<?php if ($natent['blockoffenders7'] == 'on' && config_get_path("installedpackages/snortglobal/rule/{$i}/ips_mode") == 'ips_mode_legacy') : ?>
							<?=gettext('LEGACY MODE');?>
						<?php elseif ($natent['blockoffenders7'] == 'on' && config_get_path("installedpackages/snortglobal/rule/{$i}/ips_mode") == 'ips_mode_inline') : ?>
							<?=gettext('INLINE IPS');?>
						<?php else : ?>
							<?=gettext('DISABLED');?>
						<?php endif; ?>
					</td>
					<td class="text-info" ondblclick="document.location='snort_interfaces_edit.php?id=<?=$i?>';">
						<?=htmlspecialchars($natent['descr'])?>
					</td>
					<td>
						<a href="snort_interfaces_edit.php?id=<?=$i;?>" class="fa-solid fa-pencil" title="<?=gettext('Edit this Snort interface mapping');?>"></a>
						<?php if ($id_gen < count($ifaces)): ?>
							<a href="snort_interfaces_edit.php?id=<?=$i?>&action=dup" class="fa-regular fa-clone" title="<?=gettext('Clone this Snort instance to an available interface');?>"></a>
						<?php endif; ?>
						<a style="cursor:pointer;" class="fa-solid fa-trash-can no-confirm" id="Xldel_<?=$i?>" title="<?=gettext('Delete this Snort interface mapping'); ?>"></a>
						<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="ldel_<?=$i?>" name="ldel_<?=$i?>" value="ldel_<?=$i?>" title="<?=gettext('Delete this Snort interface mapping'); ?>">Delete this Snort interface mapping</button>
					</td>	
				</tr>
				<?php endforeach; ob_end_flush(); ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<?php if ($id_gen < count($ifaces)): ?>
		<a href="snort_interfaces_edit.php?id=<?=$id_gen?>" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add Snort interface mapping');?>">
			<i class="fa-solid fa-plus icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
	<?php endif; ?>
	<?php if ($id_gen > 0): ?>
		<button type="submit" name="del_x" id="del_x" class="btn btn-danger btn-sm no-confirm" title="<?=gettext('Delete selected Snort interface mapping(s)');?>" onclick="return intf_del()">
			<i class="fa-solid fa-trash-can no-confirm icon-embed-btn"></i>
			<?=gettext('Delete');?>
		</button>
	<?php endif; ?>
</nav>
</form>

<div class="infoblock">
	<?=print_info_box('<div class="row">
							<div class="col-md-12">
								<p>This is where you can see an overview of all your interface settings. Please configure the parameters on the <strong>Global Settings</strong> tab before adding an interface.</p>
								<p><strong>Warning: New settings will not take effect until interface restart</strong></p>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<p>
									Click on the <i class="fa-lg fa-solid fa-pencil" alt="Edit Icon"></i> icon to edit an interface and settings.<br/>
									Click on the <i class="fa-lg fa-solid fa-trash-can" alt="Delete Icon"></i> icon to delete an interface and settings.<br/>
									Click on the <i class="fa-lg fa-regular fa-clone" alt="Clone Icon"></i> icon to clone an existing interface.
								</p>
							</div>
							<div class="col-md-6">
								<p>
									<i class="fa-lg fa-solid fa-check-circle" alt="Running"></i> <i class="fa-lg fa-solid fa-times" alt="Not Running"></i> icons will show current Snort status<br/>
									Click on the <i class="fa-lg fa-solid fa-arrow-rotate-right" alt="Start"></i> or <i class="fa-lg fa-solid fa-circle-stop" alt="Stop"></i> icons to start/stop Snort.
								</p>
							</div>
						</div>', 'info')?>
</div>

<script type="text/javascript">
//<![CDATA[

	function check_status() {

		// This function uses Ajax to post a query to
		// this page requesting the status of each
		// configured interface.  The result is returned
		// as a JSON array object.  A timer is set upon
		// completion to call the function again in
		// 2 seconds.  This allows dynamic updating
		// of interface status in the GUI.
		$.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>",
			{
				type: 'post',
				data: {
					status: 'check'
				},
				success: showStatus,
				complete: function() {
					setTimeout(check_status, 2000);
				}
			}
		);
	}

	function showStatus(responseData) {

		// The JSON object returned by check_status() is an associative array
		// of interface unique IDs and corresponding service status.  The
		// "key" is the service name followed by the real physical interface name.
		// The "value" of the key is either "DISABLED, STOPPED, STARTING, or RUNNING".
		//
		// Example key:  snort_em1
		//
		// Within the HTML of this page, icon controls for displaying status
		// and for starting/restarting/stopping the service are tagged with
		// control IDs using "key" followed by the icon's function.  These
		// control IDs are used in the code below to alter icon appearance
		// depending on the service status.
		//
		// Because an interface name in FreeBSD can contain CSS special characters
		// such as a period, any CSS special characters in an interface name are
		// escaped by double-backslashes in the code below.

		var data = jQuery.parseJSON(responseData);

		// Iterate the associative array and update interface status icons
		for(var key in data) {
			var service_name = key.substring(0, key.indexOf('_'));
			if (data[key] != 'DISABLED') {
				if (data[key] == 'STOPPED') {
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).removeClass('fa-solid fa-check-circle fa-cog fa-spin text-success text-info');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).addClass('fa-solid fa-times-circle text-danger');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).prop('title', service_name + ' is stopped on this interface');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_restart').addClass('hidden');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_stop').addClass('hidden');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_start').removeClass('hidden');
				}
				if (data[key] == 'STOPPING') {
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).removeClass('fa-solid fa-check-circle fa-times-circle text-success text-danger');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).addClass('fa-cog fa-solid fa-spin text-info');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).prop('title', service_name + ' is stopping on this interface');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_restart').addClass('hidden');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_start').addClass('hidden');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_stop').removeClass('hidden');
				}
				if (data[key] == 'STARTING') {
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).removeClass('fa-solid fa-check-circle fa-times-circle text-success text-danger');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).addClass('fa-cog fa-solid fa-spin text-info');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).prop('title', service_name + ' is starting on this interface');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_restart').addClass('hidden');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_start').addClass('hidden');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_stop').removeClass('hidden');
				}
				if (data[key] == 'RUNNING') {
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).removeClass('fa-solid fa-times-circle fa-cog fa-spin text-danger text-info');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).addClass('fa-solid fa-check-circle text-success');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).prop('title', service_name + ' is running on this interface');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_restart').removeClass('hidden');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_stop').removeClass('hidden');
					$('#' + key.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_start').addClass('hidden');
				}
			}
		}
	}	

	function snort_iface_toggle(elem, action, id) {
		// Peel off the first part of the control name
		// to identify the STATUS icon.
		var fldName = $(elem).attr('id');
		fldName = fldName.substring(0, fldName.lastIndexOf('_'));
		var service_name = fldName.substring(0, fldName.indexOf('_'));

		// If stopping the service, change STATUS to a spinning gear cog.
		if (action == 'stop') {
			$('#' + fldName.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).removeClass('fa-check-circle fa-solid fa-times-circle text-success text-danger');
			$('#' + fldName.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).addClass('fa-cog fa-solid fa-spin text-info');
			$('#' + fldName.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" )).prop('title', service_name + ' is stopping on this interface');
			$('#' + fldName.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" ) + '_restart').addClass('hidden');
		}
		$('#toggle').val(action);
		$('#id').val(id);
		$('#iform').submit();
	}

	function intf_del() {
		var isSelected = false;
		var inputs = document.iform.elements;
		for (var i = 0; i < inputs.length; i++) {
			if (inputs[i].type == "checkbox") {
				if (inputs[i].checked)
					isSelected = true;
			}
		}
		if (isSelected)
			return confirm('Do you really want to delete the selected Snort interface mapping(s)?');
		else
			alert("There is no Snort interface mapping selected for deletion.  Click the checkbox beside the Snort mapping(s) you wish to delete.");
	}

	events.push(function() {
		$('[id^=Xldel_]').click(function (event) {
			if(confirm("<?=gettext('Delete this Snort interface mapping?')?>")) {
				$('#' + event.target.id.slice(1)).click();
			}
		});
	});

	// Set a timer to call the check_status()
	// function in two seconds.
	setTimeout(check_status, 2000);

//]]>
</script>

<?php

include("foot.inc");
?>

