<?php
/*
 * status_mail_report_add_cmd.php
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
##|*IDENT=page-status-mailreportsaddcmd
##|*NAME=Status: Email Reports: Add Command page
##|*DESCR=Allow access to the 'Status: Email Reports: Add Command' page.
##|*MATCH=status_mail_report_add_cmd.php*
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

init_config_arr(array('mailreports', 'schedule', $reportid, 'cmd', 'row'));
$a_cmds = $a_mailreports[$reportid]['cmd']['row'];

if (isset($id) && $a_cmds[$id]) {
	$pconfig = $a_cmds[$id];
} else {
	$pconfig = array();
}

if (isset($id) && !($a_cmds[$id])) {
	header("Location: status_mail_report_edit.php?id={$reportid}");
	return;
}

if ($_POST) {
	unset($_POST['__csrf_magic'], $_POST['id'], $_POST['reportid'], $_POST['submit'], $_POST['save']);
	$pconfig = $_POST;

	if (isset($id) && $a_cmds[$id])
		$a_cmds[$id] = $pconfig;
	else
		$a_cmds[] = $pconfig;

	$a_mailreports[$reportid]['cmd']['row'] = $a_cmds;

	write_config("mailreport: Command settings saved");
	header("Location: status_mail_report_edit.php?id={$reportid}");
	return;
}

$pgtitle = array(gettext("Status"), gettext("Email Reports"), gettext("Add Command"));
include("head.inc");

$form = new Form();

$section = new Form_Section('Command Settings');

$section->addInput(new Form_Input(
	'descr',
	'Name',
	'text',
	$pconfig['descr']
))->setHelp('Enter a description here for reference.');

$section->addInput(new Form_Input(
	'detail',
	'Command',
	'text',
	$pconfig['detail']
))->setHelp('Enter the full path to a command here.')->setWidth(6);

$form->add($section);

$form->addGlobal(new Form_Input(
	'reportid',
	null,
	'hidden',
	$reportid
));

if (isset($id) && $a_cmds[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}
print($form);

?>

<div>
<?=print_info_box(gettext("Use full paths to commands to ensure they run properly. The command will be run during the report and its stdout output will be included in the report body. Be extremely careful what commands are run, the same warnings apply as those when using Diagnostics &gt; Command.") .
	"<br /><br />" .
	gettext("Do not use this solely as a way to run a command on a schedule, use the Cron package for that purpose instead."), 'warning'); ?>
</div>

<?php include("foot.inc"); ?>
