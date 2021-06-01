<?php
/*
 * openvpn-client-import.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
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

require_once("config.gui.inc");
require_once("certs.inc");
require_once("pfsense-utils.inc");
require_once("openvpn.inc");
require_once("pkg-utils.inc");

if (!isset($openvpn_client_modes)) {
	$openvpn_client_modes = array(
		'p2p_tls' => gettext("Peer to Peer (SSL/TLS)"),
		'p2p_shared_key' => gettext("Peer to Peer (Shared Key)")
	);
}

// Perform some basic form validation, parse the .ovpn file, create the CA and cert (if required)
// then map the ovpn configs to pfSense configs and save
function import_openvpn_client($pconfig, $filename, $fileconts) {
	global $input_errors;

	$wc_msg = "";

	// Perform basic form validation
	if (isset($pconfig['auth-user-pass']) && ((strlen($pconfig['password']) == 0) || (strlen($pconfig['username']) == 0))) {
		$input_errors[] = gettext("A username and password must me provided for this configuration. (auth-user-pass was specified)");
	}

	if ($pconfig['password'] != "********") {
		if ($pconfig['password'] != $pconfig['password_confirm']) {
			$input_errors[] = gettext("The password and confirmation do not match");
		}
	}

	if (!$filename) {
		$input_errors[] = gettext("No .ovpn configuration file has been provided");
	}

	$username = $pconfig['username'];
	$password = $pconfig['password'];

	if ((strlen($username) > 0) && (strlen($password) == 0)) {
		$input_errors[] = gettext("Username supplied with no password.");
	}

	if ($input_errors) {
		return;
	}


	if (strlen($pconfig['description']) == 0) {
		$name = preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename) . "_OpenVPN";
	} else {
		$name = $pconfig['description'];
	}

	// Read the .ovpn file into an array
	$ovpn = parseOvpnConfig($fileconts);

	// Save the new CA and remember the reference ID
	if (strlen($ovpn['ca']) > 0) {
		if (CAExists($name)) {
			$input_errors[] = gettext("A CA with that name/description already exists. Please remove it or choose a new name.");
			return "";
		} else {
			$caref = save_OVPN_CA($ovpn['ca'], $name . "_CA", "disabled", "disabled");
		}
	}

	// Save the new certificate and remember the reference ID
	if (strlen($ovpn['cert']) > 0) {
		if (certExists($name)) {
			$input_errors[] = gettext("A certificate with that name/description already exists. Please remove it or choose a new name.");
			return "";
		} else {
			$certref = save_OVPN_CERT($ovpn['cert'], $ovpn['key'], $name . "_CERT", "disabled", "disabled");
		}
	}

	// Map the .ovpn configuration array into a pfSense configuration,
	// then save it with a suitable message
	if (!$input_errors) {
		$mappedConfig = mapOvpnConfig(
			$ovpn,
			$name,
			$username,
			$password,
			$caref,
			$certref,
			$pconfig['interface'],
			$pconfig['mode'],
			$pconfig['disable']
		);

		// print(json_encode($mappedConfig));
		$wc_msg = saveOpenVPNTunnel($mappedConfig);
	}

	return $wc_msg;
}

// Read the file contents into a PHP array. The $state variable determines what is being captured.
// cert, ca, key, line option etc
function parseOvpnConfig($fileconts) {
	global $input_errors;

	// State definitions
	define("CA", 0);
	define("CERT", 1);
	define("CMDS", 2);
	define("KEY", 3);
	define("TLSAUTH", 4);

	$state = CMDS;

	// All of the config items needed to please pfSense
	$ovpn = array();

	// Defaults
	$ovpn['compress'] = false;

	// Read the file into an array a line at a time
	$input = explode("\n", $fileconts);

	// For each line from the .ovpn config file, parse it and record it as needed	
	foreach ($input as &$line) {
		$line = trim($line);

		// Skip comments
		if (startsWith(trim($line), "#") || startsWith(trim($line), ";")) {
			continue;
		}

		// State machine to captures various config elements
		switch ($state) {

			// Record the CA
			case CA :
				if (startsWith($line, "</ca>")) {
					$state = CMDS;
				} else {
					$ovpn['ca'] .= ($line . "\n");
				}

				break;

			// Record the cert
			case CERT :
				if (startsWith($line, "</cert>")) {
					$state = CMDS;
				} else {
					$ovpn['cert'] .= ($line . "\n");
				}

				break;

			// Record the key
			case KEY :
				if (startsWith($line, "</key>")) {
					$state = CMDS;
				} else {
					$ovpn['key'] .= ($line . "\n");
				}

				break;

			// Record tls-auth key
			case TLSAUTH :
				if (startsWith($line, "</tls-auth>")) {
					$state = CMDS;
				} else {
					$ovpn['tls'] .= ($line . "\n");
				}

				break;

			// Process one-line coptions
			default :
				interpretLine($line, $state, $ovpn);
				break;
		}
	}

	return $ovpn;
}

/*
 * Interpret the line items defined in the .ovpn file into a local array $ovpn
 * Items are divided into three classes:
 *  Line items to ignore
 *  Line items that are recognized commands to be interpreted and saved
 *  Everything else, which goes into the custom array
 *
 * Cases are listed in alphabetical order
 */
function interpretLine($line, &$state, &$ovpn) {
	$parts = preg_split('/\s+/', $line);
	$len = count($parts);

	switch ($parts[0]) {
		// Ignore these for now
		case  "" :
		case  "auth-user-pass" :
		case  "compat-names" :
		case  "key-method" :
		case  "no-iv" :
		case  "no-name-remapping" :
		case  "no-replay" :
		case  "nobind":
		case  "persist-key" :
		case  "persist-tun" :
		case  "ping-timer-rem" :
		case  "pull" :
		case  "redirect-gateway" :
		case  "resolv-retry" :
		case  "tls-client" :
		case  "tun-ipv6" :
		break;

		case "auth" :
			$ovpn['digest'] = strtoupper($parts[1]);
		break;

		case "auth-retry" :
			if ($parts[1] && ($parts[1] == "nointeract")) {
				$ovpn['auth-retry-none'] = "yes";
			}

		break;

		case  "auth-user-pass" :
			$ovpn['auth-user-pass'] = true;
		break;

		case "<ca>" :
			$state = CA;
		break;

		case "<cert>" :
			$state = CERT;
		break;

		case "cipher" :
		case "data-ciphers-fallback" :
			$ovpn['data_ciphers_fallback'] = strtoupper($parts[1]);
		break;

		case "client" :
			$ovpn['client'] = true;
		break;

		case "comp-lzo" :
			if (count($parts) > 1) {
				$ovpn['custom'] .= ($line . "\n");
			} else {
				$ovpn['custom'] .= ("comp-lzo no\n");
			}

		break;

		case "compress" :
		case "comp-lzo" :
			if ($parts[1] && ($parts[1] != "no")) {
				$ovpn['allow_compression'] = "yes";
			}

		break;

		case "create_gw" :
			$ovpn['create_gw'] = $parts[1];
		break;

		case "data-ciphers" :
		case "ncp-ciphers" :
			// colon separated list
			$ovpn['data_ciphers'] = array();
			$c = explode(":", $parts[1]);
			foreach ($c as $cipher) {
				$ovpn['data_ciphers'][] = strtoupper($cipher);
			}

		break;

		case "dev" :
			if ($len > 1) {
				$ovpn['dev_mode'] = $parts[1];
			}
		break;

		case "dev_mode" :
			$ovpn['dev_mode'] = $parts[1];
		break;

		case "explicit-exit-notify" :
			$ovpn['exit_notify'] = $parts[1];
		break;

		case  "fast-io" :
			$ovpn['udp_fast_io'] = "yes";
		break;

		case "http-proxy" :
			$ovpn['proxy_addr'] = $parts[1];
			$ovpn['proxy_port'] = $parts[2];

			if(count($parts) > 2) {
				$ovpn['proxy_authtype'] = $parts[4];

				if($parts[4] == "basic") {
					$ovpn['proxy_user'] = $parts[5];
					$ovpn['proxy_passwd'] = $parts[6];
				}
			} else {
				$ovpn['proxy_authtype'] = "none";
			}

		break;

		case "ifconfig" :
			if (!isset($ovpn['ifconfig'])) {
				$ovpn['ifconfig'] = array();
			}

			$ovpn['ifconfig'][] = array($parts[1], $parts[2]);

		break;

		case "ifconfig-ipv6" :
			if (!isset($ovpn['ifconfig-ipv6'])) {
				$ovpn['ifconfig-ipv6'] = array();
			}

			$ovpn['ifconfig-ipv6'][] = $parts[1];

		break;

		case "inactive" :
			$ovpn['inactive_seconds'] = $parts[1];
		break;

		case "keepalive" :
			$ovpn['ping_method'] = "keepalive";
			$ovpn['keepalive_interval'] = $parts[1];
			$ovpn['keepalive_timeout'] = $parts[2];
		break;

		case "<key>" :
			$state = KEY;
		break;

		case "key-direction" :
			if ($len > 1) {
				$ovpn['tlsauth_keydir'] = $parts[1];
			}
		break;

		case "lport" :
			$ovpn['local_port'] = $parts[1];
		break;

		case "ncp-disable" :
			$ovpn['ncp_disable'] = "yes";
		break;

		case "passtos" :
			$ovpn['passtos'] = "yes";
		break;

		case "ping" :
			$ovpn['ping_method'] = "ping";
			$ovpn['ping_seconds'] = $parts[1];
		break;

		case "ping-exit" :
			$ovpn['ping_action'] = "ping_exit";
			$ovpn['ping_action_seconds'] = $parts[1];
		break;

		case "ping-restart" :
			$ovpn['ping_action'] = "ping_restart";
			$ovpn['ping_action_seconds'] = $parts[1];
		break;

		case "proto" :
			$ovpn['protocol'] = strtoupper($parts[1]);
		break;

		case "rcvbuf" :
			$ovpn['rcvbuf'] = $parts[1];
		break;

		// The first "remote" is used as the address, the rest go into "custom"
		case "remote" :
			if ($len > 1) {
				if (strlen($ovpn['server_addr']) == 0) {
					$ovpn['server_addr'] = $parts[1];
					$ovpn['server_port'] = $parts[2];
				} else {
					$ovpn['custom'] .= (trim($line) . "\n");
				}
			}

		break;

		case "route" :
			if (!isset($ovpn['route'])) {
				$ovpn['route'] = array();
			}

			// Is it in CIDR format?
			if (is_subnet($parts[1])) {
				$ovpn['route'][] = $parts[1];
			} else {
				$ovpn['route'][] = $parts[1] . "/" . mask2subnet($parts[2]);
			}

		break;

		case "route-ipv6" :
			if (!isset($ovpn['route-ipv6'])) {
				$ovpn['route-ipv6'] = array();
			}

			$ovpn['route-ipv6'][] = $parts[1];

		break;

		case "route-noexec" :
			$ovpn['route_no_exec'] = "yes";
		break;

		case "route-nopull" :
			$ovpn['route_no_pull'] = "yes";
		break;

		case "server-poll-timeout" :
			if ($len > 1) {
				$ovpn['timeout'] = $parts[1];
			}
		break;

		case "sndbuf" :
			$ovpn['sndbuf'] = $parts[1];
		break;

		case "shaper" :
			$ovpn['use_shaper'] = $parts[1];
		break;

		case "tls-auth" :
			$ovpn['tls_type'] = "auth";
		break;

		case "<tls-auth>" :
			$state = TLSAUTH;
		break;

		case "tls-crypt" :
			$ovpn['tls_type'] = "crypt";
		break;

		case "topology" :
			$ovpn['topology'] = $parts[1];
		break;

		case "verb" :
			$ovpn['verbosity_level'] = $parts[1];
		break;

		/* Replace deprecated options
		 * These cases fall though.
		 * see https://community.openvpn.net/openvpn/wiki/DeprecatedOptions */
		case "ifconfig-pool-linear" :
			$line = "topology p2p";

		case "client-cert-not-required" :
			$line = "verify-client-cert none";

		case "tls-remote" :
			$line = "verify-x509-name" . $parts[1];

		// If this option is unknown, add it to the custom option list
		default:
			$ovpn['custom'] .= (trim($line) . "\n");
		break;
	}
}

function startsWith($string, $startString) {
	return (substr($string, 0, strlen($startString)) === $startString);
}

// Map ovpn settings to pfSense settings where required, mostly to accommodate default values
function mapOvpnConfig($ovpn, $name, $username, $password, $caref, $certrefid, $interface, $mode, $disable) {
	// Make a local copy of the proposed config
	$client = $ovpn;

	// Defaults
	$client['caref'] = $caref;
	$client['certref'] = $certrefid;
	$client['custom_options'] = $ovpn['custom'];
	$client['interface'] = $interface;
	$client['auth_user'] = $username;
	$client['auth_pass'] = $password;
	$client['description'] = $name;
	$client['ncp_key'] = "yes";
	$client['mode'] = $mode;

	if(!isset($ovpn['allow_compression'])) {
		$client['allow_compression'] = "asym";
	}

	if(!isset($ovpn['verbosity_level'])) {
		$client['verbosity_level'] = 1;
	}

	if (strlen($ovpn['tls']) > 0) {
		$client['tlsauth_enable'] = "yes";
	}

	if (isset($ovpn['sndbuf']) || isset($ovpn['rcvbuf'])) {
		$client['sndrcvbuf'] =  $ovpn['sndbuf'] > $ovpn['rcvbuf'] ? $ovpn['sndbuf']:$ovpn['rcvbuf'];
		unset($ovp['sndbuf']);
		unset($client['rcvbuf']);
	}

	if (!isset($client['protocol'])) {
		$client['protocol'] = "UDP4";
	}

	if (!isset($ovpn['topology'])) {
		$client['topology'] = "subnet";
	}

	if (!isset($ovpn['exit_notify'])) {
		$client['exit_notify'] = "none";
	}

	if (!$ovpn['ncp_disable']) {
		$client['ncp_enable'] = "enabled";
	}

	if (!isset($ovpn['tls_type'])) {
		$client['tls_type'] = "auth";
	}

	if (isset($ovpn['route'])) {
		$client['remote_network'] = implode(",", $ovpn['route']);
		unset($client['route']);
	}

	if (isset($ovpn['route-ipv6'])) {
		$client['remote_networkv6'] = implode(",", $ovpn['route-ipv6']);
		unset($client['route-ipv6']);
	}

	if (isset($ovpn['ifconfig'])) {
		$ovpn_ifconfig = array();
		foreach ($ovpn['ifconfig'] as $ifconfig) {
			if ($ovpn['dev_mode'] == 'tap') {
				$bits = mask2subnet($ifconfig[1]);
				$ovpn_ifconfig[] = gen_subnetv4($ifconfig[0], intval($bits)) . "/" . $bits;
			} else {
				// trying to find the smallest network:
				$subnet_part = long2ip(ip2long($ifconfig[1]) - 1);
				for ($i = 30; $i >= 1; $i--) {
					$subnet = $subnet_part . "/" . $i;
					if (ip_in_subnet($ifconfig[0], $subnet)) {
						$ovpn_ifconfig[] = $subnet;
						break;
					}
				}
			}
		}

		$client['tunnel_network'] = implode(",", $ovpn_ifconfig);
		unset($client['ifconfig']);
	}

	if (isset($ovpn['ifconfig-ipv6'])) {
		$ovpn_ifconfig6 = array();
		foreach ($ovpn['ifconfig-ipv6'] as $ifconfig6) {
			if ($ovpn['dev_mode'] == 'tap') {
				list($prefix, $bits) = explode('/', $ifconfig6);
				$ovpn_ifconfig6[] = merge_ipv6_delegated_prefix($prefix, '::', $bits) . "/" . $bits;
			} else {
				// use default /64 netmask
				$ovpn_ifconfig6[] = merge_ipv6_delegated_prefix($ifconfig6, '::', 64) . "/64";
			}
		}
			
		$client['tunnel_networkv6'] = implode(",", $ovpn_ifconfig6);
		unset($client['ifconfig-ipv6']);
	}

	if (!isset($client['create_gw'])) {
		$client['create_gw'] = "both";
	}

	if (isset($disable)) {
		$client['disable'] = "yes";
	}

	return $client;
}

/*
 * This code is largely copied from vpn_openvpn_client.php, system_certmanager.php and system_camanager.php
 * It would have been nicer to move the code into a .inc file and call require on it as needed,
 * but that would have broken backwards compatibility.
 *
 * For the most part, these functions have been made modular with very little reliance on global vars.
 * Please keep it that way! Let functions receive arguments and return results.
 */

// Save an imported OpenVPN CA
function save_OVPN_CA($cert, $descr, $trust, $randomserial) {
	global $config;

	init_config_arr(array('ca'));
	$a_ca = &$config['ca'];

	$ca = array();
	$ca['refid'] = uniqid();
	$ca['descr'] = $descr;
	$ca['trust'] = $trust;
	$ca['randomserial'] = $randomserial;

	ca_import($ca, $cert);
	$a_ca[] = $ca;

	return $ca['refid'];
}

// Save the provided cert and key using the provided description/name
function save_OVPN_CERT($newcert, $key, $name) {
	global $config, $input_errors;

	$input_errors = array();
	$cert = array();
	init_config_arr(array('cert'));
	$a_cert = &$config['cert'];

	if ($newcert && (!strstr($newcert, "BEGIN CERTIFICATE") || !strstr($newcert, "END CERTIFICATE"))) {
		$input_errors[] = gettext("This certificate does not appear to be valid.");
	}

	if ($key && (cert_get_publickey($newcert, false) != cert_get_publickey($key, false, 'prv'))) {
		$input_errors[] = gettext("The submitted private key does not match the submitted certificate data.");
	}

	/* Make sure we do not have invalid characters in the fields for the certificate */
	if (preg_match("/[\?\>\<\&\/\\\"\']/", $name)) {
		$input_errors[] = gettext("The field 'Descriptive Name' contains invalid characters.");
	}

	$cert['descr'] = $name;
	$cert['refid'] = uniqid();

	cert_import($cert, $newcert, $key);
	$a_cert[] = $cert;

	return $cert['refid'];
}

// Functionality largely grabbed from vpn_openvpn_client.php but modularized and organzied
function saveOpenVPNTunnel($post) {
	global $input_errors;
	global $config, $openvpn_topologies, $openvpn_tls_modes;
	global $openvpn_exit_notify_client, $openvpn_ping_method, $openvpn_ping_action;
	global $simplefields;

	$simplefields = array('auth_user', 'auth_pass', 'proxy_user', 'proxy_passwd');

	init_config_arr(array('openvpn', 'openvpn-client'));
	$a_client = &$config['openvpn']['openvpn-client'];

	$input_errors = array();
	$vpnid = 0;

	$post['ncp_enable'] = ($post['ncp_enable'] == 'yes') ? 'enabled' : 'disabled';

	$cipher_validation_list = array_keys(openvpn_get_cipherlist());
	if (!in_array($post['data_ciphers_fallback'], $cipher_validation_list)) {
		$input_errors[] = gettext("The selected Fallback Data Encryption Algorithm is not valid.");
	}

	/* Maximum option line length = 256, see https://redmine.pfsense.org/issues/11559 */
	if (!empty($post['data_ciphers']) && (strlen("data-ciphers " . implode(",", $post['data_ciphers'])) > 254)) {
		$input_errors[] = gettext("Too many Data Encryption Algorithms have been selected.");
	}

	list($iv_iface, $iv_ip) = explode ("|", $post['interface']);
	if (is_ipaddrv4($iv_ip) && (stristr($post['protocol'], "6") !== false)) {
		$input_errors[] = gettext("Protocol and IP address families do not match. An IPv6 protocol and an IPv4 IP address cannot be selected.");
	} elseif (is_ipaddrv6($iv_ip) && (stristr($post['protocol'], "6") === false)) {
		$input_errors[] = gettext("Protocol and IP address families do not match. An IPv4 protocol and an IPv6 IP address cannot be selected.");
	} elseif ((stristr($post['protocol'], "6") === false) && !get_interface_ip($iv_iface) && ($post['interface'] != "any")) {
		// If an underlying interface to be used by this client uses DHCP, then it may not have received an IP address yet.
		// So in that case we do not report a problem.
		if (!interface_has_dhcp($iv_iface, 4)) {
			$input_errors[] = gettext("An IPv4 protocol was selected, but the selected interface has no IPv4 address.");
		}
	} elseif ((stristr($post['protocol'], "6") !== false) && !get_interface_ipv6($iv_iface) && ($post['interface'] != "any")) {
		// If an underlying interface to be used by this client uses DHCP6, then it may not have received an IP address yet.
		// So in that case we do not report a problem.
		if (!interface_has_dhcp($iv_iface, 6)) {
			$input_errors[] = gettext("An IPv6 protocol was selected, but the selected interface has no IPv6 address.");
		}
	}

	if ($post['mode'] != "p2p_shared_key") {
		$tls_mode = true;
	} else {
		$tls_mode = false;
	}

	/* input validation */
	if ($post['local_port']) {
		if ($result = openvpn_validate_port($post['local_port'], 'Local port')) {
			$input_errors[] = $result;
		}

		$portused = openvpn_port_used($post['protocol'], $post['interface'], $post['local_port'], $vpnid);
		if (($portused != $vpnid) && ($portused != 0)) {
			$input_errors[] = gettext("The specified 'Local port' is in use. Please select another value");
		}
	}

	if ($result = openvpn_validate_host($post['server_addr'], 'Server host or address')) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_port($post['server_port'], 'Server port')) {
		$input_errors[] = $result;
	}

	if (!array_key_exists($post['topology'], $openvpn_topologies)) {
		$input_errors[] = gettext("The field 'Topology' contains an invalid selection");
	}

	if ($post['proxy_addr']) {

		if ($result = openvpn_validate_host($post['proxy_addr'], 'Proxy host or address')) {
			$input_errors[] = $result;
		}

		if ($result = openvpn_validate_port($post['proxy_port'], 'Proxy port')) {
			$input_errors[] = $result;
		}

		if ($post['proxy_authtype'] != "none") {
			if (empty($post['proxy_user']) || empty($post['proxy_passwd'])) {
				$input_errors[] = gettext("User name and password are required for proxy with authentication.");
			}
		}
	}

	if ($post['tunnel_network']) {
		if ($result = openvpn_validate_cidr($post['tunnel_network'], 'IPv4 Tunnel Network', false, "ipv4")) {
			$input_errors[] = $result;
		}
		if ((!isset($a_client[$id]) ||
		    ($a_client[$id]['tunnel_network'] != $post['tunnel_network'])) &&
		    openvpn_is_tunnel_network_in_use($post['tunnel_network'])) {
			$input_errors[] = gettext("The submitted IPv4 Tunnel Network is already in use.");
		}
	}

	if ($post['tunnel_networkv6']) {
		if ($result = openvpn_validate_cidr($post['tunnel_networkv6'], 'IPv6 Tunnel Network', false, "ipv6")) {
			$input_errors[] = $result;
		}
		if ((!isset($a_client[$id]) ||
		    ($a_client[$id]['tunnel_networkv6'] != $post['tunnel_networkv6'])) &&
		    openvpn_is_tunnel_network_in_use($post['tunnel_networkv6'])) {
			$input_errors[] = gettext("The submitted IPv6 Tunnel Network is already in use.");
		}
	}

	if ($result = openvpn_validate_cidr($post['remote_network'], 'IPv4 Remote Network', true, "ipv4")) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($post['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6")) {
		$input_errors[] = $result;
	}

	if (!empty($post['use_shaper']) && (!is_numeric($post['use_shaper']) || ($post['use_shaper'] <= 0))) {
		$input_errors[] = gettext("The bandwidth limit must be a positive numeric value.");
	}

	if ($post['autokey_enable']) {
		$post['shared_key'] = openvpn_create_key();
	}

	if (!$tls_mode && !$post['autokey_enable']) {
		if (!strstr($post['shared_key'], "-----BEGIN OpenVPN Static key V1-----") ||
		    !strstr($post['shared_key'], "-----END OpenVPN Static key V1-----")) {
			$input_errors[] = gettext("The field 'Shared Key' does not appear to be valid");
		}
	}

	if ($tls_mode && $post['tlsauth_enable'] && !$post['autotls_enable']) {
		if (!strstr($post['tls'], "-----BEGIN OpenVPN Static key V1-----") ||
		    !strstr($post['tls'], "-----END OpenVPN Static key V1-----")) {
			$input_errors[] = gettext("The field 'TLS Key' does not appear to be valid");
		}
		if (!in_array($post['tls_type'], array_keys($openvpn_tls_modes))) {
			$input_errors[] = gettext("The field 'TLS Key Usage Mode' is not valid");
		}
	}

	if (($post['mode'] == "p2p_shared_key") && strstr($post['data_ciphers_fallback'], "GCM")) {
		$input_errors[] = gettext("GCM Encryption Algorithms cannot be used with Shared Key mode.");
	}

	/* If we are not in shared key mode, then we need the CA/Cert. */
	if ($post['mode'] != "p2p_shared_key") {
		if (($post['ncp_enable'] != "disabled") && !empty($post['data_ciphers']) && is_array($post['data_ciphers'])) {
			foreach ($post['data_ciphers'] as $dc) {
				if (!in_array(trim($dc), $cipher_validation_list)) {
					$input_errors[] = gettext("One or more of the selected Data Encryption Algorithms is not valid.");
				}
			}
		}
		$reqdfields = explode(" ", "caref");
		$reqdfieldsn = array(gettext("Certificate Authority"));
	} elseif (!$post['autokey_enable']) {
		/* We only need the shared key filled in if we are in shared key mode and autokey is not selected. */
		$reqdfields = array('shared_key');
		$reqdfieldsn = array(gettext('Shared key'));
	}

	//do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);

	if ($post['mode'] != "p2p_shared_key") {
		if (empty($post['certref']) && empty($post['auth_user']) && empty($post['auth_pass'])) {
			$input_errors[] = gettext("If no Client Certificate is selected, a username and/or password must be entered.");
		}
	}

	/* UDP Fast I/O and Exit Notify are not compatible with TCP, so toss the
	 * option out when submitted since it can't be set this way
	 * legitimately. This also avoids having to perform any more trickery on
	 * the stored option to not preserve the value when changing modes. */
	if (strtolower(substr($post['protocol'], 0, 3)) != "udp") {
		if ($post['udp_fast_io']) {
			unset($post['udp_fast_io']);
		}
		if ($post['exit_notify']) {
			unset($post['exit_notify']);
		}
	} else {
		if (!array_key_exists($post['exit_notify'], $openvpn_exit_notify_client)) {
			$input_errors[] = gettext("The Exit Notify value is invalid.");
		}
	}

	if ($post['udp_fast_io'] && (!empty($post['use_shaper']))) {
		/* Only warn if the user is set to UDP, otherwise it isn't relevant (See above) */
		if (strtolower(substr($post['protocol'], 0, 3)) == "udp") {
			$input_errors[] = gettext("Limit Outgoing Bandwidth is not compatible with UDP Fast I/O.");
		} else {
			unset($post['udp_fast_io']);
		}
	}

	if (!empty($post['sndrcvbuf']) && !array_key_exists($post['sndrcvbuf'], openvpn_get_buffer_values())) {
		$input_errors[] = gettext("The supplied Send/Receive Buffer size is invalid.");
	}

	if (!empty($post['ping_method']) && !array_key_exists($post['ping_method'], $openvpn_ping_method)) {
		$input_errors[] = gettext("The supplied Ping Method is invalid.");
	}
	if (!empty($post['ping_action']) && !array_key_exists($post['ping_action'], $openvpn_ping_action)) {
		$input_errors[] = gettext("The supplied Ping Action is invalid.");
	}
	if (!empty($post['keepalive_interval']) && !is_numericint($post['keepalive_interval'])) {
		$input_errors[] = gettext("The supplied Keepalive Interval value is invalid.");
	}
	if (!empty($post['keepalive_timeout']) && !is_numericint($post['keepalive_timeout'])) {
		$input_errors[] = gettext("The supplied Keepalive Timeout value is invalid.");
	}
	if (!empty($post['ping_seconds']) && !is_numericint($post['ping_seconds'])) {
		$input_errors[] = gettext("The supplied Ping Seconds value is invalid.");
	}
	if (!empty($post['ping_action_seconds']) && !is_numericint($post['ping_action_seconds'])) {
		$input_errors[] = gettext("The supplied Ping Restart or Exit Seconds value is invalid.");
	}
	if (!empty($post['inactive_seconds']) && !is_numericint($post['inactive_seconds'])) {
		$input_errors[] = gettext("The supplied Inactive Seconds value is invalid.");
	}

	if ($input_errors) {
		// Remove any CAs or certs that were created
		if (strlen($post['caref']) > 0) {
			deleteCA($post['caref']);
		}

		if (strlen($post['certref']) > 0) {
			deleteCert($post['certref']);
		}
	} else {
		$client = array();

		if (isset($id) && $a_client[$id] &&
		    $post['dev_mode'] <> $a_client[$id]['dev_mode']) {
			/*
			 * delete old interface so a new TUN or TAP interface
			 * can be created.
			 */
			openvpn_delete('client', $a_client[$id]);
		}

		foreach ($simplefields as $stat) {
			if ($post[$stat] == DMYPWD) {
				if (is_numeric($post['parentid'])) {
					$orig_id = $post['parentid'];
				} else {
					$orig_id = $id;
				}
				$client[$stat] = $a_client[$orig_id][$stat];
			} else {
				update_if_changed($stat, $client[$stat], $post[$stat]);
			}
		}

		if ($vpnid) {
			$client['vpnid'] = $vpnid;
		} else {
			$client['vpnid'] = openvpn_vpnid_next();
		}

		if ($post['disable'] == "yes") {
			$client['disable'] = true;
		}

		$client['protocol'] = $post['protocol'];
		$client['dev_mode'] = $post['dev_mode'];
		list($client['interface'], $client['ipaddr']) = explode ("|", $post['interface']);
		$client['local_port'] = $post['local_port'];
		$client['server_addr'] = $post['server_addr'];
		$client['server_port'] = $post['server_port'];
		$client['proxy_addr'] = $post['proxy_addr'];
		$client['proxy_port'] = $post['proxy_port'];
		$client['proxy_authtype'] = $post['proxy_authtype'];
		$client['description'] = $post['description'];
		$client['mode'] = $post['mode'];
		$client['topology'] = $post['topology'];
		$client['custom_options'] = str_replace("\r\n", "\n", $post['custom_options']);

		if ($tls_mode) {
			$client['caref'] = $post['caref'];
			$client['certref'] = $post['certref'];
			$client['crlref'] = $post['crlref'];
			if ($post['tlsauth_enable']) {
				if ($post['autotls_enable']) {
					$post['tls'] = openvpn_create_key();
				}
				$client['tls'] = base64_encode($post['tls']);
				$client['tls_type'] = $post['tls_type'];
				$client['tlsauth_keydir'] = $post['tlsauth_keydir'];
			}
		} else {
			$client['shared_key'] = base64_encode($post['shared_key']);
		}
		$client['data_ciphers_fallback'] = $post['data_ciphers_fallback'];
		$client['digest'] = $post['digest'];
		$client['engine'] = 'none';

		$client['tunnel_network'] = $post['tunnel_network'];
		$client['tunnel_networkv6'] = $post['tunnel_networkv6'];
		$client['remote_network'] = $post['remote_network'];
		$client['remote_networkv6'] = $post['remote_networkv6'];
		$client['use_shaper'] = $post['use_shaper'];
		$client['allow_compression'] = $post['allow_compression'];
		$client['compression'] = $post['compression'];
		$client['auth-retry-none'] = $post['auth-retry-none'];
		$client['passtos'] = $post['passtos'];
		$client['udp_fast_io'] = $post['udp_fast_io'];
		$client['exit_notify'] = $post['exit_notify'];
		$client['sndrcvbuf'] = $post['sndrcvbuf'];

		$client['route_no_pull'] = $post['route_no_pull'];
		$client['route_no_exec'] = $post['route_no_exec'];
		$client['dns_add'] = $post['dns_add'];
		$client['verbosity_level'] = $post['verbosity_level'];
		$client['create_gw'] = $post['create_gw'];

		if (!empty($post['data_ciphers'])) {
			$client['data_ciphers'] = implode(",", $post['data_ciphers']);
		}

		$client['ncp_enable'] = $post['ncp_enable'];

		$client['ping_method'] = $post['ping_method'];
		$client['keepalive_interval'] = $post['keepalive_interval'];
		$client['keepalive_timeout'] = $post['keepalive_timeout'];
		$client['ping_seconds'] = $post['ping_seconds'];
		$client['ping_action'] = $post['ping_action'];
		$client['ping_action_seconds'] = $post['ping_action_seconds'];
		$client['inactive_seconds'] = $post['inactive_seconds'];


		$a_client[] = $client;
		$wc_msg = sprintf(gettext('Added OpenVPN client to server %1$s:%2$s "%3$s"'), $client['server_addr'], $client['server_port'], $client['description']);

		if (strlen($carefid) > 0) {
			ca_setup_trust_store();
		}

		write_config($wc_msg);
		openvpn_resync('client', $client);
	}
/*
	if (!empty($post['data_ciphers'])) {
		$post['data_ciphers'] = implode(",", $post['data_ciphers']);
	}
*/
	return $wc_msg;
}

// Check if a CA eists with this name
function CAExists($name) {
	global $config;

	if (!isset($config['ca'])) {
		return false;
	}

	$name .= "_CA";
	$a_ca = &$config['ca'];

	foreach ($a_ca as $ca) {
		if ($ca['descr'] == $name ) {
			return true;
		}
	}

	return false;
}

// Check if a cert exists with this name
function certExists($name) {
	global $config;

	if (!isset($config['cert'])) {
		return false;
	}

	$name .= "_CERT";
	$a_cert = &$config['cert'];

	foreach ($a_cert as $cert) {
		if ($cert['descr'] == $name) {
			return true;
		}
	}

	return false;
}

// Delete a CA by reference ID because the OpenVPN config that created it had errors
function deleteCA($ref) {
	global $config;

	$a_ca = &$config['ca'];
	$i = 0;

	foreach ($a_ca as $ca) {
		if ($ca['refid'] == $ref ) {
			unset($a_ca[$i]);
		}

	$i++;
	}
}

// Delete a Cert by reference ID because the OpenVPN config that created it had errors
function deleteCert($ref) {
	global $config;

	$a_cert = &$config['cert'];
	$i = 0;

	foreach ($a_cert as $cert) {
		if ($cert['refid'] == $ref ) {
			unset($a_cert[i]);
		}

	$i++;
	}
}

// Convert a 255.255.255.255 style mask string to a subnet value, E.g.: 32
function mask2subnet($mask) {
	$long = ip2long($mask);
	$base = ip2long('255.255.255.255');

	return 32-log(($long ^ $base)+1,2);
}

?>
