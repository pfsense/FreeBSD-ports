<?php
/*
 * vpn_openvpn_client.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-openvpn-import
##|*NAME=OpenVPN: Clients
##|*DESCR=Allow access to the 'OpenVPN: Client import' page.
##|*MATCH=vpn_openvpn_import.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("openvpn.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");
require_once("openvpn-client-import.inc");

global $openvpn_topologies, $openvpn_tls_modes;

$info = sprintf(gettext(
	"%1s software can import a unified client configuration file as exported by an OpenVPN server. " .
	"The unified format includes all of the certificates and keys required for the connection.%2s" .
	"In many cases the newly imported tunnel will be started and will pass traffic on completion of " .
	"the import, but in some cases it will be necessary to make adjustments to the imported client " .
	"configuration by visiting the %3s page."
), $g['product_label_html'], "<br /><br />", '<a href="vpn_openvpn_client.php">OpenVPN client edit</a>');

init_config_arr(array('openvpn', 'openvpn-client'));
$a_client = &$config['openvpn']['openvpn-client'];
$pconfig = array();

$ulfile = "";
$savemsg = "";

if ($_POST) {
	$pconfig = $_POST;

	$filename = $_FILES['ovpnfile']['name'];
	if ($_FILES['ovpnfile']['tmp_name']) {
		$fileconts = file_get_contents($_FILES['ovpnfile']['tmp_name']);
	}

	import_openvpn_client($pconfig, $filename, $fileconts);
	if (!$input_errors) {
		pfSenseHeader("vpn_openvpn_client.php");
		exit;
	}
}

$pgtitle = array(gettext("VPN"), gettext("OpenVPN"), gettext("Client import"));
$pglinks = array("", "vpn_openvpn_server.php", "vpn_openvpn_import.php");

$shortcut_section = "openvpn";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Servers"), false, "vpn_openvpn_server.php");
$tab_array[] = array(gettext("Clients"), true, "vpn_openvpn_client.php");
$tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
add_package_tabs("OpenVPN", $tab_array);
display_top_tabs($tab_array);

// Automatically format the information block with close/open icon
?> <div class="infoblock blockopen"> <?php

if (strlen($savemsg) == 0) {
	print_info_box($info, 'info', false);
}

?> </div> <?php

$form = new Form("Import");
$form->setMultipartEncoding();

$section = new Form_Section('OpenVPN client configuration');

$section->addInput(new Form_Input(
	'ovpnfile',
	'*.ovpn config file',
	'file',
))->addClass('btn-default')->setAttribute('accept', '.ovpn');

$section->addInput(new Form_Checkbox(
	'disable',
	'Disabled',
	'Disable this client',
	$pconfig['disable']
))->setHelp('Set this option to disable this client after import.');

$section->addInput(new Form_Select(
	'mode',
	'*Server mode',
	$pconfig['mode'],
	$openvpn_client_modes
));

$section->addInput(new Form_Input(
	'description',
	'Name',
	'text',
	$pconfig['description']
))->setHelp('Enter a name or description for the imported tunnel/certs. If no name is provided, the uploaded file name will be used.');

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['interface'],
	openvpn_build_if_list()
))->setHelp("The interface used by the firewall to originate this OpenVPN client connection");

$section->addInput(new Form_Input(
	'username',
	'User name',
	'text',
	$pconfig['username']
))->setHelp('If the imported tunnel requires username/password authentication, enter the username here.');

$section->addPassword(new Form_Input(
	'password',
	'Password',
	'password',
	''
))->setHelp('If the imported tunnel requires username/password authentication, enter the password here.');

$form->add($section);
print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

});
//]]>
</script>

<?php include("foot.inc");
