<?php
/*
 * mcast_bridge_edit.php
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

$shortcut_section = 'mcast-bridge';

$service_path = MCB_CONF_PATH_SERVICE . '/';

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
	$init_path = MCB_CONF_PATH_SERVICE . '/' . $id;
	$service_path .= $id;

	if (!config_get_path($service_path . '/' . MCB_CONF_NAME_SERVICE_DISABLED)) {
		$dirty = 1;
	}
}
else if (is_numericint($_REQUEST['dup'])) {
	$init_path = MCB_CONF_PATH_SERVICE . '/' . $_REQUEST['dup'];
}

if (isset($init_path)) {
	$init_array = config_get_path($init_path, null);
	unset($init_path);

	$pconfig = array();
	$pconfig[MCB_CONF_NAME_SERVICE_DISABLED] = $init_array[MCB_CONF_NAME_SERVICE_DISABLED];
	$pconfig[MCB_CONF_NAME_SERVICE_PORT] = $init_array[MCB_CONF_NAME_SERVICE_PORT];
	$pconfig[MCB_CONF_NAME_SERVICE_IPV4] = $init_array[MCB_CONF_NAME_SERVICE_IPV4];
	$pconfig[MCB_CONF_NAME_SERVICE_IPV6] = $init_array[MCB_CONF_NAME_SERVICE_IPV6];
	$pconfig[MCB_CONF_NAME_SERVICE_INBOUND] = array_filter(explode(',', $init_array[MCB_CONF_NAME_SERVICE_INBOUND]));
	$pconfig[MCB_CONF_NAME_SERVICE_OUTBOUND] = array_filter(explode(',', $init_array[MCB_CONF_NAME_SERVICE_OUTBOUND]));
	$pconfig[MCB_CONF_NAME_SERVICE_STATIC_INBOUND] = array_filter(explode(',', $init_array[MCB_CONF_NAME_SERVICE_STATIC_INBOUND]));
	$pconfig[MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND] = array_filter(explode(',', $init_array[MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND]));
	$pconfig[MCB_CONF_NAME_SERVICE_DESC] = $init_array[MCB_CONF_NAME_SERVICE_DESC];
}

// Get the list of available interfaces
$available_interfaces = get_configured_interface_with_descr();

if ($_POST) {
	$pconfig = $_POST;

	// Validate port
	$pconfig[MCB_CONF_NAME_SERVICE_PORT] = trim($pconfig[MCB_CONF_NAME_SERVICE_PORT]);
	if (! is_port($pconfig[MCB_CONF_NAME_SERVICE_PORT], false)) {
		$input_errors[] = gettext('Invalid UDP Port.');
	}

	// Ensure the port is unique
	$existing = config_get_path(MCB_CONF_PATH_SERVICE, []);
	$count = count($existing);
	for ($i = 0; $i < count($existing); $i++) {
		if ($i != $id && $pconfig[MCB_CONF_NAME_SERVICE_PORT] === $existing[$i][MCB_CONF_NAME_SERVICE_PORT]) {
			$input_errors[] = gettext('UDP Port is already in use by another bridge instance.');
		}
	}

	// Validate addresses
	$pconfig[MCB_CONF_NAME_SERVICE_IPV4] = trim($pconfig[MCB_CONF_NAME_SERVICE_IPV4]);
	$pconfig[MCB_CONF_NAME_SERVICE_IPV6] = trim($pconfig[MCB_CONF_NAME_SERVICE_IPV6]);
	if (empty($pconfig[MCB_CONF_NAME_SERVICE_IPV4]) && empty($pconfig[MCB_CONF_NAME_SERVICE_IPV6])) {
		$input_errors[] = gettext('At least one Multicast Address must be provided.');
	}
	else {
		if (!empty($pconfig[MCB_CONF_NAME_SERVICE_IPV4])) {
			if (!is_mcastv4($pconfig[MCB_CONF_NAME_SERVICE_IPV4])) {
				$input_errors[] = gettext('Invalid IPv4 Multicast Address.');
			}
			else if (preg_match('/^224\.0\.0\./', $pconfig[MCB_CONF_NAME_SERVICE_IPV4])) {
				$input_errors[] = gettext('IPv4 Multicast Address is link local (non routable).');
			}
		}
		if (!empty($pconfig[MCB_CONF_NAME_SERVICE_IPV6])) {
			if (!is_mcastv6($pconfig[MCB_CONF_NAME_SERVICE_IPV6])) {
				$input_errors[] = gettext('Invalid IPv6 Multicast Address.');
			}
			else if (preg_match('/^ff02:/', $pconfig[MCB_CONF_NAME_SERVICE_IPV6])) {
				$input_errors[] = gettext('IPv6 Multicast Address is link local (non routable).');
			}
		}
	}

	// Validate the inbound and outbound interface configurations
	if (empty($pconfig[MCB_CONF_NAME_SERVICE_INBOUND]) && empty($pconfig[MCB_CONF_NAME_SERVICE_STATIC_INBOUND])) {
		$input_errors[] = gettext('At least one Inbound Interface is required.');
	}
	if (empty($pconfig[MCB_CONF_NAME_SERVICE_OUTBOUND]) && empty($pconfig[MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND])) {
		$input_errors[] = gettext('At least one Outbound Interface is required.');
	}
	if (!$input_errors) {
		// Combined list of inbound interfaces
		if (!empty($pconfig[MCB_CONF_NAME_SERVICE_INBOUND])) {
			$inbound_combined = $pconfig[MCB_CONF_NAME_SERVICE_INBOUND];
			if (!empty($pconfig[MCB_CONF_NAME_SERVICE_STATIC_INBOUND])) {
				$inbound_combined = array_unique(array_merge($inbound_combined, $pconfig[MCB_CONF_NAME_SERVICE_STATIC_INBOUND]));
			}
		}
		else {
			$inbound_combined = $pconfig[MCB_CONF_NAME_SERVICE_STATIC_INBOUND];
		}

		// Combined list of inbound interfaces
		if (!empty($pconfig[MCB_CONF_NAME_SERVICE_OUTBOUND])) {
			$outbound_combined = $pconfig[MCB_CONF_NAME_SERVICE_OUTBOUND];
			if (!empty($pconfig[MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND])) {
				$outbound_combined = array_unique(array_merge($outbound_combined, $pconfig[MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND]));
			}
		}
		else {
			$outbound_combined = $pconfig[MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND];
		}

		// Ensure we don't have a single inbound in the outboud list or vice versa
		if (count($inbound_combined) == 1 && array_search($inbound_combined[0], $outbound_combined) !== false) {
			$input_errors[] = gettext('The single Inbound Interface cannot also be an Outbound Interface.');
		}
		if (count($outbound_combined) == 1 && array_search($outbound_combined[0], $inbound_combined) !== false) {
			$input_errors[] = gettext('The single Outbound Interface cannot also be an Inbound Interface.');
		}
	}

	// Update the config
	if (!$input_errors) {
		$write_array = array();
		if ($pconfig[MCB_CONF_NAME_SERVICE_DISABLED]) {
			$write_array[MCB_CONF_NAME_SERVICE_DISABLED] = $pconfig[MCB_CONF_NAME_SERVICE_DISABLED];
		}
		else {
			$dirty = 1;
		}

		$write_array[MCB_CONF_NAME_SERVICE_PORT] = $pconfig[MCB_CONF_NAME_SERVICE_PORT];
		$write_array[MCB_CONF_NAME_SERVICE_IPV4] = $pconfig[MCB_CONF_NAME_SERVICE_IPV4];
		$write_array[MCB_CONF_NAME_SERVICE_IPV6] = $pconfig[MCB_CONF_NAME_SERVICE_IPV6];
		if (is_array($pconfig[MCB_CONF_NAME_SERVICE_INBOUND])) {
			$write_array[MCB_CONF_NAME_SERVICE_INBOUND] = implode(',', $pconfig[MCB_CONF_NAME_SERVICE_INBOUND]);
		}
		if (is_array($pconfig[MCB_CONF_NAME_SERVICE_OUTBOUND])) {
			$write_array[MCB_CONF_NAME_SERVICE_OUTBOUND] = implode(',', $pconfig[MCB_CONF_NAME_SERVICE_OUTBOUND]);
		}
		if (is_array($pconfig[MCB_CONF_NAME_SERVICE_STATIC_INBOUND])) {
			$write_array[MCB_CONF_NAME_SERVICE_STATIC_INBOUND] = implode(',', $pconfig[MCB_CONF_NAME_SERVICE_STATIC_INBOUND]);
		}
		if (is_array($pconfig[MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND])) {
			$write_array[MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND] = implode(',', $pconfig[MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND]);
		}
		$write_array[MCB_CONF_NAME_SERVICE_DESC] = $pconfig[MCB_CONF_NAME_SERVICE_DESC];

		// Write the config
		config_set_path($service_path, $write_array);
		write_config(sprintf(gettext("Multicast Bridge: %s port %s"),
			isset($id) ? gettext('edited') : gettext('added'),
			$write_array[MCB_CONF_NAME_SERVICE_PORT]));

		// Mark the subsystem as dirty if appropriate
		if ($dirty) {
			mark_subsystem_dirty('mcast_bridge');
		}

		// Return to the main page
		header("Location: mcast_bridge.php");
		exit;
	}
}


$pgtitle = array(gettext("Services"), gettext("Multicast Bridge"), gettext("Edit Bridge"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;

$section = new Form_Section('Edit Bridge');

// Disable
$section->addInput(new Form_Checkbox(
	'disabled',
	'Disable',
	'Disable this bridge',
	$pconfig[MCB_CONF_NAME_SERVICE_DISABLED]
));

// UDP port
$section->addInput(new Form_Input(
	'port',
	'*UDP Port',
	'text',
	$pconfig[MCB_CONF_NAME_SERVICE_PORT]
));

$group = new Form_Group('Multicast Addresses');
// IPv4 address
$group->add(new Form_Input(
	'ipv4',
	'IPv4 Multicast Address',
	'text',
	$pconfig[MCB_CONF_NAME_SERVICE_IPV4]
))->setHelp(gettext('IPv4 Multicast Address. Must be routable (non 224.0.0.0/24).'));
// IPv6 address
$group->add(new Form_Input(
	'ipv6',
	'IPv6 Multicast Address',
	'text',
	$pconfig[MCB_CONF_NAME_SERVICE_IPV6]
))->setHelp(gettext('IPv6 Multicast Address. Must be routable (non ff02::/16).'));
$group->setHelp(
	gettext('A bridge may have an IPv4 multicast address, an IPv6 multicast address, ' .
		'or both. At least one Multicast address is required.'));
$section->add($group);

// Inbound interfaces
$group = new Form_Group('Inbound Interfaces');
$group->add(new Form_Select(
	MCB_CONF_NAME_SERVICE_INBOUND,
	'Inbound Interfaces',
	$pconfig[MCB_CONF_NAME_SERVICE_INBOUND],
	$available_interfaces,
	true
))->setHelp(gettext('Inbound Interfaces.'));
// Static inbound interfaces
$group->add(new Form_Select(
	MCB_CONF_NAME_SERVICE_STATIC_INBOUND,
	'Static Inbound Interfaces',
	$pconfig[MCB_CONF_NAME_SERVICE_STATIC_INBOUND],
	$available_interfaces,
	true
))->setHelp(gettext('Static Inbound Interfaces.'));
$group->setHelp(
	gettext('Inbound Interfaces are interfaces that the bridge will receive packets ' .
		'from. By default, the bridge will join the multicast group on an inbound ' .
		'interface when there is an active subscriber on one of the outbound ' .
		'interfaces, and leave the group when there are no more active subscribers. ' .
		'However, if an interface is listed in the Static Inbound Interface list, ' .
		'the bridge will join the multicast group on that interface immediately on ' .
		'startup, and not leave the group even if no active subscribers are present. ' .
		'Interfaces may be bi-directional, appearing in both Inbound Interface ' .
		'and Outbound Interface lists.'));
$section->add($group);

// Outbound interfaces
$group = new Form_Group('Outbound Interfaces');
$group->add(new Form_Select(
	MCB_CONF_NAME_SERVICE_OUTBOUND,
	'Outbound Interfaces',
	$pconfig[MCB_CONF_NAME_SERVICE_OUTBOUND],
	$available_interfaces,
	true
))->setHelp(gettext('Outbound Interfaces.'));
// Static outbound interfaces
$group->add(new Form_Select(
	MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND,
	'Static Outbound Interfaces',
	$pconfig[MCB_CONF_NAME_SERVICE_STATIC_OUTBOUND],
	$available_interfaces,
	true
))->setHelp(gettext('Static Outbound Interfaces.'));
$group->setHelp(
	gettext('Outbound Interfaces are interfaces that the bridge will forward packets ' .
		'to. By default, the bridge will use IGMP (IPv4) and MLD (IPv6) to determine ' .
		'if there are active subscribers present on the interface, and will only ' .
		'forward packets to the interface if an active subscriber is currently ' .
		'present. However, if an interface is in the Static Outbound Interface ' .
		'list, IGMP/MLD will not be used on the interface, and the bridge will ' .
		'always consider an active subscriber to be present on the interface. ' .
		'Interfaces may be bi-directional, appearing in both Inbound Interface ' .
		'and Outbound Interface lists.'));
$section->add($group);

$section->addInput(new Form_Input(
	'desc',
	'Description',
	'text',
	$pconfig[MCB_CONF_NAME_SERVICE_DESC]
))->setHelp('Description of this bridge for reference (not parsed).');

$form->add($section);

print($form);
?>

<?php include("foot.inc");
