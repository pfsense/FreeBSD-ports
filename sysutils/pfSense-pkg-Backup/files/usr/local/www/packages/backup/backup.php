<?php
/*
	backup.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2008 Mark J Crane
	Copyright (C) 2015 ESF, LLC
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
require_once("guiconfig.inc");
require_once("/usr/local/pkg/backup.inc");

global $config, $backup_dir, $backup_filename, $backup_path;
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
					$backup_cmd .= htmlspecialchars($ent['path']) . ' ';
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

$pgtitle = "Backup: Files &amp; Directories";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>


<?php
if ($_GET["savemsg"]) {
	print_info_box($_GET["savemsg"]);
}
?>

<div id="mainlevel">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td class="tabnavtbl">
<?php

	$tab_array = array();
	$tab_array[] = array(gettext("Settings"), true, "/packages/backup/backup.php");
 	display_top_tabs($tab_array);

?>
</td></tr>
</table>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
	<td class="tabcont" >

	<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>Use this to tool to backup files and directories. The following directories are recommended for backup:
			<table>
				<tr><td></td><td></td></tr>
				<tr><td><strong>pfSense Config</strong></td><td>/cf/conf</td></tr>
				<tr><td><strong>RRD Graph Data Files</strong></td><td>/var/db/rrd</td></tr>
			</table>
		</td>
	</tr>
	</table>

	<br/>
	<br/>

	<div id="niftyOutter">
	<form action="backup.php" method="post" enctype="multipart/form-data" name="frmUpload" onsubmit="">
		<table width='690' cellpadding='0' cellspacing='0' border='0'>
		<tr><td align='left' colspan='4'><strong>Upload and Restore</strong></td></tr>
		<tr>
			<td colspan='2'>Use this to upload and restore your backup file.</td>
			<td align="right">File to upload:</td>
			<td width='50%' valign="top" align='right' class="label">
				<input name="ulfile" type="file" class="button" id="ulfile" />
			</td>
			<td valign="top" class="label">
				<input name="submit" type="submit" class="button" id="upload" value="Upload" />
			</td>
		</tr>
		</table>
		<br />
		<br />
	</form>
	</div>

	<table width='690' cellpadding='0' cellspacing='0' border='0'>
	<tr>
		<td width='80%'>
		<strong>Backup / Restore</strong><br />
		The 'Backup' button compresses the directories that are listed below to /root/backup/pfsense.bak.tgz; after that it presents the file for download.<br />
		If the backup file does not exist in /root/backup/pfsense.bak.tgz then the 'Restore' button will be hidden.<br /><br /><br />
		</td>
		<td width='20%' valign='middle' align='right'>
			<input type='button' value='Backup' onclick="document.location.href='backup.php?a=download&amp;t=backup';" />
			<?php
				if (file_exists($backup_path)) {
					echo "\t<input type='button' value='Restore' onclick=\"document.location.href='backup.php?a=other&amp;t=restore';\" />\n";
				}
			?>
		</td>
	</tr>
	</table>
	<br /><br />


	<form action='backup.php' method='post' name='iform' id='iform'>

<?php
if ($config_change == 1) {
	write_config();
	$config_change = 0;
}
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td width="20%" class="listhdrr">Name</td>
		<td width="25%" class="listhdrr">Path</td>
		<td width="5%" class="listhdrr">Enabled</td>
		<td width="40%" class="listhdr">Description</td>
		<td width="10%" class="list">
			<table border="0" cellspacing="0" cellpadding="1">
				<tr>
					<td width="17"></td>
					<td valign="middle"><a href="backup_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" width="17" height="17" border="0" /></a></td>
				</tr>
			</table>
		</td>
	</tr>

	<?php

	$i = 0;
	if (count($a_backup) > 0) {

		foreach ($a_backup as $ent) {

	?>
	<tr>
		<td class="listr" ondblclick="document.location='backup_edit.php?id=<?=$i;?>';">
			<?=$ent['name'];?>&nbsp;
		</td>
		<td class="listr" ondblclick="document.location='backup_edit.php?id=<?=$i;?>';">
			<?=$ent['path'];?>&nbsp;
		</td>
		<td class="listr" ondblclick="document.location='backup_edit.php?id=<?=$i;?>';">
			<?=$ent['enabled'];?>&nbsp;
		</td>
		<td class="listbg" ondblclick="document.location='backup_edit.php?id=<?=$i;?>';">
			<font color="#FFFFFF"><?=htmlspecialchars($ent['description']);?>&nbsp;</font>
		</td>
		<td valign="middle" nowrap="nowrap" class="list">
			<table border="0" cellspacing="0" cellpadding="1">
				<tr>
					<td valign="middle"><a href="backup_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" alt="" width="17" height="17" border="0" /></a></td>
					<td><a href="backup_edit.php?type=backup&amp;act=del&amp;id=<?=$i;?>" onclick="return confirm('Do you really want to delete this?')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" alt="" width="17" height="17" border="0" /></a></td>
				</tr>
			</table>
		</td>
	</tr>
	<?php
			$i++;
		}
	}
	?>

	<tr>
		<td class="list" colspan="4"></td>
		<td class="list">
			<table border="0" cellspacing="0" cellpadding="1">
				<tr>
					<td width="17"></td>
					<td valign="middle"><a href="backup_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" width="17" height="17" border="0" /></a></td>
				</tr>
			</table>
		</td>
	</tr>

	<tr>
		<td class="list" colspan="3"></td>
		<td class="list"></td>
	</tr>
</table>
</form>

<br />

</td>
</tr>
</table>

</div>

<?php include("fend.inc"); ?>
</body>
</html>
