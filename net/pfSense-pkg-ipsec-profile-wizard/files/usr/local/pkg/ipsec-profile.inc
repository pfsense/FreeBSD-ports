<?php
/*
 * ipsec-profile.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2020 Rubicon Communications, LLC (Netgate)
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

/*
 * Note that names starting with 'iew' are specific to Windows, 'iep' items are
 *   specific to Apple. 'iex' apply to both.
 */

require_once("globals.inc");
require_once("config.inc");
require_once("functions.inc");
require_once("interfaces.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");
require_once("util.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("certs.inc");

/* constants */
define('PROFILEIDX', 0);
define('IPSECIDX', 1);
define('CAIDX', 2);
define('PKCS12IDX', 3);

define('BODYSCOPE', 0);
define('PROFILESCOPE', 1);
define('PAYLOADSCOPE', 2);
define('VPNSCOPE', 3);
define('CASCOPE', 3);
define('CERTSCOPE', 3);
define('IKESCOPE', 4);
define('SASCOPE', 5);

global $config, $mobileconfig, $a_phase1, $a_phase2, $a_cert, $a_mobilekey, $certLineChars, $enablepfs;

init_config_arr(array('ipsec', 'client'));
init_config_arr(array('ipsec', 'phase1'));
init_config_arr(array('ipsec', 'phase2'));
init_config_arr(array('ipsec', 'mobilekey'));
init_config_arr(array('cert'));
init_config_arr(array('system', 'user'));

$mobileconfig = &$config['ipsec']['client'];
$a_phase1 = &$config['ipsec']['phase1'];
$a_phase2 = &$config['ipsec']['phase2'];
$a_cert = &$config['cert'];
$a_user = &$config['system']['user'];
$a_mobilekey = &$config['ipsec']['mobilekey'];
$certLineChars = 52;
$enablepfs = false;

global $mobile_p1, $mobile_p2;
/* Locate Mobile Phase 1 */
$mobile_p1 = array();
foreach ($a_phase1 as $p1) {
	if (isset($p1['mobile'])) {
		$mobile_p1 = $p1;
	}
}
$mobile_p2 = array();
foreach ($a_phase2 as $p2) {
	if ($p2['ikeid'] == $mobile_p1['ikeid']) {
		$mobile_p2[] = $p2;
	}
}

/*
 * Server Address - Select from server cert SAN entries or interface addresses.
 */
global $server_list;
$server_list = iex_server_list();
array_unshift($server_list, 'Auto');
$server_list[] = 'Custom Hostname';

/* These lists contain the various values supported _by Windows_ indexed by
 * the equivalent value found in the pfSense configuration.
 * The values were obtained from Microsoft documentation:
 *   https://docs.microsoft.com/en-us/powershell/module/vpnclient/set-vpnconnectionipsecconfiguration?view=win10-ps */

global $win_supported_map;
$win_supported_map = array(
	'p1' => array(
		'enc' => array(
			'param' => '-EncryptionMethod',
			'values' => array(
				'3des' => 'DES3',
				'aes-128' => 'AES128',
				'aes-192' => 'AES192',
				'aes-256' => 'AES256',
				'aes128gcm' => 'GCMAES128',
				'aes256gcm' => 'GCMAES256'
			)
		),
		'hash' => array(
			'param' => '-IntegrityCheckMethod',
			'values' => array(
				'md5' => 'MD5',
				'sha1' => 'SHA1',
				'sha256' => 'SHA256',
				'sha384' => 'SHA384'
			)
		),
		'dh' => array(
			'param' => '-DHGroup',
			'values' => array(
				'1' => 'Group1',
				'2' => 'Group2',
				'14' => 'Group14',
				'19' => 'ECP256',
				'20' => 'ECP384',
				'24' => 'Group24'
			)
		)
	),
	'p2' => array(
		'enc' => array(
			'param' => '-CipherTransformConstants',
			'values' => array(
				'3des' => 'DES3',
				'aes-128' => 'AES128',
				'aes-192' => 'AES192',
				'aes-256' => 'AES256',
				'aes128gcm' => 'GCMAES128',
				'aes192gcm' => 'GCMAES192',
				'aes256gcm' => 'GCMAES256'
			)
		),
		'hash' => array(
			'param' => '-AuthenticationTransformConstants',
			'values' => array(
				'' => 'None',
				'hmac_md5' => 'MD596',
				'hmac_sha1' => 'SHA196',
				'hmac_sha256' => 'SHA256128'
			)
		),
		'dh' => array(
			'param' => '-PfsGroup',
			'values' => array(
				'' => 'None',
				'1' => 'PFS1',
				'2' => 'PFS2',
				'14' => 'PFS2048',
				'19' => 'ECP256',
				'20' => 'ECP384',
				'24' => 'PFS24'
			)
		)
	)
);

/* Apple profiles support the same set of encryption algorithms for P1 and P2,
 * and pfSense names are the same in both cases.
 *
 * Apple profiles also support the same set of integrity/hash algorithms for P1
 *  and P2, but pfSense names are different in both cases. Strip "hmac_" from
 *  start of P2 hash algo name.
 */
global $apple_supported_map;
$apple_supported_map = array(
	'enc' => array(
		'3des' => 'DES3',
		'aes-128' => 'AES-128',
		'aes-192' => 'AES-192',
		'aes-256' => 'AES-256',
		'aes128gcm' => 'AES-128-GCM',
		'aes256gcm' => 'AES-256-GCM'
	),
	'hash' => array(
		'sha1' => 'SHA1-96',
		'sha256' => 'SHA2-256',
		'sha384' => 'SHA2-384',
		'sha512' => 'SHA2-512'
	),
	'dh' => array(
		''   => '0',
		'1'  => '1',
		'2'  => '2',
		'5'  => '5',
		'14' => '14',
		'15' => '15',
		'16' => '16',
		'17' => '17',
		'18' => '18',
		'19' => '19',
		'20' => '20',
		'21' => '21'
	)
);

/****f* iex_server_list
 * NAME
 *   iex_server_list - Generate a list of valid server addresses from the
 *                     server certificate SAN list.
 * INPUTS
 *   $auto: Boolean (default false), Automatically picks a server: when true,
 *          stops on the first hit and returns a single value.
 * RESULT
 *   Returns an array containing valid server addresses based on the values
 *   contained in the Mobile IPsec Phase 1 server certificate. Alternately,
 *   returns a single string containing the first viable target when in
 *   automatic mode.
 ******/
function iex_server_list($auto=false) {
	global $mobile_p1;

	$server_list = array();

	if (!empty($mobile_p1['certref'])) {
		$servercert = lookup_cert($mobile_p1['certref']);

		/* Check the SAN list for compatible types and store the compatible
		 * values */
		foreach (cert_get_sans($servercert['crt']) as $sanpair) {
			list($santype, $san) = explode(':', trim($sanpair), 2);
			if (in_array($santype, array('DNS', 'IP Address'))) {
				$server_list[] = $san;
				/* If we are in auto mode, stop on the first match. */
				if ($auto) {
					break;
				}
			}
		}
	} else {
		list($realAddr, $pubAddr) = iep_get_addresses($mobile_p1['interface']);
		if (is_ipaddr($pubAddr)) {
			$server_list[] = $pubAddr;
		}
	}

	/* If no viable entries were found in the SAN list, fall back to using
	 * the interface address chosen in the Mobile IPsec P1 */
	if (empty($server_list)) {
		$server_list[] = ipsec_get_phase1_src($mobile_p1);
	}

	/* For automatic mode, return the first entry only. Otherwise, return
	 * the full list. */
	return ($auto) ? $server_list[0] : $server_list;
}

/****f* iex_parameter_check
 * NAME
 *   iex_parameter_check - Check Mobile P1/P2 values for compatibility with
 *                         clients.
 * INPUTS
 *   $type: The type of client to process ('apple', 'windows')
 * RESULT
 *   Returns an array containing any errors found during the compatibility
 *   check.
 ******/
function iex_parameter_check($type) {
	global $mobile_p1, $mobile_p2, $apple_supported_map, $win_supported_map, $enablepfs;
	$errors = array();
	$p1errors = array();
	$p1_ok = false;

	/* Check each P1 encryption configuration line */
	foreach ($mobile_p1['encryption']['item'] as $mp1e) {
		$p1err = iex_check_p1($type, $mp1e);
		/* If this entry is OK, mark that we have at least one usable
		 * encryption configuration and stop */
		if (empty($p1err)) {
			$p1_ok = true;
			break;
		} else {
			$p1errors = array_merge($p1errors, $p1err);
		}
	}

	/* If we could not find any compatible P1 encryption, collect the errors */
	if (!$p1_ok) {
		$errors = array_merge($errors, $p1errors);
	}

	if (is_array($mobile_p2) && !empty($mobile_p2)) {
		/* Check the mobile Phase 2 entries and collect errors */
		foreach ($mobile_p2 as $mp2) {
			$errors = array_merge($errors, iex_check_p2($type, $mp2));
		}
	}
	return $errors;
}

/****f* iex_parameter_convert
 * NAME
 *   iex_parameter_convert - Convert pfSense Mobile IPsec P1/P2 settings to
 *                           equivalent parameters for use with Windows
 *                           PowerShell Set-VpnConnectionIPsecConfiguration
 * INPUTS
 *   $type  : The type of client to process ('apple', 'windows')
 *   $phases: Which phase to return (default=both), 1=IKE, 2=Child SA
 * RESULT
 *   Returns a string containing appropriate options which can be passed to
 *   Set-VpnConnectionIPsecConfiguration which match settings in the pfSense
 *   configuration.
 ******/
function iex_parameter_convert($type, $phases = array(1, 2)) {
	global $mobile_p1, $mobile_p2, $win_supported_map, $apple_supported_map, $enablepfs;
	$options = "";

	if ($type == 'apple') {
		$linesep = "";
	} else {
		$linesep = " `\r\n";
	}

	/* Check each P1 encryption configuration line and attempt conversion */
	if (in_array('1', $phases)) {
		foreach ($mobile_p1['encryption']['item'] as $mp1e) {
			/* Attempt to convert this P1 encryption line */
			$p1opt = iex_check_p1($type, $mp1e, true);
			/* If the conversion is successful, add the converted options
			 * and stop processing P1 encryption entries. */
			if (!empty($p1opt)) {
				$options .= $p1opt . $linesep;
				break;
			}
		}
	}

	/* Check the mobile Phase 2 entries and attempt conversion */
	if (in_array('2', $phases)) {
		foreach ($mobile_p2 as $mp2) {
			/* Attempt to convert the values on this P2 */
			$p2opt = iex_check_p2($type, $mp2, true);
			/* If the conversion is successful, add the converted options
			 * and stop processing P2 entries. */
			if (!empty($p2opt)) {
				$options .= $p2opt . $linesep;
				break;
			}
		}
	}
	return $options;
}

/****f* iex_convert_value
 * NAME
 *   iex_convert_value - Checks if a specific pfSense IPsec configuration
 *                       component is supported by a set of possible choices.
 * INPUTS
 *   $want:      String, a pfSense configuration parameter to check.
 *   $supported: Array, the appropriately matched area to check, such as:
 *               $win_supported_map[<phase>][<type>]['values']
 * RESULT
 *   Returns a string containing a compatible value for a given pfSense
 *   configuration parameter, or null if no compatible match could be located.
 ******/
function iex_convert_value($want, $supported) {
	if (!is_array($supported)) {
		return null;
	}
	if (array_key_exists($want, $supported)) {
		return $supported[$want];
	} else {
		return null;
	}
}

/****f* iex_check_p1
 * NAME
 *   iex_check_p1 - Checks and optionally converts pfSense Mobile IPsec P1
 *                  parameters to those compatible with the given client type.
 * INPUTS
 *   $type:      The type of client to process ('apple', 'windows')
 *   $p1:        Array, a pfSense mobile P1 configuration
 *   $convert:   Boolean (default: False), whether to return the converted
 *               parameters or an array of errors.
 * RESULT
 *   When $convert is false, returns an array of errors detected when checking
 *   parameters.
 *   When $convert is true, returns a string containing compatible parameters
 *   and values.
 ******/
function iex_check_p1($type, $p1, $convert=false) {
	global $apple_supported_map, $win_supported_map;
	$p1errors = array();
	$options = "";

	if ($type == 'apple') {
		$map_enc = &$apple_supported_map['enc'];
		$map_hash = &$apple_supported_map['hash'];
		$map_dh = &$apple_supported_map['dh'];
	} else {
		$map_enc = &$win_supported_map['p1']['enc']['values'];
		$map_hash = &$win_supported_map['p1']['hash']['values'];
		$map_dh = &$win_supported_map['p1']['dh']['values'];
	}

	/* Must match a full P1 encryption algorithm entry */

	/* Check P1 Encryption Algorithm */
	$ealgo = iex_fixup_algorithm($p1['encryption-algorithm']['name'], $p1['encryption-algorithm']['keylen']);
	$conv_ealgo = iex_convert_value($ealgo, $map_enc);
	if ($conv_ealgo) {
		if ($type == 'apple') {
			$options .= iep_key_data_str("EncryptionAlgorithm", "string", $conv_ealgo, SASCOPE);
		} else {
			$options .= " {$win_supported_map['p1']['enc']['param']} {$conv_ealgo}";
		}
	} else {
		$p1errors[] = sprintf(gettext("Phase 1 Encryption Algorithm unsupported by this client. Supported values are (%s)"),
				implode(', ', array_keys($map_enc)));
	}

	/* Check/Convert P1 Hash Algorithm */
	if ($type == 'apple') {
		$halgo = iep_fixup_integrity($p1['hash-algorithm']);
	} else {
		$halgo = $p1['hash-algorithm'];
	}

	$conv_halgo = iex_convert_value($halgo, $map_hash);
	if ($conv_halgo) {
		if ($type == 'apple') {
			$options .= iep_key_data_str("IntegrityAlgorithm", "string", $conv_halgo, SASCOPE);
		} else {
			$options .= " {$win_supported_map['p1']['hash']['param']} {$conv_halgo}";
		}
	} else {
		$p1errors[] = sprintf(gettext("Phase 1 Hash Algorithm unsupported by this client. Supported values are (%s)"),
				implode(', ', array_keys($map_hash)));
	}

	/* Check/Convert P1 DH Group */
	$conv_dh = iex_convert_value($p1['dhgroup'], $map_dh);
	if ($conv_dh) {
		if ($type == 'apple') {
			$options .= iep_key_data_str("DiffieHellmanGroup", "integer", $conv_dh, SASCOPE);
		} else {
			$options .= " {$win_supported_map['p1']['dh']['param']} {$conv_dh}";
		}
	} else {
		$p1errors[] = sprintf(gettext("Phase 1 DH Group unsupported by this client. Supported values are (%s)"),
				implode(', ', array_keys($map_dh)));
	}

	/* If converting, return option string. Otherwise, return error list. */
	if ($convert) {
		return (empty($p1errors)) ? $options : "";
	} else {
		return $p1errors;
	}
}

/****f* iex_check_p2
 * NAME
 *   iex_check_p2 - Checks and optionally converts pfSense Mobile IPsec P2
 *                  parameters to those compatible with the given client type.
 * INPUTS
 *   $type:      The type of client to process ('apple', 'windows')
 *   $p2:        Array, a pfSense mobile P2 configuration
 *   $convert:   Boolean (default: False), whether to return the converted
 *               parameters or an array of errors.
 * RESULT
 *   When $convert is false, returns an array of errors detected when checking
 *   parameters.
 *   When $convert is true, returns a string containing compatible
 *   parameters and values.
 ******/
function iex_check_p2($type, $p2, $convert=false) {
	global $mobileconfig, $apple_supported_map, $win_supported_map, $enablepfs;
	$p2errors = array();
	$options = "";

	if ($type == 'apple') {
		$map_enc = &$apple_supported_map['enc'];
		$map_hash = &$apple_supported_map['hash'];
		$map_dh = &$apple_supported_map['dh'];
	} else {
		$map_enc = &$win_supported_map['p2']['enc']['values'];
		$map_hash = &$win_supported_map['p2']['hash']['values'];
		$map_dh = &$win_supported_map['p2']['dh']['values'];
	}

	/* Only need to find one match for each item, not a complete match */
	/* Check/Convert P2 ealgo -- Array */
	$p2e_errors = array();
	$p2e_ok = false;

	foreach ($p2['encryption-algorithm-option'] as $p2e) {
		$ealgo = iex_fixup_algorithm($p2e['name'], $p2e['keylen']);
		$conv_ealgo = iex_convert_value($ealgo, $map_enc);

		if ($conv_ealgo) {
			$p2e_ok = true;
			if ($type == 'apple') {
				$options .= iep_key_data_str("EncryptionAlgorithm", "string", $conv_ealgo, SASCOPE);
			} else {
				$options .= " {$win_supported_map['p2']['enc']['param']} {$conv_ealgo}";
			}
			break;
		} else {
			$p2e_errors[] = sprintf(gettext("Phase 2 Encryption Algorithm unsupported by this client. Supported values are (%s)"),
						implode(', ', array_keys($map_enc)));
		}
	}
	if (!$p2e_ok) {
		$p2errors = array_merge($p2errors, $p2e_errors);
	}

	/* Check/Convert P2 halgo -- Array */
	$p2h_errors = array();
	$p2h_ok = false;
	if (!is_array($p2['hash-algorithm-option'])) {
		$p2['hash-algorithm-option'] = array();
	}
	foreach ($p2['hash-algorithm-option'] as $p2h) {
		if ($type == 'apple') {
			$halgo = iep_fixup_integrity($p2h);
		} else {
			$halgo = $p2h;
		}
		$conv_halgo = iex_convert_value($halgo, $map_hash);
		if ($conv_halgo) {
			$p2h_ok = true;
			if ($type == 'apple') {
				$options .= iep_key_data_str("IntegrityAlgorithm", "string", $conv_halgo, SASCOPE);
			} else {
				$options .= " {$win_supported_map['p2']['hash']['param']} {$conv_halgo}";
			}
			break;
		} else {
			$p2h_errors[] = sprintf(gettext("Phase 2 Hash Algorithm unsupported by this client. Supported values are (%s)"),
						implode(', ', array_keys($map_hash)));
		}
	}

	/* P2 hash may be omitted with AES-GCM, otherwise flag an error */
	if (!$p2h_ok &&
	    !(empty($p2['hash-algorithm-option']) && (stripos($ealgo, 'gcm') !== false))) {
		$p2errors = array_merge($p2errors, $p2h_errors);
	}

	/* Check/Convert P2 pfs */
	if (!empty($mobileconfig['pfs_group'])) {
		$pfs = $mobileconfig['pfs_group'];
	} elseif (!empty($p2['pfsgroup'])) {
		$pfs = $p2['pfsgroup'];
	} else {
		$pfs = '';
	}

	$conv_dh = iex_convert_value($pfs, $map_dh);
	if ($conv_dh !== null) {
		if ($type == 'apple') {
			$enablepfs = ((int)$conv_dh > 0);
			if ($enablepfs) {
				$options .= iep_key_data_str("DiffieHellmanGroup", "integer", $conv_dh, SASCOPE);
			}
		} else {
			$options .= " {$win_supported_map['p2']['dh']['param']} {$conv_dh}";
		}
	} else {
		$p2errors[] = sprintf(gettext("Phase 2 PFS Group unsupported by this client. Supported values are (%s)"),
				implode(', ', array_keys($map_dh)));
	}

	if ($type == 'apple') {
		if (($p2['lifetime'] >= 600) &&
		    ($p2['lifetime'] <= 86400)) {
			$ipsecLifetime = (int)$p2['lifetime'] / 60;
			$options .= iep_key_data_str("LifeTimeInMinutes", "integer", $ipsecLifetime, SASCOPE);
		}
	}

	/* If converting, return option string. Otherwise, return error list. */
	if ($convert) {
		return (empty($p2errors)) ? $options : "";
	} else {
		return $p2errors;
	}
}

/****f* iex_random_pass
 * NAME
 *   iex_random_pass - Generate a random, reasonably-secure password.
 * INPUTS
 *   None
 * RESULT
 *   Returns a string containing a random 56 character alphanumeric password
 ******/
function iex_random_pass() {
	$keyoutput = "";
	$keystatus = "";
	exec("/bin/dd status=none if=/dev/random bs=4096 count=1 | /usr/bin/openssl sha224 | /usr/bin/cut -f2 -d' '", $keyoutput, $keystatus);
	return $keyoutput[0];
}

/****f* iew_export_archive
 * NAME
 *   iew_export_archive - Creates an archive containing certificates and a
 *                        Windows PowerShell script which will import
 *                        certificates and VPN settings into a Windows client.
 * INPUTS
 *   $vpn_name:       The name used for the archive as well as for the Windows
 *                    VPN instance.
 *   $server_address: The hostname or IP address to which the client must
 *                    connect.
 *   $user_certref:   For EAP-TLS, a certificate refid for the specific end-user
 *                    certificate to include in the archive.
 *   $send_download:  Boolean, whether or not to initiate a download of the
 *                    archive.
 * RESULT
 *   Creates and sends an archive to the user (browser download) containing all
 *   of the files and commands necessary to configure a Windows VPN client.
 ******/
function iew_export_archive($vpn_name, $server_address, $user_certref = null, $send_download = true) {
	global $g, $config, $mobileconfig, $a_phase1, $a_phase2, $mobile_p1, $mobile_p2, $win_supported_map;

	$script_filename = "add_pfSense_vpn_client.ps1";

	/* Since this is a Windows script, use DOS style newlines */
	$nl = "\r\n";

	/* Script header */
	$script = "# IKEv2 VPN Import Script{$nl}";
	$script .= "# Automatically generated by pfSense{$nl}";
	$script .= "Set-Location -Path \$PSScriptRoot{$nl}";

	/* Create temp directory */
	$script_dir = @tempnam($g['tmp_path'], "IEW");
	@rmdir_recursive($script_dir);
	safe_mkdir($script_dir, 0700);

	/* Import User Cert for EAP-TLS */
	if ($mobile_p1['authentication_method'] == 'eap-tls') {
		/* Check user cert */
		$user_cert = lookup_cert($user_certref);
		if (!$user_cert || empty($user_cert['prv'])) {
			/* Cleanup unfinished work */
			@rmdir_recursive($script_dir);
			throw new Exception(gettext('Invalid TLS certificate'));
		}

		$password = iex_random_pass();

		/* Export user CA on its own */
		$uca = lookup_ca($user_cert['caref']);
		$ucafn = "pfSense_ikev2_userca_{$uca['refid']}.pem";
		file_put_contents("{$script_dir}/{$ucafn}", base64_decode($uca['crt']));
		$script .= "{$nl}# Import User TLS Certificate CA{$nl}";
		$script .= "Import-Certificate -FilePath \"{$ucafn}\" -CertStoreLocation Cert:\\LocalMachine\\Root\\{$nl}";
		/* Get CA Fingerprint */
		$ca_fingerprint = openssl_x509_fingerprint(openssl_x509_read(base64_decode($uca['crt'])));
		/* Reformat as Windows "Thumbprint" style */
		$ca_thumbprint = chunk_split($ca_fingerprint, 2, ' ');

		/* Export P12 with random password and store */
		$args = array();
		$args['friendly_name'] = $user_cert['descr'];
		$args['encrypt_key_cipher'] = OPENSSL_CIPHER_AES_256_CBC;
		$args['extracerts'] = openssl_x509_read(base64_decode($uca['crt']));
		$res_crt = openssl_x509_read(base64_decode($user_cert['crt']));
		$res_key = openssl_pkey_get_private(base64_decode($user_cert['prv']));
		$exp_data = "";
		openssl_pkcs12_export($res_crt, $exp_data, $res_key, $password, $args);
		$ucfn = "pfSense_ikev2_user_{$user_cert['refid']}.p12";
		file_put_contents("{$script_dir}/{$ucfn}", $exp_data);

		/* Setup variable and commands for P12 import with random password */
		$script .= "{$nl}# Import User TLS Certificate PKCS#12{$nl}";
		$script .= "\$password = ConvertTo-SecureString -String \"{$password}\" -AsPlainText -Force{$nl}";
		$script .= "Import-PfxCertificate -FilePath \"{$ucfn}\" -CertStoreLocation Cert:\\CurrentUser\\My\\ -Password \$password{$nl}";
	}

	/* Export Server CA Cert */
	$servercert = lookup_cert($mobile_p1['certref']);
	$ca = ca_chain($servercert);
	if ($ca) {
		$cafn = "pfSense_ikev2_{$servercert['caref']}.pem";
		file_put_contents("{$script_dir}/{$cafn}", $ca);

		$script .= "{$nl}# Import Server Certificate CA{$nl}";
		$script .= "Import-Certificate -FilePath \"{$cafn}\" -CertStoreLocation Cert:\\LocalMachine\\Root\\{$nl}";
	}

	/* Make Add Command */
	$script .= "{$nl}# Add VPN Connection{$nl}";
	if ($mobile_p1['authentication_method'] == 'eap-tls') {
		$script .= "\$CustomEAP = '";
		$script .= <<<EOD
<EapHostConfig xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
   <EapMethod>
      <Type xmlns="http://www.microsoft.com/provisioning/EapCommon">13</Type>
      <VendorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorId>
      <VendorType xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorType>
      <AuthorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</AuthorId>
   </EapMethod>
   <Config>
      <Eap xmlns="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1">
         <Type>13</Type>
         <EapType xmlns="http://www.microsoft.com/provisioning/EapTlsConnectionPropertiesV1">
            <CredentialsSource>
               <CertificateStore>
                  <SimpleCertSelection>true</SimpleCertSelection>
               </CertificateStore>
            </CredentialsSource>
            <ServerValidation>
               <DisableUserPromptForServerValidation>false</DisableUserPromptForServerValidation>
               <ServerNames>{$server_address}</ServerNames>
               <TrustedRootCA>{$ca_thumbprint}</TrustedRootCA>
            </ServerValidation>
            <DifferentUsername>false</DifferentUsername>
            <PerformServerValidation xmlns="http://www.microsoft.com/provisioning/EapTlsConnectionPropertiesV2">true</PerformServerValidation>
            <AcceptServerName xmlns="http://www.microsoft.com/provisioning/EapTlsConnectionPropertiesV2">true</AcceptServerName>
         </EapType>
      </Eap>
   </Config>
</EapHostConfig>
EOD;
		$script .= "'{$nl}{$nl}";
	}
	$script .= "Add-VpnConnection -Name \"{$vpn_name}\" -TunnelType \"Ikev2\" -EncryptionLevel Required `{$nl}";

	/* Determine VPN Server Address */
	$script .= "  -ServerAddress {$server_address}";

	/* DNS Suffix */
	if (!empty($mobileconfig['dns_domain'])) {
		$script .= " -DnsSuffix \"{$mobileconfig['dns_domain']}\"";
	}
	/* Split Tunneling */
	if (isset($mobileconfig['net_list'])) {
		$script .= " -SplitTunneling";
	}
	/* Authentication Method */
	if ($mobile_p1['authentication_method'] == 'eap-tls') {
		$script .= " -AuthenticationMethod EAP -EapConfigXmlStream \$CustomEAP";
	}
	$script .= " -PassThru{$nl}";

	/* Make Set Command */
	$set_errors = iex_parameter_check('windows');
	if (!empty($set_errors)) {
		/* Cleanup unfinished work */
		@rmdir_recursive($script_dir);
		throw new Exception(sprintf(gettext('IPsec parameters not supported by Windows: %s'), implode(', ', $set_errors)));
	}
	$script .= "{$nl}# Set VPN Config{$nl}";
	$script .= "Set-VpnConnectionIPsecConfiguration -Name \"{$vpn_name}\" `{$nl}";
	$script .= iex_parameter_convert('windows');
	$script .= " -PassThru -Force{$nl}";

	/* Split Tunneling */
	if (isset($mobileconfig['net_list'])) {
		/* Add Routes */
		$script .= "{$nl}# Split Tunnel Routes{$nl}";
		foreach ($mobile_p2 as $mp2) {
			$localnet = ipsec_idinfo_to_cidr($mp2['localid'], true, $mp2['mode']);
			$script .= "Add-VpnConnectionRoute -ConnectionName \"{$vpn_name}\" -DestinationPrefix {$localnet}{$nl}";
		}
	}

	/* Write Script */
	file_put_contents("{$script_dir}/{$script_filename}", $script);

	/* Create Archive */
	$zname = "{$g['tmp_path']}/" . str_replace(' ', '_', $vpn_name) . ".zip";
	@unlink_if_exists($zname);
	$command = "cd " . escapeshellarg("{$script_dir}") .
			" && /usr/local/bin/zip -r -D " .
			escapeshellarg($zname) . " . ";
	exec($command);

	/* Send download file */
	if ($send_download) {
		send_user_download('file', str_replace(' ', '_', $zname));
	}

	/* Cleanup */
	@rmdir_recursive($script_dir);
	@unlink_if_exists($zname);

	return $script;
}

/****f* iex_fixup_algorithm
 * NAME
 *   iex_fixup_algorithm - Fixes up the encryption algorithm to a format
 *                         expected by target platforms.
 * INPUTS
 *   $algo   : String, an encryption algorithm
 *   $keylen : String, a numeric key length value
 * RESULT
 *   Given an algorithm and key length, returns a string in which the key length
 *   is only appended to AES. This effectively converts the two into a single
 *   string which is how the target platforms specify AES variants.
 ******/
function iex_fixup_algorithm($algo, $keylen) {
	return ($algo == 'aes') ? "{$algo}-{$keylen}" : $algo;
}

/****f* iep_fixup_integrity
 * NAME
 *   iep_fixup_integrity - Fixes up the integrity algorithm to a format
 *                         expected by target platforms.
 * INPUTS
 *   $algo : String, an integrity algorithm as stored in the pfSense
 *           configuration
 * RESULT
 *   Returns the integrity algorithm with pfSense-specific parts removed,
 *   which results in the format expected by the target platform.
 ******/
function iep_fixup_integrity($algo) {
	/* pfSense P2 integrity/hash algorithms are prefixed with "hmac_" but
	 * otherwise match their P1 counterparts. Apple uses the same names
	 * in both, so remove the "hmac_". */
	$remove_prefix = "hmac_";
	if (substr($algo, 0, strlen($remove_prefix)) == $remove_prefix) {
		$algo = substr($algo, strlen($remove_prefix));
	}
	return $algo;
}

/****f* iep_get_addresses
 * NAME
 *   iep_get_addresses - Find the IP address of an interface usable for IPsec.
 * INPUTS
 *   $intf:      String, a pfSense interface name
 * RESULT
 *  Returns an array with the actual IP address of the interface in the first
 *  element and for AWS/OpenStack hosts, the second public NAT IP in the second
 *  element
 ******/
function iep_get_addresses($intf) {
	$sysIntf  = get_real_interface($intf);
	$realAddr = get_interface_ip($intf);

	/* AWS hosts will get a file under /var/db with the public IP */
	$natIpCache = "/var/db/natted_{$sysIntf}_ip";
	if (file_exists($natIpCache)) {
		$natTxt = file_get_contents($natIpCache);
		$natIpAddr = rtrim($natTxt);
		$pubAddr = $natIpAddr;
	}

	if (!isset($pubAddr)) {
		$pubAddr = $realAddr;
	}

	return array($realAddr, $pubAddr);
}

/****f* iep_get_vpn_type
 * NAME
 *   iep_get_vpn_type - Convert pfSense stored IKE type value to the equivalent
 *                      Apple profile tag
 * INPUTS
 *   $iketype:   String, a pfSense configuration parameter for a mobile IPsec
 *               IKE type (empty, ikev2, ikev1 -- default: ikev1).
 * RESULT
 *   Returns Apple profile compatible values: "IKEv2" for IKEv2, "IPSec"
 *   otherwise.
 ******/
function iep_get_vpn_type($iketype) {
	return ($iketype == "ikev2") ? "IKEv2" : "IPSec";
}

/****f* iep_get_uuids
 * NAME
 *   iep_get_uuids - Generate a set of UUIDs
 * INPUTS
 *   $num: Integer, the number of UUIDs to generate.
 * RESULT
 *   Returns an array containing the requested number of UUIDs
 ******/
function iep_get_uuids($num=4) {
	$uuids = array();
	exec("/bin/uuidgen -n " . escapeshellarg($num), $uuids);
	return $uuids;
}


/****f* iep_get_client_auth
 * NAME
 *   iep_get_client_auth - Locate a VPN client entry based on a given username
 *                         or certificate reference.
 * INPUTS
 *   $user: The username or certificate reference to find
 * RESULT
 *   Array of multiple values:
 *     0: Authentication type (user, cert, hybrid)
 *     1: IKE Identity (username or certificate DN)
 *     2: Auth Data (PSK string or certificate data)
 ******/
function iep_get_client_auth($user) {
	global $mobile_p1, $a_user, $a_cert, $a_mobilekey, $mobileconfig;
	$auth_type = "";
	$ike_id = "";
	$auth_data = "";

	/* Check VPN type */
	switch ($mobile_p1['authentication_method']) {
		/* If it's a cert-based type, look up the certificate based on the refid */
		case 'eap-tls':
		case 'cert':
			$auth_type = "cert";
			foreach ($a_cert as $cert) {
				/* Check for ID match */
				if ($cert['refid'] == $user) {
					$auth_data = $cert;
					$ike_id = iep_get_cert_client_certid($cert);
					break;
				}
			}
			break;

		/* If it's a user+cert based type, get the user and certificate */
		case 'hybrid_rsa_server':
		case 'hybrid_cert_server':
		case 'xauth_rsa_server':
		case 'xauth_cert_server':
			$auth_type = "hybrid";
			if (empty($mobileconfig['user_source']) ||
			    ($mobileconfig['user_source'] == "Local Database")) {
				$userentry = getUserEntry($user);
				$auth_data = iep_get_cert_client_cert($mobile_p1['caref'], $userentry);
				$ike_id = iep_get_cert_client_certid($cert);
			} else {
				foreach ($a_cert as $cert) {
					/* Check for ID match */
					if ($cert['refid'] == $user) {
						$auth_data = $cert;
						$ike_id = iep_get_cert_client_certid($cert);
						break;
					}
				}
			}
			break;

		/* If it's a user-based type, look up the user entry based on the username */
		case 'xauth_psk_server':
			$auth_type = "user";
			$auth_data = $mobile_p1['pre-shared-key']; // Shared PSK
			$ike_id = $mobile_p1['peerid_data']; // Group ID
			break;

		/* EAP user auth methods do not need a local ID or auth data set */
		case 'eap-mschapv2':
		case 'eap-radius':
			$auth_type = "user";
			$ike_id = null;
			$auth_data = null;
			break;

		/* Any user on the system with a PSK or entry on PSK tab */
		case 'pre_shared_key':
			$auth_type = "user";
			$ike_id = $user;
			foreach ($a_user as $kuser) {
				if ($user == $kuser['name']) {
					$auth_data = $kuser['ipsecpsk'];
				}
			}
			foreach ($a_mobilekey as $mobilekey) {
				if (($mobilekey['ident'] == $user) &&
				    ($mobilekey['type'] == 'PSK')) {
					$auth_data = $mobilekey['pre-shared-key'];
				}
			}
			break;
	}

	return array($auth_type, $ike_id, $auth_data);

}

/****f* iep_get_valid_users
 * NAME
 *   iep_get_valid_users - Generate a list of valid users for Mobile IPsec
 * INPUTS
 *   None
 * RESULT
 *   Returns an array containing a list of valid clients, which may be users,
 *   certificates, or IPsec keys.
 ******/
function iep_get_valid_users() {
	global $config, $mobile_p1, $a_user, $a_cert, $a_mobilekey, $mobileconfig;
	$valid_users = array();

	switch ($mobile_p1['authentication_method']) {
		/* Methods which use certificates exclusively, not users */
		case 'eap-tls':
		case 'cert':
			foreach ($a_cert as $cert) {
				/* Check for CA match */
				if ($cert['caref'] == $mobile_p1['caref']) {
					$valid_users[$cert['refid']] = $cert['descr'];
				}
			}
			break;

		/* Methods which use certificates and users */
		case 'hybrid_cert_server':
		case 'xauth_cert_server':
			if (empty($mobileconfig['user_source']) ||
			    ($mobileconfig['user_source'] == "Local Database")) {
				foreach ($a_user as $user) {
					if (!empty(iep_get_cert_client_id($cert['caref'], $user))) {
						$valid_users[$user['name']] = "{$user['name']}";
						if (!empty($user['descr'])) {
							$valid_users[$user['name']] .= " ({$user['descr']})";
						}
					}
				}
			} else {
				foreach ($a_cert as $cert) {
					/* Check for CA match */
					if ($cert['caref'] == $mobile_p1['caref']) {
						$valid_users[$cert['refid']] = $cert['descr'];
					}
				}
			}
			break;

		/* Methods requiring a valid user with correct privilege */
		case 'xauth_psk_server':
			if (empty($mobileconfig['user_source']) ||
			    ($mobileconfig['user_source'] == "Local Database")) {
				foreach ($a_user as $user) {
					if (userHasPrivilege($user, "user-ipsec-xauth-dialin")) {
						$valid_users[$user['name']] = "{$user['name']}";
						if (!empty($user['descr'])) {
							$valid_users[$user['name']] .= " ({$user['descr']})";
						}
					}
				}
			} else {
				$valid_users['<external>'] = gettext("External Auth");
			}
			break;

		/* Methods requiring entry on PSK tab */
		case 'eap-mschapv2':
			foreach ($a_mobilekey as $mobilekey) {
				if ($mobilekey['type'] == 'EAP') {
					$valid_users[$mobilekey['ident']] = $mobilekey['ident'];
				}
			}
			break;

		/* Any user on the system with a PSK or entry on PSK tab */
		case 'pre_shared_key':
			foreach ($a_user as $user) {
				if (!empty($user['ipsecpsk'])) {
					$valid_users[$user['name']] = "{$user['name']}";
					if (!empty($user['descr'])) {
						$valid_users[$user['name']] .= " ({$user['descr']})";
					}
				}
			}
			foreach ($a_mobilekey as $mobilekey) {
				if ($mobilekey['type'] == 'PSK') {
					$valid_users[$mobilekey['ident']] = $mobilekey['ident'];
				}
			}
			break;
		/* Ignore user entirely */
		case 'eap-radius':
		default:
			$valid_users['<external>'] = gettext("External Auth");
			break;
	}
	return $valid_users;
}

/****f* iep_get_cert_client_cert
 * NAME
 *   iep_get_cert_client_cert - Searches a given user's certificates to find
 *                              one signed by a given CA.
 * INPUTS
 *   $caref: String, the refid of the CA by which the user cert should be signed
 *   $user : String, username which will be searched to find a certificate
 * RESULT
 *   Returns a certificate array, if found, for the given user signed by the
 *   given CA.
 ******/
function iep_get_cert_client_cert($caref, $user) {
	$vpnCert = array();
	if (is_array($user['cert'])) {
		foreach ($user['cert'] as $certid) {
			$cert = lookup_cert($certid);
			if ($cert['caref'] === $caref) {
				$vpnCert = $cert;
				break;
			}
		}
	}
	return $vpnCert;
}

/****f* iep_get_cert_client_certid
 * NAME
 *   iep_get_cert_client_certid - Crafts an Apple-compatible certificate ID
 *                                string.
 * INPUTS
 *   $vpnCert:   Array, certificate from which the ID will be created.
 * RESULT
 *   A string containing an ID for the given certificate, in a format compatible
 *   with Apple profiles.
 * 
 *   Editorial note -
 *   This would be much easier if you could use the DN/subject as the
 *   identifier, but iOS parses DN's incorrectly and sends them as the
 *   wrong type
 ******/
function iep_get_cert_client_certid($vpnCert) {
	/* Parse cert, take the value of the first subjectAltName field */
	$certTxt = base64_decode($vpnCert['crt']);
	$parsedCert = openssl_x509_parse($certTxt);

	if (is_array($parsedCert['extensions'])) {
		$subjAltName = $parsedCert['extensions']['subjectAltName'];
		/* May be multiple names, comma separated */
		$altNames = explode(',', $subjAltName);
		foreach ($altNames as $name) {
			/* Formatted as 'type:value' */
			$fields = explode(':', $name);
			if (count($fields) == 2) {
				$clientId = trim($fields[1]);
			}
		}

	/* No SANs found, use the CN of the subject */
	} elseif (is_array($parsedCert['subject'])){
		$clientId = $parsedCert['subject']['CN'];
	} else {
		$clientId = "";
	}

	return($clientId);
}

/****f* iep_get_cert_client_id
 * NAME
 *   iep_get_cert_client_id - Convenince function to find the client ID by CA
 *                            and username in one step.
 * INPUTS
 *   $caref: String, the refid of the CA by which the user cert should be signed
 *   $user : String, username which will be searched to find a certificate
 * RESULT
 *   A string containing an ID for the given certificate, in a format compatible
 *   with Apple profiles.
 ******/
function iep_get_cert_client_id($caref, $user) {
	return iep_get_cert_client_certid(iep_get_cert_client_cert($caref, $user));
}


/****f* iep_key_data_str
 * NAME
 *   iep_key_data_str - Generate the text of a key-value pair formatted for
  *                     inclusion in an Apple config profile.
 * INPUTS
 *   $keyName    : String, the name of the key
 *   $keyType    : String, the data type for the key
 *   $keyValue   : String, the value associated with the key
 *   $indentLevel: Integer, number of tab stops to indent this content
 * RESULT
 *   Returns a string formatted as a set of Apple Profile dictionary key/value
 *   pair, with the given values.
 ******/
function iep_key_data_str($keyName, $keyType, $keyValue, $indentLevel) {
	$indent = str_repeat("\t", $indentLevel);

	if (empty($keyName) && empty($keyValue)) {
		return "";
	}

	$extraNewline = "";
	$extraIndent = "";

	/* Dict/data/array get their open/close tags on their own lines */
	if (in_array($keyType, array("dict", "data", "array"))) {
		$extraNewline = "\n";
		$extraIndent  = $indent;
	}

	$keyData = "";
	/* Some arrays don't have a key name, everything else probably does */
	if ($keyName) {
		$keyData .= $indent . "<key>{$keyName}</key>\n";
	}

	/* Most types have open/close tags. booleans have a single tag */
	$keyData .= $indent;
	if ($keyType == "boolean") {
		if ($keyValue === false) {
			$keyData .= "<false/>\n";
		} else {
			$keyData .= "<true/>\n";
		}
	} else {
		$keyData .= "<{$keyType}>" . $extraNewline;
		$keyData .= "{$keyValue}";
		$keyData .= $extraIndent . "</{$keyType}>\n";
	}

	return $keyData;
}

/****f* iep_generate_vpn
 * NAME
 *   iep_generate_vpn - Generate Payload data for the VPN dict
 * INPUTS
 *   $profId        : String, Internal ID of the VPN profile
 *   $payloadUuid   : String, UUID associated with this payload
 *   $certUuid      : String, UUID associated with the included certificate
 *   $vpn_name      : String, the user-visible name for this VPN profile
 *   $server_address: String, the IP address or hostname of the VPN server
 * RESULT
 *   Returns a string containing Apple Profile key/value pairs to configure this
 *   mobile IPsec VPN on a client.
 ******/
function iep_generate_vpn($profId, $payloadUuid, $certUuid, $vpn_name = "", $server_address = "") {
	global $mobile_p1, $mobile_p2, $user, $ike, $authMethod, $extendedAuth, $setCertUuid, $auth_type, $ike_id, $auth_data;

	$ike = iep_get_vpn_type($mobile_p1['iketype']);
	$ikeDict = iep_generate_ike($profId, $certUuid, $server_address);
	$ipv4Dict = iep_key_data_str('OverridePrimary', 'integer', 1, VPNSCOPE + 1);

	$vpnAttrs =
	[
		[ 'key' => $ike, 'type' => 'dict',
		'value' => $ikeDict],
		[ 'key' => "IPv4", 'type' => 'dict',
		'value' => $ipv4Dict],
		[ 'key' => 'PayloadDescription', 'type' => 'string',
		'value' => 'Configures VPN settings, including authentication'],
		[ 'key' => 'PayloadDisplayName', 'type' => 'string',
		'value' => (empty($vpn_name) ? 'VPN (pfSense IPsec VPN)' : $vpn_name)],
		[ 'key' => 'PayloadIdentifier', 'type' => 'string',
		'value' => "{$profId}.remote-access"],
		[ 'key' => 'PayloadOrganization', 'type' => 'string',
		'value' => 'pfSense'],
		[ 'key' => 'PayloadType', 'type' => 'string',
		'value' => 'com.apple.vpn.managed'],
		[ 'key' => 'PayloadUUID', 'type' => 'string',
		'value' => $payloadUuid],
		[ 'key' => 'PayloadVersion', 'type' => 'integer',
		'value' => 1],
		[ 'key' => 'UserDefinedName', 'type' => 'string',
		'value' => (empty($vpn_name) ? 'pfSense IPsec VPN' : $vpn_name)],
		[ 'key' => 'VPNType', 'type' => 'string',
		'value' => $ike]
	];

	$vpnDict = "";
	foreach ($vpnAttrs as $attr) {
		$vpnDict .= iep_key_data_str($attr['key'], $attr['type'], $attr['value'], VPNSCOPE);
	}

	return $vpnDict;
}

/****f* iep_generate_ikev1
 * NAME
 *   iep_generate_ikev1 - Generate Apple compatible IKEv1 parameters
 * INPUTS
 *   None
 * RESULT
 *   Returns a string containing an IKEv1 configuration for this mobile IPsec
 *   VPN.
 ******/
function iep_generate_ikev1() {
	global $mobile_p1, $user, $ike, $authMethod, $extendedAuth, $setCertUuid, $auth_type, $ike_id, $auth_data;
	if ($authMethod == 'SharedSecret') {
		/* With hybrid auth, group/ID needs to end with "[hybrid]" */
		if (in_array($mobile_p1['authentication_method'], array("hybrid_rsa_server", "hybrid_cert_server"))) {
			$localId = $user . '[hybrid]';
		/* If an ID is not specifically set, use username */
		} elseif (!$ike_id) {
			$localId = $user;
		} else {
			$localId = $ike_id;
		}

		$ikeDict .= iep_key_data_str("LocalIdentifier", "string",
			$localId, IKESCOPE);
		$ikeDict .= iep_key_data_str("LocalIdentifierType",
			"string", "KeyID", IKESCOPE);
	}

	$ikeDict .= iep_key_data_str("XAuthEnabled", "integer",
		(int)$extendedAuth, IKESCOPE);
	if ($extendedAuth &&
	    !(in_array($mobile_p1['authentication_method'], array("xauth_rsa_server", "xauth_cert_server")))) {
		$ikeDict .= iep_key_data_str("XAuthName", "string", $user,
			IKESCOPE);
	}

	return $ikeDict;
}

/****f* iep_generate_ikev2
 * NAME
 *   iep_generate_ikev2 - Generate Apple compatible IKEv2 parameters
 * INPUTS
 *   $realAddr: String, IP address which corresponds to the real public IP
 *              address used by clients to connect.
 * RESULT
 *   Returns a string containing an IKEv2 configuration for this mobile IPsec
 *   VPN.
 ******/
function iep_generate_ikev2($realAddr) {
	global $config, $mobile_p1, $mobile_p2, $ike, $authMethod, $extendedAuth, $setCertUuid, $auth_type, $ike_id, $auth_data;
	/*
	 * https://wiki.strongswan.org/projects/strongswan/wiki/AppleIKEv2Profile
	 * set AuthenticationMethod, ExtendedAuthEnabled, PayloadCertifcateUUID
	 * based on the information from strongswan on how the client uses
	 * those.
	 */

	if ($mobile_p1['dpd_delay'] < 300) {
		$dpdLevel = "High";
	} elseif ($mobile_p1['dpd_delay'] < 900) {
		$dpdLevel = "Medium";
	} elseif (isset($mobile_p1['dpd_delay'])) {
		$dpdLevel = "Low";
	} else {
		$dpdLevel = "None";
	}

	$set_errors = iex_parameter_check('apple');
	if (!empty($set_errors)) {
		throw new Exception(sprintf(gettext('IPsec parameters not supported by Apple: %s'), implode(', ', $set_errors)));
	}

	if ($mobile_p1['certref']) {
		$cert   = lookup_cert($mobile_p1['certref']);
		$ca     = lookup_ca($cert['caref']);
		$caCn   = cert_get_cn($ca['crt']);
		$certCn = cert_get_cn($cert['crt']);

		$ikeDict .= iep_key_data_str("ServerCertificateIssuerCommonName",
			"string", $caCn, IKESCOPE);
		$ikeDict .= iep_key_data_str("ServerCertificateCommonName",
			"string", $certCn, IKESCOPE);
	}

	if ($mobile_p1['myid_type'] == "myaddress") {
		$remoteId = $realAddr;
	} else {
		$remoteId = $mobile_p1['myid_data'];
	}

	$ikeDict .= iep_key_data_str("LocalIdentifier", "string",
		$ike_id, IKESCOPE);
	$ikeDict .= iep_key_data_str("RemoteIdentifier", "string",
		$remoteId, IKESCOPE);
	$ikeDict .= iep_key_data_str("DeadPeerDetectionInterval", "string",
		$dpdLevel, IKESCOPE);
	$ikeDict .= iep_key_data_str("ExtendedAuthEnabled", "integer",
		(int)$extendedAuth, IKESCOPE);

	$ikeSADict = iex_parameter_convert('apple', array(1));
	/* On 2.4.x, IKE lifetime is in 'lifetime'
	 * On 2.5.x, it is 'over_time' plus the larger of 'rekey_time' or 'reauth_time'
	 */
	$p1life = (int)$mobile_p1['over_time'] + max((int)$mobile_p1['lifetime'], (int)$mobile_p1['rekey_time'], (int)$mobile_p1['reauth_time']);
	if (($p1life >= 600) &&
	    ($p1life <= 86400)) {
		$ikeSADict .= iep_key_data_str("LifeTimeInMinutes", "integer", ($p1life / 60), SASCOPE);
	}

	$ikeDict .= iep_key_data_str("IKESecurityAssociationParameters",
		"dict", $ikeSADict, IKESCOPE);

	$childSADict = iex_parameter_convert('apple', array(2));

	$ikeDict .= iep_key_data_str("ChildSecurityAssociationParameters",
		"dict", $childSADict, IKESCOPE);

	return $ikeDict;
}

/****f* iep_get_mobilekey
 * NAME
 *   iep_get_mobilekey - Searches IPsec key list for a specific entry.
 * INPUTS
 *   $ident  : String, the identifier for the key to find
 *   $keyType: String, the type of key to find ('PSK', 'EAP')
 * RESULT
 *   Returns a string containing the pre-shared key for the given identifier and
 *   key type, if found. Otherwise returns null.
 ******/
function iep_get_mobilekey($ident, $keytype) {
	global $config;

	if (is_array($config['ipsec']['mobilekey'])) {
		foreach ($config['ipsec']['mobilekey'] as $key) {
			if (($key['ident'] == $ident) && ($key['type'] == $keytype)) {
				return $key['pre-shared-key'];
			}
		}
	}

	return null;
}

/****f* iep_get_auth_info
 * NAME
 *   iep_get_auth_info - Determines IPsec authentication information for the
 *                       mobile IPsec VPN.
 * INPUTS
 *   None
 * RESULT
 *   Array:
 *     $ike         : String, IKE type ('IKEv2' or 'IPSec')
 *     $authMethod  : String, Authentication method, either 'Certificate' or
 *                    'SharedSecret'
 *     $extendedAuth: Boolean, whether or not to enable Extended Authentication
 *     $setCertUuid : Boolean, whether or not to include a certificate UUID
 ******/
function iep_get_auth_info() {
	global $mobile_p1;
	$ike = iep_get_vpn_type($mobile_p1['iketype']);

	if ($ike == "IPSec") {
		$certAuthMethods = array(
			"xauth_rsa_server",
			"xauth_cert_server",
			"rsasig"
		);
		$extendedAuthMethods = array(
			"hybrid_rsa_server",
			"hybrid_cert_server",
			"xauth_rsa_server",
			"xauth_cert_server",
			"xauth_psk_server"
		);
		$certUuidMethods = array(
			"rsasig",
			"xauth_rsa_server",
			"xauth_cert_server"
		);
	} else {
		$certAuthMethods = array(
			"eap-tls",
			"eap-mschapv2",
			"eap-radius",
			"hybrid_rsa_server",
			"xauth_rsa_server",
			"xauth_cert_server",
			"rsasig"
		);
		$extendedAuthMethods = array(
			"eap-tls",
			"eap-mschapv2",
			"eap-radius"
		);
		$certUuidMethods = array(
			"rsasig",
			"eap-tls",
			"xauth_rsa_server",
			"xauth_cert_server"
		);
	}

	/* try to determine certificate or shared secret */
	$authMethod = (in_array($mobile_p1['authentication_method'], $certAuthMethods)) ? "Certificate" : "SharedSecret";
	$extendedAuth = in_array($mobile_p1['authentication_method'], $extendedAuthMethods);
	$setCertUuid = in_array($mobile_p1['authentication_method'], $certUuidMethods);

	return array($ike, $authMethod, $extendedAuth, $setCertUuid);
}

/****f* iep_generate_ike
 * NAME
 *   iep_generate_ike - Forms an Apple compatible set of IKE parameters for th
 *                      mobile IPsec VPN.
 * INPUTS
 *   $profId        : String, Internal ID of the VPN profile
 *   $certUuid      : String, UUID associated with the included certificate
 *   $server_address: String, the IP address or hostname of the VPN server
 * RESULT
 *   Returns a string containing a compatible set of IKE paramters, in Apple
 *   profile key/value format.
 ******/
function iep_generate_ike($profId, $certUuid, $server_address = "") {
	global $mobile_p1, $mobile_p2;

	list($realAddr, $pubAddr) = iep_get_addresses($mobile_p1['interface']);
	if (!empty($server_address)) {
		$pubAddr = $server_address;
	}

	global $user, $ike, $authMethod, $extendedAuth, $setCertUuid, $auth_type, $ike_id, $auth_data, $enablepfs;

	/* If no peerid data is explicitly set, the client will send it's
	 * IP address as the identifier. When using certificate authentication
	 * strongswan will fail the authentication if the client ID is not
	 * equal to either the CN of the certificate (which must resolve in
	 * DNS) or a value populated in an altSubjectName of the certificate
	 */
	if ($setCertUuid) {
		$mobile_p1['peerid_data'] = $ike_id;
	}

	/* Generate the pieces that are specific to the IKE version */
	if ($ike == "IPSec") {
		$ikeDict = iep_generate_ikev1();
	} else {
		$ikeDict = iep_generate_ikev2($realAddr);
		$ikeDict .= iep_key_data_str("EnablePFS", "boolean", $enablepfs, IKESCOPE);
	}

	$ikeDict .= iep_key_data_str("AuthenticationMethod", "string", $authMethod,
		IKESCOPE);
	$ikeDict .= iep_key_data_str("RemoteAddress", "string", $pubAddr, IKESCOPE);

	if ($setCertUuid) {
		$ikeDict .= iep_key_data_str("PayloadCertificateUUID", "string",
			$certUuid, IKESCOPE);

		/* ECDSA certificates are only supported on pfSense 2.5.0 or later.
		 * Default CertificateType is RSA, so this only needs added for
		 * ECDSA certificates. */
		if (function_exists('cert_get_pkey_curve')) {
			$curve = cert_get_pkey_curve($auth_data['prv']);
			if ($curve) {
				$certtype = 'ECDSA';
				switch ($curve) {
					case 'prime256v1':
						$certtype .= '256';
						break;
					case 'secp384r1':
						$certtype .= '384';
						break;
					case 'secp521r1':
						$certtype .= '521';
						break;
					default:
						/* Unsupported Curve */
						$certtype = '';
						break;
				}
				if (!empty($certtype)) {
					$ikeDict .= iep_key_data_str("CertificateType", "string",
							$certtype, IKESCOPE);
				}
			}
		}
	}

	if ($authMethod == 'SharedSecret') {
		$defaultPsk = iep_get_mobilekey('any', 'PSK');
		if (!empty($auth_data)) {
			$encPsk = base64_encode($auth_data);
		} elseif (!empty($defaultPsk)) {
			$encPsk = base64_encode($defaultPsk);
		} elseif (!empty($mobile_p1['pre-shared-key'])) {
			$encPsk = base64_encode($mobile_p1['pre-shared-key']);
		}

		if (is_null($encPsk)) {
			$encPsk = "PSK Required but No suitable PSK found";
		}
		$pskIndent = str_repeat("\t", IKESCOPE);

		$ikeDict .= iep_key_data_str("SharedSecret", "data", $pskIndent . $encPsk . "\n", IKESCOPE);
	}

	$ikeDict .= iep_key_data_str("OnDemandEnabled", "integer", 0, IKESCOPE);

	return $ikeDict;
}


/****f* iep_generate_ca_config
 * NAME
 *   iep_generate_ca_config - Generate an Apple profile section for a CA
 *                            certificate.
 * INPUTS
 *   $certref: String, certificate reference ID for the server certificate
 *   $profId : String, Internal ID of the VPN profile
 *   $uuid   : String, UUID to use for this certificate
 * RESULT
 *   Returns a string containing an Apple profile section containing the CA
 *   certificate and its associated settings.
 ******/
function iep_generate_ca_config($certref, $profId, $uuid) {
	global $config, $certLineChars;

	$caIndent = str_repeat("\t", CASCOPE);

	if (!isset($certref)) {
		return "";
	}

	$vpnCert = lookup_cert($certref);
	$cacert = lookup_ca($vpnCert['caref']);

	if (!$cacert) {
		return "";
	}

	$filename = urlencode("{$cacert['descr']}.crt");
	/* split the base64 encoded cert into lines that fit onto a terminal */
	$certData = implode("\n{$caIndent}", str_split($cacert['crt'], $certLineChars));
	$certData = $caIndent . $certData . "\n";

	$caAttrs = [

		[ 'key' => 'PayloadCertificateFileName', 'type' => 'string',
		'value' => $filename ],
		[ 'key' => 'PayloadContent', 'type' => 'data',
		'value' => $certData ],
		[ 'key' => 'PayloadDescription', 'type' => 'string',
		'value' =>
		'Provides device authentication (certificate or identity).' ],
		[ 'key' => 'PayloadDisplayName', 'type' => 'string',
		'value' => $cacert['descr'] ],
		[ 'key' => 'PayloadIdentifier', 'type' => 'string',
		'value' => "{$profId}.certs1.credential1" ],
		[ 'key' => 'PayloadOrganization', 'type' => 'string',
		'value' => 'pfSense' ],
		[ 'key' => 'PayloadType', 'type' => 'string',
		'value' => 'com.apple.security.root' ],
		[ 'key' => 'PayloadUUID', 'type' => 'string',
		'value' => $uuid ],
		[ 'key' => 'PayloadVersion', 'type' => 'integer', 'value' => 1 ]
	];

	$caDict = "";
	foreach ($caAttrs as $attr) {
		$caDict .= iep_key_data_str($attr['key'], $attr['type'], $attr['value'], CASCOPE);
	}

	return $caDict;
}

/****f* iep_generate_p12
 * NAME
 *   iep_generate_p12 - Convert a given certificate to PKCS#12 format
 * INPUTS
 *   $vpnCert: String, the user certificate used for this bundle
 *   $passwd : String, the password used to protect this bundle
 * RESULT
 *   A string containing a PKCS#12 bundle archive containing the given user
 *   certificate.
 ******/
function iep_generate_p12($vpnCert, $passwd) {
	/* specific requirements of iOS - the CA cert can not be included
	 * as an "extra" cert like it normally is when using the "export p12"
	 * button in the Certificate Manager. The p12 has to have a password
	 * on it or the profile will fail to import.
	 */
	$args = array(
		'friendly_name' => $vpnCert['descr']
	);

	$p12crt = openssl_x509_read(base64_decode($vpnCert['crt']));
	$p12key = openssl_pkey_get_private(base64_decode($vpnCert['prv']), "");
	$p12data = "";
	openssl_pkcs12_export($p12crt, $p12data, $p12key, $passwd, $args);

	$p12txt = base64_encode($p12data);

	return $p12txt;
}

/****f* iep_generate_ca_config
 * NAME
 *   iep_generate_ca_config - Generate an Apple profile section for a user
 *                            certificate.
 * INPUTS
 *   $profId : String, Internal ID of the VPN profile
 *   $uuid   : String, UUID to use for this certificate
 * RESULT
 *   Returns a string containing an Apple profile section containing the user
 *   certificate P12 and its associated settings.
 ******/
function iep_generate_cert_data($profId, $uuid) {
	global $config, $certLineChars;
	global $ike, $authMethod, $extendedAuth, $setCertUuid, $auth_type, $ike_id, $auth_data;

	$certIndent = str_repeat("\t", CERTSCOPE);

	$p12passwd = iex_random_pass();
	if ($auth_data) {
		$p12file = urlencode("{$auth_data['descr']}.p12");
		$p12txt = iep_generate_p12($auth_data, $p12passwd);
		/* keep the encoded cert from being one gigantic line */
		$p12txt = implode("\n{$certIndent}", str_split($p12txt, $certLineChars));
		$p12txt = $certIndent . $p12txt . "\n";
	} else {
		return "";
	}

	$certAttrs =
	[
		[ 'key' => 'Password', 'type' => 'string',
		'value' => $p12passwd ],
		[ 'key' => 'PayloadCertificateFileName', 'type' => 'string',
		'value' => $p12file ],
		[ 'key' => 'PayloadContent', 'type' => 'data',
		'value' => $p12txt ],
		[ 'key' => 'PayloadDescription', 'type' => 'string',
		'value' => 'Provides device authentication (certificate or identity).' ],
		[ 'key' => 'PayloadDisplayName', 'type' => 'string',
		'value' => $p12file ],
		[ 'key' => 'PayloadIdentifier', 'type' => 'string',
		'value' => "{$profId}.certs1.credential" ],
		[ 'key' => 'PayloadOrganization', 'type' => 'string',
		'value' => 'pfSense' ],
		[ 'key' => 'PayloadType', 'type' => 'string',
		'value' => "com.apple.security.pkcs12" ],
		[ 'key' => 'PayloadUUID', 'type' => 'string',
		'value' => $uuid ],
		[ 'key' => 'PayloadVersion', 'type' => 'integer', 'value' => 1 ]
	];

	$certDict = "";
	foreach ($certAttrs as $attr) {
		$certDict .= iep_key_data_str($attr['key'], $attr['type'], $attr['value'], CERTSCOPE);
	}

	return $certDict;
}

/****f* iep_get_profile_id
 * NAME
 *   iep_get_profile_id - Generate an Apple profile identifier for this router
 * INPUTS
 *   None
 * RESULT
 *   Returns a string containing a profile identifier for this router in reverse
 *   domain format (e.g. 'router.example.com' => 'com.example.router.vpn')
 ******/
function iep_get_profile_id() {
	/*  */
	$hostname = gethostname();
	$pieces = explode('.', $hostname);
	$profId = '';
	while (count($pieces) > 0) {
		$lastElem = array_pop($pieces);
		$profId .= "{$lastElem}.";
	}
	$profId .= "vpn";

	return $profId;
}

/****f* iep_generate_payload
 * NAME
 *   iep_generate_payload - Generate an Apple profile VPN payload for this VPN
 * INPUTS
 *   $vpn_name      : String, the user-visible name for this VPN profile
 *   $server_address: String, the IP address or hostname of the VPN server
 *   $user          : String, the username, key name, certificate reference ID
 *                    for this VPN profile.
 * RESULT
 *   Returns a string containing a VPN payload data in Apple compatible
 *   key/value format.
 ******/
function iep_generate_payload($vpn_name, $server_address, $user = null) {
	global $mobile_p1, $mobile_p2;

	$uuids = iep_get_uuids();
	$profId = iep_get_profile_id();
	$vpnType = iep_get_vpn_type($mobile_p1['iketype']);

	global $ike, $authMethod, $extendedAuth, $setCertUuid, $auth_type, $ike_id, $auth_data;
	list($ike, $authMethod, $extendedAuth, $setCertUuid) = iep_get_auth_info();
	list($auth_type, $ike_id, $auth_data) = iep_get_client_auth($user);

	if (in_array($auth_type, array('user', 'hybrid')) && ($user != '<external>')) {
		$userentry = getUserEntry((($user === null) ? $_SESSION['Username'] : $user));
	}

	$vpnDict = iep_generate_vpn($profId, $uuids[IPSECIDX],
		$uuids[PKCS12IDX], $vpn_name, $server_address);
	$caDict  = iep_generate_ca_config($mobile_p1['certref'], $profId,
		$uuids[CAIDX]);

	$contentList = iep_key_data_str(null, "dict", $vpnDict, PAYLOADSCOPE);
	$contentList .= iep_key_data_str(null, "dict", $caDict, PAYLOADSCOPE);

	if ($setCertUuid) {
		$certDict = iep_generate_cert_data($profId, $uuids[PKCS12IDX]);
		$contentList .= iep_key_data_str(null, "dict", $certDict, PAYLOADSCOPE);
	}

	$payloadAttrs =
	[
		[ 'key' => 'PayloadContent', 'type' => 'array',
		'value' => $contentList ],
		[ 'key' => 'PayloadDescription', 'type' => 'string',
		'value' => "Contains configuration settings for connecting to VPN on pfSense firewall and VPN appliance" ],
		[ 'key' => 'PayloadDisplayName', 'type' => 'string',
		'value' => empty($vpn_name) ? "pfSense remote access VPN configuration" : $vpn_name ],
		[ 'key' => 'PayloadIdentifier', 'type' => 'string',
		'value' => $profId ],
		[ 'key' => 'PayloadOrganization', 'type' => 'string',
		'value' => "pfSense" ],
		[ 'key' => 'PayloadRemovalDisallowed', 'type' => 'boolean',
		'value' => false ],
		[ 'key' => 'PayloadType', 'type' => 'string',
		'value' => 'Configuration' ],
		[ 'key' => 'PayloadUUID', 'type' => 'string',
		'value' => $uuids[PROFILEIDX] ],
		[ 'key' => 'PayloadVersion', 'type' => 'integer',
		'value' => 1 ]
	];

	$payloadTxt = "";
	foreach ($payloadAttrs as $attr) {
		$payloadTxt .= iep_key_data_str($attr['key'], $attr['type'],
			$attr['value'], PROFILESCOPE);
	}

	return $payloadTxt;
}

/****f* iep_generate_profile
 * NAME
 *   iep_generate_profile - Generate an Apple profile VPN profile container
 *                          which includes VPN profile data.
 *   $vpn_name      : String, the user-visible name for this VPN profile
 *   $server_address: String, the IP address or hostname of the VPN server
 *   $user          : String, the username, key name, certificate reference ID
 *                    for this VPN profile.
 * RESULT
 *   Returns a string containing a complete Apple compatible VPN profile.
 ******/
function iep_generate_profile($vpn_name, $server_address, $user = null) {
	global $mobile_p1, $mobile_p2;

	$payloadTxt = iep_generate_payload($vpn_name, $server_address, $user);

	$profileTxt =
'<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
';
	$profileTxt .= iep_key_data_str(null, "dict", $payloadTxt, BODYSCOPE);
	$profileTxt .=  "</plist>\n";

	return $profileTxt;
}
