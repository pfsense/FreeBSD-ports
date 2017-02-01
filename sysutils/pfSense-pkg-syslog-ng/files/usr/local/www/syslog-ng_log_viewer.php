<?php
/*
 * syslog-ng_log_viewer.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2012 Lance Leger
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
require("/usr/local/pkg/syslog-ng.inc");

$objects = $config['installedpackages']['syslogngadvanced']['config'];
$default_logdir = $config['installedpackages']['syslogng']['config'][0]['default_logdir'];
$default_logfile = $config['installedpackages']['syslogng']['config'][0]['default_logfile'];
$compress_archives = $config['installedpackages']['syslogng']['config'][0]['compress_archives'];
$compress_type = $config['installedpackages']['syslogng']['config'][0]['compress_type'];

if ($_POST['logfile']) {
	$logfile = $_POST['logfile'];
} else {
	$logfile = $default_logdir . "/" . $default_logfile;
}

if ($_POST['limit']) {
	$limit = intval($_POST['limit']);
} else {
	$limit = "50";
}

if ($_POST['archives']) {
	$archives = true;
}

if ($_POST['filter']) {
	$filter = htmlspecialchars($_POST['filter']);
}

if ($_POST['not']) {
	$not = true;
}

$log_messages = array();
if (file_exists($logfile) && (filesize($logfile) > 0)) {
	$grep = "/usr/bin/grep -ih";

	if (($compress_archives == 'on') && glob($logfile . "*" . $compress_type) && $archives) {
		if($compress_type == 'bz2') {
			$grep = "/usr/bin/bzgrep -ih";
		} else {
			$grep = "/usr/bin/zgrep -ih";
		}
	}

	if (isset($filter) && $not) {
		$grepcmd = "$grep -v " . escapeshellarg($filter) . " $logfile";
	} else {
		$grepcmd = "$grep  " . escapeshellarg($filter) . " $logfile";
	}

	if ($archives) {
		$grepcmd = $grepcmd . "*";
	}

	$log_lines = trim(shell_exec("$grepcmd | /usr/bin/wc -l"));
	$log_output = trim(shell_exec("$grepcmd | /usr/bin/sort -M | /usr/bin/tail -n $limit"));

	if (!empty($log_output)) {
		$log_messages = explode("\n", $log_output);
		$log_messages_count = sizeof($log_messages);
	}
}

$pgtitle = array("Package", "Services: Syslog-ng", "Logs");

require_once("head.inc");

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array("General", false, "/pkg_edit.php?xml=syslog-ng.xml&amp;id=0");
$tab_array[] = array("Advanced", false, "/pkg.php?xml=syslog-ng_advanced.xml");
$tab_array[] = array("Log Viewer", true, "/syslog-ng_log_viewer.php");
display_top_tabs($tab_array);


$form = new Form(false);

$section = new Form_Section("Syslog-ng Logs");

$log_files = syslogng_get_log_files($objects);

$section->addInput(new Form_Select(
	'logfile',
	'Log File',
	$logfile,
	array_combine($log_files, $log_files)
));

$section->addInput(new Form_Select(
	'limit',
	'Limit',
	$limit,
	array_combine(array("10", "20", "50", "100", "250", "500"), array("10", "20", "50", "100", "250", "500"))
));

$section->addInput(new Form_Checkbox(
	'archives',
	'Include Archives',
	'',
	$archives
));

$section->addInput(new Form_Input(
	'filter',
	'Filter',
	'text',
	$filter
));

$section->addInput(new Form_Checkbox(
	'not',
	'Inverse Filter (NOT)',
	'',
	$not
));

$form->addGlobal(new Form_Button(
	'submit',
	"Refresh",
	null,
	'fa-refresh'
))->addClass('btn-primary');

$form->add($section);

print($form);
?>

<?php
if (empty($log_messages)) {
	print_info_box("No log messages found or log file is empty", "danger", false);
} else {
	print('<div class="panel panel-default">');
	print('<div class="panel-heading"><h2 class="panel-title">Showing ' . $log_messages_count . ' of ' . $log_lines . ' messages</h2></div>');
?>

	<div class="panel-body">
		<div clas="table-responsive">
			<table class="table table-condensed table-hover">
				<thead>
				</thead>
				<tbody>
<?php
	foreach($log_messages as $log_message) {
		print('<tr><td >' . $log_message . '</td></tr>');
	}
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php
}

include("foot.inc"); ?>
