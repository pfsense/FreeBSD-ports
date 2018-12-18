<?php
/*
 * vpn_ipsec_profile.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
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

require("guiconfig.inc");

global $config;

$ipsecProfile = 'remote-access-ipsec.mobileconfig';

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

$certLineChars = 52;


/*
 * return an array with the actual IP address of the interface in the first
 * element and for AWS/OpenStack hosts, the second public NAT IP in the second
 *  element
 */
function get_addresses($intf) {

	$addresses = array();
	$sysIntf = get_real_interface($intf);
	$ipaddr  = get_interface_ip($intf);
	$addresses[] = $ipaddr;

	/* AWS hosts will get a file under /var/db with the public IP */
	$natIpCache = "/var/db/natted_${sysIntf}_ip";
	if (file_exists($natIpCache)) {
		$natTxt = file_get_contents($natIpCache);
		$natIpAddr = rtrim($natTxt);
		$addresses[] = $natIpAddr;
	}

	return $addresses;
}

function get_vpn_type($iketype) {

	/* convert pfSense stored value to the iOS profile tag */
	if ($iketype == "ikev2") {
		$vpnType = "IKEv2";
	} else {
		$vpnType = "IPSec";
	}
	return $vpnType;
}

function get_uuids($num=4) {

	$uuids = array();

	/* generate uuids */
	exec("/bin/uuidgen -n $num", $uuids);

	return $uuids;
}

function get_cert_client_id($caref, $user) {

	/* search current user certs for one signed by right CA */
	$certids = $user['cert'];
	if (is_array($certids)) {
		foreach ($certids as $certid) {
			$cert = lookup_cert($certid);
			if ($cert['caref'] === $caref) {
				$vpnCert = $cert;
				break;
			}
		}
	}

	/* Editorial note -
	 * This would be much easier if you could use the DN/subject as the
	 * identifier, but iOS parses DN's incorrectly and sends them as the
	 * wrong type */

	/* parse cert, take the value of the first subjectAltName field */

	$certTxt = base64_decode($vpnCert['crt']);
	$parsedCert = openssl_x509_parse($certTxt);

	if (is_array($parsedCert['extensions'])) {
		$subjAltName = $parsedCert['extensions']['subjectAltName'];
		/* may be multiple names, comma separated */
		$altNames = explode(',', $subjAltName);
		foreach ($altNames as $name) {
			/* formatted as 'type:value' */
			$fields = explode(':', $name);
			if (count($fields) == 2) {
				$clientId = trim($fields[1]);
			}
		}

	/* no SANs found, use the CN of the subject */
	} elseif (is_array($parsedCert['subject'])){
		$clientId = $parsedCert['subject']['CN'];

	} else {
		$clientId = "";
	}

	return($clientId);
}

/* 
 * Generate the text of a key-value pair formatted for inclusion in an iOS
 *  config profile.
 */
function key_data_str($keyName, $keyType, $keyValue, $indentLevel) {

	$indent = str_repeat("\t", $indentLevel);

	if (empty($keyName) && empty($keyValue)) {
		return "";
	}

	$extraNewline = "";
	$extraIndent = "";

	/* dict/data/array get their open/close tags on their own lines */
	if ($keyType == "dict" || $keyType == "data" || $keyType == "array") {
		$extraNewline = "\n";
		$extraIndent  = $indent;
	}

	$keyData = "";
	/* some arrays don't have a key name, everything else probably does */
	if ($keyName) {
		$keyData .= $indent . "<key>$keyName</key>\n";
	}

	/* most types have open/close tags. booleans have a single tag */
	$keyData .= $indent;
	if ($keyType == "boolean") {
		if ($keyValue === false) {
			$keyData .= "<false/>\n";
		} else {
			$keyData .= "<true/>\n";
		}
	} else {
		$keyData .= "<${keyType}>" . $extraNewline;
		$keyData .= "${keyValue}";
		$keyData .= $extraIndent . "</${keyType}>\n";
	}

	return $keyData;
}


/* VPN specific settings */
function generate_vpn($phase1, $profId, $payloadUuid, $certUuid, $user) {

	$ike = get_vpn_type($phase1['iketype']);
	$ikeDict = generate_ike($phase1, $profId, $certUuid, $user);
	$ipv4Dict = key_data_str('OverridePrimary', 'integer', 1, VPNSCOPE + 1);

	$vpnAttrs = 
	[
		[ 'key' => "$ike", 'type' => 'dict',
		'value' => $ikeDict],
		[ 'key' => "IPv4", 'type' => 'dict',
		'value' => $ipv4Dict],
		[ 'key' => 'PayloadDescription', 'type' => 'string',
		'value' => 'Configures VPN settings, including authentication'],
		[ 'key' => 'PayloadDisplayName', 'type' => 'string',
		'value' => 'VPN (pfSense IPsec VPN)'],
		[ 'key' => 'PayloadIdentifier', 'type' => 'string',
		'value' => "${profId}.remote-access"],
		[ 'key' => 'PayloadOrganization', 'type' => 'string',
		'value' => 'pfSense'],
		[ 'key' => 'PayloadType', 'type' => 'string',
		'value' => 'com.apple.vpn.managed'],
		[ 'key' => 'PayloadUUID', 'type' => 'string',
		'value' => $payloadUuid],
		[ 'key' => 'PayloadVersion', 'type' => 'integer',
		'value' => 1],
		[ 'key' => 'UserDefinedName', 'type' => 'string',
		'value' => 'pfSense IPsec VPN'],
		[ 'key' => 'VPNType', 'type' => 'string',
		'value' => $ike]
	];

	$vpnDict = "";
	foreach ($vpnAttrs as $attr) {
		$vpnDict .= key_data_str($attr['key'], $attr['type'], $attr['value'], VPNSCOPE);
	}

	return $vpnDict;
}


/* IPsec (IKEv1) specific settings */
function generate_ikev1($phase1, $user, $authMethod, $extendedAuth) {

	if ($authMethod == 'SharedSecret') {
		/* with hybrid auth, group/ID needs to end with "[hybrid]" */
		if ($phase1['authentication_method'] == "hybrid_rsa_server") {
			$localId = $user['name'] . '[hybrid]';
		/* if an ID is not specifically set, use username */
		} elseif (!$phase1['peerid_data']) {
			$localId = $user['name'];
		} else {
			$localId = $phase1['peerid_data'];
		}

		$ikeDict .= key_data_str("LocalIdentifier", "string",
			$localId, IKESCOPE);
		$ikeDict .= key_data_str("LocalIdentifierType",
			"string", "KeyID", IKESCOPE);
	}

	$ikeDict .= key_data_str("XAuthEnabled", "integer",
		$extendedAuth, IKESCOPE);
	if ($extendedAuth) {
		$ikeDict .= key_data_str("XAuthName", "string", $user['name'],
			IKESCOPE);
	}

	return $ikeDict;
}


/* IKEv2 specific settings */
function generate_ikev2($phase1, $user, $authMethod, $extendedAuth, $realAddr) {

	global $config;

	/*
	 *https://wiki.strongswan.org/projects/strongswan/wiki/AppleIKEv2Profile
	 * set AuthenticationMethod, ExtendedAuthEnabled, PayloadCertifcateUUID
	 * based on the information from strongswan on how the client uses
	 * those.
	 */

	if ($phase1['dpd_delay'] < 300) {
		$dpdLevel = "High";
	} elseif ($phase1['dpd_delay'] < 900) {
		$dpdLevel = "Medium";
	} elseif (isset($phase1['dpd_delay'])) {
		$dpdLevel = "Low";
	} else {
		$dpdLevel = "None";
	}


	/* iOS defaults to 3DES if you don't set encryption algorithms, so
	 * try to figure out some sane values to use from the system config.
	 * valid encryption algorithms: DES, 3DES, AES-128, AES-256,
	 * AES-128-GCM, AES-256-GCM
	 * valid integrity algorithms: SHA1-96, SHA1-160, SHA2-256, SHA2-384,
	 * SHA2-512
	 */
	$ikeEncAlg = strtoupper($phase1['encryption-algorithm']['name']);
	if (isset($phase1['encryption-algorithm']['keylen'])) {
		$ikeEncAlg .= "-{$phase1['encryption-algorithm']['keylen']}";
	}

	$phase1HashMap = [ 	'sha1' => 'SHA1-96',
				'sha256' => 'SHA2-256',
				'sha384' => 'SHA2-384',
				'sha512' => 'SHA2-512' ];

	$ikeHashAlg = $phase1HashMap[$phase1['hash-algorithm']];

	if ($phase1['certref']) {
		$cert = lookup_cert($phase1['certref']);
		$ca   = lookup_ca($cert['caref']);
		$caCn = cert_get_cn($ca['crt']);
		$certCn = cert_get_cn($cert['crt']);

		$ikeDict .= key_data_str("ServerCertificateIssuerCommonName",
			"string", $caCn, IKESCOPE);
		$ikeDict .= key_data_str("ServerCertificateCommonName",
			"string", $certCn, IKESCOPE);
	}

	if ($phase1['myid_type'] == "myaddress") {
		$remoteId = $realAddr;
	} else {
		$remoteId = $phase1['myid_data'];
	}

	/* for pre-shared key, the user name gets used as the peer ID to
	 * identify the correct shared secret */
	if ($authMethod == 'SharedSecret') {
		if ($phase1['authentication_method'] == "pre_shared_key") {
			$localId = $user['name'];
		} else {
			$localId = $phase1['peerid_data'];
		}
	} else {
		$localId = $phase1['peerid_data'];
	}

	$ikeDict .= key_data_str("LocalIdentifier", "string",
		$localId, IKESCOPE);
	$ikeDict .= key_data_str("RemoteIdentifier", "string",
		$remoteId, IKESCOPE);
	$ikeDict .= key_data_str("DeadPeerDetectionInterval", "string",
		$dpdLevel, IKESCOPE);
	$ikeDict .= key_data_str("ExtendedAuthEnabled", "integer",
		$extendedAuth, IKESCOPE);

	$ikeSADict = key_data_str("EncryptionAlgorithm", "string",
		$ikeEncAlg, SASCOPE);
	if (isset($ikeHashAlg)) {
		$ikeSADict .= key_data_str("IntegrityAlgorithm",
			"string", $ikeHashAlg, SASCOPE);
	}
	$ikeSADict .= key_data_str("DiffieHellmanGroup", "integer",
		$phase1['dhgroup'], 5);
	$ikeSADict .= key_data_str("LifeTimeInMinutes", "integer",
		$phase1['lifetime']/60, 5);

	$ikeDict .= key_data_str("IKESecurityAssociationParameters",
		"dict", $ikeSADict, IKESCOPE);

	$raPhase2 = array();
	foreach ($config['ipsec']['phase2'] as $ipsecp2) {
		if ($ipsecp2['ikeid'] == $phase1['ikeid']) {
			$raPhase2 = $ipsecp2;
			break;
		}
	}

	if (is_array($raPhase2['encryption-algorithm-option'])) {
		$ipsecEncAlg = strtoupper($raPhase2['encryption-algorithm-option'][0]["name"]);
		if (isset($raPhase2['encryption-algorithm-option'][0]['keylen'])) {
			$ipsecEncAlg .= "-{$raPhase2['encryption-algorithm-option'][0]['keylen']}";
		}
	} else {
		$ipsecEncAlg = $ikeEncAlg;
	}

	$phase2HashMap = [	'hmac_sha1' => 'SHA1-96',
				'hmac_sha256' => 'SHA2-256',
				'hmac_sha384' => 'SHA2-384',
				'hmac_sha512' => 'SHA2-512'];

	if (is_array($raPhase2['hash-algorithm-option'])) {
		$ipsecHashAlg = $phase2HashMap[$raPhase2['hash-algorithm-option'][0]];
	} else {
		$ipsecHashAlg = $ikeHashAlg;
	}

	if (isset($config['ipsec']['client']['pfs_group'])) {
		$ipsecDhGroup = $config['ipsec']['client']['pfs_group'];
	} elseif (isset($raPhase2['pfsgroup'])) {
		$ipsecDhGroup = $raPhase2['pfsgroup'];
	} else {
		$ipsecDhGroup = 0;
	}

	$ipsecLifetime = $raPhase2['lifetime'] / 60;

	$childSADict = key_data_str("EncryptionAlgorithm", "string",
		$ipsecEncAlg, SASCOPE);
	$childSADict .= key_data_str("IntegrityAlgorithm", "string",
		$ipsecHashAlg, SASCOPE);
	if ($ipsecDhGroup > 0) {
		$childSADict .= key_data_str("DiffieHellmanGroup", "integer",
			$ipsecDhGroup, SASCOPE);
	}
	$childSADict .= key_data_str("LifeTimeInMinutes", "integer",
		$ipsecLifetime, SASCOPE);

	$ikeDict .= key_data_str("ChildSecurityAssociationParameters",
		"dict", $childSADict, IKESCOPE);

	return $ikeDict;
}


function get_mobilekey($ident, $keytype) {

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


		

/* version-independent IKE configuration. calls the version-specific one */
function generate_ike($phase1, $profId, $certUuid, $user) {

	list($realAddr, $pubAddr) = get_addresses($phase1['interface']);
	if (!isset($pubAddr)) {
		$pubAddr = $realAddr;
	}

	$ike = get_vpn_type($phase1['iketype']);

	if ($ike == "IPSec") {
		$certAuthMethods = ["xauth_rsa_server",
			"rsasig"];
		$extendedAuthMethods = ["hybrid_rsa_server", "xauth_rsa_server",
			"xauth_psk_server"];
		$certUuidMethods = [ "rsasig", "xauth_rsa_server"];

	} else {
		$certAuthMethods = ["eap-tls", "eap-mschapv2", "eap-radius",
			"hybrid_rsa_server", "xauth_rsa_server", "rsasig"];
		$extendedAuthMethods = ["eap-tls", "eap-mschapv2",
			"eap-radius"];
		$certUuidMethods = [ "rsasig", "eap-tls", "xauth_rsa_server" ];

	}

	/* try to determine certificate or shared secret */
	if (in_array($phase1['authentication_method'], $certAuthMethods)) {
		$authMethod = "Certificate";
	} else {
		$authMethod = "SharedSecret";
	}

	$extendedAuth = 0;
	if (in_array($phase1['authentication_method'], $extendedAuthMethods)) {
		$extendedAuth = 1;
	}

	$setCertUuid = 0;
	if (in_array($phase1['authentication_method'], $certUuidMethods)) {
		$setCertUuid = 1;
	}
	
	/* if no peerid data is explicitly set, the client will send it's
	 * IP address as the identifier. When using certificate authentication
	 * strongswan will fail the authentication if the client ID is not
	 * equal to either the CN of the certificate (which must resolve in
	 * DNS) or a value populated in an altSubjectName of the certificate
	 */
	if ($setCertUuid) {
		$phase1['peerid_data'] = get_cert_client_id(
			$phase1['caref'], $user);
	}

	/* generate the pieces that are specific to the IKE version */
	if ($ike == "IPSec") {
		$ikeDict = generate_ikev1($phase1, $user, $authMethod, $extendedAuth);
	} else {
		$ikeDict = generate_ikev2($phase1, $user, $authMethod, $extendedAuth, $realAddr);
	}

		
	$ikeDict .= key_data_str("AuthenticationMethod", "string", $authMethod,
		IKESCOPE);
	$ikeDict .= key_data_str("RemoteAddress", "string", $pubAddr, IKESCOPE);

	if ($setCertUuid) {
		$ikeDict .= key_data_str("PayloadCertificateUUID", "string",
			$certUuid, IKESCOPE);
	}

	if ($authMethod == 'SharedSecret') {
		$defaultPsk = get_mobilekey('any', 'PSK');
		if (!empty($user['ipsecpsk'])) {
			$encPsk = base64_encode($user['ipsecpsk']);
		} elseif (!empty($defaultPsk)) {
			$encPsk = base64_encode($defaultPsk);
		} elseif (!empty($phase1['pre-shared-key'])) {
			$encPsk = base64_encode($phase1['pre-shared-key']);
		}

		if (is_null($encPsk)) {
			$encPsk = "No PSK found for {$user['name']}";
		}
		$pskIndent = str_repeat("\t", IKESCOPE);

		$ikeDict .= key_data_str("SharedSecret", "data", $pskIndent . $encPsk . "\n", IKESCOPE);
	}

	$ikeDict .= key_data_str("OnDemandEnabled", "integer", 0, IKESCOPE);

	return $ikeDict;
}

/*
 * certref is the ID Of the server cert. look up that cert, find if we manage
 * the CA cert locally, if so, generate a profile configuration section
 * that contains the CA cert.
 *
 */
function generate_ca_config($certref, $profId, $uuid) {

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
	$certData = implode("\n${caIndent}", str_split($cacert['crt'], $certLineChars));
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
		'value' => "${profId}.certs1.credential1" ],
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
		$caDict .= key_data_str($attr['key'], $attr['type'], $attr['value'], CASCOPE);
	}

	return $caDict;
}

function generate_p12($vpnCert, $passwd) {

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

/*
 * Find a certificate for the user signed by the appropriate CA, generate
 * a config profile section containing the certificate. 
 */
function generate_cert_data($caref, $profId, $uuid, $user) {

	global $config, $certLineChars;

	/* search current user certs for one signed by right CA */
	$certids = $user['cert'];
	if (is_array($certids)) {
		foreach ($certids as $certid) {
			$cert = lookup_cert($certid);
			if ($cert['caref'] === $caref) {
				$vpnCert = $cert;
				break;
			}
		}
	}
			

	$certIndent = str_repeat("\t", CERTSCOPE);

	$p12passwd = "iOSPasswd123";
	if (isset($vpnCert)) {
		$p12file = urlencode("{$vpnCert['descr']}.p12");
		$p12txt = generate_p12($vpnCert, $p12passwd);
		/* keep the encoded cert from being one gigantic line */
		$p12txt = implode("\n${certIndent}", str_split($p12txt, $certLineChars));
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
		'value' => "${profId}.certs1.credential" ],
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
		$certDict .= key_data_str($attr['key'], $attr['type'], $attr['value'], CERTSCOPE);
	}

	return $certDict;

}

function get_profile_id() {

	/* profile identifier is in reverse domain format */
	$hostname = gethostname();
	$pieces = explode('.', $hostname);
	$profId = '';
	while (count($pieces) > 0) {
		$lastElem = array_pop($pieces);
		$profId .= "${lastElem}.";
	}
	$profId .= "vpn";

	return $profId;
}

function generate_payload($phase1) {

	$uuids = get_uuids();
	$profId = get_profile_id();
	$vpnType = get_vpn_type($phase1['iketype']);

	$user = getUserEntry($_SESSION['Username']);
	$vpnDict = generate_vpn($phase1, $profId, $uuids[IPSECIDX],
		$uuids[PKCS12IDX], $user);
	$caDict  = generate_ca_config($phase1['certref'], $profId,
		$uuids[CAIDX]);
	$certDict = generate_cert_data($phase1['caref'], $profId,
		$uuids[PKCS12IDX], $user);
	
	$contentList = key_data_str(null, "dict", $vpnDict, PAYLOADSCOPE);
	$contentList .= key_data_str(null, "dict", $caDict, PAYLOADSCOPE);
	$contentList .= key_data_str(null, "dict", $certDict, PAYLOADSCOPE);

	$payloadAttrs = 
	[
		[ 'key' => 'PayloadContent', 'type' => 'array',
		'value' => $contentList ],
		[ 'key' => 'PayloadDescription', 'type' => 'string',
		'value' => "Contains configuration settings for connecting to VPN on pfSense firewall and VPN appliance" ],
		[ 'key' => 'PayloadDisplayName', 'type' => 'string',
		'value' => "pfSense remote access VPN configuration" ],
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
		$payloadTxt .= key_data_str($attr['key'], $attr['type'],
			$attr['value'], PROFILESCOPE);
	}

	return $payloadTxt;
}

function generate_profile($phase1) {

	$payloadTxt = generate_payload($phase1);

	$profileTxt =
'<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/
PropertyList-1.0.dtd">
<plist version="1.0">
';
	$profileTxt .= key_data_str(null, "dict", $payloadTxt, BODYSCOPE);
	$profileTxt .=  "</plist>\n";

	return $profileTxt;
}



if (is_array($config['ipsec']) && is_array($config['ipsec']['phase1'])) {
	foreach ($config['ipsec']['phase1'] as $ph1) {
		if (isset($ph1['mobile'])) {
			$phase1 = $ph1;
			break;
		}
	}
}

/* only draw a page to write an error if no mobile IPsec VPN is configured */
if (!isset($phase1) || isset($phase1['disabled'])) {

	$pgtitle = array(gettext("VPN"),gettext("iOS IPsec Profile"));
	$shortcut_section = "ipsec";

	include("head.inc");
	echo "<body link=\"#0000CC\" vlink=\"#0000CC\" alink=\"#0000CC\">\n";

	echo gettext("No mobile IPsec VPN is configured.") .
		" <a href=\"vpn_ipsec_mobile.php\">" .
		gettext("Configure mobile IPsec") . "</a>\n" ;

	echo "</body></html>";

	exit;
}


/* generate profile text and send as a file download */
$cfgTxt = generate_profile($phase1);


	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename='.$ipsecProfile);
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . strlen($cfgTxt));
	ob_clean();
	flush();
	echo $cfgTxt;

exit;

?>
