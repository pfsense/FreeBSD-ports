<?php
/*
 * nrpe.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009 Erik Kristensen
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
require_once("filter.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");
require_once("util.inc");

define('NRPE_BASE', '/usr/local');
define('NRPE_BINARY', "/usr/local/sbin/nrpe3");
define('NRPE_CONFIG_DIR', NRPE_BASE . '/etc');
define('NRPE_RCFILE', '/usr/local/etc/rc.d/nrpe3.sh');

function nrpe_upgrade_config() {
	global $config;
	if (is_array($config['installedpackages']['nrpe2']['config'][0]) &&
	    !is_array($config['installedpackages']['nrpe']['config'][0])) {
		$config['installedpackages']['nrpe'] = $config['installedpackages']['nrpe2'];
		unset($config['installedpackages']['nrpe2']);
	}
}

function nrpe_custom_php_install_command() {
	global $config;

	nrpe_upgrade_config();

	/* Create default commands configuration */
	if (!is_array($config['installedpackages']['nrpe']['config'][0]['row'])) {
		$config['installedpackages']['nrpe']['config'][0]['row'] = array(
			0 => array(
				'name' => 'check_users',
				'command' => 'check_users',
				'warning' => '5',
				'critical' => '10'
			),
			1 => array(
				'name' => 'check_load',
				'command' => 'check_load',
				'warning' => '15,10,5',
				'critical' => '30,25,20',
			),
			2 => array(
				'name' => 'check_root',
				'command' => 'check_disk',
				'warning' => '20%',
				'critical' => '10%',
				'extra' => '-p /'
			),
			3 => array(
				'name' => 'check_var',
				'command' => 'check_disk',
				'warning' => '20%',
				'critical' => '10%',
				'extra' => '-p /var/run'
			),
			4 => array(
				'name' => 'check_zombie_procs',
				'command' => 'check_procs',
				'warning' => '5',
				'critical' => '10',
				'extra' => '-s Z'
			),
			5 => array(
				'name' => 'check_total_procs',
				'command' => 'check_procs',
				'warning' => '150',
				'critical' => '200'
			)
		);
	}

}

function nrpe_custom_php_deinstall_command() {
	unlink_if_exists(NRPE_RCFILE);
}

function nrpe_write_rc_file() {
	$NRPE_CONFIG_DIR = NRPE_CONFIG_DIR;
	$NRPE_BINARY = NRPE_BINARY;
	$fd = fopen(NRPE_RCFILE, 'w');
	$rc_file = <<<EOD
#!/bin/sh
#
# $FreeBSD: ports/net-mgmt/nrpe3/files/nrpe3.in,v 1.3 2008/12/25 20:24:43 miwi Exp $
#

# PROVIDE: nrpe3
# REQUIRE: NETWORKING
# KEYWORD: shutdown

#
# Add the following lines to /etc/rc.conf to enable nrpe3:
# nrpe3_enable (bool):    Set to "NO" by default.
#                         Set it to "YES" to enable nrpe3.
# nrpe3_flags (str):      Set to "" by default.
# nrpe3_configfile (str): Set to "{$NRPE_CONFIG_DIR}/nrpe.cfg" by default.
# nrpe3_pidfile (str):    Set to "/var/spool/nagios/nrpe3.pid" by default.
#

. /etc/rc.subr

nrpe3_enable=\${nrpe3_enable-"YES"}
name="nrpe3"
rcvar="\${name}_enable"

command="{$NRPE_BINARY}"
command_args="-d"
extra_commands="reload"

sig_reload=HUP

[ -z "\${nrpe3_flags}" ] && nrpe3_flags=""
[ -z "\${nrpe3_configfile}" ] && nrpe3_configfile="{$NRPE_CONFIG_DIR}/nrpe.cfg"
[ -z "\${nrpe3_pidfile}" ] && nrpe3_pidfile="/var/run/nrpe3.pid"

load_rc_config "\${name}"

pidfile="\${nrpe3_pidfile}"

required_files="\${nrpe3_configfile}"
command_args="\${command_args} -c \${nrpe3_configfile}"

run_rc_command "$1"
EOD;
	fwrite($fd, $rc_file);
	fclose($fd);
	chmod(NRPE_RCFILE, 0755);

}

function nrpe_resync_package() {
	conf_mount_rw();
	nrpe_custom_php_write_config();
	nrpe_custom_php_service();
	conf_mount_ro();
}

function nrpe_custom_php_write_config() {
	global $config;
	$nagios_check_path = NRPE_BASE . "/libexec/nagios";

	$cmds = array();
	foreach ($config['installedpackages']['nrpe']['config'][0]['row'] as $cmd) {
		$sudo_bin = "/usr/local/bin/sudo";
		$sudo = (isset($cmd['sudo']) && is_executable($sudo_bin)) ? "{$sudo_bin} " : "";
		$wcmd = !empty($cmd['warning']) ? "-w {$cmd['warning']}" : "";
		$ccmd = !empty($cmd['critical']) ? "-c {$cmd['critical']}" : "";

		$path_to_command = "{$nagios_check_path}/{$cmd['command']}";
		if (is_executable($path_to_command)) {
			$cmds[] = "command[{$cmd['name']}]={$sudo}{$path_to_command} {$wcmd} {$ccmd} {$cmd['extra']}\n";
		}
		else if ($cmd['command'] === 'Custom shell command') {
			$cmds[] = "command[{$cmd['name']}]={$sudo}{$cmd['extra']}\n";
		}
	}
	$commands = implode($cmds);

	$server_port = $config['installedpackages']['nrpe']['config'][0]['server_port'] ?: '5666';
	$allowed_hosts = $config['installedpackages']['nrpe']['config'][0]['allowed_hosts'] ?: '127.0.0.1';
	$dont_blame_nrpe = $config['installedpackages']['nrpe']['config'][0]['dont_blame_nrpe'] == "on" ? '1' : '0';

	/* Create configuration file */
	$fd = fopen(NRPE_CONFIG_DIR . '/nrpe.cfg', 'w');
	$nrpe_cfg = <<<EOD
log_facility=daemon
pid_file=/var/run/nrpe3.pid
server_port={$server_port}
nrpe_user=nagios
nrpe_group=nagios
allowed_hosts={$allowed_hosts}
dont_blame_nrpe={$dont_blame_nrpe}
debug=0
command_timeout=60
connection_timeout=300
{$commands}
EOD;
	if ($config['installedpackages']['nrpe']['config'][0]['server_address'] != "") {
		$nrpe_cfg .= "server_address={$config['installedpackages']['nrpe']['config'][0]['server_address']}";
	}
	fwrite($fd, $nrpe_cfg);
	fclose($fd);

}

function nrpe_custom_php_service() {
	global $config;

	if ($config['installedpackages']['nrpe']['config'][0]['enabled'] == "on") {
		nrpe_write_rc_file();
		if (is_service_running("nrpe")) {
			restart_service("nrpe");
		} else {
			start_service("nrpe");
		}
	} else {
		if (is_service_running("nrpe")) {
			stop_service("nrpe");
		}
		unlink_if_exists(NRPE_RCFILE);
	}
}

function nrpe_get_commands() {
	$nagios_check_path = NRPE_BASE . "/libexec/nagios";
	$commands = glob("{$nagios_check_path}/check_*");
	$cmdarr = array(
		array("command" => 'Custom shell command')
	);
	foreach ($commands as $cmd) {
		$cmdarr[]["command"] = basename($cmd);
	}
	return $cmdarr;
}

function nrpe_custom_php_validation_command($post, &$input_errors) {
	if (!is_port($post['server_port'])) {
		$input_errors[] = gettext("'Port Number' must be a valid port.");
	}
	if ($post['server_address'] != "") {
		if (!is_ipaddr_configured($post['server_address']) && !preg_match("/(127.0.0.1|0.0.0.0)/", $post['server_address'])) {
			$input_errors[] = gettext("'Bind IP Address' is not a valid, locally configured IP address.");
		}
	}
	foreach (explode(",", $post['allowed_hosts']) as $host) {
		if (!empty($host) && !is_ipaddr($host)) {
			$input_errors[] = gettext("'Nagios Server(s)' entry '{$host}' is not a valid IP address.");
		}
	}
}

?>
