<?php
/*
 * autoconfigbackup_backup.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2015 Rubicon Communications, LLC (Netgate)
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
		if (write_config($_REQUEST['reason'])) {
			$savemsg = "Backup completed successfully.";
		}
	} elseif (write_config("Backup invoked via Auto Config Backup.")) {
			$savemsg = "Backup completed successfully.";
	} else {
		$savemsg = "Backup not completed - write_config() failed.";
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
if ($savemsg) {
	print_info_box($savemsg, 'success');
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
