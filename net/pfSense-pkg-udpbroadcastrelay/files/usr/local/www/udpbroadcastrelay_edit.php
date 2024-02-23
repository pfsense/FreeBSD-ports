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

$is_new_entry = (!isset($_GET['idx'])) ? true : false;
// Get the configuration array index for an instance from the URL
if (isset($_GET['idx']) && is_numericint($_GET['idx'])) {
	$idx = intval(htmlspecialchars($_GET['idx']));
} elseif (isset($idx)) {
	unset($idx);
}
// Initialize page config
if (isset($idx)) {
	$pconfig = udpbr_get_settings(true, $idx);
}
if (!isset($idx) || empty($pconfig)) {
	$pconfig = array(
		'id' => '',
		'description' => '',
		'port' => '',
		'interfaces' => '',
		'spoof' => '',
		'multicast' => ''
	);
}

if (isset($_POST['save'])) {
	$pconfig['id'] = $_POST['id'];
	if (isset($_POST['enable'])) {
		$pconfig['enable'] = '';
	} elseif (isset($pconfig['enable'])) {
		unset($pconfig['enable']);
	}
	$pconfig['description'] = mb_convert_encoding($_POST['description'], 'HTML-ENTITIES', 'auto');
	$pconfig['port'] = $_POST['port'];
	$pconfig['interfaces'] = implode(',', $_POST['interfaces']);
	$pconfig['spoof'] = $_POST['spoof'];
	$pconfig['multicast'] = $_POST['multicast'];
}

// Do input validation when saving or editing an existing configuration
if (isset($_POST['save']) || !$is_new_entry) {
	$input_errors = udpbr_validate_config($pconfig, true, $idx);

	// Save the configuration, apply changes, and redirect with save message
	if (isset($_POST['save']) && empty($input_errors)) {
		udpbr_set_instance($pconfig, $is_new_entry, $idx);
		write_config(gettext('UDP Broadcast Relay pkg: saved instance.'));
		udpbr_resync();
		header('Location: udpbroadcastrelay.php?saved');
		exit;
	}
}

$pgtitle = array(gettext('Services'), gettext('UDP Broadcast Relay'), gettext('Edit'));
$pglinks = array('', 'udpbroadcastrelay.php', '@self');
include('head.inc');

// Show input validation errors
if (!empty($input_errors)) {
	print_input_errors($input_errors);
}

// Form variables
$form_interfaces_available = udpbr_get_interfaces_sorted();
$form_spoof_options = array(
	0 => gettext('Keep Original (default)'),
	1 => gettext('Use Interface Address and Destination Port'),
	2 => gettext('Use Interface Address only')
);

$form = new Form;
$section = new Form_Section('Instance Settings');
$section->addInput(new Form_Checkbox(
	'enable',
	gettext('Enable'),
	gettext('Enable this instance.'),
	isset($pconfig['enable'])
));
$section->addInput(new Form_Input(
	'description',
	gettext('Description'),
	'text',
	$pconfig['description']
))->setHelp('A description for administrative reference (not parsed).');
$section->addInput(new Form_Select(
	'interfaces',
	gettext('*Network Interfaces'),
	(explode(',', $pconfig['interfaces'])),
	$form_interfaces_available,
	true
))->addClass('general', 'resizable')->setHelp('Interfaces to receive and transmit packets on. Must select at least 2.')->setWidth(5);
$section->addInput(new Form_Select(
	'spoof',
	gettext('Spoof Source'),
	$pconfig['spoof'],
	$form_spoof_options
))->setHelp('Spoof the source IP address and/or port when relaying packets.')->setWidth(5);
$group = new Form_Group('');
$group->add(new Form_Input(
	'id',
	gettext('Instance ID'),
	null,
	$pconfig['id'],
	array('type' => 'number', 'min' => 1, 'max' => 63, 'step' => 1)
))->setHelp('A unique number between instances (1-63).')->setWidth(2);
$group->add(new Form_Input(
	'port',
	gettext('UDP Port'),
	null,
	$pconfig['port'],
	array('type' => 'number', 'min' => 1, 'max' => 65535, 'step' => 1)
))->setHelp('Destination UDP port to listen on (1-65535).')->setWidth(2);
$group->add(new Form_IpAddress(
	'multicast',
	gettext('IP Address'),
	$pconfig['multicast'],
	'V4'
))->setHelp('Multicast group to relay packets on (optional).')->setWidth(2);
$section->add($group);
$form->add($section);

print($form);

include('foot.inc');
