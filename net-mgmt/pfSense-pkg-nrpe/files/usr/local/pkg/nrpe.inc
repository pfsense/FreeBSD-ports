<?php
/*
 * nrpe.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2025 Rubicon Communications, LLC (Netgate)
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
require_once("xmlrpc_client.inc");

define('NRPE_BASE', '/usr/local');
define('NRPE_BINARY', "/usr/local/sbin/nrpe");
define('NRPE_CONFIG_DIR', NRPE_BASE . '/etc');
define('NRPE_RCFILE', '/usr/local/etc/rc.d/nrpe.sh');

function nrpe_upgrade_config() {
	if (is_array(config_get_path('installedpackages/nrpe2/config/0')) &&
	    !is_array(config_get_path('installedpackages/nrpe/config/0'))) {
		config_set_path('installedpackages/nrpe',
		    config_get_path('installedpackages/nrpe2'));
		config_del_path('installedpackages/nrpe2');
	}
}

function nrpe_custom_php_install_command() {
	nrpe_upgrade_config();

	/* Create default commands configuration */
	if (is_array(
	    config_get_path('installedpackages/nrpe/config/0/row'))) {
		return;
	}
	config_set_path('installedpackages/nrpe/config/0/row', array(
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
		),
		6 => array(
			'name' => 'check_swap',
			'command' => 'check_swap',
			'warning' => '50%',
			'critical' => '25%'
		)
	));

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

# PROVIDE: nrpe
# REQUIRE: NETWORKING
# KEYWORD: shutdown

#
# Add the following lines to /etc/rc.conf to enable nrpe:
# nrpe_enable (bool):    Set to "NO" by default.
#                        Set it to "YES" to enable nrpe.
# nrpe_flags (str):      Set to "" by default.
# nrpe_configfile (str): Set to "{$NRPE_CONFIG_DIR}/nrpe.cfg" by default.
# nrpe_pidfile (str):    Set to "/var/spool/nagios/nrpe.pid" by default.
#

. /etc/rc.subr

nrpe_enable=\${nrpe_enable-"YES"}
name="nrpe"
rcvar="\${name}_enable"

command="{$NRPE_BINARY}"
command_args="-d"
extra_commands="reload"

sig_reload=HUP

[ -z "\${nrpe_flags}" ] && nrpe_flags=""
[ -z "\${nrpe_configfile}" ] && nrpe_configfile="{$NRPE_CONFIG_DIR}/nrpe.cfg"
[ -z "\${nrpe_pidfile}" ] && nrpe_pidfile="/var/run/nrpe.pid"

load_rc_config "\${name}"

pidfile="\${nrpe_pidfile}"

required_files="\${nrpe_configfile}"
command_args="\${command_args} -c \${nrpe_configfile}"

run_rc_command "$1"
EOD;
	fwrite($fd, $rc_file);
	fclose($fd);
	chmod(NRPE_RCFILE, 0755);

}

function nrpe_resync_package() {
	nrpe_custom_php_write_config();
	nrpe_custom_php_service();
}

function nrpe_custom_php_write_config() {
	$nagios_check_path = NRPE_BASE . "/libexec/nagios";

	$pconfig = config_get_path('installedpackages/nrpe/config/0');

	$cmds = array();
	foreach ($pconfig['row'] as $cmd) {
		$sudo_bin = "/usr/local/bin/sudo";
		$sudo = (isset($cmd['sudo']) && is_executable($sudo_bin))
		    ? "{$sudo_bin} " : "";
		$wcmd = !empty($cmd['warning']) ? "-w {$cmd['warning']}" : "";
		$ccmd = !empty($cmd['critical']) ? "-c {$cmd['critical']}" : "";

		$path_to_command = "{$nagios_check_path}/{$cmd['command']}";
		if (is_executable($path_to_command)) {
			$cmds[] = "command[{$cmd['name']}]={$sudo}" .
			    "{$path_to_command} {$wcmd} {$ccmd} " .
			    "{$cmd['extra']}\n";
		}
		else if ($cmd['command'] === 'Custom shell command') {
			$cmds[] = "command[{$cmd['name']}]={$sudo}" .
			    "{$cmd['extra']}\n";
		}
	}
	$commands = implode($cmds);

	$server_port = $pconfig['server_port'] ?: '5666';
	$allowed_hosts = $pconfig['allowed_hosts'] ?: '127.0.0.1';
	$dont_blame_nrpe = $pconfig['dont_blame_nrpe'] == "on" ? '1' : '0';

	/* Create configuration file */
	$fd = fopen(NRPE_CONFIG_DIR . '/nrpe.cfg', 'w');
	$nrpe_cfg = <<<EOD
log_facility=daemon
pid_file=/var/run/nrpe.pid
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
	if ($pconfig['server_address'] != "") {
		$nrpe_cfg .= "server_address={$pconfig['server_address']}";
	}
	fwrite($fd, $nrpe_cfg);
	fclose($fd);
}

function nrpe_custom_php_service() {
	if (config_get_path('installedpackages/nrpe/config/0/enabled') ==
	    "on") {
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
		$input_errors[] = gettext(
		    "'Port Number' must be a valid port.");
	}
	if ($post['server_address'] != "") {
		if (!is_ipaddr_configured($post['server_address']) &&
		    !preg_match("/(127.0.0.1|0.0.0.0)/",
		    $post['server_address'])) {
			$input_errors[] = gettext("'Bind IP Address' is not " .
			    "a valid, locally configured IP address.");
		}
	}
	foreach (explode(",", $post['allowed_hosts']) as $host) {
		if (!empty($host) && !is_ipaddr($host)) {
			$input_errors[] = sprintf(gettext(
			    "'Nagios Server(s)' entry '%s' is not a valid " .
			    "IP address."), $host);
		}
	}
}

function nrpe_plugin_xmlrpc_send() {
	return [
		'installedpackages/nrpe'
	];
}

function nrpe_plugin_xmlrpc_recv($new_sections) {
	$section_paths = [
		'installedpackages/nrpe'
	];

	$ret = [
		'xmlrpc_recv_result' => false
	];
	foreach ($section_paths as $path) {
		$old_section = config_get_path($path, []);
		$new_section = array_get_path($new_sections, $path, []);

		// Don't save empty configuration sections.
		if (empty($old_section) && empty($new_section)) {
			continue;
		}
		// Ignore unchanged configuration sections.
		if ($old_section === $new_section) {
			continue;
		}

		$ret[$path] = array_merge($old_section, $new_section);
		$ret['xmlrpc_recv_result'] = true;
	}

	return $ret;
}

function nrpe_plugin_xmlrpc_recv_done($xmlrpc_recv_result) {
	if (array_get_path($xmlrpc_recv_result, 'freeradius/xmlrpc_recv_result') !== true) {
		return;
	}
	nrpe_resync_package();
}

?>
