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

/*
 * Valildate a tunnel
 * These validation checks should be in the same order as the UI for consistency
 */
function wg_validate_tunnel_post($pconfig) {

	$input_errors = array();

	// Check enabled/disabled status
	if (is_wg_tunnel_assigned($pconfig['name']) && (!isset($pconfig['enabled']) || ($pconfig['enabled'] != 'yes'))) {

		$wg_pfsense_if = wg_get_pfsense_interface_info($pconfig['name']);

		$input_errors[] = "Cannot disable {$pconfig['name']} while it is assigned to {$wg_pfsense_if['name']} ({$wg_pfsense_if['name']}).";

	}

	// Check listen port
	$lport = $pconfig['listenport'];

	if (!empty($lport) && (!ctype_digit($lport) || !is_port($lport))) {

		$input_errors[] = "Invalid interface listen port ({$lport}).";

	}

	// Check keys
	if (empty($pconfig['privatekey'])) {

		$input_errors[] = gettext('A private key must be specified.');

	} elseif (!wg_is_valid_key($pconfig['privatekey'])) {

		$input_errors[] = gettext('The private key specified is not a valid WireGuard private key.');

	}

	// Assigned tunnels don't need these validation checks
	if (!is_wg_tunnel_assigned($pconfig['name'])) {

		foreach ($pconfig['addresses']['row'] as $address) {

			// Remove any accidental whitespace
			$address['address'] = trim($address['address']);

			$tmp_subnet = "{$address['address']}/{$address['mask']}";

			if (!empty($address['address']) && !is_subnet($tmp_subnet)) {

				$input_errors[] = "Address {$tmp_subnet} is not a valid CIDR address.";

			}

			$conflicts = where_is_ipaddr_configured($address['address'], $skip, true, true, $address['mask']);

			if (!empty($conflicts)) {

				foreach ($conflicts as $conflict) {

					$ifname = strtoupper($conflict['if']);

					$input_errors[] = "Address {$address['address']} is already configured on this firewall. [ {$ifname} ({$conflict['ip_or_subnet']}) ]";
				
				}
			}
		}
	
	}

	// Consumers expect an array
	return $input_errors;

}

/*
 * Valildate a peer
 * These validation checks should be in the same order as the UI for consistency
 */
function wg_validate_peer_post($pconfig) {

	$input_errors = array();

	// Check remote endpoint
	$ep = trim($pconfig['endpoint']);

	if (!empty($ep) && !is_hostname($ep) && !is_ipaddr($ep)) {

		$input_errors[] = "Endpoint {$ep} must be a valid IPv4 or IPv6 address or hostname.";

	}

	// Check remote port
	$rport = $pconfig['port'];

	if (!empty($rport) && (!ctype_digit($rport) || !is_port($rport))) {

		$input_errors[] = "Invalid peer remote port ({$rport}).";
	}

	// Check persistent keep alive
	$keepalive = $pconfig['persistentkeepalive'];

	if (!empty($keepalive) && !is_numericint($keepalive)) {

		$input_errors[] = "Invalid keep alive interval ({$keepalive}).";

	}

	// Check public key
	if (empty($pconfig['publickey'])) {

		$input_errors[] = gettext('A public key must be specified.');

	} elseif (!wg_is_valid_key($pconfig['publickey'])) {

		$input_errors[] = "The public key ({$pconfig['publickey']}) is not a valid WireGuard public key.";

	} elseif (!empty($pconfig['tun'])) {

		$peers = wg_get_tunnel_peers($pconfig['tun']);

		$peer_idx = wg_get_peer_id($pconfig['publickey'], $pconfig['tun']);

		foreach ($peers as $peer) {

			// We don't want to allow duplicate public keys to be assigned on the same tunnel
			if (($peer['publickey'] == $pconfig['publickey']) && ($peer['index'] != $peer_idx)) {

				$input_errors[] = "The public key ({$pconfig['publickey']}) is already assigned to a peer on this tunnel ({$pconfig['tun']}).";
				
				break;

			}

		}

	}

	// Check pre-shared key
	if (!empty($pconfig['presharedkey']) && !wg_is_valid_key($pconfig['presharedkey'])) {

		$input_errors[] = "The pre-shared key ({$pconfig['presharedkey']}) is not a valid WireGuard pre-shared key.";

	}

	// Check allowed ips
	if (!empty($pconfig['allowedips']['row']) && is_array($pconfig['allowedips']['row'])) {

		foreach ($pconfig['allowedips']['row'] as $row) {

			$row['address'] = trim($row['address']);

			$tmp_subnet = "{$row['address']}/{$row['mask']}";

			if (!empty($row['address']) && !is_subnet($tmp_subnet)) {

				$input_errors[] = "Address {$tmp_subnet} is not a valid IPv4 or IPv6 CIDR subnet address.";

			}

		}

	}

	// Consumers expect an array
	return $input_errors;

}

?>