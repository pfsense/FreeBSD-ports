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

// Get configuration data
$this_item_config = udpbr_get_config();

// Use submitted data instead of configuration data
if (isset($_POST['save'])) {
	// Handle POST data differences
	if (isset($this_item_config['item'])) {
		$_POST['item'] = $this_item_config['item'];
	}

	$this_item_config = $_POST;
}

// Parse saved or submitted data
if (isset($this_item_config)) {
	$input_errors = udpbr_parse_config($this_item_config);
}

// Write configuration
if (isset($_POST['save']) && isset($this_item_config) && empty($input_errors)) {
	// Replace existing config
	udpbr_set_config($this_item_config);

	// Reload the service with the new configuration
	udpbr_resync();

	// Reload the general page with a message
	header('Location: /udpbroadcastrelay/udpbroadcastrelay.php?saved');
	exit;
}

// Delete configuration
if (isset($_POST['del_btn']) && is_array($_POST['del']) && isset($this_item_config)) {
	$ids_to_remove = [];
	foreach ($_POST['del'] as $id) {
		if (is_numericint($id) && (array_get_path($this_item_config, "item/{$id}") !== null)) {
			$ids_to_remove[] = intval($id);
		}
	}

	// Remove existing config items
	if (udpbr_del_instance_config($ids_to_remove)) {

		udpbr_resync();

		// Reload the general page with a message
		header('Location: /udpbroadcastrelay/udpbroadcastrelay.php?deleted');
		exit;
	}
}

$pgtitle = [gettext('Services'), gettext('UDP Broadcast Relay')];
$pglinks = ['', '/udpbroadcastrelay/udpbroadcastrelay.php'];
include_once('head.inc');

// Show messages
if (isset($_GET['saved']) || isset($_GET['deleted'])) {
	print_info_box(gettext('Saved settings.'), 'success');
}

// Show errors
if (is_array($input_errors)) {
	print_input_errors($input_errors);
}

// Initialize General Settings form data
if (!isset($this_item_config)) {
	$this_item_config = [];
}

$form = new Form;
$section = new Form_Section(gettext('General Settings'));

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	gettext('Enable the UDP Broadcast Relay service.'),
	isset($this_item_config['enable'])
));

$section->addInput(new Form_Select(
	'carpstatusvid',
	gettext('Track CARP Status'),
	$this_item_config['carpstatusvid'] ?? 'none',
	array_merge(['none' => 'none'], udpbr_get_carpvips())
))->setHelp('Tracks the CARP status of the selected CARP VIP. The service '. 
    'will only run when the selected VIP is in the MASTER state.')->setWidth(5);

$form->add($section);

// Show the General Settings form
print($form);

// Initialize Configured Instances form data
$instance_list = [];
if (is_array($this_item_config['item'])) {
	$interfaces = udpbr_get_interfaces(false, false);
	$instance_list = $this_item_config['item'];

	// Use the interface description instead of its friendly name
	foreach ($instance_list as &$instance) {
		$instance['interfaces'] = is_string($instance['interfaces']) ? explode(',', $instance['interfaces']) : [];
		foreach ($instance['interfaces'] as &$interface) {
			if (isset($interfaces[$interface])) {
				$interface = $interfaces[$interface]['descr'];
			}
		}
		unset($interface);
		$instance['interfaces'] = implode(', ', $instance['interfaces']);
	}
	unset($instance);
}

// Show the Configured Instances form
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Configured Instances');?></h2></div>
	<div class="table-responsive panel-body">
		<form action="/udpbroadcastrelay/udpbroadcastrelay.php" method="post">
			<input type="hidden" name="list_id" id="list_id" value=""/>
			<table id="maintable" class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><input type="checkbox" id="selectAll" name="selectAll" /></th>
						<th><?=gettext('Port')?></th>
						<th><?=gettext('Interfaces')?></th>
						<th><?=gettext('Description')?></th>
						<th><?=gettext('Actions')?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($instance_list as $id => $instance): ?>
					<tr <?php if (!isset($instance['enable'])): ?>class="disabled"<?php endif; ?>>
						<td>
							<input type="checkbox" id="frc<?=$id?>" name="del[]" value="<?=$id?>" onclick="fr_bgcolor('<?=$id?>')" />
						</td>
						<td>
							<?=isset($instance['port']) ? htmlspecialchars("{$instance['port']}") : ''?>
						</td>
						<td>
							<?=isset($instance['interfaces']) ? htmlspecialchars("{$instance['interfaces']}") : ''?>
						</td>
						<td>
							<?=isset($instance['description']) ? htmlspecialchars("{$instance['description']}") : ''?>
						</td>
						<td>
							<a class="fa-solid fa-pencil fa-lg" href="/udpbroadcastrelay/udpbroadcastrelay_edit.php?id=<?=$id?>" title="<?=gettext('Edit Instance');?>"></a>
						</td>
					</tr>
				<?php endforeach; ?>
					<tr style="background-color: inherit;">
						<td colspan="6" class="text-right">
							<a href="/udpbroadcastrelay/udpbroadcastrelay_edit.php" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add New Instance');?>">
								<i class="fa-solid fa-plus icon-embed-btn"></i>
								<?=gettext('Add');?>
							</a>
							<?php if (count($instance_list) > 0): ?>
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
