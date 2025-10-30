<?php
/*
 * status_traffic_totals.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2025 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("service-utils.inc");
require_once("interfaces.inc");
require_once("ipsec.inc");

define('LOG_PREFIX_PKG_TRAFFICTOTALS', 'Traffic Totals');

global $vnstat_dbdir, $vnstat_config;
$vnstat_dbdir = '/var/db/vnstat';
$vnstat_config = '/usr/local/etc/vnstat.conf';

function vnstat_write_conf() {
	global $vnstat_dbdir, $vnstat_config;
	$vnscfg = config_get_path('installedpackages/traffictotals/config/0', []);
	$startDay = isset($vnscfg['startday']) ? $vnscfg['startday'] : 1;

	$vnstat_conf_file = <<<EOF
#########################################
# vnStat 2.2 config file                #
# AUTOMATICALLY GENERATED. DO NOT EDIT! #
#########################################

# Location of the database directory
DatabaseDir "{$vnstat_dbdir}"

# On which day should months change
MonthRotate {$startDay}

# Image colors
CBackground     "FFFFFF"
CEdge           "AEAEAE"
CHeader         "606060"
CHeaderTitle    "FFFFFF"
CHeaderDate     "FFFFFF"
CText           "000000"
CLine           "B0B0B0"
CLineL          "-"
CRx             "92CF00"
CTx             "606060"
CRxD            "-"
CTxD            "-"

EOF;

	if (file_put_contents($vnstat_config, $vnstat_conf_file) === FALSE) {
		logger(LOG_ERR, localize_text("Could not write %s", $vnstat_config), LOG_PREFIX_PKG_TRAFFICTOTALS);
		exit;
	}
}

function vnstat_create_nic_dbs() {
	//TODO: code that allows you to just add new interfaces (check exsisting first and compare)
	//List can be obtained from vnstat --iflist.
	foreach(vnstat_portlist() as $interface => $details) {
		unset($output);
		exec('/usr/local/bin/vnstat' .
			' -i ' . escapeshellarg($details['if']) .
			' --setalias ' . escapeshellarg($details['descr']) .
			' --add', $output);

		if (strpos($output[0], 'already exists in the database') !== false) {
			exec('/usr/local/bin/vnstat' .
			' -i ' . escapeshellarg($details['if']) .
			' --setalias ' . escapeshellarg($details['descr']));
		}
	}

    sleep(5);
	restart_service_if_running('vnstatd');
}

function vnstat_delete_nic_dbs() {
	global $vnstat_dbdir;
	stop_service('vnstatd');
	rmdir_recursive($vnstat_dbdir);
}

function vnstat_read() {
	$json_string = '';
	$fd = popen("/usr/local/bin/vnstat --json", "r");
	$error = "";

	$json_string = str_replace("\n", ' ', fgets($fd));
	if(substr($json_string, 0, 5) === "Error") {
		throw new Exception(substr($json_string, 7));
	}

	while (!feof($fd)) {
		$json_string .= fgets($fd);

		if(substr($json_string, 0, 5) === "Error") {
			throw new Exception(str_replace("\n", ' ', substr($json_string, 7)));
			break;
		}
	}

	pclose($fd);
	return $json_string;
}

function vnstat_install() {
	global $vnstat_dbdir;

	// Remove cron job from old versions, it does not work this way any longer.
	install_cron_job("/usr/local/bin/vnstat -u", false);

	// Detect if enabled
	try {
		$data = vnstat_read();
		// Enabled
		config_set_path('installedpackages/traffictotals/config/0/enabled', true);
	} catch (Exception $e) {
		if (config_path_enabled('installedpackages/traffictotals/config/0', 'enabled')) {
			// Enabled but missing data, so reinitialize
			vnstat_write_conf();
			vnstat_create_nic_dbs();
		} else {
			// Disabled
		}
	}
}

function vnstat_deinstall() {
	global $vnstat_dbdir;
	rmdir_recursive($vnstat_dbdir);
}

function vnstat_write_rcfile() {
	global $g, $vnstat_dbdir, $vnstat_config;
	$rundir = "{$g['varrun_path']}/vnstat";
	$pidfile = "{$rundir}/vnstat.pid";
	$user = 'vnstat';
	$group = 'vnstat';

	$rc = array();
	$rc['file'] = 'vnstatd.sh';
	$rc['stop'] = '/usr/bin/killall vnstatd';
	$rc['start'] = <<<EOD

	if [ ! -d {$vnstat_dbdir} ]; then
		install -d -o {$user} -g {$group} {$vnstat_dbdir};
	fi

	if [ ! -d "{$rundir}" ]; then
		install -d -o {$user} -g {$group} {$rundir};
	fi

	/usr/local/sbin/vnstatd -d -p {$pidfile} --config {$vnstat_config}

EOD;
	write_rcfile($rc);
}

function vnstat_portlist() {
	$portlist = config_get_path('interfaces', []);
	$ifdescrs = get_configured_interface_with_descr();

	if (ipsec_enabled()) {
		$portlist['enc0']['if'] = "enc0";
		$portlist['enc0']['descr'] = gettext("IPsec");
	}

	foreach (array('server', 'client') as $mode) {
		foreach (config_get_path("openvpn/openvpn-{$mode}", []) as $setting) {
			if (!isset($setting['disable'])) {
				$portlist['ovpn' . substr($mode, 0, 1) . $setting['vpnid']]['if'] = 'ovpn' . substr($mode, 0, 1) . $setting['vpnid'];
				$portlist['ovpn' . substr($mode, 0, 1) . $setting['vpnid']]['descr'] = gettext("OpenVPN") . " " . $mode . ": ".htmlspecialchars($setting['description']);
			}
		}
	}

	foreach ($portlist as $interface => $details) {
		if ($details['descr']) {
			//do nothing
		} elseif ($ifdescrs[$interface]) {
			$portlist[$interface]['descr'] = $ifdescrs[$interface];
		} else {
			$portlist[$interface]['descr'] = $interface;
		}
	}

	return $portlist;
}

function vnstat_sync() {
	if (config_path_enabled('installedpackages/traffictotals/config/0', 'enabled')) { // Enabled
		//make the directory for the interface databases
		safe_mkdir('/var/db/vnstat');
		//overwrite vnstat conf
		vnstat_write_conf();
		//loop through interfaces and create databases
		vnstat_write_rcfile();
		start_service('vnstatd');
		sleep(5);
		vnstat_create_nic_dbs();
	} else {
		// disable
		//loop through interfaces and delete databases
		vnstat_delete_nic_dbs();
		unlink_if_exists('/usr/local/etc/rc.d/vnstatd.sh');
	}
}

function vnstat_reset() {
	//loop through interfaces and delete databases
	vnstat_delete_nic_dbs();
	restart_service('vnstatd');
	sleep(5);
	//loop through interfaces and re-create databases
	vnstat_create_nic_dbs();
}
