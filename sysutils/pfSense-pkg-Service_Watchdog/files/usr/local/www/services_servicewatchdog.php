<?php
/*
	services_servicewatchdog.php
	Copyright (C) 2013 Jim Pingle
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
##|*IDENT=page-services-servicewatchdog
##|*NAME=Services: Service Watchdog
##|*DESCR=Allow access to the 'Services: Service Watchdog' page.
##|*MATCH=services_servicewatchdog.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("service-utils.inc");
require_once("servicewatchdog.inc");

if (!is_array($config['installedpackages']['servicewatchdog']['item'])) {
	$config['installedpackages']['servicewatchdog']['item'] = array();
}

$a_pwservices = &$config['installedpackages']['servicewatchdog']['item'];

/* if a custom message has been passed along, lets process it */
if ($_GET['savemsg']) {
	$savemsg = $_GET['savemsg'];
}

if ($_GET['act'] == "del") {
	if ($a_pwservices[$_GET['id']]) {
		unset($a_pwservices[$_GET['id']]);
		servicewatchdog_cron_job();
		write_config();
		header("Location: services_servicewatchdog.php");
		return;
	}
}

if (isset($_POST['Update'])) {
	/* update selected services */
	if (is_array($_POST['notifies']) && count($_POST['notifies'])) {
		/* Check each service and set the notify flag only for those chosen, remove those that are unset. */
		foreach ($a_pwservices as $idx => $thisservice) {
			if (!is_array($thisservice)) {
				continue;
			}
			if (in_array($idx, $_POST['notifies'])) {
				$a_pwservices[$idx]['notify'] = true;
			} else {
				if (isset($a_pwservices[$idx]['notify'])) {
					unset($a_pwservices[$idx]['notify']);
				}
			}
		}
	} else { /* No notifies selected, remove them all. */
		foreach ($a_pwservices as $idx => $thisservice) {
			unset($a_pwservices[$idx]['notify']);
		}
	}
	servicewatchdog_cron_job();
	write_config();
	header("Location: services_servicewatchdog.php");
	return;
}

if (isset($_POST['del_x'])) {
	/* delete selected services */
	if (is_array($_POST['pwservices']) && count($_POST['pwservices'])) {
		foreach ($_POST['pwservices'] as $servicei) {
			unset($a_pwservices[$servicei]);
		}
		servicewatchdog_cron_job();
		write_config();
		header("Location: services_servicewatchdog.php");
		return;
	}
} else {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
	unset($movebtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/move_(\d+)_x/", $pn, $matches)) {
			$movebtn = $matches[1];
			break;
		}
	}
	/* move selected services before this service */
	if (isset($movebtn) && is_array($_POST['pwservices']) && count($_POST['pwservices'])) {
		$a_pwservices_new = array();

		/* copy all services < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['pwservices'])) {
				$a_pwservices_new[] = $a_pwservices[$i];
			}
		}

		/* copy all selected services */
		for ($i = 0; $i < count($a_pwservices); $i++) {
			if ($i == $movebtn) {
				continue;
			}
			if (in_array($i, $_POST['pwservices'])) {
				$a_pwservices_new[] = $a_pwservices[$i];
			}
		}

		/* copy $movebtn service */
		if ($movebtn < count($a_pwservices)) {
			$a_pwservices_new[] = $a_pwservices[$movebtn];
		}

		/* copy all services > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_pwservices); $i++) {
			if (!in_array($i, $_POST['pwservices'])) {
				$a_pwservices_new[] = $a_pwservices[$i];
			}
		}
		$a_pwservices = $a_pwservices_new;
		servicewatchdog_cron_job();
		write_config();
		header("Location: services_servicewatchdog.php");
		return;
	}
}

$closehead = false;
$pgtitle = array(gettext("Services"), gettext("Service Watchdog"));
include("head.inc");

?>
<script type="text/javascript" src="/javascript/domTT/domLib.js"></script>
<script type="text/javascript" src="/javascript/domTT/domTT.js"></script>
<script type="text/javascript" src="/javascript/domTT/behaviour.js"></script>
<script type="text/javascript" src="/javascript/domTT/fadomatic.js"></script>

<link type="text/css" rel="stylesheet" href="/javascript/chosen/chosen.css" />
</head>
<body link="#000000" vlink="#000000" alink="#000000">
<?php include("fbegin.inc"); ?>
<form action="services_servicewatchdog.php" method="post" name="iform">
<script type="text/javascript" language="javascript" src="/javascript/row_toggle.js"></script>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="services to monitor">
<tr><td><div id="mainarea">
<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
	<tr>
		<td colspan="8" align="center">
			<?php echo gettext("This page allows you to select services to be monitored so that they may be automatically restarted if they crash or are stopped."); ?>
			<br/><br/>
		</td>
	</tr>
	<tr id="frheader">
		<td width="5%" class="list">&nbsp;</td>
		<td width="5%" class="listhdrr">Notify</td>
		<td width="30%" class="listhdrr"><?=gettext("Service Name");?></td>
		<td width="60%" class="listhdrr"><?=gettext("Description");?></td>
		<td width="5%" class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="buttons">
				<tr>
					<td width="17">
					<?php if (count($a_pwservices) == 0): ?>
						<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?=gettext("delete selected services");?>" border="0" alt="delete" />
					<?php else: ?>
						<input name="del" type="image" src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" title="<?=gettext("delete selected services"); ?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected services?");?>')" />
					<?php endif; ?>
					</td>
					<td><a href="services_servicewatchdog_add.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add new service"); ?>" alt="add" /></a></td>
				</tr>
			</table>
		</td>
	</tr>

<?php
$nservices = $i = 0;
foreach ($a_pwservices as $thisservice):
?>
	<tr valign="top" id="fr<?=$nservices;?>">
		<td class="listt"><input type="checkbox" id="frc<?=$nservices;?>" name="pwservices[]" value="<?=$i;?>" onClick="fr_bgcolor('<?=$nservices;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" /></td>
		<td class="listlr"><input type="checkbox" id="notify<?=$nservices;?>" name="notifies[]" value="<?=$i;?>" style="margin: 0; padding: 0; width: 15px; height: 15px;" <?PHP if (isset($thisservice['notify'])) echo 'checked="CHECKED"';?>/></td>
		<td class="listr" onclick="fr_toggle(<?=$nservices;?>)" id="frd<?=$nservices;?>" ondblclick="document.location='services_servicewatchdog_add.php?id=<?=$nservices;?>';">
			<?=$thisservice['name'];?>
		</td>
		<td class="listr" onclick="fr_toggle(<?=$nservices;?>)" id="frd<?=$nservices;?>" ondblclick="document.location='services_servicewatchdog_add.php?id=<?=$nservices;?>';">
			<?=$thisservice['description'];?>
		</td>
		<td valign="middle" class="list" nowrap>
			<table border="0" cellspacing="0" cellpadding="1" summary="add">
				<tr>
					<td><input onmouseover="fr_insline(<?=$nservices;?>, true)" onmouseout="fr_insline(<?=$nservices;?>, false)" name="move_<?=$i;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" title="<?=gettext("move selected services before this service");?>" height="17" type="image" width="17" border="0" /></td>
					<td align="center" valign="middle"><a href="services_servicewatchdog.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this service?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete service");?>" alt="delete" /></a></td>
				</tr>
			</table>
		</td>
	</tr>
<?php
	$i++;
	$nservices++;
endforeach;
?>
	<tr>
		<td class="list" colspan="4"></td>
		<td class="list" valign="middle" nowrap>
			<table border="0" cellspacing="0" cellpadding="1" summary="add">
				<tr>
					<td><?php if ($nservices == 0): ?><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="<?=gettext("move selected services to end"); ?>" border="0" alt="move" /><?php else: ?><input name="move_<?=$i;?>" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" width="17" height="17" title="<?=gettext("move selected services to end");?>" border="0" alt="move" /><?php endif; ?></td>
				</tr>
				<tr>
					<td width="17">
					<?php if (count($a_pwservices) == 0): ?>
						<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?=gettext("delete selected services");?>" border="0" alt="delete" />
					<?php else: ?>
						<input name="del" type="image" src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" title="<?=gettext("delete selected services"); ?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected services?");?>')" />
					<?php endif; ?>
					</td>
					<td><a href="services_servicewatchdog_add.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add new service"); ?>" alt="add" /></a></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td></td>
		<td colspan="4">
			<?php echo gettext("Check Notify next to services to perform an e-mail notification when the service is restarted. Configure e-mail notifications to receive the alerts."); ?>
			<br/>
			<input name="Update" type="submit" class="formbtn" value="<?=gettext("Update Notification Settings"); ?>" />
			<br/>
			<br/>
		</td>
		<td></td>
	</tr>
	<tr>
		<td></td>
		<td colspan="4">
			<?php echo gettext("Click to select a service and use the arrows to re-order them in the list. Higher services are checked first."); ?>
		</td>
		<td></td>
	</tr>
</table>
</div></td></tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
