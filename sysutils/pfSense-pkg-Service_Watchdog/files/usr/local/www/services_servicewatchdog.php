<?php
/*
 * services_servicewatchdog.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-servicewatchdog
##|*NAME=Services: Service Watchdog
##|*DESCR=Allow access to the 'Services: Service Watchdog' page.
##|*MATCH=services_servicewatchdog.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("service-utils.inc");
require_once("servicewatchdog.inc");

if (!is_array($config['installedpackages']['servicewatchdog'])) {
	$config['installedpackages']['servicewatchdog'] = array();
}

if (!is_array($config['installedpackages']['servicewatchdog']['item'])) {
	$config['installedpackages']['servicewatchdog']['item'] = array();
}

$a_pwservices = &$config['installedpackages']['servicewatchdog']['item'];

/* if a custom message has been passed along, lets process it */
if ($_GET['savemsg']) {
	$savemsg = $_GET['savemsg'];
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
	} else {
		/* No notifies selected, remove them all. */
		foreach ($a_pwservices as $idx => $thisservice) {
			unset($a_pwservices[$idx]['notify']);
		}
	}
	servicewatchdog_cron_job();
	write_config(gettext("Services: Service Watchdog: updated notification settings."));
	header("Location: services_servicewatchdog.php");
	return;
}

if (isset($_POST['del'])) {
	/* delete selected services */
	if (is_array($_POST['pwservices']) && count($_POST['pwservices'])) {
		foreach ($_POST['pwservices'] as $servicei) {
			unset($a_pwservices[$servicei]);
		}
		servicewatchdog_cron_job();
		write_config(gettext("Services: Service Watchdog: deleted a service from watchdog."));
		header("Location: services_servicewatchdog.php");
		return;
	}
} else {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
	unset($movebtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/del_(\d+)/", $pn, $matches)) {
			$delbtn = $matches[1];
		} elseif (preg_match("/move_(\d+)/", $pn, $matches)) {
			$movebtn = $matches[1];
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
		write_config(gettext("Services: Service Watchdog: changed services order configuration."));
		header("Location: services_servicewatchdog.php");
		return;
	} else if (isset($delbtn)) {
		unset($a_pwservices[$delbtn]);
		servicewatchdog_cron_job();
		write_config(gettext("Services: Service Watchdog: deleted a service from watchdog."));
		header("Location: services_servicewatchdog.php");
		return;
	}
}

$pgtitle = array(gettext("Services"), gettext("Service Watchdog"));
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

print_info_box(gettext("This page allows selecting services to be monitored so that they may be automatically restarted if they crash or are stopped."), 'info');
?>

<form name="mainform" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Monitored Services')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th width="5%" class="list">&nbsp;</th>
						<th width="5%" class="listhdrr"><?=gettext("Notify")?></th>
						<th width="30%" class="listhdrr"><?=gettext("Service Name")?></th>
						<th width="60%" class="listhdrr"><?=gettext("Description")?></th>
						<th width="5%"><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody class="services">

<?php
$nservices = $i = 0;
foreach ($a_pwservices as $thisservice):
?>
	<tr valign="top" id="fr<?=$nservices?>">
		<td>
			<input type="checkbox" id="frc<?=$nservices?>" name="pwservices[]" value="<?=$i?>" onclick="fr_bgcolor('<?=$nservices?>')" />
			<a class="fa-solid fa-anchor" id="Xmove_<?=$nservices?>" title="<?=gettext("Move checked entries to here")?>"></a>
		</td>
		<td><input type="checkbox" id="notify<?=$nservices?>" name="notifies[]" value="<?=$i?>" style="margin: 0; padding: 0; width: 15px; height: 15px;" <?PHP if (isset($thisservice['notify'])) echo 'checked="checked"'?>/></td>
		<td onclick="fr_toggle(<?=$nservices?>)" id="frd<?=$nservices?>" ondblclick="document.location='services_servicewatchdog_add.php?id=<?=$nservices?>';">
			<?=$thisservice['name']?>
		</td>
		<td onclick="fr_toggle(<?=$nservices?>)" id="frd<?=$nservices?>" ondblclick="document.location='services_servicewatchdog_add.php?id=<?=$nservices?>';">
			<?=$thisservice['description']?>
		</td>
		<td style="cursor: pointer;">
			<button style="display: none;" class="btn btn-default btn-xs" type="submit" id="move_<?=$i?>" name="move_<?=$i?>" value="move_<?=$i?>"><?=gettext("Move checked entries to here")?></button>
			<a class="fa-solid fa-trash-can no-confirm" id="Xdel_<?=$i?>" title="<?=gettext('Delete'); ?>"></a>
			<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="del_<?=$i?>" name="del_<?=$i?>" value="del_<?=$i?>" title="<?=gettext('Delete'); ?>">Delete</button>
		</td>
	</tr>
<?php
	$i++;
	$nservices++;
endforeach;
?>

<?php if ($i == 0): ?>
					<tr>
						<td colspan="2"></td>
						<td colspan="2">
							<?=gettext("No services have been defined for monitoring.");?>
						</td>
						<td></td>
					</tr>
<?php endif; ?>

				</tbody>
			</table>
		</div>
	</div>
	<nav class="action-buttons">
		<br />
		<a href="services_servicewatchdog_add.php" class="btn btn-success btn-sm">
			<i class="fa-solid fa-plus icon-embed-btn"></i>
			<?=gettext("Add New Service")?>
		</a>
		<button type="submit" id="Update" name="Update" class="btn btn-sm btn-primary" value="Update Notification Settings" title="<?=gettext('Update Notification Settings')?>">
			<i class="fa-solid fa-save icon-embed-btn"></i>
			<?=gettext("Save Notification Settings")?>
		</button>
<?php if ($i !== 0): ?>
		<button type="submit" name="del" class="btn btn-danger btn-sm" value="<?=gettext("Delete Selected Services")?>">
			<i class="fa-solid fa-trash-can icon-embed-btn"></i>
			<?=gettext("Delete")?>
		</button>
<?php endif; ?>
	</nav>
</form>
<div id="infoblock">
	<?php print_info_box(gettext("Check Notify next to services to perform an e-mail notification when the service is restarted. Configure e-mail notifications to receive the alerts."), 'info'); ?>
</div>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('[id^=Xmove_]').click(function (event) {
		$('#' + event.target.id.slice(1)).click();
	});

	$('[id^=Xdel_]').click(function (event) {
		if(confirm("<?=gettext('Delete this Service entry?')?>")) {
			$('#' + event.target.id.slice(1)).click();
		}
	});
});
//]]>
</script>
<?php include("foot.inc"); ?>
