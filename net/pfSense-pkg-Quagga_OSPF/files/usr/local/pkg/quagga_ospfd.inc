<?php
/*
 * quagga_ospfd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2010-2015 Rubicon Communications, LLC (Netgate)
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
require_once("functions.inc");
require_once("interfaces.inc");
require_once("service-utils.inc");
require_once("util.inc");

define('PKG_QUAGGA_CONFIG_BASE', '/var/etc/quagga');

function quagga_ospfd_get_carp_list() {

	$list = array();
	$list[] = array("name" => "none", "value" => "none");

	$carplist = get_configured_vip_list("all", VIP_CARP);
	foreach ($carplist as $vid => $vaddr) {
		$vip = get_configured_vip($vid);
		$list[] = array("name" => "$vaddr (vhid: {$vip['vhid']})", "value" => $vid);
	}

	return ($list);
}

function quagga_ospfd_get_interfaces() {
	global $config;
	$interfaces = get_configured_interface_with_descr();
	$ospf_ifs = array();
	foreach ($interfaces as $iface => $ifacename) {
		$tmp["name"] = $ifacename;
		$tmp["value"] = $iface;
		$ospf_ifs[] = $tmp;
	}

	foreach (array('server', 'client') as $mode) {
		if (is_array($config['openvpn']["openvpn-{$mode}"])) {
			foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
				if (!isset($setting['disable'])) {
					$tmp["name"] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
					$tmp["value"] = 'ovpn' . substr($mode, 0, 1) . $setting['vpnid'];
					$ospf_ifs[] = $tmp;
				}
			}
		}
	}

	return $ospf_ifs;
}

function quagga_ospfd_install_conf() {
	global $config;

	conf_mount_rw();
	// Since we need to embed this in a string, copy to a var. Can't embed constnats.
	$quagga_config_base = PKG_QUAGGA_CONFIG_BASE;

	$noaccept = "";

	// generate ospfd.conf based on the assistant
	if (is_array($config['installedpackages']['quaggaospfd']['config'])) {
		$ospfd_conf = &$config['installedpackages']['quaggaospfd']['config'][0];
	} else {
		log_error("Quagga OSPFd: No config data found.");
		return;
	}

	if (isset($config['installedpackages']['quaggaospfdraw']['config'][0]['ospfd'])
	    && !empty($config['installedpackages']['quaggaospfdraw']['config'][0]['ospfd'])) {
		// if there is a raw config specifyed in tthe config.xml use that instead of the assisted config
		$conffile = str_replace("\r","",base64_decode($config['installedpackages']['quaggaospfdraw']['config'][0]['ospfd']));
	} else {
		$conffile = "# This file was created by the pfSense package manager.  Do not edit!\n\n";

		if ($ospfd_conf['password']) {
			$conffile .= "password {$ospfd_conf['password']}\n";
		}
		if ($ospfd_conf['logging']) {
			$conffile .= "log syslog\n";
		}

		/* Interface Settings */
		$passive_interfaces = array();
		$interface_networks = array();

		if (is_array($config['installedpackages']['quaggaospfdinterfaces']['config'])) {
			foreach ($config['installedpackages']['quaggaospfdinterfaces']['config'] as $conf) {
				$realif = get_real_interface($conf['interface']);
				if (empty($realif)) {
					continue;
				}
				$conffile .= "interface {$realif}\n" ;
				if (!empty($conf['networktype'])) {
					$conffile .= "  ip ospf network {$conf['networktype']}\n";
				}
				if (!empty($conf['metric'])) {
					$conffile .= "  ip ospf cost {$conf['metric']}\n";
				}
				if (!empty($conf['hellointervalinseconds'])) {
					$conffile .= "  ip ospf hello-interval {$conf['hellointervalinseconds']}\n";
				}
				if ($conf['md5password'] && !empty($conf['password'])) {
					$conffile .= "  ip ospf authentication message-digest\n";
					$conffile .= "  ip ospf message-digest-key 1 md5 " . substr($conf['password'], 0, 15) . "\n";
				} elseif (!empty($conf['password'])) {
					$conffile .= "  ip ospf authentication-key " . substr($conf['password'], 0, 8) . "\n";
				}
				if (!empty($conf['routerpriorityelections']) || ($conf['routerpriorityelections'] == "0")) {
					$conffile .= "  ip ospf priority {$conf['routerpriorityelections']}\n";
				}
				if (!empty($conf['retransmitinterval'])) {
					$conffile .= "  ip ospf retransmit-interval {$conf['retransmitinterval']}\n";
				}
				if (!empty($conf['deadtimer'])) {
					$conffile .= "  ip ospf dead-interval {$conf['deadtimer']}\n";
				}
				if (!empty($conf['passive'])) {
					$passive_interfaces[] = $realif;
				}
				$interface_ip = find_interface_ip($realif);
				$interface_subnet = find_interface_subnet($realif);
				/* Cheap hack since point-to-points won't attach if /32 is used. */
				if ($interface_subnet == 32) {
					$interface_subnet = 30;
				}
				$subnet = gen_subnet($interface_ip, $interface_subnet);
				if (!empty($conf['acceptfilter']) && is_ipaddr($subnet) && is_numericint($interface_subnet)) {
					$noaccept .= "ip prefix-list ACCEPTFILTER deny {$subnet}/{$interface_subnet}\n";
				}
				if (!empty($conf['interfacearea'])) {
					$interface_networks[] = array( "subnet" => "{$subnet}/{$interface_subnet}", "area" => $conf['interfacearea']);
				} else {
					$interface_networks[] = array( "subnet" => "{$subnet}/{$interface_subnet}", "area" => $ospfd_conf['area']);
				}
 			}
		}


		$redist = "";
		$noredist = "";
		if (is_array($ospfd_conf['row'])) {
			foreach ($ospfd_conf['row'] as $redistr) {
				if (empty($redistr['routevalue'])) {
					continue;
				}
				if (isset($redistr['acceptfilter'])) {
					$noaccept .= "ip prefix-list ACCEPTFILTER deny {$redistr['routevalue']}\n";
				}
				if (isset($redistr['redistribute'])) {
					$noredist .= "  access-list dnr-list deny {$redistr['routevalue']}\n";
				} else {
					$area = ($redistr['routearea'] == "") ? $ospfd_conf['area'] : $redistr['routearea'];
					$redist .= "  network {$redistr['routevalue']} area {$area}\n";
				}
			}
		}

		/* OSPF Settings */

		$conffile .= "\n\nrouter ospf\n";

		// Specify router id
		if ($ospfd_conf['routerid']) {
			$conffile .= "  ospf router-id {$ospfd_conf['routerid']}\n";
		}
		/* In package versions <=0.6.15, this used to a checkbox handling stub areas only
		 * Leave the first check here
		 */
		if ($ospfd_conf['updatefib'] == "on" ) {
			$conffile .= "  area {$ospfd_conf['area']} stub\n";
		} elseif ($ospfd_conf['updatefib'] == "stub") {
			$conffile .= "  area {$ospfd_conf['area']} stub\n";
		} elseif ($ospfd_conf['updatefib'] == "nosum") {
			$conffile .= "  area {$ospfd_conf['area']} stub no-summary\n";
		} elseif ($ospfd_conf['updatefib'] == "nssa") {
			$conffile .= "  area {$ospfd_conf['area']} nssa\n";
		}
		if ($ospfd_conf['logging'] && $ospfd_conf['adjacencylog']) {
			$conffile .= "  log-adjacency-changes detail\n";
		}
		if ($ospfd_conf['redistributeconnectedsubnets']) {
			$conffile .= "  redistribute connected\n";
		}
		if ($ospfd_conf['redistributestatic']) {
			$conffile .= "  redistribute static\n";
		}
		if ($ospfd_conf['redistributekernel']) {
			$conffile .= "  redistribute kernel\n";
		}
		if ($ospfd_conf['redistributedefaultroute']) {
			$conffile .= "  default-information originate\n";
		}
		if ($ospfd_conf['spfholdtime'] || $ospfd_conf['spfdelay']) {
			$spf_minhold = ($ospfd_conf['spfholdtime']) ? $ospfd_conf['spfholdtime'] : 1000;
			$spf_maxhold = $spf_minhold * 10;
			$spf_delay = ($ospfd_conf['spfdelay']) ? $ospfd_conf['spfdelay'] : 200;
			$conffile .= "  timers throttle spf {$spf_delay} {$spf_minhold} {$spf_maxhold}\n";
		}
		if ($ospfd_conf['rfc1583']) {
			$conffile .= "  ospf rfc1583compatibility\n";
		}
		if (is_array($passive_interfaces)) {
			foreach ($passive_interfaces as $pint)
				$conffile .= "  passive-interface {$pint}\n";
		}
		if (is_array($interface_networks)) {
			foreach ($interface_networks as $ifn) {
				if (is_subnet($ifn['subnet'])) {
					$conffile .= "  network {$ifn['subnet']} area {$ifn['area']}\n";
				}
			}
		}
		if ($conf['md5password'] && !empty($conf['password']) && !empty($conf['interfacearea'])) {
			$conffile .= "  area {$conf['interfacearea']} authentication message-digest\n";
		} elseif ($conf['md5password'] && !empty($conf['password']) && empty($conf['interfacearea'])) {
			$conffile .= "  area 0.0.0.0 authentication message-digest\n";
		}
		if ($conf['password'] && empty($conf['md5password']) && !empty($conf['interfacearea'])) {
			$conffile .= "  area {$conf['interfacearea']} authentication\n";
		} elseif ($conf['password'] && empty($conf['md5password']) && empty($conf['interfacearea'])) {
			$conffile .= "  area 0.0.0.0 authentication\n";
		}
		if (!empty($redist)) {
			$conffile .= $redist;
		}
		if (!empty($noredist)) {
			$conffile .= "  distribute-list dnr-list out connected\n";
			$conffile .= "  distribute-list dnr-list out kernel\n";
			$conffile .= "  distribute-list dnr-list out static\n";
			//$conffile .= "  distribute-list dnr-list out ospf\n";
			$conffile .= $noredist;
			$conffile .= "  access-list dnr-list permit any\n";
		}

	}
	safe_mkdir($quagga_config_base);
	$fd = fopen("{$quagga_config_base}/ospfd.conf", "w");

	// Write out the configuration file
	fwrite($fd, $conffile);

	// Close file handle
	fclose($fd);

	/* Make zebra config */
	if (isset($config['installedpackages']['quaggaospfdraw']['config'][0]['zebra'])
	    && !empty($config['installedpackages']['quaggaospfdraw']['config'][0]['zebra'])) {
		// if there is a raw config specifyed in tthe config.xml use that instead of the assisted config
		$zebraconffile = str_replace("\r", "", base64_decode($config['installedpackages']['quaggaospfdraw']['config'][0]['zebra']));
	} else {
		$zebraconffile = "# This file was created by the pfSense package manager.  Do not edit!\n\n";
		if ($ospfd_conf['password']) {
			$zebraconffile .= "password {$ospfd_conf['password']}\n";
		}
		if ($ospfd_conf['logging']) {
			$zebraconffile .= "log syslog\n";
		}
		if (!empty($noaccept)) {
			$zebraconffile .= $noaccept;
			$zebraconffile .= "ip prefix-list ACCEPTFILTER permit any\n";
			$zebraconffile .= "route-map ACCEPTFILTER permit 10\n";
			$zebraconffile .= "match ip address prefix-list ACCEPTFILTER\n";
			$zebraconffile .= "ip protocol ospf route-map ACCEPTFILTER\n";
		}
	}
	$fd = fopen("{$quagga_config_base}/zebra.conf", "w");
	fwrite($fd, $zebraconffile);
	fclose($fd);

	$carp_ip_status_check = "";
	if (isset($ospfd_conf['carpstatusvid']) && $ospfd_conf['carpstatusvid'] != "none") {
		$vip = get_configured_vip($ospfd_conf['carpstatusvid']);
		$carpcheckinterface = escapeshellarg(get_real_interface($vip['interface']));
		$vhid = escapeshellarg("vhid {$vip['vhid']}");
		$carp_ip_status_check = <<<EOF

CARP_STATUS=`/sbin/ifconfig {$carpcheckinterface} | /usr/bin/grep 'carp:' | /usr/bin/grep {$vhid} | /usr/bin/awk '{print \$2;}'`
if [ \${CARP_STATUS} != "MASTER" ]; then
	exit;
fi
EOF;
	}


	// Create rc.d file
	$rc_file_stop = <<<EOF
if [ -e /var/run/quagga/zebra.pid ]; then
	/bin/kill -9 `/bin/cat /var/run/quagga/zebra.pid`
	/bin/rm -f /var/run/quagga/zebra.pid
fi
if [ -e /var/run/quagga/ospfd.pid ]; then
	/bin/kill -9 `/bin/cat /var/run/quagga/ospfd.pid`
	/bin/rm -f /var/run/quagga/ospfd.pid
fi
EOF;
	$rc_file_start = <<<EOF
/bin/mkdir -p /var/run/quagga
/bin/mkdir -p /var/log/quagga
/bin/rm -f /var/run/quagga/zebra.pid
/bin/rm -f /var/run/quagga/ospfd.pid

/usr/sbin/chown -R quagga:quagga {$quagga_config_base}
/usr/sbin/chown -R quagga:quagga /var/run/quagga
/usr/sbin/chown -R quagga:quagga /var/log/quagga
# Ensure no other copies of the daemons are running or it breaks.
/usr/bin/killall -9 zebra 2>/dev/null
/usr/bin/killall -9 ospfd 2>/dev/null
sleep 1
{$carp_ip_status_check}
/usr/local/sbin/zebra -d -f {$quagga_config_base}/zebra.conf
/usr/local/sbin/ospfd -d -f {$quagga_config_base}/ospfd.conf
EOF;
	write_rcfile(array(
			"file" => "quagga.sh",
			"start" => $rc_file_start,
			"stop" =>  $rc_file_stop
		)
	);

	// Ensure files have correct permissions
	mwexec("/bin/chmod a+rx /usr/local/etc/rc.d/quagga.sh");
	mwexec("/bin/chmod u+rw,go-rw {$quagga_config_base}/ospfd.conf");
	mwexec("/bin/chmod u+rw,go-rw {$quagga_config_base}/zebra.conf");

	// Kick off newly created rc.d script
	if (isset($ospfd_conf['carpstatusvid']) && $ospfd_conf['carpstatusvid'] != "none") {
		$status = get_carp_interface_status($ospfd_conf['carpstatusvid']);
		switch (strtoupper($status)) {
			// Stop the service if the VIP is in BACKUP or INIT state.
			case "BACKUP":
			case "INIT":
				mwexec_bg("/usr/local/etc/rc.d/quagga.sh stop");
				break;
			// Start the service if the VIP is MASTER state.
			case "MASTER":
			// Assume it's up if the status can't be determined.
			default:
				mwexec_bg("/usr/local/etc/rc.d/quagga.sh restart");
				break;
		}
	} else {
		mwexec_bg("/usr/local/etc/rc.d/quagga.sh restart");
	}

	// Back to RO mount for NanoBSD and friends
	conf_mount_ro();
}

function quagga_ospfd_validate_interface() {
	global $config, $id, $input_errors;

	if ($config['installedpackages']['quaggaospfdinterfaces']['config']) {
		foreach ($config['installedpackages']['quaggaospfdinterfaces']['config'] as $index => $conf) {
			if ($index == 0) {
				continue;
			}
			if ($id != $index && $conf['interface'] == $_POST['interface']) {
				$input_errors[] = "Interface {$_POST['interface']} is already configured.";
			}
		}
	}
	if ($_POST['md5password'] && empty($_POST['password'])) {
		$input_errors[] = "Please input a password.";
	}
}

function quagga_ospfd_validate_input() {
	global $config, $input_errors;

	if ($_POST['password'] <> "" && (strpos($_POST['password'], "'") !== false)) {
		$input_errors[] = "Password cannot contain a single quote (')";
	}
	if (!empty($_POST['routerid']) && !is_ipaddr($_POST['routerid'])) {
		$input_errors[] = "Router ID must be an address.";
	}
	if (!is_ipaddr($_POST['area'])) {
		$input_errors[] = "Area needs to be a valid ip_address.";
	}
	if ($_POST['spfholdtime'] <> "" && ($_POST['spfholdtime'] < 1 || $_POST['spfholdtime'] > 5)) {
		$input_errors[] = "SPF holdtime needs to be between 1 and 5.";
	}
	if ($_POST['spfdelay'] <> "" && ($_POST['spfdelay'] < 1 || $_POST['spfdelay'] > 10)) {
		$input_errors[] = "SPF delay needs to be between 1 and 10.";
	}
	if (!$config['installedpackages']['quaggaospfdinterfaces']['config']) {
		$input_errors[] = "Please select an interface to use for Quagga OSPFd.";
	}
}

function quagga_ospfd_plugin_carp($pluginparams) {
	global $config;
	require_once("service-utils.inc");
	// Called when a CARP interface changes state
	// $pluginparams['event'] either 'rc.carpmaster' or 'rc.carpbackup'
	// $pluginparams['interface'] contains the affected interface

	/* If there is no OSPF config, then stop */
	if (is_array($config['installedpackages']['quaggaospfd']['config'])) {
		$ospfd_conf = &$config['installedpackages']['quaggaospfd']['config'][0];
	} else {
		return null;
	}
	/* If there is no properly configured CARP status check IP, then stop */
	if (!isset($ospfd_conf['carpstatusvid']) || $ospfd_conf['carpstatusvid'] == "none") {
		return null;
	}
	list($vhid, $iface) = explode("@", trim($pluginparams['interface']));
	$friendly = convert_real_interface_to_friendly_interface_name($iface);
	$vip = get_configured_vip($ospfd_conf['carpstatusvid']);
	if ($vip['vhid'] != $vhid || $vip['interface'] != $friendly) {
		return null;
	}

	/* Start or stop the service as needed based on the CARP transition. */
	if ($pluginparams['event'] == "rc.carpmaster") {
		start_service("Quagga OSPFd");
	} elseif ($pluginparams['event'] == "rc.carpbackup") {
		stop_service("Quagga OSPFd");
	}
}
?>
