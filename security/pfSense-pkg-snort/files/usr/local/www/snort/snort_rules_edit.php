<?php
/*
 * snort_rules_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008-2009 Robert Zelaya
 * Copyright (c) 2014 Bill Meeks
 * Copyright (c) 2006-2009 Volker Theile
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
	echo '<html><body link="#000000" vlink="#000000" alink="#000000">';
	echo '<script language="javascript" type="text/javascript">';
	echo 'window.close();</script>';
	echo '</body></html>';
	exit;
}

if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}

$a_rule = &$config['installedpackages']['snortglobal']['rule'];

$if_real = get_real_interface($a_rule[$id]['interface']);
$snort_uuid = $a_rule[$id]['uuid'];
$snortlogdir = SNORTLOGDIR;
$snortcfgdir = "{$snortdir}/snort_{$snort_uuid}_{$if_real}/";

$file = htmlspecialchars($_GET['openruleset'], ENT_QUOTES | ENT_HTML401);
$contents = '';
$wrap_flag = "off";

// Correct displayed file title if necessary
if ($file == "Auto-Flowbit Rules")
	$displayfile = FLOWBITS_FILENAME;
else
	$displayfile = $file;

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
	elseif (file_exists("{$snortdir}/preproc_rules/{$file}"))
		$rules_map = snort_load_rules_map("{$snortdir}/preproc_rules/{$file}");
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
// Is it a rules file in the ../preproc_rules/ directory?
elseif (file_exists("{$snortdir}/preproc_rules/{$file}"))
	$contents = file_get_contents("{$snortdir}/preproc_rules/{$file}");
// Is it a disabled preprocessor auto-rules-disable file?
elseif (file_exists("{$snortlogdir}/{$file}"))
	$contents = file_get_contents("{$snortlogdir}/{$file}");
// It is not something we can display, so exit.
else
	$contents = gettext("Unable to open file: {$displayfile}");

$pgtitle = array(gettext("Snort"), gettext("File Viewer"));
?>

<?php include("head.inc");?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
	<td class="tabcont">
		<table width="100%" cellpadding="0" cellspacing="6" bgcolor="#eeeeee">
		<tr>
			<td class="pgtitle" colspan="2">Snort: Rules Viewer</td>
		</tr>
		<tr>
			<td width="20%">
				<input type="button" class="formbtn" value="Return" onclick="window.close()">
			</td>
			<td align="right">
				<b><?php echo gettext("Rules File: ") . '</b>&nbsp;' . $displayfile; ?>&nbsp;&nbsp;&nbsp;&nbsp;
			</td>
		</tr>
		<tr>
			<td valign="top" class="label" colspan="2">
			<div style="background: #eeeeee; width:100%; height:100%;" id="textareaitem"><!-- NOTE: The opening *and* the closing textarea tag must be on the same line. -->
			<textarea style="width:100%; height:100%;" wrap="<?=$wrap_flag?>" rows="33" cols="80" name="code2"><?=$contents;?></textarea>
			</div>
			</td>
		</tr>
		</table>
	</td>
</tr>
</table>

<?php require_once("foot.inc"); ?>
