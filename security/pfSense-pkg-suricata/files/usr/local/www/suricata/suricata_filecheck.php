<?php
/*
 * suricata_filecheck.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
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

require('guiconfig.inc');
require_once("/usr/local/pkg/suricata/suricata.inc");

$file_hash = $_REQUEST['filehash'];
$uuid = $_REQUEST['uuid'];
$file_name = urldecode($_REQUEST['filename']);
$file_size = urldecode($_REQUEST['filesize']);
$a_instance = config_get_path('installedpackages/suricata/rule', []);

foreach ($a_instance as $instance) {
	if (($instance['uuid'] == $uuid) &&
	    ($instance['enable_file_store'] == 'on') &&
	    !empty($instance['file_store_logdir'])) {
		foreach (suricata_listfiles(base64_decode($instance['file_store_logdir'])) as $path) {
			if (basename($path) == $file_hash) {
				$filepath = $path;
				$filetype = exec('/usr/bin/file -b ' . escapeshellarg($path) . ' 2>/dev/null');
				break 2;
			}
		}
	}
}

if ($_POST['download']) {
	if (file_exists($filepath)) {
		if (isset($_SERVER['HTTPS'])) {
			header('Pragma: ');
			header('Cache-Control: ');
		} else {
			header("Pragma: private");
			header("Cache-Control: private, must-revalidate");
		}
		header("Content-Type: application/octet-stream");
		header("Content-length: " . filesize($filepath));
		header("Content-disposition: attachment; filename = {$file_hash}.file");
		readfile($filepath);
	} else {
		$savemsg = gettext("An error occurred while creating archive");
	}
}

$pglinks = array("", "/suricata/suricata_interfaces.php", "@self");
$pgtitle = array("Services", "Suricata", "File Check");
include_once("head.inc");

if ($savemsg) {
	print_info_box($savemsg);
}

?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title"><?=gettext("Online Malware File Hash Check")?></h4>
	</div>
	<div>
		<p class="text-center"><br/>NOTE:&emsp;The following links are to external services, so their reliability cannot be guaranteed.
			It is also recommended to open these links in a different Browser</p>
	</div>
	<div>
		<table class="table table-striped table-hover table-compact">
			<thead>
				<tr>
					<th width="20%"><!-- Icon field --></th>
					<th><!-- Threat Source Link --></th>
				</tr>
			</thead>
			<tbody>
				<!-- IP threat source links -->
				<tr>
					<td><span style="color: blue;">Hash Lookups</span><i class="fa-solid fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.virustotal.com/gui/file/<?=$file_hash;?>/detection/">
						<?=gettext("VirusTotal");?></a></td>
				</tr>
				<tr>
					<td><i class="fa-solid fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.hybrid-analysis.com/sample/<?=$file_hash;?>">
						<?=gettext("Hybrid Analysis");?></a></td>
				</tr>
				<tr>
					<td><i class="fa-solid fa-globe pull-right"></i></td>	
					<td><a target="_blank" href="https://virusscan.jotti.org/en-US/search/hash/<?=$file_hash;?>">
						<?=gettext("Jotti's malware scan");?></a></td>
				</tr>
				<tr>
					<td><i class="fa-solid fa-globe pull-right"></i></td>	
					<td><a target="_blank" href="https://metadefender.opswat.com/results/file/<?=$file_hash;?>/hash/overview?lang=en">
						<?=gettext("OPSWAT MetaDefender Cloud");?></a></td>
				</tr>
				<tr>
					<td><i class="fa-solid fa-globe pull-right"></i></td>	
					<td><a target="_blank" href="https://www.joesandbox.com/search?q=<?=$file_hash;?>">
						<?=gettext("JOESandbox Cloud");?></a></td>
				</tr>
				<tr>
					<td><i class="fa-solid fa-globe pull-right"></i></td>	
					<td><a target="_blank" href="https://opentip.kaspersky.com/<?=$file_hash;?>/">
						<?=gettext("Kaspersky Threat Intelligence Portal");?></a></td>
				</tr>
				<tr>
					<td><i class="fa-solid fa-globe pull-right"></i></td>	
					<td><a target="_blank" href="https://beta.virusbay.io/sample/browse?q=<?=$file_hash;?>">
						<?=gettext("VirusBay");?></a></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

<?php 
$form = new Form(false);
$form->setAttribute('name', 'formalert')->setAttribute('id', 'formalert');

$section = new Form_Section('File Info');

$section->addInput(new Form_StaticText(
	'Name',
	'<br\>' . htmlspecialchars($file_name) . '&nbsp;',
));

$section->addInput(new Form_StaticText(
	'Size',
	'<br\>' . htmlspecialchars($file_size) . '&nbsp;',
));

if (strlen($file_hash) == 64) {
	$hash_type = "SHA256";
} elseif (strlen($file_hash) == 40) {
	$hash_type = "SHA1";
} elseif (strlen($file_hash) == 32) {
	$hash_type = "MD5";
} else {
	$hash_type = "unknown";
}

$section->addInput(new Form_StaticText(
	'Hash (' . $hash_type . ')',
	'<br\>' . htmlspecialchars($file_hash) . '&nbsp;',
));

if ($filepath) {
	$section->addInput(new Form_StaticText(
		'Type',
		$filetype
	));
	$section->addInput(new Form_Button(
		'download',
		'Download',
		null,
		'fa-solid fa-download'
	))->removeClass('btn-default')->addClass('btn-info btn-sm');
}

$form->add($section);

print $form;

include('foot.inc'); 

?>
