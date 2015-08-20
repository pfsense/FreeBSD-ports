<?php
/*
	blinkled.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2009 Jim Pingle
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
