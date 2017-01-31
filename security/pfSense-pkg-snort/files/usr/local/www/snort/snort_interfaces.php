<?php
/*
 * snort_interfaces.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015 Bill Meeks
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

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
$a_nat = &$config['installedpackages']['snortglobal']['rule'];

// Calculate the index of the next added Snort interface
$id_gen = count($config['installedpackages']['snortglobal']['rule']);

// Get list of configured firewall interfaces
$ifaces = get_configured_interface_list();

if (isset($_POST['del_x'])) {
	/* Delete selected Snort interfaces */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		conf_mount_rw();
		foreach ($_POST['rule'] as $rulei) {
			$if_real = get_real_interface($a_nat[$rulei]['interface']);
			$if_friendly = convert_friendly_interface_to_friendly_descr($snortcfg['interface']);
			$snort_uuid = $a_nat[$rulei]['uuid'];
			log_error("Stopping Snort on {$if_friendly}({$if_real}) due to interface deletion...");
			snort_stop($a_nat[$rulei], $if_real);
			rmdir_recursive("{$snortlogdir}/snort_{$if_real}{$snort_uuid}");
			rmdir_recursive("{$snortdir}/snort_{$snort_uuid}_{$if_real}");

			// Finally delete the interface's config entry entirely
			unset($a_nat[$rulei]);
			log_error("Deleted Snort instance on {$if_friendly}({$if_real}) per user request...");
		}
	  
		/* If all the Snort interfaces are removed, then unset the interfaces config array. */
		if (empty($a_nat))
			unset($a_nat);

		// Save updated configuration
		write_config("Snort pkg: deleted one or more Snort interfaces.");
		sleep(2);
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();	  
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
		$if_real = get_real_interface($a_nat[$delbtn_list]['interface']);
		$if_friendly = convert_friendly_interface_to_friendly_descr($snortcfg['interface']);
		$snort_uuid = $a_nat[$delbtn_list]['uuid'];
		log_error("Stopping Snort on {$if_friendly}({$if_real}) due to interface deletion...");
		snort_stop($a_nat[$delbtn_list], $if_real);
		rmdir_recursive("{$snortlogdir}/snort_{$if_real}{$snort_uuid}");
		rmdir_recursive("{$snortdir}/snort_{$snort_uuid}_{$if_real}");

		// Finally delete the interface's config entry entirely
		unset($a_nat[$delbtn_list]);
		log_error("Deleted Snort instance on {$if_friendly}({$if_real}) per user request...");

		// Save updated configuration
		write_config("Snort pkg: deleted one or more Snort interfaces.");
		sleep(2);
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();	  
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_interfaces.php");
		exit;
	}
}

/* start/stop barnyard2 */
if ($_POST['by2toggle'] && is_numericint($_POST['id'])) {
	$snortcfg = $config['installedpackages']['snortglobal']['rule'][$_POST['id']];
	$if_real = get_real_interface($snortcfg['interface']);
	$if_friendly = convert_friendly_interface_to_friendly_descr($snortcfg['interface']);

	switch ($_POST['by2toggle']) {
		case 'start':
			/* set flag to rebuild interface rules before starting Snort */
			$rebuild_rules = true;
			conf_mount_rw();
			sync_snort_package_config();
			conf_mount_ro();
			$rebuild_rules = false;
			if (snort_is_running($snortcfg['uuid'], $if_real, 'barnyard2')) {
				log_error("Restarting Barnyard2 on {$if_friendly}({$if_real}) per user request...");
				snort_barnyard_stop($snortcfg, $if_real);
				snort_barnyard_start($snortcfg, $if_real);
			}
			else {
				log_error("Starting Barnyard2 on {$if_friendly}({$if_real}) per user request...");
				snort_barnyard_start($snortcfg, $if_real);
			}
			sleep(3); // So the GUI reports correctly
			break;
		case 'stop':
			if (snort_is_running($snortcfg['uuid'], $if_real, 'barnyard2')) {
				log_error("Stopping Barnyard2 on {$if_friendly}({$if_real}) per user request...");
				snort_barnyard_stop($snortcfg, $if_real);
			}
			sleep(3); // So the GUI reports correctly
		default:
	}
}

/* start/stop snort */
if ($_POST['toggle'] && is_numericint($_POST['id'])) {
	$snortcfg = $config['installedpackages']['snortglobal']['rule'][$_POST['id']];
	$if_real = get_real_interface($snortcfg['interface']);
	$if_friendly = convert_friendly_interface_to_friendly_descr($snortcfg['interface']);

	switch ($_POST['toggle']) {
		case 'start':
			/* set flag to rebuild interface rules before starting Snort */
			$rebuild_rules = true;
			conf_mount_rw();
			sync_snort_package_config();
			conf_mount_ro();
			$rebuild_rules = false;
			if (snort_is_running($snortcfg['uuid'], $if_real)) {
				log_error("Restarting Snort on {$if_friendly}({$if_real}) per user request...");
				snort_stop($snortcfg, $if_real);
				snort_start($snortcfg, $if_real);
			}
			else {
				log_error("Starting Snort on {$if_friendly}({$if_real}) per user request...");
				snort_start($snortcfg, $if_real);
			}
			sleep(3); // So the GUI reports correctly
			break;
		case 'stop':
			if (snort_is_running($snortcfg['uuid'], $if_real)) {
				log_error("Stopping Snort on {$if_friendly}({$if_real}) per user request...");
				snort_stop($snortcfg, $if_real);
			}
			sleep(3); // So the GUI reports correctly
		default:
	}
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
<input type="hidden" name="by2toggle" id="by2toggle" value="">

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
					<th><?=gettext("Blocking"); ?></th>
					<th><?=gettext("Barnyard2 Status"); ?></th>
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

				foreach ($a_nat as $natent): ?>
				<tr id="fr<?=$nnats?>">
				<?php
					/* convert fake interfaces to real and check if iface is up */
					/* There has to be a smarter way to do this */
					$if_real = get_real_interface($natent['interface']);
					$natend_friendly = convert_friendly_interface_to_friendly_descr($natent['interface']);
					$snort_uuid = $natent['uuid'];
					if (!snort_is_running($snort_uuid, $if_real)){
						$icon_snort_msg = 'Click to start Snort on ' . $natend_friendly;
					}
					else{
						$icon_snort_msg = 'Click to restart Snort on ' . $natend_friendly;
					}
					if (!snort_is_running($snort_uuid, $if_real, 'barnyard2')){
						$icon_by2_msg = 'Click to start Barnyard2 on ' . $natend_friendly;
					}
					else{
						$icon_by2_msg = 'Click to restart Barnyard2 on ' . $natend_friendly;
					}

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
						<input type="checkbox" id="frc<?=$nnats?>" name="rule[]" value="<?=$i?>" onClick="fr_bgcolor('<?=$nnats?>')" style="margin: 0; padding: 0;">
					</td>
					<td id="frd<?=$nnats?>" ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats?>';">
						<?php
							echo $natend_friendly;
						?>
					</td>
					<td id="frd<?=$nnats?>" ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats?>';">
						<?php if ($config['installedpackages']['snortglobal']['rule'][$nnats]['enable'] == 'on') : ?>
							<?php if (snort_is_running($snort_uuid, $if_real)) : ?>
								<i class="fa fa-check-circle text-success icon-primary" title="<?=gettext('Snort is running on ' . $natend_friendly);?>"></i>
								&nbsp;&nbsp;
								<i class="fa fa-repeat icon-pointer icon-primary text-info" onclick="javascript:snort_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext($icon_snort_msg);?>"></i>
								<i class="fa fa-stop-circle-o icon-pointer icon-primary text-info" onclick="javascript:snort_iface_toggle('stop', '<?=$nnats?>');" title="<?=gettext('Click to stop Snort on ' . $natend_friendly);?>"></i>
							<?php else: ?>
								<i class="fa fa-times-circle text-warning icon-primary" title="<?=gettext('Snort is stopped on ' . $natend_friendly);?>"></i>
								&nbsp;&nbsp;
								<i class="fa fa-play-circle icon-pointer icon-primary text-info" onclick="javascript:snort_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext($icon_snort_msg);?>"></i>
							<?php endif; ?>
						<?php else : ?>
							<?=gettext('DISABLED');?>&nbsp;
						<?php endif; ?>
					</td>
					<td id="frd<?=$nnats?>" ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats?>';">
						<?php if ($config['installedpackages']['snortglobal']['rule'][$nnats]['performance'] != "") : ?>
							<?=gettext(strtoupper($config['installedpackages']['snortglobal']['rule'][$nnats]['performance']))?>
						<?php else: ?>
							<?=gettext('UNKNOWN');?>
						<?php endif; ?>
					</td>
					<td id="frd<?=$nnats?>" ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats?>';">
						<?php if ($config['installedpackages']['snortglobal']['rule'][$nnats]['blockoffenders7'] == 'on') : ?>
							<?=gettext('ENABLED');?>
						<?php else: ?>
							<?=gettext('DISABLED');?>
						<?php endif; ?>
					</td>
					<td id="frd<?=$nnats?>" ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats?>';">
						<?php if ($config['installedpackages']['snortglobal']['rule'][$nnats]['barnyard_enable'] == 'on') : ?>
							<?php if (snort_is_running($snort_uuid, $if_real, 'barnyard2')) : ?>
								<i class="fa fa-check-circle text-success icon-primary" title="<?=gettext('Barnyard2 is running on ' . $natend_friendly);?>"></i>
								&nbsp;&nbsp;
								<i class="fa fa-repeat icon-pointer text-info icon-primary" onclick="javascript:by2_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext($icon_by2_msg);?>"></i>
								<i class="fa fa-stop-circle-o icon-pointer text-info icon-primary" onclick="javascript:by2_iface_toggle('stop', '<?=$nnats?>');" title="<?=gettext('Click to stop Barnyard2 on ' . $natend_friendly);?>"></i>
							<?php else: ?>
								<i class="fa fa-times-circle text-warning icon-primary" title="<?=gettext('Barnyard2 is stopped on ' . $natend_friendly);?>"></i>
								&nbsp;&nbsp;
								<i class="fa fa-play-circle icon-pointer text-info icon-primary" onclick="javascript:by2_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext($icon_by2_msg);?>"></i>
							<?php endif; ?>
						<?php else : ?>
							<?=gettext('DISABLED');?>&nbsp;
						<?php endif; ?>
					</td>
					<td class="bg-info" ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats?>';">
						<?=htmlspecialchars($natent['descr'])?>
					</td>
					<td>
						<a href="snort_interfaces_edit.php?id=<?=$nnats;?>" class="fa fa-pencil icon-primary" title="<?=gettext('Edit this Snort interface mapping');?>"></a>
						<?php if ($id_gen < count($ifaces)): ?>
							<a href="snort_interfaces_edit.php?id=<?=$nnats?>&action=dup" class="fa fa-clone" title="<?=gettext('Clone this Snort instance to an available interface');?>"></a>
						<?php endif; ?>
						<a style="cursor:pointer;" class="fa fa-trash no-confirm icon-primary" id="Xldel_<?=$nnats?>" title="<?=gettext('Delete this Snort interface mapping'); ?>"></a>
						<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="ldel_<?=$nnats?>" name="ldel_<?=$nnats?>" value="ldel_<?=$nnats?>" title="<?=gettext('Delete this Snort interface mapping'); ?>">Delete this Snort interface mapping</button>
					</td>	
				</tr>
				<?php $i++; $nnats++; endforeach; ob_end_flush(); ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<?php if ($id_gen < count($ifaces)): ?>
		<a href="snort_interfaces_edit.php?id=<?=$id_gen?>" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add Snort interface mapping');?>">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
	<?php endif; ?>
	<?php if ($id_gen > 0): ?>
		<button type="submit" name="del_x" id="del_x" class="btn btn-danger btn-sm no-confirm" title="<?=gettext('Delete selected Snort interface mapping(s)');?>" onclick="return intf_del()">
			<i class="fa fa-trash no-confirm icon-embed-btn"></i>
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
									Click on the <i class="fa fa-lg fa-pencil" alt="Edit Icon"></i> icon to edit an interface and settings.<br/>
									Click on the <i class="fa fa-lg fa-trash" alt="Delete Icon"></i> icon to delete an interface and settings.<br/>
									Click on the <i class="fa fa-lg fa-clone" alt="Clone Icon"></i> icon to clone an existing interface.
								</p>
							</div>
							<div class="col-md-6">
								<p>
									<i class="fa fa-lg fa-check-circle" alt="Running"></i> <i class="fa fa-lg fa-times" alt="Not Running"></i> icons will show current snort and barnyard2 status<br/>
									Click on the <i class="fa fa-lg fa-repeat" alt="Start"></i> or <i class="fa fa-lg fa-stop-circle-o" alt="Stop"></i> icons to start/stop Snort and Barnyard2.
								</p>
							</div>
						</div>', info)?>
</div>

<script type="text/javascript">
//<![CDATA[

	function snort_iface_toggle(action, id) {
		$('#toggle').val(action);
		$('#id').val(id);
		$('#iform').submit();
	}

	function by2_iface_toggle(action, id) {
		$('#by2toggle').val(action);
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

//]]>
</script>

<?php
include("foot.inc");
?>

