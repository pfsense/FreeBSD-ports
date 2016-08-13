<?php
/*
	cron_edit.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2008 Mark J Crane
	Copyright (C) 2015 ESF, LLC
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
require_once("guiconfig.inc");
require_once("/usr/local/pkg/cron.inc");

$a_cron = &$config['cron']['item'];

$id = $_GET['id'];
if (isset($_POST['id'])) {
	$id = $_POST['id'];
}

if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'php') {
		if ($a_cron[$_GET['id']]) {
			unset($a_cron[$_GET['id']]);
			write_config();
			cron_sync_package();
			header("Location: cron.php");
			exit;
		}
	}
}

if (isset($id) && $a_cron[$id]) {
	$pconfig['minute'] = $a_cron[$id]['minute'];
	$pconfig['hour'] = $a_cron[$id]['hour'];
	$pconfig['mday'] = $a_cron[$id]['mday'];
	$pconfig['month'] = $a_cron[$id]['month'];
	$pconfig['wday'] = $a_cron[$id]['wday'];
	$pconfig['who'] = $a_cron[$id]['who'];
	$pconfig['command'] = $a_cron[$id]['command'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (!$input_errors) {

		$ent = array();
		$ent['minute'] = $_POST['minute'];
		$ent['hour'] = $_POST['hour'];
		$ent['mday'] = $_POST['mday'];
		$ent['month'] = $_POST['month'];
		$ent['wday'] = $_POST['wday'];
		$ent['who'] = $_POST['who'];
		$ent['command'] = $_POST['command'];

		if (isset($id) && $a_cron[$id]) {
			// update
			$a_cron[$id] = $ent;
		} else {
			// add
			$a_cron[] = $ent;
		}

		write_config();
		cron_sync_package();

		header("Location: cron.php");
		exit;
	}
}


if (empty($id)) {
	$pgtitle = array(gettext("Services"), gettext("Cron"), gettext("Add"));
} else {
	$pgtitle = array(gettext("Services"), gettext("Cron"), gettext("Edit"));
}
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "/packages/cron/cron.php");
if (empty($id)) {
	$tab_array[] = array(gettext("Add"), true, "/packages/cron/cron_edit.php");
} else {
	$tab_array[] = array(gettext("Edit"), true, "/packages/cron/cron_edit.php");
}
display_top_tabs($tab_array);

$form = new Form;
$section = new Form_Section('Add A Cron Schedule');

$section->addInput(new Form_Input(
	'minute',
	'Minute',
	'text',
	$pconfig['minute']
))->setHelp("The minute(s) at which the command will be executed. (0-59, ranges, or divided, *=all)");

$section->addInput(new Form_Input(
	'hour',
	'Hour',
	'text',
	$pconfig['hour']
))->setHelp("The hour(s) at which the command will be executed. (0-23, ranges, or divided, *=all)");

$section->addInput(new Form_Input(
	'mday',
	'Day of the Month',
	'text',
	$pconfig['mday']
))->setHelp("The day(s) of the month on which the command will be executed. (1-31, ranges, or divided, *=all)");

$section->addInput(new Form_Input(
	'month',
	'Month of the Year',
	'text',
	$pconfig['month']
))->setHelp("The month(s) of the year during which the command will be executed. (1-12, ranges, or divided, *=all)");

$section->addInput(new Form_Input(
	'wday',
	'Day of the Week',
	'text',
	$pconfig['wday']
))->setHelp("The day(s) of the week on which the command will be executed. (0-7, 7=Sun or use names, ranges, or divided, *=all)");

$section->addInput(new Form_Input(
	'who',
	'User',
	'text',
	$pconfig['who']
))->setHelp("The user executing the command (typically \"root\")");

$section->addInput(new Form_Textarea(
	'command',
	'Command',
	$pconfig['command']
))->setHelp("The <strong>full path</strong> to the command, plus parameters.");

$form->add($section);

$btncncl = new Form_Button(
    'cancel',
    'Cancel'
);
 
$btncncl->removeClass('btn-primary')->addClass('btn-danger');
 
$form->addGlobal($btncncl);

print $form;

?>
<div class="infoblock">
	<?=print_info_box('Using "*" for a time entry means "all" or "every", and is the same as a range from first to last. ' .
		'<br/>Ranges may also be used, for example "1-5" in the "Day of Week" field means Monday through Friday' .
		'<br/>Time entries may be divided and will be executed when they divide evenly, for example "*/15" in the Minute field means "Every 15 minutes".' .
		'<br/><br/>For more information see: <a href="http://www.freebsd.org/doc/en/books/handbook/configtuning-cron.html">FreeBSD Handbook - Configuring cron(8)</a> ' .
		'and <a href="https://www.freebsd.org/cgi/man.cgi?query=crontab&amp;sektion=5">crontab(5) man page</a>.', info)?>
</div>

<?php include("foot.inc"); ?>
