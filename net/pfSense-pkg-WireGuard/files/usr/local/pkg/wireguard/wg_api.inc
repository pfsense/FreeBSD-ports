<?php
/*
 * wg_api.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/theonemcdonald)
 * Copyright (c) 2021 Vajonam
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
require_once('interfaces.inc');		// interface_configure
require_once('util.inc');		// get_configured_interface_list_by_realif, mwexec
require_once('services.inc');
require_once('service-utils.inc');

// WireGuard includes
require_once('wireguard/wg_globals.inc');
require_once('wireguard/wg_install.inc');

// Returns a massive associative array of current wg status
function wg_status() {
	global $wgg;

	$tunnel_keys = array('private_key', 'public_key', 'listen_port', 'fwmark');

	$peer_keys = array('public_key', 'preshared_key', 'endpoint', 'allowed_ips', 'latest_handshake', 'transfer_rx', 'transfer_tx', 'persistent_keepalive');
	
	$ret_array = $wg_output_rows = array();
	
	exec("{$wgg['wg']} show all dump", $wg_output_rows, $ret_code);

	if ($ret_code == 0) {
	
		foreach ($wg_output_rows as $wg_index => $wg_row) {

			$tmp_tunnel = $tmp_peer = array();

			$a_device = explode("\t", $wg_row);

			$current_device = $a_device[0];

			if (strcmp($current_device, $last_device)) {

				foreach ($tunnel_keys as $index => $key) {

					$tmp_tunnel[$key] = $a_device[$index + 1];

				}

				// Gets some extra information about tunnels not returned by `wg show all dump`
				$tunnel_if_stats 		= pfSense_get_interface_stats($current_device);

				$tun_idx			= wg_get_tunnel_array_index($current_device);

				$tmp_tunnel['status'] 		= wg_interface_status($current_device) ? 'up' : 'down';

				$tmp_tunnel['transfer_rx'] 	= $tunnel_if_stats['inbytes'];

				$tmp_tunnel['transfer_tx'] 	= $tunnel_if_stats['outbytes'];

				$tmp_tunnel['inpkts']		= $tunnel_if_stats['inpkts'];

				$tmp_tunnel['outpkts']		= $tunnel_if_stats['outpkts'];

				$tmp_tunnel['mtu']		= $tunnel_if_stats['mtu'];

				$tmp_tunnel['config']		= $wgg['tunnels'][$tun_idx];

				// Add the tunnel to the array
				$ret_array[$current_device] = $tmp_tunnel;
				
				// Now provision an empty peer array
				$ret_array[$current_device]['peers'] = array();
				
				$last_device = $a_device[0];

			} else {

				foreach ($peer_keys as $index => $key) {

					$tmp_peer[$key] = $a_device[$index + 1];

				}

				// Gets some extra information about peers not returned by `wg show all dump`
				$peer_id = wg_get_peer_id($tmp_peer['public_key'], $last_device);

				$tmp_peer['config']		= $wgg['peers'][$peer_id];
				
				// Add the peer to the array
				$ret_array[$last_device]['peers'][$a_device[1]] = $tmp_peer;
				
			}


		}

	}

	// Consumers of this function always expect an array type
	return $ret_array;

}

// Wrapper for wg_status to output json
function wg_status_json($pretty = false) {

	$a_json = wg_status();

	return json_encode($a_json, ($pretty ? JSON_PRETTY_PRINT : null) | JSON_UNESCAPED_SLASHES);

}

// Returns a peer array index by public key and tunnel name
function wg_get_peer_id($public_key, $tunnel_name) {
	global $wgg;

	if (isset($wgg['peers']) && is_array($wgg['peers'])) {

		foreach ($wgg['peers'] as $peer_id => $peer){

			if ($public_key == $peer['publickey'] && $tunnel_name = $peer['tun']) {

				return $peer_id;

			}

		}

	}

	return -1;

}

function wg_get_address_family($address) {

	if (is_v4($address)) {

		return 'inet';

	} elseif (is_v6($address)) {

		return 'inet6';

	} else {

		return false;

	}

}

function wg_ifconfig_del_address($wg_ifname, $masked_address, $ret_posix_style = true) {
	global $wgg;

	// Assume family validation will fail...
	$ret_val = 1;

	// Gets the ifconfig address family while also validating the input at the same time
	if ($family = wg_get_address_family($masked_address)) {

		list($address, $address_subnet) = explode('/', $masked_address);

		wg_escapeshellarg($wg_ifname, $address);

		$ret_val = mwexec("{$wgg['ifconfig']} {$wg_ifname} {$family} {$address} delete");

	}

	return ($ret_posix_style ? ($ret_val <> 0) : ($ret_val == 0));

}

function wg_ifconfig_set_address($wg_ifname, $masked_address, $ret_posix_style = true) {
	global $wgg;

	// Assume family validation will fail...
	$ret_val = 1;

	// Gets the ifconfig address family while also validating the input at the same time
	if ($family = wg_get_address_family($masked_address)) {

		wg_escapeshellarg($wg_ifname, $masked_address);

		$ret_val = mwexec("{$wgg['ifconfig']} {$wg_ifname} {$family} {$masked_address} add");

	}

	return ($ret_posix_style ? ($ret_val <> 0) : ($ret_val == 0));

}

function wg_ifconfig_down_interface($wg_ifname, $ret_posix_style = true) {
	global $wgg;

	wg_escapeshellarg($wg_ifname);

	$ret_val = mwexec("{$wgg['ifconfig']} {$wg_ifname} down");

	return ($ret_posix_style ? ($ret_val <> 0) : ($ret_val == 0));

}

function wg_ifconfig_up_interface($wg_ifname, $ret_posix_style = true) {
	global $wgg;

	wg_escapeshellarg($wg_ifname);

	$ret_val = mwexec("{$wgg['ifconfig']} {$wg_ifname} up");

	return ($ret_posix_style ? ($ret_val <> 0) : ($ret_val == 0));

}

/*
 * This updates the addresses of the specified interface without tearing it down
 */
function wg_interface_update_addresses($wg_ifname, $ret_posix_style = true) {
	global $wgg;

	// Assume everything will be okay...
	$ret_val = 0;

	$tun_idx = wg_get_tunnel_array_index($wg_ifname);

	$tunnel = $wgg['tunnels'][$tun_idx];

	wg_init_config_arr($tunnel, array('addresses', 'row'));

	// Assigned tunnel interfaces are handled by pfSense and should be ignored here
	if (!is_wg_tunnel_assigned($wg_ifname)) {

		// Get an array of the current addresses assigned to the tunnel interface
		$current = pfSense_getall_interface_addresses($wg_ifname);

		// Get an array of the addresses to be assigned to the interface
		$desired = array_map(fn(&$x) => "{$x['address']}/{$x['mask']}", $tunnel['addresses']['row']);

		// Determine the addresses to remove
		$addresses_to_remove = array_diff($current, array_intersect($current, $desired));

		// Now remove them
		foreach ($addresses_to_remove as $address) {

			$ret_val |= wg_ifconfig_del_address($wg_ifname, $address);

		}

		// Determine the addresses to add
		$addresses_to_add = array_diff($desired, array_intersect($current, $desired));

		// Now add them
		foreach ($addresses_to_add as $address) {

			$ret_val |= wg_ifconfig_set_address($wg_ifname, $address);

		}

	// Need to let pfSense handle the assigned interfaces
	} elseif (is_wg_tunnel_assigned($wg_ifname)) {

		if ($pfsense_if_name = wg_get_pfsense_interface_info($wg_ifname)) {

			interface_reconfigure($pfsense_if_name['name']);

		}

	}

	return ($ret_posix_style ? ($ret_val <> 0) : ($ret_val == 0));

}

/* 
 * Translates WireGuard interface names to pfSense interface names and descriptions
 */
function wg_get_pfsense_interface_info($tunnel_name) {

	$ret_array = array();

	$iflist = get_configured_interface_list_by_realif(true);

	$ifdescr = get_configured_interface_with_descr(true);

	if (isset($iflist[$tunnel_name])) {

		$tmp_name = $iflist[$tunnel_name];

		$ret_array['name'] 	= $tmp_name;

		$ret_array['descr']	=  $ifdescr[$tmp_name];

		return $ret_array;

	}

	// Consumers of this function always expect an array type
	return $ret_array;

}

function wg_interface_syncconf($wg_ifname, $ret_posix_style = true) {
	global $wgg;

	$ret_val = 1;

	$wg_conf_path = "{$wgg['conf_path']}/{$wg_ifname}.conf";

	if (file_exists($wg_conf_path)) {

		wg_escapeshellarg($wg_ifname, $wg_conf_path);

		$ret_val = mwexec("{$wgg['wg']} syncconf {$wg_ifname} {$wg_conf_path}");

	}

	return ($ret_posix_style ? ($ret_val <> 0) : ($ret_val == 0));

}

/*
 * This creates a WireGuard interface of a specified name
 */
function wg_interface_create($wg_ifname, $ret_posix_style = true) {
	global $wgg;

	wg_escapeshellarg($wg_ifname);

	// First check if the interface already exists
	$ret_val = mwexec("{$wgg['ifconfig']} {$wg_ifname}");

	if ($ret_val <> 0) {
		
		// Create the interface with the specified name
		$ret_val = mwexec("{$wgg['ifconfig']} wg create name {$wg_ifname}");

	}
	
	return ($ret_posix_style ? ($ret_val <> 0) : ($ret_val == 0));

}

/*
 * This destroys a WireGuard interface of a specified name
 */
function wg_interface_destroy($wg_ifname, $ret_posix_style = true) {
	global $wgg;

	wg_escapeshellarg($wg_ifname);

	$ret_val = mwexec("{$wgg['ifconfig']} {$wg_ifname} destroy");

	return ($ret_posix_style ? ($ret_val <> 0) : ($ret_val == 0));

}

/*
 * Returns an array of the current WireGuard tunnel interfaces running as per ifconfig
 */
function wg_get_real_ifs() {
	global $wgg;

	$ret_array = array();

	exec("{$wgg['ifconfig']} -g wg", $ret_array, $ret_code);
	
	return ($ret_code == 0) ? $ret_array : array();

}

/*
 * This adds a WireGuard interface to the WireGuard interface group
 */
function wg_interface_group_add($wg_ifname, $ret_posix_style = true) {
	global $wgg;

	$wg_ifgroup = $wgg['ifgroupentry']['ifname'];

	wg_escapeshellarg($wg_ifname, $wg_ifgroup);

	// First check if the interface already exists
	$ret_val = mwexec("{$wgg['ifconfig']} {$wg_ifname}");

	if ($ret_val == 0) {

		// Add interface to interface group
		$ret_val = mwexec("{$wgg['ifconfig']} {$wg_ifname} group {$wg_ifgroup}");

	}
		
	return ($ret_posix_style ? ($ret_val <> 0) : ($ret_val == 0));

}

function wg_interface_status($wg_ifname) {

	$if_flags = wg_ifconfig_interface_flags($wg_ifname);

	return in_array("UP", $if_flags);

}

function wg_ifconfig_interface_flags($wg_ifname) {
	global $wgg;

	wg_escapeshellarg($wg_ifname);

	$ret_array = $output = array();

	exec("{$wgg['ifconfig']} {$wg_ifname}", $output, $ret_code);

	if ($ret_code == 0) {

		if (preg_match("/flags=.*<(?P<flags>.*)>/", $output[0], $matches)) {

			$ret_array = explode(',', $matches['flags']);

		}

	}

	// Consumers of this function always expect an array type
	return $ret_array;

}

// Get various package infos and return an associative array
function wg_pkg_info() {
	global $wgg;

	$ret_array = array();

	$a_fields = array('name'=> '%n', 'version' => '%v', 'comment' => '%c');

	$a_return_keys = array_values(array_flip($a_fields));

	$field_string = implode("\t", $a_fields);

	$a_packages = $wgg['depends_names'];

	// Each package needs to be escaped individually before imploding
	wg_escapeshellarg($field_string, $a_packages);
	
	$packages_string = implode(" ", $a_packages);

	exec("{$wgg['pkg']} query {$field_string} {$packages_string}", $packages, $ret_code);

	if ($ret_code == 0) {

		foreach ($packages as $pkg_index => $package) {

			$fields = explode("\t", $package);

			foreach ($fields as $field_index => $field) {

				$ret_array[$pkg_index][$a_return_keys[$field_index]] = $field;


			}

		}

	}

	// Consumers of this function always expect an array type
	return $ret_array;

}

// Generate private key
function wg_gen_keypair($json = false) {
	global $wgg;

	$privkey = exec("{$wgg['wg']} genkey");

	$res = wg_gen_publickey($privkey, $json);

	return $res;

}

// Compose the public key from a provided private key
function wg_gen_publickey($privkey, $json = false) {
	global $wgg;

	// We want to pass the unmodified privkey through to the return array
	$safe_privkey = $privkey;

	$was_clamped 		= wg_is_key_clamped($privkey);

	$privkey_clamped 	= wg_clamp_key($privkey);

	wg_escapeshellarg($safe_privkey);

	$pubkey = exec("echo {$safe_privkey} | {$wgg['wg']} pubkey");

	$res = array('privkey' => $privkey, 'privkey_clamped' => $privkey_clamped, 'pubkey' => $pubkey, 'was_clamped' => $was_clamped);

	return $json ? json_encode($res) : $res;

}

function wg_clamp_key($key) {

	if (wg_is_valid_key($key)) {

		$decoded_key = base64_decode($key);

		$key_bytes = array_slice(unpack("C*", "\0{$decoded_key}"), 1);

		$key_bytes[0] &= 248;

		$key_bytes[31] = ($key_bytes[31] & 127) | 64;

		$decoded_clamped_key = pack("C*", ...$key_bytes);

		return base64_encode($decoded_clamped_key);

	}

	return null;

}

function wg_is_key_clamped($key) {

	if (wg_is_valid_key($key)) {

		return ($key == wg_clamp_key($key));

	}

	return true;

}

// Checks if a given private, public, or pre-shared key is valid
function wg_is_valid_key($key) {

	// Reference: https://lists.zx2c4.com/pipermail/wireguard/2020-December/006222.html

	return preg_match('/^[A-Za-z0-9+\/]{42}[A|E|I|M|Q|U|Y|c|g|k|o|s|w|4|8|0]=$/', $key);

}

// Generate a pre-shared key
function wg_gen_psk() {
	global $wgg;

	$psk = exec("{$wgg['wg']} genpsk");

	return $psk;
	
}

// Return the next available WireGuard port
function next_wg_port() {
	global $wgg;

	wg_globals();

	for ($idx = $wgg['default_port']; $idx < $wgg['max_port']; $idx++) {

		// Check to see if the port is already in use
		$found = false;

		foreach ($wgg['tunnels'] as $tunnel) {

			if ($tunnel['listenport'] == $idx) {

				$found = true;

				break;

			}

		}

		// If not, it can be used
		if (!$found) {

			return $idx;
			
		}

	}

	return $wgg['default_port'];

}

// Wrapper to return just the xml array index
function wg_get_tunnel_array_index($tunnel_name) {
	global $wgg;

	$ret_val = array();

	if (is_array($wgg['tunnels'])) {

		foreach ($wgg['tunnels'] as $tun_idx => $tunnel) {

			if ($tunnel['name'] == $tunnel_name) {

				return $tun_idx;

			}

		}

	}

	return -1;

}

// Return the next available WireGuard interface
function next_wg_if() {
	global $wgg;

	wg_globals();

	$used_ifs = array();

	foreach ($wgg['tunnels'] as $tunnel) {

		$used_ifs[] = $tunnel['name'];

	}

	for ($ifnum = 0; $ifnum < $wgg['max_tunnels']; $ifnum++) {

		$want_if = "{$wgg['if_prefix']}{$ifnum}";
		
		if (!in_array($want_if, $used_ifs)) {

			return $want_if;

		}

	}

	return -1;
	
}

// Check if wg tunnel is assigned to an interface
function is_wg_tunnel_assigned($tunnel_name, $disabled = true) {

	$if_list = get_configured_interface_list_by_realif($disabled);

	$is_assigned = array_key_exists($tunnel_name, $if_list);

	return $is_assigned;
	
}

// Check if at least one tunnel is assigned
function is_wg_assigned($disabled = true) {
	global $wgg;

	// Assume that no tunnels are assigned
	$is_assigned = false;

	$if_list = get_configured_interface_list_by_realif($disabled);

	foreach ($if_list as $realif => $name) {

		// We found one, no need to keep checking
		if (substr($realif, 0, strlen($wgg['if_prefix'])) == $wgg['if_prefix']) {
	
			$is_assigned = true;

			break;
	
		}
	    
	    }

	return $is_assigned;

}

// Check if at least one tunnel is enabled
function is_wg_enabled() {
	global $config, $wgg;

	wg_globals();

	if (is_array($wgg['tunnels'])) {

		foreach ($wgg['tunnels'] as $tunnel) {

			if (empty($tunnel['enabled'])) {

				continue;

			}

			return true;

		}

	}

	return false;
	
}

/*
 * This returns an array of peers for a given tunnel
 */
function wg_get_tunnel_peers($tunnel_name) {
	global $wgg;

	wg_globals();

	$a_ret = array();

	$tun_idx = wg_get_tunnel_array_index($tunnel_name);

	if (isset($wgg['tunnels'][$tun_idx])) {

		if (isset($wgg['peers']) && is_array($wgg['peers'])) {

			// Look through array of peers for matching tunnel name
			foreach ($wgg['peers'] as $peer_idx => $peer) {

				if ($peer['tun'] == $tunnel_name) {

					// We need the array index for future manipulations
					$peer['index'] = $peer_idx;

					$a_ret[] = $peer;

				}

			}

		}

	}

	// Return the list of filtered peers
	return $a_ret;

}

/* 
 * Return WireGuard tunnel networks for a given address family
 */
function wg_get_tunnel_networks($family = 'both') {
	global $config, $wgg;

	$wg_tunnel_networks = array();

	if (is_wg_enabled()) {

		foreach ($wgg['tunnels'] as $tunnel) {

			if (!empty($tunnel['addresses']['row']) && is_array($tunnel['addresses']['row'])) {

				foreach ($tunnel['addresses']['row'] as $address) {

					$masked_address = "{$address['address']}/{$address['mask']}";

					if ((is_ipaddrv6($masked_address) && ($family == 'ipv4')) ||
						(is_ipaddrv4($masked_address) && ($family == 'ipv6'))) {

						continue;

					}

					if (is_subnet($masked_address)) {

						$network = gen_subnet($address['address'], $address['mask']);

						$wg_tunnel_networks[] 	= array(
										'network' => $network, 
										'mask' => $address['mask'],
										'tun' => $tunnel['name'],
										'descr' => $tunnel['descr']);

					}

				}

			}

		}

	}

	return $wg_tunnel_networks;

}

?>