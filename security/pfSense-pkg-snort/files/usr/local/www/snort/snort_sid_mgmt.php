<?php
/*
 * snort_sid_mgmt.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2017 Bill Meeks
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

$pconfig['auto_manage_sids'] = $config['installedpackages']['snortglobal']['auto_manage_sids'];

// Hard-code the path where SID Mods Lists are stored
// and disregard any user-supplied path element.
$sidmods_path = SNORT_SID_MODS_PATH;

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

if (isset($_POST['sidlist_action']) && isset($_POST['sidlist_fname'])) {
	switch ($_POST['sidlist_action']) {
		case 'delete':
			if (!snort_is_sidmodslist_active(basename($_POST['sidlist_fname'])))
				unlink_if_exists($sidmods_path . basename($_POST['sidlist_fname']));
			else
				$input_errors[] = gettext("This SID Mods List is currently assigned to an interface and cannot be deleted.");
			break;

		case 'edit':
			$file = $sidmods_path . basename($_POST['sidlist_fname']);
			$data = file_get_contents($file);
			if ($data !== FALSE) {
				$sidmodlist_data = htmlspecialchars($data);
				$sidmodlist_edit_style = "display: inline;";
				$sidmodlist_name = basename($_POST['sidlist_fname']);
				unset($data);
			}
			else {
				$input_errors[] = gettext("An error occurred reading the file.");
			}
			break;

		case 'download':
			$file = $sidmods_path . basename($_POST['sidlist_fname']);
			if (file_exists($file)) {
				ob_start(); //important or other posts will fail
				if (isset($_SERVER['HTTPS'])) {
					header('Pragma: ');
					header('Cache-Control: ');
				} else {
					header("Pragma: private");
					header("Cache-Control: private, must-revalidate");
				}
				header("Content-Type: application/octet-stream");
				header("Content-length: filesize=" . filesize($file));
				header("Content-disposition: attachment; filename=" . basename($file));
				ob_end_clean(); //important or other post will fail
				readfile($file);
				exit;
			}
			else
				$savemsg = gettext("Unable to locate the file specified!");

		default:
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
	$config['installedpackages']['snortglobal']['auto_manage_sids'] = $pconfig['auto_manage_sids'] ? "on" : "off";

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

	// Write the new configuration
	write_config("Snort pkg: updated automatic SID management settings.");

	$intf_msg = "";

	// If any interfaces were marked for restart, then do it
	if (is_array($_POST['torestart'])) {
		foreach ($_POST['torestart'] as $k) {
			// Update the snort.conf file and
			// rebuild rules for this interface.
			$rebuild_rules = true;
			conf_mount_rw();
			snort_generate_conf($a_nat[$k]);
			conf_mount_ro();
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

if (isset($_POST['sidlist_dnload_all'])) {
	$save_date = date("Y-m-d-H-i-s");
	$file_name = "snort_sid_conf_files_{$save_date}.tar.gz";
	exec("cd {$sidmods_path} && /usr/bin/tar -czf {$g['tmp_path']}/{$file_name} *");

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
		header("Content-length: filesize=" . filesize("{$g['tmp_path']}/{$file_name}"));
		header("Content-disposition: attachment; filename=" . $file_name);
		ob_end_clean(); //important or other post will fail
		readfile("{$g['tmp_path']}/{$file_name}");

		// Clean up the temp file
		unlink_if_exists("{$g['tmp_path']}/{$file_name}");
		exit;
	}
	else
		$savemsg = gettext("An error occurred while creating the gzip archive!");
}

// Get all files in the SID Mods Lists sub-directory as an array
// Leave this as the last thing before spewing the page HTML
// so we can pick up any changes made to files in code above.
$sidmodfiles = return_dir_as_array($sidmods_path);
$sidmodselections = array_merge(Array( "None" ), $sidmodfiles);

$pgtitle = array(gettext('Services'), gettext('Snort'), gettext('SID Management'));
include_once("head.inc");

/* Display Alert message, under form tag or no refresh */
if ($input_errors)
	print_input_errors($input_errors);
?>

<form action="snort_sid_mgmt.php" method="post" enctype="multipart/form-data" name="iform" id="iform" class="form-horizontal">
<input type="hidden" name="MAX_FILE_SIZE" value="100000000" />
<input type="hidden" name="sidlist_fname" id="sidlist_fname" value=""/>
<input type="hidden" name="sidlist_action" id="sidlist_action" value=""/>

<?php
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

$section = new Form_Section('SID Management General Settings');
if ($g['platform'] == "nanobsd") {
	$section->addInput(new Form_StaticText(
		null,
		'SID auto-management is not supported on NanoBSD installs.'
	))->addClass('text-danger');
}
else {
	$group = new Form_Group('Enable Automatic SID State Management');
	$group->add(new Form_Checkbox(
		'auto_manage_sids',
		'',
		'Enable automatic management of rule state and content using configuration files. Default is Not Checked.',
		$pconfig['auto_manage_sids'] == 'on' ? true:false,
		'on'
	))->setHelp('Snort will automatically enable/disable/modify text rules upon each update using criteria specified in configuration files.  ' . 
		'The supported configuration file format is the same as that used in the PulledPork and Oinkmaster enablesid.conf, disablesid.conf and ' . 
		'modifysid.conf files.  You can either upload existing configuration files or create your own.');
	$section->add($group);
}
print($section);
?>

<div id="sid-conf-rows">
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('SID Management Configuration Files'); ?></h2></div>
	<div class="panel-body">
		<div id="uploader" class="row col-sm-12" style="display: none;">
			<div class="col-sm-8">
				<br/><?=gettext('Click BROWSE to select a file to import, and then click UPLOAD.  Click CLOSE to quit.'); ?>
			</div>
			<div class="col-sm-8">
				<div class="form-group">
					<input type="file" name="sidmods_fileup" id="sidmods_fileup" class="form-control" />
					<button type="submit" class="btn btn-info btn-sm" name="upload" id="upload" title="<?=gettext('Upload selected SID mods list to firewall');?>">
						<i class="fa fa-upload icon-embed-btn"></i>
						<?=gettext('Upload');?>
					</button>
					<button type="button" class="btn btn-default btn-sm btn-warning" onClick="document.getElementById('uploader').style.display='none';" >
						<?=gettext('Close');?>
					</button>
				</div>
			</div>
		</div>

		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext('SID Mods List File Name'); ?></th>
						<th><?=gettext('Last Modified Time'); ?></th>
						<th><?=gettext('File Size'); ?></th>
						<th><?=gettext('Actions'); ?></th>
					</tr>
				</thead>
				<tbody>
			<?php foreach ($sidmodfiles as $file): ?>
					<tr>
						<td><?=gettext($file); ?></td>
						<td><?=date('M-d Y g:i a', filemtime("{$sidmods_path}{$file}")); ?></td>
						<td><?=format_bytes(filesize("{$sidmods_path}{$file}")); ?> </td>
						<td>
							<a href="#" class="fa fa-pencil icon-primary" onclick="javascript:snort_sidlist_action('edit', '<?=$file; ?>');" title="<?=gettext('Edit this SID Mods List');?>"></a>
							<a href="#" class="fa fa-trash icon-primary no-confirm" onclick="javascript:snort_sidlist_action('delete', '<?=$file; ?>');" title="<?=gettext('Delete this SID Mods List');?>"></a>
							<a href="#" class="fa fa-download icon-primary" onclick="javascript:snort_sidlist_action('download', '<?=$file; ?>');" title="<?=gettext('Download this SID Mods List file');?>"></a>
						</td>
					</tr>
			<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<nav class="action-buttons">
			<button type="button" class="btn btn-success btn-sm" title="<?=gettext('Create new SID Mods List');?>" 
				onclick="document.getElementById('sidlist_data').value=''; document.getElementById('sidlist_name').value=''; document.getElementById('sidlist_editor').style.display='block'; document.getElementById('sidlist_name').focus();">
				<i class="fa fa-plus icon-embed-btn"></i>
				<?=gettext('Add');?>
			</button>
			<button type="button" class="btn btn-info btn-sm" title="<?=gettext('Upload a SID Mods List file');?>" onclick="document.getElementById('uploader').style.display='block';">
				<i class="fa fa-upload icon-embed-btn"></i>
				<?=gettext('Upload');?>
			</button>
			<button type="submit" class="btn btn-info btn-sm" id="sidlist_dnload_all" name="sidlist_dnload_all" title="<?=gettext('Download all SID Mods List files as gzip archive');?>">
				<i class="fa fa-download icon-embed-btn"></i>
				<?=gettext('Download');?>
			</button>
		</nav>

		<div id="sidlist_editor" class="row col-sm-12" style="<?=$sidmodlist_edit_style;?>">
			<div class="col-sm-9">
				<div class="input-group">
					<div class="input-group-addon"><strong><?=gettext('File Name: ');?></strong></div>
					<input type="text" class="form-control" id="sidlist_name" name="sidlist_name" value="<?=gettext($sidmodlist_name);?>" />
				</div>
			</div>
			<div class="col-sm-9">
				<button type="submit" id="save" name="save" class="btn btn-primary btn-sm" title="<?=gettext('Save changes and close editor');?>">
					<i class="fa fa-save icon-embed-btn"></i>
					<?=gettext('Save'); ?>
				</button>
				<button type="button" class="btn btn-default btn-sm btn-warning" id="cancel" name="cancel" onClick="document.getElementById('sidlist_editor').style.display='none';" 
					title="<?=gettext('Abandon changes and quit editor');?>">
					<?=gettext('Cancel');?>
				</button>
			</div>
			<div class="col-sm-9">
				<textarea wrap="off" cols="" rows="12" name="sidlist_data" id="sidlist_data" class="form-control"><?=$sidmodlist_data;?></textarea>
			</div>
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?php echo gettext("Interface SID Management File Assignments"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
				   <tr>
					<th><?=gettext('Rebuild'); ?></th>
					<th><?=gettext('Interface'); ?></th>
					<th><?=gettext('SID State Order'); ?></th>
					<th><?=gettext('Enable SID File'); ?></th>
					<th><?=gettext('Disable SID File'); ?></th>
					<th><?=gettext('Modify SID File'); ?></th>
				   </tr>
				</thead>
				<tbody>
				   <?php foreach ($a_nat as $k => $natent): ?>
					<tr>
						<td>
							<input type="checkbox" name="torestart[]" id="torestart[]" value="<?=$k;?>" title="<?=gettext('Apply new configuration and rebuild rules for this interface when saving');?>" />
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
					</tr>
				   <?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
</div>
<div class="col-sm-10 col-sm-offset-2">
	<button type="submit" id="save_auto_sid_conf" name="save_auto_sid_conf" class="btn btn-primary btn-sm" title="<?=gettext('Save SID Management configuration');?>">
		<i class="fa fa-save icon-embed-btn"></i>
		<?=gettext('Save');?>
	</button>
</div>

</form>

<?php if ($g['platform'] != "nanobsd") : ?>
<script type="text/javascript">
//<![CDATA[

	function snort_sidlist_action(action,list) {
		$('#sidlist_action').val(action);
		$('#sidlist_fname').val(list);
		if (action == 'delete') {
			if (confirm('Are you sure you want to delete this SID Mods List?'))
				$('#iform').submit();
		}
		else {
			$('#iform').submit();
		}
	}

	function enable_sid_conf() {
		var endis = !document.iform.auto_manage_sids.checked;
		if (endis) {
			document.getElementById("sid-conf-rows").style.display = "none";
		}
		else {
			document.getElementById("sid-conf-rows").style.display = "block";
		}
	}

events.push(function(){

	// ---------- Click checkbox handlers -------------------------------------------------------
	// When 'auto_manage_sids' is clicked, disable/enable the other page form controls
	$('#auto_manage_sids').click(function() {
		enable_sid_conf();
	});

	enable_sid_conf();

});
//]]>
</script>
<?php endif; ?>

<?php
include("foot.inc");
?>

