<?php
/*
 * nmap_view_results.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2022-2022 Rubicon Communications, LLC (Netgate)
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

require("guiconfig.inc");
#require_once("pfsense-utils.inc");
require("/usr/local/pkg/nmap.inc");

$pgtitle = array("Package", "Diagnostics: Nmap", "View Results");

require_once("head.inc");

$tab_array = array();
$tab_array[] = array("Scan", false, "/pkg_edit.php?xml=nmap.xml&amp;id=0");
$tab_array[] = array("View Results", true, "/nmap_view_results.php");
display_top_tabs($tab_array);

$fp = "/root/";
$fn = "nmap.result";

$form = new Form(false);
$section = new Form_Section('Nmap Scan Results:');
if (file_exists($fp.$fn)) {
	$section->addInput(new Form_StaticText(
		'Last scan completed on:',
		date("F jS, Y g:i:s a.", filemtime($fp.$fn))
	));
} else {
	$section->addInput(new Form_StaticText(
		'Last scan completed on:',
		'none'
	));
}

$form->add($section);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Last scan results')?></h2></div>
	<div class="panel-body">
		<div class="form-group">
<?php
		print('<textarea class="form-control" rows="20" style="font-size: 13px; font-family: consolas,monaco,roboto mono,liberation mono,courier;">');
		$max_display_size = 50*1024*1024; // 50MB limit on GUI capture display. See https://redmine.pfsense.org/issues/9239
		if (file_exists($fp.$fn) && (filesize($fp.$fn) > $max_display_size)) {
			print(gettext("Nmap scan results file is too large to display in the GUI.") .
				"\n" .
				gettext("Download the file, or view it in the console or ssh shell.") .
				"\n" .
				gettext("Results file: {$fp}{$fn}"));
		} elseif (!file_exists($fp.$fn) || (filesize($fp.$fn) === 0)) {
			print(gettext("No nmap scan results to display."));
		} else {
			print(file_get_contents($fp.$fn));
		}
		print('</textarea>');

?>
		</div>
	</div>
</div>
<?php

/* check if nmap scan is already running */
$processcheck = (trim(shell_exec("/bin/ps axw -O pid= | /usr/bin/grep 'tee {$fp}{$fn}' | /usr/bin/egrep -v '(pflog|grep)'")));

$processisrunning = ($processcheck != "");

if ($_POST) {
	if ($_POST['clearbtn'] != "") {
		$action = gettext("Clear Results");

		//delete previous scan result if it exists
		if (file_exists($fp.$fn) and $processisrunning != true) {
			unlink ($fp.$fn);
			header("Refresh: 0");
		}
	} else if ($_POST['refreshbtn'] != "") {
		$action = gettext("Refresh Results");
		header("Refresh: 0");
	}
}

if (file_exists($fp.$fn) and $processisrunning != true) {
	$group = new Form_Group('');
	$group->add(new Form_Button(
		'clearbtn',
		'Clear Results',
		null,
		'fa-trash'
	))->setHelp('Clear scan results file.')->addClass('btn-danger');

	$section->add($group);

} else if ($processisrunning) {
		$group = new Form_Group('');
		$group->add(new Form_Button(
			'refreshbtn',
			' Refresh Results',
			null,
			'fa-retweet'
		))->setHelp('Reload scan results.')->addClass('btn-success');

	$section->add($group);
}

print($form);

include("foot.inc");

