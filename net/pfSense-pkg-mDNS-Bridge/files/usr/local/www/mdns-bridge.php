<?php
/*
 * mdns-bridge.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2024-2025 Denny Page
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
require_once("mdns-bridge.inc");

$shortcut_section = 'mdns-bridge';

// Configuration paths
$package_path = 'installedpackages/mdns-bridge';
$path_enable = 'enable';
$path_carp_vhid = 'carp_vhid';
$path_active_interfaces = 'active_interfaces';
$path_global_ip_protocols = 'global_ip_protocols';
$path_global_filter_type = 'global_filter_type';
$path_global_filter_list = 'global_filter_list';
$path_disable_packet_filtering = 'disable_packet_filtering';
$path_interfaces = 'interfaces';

// Get the current configuration
$current_config = config_get_path($package_path, []);
$pconfig['enable'] = array_get_path($current_config, $path_enable);
$pconfig['carp_vhid'] = array_get_path($current_config, $path_carp_vhid);
$pconfig['active_interfaces'] = explode(',', array_get_path($current_config, $path_active_interfaces, ''));
$pconfig['global_ip_protocols'] = array_get_path($current_config, $path_global_ip_protocols, 'both');
$pconfig['global_filter_type'] = array_get_path($current_config, $path_global_filter_type, 'none');
$pconfig['global_filter_list'] = array_get_path($current_config, $path_global_filter_list, '');
$pconfig['disable_packet_filtering'] = array_get_path($current_config, $path_disable_packet_filtering, false);
$pconfig['interfaces'] = array_get_path($current_config, $path_interfaces, []);

// Avahi conflict
$avahi_enabled = config_get_path('installedpackages/avahi/config/0/enable', false) &&
		 config_get_path('installedpackages/avahi/config/0/reflection', false);

// Get the list of available interfaces
$available_interfaces = get_configured_interface_with_descr();
foreach ($available_interfaces as $interface => $name) {
	if (interface_has_gateway($interface) || interface_has_gatewayv6($interface)) {
		unset($available_interfaces[$interface]);
	}
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	// Check for Avahi conflict
	if ($pconfig['enable'] && $avahi_enabled) {
		$input_errors[] = gettext('Avahi relfection must be disabled before enabling mDNS Bridge');
	}

	// Validate interfaces
	if (count($pconfig['active_interfaces']) < 2) {
		$input_errors[] = gettext('A minimum of two interfaces are required');
	}

	// Validate and normalize the global filter
	if ($pconfig['global_filter_type'] != 'none' && trim($pconfig['global_filter_list']) == '') {
		$pconfig['global_filter_type'] = 'none';
		$pconfig['global_filter_list'] = '';
	}
	if ($pconfig['global_filter_type'] != 'none') {
		$pconfig['disable_packet_filtering'] = false;
		$filter_list = array();
		foreach (explode(',', $pconfig['global_filter_list']) as $filter) {
			$filter = trim($filter);
			if (!is_domain($filter, false, false)) {
				$input_errors[] = sprintf(gettext('Invalid domain in Global Filter List: "%1$s"'), $filter);
			}
			$filter_list[] = $filter;
		}
		$pconfig['global_filter_list'] = implode(', ', $filter_list);
	}

	// Validate and normalize the interface filters
	foreach ($pconfig['active_interfaces'] as $interface) {
		// Inbound filter
		if ($pconfig['inbound_filter_type_' . $interface] != 'none' && trim($pconfig['inbound_filter_list_' . $interface]) == '') {
			$pconfig['inbound_filter_type_' . $interface] = 'none';
			$pconfig['inbound_filter_list_' . $interface] = '';
		}
		if ($pconfig['inbound_filter_type_' . $interface] != 'none') {
			$pconfig['disable_packet_filtering'] = false;
			$filter_list = array();
			foreach (explode(',', $pconfig['inbound_filter_list_' . $interface]) as $filter) {
				$filter = trim($filter);
				if (!is_domain($filter, false, false)) {
					$input_errors[] = sprintf(gettext('Invalid domain in %1$s Inbound Filter List: "%2$s"'),
						convert_friendly_interface_to_friendly_descr($interface), $filter);
				}
				$filter_list[] = $filter;
			}
			$pconfig['inbound_filter_list_' . $interface] = implode(', ', $filter_list);
		}

		// Outbound filter
		if ($pconfig['outbound_filter_type_' . $interface] != 'none' && trim($pconfig['outbound_filter_list_' . $interface]) == '') {
			$pconfig['outbound_filter_type_' . $interface] = 'none';
			$pconfig['outbound_filter_list_' . $interface] = '';
		}
		if ($pconfig['outbound_filter_type_' . $interface] != 'none') {
			$pconfig['disable_packet_filtering'] = false;
			$filter_list = array();
			foreach (explode(',', $pconfig['outbound_filter_list_' . $interface]) as $filter) {
				$filter = trim($filter);
				if (!is_domain($filter, false, false)) {
					$input_errors[] = sprintf(gettext('Invalid domain in %1$s Outbound Filter List: "%2$s"'),
						convert_friendly_interface_to_friendly_descr($interface), $filter);
				}
				$filter_list[] = $filter;
			}
			$pconfig['outbound_filter_list_' . $interface] = implode(', ', $filter_list);
		}
	}

	// Rebuild the interfaces array
	foreach ($available_interfaces as $interface => $name) {
		array_set_path($pconfig, "interfaces/{$interface}/ip_protocols", $pconfig['ip_protocols_' . $interface]);
		array_set_path($pconfig, "interfaces/{$interface}/inbound_filter_type", $pconfig['inbound_filter_type_' . $interface]);
		array_set_path($pconfig, "interfaces/{$interface}/inbound_filter_list", $pconfig['inbound_filter_list_' . $interface]);
		array_set_path($pconfig, "interfaces/{$interface}/outbound_filter_type", $pconfig['outbound_filter_type_' . $interface]);
		array_set_path($pconfig, "interfaces/{$interface}/outbound_filter_list", $pconfig['outbound_filter_list_' . $interface]);
	}

	// Update the config
	if (!$input_errors) {
		// Global settings
		array_set_path($current_config, $path_enable, $pconfig['enable']);
		array_set_path($current_config, $path_carp_vhid, $pconfig['carp_vhid']);
		array_set_path($current_config, $path_active_interfaces, implode(',', $pconfig['active_interfaces']));
		array_set_path($current_config, $path_global_ip_protocols, $pconfig['global_ip_protocols']);
		array_set_path($current_config, $path_global_filter_type, $pconfig['global_filter_type']);
		array_set_path($current_config, $path_global_filter_list, $pconfig['global_filter_list']);
		array_set_path($current_config, $path_disable_packet_filtering, $pconfig['disable_packet_filtering']);

		// Interface settings
		foreach ($pconfig['active_interfaces'] as $interface) {
			array_set_path($current_config, "{$path_interfaces}/{$interface}/ip_protocols", $pconfig['ip_protocols_' . $interface]);
			array_set_path($current_config, "{$path_interfaces}/{$interface}/inbound_filter_type", $pconfig['inbound_filter_type_' . $interface]);
			array_set_path($current_config, "{$path_interfaces}/{$interface}/inbound_filter_list", $pconfig['inbound_filter_list_' . $interface]);
			array_set_path($current_config, "{$path_interfaces}/{$interface}/outbound_filter_type", $pconfig['outbound_filter_type_' . $interface]);
			array_set_path($current_config, "{$path_interfaces}/{$interface}/outbound_filter_list", $pconfig['outbound_filter_list_' . $interface]);
		}

		// Write the config
		config_set_path($package_path, $current_config);
		write_config(gettext("mDNS Bridge settings changed"));

		// Sync the running configuration
		mdns_bridge_sync_config();
	}
}


$ip_protocol_types = array(
	'both' => gettext('IPv4 and IPv6'),
	'ipv4' => gettext('IPv4 only'),
	'ipv6' => gettext('IPv6 only') );

$filter_types = array(
	'none' => gettext('none'),
	'allow' => gettext('Allow'),
	'deny' => gettext('Deny') );

$filter_help_text = gettext(
	'Comma separated list of mDNS names. Most often, a name should ' .
	'be a single label representing a service name such as ' .
	'_printer, _ipp, _ipps, _airplay, _hap, _http or _ssh.');
$filter_placeholder_text = gettext('name1, name2, name3');


$pgtitle = array(gettext("Services"), gettext("mDNS Bridge"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}


$form = new Form;
$section = new Form_Section('General Settings');

// Enable
$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable the mDNS Bridge daemon',
	$pconfig['enable']
));

// CARP
$section->addInput(new Form_Select(
	'carp_vhid',
	'CARP Status VHID',
	$pconfig['carp_vhid'],
	mdns_bridge_get_carp_list()
))->setHelp(gettext('Used for HA MASTER/BACKUP status. mDNS Bridge will be started when the chosen VHID is in MASTER status, and stopped when in BACKUP status.'));

// List of interfaces
$section->addInput(new Form_Select(
	'active_interfaces',
	'*Interfaces',
	$pconfig['active_interfaces'],
	$available_interfaces,
	true
))->addClass('active_interfaces')->setHelp(gettext('Interfaces that the mDNS Bridge daemon will operate on. Two or more interfaces are required.'));
$form->add($section);

$section = new Form_Section('Global Settings');
// Global IP protocol list
$section->addInput(new Form_Select(
	'global_ip_protocols',
	'IP Protocols',
	$pconfig['global_ip_protocols'],
	$ip_protocol_types
))->setHelp(gettext('Select which IP protocols mDNS Bridge will operate on.'));


// Global filter type
$group = new Form_Group('Global Filter');
$group->add(new Form_Select(
	'global_filter_type',
	null,
	$pconfig['global_filter_type'],
	$filter_types
))->setHelp(gettext('The global filter is applied to incoming packets on all interfaces prior to any interface specific filters.'));
$section->add($group);

// Conditional text explaining the currently selected filter type
$group = new Form_Group(null);
$group->addClass('sh_global_filter_none');
$group->add(new Form_StaticText(
	null,
	sprintf('<b>' . gettext('All mDNS names are allowed by default.') . '</b>')));
$section->add($group);
$group = new Form_Group(null);
$group->addClass('sh_global_filter_allow');
$group->add(new Form_StaticText(
	null,
	sprintf('<b>' . gettext('mDNS names that do not match an entry in the ' .
		'Global Filter List will be dropped from packets received ' .
		'on all interfaces.') . '</b>')));
$section->add($group);
$group = new Form_Group(null);
$group->addClass('sh_global_filter_deny');
$group->add(new Form_StaticText(
	null,
	sprintf('<b>' . gettext('mDNS names that match an entry in the ' .
		'Global Filter List will be dropped from packets received ' .
		'on all interfaces.') . '</b>')));
$section->add($group);

$group = new Form_Group('Global Filter List');
$group->addClass('sh_global_filter_list');
$group->add(new Form_Input(
	'global_filter_list',
	null,
	'text',
	$pconfig['global_filter_list']
))->setHelp($filter_help_text)->setWidth(7)->setAttribute('placeholder', $filter_placeholder_text);
$section->add($group);

$form->add($section);

// Interface sections
foreach ($available_interfaces as $interface => $name) {
	$interface_config = array_get_path($pconfig, "interfaces/{$interface}", []);

	$section = new Form_Section($name . ' Settings');
	$section->addClass('sh_interface');
	$section->addClass('sh_interface_' . $interface);

	// IP protocols
	$group = new Form_Group('IP Protocols');
	$group->addClass('sh_interface_protocols');
	$group->add(new Form_Select(
		'ip_protocols_' . $interface,
		null,
		$interface_config['ip_protocols'],
		$ip_protocol_types
	))->setHelp(gettext('Select which IP protocols mDNS Bridge will operate on.'));
	$section->add($group);

	// Inbound filter type
	$group = new Form_Group('Inbound Filter');
	$group->add(new Form_Select(
		'inbound_filter_type_' . $interface,
		null,
		$interface_config['inbound_filter_type'],
		$filter_types
	))->setHelp(gettext('The inbound filter is applied to packets received on the interface following the global filter.'));
	$section->add($group);

	// Conditional text explaining the currently selected inbound filter type
	$group = new Form_Group(null);
	$group->addClass('sh_interface_inbound_filter_none_' . $interface);
	$group->add(new Form_StaticText(
		null,
		sprintf('<b>' . gettext('All mDNS names are allowed inbound on the interface.') .
		'</b>')));
	$section->add($group);
	$group = new Form_Group(null);
	$group->addClass('sh_interface_inbound_filter_allow_' . $interface);
	$group->add(new Form_StaticText(
		null,
		sprintf('<b>' . gettext('mDNS names that do not match an entry in the ' .
			'Inbound Filter List will be dropped from packets received on the ' .
			'interface.') . '</b>')));
	$section->add($group);
	$group = new Form_Group(null);
	$group->addClass('sh_interface_inbound_filter_deny_' . $interface);
	$group->add(new Form_StaticText(
		null,
		sprintf('<b>' . gettext('mDNS names that match an entry in the ' .
			'Inbound Filter List will be dropped from packets received on the ' .
			'interface.') . '</b>')));
	$section->add($group);

	$group = new Form_Group('Inbound Filter List');
	$group->addClass('sh_interface_inbound_filter_list_' . $interface);
	$group->add(new Form_Input(
		'inbound_filter_list_' . $interface,
		null,
		'text',
		array_get_path($interface_config, 'inbound_filter_list',''))
	)->setHelp($filter_help_text)->setWidth(7)->setAttribute('placeholder', $filter_placeholder_text);
	$section->add($group);


	// Outbound filter type
	$group = new Form_Group('Outbound Filter');
	$group->add(new Form_Select(
		'outbound_filter_type_' . $interface,
		null,
		$interface_config['outbound_filter_type'],
		$filter_types
	))->setHelp(gettext('The outbound filter is applied to packets prior to sending packets on the interface.'));
	$section->add($group);

	// Conditional text explaining the currently selected outbound filter type
	$group = new Form_Group(null);
	$group->addClass('sh_interface_outbound_filter_none_' . $interface);
	$group->add(new Form_StaticText(
		null,
		sprintf('<b>' . gettext('All mDNS names are allowed outbound on the interface.') .
		'</b>')));
	$section->add($group);
	$group = new Form_Group(null);
	$group->addClass('sh_interface_outbound_filter_allow_' . $interface);
	$group->add(new Form_StaticText(
		null,
		sprintf('<b>' . gettext('mDNS names that do not match an entry in the ' .
			'Outbound Filter List will be excluded from packets sent on the ' .
			'interface.') . '</b>')));
	$section->add($group);
	$group = new Form_Group(null);
	$group->addClass('sh_interface_outbound_filter_deny_' . $interface);
	$group->add(new Form_StaticText(
		null,
		sprintf('<b>' . gettext('mDNS names that match an entry in the ' .
			'Outbound Filter List will be excluded from packets sent on the ' .
			'interface.') . '</b>')));
	$section->add($group);

	// Outbound filter list
	$group = new Form_Group('Outbound Filter List');
	$group->addClass('sh_interface_outbound_filter_list_' . $interface);
	$group->add(new Form_Input(
		'outbound_filter_list_' . $interface,
		null,
		'text',
		array_get_path($interface_config, 'outbound_filter_list',''))
	)->setHelp($filter_help_text)->setWidth(7)->setAttribute('placeholder', $filter_placeholder_text);
	$section->add($group);

	$form->add($section);
}

// Advanced option to Disable all packet filtering
$section = new Form_Section('Advanced');
$section->addClass('sh_disable_packet_filtering');
$section->addInput(new Form_Checkbox(
	'disable_packet_filtering',
	'Disable Packet Filtering',
	'Completely disable packet decoding and filtering',
	$pconfig['disable_packet_filtering']
))->setHelp(gettext('Selecting this option will cause packets received from one interface to be forwarded directly to neighboring interfaces without any further processing. <b>This option disables all mDNS packet validation and filtering, including link local addresses. Use this option with extreme caution.</b>'));
$form->add($section);

print($form);
?>


<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var available_interfaces = <?=json_encode(array_keys($available_interfaces))?>;

	// Show/hide interface sections
	function hideInterfaces() {
		hideClass('sh_interface', true);

		var selected = $(".active_interfaces").val();
		var length = $(".active_interfaces :selected").length;
		for (var i = 0; i < length; i++) {
			hideClass('sh_interface_' + selected[i], false);
		}
	}

	// Show/hide interface ip protocols based on global ip protocols
	function hideInterfaceProtocols() {
		hideClass('sh_interface_protocols', $('#global_ip_protocols').prop('value') != 'both');
	}

	// Show/hide advanced
	function hideAdvanced() {
		showadvanced = $('#global_filter_type').prop('value') == 'none';
		if (showadvanced) {
			var selected = $(".active_interfaces").val();
			var length = $(".active_interfaces :selected").length;
			for (var i = 0; i < length; i++) {
				if ($('#inbound_filter_type_' + selected[i]).prop('value') != 'none' ||
				    $('#outbound_filter_type_' + selected[i]).prop('value') != 'none') {
					showadvanced = false;
					break;
				}
			}
		}
		hideClass('sh_disable_packet_filtering', !showadvanced);
	}

	// Show/hide based on global filter
	function hideGlobalFilter() {
		hideClass('sh_global_filter_none', $('#global_filter_type').prop('value') != 'none');
		hideClass('sh_global_filter_allow', $('#global_filter_type').prop('value') != 'allow');
		hideClass('sh_global_filter_deny', $('#global_filter_type').prop('value') != 'deny');
		hideClass('sh_global_filter_list', $('#global_filter_type').prop('value') == 'none');
	}

	// Show/hide based on interface filters
	function hideInterfaceFilter(interface, direction) {
		hideClass('sh_interface_' + direction + '_filter_none_' + interface, ($('#' + direction + '_filter_type_' + interface).prop('value') != 'none'));
		hideClass('sh_interface_' + direction + '_filter_allow_' + interface,($('#' + direction + '_filter_type_' + interface).prop('value') != 'allow'));
		hideClass('sh_interface_' + direction + '_filter_deny_' + interface, ($('#' + direction + '_filter_type_' + interface).prop('value') != 'deny'));
		hideClass('sh_interface_' + direction + '_filter_list_' + interface, ($('#' + direction + '_filter_type_' + interface).prop('value') == 'none'));
	}

	// On changing selection for active interfaces
	$('.active_interfaces').change(function () {
		hideInterfaces();
		hideAdvanced();
	});

	// On changing selection for global ip protocols
	$('#global_ip_protocols').change(function() {
		hideInterfaceProtocols();
	});

	// On changing selection for global filter
	$('#global_filter_type').change(function() {
		hideGlobalFilter();
		hideAdvanced();
	});

	// On changing selection for interface filters
	$("select[id^='inbound_filter_type_']").change(function() {
		let result;
		result = $(this).attr('id').match(/^inbound_filter_type_([\w]+)/);
		if (result && available_interfaces.includes(result[1])) {
			hideInterfaceFilter(result[1], 'inbound');
			hideAdvanced();
		}
	});
	$("select[id^='outbound_filter_type_']").change(function() {
		let result;
		result = $(this).attr('id').match(/^outbound_filter_type_([\w]+)/);
		if (result && available_interfaces.includes(result[1])) {
			hideInterfaceFilter(result[1], 'outbound');
			hideAdvanced();
		}
	});

	// Initial page load
	hideInterfaces();
	hideInterfaceProtocols();
	hideGlobalFilter();
	hideAdvanced();

	for (let interface of available_interfaces) {
		hideInterfaceFilter(interface, 'inbound');
		hideInterfaceFilter(interface, 'outbound');
	}

});
//]]>
</script>

<?php include("foot.inc");
