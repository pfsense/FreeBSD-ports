<?php
/*
 * squid_reverse.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2012 Martin Fuchs
 * Copyright (c) 2012-2014 Marcello Coutinho
 * Copyright (c) 2013-2015 Gekkenhuis
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

require_once('certs.inc');
require_once("pfsense-utils.inc");
require_once('util.inc');
/* This file is currently only being included in squid.inc and not used separately */
// require_once('squid.inc');

/* Reverse Proxy Server configuration handler */
function squid_resync_reverse() {
	global $config;

	// config file
	if (is_array($config['installedpackages']['squidreversegeneral'])) {
		$settings = $config['installedpackages']['squidreversegeneral']['config'][0];
	} else {
		$settings = array();
	}
	if (is_array($config['installedpackages']['squidreversepeer'])) {
		$reverse_peers = $config['installedpackages']['squidreversepeer']['config'];
	} else {
		$reverse_peers = array();
	}
	if (is_array($config['installedpackages']['squidreverseuri'])) {
		$reverse_maps = $config['installedpackages']['squidreverseuri']['config'];
	} else {
		$reverse_maps = array();
	}
	if (is_array($config['installedpackages']['squidreverseredir'])) {
		$reverse_redir = $config['installedpackages']['squidreverseredir']['config'];
	} else {
		$reverse_redir = array();
	}

	$conf = "# Reverse Proxy settings\n";

	if (isset($settings['reverse_ssl_cert']) && $settings['reverse_ssl_cert'] != "none") {
		$svr_cert = lookup_cert($settings['reverse_ssl_cert']);
		if ($svr_cert != false) {
			if (base64_decode($svr_cert['crt'])) {
				file_put_contents(SQUID_CONFBASE . "/{$settings['reverse_ssl_cert']}.crt", sq_text_area_decode($svr_cert['crt']));
				$reverse_crt = SQUID_CONFBASE . "/{$settings['reverse_ssl_cert']}.crt";
			}
			if (base64_decode($svr_cert['prv'])) {
				file_put_contents(SQUID_CONFBASE . "/{$settings['reverse_ssl_cert']}.key", sq_text_area_decode($svr_cert['prv']));
				$reverse_key = SQUID_CONFBASE . "/{$settings['reverse_ssl_cert']}.key";
			}
		}
	}

	if (!empty($settings['reverse_int_ca'])) {
		file_put_contents(SQUID_CONFBASE . "/{$settings['reverse_ssl_cert']}.crt", "\n" . sq_text_area_decode($settings['reverse_int_ca']), FILE_APPEND | LOCK_EX);
	}

	if (isset($settings['reverse_check_clientca']) && $settings['reverse_check_clientca'] == "on") {
		if (isset($settings['reverse_ssl_clientca']) && $settings['reverse_ssl_clientca'] != 'none') {
			$clientca_cert = lookup_ca($settings['reverse_ssl_clientca']);
			$clientca_opts = '';
			if ($clientca_cert != false) {
				if (base64_decode($clientca_cert['crt'])) {
					file_put_contents(SQUID_CONFBASE . "/{$settings['reverse_ssl_clientca']}.crt", sq_text_area_decode($clientca_cert['prv']));
					$clientca_opts = "clientca=" . SQUID_CONFBASE . "/{$settings['reverse_ssl_clientca']}.crt";
				}
				if (base64_decode($clientca_cert['prv'])) {
					file_put_contents(SQUID_CONFBASE . "/{$settings['reverse_ssl_clientca']}.crt", "\n" . sq_text_area_decode($clientca_cert['crt']), FILE_APPEND | LOCK_EX);
				}
			}
		}
		if (isset($settings['reverse_ssl_clientcrl']) && $settings['reverse_ssl_clientcrl'] != 'none') {
			$crl = lookup_crl($settings['reverse_ssl_clientcrl']);
			crl_update($crl);
			if ($crl != false) {
				if (base64_decode($crl['text'])) {
					file_put_contents(SQUID_CONFBASE . "/{$settings['reverse_ssl_clientcrl']}.crl", sq_text_area_decode($crl['text']));
					$clientca_opts .= " crlfile=" . SQUID_CONFBASE . "/{$settings['reverse_ssl_clientcrl']}.crl";
					$sslflags_https_port_iface = "sslflags=VERIFY_CRL";
				}
			}
		}
	}

	$ifaces = ($settings['reverse_interface'] ? $settings['reverse_interface'] : 'wan');
	$real_ifaces = array();

	// set HTTP port and defsite
	$http_port = (!is_port($settings['reverse_http_port']) ? "80" : $settings['reverse_http_port']);
	$http_defsite = (empty($settings['reverse_http_defsite']) ? $settings['reverse_external_fqdn'] : $settings['reverse_http_defsite']);

	// set HTTPS port and defsite
	$https_port = (!is_port($settings['reverse_https_port']) ? "443" : $settings['reverse_https_port']);
	$https_defsite = (empty($settings['reverse_https_defsite']) ? $settings['reverse_external_fqdn'] : $settings['reverse_https_defsite']);

	// Ignore Internal Certificate Validation
	$sslflags_cache_peer = ($settings['reverse_ignore_ssl_valid'] == "on" ? "sslflags=DONT_VERIFY_PEER" : "");

	// Reverse Proxy HTTPS security settings
	if (isset($settings["dhparams_size"])) {
		if ($settings['dhparams_size'] == '2048' && file_exists("/etc/dh-parameters.2048")) {
			$dhparams = "dhparams=/etc/dh-parameters.2048";
		} else if ($settings['dhparams_size'] == '4096' && file_exists("/etc/dh-parameters.4096")) {
			$dhparams = "dhparams=/etc/dh-parameters.4096";
		}
	} else if (file_exists("/etc/dh-parameters.2048")) {
		// Fallback dhparams option
		$dhparams = "dhparams=/etc/dh-parameters.2048";
	}

	// Without DHParams file and settings below squid will not work correct
	if (isset($dhparams) && !empty($dhparams)) {
		if ($settings['reverse_compatibility_mode'] == 'modern') {
			// Modern
			$ciphers = "cipher=ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!3DES:!MD5:!PSK";
			$options = "options=NO_SSLv2,NO_SSLv3,NO_TLSv1,SINGLE_DH_USE,CIPHER_SERVER_PREFERENCE";
		} else if ($settings['reverse_compatibility_mode'] == 'intermediate') {
			// Intermediate
			$ciphers = "cipher=ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:ECDHE-RSA-DES-CBC3-SHA:ECDHE-ECDSA-DES-CBC3-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:AES:CAMELLIA:DES-CBC3-SHA:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!MD5:!PSK:!aECDH:!EDH-DSS-DES-CBC3-SHA:!EDH-RSA-DES-CBC3-SHA:!KRB5-DES-CBC3-SHA";
			$options = "options=NO_SSLv2,NO_SSLv3,SINGLE_DH_USE,CIPHER_SERVER_PREFERENCE";
		}
	}

	if (!empty($settings['disable_session_reuse'])) {
		// reverse_ssl_clientcrl doesn't set the sslflags on
		// reverse_ip https_port so we cannot use the same variable
		$sslflags_https_port_reverse = "sslflags=NO_SESSION_REUSE";
	
		// Check or the sslflags for the interface https_port
		// are already set by reverse_ssl_clientcrl setting
		if(!empty($sslflags_https_port_iface)) {
			// Append the sslflags
			$sslflags_https_port_iface .= ",NO_SESSION_REUSE";
		} else {
			// Set the sslflags
			$sslflags_https_port_iface = $sslflags_https_port_reverse;
		}
	}

	foreach (explode(",", $ifaces) as $i => $iface) {
		$real_ifaces[] = squid_get_real_interface_address($iface);
		if ($real_ifaces[$i][0]) {
			//HTTP
			if ((!empty($settings['reverse_http'])) || ($settings['reverse_owa_autodiscover'] == 'on')) {
				$conf .= "http_port {$real_ifaces[$i][0]}:{$http_port} accel defaultsite={$http_defsite} vhost\n";
			}
			//HTTPS
			if (!empty($settings['reverse_https'])) {
				// Squid will fail when the line length exceeds 1024 characters, this can happen because of the long ciphersuite so break the line
				$conf .= "https_port {$real_ifaces[$i][0]}:{$https_port} accel cert={$reverse_crt} key={$reverse_key} {$clientca_opts} {$sslflags_https_port_iface} {$dhparams} {$ciphers} \\\n";
				$conf .= " {$options} defaultsite={$https_defsite} vhost\n";
				$conf .= "\n";
			}
		}
	}

	if (!empty($settings['reverse_ip'])) {
		$reverse_ip = explode(";", ($settings['reverse_ip']));
		foreach ($reverse_ip as $reip) {
			//Take care of IPv6 IP(s) configured in User Defined Reverse Proxy IPs
			if (is_ipaddrv6($reip)) {
				$reip = "[{$reip}]";
			}
			//HTTP
			if ((!empty($settings['reverse_http'])) || ($settings['reverse_owa_autodiscover'] == 'on')) {
				$conf .= "http_port {$reip}:{$http_port} accel defaultsite={$http_defsite} vhost\n";
			}
			//HTTPS
			if (!empty($settings['reverse_https'])) {
				// Squid will fail when the line length exceeds 1024 characters, this can happen because of the long ciphersuite so break the line
				$conf .= "https_port {$reip}:{$https_port} accel cert={$reverse_crt} key={$reverse_key} {$sslflags_https_port_reverse} {$dhparams} {$ciphers} \\\n";
				$conf .= " {$options} defaultsite={$https_defsite} vhost\n";
				$conf .= "\n";
			}
		}
	}

	// peers
	if ($settings['reverse_owa'] == 'on') {
		if (!empty($settings['reverse_owa_ip'])) {
			$reverse_owa_ip = explode(";", ($settings['reverse_owa_ip']));
			$casnr = 0;
			foreach ($reverse_owa_ip as $reowaip) {
				$casnr++;
				$conf .= "cache_peer {$reowaip} parent 443 0 proxy-only no-query no-digest originserver login=PASSTHRU connection-auth=on ssl {$sslflags_cache_peer} front-end-https=on name=OWA_HOST_443_{$casnr}_pfs\n";
				$conf .= "cache_peer {$reowaip} parent 80 0 proxy-only no-query no-digest originserver login=PASSTHRU connection-auth=on name=OWA_HOST_80_{$casnr}_pfs\n";
			}
		}
	}

	$active_peers = array();
	if (is_array($reverse_peers)) {
		foreach ($reverse_peers as $rp) {
			if ($rp['enable'] == "on" && $rp['name'] != "" && $rp['ip'] != "" && $rp['port'] != "") {
				$conf_peer = "#{$rp['description']}\n";
				$conf_peer .= "cache_peer {$rp['ip']} parent {$rp['port']} 0 proxy-only no-query no-digest originserver login=PASSTHRU connection-auth=on round-robin ";
				if ($rp['protocol'] == 'HTTPS') {
					$conf_peer .= "ssl {$sslflags_cache_peer} front-end-https=auto ";
				}
				$conf_peer .= "name=rvp_{$rp['name']}\n\n";

				// add peer only if reverse proxy is enabled for http
				if ($rp['protocol'] == 'HTTP' && $settings['reverse_http'] == "on") {
					$conf .= $conf_peer;
					array_push($active_peers, $rp['name']);
				}
				// add peer only if if reverse proxy is enabled for https
				if ($rp['protocol'] == 'HTTPS' && $settings['reverse_https'] == "on") {
					if (!in_array($rp['name'], $active_peers)) {
						$conf .= $conf_peer;
						array_push($active_peers, $rp['name']);
					}
				}
			}
		}
	}

	// redirects
	if (is_array($reverse_redir)) {
		foreach ($reverse_redir as $rdr) {
			if ($rdr['enable'] == "on" && $rdr['name'] != "" && $rdr['pathregex'] != "" && $rdr['redirurl'] != "") {
				$conf_rdr = "# Redirect: {$rdr['description']}\n";

				if (is_array($rdr['row'])) {
					foreach ($rdr['row'] as $uri) {
						$conf_rdr .= "acl rdr_dst_{$rdr['name']} dstdomain {$uri['uri']}\n";
					}
				}

				$conf_rdr .= "acl rdr_path_{$rdr['name']} urlpath_regex {$rdr['pathregex']}\n";
				$conf_rdr .= "deny_info {$rdr['redirurl']} rdr_path_{$rdr['name']}\n";

				foreach (explode(',', $rdr['protocol']) as $rdr_protocol) {
					if ($rdr_protocol == "HTTP") {
						$conf_rdr .= "http_access deny HTTP rdr_dst_{$rdr['name']} rdr_path_{$rdr['name']}\n";
					}

					if ($rdr_protocol == "HTTPS") {
						$conf_rdr .= "http_access deny HTTPS rdr_dst_{$rdr['name']} rdr_path_{$rdr['name']}\n";
					}
				}

				$conf_rdr .= "\n";
			}

			$conf .= $conf_rdr;
		}
	}

	// ACLs and mappings

	//create an empty owa_dirs to populate based on user selected options
	$owa_dirs = array();
	if (($settings['reverse_owa'] == 'on') && $settings['reverse_https'] == "on") {
		if (!empty($settings['reverse_owa_ip'])) {
			array_push($owa_dirs, 'owa', 'exchange', 'public', 'exchweb', 'ecp', 'OAB');
			if ($settings['reverse_owa_activesync']) {
				array_push($owa_dirs, 'Microsoft-Server-ActiveSync');
			}
			if ($settings['reverse_owa_rpchttp']) {
				array_push($owa_dirs, 'rpc/rpcproxy.dll', 'rpcwithcert/rpcproxy.dll');
			}
			if ($settings['reverse_owa_mapihttp']) {
				array_push($owa_dirs, 'mapi');
			}
			if ($settings['reverse_owa_webservice']) {
				array_push($owa_dirs, 'EWS');
			}
		}
		if (is_array($owa_dirs)) {
			foreach ($owa_dirs as $owa_dir) {
				$conf .= "acl OWA_URI_pfs url_regex -i ^https://{$settings['reverse_external_fqdn']}/$owa_dir.*$\n";
			}
		}

		if (($settings['reverse_owa'] == 'on') && (!empty($settings['reverse_owa_ip'])) && ($settings['reverse_owa_autodiscover'] == 'on')) {
			if (substr_count($settings['reverse_external_fqdn'], ".") > 1) {
				$reverse_external_domain = strstr($settings['reverse_external_fqdn'], '.');
			} else {
				$reverse_external_domain = ".{$settings['reverse_external_fqdn']}";
			}
			$conf .= "acl OWA_URI_pfs url_regex -i ^http://{$settings['reverse_external_fqdn']}/AutoDiscover/AutoDiscover.xml\n";
			$conf .= "acl OWA_URI_pfs url_regex -i ^https://{$settings['reverse_external_fqdn']}/AutoDiscover/AutoDiscover.xml\n";
			$conf .= "acl OWA_URI_pfs url_regex -i ^http://autodiscover{$reverse_external_domain}/AutoDiscover/AutoDiscover.xml\n";
			$conf .= "acl OWA_URI_pfs url_regex -i ^https://autodiscover{$reverse_external_domain}/AutoDiscover/AutoDiscover.xml\n";
		}
	}
	//$conf .= "ssl_unclean_shutdown on";
	if (is_array($reverse_maps)) {
		foreach ($reverse_maps as $rm) {
			if ($rm['enable'] == "on" && $rm['name'] != "" && $rm['peers'] != "" && is_array($rm['row'])) {
				foreach ($rm['row'] as $uri) {
					$url_regex = ($uri['uri'] == '' ? $settings['reverse_external_fqdn'] : $uri['uri'] );
					//$conf .= "acl rvm_{$rm['name']} url_regex -i {$uri['uri']}{$url_regex}.*$\n";
					$conf .= "acl rvm_{$rm['name']} url_regex -i {$url_regex}\n";
					if ($rm['name'] != $last_rm_name) {
						$cache_peer_never_direct_conf .= "never_direct allow rvm_{$rm['name']}\n";
						$http_access_conf .= "http_access allow rvm_{$rm['name']}\n";
						foreach (explode(',', $rm['peers']) as $map_peer) {
							if (in_array($map_peer, $active_peers)) {
								$cache_peer_allow_conf .= "cache_peer_access rvp_{$map_peer} allow rvm_{$rm['name']}\n";
								$cache_peer_deny_conf .= "cache_peer_access rvp_{$map_peer} deny allsrc\n";
							}
						}
						$last_rm_name = $rm['name'];
					}
				}
			}
		}
	}

	// access
	if ($settings['reverse_owa'] == 'on' && !empty($settings['reverse_owa_ip']) && $settings['reverse_https'] == "on") {

		for ($cascnt = 1; $cascnt < $casnr + 1; $cascnt++) {
			$conf .= "cache_peer_access OWA_HOST_443_{$cascnt}_pfs allow OWA_URI_pfs\n";
			$conf .= "cache_peer_access OWA_HOST_80_{$cascnt}_pfs allow OWA_URI_pfs\n";
			$conf .= "cache_peer_access OWA_HOST_443_{$cascnt}_pfs deny allsrc\n";
			$conf .= "cache_peer_access OWA_HOST_80_{$cascnt}_pfs deny allsrc\n";
		}

		$conf .= "never_direct allow OWA_URI_pfs\n";
		$conf .= "http_access allow OWA_URI_pfs\n";
	}

	$conf .= $cache_peer_allow_conf . $cache_peer_deny_conf . $cache_peer_never_direct_conf . $http_access_conf . "\n";

	if (!empty($settings['deny_info_tcp_reset'])) {
		$conf .= "deny_info TCP_RESET allsrc\n";
	}

	return $conf;
}

/* Refresh Client Certificate Revocation List */
function squid_refresh_crl() {
	global $config;

	if (is_array($config['installedpackages']['squidreversegeneral'])) {
		$settings = $config['installedpackages']['squidreversegeneral']['config'][0];
	} else {
		$settings = array();
	}

	if (isset($settings['reverse_check_clientca']) && $settings['reverse_check_clientca'] == "on" && isset($settings['reverse_ssl_clientcrl']) && $settings['reverse_ssl_clientcrl'] != 'none') {
		$crl = lookup_crl($settings['reverse_ssl_clientcrl']);
		crl_update($crl);
		if ($crl != false) {
			if (base64_decode($crl['text'])) {
				file_put_contents(SQUID_CONFBASE . "/{$settings['reverse_ssl_clientcrl']}.crl", sq_text_area_decode($crl['text']));
			}
		}
	}
}

/* Check whether Squid reverse proxy is enabled */
function squid_reverse_enabled() {
	global $config, $reverse_proxy_enabled;
	$reverse_proxy_enabled = false;

	if (is_array($config['installedpackages']['squidreversegeneral']['config'])) {
		// check whether HTTP or HTTPS reverse proxy is enabled ...
		if ($config['installedpackages']['squidreversegeneral']['config'][0]['reverse_http'] == "on" ||
		    $config['installedpackages']['squidreversegeneral']['config'][0]['reverse_https'] == "on") {
			// ... and has at least one reverse interface configured
			if (!empty($config['installedpackages']['squidreversegeneral']['config'][0]['reverse_interface'])) {
				$reverse_proxy_enabled = true;
			}
		}
	}

	return $reverse_proxy_enabled;
}

/* Migrate reverse proxy configuration from old Squid package versions */
function squid_reverse_upgrade_config() {
	global $config;
	if (is_array($config['installedpackages']['squidreverse'])) {
		$old_reverse_settings = $config['installedpackages']['squidreverse']['config'][0];

		// settings
		if (!is_array($config['installedpackages']['squidreversegeneral'])) {
			$config['installedpackages']['squidreversegeneral']['config'][0] = $old_reverse_settings;
			unset($config['installedpackages']['squidreversegeneral']['config'][0]['reverse_cache_peer']);
			unset($config['installedpackages']['squidreversegeneral']['config'][0]['reverse_uri']);
			unset($config['installedpackages']['squidreversegeneral']['config'][0]['reverse_acl']);
		}

		// peers
		if (!is_array($config['installedpackages']['squidreversepeer'])) {
			foreach (explode("\n", sq_text_area_decode($old_reverse_settings['reverse_cache_peer'])) as $cache_peers) {
				foreach (explode(";", $cache_peers) as $cache_peer) {
					$config['installedpackages']['squidreversepeer']['config'][] = array(
						'description' => 'migrated',
						'enable' => 'on',
						'name' => $cache_peer[0],
						'port' => $cache_peer[1],
						'protocol' => $cache_peer[2]
					);
				}
			}
		}

		// mappings
		if (!is_array($config['installedpackages']['squidreverseuri'])) {
			foreach (explode("\n", sq_text_area_decode($old_reverse_settings['reverse_acl'])) as $acls) {
				foreach (explode(";", $acls) as $acl) {
					array_push(${'peer_'.$acl[0]}, $acl[1]);
				}
			}
			foreach (explode("\n", sq_text_area_decode($old_reverse_settings['reverse_uri'])) as $uris) {
				foreach (explode(";", $uris) as $uri) {
					$peer_list = (is_array(${'peer_' . $uri[0]}) ? implode(",", ${'peer_' . $uri[0]}) : "");
					$config['installedpackages']['squidreverseuri']['config'][] = array(
						'description' => 'migrated',
						'enable' => 'on',
						'name' => $uri[0],
						'uri' => $uri[1],
						'vhost' => $uri[2],
						'peers' => $peer_list
					);
				}
			}
		}
	}
}

/* Reverse Proxy Server input validation */
function squid_validate_reverse($post, &$input_errors) {
	global $config;

	/* Manually refresh client CRL */
	if ($post['refresh_crl'] == 'Refresh CRL') {
		log_error("[squid] Client Certificate Revocation List refresh forced via GUI. Refreshing now...");
		squid_refresh_crl();
	}

	if ($post['reverse_http'] == 'on' || $post['reverse_https'] == 'on') {
		if (empty($post['reverse_interface'])) {
			$input_errors[] = "You must select at least one interface under 'Reverse Proxy Interface(s)' to enable HTTP Reverse Proxy.";
		}
		$fqdn = trim($post['reverse_external_fqdn']);
		if (empty($fqdn) || !is_domain($fqdn)) {
			$input_errors[] = "'External FQDN' field must contain a valid domain name.";
		}
		unset($fqdn);
	}

	if (!empty($post['reverse_ip'])) {
		$reverse_ip = explode(";", ($post['reverse_ip']));
		foreach ($reverse_ip as $reip) {
			if (!is_ipaddr_configured(trim($reip))) {
				$input_errors[] = "You must enter a valid, locally configured IP address in the 'User Defined Reverse Proxy IPs' field. '$reip' is invalid.";
			}
		}
		unset($reverse_ip);
	}

	if ($post['reverse_http'] == 'on') {
		$port = trim($post['reverse_http_port']);
		preg_match("/(\d+)/", shell_exec("/sbin/sysctl net.inet.ip.portrange.reservedhigh"), $portrange);
		if (!is_port($port)) {
			$input_errors[] = "'Reverse HTTP port' must contain a valid port number.";
		}
		if (is_port($port) && $port <= $portrange[1]) {
			$input_errors[] = "'Reverse HTTP port' must contain a port number higher than net.inet.ip.portrange.reservedhigh sysctl value({$portrange[1]}).";
			$input_errors[] = "To listen on low ports, change net.inet.ip.portrange.reservedhigh sysctl value to 0 in System: Advanced: System Tunables and restart Squid daemon.";
		}
		unset($port, $portrange);
	}

	if ($post['reverse_https'] == 'on') {
		$port = trim($post['reverse_https_port']);
		preg_match("/(\d+)/", shell_exec("/sbin/sysctl net.inet.ip.portrange.reservedhigh"), $portrange);
		if (!is_port($port)) {
			$input_errors[] = "'Reverse HTTPS port' must contain a valid port number.";
		}
		if (!empty($port) && is_port($port) && $port <= $portrange[1]) {
			$input_errors[] = "'Reverse HTTPS port' must contain a port number higher than net.inet.ip.portrange.reservedhigh sysctl value({$portrange[1]}).";
			$input_errors[] = "To listen on low ports, change net.inet.ip.portrange.reservedhigh sysctl value to 0 in System: Advanced: System Tunables and restart Squid daemon.";
		}
		unset($port, $portrange);

		if ($post['reverse_ssl_cert'] == 'none') {
			$input_errors[] = "A valid certificate for the external interface must be selected when 'HTTPS Reverse Proxy' is enabled.";
		}

		if ($post['reverse_check_clientca'] == 'on') {
			if ($post['reverse_ssl_clientca'] == 'none') {
				$input_errors[] = "A valid 'Client Certificate CA' must be selected when 'Check Client Certificate' is enabled";
			}
		}
	}

	if ($post['reverse_owa'] == 'on') {
		if ($post['reverse_https'] != 'on') {
			$input_errors[] = "You have to enable HTTPS Reverse Proxy to enable OWA support.";
		}

		if (!empty($post['reverse_owa_ip'])) {
			$reowaips = explode(";", ($post['reverse_owa_ip']));
			foreach ($reowaips as $reowaip) {
				if (!is_ipaddr(trim($reowaip))) {
					$input_errors[] = "You must enter a valid IP address in the 'CAS-Array / OWA Frontend IP Address(es)' field. '$reowaip' is invalid.";
				}
			}
			unset($reowaips);
		} else {
			$input_errors[] = "You must enter at least one valid IP address in the 'CAS-Array / OWA Frontend IP Address(es)' field.";
		}
	}

	$contents = $post['reverse_cache_peer'];
	if (!empty($contents)) {
		$defs = explode("\r\n", ($contents));
		foreach ($defs as $def) {
			$cfg = explode(";", ($def));
			if (!is_ipaddr($cfg[1])) {
				$input_errors[] = "Please choose a valid IP in the cache peer configuration.";
			}
			if (!is_port($cfg[2])) {
				$input_errors[] = "Please choose a valid port in the cache peer configuration.";
			}
			if (($cfg[3] != 'HTTPS') && ($cfg[3] != 'HTTP')) {
				$input_errors[] = "Please choose HTTP or HTTPS in the cache peer configuration.";
			}
		}
		unset($cfg, $defs);
	}
	unset($contents);
}

?>
