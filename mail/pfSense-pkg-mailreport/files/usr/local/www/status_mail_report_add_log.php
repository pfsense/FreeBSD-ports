<?php
/*
 * status_mail_report_add_log.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007-2011 Seth Mos <seth.mos@dds.nl>
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

##|+PRIV
##|*IDENT=page-status-mailreportsaddlog
##|*NAME=Status: Email Reports: Add Log page
##|*DESCR=Allow access to the 'Status: Email Reports: Add Log' page.
##|*MATCH=status_mail_report_add_log.php*
##|-PRIV

require("guiconfig.inc");

require_once('mailreport/mail_report.inc');

$reportid = $_REQUEST['reportid'];
$id = $_REQUEST['id'];

init_config_arr(array('mailreports', 'schedule'));
$a_mailreports = &$config['mailreports']['schedule'];

if (!isset($reportid) || !isset($a_mailreports[$reportid])) {
	header("Location: status_mail_report.php");
	return;
}

init_config_arr(array('mailreports', 'schedule', $reportid, 'log', 'row'));
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
$logfiles = glob("*log");

sort($logfiles);

if ($_POST) {
	unset($_POST['__csrf_magic'], $_POST['id'], $_POST['reportid'], $_POST['submit'], $_POST['save']);
	$pconfig = $_POST;

	if (isset($id) && $a_logs[$id]) {
		$a_logs[$id] = $pconfig;
	} else {
		$a_logs[] = $pconfig;
	}

	$a_mailreports[$reportid]['log']['row'] = $a_logs;

	write_config("mailreport: Logs settings updated");
	header("Location: status_mail_report_edit.php?id={$reportid}");
	return;
}

$pgtitle = array(gettext("Status"), gettext("Email Reports"), gettext("Add Log"));
include("head.inc");

$form = new Form();

$section = new Form_Section('Log Settings');

$no_list_logs = array("utx.log", "lastlog");
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
