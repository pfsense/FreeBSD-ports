<?php
/* $Id$ */
/*
	status_mail_report_add_log.php
	Part of pfSense
	Copyright (C) 2011-2014 Jim Pingle <jimp@pfsense.org>
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
/*	
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-status-mailreportsaddlog
##|*NAME=Status: Email Reports: Add Log page
##|*DESCR=Allow access to the 'Status: Email Reports: Add Log' page.
##|*MATCH=status_mail_report_add_log.php*
##|-PRIV

require("guiconfig.inc");
require_once("mail_reports.inc");

$reportid = $_REQUEST['reportid'];
$id = $_REQUEST['id'];

if (!is_array($config['mailreports']['schedule']))
	$config['mailreports']['schedule'] = array();

$a_mailreports = &$config['mailreports']['schedule'];

if (!isset($reportid) || !isset($a_mailreports[$reportid])) {
	header("Location: status_mail_report.php");
	return;
}

if (!is_array($a_mailreports[$reportid]['log']['row'])) {
	$a_mailreports[$reportid]['log'] = array();
	$a_mailreports[$reportid]['log']['row'] = array();
}
$a_logs = $a_mailreports[$reportid]['log']['row'];

if (isset($id) && $a_logs[$id]) {
	$pconfig = $a_logs[$id];
} else {
	$pconfig = array();
}

if (isset($id) && !($a_logs[$id])) {
	header("Location: status_mail_report_edit.php?id={$reportid}");
	return;
}

$logpath = "/var/log/";
chdir($logpath);
$logfiles = glob("*.log");

sort($logfiles);

if ($_POST) {
	unset($_POST['__csrf_magic']);
	$pconfig = $_POST;

	if (isset($id) && $a_logs[$id])
		$a_logs[$id] = $pconfig;
	else
		$a_logs[] = $pconfig;

	$a_mailreports[$reportid]['log']['row'] = $a_logs;

	write_config();
	header("Location: status_mail_report_edit.php?id={$reportid}");
	return;
}

$pgtitle = array(gettext("Status"), gettext("Email Reports"), gettext("Add Log"));
include("head.inc");

$form = new Form();

$section = new Form_Section('Log Settings');

$no_list_logs = array("utx.log");
$logoptions = array();
foreach ($logfiles as $logfile) {
	if (in_array($logfile, $no_list_logs)) {
		continue;
	}
	$logoptions[$logfile] = get_friendly_log_name($logfile);
}
$section->addInput(new Form_Select(
	'logfile',
	'Log',
	$pconfig['logfile'],
	$logoptions
))->setHelp('Select the log file to include in the report.');


$section->addInput(new Form_Input(
	'lines',
	'# Rows',
	'text',
	$pconfig['lines']
))->setHelp('Enter the number of rows to include in the report.');

$section->addInput(new Form_Input(
	'detail',
	'Filter',
	'text',
	$pconfig['detail']
))->setHelp('Enter some text to filter for log lines containing this string.')->setWidth(6);

$form->add($section);

$form->addGlobal(new Form_Input(
	'reportid',
	null,
	'hidden',
	$reportid
));

if (isset($id) && $a_logs[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}
print($form);
?>
<?php include("foot.inc"); ?>