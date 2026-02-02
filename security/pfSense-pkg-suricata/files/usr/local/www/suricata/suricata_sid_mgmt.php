<?php
/*
 * suricata_sid_mgmt.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
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

global $g, $rebuild_rules;

$suricatadir = SURICATADIR;
$pconfig = array();

// Grab saved settings from configuration
$a_nat = config_get_path('installedpackages/suricata/rule', []);
$a_list = config_get_path('installedpackages/suricata/sid_mgmt_lists/item', []);
$pconfig['auto_manage_sids'] = config_get_path('installedpackages/suricata/config/0/auto_manage_sids');

// Set default to not show SID modification lists editor controls
$sidmodlist_edit_style = "display: none;";

if (!empty($_POST))
	$pconfig = $_POST;

function suricata_is_sidmodslist_active($sidlist) {

	/*****************************************************
	 * This function checks all the configured Suricata  *
	 * interfaces to see if the passed SID Mods List is  *
	 * used by an interface.                             *
	 *                                                   *
	 * Returns: TRUE  if List is in use                  *
	 *          FALSE if List can be deleted             *
	 *****************************************************/

	foreach (config_get_path('installedpackages/suricata/rule', []) as $rule) {
		if ($rule['enable_sid_file'] == $sidlist) {
			return TRUE;
		}
		if ($rule['disable_sid_file'] == $sidlist) {
			return TRUE;
		}
		if ($rule['modify_sid_file'] == $sidlist) {
			return TRUE;
		}

		// The tests below let the user remove an assigned
		// DROP_SID or REJECT_SID list from an interface
		// that now uses a mode where these list types
		// are no longer applicable.
		if ($rule['blockoffenders'] == 'on' && ($rule['ips_mode'] == 'ips_mode_inline' || $rule['block_drops_only'] == 'on')) {
			if ($rule['drop_sid_file'] == $sidlist) {
				return TRUE;
			}
		}
		if ($rule['blockoffenders'] == 'on' && $rule['ips_mode'] == 'ips_mode_inline') {
			if ($rule['reject_sid_file'] == $sidlist) {
				return TRUE;
			}
		}
	}
	return FALSE;
}

if (isset($_POST['upload'])) {
	if ($_FILES["sidmods_fileup"]["error"] == UPLOAD_ERR_OK) {
		$tmp = array();
		$tmp['name'] = basename($_FILES["sidmods_fileup"]["name"]);
		$tmp['modtime'] = time();
		$tmp_fname = $_FILES["sidmods_fileup"]["tmp_name"];
		$data = file_get_contents($tmp_fname);
		$tmp['content'] = base64_encode(str_replace("\r\n", "\n", $data));

		// Check for duplicate conflicting list name
		foreach ($a_list as $list) {
			if ($list['name'] == $tmp['name']) {
				$input_errors[] = gettext("A list with that name already exists!  Please choose a different name for the list being uploaded.");
				break;
			}
		}
		if (!$input_errors) {
			$a_list[] = $tmp;

			// Write the new configuration
			config_set_path('installedpackages/suricata/sid_mgmt_lists/item', $a_list);
			write_config("Suricata pkg: Uploaded new automatic SID management list.");
		}
	}
	else
		$input_errors[] = gettext("Failed to upload file {$_FILES["sidmods_fileup"]["name"]}");
}

if (isset($_POST['sidlist_delete']) && isset($a_list[$_POST['sidlist_id']])) {
	if (!suricata_is_sidmodslist_active($a_list[$_POST['sidlist_id']]['name'])) {

		// Remove the list from DROP_SID or REJECT_SID if assigned on any interface.
		foreach($a_nat as $k => $rule) {
			if ($rule['drop_sid_file'] == $a_list[$_POST['sidlist_id']]['name']) {
				unset($a_nat[$k]['drop_sid_file']);
			}
			if ($rule['reject_sid_file'] == $a_list[$_POST['sidlist_id']]['name']) {
				unset($a_nat[$k]['reject_sid_file']);
			}
		}

		// Now delete the list itself
		unset($a_list[$_POST['sidlist_id']]);

		// Write the new configuration
		config_set_path('installedpackages/suricata/rule', $a_nat);
		config_set_path('installedpackages/suricata/sid_mgmt_lists/item', $a_list);
		write_config("Suricata pkg: deleted automatic SID management list.");
	}
	else {
		$input_errors[] = gettext("This SID Mods List is currently assigned to an interface and cannot be deleted until the assignment is removed.");
	}
}

if (isset($_POST['sidlist_edit']) && isset($a_list[$_POST['sidlist_id']])) {
	$data = base64_decode($a_list[$_POST['sidlist_id']]['content']);
	$sidmodlist_data = htmlspecialchars($data);
	$sidmodlist_edit_style = "show";
	$sidmodlist_name = $a_list[$_POST['sidlist_id']]['name'];
	$sidmodlist_id = $_POST['sidlist_id'];
	unset($data);
}

if (isset($_POST['save']) && isset($_POST['sidlist_data']) && isset($_POST['listid'])) {
	if (strlen($_POST['sidlist_name']) > 0) {
		$tmp = array();
		$tmp['name'] = basename($_POST['sidlist_name']);
		$tmp['modtime'] = time();
		$tmp['content'] = base64_encode(str_replace("\r\n", "\n", $_POST['sidlist_data']));

		// If this test is TRUE, then we are adding a new list
		if ($_POST['listid'] == count($a_list)) {
			$a_list[] = $tmp;

			// Write the new configuration
			config_set_path('installedpackages/suricata/sid_mgmt_lists/item', $a_list);
			write_config("Suricata pkg: added new automatic SID management list.");
		}
		else {
			$a_list[$_POST['listid']] = $tmp;

			// Write the new configuration
			config_set_path('installedpackages/suricata/sid_mgmt_lists/item', $a_list);
			write_config("Suricata pkg: updated automatic SID management list.");
		}
		unset($tmp);
	}
	else {
		$input_errors[] = gettext("You must provide a valid name for the new SID Mods List.");
		$sidmodlist_edit_style = "display: table-row-group;";
	}
}

if (isset($_POST['save_auto_sid_conf'])) {
	config_set_path('installedpackages/suricata/config/0/auto_manage_sids', $pconfig['auto_manage_sids'] ? "on" : "off");

	// Grab the SID Mods config for the interfaces from the form's controls array
	foreach ($_POST['sid_state_order'] as $k => $v) {
		$a_nat[$k]['sid_state_order'] = $v;
	}
	foreach ($_POST['enable_sid_file'] as $k => $v) {
		if (strcasecmp($v, "None") == 0) {
			unset($a_nat[$k]['enable_sid_file']);
			continue;
		}
		$a_nat[$k]['enable_sid_file'] = $v;
	}
	foreach ($_POST['disable_sid_file'] as $k => $v) {
		if (strcasecmp($v, "None") == 0) {
			unset($a_nat[$k]['disable_sid_file']);
			continue;
		}
		$a_nat[$k]['disable_sid_file'] = $v;
	}
	foreach ($_POST['modify_sid_file'] as $k => $v) {
		if (strcasecmp($v, "None") == 0) {
			unset($a_nat[$k]['modify_sid_file']);
			continue;
		}
		$a_nat[$k]['modify_sid_file'] = $v;
	}

	foreach ($_POST['drop_sid_file'] as $k => $v) {
		if (strcasecmp($v, "None") == 0) {
			unset($a_nat[$k]['drop_sid_file']);
			continue;
		}
		$a_nat[$k]['drop_sid_file'] = $v;
	}

	foreach ($_POST['reject_sid_file'] as $k => $v) {
		if (strcasecmp($v, "None") == 0) {
			unset($a_nat[$k]['reject_sid_file']);
			continue;
		}
		$a_nat[$k]['reject_sid_file'] = $v;
	}

	// Write the new configuration
	config_set_path('installedpackages/suricata/rule', $a_nat);
	write_config("Suricata pkg: updated automatic SID management settings.");

	$intf_msg = "";

	// If any interfaces were marked for restart, then do it
	if (is_array($_POST['torestart'])) {
		foreach ($_POST['torestart'] as $k) {
			// Update the suricata.yaml file and
			// rebuild rules for this interface.
			$rebuild_rules = true;
			suricata_generate_yaml($a_nat[$k]);
			$rebuild_rules = false;

			// Signal Suricata to "live reload" the rules
			suricata_reload_config($a_nat[$k]);

			$intf_msg .= convert_friendly_interface_to_friendly_descr($a_nat[$k]['interface']) . ", ";
		}
		$savemsg = gettext("Changes were applied to these interfaces: " . trim($intf_msg, ' ,') . " and Suricata signaled to live-load the new rules.");

		// Sync to configured CARP slaves if any are enabled
		suricata_sync_on_changes();
	}
}

if (isset($_POST['sidlist_dnload']) &&
    isset($_POST['sidlist_id'])) {
	if (array_key_exists($_POST['sidlist_id'], $a_list)) {
		send_user_download('data',
					base64_decode($a_list[$_POST['sidlist_id']]['content']),
					basename($a_list[$_POST['sidlist_id']]['name']));
	} else {
		$savemsg = gettext("Unable to locate the list specified!");
	}
}

if (isset($_POST['sidlist_dnload_all'])) {
	$file_path = g_get('tmp_path') . '/suricata_sid_conf_files_' . date("Y-m-d-H-i-s") . '.tar.gz';

	// Create a temporary directory to hold the lists as individual files
	$tmpdirname = g_get('tmp_path') . '/sidmods/';
	safe_mkdir($tmpdirname);

	// Walk all saved lists and write them out to individual files
	foreach($a_list as $list) {
		file_put_contents($tmpdirname . basename($list['name']), base64_decode($list['content']));
		touch($tmpdirname . basename($list['name']), $list['modtime']);
	}

	// Put all the files into a single tar gzip archive
	exec('/usr/bin/tar -C ' . escapeshellarg($tmpdirname) . ' --strip-components 1 -czf ' . escapeshellarg($file_path) . ' .');

	if (file_exists($file_path)) {
		// Remove all the temporary files and directory if created
		if (is_dir($tmpdirname)) {
			rmdir_recursive($tmpdirname);
		}
		send_user_download('file', $file_path);
		unlink_if_exists($file_path);
		exit;
	} else {
		$savemsg = gettext("An error occurred while creating the gzip archive!");
	}

	// Remove all the temporary files and directory if created
	if (is_dir($tmpdirname)) {
		rmdir_recursive($tmpdirname);
	}
	unlink_if_exists($file_path);
}

// Get all the SID Mods Lists as an array
// Leave this as the last thing before spewing the page HTML
// so we can pick up any changes made in code above.
$sidmodlists = config_get_path('installedpackages/suricata/sid_mgmt_lists/item', []);
$sidmodselections = Array();
$sidmodselections[] = "None";
foreach ($sidmodlists as $list) {
	$sidmodselections[] = $list['name'];
}

$pglinks = array("", "/suricata/suricata_interfaces.php", "@self");
$pgtitle = array("Services", "Suricata", "SID Management");
include_once("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), true, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

/* Display Alert message, under form tag or no refresh */
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

?>

<form action="suricata_sid_mgmt.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
	<input type="hidden" name="MAX_FILE_SIZE" value="100000000" />
	<input type="hidden" name="sidlist_id" id="sidlist_id" value=""/>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("General Settings")?></h2></div>
		<div class="panel-body table-responsive">

			<div class="form-group">
				<label class="col-sm-2 control-label">
					<?= gettext("Enable Automatic SID State Management"); ?>
				</label>
				<div class="checkbox col-sm-10">
					<label>
						<input type="checkbox" id="auto_manage_sids" name="auto_manage_sids" value="on"
						<?php if ($pconfig['auto_manage_sids'] == 'on') echo " checked"; ?>
						onclick="enable_sid_conf();" />
						<?=gettext("Enable automatic management of rule state and content using SID Management Configuration Lists.  Default is Not Checked.")?>
					</label>
					<span class="help-block">
						<?=gettext("When checked, Suricata will automatically enable/disable/modify text rules upon each update using criteria ") .
						gettext("specified in SID Management Configuration Lists.  The supported configuration list format is the same as that used ") .
						gettext("by PulledPork and Oinkmaster.  See the included sample conf lists for usage examples.  ") .
						gettext("Either upload existing configurations to the firewall or create new ones by clicking ADD below."); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("SID Management Configuration Lists")?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<tbody>
				<tr>
					<td>
						<table class="table table-striped table-hover table-condensed">
							<thead>
								<tr>
									<th><?=gettext("SID Mods List Name"); ?></th>
									<th><?=gettext("Last Modified Time"); ?></th>
									<th><?=gettext("List Actions")?>
									</th>
								</tr>
							</thead>
							<tbody>
						<?php foreach ($sidmodlists as $i => $list): ?>
							<tr>
								<td><?=gettext($list['name']); ?></td>
								<td><?=date('M-d Y g:i a', $list['modtime'] + 0); ?></td>

								<td>
									<a name="sidlist_editX[]" id="sidlist_editX[]" type="button" title="<?=gettext('Edit this SID Mods List');?>"
										onClick='sidlistid="<?=$i;?>"' style="cursor: pointer;">
										<i class="fa-solid fa-pencil"></i>
									</a>

									<a name="sidlist_deleteX[]" id="sidlist_deleteX[]" type="button" title="<?=gettext('Delete this SID Mods List');?>"
										onClick='sidlistid="<?=$i;?>"' style="cursor: pointer;">
										<i class="fa-solid fa-trash-can" title="<?=gettext('Delete this SID Mods List');?>"></i>
									</a>

									<a name="sidlist_dnloadX[]" id="sidlist_dnloadX[]" type="button" title="<?=gettext('Download this SID Mods List');?>"
										onClick='sidlistid="<?=$i;?>"' style="cursor: pointer;">
										<i class="fa-solid fa-download" title="<?=gettext('Download this SID Mods List');?>"></i>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
						</table>
					</td>
				</tr>
				</tbody>
			</table>
		</div>

		<!-- Modal file upload window -->
		<div class="modal fade" role="dialog" id="uploader" name="uploader">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>

						<h3 class="modal-title" id="myModalLabel"><?=gettext("SID Management List Upload")?></h3>
					</div>

					<div class="modal-body">
						<?=gettext("Click BROWSE to select a file to import, and then click UPLOAD.  Click CLOSE to quit."); ?><br /><br />

						<input type="file" class="btn btn-info" name="sidmods_fileup" id="sidmods_fileup" class="file" size="50" /><br />
						<input type="submit" class="btn btn-sm btn-primary" name="upload" id="upload" value="<?=gettext("Upload");?>" title="<?=gettext("Upload selected SID mods list to firewall");?>"/>&nbsp;&nbsp;
						<input type="button" class="btn btn-sm btn-default" value="<?=gettext("Close");?>" data-dismiss="modal"/><br/>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal SID editor window -->
		<div class="modal fade" role="dialog" id="sidlist_editor">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>

						<h3 class="modal-title" id="myModalLabel"><?=gettext("SID Auto-Management List Editor")?></h3>
					</div>

					<div class="modal-body">
						<input type="hidden" name="listid" id="listid" value="<?=htmlspecialchars($sidmodlist_id);?>" />
						<?=gettext("List Name: ");?>
						<input type="text" size="45" class="form-control file" id="sidlist_name" name="sidlist_name" value="<?=htmlspecialchars($sidmodlist_name);?>" /><br />
						<button type="submit" class="btn btn-sm btn-primary" id="save" name="save" value="<?=gettext("Save");?>" title="<?=gettext("Save changes and close editor");?>">
							<i class="fa-solid fa-save icon-embed-btn"></i>
							<?=gettext("Save");?>
						</button>
						<button type="button" class="btn btn-sm btn-warning" id="cancel" name="cancel" value="<?=gettext("Cancel");?>" data-dismiss="modal" title="<?=gettext("Abandon changes and quit editor");?>">
							<?=gettext("Cancel");?>
						</button><br /><br />

						<textarea class="form-control" wrap="off" cols="80" rows="20" name="sidlist_data" id="sidlist_data"
						><?=$sidmodlist_data;?></textarea>
					</div>
				</div>
			</div>
		</div>
	</div>

	<nav class="action-buttons">

		<button data-toggle="modal" data-target="#sidlist_editor" role="button" aria-expanded="false" type="button" name="sidlist_new" id="sidlist_new" class="btn btn-success btn-sm" title="<?=gettext('Create a new SID Mods List');?>"
		onClick="document.getElementById('sidlist_data').value=''; document.getElementById('sidlist_name').value=''; document.getElementById('sidlist_editor').style.display='table-row-group'; document.getElementById('sidlist_name').focus();
			document.getElementById('sidlist_id').value='<?=count($a_list);?>';">
			<i class="fa-solid fa-plus icon-embed-btn"></i><?=gettext("Add")?>
		</button>

		<button data-toggle="modal" data-target="#uploader" role="button" aria-expanded="false" type="button" name="sidlist_import" id="sidlist_import" class="btn btn-info btn-sm" title="<?=gettext('Import/upload SID Mods List');?>">
			<i class="fa-solid fa-upload icon-embed-btn"></i>
			<?=gettext("Import")?>
		</button>

		<button type="input" name="sidlist_dnload_all" id="sidlist_dnload_all" class="btn btn-info btn-sm" title="<?=gettext('Download all SID Mods Lists in a single gzip archive');?>">
			<i class="fa-solid fa-download icon-embed-btn"></i>
			<?=gettext("Download")?>
		</button>

	</nav>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Interface SID Management List Assignments")?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
				   <tr>
					<th><?=gettext("Rebuild")?></th>
					<th><?=gettext("Interface")?></th>
					<th><?=gettext("SID State Order")?></th>
					<th><?=gettext("Enable SID List")?></th>
					<th><?=gettext("Disable SID List")?></th>
					<th><?=gettext("Modify SID List")?></th>
					<th><?=gettext("Drop SID List")?></th>
					<th><?=gettext("Reject SID List")?></th>
				   </tr>
				</thead>
				<tbody>
			   <?php foreach ($a_nat as $k => $natent): ?>
				<?php
					// Skip displaying any instance where the physical pfSense interface is missing
					if (get_real_interface($natent['interface']) == "") {
						continue;
					}
				?>
				<tr>
					<td class="text-center">
						<input type="checkbox" name="torestart[]" id="torestart[]" value="<?=$k;?>" title="<?=gettext("Apply new configuration and rebuild rules for this interface when saving");?>" />
					</td>
					<td><?=convert_friendly_interface_to_friendly_descr($natent['interface']); ?></td>
					<td>
						<select name="sid_state_order[<?=$k?>]" class="form-control" id="sid_state_order[<?=$k?>]">
							<?php
								foreach (array("disable_enable" => "Disable, Enable", "enable_disable" => "Enable, Disable") as $key => $order) {
									if ($key == $natent['sid_state_order'])
										echo "<option value='{$key}' selected>";
									else
										echo "<option value='{$key}'>";

									echo htmlspecialchars($order) . '</option>';
								}
							?>
						</select>
					</td>
					<td>
						<select name="enable_sid_file[<?=$k?>]" class="form-control" id="enable_sid_file[<?=$k?>]">
							<?php
								foreach ($sidmodselections as $choice) {
									if ($choice == $natent['enable_sid_file'])
										echo "<option value='{$choice}' selected>";
									else
										echo "<option value='{$choice}'>";

									echo htmlspecialchars(gettext($choice)) . '</option>';
								}
							?>
						</select>
					</td>
					<td>
						<select name="disable_sid_file[<?=$k?>]" class="form-control" id="disable_sid_file[<?=$k?>]">
							<?php
								foreach ($sidmodselections as $choice) {
									if ($choice == $natent['disable_sid_file'])
										echo "<option value='{$choice}' selected>";
									else
										echo "<option value='{$choice}'>";

									echo htmlspecialchars(gettext($choice)) . '</option>';
								}
							?>
						</select>
					</td>
					<td>
						<select name="modify_sid_file[<?=$k?>]" class="form-control" id="modify_sid_file[<?=$k?>]">
							<?php
								foreach ($sidmodselections as $choice) {
									if ($choice == $natent['modify_sid_file'])
										echo "<option value='{$choice}' selected>";
									else
										echo "<option value='{$choice}'>";

									echo htmlspecialchars(gettext($choice)) . '</option>';
								}
							?>
						</select>
					</td>
					<td>
						<?php if ($natent['blockoffenders'] == 'on' && ($natent['ips_mode'] == 'ips_mode_inline' || $natent['block_drops_only'] == 'on')) : ?>
							<select name="drop_sid_file[<?=$k?>]" class="form-control" id="drop_sid_file[<?=$k?>]">
								<?php
									foreach ($sidmodselections as $choice) {
										if ($choice == $natent['drop_sid_file'])
											echo "<option value='{$choice}' selected>";
										else
											echo "<option value='{$choice}'>";

										echo htmlspecialchars(gettext($choice)) . '</option>';
									}
								?>
							</select>
						<?php else : ?>
							<input type="hidden" name="drop_sid_file[<?=$k?>]" id="drop_sid_file[<?=$k?>]" value="<?=isset($natent['drop_sid_file']) ? $natent['drop_sid_file'] : 'None';?>">
							<span class="text-center"><?=gettext("N/A")?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ($natent['blockoffenders'] == 'on' && $natent['ips_mode'] == 'ips_mode_inline') : ?>
							<select name="reject_sid_file[<?=$k?>]" class="form-control" id="reject_sid_file[<?=$k?>]">
								<?php
									foreach ($sidmodselections as $choice) {
										if ($choice == $natent['reject_sid_file'])
											echo "<option value='{$choice}' selected>";
										else
											echo "<option value='{$choice}'>";

										echo htmlspecialchars(gettext($choice)) . '</option>';
									}
								?>
							</select>
						<?php else : ?>
							<input type="hidden" name="reject_sid_file[<?=$k?>]" id="reject_sid_file[<?=$k?>]" value="<?=isset($natent['reject_sid_file']) ? $natent['reject_sid_file'] : 'None';?>">
							<span class="text-center"><?=gettext("N/A")?></span>
						<?php endif; ?>
					</td>
				</tr>
			   <?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</div>

		<button type="submit" id="save_auto_sid_conf" name="save_auto_sid_conf" class="btn btn-primary" value="<?=gettext("Save");?>" title="<?=gettext("Save SID Management configuration");?>" >
			<i class="fa-solid fa-save icon-embed-btn"></i>
			<?=gettext("Save");?>
		</button>
		&nbsp;&nbsp;<?=gettext("Remember to save changes before exiting this page"); ?>

</form>
</br />

<div class="infoblock">
<?php
	print_info_box(
		'<p>' .
			gettext("Check the box beside an interface to immediately apply new auto-SID management changes and signal Suricata to live-load the new rules for the interface when clicking Save; " .
				"otherwise only the new file assignments will be saved.") .
		'</p>' .
		'<p>' .
			gettext("SID State Order controls the order in which enable and disable state modifications are performed. An example would be to disable an entire category and later enable only a rule or two from it. " .
				" In this case you would choose 'disable,enable' for the State Order.  Note that the last action performed takes priority.") .
		'</p>' .
		'<p>' .
			gettext("The Enable SID File, Disable SID File, Modify SID File and Drop SID File drop-down controls specify which rule modification lists are run automatically for the interface.  Setting a list control to 'None' disables that modification. " .
				"Setting all list controls for an interface to 'None' disables automatic SID state management for the interface.") .
		'</p>', 'info', false);
?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	$('[id^=sidlist_editX]').click(function () {
		$('#sidlist_edit').remove();
		$('#sidlist_delete').remove();
		$('#sidlist_dnload').remove();
		$('#sidlist_id').val(sidlistid);
		$('<input type="hidden" name="sidlist_edit" id="sidlist_edit" value="0"/>').appendTo($(form));
		$(form).submit();
	});

	$('[id^=sidlist_deleteX]').click(function () {
		$('#sidlist_edit').remove();
		$('#sidlist_delete').remove();
		$('#sidlist_dnload').remove();
		$('#sidlist_id').val(sidlistid);
		$('<input type="hidden" name="sidlist_delete" id="sidlist_delete" value="0"/>').appendTo($(form));
		$(form).submit();
	});

	$('[id^=sidlist_dnloadX]').click(function () {
		$('#sidlist_edit').remove();
		$('#sidlist_delete').remove();
		$('#sidlist_dnload').remove();
		$('#sidlist_id').val(sidlistid);
		$('<input type="hidden" name="sidlist_dnload" id="sidlist_dnload" value="0"/>').appendTo($(form));
		$(form).submit();
	});

	// If the user is editing a file, open the modal on page load
<?php if ($sidmodlist_edit_style == "show") : ?>
	$("#sidlist_editor").modal('show');
<?php endif ?>
});
//]]>
</script>

<?php
include("foot.inc"); ?>
