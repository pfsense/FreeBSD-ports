<?php
/*
 * zabbix-proxy.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Danilo G. Baio
 * Copyright (c) 2013 Marcello Coutinho
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

require_once("util.inc");
require_once("functions.inc");
require_once("pkg-utils.inc");
require_once("globals.inc");
require_once("certs.inc");

define('ZABBIX_PROXY_BASE', '/usr/local');
define('ZABBIX_VERSION', %%ZABBIXVERSION%%);

function php_deinstall_zabbix_proxy() {
	unlink_if_exists(ZABBIX_PROXY_BASE . "/etc/zabbix%%ZABBIXVERSION%%/zabbix_proxy.conf");
	unlink_if_exists("/var/log/zabbix-proxy/zabbix_proxy.log");
	unlink_if_exists("/var/run/zabbix-proxy/zabbix_proxy.pid");

	if (is_dir("/var/log/zabbix-proxy")) {
		mwexec("/bin/rm -rf /var/log/zabbix-proxy/");
	}
	if (is_dir("/var/run/zabbix-proxy")) {
		mwexec("/bin/rm -rf /var/run/zabbix-proxy/");
	}
	if (is_dir("/var/db/zabbix-proxy")) {
		mwexec("/bin/rm -rf /var/db/zabbix-proxy/");
	}
}

function validate_input_zabbix_proxy($post, &$input_errors) {
	if (isset($post['proxyenabled'])) {
		if (!preg_match("/\w+/", $post['server'])) {
			$input_errors[] = "'Server' field is required.";
		}

		if (!is_numericint($post['serverport'])) {
			$input_errors[] = "'Server Port' value is not numeric.";
		} elseif ($post['serverport'] < 1 || $post['serverport'] > 65535) {
			$input_errors[] = "Enter a valid value for 'Server Port'.";
		}

		if (!preg_match("/\w+/", $post['hostname'])) {
			$input_errors[] = "'Hostname' field is required.";
		}

		if ($post['listenport'] != '') {
			if (!is_numericint($post['listenport'])) {
				$input_errors[] = "'Listen Port' value is not numeric.";
			} elseif ($post['listenport'] < 1 || $post['listenport'] > 65535) {
				$input_errors[] = "You must enter a valid value for 'Listen Port'.";
			}
		}

		if (!is_numericint($post['configfrequency'])) {
			$input_errors[] = "'Config Frequency' value is not numeric.";
		}

		if (!is_numericint($post['trappertimeout'])) {
			$input_errors[] = "'Trapper Timeout' value is not numeric.";
		} elseif ($post['trappertimeout'] < 1 || $post['trappertimeout'] > 300) {
			$input_errors[] = "Enter a valid value for 'Trapper Timeout'.";
		}

		if (!is_numericint($post['starttrappers'])) {
			$input_errors[] = "'Start Trappers' value is not numeric.";
		} elseif ($post['starttrappers'] > 1000) {
			$input_errors[] = "Enter a valid value for 'Start Trappers'.";
		}
	}
}

function sync_package_zabbix_proxy() {
	global $config, $g;


	// Check zabbix proxy config
	if (isset($config['installedpackages']['%%ZABBIXUNIQNAME%%']) &&
	    is_array($config['installedpackages']['%%ZABBIXUNIQNAME%%'])) {
		init_config_arr(array('installedpackages', '%%ZABBIXUNIQNAME%%', 'config', 0));
		$zbproxy_config = $config['installedpackages']['%%ZABBIXUNIQNAME%%']['config'][0];
		if ($zbproxy_config['proxyenabled'] == "on") {
			$ListenIp = $zbproxy_config['listenip'] ?: "0.0.0.0";
			$ListenPort = $zbproxy_config['listenport'] ?: "10051";
			$Mode = (is_numericint($zbproxy_config['proxymode']) ? $zbproxy_config['proxymode'] : 0);
			$AdvancedParams = base64_decode($zbproxy_config['advancedparams']);

			$TLSConnect = "TLSConnect=" . $zbproxy_config['tlsconnect'];
			$TLSAccept = "TLSAccept=";
			$TLSAccept .= $zbproxy_config['tlsaccept'] ?: "unencrypted";

			if ($zbproxy_config['tlscaso']) {
				$TlsCAfile = "TLSCAFile=/usr/local/etc/ssl/cert.pem";
			} else {
				if ($zbproxy_config['tlscafile'] != "none") {
					$ca = lookup_ca($zbproxy_config['tlscafile']);
					zabbix_proxy_add_keyfile($ca['crt'], "ca");
					$TlsCAfile = "TLSCAFile=/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_proxy.ca";
				}
			}

			if ($zbproxy_config['tlscrlfile'] != "none") {
				$crl = lookup_crl($zbproxy_config['tlscrlfile']);
				crl_update($crl);
				zabbix_proxy_add_keyfile($crl['text'], "crl-verify");
				$TlsCRLfile = "TLSCRLFile=/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_proxy.crl-verify";
			}

			if ($zbproxy_config['tlscertfile'] != "none") {
				$cert = lookup_cert($zbproxy_config['tlscertfile']);

				zabbix_proxy_add_keyfile($cert['crt'], "cert");
				$TlsCERTfile = "TLSCertFile=/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_proxy.cert";

				zabbix_proxy_add_keyfile($cert['prv'], "key");
				$TlsKEYfile = "TLSKeyFile=/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_proxy.key";
			}

			if (!empty($zbproxy_config['tlspskidentity'])) {
				$TLSPSKIdentity = "TLSPSKIdentity={$zbproxy_config['tlspskidentity']}";
			}

			if (!empty($zbproxy_config['tlspskfile'])) {
				zabbix_proxy_add_keyfile($zbproxy_config['tlspskfile'], "psk");
				$TLSPSKFile = "TLSPSKFile=/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_proxy.psk";
			}

			$StartSNMPTrapper = $zbproxy_config['startsnmptrapper'];
			$SNMPTrapperFile = $zbproxy_config['snmptrapperfile'] ?: "/tmp/zabbix_traps.tmp";
			$TrapperTimeout = $zbproxy_config['trappertimeout'] ?: "300";
			$StartTrappers = $zbproxy_config['starttrappers'] ?: "5";

			$zbproxy_conf_file = <<< EOF
Server={$zbproxy_config['server']}
ServerPort={$zbproxy_config['serverport']}
Hostname={$zbproxy_config['hostname']}
ListenIP={$ListenIp}
ListenPort={$ListenPort}
PidFile=/var/run/zabbix-proxy/zabbix_proxy.pid
DBName=/var/db/zabbix-proxy/proxy.db
LogFile=/var/log/zabbix-proxy/zabbix_proxy.log
ConfigFrequency={$zbproxy_config['configfrequency']}
FpingLocation=/usr/local/sbin/fping
# There's currently no fping6 (IPv6) dependency in the package,
# but if there was, the binary would likely also be in /usr/local/sbin.
Fping6Location=/usr/local/sbin/fping6
ProxyMode={$Mode}
{$TLSConnect}
{$TLSAccept}
{$TlsCAfile}
{$TlsCRLfile}
{$TlsCERTfile}
{$TlsKEYfile}
{$TLSPSKIdentity}
{$TLSPSKFile}
StartSNMPTrapper={$StartSNMPTrapper}
SNMPTrapperFile={$SNMPTrapperFile}
TrapperTimeout={$TrapperTimeout}
StartTrappers={$StartTrappers}
{$AdvancedParams}

EOF;
			file_put_contents(ZABBIX_PROXY_BASE . "/etc/zabbix%%ZABBIXVERSION%%/zabbix_proxy.conf", strtr($zbproxy_conf_file, array("\r" => "")));
		}
	}

	$want_sysctls = array(
		'kern.ipc.shmall' => '2097152',
		'kern.ipc.shmmax' => '2147483648',
		'kern.ipc.semmsl' => '250'
	);
	$sysctls = array();
	// Check sysctl file values
	$sc_file="";
	if (file_exists("/etc/sysctl.conf")) {
		$sc = file("/etc/sysctl.conf");
		foreach ($sc as $line) {
			list($sysk, $sysv) = explode("=", $line, 2);
			if (preg_match("/\w/", $line) && !array_key_exists($sysk, $want_sysctls)) {
				$sc_file .= $line;
			}
		}
	}
	foreach ($want_sysctls as $ws => $wv) {
		$sc_file .= "{$ws}={$wv}\n";
		mwexec("/sbin/sysctl {$ws}={$wv}");
	}
	file_put_contents("/etc/sysctl.conf", $sc_file);

	// Check bootloader values
	$lt_file = "";
	$want_tunables = array(
		'kern.ipc.semopm' => '100',
		'kern.ipc.semmni' => '128',
		'kern.ipc.semmns' => '32000',
		'kern.ipc.shmmni' => '4096'
	);
	$tunables = array();
	if (file_exists("/boot/loader.conf")) {
		$lt = file("/boot/loader.conf");
		foreach ($lt as $line) {
			list($tunable, $val) = explode("=", $line, 2);
			if (preg_match("/\w/", $line) && !array_key_exists($tunable, $want_tunables)) {
				$lt_file .= $line;
			}
		}
	}
	foreach ($want_tunables as $wt => $wv) {
		$lt_file .= "{$wt}={$wv}\n";
	}
	file_put_contents("/boot/loader.conf", $lt_file);

	// Check startup script files
	// Create a few directories and ensure the sample files are in place
	if (!is_dir(ZABBIX_PROXY_BASE . "/etc/zabbix%%ZABBIXVERSION%%")) {
		mwexec("/bin/mkdir -p " . ZABBIX_PROXY_BASE . "/etc/zabbix%%ZABBIXVERSION%%");
	}

	$dir_checks = <<< EOF

	if [ ! -d /var/log/zabbix-proxy ]; then
		/bin/mkdir -p /var/log/zabbix-proxy
		/bin/chmod 755 /var/log/zabbix-proxy
	fi
	/usr/sbin/chown -R zabbix:zabbix /var/log/zabbix-proxy

	if [ ! -d /var/run/zabbix-proxy ]; then
		/bin/mkdir -p /var/run/zabbix-proxy
		/bin/chmod 755 /var/run/zabbix-proxy
	fi
	/usr/sbin/chown -R zabbix:zabbix /var/run/zabbix-proxy

	if [ ! -d /var/db/zabbix-proxy ]; then
		/bin/mkdir -p /var/db/zabbix-proxy
		/bin/chmod 755 /var/db/zabbix-proxy
	fi
	/usr/sbin/chown -R zabbix:zabbix /var/db/zabbix-proxy

EOF;

	$pid_check = <<< EOF

	/bin/pgrep -anx zabbix_proxy 2>/dev/null
	if [ "\$?" -eq "0" ]; then
		/usr/bin/killall -9 zabbix_proxy
		/bin/rm -f /var/run/zabbix-proxy/zabbix_proxy.pid
	fi

EOF;

	$zproxy_rcfile = "/usr/local/etc/rc.d/zabbix_proxy.sh";
	if (is_array($zbproxy_config) && $zbproxy_config['proxyenabled'] == "on") {
		$zproxy_start = strtr($dir_checks, array("\r" => ""));
		$zproxy_start .= "\techo \"Starting Zabbix Proxy\"...\n";
		$zproxy_start .= "\t" . ZABBIX_PROXY_BASE . "/sbin/zabbix_proxy -c /usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_proxy.conf\n";

		$zproxy_stop = "echo \"Stopping Zabbix Proxy\"\n";
		$zproxy_stop .= "\t/usr/bin/killall zabbix_proxy\n";
		$zproxy_stop .= "\t/bin/sleep 5\n";
		$zproxy_stop .= strtr($pid_check, array("\r" => ""));

		// write out rc.d start/stop file
		write_rcfile(array(
			"file" => "zabbix_proxy.sh",
			"start" => $zproxy_start,
			"stop" => $zproxy_stop
			)
		);
		restart_service("zabbix_proxy");
	} else {
		if (is_service_running("zabbix_proxy")) {
			stop_service("zabbix_proxy");
		}
		unlink_if_exists($zproxy_rcfile);
	}

}

// Based on openvpn_add_keyfile() function
function zabbix_proxy_add_keyfile($data, $directive) {

	$fpath = "/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_proxy.{$directive}";

	file_put_contents($fpath, base64_decode($data));
	@chmod($fpath, 0600);
	@chown($fpath, "zabbix");
}

function zabbix_proxy_certca($type) {
        global $config;
        $c_arr = array();
        $c_arr[] = array('refid' => 'none', 'descr' => 'none (auto)');
        if (isset($config[$type]) && is_array($config[$type])) {
                foreach ($config[$type] as $c) {
                        if (!empty($c['prv'])) {
                                $res_key = openssl_pkey_get_private(base64_decode($c['prv']));
                                $key_details = openssl_pkey_get_details($res_key);
                                if ($key_details['type'] == OPENSSL_KEYTYPE_RSA) {
                                        $c_arr[] = $c;
                                }
                        } elseif ($type == 'ca') {
                                $c_arr[] = $c;
                        }
                }
        }
        return $c_arr;
}

function zabbixproxy_plugin_certificates($pluginparams) {
	global $config;
	$result = array();
	init_config_arr(array('installedpackages', 'zabbixproxylts', 'config'));
	$zabbixconf = $config['installedpackages']['zabbixproxylts']['config'][0];

	if (($pluginparams['type'] == 'certificates') && !empty($zabbixconf) && ($zabbixconf['agentenabled'] == 'on')) {
		if (($pluginparams['event'] == 'used_ca') && ($zabbixconf['tlscaso'] != 'on') &&
		    !empty($zabbixconf['tlscafile']) && ($zabbixconf['tlscafile'] != 'none')) {
			$result['pkgname'] = "Zabbix Proxy";
			$result['certificatelist'] = array();
			$item = array();
			$cert = $zabbixconf['tlscafile'];
			$item['usedby'] = 'TLS CA';
			$result['certificatelist'][$cert][] = $item;
		}

		if (($pluginparams['event'] == 'used_certificates') && !empty($zabbixconf['tlscertfile']) &&
		    ($zabbixconf['tlscertfile'] != 'none')) {
			$result['pkgname'] = "Zabbix Proxy";
			$result['certificatelist'] = array();
		        if ($zabbixconf['tlsconnect'] == 'cert') {
				$item = array();
				$cert = $zabbixconf['tlscertfile'];
				$item['usedby'] = 'TLS Proxy';
				$result['certificatelist'][$cert][] = $item;
			}
		        if ($zabbixconf['tlsaccept'] == 'cert') {
				$item = array();
				$cert = $zabbixconf['tlscertfile'];
				$item['usedby'] = 'TLS Proxy';
				$result['certificatelist'][$cert][] = $item;
			}
		}

		if (($pluginparams['event'] == 'used_crl') && !empty($zabbixconf['tlscrlfile']) &&
		    ($zabbixconf['tlscrlfile'] != 'none')) {
			$result['pkgname'] = "Zabbix Proxy";
			$result['certificatelist'] = array();
			$item = array();
			$cert = $zabbixconf['tlscrlfile'];
			$item['usedby'] = 'TLS CRL';
			$result['certificatelist'][$cert][] = $item;
		}
	}

	return $result;
}

?>
