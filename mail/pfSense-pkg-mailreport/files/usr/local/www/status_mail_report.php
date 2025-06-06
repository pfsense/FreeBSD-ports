<?php
/*
 * status_mail_report.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2025 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-status-mailreports
##|*NAME=Status: Email Reports page
##|*DESCR=Allow access to the 'Status: Email Reports' page.
##|*MATCH=status_mail_report.php*
##|-PRIV

require("guiconfig.inc");

require_once('mailreport/mail_report.inc');

if (isset($_POST['del'])) {
	if (is_array($_POST['reports']) && count($_POST['reports'])) {
		foreach ($_POST['reports'] as $reportsi) {
			config_del_path("mailreports/schedule/{$reportsi}");
		}
		set_mail_report_cron_jobs(config_get_path('mailreports/schedule', []));
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
		$mailreports_item_config_temp = config_get_path("mailreports/schedule/{$delbtn}");
		if ($mailreports_item_config_temp) {
			config_del_path("mailreports/schedule/{$delbtn}");

			// Fix up cron job(s)
			set_mail_report_cron_jobs(config_get_path('mailreports/schedule', []));

			write_config("Removed Email Report '{$mailreports_item_config_temp['descr']}'");
			configure_cron();
			header("Location: status_mail_report.php");
			exit;
		}
	}
}


$pgtitle = array(gettext("Status"), gettext("Email Reports"));
include("head.inc");
?>

<form name="mainform" method="post">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext('Email Reports')?></h2>
			<?=gettext("Define reports to be sent periodically via email.");?>
		</div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<th>&nbsp;</th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Schedule")?></th>
					<th><?=gettext("Skippable")?></th>
					<th><?=gettext("Commands")?></th>
					<th><?=gettext("Logs")?></th>
					<th><?=gettext("Actions")?></th>
				</thead>
				<tbody class="services">

<?php
		$i = 0;
		foreach (config_get_path('mailreports/schedule', []) as $mailreport):
			if (!is_array($mailreport)) {
				$mailreport = array();
			}

			if (empty($mailreport['text']) &&
			    !empty($mailreport['schedule_friendly'])) {
				$friendly = $mailreport['schedule_friendly'];
			} elseif (!empty($mailreport['text'])) {
				$friendly = $mailreport['text'];
			} else {
				$friendly = "-";
			}

			array_init_path($mailreport, 'cmd/row');
			array_init_path($mailreport, 'log/row');
?>
		<tr>
			<td><input type="checkbox" id="frc<?=$i?>" name="reports[]" value="<?=$i?>" onclick="fr_bgcolor('<?=$i?>')" /></td>
			<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>" ondblclick="document.location='status_mail_report_edit.php?id=<?=$i?>';">
				<?=$mailreport['descr'];?>
			</td>
			<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>" ondblclick="document.location='status_mail_report_edit.php?id=<?=$i?>';">
				<?=$friendly;?>
			</td>
			<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>" ondblclick="document.location='status_mail_report_edit.php?id=<?=$i?>';">
				<?=ucfirst($mailreport['skipifempty']); ?>
			</td>
			<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>" ondblclick="document.location='status_mail_report_edit.php?id=<?=$i?>';">
				<?=(is_array($mailreport['cmd']['row']) ? count($mailreport['cmd']['row']) : 0);?>
			</td>
			<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>" ondblclick="document.location='status_mail_report_edit.php?id=<?=$i?>';">
				<?=(is_array($mailreport['log']['row']) ? count($mailreport['log']['row']) : 0);?>
			</td>
			<td style="cursor: pointer;">
				<a class="fa-solid fa-pencil" href="status_mail_report_edit.php?id=<?=$i?>" title="<?=gettext("Edit Report"); ?>"></a>
				<a class="fa-solid fa-trash-can no-confirm" id="Xdel_<?=$i?>" title="<?=gettext('Delete Report');?>"></a>
				<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="del_<?=$i?>" name="del_<?=$i?>" value="del_<?=$i?>" title="<?=gettext('Delete Report'); ?>">Delete</button>
			</td>
		</tr>
<?php
		$i++;
		endforeach;
?>

				</tbody>
			</table>
		</div>
	</div>
	<nav class="action-buttons">
		<br />
		<a href="status_mail_report_edit.php" class="btn btn-success btn-sm">
			<i class="fa-solid fa-plus icon-embed-btn"></i>
			<?=gettext("Add New Report")?>
		</a>
<?php if ($i !== 0): ?>
		<button type="submit" name="del" class="btn btn-danger btn-sm" value="<?=gettext("Delete Selected Reports")?>">
			<i class="fa-solid fa-trash-can icon-embed-btn"></i>
			<?=gettext("Delete")?>
		</button>
<?php endif; ?>
	</nav>
</form>
<?php print_info_box(gettext("Configure SMTP settings at <a href=\"/system_advanced_notifications.php\">System &gt; Advanced, Notifications tab</a>"), 'info'); ?>
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
