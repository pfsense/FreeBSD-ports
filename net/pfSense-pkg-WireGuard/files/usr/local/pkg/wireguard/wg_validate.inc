<?php
/*
 * wg_validate.inc
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
require_once('wireguard/wg_globals.inc');

wg_globals();

global $wgpeerdefaultroute;
$wgpeerdefaultroute = array();

// Validate the user's input and return error messages if not acceptable
function wg_validate_post($pconfig) {
	$input_errors = array();

	// Assigned tunnels don't need these validation checks
	if (!is_wg_tunnel_assigned($pconfig)) {

		// Check the addresses
		$addrs = explode(",", $pconfig['addresses']);

		foreach ($addrs as $addr) {

			$addr = trim($addr);

			// Interface address is not technically required anymore
			if (!empty($addr) && !is_subnet($addr)) {

				$input_errors[] = gettext("{$addr} is not a valid CIDR address");

			}

			$a = explode("/", $addr);

			$conflicts = where_is_ipaddr_configured($a[0], $skip, true, true, $a[1]);

			if (!empty($conflicts)) {

				foreach ($conflicts as $conflict) {

					$ifname = strtoupper($conflict['if']);

					$input_errors[] = gettext("{$addr} is already configured on this firewall: {$ifname} ({$conflict['ip_or_subnet']})");
				
				}
			}
		}
	
	}

	if (is_wg_tunnel_assigned($pconfig) && (!isset($pconfig['enabled']) || ($pconfig['enabled'] != 'yes'))) {

		$input_errors[] = gettext('Cannot disable a WireGuard tunnel while it is assigned as an interface.');

	}

	// Check listen port
	$lport = $pconfig['listenport'];

	if (!empty($lport) && (!ctype_digit($lport) || !is_port($lport))) {

		$input_errors[] = gettext("Invalid interface listen port.");

	}

	// Check keys
	if (empty($pconfig['privatekey'])) {

		$input_errors[] = gettext("A private key must be specified.");

	} elseif (!wg_is_valid_key($pconfig['privatekey'])) {

		$input_errors[] = gettext("The private key specified is not a valid WireGuard private key.");

	}

	return $input_errors;

}

// Valildate a peer
function wg_validate_peer_post($post) {

	$input_errors = array();

	// Check remote port
	$rport = $post['port'];

	if (!empty($rport) && (!ctype_digit($rport) || !is_port($rport))) {

		$input_errors[] = gettext("Invalid remote port. ({$rport}})");
	}

	// Check key
	if (empty($post['publickey'])) {

		$input_errors[] = gettext("A public key must be specified.");

	} else {

		if (!wg_is_valid_key($post['publickey'])) {

			$input_errors[] = gettext("The public key specified is not a valid WireGuard public key.");

		}

	}

	// Endpoint

	$ep = trim($post['endpoint']);

	if (!empty($ep) && !is_hostname($ep) && !is_ipaddr($ep)) {

		$input_errors[] = gettext("Endpoint must be a valid IPv4 or IPv6 adress or hostname.");
	}

	// Allowed IPs
	if (!empty($post['allowedips'])) {

		foreach (explode(",", $post['allowedips']) as $ip) {

			if (!is_subnet(trim($ip))) {

				$input_errors[] =gettext("Address {$ip} is not a valid IPv4 or IPv6 CIDR subnet address.");

			}
		}
	}

	return $input_errors;
}

// Check if wg tunnel is assigned to an interface
function is_wg_tunnel_assigned($tunnel, $disabled = true) {
	global $config;

	// Assume we have an interface first
	$wg_ifname = $tunnel;

	// Looks like we have a tunnel structure
	if (is_array($tunnel) && isset($tunnel['name'])) {

		$wg_ifname = $tunnel['name'];

	}

	$if_list = get_configured_interface_list_by_realif($disabled);

	$is_assigned = array_key_exists($wg_ifname, $if_list);

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
	global $wgg;

	wg_globals();

	foreach ($wgg['tunnels'] as $tunnel) {

		if (empty($tunnel['enabled'])) {

			continue;

		}

		return true;

	}

	return false;
}

?>