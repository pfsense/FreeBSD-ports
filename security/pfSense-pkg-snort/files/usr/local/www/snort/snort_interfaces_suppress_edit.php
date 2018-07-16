<?php
/*
 * snort_interfaces_suppress_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009-2010 Robert Zelaya.
 * Copyright (c) 2018 Bill Meeks
 * All rights reserved.
 *
 * originially part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

$snortglob = $config['installedpackages']['snortglobal'];

if (!is_array($config['installedpackages']['snortglobal']['suppress']))
	$config['installedpackages']['snortglobal']['suppress'] = array();
if (!is_array($config['installedpackages']['snortglobal']['suppress']['item']))
	$config['installedpackages']['snortglobal']['suppress']['item'] = array();
$a_suppress = &$config['installedpackages']['snortglobal']['suppress']['item'];

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

/* Should never be called without identifying list index, so bail */
if (is_null($id)) {
	header("Location: /snort/snort_interfaces_suppress.php");
	exit;
}

/* returns true if $name is a valid name for a whitelist file name or ip */
function is_validwhitelistname($name) {
	if (!is_string($name))
		return false;

	if (!preg_match("/[^a-zA-Z0-9\_\.\/]/", $name))
		return true;

	return false;
}

if ($_POST['cancel']) {
	header("Location: /snort/snort_interfaces_suppress.php");
	exit;
}

if (isset($id) && $a_suppress[$id]) {

	/* old settings */
	$pconfig['name'] = $a_suppress[$id]['name'];
	$pconfig['uuid'] = $a_suppress[$id]['uuid'];
	$pconfig['descr'] = $a_suppress[$id]['descr'];
	if (!empty($a_suppress[$id]['suppresspassthru'])) {
		$pconfig['suppresspassthru'] = base64_decode($a_suppress[$id]['suppresspassthru']);
		$pconfig['suppresspassthru'] = str_replace("&#8203;", "", $pconfig['suppresspassthru']);
	}
	if (empty($a_suppress[$id]['uuid']))
		$pconfig['uuid'] = uniqid();
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	$reqdfields = explode(" ", "name");
	$reqdfieldsn = array("Name");

	$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
	if ($pf_version < 2.1)
		$input_errors = eval('do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors); return $input_errors;');
	else
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if(strtolower($_POST['name']) == "defaultwhitelist")
		$input_errors[] = "Suppression List files may not be named defaultwhitelist.";

	if (is_validwhitelistname($_POST['name']) == false)
		$input_errors[] = "Suppression List name may only consist of the characters \"a-z, A-Z, 0-9 and _\". Note: No Spaces or dashes. Press Cancel to reset.";

	/* check for name conflicts */
	foreach ($a_suppress as $s_list) {
		if (isset($id) && ($a_suppress[$id]) && ($a_suppress[$id] === $s_list))
			continue;

		if ($s_list['name'] == $_POST['name']) {
			$input_errors[] = "A Suppression List file with this name already exists.";
			break;
		}
	}

	if (!$input_errors) {
		$s_list = array();
		$s_list['name'] = $_POST['name'];
		$s_list['uuid'] = uniqid();
		$s_list['descr']  =  mb_convert_encoding($_POST['descr'],"HTML-ENTITIES","auto");
		if ($_POST['suppresspassthru']) {
			$s_list['suppresspassthru'] = str_replace("&#8203;", "", $s_list['suppresspassthru']);
			$s_list['suppresspassthru'] = base64_encode(str_replace("\r\n", "\n", $_POST['suppresspassthru']));
		}

		if (isset($id) && $a_suppress[$id])
			$a_suppress[$id] = $s_list;
		else
			$a_suppress[] = $s_list;

		write_config("Snort pkg: modified Suppress List {$s_list['name']}.");
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();

		header("Location: /snort/snort_interfaces_suppress.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Suppression List Edit"));
include_once("head.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$tab_array = array();
$tab_array[] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
$tab_array[] = array(gettext("Suppress"), true, "/snort/snort_interfaces_suppress.php");
$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);

$form = new Form(FALSE);
$section = new Form_Section('General Information');
$section->addInput(new Form_Input(
	'name',
	'Name',
	'text',
	$pconfig['name']
))->setPattern('[a-zA-Z0-9_]+')->setHelp('The list name may only consist of the characters \'a-z, A-Z, 0-9 and _\'.');
$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference.');
$form->add($section);

$content_help = 'Valid keywords are \'suppress\', \'event_filter\' and \'rate_filter\'.' . '<br />';
$content_help .= 'Example 1: suppress gen_id 1, sig_id 1852, track by_src, ip 10.1.1.54' . '<br />';
$content_help .= 'Example 2: event_filter gen_id 1, sig_id 1851, type limit, track by_src, count 1, seconds 60' . '<br />';
$content_help .= 'Example 3: rate_filter gen_id 135, sig_id 1, track by_src, count 100, seconds 1, new_action log, timeout 10';
$section = new Form_Section('Suppression List Content');
$section->addInput(new Form_Textarea (
	'suppresspassthru',
	'Suppression Rules',
	$pconfig['suppresspassthru']
))->setHelp($content_help)->setAttribute('rows', 16)->setAttribute('wrap', 'off');
$form->add($section);

$section = new Form_Section('');
$btnsave = new Form_Button(
	'save',
	'Save',
	null,
	'fa-save'
);
$btncancel = new Form_Button(
	'cancel',
	'Cancel'
);
$btnsave->addClass('btn-primary')->addClass('btn-default');
$btncancel->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-warning');

$section->addInput(new Form_StaticText(
	null,
	$btnsave . $btncancel
));
$form->add($section);

// Include the Pass List ID in a hidden form field with any $_POST
if (isset($id)) {
	$form->addGlobal(new Form_Input(
		'id',
		'id',
		'hidden',
		$id
	));
}

print($form);
include("foot.inc");?>

