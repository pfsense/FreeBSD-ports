<?php
/*
 * suricata_rules_edit.php
 *
*  Copyright (c)  2004-2016  Electric Sheep Fencing, LLC. All rights reserved.
*
*  Redistribution and use in source and binary forms, with or without modification,
*  are permitted provided that the following conditions are met:
*
*  1. Redistributions of source code must retain the above copyright notice,
*      this list of conditions and the following disclaimer.
*
*  2. Redistributions in binary form must reproduce the above copyright
*      notice, this list of conditions and the following disclaimer in
*      the documentation and/or other materials provided with the
*      distribution.
*
*  3. All advertising materials mentioning features or use of this software
*      must display the following acknowledgment:
*      "This product includes software developed by the pfSense Project
*       for use in the pfSense software distribution. (http://www.pfsense.org/).
*
*  4. The names "pfSense" and "pfSense Project" must not be used to
*       endorse or promote products derived from this software without
*       prior written permission. For written permission, please contact
*       coreteam@pfsense.org.
*
*  5. Products derived from this software may not be called "pfSense"
*      nor may "pfSense" appear in their names without prior written
*      permission of the Electric Sheep Fencing, LLC.
*
*  6. Redistributions of any form whatsoever must retain the following
*      acknowledgment:
*
*  "This product includes software developed by the pfSense Project
*  for use in the pfSense software distribution (http://www.pfsense.org/).
*
*  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
*  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
*  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
*  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
*  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
*  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
*  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
*  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
*  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
*  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
*  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
*  OF THE POSSIBILITY OF SUCH DAMAGE.
*
*
* Portions of this code are based on original work done for the Snort package for pfSense by the following contributors:
*
* Copyright (C) 2003-2004 Manuel Kasper
* Copyright (C) 2005 Bill Marquette
* Copyright (C) 2006 Scott Ullrich (copyright assigned to ESF)
* Copyright (C) 2009 Robert Zelaya Sr. Developer
* Copyright (C) 2012 Ermal Luci  (copyright assigned to ESF)
* Copyright (C) 2016 Bill Meeks
*
*/

require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

$flowbit_rules_file = FLOWBITS_FILENAME;
$suricatadir = SURICATADIR;

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

if (!is_array($config['installedpackages']['suricata']['rule'])) {
	$config['installedpackages']['suricata']['rule'] = array();
}

$a_rule = &$config['installedpackages']['suricata']['rule'];

$if_real = get_real_interface($a_rule[$id]['interface']);
$suricata_uuid = $a_rule[$id]['uuid'];
$suricatacfgdir = "{$suricatadir}suricata_{$suricata_uuid}_{$if_real}/";

$file = htmlspecialchars($_GET['openruleset'], ENT_QUOTES | ENT_HTML401);
$contents = '';
$wrap_flag = "off";

// Correct displayed file title if necessary
if ($file == "Auto-Flowbit Rules")
	$displayfile = FLOWBITS_FILENAME;
elseif ($file == "suricata.rules")
	$displayfile = "Currently Active Rules";
else
	$displayfile = strip_tags($file);

// Read the contents of the argument passed to us.
// It may be an IPS policy string, an individual SID,
// a standard rules file, or a complete file name.
// Test for the special case of an IPS Policy file.
if (substr($file, 0, 10) == "IPS Policy") {
	$rules_map = suricata_load_vrt_policy(strtolower(trim(substr($file, strpos($file, "-")+1))));
	if (isset($_GET['sid']) && is_numericint($_GET['sid']) && isset($_GET['gid']) && is_numericint($_GET['gid'])) {
		$contents = $rules_map[$_GET['gid']][trim($_GET['sid'])]['rule'];
		$wrap_flag = "soft";
	}
	else {
		$contents = "# Suricata IPS Policy - " . ucfirst(trim(substr($file, strpos($file, "-")+1))) . "\n\n";
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
		$rules_map = suricata_load_rules_map("{$suricatacfgdir}rules/" . FLOWBITS_FILENAME);
	elseif ($file == "suricata.rules")
		$rules_map = suricata_load_rules_map("{$suricatacfgdir}rules/suricata.rules");
	else
		$rules_map = suricata_load_rules_map("{$suricatadir}rules/{$file}");

	$contents = $rules_map[$_GET['gid']][trim($_GET['sid'])]['rule'];
	$wrap_flag = "soft";
}
// Is it our special flowbit rules file?
elseif ($file == "Auto-Flowbit Rules")
	$contents = file_get_contents("{$suricatacfgdir}rules/{$flowbit_rules_file}");
// Is it a rules file in the ../rules/ directory?
elseif (file_exists("{$suricatadir}rules/{$file}"))
	$contents = file_get_contents("{$suricatadir}rules/{$file}");
// It is not something we can display, so exit.
else
	$input_errors[] = gettext("Unable to open file: {$displayfile}");

$pgtitle = array(gettext("Suricata"), gettext("Rules File Viewer"));

include("head.inc");

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
 ))->setRows(40)->setNoWrap()->setCols(90)->removeClass("form-control");

$form->add($section);

print($form);

include("foot.inc");?>
