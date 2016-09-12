#!/usr/local/bin/php-cgi -q
<?php
/*
 * mail_reports_generate.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011 Rubicon Communications, LLC (Netgate)
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

require_once("mail_reports.inc");
require_once("notices.inc");

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (!isset($id) && isset($argv[1]))
	$id = $argv[1];

// No data, no report to run, bail!
if (!isset($id))
	exit;

// No reports to run, bail!
if (!is_array($config['mailreports']['schedule']))
	exit;

// The Requested report doesn't exist, bail!
if (!$config['mailreports']['schedule'][$id])
	exit;

$thisreport = $config['mailreports']['schedule'][$id];

if (is_array($thisreport['cmd']) && is_array($thisreport['cmd']['row']))
	$cmds = $thisreport['cmd']['row'];
else
	$cmds = array();

if (is_array($thisreport['log']) && is_array($thisreport['log']['row']))
	$logs = $thisreport['log']['row'];
else
	$logs = array();

// If there is nothing to do, bail!
if ((!is_array($cmds) || !(count($cmds) > 0))
	&& (!is_array($logs) || !(count($logs) > 0)))
	return;

// Print report header

// Print command output
$cmdtext = "";
foreach ($cmds as $cmd) {
	$output = "";
	$cmdtext .= "Command output: {$cmd['descr']} (" . htmlspecialchars($cmd['detail']) . ")<br />\n";
	exec($cmd['detail'], $output);
	$cmdtext .= "<pre>\n";
	$cmdtext .= implode("\n", $output);
	$cmdtext .= "\n</pre>";
}

// Print log output
$logtext = "";
foreach ($logs as $log) {
	$lines = empty($log['lines']) ? 50 : $log['lines'];
	$filter = empty($log['detail']) ? null : array($log['detail']);
	$logtext .= "Log output: " . get_friendly_log_name($log['logfile']) . " ({$log['logfile']})<br />\n";
	$logtext .= "<pre>\n";
	$logtext .= implode("\n", mail_report_get_log($log['logfile'], $lines, $filter));
	$logtext .= "\n</pre>";
}

mail_report_send($thisreport['descr'], $cmdtext, $logtext, $attach);

?>
