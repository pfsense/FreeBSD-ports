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
require_once('wireguard/wg.inc');
require_once('wireguard/wg_guiconfig.inc');
require_once('wireguard/wg_service.inc');

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

?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('WireGuard Status')?></h2>
	</div>
	<div class="table-responsive panel-body">
		<table class="table table-hover table-striped table-condensed" style="overflow-x: visible;">
			<thead>
				<th><?=gettext('Tunnel')?></th>
				<th><?=gettext('Description')?></th>
				<th><?=gettext('# Peers')?></th>
				<th><?=gettext('Public Key')?></th>
				<th><?=gettext('Address / Assignment')?></th>
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
				<tr class="tunnel-entry">
					<td>
						<?=wg_interface_status_icon($device['status'])?>
						<a href="vpn_wg_tunnels_edit.php?tun=<?=htmlspecialchars($device_name)?>"><?=htmlspecialchars($device_name)?>
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
				<tr class="peer-entries">
					<td colspan="9">
						<table class="table table-hover table-condensed">
							<thead>
								<th><?=gettext('Peer')?></th>
								<th><?=gettext('Latest Handshake')?></th>
								<th><?=gettext('Public Key')?></th>
								<th><?=htmlspecialchars(wg_format_endpoint(true))?></th>
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
									<td><?=wg_generate_peer_allowedips_popup_link(wg_get_peer_idx($peer['config']['publickey'], $peer['config']['tun']))?></td>
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

<nav class="action-buttons">
	<a href="#" class="btn btn-info btn-sm" id="showpeers">
		<i class="fa fa-info icon-embed-btn"></i>
		<?=gettext("Show Peers")?>
	</a>
</nav>

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
			$a_packages = wg_pkg_info();

			foreach ($a_packages as $package):
?>
    				<tr>
					<td><?=htmlspecialchars($package['name'])?></td>
					<td><?=htmlspecialchars($package['version'])?></td>
					<td><?=htmlspecialchars($package['comment'])?></td>

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
	var peershidden = true;

	hideClass('peer-entries', peershidden);

	// Toggle peer visibility
	$('#showpeers').click(function () {
		peershidden = !peershidden;
		hideClass('peer-entries', peershidden);
	});

	$('.tunnel-entry').click(function () {
		$(this).next().toggle();
	});

});
//]]>
</script>

<?php 

include('foot.inc');

// Must be included last
include('wireguard/wg_foot.inc');

?>