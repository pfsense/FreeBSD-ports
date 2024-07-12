<?php
/*
 * udpbroadcastrelay_edit.php
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
##|*IDENT=page-services-udpbroadcastrelay-edit
##|*NAME=Services: UDP Broadcast Relay Edit
##|*DESCR=Access the 'Services: UDP Broadcast Relay Edit' page.
##|*MATCH=udpbroadcastrelay_edit.php*
##|-PRIV

require_once('guiconfig.inc');
require_once('udpbroadcastrelay/udpbroadcastrelay.inc');

// Get configuration data
if (isset($_GET['id']) && is_numericint($_GET['id'])) {
	$this_item_id = intval($_GET['id']);
	$this_item_config = udpbr_get_instance_config($this_item_id);
	if (empty($this_item_config) || !is_array($this_item_config)) {
		// This configuration item does not exist
		unset($this_item_id);
	}
}

// Get form data
if (is_array($_POST) && isset($_POST['save'])) {
	$temp_item_config = $_POST;

	// Interfaces are stored as CSVs
	if (is_array($temp_item_config['interfaces'])) {
		$temp_item_config['interfaces'] = implode(',', $temp_item_config['interfaces']);
	}

	$this_item_config = $temp_item_config;
}

// Parse item configuration
if (isset($this_item_config)) {
	if (isset($this_item_id)) {
		// Existing instance
		$input_errors = udpbr_parse_instance_config($this_item_config, $this_item_id);
	} else {
		// New instance
		$input_errors = udpbr_parse_instance_config($this_item_config);
	}
}

// Parse form action
$item_action = [
	'action' => null,
];
if (is_array($_POST) && isset($this_item_config)) {
	if (isset($_POST['save']) && empty($input_errors)) {
		// Save configuration
		$item_action['action'] = 'save';
	}
}

// Write configuration
if ($item_action['action'] == 'save') {
	if (isset($this_item_id)) {
		// Replace an existing config item
		udpbr_set_instance_config($this_item_config, $this_item_id);
	} else {
		// Add a new config item
		udpbr_set_instance_config($this_item_config);
	}

	// Reload the service with the new configuration
	udpbr_resync();

	// Return to the general page with a message
	header('Location: /udpbroadcastrelay/udpbroadcastrelay.php?saved');
	exit;
}

$pgtitle = [gettext('Services'), gettext('UDP Broadcast Relay'), gettext('Edit')];
$pglinks = ['', '/udpbroadcastrelay/udpbroadcastrelay.php', '@self'];
include('head.inc');

// Show errors
if (is_array($input_errors)) {
	print_input_errors($input_errors);
}

// Initialize form data
if (!isset($this_item_config)) {
	$this_item_config = [];
}

$form = new Form;
$section = new Form_Section('Instance Settings');

$section->addInput(new Form_Checkbox(
	'enable',
	gettext('Enable'),
	gettext('Enable this instance.'),
	isset($this_item_config['enable'])
));

$section->addInput(new Form_Input(
	'description',
	gettext('Description'),
	'text',
	($this_item_config['description'] ?? null)
))->setHelp('A description for administrative reference (not parsed).');

$section->addInput(new Form_Select(
	'interfaces',
	gettext('*Network Interfaces'),
	(isset($this_item_config['interfaces']) ? explode(',', $this_item_config['interfaces']) : null),
	array_map(function($interface) {
		return $interface['descr'];
	}, udpbr_get_interfaces()),
	true
))->addClass('general', 'resizable')->setHelp('Interfaces to receive and transmit packets on. Must select at least 2.')->setWidth(5);

$section->addInput(new Form_Select(
	'spoof',
	gettext('Spoof Source'),
	$this_item_config['spoof'] ?? null,
	[
		0 => gettext('Keep Original (default)'),
		1 => gettext('Use Interface Address and Destination Port'),
		2 => gettext('Use Interface Address only')
	]
))->setHelp('Spoof the source IP address and/or port when relaying packets.')->setWidth(5);

$group = new Form_Group('');
$group->add(new Form_Input(
	'port',
	gettext('UDP Port'),
	null,
	$this_item_config['port'] ?? null,
	['type' => 'number', 'min' => 1, 'max' => 65535, 'step' => 1]
))->setHelp('Destination UDP port to listen on (1-65535).')->setWidth(2);
$group->add(new Form_IpAddress(
	'multicast',
	gettext('IP Address'),
	$this_item_config['multicast'] ?? null,
	'V4'
))->setHelp('Multicast group to relay packets on (optional).')->setWidth(4);
$section->add($group);

$form->add($section);

// Show the form
print($form);

include('foot.inc');
