<?php
/*
 * suricata_passlist.php
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

if (!is_array($config['installedpackages']['suricata']['passlist']))
	$config['installedpackages']['suricata']['passlist'] = array();
if (!is_array($config['installedpackages']['suricata']['passlist']['item']))
	$config['installedpackages']['suricata']['passlist']['item'] = array();
$a_passlist = &$config['installedpackages']['suricata']['passlist']['item'];

// Calculate the next Pass List index ID
if (isset($config['installedpackages']['suricata']['passlist']['item']))
	$id_gen = count($config['installedpackages']['suricata']['passlist']['item']);
else
	$id_gen = '0';

function suricata_is_passlist_used($list) {

	/**********************************************
	 * This function tests the provided Pass List *
	 * to determine if it is assigned to an       *
	 * interface.                                 *
	 *                                            *
	 * On Entry: $list -> Pass List name to test  *
	 *                                            *
	 * Returns: TRUE if Pass List is in use or    *
	 *          FALSE if not in use               *
	 **********************************************/

	global $config;

	if (!is_array($config['installedpackages']['suricata']['rule']))
		return FALSE;

	foreach($config['installedpackages']['suricata']['rule'] as $v) {
		if (isset($v['passlistname']) && $v['passlistname'] == $list)
			return TRUE;
	}
	return FALSE;
}

if ($_POST['del'] && is_numericint($_POST['list_id'])) {
	if ($a_passlist[$_POST['list_id']]) {
		/* make sure list is not being referenced by any interface */
		if (suricata_is_passlist_used($a_passlist[$_POST['list_id']]['name'])) {
			$input_errors[] = gettext("This Pass List is currently assigned to a Suricata interface and cannot be deleted.  Unassign it from all Suricata interfaces first.");
		}
		if (!$input_errors) {
			unset($a_passlist[$_POST['list_id']]);
			write_config("Suricata pkg: deleted PASS LIST.");
			conf_mount_rw();
			sync_suricata_package_config();
			conf_mount_ro();
			header("Location: /suricata/suricata_passlist.php");
			exit;
		}
	}
}

$pgtitle = gettext("Suricata: Pass Lists");
include_once("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php
include_once("fbegin.inc");
?>

<form action="/suricata/suricata_passlist.php" method="post">
<input type="hidden" name="list_id" id="list_id" value=""/>
<?php
/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tbody>
<tr><td>
	<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
	$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
	$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
	$tab_array[] = array(gettext("Pass Lists"), true, "/suricata/suricata_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
	$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$instanceid}");
	$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
	$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
	display_top_tabs($tab_array, true);
	?>
	</td>
</tr>
<tr>
	<td><div id="mainarea">
	<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
		<tbody>
		<tr>
			<td width="25%" class="listhdrr">List Name</td>
			<td width="30%" class="listhdrr">Assigned Alias</td>
			<td class="listhdr">Description</td>
			<td width="40px" class="list"></td>
		</tr>
		<?php foreach ($a_passlist as $i => $list): ?>
		<tr>
			<td class="listlr" 
				ondblclick="document.location='suricata_passlist_edit.php?id=<?=$i;?>';">
				<?=htmlspecialchars($list['name']);?></td>
			<td class="listr" 
				ondblclick="document.location='suricata_passlist_edit.php?id=<?=$i;?>';" 
				title="<?=filter_expand_alias($list['address']);?>">
				<?php echo gettext($list['address']);?></td>
			<td class="listbg" 
				ondblclick="document.location='suricata_passlist_edit.php?id=<?=$i;?>';">
			<font color="#FFFFFF"><?=htmlspecialchars($list['descr']);?></font></td>
			<td valign="middle" nowrap class="list">
			<table border="0" cellspacing="0" cellpadding="1">
				<tbody>
				<tr>
					<td valign="middle"><a href="suricata_passlist_edit.php?id=<?=$i;?>">
					<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?php echo gettext("Edit pass list"); ?>"></a>
					</td>
					<td><input type="image" name="del[]" onclick="document.getElementById('list_id').value='<?=$i;?>';return confirm('<?=gettext("Do you really want to delete this pass list?  Click OK to continue or CANCEL to quit.)!");?>');" 
					src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?php echo gettext("Delete pass list"); ?>"/>
					</td>
				</tr>
				</tbody>
			</table>
			</td>
		</tr>
		<?php endforeach; ?>
		<tr>
			<td class="list" colspan="3"></td>
			<td class="list">
			<table border="0" cellspacing="0" cellpadding="1">
				<tbody>
				<tr>
					<td valign="middle" width="17">&nbsp;</td>
					<td valign="middle"><a href="suricata_passlist_edit.php?id=<?php echo $id_gen;?> ">
					<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
					width="17" height="17" border="0" title="<?php echo gettext("add a new pass list"); ?>"/></a>
					</td>
				</tr>
				</tbody>
			</table>
				</td>
			</tr>
			</tbody>
		</table>
		</div>
		</td>
	</tr>
	</tbody>
</table>
<br>
<table width="100%" border="0" cellpadding="1" cellspacing="1">
	<tbody>
	<tr>
		<td width="100%"><span class="vexpl"><span class="red"><strong><?php echo gettext("Notes:"); ?></strong></span>
		<p><?php echo gettext("1. Here you can create Pass List files for your Suricata package rules.  Hosts on a Pass List are never blocked by Suricata."); ?><br/>
		<?php echo gettext("2. Add all the IP addresses or networks (in CIDR notation) you want to protect against Suricata block decisions."); ?><br/>
		<?php echo gettext("3. The default Pass List includes the WAN IP and gateway, defined DNS servers, VPNs and locally-attached networks."); ?><br/>
		<?php echo gettext("4. Be careful, it is very easy to get locked out of your system by altering the default settings."); ?><br/>
		<?php echo gettext("5. To use a custom Pass List on an interface, you must manually assign the list using the drop-down control on the Interface Settings tab."); ?></p></span></td>
	</tr>
	<tr>
		<td width="100%"><span class="vexpl"><?php echo gettext("Remember you must restart Suricata on the interface for changes to take effect!"); ?></span></td>
	</tr>
	</tbody>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
