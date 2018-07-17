<?php
/*
 * status_mail_report_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2014 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-status-mailreportsedit
##|*NAME=Status: Email Reports: Edit Report page
##|*DESCR=Allow access to the 'Status: Email Reports: Edit Report' page.
##|*MATCH=status_mail_report_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("mail_reports.inc");

$cmdid = $_REQUEST['cmdid'];
$logid = $_REQUEST['logid'];
$id = $_REQUEST['id'];

if (!is_array($config['mailreports'])) {
	$config['mailreports'] = array();
}

if (!is_array($config['mailreports']['schedule'])) {
	$config['mailreports']['schedule'] = array();
}

$a_mailreports = &$config['mailreports']['schedule'];
if (isset($id) && $a_mailreports[$id]) {
	if (!is_array($a_mailreports[$id]['row']))
		$a_mailreports[$id]['row'] = array();
	$pconfig = $a_mailreports[$id];
	$a_cmds = $a_mailreports[$id]['cmd']['row'];
	$a_logs = $a_mailreports[$id]['log']['row'];
}

if (!is_array($pconfig))
	$pconfig = array();
if (!is_array($a_cmds))
	$a_cmds = array();
if (!is_array($a_logs))
	$a_logs = array();

$frequencies = array("daily", "weekly", "monthly", "quarterly", "yearly");
$daysofweek = array(
		"" => "",
		"0" => "Sunday",
		"1" => "Monday",
		"2" => "Tuesday",
		"3" => "Wednesday",
		"4" => "Thursday",
		"5" => "Friday",
		"6" => "Saturday");
$dayofmonth = array("", "1", "15");
$monthofquarter = array(
		"" => "",
		"1" => "beginning",
		"2" => "middle");
$monthofyear = array(
		"" => "",
		"1" => "January",
		"2" => "February",
		"3" => "March",
		"4" => "April",
		"5" => "May",
		"6" => "June",
		"7" => "July",
		"8" => "August",
		"9" => "September",
		"10" => "October",
		"11" => "November",
		"12" => "December");

if (isset($_POST['del'])) {
	$need_save = false;
	if (is_array($_POST['commands']) && count($_POST['commands'])) {
		foreach ($_POST['commands'] as $commandsi) {
			unset($a_cmds[$commandsi]);
			$a_mailreports[$id]['cmd']['row'] = $a_cmds;
			$need_save = true;
		}
	}
	if (is_array($_POST['logs']) && count($_POST['logs'])) {
		foreach ($_POST['logs'] as $logsi) {
			unset($a_logs[$logsi]);
			$a_mailreports[$id]['log']['row'] = $a_logs;
			$need_save = true;
		}
	}
	if ($need_save) {
		write_config();
	}
	header("Location: status_mail_report_edit.php?id={$id}");
	return;
} else {
	unset($delbtn_cmd, $delbtn_log);

	foreach ($_POST as $pn => $pd) {
		if (preg_match("/cdel_(\d+)/", $pn, $matches)) {
			$delbtn_cmd = $matches[1];
		} elseif (preg_match("/ldel_(\d+)/", $pn, $matches)) {
			$delbtn_log = $matches[1];
		}
	}
	$need_save = false;
	if (is_numeric($delbtn_cmd) && $a_cmds[$delbtn_cmd]) {
		unset($a_cmds[$delbtn_cmd]);
		$a_mailreports[$id]['cmd']['row'] = $a_cmds;
		$need_save = true;
	}
	if (is_numeric($delbtn_log) && $a_logs[$delbtn_log]) {
		unset($a_logs[$delbtn_log]);
		$a_mailreports[$id]['log']['row'] = $a_logs;
		$need_save = true;
	}
	if ($need_save) {
		write_config();
		header("Location: status_mail_report_edit.php?id={$id}");
		return;
	}
}


if ($_POST) {
	unset($_POST['__csrf_magic']);
	$pconfig = $_POST;
	if ($_POST['Submit'] == "Send Now") {
		mwexec_bg("/usr/local/bin/mail_reports_generate.php {$id}");
		header("Location: status_mail_report_edit.php?id={$id}");
		return;
	}
	$friendly = "";

	// Default to midnight if unset/invalid.
	$pconfig['timeofday'] = isset($pconfig['timeofday']) ? $pconfig['timeofday'] : 0;
	$friendlytime = sprintf("%02d:00", $pconfig['timeofday']);
	$friendly = "Daily at {$friendlytime}";

	// If weekly, check for day of week
	if ($pconfig['frequency'] == "weekly") {
		$pconfig['dayofweek'] = isset($pconfig['dayofweek']) ? $pconfig['dayofweek'] : 0;
		$friendly = "Weekly, on {$daysofweek[$pconfig['dayofweek']]} at {$friendlytime}";
	} else {
		if (isset($pconfig['dayofweek']))
			unset($pconfig['dayofweek']);
	}

	// If monthly, check for day of the month
	if ($pconfig['frequency'] == "monthly") {
		$pconfig['dayofmonth'] = isset($pconfig['dayofmonth']) ? $pconfig['dayofmonth'] : 1;
		$friendly = "Monthly, on day {$pconfig['dayofmonth']} at {$friendlytime}";
	} elseif ($pconfig['frequency'] != "yearly") {
		if (isset($pconfig['dayofmonth']))
			unset($pconfig['dayofmonth']);
	}

	// If quarterly, check for day of the month
	if ($pconfig['frequency'] == "quarterly") {
		$pconfig['monthofquarter'] = isset($pconfig['monthofquarter']) ? $pconfig['monthofquarter'] : 1;
		$friendly = "Quarterly, at the {$monthofquarter[$pconfig['monthofquarter']]}, at {$friendlytime}";
		switch ($pconfig['monthofquarter']) {
			case 2:
				$pconfig['dayofmonth'] = 15;
				$pconfig['monthofyear'] = "2,5,8,11";
				break;
			case 1:
			default:
				$pconfig['dayofmonth'] = 1;
				$pconfig['monthofyear'] = "1,4,7,10";
				break;
		}
	} else {
		if (isset($pconfig['monthofquarter']))
			unset($pconfig['monthofquarter']);
	}

	// If yearly, check for day of the month
	if ($pconfig['frequency'] == "yearly") {
		$pconfig['monthofyear'] = isset($pconfig['monthofyear']) ? $pconfig['monthofyear'] : 1;
		$friendly = "Yearly, on day {$pconfig['dayofmonth']} of {$monthofyear[$pconfig['monthofyear']]} at {$friendlytime}";
	} elseif ($pconfig['frequency'] != "quarterly") {
		if (isset($pconfig['monthofyear']))
			unset($pconfig['monthofyear']);
	}

	// Copy back into the schedule.
	if (count($a_cmds)) {
		$pconfig['cmd']["row"] = $a_cmds;
	} elseif (is_array($pconfig['cmd'])) {
		unset($pconfig['cmd']);
	}
	if (count($a_logs)) {
		$pconfig['log']["row"] = $a_logs;
	} elseif (is_array($pconfig['log'])) {
		unset($pconfig['log']);
	}

	$pconfig['schedule_friendly'] = $friendly;

	if (isset($id) && $a_mailreports[$id])
		$a_mailreports[$id] = $pconfig;
	else
		$a_mailreports[] = $pconfig;

	// Fix up cron job(s)
	set_mail_report_cron_jobs($a_mailreports);
	write_config();
	configure_cron();
	header("Location: status_mail_report.php");
	return;
}

$pgtitle = array(gettext("Status"), gettext("Email Reports"), gettext("Edit Reports"));
include("head.inc");

$form = new Form(false);

$section = new Form_Section('Report Settings');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('Enter a description here for reference.');

$form->add($section);

$section = new Form_Section('Schedule');

$freqoptions = array();
foreach ($frequencies as $freq) {
	$freqoptions[$freq] = ucwords($freq);
}
$section->addInput(new Form_Select(
	'frequency',
	'Frequency',
	$pconfig['frequency'],
	$freqoptions
))->setHelp('Select the frequency for the report to be sent via email.');

$dowoptions = array();
foreach ($daysofweek as $dowi => $dow) {
	$dowoptions[$dowi] = ucwords($dow);
}
$section->addInput(new Form_Select(
	'dayofweek',
	'Day of the Week',
	$pconfig['dayofweek'],
	$dowoptions
))->setHelp('Select the day of the week to send the report. Only valid for weekly reports.');

$domoptions = array();
foreach ($dayofmonth as $dom) {
	$domoptions[$dom] = $dom;
}
$section->addInput(new Form_Select(
	'dayofmonth',
	'Day of the Month',
	$pconfig['dayofmonth'],
	$domoptions
))->setHelp('Select the day of the month to send the report. Only valid for monthly and yearly reports.');

$moqoptions = array();
foreach ($monthofquarter as $moqi => $moq) {
	$moqoptions[$moqi] = ucwords($moq);
}
$section->addInput(new Form_Select(
	'monthofquarter',
	'Time of Quarter',
	$pconfig['monthofquarter'],
	$moqoptions
))->setHelp('Select the time of the quarter to send the report. Only valid for quarter reports.');

$moyoptions = array();
foreach ($monthofyear as $moyi => $moy) {
	$moyoptions[$moyi] = ucwords($moy);
}
$section->addInput(new Form_Select(
	'monthofyear',
	'Month of the Year',
	$pconfig['monthofyear'],
	$moyoptions
))->setHelp('Select the month of the year to send the report. Only valid for yearly reports.');


$section->addInput(new Form_Select(
	'timeofday',
	'Hour of Day',
	$pconfig['timeofday'],
	array_combine(range(0, 23, 1), range(0, 23, 1))
))->setHelp('Select the hour of the day when the report should be sent. Be aware that scheduling reports between 1am-3am can cause issues during DST switches in zones that have them. Valid for any type of report.');

$group = new Form_Group('');
$group->add(new Form_Button(
	'Submit',
	'Save'
));
if (isset($id) && $a_mailreports[$id]) {
	$group->add(new Form_Button(
		'Submit',
		'Send Now'
	));
}
$section->add($group);

$form->add($section);

if (isset($id) && $a_mailreports[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}
print($form);

$allcount = 0;
?>

<?php if (isset($id) && $a_mailreports[$id]): ?>
<form name="itemsform" method="post">
	<div class="panel panel-default" id="commandentries">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Included Commands')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<th>&nbsp;</th>
					<th width="30%"><?=gettext("Name")?></th>
					<th width="60%"><?=gettext("Command")?></th>
					<th width="10%"><?=gettext("Actions")?></th>
				</thead>
				<tbody>
			<?php $i = 0; foreach ($a_cmds as $cmd): ?>
			<tr>
				<td><input type="checkbox" id="frc<?=$i?>" name="commands[]" value="<?=$i?>" onclick="fr_bgcolor('<?=$i?>')" /></td>
				<td><?=htmlspecialchars($cmd['descr']); ?></td>
				<td><?=htmlspecialchars($cmd['detail']); ?></td>
				<td style="cursor: pointer;">
					<a class="fa fa-pencil" href="status_mail_report_add_cmd.php?reportid=<?=$id ?>&id=<?=$i?>" title="<?=gettext("Edit Command"); ?>"></a>
					<a class="fa fa-trash no-confirm" id="Xcdel_<?=$i?>" title="<?=gettext('Delete Command'); ?>"></a>
					<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="cdel_<?=$i?>" name="cdel_<?=$i?>" value="cdel_<?=$i?>" title="<?=gettext('Delete Command'); ?>">Delete Command</button>
				</td>
			</tr>
			<?php $i++; $allcount++; endforeach; ?>
				</tbody>
			</table>
		</div>
		<nav class="action-buttons">
			<a href="status_mail_report_add_cmd.php?reportid=<?=$id ?>" class="btn btn-success btn-sm">
				<i class="fa fa-plus icon-embed-btn"></i>
				<?=gettext("Add New Command")?>
			</a>
		</nav>
	</div>
	<div class="panel panel-default" id="logentries">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Included Logs')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<th>&nbsp;</th>
					<th width="30%"><?=gettext("Log")?></th>
					<th width="20%"><?=gettext("# Rows")?></th>
					<th width="40%"><?=gettext("Filter")?></th>
					<th width="10%"><?=gettext("Actions")?></th>
				</thead>
				<tbody>
			<?php $i = 0; foreach ($a_logs as $log): ?>
			<tr>
				<td><input type="checkbox" id="frl<?=$i?>" name="logs[]" value="<?=$i?>" onclick="fr_bgcolor('<?=$i?>')" /></td>
				<td><?=get_friendly_log_name($log['logfile']); ?></td>
				<td><?=$log['lines']; ?></td>
				<td><?=$log['detail']; ?></td>
				<td style="cursor: pointer;">
					<a class="fa fa-pencil" href="status_mail_report_add_log.php?reportid=<?=$id ?>&id=<?=$i?>" title="<?=gettext("Edit Log"); ?>"></a>
					<a class="fa fa-trash no-confirm" id="Xldel_<?=$i?>" title="<?=gettext('Delete Log'); ?>"></a>
					<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="ldel_<?=$i?>" name="ldel_<?=$i?>" value="ldel_<?=$i?>" title="<?=gettext('Delete Log'); ?>">Delete Log</button>
				</td>
			</tr>
			<?php $i++; $allcount++; endforeach; ?>
				</tbody>
			</table>
		</div>
		<nav class="action-buttons">
			<a href="status_mail_report_add_log.php?reportid=<?=$id ?>" class="btn btn-success btn-sm">
				<i class="fa fa-plus icon-embed-btn"></i>
				<?=gettext("Add New Log")?>
			</a>
		</nav>
	</div>
	<nav class="action-buttons">
		<br />
	<?php if ($allcount > 0): ?>
		<button type="submit" name="del" class="btn btn-danger btn-sm" value="<?=gettext("Delete Selected Items")?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext("Delete Selected Items")?>
		</button>
	<?php endif; ?>
	</nav>
</form>
<?php else: ?>
<?php print_info_box(gettext("Submit the report first, then items may be added."), 'warning'); ?>
<?php endif; ?>


<?php print_info_box(gettext("Configure SMTP settings under <a href=\"/system_advanced_notifications.php\">System -&gt; Advanced, on the Notifications tab</a>"), 'info'); ?>

<script type="text/javascript">
//<![CDATA[

events.push(function() {
	$('[id^=Xcdel_]').click(function (event) {
		if(confirm("<?=gettext('Delete this report command entry?')?>")) {
			$('#' + event.target.id.slice(1)).click();
		}
	});
	$('[id^=Xldel_]').click(function (event) {
		if(confirm("<?=gettext('Delete this report log entry?')?>")) {
			$('#' + event.target.id.slice(1)).click();
		}
	});
});

//]]>
</script>
<?php include("foot.inc"); ?>
