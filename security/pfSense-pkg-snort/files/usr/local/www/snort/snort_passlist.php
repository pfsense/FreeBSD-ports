<?php
/*
 * snort_passlist.php
 *
 * Copyright (C) 2004 Scott Ullrich
 * Copyright (C) 2011-2012 Ermal Luci
 * Copyright (C) 2015 Bill Meeks
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

if (isset($_POST['del_btn'])) {
	$need_save = false;
	if (is_array($_POST['del']) && count($_POST['del'])) {
		foreach ($_POST['del'] as $itemi) {
			/* make sure list is not being referenced by any interface */
			if (snort_is_passlist_used($a_passlist[$_POST['list_id']]['name'])) {
				$input_errors[] = gettext("Pass List '{$a_passlist[$itemi]['name']}' is currently assigned to a Snort interface and cannot be deleted.  Unassign it from all Snort interfaces first.");
			} else {
				unset($a_passlist[$itemi]);
				$need_save = true;
			}
		}
		if ($need_save) {
			write_config("Snort pkg: deleted PASS LIST.");
			conf_mount_rw();
			sync_snort_package_config();
			conf_mount_ro();
			header("Location: /snort/snort_passlist.php");
			return;
		}
	}
}
else {
	unset($delbtn_list);
	$need_save = false;

	foreach ($_POST as $pn => $pd) {
		if (preg_match("/cdel_(\d+)/", $pn, $matches)) {
			$delbtn_list = $matches[1];
		}
	}
	if (is_numeric($delbtn_list) && $a_passlist[$delbtn_list]) {
		if (snort_is_passlist_used($a_passlist[$_POST['list_id']]['name'])) {
			$input_errors[] = gettext("This Pass List '{$a_passlist[$delbtn_list]['name']}' is currently assigned to a Snort interface and cannot be deleted.  Unassign it from all Snort interfaces first.");
		}
		else {
			unset($a_passlist[$delbtn_list]);
			write_config("Snort pkg: deleted PASS LIST.");
			conf_mount_rw();
			sync_snort_package_config();
			conf_mount_ro();
			header("Location: /snort/snort_passlist.php");
			return;
		}
	}
}

$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Pass Lists"));
include_once("head.inc");

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), true, "/snort/snort_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);
?>

<form action="/snort/snort_passlist.php" method="post">
<input type="hidden" name="list_id" id="list_id" value=""/>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Configured Pass Lists');?></h2></div>

	<div id="mainarea" class="table-responsive panel-body">
		<table id="maintable" class="table table-striped table-hover table-condensed">
			<thead>
			<tr>
				<th>&nbsp;</th>
				<th>List Name</th>
				<th>Assigned Alias</th>
				<th>Description</th>
				<th>Actions</th>
			</tr>
			</thead>
			<tbody>
		<?php foreach ($a_passlist as $i => $list): ?>
			<tr>
				<td><input type="checkbox" id="frc<?=$i?>" name="del[]" value="<?=$i?>" onclick="fr_bgcolor('<?=$i?>')" /></td>
				<td ondblclick="document.location='snort_passlist_edit.php?id=<?=$i;?>';"><?=htmlspecialchars($list['name']);?></td>
				<td ondblclick="document.location='snort_passlist_edit.php?id=<?=$i;?>';" title="<?=filter_expand_alias($list['address']);?>">
					<?=gettext($list['address']);?></td>
				<td ondblclick="document.location='snort_passlist_edit.php?id=<?=$i;?>';"><?=htmlspecialchars($list['descr']);?>&nbsp;</td>
				<td style="cursor: pointer;"><a href="snort_passlist_edit.php?id=<?=$i;?>" class="fa fa-pencil" title="<?=gettext('Edit Pass List');?>"></a>
				<a class="fa fa-trash no-confirm" id="Xcdel_<?=$i?>" title="<?=gettext('Delete Pass List'); ?>"></a>
				<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="cdel_<?=$i?>" name="cdel_<?=$i?>" value="cdel_<?=$i?>" title="<?=gettext('Delete Pass List'); ?>">Delete Pass List</button></td>
			</tr>
		<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<nav class="action-buttons">
		<a href="snort_passlist_edit.php?id=<?php echo $id_gen;?>" role="button" class="btn btn-sm btn-success" title="<?=gettext('add a new pass list');?>">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
		<?php if (count($a_passlist) > 0): ?>
			<button type="submit" name="del_btn" id="del_btn" class="btn btn-danger btn-sm" title="<?=gettext('Delete Selected Items');?>">
				<i class="fa fa-trash icon-embed-btn"></i>
				<?=gettext('Delete');?>
			</button>
		<?php endif; ?>
	</nav>
</div>
</form>

<div class="infoblock">
	<div class="alert alert-info clearfix" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<div class="pull-left">
			<dl class="dl-horizontal responsive">
				<dt><?=gettext('Notes:');?></dt><dd></dd>
				<dt><?=gettext('1.');?></dt><dd><?=gettext('Here you can create Pass List files for your Snort package rules.  Hosts on a Pass List are never blocked by Snort.');?></dd>
				<dt><?=gettext('2.');?></dt><dd><?=gettext('Add all the IP addresses or networks (in CIDR notation) you want to protect against Snort block decisions.');?></dd>
				<dt><?=gettext('3.');?></dt><dd><?=gettext('The default Pass List includes the WAN IP and gateway, defined DNS servers, VPNs and locally-attached networks.');?></dd>
				<dt><?=gettext('4.');?></dt><dd><?=gettext('Be careful, it is very easy to get locked out of your system by altering the default settings.');?></dd>
				<dt><?=gettext('5.');?></dt><dd><?=gettext('To use a custom Pass List on an interface, you must manually assign the list using the drop-down control on the Interface Settings tab.');?></dd>
			</dl>
		</div>
	</div>
</div>
<script type="text/javascript">
//<![CDATA[

events.push(function() {
	$('[id^=Xcdel_]').click(function (event) {
		if(confirm("<?=gettext('Delete this Pass List entry?')?>")) {
			$('#' + event.target.id.slice(1)).click();
		}
	});
});

//]]>
</script>
<?php include("foot.inc"); ?>

