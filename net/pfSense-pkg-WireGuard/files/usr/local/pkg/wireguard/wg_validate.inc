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
		$addrs = explode(",", $pconfig['interface']['address']);

		foreach ($addrs as $addr) {
			$addr = trim($addr);

			// Interface address is not technically required anymore
			if (!empty($addr) && !is_subnet($addr)) {
				$input_errors[] = sprintf(gettext(
					'%1$s is not a valid CIDR address'), $addr);
			}

			$a = explode("/", $addr);
			$conflicts = where_is_ipaddr_configured($a[0], $skip, true,
				true, $a[1]);

			if (!empty($conflicts)) {
				foreach ($conflicts as $conflict) {
					$input_errors[] = sprintf(gettext(
						'%1$s is already configured on this ' .
						'firewall: %2$s (%3$s)'), $addr,
						strtoupper($conflict['if']),
						$conflict['ip_or_subnet']);
				}
			}
		}
	
	}

	if (is_wg_tunnel_assigned($pconfig) && (!isset($pconfig['enabled']) || ($pconfig['enabled'] != 'yes'))) {

		$input_errors[] = gettext('Cannot disable a WireGuard tunnel while it is assigned as an interface.');

	}

	// Check listen port
	$lport = $pconfig['interface']['listenport'];
	if (!empty($lport) && (!ctype_digit($lport) || !is_port($lport))) {
		$input_errors[] = gettext("Invalid interface listen port.");
	}

	// Check keys
	if (empty($pconfig['interface']['privatekey'])) {
		$input_errors[] = gettext("Private key must be specified.");
	}

	// Now the peers
	if (isset($pconfig['peers']['wgpeer'])) {
		$idx = 0;
		foreach ($pconfig['peers']['wgpeer'] as $peer) {
			$input_errors = array_merge($input_errors,
			    wg_validate_peer($idx, $peer));
			$idx++;
		}
	}

	return $input_errors;
}

// Valildate a peer
function wg_validate_peer($idx, $peer) {
	global $wgpeerdefaultroute;

	$input_errors = array();

	// Check remote port
	$rport = $peer['port'];
	if (!empty($rport) && (!ctype_digit($rport) || !is_port($rport))) {
		$input_errors[] = sprintf(gettext(
		    'Peer %1$s: Invalid remote port. (%2$s)'), $idx, $rport);
	}

	// Check key
	if (empty($peer['publickey'])) {
		$input_errors[] = sprintf(gettext(
		    'Peer %1$s: A public key must be specified'), $idx);
	}

	// Endpoint
	$ep = trim($peer['endpoint']);
	if (!empty($ep) && !is_hostname($ep) && !is_ipaddr($ep)) {
		$input_errors[] = sprintf(gettext(
		    'Peer %1$s: Endpoint must be a valid IPv4 or IPv6 ' .
		    'adress or hostname.'), $idx);
	}

	// Allowed IPs
	if (!empty($peer['allowedips'])) {
		foreach (explode(",", $peer['allowedips']) as $ip) {
			if (!is_subnet(trim($ip))) {
				$input_errors[] = sprintf(gettext(
				    'Peer %1$s: Address %2$s is not a valid ' .
				    'IPv4 or IPv6 CIDR subnet address.'),
					$idx, $ip);
			}
		}
	}

	// Default route, see https://redmine.pfsense.org/issues/11465
	$defroute = wg_defaultroute_check($peer);
	if ($defroute) { 
		if ((($defroute == 'all') && !empty($wgpeerdefaultroute)) ||
		    (($defroute == 'ipv4') && in_array('ipv4', $wgpeerdefaultroute)) ||
		    (($defroute == 'ipv6') && in_array('ipv6', $wgpeerdefaultroute))) {
			$input_errors[] = sprintf(gettext(
			    'Peer %1$s: Only one endpoint per address family can be the default route ' .
			    '(0.0.0.0/0, ::/0, or blank in the Allowed IPs field).'), $idx);
		} else {
			$wgpeerdefaultroute[] = $defroute;
		}
	}

	return $input_errors;
}

function wg_defaultroute_check($peer) {

	if (empty($peer['allowedips'])) {
		return 'all';
	} else {
		foreach (explode(",", $peer['allowedips']) as $ip) {
			$ip = trim($ip);
			if ($ip == '0.0.0.0/0') {
				return 'ipv4';
			} elseif ($ip == '::/0') {
				return 'ipv6';
			}
		}
	}
	return false;
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
	global $config;

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