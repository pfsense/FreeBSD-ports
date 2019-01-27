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
			$execstr = <<<EOD
require_once('service-utils.inc');
service_control_restart("{$servicename}", $extras);
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
$a_acmeserver['letsencrypt-staging'] = array('name' => "Let's Encrypt Staging ACME v1 (for TESTING purposes)",
    'url' => 'https://acme-staging.api.letsencrypt.org',
);
$a_acmeserver['letsencrypt-staging-2'] = array('name' => "Let's Encrypt Staging ACME v2 (for TESTING purposes)",
    'url' => 'https://acme-staging-v02.api.letsencrypt.org'
);
$a_acmeserver['letsencrypt-production'] = array('name' => "Let's Encrypt Production ACME v1 (Applies rate limits to certificate requests)",
    'url' => 'https://acme-v01.api.letsencrypt.org',
);
$a_acmeserver['letsencrypt-production-2'] = array('name' => "Let's Encrypt Production ACME v2 (Applies rate limits to certificate requests)",
    'url' => 'https://acme-v02.api.letsencrypt.org'
);
$a_acmeserver['buypass-production'] = array('name' => "BuyPass Production ACME v2 (Applies rate limits to certificate requests)",
    'url' => 'https://api.buypass.com/acme'
);
/*$a_acmeserver['dummy'] = array('name' => "dummy",
    'url' => 'https://example.org'
);*/

global $acme_domain_validation_method;
$acme_domain_validation_method = array();
$acme_domain_validation_method['webroot'] = array('name' => "webroot local folder",
	'fields' => array(
		'folder' => array('name'=>"folder",'columnheader'=>"RootFolder",'type'=>"textbox",'size'=>"50",
			'description' =>"Folder the acme challenge response is written to; for example: /usr/local/www/.well-known/acme-challenge/"
		)
	));
$acme_domain_validation_method['webrootftp'] = array('name' => "webroot FTP",
	'fields' => array(
		'ftpserver' => array('name'=>"ftpserver",'columnheader'=>"Server",'type'=>"textbox",'size'=>"50",
			'description' =>"Hostname of FTP server to connect to for example: ftps://www.webserver.tld "
			. "currently ftps(passive) and sftp are supported."
		),
		'username' => array('name'=>"username",'columnheader'=>"Username",'type'=>"textbox",'size'=>"50",
			'description' =>"Username for the remote server"
		),
		'password' => array('name'=>"password",'columnheader'=>"Password",'type'=>"textbox",'size'=>"50",
			'description' =>"Password used to authenticate to the server"
		),
		'folder' => array('name'=>"folder",'columnheader'=>"Folder",'type'=>"textbox",'size'=>"50",
			'description' =>"Folder the acme challenge response is written to for default: /.well-known/acme-challenge/"
		)
	));
$acme_domain_validation_method['standalone'] = array('name' => "standalone HTTP server",
	'fields' => array(
		'port' => array('name'=>"port",'columnheader'=>"Port",'type'=>"textbox",'size'=>"50",
			'description' =>"HTTP listen port for stand-alone server. Must be 80 or have port 80 on WAN forwarded to this port. Firewall rules must also allow traffic to reach this port."
		),
		'ipv6' => array('name'=>"ipv6",'columnheader'=>"IPv6",'type'=>"checkbox",
			'description' =>"Bind to IPv6 instead of IPv4."
		)
	));
$acme_domain_validation_method['standalonetls'] = array('name' => "standalone TLS-ALPN server",
	'fields' => array(
		'port' => array('name'=>"port",'columnheader'=>"Port",'type'=>"textbox",'size'=>"50",
			'description' =>"TLS listen port for stand-alone server. Must be 443 or have port 443 on WAN forwarded to this port. Firewall rules must also allow traffic to reach this port."
		),
	));
$acme_domain_validation_method['dns_manual'] = array('name' => "DNS-Manual",
	'fields' => array(
	));
$acme_domain_validation_method['dns_nsupdate'] = array('name' => "DNS-NSupdate / RFC 2136",
	'fields' => array(
		'NSUPDATE_SERVER' => array('name'=>"nsupdate_server",'columnheader'=>"Server",'type'=>"textbox",
			'description' =>"Fill in the nsupdate DNS server (IP address or hostname)"
		),
		'NSUPDATE_KEYNAME' => array('name'=>"nsupdate_keyname",'columnheader'=>"Key Name",'type'=>"textbox",
			'description' =>"(Optional) Fill in a name for the key, if it is different than _acme-challenge.[Domainname]"
		),
		'NSUPDATE_KEYALGO' => array('name' => 'nsupdate_keyalgo', 'columnheader' => 'Key Algorithm', 'type' => 'select',
			'items' => array(
				'157' => array('name' => 'HMAC-MD5'),
				'165' => array('name' => 'HMAC-SHA512'),
				'164' => array('name' => 'HMAC-SHA384'),
				'163' => array('name' => 'HMAC-SHA256'),
				'162' => array('name' => 'HMAC-SHA224'),
				'161' => array('name' => 'HMAC-SHA1'),
			),
			'description' => 'Choose the nsupdate key algorithm  (typically "HMAC-MD5")'
		),
		'NSUPDATE_KEY' => array('name'=>"nsupdate_key",'columnheader'=>"Key",'type'=>"textarea",
			'description' =>"Fill in the nsupdate key contents"
		),
		'NSUPDATE_ZONE' => array('name'=>"nsupdate_zone",'columnheader'=>"Zone",'type'=>"textbox",
			'description' =>"(Optional) Explicitly set the zone name for updates."
		)
	));
$acme_domain_validation_method['dns_acmedns'] = array('name' => "DNS-acme-dns.io",
	'fields' => array(
		'ACMEDNS_USERNAME' => array('name'=>"acmedns_username",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"Fill in the acme-dns.io Username"
		),
		'ACMEDNS_PASSWORD' => array('name'=>"acmedns_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Fill in the acme-dns.io Password"
		),
		'ACMEDNS_SUBDOMAIN' => array('name'=>"acmedns_subdomain",'columnheader'=>"Subdomain",'type'=>"textbox",
			'description' =>"Fill in the acme-dns.io subdomain"
		),
		'ACMEDNS_UPDATE_URL' => array('name'=>"acmedns_update_url",'columnheader'=>"Update URL",'type'=>"textbox",
			'description' =>"Fill in a custom ACME DNS Update URL (optional)"
		),
	));
$acme_domain_validation_method['dns_ali'] = array('name' => "DNS-Aliyuncs",
	'fields' => array(
		'Ali_Key' => array('name'=>"ali_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		),
		'Ali_Secret' => array('name'=>"ali_secret",'columnheader'=>"Secret",'type'=>"textbox",
			'description' =>"Fill in the API Secret"
		)
	));
$acme_domain_validation_method['dns_ad'] = array('name' => "DNS-Alwaysdata",
	'fields' => array(
		'AD_API_KEY' => array('name'=>"ad_api_key",'columnheader'=>"API Key",'type'=>"textbox",
			'description' =>"Alwaysdata API Key"
		)
	));
$acme_domain_validation_method['dns_autodns'] = array('name' => "DNS-autoDNS from InternetX",
	'fields' => array(
		'AUTODNS_USER' => array('name'=>"autodns_user",'columnheader'=>"User",'type'=>"textbox",
			'description' =>"Fill in the autoDNS Username"
		),
		'AUTODNS_PASSWORD' => array('name'=>"autodns_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Fill in the autoDNS Password"
		),
		'AUTODNS_CONTEXT' => array('name'=>"autodns_context",'columnheader'=>"Context",'type'=>"textbox",
			'description' =>"Fill in the autoDNS Context"
		)
	));
$acme_domain_validation_method['dns_aws'] = array('name' => "DNS-Amazon Route53",
	'fields' => array(
		'AWS_ACCESS_KEY_ID' => array('name'=>"aws_access_key_id",'columnheader'=>"Id",'type'=>"textbox",
			'description' =>"Fill in the API Id"
		),
		'AWS_SECRET_ACCESS_KEY' => array('name'=>"aws_secret_access_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		)
	));
$acme_domain_validation_method['dns_azure'] = array('name' => "DNS-Azure (Microsoft)",
	'fields' => array(
		'AZUREDNS_SUBSCRIPTIONID' => array('name'=>"azuredns_subscriptionid",'columnheader'=>"Subscription ID",'type'=>"textbox",
			'description' =>"Fill in the Azure Subscription ID. First, <a href=\"https://github.com/Neilpang/acme.sh/wiki/How-to-use-Azure-DNS\">setup a service principal for access to the DNS Zone</a>."
		),
		'AZUREDNS_TENANTID' => array('name'=>"azuredns_tenantid",'columnheader'=>"Tenant ID",'type'=>"textbox",
			'description' =>"Fill in the Azure Tenant ID"
		),
		'AZUREDNS_APPID' => array('name'=>"azuredns_appid",'columnheader'=>"App ID",'type'=>"textbox",
			'description' =>"Fill in the Azure App ID"
		),
		'AZUREDNS_CLIENTSECRET' => array('name'=>"azuredns_clientsecret",'columnheader'=>"Client Secret",'type'=>"textbox",
			'description' =>"Fill in the Azure Client Secret"
		)
	));
$acme_domain_validation_method['dns_cloudns'] = array('name' => "DNS-ClouDNS",
	'fields' => array(
		'CLOUDNS_AUTH_ID' => array('name'=>"cloudns_auth_id",'columnheader'=>"Auth ID",'type'=>"textbox",
			'description' =>"Fill in the authentication ID"
		),
		'CLOUDNS_SUB_AUTH_ID' => array('name'=>"cloudns_sub_auth_id",'columnheader'=>"Sub Auth ID",'type'=>"textbox",
			'description' =>"Fill in the sub authentication ID"
		),
		'CLOUDNS_AUTH_PASSWORD' => array('name'=>"cloudns_auth_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Fill in the Password"
		)
	));
$acme_domain_validation_method['dns_cf'] = array('name' => "DNS-Cloudflare",
	'fields' => array(
		'CF_Key' => array('name'=>"cf_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		),
		'CF_Email' => array('name'=>"cf_email",'columnheader'=>"Email",'type'=>"textbox",
			'description' =>"Fill in the API Email Address"
		)
	));
$acme_domain_validation_method['dns_cx'] = array('name' => "DNS-Cloudxns",
	'fields' => array(
		'CX_Key' => array('name'=>"cx_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		),
		'CX_Secret' => array('name'=>"cx_secret",'columnheader'=>"Secret",'type'=>"textbox",
			'description' =>"Fill in the API Secret"
		)
	));
$acme_domain_validation_method['dns_conoha'] = array('name' => "DNS-Conoha",
	'fields' => array(
		'CONOHA_Username' => array('name'=>"conoha_username", 'columnheader'=>"User", 'type'=>"textbox",
			'description' =>"Fill in the Username"
		),
		'CONOHA_Password' => array('name'=>"conoha_password", 'columnheader'=>"Password", 'type'=>"textbox",
			'description' =>"Fill in the Password"
		),
		'CONOHA_TenantId' => array('name'=>"conoha_tenantid", 'columnheader'=>"Tenant ID", 'type'=>"textbox",
			'description' =>"Fill in the Tenant ID"
		),
		'CONOHA_IdentityServiceApi' => array('name'=>"conoha_identityserviceapi", 'columnheader'=>"ID Srv API", 'type'=>"textbox",
			'description' =>"Fill in the Identity Service API"
		)
	));
$acme_domain_validation_method['dns_cyon'] = array('name' => "DNS-cyon.ch",
	'fields' => array(
		'CY_Username' => array('name'=>"cy_username",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"Fill in the CY username"
		),
		'CY_Password' => array('name'=>"cy_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Fill in the CY Password"
		)
	));
$acme_domain_validation_method['dns_dgon'] = array('name' => "DNS-DigitalOcean",
	'fields' => array(
		'DO_API_KEY' => array('name'=>"do_api_key",'columnheader'=>"DO API Key",'type'=>"textbox",
			'description' =>"Fill in the DigitalOcean API Key"
		)
	));
$acme_domain_validation_method['dns_da'] = array('name' => "DNS-DirectAdmin",
	'fields' => array(
		'DA_Api' => array('name'=>"da_api",'columnheader'=>"DA API URI",'type'=>"textbox",
			'description' =>"Fill in the DirectAdmin API URI (e.g. https://remoteUser:remotePassword@da.example.com:8443)"
		),
		'DA_Api_Insecure' => array('name'=>"da_api_insecure",'columnheader'=>"DA API Secure",'type'=>"textbox",
			'description' =>"DirectAdmin API Security check, 0=check for valid SSL certificate, 1=always accept",
		)
	));
$acme_domain_validation_method['dns_dnsimple'] = array('name' => "DNS-DNSimple",
	'fields' => array(
		'DNSimple_OAUTH_TOKEN' => array('name'=>"dnsimple_oauth_token",'columnheader'=>"OAUTH Token",'type'=>"textbox",
			'description' =>"Fill in the DNSimple oauth token, visit <a href=\"https://dnsimple.com/user\">https://dnsimple.com/user</a> to generate."
		)
	));
$acme_domain_validation_method['dns_me'] = array('name' => "DNS-DNSMadeEasy",
	'fields' => array(
		'ME_Key' => array('name'=>"me_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		),
		'ME_Secret' => array('name'=>"me_secret",'columnheader'=>"Secret",'type'=>"textbox",
			'description' =>"Fill in the API Secret"
		)
	));
$acme_domain_validation_method['dns_dpi'] = array('name' => "DNS-Dnspod.com",
	'fields' => array(
		'DPI_Id' => array('name'=>"dpi_id",'columnheader'=>"ID",'type'=>"textbox",
			'description' =>"Fill in the ID"
		),
		'DPI_Key' => array('name'=>"dpi_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Fill in the Key"
		)
	));
$acme_domain_validation_method['dns_do'] = array('name' => "DNS-Domain-Offensive / Resellerinterface / Domainrobot",
	'fields' => array(
		'DO_PID' => array('name'=>"do_pid",'columnheader'=>"Customer ID",'type'=>"textbox",
			'description' =>"Fill in the DO Customer ID"
		),
		'DO_PW' => array('name'=>"do_pw",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Fill in the DO Password"
		)
	));
$acme_domain_validation_method['dns_dp'] = array('name' => "DNS-Dnspod.cn",
	'fields' => array(
		'DP_Id' => array('name'=>"dp_id",'columnheader'=>"Id",'type'=>"textbox",
			'description' =>"Fill in the API Id"
		),
		'DP_Key' => array('name'=>"dp_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		)
	));
$acme_domain_validation_method['dns_dreamhost'] = array('name' => "DNS-DreamHost",
	'fields' => array(
		'DH_API_KEY' => array('name'=>"dh_api_key",'columnheader'=>"API Token",'type'=>"textbox",
			'description' =>"Fill in the Dreamhost API Token"
		)
	));
$acme_domain_validation_method['dns_duckdns'] = array('name' => "DNS-DuckDNS",
	'fields' => array(
		'DuckDNS_Token' => array('name'=>"duckdns_token",'columnheader'=>"API Token",'type'=>"textbox",
			'description' =>"Fill in the DuckDNS API Token (Look in DuckDNS account settings)"
		)
	));
$acme_domain_validation_method['dns_dyn'] = array('name' => "DNS-Dyn.com",
	'fields' => array(
		'DYN_Customer' => array('name'=>"dyn_customer",'columnheader'=>"Customer",'type'=>"textbox",
			'description' =>"Fill in the dyn.com customer ID"
		),
		'DYN_Username' => array('name'=>"dyn_username",'columnheader'=>"API Username",'type'=>"textbox",
			'description' =>"Fill in the dyn.com API Username (Dyn Managed DNS user, Needs Z&R Permissions for RecordAdd, RecordUpdate, RecordDelete, RecordGet, ZoneGet, ZoneAddNode, ZoneRemoveNode, ZonePublish)"
		),
		'DYN_Password' => array('name'=>"dyn_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Fill in the dyn.com Password"
		)
	));
$acme_domain_validation_method['dns_dynu'] = array('name' => "DNS-Dynu",
	'fields' => array(
		'Dynu_ClientId' => array('name'=>"dynu_clientid",'columnheader'=>"API Client ID",'type'=>"textbox",
			'description' =>"Fill in the API Client ID created in the Dynu account settings"
		),
		'Dynu_Secret' => array('name'=>"dynu_secret",'columnheader'=>"Secret",'type'=>"textbox",
			'description' =>"Fill in the API Secret"
		)
	));
$acme_domain_validation_method['dns_euserv'] = array('name' => "DNS-Euserv.eu",
	'fields' => array(
		'EUSERV_Username' => array('name'=>"euserv_username",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"Fill in the Username"
		),
		'EUSERV_Password' => array('name'=>"euserv_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Fill in the Password"
		)
	));
$acme_domain_validation_method['dns_exoscale'] = array('name' => "DNS-Exoscale",
	'fields' => array(
		'EXOSCALE_API_KEY' => array('name'=>"exoscale_api_key",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"Fill in the Exoscale API Key"
		),
		'EXOSCALE_SECRET_KEY' => array('name'=>"exoscale_secret_key",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Fill in the Exoscale Secret Key"
		)
	));
$acme_domain_validation_method['dns_freedns'] = array('name' => "DNS-FreeDNS",
	'fields' => array(
		'FREEDNS_User' => array('name'=>"freedns_user",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"Fill in the username"
		),
		'FREEDNS_Password' => array('name'=>"freedns_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Fill in the Password"
		)
	));
@$acme_domain_validation_method['dns_gandi_livedns'] = array('name' => "DNS-Gandi LiveDNS",
	'fields' => array(
		'GANDI_LIVEDNS_KEY' => array('name'=>"gandi_livedns_key",'columnheader'=>"API Key",'type'=>"textbox",
			'description' =>"Set the Gandi LiveDNS API Key, retrieved from <a href=\"https://account.gandi.net\">https://account.gandi.net</a>"
		)
	));
$acme_domain_validation_method['dns_gd'] = array('name' => "DNS-Godaddy",
	'fields' => array(
		'GD_Key' => array('name'=>"gd_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		),
		'GD_Secret' => array('name'=>"gd_secret",'columnheader'=>"Secret",'type'=>"textbox",
			'description' =>"Fill in the API Secret"
		)
	));
$acme_domain_validation_method['dns_gdnsdk'] = array('name' => "DNS-GratisDNS",
	'fields' => array(
		'GDNSDK_Username' => array('name'=>"gdnsdk_username",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"GratisDNS Username"
		),
		'GDNSDK_Password' => array('name'=>"gdnsdk_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"GratisDNS Password"
		)
	));
$acme_domain_validation_method['dns_he'] = array('name' => "DNS-Hurricane Electric",
	'fields' => array(
		'HE_Username' => array('name'=>"he_username",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"Hurricane Electric username"
		),
		'HE_Password' => array('name'=>"he_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Hurricane Electric password"
		)
	));
$acme_domain_validation_method['dns_hostingde'] = array('name' => "DNS-hosting.de",
	'fields' => array(
		'HOSTINGDE_APIKEY' => array('name'=>"hostingde_apikey",'columnheader'=>"API Key",'type'=>"textbox",
			'description' =>"API Key"
		),
		'HOSTINGDE_ENDPOINT' => array('name'=>"hostingde_endpoint",'columnheader'=>"Endpoint",'type'=>"textbox",
			'description' =>"Hosting.de API endpoint, e.g. https://secure.hosting.de"
		)
	));
$acme_domain_validation_method['dns_infoblox'] = array('name' => "DNS-Infoblox",
	'fields' => array(
		'Infoblox_Creds' => array('name'=>"infoblox_creds",'columnheader'=>"Credentials",'type'=>"textbox",
			'description' =>"Fill in the credentials in <strong>username:password</strong> format"
		),
		'Infoblox_Server' => array('name'=>"infoblox_server",'columnheader'=>"Server",'type'=>"textbox",
			'description' =>"Fill in the IP address or hostname of the Infoblox server"
		),
		'Infoblox_View' => array('name'=>"infoblox_view",'columnheader'=>"View",'type'=>"textbox",
			'description' =>"Fill in the DNS View, or enter 'default'"
		)
	));
$acme_domain_validation_method['dns_inwx'] = array('name' => "DNS-INWX.de",
	'fields' => array(
		'INWX_User' => array('name'=>"inwx_username",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"INWX.de username"
		),
		'INWX_Password' => array('name'=>"inwx_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"INWX.de password"
		)
	));
$acme_domain_validation_method['dns_ispconfig'] = array('name' => "DNS-ISPConfig",
	'fields' => array(
		'ISPC_User' => array('name'=>"ispc_user",'columnheader'=>"User",'type'=>"textbox",
			'description' =>"Fill in the remoteUser"
		),
		'ISPC_Password' => array('name'=>"ispc_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Fill in the remotePassword"
		),
		'ISPC_Api' => array('name'=>"ispc_api",'columnheader'=>"Api",'type'=>"textbox",
			'description' =>"Fill in the https://ispc.domain.tld:8080/remote/json.php"
		),
		'ISPC_Api_Insecure' => array('name'=>"ispc_api_insecure",'columnheader'=>"Secure",'type'=>"textbox",
			'description' =>"Fill in the Set 1 for insecure and 0 for secure  -> difference is whether ssl cert is checked for validity (0) or whether it is just accepted (1)"
		)
	));
/*
You must install python and lexicon before using it. ???
$acme_domain_validation_method['dns_lexicon'] = array('name' => "DNS-Lexicon",
	'fields' => array(
		'' => array('name'=>"ftpserver",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		),
		'' => array('name'=>"username",'columnheader'=>"Id",'type'=>"textbox",
			'description' =>"Fill in the API Id"
		)
	));*/
$acme_domain_validation_method['dns_kinghost'] = array('name' => "DNS-Kinghost API",
	'fields' => array(
		'KINGHOST_Username' => array('name'=>"kinghost_username",'columnheader'=>"Kinghost Username",'type'=>"textbox",
			'description' =>"Fill in the Kinghost API Username"
		),
		'KINGHOST_Password' => array('name'=>"kinghost_password",'columnheader'=>"Kinghost Password",'type'=>"textbox",
			'description' =>"Fill in the Kinghost API Password"
		)
	));
$acme_domain_validation_method['dns_knot'] = array('name' => "DNS-Knot API",
	'fields' => array(
		'KNOT_SERVER' => array('name'=>"knot_server",'columnheader'=>"Knot Server",'type'=>"textbox",
			'description' =>"Fill in the IP address of the Knot server"
		),
		'KNOT_KEY' => array('name'=>"knot_key",'columnheader'=>"Knot Key",'type'=>"textbox",
			'description' =>"Fill in the Knot TSIG Key"
		)
	));
$acme_domain_validation_method['dns_linode'] = array('name' => "DNS-Linode",
	'fields' => array(
		'LINODE_API_KEY' => array('name'=>"linode_api_key",'columnheader'=>"Linode API Key",'type'=>"textbox",
			'description' =>"Fill in the Linode API Key"
		)
	));
$acme_domain_validation_method['dns_linode_v4'] = array('name' => "DNS-Linode v4 API",
	'fields' => array(
		'LINODE_V4_API_KEY' => array('name'=>"linode_v4_api_key",'columnheader'=>"Linode v4 API Key",'type'=>"textbox",
			'description' =>"Fill in the Linode v4 API Key"
		)
	));
$acme_domain_validation_method['dns_loopia'] = array('name' => "DNS-Loopia",
	'fields' => array(
		'LOOPIA_User' => array('name'=>"loopia_user",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"Fill in the Loopia username"
		),
		'LOOPIA_Password' => array('name'=>"loopia_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Fill in the Loopia Password"
		)
	));
$acme_domain_validation_method['dns_lua'] = array('name' => "DNS-Luadns",
	'fields' => array(
		'LUA_Key' => array('name'=>"lua_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		),
		'LUA_Email' => array('name'=>"lua_email",'columnheader'=>"Email",'type'=>"textbox",
			'description' =>"Fill in the API Email Address"
		)
	));
$acme_domain_validation_method['dns_mydnsjp'] = array('name' => "DNS-MyDNS.JP",
	'fields' => array(
		'MYDNSJP_MasterID' => array('name'=>"mydnsjp_masterid",'columnheader'=>"Master ID",'type'=>"textbox",
			'description' =>"Master ID"
		),
		'MYDNSJP_Password' => array('name'=>"mydnsjp_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Password"
		)
	));
$acme_domain_validation_method['dns_namecom'] = array('name' => "DNS-name.com",
	'fields' => array(
		'Namecom_Username' => array('name'=>"namecom_username",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"Fill in the Name.com username"
		),
		'Namecom_Token' => array('name'=>"namecom_token",'columnheader'=>"API Token",'type'=>"textbox",
			'description' =>"Fill in the Name.com API Token"
		)
	));
$acme_domain_validation_method['dns_namecheap'] = array('name' => "DNS-Namecheap",
	'fields' => array(
		'NAMECHEAP_API_KEY' => array('name'=>"namecheap_api_key",'columnheader'=>"API Key",'type'=>"textbox",
			'description' =>"API Key"
		),
		'NAMECHEAP_USERNAME' => array('name'=>"namecheap_username",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"Username"
		)
	));
$acme_domain_validation_method['dns_namesilo'] = array('name' => "DNS-Namesilo",
	'fields' => array(
		'Namesilo_Key' => array('name'=>"namesilo_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Namesilo API Key"
		)
	));
$acme_domain_validation_method['dns_neodigit'] = array('name' => "DNS-Neodigit.net",
	'fields' => array(
		'NEODIGIT_API_TOKEN' => array('name'=>"neodigit_api_token",'columnheader'=>"API Token",'type'=>"textbox",
			'description' =>"API Token"
		)
	));
$acme_domain_validation_method['dns_netcup'] = array('name' => "DNS-netcup.net",
	'fields' => array(
		'NC_Apikey' => array('name'=>"nc_apikey",'columnheader'=>"API Key",'type'=>"textbox",
			'description' =>"API Key"
		),
		'NC_Apipw' => array('name'=>"nc_apipw",'columnheader'=>"API Password",'type'=>"textbox",
			'description' =>"API Password"
		),
		'NC_CID' => array('name'=>"nc_cid",'columnheader'=>"CID",'type'=>"textbox",
			'description' =>"Customer ID/Number"
		)
	));
$acme_domain_validation_method['dns_nsone'] = array('name' => "DNS-NS1 (nsone.com)",
	'fields' => array(
		'NS1_Key' => array('name'=>"ns1_key",'columnheader'=>"API Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		)
	));
$acme_domain_validation_method['dns_ovh'] = array('name' => "DNS-ovh / kimsufi / soyoustart / runabove",
	'fields' => array(
		'OVH_AK' => array('name'=>"ovh_ak",'columnheader'=>"Application Key",'type'=>"textbox",
			'description' =>"Fill in the Application Key"
		),
		'OVH_AS' => array('name'=>"ovh_as",'columnheader'=>"Application Secret",'type'=>"textbox",
			'description' =>"Fill in the Application Secret"
		),
		'OVH_CK' => array('name'=>"ovh_ck",'columnheader'=>"Consumer Key",'type'=>"textbox",
			'description' =>"Fill in the Consumer Key"
		),
		'OVH_END_POINT' => array('name'=>"ovh_end_point",'columnheader'=>"Endpoint",'type'=>"textbox",
			'description' =>"Fill in one of: ovh-eu/ovh-ca/kimsufi-eu/kimsufi-ca/soyoustart-eu/soyoustart-ca/runabove-ca"
		)
	));
$acme_domain_validation_method['dns_pointhq'] = array('name' => "DNS-PointDNS / pointhq.com",
	'fields' => array(
		'PointHQ_Key' => array('name'=>"pointhq_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		),
		'PointHQ_Email' => array('name'=>"pointhq_email",'columnheader'=>"E-mail",'type'=>"textbox",
			'description' =>"Fill in the account E-mail address"
		),
	));
$acme_domain_validation_method['dns_pdns'] = array('name' => "DNS-PowerDNS",
	'fields' => array(
		'PDNS_Url' => array('name'=>"pdns_url",'columnheader'=>"URL",'type'=>"textbox",
			'description' =>"Fill in the URL http://ns.example.com:8081"
		),
		'PDNS_ServerId' => array('name'=>"pdns_serverid",'columnheader'=>"ServerID",'type'=>"textbox",
			'description' =>"Fill in the ServerId localhost"
		),
		'PDNS_Token' => array('name'=>"pdns_token",'columnheader'=>"Token",'type'=>"textbox",
			'description' =>"Fill in the Token 0123456789ABCDEF"
		),
		'PDNS_Ttl' => array('name'=>"pdns_ttl",'columnheader'=>"TTL",'type'=>"textbox",
			'description' =>"Fill in the TTL 60"
		)
	));
$acme_domain_validation_method['dns_selectel'] = array('name' => "DNS-Selectel",
	'fields' => array(
		'SL_Key' => array('name'=>"sl_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Selectel API Key"
		)
	));
$acme_domain_validation_method['dns_servercow'] = array('name' => "DNS-Servercow",
	'fields' => array(
		'SERVERCOW_API_Username' => array('name'=>"servercow_api_username",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"Servercow username"
		),
		'SERVERCOW_API_Password' => array('name'=>"servercow_api_password",'columnheader'=>"Password",'type'=>"textbox",
			'description' =>"Servercow password"
		)
	));
$acme_domain_validation_method['dns_tele3'] = array('name' => "DNS-Tele3.cz",
	'fields' => array(
		'TELE3_Key' => array('name'=>"tele3_key",'columnheader'=>"Key",'type'=>"textbox",
			'description' =>"Key"
		),
		'TELE3_Secret' => array('name'=>"tele3_secret",'columnheader'=>"Secret",'type'=>"textbox",
			'description' =>"Secret"
		)
	));
$acme_domain_validation_method['dns_unoeuro'] = array('name' => "DNS-UnoEuro",
	'fields' => array(
		'UNO_User' => array('name'=>"uno_user",'columnheader'=>"Username",'type'=>"textbox",
			'description' =>"UnoEuro username"
		),
		'UNO_Key' => array('name'=>"uno_key",'columnheader'=>"API Key",'type'=>"textbox",
			'description' =>"UnoEuro API Key"
		)
	));
$acme_domain_validation_method['dns_vscale'] = array('name' => "DNS-vscale.io",
	'fields' => array(
		'VSCALE_API_KEY' => array('name'=>"vscale_api_key",'columnheader'=>"API Key",'type'=>"textbox",
			'description' =>"Fill in the API Key"
		)
	));
$acme_domain_validation_method['dns_yandex'] = array('name' => "DNS-Yandex",
	'fields' => array(
		'PDD_Token' => array('name'=>"pdd_token",'columnheader'=>"PDD Token",'type'=>"textbox",
			'description' =>"Fill in the Yandex PDD Token, generate at <a href=\"https://pddimp.yandex.ru/api2/admin/get_token\">https://pddimp.yandex.ru/api2/admin/get_token</a>"
		)
	));
$acme_domain_validation_method['dns_zonomi'] = array('name' => "DNS-Zonomi",
	'fields' => array(
		'ZM_Key' => array('name'=>"zm_key",'columnheader'=>"API Key",'type'=>"textbox",
			'description' =>"Fill in the Zonomi API Key"
		)
	));
$acme_domain_validation_method['dns_zilore'] = array('name' => "DNS-Zilore",
	'fields' => array(
		'Zilore_Key' => array('name'=>"zilore_key",'columnheader'=>"Zilore API Key",'type'=>"textbox",
			'description' =>"Fill in the Zilore API Key"
		)
	));
$acme_domain_validation_method['anydns'] = array('name' => "notforuser",
	'fields' => array(
		'challengealias' => array('name'=>"challengealias",'columnheader'=>"Enable DNS alias mode",'type'=>"textbox",
			'description' => '(Optional) Adds the --challenge-alias flag to the acme.sh call.<br/>To use a CNAME for _acme-challenge.importantDomain.tld to direct the acme validation to a different (sub)domain _acme-challenge.aliasDomainForValidationOnly.tld, configure the alternate domain here.<br/>More information can be found <a href="https://github.com/Neilpang/acme.sh/wiki/DNS-alias-mode" target="_new">here</a>.'
		),
		'challengedomain' => array('name'=>"challengedomain",'columnheader'=>"Enable DNS domain alias mode",'type'=>"checkbox",
			'description' => '(Optional) Uses the challenge alias value as --challenge-domain instead in the acme.sh call.'
		)
	));

//TODO add more challenge validation types
/* 
$acme_domain_validation_method['http-post'] = array('name' => "http-post",
	'fields' => array(
		'url' => array('name'=>"url",'columnheader'=>"Url",'type'=>"textbox",'size'=>"50",
			'description' =>"Url the challenge file is posted to, the webserver there must store and reply to the request when the acme servers perform the request for the file from /.well-known/acme-challenge/"
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
	$static_output .= "Acme, running acme_custom_php_install_command() DONE\n";
	update_output_window($static_output);
	acme_write_all_certificates();
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
		} elseif (   $certificate['keylength'] == 'custom'
				  && $cert['prv'] != $certificate['keypaste']) {
			echo "\n getCertificatePSK updating custom key";
			$cert['prv'] = $certificate['keypaste']; // (already base64-encoded)
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
								$domain["{$method}nsupdate_key"]
						);
					}
					if (substr($method, 0, 4) == "dns_") {
						$newdomain->setchallengealias($domain["anydns"."challengealias"]);
					}
					$domainstosign[] = $newdomain;
					$envvariables = array();
					if ($method == "dns_namecheap") {
						$envvariables['NAMECHEAP_SOURCEIP'] = find_interface_ip(guess_interface_from_ip(getcurrentdefaultgatewayip('inet')));
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
