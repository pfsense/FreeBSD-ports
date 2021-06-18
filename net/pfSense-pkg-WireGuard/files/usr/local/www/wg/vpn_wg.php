<?php
/*
 * vpn_wg.php
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
##|*IDENT=page-vpn-wg
##|*NAME=VPN: WireGuard
##|*DESCR=Allow access to the 'VPN: WireGuard' page.
##|*MATCH=vpn_wg.php*
##|-PRIV

// pfSense includes
require_once('guiconfig.inc');
require_once('functions.inc');

// WireGuard includes
require_once('wireguard/wg.inc');

global $wgg;

wg_globals();

$pgtitle = array(gettext("VPN"), gettext("WireGuard"), gettext("Tunnels"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "wireguard";

$tab_array = array();
$tab_array[] = array(gettext("Tunnels"), true, "vpn_wg.php");
$tab_array[] = array(gettext("Settings"), false, "vpn_wg_settings.php");
$tab_array[] = array(gettext("Status"), false, "status_wireguard.php");

include("head.inc");

// Delete a tunnel?
if (array_key_exists('delidx', $_POST) && isset($wgg['tunnels'][$_POST['delidx']])) {

	$iflist = get_configured_interface_list_by_realif();

	if (is_wg_tunnel_assigned($wgg['tunnels'][$_POST['delidx']]['name'])) {

		$input_errors[] = gettext('Cannot delete a WireGuard tunnel while it is assigned as an interface.');

	} else {

		wg_delete_tunnel($_POST['delidx']);

		header("Location: /wg/vpn_wg.php");

	}

}

add_package_tabs("wireguard", $tab_array);
display_top_tabs($tab_array);

if ($input_errors) {
	print_input_errors($input_errors);
}

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
						<th><?=gettext("Address / Assignment")?></th>
						<th><?=gettext("Port")?></th>
						<th><?=gettext("# Peers")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php

		$i = 0;
		foreach ($wgg['tunnels'] as $tunnel):
			$entryStatus = ($tunnel['enabled'] == 'yes') ? 'enabled':'disabled';
			if (!$tunnel['peers'] || !is_array($tunnel['peers'])) {
				$tunnel['peers'] = array();
			}
			if (!$tunnel['peers']['wgpeer'] || !is_array($tunnel['peers']['wgpeer'])) {
				$tunnel['peers']['wgpeer'] = array();
			}

			if (is_wg_tunnel_assigned($tunnel)) {

				// We want all configured interfaces, including disabled ones
				$iflist = get_configured_interface_list_by_realif(true);
				$ifdescr = get_configured_interface_with_descr(true);

				$iffriendly = $ifdescr[$iflist[$tunnel['name']]];

				$tunnel['interface']['address'] = $iffriendly;

			}

?>
					<tr ondblclick="document.location='vpn_wg_edit.php?index=<?=$i?>';" class="<?= $entryStatus ?>">
						<td class="peer-entries"><?=gettext('Interface')?></td>
						<td><?=htmlspecialchars($tunnel['name'])?></td>
						<td><?=htmlspecialchars($tunnel['descr'])?></td>
						<td><?=htmlspecialchars($tunnel['interface']['address'])?></td>
						<td><?=htmlspecialchars($tunnel['interface']['listenport'])?></td>
						<td><?=count($tunnel['peers']['wgpeer'])?></td>

						<td style="cursor: pointer;">
							<a class="fa fa-pencil" href="vpn_wg_edit.php?index=<?=$i?>" title="<?=gettext("Edit Tunnel"); ?>"></a>
							<a class="fa fa-trash text-danger" id="Xdel_<?=$i?>" title="<?=gettext('Delete Tunnel'); ?>"></a>
						</td>
					</tr>

					<tr class="peer-entries peerbg_color">
						<td>Peers</td>
<?php
			if (count($tunnel['peers']['wgpeer']) > 0):
?>
						<td colspan="6">
							<table class="table table-hover peerbg_color">
								<thead>
									<tr class="peerbg_color">
										<th>Description</th>
										<th>Endpoint</th>
										<th>Allowed IPs</th>
										<th>Public key</th>
									</tr>
								</thead>
								<tbody>

<?php
				foreach ($tunnel['peers']['wgpeer'] as $peer):
?>
									<tr class="peerbg_color">
										<td><?=htmlspecialchars($peer['descr'])?></td>
										<td>
										<?php if (!empty($peer["endpoint"])): ?>
											<?=htmlspecialchars($peer['endpoint'])?>:<?=((empty($peer["port"])) ? '51820' : htmlspecialchars($peer["port"]))?>
										<?php else: ?>
											<?=gettext("Dynamic")?>
										<?php endif; ?>
										</td>
										<td><?=htmlspecialchars($peer['allowedips'])?></td>
										<td><?=htmlspecialchars($peer['publickey'])?></td>
									</tr>
<?php
				endforeach;
?>
								</tbody>
							</table>
						</td>
						<?php
			else:
				print('<td colspan="6">' . gettext("No peers have been configured") . '</td>');
			endif;
?>
					</tr>
<?php
			$i++;
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
			<?=gettext("Show peers")?>
		</a>

		<a href="vpn_wg_edit.php" class="btn btn-success btn-sm">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Add Tunnel")?>
		</a>
	</nav>
</form>

<!-- Simple form that is submitted on tunnel delete -->
<form name="delform" id="delform" method="post">
	<input id="delidx" name="delidx" type="hidden" />
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

	// Delete tunnel
	$('[id^=Xdel_]').click(function (event) {
		var idx = event.target.id.split('_')[1];
		$('#delidx').val(idx);  // Set the id of the tunnel
		$('#delform').submit(); // Submit the form
	});
});
//]]>
</script>

<?php
include("foot.inc");
?>
