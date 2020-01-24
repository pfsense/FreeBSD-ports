<?php
/*
 * avahi_settings.php
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


require("guiconfig.inc");
require("/usr/local/pkg/avahi/avahi.inc");

if (!is_array($config['installedpackages']['avahi']['config'])) {
	$config['installedpackages']['avahi']['config'] = array();
}
$a_avahi = &$config['installedpackages']['avahi']['config'][0];

$actions = array(
	"allow" => gettext("Allow Interfaces"),
	"deny" => gettext("Deny Interfaces"),
);

if (isset($a_avahi)) {
	if (isset($a_avahi['interfaces'])) {
		$pconfig = $a_avahi;
	} elseif (isset($a_avahi['denyinterfaces'])) {
		$pconfig['enable'] = $a_avahi['enable'];
		$pconfig['carpstatusvid'] = $a_avahi['carpstatusvid'];

		$available_interfaces = get_configured_interface_list();
		unset($available_interfaces['wan']);
		$deny_interfaces = explode(',', $a_avahi['denyinterfaces']);
		foreach ($deny_interfaces as $i) {
			unset($available_interfaces[$i]);
		}
		$pconfig['interfaces'] = implode(',', $available_interfaces);

		$pconfig['disable_ipv4'] = !$a_avahi['enable_ipv4'];
		$pconfig['disable_ipv6'] = !$a_avahi['enable_ipv6'];
		/* override_hostname was not available in prior config */
		/* override_domain was not available in prior config */

		$pconfig['publishing'] = !$a_avahi['disable_publishing'];
		if ($pconfig['publishing']) {
			$pconfig['publish_addresses'] = $a_avahi['publish_addresses'];
			$pconfig['publish_hinfo'] = $a_avahi['publish_hinfo'];
			$pconfig['publish_workstation'] = $a_avahi['publish_workstation'];
			/* publish_domain was not available in prior config */
			$pconfig['publish_ipv4_aaaa'] = $a_avahi['publish_aaaa_on_ipv4'];
			$pconfig['publish_ipv6_a'] = $a_avahi['publish_a_on_ipv6'];
		}

		$pconfig['reflection'] = $a_avahi['enable_reflector'];

		$migration_warning = gettext("WARNING: An attempt has been made to migrate the prior Avahi configuration. Please review the settings below carefully before saving. The prior Avahi configuration will be lost when the new configuration is saved.");
	} else {
		$pconfig['interfaces'] = 'lan';
	}
}


if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (isset($pconfig['interfaces_a']) && is_array($pconfig['interfaces_a'])) {
		$pconfig['interfaces'] = implode(',', $pconfig['interfaces_a']);
	}

	/* Confirm that at least one protocol is enabled */
	if ($pconfig['disable_ipv4'] == "yes" && $pconfig['disable_ipv6'] == "yes") {
		$input_errors[] = gettext("At least one IP protocol must be enabled");
	}

	/* Confirm valid characters in host and domain names if specified */
	if ($pconfig['override_hostname'] && !preg_match('/^[a-zA-Z0-9-]+$/', $pconfig['override_hostname'])) {
		$input_errors[] = gettext("Host name may contain [a-zA-Z0-9-] only");
	}
	if ($pconfig['override_domain'] && !preg_match('/^[a-zA-Z0-9-.]+$/', $pconfig['override_domain'])) {
		$input_errors[] = gettext("Domain name may contain [a-zA-Z0-9-.] only");
	}
	if (!array_key_exists($pconfig['action'], $actions)) {
		$input_errors[] = gettext("Invalid interface action");
	}

	if (!$input_errors) {
		$avahi = array();

		$avahi['enable'] = $pconfig['enable'];
		$avahi['carpstatusvid'] = $pconfig['carpstatusvid'];
		$avahi['action'] = $pconfig['action'];
		$avahi['interfaces'] = $pconfig['interfaces'];
		$avahi['disable_ipv4'] = $pconfig['disable_ipv4'];
		$avahi['disable_ipv6'] = $pconfig['disable_ipv6'];
		$avahi['reflection'] = $pconfig['reflection'];
		$avahi['override_hostname'] = $pconfig['override_hostname'];
		$avahi['override_domain'] = $pconfig['override_domain'];

		$avahi['publishing'] = $pconfig['publishing'];
		if ($pconfig['publishing']) {
			$avahi['publish_addresses'] = $pconfig['publish_addresses'];
			$avahi['publish_hinfo'] = $pconfig['publish_hinfo'];
			$avahi['publish_workstation'] = $pconfig['publish_workstation'];
			$avahi['publish_domain'] = $pconfig['publish_domain'];
			$avahi['publish_ipv4_aaaa'] = $pconfig['publish_ipv4_aaaa'];
			$avahi['publish_ipv6_a'] = $pconfig['publish_ipv6_a'];
		}

		$a_avahi = $avahi;
		write_config("Updated Avahi settings");

		avahi_sync_config();

		header("Location: avahi_settings.php");
		exit;
	}
}


$available_interfaces = get_configured_interface_with_descr();
unset($available_interfaces['wan']);

if (!empty($pconfig['interfaces'])) {
	$pconfig['interfaces_a'] = explode(',', $pconfig['interfaces']);
}

$pgtitle = array(gettext("Services"), gettext("Avahi"));
include("head.inc");

if (isset($migration_warning)) {
	print_info_box($migration_warning);
}

if ($input_errors) {
	print_input_errors($input_errors);
}


$form = new Form;
$section = new Form_Section('General Settings');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable the Avahi daemon',
	$pconfig['enable']
));

$section->addInput(new Form_Select(
	'carpstatusvid',
	'CARP Status VIP',
	$pconfig['carpstatusvid'],
	avahi_get_carp_list()
))->setHelp('Used to determine the HA MASTER/BACKUP status. Avahi will be stopped when the chosen VIP is in BACKUP status, and started in MASTER status.');

$section->addInput(new Form_Select(
	'action',
	'Interface Action',
	$pconfig['action'],
	$actions,
	false
))->setHelp(
	'Specify whether the interfaces selected below will be allowed or denied. ' .
	'When using Deny mode, take care to select WANs and other interfaces where binding Avahi could be dangerous. ' .
	'Also note that in Deny mode, Avahi will bind to unassigned interfaces.'
);

$section->addInput(new Form_Select(
	'interfaces_a',
	'Interfaces',
	$pconfig['interfaces_a'],
	$available_interfaces,
	true
))->setHelp(
	'Interfaces that the Avahi daemon will listen and send on (Allow mode) ' .
	'or be prevented from listening or sending on (Deny mode). ' .
	'If empty, Avahi will listen on all available interfaces.'
);

$section->addInput(new Form_Checkbox(
	'disable_ipv4',
	'Disable IPv4',
	'Disable support for IPv4',
	$pconfig['disable_ipv4']
));

$section->addInput(new Form_Checkbox(
	'disable_ipv6',
	'Disable IPv6',
	'Disable support for IPv6',
	$pconfig['disable_ipv6']
));

$section->addInput(new Form_Checkbox(
	'reflection',
	'Enable reflection',
	'Repeat mdns packets across subnets',
	$pconfig['reflection']
))->setHelp(
	'This option allows clients in one subnet to browse for clients and services located in different subnets.'
);

$form->add($section);


$section = new Form_Section('Publishing');

$section->addInput(new Form_Checkbox(
	'publishing',
	'Enable publishing',
	'Enable publishing of information about the pfSense host',
	$pconfig['publishing']
))->setHelp(
	'Use with caution. Publishing can reveal a good deal of information about your pfSense host.%sNote that this option enables publishing of default system services such as ssh and ftp-ssh.', '<br/>'
);

$group = new Form_Group('Publish addresses');
$group->addClass('publishing');
$group->add(new Form_Checkbox(
	'publish_addresses',
	'Publish addresses',
	'Publish address records for the pfSense host',
	$pconfig['publish_addresses']
));
$section->add($group);

$group = new Form_Group('Publish host info');
$group->addClass('publishing');
$group->add(new Form_Checkbox(
	'publish_hinfo',
	'Publish host info',
	'Publish a host information record (OS and CPU info) for the pfSense host',
	$pconfig['publish_hinfo']
));
$section->add($group);

$group = new Form_Group('Publish workstation');
$group->addClass('publishing');
$group->add(new Form_Checkbox(
	'publish_workstation',
	'Publish workstation',
	'Publish a workstation record for the pfSense host',
	$pconfig['publish_workstation']
));
$section->add($group);

$group = new Form_Group('Publish domain');
$group->addClass('publishing');
$group->add(new Form_Checkbox(
	'publish_domain',
	'Publish Domain',
	'Publish the domain name in use by the pfSense host',
	$pconfig['publish_domain']
));
$section->add($group);

$button = new Form_Button(
	'advancedbutton',
	'Display Advanced',
	null,
	'fa-cog'
);
$button->setAttribute('type', 'button')->addClass('btn-info btn-sm');
$section->addInput(new Form_StaticText(
	null,
	$button
));

$form->add($section);


$section = new Form_Section('Advanced settings');
$section->addClass('advanced');

$group = new Form_Group('Override host name');
$group->add(new Form_Input(
	'override_hostname',
	'Override host name',
	'text',
	$pconfig['override_hostname']
))->sethelp('Override the host name used for publishing mdns records. The default is the system host name.');
$section->add($group);

$group = new Form_Group('Override domain');
$group->add(new Form_Input(
	'override_domain',
	'Override domain',
	'text',
	$pconfig['override_domain']
))->sethelp('Override the domain name used for publishing mdns records. The default is "local".');
$section->add($group);

$group = new Form_Group('IPv4 AAAA records');
$group->addClass('publishing');
$group->add(new Form_Checkbox(
	'publish_ipv4_aaaa',
	'IPv4 AAAA records',
	'Enable publishing of local IPv6 addresses (AAAA records) via IPv4',
	$pconfig['publish_ipv4_aaaa']
));
$section->add($group);

$group = new Form_Group('IPv6 A records');
$group->addClass('publishing');
$group->add(new Form_Checkbox(
	'publish_ipv6_a',
	'IPv6 A records',
	'Enable publishing of local IPv4 addresses (A records) via IPv6',
	$pconfig['publish_ipv6_a']
));
$section->add($group);
$form->add($section);

print($form);
?>


<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var showadvanced = false;

	function publishingChange() {
		var hide = !$('#publishing').prop('checked')
		hideClass('publishing', hide);
	}

	function IPv4Change() {
		var hide = $('#disable_ipv4').prop('checked')
		disableInput('publish_ipv4_aaaa', hide);
	}
	function IPv6Change() {
		var hide = $('#disable_ipv6').prop('checked')
		disableInput('publish_ipv6_a', hide);
	}

	function advancedChange(pageload) {
		var text;

		if (pageload) {
			// Initial page load
			showadvanced = <?php
				if (empty($pconfig['hostname']) && empty($pconfig['domain']) && !$pconfig['publish_ipv4_aaaa'] && !$pconfig['publish_ipv6_a']) {
					echo 'false';
				} else {
					echo 'true';
				}
			?>
		} else {
			showadvanced = !showadvanced;
		}

		hideClass('advanced', !showadvanced);

		if (showadvanced) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#advancedbutton').html('<i class="fa fa-cog"></i> ' + text);
	}

	// Show/Hide publish settings
	$('#publishing').click(function() {
		publishingChange();
	});

	$('#disable_ipv4').click(function() {
		IPv4Change();
	});
	$('#disable_ipv6').click(function() {
		IPv6Change();
	});

	// Show/Hide advanced section
	$('#advancedbutton').click(function(event) {
		advancedChange(false);
	});

	// Initial page load
	publishingChange();
	IPv4Change();
	IPv6Change();
	advancedChange(true);
});
//]]>
</script>

<?php include("foot.inc");
