<?php
/*
 * snmptt.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017 Rubicon Communications, LLC (Netgate)
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

function php_install_snmptt() {
	sync_package_snmptt();
}

function php_deinstall_snmptt() {
	global $config, $g;

	define('SNMPTT_BASE', '/usr/local');

	unlink_if_exists(SNMPTT_BASE . "/etc/rc.d/snmptt.sh");
}

function validate_input_snmptt($post, &$input_errors) {

	if (isset($post['snmpttenabled'])) {

		if (!empty($post['snmptt_system_name']) && !is_hostname($post['snmptt_system_name'])) {
			$input_errors[] = "'SNMPTT System Name' is not a valid hostname.";
		}

		if (!preg_match("/^\".+\"$/", $post['wildcard_expansion_separator'])) {
			$input_errors[] = "'Wildcard Expansion Separator' must be within quotes and can contain 1 or more characters.";
		}

		if (!is_numericint($post['threads_max'])) {
			$input_errors[] = "'Threads Max' value is not numeric.";
		}

		if (!is_numericint($post['sleep'])) {
			$input_errors[] = "'Sleep' value is not numeric.";
		}

		if (!is_numericint($post['statistics_interval'])) {
			$input_errors[] = "'Statistics Interval' value is not numeric.";
		}

	}
}

function sync_package_snmptt() {
	global $config, $g;

	conf_mount_rw();

	if (is_service_running('snmptt') && !file_exists("{$g['tmp_path']}/.rc.start_packages.running")) {
		log_error("Stopping service snmptt");
		stop_service('snmptt');
	}

	define('SNMPTT_BASE', '/usr/local');

	if (is_array($config['installedpackages']['snmptt'])) {
		$snmptt_config = $config['installedpackages']['snmptt']['config'][0];
		if ($snmptt_config['snmpttenabled'] == "on") {
			$snmptt_system_name = $snmptt_config['snmptt_system_name'];
			$mode = $snmptt_config['mode'];
			$multiple_event = $snmptt_config['multiple_event'];
			$dns_enable = $snmptt_config['dns_enable'];
			$strip_domain = $snmptt_config['strip_domain'];

			$strip_domain_list = base64_decode($snmptt_config['strip_domain_list']);
			$strip_domain_list = preg_replace("~\r\n~", "\n", $strip_domain_list);

			$resolve_value_ip_addresses = $snmptt_config['resolve_value_ip_addresses'];
			$net_snmp_perl_enable = $snmptt_config['net_snmp_perl_enable'];
			$net_snmp_perl_cache_enable = $snmptt_config['net_snmp_perl_cache_enable'];
			$net_snmp_perl_best_guess = $snmptt_config['net_snmp_perl_best_guess'];
			$translate_log_trap_oid = $snmptt_config['translate_log_trap_oid'];
			$translate_value_oids = $snmptt_config['translate_value_oids'];
			$translate_enterprise_oid_format = $snmptt_config['translate_enterprise_oid_format'];
			$translate_trap_oid_format = $snmptt_config['translate_trap_oid_format'];
			$translate_varname_oid_format = $snmptt_config['translate_varname_oid_format'];
			$translate_integers = $snmptt_config['translate_integers'];
			$wildcard_expansion_separator = $snmptt_config['wildcard_expansion_separator'];
			$allow_unsafe_regex = $snmptt_config['allow_unsafe_regex'];
			$remove_backslash_from_quotes = $snmptt_config['remove_backslash_from_quotes'];
			$dynamic_nodes = $snmptt_config['dynamic_nodes'];
			$description_mode = $snmptt_config['description_mode'];
			$description_clean = $snmptt_config['description_clean'];
			$threads_enable = $snmptt_config['threads_enable'];
			$threads_max = $snmptt_config['threads_max'];
			$date_format = $snmptt_config['date_format'];
			$time_format = $snmptt_config['time_format'];
			$date_time_format = $snmptt_config['date_time_format'];
			$sleep = $snmptt_config['sleep'];
			$use_trap_time = $snmptt_config['use_trap_time'];
			$keep_unlogged_traps = $snmptt_config['keep_unlogged_traps'];
			$duplicate_trap_window = $snmptt_config['duplicate_trap_window'];
			$stdout_enable = $snmptt_config['stdout_enable'];
			$log_enable = $snmptt_config['log_enable'];
			$log_file = $snmptt_config['log_file'];
			$log_system_enable = $snmptt_config['log_system_enable'];
			$log_system_file = $snmptt_config['log_system_file'];
			$unknown_trap_log_enable = $snmptt_config['unknown_trap_log_enable'];
			$unknown_trap_log_file = $snmptt_config['unknown_trap_log_file'];
			$statistics_interval = $snmptt_config['statistics_interval'];
			$syslog_enable = $snmptt_config['syslog_enable'];
			$syslog_facility = $snmptt_config['syslog_facility'];
			$syslog_level = $snmptt_config['syslog_level'];
			$syslog_system_enable = $snmptt_config['syslog_system_enable'];
			$syslog_system_facility = $snmptt_config['syslog_system_facility'];
			$syslog_system_level = $snmptt_config['syslog_system_level'];

			include("/usr/local/pkg/snmptt.ini.php");
			file_put_contents(SNMPTT_BASE . "/etc/snmp/snmptt.ini", $snmpttini, LOCK_EX);

			if (is_array($config['installedpackages']['snmpttconf'])) {
				$snmpttconf_config = $config['installedpackages']['snmpttconf']['config'][0];
				$snmptt_configfile = base64_decode($snmpttconf_config['snmptt_configfile']);
				$snmptt_configfile = preg_replace("~\r\n~", "\n", $snmptt_configfile);
				file_put_contents(SNMPTT_BASE . "/etc/snmp/snmptt.conf", $snmptt_configfile, LOCK_EX);
			}

		}
	}

	// RC FILE
	$snmptt_rcfile="/usr/local/etc/rc.d/snmptt.sh";
	if (is_array($snmptt_config) && $snmptt_config['snmpttenabled']=="on") {

		$snmptt_start = "echo \"Starting SNMPTT Daemon...\"\n";
		$snmptt_start .= "	if [ -f /var/run/snmptt/snmptt.pid ]; then \n";
		$snmptt_start .= "		echo \"Already running\" \n";
		$snmptt_start .= "		exit 1 \n";
		$snmptt_start .= "	fi \n";
		$snmptt_start .= "	/usr/local/bin/perl /usr/local/sbin/snmptt --daemon > /dev/null 2>&1 \n";

		$snmptt_stop = "echo \"Stopping SNMPTT Daemon...\"\n";
		$snmptt_stop .= "	if [ ! -f /var/run/snmptt/snmptt.pid ]; then \n";
		$snmptt_stop .= "			kill `pgrep -anf 'snmptt --daemon'` \n";
		$snmptt_stop .= "	else \n";
		$snmptt_stop .= "			kill `cat /var/run/snmptt/snmptt.pid` \n";
		$snmptt_stop .= "	fi \n";
		$snmptt_stop .= "	/bin/sleep 2 \n";

		/* write out rc.d start/stop file */
		write_rcfile(array(
			"file" => "snmptt.sh",
			"start" => "$snmptt_start",
			"stop" => "$snmptt_stop"
			)
		);

		if (!file_exists("{$g['tmp_path']}/.rc.start_packages.running")) {
			log_error("Starting service snmptt");
			start_service("snmptt");
		}
	}

	conf_mount_ro();
}

?>
