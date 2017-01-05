<?php
/*
 * openvpn-client-export.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
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
$current_openvpn_version = "2.4.0";
$current_openvpn_version_rev = "01";
$legacy_openvpn_version = "2.3.14";
$legacy_openvpn_version_rev = "01";

function openvpn_client_export_deinstall() {
	exec("/bin/rm -r /usr/local/share/openvpn/client-export");
}

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
	if ($usrid && is_numeric($usrid)) {
		$filename_addition = "-" . $config['system']['user'][$usrid]['name'];
	} elseif ($crtid && is_numeric($crtid) && function_exists("cert_get_cn")) {
		$filename_addition = "-" . str_replace(' ', '_', cert_get_cn($config['cert'][$crtid]['crt']));
	}
	return "{$host}-{$prot}-{$port}{$filename_addition}";
}

function openvpn_client_pem_to_pk12($outpath, $outpass, $crtpath, $keypath, $capath = false) {
	$eoutpath = escapeshellarg($outpath);
	$eoutpass = escapeshellarg($outpass);
	$ecrtpath = escapeshellarg($crtpath);
	$ekeypath = escapeshellarg($keypath);
	if ($capath) {
		$ecapath = escapeshellarg($capath);
		exec("/usr/bin/openssl pkcs12 -export -in {$ecrtpath} -inkey {$ekeypath} -certfile {$ecapath} -out {$eoutpath} -passout pass:{$eoutpass}");
	} else {
		exec("/usr/bin/openssl pkcs12 -export -in {$ecrtpath} -inkey {$ekeypath} -out {$eoutpath} -passout pass:{$eoutpass}");
	}

	unlink($crtpath);
	unlink($keypath);
	if ($capath) {
		unlink($capath);
	}
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
		if (function_exists("cert_get_cn")) {
			$servercn = cert_get_cn($server_cert['crt']);
		}
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

function openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $randomlocalport, $usetoken, $nokeys = false, $proxy, $expformat = "baseconf", $outpass = "", $skiptls = false, $doslines = false, $advancedoptions = "", $usepkcs11, $pkcs11providers, $pkcs11id) {
	global $input_errors, $g;

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
	$remotes = openvpn_client_export_build_remote_lines($settings, $useaddr, $interface, $expformat, $nl);
	$cipher = $settings['crypto'];
	$digest = !empty($settings['digest']) ? $settings['digest'] : "SHA1";

	// add basic settings
	$devmode = empty($settings['dev_mode']) ? "tun" : $settings['dev_mode'];
	if (($expformat != "inlinedroid") && ($expformat != "inlineios")) {
		$conf .= "dev {$devmode}{$nl}";
	}

	/* TODO: This has been deprecated in OpenVPN 2.4 but does not yet cause a fatal error. */
	if (!empty($settings['tunnel_networkv6']) && ($expformat != "inlinedroid") && ($expformat != "inlineios")) {
		$conf .= "tun-ipv6{$nl}";
	}
	$conf .= "persist-tun{$nl}";
	$conf .= "persist-key{$nl}";

//	if ((($expformat != "inlinedroid") && ($expformat != "inlineios")) && ($proto == "tcp"))
//		$conf .= "proto tcp-client{$nl}";
	$conf .= "cipher {$cipher}{$nl}";

	if ($legacy == 0) {
		if ($settings['ncp_enable'] == "disabled") {
			$conf .= "ncp-disable\n";
		} else {
			/* If the ncp-ciphers list is empty, don't specify a list so OpenVPN's default will be used. */
			if (!empty($settings['ncp-ciphers'])) {
				$conf .= "ncp-ciphers " . str_replace(',', ':', $settings['ncp-ciphers']) . "\n";
			}
		}
	}

	$conf .= "auth {$digest}{$nl}";
	$conf .= "tls-client{$nl}";
	$conf .= "client{$nl}";
	if (($expformat != "inlinedroid") && ($expformat != "inlineios")) {
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
	if (($randomlocalport != 0) && (substr($expformat, 0, 7) != "yealink") && ($expformat != "snom")) {
		$conf .= "lport 0{$nl}";
	}

	/* This line can cause problems with auth-only setups and also with Yealink/Snom phones
		since they are stuck on an older OpenVPN version that does not support this feature. */
	if (!empty($servercn) && !$nokeys) {
		switch ($verifyservercn) {
			case "none":
				break;
			case "tls-remote":
				$conf .= "tls-remote {$servercn}{$nl}";
				break;
			case "tls-remote-quote":
				$conf .= "tls-remote \"{$servercn}\"{$nl}";
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
			$conf .= "cryptoapicert \"SUBJ:{$user['name']}\"{$nl}";
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

	// Prevent MITM attacks by verifying the server certificate.
	// - Disable for now, it requires the server cert to include special options
	//$conf .= "remote-cert-tls server{$nl}";

	// Extra protection for the server cert, if it's supported
	if (function_exists("cert_get_purpose")) {
		if (is_array($server_cert) && ($server_cert['crt'])) {
			$purpose = cert_get_purpose($server_cert['crt'], true);
			if ($purpose['server'] == 'Yes') {
				$conf .= "ns-cert-type server{$nl}";
			}
		}
	}

	// add optional settings
	$compression = "";
	switch ($settings['compression']) {
		case 'lz4':
		case 'lz4-v2':
		case 'lzo':
		case 'stub':
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
	if (!empty($compression) && !$settings['compression_push']) {
		$conf .= "{$compression}\n";
	}

	if ($settings['passtos']) {
		$conf .= "passtos{$nl}";
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
				$keyfile = "{$tempdir}/{$prefix}.key";
				file_put_contents($keyfile, base64_decode($cert['prv']));

				// convert to pkcs12 format
				$p12file = "{$tempdir}/{$prefix}.p12";
				if ($usetoken) {
					openvpn_client_pem_to_pk12($p12file, $outpass, $crtfile, $keyfile);
				} else {
					openvpn_client_pem_to_pk12($p12file, $outpass, $crtfile, $keyfile, $cafile);
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
		case "inlineios":
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
				$conf .= "<{$tls_directive}>{$nl}" . trim(base64_decode($settings['tls'])) . "{$nl}</{$tls_directive}>{$nl}{$tls_keydir}";
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

function openvpn_client_export_installer($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $randomlocalport, $usetoken, $outpass, $proxy, $advancedoptions, $openvpn_version = "x86-xp", $usepkcs11, $pkcs11providers, $pkcs11id) {
	global $g, $input_errors, $current_openvpn_version, $current_openvpn_version_rev, $legacy_openvpn_version, $legacy_openvpn_version_rev;
	$uname_p = trim(exec("uname -p"));

	switch ($openvpn_version) {
		case "x86-xp":
			$client_install_exe = "openvpn-install-{$legacy_openvpn_version}-I0{$legacy_openvpn_version_rev}-i686.exe";
			break;
		case "x64-xp":
			$client_install_exe = "openvpn-install-{$legacy_openvpn_version}-I0{$legacy_openvpn_version_rev}-x86_64.exe";
			break;
		case "x86-win6":
			$client_install_exe = "openvpn-install-{$legacy_openvpn_version}-I6{$legacy_openvpn_version_rev}-i686.exe";
			break;
		case "x64-win6":
			$client_install_exe = "openvpn-install-{$legacy_openvpn_version}-I6{$legacy_openvpn_version_rev}-x86_64.exe";
			break;
		case "24":
		default:
			$client_install_exe = "openvpn-install-{$current_openvpn_version}-I6{$current_openvpn_version_rev}.exe";
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
	exec("/bin/cp {$tempdir}/{$client_install_exe} {$tempdir}/openvpn-install.exe");

	/* OpenVPN 2.4 installer stub is 32-bit, but could need to read a 64-bit registry.
	 * Using the 64-bit-aware openvpn-postinstall works fine even on 32-bit Windows 10.
	 */
	if ($openvpn_version == "24" || stristr($openvpn_version, "x64")) {
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
	$conf = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $randomlocalport, $usetoken, $nokeys, $proxy, "", "baseconf", false, true, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id);
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
		$keyfile = "{$tempdir}/config/{$prefix}-{$user['name']}.key";
		file_put_contents($keyfile, base64_decode($cert['prv']));
		// convert to pkcs12 format
		$p12file = "{$tempdir}/config/{$prefix}.p12";
		if ($usetoken) {
			openvpn_client_pem_to_pk12($p12file, $outpass, $crtfile, $keyfile);
		} else {
			openvpn_client_pem_to_pk12($p12file, $outpass, $crtfile, $keyfile, $cafile);
		}
	}

	// 7zip the configuration data
	chdir($tempdir);
	$files = "config ";

	$files .= "openvpn-install.exe ";
	$files .= "openvpn-postinstall.exe ";
	if ($usetoken) {
		$procchain = 	';!@Install@!UTF-8!
RunProgram="openvpn-postinstall.exe /Import"
;!@InstallEnd@!'
;
	} else {
		$procchain = 	';!@Install@!UTF-8!
RunProgram="openvpn-postinstall.exe"
;!@InstallEnd@!'
;
	}
	file_put_contents("{$tempdir}/7zipConfig",$procchain);

	if (file_exists("/usr/pbi/p7zip-{$uname_p}/bin/7z")) {
		exec("/usr/pbi/p7zip-{$uname_p}/bin/7z -y a archive.7z {$files}");
	} else {
		exec("/usr/local/libexec/p7zip/7z -y a archive.7z {$files}");
	}
	// create the final installer
	$outfile = "{$tempdir}-install.exe";
	chdir($g['tmp_path']);
	exec("/bin/cat {$tempdir}/7zsd_All.sfx {$tempdir}/7zipConfig {$tempdir}/archive.7z > {$outfile}");

	// cleanup
	exec("/bin/rm -r {$tempdir}");

	return $outfile;
}

function viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $randomlocalport, $usetoken, $outpass, $proxy, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id) {
	global $g;
	$uname_p = trim(exec("uname -p"));

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

	$conf = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $randomlocalport, $usetoken, true, $proxy, "baseconf", $outpass, true, true, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id);
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
	if (file_exists("/usr/pbi/zip-{$uname_p}/bin/zip")) {
		exec("cd {$tempdir}/.. && /usr/pbi/zip-{$uname_p}/bin/zip -r {$zipfile} Viscosity.visc");
	} else {
		exec("cd {$tempdir}/.. && /usr/local/bin/zip -r {$zipfile} Viscosity.visc");
	}
	// Remove temporary directory
	exec("rm -rf {$tempdir}");

	return $zipfile;

}

function openvpn_client_export_sharedkey_config($srvid, $useaddr, $proxy, $nokeys = false, $zipconf = false) {
	global $config, $input_errors, $g;

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

	// determine basic variables
	if ($useaddr == "serveraddr") {
		$interface = $settings['interface'];
		if (!empty($settings['ipaddr']) && is_ipaddr($settings['ipaddr'])) {
			$server_host = $settings['ipaddr'];
		} else {
			if (!$interface) {
				$interface = "wan";
			}
			if (in_array(strtolower($settings['protocol']), array("udp6", "tcp6"))) {
				$server_host = get_interface_ipv6($interface);
			} elseif (in_array(strtolower($settings['protocol']), array("udp4", "tcp4"))) {
				$server_host = get_interface_ip($interface);
			} else {
				/* TODO: Maybe consider fetching both for using IPv4 and IPv6 on OpenVPN 2.4 */
				$server_host = get_interface_ip($interface);
			}
		}
	} elseif ($useaddr == "serverhostname" || empty($useaddr)) {
		$server_host = empty($config['system']['hostname']) ? "" : "{$config['system']['hostname']}.";
		$server_host .= "{$config['system']['domain']}";
	} else {
		$server_host = $useaddr;
	}

	$server_port = $settings['local_port'];

	$proto = strtolower(substr($settings['protocol'], 0, 3));
	if (strtolower($proto) == "tcp") {
		$proto .= "-client";
	}

	$cipher = $settings['crypto'];
	$digest = !empty($settings['digest']) ? $settings['digest'] : "SHA1";

	// add basic settings
	$conf = "dev tun\n";

	/* TODO: This has been deprecated in OpenVPN 2.4 but does not yet cause a fatal error. */
	if(! empty($settings['tunnel_networkv6'])) {
		$conf .= "tun-ipv6\n";
	}
	$conf .= "persist-tun\n";
	$conf .= "persist-key\n";
	$conf .= "cipher {$cipher}\n";
	$conf .= "auth {$digest}\n";
	$conf .= "pull\n";
	$conf .= "resolv-retry infinite\n";

	/* TODO: Protocol could move to after remote. If client is 2.4, can be copied direct from server. Perhaps leverage openvpn_client_export_build_remote_lines() */
	$conf .= "proto {$proto}\n";
	$conf .= "remote {$server_host} {$server_port}\n";

	if ($settings['local_network']) {
		list($ip, $mask) = explode('/', $settings['local_network']);
		$mask = gen_subnet_mask($mask);
		$conf .= "route $ip $mask\n";
	}
	if (!empty($settings['tunnel_network'])) {
		list($ip, $mask) = explode('/', $settings['tunnel_network']);
		$mask = gen_subnet_mask($mask);
		$baselong = ip2long32($ip) & ip2long($mask);
		$ip1 = long2ip32($baselong + 1);
		$ip2 = long2ip32($baselong + 2);
		$conf .= "ifconfig $ip2 $ip1\n";
	}
	$conf .= "keepalive 10 60\n";
	$conf .= "ping-timer-rem\n";

	if (!empty($proxy)) {
		if ($proxy['proxy_type'] == "http") {
			if ($proto == "udp") {
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
				$proxy['passwdfile'] = openvpn_client_export_prefix($srvid) . "-proxy";
				$conf .= " {$proxy['passwdfile']} {$proxy['proxy_authtype']}";
			}
			$conf .= "\n";
		}
	}

	// add key settings
	$prefix = openvpn_client_export_prefix($srvid);
	$shkeyfile = "{$prefix}.secret";

	if ($nokeys) {
		$conf .= "secret {$shkeyfile}\n";
	} else {
		$conf .= "<secret>\n" . trim(base64_decode($settings['shared_key'])) . "\n</secret>\n";
	}

	// add optional settings
	$compression = "";
	switch ($settings['compression']) {
		case 'lz4':
		case 'lz4-v2':
		case 'lzo':
		case 'stub':
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
	if (!empty($compression)) {
		$conf .= "{$compression}\n";
	}
	if ($settings['passtos']) {
		$conf .= "passtos\n";
	}

	if ($zipconf == true) {
		// create template directory
		$tempdir = "{$g['tmp_path']}/{$prefix}";
		mkdir($tempdir, 0700, true);

		file_put_contents("{$tempdir}/{$prefix}.ovpn", $conf);

		$shkeyfile = "{$tempdir}/{$shkeyfile}";
		file_put_contents("{$shkeyfile}", base64_decode($settings['shared_key']));

		$uname_p = trim(exec("uname -p"));
		if (file_exists("/usr/pbi/zip-{$uname_p}/bin/zip")) {
			exec("cd {$tempdir}/.. && /usr/pbi/zip-{$uname_p}/bin/zip -r {$g['tmp_path']}/{$prefix}-config.zip {$prefix}");
		} else {
			exec("cd {$tempdir}/.. && /usr/local/bin/zip -r {$g['tmp_path']}/{$prefix}-config.zip {$prefix}");
		}

		// Remove temporary directory
		exec("rm -rf {$tempdir}");
		return "{$prefix}-config.zip";
	} else {
		return $conf;
	}
}

function openvpn_client_export_build_remote_lines($settings, $useaddr, $interface, $expformat, $nl) {
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

	if (($expformat == "inlineios") && ($proto == "tcp-client")) {
		$proto = "tcp";
	}

	if (($useaddr == "servermagic") || ($useaddr == "servermagichost")) {
		$destinations = openvpn_client_export_find_port_forwards($server_host, $settings['local_port'], $proto, true, ($useaddr == "servermagichost"));
		foreach ($destinations as $dest) {
			$remotes[] = "remote {$dest['host']} {$dest['port']} {$dest['proto']}";
		}
	} else {
		$remotes[] = "remote {$server_host} {$settings['local_port']} {$proto}";
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
		if (!isset($natent['disabled']) && ($natent['target'] == $targetip) && ($natent['local-port'] == $targetport) && ($natent['protocol'] == $targetproto)) {
			$dest['proto'] = $natent['protocol'];

			// Could be multiple ports... But we can only use one.
			$dports = is_port($natent['destination']['port']) ? array($natent['destination']['port']) : filter_expand_alias_array($natent['destination']['port']);
			$dest['port'] = $dports[0];

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

			$destinations[] = $dest;
		}
	}

	return $destinations;
}

function openvpn_client_export_find_hostname($interface) {
	global $config;
	if (is_array($config['dyndnses']['dyndns'])) {
		foreach ($config['dyndnses']['dyndns'] as $ddns) {
			if (($ddns['interface'] == $interface) && isset($ddns['enable']) && !empty($ddns['host']) && !is_numeric($ddns['host']) && is_hostname($ddns['host'])) {
				return $ddns['host'];
			}
		}
	}
	if (is_array($config['dnsupdates']['dnsupdate'])) {
		foreach ($config['dnsupdates']['dnsupdate'] as $ddns) {
			if (($ddns['interface'] == $interface) && isset($ddns['enable']) && !empty($ddns['host']) && !is_numeric($ddns['host']) && is_hostname($ddns['host'])) {
				return $ddns['host'];
			}
		}
	}

}
