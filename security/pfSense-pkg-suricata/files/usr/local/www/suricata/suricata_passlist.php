<?php
/*
 * suricata_passlist.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2023 Bill Meeks
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

$a_passlist = config_get_path('installedpackages/suricata/passlist/item', []);

// Calculate the next Pass List index ID
$id_gen = count(config_get_path('installedpackages/suricata/passlist/item', []));

function suricata_is_passlist_used($list) {

	/**********************************************
	 * This function tests the provided Pass List *
	 * to determine if it is assigned to an	      *
	 * interface.                                 *
	 *                                            *
	 * On Entry: $list -> Pass List name to test  *
	 *                                            *
	 * Returns: TRUE if Pass List is in use or    *
	 *		  FALSE if not in use         *
	 **********************************************/

	foreach(config_get_path('installedpackages/suricata/rule', []) as $v) {
		if (isset($v['passlistname']) && $v['passlistname'] == $list)
			return TRUE;
		if (isset($v['homelistname']) && $v['homelistname'] == $list)
			return TRUE;
		if (isset($v['externallistname']) && $v['externallistname'] == $list)
			return TRUE;
	}
	return FALSE;
}

if (isset($_POST['del_btn'])) {

	// User checked one or more checkboxes and clicked 'Delete' button,
	// so process the array of checked passlist entries.
	$need_save = false;
	if (is_array($_POST['del']) && count($_POST['del'])) {
		foreach ($_POST['del'] as $itemi) {
			/* make sure list is not being referenced by any interface */
			if (suricata_is_passlist_used($a_passlist[$itemi]['name'])) {
				$input_errors[] = gettext("Pass List '{$a_passlist[$itemi]['name']}' is currently assigned to a Suricata interface and cannot be deleted.  Unassign it from all Suricata interfaces first.");
			} else {
				unset($a_passlist[$itemi]);
				$need_save = true;
			}
		}
		if ($need_save && empty($input_errors)) {
			config_set_path('installedpackages/suricata/passlist/item', $a_passlist);
			write_config("Suricata pkg: deleted PASS LIST.");
			sync_suricata_package_config();
			header("Location: /suricata/suricata_passlist.php");
			return;
		}
	}
}
else {
	// User clicked the 'trash can' icon beside a single list entry
	unset($delbtn_list);
	$need_save = false;

	foreach ($_POST as $pn => $pd) {
		if (preg_match("/cdel_(\d+)/", $pn, $matches)) {
			$delbtn_list = $matches[1];
		}
	}
	if (is_numeric($delbtn_list) && $a_passlist[$delbtn_list]) {
		if (suricata_is_passlist_used($a_passlist[$delbtn_list]['name'])) {
			$input_errors[] = gettext("This Pass List '{$a_passlist[$delbtn_list]['name']}' is currently assigned to a Suricata interface and cannot be deleted.  Unassign it from all Suricata interfaces first.");
		}
		else {
			unset($a_passlist[$delbtn_list]);
			config_set_path('installedpackages/suricata/passlist/item', $a_passlist);
			write_config("Suricata pkg: deleted PASS LIST.");
			sync_suricata_package_config();
			header("Location: /suricata/suricata_passlist.php");
			return;
		}
	}
}

$pglinks = array("", "/suricata/suricata_interfaces.php", "@self");
$pgtitle = array("Services", "Suricata", "Pass Lists");
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
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php");
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
						<th>Assigned</th>
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
					<td>
						<?php suricata_is_passlist_used($list['name']) ? print(gettext("Yes")) : print(gettext("No"));?>
					</td>
					<td>
						<?=htmlspecialchars($list['descr'])?>
					</td>
					<td>
						<a href="suricata_passlist_edit.php?id=<?=$i?>" class="fa-solid fa-pencil fa-lg" title="<?=gettext('Edit Pass List');?>"></a>
						<a class="fa-solid fa-trash-can no-confirm" id="Xcdel_<?=$i?>" title="<?=gettext('Delete this Pass List'); ?>"></a>
						<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="cdel_<?=$i?>" name="cdel_<?=$i?>" value="cdel_<?=$i?>" title="<?=gettext('Delete this Pass List'); ?>">Delete Pass List</button>
					</td>
				</tr>
				<?php endforeach; ?>
				<tr>
					<td colspan="5" class="text-right">
						<a href="suricata_passlist_edit.php?id=<?=$id_gen?>" role="button" class="btn btn-sm btn-success" title="<?=gettext('add a new pass list');?>">
							<i class="fa-solid fa-plus icon-embed-btn"></i>
							<?=gettext("Add");?>
						</a>
						<?php if (count($a_passlist) > 0): ?>
							<button type="submit" name="del_btn" id="del_btn" class="btn btn-danger btn-sm" title="<?=gettext('Delete Selected Items');?>">
								<i class="fa-solid fa-trash-can icon-embed-btn"></i>
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
	<?=print_info_box('<strong>Note:</strong><ol><li>Here you can create Pass List files for your Suricata package rules. Hosts on a Pass List are never blocked by Suricata.</li><li>Add all the IP addresses or networks (in CIDR notation) you want to protect against Suricata block decisions.</li><li>The default Pass List includes the WAN IP and gateway, defined DNS servers, VPNs and locally-attached networks.</li><li>Be careful, it is very easy to get locked out of your system by altering the default settings.</li><li>To use a custom Pass List on an interface, you must manually assign the list using the drop-down control on the Interface Settings tab.</li></ol><p>Remember you must restart Suricata on the interface for changes to take effect!</p>', 'info')?>
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
