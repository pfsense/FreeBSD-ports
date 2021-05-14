<?php
/*
 * wg.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald
 * Copyright (c) 2020 Ascord
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

// pfSense includes
require_once('config.inc');
require_once('gwlb.inc');
require_once('util.inc');

// WireGuard includes
require_once('wireguard/wg_api.inc');
require_once('wireguard/wg_install.inc');
require_once('wireguard/wg_validate.inc');

global $wgg;

/*
 * This toggles a given tunnel based on index
 */
function wg_toggle_tunnel($tunnel_name) {
	global $wgg;

	$input_errors = array();

	// Make sure we have the latest info
	wg_globals();

	// Get the config array index
	$tun_idx = wg_get_tunnel_array_index($tunnel_name);

	// Make sure we have a valid tunnel in the xml
	if (isset($wgg['tunnels'][$tun_idx])) {

		$tunnel = $wgg['tunnels'][$tun_idx];

		// We can't disable a tunnel assigned to a pfSense interface
		if (!is_wg_tunnel_assigned($tunnel['name'])) {

			// Now we toggle the tunnel
			$wgg['tunnels'][$tun_idx]['enabled'] = ($tunnel['enabled'] == 'yes') ? 'no' : 'yes';

			// Sync with configuration backend
			write_config("[{$wgg['pkg_names'][0]}] Tunnel {$tunnel['name']} toggled.");

			// Sync with WireGuard backend
			wg_resync_tunnel($tunnel['name']);

		} else {

			$action_txt = ($tunnel['enabled'] == 'yes') ? 'disable' : 'enable';

			$input_errors[] = "Cannot {$action_txt} a WireGuard tunnel while it is assigned as an interface.";

		}

	}

	return $input_errors;

}

/*
 * This toggles a given peer based on peerid
 */
function wg_toggle_peer($peer_id) {
	global $wgg;

	// Make sure we have the latest info
	wg_globals();

	// Make sure we have a valid peer
	if (isset($wgg['peers'][$peer_id])) {

		// Toggle the peer
		$wgg['peers'][$peer_id]['enabled'] = ($wgg['peers'][$peer_id]['enabled'] == 'yes') ? 'no' : 'yes';

		// Sync with configuration backend
		write_config("[{$wgg['pkg_names'][0]}] Peer {$peer_id} deleted.");

		// Sync with the WireGuard backend
		wg_resync_peer($wgg['peers'][$peer_id]);

	}

}

/*
 * This deletes a given peer based on index
 */
function wg_delete_peer($peer_id) {
	global $wgg;

	wg_globals();

	if (isset($wgg['peers'][$peer_id])) {

		// We need to save this because we are about to unset it
		$tmp_peer = $wgg['peers'][$peer_id];

		unset($wgg['peers'][$peer_id]);

		// Sync with configuration backend
		write_config("[{$wgg['pkg_names'][0]}] Peer {$peer_id} deleted.");

		// Sync with the WireGuard backend
		wg_resync_peer($tmp_peer);

	}

}

/*
 * This deletes a given tunnel based on name
 */
function wg_delete_tunnel($tunnel_name) {
	global $wgg;

	// Make sure we have the latest info
	wg_globals();

	unset($ifname, $conf_path);

	// Get the config array index
	$tun_idx = wg_get_tunnel_array_index($tunnel_name);

	// Make sure we have a valid tunnel
	if (isset($wgg['tunnels'][$tun_idx])) {

		$tunnel = $wgg['tunnels'][$tun_idx];

		// We can't delete assigned tunnels
		if (!is_wg_tunnel_assigned($tunnel['name'])) {

			// Delete the tunnel
			unset($wgg['tunnels'][$tun_idx]);

			// Sync with configuration backend
			write_config("[{$wgg['pkg_names'][0]}] Tunnel {$tunnel['name']} deleted.");

			// Recreate configuration files
			wg_create_config_files();

			// Destroy the interface
			pfSense_interface_destroy($tunnel['name']);
		
		} else {

			$input_errors[] = "Cannot delete a WireGuard tunnel assigned to a pfSense interface.";

		}

	}

	return $input_errors;

}

/*
 * This transforms a raw peer post consisting of repeatables 
 */
function wg_peer_allowedips($post) {

	// Convert the post into proper arrays
	$allowedips = wg_parse_post_repeatables($post, array('address', 'address_subnet', 'address_descr'));

	$ret_array = array();

	// Loop through each address entry
	foreach ($allowedips as $address) {

		// Remove any accidental whitespace
		$address['address'] = trim($address['address']);

		if (!empty($address['address'])) {

			$ret_array[] = array('address' => $address['address'], 'mask' => $address['address_subnet'], 'descr' => $address['address_descr']);

		}

	}

	return $ret_array;

}

/*
 * This transforms a raw tunnel post consisting of repeatables 
 */
function wg_tunnel_addresses($post) {

	// Convert the post into proper arrays
	$addresses = wg_parse_post_repeatables($post, array('address', 'address_subnet', 'address_descr'));

	$ret_array = array();

	// Loop through each address entry
	foreach ($addresses as $address) {

		// Remove any accidental whitespace
		$address['address'] = trim($address['address']);

		if (!empty($address['address'])) {

			$ret_array[] = array('address' => $address['address'], 'mask' => $address['address_subnet'], 'descr' => $address['address_descr']);

		}

	}

	return $ret_array;

}

/*
 * This converts a raw form post containing any repeatables like addresses, subnets, and descriptions and returns an actual array
 */
function wg_parse_post_repeatables($post, $fields = null) {

	if (is_array($fields)) {

		$ret_array = array();

		foreach ($fields as $field) {

			$x = 0;

			while (!is_null($post["{$field}{$x}"])) {

				$ret_array[$x][$field] = $post["{$field}{$x}"];

				$x++;

			}

		}

		return $ret_array;

	}

	return false;

}

/*
 * Takes a raw post for a peer, validates it, and saves it to the configuration system
 */
function wg_do_peer_post($post) {
	global $wgg;

	wg_globals();

	$pconfig = $input_errors = array();

	$peer_idx = $post['index'];

	$pconfig = $wgg['peers'][$peer_idx];

	wg_init_config_arr($pconfig, array('allowedips', 'row'));

	$pconfig['enabled'] 		= empty($post['enabled']) ? 'no' : 'yes';

	$pconfig['tun'] 		= $post['tun'];

	$pconfig['descr'] 		= $post['descr'];

	$pconfig['endpoint'] 		= $post['endpoint'];

	$pconfig['port'] 		= empty($post['port']) ? $wgg['default_port'] : $post['port'];

	$pconfig['persistentkeepalive']	= $post['persistentkeepalive'];

	$pconfig['publickey'] 		= $post['publickey'];
	
	$pconfig['presharedkey']	= $post['presharedkey'];

	$pconfig['allowedips']['row'] 	= wg_peer_allowedips($post);

	// Looks like we have a dynamic endpoint, so clear endpoint or port variables before saving
	if (isset($post['dynamic']) && $post['dynamic'] == 'yes') {

		unset($post['endpoint'], $post['port']);

	}

	$input_errors = wg_validate_peer_post($pconfig, $peer_idx);

	if (!$input_errors) {

		$wgg['peers'][$peer_idx] = $pconfig;

		// Sync with configuration backend
		write_config("[{$wgg['pkg_names'][0]}] Peer {$pconfig['descr']} updated.");

		// Sync with WireGuard backend
		wg_resync_peer($pconfig);

	}

	return array('input_errors' => $input_errors, 'pconfig' => $pconfig);

}

/*
 * Takes a raw post for a tunnel, validates it, and saves it to the configuration system
 */
function wg_do_tunnel_post($post) {
	global $wgg;

	wg_globals();

	$pconfig = $input_errors = array();

	$tun_idx = $post['index'];

	$pconfig = $wgg['tunnels'][$tun_idx];

	wg_init_config_arr($pconfig, array('addresses', 'row'));

	$pconfig['name']		= empty($pconfig['name']) ? next_wg_if() : $pconfig['name'];

	$pconfig['enabled'] 		= empty($post['enabled']) ? 'no' : 'yes';

	$pconfig['descr'] 		= $post['descr'];

	$pconfig['listenport'] 		= empty($post['listenport']) ? next_wg_port() : $post['listenport'];

	$pconfig['privatekey'] 		= $post['privatekey'];

	$pconfig['publickey'] 		= wg_gen_publickey($post['privatekey']);

	$pconfig['mtu'] 		= empty($post['mtu']) ? $wgg['default_mtu'] : $post['mtu'];

	$pconfig['addresses']['row'] 	= wg_tunnel_addresses($post);

	$input_errors 			= wg_validate_tunnel_post($pconfig, $tun_idx);

	if (!$input_errors) {

		$wgg['tunnels'][$tun_idx] = $pconfig;

		// Sync with configuration backend
		write_config("[{$wgg['pkg_names'][0]}] Tunnel {$pconfig['name']} updated.");

		// Sync with WireGuard backend
		wg_resync_tunnel($pconfig['name']);

	}

	return array('input_errors' => $input_errors, 'pconfig' => $pconfig);

}

function wg_resync_peer($peer) {
	global $wgg;

	// Create new configuration files
	wg_create_config_files();

	// Sync the configuration with the tunnel interface
	wg_interface_syncconf($peer['tun']);

}

function wg_resync_all() {
	global $g, $wgg;

	if ($g['wireguard_installing']) {

		unset($g['wireguard_installing']);

		return;
		
	}

	wg_create_config_files();

	foreach ($wgg['tunnels'] as $tunnel) {
		
		wg_resync_tunnel($tunnel['name'], true);
	
	}

	wg_unbound_acl_update();

}


// This resyncs a specific tunnel interface
function wg_resync_tunnel($tunnel_name, $quick = false) {
	global $wgg;

	wg_globals();

	$tun_idx = wg_get_tunnel_array_index($tunnel_name);

	if (!$quick) {

		// Create new configuration files
		wg_create_config_files();

	}

	if (isset($wgg['tunnels'][$tun_idx])) {

		$tunnel = $wgg['tunnels'][$tun_idx];

		// Create the tunnel interface if it does not yet exist
		wg_interface_create($tunnel['name']);

		// Add the tunnel interface to the WireGuard interface group
		wg_interface_group_add($tunnel['name']);

		// Update the addresses on the tunnel interface
		wg_interface_update_addresses($tunnel['name']);

		// Toggle the interface accordingly instead of tearing it down completely
		if (isset($tunnel['enabled']) && $tunnel['enabled'] == 'yes') {

			wg_ifconfig_up_interface($tunnel['name']);

		} else {

			wg_ifconfig_down_interface($tunnel['name']);

		}

		// Update Unbound ACL
		wg_unbound_acl_update();

		// Sync the configuration with the tunnel interface
		wg_interface_syncconf($tunnel['name']);

	}

}

function wg_get_enabled_tunnels() {
	global $wgg;

	wg_globals();

	$ret_tunnels = array();

	foreach ($wgg['tunnels'] as $tunnel) {

		if (isset($tunnel['enabled']) && $tunnel['enabled'] == 'yes') {

			$ret_tunnels[] = $tunnel;

		}

	}

	return $ret_tunnels;

}


/*
 * (Re)creates configuration files based on the current configuration
 */
function wg_create_config_files($clean = true) {
	global $wgg;

	wg_globals();

	// Check if there are any configurations to write to disk
	if (is_array($wgg['tunnels'])) {

		// Create configuration path if it is missing
		if (!file_exists($wgg['conf_path'])) {

			mkdir($wgg['conf_path'], 0700, true);

		} else {

			chmod($wgg['conf_path'], 0700);

		}

		// We almost always want to just overwrite the old configurations
		if ($clean) {

			wg_delete_config_files();

		}

		$tunnels = wg_get_enabled_tunnels();

		foreach ($tunnels as $tunnel) {

			make_wg_conf($tunnel);

		}

	}

}

/* 
 * Remove all wg config files from any potential conf directory
 */
function wg_delete_config_files() {
	global $wgg;

	// Loop through each potential conf path and delete all .conf files
	foreach ($wgg['conf_paths_to_clean'] as $confpath) {

		unlink_if_exists("{$confpath}/*.conf");

	}

}

/* 
 * Removes any configuration xml paths as defined by $wgg['xml_conf_tags']
 */
function wg_remove_config_settings() {
	global $config, $wgg;

	// Loop through each potential conf path and unset
	foreach ($wgg['xml_paths_to_clean'] as $confpath) {

		array_unset_value($config, $confpath);

	}

	// Now write out the new config to disk
	write_config("[{$wgg['pkg_names'][0]}] Package configuration removed");

}

/*
 * Writes a WireGuard configuration file for a given tunnel to the configuration path
 */
function make_wg_conf($tunnel) {
	global $wgg;

	$txt = "# This WireGuard config file has been created automatically. " .
	    "Do not edit!\n";

	$txt .= "# Description: {$tunnel['descr']}\n\n";

	// Process Interfaces section
	$txt .= "[Interface]\n";

	if (!empty($tunnel['privatekey'])) {
		$txt .= "PrivateKey = {$tunnel['privatekey']}\n";
	}

	if (!empty($tunnel['listenport'])) {
		$txt .= "ListenPort = {$tunnel['listenport']}\n";
	}

	$txt .= "\n";

	// Process peers section
	$peers = wg_get_tunnel_peers($tunnel['name']);

	if (is_array($peers) && count($peers) > 0) {

		foreach ($peers as $peer) {

			if (isset($peer['enabled']) && $peer['enabled'] == 'yes') {

				$txt .= "# Peer: {$peer['descr']}\n";

				$txt .= "[Peer]\n";

				if (!empty($peer['publickey'])) {

					$txt .= "PublicKey = {$peer['publickey']}\n";

				}

				if (!empty($peer['presharedkey'])) {

					$txt .= "PresharedKey = {$peer['presharedkey']}\n";
				}

				
				if (is_array($peer['allowedips']['row'])) {

					$allowedips = implode(',', array_map(fn($x) => "{$x['address']}/{$x['mask']}", $peer['allowedips']['row']));

					$txt .= "AllowedIPs = {$allowedips}\n";

				}

				if (!empty($peer['endpoint'])) {
			
					$peer['endpoint'] = is_ipaddrv6($peer['endpoint']) ? "[{$peer['endpoint']}]" : $peer['endpoint'];

					$peer['port'] = empty($peer['port']) ? $wgg['default_port'] : $peer['port'];

					$txt .= "Endpoint = {$peer['endpoint']}:{$peer['port']}\n";

				}

				if (!empty($peer['persistentkeepalive'])) {

					$txt .= "PersistentKeepalive = {$peer['persistentkeepalive']}\n";
				}

				$txt .= "\n";

			}
			
		}

	}

	$conf_path = "{$wgg['conf_path']}/{$tunnel['name']}.conf";

	$old_mask = umask(077);

	file_put_contents($conf_path, $txt);

	umask($old_mask);

}

?>