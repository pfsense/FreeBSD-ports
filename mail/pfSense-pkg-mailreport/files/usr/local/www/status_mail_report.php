<?php
/* $Id$ */
/*
	status_mail_report.php
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
##|*IDENT=page-status-mailreports
##|*NAME=Status: Email Reports page
##|*DESCR=Allow access to the 'Status: Email Reports' page.
##|*MATCH=status_mail_report.php*
##|-PRIV

require("guiconfig.inc");
require_once("mail_reports.inc");

if (!is_array($config['mailreports']['schedule']))
	$config['mailreports']['schedule'] = array();

$a_mailreports = &$config['mailreports']['schedule'];

if (isset($_POST['del'])) {
	if (is_array($_POST['reports']) && count($_POST['reports'])) {
		foreach ($_POST['reports'] as $reportsi) {
			unset($a_mailreports[$reportsi]);
			set_mail_report_cron_jobs($a_mailreports);
		}
		write_config("Removed Multiple Email Reports");
		configure_cron();
		header("Location: status_mail_report.php");
		exit;
	}
} else {
	unset($delbtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/del_(\d+)/", $pn, $matches)) {
			$delbtn = $matches[1];
		}
	}

	if (isset($delbtn)) {
		if ($a_mailreports[$delbtn]) {
			$name = $a_mailreports[$delbtn]['descr'];
			unset($a_mailreports[$delbtn]);

			// Fix up cron job(s)
			set_mail_report_cron_jobs($a_mailreports);

			write_config("Removed Email Report '{$name}'");
			configure_cron();
			header("Location: status_mail_report.php");
			exit;
		}
	}
}


$pgtitle = array(gettext("Status"), gettext("Email Reports"), gettext("Add Log"));
include("head.inc");
?>

<form name="mainform" method="post">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext('Email Reports')?></h2>
			<?=gettext("Define reports to by sent periodically via email.");?>
		</div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<th>&nbsp;</th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Schedule")?></th>
					<th><?=gettext("Commands")?></th>
					<th><?=gettext("Logs")?></th>
					<th><?=gettext("Actions")?></th>
				</thead>
				<tbody class="services">

		<?php $i = 0; foreach ($a_mailreports as $mailreport): ?>
		<tr>
			<td><input type="checkbox" id="frc<?=$i?>" name="reports[]" value="<?=$i?>" onclick="fr_bgcolor('<?=$i?>')" /></td>
			<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>" ondblclick="document.location='status_mail_report_edit.php?id=<?=$i?>';">
				<?=$mailreport['descr']; ?>
			</td>
			<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>" ondblclick="document.location='status_mail_report_edit.php?id=<?=$i?>';">
				<?=$mailreport['schedule_friendly']; ?>
			</td>
			<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>" ondblclick="document.location='status_mail_report_edit.php?id=<?=$i?>';">
				<?=count($mailreport['cmd']['row']); ?>
			</td>
			<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>" ondblclick="document.location='status_mail_report_edit.php?id=<?=$i?>';">
				<?=count($mailreport['log']['row']); ?>
			</td>
			<td style="cursor: pointer;">
				<a class="fa fa-pencil" href="status_mail_report_edit.php?id=<?=$i?>" title="<?=gettext("Edit Report"); ?>"></a>
				<a class="fa fa-trash no-confirm" id="Xdel_<?=$i?>" title="<?=gettext('Delete Report'); ?>"></a>
				<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="del_<?=$i?>" name="del_<?=$i?>" value="del_<?=$i?>" title="<?=gettext('Delete Report'); ?>">Delete</button>
			</td>
		</tr>
		<?php $i++; endforeach; ?>

				</tbody>
			</table>
		</div>
	</div>
	<nav class="action-buttons">
		<br />
		<a href="status_mail_report_edit.php" class="btn btn-success btn-sm">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Add New Report")?>
		</a>
<?php if ($i !== 0): ?>
		<button type="submit" name="del" class="btn btn-danger btn-sm" value="<?=gettext("Delete Selected Reports")?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext("Delete")?>
		</button>
<?php endif; ?>
	</nav>
</form>
<?php print_info_box(gettext("Configure SMTP settings under <a href=\"/system_advanced_notifications.php\">System -&gt; Advanced, on the Notifications tab</a>"), 'info'); ?>
<script type="text/javascript">
//<![CDATA[

events.push(function() {
	$('[id^=Xdel_]').click(function (event) {
		if(confirm("<?=gettext('Delete this report?')?>")) {
			$('#' + event.target.id.slice(1)).click();
		}
	});
});
//]]>
</script>
<?php include("foot.inc"); ?>
