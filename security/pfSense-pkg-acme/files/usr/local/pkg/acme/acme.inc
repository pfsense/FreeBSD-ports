<?php
/*
 * acme.inc
 *
 * part of pfSense (https://www.pfsense.org/)
 * Copyright (c) 2016 PiBa-NL
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

namespace { // global code
	/* Write an ACME certificates to the filesystem. */
	function acme_write_certificate($certref) {
		$prefix = '/conf/acme';
		safe_mkdir($prefix);

		/* Lookup the certificate and CA */
		$cert = lookup_cert($certref);
		$cert = $cert['item'];
		if (empty($cert)) {
			return;
		}
		$ca = lookup_ca($cert['caref']);
		$ca = $ca['item'];
		if (empty($ca)) {
			return;
		}

		/* Decode the certificate, key, and CA certificate */
		$crt = base64_decode($cert['crt']);
		$key = base64_decode($cert['prv']);
		$ca = base64_decode($ca['crt']) . "\n";

		/* Write out the certificate data in various useful formats */
		file_put_contents("{$prefix}/{$cert['descr']}.crt", $crt);
		file_put_contents("{$prefix}/{$cert['descr']}.key", $key);
		file_put_contents("{$prefix}/{$cert['descr']}.ca", $ca);
		file_put_contents("{$prefix}/{$cert['descr']}.fullchain", $crt . $ca);
		file_put_contents("{$prefix}/{$cert['descr']}.all.pem", $crt . $key . $ca);
		return;
	}
	/* Write all ACME certificates to the filesystem */
	function acme_write_all_certificates() {
		if (!config_path_enabled('installedpackages/acme', 'writecerts')) {
			return;
		}

		/* Find all certificates  */
		$acmecerts = acme_plugin_certificates(array('type' => 'certificates', 'event' => 'used_certificates'));
		if (!is_array($acmecerts['certificatelist']) || empty($acmecerts['certificatelist'])) {
			return;
		}
		foreach ($acmecerts['certificatelist'] as $certref => $ac) {
			acme_write_certificate($certref);
		}
	}
	function acme_plugin_certificates($pluginparams) {
		$result = array();
		if ($pluginparams['type'] == 'certificates' && $pluginparams['event'] == 'used_certificates') {
			/* If there are no certificates, there is nothing to process. */
			if (!is_array(config_get_path('installedpackages/acme/certificates/item'))) {
				return array();
			}
			$result['pkgname'] = "Acme";
			$result['certificatelist'] = array();
			foreach (config_get_path('installedpackages/acme/certificates/item', []) as $certificate) {
				$certificatename = $certificate["name"];
				$cert = lookup_cert_by_name($certificatename);
				$cert = $cert['item'];
				$item = array();
				$cert = $cert['refid'];
				$item['usedby'] = "Acme - {$certificatename}";
				$result['certificatelist'][$cert][] = $item;
			}
		}
		return $result;
	}

	/* Attempt to restart a remote service via XMLRPC with the default XMLRPC settings. */
	function acme_xmlrpc_restart_service($servicename = "webgui", $extras = array()) {
		require_once("xmlrpc_client.inc");
		$rpc_client = new pfsense_xmlrpc_client();
		$execstr = "";
		if ($servicename == "webgui") {
			/* The GUI needs a special event to restart, it is not a traditional service. */
			$execstr = "send_event('service restart webgui');";
		} else {
			$extras = var_export($extras, true);
			$execstr = <<<EOD
require_once('service-utils.inc');
service_control_restart("{$servicename}", {$extras});
EOD;
		}
		$resp = $rpc_client->xmlrpc_exec_php($execstr);
		if ($resp != null) {
			return true;
		}
		return false;
	}

	function acme_fixup_service_args($servicestring) {
		$args = explode(' ', $servicestring);
		$extras = array();
		switch ($args[0]) {
			case "openvpn":
				if (in_array($args[1], array("server", "client"))) {
					$extras['vpnmode'] = $args[1];
				} else {
					return;
				}
				if (is_numeric($args[2])) {
					$extras['id'] = $args[2];
				} else {
					return;
				}
				$vpnsvc = find_service_by_openvpn_vpnid($extras['id']);
				if (empty($vpnsvc)) {
					return;
				}
				break;
			case "captiveportal":
				if (is_validaliasname($args[1])) {
					$extras['zone'] = $args[1];
				} else {
					return;
				}
				$cpzone = find_service_by_cp_zone($extras['zone']);
				if (empty($cpzone)) {
					return;
				}
				break;
		}
		return array($args[0], $extras);
	}
}

namespace pfsense_pkg\acme {

/* include all configuration functions */
require_once("functions.inc");
require_once("pkg-utils.inc");
require_once("pfsense-utils.inc");
require_once("notices.inc");
require_once("util.inc");
require_once("acme_utils.inc");
require_once("acme_sh.inc");
require_once("acme/acme_serverconnectors.inc");

define('LOG_PREFIX_PKG_ACME', 'ACME');

#region global array item definitions..
// <editor-fold desc="global array item definitions">

$a_enabledisable = array();
$a_enabledisable['enable'] = array('name' => 'Enabled');
$a_enabledisable['disable'] = array('name' => 'Disabled');

global $a_acmeserver;
$a_acmeserver = array();
$a_acmeserver['letsencrypt-staging-2'] = [
	'name' => "Let's Encrypt Staging ACME v2 (For TESTING purposes)",
	'url' => 'https://acme-staging-v02.api.letsencrypt.org/directory'
];
$a_acmeserver['letsencrypt-production-2'] = [
	'name' => "Let's Encrypt Production ACME v2 (Applies rate limits to certificate requests)",
	'url' => 'https://acme-v02.api.letsencrypt.org/directory'
];
$a_acmeserver['buypass-staging'] = [
	'name' => "BuyPass Staging (For TESTING purposes)",
	'url' => 'https://api.test4.buypass.no/acme/directory'
];
$a_acmeserver['buypass-production'] = [
	'name' => "BuyPass Production ACME v1 (Applies rate limits to certificate requests)",
	'url' => 'https://api.buypass.com/acme/directory'
];
$a_acmeserver['zerossl-production'] = [
	'name' => "ZeroSSL Production (Applies rate limits to certificate requests)",
	'url' => 'https://acme.zerossl.com/v2/DV90'
];
$a_acmeserver['sslcom-rsa-production'] = [
	'name' => "SSL.com RSA Production (Applies rate limits to certificate requests)",
	'url' => 'https://acme.ssl.com/sslcom-dv-rsa'
];
$a_acmeserver['sslcom-ecc-production'] = [
	'name' => "SSL.com ECC Production (Applies rate limits to certificate requests)",
	'url' => 'https://acme.ssl.com/sslcom-dv-ecc'
];
$a_acmeserver['google-staging'] = [
	'name' => "Google ACMEv2 Staging (For TESTING purposes)",
	'url' => 'https://dv.acme-v02.test-api.pki.goog/directory'
];
$a_acmeserver['google-production'] = [
	'name' => "Google Production ACME v2 (Applies rate limits to certificate requests)",
	'url' => 'https://dv.acme-v02.api.pki.goog/directory'
];
/*$a_acmeserver['dummy'] = [
	'name' => "dummy",
	'url' => 'https://example.org'
];*/

foreach (config_get_path('installedpackages/acme/customacme/servers', []) as $cas) {
	if (empty($cas['intid']) ||
	    empty($cas['url'])) {
		continue;
	}
	$a_acmeserver[$cas['intid']] = [
		'name' => $cas['name'],
		'url' => $cas['url']
	];
}

global $acme_domain_validation_method;
$acme_domain_validation_method = [];
$acme_domain_validation_method['webroot'] = [
	'name' => 'Webroot local folder',
	'fields' => [
		'folder' => [
			'name' => 'folder',
			'description' => 'Folder into which the acme challenge response is written; for example: /usr/local/www/.well-known/acme-challenge/',
			'columnheader' => 'Root Folder',
			'type' => "textbox",
			'size' => '50',
		],
	]
];
$acme_domain_validation_method['webrootftp'] = [
	'name' => 'Webroot FTP',
	'fields' => [
		'ftpserver' => [
			'name' => 'ftpserver',
			'description' => 'Hostname of FTP server to which ACME will connect (e.g. ftps://www.webserver.tld ). '
					. 'Currently supports FTPS (passive) and SFTP.',
			'columnheader' => 'Server',
			'type' => 'textbox',
			'size' => '50',
		],
		'username' => [
			'name' => 'username',
			'description' => 'Username for the remote server',
			'columnheader' => 'Username',
			'type' => 'textbox',
			'size' => '50',
		],
		'password' => [
			'name' => 'password',
			'description' => 'Password to authenticate this user on the remote server',
			'columnheader' => 'Password',
			'type' => 'password',
			'size' => '50',
		],
		'folder' => [
			'name' => 'folder',
			'description' => 'Folder into which the acme challenge response is written (e.g. /.well-known/acme-challenge/)',
			'columnheader' => 'Folder',
			'type' => 'textbox',
			'size' => '50',
		],
	]
];
$acme_domain_validation_method['standalone'] = [
	'name' => 'Standalone HTTP server',
	'fields' => [
		'port' => [
			'name' => 'port',
			'description' => 'HTTP listen port for stand-alone server. ' .
					'Must be 80 or have port 80 on WAN forwarded to this port. ' .
					'Firewall rules must allow traffic to reach this port.',
			'columnheader' => 'Port',
			'type' => 'textbox',
			'size' => '50',
		],
		'ipv6' => [
			'name' => 'ipv6',
			'description' => 'Bind to IPv6 instead of IPv4.',
			'columnheader' => 'IPv6',
			'type' => 'checkbox',
		]
	]
];
$acme_domain_validation_method['standalonetls'] = [
	'name' => 'Standalone TLS-ALPN server',
	'fields' => [
		'port' => [
			'name' => 'port',
			'description' => 'TLS listen port for stand-alone server. ' .
					'Must be 443 or have port 443 on WAN forwarded to this port. ' .
					'Firewall rules must allow traffic to reach this port.',
			'columnheader' => 'Port',
			'type' => 'textbox',
			'size' => '50',
		],
	]
];
$acme_domain_validation_method['dns_manual'] = [
	'name' => 'DNS-Manual',
	'fields' => []
];
$acme_domain_validation_method['dns_nsupdate'] = [
	'name' => 'DNS-NSupdate / RFC 2136',
	'fields' => [
		'NSUPDATE_SERVER' => [
			'name' => 'nsupdate_server',
			'description' => 'The DNS server to which updates are sent (IP address or hostname)',
			'columnheader' => 'Server',
			'type' => 'textbox',
		],
		'NSUPDATE_KEYNAME' => [
			'name' => 'nsupdate_keyname',
			'description' => '(Optional) A name for the key, if it is different than _acme-challenge.[DomainName]',
			'columnheader' => 'Key Name',
			'type' => 'textbox',
		],
		'NSUPDATE_KEYALGO' => [
			'name' => 'nsupdate_keyalgo',
			'description' => 'Algorithm used to generate the authentication Key for this zone',
			'columnheader' => 'Key Algorithm',
			'type' => 'select',
			'items' => [
				''    => ['name' => 'None'],
				'157' => ['name' => 'HMAC-MD5'],
				'165' => ['name' => 'HMAC-SHA512'],
				'164' => ['name' => 'HMAC-SHA384'],
				'163' => ['name' => 'HMAC-SHA256'],
				'162' => ['name' => 'HMAC-SHA224'],
				'161' => ['name' => 'HMAC-SHA1'],
			],
		],
		'NSUPDATE_KEY' => [
			'name' => 'nsupdate_key',
			'description' => 'The key which authenticates updates for this zone',
			'columnheader' => 'Key',
			'type' => 'textarea',
		],
		'NSUPDATE_ZONE' => [
			'name' => 'nsupdate_zone',
			'description' => '(Optional) Explicitly set the zone name for updates.',
			'columnheader' => 'Zone',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_1984hosting'] = [
	'name' => 'DNS-1984Hosting',
	'fields' => [
		'One984HOSTING_Username' => [
			'name' => 'one984hosting_username',
			'description' => '1984Hosting Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'One984HOSTING_Password' => [
			'name' => 'one984hosting_password',
			'description' => '1984Hosting Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_acmeproxy'] = [
	'name' => 'DNS-Acmeproxy',
	'fields' => [
		'ACMEPROXY_ENDPOINT' => [
			'name' => 'acmeproxy_endpoint',
			'description' => 'Acmeproxy Endpoint URL (https://ip:port)',
			'columnheader' => 'Endpoint URL',
			'type' => 'textbox',
		],
		'ACMEPROXY_USERNAME' => [
			'name' => 'acmeproxy_username',
			'description' => 'Acmeproxy Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'ACMEPROXY_PASSWORD' => [
			'name' => 'acmeproxy_password',
			'description' => 'Acmeproxy Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_acmedns'] = [
	'name' => 'DNS-acme-dns.io',
	'fields' => [
		'ACMEDNS_USERNAME' => [
			'name' => 'acmedns_username',
			'description' => 'acme-dns.io Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'ACMEDNS_PASSWORD' => [
			'name' => 'acmedns_password',
			'description' => 'acme-dns.io Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
		'ACMEDNS_SUBDOMAIN' => [
			'name' => 'acmedns_subdomain',
			'description' => 'acme-dns.io subdomain',
			'columnheader' => 'Subdomain',
			'type' => 'textbox',
		],
		'ACMEDNS_BASE_URL' => [
			'name' => 'acmedns_update_url',
			'description' => '(optional) Custom ACME DNS Base URL',
			'columnheader' => 'Base URL',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_active24'] = [
	'name' => 'DNS-Active24',
	'fields' => [
		'Active24_ApiKey' => [
			'name' => 'active24_apikey',
			'description' => 'Active24 API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
		'Active24_ApiSecret' => [
			'name' => 'active24_apisecret',
			'description' => 'API Secret',
			'columnheader' => 'API Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_beget'] = [
	'name' => 'DNS-Beget.com',
	'fields' => [
		'BEGET_User' => [
			'name' => 'beget_user',
			'description' => 'Beget Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'BEGET_Password' => [
			'name' => 'beget_password',
			'description' => 'Beget Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_edgedns'] = [
	'name' => 'DNS-Akamai Edge DNS v2',
	'fields' => [
		'AKAMAI_HOST' => [
			'name' => 'akamai_host',
			'description' => 'Hostname',
			'columnheader' => 'Host',
			'type' => 'textbox',
		],
		'AKAMAI_ACCESS_TOKEN' => [
			'name' => 'akamai_access_token',
			'description' => 'Access Token',
			'columnheader' => 'Access Token',
			'type' => 'password',
		],
		'AKAMAI_CLIENT_TOKEN' => [
			'name' => 'akamai_client_token',
			'description' => 'Client Token',
			'columnheader' => 'Client Token',
			'type' => 'password',
		],
		'AKAMAI_CLIENT_SECRET' => [
			'name' => 'akamai_client_secret',
			'description' => 'Client Secret',
			'columnheader' => 'Client Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_ali'] = [
	'name' => 'DNS-Aliyuncs',
	'fields' => [
		'Ali_Key' => [
			'name' => 'ali_key',
			'description' => 'API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
		'Ali_Secret' => [
			'name' => 'ali_secret',
			'description' => 'API Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_kas'] = [
	'name' => 'DNS-All-inkl Kasserver',
	'fields' => [
		'KAS_Login' => [
			'name' => 'kas_login',
			'description' => 'Login',
			'columnheader' => 'Login',
			'type' => 'textbox',
		],
		'KAS_Authtype' => [
			'name' => 'kas_authtype',
			'description' => 'Auth type (default: sha1)',
			'columnheader' => 'Auth Type',
			'type' => 'textbox',
		],
		'KAS_Authdata' => [
			'name' => 'kas_authdata',
			'description' => 'Auth data',
			'columnheader' => 'Auth Data',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_ad'] = [
	'name' => 'DNS-Alwaysdata',
	'fields' => [
		'AD_API_KEY' => [
			'name' => 'ad_api_key',
			'description' => 'Alwaysdata API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_alviy'] = [
	'name' => 'DNS-Alviy.com',
	'fields' => [
		'Alviy_token' => [
			'name' => 'alviy_token',
			'description' => 'API token (https://cloud.alviy.com/token)',
			'columnheader' => 'API token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_anx'] = [
	'name' => 'DNS-Anexia CloudDNS',
	'fields' => [
		'ANX_Token' => [
			'name' => 'anx_token',
			'description' => 'API Token',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_artfiles'] = [
	'name' => 'DNS-ArtFiles.de',
	'fields' => [
		'AF_API_USERNAME' => [
			'name' => 'af_api_username',
			'description' => 'ArtFiles Username',
			'columnheader' => 'User',
			'type' => 'textbox',
		],
		'AF_API_PASSWORD' => [
			'name' => 'af_api_password',
			'description' => 'ArtFiles Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_arvan'] = [
	'name' => 'DNS-Arvan',
	'fields' => [
		'Arvan_Token' => [
			'name' => 'arvan_token',
			'description' => 'Arvan API Token',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_aurora'] = [
	'name' => 'DNS-auroradns.eu',
	'fields' => [
		'AURORA_Key' => [
			'name' => 'aurora_key',
			'description' => 'API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
		'AURORA_Secret' => [
			'name' => 'aurora_secret',
			'description' => 'API Secret. Obtain the key and secret from https://cp.pcextreme.nl/auroradns/users.',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_autodns'] = [
	'name' => 'DNS-autoDNS from InternetX',
	'fields' => [
		'AUTODNS_USER' => [
			'name' => 'autodns_user',
			'description' => 'autoDNS Username',
			'columnheader' => 'User',
			'type' => 'textbox',
		],
		'AUTODNS_PASSWORD' => [
			'name' => 'autodns_password',
			'description' => 'autoDNS Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
		'AUTODNS_CONTEXT' => [
			'name' => 'autodns_context',
			'description' => 'autoDNS Context',
			'columnheader' => 'Context',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_aws'] = [
	'name' => 'DNS-Amazon Route53',
	'fields' => [
		'AWS_ACCESS_KEY_ID' => [
			'name' => 'aws_access_key_id',
			'description' => 'AWS Access Key / API ID',
			'columnheader' => 'API ID',
			'type' => 'textbox',
		],
		'AWS_SECRET_ACCESS_KEY' => [
			'name' => 'aws_secret_access_key',
			'description' => 'AWS Secret Access / API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
		'AWS_DNS_SLOWRATE' => [
			'name' => 'aws_dns_slowrate',
			'description' => 'Sleep interval after TXT record update, in seconds (default: 1)',
			'columnheader' => 'Slow Rate',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_azion'] = [
	'name' => 'DNS-azionapi.net',
	'fields' => [
		'AZION_Email' => [
			'name' => 'azion_email',
			'description' => 'Account e-mail address',
			'columnheader' => 'E-mail',
			'type' => 'textbox',
		],
		'AZION_Password' => [
			'name' => 'azion_password',
			'description' => 'Account password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_azure'] = [
	'name' => 'DNS-Azure (Microsoft)',
	'fields' => [
		'AZUREDNS_SUBSCRIPTIONID' => [
			'name' => 'azuredns_subscriptionid',
			'description' => 'Azure Subscription ID. First, <a href="https://github.com/acmesh-official/acme.sh/wiki/How-to-use-Azure-DNS">setup a service principal for access to the DNS Zone</a>.',
			'columnheader' => 'Subscription ID',
			'type' => 'textbox',
		],
		'AZUREDNS_TENANTID' => [
			'name' => 'azuredns_tenantid',
			'description' => 'Azure Tenant ID',
			'columnheader' => 'Tenant ID',
			'type' => 'textbox',
		],
		'AZUREDNS_APPID' => [
			'name' => 'azuredns_appid',
			'description' => 'Azure App ID',
			'columnheader' => 'App ID',
			'type' => 'textbox',
		],
		'AZUREDNS_CLIENTSECRET' => [
			'name' => 'azuredns_clientsecret',
			'description' => 'Azure Client Secret',
			'columnheader' => 'Client Secret',
			'type' => 'password',
		],
		'AZUREDNS_MANAGEDIDENTITY' => [
			'name' => 'azuredns_managedidentity',
			'description' => 'Use Managed Identity assigned to a resource instead of a service principal ("true" or "false")',
			'columnheader' => 'Managed Identity',
			'type' => 'textbox',
		],
		'AZUREDNS_BEARERTOKEN' => [
			'name' => 'azuredns_bearertoken',
			'description' => 'Optional token used instead of service principal credentials or managed identity',
			'columnheader' => 'Bearer Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_bookmyname'] = [
	'name' => 'DNS-BookMyName',
	'fields' => [
		'BOOKMYNAME_USERNAME' => [
			'name' => 'bookmyname_username',
			'description' => 'BookMyName Username',
			'columnheader' => 'User',
			'type' => 'textbox',
		],
		'BOOKMYNAME_PASSWORD' => [
			'name' => 'bookmyname_password',
			'description' => 'BookMyName Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_bunny'] = [
	'name' => 'DNS-Bunny DNS',
	'fields' => [
		'BUNNY_API_KEY' => [
			'name' => 'bunny_api_key',
			'description' => 'Bunny DNS API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_clouddns'] = [
	'name' => 'DNS-CloudDNS',
	'fields' => [
		'CLOUDDNS_EMAIL' => [
			'name' => 'clouddns_email',
			'description' => 'CloudDNS e-mail address',
			'columnheader' => 'E-mail',
			'type' => 'textbox',
		],
		'CLOUDDNS_CLIENT_ID' => [
			'name' => 'clouddns_client_id',
			'description' => 'CloudDNS client ID',
			'columnheader' => 'Client ID',
			'type' => 'textbox',
		],
		'CLOUDDNS_PASSWORD' => [
			'name' => 'clouddns_password',
			'description' => 'CloudDNS Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_cloudns'] = [
	'name' => 'DNS-ClouDNS',
	'fields' => [
		'CLOUDNS_AUTH_ID' => [
			'name' => 'cloudns_auth_id',
			'description' => 'Authentication ID',
			'columnheader' => 'Auth ID',
			'type' => 'textbox',
		],
		'CLOUDNS_SUB_AUTH_ID' => [
			'name' => 'cloudns_sub_auth_id',
			'description' => 'Sub authentication ID',
			'columnheader' => 'Sub Auth ID',
			'type' => 'textbox',
		],
		'CLOUDNS_AUTH_PASSWORD' => [
			'name' => 'cloudns_auth_password',
			'description' => 'ClouDNS Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_cf'] = [
	'name' => 'DNS-Cloudflare',
	'fields' => [
		'CF_Token' => [
			'name' => 'cf_token',
			'description' => 'Token Auth: Cloudflare API Token (https://dash.cloudflare.com/profile/api-tokens)',
			'columnheader' => 'Token',
			'type' => 'password',
		],
		'CF_Zone_ID' => [
			'name' => 'cf_zone_id',
			'description' => 'Token Auth: Cloudflare API Zone ID for single zone certificates',
			'columnheader' => 'Token Zone ID',
			'type' => 'textbox',
		],
		'CF_Account_ID' => [
			'name' => 'cf_account_id',
			'description' => 'Token Auth: Cloudflare API Account ID for multi-zone certificates',
			'columnheader' => 'Token Account ID',
			'type' => 'textbox',
		],
		'CF_Key' => [
			'name' => 'cf_key',
			'description' => 'Global Auth: Cloudflare Global API Key (https://dash.cloudflare.com/profile/api-tokens)',
			'columnheader' => 'Global Key',
			'type' => 'password',
		],
		'CF_Email' => [
			'name' => 'cf_email',
			'description' => 'Global Auth: Cloudflare API Email Address',
			'columnheader' => 'Global Email',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_conoha'] = [
	'name' => 'DNS-Conoha',
	'fields' => [
		'CONOHA_Username' => [
			'name' => 'conoha_username',
			'description' => 'Conoha Username',
			'columnheader' => 'User',
			'type' => 'textbox',
		],
		'CONOHA_Password' => [
			'name' => 'conoha_password',
			'description' => 'Conoha Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
		'CONOHA_TenantId' => [
			'name' => 'conoha_tenantid',
			'description' => 'Conoha Tenant ID',
			'columnheader' => 'Tenant ID',
			'type' => 'textbox',
		],
		'CONOHA_IdentityServiceApi' => [
			'name' => 'conoha_identityserviceapi',
			'description' => 'Conoha Identity Service API',
			'columnheader' => 'ID Srv API',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_constellix'] = [
	'name' => 'DNS-Constellix',
	'fields' => [
		'CONSTELLIX_Key' => [
			'name' => 'constellix_key',
			'description' => 'Constellix Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
		'CONSTELLIX_Secret' => [
			'name' => 'constellix_secret',
			'description' => 'Constellix Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_cpanel'] = [
	'name' => 'DNS-cPanel',
	'fields' => [
		'cPanel_Username' => [
			'name' => 'cpanel_username',
			'description' => 'cPanel username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'cPanel_Apitoken' => [
			'name' => 'cpanel_apitoken',
			'description' => 'cPanel API token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
		'cPanel_Hostname' => [
			'name' => 'cpanel_hostname',
			'description' => 'URL to cPanel instance',
			'columnheader' => 'URL',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_cn'] = [
	'name' => 'DNS-Core-Networks',
	'fields' => [
		'CN_User' => [
			'name' => 'cn_user',
			'description' => 'Core Networks Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'CN_Password' => [
			'name' => 'cn_password',
			'description' => 'Core Networks Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_curanet'] = [
	'name' => 'DNS-curanet.dk, scannet.dk, wannafind.dk, and dandomain.dk',
	'fields' => [
		'CURANET_AUTHCLIENTID' => [
			'name' => 'curanet_authclientid',
			'description' => 'Authentication Client ID',
			'columnheader' => 'Client ID',
			'type' => 'textbox',
		],
		'CURANET_AUTHSECRET' => [
			'name' => 'curanet_authsecret',
			'description' => 'Authentication Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_cyon'] = [
	'name' => 'DNS-cyon.ch',
	'fields' => [
		'CY_Username' => [
			'name' => 'cy_username',
			'description' => 'CY username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'CY_Password' => [
			'name' => 'cy_password',
			'description' => 'CY Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_ddnss'] = [
	'name' => 'DNS-ddnss.de',
	'fields' => [
		'DDNSS_Token' => [
			'name' => 'ddnss_token',
			'description' => 'API Token (e.g. aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee)',
			'columnheader' => 'Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_desec'] = [
	'name' => 'DNS-deSEC.io',
	'fields' => [
		'DEDYN_TOKEN' => [
			'name' => 'dedyn_token',
			'description' => 'deSEC.io API Token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
		'DEDYN_NAME' => [
			'name' => 'dedyn_name',
			'description' => 'deSEC.io Username',
			'columnheader' => 'Name',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_dgon'] = [
	'name' => 'DNS-DigitalOcean',
	'fields' => [
		'DO_API_KEY' => [
			'name' => 'do_api_key',
			'description' => 'DigitalOcean API Key',
			'columnheader' => 'DO API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_da'] = [
	'name' => 'DNS-DirectAdmin',
	'fields' => [
		'DA_Api' => [
			'name' => 'da_api',
			'description' => 'DirectAdmin API URI (e.g. https://remoteUser:remotePassword@da.example.com:8443)',
			'columnheader' => 'DA API URI',
			'type' => 'textbox',
		],
		'DA_Api_Insecure' => [
			'name' => 'da_api_insecure',
			'description' => 'DirectAdmin API Security check, 0=check for valid SSL certificate, 1=always accept',
			'columnheader' => 'DA API Secure',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_dnsexit'] = [
	'name' => 'DNS-DNSExit.com',
	'fields' => [
		'DNSEXIT_API_KEY' => [
			'name' => 'dnsexit_api_key',
			'description' => 'DNSExit API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
		'DNSEXIT_AUTH_USER' => [
			'name' => 'dnsexit_auth_user',
			'description' => 'DNSExit Username',
			'columnheader' => 'User',
			'type' => 'textbox',
		],
		'DNSEXIT_AUTH_PASS' => [
			'name' => 'dnsexit_auth_pass',
			'description' => 'DNSExit Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_dnshome'] = [
	'name' => 'DNS-dnsHome.de',
	'fields' => [
		'DNSHOME_Subdomain' => [
			'name' => 'dnshome_subdomain',
			'description' => 'Subdomain',
			'columnheader' => 'Subdomain',
			'type' => 'textbox',
		],
		'DNSHOME_SubdomainPassword' => [
			'name' => 'dnshome_subdomainpassword',
			'description' => 'Subdomain Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_dnsimple'] = [
	'name' => 'DNS-DNSimple',
	'fields' => [
		'DNSimple_OAUTH_TOKEN' => [
			'name' => 'dnsimple_oauth_token',
			'description' => 'DNSimple oauth token, visit <a href="https://dnsimple.com/user">https://dnsimple.com/user</a> to generate.',
			'columnheader' => 'OAUTH Token',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_me'] = [
	'name' => 'DNS-DNSMadeEasy',
	'fields' => [
		'ME_Key' => [
			'name' => 'me_key',
			'description' => 'DNSMadeEasy API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
		'ME_Secret' => [
			'name' => 'me_secret',
			'description' => 'DNSMadeEasy API Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_dnsservices'] = [
	'name' => 'DNS-dns.services',
	'fields' => [
		'DnsServices_Username' => [
			'name' => 'dnsservices_username',
			'description' => 'dns.services Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'DnsServices_Password' => [
			'name' => 'dnsservices_password',
			'description' => 'dns.services Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_doapi'] = [
	'name' => 'DNS-do.de',
	'fields' => [
		'DO_LETOKEN' => [
			'name' => 'do_letoken',
			'description' => 'DO.de API Token',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_do'] = [
	'name' => 'DNS-Domain-Offensive / Resellerinterface / Domainrobot',
	'fields' => [
		'DO_PID' => [
			'name' => 'do_pid',
			'description' => 'DO Customer ID',
			'columnheader' => 'Customer ID',
			'type' => 'textbox',
		],
		'DO_PW' => [
			'name' => 'do_pw',
			'description' => 'DO Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_domeneshop'] = [
	'name' => 'DNS-domeneshop.no',
	'fields' => [
		'DOMENESHOP_Token' => [
			'name' => 'domeneshop_token',
			'description' => 'Domeneshop API Token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
		'DOMENESHOP_Secret' => [
			'name' => 'domeneshop_secret',
			'description' => 'Domeneshop API Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_dp'] = [
	'name' => 'DNS-Dnspod.cn',
	'fields' => [
		'DP_Id' => [
			'name' => 'dp_id',
			'description' => 'Dnspod API ID',
			'columnheader' => 'ID',
			'type' => 'textbox',
		],
		'DP_Key' => [
			'name' => 'dp_key',
			'description' => 'Dnspod API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_dpi'] = [
	'name' => 'DNS-Dnspod.com',
	'fields' => [
		'DPI_Id' => [
			'name' => 'dpi_id',
			'description' => 'Dnspod API ID',
			'columnheader' => 'ID',
			'type' => 'textbox',
		],
		'DPI_Key' => [
			'name' => 'dpi_key',
			'description' => 'Dnspod API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_dreamhost'] = [
	'name' => 'DNS-DreamHost',
	'fields' => [
		'DH_API_KEY' => [
			'name' => 'dh_api_key',
			'description' => 'Dreamhost API Token',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_duckdns'] = [
	'name' => 'DNS-DuckDNS',
	'fields' => [
		'DuckDNS_Token' => [
			'name' => 'duckdns_token',
			'description' => 'DuckDNS API Token (Look in DuckDNS account settings)',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_durabledns'] = [
	'name' => 'DNS-DurableDNS.com',
	'fields' => [
		'DD_API_User' => [
			'name' => 'dd_api_user',
			'description' => 'DurableDNS API User',
			'columnheader' => 'API User',
			'type' => 'textbox',
		],
		'DD_API_Key' => [
			'name' => 'dd_api_key',
			'description' => 'DurableDNS API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_dyn'] = [
	'name' => 'DNS-Dyn.com',
	'fields' => [
		'DYN_Customer' => [
			'name' => 'dyn_customer',
			'description' => 'dyn.com customer ID',
			'columnheader' => 'Customer',
			'type' => 'textbox',
		],
		'DYN_Username' => [
			'name' => 'dyn_username',
			'description' => 'dyn.com API Username (Dyn Managed DNS user, Needs Z&R Permissions for RecordAdd, RecordUpdate, RecordDelete, RecordGet, ZoneGet, ZoneAddNode, ZoneRemoveNode, ZonePublish)',
			'columnheader' => 'API Username',
			'type' => 'textbox',
		],
		'DYN_Password' => [
			'name' => 'dyn_password',
			'description' => 'dyn.com Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_df'] = [
	'name' => 'DNS-dyndnsfree.de',
	'fields' => [
		'DF_user' => [
			'name' => 'df_user',
			'description' => 'dyndnsfree.de Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'DF_password' => [
			'name' => 'df_password',
			'description' => 'dyndnsfree.de Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_dynu'] = [
	'name' => 'DNS-Dynu',
	'fields' => [
		'Dynu_ClientId' => [
			'name' => 'dynu_clientid',
			'description' => 'Dynu API Client ID created in the Dynu account settings',
			'columnheader' => 'API Client ID',
			'type' => 'textbox',
		],
		'Dynu_Secret' => [
			'name' => 'dynu_secret',
			'description' => 'Dynu API Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_easydns'] = [
	'name' => 'DNS-easyDNS',
	'fields' => [
		'EASYDNS_Key' => [
			'name' => 'easydns_key',
			'description' => 'easyDNS API Key. Sign up for a key at https://cp.easydns.com/manage/security/api/signup.php',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
		'EASYDNS_Token' => [
			'name' => 'easydns_token',
			'description' => 'easyDNS API Token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_edgecenter'] = [
	'name' => 'DNS-EdgeCenter.ru',
	'fields' => [
		'EDGECENTER_API_KEY' => [
			'name' => 'edgecenter_api_key',
			'description' => 'API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_euserv'] = [
	'name' => 'DNS-Euserv.eu',
	'fields' => [
		'EUSERV_Username' => [
			'name' => 'euserv_username',
			'description' => 'Euserv.eu Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'EUSERV_Password' => [
			'name' => 'euserv_password',
			'description' => 'Euserv.eu Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_exoscale'] = [
	'name' => 'DNS-Exoscale',
	'fields' => [
		'EXOSCALE_API_KEY' => [
			'name' => 'exoscale_api_key',
			'description' => 'Exoscale API Key',
			'columnheader' => 'Username',
			'type' => 'password',
		],
		'EXOSCALE_SECRET_KEY' => [
			'name' => 'exoscale_secret_key',
			'description' => 'Exoscale Secret Key',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_fornex'] = [
	'name' => 'DNS-Fornex',
	'fields' => [
		'FORNEX_API_KEY' => [
			'name' => 'fornex_api_key',
			'description' => 'Fornex API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_freedns'] = [
	'name' => 'DNS-FreeDNS',
	'fields' => [
		'FREEDNS_User' => [
			'name' => 'freedns_user',
			'description' => 'FreeDNS username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'FREEDNS_Password' => [
			'name' => 'freedns_password',
			'description' => 'FreeDNS Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_freemyip'] = [
	'name' => 'DNS-FreeMyIP.com',
	'fields' => [
		'FREEMYIP_Token' => [
			'name' => 'freemyip_token',
			'description' => 'API Token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_gandi_livedns'] = [
	'name' => 'DNS-Gandi LiveDNS',
	'fields' => [
		'GANDI_LIVEDNS_TOKEN' => [
			'name' => 'gandi_livedns_token',
			'description' => 'Gandi LiveDNS Personal Access Token (PAT)',
			'columnheader' => 'PAT',
			'type' => 'password',
		],
		'GANDI_LIVEDNS_KEY' => [
			'name' => 'gandi_livedns_key',
			'description' => 'Gandi LiveDNS API Key (Deprecated)',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_gcore'] = [
	'name' => 'DNS-Gcore',
	'fields' => [
		'GCORE_Key' => [
			'name' => 'gcore_key',
			'description' => 'Gcore API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_geoscaling'] = [
	'name' => 'DNS-Geoscaling',
	'fields' => [
		'GEOSCALING_Username' => [
			'name' => 'geoscaling_username',
			'description' => 'Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'GEOSCALING_Password' => [
			'name' => 'geoscaling_password',
			'description' => 'Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_gd'] = [
	'name' => 'DNS-GoDaddy',
	'fields' => [
		'GD_Key' => [
			'name' => 'gd_key',
			'description' => 'GoDaddy API Key',
			'columnheader' => 'Key',
			'type' => 'textbox',
		],
		'GD_Secret' => [
			'name' => 'gd_secret',
			'description' => 'GoDaddy API Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_googledomains'] = [
	'name' => 'DNS-Google Domains (DNS API)',
	'fields' => [
		'GOOGLEDOMAINS_ACCESS_TOKEN' => [
			'name' => 'googledomains_access_token',
			'description' => 'Google Domains API Access Token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
		'GOOGLEDOMAINS_ZONE' => [
			'name' => 'googledomains_zone',
			'description' => 'Google Domains DNS Zone',
			'columnheader' => 'Zone',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_hetzner'] = [
	'name' => 'DNS-Hetzner',
	'fields' => [
		'HETZNER_Token' => [
			'name' => 'hetzner_token',
			'description' => 'Hetzner API Token. Visit https://dns.hetzner.com/settings/api-token to retrieve.',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_hexonet'] = [
	'name' => 'DNS-Hexonet',
	'fields' => [
		'Hexonet_Login' => [
			'name' => 'hexonet_login',
			'description' => 'Hexonet Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'Hexonet_Password' => [
			'name' => 'hexonet_password',
			'description' => 'Hexonet Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_huaweicloud'] = [
	'name' => 'DNS-Huawei Cloud',
	'fields' => [
		'HUAWEICLOUD_Username' => [
			'name' => 'huaweicloud_username',
			'description' => 'Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'HUAWEICLOUD_Password' => [
			'name' => 'huaweicloud_password',
			'description' => 'Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
		'HUAWEICLOUD_DomainName' => [
			'name' => 'huaweicloud_domainname',
			'description' => 'Domain Name',
			'columnheader' => 'Domain Name',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_he'] = [
	'name' => 'DNS-Hurricane Electric',
	'fields' => [
		'HE_Username' => [
			'name' => 'he_username',
			'description' => 'Hurricane Electric username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'HE_Password' => [
			'name' => 'he_password',
			'description' => 'Hurricane Electric password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_he_ddns'] = [
	'name' => 'DNS-Hurricane Electric HE.net DDNS',
	'fields' => [
		'HE_DDNS_KEY' => [
			'name' => 'he_ddns_key',
			'description' => 'HE.net DDNS Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_hostingde'] = [
	'name' => 'DNS-hosting.de',
	'fields' => [
		'HOSTINGDE_APIKEY' => [
			'name' => 'hostingde_apikey',
			'description' => 'Hosting.de API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
		'HOSTINGDE_ENDPOINT' => [
			'name' => 'hostingde_endpoint',
			'description' => 'Hosting.de API endpoint, e.g. https://secure.hosting.de',
			'columnheader' => 'Endpoint',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_infoblox'] = [
	'name' => 'DNS-Infoblox',
	'fields' => [
		'Infoblox_Creds' => [
			'name' => 'infoblox_creds',
			'description' => 'Infoblox credentials in <strong>username:password</strong> format',
			'columnheader' => 'Credentials',
			'type' => 'password',
		],
		'Infoblox_Server' => [
			'name' => 'infoblox_server',
			'description' => 'Infoblox server IP address or hostname',
			'columnheader' => 'Server',
			'type' => 'textbox',
		],
		'Infoblox_View' => [
			'name' => 'infoblox_view',
			'description' => 'Infoblox DNS View name, or enter "default"',
			'columnheader' => 'View',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_infomaniak'] = [
	'name' => 'DNS-Infomaniak',
	'fields' => [
		'INFOMANIAK_API_TOKEN' => [
			'name' => 'infomaniak_api_token',
			'description' => 'Infomaniak API token. Visit https://manager.infomaniak.com/v3/&lt;account_id&gt;/api/dashboard and generate a token with the scope Domain.',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
		'DEFAULT_INFOMANIAK_API_URL' => [
			'name' => 'default_infomaniak_api_url',
			'description' => 'Infomaniak API URL (Default: https://api.infomaniak.com)',
			'columnheader' => 'API URL',
			'type' => 'textbox',
		],
		'INFOMANIAK_TTL' => [
			'name' => 'infomaniak_ttl',
			'description' => 'Infomaniak DNS record TTL (Default: 300)',
			'columnheader' => 'TTL',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_ionos'] = [
	'name' => 'DNS-IONOS',
	'fields' => [
		'IONOS_PREFIX' => [
			'name' => 'ionos_prefix',
			'description' => 'Prefix',
			'columnheader' => 'Prefix',
			'type' => 'textbox',
		],
		'IONOS_SECRET' => [
			'name' => 'ionos_secret',
			'description' => 'Secret. Read https://beta.developer.hosting.ionos.de/docs/getstarted to learn how to get a prefix and secret.',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_ionos_cloud'] = [
	'name' => 'DNS-IONOS Cloud DNS (ionos.com)',
	'fields' => [
		'IONOS_TOKEN' => [
			'name' => 'ionos_token',
			'description' => 'API Token',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_ipv64'] = [
	'name' => 'DNS-IPv64.net',
	'fields' => [
		'IPv64_Token' => [
			'name' => 'ipv64_token',
			'description' => 'IPv64.net Access Token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_internetbs'] = [
	'name' => 'DNS-Internet.BS',
	'fields' => [
		'INTERNETBS_API_KEY' => [
			'name' => 'internetbs_api_key',
			'description' => 'Internet.BS API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
		'INTERNETBS_API_PASSWORD' => [
			'name' => 'internetbs_api_password',
			'description' => 'Internet.BS API Password',
			'columnheader' => 'API Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_inwx'] = [
	'name' => 'DNS-INWX.de',
	'fields' => [
		'INWX_User' => [
			'name' => 'inwx_username',
			'description' => 'INWX.de username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'INWX_Password' => [
			'name' => 'inwx_password',
			'description' => 'INWX.de password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
		'INWX_Shared_Secret' => [
			'name' => 'inwx_shared_secret',
			'description' => 'INWX.de shared secret',
			'columnheader' => 'Shared Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_ispconfig'] = [
	'name' => 'DNS-ISPConfig',
	'fields' => [
		'ISPC_User' => [
			'name' => 'ispc_user',
			'description' => 'ISPConfig remoteUser',
			'columnheader' => 'User',
			'type' => 'textbox',
		],
		'ISPC_Password' => [
			'name' => 'ispc_password',
			'description' => 'ISPConfig remotePassword',
			'columnheader' => 'Password',
			'type' => 'password',
		],
		'ISPC_Api' => [
			'name' => 'ispc_api',
			'description' => 'API URL (e.g. https://ispc.domain.tld:8080/remote/json.php )',
			'columnheader' => 'Api',
			'type' => 'textbox',
		],
		'ISPC_Api_Insecure' => [
			'name' => 'ispc_api_insecure',
			'description' => 'Set 1 for insecure and 0 for secure. ' .
					'Controls whether the server TLS certificate is checked for validity (0) or always accepted (1)',
			'columnheader' => 'Allow Insecure',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_jd'] = [
	'name' => 'DNS-jdcloud.com',
	'fields' => [
		'JD_ACCESS_KEY_ID' => [
			'name' => 'jd_access_key_id',
			'description' => 'jdcloud Access Key ID',
			'columnheader' => 'Key ID',
			'type' => 'textbox',
		],
		'JD_ACCESS_KEY_SECRET' => [
			'name' => 'jd_access_key_secret',
			'description' => 'jdcloud Access Key Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
		'JD_REGION' => [
			'name' => 'jd_region',
			'description' => 'jdcloud Region',
			'columnheader' => 'Region',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_joker'] = [
	'name' => 'DNS-Joker.com',
	'fields' => [
		'JOKER_USERNAME' => [
			'name' => 'joker_username',
			'description' => 'Joker.com Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'JOKER_PASSWORD' => [
			'name' => 'joker_password',
			'description' => 'Joker.com Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_kappernet'] = [
	'name' => 'DNS-kapper.net',
	'fields' => [
		'KAPPERNETDNS_Key' => [
			'name' => 'kappernetdns_key',
			'description' => 'kapper.net API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
		'KAPPERNETDNS_Secret' => [
			'name' => 'kappernetdns_secret',
			'description' => 'kapper.net API Secret',
			'columnheader' => 'API Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_kinghost'] = [
	'name' => 'DNS-Kinghost API',
	'fields' => [
		'KINGHOST_Username' => [
			'name' => 'kinghost_username',
			'description' => 'Kinghost API Username',
			'columnheader' => 'Kinghost Username',
			'type' => 'textbox',
		],
		'KINGHOST_Password' => [
			'name' => 'kinghost_password',
			'description' => 'Kinghost API Password',
			'columnheader' => 'Kinghost Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_knot'] = [
	'name' => 'DNS-Knot API',
	'fields' => [
		'KNOT_SERVER' => [
			'name' => 'knot_server',
			'description' => 'IP address of the Knot server',
			'columnheader' => 'Knot Server',
			'type' => 'textbox',
		],
		'KNOT_KEY' => [
			'name' => 'knot_key',
			'description' => 'Knot TSIG Key',
			'columnheader' => 'Knot Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_la'] = [
	'name' => 'DNS-DNS.la',
	'fields' => [
		'LA_Id' => [
			'name' => 'la_id',
			'description' => 'ID',
			'columnheader' => 'ID',
			'type' => 'textbox',
		],
		'LA_Key' => [
			'name' => 'la_key',
			'description' => 'Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_leaseweb'] = [
	'name' => 'DNS-Leaseweb',
	'fields' => [
		'LSW_Key' => [
			'name' => 'lsw_key',
			'description' => 'Leaseweb API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_limacity'] = [
	'name' => 'DNS-Lima-City.de',
	'fields' => [
		'LIMACITY_APIKEY' => [
			'name' => 'limacity_apikey',
			'description' => 'API Key must have the following roles: dns.admin, domains.reader',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_linode'] = [
	'name' => 'DNS-Linode (Deprecated -- Use v4)',
	'fields' => [
		'LINODE_API_KEY' => [
			'name' => 'linode_api_key',
			'description' => 'Linode API Key',
			'columnheader' => 'Linode API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_linode_v4'] = [
	'name' => 'DNS-Linode v4 API',
	'fields' => [
		'LINODE_V4_API_KEY' => [
			'name' => 'linode_v4_api_key',
			'description' => 'Linode v4 API Key',
			'columnheader' => 'Linode v4 API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_loopia'] = [
	'name' => 'DNS-Loopia',
	'fields' => [
		'LOOPIA_User' => [
			'name' => 'loopia_user',
			'description' => 'Loopia username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'LOOPIA_Password' => [
			'name' => 'loopia_password',
			'description' => 'Loopia Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_lua'] = [
	'name' => 'DNS-Luadns',
	'fields' => [
		'LUA_Key' => [
			'name' => 'lua_key',
			'description' => 'Luadns API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
		'LUA_Email' => [
			'name' => 'lua_email',
			'description' => 'Luadns API Email Address',
			'columnheader' => 'Email',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_miab'] = [
	'name' => 'DNS-MailinaBox',
	'fields' => [
		'MIAB_Username' => [
			'name' => 'miab_username',
			'description' => 'MailinaBox Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'MIAB_Password' => [
			'name' => 'miab_password',
			'description' => 'MailinaBox Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
		'MIAB_Server' => [
			'name' => 'miab_server',
			'description' => 'MailinaBox Server',
			'columnheader' => 'Server',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_mijnhost'] = [
	'name' => 'DNS-mijn.host',
	'fields' => [
		'MIJNHOST_API_KEY' => [
			'name' => 'mijnhost_api_key',
			'description' => 'mijn.host API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_misaka'] = [
	'name' => 'DNS-misaka.io',
	'fields' => [
		'Misaka_Key' => [
			'name' => 'misaka_key',
			'description' => 'misaka.io Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_mydnsjp'] = [
	'name' => 'DNS-MyDNS.jp',
	'fields' => [
		'MYDNSJP_MasterID' => [
			'name' => 'mydnsjp_masterid',
			'description' => 'MyDNS.jp Master ID',
			'columnheader' => 'Master ID',
			'type' => 'textbox',
		],
		'MYDNSJP_Password' => [
			'name' => 'mydnsjp_password',
			'description' => 'MyDNS.jp Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_mythic_beasts'] = [
	'name' => 'DNS-Mythic Beasts',
	'fields' => [
		'MB_AK' => [
			'name' => 'mb_ak',
			'description' => 'OAuth2 Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
		'MB_AS' => [
			'name' => 'mb_as',
			'description' => 'OAuth2 Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_namecom'] = [
	'name' => 'DNS-name.com',
	'fields' => [
		'Namecom_Username' => [
			'name' => 'namecom_username',
			'description' => 'Name.com username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'Namecom_Token' => [
			'name' => 'namecom_token',
			'description' => 'Name.com API Token',
			'columnheader' => 'API Token',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_namecheap'] = [
	'name' => 'DNS-Namecheap',
	'fields' => [
		'NAMECHEAP_API_KEY' => [
			'name' => 'namecheap_api_key',
			'description' => 'Namecheap API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
		'NAMECHEAP_USERNAME' => [
			'name' => 'namecheap_username',
			'description' => 'Namecheap Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_nm'] = [
	'name' => 'DNS-NameMaster.de',
	'fields' => [
		'NM_user' => [
			'name' => 'nm_user',
			'description' => 'namemaster.de API username',
			'columnheader' => 'User',
			'type' => 'textbox',
		],
		'NM_sha256' => [
			'name' => 'nm_sha256',
			'description' => 'namemaster.de API password as SHA256 hash',
			'columnheader' => 'Password Hash',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_nanelo'] = [
	'name' => 'DNS-Nanelo.com',
	'fields' => [
		'NANELO_TOKEN' => [
			'name' => 'nanelo_token',
			'description' => 'Nanelo.com Access Token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_nederhost'] = [
	'name' => 'DNS-NederHost',
	'fields' => [
		'NederHost_Key' => [
			'name' => 'nederhost_key',
			'description' => 'NederHost API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_namesilo'] = [
	'name' => 'DNS-Namesilo',
	'fields' => [
		'Namesilo_Key' => [
			'name' => 'namesilo_key',
			'description' => 'Namesilo API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_neodigit'] = [
	'name' => 'DNS-Neodigit.net',
	'fields' => [
		'NEODIGIT_API_TOKEN' => [
			'name' => 'neodigit_api_token',
			'description' => 'Neodigit API Token',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_netcup'] = [
	'name' => 'DNS-netcup.net',
	'fields' => [
		'NC_Apikey' => [
			'name' => 'nc_apikey',
			'description' => 'Netcup API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
		'NC_Apipw' => [
			'name' => 'nc_apipw',
			'description' => 'Netcup API Password',
			'columnheader' => 'API Password',
			'type' => 'password',
		],
		'NC_CID' => [
			'name' => 'nc_cid',
			'description' => 'Netcup Customer ID/Number',
			'columnheader' => 'CID',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_netlify'] = [
	'name' => 'DNS-Netlify',
	'fields' => [
		'NETLIFY_ACCESS_TOKEN' => [
			'name' => 'netlify_access_token',
			'description' => 'Netlify API Token',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_nic'] = [
	'name' => 'DNS-nic.ru',
	'fields' => [
		'NIC_ClientID' => [
			'name' => 'nic_clientid',
			'description' => 'nic.ru API Client ID',
			'columnheader' => 'API ClientID',
			'type' => 'textbox',
		],
		'NIC_ClientSecret' => [
			'name' => 'nic_clientsecret',
			'description' => 'nic.ru API Client Secret',
			'columnheader' => 'API Client Secret',
			'type' => 'textbox',
		],
		'NIC_Username' => [
			'name' => 'nic_username',
			'description' => 'nic.ru Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'NIC_Password' => [
			'name' => 'nic_password',
			'description' => 'nic.ru Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_njalla'] = [
	'name' => 'DNS-Njalla',
	'fields' => [
		'NJALLA_Token' => [
			'name' => 'netlify_access_token',
			'description' => 'Njalla API Token',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_nsone'] = [
	'name' => 'DNS-NS1 (nsone.com)',
	'fields' => [
		'NS1_Key' => [
			'name' => 'ns1_key',
			'description' => 'NS1 API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_nw'] = [
	'name' => 'DNS-NW API: Nexcess / Thermo.io / Futurehosting',
	'fields' => [
		'NW_API_TOKEN' => [
			'name' => 'nw_api_token',
			'description' => 'NW API Token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
		'NW_API_ENDPOINT' => [
			'name' => 'nw_api_endpoint',
			'description' => 'Choose the NW API Endpoint',
			'columnheader' => 'NW API Endpoint',
			'type' => 'select',
			'items' => [
				'' => [
					'name' => 'None'
				],
				'https://portal.nexcess.net' => [
					'name' => 'portal.nexcess.net'
				],
				'https://core.thermo.io' => [
					'name' => 'core.thermo.io'
				],
				'https://my.futurehosting.com' => [
					'name' => 'my.futurehosting.com'
				],
			],
		],
	]
];
$acme_domain_validation_method['dns_omglol'] = [
	'name' => 'DNS-omg.lol',
	'fields' => [
		'OMG_Address' => [
			'name' => 'omg_address',
			'description' => 'omg.lol address, without the preceding @ (See https://home.omg.lol/dashboard)',
			'columnheader' => 'Address',
			'type' => 'textbox',
		],
		'OMG_ApiKey' => [
			'name' => 'omg_apikey',
			'description' => 'API Key from omg.lol (https://home.omg.lol/account)',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_one'] = [
	'name' => 'DNS-one.com',
	'fields' => [
		'ONECOM_User' => [
			'name' => 'onecom_user',
			'description' => 'One.com Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'ONECOM_Password' => [
			'name' => 'onecom_password',
			'description' => 'One.com Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_online'] = [
	'name' => 'DNS-Online.net',
	'fields' => [
		'ONLINE_API_KEY' => [
			'name' => 'online_api_key',
			'description' => 'Online.net API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_openprovider'] = [
	'name' => 'DNS-OpenProvider',
	'fields' => [
		'OPENPROVIDER_USER' => [
			'name' => 'openprovider_user',
			'description' => 'OpenProvider Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'OPENPROVIDER_PASSWORDHASH' => [
			'name' => 'openprovider_passwordhash',
			'description' => 'OpenProvider Password Hash',
			'columnheader' => 'Password Hash',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_openprovider_rest'] = [
	'name' => 'DNS-OpenProvider (REST)',
	'fields' => [
		'OPENPROVIDER_REST_USERNAME' => [
			'name' => 'openprovider_rest_username',
			'description' => 'OpenProvider Account Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'OPENPROVIDER_REST_PASSWORD' => [
			'name' => 'openprovider_rest_password',
			'description' => 'OpenProvider Account Password',
			'columnheader' => 'Password Hash',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_oci'] = [
	'name' => 'DNS-Oracle Cloud Infrastructure',
	'fields' => [
		'OCI_CLI_TENANCY' => [
			'name' => 'oci_cli_tenancy',
			'description' => 'OCID of tenancy that contains the target DNS zone',
			'columnheader' => 'Tenancy',
			'type' => 'textbox',
		],
		'OCI_CLI_USER' => [
			'name' => 'oci_cli_user',
			'description' => 'OCID of user with permission to add/remove records from zones',
			'columnheader' => 'User',
			'type' => 'textbox',
		],
		'OCI_CLI_REGION' => [
			'name' => 'oci_cli_region',
			'description' => 'Tenancy home region',
			'columnheader' => 'Region',
			'type' => 'textbox',
		],
		'OCI_CLI_KEY' => [
			'name' => 'oci_cli_key',
			'description' => 'The private API signing key in PEM format. Using an encrypted private key that needs a passphrase is not supported.',
			'columnheader' => 'Key (PEM)',
			'type' => 'textarea',
		],
	]
];
$acme_domain_validation_method['dns_ovh'] = [
	'name' => 'DNS-ovh / kimsufi / soyoustart / runabove',
	'fields' => [
		'OVH_AK' => [
			'name' => 'ovh_ak',
			'description' => 'OVH Application Key',
			'columnheader' => 'Application Key',
			'type' => 'password',
		],
		'OVH_AS' => [
			'name' => 'ovh_as',
			'description' => 'OVH Application Secret',
			'columnheader' => 'Application Secret',
			'type' => 'password',
		],
		'OVH_CK' => [
			'name' => 'ovh_ck',
			'description' => 'OVH Consumer Key',
			'columnheader' => 'Consumer Key',
			'type' => 'password',
		],
		'OVH_END_POINT' => [
			'name' => 'ovh_end_point',
			'description' => 'Choose the OVH API Endpoint / Region',
			'columnheader' => 'API Endpoint',
			'type' => 'select',
			'items' => [
				'' => [
					'name' => 'None'
				],
				'ovh-eu' => [
					'name' => 'OVH Europe (Default)'
				],
				'ovh-ca' => [
					'name' => 'OVH Canada'
				],
				'ovh-us' => [
					'name' => 'OVH USA'
				],
				'kimsufi-eu' => [
					'name' => 'Kimsufi Europe'
				],
				'kimsufi-ca' => [
					'name' => 'Kimsufi Canada'
				],
				'soyoustart-eu' => [
					'name' => 'SoYouStart Europe'
				],
				'soyoustart-ca' => [
					'name' => 'SoYouStart Canada'
				],
				'runabove-ca' => [
					'name' => 'RunAbove Canada'
				],
			],
		],
	]
];
$acme_domain_validation_method['dns_pleskxml'] = [
	'name' => 'DNS-Plesk',
	'fields' => [
		'pleskxml_user' => [
			'name' => 'pleskxml_user',
			'description' => 'Plesk User',
			'columnheader' => 'User',
			'type' => 'textbox',
		],
		'pleskxml_pass' => [
			'name' => 'pleskxml_pass',
			'description' => 'Plesk Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
		'pleskxml_uri' => [
			'name' => 'pleskxml_uri',
			'description' => 'Plesk Server URI',
			'columnheader' => 'URI',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_pointhq'] = [
	'name' => 'DNS-PointDNS / pointhq.com',
	'fields' => [
		'PointHQ_Key' => [
			'name' => 'pointhq_key',
			'description' => 'PointHQ API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
		'PointHQ_Email' => [
			'name' => 'pointhq_email',
			'description' => 'PointHQ account E-mail address',
			'columnheader' => 'E-mail',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_porkbun'] = [
	'name' => 'DNS-Porkbun',
	'fields' => [
		'PORKBUN_API_KEY' => [
			'name' => 'porkbun_api_key',
			'description' => 'API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
		'PORKBUN_SECRET_API_KEY' => [
			'name' => 'porkbun_secret_api_key',
			'description' => 'Secret API Key. Obtain the key and secret from https://porkbun.com/account/api',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_pdns'] = [
	'name' => 'DNS-PowerDNS',
	'fields' => [
		'PDNS_Url' => [
			'name' => 'pdns_url',
			'description' => 'PowerDNS URL (e.g. http://ns.example.com:8081 )',
			'columnheader' => 'URL',
			'type' => 'textbox',
		],
		'PDNS_ServerId' => [
			'name' => 'pdns_serverid',
			'description' => 'PowerDNS ServerId (e.g. localhost )',
			'columnheader' => 'ServerID',
			'type' => 'textbox',
		],
		'PDNS_Token' => [
			'name' => 'pdns_token',
			'description' => 'PowerDNS Token (e.g. 0123456789ABCDEF )',
			'columnheader' => 'Token',
			'type' => 'password',
		],
		'PDNS_Ttl' => [
			'name' => 'pdns_ttl',
			'description' => 'PowerDNS Record TTL (e.g. 60 )',
			'columnheader' => 'TTL',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_rackcorp'] = [
	'name' => 'DNS-RackCorp',
	'fields' => [
		'RACKCORP_APIUUID' => [
			'name' => 'rackcorp_apiuuid',
			'description' => 'API UUID',
			'columnheader' => 'UUID',
			'type' => 'textbox',
		],
		'RACKCORP_APISECRET' => [
			'name' => 'rackcorp_apisecret',
			'description' => 'API Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_rackspace'] = [
	'name' => 'DNS-Rackspace',
	'fields' => [
		'RACKSPACE_Username' => [
			'name' => 'rackspace_username',
			'description' => 'Rackspace Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'RACKSPACE_Apikey' => [
			'name' => 'rackspace_apikey',
			'description' => 'Rackspace API Key',
			'columnheader' => 'API Key',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_rage4'] = [
	'name' => 'DNS-RAGE4',
	'fields' => [
		'RAGE4_USERNAME' => [
			'name' => 'rage4_username',
			'description' => 'Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'RAGE4_TOKEN' => [
			'name' => 'rage4_token',
			'description' => 'Token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_rcode0'] = [
	'name' => 'DNS-Rcode0',
	'fields' => [
		'RCODE0_API_TOKEN' => [
			'name' => 'rcode0_api_token',
			'description' => 'Rcode0 API Token',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
		'RCODE0_URL' => [
			'name' => 'rcode0_url',
			'description' => 'Rcode0 URL',
			'columnheader' => 'URL',
			'type' => 'textbox',
		],
		'RCODE0_TTL' => [
			'name' => 'rcode0_ttl',
			'description' => 'Rcode0 TTL',
			'columnheader' => 'TTL',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_regru'] = [
	'name' => 'DNS-reg.ru',
	'fields' => [
		'REGRU_API_Username' => [
			'name' => 'regru_api_username',
			'description' => 'reg.ru Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'REGRU_API_Password' => [
			'name' => 'regru_api_password',
			'description' => 'reg.ru API Password',
			'columnheader' => 'API Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_scaleway'] = [
	'name' => 'DNS-Scaleway',
	'fields' => [
		'SCALEWAY_API_TOKEN' => [
			'name' => 'scaleway_api_token',
			'description' => 'API Token',
			'columnheader' => 'Token',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_schlundtech'] = [
	'name' => 'DNS-schlundtech.de',
	'fields' => [
		'SCHLUNDTECH_USER' => [
			'name' => 'schlundtech_user',
			'description' => 'schlundtech.de Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'SCHLUNDTECH_PASSWORD' => [
			'name' => 'schlundtech_password',
			'description' => 'schlundtech.de Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_selectel'] = [
	'name' => 'DNS-Selectel',
	'fields' => [
		'SL_Ver' => [
			'name' => 'sl_ver',
			'description' => 'Selectel API Version and use options matching that API version.',
			'columnheader' => 'API Version',
			'type' => 'select',
			'items' => [
				'v2' => ['name' => 'v2'],
				'v1' => ['name' => 'v1 (Deprecated)'],
			],
		],
		'SL_Login_ID' => [
			'name' => 'sl_login_id',
			'description' => 'v2 Account ID',
			'columnheader' => 'v2 Account',
			'type' => 'textbox',
		],
		'SL_Project_Name' => [
			'name' => 'sl_project_name',
			'description' => 'v2 Project Name',
			'columnheader' => 'v2 Project',
			'type' => 'textbox',
		],
		'SL_Login_Name' => [
			'name' => 'sl_login_name',
			'description' => 'v2 Service Username',
			'columnheader' => 'v2 Username',
			'type' => 'textbox',
		],
		'SL_Pswd' => [
			'name' => 'sl_pswd',
			'description' => 'v2 User Password',
			'columnheader' => 'v2 Password',
			'type' => 'password',
		],
		'SL_Expire' => [
			'name' => 'sl_expire',
			'description' => 'v2 Token Lifetime (Minutes, 0-1440)',
			'columnheader' => 'v2 Lifetime',
			'type' => 'textbox',
		],

		'SL_Key' => [
			'name' => 'sl_key',
			'description' => 'Selectel v1 API Key',
			'columnheader' => 'v1 Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_selfhost'] = [
	'name' => 'DNS-Selfhost',
	'fields' => [
		'SELFHOSTDNS_USERNAME' => [
			'name' => 'selfhostdns_username',
			'description' => 'Username (Customer number, not email address or DynDNS account)',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'SELFHOSTDNS_PASSWORD' => [
			'name' => 'selfhostdns_password',
			'description' => 'Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
		'SELFHOSTDNS_MAP' => [
			'name' => 'selfhostdns_map',
			'description' => 'Record ID (Edit the record, value is shown in brackets)',
			'columnheader' => 'Record ID',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_servercow'] = [
	'name' => 'DNS-Servercow',
	'fields' => [
		'SERVERCOW_API_Username' => [
			'name' => 'servercow_api_username',
			'description' => 'Servercow username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'SERVERCOW_API_Password' => [
			'name' => 'servercow_api_password',
			'description' => 'Servercow password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_simply'] = [
	'name' => 'DNS-Simply.com',
	'fields' => [
		'SIMPLY_AccountName' => [
			'name' => 'simply_accountname',
			'description' => 'Account Name',
			'columnheader' => 'Name',
			'type' => 'textbox',
		],
		'SIMPLY_ApiKey' => [
			'name' => 'simply_apikey',
			'description' => 'API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
		'SIMPLY_Api' => [
			'name' => 'simply_api',
			'description' => 'API Endpoint URL. Default: https://api.simply.com/1',
			'columnheader' => 'API',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_spaceship'] = [
	'name' => 'DNS-Spaceship.com',
	'fields' => [
		'SPACESHIP_API_KEY' => [
			'name' => 'spaceship_api_key',
			'description' => 'API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
		'SPACESHIP_API_SECRET' => [
			'name' => 'spaceship_api_secret',
			'description' => 'API Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
		'SPACESHIP_ROOT_DOMAIN' => [
			'name' => 'spaceship_root_domain',
			'description' => 'Root domain. Manually specify the root domain if auto-detection fails. Optional.',
			'columnheader' => 'Root Domain',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_technitium'] = [
	'name' => 'DNS-Technitium DNS Server',
	'fields' => [
		'Technitium_Server' => [
			'name' => 'technitium_server',
			'description' => 'Technitium DNS Server Address',
			'columnheader' => 'Server',
			'type' => 'textbox',
		],
		'Technitium_Token' => [
			'name' => 'technitium_token',
			'description' => 'API Token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_tele3'] = [
	'name' => 'DNS-Tele3.cz',
	'fields' => [
		'TELE3_Key' => [
			'name' => 'tele3_key',
			'description' => 'Tele3 Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
		'TELE3_Secret' => [
			'name' => 'tele3_secret',
			'description' => 'Tele3 Secret',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_tencent'] = [
	'name' => 'DNS-Tencent',
	'fields' => [
		'Tencent_SecretId' => [
			'name' => 'tencent_secretid',
			'description' => 'Tencent Secret ID',
			'columnheader' => 'Secret ID',
			'type' => 'password',
		],
		'Tencent_SecretKey' => [
			'name' => 'tencent_secretkey',
			'description' => 'Tencent Secret Key',
			'columnheader' => 'Secret Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_timeweb'] = [
	'name' => 'DNS-Timeweb.Cloud',
	'fields' => [
		'TW_Token' => [
			'name' => 'tw_token',
			'description' => 'API token (JWT, https://timeweb.cloud/my/api-keys)',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_udr'] = [
	'name' => 'DNS-United-Domains Reselling',
	'fields' => [
		'UDR_USER' => [
			'name' => 'udr_user',
			'description' => 'Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'UDR_PASS' => [
			'name' => 'udr_pass',
			'description' => 'Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_ultra'] = [
	'name' => 'DNS-UltraDNS',
	'fields' => [
		'ULTRA_USR' => [
			'name' => 'ultra_usr',
			'description' => 'UltraDNS Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'ULTRA_PWD' => [
			'name' => 'ULTRA_PWD',
			'description' => 'UltraDNS Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_unoeuro'] = [
	'name' => 'DNS-UnoEuro (Deprecated -- Now simply.com)',
	'fields' => [
		'UNO_User' => [
			'name' => 'uno_user',
			'description' => 'UnoEuro username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'UNO_Key' => [
			'name' => 'uno_key',
			'description' => 'UnoEuro API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_variomedia'] = [
	'name' => 'DNS-variomedia.de',
	'fields' => [
		'VARIOMEDIA_API_TOKEN' => [
			'name' => 'variomedia_api_token',
			'description' => 'variomedia.de API Token',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_veesp'] = [
	'name' => 'DNS-Veesp',
	'fields' => [
		'VEESP_User' => [
			'name' => 'veesp_user',
			'description' => 'Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'VEESP_Password' => [
			'name' => 'veesp_password',
			'description' => 'Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_vercel'] = [
	'name' => 'DNS-Vercel',
	'fields' => [
		'VERCEL_TOKEN' => [
			'name' => 'vercel_token',
			'description' => 'Vercel Token',
			'columnheader' => 'Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_vscale'] = [
	'name' => 'DNS-vscale.io',
	'fields' => [
		'VSCALE_API_KEY' => [
			'name' => 'vscale_api_key',
			'description' => 'vscale API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_vultr'] = [
	'name' => 'DNS-vultr.com',
	'fields' => [
		'VULTR_API_KEY' => [
			'name' => 'vultr_api_key',
			'description' => 'vultr.com API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_websupport'] = [
	'name' => 'DNS-websupport.sk',
	'fields' => [
		'WS_ApiKey' => [
			'name' => 'ws_apikey',
			'description' => 'API Key / "Identifier" in the WS Admin',
			'columnheader' => 'Key',
			'type' => 'password',
		],
		'WS_ApiSecret' => [
			'name' => 'ws_apisecret',
			'description' => 'API Secret / "Secret key" in the WS Admin. Obtain the API Key and Secret from https://admin.websupport.sk/en/auth/apiKey.',
			'columnheader' => 'Secret',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_west_cn'] = [
	'name' => 'DNS-West.cn',
	'fields' => [
		'WEST_Username' => [
			'name' => 'west_username',
			'description' => 'West.cn Domain API Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'WEST_Key' => [
			'name' => 'west_key',
			'description' => 'West.cn Domain API Key',
			'columnheader' => 'Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_world4you'] = [
	'name' => 'DNS-World4You',
	'fields' => [
		'WORLD4YOU_USERNAME' => [
			'name' => 'world4you_username',
			'description' => 'Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'WORLD4YOU_PASSWORD' => [
			'name' => 'world4you_password',
			'description' => 'Password',
			'columnheader' => 'Password',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_yandex'] = [
	'name' => 'DNS-Yandex',
	'fields' => [
		'PDD_Token' => [
			'name' => 'pdd_token',
			'description' => 'Yandex PDD Token, generate at <a href="https://pddimp.yandex.ru/api2/admin/get_token">https://pddimp.yandex.ru/api2/admin/get_token</a>',
			'columnheader' => 'PDD Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_yandex360'] = [
	'name' => 'DNS-Yandex 360 for Business',
	'fields' => [
		'YANDEX360_CLIENT_ID' => [
			'name' => 'yandex360_client_id',
			'description' => 'OAuth 2.0 Client ID',
			'columnheader' => 'Client ID',
			'type' => 'textbox',
		],
		'YANDEX360_CLIENT_SECRET' => [
			'name' => 'yandex360_client_secret',
			'description' => 'OAuth 2.0 Client secret',
			'columnheader' => 'Client Secret',
			'type' => 'password',
		],
		'YANDEX360_ORG_ID' => [
			'name' => 'yandex360_org_id',
			'description' => 'Organization ID (Optional)',
			'columnheader' => 'Address',
			'type' => 'textbox',
		],
		'YANDEX360_ACCESS_TOKEN' => [
			'name' => 'yandex360_access_token',
			'description' => 'OAuth 2.0 Access token (Optional)',
			'columnheader' => 'Access Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_yc'] = [
	'name' => 'DNS-Yandex Cloud',
	'fields' => [
		'YC_Zone_ID' => [
			'name' => 'yc_zone_id',
			'description' => 'DNS Zone ID',
			'columnheader' => 'Zone',
			'type' => 'textbox',
		],
		'YC_Folder_ID' => [
			'name' => 'yc_folder_id',
			'description' => 'Yandex Cloud Folder ID',
			'columnheader' => 'Folder',
			'type' => 'textbox',
		],
		'YC_SA_ID' => [
			'name' => 'yc_sa_id',
			'description' => 'Service Account ID',
			'columnheader' => 'Service Account',
			'type' => 'textbox',
		],
		'YC_SA_Key_ID' => [
			'name' => 'yc_sa_key_id',
			'description' => 'Service Account IAM Key ID',
			'columnheader' => 'IAM Key',
			'type' => 'textbox',
		],
		'YC_SA_Key_File_PEM_b64' => [
			'name' => 'yc_sa_key_file_pem_b64',
			'description' => 'Base64 content of private key.',
			'columnheader' => 'Key',
			'type' => 'textarea',
		],
	]
];
$acme_domain_validation_method['dns_zonomi'] = [
	'name' => 'DNS-Zonomi',
	'fields' => [
		'ZM_Key' => [
			'name' => 'zm_key',
			'description' => 'Zonomi API Key',
			'columnheader' => 'API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_zone'] = [
	'name' => 'DNS-Zone.ee',
	'fields' => [
		'ZONE_Username' => [
			'name' => 'zone_username',
			'description' => 'Zone.ee Username',
			'columnheader' => 'Username',
			'type' => 'textbox',
		],
		'ZONE_Key' => [
			'name' => 'zone_key',
			'description' => 'Zone.ee API Key',
			'columnheader' => 'API Key',
			'type' => 'textbox',
		],
	]
];
$acme_domain_validation_method['dns_zoneedit'] = [
	'name' => 'DNS-ZoneEdit.com',
	'fields' => [
		'ZONEEDIT_ID' => [
			'name' => 'zoneedit_id',
			'description' => 'ZoneEdit ID',
			'columnheader' => 'ID',
			'type' => 'textbox',
		],
		'ZONEEDIT_Token' => [
			'name' => 'zoneedit_token',
			'description' => 'ZoneEdit API Token',
			'columnheader' => 'API Token',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['dns_zilore'] = [
	'name' => 'DNS-Zilore',
	'fields' => [
		'Zilore_Key' => [
			'name' => 'zilore_key',
			'description' => 'Zilore API Key',
			'columnheader' => 'Zilore API Key',
			'type' => 'password',
		],
	]
];
$acme_domain_validation_method['anydns'] = [
	'name' => 'notforuser',
	'fields' => [
		'challengealias' => [
			'name' => 'challengealias',
			'description' => '(Optional) Adds the --challenge-alias flag to the acme.sh call.<br/>To use a CNAME for _acme-challenge.importantDomain.tld to direct the acme validation to a different (sub)domain _acme-challenge.aliasDomainForValidationOnly.tld, configure the alternate domain here.<br/>More information can be found <a href="https://github.com/acmesh-official/acme.sh/wiki/DNS-alias-mode" target="_new">here</a>.',
			'columnheader' => 'Enable DNS alias mode',
			'type' => 'textbox',
		],
		'challengedomain' => [
			'name' => 'challengedomain',
			'description' => '(Optional) Uses the challenge domain alias value as --domain-alias instead in the acme.sh call.',
			'columnheader' => 'Enable DNS domain alias mode',
			'type' => 'checkbox',
		],
	]
];

//TODO add more challenge validation types
/*
$acme_domain_validation_method['dns-someprovider'] = [
	'name' => 'Some Provider',
	'fields' => [
		'Provider_Value' => [
			'name' => 'provider_value',
			'description' => 'Description of this field ' .
					'if the description is long, break it into multiple lines ',
			'columnheader' => 'Value',
			'type' => 'textbox',
			'size' => '50',
		],
		'Provider_Secret' => [
			'name' => 'provider_secret',
			'description' => 'Secret of some sort',
			'columnheader' => 'Secret',
			'type' => 'password',
			'size' => '50',
		],
	]
];
*/

//TODO add more 'actions'
$acme_newcertificateactions = array(
	'shellcommand' => array('name' => "Shell Command"),
	'php_command' => array('name' => "PHP Command Script"),
	'servicerestart' => array('name' => "Restart Local Service"),
	'xmlrpcservicerestart' => array('name' => "Restart Remote Service (XMLRPC)"),
);

// </editor-fold>
#end

$a_keylength = array();
$a_keylength['2048'] = array('name' => "2048-bit RSA");
$a_keylength['3072'] = array('name' => "3072-bit RSA");
$a_keylength['4096'] = array('name' => "4096-bit RSA");
$a_keylength['ec-256'] = array('name' => "256-bit ECDSA", 'ecc' => true);
$a_keylength['ec-384'] = array('name' => "384-bit ECDSA", 'ecc' => true);
$a_keylength['custom'] = array('name' => "Custom...");

function set_cronjob() {
	if (config_path_enabled('installedpackages/acme')) {
		install_cron_job('/usr/local/pkg/acme/acme_command.sh "renewall" | sed "s/^/NOTICE /" | /usr/bin/logger -p user.notice -t ACME 2>&1', true, "16", "3");
	} else {
		install_cron_job('/usr/local/pkg/acme/acme_command.sh "renewall" | sed "s/^/NOTICE /" | /usr/bin/logger -p user.notice -t ACME 2>&1', false);
	}
}

function acme_custom_php_deinstall_command() {
	global $static_output;
	$static_output .= "Acme, running acme_custom_php_deinstall_command()\n";
	update_output_window($static_output);
	$static_output .= "Acme, uninstalling cron job\n";
	update_output_window($static_output);
	/* Remove cron job (if present), see #13828 */
	install_cron_job('/usr/local/pkg/acme/acme_command.sh "renewall"', false);
	$static_output .= "Acme, running acme_custom_php_deinstall_command() DONE\n";
	update_output_window($static_output);
}

function acme_custom_php_install_command() {
	global $static_output;
	$static_output .= "Acme, running acme_custom_php_install_command()\n";
	update_output_window($static_output);
	$static_output .= "Acme, installing cron job if enabled\n";
	update_output_window($static_output);
	/* Remove old cron job (if present), see #9279 */
	install_cron_job('/usr/local/pkg/acme/acme_command.sh "renewall"', false);
	set_cronjob();
	/* convert <desc> to <descr> to allow IDN characters, #10442 */
	acme_convert_desc_to_descr();
	acme_convert_letsencryptv1_to_letsencryptv2();
	$static_output .= "Acme, running acme_custom_php_install_command() DONE\n";
	update_output_window($static_output);
	acme_write_all_certificates();
}

function acme_convert_desc_to_descr() {
	foreach (array('accountkeys', 'certificates') as $a_array) {
		$acme_item_config = config_get_path("installedpackages/acme/{$a_array}/item");
		foreach ($acme_item_config as & $item) {
			if (isset($item['desc'])) {
				$item['descr'] = $item['desc'];
				unset($item['desc']);
			}
		}
		config_set_path("installedpackages/acme/{$a_array}/item", $acme_item_config);
	}
}

function acme_convert_letsencryptv1_to_letsencryptv2() {
	$acme_item_config = config_get_path('installedpackages/acme/accountkeys/item', []);
	foreach ($acme_item_config as & $item) {
		if ($item['acmeserver'] == 'letsencrypt-staging') {
			$item['acmeserver'] = 'letsencrypt-staging-2';
		} elseif ($item['acmeserver'] == 'letsencrypt-production') {
			$item['acmeserver'] = 'letsencrypt-production-2';
		}
	}
	config_set_path('installedpackages/acme/accountkeys/item', $acme_item_config);
}

function get_itembyname($a_array, $name) {
	$i = 0;
	if (is_array($a_array)) {
		foreach ($a_array as $item) {
			if ($item['name'] == $name) {
				return $i;
			}
			$i++;
		}
	}
	return null;
}

function get_accountkey_id($name) {
	return get_itembyname(config_get_path('installedpackages/acme/accountkeys/item', []), $name);
}
function get_accountkey($name) {
	$id = get_accountkey_id($name);
	return config_get_path("installedpackages/acme/accountkeys/item/{$id}");
}

function get_certificate_id($name) {
	$a_certificates = config_get_path('installedpackages/acme/certificates/item');
	$i = 0;
	if (is_array($a_certificates)) {
		foreach ($a_certificates as $certificate) {
			if ($certificate['name'] == $name) {
				return $i;
			}
			$i++;
		}
	}
	return null;
}

function get_certificate($name) {
	$id = get_certificate_id($name);
	if (is_numeric($id)) {
		return config_get_path("installedpackages/acme/certificates/item/{$id}");
	}
	return null;
}

	function generateAccountKey($name, $ca, $email = "") {
		$acmesh = new acme_sh($name, $ca, $email);
		return $acmesh->generateAccountKey();
	}

	function generateDomainKey($name, $ca, $domain, $keylength, $email = "") {
		$acmesh = new acme_sh($name, $ca, $email);
		return $acmesh->generateDomainKey($domain, $keylength);
	}

	function registerAcmeAccountKey($name, $ca, $key, $email = "") {
		$acmesh = new acme_sh($name, $ca, $email);
		return $acmesh->registeraccount($key);
	}

	function renew_all_certificates($force = false) {
		$a_certificates = config_get_path('installedpackages/acme/certificates/item', []);
		$errors = "";
		foreach($a_certificates as $certificate) {
			if ($certificate['status'] != 'active') {
				echo "Certificate renewal certificate {$certificate['name']} is set to: disabled\n";
				continue;
			}
			echo "Checking if renewal is needed for: {$certificate['name']}\n";
			$res = issue_certificate($certificate['name'], $force);
			if (!$res) {
				$errmsg = localize_text("Failed to renew certificate for %s", $certificate['name']);
				logger(LOG_WARNING, $errmsg, LOG_PREFIX_PKG_ACME);
				$errors .= LOG_PREFIX_PKG_ACME . " {$errmsg}\n";
			}
		}
		if (!empty($errors)) {
			notify_all_remote($errors);
		}
	}

	function getCertificatePSK($ca, $certificate, $domain) {
		$certificatename = $certificate['name'];
		$cert = lookup_cert_by_name($certificatename);
		$cert = $cert['item'];
		if (!is_array($cert)) {
			$cert = array();
			$cert['refid'] = uniqid();
			$cert['descr'] = $certificatename;
			// If keylength is set to 'custom', just use the user-pasted key
			// instead of generating a new one
			if ($certificate['keylength'] != 'custom') {
				echo "\n getCertificatePSK creating new key";
				$privatekey = generateDomainKey($certificatename, $ca, $domain, $certificate['keylength']);
				$cert['prv'] = base64_encode($privatekey);
			} else {
				echo "\n getCertificatePSK using custom key";
				$cert['prv'] = $certificate['keypaste']; // (already base64-encoded)
			}
			config_set_path('cert/', $cert);
			echo "\n{$cert['prv']}";
			$desc = "Acme: Add new certificate & key.";
			write_config($desc);
		} elseif (($certificate['keylength'] == 'custom') && ($cert['prv'] != $certificate['keypaste'])) {
			echo "\n getCertificatePSK updating custom key";
			$cert['prv'] = $certificate['keypaste']; // (already base64-encoded)
		} elseif ($certificate['keylength'] != 'custom') {
			/* Default value to ensure it gets updated if missing/invalid */
			$keybits = -1;
			/* Only attempt to fetch the private key if one is present. */
			if (!empty($cert['prv'])) {
				$res_key = @openssl_pkey_get_private(base64_decode($cert['prv']));
			}
			/* Only check the key details if the private key could be fetched */
			if ($res_key) {
				$key_details = @openssl_pkey_get_details($res_key);
				if (is_array($key_details)) {
					if ($key_details['type'] == OPENSSL_KEYTYPE_RSA) {
						$keybits = $key_details['bits'];
					} else {
						$keybits = $key_details['ec']['curve_name'];
					}
				}
			}
			if (is_numeric($certificate['keylength'])) {
				$newkeybits = $certificate['keylength'];
			} else {
				if ($certificate['keylength'] == 'ec-256') {
					$newkeybits = 'prime256v1';
				} else {
					$newkeybits = 'secp384r1';
				}
			}
			if ($keybits != $newkeybits) {
				echo "\n getCertificatePSK updating key";
				$privatekey = generateDomainKey($certificatename, $ca, $domain, $certificate['keylength']);
				$cert['prv'] = base64_encode($privatekey);
			}
		}

		return base64_decode($cert['prv']);
	}

	function saveCACertificateToStore($crt) {
		$crt_enc = base64_encode($crt);
		foreach(config_get_path('ca', []) as $ca) {
			if ($ca['crt'] == $crt_enc) {
				return;
			}
		}
		$subject = cert_get_subject($crt, false);
		$cert = array();
		$cert['refid'] = uniqid();
		$cert['descr'] = "Acmecert: {$subject}";
		ca_import($cert, $crt);
		config_set_path('ca/', $cert);
		logger(LOG_NOTICE, localize_text("storing new CA: %s", $subject), LOG_PREFIX_PKG_ACME);
	}

	function storeCertificateCer($certificatename, $keyfile, $cerfile, $fullchainfile) {
		$certupdated = false;

		$key = file_get_contents($keyfile);
		$crt = file_get_contents($cerfile);
		$fullchain = "";
		if(!empty($fullchainfile)) {
			$fullchain = file_get_contents($fullchainfile);
			preg_match_all("/-+BEGIN CERTIFICATE(.+?)END CERTIFICATE-+/s", $fullchain, $certificatematches);
			$first = true;
			foreach($certificatematches[0] as $cacert) {
				if ($first == true) {
					$first = false;
					continue;
				}
				saveCACertificateToStore($cacert);
			}
		}
		foreach (config_get_path('cert', []) as $idx => $cert) {
			if ($cert['descr'] == $certificatename) {
				logger(LOG_NOTICE, localize_text("storing new certificate: %s", $certificatename), LOG_PREFIX_PKG_ACME);
				echo "\nupdate cert!";
				cert_import($cert, $crt, $key);
				config_set_path("cert/{$idx}", $cert);
				$certupdated = true;
				break;
			}
		}

		return $certupdated;
	}

	function issue_certificate($id, $force = false, $renew = false) {
		$result = true;
		$certificate = get_certificate($id);
		if (!$force) {
			if ($certificate['status'] != 'active') {
				echo "Certificate renewal for this certificate is set to: disabled\n";
				return $result;
			}

			$renewafterdays = is_numericint($certificate['renewafter']) ? $certificate['renewafter'] : 60;
			$timetorenew = false;
			$now = new \DateTime();
			$lastrenewal = new \DateTime();
			$lastrenewal->setTimestamp($certificate['lastrenewal']);
			$nextrenewal = $lastrenewal->add(new \DateInterval('P'.$renewafterdays.'D'));
			if ($now >= $nextrenewal) {
				echo "## Its time to renew ##\n";
				$timetorenew = true;
			} else {
				echo "Renewal number of days not yet reached.\n";
			}
		}

		if ($timetorenew || $force) {
			logger(LOG_NOTICE, localize_text("renewing certificate: %s", $id), LOG_PREFIX_PKG_ACME);
			echo "Renewing certificate \n";
			$domainstosign = array();
			if (is_array($certificate) &&
			    is_array($certificate['a_domainlist']) &&
			    is_array($certificate['a_domainlist']['item'])) {
				foreach($certificate['a_domainlist']['item'] as $domain) {
					if ($domain['status'] == 'disable') {
						continue;
					}
					$method = $domain['method'];
					$newdomain = new acme_sh_domain($domain['name'], $method);
					if ($method == "dns_nsupdate") {
						$newdomain->setnsupdateparameters(
								$domain["{$method}nsupdate_server"],
								$domain["{$method}nsupdate_keyname"],
								$domain["{$method}nsupdate_keyalgo"],
								$domain["{$method}nsupdate_key"],
								$domain["{$method}nsupdate_zone"]
						);
					}
					if (substr($method, 0, 4) == "dns_") {
						$newdomain->setchallengealias($domain["anydns"."challengealias"]);
						$newdomain->setchallengedomain($domain["anydns"."challengedomain"]);
					}
					$domainstosign[] = $newdomain;
					$envvariables = array();
					if ($method == "dns_namecheap") {
						$defgw = route_get_default('inet');
						$envvariables['NAMECHEAP_SOURCEIP'] = find_interface_ip(guess_interface_from_ip($defgw));
					}
					global $acme_domain_validation_method;
					foreach($acme_domain_validation_method[$method]['fields'] as $key => $field) {
						$envvariables[$key] = $domain["{$method}{$field['name']}"];
					}

					/* Password for SelfHost needs encoding.
					 * See https://redmine.pfsense.org/issues/15229 */
					if (($method == "dns_selfhost") &&
					    array_key_exists('SELFHOSTDNS_PASSWORD', $envvariables) &&
					    !empty($envvariables['SELFHOSTDNS_PASSWORD'])) {
						$envvariables['SELFHOSTDNS_PASSWORD'] = urlencode($envvariables['SELFHOSTDNS_PASSWORD']);
					}
				}
			}

			echo "account: {$certificate['acmeaccount']} \n";
			$account = get_accountkey($certificate['acmeaccount']);
			$acmeserver = $account['acmeserver'];
			$accountkey = $account['accountkey'];
			$email = $account['email'];
			echo "server: $acmeserver \n";

			global $a_acmeserver;
			$url = $a_acmeserver[$acmeserver]['url'];
			$certificatepsk = getCertificatePSK($url, $certificate, $domainstosign[0]->domainname);
			$acmesh = new acme_sh($certificate['name'], $url, $email);
			$action = $renew == true ? "renew" : "issue";
			$acmesh->dnssleep = $certificate['dnssleep'];
			$acmesh->challengealias = $certificate['challengealias'];
			$acmesh->preferredchain = $certificate['preferredchain'];
			$result = $acmesh->signCertificate($action, $accountkey, $certificatepsk, $domainstosign, $envvariables);
		}
		return $result;
	}

	function challenge_response_put($certificatename, $domain, $token, $payload) {
		$acmecert = get_certificate($certificatename);
		global $ftpconn;

		echo "\nchallenge_response_put $certificatename, $domain\n";
		foreach($acmecert['a_domainlist']['item'] as $domainitem) {
			if($domainitem['name'] == $domain){
				$domain_info = $domainitem;
				echo "FOUND domainitem";
			}
		}
		if ($domain_info['method'] == 'webroot') {
			echo "webroot\n";
			$directory = $domain_info['webrootfolder'];
			if(!file_exists($directory) && !@mkdir($directory, 0755, true)) {
				throw new \RuntimeException("Couldn't create directory: '{$directory}' to expose challenge for certificate: {$certificatename}.");
			}
			$tokenPath = $directory . "/" . $token;
			file_put_contents($tokenPath, $payload);
			echo "put token at: {$tokenPath}\n";
		}
		if ($domain_info['method'] == 'webrootftp') {
			echo "FTP\n";
			try {
				$ftpconn = new FTPConnection($domain_info['webrootftpftpserver']);
				$ftpconn->login($domain_info['webrootftpusername'], $domain_info['webrootftppassword']);
				$directory = $domain_info['webrootftpfolder'];
				$tokenPath = $directory . "/" . $token;
				$ftpconn->mkdir($directory);
				$ftpconn->uploadData($payload, $tokenPath);
			} catch (\Exception $e) {
				echo 'FTP Attempt Failed: ',  $e->getMessage(), "\n";
			}
		}
	}

	function challenge_response_cleanup($certificatename, $domain, $token) {
		global $ftpconn;
		$acmecert = get_certificate($certificatename);
		foreach($acmecert['a_domainlist']['item'] as $domainitem) {
			if($domainitem['name'] == $domain){
				$domain_info = $domainitem;
			}
		}
		if ($domain_info['method'] == 'webroot') {
			$tokenfile = $domain_info['webrootfolder'] . "/" . $token;
			@unlink($tokenfile);
		}
		if ($domain_info['method'] == 'webrootftp') {
			$tokenfile = $domain_info['webrootftpfolder'] . "/" . $token;
			if ($ftpconn) {
				try {
					$ftpconn->deleteFile($tokenfile);
				} catch (\Exception $e) {
					echo 'FTP Attempt Failed: ',  $e->getMessage(), "\n";
				}
			}
		}
	}
}
