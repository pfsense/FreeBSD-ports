<?php
/*
 * snort_sid_mgmt.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2019 Bill Meeks
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
require_once("/usr/local/pkg/snort/snort.inc");

global $g, $config, $rebuild_rules;

$snortdir = SNORTDIR;
$pconfig = array();

// Grab saved settings from configuration
if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
$a_nat = &$config['installedpackages']['snortglobal']['rule'];

if (!is_array($config['installedpackages']['snortglobal']['sid_mgmt_lists']))
	$config['installedpackages']['snortglobal']['sid_mgmt_lists'] = array();
if (!is_array($config['installedpackages']['snortglobal']['sid_mgmt_lists']['item']))
	$config['installedpackages']['snortglobal']['sid_mgmt_lists']['item'] = array();
$a_list = &$config['installedpackages']['snortglobal']['sid_mgmt_lists']['item'];

$pconfig['auto_manage_sids'] = $config['installedpackages']['snortglobal']['auto_manage_sids'];

// Set default to not show SID modification lists editor controls
$sidmodlist_edit_style = "display: none;";

if (!empty($_POST))
	$pconfig = $_POST;

function snort_is_sidmodslist_active($sidlist) {

	/*****************************************************
	 * This function checks all the configured Snort     *
	 * interfaces to see if the passed SID Mods List is  *
	 * used by an interface.                             *
	 *                                                   *
	 * Returns: TRUE  if List is in use                  *
	 *          FALSE if List is not in use              *
	 *****************************************************/

	global $g, $config;

	if (!is_array($config['installedpackages']['snortglobal']['rule']))
		return FALSE;

	foreach ($config['installedpackages']['snortglobal']['rule'] as $rule) {
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
		if ($rule['blockoffenders7'] == 'on' && $rule['ips_mode'] == 'ips_mode_inline') {
			if ($rule['drop_sid_file'] == $sidlist) {
				return TRUE;
			}
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
			write_config("Snort pkg: Uploaded new automatic SID management list.");
		}
	}
	else
		$input_errors[] = gettext("Failed to upload file {$_FILES["sidmods_fileup"]["name"]}");
}

if (isset($_POST['sidlist_delete']) && isset($a_list[$_POST['sidlist_id']])) {
	if (!snort_is_sidmodslist_active($a_list[$_POST['sidlist_id']]['name'])) {

		// Remove the list from DROP_SID or REJECT_SID if assigned on any interface.
		foreach($a_nat as $k => $rule) {
			if ($rule['drop_sid_file'] == $a_list[$_POST['sidlist_id']]['name']) {
				unset($a_nat[$k]['drop_sid_file']);
			}
			if ($rule['reject_sid_file'] == $a_list[$_POST['sidlist_id']]['name']) {
				unset($a_nat[$k]['reject_sid_file']);
			}
		}
		unset($a_list[$_POST['sidlist_id']]);

		// Write the new configuration
		write_config("Snort pkg: deleted automatic SID management list.");
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
		$tmp['name'] = $_POST['sidlist_name'];
		$tmp['modtime'] = time();
		$tmp['content'] = base64_encode(str_replace("\r\n", "\n", $_POST['sidlist_data']));

		// If this test is TRUE, then we are adding a new list
		if ($_POST['listid'] == count($a_list)) {
			$a_list[] = $tmp;

			// Write the new configuration
			write_config("Snort pkg: added new automatic SID management list.");
		}
		else {
			$a_list[$_POST['listid']] = $tmp;

			// Write the new configuration
			write_config("Snort pkg: updated automatic SID management list.");
		}
		unset($tmp);
	}
	else {
		$input_errors[] = gettext("You must provide a valid name for the new SID Mods List.");
		$sidmodlist_edit_style = "display: table-row-group;";
	}
}

if (isset($_POST['save_auto_sid_conf'])) {
	$config['installedpackages']['snortglobal']['auto_manage_sids'] = $pconfig['auto_manage_sids'] ? "on" : "off";

	// Grab the SID Mods config for the interfaces from the form's controls array
	if (is_array($_POST['sid_state_order'])) {
		foreach ($_POST['sid_state_order'] as $k => $v) {
			$a_nat[$k]['sid_state_order'] = $v;
		}
	}
	if (is_array($_POST['enable_sid_file'])) {
		foreach ($_POST['enable_sid_file'] as $k => $v) {
			if ($v == "None") {
				unset($a_nat[$k]['enable_sid_file']);
				continue;
			}
			$a_nat[$k]['enable_sid_file'] = $v;
		}
	}
	if (is_array($_POST['disable_sid_file'])) {
		foreach ($_POST['disable_sid_file'] as $k => $v) {
			if ($v == "None") {
				unset($a_nat[$k]['disable_sid_file']);
				continue;
			}
			$a_nat[$k]['disable_sid_file'] = $v;
		}
	}
	if (is_array($_POST['modify_sid_file'])) {
		foreach ($_POST['modify_sid_file'] as $k => $v) {
			if ($v == "None") {
				unset($a_nat[$k]['modify_sid_file']);
				continue;
			}
			$a_nat[$k]['modify_sid_file'] = $v;
		}
	}

	if (is_array($_POST['drop_sid_file'])) {
		foreach ($_POST['drop_sid_file'] as $k => $v) {
			if ($v == "None") {
				unset($a_nat[$k]['drop_sid_file']);
				continue;
			}
			$a_nat[$k]['drop_sid_file'] = $v;
		}
	}

	if (is_array($_POST['reject_sid_file'])) {
		foreach ($_POST['reject_sid_file'] as $k => $v) {
			if ($v == "None") {
				unset($a_nat[$k]['reject_sid_file']);
				continue;
			}
			$a_nat[$k]['reject_sid_file'] = $v;
		}
	}

	// Write the new configuration
	write_config("Snort pkg: updated automatic SID management settings.");

	$intf_msg = "";

	// If any interfaces were marked for restart, then do it
	if (is_array($_POST['torestart'])) {
		foreach ($_POST['torestart'] as $k) {
			// Update the snort.conf file and
			// rebuild rules for this interface.
			$rebuild_rules = true;
			snort_generate_conf($a_nat[$k]);
			$rebuild_rules = false;

			// Signal Snort to "live reload" the rules
			snort_reload_config($a_nat[$k]);

			$intf_msg .= convert_friendly_interface_to_friendly_descr($a_nat[$k]['interface']) . ", ";
		}
		$savemsg = gettext("Changes were applied to these interfaces: " . trim($intf_msg, ' ,') . " and Snort signaled to live-load the new rules.");

		// Sync to configured CARP slaves if any are enabled
		snort_sync_on_changes();
	}
}

if (isset($_POST['sidlist_dnload']) && isset($_POST['sidlist_id'])) {
	$file = $a_list[$_POST['sidlist_id']]['name'];

	// Create a temporary directory to hold the list as an individual file
	$tmpdirname = "{$g['tmp_path']}/sidmods/";
	safe_mkdir("{$tmpdirname}");

	file_put_contents($tmpdirname . $file, base64_decode($a_list[$_POST['sidlist_id']]['content']));
	touch($tmpdirname . $file, $a_list[$_POST['sidlist_id']]['modtime']);	

	if (file_exists($tmpdirname . $file)) {
		if (isset($_SERVER['HTTPS'])) {
			header('Pragma: ');
			header('Cache-Control: ');
		} else {
			header("Pragma: private");
			header("Cache-Control: private, must-revalidate");
		}
		header("Content-Type: application/octet-stream");
		header("Content-length: " . filesize($file));
		header("Content-disposition: attachment; filename = " . basename($file));
		ob_clean();
		flush();
		readfile($tmpdirname . $file);

		// Clean up temporary file if created
		if (is_dir($tmpdirname)) {
			rmdir_recursive($tmpdirname);
		}
		exit;
	}
	else
		$savemsg = gettext("Unable to locate the list specified!");

	// Clean up temporary file if created
	if (is_dir($tmpdirname)) {
		rmdir_recursive($tmpdirname);
	}
}

if (isset($_POST['sidlist_dnload_all'])) {
	$save_date = date("Y-m-d-H-i-s");
	$file_name = "snort_sid_conf_files_{$save_date}.tar.gz";
	
	// Create a temporary directory to hold the lists as individual files
	$tmpdirname = "{$g['tmp_path']}/sidmods/";
	safe_mkdir("{$tmpdirname}");

	// Walk all saved lists and write them out to individual files
	foreach($a_list as $list) {
		file_put_contents($tmpdirname . $list['name'], base64_decode($list['content']));
		touch($tmpdirname . $list['name'], $list['modtime']);	
	}

	// Zip up all the files into a single tar gzip archive
	exec("cd {$tmpdirname} && /usr/bin/tar -czf {$g['tmp_path']}/{$file_name} *");

	if (file_exists("{$g['tmp_path']}/{$file_name}")) {
		ob_start(); //important or other posts will fail
		if (isset($_SERVER['HTTPS'])) {
			header('Pragma: ');
			header('Cache-Control: ');
		} else {
			header("Pragma: private");
			header("Cache-Control: private, must-revalidate");
		}
		header("Content-Type: application/octet-stream");
		header("Content-length: " . filesize("{$g['tmp_path']}/{$file_name}"));
		header("Content-disposition: attachment; filename = {$file_name}");
		ob_end_clean(); //important or other post will fail
		readfile("{$g['tmp_path']}/{$file_name}");
		// Remove all the temporary files and directory if created
		if (is_dir($tmpdirname)) {
			rmdir_recursive($tmpdirname);
			unlink_if_exists($g['tmp_path'] . "/" . $file_name);
		}
		exit;
	}
	else
		$savemsg = gettext("An error occurred while creating the gzip archive!");

	// Remove all the temporary files and directory if created
	if (is_dir($tmpdirname)) {
		rmdir_recursive($tmpdirname);
		unlink_if_exists($g['tmp_path'] . "/" . $file_name);
	}
}

// Get all the SID Mods Lists as an array
// Leave this as the last thing before spewing the page HTML
// so we can pick up any changes made in code above.
$sidmodlists = $config['installedpackages']['snortglobal']['sid_mgmt_lists']['item'];
$sidmodselections = Array();
$sidmodselections[] = "None";
foreach ($sidmodlists as $list) {
	$sidmodselections[] = $list['name'];
}

$pgtitle = array(gettext('Services'), gettext('Snort'), gettext('SID Management'));
include_once("head.inc");

/* Display Alert message, under form tag or no refresh */
if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg) {
	/* Display save message */
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), true, "/snort/snort_sid_mgmt.php");
$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);
?>

<form action="snort_sid_mgmt.php" method="post" enctype="multipart/form-data" name="iform" id="iform" class="form-horizontal">
	<input type="hidden" name="MAX_FILE_SIZE" value="100000000" />
	<input type="hidden" name="sidlist_id" id="sidlist_id" value=""/>

<?php
$section = new Form_Section('SID Management General Settings');
$group = new Form_Group('Enable Automatic SID State Management');
$group->add(new Form_Checkbox(
	'auto_manage_sids',
	'',
	'Enable automatic management of rule state and content using configuration lists. Default is Not Checked.',
	$pconfig['auto_manage_sids'] == 'on' ? true:false,
	'on'
))->setHelp('Snort will automatically enable/disable/modify text rules upon each update using criteria specified in SID Management Configuration lists.  ' . 
	'The supported configuration format is the same as that used in the PulledPork and Oinkmaster enablesid.conf, disablesid.conf and ' . 
	'modifysid.conf files.  You can either upload existing configurations to the firewall or create new ones using ADD below.');
$section->add($group);
print($section);
?>

<div id="sid-conf-rows">
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
										<i class="fa fa-pencil"></i>
									</a>

									<a name="sidlist_deleteX[]" id="sidlist_deleteX[]" type="button" title="<?=gettext('Delete this SID Mods List');?>"
										onClick='sidlistid="<?=$i;?>"' style="cursor: pointer;">
										<i class="fa fa-trash" title="<?=gettext('Delete this SID Mods List');?>"></i>
									</a>

									<a name="sidlist_dnloadX[]" id="sidlist_dnloadX[]" type="button" title="<?=gettext('Download this SID Mods List');?>"
										onClick='sidlistid="<?=$i;?>"' style="cursor: pointer;">
										<i class="fa fa-download" title="<?=gettext('Download this SID Mods List');?>"></i>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
							</tbody>
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
						<input type="hidden" name="listid" id="listid" value="<?=$sidmodlist_id;?>" />
						<?=gettext("List Name: ");?>
						<input type="text" size="45" class="form-control file" id="sidlist_name" name="sidlist_name" value="<?=$sidmodlist_name;?>" /><br />
						<button type="submit" class="btn btn-sm btn-primary" id="save" name="save" value="<?=gettext("Save");?>" title="<?=gettext("Save changes and close editor");?>">
							<i class="fa fa-save icon-embed-btn"></i>
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
			<i class="fa fa-plus icon-embed-btn"></i><?=gettext("Add")?>
		</button>

		<button data-toggle="modal" data-target="#uploader" role="button" aria-expanded="false" type="button" name="sidlist_import" id="sidlist_import" class="btn btn-info btn-sm" title="<?=gettext('Import/upload SID Mods List');?>">
			<i class="fa fa-upload icon-embed-btn"></i>
			<?=gettext("Import")?>
		</button>

		<button type="input" name="sidlist_dnload_all" id="sidlist_dnload_all" class="btn btn-info btn-sm" title="<?=gettext('Download all SID Mods Lists in a single gzip archive');?>">
			<i class="fa fa-download icon-embed-btn"></i>
			<?=gettext("Download")?>
		</button>

	</nav>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("SID Management List Interface Assignments")?></h2></div>
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
						<?php if ($natent['blockoffenders7'] == 'on' && $natent['ips_mode'] == 'ips_mode_inline') : ?>
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
							<input type="hidden" name="drop_sid_file[<?=$k?>]" id="drop_sid_file[<?=$k?>]" value="<?=isset($natent['drop_sid_file']) ? $natent['drop_sid_file'] : 'none';?>">
							<span class="text-center"><?=gettext("N/A")?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ($natent['blockoffenders7'] == 'on' && $natent['ips_mode'] == 'ips_mode_inline') : ?>
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
							<input type="hidden" name="reject_sid_file[<?=$k?>]" id="reject_sid_file[<?=$k?>]" value="<?=isset($natent['reject_sid_file']) ? $natent['reject_sid_file'] : 'none';?>">
							<span class="text-center"><?=gettext("N/A")?></span>
						<?php endif; ?>
					</td>


				</tr>
			   <?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
	<div>
		<button type="submit" id="save_auto_sid_conf" name="save_auto_sid_conf" class="btn btn-primary" value="<?=gettext("Save");?>" title="<?=gettext("Save SID Management configuration");?>" >
			<i class="fa fa-save icon-embed-btn"></i>
			<?=gettext("Save");?>
		</button>
		&nbsp;&nbsp;<?=gettext("Remember to save changes before exiting this page"); ?>
	</div>
</form>
</br />
	<div class="infoblock">
	<?php
		print_info_box(
			'<p>' .
				gettext("Check the box beside an interface to immediately apply new auto-SID management changes and signal Snort to live-load the new rules for the interface when clicking Save; " . 
					"otherwise only the new file assignments will be saved.") .
			'</p>' .
			'<p>' .
				gettext("SID State Order controls the order in which enable and disable state modifications are performed. An example would be to disable an entire category and later enable only a rule or two from it. " . 
					" In this case you would choose 'disable,enable' for the State Order.  Note that the last action performed takes priority.") .
			'</p>' .
			'<p>' .
				gettext("The Enable SID File, Disable SID File, Modify SID File, Drop SID File and Reject SID File drop-down controls specify which rule modification lists are run automatically for the interface.  Setting a list control to 'None' disables that modification. " . 
					"Setting all list controls for an interface to 'None' disables automatic SID state management for the interface.") .
			'</p>', 'info', false);

		// Finished with config array reference, so release it
		unset($a_nat);
	?>
	</div>

<script type="text/javascript">
//<![CDATA[
function enable_sid_conf() {
	var endis = !document.iform.auto_manage_sids.checked;
	if (endis) {
		document.getElementById("sid-conf-rows").style.display = "none";
	}
	else {
		document.getElementById("sid-conf-rows").style.display = "block";
	}
}

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

	// ---------- Click checkbox handlers -------------------------------------------------------
	// When 'auto_manage_sids' is clicked, disable/enable the other page form controls
	$('#auto_manage_sids').click(function() {
		enable_sid_conf();
	});

	enable_sid_conf();

	// If the user is editing a file, open the modal on page load
<?php if ($sidmodlist_edit_style == "show") : ?>
	$("#sidlist_editor").modal('show');
<?php endif ?>
});
//]]>
</script>

<?php
include("foot.inc"); ?>

