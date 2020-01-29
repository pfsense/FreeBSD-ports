<?php
/*
 * softflowd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013-2020 Rubicon Communications, LLC (Netgate)
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

require_once("config.inc");
require_once("globals.inc");
require_once("interfaces.inc");
require_once("service-utils.inc");
require_once("util.inc");

function sync_package_softflowd() {
	global $config;

	if (is_array($config['installedpackages']['softflowd']['config'][0])) {
		$cf = $config['installedpackages']['softflowd']['config'][0];
	} else {
		$cf = array();
	}
	conf_mount_rw();
	$interface_list = explode(",", $cf['interface']);
	if (!empty($cf['host']) && !empty($interface_list) && $cf['enable'] != "off") {
		$cf['host'] = is_ipaddrv6($cf['host']) ? "[{$cf['host']}]" : $cf['host'];
		$start = "/usr/bin/killall -9 softflowd";
		$if_index = 1;
		foreach ($interface_list as $interface_friendly) {
			if (empty($interface_friendly)) {
				continue;
			}
			$interface = get_real_interface($interface_friendly);
			if (empty($interface)) {
				continue;
			}
			
			$start .= "\n\t/usr/local/bin/softflowd ";
			$start .= " -i " . escapeshellarg($if_index++ . ":" . $interface);
			$start .= " -n " . escapeshellarg("{$cf['host']}:{$cf['port']}");
			if (is_numericint($cf['maxflows'])) {
				$start .= " -m " . escapeshellarg($cf['maxflows']);
			}
			if (is_numericint($cf['hoplimit'])) {
				$start .= " -L " . escapeshellarg($cf['hoplimit']);
			}
			if (is_numericint($cf['sample']) && ($cf['sample'] > 0)) {
				$start .= " -s " . escapeshellarg($cf['sample']);
			}
			if ($cf['version'] != "") {
				$start .= " -v " . escapeshellarg($cf['version']);
			}
			if ($cf['flowtracking'] != "") {
				$start .= " -T " . escapeshellarg($cf['flowtracking']);
			}
			if ($cf['flowtimestamp'] != "" && $cf['flowtimestamp'] != "omit") {
				$start .= " -A " . escapeshellarg($cf['flowtimestamp']);
			}
			if (is_numericint($cf['timeout-general'])) {
				$start .= " -t " . escapeshellarg("general={$cf['timeout-general']}");
			}
			if (is_numericint($cf['timeout-maxlife'])) {
				$start .= " -t " . escapeshellarg("maxlife={$cf['timeout-maxlife']}");
			}
			if (is_numericint($cf['timeout-expint'])) {
				$start .= " -t " . escapeshellarg("expint={$cf['timeout-expint']}");
			}
			if (is_numericint($cf['timeout-tcp'])) {
				$start .= " -t " . escapeshellarg("tcp={$cf['timeout-tcp']}");
			}
			if (is_numericint($cf['timeout-tcp-rst'])) {
				$start .= " -t " . escapeshellarg("tcp.rst={$cf['timeout-tcp-rst']}");
			}
			if (is_numericint($cf['timeout-tcp-fin'])) {
				$start .= " -t " . escapeshellarg("tcp.fin={$cf['timeout-tcp-fin']}");
			}
			if (is_numericint($cf['timeout-udp'])) {
				$start .= " -t " . escapeshellarg("udp={$cf['timeout-udp']}");
			}
			$start .= " -p " . escapeshellarg("/var/run/softflowd.{$interface}.pid");
			$start .= " -c " . escapeshellarg("/var/run/softflowd.{$interface}.ctl");
		}
		write_rcfile(array(
				"file" => "softflowd.sh",
				"start" => $start,
				"stop" => "/usr/bin/killall -9 softflowd"
			)
		);
		/* Only try to restart if not booting */
		if (!platform_booting()) {
			if (is_service_running("softflowd")) {
				restart_service("softflowd");
			} else {
				start_service("softflowd");
			}
		}
	} else {
		stop_service("softflowd");
		unlink_if_exists("/usr/local/etc/rc.d/softflowd.sh");
	}
	conf_mount_ro();
}

function validate_form_softflowd($post, &$input_errors) {
	if (!is_ipaddr($post['host'])) {
		$input_errors[] = "You must specify a valid ip address in the 'Host' field";
	}
	if (!is_port($post['port'])) {
		$input_errors[] = "You must specify a valid port number in the 'Port' field";
	}
	if (!in_array($post['version'], array(1, 5, 9, 10))) {
		$input_errors[] = 'You must specify a netflow version';
	}
	if (!in_array($post['flowtracking'], array("ether", "vlan", "full", "proto", "ip"))) {
		$input_errors[] = 'You must specify a valid flow tracking selection';
	}
	if (!in_array($post['flowtimestamp'], array("sec", "milli", "micro", "nano", "omit"))) {
		$input_errors[] = 'You must specify a valid flow timestamp precision';
	}
	if (!empty($post['hoplimit']) && (!is_numericint($post['hoplimit']) || ($post['hoplimit'] < 1) || ($post['hoplimit'] > 255))) {
		$input_errors[] = 'You must specify a valid hop limit';
	}
	if (!empty($post['timeout-general']) && !is_numericint($post['timeout-general'])) {
		$input_errors[] = 'You must specify a valid General Timeout';
	}
	if (!empty($post['timeout-maxlife']) && !is_numericint($post['timeout-maxlife'])) {
		$input_errors[] = 'You must specify a valid Maximum Lifetime Timeout';
	}
	if (!empty($post['timeout-expint']) && !is_numericint($post['timeout-expint'])) {
		$input_errors[] = 'You must specify a valid Expire Interval';
	}
	if (!empty($post['timeout-tcp']) && !is_numericint($post['timeout-tcp'])) {
		$input_errors[] = 'You must specify a valid TCP Timeout';
	}
	if (!empty($post['timeout-tcp-rst']) && !is_numericint($post['timeout-tcp-rst'])) {
		$input_errors[] = 'You must specify a valid TCP RST Timeout';
	}
	if (!empty($post['timeout-tcp-fin']) && !is_numericint($post['timeout-tcp-fin'])) {
		$input_errors[] = 'You must specify a valid TCP FIN Timeout';
	}
	if (!empty($post['timeout-udp']) && !is_numericint($post['timeout-udp'])) {
		$input_errors[] = 'You must specify a valid UDP Timeout';
	}
}

function cleanup_config_softflowd() {
	global $a_pkg;
	$pffconf = array();
	if (is_array($a_pkg)) {
		foreach($a_pkg as $cf) {
			if ($cf['host'] != "") {
				$pffconf = $cf;
			}
		}
	}
	$a_pkg = array();
	$a_pkg[0] = $pffconf;
}

function display_interface_indices_softflowd() {
	global $config;

	if (is_array($config['installedpackages']['softflowd']['config'][0])) {
		$cf = $config['installedpackages']['softflowd']['config'][0];
	} else {
		$cf = array();
	}
	
	/* Compile and display the list of interface indices if softflowd is active */
	if (isset($cf['interface'])) {
		$interface_list = explode(",", $cf['interface']);
		$interface_indices = "";
		if (!empty($cf['host']) && !empty($interface_list) && $cf['enable'] != "off") {
			$if_index = 1;
			foreach ($interface_list as $interface_friendly) {
				$interface_desc = convert_friendly_interface_to_friendly_descr($interface_friendly);
				$interface_indices .= "{$if_index}: {$interface_desc}<br>";
				$if_index++;
			}
			print_info_box("The following interface indices are currently in use:<br><br>{$interface_indices}", "info", "");
		}
	}
}

?>
