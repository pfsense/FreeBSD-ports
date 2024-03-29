#!/usr/local/bin/php-cgi -q
<?php
/*
 * mail_reports_generate.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2007-2011 Seth Mos <seth.mos@dds.nl>
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

require_once("mailreport/mail_report.inc");
require_once("notices.inc");

$id = $_REQUEST['id'];

if (!isset($id) && isset($argv[1])) {
	$id = $argv[1];
}

init_config_arr(array('mailreports', 'schedule'));

// If there is no report ID or the report doesn't exist, bail.
if (!isset($id) ||
    !$config['mailreports']['schedule'][$id]) {
	exit;
}

init_config_arr(array('mailreports', 'schedule', $id, 'cmd', 'row'));
init_config_arr(array('mailreports', 'schedule', $id, 'log', 'row'));

$thisreport = $config['mailreports']['schedule'][$id];

// If there is nothing to do, bail!
if (empty($thisreport['cmd']['row']) && empty($thisreport['log']['row'])) {
	return;
}

// Print report header

// Used to determine if any content was generated
$hascontent = 0;

// Print command output
$cmdtext = "";
foreach ($thisreport['cmd']['row'] as $cmd) {
	$output = "";
	$cmdtext .= gettext("Command output") . ": {$cmd['descr']} (" . htmlspecialchars($cmd['detail']) . ")<br />\n";
	exec($cmd['detail'], $output);
	$hascontent = $hascontent + count($output);
	$cmdtext .= "<pre>\n";
	$cmdtext .= implode("\n", $output);
	$cmdtext .= "\n</pre>";
}

// Print log output
$logtext = "";
foreach ($thisreport['log']['row'] as $log) {
	$lines = empty($log['lines']) ? 50 : $log['lines'];
	$filter = empty($log['detail']) ? null : array($log['detail']);
	$logtext .= gettext("Log output") . ": " . get_friendly_log_name($log['logfile']) . " ({$log['logfile']})<br />\n";
	$logtext .= "<pre>\n";
	$output = mail_report_get_log($log['logfile'], $lines, $filter);
	$hascontent = $hascontent + count($output);
	$logtext .= implode("\n", $output);
	$logtext .= "\n</pre>";
}

if ($hascontent > 0 || empty($thisreport['skipifempty'])) {
	mail_report_send($thisreport['descr'], $cmdtext, $logtext, $attach);
}
