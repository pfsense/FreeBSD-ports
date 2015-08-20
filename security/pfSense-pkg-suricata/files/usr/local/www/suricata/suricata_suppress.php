<?php
/*
 * suricata_suppress.php
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

if (!is_array($config['installedpackages']['suricata']['rule']))
	$config['installedpackages']['suricata']['rule'] = array();
if (!is_array($config['installedpackages']['suricata']['suppress']))
	$config['installedpackages']['suricata']['suppress'] = array();
if (!is_array($config['installedpackages']['suricata']['suppress']['item']))
	$config['installedpackages']['suricata']['suppress']['item'] = array();
$a_suppress = &$config['installedpackages']['suricata']['suppress']['item'];
$id_gen = count($config['installedpackages']['suricata']['suppress']['item']);

function suricata_suppresslist_used($supplist) {

	/****************************************************************/
	/* This function tests if the passed Suppress List is currently */
	/* assigned to an interface.  It returns TRUE if the list is    */
	/* in use.                                                      */
	/*                                                              */
	/* Returns:  TRUE if list is in use, else FALSE                 */
	/****************************************************************/

	global $config;

	$suricataconf = $config['installedpackages']['suricata']['rule'];
	if (empty($suricataconf))
		return false;
	foreach ($suricataconf as $value) {
		if ($value['suppresslistname'] == $supplist)
			return true;
	}
	return false;
}

function suricata_find_suppresslist_interface($supplist) {

	/****************************************************************/
	/* This function finds the first (if more than one) interface   */
	/* configured to use the passed Suppress List and returns the   */
	/* index of the interface in the ['rule'] config array.         */
	/*                                                              */
	/* Returns: index of interface in ['rule'] config array or      */
	/*          FALSE if no interface found.                        */
	/****************************************************************/

	global $config;
	$suricataconf = $config['installedpackages']['suricata']['rule'];
	if (empty($suricataconf))
		return false;
	foreach ($suricataconf as $rule => $value) {
		if ($value['suppresslistname'] == $supplist)
			return $rule;
	}
	return false;
}

if ($_POST['del'] && is_numericint($_POST['list_id'])) {
	if ($a_suppress[$_POST['list_id']]) {
		// make sure list is not being referenced by any Suricata-configured interface
		if (suricata_suppresslist_used($a_suppress[$_POST['list_id']]['name'])) {
			$input_errors[] = gettext("ERROR -- Suppress List is currently assigned to an interface and cannot be removed!");
		}
		else {
			unset($a_suppress[$_POST['list_id']]);
			write_config("Suricata pkg: deleted SUPPRESS LIST.");
			conf_mount_rw();
			sync_suricata_package_config();
			conf_mount_ro();
			header("Location: /suricata/suricata_suppress.php");
			exit;
		}
	}
}

$pgtitle = gettext("Suricata: Suppression Lists");
include_once("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000">

<?php
include_once("fbegin.inc");
if($pfsense_stable == 'yes'){echo '<p class="pgtitle">' . $pgtitle . '</p>';}
if ($input_errors) {
	print_input_errors($input_errors);
}

?>

<form action="/suricata/suricata_suppress.php" method="post"><?php if ($savemsg) print_info_box($savemsg); ?>
<input type="hidden" name="list_id" id="list_id" value=""/>
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
	$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
	$tab_array[] = array(gettext("Suppress"), true, "/suricata/suricata_suppress.php");
	$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php");
	$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
	$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
	display_top_tabs($tab_array, true);
?>
</td>
</tr>
<tr><td><div id="mainarea">
	<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
		<thead>
			<tr>
				<th width="30%" class="listhdrr"><?php echo gettext("Suppress List Name"); ?></th>
				<th width="60%" class="listhdr"><?php echo gettext("Description"); ?></th>
				<th width="10%" class="list"></th>
			</tr>
		</thead>
		<tbody>
		<?php $i = 0; foreach ($a_suppress as $list): ?>
			<?php
				if (suricata_suppresslist_used($list['name'])) {
					$icon = "<img src=\"/themes/{$g['theme']}/images/icons/icon_frmfld_pwd.png\" " . 
						"width=\"16\" height=\"16\" border=\"0\" title=\"" . gettext("List is in use by an instance") . "\"/>";
				}
				else
					$icon = "";
			 ?>
			<tr>
				<td height="20px" class="listlr"
					ondblclick="document.location='suricata_suppress_edit.php?id=<?=$i;?>';">
					<?=htmlspecialchars($list['name']);?>&nbsp;<?=$icon;?></td>
				<td height="20px" class="listbg"
					ondblclick="document.location='suricata_suppress_edit.php?id=<?=$i;?>';">
				<font color="#FFFFFF"> <?=htmlspecialchars($list['descr']);?>&nbsp;</font>
				</td>
				<td height="20px" valign="middle" nowrap class="list">
					<table border="0" cellspacing="0" cellpadding="1">
						<tbody>
						<tr>
							<td valign="middle"><a
							href="suricata_suppress_edit.php?id=<?=$i;?>"><img
							src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" 
							width="17" height="17" border="0" title="<?php echo gettext("edit Suppress List"); ?>"></a></td>
						<?php if (suricata_suppresslist_used($list['name'])) : ?>
							<td><img src="/themes/<?=$g['theme'];?>/images/icons/icon_x_d.gif" 
							width="17" height="17" border="0" title="<?php echo gettext("Assigned Suppress Lists cannot be deleted");?>"/></td>
							<td><a href="/suricata/suricata_interfaces_edit.php?id=<?=suricata_find_suppresslist_interface($list['name']);?>">
							<img src="/themes/<?=$g['theme'];?>/images/icons/icon_right.gif" 
							width="17" height="17" border="0" title="<?php echo gettext("Goto first instance associated with this Suppress List");?>"/></a>
							</td>
						<?php else : ?>
							<td><input type="image" name="del[]" onclick="document.getElementById('list_id').value='<?=$i;?>';return confirm('<?=gettext("Do you really want to delete this Suppress List?");?>');" 
							src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete Suppress List");?>"/></td>
							<td>&nbsp;</td>
						<?php endif; ?>
						</tr>
						</tbody>
					</table>
				</td>
			</tr>
		<?php $i++; endforeach; ?>
			<tr>
				<td class="list" colspan="2"></td>
				<td  class="list">
					<table border="0" cellspacing="0" cellpadding="1">
						<tbody>
						<tr>
							<td valign="middle" width="17">&nbsp;</td>
							<td valign="middle"><a
							href="suricata_suppress_edit.php?id=<?php echo $id_gen;?> "><img
							src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
							width="17" height="17" border="0" title="<?php echo gettext("add a new list"); ?>"></a></td>
						</tr>
						</tbody>
					</table>
				</td>
			</tr>
		</tbody>
	</table>
</div>
</td></tr>
<tr>
	<td colspan="3" width="100%"><br/><span class="vexpl"><span class="red"><strong><?php echo gettext("Note:"); ?></strong></span>
	<p><?php echo gettext("Here you can create event filtering and " .
	"suppression for your Suricata package rules."); ?><br/><br/>
	<?php echo gettext("Please note that you must restart a running Interface so that changes can " .
	"take effect."); ?><br/><br/>
	<?php echo gettext("You cannot delete a Suppress List that is currently assigned to a Suricata interface (instance).") . "<br/>" . 
	gettext("You must first unassign the Suppress List on the Interface Edit tab."); ?>
	</p></span></td>
</tr>
</tbody>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
