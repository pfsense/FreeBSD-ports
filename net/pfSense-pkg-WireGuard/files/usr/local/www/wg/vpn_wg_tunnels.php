<?php
/*
 * vpn_wg_tunnels.php
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
##|*NAME=VPN: WireGuard
##|*DESCR=Allow access to the 'VPN: WireGuard' page.
##|*MATCH=vpn_wg_tunnels.php*
##|-PRIV

// pfSense includes
require_once('functions.inc');
require_once('guiconfig.inc');

// WireGuard includes
require_once('wireguard/wg.inc');
require_once('wireguard/wg_guiconfig.inc');

global $wgg;

wg_globals();

if ($_POST) {

	if (isset($_POST['tun'])) {

		$tun_name = $_POST['tun'];

		if ($_POST['act'] == 'toggle') {

			$input_errors = wg_toggle_tunnel($tun_name);

		} elseif ($_POST['act'] == 'delete') { 

			$input_errors = wg_delete_tunnel($tun_name);

		}

	}

}

$shortcut_section = "wireguard";

$pgtitle = array(gettext("VPN"), gettext("WireGuard"), gettext("Tunnels"));
$pglinks = array("", "@self", "@self");

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

?>

<form name="mainform" method="post">
<?php
	if (is_array($wgg['tunnels']) && count($wgg['tunnels']) == 0):

		print_info_box(gettext('No WireGuard tunnels have been configured. Click the "Add Tunnel" button below to create one.'), 'warning', false);
		
	else:
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('WireGuard Tunnels')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th class="peer-entries"></th>
						<th><?=gettext("Name")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Public Key")?></th>
						<th><?=gettext("Address / Assignment")?></th>
						<th><?=gettext("Listen Port")?></th>
						<th><?=gettext("# Peers")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php
		foreach ($wgg['tunnels'] as $tunnel):

			$peers = wg_get_tunnel_peers($tunnel['name']);
?>
					<tr ondblclick="document.location='vpn_wg_tunnels_edit.php?tun=<?=$tunnel['name']?>';" class="<?=wg_entrystatus_class($tunnel)?>">
						<td class="peer-entries"><?=gettext('Interface')?></td>
						<td><?=htmlspecialchars($tunnel['name'])?></td>
						<td><?=htmlspecialchars($tunnel['descr'])?></td>
						<td><?=htmlspecialchars(wg_truncate_pretty($tunnel['publickey'], 16))?></td>
						<td><?=wg_generate_tunnel_address_popup_link($tunnel['name'])?></td>
						<td><?=htmlspecialchars($tunnel['listenport'])?></td>
						<td><?=count($peers)?></td>

						<td style="cursor: pointer;">
							<a class="fa fa-user-plus" title="<?=gettext("Add Peer")?>" href="<?="vpn_wg_peers_edit.php?tun={$tunnel['name']}"?>"></a>
							<a class="fa fa-pencil" title="<?=gettext("Edit tunnel")?>" href="<?="vpn_wg_tunnels_edit.php?tun={$tunnel['name']}"?>"></a>
							<?=wg_generate_toggle_icon_link($tunnel, 'Click to toggle enabled/disabled status', "?act=toggle&tun={$tunnel['name']}")?>
							<a class="fa fa-trash text-danger" title="<?=gettext('Delete tunnel')?>" href="<?="?act=delete&tun={$tunnel['name']}"?>" usepost></a>
						</td>
					</tr>

					<tr class="peer-entries peerbg_color">
						<td><?=gettext("Peers")?></td>
<?php

			if (count($peers) > 0):
?>
						<td colspan="6">
							<table class="table table-hover peerbg_color">
								<thead>
									<tr class="peerbg_color">
										<th><?=gettext("Description")?></th>
										<th><?=gettext("Public key")?></th>
										<th><?=gettext("Allowed IPs")?></th>
										<th><?=wg_format_endpoint(true)?></th>
									</tr>
								</thead>
								<tbody>

<?php
				foreach ($peers as $peer):
?>
									<tr class="peerbg_color">
										<td><?=htmlspecialchars(wg_truncate_pretty($peer['descr'], 16))?></td>
										<td><?=htmlspecialchars(wg_truncate_pretty($peer['publickey'], 16))?></td>
										<td><?=wg_generate_peer_allowedips_popup_link($peer['index'])?></td>
										<td><?=htmlspecialchars(wg_format_endpoint(false, $peer))?></td>
									</tr>
<?php
				endforeach;
?>
								</tbody>
							</table>
						</td>
<?php
			else:
?>
						<td colspan="6"><?=gettext("No peers have been configured")?></td>
<?php
			endif;
?>
					</tr>
<?php
		endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>
<?php
	endif;
?>
	<nav class="action-buttons">
		<a href="#" class="btn btn-info btn-sm" id="showpeers">
			<i class="fa fa-info icon-embed-btn"></i>
			<?=gettext("Show Peers")?>
		</a>

		<a href="vpn_wg_tunnels_edit.php" class="btn btn-success btn-sm">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Add Tunnel")?>
		</a>
	</nav>
</form>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var peershidden = true;
	var keyshidden = true;

	hideClass('peer-entries', peershidden);

	// Toggle peer visibility
	$('#showpeers').click(function () {
		peershidden = !peershidden;
		hideClass('peer-entries', peershidden);
	})

});
//]]>
</script>

<?php 

include('foot.inc');

// Must be included last
include('wireguard/wg_foot.inc');

?>