<?php
/*
 * wg_api.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/rcmcdonald91)
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
require_once('filter.inc');
require_once('gwlb.inc');
require_once('interfaces.inc');
require_once('util.inc');
require_once('services.inc');
require_once('service-utils.inc');

// WireGuard includes
require_once('wireguard/includes/wg_globals.inc');
require_once('wireguard/includes/wg_install.inc');

/*
* A wrapper for wg_get_running_config to export everything plus extras for the status page
*/
function wg_get_status($json = false) {
	return wg_get_running_config(true, $json);
}

/*
* Returns a massive associative array of current wg status
* 
* $extras returns additional info not provided by `wg show all dump`
*/
function wg_get_running_config($extras = false, $json = false) {
	global $wgg;

	$tunnel_output_keys = array('private_key', 'public_key', 'listen_port', 'fwmark');

	$peer_output_keys = array('public_key', 'preshared_key', 'endpoint', 'allowed_ips', 'latest_handshake', 'transfer_rx', 'transfer_tx', 'persistent_keepalive');

	$ret_config = $cmd_output_rows = array();

	exec("{$wgg['wg']} show all dump", $cmd_output_rows, $ret_code);

	// Guard check
	if ($ret_code !== 0) {
		return ($json ? json_encode($ret_config) : $ret_config);
	}

	foreach ($cmd_output_rows as $row) {
		$tmp_tunnel = $tmp_peer = array();

		$a_device = explode("\t", $row);

		$current_device = $a_device[0];

		if (strcmp($current_device, $last_device)) {
			foreach ($tunnel_output_keys as $key_index => $key) {
				$tmp_tunnel[$key] = $a_device[$key_index + 1];
			}

			if ($extras) {
				// Gets some extra information about tunnels not returned by `wg show all dump`
				$tunnel_if_stats		= pfSense_get_interface_stats($current_device);

				$tmp_tunnel['status']		= wg_interface_status($current_device) ? 'up' : 'down';
				$tmp_tunnel['transfer_rx']	= $tunnel_if_stats['inbytes'];
				$tmp_tunnel['transfer_tx']	= $tunnel_if_stats['outbytes'];
				$tmp_tunnel['inpkts']		= $tunnel_if_stats['inpkts'];
				$tmp_tunnel['outpkts']		= $tunnel_if_stats['outpkts'];
				$tmp_tunnel['mtu']		= $tunnel_if_stats['mtu'];

				[$tun_idx, $tmp_tunnel['config'], $is_new] = wg_tunnel_get_config_by_name($current_device);
			}

			// Add the tunnel to the array
			$ret_config[$current_device] = $tmp_tunnel;

			// Now provision an empty peer array
			$ret_config[$current_device]['peers'] = array();

			$last_device = $a_device[0];

			continue;
		}

		foreach ($peer_output_keys as $key_index => $key) {
			$tmp_peer[$key] = $a_device[$key_index + 1];
		}

		if ($extras) {
			[$peer_idx, $tmp_peer['config'], $is_new] = wg_peer_get_config_by_name($last_device, $tmp_peer['public_key']);
		}

		// Add the peer to the array
		$ret_config[$last_device]['peers'][$a_device[1]] = $tmp_peer;
	}

	return ($json ? json_encode($ret_config) : $ret_config);
}

/*
 * Returns a peer's config array index by public key and tunnel name
 */
function wg_peer_get_array_idx($public_key, $tunnel_name) {
	global $wgg;

	foreach ((array) $wgg['peers'] as $peer_idx => $peer){
		if ($public_key == $peer['publickey'] && $tunnel_name == $peer['tun']) {
			return $peer_idx;
		}
	}

	return -1;
}

function wg_get_address_family($address) {
	if (is_v4($address)) {
		return 'inet';
	} elseif (is_v6($address)) {
		return 'inet6';
	}

	return false;
}

function wg_ifconfig_if_address_adddel($if_name, $masked_address, $add = true, &$cmds = null) {
	global $wgg;

	$s = fn($x) => $x;

	$ret_code = 0;

	$action = $add ? 'add' : 'delete';

	$error = $add ? WG_ERROR_IF_SETADDR : WG_ERROR_IF_DELADDR;

	if (wg_is_valid_tunnel($if_name, true)
	    && ($family = wg_get_address_family($masked_address))) {
		$cmd = "{$wgg['ifconfig']} {$s(escapeshellarg($if_name))} {$s(escapeshellarg($family))} {$s(escapeshellarg($masked_address))} {$s(escapeshellarg($action))} 2>&1";
		$cmds[] = $res = wg_exec($cmd, 'interface', $error);
		$ret_code = $res['ret_code'];
	}

	return ($ret_code == 0);
}

function wg_interface_in_group($if_name, $group = null) {
	global $wgg;

	$s = fn($x) => $x;

	$ifs = array();

	// Assume this will fail...
	$res = false;

	// Default to the package-installed interface group...
	$group = (is_null($group)) ? $wgg['ifgroupentry']['ifname'] : $group;

	if (wg_is_valid_tunnel($if_name, true)) {
		$cmd = "{$wgg['ifconfig']} -g {$s(escapeshellarg($group))} 2>&1";
		exec($cmd, $ifs, $ret_code);
		$res = ($ret_code <> 0) ? $res : in_array($if_name, $ifs);
	}

	return $res;
}

function wg_kld_loadunload($load = true, &$cmds = null) {
	global $wgg;

	$s = fn($x) => $x;

	$ret_code = 0;

	$kmod = $wgg['kmod'];

	// Determine which binary to use
	$kldbin = $load ? $wgg['kldload'] : $wgg['kldunload'];

	$error = $load ? WG_ERROR_KLD_LOAD : WG_ERROR_KLD_UNLOAD;

	if (($load && !is_module_loaded($kmod))
	    || (!$load && is_module_loaded($kmod))) {
		$cmd = "{$kldbin} {$s(escapeshellarg($kmod))} 2>&1";
		$cmds[] = $res = wg_exec($cmd, 'kld', $error);
		$ret_code = $res['ret_code'];
	}

	return ($ret_code == 0);
}

function wg_ifconfig_if_updown($if_name, $up = true, &$cmds = null) {
	global $wgg;

	$s = fn($x) => $x;
	
	$state = fn($up) => $up ? 'up' : 'down';

	$ret_code = 0;

	// Is this even a real interface?
	if (wg_is_valid_tunnel($if_name, true)) { 
		// Are we trying to down-an-up or up-a-down? Anything else is wrong in this context...
		if ((!wg_interface_status($if_name) && $up) || (wg_interface_status($if_name) && !$up)) {
			$cmd = "{$wgg['ifconfig']} {$s(escapeshellarg($if_name))} {$s(escapeshellarg($state($up)))} 2>&1";
			$error = $up ? WG_ERROR_IF_UP : WG_ERROR_IF_DOWN;
			$cmds[] = $res = wg_exec($cmd, 'interface', $error);
			$ret_code = $res['ret_code'];
		}
	}

	return ($ret_code == 0);
}

/*
 * This updates the addresses of the specified interface without tearing it down
 */
function wg_interface_update_addresses($if_name, &$cmds = null) {
	global $wgg;

	// Assume this will be successful...
	$res = true;

	if (wg_is_valid_tunnel($if_name, true)
	    && ([$tun_idx, $tunnel, $is_new] = wg_tunnel_get_config_by_name($if_name))) {
		// Assigned tunnel interfaces are handled by pfSense and should be ignored here
		if (!is_wg_tunnel_assigned($tunnel['name'])) {
			// Get an array of the current addresses assigned to the tunnel interface
			$current = pfSense_getall_interface_addresses($tunnel['name']);

			// Get an array of the addresses to be assigned to the interface
			$desired = array_map(function($x) {
				return "{$x['address']}/{$x['mask']}"; 
			}, $tunnel['addresses']['row']);

			// Determine the addresses to remove
			$addresses_to_remove = array_diff($current, array_intersect($current, $desired));

			// Now remove them
			foreach ($addresses_to_remove as $address) {
				$res &= wg_ifconfig_if_address_adddel($tunnel['name'], $address, false, $cmds);
			}

			// Determine the addresses to add
			$addresses_to_add = array_diff($desired, array_intersect($current, $desired));

			// Now add them
			foreach ($addresses_to_add as $address) {
				$res &= wg_ifconfig_if_address_adddel($tunnel['name'], $address, true, $cmds);
			}

		// Need to let pfSense handle the assigned interfaces
		} elseif (is_wg_tunnel_assigned($tunnel['name'])) {
			if ($pfsense_if_name = wg_get_pfsense_interface_info($tunnel['name'])) {
				// This doesn't return anything useful...
				interface_reconfigure($pfsense_if_name['name']);
			}
		}
	}

	// This will return false if anything went wrong...
	return $res;
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
		$ret_array['descr']	= $ifdescr[$tmp_name];

		return $ret_array;
	}

	// Consumers of this function always expect an array type
	return $ret_array;
}

/*
* A wrapper for just setting peer endpoints
*/
function wg_peer_set_endpoint($tunnel_name, $public_key, $endpoint, &$cmds = null) {
	// We only want to run this in running peers...
	if (in_array($public_key, wg_tunnel_get_peers_running_keys($tunnel_name))) {
		return wg_peer_set_config($tunnel_name, $public_key, 'endpoint', $endpoint, WG_ERROR_PEER_ENDPOINT, $cmds);
	}
}

/*
* A wrapper for just removing peers
*/
function wg_peer_set_remove($tunnel_name, $public_key, &$cmds = null) {
	// We only want to run this in running peers...
	if (in_array($public_key, wg_tunnel_get_peers_running_keys($tunnel_name))) {
		return wg_peer_set_config($tunnel_name, $public_key, 'remove', null, WG_ERROR_PEER_REMOVE, $cmds);
	}
}

/*
* A wrapper for `wg set <wg_ifname> peer <public_key> <key> <value>`
* 
* If $key and $value are null, this will just create a peer entry
*/
function wg_peer_set_config($tunnel_name, $public_key, $key = null, $value = null, $error_flag = 0, &$cmds = null) {
	global $wgg;

	$s = fn($x) => $x;

	$key = (!is_null($key)) ? " {$s(escapeshellarg($key))}" : null;

	$value = (!is_null($value)) ? " {$s(escapeshellarg($value))}" : null;

	if (wg_is_valid_tunnel($tunnel_name, true)) {
		$cmd = "{$wgg['wg']} set {$s(escapeshellarg($tunnel_name))} peer {$s(escapeshellarg($public_key))}{$key}{$value} 2>&1";
		$cmds[] = $res = wg_exec($cmd, 'peer', $error_flag);
		$ret_code = $res['ret_code'];
	}

	return ($ret_code == 0);
}

function wg_wg_if_sync($if_name, &$cmds = null) {
	global $wgg;

	$s = fn($x) => $x;

	$ret_code = 0;

	// We need to make sure latest conf files are on disk
	wg_resync();

	$conf_path = "{$wgg['conf_path']}/{$if_name}.conf";

	if (file_exists($conf_path)
	    && wg_is_valid_tunnel($if_name, true)) {
		$cmd = "{$wgg['wg']} syncconf {$s(escapeshellarg($if_name))} {$s(escapeshellarg($conf_path))}";
		$cmds[] = $res = wg_exec($cmd, 'interface', WG_ERROR_IF_SYNC);
		$ret_code = $res['ret_code'];
	}

	return ($ret_code == 0);
}

/*
* This creates a WireGuard interface of a specified name
*/
function wg_ifconfig_if_create($if_name, &$cmds = null) {
	global $wgg;

	$s = fn($x) => $x;

	$ret_code = 0;

	if (!wg_is_valid_tunnel($if_name, true)) {
		$cmd = "{$wgg['ifconfig']} wg create name {$s(escapeshellarg($if_name))} 2>&1";
		$cmds[] = $res = wg_exec($cmd, 'interface', WG_ERROR_IF_CREATE);
		$ret_code = $res['ret_code'];
	}

	return ($ret_code == 0);
}

function wg_exec($cmd, $error_type = null, $error_flag = 1) {
	$ret_code = 0;

	$output = $errors = array();
	
	exec($cmd, $output, $ret_code);

	$ret_code = ($ret_code <> 0) ? $error_flag : $ret_code;

	$errors = wg_get_errors($error_type, $ret_code);
	
	return array('cmd' => $cmd, 'output' => $output, 'ret_code' => $ret_code, 'errors' => $errors);
}

function wg_get_errors($type, $ret_code) {
	global $wgg;

	$errors = array();

	foreach ((array) $wgg['error_flags'][$type] as $error_mask => $error_text) {
		if (($ret_code & $error_mask) > 0) {
			$errors[$error_mask] = $error_text;
		}
	}

	return $errors;
}

/*
* This destroys a WireGuard interface of a specified name
*/
function wg_ifconfig_if_destroy($if_name, &$cmds = null) {
	global $wgg;

	$s = fn($x) => $x;

	$ret_code = 0;

	if (wg_is_valid_tunnel($if_name, true)) {
		$cmd = "{$wgg['ifconfig']} {$s(escapeshellarg($if_name))} destroy 2>&1";
		$cmds[] = $res = wg_exec($cmd, 'interface', WG_ERROR_IF_DESTROY);
		$ret_code = $res['ret_code'];
	}

	return ($ret_code == 0);
}

function wg_gateways_set_enable($enable = true, $filter_configure = true) {
    $gws = return_gateways_array(true);

    // Assume nothing will be done
    $changes = false;

    foreach ($gws as $gwname => $gw) {
        if (in_array($gw['interface'], wg_get_configured_ifs())) {
            gateway_set_enabled($gwname, $enable);
            $changes = true;
        }
    }

    // Guard check
    if (!$changes) {
        return;
    }

    $action = $enable ? 'Enabled' : 'Disabled';

    wg_write_config("{$action} all WireGuard gateways.", false);

    if ($filter_configure) {
        filter_configure();
    }
}

/*
 * Returns an array of running WireGuard tunnel interfaces per wg(8)
 */
function wg_get_running_ifs() {
	return array_keys(wg_get_running_config());
}

/*
 * Returns an array of configured WireGuard tunnel interfaces
 */
function wg_get_configured_ifs() {
	global $wgg;

	return array_map(fn($x) => $x['name'], (array) $wgg['tunnels']);
}

function wg_ifconfig_if_group_addremove($if_name, $group, $remove = false, &$cmds = null) {
	global $wgg;

	$s = fn($x) => $x;

	$ret_code = 0;

	$action = $remove ? '-group' : 'group';

	// Is this a real interface and not already in the group?
	if (in_array($if_name, pfSense_interface_listget())
	    && ((!wg_interface_in_group($if_name, $group) && !$remove) || (wg_interface_in_group($if_name, $group) && $remove))) {
		$cmd = "{$wgg['ifconfig']} {$s(escapeshellarg($if_name))} {$s(escapeshellarg($action))} {$s(escapeshellarg($group))} 2>&1";
		$cmds[] = $res = wg_exec($cmd, 'interface', WG_ERROR_IF_GROUP);
		$ret_code = ($res['ret_code']);	
	}

	return ($ret_code == 0);
}

/*
 * This adds a WireGuard interface to the WireGuard interface groups
 */
function wg_interface_update_groups($if_name, &$cmds = null) {
	global $wgg;

	// Assume this will be successful...
	$res = true;

	// Is this a real interface?
	if (in_array($if_name, pfSense_interface_listget())) {
		$res &= wg_ifconfig_if_group_addremove($if_name, $wgg['ifgroupentry']['ifname'], true, $cmds);

		$res &= wg_ifconfig_if_group_addremove($if_name, 'wg', false, $cmds);

		if (isset($wgg['config']['interface_group'])
		    && (($wgg['config']['interface_group'] == 'none') || (($wgg['config']['interface_group'] == 'unassigned')
		    && is_wg_tunnel_assigned($if_name)))) {
			// We don't want to add the tunnel to the WireGuard group...
			return $res;
		}

		// Default behavior is to just add everything...
		$res &= wg_ifconfig_if_group_addremove($if_name, $wgg['ifgroupentry']['ifname'], false, $cmds);
	}

	return $res;
}

function wg_interface_status($if_name) {
	$if_flags = wg_ifconfig_get_flags($if_name);

	return in_array('UP', $if_flags);
}

function wg_ifconfig_get_flags($if_name) {
	global $wgg;

	$s = fn($x) => $x;

	$flags = $output = array();

	if (wg_is_valid_tunnel($if_name, true)) {
		exec("{$wgg['ifconfig']} {$s(escapeshellarg($if_name))}", $output, $ret_code);

		if (($ret_code == 0) && preg_match("/flags=.*<(?P<flags>.*)>/", $output[0], $matches)) {
			$flags = explode(',', $matches['flags']);
		}
	}

	// Consumers of this function always expect an array type
	return $flags;
}

// Get various package infos and return an associative array
function wg_pkg_info() {
	global $wgg;

	$s = fn($x) => $x;

	$ret_array = array();

	$fields = array('name'=> '%n', 'version' => '%v', 'comment' => '%c');

	$return_keys = array_values(array_flip($fields));

	$field_string = implode("\t", $fields);

	// Each package needs to be escaped individually before imploding
	$packages = array_map(fn($x) => escapeshellarg($x), $wgg['depends_names']);

	$packages_string = implode(' ', $packages);

	exec("{$wgg['pkg']} query {$s(escapeshellarg($field_string))} {$packages_string}", $output, $ret_code);

	if ($ret_code == 0) {
		foreach ($output as $pkg_index => $package) {
			$fields = explode("\t", $package);

			foreach ($fields as $field_index => $field) {
				$ret_array[$pkg_index][$return_keys[$field_index]] = $field;
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

	return wg_gen_publickey($privkey, $json);
}

// Compose the public key from a provided private key
function wg_gen_publickey($privkey, $json = false) {
	global $wgg;

	$s = fn($x) => $x;

	$was_clamped = wg_is_key_clamped($privkey);

	$privkey_clamped = wg_clamp_key($privkey);

	$pubkey = exec("echo {$s(escapeshellarg($privkey))} | {$wgg['wg']} pubkey");

	$res = array('privkey' => $privkey, 'privkey_clamped' => $privkey_clamped, 'pubkey' => $pubkey, 'was_clamped' => $was_clamped);

	return $json ? json_encode($res) : $res;
}

/*
 * Reference0: https://lists.zx2c4.com/pipermail/wireguard/2021-June/006787.html
 * Reference1: https://git.zx2c4.com/wireguard-freebsd/tree/src/crypto.h#n100
 * 
 * Even though any 256-bit bitstring generated from a csprng is a fine private key,
 * the kernel clamps private keys according to the transformation implemented here.
 * Some WireGuard key generators aren't properly pre-clamping generated keys and this
 * can cause confusion for some users who might stumble across local .conf files 
 * and wg(8) output and see 'different' private keys, even though these keys result in
 * the same public key.
 * 
 * The way WireGuard is implemented, you can technically have two valid private keys,
 * one that is pre-clamped and one that is not, that can both result in the same public key.
 * 
 * These routines detect if the key is clamped or not, so we can at least include a note
 * in the .conf file so users who stumble onto them during troubleshooting won't freak out.
 * 
 * These routines might also become useful in the future for logging facilities.
 * 
 * Private keys and pre-shared Keys must undergo this transformaton.
 * 
 */
function wg_clamp_key($key) {
        if (!wg_is_valid_key($key)) {
                return null;
        }

        $decoded_key = base64_decode($key);
        $key_bytes = array_slice(unpack("C*", "\0{$decoded_key}"), 1);
        $key_bytes[0] &= 248;
        $key_bytes[31] = ($key_bytes[31] & 127) | 64;
        $decoded_clamped_key = pack("C*", ...$key_bytes);

        return base64_encode($decoded_clamped_key);
}

/*
 * Checks if a given $key is clamped
 */
function wg_is_key_clamped($key) {
	if (wg_is_valid_key($key)) {
		return ($key == wg_clamp_key($key));
	}

	return true;
}

/*
 * Reference0: https://lists.zx2c4.com/pipermail/wireguard/2020-December/006222.html
 * 
 * Checks if a given private, public, or pre-shared key is valid
 */
function wg_is_valid_key($key) {
	return preg_match('/^[A-Za-z0-9+\/]{42}[A|E|I|M|Q|U|Y|c|g|k|o|s|w|4|8|0]=$/', $key);
}

/*
 * Generate a pre-shared key
 */
function wg_gen_psk() {
	global $wgg;

	$psk = exec("{$wgg['wg']} genpsk");

	return $psk;
}

/*
 * Return the next available WireGuard port
 */
function next_wg_port() {
	global $wgg;

	wg_globals();

	for ($idx = $wgg['default_port']; $idx < $wgg['max_port']; $idx++) {
		// Check to see if the port is already in use
		$found = false;

		foreach ((array) $wgg['tunnels'] as $tunnel) {
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

	// We've run out of ports
	return false;
}

// Wrapper to return just the xml array index
function wg_tunnel_get_array_idx($tunnel_name) {
	global $wgg;

	foreach ((array) $wgg['tunnels'] as $tun_idx => $tunnel) {
		if ($tunnel['name'] == $tunnel_name) {
			return $tun_idx;
		}
	}

	return -1;
}

// Return the next available WireGuard interface
function next_wg_if() {
	global $wgg;

	wg_globals();

	for ($ifnum = 0; $ifnum < $wgg['max_tunnels']; $ifnum++) {
		$want_if = "{$wgg['if_prefix']}{$ifnum}";

		if (!in_array($want_if, wg_get_configured_ifs())) {
			return $want_if;
		}
	}

	// We've run out of tuns
	return false;
}

// Check if wg tunnel is assigned to an interface
function is_wg_tunnel_assigned($tunnel_name, $disabled = true) {
	$if_list = get_configured_interface_list_by_realif($disabled);

	return array_key_exists($tunnel_name, $if_list);
}

/*
 * Checks if a peer is enabled
 */
function wg_peer_is_enabled($tunnel, $public_key) {
	if ([$peer_idx, $peer, $is_new] = wg_peer_get_config_by_name($tunnel, $public_key)) {
		return (isset($peer['enabled']) && ($peer['enabled'] == 'yes'));
	}

	return false;
}

/*
 * Check if the given peer has any allowed ips indicating it's the default route it's tunnel
 */
function wg_peer_is_default_route($peer) {
	$default_ipv4 = false;
	$default_ipv6 = false;

	// TODO: Unassigned tunnel check?

	if (!empty($peer['allowedips']['row']) && is_array($peer['allowedips']['row'])) {
		foreach ((array) $peer['allowedips']['row'] as $row) {
			# Only interested in default routes using masks of /0
			if ($row['mask'] != '0') {
				continue;
			}

			if (is_ipaddrv4($row['address']) && $row['address'] == '0.0.0.0') {
				$default_ipv4 = true;
			# TODO: Check for compressed IPv6 addresses? Could it be bypassed with 0::/0, or 0:0000:0::/0 etc
			} else if (is_ipaddrv6($row['address']) && $row['address'] == '::') {
				$default_ipv6 = true;
			}

			# Check if we can break out early
			if ($default_ipv4 && $default_ipv6) {
				break;
			}
		}
	}

	return [$default_ipv4, $default_ipv6];
}

/*
 * Check if a given WireGuard peer is valid
 */
function wg_peer_is_valid($tunnel_name, $public_key, $running_state = false) {
	global $wgg;

	return in_array($public_key, ($running_state ? wg_tunnel_get_peers_running_keys($tunnel_name) : wg_tunnel_get_peers_config_keys($tunnel_name)));
}

/*
 * Check if a given WireGuard tunnel is valid
 */
function wg_is_valid_tunnel($tunnel_name, $running_state = false) {
	global $wgg;

	return in_array($tunnel_name, ($running_state ? wg_get_running_ifs() : wg_get_configured_ifs()));
}

// Checks if the service is or should be enabled
function wg_is_service_enabled() {
	global $wgg;

	$enable = $wgg['config']['enable'];

	$is_enabled = (isset($enable) && (($enable === 'on') || $enable === 'yes'));

	$should_be_enabled = wg_is_wg_assigned();

	return ($is_enabled || $should_be_enabled);
}

// Check if at least one tunnel is enabled
function is_wg_enabled() {
	global $wgg;

	wg_globals();

	foreach ((array) $wgg['tunnels'] as ['enabled' => $enabled]) {
		if (isset($enabled) && $enabled == 'yes') {
			// We found one, no need to keep checking
			return true;
		}
	}

	return false;
}

/*
 * Check if at least one tunnel is assigned to a pfSense interface
 */
function wg_is_wg_assigned($disabled = true) {
	global $wgg;

	foreach ((array) $wgg['tunnels'] as ['name' => $name]) {
		if (is_wg_tunnel_assigned($name, $disabled)) {
			// We found one, no need to keep checking
			return true;
		}
	}

	return false;
}

function wg_peer_get_config_by_name($tunnel_name, $public_key) {
	global $wgg;

	$peer_idx = wg_peer_get_array_idx($public_key, $tunnel_name);

	return wg_peer_get_config($peer_idx, false);
}

function wg_tunnel_get_config_by_name($tunnel_name) {
	global $wgg;

	$tun_idx = wg_tunnel_get_array_idx($tunnel_name);

	return wg_tunnel_get_config($tun_idx, false);
}

function wg_tunnel_get_config($tun_idx, $return_empty = false) {
	global $wgg;

	wg_globals();

	$tunnel = array();

	$valid = (isset($wgg['tunnels'][$tun_idx]) && is_array($wgg['tunnels'][$tun_idx]));

	$is_new = !$valid;

	$tun_idx = !$valid ? count($wgg['tunnels']) : $tun_idx;

	$tunnel = $wgg['tunnels'][$tun_idx];

	wg_init_config_arr($tunnel, array('addresses', 'row'));

	return ($valid || $return_empty) ? array($tun_idx, $tunnel, $is_new) : false;
}

/*
 * Return bools indicating if the tunnel referenced has any peers with default IPv4 or IPv6 routes
 */
function wg_tunnel_has_default_route_peer($tunnel_name, $ignore_peer_idx) {
	$default_ipv4 = false;
	$default_ipv6 = false;

	foreach (wg_tunnel_get_peers_config($tunnel_name) as [$peer_idx, $peer, $is_new]) {
		if ($peer_idx != $ignore_peer_idx) {
			[$peer_default_ipv4, $peer_default_ipv6] = wg_peer_is_default_route($peer);

			if ($peer_default_ipv4) {
				$default_ipv4 = true;
			}

			if ($peer_default_ipv6) {
				$default_ipv6 = true;
			}
		}

		# Check if we can stop early
		if ($default_ipv4 && $default_ipv6) {
			break;
		}
	}

	return [$default_ipv4, $default_ipv6];
}

function wg_peer_get_config($peer_idx, $return_empty = false) {
	global $wgg;

	wg_globals();

	$peer = array();

	$valid = (isset($wgg['peers'][$peer_idx]) && is_array($wgg['peers'][$peer_idx]));

	$is_new = !$valid;

	$peer_idx = !$valid ? count($wgg['peers']) + 1 : $peer_idx;

	$peer = $wgg['peers'][$peer_idx];

	wg_init_config_arr($peer, array('allowedips', 'row'));

	return ($valid || $return_empty) ? array($peer_idx, $peer, $is_new) : false;
}

/*
 * This returns an array of peer configs for a given tunnel
 */
function wg_tunnel_get_peers_config($tunnel_name) {
	global $wgg;

	wg_globals();

	$ret_peers = array();

	// Look through array of peers for matching tunnel name
	foreach ((array) $wgg['peers'] as $peer_idx => $peer) {
		if ($peer['tun'] == $tunnel_name) {
			$ret_peers[] = wg_peer_get_config($peer_idx, false);
		}
	}

	// Return the list of filtered peers
	return $ret_peers;
}

/*
 * This returns an array of peer keys for a given tunnel
 * 
 * These are actually *running* and bound to a tunnel
 */
function wg_tunnel_get_peers_running_keys($tunnel_name) {
	global $wgg;

	$s = fn($x) => $x;

	$output = $res = array();

	$cmd = "{$wgg['wg']} show {$s(escapeshellarg($tunnel_name))} peers 2>&1";

	exec($cmd, $output, $ret_code);

	$res = ($ret_code <> 0) ? $res : $output;

	return $res;
}

/*
 * This returns an array of peer keys for a given tunnel
 * 
 * These are actually *configured* and bound to a tunnel
 */
function wg_tunnel_get_peers_config_keys($tunnel_name) {
	// Pull out the public keys
	$keys = array_map(function($s) { 
		[$peer_idx, $peer, $is_new] = $s;

		return $peer['publickey'];
	}, wg_tunnel_get_peers_config($tunnel_name));

	return $keys;
}

/* 
 * Return WireGuard tunnel networks for a given address family
 */
function wg_get_tunnel_networks($family = 'both') {
	global $wgg;

	$wg_tunnel_networks = array();

	// Bail out early if WireGuard is not enabled...
	if (!is_wg_enabled()) {
		return $wg_tunnel_networks;
	}

	foreach ((array) $wgg['tunnels'] as $tunnel) {
		if (!is_array($tunnel['addresses'])
		    || !is_array($tunnel['addresses']['row']))
				continue;

		foreach ((array) $tunnel['addresses']['row'] as $address) {
			$masked_address = "{$address['address']}/{$address['mask']}";

			if ((is_ipaddrv6($masked_address) && ($family == 'ipv4')) 
			    || (is_ipaddrv4($masked_address) && ($family == 'ipv6'))) {
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

	return $wg_tunnel_networks;
}

?>
