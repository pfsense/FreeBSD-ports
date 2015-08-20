<?php
/*
	quagga_ospfd.inc
	Copyright (C) 2010 Ermal Lu�i
	Copyright (C) 2012 Jim Pingle
	part of pfSense
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
require_once("config.inc");
require_once("functions.inc");
require_once("service-utils.inc");

define('PKG_QUAGGA_CONFIG_BASE', '/var/etc/quagga');

$pkg_login	= "quagga";
$pkg_uid	= "101";
$pkg_group	= "quagga";
$pkg_gid	= "101";
$pkg_gecos	= "Quagga route daemon pseudo user";
$pkg_homedir	= "/var/etc/quagga";
$pkg_shell	= "/usr/sbin/nologin";

function quagga_ospfd_get_interfaces() {
	global $config;
	$interfaces = get_configured_interface_with_descr();
	$ospf_ifs = array();
	foreach ($interfaces as $iface => $ifacename) {
		$tmp["name"]  = $ifacename;
		$tmp["value"] = $iface;
		$ospf_ifs[] = $tmp;
	}

	foreach (array('server', 'client') as $mode) {
		if (is_array($config['openvpn']["openvpn-{$mode}"])) {
			foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
				if (!isset($setting['disable'])) {
					$tmp["name"]  = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
					$tmp["value"] = 'ovpn' . substr($mode, 0, 1) . $setting['vpnid'];
					$ospf_ifs[] = $tmp;
				}
			}
		}
	}

	return $ospf_ifs;
}

function quagga_ospfd_install_conf() {
	global $config, $g, $input_errors, $pkg_login, $pkg_uid, $pkg_group, $pkg_gid, $pkg_gecos, $pkg_homedir, $pkg_shell;

	conf_mount_rw();
	// Since we need to embed this in a string, copy to a var. Can't embed constnats.
	$quagga_config_base = PKG_QUAGGA_CONFIG_BASE;

	$noaccept = "";
	
	// generate ospfd.conf based on the assistant
	if(is_array($config['installedpackages']['quaggaospfd']['config']))
		$ospfd_conf = &$config['installedpackages']['quaggaospfd']['config'][0];
	else {
		log_error("Quagga OSPFd: No config data found.");
		return;
	}

	if (isset($config['installedpackages']['quaggaospfdraw']['config'][0]['ospfd'])
	    && !empty($config['installedpackages']['quaggaospfdraw']['config'][0]['ospfd'])) {
		// if there is a raw config specifyed in tthe config.xml use that instead of the assisted config
		$conffile = str_replace("\r","",base64_decode($config['installedpackages']['quaggaospfdraw']['config'][0]['ospfd']));
	} else {
		$conffile = "# This file was created by the pfSense package manager.  Do not edit!\n\n";

		if($ospfd_conf['password'])
			$conffile .= "password {$ospfd_conf['password']}\n";

		if ($ospfd_conf['logging'])
			$conffile .= "log syslog\n";

		/* Interface Settings */
		$passive_interfaces = array();
		$interface_networks = array();

		if (is_array($config['installedpackages']['quaggaospfdinterfaces']['config'])) {
			foreach ($config['installedpackages']['quaggaospfdinterfaces']['config'] as $conf) {
				$realif = get_real_interface($conf['interface']);
				$conffile .= "interface {$realif}\n" ;
				if (!empty($conf['metric'])) {
					$conffile .= "  ip ospf cost {$conf['metric']}\n";
				}
				if (!empty($conf['hellointervalinseconds'])) {
					$conffile .= "  ip ospf hello-interval {$conf['hellointervalinseconds']}\n";
				}
				if ($conf['md5password'] && !empty($conf['password'])) {
					$conffile .= "  ip ospf authentication message-digest\n";
					$conffile .= "  ip ospf message-digest-key 1 md5 " . substr($conf['password'], 0, 15) . "\n";
				} else if (!empty($conf['password'])) {
					$conffile .= "  ip ospf authentication-key " . substr($conf['password'], 0, 8) . "\n";
				}
				if (!empty($conf['routerpriorityelections'])) {
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
				if ($interface_subnet == 32)
					$interface_subnet = 30;
				$subnet = gen_subnet($interface_ip, $interface_subnet);
				if (!empty($conf['acceptfilter'])) {
					$noaccept .= "ip prefix-list ACCEPTFILTER deny {$subnet}/{$interface_subnet}\n";
				}
				if (!empty($conf['interfacearea'])) {
					$interface_networks[] = array( "subnet" => "{$subnet}/{$interface_subnet}", "area" => $conf['interfacearea']);
				}
				else {
					$interface_networks[] = array( "subnet" => "{$subnet}/{$interface_subnet}", "area" => $ospfd_conf['area']);
				}
				


 			}
		}
		

		$redist = "";
		$noredist = "";
		if (is_array($ospfd_conf['row'])) {
			foreach ($ospfd_conf['row'] as $redistr) {
				if (empty($redistr['routevalue']))
					continue;
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
		if($ospfd_conf['routerid'])
			$conffile .= "  ospf router-id {$ospfd_conf['routerid']}\n";

		if ($ospfd_conf['updatefib'])
			$conffile .= "  area {$ospfd_conf['area']} stub\n";

		if ($ospfd_conf['logging'] && $ospfd_conf['adjacencylog'])
			$conffile .= "  log-adjacency-changes detail\n";

		if ($ospfd_conf['redistributeconnectedsubnets'])
			$conffile .= "  redistribute connected\n";

		if ($ospfd_conf['redistributestatic'])
			$conffile .= "  redistribute static\n";
		
		if ($ospfd_conf['redistributekernel'])
			$conffile .= "  redistribute kernel\n";
		

		if ($ospfd_conf['redistributedefaultroute'])
			$conffile .= "  default-information originate\n";

		if ($ospfd_conf['spfholdtime'] || $ospfd_conf['spfdelay']) {
			$spf_minhold = ($ospfd_conf['spfholdtime']) ? $ospfd_conf['spfholdtime'] : 1000;
			$spf_maxhold = $spf_minhold * 10;
			$spf_delay = ($ospfd_conf['spfdelay']) ? $ospfd_conf['spfdelay'] : 200;
			$conffile .= "  timers throttle spf {$spf_delay} {$spf_minhold} {$spf_maxhold}\n";
		}

		if ($ospfd_conf['rfc1583'])
			$conffile .= "  ospf rfc1583compatibility\n";

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

		if (!empty($redist))
			$conffile .= $redist;

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
		$zebraconffile = str_replace("\r","",base64_decode($config['installedpackages']['quaggaospfdraw']['config'][0]['zebra']));
	} else {
		$zebraconffile = "# This file was created by the pfSense package manager.  Do not edit!\n\n";
		if($ospfd_conf['password'])
			$zebraconffile .= "password {$ospfd_conf['password']}\n";
		if ($ospfd_conf['logging'])
			$zebraconffile .= "log syslog\n";
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
	if (is_ipaddr($ospfd_conf['carpstatusip'])) {

		$pfs_version = substr(trim(file_get_contents("/etc/version")),0,3);
		switch ($pfs_version) {
			case "2.0":
			case "2.1":
				/* Check for 2.1 and before */
				$carpcheckinterface = trim(find_carp_interface($ospfd_conf['carpstatusip']));
				$carp_ip_status_check = <<<EOF

CARP_STATUS=`/sbin/ifconfig {$carpcheckinterface} | /usr/bin/grep carp: | /usr/bin/awk '{print \$2;}'`
if [ \${CARP_STATUS} != "MASTER" ]; then
	exit;
fi

EOF;
				break;
			case "2.2":
			default:
				/* Check for 2.2 and later */
				if (is_array($config['virtualip']['vip'])) {
					foreach ($config['virtualip']['vip'] as $vip) {
						if (($vip['mode'] == "carp") && ($vip['subnet'] == $ospfd_conf['carpstatusip'])) {
							$carpcheckinterface = escapeshellarg(get_real_interface($vip['interface']));
							$vhid = escapeshellarg($vip['vhid']);
							$carp_ip_status_check = <<<EOF

CARP_STATUS=`/sbin/ifconfig {$carpcheckinterface} | /usr/bin/grep 'carp:' | /usr/bin/grep 'vhid {$vhid}' | /usr/bin/awk '{print \$2;}'`
if [ \${CARP_STATUS} != "MASTER" ]; then
	exit;
fi
EOF;
						}
					}
				}
				break;
		}
	}


	// Create rc.d file
	$rc_file_stop = <<<EOF
if [ -e /var/run/quagga/zebra.pid ]; then
	kill -9 `cat /var/run/quagga/zebra.pid`
	rm -f /var/run/quagga/zebra.pid
fi
if [ -e /var/run/quagga/ospfd.pid ]; then
	kill -9 `cat /var/run/quagga/ospfd.pid`
	rm -f /var/run/quagga/ospfd.pid
fi
EOF;
	$rc_file_start = <<<EOF
/bin/mkdir -p /var/run/quagga
/bin/mkdir -p /var/log/quagga
rm -f /var/run/quagga/zebra.pid
rm -f /var/run/quagga/ospfd.pid

if [ `pw groupshow {$pkg_group} 2>&1 | grep -c "pw: unknown group"` -gt 0 ]; then
	/usr/sbin/pw groupadd {$pkg_group} -g {$pkg_gid}
fi
if [ `pw usershow {$pkg_login} 2>&1 | grep -c "pw: no such user"` -gt 0 ]; then
	/usr/sbin/pw useradd {$pkg_login} -u {$pkg_uid} -g {$pkg_gid} -c "{$pkg_gecos}" -d {$pkg_homedir} -s {$pkg_shell}
fi

/usr/sbin/chown -R quagga:quagga {$quagga_config_base}
/usr/sbin/chown -R quagga:quagga /var/run/quagga
/usr/sbin/chown -R quagga:quagga /var/log/quagga
# Ensure no other copies of the daemons are running or it breaks.
killall -9 zebra 2>/dev/null
killall -9 ospfd 2>/dev/null
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
	exec("chmod a+rx /usr/local/etc/rc.d/quagga.sh");
	exec("chmod u+rw,go-rw {$quagga_config_base}/ospfd.conf");
	exec("chmod u+rw,go-rw {$quagga_config_base}/zebra.conf");

	// Kick off newly created rc.d script
	if (is_ipaddr($ospfd_conf['carpstatusip'])) {
		$status = quagga_get_carp_status_by_ip($ospfd_conf['carpstatusip']);
		switch (strtoupper($status)) {
			// Stop the service if the VIP is in BACKUP or INIT state.
			case "BACKUP":
			case "INIT":
				exec("/usr/local/etc/rc.d/quagga.sh stop");
				break;
			// Start the service if the VIP is MASTER state.
			case "MASTER":
			// Assume it's up if the status can't be determined.
			default:
				exec("/usr/local/etc/rc.d/quagga.sh restart");
				break;
		}
	} else {
		exec("/usr/local/etc/rc.d/quagga.sh restart");
	}

	// Back to RO mount for NanoBSD and friends
	conf_mount_ro();
}

function quagga_ospfd_validate_interface() {
	global $config, $g, $id, $input_errors;

	if ($config['installedpackages']['quaggaospfdinterfaces']['config']) {
		foreach ($config['installedpackages']['quaggaospfdinterfaces']['config'] as $index => $conf) {
			if ($index == 0)
				continue;
			if ($id != $index && $conf['interface'] == $_POST['interface'])
				$input_errors[] = "Interface {$_POST['interface']} is already configured.";
		}
	}
	if ($_POST['md5password'] && empty($_POST['password']))
		$input_errors[] = "Please input a password.";
}

function quagga_ospfd_validate_input() {
	global $config, $g, $input_errors;

	if ($_POST['password'] <> "" && (strpos($_POST['password'], "'") !== false))
		$input_errors[] = "Password cannot contain a single quote (')";
	if (!empty($_POST['routerid']) && !is_ipaddr($_POST['routerid']))
		$input_errors[] = "Router ID must be an address.";
	if (!is_ipaddr($_POST['area']))
		$input_errors[] = "Area needs to be a valid ip_address.";
	if ($_POST['spfholdtime'] <> "" && ($_POST['spfholdtime'] < 1 || $_POST['spfholdtime'] > 5))
		$input_errors[] = "SPF holdtime needs to be between 1 and 5.";
	if ($_POST['spfdelay'] <> "" && ($_POST['spfdelay'] < 1 || $_POST['spfdelay'] > 10))
		$input_errors[] = "SPF delay needs to be between 1 and 10.";
	if (!$config['installedpackages']['quaggaospfdinterfaces']['config'])
		$input_errors[] = "Please select an interface to use for Quagga OSPFd.";
}

function quagga_get_carp_status_by_ip($ipaddr) {
	$iface = trim(find_carp_interface($ipaddr));
	if ($iface) {
		$status = get_carp_interface_status($iface);
		// If there is no status for that interface, return null.
		if (!$status)
			$status = null;
	} else {
		// If there is no VIP by that IP, return null.
		$status = null;
	}
	return $status;
}

function quagga_ospfd_plugin_carp($pluginparams) {
	global $config;
	require_once("service-utils.inc");
	// Called when a CARP interface changes state
	// $pluginparams['event'] either 'rc.carpmaster' or 'rc.carpbackup'
	// $pluginparams['interface'] contains the affected interface

	/* If there is no OSPF config, then stop */
	if(is_array($config['installedpackages']['quaggaospfd']['config'])) {
		$ospfd_conf = &$config['installedpackages']['quaggaospfd']['config'][0];
	} else {
		return null;
	}
	/* If there is no properly configured CARP status check IP, then stop */
	if (!is_ipaddr($ospfd_conf['carpstatusip'])) {
		return null;
	}
	list($vhid, $iface) = explode("@", trim($pluginparams['interface']));
	$friendly = convert_real_interface_to_friendly_interface_name($iface);
	$carp_iface = "{$friendly}_vip${vhid}";

	/* If this CARP transition is not from the IP address to check, then stop. */
	if (get_interface_ip($carp_iface) != $ospfd_conf['carpstatusip']) {
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
