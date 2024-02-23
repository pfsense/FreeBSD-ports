<?php
/*
 * tftp_files.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2008 Mark J Crane
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

require_once("config.inc");
require_once("guiconfig.inc");
require_once("notices.inc");
require_once("util.inc");
require_once("/usr/local/pkg/tftpd.inc");

$shortcut_section = 'tftpd';

/* Trigger full backup creation */
if ($_GET['a'] == "other" && $_GET['t'] == "backup") {
	tftp_create_backup();
}

/* Download full backup or individual files */
if ($_GET['a'] == "download") {
	if ($_GET['t'] == "backup") {
		// Create backup first
		tftp_create_backup(true);
		// Download full TFTP server backup from BACKUP_DIR
		$desc = BACKUP_PATH;
		$filename = BACKUP_FILENAME;
	} else {
		// Download a single file from FILES_DIR
		// Only allow to download files under the FILES_DIR
		$filename = htmlspecialchars($_GET['filename']);
		$error_msg = "Attempt to download files outside of TFTP server directory rejected!";
		if (!tftp_filesdir_bounds_check($filename, $error_msg)) {
			header("Location: tftp_files.php");
			return;
		} else {
			$desc = $filename;
			$filename = basename($filename);
		}
	}

	session_cache_limiter('public');
	$fd = fopen("{$desc}", "rb");
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");
	header("Content-Description: File Transfer");
	header("Content-Disposition: attachment; filename=\"{$filename}\"");
	header("Cache-Control: no-cache, must-revalidate");
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
	header("Content-Length: " . filesize("{$desc}"));
	fpassthru($fd);
	exit;
}

/* Restore TFTP server backup */
if ($_GET['a'] == "other" && $_GET['t'] == "restore") {
	tftp_restore_backup();
	exit;
}

/* Upload files to TFTP server */
if ($_POST['upload'] == "Upload" && $_FILES["tftpd_fileup"]["error"] == UPLOAD_ERR_OK) {
	if (is_uploaded_file($_FILES['tftpd_fileup']['tmp_name'])) {
		$tmp_name = $_FILES["tftpd_fileup"]["tmp_name"];
		$name = basename($_FILES["tftpd_fileup"]["name"]);
		move_uploaded_file($tmp_name, FILES_DIR . "/{$name}");
		chown(FILES_DIR . "/{$name}", 'nobody');
		chgrp(FILES_DIR . "/{$name}", 'nobody');
	} else {
		$input_errors[] = gettext("Failed to upload file {$_FILES["tftpd_fileup"]["name"]}");
	}
}

/* Delete a file from TFTP server */
if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'tftp') {
		$filename = htmlspecialchars($_GET['filename']);
		$error_msg = "Attempt to delete files outside of TFTP server directory rejected!";
		if (!tftp_filesdir_bounds_check($filename, $error_msg)) {
			header("Location: tftp_files.php");
			return;
		} else {
			unlink_if_exists("{$filename}");
			header("Location: tftp_files.php");
			exit;
		}
	}
}

$pgtitle = array(gettext('Services'), gettext('TFTP Server'), gettext('Files'));
require_once("head.inc");
$savemsg = htmlspecialchars($_GET["savemsg"]);
$result = htmlspecialchars($_GET["result"]) ?: 'success';
if ($savemsg) {
	print_info_box($savemsg, $result);
}
$tab_array = array();
$tab_array[] = array("Settings", false, "/pkg_edit.php?xml=tftpd.xml&amp;id=0");
$tab_array[] = array("Files", true, "/tftp_files.php");
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("TFTP Server Files Management")?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed">
		<tbody><tr><td>
			<table class="table table-striped table-hover table-condensed">
			<thead>
				<th>File Name</th>
				<th>Last Modified</th>
				<th>Size</th>
				<th>Actions</th>
			</thead>
			<tbody>
			<?php $tftpdfiles = new RecursiveDirectoryIterator(FILES_DIR); ?>
			<?php foreach (new RecursiveIteratorIterator($tftpdfiles) as $filename => $file): ?>
			<?php if (is_file($file)): ?>
				<tr>
					<td><?=htmlspecialchars($file); ?></td>
					<td><?=date('M-d Y g:i a', filemtime("{$file}")); ?></td>
					<td><?=format_bytes(filesize("{$file}")); ?> </td>
						<td>
						<a name="tftpd_deleteX[]" id="tftpd_deleteX[]" type="button" title="<?=gettext('Delete this file');?>"
							href='?type=tftp&amp;act=del&amp;filename=<?=htmlspecialchars($file);?>' style="cursor: pointer;" text="delete this">
							<i class="fa-solid fa-trash-can" title="<?=gettext('Delete this file');?>"></i>
						</a>
						<a name="tftpd_dnloadX[]" id="tftpd_dnloadX[]" type="button" title="<?=gettext('Download this file');?>"
							href='tftp_files.php?a=download&amp;filename=<?=htmlspecialchars($file);?>' style="cursor: pointer;">
							<i class="fa-solid fa-download" title="<?=gettext('Download this file');?>"></i>
						</a>
					</td>
				</tr>
			<?php endif; ?>
			<?php endforeach; ?>
			</tbody>
			</table>
		</td></tr></tbody>
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

					<h3 class="modal-title" id="myModalLabel"><?=gettext("File Upload")?></h3>
				</div>

				<div class="modal-body">
					<?=gettext("Select a file to upload, and then click 'Upload'. Click 'Close' to quit."); ?><br /><br />
					<form action="tftp_files.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
					<input type="hidden" name="MAX_FILE_SIZE" value="100000000" />
					<input type="file" class="btn btn-info" name="tftpd_fileup" id="tftpd_fileup" class="formfld file" size="50" /><br />
					<input type="submit" class="btn btn-sm btn-primary" name="upload" id="upload" value="<?=gettext("Upload");?>" title="<?=gettext("Upload selected files");?>"/>&nbsp;&nbsp;
					<input type="button" class="btn btn-sm btn-default" value="<?=gettext("Close");?>" data-dismiss="modal"/><br/>
					</form>
				</div>
			</div>
		</div>
	</div>
	<nav class="action-buttons">
		<button data-toggle="modal" data-target="#uploader" role="button" aria-expanded="false" type="button" name="tftpd_upload" id="tftpd_upload" class="btn btn-info btn-sm" title="<?=gettext('Upload files');?>">
			<i class="fa-solid fa-upload icon-embed-btn"></i>
			<?=gettext("Upload")?>
		</button>
		<a name="tftpd_dnload_all" id="tftpd_dnload_all" type="button" class="btn btn-info btn-sm" 
			title="<?=sprintf(gettext('Backup all files to %s and download the backup in a single gzip archive'), BACKUP_PATH);?>" 
			href="tftp_files.php?a=download&amp;t=backup" text="download all files">
			<i class="fa-solid fa-download icon-embed-btn"></i>
			<?=gettext('Backup &amp; Download');?>
		</a>
		<a name="tftpd_backup" id="tftpd_backup" type="button" class="btn btn-success btn-sm" title="<?=sprintf(gettext('Backup all files to %s'), BACKUP_PATH);?>"
			href="tftp_files.php?a=other&amp;t=backup" text="backup files">
			<i class="fa-solid fa-save icon-embed-btn"></i>
			<?=gettext('Backup');?>
		</a>
		<?php if (file_exists(BACKUP_PATH)): ?>
		<a name="tftpd_restore" id="tftpd_restore" type="button" class="btn btn-danger btn-sm" title="<?=sprintf(gettext('Restore all files from %s'), BACKUP_PATH);?>"
			href="tftp_files.php?a=other&amp;t=restore" text="restore backup">
			<i class="fa-solid fa-undo icon-embed-btn"></i>
			<?=gettext('Restore');?>
		</a>
		<?php endif; ?>
	</nav>
</div>

<?php require_once("foot.inc"); ?>
