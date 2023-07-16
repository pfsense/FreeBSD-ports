<?php
/*
 * openvpn-client-export.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2008 Shrew Soft Inc
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

require_once("globals.inc");
require_once("openvpn.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");

global $current_openvpn_version, $current_openvpn_version_rev;
$current_openvpn_version = "2.6.5";
$current_openvpn_version_rev = "001";

global $previous_openvpn_version, $previous_openvpn_version_rev;
$previous_openvpn_version = "2.5.9";
$previous_openvpn_version_rev = "601";

global $legacy_openvpn_version, $legacy_openvpn_version_rev;
$legacy_openvpn_version = "2.4.12";
$legacy_openvpn_version_rev = "601";

/* Ciphers not supported by legacy clients */
$legacy_incompatible_ciphers = array("CHACHA20-POLY1305");

function get_openvpnserver_by_id($srvid) {
	global $config;
	foreach($config['openvpn']['openvpn-server'] as $server) {
		if ($server['vpnid'] == $srvid) {
			return $server;
		}
	}
	return null;
}

function openvpn_client_export_prefix($srvid, $usrid = null, $crtid = null) {
	global $config;

	// lookup server settings
	$settings = get_openvpnserver_by_id($srvid);
	if (empty($settings)) {
		return false;
	}
	if ($settings['disable']) {
		return false;
	}
	$host = empty($config['system']['hostname']) ? "openvpn" : $config['system']['hostname'];
	$prot = ($settings['protocol'] == 'UDP' ? 'udp' : $settings['protocol']);
	$port = $settings['local_port'];

	$filename_addition = "";
	if (isset($usrid) && is_numeric($usrid)) {
		$filename_addition = "-" . $config['system']['user'][$usrid]['name'];
	} elseif (isset($crtid) && is_numeric($crtid)) {
		$filename_addition = "-" . str_replace(' ', '_', cert_get_cn($config['cert'][$crtid]['crt']));
	}
	return "{$host}-{$prot}-{$port}{$filename_addition}";
}

function openvpn_client_pem_to_pk12($outpath, $outpass, $p12encryption, $crtpath, $keypath, $descr = "", $capath = false) {
	$eoutpath = escapeshellarg($outpath);
	$eoutpass = escapeshellarg($outpass);
	$ecrtpath = escapeshellarg($crtpath);

	if ($keypath) {
		$key = '-inkey ' . escapeshellarg($keypath);
	} else {
		$key = '-nokeys';
	}

	/* Encryption and Digest */
	switch ($p12encryption) {
		case 'legacy':
			$algo = '-certpbe PBE-SHA1-RC2-40 -keypbe PBE-SHA1-RC2-40';
			$hash = '';
			break;
		case 'low':
			$algo = '-certpbe PBE-SHA1-3DES -keypbe PBE-SHA1-3DES';
			$hash = '-macalg SHA1';
			break;
		case 'high':
		default:
			$algo = '-aes256 -certpbe AES-256-CBC -keypbe AES-256-CBC';
			$hash = '-macalg sha256';
	}

	$tempdir = $g['tmp_path'] . "/openvpn-export-p12-".uniqid();
	mkdir($tempdir, 0700, true);

	/* Set the friendly name if available */
	$edescr = "";
	if (!empty($descr)) {
		$edescr = escapeshellarg($descr);
		$edescr = "-name {$edescr} -CSP {$edescr}";
	}

	/* Passphrase handling */
	if (!empty($outpass)) {
		/* Use passphrase text file so the passphrase is not visible in
		 * process list. */
		$passfile = "{$tempdir}/passphrase.txt";
		file_put_contents($passfile, $outpass . "\n");
		$pass = '-passout file:' . escapeshellarg($passfile);
	} else {
		/* Null password + disable encryption of the keys */
		$pass = '-passout pass: -nodes';
	}

	if ($capath) {
		$ecapath = escapeshellarg($capath);
		exec("/usr/bin/openssl pkcs12 -export -in {$ecrtpath} {$key} -certfile {$ecapath} -out {$eoutpath} {$pass} {$edescr} {$algo} {$hash}");
	} else {
		exec("/usr/bin/openssl pkcs12 -export -in {$ecrtpath} {$key} -out {$eoutpath} {$pass} {$edescr} {$algo} {$hash}");
	}

	unlink($crtpath);
	unlink_if_exists($keypath);
	if ($capath) {
		unlink($capath);
	}
	@rmdir_recursive($tempdir);
}

function openvpn_client_export_validate_config($srvid, $usrid, $crtid) {
	global $config, $input_errors;

	// lookup server settings
	$settings = get_openvpnserver_by_id($srvid);
	if (empty($settings)) {
		$input_errors[] = "Could not locate server configuration.";
		return false;
	}
	if ($settings['disable']) {
		$input_errors[] = "You cannot export for disabled servers.";
		return false;
	}

	// lookup server certificate info
	$server_cert = lookup_cert($settings['certref']);
	if (!$server_cert) {
		$input_errors[] = "Could not locate server certificate.";
	} else {
		$server_ca = ca_chain($server_cert);
		if (!$server_ca) {
			$input_errors[] = "Could not locate the CA reference for the server certificate.";
		}
		$servercn = cert_get_cn($server_cert['crt']);
	}

	// lookup user info
	if (is_numeric($usrid)) {
		$user = $config['system']['user'][$usrid];
		if (!$user) {
			$input_errors[] = "Could not find user settings.";
		}
	}

	// lookup user certificate info
	if ($settings['mode'] == "server_tls_user") {
		if ($settings['authmode'] == "Local Database") {
			$cert = $user['cert'][$crtid];
		} else {
			$cert = $config['cert'][$crtid];
		}
		if (!$cert) {
			$input_errors[] = "Could not find client certificate.";
		} else {
			// If $cert is not an array, it's a certref not a cert.
			if (!is_array($cert)) {
				$cert = lookup_cert($cert);
			}
		}
	} elseif (($settings['mode'] == "server_tls") || (($settings['mode'] == "server_tls_user") && ($settings['authmode'] != "Local Database"))) {
		$cert = $config['cert'][$crtid];
		if (!$cert) {
			$input_errors[] = "Could not find client certificate.";
		}
	} else {
		$nokeys = true;
	}

	if ($input_errors) {
		return false;
	}
	return array($settings, $server_cert, $server_ca, $servercn, $user, $cert, $nokeys);
}

function openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $bindmode, $usetoken, $nokeys = false, $proxy, $expformat = "baseconf", $outpass = "", $p12encryption, $skiptls = false, $doslines = false, $advancedoptions = "", $usepkcs11, $pkcs11providers, $pkcs11id) {
	global $input_errors, $g, $legacy_incompatible_ciphers;

	$validconfig = openvpn_client_export_validate_config($srvid, $usrid, $crtid);
	if ($validconfig) {
		list($settings, $server_cert, $server_ca, $servercn, $user, $cert, $nokeys) = $validconfig;
	} else {
		return false;
	}
	
	$prefix = openvpn_client_export_prefix($srvid, $usrid, $crtid);

	$nl = ($doslines) ? "\r\n" : "\n";
	
	$conf = "";
	if ($expformat == "inlinevisc") {
		$friendly_name = $settings['description'];
		$conf .= <<<EOF
#-- Config Auto Generated By pfSense for Viscosity --#

#viscosity startonopen false
#viscosity dhcp true
#viscosity dnssupport true
#viscosity name {$friendly_name}

EOF;
	}
	
	// determine basic variables
	$remotes = openvpn_client_export_build_remote_lines($settings, $useaddr, $interface, $expformat, $nl, $legacy);
	$digest = !empty($settings['digest']) ? $settings['digest'] : "SHA1";

	// add basic settings
	$devmode = empty($settings['dev_mode']) ? "tun" : $settings['dev_mode'];
	if (($expformat != "inlinedroid") && ($expformat != "inlineconnect")) {
		$conf .= "dev {$devmode}{$nl}";
	}

	/* This has been deprecated in OpenVPN 2.4 and removed from OpenVPN 2.5. */
	if ($legacy &&
	    (!empty($settings['tunnel_networkv6']) &&
	    ($expformat != "inlinedroid") &&
	    ($expformat != "inlineconnect"))) {
		$conf .= "tun-ipv6{$nl}";
	}
	$conf .= "persist-tun{$nl}";
	$conf .= "persist-key{$nl}";

	$cipher = $settings['data_ciphers_fallback'];
	$data_ciphers = explode(',', openvpn_build_data_cipher_list($settings['data_ciphers'], $cipher));
	if ($legacy) {
		$data_ciphers = array_diff($data_ciphers, $legacy_incompatible_ciphers);
	}
	$data_ciphers = implode(':', $data_ciphers);

	/* If the data ciphers list is empty, don't specify a list so OpenVPN's default will be used. */
	if (!empty($data_ciphers)) {
		$conf .= (($legacy) ? "ncp-ciphers" : "data-ciphers") . " {$data_ciphers}{$nl}";
	}
	$conf .= (($legacy) ? "cipher" : "data-ciphers-fallback") . " {$cipher}{$nl}";

	$conf .= "auth {$digest}{$nl}";
	$conf .= "tls-client{$nl}";
	$conf .= "client{$nl}";
	if (($expformat != "inlinedroid") && ($expformat != "inlineconnect")) {
		$conf .= "resolv-retry infinite{$nl}";
	}
	$conf .= "$remotes{$nl}";

	/* Prevent DNS Leaks on Windows 10 by blocking outside access to other
	 * DNS Servers. Using the setenv method is non-fatal to clients which
	 * do not support the option.
	 * See https://redmine.pfsense.org/issues/6719 and https://community.openvpn.net/openvpn/ticket/605
	 */
	if ($blockoutsidedns != 0) {
		$conf .= "setenv opt block-outside-dns{$nl}";
	}

	/* Use a random local port, otherwise two clients will conflict if they run at the same time.
		May not be supported on older clients (Released before May 2010) */
	if ((substr($expformat, 0, 7) != "yealink") && ($expformat != "snom")) {
		if ($bindmode == 'nobind') {
			$conf .= "nobind{$nl}";
		} elseif ($bindmode == 'lport0') {
			$conf .= "lport 0{$nl}";
		}
	}

	/* This line can cause problems with auth-only setups and also with Yealink/Snom phones
		since they are stuck on an older OpenVPN version that does not support this feature. */
	if (!empty($servercn) && !$nokeys) {
		switch ($verifyservercn) {
			case "none":
				break;
			default:
				if ((substr($expformat, 0, 7) != "yealink") && ($expformat != "snom")) {
					$conf .= "verify-x509-name \"{$servercn}\" name{$nl}";
				}
		}
	}

	if (!empty($proxy)) {
		if ($proxy['proxy_type'] == "http") {
			if (strtoupper(substr($settings['protocol'], 0, 3)) == "UDP") {
				$input_errors[] = "This server uses UDP protocol and cannot communicate with HTTP proxy.";
				return;
			}
			$conf .= "http-proxy {$proxy['ip']} {$proxy['port']} ";
		}
		if ($proxy['proxy_type'] == "socks") {
			$conf .= "socks-proxy {$proxy['ip']} {$proxy['port']} ";
		}
		if ($proxy['proxy_authtype'] != "none") {
			if (!isset($proxy['passwdfile'])) {
				$proxy['passwdfile'] = $prefix . "-proxy";
				$conf .= " {$proxy['passwdfile']} {$proxy['proxy_authtype']}";
			}
		}
		$conf .= "{$nl}";
	}

	// add user auth settings
	switch($settings['mode']) {
		case 'server_user':
		case 'server_tls_user':
			$conf .= "auth-user-pass{$nl}";
			break;
	}

	// add key settings
	$cafile = "{$prefix}-ca.crt";
	if ($nokeys == false) {
		if ($expformat == "yealink_t28") {
			$conf .= "ca /yealink/config/openvpn/keys/ca.crt{$nl}";
			$conf .= "cert /yealink/config/openvpn/keys/client1.crt{$nl}";
			$conf .= "key /yealink/config/openvpn/keys/client1.key{$nl}";
		} elseif ($expformat == "yealink_t38g") {
			$conf .= "ca /phone/config/openvpn/keys/ca.crt{$nl}";
			$conf .= "cert /phone/config/openvpn/keys/client1.crt{$nl}";
			$conf .= "key /phone/config/openvpn/keys/client1.key{$nl}";
		} elseif ($expformat == "yealink_t38g2") {
			$conf .= "ca /config/openvpn/keys/ca.crt{$nl}";
			$conf .= "cert /config/openvpn/keys/client1.crt{$nl}";
			$conf .= "key /config/openvpn/keys/client1.key{$nl}";
		} elseif ($expformat == "snom") {
			$conf .= "ca /openvpn/ca.crt{$nl}";
			$conf .= "cert /openvpn/phone1.crt{$nl}";
			$conf .= "key /openvpn/phone1.key{$nl}";
		} elseif ($usepkcs11) {
			$conf .= "ca {$cafile}{$nl}";
			$conf .= "pkcs11-providers '{$pkcs11providers}'{$nl}";
			$conf .= "pkcs11-id '{$pkcs11id}'{$nl}";
		} elseif ($usetoken) {
			$conf .= "ca {$cafile}{$nl}";
			$crt = openssl_x509_parse(base64_decode($cert['crt']));
			$subj = implode(', ', $crt['subject']);
			$conf .= "cryptoapicert \"SUBJ:{$subj}\"{$nl}";
		} elseif (substr($expformat, 0, 6) != "inline") {
			$conf .= "pkcs12 {$prefix}.p12{$nl}";
		}
	} elseif ($settings['mode'] == "server_user") {
		if (substr($expformat, 0, 6) != "inline") {
			$conf .= "ca {$cafile}{$nl}";
		}
	}

	if ($settings['tls'] && !$skiptls) {
		if ($settings['tls_type'] == "crypt") {
			$tls_directive = "tls-crypt";
			$tls_keydir = "";
		} else {
			$tls_directive = "tls-auth";
			$tls_keydir = " 1";
		}

		if ($expformat == "yealink_t28") {
			$conf .= "{$tls_directive} /yealink/config/openvpn/keys/ta.key{$tls_keydir}{$nl}";
		} elseif ($expformat == "yealink_t38g") {
			$conf .= "{$tls_directive} /phone/config/openvpn/keys/ta.key{$tls_keydir}{$nl}";
		} elseif ($expformat == "yealink_t38g2") {
			$conf .= "{$tls_directive} /config/openvpn/keys/ta.key{$tls_keydir}{$nl}";
		} elseif ($expformat == "snom") {
			$conf .= "{$tls_directive} /openvpn/ta.key{$tls_keydir}{$nl}";
		} elseif (substr($expformat, 0, 6) != "inline") {
			$conf .= "{$tls_directive} {$prefix}-tls.key{$tls_keydir}{$nl}";
		}
	}

	// Extra protection against for the server cert, if it's supported
	if (is_array($server_cert) && ($server_cert['crt'])) {
		$crt_details = openssl_x509_parse(base64_decode($server_cert['crt']));
		$eku_list = explode(',', $crt_details['extensions']['extendedKeyUsage']);
		if (in_array('TLS Web Server Authentication', $eku_list)) {
			$conf .= "remote-cert-tls server{$nl}";
		}
	}

	// add optional settings
	$compression = "";
	switch ($settings['compression']) {
		case 'none':
			$settings['compression'] = '';
		case 'lz4':
		case 'lz4-v2':
		case 'lzo':
		case 'stub':
		case 'stub-v2':
			$compression .= "compress {$settings['compression']}";
			break;
		case 'noadapt':
			$compression .= "comp-noadapt";
			break;
		case 'adaptive':
		case 'yes':
		case 'no':
			$compression .= "comp-lzo {$settings['compression']}";
			break;
		default:
			/* Add nothing to the configuration */
			break;
	}

	/* If the server is set to push, don't put a compression line in the client config. */
	if (!empty($compression) && !$settings['compression_push'] &&
	    ((($settings['allow_compression'] != 'no') && !$legacy) || $legacy)) {
		$conf .= "{$compression}\n";
	}

	if ($settings['passtos']) {
		$conf .= "passtos{$nl}";
	}

	if (stristr($settings['protocol'], "udp")) {
		$conf .= "explicit-exit-notify{$nl}";
	}

	// add advanced options
	$advancedoptions = str_replace("\r\n", "\n", $advancedoptions);
	$advancedoptions = str_replace("\n", $nl, $advancedoptions);
	$advancedoptions = str_replace(";", $nl, $advancedoptions);
	$conf .= $advancedoptions;
	$conf .= $nl;

	switch ($expformat) {
		// "zip" creates: "/{$prefix}-config.zip"
		case "zip":
			// create template directory
			$tempdir = "{$g['tmp_path']}/{$prefix}";
			@mkdir($tempdir, 0700, true);

			// write cofiguration file
			if (!empty($proxy) && $proxy['proxy_authtype'] != "none") {
				$pwdfle = "{$proxy['user']}\n";
				$pwdfle .= "{$proxy['password']}\n";
				file_put_contents("{$tempdir}/{$proxy['passwdfile']}", $pwdfle);
			}
			
			file_put_contents("{$tempdir}/{$prefix}.ovpn", $conf);

			$cafile = "{$tempdir}/{$cafile}";
			file_put_contents("{$cafile}", $server_ca);
			if ($settings['tls']) {
				$tlsfile = "{$tempdir}/{$prefix}-tls.key";
				file_put_contents($tlsfile, base64_decode($settings['tls']));
			}

			// write key files
			if ($settings['mode'] != "server_user" && !$usepkcs11) {
				$crtfile = "{$tempdir}/{$prefix}-cert.crt";
				file_put_contents($crtfile, base64_decode($cert['crt']));
				if (!empty($cert['prv'])) {
					$keyfile = "{$tempdir}/{$prefix}.key";
					file_put_contents($keyfile, base64_decode($cert['prv']));
				}

				// convert to pkcs12 format
				$p12file = "{$tempdir}/{$prefix}.p12";
				if ($usetoken) {
					openvpn_client_pem_to_pk12($p12file, $outpass, $p12encryption, $crtfile, $keyfile, $prefix);
				} else {
					openvpn_client_pem_to_pk12($p12file, $outpass, $p12encryption, $crtfile, $keyfile, $prefix, $cafile);
				}
			}
			$command = "cd " . escapeshellarg("{$tempdir}/..")
					. " && /usr/local/bin/zip -r "
					. escapeshellarg("{$g['tmp_path']}/{$prefix}-config.zip")
					. " " . escapeshellarg($prefix);
			exec($command);
			// Remove temporary directory
			exec("rm -rf " . escapeshellarg($tempdir));
			return "{$g['tmp_path']}/{$prefix}-config.zip";
		// creates: conf file content.
		case "inline":
		case "inlinedroid":
		case "inlineconnect":
		case "inlinevisc":
			// Inline CA
			$conf .= "<ca>{$nl}" . trim($server_ca) . "{$nl}</ca>{$nl}";
			if ($settings['mode'] != "server_user") {
				// Inline Cert
				$conf .= "<cert>{$nl}" . trim(base64_decode($cert['crt'])) . "{$nl}</cert>{$nl}";
				// Inline Key
				$conf .= "<key>{$nl}" . trim(base64_decode($cert['prv'])) . "{$nl}</key>{$nl}";
			} else {
				// Work around OpenVPN Connect assuming you have a client cert even when you don't need one
				$conf .= "setenv CLIENT_CERT 0{$nl}";
			}
			// Inline TLS
			if ($settings['tls']) {
				if ($settings['tls_type'] == "crypt") {
					$tls_directive = "tls-crypt";
					$tls_keydir = "";
				} else {
					$tls_directive = "tls-auth";
					$tls_keydir = "key-direction 1{$nl}";
				}
				$conf .= $tls_keydir;
				$conf .= "<{$tls_directive}>{$nl}" . trim(base64_decode($settings['tls'])) . "{$nl}</{$tls_directive}>{$nl}";
			}
			return $conf;
		// "yealink" creates: "/client.tar"
		case "yealink_t28":
		case "yealink_t38g":
		case "yealink_t38g2":
			// create template directory
			$tempdir = "{$g['tmp_path']}/{$prefix}";
			$keydir = "{$tempdir}/keys";
			mkdir($tempdir, 0700, true);
			mkdir($keydir, 0700, true);

			file_put_contents("{$tempdir}/vpn.cnf", $conf);

			$cafile = "{$keydir}/ca.crt";
			file_put_contents("{$cafile}", $server_ca);
			if ($settings['tls']) {
				$tlsfile = "{$keydir}/ta.key";
				file_put_contents($tlsfile, base64_decode($settings['tls']));
			}

			// write key files
			if ($settings['mode'] != "server_user") {
				$crtfile = "{$keydir}/client1.crt";
				file_put_contents($crtfile, base64_decode($cert['crt']));
				$keyfile = "{$keydir}/client1.key";
				file_put_contents($keyfile, base64_decode($cert['prv']));
			}
			exec("tar -C {$tempdir} -cf {$g['tmp_path']}/client.tar ./keys ./vpn.cnf");
			// Remove temporary directory
			exec("rm -rf {$tempdir}");
			return $g['tmp_path'] . "/client.tar";
		// "snom" creates: /vpnclient.tar
		case "snom":
			// create template directory
			$tempdir = "{$g['tmp_path']}/{$prefix}";
			mkdir($tempdir, 0700, true);

			file_put_contents("{$tempdir}/vpn.cnf", $conf);

			$cafile = "{$tempdir}/ca.crt";
			file_put_contents("{$cafile}", $server_ca);
			if ($settings['tls']) {
				$tlsfile = "{$tempdir}/ta.key";
				file_put_contents($tlsfile, base64_decode($settings['tls']));
			}

			// write key files
			if ($settings['mode'] != "server_user") {
				$crtfile = "{$tempdir}/phone1.crt";
				file_put_contents($crtfile, base64_decode($cert['crt']));
				$keyfile = "{$tempdir}/phone1.key";
				file_put_contents($keyfile, base64_decode($cert['prv']));
			}
			exec("cd {$tempdir}/ && tar -cf {$g['tmp_path']}/vpnclient.tar *");
			// Remove temporary directory
			exec("rm -rf {$tempdir}");
			return $g['tmp_path'] . "/vpnclient.tar";
		default:
			return $conf;
	}
}

function openvpn_client_export_installer($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $bindmode, $usetoken, $outpass, $p12encryption, $proxy, $advancedoptions, $openvpn_version = "x64-current", $usepkcs11, $pkcs11providers, $pkcs11id, $silent) {
	global $g, $input_errors;
	global $current_openvpn_version, $current_openvpn_version_rev;
	global $previous_openvpn_version, $previous_openvpn_version_rev;
	global $legacy_openvpn_version, $legacy_openvpn_version_rev;

	switch ($openvpn_version) {
		case "Win7":
			$legacy = true;
			$client_install_exe = "openvpn-install-{$legacy_openvpn_version}-I{$legacy_openvpn_version_rev}-Win7.exe";
			break;
		case "Win10":
			$legacy = true;
			$client_install_exe = "openvpn-install-{$legacy_openvpn_version}-I{$legacy_openvpn_version_rev}-Win10.exe";
			break;
		case "x86-previous":
			$client_install_exe = "OpenVPN-{$previous_openvpn_version}-I{$previous_openvpn_version_rev}-x86.msi";
			break;
		case "x64-previous":
			$client_install_exe = "OpenVPN-{$previous_openvpn_version}-I{$previous_openvpn_version_rev}-amd64.msi";
			break;
		case "x86-current":
			$client_install_exe = "OpenVPN-{$current_openvpn_version}-I{$current_openvpn_version_rev}-x86.msi";
			break;
		case "x64-current":
		default:
			$client_install_exe = "OpenVPN-{$current_openvpn_version}-I{$current_openvpn_version_rev}-amd64.msi";
			break;
	}

	$ovpndir = "/usr/local/share/openvpn";
	$workdir = "{$ovpndir}/client-export";

	$validconfig = openvpn_client_export_validate_config($srvid, $usrid, $crtid);
	if ($validconfig) {
		list($settings, $server_cert, $server_ca, $servercn, $user, $cert, $nokeys) = $validconfig;
	} else {
		return false;
	}

	// create template directory
	$tempdir = $g['tmp_path'] . "/openvpn-export-".uniqid();
	mkdir($tempdir, 0700, true);

	// create config directory
	$confdir = "{$tempdir}/config";
	if (!is_dir($confdir)) {
		mkdir($confdir, 0700, true);
	}

	// copy the template directory
	exec("cp -r {$workdir}/template/* {$tempdir}");
	// and put the required installer exe in place

	$installer_parts = pathinfo($client_install_exe);
	$installer_filename = "openvpn-install.{$installer_parts['extension']}";

	exec("/bin/cp {$tempdir}/{$client_install_exe} {$tempdir}/{$installer_filename}");

	/* OpenVPN Win7/Win10 installer stub is 32-bit, but could need to read a 64-bit registry.
	 * Using the 64-bit-aware openvpn-postinstall works fine even on 32-bit Windows 10.
	 */
	if (in_array($openvpn_version, array('Win7', 'Win10', 'x64-previous', 'x64-current'))) {
		rename("{$tempdir}/openvpn-postinstall64.exe", "{$tempdir}/openvpn-postinstall.exe");
	}

	// write configuration file
	$prefix = openvpn_client_export_prefix($srvid, $usrid, $crtid);
	$cfgfile = "{$confdir}/{$prefix}-config.ovpn";
	if (!empty($proxy) && $proxy['proxy_authtype'] != "none") {
		$proxy['passwdfile'] = "{$prefix}-password";
		$pwdfle = "{$proxy['user']}\r\n";
		$pwdfle .= "{$proxy['password']}\r\n";
		file_put_contents("{$confdir}/{$proxy['passwdfile']}", $pwdfle);
	}
	$conf = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $bindmode, $usetoken, $nokeys, $proxy, "baseconf", "", $p12encryption, false, true, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id);
	if (!$conf) {
		$input_errors[] = "Could not create a config to export.";
		return false;
	}

	file_put_contents($cfgfile, $conf);

	$cafile = "{$tempdir}/config/{$prefix}-ca.crt";
	file_put_contents($cafile, $server_ca);
	if ($settings['tls']) {
		$tlsfile = "{$tempdir}/config/{$prefix}-tls.key";
		file_put_contents($tlsfile, base64_decode($settings['tls']));
	}

	// write key files
	if ($settings['mode'] != "server_user" && !$usepkcs11) {
		$crtfile = "{$tempdir}/config/{$prefix}-{$user['name']}.crt";
		file_put_contents($crtfile, base64_decode($cert['crt']));
		if (!empty($cert['prv'])) {
			$keyfile = "{$tempdir}/config/{$prefix}-{$user['name']}.key";
			file_put_contents($keyfile, base64_decode($cert['prv']));
		}
		// convert to pkcs12 format
		$p12file = "{$tempdir}/config/{$prefix}.p12";
		if ($usetoken) {
			openvpn_client_pem_to_pk12($p12file, $outpass, $p12encryption, $crtfile, $keyfile, "{$prefix}-{$user['name']}");
		} else {
			openvpn_client_pem_to_pk12($p12file, $outpass, $p12encryption, $crtfile, $keyfile, "{$prefix}-{$user['name']}", $cafile);
		}
	}

	// 7zip the configuration data
	chdir($tempdir);
	$files = "config ";

	$files .= "{$installer_filename} ";
	$files .= "openvpn-postinstall.exe ";
	$procchain = ';!@Install@!UTF-8!' . "\n";

	$tokenarg = ($usetoken) ? ' /Import' : '';
	if ($silent) {
		$silentargexe = ' /S';
		$silentargmsi = ' /qn';
	} else {
		$silentarg = '';
	}

	if ($installer_parts['extension'] == 'exe') {
		$procchain .= "RunProgram=\"openvpn-install.exe{$silentargexe}\"\n";
	} else {
		/* Have to use RunProgram not ExecuteFile since ExecuteFile won't chain with RunProgram */
		$procchain .= "RunProgram=\"msiexec.exe /i openvpn-install.msi{$silentargmsi} /norestart\"\n";
	}

	$procchain .= "RunProgram=\"openvpn-postinstall.exe{$silentargexe}{$tokenarg}\"\n";

	$procchain .= ';!@InstallEnd@!' . "\n";
	file_put_contents("{$tempdir}/7zipConfig",$procchain);

	exec("/usr/local/bin/7z -y a archive.7z {$files}");
	// create the final installer
	$outfile = "{$tempdir}-install.exe";
	chdir($g['tmp_path']);
	exec("/bin/cat {$tempdir}/7zsd_All.sfx {$tempdir}/7zipConfig {$tempdir}/archive.7z > {$outfile}");

	// cleanup
	exec("/bin/rm -r {$tempdir}");

	return $outfile;
}

function viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $bindmode, $usetoken, $outpass, $p12encryption, $proxy, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id) {
	global $g;

	$uniq = uniqid();
	$tempdir = $g['tmp_path'] . "/openvpn-export-" . $uniq;
	$zipfile = $g['tmp_path'] . "/{$uniq}-Viscosity.visc.zip";

	$validconfig = openvpn_client_export_validate_config($srvid, $usrid, $crtid);
	if ($validconfig) {
		list($settings, $server_cert, $server_ca, $servercn, $user, $cert, $nokeys) = $validconfig;
	} else {
		return false;
	}

	// create template directory
	mkdir($tempdir, 0700, true);
	mkdir($tempdir . "/Viscosity.visc", 0700, true);

	// Append new Viscosity.visc directory on top
	$tempdir = $tempdir . "/Viscosity.visc/";

	// write cofiguration file
	if (!empty($proxy) && $proxy['proxy_authtype'] != "none") {
		$proxy['passwdfile'] = "config-password";
		$pwdfle = "{$proxy['user']}\n";
		$pwdfle .= "{$proxy['password']}\n";
		file_put_contents("{$tempdir}/{$proxy['passwdfile']}", $pwdfle);
	}

	$conf = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $bindmode, $usetoken, true, $proxy, "baseconf", $outpass, $p12encryption, true, true, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id);
	if (!$conf) {
		return false;
	}

	// We need to nuke the ca line from the above config if it exists.
	$conf = explode("\n", $conf);
	for ($i = 0; $i < count($conf); $i++) {
		if ((substr($conf[$i], 0, 3) == "ca ") || (substr($conf[$i], 0, 7) == "pkcs12 ")) {
			unset($conf[$i]);
		}
	}
	$conf = implode("\n", $conf);

	$friendly_name = $settings['description'];
	$visc_settings = <<<EOF
#-- Config Auto Generated By pfSense for Viscosity --#

#viscosity startonopen false
#viscosity dhcp true
#viscosity dnssupport true
#viscosity name {$friendly_name}

EOF;

	$configfile = "{$tempdir}/config.conf";
	$conf .= "ca ca.crt\n";

	if ($settings['tls_type'] == "crypt") {
		$tls_directive = "tls-crypt";
		$tls_keydir = "";
	} else {
		$tls_directive = "tls-auth";
		$tls_keydir = " 1";
	}

	$conf .= "{$tls_directive} ta.key{$tls_keydir}\n";
	if ($settings['mode'] != "server_user") {
		$conf .= <<<EOF
cert cert.crt
key key.key
EOF;
	}

	file_put_contents($configfile, $visc_settings . "\n" . $conf);

	//	ca.crt		cert.crt	config.conf	key.key		ta.key

	// write ca
	$cafile = "{$tempdir}/ca.crt";
	file_put_contents($cafile, $server_ca);

	if ($settings['mode'] != "server_user" && !$usepkcs11) {

		// write user .crt
		$crtfile = "{$tempdir}/cert.crt";
		file_put_contents($crtfile, base64_decode($cert['crt']));

		// write user .key
		if (!empty($outpass)) {
			$keyfile = "{$tempdir}/key.key";
			$clearkeyfile = "{$tempdir}/key-clear.key";
			file_put_contents($clearkeyfile, base64_decode($cert['prv']));
			$eoutpass = escapeshellarg($outpass);
			$ekeyfile = escapeshellarg($keyfile);
			$eclearkeyfile = escapeshellarg($clearkeyfile);
			exec("/usr/bin/openssl rsa -in ${eclearkeyfile} -out ${ekeyfile} -des3 -passout pass:${eoutpass}");
			unlink($clearkeyfile);
		} else {
			$keyfile = "{$tempdir}/key.key";
			file_put_contents($keyfile, base64_decode($cert['prv']));
		}
	}

	// TLS support?
	if ($settings['tls']) {
		$tlsfile = "{$tempdir}/ta.key";
		file_put_contents($tlsfile, base64_decode($settings['tls']));
	}

	// Zip Viscosity file
	exec("cd {$tempdir}/.. && /usr/local/bin/zip -r {$zipfile} Viscosity.visc");

	// Remove temporary directory
	exec("rm -rf {$tempdir}");

	return $zipfile;

}

function openvpn_client_export_build_remote_lines($settings, $useaddr, $interface, $expformat, $nl, $legacy=false) {
	global $config;
	$remotes = array();
	if (($useaddr == "serveraddr") || ($useaddr == "servermagic") || ($useaddr == "servermagichost")) {
		$interface = $settings['interface'];
		if (!empty($settings['ipaddr']) && is_ipaddr($settings['ipaddr'])) {
			$server_host = $settings['ipaddr'];
		} else {
			if (!$interface || ($interface == "any")) {
				$interface = "wan";
			}
			if (in_array(strtolower($settings['protocol']), array("udp6", "tcp6"))) {
				$server_host = get_interface_ipv6($interface);
			} else {
				$server_host = get_interface_ip($interface);
			}
		}
	} else if ($useaddr == "serverhostname" || empty($useaddr)) {
		$server_host = empty($config['system']['hostname']) ? "" : "{$config['system']['hostname']}.";
		$server_host .= "{$config['system']['domain']}";
	} else {
		$server_host = $useaddr;
	}

	$proto = strtolower(substr($settings['protocol'], 0, 3));
	if (strtolower($proto) == "tcp") {
		$proto .= "-client";
	}

	if (($expformat == "inlineconnect") && ($proto == "tcp-client")) {
		$proto = "tcp";
	}

	if (($useaddr == "servermagic") || ($useaddr == "servermagichost")) {
		$destinations = openvpn_client_export_find_port_forwards($server_host, $settings['local_port'], $proto, true, ($useaddr == "servermagichost"));
		foreach ($destinations as $dest) {
			$remotes[] = "remote {$dest['host']} {$dest['port']} {$dest['proto']}";
		}
	} else {
		$remoteproto = $legacy ? $proto : strtolower($settings['protocol']);
		$remotes[] = "remote {$server_host} {$settings['local_port']} {$remoteproto}";
	}

	return implode($nl, $remotes);
}

function openvpn_client_export_find_port_forwards($targetip, $targetport, $targetproto, $skipprivate, $findhostname=false) {
	global $config, $FilterIflist;
	if (empty($FilterIflist)) {
		filter_generate_optcfg_array();
	}
	$destinations = array();

	if (!is_array($config['nat']) || !is_array($config['nat']['rule'])) {
		return $destinations;
	}

	foreach ($config['nat']['rule'] as $natent) {
		$dest = array();
		if (!isset($natent['disabled']) &&
		   ($natent['target'] == $targetip) &&
		   ($natent['local-port'] == $targetport) &&
		   ($natent['protocol'] == strtolower(substr($targetproto, 0, 3)))) {
			$dest['proto'] = $targetproto;

			// Could be network or address ...
			$natif = (!$natent['interface']) ? "wan" : $natent['interface'];

			if (!isset($FilterIflist[$natif])) {
				continue; // Skip if there is no interface
			}

			$dstaddr = trim(filter_generate_address($natent, 'destination', true));
			if (!$dstaddr) {
				$dstaddr = $FilterIflist[$natif]['ip'];
			}

			$dstaddr_port = explode(" ", $dstaddr);

			if (empty($dstaddr_port[0]) || strtolower(trim($dstaddr_port[0])) == "port") {
				continue; // Skip port forward if no destination address found
			}

			if (!is_ipaddr($dstaddr_port[0])) {
				continue; // We can only work with single IPs, not subnets!
			}

			if ($skipprivate && is_private_ip($dstaddr_port[0])) {
				continue; // Skipping a private IP destination!
			}

			$dest['host'] = $dstaddr_port[0];

			if ($findhostname) {
				$hostname = openvpn_client_export_find_hostname($natif);
				if (!empty($hostname)) {
					$dest['host'] = $hostname;
				}
			}

			$dports = is_port($natent['destination']['port'])
			    ? array($natent['destination']['port'])
			    : filter_expand_alias_array($natent['destination']['port']);

			// Could be multiple ports, we add all of them
			foreach ($dports as $dport) {
				$dest['port'] = $dport;
				$destinations[] = $dest;
			}
		}
	}

	return $destinations;
}

function openvpn_client_export_find_hostname($interface) {
	global $config, $dyndns_split_domain_types;
	init_config_arr(array('dyndnses', 'dyndns'));
	init_config_arr(array('dnsupdates', 'dnsupdate'));
	foreach (array($config['dyndnses']['dyndns'], $config['dnsupdates']['dnsupdate']) as $ddnslist) {
		foreach ($ddnslist as $ddns) {
			if (($ddns['interface'] == $interface) && isset($ddns['enable']) && !empty($ddns['host']) && !is_numeric($ddns['host']) && is_hostname($ddns['host'])) {
				return $ddns['host'] . ((in_array($ddns['type'], $dyndns_split_domain_types)) ? '.' . $ddns['domainname'] : '');
			}
		}
	}
}

function openvpn_client_export_install_command() {
	global $config;

	init_config_arr(array('installedpackages', 'vpn_openvpn_export', 'config', 0));
	if (is_array($config['installedpackages']['vpn_openvpn_export']['config'][0]['defaultsettings']) &&
	    isset($config['installedpackages']['vpn_openvpn_export']['config'][0]['defaultsettings']['randomlocalport'])) {
		if ($config['installedpackages']['vpn_openvpn_export']['config'][0]['defaultsettings']['randomlocalport'] == 'yes') {
			$config['installedpackages']['vpn_openvpn_export']['config'][0]['defaultsettings']['bindmode'] = 'lport0';
		} else {
			$config['installedpackages']['vpn_openvpn_export']['config'][0]['defaultsettings']['bindmode'] = 'bind';
		}
		unset($config['installedpackages']['vpn_openvpn_export']['config'][0]['defaultsettings']['randomlocalport']);
		$config_changed = true;
	}

	if (is_array($config['installedpackages']['vpn_openvpn_export']['config'][0]['serverconfig']) &&
	    !empty($config['installedpackages']['vpn_openvpn_export']['config'][0]['serverconfig'])) {
		foreach ($config['installedpackages']['vpn_openvpn_export']['config'][0]['serverconfig'] as & $srvconf) {
			if (isset($srvconf['randomlocalport'])) {
				if ($srvconf['randomlocalport'] == 'yes') {
					$srvconf['bindmode'] = 'lport0';
				} else {
					$srvconf['bindmode'] = 'bind';
				}
				unset($srvconf['randomlocalport']);
				$config_changed = true;
			}
		}
	}

	if ($config_changed) {
		write_config("[OpenVPN Client Export] Package configuration updated.");
	}
}
