<?php
/*
 * suricata_sid_mgmt.php
 *
 * Portions of this code are based on original work done for the
 * Snort package for pfSense from the following contributors:
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
		$sidmodlist_edit_style = "display: table-row-group;";
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

	// Write the new configuration
	write_config("Suricata pkg: updated automatic SID management settings.");

	$intf_msg = "";

	// If any interfaces were marked for restart, then do it
	if (is_array($_POST['torestart'])) {
		foreach ($_POST['torestart'] as $k) {
			// Update the suricata.yaml file and
			// rebuild rules for this interface.
			$rebuild_rules = true;
			conf_mount_rw();
			suricata_generate_yaml($a_nat[$k]);
			conf_mount_ro();
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
		ob_end_clean(); //important or other post will fail
		readfile($file);
	}
	else
		$savemsg = gettext("Unable to locate the file specified!");
}

if (isset($_POST['sidlist_dnload_all_x'])) {
	$save_date = date("Y-m-d-H-i-s");
	$file_name = "suricata_sid_conf_files_{$save_date}.tar.gz";
	exec("cd {$sidmods_path} && /usr/bin/tar -czf /tmp/{$file_name} *");

	if (file_exists("/tmp/{$file_name}")) {
		ob_start(); //important or other posts will fail
		if (isset($_SERVER['HTTPS'])) {
			header('Pragma: ');
			header('Cache-Control: ');
		} else {
			header("Pragma: private");
			header("Cache-Control: private, must-revalidate");
		}
		header("Content-Type: application/octet-stream");
		header("Content-length: " . filesize("/tmp/{$file_name}"));
		header("Content-disposition: attachment; filename = {$file_name}");
		ob_end_clean(); //important or other post will fail
		readfile("/tmp/{$file_name}");

		// Clean up the temp file
		unlink_if_exists("/tmp/{$file_name}");
	}
	else
		$savemsg = gettext("An error occurred while creating the gzip archive!");
}

// Get all files in the SID Mods Lists sub-directory as an array
// Leave this as the last thing before spewing the page HTML
// so we can pick up any changes made to files in code above.
$sidmodfiles = return_dir_as_array($sidmods_path);
$sidmodselections = array_merge(Array( "None" ), $sidmodfiles);

$pgtitle = gettext("Suricata: SID Management");
include_once("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000">

<?php
include_once("fbegin.inc");

/* Display Alert message, under form tag or no refresh */
if ($input_errors)
	print_input_errors($input_errors);
?>

<form action="suricata_sid_mgmt.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<input type="hidden" name="MAX_FILE_SIZE" value="100000000" />
<input type="hidden" name="sidlist_fname" id="sidlist_fname" value=""/>

<?php
if ($savemsg) {
	/* Display save message */
	print_info_box($savemsg);
}
?>

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
		$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
		$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php");
		$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
		$tab_array[] = array(gettext("SID Mgmt"), true, "/suricata/suricata_sid_mgmt.php");
		$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
		$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
        	display_top_tabs($tab_array, true);
	?>
	</td></tr>
	<tr><td>
		<div id="mainarea">
		<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
			<tbody>
		<?php if ($g['platform'] == "nanobsd") : ?>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("SID auto-management is not supported on NanoBSD installs"); ?></td>
			</tr>
		<?php else: ?>
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?php echo gettext("General Settings"); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable Automatic SID State Management"); ?></td>
				<td width="78%" class="vtable"><input type="checkbox" id="auto_manage_sids" name="auto_manage_sids" value="on" 
				<?php if ($pconfig['auto_manage_sids'] == 'on') echo " checked"; ?> onclick="enable_sid_conf();" />&nbsp;<?=gettext("Enable automatic management of rule state ") . 
				gettext("and content using configuration files.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>";?>.<br/><br/>
				<?=gettext("Suricata will automatically enable/disable/modify text rules upon each update using criteria specified in configuration files.  ") . 
				gettext("The supported configuration file format is the same as that used in the PulledPork and Oinkmaster enablesid.conf, disablesid.conf and ") . 
				gettext("modifysid.conf files.  You can either upload existing files or create your own."); ?>
				</td>
			</tr>
			</tbody>
			<tbody id="sid_conf_rows">
			<tr>
				<td  colspan="2" valign="top" class="listtopic"><?php echo gettext("SID Management Configuration Files"); ?></td>
			</tr>
			<tr>
				<td colspan="2" class="vtable" align="center" >
					<table width="100%" border="0" cellpadding="4" cellspacing="0">
						<tbody id="uploader" style="display: none;">
						<tr>
							<td class="list"><br/><?php echo gettext("Click BROWSE to select a file to import, and then click UPLOAD.  Click CLOSE to quit."); ?></td>
						</tr>
						<tr>
							<td class="list"><input type="file" name="sidmods_fileup" id="sidmods_fileup" class="formfld file" size="50" />
								&nbsp;&nbsp;<input type="submit" name="upload" id="upload" value="<?=gettext("Upload");?>" 
								title="<?=gettext("Upload selected SID mods list to firewall");?>"/>&nbsp;&nbsp;<input type="button" 
								value="<?=gettext("Close");?>" onClick="document.getElementById('uploader').style.display='none';" /><br/></td>
							<td class="list"></td>
						</tr>
						</tbody>
						<tbody>
						<tr>
							<td>
								<table id="maintable" width="100%" border="0" cellpadding="4" cellspacing="0">
									<colgroup>
										<col style="width: 45%;">
										<col style="width: 25%;">
										<col style="width: 15%;">
										<col style="width: 15%;">
									</colgroup>
									<thead>
										<tr>
											<th class="listhdrr"><?php echo gettext("SID Mods List File Name"); ?></th>
											<th class="listhdrr"><?php echo gettext("Last Modified Time"); ?></th>
											<th class="listhdrr"><?php echo gettext("File Size"); ?></th>
											<th class="list" align="left"><img style="cursor:pointer;" name="sidlist_new" id="sidlist_new" 
											src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" 
											height="17" border="0" title="<?php echo gettext('Create a new SID Mods List');?>" 
											onClick="document.getElementById('sidlist_data').value=''; document.getElementById('sidlist_name').value=''; document.getElementById('sidlist_editor').style.display='table-row-group'; document.getElementById('sidlist_name').focus();" />
											<img style="cursor:pointer;" name="sidlist_import" id="sidlist_import" 
											onClick="document.getElementById('uploader').style.display='table-row-group';" 
											src="../themes/<?= $g['theme']; ?>/images/icons/icon_import_alias.gif" width="17" 
											height="17" border="0" title="<?php echo gettext('Import/Upload a SID Mods List');?>"/>
											<input type="image" name="sidlist_dnload_all" id="sidlist_dnload_all" 
											src="../tree/page-file_play.gif" width="16" height="16" border="0" 
											title="<?php echo gettext('Download all SID Mods List files in a single gzip archive');?>"/>
											</th>
										</tr>
									</thead>
									<tbody>
								<?php foreach ($sidmodfiles as $file): ?>
									<tr>
										<td class="listr"><?php echo gettext($file); ?></td>
										<td class="listr"><?=date('M-d Y g:i a', filemtime("{$sidmods_path}{$file}")); ?></td>
										<td class="listr"><?=format_bytes(filesize("{$sidmods_path}{$file}")); ?> </td>
										<td class="list"><input type="image" name="sidlist_edit[]" id="sidlist_edit[]" 
										onClick="document.getElementById('sidlist_fname').value='<?=$file;?>';" 
										src="../themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" 
										height="17" border="0" title="<?php echo gettext('Edit this SID Mods List');?>"/>
										<input type="image" name="sidlist_delete[]" id="sidlist_delete[]" 
										onClick="document.getElementById('sidlist_fname').value='<?=$file;?>'; 
										return confirm('<?=gettext("Are you sure you want to permanently delete this file?  Click OK to continue or CANCEL to quit.");?>');" 
										src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" 
										height="17" border="0" title="<?php echo gettext('Delete this SID Mods List');?>"/>
										<input type="image" name="sidlist_dnload[]" id="sidlist_dnload[]" 
										onClick="document.getElementById('sidlist_fname').value='<?=$file;?>';" 
										src="../tree/page-file_play.gif" width="16" height="16" border="0" 
										title="<?php echo gettext('Download this SID Mods List file');?>"/>
										</td>
									</tr>
								<?php endforeach; ?>
									</tbody>
									<tbody id="sidlist_editor" style="<?=$sidmodlist_edit_style;?>">
									<tr>
										<td colspan="4">&nbsp;</td>
									</tr>
									<tr>
										<td colspan="4"><strong><?=gettext("File Name: ");?></strong><input type="text" size="45" class="formfld file" id="sidlist_name" name="sidlist_name" value="<?=$sidmodlist_name;?>" />
										&nbsp;&nbsp;<input type="submit" id="save" name="save" value="<?=gettext(" Save ");?>" title="<?=gettext("Save changes and close editor");?>" />
										&nbsp;&nbsp;<input type="button" id="cancel" name="cancel" value="<?=gettext("Cancel");?>" onClick="document.getElementById('sidlist_editor').style.display='none';"  
										title="<?=gettext("Abandon changes and quit editor");?>" /></td>
									</tr>
									<tr>
										<td colspan="4">&nbsp;</td>
									</tr>
									<tr>
										<td colspan="4"><textarea wrap="off" cols="80" rows="20" name="sidlist_data" id="sidlist_data" 
										style="width:95%; height:100%;"><?=$sidmodlist_data;?></textarea>
										</td>
									</tr>
									</tbody>
									<tbody>
									<tr>
										<td colspan="3" class="vexpl"><br/><span class="red"><strong><?php echo gettext("Note:"); ?></strong></span>
										<br/><?php echo gettext("SID Mods Lists are stored as local files on the firewall and their contents are " . 
										"not saved as part of the firewall configuration file."); ?></td>
										<td class="list"></td>
									</tr>
									<tr>
										<td colspan="3" class="vexpl"><br/><strong><?php echo gettext("File List Controls:"); ?></strong><br/><br/>
										&nbsp;&nbsp;<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" />
										&nbsp;<?=gettext("Opens the editor window to create a new SID Mods List.  You must provide a valid filename before saving.");?><br/>
										&nbsp;&nbsp;<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_import_alias.gif" width="17" height="17" border="0" />
										&nbsp;<?=gettext("Opens the file upload control for uploading a new SID Mods List from your local machine.");?><br/>
										&nbsp;&nbsp;<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" />
										&nbsp;<?=gettext("Opens the SID Mods List in a text edit control for viewing or editing its contents.");?><br/>
										&nbsp;&nbsp;<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" />
										&nbsp;<?=gettext("Deletes the SID Mods List from the file system after confirmation.");?><br/>
										&nbsp;&nbsp;<img src="../tree/page-file_play.gif" width="16" height="16" border="0" />
										&nbsp;<?=gettext("Downloads the SID Mods List file to your local machine.");?><br/>
										</td>
										<td class="list"></td>
									</tr>
									</tbody>
								</table>
							</td>
						</tr>
						</tbody>
					</table>
				</td>
			</tr>
			<tr>
				<td  colspan="2" valign="top" class="listtopic"><?php echo gettext("Interface SID Management File Assignments"); ?></td>
			</tr>
			<tr>
				<td colspan="2" class="vtable" align="center" >
					<table width="100%" border="0" cellpadding="2" cellspacing="0">
						<tbody>
						<tr>
							<td>
								<table width="100%" border="0" cellpadding="0" cellspacing="0">
									<colgroup>
										<col width="4%" align="center">
										<col width="20" align="center">
										<col width="16%" align="center">
										<col width="20%" align="center">
										<col width="20%" align="center">
										<col width="20%" align="center">
									</colgroup>
									<thead>
									   <tr>
										<th class="listhdrr"><?=gettext("Rebuild"); ?></th>
										<th class="listhdrr"><?=gettext("Interface"); ?></th>
										<th class="listhdrr"><?=gettext("SID State Order"); ?></th>
										<th class="listhdrr"><?=gettext("Enable SID File"); ?></th>
										<th class="listhdrr"><?=gettext("Disable SID File"); ?></th>
										<th class="listhdrr"><?=gettext("Modify SID File"); ?></th>
									   </tr>
									</thead>
									<tbody>
								   <?php foreach ($a_nat as $k => $natent): ?>
									<tr>
										<td class="listr" align="center">
											<input type="checkbox" name="torestart[]" id="torestart[]" value="<?=$k;?>" title="<?=gettext("Apply new configuration and rebuild rules for this interface when saving");?>" />
										</td>
										<td class="listbg"><?=convert_friendly_interface_to_friendly_descr($natent['interface']); ?></td>
										<td class="listr" align="center">
											<select name="sid_state_order[<?=$k?>]" class="formselect" id="sid_state_order[<?=$k?>]">
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
										<td class="listr" align="center">
											<select name="enable_sid_file[<?=$k?>]" class="formselect" id="enable_sid_file[<?=$k?>]">
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
										<td class="listr" align="center">
											<select name="disable_sid_file[<?=$k?>]" class="formselect" id="disable_sid_file[<?=$k?>]">
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
										<td class="listr" align="center">
											<select name="modify_sid_file[<?=$k?>]" class="formselect" id="modify_sid_file[<?=$k?>]">
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
							</td>
						</tr>
						<tr>
							<td class="vexpl">&nbsp;
							</td>
						</tr>
						<tr>
							<td>
								<table width="100%" cellpadding="2" cellspacing="2" border="0">
									<tbody>
									<tr>
										<td colspan="2" class="vexpl" style="text-align: bottom;"><strong><span class="red"><?=gettext("Notes:");?></span></strong></td>
									</tr>
									<tr>
										<td class="vexpl" style="vertical-align: top;"><?=gettext("1.");?></td>
										<td class="vexpl"><?=gettext("Check the box beside an interface to immediately apply new auto-SID management ") . 
										gettext("changes and signal Suricata to live-load the new rules for the interface when clicking SAVE; ") . 
										gettext("otherwise only the new file assignments will be saved.");?>
										</td>
									</tr>
									<tr>
										<td class="vexpl" style="vertical-align: top;"><?=gettext("2.");?></td>
										<td class="vexpl"><?=gettext("SID State Order controls the order in which enable and disable state modifications are performed.  ") . 
										gettext("An example would be to disable an entire category and later enable only a rule or two from it.  In this case you would ") . 
										gettext("choose 'disable,enable' for the State Order.  Note that the last action performed takes priority.");?>
										</td>
									</tr>
									<tr>
										<td class="vexpl" style="vertical-align: top;"><?=gettext("3.");?></td>
										<td class="vexpl"><?=gettext("The Enable SID File, Disable SID File and Modify SID File controls specify which rule modification ") . 
										gettext("files are run automatically for the interface.  Setting a file control to 'None' disables that modification.  ") . 
										gettext("Setting all file controls for an interface to 'None' disables automatic SID state management for the interface.");?>
										</td>
									</tr>
									</tbody>
								</table>
							</td>
						</tr>
						</tbody>
					</table>
				</td>
			</tr>
			</tbody>
			<tbody>
			<tr>
				<td colspan="2" class="vexpl" align="center"><input type="submit" id="save_auto_sid_conf" name="save_auto_sid_conf" class="formbtn" value="<?=gettext("Save");?>" title="<?=gettext("Save SID Management configuration");?>" />
				&nbsp;&nbsp;<?=gettext("Remember to save changes before exiting this page"); ?>
				</td>
			</tr>
		<?php endif; ?>
			</tbody>
		</table>
		</div>
	</td></tr>
	</tbody>
</table>
</form>


<?php include("fend.inc"); ?>

<?php if ($g['platform'] != "nanobsd") : ?>
<script type="text/javascript">

function enable_sid_conf() {
	var endis = !document.iform.auto_manage_sids.checked;
	if (endis) {
		document.getElementById("sid_conf_rows").style.display = "none";
	}
	else {
		document.getElementById("sid_conf_rows").style.display = "";
	}
}

enable_sid_conf();

</script>
<?php endif; ?>

</body>
</html>
