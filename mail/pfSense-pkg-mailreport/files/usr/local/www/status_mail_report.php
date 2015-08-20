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

if ($_GET['act'] == "del") {
	if ($a_mailreports[$_GET['id']]) {
		$name = $a_mailreports[$_GET['id']]['descr'];
		unset($a_mailreports[$_GET['id']]);

		// Fix up cron job(s)
		set_mail_report_cron_jobs($a_mailreports);

		write_config("Removed Email Report '{$name}'");
		configure_cron();
		header("Location: status_mail_report.php");
		exit;
	}
}

$pgtitle = array(gettext("Status"),gettext("Email Reports"));
include("head.inc");
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td><div id="mainarea">
	<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr><td colspan="4">Here you can define a list of reports to be sent by email. </td></tr>
		<tr><td>&nbsp;</td></tr>
		<tr>
			<td width="34%" class="listhdr"><?=gettext("Description");?></td>
			<td width="24%" class="listhdr"><?=gettext("Schedule");?></td>
			<td width="12%" class="listhdr"><?=gettext("Commands");?></td>
			<td width="12%" class="listhdr"><?=gettext("Logs");?></td>
			<td width="12%" class="listhdr"><?=gettext("Graphs");?></td>
			<td width="6%" class="list"><a href="status_mail_report_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
		</tr>
		<?php $i = 0; foreach ($a_mailreports as $mailreport): ?>
		<tr ondblclick="document.location='status_mail_report_edit.php?id=<?=$i;?>'">
			<td class="listlr"><?php echo $mailreport['descr']; ?></td>
			<td class="listlr"><?php echo $mailreport['schedule_friendly']; ?></td>
			<td class="listlr"><?php echo count($mailreport['cmd']['row']); ?></td>
			<td class="listlr"><?php echo count($mailreport['log']['row']); ?></td>
			<td class="listlr"><?php echo count($mailreport['row']); ?></td>
			<td valign="middle" nowrap class="list">
				<a href="status_mail_report_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a>
				&nbsp;
				<a href="status_mail_report.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a>
			</td>
		</tr>
		<?php $i++; endforeach; ?>
		<tr>
			<td class="list" colspan="5"></td>
			<td class="list"><a href="status_mail_report_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
		</tr>
		<tr>
			<td colspan="3" class="list"><p class="vexpl">
				<span class="red"><strong><?=gettext("Note:");?><br></strong></span>
				<?=gettext("Click + above to add scheduled reports.");?><br/>
				Configure your SMTP settings under <a href="/system_advanced_notifications.php">System -&gt; Advanced, on the Notifications tab</a>.
			</td>
			<td class="list">&nbsp;</td>
		</tr>
	</table>
	</div></td></tr>
</table>

<?php include("fend.inc"); ?>
</body>
</html>
