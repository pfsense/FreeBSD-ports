<?php
/*
 * mcast_bridge.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2025 Denny Page
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


require_once("guiconfig.inc");
require_once("mcast_bridge.inc");

$shortcut_section = 'mcast_bridge';


// Get the current configuration
$current_config = config_get_path(MCB_CONF_PATH, []);
$pconfig[MCB_CONF_NAME_ENABLE] = array_get_path($current_config, MCB_CONF_NAME_ENABLE);
$pconfig[MCB_CONF_NAME_CARP_VHID] = array_get_path($current_config, MCB_CONF_NAME_CARP_VHID);
$pconfig[MCB_CONF_NAME_IGMP_QUERIER] = array_get_path($current_config, MCB_CONF_NAME_IGMP_QUERIER, 'quick');
$pconfig[MCB_CONF_NAME_MLD_QUERIER] = array_get_path($current_config, MCB_CONF_NAME_MLD_QUERIER, 'quick');

// Handle a bridge toggle
if ($_REQUEST['act'] == "toggle") {
	$id = $_REQUEST['id'];
	if (is_numericint($id)) {
		$service_path = MCB_CONF_NAME_SERVICE . '/' . $id;
		if (array_get_path($current_config, $service_path)) {
			// Set the paths
			$service_disabled_path = $service_path . '/' . MCB_CONF_NAME_SERVICE_DISABLED;
			$service_port_path = $service_path . '/' . MCB_CONF_NAME_SERVICE_PORT;
	
			// Get the current state
			$service_enabled = array_get_path($current_config, $service_disabled_path) === null;
			$service_port = array_get_path($current_config, $service_port_path);
	
			// Update the config and cached array
			if ($service_enabled) {
				// Disable the bridge
				config_set_path(MCB_CONF_PATH . '/' . $service_disabled_path, 'yes');
				array_set_path($current_config, $service_disabled_path, 'yes');
				$changedesc = sprintf(gettext("Multicast Bridge: disabled port %s"), $service_port);
			} else {
				// Enable the bridge
				config_del_path(MCB_CONF_PATH . '/' . $service_disabled_path);
				array_del_path($current_config, $service_disabled_path);
				$changedesc = sprintf(gettext("Multicast Bridge: enabled port %s"), $service_port);
			}
	
			// Write the config
			write_config($changedesc);
			mark_subsystem_dirty('mcast_bridge');
		}
	}
}

// Handle a bridge delete
else if ($_REQUEST['act'] == "delete") {
	$id = $_REQUEST['id'];
	if (is_numericint($id)) {
		$service_path = MCB_CONF_NAME_SERVICE . '/' . $id;
		$service = array_get_path($current_config, $service_path, []);
		if ($service) {
			$changedesc = sprintf(gettext("Multicast Bridge: deleted port %s"), $service[MCB_CONF_NAME_SERVICE_PORT]);

			// Update the config and cached array
			config_del_path(MCB_CONF_PATH . '/' . $service_path);
			array_del_path($current_config, $service_path);

			// Write the config and mark the subsystem dirty if appropriate
			write_config($changedesc);
			if (!isset($service[MCB_CONF_NAME_SERVICE_DISABLED])) {
				mark_subsystem_dirty('mcast_bridge');
			}
		}

	}
}

else if ($_POST['save']) {
	$pconfig = $_POST;

	// Update the config
	config_set_path(MCB_CONF_PATH_ENABLE, $pconfig[MCB_CONF_NAME_ENABLE]);
	config_set_path(MCB_CONF_PATH_CARP_VHID, $pconfig[MCB_CONF_NAME_CARP_VHID]);
	config_set_path(MCB_CONF_PATH_IGMP_QUERIER, $pconfig[MCB_CONF_NAME_IGMP_QUERIER]);
	config_set_path(MCB_CONF_PATH_MLD_QUERIER, $pconfig[MCB_CONF_NAME_MLD_QUERIER]);

	// Write the config
	write_config(gettext("Multicast Bridge: general settings changed"));
	mark_subsystem_dirty('mcast_bridge');
}

else if ($_POST['apply']) {
	clear_subsystem_dirty('mcast_bridge');

	// Sync the running configuration
	mcast_bridge_sync_config();
}

// Available options for Querier Modes
$querier_mode_options = array(
	'never' => gettext('Never - the querier function is disabled'),
	'quick' => gettext('Quick - activate at startup (default)'),
	'delay' => gettext('Delay - activate after 125 seconds if no other querier is present'),
	'defer' => gettext('Defer - delay activation and always defer to other queriers') );

$pgtitle = array(gettext("Services"), gettext("Multicast Bridge"));
include("head.inc");

if (is_subsystem_dirty('mcast_bridge')) {
	print_apply_box(gettext('The Mcast Bridge configuration has changed.') . '<br />' .
			gettext('The changes must be applied to take effect.'));
}

$form = new Form;
$section = new Form_Section('Settings');

// Enable
$section->addInput(new Form_Checkbox(
	MCB_CONF_NAME_ENABLE,
	'Enable',
	'Enable the Multicast Bridge daemon',
	$pconfig[MCB_CONF_NAME_ENABLE]
));

// CARP
$section->addInput(new Form_Select(
	MCB_CONF_NAME_CARP_VHID,
	'CARP Status VHID',
	$pconfig[MCB_CONF_NAME_CARP_VHID],
	mcast_bridge_get_carp_list()
))->setHelp(gettext('Used for HA MASTER/BACKUP status. Multicast Bridge will be started when the chosen VHID is in MASTER status, and stopped when in BACKUP status.'));

// IGMP Querier mode
$section->addInput(new Form_Select(
	MCB_CONF_NAME_IGMP_QUERIER,
	'IGMP Querier Mode',
	$pconfig[MCB_CONF_NAME_IGMP_QUERIER],
	$querier_mode_options
))->setHelp(gettext('When to activate as an Internet Group Membership Protocol (IPv4) querier.'));

// MLD Querier mode
$section->addInput(new Form_Select(
	MCB_CONF_NAME_MLD_QUERIER,
	'MLD Querier Mode',
	$pconfig[MCB_CONF_NAME_MLD_QUERIER],
	$querier_mode_options
))->setHelp(gettext('When to activate as an Multicast Listener Discovery (IPv6) querier.'));

// Additional Information
$section->addInput(new Form_StaticText(
	gettext('Additional Information'),
	'<span class="help-block">'.
	gettext('By default, mcast-bridge uses the IGMP (IPv4) and MLD (IPv6) protocols to ' .
		'determine if active subscribers are present on outbound interfaces, and only ' .
		'forwards packets to an interface if an active subscriber is currently present. ' .
		'If an interface is configured as static (indicated by an asterisk below), then ' .
		'a subscriber is always assumed to be present and IGMP and MLD are not used ' .
		'for that bridge interface.' .
		'<br><br>' .
		'With both the IGMP and MLD protocols, a single "querier" is elected to be ' .
		'responsible for tracking multicast subscribers in the network. Most often, a ' .
		'switch or router handles the role of querier, however mcast-bridge is also ' .
		'capable of acting as the querier if enabled. Additional information on ' .
		'querier modes is available in the ' .
		'<a target="_blank" href="https://github.com/dennypage/mcast-bridge/blob/main/README.md#notes-on-querier-modes">' .
		'mcast-bridge README</a>.')
));

$form->add($section);
print($form);

?>
<form method="post">

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Bridges')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table id="bridge_services" class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th></th>
						<th><?=gettext('UDP Port')?></th>
						<th><?=gettext('IPv4 Address')?></th>
						<th><?=gettext('IPv6 Address')?></th>
						<th><?=gettext('Inbound Interfaces')?></th>
						<th><?=gettext('Outbound Interfaces')?></th>
						<th><?=gettext('Description')?></th>
					</tr>
				</thead>
				<tbody>
<?php
foreach (array_get_path($current_config, MCB_CONF_NAME_SERVICE, []) as $id => $service):
	if (isset($service[MCB_CONF_NAME_SERVICE_DISABLED])) {
		$icon = 'fa-solid fa-ban';
		$title = gettext('Bridge disabled');
	} else {
		$icon = 'fa-regular fa-circle-check';
		$title = gettext('Bridge enabled');
	}

	// Build the inbound interface display list
	$display = array();
	$interface_list = array_filter(explode(',', $service[MCB_CONF_NAME_SERVICE_INBOUND]));
	$static_interface_list = array_filter(explode(',', $service[MCB_CONF_NAME_SERVICE_STATIC_INBOUND]));
	foreach ($interface_list as $interface) {
		$friendly = convert_friendly_interface_to_friendly_descr($interface);
		if (array_search($interface, $static_interface_list) !== false) {
			$display[] = $friendly . '*';
		}
		else {
			$display[] = $friendly;
		}
	}
	foreach ($static_interface_list as $interface) {
		$friendly = convert_friendly_interface_to_friendly_descr($interface);
		if (array_search($interface, $interface_list) === false) {
			$display[] = $friendly . '*';
		}
	}
	$inbound_display = implode(', ', $display);

	// Build the outbound interface display list
	$display = array();
	$interface_list = array_filter(explode(',', $service[MCB_CONF_NAME_SERVICE_OUTBOUND]));
	$static_interface_list = array_filter(explode(',', $service[MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND]));
	foreach ($interface_list as $interface) {
		$friendly = convert_friendly_interface_to_friendly_descr($interface);
		if (array_search($interface, $static_interface_list) !== false) {
			$display[] = $friendly . '*';
		}
		else {
			$display[] = $friendly;
		}
	}
	foreach ($static_interface_list as $interface) {
		$friendly = convert_friendly_interface_to_friendly_descr($interface);
		if (array_search($interface, $interface_list) === false) {
			$display[] = $friendly . '*';
		}
	}
	$outbound_display = implode(', ', $display);

?>
					<tr<?=($icon != 'fa-regular fa-circle-check')? ' class="disabled"' : ''?> onClick="fr_toggle(<?=$id;?>)" id="fr<?=$id;?>">
						<td title="<?=$title?>"><i class="<?=$icon?>"></i></td>
						<td>
							<?=htmlspecialchars($service[MCB_CONF_NAME_SERVICE_PORT])?>
						</td>
						<td>
							<?=htmlspecialchars($service[MCB_CONF_NAME_SERVICE_IPV4])?>
						</td>
						<td>
							<?=htmlspecialchars($service[MCB_CONF_NAME_SERVICE_IPV6])?>
						</td>
						<td>
							<?=htmlspecialchars($inbound_display)?>
						</td>
						<td>
							<?=htmlspecialchars($outbound_display)?>
						</td>
						<td>
							<?=htmlspecialchars($service[MCB_CONF_NAME_SERVICE_DESC])?>
						</td>
						<td style="white-space: nowrap;">
							<a href="mcast_bridge_edit.php?id=<?=$id?>" class="fa-solid fa-pencil" title="<?=gettext('Edit bridge');?>"></a>
							<a href="mcast_bridge_edit.php?dup=<?=$id?>" class="fa-regular fa-clone" title="<?=gettext('Duplicate bridge')?>"></a>

	<?php if (isset($service[MCB_CONF_NAME_SERVICE_DISABLED])) {
	?>
							<a href="?act=toggle&amp;id=<?=$id?>" class="fa-regular fa-square-check" title="<?=gettext('Enable bridge')?>" usepost></a>
	<?php } else {
	?>
							<a href="?act=toggle&amp;id=<?=$id?>" class="fa-solid fa-ban" title="<?=gettext('Disable bridge')?>" usepost></a>
	<?php }
	?>
							<a href="?act=delete&amp;id=<?=$id?>" class="fa-solid fa-trash-can" title="<?=gettext('Delete bridge')?>" usepost></a>

						</td>
					</tr>
<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<div class="panel-body">
<nav class="action-buttons">
	<a href="mcast_bridge_edit.php" role="button" class="btn btn-success">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext('Add');?>
	</a>
</nav>
</div>
</form>

<?php include("foot.inc");
