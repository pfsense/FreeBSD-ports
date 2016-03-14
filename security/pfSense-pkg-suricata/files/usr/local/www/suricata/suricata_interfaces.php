<?php
/*
* suricata_interfaces.php
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
* Copyright (C) 2014 Bill Meeks
*
*/

require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g, $rebuild_rules;

$suricatadir = SURICATADIR;
$suricatalogdir = SURICATALOGDIR;
$rcdir = RCFILEPREFIX;

if ($_POST['id'])
	$id = $_POST['id'];
else
	$id = 0;

if (!is_array($config['installedpackages']['suricata']['rule'])) {
	$config['installedpackages']['suricata']['rule'] = array();
}

$a_nat = &$config['installedpackages']['suricata']['rule'];
$id_gen = count($config['installedpackages']['suricata']['rule']);

// Get list of configured firewall interfaces
$ifaces = get_configured_interface_list();

if (isset($_POST['del_x'])) {
	/* delete selected interfaces */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		conf_mount_rw();
		foreach ($_POST['rule'] as $rulei) {
			$if_real = get_real_interface($a_nat[$rulei]['interface']);
			$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$rulei]['interface']);
			$suricata_uuid = $a_nat[$rulei]['uuid'];
			suricata_stop($a_nat[$rulei], $if_real);
			rmdir_recursive("{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}");
			rmdir_recursive("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}");
			unset($a_nat[$rulei]);
		}

		/* If all the Suricata interfaces are removed, then unset the config array. */
		if (empty($a_nat))
			unset($a_nat);

		write_config("Suricata pkg: deleted one or more Suricata interfaces.");
		sleep(2);

		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();

		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /suricata/suricata_interfaces.php");
		exit;
	}
} else {
	unset($delbtn_list);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/ldel_(\d+)/", $pn, $matches)) {
			$delbtn_list = $matches[1];
		}
	}

	if (is_numeric($delbtn_list) && $a_nat[$delbtn_list]) {
		$if_real = get_real_interface($a_nat[$delbtn_list]['interface']);
		$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$delbtn_list]['interface']);
		$suricata_uuid = $a_nat[$delbtn_list]['uuid'];
		log_error("Stopping Suricata on {$if_friendly}({$if_real}) due to interface deletion...");
		suricata_stop($a_nat[$delbtn_list], $if_real);
		rmdir_recursive("{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}");
		rmdir_recursive("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}");

		// Finally delete the interface's config entry entirely
		unset($a_nat[$delbtn_list]);
		log_error("Deleted Suricata instance on {$if_friendly}({$if_real}) per user request...");

		// Save updated configuration
		write_config("Suricata pkg: deleted one or more Suricata interfaces.");
		sleep(2);
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /suricata/suricata_interfaces.php");
		exit;
	}
}

/* start/stop Barnyard2 */
if ($_POST['bartoggle']) {
	$suricatacfg = $config['installedpackages']['suricata']['rule'][$id];
	$if_real = get_real_interface($suricatacfg['interface']);
	$if_friendly = convert_friendly_interface_to_friendly_descr($suricatacfg['interface']);

	if (!suricata_is_running($suricatacfg['uuid'], $if_real, 'barnyard2')) {
		log_error("Toggle (barnyard starting) for {$if_friendly}({$suricatacfg['descr']})...");
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();
		suricata_barnyard_start($suricatacfg, $if_real);
	} else {
		log_error("Toggle (barnyard stopping) for {$if_friendly}({$suricatacfg['descr']})...");
		suricata_barnyard_stop($suricatacfg, $if_real);
	}

	sleep(3); // So the GUI reports correctly
	header("Location: /suricata/suricata_interfaces.php");
	exit;
}

/* start/stop Suricata */
if ($_POST['toggle']) {
	$suricatacfg = $config['installedpackages']['suricata']['rule'][$id];
	$if_real = get_real_interface($suricatacfg['interface']);
	$if_friendly = convert_friendly_interface_to_friendly_descr($suricatacfg['interface']);

	switch($_POST['toggle']) {
		case 'start' :
				$rebuild_rules = true;
				conf_mount_rw();
				sync_suricata_package_config();
				conf_mount_ro();
				$rebuild_rules = false;

			if (suricata_is_running($suricatacfg['uuid'], $if_real)) {
				log_error("Toggle (suricata stopping) for {$if_friendly}({$suricatacfg['descr']})...");
				suricata_stop($suricatacfg, $if_real);
				suricata_start($suricatacfg, $if_real);
			} else {
				log_error("Toggle (suricata starting) for {$if_friendly}({$suricatacfg['descr']})...");
				// set flag to rebuild interface rules before starting Suricata
				suricata_start($suricatacfg, $if_real);
			}
			break;
		case 'stop' :
//			if (suricata_is_running($suricatacfg['uuid'], $if_real)) {
				log_error("Toggle (suricata stopping) for {$if_friendly}({$suricatacfg['descr']})...");
				suricata_stop($suricatacfg, $if_real);
//			}

			break;

		default:
	}

	sleep(3); // So the GUI reports correctly
}

$suri_bin_ver = SURICATA_BIN_VERSION;
$suri_pkg_ver = SURICATA_PKG_VER;

$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Interfaces"));
include_once("head.inc"); ?>

<?php
	/* Display Alert message */
	if ($input_errors)
		print_input_errors($input_errors);

	if ($savemsg)
		print_info_box($savemsg);
?>

<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Interfaces"), true, "/suricata/suricata_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
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

<form action="suricata_interfaces.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<input type="hidden" name="id" id="id" value="">
<input type="hidden" name="toggle" id="toggle" value="">
<input type="hidden" name="by2toggle" id="by2toggle" value="">

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Interface Settings Overview")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table id="maintable" class="table table-striped table-hover table-condensed">
				<thead>
				<tr id="frheader">
					<th>&nbsp;</th>
					<th><?=gettext("Interface"); ?></th>
					<th><?=gettext("Suricata"); ?></th>
					<th><?=gettext("Pattern Match"); ?></th>
					<th><?=gettext("Block"); ?></th>
					<th><?=gettext("Barnyard2"); ?></th>
					<th><?=gettext("Description"); ?></th>
					<th><?=gettext("Actions")?></th>
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

				if ($id_gen == 0) {
					$no_rules = false;
				} else {
					$no_rules = true;
				}

				foreach ($a_nat as $natent):
?>
				<tr id="fr<?=$nnats?>">
<?php
					$icon_suricata_msg = 'default';
					/* convert fake interfaces to real and check if iface is up */
					/* There has to be a smarter way to do this */
					$if_real = get_real_interface($natent['interface']);
					$natend_friendly= convert_friendly_interface_to_friendly_descr($natent['interface']);
					$suricata_uuid = $natent['uuid'];

					if (!suricata_is_running($suricata_uuid, $if_real)){
						$icon_suricata_msg = 'Click to start Suricata on ' . $natend_friendly;
					}
					else{
						$icon_suricata_msg = 'Click to restart Suricata on ' . $natend_friendly;
					}

					if (!suricata_is_running($suricata_uuid, $if_real, 'barnyard2')){
						$icon_by2_msg = 'Click to start Barnyard2 on ' . $natend_friendly;
					}
					else{
						$icon_by2_msg = 'Click to restart Barnyard2 on ' . $natend_friendly;
					}

					/* See if interface has any rules defined and set boolean flag */
					$no_rules = true;

					if (isset($natent['customrules']) && !empty($natent['customrules'])) {
						$no_rules = false;
					}

					if (isset($natent['rulesets']) && !empty($natent['rulesets'])) {
						$no_rules = false;
					}

					if (isset($natent['ips_policy']) && !empty($natent['ips_policy'])) {
						$no_rules = false;
					}

					/* Do not display the "no rules" warning if interface disabled */
					if ($natent['enable'] == "off") {
						$no_rules = false;
					}

					if ($no_rules) {
						$no_rules_footnote = true;
					}
?>
					<td>
						<input type="checkbox" id="frc<?=$nnats?>" name="rule[]" value="<?=$i?>" onClick="fr_bgcolor('<?=$nnats?>')" style="margin: 0; padding: 0;">
					</td>
					<td id="frd<?=$nnats?>"
					ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
<?php
						echo $natend_friendly;
?>
					</td>

					<td id="frd<?=$nnats?>"
					ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
<?php
					$check_suricata_info = $config['installedpackages']['suricata']['rule'][$nnats]['enable'];
?>
					<?php if ($check_suricata_info == "on") : ?>
						<?php if (suricata_is_running($suricata_uuid, $if_real)) : ?>
							<i class="fa fa-check-circle text-success icon-primary" title="<?=gettext('Suricata is running on ' . $natend_friendly);?>"></i>
							&nbsp;
							<i class="fa fa-repeat icon-pointer icon-primary text-info" onclick="javascript:suricata_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext($icon_suricata_msg);?>"></i>
							<i class="fa fa-stop-circle-o icon-pointer icon-primary text-info" onclick="javascript:suricata_iface_toggle('stop', '<?=$nnats?>');" title="<?=gettext('Click to stop Suricata on ' . $natend_friendly);?>"></i>
						<?php else: ?>
							<i class="fa fa-times-circle text-warning icon-primary" title="<?=gettext('Suricata is stopped on ' . $natend_friendly);?>"></i>
							&nbsp;
							<i class="fa fa-play-circle icon-pointer icon-primary text-info" onclick="javascript:suricata_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext($icon_suricata_msg);?>"></i>
						<?php endif; ?>
					<?php else : ?>
						<?=gettext('DISABLED');?>&nbsp;
					<?php endif; ?>

					</td>

					<td
					id="frd<?=$nnats?>"
					ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
<?php
					$check_performance_info = $config['installedpackages']['suricata']['rule'][$nnats]['mpm_algo'];

					if ($check_performance_info != "") {
						$check_performance = $check_performance_info;
					}else{
						$check_performance = "unknown";
					}

?>
						<?=strtoupper($check_performance)?>
					</td>

					<td
					id="frd<?=$nnats?>"
					ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
					<?php
					$check_blockoffenders_info = $config['installedpackages']['suricata']['rule'][$nnats]['blockoffenders'];

					if ($check_blockoffenders_info == "on")
					{
						$check_blockoffenders = enabled;
					} else {
						$check_blockoffenders = disabled;
					}
?>
					<?=strtoupper($check_blockoffenders)?>
					</td>

					<td id="frd<?=$nnats?>" ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
						<?php if ($config['installedpackages']['suricata']['rule'][$nnats]['barnyard_enable'] == 'on') : ?>
							<?php if (suricata_is_running($suricata_uuid, $if_real, 'barnyard2')) : ?>
								<i class="fa fa-check-circle text-success icon-primary" title="<?=gettext('Barnyard2 is running on ' . $natend_friendly);?>"></i>
								&nbsp;
								<i class="fa fa-repeat icon-pointer text-info icon-primary" onclick="javascript:by2_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext($icon_by2_msg);?>"></i>
								<i class="fa fa-stop-circle-o icon-pointer text-info icon-primary" onclick="javascript:by2_iface_toggle('stop', '<?=$nnats?>');" title="<?=gettext('Click to stop Barnyard2 on ' . $natend_friendly);?>"></i>
							<?php else: ?>
								<i class="fa fa-times-circle text-warning icon-primary" title="<?=gettext('Barnyard2 is stopped on ' . $natend_friendly);?>"></i>
								&nbsp;
								<i class="fa fa-play-circle icon-pointer text-info icon-primary" onclick="javascript:by2_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext($icon_by2_msg);?>"></i>
							<?php endif; ?>
						<?php else : ?>
							<?=gettext('DISABLED');?>&nbsp;
						<?php endif; ?>
					</td>

					<td ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
					<?=htmlspecialchars($natent['descr'])?>
					</td>

					<td>
						<a href="suricata_interfaces_edit.php?id=<?=$nnats;?>" class="fa fa-pencil icon-primary" title="<?=gettext('Edit this Suricata interface mapping');?>"></a>
						<?php if ($id_gen < count($ifaces)): ?>
							<a href="suricata_interfaces_edit.php?id=<?=$nnats?>&action=dup" class="fa fa-clone" title="<?=gettext('Clone this Suricata instance to an available interface');?>"></a>
						<?php endif; ?>
						<a style="cursor:pointer;" class="fa fa-trash no-confirm icon-primary" id="Xldel_<?=$nnats?>" title="<?=gettext('Delete this Suricata interface mapping'); ?>"></a>
						<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="ldel_<?=$nnats?>" name="ldel_<?=$nnats?>" value="ldel_<?=$nnats?>" title="<?=gettext('Delete this Suricata interface mapping'); ?>">Delete this Suricata interface mapping</button>
					</td>

				</tr>
				<?php $i++; $nnats++; endforeach; ob_end_flush(); ?>
				<tr>
					<td></td>
					<td colspan="7">
						<?php if ($no_rules_footnote): ?><span class="text-danger"><?=gettext("WARNING: Marked interface currently has no rules defined for Suricata"); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<?php if ($id_gen < count($ifaces)): ?>
		<a href="suricata_interfaces_edit.php?id=<?=$id_gen?>" class="btn btn-sm btn-success" title="<?=gettext('Add Suricata interface mapping')?>">
			<i class="fa fa-plus icon-embed-btn" ></i><?=gettext("Add")?>
		</a>
	<?php endif; ?>

	<?php if ($id_gen != 0): ?>
		<button type="submit" name="del_x" id="del_x" class="btn btn-danger btn-sm no-confirm" title="<?=gettext('Delete selected Suricata interface mapping(s)');?>" onclick="return intf_del()">
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
									<i class="fa fa-lg fa-check-circle" alt="Running"></i> <i class="fa fa-lg fa-times" alt="Not Running"></i> icons will show current Suricata and barnyard2 status<br/>
									Click the <i class="fa fa-lg fa-play-circle" alt="Start"></i> or <i class="fa fa-lg fa-repeat" alt="Restart"></i> or <i class="fa fa-lg fa-stop-circle-o" alt="Stop"></i> icons to start/restart/stop Suricata and Barnyard2.
								</p>
							</div>
						</div>', info, false)?>
</div>

<script type="text/javascript">
//<![CDATA[

	function suricata_iface_toggle(action, id) {
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
			return confirm('Do you really want to delete the selected Suricata mapping?');
		else
			alert("There is no Suricata mapping selected for deletion.  Click the checkbox beside the Suricata mapping(s) you wish to delete.");
	}

	events.push(function() {
		$('[id^=Xldel_]').click(function (event) {
			if(confirm("<?=gettext('Delete this Suricata interface mapping?')?>")) {
				$('#' + event.target.id.slice(1)).click();
			}
		});
	});
//]]>
</script>

<?php
include("foot.inc");
?>
