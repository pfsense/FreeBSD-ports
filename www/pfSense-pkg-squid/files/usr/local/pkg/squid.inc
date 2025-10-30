<?php
/*
 * squid.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2016 Marcello Coutinho
 * Copyright (c) 2006 Fernando Lemos
 * Copyright (c) 2012 Martin Fuchs
 * Copyright (c) 2012-2014 Marcello Coutinho
 * Copyright (c) 2013 Gekkenhuis
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

require_once('config.inc');
require_once('config.lib.inc');
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
define('LOG_PREFIX_PKG_SQUID', 'squid');

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
function sq_text_area_decode($text, $idn=false) {
	$contents = preg_replace('/\r\n/', "\n", base64_decode($text));
	if (function_exists('idn_to_ascii') && $idn) {
		$contents_idn = array();
		foreach (explode("\n", $contents) as $entry) {
			if (is_domain($entry)) {
				$contents_idn[] = idn_to_ascii($entry);
			} else {
				$contents_idn[] = $entry;
			}
		}
		return implode("\n", $contents_idn);
	} else {
		return $contents;
	}
}

/* Get interface IP and netmask for Squid interfaces */
function squid_get_real_interface_address($iface, $proto = 'inet') {
	if (!function_exists("get_interface_ip")) {
		require_once("interfaces.inc");
	}

	$inf = array();
	switch ($proto) {
		case 'inet':
			$inf[] = array(get_interface_ip($iface), gen_subnet_mask(get_interface_subnet($iface)));
			break;
		case 'inet6':
			$inf[] = array(get_interface_ipv6($iface), get_interface_subnetv6($iface));
			break;
		case 'any':
			$inf[] = array(get_interface_ip($iface), gen_subnet_mask(get_interface_subnet($iface)));
			$inf[] = array(get_interface_ipv6($iface), get_interface_subnetv6($iface));
			break;
		default:
			break;
	}

	return $inf;
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
		logger(LOG_ERR, localize_text("Attempted to chown an invalid directory: '%s'", $dir), LOG_PREFIX_PKG_SQUID);
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
		logger(LOG_ERR, localize_text("%s call failed; permissions not set for directory: '%s'", 'squid_chown_recursive()', $dir), LOG_PREFIX_PKG_SQUID);
	}
}

/* Check whether Squid is enabled */
function squid_enabled() {
	global $proxy_enabled;
	$proxy_enabled = false;

	if (is_array(config_get_path('installedpackages/squid/config'))) {
		// check whether Squid is enabled ...
		if (config_get_path('installedpackages/squid/config/0/enable_squid') == "on") {
			// ... and has at least one interface configured ...
			if (!empty(config_get_path('installedpackages/squid/config/0/active_interface'))) {
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
	$cert_arr = array();
	$cert_arr[] = array('refid' => 'none', 'descr' => 'none');
	foreach (config_get_path('cert', []) as $cert) {
		$cert_arr[] = array('refid' => $cert['refid'], 'descr' => $cert['descr']);
	}
	return $cert_arr;
}

/* Handle extra CA certificates, see https://redmine.pfsense.org/issues/10605 */
function squid_gen_extra_ca() {
	$capath = SQUID_LOCALBASE . '/share/certs';

	unlink_if_exists("{$capath}/*.0");

	/* add Trust Store CAs, see https://redmine.pfsense.org/issues/12738 */
	foreach (config_get_path('ca', []) as $ca) {
		/* If the entry is invalid or is not trusted, skip it. */
		if (!is_array($ca) ||
		    empty($ca['crt']) ||
		    !isset($ca['trust']) ||
		    ($ca['trust'] != "enabled")) {
			continue;
		}
		ca_setup_capath($ca, $capath);
	}

	if (!empty(config_get_path('installedpackages/squid/config/0/extraca'))) {
		$extraca = config_get_path('installedpackages/squid/config/0/extraca');
		if ($extraca != 'none') {
			$param = array('caref' => $extraca);
			$cas = ca_chain_array($param);
			foreach ($cas as $ca) {
				ca_setup_capath($ca, $capath);
			}
		}
	}
}

/*
 * Squid cache setup
 */

 /* Create Squid disk cache directories */
function squid_dash_z($cache_action = 'none') {
	// We need cache configured after initial package install
	if (!is_array(config_get_path('installedpackages/squidcache/config'))) {
		logger(LOG_INFO, localize_text("'Local Cache' not configured, disk cache will be disabled."), LOG_PREFIX_PKG_SQUID);
		logger(LOG_INFO, localize_text("Please, configure and save 'Local Cache' settings before enabling Squid proxy."), LOG_PREFIX_PKG_SQUID);
		return;
	}

	if (is_array(config_get_path('installedpackages/squidcache'))) {
		$cachesettings = config_get_path('installedpackages/squidcache/config/0');
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
				logger(LOG_INFO, localize_text("Deleting cache dir '%s' since 'Hard Disk Cache System' is set to null...", $cachedir), LOG_PREFIX_PKG_SQUID);
				// cannot nuke disk cache while Squid is running
				squid_stop_monitor();
				if (is_service_running('squid')) {
					stop_service("squid");
				}
				@rename($cachedir, "{$cachedir}.old");
				mwexec_bg("/bin/rm -rf {$cachedir}.old");
				squid_restart_services();
			} else {
				logger(LOG_INFO, localize_text("'Hard Disk Cache System' is set to null."), LOG_PREFIX_PKG_SQUID);
				logger(LOG_INFO, localize_text("Will NOT delete cache dir '%s' since it is not located under %s. Delete manually if required.", $cachedir, '/var/squid'), LOG_PREFIX_PKG_SQUID);
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
				logger(LOG_INFO, localize_text("Will NOT delete cache dir '%s' since it is not located under %s. Delete manually if required.", $cachedir, '/var/squid'), LOG_PREFIX_PKG_SQUID);
			}
		}
		squid_create_cachedir();
		squid_restart_services();
	}
}

/* Helper function for squid_dash_z() */
function squid_create_cachedir() {
	if (is_array(config_get_path('installedpackages/squidcache'))) {
		$cachesettings = config_get_path('installedpackages/squidcache/config/0');
	} else {
		$cachesettings = array();
	}
	$cachedir = ($cachesettings['harddisk_cache_location'] ? $cachesettings['harddisk_cache_location'] : '/var/squid/cache');

	if (!is_dir($cachedir)) {
		logger(LOG_INFO, localize_text("Creating cache dir '%s' ...", $cachedir), LOG_PREFIX_PKG_SQUID);
		safe_mkdir($cachedir, 0755);
		@chown($cachedir, SQUID_UID);
		@chgrp($cachedir, SQUID_GID);
	}

	if (!is_dir($cachedir . '/00')) {
		logger(LOG_INFO, localize_text("Creating Squid cache subdirs in %s ...", $cachedir), LOG_PREFIX_PKG_SQUID);
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
	if (is_platform_booting()) {
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
		logger(LOG_INFO, localize_text("Adding cronjobs ..."), LOG_PREFIX_PKG_SQUID);
		install_cron_job("{$cron_cmd}", $should_install, "0", "0", "*", "*", "*", "root");
		install_cron_job("{$swapstate_cmd}", $should_install, "15", "0", "*", "*", "*", "root");
	} else {
		logger(LOG_INFO, localize_text("Removing cronjobs ..."), LOG_PREFIX_PKG_SQUID);
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
SQUID_ENABLED=$(/usr/local/sbin/read_xml_tag.sh string installedpackages/squid/config/enable_squid)
if [ "\${SQUID_ENABLED}" != "on" ]; then
	exit 0
elif [ -z "`/bin/ps auxw | /usr/bin/grep "[s]quid " | /usr/bin/awk '{print $2}'`" ]; then
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

	write_rcfile($rc);
}

/* Start sqp_monitor.sh watchdog script */
function squid_start_monitor() {
	if (squid_enabled()) {
		if (!exec("/bin/ps auxw | /usr/bin/grep '[s]qpmon'")) {
			logger(LOG_INFO, localize_text("Starting a proxy monitor script"), LOG_PREFIX_PKG_SQUID);
			mwexec_bg("/usr/local/etc/rc.d/sqp_monitor.sh start");
		}
		sleep(1);
	} else {
		logger(LOG_INFO, localize_text("Squid is disabled. Not starting a proxy monitor script"), LOG_PREFIX_PKG_SQUID);
	}
}

/* Stop sqp_monitor.sh watchdog script */
function squid_stop_monitor() {
	/* kill any running proxy alarm scripts */
	if (exec("/bin/ps auxw | /usr/bin/grep '[s]qpmon'")) {
		logger(LOG_INFO, localize_text("Stopping any running proxy monitors"), LOG_PREFIX_PKG_SQUID);
		mwexec("/usr/local/etc/rc.d/sqp_monitor.sh stop");
	}
	sleep(1);
}

/* Start and/or stop services according to Squid configuration */
function squid_restart_services() {
	// do not (re)start squid services on boot
	if (is_platform_booting()) {
		return;
	}

	if (is_array(config_get_path('installedpackages/squid/config'))) {
		$squid_config = config_get_path('installedpackages/squid/config/0');
	} else {
		return null;
	}

	if (squid_enabled()) {
		/* kill any running proxy alarm scripts */
		squid_stop_monitor();

		/* Start/Restart Squid, if a CARP VIP is set, check its status and act
		* appropriately. */
		if (isset($squid_config['carpstatusvid']) && $squid_config['carpstatusvid'] != "none") {
			$status = get_carp_interface_status($squid_config['carpstatusvid']);
			switch (strtoupper($status)) {
				// Stop the service if the VIP is in BACKUP or INIT state.
				case "BACKUP":
				case "INIT":
					if (is_service_running('squid')) {
						logger(LOG_INFO, localize_text("Stopping service... (CARP BACKUP/INIT)"), LOG_PREFIX_PKG_SQUID);
						stop_service("squid");
					} else {
						logger(LOG_INFO, localize_text("Do not start service... (CARP BACKUP/INIT)"), LOG_PREFIX_PKG_SQUID);
					}
					return;
					break;
				case "MASTER":
					/* Stopping the service on other node may take some time 
					(shutdown_lifetime 3 seconds) */
					sleep(4);
					break;
				default:
					break;
			}
		}
		if (!is_service_running('squid')) {
			logger(LOG_INFO, localize_text("Starting service..."), LOG_PREFIX_PKG_SQUID);
			mwexec(SQUID_BASE . "/sbin/squid -f " . SQUID_CONFFILE);
		} else {
			logger(LOG_INFO, localize_text("Reloading for configuration sync..."), LOG_PREFIX_PKG_SQUID);
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
			logger(LOG_INFO, localize_text("Stopping service..."), LOG_PREFIX_PKG_SQUID);
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
	/* Set storage system for ramdisk */

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
	global $keep;

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
	if (is_array(config_get_path('installedpackages/squidcache'))) {
		$cachesettings = config_get_path('installedpackages/squidcache/config/0');
	} else {
		$cachesettings = array();
	}
	$cachedir = ($cachesettings['harddisk_cache_location'] ? $cachesettings['harddisk_cache_location'] : '/var/squid/cache');
	if (is_array(config_get_path('installedpackages/squid'))) {
		$squidsettings = config_get_path('installedpackages/squid/config/0');
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
				logger(LOG_INFO, localize_text("Will NOT delete Squid cache dir '%s' since it is not located under %s. Delete manually if required.", $cachedir, '/var/squid'), LOG_PREFIX_PKG_SQUID);
			}
		}
		if (is_dir("{$logdir}")) {
			if (substr($logdir, 0, 11) === "/var/squid/") {
				mwexec("/bin/rm -rf {$logdir}");
			} else {
				logger(LOG_INFO, localize_text("Will NOT delete Squid log dir '%s' since it is not located under %s. Delete manually if required.", $logdir, '/var/squid'), LOG_PREFIX_PKG_SQUID);
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
		logger(LOG_INFO, localize_text("Removing all Squid settings since 'Keep Settings/Data' is disabled..."), LOG_PREFIX_PKG_SQUID);
		$pkgs = [
			'squid',
			'squidantivirus',
			'squidantivirus',
			'squidauth',
			'squidcache',
			'squidnac',
			'squidreverse',
			'squidreversegeneral',
			'squidreversepeer',
			'squidreverseredir',
			'squidreverseuri',
			'squidsync',
			'squidtraffic',
			'squidremote',
			'squidusers'
		];
		foreach ($pkgs as $pkg) {
			config_del_path("installedpackages/{$pkg}");
		}
	}
}

/* Migrate configuration from god knows which Squid package versions */
/* None of these ever existed with Squid 3.4 package and this cruft should be most likely just removed */
function squid_upgrade_config() {
	/* migrate existing csv config fields */
	if (is_array(config_get_path('installedpackages/squidauth/config'))) {
		$settingsauth = config_get_path('installedpackages/squidauth/config/0');
	}
	if (is_array(config_get_path('installedpackages/squidcache/config'))) {
		$settingscache = config_get_path('installedpackages/squidcache/config/0');
	}
	if (is_array(config_get_path('installedpackages/squidnac/config'))) {
		$settingsnac = config_get_path('installedpackages/squidnac/config/0');
	}
	if (is_array(config_get_path('installedpackages/squid/config'))) {
		$settingsgen = config_get_path('installedpackages/squid/config/0');
	}

	/* migrate auth settings */
	if (!empty($settingsauth['no_auth_hosts']) && strstr($settingsauth['no_auth_hosts'], ",")) {
		$settingsauth['no_auth_hosts'] = base64_encode(implode("\n", explode(",", $settingsauth['no_auth_hosts'])));
		config_set_path('installedpackages/squidauth/config/0/no_auth_hosts', $settingsauth['no_auth_hosts']);
	}
	/* NT Domain authentication has been removed - Bug #7017 */
	if (!empty($settingsauth['auth_method'])) {
		if (preg_match("/msnt/i", $settingsauth['auth_method'])) {
			$msnt_msg = localize_text("NT Domain authentication has been removed - see Bug %s! Use LDAP for AD authentication.", '#7017');
			file_notice("squid", $msnt_msg, "Packages", "");
			logger(LOG_WARNING, $msnt_msg, LOG_PREFIX_PKG_SQUID);
		}
	}

	/* migrate cache settings */
	if (!empty($settingscache['donotcache']) && strstr($settingscache['donotcache'], ",")) {
		$settingscache['donotcache'] = base64_encode(implode("\n", explode(",", $settingscache['donotcache'])));
		config_set_path('installedpackages/squidcache/config/0/donotcache', $settingscache['donotcache']);
	}
	/* unset broken dynamic caching patterns removed since Squid3 package v0.4.3 */
	config_del_path('installedpackages/squidcache/config/refresh_patterns');

	/* migrate nac settings */
	if (!empty($settingsnac['allowed_subnets']) && strstr($settingsnac['allowed_subnets'], ",")) {
		$settingsnac['allowed_subnets'] = base64_encode(implode("\n", explode(",", $settingsnac['allowed_subnets'])));
		config_set_path('installedpackages/squidnac/config/0/allowed_subnets', $settingsnac['allowed_subnets']);
	}

	if (!empty($settingsnac['banned_hosts']) && strstr($settingsnac['banned_hosts'], ",")) {
		$settingsnac['banned_hosts'] = base64_encode(implode("\n", explode(",", $settingsnac['banned_hosts'])));
		config_set_path('installedpackages/squidnac/config/0/banned_hosts', $settingsnac['banned_hosts']);
	}

	if (!empty($settingsnac['unrestricted_hosts']) && strstr($settingsnac['unrestricted_hosts'], ",")) {
		$settingsnac['unrestricted_hosts'] = base64_encode(implode("\n", explode(",", $settingsnac['unrestricted_hosts'])));
		config_set_path('installedpackages/squidnac/config/0/unrestricted_hosts', $settingsnac['unrestricted_hosts']);
	}

	if (!empty($settingsnac['whitelist']) && strstr($settingsnac['whitelist'], ",")) {
		$settingsnac['whitelist'] = base64_encode(implode("\n", explode(",", $settingsnac['whitelist'])));
		config_set_path('installedpackages/squidnac/config/0/whitelist', $settingsnac['whitelist']);
	}

	if (!empty($settingsnac['blacklist']) && strstr($settingsnac['blacklist'], ",")) {
		$settingsnac['blacklist'] = base64_encode(implode("\n", explode(",", $settingsnac['blacklist'])));
		config_set_path('installedpackages/squidnac/config/0/blacklist', $settingsnac['blacklist']);
	}

	if (!empty($settingsnac['block_user_agent']) && strstr($settingsnac['block_user_agent'], ",")) {
		$settingsnac['block_user_agent'] = base64_encode(implode("\n", explode(",", $settingsnac['block_user_agent'])));
		config_set_path('installedpackages/squidnac/config/0/block_user_agent', $settingsnac['block_user_agent']);
	}

	if (!empty($settingsnac['block_reply_mime_type']) && strstr($settingsnac['block_reply_mime_type'], ",")) {
		$settingsnac['block_reply_mime_type'] = base64_encode(implode("\n", explode(",", $settingsnac['block_reply_mime_type'])));
		config_set_path('installedpackages/squidnac/config/0/block_reply_mime_type', $settingsnac['block_reply_mime_type']);
	}

	/* XXX: broken Captive Portal patch (Bug #5594) */
	if (config_get_path('installedpackages/squid/config/0/patch_cp')) {
		if (config_get_path('installedpackages/squid/config/0/patch_cp') == "on") {
			$cp_msg = localize_text(
				"Patch Captive Portal feature was removed - see Bug %s! Double-check '%s' contents for sanity! Get a sane copy of the file from pfSense GitHub repository if needed.",
				'#5594', '/etc/inc/captiveportal.inc'
			);
			file_notice("squid", $cp_msg, "Packages", "");
			logger(LOG_WARNING, $cp_msg, LOG_PREFIX_PKG_SQUID);
			unset($cp_msg);
		}
		config_del_path('installedpackages/squid/config/0/patch_cp');
	}

	/* migrate reverse proxy settings */
	squid_reverse_upgrade_config();

	/* unset broken antivirus settings */
	squid_antivirus_upgrade_config();

	write_config(gettext("Upgraded Squid configuration during package install."));
}


/*
 * Squid input validation
 */

/* Proxy Server: General Settings input validation */
function squid_validate_general($post, &$input_errors) {
	if (is_array(config_get_path('installedpackages/squid'))) {
		$settings = config_get_path('installedpackages/squid/config/0');
	} else {
		$settings = array();
	}

	// force users to configure cache
	if (!is_array(config_get_path('installedpackages/squidcache/config'))) {
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
			logger(LOG_INFO, localize_text("Enabled as reverse proxy ONLY. If this is not what you intended, visit Services: Squid Proxy Server: General and configure proxy interfaces."), LOG_PREFIX_PKG_SQUID);
		}
	}

	if ($post['listenproto'] == 'inet') {
		foreach ($post['active_interface'] as $a_int) {
			if (get_interface_ip($a_int)) {
				$int_proto_ok = true;
				break;
			}
		}
		if (!$int_proto_ok) {
			$input_errors[] = "At least one interface with IPv4 address must be selected.";
		}
		if (($post['outgoing_interface'] != 'auto') && !get_interface_ip($post['outgoing_interface'])) {
			$input_errors[] = "The Outgoing Network Interface must have an IPv4 address.";
		}
	} elseif ($post['listenproto'] == 'inet6') {
		foreach ($post['active_interface'] as $a_int) {
			if (get_interface_ipv6($a_int)) {
				$int_proto_ok = true;
				break;
			}
		}
		if (!$int_proto_ok) {
			$input_errors[] = "At least one interface with IPv6 address must be selected.";
		}
		if (($post['outgoing_interface'] != 'auto') && !get_interface_ipv6($post['outgoing_interface'])) {
			$input_errors[] = "The Outgoing Network Interface must have an IPv6 address.";
		}
	} else {
		foreach ($post['active_interface'] as $a_int) {
			if (get_interface_ip($a_int) || get_interface_ipv6($a_int)) {
				$int_proto_ok = true;
				break;
			}
		}
		if (!$int_proto_ok) {
			$input_errors[] = "At least one interface with IPv4 or IPv6 address must be selected.";
		}
		if (($post['outgoing_interface'] != 'auto') && !get_interface_ip($post['outgoing_interface']) &&
		    !get_interface_ipv6($post['outgoing_interface'])) {
			$input_errors[] = "The Outgoing Network Interface must have an IPv4 or IPv6 address.";
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

	if ($post['log_dir'][0] != '/') {
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
	$webgui_port = config_get_path('system/webgui/port');
	if ((config_get_path('system/webgui/port') == "") && (config_get_path('system/webgui/protocol') == "http")) {
		$webgui_port = 80;
	}
	if ((config_get_path('system/webgui/port') == "") && (config_get_path('system/webgui/protocol') == "https")) {
		$webgui_port = 443;
	}
	if (($post['transparent_proxy'] != 'on') && ($port == $webgui_port)) {
		$input_errors[] = "You can not run Squid on the same port as the pfSense WebGUI";
	}
	unset($port, $webgui_port);

	if ($post['transparent_proxy'] == 'on') {
		if (preg_match("/(local|ldap|radius|ntlm)/", config_get_path('installedpackages/squidauth/config/0/auth_method'))) {
			$input_errors[] = "Transparent mode cannot be enabled while proxy authentication is enabled";
		}
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
		} else {
			$dca = lookup_ca($post['dca']);
			$dca = $dca['item'];
			if (!$dca) {
				$input_errors[] = "Invalid SSL interception CA.";
			}
			if (empty($dca['prv'])) {
				$input_errors[] = "The SSL interception CA must have a private key (internal CA, not external)";
			}
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
		logger(LOG_INFO, localize_text("Clear disk cache forced via GUI. Clearing cache now..."), LOG_PREFIX_PKG_SQUID);
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
		foreach (explode("\n", $post['donotcache']) as $host) {
			$host = trim($host);
			// Allow matching all subdomains, like .example.com
			if (strpos($host, '.') === 0) {
				$host = substr($host, 1);
			}
			if (!is_ipaddr($host) && !is_domain($host)) {
				$input_errors[] = "The host '$host' is not a valid IP or hostname.";
			}
		}
		unset($host);
	}

	if (substr($post['harddisk_cache_location'], -1, 1) == '/') {
		$input_errors[] = 'Log location must not end with a / character.';
	}

	if ($post['harddisk_cache_location'][0] != '/') {
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
		if (preg_match_all("@([0-9a-fA-F.:]+)(/[0-9.]+|)@", $_POST[$hosts], $matches)) {
			for ($x = 0; $x < count($matches[1]); $x++) {
				if ($matches[2][$x] == "") {
					if (!is_ipaddr($matches[1][$x])) {
						$input_errors[] = "'{$matches[1][$x]}' is not a valid IP address.";
					}
				} else {
					if (!is_subnet($matches[0][$x])) {
						$input_errors[] = "The subnet '{$matches[0][$x]}' is not a valid CIDR range or IPv6 prefix.";
					}
				}
			}
		}
	}

	if (!empty($post['ext_cachemanager'])) {
		$extmgr = explode(";", ($post['ext_cachemanager']));
		foreach ($extmgr as $mgr) {
			if (!is_ipaddr($mgr)) {
				$input_errors[] = "You must enter a valid IP address in the 'External Cache Manager' field'.";
			}
		}
	}
	unset($extmgr);

	if (!empty($post['google_accounts'])) {
		foreach (explode(",", $post['google_accounts']) as $dom) {
			$dom = trim($dom);
			if (!is_domain($dom)) {
				$input_errors[] = "The '$dom' is not a valid Google Accounts Domain.";
			}
		}
	}
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

		if (!empty($port['max_user_ip']) && !is_numeric($port['max_user_ip'])) {
			$input_errors[] = "'Authentication Max User IP' must be numeric or empty.";
		}

		switch ($auth_method) {
			case 'ldap':
				$user = trim($post['ldap_user']);
				$ldaptype = trim($post['ldap_urltype']);
				$ldapversion = trim($post['ldap_version']);
				if (empty($user)) {
					$input_errors[] = "'LDAP server user DN' is required.";
				} elseif (!$user) {
					$input_errors[] = "'LDAP server user DN' must be a valid DN.";
				}
				if ($ldaptype == 'starttls' && $ldapversion == '2') {
					$input_errors[] = "'STARTTLS supported only by LDAP version 3.";
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
	global $valid_acls;

	if (is_array(config_get_path('installedpackages/squid'))) {
		$settings = config_get_path('installedpackages/squid/config/0');
	} else {
		$settings = array();
	}

	$conf = "# This file is automatically generated by pfSense\n";
	$conf .= "# Do not edit manually !\n\n";
	// Check ssl interception
	$ssl_interception = '';
	if ($settings['ssl_proxy'] == 'on') {
		squid_gen_extra_ca();
		$srv_cert = lookup_ca($settings["dca"]);
		$srv_cert = $srv_cert['item'];
		if ($srv_cert != false) {
			if (base64_decode($srv_cert['prv'])) {
				if (file_exists(SQUID_LOCALBASE . "/libexec/squid/security_file_certgen")) {
					$squid4 = true;
				}
				// check if ssl_db was initilized by Squid
				if (!file_exists(SQUID_SSL_DB . "/serial")) {
					if (is_dir(SQUID_SSL_DB)) {
						mwexec("/bin/rm -rf " . SQUID_SSL_DB);
					}
					if ($squid4) {
						mwexec(SQUID_LOCALBASE . "/libexec/squid/security_file_certgen -c -s " . SQUID_SSL_DB . " -M 4MB");
					} else {
						mwexec(SQUID_LOCALBASE . "/libexec/squid/ssl_crtd -c -s " . SQUID_SSL_DB);
					}
				}
				// force squid user permission on /var/squid/lib/ssl_db/
				squid_chown_recursive(SQUID_SSL_DB, SQUID_UID, SQUID_GID);
				// cert, key, version, cipher, options, clientca, tls-cafile, capath, crlfile, tls-dh, sslflags, sslcontext
				$crt_pk = SQUID_CONFBASE . "/serverkey.pem";
				$crt_capath = SQUID_LOCALBASE . "/share/certs/";
				$crt_cafile = SQUID_LOCALBASE . "/share/certs/ca-root-nss.crt";
				$sslproxy_options = "NO_SSLv3,NO_TLSv1,NO_TLSv1_1";
				/* XXX: Bug #4453, Bug #6592, Feature #6593, Bug #6563
				 * http://wiki.squid-cache.org/ConfigExamples/Intercept/SslBumpExplicit#Modern_DH.2FEDH_ciphers_usage
				 */
				if (empty($settings['sslproxy_compatibility_mode']) || ($settings['sslproxy_compatibility_mode'] == 'modern')) {
					// Modern cipher suites
					$sslproxy_cipher = "EECDH+ECDSA+AESGCM:EECDH+aRSA+AESGCM:EECDH+ECDSA+SHA384:EECDH+ECDSA+SHA256:EECDH+aRSA+SHA384:EECDH+aRSA+SHA256:EECDH:EDH+aRSA:!RC4:!aNULL:!eNULL:!LOW:!3DES:!SHA1:!MD5:!EXP:!PSK:!SRP:!DSS";
				} else {
					$sslproxy_cipher = "EECDH+ECDSA+AESGCM:EECDH+aRSA+AESGCM:EECDH+ECDSA+SHA384:EECDH+ECDSA+SHA256:EECDH+aRSA+SHA384:EECDH+aRSA+SHA256:EECDH:EDH+aRSA:HIGH:!RC4:!aNULL:!eNULL:!LOW:!3DES:!MD5:!EXP:!PSK:!SRP:!DSS";
				}
				if (!empty($settings["dhparams_size"])) {
					if ($settings['dhparams_size'] == '4096') {
						$sslproxy_dhparams = "tls-dh=prime256v1:/etc/dh-parameters.4096";
					} elseif ($settings['dhparams_size'] == '2048') {
						$sslproxy_dhparams = "tls-dh=prime256v1:/etc/dh-parameters.2048";
					} elseif ($settings['dhparams_size'] == '1024') {
						$sslproxy_dhparams = "tls-dh=prime256v1:/etc/dh-parameters.1024";
					}
				} elseif (file_exists("/etc/dh-parameters.2048")) {
					// Fallback options for defaults on install
					$sslproxy_dhparams = "tls-dh=prime256v1:/etc/dh-parameters.2048";
				} else {
					// Should never get here
					$sslproxy_dhparams = "";
				}
				file_put_contents($crt_pk, unixnewlines(base64_decode($srv_cert['prv']) . "\n" . base64_decode($srv_cert['crt'])));
				$sslcrtd_children = ($settings['sslcrtd_children'] ? $settings['sslcrtd_children'] : 5);
				$ssl_interception .= "ssl-bump generate-host-certificates=on dynamic_cert_mem_cache_size=" . ($sslcrtd_children*2) . "MB cert={$crt_pk} tls-cafile={$crt_cafile} capath={$crt_capath} cipher={$sslproxy_cipher} {$sslproxy_dhparams} options={$sslproxy_options}\n";
				if ($squid4) {
					$interception_checks = "sslcrtd_program " . SQUID_LOCALBASE . "/libexec/squid/security_file_certgen -s " . SQUID_SSL_DB . " -M 4MB -b 2048\n";
					$interception_checks .= "tls_outgoing_options cafile={$crt_cafile}\n";
					$interception_checks .= "tls_outgoing_options capath={$crt_capath}\n";
					$interception_checks .= "tls_outgoing_options options={$sslproxy_options}\n";
					$interception_checks .= "tls_outgoing_options cipher={$sslproxy_cipher}\n";
					if (preg_match("/sslproxy_flags/", $settings["interception_checks"])) {
						$interception_checks .= "tls_outgoing_options flags=DONT_VERIFY_PEER\n";
					}
				} else {
					$interception_checks = "sslcrtd_program " . SQUID_LOCALBASE . "/libexec/squid/ssl_crtd -s " . SQUID_SSL_DB . " -M 4MB -b 2048\n";
					$interception_checks .= "sslproxy_cafile {$crt_cafile}\n";
					$interception_checks .= "sslproxy_capath {$crt_capath}\n";
					$interception_checks .= "sslproxy_options {$sslproxy_options}\n";
					$interception_checks .= "sslproxy_cipher {$sslproxy_cipher}\n";
					if (preg_match("/sslproxy_flags/", $settings["interception_checks"])) {
						$interception_checks .= "sslproxy_flags DONT_VERIFY_PEER\n";
					}
				}
				$interception_checks .= "sslcrtd_children {$sslcrtd_children}\n";
				if (preg_match("/sslproxy_cert_error/", $settings["interception_checks"])) {
					$interception_checks .= "sslproxy_cert_error allow all\n";
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

	if (empty($settings['listenproto'])) {
		$listenproto = 'inet';
	} else {
		$listenproto = $settings['listenproto'];
	}

	if ($settings['transparent_proxy'] == "on") {
		$transparent_ifaces = explode(",", $settings['transparent_active_interface']);
		foreach ($transparent_ifaces as $t_iface) {
			$t_ifaces_ips = squid_get_real_interface_address($t_iface, $listenproto);
			foreach ($t_ifaces_ips as $t_iface_ip) {
				if ($t_iface_ip[0]) {
					$real_ifaces[] = $t_iface_ip;
				}
			}
		}
	} else {
		$transparent_ifaces = array();
	}

	if ($settings['ssl_proxy'] == "on") {
		$ssl_ifaces = explode(",", $settings['ssl_active_interface']);
		foreach ($ssl_ifaces as $s_iface) {
			$s_ifaces_ips = squid_get_real_interface_address($s_iface, $listenproto);
			foreach ($s_ifaces_ips as $s_iface_ip) {
				if ($s_iface_ip[0]) {
					$real_ifaces[] = $s_iface_ip;
				}
			}
		}
	} else {
		$ssl_ifaces = array();
	}

	// check all proxy interfaces selected
	foreach ($proxy_ifaces as $iface) {
		$ifaces_ips = squid_get_real_interface_address($iface, $listenproto);
		foreach ($ifaces_ips as $iface_ip) {
			if ($iface_ip[0]) {
				// do not add loopback twice when transparent proxy is enabled
				if ((($iface_ip[0] == "127.0.0.1") || ($iface_ip[0] == "::1")) && 
				    $settings['transparent_proxy'] == "on") {
					continue;
				} else {
					$real_ifaces[] = $iface_ip;
					if (is_ipaddrv4($iface_ip[0])) {
						$listenip = "{$iface_ip[0]}";
					} elseif ($settings['transparent_proxy'] == 'on') {
						// do not add IPv6 interface twice when transparent proxy is enabled
						continue;
					} else {
						$listenip = "[{$iface_ip[0]}]";
					}
					if (in_array($iface, $ssl_ifaces)) {
						$conf .= "http_port {$listenip}:{$port} {$ssl_interception}\n";
					} else {
						$conf .= "http_port {$listenip}:{$port}\n";
					}
				}
			}
		}
	}

	if ($settings['transparent_proxy'] == 'on') {
		if ($listenproto != 'inet6') {
			if ($settings['ssl_proxy'] == "on" && count($ssl_ifaces) > 0) {
				$conf .= "http_port 127.0.0.1:{$port} intercept {$ssl_interception}\n";
				$conf .= "https_port 127.0.0.1:{$ssl_port} intercept {$ssl_interception}\n";
			} else {
				$conf .= "http_port 127.0.0.1:{$port} intercept\n";
			}
		}
		if ($listenproto != 'inet') {
			foreach ($transparent_ifaces as $t_iface) {
				$t_ifaces_ips = squid_get_real_interface_address($t_iface, $listenproto);
				foreach ($t_ifaces_ips as $t_iface_ip) {
					if (is_ipaddrv6($t_iface_ip[0])) {
						if ($settings['ssl_proxy'] == "on" && count($ssl_ifaces) > 0) {
							$conf .= "http_port [{$t_iface_ip[0]}]:{$port} intercept {$ssl_interception}\n";
							$conf .= "https_port [{$t_iface_ip[0]}]:{$ssl_port} intercept {$ssl_interception}\n";
						} else {
							$conf .= "http_port [{$t_iface_ip[0]}]:{$port} intercept\n";
						}
					}
				}
			}
		}
	}
	if (!empty($settings['outgoing_interface']) && ($settings['outgoing_interface'] != 'auto')) {
		$outip = get_interface_ip($settings['outgoing_interface']);
		$outipv6 = get_interface_ipv6($settings['outgoing_interface']);
		if ($outip && ($listenproto != 'inet6')) {
			$conf .= "tcp_outgoing_address {$outip}\n";
		}
		if ($outipv6 && ($listenproto != 'inet')) {
			$conf .= "tcp_outgoing_address {$outipv6}\n";
		}
	}
	$icp_port = ($settings['icp_port'] ? $settings['icp_port'] : 0);
	$dns_v4_first = ($settings['dns_v4_first'] == "on" ? "on" : "off");
	$piddir = g_get('varrun_path') . '/squid';
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
		logger(LOG_INFO, localize_text("Creating Squid log dir '%s' ...", $logdir), LOG_PREFIX_PKG_SQUID);
		safe_mkdir($logdir, 0755);
	}
	squid_chown_recursive($logdir, SQUID_UID, SQUID_GID);
	$logdir_cache = $logdir . '/cache.log';
	$logdir_access = ($settings['log_enabled'] == 'on' ? $logdir . '/access.log' : '/dev/null');
	$pinger_helper = ($settings['disable_pinger']) == 'on' ? 'off' : 'on';
	$pinger_program = SQUID_LOCALBASE . "/libexec/squid/pinger";

	$squid_uid = SQUID_UID;
	$squid_gid = SQUID_GID;

	/*
	 * Disable cache digest generation unless we are a sibbling to another proxy.
	 * This avoids periods when Squid is unresponsive due to digest generation.
	 */
	$digest_generation = "off";

	foreach (config_get_path('installedpackages/squid/config/squidremote/config', []) as $settings) {
		if ($settings['enable'] == 'on' && $settings['hierarchy'] == 'sibling') {
			$digest_generation = "on";
			break;
		}
	}

	$conf .= <<< EOD
icp_port {$icp_port}
digest_generation {$digest_generation}
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
			if (is_subnetv6("{$ip}/{$mask}")) {
				$src6 = gen_subnetv6($ip, $mask) . "/" . $mask;
				if (!preg_match("@$src6@", $src)) {
					$src .= " " . $src6;
				}
			} else {
				$ip = long2ip(ip2long($ip) & ip2long($mask));
				$mask = 32 - log((ip2long($mask) ^ ip2long('255.255.255.255')) +1, 2);
				if (!preg_match("@$ip/$mask@", $src)) {
					// XXX: Do not add invalid subnets (Bug #4331, Bug #4526)
					if (is_subnetv4("{$ip}/{$mask}")) {
						$src .= " $ip/$mask";
					} else {
						logger(LOG_WARNING, localize_text("'Allow Users on Interface' ACL skipped for '%s/%s' since it is not a valid IPv4 subnet.", $ip, $mask), LOG_PREFIX_PKG_SQUID);
					}
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
	if (is_array(config_get_path('installedpackages/squidcache'))) {
		$settings = config_get_path('installedpackages/squidcache/config/0');
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
		if (!is_array(config_get_path('installedpackages/squidcache/config'))) {
			logger(LOG_INFO, localize_text("'Local Cache' not configured, disk cache will be disabled."), LOG_PREFIX_PKG_SQUID);
			logger(LOG_INFO, localize_text("Please, configure and save 'Local Cache' settings before enabling Squid proxy."), LOG_PREFIX_PKG_SQUID);
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
	if ($settings['nocache'] == 'on') {
		$conf .= "cache deny all\n";
	} else {
		$conf .= "cache allow all\n";
	}

	return $conf.$refresh_conf;
}

/* Proxy Server: Remote Proxy Settings configuration handler */
function squid_resync_upstream() {
	$conf = "\n#Remote proxies\n";
	foreach (config_get_path('installedpackages/squidremote/config', []) as $settings) {
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
	global $valid_acls;

	if (is_array(config_get_path('installedpackages/squidnac'))) {
		$settings = config_get_path('installedpackages/squidnac/config/0');
	} else {
		$settings = array();
	}
	if (is_array(config_get_path('installedpackages/squid'))) {
		$squidsettings = config_get_path('installedpackages/squid/config/0');
	} else {
		$squidsettings = array();
	}

	$webgui_port = config_get_path('system/webgui/port');
	$addtl_ports = $settings['addtl_ports'];
	$addtl_sslports = $settings['addtl_sslports'];
	// do not add (default) proxy ports when using Squid as reverse proxy only
	if (!empty($squidsettings['active_interface'])) {
		$port = $squidsettings['proxy_port'] ? $squidsettings['proxy_port'] : 3128;
		$ssl_port = $squidsettings['ssl_proxy_port'] ? $squidsettings['ssl_proxy_port'] : 3129;
	}
	$conf = <<< EOD

# Setup some default acls
# ACLs all, manager, localhost, and to_localhost are predefined.
acl allsrc src all
acl safeports port 21 70 80 210 280 443 488 563 591 631 777 901 {$webgui_port} {$port} {$ssl_port} 1025-65535 {$addtl_ports}
acl sslports port 443 563 {$webgui_port} {$addtl_sslports}

acl purge method PURGE
acl connect method CONNECT

# Define protocols used for redirects
acl HTTP proto HTTP
acl HTTPS proto HTTPS

EOD;

	if ($squidsettings['ssl_proxy'] == 'on') {
		$conf .= <<< EOD

# SslBump Peek and Splice
# http://wiki.squid-cache.org/Features/SslPeekAndSplice
# http://wiki.squid-cache.org/ConfigExamples/Intercept/SslBumpExplicit
# Match against the current step during ssl_bump evaluation [fast]
# Never matches and should not be used outside the ssl_bump context.
#
# At each SslBump step, Squid evaluates ssl_bump directives to find
# the next bumping action (e.g., peek or splice). Valid SslBump step
# values and the corresponding ssl_bump evaluation moments are:
#   SslBump1: After getting TCP-level and HTTP CONNECT info.
#   SslBump2: After getting TLS Client Hello info.
#   SslBump3: After getting TLS Server Hello info.
# These ACLs exist even when 'SSL/MITM Mode' is set to 'Custom' so that
# they can be used there for custom configuration.
acl step1 at_step SslBump1
acl step2 at_step SslBump2
acl step3 at_step SslBump3

EOD;
	}

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
		$contents = sq_text_area_decode($settings[$option], true);
		if (!empty($contents)) {
			file_put_contents(SQUID_ACLDIR . "/$option.acl", $contents);
			$conf .= "acl $option $directive \"" . SQUID_ACLDIR . "/$option.acl\"\n";
			if (($squidsettings['sslproxy_mitm_mode'] == "splicewhitelist") &&
		            ($option == 'whitelist')) {
				$conf .= "acl sslwhitelist ssl::server_name_regex -i \"" . SQUID_ACLDIR . "/$option.acl\"\n";
			}
			$valid_acls[] = $option;
		} elseif (file_exists(SQUID_ACLDIR . "/$option.acl")) {
			unlink(SQUID_ACLDIR . "/$option.acl");
		}
	}

	$conf .= <<< EOD
http_access allow manager localhost

EOD;

	if (is_array(config_get_path('installedpackages/squidcache'))) {
		$settings_ch = config_get_path('installedpackages/squidcache/config/0');
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
http_access allow localhost

EOD;

	return $conf;
}

/* Proxy server: Traffic Management configuration handler */
function squid_resync_traffic() {
	global $valid_acls;

	if (!is_array($valid_acls)) {
		return;
	}
	if (is_array(config_get_path('installedpackages/squidtraffic'))) {
		$settings = config_get_path('installedpackages/squidtraffic/config/0');
	} else {
		$settings = array();
	}
	if (is_array(config_get_path('installedpackages/squidnac/config'))) {
		$settingsnac = config_get_path('installedpackages/squidnac/config/0');
	} else {
		$settingsnac = array();
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

	if (!empty($settingsnac['unrestricted_hosts'])) {
		if (squid_is_valid_acl('unrestricted_hosts') && $settings['unrestricted_throttling'] != "on") {
			foreach (array('unrestricted_hosts') as $item) {
				if (in_array($item, $valid_acls)) {
					$conf .= "# Do not throttle unrestricted hosts\n";
					$conf .= "delay_access 1 deny $item\n";
				}
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
	global $valid_acls;
	$write_config = 0;
	if (!is_array(config_get_path('installedpackages/squidauth/config'))) {
		config_set_path('installedpackages/squidauth/config', [['auth_method' => "none"]]);
		$write_config++;
	}
	$settings = config_get_path('installedpackages/squidauth/config/0');
	if (is_array(config_get_path('installedpackages/squidnac/config'))) {
		$settingsnac = config_get_path('installedpackages/squidnac/config/0');
	} else {
		$settingsnac = array();
	}

	if (is_array(config_get_path('installedpackages/squid/config'))) {
		$settingsconfig = config_get_path('installedpackages/squid/config/0');
	} else {
		$settingsconfig = array();
	}

	if ($write_config > 0) {
		write_config(gettext("Squid authentication method not configured, setting to None explicitly."));
	}

	$conf = '';

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

	// Unrestricted hosts take precedence over blacklist
	if (!empty($settingsnac['unrestricted_hosts'])) {
		if (squid_is_valid_acl('unrestricted_hosts') && $settings['unrestricted_auth'] != "on") {
			$conf .= "# These hosts do not have any restrictions\n";
			$conf .= "http_access allow unrestricted_hosts\n";
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
	if (!empty($settingsnac['google_accounts'])) {
		$conf .= "# List of domains allowed to logging in to Google services\n";
		$conf .= "request_header_access X-GoogApps-Allowed-Domains deny all\n";
		$conf .= "request_header_add X-GoogApps-Allowed-Domains {$settingsnac['google_accounts']}\n";
	}
	if (!empty($settingsnac['youtube_restrict'])) {
		$conf .= "# Set YouTube safesearch restriction\n";
		$conf .= "acl youtubedst dstdomain -n www.youtube.com m.youtube.com ";
	        $conf .= "youtubei.googleapis.com youtube.googleapis.com www.youtube-nocookie.com\n";
		$conf .= "request_header_access YouTube-Restrict deny all\n";
		$conf .= "request_header_add YouTube-Restrict {$settingsnac['youtube_restrict']} youtubedst\n";
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
		// SSL interception ACL options without authentication
		if ($settingsconfig['ssl_proxy'] == "on") {
			// Custom SSL/MITM configuration overrides everything
			if ($settingsconfig['sslproxy_mitm_mode'] == "custom") {
				$conf .= "# Custom SSL/MITM options before auth\n" . sq_text_area_decode($settingsconfig['custom_options3_squid3']) . "\n\n";
			} elseif ($settingsconfig['sslproxy_mitm_mode'] == "spliceall") {
				// 'Splice All' SSL/MIMT configuration
				$conf .= "ssl_bump peek step1\n";
				$conf .= "ssl_bump splice all\n";
			} else {
				// 'Splice Whitelist, Bump Otherwise' SSL/MIMT configuration (Default)
				$conf .= "ssl_bump peek step1\n";
				if (!empty($settingsnac['whitelist'])) {
					$conf .= "ssl_bump splice sslwhitelist\n";
				}
				$conf .= "ssl_bump bump all\n";
			}
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
				$port = '';
				$ldap_proto = 'ldap://';
				$usetls = '';
				if (isset($settings['auth_server_port']) && !empty($settings['auth_server_port'])) {
					$port = ":{$settings['auth_server_port']}";
				} else if ($settings['ldap_urltype'] == 'ssl') {
					$port = ':636';
				}
				if ($settings['ldap_urltype'] == 'ssl') {
					$ldap_proto = 'ldaps://';
				} else if ($settings['ldap_urltype'] == 'starttls') {
					$usetls = '-ZZ';
				} 
				$password = (isset($settings['ldap_pass']) ? "-w " . escapeshellarg($settings['ldap_pass']) : '');
				$noreferrals = ($settings['ldap_noreferrals'] == 'on' ? "-R" : '');
				$conf .= "auth_param basic program " . SQUID_LOCALBASE
					. "/libexec/squid/basic_ldap_auth -v {$settings['ldap_version']} -b " . escapeshellarg($settings['ldap_basedomain'])
					. " " . $noreferrals . " -D " . escapeshellarg($settings['ldap_user']) . " " . $password
					. " -f " . escapeshellarg($settings['ldap_filter']) . " -u " . escapeshellarg($settings['ldap_userattribute'])
					. " " . $usetls . " -P -H " . escapeshellarg($ldap_proto . $settings['auth_server'] . $port) . "\n";
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

		// Authentication TTL
		$conf .= "authenticate_ip_ttl $auth_ttl minute\n";

		// Authentication Max User IP
		if (is_numeric($settings['max_user_ip'])) {
			$conf .= "acl maxip max_user_ip -s {$settings['max_user_ip']}\n";
			$conf .= "http_access deny maxip\n";
		}

		// Custom User Options after authentication definition
		$conf .= "# Custom options after auth\n" . sq_text_area_decode($settingsconfig['custom_options2_squid3']) . "\n\n";
		// SSL interception ACL options with authentication
		if ($settingsconfig['ssl_proxy'] == "on") {
			// Custom SSL/MITM configuration overrides everything
			if ($settingsconfig['sslproxy_mitm_mode'] == "custom") {
				$conf .= "# Custom SSL/MITM options after auth\n" . sq_text_area_decode($settingsconfig['custom_options3_squid3']) . "\n\n";
			} elseif ($settingsconfig['sslproxy_mitm_mode'] == "spliceall") {
				// 'Splice All' SSL/MIMT configuration
				$conf .= "ssl_bump peek step1\n";
				$conf .= "ssl_bump splice all\n";
			} else {
				// 'Splice Whitelist, Bump Otherwise' SSL/MIMT configuration (Default)
				$conf .= "ssl_bump peek step1\n";
				if (!empty($settingsnac['whitelist'])) {
					$conf .= "ssl_bump splice sslwhitelist\n";
				}
				$conf .= "ssl_bump bump all\n";
			}
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
	$users = config_get_path('installedpackages/squidusers/config');
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
	// detect boot process
	if (is_array($_POST)) {
		if (!is_platform_booting()) {
			unset($boot_process);
		} else {
			$boot_process = "on";
		}
	}

	logger(LOG_INFO, localize_text("%s function call pr:%s bp:%s rpc:%s", 'squid_resync', is_process_running('squid'), isset($boot_process), $via_rpc), LOG_PREFIX_PKG_SQUID);

	if (is_process_running('squid') && isset($boot_process) && $via_rpc == "no") {
		return;
	}


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
}

/*
 * Squid firewall rules configuration
 */

function squid_generate_rules($type) {
	$squid_conf = config_get_path('installedpackages/squid/config/0');

	// Do not install any firewall rules if Squid is disabled or used as reverse proxy only
	if (!squid_enabled()) {
		logger(LOG_INFO, localize_text("Installed but disabled. Not installing '%s' rules.", $type), LOG_PREFIX_PKG_SQUID);
		return;
	} elseif (empty($squid_conf['active_interface'])) {
		logger(LOG_INFO, localize_text("Configured as reverse proxy only. Not installing '%s' rules.", $type), LOG_PREFIX_PKG_SQUID);
		return;
	}

	// Do not install any firewall rules if Squid is not running
	if (!is_service_running('squid')) {
		logger(LOG_INFO, localize_text("Installed but not started. Not installing '%s' rules.", $type), LOG_PREFIX_PKG_SQUID);
		return;
	}

	// Proxy Interface(s)
	$proxy_ifaces = explode(",", $squid_conf['active_interface']);
	$proxy_ifaces = array_map('convert_friendly_interface_to_real_interface_name', $proxy_ifaces);
	// Transparent Proxy Interface(s)
	if ($squid_conf['transparent_proxy'] == "on") {
		$transparent_ifaces = explode(",", $squid_conf['transparent_active_interface']);
		$transparent_ifaces = array_map('convert_friendly_interface_to_real_interface_name', $transparent_ifaces);
	} else {
		$transparent_ifaces = array();
	}
	// SSL Intercept Interface(s)
	if ($squid_conf['ssl_proxy'] == "on") {
		$ssl_ifaces = explode(",", $squid_conf['ssl_active_interface']);
		$ssl_ifaces = array_map('convert_friendly_interface_to_real_interface_name', $ssl_ifaces);
	} else {
		$ssl_ifaces = array();
	}

	// Define proxy ports
	$port = ($squid_conf['proxy_port'] ? $squid_conf['proxy_port'] : 3128);
	$ssl_port = ($squid_conf['ssl_proxy_port'] ? $squid_conf['ssl_proxy_port'] : 3129);
	$pf_rule_ports = "{{$port},{$ssl_port}}";

	// Define NAT ports - 80 and 443 if SSL filtering is enabled
	$pf_nat_ports = ($squid_conf['ssl_proxy'] == "on" ? "{80,443}" : "80");

	if (empty($squid_conf['listenproto'])) {
		$listenproto = 'inet';
	} else {
		$listenproto = $squid_conf['listenproto'];
	}

	/*
	 * When transparent proxy is enabled and we are doing NAT, use rdr pass to pass traffic
	 * For PPPoE server, the $pppoe below is a pf macro, not a PHP variable, needs to be escaped.
	 */
	$rules = '';
	switch($type) {
		case 'nat':
			// No NAT rules if transparent proxy is not enabled
			if ($squid_conf['transparent_proxy'] != 'on') {
				break;
			}
			$rules .= "\n# Setup Squid proxy redirect\n";
			/* Bypass Proxy for Private Address Destination */
			if ($squid_conf['private_subnet_proxy_off'] == 'on') {
				foreach ($transparent_ifaces as $iface) {
					$pf_transparent_rule_port = (in_array($iface, $ssl_ifaces) ? "{80,443}" : "80");
					if ($listenproto != 'inet6') {
						$rules .= "no rdr on $iface proto tcp from any to { 192.168.0.0/16, 172.16.0.0/12, 10.0.0.0/8 } port {$pf_transparent_rule_port}\n";
					}
					if ($listenproto != 'inet') {
						$rules .= "no rdr on $iface proto tcp from any to { fc00::/7 } port {$pf_transparent_rule_port}\n";
					}
				}
				/* Handle PPPOE case */
				if (((config_get_path('pppoe/mode') == "server" && config_get_path('pppoe/localip')) ||
				    (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) &&
				    ($listenproto != 'inet6')) {
					$rules .= "no rdr on \$pppoe proto tcp from any to { 192.168.0.0/16, 172.16.0.0/12, 10.0.0.0/8 } port {$pf_nat_ports}\n";
				}
			}
			/* Bypass Proxy for These Source IPs */
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
				if (((config_get_path('pppoe/mode') == "server" && config_get_path('pppoe/localip')) ||
				    (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) &&
				    ($listenproto != 'inet6')) {
					$rules .= "no rdr on \$pppoe proto tcp from { $exempt_ip } to any port {$pf_nat_ports}\n";
				}
			}
			/* Bypass Proxy for These Destination IPs */
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
				if (((config_get_path('pppoe/mode') == "server" && config_get_path('pppoe/localip')) ||
				    (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) &&
				    ($listenproto != 'inet6')) {
					$rules .= "no rdr on \$pppoe proto tcp from any to { $exempt_dest } port {$pf_nat_ports}\n";
				}
			}
			/* Transparent Proxy Interface(s) */
			foreach ($transparent_ifaces as $t_iface) {
				$t_ifaces_ips = squid_get_real_interface_address($t_iface, $listenproto);
				foreach ($t_ifaces_ips as $t_iface_ip) {
					if (is_ipaddrv4($t_iface_ip[0]) && ($listenproto != 'inet6')) {
						$rules .= "rdr pass on $t_iface inet proto tcp from any to !($t_iface) " .
						"port 80 -> 127.0.0.1 port {$port}\n";
						if (in_array($t_iface, $ssl_ifaces)) {
							$rules .= "rdr pass on $t_iface inet proto tcp from any to !($t_iface) " .
							"port 443 -> 127.0.0.1 port {$ssl_port}\n";
						}
					} elseif (is_ipaddrv6($t_iface_ip[0]) && ($listenproto != 'inet')) {
						$rules .= "rdr pass on $t_iface inet6 proto tcp from any to !($t_iface) " .
						"port 80 -> {$t_iface_ip[0]} port {$port}\n";
						if (in_array($t_iface, $ssl_ifaces)) {
							$rules .= "rdr pass on $t_iface inet6 proto tcp from any to !($t_iface) " .
							"port 443 -> {$t_iface_ip[0]} port {$ssl_port}\n";
						}
					}
				}
			}
			/*
			 * Transparent Proxy Interface(s) - handle the PPPOE case
			 * For PPPoE server, mpd uses a group of different _local_ interfaces
			 * The rules below are needed so that the clients can be transparently proxied
			 */
			if (((config_get_path('pppoe/mode') == "server" && config_get_path('pppoe/localip')) ||
			    (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) &&
			    ($listenproto != 'inet6')) {
				$rules .= "rdr pass on \$pppoe proto tcp from any to !127.0.0.1 port 80 -> 127.0.0.1 port {$port}\n";
				if ($squid_conf['ssl_proxy'] == "on") {
					$rules .= "rdr pass on \$pppoe proto tcp from any to !127.0.0.1 port 443 -> 127.0.0.1 port {$ssl_port}\n";
				}
			}
			$rules .= "\n";
			break;
		case 'filter':
		case 'rule':
			/*
			 * Non-Transparent Proxy Interface(s)
			 * Pass traffic to proxy ports on the proxy interface(s) when 'Allow Users on Interface' is enabled
			 * XXX: Consider ACLs > Allowed Subnets
			 */
			if ($squid_conf['allow_interface'] == 'on') {
				foreach ($proxy_ifaces as $iface) {
					$rules .= "# Setup squid pass rules for proxy\n";
					$rules .= "pass in quick on $iface proto tcp from any to ($iface) port {$pf_rule_ports} flags S/SA keep state\n";
					$rules .= "\n";
				}
				/* Handle PPPOE case */
				if (config_get_path('pppoe/mode') == "server" && config_get_path('pppoe/localip')) {
					$rules .= "pass in quick on \$pppoe proto tcp from any to " . config_get_path('pppoe/localip') . " port {$pf_rule_ports} flags S/SA keep state\n";
				}
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
	if (is_array(config_get_path('installedpackages/squidsync/config'))) {
		$squid_sync = config_get_path('installedpackages/squidsync/config/0');
		$synconchanges = $squid_sync['synconchanges'];
		$synctimeout = $squid_sync['synctimeout'] ?: '250';
		switch ($synconchanges) {
			case "manual":
				if (is_array($squid_sync['row'])) {
					$rs = $squid_sync['row'];
				} else {
					logger(LOG_WARNING, localize_text("XMLRPC sync is enabled but there are no hosts configured as replication targets."), LOG_PREFIX_PKG_SQUID);
					return;
				}
				break;
			default:
				return;
				break;
		}
		if (is_array($rs)) {
			logger(LOG_INFO, localize_text("XMLRPC sync is starting."), LOG_PREFIX_PKG_SQUID);
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
						logger(LOG_ERR, localize_text("XMLRPC sync with '%s' aborted due to the following error(s): %s", $sync_to_ip, $error), LOG_PREFIX_PKG_SQUID);
					}
				}
			}
			logger(LOG_INFO, localize_text("XMLRPC sync completed."), LOG_PREFIX_PKG_SQUID);
		}
 	}
}

if (!function_exists('pf_version')) {
	function pf_version() {
		return substr(trim(file_get_contents("/etc/version")), 0, 3);
	}
}

/* Perform the actual XMLRPC sync */
function squid_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout) {
	if ($username == "" || $password == "" || $sync_to_ip == "" || $port == "" || $protocol == "") {
		logger(LOG_WARNING, localize_text("A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync"), LOG_PREFIX_PKG_SQUID);
		return;
	}

	/* XML will hold the sections to sync */
	$xml = array();
	$pkgs = [
		'squid',
		'squidremote',
		'squidcache',
		'squidantivirus',
		'squidnac',
		'squidtraffic',
		'squidreversegeneral',
		'squidreversepeer',
		'squidreverseuri',
		'squidauth',
		'squidusers'
	];

	foreach ($pkgs as $pkg) {
		$xml[$pkg] = config_get_path("installedpackages/{$pkg}");
	}

	/* Commands to reload Squid settings on the destination sync host. */
	$execcmd = "require_once('/usr/local/pkg/squid.inc');\n";
	$execcmd .= "squid_resync('yes');";

	// xmlrpc cannot encode NULL objects/arrays..
	foreach($xml as $xmlkey => $xmlvalue) {
		if (gettype($xmlvalue) == "NULL") {
			$xml[$xmlkey] = array();
		}
	}
	$synctimeout = intval($synctimeout);
	$rpc_client = new pfsense_xmlrpc_client();
	$rpc_client->setConnectionData($sync_to_ip, $port, $username, $password, $protocol);
	$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section', $xml, $synctimeout);//
	$resp = $rpc_client->xmlrpc_exec_php($execcmd, $synctimeout);
}

function squid_plugin_xmlrpc_send() {
	if (config_get_path('installedpackages/squidsync/config/0/synconchanges') != 'auto') {
		return [];
	}

	return [
		'installedpackages/squidsync/config/0/synconchanges',
		'installedpackages/squid',
		'installedpackages/squidremote',
		'installedpackages/squidcache',
		'installedpackages/squidantivirus',
		'installedpackages/squidnac',
		'installedpackages/squidtraffic',
		'installedpackages/squidreversegeneral',
		'installedpackages/squidreversepeer',
		'installedpackages/squidreverseuri',
		'installedpackages/squidauth',
		'installedpackages/squidusers'
	];
}

function squid_plugin_xmlrpc_recv($new_sections) {
	if (array_get_path($new_sections, 'installedpackages/squidsync/config/0/synconchanges') != 'auto') {
		return [];
	}

	$section_paths = [
		'installedpackages/squid',
		'installedpackages/squidremote',
		'installedpackages/squidcache',
		'installedpackages/squidantivirus',
		'installedpackages/squidnac',
		'installedpackages/squidtraffic',
		'installedpackages/squidreversegeneral',
		'installedpackages/squidreversepeer',
		'installedpackages/squidreverseuri',
		'installedpackages/squidauth',
		'installedpackages/squidusers'
	];

	$ret = [
		'xmlrpc_recv_result' => false
	];
	foreach ($section_paths as $path) {
		$old_section = config_get_path($path, []);
		$new_section = array_get_path($new_sections, $path, []);

		// Don't save empty configuration sections.
		if (empty($old_section) && empty($new_section)) {
			continue;
		}
		// Ignore unchanged configuration sections.
		if ($old_section === $new_section) {
			continue;
		}

		$ret[$path] = array_merge($old_section, $new_section);
		$ret['xmlrpc_recv_result'] = true;
	}

	return $ret;
}

function squid_plugin_xmlrpc_recv_done($xmlrpc_recv_result) {
	if (array_get_path($xmlrpc_recv_result, 'squid/xmlrpc_recv_result') !== true) {
		return;
	}
	squid_resync('yes');
}

/* Get a listing of CA entries which are valid for use with MITM/Splice.
 * They MUST have a private key present!
 */
function squid_get_ca_or_certs($type, $consumer = 'HTTPS', $noprv = false, $addnone = true) {
	$c_arr = array();
	if (!in_array($type, ['cert', 'ca'])) {
		return $c_arr;
	}

	if (function_exists('cert_build_list')) {
		$list_arr = cert_build_list($type, $consumer, true, false);
	}
	if (is_array(config_get_path("{$type}"))) {
		if (is_array($list_arr)) {
			foreach (config_get_path("{$type}", []) as $c) {
				if (in_array($c['refid'],array_column($list_arr, 'refid')) && 
				 (!empty($c['prv']) || $noprv)) {
					$c_arr[] = $c;
				}
			}
		} else {
			foreach (config_get_path("{$type}", []) as $c) {
				if (!empty($c['prv']) || $noprv) {
					$c_arr[] = $c;
				}
			}
		}
	}
	if ($addnone) {
		$c_arr[] = array('refid' => 'none', 'descr' => 'none (auto)');
	}
	return $c_arr;
}

function squid_get_interface_list($loopback=true,$gwgroups=false,$auto=false) {
	$ifaces = array();
	$carplist = get_configured_vip_list();
	if ($auto) {
		$ifaces[] = array("name" => "Default (auto)", "value" => "auto");
	}
	foreach ($carplist as $vid => $vaddr) {
		$vip = get_configured_vip($vid);
		$ifaces[] = array("name" => "$vaddr ({$vip['descr']})", "value" => $vid);
	}
	foreach (get_configured_interface_with_descr() as $value => $name) {
		$ifaces[] = array("name" => $name, "value" => $value);
	}
	if ($loopback) {
		$ifaces[] = array("name" => "loopback", "value" => "lo0");
	}
	if ($gwgroups) {
		foreach (return_gateway_groups_array() as $name => $group) {
			$ifaces[] = array("name" => 'GW Group ' . $name, "value" => $name);
		}
	}
	return ($ifaces);
}

/* Build a list of only CARP VIPs, formatted for use in a selection list */
function squid_get_carp_list() {
	$list = array();
	$list[] = array("name" => "none", "value" => "none");
	$carplist = get_configured_vip_list();
	foreach ($carplist as $vid => $vaddr) {
		$vip = get_configured_vip($vid);
		$list[] = array("name" => "$vaddr ({$vip['descr']})", "value" => $vid);
	}
	return ($list);
}

function squid_plugin_carp($pluginparams) {
	/* If squid is not configured, then stop */
	if (is_array(config_get_path('installedpackages/squid/config'))) {
		$squid_config = config_get_path('installedpackages/squid/config/0');
	} else {
		return null;
	}
	/* If there is no properly configured CARP status check IP, then stop */
	if (!isset($squid_config['carpstatusvid']) || $squid_config['carpstatusvid'] == "none") {
		return null;
	}
	/* Locate the VIP and match it against the configured check address */
	list($vhid, $iface) = explode("@", trim($pluginparams['interface']));
	$friendly = convert_real_interface_to_friendly_interface_name($iface);
	$vip = get_configured_vip($squid_config['carpstatusvid']);
	if (($vip['vhid'] != $vhid) || ($vip['interface'] != $friendly)) {
		/* This is not the VIP we're looking for, move along. */
		return null;
	}
	/* Start or stop the service as needed based on the CARP transition. */
	if ($pluginparams['event'] == "rc.carpmaster") {
		logger(LOG_INFO, localize_text("Starting service..."), LOG_PREFIX_PKG_SQUID);
		/* Stopping the service on other node may take some time 
		(shutdown_lifetime 3 seconds) */
		sleep(4);
		start_service("squid");
		filter_configure();
	} elseif (($pluginparams['event'] == "rc.carpbackup") && is_service_running('squid')) {
		logger(LOG_INFO, localize_text("Stopping service... (CARP BACKUP/INIT)"), LOG_PREFIX_PKG_SQUID);
		squid_stop_monitor();
		stop_service("squid");
		filter_configure();
	}
}

function squid_plugin_certificates($pluginparams) {
	$result = array('pkgname' => 'Squid', 'certificatelist' => array());
	$squidconf = config_get_path('installedpackages/squid/config/0');
	$squidrevconf = config_get_path('installedpackages/squidreversegeneral/config/0');

	if (($pluginparams['type'] == 'certificates') && !empty($squidconf) &&
	    ($squidconf['enable_squid'] == 'on')) {
	    	// CA
		if (($pluginparams['event'] == 'used_ca') && !empty($squidconf['extraca'])) {
			$item = array();
			$cert = $squidconf['extraca'];
			$item['usedby'] = 'Extra Trusted CA';
			$result['certificatelist'][$cert][] = $item;
		}
		if (($pluginparams['event'] == 'used_ca') && !empty($squidconf['dca']) &&
		    ($squidconf['ssl_proxy'] == 'on')) {
			$item = array();
			$cert = $squidconf['dca'];
			$item['usedby'] = 'HTTPS/SSL Interception CA';
			$result['certificatelist'][$cert][] = $item;
		}
		if (($pluginparams['event'] == 'used_ca') && !empty($squidrevconf['reverse_ssl_clientca']) &&
		    ($squidrevconf['reverse_https'] == 'on')) {
			$item = array();
			$cert = $squidrevconf['reverse_ssl_clientca'];
			$item['usedby'] = 'Client Certificate CA';
			$result['certificatelist'][$cert][] = $item;
		}

		// Cert
		if (($pluginparams['event'] == 'used_certificates') && !empty($squidrevconf['reverse_ssl_cert']) &&
		    ($squidrevconf['reverse_https'] == 'on')) {
			$item = array();
			$cert = $squidrevconf['reverse_ssl_cert'];
			$item['usedby'] = 'Reverse SSL Certificate';
			$result['certificatelist'][$cert][] = $item;
		}

		// CRL
		if (($pluginparams['event'] == 'used_crl') && !empty($squidrevconf['reverse_ssl_clientcrl']) &&
		    ($squidrevconf['reverse_https'] == 'on')) {
			$item = array();
			$cert = $squidrevconf['reverse_ssl_clientcrl'];
			$item['usedby'] = 'Client Certificate Revocation List';
			$result['certificatelist'][$cert][] = $item;
		}
	}

	return $result;
}
