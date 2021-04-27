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
require_once('globals.inc');
require_once('interfaces.inc');
require_once('pfsense-utils.inc');
require_once('pkg-utils.inc');
require_once('service-utils.inc');
require_once('system.inc');
require_once('util.inc');

// WireGuard includes
require_once('wireguard/wg_api.inc');
require_once('wireguard/wg_globals.inc');
require_once('wireguard/wg_install.inc');
require_once('wireguard/wg_validate.inc');

global $wgg;

// Setup WireGuard tunnel...this needs to be reworked at some point
function wg_configure_if($tunnel, $conf_hard = true, $verbose = false) {
	global $config, $wgg;

	// Should we perform a hard reconfiguration
	if ($conf_hard) {

		// Destroy the interface first
		wg_destroy_if($tunnel, $verbose);

		// Then attempt to bring up interface using wg-quick(8)
		exec_wg_quick_action($tunnel, "up", $verbose);

	}

	// Now let's always soft configure the interface
	wg_syncconf($tunnel);

	$wg_ifname = escapeshellarg($tunnel['name']);
	$group_ifname =escapeshellarg($wgg['ifgroupentry']['ifname']);

	// Add the interface to the interface group (i.e. WireGuard)
	mwexec("{$wgg['ifconfig']} {$wg_ifname} group {$group_ifname}");

	// Get the tunnel interface friendly name if assigned
	$if_fname = convert_real_interface_to_friendly_interface_name($tunnel['name']);

	if(isset($if_fname)) {

		interface_reconfigure($if_fname);

		system_routing_configure($if_fname);
	
	}

}


// Remove WireGuard tunnel
function wg_destroy_if($tunnel, $verbose = false) {

	// Execute the wg-quick(8) way of tearing down an interface
	exec_wg_quick_action($tunnel, "down", $verbose);

	// Need to transform out of tunnel structure because pfSense_interface_destroy expects an ifname
	if (is_array($tunnel) && isset($tunnel['name'])) {

		$tunnel = $tunnel['name'];

	}

	// Now execute the pfSense way of tearing down an interface
	pfSense_interface_destroy($tunnel);

}

function wg_toggle_tunnel($tun_id) {
	global $wgg;

	$input_errors = array();

	// Make sure we have the latest info
	wg_globals();

	// Make sure we have a valid tunnel
	if (isset($wgg['tunnels'][$tun_id])) {

		// We can't disable tunnels if they are assigned
		if (!is_wg_tunnel_assigned($wgg['tunnels'][$tun_id]['name'])) {

			// Now we toggle the tunnel
			$wgg['tunnels'][$tun_id]['enabled'] = ($wgg['tunnels'][$tun_id]['enabled'] == 'yes') ? 'no' : 'yes';

			// Write the configuration to disk
			write_config("[{$wgg['pkg_names'][0]}] Tunnel {$wgg['tunnels'][$tun_id]['name']} toggled.");
		
			// Regenerate conf files
			wg_globals();
			wg_create_config_files();
			wg_ifgroup_install();

			// This logic needs to be rewritten at some point
			$tunnel = $wgg['tunnels'][$tun_id];

			if (isset($tunnel['enabled']) && $tunnel['enabled'] == 'yes') {

				$conf_hard = (!is_wg_tunnel_assigned($tunnel) || !does_interface_exist($tunnel['name']));

				wg_configure_if($tunnel, $conf_hard);

			} else {

				wg_destroy_if($tunnel);

			}
		
		} else {

			$action_txt = ($wgg['tunnels'][$tun_id]['enabled'] == 'yes') ? 'disable' : 'enable';

			$input_errors[] = "Cannot {$action_txt} a WireGuard tunnel while it is assigned as an interface.";

		}

	}

	return $input_errors;

}

function wg_format_endpoint($peer, $endpoint_key = 'endpoint', $port_key = 'port') {

	if (!empty($peer[$endpoint_key])) {

		$ret_str = "{$peer[$endpoint_key]} : {$peer[$port_key]}";

	} else {

		$ret_str = "Dynamic";

	}

	return $ret_str;

}

function wg_toggle_peer($peer_id) {
	global $config, $wgg;

	// Make sure we have the latest info
	wg_globals();

	// Make sure we have a valid peer
	if (isset($wgg['peers'][$peer_id])) {

		$wgg['peers'][$peer_id]['enabled'] = ($wgg['peers'][$peer_id]['enabled'] == 'yes') ? 'no' : 'yes';

		write_config("[{$wgg['pkg_names'][0]}] Peer {$peer_id} toggled.");

		wg_resync();

	}

}

function wg_delete_peer($peer_id) {
	global $config, $wgg;

	wg_globals();

	if (isset($wgg['peers'][$peer_id])) {

		unset($wgg['peers'][$peer_id]);

		write_config("[{$wgg['pkg_names'][0]}] Peer {$peer_id} deleted.");

		wg_resync();

	}

}

function wg_delete_tunnel($tun_id) {
	global $config, $wgg;

	wg_globals();

	if (isset($wgg['tunnels'][$tun_id])) {

		// We can't disable tunnels if they are assigned
		if (!is_wg_tunnel_assigned($wgg['tunnels'][$tun_id]['name'])) {

			$tmp_tunnel = $wgg['tunnels'][$tun_id];

			unset($ifname);

			unset($conf_path);

			if (isset($wgg['tunnels'][$tun_id]['name'])) {

				$ifname = $wgg['tunnels'][$tun_id]['name'];

			}
		
			if (isset($ifname)) {

				$conf_path = "{$wgg['conf_path']}/{$ifname}.conf";

			}

			// Delete the tunnel configuration entry
			unset($wgg['tunnels'][$tun_id]);

			write_config("[{$wgg['pkg_names'][0]}] Tunnel {$tmp_tunnel['name']} deleted.");

			// Delete the wg?.conf file
			if (isset($conf_path) && is_file($conf_path)) {

				unlink($conf_path);

			}

			// Destroy the deleted tunnel
			wg_destroy_if($ifname);
		
		} else {

			$input_errors[] = "Cannot delete a WireGuard tunnel while it is assigned as an interface.";

		}

	}

	return $input_errors;

}

// This returns an array of peer structs for a given tunnel
function wg_get_tunnel_peers($tunnel) {
	global $config, $wgg;

	$a_ret = array();

	// Assume we have a tunnel name first
	$wg_ifname = $tunnel;

	// Looks like we have a tunnel structure
	if (is_array($tunnel) && isset($tunnel['name'])) {

		// Transform to an interface name
		$wg_ifname = $tunnel['name'];

	}

	// Look through array of peers for matching tunnel name
	foreach ($wgg['peers'] as $peer_id => $peer) {

		if ($peer['tun'] == $wg_ifname) {

			// We need the array index for future manipulations
			$peer['index'] = $peer_id;

			$a_ret[] = $peer;

		}

	}

	// Return the list of filtered peers
	return $a_ret;

}

function build_tun_list() {
	global $config, $wgg;

	$a_ret = array();

	$a_ret['unassigned'] = 'Unassigned';

	foreach ($wgg['tunnels'] as $tunnel) {

		$a_ret[$tunnel['name']] = "{$tunnel['name']} ({$tunnel['descr']})";	

	}

	return $a_ret;

}

function wg_allowed_ips_filtered($allowed_ips) {

	$a_all = array('0.0.0.0/0', '::/0');

	$all_ipv4 = $all_ipv6 = false;

	$a_ips = explode(',', $allowed_ips);

	$a_ret = array();

	foreach ($a_ips as $index => $ip) {

		if (!in_array($ip, $a_all)) {

			$a_ret[] = $ip;

		} elseif (in_array($ip, $a_all)) {

			$all_ipv4 = is_v4($ip) ? true : $all_ipv4;

			$all_ipv6 = is_v6($ip) ? true : $all_ipv6;

		}

	}

	// Need to make sure we return at least one for the UI
	if (count($a_ret) == 0) {

		$a_ret[] = null;

	}

	return array('allowedips' => $a_ret, 'all_ipv4' => $all_ipv4, 'all_ipv6' => $all_ipv6);

}

// This converts a form post array containing addresses and subnets and returns an CIDR-formatted array
function wg_parse_post_addresses($post, $default = true) {

	$a_ret = array();

	$all_ipv4 = ($default && isset($post['all_ipv4']) && $post['all_ipv4'] == 'yes');

	$all_ipv6 = ($default && isset($post['all_ipv6']) && $post['all_ipv6'] == 'yes');

	if ($all_ipv4) { 
		
		$a_ret[] = '0.0.0.0/0'; 
	
	}

	if ($all_ipv6) { 
		
		$a_ret[] = '::/0'; 
	
	}

	$x = 0;

	while ($post["address{$x}"] && $post["address_subnet{$x}"]) {

		$s_address = "{$post["address{$x}"]}/{$post["address_subnet{$x}"]}";

		if (!$all_ipv4 && is_v4($s_address)) {

			$a_ret[] = $s_address;

		}
		
		if (!$all_ipv6 && is_v6($s_address)) {

			$a_ret[] = $s_address;
		
		}

		$x++;

	}
	
	return $a_ret;

}

function wg_do_peer_post($post) {
	global $config, $wgg;

	wg_globals();

	$input_errors = array();

	$index = $post['index'];

	$pconfig = $wgg['peers'][$index];

	// Looks like we have a dynamic endpoint, clear endpoint or port variables before saving
	if (isset($post['dynamic']) && $post['dynamic'] == 'yes') {

		unset($post['endpoint']);

		unset($post['port']);

	}

	$a_allowed_ips = wg_parse_post_addresses($post);

	$pconfig['enabled'] 		= empty($post['enabled']) ? 'no' : 'yes';
	$pconfig['tun'] 		= $post['tun'];
	$pconfig['descr'] 		= $post['descr'];
	$pconfig['endpoint'] 		= $post['endpoint'];
	$pconfig['port'] 		= $post['port'];
	$pconfig['persistentkeepalive']	= $post['persistentkeepalive'];
	$pconfig['publickey'] 		= $post['publickey'];
	$pconfig['presharedkey']	= $post['presharedkey'];
	$pconfig['allowedips'] 		= implode(',', $a_allowed_ips);

	$input_errors = wg_validate_peer_post($pconfig);

	if (!$input_errors) {

		$wgg['peers'][$index] = $pconfig;

		write_config("[{$wgg['pkg_names'][0]}] Peer {$pconfig['descr']} updated.");

	}

	return(array('input_errors' => $input_errors, 'pconfig' => $pconfig));

}

// Write new tunnel values to the configuration system
function wg_do_post($post) {
	global $config, $wgg;

	wg_globals();

	$input_errors = array();

	$index = $post['index'];

	$pconfig = $wgg['tunnels'][$index];

	if (empty($pconfig['name'])) {

		$pconfig['name'] = next_wg_if();

	}

	if (empty($pconfig['mtu'])) {

		$pconfig['mtu'] = $wgg['default_mtu'];

	}

	$pconfig['enabled'] = empty($post['enabled']) ? 'no' : 'yes';

	$pconfig['descr'] = $post['descr'];

	// Tunnel section
	$pconfig['addresses'] = implode(',', wg_parse_post_addresses($post));
	$pconfig['listenport'] = $post['listenport'];
	$pconfig['privatekey'] = $post['privatekey'];
	$pconfig['publickey'] = wg_gen_publickey($post['privatekey']);

	$input_errors = wg_validate_post($pconfig);

	if (!$input_errors) {

		$wgg['tunnels'][$index] = $pconfig;

		write_config("[{$wgg['pkg_names'][0]}] Tunnel {$pconfig['name']} updated.");

	}

	return(array('input_errors' => $input_errors, 'pconfig' => $pconfig));

}



function wg_resync($verbose = false) {
	global $config, $g, $wgg;

	// Read latest settings
	wg_globals();

	// Write out configuration files to disk
	wg_create_config_files();

	// Attempt to reinstall the interface group to keep things clean
	wg_ifgroup_install();

	if ($g['wireguard_installing']) {

		update_status("\n  Configuring WireGuard tunnels...");

	}

	if (is_array($wgg['tunnels'])) {

		foreach ($wgg['tunnels'] as $tunnel) {

			if (isset($tunnel['enabled']) && $tunnel['enabled'] == 'yes') {

				// Determine if we should configure hard or soft
				$conf_hard = (!is_wg_tunnel_assigned($tunnel) || !does_interface_exist($tunnel['name']));

				// Attempt to create the WireGuard tunnel
				wg_configure_if($tunnel, $conf_hard, $verbose);

			} else {

				// Attempt to destroy the WireGuard tunnel
				wg_destroy_if($tunnel, $verbose);

			}

		}

	}

	if ($g['wireguard_installing']) {

		update_status(" done.\n");

		// We are no longer installing
		unset($g['wireguard_installing']);

	}

}

function wg_create_config_files($clean = true) {
	global $config, $wgg;

	wg_globals();

	// We've got some tunnels to configure
	if (is_array($wgg['tunnels'])) {

		// Create configuration path if it is missing
		if (!file_exists($wgg['conf_path'])) {

			mkdir($wgg['conf_path'], 0700, true);

		} else {

			chmod($wgg['conf_path'], 0700);

		}

		// We almost always want to just overwrite the configurations
		if ($clean) {

			wg_delete_config_files();

		}

		foreach ($wgg['tunnels'] as $tunnel) {

			if (!empty($tunnel['enabled']) && $tunnel['enabled'] == 'yes') {

				make_wg_conf($tunnel);

			}

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

// Write each file
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

	if (!empty($tunnel['addresses'])) {
		$txt .= "Address = {$tunnel['addresses']}\n";
	}

	if (!empty($tunnel['mtu'])) {
		$txt .= "MTU = {$tunnel['mtu']}\n";
	}

	// We need to address this at some point
	$txt .= "Table = off\n";

	$txt .= "\n";

	// Process peers section

	$peers = wg_get_tunnel_peers($tunnel);

	if (is_array($peers) && count($peers) > 0) {

		foreach ($peers as $peer) {

			if (isset($peer['enabled']) && $peer['enabled'] == 'yes') {

				$txt .= "# Peer: {$peer['descr']}\n";

				$txt .= "[Peer]\n";

				if (!empty($peer['publickey'])) {

					$txt .= "PublicKey = {$peer['publickey']}\n";

				}

				if (!empty($peer['endpoint'])) {

					$txt .= "EndPoint = ";

					$txt .= is_ipaddrv6($peer['endpoint']) ? "[{$peer['endpoint']}]" : $peer['endpoint'];

					$txt .= ":" . ((empty($peer['port'])) ? '51820' : $peer['port']) . "\n";

				}

				if (!empty($peer['allowedips'])) {

					$txt .= "AllowedIPs = {$peer['allowedips']}\n";

				}

				if (!empty($peer['persistentkeepalive'])) {

					$txt .= "PersistentKeepalive = {$peer['persistentkeepalive']}\n";
				}

				if (!empty($peer['presharedkey'])) {

					$txt .= "PresharedKey = {$peer['presharedkey']}\n";
				}

				$txt .= "\n";

			}
			
		}

	}

	$conf_path = "{$wgg['conf_path']}/{$tunnel['name']}.conf";

	file_put_contents($conf_path, $txt);

	chmod($conf_path, 0600);

}

?>