<?php
/*
 * snort_rules_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2022 Bill Meeks
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

$flowbit_rules_file = FLOWBITS_FILENAME;
$snortdir = SNORTDIR;

if (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

// If we were not passed a valid index ID, close the pop-up and exit
if (is_null($id)) {
	echo '<html><body>';
	echo '<script language="javascript" type="text/javascript">';
	echo 'window.close();</script>';
	echo '</body></html>';
	exit;
}

$a_rule = config_get_path("installedpackage/snortglobal/rule/{$id}", []);

$if_real = get_real_interface($a_rule['interface']);
$snort_uuid = $a_rule['uuid'];
$snortlogdir = SNORTLOGDIR;
$snortcfgdir = "{$snortdir}/snort_{$snort_uuid}_{$if_real}/";

$file = basename(htmlspecialchars($_GET['openruleset'], ENT_QUOTES | ENT_HTML401));
$contents = '';
$wrap_flag = "off";

// Correct displayed file title if necessary
if ($file == "Auto-Flowbit Rules")
	$displayfile = FLOWBITS_FILENAME;
elseif ($file == "snort.rules")
	$displayfile = "Currently Active Rules";
else
	$displayfile = strip_tags($file);

// Read the contents of the argument passed to us.
// It may be an IPS policy string, an individual SID,
// a standard rules file, or a complete file name.
// Test for the special case of an IPS Policy file.
if (substr($file, 0, 10) == "IPS Policy") {
	$rules_map = snort_load_vrt_policy(strtolower(trim(substr($file, strpos($file, "-")+1))));
	if (isset($_GET['sid']) && is_numericint($_GET['sid']) && isset($_GET['gid']) && is_numericint($_GET['gid'])) {
		$contents = $rules_map[$_GET['gid']][trim($_GET['sid'])]['rule'];
		$wrap_flag = "soft";
	}
	else {
		$contents = "# Snort IPS Policy - " . ucfirst(trim(substr($file, strpos($file, "-")+1))) . "\n\n";
		foreach (array_keys($rules_map) as $k1) {
			foreach (array_keys($rules_map[$k1]) as $k2) {
				$contents .= "# Category: " . $rules_map[$k1][$k2]['category'] . "   SID: {$k2}\n";
				$contents .= $rules_map[$k1][$k2]['rule'] . "\n";
			}
		}
	}
	unset($rules_map);
}
// Is it a SID to load the rule text from?
elseif (isset($_GET['sid']) && is_numericint($_GET['sid']) && isset($_GET['gid']) && is_numericint($_GET['gid'])) {
	// If flowbit rule, point to interface-specific file
	if ($file == "Auto-Flowbit Rules")
		$rules_map = snort_load_rules_map("{$snortcfgdir}/rules/" . FLOWBITS_FILENAME);
	elseif ($file == "snort.rules")
		$rules_map = snort_load_rules_map("{$snortcfgdir}/rules/snort.rules");
	else
		$rules_map = snort_load_rules_map("{$snortdir}/rules/{$file}");

	$contents = $rules_map[$_GET['gid']][trim($_GET['sid'])]['rule'];
	$wrap_flag = "soft";
}
// Is it our special flowbit rules file?
elseif ($file == "Auto-Flowbit Rules")
	$contents = file_get_contents("{$snortcfgdir}/rules/{$flowbit_rules_file}");
// Is it a rules file in the ../rules/ directory?
elseif (file_exists("{$snortdir}/rules/{$file}"))
	$contents = file_get_contents("{$snortdir}/rules/{$file}");
// It is not something we can display, so exit.
else
	$input_errors[] = gettext("Unable to open file: {$displayfile}");

$pgtitle = array(gettext("Snort"), gettext("Rules File Viewer"));

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg)
	print_info_box($savemsg);

$form = new Form(false);

$section = new Form_Section('Rules file: ' . $displayfile);

$btnclear = new Form_Button(
	'close',
	'Close'
);

$btnclear->addClass('btn-primary');
$btnclear->setType('button');
$btnclear->setOnclick('window.close()');

$section->addInput($btnclear);

$section->addInput(new Form_Textarea(
	'textareaitem',
	'Rule file',
	$contents
 ))->setRows(40)->setNoWrap()->removeClass("form-control")->addClass("col-lg-12");

$form->add($section);

print($form);

include("foot.inc");?>
