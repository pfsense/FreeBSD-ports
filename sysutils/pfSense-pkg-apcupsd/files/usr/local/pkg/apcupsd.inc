<?php
/*
 * apcupsd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2016 Danilo G. Baio <dbaio@bsd.com.br>
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

function php_install_apcupsd() {
	sync_package_apcupsd();
	apccontrol_scripts_install();
}

function php_deinstall_apcupsd() {
	global $config, $g;

	define('APCUPSD_BASE', '/usr/local');

	unlink_if_exists(APCUPSD_BASE . "/etc/rc.d/apcupsd.sh");
	unlink_if_exists(APCUPSD_BASE . "/etc/apcupsd/apcupsd.conf");
	unlink_if_exists("/var/log/apcupsd.events");
	unlink_if_exists("/var/run/apcupsd.pid");

}

function validate_input_apcupsd($post, &$input_errors) {

	if (isset($post['apcupsdenabled'])) {
		if ($post['polltime'] != '' && !is_numericint($post['polltime'])) {
			$input_errors[] = 'Poll Time is not numeric.';
		}

		if ($post['onbatterydelay'] != '' && !is_numericint($post['onbatterydelay'])) {
			$input_errors[] = 'OnBattery Delay is not numeric.';
		}

		if ($post['batterylevel'] != '' && !is_numericint($post['batterylevel'])) {
			$input_errors[] = 'Battery Level is not numeric.';
		}

		if ($post['minutes'] != '' && !is_numericint($post['minutes'])) {
			$input_errors[] = 'Minutes is not numeric.';
		}

		if ($post['timeout'] != '' && !is_numericint($post['timeout'])) {
			$input_errors[] = 'Timeout is not numeric.';
		}

		if ($post['annoy'] != '' && !is_numericint($post['annoy'])) {
			$input_errors[] = 'Annoy is not numeric.';
		}

		if ($post['annoydelay'] != '' && !is_numericint($post['annoydelay'])) {
			$input_errors[] = 'Annoy Delay is not numeric.';
		}

		if ($post['killdelay'] != '' && !is_numericint($post['killdelay'])) {
			$input_errors[] = 'Kill Delay is not numeric.';
		}

		if ($post['nisip'] != '') {
			if (!is_ipaddr_configured($post['nisip']) && !preg_match("/(127.0.0.1|0.0.0.0)/", $post['nisip'])) {
				$input_errors[] = 'NIS Ip is not a configured IP address.';
			}
		}

		if ($post['nisport'] != '') {
			if (!preg_match("/^\d+$/", $post['nisport'])) {
				$input_errors[] = 'NIS Port is not numeric.';
			}
		}

		if ($post['emailnotification'] == 'smtpbuiltin') {
			if (empty($post['smtphost'])) {
					$input_errors[] = 'SMTP Host cannot be empty if SMTP builtin is enabled.';
			}

			if (empty($post['smtpport'])) {
					$input_errors[] = 'SMTP Port cannot be empty if SMTP builtin is enabled.';
			} elseif (!is_port($post['smtpport'])) {
				$input_errors[]='SMTP Port is not a valid port.';
			}

			if (empty($post['smtpfrom'])) {
					$input_errors[] = 'From field cannot be empty if SMTP builtin is enabled.';
			}

			if (empty($post['smtpto'])) {
					$input_errors[] = 'To field cannot be empty if SMTP builtin is enabled.';
			}
		}

	}
}

function sync_package_apcupsd() {
	global $config, $g;

	conf_mount_rw();

	if (is_service_running('apcupsd') && !file_exists("{$g['tmp_path']}/.rc.start_packages.running")) {
		log_error("Stopping service apcupsd");
		stop_service('apcupsd');
	}

	define('APCUPSD_BASE', '/usr/local');

	// check apcupsd settings
	if (is_array($config['installedpackages']['apcupsd'])) {
		$apcupsd_config = $config['installedpackages']['apcupsd']['config'][0];
		if ($apcupsd_config['apcupsdenabled'] == "on") {
			$upsname = $apcupsd_config['upsname'];
			$upscable = $apcupsd_config['upscable'];
			$upstype = $apcupsd_config['upstype'];
			$device = ($apcupsd_config['device'] != '' ? "DEVICE {$apcupsd_config['device']}" : "#DEVICE");
			$polltime = $apcupsd_config['polltime'] ?: "60";
			$onbatterydelay = $apcupsd_config['onbatterydelay'] ?: "6";
			$batterylevel= $apcupsd_config['batterylevel'] ?: "5";
			$minutes = $apcupsd_config['minutes'] ?: "3";
			$timeout = $apcupsd_config['timeout'] ?: "0";
			$annoy = $apcupsd_config['annoy'] ?: "300";
			$annoydelay = $apcupsd_config['annoydelay'] ?: "60";
			$killdelay = $apcupsd_config['killdelay'] ?: "0";
			$shutdownbehavior = $apcupsd_config['shutdownbehavior'];
			$netserver = $apcupsd_config['netserver'];
			$nisip= $apcupsd_config['nisip'] ?: "0.0.0.0";
			$nisport = $apcupsd_config['nisport'] ?: "3551";
			$upsclass = $apcupsd_config['upsclass'];
			$upsmode = $apcupsd_config['upsmode'];
			$lockfile= $apcupsd_config['lockfile'] ?: "/var/tmp";
			$emailnotification = $apcupsd_config['emailnotification'];

			include("/usr/local/pkg/apcupsd.conf.php");
			file_put_contents(APCUPSD_BASE . "/etc/apcupsd/apcupsd.conf", $apcupsdconf, LOCK_EX);
		}
	}

	// RC FILE
	$apcupsd_rcfile="/usr/local/etc/rc.d/apcupsd.sh";
	if (is_array($apcupsd_config) && $apcupsd_config['apcupsdenabled']=="on") {

		$apcupsd_start = "echo \"Starting APC UPS Daemon...\"\n";
		$apcupsd_start .= "	if [ -f /var/run/apcupsd.pid ]; then \n";
		$apcupsd_start .= "		echo \"Already running\" \n";
		$apcupsd_start .= "		exit 1 \n";
		$apcupsd_start .= "	fi \n";
		$apcupsd_start .= "	if [ ! -d {$lockfile} ]; then \n";
		$apcupsd_start .= "		/bin/mkdir -p {$lockfile} \n";
		$apcupsd_start .= "	fi \n";
		$apcupsd_start .= "	/bin/rm -f {$lockfile}/*LCK* 2>/dev/null \n";
		if ($apcupsd_config['killonpowerfail'] == "on") {
			$apcupsd_start .= "	" . APCUPSD_BASE . "/sbin/apcupsd --kill-on-powerfail";
		} else {
			$apcupsd_start .= "	" . APCUPSD_BASE . "/sbin/apcupsd";
		}

		$apcupsd_stop = "echo \"Stopping APC UPS Daemon...\"\n";
		$apcupsd_stop .= "	/usr/bin/killall apcupsd\n";
		$apcupsd_stop .= "	/bin/sleep 5";

		/* write out rc.d start/stop file */
		write_rcfile(array(
			"file" => "apcupsd.sh",
			"start" => "$apcupsd_start",
			"stop" => "$apcupsd_stop"
			)
		);

		apccontrol_scripts_install($emailnotification,$shutdownbehavior);

		if (!file_exists("{$g['tmp_path']}/.rc.start_packages.running")) {
			log_error("Starting service apcupsd");
			start_service("apcupsd");
		}
	}

	conf_mount_ro();
}

function apccontrol_scripts_install($emailnotification,$shutdownbehavior) {
	global $config, $g;

	define('APCUPSD_BASE', '/usr/local');

	$apcstatus['commfailure'] = "\$HOSTNAME - Communications with UPS \$1 lost";
	$apcstatus['commok'] = "\$HOSTNAME - Communications with UPS \$1 restored";
	$apcstatus['onbattery'] = "\$HOSTNAME - Power failure. Running on UPS \$1 batteries";
	$apcstatus['offbattery'] = "\$HOSTNAME - UPS \$1 Power has returned...";
	$apcstatus['changeme'] = "\$HOSTNAME - Emergency! UPS batteries have failed. Change them NOW";

	$apccontrol_scripts = array("offbattery","onbattery","commfailure","commok","changeme");
	foreach ($apccontrol_scripts as $apccontrol_script) {

		if ($emailnotification == "smtpbuiltin") {

			if (is_array($config['installedpackages']['apcupsd'])) {
				$apcupsd_config = $config['installedpackages']['apcupsd']['config'][0];
				if ($apcupsd_config['apcupsdenabled'] == "on") {
					$smtphost = $apcupsd_config['smtphost'] ?: "0.0.0.0";
					$smtpport = $apcupsd_config['smtpport'] ?: "25";
					$smtpfrom = $apcupsd_config['smtpfrom'];
					$smtpto = $apcupsd_config['smtpto'];
					$smtpcc = $apcupsd_config['smtpcc'];
					if (empty($smtpcc)) {
						$smtpcc_cmd = "";
					} else {
						$smtpcc_cmd = "-c {$smtpcc}";
					}
				}
			}

			$pfSense_hostname = gethostname();

			$apccontrol_script_file =<<<EOF
#!/bin/sh

HOSTNAME="{$pfSense_hostname}"
MSG="{$apcstatus["{$apccontrol_script}"]}"
#
(
   echo " "
   echo "\$MSG"
   echo " "
   /usr/local/sbin/apcaccess status
) | /usr/local/sbin/smtp -s "\$MSG" -h {$smtphost}:{$smtpport} -f {$smtpfrom} {$smtpcc_cmd} {$smtpto}
exit 0

EOF;

		} else {
		$apccontrol_script_file =<<<EOF
#!/bin/sh

/usr/local/bin/php -f /usr/local/pkg/apcupsd_mail.php {$apccontrol_script} > /dev/null

exit 0

EOF;
		}

		file_put_contents(APCUPSD_BASE . "/etc/apcupsd/" . $apccontrol_script, $apccontrol_script_file, LOCK_EX);
	}

	if ($shutdownbehavior == "poweroff") {
		$doshutdown_script_file =<<<EOF
#!/bin/sh

printf "Beginning Shutdown Sequence (shutdown -p)" | wall
/sbin/shutdown -p now "apcupsd initiated shutdown"

exit 99

EOF;

		file_put_contents(APCUPSD_BASE . "/etc/apcupsd/doshutdown", $doshutdown_script_file, LOCK_EX);
		@chmod(APCUPSD_BASE . "/etc/apcupsd/doshutdown", 0744);
	} else {
		unlink_if_exists(APCUPSD_BASE . "/etc/apcupsd/doshutdown");
	}

}

function check_nis_running_apcupsd() {
	global $config, $g;

	if (is_array($config['installedpackages']['apcupsd'])) {
		$apcupsd_config = $config['installedpackages']['apcupsd']['config'][0];
		if ($apcupsd_config['apcupsdenabled'] == "on" && $apcupsd_config['netserver'] == "on" ) {
			return(true);
		}
	} else {
		return(false);
	}
}

function check_nis_ip_apcupsd() {
	global $config, $g;
	if (is_array($config['installedpackages']['apcupsd'])) {
		$apcupsd_config = $config['installedpackages']['apcupsd']['config'][0];
		if ($apcupsd_config['apcupsdenabled']=="on" && $apcupsd_config['netserver']=="on" ) {
			return($apcupsd_config['nisip']);
		}
	} else {
		return(false);
	}
}

function check_nis_port_apcupsd() {
	global $config, $g;
	if (is_array($config['installedpackages']['apcupsd'])) {
		$apcupsd_config = $config['installedpackages']['apcupsd']['config'][0];
		if ($apcupsd_config['apcupsdenabled']=="on" && $apcupsd_config['netserver']=="on" ) {
			return($apcupsd_config['nisport']);
		}
	} else {
		return(false);
	}
}

?>
