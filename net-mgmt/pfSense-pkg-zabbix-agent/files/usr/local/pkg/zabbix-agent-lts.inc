<?php
/*
	zabbix-agent-lts.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2013 Danilo G. Baio
	Copyright (C) 2013 Marcello Coutinho
	Copyright (C) 2015 ESF, LLC
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
require_once("util.inc");
require_once("functions.inc");
require_once("pkg-utils.inc");
require_once("globals.inc");

$pf_version = substr(trim(file_get_contents("/etc/version")), 0, 3);
if ($pf_version == "2.1" || $pf_version == "2.2") {
	define('ZABBIX_AGENT_BASE', '/usr/pbi/zabbix22-agent-' . php_uname("m"));
} else {
	define('ZABBIX_AGENT_BASE', '/usr/local');
}

function php_deinstall_zabbix_agent_lts() {
	conf_mount_rw();

	stop_service("zabbix_agentd_lts");
	unlink_if_exists(ZABBIX_AGENT_BASE . "/etc/rc.d/zabbix_agentd_lts.sh");
	unlink_if_exists(ZABBIX_AGENT_BASE . "/etc/zabbix22/zabbix_agentd.conf");
	unlink_if_exists("/var/log/zabbix-agent-lts/zabbix_agentd_lts.log");
	unlink_if_exists("/var/run/zabbix-agent-lts/zabbix_agentd_lts.pid");

	if (is_dir("/var/log/zabbix-agent-lts")) {
		mwexec("/bin/rm -rf /var/log/zabbix-agent-lts/");
	}
	if (is_dir("/var/run/zabbix-agent-lts")) {
		mwexec("/bin/rm -rf /var/run/zabbix-agent-lts/");
	}

	conf_mount_ro();
}

function validate_input_zabbix_agent_lts($post, &$input_errors) {

	if (isset($post['agentenabled'])) {
		if (!preg_match("/\w+/", $post['server'])) {
			$input_errors[] = "Server field is required.";
		}

		if (!preg_match("/\w+/", $post['hostname'])) {
			$input_errors[] = "Hostname field is required.";
		}

		if ($post['listenip'] != '') {
			if (!is_ipaddr_configured($post['listenip']) && !preg_match("/(127.0.0.1|0.0.0.0)/", $post['listenip'])) {
				$input_errors[] = "'Listen IP' is not a configured IP address.";
			}
		}

		if ($post['listenport'] != '') {
			if (!is_numericint($post['listenport'])) {
				$input_errors[] = "'Listen Port' value is not numeric.";
			} elseif ($post['listenport'] < 1 || $post['listenport'] > 65535) {
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

function sync_package_zabbix_agent_lts() {
	global $config, $g;

	conf_mount_rw();

	// Check Zabbix Agent settings
	if (is_array($config['installedpackages']['zabbixagentlts'])) {
		$zbagent_config = $config['installedpackages']['zabbixagentlts']['config'][0];
		if ($zbagent_config['agentenabled'] == "on") {
			$RefreshActChecks = (preg_match("/(\d+)/", $zbagent_config['refreshactchecks'], $matches)? $matches[1] : "120");
			$BufferSend = (preg_match("/(\d+)/", $zbagent_config['buffersend'], $matches) ? $matches[1] : "5");
			$BufferSize = (preg_match("/(\d+)/", $zbagent_config['buffersize'], $matches) ? $matches[1] : "100");
			$StartAgents = (preg_match("/(\d+)/", $zbagent_config['startagents'], $matches) ? $matches[1] : "3");
			$UserParams = base64_decode($zbagent_config['userparams']);
			$ListenIp = $zbagent_config['listenip'] ?: "0.0.0.0";
			$ListenPort = $zbagent_config['listenport'] ?: "10050";
			$TimeOut = $zbagent_config['timeout'] ?: "3";

			$zbagent_conf_file = <<< EOF
Server={$zbagent_config['server']}
ServerActive={$zbagent_config['serveractive']}
Hostname={$zbagent_config['hostname']}
ListenIP={$ListenIp}
ListenPort={$ListenPort}
RefreshActiveChecks={$RefreshActChecks}
DebugLevel=3
PidFile=/var/run/zabbix-agent-lts/zabbix_agentd_lts.pid
LogFile=/var/log/zabbix-agent-lts/zabbix_agentd_lts.log
LogFileSize=1
Timeout={$TimeOut}
BufferSend={$BufferSend}
BufferSize={$BufferSize}
StartAgents={$StartAgents}
{$UserParams}

EOF;
			file_put_contents(ZABBIX_AGENT_BASE . "/etc/zabbix22/zabbix_agentd.conf", strtr($zbagent_conf_file, array("\r" => "")));
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
	if (!is_dir(ZABBIX_AGENT_BASE . "/etc/zabbix22")) {
		mwexec("/bin/mkdir -p " . ZABBIX_AGENT_BASE . "/etc/zabbix22");
	}

	$dir_checks = <<< EOF

	if [ ! -d /var/log/zabbix-agent-lts ]; then
		/bin/mkdir -p /var/log/zabbix-agent-lts
		/usr/sbin/chmod 755 /var/log/zabbix-agent-lts
	fi
	/usr/sbin/chown -R zabbix:zabbix /var/log/zabbix-agent-lts

	if [ ! -d /var/run/zabbix-agent-lts ]; then
		/bin/mkdir -p /var/run/zabbix-agent-lts
		/usr/sbin/chmod 755 /var/run/zabbix-agent-lts
	fi
	/usr/sbin/chown -R zabbix:zabbix /var/run/zabbix-agent-lts

EOF;

	$zagent_rcfile = "/usr/local/etc/rc.d/zabbix_agentd_lts.sh";
	if (is_array($zbagent_config) && $zbagent_config['agentenabled'] == "on") {
		$zagent_start .= strtr($dir_checks, array("\r" => "")). "\necho \"Starting Zabbix Agent LTS...\"\n";
		$zagent_start .= ZABBIX_AGENT_BASE . "/sbin/zabbix_agentd\n";

		$zagent_stop = "echo \"Stopping Zabbix Agent LTS...\"\n";
		$zagent_stop .= "/usr/bin/killall zabbix_agentd\n";
		$zagent_stop .= "/bin/sleep 5\n";

		// write out rc.d start/stop file
		write_rcfile(array(
			"file" => "zabbix_agentd_lts.sh",
			"start" => "$zagent_start",
			"stop" => "$zagent_stop"
			)
		);
		restart_service("zabbix_agentd_lts");
	} else {
		if (is_service_running("zabbix_agentd_lts")) {
			stop_service("zabbix_agentd_lts");
		}
		unlink_if_exists($zagent_rcfile);
	}

	conf_mount_ro();
}

?>
