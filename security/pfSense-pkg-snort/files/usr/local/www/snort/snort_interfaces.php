<?php
/*
 * snort_interfaces.php
 *
 * Copyright (C) 2008-2009 Robert Zelaya.
 * Copyright (C) 2011-2012 Ermal Luci
 * Copyright (C) 2014 Bill Meeks
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
	if (is_array($_POST['rule'])) {
		conf_mount_rw();
		foreach ($_POST['rule'] as $rulei) {
			$if_real = get_real_interface($a_nat[$rulei]['interface']);
			$snort_uuid = $a_nat[$rulei]['uuid'];
			snort_stop($a_nat[$rulei], $if_real);
			rmdir_recursive("{$snortlogdir}/snort_{$if_real}{$snort_uuid}");
			rmdir_recursive("{$snortdir}/snort_{$snort_uuid}_{$if_real}");

			// Finally delete the interface's config entry entirely
			unset($a_nat[$rulei]);
		}
	  
		/* If all the Snort interfaces are removed, then unset the interfaces config array. */
		if (empty($a_nat))
			unset($a_nat);

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
if ($_POST['bartoggle'] && is_numericint($_POST['id'])) {
	$snortcfg = $config['installedpackages']['snortglobal']['rule'][$_POST['id']];
	$if_real = get_real_interface($snortcfg['interface']);
	$if_friendly = convert_friendly_interface_to_friendly_descr($snortcfg['interface']);

	if (!snort_is_running($snortcfg['uuid'], $if_real, 'barnyard2')) {
		log_error("Toggle (barnyard starting) for {$if_friendly}({$if_real})...");
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();
		snort_barnyard_start($snortcfg, $if_real);
	} else {
		log_error("Toggle (barnyard stopping) for {$if_friendly}({$if_real})...");
		snort_barnyard_stop($snortcfg, $if_real);
	}
	sleep(3); // So the GUI reports correctly
}

/* start/stop snort */
if ($_POST['toggle'] && is_numericint($_POST['id'])) {
	$snortcfg = $config['installedpackages']['snortglobal']['rule'][$_POST['id']];
	$if_real = get_real_interface($snortcfg['interface']);
	$if_friendly = convert_friendly_interface_to_friendly_descr($snortcfg['interface']);

	if (snort_is_running($snortcfg['uuid'], $if_real)) {
		log_error("Toggle (snort stopping) for {$if_friendly}({$if_real})...");
		snort_stop($snortcfg, $if_real);
	} else {
		log_error("Toggle (snort starting) for {$if_friendly}({$if_real})...");

		/* set flag to rebuild interface rules before starting Snort */
		$rebuild_rules = true;
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();
		$rebuild_rules = false;
		snort_start($snortcfg, $if_real);
	}
	sleep(3); // So the GUI reports correctly
}

$pgtitle = "Services: Snort " . SNORT_BIN_VERSION . " pkg v{$config['installedpackages']['package'][get_pkg_id("snort")]['version']}";
include_once("head.inc");

?>
<body link="#000000" vlink="#000000" alink="#000000">

<?php
include_once("fbegin.inc");

	/* Display Alert message */
	if ($input_errors)
		print_input_errors($input_errors);

	if ($savemsg)
		print_info_box($savemsg);
?>

<form action="snort_interfaces.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<input type="hidden" name="id" id="id" value="">

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
	<td>
	<?php
		$tab_array = array();
		$tab_array[0] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
		$tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
		$tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
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
	</td>
</tr>
<tr>
	<td>
	<div id="mainarea">
	<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr id="frheader">
			<td width="3%" class="list">&nbsp;</td>
			<td width="10%" class="listhdrr"><?php echo gettext("Interface"); ?></td>
			<td width="14%" class="listhdrr"><?php echo gettext("Snort"); ?></td>
			<td width="10%" class="listhdrr"><?php echo gettext("Performance"); ?></td>
			<td width="10%" class="listhdrr"><?php echo gettext("Block"); ?></td>
			<td width="12%" class="listhdrr"><?php echo gettext("Barnyard2"); ?></td>
			<td width="32%" class="listhdr"><?php echo gettext("Description"); ?></td>
			<td class="list">
			<table border="0" cellspacing="0" cellpadding="0">
				<tr>
					<td class="list" valign="middle">
						<?php if ($id_gen < count($ifaces)): ?>
							<a href="snort_interfaces_edit.php?id=<?php echo $id_gen;?>">
							<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
							width="17" height="17" border="0" title="<?php echo gettext('Add Snort interface mapping');?>"></a>
						<?php else: ?>
							<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus_d.gif" width="17" height="17" border="0" 
							title="<?php echo gettext('No available interfaces for a new Snort mapping');?>">
						<?php endif; ?>
					</td>
					<td class="list" valign="middle">
						<?php if ($id_gen == 0): ?>
							<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" " border="0">
						<?php else: ?>
							<input name="del" type="image" src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" 
							width="17" height="17" title="<?php echo gettext("Delete selected Snort interface mapping(s)"); ?>"
							onclick="return intf_del()">
						<?php endif; ?>
					</td>
				</tr>
			</table>
			</td>
		</tr>
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
		<tr valign="top" id="fr<?=$nnats;?>">
		<?php

			/* convert fake interfaces to real and check if iface is up */
			$if_real = get_real_interface($natent['interface']);
			$natend_friendly = convert_friendly_interface_to_friendly_descr($natent['interface']);
			$snort_uuid = $natent['uuid'];
			if (!snort_is_running($snort_uuid, $if_real)){
				$iconfn = 'block';
				$iconfn_msg1 = 'Snort is not running on ';
				$iconfn_msg2 = '. Click to start.';
			}
			else{
				$iconfn = 'pass';
				$iconfn_msg1 = 'Snort is running on ';
				$iconfn_msg2 = '. Click to stop.';
			}
			if (!snort_is_running($snort_uuid, $if_real, 'barnyard2')){
				$biconfn = 'block';
				$biconfn_msg1 = 'Barnyard2 is not running on ';
				$biconfn_msg2 = '. Click to start.';
			}
			else{
				$biconfn = 'pass';
				$biconfn_msg1 = 'Barnyard2 is running on ';
				$biconfn_msg2 = '. Click to stop.';
				}

			/* See if interface has any rules defined and set boolean flag */
			$no_rules = true;
			if (isset($natent['customrules']) && !empty($natent['customrules']))
				$no_rules = false;
			elseif (isset($natent['rulesets']) && !empty($natent['rulesets']))
				$no_rules = false;
			elseif (isset($natent['ips_policy']) && !empty($natent['ips_policy']))
				$no_rules = false;
			elseif ($config['installedpackages']['snortglobal']['auto_manage_sids'] == 'on' && !empty($natent['enable_sid_file']))
				$no_rules = false;
			/* Do not display the "no rules" warning if interface disabled */
			if ($natent['enable'] == "off")
				$no_rules = false;
			if ($no_rules)
				$no_rules_footnote = true;
		?>
			<td class="listt">
			<input type="checkbox" id="frc<?=$nnats;?>" name="rule[]" value="<?=$i;?>" onClick="fr_bgcolor('<?=$nnats;?>')" style="margin: 0; padding: 0;">
			</td>
			<td class="listr" 
			id="frd<?=$nnats;?>"
			ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
			<?php
				echo $natend_friendly;
			?>
			</td>
			<td class="listr" 
			id="frd<?=$nnats;?>"
			ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
			<?php
			$check_snort_info = $config['installedpackages']['snortglobal']['rule'][$nnats]['enable'];
			if ($check_snort_info == "on") {
				echo gettext("ENABLED") . "&nbsp;";
				echo "<input type='image' src='../themes/{$g['theme']}/images/icons/icon_{$iconfn}.gif' width='13' height='13' border='0' ";
				echo "onClick='document.getElementById(\"id\").value=\"{$nnats}\";' name=\"toggle[]\" ";
				echo "title='" . gettext($iconfn_msg1.$natend_friendly.$iconfn_msg2) . "'/>";
				echo ($no_rules) ? "&nbsp;<img src=\"../themes/{$g['theme']}/images/icons/icon_frmfld_imp.png\" width=\"15\" height=\"15\" border=\"0\">" : "";
			} else
				echo gettext("DISABLED");
			?>
			</td>
			<td class="listr" 
			id="frd<?=$nnats;?>"
			ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
			<?php
			$check_performance_info = $config['installedpackages']['snortglobal']['rule'][$nnats]['performance'];
			if ($check_performance_info != "") {
				$check_performance = $check_performance_info;
			}else{
				$check_performance = "lowmem";
			}
			?> <?=strtoupper($check_performance);?>
			</td>
			<td class="listr" 
			id="frd<?=$nnats;?>"
			ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
			<?php
			$check_blockoffenders_info = $config['installedpackages']['snortglobal']['rule'][$nnats]['blockoffenders7'];
			if ($check_blockoffenders_info == "on")
			{
				$check_blockoffenders = enabled;
			} else {
				$check_blockoffenders = disabled;
			}
			?> <?=strtoupper($check_blockoffenders);?>
			</td>
			<td class="listr" 
			id="frd<?=$nnats;?>"
			ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
			<?php
			$check_snortbarnyardlog_info = $config['installedpackages']['snortglobal']['rule'][$nnats]['barnyard_enable'];
			if ($check_snortbarnyardlog_info == "on") {
				echo gettext("ENABLED") . "&nbsp;";
				echo "<input type='image' name='bartoggle[]' src='../themes/{$g['theme']}/images/icons/icon_{$biconfn}.gif' width='13' height='13' border='0' ";
				echo "onClick='document.getElementById(\"id\").value=\"{$nnats}\"'; title='" . gettext($biconfn_msg1.$natend_friendly.$biconfn_msg2) . "'/>";
			} else
				echo gettext("DISABLED");
			?>
			</td>
			<td class="listbg" 
			ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
			<font color="#ffffff"> <?=htmlspecialchars($natent['descr']);?>&nbsp;</font>
			</td>
			<td valign="middle" class="list" nowrap>
			<table border="0" cellspacing="0" cellpadding="0">
				<tr>
					<td class="list" valign="middle"><a href="snort_interfaces_edit.php?id=<?=$i;?>"><img
						src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif"
						width="17" height="17" border="0" title="<?php echo gettext('Edit Snort interface mapping'); ?>"></a>
					</td>
					<td class="list" valign="middle">
						<?php if ($id_gen < count($ifaces)): ?>
							<a href="snort_interfaces_edit.php?id=<?=$i;?>&action=dup">
							<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
							width="17" height="17" border="0" title="<?php echo gettext('Add new interface mapping based on this one'); ?>"></a>
						<?php else: ?>
							<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus_d.gif" width="17" height="17" border="0" 
							title="<?php echo gettext('No available interfaces for a new Snort mapping');?>">
						<?php endif; ?>
					</td>
				</tr>
			</table>
			</td>	
		</tr>
		<?php $i++; $nnats++; endforeach; ob_end_flush(); ?>
		<tr>
			<td class="list"></td>
			<td class="list" colspan="6">
				<?php if ($no_rules_footnote): ?><br><img src="../themes/<?= $g['theme']; ?>/images/icons/icon_frmfld_imp.png" width="15" height="15" border="0">
					<span class="red">&nbsp;&nbsp <?php echo gettext("WARNING: Marked interface currently has no rules defined for Snort"); ?></span>
				<?php else: ?>&nbsp;
				<?php endif; ?>					 
			</td>
			<td class="list" valign="middle" nowrap>
				<table border="0" cellspacing="0" cellpadding="0">
					<tr>
						<td class="list">
							<?php if ($id_gen < count($ifaces)): ?>
								<a href="snort_interfaces_edit.php?id=<?php echo $id_gen;?>">
								<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
								width="17" height="17" border="0" title="<?php echo gettext('Add Snort interface mapping');?>"></a>
							<?php else: ?>
								<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus_d.gif" width="17" height="17" border="0" 
								title="<?php echo gettext('No available interfaces for a new Snort mapping');?>">
							<?php endif; ?>
						</td>
						<td class="list">
							<?php if ($id_gen == 0): ?>
								<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" " border="0">
							<?php else: ?>
								<input name="del" type="image" src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" 
								width="17" height="17" title="<?php echo gettext("Delete selected Snort interface mapping(s)"); ?>"
								onclick="return intf_del()">
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
		<td colspan="8">&nbsp;</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td colspan="6">
			<table class="tabcont" width="100%" border="0" cellpadding="1" cellspacing="0">
				<tr>
					<td colspan="3" class="vexpl"><span class="red"><strong><?php echo gettext("Note:"); ?></strong></span> <br>
						<?php echo gettext("This is the ") . "<strong>" . gettext("Snort Menu ") . 
						"</strong>" . gettext("where you can see an overview of all your interface settings.");
						if (empty($a_nat)) {
							echo gettext("Please visit the ") . "<strong>" . gettext("Global Settings") . 
							"</strong>" . gettext(" tab before adding an interface."); 
						}?>
					</td>
				</tr>
				<tr>
					<td colspan="3" class="vexpl">
						<?php echo gettext("New settings will not take effect until interface restart."); ?>
					</td>
				</tr>
				<tr>
					<td colspan="3" class="vexpl"><br>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><strong>Click</strong> on the <img src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
						width="17" height="17" border="0" title="<?php echo gettext("Add Icon"); ?>"> icon to add 
						an interface.
					</td>
					<td width="3%" class="vexpl">&nbsp;
					</td>
					<td class="vexpl"><img src="../themes/<?= $g['theme']; ?>/images/icons/icon_pass.gif"
						width="13" height="13" border="0" title="<?php echo gettext("Running"); ?>">
						<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_block.gif"
						width="13" height="13" border="0" title="<?php echo gettext("Not Running"); ?>">  icons will show current 
						snort and barnyard2 status.
					</td>
				</tr>
				<tr>
					<td class="vexpl"><strong>Click</strong> on the <img src="../themes/<?= $g['theme']; ?>/images/icons/icon_e.gif"
						width="17" height="17" border="0" title="<?php echo gettext("Edit Icon"); ?>"> icon to edit 
						an interface and settings.
					<td width="3%">&nbsp;
					</td>
					<td class="vexpl"><strong>Click</strong> on the status icons to <strong>toggle</strong> snort and barnyard2 status.
					</td>
				</tr>
				<tr>
					<td colspan="3" class="vexpl"><strong> Click</strong> on the <img src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif"
						width="17" height="17" border="0" title="<?php echo gettext("Delete Icon"); ?>"> icon to
						delete an interface and settings.
					</td>
				</tr>
			</table>
			</td>
			<td>&nbsp;</td>
		</tr>
	</table>
	</div>
	</td>
</tr>
</table>
</form>

<script type="text/javascript">

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

</script>

<?php
include("fend.inc");
?>
</body>
</html>
