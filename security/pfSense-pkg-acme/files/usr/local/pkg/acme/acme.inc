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
		global $config;
		$prefix = '/conf/acme';
		safe_mkdir($prefix);

		/* Lookup the certificate and CA */
		$cert = lookup_cert($certref);
		if (empty($cert)) {
			return;
		}
		$ca = lookup_ca($cert['caref']);
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
		global $config;
		$a_global = &$config['installedpackages']['acme'];
		if (!isset($a_global['writecerts'])) {
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
		global $config;
		$result = array();
		if ($pluginparams['type'] == 'certificates' && $pluginparams['event'] == 'used_certificates') {
			/* If there are no certificates, there is nothing to process. */
			if (!isset($config['installedpackages']['acme']['certificates']['item']) ||
			    !is_array($config['installedpackages']['acme']['certificates']['item'])) {
				return array();
			}
			$result['pkgname'] = "Acme";
			$result['certificatelist'] = array();
			foreach ($config['installedpackages']['acme']['certificates']['item'] as $certificate) {
				$certificatename = $certificate["name"];
				$cert = lookup_cert_by_name($certificatename);
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

$d_acmeconfdirty_path = $g['varrun_path'] . "/acme.conf.dirty";
#region global array item definitions..
// <editor-fold desc="global array item definitions">

$a_enabledisable = array();
$a_enabledisable['enable'] = array('name' => 'Enabled');
$a_enabledisable['disable'] = array('name' => 'Disabled');

global $a_acmeserver;
$a_acmeserver = array();
$a_acmeserver['letsencrypt-staging-2'] = array('name' => "Let's Encrypt Staging ACME v2 (For TESTING purposes)",
    'url' => 'https://acme-staging-v02.api.letsencrypt.org/directory'
);
$a_acmeserver['letsencrypt-production-2'] = array('name' => "Let's Encrypt Production ACME v2 (Applies rate limits to certificate requests)",
    'url' => 'https://acme-v02.api.letsencrypt.org/directory'
);
$a_acmeserver['buypass-staging'] = array('name' => "BuyPass Staging (For TESTING purposes)",
    'url' => 'https://api.test4.buypass.no/acme/directory'
);
$a_acmeserver['buypass-production'] = array('name' => "BuyPass Production ACME v1 (Applies rate limits to certificate requests)",
    'url' => 'https://api.buypass.com/acme/directory'
);
$a_acmeserver['zerossl-production'] = array('name' => "ZeroSSL Production (Applies rate limits to certificate requests)",
    'url' => 'https://acme.zerossl.com/v2/DV90'
);
$a_acmeserver['sslcom-rsa-production'] = array('name' => "SSL.com RSA Production (Applies rate limits to certificate requests)",
    'url' => 'https://acme.ssl.com/sslcom-dv-rsa'
);
$a_acmeserver['sslcom-ecc-production'] = array('name' => "SSL.com ECC Production (Applies rate limits to certificate requests)",
    'url' => 'https://acme.ssl.com/sslcom-dv-ecc'
);
/*$a_acmeserver['dummy'] = array('name' => "dummy",
    'url' => 'https://example.org'
);*/

global $acme_domain_validation_method;
$acme_domain_validation_method = array();
$acme_domain_validation_method['webroot'] = array('name' => "Webroot local folder",
	'fields' => array(
		'folder' => array('name' => "folder", 'columnheader' => "Root Folder", 'type' => "textbox", 'size' => "50",
			'description' => "Folder into which the acme challenge response is written; for example: /usr/local/www/.well-known/acme-challenge/"
		)
	));
$acme_domain_validation_method['webrootftp'] = array('name' => "Webroot FTP",
	'fields' => array(
		'ftpserver' => array('name' => "ftpserver", 'columnheader' => "Server", 'type' => "textbox", 'size' => "50",
			'description' => "Hostname of FTP server to which ACME will connect (e.g. ftps://www.webserver.tld ). "
			. "Currently supports ftps (passive) and sftp."
		),
		'username' => array('name' => "username", 'columnheader' => "Username", 'type' => "textbox", 'size' => "50",
			'description' => "Username for the remote server"
		),
		'password' => array('name' => "password", 'columnheader' => "Password", 'type' => "password", 'size' => "50",
			'description' => "Password to authenticate this user on the remote server"
		),
		'folder' => array('name' => "folder", 'columnheader' => "Folder", 'type' => "textbox", 'size' => "50",
			'description' => "Folder into which the acme challenge response is written (e.g. /.well-known/acme-challenge/)"
		)
	));
$acme_domain_validation_method['standalone'] = array('name' => "Standalone HTTP server",
	'fields' => array(
		'port' => array('name' => "port", 'columnheader' => "Port", 'type' => "textbox", 'size' => "50",
			'description' => "HTTP listen port for stand-alone server. " .
					"Must be 80 or have port 80 on WAN forwarded to this port. " .
					"Firewall rules must allow traffic to reach this port."
		),
		'ipv6' => array('name' => "ipv6", 'columnheader' => "IPv6", 'type' => "checkbox",
			'description' => "Bind to IPv6 instead of IPv4."
		)
	));
$acme_domain_validation_method['standalonetls'] = array('name' => "Standalone TLS-ALPN server",
	'fields' => array(
		'port' => array('name' => "port", 'columnheader' => "Port", 'type' => "textbox", 'size' => "50",
			'description' => "TLS listen port for stand-alone server. " .
					"Must be 443 or have port 443 on WAN forwarded to this port. " .
					"Firewall rules must allow traffic to reach this port."
		),
	));
$acme_domain_validation_method['dns_manual'] = array('name' => "DNS-Manual",
	'fields' => array(
	));
$acme_domain_validation_method['dns_nsupdate'] = array('name' => "DNS-NSupdate / RFC 2136",
	'fields' => array(
		'NSUPDATE_SERVER' => array('name' => "nsupdate_server", 'columnheader' => "Server", 'type' => "textbox",
			'description' => "The DNS server to which updates are sent (IP address or hostname)"
		),
		'NSUPDATE_KEYNAME' => array('name' => "nsupdate_keyname", 'columnheader' => "Key Name", 'type' => "textbox",
			'description' => "(Optional) A name for the key, if it is different than _acme-challenge.[Domainname]"
		),
		'NSUPDATE_KEYALGO' => array('name' => 'nsupdate_keyalgo', 'columnheader' => 'Key Algorithm', 'type' => 'select',
			'items' => array(
				'' => array('name' => 'None'),
				'157' => array('name' => 'HMAC-MD5'),
				'165' => array('name' => 'HMAC-SHA512'),
				'164' => array('name' => 'HMAC-SHA384'),
				'163' => array('name' => 'HMAC-SHA256'),
				'162' => array('name' => 'HMAC-SHA224'),
				'161' => array('name' => 'HMAC-SHA1'),
			),
			'description' => 'Algorithm used to generate the authentication Key for this zone (typically "HMAC-MD5")'
		),
		'NSUPDATE_KEY' => array('name' => "nsupdate_key", 'columnheader' => "Key", 'type' => "textarea",
			'description' => "The key used to authenticate updates for this zone"
		),
		'NSUPDATE_ZONE' => array('name' => "nsupdate_zone", 'columnheader' => "Zone", 'type' => "textbox",
			'description' => "(Optional) Explicitly set the zone name for updates."
		)
	));
$acme_domain_validation_method['dns_1984hosting'] = array('name' => "DNS-1984Hosting",
	'fields' => array(
		'One984HOSTING_Username' => array('name' => "one984hosting_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "1984Hosting Username"
		),
		'One984HOSTING_Password' => array('name' => "one984hosting_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "1984Hosting Password"
		),
	));
$acme_domain_validation_method['dns_acmeproxy'] = array('name' => "DNS-Acmeproxy",
	'fields' => array(
		'ACMEPROXY_ENDPOINT' => array('name' => "acmeproxy_endpoint", 'columnheader' => "Endpoint URL", 'type' => "textbox",
			'description' => "Acmeproxy Endpoint URL (https://ip:port)"
		),
		'ACMEPROXY_USERNAME' => array('name' => "acmeproxy_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Acmeproxy Username"
		),
		'ACMEPROXY_PASSWORD' => array('name' => "acmeproxy_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Acmeproxy Password"
		),
	));
$acme_domain_validation_method['dns_acmedns'] = array('name' => "DNS-acme-dns.io",
	'fields' => array(
		'ACMEDNS_USERNAME' => array('name' => "acmedns_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "acme-dns.io Username"
		),
		'ACMEDNS_PASSWORD' => array('name' => "acmedns_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "acme-dns.io Password"
		),
		'ACMEDNS_SUBDOMAIN' => array('name' => "acmedns_subdomain", 'columnheader' => "Subdomain", 'type' => "textbox",
			'description' => "acme-dns.io subdomain"
		),
		'ACMEDNS_BASE_URL' => array('name' => "acmedns_update_url", 'columnheader' => "Base URL", 'type' => "textbox",
			'description' => "(optional) Custom ACME DNS Base URL"
		),
	));
$acme_domain_validation_method['dns_active24'] = array('name' => "DNS-Active24",
	'fields' => array(
		'ACTIVE24_Token' => array('name' => "active24_token", 'columnheader' => "Token", 'type' => "textbox",
			'description' => "Active24 Token"
		),
	));
$acme_domain_validation_method['dns_edgedns'] = array('name' => "DNS-Akamai Edge DNS v2",
	'fields' => array(
		'AKAMAI_HOST' => array('name' => "akamai_host", 'columnheader' => "Host", 'type' => "textbox",
			'description' => "Hostname"
		),
		'AKAMAI_ACCESS_TOKEN' => array('name' => "akamai_access_token", 'columnheader' => "Access Token", 'type' => "textbox",
			'description' => "Access Token"
		),
		'AKAMAI_CLIENT_TOKEN' => array('name' => "akamai_client_token", 'columnheader' => "Client Token", 'type' => "textbox",
			'description' => "Client Token"
		),
		'AKAMAI_CLIENT_SECRET' => array('name' => "akamai_client_secret", 'columnheader' => "Client Secret", 'type' => "password",
			'description' => "Client Secret"
		),
	));
$acme_domain_validation_method['dns_ali'] = array('name' => "DNS-Aliyuncs",
	'fields' => array(
		'Ali_Key' => array('name' => "ali_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "API Key"
		),
		'Ali_Secret' => array('name' => "ali_secret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "API Secret"
		)
	));
$acme_domain_validation_method['dns_kas'] = array('name' => "DNS-All-inkl Kasserver",
	'fields' => array(
		'KAS_Login' => array('name' => "kas_login", 'columnheader' => "Login", 'type' => "textbox",
			'description' => "Login"
		),
		'KAS_Authtype' => array('name' => "kas_authtype", 'columnheader' => "Auth Type", 'type' => "textbox",
			'description' => "Auth type (default: sha1)"
		),
		'KAS_Authdata' => array('name' => "kas_authdata", 'columnheader' => "Auth Data", 'type' => "password",
			'description' => "Auth data"
		)
	));
$acme_domain_validation_method['dns_ad'] = array('name' => "DNS-Alwaysdata",
	'fields' => array(
		'AD_API_KEY' => array('name' => "ad_api_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "Alwaysdata API Key"
		)
	));
$acme_domain_validation_method['dns_anx'] = array('name' => "DNS-Anexia CloudDNS",
	'fields' => array(
		'ANX_Token' => array('name' => "anx_token", 'columnheader' => "API Token", 'type' => "textbox",
			'description' => "API Token"
		)
	));
$acme_domain_validation_method['dns_arvan'] = array('name' => "DNS-Arvan",
	'fields' => array(
		'Arvan_Token' => array('name' => "arvan_token", 'columnheader' => "API Token", 'type' => "textbox",
			'description' => "Arvan API Token"
		)
	));
$acme_domain_validation_method['dns_aurora'] = array('name' => "DNS-auroradns.eu",
	'fields' => array(
		'AURORA_Key' => array('name' => "aurora_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "API Key"
		),
		'AURORA_Secret' => array('name' => "aurora_secret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "API Secret. Obtain the key and secret from https://cp.pcextreme.nl/auroradns/users."
		),
	));
$acme_domain_validation_method['dns_autodns'] = array('name' => "DNS-autoDNS from InternetX",
	'fields' => array(
		'AUTODNS_USER' => array('name' => "autodns_user", 'columnheader' => "User", 'type' => "textbox",
			'description' => "autoDNS Username"
		),
		'AUTODNS_PASSWORD' => array('name' => "autodns_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "autoDNS Password"
		),
		'AUTODNS_CONTEXT' => array('name' => "autodns_context", 'columnheader' => "Context", 'type' => "textbox",
			'description' => "autoDNS Context"
		)
	));
$acme_domain_validation_method['dns_aws'] = array('name' => "DNS-Amazon Route53",
	'fields' => array(
		'AWS_ACCESS_KEY_ID' => array('name' => "aws_access_key_id", 'columnheader' => "API ID", 'type' => "textbox",
			'description' => "AWS Access Key / API ID"
		),
		'AWS_SECRET_ACCESS_KEY' => array('name' => "aws_secret_access_key", 'columnheader' => "API Key", 'type' => "password",
			'description' => "AWS Secret Access / API Key"
		),
		'AWS_DNS_SLOWRATE' => array('name' => "aws_dns_slowrate", 'columnheader' => "Slow Rate", 'type' => "textbox",
			'description' => "Sleep interval after TXT record update, in seconds (default: 1)"
		),
	));
$acme_domain_validation_method['dns_azion'] = array('name' => "DNS-azionapi.net",
	'fields' => array(
		'AZION_Email' => array('name' => "azion_email", 'columnheader' => "E-mail", 'type' => "textbox",
			'description' => "Account e-mail address"
		),
		'AZION_Password' => array('name' => "azion_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Account password"
		),
	));
$acme_domain_validation_method['dns_azure'] = array('name' => "DNS-Azure (Microsoft)",
	'fields' => array(
		'AZUREDNS_SUBSCRIPTIONID' => array('name' => "azuredns_subscriptionid", 'columnheader' => "Subscription ID", 'type' => "textbox",
			'description' => "Azure Subscription ID. First, <a href=\"https://github.com/acmesh-official/acme.sh/wiki/How-to-use-Azure-DNS\">setup a service principal for access to the DNS Zone</a>."
		),
		'AZUREDNS_TENANTID' => array('name' => "azuredns_tenantid", 'columnheader' => "Tenant ID", 'type' => "textbox",
			'description' => "Azure Tenant ID"
		),
		'AZUREDNS_APPID' => array('name' => "azuredns_appid", 'columnheader' => "App ID", 'type' => "textbox",
			'description' => "Azure App ID"
		),
		'AZUREDNS_CLIENTSECRET' => array('name' => "azuredns_clientsecret", 'columnheader' => "Client Secret", 'type' => "password",
			'description' => "Azure Client Secret"
		)
	));
$acme_domain_validation_method['dns_clouddns'] = array('name' => "DNS-CloudDNS",
	'fields' => array(
		'CLOUDDNS_EMAIL' => array('name' => "clouddns_email", 'columnheader' => "E-mail", 'type' => "textbox",
			'description' => "CloudDNS e-mail address"
		),
		'CLOUDDNS_CLIENT_ID' => array('name' => "clouddns_client_id", 'columnheader' => "Client ID", 'type' => "textbox",
			'description' => "CloudDNS client ID"
		),
		'CLOUDDNS_PASSWORD' => array('name' => "clouddns_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "CloudDNS Password"
		)
	));
$acme_domain_validation_method['dns_cloudns'] = array('name' => "DNS-ClouDNS",
	'fields' => array(
		'CLOUDNS_AUTH_ID' => array('name' => "cloudns_auth_id", 'columnheader' => "Auth ID", 'type' => "textbox",
			'description' => "Authentication ID"
		),
		'CLOUDNS_SUB_AUTH_ID' => array('name' => "cloudns_sub_auth_id", 'columnheader' => "Sub Auth ID", 'type' => "textbox",
			'description' => "Sub authentication ID"
		),
		'CLOUDNS_AUTH_PASSWORD' => array('name' => "cloudns_auth_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "ClouDNS Password"
		)
	));
$acme_domain_validation_method['dns_cf'] = array('name' => "DNS-Cloudflare",
	'fields' => array(
		'CF_Key' => array('name' => "cf_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "Cloudflare API Key"
		),
		'CF_Email' => array('name' => "cf_email", 'columnheader' => "Email", 'type' => "textbox",
			'description' => "Cloudflare API Email Address"
		),
		'CF_Token' => array('name' => "cf_token", 'columnheader' => "Token", 'type' => "textbox",
			'description' => "Cloudflare API Token"
		),
		'CF_Account_ID' => array('name' => "cf_account_id", 'columnheader' => "Account ID", 'type' => "textbox",
			'description' => "Cloudflare API Account ID"
		),
		'CF_Zone_ID' => array('name' => "cf_zone_id", 'columnheader' => "Zone ID", 'type' => "textbox",
			'description' => "Cloudflare API Zone ID"
		),
	));
$acme_domain_validation_method['dns_cx'] = array('name' => "DNS-Cloudxns",
	'fields' => array(
		'CX_Key' => array('name' => "cx_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "Cloudxns API Key"
		),
		'CX_Secret' => array('name' => "cx_secret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "Cloudxns API Secret"
		)
	));
$acme_domain_validation_method['dns_conoha'] = array('name' => "DNS-Conoha",
	'fields' => array(
		'CONOHA_Username' => array('name' => "conoha_username", 'columnheader' => "User", 'type' => "textbox",
			'description' => "Conoha Username"
		),
		'CONOHA_Password' => array('name' => "conoha_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Conoha Password"
		),
		'CONOHA_TenantId' => array('name' => "conoha_tenantid", 'columnheader' => "Tenant ID", 'type' => "textbox",
			'description' => "Conoha Tenant ID"
		),
		'CONOHA_IdentityServiceApi' => array('name' => "conoha_identityserviceapi", 'columnheader' => "ID Srv API", 'type' => "textbox",
			'description' => "Conoha Identity Service API"
		)
	));
$acme_domain_validation_method['dns_constellix'] = array('name' => "DNS-Constellix",
	'fields' => array(
		'CONSTELLIX_Key' => array('name' => "constellix_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "Constellix Key"
		),
		'CONSTELLIX_Secret' => array('name' => "constellix_secret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "Constellix Secret"
		),
	));
$acme_domain_validation_method['dns_cpanel'] = array('name' => "DNS-cPanel",
	'fields' => array(
		'cPanel_Username' => array('name' => "cpanel_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "cPanel username"
		),
		'cPanel_Apitoken' => array('name' => "cpanel_apitoken", 'columnheader' => "Token", 'type' => "textbox",
			'description' => "cPanel API token"
		),
		'cPanel_Hostname' => array('name' => "cpanel_hostname", 'columnheader' => "URL", 'type' => "textbox",
			'description' => "URL to cPanel instance"
		),
	));
$acme_domain_validation_method['dns_cn'] = array('name' => "DNS-Core-Networks",
	'fields' => array(
		'CN_User' => array('name' => "cn_user", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Core Networks Username"
		),
		'CN_Password' => array('name' => "cn_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Core Networks Password"
		)
	));
$acme_domain_validation_method['dns_curanet'] = array('name' => "DNS-curanet.dk, scannet.dk, wannafind.dk, and dandomain.dk",
	'fields' => array(
		'CURANET_AUTHCLIENTID' => array('name' => "curanet_authclientid", 'columnheader' => "Client ID", 'type' => "textbox",
			'description' => "Authentication Client ID"
		),
		'CURANET_AUTHSECRET' => array('name' => "curanet_authsecret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "Authentication Secret"
		)
	));
$acme_domain_validation_method['dns_cyon'] = array('name' => "DNS-cyon.ch",
	'fields' => array(
		'CY_Username' => array('name' => "cy_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "CY username"
		),
		'CY_Password' => array('name' => "cy_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "CY Password"
		)
	));
$acme_domain_validation_method['dns_ddnss'] = array('name' => "DNS-ddnss.de",
	'fields' => array(
		'DDNSS_Token' => array('name' => "ddnss_token", 'columnheader' => "Token", 'type' => "textbox",
			'description' => "API Token (e.g. aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee)"
		)
	));
$acme_domain_validation_method['dns_desec'] = array('name' => "DNS-deSEC.io",
	'fields' => array(
		'DEDYN_TOKEN' => array('name' => "dedyn_token", 'columnheader' => "Token", 'type' => "textbox",
			'description' => "deSEC.io API Token"
		),
		'DEDYN_NAME' => array('name' => "dedyn_name", 'columnheader' => "Name", 'type' => "textbox",
			'description' => "deSEC.io Username"
		)
	));
$acme_domain_validation_method['dns_dgon'] = array('name' => "DNS-DigitalOcean",
	'fields' => array(
		'DO_API_KEY' => array('name' => "do_api_key", 'columnheader' => "DO API Key", 'type' => "textbox",
			'description' => "DigitalOcean API Key"
		)
	));
$acme_domain_validation_method['dns_da'] = array('name' => "DNS-DirectAdmin",
	'fields' => array(
		'DA_Api' => array('name' => "da_api", 'columnheader' => "DA API URI", 'type' => "textbox",
			'description' => "DirectAdmin API URI (e.g. https://remoteUser:remotePassword@da.example.com:8443)"
		),
		'DA_Api_Insecure' => array('name' => "da_api_insecure", 'columnheader' => "DA API Secure", 'type' => "textbox",
			'description' => "DirectAdmin API Security check, 0=check for valid SSL certificate, 1=always accept",
		)
	));
$acme_domain_validation_method['dns_dnshome'] = array('name' => "DNS-dnsHome.de",
	'fields' => array(
		'DNSHOME_Subdomain' => array('name' => "dnshome_subdomain", 'columnheader' => "Subdomain", 'type' => "textbox",
			'description' => "Subdomain"
		),
		'DNSHOME_SubdomainPassword' => array('name' => "dnshome_subdomainpassword", 'columnheader' => "Password", 'type' => "password",
			'description' => "Subdomain Password"
		)
	));
$acme_domain_validation_method['dns_dnsimple'] = array('name' => "DNS-DNSimple",
	'fields' => array(
		'DNSimple_OAUTH_TOKEN' => array('name' => "dnsimple_oauth_token", 'columnheader' => "OAUTH Token", 'type' => "textbox",
			'description' => "DNSimple oauth token, visit <a href=\"https://dnsimple.com/user\">https://dnsimple.com/user</a> to generate."
		)
	));
$acme_domain_validation_method['dns_me'] = array('name' => "DNS-DNSMadeEasy",
	'fields' => array(
		'ME_Key' => array('name' => "me_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "DNSMadeEasy API Key"
		),
		'ME_Secret' => array('name' => "me_secret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "DNSMadeEasy API Secret"
		)
	));
$acme_domain_validation_method['dns_doapi'] = array('name' => "DNS-do.de",
	'fields' => array(
		'DO_LETOKEN' => array('name' => "do_letoken", 'columnheader' => "API Token", 'type' => "textbox",
			'description' => "DO.de API Token"
		)
	));
$acme_domain_validation_method['dns_do'] = array('name' => "DNS-Domain-Offensive / Resellerinterface / Domainrobot",
	'fields' => array(
		'DO_PID' => array('name' => "do_pid", 'columnheader' => "Customer ID", 'type' => "textbox",
			'description' => "DO Customer ID"
		),
		'DO_PW' => array('name' => "do_pw", 'columnheader' => "Password", 'type' => "password",
			'description' => "DO Password"
		)
	));
$acme_domain_validation_method['dns_domeneshop'] = array('name' => "DNS-domeneshop.no",
	'fields' => array(
		'DOMENESHOP_Token' => array('name' => "domeneshop_token", 'columnheader' => "Token", 'type' => "textbox",
			'description' => "Domeneshop API Token"
		),
		'DOMENESHOP_Secret' => array('name' => "domeneshop_secret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "Domeneshop API Secret"
		),
	));
$acme_domain_validation_method['dns_dp'] = array('name' => "DNS-Dnspod.cn",
	'fields' => array(
		'DP_Id' => array('name' => "dp_id", 'columnheader' => "ID", 'type' => "textbox",
			'description' => "Dnspod API ID"
		),
		'DP_Key' => array('name' => "dp_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "Dnspod API Key"
		)
	));
$acme_domain_validation_method['dns_dpi'] = array('name' => "DNS-Dnspod.com",
	'fields' => array(
		'DPI_Id' => array('name' => "dpi_id", 'columnheader' => "ID", 'type' => "textbox",
			'description' => "Dnspod API ID"
		),
		'DPI_Key' => array('name' => "dpi_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "Dnspod API Key"
		)
	));
$acme_domain_validation_method['dns_dreamhost'] = array('name' => "DNS-DreamHost",
	'fields' => array(
		'DH_API_KEY' => array('name' => "dh_api_key", 'columnheader' => "API Token", 'type' => "textbox",
			'description' => "Dreamhost API Token"
		)
	));
$acme_domain_validation_method['dns_duckdns'] = array('name' => "DNS-DuckDNS",
	'fields' => array(
		'DuckDNS_Token' => array('name' => "duckdns_token", 'columnheader' => "API Token", 'type' => "textbox",
			'description' => "DuckDNS API Token (Look in DuckDNS account settings)"
		)
	));
$acme_domain_validation_method['dns_durabledns'] = array('name' => "DNS-DurableDNS.com",
	'fields' => array(
		'DD_API_User' => array('name' => "dd_api_user", 'columnheader' => "API User", 'type' => "textbox",
			'description' => "DurableDNS API User"
		),
		'DD_API_Key' => array('name' => "dd_api_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "DurableDNS API Key"
		),
	));
$acme_domain_validation_method['dns_dyn'] = array('name' => "DNS-Dyn.com",
	'fields' => array(
		'DYN_Customer' => array('name' => "dyn_customer", 'columnheader' => "Customer", 'type' => "textbox",
			'description' => "dyn.com customer ID"
		),
		'DYN_Username' => array('name' => "dyn_username", 'columnheader' => "API Username", 'type' => "textbox",
			'description' => "dyn.com API Username (Dyn Managed DNS user, Needs Z&R Permissions for RecordAdd, RecordUpdate, RecordDelete, RecordGet, ZoneGet, ZoneAddNode, ZoneRemoveNode, ZonePublish)"
		),
		'DYN_Password' => array('name' => "dyn_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "dyn.com Password"
		)
	));
$acme_domain_validation_method['dns_df'] = array('name' => "DNS-dyndnsfree.de",
	'fields' => array(
		'DF_user' => array('name' => "df_user", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "dyndnsfree.de Username"
		),
		'DF_password' => array('name' => "df_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "dyndnsfree.de Password"
		)
	));
$acme_domain_validation_method['dns_dynu'] = array('name' => "DNS-Dynu",
	'fields' => array(
		'Dynu_ClientId' => array('name' => "dynu_clientid", 'columnheader' => "API Client ID", 'type' => "textbox",
			'description' => "Dynu API Client ID created in the Dynu account settings"
		),
		'Dynu_Secret' => array('name' => "dynu_secret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "Dynu API Secret"
		)
	));
$acme_domain_validation_method['dns_easydns'] = array('name' => "DNS-easyDNS",
	'fields' => array(
		'EASYDNS_Key' => array('name' => "easydns_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "easyDNS API Key. Sign up for a key at http://docs.sandbox.rest.easydns.net/beta_signup.php"
		),
		'EASYDNS_Token' => array('name' => "easydns_token", 'columnheader' => "Token", 'type' => "textbox",
			'description' => "easyDNS API Token"
		)
	));
$acme_domain_validation_method['dns_euserv'] = array('name' => "DNS-Euserv.eu",
	'fields' => array(
		'EUSERV_Username' => array('name' => "euserv_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Euserv.eu Username"
		),
		'EUSERV_Password' => array('name' => "euserv_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Euserv.eu Password"
		)
	));
$acme_domain_validation_method['dns_exoscale'] = array('name' => "DNS-Exoscale",
	'fields' => array(
		'EXOSCALE_API_KEY' => array('name' => "exoscale_api_key", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Exoscale API Key"
		),
		'EXOSCALE_SECRET_KEY' => array('name' => "exoscale_secret_key", 'columnheader' => "Password", 'type' => "password",
			'description' => "Exoscale Secret Key"
		)
	));
$acme_domain_validation_method['dns_freedns'] = array('name' => "DNS-FreeDNS",
	'fields' => array(
		'FREEDNS_User' => array('name' => "freedns_user", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "FreeDNS username"
		),
		'FREEDNS_Password' => array('name' => "freedns_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "FreeDNS Password"
		)
	));
@$acme_domain_validation_method['dns_gandi_livedns'] = array('name' => "DNS-Gandi LiveDNS",
	'fields' => array(
		'GANDI_LIVEDNS_KEY' => array('name' => "gandi_livedns_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "Gandi LiveDNS API Key, retrieved from <a href=\"https://account.gandi.net\">https://account.gandi.net</a>"
		)
	));
$acme_domain_validation_method['dns_geoscaling'] = array('name' => "DNS-Geoscaling",
	'fields' => array(
		'GEOSCALING_Username' => array('name' => "geoscaling_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Username"
		),
		'GEOSCALING_Password' => array('name' => "geoscaling_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Password"
		)
	));
$acme_domain_validation_method['dns_gd'] = array('name' => "DNS-GoDaddy",
	'fields' => array(
		'GD_Key' => array('name' => "gd_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "GoDaddy API Key"
		),
		'GD_Secret' => array('name' => "gd_secret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "GoDaddy API Secret"
		)
	));
$acme_domain_validation_method['dns_gdnsdk'] = array('name' => "DNS-GratisDNS",
	'fields' => array(
		'GDNSDK_Username' => array('name' => "gdnsdk_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "GratisDNS Username"
		),
		'GDNSDK_Password' => array('name' => "gdnsdk_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "GratisDNS Password"
		)
	));
$acme_domain_validation_method['dns_hetzner'] = array('name' => "DNS-Hetzner",
	'fields' => array(
		'HETZNER_Token' => array('name' => "hetzner_token", 'columnheader' => "API Token", 'type' => "password",
			'description' => "Hetzner API Token. Visit https://dns.hetzner.com/settings/api-token to retrieve."
		)
	));
$acme_domain_validation_method['dns_hexonet'] = array('name' => "DNS-Hexonet",
	'fields' => array(
		'Hexonet_Login' => array('name' => "hexonet_login", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Hexonet Username"
		),
		'Hexonet_Password' => array('name' => "hexonet_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Hexonet Password"
		)
	));
$acme_domain_validation_method['dns_huaweicloud'] = array('name' => "DNS-Huawei Cloud",
	'fields' => array(
		'HUAWEICLOUD_Username' => array('name' => "huaweicloud_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Username"
		),
		'HUAWEICLOUD_Password' => array('name' => "huaweicloud_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Password"
		),
		'HUAWEICLOUD_ProjectID' => array('name' => "huaweicloud_projectid", 'columnheader' => "Project ID", 'type' => "textbox",
			'description' => "Project ID"
		),
	));
$acme_domain_validation_method['dns_he'] = array('name' => "DNS-Hurricane Electric",
	'fields' => array(
		'HE_Username' => array('name' => "he_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Hurricane Electric username"
		),
		'HE_Password' => array('name' => "he_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Hurricane Electric password"
		)
	));
$acme_domain_validation_method['dns_hostingde'] = array('name' => "DNS-hosting.de",
	'fields' => array(
		'HOSTINGDE_APIKEY' => array('name' => "hostingde_apikey", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "Hosting.de API Key"
		),
		'HOSTINGDE_ENDPOINT' => array('name' => "hostingde_endpoint", 'columnheader' => "Endpoint", 'type' => "textbox",
			'description' => "Hosting.de API endpoint, e.g. https://secure.hosting.de"
		)
	));
$acme_domain_validation_method['dns_infoblox'] = array('name' => "DNS-Infoblox",
	'fields' => array(
		'Infoblox_Creds' => array('name' => "infoblox_creds", 'columnheader' => "Credentials", 'type' => "textbox",
			'description' => "Infoblox credentials in <strong>username:password</strong> format"
		),
		'Infoblox_Server' => array('name' => "infoblox_server", 'columnheader' => "Server", 'type' => "textbox",
			'description' => "Infoblox server IP address or hostname"
		),
		'Infoblox_View' => array('name' => "infoblox_view", 'columnheader' => "View", 'type' => "textbox",
			'description' => "Infoblox DNS View name, or enter 'default'"
		)
	));
$acme_domain_validation_method['dns_infomaniak'] = array('name' => "DNS-Infomaniak",
	'fields' => array(
		'INFOMANIAK_API_TOKEN' => array('name' => "infomaniak_api_token", 'columnheader' => "API Token", 'type' => "password",
			'description' => "Infomaniak API token. Visit https://manager.infomaniak.com/v3/&lt;account_id&gt;/api/dashboard and generate a token with the scope Domain."
		),
		'DEFAULT_INFOMANIAK_API_URL' => array('name' => "default_infomaniak_api_url", 'columnheader' => "API URL", 'type' => "textbox",
			'description' => "Infomaniak API URL (Default: https://api.infomaniak.com)"
		),
		'INFOMANIAK_TTL' => array('name' => "infomaniak_ttl", 'columnheader' => "TTL", 'type' => "textbox",
			'description' => "Infomaniak DNS record TTL (Default: 300)"
		)
	));
$acme_domain_validation_method['dns_ionos'] = array('name' => "DNS-IONOS",
	'fields' => array(
		'IONOS_PREFIX' => array('name' => "ionos_prefix", 'columnheader' => "Prefix", 'type' => "textbox",
			'description' => "Prefix"
		),
		'IONOS_SECRET' => array('name' => "ionos_secret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "Secret. Read https://beta.developer.hosting.ionos.de/docs/getstarted to learn how to get a prefix and secret."
		)
	));
$acme_domain_validation_method['dns_internetbs'] = array('name' => "DNS-Internet.BS",
	'fields' => array(
		'INTERNETBS_API_KEY' => array('name' => "internetbs_api_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "Internet.BS API Key"
		),
		'INTERNETBS_API_PASSWORD' => array('name' => "internetbs_api_password", 'columnheader' => "API Password", 'type' => "password",
			'description' => "Internet.BS API Password"
		)
	));
$acme_domain_validation_method['dns_inwx'] = array('name' => "DNS-INWX.de",
	'fields' => array(
		'INWX_User' => array('name' => "inwx_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "INWX.de username"
		),
		'INWX_Password' => array('name' => "inwx_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "INWX.de password"
		),
		'INWX_Shared_Secret' => array('name' => "inwx_shared_secret", 'columnheader' => "Shared Secret", 'type' => "password",
			'description' => "INWX.de shared secret"
		),
	));
$acme_domain_validation_method['dns_ispconfig'] = array('name' => "DNS-ISPConfig",
	'fields' => array(
		'ISPC_User' => array('name' => "ispc_user", 'columnheader' => "User", 'type' => "textbox",
			'description' => "ISPConfig remoteUser"
		),
		'ISPC_Password' => array('name' => "ispc_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "ISPConfig remotePassword"
		),
		'ISPC_Api' => array('name' => "ispc_api", 'columnheader' => "Api", 'type' => "textbox",
			'description' => "API URL (e.g. https://ispc.domain.tld:8080/remote/json.php )"
		),
		'ISPC_Api_Insecure' => array('name' => "ispc_api_insecure", 'columnheader' => "Allow Insecure", 'type' => "textbox",
			'description' => "Set 1 for insecure and 0 for secure. " .
					"Controls whether the server TLS certificate is checked for validity (0) or always accepted (1)"
		)
	));
$acme_domain_validation_method['dns_jd'] = array('name' => "DNS-jdcloud.com",
	'fields' => array(
		'JD_ACCESS_KEY_ID' => array('name' => "jd_access_key_id", 'columnheader' => "Key ID", 'type' => "textbox",
			'description' => "jdcloud Access Key ID"
		),
		'JD_ACCESS_KEY_SECRET' => array('name' => "jd_access_key_secret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "jdcloud Access Key Secret"
		),
		'JD_REGION' => array('name' => "jd_region", 'columnheader' => "Region", 'type' => "textbox",
			'description' => "jdcloud Region"
		),
	));
$acme_domain_validation_method['dns_joker'] = array('name' => "DNS-Joker.com",
	'fields' => array(
		'JOKER_USERNAME' => array('name' => "joker_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Joker.com Username"
		),
		'JOKER_PASSWORD' => array('name' => "joker_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Joker.com Password"
		)
	));
$acme_domain_validation_method['dns_kappernet'] = array('name' => "DNS-kapper.net",
	'fields' => array(
		'KAPPERNETDNS_Key' => array('name' => "kappernetdns_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "kapper.net API Key"
		),
		'KAPPERNETDNS_Secret' => array('name' => "kappernetdns_secret", 'columnheader' => "API Secret", 'type' => "password",
			'description' => "kapper.net API Secret"
		)
	));
$acme_domain_validation_method['dns_kinghost'] = array('name' => "DNS-Kinghost API",
	'fields' => array(
		'KINGHOST_Username' => array('name' => "kinghost_username", 'columnheader' => "Kinghost Username", 'type' => "textbox",
			'description' => "Kinghost API Username"
		),
		'KINGHOST_Password' => array('name' => "kinghost_password", 'columnheader' => "Kinghost Password", 'type' => "password",
			'description' => "Kinghost API Password"
		)
	));
$acme_domain_validation_method['dns_knot'] = array('name' => "DNS-Knot API",
	'fields' => array(
		'KNOT_SERVER' => array('name' => "knot_server", 'columnheader' => "Knot Server", 'type' => "textbox",
			'description' => "IP address of the Knot server"
		),
		'KNOT_KEY' => array('name' => "knot_key", 'columnheader' => "Knot Key", 'type' => "password",
			'description' => "Knot TSIG Key"
		)
	));
$acme_domain_validation_method['dns_leaseweb'] = array('name' => "DNS-Leaseweb",
	'fields' => array(
		'LSW_Key' => array('name' => "lsw_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "Leaseweb API Key"
		),
	));
$acme_domain_validation_method['dns_linode'] = array('name' => "DNS-Linode",
	'fields' => array(
		'LINODE_API_KEY' => array('name' => "linode_api_key", 'columnheader' => "Linode API Key", 'type' => "textbox",
			'description' => "Linode API Key"
		)
	));
$acme_domain_validation_method['dns_linode_v4'] = array('name' => "DNS-Linode v4 API",
	'fields' => array(
		'LINODE_V4_API_KEY' => array('name' => "linode_v4_api_key", 'columnheader' => "Linode v4 API Key", 'type' => "textbox",
			'description' => "Linode v4 API Key"
		)
	));
$acme_domain_validation_method['dns_loopia'] = array('name' => "DNS-Loopia",
	'fields' => array(
		'LOOPIA_User' => array('name' => "loopia_user", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Loopia username"
		),
		'LOOPIA_Password' => array('name' => "loopia_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Loopia Password"
		)
	));
$acme_domain_validation_method['dns_lua'] = array('name' => "DNS-Luadns",
	'fields' => array(
		'LUA_Key' => array('name' => "lua_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "Luadns API Key"
		),
		'LUA_Email' => array('name' => "lua_email", 'columnheader' => "Email", 'type' => "textbox",
			'description' => "Luadns API Email Address"
		)
	));
$acme_domain_validation_method['dns_miab'] = array('name' => "DNS-MailinaBox",
	'fields' => array(
		'MIAB_Username' => array('name' => "miab_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "MailinaBox Username"
		),
		'MIAB_Password' => array('name' => "miab_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "MailinaBox Password"
		),
		'MIAB_Server' => array('name' => "miab_server", 'columnheader' => "Server", 'type' => "textbox",
			'description' => "MailinaBox Server"
		)
	));
$acme_domain_validation_method['dns_misaka'] = array('name' => "DNS-misaka.io",
	'fields' => array(
		'Misaka_Key' => array('name' => "misaka_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "misaka.io Key"
		),
	));
$acme_domain_validation_method['dns_mydnsjp'] = array('name' => "DNS-MyDNS.jp",
	'fields' => array(
		'MYDNSJP_MasterID' => array('name' => "mydnsjp_masterid", 'columnheader' => "Master ID", 'type' => "textbox",
			'description' => "MyDNS.jp Master ID"
		),
		'MYDNSJP_Password' => array('name' => "mydnsjp_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "MyDNS.jp Password"
		)
	));
$acme_domain_validation_method['dns_mythic_beasts'] = array('name' => "DNS-Mythic Beasts",
	'fields' => array(
		'MB_AK' => array('name' => "mb_ak", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "OAuth2 Key"
		),
		'MB_AS' => array('name' => "mb_as", 'columnheader' => "Secret", 'type' => "password",
			'description' => "OAuth2 Secret"
		)
	));
$acme_domain_validation_method['dns_namecom'] = array('name' => "DNS-name.com",
	'fields' => array(
		'Namecom_Username' => array('name' => "namecom_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Name.com username"
		),
		'Namecom_Token' => array('name' => "namecom_token", 'columnheader' => "API Token", 'type' => "textbox",
			'description' => "Name.com API Token"
		)
	));
$acme_domain_validation_method['dns_namecheap'] = array('name' => "DNS-Namecheap",
	'fields' => array(
		'NAMECHEAP_API_KEY' => array('name' => "namecheap_api_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "Namecheap API Key"
		),
		'NAMECHEAP_USERNAME' => array('name' => "namecheap_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Namecheap Username"
		)
	));
$acme_domain_validation_method['dns_nm'] = array('name' => "DNS-NameMaster.de",
	'fields' => array(
		'NM_user' => array('name' => "nm_user", 'columnheader' => "User", 'type' => "textbox",
			'description' => "namemaster.de API username"
		),
		'NM_sha256' => array('name' => "nm_sha256", 'columnheader' => "Password Hash", 'type' => "textbox",
			'description' => "namemaster.de API password as SHA256 hash"
		)
	));
$acme_domain_validation_method['dns_nederhost'] = array('name' => "DNS-NederHost",
	'fields' => array(
		'NederHost_Key' => array('name' => "nederhost_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "NederHost API Key"
		)
	));
$acme_domain_validation_method['dns_namesilo'] = array('name' => "DNS-Namesilo",
	'fields' => array(
		'Namesilo_Key' => array('name' => "namesilo_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "Namesilo API Key"
		)
	));
$acme_domain_validation_method['dns_neodigit'] = array('name' => "DNS-Neodigit.net",
	'fields' => array(
		'NEODIGIT_API_TOKEN' => array('name' => "neodigit_api_token", 'columnheader' => "API Token", 'type' => "textbox",
			'description' => "Neodigit API Token"
		)
	));
$acme_domain_validation_method['dns_netcup'] = array('name' => "DNS-netcup.net",
	'fields' => array(
		'NC_Apikey' => array('name' => "nc_apikey", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "Netcup API Key"
		),
		'NC_Apipw' => array('name' => "nc_apipw", 'columnheader' => "API Password", 'type' => "password",
			'description' => "Netcup API Password"
		),
		'NC_CID' => array('name' => "nc_cid", 'columnheader' => "CID", 'type' => "textbox",
			'description' => "Netcup Customer ID/Number"
		)
	));
$acme_domain_validation_method['dns_netlify'] = array('name' => "DNS-Netlify",
	'fields' => array(
		'NETLIFY_ACCESS_TOKEN' => array('name' => "netlify_access_token", 'columnheader' => "API Token", 'type' => "password",
			'description' => "Netlify API Token"
		),
	));
$acme_domain_validation_method['dns_nic'] = array('name' => "DNS-nic.ru",
	'fields' => array(
		'NIC_ClientID' => array('name' => "nic_clientid", 'columnheader' => "API ClientID", 'type' => "textbox",
			'description' => "nic.ru API Client ID"
		),
		'NIC_ClientSecret' => array('name' => "nic_clientsecret", 'columnheader' => "API Client Secret", 'type' => "textbox",
			'description' => "nic.ru API Client Secret"
		),
		'NIC_Username' => array('name' => "nic_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "nic.ru Username"
		),
		'NIC_Password' => array('name' => "nic_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "nic.ru Password"
		)
	));
$acme_domain_validation_method['dns_njalla'] = array('name' => "DNS-Njalla",
	'fields' => array(
		'NJALLA_Token' => array('name' => "netlify_access_token", 'columnheader' => "API Token", 'type' => "password",
			'description' => "Njalla API Token"
		),
	));
$acme_domain_validation_method['dns_nsone'] = array('name' => "DNS-NS1 (nsone.com)",
	'fields' => array(
		'NS1_Key' => array('name' => "ns1_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "NS1 API Key"
		)
	));
$acme_domain_validation_method['dns_nw'] = array('name' => "DNS-NW API: Nexcess / Thermo.io / Futurehosting",
	'fields' => array(
		'NW_API_TOKEN' => array('name' => "nw_api_token", 'columnheader' => "Token", 'type' => "textbox",
			'description' => "NW API Token"
		),
		'NW_API_ENDPOINT' => array('name' => 'nw_api_endpoint', 'columnheader' => 'NW API Endpoint', 'type' => 'select',
			'items' => array(
				'' => array('name' => 'None'),
				'https://portal.nexcess.net' => array('name' => 'portal.nexcess.net'),
				'https://core.thermo.io' => array('name' => 'core.thermo.io'),
				'https://my.futurehosting.com' => array('name' => 'my.futurehosting.com'),
			),
			'description' => 'Choose the NW API Endpoint'
		),
	));
$acme_domain_validation_method['dns_one'] = array('name' => "DNS-one.com",
	'fields' => array(
		'ONECOM_User' => array('name' => "onecom_user", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "One.com Username"
		),
		'ONECOM_Password' => array('name' => "onecom_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "One.com Password"
		)
	));
$acme_domain_validation_method['dns_online'] = array('name' => "DNS-Online.net",
	'fields' => array(
		'ONLINE_API_KEY' => array('name' => "online_api_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "Online.net API Key"
		)
	));
$acme_domain_validation_method['dns_oci'] = array('name' => "DNS-Oracle Cloud Infrastructure",
	'fields' => array(
		'OCI_CLI_TENANCY' => array('name' => "oci_cli_tenancy", 'columnheader' => "Tenancy", 'type' => "textbox",
			'description' => "OCID of tenancy that contains the target DNS zone"
		),
		'OCI_CLI_USER' => array('name' => "oci_cli_user", 'columnheader' => "User", 'type' => "textbox",
			'description' => "OCID of user with permission to add/remove records from zones"
		),
		'OCI_CLI_REGION' => array('name' => "oci_cli_region", 'columnheader' => "Region", 'type' => "textbox",
			'description' => "Tenancy home region"
		),
		'OCI_CLI_KEY' => array('name' => "oci_cli_key", 'columnheader' => "Key (PEM)", 'type' => "textarea",
			'description' => "The private API signing key in PEM format. Using an encrypted private key that needs a passphrase is not supported."
		),
	));
$acme_domain_validation_method['dns_openprovider'] = array('name' => "DNS-OpenProvider",
	'fields' => array(
		'OPENPROVIDER_USER' => array('name' => "openprovider_user", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "OpenProvider Username"
		),
		'OPENPROVIDER_PASSWORDHASH' => array('name' => "openprovider_passwordhash", 'columnheader' => "Password Hash", 'type' => "password",
			'description' => "OpenProvider Password Hash"
		)
	));
$acme_domain_validation_method['dns_ovh'] = array('name' => "DNS-ovh / kimsufi / soyoustart / runabove",
	'fields' => array(
		'OVH_AK' => array('name' => "ovh_ak", 'columnheader' => "Application Key", 'type' => "textbox",
			'description' => "OVH Application Key"
		),
		'OVH_AS' => array('name' => "ovh_as", 'columnheader' => "Application Secret", 'type' => "password",
			'description' => "OVH Application Secret"
		),
		'OVH_CK' => array('name' => "ovh_ck", 'columnheader' => "Consumer Key", 'type' => "textbox",
			'description' => "OVH Consumer Key"
		),
		'OVH_END_POINT' => array('name' => 'ovh_end_point', 'columnheader' => 'API Endpoint', 'type' => 'select',
			'items' => array(
				'' => array('name' => 'None'),
				'ovh-eu' => array('name' => 'OVH Europe'),
				'ovh-ca' => array('name' => 'OVH Canada'),
				'kimsufi-eu' => array('name' => 'Kimsufi Europe'),
				'kimsufi-ca' => array('name' => 'Kimsufi Canada'),
				'soyoustart-eu' => array('name' => 'SoYouStart Europe'),
				'soyoustart-ca' => array('name' => 'SoYouStart Canada'),
				'runabove-ca' => array('name' => 'RunAbove Canada'),
			),
			'description' => 'Choose the OVH API Endpoint / Region'
		),

	));
$acme_domain_validation_method['dns_pleskxml'] = array('name' => "DNS-Plesk",
	'fields' => array(
		'pleskxml_user' => array('name' => "pleskxml_user", 'columnheader' => "User", 'type' => "textbox",
			'description' => "Plesk User"
		),
		'pleskxml_pass' => array('name' => "pleskxml_pass", 'columnheader' => "Password", 'type' => "password",
			'description' => "Plesk Password"
		),
		'pleskxml_uri' => array('name' => "pleskxml_uri", 'columnheader' => "URI", 'type' => "textbox",
			'description' => "Plesk Server URI"
		),
	));
$acme_domain_validation_method['dns_pointhq'] = array('name' => "DNS-PointDNS / pointhq.com",
	'fields' => array(
		'PointHQ_Key' => array('name' => "pointhq_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "PointHQ API Key"
		),
		'PointHQ_Email' => array('name' => "pointhq_email", 'columnheader' => "E-mail", 'type' => "textbox",
			'description' => "PointHQ account E-mail address"
		),
	));
$acme_domain_validation_method['dns_porkbun'] = array('name' => "DNS-Porkbun",
	'fields' => array(
		'PORKBUN_API_KEY' => array('name' => "porkbun_api_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "API Key"
		),
		'PORKBUN_SECRET_API_KEY' => array('name' => "porkbun_secret_api_key", 'columnheader' => "Secret", 'type' => "password",
			'description' => "Secret API Key. Obtain the key and secret from https://porkbun.com/account/api"
		),
	));
$acme_domain_validation_method['dns_pdns'] = array('name' => "DNS-PowerDNS",
	'fields' => array(
		'PDNS_Url' => array('name' => "pdns_url", 'columnheader' => "URL", 'type' => "textbox",
			'description' => "PowerDNS URL (e.g. http://ns.example.com:8081 )"
		),
		'PDNS_ServerId' => array('name' => "pdns_serverid", 'columnheader' => "ServerID", 'type' => "textbox",
			'description' => "PowerDNS ServerId (e.g. localhost )"
		),
		'PDNS_Token' => array('name' => "pdns_token", 'columnheader' => "Token", 'type' => "textbox",
			'description' => "PowerDNS Token (e.g. 0123456789ABCDEF )"
		),
		'PDNS_Ttl' => array('name' => "pdns_ttl", 'columnheader' => "TTL", 'type' => "textbox",
			'description' => "PowerDNS Record TTL (e.g. 60 )"
		)
	));
$acme_domain_validation_method['dns_rackcorp'] = array('name' => "DNS-RackCorp",
	'fields' => array(
		'RACKCORP_APIUUID' => array('name' => "rackcorp_apiuuid", 'columnheader' => "UUID", 'type' => "textbox",
			'description' => "API UUID"
		),
		'RACKCORP_APISECRET' => array('name' => "rackcorp_apisecret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "API Secret"
		),
	));
$acme_domain_validation_method['dns_rackspace'] = array('name' => "DNS-Rackspace",
	'fields' => array(
		'RACKSPACE_Username' => array('name' => "rackspace_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Rackspace Username"
		),
		'RACKSPACE_Apikey' => array('name' => "rackspace_apikey", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "Rackspace API Key"
		)
	));
$acme_domain_validation_method['dns_rcode0'] = array('name' => "DNS-Rcode0",
	'fields' => array(
		'RCODE0_API_TOKEN' => array('name' => "rcode0_api_token", 'columnheader' => "API Token", 'type' => "textbox",
			'description' => "Rcode0 API Token"
		),
		'RCODE0_URL' => array('name' => "rcode0_url", 'columnheader' => "URL", 'type' => "textbox",
			'description' => "Rcode0 URL"
		),
		'RCODE0_TTL' => array('name' => "rcode0_ttl", 'columnheader' => "TTL", 'type' => "textbox",
			'description' => "Rcode0 TTL"
		)
	));
$acme_domain_validation_method['dns_regru'] = array('name' => "DNS-reg.ru",
	'fields' => array(
		'REGRU_API_Username' => array('name' => "regru_api_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "reg.ru Username"
		),
		'REGRU_API_Password' => array('name' => "regru_api_password", 'columnheader' => "API Password", 'type' => "password",
			'description' => "reg.ru API Password"
		)
	));
$acme_domain_validation_method['dns_scaleway'] = array('name' => "DNS-Scaleway",
	'fields' => array(
		'SCALEWAY_API_TOKEN' => array('name' => "scaleway_api_token", 'columnheader' => "Token", 'type' => "textbox",
			'description' => "API Token"
		),
	));
$acme_domain_validation_method['dns_schlundtech'] = array('name' => "DNS-schlundtech.de",
	'fields' => array(
		'SCHLUNDTECH_USER' => array('name' => "schlundtech_user", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "schlundtech.de Username"
		),
		'SCHLUNDTECH_PASSWORD' => array('name' => "schlundtech_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "schlundtech.de Password"
		)
	));
$acme_domain_validation_method['dns_selectel'] = array('name' => "DNS-Selectel",
	'fields' => array(
		'SL_Key' => array('name' => "sl_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "Selectel API Key"
		)
	));
$acme_domain_validation_method['dns_servercow'] = array('name' => "DNS-Servercow",
	'fields' => array(
		'SERVERCOW_API_Username' => array('name' => "servercow_api_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Servercow username"
		),
		'SERVERCOW_API_Password' => array('name' => "servercow_api_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Servercow password"
		)
	));
$acme_domain_validation_method['dns_simply'] = array('name' => "DNS-Simply.com",
	'fields' => array(
		'SIMPLY_AccountName' => array('name' => "simply_accountname", 'columnheader' => "Name", 'type' => "textbox",
			'description' => "Account Name"
		),
		'SIMPLY_ApiKey' => array('name' => "simply_apikey", 'columnheader' => "Key", 'type' => "password",
			'description' => "API Key"
		),
		'SIMPLY_Api' => array('name' => "simply_api", 'columnheader' => "API", 'type' => "textbox",
			'description' => "API Endpoint URL. Default: https://api.simply.com/1"
		),
	));
$acme_domain_validation_method['dns_tele3'] = array('name' => "DNS-Tele3.cz",
	'fields' => array(
		'TELE3_Key' => array('name' => "tele3_key", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "Tele3 Key"
		),
		'TELE3_Secret' => array('name' => "tele3_secret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "Tele3 Secret"
		)
	));
$acme_domain_validation_method['dns_udr'] = array('name' => "DNS-United-Domains Reselling",
	'fields' => array(
		'UDR_USER' => array('name' => "udr_user", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Username"
		),
		'UDR_PASS' => array('name' => "udr_pass", 'columnheader' => "Password", 'type' => "password",
			'description' => "Password"
		)
	));
$acme_domain_validation_method['dns_ultra'] = array('name' => "DNS-UltraDNS",
	'fields' => array(
		'ULTRA_USR' => array('name' => "ultra_usr", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "UltraDNS Username"
		),
		'ULTRA_PWD' => array('name' => "ULTRA_PWD", 'columnheader' => "Password", 'type' => "password",
			'description' => "UltraDNS Password"
		)
	));
$acme_domain_validation_method['dns_unoeuro'] = array('name' => "DNS-UnoEuro",
	'fields' => array(
		'UNO_User' => array('name' => "uno_user", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "UnoEuro username"
		),
		'UNO_Key' => array('name' => "uno_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "UnoEuro API Key"
		)
	));
$acme_domain_validation_method['dns_variomedia'] = array('name' => "DNS-variomedia.de",
	'fields' => array(
		'VARIOMEDIA_API_TOKEN' => array('name' => "variomedia_api_token", 'columnheader' => "API Token", 'type' => "textbox",
			'description' => "variomedia.de API Token"
		),
	));
$acme_domain_validation_method['dns_veesp'] = array('name' => "DNS-Veesp",
	'fields' => array(
		'VEESP_User' => array('name' => "veesp_user", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Username"
		),
		'VEESP_Password' => array('name' => "veesp_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Password"
		)
	));
$acme_domain_validation_method['dns_vscale'] = array('name' => "DNS-vscale.io",
	'fields' => array(
		'VSCALE_API_KEY' => array('name' => "vscale_api_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "vscale API Key"
		)
	));
$acme_domain_validation_method['dns_vultr'] = array('name' => "DNS-vultr.com",
	'fields' => array(
		'VULTR_API_KEY' => array('name' => "vultr_api_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "vultr.com API Key"
		)
	));
$acme_domain_validation_method['dns_websupport'] = array('name' => "DNS-websupport.sk",
	'fields' => array(
		'WS_ApiKey' => array('name' => "ws_apikey", 'columnheader' => "Key", 'type' => "textbox",
			'description' => "API Key / 'Identifier' in the WS Admin"
		),
		'WS_ApiSecret' => array('name' => "ws_apisecret", 'columnheader' => "Secret", 'type' => "password",
			'description' => "API Secret / 'Secret key' in the WS Admin. Obtain the API Key and Secret from https://admin.websupport.sk/en/auth/apiKey."
		)
	));
$acme_domain_validation_method['dns_world4you'] = array('name' => "DNS-World4You",
	'fields' => array(
		'WORLD4YOU_USERNAME' => array('name' => "world4you_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Username"
		),
		'WORLD4YOU_PASSWORD' => array('name' => "world4you_password", 'columnheader' => "Password", 'type' => "password",
			'description' => "Password"
		)
	));
$acme_domain_validation_method['dns_yandex'] = array('name' => "DNS-Yandex",
	'fields' => array(
		'PDD_Token' => array('name' => "pdd_token", 'columnheader' => "PDD Token", 'type' => "textbox",
			'description' => "Yandex PDD Token, generate at <a href=\"https://pddimp.yandex.ru/api2/admin/get_token\">https://pddimp.yandex.ru/api2/admin/get_token</a>"
		)
	));
$acme_domain_validation_method['dns_zonomi'] = array('name' => "DNS-Zonomi",
	'fields' => array(
		'ZM_Key' => array('name' => "zm_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "Zonomi API Key"
		)
	));
$acme_domain_validation_method['dns_zone'] = array('name' => "DNS-Zone.ee",
	'fields' => array(
		'ZONE_Username' => array('name' => "zone_username", 'columnheader' => "Username", 'type' => "textbox",
			'description' => "Zone.ee Username"
		),
		'ZONE_Key' => array('name' => "zone_key", 'columnheader' => "API Key", 'type' => "textbox",
			'description' => "Zone.ee API Key"
		)
	));
$acme_domain_validation_method['dns_zilore'] = array('name' => "DNS-Zilore",
	'fields' => array(
		'Zilore_Key' => array('name' => "zilore_key", 'columnheader' => "Zilore API Key", 'type' => "textbox",
			'description' => "Zilore API Key"
		)
	));
$acme_domain_validation_method['anydns'] = array('name' => "notforuser",
	'fields' => array(
		'challengealias' => array('name' => "challengealias", 'columnheader' => "Enable DNS alias mode", 'type' => "textbox",
			'description' => '(Optional) Adds the --challenge-alias flag to the acme.sh call.<br/>To use a CNAME for _acme-challenge.importantDomain.tld to direct the acme validation to a different (sub)domain _acme-challenge.aliasDomainForValidationOnly.tld, configure the alternate domain here.<br/>More information can be found <a href="https://github.com/acmesh-official/acme.sh/wiki/DNS-alias-mode" target="_new">here</a>.'
		),
		'challengedomain' => array('name' => "challengedomain", 'columnheader' => "Enable DNS domain alias mode", 'type' => "checkbox",
			'description' => '(Optional) Uses the challenge domain alias value as --domain-alias instead in the acme.sh call.'
		)
	));

//TODO add more challenge validation types
/* 
$acme_domain_validation_method['http-post'] = array('name' => "http-post",
	'fields' => array(
		'url' => array('name' => "url", 'columnheader' => "Url", 'type' => "textbox", 'size' => "50",
			'description' => "Url the challenge file is posted to, the webserver there must store and reply to the request when the acme servers perform the request for the file from /.well-known/acme-challenge/"
		)
	));
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

if (!is_array($config['installedpackages']['acme'])) {
	$config['installedpackages']['acme'] = array();
}
if (!is_array($config['installedpackages']['acme']['certificates'])) {
	$config['installedpackages']['acme']['certificates'] = array();
}
if (!is_array($config['installedpackages']['acme']['certificates']['item'])) {
	$config['installedpackages']['acme']['certificates']['item'] = array();
}

if (!is_array($config['installedpackages']['acme'])) {
	$config['installedpackages']['acme'] = array();
}
if (!is_array($config['installedpackages']['acme']['accountkeys'])) {
	$config['installedpackages']['acme']['accountkeys'] = array();
}
if (!is_array($config['installedpackages']['acme']['accountkeys']['item'])) {
	$config['installedpackages']['acme']['accountkeys']['item'] = array();
}

function set_cronjob() {
	global $config;
	$a_global = &$config['installedpackages']['acme'];
	if (isset($a_global['enable'])) {
		install_cron_job('/usr/local/pkg/acme/acme_command.sh "renewall" | /usr/bin/logger -t ACME 2>&1', true, "16", "3");
	} else {
		install_cron_job('/usr/local/pkg/acme/acme_command.sh "renewall" | /usr/bin/logger -t ACME 2>&1', false);
	}
}

function acme_custom_php_deinstall_command() {
	global $static_output;
	$static_output .= "Acme, running acme_custom_php_deinstall_command()\n";
	update_output_window($static_output);
	$static_output .= "Acme, uninstalling cron job\n";
	update_output_window($static_output);
	install_cron_job("/etc/rc.acme_renew.sh", false);
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
	global $config;
	init_config_arr(array('installedpackages', 'acme', 'accountkeys', 'item'));
	init_config_arr(array('installedpackages', 'acme', 'certificates', 'item'));

	foreach (array('accountkeys', 'certificates') as $a_array) {
		foreach ($config['installedpackages']['acme'][$a_array]['item'] as & $item) {
			if (isset($item['desc'])) {
				$item['descr'] = $item['desc'];
				unset($item['desc']);
			}
		}
	}
}

function acme_convert_letsencryptv1_to_letsencryptv2() {
	global $config;
	init_config_arr(array('installedpackages', 'acme', 'accountkeys', 'item'));

	foreach ($config['installedpackages']['acme']['accountkeys']['item'] as & $item) {
		if ($item['acmeserver'] == 'letsencrypt-staging') {
			$item['acmeserver'] = 'letsencrypt-staging-2';
		} elseif ($item['acmeserver'] == 'letsencrypt-production') {
			$item['acmeserver'] = 'letsencrypt-production-2';
		}
	}
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
	global $config;
	if (!is_array($config['installedpackages']['acme']['accountkeys']['item'])) {
		$config['installedpackages']['acme']['accountkeys']['item'] = array();
	}
	$a_array = &$config['installedpackages']['acme']['accountkeys']['item'];
	return get_itembyname($a_array, $name);
}
function get_accountkey($name) {
	global $config;
	if (!is_array($config['installedpackages']['acme']['accountkeys']['item'])) {
		$config['installedpackages']['acme']['accountkeys']['item'] = array();
	}
	$a_array = &$config['installedpackages']['acme']['accountkeys']['item'];
	$id = get_accountkey_id($name);
	return $a_array[$id];
}

function get_certificate_id($name) {
	global $config;
	if (!is_array($config['installedpackages']['acme']['certificates']['item'])) {
		$config['installedpackages']['acme']['certificates']['item'] = array();
	}
	$a_certificates = &$config['installedpackages']['acme']['certificates']['item'];
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

function & get_certificate($name) {
	global $config;
	if (!is_array($config['installedpackages']['acme']['certificates']['item'])) {
		$config['installedpackages']['acme']['certificates']['item'] = array();
	}
	$a_certificates = &$config['installedpackages']['acme']['certificates']['item'];
	$id = get_certificate_id($name);
	if (is_numeric($id)) {
		return $a_certificates[$id];
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
		global $config;		
		$a_certificates = getarraybyref($config, 'installedpackages', 'acme', 'certificates', 'item');
		$errors = "";
		foreach($a_certificates as $certificate) {
			if ($certificate['status'] != 'active') {
				echo "Certificate renewal certificate {$certificate['name']} is set to: disabled\n";
				continue;
			}
			echo "Checking if renewal is needed for: {$certificate['name']}\n";
			$res = issue_certificate($certificate['name'], $force);
			if (!$res) {
				$errmsg = "ACME, Failed to renew certificate for {$certificate['name']}";
				syslog(LOG_WARNING, $errmsg);
				$errors .= "{$errmsg}\n";
			}
		}
		if (!empty($errors)) {
			notify_all_remote($errors);
		}
	}
	
	function getCertificatePSK($ca, $certificate, $domain) {
		$certificatename = $certificate['name'];
		$cert = lookup_cert_by_name($certificatename);
		if (!is_array($cert)) {
			global $config;
			$a_cert =& $config['cert'];
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
			$a_cert[] = $cert;
			echo "\n{$cert['prv']}";
			$desc = "Acme: Add new certificate & key.";
			write_config($desc);
		} elseif (($certificate['keylength'] == 'custom') && ($cert['prv'] != $certificate['keypaste'])) {
			echo "\n getCertificatePSK updating custom key";
			$cert['prv'] = $certificate['keypaste']; // (already base64-encoded)
		} elseif ($certificate['keylength'] != 'custom') {
			$res_key = openssl_pkey_get_private(base64_decode($cert['prv']));
			$key_details = openssl_pkey_get_details($res_key);
			if ($key_details['type'] == OPENSSL_KEYTYPE_RSA) {
				$keybits = $key_details['bits'];
			} else {
				$keybits = $key_details['ec']['curve_name'];
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
		global $config;
		$crt_enc = base64_encode($crt);
		if (!is_array($config['ca'])) {
			$config['ca'] = array();
		}
		$a_ca =& $config['ca'];
		foreach($a_ca as $ca) {
			if ($ca['crt'] == $crt_enc) {
				return;
			}
		}
		$subject = cert_get_subject($crt, false);
		$cert = array();
		$cert['refid'] = uniqid();
		$cert['descr'] = "Acmecert: {$subject}";
		ca_import($cert, $crt);
		$a_ca[] = $cert;
		syslog(LOG_NOTICE, "Acme, storing new CA: {$subject}");
	}
	
	function storeCertificateCer($certificatename, $keyfile, $cerfile, $fullchainfile) {
		global $config;
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
		if (is_array($config['cert'])) {
			foreach ($config['cert'] as &$cert) {
				if ($cert['descr'] == $certificatename) {
					syslog(LOG_NOTICE, "Acme, storing new certificate: {$certificatename}");
					echo "\nupdate cert!";
					cert_import($cert, $crt, $key);
					$certupdated = true;
					break;
				}
			}
		}
		
		return $certupdated;
	}
		
	function issue_certificate($id, $force = false, $renew = false) {
		$result = true;
		$certificate = & get_certificate($id);
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
			syslog(LOG_NOTICE, "Acme, renewing certificate: {$id}");
			echo "Renewing certificate \n";
			$domainstosign = array();
			if (is_array($certificate['a_domainlist']['item'])) {
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
						# The function to find the default gateway changed on 2.5.0
						$defgw = function_exists('route_get_default') ? route_get_default('inet') : getcurrentdefaultgatewayip('inet');
						$envvariables['NAMECHEAP_SOURCEIP'] = find_interface_ip(guess_interface_from_ip($defgw));
					}
					global $acme_domain_validation_method;
					foreach($acme_domain_validation_method[$method]['fields'] as $key => $field) {
						$envvariables[$key] = $domain["{$method}{$field['name']}"];
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
			$acmesh->ocspstaple = $certificate['ocspstaple'];
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
