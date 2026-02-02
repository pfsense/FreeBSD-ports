<?php
/*
 * snmptt.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2025 Rubicon Communications, LLC (Netgate)
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

define('LOG_PREFIX_PKG_SNMPTT', 'snmptt');

function php_deinstall_snmptt() {
	unlink_if_exists("/usr/local/etc/rc.d/snmptt.sh");
}

function validate_input_snmptt($post, &$input_errors) {

	if (isset($post['snmpttenabled'])) {

		if (!empty($post['snmptt_system_name']) && !is_hostname($post['snmptt_system_name'])) {
			$input_errors[] = "'SNMPTT System Name' is not a valid hostname.";
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

		if (preg_match("/\"/", $post['wildcard_expansion_separator'])) {
			$input_errors[] = "Double quotes not allowed in 'Wildcard Expansion Separator' field.";
		}

	}
}

function sync_package_snmptt() {
	global $g;

	if (is_service_running('snmptt') && !file_exists("{$g['tmp_path']}/.rc.start_packages.running")) {
		logger(LOG_INFO, localize_text("Stopping service"), LOG_PREFIX_PKG_SNMPTT);
		stop_service('snmptt');
	}

	if (is_array(config_get_path('installedpackages/snmptt'))) {
		$snmptt_config = config_get_path('installedpackages/snmptt/config/0');
		if ($snmptt_config['snmpttenabled'] == "on") {
			$strip_domain_list = base64_decode($snmptt_config['strip_domain_list']);
			$strip_domain_list = preg_replace("~\r\n~", "\n", $strip_domain_list);
			if (!empty($snmptt_config['wildcard_expansion_separator'])) {
				$wildcard_expansion_separator = $snmptt_config['wildcard_expansion_separator'];
			} else {
				$wildcard_expansion_separator = " ";
			}
			include("/usr/local/pkg/snmptt.ini.php");
			file_put_contents("/usr/local/etc/snmp/snmptt.ini", $snmpttini, LOCK_EX);

			if (is_array(config_get_path('installedpackages/snmpttconf'))) {
				$snmpttconf_config = config_get_path('installedpackages/snmpttconf/config/0');
				$snmptt_configfile = base64_decode($snmpttconf_config['snmptt_configfile']);
				$snmptt_configfile = preg_replace("~\r\n~", "\n", $snmptt_configfile);
				file_put_contents("/usr/local/etc/snmp/snmptt.conf", $snmptt_configfile, LOCK_EX);
			}
		}
	}

	if (is_array($snmptt_config) && $snmptt_config['snmpttenabled']=="on" && $snmptt_config['mode'] == "daemon") {
		// RC FILE
		$snmptt_rcfile="/usr/local/etc/rc.d/snmptt.sh";

		$snmptt_start = "echo \"Starting SNMPTT Daemon...\"\n";
		$snmptt_start .= "	for DIR in log run spool; do\n";
		$snmptt_start .= "		if [ ! -d /var/\$DIR/snmptt ]; then\n";
		$snmptt_start .= "			mkdir /var/\$DIR/snmptt\n";
		$snmptt_start .= "			chown snmptt:snmptt /var/\$DIR/snmptt\n";
		$snmptt_start .= "		fi\n";
		$snmptt_start .= "	done\n";
		$snmptt_start .= "	if [ -f /var/run/snmptt/snmptt.pid ]; then\n";
		$snmptt_start .= "		echo \"Already running\"\n";
		$snmptt_start .= "		exit 1\n";
		$snmptt_start .= "	fi\n";
		$snmptt_start .= "	/usr/local/bin/perl /usr/local/sbin/snmptt --daemon > /dev/null 2>&1\n";

		$snmptt_stop = "echo \"Stopping SNMPTT Daemon...\"\n";
		$snmptt_stop .= "	if [ ! -f /var/run/snmptt/snmptt.pid ]; then\n";
		$snmptt_stop .= "			kill `pgrep -anf 'snmptt --daemon'`\n";
		$snmptt_stop .= "	else\n";
		$snmptt_stop .= "			kill `cat /var/run/snmptt/snmptt.pid`\n";
		$snmptt_stop .= "	fi\n";
		$snmptt_stop .= "	/bin/sleep 2\n";

		/* write out rc.d start/stop file */
		write_rcfile(array(
			"file" => "snmptt.sh",
			"start" => "$snmptt_start",
			"stop" => "$snmptt_stop"
			)
		);

		if (!file_exists("{$g['tmp_path']}/.rc.start_packages.running")) {
			logger(LOG_INFO, localize_text("Starting service"), LOG_PREFIX_PKG_SNMPTT);
			start_service("snmptt");
		}
	} else {
		unlink_if_exists("/usr/local/etc/rc.d/snmptt.sh");
	}

}

?>
