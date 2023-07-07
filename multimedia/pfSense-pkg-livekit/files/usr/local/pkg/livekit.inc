<?php

define("YMLFILE", "/usr/local/etc/livekit.yml");
function write_conf($params) {
	    if (!file_exists(YMLFILE) && !is_link(YMLFILE) && !touch(YMLFILE)) {
	            return false;
	    }

	    if (!is_writable(YMLFILE)) {
	            return false;
	    }

	    $towrite = "# LiveKit configuration generated with pfSense UI\n";
	    $towrite .= "port: {$params['port']}\n";
	    $towrite .= "keys:\n";
	    $towrite .= "  {$params['key']}: {$params['secret']}\n";

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

	write_conf(array(
		"key" => $livekit_conf['livekit_apikey'],
		"secret" => $livekit_conf['livekit_secret'],
		"port" => $livekit_conf['livekit_port'],
		)
	);

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
	$port = $port["livekit_port"];

	if (!empty($key) && strlen($secret) < 32) {
	        $input_errors[] = 'API secret must be at least 32 characters';
	}

	if (!empty($key) && strlen($key) < 32) {
	        $input_errors[] = 'API key must be at least 32 characters';
	}

	if (!empty($port) && !is_numeric($port)) {
	        $input_errors[] = 'Port should be a number';
	}
}

?>
