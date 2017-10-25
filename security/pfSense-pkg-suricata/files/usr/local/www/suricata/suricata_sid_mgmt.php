<?php
/*
 * suricata_sid_mgmt.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2016 Bill Meeks
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

global $g, $config, $rebuild_rules;

$suricatadir = SURICATADIR;
$pconfig = array();

// Grab saved settings from configuration
if (!is_array($config['installedpackages']['suricata']['rule']))
	$config['installedpackages']['suricata']['rule'] = array();
$a_nat = &$config['installedpackages']['suricata']['rule'];

$pconfig['auto_manage_sids'] = $config['installedpackages']['suricata']['config'][0]['auto_manage_sids'];

// Hard-code the path where SID Mods Lists are stored
// and disregard any user-supplied path element.
$sidmods_path = SURICATA_SID_MODS_PATH;

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
	 *          FALSE if List is not in use              *
	 *****************************************************/

	global $g, $config;

	if (!is_array($config['installedpackages']['suricata']['rule']))
		return FALSE;

	foreach ($config['installedpackages']['suricata']['rule'] as $rule) {
		if ($rule['enable_sid_file'] == $sidlist) {
			return TRUE;
		}
		if ($rule['disable_sid_file'] == $sidlist) {
			return TRUE;
		}
		if ($rule['modify_sid_file'] == $sidlist) {
			return TRUE;
		}
		if ($rule['drop_sid_file'] == $sidlist) {
			return TRUE;
		}
	}
	return FALSE;
}

if (isset($_POST['upload'])) {
	if ($_FILES["sidmods_fileup"]["error"] == UPLOAD_ERR_OK) {
		$tmp_name = $_FILES["sidmods_fileup"]["tmp_name"];
		$name = basename($_FILES["sidmods_fileup"]["name"]);
		move_uploaded_file($tmp_name, "{$sidmods_path}{$name}");
	}
	else
		$input_errors[] = gettext("Failed to upload file {$_FILES["sidmods_fileup"]["name"]}");
}

if (isset($_POST['sidlist_delete']) && isset($_POST['sidlist_fname'])) {
	if (!suricata_is_sidmodslist_active(basename($_POST['sidlist_fname'])))
		unlink_if_exists($sidmods_path . basename($_POST['sidlist_fname']));
	else
		$input_errors[] = gettext("This SID Mods List is currently assigned to an interface and cannot be deleted.");
}

if (isset($_POST['sidlist_edit']) && isset($_POST['sidlist_fname'])) {
	$file = $sidmods_path . basename($_POST['sidlist_fname']);
	$data = file_get_contents($file);
	if ($data !== FALSE) {
		$sidmodlist_data = htmlspecialchars($data);
		$sidmodlist_edit_style = "show";
		$sidmodlist_name = basename($_POST['sidlist_fname']);
		unset($data);
	}
	else {
		$input_errors[] = gettext("An error occurred reading the file.");
	}
}

if (isset($_POST['save']) && isset($_POST['sidlist_data'])) {
	if (strlen(basename($_POST['sidlist_name'])) > 0) {
		$file = $sidmods_path . basename($_POST['sidlist_name']);
		$data = str_replace("\r\n", "\n", $_POST['sidlist_data']);
		file_put_contents($file, $data);
		unset($data);
	}
	else {
		$input_errors[] = gettext("You must provide a valid filename for the SID Mods List.");
		$sidmodlist_edit_style = "display: table-row-group;";
	}
}

if (isset($_POST['save_auto_sid_conf'])) {
	$config['installedpackages']['suricata']['config'][0]['auto_manage_sids'] = $pconfig['auto_manage_sids'] ? "on" : "off";

	// Grab the SID Mods config for the interfaces from the form's controls array
	foreach ($_POST['sid_state_order'] as $k => $v) {
		$a_nat[$k]['sid_state_order'] = $v;
	}
	foreach ($_POST['enable_sid_file'] as $k => $v) {
		if ($v == "None") {
			unset($a_nat[$k]['enable_sid_file']);
			continue;
		}
		$a_nat[$k]['enable_sid_file'] = $v;
	}
	foreach ($_POST['disable_sid_file'] as $k => $v) {
		if ($v == "None") {
			unset($a_nat[$k]['disable_sid_file']);
			continue;
		}
		$a_nat[$k]['disable_sid_file'] = $v;
	}
	foreach ($_POST['modify_sid_file'] as $k => $v) {
		if ($v == "None") {
			unset($a_nat[$k]['modify_sid_file']);
			continue;
		}
		$a_nat[$k]['modify_sid_file'] = $v;
	}

	foreach ($_POST['drop_sid_file'] as $k => $v) {
		if ($v == "None") {
			unset($a_nat[$k]['drop_sid_file']);
			continue;
		}
		$a_nat[$k]['drop_sid_file'] = $v;
	}

	// Write the new configuration
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

if (isset($_POST['sidlist_dnload']) && isset($_POST['sidlist_fname'])) {
	$file = $sidmods_path . basename($_POST['sidlist_fname']);
	if (file_exists($file)) {
		ob_start(); //important or other posts will fail
		session_cache_limiter('nocache');
		header("Content-Type: application/octet-stream");
		header("Content-Length: " . filesize($file));
		header("Content-Disposition: attachment; filename = " . basename($file));
		ob_end_clean(); //important or other post will fail
		readfile($file);
		exit();
	}
	else
		$savemsg = gettext("Unable to locate the file specified!");
}

if (isset($_POST['sidlist_dnload_all'])) {
	$save_date = date("Y-m-d-H-i-s");
	$file_name = "suricata_sid_conf_files_{$save_date}.tar.gz";
	exec("/usr/bin/tar -czf /tmp/{$file_name} --strip-components 5 {$sidmods_path}/*");

	if (file_exists("/tmp/{$file_name}")) {
		ob_start(); //important or other posts will fail
		session_cache_limiter('nocache');
		header("Content-Type: application/octet-stream");
		header("Content-Length: " . filesize("/tmp/{$file_name}"));
		header("Content-Disposition: attachment; filename = {$file_name}");
		ob_end_clean(); //important or other post will fail
		readfile("/tmp/{$file_name}");

		// Clean up the temp file
		unlink_if_exists("/tmp/{$file_name}");
		exit();
	}
	else
		$savemsg = gettext("An error occurred while creating the gzip archive!");
}

// Get all files in the SID Mods Lists sub-directory as an array
// Leave this as the last thing before spewing the page HTML
// so we can pick up any changes made to files in code above.
$sidmodfiles = return_dir_as_array($sidmods_path);
$sidmodselections = array_merge(Array( "None" ), $sidmodfiles);

$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("SID Management"));
include_once("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), true, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

if ($g['platform'] == "nanobsd") {
	$input_errors[] = gettext("SID auto-management is not supported on NanoBSD installs");
}

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
	<input type="hidden" name="sidlist_fname" id="sidlist_fname" value=""/>

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
						<?=gettext("Enable automatic management of rule state and content using configuration files.  Default is Not Checked.")?>
					</label>
					<span class="help-block">
						<?=gettext("When checked, Suricata will automatically enable/disable/modify text rules upon each update using criteria ") . 
						gettext("specified in configuration files.  The supported configuration file format is the same as that used ") . 
						gettext("by PulledPork and Oinkmaster.  See the included sample conf files for usage examples.  ") . 
						gettext("Either upload existing files to the firewall or create new ones by clicking ADD below."); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("SID Management Configuration Files")?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<tbody>
				<tr>
					<td>
						<table class="table table-striped table-hover table-condensed">
							<thead>
								<tr>
									<th><?=gettext("SID Mods List File Name"); ?></th>
									<th><?=gettext("Last Modified Time"); ?></th>
									<th><?=gettext("File Size"); ?></th>
									<th><?=gettext("Actions")?>
									</th>
								</tr>
							</thead>
							<tbody>
						<?php foreach ($sidmodfiles as $file): ?>
							<tr>
								<td><?=gettext($file); ?></td>
								<td><?=date('M-d Y g:i a', filemtime("{$sidmods_path}{$file}")); ?></td>
								<td><?=format_bytes(filesize("{$sidmods_path}{$file}")); ?> </td>

								<td>
									<a name="sidlist_editX[]" id="sidlist_editX[]" type="button" title="<?=gettext('Edit this SID Mods List');?>"
										onClick='sidfilename="<?=$file;?>"' style="cursor: pointer;">
										<i class="fa fa-pencil"></i>
									</a>

									<a name="sidlist_deleteX[]" id="sidlist_deleteX[]" type="button" title="<?=gettext('Delete this SID Mods List');?>"
										onClick='sidfilename="<?=$file;?>"' style="cursor: pointer;" text="delete this">
										<i class="fa fa-trash" title="<?=gettext('Delete this SID Mods List');?>"></i>
									</a>

									<a name="sidlist_dnloadX[]" id="sidlist_dnloadX[]" type="button" title="<?=gettext('Download this SID Mods List');?>"
										onClick='sidfilename="<?=$file;?>"' style="cursor: pointer;">
										<i class="fa fa-download" title="<?=gettext('Download this SID Mods List');?>"></i>
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

						<h3 class="modal-title" id="myModalLabel"><?=gettext("SID Upload")?></h3>
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

						<h3 class="modal-title" id="myModalLabel"><?=gettext("SID Auto-Management File Editor")?></h3>
					</div>

					<div class="modal-body">
						<?=gettext("File Name: ");?>
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

						<?php echo gettext("Note:"); ?></strong>
						<br/><?php echo gettext("SID Mods Lists are stored as local files on the firewall and their contents are " .
						"not saved as part of the firewall configuration file."); ?>
					</div>
				</div>
			</div>
		</div>
	</div>

	<nav class="action-buttons">

		<button data-toggle="modal" data-target="#sidlist_editor" role="button" aria-expanded="false" type="button" name="sidlist_new" id="sidlist_new" class="btn btn-success btn-sm" title="<?=gettext('Create a new SID Mods List');?>"
		onClick="document.getElementById('sidlist_data').value=''; document.getElementById('sidlist_name').value=''; document.getElementById('sidlist_editor').style.display='table-row-group'; document.getElementById('sidlist_name').focus();">
			<i class="fa fa-plus icon-embed-btn"></i><?=gettext("Add")?>
		</button>

		<button data-toggle="modal" data-target="#uploader" role="button" aria-expanded="false" type="button" name="sidlist_import" id="sidlist_import" class="btn btn-info btn-sm" title="<?=gettext('Import/upload SID Mods List');?>">
			<i class="fa fa-upload icon-embed-btn"></i>
			<?=gettext("Import")?>
		</button>

		<button type="input" name="sidlist_dnload_all" id="sidlist_dnload_all" class="btn btn-info btn-sm" title="<?=gettext('Download all SID Mods List files in a single gzip archive');?>">
			<i class="fa fa-download icon-embed-btn"></i>
			<?=gettext("Download")?>
		</button>

	</nav>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Interface SID Management File Assignments")?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
				   <tr>
					<th><?=gettext("Rebuild")?></th>
					<th><?=gettext("Interface")?></th>
					<th><?=gettext("SID State Order")?></th>
					<th><?=gettext("Enable SID File")?></th>
					<th><?=gettext("Disable SID File")?></th>
					<th><?=gettext("Modify SID File")?></th>
					<th><?=gettext("Drop SID File")?></th>
				   </tr>
				</thead>
				<tbody>
			   <?php foreach ($a_nat as $k => $natent): ?>
				<tr>
					<td>
						<input type="checkbox" name="torestart[]" id="torestart[]" value="<?=$k;?>" title="<?=gettext("Apply new configuration and rebuild rules for this interface when saving");?>" />
					</td>
					<td class="listbg"><?=convert_friendly_interface_to_friendly_descr($natent['interface']); ?></td>
					<td class="listr" align="center">
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
					</td>
				</tr>
			   <?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</div>

		<button type="submit" id="save_auto_sid_conf" name="save_auto_sid_conf" class="btn btn-primary" value="<?=gettext("Save");?>" title="<?=gettext("Save SID Management configuration");?>" >
			<i class="fa fa-save icon-embed-btn"></i>
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
			gettext("The Enable SID File, Disable SID File, Modify SID File and Drop SID File drop-down controls specify which rule modification files are run automatically for the interface.  Setting a file control to 'None' disables that modification. " . 
				"Setting all file controls for an interface to 'None' disables automatic SID state management for the interface.") .
		'</p>', 'info', false);
?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	sidfilename = "";

	$('[id^=sidlist_editX]').click(function () {
		$('#sidlist_fname').val(sidfilename);
		$('<input type="hidden" name="sidlist_edit[]" id="sidlist_edit[]" value="0"/>').appendTo($(form));
		$(form).submit();
	});

	$('[id^=sidlist_deleteX]').click(function () {
		$('#sidlist_fname').val(sidfilename);
		$('<input type="hidden" name="sidlist_delete[]" id="sidlist_edit[]" value="0"/>').appendTo($(form));
		$(form).submit();
	});

	$('[id^=sidlist_dnloadX]').click(function () {
		$('#sidlist_fname').val(sidfilename);
		$('<input type="hidden" name="sidlist_dnload[]" id="sidlist_edit[]" value="0"/>').appendTo($(form));
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

