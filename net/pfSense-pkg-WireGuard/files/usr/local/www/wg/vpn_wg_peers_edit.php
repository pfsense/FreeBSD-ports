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
require_once('wireguard/wg_guiconfig.inc');

global $wgg;

wg_globals();

if (isset($_REQUEST['tun'])) {

	$tun_name = $_REQUEST['tun'];

}

if (isset($_REQUEST['peer']) && is_numericint($_REQUEST['peer'])) {

	$peer_idx = $_REQUEST['peer'];

}

// All form save logic is in wireguard/wg.inc
if ($_POST) {

	if ($_POST['act'] == 'save') {

		$res = wg_do_peer_post($_POST);
		
		$input_errors = $res['input_errors'];

		$pconfig = $res['pconfig'];

		if (!$input_errors) {
			
			// Save was successful
			header("Location: /wg/vpn_wg_peers.php");

		}

	} elseif ($_POST['act'] == 'genpsk') {

		// Process ajax call requesting new pre-shared key
		print(wg_gen_psk());

		exit;
	
	}

} 

$pconfig = array();

if (isset($peer_idx) && is_array($wgg['peers'][$peer_idx])) {

	// Looks like we are editing an existing peer
	$pconfig = &$wgg['peers'][$peer_idx];

} else {

	// We are creating a new peer
	$pconfig = array();

	// Default to enabled
	$pconfig['enabled'] = 'yes';

	// Automatically choose a tunnel based on the request 
	$pconfig['tun'] = $tun_name;

	// Default to a dynamic tunnel, so hide the endpoint form group
	$is_dynamic = true;

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

if (count($wgg['tunnels']) > 0 && !is_module_loaded($wgg['kmod'])) {

	print_info_box(gettext('The WireGuard kernel module is not loaded!'), 'danger', null);

}

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
	$peer_idx
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
	wg_get_tun_list()
))->setHelp("WireGuard tunnel for this peer. (<a href='vpn_wg_tunnels_edit.php'>Create a New Tunnel</a>)");

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr'],
	['placeholder' => 'Description']
))->setHelp("Peer description for administrative reference (not parsed).");

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
	wg_secret_input_type(),
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

$form->add($section);

$section = new Form_Section('Address Configuration');

// Hack to ensure empty lists default to /128 mask
if (!is_array($pconfig['allowedips']['row'])) {

	wg_init_config_arr($pconfig, array('allowedips', 'row', 0));
	
	$pconfig['allowedips']['row'][0]['mask'] = '128';
	
}

$last = count($pconfig['allowedips']['row']) - 1;

foreach ($pconfig['allowedips']['row'] as $counter => $item) {

	$group = new Form_Group($counter == 0 ? 'Allowed IPs' : null);

	$group->addClass('repeatable');

	$group->add(new Form_IpAddress(
		"address{$counter}",
		'Allowed Subnet or Host',
		$item['address'],
		'BOTH'
	))->setHelp($counter == $last ? 'IPv4 or IPv6 subnet or host reachable via this peer.' : '')
		->addMask("address_subnet{$counter}", $item['mask'], 128, 0)
		->setWidth(4);

	$group->add(new Form_Input(
		"address_descr{$counter}",
		'Description',
		'text',
		$item['descr']
	))->setHelp($counter == $last ? 'Description for administrative reference (not parsed).' : '')
		->setWidth(4);

	$group->add(new Form_Button(
		"deleterow{$counter}",
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

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Supress "Delete" button if there are fewer than two rows
	checkLastRow();

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

	$('#dynamic').click(function () {

		updateDynamicSection(this.checked);

	});

	function updateDynamicSection(hide) {

		hideClass('endpoint', hide);

	}

	updateDynamicSection($('#dynamic').prop('checked'));

});
//]]>
</script>

<?php

include('foot.inc');

// Must be included last
include('wireguard/wg_foot.inc');

?>