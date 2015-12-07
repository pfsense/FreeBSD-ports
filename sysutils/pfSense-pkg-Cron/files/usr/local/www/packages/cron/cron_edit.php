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

$pgtitle = array(gettext("Cron"),gettext("Edit"));
include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[
function show_advanced_config() {
	document.getElementById("showadvancedbox").innerHTML = '';
	aodiv = document.getElementById('showadvanced');
	aodiv.style.display = "block";
//]]>
</script>

<?php if ($input_errors) print_input_errors($input_errors); ?>

<table summary="mainlevel">
	<tr>
		<td class="tabnavtbl">
		<?php
			$tab_array = array();
			$tab_array[] = array(gettext("Settings"), false, "/packages/cron/cron.php");
			$tab_array[] = array(gettext("Edit"), true, "/packages/cron/cron_edit.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
</table>
<?php

$form = new Form;
$section = new Form_Section('Add A Cron Schedule');

$section->addInput(new Form_Input(
	'minute',
	'minute',
	'text',
	htmlspecialchars($pconfig['minute'])
));

$section->addInput(new Form_Input(
	'hour',
	'hour',
	'text',
	htmlspecialchars($pconfig['hour'])
));

$section->addInput(new Form_Input(
	'mday',
	'mday',
	'text',
	htmlspecialchars($pconfig['mday'])
));

$section->addInput(new Form_Input(
	'month',
	'month',
	'text',
	htmlspecialchars($pconfig['month'])
));

$section->addInput(new Form_Input(
	'wday',
	'wday',
	'text',
	htmlspecialchars($pconfig['wday'])
));

$section->addInput(new Form_Input(
	'who',
	'who',
	'text',
	htmlspecialchars($pconfig['who'])
));

$section->addInput(new Form_Textarea(
	'command',
	'command',
	htmlspecialchars($pconfig['command'])
));

$form->add($section);

$btncncl = new Form_Button(
    'cancel',
    'Cancel'
);
 
$btncncl->removeClass('btn-primary')->addClass('btn-danger');
 
$form->addGlobal($btncncl);

print $form;

?>

<?php include("foot.inc"); ?>