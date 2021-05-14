<?php
/*
 * wg_service.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/theonemcdonald)
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


// pfSense includes
require_once('config.inc');
require_once('globals.inc');
require_once('gwlb.inc');
require_once('util.inc');
require_once('services.inc');
require_once('service-utils.inc');

// WireGuard includes
require_once('wireguard/wg.inc');

if (isset($argv[1])) {

	if (!wg_is_cli()) {

		// Bail out if we aren't in the CLI...
		die("FATAL: This script can only be started through the CLI.\n");

	}

	if (PHP_BINARY != $wgg['wg_daemon']) {

		// Bail out if we aren't running under under the correct executable...
		die("FATAL: This script can only be executed by {$wgg['wg_daemon']}.\n");

	}

	switch (strtolower($argv[1])) {

		case 'start':

			ignore_user_abort(true);

			set_time_limit(0);
	
			wg_service_start();
	
			wg_service_daemon();
	
			exit(0);

		case 'stop':

			ignore_user_abort(true);

			set_time_limit(0);
	
			wg_service_stop();
	
			exit(0);

		default:

			exit(0);

	}

}

function wg_is_cli() {

	return PHP_SAPI == 'cli';

}

function wg_service_stop() {
	global $wgg;

	killbypid($wgg['pid_path']);
	
}

function wg_is_service_running() {
	global $wgg;

	if (!($h_lock = @fopen($wgg['pid_path'],'r')) || !file_exists($wgg['pid_path'])) {

		return 0;

	}

	$not_running = flock($h_lock, LOCK_EX | LOCK_NB, $wouldblock);

	if ($not_running) {
	
		flock($h_lock, LOCK_UN);

	}

	@fclose($h_lock);

	return !$not_running || $wouldblock;

}

function wg_service_start() {
	global $g, $wgg;

	if (wg_is_service_running()) {

		exit(1);

	}

	umask(0);

	// The initial fork...
	$newpid = pcntl_fork();

	if ($newpid === - 1) {

		// We could not fork, so bail out...
		exit(1);

	} elseif ($newpid) {

		// I'm the parent, and I'm going to self-destruct...
		exit(0);

	}

	// Now become the session leader
	if (posix_setsid() < 0) {

		exit(1);

	}

	// The second fork...
	$newpid = pcntl_fork();

	if ($newpid === - 1) {

		// We could not fork, so bail out...
		exit(1);

	} elseif ($newpid) {

		// I'm the parent, and I'm going to self-destruct...
		exit(0);

	}

	if (platform_booting()) {

		$g['wireguard_firststart'] = true;

		print_message("Configuring WireGuard tunnels...");

		mute_kernel_msgs();

	}

	// Create WireGuard configuration files
	wg_create_config_files();

	// Update Unbound ACL without restarting Unbound, we will do this once on service start
	wg_unbound_acl_update(false);

	// Build each tunnel
	foreach ($wgg['tunnels'] as $tunnel) {
	
		wg_resync_tunnel($tunnel['name'], true);
	
	}
 
	if (platform_booting()) {

		unmute_kernel_msgs();

		print_message("done. \n");

		// Let the boot process continue and handle additional service and package invocation
		exit(0);

	}

	// We don't need to restart any additional services because we just finished booting...
	if ($g['wireguard_firststart']) {

		unset($g['wireguard_firststart']);

		return;

	}

	setup_gateways_monitor();

	services_unbound_configure();
	
	filter_configure();

	return;

}

function wg_service_daemon() {
	global $wgg;

	// Register the service environment
	wg_register_service_env();

	// Register the service signal handlers
	wg_register_sig_handler();

	while (true) {

		// Check if any new signals are waiting for dispatch
		pcntl_signal_dispatch();

		// The whole point of this daemon...
		if (!is_module_loaded($wgg['kmod'])) {

			break;

		}

		// Wait a bit before trying again
		sleep(1);

	}

	// Send a signal to self to terminate gracefully
	posix_kill(getmypid(), SIGTERM);

	return;

}

function wg_service_install() {
	global $wgg;

	stop_service('wireguard');

	unlink_if_exists($wgg['wg_daemon']);

	link($wgg['php'], $wgg['wg_daemon']);

}

function wg_service_deinstall() {
	global $wgg;

	stop_service('wireguard');

	unlink_if_exists($wgg['wg_daemon']);

}

function wg_service_rcfile() {
	global $wgg;

	$start = $stop = array();

	$start[] = "{$wgg['wg_daemon']} -f {$wgg['wg_includes']}/wg_service.inc start";

	$stop[] = "{$wgg['wg_daemon']} -f {$wgg['wg_includes']}/wg_service.inc stop";

	$rcfile['file'] 	= 'wireguardd';

	$rcfile['start'] 	= implode("\n\t", $start);

	$rcfile['stop'] 	= implode("\n\t", $stop);

	write_rcfile($rcfile);

}

function wg_register_service_env() {
	global $h_lock, $wgg;

	$h_lock = @fopen($wgg['pid_path'], 'a+');

	flock($h_lock, LOCK_EX);

	ftruncate($h_lock, 0);

	fseek($h_lock, 0, 0);

	fwrite($h_lock, getmypid());

	fflush($h_lock);

	fclose(STDIN);

	fclose(STDOUT);

	fclose(STDERR);

}

function wg_register_sig_handler() {

	pcntl_signal(SIGTERM, 'sig_handler');

	pcntl_signal(SIGHUP, 'sig_handler');

	pcntl_signal(SIGINT, 'sig_handler');

}

function sig_handler($signo) {
	global $h_lock, $wgg;

	switch ($signo) {

		case SIGTERM:

			// Release exclusive lock
			@flock($h_lock, LOCK_UN);

			// Close file handler
			@fclose($h_lock);

			// Delete PID
			unlink($wgg['pid_path']);

			// Teardown tunnels
			wg_destroy_tunnels();

			// We are done...
			exit;

			break;

		case SIGHUP:

			break;

		default:

			break;

	}

}

// This is a hack to get text onto the console during booting
function print_message($message) {

	$message = gettext($message);

    	$message = escapeshellarg($message);

	mwexec("printf {$message} >&2");

}

?>
