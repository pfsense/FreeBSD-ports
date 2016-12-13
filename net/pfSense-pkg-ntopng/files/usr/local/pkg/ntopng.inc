<?php
/*
	ntopng.inc
	part of pfSense (https://www.pfSense.org/)
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
require_once("globals.inc");
require_once("interfaces.inc");
require_once("pkg-utils.inc");
require_once("service-utils.inc");
require_once("util.inc");
require_once("certs.inc");

global $redis_path;
$redis_path = "/usr/local/bin";

function ntopng_php_install_command() {
	$fonts_path = "/usr/local/lib/X11/fonts";
	/* Fix broken GUI fonts */
	mwexec("/bin/cp -Rp {$fonts_path}/webfonts/ {$fonts_path}/TTF/");

	/* Create dirs for Redis DB, data and graphs */
	ntopng_create_datadir();
}

function ntopng_php_deinstall_command() {
	global $config;

	/* Wipe data and settings if the user does not wish to keep them */
	$ntopng_config = $config['installedpackages']['ntopng']['config'][0];
	if ($ntopng_config['keepdata'] != "on") {
		if (is_dir("/var/db/ntopng/")) {
			mwexec("rm -rf /var/db/ntopng/");
		}
		if (is_array($config['installedpackages']['ntopng'])) {
			unset($config['installedpackages']['ntopng']);
			write_config("[ntopng] Removed package settings on uninstall.");
		}
		log_error(gettext("[ntopng] Removed package data and settings since 'Keep Data/Settings' is disabled."));
	}
}

function ntopng_write_cert_file($file, $cert) {
	$fd = fopen($file, "w");
	if (!$fd) {
		log_error("ERROR: Could not open {$file} for writing");
		return;
	}
	chmod($file, 0600);
	/* newline to deal with certs/keys lacking newline at end */
	fwrite($fd, base64_decode($cert['crt']) . "\n");
	fwrite($fd, base64_decode($cert['prv']) . "\n");
	fclose($fd);
}


function ntopng_sync_package() {
	global $config, $ntopng_config, $redis_path;
	/* These are done via ntopng_validate_input(), just return */
	if ($_POST['Submit'] == "Update GeoIP Data") {
		return;
	}
	if ($_POST['Delete'] == "Delete (Historical) Data") {
		return;
	}

	$ifaces = "";
	$ntopng_config =& $config['installedpackages']['ntopng']['config'][0];

	/* Just stop services and unlink rc script if disabled */
	if ($ntopng_config['enable'] != "on") {
		ntopng_services_stop();
		unlink_if_exists("/usr/local/etc/rc.d/ntopng.sh");
		unlink_if_exists("/usr/local/share/ntopng/httpdocs/ssl/ntopng-cert.pem");
		return;
	}

	foreach ($ntopng_config['interface_array'] as $iface) {
		$if = convert_friendly_interface_to_real_interface_name($iface);
		if ($if) {
			$ifaces .= " -i " . escapeshellarg("{$if}");
		}
	}

	/* DNS Mode */
	if (is_numeric($ntopng_config['dns_mode']) && ($ntopng_config['dns_mode'] >= 0) && ($ntopng_config['dns_mode'] <= 3)) {
		$dns_mode = "--dns-mode " . escapeshellarg($ntopng_config['dns_mode']);
	}

	/* Local Networks */
	switch ($ntopng_config['local_networks']) {
		case "selected":
			$nets = array();
			foreach ($ntopng_config['interface_array'] as $iface) {
				if (is_ipaddr(get_interface_ip($iface))) {
					$nets[] = gen_subnet(get_interface_ip($iface), get_interface_subnet($iface)) . '/' . get_interface_subnet($iface);
				}
			}
			if (!empty($nets)) {
				$local_networks = "--local-networks " . escapeshellarg(implode(",", $nets));
			}
			break;
		case "lanonly":
			if (is_ipaddr(get_interface_ip('lan'))) {
				$local_networks = "--local-networks " . escapeshellarg(gen_subnet(get_interface_ip('lan'), get_interface_subnet('lan')) . '/' . get_interface_subnet('lan'));
			}
			break;
		case "rfc1918":
		default:
			$local_networks = "--local-networks '192.168.0.0/16,172.16.0.0/12,10.0.0.0/8'";
			break;
	}

	/* Historical Data Storage, Dump expired flows */
	if ($ntopng_config['dump_flows'] == "on") {
		$dump_flows = "-F";
	}

	/* Disable alerts */
	if ($ntopng_config['disable_alerts'] == "on") {
		$disable_alerts = "-H";
	}

	if ($config['system']['webgui']['protocol'] == "https") {
		$cert =& lookup_cert($config['system']['webgui']['ssl-certref']);
		ntopng_write_cert_file("/usr/local/share/ntopng/httpdocs/ssl/ntopng-cert.pem", $cert);
		$http_args = "-w 0 -W 3000";
	} else {
		unlink_if_exists("/usr/local/share/ntopng/httpdocs/ssl/ntopng-cert.pem");
		$http_args = "-w 3000";
	}

	/* Create rc script */
	$start = "";
	$stop = "";
	$start .= "\t{$redis_path}/redis-server --bind 127.0.0.1 ::1 --dir /var/db/ntopng/ --dbfilename ntopng.rdb &\n";
	// TODO:
	// Add support for --data-dir /somewhere, --httpdocs-dir /somewhereelse,
	// --dump-timeline (on/off) --http-port, --https-port

	$start .= "\t/usr/local/bin/ntopng -d /var/db/ntopng -G /var/run/ntopng.pid -s -e {$http_args} {$disable_alerts} {$dump_flows} {$ifaces} {$dns_mode} {$aggregations} {$local_networks} &\n";
	$stop .= "/usr/bin/killall ntopng redis-cli redis-server\n";
	write_rcfile(array("file" => "ntopng.sh", "start" => $start, "stop" => $stop));

	/* Set up admin password */
	ntopng_set_redis_password();
	
	/* (Re)start services if not booting */
	if (!platform_booting()) {
		ntopng_services_stop();
		start_service("ntopng");
		sleep(20);
	}
}

function ntopng_services_stop() {
	if ((is_process_running("redis-server")) || (is_process_running("ntopng"))) {
		stop_service("ntopng");
	}
	for ($i = 0; $i <= 10; $i++) {
		if ((!is_process_running("redis-server")) && (!is_process_running("ntopng"))) {
			break;
		}
		sleep(2);
	}
}

function ntopng_redis_started() {
	global $redis_path, $redis_started;
	$redis_started = false;

	if (!is_process_running("redis-server")) {
		mwexec_bg("{$redis_path}/redis-server --bind 127.0.0.1 ::1 --dir /var/db/ntopng/ --dbfilename ntopng.rdb");
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
	return $redis_started;
}

function ntopng_set_redis_password() {
	global $config, $ntopng_config, $redis_path;
	$ntopng_config = $config['installedpackages']['ntopng']['config'][0];

	if (!empty($ntopng_config['redis_password'])) {
		$password = md5($ntopng_config['redis_password']);
		if (ntopng_redis_started()) {
			mwexec("{$redis_path}/redis-cli SET ntopng.user.admin.password " . escapeshellarg($password));
			// Make sure the preferences menu is accessible (Bug #6999)
			mwexec("{$redis_path}/redis-cli SET ntopng.user.admin.group administrator");
			mwexec("{$redis_path}/redis-cli save");
		} else {
			log_error(gettext("[ntopng] Cannot set admin password - redis-server is not running."));
		}
	}
}

function ntopng_create_datadir() {
	safe_mkdir("/var/db/ntopng/rrd/graphics", 0755);
	mwexec("/bin/chmod -R 755 /var/db/ntopng");
	mwexec("/usr/sbin/chown -R nobody:nobody /var/db/ntopng");
}

function ntopng_update_geoip() {
	global $config;
	$fetchcmd = "/usr/bin/fetch";
	$geolite_city = "https://geolite.maxmind.com/download/geoip/database/GeoLiteCity.dat.gz";
	$geolite_city_v6 = "https://geolite.maxmind.com/download/geoip/database/GeoLiteCityv6-beta/GeoLiteCityv6.dat.gz";
	$geoip_asnum = "https://download.maxmind.com/download/geoip/database/asnum/GeoIPASNum.dat.gz";
	$geoip_asnum_v6 = "https://download.maxmind.com/download/geoip/database/asnum/GeoIPASNumv6.dat.gz";
	$output_dir = "/usr/local/share/ntopng";

	mwexec("{$fetchcmd} -o {$output_dir} -T 5 {$geolite_city}");
	mwexec("{$fetchcmd} -o {$output_dir} -T 5 {$geolite_city_v6}");
	mwexec("{$fetchcmd} -o {$output_dir} -T 5 {$geoip_asnum}");
	mwexec("{$fetchcmd} -o {$output_dir} -T 5 {$geoip_asnum_v6}");

	ntopng_fixup_geoip();

	/* Do not (re)start services on package (re)install, only on manual GeoIP updates via the GUI */
	if ($_POST['Submit'] == "Update GeoIP Data") {
		$ntopng_config = $config['installedpackages']['ntopng']['config'][0];
		ntopng_services_stop();
		if ($ntopng_config['enable'] == "on") {
			start_service("ntopng");
		}
	}
}

function ntopng_fixup_geoip() {
	$target_dir = "/usr/local/share/ntopng/httpdocs/geoip";
	$source_dir = "/usr/local/share/ntopng";

	safe_mkdir($target_dir, 0755);

	foreach(glob("{$source_dir}/Geo*.dat*") as $geofile) {
		/* Decompress if needed. */
		if (substr($geofile, -3, 3) == ".gz") {
			// keep -f here, otherwise the files will not get updated
			mwexec("/usr/bin/gzip -d -f " . escapeshellarg($geofile));
		}
	}

	/* Use a separate glob since the filenames could have changed since the last run */
	foreach(glob("{$source_dir}/Geo*.dat*") as $geofile) {
		$target_file = $target_dir . '/' . basename($geofile);
		if (!file_exists($target_file)) {
			symlink($geofile, $target_file);
		}
	}
}

function ntopng_flush_historical_data() {
	global $config, $ntopng_config, $redis_path;
	$ntopng_config = $config['installedpackages']['ntopng']['config'][0];

	if (ntopng_redis_started()) {
		/* Delete all the keys of all the existing Redis databases */
		mwexec("{$redis_path}/redis-cli flushall");
		log_error(gettext("[ntopng] Flushed Redis DB."));
		/* Set admin password while redis-server is still running */
		ntopng_set_redis_password();
		log_error(gettext("[ntopng] Set admin password for Redis DB."));
		/* Stop services and delete all graphs, data and dump flows */
		ntopng_services_stop();
		if (is_dir("/var/db/ntopng/")) {
			mwexec("rm -rf /var/db/ntopng/");
			log_error(gettext("[ntopng] Deleted ntopng historical traffic data and graphs."));
		} else {
			log_error(gettext("[ntopng] Nothing to delete; /var/db/ntopng/ directory not found."));
		}
		/* Re-create the required directory structure with proper permissions */
		ntopng_create_datadir();
		log_error(gettext("[ntopng] Re-created required data directory structure."));
		/* Resync settings and restart services if enabled */
		unset($_POST['Delete']);
		ntopng_sync_package();
		log_error(gettext("[ntopng] Resynced ntopng settings."));
	} else {
		$error = "Cannot delete historical data - redis-server is not running.";
		log_error(gettext("[ntopng] {$error}"));
		file_notice("ntopng", $error, "ntopng Delete Historical Data", "");
	}
}

function ntopng_validate_input($post, &$input_errors) {
	if (empty($post['redis_password']) || empty($post['redis_passwordagain'])) {
		$input_errors[] = "You must provide (and confirm) ntopng's password.";
	}
	if ((strlen($post['redis_password']) < 5) || (strlen($post['redis_passwordagain']) < 5)) {
		$input_errors[] = "Password must have at least 5 characters.";
	}
	if ($post['redis_password'] != $post['redis_passwordagain']) {
		$input_errors[] = "The provided passwords did not match.";
	}
	if ($post['Submit'] == "Update GeoIP Data") {
		ntopng_update_geoip();
	}
	if ($post['Delete'] == "Delete (Historical) Data") {
		ntopng_flush_historical_data();
	}
}

?>
