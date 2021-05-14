<?php
/*
 * vpn_wg_tunnels_edit.php
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
##|*MATCH=vpn_wg_tunnels_edit.php*
##|-PRIV

// pfSense includes
require_once('functions.inc');
require_once('guiconfig.inc');

// WireGuard includes
require_once('wireguard/wg.inc');
require_once('wireguard/wg_guiconfig.inc');

global $wgg;

wg_globals();

$pconfig = array();

// Always assume we are creating a new tunnel
$is_new = true;

if (isset($_REQUEST['tun'])) {

	$tun = $_REQUEST['tun'];

	$tun_idx = wg_get_tunnel_array_index($_REQUEST['tun']);

}

if (isset($_REQUEST['peer'])) {

	$peer_id = $_REQUEST['peer'];

}

// All form save logic is in wireguard/wg.inc
if ($_POST) {

	if ($_POST['act'] == 'save') {

		$res = wg_do_tunnel_post($_POST);
		
		$input_errors = $res['input_errors'];

		$pconfig = $res['pconfig'];

		if (!$input_errors) {

			// Save was successful
			header("Location: /wg/vpn_wg_tunnels.php");

		}

	} elseif ($_POST['act'] == 'genkeys') {

		// Process ajax call requesting new key pair
		print(wg_gen_keypair(true));

		exit;

	} elseif ($_POST['act'] == 'genpubkey') {

		// Process ajax call calculating the public key from a private key
		print(wg_gen_publickey($_POST['privatekey']));

		exit;

	} elseif ($_POST['act'] == 'toggle') {

		wg_toggle_peer($peer_id);

	} elseif ($_POST['act'] == 'delete') {

		wg_delete_peer($peer_id);

	}

}

// Looks like we are editing an existing tunnel
if (isset($tun_idx) && is_array($wgg['tunnels'][$tun_idx])) {

	$pconfig = &$wgg['tunnels'][$tun_idx];

	// Supress warning and allow peers to be added via the 'Add Peer' link
	$is_new = false;

// Looks like we are creating a new tunnel
} else {

	// Default to enabled
	$pconfig['enabled'] = 'yes';

	$pconfig['name'] = next_wg_if();

}

// Save the MTU settings prior to re(saving)
$pconfig['mtu'] = get_interface_mtu($pconfig['name']);

$shortcut_section = "wireguard";

$pgtitle = array(gettext("VPN"), gettext("WireGuard"), gettext("Tunnels"), gettext("Edit"));
$pglinks = array("", "/wg/vpn_wg_tunnels.php", "/wg/vpn_wg_tunnels.php", "@self");

$tab_array = array();
$tab_array[] = array(gettext("Tunnels"), true, "/wg/vpn_wg_tunnels.php");
$tab_array[] = array(gettext("Peers"), false, "/wg/vpn_wg_peers.php");
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

$section = new Form_Section("Tunnel Configuration ({$pconfig['name']})");

$form->addGlobal(new Form_Input(
	'index',
	'',
	'hidden',
	$tun_idx
));

$tun_enable = new Form_Checkbox(
	'enabled',
	'Tunnel Enabled',
	gettext('Enable'),
	$pconfig['enabled'] == 'yes'
);

$tun_enable->setHelp('<span class="text-danger">Note: </span>Tunnel must be <b>enabled</b> in order to be assigned to a pfSense interface.');	

// Disable the tunnel enabled button if interface is assigned in pfSense
if (is_wg_tunnel_assigned($pconfig)) {

	$tun_enable->setDisabled();

	$tun_enable->setHelp('<span class="text-danger">Note: </span>Tunnel cannot be <b>disabled</b> when assigned to a pfSense interface.');

	// We still want to POST this field, make it a hidden field now
	$form->addGlobal(new Form_Input(
		'enabled',
		'',
		'hidden',
		'yes'
	));

}

$section->addInput($tun_enable);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr'],
	['placeholder' => 'Description']
))->setHelp('Description for administrative reference (not parsed).');

$section->addInput(new Form_Input(
	'listenport',
	'*Listen Port',
	'text',
	$pconfig['listenport'],
	['placeholder' => next_wg_port()]
))->setHelp('Port used by this tunnel to communicate with peers.');

$group = new Form_Group('*Interface Keys');

$group->add(new Form_Input(
	'privatekey',
	'Private Key',
	wg_secret_input_type(),
	$pconfig['privatekey']
))->setHelp('Private key for this tunnel. (Required)');

$group->add(new Form_Input(
	'publickey',
	'Public Key',
	'text',
	$pconfig['publickey']
))->setHelp('Public key for this tunnel. (<a id="copypubkey" href="#">Copy</a>)')->setReadonly();

$group->add(new Form_Button(
	'genkeys',
	'Generate',
	null,
	'fa-key'
))->addClass('btn-primary btn-sm')
	->setHelp('New Keys')
	->setWidth(1);

$section->add($group);

$form->add($section);

$section = new Form_Section("Interface Configuration ({$pconfig['name']})");

if (!is_wg_tunnel_assigned($pconfig)) {

	$section->addInput(new Form_StaticText(
		'Assignment',
		"<i class='fa fa-sitemap' style='vertical-align: middle;'></i><a style='padding-left: 3px' href='../../interfaces_assign.php'>Interface Assignments</a>"
	));

	$section->addInput(new Form_StaticText(
		'Firewall Rules',
		"<i class='fa fa-shield-alt' style='vertical-align: middle;'></i><a style='padding-left: 3px' href='../../firewall_rules.php?if={$wgg['if_group']}'>WireGuard Interface Group</a>"
	));

	// Hack to ensure empty lists default to /128 mask
	if (!is_array($pconfig['addresses']['row'])) {

		wg_init_config_arr($pconfig, array('addresses', 'row', 0));

		$pconfig['addresses']['row'][0]['mask'] = '128';

	}

	$last = count($pconfig['addresses']['row']) - 1;

	foreach ($pconfig['addresses']['row'] as $counter => $item) {
	
		$group = new Form_Group($counter == 0 ? 'Interface Addresses' : '');
	
		$group->addClass('repeatable');

		$group->add(new Form_IpAddress(
			"address{$counter}",
			'Interface Address',
			$item['address'],
			'BOTH'
		))->setHelp($counter == $last ? 'IPv4 or IPv6 address assigned to the tunnel interface.' : '')
			->addMask("address_subnet{$counter}", $item['mask'])
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
		'Add Address',
		null,
		'fa-plus'
	))->addClass('btn-success btn-sm addbtn');

} else {

	// We want all configured interfaces, including disabled ones
	$wg_pfsense_if = wg_get_pfsense_interface_info($pconfig['name']);

	$section->addInput(new Form_StaticText(
		'Assignment',
		"<i class='fa fa-sitemap' style='vertical-align: middle;'></i><a style='padding-left: 3px' href='../../interfaces_assign.php'>{$wg_pfsense_if['descr']} ({$wg_pfsense_if['name']})</a>"
	));

	$section->addInput(new Form_StaticText(
		'Interface',
		"<i class='fa fa-ethernet' style='vertical-align: middle;'></i><a style='padding-left: 3px' href='../../interfaces.php?if={$wg_pfsense_if['name']}'>Interface Configuration</a>"
	));

	$section->addInput(new Form_StaticText(
		'Firewall Rules',
		"<i class='fa fa-shield-alt' style='vertical-align: middle;'></i><a style='padding-left: 3px' href='../../firewall_rules.php?if={$wg_pfsense_if['name']}'>Firewall Configuration</a>"
	));

}

$form->add($section);

$form->addGlobal(new Form_Input(
	'mtu',
	'',
	'hidden',
	$pconfig['mtu']
));

$form->addGlobal(new Form_Input(
	'is_new',
	'',
	'hidden',
	$is_new
));

$form->addGlobal(new Form_Input(
	'act',
	'',
	'hidden',
	'save'
));

print($form);

if ($is_new):

	print_info_box("New tunnels must be saved before adding or assigning peers.", 'warning', null);

else:

?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Peer Configuration")?></h2>
	</div>
	<div id="mainarea" class="table-responsive panel-body">
		<table id="peertable" class="table table-hover table-striped table-condensed" style="overflow-x: 'visible'">
			<thead>
				<tr>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Public key")?></th>
					<th><?=gettext("Allowed IPs")?></th>
					<th><?=wg_format_endpoint(true)?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
<?php
		$peers = wg_get_tunnel_peers($pconfig['name']);

		if (!empty($peers)):

			foreach ($peers as $peer):
?>
				<tr ondblclick="document.location='<?="vpn_wg_peers_edit.php?peer={$peer['index']}"?>';" class="<?=wg_entrystatus_class($peer)?>">
					<td><?=htmlspecialchars($peer['descr'])?></td>
					<td><?=htmlspecialchars(substr($peer['publickey'], 0, 16).'...')?></td>
					<td><?=wg_generate_peer_allowedips_popup_link($peer['index'])?></td>
					<td><?=htmlspecialchars(wg_format_endpoint(false, $peer))?></td>
					<td style="cursor: pointer;">
						<a class="fa fa-pencil" title="<?=gettext("Edit peer")?>" href="<?="vpn_wg_peers_edit.php?peer={$peer['index']}"?>"></a>
						<?=wg_generate_toggle_icon_link($peer, 'Click to toggle enabled/disabled status', "?act=toggle&peer={$peer['index']}&tun={$tun}")?>
						<a class="fa fa-trash text-danger" title="<?=gettext('Delete peer')?>" href="<?="?act=delete&peer={$peer['index']}&tun={$tun}"?>" usepost></a>
					</td>
				</tr>

<?php
			endforeach;
		endif;
?>
			</tbody>
		</table>
	</div>
</div>

<?php
endif;
?>

<nav class="action-buttons">
<?php
// We cheat here and show disabled buttons for a better user experience
if ($is_new):
?>
	<button class="btn btn-success btn-sm" title="<?=gettext('Add Peer')?>" disabled>
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add Peer")?>
	</button>
<?php
// Now we show the actual links once the tunnel is actually saved
else:
?>
	<a href="<?="vpn_wg_peers_edit.php?tun={$pconfig['name']}"?>" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add Peer")?>
	</a>
<?php
endif;
?>
	<button type="submit" id="saveform" name="saveform" class="btn btn-primary btn-sm" value="save" title="<?=gettext('Save tunnel')?>">
		<i class="fa fa-save icon-embed-btn"></i>
		<?=gettext("Save Tunnel")?>
	</button>
</nav>

<?php $genkeywarning = gettext("Overwrite key pair? Click 'ok' to overwrite keys."); ?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Supress "Delete" button if there are fewer than two rows
	checkLastRow();

	$('#copypubkey').click(function () {
		$('#publickey').focus();
		$('#publickey').select();
		document.execCommand("copy");
	});

	// These are action buttons, not submit buttons
	$("#genkeys").prop('type', 'button');

	// Request a new public/private key pair
	$('#genkeys').click(function(event) {
		if ($('#privatekey').val().length == 0 || confirm("<?=$genkeywarning?>")) {
			ajaxRequest = $.ajax({
				url: '/wg/vpn_wg_tunnels_edit.php',
				type: 'post',
				data: {act: 'genkeys'},
				success: function(response, textStatus, jqXHR) {
					resp = JSON.parse(response);
					$('#publickey').val(resp.pubkey);
					$('#privatekey').val(resp.privkey);
				}
			});
		}
	});

	// Request a new public key when private key is changed
	$('#privatekey').change(function(event) {
		ajaxRequest = $.ajax(
			{
				url: '/wg/vpn_wg_tunnels_edit.php',
				type: 'post',
				data: {
					act: 'genpubkey',
					privatekey: $('#privatekey').val()
				},
			success: function(response, textStatus, jqXHR) {
				$('#publickey').val(response);
			}
		});
	});


	// Save the form
	$('#saveform').click(function(event) {
		$(form).submit();
	});

});
//]]>
</script>

<?php 

include('foot.inc');

// Must be included last
include('wireguard/wg_foot.inc');

?>