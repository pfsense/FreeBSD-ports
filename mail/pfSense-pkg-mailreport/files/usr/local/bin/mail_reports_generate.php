#!/usr/local/bin/php-cgi -q
<?php
/* $Id$ */
/*
	mail_reports_generate.php
	Part of pfSense
	Copyright (C) 2011 Jim Pingle <jimp@pfsense.org>
	Portions Copyright (C) 2007-2011 Seth Mos <seth.mos@dds.nl>
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
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