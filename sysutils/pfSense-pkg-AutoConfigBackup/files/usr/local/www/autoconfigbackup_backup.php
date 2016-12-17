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

	/* The config write above will trigger a fresh upload with the given reason.
	 * This manual upload appears to be a relic of an older time (1.2.x)
	 * Leaving it just in case it needs to be resurrected
	 */
	//upload_config($_REQUEST['reason']);

	$donotshowheader = true;
}

$pgtitle = array("Diagnostics", "Auto Configuration Backup", "Backup Now");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array("Settings", false, "/pkg_edit.php?xml=autoconfigbackup.xml&amp;id=0");
$tab_array[] = array("Restore", false, "/autoconfigbackup.php");
$tab_array[] = array("Backup now", true, "/autoconfigbackup_backup.php");
$tab_array[] = array("Stats", false, "/autoconfigbackup_stats.php");
display_top_tabs($tab_array);

$form = new Form("Backup");

$section = new Form_Section('Backup Details');

$section->addInput(new Form_Input(
	'reason',
	'Revision Reason',
	'text',
	$_REQUEST['reason']
))->setWidth(7)->setHelp("Enter the reason for the backup");

$form->add($section);

print($form);

?>
<?php include("foot.inc"); ?>
