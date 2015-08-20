<?php
/*
 * suricata_interfaces.php
 *
 * Significant portions of this code are based on original work done
 * for the Snort package for pfSense from the following contributors:
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

global $g, $rebuild_rules;

$suricatadir = SURICATADIR;
$suricatalogdir = SURICATALOGDIR;
$rcdir = RCFILEPREFIX;

if ($_POST['id'])
	$id = $_POST['id'];
else
	$id = 0;

if (!is_array($config['installedpackages']['suricata']['rule']))
	$config['installedpackages']['suricata']['rule'] = array();
$a_nat = &$config['installedpackages']['suricata']['rule'];
$id_gen = count($config['installedpackages']['suricata']['rule']);

// Get list of configured firewall interfaces
$ifaces = get_configured_interface_list();

if ($_POST['del_x']) {
	/* delete selected interfaces */
	if (is_array($_POST['rule'])) {
		conf_mount_rw();
		foreach ($_POST['rule'] as $rulei) {
			$if_real = get_real_interface($a_nat[$rulei]['interface']);
			$suricata_uuid = $a_nat[$rulei]['uuid'];
			suricata_stop($a_nat[$rulei], $if_real);
			rmdir_recursive("{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}");
			rmdir_recursive("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}");
			unset($a_nat[$rulei]);
		}
		conf_mount_ro();
	  
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

	if (suricata_is_running($suricatacfg['uuid'], $if_real)) {
		log_error("Toggle (suricata stopping) for {$if_friendly}({$suricatacfg['descr']})...");
		suricata_stop($suricatacfg, $if_real);
	} else {
		log_error("Toggle (suricata starting) for {$if_friendly}({$suricatacfg['descr']})...");
		// set flag to rebuild interface rules before starting Snort
		$rebuild_rules = true;
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();
		$rebuild_rules = false;
		suricata_start($suricatacfg, $if_real);
	}
	sleep(3); // So the GUI reports correctly
	header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
	header( 'Cache-Control: no-store, no-cache, must-revalidate' );
	header( 'Cache-Control: post-check=0, pre-check=0', false );
	header( 'Pragma: no-cache' );
	header("Location: /suricata/suricata_interfaces.php");
	exit;
}
$suri_bin_ver = SURICATA_BIN_VERSION;
$suri_pkg_ver = SURICATA_PKG_VER;
$pgtitle = "Services: Suricata {$suri_bin_ver} pkg v{$suri_pkg_ver} - Intrusion Detection System";
include_once("head.inc");

?>
<body link="#000000" vlink="#000000" alink="#000000">

<?php include_once("fbegin.inc"); ?>

<form action="suricata_interfaces.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<input type="hidden" name="id" id="id" value="">
<?php
	/* Display Alert message */
	if ($input_errors)
		print_input_errors($input_errors);

	if ($savemsg)
		print_info_box($savemsg);
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tbody>
<tr>
	<td>
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
	</td>
</tr>
<tr>
	<td>
	<div id="mainarea">
	<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
		<colgroup>
			<col width="3%" align="center">
			<col width="12%">
			<col width="14%">
			<col width="120" align="center">
			<col width="65" align="center">
			<col width="14%">
			<col>
			<col width="20" align="center">
		</colgroup>
		<thead>
		<tr id="frheader">
			<th class="list">&nbsp;</th>
			<th class="listhdrr"><?php echo gettext("Interface"); ?></th>
			<th class="listhdrr"><?php echo gettext("Suricata"); ?></th>
			<th class="listhdrr"><?php echo gettext("Pattern Match"); ?></th>
			<th class="listhdrr"><?php echo gettext("Block"); ?></th>
			<th class="listhdrr"><?php echo gettext("Barnyard2"); ?></th>
			<th class="listhdr"><?php echo gettext("Description"); ?></th>
			<th class="list">
				<?php if ($id_gen < count($ifaces)): ?>
					<a href="suricata_interfaces_edit.php?id=<?php echo $id_gen;?>">
					<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
					width="17" height="17" border="0" title="<?php echo gettext('Add Suricata interface mapping');?>"></a>
				<?php else: ?>
					<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus_d.gif" width="17" height="17" border="0" 
					title="<?php echo gettext('No available interfaces for a new Suricata mapping');?>">
				<?php endif; ?>
				<?php if ($id_gen == 0): ?>
					<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" " border="0">
				<?php else: ?>
					<input name="del" type="image" src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" 
					width="17" height="17" title="<?php echo gettext("Delete selected Suricata interface mapping(s)"); ?>"
					onclick="return intf_del()">
				<?php endif; ?>
			</th>
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
		<tr valign="top" id="fr<?=$nnats;?>">
		<?php

			/* convert fake interfaces to real and check if iface is up */
			/* There has to be a smarter way to do this */
			$if_real = get_real_interface($natent['interface']);
			$natend_friendly= convert_friendly_interface_to_friendly_descr($natent['interface']);
			$suricata_uuid = $natent['uuid'];
			if (!suricata_is_running($suricata_uuid, $if_real)){
				$iconfn = 'block';
				$iconfn_msg1 = 'Suricata is not running on ';
				$iconfn_msg2 = '. Click to start.';
			}
			else{
				$iconfn = 'pass';
				$iconfn_msg1 = 'Suricata is running on ';
				$iconfn_msg2 = '. Click to stop.';
			}
			if (!suricata_is_running($suricata_uuid, $if_real, 'barnyard2')){
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
			<td class="listt">
			<input type="checkbox" id="frc<?=$nnats;?>" name="rule[]" value="<?=$i;?>" onClick="fr_bgcolor('<?=$nnats;?>')" style="margin: 0; padding: 0;">
			</td>
			<td class="listr" valign="middle" 
			id="frd<?=$nnats;?>" 
			ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats;?>';">
			<?php
				echo $natend_friendly;
			?>
			</td>
			<td class="listr" valign="middle"   
			id="frd<?=$nnats;?>"  
			ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats;?>';">
			<?php
			$check_suricata_info = $config['installedpackages']['suricata']['rule'][$nnats]['enable'];
			if ($check_suricata_info == "on") {
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
			id="frd<?=$nnats;?>" valign="middle" align="center" 
			ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats;?>';">
			<?php
			$check_performance_info = $config['installedpackages']['suricata']['rule'][$nnats]['mpm_algo'];
			if ($check_performance_info != "") {
				$check_performance = $check_performance_info;
			}else{
				$check_performance = "unknown";
			}
			?> <?=strtoupper($check_performance);?>
			</td>
			<td class="listr" 
			id="frd<?=$nnats;?>" valign="middle" align="center" 
			ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats;?>';">
			<?php
			$check_blockoffenders_info = $config['installedpackages']['suricata']['rule'][$nnats]['blockoffenders'];
			if ($check_blockoffenders_info == "on")
			{
				$check_blockoffenders = enabled;
			} else {
				$check_blockoffenders = disabled;
			}
			?> <?=strtoupper($check_blockoffenders);?>
			</td>
			<td class="listr" 
			id="frd<?=$nnats;?>" valign="middle" 
			ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats;?>';">
			<?php
			$check_suricatabarnyardlog_info = $config['installedpackages']['suricata']['rule'][$nnats]['barnyard_enable'];
			if ($check_suricatabarnyardlog_info == "on") {
				echo gettext("ENABLED") . "&nbsp;";
				echo "<input type='image' name='bartoggle[]' src='../themes/{$g['theme']}/images/icons/icon_{$biconfn}.gif' width='13' height='13' border='0' ";
				echo "onClick='document.getElementById(\"id\").value=\"{$nnats}\"'; title='" . gettext($biconfn_msg1.$natend_friendly.$biconfn_msg2) . "'/>";
			} else
				echo gettext("DISABLED");
			?>
			</td>
			<td class="listbg" valign="middle" 
			ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats;?>';">
			<font color="#ffffff"> <?=htmlspecialchars($natent['descr']);?>&nbsp;</font>
			</td>
			<td valign="middle" class="list" nowrap>
				<a href="suricata_interfaces_edit.php?id=<?=$i;?>">
				<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif"
				width="17" height="17" border="0" title="<?php echo gettext('Edit this Suricata interface mapping'); ?>"></a>
				<?php if ($id_gen < count($ifaces)): ?>
					<a href="suricata_interfaces_edit.php?id=<?=$i;?>&action=dup">
					<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
					width="17" height="17" border="0" title="<?php echo gettext('Add new interface mapping based on this one'); ?>"></a>
				<?php else: ?>
					<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus_d.gif" width="17" height="17" border="0" 
					title="<?php echo gettext('No available interfaces for a new Suricata mapping');?>">
				<?php endif; ?>
			</td>	
		</tr>
		<?php $i++; $nnats++; endforeach; ob_end_flush(); ?>
		<tr>
			<td class="list"></td>
			<td class="list" colspan="6">
				<?php if ($no_rules_footnote): ?><br><img src="../themes/<?= $g['theme']; ?>/images/icons/icon_frmfld_imp.png" width="15" height="15" border="0">
					<span class="red">&nbsp;&nbsp <?php echo gettext("WARNING: Marked interface currently has no rules defined for Suricata"); ?></span>
				<?php else: ?>&nbsp;
				<?php endif; ?>					 
			</td>
			<td class="list">
				<?php if ($id_gen < count($ifaces)): ?>
					<a href="suricata_interfaces_edit.php?id=<?php echo $id_gen;?>">
					<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
					width="17" height="17" border="0" title="<?php echo gettext('Add Suricata interface mapping');?>"></a>
				<?php else: ?>
					<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus_d.gif" width="17" height="17" border="0" 
					title="<?php echo gettext('No available interfaces for a new Suricata mapping');?>">
				<?php endif; ?>
				<?php if ($id_gen == 0): ?>
					<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" " border="0">
				<?php else: ?>
					<input name="del" type="image" src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" 
					width="17" height="17" title="<?php echo gettext("Delete selected Suricata interface mapping(s)"); ?>"
					onclick="return intf_del()">
				<?php endif; ?>
			</td>
		</tr>
		<tr>
		<td colspan="8">&nbsp;</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td colspan="6">
			<table class="tabcont" width="100%" border="0" cellpadding="1" cellspacing="0">
				<tbody>
				<tr>
					<td colspan="3" class="vexpl"><span class="red"><strong><?php echo gettext("Note:"); ?></strong></span> <br>
						<?php echo gettext("This is the ") . "<strong>" . gettext("Suricata Menu ") . 
						"</strong>" . gettext("where you can see an overview of all your interface settings.  ");
						if (empty($a_nat)) {
							echo gettext("Please configure the parameters on the ") . "<strong>" . gettext("Global Settings") . 
							"</strong>" . gettext(" tab before adding an interface."); 
						}?>
					</td>
				</tr>
				<tr>
					<td colspan="3" class="vexpl"><br>
					</td>
				</tr>
				<tr>
					<td colspan="3" class="vexpl"><span class="red"><strong><?php echo gettext("Warning:"); ?></strong></span><br>
						<strong><?php echo gettext("New settings will not take effect until interface restart."); ?></strong>
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
						suricata and barnyard2 status.
					</td>
				</tr>
				<tr>
					<td class="vexpl"><strong>Click</strong> on the <img src="../themes/<?= $g['theme']; ?>/images/icons/icon_e.gif"
						width="17" height="17" border="0" title="<?php echo gettext("Edit Icon"); ?>"> icon to edit 
						an interface and settings.
					<td width="3%">&nbsp;
					</td>
					<td class="vexpl"><strong>Click</strong> on the status icons to <strong>toggle</strong> suricata and barnyard2 status.
					</td>
				</tr>
				<tr>
					<td colspan="3" class="vexpl"><strong> Click</strong> on the <img src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif"
						width="17" height="17" border="0" title="<?php echo gettext("Delete Icon"); ?>"> icon to
						delete an interface and settings.
					</td>
				</tr>
				</tbody>
			</table>
			</td>
			<td>&nbsp;</td>
		</tr>
		</tbody>
	</table>
	</div>
	</td>
</tr>
</tbody>
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
		return confirm('Do you really want to delete the selected Suricata mapping?');
	else
		alert("There is no Suricata mapping selected for deletion.  Click the checkbox beside the Suricata mapping(s) you wish to delete.");
}

</script>

<?php
include("fend.inc");
?>
</body>
</html>
