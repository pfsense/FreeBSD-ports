<?php
/*
 * Copyright (C) 2004 Scott Ullrich
 * Copyright (C) 2011-2012 Ermal Luci
 * All rights reserved.
 *
 * originially part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * modified for the pfsense snort package
 * Copyright (C) 2009-2010 Robert Zelaya.
 * Copyright (C) 2014 Bill Meeks
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

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();

// Hard-code the path where IP Lists are stored
// and disregard any user-supplied path element.
$iprep_path = SNORT_IPREP_PATH;

// Set default to not show IP List editor controls
$iplist_edit_style = "display: none;";

function snort_is_iplist_active($iplist) {

	/***************************************************
	 * This function checks all the configured Snort   *
	 * interfaces to see if the passed IP List is used *
	 * as a whitelist or blacklist by an interface.    *
	 *                                                 *
	 * Returns: TRUE  if IP List is in use             *
	 *          FALSE if IP List is not in use         *
	 ***************************************************/

	global $g, $config;

	if (!is_array($config['installedpackages']['snortglobal']['rule']))
		return FALSE;

	foreach ($config['installedpackages']['snortglobal']['rule'] as $rule) {
		if (is_array($rule['wlist_files']['item'])) {
			foreach ($rule['wlist_files']['item'] as $file) {
				if ($file == $iplist)
					return TRUE;
			}
		}
		if (is_array($rule['blist_files']['item'])) {
			foreach ($rule['blist_files']['item'] as $file) {
				if ($file == $iplist)
					return TRUE;
			}
		}
	}
	return FALSE;
}


if (isset($_POST['upload'])) {
	if ($_FILES["iprep_fileup"]["error"] == UPLOAD_ERR_OK) {
		$tmp_name = $_FILES["iprep_fileup"]["tmp_name"];
		$name = $_FILES["iprep_fileup"]["name"];
		move_uploaded_file($tmp_name, "{$iprep_path}{$name}");
	}
	else
		$input_errors[] = gettext("Failed to upload file {$_FILES["iprep_fileup"]["name"]}");
}

if (isset($_POST['iplist_delete']) && isset($_POST['iplist_fname'])) {
	if (!snort_is_iplist_active($_POST['iplist_fname']))
		unlink_if_exists("{$iprep_path}{$_POST['iplist_fname']}");
	else
		$input_errors[] = gettext("This IP List is currently assigned as a Whitelist or Blackist for an interface and cannot be deleted.");
}

if (isset($_POST['iplist_edit']) && isset($_POST['iplist_fname'])) {
	$file = $iprep_path . basename($_POST['iplist_fname']);
	$data = file_get_contents($file);
	if ($data !== FALSE) {
		$iplist_data = htmlspecialchars($data);
		$iplist_edit_style = "display: table-row-group;";
		$iplist_name = basename($_POST['iplist_fname']);
		unset($data);
	}
	else {
		$input_errors[] = gettext("An error occurred reading the file.");
	}
}

if (isset($_POST['save']) && isset($_POST['iplist_data'])) {
	if (strlen(basename($_POST['iplist_name'])) > 0) {
		$file = $iprep_path . basename($_POST['iplist_name']);
		$data = str_replace("\r\n", "\n", $_POST['iplist_data']);
		file_put_contents($file, $data);
		unset($data);
	}
	else {
		$input_errors[] = gettext("You must provide a valid filename for the IP List.");
		$iplist_edit_style = "display: table-row-group;";
	}
}

// Get all files in the IP Lists sub-directory as an array
// Leave this as the last thing before spewing the page HTML
// so we can pick up any changes made to files in code above.
$ipfiles = return_dir_as_array($iprep_path);

$pgtitle = gettext("Snort: IP Reputation Lists");
include_once("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000">

<?php
include_once("fbegin.inc");
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg)
	print_info_box($savemsg);
?>

<form action="/snort/snort_ip_list_mgmt.php" enctype="multipart/form-data" method="post">
<input type="hidden" name="MAX_FILE_SIZE" value="100000000" />
<input type="hidden" name="iplist_fname" id="iplist_fname" value=""/>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tbody>
<tr><td>
<?php
        $tab_array = array();
        $tab_array[0] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
        $tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
        $tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
        $tab_array[3] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
        $tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[5] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
        $tab_array[6] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
        $tab_array[7] = array(gettext("IP Lists"), true, "/snort/snort_ip_list_mgmt.php");
	$tab_array[8] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[9] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
	$tab_array[10] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
        display_top_tabs($tab_array, true);
?>
</td>
</tr>
<tr>
	<td>
	<div id="mainarea">

	<?php if ($g['platform'] == "nanobsd") : ?>
		<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
			<tbody>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("IP Reputation is not supported on NanoBSD installs"); ?></td>
			</tr>
			</tbody>
		</table>
	<?php else: ?>
		<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
			<colgroup>
				<col style="width: 50%;">
				<col style="width: 25%;">
				<col style="width: 15%;">
				<col style="width: 10%;">
			</colgroup>
			<thead>
				<tr>
					<th class="listhdrr"><?php echo gettext("IP List File Name"); ?></th>
					<th class="listhdrr"><?php echo gettext("Last Modified Time"); ?></th>
					<th class="listhdrr"><?php echo gettext("File Size"); ?></th>
					<th class="list" align="left"><img style="cursor:pointer;" name="iplist_new" id="iplist_new" 
					src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" 
					height="17" border="0" title="<?php echo gettext('Create a new IP List');?>" 
					onClick="document.getElementById('iplist_data').value=''; document.getElementById('iplist_name').value=''; document.getElementById('iplist_editor').style.display='table-row-group'; document.getElementById('iplist_name').focus();" />
					<img style="cursor:pointer;" name="iplist_import" id="iplist_import" 
					onClick="document.getElementById('uploader').style.display='table-row-group';" 
					src="../themes/<?= $g['theme']; ?>/images/icons/icon_import_alias.gif" width="17" 
					height="17" border="0" title="<?php echo gettext('Import/Upload an IP List');?>"/></th>
				</tr>
			</thead>
		<?php foreach ($ipfiles as $file): ?>
			<tr>
				<td class="listr"><?php echo gettext($file); ?></td>
				<td class="listr"><?=date('M-d Y g:i a', filemtime("{$iprep_path}{$file}")); ?></td>
				<td class="listr"><?=format_bytes(filesize("{$iprep_path}{$file}")); ?> </td>
				<td class="list"><input type="image" name="iplist_edit[]" id="iplist_edit[]" 
				onClick="document.getElementById('iplist_fname').value='<?=$file;?>';" 
				src="../themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" 
				height="17" border="0" title="<?php echo gettext('Edit this IP List');?>"/>
				<input type="image" name="iplist_delete[]" id="iplist_delete[]" 
				onClick="document.getElementById('iplist_fname').value='<?=$file;?>'; 
				return confirm('<?=gettext("Are you sure you want to permanently delete this IP List file?  Click OK to continue or CANCEL to quit.");?>');" 
				src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" 
				height="17" border="0" title="<?php echo gettext('Delete this IP List');?>"/></td>
			</tr>
		<?php endforeach; ?>
			<tbody id="uploader" style="display: none;">
				<tr>
					<td colspan="4" class="list"><br/><?php echo gettext("Click BROWSE to select a file to import, and then click UPLOAD.  Click CLOSE to quit."); ?></td>
				</tr>
				<tr>
					<td colspan="4" class="list"><input type="file" name="iprep_fileup" id="iprep_fileup" class="formfld file" size="50" />
					&nbsp;&nbsp;<input type="submit" name="upload" id="upload" value="<?=gettext("Upload");?>" 
					title="<?=gettext("Upload selected IP list to firewall");?>"/>&nbsp;&nbsp;<input type="button" 
					value="<?=gettext("Close");?>" onClick="document.getElementById('uploader').style.display='none';" /><br/></td>
					<td colspan="4" class="list"></td>
				</tr>
			</tbody>
			<tbody id="iplist_editor" style="<?=$iplist_edit_style;?>">
			<tr>
				<td colspan="4">&nbsp;</td>
			</tr>
			<tr>
				<td colspan="4"><strong><?=gettext("File Name: ");?></strong><input type="text" size="45" class="formfld file" id="iplist_name" name="iplist_name" value="<?=$iplist_name;?>" />
				&nbsp;&nbsp;<input type="submit" id="save" name="save" value="<?=gettext(" Save ");?>" title="<?=gettext("Save changes and close editor");?>" />
				&nbsp;&nbsp;<input type="button" id="cancel" name="cancel" value="<?=gettext("Cancel");?>" onClick="document.getElementById('iplist_editor').style.display='none';"  
				title="<?=gettext("Abandon changes and quit editor");?>" /></td>
			</tr>
			<tr>
				<td colspan="4">&nbsp;</td>
			</tr>
			<tr>
				<td colspan="4"><textarea wrap="off" cols="80" rows="20" name="iplist_data" id="iplist_data" 
				style="width:95%; height:100%;"><?=$iplist_data;?></textarea>
				</td>
			</tr>
			</tbody>

			<tr>
				<td colspan="3" class="vexpl"><br/><span class="red"><strong><?php echo gettext("Notes:"); ?></strong></span>
				<br/><?php echo gettext("1. IP Lists are used by the IP Reputation Preprocessor and are text files formatted " . 
				"with one IP address (or CIDR network) per line."); ?></td>
				<td class="list"></td>
			</tr>
			<tr>
				<td colspan="3" class="vexpl" style="height: 20px; vertical-align: middle;"><?php echo gettext("2. IP Lists are stored as local files on the firewall and their contents are " . 
				"not saved as part of the firewall configuration file."); ?></td>
				<td class="list"></td>
			</tr>
			<tr>
				<td colspan="3" class="vexpl"><br/><strong><?php echo gettext("IP List Controls:"); ?></strong><br/><br/>
				&nbsp;&nbsp;<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" />
				&nbsp;<?=gettext("Opens the editor window to create a new IP List.  You must provide a valid filename before saving.");?><br/>
				&nbsp;&nbsp;<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_import_alias.gif" width="17" height="17" border="0" />
				&nbsp;<?=gettext("Opens the file upload control for uploading a new IP List from your local machine.");?><br/>
				&nbsp;&nbsp;<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" />
				&nbsp;<?=gettext("Opens the IP List in a text edit control for viewing or editing its contents.");?><br/>
				&nbsp;&nbsp;<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" />
				&nbsp;<?=gettext("Deletes the IP List from the file system after confirmation.");?></td>
				<td class="list"></td>
			</tr>
		</table>
	<?php endif; ?>
	</div>
	</td>
</tr>
</tbody>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
