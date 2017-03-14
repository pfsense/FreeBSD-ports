<?php
/*
 * nrpe2.inc
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
// Bonus comment for testing
global $pfs_version;
$pfs_version = substr(trim(file_get_contents("/etc/version")), 0, 3);

if ($pfs_version == "2.1" || $pfs_version == "2.2") {
	define('NRPE_BASE', '/usr/pbi/nrpe-' . php_uname("m"));
} else {
	define('NRPE_BASE', '/usr/local');
}

if ($pfs_version == "2.1") {
	define('NRPE_BINARY', NRPE_BASE . "/sbin/nrpe2");
} else {
	define('NRPE_BINARY', "/usr/local/sbin/nrpe2");
}

define('NRPE_CONFIG_DIR', NRPE_BASE . '/etc');
define('NRPE_RCFILE', '/usr/local/etc/rc.d/nrpe2.sh');


function nrpe2_custom_php_install_command() {
	global $config;

	/* Create default commands configuration */
	if (!is_array($config['installedpackages']['nrpe2']['config'][0]['row'])) {
		$config['installedpackages']['nrpe2']['config'][0]['row'] = array(
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

	/* Remove the rc script installed with the package */
	unlink_if_exists(NRPE_CONFIG_DIR . '/rc.d/nrpe2');

}

function nrpe2_custom_php_deinstall_command() {
	unlink_if_exists(NRPE_RCFILE);
}

function nrpe2_write_rc_file() {
	$NRPE_CONFIG_DIR = NRPE_CONFIG_DIR;
	$NRPE2_BINARY = NRPE_BINARY;
	$fd = fopen(NRPE_RCFILE, 'w');
	$rc_file = <<<EOD
#!/bin/sh
#
# $FreeBSD: ports/net-mgmt/nrpe2/files/nrpe2.in,v 1.3 2008/12/25 20:24:43 miwi Exp $
#

# PROVIDE: nrpe2
# REQUIRE: NETWORKING
# KEYWORD: shutdown

#
# Add the following lines to /etc/rc.conf to enable nrpe2:
# nrpe2_enable (bool):    Set to "NO" by default.
#                         Set it to "YES" to enable nrpe2.
# nrpe2_flags (str):      Set to "" by default.
# nrpe2_configfile (str): Set to "{$NRPE_CONFIG_DIR}/nrpe.cfg" by default.
# nrpe2_pidfile (str):    Set to "/var/spool/nagios/nrpe2.pid" by default.
#

. /etc/rc.subr

nrpe2_enable=\${nrpe2_enable-"YES"}
name="nrpe2"
rcvar="\${name}_enable"

command="{$NRPE2_BINARY}"
command_args="-d"
extra_commands="reload"

sig_reload=HUP

[ -z "\${nrpe2_flags}" ] && nrpe2_flags=""
[ -z "\${nrpe2_configfile}" ] && nrpe2_configfile="{$NRPE_CONFIG_DIR}/nrpe.cfg"
[ -z "\${nrpe2_pidfile}" ] && nrpe2_pidfile="/var/run/nrpe2.pid"

load_rc_config "\${name}"

pidfile="\${nrpe2_pidfile}"

required_files="\${nrpe2_configfile}"
command_args="\${command_args} -c \${nrpe2_configfile}"

run_rc_command "$1"
EOD;
	fwrite($fd, $rc_file);
	fclose($fd);
	chmod(NRPE_RCFILE, 0755);

}

function nrpe2_resync_package() {
	conf_mount_rw();
	nrpe2_custom_php_write_config();
	nrpe2_custom_php_service();
	conf_mount_ro();
}

function nrpe2_custom_php_write_config() {
	global $config;
	$nagios_check_path = NRPE_BASE . "/libexec/nagios";

	$cmds = array();
	foreach ($config['installedpackages']['nrpe2']['config'][0]['row'] as $cmd) {
		$sudo_bin = "/usr/local/bin/sudo";
		$sudo = (isset($cmd['sudo']) && is_executable($sudo_bin)) ? "{$sudo_bin} " : "";
		$wcmd = !empty($cmd['warning']) ? "-w {$cmd['warning']}" : "";
		$ccmd = !empty($cmd['critical']) ? "-c {$cmd['critical']}" : "";
		if (is_executable("{$nagios_check_path}/{$cmd['command']}")) {
			$cmds[] = "command[{$cmd['name']}]={$sudo}{$nagios_check_path}/{$cmd['command']} {$wcmd} {$ccmd} {$cmd['extra']}\n";
		}
	}
	$commands = implode($cmds);

	$server_port = $config['installedpackages']['nrpe2']['config'][0]['server_port'] ?: '5666';
	$allowed_hosts = $config['installedpackages']['nrpe2']['config'][0]['allowed_hosts'] ?: '127.0.0.1';
	$dont_blame_nrpe = $config['installedpackages']['nrpe2']['config'][0]['dont_blame_nrpe'] == "on" ? '1' : '0';

	/* Create configuration file */
	$fd = fopen(NRPE_CONFIG_DIR . '/nrpe.cfg', 'w');
	$nrpe_cfg = <<<EOD
log_facility=daemon
pid_file=/var/run/nrpe2.pid
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
	if ($config['installedpackages']['nrpe2']['config'][0]['server_address'] != "") {
		$nrpe_cfg .= "server_address={$config['installedpackages']['nrpe2']['config'][0]['server_address']}";
	}
	fwrite($fd, $nrpe_cfg);
	fclose($fd);

}

function nrpe2_custom_php_service() {
	global $config;

	if ($config['installedpackages']['nrpe2']['config'][0]['enabled'] == "on") {
		nrpe2_write_rc_file();
		if (is_service_running("nrpe2")) {
			restart_service("nrpe2");
		} else {
			start_service("nrpe2");
		}
	} else {
		if (is_service_running("nrpe2")) {
			stop_service("nrpe2");
		}
		unlink_if_exists(NRPE_RCFILE);
	}
}

function nrpe2_get_commands() {
	$nagios_check_path = NRPE_BASE . "/libexec/nagios";
	$commands = glob("{$nagios_check_path}/check_*");
	$cmdarr = array();
	foreach ($commands as $cmd) {
		$cmdarr[]["command"] = basename($cmd);
	}
	return $cmdarr;
}

function nrpe2_custom_php_validation_command($post, &$input_errors) {
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
