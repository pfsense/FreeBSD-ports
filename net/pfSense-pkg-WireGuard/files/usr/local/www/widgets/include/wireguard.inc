<?php
/*
 * wireguard.inc
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

$wireguard_title = gettext('WireGuard');
$wireguard_title_link = '/wg/status_wireguard.php';

function wg_do_widget_settings_post($post, $user_settings) {
	global $wgg;

	$pconfig = array();

	if (isset($post['widgetkey'])) {

		$widgetkey 			= $post['widgetkey'];

		$refresh_interval 		= $post["{$widgetkey}_refresh_interval"];

		$activity_threshold		= $post["{$widgetkey}_activity_threshold"];

		$pconfig['refresh_interval'] 	= $wgg['default_widget_refresh_interval'];
		
		$pconfig['activity_threshold']	= $wgg['default_widget_activity_threshold'];

		// Check if the posted refresh interval is valid, if so set it...
		if (isset($refresh_interval)
		    && is_numericint($refresh_interval) && in_array($refresh_interval, range(0, 10))) {

			$pconfig['refresh_interval'] = $refresh_interval;

		}

		// Check if the posted activity threshold is valid, if so set it...
		if (isset($activity_threshold)
		    && is_numericint($activity_threshold) && ($activity_threshold >= 0)) {

			$pconfig['activity_threshold'] = $activity_threshold;

		}

		// Sync with the configuration backend...
		$user_settings['widgets'][$widgetkey] = $pconfig;

		// Log entry for write_config()
		$message = sprintf(gettext('Updated %s widget settings via dashboard.'), $wgg['pkg_name']);

		// Save the widget settings...
		save_widget_settings($_SESSION['Username'], $user_settings['widgets'], $message);
	
	}

}

function wg_compose_widget_body($widgetkey, $wireguard_activity_threshold) {
	global $wgg;

	/*
	 * A simple hack to use functions inside heredoc strings
	 * This also allows translation scripts to pick up strings normally by scraping gettext() calls
	 * 
	 * Example: {$s(gettext('my text'))}
	 * 
	 */

	$s = fn($x) => $x;

	$a_devices = wg_get_status();

	$current_time = (new DateTimeImmutable)->getTimestamp();

	if (empty($wgg['tunnels'])) {

		$data_html = 	<<<"ROW"
				<tr>
					<td colspan="9">{$s(gettext('No WireGuard tunnels have been configured.'))}</td>
				</tr>

				ROW;

	} elseif (empty($a_devices)) {

		$data_html =	<<<"ROW"
				<tr>
					<td colspan="9">{$s(gettext('The WireGuard service is not running.'))}</td>
				</tr>

				ROW;

	} else {

		$data_html = null;

		foreach ($a_devices as $device_name => $device) {

			$active_peers = array_filter($device['peers'], function ($x) use ($current_time, $wireguard_activity_threshold) {

				/* 
				 * We need to filter the list of peers for those recently active
				 * or 0 to disable and show all peers...
				 */

				return (($wireguard_activity_threshold == 0) || (($current_time - $x['latest_handshake']) <= $wireguard_activity_threshold));

			});

			$data_html .= 	<<<"ROW"
					<tr>
						<td>
							{$s(wg_interface_status_icon($device['status']))}
							<a href="wg/vpn_wg_tunnels_edit.php?tun={$s(htmlspecialchars($device_name))}">{$s(htmlspecialchars($device_name))}</a>
						</td>
						<td>{$s(htmlspecialchars(wg_truncate_pretty($device['config']['descr'], 16)))}</td>
						<td>{$s(htmlspecialchars(count($active_peers)))}</td>
						<td>{$s(htmlspecialchars($device['listen_port']))}</td>
						<td>{$s(htmlspecialchars(format_bytes($device['transfer_rx'])))}</td>
						<td>{$s(htmlspecialchars(format_bytes($device['transfer_tx'])))}</td>
					</tr>

					ROW;

		}

	}

	return $data_html;

}

?>