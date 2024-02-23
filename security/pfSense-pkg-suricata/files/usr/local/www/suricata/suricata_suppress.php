<?php
/*
 * suricata_suppress.php
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

$a_suppress = config_get_path('installedpackages/suricata/suppress/item', []);
$id_gen = count($a_suppress);

function suricata_suppresslist_used($supplist) {

	/****************************************************************/
	/* This function tests if the passed Suppress List is currently */
	/* assigned to an interface.  It returns TRUE if the list is	*/
	/* in use.							*/
	/*								*/
	/* Returns:  TRUE if list is in use, else FALSE			*/
	/****************************************************************/

	foreach (config_get_path('installedpackages/suricata/rule', []) as $value) {
		if ($value['suppresslistname'] == $supplist)
			return true;
	}
	return false;
}

function suricata_find_suppresslist_interface($supplist) {

	/****************************************************************/
	/* This function finds the first (if more than one) interface   */
	/* configured to use the passed Suppress List and returns the   */
	/* index of the interface in the ['rule'] config array.		*/
	/*								*/
	/* Returns: index of interface in ['rule'] config array or	*/
	/*		  FALSE if no interface found.			*/
	/****************************************************************/

	foreach (config_get_path('installedpackages/suricata/rule', []) as $rule => $value) {
		if ($value['suppresslistname'] == $supplist)
			return $rule;
	}
	return false;
}

if (isset($_POST['del_btn'])) {
	$need_save = false;
	if (is_array($_POST['del']) && count($_POST['del'])) {
		foreach ($_POST['del'] as $itemi) {
			/* make sure list is not being referenced by any interface */
			if (suricata_suppresslist_used($a_suppress[$itemi]['name'])) {
				$input_errors[] = gettext("Suppression List '{$a_suppress[$itemi]['name']}' is currently assigned to a Suricata interface and cannot be deleted.  Unassign it from all Suricata interfaces first.");
			} else {
				unset($a_suppress[$itemi]);
				$need_save = true;
			}
		}
		if ($need_save) {
			config_set_path('installedpackages/suricata/suppress/item', $a_suppress);
			write_config("Suricata pkg: deleted SUPPRESSION LIST.");
			sync_suricata_package_config();
			header("Location: /suricata/suricata_suppress.php");
			return;
		}
	}
}

$pglinks = array("", "/suricata/suricata_interfaces.php", "@self");
$pgtitle = array("Services", "Suricata", "Suppress Lists");
include_once("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), true, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Configured Suppression Lists');?></h2></div>
	<div class="table-responsive panel-body">
		<form action="/suricata/suricata_suppress.php" method="post"><?php if ($savemsg) print_info_box($savemsg); ?>
			<input type="hidden" name="list_id" id="list_id" value=""/>

			<table id="maintable" class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th>&nbsp;</th>
						<th><?=gettext("List Name"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext("Actions"); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $i = 0; foreach ($a_suppress as $list): ?>
					<?php
						if (suricata_suppresslist_used($list['name'])) {
							$icon = "&nbsp;<i class=\"fa-solid fa-info-circle\" style=\"cursor: pointer;\" title=\"" . gettext("List is in use by an instance") . "\"></i>";
						}
						else
							$icon = "";
					?>
					<tr>
						<td>
							<input type="checkbox" name="del[]" value="<?=$i?>" />
						</td>
						<td>
							<?=htmlspecialchars($list['name'])?> <?=$icon?>
						</td>
						<td>
							<?=htmlspecialchars($list['descr'])?>
						</td>
						<td>
							<a href="suricata_suppress_edit.php?id=<?=$i?>">
								<i class="fa-solid fa-pencil fa-lg" title="<?=gettext("Edit Suppress List"); ?>"></i>
							</a>
							<?php if (suricata_suppresslist_used($list['name'])) : ?>
							<a href="/suricata/suricata_interfaces_edit.php?id=<?=suricata_find_suppresslist_interface($list['name'])?>">
								<i class="fa-regular fa-square-caret-right" title="<?=gettext('Goto first instance associated with this Suppress List')?>" style="cursor: pointer;"?></i>
							</a>
							<?php endif; ?>
						</td>
					</tr>
					<?php $i++; endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<nav class="action-buttons">
		<a href="suricata_suppress_edit.php?id=<?=$id_gen?>" class="btn btn-sm btn-success" title="<?=gettext('Add a new suppression list');?>">
			<i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Add");?>
		</a>
		<?php if (count($a_suppress) > 0): ?>
		<button type="submit" name="del_btn" id="del_btn" class="btn btn-danger btn-sm" title="<?=gettext('Delete Selected Items');?>">
			<i class="fa-solid fa-trash-can icon-embed-btn"></i>
			<?=gettext('Delete');?>
		</button>
		<?php endif; ?>
	</nav>
</form>


<div class="infoblock">
	<?=print_info_box('<p><strong>Note:</strong> Here you can create event filtering and suppression for your Suricata package rules.</p><p>Please note that you must restart a running Interface so that changes can take effect.</p><p>You cannot delete a Suppress List that is currently assigned to a Suricata interface (instance).</p><p>You must first unassign the Suppress List on the Interface Edit tab.</p>', 'info')?>
</div>

<?php include("foot.inc"); ?>
