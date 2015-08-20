<?php
/*
 * snort_passlist.php
 *
 * Copyright (C) 2004 Scott Ullrich
 * Copyright (C) 2011-2012 Ermal Luci
 * Copyright (C) 2014 Bill Meeks
 * All rights reserved.
 *
 * originially part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * modified for the pfsense snort package
 * Copyright (C) 2009-2010 Robert Zelaya.
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

if (!is_array($config['installedpackages']['snortglobal']['whitelist']))
	$config['installedpackages']['snortglobal']['whitelist'] = array();
if (!is_array($config['installedpackages']['snortglobal']['whitelist']['item']))
	$config['installedpackages']['snortglobal']['whitelist']['item'] = array();
$a_passlist = &$config['installedpackages']['snortglobal']['whitelist']['item'];

// Calculate the next Pass List index ID
if (isset($config['installedpackages']['snortglobal']['whitelist']['item']))
	$id_gen = count($config['installedpackages']['snortglobal']['whitelist']['item']);
else
	$id_gen = '0';

function snort_is_passlist_used($list) {

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

	if (!is_array($config['installedpackages']['snortglobal']['rule']))
		return FALSE;

	foreach($config['installedpackages']['snortglobal']['rule'] as $v) {
		if (isset($v['whitelistname']) && $v['whitelistname'] == $list)
			return TRUE;
	}
	return FALSE;
}

if ($_POST['del'] && is_numericint($_POST['list_id'])) {
	if ($a_passlist[$_POST['list_id']]) {
		/* make sure list is not being referenced by any interface */
		if (snort_is_passlist_used($a_passlist[$_POST['list_id']]['name'])) {
			$input_errors[] = gettext("This Pass List is currently assigned to a Snort interface and cannot be deleted.  Unassign it from all Snort interfaces first.");
		}
		if (!$input_errors) {
			unset($a_passlist[$_POST['list_id']]);
			write_config("Snort pkg: deleted PASS LIST.");
			conf_mount_rw();
			sync_snort_package_config();
			conf_mount_ro();
			header("Location: /snort/snort_passlist.php");
			exit;
		}
	}
}

$pgtitle = gettext("Snort: Pass Lists");
include_once("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php
include_once("fbegin.inc");

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}
?>

<form action="/snort/snort_passlist.php" method="post">
<input type="hidden" name="list_id" id="list_id" value=""/>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
        $tab_array = array();
        $tab_array[0] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
        $tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
        $tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
        $tab_array[3] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
        $tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
        $tab_array[5] = array(gettext("Pass Lists"), true, "/snort/snort_passlist.php");
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
	<td><div id="mainarea">
	<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td width="25%" class="listhdrr">List Name</td>
			<td width="30%" class="listhdrr">Assigned Alias</td>
			<td class="listhdr">Description</td>
			<td width="40px" class="list"></td>
		</tr>
		<?php foreach ($a_passlist as $i => $list): ?>
		<tr>
			<td class="listlr"
				ondblclick="document.location='snort_passlist_edit.php?id=<?=$i;?>';">
				<?=htmlspecialchars($list['name']);?></td>
			<td class="listr"
				ondblclick="document.location='snort_passlist_edit.php?id=<?=$i;?>';" 
				title="<?=filter_expand_alias($list['address']);?>">
				<?php echo gettext($list['address']);?></td>
			<td class="listbg"
				ondblclick="document.location='snort_passlist_edit.php?id=<?=$i;?>';">
			<font color="#FFFFFF"> <?=htmlspecialchars($list['descr']);?>&nbsp;
			</td>
			<td valign="middle" nowrap class="list">
			<table border="0" cellspacing="0" cellpadding="1">
				<tr>
					<td valign="middle"><a href="snort_passlist_edit.php?id=<?=$i;?>">
					<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?php echo gettext("Edit pass list"); ?>"></a>
					</td>
					<td><input type="image" name="del[]" onclick="document.getElementById('list_id').value='<?=$i;?>';return confirm('<?=gettext("Do you really want to delete this pass list?  Click OK to continue or CANCEL to quit.)!");?>');" 
					src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?php echo gettext("Delete pass list"); ?>"/>
					</td>
				</tr>
			</table>
			</td>
		</tr>
		<?php endforeach; ?>
		<tr>
			<td class="list" colspan="3"></td>
			<td class="list">
			<table border="0" cellspacing="0" cellpadding="1">
				<tr>
					<td valign="middle" width="17">&nbsp;</td>
					<td valign="middle"><a href="snort_passlist_edit.php?id=<?php echo $id_gen;?> ">
					<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
					width="17" height="17" border="0" title="<?php echo gettext("add a new pass list"); ?>"/></a>
					</td>
				</tr>
			</table>
				</td>
			</tr>
		</table>
		</div>
		</td>
	</tr>
</table>
<br>
<table width="100%" border="0" cellpadding="1"
	cellspacing="1">
	<tr>
	<td width="100%"><span class="vexpl"><span class="red"><strong><?php echo gettext("Notes:"); ?></strong></span>
	<p><?php echo gettext("1. Here you can create Pass List files for your Snort package rules.  Hosts on a Pass List are never blocked by Snort."); ?><br/>
	<?php echo gettext("2. Add all the IP addresses or networks (in CIDR notation) you want to protect against Snort block decisions."); ?><br/>
	<?php echo gettext("3. The default Pass List includes the WAN IP and gateway, defined DNS servers, VPNs and locally-attached networks."); ?><br/>
	<?php echo gettext("4. Be careful, it is very easy to get locked out of your system by altering the default settings."); ?></p></span></td>
	</tr>
	<tr>
	<td width="100%"><span class="vexpl"><?php echo gettext("Remember you must restart Snort on the interface for changes to take effect!"); ?></span></td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
