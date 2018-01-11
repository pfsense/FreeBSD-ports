<?php
/*
 * suricata_passlist.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2014 Bill Meeks
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
	 * to determine if it is assigned to an	      *
	 * interface.								  *
	 *											  *
	 * On Entry: $list -> Pass List name to test  *
	 *											  *
	 * Returns: TRUE if Pass List is in use or	  *
	 *		  FALSE if not in use			      *
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

if (isset($_POST['del_btn'])) {
	$need_save = false;
	if (is_array($_POST['del']) && count($_POST['del'])) {
		foreach ($_POST['del'] as $itemi) {
			/* make sure list is not being referenced by any interface */
			if (suricata_is_passlist_used($a_passlist[$_POST['list_id']]['name'])) {
				$input_errors[] = gettext("Pass List '{$a_passlist[$itemi]['name']}' is currently assigned to a Suricata interface and cannot be deleted.  Unassign it from all Suricata interfaces first.");
			} else {
				unset($a_passlist[$itemi]);
				$need_save = true;
			}
		}
		if ($need_save) {
			write_config("Suricata pkg: deleted PASS LIST.");
			conf_mount_rw();
			sync_suricata_package_config();
			conf_mount_ro();
			header("Location: /suricata/suricata_passlist.php");
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
		if (suricata_is_passlist_used($a_passlist[$_POST['list_id']]['name'])) {
			$input_errors[] = gettext("This Pass List '{$a_passlist[$delbtn_list]['name']}' is currently assigned to a Suricata interface and cannot be deleted.  Unassign it from all Suricata interfaces first.");
		}
		else {
			unset($a_passlist[$delbtn_list]);
			write_config("Suricata pkg: deleted PASS LIST.");
			conf_mount_rw();
			sync_suricata_package_config();
			conf_mount_ro();
			header("Location: /suricata/suricata_passlist.php");
			return;
		}
	}
}

$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Pass Lists"));
include_once("head.inc");

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}

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

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Configured Pass Lists');?></h2></div>
	<div class="table-responsive panel-body">
		<form action="/suricata/suricata_passlist.php" method="post">
			<input type="hidden" name="list_id" id="list_id" value=""/>
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
					<td>
						<input type="checkbox" id="frc<?=$i?>" name="del[]" value="<?=$i?>" onclick="fr_bgcolor('<?=$i?>')" />
					</td>
					<td>
						<?=htmlspecialchars($list['name'])?>
					</td>
					<?php if (!empty($list['address'])) : ?>
					<td title="<?=filter_expand_alias($list['address'])?>">
						<?=gettext($list['address'])?>
					</td>
					<?php else : ?>
					<td>
					</td>
					<?php endif; ?>
					<td>
						<?=htmlspecialchars($list['descr'])?>
					</td>
					<td>
						<a href="suricata_passlist_edit.php?id=<?=$i?>" class="fa fa-pencil fa-lg" title="<?=gettext('Edit Pass List');?>"></a>
					</td>
				</tr>
				<?php endforeach; ?>
				<tr>
					<td colspan="5" class="text-right">
						<a href="suricata_passlist_edit.php?id=<?=$id_gen?>" role="button" class="btn btn-sm btn-success" title="<?=gettext('add a new pass list');?>">
							<i class="fa fa-plus icon-embed-btn"></i>
							<?=gettext("Add");?>
						</a>
						<?php if (count($a_passlist) > 0): ?>
							<button type="submit" name="del_btn" id="del_btn" class="btn btn-danger btn-sm" title="<?=gettext('Delete Selected Items');?>">
								<i class="fa fa-trash icon-embed-btn"></i>
								<?=gettext('Delete');?>
							</button>
						<?php endif; ?>
					</td>
				</tr>
					</tbody>
			</table>
		</form>
	</div>
</div>

<div class="infoblock">
	<?=print_info_box('<strong>Note:</strong><ol><li>Here you can create Pass List files for your Suricata package rules. Hosts on a Pass List are never blocked by Suricata.</li><li>Add all the IP addresses or networks (in CIDR notation) you want to protect against Suricata block decisions.</li><li>The default Pass List includes the WAN IP and gateway, defined DNS servers, VPNs and locally-attached networks.</li><li>Be careful, it is very easy to get locked out of your system by altering the default settings.</li><li>To use a custom Pass List on an interface, you must manually assign the list using the drop-down control on the Interface Settings tab.</li></ol><p>Remember you must restart Suricata on the interface for changes to take effect!</p>', info)?>
</div>

<?php include("foot.inc"); ?>
