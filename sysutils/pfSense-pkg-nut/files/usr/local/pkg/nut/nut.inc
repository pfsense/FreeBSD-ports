<?php
/*
 * nut.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016-2024 Denny Page
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
require_once("util.inc");
require_once("service-utils.inc");

define('LOG_PREFIX_PKG_NUT', 'NUT');

const NUT_ETC = "/usr/local/etc/nut";
const NUT_MONFILE = NUT_ETC . "/upsmon.conf";
const NUT_UPSFILE = NUT_ETC . "/ups.conf";
const NUT_UPSDFILE = NUT_ETC . "/upsd.conf";
const NUT_USERSFILE = NUT_ETC . "/upsd.users";
const NUT_RCFILE = "/usr/local/etc/rc.d/nut.sh";
const NUT_SHUTDOWNFILE = "/usr/local/etc/rc.d/shutdown.nut.sh";

$shortcut_section = "nut";

function nut_check_var_db() {
	/*
		Directory /var/db/nut is actually owned by the nut package itself, however
		there are a couple of circumstance in which it might not exist:

		The prior nut package incorrectly assumed ownership of the directory and
		removed it upon deinstall. If the pfSense nut package was reinstalled, but
		the nut package itself was not, the directory will be missing.

		The other situation in which the directory will be missing is when /var is
		a ram based filesystem. In this case, the contents of /var are lost at each
		reboot and must be recreated.
	*/

	$d = "/var/db/nut";
	if (!is_dir($d)) {
		mkdir($d, 0750, true);
		safe_mkdir($d, 0750);
		chown($d, "nut");
		chgrp($d, "nut");
	}
}

function nut_write_file($file, $content, $perm) {
	$fd = fopen($file, "w");
	if (!$fd) {
		logger(LOG_ERR, localize_text("Could not open %s for writing", $file), LOG_PREFIX_PKG_NUT);
		exit;
	}
	chmod($file, $perm);
	fwrite($fd, $content);
	fclose($fd);
}


function nut_write_rcfile($driver) {
	nut_check_var_db();

	$start = "echo starting NUT";
	$start .= "\n	/usr/bin/killall -q -9 upsmon";
	$start .= "\n	/usr/bin/killall -q -9 upsd";
	$start .= "\n	/usr/bin/killall -q -9 upsdrvctl";
	if (isset($driver)) {
		$start .= "\n	/usr/bin/killall -q -9 $driver";
	}

	if (isset($driver)) {
		$start .= "\n	/usr/local/sbin/upsdrvctl start &";
		$start .= "\n	/usr/local/sbin/upsd -u root";

		/*
		 * Since we are starting the driver in backgroud, give
		 * the driver and upsd a moment to start.
		 */
		$start .= "\n	sleep 1";
	}

	/* NB: Service status keys off of upsmon. */
	$start .= "\n	/usr/local/sbin/upsmon";
	$start .= "\n	return 0";

	$stop = "echo stopping NUT";
	$stop .= "\n	/usr/bin/killall -q upsmon";
	if (isset($driver)) {
		$stop .= "\n	/usr/bin/killall -q upsd";
		$stop .= "\n	/usr/bin/killall -q upsdrvctl";
		$stop .= "\n	/usr/bin/killall -q $driver";
	}
	$stop .= "\n	return 0";

	write_rcfile(array(
		"file" => "nut.sh",
		"start" => $start,
		"stop" => $stop
		)
	);
}


function nut_write_shutdownfile() {
	$shutdownfile = "#!/bin/sh\n\n";
	$shutdownfile .= "if /usr/local/sbin/upsmon -K\n";
	$shutdownfile .= "then\n";
	$shutdownfile .= "	echo \"NUT killing power...\" | wall\n";
	$shutdownfile .= "	/usr/local/sbin/upsdrvctl shutdown\n";
	$shutdownfile .= "fi\n";

	nut_write_file(NUT_SHUTDOWNFILE, $shutdownfile, 0755);
}


function nut_sync_config() {
	if (is_service_running('nut')) {
		logger(LOG_INFO, localize_text("Stopping service"), LOG_PREFIX_PKG_NUT);
		stop_service('nut');
	}

	/* Is the service enabled? */
	$ups_type = config_get_path('installedpackages/nut/config/0/type', 'disabled');
	if ($ups_type == 'disabled') {
		unlink_if_exists(NUT_MONFILE);
		unlink_if_exists(NUT_UPSFILE);
		unlink_if_exists(NUT_UPSDFILE);
		unlink_if_exists(NUT_USERSFILE);
		unlink_if_exists(NUT_RCFILE);
		unlink_if_exists(NUT_SHUTDOWNFILE);
		return;
	}

	/* UPS name */
	$ups_name = config_get_path('installedpackages/nut/config/0/name');

	/* If it's a remote NUT server, we only need monitor information. */
	if ($ups_type == 'remote_nut') {
		$monitor = $ups_name . "@" . config_get_path('installedpackages/nut/config/0/remote_addr');

		$addport = config_get_path('installedpackages/nut/config/0/remote_port');
		if (!empty($addport)) {
			$monitor .= ":" . $addport;
		}

		$monitor .= " 1 " . config_get_path('installedpackages/nut/config/0/remote_user') . " " .
				    config_get_path('installedpackages/nut/config/0/remote_pass') . " slave";
	} else {
		/* Define driver, port and arguments. */
		switch ($ups_type) {
			case 'local_usb':
				$driver = config_get_path('installedpackages/nut/config/0/usb_driver');
				$port = "auto";
				break;

			case 'local_serial':
				$driver = config_get_path('installedpackages/nut/config/0/serial_driver');
				$port = config_get_path('installedpackages/nut/config/0/serial_port');
				break;

			case 'local_generic':
				$driver = "genericups";
				$port = config_get_path('installedpackages/nut/config/0/serial_port');
				$args = "upstype=" . config_get_path('installedpackages/nut/config/0/generic_type');
				break;

			case 'remote_apcupsd':
				$driver = "apcupsd-ups";
				$port = config_get_path('installedpackages/nut/config/0/remote_addr');
				$addport = config_get_path('installedpackages/nut/config/0/remote_port');
				if (!empty($addport)) {
					$port .= ":" . $addport;
				}
				break;

			case 'remote_netxml':
				$driver = "netxml-ups";
				$port = config_get_path('installedpackages/nut/config/0/remote_proto') . "://" .
					config_get_path('installedpackages/nut/config/0/remote_addr');

				$addport = config_get_path('installedpackages/nut/config/0/remote_port');
				if (!empty($addport)) {
					$port .= ":" . $addport;
				}

				$user = config_get_path('installedpackages/nut/config/0/remote_user');
				if (!empty($user)) {
					$args = "login=\"" . $user . "\"";
					$pass = config_get_path('installedpackages/nut/config/0/remote_pass');
					if (!empty($pass)) {
						$args .= "\npassword=\"" . $pass . "\"";
					}
				}
				break;

			case 'remote_snmp':
				$driver = "snmp-ups";
				$port = config_get_path('installedpackages/nut/config/0/remote_addr');
				break;

			case 'dummy':
				$driver = "dummy-ups";
				$port = config_get_path('installedpackages/nut/config/0/dummy_port');
				break;
		}

		/* Set up ups.conf */
		$upsfile = "";

		/* Add any global ups.conf extras */
		$extras = config_get_path('installedpackages/nut/config/0/ups_conf');
		if (!empty($extras)) {
			$upsfile .= base64_decode($extras) . "\n\n";
		}

		/* UPS section */
		$upsfile .= "[" . $ups_name . "]\n";
		$upsfile .= "driver=" . $driver . "\n";
		$upsfile .= "port=" . $port . "\n";
		if (!empty($args)) {
			$upsfile .= $args . "\n";
		}

		/* Add any driver extras */
		$extras = config_get_path('installedpackages/nut/config/0/extra_args');
		if (!empty($extras)) {
			$upsfile .= base64_decode($extras) . "\n";
		}

		/* Set up upsd.conf */
		$upsdfile = "LISTEN 127.0.0.1\n";
		$upsdfile .= "LISTEN ::1\n";
		/* Add any upsd.conf extras */
		$extras = config_get_path('installedpackages/nut/config/0/upsd_conf');
		if (!empty($extras)) {
			$upsdfile .= "\n\n" . base64_decode($extras) . "\n";
		}

		/* Local user for monitoring */
		$user = "local-monitor";
		$pass = bin2hex(openssl_random_pseudo_bytes(10));
		$monitor = $ups_name . " 1 " . $user . " " . $pass . " master";

		/* Set up upsd.users */
		$usersfile = "[admin]\n";
		$usersfile .= "password=" . bin2hex(openssl_random_pseudo_bytes(10)) . "\n";
		$usersfile .= "actions=set\n";
		$usersfile .= "instcmds=all\n";
		$usersfile .= "[" . $user . "]\n";
		$usersfile .= "password=" . $pass . "\n";
		$usersfile .= "upsmon master\n";

		/* Add any upsd.users extras */
		$extras = config_get_path('installedpackages/nut/config/0/upsd_users');
		if (!empty($extras)) {
			$usersfile .= "\n\n" . base64_decode($extras) . "\n";
		}
	}


	/* Set up upsmon.conf */
	$monfile = "MONITOR " . $monitor . "\n";
	$monfile .= "SHUTDOWNCMD \"/sbin/shutdown -p +0\"\n";
	$monfile .= "POWERDOWNFLAG /etc/killpower\n";

	/* Event notifications? */
	if (config_get_path('installedpackages/nut/config/0/email') == 'yes') {
		$monfile .= "NOTIFYCMD /usr/local/pkg/nut/nut_email.php\n";
		$monfile .= "NOTIFYFLAG ONLINE   SYSLOG+WALL+EXEC\n";
		$monfile .= "NOTIFYFLAG ONBATT   SYSLOG+WALL+EXEC\n";
		$monfile .= "NOTIFYFLAG LOWBATT  SYSLOG+WALL+EXEC\n";
		$monfile .= "NOTIFYFLAG FSD      SYSLOG+WALL+EXEC\n";
		$monfile .= "NOTIFYFLAG COMMOK   SYSLOG+WALL+EXEC\n";
		$monfile .= "NOTIFYFLAG COMMBAD  SYSLOG+WALL+EXEC\n";
		$monfile .= "NOTIFYFLAG SHUTDOWN SYSLOG+WALL+EXEC\n";
		$monfile .= "NOTIFYFLAG REPLBATT SYSLOG+WALL+EXEC\n";
		$monfile .= "NOTIFYFLAG NOCOMM   SYSLOG+WALL+EXEC\n";
		$monfile .= "NOTIFYFLAG NOPARENT SYSLOG+WALL+EXEC\n";
	}

	/* Add any upsmon.conf extras */
	$extras = config_get_path('installedpackages/nut/config/0/upsmon_conf');
	if (!empty($extras)) {
		$monfile .= "\n\n" . base64_decode($extras) . "\n";
	}

	/* Write out the files */
	if (!empty($upsfile)) {
		nut_write_file(NUT_UPSFILE, $upsfile, 0640);
	} else {
		unlink_if_exists(NUT_UPSFILE);
	}
	if (!empty($upsdfile)) {
		nut_write_file(NUT_UPSDFILE, $upsdfile, 0640);
	} else {
		unlink_if_exists(NUT_UPSDFILE);
	}
	if (!empty($usersfile)) {
		nut_write_file(NUT_USERSFILE, $usersfile, 0640);
	} else {
		unlink_if_exists(NUT_USERSFILE);
	}
	nut_write_file(NUT_MONFILE, $monfile, 0640);
	nut_write_rcfile($driver);
	nut_write_shutdownfile();

	if (platform_booting()) {
		return;
	}

	logger(LOG_INFO, localize_text("Starting service"), LOG_PREFIX_PKG_NUT);
	start_service('nut');
	sleep(3);
}


function nut_ups_status()
{
	$status = array();

	/* Is the service enabled? */
	$ups_type = config_get_path('installedpackages/nut/config/0/type', 'disabled');
	if ($ups_type == 'disabled') {
		$status['_summary'] = "UPS monitoring is not enabled";
		return $status;
	}

	/* Name of the ups */
	$ups_name = config_get_path('installedpackages/nut/config/0/name');
	if ($ups_type == 'remote_nut') {
		$addr = config_get_path('installedpackages/nut/config/0/remote_addr');
		if (is_ipaddrv6($addr)) {
			/* upsc requires brackets for ipv6 addresses */
			$ups_name .= "@[" . $addr . "]";
		} else {
			$ups_name .= "@" . $addr;
		}
		$port = config_get_path('installedpackages/nut/config/0/remote_port');
		if (!empty($port)) {
			$ups_name .= ":" . $port;
		}
	} else {
		$ups_name .= "@" . "localhost";
	}
	$status['_name'] = $ups_name;

	/* Even though upsc might actually work (no password) it's better to report failure of upsmon */
	if (!is_service_running('nut')) {
		if (get_uptime_sec() < 60) {
			$status['_summary'] = "UPS Monitor pending";
		} else {
			$status['_summary'] = "UPS Monitor not running";
			$status['_alert'] = true;
		}
		return $status;
	}

	/* Pull the fields from upsc */
	$pipe = popen("/usr/local/bin/upsc $ups_name", 'r');
	if ($pipe) {
		while ($line = fgets($pipe)) {
			$index = strpos($line, ':');
			if ($index !== false) {
				$key = trim(substr($line, 0, $index));
				$val = trim(substr($line, $index + 1));
				$status[$key] = $val;
			}
		}
		pclose($pipe);
	}

	if (count($status) < 2) {
		/* If we are using a local upsd, allow it a minute to initialize before declaring an alert */
		if (file_exists("/var/db/nut/upsd.pid")) {
			$stat = stat("/var/db/nut/upsd.pid");
			if (time() - $stat['mtime'] < 60) {
				$status['_summary'] = "UPS Daemon pending";
				return $status;
			}
		}

		$status['_summary'] = "Failed to retrieve status";
		$status['_alert'] = true;
		return $status;
	}

	/* Status => meaningful words */
	if (isset($status['ups.status'])) {
		$str = strtok($status['ups.status'], ' ');
		while ($str) {
			switch ($str) {
				case 'OL':
					$summary .= "On line";
					break;
				case 'OB':
					$summary .= "On battery";
					$alert = true;
					break;
				case 'LB':
					$summary .= "Low battery";
					$alert = true;
					break;
				case 'HB':
					$summary .= "High battery";
					break;
				case 'RB':
					$summary .= "Replace battery";
					$alert = true;
					break;
				case 'CHRG':
					$summary .= "Charging";
					break;
				case 'DISCHRG':
					$summary .= "Discharging";
					break;
				case 'BYPASS':
					$summary .= "Bypass";
					$alert = true;
					break;
				case 'CAL':
					$summary .= "Calibrating";
					break;
				case 'OVER':
					$summary .= "Overloaded";
					$alert = true;
					break;
				case 'TRIM':
					$summary .= "Voltage trim";
					break;
				case 'BOOST':
					$summary .= "Voltage boost";
					break;
				default:
					$summary .= $str;
			}

			$str = strtok(' ');
			if ($str) {
				$summary .= ", ";
			}
		}

		$status['_summary'] = $summary;
	}

	if ($alert || isset($status['ups.alarm'])) {
		$status['_alert'] = true;
	}

	/* Runtime => hours, minutes, seconds */
	if (isset($status['battery.runtime'])) {
		$t = (int) $status['battery.runtime'];

		$s = $t % 60;
		$t /= 60;
		$m = $t % 60;
		$h = $t / 60;

		$status['_hms'] = sprintf("%d:%02d:%02d", $h, $m, $s);
	}

	return $status;
}


function nut_deinstall_command() {
	if (is_service_running('nut')) {
		logger(LOG_INFO, localize_text("Stopping service"), LOG_PREFIX_PKG_NUT);
		stop_service('nut');
	}

	unlink_if_exists(NUT_MONFILE);
	unlink_if_exists(NUT_UPSFILE);
	unlink_if_exists(NUT_UPSDFILE);
	unlink_if_exists(NUT_USERSFILE);
	unlink_if_exists(NUT_RCFILE);
	unlink_if_exists(NUT_SHUTDOWNFILE);
}
