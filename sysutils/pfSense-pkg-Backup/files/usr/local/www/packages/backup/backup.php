<?php
/*
 * backup.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Mark J Crane
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
require_once("/usr/local/pkg/backup.inc");

global $config, $backup_dir, $backup_filename, $backup_path;

if (!is_array($config['installedpackages']['backup'])) {
	$config['installedpackages']['backup'] = array();
}

if (!is_array($config['installedpackages']['backup']['config'])) {
	$config['installedpackages']['backup']['config'] = array();
}

$a_backup = &$config['installedpackages']['backup']['config'];
$backup_dir = "/root/backup";
$backup_filename = "pfsense.bak.tgz";
$backup_path = "{$backup_dir}/{$backup_filename}";

if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'backup') {
		if ($a_backup[$_GET['id']]) {
			conf_mount_rw();
			unset($a_backup[$_GET['id']]);
			write_config();
			header("Location: backup.php");
			conf_mount_ro();
			exit;
		}
	}
}

if ($_GET['a'] == "download") {
	if ($_GET['t'] == "backup") {
		conf_mount_rw();

		$i = 0;
		if (count($a_backup) > 0) {
			/* Do NOT remove the trailing space after / from $backup_cmd below!!! */
			$backup_cmd = "/usr/bin/tar --create --verbose --gzip --file {$backup_path} --directory / ";
			foreach ($a_backup as $ent) {
				if ($ent['enabled'] == "true") {
					$backup_cmd .= escapeshellarg($ent['path']) . ' ';
				}
				$i++;
			}
			system($backup_cmd);
		}

		session_cache_limiter('public');
		$fd = fopen("{$backup_path}", "rb");
		header("Content-Type: application/force-download");
		header("Content-Type: binary/octet-stream");
		header("Content-Type: application/download");
		header("Content-Description: File Transfer");
		header('Content-Disposition: attachment; filename="' . $backup_filename . '"');
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
		header("Content-Length: " . filesize($backup_path));
		fpassthru($fd);

		conf_mount_ro();
		exit;
	}
}

if ($_GET['a'] == "other") {
	if ($_GET['t'] == "restore") {
		// Extract the tgz file
		if (file_exists($backup_path)) {
			conf_mount_rw();
			system("/usr/bin/tar -xpzC / -f {$backup_path}");
			header("Location: backup.php?savemsg=Backup+has+been+restored.");
		} else {
			header("Location: backup.php?savemsg=Restore+failed.+Backup+file+not+found.");
		}
		conf_mount_ro();
		exit;
	}
}

if (($_POST['submit'] == "Upload") && is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
	conf_mount_rw();
	move_uploaded_file($_FILES['ulfile']['tmp_name'], "{$backup_path}");
	$savemsg = "Uploaded file to {$backup_dir}" . htmlentities($_FILES['ulfile']['name']);
	system("/usr/bin/tar -xpzC / -f {$backup_path}");
	conf_mount_ro();
}

$pgtitle = array(gettext("Diagnostics"), gettext("Backup Files and Directories"), gettext("Settings"));
include("head.inc");

if ($_GET["savemsg"]) {
	print_info_box($_GET["savemsg"]);
}

$tab_array = array();
$tab_array[] = array(gettext("Settings"), true, "/packages/backup/backup.php");
$tab_array[] = array(gettext("Add"), false, "/packages/backup/backup_edit.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">Backups</h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-hover">
				<tr>
					<td>Use this to tool to backup files and directories. The following directories are recommended for backup:
						<table>
							<tr><td><strong>pfSense Config:</strong></td><td>/cf/conf</td></tr>
							<tr><td><strong>RRD Graph Data Files:</strong></td><td>/var/db/rrd</td></tr>
						</table>
					</td>
				</tr>
			</table>
		</div>
	</div>
	<div class="panel-heading"><h2 class="panel-title">Upload Archive</h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<form action="backup.php" method="post" enctype="multipart/form-data" name="frmUpload" onsubmit="">
				<table class="table table-hover">
				<tr>
					<td colspan="2">
						Restore a backup by selecting the backup archive and clicking <strong>Upload</strong>.
					</td>
				</tr>
				<tr>
					<td>File to upload:</td>
					<td>
						<input name="ulfile" type="file" class="btn btn-info" id="ulfile" />
						<br />
						<button name="submit" type="submit" class="btn btn-primary" id="upload" value="Upload">
							<i class="fa fa-upload icon-embed-btn"></i>
							Upload
						</button>
					</td>
				</tr>
				</table>
			</form>
		</div>
	</div>
	<div class="panel-heading"><h2 class="panel-title">Backup and Restore</h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<form action="backup.php" method="post" enctype="multipart/form-data" name="frmUpload" onsubmit="">
			<table class="table table-hover">
				<tr>
					<td>
					The 'Backup' button compresses the directories that are listed below to /root/backup/pfsense.bak.tgz; after that it presents the file for download.<br />
					If the backup file does not exist in /root/backup/pfsense.bak.tgz then the 'Restore' button will be hidden.
					</td>
				</tr>
				<tr>
					<td>
						<button type='button' class="btn btn-primary" value='Backup' onclick="document.location.href='backup.php?a=download&amp;t=backup';">
							<i class="fa fa-download icon-embed-btn"></i>
							Backup
						</button>
						<?php	if (file_exists($backup_path)) { ?>
								<button type="button" class="btn btn-warning" value="Restore" onclick="document.location.href='backup.php?a=other&amp;t=restore';">
									<i class="fa fa-undo icon-embed-btn"></i>
									Restore
								</button>
						<?php 	} ?>
					</td>
				</tr>
			</table>
			</form>
		</div>
	</div>
	<div class="panel-heading"><h2 class="panel-title">Backup Locations</h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<form action="backup_edit.php" method="post" name="iform" id="iform">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<td width="20%">Name</td>
						<td width="25%">Path</td>
						<td width="5%">Enabled</td>
						<td width="40%">Description</td>
						<td width="10%">Actions</td>
					</tr>
				</thead>
				<tbody>
<?php
$i = 0;
if (count($a_backup) > 0):
	foreach ($a_backup as $ent): ?>
					<tr>
						<td><?=$ent['name']?>&nbsp;</td>
						<td><?=$ent['path']?>&nbsp;</td>
						<td><? echo ($ent['enabled'] == "true") ? "Enabled" : "Disabled";?>&nbsp;</td>
						<td><?=htmlspecialchars($ent['description'])?>&nbsp;</td>
						<td>
							<a href="backup_edit.php?id=<?=$i?>"><i class="fa fa-pencil" alt="edit"></i></a>
							<a href="backup_edit.php?type=backup&amp;act=del&amp;id=<?=$i?>"><i class="fa fa-trash" alt="delete"></i></a>
						</td>
					</tr>
<?	$i++;
	endforeach;
endif; ?>
					<tr>
						<td colspan="5"></td>
						<td>
							<a class="btn btn-small btn-success" href="backup_edit.php"><i class="fa fa-plus" alt="add"></i> Add</a>
						</td>
					</tr>
				</tbody>

			</form>
		</div>
	</div>
</div>

<?php include("foot.inc"); ?>
