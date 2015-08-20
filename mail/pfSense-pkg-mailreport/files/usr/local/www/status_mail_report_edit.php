<?php
/* $Id$ */
/*
	status_mail_report_edit.php
	Part of pfSense
	Copyright (C) 2011-2014 Jim Pingle <jimp@pfsense.org>
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
##|*IDENT=page-status-mailreportsedit
##|*NAME=Status: Email Reports: Edit Report page
##|*DESCR=Allow access to the 'Status: Email Reports: Edit Report' page.
##|*MATCH=status_mail_report_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("mail_reports.inc");

$cmdid = $_REQUEST['cmdid'];
$logid = $_REQUEST['logid'];
$graphid = $_REQUEST['graphid'];
$id = $_REQUEST['id'];

if (!is_array($config['mailreports']['schedule']))
	$config['mailreports']['schedule'] = array();

$a_mailreports = &$config['mailreports']['schedule'];
if (isset($id) && $a_mailreports[$id]) {
	if (!is_array($a_mailreports[$id]['row']))
		$a_mailreports[$id]['row'] = array();
	$pconfig = $a_mailreports[$id];
	$a_cmds = $a_mailreports[$id]['cmd']['row'];
	$a_logs = $a_mailreports[$id]['log']['row'];
	$a_graphs = $a_mailreports[$id]['row'];
}

if (!is_array($pconfig))
	$pconfig = array();
if (!is_array($a_cmds))
	$a_cmds = array();
if (!is_array($a_logs))
	$a_logs = array();
if (!is_array($a_graphs))
	$a_graphs = array();


if ($_GET['act'] == "del") {
	if (is_numeric($cmdid) && $a_cmds[$cmdid]) {
		unset($a_cmds[$cmdid]);
		$a_mailreports[$id]['cmd']['row'] = $a_cmds;
		write_config();
		header("Location: status_mail_report_edit.php?id={$id}");
		return;
	} elseif (is_numeric($logid) && $a_logs[$logid]) {
		unset($a_logs[$logid]);
		$a_mailreports[$id]['log']['row'] = $a_logs;
		write_config();
		header("Location: status_mail_report_edit.php?id={$id}");
		return;
	} elseif (is_numeric($graphid) && $a_graphs[$graphid]) {
		unset($a_graphs[$graphid]);
		$a_mailreports[$id]['row'] = $a_graphs;
		write_config();
		header("Location: status_mail_report_edit.php?id={$id}");
		return;
	}
}

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
	$pconfig['cmd']["row"] = $a_cmds;
	$pconfig['log']["row"] = $a_logs;
	$pconfig["row"] = $a_graphs;

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

$pgtitle = array(gettext("Status"),gettext("Edit Email Reports"));
include("head.inc");
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td><div id="mainarea">
	<form action="status_mail_report_edit.php" method="post" name="iform" id="iform">
	<table class="tabcont" width="100%" border="0" cellpadding="1" cellspacing="1">
		<tr>
			<td class="listtopic" colspan="4">General Settings</td>
			<td></td>
		</tr>
		<tr>
			<td valign="top" class="vncell"><?=gettext("Description");?></td>
			<td class="vtable" colspan="3">
				<input name="descr" type="text" class="formfld unknown" id="descr" size="60" value="<?=htmlspecialchars($pconfig['descr']);?>">
			</td>
			<td></td>
		</tr>
		<tr>
			<td class="listtopic" colspan="4">Report Schedule</td>
			<td></td>
		</tr>
		<tr>
			<td class="vncellreq" valign="top" colspan="1">Frequency</td>
			<td class="vtable" colspan="3">
			<select name="frequency">
			<?php foreach($frequencies as $freq): ?>
				<option value="<?php echo $freq; ?>" <?php if($pconfig["frequency"] === $freq) echo "selected"; ?>><?php echo ucwords($freq); ?></option>
			<?php endforeach; ?>
			</select>
			<br/>Select the frequency for the report to be sent via email.
			<br/>
			</td>
			<td></td>
		</tr>
		<tr>
			<td class="vncell" valign="top" colspan="1">Day of the Week</td>
			<td class="vtable" colspan="3">
			<select name="dayofweek">
			<?php foreach($daysofweek as $dowi => $dow): ?>
				<option value="<?php echo $dowi; ?>" <?php if($pconfig["dayofweek"] == $dowi) echo "selected"; ?>><?php echo ucwords($dow); ?></option>
			<?php endforeach; ?>
			</select>
			<br/>Select the day of the week to send the report. Only valid for weekly reports.
			<br/>
			</td>
			<td></td>
		</tr>
		<tr>
			<td class="vncell" valign="top" colspan="1">Day of the Month</td>
			<td class="vtable" colspan="3">
			<select name="dayofmonth">
			<?php foreach($dayofmonth as $dom): ?>
				<option value="<?php echo $dom; ?>" <?php if($pconfig["dayofmonth"] == $dom) echo "selected"; ?>><?php echo $dom; ?></option>
			<?php endforeach; ?>
			</select>
			<br/>Select the day of the month to send the report. Only valid for monthly and yearly reports.
			<br/>
			</td>
			<td></td>
		</tr>
		<tr>
			<td class="vncell" valign="top" colspan="1">Time of Quarter</td>
			<td class="vtable" colspan="3">
			<select name="monthofquarter">
			<?php foreach($monthofquarter as $moqi => $moq): ?>
				<option value="<?php echo $moqi; ?>" <?php if($pconfig["monthofquarter"] == $moqi) echo "selected"; ?>><?php echo $moq; ?></option>
			<?php endforeach; ?>
			</select>
			<br/>Select the time of the quarter to send the report. Only valid for quarter reports.
			<br/>
			</td>
			<td></td>
		</tr>
		<tr>
			<td class="vncell" valign="top" colspan="1">Month of the Year</td>
			<td class="vtable" colspan="3">
			<select name="monthofyear">
			<?php foreach($monthofyear as $moyi => $moy): ?>
				<option value="<?php echo $moyi; ?>" <?php if($pconfig["monthofyear"] == $moyi) echo "selected"; ?>><?php echo $moy; ?></option>
			<?php endforeach; ?>
			</select>
			<br/>Select the month of the year to send the report. Only valid for yearly reports.
			<br/>
			</td>
			<td></td>
		</tr>
		<tr>
			<td class="vncell" valign="top" colspan="1">Hour of Day</td>
			<td class="vtable" colspan="3">
			<select name="timeofday">
				<option value="" <?php if($pconfig["timeofday"] == "") echo "selected"; ?>></option>
				<?php for($i=0; $i < 24; $i++): ?>
				<option value="<?php echo $i; ?>" <?php if("{$pconfig['timeofday']}" == "{$i}") echo "selected"; ?>><?php echo $i; ?></option>
				<?php endfor; ?>
			</select>
			<br/>Select the hour of the day when the report should be sent. Be aware that scheduling reports between 1am-3am can cause issues during DST switches in zones that have them. Valid for any type of report.
			<br/>
			</td>
			<td></td>
		</tr>
		<tr>
			<td class="listtopic" colspan="4">Report Commands</td>
			<td></td>
		</tr>
		<tr>
			<td width="30%" class="listhdr"><?=gettext("Name");?></td>
			<td width="60%" colspan="3" class="listhdr"><?=gettext("Command");?></td>
			<td width="10%" class="list">
			<?php if (isset($id) && $a_mailreports[$id]): ?>
				<a href="status_mail_report_add_cmd.php?reportid=<?php echo $id ;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a>
			</td>
			<?php else: ?>
			</td>
				<tr><td colspan="5" align="center"><br/>Save the report first, then items may be added.<br/></td></tr>
			<?php endif; ?>
		</tr>
		<?php $i = 0; foreach ($a_cmds as $cmd): ?>
		<tr ondblclick="document.location='status_mail_report_add_cmd.php?reportid=<?php echo $id ;?>&amp;id=<?=$i;?>'">
			<td class="listlr"><?php echo htmlspecialchars($cmd['descr']); ?></td>
			<td colspan="3" class="listlr"><?php echo htmlspecialchars($cmd['detail']); ?></td>
			<td valign="middle" nowrap class="list">
				<a href="status_mail_report_add_cmd.php?reportid=<?php echo $id ;?>&id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a>
				&nbsp;
				<a href="status_mail_report_edit.php?act=del&id=<?php echo $id ;?>&cmdid=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a>
			</td>
		</tr>
		<?php $i++; endforeach; ?>
		<tr>
			<td class="list" colspan="4"></td>
			<td class="list">
			<?php if (isset($id) && $a_mailreports[$id]): ?>
				<a href="status_mail_report_add_cmd.php?reportid=<?php echo $id ;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a>
			<?php endif; ?>
			</td>
		</tr>
		<tr>
			<td class="listtopic" colspan="4">Report Logs</td>
			<td></td>
		</tr>
		<tr>
			<td width="30%" class="listhdr"><?=gettext("Log");?></td>
			<td width="20%" class="listhdr"><?=gettext("# Rows");?></td>
			<td width="40%" colspan="2" class="listhdr"><?=gettext("Filter");?></td>
			<td width="10%" class="list">
			<?php if (isset($id) && $a_mailreports[$id]): ?>
				<a href="status_mail_report_add_log.php?reportid=<?php echo $id ;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a>
			</td>
			<?php else: ?>
			</td>
				<tr><td colspan="5" align="center"><br/>Save the report first, then items may be added.<br/></td></tr>
			<?php endif; ?>
		</tr>
		<?php $i = 0; foreach ($a_logs as $log): ?>
		<tr ondblclick="document.location='status_mail_report_add_log.php?reportid=<?php echo $id ;?>&amp;id=<?=$i;?>'">
			<td class="listlr"><?php echo get_friendly_log_name($log['logfile']); ?></td>
			<td class="listlr"><?php echo $log['lines']; ?></td>
			<td colspan="2" class="listlr"><?php echo $log['detail']; ?></td>
			<td valign="middle" nowrap class="list">
				<a href="status_mail_report_add_log.php?reportid=<?php echo $id ;?>&id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a>
				&nbsp;
				<a href="status_mail_report_edit.php?act=del&id=<?php echo $id ;?>&logid=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a>
			</td>
		</tr>
		<?php $i++; endforeach; ?>
		<tr>
			<td class="list" colspan="4"></td>
			<td class="list">
			<?php if (isset($id) && $a_mailreports[$id]): ?>
				<a href="status_mail_report_add_log.php?reportid=<?php echo $id ;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a>
			<?php endif; ?>
			</td>
		</tr>
		<tr>
			<td class="listtopic" colspan="4">Report Graphs</td>
			<td></td>
		</tr>
		<tr>
			<td width="30%" class="listhdr"><?=gettext("Graph");?></td>
			<td width="20%" class="listhdr"><?=gettext("Style");?></td>
			<td width="20%" class="listhdr"><?=gettext("Time Span");?></td>
			<td width="20%" class="listhdr"><?=gettext("Period");?></td>
			<td width="10%" class="list">
			<?php if (isset($id) && $a_mailreports[$id]): ?>
				<a href="status_mail_report_add_graph.php?reportid=<?php echo $id ;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a>
			</td>
			<?php else: ?>
			</td>
				<tr><td colspan="5" align="center"><br/>Save the report first, then items may be added.<br/></td></tr>
			<?php endif; ?>
		</tr>
		<?php $i = 0; foreach ($a_graphs as $graph): 
			$optionc = explode("-", $graph['graph']);
			$optionc[1] = str_replace(".rrd", "", $optionc[1]);
			$friendly = convert_friendly_interface_to_friendly_descr(strtolower($optionc[0]));
			if(!empty($friendly)) {
				$optionc[0] = $friendly;
			}
			$prettyprint = ucwords(implode(" :: ", $optionc));
		?>
		<tr ondblclick="document.location='status_mail_report_add_graph.php?reportid=<?php echo $id ;?>&amp;id=<?=$i;?>'">
			<td class="listlr"><?php echo $prettyprint; ?></td>
			<td class="listlr"><?php echo $graph['style']; ?></td>
			<td class="listlr"><?php echo $graph['timespan']; ?></td>
			<td class="listlr"><?php echo $graph['period']; ?></td>
			<td valign="middle" nowrap class="list">
				<a href="status_mail_report_add_graph.php?reportid=<?php echo $id ;?>&id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a>
				&nbsp;
				<a href="status_mail_report_edit.php?act=del&id=<?php echo $id ;?>&graphid=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a>
			</td>
		</tr>
		<?php $i++; endforeach; ?>
		<tr>
			<td class="list" colspan="4"></td>
			<td class="list">
			<?php if (isset($id) && $a_mailreports[$id]): ?>
				<a href="status_mail_report_add_graph.php?reportid=<?php echo $id ;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a>
			<?php endif; ?>
			</td>
		</tr>
		<tr>
			<td colspan="4" align="center">
			<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>">
			<?php if (isset($id) && $a_mailreports[$id]): ?>
			<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Send Now");?>">
			<?php endif; ?>
			<a href="status_mail_report.php"><input name="cancel" type="button" class="formbtn" value="<?=gettext("Cancel");?>"></a>
			<?php if (isset($id) && $a_mailreports[$id]): ?>
			<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
			<?php endif; ?>
			</td>
			<td></td>
		</tr>
		<tr>
			<td colspan="4" class="list"><p class="vexpl">
				<span class="red"><strong><?=gettext("Note:");?><br></strong></span>
				<?=gettext("Click + above to add graphs to this report.");?><br/>
				Configure your SMTP settings under <a href="/system_advanced_notifications.php">System -&gt; Advanced, on the Notifications tab</a>.
			</td>
			<td class="list">&nbsp;</td>
		</tr>
	</table>
	</form>
	</div></td></tr>
</table>

<?php include("fend.inc"); ?>
</body>
</html>
