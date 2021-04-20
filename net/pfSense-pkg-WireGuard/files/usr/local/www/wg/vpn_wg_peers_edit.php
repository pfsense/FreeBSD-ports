<?php
/*
 * vpn_wg_peers_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald
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
##|*IDENT=page-vpn-wireguard
##|*NAME=VPN: WireGuard: Edit
##|*DESCR=Allow access to the 'VPN: WireGuard' page.
##|*MATCH=vpn_wg_peers_edit.php*
##|-PRIV

// pfSense includes
require_once('functions.inc');
require_once('guiconfig.inc');

// WireGuard includes
require_once('wireguard/wg.inc');

global $wgg;

wg_globals();

$secrets_input_type = (isset($wgg['config']['hide_secrets']) && $wgg['config']['hide_secrets'] =='yes') ? 'password' : 'text';

if (isset($_REQUEST['tun'])) {

	$tun = $_REQUEST['tun'];

	$tun_id = wg_get_tunnel_id($_REQUEST['tun']);

}

if (isset($_REQUEST['peer']) && is_numericint($_REQUEST['peer'])) {

	$peer_id = $_REQUEST['peer'];

}

// All form save logic is in wireguard/wg.inc
if ($_POST) {

	if ($_POST['act'] == 'save') {

		$res = wg_do_peer_post($_POST);
		
		$input_errors = $res['input_errors'];

		$pconfig = $res['pconfig'];

		if (!$input_errors) {

			wg_resync();
			
			// Save was successful
			header("Location: /wg/vpn_wg_peers.php");

		}

	} elseif ($_POST['act'] == 'genpsk') {

		// Process ajax call requesting new pre-shared key
		print(wg_gen_psk());

		exit;
	
	}

} else {

	if (isset($peer_id) && is_array($wgg['peers'][$peer_id])) {

		// Looks like we are editing an existing tunnel
		$pconfig = &$wgg['peers'][$peer_id];

	} else {

		// We are creating a new peer
		$pconfig = array();

		// Default to enabled
		$pconfig['enabled'] = 'yes';

		// Automatically choose a tunnel based on the request 
		$pconfig['tun'] = $tun;

		// Default to a dynamic tunnel, so hide the endpoint form group
		$is_dynamic = true;

	}

	// Pull out $allowedips, $all_ipv4, and $all_ipv6 in one shot
	extract(wg_allowed_ips_filtered($pconfig['allowedips']));

}

$shortcut_section = "wireguard";

$pgtitle = array(gettext("VPN"), gettext("WireGuard"), gettext("Peers"), gettext("Edit"));
$pglinks = array("", "/wg/vpn_wg_tunnels.php", "/wg/vpn_wg_peers.php", "@self");

$tab_array = array();
$tab_array[] = array(gettext("Tunnels"), false, "/wg/vpn_wg_tunnels.php");
$tab_array[] = array(gettext("Peers"), true, "/wg/vpn_wg_peers.php");
$tab_array[] = array(gettext("Settings"), false, "/wg/vpn_wg_settings.php");
$tab_array[] = array(gettext("Status"), false, "/wg/status_wireguard.php");

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

display_top_tabs($tab_array);

$form = new Form(false);

$section = new Form_Section('Peer Configuration');

$form->addGlobal(new Form_Input(
	'index',
	'',
	'hidden',
	$peer_id
));

$section->addInput(new Form_Checkbox(
	'enabled',
	'Peer Enabled',
	gettext('Enable'),
	$pconfig['enabled'] == 'yes'
))->setHelp('<span class="text-danger">Note: </span>Uncheck this option to disable this peer without removing it from the list.');

$section->addInput($input = new Form_Select(
	'tun',
	'Tunnel',
	$pconfig['tun'],
	build_tun_list()
))->setHelp("WireGuard tunnel for this peer. (<a href='vpn_wg_tunnels_edit.php'>Create a New Tunnel</a>)");

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr'],
	['placeholder' => 'Description']
))->setHelp("Peer description for administrative reference (not parsed)");

$section->addInput(new Form_Checkbox(
	'dynamic',
	'Dynamic Endpoint',
	gettext('Dynamic'),
	empty($pconfig['endpoint']) || $is_dynamic
))->setHelp('<span class="text-danger">Note: </span>Uncheck this option to assign an endpoint address and port for this peer.');

$group = new Form_Group('Endpoint');

$group->add(new Form_Input(
	'endpoint',
	'Endpoint',
	'text',
	$pconfig['endpoint']
))->setWidth(5)
	->setHelp('Hostname, IPv4, or IPv6 address of this peer.<br />
			Leave endpoint and port blank if unknown (dynamic endpoints).');

$group->add(new Form_Input(
	'port',
	'Endpoint Port',
	'text',
	$pconfig['port']
))->setWidth(3)
	->setHelp("Port used by this peer.<br />
			Leave blank for default ({$wgg['default_port']}).");

$group->addClass("endpoint");

$section->add($group);

$section->addInput(new Form_Input(
	'persistentkeepalive',
	'Keep Alive',
	'text',
	$pconfig['persistentkeepalive']
))->setHelp('Interval (in seconds) for Keep Alive packets sent to this peer.<br />
		Default is empty (disabled).');

$section->addInput(new Form_Input(
	'publickey',
	'*Public Key',
	'text',
	$pconfig['publickey'],
	['placeholder' => 'Public Key']
))->setHelp('WireGuard public key for this peer.');

$group = new Form_Group('Pre-shared Key');

$group->add(new Form_Input(
	'presharedkey',
	'Pre-shared Key',
	$secrets_input_type,
	$pconfig['presharedkey']
))->setHelp('Optional pre-shared key for this tunnel.');

$group->add(new Form_Button(
	'genpsk',
	'Generate',
	null,
	'fa-key'
))->addClass('btn-primary btn-sm')
	->setHelp('New Pre-shared Key');

$section->add($group);

$group = new Form_Group("Allowed IPs");

$group->add(new Form_Checkbox(
	'all_ipv4',
	'Protocol',
	'IPv4',
	$all_ipv4
))->setWidth(3)->setHelp("Allow all IPv4 addresses (0.0.0.0/0)");

$group->add(new Form_Checkbox(
	'all_ipv6',
	'Protocol',
	'IPv6',
	$all_ipv6
))->setWidth(3)->setHelp("Allow all IPv6 addresses (::/0)");

$section->add($group);

$group = new Form_Group(null);

$group->add(new Form_StaticText(
	null,
	'IPv4 or IPv6 subnets or hosts reachable via this peer:'
))->setWidth(5);

$section->add($group);

foreach ($allowedips as $index => $ip) {

	list($address, $address_subnet) = explode("/", $ip);

	$group = new Form_Group(null);

	$group->addClass('repeatable');

	$group->add(new Form_IpAddress(
		"address{$index}",
		'Allowed IPs',
		$address,
		'BOTH'
	))->addMask("address_subnet{$index}", $address_subnet, 128, 1)
		->setWidth(5);

	$group->add(new Form_Button(
		"deleterow{$index}",
		'Delete',
		null,
		'fa-trash'
	))->addClass('btn-warning btn-sm');

	$section->add($group);

}

$section->addInput(new Form_Button(
	'addrow',
	'Add Allowed IP',
	null,
	'fa-plus'
))->addClass('btn-success btn-sm addbtn');

$form->add($section);

$form->addGlobal(new Form_Input(
	'act',
	'',
	'hidden',
	'save'
));

print($form);

?>

<nav class="action-buttons">
	<button type="submit" id="saveform" name="saveform" class="btn btn-primary btn-sm" value="save" title="<?=gettext('Save Peer')?>">
		<i class="fa fa-save icon-embed-btn"></i>
		<?=gettext("Save Peer")?>
	</button>
</nav>

<?php $genkeywarning = gettext("Overwrite pre-shared key? Click 'ok' to overwrite key."); ?>

<!-- ============== JavaScript =================================================================================================-->
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	$('#copypsk').click(function () {
		$('#presharedkey').focus();
		$('#presharedkey').select();
		document.execCommand("copy");
	});

	// These are action buttons, not submit buttons
	$('#genpsk').prop('type','button');

	// Request a new pre-shared key
	$('#genpsk').click(function(event) {
		if ($('#presharedkey').val().length == 0 || confirm("<?=$genkeywarning?>")) {
			ajaxRequest = $.ajax({
				url: "/wg/vpn_wg_peers_edit.php",
				type: "post",
				data: {
					act: "genpsk"
				},
				success: function(response, textStatus, jqXHR) {
					$('#presharedkey').val(response);
				}
			});
		}

	});

	// Save the form
	$('#saveform').click(function () {
		$(form).submit();
	});

	function updateSection(hide) {

		hideClass('endpoint', hide);

	}

	$('#dynamic').click(function () {

		updateSection(this.checked);

	});

    	updateSection($('#dynamic').prop('checked'));

});
//]]>
</script>

<?php

include("foot.inc");

?>