<?php
/*
 * collectd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2025 Rubicon Communications, LLC (Netgate)
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

define('COLLECTD_AGENT_BASE', '/usr/local');

function php_deinstall_collectd_agent() {
	unlink_if_exists(COLLECTD_AGENT_BASE . "/etc/collectd.conf");
	unlink_if_exists("/var/log/collectd.log");
	unlink_if_exists("/var/run/collectdmon.pid");
}

function validate_input_collectd($post, &$input_errors) {
	if (isset($post['agentenabled'])) {
		if ($post['plugins'] == '') {
			$input_errors[] = "At least one plugin must be selected.";
		}

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

		if ($post['hostname'] != '') {
			if (!is_domain($post['hostname'])) {
				$input_errors[] = "Hostname is not valid.";
			}
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

		if ($post['serverport'] != '') {
			if (!is_port($post['serverport'])) {
				$input_errors[] = "You must enter a valid value for 'Server Port'.";
			}
		}
	}
}

function sync_package_collectd() {
	// Check Collectd Agent settings
	$collectd_config = config_get_path('installedpackages/collectd/config/0', []);
	if ($collectd_config['agentenabled'] == "on") {
		if (!empty($collectd_config['hostname'])) {
			$hostname = "Hostname	\"{$collectd_config['hostname']}\"";
		} else {
			$hostname = "FQDNLookup	false";
		}
		foreach (explode(",", $collectd_config['plugins']) as $plugin) {
			if ($plugin == "cpu")       { $plugins .= "LoadPlugin	\"cpu\"\n"; }
			if ($plugin == "df")        { $plugins .= "LoadPlugin	\"df\"\n"; }
			if ($plugin == "disk")      { $plugins .= "LoadPlugin	\"disk\"\n"; }
			if ($plugin == "interface") { $plugins .= "LoadPlugin	\"interface\"\n"; }
			if ($plugin == "load")      { $plugins .= "LoadPlugin	\"load\"\n"; }
			if ($plugin == "memory")    { $plugins .= "LoadPlugin	\"memory\"\n"; }
			if ($plugin == "zfs_arc")   { $plugins .= "LoadPlugin	\"zfs_arc\"\n"; }
		}
		foreach (explode(",", $collectd_config['server']) as $srv) {
			$server .= "\n	<Server \"$srv\"";
			if (!empty($collectd_config['serverport'])) {
				$server .= " \"{$collectd_config['serverport']}\"";
			}
			$server .= ">";
			if (!empty($collectd_config['username']) && !empty($collectd_config['password'])) {
				$server .= "\n		SecurityLevel \"Encrypt\"";
				$server .= "\n		Username \"{$collectd_config['username']}\"";
				$server .= "\n		Password \"{$collectd_config['password']}\"";
			}
			$server .= "\n	</Server>";
		}
		if (!empty($collectd_config['bindaddress'])) {
			$server .= "\n	BindAddress	\"{$collectd_config['bindaddress']}\"";
		}
		$server .= "\n";

		$collectd_conf_file = <<< EOF
{$hostname}
{$plugins}
LoadPlugin "network"
<Plugin "network">{$server}</Plugin>

EOF;
		file_put_contents(COLLECTD_AGENT_BASE . "/etc/collectd.conf", strtr($collectd_conf_file, array("\r" => "")));
	}

	if (is_array($collectd_config) && $collectd_config['agentenabled'] == "on") {
		restart_service("collectd");
	} else {
		if (is_service_running("collectd")) {
			stop_service("collectd");
		}
	}
}

?>
