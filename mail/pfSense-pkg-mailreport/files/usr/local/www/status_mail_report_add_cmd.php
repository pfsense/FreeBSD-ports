<?php
/* $Id$ */
/*
	status_mail_report_add_cmd.php
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
##|*IDENT=page-status-mailreportsaddcmd
##|*NAME=Status: Email Reports: Add Command page
##|*DESCR=Allow access to the 'Status: Email Reports: Add Command' page.
##|*MATCH=status_mail_report_add_cmd.php*
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

if (!is_array($a_mailreports[$reportid]['cmd']['row'])) {
	$a_mailreports[$reportid]['cmd'] = array();
	$a_mailreports[$reportid]['cmd']['row'] = array();
}
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
	unset($_POST['__csrf_magic']);
	$pconfig = $_POST;

	if (isset($id) && $a_cmds[$id])
		$a_cmds[$id] = $pconfig;
	else
		$a_cmds[] = $pconfig;

	$a_mailreports[$reportid]['cmd']['row'] = $a_cmds;

	write_config();
	header("Location: status_mail_report_edit.php?id={$reportid}");
	return;
}


$pgtitle = array(gettext("Status"),gettext("Add Email Report Command"));
include("head.inc");
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td><div id="mainarea">
	<form action="status_mail_report_add_cmd.php" method="post" name="iform" id="iform">
	<table class="tabcont" width="100%" border="0" cellpadding="1" cellspacing="1">
		<tr>
			<td class="listtopic" colspan="2">Command Settings</td>
		</tr>
		<tr>
			<td width="20%" class="listhdr">
				<?=gettext("Name:");?>
			</td>
			<td width="80%" class="listhdr">
				<input name="descr" type="text" class="formfld unknown" id="descr" size="20" value="<?=htmlspecialchars($pconfig['descr']);?>">
			</td>
		</tr>
		<tr>
			<td class="listhdr">
				<?=gettext("Command:");?>
			</td>
			<td class="listhdr">
				<input name="detail" type="text" class="formfld unknown" id="detail" size="60" value="<?=htmlspecialchars($pconfig['detail']);?>">
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td>
				<br/>NOTE: Use full paths to commands to ensure they run properly. The command will be run during the report and its stdout output will be included in the report body. Be extremely careful what commands you choose to run, the same warnings apply as those when using Diagnostics &gt; Command.
				<br/>
				<br/>Do not use this solely as a way to run a command on a schedule, use the Cron package for that purpose instead.
			</td>
		</tr>
		<tr>
			<td colspan="2" align="center">
			<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>">
			<a href="status_mail_report_edit.php?id=<?php echo $reportid;?>"><input name="cancel" type="button" class="formbtn" value="<?=gettext("Cancel");?>"></a>
			<input name="reportid" type="hidden" value="<?=htmlspecialchars($reportid);?>">
			<?php if (isset($id) && $a_cmds[$id]): ?>
			<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
			<?php endif; ?>
			</td>
			<td></td>
		</tr>
	</table>
	</form>
	</div></td></tr>
</table>

<?php include("fend.inc"); ?>
</body>
</html>
