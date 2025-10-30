<?php
/*
 * ntopng.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2025 Rubicon Communications, LLC (Netgate)
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
require_once("globals.inc");
require_once("interfaces.inc");
require_once("pkg-utils.inc");
require_once("service-utils.inc");
require_once("util.inc");
require_once("certs.inc");

define('LOG_PREFIX_PKG_NTOPNG', 'ntopng');

const DATA_DIR = "/var/db/ntopng/";
const RC_FILE = "/usr/local/etc/rc.d/ntopng.sh";
const CONF_FILE = "/usr/local/etc/ntopng.conf";
const DB_FILE = DATA_DIR . "ntopng.rdb";
const HTTP_PORT = "3000";

const SHARE_DIR = "/usr/local/share/ntopng/";
const CERT_FILE = SHARE_DIR . "httpdocs/ssl/ntopng-cert.pem";
const GEOIP_DIR = SHARE_DIR . "httpdocs/geoip/";

const NTOPNG_CMD = "/usr/local/bin/ntopng " . CONF_FILE;
const REDIS_CMD = "/usr/local/bin/redis-server --daemonize yes --bind 127.0.0.1 ::1 --dbfilename ntopng.rdb --dir " . DATA_DIR;
const REDIS_CLI = "/usr/local/bin/redis-cli";


function ntopng_php_install_command() {
	ntopng_create_datadir();

	/* migrate ntopng settings */
	ntopng_migrate_settings();
}

function ntopng_php_deinstall_command() {
	/* Wipe data and settings if the user does not wish to keep them */
	if (config_get_path('installedpackages/ntopng/config/0/keepdata') != "on") {
		if (is_dir(DATA_DIR)) {
			mwexec("/bin/rm -rf " . DATA_DIR);
		}

		config_del_path('installedpackages/ntopng');
		write_config("[ntopng] Removed package settings on uninstall.");

		logger(LOG_INFO, localize_text("Removed package data and settings since 'Keep Data/Settings' is disabled."), LOG_PREFIX_PKG_NTOPNG);
	}
}

function ntopng_migrate_settings() {
	$local_networks = config_get_path('installedpackages/ntopng/config/0/local_networks');
	if ($local_networks) {
		/*
		  ntopng determines the ip addresses and netmasks for each active interface,
		  and any traffic on those networks is already considered local. The -m parameter
		  is only for adding networks that are not associated with the interfaces being
		  monitored. Older versions of the package treated the -m parameter as if it were
		  an absolute list of local networks rather than a list of additional local networks.

		  Previously, the following options were available:

		    selected  Consider selected interface networks local
		    lanonly   Consider only LAN interface local
		    rfc1918   Consider all RFC1918 networks local
		    custom    Use custom list

		  Going forward, additional local networks are handled as a list (same as the
		  prior "custom" list). Selected interfaces and LAN do not require migration as
		  these are handled natively in ntopng. This leaves us with rfc1918, which we
		  migrate to the list in order to maintain the prior behavior.
		*/

		if ($local_networks == "rfc1918") {
			$rows = array(array('cidr'=>'192.168.0.0/16'), array('cidr'=>'172.16.0.0/12'), array('cidr'=>'10.0.0.0/8'));
			config_set_path('installedpackages/ntopng/config/0/row', $rows);
			logger(LOG_INFO, localize_text("Migrated rfc1918 local_networks setting."), LOG_PREFIX_PKG_NTOPNG);
		}
		config_del_path('installedpackages/ntopng/config/0/local_networks');

		/* Default Server Interface to match prior behavior */
		config_set_path('installedpackages/ntopng/config/0/server_interface', "All");
		config_set_path('installedpackages/ntopng/config/0/server_ipv4', "on");
		write_config("Migrated ntopng settings.");
	}
}

function ntopng_create_datadir() {
	mwexec("/bin/mkdir -p " . DATA_DIR);
	mwexec("/bin/chmod -R 755 " . DATA_DIR);
	mwexec("/usr/sbin/chown -R ntopng:ntopng " . DATA_DIR);
}

function ntopng_write_cert_file($file, $cert) {
	$fd = fopen($file, "w");
	if (!$fd) {
		logger(LOG_ERR, localize_text("Could not open %s for writing", $file), LOG_PREFIX_PKG_NTOPNG);
		return;
	}
	chmod($file, 0600);
	/* newline to deal with certs/keys lacking newline at end */
	fwrite($fd, base64_decode($cert['crt']) . "\n" . ca_chain($cert));
	fwrite($fd, base64_decode($cert['prv']));
	fclose($fd);
}

function ntopng_write_conf_file($file, $array) {
	$fd = fopen($file, "w");
	if (!$fd) {
		logger(LOG_ERR, localize_text("Could not open %s for writing", $file), LOG_PREFIX_PKG_NTOPNG);
		return;
	}
	chmod($file, 0600);
	fwrite($fd, $array);
	fclose($fd);
}

function ntopng_sync_package() {
	/* These are done via ntopng_validate_input(), just return */
	if ($_POST['Submit'] == "Update GeoIP Data") {
		return;
	}
	if ($_POST['Delete'] == "Delete Data") {
		return;
	}

	/* Just stop service and unlink rc script if disabled */
	if (config_get_path('installedpackages/ntopng/config/0/enable') != "on") {
		ntopng_stop_service();
		unlink_if_exists(RC_FILE);
		unlink_if_exists(CERT_FILE);
		unlink_if_exists(CONF_FILE);
		return;
	}

	/* Ensure data directory exists */
	ntopng_create_datadir();

	/* Create config file */
	$config_lines = "# This file is automatically generated -- do not edit\n";
	$config_lines .= "--daemon=\n";
	$config_lines .= "--pid=/var/run/ntopng.pid\n";
	$config_lines .= "--data-dir=" . DATA_DIR . "\n";

	/* Server listen addresses */
	$iface = config_get_path('installedpackages/ntopng/config/0/server_interface');
	$listen_array = array();
	if (config_get_path('installedpackages/ntopng/config/0/server_ipv4') == "on") {
		if ($iface == "All") {
			$listen_array[] = "0.0.0.0:" . HTTP_PORT;
		} else {
			$addr = get_interface_ip($iface);
			if (is_ipaddrv4($addr)) {
				$listen_array[] = "{$addr}:" . HTTP_PORT;
			}
		}
	}
	elseif (config_get_path('installedpackages/ntopng/config/0/server_ipv6') == "on") {
		if ($iface == "All") {
			$listen_array[] = "[::]:" . HTTP_PORT;
		} else {
			$addr = get_interface_ipv6($iface);
			if (is_ipaddrv6($addr)) {
				$listen_array[] = "[{$addr}]:" . HTTP_PORT;
			}
		}
	}
	$listen_string = implode(',', $listen_array);

	/* HTTP/HTTPS */
	if (config_get_path('system/webgui/protocol') == "https") {
		$cert = lookup_cert(config_get_path('system/webgui/ssl-certref'));
		$cert = $cert['item'];
		ntopng_write_cert_file(CERT_FILE, $cert);
		$config_lines .= "--http-port=0\n--https-port=$listen_string\n";
	} else {
		unlink_if_exists(CERT_FILE);
		$config_lines = "--http-port=$listen_string\n";
	}

	/* Promiscuous mode? */
	if (config_get_path('installedpackages/ntopng/config/0/promiscuous') != "on") {
		$config_lines .= "--no-promisc=\n";
	}

	/* Monitor interfaces */
	foreach (config_get_path('installedpackages/ntopng/config/0/interface_array', []) as $iface) {
		$if = convert_friendly_interface_to_real_interface_name($iface);
		if ($if) {
			$config_lines .= "--interface={$if}\n";
		}
	}

	/* DNS Mode */
	$dns_mode = config_get_path('installedpackages/ntopng/config/0/dns_mode');
	if (is_numeric($dns_mode) && ($dns_mode >= 0) && ($dns_mode <= 3)) {
		$config_lines .= "--dns-mode=" . $dns_mode . "\n";
	}

	/* Additional local networks */
	$nets = array();
	foreach (config_get_path('installedpackages/ntopng/config/0/row', []) as $net) {
		$cidr = trim($net['cidr']);
		if (is_subnet($cidr)) {
			$cidr_name = trim($net['cidr_name']);
			if (!empty($cidr_name)) {
				$cidr .= '=' . $cidr_name;
			}
			$nets[] = $cidr;
		}
	}
	if (!empty($nets)) {
		$config_lines .= "--local-networks=" . implode(",", $nets) . "\n";
	}

	/* Add custom configuration options */
	$custom_config = config_get_path('installedpackages/ntopng/config/0/custom_config');
	if (!empty($custom_config)) {
		$config_lines .= "# Custom configuration options:\n";
		$config_lines .= trim(str_replace("\r\n", "\n", base64_decode($custom_config))) . "\n";
	}

	/* Write the configuration file */
	ntopng_write_conf_file(CONF_FILE, $config_lines);

	/* Create rc script */
	$conf_file = CONF_FILE;
	$db_file = DB_FILE;
	$redis_cmd = REDIS_CMD;
	$redis_cli = REDIS_CLI;
	$ntopng_cmd = NTOPNG_CMD;

	$start = <<<EOD

	# Make sure library path cache is updated
	/etc/rc.d/ldconfig start

	if [ ! -f {$conf_file} ]
	then
		/usr/bin/logger -t ntopng "Configuration file missing. Re-save ntopng settings."
		exit 1
	fi
	if [ ! -f {$db_file} ]
	then
		/usr/bin/logger -t ntopng "Redis database file missing. Re-save ntopng settings."
		exit 1
	fi

	# Start redis
	{$redis_cmd}

	# Start ntopng
	{$ntopng_cmd}

EOD;

	$stop = <<<EOD

	# Kill ntopng
	while /bin/pgrep -q ntopng; do
		/usr/bin/killall -q ntopng
		sleep 1
	done

	# Stop redis
	/usr/bin/killall -q redis-cli
	{$redis_cli} shutdown save
	sleep 1
	while /bin/pgrep -q redis-server; do
		{$redis_cli} shutdown save
		sleep 1
		/usr/bin/killall -q redis-server
	done

EOD;

	write_rcfile(array("file" => "ntopng.sh", "start" => $start, "stop" => $stop));

	/* Set ntopng admin password */
	$password = config_get_path('installedpackages/ntopng/config/0/redis_password');
	if (!empty($password)) {
		if (ntopng_start_redis()) {
			mwexec(REDIS_CLI . " SET ntopng.user.admin.password " . escapeshellarg(md5($password)));
			mwexec(REDIS_CLI . " SET ntopng.user.admin.group administrator");
			mwexec(REDIS_CLI . " SET ntopng.prefs.admin_password_changed 1");
			mwexec(REDIS_CLI . " save");
		} else {
			logger(LOG_ERR, localize_text("Cannot set admin password - redis-server is not running."), LOG_PREFIX_PKG_NTOPNG);
		}
	}

	/* (Re)start service if not booting */
	if (!platform_booting()) {
		ntopng_stop_service();
		start_service("ntopng");
	}
}

function ntopng_start_redis() {
	$redis_started = false;

	if (!is_process_running("redis-server")) {
		mwexec(REDIS_CMD);
		for ($i = 0; $i <= 10; $i++) {
			if (is_process_running("redis-server")) {
				$redis_started = true;
				break;
			}
			sleep(1);
		}
	} else {
		$redis_started = true;
	}

	if ($redis_started == false) {
		logger(LOG_ERR, localize_text("Failed to start redis-server."), LOG_PREFIX_PKG_NTOPNG);
	}

	return $redis_started;
}

function ntopng_stop_redis() {
	if (is_process_running("redis-server")) {
		mwexec(REDIS_CLI . " shutdown save");
		sleep(2);

		for ($i = 0; $i <= 10; $i++) {
			if (!is_process_running("redis-server")) {
				return;
			}
			mwexec(REDIS_CLI . " shutdown save");
			sleep(2);
		}

		logger(LOG_ERR, localize_text("redis-server failed to stop."), LOG_PREFIX_PKG_NTOPNG);
		mwexec("/usr/bin/killall -q -9 redis-server", true);
	}
}

function ntopng_stop_ntopng() {
	if (is_process_running("ntopng")) {
		mwexec("/usr/bin/killall -q ntopng", true);
		sleep(2);

		for ($i = 0; $i <= 10; $i++) {
			if (!is_process_running("ntopng")) {
				return;
			}
			mwexec("/usr/bin/killall -q ntopng", true);
			sleep(2);
		}

		logger(LOG_ERR, localize_text("ntopng failed to stop."), LOG_PREFIX_PKG_NTOPNG);
		mwexec("/usr/bin/killall -q -9 ntopng", true);
	}
}

function ntopng_stop_service() {
	if ((is_process_running("redis-server")) || (is_process_running("ntopng"))) {
		stop_service("ntopng");
		sleep(2);

		/* Make sure everything stopped */
		ntopng_stop_ntopng();
		ntopng_stop_redis();
	}
}

function ntopng_start_service() {
	if (config_get_path('installedpackages/ntopng/config/0/enable') == "on") {
		start_service("ntopng");
	}
}

function ntopng_update_geolite2_db_file(&$ch, $db_name) {
	$url = "https://download.maxmind.com/geoip/databases/{$db_name}/download?suffix=tar.gz";
	$tarfile = SHARE_DIR . "{$db_name}.tar.gz";
	$mmdb_name = "{$db_name}.mmdb";

	/* Set up the tar file */
	$fp = fopen($tarfile, "wb");
	if (!$fp) {
		logger(LOG_ERR, localize_text("Could not open %s for writing.", $tarfile), LOG_PREFIX_PKG_NTOPNG);
		return (FALSE);
	}
	curl_setopt($ch, CURLOPT_FILE, $fp);

	/* Download the tar file */
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_exec($ch);
	fclose($fp);

	/* Error? */
	$rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($rc != 200) {
		logger(LOG_ERR, localize_text("Download of %s (%s) failed: response code %s.", $db_name, $url, $rc), LOG_PREFIX_PKG_NTOPNG);
		return (FALSE);
	}

	/* Extract the mmdb file */
	mwexec("/usr/bin/tar -C " . SHARE_DIR . " -f {$tarfile} --strip 1 -xz '*.mmdb'");

	/* Create the symlink */
	safe_mkdir(GEOIP_DIR, 0755);
	symlink(SHARE_DIR . "{$mmdb_name}", GEOIP_DIR . "{$mmdb_name}");

	return (TRUE);
}

function ntopng_update_geolite2_db($account, $key) {
	/* MaxMind databases we want to install. NB: While the ntopng doc references
	   GeoLite2-Country, it is not actually used. City is a superset of Country. */
	$geolite_mmdb_names = array("GeoLite2-City", "GeoLite2-ASN");

	/* Basic curl parameters */
	$ch = curl_init();
	set_curlproxy($ch);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);

	/* User agent */
	$user_agent = g_get('product_label') . '/' . g_get('product_version');
	if (!config_path_enabled('system', 'do_not_send_uniqueid')) {
		$user_agent .= ':' . system_get_uniqueid();
	}
	curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);

	/* Maxmind account and key */
	curl_setopt($ch, CURLOPT_USERPWD, "{$account}:{$key}");

	/* Download the databases */
	$status = (TRUE);
	foreach ($geolite_mmdb_names as $name) {
		if ($status) {
			$status = ntopng_update_geolite2_db_file($ch, $name);
		}
	}
	curl_close($ch);
	if (!$status) {
		logger(LOG_ERR, localize_text("Failed to update geolocation databases."), LOG_PREFIX_PKG_NTOPNG);
		return (FALSE);
	}
	logger(LOG_INFO, localize_text("Updated geolocation databases."), LOG_PREFIX_PKG_NTOPNG);

	/* Do not (re)start service on package (re)install, only on manual GeoIP updates via the GUI */
	if ($_POST['Submit'] == "Update GeoIP Data") {
		ntopng_stop_service();
		ntopng_start_service();
	}
	return (TRUE);
}

function ntopng_delete_data() {
	logger(LOG_INFO, localize_text("Request to delete data."), LOG_PREFIX_PKG_NTOPNG);

	/* Shut down ntopng */
	ntopng_stop_ntopng();

	/* Ensure redis is running */
	if (ntopng_start_redis()) {
		/* Delete redis cache entries */
		mwexec(REDIS_CLI . " --scan --pattern '*.cache.*' | xargs -L 100 " . REDIS_CLI . " DEL");
		logger(LOG_INFO, localize_text("Deleted cache entries in Redis DB."), LOG_PREFIX_PKG_NTOPNG);

		/* Stop redis */
		ntopng_stop_redis();

		/* delete all rrd and db files and remove empty directories */
		if (is_dir(DATA_DIR)) {
			mwexec("/usr/bin/find " . DATA_DIR . " -name '*.db' -o -name '*.rrd' | /usr/bin/xargs /bin/rm");
			mwexec("/usr/bin/find " . DATA_DIR . " -depth -type d | /usr/bin/xargs /bin/rmdir", true);
			logger(LOG_INFO, localize_text("Deleted ntopng traffic data and alerts."), LOG_PREFIX_PKG_NTOPNG);
		} else {
			logger(LOG_INFO, localize_text("Nothing to delete; directory %s not found.", DATA_DIR), LOG_PREFIX_PKG_NTOPNG);
		}

		/* Resync settings and restart service if enabled */
		unset($_POST['Delete']);
		ntopng_sync_package();
		logger(LOG_INFO, localize_text("Resynced ntopng settings."), LOG_PREFIX_PKG_NTOPNG);
	} else {
		logger(LOG_ERR, localize_text("Cannot delete data - redis-server is not running."), LOG_PREFIX_PKG_NTOPNG);
	}
}

function ntopng_reset() {
	logger(LOG_INFO, localize_text("Request to perform a full reset."), LOG_PREFIX_PKG_NTOPNG);

	/* Shut everything down */
	ntopng_stop_service();

	/* Remove the entire data directory */
	mwexec("/bin/rm -rf " . DATA_DIR);
	logger(LOG_INFO, localize_text("All traffic data, alerts, preferences and customization deleted."), LOG_PREFIX_PKG_NTOPNG);

	/* Resync settings and restart service if enabled */
	unset($_POST['Delete']);
	ntopng_sync_package();
	logger(LOG_INFO, localize_text("Resynced ntopng settings."), LOG_PREFIX_PKG_NTOPNG);
}

function ntopng_validate_input($post, &$input_errors) {
	/* Validate password */
	if (empty($post['redis_password']) || empty($post['redis_passwordagain'])) {
		$input_errors[] = "You must provide (and confirm) ntopng's password.";
	}
	if ((strlen($post['redis_password']) < 5) || (strlen($post['redis_passwordagain']) < 5)) {
		$input_errors[] = "Password must have at least 5 characters.";
	}
	if ($post['redis_password'] != $post['redis_passwordagain']) {
		$input_errors[] = "The provided passwords did not match.";
	}
	if (function_exists('validate_password')) {
		$input_errors = array_merge($input_errors, validate_password('admin', $post['redis_password']));
	}

	/* Validate server parameters */
	if ($post['server_ipv4'] != "on" && $post['server_ipv6'] != "on") {
		$input_errors[] = "At least one of Server IPv4 or Server IPv6 must be enabled.";
	}

	/* Validate additional local networks (cidr and cidr_name). */
	$idx = 0;
	while (isset($_POST["cidr{$idx}"])) {
		$cidr = $_POST["cidr" . $idx];
		$cidr_name = $_POST["cidr_name" . $idx];
		if (!empty($cidr)) {
			if (!is_subnet($cidr)) {
				$input_errors[] = "Local network \"{$cidr}\" is not a valid cidr format.";
			}
		}
		if (!empty($cidr_name)) {
			if (empty($cidr)) {
				$input_errors[] = "Local network cidr missing for network name \"{$cidr_name}\".";
			}
			if (!preg_match('/^[\w]+$/', $cidr_name)) {
				$input_errors[] = "Local network name \"{$cidr_name}\" contains invalid characters. Allowed characters are [A-Za-z_0-9].";
			}
		}
		$idx++;
	}

	/* MaxMind GeoLite DB update */
	if ($post['Submit'] == "Update GeoLite2 DB") {
		if (empty($post['maxmind_account']) || empty($post['maxmind_key'])) {
			$input_errors[] = "To download MaxMind GeoIP data, you need to provide a valid GeoLite2 account and license key.";
		} else {
			$status = ntopng_update_geolite2_db($post['maxmind_account'], $post['maxmind_key']);
			if (!$status) {
				$input_errors[] = "MaxMind GeoIP update failed--check account and license key.";
			}
		}
	}

	/* Data deletion */
	if ($post['Delete'] == "Delete data") {
		ntopng_delete_data();
	}
	if ($post['Reset'] == "Reset ntopng") {
		ntopng_reset();
	}
}

?>
