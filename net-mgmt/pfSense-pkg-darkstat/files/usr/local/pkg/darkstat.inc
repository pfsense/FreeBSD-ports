<?php
/*
 * darkstat.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2017 Rubicon Communications, LLC (Netgate)
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

require_once('config.inc');
require_once('interfaces.inc');
require_once('pfsense-utils.inc');
require_once('services.inc');
require_once('service-utils.inc');
require_once('util.inc');

function sync_package_darkstat() {
	global $config, $darkstat_config;
	if (is_array($config['installedpackages']['darkstat'])) {
		$darkstat_config =& $config['installedpackages']['darkstat']['config'][0];
	} else {
		$darkstat_config = array();
	}
	conf_mount_rw();

	/* If the service is (being) disabled, stop it (if running), remove rc script and do nothing else */
	if ($darkstat_config['enable'] != "on") {
		if (is_process_running("darkstat")) {
			stop_service("darkstat");
		}
		unlink_if_exists('/usr/local/etc/rc.d/darkstat.sh');
		return;
	}

	/* Configure capture interface(s) */
	$capture_interfaces = $darkstat_config['capture_interfaces'] ?: 'lan';
	foreach (explode(",", $capture_interfaces) as $iface) {
		$if = convert_friendly_interface_to_real_interface_name($iface);
		if ($if) {
			$ifaces_final .= " -i {$if}";
		}
	}
	$start = "/usr/local/sbin/darkstat {$ifaces_final}";

	/* Configure bind interface(s) */
	foreach (explode(",", $darkstat_config['bind_interfaces']) as $iface) {
		$if = get_interface_ip("$iface");
		if ($if) {
			$bind_ifaces_final .= " -b {$if}";
		}
	}
	$start .= "{$bind_ifaces_final}";

	/* Configure darkstat webgui port */
	if (is_port($darkstat_config['port'])) {
		$port = $darkstat_config['port'];
	} else {
		$port = '666';
	}
	$start .= " -p {$port}";

	/* Deal with the rest of configured options here */
	/* Local Network Traffic */
	$localnetworkenable = $darkstat_config['localnetworkenable'];
	$lif = $darkstat_config['localnetwork'];
	/* Local Networks Only */
	if ($localnetworkenable != "") {
		if (is_ipaddrv4(get_interface_ip($lif))) {
			$start .= " -l " . escapeshellarg(gen_subnet(get_interface_ip($lif), get_interface_subnet($lif)) . '/' . gen_subnet_mask(get_interface_subnet($lif)));
		}
		if ($darkstat_config['localnetworkonly'] != "") {
			$start .= " --local-only";
		}
	}
	/* No Promiscuous Mode */
	if (($localnetworkenable == "") && ($darkstat_config['nopromisc'] != "")) {
		$start .= " --no-promisc";
	}
	/* Disable DNS Resolution */
	if ($darkstat_config['nodns'] != "") {
		$start .= " --no-dns";
	}
	/* Disable MAC Display */
	if ($darkstat_config['nomacs'] != "") {
		$start .= " --no-macs";
	}
	/* Disable Seen Time */
	if ($darkstat_config['nolastseen'] != "") {
		$start .= " --no-lastseen";
	}
	/* Maximum Hosts Count */
	$hostsmax = $darkstat_config['hostsmax'];
	$hostskeep = $darkstat_config['hostskeep'];
	if (($hostsmax > 0) && ($hostsmax > $hostskeep)) {
		$start .= " --hosts-max {$hostsmax}";
	}
	/* Maximum Hosts to Keep */
	if (($hostskeep > 0) && ($hostskeep < $hostsmax)) {
		$start .= " --hosts-keep {$hostskeep}";
	}
	/* Maximum Ports Count */
	$portsmax = $darkstat_config['portsmax'];
	$portskeep = $darkstat_config['portskeep'];
	if (($portsmax > 0) && ($portsmax > $portskeep)) {
		$start .= " --ports-max {$portsmax}";
	}
	/* Maximum Ports to Keep */
	if (($portskeep > 0) && ($portskeep < $portsmax)) {
		$start .= " --ports-keep {$portskeep}";
	}
	/* Advanced Filtering Options */
	$advfilter = $darkstat_config['advfilter'];
	if ($advfilter != "") {
		$start .= " -f " . escapeshellarg(base64_decode($advfilter));
	}

	/* Create rc script */
	write_rcfile(array(
			"file" => "darkstat.sh",
			"start" => $start,
			"stop" => "/usr/bin/killall darkstat"
		)
	);

	/* Do not (re)start service on boot */
	if (platform_booting()) {
		return;
	} elseif (is_process_running("darkstat")) {
		restart_service("darkstat");
	} else {
		start_service("darkstat");
	}

	conf_mount_ro();
}

function validate_input_darkstat($post, &$input_errors) {
	global $config;
	/* Validate Web Interface Port */
	if ($post['port']) {
		if (!is_port($post['port'])) {
			$input_errors[] = gettext("The value for 'Web Interface Port' must be a valid port (1-65535).");
		}
		// Check webGUI port conflicts
		$webgui_port = $config['system']['webgui']['port'];
		if ($config['system']['webgui']['port'] == "") {
			if ($config['system']['webgui']['protocol'] == "http") {
				$webgui_port = 80;
			} elseif ($config['system']['webgui']['protocol'] == "https") {
				$webgui_port = 443;
			}
		}
		if ($post['port'] == "{$webgui_port}") {
			$input_errors[] = gettext("The value for 'Web Interface Port' must not be the same port where pfSense WebGUI is running ($webgui_port).");
		}
	}
	/* Validate Web Interface Hostname or IP */
	if ($post['host']) {
		if (!is_ipaddrv4($post['host']) && !is_hostname($post['host']) && !is_domain($post['host'])) {
			$input_errors[] = gettext("The value for 'Web Interface Hostname' must be a valid IPv4 address, hostname or domain");
		} elseif (is_ipaddrv4($post['host']) && !is_ipaddr_configured($post['host'])) {
			$input_errors[] = "Web Interface IP must be a valid, locally configured IPv4 address!";
		}
	}
	/* Validate Maximum Hosts Count */
	if ($post['hostsmax']) {
		if ($post['hostsmax'] < 1 || !is_numericint($post['hostsmax'])) {
			$input_errors[] = gettext("The value for 'Maximum Hosts Count' must be a positive integer.");
		}
	}
	/* Validate Maximum Hosts to Keep */
	if ($post['hostskeep']) {
		if ($post['hostskeep'] < 1 || !is_numericint($post['hostskeep'])) {
			$input_errors[] = gettext("The value for 'Maximum Hosts to Keep' must be a positive integer.");
		}
	}
	/* Validate sanity for hosts limits */
	if ($post['hostsmax'] || $post['hostskeep']) {
		if ($post['hostsmax'] <= $post['hostskeep']) {
			$input_errors[] = gettext("'Maximum Hosts Count' must be greater than 'Maximum Hosts to Keep'.");
		}
	}
	/* Validate Maximum Ports Count */
	if ($post['portsmax']) {
		if ($post['portsmax'] < 1 || !is_numericint($post['portsmax'])) {
			$input_errors[] = gettext("The value for 'Maximum Ports Count' must be a positive integer.");
		}
	}
	/* Validate Maximum Ports to Keep */
	if ($post['portskeep']) {
		if ($post['portskeep'] < 1 || !is_numericint($post['portskeep'])) {
			$input_errors[] = gettext("The value for 'Maximum Ports to Keep' to keep' must be a positive integer.");
		}
	}
	/* Validate sanity for ports limits */
	if ($post['portsmax'] || $post['portskeep']) {
		if ($post['portsmax'] <= $post['portskeep']) {
			$input_errors[] = gettext("'Maximum Ports to Keep' must be greater than 'Maximum Ports Count'.");
		}
	}
	/* Validate Local Network Traffic */
	if ($post['localnetworkenable'] && $post['nopromisc'] != "") {
		$input_errors[] = gettext("'No Promiscuous Mode' cannot be used when the 'Local Network' feature is enabled.");
	}
	/* Local Network must be IPv4 - no IPv6 support in darkstat */
	if ($post['localnetwork']) {
		$int = convert_friendly_interface_to_real_interface_name($post['localnetwork']);
		$ip = find_interface_ip($int);
		if (!is_ipaddrv4($ip)) {
			$input_errors[] = gettext("The selected 'local network' interface has no IPv4 configured. Configured IPv4 is required.");
		}
	}
	/* Basic sanity validation for Advanced Filtering Options*/
	if (($post['advfilter']) && !preg_match("/^[a-zA-Z0-9\+\-\=\(\):. ]*$/", $post['advfilter'])) {
		$input_errors[] = gettext('Advanced traffic filtering options may only contain characters matching ^[a-zA-Z0-9\+\-\=\(\):. ]*$ regexp.');
	}
}

?>
