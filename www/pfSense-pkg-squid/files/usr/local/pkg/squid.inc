<?php
/*
	squid.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2006-2009 Scott Ullrich
	Copyright (C) 2006 Fernando Lemos
	Copyright (C) 2012 Martin Fuchs
	Copyright (C) 2012-2014 Marcello Coutinho
	Copyright (C) 2013 Gekkenhuis
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
require_once('config.inc');
require_once('globals.inc');
require_once('notices.inc');
require_once('pfsense-utils.inc');
require_once('pkg-utils.inc');
require_once('services.inc');
require_once('service-utils.inc');
require_once('util.inc');
require_once('xmlrpc_client.inc');

if (!function_exists("filter_configure")) {
	require_once("filter.inc");
}

$shortcut_section = "squid";

define('SQUID_BASE', '/usr/local');
define('SQUID_LOCALBASE', '/usr/local');
define('SQUID_UID', 'squid');
define('SQUID_GID', 'proxy');
define('SQUID_EXTRA_GID', 'squid');
define('SQUID_CONFBASE', SQUID_LOCALBASE .'/etc/squid');
define('SQUID_CONFFILE', SQUID_CONFBASE . '/squid.conf');
define('SQUID_ACLDIR', '/var/squid/acl');
define('SQUID_PASSWD', '/var/etc/squid.passwd');
define('SQUID_SSL_DB', '/var/squid/lib/ssl_db');

$valid_acls = array();

$uname = posix_uname();
if ($uname['machine'] == 'amd64') {
	ini_set('memory_limit', '250M');
}

/* Squid reverse proxy */
require_once('/usr/local/pkg/squid_reverse.inc');
/* Squid javascript helpers */
require_once('/usr/local/pkg/squid_js.inc');
/* Squid antivirus intergration features helpers */
require_once('/usr/local/pkg/squid_antivirus.inc');

/*
 * Utility functions
 */

/* Handle base64 encoding and linebreaks in textarea configuration fields */
function sq_text_area_decode($text) {
	return preg_replace('/\r\n/', "\n", base64_decode($text));
}

/* Get interface IP and netmask for Squid interfaces */
function squid_get_real_interface_address($iface) {
	if (!function_exists("get_interface_ip")) {
		require_once("interfaces.inc");
	}

	return array(get_interface_ip($iface), gen_subnet_mask(get_interface_subnet($iface)));
}

/* Check whether ACL is valid */
function squid_is_valid_acl($acl) {
	global $valid_acls;

	if (!is_array($valid_acls)) {
		return;
	}

	return in_array($acl, $valid_acls);
}

/* Recursively change ownership of directories */
function squid_chown_recursive($dir, $user, $group) {
	if (empty($dir) || ($dir == '/') || ($dir == '/usr/local') || !is_dir($dir)) {
		log_error(gettext("[squid] Attempted to chown an invalid directory: '{$dir}'"));
		return;
	}

	chown($dir, $user);
	chgrp($dir, $group);
	$handle = opendir($dir);
	if ($handle) {
		while (($item = readdir($handle)) !== false) {
			if (!empty($item) && ($item != ".") && ($item != "..")) {
				$path = "{$dir}/{$item}";
				// Recurse unless it's the cache dir, that is slow and rarely necessary.
				if (is_dir($path) && (basename($dir) != "cache")) {
					squid_chown_recursive($path, $user, $group);
				} elseif (is_file($path)) {
					chown($path, $user);
					chgrp($path, $group);
				}
			}
		}
	} else {
		log_error(gettext("[squid] squid_chown_recursive() call failed; permissions not set for directory: '{$dir}'"));
	}
}

/* Check whether Squid is enabled */
function squid_enabled() {
	global $config, $proxy_enabled;
	$proxy_enabled = false;

	if (is_array($config['installedpackages']['squid']['config'])) {
		// check whether Squid is enabled ...
		if ($config['installedpackages']['squid']['config'][0]['enable_squid'] == "on") {
			// ... and has at least one interface configured ...
			if (!empty($config['installedpackages']['squid']['config'][0]['active_interface'])) {
				$proxy_enabled = true;
			// ... or whether Squid reverse proxy is enabled
			} elseif (squid_reverse_enabled()) {
				$proxy_enabled = true;
			}
		}
	}
	return $proxy_enabled;
}

/* Get list of certificates for SSL proxy */
function squid_get_server_certs() {
	global $config;
	$cert_arr = array();
	$cert_arr[] = array('refid' => 'none', 'descr' => 'none');
	foreach ($config['cert'] as $cert) {
		$cert_arr[] = array('refid' => $cert['refid'], 'descr' => $cert['descr']);
	}
	return $cert_arr;
}

/* Handle root CA certificates bundle */
function squid_check_ca_hashes() {
	global $config, $g;

	// check certificates
	$cert_count = 0;
	if (is_dir(SQUID_LOCALBASE . '/share/certs')) {
		if ($handle = opendir(SQUID_LOCALBASE . '/share/certs')) {
			while (false !== ($file = readdir($handle))) {
				if (preg_match ("/\d+.0/", $file)) {
					$cert_count++;
				}
			}
			closedir($handle);
		}
	}
	if ($cert_count < 10) {
		conf_mount_rw();
		// create ca-root hashes from ca-root-nss package
		log_error("[squid] Creating root certificate bundle hashes from the Mozilla Project...");
		$cas = file(SQUID_LOCALBASE . '/share/certs/ca-root-nss.crt');
		$cert = 0;
		foreach ($cas as $ca) {
			if (preg_match("/--BEGIN CERTIFICATE--/", $ca)) {
				$cert = 1;
			}
			if ($cert == 1) {
				$crt .= $ca;
			}
			if (preg_match("/-END CERTIFICATE-/", $ca)) {
				file_put_contents("/tmp/cert.pem", $crt, LOCK_EX);
				$cert_hash = array();
				exec("/usr/bin/openssl x509 -hash -noout -in /tmp/cert.pem", $cert_hash);
				file_put_contents(SQUID_LOCALBASE . "/share/certs/" . $cert_hash[0] . ".0", $crt, LOCK_EX);
				$crt = "";
				$cert = 0;
			}
		}
	}
}

/*
 * Squid cache setup
 */

 /* Create Squid disk cache directories */
function squid_dash_z($cache_action = 'none') {
	global $config;

	// We need cache configured after initial package install
	if (!is_array($config['installedpackages']['squidcache']['config'])) {
		log_error("[squid] 'Local Cache' not configured, disk cache will be disabled.");
		log_error("[squid] Please, configure and save 'Local Cache' settings before enabling Squid proxy.");
		return;
	}

	if (is_array($config['installedpackages']['squidcache'])) {
		$cachesettings = $config['installedpackages']['squidcache']['config'][0];
	} else {
		$cachesettings = array();
	}
	$cachedir = ($cachesettings['harddisk_cache_location'] ? $cachesettings['harddisk_cache_location'] : '/var/squid/cache');
	$numdirs = ($cachesettings['level1_subdirs'] ? $cachesettings['level1_subdirs'] : 16);
	if (is_dir($cachedir)) {
		$currentdirs = count(glob("{$cachedir}/*", GLOB_ONLYDIR));
	} else {
		$currentdirs = 0;
	}

	// If the cache system is null, there is no need to initialize the (irrelevant) cache dir.
	// If it already exists, delete it.
	if ($cachesettings['harddisk_cache_system'] == "null") {
		if (is_dir($cachedir)) {
			if (substr($cachedir, 0, 11) === "/var/squid/") {
				log_error("[squid] Deleting cache dir '{$cachedir}' since 'Hard Disk Cache System' is set to null...");
				// cannot nuke disk cache while Squid is running
				squid_stop_monitor();
				if (is_service_running('squid')) {
					stop_service("squid");
				}
				@rename($cachedir, "{$cachedir}.old");
				mwexec_bg("/bin/rm -rf {$cachedir}.old");
				squid_restart_services();
			} else {
				log_error("[squid] 'Hard Disk Cache System' is set to null.");
				log_error("[squid] Will NOT delete cache dir '{$cachedir}' since it is not located under /var/squid. Delete manually if required.");
			}
		}
		return;
	}

	// Re-create the cachedir if clean is forced by manually,
	// or if the cachedir changed, or level1_subdirs don't exist or the number of level1_subdirs changed
	if ($cache_action == "clean" || !is_dir($cachedir) || !is_dir($cachedir . '/00') || $numdirs != $currentdirs) {
		// cannot nuke disk cache while Squid is running
		squid_stop_monitor();
		if (is_service_running('squid')) {
			stop_service("squid");
		}
		if (is_dir($cachedir)) {
			if (substr($cachedir, 0, 11) === "/var/squid/") {
				@rename($cachedir, "{$cachedir}.old");
				mwexec_bg("/bin/rm -rf {$cachedir}.old");
			} else {
				log_error("[squid] Will NOT delete cache dir '{$cachedir}' since it is not located under /var/squid. Delete manually if required.");
			}
		}
		squid_create_cachedir();
		squid_restart_services();
	}
}

/* Helper function for squid_dash_z() */
function squid_create_cachedir() {
	global $config;
	if (is_array($config['installedpackages']['squidcache'])) {
		$cachesettings = $config['installedpackages']['squidcache']['config'][0];
	} else {
		$cachesettings = array();
	}
	$cachedir = ($cachesettings['harddisk_cache_location'] ? $cachesettings['harddisk_cache_location'] : '/var/squid/cache');

	if (!is_dir($cachedir)) {
		log_error("[squid] Creating cache dir '{$cachedir}' ...");
		safe_mkdir($cachedir, 0755);
		@chown($cachedir, SQUID_UID);
		@chgrp($cachedir, SQUID_GID);
	}

	if (!is_dir($cachedir . '/00')) {
		log_error("[squid] Creating Squid cache subdirs in {$cachedir} ...");
		mwexec(SQUID_BASE. "/sbin/squid -z -f " . SQUID_CONFFILE);
		// Double check permissions here, should be safe to recurse cache dir if it's small here.
		squid_chown_recursive($cachedir, SQUID_UID, SQUID_GID);
	}

	if (file_exists("/var/squid/cache/swap.state")) {
		chown("/var/squid/cache/swap.state", SQUID_UID);
		chgrp("/var/squid/cache/swap.state", SQUID_GID);
		chmod("/var/squid/cache/swap.state", 0666);
	}
}


/*
 * rc scripts, services and cronjobs
 */

/* Handle cronjob install/uninstall */
function squid_install_cron($should_install) {
	if (platform_booting()) {
		return;
	}

	$cron_cmd = SQUID_BASE . "/sbin/squid -k rotate -f " . SQUID_CONFFILE;
	/*
	 * This is here to prevent Squid from filling disk completely on misconfigured boxes.
	 * When 'Hard Disk Cache System' is set to null, the script silently returns, no need to check here.
	 * Otherwise, swapstate_check.php will only clear the disk cache on the following conditions:
	 * - if the swap.state file is taking up more than 75% of disk space,
	 * - or the drive is 90% full and swap.state is larger than 1GB.
	 */
	$swapstate_cmd = "/usr/local/pkg/swapstate_check.php";
	if (($should_install) && (squid_enabled())) {
		log_error("[squid] Adding cronjobs ...");
		install_cron_job("{$cron_cmd}", $should_install, "0", "0", "*", "*", "*", "root");
		install_cron_job("{$swapstate_cmd}", $should_install, "15", "0", "*", "*", "*", "root");
	} else {
		log_error("[squid] Removing cronjobs ...");
		install_cron_job("{$cron_cmd}", false);
		install_cron_job("{$swapstate_cmd}", false);
	}
}

/* Create /usr/local/etc/rc.d/squid.sh rc script */
function squid_write_rcfile() {
	/* Declare a variable for the SQUID_CONFFILE constant. */
	/* Then the variable can be referenced easily in the heredoc text that generates the rc file. */
	$squid_conffile_var = SQUID_CONFFILE;
	$squid_base = SQUID_BASE;
	$rc = array();
	$rc['file'] = 'squid.sh';
	$rc['start'] = <<< EOD
#/sbin/sysctl net.inet.ip.portrange.reservedhigh=0
if [ -z "`/bin/ps auxw | /usr/bin/grep "[s]quid " | /usr/bin/awk '{print $2}'`" ]; then
	{$squid_base}/sbin/squid -f {$squid_conffile_var}
fi

EOD;

	$rc['stop'] = <<< EOD
{$squid_base}/sbin/squid -k shutdown -f {$squid_conffile_var}
# Just to be sure...
sleep 5
if [ -n "`/bin/ps auxw | /usr/bin/grep "[s]quid " | /usr/bin/awk '{print $2}'`" ]; then
	{$squid_base}/sbin/squid -k kill -f {$squid_conffile_var}
fi

if [ -x /usr/bin/ipcs ]; then
# http://man.chinaunix.net/newsoft/squid/Squid_FAQ/FAQ-22.html#ss22.8
/usr/bin/ipcs | /usr/bin/grep '^[mq]' | /usr/bin/awk '{printf "ipcrm -%s %s\\n", $1, $2}' | /bin/sh
fi

/usr/bin/killall -9 squid 2>/dev/null
/usr/bin/killall pinger 2>/dev/null

EOD;

	conf_mount_rw();
	write_rcfile($rc);
	conf_mount_ro();
}

/* Start sqp_monitor.sh watchdog script */
function squid_start_monitor() {
	if (squid_enabled()) {
		if (!exec("/bin/ps auxw | /usr/bin/grep '[s]qpmon'")) {
			log_error("[squid] Starting a proxy monitor script");
			mwexec_bg("/usr/local/etc/rc.d/sqp_monitor.sh start");
		}
		sleep(1);
	} else {
		log_error("[squid] Squid is disabled. Not starting a proxy monitor script");
	}
}

/* Stop sqp_monitor.sh watchdog script */
function squid_stop_monitor() {
	/* kill any running proxy alarm scripts */
	if (exec("/bin/ps auxw | /usr/bin/grep '[s]qpmon'")) {
		log_error("[squid] Stopping any running proxy monitors");
		mwexec("/usr/local/etc/rc.d/sqp_monitor.sh stop");
	}
	sleep(1);
}

/* Start and/or stop services according to Squid configuration */
function squid_restart_services() {
	global $config;

	// do not (re)start squid services on boot
	if (platform_booting()) {
		return;
	}

	if (squid_enabled()) {
		/* kill any running proxy alarm scripts */
		squid_stop_monitor();

		if (!is_service_running('squid')) {
			log_error("[squid] Starting service...");
			mwexec(SQUID_BASE . "/sbin/squid -f " . SQUID_CONFFILE);
		} else {
			log_error("[squid] Reloading for configuration sync...");
			mwexec(SQUID_BASE . "/sbin/squid -k reconfigure -f " . SQUID_CONFFILE);
		}
		// sleep for a couple seconds to give squid a chance to fire up fully.
		for ($i = 0; $i < 10; $i++) {
			if (!is_service_running('squid')) {
				sleep(1);
			}
		}
		/* restart proxy alarm scripts */
		squid_start_monitor();

	} else {
		/* Squid is disabled - kill any running proxy alarm scripts and stop Squid services */
		squid_stop_monitor();
		if (is_service_running('squid')) {
			log_error("[squid] Stopping service...");
			stop_service("squid");
		}
	}
}

/*
 * Change the squid OS user to match what is required for squid to run and have
 * access to the files and devices it needs. See #5869
 */
function squid_fixup_user() {
	$squid_userinfo = posix_getpwnam(SQUID_UID);
	$squid_groupinfo = posix_getgrgid($squid_userinfo['gid']);
	$squid_exgrpinfo = posix_getgrnam(SQUID_EXTRA_GID);

	/* If the squid user group is not what we want, fix it. */
	if ($squid_groupinfo['name'] != SQUID_GID) {
		mwexec('/usr/sbin/pw usermod -n ' . escapeshellarg(SQUID_UID) . ' -g ' . escapeshellarg(SQUID_GID));
	}
	/* Ensure the squid user is also a member of the desired 'extra' group */
	if (!in_array(SQUID_UID, $squid_exgrpinfo['members'])) {
		mwexec('/usr/sbin/pw groupmod -n ' . escapeshellarg(SQUID_EXTRA_GID) . ' -m ' . escapeshellarg(SQUID_UID));
	}
}

/*
 * Squid package install/uninstall
 */

function squid_install_command() {
	global $config, $g;

	/* Set storage system for nanobsd */
	if (!is_array($config['installedpackages']['squidcache'])) {
		$config['installedpackages']['squidcache'] = array();
	}
	if ($g['platform'] == "nanobsd") {
		$config['installedpackages']['squidcache']['config'][0]['harddisk_cache_system'] = 'null';
	}

	// Fix user/group entry permissions
	squid_fixup_user();

	// migrate configuration from old versions
	squid_upgrade_config();

	/* make sure pinger is executable and suid root */
	// XXX: Bug #5114
	if (file_exists(SQUID_LOCALBASE . "/libexec/squid/pinger")) {
		chgrp(SQUID_LOCALBASE . "/libexec/squid/pinger", SQUID_GID);
	}

	// create squid rcfile
	squid_write_rcfile();

	// create squid monitor rcfile
	write_rcfile(array(
		"file" => "sqp_monitor.sh",
		"start" => "/usr/local/pkg/sqpmon.sh &",
		"stop" => "/bin/ps awux | /usr/bin/grep \"sqpmon\" | /usr/bin/grep -v \"grep\" | /usr/bin/grep -v \"php\" | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill")
	);

	// antivirus intergration
	squid_antivirus_install_command();

	foreach (array(SQUID_CONFBASE, SQUID_ACLDIR, SQUID_SSL_DB) as $dir) {
		safe_mkdir($dir, 0755);
		squid_chown_recursive($dir, SQUID_UID, SQUID_GID);
	}

	if (!file_exists(SQUID_CONFBASE . '/mime.conf') && file_exists(SQUID_CONFBASE . '/mime.conf.default')) {
		copy(SQUID_CONFBASE . '/mime.conf.default', SQUID_CONFBASE . '/mime.conf');
	}

	// remove unwanted rc script
	unlink_if_exists("/usr/local/etc/rc.d/squid");

	// remove broken cronjobs possibly left over after 'Clear Cache on Log Rotate' misfeature
	install_cron_job("/usr/local/pkg/swapstate_check.php clean;", false);
	install_cron_job("/bin/rm /var/squid/cache/swap.state;", false);

	/* NT Domain authentication has been removed */
	unlink_if_exists(SQUID_CONFBASE . '/msntauth.conf');

}

function squid_deinstall_command() {
	global $config, $g, $keep;

	/* remove cronjobs */
	squid_install_cron(false);

	/* kill all running services */
	mwexec('/usr/local/etc/rc.d/sqp_monitor.sh stop');
	mwexec("/bin/ps awux | /usr/bin/egrep -i '[s]quid -f|\([s]quid\)' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");
	mwexec("/bin/ps awux | /usr/bin/grep '[d]iskd' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");
	mwexec("/bin/ps awux | /usr/bin/grep '[d]nsserver' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");
	mwexec("/bin/ps awux | /usr/bin/grep '[u]nlinkd' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");

	/* delete rc scripts */
	unlink_if_exists('/usr/local/etc/rc.d/sqp_monitor.sh');
	unlink_if_exists('/usr/local/etc/rc.d/squid.sh');

	/* clean up created directories if 'Keep Settings/Data' is disabled */
	if (is_array($config['installedpackages']['squidcache'])) {
		$cachesettings = $config['installedpackages']['squidcache']['config'][0];
	} else {
		$cachesettings = array();
	}
	$cachedir = ($cachesettings['harddisk_cache_location'] ? $cachesettings['harddisk_cache_location'] : '/var/squid/cache');
	if (is_array($config['installedpackages']['squid'])) {
		$squidsettings = $config['installedpackages']['squid']['config'][0];
	} else {
		$squidsettings = array();
	}
	$logdir = ($squidsettings['log_dir'] ? $squidsettings['log_dir'] : '/var/squid/logs');
	$keep = ($squidsettings['keep_squid_data'] ? true : false);

	if (!$keep) {
		if (is_dir("{$cachedir}")) {
			if (substr($cachedir, 0, 11) === "/var/squid/") {
				mwexec_bg("/bin/rm -rf {$cachedir}");
			} else {
				log_error("[squid] Will NOT delete Squid cache dir '{$cachedir}' since it is not located under /var/squid. Delete manually if required.");
			}
		}
		if (is_dir("{$logdir}")) {
			if (substr($logdir, 0, 11) === "/var/squid/") {
				mwexec("/bin/rm -rf {$logdir}");
			} else {
				log_error("[squid] Will NOT delete Squid log dir '{$logdir}' since it is not located under /var/squid. Delete manually if required.");
			}
		}
		$dirs = array("/var/run/squid", "/var/squid");
		foreach ($dirs as $dir) {
			if (is_dir("{$dir}")) {
				mwexec("/bin/rm -rf {$dir}");
			}
		}
	}

	// remove antivirus integration features
	squid_antivirus_deinstall_command();

	filter_configure();

	/* Remove package settings from config if 'Keep Settings/Data' is disabled */
	if (!$keep) {
		log_error("[squid] Removing all Squid settings since 'Keep Settings/Data' is disabled...");
		if (is_array($config['installedpackages']['squid'])) {
			unset($config['installedpackages']['squid']);
		}
		if (is_array($config['installedpackages']['squidantivirus'])) {
			unset($config['installedpackages']['squidantivirus']);
		}
		if (is_array($config['installedpackages']['squidauth'])) {
			unset($config['installedpackages']['squidauth']);
		}
		if (is_array($config['installedpackages']['squidcache'])) {
			unset($config['installedpackages']['squidcache']);
		}
		if (is_array($config['installedpackages']['squidnac'])) {
			unset($config['installedpackages']['squidnac']);
		}
		if (is_array($config['installedpackages']['squidreverse'])) {
			unset($config['installedpackages']['squidreverse']);
		}
		if (is_array($config['installedpackages']['squidreversegeneral'])) {
			unset($config['installedpackages']['squidreversegeneral']);
		}
		if (is_array($config['installedpackages']['squidreversepeer'])) {
			unset($config['installedpackages']['squidreversepeer']);
		}
		if (is_array($config['installedpackages']['squidreverseredir'])) {
			unset($config['installedpackages']['squidreverseredir']);
		}
		if (is_array($config['installedpackages']['squidreverseuri'])) {
			unset($config['installedpackages']['squidreverseuri']);
		}
		if (is_array($config['installedpackages']['squidsync'])) {
			unset($config['installedpackages']['squidsync']);
		}
		if (is_array($config['installedpackages']['squidtraffic'])) {
			unset($config['installedpackages']['squidtraffic']);
		}
		if (is_array($config['installedpackages']['squidremote'])) {
			unset($config['installedpackages']['squidremote']);
		}
		if (is_array($config['installedpackages']['squidusers'])) {
			unset($config['installedpackages']['squidusers']);
		}
	}
}

/* Migrate configuration from god knows which Squid package versions */
/* None of these ever existed with Squid 3.4 package and this cruft should be most likely just removed */
function squid_upgrade_config() {
	global $config;
	/* migrate existing csv config fields */
	if (is_array($config['installedpackages']['squidauth']['config'])) {
		$settingsauth = $config['installedpackages']['squidauth']['config'][0];
	}
	if (is_array($config['installedpackages']['squidcache']['config'])) {
		$settingscache = $config['installedpackages']['squidcache']['config'][0];
	}
	if (is_array($config['installedpackages']['squidnac']['config'])) {
		$settingsnac = $config['installedpackages']['squidnac']['config'][0];
	}
	if (is_array($config['installedpackages']['squid']['config'])) {
		$settingsgen = $config['installedpackages']['squid']['config'][0];
	}

	/* migrate auth settings */
	if (!empty($settingsauth['no_auth_hosts']) && strstr($settingsauth['no_auth_hosts'], ",")) {
		$settingsauth['no_auth_hosts'] = base64_encode(implode("\n", explode(",", $settingsauth['no_auth_hosts'])));
		$config['installedpackages']['squidauth']['config'][0]['no_auth_hosts'] = $settingsauth['no_auth_hosts'];
	}
	/* NT Domain authentication has been removed - Bug #7017 */
	if (!empty($settingsauth['auth_method'])) {
		if (preg_match("/msnt/i", $settingsauth['auth_method'])) {
			$msnt_msg = "NT Domain authentication has been removed - see Bug #7017! Use LDAP for AD authentication.";
			file_notice("squid", $msnt_msg, "Packages", "");
			log_error("[squid] {$msnt_msg}");
		}
	}

	/* migrate cache settings */
	if (!empty($settingscache['donotcache']) && strstr($settingscache['donotcache'], ",")) {
		$settingscache['donotcache'] = base64_encode(implode("\n", explode(",", $settingscache['donotcache'])));
		$config['installedpackages']['squidcache']['config'][0]['donotcache'] = $settingscache['donotcache'];
	}
	/* unset broken dynamic caching patterns removed since Squid3 package v0.4.3 */
	if (!empty($config['installedpackages']['squidcache']['config']['refresh_patterns'])) {
		unset($config['installedpackages']['squidcache']['config']['refresh_patterns']);
	}

	/* migrate nac settings */
	if (!empty($settingsnac['allowed_subnets']) && strstr($settingsnac['allowed_subnets'], ",")) {
		$settingsnac['allowed_subnets'] = base64_encode(implode("\n", explode(",", $settingsnac['allowed_subnets'])));
		$config['installedpackages']['squidnac']['config'][0]['allowed_subnets'] = $settingsnac['allowed_subnets'];
	}

	if (!empty($settingsnac['banned_hosts']) && strstr($settingsnac['banned_hosts'], ",")) {
		$settingsnac['banned_hosts'] = base64_encode(implode("\n", explode(",", $settingsnac['banned_hosts'])));
		$config['installedpackages']['squidnac']['config'][0]['banned_hosts'] = $settingsnac['banned_hosts'];
	}

	if (!empty($settingsnac['banned_macs']) && strstr($settingsnac['banned_macs'], ",")) {
		$settingsnac['banned_macs'] = base64_encode(implode("\n", explode(",", $settingsnac['banned_macs'])));
		$config['installedpackages']['squidnac']['config'][0]['banned_macs'] = $settingsnac['banned_macs'];
	}

	if (!empty($settingsnac['unrestricted_hosts']) && strstr($settingsnac['unrestricted_hosts'], ",")) {
		$settingsnac['unrestricted_hosts'] = base64_encode(implode("\n", explode(",", $settingsnac['unrestricted_hosts'])));
		$config['installedpackages']['squidnac']['config'][0]['unrestricted_hosts'] = $settingsnac['unrestricted_hosts'];
	}

	if (!empty($settingsnac['unrestricted_macs']) && strstr($settingsnac['unrestricted_macs'], ",")) {
		$settingsnac['unrestricted_macs'] = base64_encode(implode("\n", explode(",", $settingsnac['unrestricted_macs'])));
		$config['installedpackages']['squidnac']['config'][0]['unrestricted_macs'] = $settingsnac['unrestricted_macs'];
	}

	if (!empty($settingsnac['whitelist']) && strstr($settingsnac['whitelist'], ",")) {
		$settingsnac['whitelist'] = base64_encode(implode("\n", explode(",", $settingsnac['whitelist'])));
		$config['installedpackages']['squidnac']['config'][0]['whitelist'] = $settingsnac['whitelist'];
	}

	if (!empty($settingsnac['blacklist']) && strstr($settingsnac['blacklist'], ",")) {
		$settingsnac['blacklist'] = base64_encode(implode("\n", explode(",", $settingsnac['blacklist'])));
		$config['installedpackages']['squidnac']['config'][0]['blacklist'] = $settingsnac['blacklist'];
	}

	if (!empty($settingsnac['block_user_agent']) && strstr($settingsnac['block_user_agent'], ",")) {
		$settingsnac['block_user_agent'] = base64_encode(implode("\n", explode(",", $settingsnac['block_user_agent'])));
		$config['installedpackages']['squidnac']['config'][0]['block_user_agent'] = $settingsnac['block_user_agent'];
	}

	if (!empty($settingsnac['block_reply_mime_type']) && strstr($settingsnac['block_reply_mime_type'], ",")) {
		$settingsnac['block_reply_mime_type'] = base64_encode(implode("\n", explode(",", $settingsnac['block_reply_mime_type'])));
		$config['installedpackages']['squidnac']['config'][0]['block_reply_mime_type'] = $settingsnac['block_reply_mime_type'];
	}

	/* XXX: broken Captive Portal patch (Bug #5594) */
	if (isset($config['installedpackages']['squid']['config'][0]['patch_cp'])) {
		if ($config['installedpackages']['squid']['config'][0]['patch_cp'] == "on") {
			$cp_msg = "Patch Captive Portal feature was removed - see Bug #5594!";
			$cp_msg .= "Double-check '/etc/inc/captiveportal.inc' contents for sanity! Get a sane copy of the file from pfSense GitHub repository if needed.";
			file_notice("squid", $cp_msg, "Packages", "");
			log_error("[squid] {$cp_msg}");
			unset($cp_msg);
		}
		unset($config['installedpackages']['squid']['config'][0]['patch_cp']);
	}

	/* migrate reverse proxy settings */
	squid_reverse_upgrade_config();
	
	/* unset broken antivirus settings */
	squid_antivirus_upgrade_config();

	write_config();
}


/*
 * Squid input validation
 */

/* Proxy Server: General Settings input validation */
function squid_validate_general($post, &$input_errors) {
	global $config;

	if (is_array($config['installedpackages']['squid'])) {
		$settings = $config['installedpackages']['squid']['config'][0];
	} else {
		$settings = array();
	}

	// force users to configure cache
	if (!is_array($config['installedpackages']['squidcache']['config'])) {
		$input_errors[] = "Please, configure and save 'Local Cache' settings first.";
	}

	// force users to select at least one proxy or reverse proxy interface when enabling Squid
	if ($post['enable_squid'] == "on") {
		// if reverse proxy is configured, perhaps the user wants to use the reverse proxy features only
		if (!squid_reverse_enabled()) {
			if (empty($post['active_interface'])) {
				$input_errors[] = "You must select at least one interface under 'Proxy Interface(s)' to enable Squid proxy.";
				$input_errors[] = "If you intend to use Squid as reverse proxy ONLY, then visit Services: Squid Proxy Server: General, configure and save the reverse proxy settings first.";
			}
		} else {
			log_error("[squid] Enabled as reverse proxy ONLY. If this is not what you intended, visit Services: Squid Proxy Server: General and configure proxy interfaces.");
		}
	}

	$icp_port = trim($post['icp_port']);
	if (!empty($icp_port) && !is_port($icp_port)) {
		$input_errors[] = "You must enter a valid port number in the 'ICP port' field.";
	}
	unset($icp_port);

	if (substr($post['log_dir'], -1, 1) == '/') {
		$input_errors[] = 'Log location must not end with a / character.';
	}

	if ($post['log_dir']{0} != '/') {
		$input_errors[] = 'Log location must start with a / character.';
	}

	if (strlen($post['log_dir']) <= 3) {
		$input_errors[] = "Configured log location directory is not valid.";
	}

	$log_rotate = trim($post['log_rotate']);
	if (!empty($log_rotate) && (!is_numericint($log_rotate) or ($log_rotate < 1))) {
		$input_errors[] = "You must enter a valid number of days in the 'Log rotate' field.";
	}
	unset($log_rotate);

	// check that the proxy port does not clash with WebGUI
	$port = ($settings['proxy_port'] ? $settings['proxy_port'] : 3128);
	$port = $post['proxy_port'] ? $post['proxy_port'] : $port;
	$webgui_port = $config['system']['webgui']['port'];
	if (($config['system']['webgui']['port'] == "") && ($config['system']['webgui']['protocol'] == "http")) {
		$webgui_port = 80;
	}
	if (($config['system']['webgui']['port'] == "") && ($config['system']['webgui']['protocol'] == "https")) {
		$webgui_port = 443;
	}
	if (($post['transparent_proxy'] != 'on') && ($port == $webgui_port)) {
		$input_errors[] = "You can not run Squid on the same port as the pfSense WebGUI";
	}
	unset($port, $webgui_port);
	
	if ($post['transparent_proxy'] == 'on') {
		if (empty($post['transparent_active_interface'])) {
			$input_errors[] = "You must select at least one interface under 'Transparent Proxy Interface(s)' when 'Transparent HTTP Proxy' is enabled.";
		} else {
			// allow transparent proxy only on interfaces where Squid is actually running to keep configuration sane
			$a_ifaces = $post['active_interface'] ?: array();
			$t_ifaces = $post['transparent_active_interface'];
			foreach ($t_ifaces as $t_iface) {
				if (!in_array($t_iface, $a_ifaces)) {
					$err_iface = convert_friendly_interface_to_friendly_descr($t_iface);
					$input_errors[] = "'Transparent Proxy Interface(s)' may only contain interfaces also selected in 'Proxy Interface(s)' above. '{$err_iface}' is not valid.";
					unset($err_iface);
				}
			}
			unset($a_ifaces, $t_iface, $t_ifaces);
		}
	}

	if ($post['ssl_proxy'] == 'on') {
		if ($post['dca'] == 'none') {
			$input_errors[] = "SSL interception cannot be enabled without a CA.";
		}
		if (empty($post['ssl_active_interface'])) {
			$input_errors[] = "You must select at least one interface under 'SSL Intercept Interface(s)' when 'HTTPS/SSL Interception' is enabled.";
		} else {
			// allow HTTPS/SSL Interception only on interfaces where Squid is actually running to keep configuration sane
			$a_ifaces = $post['active_interface'] ?: array();
			$s_ifaces = $post['ssl_active_interface'];
			foreach ($s_ifaces as $s_iface) {
				if (!in_array($s_iface, $a_ifaces)) {
					$err_iface = convert_friendly_interface_to_friendly_descr($s_iface);
					$input_errors[] = "'SSL Intercept Interface(s)' may only contain interfaces also selected in 'Proxy Interface(s)' above. '{$err_iface}' is not valid.";
					unset($err_iface);
				}
			}
			unset($a_ifaces, $s_ifaces, $s_iface);
		}
	}

	foreach (array('defined_ip_proxy_off') as $hosts) {
		foreach (explode(";", $post[$hosts]) as $host) {
			$host = trim($host);
			if (!empty($host) && !is_ipaddr($host) && !is_alias($host) && !is_hostname($host) && !is_subnet($host)) {
				$input_errors[] = "'Bypass proxy for these source IPs' entry '$host' is not a valid IP address, hostname, or alias.";
			}
		}
	}
	unset($host, $hosts);
	
	foreach (array('defined_ip_proxy_off_dest') as $hosts) {
		foreach (explode(";", $post[$hosts]) as $host) {
			$host = trim($host);
			if (!empty($host) && !is_ipaddr($host) && !is_alias($host) && !is_hostname($host) && !is_subnet($host)) {
				$input_errors[] = "'Bypass proxy for these destination IPs' entry '$host' is not a valid IP address, hostname, or alias.";
			}
		}
	}
	unset($host, $hosts);

	if (!empty($post['dns_nameservers'])) {
		$altdns = explode(";", ($post['dns_nameservers']));
		foreach ($altdns as $dnssrv) {
			if (!is_ipaddr($dnssrv)) {
				$input_errors[] = "You must enter a valid IP address in the 'Alternate DNS servers' field.";
				break;
			}
		}
	}
	unset($altdns, $dnssrv);
}

/* Proxy Server: Remote Proxy Settings input validation */
function squid_validate_upstream($post, &$input_errors) {
	if ($post['enabled'] != 'on') {
		return;
	}

	$addr = trim($post['proxyaddr']);
	if (empty($addr)) {
		$input_errors[] = "The 'Proxy hostname' field is required";
	} else {
		if (!is_ipaddr($addr) && !is_domain($addr)) {
			$input_errors[] = "You must enter a valid IP address or host name in the 'Proxy hostname' field.";
		}
	}

	foreach (array('proxyport' => 'TCP port', 'icpport' => 'ICP port') as $field => $name) {
		$port = trim($post[$field]);
		if (empty($port)) {
			$input_errors[] = "The '$name' field is required.";
		} else {
			if (!is_port($port)) {
				$input_errors[] = "The '$name' field must contain a valid port number (1-65535).";
			}
		}
	}
	unset($port);
}

/* Proxy Server: Cache Management input validation */
function squid_validate_cache($post, &$input_errors) {
	/* Manually clear hard disk cache */
	if ($post['clear_cache'] == 'Clear Disk Cache NOW') {
		log_error("[squid] Clear disk cache forced via GUI. Clearing cache now...");
		squid_dash_z("clean");
		return;
	}

	$num_fields = array(
		'harddisk_cache_size' => 'Hard disk cache size',
		'memory_cache_size' => 'Memory cache size',
		'maximum_object_size' => 'Maximum object size',
	);

	foreach ($num_fields as $field => $name) {
		$value = trim($post[$field]);
		if (!is_numericint($value)) {
			$input_errors[] = "You must enter a valid value for '$field'.";
		}
	}
	unset($num_fields);

	$value = trim($post['minimum_object_size']);
	if (!is_numericint($value)) {
		$input_errors[] = "You must enter a valid value for 'Minimum object size'.";
	}
	unset($value);

	if (!empty($post['cache_swap_low'])) {
		$value = trim($post['cache_swap_low']);
		if (!is_numericint($value) || ($value > 100)) {
			$input_errors[] = "You must enter a valid value for 'Low-water-mark'.";
		}
		unset($value);
	}

	if (!empty($post['cache_swap_high'])) {
		$value = trim($post['cache_swap_high']);
		if (!is_numericint($value) || ($value > 100)) {
			$input_errors[] = "You must enter a valid value for 'High-water-mark'.";
		}
		unset($value);
	}

	if ($post['donotcache'] != "") {
		foreach (split("\n", $post['donotcache']) as $host) {
			$host = trim($host);
			if (!is_ipaddr($host) && !is_domain($host)) {
				$input_errors[] = "The host '$host' is not a valid IP or hostname.";
			}
		}
		unset($host);
	}

	if (substr($post['harddisk_cache_location'], -1, 1) == '/') {
		$input_errors[] = 'Log location must not end with a / character.';
	}

	if ($post['harddisk_cache_location']{0} != '/') {
		$input_errors[] = 'Log location must start with a / character.';
	}

	if (strlen($post['harddisk_cache_location']) <= 3) {
		$input_errors[] = "Configured log location directory is not valid.";
	}
}

/* Proxy Server: Access Control input validation */
function squid_validate_nac($post, &$input_errors) {
	$allowed_subnets = explode("\n", $post['allowed_subnets']);
	foreach ($allowed_subnets as $subnet) {
		$subnet = trim($subnet);
		if (!empty($subnet) && !is_subnet($subnet) && $subnet != "all") {
			$input_errors[] = "'Allowed Subnets' must be a valid CIDR range or 'all'. The subnet '$subnet' is not valid.";
		}
	}
	unset($allowed_subnets);

	foreach (array('unrestricted_hosts', 'banned_hosts') as $hosts) {
		if (preg_match_all("@([0-9.]+)(/[0-9.]+|)@", $_POST[$hosts], $matches)) {
			for ($x = 0; $x < count($matches[1]); $x++) {
				if ($matches[2][$x] == "") {
					if (!is_ipaddr($matches[1][$x])) {
						$input_errors[] = "'{$matches[1][$x]}' is not a valid IP address.";
					}
				} else {
					if (!is_subnet($matches[0][$x])) {
						$input_errors[] = "The subnet '{$matches[0][$x]}' is not a valid CIDR range.";
					}
				}
			}
		}
	}

	foreach (array('unrestricted_macs', 'banned_macs') as $macs) {
		foreach (explode("\n", $post[$macs]) as $mac) {
			$mac = trim($mac);
			if (!empty($mac) && !is_macaddr($mac)) {
				$input_errors[] = "'$mac' is not a valid MAC address.";
			}
		}
		unset($mac);
	}

	foreach (explode(",", $post['timelist']) as $time) {
		$time = trim($time);
		if (!empty($time) && !squid_is_timerange($time)) {
			$input_errors[] = "The time range '$time' is not a valid time range.";
		}
	}
	unset($time);

	if (!empty($post['ext_cachemanager'])) {
		$extmgr = explode(";", ($post['ext_cachemanager']));
		foreach ($extmgr as $mgr) {
			if (!is_ipaddr($mgr)) {
				$input_errors[] = "You must enter a valid IP address in the 'External Cache Manager' field'.";
			}
		}
	}
	unset($extmgr);
}

/* Proxy server: Traffic Management input validation */
function squid_validate_traffic($post, &$input_errors) {
	$num_fields = array(
		'max_download_size' => 'Maximum download size',
		'max_upload_size' => 'Maximum upload size',
		'perhost_throttling' => 'Per-host bandwidth throttling',
		'overall_throttling' => 'Overall bandwidth throttling',
	);

	foreach ($num_fields as $field => $name) {
		$value = trim($post[$field]);
		if (!is_numericint($value)) {
			$input_errors[] = "The '$name' field must contain a positive integer.";
		}
	}
	unset($num_fields);

	if (!empty($post['quick_abort_min'])) {
		$value = trim($post['quick_abort_min']);
		if ((!is_numericint($value)) && ($value != "-1")) {
			$input_errors[] = "'Finish when remaining KB' must contain a positive integer or '-1'.";
		}
	}

	if (!empty($post['quick_abort_max'])) {
		$value = trim($post['quick_abort_max']);
		if (!is_numericint($value)) {
			$input_errors[] = "'Abort when remaining KB' must contain a positive integer.";
		}
	}

	if (!empty($post['quick_abort_pct'])) {
		$value = trim($post['quick_abort_pct']);
		if (!is_numericint($value) || ($value > 100)) {
			$input_errors[] = "'Finish when remaining %' must contain valid percentage (1-100).";
		}
	}

	if ($post['throttle_specific'] == "on") {
		$others = trim($post['throttle_others']);
		if ($post['throttle_binaries'] == "" && $post['throttle_cdimages'] == "" && $post['throttle_multimedia'] == "" && $others == "") {
			$input_errors[] = "'Throttle Only Specific Extensions' enabled but no extensions specified. Select some options under 'Squid Transfer Extension Settings' or disable this option.";
		}
		unset($others);
	}

}

/* Proxy Server: Authentication input validation */
function squid_validate_auth($post, &$input_errors) {
	$num_fields = array(
		array('auth_processes', 'Authentication processes', 1),
		array('auth_ttl', 'Authentication TTL', 0),
	);

	foreach ($num_fields as $field) {
		$value = trim($post[$field[0]]);
		if (!empty($value) && (!is_numeric($value) || ($value < $field[2]))) {
			$input_errors[] = "The '{$field[1]}' field must contain a valid number greater than {$field[2]}";
		}
	}
	unset($num_fields);

	$auth_method = $post['auth_method'];
	if (($auth_method != 'none') && ($auth_method != 'local') && ($auth_method != 'cp')) {
		$server = trim($post['auth_server']);
		if (empty($server)) {
			$input_errors[] = "'Authentication server' is required.";
		} elseif (!is_ipaddr($server) && !is_domain($server)) {
			$input_errors[] = "'Authentication server' must contain a valid IP address or domain name.";
		}

		$port = trim($post['auth_server_port']);
		if (!empty($port) && !is_port($port)) {
			$input_errors[] = "'Authentication server port' must contain a valid port number.";
		}

		switch ($auth_method) {
			case 'ldap':
				$user = trim($post['ldap_user']);
				if (empty($user)) {
					$input_errors[] = "'LDAP server user DN' is required.";
				} elseif (!$user) {
					$input_errors[] = "'LDAP server user DN' must be a valid DN.";
				}
				break;
			case 'radius':
				$secret = trim($post['radius_secret']);
				if (empty($secret)) {
					$input_errors[] = "'RADIUS secret' is required.";
				}
				break;
		}

		$no_auth = explode("\n", $post['no_auth_hosts']);
		foreach ($no_auth as $host) {
			$host = trim($host);
			if (!empty($host) && !is_subnet($host)) {
				$input_errors[] = "The host '$host' is not a valid CIDR range";
			}
		}
	}
	unset($auth_method, $port, $server, $secret, $user);
}

/* Proxy Server: General Settings configuration handler */
function squid_resync_general() {
	global $g, $config, $valid_acls;

	if (is_array($config['installedpackages']['squid'])) {
		$settings = $config['installedpackages']['squid']['config'][0];
	} else {
		$settings = array();
	}

	$conf = "# This file is automatically generated by pfSense\n";
	$conf .= "# Do not edit manually !\n\n";
	// Check ssl interception
	if (($settings['ssl_proxy'] == 'on')) {
		squid_check_ca_hashes();
		$srv_cert = lookup_ca($settings["dca"]);
		if ($srv_cert != false) {
			if (base64_decode($srv_cert['prv'])) {
				// check if ssl_db was initilized by Squid
				if (!file_exists(SQUID_SSL_DB . "/serial")) {
					if (is_dir(SQUID_SSL_DB)) {
						mwexec("/bin/rm -rf " . SQUID_SSL_DB);
					}
					mwexec(SQUID_LOCALBASE . "/libexec/squid/ssl_crtd -c -s " . SQUID_SSL_DB);
				}
				// force squid user permission on /var/squid/lib/ssl_db/
				squid_chown_recursive(SQUID_SSL_DB, SQUID_UID, SQUID_GID);
				// cert, key, version, cipher, options, clientca, cafile, capath, crlfile, tls-dh, sslflags, sslcontext
				$crt_pk = SQUID_CONFBASE . "/serverkey.pem";
				$crt_capath = SQUID_LOCALBASE . "/share/certs/";
				$sslproxy_options = "NO_SSLv2,NO_SSLv3";
				/* XXX: Bug #4453, Bug #6592, Feature #6593
				 * http://wiki.squid-cache.org/ConfigExamples/Intercept/SslBumpExplicit#Modern_DH.2FEDH_ciphers_usage
				 */
				if (!empty($settings['sslproxy_compatibility_mode']) && ($settings['sslproxy_compatibility_mode'] == 'modern')) {
					// Modern cipher suites
					$sslproxy_cipher = "EECDH+ECDSA+AESGCM:EECDH+aRSA+AESGCM:EECDH+ECDSA+SHA384:EECDH+ECDSA+SHA256:EECDH+aRSA+SHA384:EECDH+aRSA+SHA256:EECDH+aRSA+RC4:EECDH:EDH+aRSA:!RC4:!aNULL:!eNULL:!LOW:!3DES:!MD5:!EXP:!PSK:!SRP:!DSS";
					$sslproxy_options .= ",NO_TLSv1";
				} else {
					// Use intermediate cipher suites by default to match port versions <0.4.29 behavior on upgrade
					$sslproxy_cipher = "EECDH+ECDSA+AESGCM:EECDH+aRSA+AESGCM:EECDH+ECDSA+SHA384:EECDH+ECDSA+SHA256:EECDH+aRSA+SHA384:EECDH+aRSA+SHA256:EECDH+aRSA+RC4:EECDH:EDH+aRSA:HIGH:!RC4:!aNULL:!eNULL:!LOW:!3DES:!MD5:!EXP:!PSK:!SRP:!DSS";
				}
				if (!empty($settings["dhparams_size"])) {
					if ($settings['dhparams_size'] == '4096') {
						$sslproxy_dhparams = "tls-dh=prime256v1:/etc/dh-parameters.4096";
					} elseif ($settings['dhparams_size'] == '2048') {
						$sslproxy_dhparams = "tls-dh=prime256v1:/etc/dh-parameters.2048";
					} elseif ($settings['dhparams_size'] == '1024') {
						$sslproxy_dhparams = "tls-dh=prime256v1:/etc/dh-parameters.1024";
					}
					$sslproxy_options .= ",SINGLE_DH_USE,SINGLE_ECDH_USE";
				} elseif (file_exists("/etc/dh-parameters.2048")) {
					// Fallback options for defaults on install
					$sslproxy_dhparams = "tls-dh=prime256v1:/etc/dh-parameters.2048";
					$sslproxy_options .= ",SINGLE_DH_USE,SINGLE_ECDH_USE";
				} else {
					// Should never get here
					$sslproxy_dhparams = "";
				}
				file_put_contents($crt_pk, base64_decode($srv_cert['prv']) . base64_decode($srv_cert['crt']));
				$sslcrtd_children = ($settings['sslcrtd_children'] ? $settings['sslcrtd_children'] : 5);
				$ssl_interception .= "ssl-bump generate-host-certificates=on dynamic_cert_mem_cache_size=" . ($sslcrtd_children*2) . "MB cert={$crt_pk} capath={$crt_capath} cipher={$sslproxy_cipher} {$sslproxy_dhparams} options={$sslproxy_options}\n";
				$interception_checks = "sslcrtd_program " . SQUID_LOCALBASE . "/libexec/squid/ssl_crtd -s " . SQUID_SSL_DB . " -M 4MB -b 2048\n";
				$interception_checks .= "sslcrtd_children {$sslcrtd_children}\n";
				$interception_checks .= "sslproxy_capath {$crt_capath}\n";
				$interception_checks .= "sslproxy_options {$sslproxy_options}\n";
				$interception_checks .= "sslproxy_cipher {$sslproxy_cipher}\n";
				if (preg_match("/sslproxy_cert_error/", $settings["interception_checks"])) {
					$interception_checks .= "sslproxy_cert_error allow all\n";
				}
				if (preg_match("/sslproxy_flags/", $settings["interception_checks"])) {
					$interception_checks .= "sslproxy_flags DONT_VERIFY_PEER\n";
				}
				if ($settings["interception_adapt"] != "") {
					foreach (explode(",", $settings["interception_adapt"]) as $adapt) {
						$interception_checks .= "sslproxy_cert_adapt {$adapt} all\n";
					}
				}
			}
		}
	}
	$port = ($settings['proxy_port'] ? $settings['proxy_port'] : 3128);
	$ssl_port = ($settings['ssl_proxy_port'] ? $settings['ssl_proxy_port'] : 3129);

	// Read assigned interfaces
	$real_ifaces = array();

	if ($settings['active_interface']) {
		$proxy_ifaces = explode(",", $settings['active_interface']);
	} else {
		$proxy_ifaces = array();
	}

	if ($settings['transparent_proxy'] == "on") {
		$transparent_ifaces = explode(",", $settings['transparent_active_interface']);
		foreach ($transparent_ifaces as $t_iface) {
			$t_iface_ip = squid_get_real_interface_address($t_iface);
			if ($t_iface_ip[0]) {
				$real_ifaces[] = $t_iface_ip;
			}
		}
	} else {
		$transparent_ifaces = array();
	}

	if ($settings['ssl_proxy'] == "on") {
		$ssl_ifaces = explode(",", $settings['ssl_active_interface']);
		foreach ($ssl_ifaces as $s_iface) {
			$s_iface_ip = squid_get_real_interface_address($s_iface);
			if ($s_iface_ip[0]) {
				$real_ifaces[] = $s_iface_ip;
			}
		}
	} else {
		$ssl_ifaces = array();
	}

	// check all proxy interfaces selected
	foreach ($proxy_ifaces as $iface) {
		$iface_ip = squid_get_real_interface_address($iface);
		if ($iface_ip[0]) {
			// do not add loopback twice when transparent proxy is enabled
			if ($iface_ip[0] == "127.0.0.1" && $settings['transparent_proxy'] == "on") {
				continue;
			} else {
				$real_ifaces[] = $iface_ip;
				if (in_array($iface, $ssl_ifaces)) {
					$conf .= "http_port {$iface_ip[0]}:{$port} {$ssl_interception}\n";
				} else {
					$conf .= "http_port {$iface_ip[0]}:{$port}\n";
				}
			}
		}
	}

	if (($settings['transparent_proxy'] == 'on')) {
		if ($settings['ssl_proxy'] == "on" && count($ssl_ifaces) > 0) {
			$conf .= "http_port 127.0.0.1:{$port} intercept {$ssl_interception}\n";
			$conf .= "https_port 127.0.0.1:{$ssl_port} intercept {$ssl_interception}\n";
		} else {
			$conf .= "http_port 127.0.0.1:{$port} intercept\n";
		}
	}
	$icp_port = ($settings['icp_port'] ? $settings['icp_port'] : 0);
	$dns_v4_first = ($settings['dns_v4_first'] == "on" ? "on" : "off");
	$piddir = "{$g['varrun_path']}/squid";
	$pidfile = "{$piddir}/squid.pid";
	if (!is_dir($piddir)) {
		safe_mkdir($piddir, 0755);
	}
	squid_chown_recursive($piddir, SQUID_UID, 'wheel');
	$language = ($settings['error_language'] ? $settings['error_language'] : 'en');
	$icondir = SQUID_CONFBASE . '/icons';
	$hostname = ($settings['visible_hostname'] ? $settings['visible_hostname'] : 'localhost');
	$email = ($settings['admin_email'] ? $settings['admin_email'] : 'admin@localhost');

	$logdir = ($settings['log_dir'] ? $settings['log_dir'] : '/var/squid/logs');
	if (!is_dir($logdir)) {
		log_error("[squid] Creating Squid log dir '{$logdir}' ...");
		safe_mkdir($logdir, 0755);
	}
	squid_chown_recursive($logdir, SQUID_UID, SQUID_GID);
	$logdir_cache = $logdir . '/cache.log';
	$logdir_access = ($settings['log_enabled'] == 'on' ? $logdir . '/access.log' : '/dev/null');
	$pinger_helper = ($settings['disable_pinger']) == 'on' ? 'off' : 'on';
	$pinger_program = SQUID_LOCALBASE . "/libexec/squid/pinger";

	$squid_uid = SQUID_UID;
	$squid_gid = SQUID_GID;

	$conf .= <<< EOD
icp_port {$icp_port}
dns_v4_first {$dns_v4_first}
pid_filename {$pidfile}
cache_effective_user {$squid_uid}
cache_effective_group {$squid_gid}
error_default_language {$language}
icon_directory {$icondir}
visible_hostname {$hostname}
cache_mgr {$email}
access_log {$logdir_access}
cache_log {$logdir_cache}
cache_store_log none
netdb_filename {$logdir}/netdb.state
pinger_enable {$pinger_helper}
pinger_program {$pinger_program}
{$interception_checks}

EOD;

	// Per squid docs, setting logfile_rotate to 0 is safe and causes a simple close/reopen.
	$rotate = empty($settings['log_rotate']) ? 0 : $settings['log_rotate'];
	$conf .= "logfile_rotate {$rotate}\n";
	$conf .= "debug_options rotate={$rotate}\n";
	squid_install_cron(true);

	$conf .= <<< EOD
shutdown_lifetime 3 seconds

EOD;

	if ($settings['allow_interface'] == 'on') {
		$src = '';
		foreach ($real_ifaces as $iface) {
			list($ip, $mask) = $iface;
			$ip = long2ip(ip2long($ip) & ip2long($mask));
			$mask = 32 - log((ip2long($mask) ^ ip2long('255.255.255.255')) +1, 2);
			if (!preg_match("@$ip/$mask@", $src)) {
				// XXX: Do not add invalid subnets (Bug #4331, Bug #4526)
				if (is_subnet("{$ip}/{$mask}")) {
					$src .= " $ip/$mask";
				} else {
					log_error("[squid] 'Allow Users on Interface' ACL skipped for '{$ip}/{$mask}' since it is not a valid subnet.");
				}
			}
		}
		if (!empty($src)) {
			$conf .= "# Allow local network(s) on interface(s)\n";
			$conf .= "acl localnet src $src\n";
			$valid_acls[] = 'localnet';
		}
	}

	if ($settings['xforward_mode']) {
		$conf .= "forwarded_for {$settings['xforward_mode']}\n";
	} else {
		// only used for first run
		$conf .= "forwarded_for on\n";
	}

	if ($settings['disable_via']) {
		$conf .= "via off\n";
	}

	if ($settings['disable_squidversion']) {
		$conf .= "httpd_suppress_version_string on\n";
	}

	if (!empty($settings['uri_whitespace'])) {
		$conf .= "uri_whitespace {$settings['uri_whitespace']}\n";
	} else {
		// only used for first run
		$conf .= "uri_whitespace strip\n";
	}

	if (!empty($settings['dns_nameservers'])) {
		$altdns = explode(";", ($settings['dns_nameservers']));
		$conf .= "dns_nameservers ";
		foreach ($altdns as $dnssrv) {
			$conf .= $dnssrv . " ";
		}
	}

	return $conf;
}

/* Proxy Server: Cache Management configuration handler */
function squid_resync_cache() {
	global $config, $g;

	if (is_array($config['installedpackages']['squidcache'])) {
		$settings = $config['installedpackages']['squidcache']['config'][0];
	} else {
		$settings = array();
	}

	// apply cache settings
	$cachedir = ($settings['harddisk_cache_location'] ? $settings['harddisk_cache_location'] : '/var/squid/cache');
	$disk_cache_size = ($settings['harddisk_cache_size'] ? $settings['harddisk_cache_size'] : 100);
	$level1 = ($settings['level1_subdirs'] ? $settings['level1_subdirs'] : 16);
	$memory_cache_size = ($settings['memory_cache_size'] ? $settings['memory_cache_size'] : 64);
	$max_objsize = ($settings['maximum_object_size'] ? $settings['maximum_object_size'] . " MB" : "4 MB");
	$min_objsize = ($settings['minimum_object_size'] ? $settings['minimum_object_size'] : 0);
	$max_objsize_in_mem = ($settings['maximum_objsize_in_mem'] ? $settings['maximum_objsize_in_mem'] : 256);
	$cache_policy = ($settings['cache_replacement_policy'] ? $settings['cache_replacement_policy'] : 'heap LFUDA');
	$memory_policy = ($settings['memory_replacement_policy'] ? $settings['memory_replacement_policy'] : 'heap GDSF');
	$offline_mode = ($settings['enable_offline'] == 'on' ? 'on' : 'off');
	$conf = '';
	if (!isset($settings['harddisk_cache_system'])) {
		if ($g['platform'] == "nanobsd") {
			$disk_cache_system = 'null';
		} elseif (!is_array($config['installedpackages']['squidcache']['config'])) {
			log_error("[squid] 'Local Cache' not configured, disk cache will be disabled.");
			log_error("[squid] Please, configure and save 'Local Cache' settings before enabling Squid proxy.");
		} else {
			$disk_cache_system = 'ufs';
		}
	} else {
		$disk_cache_system = $settings['harddisk_cache_system'];
	}
	// 'null' storage type dropped. In-memory cache is always present. Remove all cache_dir options to prevent on-disk caching.
	if ($disk_cache_system != "null") {
		$disk_cache_opts = "cache_dir {$disk_cache_system} {$cachedir} {$disk_cache_size} {$level1} 256";
	}
	// check dynamic content
	if (empty($settings['cache_dynamic_content'])) {
		$conf .= 'acl dynamic urlpath_regex cgi-bin \?' . "\n";
		$conf .= "cache deny dynamic\n";
	} else {
		if ($settings['custom_refresh_patterns'] != "") {
			$conf .= sq_text_area_decode($settings['custom_refresh_patterns']) . "\n";
		}
	}

	$refresh_conf = <<< EOC
# Add any of your own refresh_pattern entries above these.
refresh_pattern ^ftp:    1440  20%  10080
refresh_pattern ^gopher:  1440  0%  1440
refresh_pattern -i (/cgi-bin/|\?) 0  0%  0
refresh_pattern .    0  20%  4320

EOC;

	$conf .= <<< EOD

cache_mem {$memory_cache_size} MB
maximum_object_size_in_memory {$max_objsize_in_mem} KB
memory_replacement_policy {$memory_policy}
cache_replacement_policy {$cache_policy}
minimum_object_size {$min_objsize} KB
maximum_object_size {$max_objsize}
{$disk_cache_opts}
offline_mode {$offline_mode}

EOD;

	if (!empty($settings['cache_swap_low'])) {
		$conf .= "cache_swap_low {$settings['cache_swap_low']}\n";
	}
	if (!empty($settings['cache_swap_high'])) {
		$conf .= "cache_swap_high {$settings['cache_swap_high']}\n";
	}

	$donotcache = sq_text_area_decode($settings['donotcache']);
	if (!empty($donotcache)) {
		file_put_contents(SQUID_ACLDIR . '/donotcache.acl', $donotcache);
		$conf .= 'acl donotcache dstdomain "' . SQUID_ACLDIR . "/donotcache.acl\"\n";
		$conf .= "cache deny donotcache\n";
	} elseif (file_exists(SQUID_ACLDIR . '/donotcache.acl')) {
		unlink(SQUID_ACLDIR . '/donotcache.acl');
	}
	$conf .= "cache allow all\n";

	return $conf.$refresh_conf;
}

/* Proxy Server: Remote Proxy Settings configuration handler */
function squid_resync_upstream() {
	global $config;

	if (!is_array($config['installedpackages']['squidremote']['config'])) {
		$config['installedpackages']['squidremote']['config'] = array();
	}

	$conf = "\n#Remote proxies\n";
	foreach ($config['installedpackages']['squidremote']['config'] as $settings) {
		if ($settings['enable'] == 'on') {
			$conf .= "cache_peer {$settings['proxyaddr']} {$settings['hierarchy']} {$settings['proxyport']} ";
			if ($settings['icpport'] == '7') {
				$conf .= "{$settings['icpport']} {$settings['icpoptions']} {$settings['peermethod']} {$settings['allowmiss']} ";
			} else {
				$conf .= "{$settings['icpport']} ";
			}
			// auth settings
			if (!empty($settings['username']) && !empty($settings['password'])) {
				$conf .= " login={$settings['username']}:{$settings['password']}";
			} else {
				$conf .= "{$settings['authoption']} ";
			}
			// other options settings
			if (!empty($settings['weight'])) {
				$conf .= "weight={$settings['weight']} ";
			}
			if (!empty($settings['basetime'])) {
				$conf .= "basetime={$settings['basetime']} ";
			}
			if (!empty($settings['ttl'])) {
				$conf .= "ttl={$settings['ttl']} ";
			}
			if (!empty($settings['nodelay'])) {
				$conf .= "no-delay";
			}
		}
		$conf .= "\n";
	}
	return $conf;
}

/* Proxy Server: Access Control configuration handler */
function squid_resync_nac() {
	global $config, $valid_acls;

	if (is_array($config['installedpackages']['squidnac'])) {
		$settings = $config['installedpackages']['squidnac']['config'][0];
	} else {
		$settings = array();
	}
	if (is_array($config['installedpackages']['squid'])) {
		$squidsettings = $config['installedpackages']['squid']['config'][0];
	} else {
		$squidsettings = array();
	}

	$webgui_port = $config['system']['webgui']['port'];
	$addtl_ports = $settings['addtl_ports'];
	$addtl_sslports = $settings['addtl_sslports'];
	// do not add (default) proxy ports when using Squid as reverse proxy only
	if (!empty($squidsettings['active_interface'])) {
		$port = $squidsettings['proxy_port'] ? $squidsettings['proxy_port'] : 3128;
		$ssl_port = $squidsettings['ssl_proxy_port'] ? $squidsettings['ssl_proxy_port'] : 3129;
	}
	$conf = <<< EOD

# Setup some default acls
# From 3.2 further configuration cleanups have been done to make things easier and safer. The manager, localhost, and to_localhost ACL definitions are now built-in.
# acl localhost src 127.0.0.1/32
acl allsrc src all
acl safeports port 21 70 80 210 280 443 488 563 591 631 777 901 {$webgui_port} {$port} {$ssl_port} 1025-65535 {$addtl_ports}
acl sslports port 443 563 {$webgui_port} {$addtl_sslports}

# From 3.2 further configuration cleanups have been done to make things easier and safer. The manager, localhost, and to_localhost ACL definitions are now built-in.
#acl manager proto cache_object

acl purge method PURGE
acl connect method CONNECT

# Define protocols used for redirects
acl HTTP proto HTTP
acl HTTPS proto HTTPS

EOD;

	$allowed_subnets = preg_replace("/\s+/"," ", sq_text_area_decode($settings['allowed_subnets']));
	if (!empty($allowed_subnets)) {
		$conf .= "acl allowed_subnets src $allowed_subnets\n";
		$valid_acls[] = 'allowed_subnets';
	}

	$options = array(
		'unrestricted_hosts' => 'src',
		'banned_hosts' => 'src',
		'whitelist' => 'dstdom_regex -i',
		'blacklist' => 'dstdom_regex -i',
		'block_user_agent' => 'browser -i',
		'block_reply_mime_type' => 'rep_mime_type -i',
	);

	foreach ($options as $option => $directive) {
		$contents = sq_text_area_decode($settings[$option]);
		if (!empty($contents)) {
			file_put_contents(SQUID_ACLDIR . "/$option.acl", $contents);
			$conf .= "acl $option $directive \"" . SQUID_ACLDIR . "/$option.acl\"\n";
			$valid_acls[] = $option;
		} elseif (file_exists(SQUID_ACLDIR . "/$option.acl")) {
			unlink(SQUID_ACLDIR . "/$option.acl");
		}
	}

	$conf .= <<< EOD
http_access allow manager localhost

EOD;

	if (is_array($config['installedpackages']['squidcache'])) {
		$settings_ch = $config['installedpackages']['squidcache']['config'][0];
		if (!empty($settings_ch['ext_cachemanager'])) {
			$extmgr = explode(";", ($settings_ch['ext_cachemanager']));
			$conf .= "\n# Allow external cache managers\n";
			foreach ($extmgr as $mgr) {
				$conf .= "acl ext_manager src {$mgr}\n";
			}
			$conf .= "http_access allow manager ext_manager\n";
		}
	}

	$conf .= <<< EOD

http_access deny manager
http_access allow purge localhost
http_access deny purge
http_access deny !safeports
http_access deny CONNECT !sslports

# Always allow localhost connections
# From 3.2 further configuration cleanups have been done to make things easier and safer.
# The manager, localhost, and to_localhost ACL definitions are now built-in.
# http_access allow localhost

EOD;

	return $conf;
}

/* Proxy server: Traffic Management configuration handler */
function squid_resync_traffic() {
	global $config, $valid_acls;

	if (!is_array($valid_acls)) {
		return;
	}
	if (is_array($config['installedpackages']['squidtraffic'])) {
		$settings = $config['installedpackages']['squidtraffic']['config'][0];
	} else {
		$settings = array();
	}

	$conf = '';
	if (!empty($settings['quick_abort_min']) || ($settings['quick_abort_min']) == "0") {
		$conf .= "quick_abort_min {$settings['quick_abort_min']} KB\n";
	}
	if (!empty($settings['quick_abort_max']) || ($settings['quick_abort_max']) == "0") {
		$conf .= "quick_abort_max {$settings['quick_abort_max']} KB\n";
	}
	if (!empty($settings['quick_abort_pct'])) {
		$conf .= "quick_abort_pct {$settings['quick_abort_pct']}\n";
	}

	$up_limit = ($settings['max_upload_size'] ? $settings['max_upload_size'] : 0);
	$down_limit = ($settings['max_download_size'] ? $settings['max_download_size'] : 0);
	$conf .= "request_body_max_size $up_limit KB\n";
	if ($down_limit != 0) {
		$conf .= 'reply_body_max_size ' . $down_limit . " KB allsrc \n";
	}

	// Only apply throttling past 10MB
	// XXX: Should this really be hardcoded?
	$threshold = 10 * 1024 * 1024;
	$overall = $settings['overall_throttling'];
	if (!isset($overall) || ($overall == 0)) {
		$overall = -1;
	} else {
		$overall *= 1024;
	}
	$perhost = $settings['perhost_throttling'];
	if (!isset($perhost) || ($perhost == 0)) {
		$perhost = -1;
	} else {
		$perhost *= 1024;
	}
	$conf .= <<< EOD
delay_pools 1
delay_class 1 2
delay_parameters 1 $overall/$overall $perhost/$perhost
delay_initial_bucket_level 100

EOD;

	if (!empty($settings['unrestricted_hosts'])) {
		foreach (array('unrestricted_hosts') as $item) {
			if (in_array($item, $valid_acls)) {
				$conf .= "# Do not throttle unrestricted hosts\n";
				$conf .= "delay_access 1 deny $item\n";
			}
		}
	}

	if ($settings['throttle_specific'] == 'on') {
		$exts = array();
		$binaries = 'bin,cab,sea,ar,arj,tar,tgz,gz,tbz,bz2,zip,7z,exe,com';
		$cdimages = 'iso,bin,mds,nrg,gho,bwt,b5t,pqi';
		$multimedia = 'aiff?,asf,avi,divx,mov,mp3,mp4,wmv,mpe?g,qt,ra?m';
		foreach (array('throttle_binaries' => $binaries, 'throttle_cdimages' => $cdimages, 'throttle_multimedia' => $multimedia) as $field => $set) {
			if ($settings[$field] == 'on') {
				$exts = array_merge($exts, explode(",", $set));
			}
		}

		foreach (explode(",", $settings['throttle_others']) as $ext) {
			if (!empty($ext)) {
				$exts[] = $ext;
			}
		}

		$contents = '';
		foreach ($exts as $ext) {
			$contents .= "\.$ext\$\n";
		}
		file_put_contents(SQUID_ACLDIR . '/throttle_exts.acl', $contents);

		$conf .= "# Throttle extensions matched in the url\n";
		$conf .= "acl throttle_exts urlpath_regex -i \"" . SQUID_ACLDIR . "/throttle_exts.acl\"\n";
		$conf .= "delay_access 1 allow throttle_exts\n";
		$conf .= "delay_access 1 deny allsrc\n";
	} else {
		unlink_if_exists(SQUID_ACLDIR . '/throttle_exts.acl');
		$conf .= "delay_access 1 allow allsrc\n";
	}

	return $conf;
}

/* Proxy Server: Authentication configuration handler */
function squid_resync_auth() {
	global $config, $valid_acls;
	$write_config = 0;
	if (!is_array($config['installedpackages']['squidauth']['config'])) {
		$config['installedpackages']['squidauth']['config'][] = array('auth_method'=> "none");
		$write_config++;
	}
	$settings = $config['installedpackages']['squidauth']['config'][0];
	if (is_array($config['installedpackages']['squidnac']['config'])) {
		$settingsnac = $config['installedpackages']['squidnac']['config'][0];
	} else {
		$settingsnac = array();
	}

	if (is_array($config['installedpackages']['squid']['config'])) {
		$settingsconfig = $config['installedpackages']['squid']['config'][0];
	} else {
		$settingsconfig = array();
	}

	if ($write_config > 0) {
		write_config();
	}

	$conf = '';

	// SSL interception acl options part 1
	if ($settingsconfig['ssl_proxy'] == "on" && ! empty($settingsnac['whitelist'])) {
		$conf .= "always_direct allow whitelist\n";
		$conf .= "ssl_bump none whitelist\n";
	}

	// Package integration
	if (!empty($settingsconfig['custom_options'])) {
		$co_preg[0] = '/;/';
		$co_rep[0] = "\n";
		$co_preg[1] = "/redirect_program/";
		$co_rep[1] = "url_rewrite_program";
		$co_preg[2] = "/redirector_bypass/";
		$co_rep[2] = "url_rewrite_bypass";
		$conf .= "# Package Integration\n" . preg_replace($co_preg, $co_rep, $settingsconfig['custom_options']) . "\n\n";
	}

	// Custom User Options before authentication acls
	$conf .= "# Custom options before auth\n" . sq_text_area_decode($settingsconfig['custom_options_squid3']) . "\n\n";

	// Deny the banned guys before allowing the good guys
	if (!empty($settingsnac['banned_hosts'])) {
		if (squid_is_valid_acl('banned_hosts')) {
			$conf .= "# These hosts are banned\n";
			$conf .= "http_access deny banned_hosts\n";
		}
	}
	if (!empty($settingsnac['banned_macs'])) {
		if (squid_is_valid_acl('banned_macs')) {
			$conf .= "# These macs are banned\n";
			$conf .= "http_access deny banned_macs\n";
		}
	}

	// Unrestricted hosts take precedence over blacklist
	if (!empty($settingsnac['unrestricted_hosts'])) {
		if (squid_is_valid_acl('unrestricted_hosts') && $settings['unrestricted_auth'] != "on") {
			$conf .= "# These hosts do not have any restrictions\n";
			$conf .= "http_access allow unrestricted_hosts\n";
		}
	}
	if (!empty($settingsnac['unrestricted_macs'])) {
		if (squid_is_valid_acl('unrestricted_macs')) {
			$conf .= "# These hosts do not have any restrictions\n";
			$conf .= "http_access allow unrestricted_macs\n";
		}
	}

	// Whitelist and blacklist also take precedence over other allow rules
	if (!empty($settingsnac['whitelist'])) {
		if (squid_is_valid_acl('whitelist')) {
			$conf .= "# Always allow access to whitelist domains\n";
			$conf .= "http_access allow whitelist\n";
		}
	}
	if (!empty($settingsnac['blacklist'])) {
		if (squid_is_valid_acl('blacklist')) {
			$conf .= "# Block access to blacklist domains\n";
			$conf .= "http_access deny blacklist\n";
		}
	}
	if (!empty($settingsnac['block_user_agent'])) {
		if (squid_is_valid_acl('block_user_agent')) {
			$conf .= "# Block access with user agents and browsers\n";
			$conf .= "http_access deny block_user_agent\n";
		}
	}
	if (!empty($settingsnac['block_reply_mime_type'])) {
		if (squid_is_valid_acl('block_reply_mime_type')) {
			$conf .= "# Block access with mime type in the reply\n";
			$conf .= "http_reply_access deny block_reply_mime_type\n";
		}
	}

	// Include squidguard denied acl log in squid
	if ($settingsconfig['log_sqd']) {
		$conf .= "acl sglog url_regex -i sgr=ACCESSDENIED\n";
	}

	$transparent_proxy = ($settingsconfig['transparent_proxy'] == 'on');
	if ($transparent_proxy) {
		if (preg_match ("/(none|cp)/", $settings['auth_method'])) {
			$auth_method = $settings['auth_method'];
		} else {
			$auth_method = "none";
		}
	} else {
		$auth_method = $settings['auth_method'];
	}
	// Allow the remaining ACLs if no authentication is set
	if ($auth_method == 'none' || $auth_method == 'cp') {
		// Include squidguard denied acl log in squid
		if ($settingsconfig['log_sqd']) {
			$conf .= "http_access deny sglog\n";
		}
	}
	if ($auth_method == 'none') {
		// SSL interception acl options part 2 without authentication
		if ($settingsconfig['ssl_proxy'] == "on") {
			$conf .= "always_direct allow all\n";
			$conf .= "ssl_bump server-first all\n";
		}
		$conf .= "# Setup allowed ACLs\n";
		$allowed = array('allowed_subnets');
		if ($settingsconfig['allow_interface'] == 'on' && !empty($settingsconfig['active_interface'])) {
			$conf .= "# Allow local network(s) on interface(s)\n";
			$allowed[] = "localnet";
		}
		$allowed = array_filter($allowed, 'squid_is_valid_acl');
		foreach ($allowed as $acl) {
			$conf .= "http_access allow $acl\n";
		}
	} else {
		$noauth = implode(' ', explode("\n", sq_text_area_decode($settings['no_auth_hosts'])));
		if (!empty($noauth)) {
			$conf .= "acl noauth src $noauth\n";
			$valid_acls[] = 'noauth';
		}

		// Set up the external authentication programs
		$auth_ttl = ($settings['auth_ttl'] ? $settings['auth_ttl'] : 5);
		$processes = ($settings['auth_processes'] ? $settings['auth_processes'] : 5);
		$prompt = ($settings['auth_prompt'] ? $settings['auth_prompt'] : 'Please enter your credentials to access the proxy');
		switch ($auth_method) {
			case 'local':
				$conf .= 'auth_param basic program ' . SQUID_LOCALBASE . '/libexec/squid/basic_ncsa_auth ' . SQUID_PASSWD . "\n";
				break;
			case 'ldap':
				$port = ((isset($settings['auth_server_port']) && !empty($settings['auth_server_port'])) ? ":{$settings['auth_server_port']}" : '');
				$password = (isset($settings['ldap_pass']) ? "-w {$settings['ldap_pass']}" : '');
				$conf .= "auth_param basic program " . SQUID_LOCALBASE . "/libexec/squid/basic_ldap_auth -v {$settings['ldap_version']} -b {$settings['ldap_basedomain']} -D {$settings['ldap_user']} $password -f \"{$settings['ldap_filter']}\" -u {$settings['ldap_userattribute']} -P {$settings['auth_server']}$port\n";
				break;
			case 'radius':
				$port = ((isset($settings['auth_server_port']) && !empty($settings['auth_server_port'])) ? "-p {$settings['auth_server_port']}" : '');
				$conf .= "auth_param basic program ". SQUID_LOCALBASE . "/libexec/squid/basic_radius_auth -w {$settings['radius_secret']} -h {$settings['auth_server']} $port\n";
				break;
			case 'cp':
				$helpers_num = "children-startup=" . (int) ($processes/2) . " children-max={$processes} children-idle=" . (int) ($processes/3);
				$conf .= "external_acl_type check_cp {$helpers_num} ttl={$auth_ttl} %SRC " . SQUID_BASE . "/bin/check_ip.php\n";
				$conf .= "acl password external check_cp\n";
				break;
		}
		if ($auth_method != 'cp') {
		$conf .= <<< EOD
auth_param basic children $processes
auth_param basic realm $prompt
auth_param basic credentialsttl $auth_ttl minutes
acl password proxy_auth REQUIRED

EOD;
		}
		// Custom User Options after authentication definition
		$conf .= "# Custom options after auth\n" . sq_text_area_decode($settingsconfig['custom_options2_squid3']) . "\n\n";

		// SSL interception acl options part 2
		if ($settingsconfig['ssl_proxy'] == "on") {
			$conf .= "always_direct allow all\n";
			$conf .= "ssl_bump server-first all\n";
		}
		// Onto the ACLs
		$password = array('localnet', 'allowed_subnets');
		$passwordless = array('unrestricted_hosts');
		if ($settings['unrestricted_auth'] == 'on') {
			// Even the unrestricted hosts should authenticate
			$password = array_merge($password, $passwordless);
			$passwordless = array();
		}
		$passwordless[] = 'noauth';
		$password = array_filter($password, 'squid_is_valid_acl');
		$passwordless = array_filter($passwordless, 'squid_is_valid_acl');

		// Allow the ACLs that don't need to authenticate
		foreach ($passwordless as $acl) {
			$conf .= "http_access allow $acl\n";
		}

		// Include squidguard denied acl log in squid
		if ($settingsconfig['log_sqd']) {
			$conf .= "http_access deny password sglog\n";
		}

		// Allow the other ACLs as long as they authenticate
		foreach ($password as $acl) {
			$conf .= "http_access allow password $acl\n";
		}
	}

	$conf .= "# Default block all to be sure\n";
	$conf .= "http_access deny allsrc\n";

	return $conf;
}

/* Proxy server: Local users configuration handler */
function squid_resync_users() {
	global $config;

	$users = $config['installedpackages']['squidusers']['config'];
	$contents = '';
	if (is_array($users)) {
		foreach ($users as $user) {
			/* Store password with SHA512 and a random salt, which allows for longer passwords. */
			$contents .= $user['username'] . ':' . crypt($user['password'], '$6$rounds=5000$' . bin2hex(openssl_random_pseudo_bytes(16)) . '$') . "\n";
		}
	}
	file_put_contents(SQUID_PASSWD, $contents);
	chown(SQUID_PASSWD, SQUID_UID);
	chmod(SQUID_PASSWD, 0600);
}

/* Wrapper function to sync whole Squid configuration */
function squid_resync($via_rpc = "no") {
	global $config;

	// detect boot process
	if (is_array($_POST)) {
		if (!platform_booting()) {
			unset($boot_process);
		} else {
			$boot_process = "on";
		}
	}

	log_error("[squid] - squid_resync function call pr:" . is_process_running('squid') . " bp:" . isset($boot_process) . " rpc:" . $via_rpc);

	if (is_process_running('squid') && isset($boot_process) && $via_rpc == "no") {
		return;
	}

	conf_mount_rw();

	// Fix user/group entry permissions
	squid_fixup_user();

	foreach (array(SQUID_CONFBASE, SQUID_ACLDIR, SQUID_SSL_DB) as $dir) {
		safe_mkdir($dir, 0755);
		squid_chown_recursive($dir, SQUID_UID, SQUID_GID);
	}
	$conf = squid_resync_general() . "\n";
	$conf .= squid_resync_cache() . "\n";
	$conf .= squid_resync_upstream() . "\n";
	$conf .= squid_resync_nac() . "\n";
	$conf .= squid_resync_traffic() . "\n";
	$conf .= squid_resync_reverse() . "\n";
	$conf .= squid_resync_auth() . "\n";
	$conf .= squid_resync_antivirus();
	squid_resync_users();
	squid_write_rcfile();

	if (!isset($boot_process) || $via_rpc == "yes") {
		squid_sync_on_changes();
	}

	// write config file
	file_put_contents(SQUID_CONFFILE, $conf);

	/* make sure pinger is executable and suid root */
	// XXX: Bug #5114
	if (file_exists(SQUID_LOCALBASE . "/libexec/squid/pinger")) {
		chgrp(SQUID_LOCALBASE . "/libexec/squid/pinger", SQUID_GID);
	}

	// check cache dir and create if necessary
	squid_dash_z();

	// restart Squid if enabled and reconfigure filter
	squid_restart_services();
	filter_configure();
	conf_mount_ro();
}

/*
 * Squid firewall rules configuration
 */

function squid_generate_rules($type) {
	global $config;

	$squid_conf = $config['installedpackages']['squid']['config'][0];

	// do not install any firewall rules if Squid is disabled or used as reverse proxy only
	if (!squid_enabled()) {
		log_error("[squid] Installed but disabled. Not installing '{$type}' rules.");
		return;
	} elseif (empty($squid_conf['active_interface'])) {
		log_error("[squid] Configured as reverse proxy only. Not installing '{$type}' rules.");
		return;
	}

	// normal squid rule check
	if (($squid_conf['transparent_proxy'] != 'on') || ($squid_conf['allow_interface'] != 'on')) {
		return;
	}

	if (!is_service_running('squid')) {
		log_error("[squid] Installed but not started. Not installing '{$type}' rules.");
		return;
	}
	// Read assigned interfaces
	$proxy_ifaces = explode(",", $squid_conf['active_interface']);
	$proxy_ifaces = array_map('convert_friendly_interface_to_real_interface_name', $proxy_ifaces);
	if ($squid_conf['transparent_proxy'] == "on") {
		$transparent_ifaces = explode(",", $squid_conf['transparent_active_interface']);
		$transparent_ifaces = array_map('convert_friendly_interface_to_real_interface_name', $transparent_ifaces);
	} else {
		$transparent_ifaces = array();
	}
	if ($squid_conf['ssl_proxy'] == "on") {
		$ssl_ifaces = explode(",", $squid_conf['ssl_active_interface']);
		$ssl_ifaces = array_map('convert_friendly_interface_to_real_interface_name', $ssl_ifaces);
	} else {
		$ssl_ifaces = array();
	}

	$port = ($squid_conf['proxy_port'] ? $squid_conf['proxy_port'] : 3128);
	$ssl_port = ($squid_conf['ssl_proxy_port'] ? $squid_conf['ssl_proxy_port'] : 3129);

	$fw_aliases = filter_generate_aliases();
	if (strstr($fw_aliases, "pptp =")) {
		$PPTP_ALIAS = "\$pptp";
	} else {
		$PPTP_ALIAS = "\$PPTP";
	}
	if (strstr($fw_aliases, "PPPoE =")) {
		$PPPOE_ALIAS = "\$PPPoE";
	} else {
		$PPPOE_ALIAS = "\$pppoe";
	}

	// define ports based on transparent options and ssl filtering
	$pf_rule_port = ($squid_conf['ssl_proxy'] == "on" ? "{80,443}" : "80");
	switch($type) {
		case 'nat':
			$rules .= "\n# Setup Squid proxy redirect\n";
			if ($squid_conf['private_subnet_proxy_off'] == 'on') {
				foreach ($transparent_ifaces as $iface) {
					$pf_transparent_rule_port = (in_array($iface, $ssl_ifaces) ? "{80,443}" : "80");
					$rules .= "no rdr on $iface proto tcp from any to { 192.168.0.0/16, 172.16.0.0/12, 10.0.0.0/8 } port {$pf_transparent_rule_port}\n";
				}
				/* Handle PPPOE case */
				if (($config['pppoe']['mode'] == "server" && $config['pppoe']['localip']) || (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) {
					$rules .= "no rdr on $PPPOE_ALIAS proto tcp from any to { 192.168.0.0/16, 172.16.0.0/12, 10.0.0.0/8 } port {$pf_rule_port}\n";
				}
			}
			if (!empty($squid_conf['defined_ip_proxy_off'])) {
				$defined_ip_proxy_off = explode(";", $squid_conf['defined_ip_proxy_off']);
				$exempt_ip = "";
				foreach ($defined_ip_proxy_off as $ip_proxy_off) {
					if (!empty($ip_proxy_off)) {
						$ip_proxy_off = trim($ip_proxy_off);
						if (is_alias($ip_proxy_off)) {
							$ip_proxy_off = '$' . $ip_proxy_off;
						}
						$exempt_ip .= ", $ip_proxy_off";
					}
				}
				$exempt_ip = substr($exempt_ip, 2);
				foreach ($transparent_ifaces as $iface) {
					$pf_transparent_rule_port = (in_array($iface, $ssl_ifaces) ? "{80,443}" : "80");
					$rules .= "no rdr on $iface proto tcp from { $exempt_ip } to any port {$pf_transparent_rule_port}\n";
				}
				/* Handle PPPOE case */
				if (($config['pppoe']['mode'] == "server" && $config['pppoe']['localip']) || (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) {
					$rules .= "no rdr on $PPPOE_ALIAS proto tcp from { $exempt_ip } to any port {$pf_rule_port}\n";
				}
			}
			if (!empty($squid_conf['defined_ip_proxy_off_dest'])) {
				$defined_ip_proxy_off_dest = explode(";", $squid_conf['defined_ip_proxy_off_dest']);
				$exempt_dest = "";
				foreach ($defined_ip_proxy_off_dest as $ip_proxy_off_dest) {
					if (!empty($ip_proxy_off_dest)) {
						$ip_proxy_off_dest = trim($ip_proxy_off_dest);
						if (is_alias($ip_proxy_off_dest)) {
							$ip_proxy_off_dest = '$' . $ip_proxy_off_dest;
						}
						$exempt_dest .= ", $ip_proxy_off_dest";
					}
				}
				$exempt_dest = substr($exempt_dest, 2);
				foreach ($transparent_ifaces as $iface) {
					$pf_transparent_rule_port = (in_array($iface, $ssl_ifaces) ? "{80,443}" : "80");
					$rules .= "no rdr on $iface proto tcp from any to { $exempt_dest } port {$pf_transparent_rule_port}\n";
				}
				/* Handle PPPOE case */
				if (($config['pppoe']['mode'] == "server" && $config['pppoe']['localip']) || (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) {
					$rules .= "no rdr on $PPPOE_ALIAS proto tcp from any to { $exempt_dest } port {$pf_rule_port}\n";
				}
			}
			foreach ($transparent_ifaces as $t_iface) {
				$pf_transparent_rule_port = (in_array($t_iface, $ssl_ifaces) ? "{80,443}" : "80");
				$rules .= "rdr on $t_iface proto tcp from any to !($t_iface) port 80 -> 127.0.0.1 port {$port}\n";
				if (in_array($t_iface, $ssl_ifaces)) {
					$rules .= "rdr on $t_iface proto tcp from any to !($t_iface) port 443 -> 127.0.0.1 port {$ssl_port}\n";
				}
			}
			/* Handle PPPOE case */
			if (($config['pppoe']['mode'] == "server" && $config['pppoe']['localip']) || (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) {
				$rules .= "rdr on $PPPOE_ALIAS proto tcp from any to !127.0.0.1 port {$pf_rule_port} -> 127.0.0.1 port {$port}\n";
			}
			$rules .= "\n";
			break;
		case 'filter':
		case 'rule':
			foreach ($transparent_ifaces as $iface) {
				$pf_transparent_rule_port = (in_array($iface, $ssl_ifaces) ? "{80,443,{$port},{$ssl_port}}" : "{80,{$port}}");
				$rules .= "# Setup squid pass rules for proxy\n";
				$rules .= "pass in quick on $iface proto tcp from any to !($iface) port {$pf_transparent_rule_port} flags S/SA keep state\n";
				// $rules .= "pass in quick on $iface proto tcp from any to !($iface) port {$port} flags S/SA keep state\n";
				$rules .= "\n";
			}
			if ($config['pppoe']['mode'] == "server" && $config['pppoe']['localip']) {
				$rules .= "pass in quick on $PPPOE_ALIAS proto tcp from any to !127.0.0.1 port {$port} flags S/SA keep state\n";
			}
			break;
		default:
			break;
	}

	return $rules;
}

/*
 * Squid XMLRPC sync
 */

/* XMLRPC sync configuration */
function squid_sync_on_changes() {
	global $config;

	if (is_array($config['installedpackages']['squidsync']['config'])) {
		$squid_sync = $config['installedpackages']['squidsync']['config'][0];
		$synconchanges = $squid_sync['synconchanges'];
		$synctimeout = $squid_sync['synctimeout'] ?: '250';
		switch ($synconchanges) {
			case "manual":
				if (is_array($squid_sync['row'])) {
					$rs = $squid_sync['row'];
				} else {
					log_error("[squid] XMLRPC sync is enabled but there are no hosts configured as replication targets.");
					return;
				}
				break;
			case "auto":
				if (is_array($config['hasync'])) {
					$system_carp = $config['hasync'];
					$rs[0]['ipaddress'] = $system_carp['synchronizetoip'];
					$rs[0]['username'] = $system_carp['username'];
					$rs[0]['password'] = $system_carp['password'];
					$rs[0]['syncdestinenable'] = FALSE;

					// XMLRPC sync is currently only supported over connections using the same protocol and port as this system
					if ($config['system']['webgui']['protocol'] == "http") {
						$rs[0]['syncprotocol'] = "http";
						$rs[0]['syncport'] = $config['system']['webgui']['port'] ?: '80';
					} else {
						$rs[0]['syncprotocol'] = "https";
						$rs[0]['syncport'] = $config['system']['webgui']['port'] ?: '443';
					}
					if ($system_carp['synchronizetoip'] == "") {
						log_error("[squid] XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets.");
						return;
					} else {
						$rs[0]['syncdestinenable'] = TRUE;
					}
				} else {
					log_error("[squid] XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets.");
					return;
				}
				break;
			default:
				return;
				break;
		}
		if (is_array($rs)) {
			log_error("[squid] XMLRPC sync is starting.");
			foreach ($rs as $sh) {
				// Only sync enabled replication targets
				if ($sh['syncdestinenable']) {
					$sync_to_ip = $sh['ipaddress'];
					$port = $sh['syncport'];
					$username = $sh['username'] ?: 'admin';
					$password = $sh['password'];
					$protocol = $sh['syncprotocol'];

					$error = '';
					$valid = TRUE;

					if ($password == "") {
						$error = "Password parameter is empty. ";
						$valid = FALSE;
					}
					if (!is_ipaddr($sync_to_ip) && !is_hostname($sync_to_ip) && !is_domain($sync_to_ip)) {
						$error .= "Misconfigured Replication Target IP Address or Hostname. ";
						$valid = FALSE;
					}
					if (!is_port($port)) {
						$error .= "Misconfigured Replication Target Port. ";
						$valid = FALSE;
					}
					if ($valid) {
						squid_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout);
					} else {
						log_error("[squid] XMLRPC sync with '{$sync_to_ip}' aborted due to the following error(s): {$error}");
					}
				}
			}
			log_error("[squid] XMLRPC sync completed.");
		}
 	}
}

if(!function_exists('pf_version')) {
	function pf_version() {
		return substr(trim(file_get_contents("/etc/version")), 0, 3);
	}
}

/* Perform the actual XMLRPC sync */
function squid_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout) {
	global $config, $g;

	if ($username == "" || $password == "" || $sync_to_ip == "" || $port == "" || $protocol == "") {
		log_error("[squid] A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync");
		return;
	}

	/* XML will hold the sections to sync */
	$xml = array();
	$xml['squid'] = $config['installedpackages']['squid'];
	$xml['squidremote'] = $config['installedpackages']['squidremote'];
	$xml['squidcache'] = $config['installedpackages']['squidcache'];
	$xml['squidantivirus'] = $config['installedpackages']['squidantivirus'];
	$xml['squidnac'] = $config['installedpackages']['squidnac'];
	$xml['squidtraffic'] = $config['installedpackages']['squidtraffic'];
	$xml['squidreversegeneral'] = $config['installedpackages']['squidreversegeneral'];
	$xml['squidreversepeer'] = $config['installedpackages']['squidreversepeer'];
	$xml['squidreverseuri'] = $config['installedpackages']['squidreverseuri'];
	$xml['squidauth'] = $config['installedpackages']['squidauth'];
	$xml['squidusers'] = $config['installedpackages']['squidusers'];

	$execcmd = "require_once('/usr/local/pkg/squid.inc');\n";
	$execcmd .= "squid_resync('yes');";

	if (pf_version() >= "2.4") {
		// xmlrpc cannot encode NULL objects/arrays..
		foreach($xml as $xmlkey => $xmlvalue) {
			if (gettype($xmlvalue) == "NULL") {
				$xml[$xmlkey] = array();
			}
		}
		$synctimeout = intval($synctimeout);
		$rpc_client = new pfsense_xmlrpc_client();
		$rpc_client->setConnectionData($sync_to_ip, $port, $username, $password, $protocol);
		$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section', $xml, $synctimeout);
		$resp = $rpc_client->xmlrpc_exec_php($execcmd, $synctimeout);
	} else {
		// pfSense before 2.4
		require_once('xmlrpc.inc');

		// Take care of IPv6 literal address
		if (is_ipaddrv6($sync_to_ip)) {
			$sync_to_ip = "[{$sync_to_ip}]";
		}

		$url = "{$protocol}://{$sync_to_ip}";

		/* Assemble XMLRPC payload */
		$params = array(XML_RPC_encode($password), XML_RPC_encode($xml));

		/* Set a few variables needed for sync */
		$method = 'pfsense.merge_installedpackages_section_xmlrpc';
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		if ($g['debug']) {
			$cli->setDebug(1);
		}
		/* Send our XMLRPC message and timeout after defined sync timeout value*/
		$resp = $cli->send($msg, $synctimeout);
		if (!$resp) {
			$error = "A communication error occurred while attempting XMLRPC sync with {$url}:{$port}.";
			log_error("[squid] {$error}");
			file_notice("sync_settings", $error, "Squid Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$cli->setDebug(1);
			$resp = $cli->send($msg, $synctimeout);
			$error = "An error code was received while attempting XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error("[squid] {$error}");
			file_notice("sync_settings", $error, "Squid Settings Sync", "");
		} else {
			log_error("[squid] XMLRPC sync successfully completed with {$url}:{$port}.");
		}

		/* Tell Squid to reload our settings on the destination sync host. */
		$method = 'pfsense.exec_php';
		$execcmd = "require_once('/usr/local/pkg/squid.inc');\n";
		$execcmd .= "squid_resync('yes');";
		/* Assemble XMLRPC payload */
		$params = array(XML_RPC_encode($password), XML_RPC_encode($execcmd));

		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		$resp = $cli->send($msg, $synctimeout);
		if (!$resp) {
			$error = "A communication error occurred while attempting XMLRPC sync with {$url}:{$port} (pfsense.exec_php).";
			log_error("[squid] {$error}");
			file_notice("sync_settings", $error, "Squid Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$cli->setDebug(1);
			$resp = $cli->send($msg, $synctimeout);
			$error = "An error code was received while attempting XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error("[squid] {$error}");
			file_notice("sync_settings", $error, "Squid Settings Sync", "");
		} else {
			log_error("[squid] XMLRPC reload data success with {$url}:{$port} (pfsense.exec_php).");
		}
	}
}
