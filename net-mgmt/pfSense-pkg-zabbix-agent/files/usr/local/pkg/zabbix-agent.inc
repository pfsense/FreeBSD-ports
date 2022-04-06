<?php
/*
 * zabbix-agent.inc
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

define('ZABBIX_AGENT_BASE', '/usr/local');
define('ZABBIX_VERSION', %%ZABBIXVERSION%%);

function php_deinstall_zabbix_agent() {
	unlink_if_exists(ZABBIX_AGENT_BASE . "/etc/zabbix%%ZABBIXVERSION%%/zabbix_agentd.conf");
	unlink_if_exists("/var/log/zabbix-agent/zabbix_agentd.log");
	unlink_if_exists("/var/run/zabbix-agent/zabbix_agentd.pid");

	if (is_dir("/var/log/zabbix-agent")) {
		mwexec("/bin/rm -rf /var/log/zabbix-agent/");
	}
	if (is_dir("/var/run/zabbix-agent")) {
		mwexec("/bin/rm -rf /var/run/zabbix-agent/");
	}
}

function validate_input_zabbix_agent($post, &$input_errors) {

	if (isset($post['agentenabled'])) {
		if ($post['server'] != '') {
			foreach (explode(",", $post['server']) as $srv) {
				$srv = trim($srv);
				if (!is_ipaddr($srv) && !is_hostname($srv)) {
					$input_errors[] = htmlspecialchars($srv) .
					" is not a valid IP address or hostname for 'Server'.";
				}
			}
		} else {
			$input_errors[] = "Server field is required.";
		}

		if ($post['serveractive'] != '') {
			foreach (explode(",", $post['serveractive']) as $sact) {
				$sact = trim($sact);
				if (!is_ipaddr($sact) && !is_hostname($sact) &&
				    !is_ipaddrwithport($sact) && !is_hostnamewithport($sact)) {
					$input_errors[] = htmlspecialchars($sact) .
					" is not a valid IP address or hostname with port for 'Server Active'.";
				}
			}
		}

		if (!is_domain($post['hostname'])) {
			$input_errors[] = "Hostname field is required.";
		}

		if ($post['listenip'] != '') {
			foreach (explode(",", $post['listenip']) as $listip) {
				$listip = trim($listip);
				if (!is_ipaddr_configured($listip) && !preg_match("/(127.0.0.1|0.0.0.0|::1|::)/", $listip)) {
					$input_errors[] = htmlspecialchars($listip) .
					" is not a configured IP address for 'Listen IP'.";
				}
			}
		}

		if ($post['listenport'] != '') {
			if (!is_port($post['listenport'])) {
				$input_errors[] = "You must enter a valid value for 'Listen Port'.";
			}
		}

		if ($post['refreshactchecks'] != '') {
			if (!is_numericint($post['refreshactchecks'])) {
				$input_errors[] = "'Refresh Active Checks' value is not numeric.";
			} elseif ($post['refreshactchecks'] < 60 || $post['refreshactchecks'] > 3600) {
				$input_errors[] = "You must enter a valid value for 'Refresh Active Checks'.";
			}
		}

		if ($post['timeout'] != '') {
			if (!is_numericint($post['timeout'])) {
				$input_errors[] = "Timeout value is not numeric.";
			} elseif ($post['timeout'] < 1 || $post['timeout'] > 30) {
				$input_errors[] = "You must enter a valid value for 'Timeout'.";
			}
		}

		if ($post['buffersend'] != '') {
			if (!is_numericint($post['buffersend'])) {
				$input_errors[] = "'Buffer Send' value is not numeric.";
			} elseif ($post['buffersend'] < 1 || $post['buffersend'] > 3600) {
				$input_errors[] = "You must enter a valid value for 'Buffer Send'.";
			}
		}

		if ($post['buffersize'] != '') {
			if (!is_numericint($post['buffersize'])) {
				$input_errors[] = "'Buffer Size' value is not numeric.";
			} elseif ($post['buffersize'] < 2 || $post['buffersize'] > 65535) {
				$input_errors[] = "You must enter a valid value for 'Buffer Size'.";
			}
		}

		if ($post['startagents'] != '') {
			if (!is_numericint($post['startagents'])) {
				$input_errors[] = "'Start Agents' value is not numeric.";
			} elseif ($post['startagents'] < 0 || $post['startagents'] > 100) {
				$input_errors[] = "You must enter a valid value for 'Start Agents'.";
			}
		}
	}
}

function sync_package_zabbix_agent() {
	global $config, $g;


	// Check Zabbix Agent settings
	if (isset($config['installedpackages']['%%ZABBIXUNIQNAME%%']) &&
	    is_array($config['installedpackages']['%%ZABBIXUNIQNAME%%'])) {
		init_config_arr(array('installedpackages', '%%ZABBIXUNIQNAME%%', 'config', 0));
		$zbagent_config = $config['installedpackages']['%%ZABBIXUNIQNAME%%']['config'][0];
		if ($zbagent_config['agentenabled'] == "on") {
			$RefreshActChecks = (preg_match("/(\d+)/", $zbagent_config['refreshactchecks'], $matches)? $matches[1] : "120");
			$BufferSend = (preg_match("/(\d+)/", $zbagent_config['buffersend'], $matches) ? $matches[1] : "5");
			$BufferSize = (preg_match("/(\d+)/", $zbagent_config['buffersize'], $matches) ? $matches[1] : "100");
			$StartAgents = (preg_match("/(\d+)/", $zbagent_config['startagents'], $matches) ? $matches[1] : "3");
			$UserParams = base64_decode($zbagent_config['userparams']);
			$ListenIp = $zbagent_config['listenip'] ?: "0.0.0.0";
			$ListenPort = $zbagent_config['listenport'] ?: "10050";
			$TimeOut = $zbagent_config['timeout'] ?: "3";

			$TLSConnect = "TLSConnect=" . $zbagent_config['tlsconnect'];
			$TLSAccept = "TLSAccept=";
			$TLSAccept .= $zbagent_config['tlsaccept'] ?: "unencrypted";

			if ($zbagent_config['tlscaso']) {
				$TlsCAfile = "TLSCAFile=/usr/local/etc/ssl/cert.pem";
			} else {
				if ($zbagent_config['tlscafile'] != "none") {
					$ca = lookup_ca($zbagent_config['tlscafile']);
					zabbix_agent_add_keyfile($ca['crt'], "ca");
					$TlsCAfile = "TLSCAFile=/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_agentd.ca";
				}
			}

			if ($zbagent_config['tlscrlfile'] != "none") {
				$crl = lookup_crl($zbagent_config['tlscrlfile']);
				crl_update($crl);
				zabbix_agent_add_keyfile($crl['text'], "crl-verify");
				$TlsCRLfile = "TLSCRLFile=/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_agentd.crl-verify";
			}

			if ($zbagent_config['tlscertfile'] != "none") {
				$cert = lookup_cert($zbagent_config['tlscertfile']);

				zabbix_agent_add_keyfile($cert['crt'], "cert");
				$TlsCERTfile = "TLSCertFile=/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_agentd.cert";

				zabbix_agent_add_keyfile($cert['prv'], "key");
				$TlsKEYfile = "TLSKeyFile=/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_agentd.key";
			}

			if (!empty($zbagent_config['tlspskidentity'])) {
				$TLSPSKIdentity = "TLSPSKIdentity={$zbagent_config['tlspskidentity']}";
			}

			if (!empty($zbagent_config['tlspskfile'])) {
				zabbix_agent_add_keyfile($zbagent_config['tlspskfile'], "psk");
				$TLSPSKFile = "TLSPSKFile=/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_agentd.psk";
			}

			$zbagent_conf_file = <<< EOF
Server={$zbagent_config['server']}
ServerActive={$zbagent_config['serveractive']}
Hostname={$zbagent_config['hostname']}
ListenIP={$ListenIp}
ListenPort={$ListenPort}
RefreshActiveChecks={$RefreshActChecks}
DebugLevel=3
PidFile=/var/run/zabbix-agent/zabbix_agentd.pid
LogFile=/var/log/zabbix-agent/zabbix_agentd.log
LogFileSize=1
Timeout={$TimeOut}
BufferSend={$BufferSend}
BufferSize={$BufferSize}
StartAgents={$StartAgents}
{$TLSConnect}
{$TLSAccept}
{$TlsCAfile}
{$TlsCRLfile}
{$TlsCERTfile}
{$TlsKEYfile}
{$TLSPSKIdentity}
{$TLSPSKFile}
{$UserParams}

EOF;
			file_put_contents(ZABBIX_AGENT_BASE . "/etc/zabbix%%ZABBIXVERSION%%/zabbix_agentd.conf", strtr($zbagent_conf_file, array("\r" => "")));
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
	if (!is_dir(ZABBIX_AGENT_BASE . "/etc/zabbix%%ZABBIXVERSION%%")) {
		mwexec("/bin/mkdir -p " . ZABBIX_AGENT_BASE . "/etc/zabbix%%ZABBIXVERSION%%");
	}

	$dir_checks = <<< EOF

	if [ ! -d /var/log/zabbix-agent ]; then
		/bin/mkdir -p /var/log/zabbix-agent
		/bin/chmod 755 /var/log/zabbix-agent
	fi
	/usr/sbin/chown -R zabbix:zabbix /var/log/zabbix-agent

	if [ ! -d /var/run/zabbix-agent ]; then
		/bin/mkdir -p /var/run/zabbix-agent
		/bin/chmod 755 /var/run/zabbix-agent
	fi
	/usr/sbin/chown -R zabbix:zabbix /var/run/zabbix-agent

EOF;

	$zagent_rcfile = "/usr/local/etc/rc.d/zabbix_agentd.sh";
	if (is_array($zbagent_config) && $zbagent_config['agentenabled'] == "on") {
		$zagent_start .= strtr($dir_checks, array("\r" => "")). "\necho \"Starting Zabbix Agent...\"\n";
		$zagent_start .= ZABBIX_AGENT_BASE . "/sbin/zabbix_agentd -c /usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_agentd.conf\n";

		$zagent_stop = "echo \"Stopping Zabbix Agent...\"\n";
		$zagent_stop .= "/usr/bin/killall zabbix_agentd\n";
		$zagent_stop .= "/bin/sleep 5\n";

		// write out rc.d start/stop file
		write_rcfile(array(
			"file" => "zabbix_agentd.sh",
			"start" => "$zagent_start",
			"stop" => "$zagent_stop"
			)
		);
		restart_service("zabbix_agentd");
	} else {
		if (is_service_running("zabbix_agentd")) {
			stop_service("zabbix_agentd");
		}
		unlink_if_exists($zagent_rcfile);
	}

}

// Based on openvpn_add_keyfile() function
function zabbix_agent_add_keyfile($data, $directive) {

	$fpath = "/usr/local/etc/zabbix%%ZABBIXVERSION%%/zabbix_agentd.{$directive}";

	file_put_contents($fpath, base64_decode($data));
	@chmod($fpath, 0600);
	@chown($fpath, "zabbix");
}

function zabbix_agent_certca($type) {
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

function zabbixagent_plugin_certificates($pluginparams) {
	global $config;
	$result = array();
	init_config_arr(array('installedpackages', 'zabbixagentlts', 'config'));
	$zabbixconf = $config['installedpackages']['zabbixagentlts']['config'][0];

	if (($pluginparams['type'] == 'certificates') && !empty($zabbixconf) && ($zabbixconf['agentenabled'] == 'on')) {
		if (($pluginparams['event'] == 'used_ca') && ($zabbixconf['tlscaso'] != 'on') &&
		    !empty($zabbixconf['tlscafile']) && ($zabbixconf['tlscafile'] != 'none')) {
			$result['pkgname'] = "Zabbix Agent";
			$result['certificatelist'] = array();
			$item = array();
			$cert = $zabbixconf['tlscafile'];
			$item['usedby'] = 'TLS CA';
			$result['certificatelist'][$cert][] = $item;
		}

		if (($pluginparams['event'] == 'used_certificates') && !empty($zabbixconf['tlscertfile']) &&
		    ($zabbixconf['tlscertfile'] != 'none')) {
			$result['pkgname'] = "Zabbix Agent";
			$result['certificatelist'] = array();
		        if ($zabbixconf['tlsconnect'] == 'cert') {
				$item = array();
				$cert = $zabbixconf['tlscertfile'];
				$item['usedby'] = 'TLS Connect';
				$result['certificatelist'][$cert][] = $item;
			}
		        if ($zabbixconf['tlsaccept'] == 'cert') {
				$item = array();
				$cert = $zabbixconf['tlscertfile'];
				$item['usedby'] = 'TLS Accept';
				$result['certificatelist'][$cert][] = $item;
			}
		}

		if (($pluginparams['event'] == 'used_crl') && !empty($zabbixconf['tlscrlfile']) &&
		    ($zabbixconf['tlscrlfile'] != 'none')) {
			$result['pkgname'] = "Zabbix Agent";
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
