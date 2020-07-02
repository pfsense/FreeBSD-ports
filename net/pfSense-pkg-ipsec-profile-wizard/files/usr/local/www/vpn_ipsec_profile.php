<?php
/*
 * vpn_ipsec_profile.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2020 Rubicon Communications, LLC (Netgate)
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

require_once("ipsec-profile.inc");
require_once("guiconfig.inc");
require_once("classes/Form.class.php");

global $config, $mobileconfig, $a_phase1, $a_phase2, $a_cert,
	$apple_supported_map, $server_list,
	$mobile_p1, $mobile_p2;
$input_errors = array();

$debug = true;

$profile_suffix = '.mobileconfig';

/*
 * See also: https://developer.apple.com/business/documentation/Configuration-Profile-Reference.pdf
 *           https://support.apple.com/apple-configurator
 *
 */

if (empty($mobile_p1)) {
	$input_errors[] = gettext('There is no Mobile Phase 1 in the IPsec configuration.');
} else {
	/* Locate Mobile Phase 2 entries */
	if (empty($mobile_p2)) {
		$input_errors[] = gettext('There are no Mobile Phase 2 entries in the IPsec configuration.');
	} else {
		foreach ($mobile_p2 as $mp2) {
			if (!in_array($mp2['mode'], array('tunnel', 'tunnel6'))) {
				$input_errors[] = gettext('Mobile Phase 2 entry modes must all be "Tunnel".');
				break;
			}
		}
	}
}

$valid_users = iep_get_valid_users();

if ($_POST) {
	if (($_POST['server_address'] == 'Custom Hostname') &&
	    !is_hostname($_POST['server_hostname']) &&
	    !is_ipaddr($_POST['server_hostname'])) {
		$input_errors[] = gettext('Custom Hostname value must be a Hostname or IP address.');
	}

	if (!array_key_exists($_POST['vpnclient'], $valid_users)) {
		$input_errors[] = gettext('Requested client is not valid for this Mobile IPsec P1.');
	}
}

$set_errors = iex_parameter_check('apple');
if (!empty($set_errors)) {
	$input_errors = array_merge($input_errors, $set_errors);
}

if (!empty($set_errors)) {
	$input_errors = array_merge($input_errors, $set_errors);
}

$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("IPsec Export: Apple Profile"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "ipsec";

if ($_POST && empty($input_errors)) {
	try {
		/* Use the user-supplied VPN name or a simple default if it was empty */
		$vpn_name = (!empty($_POST['name'])) ? $_POST['name'] : "Mobile IPsec ({$config['system']['hostname']})";
		/* Ensure it's only a filename, not a path */
		$vpn_name = basename($vpn_name);

		/* If the user submitted a valid host, use it, otherwise force automatic mode */
		if (($_POST['server_address'] == 'Custom Hostname') &&
		    (is_hostname($_POST['server_hostname']) || is_ipaddr($_POST['server_hostname']))) {
			$server_address = $_POST['server_hostname'];
		} elseif (empty($_POST['server_address']) || ($_POST['server_address'] == 'Auto') ||
		    (!is_hostname($_POST['server_address']) && !is_ipaddr($_POST['server_address']))) {
			$server_address = iex_server_list(true);
		} else {
			$server_address = $_POST['server_address'];
		}

		/* Validated client reference is in $_POST['vpnclient'] but it could be a username or certificate reference. */

		/* Set the user cert if using EAP-TLS mode */
		$user_certref = ($mobile_p1['authentication_method'] == 'eap-tls') ? $_POST['user_certref'] : null;
		// get_cert_client_id($caref, $user)
		/* Set the user cert if using EAP-TLS mode */
		$user = !empty($_POST['vpnclient']) ? $_POST['vpnclient'] : null;

		/* Export the config */
		if ($_POST['Submit'] == gettext('Download')) {
			$filename = str_replace(' ', '_', $vpn_name);
			if (!empty($user)) {
				$filename .= '.' . $user;
			}
			if (!empty($user_certref)) {
				$filename .= '.' . $user_certref;
			}
			$filename .= $profile_suffix;
			send_user_download('data', iep_generate_profile($vpn_name, $server_address, $user, $user_certref), $filename);
		}

	} catch (Exception $e) {
		$input_errors[] = sprintf(gettext('Could not export IPsec VPN: %s'), $e->getMessage());
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Apple Profile"), true, "vpn_ipsec_profile.php");
$tab_array[] = array(gettext("Windows PowerShell"), false, "vpn_ipsec_export_win.php");
display_top_tabs($tab_array);
?>

<?php
/* User Options */
$form = new Form(false);
$section = new Form_Section('Apple Profile Export Settings');

$section->addInput(new Form_Input(
	'name',
	'VPN Name',
	'text',
	"VPN ({$config['system']['hostname']}) - " . (!empty($mobile_p1['descr']) ? $mobile_p1['descr'] : 'Mobile IPsec')
))->setHelp('The name of the VPN as seen by the client in their network list. ' .
		'This name is also used when creating the profile filename.');

/* Server Address - Select from server cert SAN entries or 'auto' */
$section->addInput(new Form_Select(
	'server_address',
	'Server Address',
	null,
	array_combine($server_list, $server_list)
))->setHelp('Select the server address to be used by the client. ' .
		'This list is generated from the SAN entries on the server certificate. ' .
		'The server address must be present in the server certificate SAN list.');

$section->addInput(new Form_Input(
	'server_hostname',
	'Custom Hostname',
	'text',
	""
))->setHelp('Used with the \'Custom Hostname\' Server Address selection. ' .
	'Address to which clients will connect instead of the choices above.');

$section->addInput(new Form_Select(
	'vpnclient',
	'VPN Client',
	(empty($_POST['vpnclient']) ? $_SESSION['Username'] : $_POST['vpnclient']),
	$valid_users
))->setHelp('Select the client to export. Depending on the IPsec Mobile P1 settings, this may be a user entry or a TLS certificate.');

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Download',
	null,
	'fa-download'
))->addClass('btn-primary');

$form->addGlobal(new Form_Button(
	'Submit',
	'View',
	null,
	'fa-search'
))->addClass('btn-primary');

print($form);
?>

<?php if (empty($input_errors) &&
          ($_POST['Submit'] == gettext('View'))): ?>
<textarea cols="130" rows="40">
<?= iep_generate_profile($vpn_name, $server_address, $user, $vpnclient); ?>
</textarea>
<br/>
<?php endif; ?>

<div class="infoblock blockopen">
<?php
print_info_box(
'<p>' . gettext('This page generates an IPsec VPN Profile compatible with Apple products.') . ' ' .
gettext('Import the resulting profile into the client device or workstation.') .
'<p>' . sprintf(gettext('Visit the %1$sApple Configurator Site%2$s for details about creating and using profiles.'),
	'<a href="https://support.apple.com/apple-configurator">', '</a>') . ' ' .
'<p>' . sprintf(gettext('See the %1$sApple Configuration Profile Reference Documentation%2$s for details about the contents of profiles.'),
	'<a href="https://developer.apple.com/business/documentation/Configuration-Profile-Reference.pdf">', '</a>')
, 'info', false);
?>

</div>

<?php if ($mobile_p1['authentication_method'] == 'eap-tls'): ?>
<?= print_info_box(gettext("If a TLS client is missing from the list it is likely due to a CA mismatch " .
				"between the IPsec Peer Certificate Authority and the client certificate, " .
				"or the client certificate does not exist on this firewall.")); ?>
<?php endif; ?>
<?php
include("foot.inc");
