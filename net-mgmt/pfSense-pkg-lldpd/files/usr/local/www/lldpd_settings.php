<?php
/*
 * lldpd_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2018 Denny Page
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
require_once("interfaces.inc");
require_once("/usr/local/pkg/lldpd/lldpd.inc");


if (!is_array($config['installedpackages']['lldpd']['config'])) {
	$config['installedpackages']['lldpd']['config'] = array();
}
$a_lldpd = &$config['installedpackages']['lldpd']['config'][0];
if (isset($a_lldpd['interfaces'])) {
	$pconfig = $a_lldpd;
} else {
	$pconfig['interfaces'] = 'lan';
	$pconfig['chassis'] = 'lan';
	$pconfig['management'] = 'lan';
	$pconfig['lldp_proto'] = 'active';
}


if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	$pconfig['interfaces'] = implode(',', $pconfig['interfaces_a']);

	/* Confirm at least one interface has been chosen */
	if (empty($pconfig['interfaces_a'])) {
		$input_errors[] = gettext("At least one interface must be selected");
	}

	if (!$pconfig['receiveonly']) {
		/* Confirm IP on management interface */
		$ipv4 = get_interface_ip($pconfig['management']);
		$ipv6 = get_interface_ipv6($pconfig['management']);
		if (!(isset($ipv4) or isset($ipv6))) {
			$input_errors[] = gettext("The management interface must have an IP address");
		}
	}

	if (!$input_errors) {
		$lldpd = array();
		$lldpd['enable'] = $pconfig['enable'];
		$lldpd['receiveonly'] = $pconfig['receiveonly'];
		$lldpd['interfaces'] = $pconfig['interfaces'];
		$lldpd['chassis'] = $pconfig['chassis'];
		$lldpd['management'] = $pconfig['management'];

		if (!$pconfig['receiveonly']) {
			$lldpd['lldp_proto'] = $pconfig['lldp_proto'];
			$lldpd['cdp_proto'] = $pconfig['cdp_proto'];
			$lldpd['edp_proto'] = $pconfig['edp_proto'];
			$lldpd['fdp_proto'] = $pconfig['fdp_proto'];
			$lldpd['ndp_proto'] = $pconfig['ndp_proto'];
		} else {
			$lldpd['lldp_proto'] = $pconfig['lldp_proto_ro'];
			$lldpd['cdp_proto'] = $pconfig['cdp_proto_ro'];
			$lldpd['edp_proto'] = $pconfig['edp_proto_ro'];
			$lldpd['fdp_proto'] = $pconfig['fdp_proto_ro'];
			$lldpd['ndp_proto'] = $pconfig['ndp_proto_ro'];
		}

		$a_lldpd = $lldpd;
		write_config("Updated LLDP settings");

		lldpd_sync_config();
		header("Location: lldpd_status.php");
		exit;
	}
}


$protocol_options = array(
	'disabled' => gettext('Disabled'),
	'passive' => gettext('Passive'),
	'active' => gettext('Active') );

$protocol_options_cdp = array(
	'disabled' => gettext('Disabled'),
	'passive' => gettext('Passive V1 and V2'),
	'passive_v2_only' => gettext('Passive V2 only'),
	'active_v1_passive_v2' => gettext('Active V1, Passive V2'),
	'active_v2_passive_v1' => gettext('Active V2, Passive V1'),
	'active_v2_only' => gettext('Active V2 only') );

$protocol_options_ro = array(
	'disabled' => gettext('Disabled'),
	'passive' => gettext('Enabled') );

$protocol_options_cdp_ro = array(
	'disabled' => gettext('Disabled'),
	'passive' => gettext('Enable V1 and V2'),
	'passive_v2_only' => gettext('Enable V2 only') );

$available_interfaces = get_configured_interface_with_descr();
$pconfig['interfaces_a'] = explode(',', $pconfig['interfaces']);


$pgtitle = array(gettext("Services"), gettext("LLDP"), gettext("Settings"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("LLDP Status"), false, "/lldpd_status.php");
$tab_array[] = array(gettext("LLDP Settings"), true, "/lldpd_settings.php");
display_top_tabs($tab_array);


if ($input_errors) {
	print_input_errors($input_errors);
}


/* General input */

$form = new Form;
$section = new Form_Section('General Settings');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable the LLDP daemon',
	$pconfig['enable']
));

$section->addInput(new Form_Checkbox(
	'receiveonly',
	'Receive Only Mode',
	'Do not transmit discovery frames, even in response to received frames',
	$pconfig['receiveonly']
));

$section->addInput(new Form_Select(
	'interfaces_a',
	'Interfaces',
	$pconfig['interfaces_a'],
	$available_interfaces,
	true
))->setHelp(
	'Interfaces that lldpd will listen and send on'
	);

$section->addInput(new Form_Select(
	'chassis',
	'Chassis Interface',
	$pconfig['chassis'],
	$available_interfaces
))->setHelp(
	'Interface that lldpd will use for chassis identification (usually LAN)'
	);

$section->addInput(new Form_Select(
	'management',
	'IP Management Interface',
	$pconfig['management'],
	$available_interfaces
))->setHelp(
	'Interface that lldpd will use for management addresses (usually LAN)'
	);

$form->add($section);

$section = new Form_Section('Protocol Support');

/* Protocols */

$group = new Form_Group('Link Layer Discover Protocol (LLDP)');
$group->add(new Form_Select(
	'lldp_proto',
	null,
	$pconfig['lldp_proto'],
	$protocol_options
));
$group->addClass('proto');
$section->add($group);

$group = new Form_Group('Cisco Discovery Protocol (CDP)');
$group->add(new Form_Select(
	'cdp_proto',
	null,
	$pconfig['cdp_proto'],
	$protocol_options_cdp
));
$group->addClass('proto');
$section->add($group);

$group = new Form_Group('Extreme Discovery Protocol (EDP)');
$group->add(new Form_Select(
	'edp_proto',
	null,
	$pconfig['edp_proto'],
	$protocol_options
));
$group->addClass('proto');
$section->add($group);

$group = new Form_Group('Foundry Discovery Protocol (FDP)');
$group->add(new Form_Select(
	'fdp_proto',
	null,
	$pconfig['fdp_proto'],
	$protocol_options
));
$group->addClass('proto');
$section->add($group);

$group = new Form_Group('Nortel Discovery Protocol (NDP)');
$group->add(new Form_Select(
	'ndp_proto',
	null,
	$pconfig['ndp_proto'],
	$protocol_options
));
$group->addClass('proto');
$section->add($group);

/* Receive only variants of protocols */

$group = new Form_Group('Link Layer Discover Protocol (LLDP)');
$group->add(new Form_Select(
	'lldp_proto_ro',
	null,
	$pconfig['lldp_proto'],
	$protocol_options_ro
));
$group->addClass('proto_ro');
$section->add($group);

$group = new Form_Group('Cisco Discovery Protocol (CDP)');
$group->add(new Form_Select(
	'cdp_proto_ro',
	null,
	$pconfig['cdp_proto'],
	$protocol_options_cdp_ro
));
$group->addClass('proto_ro');
$section->add($group);

$group = new Form_Group('Extreme Discovery Protocol (EDP)');
$group->add(new Form_Select(
	'edp_proto_ro',
	null,
	$pconfig['edp_proto'],
	$protocol_options_ro
));
$group->addClass('proto_ro');
$section->add($group);

$group = new Form_Group('Foundry Discovery Protocol (FDP)');
$group->add(new Form_Select(
	'fdp_proto_ro',
	null,
	$pconfig['fdp_proto'],
	$protocol_options_ro
));
$group->addClass('proto_ro');
$section->add($group);

$group = new Form_Group('Nortel Discovery Protocol (NDP)');
$group->add(new Form_Select(
	'ndp_proto_ro',
	null,
	$pconfig['ndp_proto'],
	$protocol_options_ro
));
$group->addClass('proto_ro');
$section->add($group);


$form->add($section);
print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	function map_proto_ro(value) {
		if (value == 'disabled') {
			return value;
		} else {
			return 'passive';
		}
	}

	function map_proto_cdp_ro(value) {
		if (value == 'disabled' || value == 'passive' || value == 'passive_v2_only') {
			return value;
		} else if (value == 'active_v2_only') {
			return 'passive_v2_only';
		} else {
			return 'passive';
		}
	}

	function receiveonlyChange(enabled) {
		if (enabled) {
			$('#lldp_proto_ro').val(map_proto_ro($('#lldp_proto').val));
			$('#cdp_proto_ro').val(map_proto_cdp_ro($('#cdp_proto').val));
			$('#edp_proto_ro').val(map_proto_ro($('#edp_proto').val));
			$('#fdp_proto_ro').val(map_proto_ro($('#fdp_proto').val));
			$('#ndp_proto_ro').val(map_proto_ro($('#ndp_proto').val));

			hideInput('chassis', true);
			hideInput('management', true);
			hideClass('proto', true);
			hideClass('proto_ro', false);
		} else {
			hideInput('chassis', false);
			hideInput('management', false);
			hideClass('proto', false);
			hideClass('proto_ro', true);
		}
	}

	$('#receiveonly').click(function() {
		receiveonlyChange($(this).prop("checked"));
	});

	// Initial page load
	receiveonlyChange($('#receiveonly').prop("checked"));

});
//]]>
</script>

<?php include("foot.inc");
