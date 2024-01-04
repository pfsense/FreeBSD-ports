<?php

define("YMLFILE", "/usr/local/etc/livekit.yml");
function write_conf($livekit_conf) {
	$key = $livekit_conf['livekit_apikey'];
	$secret = $livekit_conf['livekit_secret'];
	$port = $livekit_conf['livekit_port'];
	$singleport = $livekit_conf['livekit_singleport'];
	$singleport_port = $livekit_conf['livekit_singleport_port'];
	$portrange_start = $livekit_conf['livekit_portrange_start'];
	$portrange_end = $livekit_conf['livekit_portrange_end'];

	if (!file_exists(YMLFILE) && !is_link(YMLFILE) && !touch(YMLFILE)) {
		return false;
	}

	if (!is_writable(YMLFILE)) {
		return false;
	}

	$towrite = "# LiveKit configuration generated with pfSense UI\n";
	$towrite .= "port: {$port}\n";
	$towrite .= "keys:\n";
	$towrite .= "  {$key}: {$secret}\n";

	$towrite .= "rtc:\n";

	if ($singleport) {
		$towrite .= "  udp_port: {$singleport_port}\n";
	} else {
		$towrite .= "  port_range_start: {$portrange_start}\n";
		$towrite .= "  port_range_end: {$portrange_end}\n";
	}

	@file_put_contents(YMLFILE, $towrite);
	unset($towrite);
	@chmod(YMLFILE, 0755);

	return;
}

function sync_package_livekit() {
	global $config;
	
	if (is_array($config['installedpackages']['livekit'])) {
	    $livekit_conf = &$config['installedpackages']['livekit']['config'][0];
	} else {
	    $livekit_conf = array();
	}

	if (empty($livekit_conf['livekit_port'])) {
		$livekit_conf['livekit_port'] = 7880;
	}
	if (empty($livekit_conf['livekit_apikey'])) {
		$livekit_conf['livekit_apikey'] = 'apikey';
	}
	if (empty($livekit_conf['livekit_secret'])) {
		$livekit_conf['livekit_secret'] = 'secret';
	}
	if (empty($livekit_conf['livekit_singleport_port'])) {
		$livekit_conf['livekit_singleport_port'] = 7882;
	}
	if (empty($livekit_conf['livekit_portrange_start'])) {
		$livekit_conf['livekit_portrange_start'] = 50000;
	}
	if (empty($livekit_conf['livekit_portrange_end'])) {
		$livekit_conf['livekit_portrange_end'] = 60000;
	}

	write_conf($livekit_conf);

	$options = "--config " . YMLFILE;
	$options .= " --bind 0.0.0.0";

	write_rcfile(array(
		"file" => "livekit.sh",
		"start" => "livekit-server {$options}",
		"stop" => "/usr/bin/killall -9 livekit-server",
		)
	);

	if (is_service_running('livekit')) {
		stop_service("livekit");
		sleep(3);
	}
 
	/* Only (re)start the service when it is enabled */
	if ($livekit_conf['enable'] == "on") {
		start_service("livekit");
		sleep(3);
	} else {
		if (is_service_running('livekit')) {
			stop_service("livekit");
			sleep(3);
		}
		unlink_if_exists('/usr/local/etc/rc.d/livekit.sh');
	}
}

function validate_form_livekit($post, &$input_errors) {
	$secret = $post["livekit_secret"];
	$key = $post["livekit_apikey"];
	$port = $post["livekit_port"];
	$singleport_port = $post["livekit_singleport_port"];
	$portrange_start = $post['livekit_portrange_start'];
	$portrange_end = $post['livekit_portrange_end'];

	if (!empty($key) && strlen($secret) < 32) {
	        $input_errors[] = 'API secret must be at least 32 characters';
	}

	if (!empty($key) && strlen($key) < 32) {
	        $input_errors[] = 'API key must be at least 32 characters';
	}

	if (!empty($port) && !is_numeric($port)) {
	        $input_errors[] = 'Port should be a number';
	}

	if (!empty($singleport_port) && !is_numeric($singleport_port)) {
	        $input_errors[] = 'UDP port should be a number';
	}

	if (!empty($portrange_start) && !is_numeric($portrange_start)) {
	        $input_errors[] = 'UDP port range start should be a number';
	}

	if (!empty($portrange_end) && !is_numeric($portrange_end)) {
	        $input_errors[] = 'UDP port range end should be a number';
	}
}

?>
