<?php
/*
	syslog-ng.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2012 Lance Leger
	Copyright (C) 2015 ESF, LLC
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
require_once('globals.inc');
require_once('config.inc');
require_once('util.inc');
require_once('pfsense-utils.inc');
require_once('pkg-utils.inc');
require_once('service-utils.inc');

if (!function_exists("filter_configure")) {
	require_once("filter.inc");
}

$pf_version = substr(trim(file_get_contents("/etc/version")), 0, 3);
if ($pf_version == "2.1" || $pf_version == "2.2") {
	define("SYSLOGNG_BASEDIR", "/usr/pbi/syslog-ng-" . php_uname("m") . "/");
} else {
	define("SYSLOGNG_BASEDIR", "/usr/local/");
}

function syslogng_get_real_interface_address($interface) {
	$interface = convert_friendly_interface_to_real_interface_name($interface);
	$ip = get_interface_ip($interface);
	return array($ip);
}

function syslogng_install_command() {
	conf_mount_rw();
	if (is_link("/usr/local/lib/syslog-ng")) {
		unlink("/usr/local/lib/syslog-ng");
	}
	if (!file_exists("/usr/local/lib/syslog-ng")) {
		@symlink(SYSLOGNG_BASEDIR . "local/lib/syslog-ng", "/usr/local/lib/syslog-ng");
	}
	syslogng_install_cron(true);
	conf_mount_ro();
	syslogng_resync();
}

function syslogng_deinstall_command() {
	conf_mount_rw();
	service_stop("syslog-ng");
	unlink_if_exists("/usr/local/etc/rc.d/syslog-ng.sh");
	if (is_link("/usr/local/lib/syslog-ng"))
		unlink("/usr/local/lib/syslog-ng");
	syslogng_install_cron(false);
	unlink_if_exists("/usr/local/etc/logrotate.conf");
	unlink_if_exists("/usr/local/etc/syslog-ng.conf");
	conf_mount_ro();
	filter_configure();
}

function syslogng_validate_general($post, &$input_errors) {
	global $config;

	$objects = $config['installedpackages']['syslogngadvanced']['config'];

	if (empty($post['interfaces'])) {
		$input_errors[] = 'You must select at least one interface in \'Interfaces\' field';
	} else {
		$post['interfaces'] = implode(",", $post['interfaces']);
	}

	if (!is_port($post['default_port'])) {
		$input_errors[] = 'You must enter a valid port number in the \'Default Port\' field';
	}

	$sockstat = trim(shell_exec("sockstat -l -P " . $post['default_protocol'] . " -p " . $post['default_port'] . " | grep -v ^USER | grep -v syslog-ng"));
	if (!empty($sockstat)) {
		$input_errors[] = 'The port specified in the \'Default Port\' field is already in use';
	}

	if (!preg_match("/^\\/[^?*:;{}\\\\]+[^\\/]$/", $post['default_logdir'])) {
		$input_errors[] = 'You must enter a valid directory in the \'Default Log Directory\' field';
	} elseif ($post['default_logdir'] == "/var/log") {
		$input_errors[] = 'You must enter a valid directory in the \'Default Log Directory\' field -- /var/log is reserved for pfSense';
	}

	if (!preg_match("/^[^\\/?*:;{}\\\\]+$/", $post['default_logfile'])) {
		$input_errors[] = 'You must enter a valid file in the \'Default Log File\' field';
	}

	$default_objects = syslogng_build_default_objects($post);

	if (empty($objects)) {
		$objects = $default_objects;
	} else {
		$objects = syslogng_merge_objects($objects, $default_objects);
	}

	if ($errors = syslogng_test_object_syntax($objects)) {
		$input_errors[] = "Syslog-ng syntax test failed:\n" . $errors;
	}
}

function syslogng_validate_advanced($post, &$input_errors) {
	global $config;

	$objects = $config['installedpackages']['syslogngadvanced']['config'];

	if ($post['objectname'] == '_DEFAULT') {
		$input_errors[] = 'Creation or modification of \'_DEFAULT\' objects not permitted. Change default settings under \'General\' tab.';
	}

	$post['objectparameters'] = base64_encode($post['objectparameters']);
	$new_object[] = array("objecttype"=>$post['objecttype'], "objectname"=>$post['objectname'], "objectparameters"=>$post['objectparameters']);

	if (empty($objects)) {
		$objects = $new_object;
	} else {
		$objects = syslogng_merge_objects($objects, $new_object);
	}

	if ($errors = syslogng_test_object_syntax($objects)) {
		$input_errors[] = "Syslog-ng syntax test failed:\n" . $errors;
	}
}

function syslogng_install_cron($should_install) {
	global $config;

	if (platform_booting()) {
		return;
	}

	if (!$config['cron']['item']) {
		return;
	}

	$x=0;
	$rotate_job_id=-1;
	$rotate_is_installed = false;

	foreach ($config['cron']['item'] as $item) {
		if (strstr($item['task_name'], "syslogng_rotate_logs")) {
			$rotate_job_id = $x;
		}
		$x++;
	}
	$need_write = false;
	switch ($should_install) {
		case true:
			if ($rotate_job_id < 0) {
				$cron_item = array();
				$cron_item['task_name'] = "syslogng_rotate_logs";
				$cron_item['minute'] = "0";
				$cron_item['hour'] = "*";
				$cron_item['mday'] = "*";
				$cron_item['month'] = "*";
				$cron_item['wday'] = "*";
				$cron_item['who'] = "root";
				$cron_item['command'] = "/usr/bin/nice -n20 " . SYSLOGNG_BASEDIR . "local/sbin/logrotate /usr/local/etc/logrotate.conf";
				$config['cron']['item'][] = $cron_item;
				$need_write = true;
			}
			if ($need_write) {
				parse_config(true);
				write_config("Adding syslog-ng Cron Jobs");
			}
		break;
		case false:
			if ($rotate_job_id >= 0) {
				unset($config['cron']['item'][$rotate_job_id]);
				$need_write = true;
			}
			if ($need_write) {
				parse_config(true);
				write_config("Removing syslog-ng Cron Jobs");
			}
		break;
	}
	configure_cron();
}

function syslogng_build_default_objects($settings) {
	$default_objects = array();

	$interfaces = $settings['interfaces'];
	$default_protocol = $settings['default_protocol'];
	$default_port = $settings['default_port'];
	$default_logdir = $settings['default_logdir'];
	$default_logfile = $settings['default_logfile'];

	$default_objects[0] = array("objecttype"=>"source", "objectname"=>"_DEFAULT", "objectparameters"=>"{ internal(); syslog(transport($default_protocol) port($default_port)");
	foreach (explode(",", $interfaces) as $interface) {
		$interface_address = syslogng_get_real_interface_address($interface);
		if ($interface_address[0]) {
			$default_objects[0]['objectparameters'] .= " ip({$interface_address[0]})";
		}
	}
	$default_objects[0]['objectparameters'] .= "); };";
	$default_objects[0]['objectparameters'] = base64_encode($default_objects[0]['objectparameters']);
	$default_objects[1] = array("objecttype"=>"destination", "objectname"=>"_DEFAULT", "objectparameters"=>"{ file(\"$default_logdir/$default_logfile\"); };");
	$default_objects[1]['objectparameters'] = base64_encode($default_objects[1]['objectparameters']);
	$default_objects[2] = array("objecttype"=>"log", "objectname"=>"_DEFAULT", "objectparameters"=>"{ source(_DEFAULT); destination(_DEFAULT); };");
	$default_objects[2]['objectparameters'] = base64_encode($default_objects[2]['objectparameters']);

	return $default_objects;
}


function syslogng_merge_objects($objects1, $objects2) {

	foreach ($objects2 as $object2) {
		$match = 0;
		foreach ($objects1 as &$object1) {
			if (($object2['objecttype'] == $object1['objecttype']) && ($object2['objectname'] == $object1['objectname'])) {
				$object1 = $object2;
				$match = 1;
			}
		}
		if ($match == 0) {
			array_push($objects1, $object2);
		}
	}

	return $objects1;
}

function syslogng_test_object_syntax($objects) {
	rename("/usr/local/etc/syslog-ng.conf", "/usr/local/etc/syslog-ng.conf.backup");
	syslogng_build_conf($objects);
	$errors = trim(shell_exec('/usr/local/sbin/syslog-ng --syntax-only 2>&1'));
	rename("/usr/local/etc/syslog-ng.conf", "/usr/local/etc/syslog-ng.conf.tested");
	rename("/usr/local/etc/syslog-ng.conf.backup", "/usr/local/etc/syslog-ng.conf");

	return $errors;
}

function syslogng_get_log_files($objects) {
	$log_files = array();

	foreach ($objects as $object) {
		if ($object['objecttype'] == 'destination') {
			preg_match("/\bfile\b\(['\"]([^'\"]*)['\"]/", base64_decode($object['objectparameters']), $match);
			if ($match) {
				$log_file = $match[1];
				array_push($log_files, $log_file);
			}
		}
	}

	return $log_files;
}

function syslogng_build_conf($objects) {
	$conf = "# This file is automatically generated by pfSense\n";
	$conf .= "# Do not edit manually !\n";
	$conf .= "@version:3.6\n";

	foreach ($objects as $object) {
		if ($object['objecttype'] == 'log' || $object['objecttype'] == 'options') {
			$conf .= $object['objecttype'] . " " . base64_decode($object['objectparameters']) . "\n";
		} else {
			$conf .= $object['objecttype'] . " " . $object['objectname'] . " " . base64_decode($object['objectparameters']) . "\n";
		}
	}

	file_put_contents('/usr/local/etc/syslog-ng.conf', $conf);
}

function syslogng_build_logrotate_conf($settings, $objects) {
	$conf = "# This file is automatically generated by pfSense\n";
	$conf .= "# Do not edit manually !\n";

	$compress_archives = $settings['compress_archives'];
	$compress_type = $settings['compress_type'];
	$archive_frequency = $settings['archive_frequency'];
	$max_archives = $settings['max_archives'];

	$log_files = syslogng_get_log_files($objects);

	foreach ($log_files as $log_file) {
		$conf .= "$log_file ";
	}

	$conf .= "{\n";
	$conf .= "\trotate $max_archives\n";
	$conf .= "\t$archive_frequency\n";

	if ($compress_archives == 'on') {
		$conf .= "\tcompress\n";
		if ($compress_type == 'bz2') {
			$conf .= "\tcompresscmd bzip2\n";
		}
	}

	$conf .= "\tpostrotate\n";
	$conf .= "\t\tkill -s HUP `cat /var/run/syslog-ng.pid`\n";
	$conf .= "\tendscript\n";
	$conf .= "}\n";

	file_put_contents('/usr/local/etc/logrotate.conf', $conf);
}

function syslogng_generate_rules($type) {
	global $config;

	$settings = $config['installedpackages']['syslogng']['config'][0];

	$interfaces = ($settings['interfaces'] ? $settings['interfaces'] : 'lan');
	$default_protocol = ($settings['default_protocol'] ? $settings['default_protocol'] : 'udp');
	$default_port = ($settings['default_port'] ? $settings['default_port'] : 5140);

	$rules = "";
	switch ($type) {
		case 'rule':
			foreach ($interfaces as $interface) {
				$rules .= "pass in quick on $interface proto $default_protocol from any to !($interface) port $default_port no state label\n";
			}
			break;
	}

	return $rules;
}

function syslogng_resync() {
	global $config;
	conf_mount_rw();

	$settings = $config['installedpackages']['syslogng']['config'][0];
	$objects = $config['installedpackages']['syslogngadvanced']['config'];

	if (!isset($settings['enable'])) {
		$settings['enable'] = 'off';
	}
	if (!isset($settings['interfaces'])) {
		$settings['interfaces'] = 'lan';
	}
	if (!isset($settings['default_protocol'])) {
		$settings['default_protocol'] = 'udp';
	}
	if (!isset($settings['default_port'])) {
		$settings['default_port'] = 5140;
	}
	if (!isset($settings['default_logdir'])) {
		$settings['default_logdir'] = '/var/syslog-ng';
	}
	if (!isset($settings['default_logfile'])) {
		$settings['default_logfile'] = 'default.log';
	}
	if (!isset($settings['archive_frequency'])) {
		$settings['archive_frequency'] = 'daily';
	}
	if (!isset($settings['compress_archives'])) {
		$settings['compress_archives'] = 'on';
	}
	if (!isset($settings['compress_type'])) {
		$settings['compress_type'] = 'gz';
	}
	if (!isset($settings['max_archives'])) {
		$settings['max_archives'] = 30;
	}

	$default_objects = syslogng_build_default_objects($settings);

	if (empty($objects)) {
		$objects = $default_objects;
	} else {
		$objects = syslogng_merge_objects($objects, $default_objects);
	}

	$sort = array();
	foreach ($objects as $k=>$v) {
		$sort['objecttype'][$k] = $v['objecttype'];
		$sort['objectname'][$k] = $v['objectname'];
	}
	array_multisort($sort['objecttype'], SORT_ASC, $sort['objectname'], SORT_ASC, $objects);

	syslogng_build_conf($objects);
	syslogng_build_logrotate_conf($settings, $objects);

	$config['installedpackages']['syslogng']['config'][0] = $settings;
	$config['installedpackages']['syslogngadvanced']['config'] = $objects;

	if ($settings['enable'] == 'on') {
		if (!file_exists($settings['default_logdir'])) {
			exec("mkdir -p " . $settings['default_logdir']);
		}

		syslogng_write_rcfile();

		if (!is_service_running('syslog-ng')) {
			log_error("Starting syslog-ng");
			start_service("syslog-ng");
		} else {
			log_error("Reloading syslog-ng for configuration sync");
			restart_service("syslog-ng");
		}

		// Sleep for a couple seconds to give syslog-ng a chance to fire up fully.
		for ($i=0; $i < 10; $i++) {
			if (!is_service_running('syslog-ng')) {
				sleep(1);
			}
		}
	} else {
		if (is_service_running('syslog-ng')) {
			log_error("Stopping syslog-ng");
			stop_service("syslog-ng");
			unlink_if_exists("/usr/local/etc/rc.d/syslog-ng.sh");
		}
	}

	write_config();
	conf_mount_ro();
	filter_configure();
}

function syslogng_write_rcfile() {
	$rc = array();
	$pid_file = "/var/run/syslog-ng.pid";
	$rc['file'] = 'syslog-ng.sh';
	$rc['start'] = <<<EOD
if [ -z "`ps auxw | grep "syslog-ng" | grep -v "syslog-ng.sh" | grep -v "grep"`" ]; then
	/usr/local/sbin/syslog-ng -p {$pid_file}
fi

EOD;
	$rc['stop'] = <<<EOD
if [ -s "{$pid_file}" ]; then
	kill `cat {$pid_file}` 2>/dev/null
fi
# Just in case pid file didn't exist or process is still running...
sleep 5
killall -9 syslog-ng 2>/dev/null

EOD;
	$rc['restart'] = <<<EOD
if [ -z "`ps auxw | grep "syslog-ng" | grep -v "syslog-ng.sh"`" ]; then
	/usr/local/sbin/syslog-ng -p {$pid_file}
elif [ -s "{$pid_file}" ]; then
	kill -s HUP `cat {$pid_file}` 2>/dev/null
else
	killall -9 syslog-ng 2>/dev/null
	/usr/local/sbin/syslog-ng -p {$pid_file}
fi

EOD;
	conf_mount_rw();
	write_rcfile($rc);
}
?>
