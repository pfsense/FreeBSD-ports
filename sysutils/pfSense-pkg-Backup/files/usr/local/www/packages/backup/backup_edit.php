<?php
/*
	backup_edit.php
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


$a_backup = &$config['installedpackages']['backup']['config'];

$id = $_GET['id'];
if (isset($_POST['id'])) {
	$id = $_POST['id'];
}

if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'backup') {
		if ($a_backup[$_GET['id']]) {
			conf_mount_rw();
			unset($a_backup[$_GET['id']]);
			write_config();
			backup_sync_package();
			header("Location: backup.php");
			conf_mount_ro();
			exit;
		}
	}
}

if (isset($id) && $a_backup[$id]) {

	$pconfig['name'] = $a_backup[$id]['name'];
	$pconfig['path'] = $a_backup[$id]['path'];
	$pconfig['enabled'] = $a_backup[$id]['enabled'];
	$pconfig['description'] = $a_backup[$id]['description'];

}

if ($_POST) {
	/* TODO - This needs some basic input validation for the path at least */
	unset($input_errors);
	$pconfig = $_POST;

	if (!$input_errors) {

		$ent = array();
		$ent['name'] = $_POST['name'];
		$ent['path'] = $_POST['path'];
		$ent['enabled'] = $_POST['enabled'];
		$ent['description'] = $_POST['description'];

		if (isset($id) && $a_backup[$id]) {
			// update
			$a_backup[$id] = $ent;
		} else {
			// add
			$a_backup[] = $ent;
		}

		write_config();
		backup_sync_package();

		header("Location: backup.php");
		exit;
	}
}

$thispage = gettext("Add");
if (!empty($id)) {
	$thispage = gettext("Edit");
}

$pgtitle = array(gettext("Diagnostics"), gettext("Backup Files and Directories"), $thispage);
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "/packages/backup/backup.php");
$tab_array[] = array($thispage, true, "/packages/backup/backup_edit.php");

display_top_tabs($tab_array);

$form = new Form();
$section = new Form_Section('Backup Settings');

$section->addInput(new Form_Input(
	'name',
	'Backup Name',
	'text',
	$pconfig['name']
))->setHelp('Enter a name for the backup.');

$section->addInput(new Form_Input(
	'path',
	'Path',
	'text',
	$pconfig['path']
))->setHelp('Enter the full path to the file or directory to backup.');

$section->addInput(new Form_Select(
	'enabled',
	'Enabled',
	$pconfig['enabled'],
	array( "true" => "Enabled", "false" => "Disabled" )
))->setHelp('Choose whether this backup location is enabled or disabled.');

$section->addInput(new Form_Input(
	'description',
	'Description',
	'text',
	$pconfig['description']
))->setHelp('Enter a description here for reference.');

$form->add($section);

print $form;
?>
<?php include("foot.inc"); ?>
