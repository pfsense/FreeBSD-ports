<?php
/*
 * status_wireguard.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/theonemcdonald)
 * Copyright (c) 2021 Vajonam
 * Copyright (c) 2020 Ascrod
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
##|*IDENT=page-status-wireguard
##|*NAME=Status: WireGuard
##|*DESCR=Allow access to the 'Status: WireGuard' page.
##|*MATCH=status_wireguard.php*
##|-PRIV

// pfSense includes
require_once('guiconfig.inc');
require_once('util.inc');

// WireGuard includes
require_once('wireguard/includes/wg.inc');
require_once('wireguard/includes/wg_guiconfig.inc');

global $wgg;

// Initialize $wgg state
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
}

$shortcut_section = "wireguard";

$pgtitle = array(gettext("Status"), gettext("WireGuard"));
$pglinks = array("", "@self");

$tab_array = array();
$tab_array[] = array(gettext("Tunnels"), false, "/wg/vpn_wg_tunnels.php");
$tab_array[] = array(gettext("Peers"), false, "/wg/vpn_wg_peers.php");
$tab_array[] = array(gettext("Settings"), false, "/wg/vpn_wg_settings.php");
$tab_array[] = array(gettext("Status"), true, "/wg/status_wireguard.php");

include("head.inc");

wg_print_service_warning();

if (isset($_POST['apply'])) {
	print_apply_result_box($ret_code);
}

wg_print_config_apply_box();

display_top_tabs($tab_array);

$a_devices = wg_get_status();

$peers_hidden = wg_status_peers_hidden();
?>

<?php if ($peers_hidden): ?>
<style> tr[class^='treegrid-parent-'] { display: none; } </style>
<?php endif; ?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('WireGuard Status')?></h2>
	</div>
	<div class="table-responsive panel-body">
		<table class="table table-hover table-striped table-condensed tree" style="overflow-x: visible;">
			<thead>
				<th><?=gettext('Tunnel')?></th>
				<th><?=gettext('Description')?></th>
				<th><?=gettext('Peers')?></th>
				<th><?=gettext('Public Key')?></th>
				<th><?=gettext('Address')?> / <?=gettext('Assignment')?></th>
				<th><?=gettext('MTU')?></th>
				<th><?=gettext('Listen Port')?></th>
				<th><?=gettext('RX')?></th>
				<th><?=gettext('TX')?></th>
			</thead>
			<tbody>
<?php
if (!empty($a_devices)):
	foreach ($a_devices as $device_name => $device):
?>
				<tr class="<?="treegrid-{$device_name}"?>">
					<td>
						<?=wg_interface_status_icon($device['status'])?>
						<a href="vpn_wg_tunnels_edit.php?tun=<?=htmlspecialchars($device_name)?>"><?=htmlspecialchars($device_name)?></a>
					</td>
					<td><?=htmlspecialchars(wg_truncate_pretty($device['config']['descr'], 16))?></td>
					<td><?=count($device['peers'])?></td>
					<td title="<?=htmlspecialchars($device['public_key'])?>">
						<?=htmlspecialchars(wg_truncate_pretty($device['public_key'], 16))?>
					</td>
					<td><?=wg_generate_tunnel_address_popover_link($device_name)?></td>
					<td><?=htmlspecialchars($device['mtu'])?></td>
					<td><?=htmlspecialchars($device['listen_port'])?></td>
					<td><?=htmlspecialchars(format_bytes($device['transfer_rx']))?></td>
					<td><?=htmlspecialchars(format_bytes($device['transfer_tx']))?></td>
				</tr>
				<tr class="<?="treegrid-parent-{$device_name}"?>">
					<td style="font-weight: bold;"><?=gettext('Peers')?></td>
					<td colspan="8" class="contains-table">
						<table class="table table-hover table-condensed">
							<thead>
								<th><?=gettext('Description')?></th>
								<th><?=gettext('Latest Handshake')?></th>
								<th><?=gettext('Public Key')?></th>
								<th><?=gettext('Endpoint')?></th>
								<th><?=gettext('Allowed IPs')?></th>
								<th><?=gettext('RX')?></th>
								<th><?=gettext('TX')?></th>
							</thead>
							<tbody>
<?php
		if (count($device['peers']) > 0):
			foreach($device['peers'] as $peer):
?>
								<tr>
									<td>
										<?=wg_handshake_status_icon("@{$peer['latest_handshake']}")?>
										<?=htmlspecialchars(wg_truncate_pretty($peer['config']['descr'], 16))?>
									</td>
									<td><?=htmlspecialchars(wg_human_time_diff("@{$peer['latest_handshake']}"))?></td>
									<td title="<?=htmlspecialchars($peer['public_key'])?>">
										<?=htmlspecialchars(wg_truncate_pretty($peer['public_key'], 16))?>
									</td>
									<td><?=htmlspecialchars($peer['endpoint'])?></td>
									<td><?=wg_generate_peer_allowedips_popup_link(wg_peer_get_array_idx($peer['config']['publickey'], $peer['config']['tun']))?></td>
									<td><?=htmlspecialchars(format_bytes($peer['transfer_rx']))?></td>
									<td><?=htmlspecialchars(format_bytes($peer['transfer_tx']))?></td>
								</tr>
<?php	
			endforeach;
		else:
?>
								<tr>
									<td colspan="7"><?=gettext('No peers have been configured')?></td>
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
elseif (empty($wgg['tunnels'])):
?>
				<tr>
					<td colspan="9"><?php print_info_box(gettext('No WireGuard tunnels have been configured.'), 'warning', null); ?></td>
				</tr>
<?php
else:
?>
				<tr>
					<td colspan="9"><?php print_info_box(gettext('No WireGuard status information is available.'), 'warning', null); ?></td>
				</tr>
<?php
endif;
?>
			</tbody>
		</table>
    	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('Package Versions')?></h2>
	</div>
	<div class="table-responsive panel-body">
		<table class="table table-hover table-striped table-condensed">
			<thead>
				<tr>
					<th><?=gettext('Name')?></th>
					<th><?=gettext('Version')?></th>
    					<th><?=gettext('Comment')?></th>
				</tr>
			</thead>
			<tbody>
<?php
			foreach (wg_pkg_info() as ['name' => $name, 'version' => $version, 'comment' => $comment]):
?>
    				<tr>
					<td><?=htmlspecialchars($name)?></td>
					<td><?=htmlspecialchars($version)?></td>
					<td><?=htmlspecialchars($comment)?></td>

				</tr>
<?php
			endforeach;
?>

			</tbody>
		</table>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('.tree').treegrid({
		expanderExpandedClass: 'fa fa fa-chevron-down',
		expanderCollapsedClass: 'fa fa fa-chevron-right',
		initialState: (<?=json_encode($peers_hidden)?> ? 'collapsed' : 'expanded')
	});
});
//]]>
</script>

<?php
include('wireguard/includes/wg_foot.inc');
include('foot.inc');
?>