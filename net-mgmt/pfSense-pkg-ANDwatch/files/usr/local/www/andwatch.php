<?php
/*
 * andwatch.php
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
require_once("andwatch.inc");


// Configuration paths
$package_path = 'installedpackages/andwatch';
$path_enable = 'enable';
$path_active_interfaces = 'active_interfaces';
$path_interfaces = 'interfaces';

// Get the current configuration
$current_config = config_get_path($package_path, []);
$pconfig['enable'] = array_get_path($current_config, $path_enable);
$pconfig['active_interfaces'] = array_filter(explode(',', array_get_path($current_config, $path_active_interfaces, '')));
$pconfig['interfaces'] = array_get_path($current_config, $path_interfaces, []);

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

	if (empty($pconfig['active_interfaces'])) {
		$input_errors[] = gettext('No interfaces selected');
	}

	// Rebuild the interfaces array
	foreach ($available_interfaces as $interface => $name) {
		$notifications = $pconfig['notifications_' . $interface];
		$expiration = $pconfig['expiration_' . $interface];
		$pcap_filter = $pconfig['pcap_filter_' . $interface];
		$custom_filter = trim($pconfig['custom_filter_' . $interface]);

		// Validate custom filter
		if ($pcap_filter == 'custom' && strlen($custom_filter) > 100) {
			$input_errors[] = 'custom filter exceeds maximum length of 100 bytes';
		}

		array_set_path($pconfig, "interfaces/{$interface}/notifications", $notifications);
		array_set_path($pconfig, "interfaces/{$interface}/expiration", $expiration);
		array_set_path($pconfig, "interfaces/{$interface}/pcap_filter", $pcap_filter);
		array_set_path($pconfig, "interfaces/{$interface}/custom_filter", $custom_filter);
	}

	// Update the config
	if (!$input_errors) {
		// General settings
		array_set_path($current_config, $path_enable, $pconfig['enable']);
		array_set_path($current_config, $path_active_interfaces, implode(',', $pconfig['active_interfaces']));

		// Interface settings
		foreach ($pconfig['active_interfaces'] as $interface) {
			array_set_path($current_config, "{$path_interfaces}/{$interface}/notifications",
					array_get_path($pconfig, "interfaces/{$interface}/notifications"));
			array_set_path($current_config, "{$path_interfaces}/{$interface}/expiration",
					array_get_path($pconfig, "interfaces/{$interface}/expiration"));
			array_set_path($current_config, "{$path_interfaces}/{$interface}/pcap_filter",
					array_get_path($pconfig, "interfaces/{$interface}/pcap_filter"));
			array_set_path($current_config, "{$path_interfaces}/{$interface}/custom_filter",
					array_get_path($pconfig, "interfaces/{$interface}/custom_filter"));
		}

		// Write the config
		config_set_path($package_path, $current_config);
		write_config(gettext("ANDwatch settings changed"));

		// Sync the running configuration
		andwatch_sync_config();
	}
}


$pcap_filter_options = array(
	'none' => gettext('none'),
	'link-local' => gettext('Ignore link-local addresses (169.254.0.0/16, fe80::/10)'),
	'link-local-unique' => gettext('Ignore link-local and unique local addresses (169.254.0.0/16, fe80::/10, fc00::/7)'),
	'custom' => gettext('Custom filter') );

$expiration_options = array(
	'5' => gettext('5 days'),
	'10' => gettext('10 days'),
	'15' => gettext('15 days'),
	'20' => gettext('20 days'),
	'30' => gettext('30 days (default)'),
	'60' => gettext('60 days'),
	'90' => gettext('90 days') );

$notification_help = gettext(
	'When ANDwatch is first enabled on an interface, a lot of notifications may be ' .
	'generated very quickly.<br>It is generally recommended to allow ANDwatch to ' .
	'run for several minutes on a new interface before<br>enabling notifications.');

$expiration_help = gettext(
	'Records that have not been updated in this many days are deleted by the ' .
	'ANDwatch daemon');

$pcap_filter_help = gettext(
	'Additional PCAP filter to be applied to ARP and Neighbor Discovery packets ' .
	'received on the interface.');

$custom_filter_help = gettext(
	'A filter as used by pcap/tcpdump. For information on filter formats, see the ' .
	'<a target="_blank" href="https://www.tcpdump.org/manpages/pcap-filter.7.html">' .
	'pcap-filter man page' . '</a>.<br>' .
	'Filters can be validated prior to use by using the -d option to tcpdump.');

$custom_filter_placeholder = gettext('not net 169.254.0.0/16 and not net fe80::0/10 and not net fc00::0/7');


$pgtitle = array(gettext("Services"), gettext("ANDwatch"));
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
	'Enable the ANDwatch daemon(s)',
	$pconfig['enable']
));

// List of interfaces
$section->addInput(new Form_Select(
	'active_interfaces',
	'*Interfaces',
	$pconfig['active_interfaces'],
	$available_interfaces,
	true
))->addClass('active_interfaces')->setHelp(gettext('Interfaces that the ANDwatch daemon will operate on.'));
$form->add($section);


// Interface sections
foreach ($available_interfaces as $interface => $name) {
	$interface_config = array_get_path($pconfig, "interfaces/{$interface}", []);

	$section = new Form_Section($name . ' Settings');
	$section->addClass('sh_interface');
	$section->addClass('sh_interface_' . $interface);

	// Notifications
	$section->addInput(new Form_Checkbox(
		'notifications_' . $interface,
		'Notifications',
		'Enable notifications from the ANDwatch daemon for this interface',
		$interface_config['notifications']
	))->setHelp($notification_help);

	// Record expiration
	if (is_null($interface_config['expiration'])) {
		$interface_config['expiration'] = '30';
	}
	$group = new Form_Group('Record Expiration');
	$group->add(new Form_Select(
		'expiration_' . $interface,
		null,
		$interface_config['expiration'],
		$expiration_options
	))->setHelp($expiration_help);
	$section->add($group);

	// PCAP filter
	if (is_null($interface_config['pcap_filter'])) {
		$interface_config['pcap_filter'] = 'link-local-unique';
	}
	$group = new Form_Group('PCAP Filter');
	$group->add(new Form_Select(
		'pcap_filter_' . $interface,
		null,
		$interface_config['pcap_filter'],
		$pcap_filter_options
	))->setWidth(7)->setHelp($pcap_filter_help);
	$section->add($group);

	// Custom filter
	$group = new Form_Group('Custom Filter');
	$group->addClass('sh_interface_custom_filter_' . $interface);
	$group->add(new Form_Input(
		'custom_filter_' . $interface,
		null,
		'text',
		$interface_config['custom_filter'])
	)->setWidth(7)->setHelp($custom_filter_help)->setAttribute('placeholder', $custom_filter_placeholder);
	$section->add($group);

	$form->add($section);
}


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

	// Show/hide based on interface filters
	function hideInterfaceCustomFilter(interface) {
		hideClass('sh_interface_custom_filter_' + interface, $('#pcap_filter_' + interface).prop('value') != 'custom');
	}

	// On changing selection for active interfaces
	$('.active_interfaces').change(function () {
		hideInterfaces();
	});

	// On changing selection for interface filter type
	$("select[id^='pcap_filter_']").change(function() {
		let result;
		result = $(this).attr('id').match(/^pcap_filter_([\w]+)/);
		if (result && available_interfaces.includes(result[1])) {
			hideInterfaceCustomFilter(result[1]);
		}
	});

	// Initial page load
	hideInterfaces();
	for (let interface of available_interfaces) {
		hideInterfaceCustomFilter(interface);
	}

});
//]]>
</script>

<?php include("foot.inc");
