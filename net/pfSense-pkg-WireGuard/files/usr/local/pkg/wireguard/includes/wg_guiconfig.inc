<?php
/*
 * wg_guiconfig.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/theonemcdonald)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the 'License');
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an 'AS IS' BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// pfSense includes
require_once('util.inc');

// WireGuard includes
require_once('wireguard/includes/wg.inc');
require_once('wireguard/includes/wg_globals.inc');

function wg_print_service_warning($only_with_tunnels = true) {
	global $wgg;

	if (!wg_is_service_running() 
	    && (!$only_with_tunnels || count($wgg['tunnels']) > 0)) {
		print_info_box(gettext('The WireGuard service is not running.'), 'danger', null);
	}
}

function wg_print_config_apply_box() {
	global $wgg;

	$s = fn($x) => $x;

	if (is_subsystem_dirty($wgg['subsystems']['wg'])) {
		print_apply_box("{$s(gettext('The WireGuard configuration has been changed.'))}<br />
					{$s(gettext('The changes must be applied for them to take effect.'))}<br />
					{$s(gettext('Notice: This action may momentarily suspend active WireGuard peer connections on any changed tunnels.'))}");
	}
}

// Formats peer endpoint address and port for the UI
function wg_format_endpoint($header = true, $peer = null, $endpoint_key = 'endpoint', $port_key = 'port') {
	$s = fn($x) => $x;

	if ($header) {
		return "{$s(gettext('Endpoint'))} : {$s(gettext('Port'))}";
	}

	if (!is_null($peer)) {
		if (!empty($peer[$endpoint_key])) {
			return "{$peer[$endpoint_key]}:{$peer[$port_key]}";
		} else {
			return gettext('Dynamic');
		}
	}
}

// Gets a list of available tunnels in a format suitable for input selection boxes
function wg_get_tun_list() {
	global $config, $wgg;

	// Format tunnel description if one is configured
	$tunDescr = fn($tunnel) => !empty($tunnel['descr']) ? " ({$tunnel['descr']})" : null;

	$a_ret = array();

	// Always include the unassigned option first
	$a_ret['unassigned'] = 'Unassigned';

	if (is_array($wgg['tunnels'])) {
		foreach ($wgg['tunnels'] as $tunnel) {
			$a_ret[$tunnel['name']] = "{$tunnel['name']}{$tunDescr($tunnel)}";	
		}
	}

	// Consumers of this function always expect an array type
	return $a_ret;
}

// Truncates a given string if necessary and formats it to make it look good
function wg_truncate_pretty($text, $max_length) {
	$s_truncated = substr($text, 0, $max_length);

	$s_pretty_dots = (strlen($text) > $max_length) ? '...' : null;

	return "{$s_truncated}{$s_pretty_dots}";
}

function wg_interface_status_icon($status = 'up', $enabled_class = "fa fa-arrow-up text-success", $disabled_class = "fa fa-arrow-down text-danger") {
	global $wgg;

	$s = fn($x) => $x;

	$ret_class = ($status === 'up') ? $enabled_class : $disabled_class;

	$ret_html = "<i class=\"{$s(htmlspecialchars($ret_class))}\" style=\"vertical-align: middle;\" title=\"{$s(htmlspecialchars($status))}\"></i>";

	return $ret_html;
}

/* 
 * Returns the appropriate icon for peer handshake status based on latest handshake time
 * 
 * $latest_handshake should be format compatible with DateTime
 */
function wg_handshake_status_icon($latest_handshake = '@0', $tunnel_status = 'up', $fa_icon = "fa-handshake") {
	global $wgg;

	$s = fn($x) => $x;

	try {
		$end_time = new DateTimeImmutable;

		$start_time = new DateTime($latest_handshake);
	} catch (Exception $e) {
		return null;
	}

	$diff_time = $end_time->diff($start_time);

	$diff_time_seconds = abs($end_time->getTimestamp() - $start_time->getTimestamp());

	$a_thresholds = $wgg['handshake_thresholds'];

	foreach ($a_thresholds as $threshold => $value) {
		if ($diff_time_seconds >= $threshold) {
			$ret_class = $value['class'];
			$ret_title = $value['title'];
			break;
		}
	}

	$ret_html = "<i class=\"fa {$s(htmlspecialchars($fa_icon))} {$s(htmlspecialchars($ret_class))}\" style=\"vertical-align: middle;\" title=\"{$s(htmlspecialchars($ret_title))}\"></i>";

	return $ret_html;
}

/* 
 * Formats a given time difference in a human-friendly way
 * 
 * $start and $end should be format compatible with DateTime
 */
function wg_human_time_diff($start, $end = null, $num_units = 2, $from_epoch = false) {
	if (is_null($end)) {
		$end = new DateTime('now');
	}

	// If these fail for any reason, just bail out...
	try {
		if (!($start instanceof DateTime)) {
			$start = new DateTime($start);
		}

		if (!($end instanceof DateTime)) {
			$end = new DateTime($end);
		}
	} catch (Exception $e) {
		return gettext('never');
	}

	$interval = $end->diff($start);

	$doPlural = fn($nb, $str) => ($nb <= 1) ? $str[0] : $str[1];

	$doTense = fn($interval) => ($interval->invert) ? gettext('ago') : gettext('from now');

	$a_format = array();

	$a_interval = get_object_vars($interval);

	$tokens	= array(
			'y' => array(gettext('year'), gettext('years')),
			'm' => array(gettext('month'), gettext('months')),
			'd' => array(gettext('day'), gettext('days')),
			'h' => array(gettext('hour'), gettext('hours')),
			'i' => array(gettext('minute'), gettext('minutes')),
			's' => array(gettext('second'), gettext('seconds')));

	foreach ($tokens as $key => $value) {
		if ($a_interval[$key] != 0) {
			$a_format[] = "%{$key} {$doPlural($a_interval[$key], $value)}";
		}
	}

	if ((count($a_format) == 0) || (($start->getTimestamp() == 0) && !$from_epoch)) {
		return gettext('never');
	}

	$s_format = implode(', ', array_slice($a_format, 0, $num_units));

	return "{$interval->format($s_format)} {$doTense($interval)}";
}

// Returns the appropriate text input field type for secrets
function wg_secret_input_type() {
	global $wgg;

	wg_globals();

	$type = 'text';

	if (isset($wgg['config']['hide_secrets'])
	    && $wgg['config']['hide_secrets'] =='yes') {
		$type = 'password';
	}

	return $type;
}

// Returns bool indicating if peers should be hidden on status page on load
function wg_status_peers_hidden() {
	global $wgg;

	wg_globals();

	return ($wgg['config']['hide_peers'] === 'yes');
}

function wg_tunnel_status_class($tunnel = null) {
	global $wgg;

	if (isset($tunnel) && is_array($tunnel)) {
		if (wg_is_service_running() && ($tunnel['enabled'] == 'yes')) {
			return 'enabled';
		}
	}

	return 'disabled';
}

function wg_peer_status_class($peer = null) {
	global $wgg;

	if (isset($peer) && is_array($peer)) {
		$tunnel_state = true;

		// We want to visually disable peers if the tunnel is disabled...
		if ([$tun_idx, $tunnel, $is_new] = wg_tunnel_get_config_by_name($peer['tun'])) {
			$tunnel_state = ($tunnel['enabled'] == 'yes');
		}

		if (wg_is_service_running() && ($peer['enabled'] == 'yes') && $tunnel_state) {
			return 'enabled';
		}
	}

	return 'disabled';
}

// Generates a toggle icon link based on the status of the target
function wg_generate_toggle_icon_link($enabled = true, $type = null, $href = '#', $post = true, $icon_enabled = 'fa-ban', $icon_disabled = 'fa-check-square-o') {
	$s = fn($x) => $x;
	
	$icon = $enabled ? $icon_enabled : $icon_disabled;

	$action = $enabled ? gettext('Disable') : gettext('Enable');

	$title = "{$action} {$type}";

	$usepost = $post ? ' usepost' : null;

	return "<a class=\"fa {$s(htmlspecialchars($icon))}\" title=\"{$s(htmlspecialchars($title))}\" href=\"{$s(htmlspecialchars($href))}\"{$s(htmlspecialchars($usepost))}></a>";
}

function wg_compose_address_popover_link($addresses, $title, $href) {
	global $wgg;

	$s = fn($x) => $x;

	$getDescr = fn($x) => !empty($x) ? htmlspecialchars($x) : "<i>({$s(gettext('none'))})</i>";

	$ret_html = null;

	if (is_array($addresses) && count($addresses) > 0) {
		$extras = count($addresses) - 1;

		if ($extras > 0) {
			$data_html = 	<<<"START"
					<table>
						<thead>
							<th>{$s(gettext('Address'))}</th>
							<th style='padding-left: 10px;'>{$s(gettext('Description'))}</th>
						</thead>
						<tbody>

					START;

			foreach ($addresses as $address) {
				$address['address'] = "{$address['address']}/{$address['mask']}";

				$data_html .= 	<<<"ROW"
								<tr>
									<td>{$s(htmlspecialchars($address['address']))}</td>
									<td style='padding-left: 10px;'>{$getDescr($address['descr'])}</td>
								</tr>

						ROW;
			}

			$data_html .= 	<<<"END"
						</tbody>
					</table>
					END;

			$hint = "{$addresses[0]['address']}/{$addresses[0]['mask']} (+{$extras})";

			$popover_params = " data-toggle=\"popover\" data-trigger=\"hover focus\" data-content=\"{$data_html}\" data-html=\"true\"";
		} else {
			$title = $addresses[0]['descr'];

			$hint = "{$addresses[0]['address']}/{$addresses[0]['mask']}";

			$popover_params = null;
		}

		$ret_html = "<a href=\"{$s(htmlspecialchars($href))}\" title=\"{$s(htmlspecialchars($title))}\"{$popover_params}>{$s(htmlspecialchars($hint))}</a>";
	} else {
		$ret_html = "({$s(gettext('none'))})";
	}

	return $ret_html;
}

function wg_generate_tunnel_address_popover_link($tunnel_name) {
	global $wgg;

	$s = fn($x) => $x;

	if ([$tun_idx, $tunnel, $is_new] = wg_tunnel_get_config_by_name($tunnel_name, false)) {
		if (!is_wg_tunnel_assigned($tunnel['name'])) {
			return wg_compose_address_popover_link($tunnel['addresses']['row'], gettext('Interface Addresses'), "vpn_wg_tunnels_edit.php?tun={$tunnel_name}");
		} else {
			$wg_pfsense_if = wg_get_pfsense_interface_info($tunnel['name']);

			$ret_html = 	<<<"RET"
					<i class="fa fa-sitemap" style="vertical-align: middle;"></i>
					<a style="padding-left: 3px" href="../../interfaces.php?if={$s(htmlspecialchars($wg_pfsense_if['name']))}">{$s(htmlspecialchars($wg_pfsense_if['descr']))} ({$s(htmlspecialchars($wg_pfsense_if['name']))})</a>
					
					RET;

			return $ret_html;
		}
	}

	return null;
}

function wg_generate_peer_allowedips_popup_link($peer_idx) {
	global $wgg;

	$s = fn($x) => $x;

	if ([$peer_idx, $peer, $is_new] = wg_peer_get_config($peer_idx, false)) {
		return wg_compose_address_popover_link($peer['allowedips']['row'], gettext('Allowed IPs'), "vpn_wg_peers_edit.php?peer={$peer_idx}");
	}

	return null;
}

?>
