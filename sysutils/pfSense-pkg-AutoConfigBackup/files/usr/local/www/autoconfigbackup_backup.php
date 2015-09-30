<?php
/*
	autoconfigbackup_backup.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2008 Scott Ullrich
	Copyright (C) 2008-2015 Electric Sheep Fencing LP
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
require("globals.inc");
require("guiconfig.inc");
require("autoconfigbackup.inc");

global $pf_version;
$pf_version = substr(trim(file_get_contents("/etc/version")), 0, 3);
if ($pf_version < 2.0) {
	require("crypt_acb.php");
}

if (!$config['installedpackages']['autoconfigbackup']['config'][0]['username']) {
	Header("Location: /pkg_edit.php?xml=autoconfigbackup.xml&id=0&savemsg=Please+setup+Auto+Config+Backup");
	exit;
}

if ($_POST) {
	if ($_REQUEST['nooverwrite']) {
		touch("/tmp/acb_nooverwrite");
	}
	if ($_REQUEST['reason']) {
		write_config($_REQUEST['reason']);
	} else {
		write_config("Backup invoked via Auto Config Backup.");
	}
	$config = parse_config(true);
	conf_mount_rw();
	unlink_if_exists("/cf/conf/lastpfSbackup.txt");
	conf_mount_ro();
	upload_config($_REQUEST['reason']);
	$savemsg = "Backup completed successfully.";
	$donotshowheader = true;
}

$pgtitle = "Diagnostics: Auto Configuration Backup Now";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<div id='maincontent'>
<?php
	include("fbegin.inc");
	if ($pf_version < 2.0) {
		echo "<p class=\"pgtitle\">{$pgtitle}</p>";
	}
	if ($savemsg) {
		print_info_box($savemsg);
	}
	if ($input_errors) {
		print_input_errors($input_errors);
	}
?>
<form method="post" action="autoconfigbackup_backup.php">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
	<div id='feedbackdiv'></div>
	<?php
		$tab_array = array();
		$tab_array[] = array("Settings", false, "/pkg_edit.php?xml=autoconfigbackup.xml&amp;id=0");
		$tab_array[] = array("Restore", false, "/autoconfigbackup.php");
		$tab_array[] = array("Backup now", true, "/autoconfigbackup_backup.php");
		$tab_array[] = array("Stats", false, "/autoconfigbackup_stats.php");
		display_top_tabs($tab_array);
	?>
</td></tr>
<tr><td>
	<table id="backuptable" class="tabcont" align="center" width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr><td colspan="2" align="left">
			<table>
				<tr>
					<td align="right">Enter the backup reason:</td>
					<td>
						<input name="reason" id="reason" size="80" />
					</td>
				</tr>
				<tr>
					<td>&nbsp;</td>
				</tr>
				<tr>
					<td align="right">
						<input type="submit" name="Backup" value="Backup" />
					</td>
				</tr>
			</table>
		</td></tr>
	</table>
</td></tr>
</div>
</td></tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
