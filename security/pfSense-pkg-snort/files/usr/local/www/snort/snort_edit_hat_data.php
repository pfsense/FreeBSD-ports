<?php
/*
 * snort_edit_hat_data.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2018 Bill Meeks
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

global $g, $rebuild_rules;

$snortdir = SNORTDIR;

if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}
$a_nat = &$config['installedpackages']['snortglobal']['rule'];

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
	header("Location: /snort/snort_interfaces.php");
	exit;
}

if (!empty($a_nat[$id]['host_attribute_data']))
	$pconfig['host_attribute_data'] = base64_decode($a_nat[$id]['host_attribute_data']);
else
	$pconfig['host_attribute_data'] = "";

if ($_POST['clear']) {
	unset($a_nat[$id]['host_attribute_data']);
	$a_nat[$id]['host_attribute_table'] = 'off';
	write_config("Snort pkg: cleared Host Attribute Table data for {$a_nat[$id]['interface']}.");
	$rebuild_rules = false;
	conf_mount_rw();
	snort_generate_conf($a_nat[$id]);
	conf_mount_ro();
	$pconfig['host_attribute_data'] = "";
}

if ($_POST['save']) {
	$a_nat[$id]['host_attribute_data'] = base64_encode($_POST['host_attribute_data']);
	if (strlen($_POST['host_attribute_data']) > 0)
		$a_nat[$id]['host_attribute_table'] = 'on';
	else
		$a_nat[$id]['host_attribute_table'] = 'off';
	write_config("Snort pkg: modified Host Attribute Table data for {$a_nat[$id]['interface']}.");
	$rebuild_rules = false;
	conf_mount_rw();
	snort_generate_conf($a_nat[$id]);
	conf_mount_ro();
	$pconfig['host_attribute_data'] = $_POST['host_attribute_data'];
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']);
$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Host Attribute Table Data"), gettext("{$if_friendly}"));
include_once("head.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$form = new Form(FALSE);
$section = new Form_Section('Edit Host Attribute Table Data');
$section->addInput(new Form_Textarea (
	'host_attribute_data',
	'Host Attribute Table Data',
	$pconfig['host_attribute_data']
))->setHelp('Type or paste in the Host Atttribute Table data for this Snort interface instance and then click SAVE.')->setRows('25')->setNoWrap()->setAttribute('wrap', 'off');

// Create some customized Form buttons
$btnsave = new Form_Button(
	'save',
	'Save',
	null,
	'fa-save'
);
$btnreturn = new Form_Button(
	'',
	'Return',
	'snort_preprocessors.php?id=' . $id,
	'fa-backward'
);
$btnclear = new Form_Button(
	'clear',
	'Clear',
	null,
	'fa-trash'
);

// Customize the class and attributes for the buttons
$btnsave->addClass('btn-primary')->addClass('btn-default');
$btnsave->setAttribute('title', gettext('Save Host Attribute data'));
$btnreturn->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-success');
$btnreturn->setAttribute('title', gettext("Return to Preprocessors tab"));
$btnclear->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-danger');
$btnclear->setAttribute('title', gettext("Deletes all Host Attribute data"));

// Add the customized buttons to StaticText control for display
$section->addInput(new Form_StaticText(
	null,
	$btnsave . $btnreturn . $btnclear
));

$form->add($section);

$form->addGlobal(new Form_Input(
	'id',
	'id',
	'hidden',
	$id
));

print($form);

include("foot.inc"); ?>

