<?php
/*
 * wg_validate.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/theonemcdonald)
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
require_once('pfsense-utils.inc');	// where_is_ipaddr_configured
require_once('util.inc'); 		// is_subnet, is_port, is_hostname, is_ipaddr

// WireGuard includes
require_once('wireguard/wg_api.inc');

wg_globals();

// Validate the user's input and return error messages if not acceptable
function wg_validate_tunnel_post($pconfig, $tun_idx) {
	$input_errors = array();

	// Assigned tunnels don't need these validation checks
	if (!is_wg_tunnel_assigned($pconfig)) {

		foreach ($pconfig['addresses']['row'] as $address) {

			// Remove any accidental whitespace
			$address['address'] = trim($address['address']);

			$tmp_subnet = "{$address['address']}/{$address['mask']}";

			if (!empty($address['address']) && !is_subnet($tmp_subnet)) {

				$input_errors[] = gettext("{$tmp_subnet} is not a valid CIDR address");

			}

			$conflicts = where_is_ipaddr_configured($address['address'], $skip, true, true, $address['mask']);

			if (!empty($conflicts)) {

				foreach ($conflicts as $conflict) {

					$ifname = strtoupper($conflict['if']);

					$input_errors[] = gettext("{$address['address']} is already configured on this firewall: {$ifname} ({$conflict['ip_or_subnet']})");
				
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

		$input_errors[] = gettext('Invalid interface listen port.');

	}

	// Check keys
	if (empty($pconfig['privatekey'])) {

		$input_errors[] = gettext('A private key must be specified.');

	} elseif (!wg_is_valid_key($pconfig['privatekey'])) {

		$input_errors[] = gettext('The private key specified is not a valid WireGuard private key.');

	}

	return $input_errors;

}

// Valildate a peer
function wg_validate_peer_post($pconfig, $peer_idx) {

	$input_errors = array();

	// Check remote port
	$rport = $pconfig['port'];

	if (!empty($rport) && (!ctype_digit($rport) || !is_port($rport))) {

		$input_errors[] = gettext("Invalid remote port. ({$rport}})");
	}

	// Check public key
	if (empty($pconfig['publickey'])) {

		$input_errors[] = gettext('A public key must be specified.');

	} elseif (!wg_is_valid_key($pconfig['publickey'])) {

		$input_errors[] = gettext("The public key ({$pconfig['publickey']}) is not a valid WireGuard public key.");

	} elseif (!empty($pconfig['tun'])) {

		$peers = wg_get_tunnel_peers($pconfig['tun']);

		foreach ($peers as $peer) {

			// We don't want to allow duplicate public keys to be assigned on the same tunnel
			if (($peer['publickey'] == $pconfig['publickey']) && ($peer['index'] != $peer_idx)) {

				$input_errors[] = gettext("The public key ({$pconfig['publickey']}) is already assigned to a peer on this tunnel ({$pconfig['tun']}).");
				
				break;

			}

		}

	}

	$ep = trim($pconfig['endpoint']);

	if (!empty($ep) && !is_hostname($ep) && !is_ipaddr($ep)) {

		$input_errors[] = gettext('Endpoint must be a valid IPv4 or IPv6 adress or hostname.');

	}

	// Allowed IPs
	if (!empty($pconfig['allowedips']['row']) && is_array($pconfig['allowedips']['row'])) {

		foreach ($pconfig['allowedips']['row'] as $address) {

			$address['address'] = trim($address['address']);

			$tmp_subnet = "{$address['address']}/{$address['mask']}";

			if (!empty($address['address']) && !is_subnet($tmp_subnet)) {

				$input_errors[] =gettext("Address {$tmp_subnet} is not a valid IPv4 or IPv6 CIDR subnet address.");

			}

		}

	}

	return $input_errors;
}

?>