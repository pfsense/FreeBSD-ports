<?php
/*
 * vpn_ipsec_export_win.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2020 Rubicon Communications, LLC (Netgate)
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
	$win_supported_map, $server_list, $mobile_p1, $mobile_p2;
$input_errors = array();

if (empty($mobile_p1)) {
	$input_errors[] = gettext('There is no Mobile Phase 1 in the IPsec configuration.');
} elseif ($mobile_p1['iketype'] != 'ikev2') {
	$input_errors[] = gettext('Mobile Phase 1 is not IKEv2. This utility only supports IKEv2.');
} elseif (substr($mobile_p1['authentication_method'], 0, 3) != 'eap') {
	$input_errors[] = gettext('Mobile Phase 1 Authentication Method is not EAP. This utility only supports EAP Methods.');
} else {
	/* Locate Mobile Phase 2 entries */
	$mobile_p2 = array();
	foreach ($a_phase2 as $p2) {
		if ($p2['ikeid'] == $mobile_p1['ikeid']) {
			$mobile_p2[] = $p2;
		}
	}
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

if ($_POST) {
	if (($_POST['server_address'] == 'Custom Hostname') &&
	    !is_hostname($_POST['server_hostname']) &&
	    !is_ipaddr($_POST['server_hostname'])) {
		$input_errors[] = gettext('Custom Hostname value must be a Hostname or IP address.');
	}
}


$set_errors = iex_parameter_check('windows');
if (!empty($set_errors)) {
	$input_errors = array_merge($input_errors, $set_errors);
}

$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("IPsec Export: Windows PowerShell"));
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

		/* Set the user cert if using EAP-TLS mode */
		$user_certref = ($mobile_p1['authentication_method'] == 'eap-tls') ? $_POST['user_certref'] : null;

		/* Export the config */
		if ($_POST['Submit'] == gettext('Download')) {
			iew_export_archive($vpn_name, $server_address, $user_certref, true);
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
$tab_array[] = array(gettext("Apple Profile"), false, "vpn_ipsec_profile.php");
$tab_array[] = array(gettext("Windows PowerShell"), true, "vpn_ipsec_export_win.php");
display_top_tabs($tab_array);
?>
<?php
/* User Options */
$form = new Form(false);
$section = new Form_Section('IKEv2 Export Settings');

/* Name - Pre-fill with "pfSense-<P1 Descr>", if P1 descr is blank use hostname */
$section->addInput(new Form_Input(
	'name',
	'VPN Name',
	'text',
	"VPN ({$config['system']['hostname']}) - " . (!empty($mobile_p1['descr']) ? $mobile_p1['descr'] : 'Mobile IPsec')
))->setHelp('The name of the VPN as seen by the client in their network list. ' .
		'This name is also used when creating the download archive.');

$section->addInput(new Form_Select(
	'server_address',
	'Server Address',
	null,
	array_combine($server_list, $server_list)
))->setHelp('Select the server address to be used by the client. ' .
		'This list is generated from the SAN entries on the server certificate. ' .
		'Windows requires the server address be present in the server certificate SAN list.');

$section->addInput(new Form_Input(
	'server_hostname',
	'Custom Hostname',
	'text',
	""
))->setHelp('Used with the \'Custom Hostname\' Server Address selection. ' .
	'Address to which clients will connect instead of the choices above.');

/* For EAP-TLS, pick a specific cert */
if ($mobile_p1['authentication_method'] == 'eap-tls') {
	/* Collect user cert list from the Peer Certificate Authority */
	$tls_client_list = array();
	foreach ($a_cert as $crt) {
		if (($mobile_p1['caref'] == $crt['caref']) && !empty($crt['prv'])) {
			$tls_client_list[$crt['refid']] = $crt['descr'];
		}
	}
	asort($tls_client_list, SORT_NATURAL | SORT_FLAG_CASE);
	$section->addInput(new Form_Select(
		'user_certref',
		'TLS User Certificate',
		$_POST['user_certref'],
		$tls_client_list
	))->setHelp('Select a TLS client certificate to include in the download archive. ');
}

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
<?= iew_export_archive($vpn_name, $server_address, $user_certref, false); ?>
</textarea>
<br/>
<?php endif; ?>

<div class="infoblock blockopen">
<?php
print_info_box(
'<p>' . gettext('This page generates an archive with a Windows PowerShell script and certificate files.') . ' ' .
gettext('The commands in the PowerShell script will import certificates and setup the VPN on the client workstation.') . '<p>' .
'<p>' . gettext('Running PowerShell scripts on Windows is disabled by default, but local policies may override that behavior.') . ' ' .
sprintf(gettext('See the %1$sPowerShell Execution Policies Documentation%2$s for details.'),
	'<a href="https://go.microsoft.com/fwlink/?LinkID=135170">', '</a>') . ' ' .
gettext('If scripting is disabled, the commands may be copied and pasted into a PowerShell prompt.') . '<p>' .
'<p>' . gettext('Some commands may require Administrator access, such as importing the CA certificate.') . ' ' .
gettext('Run these commands at an Administrator-level PowerShell prompt or use an alternate method.') . '<p>' .
'<p>' . gettext('If the <strong>Network List</strong> option is active on the <strong>Mobile Clients</strong> tab,') . ' ' .
gettext('the script will include parameters to setup Split Tunneling on the client as well as commands to') . ' ' .
gettext('configure routes on the VPN for networks configured in the mobile Phase 2 entries.') . '<p>' .
'<p>' . gettext('This utility checks configured Mobile Phase 1 and Phase 2 entries and attempts to locate a set of') . ' ' .
gettext('parameters which are compatible with Windows clients. It uses the first match it finds, so order choices') . ' ' .
gettext('in the Phase 1 and Phase 2 list appropriately or manually edit the resulting script as needed.') . ' ' .
sprintf(gettext('For a full list of compatible parameters, see the %1$sMicrosoft Documentation for Set-VpnConnectionIPsecConfiguration%2$s'),
	'<a href="https://docs.microsoft.com/en-us/powershell/module/vpnclient/set-vpnconnectionipsecconfiguration?view=win10-ps">', '') . '<p>'
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
