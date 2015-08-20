<?php
/*
	gwled.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2011 Jim Pingle
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

require_once("functions.inc");

function sync_package_gwled() {
	global $config;
	$gwled_config = $config['installedpackages']['gwled']['config'][0];
	led_normalize();
	/* Kill all instances of gwled */
	if (is_process_running("gwled")) {
		mwexec("/bin/pkill -9 -f gwled");
	}
	gwled_start();
}

function gwled_start() {
	global $config;
	$gwled_config = $config['installedpackages']['gwled']['config'][0];
	if (!($gwled_config['enable'])) {
		return;
	}

	// How often to run the check, in seconds.
	$interval = 15;

	if (($gwled_config['enable_led2']) && ($gwled_config['gw_led2']) ||
	    ($gwled_config['enable_led3']) && ($gwled_config['gw_led3'])) {
		mwexec("/usr/local/bin/minicron {$interval} /var/run/gwled.pid /usr/local/bin/gwled.php");
		mwexec_bg("/usr/local/bin/gwled.php");
	}
}

function validate_form_gwled($post, &$input_errors) {
	/* Make sure both aren't using the same interface */
	if (($post['gw_led2']) && ($post['gw_led3']) &&
	    (($post['enable_led2']) && ($post['enable_led3'])) &&
	    ($post['gw_led2'] == $post['gw_led3'])) {
		$input_errors[] = 'You cannot set two LEDs for a single gateway. Please choose seperate interfaces.';
	}
}

function gwled_get_gateways() {
	$gateways = return_gateways_array();

	foreach ($gateways as $gateway) {
		$tmp["name"] = "{$gateway['name']} (Monitor: {$gateway['monitor']})";
		$tmp["value"] = $gateway['name'];
		$gws[] = $tmp;
	}
	return $gws;
}

function gwled_set_status($gateway, $led) {
	$a_gateways = return_gateways_array();
	$gateways_status = array();
	$gateways_status = return_gateways_status(true);

	if ($gateways_status[$gateway]) {
		$status = $gateways_status[$gateway]['status'];
		if (stristr($status, "down")) {
			led_blink($led, "veryfast");
		} elseif (stristr($status, "loss")) {
			led_blink($led, "fast");
		} elseif (stristr($status, "delay")) {
			led_blink($led, "medium");
		} elseif ($status == "none") {
			led_on($led);
		}
	} else {
		led_blink($led, "veryfast");
	}
}

?>
