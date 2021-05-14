<?php
/*
 * wg_globals.inc
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
require_once('wireguard/wg_globals.inc');

// Formats peer endpoint address and port for the UI
function wg_format_endpoint($header = true, $peer = null, $endpoint_key = 'endpoint', $port_key = 'port') {

	if ($header) {

		$endpoint = gettext('Endpoint');

		$port = gettext('Port');

		return "{$endpoint} : {$port}";

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

	$a_ret = array();

	// Always include the unassigned option first
	$a_ret['unassigned'] = 'Unassigned';

	if (is_array($wgg['tunnels'])) {

		foreach ($wgg['tunnels'] as $tunnel) {

			$a_ret[$tunnel['name']] = "{$tunnel['name']} ({$tunnel['descr']})";	

		}
	
	}

	return $a_ret;

}

// Truncates a given string if if necessary and formats it to make it look good
function wg_truncate_pretty($str, $length) {

	$s_truncated = substr($str, 0, $length);

	$s_pretty_dots = (strlen($str) > $length) ? '...' : '';

	return "{$s_truncated}{$s_pretty_dots}";

}

function wg_interface_status_icon($status, $fa_icon = "fa-ethernet") {
	global $wgg;

	$ret_class = ($status == "up") ? 'text-success' : '';

	$ret_html = "<i class=\"fa {$fa_icon} {$ret_class}\" style=\"vertical-align: middle;\" title=\"{$status}\"></i>";

	return $ret_html;

}

// Returns the appropriate fa icon class for handshake status icon
function wg_handshake_status_icon($latest_handshake = 0, $fa_icon = "fa-handshake") {
	global $wgg;

	$current_time = time();

	$diff_time = $current_time - $latest_handshake;

	$a_thresholds = $wgg['handshake_thresholds'];

	foreach ($a_thresholds as $threshold => $value) {

		if ($diff_time >= $threshold) {
	
			$ret_class = $value['class'];

			$ret_title = $value['title'];

			break;

		}

	}

	$ret_html = "<i class=\"fa {$fa_icon} {$ret_class}\" style=\"vertical-align: middle;\" title=\"{$ret_title}\"></i>";

	return $ret_html;

}

// Formats a given time difference in a human-friendly way
function wg_human_time_diff($start_time, $end_time = null, $from_epoch = false) {
	
	// If end time is not supplied, use the current time instead
	$end_time = is_null($end_time) ? time() : $end_time;

	// If start time is zero, proceed unless we are actually interested in calculating time from epoch, probably not...
	$start_time = ($start_time > 0) ? $start_time : ($from_epoch ? $start_time : $end_time);

	$diff_time = abs($end_time - $start_time);

	$tokens = array (
		31536000 => 'year',
		2592000 => 'month',
		604800 => 'week',
		86400 => 'day',
		3600 => 'hour',
		60 => 'minute',
		1 => 'second'
	);

	foreach ($tokens as $unit => $text) {

		if ($diff_time < $unit) {
			
			continue;
			
		}

		$num_of_units = floor($diff_time / $unit);

		$plural = ($num_of_units > 1) ? 's' : '';
		
		return "{$num_of_units} {$text}{$plural}";

	}

	return "never";

}

// Returns the appropriate text input field type for secrets
function wg_secret_input_type() {
	global $wgg;

	wg_globals();

	if (isset($wgg['config']['hide_secrets']) && $wgg['config']['hide_secrets'] =='yes') {

		return 'password';

	}

	return 'text';
	
}

// Gets the appropriate class based on whether or not the target is enabled or disabled
function wg_entrystatus_class($target_device) {
	global $wgg;

	if (isset($target_device) && 
		$target_device['enabled'] == 'yes' &&
			is_module_loaded($wgg['kmod'])) {

		return 'enabled';

	}

	return 'disabled';

}

// Generates a toggle icon link based on the status of the target
function wg_generate_toggle_icon_link($target_device, $title = '', $href = '#', $usepost = true, $icon_enabled = 'fa-ban', $icon_disabled = 'fa-check-square-o') {

	if (isset($target_device)) {
	
		$s_icon = (isset($target_device['enabled']) && $target_device['enabled'] == 'yes') ? $icon_enabled : $icon_disabled;

		$s_title = gettext($title);

		$s_usepost = $usepost ? 'usepost' : '';

		return "<a class=\"fa {$s_icon}\" title=\"{$s_title}\" href=\"{$href}\" {$s_usepost}></a>";

	}

	return null;

}

function wg_generate_tunnel_address_popup_link($tunnel_name) {
	global $wgg;

	$tun_idx = wg_get_tunnel_array_index($tunnel_name);

	if (isset($wgg['tunnels'][$tun_idx]) && is_array($wgg['tunnels'][$tun_idx])) {

		$tunnel = $wgg['tunnels'][$tun_idx];

		$addresses = $tunnel['addresses']['row'];

		if (!is_wg_tunnel_assigned($tunnel['name'])) {
			
			if (is_array($addresses) && count($addresses) > 0) {

				$href = "vpn_wg_tunnels_edit.php?tun={$tunnel['name']}";

				$extras = count($addresses) - 1;

				if ($extras > 0) {

					$data_html = "<table><thead><th>Address</th><th style='padding-left: 10px;'>Description</th></thead><tbody>";
		
					foreach ($addresses as $address) {
		
						$data_html .= "<tr><td>{$address['address']}/{$address['mask']}</td><td style='padding-left: 10px;'>{$address['descr']}</td></tr>";
		
					}
		
					$data_html .= '</tbody></table>';
		
					$hint = "{$addresses[0]['address']} (+{$extras})";
		
					$ret_html = "<a href=\"{$href}\" title=\"Interface Addresses\" data-toggle=\"popover\" data-trigger=\"hover focus\" data-content=\"{$data_html}\" data-html=\"true\">{$hint}</a>";
		
					return $ret_html;
		
				} else {
		
					$ret_html = "<a href=\"{$href}\" title=\"{$addresses[0]['descr']}\">{$addresses[0]['address']}/{$addresses[0]['mask']}</a>";
		
					return $ret_html;
				
				}
		
			} else {

				return '(none)';

			}

		} else {

			$wg_pfsense_if = wg_get_pfsense_interface_info($tunnel['name']);

			return "<i class=\"fa fa-sitemap\" style=\"vertical-align: middle;\"></i><a style=\"padding-left: 3px\" href=\"../../interfaces.php?if={$wg_pfsense_if['name']}\">{$wg_pfsense_if['descr']} ({$wg_pfsense_if['name']})</a>";

		}

	}

}

function wg_generate_peer_allowedips_popup_link($peer_idx) {
	global $wgg;

	if (isset($wgg['peers'][$peer_idx]) && is_array($wgg['peers'][$peer_idx])) {

		$peer = $wgg['peers'][$peer_idx];

		$allowedips = $peer['allowedips']['row'];
			
		if (is_array($allowedips) && count($allowedips) > 0) {

			$href = "vpn_wg_peers_edit.php?peer={$peer_idx}";

			$extras = count($allowedips) - 1;

			if ($extras > 0) {

				$data_html = "<table><thead><th>Subnet</th><th style='padding-left: 10px;'>Description</th></thead><tbody>";
	
				foreach ($allowedips as $address) {
	
					$data_html .= "<tr><td>{$address['address']}/{$address['mask']}</td><td style='padding-left: 10px;'>{$address['descr']}</td></tr>";
	
				}
	
				$data_html .= '</tbody></table>';
	
				$hint = "{$allowedips[0]['address']} (+{$extras})";
	
				$ret_html = "<a href=\"{$href}\" title=\"Allowed IPs\" data-toggle=\"popover\" data-trigger=\"hover focus\" data-content=\"{$data_html}\" data-html=\"true\">{$hint}</a>";
	
				return $ret_html;
	
			} else {
	
				$ret_html = "<a href=\"{$href}\" title=\"{$allowedips[0]['descr']}\">{$allowedips[0]['address']}/{$allowedips[0]['mask']}</a>";
	
				return $ret_html;
			
			}

		} else {

			return '(none)';
		}


	}

}

?>