<?php
/*
 * vpn_wg_tunnels.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/rcmcdonald91)
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
require_once('wireguard/includes/wg.inc');
require_once('wireguard/includes/wg_guiconfig.inc');

global $wgg;

// Initialize $wgg state
wg_globals();

$pconfig = [];

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

	if (isset($_POST['tun'])) {
		$tun_name = $_POST['tun'];

		/* Check if the submitted tunnel exists
		 * https://redmine.pfsense.org/issues/12731
		 */
		$tun_found = false;
		if (is_array($wgg['tunnels']) && count($wgg['tunnels']) > 0) {
			foreach ($wgg['tunnels'] as $tunnel) {
				if ($tunnel['name'] == $tun_name) {
					$tun_found = true;
					break;
				}
			}
		}

		if ($tun_found) {
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
		} else {
			/* User submitted a tunnel that does not exist, so bail.
			 * https://redmine.pfsense.org/issues/12731
			 */
			$input_errors = array(gettext("The requested tunnel does not exist."));
		}

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

$pgtitle = array(gettext('VPN'), gettext('WireGuard'), gettext('Tunnels'));
$pglinks = array('', '@self', '@self');

$tab_array = array();
$tab_array[] = array(gettext('Tunnels'), true, '/wg/vpn_wg_tunnels.php');
$tab_array[] = array(gettext('Peers'), false, '/wg/vpn_wg_peers.php');
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

<style> tr[class^='treegrid-parent-'] { display: none; } </style>

<form name="mainform" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('WireGuard Tunnels')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-hover table-striped table-condensed tree">
				<thead>
					<tr>
						<th><?=gettext('Name')?></th>
						<th><?=gettext('Description')?></th>
						<th><?=gettext('Public Key')?></th>
						<th><?=gettext('Address')?> / <?=gettext('Assignment')?></th>
						<th><?=gettext('Listen Port')?></th>
						<th><?=gettext('Peers')?></th>
						<th><?=gettext('Actions')?></th>
					</tr>
				</thead>
				<tbody>
<?php
if (is_array($wgg['tunnels']) && count($wgg['tunnels']) > 0):
		foreach ($wgg['tunnels'] as $tunnel):
			$peers = wg_tunnel_get_peers_config($tunnel['name']);
?>
					<tr class="<?="treegrid-{$tunnel['name']}"?> <?=wg_tunnel_status_class($tunnel)?>">
						<td><?=htmlspecialchars($tunnel['name'])?></td>
						<td><?=htmlspecialchars($tunnel['descr'])?></td>
						<td style="cursor: pointer;" class="pubkey" title="<?=htmlspecialchars($tunnel['publickey'])?>">
							<?=htmlspecialchars(wg_truncate_pretty($tunnel['publickey'], 32))?>
						</td>
						<td><?=wg_generate_tunnel_address_popover_link($tunnel['name'])?></td>
						<td><?=htmlspecialchars($tunnel['listenport'])?></td>
						<td><?=count($peers)?></td>

						<td style="cursor: pointer;">
							<a class="fa-solid fa-user-plus" title="<?=gettext('Add Peer')?>" href="<?="vpn_wg_peers_edit.php?tun={$tunnel['name']}"?>"></a>
							<a class="fa-solid fa-pencil" title="<?=gettext('Edit Tunnel')?>" href="<?="vpn_wg_tunnels_edit.php?tun={$tunnel['name']}"?>"></a>
							<a class="fa-solid fa-download" title="<?=gettext('Download Configuration')?>" href="<?="?act=download&tun={$tunnel['name']}"?>" usepost></a>
							<?=wg_generate_toggle_icon_link(($tunnel['enabled'] == 'yes'), 'tunnel', "?act=toggle&tun={$tunnel['name']}")?>
							<a class="fa-solid fa-trash-can text-danger" title="<?=gettext('Delete Tunnel')?>" href="<?="?act=delete&tun={$tunnel['name']}"?>" usepost></a>
						</td>
					</tr>

					<tr class="<?="treegrid-parent-{$tunnel['name']}"?>">
						<td style="font-weight: bold;"><?=gettext('Peers')?></td>
						<td colspan="7" class="contains-table">
							<table class="table table-hover table-striped table-condensed">
								<thead>
									<th><?=gettext('Description')?></th>
									<th><?=gettext('Public Key')?></th>
									<th><?=gettext('Tunnel')?></th>
									<th><?=gettext('Allowed IPs')?></th>
									<th><?=gettext('Endpoint')?></th>
								</thead>
								<tbody>
<?php
			if (count($peers) > 0):
				foreach ($peers as [$peer_idx, $peer, $is_new]):
?>
									<tr>
										<td><?=htmlspecialchars(wg_truncate_pretty($peer['descr'], 16))?></td>
										<td><?=htmlspecialchars(wg_truncate_pretty($peer['publickey'], 32))?></td>
										<td><?=htmlspecialchars($peer['tun'])?></td>
										<td><?=wg_generate_peer_allowedips_popup_link($peer_idx)?></td>
										<td><?=htmlspecialchars(wg_format_endpoint(false, $peer))?></td>
									</tr>
<?php
				endforeach;
			else:
?>
									<tr>
										<td colspan="5"><?=gettext('No peers have been configured')?></td>
									</tr>
<?php
			endif;
?>
								</tbody>
							</table>
						</td>
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
		<a href="vpn_wg_tunnels_edit.php" class="btn btn-success btn-sm">
			<i class="fa-solid fa-plus icon-embed-btn"></i>
			<?=gettext('Add Tunnel')?>
		</a>
	</nav>
</form>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('.pubkey').click(function () {
		var publicKey = $(this).attr('title');
		try {
			// The 'modern' way...
			navigator.clipboard.writeText(publicKey);
		} catch {
			console.warn("Failed to copy text using navigator.clipboard, falling back to commands");

			// Convert the TD contents to an input with pub key
			var pubKeyInput = $('<input/>', {val: publicKey});
			var oldText = $(this).text();

			// Add to DOM
			$(this).html(pubKeyInput);

			// Copy
			pubKeyInput.select();
			document.execCommand("copy");

			// Revert back to just text
			$(this).html(oldText);
		}
	});

	$('.tree').treegrid({
		expanderExpandedClass: 'fa-solid fa fa-chevron-down',
		expanderCollapsedClass: 'fa-solid fa fa-chevron-right',
		initialState: 'collapsed'
	});
});
//]]>
</script>

<?php
include('wireguard/includes/wg_foot.inc');
include('foot.inc');
?>
