<?php
/*
 * udpbroadcastrelay.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2023-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-udpbroadcastrelay
##|*NAME=Services: UDP Broadcast Relay
##|*DESCR=Access the 'Services: UDP Broadcast Relay' page.
##|*MATCH=udpbroadcastrelay.php*
##|-PRIV

require_once('guiconfig.inc');
require_once('udpbroadcastrelay/udpbroadcastrelay.inc');

// Delete the selected instances
if (isset($_POST['del_btn'])) {
	if (is_array($_POST['del']) && count($_POST['del'])) {
		$need_save = false;
		foreach ($_POST['del'] as $id) {
			// remove the config entry
			udpbr_set_instance(null, false, $id);
			$need_save = true;
		}

		// save the config change and redirect to the main page
		if ($need_save) {
			write_config(gettext('UDP Broadcast Relay pkg: deleted instance(s).'));
			udpbr_resync();
			header('Location: /udpbroadcastrelay.php');
			exit;
		}
	}
}

// Get saved settings
$pconfig = udpbr_get_settings();
if (empty($pconfig)) {
	$pconfig = array();
}
if (!empty($pconfig['item']) && is_array($pconfig['item'])) {
	$instances = $pconfig['item'];
} else {
	$instances = array();
}
// Use interface descriptions instead of names
if (!empty($instances)) {
	$form_interfaces = udpbr_get_interfaces_sorted(true);

	foreach ($instances as &$instance) {
		$interfaces_saved = explode(',', $instance['interfaces']);
		$interfaces_description = array();
		foreach ($interfaces_saved as $interface) {
			if (array_key_exists($interface, $form_interfaces)) {
				$interfaces_description[] = $form_interfaces[$interface];
			} else {
				$interfaces_description[] = $interface;
			}
		}
		$instance['interfaces'] = implode(', ', $interfaces_description);
	}
	unset($instance);
}

if (isset($_POST['save'])) {
	if (isset($_POST['enable'])) {
		$pconfig['enable'] = '';
	} elseif (isset($pconfig['enable'])) {
		unset($pconfig['enable']);
	}
	$pconfig['carpstatusvid'] = $_POST['carpstatusvid'];
}

// Do input validation on page load
$input_errors = udpbr_validate_config($pconfig, false, null, udpbr_get_interfaces_sorted());

// Save the configuration and apply changes
if (isset($_POST['save']) && empty($input_errors)) {
	udpbr_set_settings($pconfig);
	write_config(gettext('UDP Broadcast Relay pkg: saved general settings.'));
	udpbr_resync();
}

$pgtitle = array(gettext('Services'), gettext('UDP Broadcast Relay'));
$pglinks = array('', 'udpbroadcastrelay.php');
include_once('head.inc');

// Show input validation errors
if (!empty($input_errors)) {
	print_input_errors($input_errors);
}

// Show save messages
if (isset($_POST['save']) && empty($input_errors)) {
	print_info_box(gettext('Saved general settings.'), 'success');
} elseif (isset($_GET['saved'])) {
	print_info_box(gettext('Saved settings.'), 'success');
}

// General Settings form
$form = new Form;
$section = new Form_Section(gettext('General Settings'));
$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	gettext('Enable the UDP Broadcast Relay service.'),
	isset($pconfig['enable'])
));
$section->addInput(new Form_Select(
	'carpstatusvid',
	gettext('Track CARP Status'),
	$pconfig['carpstatusvid'],
	udpbr_get_carp_list()
))->setHelp(gettext('Tracks the CARP status of the selected CARP VIP. The service will only run when the selected VIP is in the MASTER state.'))->setWidth(5);
$form->add($section);

print($form);

// Configured Instances form
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Configured Instances');?></h2></div>
	<div class="table-responsive panel-body">
		<form action="udpbroadcastrelay.php" method="post">
			<input type="hidden" name="list_id" id="list_id" value=""/>
			<table id="maintable" class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><input type="checkbox" id="selectAll" name="selectAll" /></th>
						<th><?=gettext('ID')?></th>
						<th><?=gettext('Port')?></th>
						<th><?=gettext('Interfaces')?></th>
						<th><?=gettext('Description')?></th>
						<th><?=gettext('Actions')?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($instances as $i => $instance): ?>
					<tr <?php if (!isset($instance['enable'])): ?>class="disabled"<?php endif; ?>>
						<td>
							<input type="checkbox" id="frc<?=$i?>" name="del[]" value="<?=$i?>" onclick="fr_bgcolor('<?=$i?>')" />
						</td>
						<td>
							<?=htmlspecialchars($instance['id'])?>
						</td>
						<td>
							<?=htmlspecialchars($instance['port'])?>
						</td>
						<td>
							<?=htmlspecialchars($instance['interfaces'])?>
						</td>
						<td>
							<?=htmlspecialchars($instance['description'])?>
						</td>
						<td>
							<a class="fa-solid fa-pencil fa-lg" href="udpbroadcastrelay_edit.php?idx=<?=$i?>" title="<?=gettext('Edit Instance');?>"></a>
						</td>
					</tr>
				<?php endforeach; ?>
					<tr style="background-color: inherit;">
						<td colspan="6" class="text-right">
							<a href="udpbroadcastrelay_edit.php" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add New Instance');?>">
								<i class="fa-solid fa-plus icon-embed-btn"></i>
								<?=gettext('Add');?>
							</a>
							<?php if (count($instances) > 0): ?>
								<button type="submit" name="del_btn" id="del_btn" class="btn btn-danger btn-sm" title="<?=gettext('Delete Selected Instance');?>">
									<i class="fa-solid fa-trash-can icon-embed-btn"></i>
									<?=gettext('Delete');?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</form>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Disable the delete button when no entries are checked and handle the selectAll checkbox
	buttonsmode('frc', ['del_btn']);

	$('[id^=fr]').click(function () {
		buttonsmode('frc', ['del_btn']);
	});

	$('#selectAll').click(function() {
		var checkedStatus = this.checked;
		$('#maintable tbody tr').find('td:first :checkbox').each(function() {
		$(this).prop('checked', checkedStatus);
		});
		buttonsmode('frc', ['del_btn']);
	});
});
//]]>
</script>

<?php
include('foot.inc');
