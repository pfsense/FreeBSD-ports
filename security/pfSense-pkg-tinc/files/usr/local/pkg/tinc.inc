<?php
/*
 * tinc.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2025 Rubicon Communications, LLC (Netgate)
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

require_once('globals.inc');
require_once('config.inc');
require_once('service-utils.inc');
require_once('util.inc');
require_once('system.inc');

define('LOG_PREFIX_PKG_TINC', 'tinc');

function tinc_plugin_carp($pluginparams) {
	// $pluginparams['type'] = 'carp';
	// $pluginparams['event'] = 'rc.carpmaster';
	// $pluginparams['interface'] = $argument;
	logger(LOG_INFO, localize_text("got carp event "), LOG_PREFIX_PKG_TINC);
	if ($pluginparams['type'] == 'carp') {
		if ($pluginparams['event'] == 'rc.carpmaster') {
			// start
			logger(LOG_INFO, localize_text("got CARP:MASTER -> starting"), LOG_PREFIX_PKG_TINC);
			if (is_service_running('tinc')) {
				logger(LOG_INFO, localize_text("Service already running"), LOG_PREFIX_PKG_TINC);
			} else {
				start_service("tinc");
			}
		}
		if ($pluginparams['event'] == 'rc.carpbackup') {
			// stop
			logger(LOG_INFO, localize_text("got CARP:BACKUP -> stopping"), LOG_PREFIX_PKG_TINC);
			if (!is_service_running('tinc')) {
				logger(LOG_INFO, localize_text("Service already stopped"), LOG_PREFIX_PKG_TINC);
			} else {
				stop_service("tinc");
			}
		}
	}
}


function tinc_save() {
	global $configpath;
	$configpath = '/usr/local/etc/tinc';


	rmdir_recursive("{$configpath}.old");
	rename("{$configpath}", "{$configpath}.old");
	safe_mkdir("{$configpath}");
	safe_mkdir("{$configpath}/hosts");

	$tincconf = config_get_path('installedpackages/tinc/config/0', []);

	// No proper config, bail out.
	if (!isset($tincconf['name']) || empty($tincconf['name'])) {
		logger(LOG_ERR, localize_text("Cannot configure (name not set). Check your configuration."), LOG_PREFIX_PKG_TINC);
		return;
	}

	$fout = fopen("{$configpath}/tinc.conf", "w");
	fwrite($fout, "name=" . $tincconf['name'] . "\n");
	fwrite($fout, "AddressFamily=" . $tincconf['addressfamily'] . "\n");

	foreach (config_get_path('installedpackages/tinchosts/config', []) as $host) {
		if ($host['connect']) {
			fwrite($fout, "ConnectTo=" . $host['name'] . "\n");
		}

		$_output = "Address=" . $host['address'] . "\n";
		$_output .= "Subnet=" . $host['subnet'] . "\n";
		$_output .= base64_decode($host['extra']) . "\n";
		$_output .= base64_decode($host['cert_pub']) . "\n";
		file_put_contents("{$configpath}/hosts/" . $host['name'], $_output);
		if ($host['host_up']) {
			file_put_contents("{$configpath}/hosts/" . $host['name'] . '-up', unixnewlines(base64_decode($host['host_up'])) . "\n");
			chmod("{$configpath}/hosts/" . $host['name'] . '-up', 0744);
		}
		if ($host['host_down']) {
			file_put_contents("{$configpath}/hosts/" . $host['name'] . '-down', unixnewlines(base64_decode($host['host_down'])) . "\n");
			chmod("{$configpath}/hosts/" . $host['name'] . '-down', 0744);
		}
	}
	fwrite($fout, base64_decode($tincconf['extra']) . "\n");
	fclose($fout);

	// Check if we need to generate a new RSA key pair.
	if ($tincconf['gen_rsa']) {
		safe_mkdir("{$configpath}/tmp");
		exec("/usr/local/sbin/tincd -c {$configpath}/tmp -K");
		$tincconf['cert_pub'] = base64_encode(file_get_contents("{$configpath}/tmp/rsa_key.pub"));
		$tincconf['cert_key'] = base64_encode(file_get_contents("{$configpath}/tmp/rsa_key.priv"));
		$tincconf['gen_rsa'] = false;
		config_set_path('installedpackages/tinc/config/0', $tincconf);
		rmdir_recursive("{$configpath}/tmp");
		write_config("[tinc] New RSA key pair generated.");
	}

	$_output = "Subnet=" . $tincconf['localsubnet'] . "\n";
	$_output .= base64_decode($tincconf['host_extra']) . "\n";
	$_output .= base64_decode($tincconf['cert_pub']) . "\n";
	file_put_contents("{$configpath}/hosts/" . $tincconf['name'], $_output);
	file_put_contents("{$configpath}/rsa_key.priv", base64_decode($tincconf['cert_key']) . "\n");
	chmod("{$configpath}/rsa_key.priv", 0600);
	if ($tincconf['tinc_up']) {
		$_output = unixnewlines(base64_decode($tincconf['tinc_up'])) . "\n";
	} else {
		$_output = "ifconfig \$INTERFACE " . $tincconf['localip'] . " netmask " . $tincconf['vpnnetmask'] . "\n";
		$_output .= "ifconfig \$INTERFACE group pkg_tinc\n";
	}
	file_put_contents("{$configpath}/tinc-up", $_output);
	chmod("{$configpath}/tinc-up", 0744);
	if ($tincconf['tinc_down']) {
		file_put_contents("{$configpath}/tinc-down", unixnewlines(base64_decode($tincconf['tinc_down'])) . "\n");
		chmod("{$configpath}/tinc-down", 0744);
	}
	if ($tincconf['host_up']) {
		file_put_contents("{$configpath}/host-up", unixnewlines(base64_decode($tincconf['host_up'])) . "\n");
		chmod("{$configpath}/host-up", 0744);
	}
	if ($tincconf['host_down']) {
		file_put_contents("{$configpath}/host-down", unixnewlines(base64_decode($tincconf['host_down'])) . "\n");
		chmod("{$configpath}/host-down", 0744);
	}
	if ($tincconf['subnet_up']) {
		file_put_contents("{$configpath}/subnet-up", unixnewlines(base64_decode($tincconf['subnet_up'])) . "\n");
		chmod("{$configpath}/subnet-up", 0744);
	}
	if ($tincconf['subnet_down']) {
		file_put_contents("{$configpath}/subnet-down", unixnewlines(base64_decode($tincconf['subnet_down'])) . "\n");
		chmod("{$configpath}/subnet-down", 0744);
	}

	if ($tincconf['enable'] != "") {
		tinc_write_rcfile();
		if (is_service_running("tinc")) {
			restart_service("tinc");
		} else {
			start_service("tinc");
		}
	} else {
		if (is_process_running("tincd")) {
			stop_service("tinc");
		}
		unlink_if_exists("/usr/local/etc/rc.d/tinc.sh");
	}

}

function tinc_write_rcfile() {
	$rc['file'] = 'tinc.sh';
	$rc['start'] .= "/usr/local/sbin/tincd --config=/usr/local/etc/tinc\n\t";
	$rc['stop'] .= "/usr/local/sbin/tincd --kill && sleep 2\n\t";
	write_rcfile($rc);
}

function tinc_install() {
	global $g;

	safe_mkdir("/usr/local/etc/tinc/hosts");
	tinc_write_rcfile();
	unlink_if_exists("/usr/local/etc/rc.d/tincd");

	/* Create Interface Group */

	/* Don't add the interface group if it already exists */
	$found = false;
	foreach (config_get_path('ifgroups/ifgroupentry', []) as $ifg) {
		if ($ifg['ifname'] == "pkg_tinc") {
			$found = true;
		}
	}
	if (!$found) {
		$ifgroupentry = array();
		$ifgroupentry['members'] = '';
		$ifgroupentry['descr'] = 'tinc mesh VPN interface group';
		$ifgroupentry['ifname'] = 'pkg_tinc';
		config_set_path('ifgroups/ifgroupentry/', $ifgroupentry);
	}

	write_config("[tinc] Package installed.");
}

function tinc_deinstall() {
	global $g;

	/* Remove Interface Group */

	$myid = -1;
	foreach (config_get_path('ifgroups/ifgroupentry', []) as $idx => $ifgroupentry) {
		if ($ifgroupentry['ifname'] == 'pkg_tinc') {
			$myid = $idx;
			break;
		}
	}

	if (is_numericint($myid) && config_get_path("ifgroups/ifgroupentry/{$myid}")) {
		$members = array_filter(explode(" ", config_get_path("ifgroups/ifgroupentry/{$myid}/members")));
		foreach ($members as $ifs) {
			$realif = get_real_interface($ifs);
			if ($realif) {
				mwexec("/sbin/ifconfig {$realif} -group " . escapeshellarg(config_get_path("ifgroups/ifgroupentry/{$myid}/ifname")));
			}
		}
		config_del_path("ifgroups/ifgroupentry/{$myid}");
	}
	rmdir_recursive("/var/tmp/tinc");
	rmdir_recursive("/usr/local/etc/tinc*");
	write_config("[tinc] Package uninstalled.");
}

function tinc_validate_input($post, &$input_errors) {
	if ($post['localip']) {
		if ((!is_ipaddr($post['localip'])) && (!is_hostname($post['localip']))) {
			$input_errors[] = gettext("'Local IP' must be a valid IP address or hostname.");
		}
	}
	if ($post['address']) {
		if ((!is_ipaddr($post['address'])) && (!is_hostname($post['address']))) {
			$input_errors[] = gettext("'Host Address' must be a valid IP address or hostname.");
		}
	}
	if (($post['localsubnet']) && (!is_subnet($post['localsubnet']))) {
		$input_errors[] = gettext("'Local Subnet' must be a valid subnet.");
	}
	if (($post['subnet']) && (!is_subnet($post['subnet']))) {
		$input_errors[] = gettext("'Subnet' must be a valid subnet.");
	}
}
?>
