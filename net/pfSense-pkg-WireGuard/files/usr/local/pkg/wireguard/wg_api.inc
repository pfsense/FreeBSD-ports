<?php
/*
 * wg_api.inc
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

// pfSense includes
require_once('globals.inc');
require_once('config.inc');
require_once('pfsense-utils.inc');
require_once('system.inc');
require_once('pkg-utils.inc');
require_once('service-utils.inc');
require_once('util.inc');

// WireGuard includes
require_once('wg_globals.inc');

// Get WireGuard status
function wg_status() {
	global $wgg;

	$status = array();
	exec("{$wgg['wg']} show", $status);

	$output = implode("\n", $status);
	return htmlspecialchars($output);

}

// Get WireGuard interface status
function wg_interface_status() {
	global $wgg;

	$status = array();
	exec("{$wgg['ifconfig']} -a -g {$wgg['if_group']}", $status);

	$output = implode("\n", $status);
	return htmlspecialchars($output);

}

// Get WireGuard kmod status
function wg_kmod_status() {
	global $wgg;

	$output = exec("{$wgg['kldstat']} | grep {$wgg['kmod']}");
	
	return htmlspecialchars($output);

}

// Get wg and tools version
function wg_version() {
	global $wgg;

	$packages = array();
	exec("{$wgg['pkg']} info | grep -i {$wgg['pkg_name']}", $packages);

	$output = implode("\n", $packages);
	return htmlspecialchars($output);

}

// Generate private key
function genKeyPair($json = false) {
	global $wgg;

	$privkey = exec("{$wgg['wg']} genkey");
	$pubkey = genPubKey($privkey);

	$res = array('privkey' => $privkey, 'pubkey' => $pubkey);
	return $json ? json_encode($res) : $res;

}

// Compose the public key from a provided private key
function genPubKey($privkey) {
	global $wgg;

	$pubkey = exec("echo {$privkey} | {$wgg['wg']} pubkey");

	return $pubkey;

}

// This soft resyncs a given WireGuard Tunnel (i.e. wg syncconf wgX <(wg-quick strip wgX))
function wg_syncconf($tunnel) {
	global $wgg;

	// Assume we have an interface first
	$wg_ifname = $tunnel;

	// Looks like we have a tunnel structure
	if (is_array($tunnel) && isset($tunnel['name'])) {

		// Transform to an interface name
		$wg_ifname = $tunnel['name'];

	}

	// Pass the stripped configuration to wg(8)
	$syncconf_command ="{$wgg['wg']} syncconf {$wg_ifname} <({$wgg['wg_quick']} strip {$wg_ifname})";

	$syncconf_command = escapeshellarg($syncconf_command);

	// We need <() process substitution, so use bash...might as well use bash everywhere?
	exec("{$wgg['bash']} -c {$syncconf_command}");

}

// Executes a wg-quick(8) action using a specified tunnel or interface
function exec_wg_quick_action($tunnel, $action, $verbose = false) {
	global $wgg;

	// Assume we have an interface first
	$wg_ifname = $tunnel;

	// Looks like we have a tunnel structure
	if (is_array($tunnel) && isset($tunnel['name'])) {

		// Transform to an interface name
		$wg_ifname = $tunnel['name'];

	}

	// Execute wg-quick(8) on the interface using the provided action 
	exec("{$wgg['wg_quick']} {$action} {$wg_ifname}" . verbose_pipe($verbose));

}

// Generate a pre-shared key
function genPSK() {
	global $wgg;

	$psk = exec("{$wgg['wg']} genpsk");

	return $psk;
	
}

// Return the next available WireGuard port
function next_wg_port() {
	global $config, $wgg;

	wg_globals();

	for ($idx=$wgg['default_port']; $idx<$wgg['max_port']; $idx++) {

		// Check to see if the port is already in use
		$found = false;

		foreach ($wgg['tunnels'] as $tunnel) {

			if ($tunnel['interface']['listenport'] == $idx) {

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

// Return the next available WireGuard interface
function next_wg_if() {
	global $config, $wgg;

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

function verbose_pipe($verbose) {

	return $verbose ? "" : " > /dev/null 2>&1";

}

?>