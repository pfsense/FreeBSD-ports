<?php
/*
 * vpn_ipsec_export_win.php
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

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");
require_once("classes/Form.class.php");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("certs.inc");

$input_errors = array();

global $config, $mobileconfig, $a_phase1, $a_phase2, $a_cert;

init_config_arr(array('ipsec', 'client'));
init_config_arr(array('ipsec', 'phase1'));
init_config_arr(array('ipsec', 'phase2'));
init_config_arr(array('cert'));

$mobileconfig = &$config['ipsec']['client'];
$a_phase1 = &$config['ipsec']['phase1'];
$a_phase2 = &$config['ipsec']['phase2'];
$a_cert = $config['cert'];

/* These lists contain the various values supported _by Windows_ indexed by
 * the equivalent value found in the pfSense configuration.
 * The values were obtained from Microsoft documentation:
 *   https://docs.microsoft.com/en-us/powershell/module/vpnclient/set-vpnconnectionipsecconfiguration?view=win10-ps */

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

/****f* iew_server_list
 * NAME
 *   iew_server_list - Generate a list of valid server addresses from the
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
function iew_server_list($auto=false) {
	global $mobile_p1;

	$servercert = lookup_cert($mobile_p1['certref']);
	$server_list = array();

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

	/* If no viable entries were found in the SAN list, fall back to using
	 * the interface address chosen in the Mobile IPsec P1 */
	if (empty($server_list)) {
		$server_list[] = ipsec_get_phase1_src($mobile_p1);
	}

	/* For automatic mode, return the first entry only. Otherwise, return
	 * the full list. */
	return ($auto) ? $server_list[0] : $server_list;
}

/****f* iew_parameter_check
 * NAME
 *   iew_parameter_check - Check Mobile P1/P2 values for compatibility with
 *                         Windows clients.
 * INPUTS
 *   None
 * RESULT
 *   Returns an array containing any errors found during the compatibility
 *   check.
 ******/
function iew_parameter_check() {
	global $mobile_p1, $mobile_p2, $win_supported_map;
	$errors = array();
	$p1errors = array();
	$p1_ok = false;

	/* Check each P1 encryption configuration line */
	foreach ($mobile_p1['encryption']['item'] as $mp1e) {
		$p1err = iew_check_p1($mp1e);
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

	/* Check the mobile Phase 2 entries and collect errors */
	foreach ($mobile_p2 as $mp2) {
		$errors = array_merge($errors, iew_check_p2($mp2));
	}
	return $errors;
}

/****f* iew_parameter_convert
 * NAME
 *   iew_parameter_convert - Convert pfSense Mobile IPsec P1/P2 settings to
 *                           equivalent parameters for use with Windows
 *                           PowerShell Set-VpnConnectionIPsecConfiguration
 * INPUTS
 *   None
 * RESULT
 *   Returns a string containing appropriate options which can be passed to
 *   Set-VpnConnectionIPsecConfiguration which match settings in the pfSense
 *   configuration.
 ******/
function iew_parameter_convert() {
	global $mobile_p1, $mobile_p2, $win_supported_map;
	$options = "";

	/* Check each P1 encryption configuration line and attempt conversion */
	foreach ($mobile_p1['encryption']['item'] as $mp1e) {
		/* Attempt to convert this P1 encryption line */
		$p1opt = iew_check_p1($mp1e, true);
		/* If the conversion is successful, add the converted options
		 * and stop processing P1 encryption entries. */
		if (!empty($p1opt)) {
			$options .= $p1opt . " `\r\n";
			break;
		}
	}

	/* Check the mobile Phase 2 entries and attempt conversion */
	foreach ($mobile_p2 as $mp2) {
		/* Attempt to convert the values on this P2 */
		$p2opt = iew_check_p2($mp2, true);
		/* If the conversion is successful, add the converted options
		 * and stop processing P2 entries. */
		if (!empty($p2opt)) {
			$options .= $p2opt . " `\r\n";
			break;
		}
	}

	return $options;
}

/****f* iew_convert_value
 * NAME
 *   iew_convert_value - Checks if a specific pfSense IPsec configuration
 *                       component is supported by Windows.
 * INPUTS
 *   $want:      String, a pfSense configuration parameter to check.
 *   $supported: Array, the appropriately matched area of $win_supported_map to
 *               check, such as: $win_supported_map[<phase>][<type>]['values']
 * RESULT
 *   Returns a string containing a compatible Windows value for a given pfSense
 *   configuration parameter, or null if no compatible match could be located.
 ******/
function iew_convert_value($want, $supported) {
	if (array_key_exists($want, $supported)) {
		return $supported[$want];
	} else {
		return null;
	}
}

/****f* iew_check_p1
 * NAME
 *   iew_check_p1 - Checks and optionally converts pfSense Mobile IPsec P1
 *                  parameters to those compatible with Windows.
 * INPUTS
 *   $p1:        Array, a pfSense mobile P1 configuration
 *   $convert:   Boolean (default: False), whether to return the converted
 *               parameters or an array of errors.
 * RESULT
 *   When $convert is false, returns an array of errors detected when checking
 *   parameters.
 *   When $convert is true, returns a string containing Windows-compatible
 *   parameters and values.
 ******/
function iew_check_p1($p1, $convert=false) {
	global $win_supported_map;
	$p1errors = array();
	$options = "";

	/* Must match a full P1 encryption algorithm entry */

	/* Windows doesn't have a separate mechanism to specify key lengths, so form a single string for AES */
	if ($p1['encryption-algorithm']['name'] == 'aes') {
		$ealgo = "{$p1['encryption-algorithm']['name']}-{$p1['encryption-algorithm']['keylen']}";
	} else {
		$ealgo = $p1['encryption-algorithm']['name'];
	}

	/* Check P1 Encryption Algorithm */
	if (iew_convert_value($ealgo, $win_supported_map['p1']['enc']['values'])) {
		$options .= " {$win_supported_map['p1']['enc']['param']} {$win_supported_map['p1']['enc']['values'][$ealgo]}";
	} else {
		$p1errors[] = sprintf(gettext("Phase 1 Encryption Algorithm unsupported by Windows. Supported values are (%s)"), implode(', ', array_keys($win_supported_map['p1']['enc']['values'])));
	}

	/* Check/Convert P1 Hash Algorithm */
	if (iew_convert_value($p1['hash-algorithm'], $win_supported_map['p1']['hash']['values'])) {
		$options .= " {$win_supported_map['p1']['hash']['param']} {$win_supported_map['p1']['hash']['values'][$p1['hash-algorithm']]}";
	} else {
		$p1errors[] = sprintf(gettext("Phase 1 Hash Algorithm unsupported by Windows. Supported values are (%s)"), implode(', ', array_keys($win_supported_map['p1']['hash']['values'])));
	}

	/* Check/Convert P1 DH Group */
	if (iew_convert_value($p1['dhgroup'], $win_supported_map['p1']['dh']['values'])) {
		$options .= " {$win_supported_map['p1']['dh']['param']} {$win_supported_map['p1']['dh']['values'][$p1['dhgroup']]}";
	} else {
		$p1errors[] = sprintf(gettext("Phase 1 DH Group unsupported by Windows. Supported values are (%s)"), implode(', ', array_keys($win_supported_map['p1']['dh']['values'])));
	}

	/* If converting, return option string. Otherwise, return error list. */
	if ($convert) {
		return $options;
	} else {
		return $p1errors;
	}
}

/****f* iew_check_p2
 * NAME
 *   iew_check_p2 - Checks and optionally converts pfSense Mobile IPsec P2
 *                  parameters to those compatible with Windows.
 * INPUTS
 *   $p1:        Array, a pfSense mobile P2 configuration
 *   $convert:   Boolean (default: False), whether to return the converted
 *               parameters or an array of errors.
 * RESULT
 *   When $convert is false, returns an array of errors detected when checking
 *   parameters.
 *   When $convert is true, returns a string containing Windows-compatible
 *   parameters and values.
 ******/
function iew_check_p2($p2, $convert=false) {
	global $mobileconfig, $win_supported_map;
	$p2errors = array();
	/* Only need to find one match for each item, not a complete match */

	/* Check/Convert P2 ealgo -- Array */
	$p2e_errors = array();
	$p2e_ok = false;
	foreach ($p2['encryption-algorithm-option'] as $p2e) {
		/* Windows doesn't have a separate mechanism to specify key lengths, so form a single string for AES */
		if ($p2e['name'] == 'aes') {
			$ealgo = "{$p2e['name']}-{$p2e['keylen']}";
		} else {
			$ealgo = $p2e['name'];
		}
		if (iew_convert_value($ealgo, $win_supported_map['p2']['enc']['values'])) {
			$p2e_ok = true;
			$options .= " {$win_supported_map['p2']['enc']['param']} {$win_supported_map['p2']['enc']['values'][$ealgo]}";
			break;
		} else {
			$p2e_errors[] = sprintf(gettext("Phase 2 Encryption Algorithm unsupported by Windows. Supported values are (%s)"), implode(', ', array_keys($win_supported_map['p2']['enc']['values'])));
		}
	}
	if (!$p2e_ok) {
		$p2errors = array_merge($p2errors, $p2e_errors);
	}

	/* Check/Convert P2 halgo -- Array */
	$p2h_errors = array();
	$p2h_ok = false;
	foreach ($p2['hash-algorithm-option'] as $p2h) {
		if (iew_convert_value($p2h, $win_supported_map['p2']['hash']['values'])) {
			$p2h_ok = true;
			$options .= " {$win_supported_map['p2']['hash']['param']} {$win_supported_map['p2']['hash']['values'][$p2h]}";
			break;
		} else {
			$p2h_errors[] = sprintf(gettext("Phase 2 Hash Algorithm unsupported by Windows. Supported values are (%s)"), implode(', ', array_keys($win_supported_map['p2']['hash']['values'])));
		}
	}

	if (!$p2h_ok) {
		$p2errors = array_merge($p2errors, $p2h_errors);
	}

	/* Check/Convert P2 pfs */
	$pfs = (!empty($mobileconfig['pfs_group'])) ? $mobileconfig['pfs_group'] : $p2['pfsgroup'];

	if (iew_convert_value($pfs, $win_supported_map['p2']['dh']['values'])) {
		$options .= " {$win_supported_map['p2']['dh']['param']} {$win_supported_map['p2']['dh']['values'][$pfs]}";
	} else {
		$p2errors[] = sprintf(gettext("Phase 2 PFS Group unsupported by Windows. Supported values are (%s)"), implode(', ', array_keys($win_supported_map['p2']['dh']['values'])));
	}

	/* If converting, return option string. Otherwise, return error list. */
	if ($convert) {
		return $options;
	} else {
		return $p2errors;
	}
}

/****f* ipsec_export_win
 * NAME
 *   ipsec_export_win - Creates an archive containing certificates and a Windows
 *                      PowerShell script which will import certificates and VPN
 *                      settings into a Windows client.
 * INPUTS
 *   $vpn_name:       The name used for the archive as well as for the Windows
 *                    VPN instance.
 *   $server_address: The hostname or IP address to which the client must
 *                    connect.
 *   $user_certref:   For EAP-TLS, a certificate refid for the specific end-user
 *                    certificate to include in the archive.
 * RESULT
 *   Creates and sends an archive to the user (browser download) containing all
 *   of the files and commands necessary to configure a Windows VPN client.
 ******/
function ipsec_export_win($vpn_name, $server_address, $user_certref = null) {
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

		/* Random Password */
		$keyoutput = "";
		$keystatus = "";
		exec("/bin/dd status=none if=/dev/random bs=4096 count=1 | /usr/bin/openssl sha224 | /usr/bin/cut -f2 -d' '", $keyoutput, $keystatus);
		$password = $keyoutput[0];

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
	$set_errors = iew_parameter_check();
	if (!empty($set_errors)) {
		/* Cleanup unfinished work */
		@rmdir_recursive($script_dir);
		throw new Exception(sprintf(gettext('IPsec parameters not supported by Windows: %s'), implode(', ', $set_errors)));
	}
	$script .= "{$nl}# Set VPN Config{$nl}";
	$script .= "Set-VpnConnectionIPsecConfiguration -Name \"{$vpn_name}\" `{$nl}";
	$script .= iew_parameter_convert();
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
	send_user_download('file', str_replace(' ', '_', $zname));

	/* Cleanup */
	@rmdir_recursive($script_dir);
	@unlink_if_exists($zname);

	return $script;
}

/* Locate Mobile Phase 1 */
$mobile_p1 = array();
foreach ($a_phase1 as $p1) {
	if (isset($p1['mobile'])) {
		$mobile_p1 = $p1;
	}
}

if (empty($mobile_p1)) {
	$input_errors[] = gettext('There is no Mobile Phase 1 in the IPsec configuration.');
} elseif ($mobile_p1['iketype'] != 'ikev2') {
	$input_errors[] = gettext('Mobile Phase 1 is not IKEv2. This utility only supports IKEv2.');
} elseif (substr($mobile_p1['authentication_method'], 0, 3) != 'eap') {
	$input_errors[] = gettext('Mobile Phase 1 Authentication Method is not EAP. This utility only supports EAP Methods.');
} else {
	/* Locate Mobile Phase 2 entries */
	$mobile_p2 = array();
	foreach ($a_phase2 as $p2) {
		if ($p2['ikeid'] == $mobile_p1['ikeid']) {
			$mobile_p2[] = $p2;
		}
	}
	if (empty($mobile_p2)) {
		$input_errors[] = gettext('There are no Mobile Phase 2 entries in the IPsec configuration.');
	} else {
		foreach ($mobile_p2 as $mp2) {
			if (!in_array($mp2['mode'], array('tunnel', 'tunnel6'))) {
				$input_errors[] = gettext('Mobile Phase 2 entry modes must all be "Tunnel".');
				break;
			}
		}
	}
}

$set_errors = iew_parameter_check();
if (!empty($set_errors)) {
	$input_errors = array_merge($input_errors, $set_errors);
}

$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("IKEv2 Export for Windows"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "ipsec";

if ($_POST && empty($input_errors)) {
	try {
		/* Use the user-supplied VPN name or a simple default if it was empty */
		$vpn_name = (!empty($_POST['name'])) ? $_POST['name'] : "Mobile IPsec ({$config['system']['hostname']})";
		/* Ensure it's only a filename, not a path */
		$vpn_name = basename($vpn_name);

		/* If the user submitted a valid host, use it, otherwise force automatic mode */
		if (empty($_POST['server_address']) || ($_POST['server_address'] == 'Auto') ||
		    (!is_hostname($_POST['server_address']) && !is_ipaddr($_POST['server_address']))) {
			$server_address = iew_server_list(true);
		} else {
			$server_address = $_POST['server_address'];
		}

		/* Set the user cert if using EAP-TLS mode */
		$user_certref = ($mobile_p1['authentication_method'] == 'eap-tls') ? $_POST['user_certref'] : null;

		/* Export the config */
		ipsec_export_win($vpn_name, $server_address, $user_certref);
	} catch (Exception $e) {
		$input_errors[] = sprintf(gettext('Could not export IPsec VPN: %s'), $e->getMessage());
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
?>
<?php
/* User Options */
$form = new Form(false);
$section = new Form_Section('IKEv2 Export Settings');

/* Name - Pre-fill with "pfSense-<P1 Descr>", if P1 descr is blank use hostname */
$section->addInput(new Form_Input(
	'name',
	'VPN Name',
	'text',
	"VPN ({$config['system']['hostname']}) - " . (!empty($mobile_p1['descr']) ? $mobile_p1['descr'] : 'Mobile IPsec')
))->setHelp('The name of the VPN as seen by the client in their network list. ' .
		'This name is also used when creating the download archive.');

/* Server Address - Select from server cert SAN entries or 'auto' */
$server_list = iew_server_list();
array_unshift($server_list, 'Auto');
$section->addInput(new Form_Select(
	'server_address',
	'Server Address',
	null,
	array_combine($server_list, $server_list)
))->setHelp('Select the server address to be used by the client. ' .
		'This list is generated from the SAN entries on the server certificate. ' .
		'Windows requires the server address be present in the server certificate SAN list.');

/* For EAP-TLS, pick a specific cert */
if ($mobile_p1['authentication_method'] == 'eap-tls') {
	/* Collect user cert list from the Peer Certificate Authority */
	$tls_client_list = array();
	foreach ($a_cert as $crt) {
		if (($mobile_p1['caref'] == $crt['caref']) && !empty($crt['prv'])) {
			$tls_client_list[$crt['refid']] = $crt['descr'];
		}
	}
	$section->addInput(new Form_Select(
		'user_certref',
		'TLS User Certificate',
		null,
		$tls_client_list
	))->setHelp('Select a TLS client certificate to include in the download archive. ');
}

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Download',
	null,
	'fa-download'
))->addClass('btn-primary');

print($form);
?>

<div class="infoblock blockopen">
<?php
print_info_box(
'<p>' . gettext('This page generates an archive with a Windows PowerShell script and certificate files.') . ' ' .
gettext('The commands in the PowerShell script will import certificates and setup the VPN on the client workstation.') . '<p>' .
'<p>' . gettext('Running PowerShell scripts on Windows is disabled by default, but local policies may override that behavior.') . ' ' .
sprintf(gettext('See the %1$sPowerShell Execution Policies Documentation%2$s for details.'),
	'<a href="https://go.microsoft.com/fwlink/?LinkID=135170">', '</a>') . ' ' .
gettext('If scripting is disabled, the commands may be copied and pasted into a PowerShell prompt.') . '<p>' .
'<p>' . gettext('Some commands may require Administrator access, such as importing the CA certificate.') . ' ' .
gettext('Run these commands at an Administrator-level PowerShell prompt or use an alternate method.') . '<p>' .
'<p>' . gettext('If the <strong>Network List</strong> option is active on the <strong>Mobile Clients</strong> tab,') . ' ' .
gettext('the script will include parameters to setup Split Tunneling on the client as well as commands to') . ' ' .
gettext('configure routes on the VPN for networks configured in the mobile Phase 2 entries.') . '<p>' .
'<p>' . gettext('This utility checks configured Mobile Phase 1 and Phase 2 entries and attempts to locate a set of') . ' ' .
gettext('parameters which are compatible with Windows clients. It uses the first match it finds, so order choices') . ' ' .
gettext('in the Phase 1 and Phase 2 list appropriately or manually edit the resulting script as needed.') . ' ' .
sprintf(gettext('For a full list of compatible parameters, see the %1$sMicrosoft Documentation for Set-VpnConnectionIPsecConfiguration%2$s'),
	'<a href="https://docs.microsoft.com/en-us/powershell/module/vpnclient/set-vpnconnectionipsecconfiguration?view=win10-ps">', '') . '<p>'
, 'info', false);
?>

</div>

<?php if ($mobile_p1['authentication_method'] == 'eap-tls'): ?>
<?= print_info_box(gettext("If a TLS client is missing from the list it is likely due to a CA mismatch " .
				"between the IPsec Peer Certificate Authority and the client certificate, " .
				"or the client certificate does not exist on this firewall.")); ?>
<?php endif; ?>
<?php
include("foot.inc");
