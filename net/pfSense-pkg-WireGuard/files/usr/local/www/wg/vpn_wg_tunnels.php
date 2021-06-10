<?php
/*
 * vpn_wg_tunnels.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/theonemcdonald)
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
require_once('pfsense-utils.inc');
require_once('service-utils.inc');

// WireGuard includes
require_once('wireguard/wg.inc');
require_once('wireguard/wg_guiconfig.inc');

global $wgg;

wg_globals();

if ($_POST) {

	if (isset($_POST['apply'])) {

		$ret_code = 0;

		if (is_subsystem_dirty($wgg['subsystems']['wg'])) {

			if (wg_is_service_running()) {

				$tunnels_to_apply = wg_apply_list_get('tunnels');

				// TODO: Make extra services restart (true) a package setting
				$sync_status = wg_tunnel_sync($tunnels_to_apply, true);

				$ret_code |= $sync_status['ret_code'];

			}

			if ($ret_code == 0) {

				clear_subsystem_dirty($wgg['subsystems']['wg']);

			}

		}

	}

	if (isset($_POST['tun'])) {

		$tun_name = $_POST['tun'];

		switch ($_POST['act']) {

			case 'download':

				wg_download_tunnel($tun_name, '/wg/vpn_wg_tunnels.php');

				exit();

				break;

			case 'toggle':
				
				$res = wg_toggle_tunnel($tun_name);
				
				break;

			case 'delete':

				$res = wg_delete_tunnel($tun_name);

				break;

			default:

				// Shouldn't be here, so bail out.
				header('Location: /wg/vpn_wg_tunnels.php');

				break;

		}

		$input_errors = $res['input_errors'];

		if (empty($input_errors)) {

			if (wg_is_service_running() && $res['changes']) {

				mark_subsystem_dirty($wgg['subsystems']['wg']);

				// Add tunnel to the list to apply
				wg_apply_list_add($res['tun_to_sync'], 'tunnels');

			}

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

wg_print_service_warning();

if (isset($_POST['apply'])) {

	print_apply_result_box($ret_code);

}

wg_print_config_apply_box();

if (!empty($input_errors)) {

	print_input_errors($input_errors);

}

display_top_tabs($tab_array);

?>

<form name="mainform" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('WireGuard Tunnels')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-hover table-striped table-condensed">
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
if (is_array($wgg['tunnels']) && count($wgg['tunnels']) > 0):

		foreach ($wgg['tunnels'] as $tunnel):

			$peers = wg_get_tunnel_peers($tunnel['name']);
?>
					<tr ondblclick="document.location='vpn_wg_tunnels_edit.php?tun=<?=$tunnel['name']?>';" class="<?=wg_entrystatus_class($tunnel)?>">
						<td class="peer-entries"><?=gettext('Interface')?></td>
						<td><?=htmlspecialchars($tunnel['name'])?></td>
						<td><?=htmlspecialchars($tunnel['descr'])?></td>
						<td class="pubkey" title="<?=htmlspecialchars($tunnel['publickey'])?>">
							<?=htmlspecialchars(wg_truncate_pretty($tunnel['publickey'], 16))?>
						</td>
						<td><?=wg_generate_tunnel_address_popover_link($tunnel['name'])?></td>
						<td><?=htmlspecialchars($tunnel['listenport'])?></td>
						<td><?=count($peers)?></td>

						<td style="cursor: pointer;">
							<a class="fa fa-user-plus" title="<?=gettext('Add Peer')?>" href="<?="vpn_wg_peers_edit.php?tun={$tunnel['name']}"?>"></a>
							<a class="fa fa-pencil" title="<?=gettext('Edit Tunnel')?>" href="<?="vpn_wg_tunnels_edit.php?tun={$tunnel['name']}"?>"></a>
							<a class="fa fa-download" title="<?=gettext('Download Configuration')?>" href="<?="?act=download&tun={$tunnel['name']}"?>" usepost></a>
							<?=wg_generate_toggle_icon_link($tunnel, 'Click to toggle enabled/disabled status', "?act=toggle&tun={$tunnel['name']}")?>
							<a class="fa fa-trash text-danger" title="<?=gettext('Delete Tunnel')?>" href="<?="?act=delete&tun={$tunnel['name']}"?>" usepost></a>
						</td>
					</tr>

					<tr class="peer-entries peerbg_color">
						<td><?=gettext("Peers")?></td>
<?php
			if (count($peers) > 0):
?>
						<td colspan="6">
							<table class="table table-hover">
								<thead>
									<tr>
										<th><?=gettext("Description")?></th>
										<th><?=gettext("Public key")?></th>
										<th><?=gettext("Allowed IPs")?></th>
										<th><?=htmlspecialchars(wg_format_endpoint(true))?></th>
									</tr>
								</thead>
								<tbody>

<?php
				foreach ($peers as $peer):
?>
									<tr>
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

else:
?>
					<tr>
						<td colspan="8">
							<?php print_info_box(gettext('No WireGuard tunnels have been configured. Click the "Add Tunnel" button below to create one.'), 'warning', null); ?>
						</td>
					</tr>
<?php
endif;
?>
				</tbody>
			</table>
		</div>
	</div>
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
	});

	$('.pubkey').click(function () {

		navigator.clipboard.writeText($(this).attr('title'));

	});

});
//]]>
</script>

<?php 

include('foot.inc');

// Must be included last
include('wireguard/wg_foot.inc');

?>