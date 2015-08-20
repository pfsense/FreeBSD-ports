<?php
/*
	cron.php
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

if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'php') {
		if ($a_cron[$_GET['id']]) {
			unset($a_cron[$_GET['id']]);
			write_config();
			header("Location: cron.php");
			exit;
		}
	}
}

$pgtitle = array(gettext("Cron"), gettext("Settings"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

<div id="mainlevel">
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="tabs">
<tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Settings"), true, "/packages/cron/cron.php");
	$tab_array[] = array(gettext("Edit"), false, "/packages/cron/cron_edit.php");
	display_top_tabs($tab_array);
?>
</td></tr>
</table>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="content">
<tr><td class="tabcont" >
	<form action="cron.php" method="post" name="iform" id="iform">
	<?php
	if ($config_change == 1) {
		write_config();
		$config_change = 0;
	}
	?>
	<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="title">
	<tr><td>
		<div>
		Cron controls the scheduling of commands.<br /><br />
		For more information see: <a href='http://www.freebsd.org/doc/en/books/handbook/configtuning-cron.html'>http://www.freebsd.org/doc/en/books/handbook/configtuning-cron.html</a>
		</div>
	</td></tr>
	</table>
	<br />

	<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="heading">
	<tr>
		<td width="5%" class="listhdrr">minute</td>
		<td width="5%" class="listhdrr">hour</td>
		<td width="5%" class="listhdrr">mday</td>
		<td width="5%" class="listhdrr">month</td>
		<td width="5%" class="listhdrr">wday</td>
		<td width="5%" class="listhdrr">who</td>
		<td width="60%" class="listhdr">command</td>
		<td width="10%" class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="icons">
			<tr>
				<td width="17"></td>
				<td valign="middle"><a href="cron_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="edit" /></a></td>
			</tr>
			</table>
		</td>
	</tr>


	<?php
		$i = 0;
		if (count($a_cron) > 0) {
			foreach ($a_cron as $ent) {
	?>
				<tr>
					<td class="listr" ondblclick="document.location='cron_edit.php?id=<?=$i;?>';"><?=$ent['minute'];?>&nbsp;</td>
					<td class="listr" ondblclick="document.location='cron_edit.php?id=<?=$i;?>';"><?=$ent['hour'];?>&nbsp;</td>
					<td class="listr" ondblclick="document.location='cron_edit.php?id=<?=$i;?>';"><?=$ent['mday'];?>&nbsp;</td>
					<td class="listr" ondblclick="document.location='cron_edit.php?id=<?=$i;?>';"><?=$ent['month'];?>&nbsp;</td>
					<td class="listr" ondblclick="document.location='cron_edit.php?id=<?=$i;?>';"><?=$ent['wday'];?>&nbsp;</td>
					<td class="listr" ondblclick="document.location='cron_edit.php?id=<?=$i;?>';"><?=$ent['who'];?>&nbsp;</td>
					<td class="listr" ondblclick="document.location='cron_edit.php?id=<?=$i;?>';"><?=$ent['command'];?>&nbsp;</td>
					<td valign="middle" style="white-space:nowrap" class="list">
						<table border="0" cellspacing="0" cellpadding="1" summary="edit delete">
						<tr>
							<td valign="middle"><a href="cron_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" alt="edit" /></a></td>
							<td><a href="cron_edit.php?type=php&amp;act=del&amp;id=<?=$i;?>" onclick="return confirm('Do you really want to delete this?')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="delete" /></a></td>
						</tr>
						</table>
					</td>
				</tr>
	<?php
		$i++;
			}
		}
	?>

	<tr>
		<td class="list" colspan="7"></td>
		<td class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="add">
			<tr>
				<td width="17"></td>
				<td valign="middle"><a href="cron_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a></td>
			</tr>
			</table>
		</td>
	</tr>

	<tr>
		<td class="list" colspan="8"></td>
		<td class="list"></td>
	</tr>
	</table>

	</form>
</td></tr>
</table>

</div>

<?php include("fend.inc"); ?>
</body>
</html>
