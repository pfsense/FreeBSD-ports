<?php
/* $Id$ */
/*
	squid_reverse.inc
	Copyright (C) 2012 Martin Fuchs
	Copyright (C) 2012-2014 Marcello Coutinho
	Copyright (C) 2013 Gekkenhuis
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

function squid_resync_reverse() {
	global $config;

	//CONFIG FILE
	if (is_array($config['installedpackages']['squidreversegeneral']))
		$settings = $config['installedpackages']['squidreversegeneral']['config'][0];
	if (is_array($config['installedpackages']['squidreversepeer']))
		$reverse_peers=$config['installedpackages']['squidreversepeer']['config'];
	if (is_array($config['installedpackages']['squidreverseuri']))
		$reverse_maps=$config['installedpackages']['squidreverseuri']['config'];
	if (is_array($config['installedpackages']['squidreverseredir']))
		$reverse_redir=$config['installedpackages']['squidreverseredir']['config'];

	$conf = "# Reverse Proxy settings\n";

	if (isset($settings["reverse_ssl_cert"]) && $settings["reverse_ssl_cert"] != "none") {
		$svr_cert = lookup_cert($settings["reverse_ssl_cert"]);
		if ($svr_cert != false) {
			if (base64_decode($svr_cert['crt'])) {
				file_put_contents(SQUID_CONFBASE . "/{$settings["reverse_ssl_cert"]}.crt",sq_text_area_decode($svr_cert['crt']));
				$reverse_crt = SQUID_CONFBASE . "/{$settings["reverse_ssl_cert"]}.crt";
			}
			if (base64_decode($svr_cert['prv'])) {
				file_put_contents(SQUID_CONFBASE . "/{$settings["reverse_ssl_cert"]}.key",sq_text_area_decode($svr_cert['prv']));
				$reverse_key = SQUID_CONFBASE . "/{$settings["reverse_ssl_cert"]}.key";
			}
		}
	}

	if (!empty($settings['reverse_int_ca']))
		file_put_contents(SQUID_CONFBASE . "/{$settings["reverse_ssl_cert"]}.crt","\n" . sq_text_area_decode($settings['reverse_int_ca']),FILE_APPEND | LOCK_EX);

	$ifaces = ($settings['reverse_interface'] ? $settings['reverse_interface'] : 'wan');
	$real_ifaces = array();

	// set HTTP port and defsite
	$http_port=(empty($settings['reverse_http_port'])?"80":$settings['reverse_http_port']);
	$http_defsite=(empty($settings['reverse_http_defsite'])?$settings['reverse_external_fqdn']:$settings['reverse_http_defsite']);

	// set HTTPS port and defsite
	$https_port=(empty($settings['reverse_https_port'])?"443":$settings['reverse_https_port']);
	$https_defsite=(empty($settings['reverse_https_defsite'])?$settings['reverse_external_fqdn']:$settings['reverse_https_defsite']);

	foreach (explode(",", $ifaces) as $i => $iface) {
		$real_ifaces[] = squid_get_real_interface_address($iface);
		if ($real_ifaces[$i][0]) {
			//HTTP
			if (!empty($settings['reverse_http']) OR ($settings['reverse_owa_autodiscover'] == 'on'))
				$conf .= "http_port {$real_ifaces[$i][0]}:{$http_port} accel defaultsite={$http_defsite} vhost\n";
			//HTTPS
			if (!empty($settings['reverse_https']))
				$conf .= "https_port {$real_ifaces[$i][0]}:{$https_port} accel cert={$reverse_crt} key={$reverse_key} defaultsite={$https_defsite} vhost\n";
		}
	}

	if (!empty($settings['reverse_ip'])) {
		$reverse_ip = explode(";", ($settings['reverse_ip']));
		foreach ($reverse_ip as $reip) {
			//HTTP
			if (!empty($settings['reverse_http']) OR ($settings['reverse_owa_autodiscover'] == 'on'))
				$conf .= "http_port {$reip}:{$http_port} accel defaultsite={$http_defsite} vhost\n";
			//HTTPS
			if (!empty($settings['reverse_https']))
				$conf .= "https_port {$reip}:{$https_port} accel cert={$reverse_crt} key={$reverse_key} defaultsite={$https_defsite} vhost\n";
		}
	}

	//PEERS
	if (($settings['reverse_owa'] == 'on') && (!empty($settings['reverse_owa_ip']))) {
		if (!empty($settings['reverse_owa_ip'])) {
			$reverse_owa_ip = explode(";", ($settings['reverse_owa_ip']));
			$casnr = 0;
			foreach ($reverse_owa_ip as $reowaip) {
				$casnr++;
				$conf .= "cache_peer {$reowaip} parent 443 0 proxy-only no-query no-digest originserver login=PASSTHRU connection-auth=on ssl sslflags=DONT_VERIFY_PEER front-end-https=on name=OWA_HOST_443_{$casnr}_pfs\n";
				$conf .= "cache_peer {$reowaip} parent 80 0 proxy-only no-query no-digest originserver login=PASSTHRU connection-auth=on name=OWA_HOST_80_{$casnr}_pfs\n";
			}
		}
	}

	$active_peers=array();
	if (is_array($reverse_peers)) {
		foreach ($reverse_peers as $rp) {
		if ($rp['enable'] =="on" && $rp['name'] !="" && $rp['ip'] !="" && $rp['port'] !="") {
			$conf_peer = "#{$rp['description']}\n";
			$conf_peer .= "cache_peer {$rp['ip']} parent {$rp['port']} 0 proxy-only no-query no-digest originserver login=PASSTHRU connection-auth=on round-robin ";
			if ($rp['protocol'] == 'HTTPS')
				$conf_peer .= "ssl sslflags=DONT_VERIFY_PEER front-end-https=auto ";
			$conf_peer .= "name=rvp_{$rp['name']}\n\n";

			// add peer only if reverse proxy is enabled for http
			if ($rp['protocol'] == 'HTTP' && $settings['reverse_http'] =="on") {
				$conf .= $conf_peer;
				array_push($active_peers,$rp['name']);
			}
			// add peer only if if reverse proxy is enabled for https
			if ($rp['protocol'] == 'HTTPS' && $settings['reverse_https'] =="on") {
				if (!in_array($rp['name'],$active_peers)) {
					$conf .= $conf_peer;
					array_push($active_peers,$rp['name']);
					}
				}
			}
		}
	}

	//REDIRECTS
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

	//ACLS and MAPPINGS

	//create an empty owa_dirs to populate based on user selected options
	$owa_dirs=array();
	if (($settings['reverse_owa'] == 'on') && $settings['reverse_https'] =="on") {
		if (!empty($settings['reverse_owa_ip'])) {
			array_push($owa_dirs,'owa','exchange','public','exchweb','ecp','OAB');
			if ($settings['reverse_owa_activesync'])
				array_push($owa_dirs,'Microsoft-Server-ActiveSync');
			if ($settings['reverse_owa_rpchttp'])
				array_push($owa_dirs,'rpc/rpcproxy.dll','rpcwithcert/rpcproxy.dll');
			if ($settings['reverse_owa_mapihttp'])
				array_push($owa_dirs,'mapi');
			if ($settings['reverse_owa_webservice'])
				array_push($owa_dirs,'EWS');
		}
		if (is_array($owa_dirs)) {
			foreach ($owa_dirs as $owa_dir)
				$conf .= "acl OWA_URI_pfs url_regex -i ^https://{$settings['reverse_external_fqdn']}/$owa_dir.*$\n";
		}

		if (($settings['reverse_owa'] == 'on') && (!empty($settings['reverse_owa_ip'])) && ($settings['reverse_owa_autodiscover'] == 'on')) {
			$reverse_external_domain = strstr($settings['reverse_external_fqdn'], '.');
			$conf .= "acl OWA_URI_pfs url_regex -i ^http://{$settings['reverse_external_fqdn']}/AutoDiscover/AutoDiscover.xml\n";
			$conf .= "acl OWA_URI_pfs url_regex -i ^https://{$settings['reverse_external_fqdn']}/AutoDiscover/AutoDiscover.xml\n";
			$conf .= "acl OWA_URI_pfs url_regex -i ^http://autodiscover{$reverse_external_domain}/AutoDiscover/AutoDiscover.xml\n";
			$conf .= "acl OWA_URI_pfs url_regex -i ^https://autodiscover{$reverse_external_domain}/AutoDiscover/AutoDiscover.xml\n";
		}
	}
	//$conf .= "ssl_unclean_shutdown on";
	if (is_array($reverse_maps)) {
		foreach ($reverse_maps as $rm) {
			if ($rm['enable'] == "on" && $rm['name']!="" && $rm['peers']!="" && is_array($rm['row'])) {
				foreach ($rm['row'] as $uri) {
					$url_regex=($uri['uri'] == '' ? $settings['reverse_external_fqdn'] : $uri['uri'] );
					//$conf .= "acl rvm_{$rm['name']} url_regex -i {$uri['uri']}{$url_regex}.*$\n";
					$conf .= "acl rvm_{$rm['name']} url_regex -i {$url_regex}\n";
					if ($rm['name'] != $last_rm_name) {
						$cache_peer_never_direct_conf .= "never_direct allow rvm_{$rm['name']}\n";
						$http_access_conf .= "http_access allow rvm_{$rm['name']}\n";
						foreach (explode(',',$rm['peers']) as $map_peer) {
							if (in_array($map_peer,$active_peers)) {
								$cache_peer_allow_conf .= "cache_peer_access rvp_{$map_peer} allow rvm_{$rm['name']}\n";
								$cache_peer_deny_conf .= "cache_peer_access rvp_{$map_peer} deny allsrc\n";
							}
						}
						$last_rm_name=$rm['name'];
					}
				}
			}
		}
	}

	//ACCESS
	if ($settings['reverse_owa'] == 'on' && !empty($settings['reverse_owa_ip']) && $settings['reverse_https'] =="on") {

		for ($cascnt=1;$cascnt<$casnr+1;$cascnt++) {
			$conf .= "cache_peer_access OWA_HOST_443_{$cascnt}_pfs allow OWA_URI_pfs\n";
			$conf .= "cache_peer_access OWA_HOST_80_{$cascnt}_pfs allow OWA_URI_pfs\n";
			$conf .= "cache_peer_access OWA_HOST_443_{$cascnt}_pfs deny allsrc\n";
			$conf .= "cache_peer_access OWA_HOST_80_{$cascnt}_pfs deny allsrc\n";
		}

		$conf .= "never_direct allow OWA_URI_pfs\n";
		$conf .= "http_access allow OWA_URI_pfs\n";
	}

	$conf .= $cache_peer_allow_conf.$cache_peer_deny_conf.$cache_peer_never_direct_conf.$http_access_conf."\n";

	if (!empty($settings['deny_info_tcp_reset']))
		$conf .= "deny_info TCP_RESET allsrc\n";

	return $conf;
}

?>
