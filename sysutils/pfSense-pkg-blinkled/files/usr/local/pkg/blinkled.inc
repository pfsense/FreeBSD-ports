<?php
/*
 * blinkled.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2015 Rubicon Communications, LLC (Netgate)
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

function blinkled_rcfile() {
	global $config;
	$blinkled_config = $config['installedpackages']['blinkled']['config'][0];
	$stop = <<<EOD
if [ `/bin/pgrep blinkled | /usr/bin/wc -l` != 0 ]; then
		/usr/bin/killall -9 blinkled
		while [ `/bin/pgrep blinkled | /usr/bin/wc -l` != 0 ]; do
			sleep 1
		done
	fi
EOD;

	$start = "{$stop}\n";
	if (($blinkled_config['enable_led2']) && ($blinkled_config['iface_led2']))
		$start .= "\t" . blinkled_launch(convert_friendly_interface_to_real_interface_name($blinkled_config['iface_led2']), 2, true) . "\n";
	if (($blinkled_config['enable_led3']) && ($blinkled_config['iface_led3']))
		$start .= "\t" . blinkled_launch(convert_friendly_interface_to_real_interface_name($blinkled_config['iface_led3']), 3, true) . "\n";

	conf_mount_rw();
	write_rcfile(array(
		'file' => 'blinkled.sh',
		'start' => $start,
		'stop' => $stop)
	);
	conf_mount_ro();
}

function sync_package_blinkled() {
	global $config;

	blinkled_rcfile();

	$blinkled_config = $config['installedpackages']['blinkled']['config'][0];
	/* If the service (being) disabled, stop it (if running) and do nothing else */
	if (!($blinkled_config['enable'])) {
		if (is_process_running("blinkled")) {
			stop_service("blinkled");
		}
		return;
	}
	/* Kill all instances of blinkled */
	if (is_process_running("blinkled")) {
		stop_service("blinkled");
	}
	start_service("blinkled");
}

function blinkled_launch($int, $led, $return = false) {
	$cmd = "/usr/local/bin/blinkled -i " . escapeshellarg($int) . " -l " . escapeshellarg("/dev/led/led{$led}");
	if ($return) {
		return $cmd;
	} else {
		mwexec($cmd);
	}
}

function validate_form_blinkled($post, &$input_errors) {
	/* Make sure both aren't using the same interface */
	if (($post['iface_led2']) && ($post['iface_led3']) &&
	    (($post['enable_led2']) && ($post['enable_led3'])) &&
	    ($post['iface_led2'] == $post['iface_led3'])) {
		$input_errors[] = 'You cannot set two LEDs for a single interface. Please choose seperate interfaces.';
	}
}

?>
