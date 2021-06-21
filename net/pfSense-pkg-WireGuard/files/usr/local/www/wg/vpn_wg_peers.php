<?php
/*
 * vpn_wg_peers.php
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
##|*MATCH=vpn_wg_peers.php*
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

	if (isset($_POST['apply'])) {

		$ret_code = 0;

		if (is_subsystem_dirty($wgg['subsystems']['wg'])) {

			if (wg_is_service_running()) {

				$tunnels_to_apply = wg_apply_list_get('tunnels');

				$sync_status = wg_tunnel_sync($tunnels_to_apply, true, true);

				$ret_code |= $sync_status['ret_code'];

			}

			if ($ret_code == 0) {

				clear_subsystem_dirty($wgg['subsystems']['wg']);

			}

		}

	}

	if (isset($_POST['peer'])) {

		$peer_idx = $_POST['peer'];

		switch ($_POST['act']) {

			case 'toggle':

				$res = wg_toggle_peer($peer_idx);

				break;

			case 'delete':
				
				$res = wg_delete_peer($peer_idx);

				break;

			default:
				
				// Shouldn't be here, so bail out.
				header('Location: /wg/vpn_wg_peers.php');

				break;
				
		}

		$input_errors = $res['input_errors'];

		if (empty($input_errors)) {

			if (wg_is_service_running() && $res['changes']) {

				mark_subsystem_dirty($wgg['subsystems']['wg']);

				// Add tunnel to the list to apply
				wg_apply_list_add('tunnels', $res['tuns_to_sync']);

			}

		}

	}

}

$shortcut_section = 'wireguard';

$pgtitle = array(gettext('VPN'), gettext('WireGuard'), gettext('Peers'));
$pglinks = array('', '/wg/vpn_wg_tunnels.php', '@self');

$tab_array = array();
$tab_array[] = array(gettext('Tunnels'), false, '/wg/vpn_wg_tunnels.php');
$tab_array[] = array(gettext('Peers'), true, '/wg/vpn_wg_peers.php');
$tab_array[] = array(gettext('Settings'), false, '/wg/vpn_wg_settings.php');
$tab_array[] = array(gettext('Status'), false, '/wg/status_wireguard.php');

include('head.inc');

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
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('WireGuard Peers')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-hover table-striped table-condensed">
				<thead>
					<tr>
						<th><?=gettext('Description')?></th>
						<th><?=gettext('Public key')?></th>
						<th><?=gettext('Tunnel')?></th>
						<th><?=gettext('Allowed IPs')?></th>
						<th><?=htmlspecialchars(wg_format_endpoint(true))?></th>
						<th><?=gettext('Actions')?></th>
					</tr>
				</thead>
				<tbody>
<?php
if (is_array($wgg['peers']) && count($wgg['peers']) > 0):

		foreach ($wgg['peers'] as $peer_idx => $peer):
?>
					<tr ondblclick="document.location='<?="vpn_wg_peers_edit.php?peer={$peer_idx}"?>';" class="<?=wg_peer_status_class($peer)?>">
						<td><?=htmlspecialchars(wg_truncate_pretty($peer['descr'], 16))?></td>
						<td class="pubkey" title="<?=htmlspecialchars($peer['publickey'])?>">
							<?=htmlspecialchars(wg_truncate_pretty($peer['publickey'], 16))?>
						</td>
						<td><?=htmlspecialchars($peer['tun'])?></td>
						<td><?=wg_generate_peer_allowedips_popup_link($peer_idx)?></td>
						<td><?=htmlspecialchars(wg_format_endpoint(false, $peer))?></td>
						<td style="cursor: pointer;">
							<a class="fa fa-pencil" title="<?=gettext('Edit Peer')?>" href="<?="vpn_wg_peers_edit.php?peer={$peer_idx}"?>"></a>
							<?=wg_generate_toggle_icon_link($peer, 'Click to toggle enabled/disabled status', "?act=toggle&peer={$peer_idx}")?>
							<a class="fa fa-trash text-danger" title="<?=gettext('Delete Peer')?>" href="<?="?act=delete&peer={$peer_idx}"?>" usepost></a>
						</td>
					</tr>

<?php
		endforeach;

else:
?>
					<tr>
						<td colspan="6">
							<?php print_info_box(gettext('No WireGuard peers have been configured. Click the "Add Peer" button below to create one.'), 'warning', null); ?>
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
		<a href="vpn_wg_peers_edit.php" class="btn btn-success btn-sm">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext('Add Peer')?>
		</a>
	</nav>
</form>

<script type="text/javascript">
//<![CDATA[
	$('.pubkey').click(function () {

		navigator.clipboard.writeText($(this).attr('title'));

	});
//]]>
</script>

<?php 

include('foot.inc');

// Must be included last
include('wireguard/wg_foot.inc');

?>