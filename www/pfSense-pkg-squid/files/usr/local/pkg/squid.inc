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
require_once('globals.inc');
require_once('config.inc');
require_once('util.inc');
require_once('pfsense-utils.inc');
require_once('pkg-utils.inc');
require_once('service-utils.inc');

if (!function_exists("filter_configure")) {
	require_once("filter.inc");
}

$shortcut_section = "squid";

global $pfs_version;
$pfs_version = substr(trim(file_get_contents("/etc/version")), 0, 3);
if ($pfs_version == "2.1" || $pfs_version == "2.2") {
	define('SQUID_BASE', '/usr/pbi/squid-' . php_uname("m"));
	define('SQUID_LOCALBASE', SQUID_BASE . "/local");
	define('SQUID_UID', 'proxy');
	define('SQUID_GID', 'proxy');
} else {
	define('SQUID_BASE', '/usr/local');
	define('SQUID_LOCALBASE', '/usr/local');
	define('SQUID_UID', 'squid');
	define('SQUID_GID', 'squid');
}

define('SQUID_CONFBASE', SQUID_LOCALBASE .'/etc/squid');
define('SQUID_CONFFILE', SQUID_CONFBASE . '/squid.conf');
define('SQUID_ACLDIR', '/var/squid/acl');
define('SQUID_PASSWD', '/var/etc/squid.passwd');
define('SQUID_SSL_DB','/var/squid/lib/ssl_db');

$valid_acls = array();

$uname = posix_uname();
if ($uname['machine'] == 'amd64') {
	ini_set('memory_limit', '250M');
}

function sq_text_area_decode($text) {
	return preg_replace('/\r\n/', "\n", base64_decode($text));
}

function squid_get_real_interface_address($iface) {
	if (!function_exists("get_interface_ip")) {
		require_once("interfaces.inc");
	}

	return array(get_interface_ip($iface), gen_subnet_mask(get_interface_subnet($iface)));
}

function squid_chown_recursive($dir, $user, $group) {
	if (empty($dir) || ($dir == '/') || ($dir == '/usr/local') || !is_dir($dir)) {
		log_error(gettext("Squid attempted to chown an invalid directory: {$dir}"));
		return;
	}

	chown($dir, $user);
	chgrp($dir, $group);
	$handle = opendir($dir) ;
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
}

function squid_check_clamav_user($user) {
	if (SQUID_BASE == '/usr/local') {
		return;
	}

	$_gc = exec("/usr/sbin/pw usershow {$user}", $sq_ex_output, $sq_ex_return);
	$user_arg = ($sq_ex_return == 0 ? "mod" : "add");
	$_gc = exec("/usr/sbin/pw user{$user_arg} {$user} -G wheel -u 9595 -s /sbin/nologin", $sq_ex_output, $sq_ex_return);
	if ($sq_ex_return != 0) {
		log_error("Squid - Could not change clamav user settings. " . serialize($sq_ex_output));
	}
}

function squid_update_clamav() {
	log_error("Updating ClamAV definitions now... This will take a while. Check /var/log/clamav/freshclam.log for progress information.");
	mwexec_bg(SQUID_BASE . "/bin/freshclam --config-file=" . SQUID_BASE . "/etc/freshclam.conf");
}

/* setup cache */
function squid_dash_z($cache_action = 'none') {
	global $config;

	// We need cache created on package install
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
				log_error("Deleting Squid cache dir {$cachedir} since 'Hard Disk Cache System' is set to null.");
				// cannot nuke disk cache while Squid is running
				squid_stop_monitor();
				if (is_service_running('squid')) {
					stop_service("squid");
				}
				rename($cachedir, "{$cachedir}.old");
				mwexec_bg("/bin/rm -rf {$cachedir}.old");
				squid_restart_services();
			} else {
				log_error("'Hard Disk Cache System' is set to null.");
				log_error("Will NOT delete Squid cache dir '{$cachedir}' since it is not located under /var/squid. Delete manually if required.");
			}
		}
		return;
	}

	// Re-create the cachedir if clean is forced by cronjob/manually,
	// or if the cachedir changed, or level1_subdirs don't exist or the number of level1_subdirs changed
	if ($cache_action == "clean" || ((!is_dir($cachedir)) || (!is_dir($cachedir . '/00'))) || ($numdirs !== $currentdirs)) {
		// cannot nuke disk cache while Squid is running
		squid_stop_monitor();
		if (is_service_running('squid')) {
			stop_service("squid");
		}
		if (is_dir($cachedir)) {
			if (substr($cachedir, 0, 11) === "/var/squid/") {
				rename($cachedir, "{$cachedir}.old");
				mwexec_bg("/bin/rm -rf {$cachedir}.old");
			} else {
				log_error("Will NOT delete Squid cache dir '{$cachedir}' since it is not located under /var/squid. Delete manually if required.");
			}
		}
		squid_create_cachedir();
		squid_restart_services();
	}
}

function squid_create_cachedir() {
	global $config;
	if (is_array($config['installedpackages']['squidcache'])) {
		$cachesettings = $config['installedpackages']['squidcache']['config'][0];
	} else {
		$cachesettings = array();
	}
	$cachedir = ($cachesettings['harddisk_cache_location'] ? $cachesettings['harddisk_cache_location'] : '/var/squid/cache');

	if (!is_dir($cachedir)) {
		log_error("Creating Squid cache dir {$cachedir}");
		safe_mkdir($cachedir, 0755);
		@chown($cachedir, SQUID_UID);
		@chgrp($cachedir, SQUID_GID);
	}

	if (!is_dir($cachedir . '/00')) {
		log_error("Creating Squid cache subdirs in $cachedir");
		// Double check permissions here, should be safe to recurse cache dir if it's small here.
		squid_chown_recursive($cachedir, SQUID_UID, SQUID_GID);
		mwexec(SQUID_BASE. "/sbin/squid -z -f " . SQUID_CONFFILE);
	}

	if (file_exists("/var/squid/cache/swap.state")) {
		chown("/var/squid/cache/swap.state", SQUID_UID);
		chgrp("/var/squid/cache/swap.state", SQUID_GID);
		chmod("/var/squid/cache/swap.state", 0666);
	}
}

function squid_is_valid_acl($acl) {
	global $valid_acls;

	if (!is_array($valid_acls)) {
		return;
	}

	return in_array($acl, $valid_acls);
}

function squid_install_command() {
	global $config, $g;

	update_status("Checking if there is configuration to migrate... One moment please...");
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

	if (SQUID_BASE != '/usr/local' && file_exists('/usr/local/bin/check_ip.php') && !file_exists(SQUID_BASE . '/bin/check_ip.php')) {
		symlink("/usr/local/bin/check_ip.php", SQUID_BASE . "/bin/check_ip.php");
	}

	/* Set storage system */
	if ($g['platform'] == "nanobsd") {
		$config['installedpackages']['squidcache']['config'][0]['harddisk_cache_system'] = 'null';
	}

	/* migrate auth settings */
	if (!empty($settingsauth['no_auth_hosts']) && strstr($settingsauth['no_auth_hosts'], ",")) {
		$settingsauth['no_auth_hosts'] = base64_encode(implode("\n", explode(",", $settingsauth['no_auth_hosts'])));
		$config['installedpackages']['squidauth']['config'][0]['no_auth_hosts'] = $settingsauth['no_auth_hosts'];
	}

	/* migrate cache settings */
	if (!empty($settingscache['donotcache']) && strstr($settingscache['donotcache'], ",")) {
		$settingscache['donotcache'] = base64_encode(implode("\n", explode(",", $settingscache['donotcache'])));
		$config['installedpackages']['squidcache']['config'][0]['donotcache'] = $settingscache['donotcache'];
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

	/* migrate reverse settings */
	if (is_array($config['installedpackages']['squidreverse'])) {
		$old_reverse_settings = $config['installedpackages']['squidreverse']['config'][0];

		// settings
		if (!is_array($config['installedpackages']['squidreversegeneral'])) {
			$config['installedpackages']['squidreversegeneral']['config'][0] = $old_reverse_settings;
			unset($config['installedpackages']['squidreversegeneral']['config'][0]['reverse_cache_peer']);
			unset($config['installedpackages']['squidreversegeneral']['config'][0]['reverse_uri']);
			unset($config['installedpackages']['squidreversegeneral']['config'][0]['reverse_acl']);
		}

		// peers
		if (!is_array($config['installedpackages']['squidreversepeer'])) {
			foreach (explode("\n", sq_text_area_decode($old_reverse_settings['reverse_cache_peer'])) as $cache_peers) {
				foreach (explode(";", $cache_peers) as $cache_peer) {
					$config['installedpackages']['squidreversepeer']['config'][] = array(
						'description' => 'migrated',
						'enable' => 'on',
						'name' => $cache_peer[0],
						'port' => $cache_peer[1],
						'protocol' => $cache_peer[2]
					);
				}
			}
		}

		// mappings
		if (!is_array($config['installedpackages']['squidreverseuri'])) {
			foreach (explode("\n", sq_text_area_decode($old_reverse_settings['reverse_acl'])) as $acls) {
				foreach (explode(";", $acls) as $acl) {
					array_push(${'peer_'.$acl[0]},$acl[1]);
				}
			}
			foreach (explode("\n", sq_text_area_decode($old_reverse_settings['reverse_uri'])) as $uris) {
				foreach (explode(";", $uris) as $uri) {
					$peer_list = (is_array(${'peer_' . $uri[0]}) ? implode(",", ${'peer_' . $uri[0]}) : "");
					$config['installedpackages']['squidreverseuri']['config'][] = array(
						'description' => 'migrated',
						'enable' => 'on',
						'name' => $uri[0],
						'uri' => $uri[1],
						'vhost' => $uri[2],
						'peers' => $peer_list
					);
				}
			}
		}
	}

	update_status("Writing configuration... One moment please...");
	write_config();

	/* make sure pinger is executable and suid root */
	// XXX: Bug #5114
	if (file_exists(SQUID_LOCALBASE . "/libexec/squid/pinger")) {
		chgrp(SQUID_LOCALBASE . "/libexec/squid/pinger", SQUID_GID);
	}

	// create squid rcfile
	squid_write_rcfile();

	// XXX: Is it really necessary? mode is set to 0755 in squid.xml
	if (file_exists("/usr/local/pkg/swapstate_check.php")) {
		@chmod("/usr/local/pkg/swapstate_check.php", 0755);
	}

	// create squid monitor rcfile
	write_rcfile(array(
		"file" => "sqp_monitor.sh",
		"start" => "/usr/local/pkg/sqpmon.sh &",
		"stop" => "/bin/ps awux | /usr/bin/grep \"sqpmon\" | /usr/bin/grep -v \"grep\" | /usr/bin/grep -v \"php\" | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill")
	);
	
	// create c-icap rcfile
	squid_write_cicap_rcfile();

	// make a backup of default c-icap config file on install; also see squid_resync_antivirus() function below
	if (!file_exists(SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.default")) {
		if (file_exists(SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.sample")) {
			copy(SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.sample", SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.default");
		}
	}

	foreach (array(SQUID_CONFBASE, SQUID_ACLDIR, SQUID_SSL_DB) as $dir) {
		safe_mkdir($dir, 0755);
		squid_chown_recursive($dir, SQUID_UID, SQUID_GID);
	}

	if (!file_exists(SQUID_CONFBASE . '/mime.conf') && file_exists(SQUID_CONFBASE . '/mime.conf.default')) {
		copy(SQUID_CONFBASE . '/mime.conf.default', SQUID_CONFBASE . '/mime.conf');
	}

}

function squid_deinstall_command() {
	global $config, $g;

	/* remove cronjobs */
	squid_install_cron(false);
	squid_install_freshclam_cron(false);

	update_status("Stopping services...");
	/* kill all running services */
	mwexec('/usr/local/etc/rc.d/sqp_monitor.sh stop');
	if (is_process_running("c-icap")) {
		mwexec('/bin/echo -n "stop" > /var/run/c-icap/c-icap.ctl');
	}
	mwexec("/bin/ps awux | /usr/bin/grep '[c]lamd' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");
	mwexec("/bin/ps awux | /usr/bin/grep '[f]reshclam' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");
	mwexec("/bin/ps awux | /usr/bin/grep '[s]quid' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");
	mwexec("/bin/ps awux | /usr/bin/grep '[d]nsserver' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");
	mwexec("/bin/ps awux | /usr/bin/grep '[u]nlinkd' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");
	/* delete rc scripts */
	unlink_if_exists('/usr/local/etc/rc.d/sqp_monitor.sh');
	unlink_if_exists('/usr/local/etc/rc.d/c-icap');
	unlink_if_exists("/usr/local/etc/rc.d/c-icap.sh");
	unlink_if_exists('/usr/local/etc/rc.d/clamav-clamd');
	unlink_if_exists('/usr/local/etc/rc.d/clamav-freshclam');

	/* clean up created directories */
	update_status("Removing cache and logs ... One moment please...");
	update_output_window("This operation may take quite some time, please be patient. Do not press stop or attempt to navigate away from this page during this process.");
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
	// XXX: Is it ok to remove cache and logs? It's going to happen every time package is updated
	if (is_dir("{$cachedir}")) {
		if (substr($cachedir, 0, 11) === "/var/squid/") {
			mwexec_bg("/bin/rm -rf {$cachedir}");
		} else {
			log_error("Will NOT delete Squid cache dir '{$cachedir}' since it is not located under /var/squid. Delete manually if required.");
		}
	}
	if (is_dir("{$logdir}")) {
		if (substr($logdir, 0, 11) === "/var/squid/") {
			mwexec("/bin/rm -rf {$logdir}");
		} else {
			log_error("Will NOT delete Squid log dir '{$logdir}' since it is not located under /var/squid. Delete manually if required.");
		}
	}
	$dirs = array("/var/run/c-icap", "/var/log/c-icap", "/var/log/clamav", "/var/run/clamav", "/var/db/clamav", "/var/run/squid", "/var/squid");
	foreach ($dirs as $dir) {
		if (is_dir("{$dir}")) {
			mwexec("/bin/rm -rf {$dir}");
		}
	}

	/* clean up created PBI symlinks */
	update_status("Finishing package cleanup.");
	if (SQUID_LOCALBASE != '/usr/local') {
		$ln_icap = array('bin/c-icap', 'bin/c-icap-client', 'c-icap-config', 'c-icap-libicapapi-config', 'c-icap-stretch', 'lib/c_icap', 'share/c_icap', 'etc/c-icap');
		foreach ($ln_icap as $ln) {
			if (is_link("/usr/local/{$ln}")) {
				unlink("/usr/local/{$ln}");
			}
		}
		if (is_link("/usr/local/lib/libicapapi.so.3")) {
			unlink("/usr/local/lib/libicapapi.so.3");
		}
	}

	/* check if clamav/c_icap is enabled in rc.conf.local */
	if (file_exists("/etc/rc.conf.local")) {
		update_status("Removing antivirus services from /etc/rc.conf.local...");
		$sample_file = file_get_contents("/etc/rc.conf.local");
		$rcconf_local_m[0] = "@c_icap_enable(.*)\n@";
		$rcconf_local_m[1] = "@clamav_clamd_enable(.*)\n@";
		$rcconf_local_r[0] = "";
		$rcconf_local_r[1] = "";
		file_put_contents("/etc/rc.conf.local", preg_replace($rcconf_local_m, $rcconf_local_r, $sample_file), LOCK_EX);
	}

	update_status("Reloading filter...");
	filter_configure();
}

function squid_validate_antivirus($post, &$input_errors) {
	global $config;

	/* Manual ClamAV database update */
	if ($post['submit'] == 'Update AV') {
		squid_update_clamav();
		return;
	}

	if ($post['enable'] != "on") {
		return;
	}

	if ($post['squidclamav'] && preg_match("/(\S+proxy.domain\S+)/", $post['squidclamav'], $a_match)) {
		$input_errors[] = "SquidClamav warnings redirect points to sample config domain ({$a_match[1]})";
		$input_errors[] = "Change redirect info on 'squidclamav.conf' field to pfSense GUI or an external host.";
	}
	if ($post['c-icap_conf']) {
		if (!preg_match("/squid_clamav/", $post['c-icap_conf'])) {
			$input_errors[] = "c-icap Squidclamav service definition is not present.";
			$input_errors[] = "Add 'Service squid_clamav squidclamav.so'(without quotes) to 'c-icap.conf' field in order to get it working.";
		}
		if (preg_match("/(Manager:Apassword\S+)/", $post['c-icap_conf'], $c_match)) {
			$input_errors[] = "Remove ldap configuration'{$c_match[1]}' from 'c-icap.conf' field.";
		}
	}

	if ($post['clamav_dbservers']) {
		foreach (explode(";", $post['clamav_dbservers']) as $dbserver) {
			$dbserver = trim($dbserver);
			if (!empty($dbserver) && !is_ipaddr($dbserver) && !is_hostname($dbserver)) {
				$input_errors[] = "'Optional ClamAV Database Update Servers' entry '$dbserver' is not a valid IP address or hostname.";
			}
		}
	}
}

function squid_validate_general($post, &$input_errors) {
	global $config;

	if (is_array($config['installedpackages']['squid'])) {
		$settings = $config['installedpackages']['squid']['config'][0];
	} else {
		$settings = array();
	}

	$port = ($settings['proxy_port'] ? $settings['proxy_port'] : 3128);
	$port = $post['proxy_port'] ? $post['proxy_port'] : $port;

	$icp_port = trim($post['icp_port']);
	if (!empty($icp_port) && !is_port($icp_port)) {
		$input_errors[] = 'You must enter a valid port number in the \'ICP port\' field.';
	}

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

	if (($post['ssl_proxy'] == 'on') && ( $post['dca'] == '')) {
		$input_errors[] = "SSL interception cannot be enabled without a CA.";
	}

	foreach (array('defined_ip_proxy_off') as $hosts) {
		foreach (explode(";", $post[$hosts]) as $host) {
			$host = trim($host);
			if (!empty($host) && !is_ipaddr($host) && !is_alias($host) && !is_hostname($host) && !is_subnet($host)) {
				$input_errors[] = "'Bypass proxy for these source IPs' entry '$host' is not a valid IP address, hostname, or alias.";
			}
		}
	}
	foreach (array('defined_ip_proxy_off_dest') as $hosts) {
		foreach (explode(";", $post[$hosts]) as $host) {
			$host = trim($host);
			if (!empty($host) && !is_ipaddr($host) && !is_alias($host) && !is_hostname($host) && !is_subnet($host)) {
				$input_errors[] = "'Bypass proxy for these destination IPs' entry '$host' is not a valid IP address, hostname, or alias.";
			}
		}
	}

	if (!empty($post['dns_nameservers'])) {
		$altdns = explode(";", ($post['dns_nameservers']));
		foreach ($altdns as $dnssrv) {
			if (!is_ipaddr($dnssrv)) {
				$input_errors[] = "You must enter a valid IP address in the 'Alternate DNS servers' field.";
				break;
			}
		}
	}
}

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
}

function squid_validate_cache($post, &$input_errors) {
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

	$value = trim($post['minimum_object_size']);
	if (!is_numericint($value)) {
		$input_errors[] = "You must enter a valid value for 'Minimum object size'.";
	}

	if (!empty($post['cache_swap_low'])) {
		$value = trim($post['cache_swap_low']);
		if (!is_numericint($value) || ($value > 100)) {
			$input_errors[] = "You must enter a valid value for 'Low-water-mark'.";
		}
	}

	if (!empty($post['cache_swap_high'])) {
		$value = trim($post['cache_swap_high']);
		if (!is_numericint($value) || ($value > 100)) {
			$input_errors[] = "You must enter a valid value for 'High-water-mark'.";
		}
	}

	if ($post['donotcache'] != "") {
		foreach (split("\n", $post['donotcache']) as $host) {
			$host = trim($host);
			if (!is_ipaddr($host) && !is_domain($host)) {
				$input_errors[] = "The host '$host' is not a valid IP or hostname.";
			}
		}
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

function squid_validate_nac($post, &$input_errors) {
	$allowed_subnets = explode("\n", $post['allowed_subnets']);
	foreach ($allowed_subnets as $subnet) {
		$subnet = trim($subnet);
		if (!empty($subnet) && !is_subnet($subnet)) {
			$input_errors[] = "The subnet '$subnet' is not a valid CIDR range.";
		}
	}

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
	}

	foreach (explode(",", $post['timelist']) as $time) {
		$time = trim($time);
		if (!empty($time) && !squid_is_timerange($time)) {
			$input_errors[] = "The time range '$time' is not a valid time range.";
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
}

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

	if (!empty($post['quick_abort_min'])) {
		$value = trim($post['quick_abort_min']);
		if ((!is_numericint($value)) && ($value !== -1)) {
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
}

function squid_validate_reverse($post, &$input_errors) {
	global $config;

	if (!empty($post['reverse_ip'])) {
		$reverse_ip = explode(";", ($post['reverse_ip']));
		foreach ($reverse_ip as $reip) {
			if (!is_ipaddr(trim($reip))) {
				$input_errors[] = "You must enter a valid IP address in the 'User-defined reverse-proxy IPs' field. '$reip' is invalid.";
			}
		}
	}

	$fqdn = trim($post['reverse_external_fqdn']);
	if (!empty($fqdn) && !is_domain($fqdn)) {
		$input_errors[] = "'External FQDN' field must contain a valid domain name.";
	}

	$port = trim($post['reverse_http_port']);
	preg_match("/(\d+)/", shell_exec("/sbin/sysctl net.inet.ip.portrange.reservedhigh"), $portrange);
	if (!empty($port) && !is_port($port)) {
		$input_errors[] = "'Reverse HTTP port' must contain a valid port number.";
	}
	if (!empty($port) && is_port($port) && $port <= $portrange[1]) {
		$input_errors[] = "'Reverse HTTP port' must contain a port number higher than net.inet.ip.portrange.reservedhigh sysctl value({$portrange[1]}).";
		$input_errors[] = "To listen on low ports, change portrange.reservedhigh sysctl value to 0 in system tunable options and restart Squid daemon.";
	}
	$port = trim($post['reverse_https_port']);
	if (!empty($port) && !is_port($port)) {
		$input_errors[] = "'Reverse HTTPS port' must contain a valid port number.";
	}
	if (!empty($port) && is_port($port) && $port <= $portrange[1]) {
		$input_errors[] = "'Reverse HTTPS port' must contain a port number higher than net.inet.ip.portrange.reservedhigh sysctl value({$portrange[1]}).";
		$input_errors[] = "To listen on low ports, change portrange.reservedhigh sysctl value to 0 in system tunable options and restart Squid daemon.";
	}
	if ($post['reverse_ssl_cert'] == 'none') {
		$input_errors[] = 'A valid certificate for the external interface must be selected';
	}

	if (($post['reverse_https'] != 'on') && ($post['reverse_owa'] == 'on')) {
		$input_errors[] = "You have to enable reverse HTTPS before enabling OWA support.";
	}

	if (!empty($post['reverse_owa_ip'])) {
		$reverse_owa_ip = explode(";", ($post['reverse_owa_ip']));
		foreach ($reverse_owa_ip as $reowaip) {
			if (!is_ipaddr(trim($reowaip))) {
				$input_errors[] = "You must enter a valid IP address in the 'CAS-Array / OWA frontend IP address' field. '$reowaip' is invalid.";
			}
		}
	}

	$contents = $post['reverse_cache_peer'];
	if (!empty($contents)) {
		$defs = explode("\r\n", ($contents));
		foreach ($defs as $def) {
			$cfg = explode(";", ($def));
			if (!is_ipaddr($cfg[1])) {
				$input_errors[] = "Please choose a valid IP in the cache peer configuration.";
			}
			if (!is_port($cfg[2])) {
				$input_errors[] = "Please choose a valid port in the cache peer configuration.";
			}
			if (($cfg[3] != 'HTTPS') && ($cfg[3] != 'HTTP')) {
				$input_errors[] = "Please choose HTTP or HTTPS in the cache peer configuration.";
			}
		}
	}
}

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
			case 'msnt':
				foreach (explode(",", trim($post['msnt_secondary'])) as $server) {
					if (!empty($server) && !is_ipaddr($server) && !is_domain($server)) {
						$input_errors[] = "The host '$server' is not a valid IP address or domain name";
					}
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
}

function squid_install_cron($should_install) {
	global $config;

	if (platform_booting()) {
		return;
	}

	parse_config(true);
	if (is_array($config['installedpackages']['squidcache'])) {
		$settings = $config['installedpackages']['squidcache']['config'][0];
	} else {
		$settings = array();
	}

	$cron_cmd = ($settings['clear_cache'] == 'on' ? "/usr/local/pkg/swapstate_check.php clean; " : "");
	$cron_cmd .= SQUID_BASE . "/sbin/squid -k rotate -f " . SQUID_CONFFILE;
	install_cron_job("{$cron_cmd}", $should_install, "0", "0", "*", "*", "*", "root");

	$swapstate_cmd = "/usr/local/pkg/swapstate_check.php clean; ";
	if ($should_install) {
		if ($settings['clear_cache'] == 'on' ) {
			install_cron_job("{$swapstate_cmd}", true, "*/360");
		} else {
			install_cron_job("{$swapstate_cmd}", false);
		}
	} else {
		install_cron_job("{$swapstate_cmd}", false);
	}
}

function squid_install_freshclam_cron($should_install) {
	global $config;
	
	if (platform_booting()) {
		return;
	}

	if (is_array($config['installedpackages']['squidantivirus'])) {
		$antivirus_config = $config['installedpackages']['squidantivirus']['config'][0];
	} else {
		$antivirus_config = array();
	}

	$freshclam_cmd = (SQUID_BASE . "/bin/freshclam --config-file=" . SQUID_BASE . "/etc/freshclam.conf");
	if ($should_install) {
		if ($antivirus_config['clamav_update'] != "0") {
			$minutes = ($antivirus_config['clamav_update'] * 60);
			install_cron_job("{$freshclam_cmd}", true, "*/{$minutes}", "*", "*", "*", "*", "clamav");
		} else {
			install_cron_job("{$freshclam_cmd}", false);
		}
	} else {
		install_cron_job("{$freshclam_cmd}", false);
	}
}

function squid_check_ca_hashes() {
	global $config, $g;

	// check certificates
	$cert_count = 0;
	if (is_dir(SQUID_LOCALBASE . '/share/certs')) {
		if ($handle = opendir(SQUID_LOCALBASE . '/share/certs')) {
			while (false !== ($file = readdir($handle))) {
				if (preg_match ("/\d+.0/",$file)) {
					$cert_count++;
				}
			}
			closedir($handle);
		}
	}
	if ($cert_count < 10) {
		conf_mount_rw();
		// create ca-root hashes from ca-root-nss package
		log_error("Creating root certificate bundle hashes from the Mozilla Project");
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
				// cert, key, version, cipher, options, clientca, cafile, capath, crlfile, dhparams, sslflags, sslcontext
				$crt_pk = SQUID_CONFBASE . "/serverkey.pem";
				$crt_capath = SQUID_LOCALBASE . "/share/certs/";
				file_put_contents($crt_pk, base64_decode($srv_cert['prv']) . base64_decode($srv_cert['crt']));
				$sslcrtd_children = ($settings['sslcrtd_children'] ? $settings['sslcrtd_children'] : 5);
				$ssl_interception .= "ssl-bump generate-host-certificates=on dynamic_cert_mem_cache_size=" . ($sslcrtd_children*2) . "MB cert={$crt_pk} capath={$crt_capath}\n";
				$interception_checks = "sslcrtd_program " . SQUID_LOCALBASE . "/libexec/squid/ssl_crtd -s " . SQUID_SSL_DB . " -M 4MB -b 2048\n";
				$interception_checks .= "sslcrtd_children {$sslcrtd_children}\n";
				$interception_checks .= "sslproxy_capath {$crt_capath}\n";
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
	$ssl_port = ($settings['ssl_proxy_port'] ? $settings['ssl_proxy_port'] : 3127);

	// Read assigned interfaces
	$real_ifaces = array();

	if ($settings['active_interface']) {
		$proxy_ifaces = explode(",", $settings['active_interface']);
	} else {
		$proxy_ifaces = array("lan");
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
				$real_ifaces[]=$s_iface_ip;
			}
		}
	} else {
		$ssl_ifaces = array();
	}

	// check all proxy interfaces selected
	foreach ($proxy_ifaces as $iface) {
		$iface_ip = squid_get_real_interface_address($iface);
		if ($iface_ip[0]) {
			$real_ifaces[] = $iface_ip;
			if (in_array($iface,$ssl_ifaces)) {
				$conf .= "http_port {$iface_ip[0]}:{$port} {$ssl_interception}\n";
			} else {
				$conf .= "http_port {$iface_ip[0]}:{$port}\n";
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
	$dns_v4_first = ($settings['dns_v4_first'] == "on" ? "on" : "off" );
	$piddir = "{$g['varrun_path']}/squid";
	$pidfile = "{$piddir}/squid.pid";
	if (!is_dir($piddir)) {
		safe_mkdir($piddir, 0755);
		squid_chown_recursive($piddir, SQUID_UID, 'wheel');
	}
	$language = ($settings['error_language'] ? $settings['error_language'] : 'en');
	$icondir = SQUID_CONFBASE . '/icons';
	$hostname = ($settings['visible_hostname'] ? $settings['visible_hostname'] : 'localhost');
	$email = ($settings['admin_email'] ? $settings['admin_email'] : 'admin@localhost');

	$logdir = ($settings['log_dir'] ? $settings['log_dir'] : '/var/squid/logs');
	if (!is_dir($logdir)) {
		log_error("Creating Squid log dir $logdir");
		safe_mkdir($logdir, 0755);
		squid_chown_recursive($logdir, SQUID_UID, SQUID_GID);
	}
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
			$mask = 32-log((ip2long($mask) ^ ip2long('255.255.255.255'))+1,2);
			if (!preg_match("@$ip/$mask@", $src)) {
				$src .= " $ip/$mask";
			}
		}
		$conf .= "# Allow local network(s) on interface(s)\n";
		$conf .= "acl localnet src $src\n";
		$valid_acls[] = 'localnet';
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
		if ($g['platform'] == "nanobsd" || !is_array ($config['installedpackages']['squidcache']['config'])) {
			$disk_cache_system = 'null';
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
	} elseif (preg_match('/youtube/', $settings['refresh_patterns'])) {
// Broken (Bug #3847) and not working (http://wiki.squid-cache.org/ConfigExamples/DynamicContent/YouTube#Discussion)
/*		$conf .= <<< EOC
# Break HTTP standard for flash videos. Keep them in cache even if asked not to.
refresh_pattern -i \.flv$ 10080 90% 999999 ignore-no-cache override-expire ignore-private

# Let the clients favorite video site through with full caching
acl youtube dstdomain .youtube.com
cache allow youtube

EOC;
*/
	}
	if (preg_match('/windows/', $settings['refresh_patterns'])) {
		$conf .= <<< EOC

# Windows Update refresh_pattern
range_offset_limit -1
refresh_pattern -i microsoft.com/.*\.(cab|exe|ms[i|u|f]|asf|wm[v|a]|dat|zip) 4320 80% 43200 reload-into-ims
refresh_pattern -i windowsupdate.com/.*\.(cab|exe|ms[i|u|f]|asf|wm[v|a]|dat|zip) 4320 80% 43200 reload-into-ims
refresh_pattern -i windows.com/.*\.(cab|exe|ms[i|u|f]|asf|wm[v|a]|dat|zip) 4320 80% 43200 reload-into-ims

EOC;
	}

	if (preg_match('/symantec/', $settings['refresh_patterns'])) {
		$conf .= <<< EOC

# Symantec refresh_pattern
range_offset_limit -1
refresh_pattern liveupdate.symantecliveupdate.com/.*\.(cab|exe|dll|msi) 10080 100% 43200 reload-into-ims
refresh_pattern symantecliveupdate.com/.*\.(cab|exe|dll|msi) 10080 100% 43200 reload-into-ims

EOC;
	}
	if (preg_match('/avast/', $settings['refresh_patterns'])) {
		$conf .= <<< EOC

# Avast refresh_pattern
range_offset_limit -1
refresh_pattern avast.com/.*\.(vpu|cab|stamp|exe) 10080 100% 43200 reload-into-ims

EOC;
	}
	if (preg_match('/avira/', $settings['refresh_patterns'])) {
		$conf.=<<< EOC

# Avira refresh_pattern
range_offset_limit -1
refresh_pattern personal.avira-update.com/.*\.(cab|exe|dll|msi|gz) 10080 100% 43200 reload-into-ims

EOC;
	}
	$refresh_conf = <<< EOC

# Add any of your own refresh_pattern entries above these.
refresh_pattern ^ftp:    1440  20%  10080
refresh_pattern ^gopher:  1440  0%  1440
refresh_pattern -i (/cgi-bin/|\?) 0  0%  0
refresh_pattern .    0  20%  4320

EOC;

	if ($settings['custom_refresh_patterns'] != "") {
		$conf .= sq_text_area_decode($settings['custom_refresh_patterns']) . "\n";
	}

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

function squid_resync_redirector() {
	global $config;

	// XXX: What port provide squirm binary? It's not present
	$httpav_enabled = ($config['installedpackages']['clamav']['config'][0]['scan_http'] == 'on');
	$redirector = "/usr/local/bin/squirm";
	if (($httpav_enabled) && is_executable($redirector)) {
		$conf = "url_rewrite_program /usr/local/bin/squirm\n";
	} else {
		$conf = "# No redirector configured\n";
	}
	return $conf;
}

function squid_resync_nac() {
	global $config, $valid_acls;

	$port = ($settings['proxy_port'] ? $settings['proxy_port'] : 3128);
	if (is_array($config['installedpackages']['squidnac'])) {
		$settings = $config['installedpackages']['squidnac']['config'][0];
	} else {
		$settings = array();
	}
	$webgui_port = $config['system']['webgui']['port'];
	$addtl_ports = $settings['addtl_ports'];
	$addtl_sslports = $settings['addtl_sslports'];
	$port = ($settings['proxy_port'] ? $settings['proxy_port'] : 3128);
	$ssl_port = ($settings['ssl_proxy_port'] ? $settings['ssl_proxy_port'] : 3127);
	$conf = <<< EOD

# Setup some default acls
# From 3.2 further configuration cleanups have been done to make things easier and safer. The manager, localhost, and to_localhost ACL definitions are now built-in.
# acl localhost src 127.0.0.1/32
acl allsrc src all
acl safeports port 21 70 80 210 280 443 488 563 591 631 777 901 $webgui_port $port $ssl_port 1025-65535 $addtl_ports
acl sslports port 443 563 $webgui_port $addtl_sslports

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

function squid_resync_antivirus() {
	global $config;

	if (is_array($config['installedpackages']['squidantivirus'])) {
		$antivirus_config = $config['installedpackages']['squidantivirus']['config'][0];
	} else {
		$antivirus_config = array();
	}

	if ($antivirus_config['enable'] == "on") {
		switch ($antivirus_config['client_info']) {
			case "both":
			default:
				$icap_send_client_ip = "on";
				$icap_send_client_username = "on";
				break;
			case "ip":
				$icap_send_client_ip = "on";
				$icap_send_client_username = "off";
				break;
			case "username":
				$icap_send_client_ip = "off";
				$icap_send_client_username = "on";
				break;
			case "none":
				$icap_send_client_ip = "off";
				$icap_send_client_username = "off";
				break;
		}

		$conf = <<< EOF
icap_enable on
icap_send_client_ip {$icap_send_client_ip}
icap_send_client_username {$icap_send_client_username}
icap_client_username_encode off
icap_client_username_header X-Authenticated-User
icap_preview_enable on
icap_preview_size 1024

icap_service service_avi_req reqmod_precache icap://[::1]:1344/squid_clamav bypass=off
adaptation_access service_avi_req allow all
icap_service service_avi_resp respmod_precache icap://[::1]:1344/squid_clamav bypass=on
adaptation_access service_avi_resp allow all

EOF;

		// check if clamav is enabled in rc.conf.local
		// XXX: This whole thing sucks and should be redone to install/enable services in pfSense way
		if (file_exists("/etc/rc.conf.local")) {
			$rc_old_file = file("/etc/rc.conf.local");
			foreach ($rc_old_file as $rc_line) {
				if (preg_match("/^clamav_clamd_enable/", $rc_line, $matches)) {
					$rc_file .= $matches[1] . '="YES"' . "\n";
					${$matches[1]} = "ok";
				} else {
					$rc_file .= $rc_line;
				}
			}
		}
		if (!isset($clamav_clamd_enable)) {
			$rc_file .= 'clamav_clamd_enable="YES"' . "\n";
		}
		file_put_contents("/etc/rc.conf.local", $rc_file, LOCK_EX);
		squid_check_clamav_user('clamav');
		// patch sample files to pfsense dirs
		// squidclamav.conf
		if (file_exists(SQUID_LOCALBASE . "/etc/c-icap/squidclamav.conf.default")) {
			$sample_file = file_get_contents(SQUID_LOCALBASE . "/etc/c-icap/squidclamav.conf.default");
			$clamav_m[0] = "@/var/run/clamav/clamd.ctl@";
			$clamav_m[1] = "@http\://proxy.domain.dom/cgi-bin/clwarn.cgi@";
			$clamav_r[0] = "/var/run/clamav/clamd.sock";
			$clamav_r[1] = "{$config['system']['webgui']['protocol']}://{$config['system']['hostname']}.{$config['system']['domain']}/squid_clwarn.php";
			if ($antivirus_config['clamav_safebrowsing'] == "on") {
				$clamav_m[2] = "@safebrowsing\s0@";
				$clamav_r[2] = "safebrowsing 1";
			}
			file_put_contents(SQUID_LOCALBASE . "/etc/c-icap/squidclamav.conf.sample", preg_replace($clamav_m, $clamav_r, $sample_file), LOCK_EX);
		}
		// c-icap.conf
		// make a backup of default c-icap.conf.sample first
		// unlike with other config files, the file distributed in package is called c-icap.conf.sample, not c-icap.conf.default
		if (!file_exists(SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.default")) {
			copy(SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.sample", SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.default");
		}
		if (file_exists(SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.default")) {
			$sample_file = file_get_contents(SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.default");
			if (!preg_match("/squid_clamav/", $sample_file)) {
				$sample_file .= "\nService squid_clamav squidclamav.so\n";
			}
			$cicap_m[0] = "@Manager:Apassword\S+@";
			$cicap_r[0] = "";
			// XXX: Bug #4615
			if (is_array($config['installedpackages']['squid'])) {
				$squidsettings = $config['installedpackages']['squid']['config'][0];
			} else {
				$squidsettings = array();
			}
			$logdir = ($squidsettings['log_dir'] ? $squidsettings['log_dir'] : '/var/squid/logs');
			$cicap_m[1] = "@DebugLevel\s1@";
			$cicap_r[1] = "DebugLevel 0";
			$cicap_m[2] = "@AccessLog /var/log/c-icap/access.log@";
			$cicap_r[2] = "AccessLog $logdir/c-icap-access.log";
			$cicap_m[3] = "@ServerLog /var/log/c-icap/server.log@";
			$cicap_r[3] = "ServerLog $logdir/c-icap-server.log";
			file_put_contents(SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.sample", preg_replace($cicap_m, $cicap_r, $sample_file), LOCK_EX);
		}
		// freshclam.conf
		// make a backup of default freshclam.conf.sample first
		if (!file_exists(SQUID_LOCALBASE . "/etc/freshclam.conf.default")) {
			copy(SQUID_LOCALBASE . "/etc/freshclam.conf.sample", SQUID_LOCALBASE . "/etc/freshclam.conf.default");
		}
		if (file_exists(SQUID_LOCALBASE . "/etc/freshclam.conf.default")) {
			$sample_file = file_get_contents(SQUID_LOCALBASE . "/etc/freshclam.conf.default");
			$freshclam_m[0] = "@#Example@";
			$freshclam_r[0] = "";
			$clamav_mirrors = "";
			if ($antivirus_config['clamav_dbregion'] != "") {
				$clamav_mirrors .= "DatabaseMirror db.{$antivirus_config['clamav_dbregion']}.clamav.net\n";
			}
			if ($antivirus_config['clamav_dbservers'] != "") {
				foreach (explode(";", $antivirus_config['clamav_dbservers']) as $dbserver) {
					$clamav_mirrors .= "DatabaseMirror {$dbserver}\n";
				}
			}
			if ($clamav_mirrors != "") {
				$freshclam_m[1] = "@#DatabaseMirror db.XY.clamav.net@";
				$freshclam_r[1] = "{$clamav_mirrors}";
			}
			if ($antivirus_config['clamav_safebrowsing'] == "on") {
				$freshclam_m[2] = "@#SafeBrowsing yes@";
				$freshclam_r[2] = "SafeBrowsing yes";
			}
			file_put_contents(SQUID_LOCALBASE . "/etc/freshclam.conf.sample", preg_replace($freshclam_m, $freshclam_r, $sample_file), LOCK_EX);
		}
		// freshclam cronjob
		squid_install_freshclam_cron(true);

		// check squidclamav files until PBIs are gone (https://redmine.pfsense.org/issues/4197)
		$ln_icap = array('bin/c-icap', 'bin/c-icap-client', 'c-icap-config', 'c-icap-libicapapi-config', 'c-icap-stretch', 'lib/c_icap', 'share/c_icap', 'etc/c-icap');
		foreach ($ln_icap as $ln) {
			if (SQUID_LOCALBASE != '/usr/local' && !file_exists("/usr/local/{$ln}") && file_exists(SQUID_LOCALBASE . "/{$ln}")) {
				symlink(SQUID_LOCALBASE . "/{$ln}", "/usr/local/{$ln}");
			}
		}
		if (SQUID_LOCALBASE != '/usr/local' && !file_exists("/usr/local/lib/libicapapi.so.3") && file_exists(SQUID_LOCALBASE . "/lib/libicapapi.so.3.0.5")) {
			symlink(SQUID_LOCALBASE . "/lib/libicapapi.so.3.0.5", "/usr/local/lib/libicapapi.so.3");
		}

		$loadsample = 0;
		if ($antivirus_config['squidclamav'] == "" && file_exists(SQUID_LOCALBASE . "/etc/c-icap/squidclamav.conf.sample")) {
			$config['installedpackages']['squidantivirus']['config'][0]['squidclamav'] = base64_encode(str_replace("\r", "", file_get_contents(SQUID_LOCALBASE . "/etc/c-icap/squidclamav.conf.sample")));
			$loadsample++;
		}
		if ($antivirus_config['c-icap_conf'] == "" && file_exists(SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.sample")) {
			$config['installedpackages']['squidantivirus']['config'][0]['c-icap_conf'] = base64_encode(str_replace("\r", "", file_get_contents(SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf.sample")));
			$loadsample++;
		}
		if ($antivirus_config['c-icap_magic'] == "" && file_exists(SQUID_LOCALBASE . "/etc/c-icap/c-icap.magic.sample")) {
			$config['installedpackages']['squidantivirus']['config'][0]['c-icap_magic'] = base64_encode(str_replace("\r", "", file_get_contents(SQUID_LOCALBASE . "/etc/c-icap/c-icap.magic.sample")));
			$loadsample++;
		}
		if ($antivirus_config['freshclam_conf'] == "" && file_exists(SQUID_LOCALBASE . "/etc/freshclam.conf.sample")) {
			$config['installedpackages']['squidantivirus']['config'][0]['freshclam_conf'] = base64_encode(str_replace("\r", "", file_get_contents(SQUID_LOCALBASE . "/etc/freshclam.conf.sample")));
			$loadsample++;
		}
		if ($loadsample > 0) {
			write_config();
			$antivirus_config = $config['installedpackages']['squidantivirus']['config'][0];
		}
		// check dirs
		$dirs = array(
			"/var/run/c-icap" => "clamav",
			"/var/log/c-icap" => "clamav",
			"/var/log/clamav" => "clamav",
			"/var/run/clamav" => "clamav",
			"/var/db/clamav" => "clamav"
		);
		foreach ($dirs as $dir_path => $dir_user) {
			safe_mkdir($dir_path, 0755);
			squid_chown_recursive($dir_path, $dir_user, "wheel");
		}

		$rcd_files = scandir(SQUID_LOCALBASE."/etc/rc.d");
		foreach ($rcd_files as $rcd_file) {
			if (SQUID_LOCALBASE != '/usr/local' && !file_exists("/usr/local/etc/rc.d/{$rcd_file}")) {
				symlink(SQUID_LOCALBASE . "/etc/rc.d/{$rcd_file}", "/usr/local/etc/rc.d/{$rcd_file}");
			}
		}

		// write advanced clamav/icap config files
		file_put_contents(SQUID_LOCALBASE . "/etc/c-icap/squidclamav.conf", base64_decode($antivirus_config['squidclamav']), LOCK_EX);
		file_put_contents(SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf", base64_decode($antivirus_config['c-icap_conf']), LOCK_EX);
		file_put_contents(SQUID_LOCALBASE . "/etc/c-icap/c-icap.magic", base64_decode($antivirus_config['c-icap_magic']), LOCK_EX);
		file_put_contents(SQUID_LOCALBASE . "/etc/freshclam.conf", base64_decode($antivirus_config['freshclam_conf']), LOCK_EX);

		// Check clamav database
		if (count(glob("/var/db/clamav/*d")) == 0) {
			log_error("Squid - Missing /var/db/clamav/*.cvd or *.cld files. Running freshclam in background.");
			mwexec_bg(SQUID_BASE . "/bin/freshclam --config-file=" . SQUID_BASE . "/etc/freshclam.conf");
		} elseif (($antivirus_config['clamav_safebrowsing'] == "on") && (!is_file("/var/db/clamav/safebrowsing.cvd"))) {
			log_error("Squid - Google Safe Browsing is enabled but missing safebrowsing.cvd definitions. Running freshclam in background.");
			mwexec_bg(SQUID_BASE . "/bin/freshclam --config-file=" . SQUID_BASE . "/etc/freshclam.conf");
		}

		// check antivirus daemons
		// check icap
		$c_icap_rcfile = "/usr/local/etc/rc.d/c-icap.sh";
		if (!file_exists($c_icap_rcfile)) {
			squid_write_cicap_rcfile();
		}
		if (is_process_running("c-icap")) {
			mwexec_bg('/bin/echo -n "reconfigure" > /var/run/c-icap/c-icap.ctl');
		} else {
			mwexec_bg("{$c_icap_rcfile} start");
		}
		// check clamav/freshclam
		$rc_files = array("clamav-freshclam", "clamav-clamd");
		$clamm[0] = "@/usr/local/(bin|sbin)@";
		$clamm[1] = "@/local/(bin|sbin)@";
		$clamm[2] = "@/usr/local/etc@";
		$clamm[3] = "@enable:=NO@";
		$clamr[0] = SQUID_BASE . "/bin";
		$clamr[1] = "/bin";
		$clamr[2] = SQUID_LOCALBASE . "/etc";
		$clamr[3] = "enable:=YES";
		foreach ($rc_files as $rc_file) {
			$clamav_rcfile = "/usr/local/etc/rc.d/{$rc_file}";
			if (file_exists($clamav_rcfile)) {
				$sample_file = file_get_contents($clamav_rcfile);
				file_put_contents($clamav_rcfile, preg_replace($clamm, $clamr, $sample_file), LOCK_EX);
			}
		}
		if (is_process_running("clamd")) {
			mwexec_bg("/usr/local/etc/rc.d/clamav-clamd reload");
		} else {
			mwexec_bg("/usr/local/etc/rc.d/clamav-clamd start");
		}
	} else {
		// stop AV services and disable all C-ICAP/AV features
		log_error("Squid antivirus features disabled.");
		if (is_process_running("clamd")) {
			log_error("Stopping ClamAV...");
			mwexec("/bin/ps awux | /usr/bin/grep '[c]lamd' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");
		}
		if (is_process_running("c-icap")) {
			log_error("Stopping C-ICAP...");
			mwexec_bg("/usr/local/etc/rc.d/c-icap.sh stop");
		}
		// freshclam cronjob
		log_error("Removing freshclam cronjob...");
		squid_install_freshclam_cron(false);

		// check if clamav is enabled in rc.conf.local
		// XXX: This whole thing sucks and should be redone to install/enable services in pfSense way
		if (file_exists("/etc/rc.conf.local")) {
			log_error("Removing antivirus services from /etc/rc.conf.local...");
			$sample_file = file_get_contents("/etc/rc.conf.local");
			$rcconf_local_m[0] = "@clamav_clamd_enable(.*)\n@";
			$rcconf_local_r[0] = "";
			file_put_contents("/etc/rc.conf.local", preg_replace($rcconf_local_m, $rcconf_local_r, $sample_file), LOCK_EX);
		}
	}

	return $conf;
}

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
		$conf .= "delay_access 1 allow allsrc\n";
	}

	return $conf;
}

function squid_get_server_certs() {
	global $config;
	$cert_arr = array();
	$cert_arr[] = array('refid' => 'none', 'descr' => 'none');
	foreach ($config['cert'] as $cert) {
		$cert_arr[] = array('refid' => $cert['refid'], 'descr' => $cert['descr']);
	}
	return $cert_arr;
}

// squid reverse
include('/usr/local/pkg/squid_reverse.inc');

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
		$conf .= "# Setup allowed acls\n";
		$allowed = array('allowed_subnets');
		if ($settingsconfig['allow_interface'] == 'on') {
			$conf .= "# Allow local network(s) on interface(s)\n";
			$allowed[] = "localnet";
		}
		$allowed = array_filter($allowed, 'squid_is_valid_acl');
		foreach ($allowed as $acl) {
			$conf .= "http_access allow $acl\n";
		}
	} else {
		$noauth = implode(' ', explode("\n", base64_decode($settings['no_auth_hosts'])));
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
				$port = (isset($settings['auth_server_port']) ? ":{$settings['auth_server_port']}" : '');
				$password = (isset($settings['ldap_pass']) ? "-w {$settings['ldap_pass']}" : '');
				$conf .= "auth_param basic program " . SQUID_LOCALBASE . "/libexec/squid/basic_ldap_auth -v {$settings['ldap_version']} -b {$settings['ldap_basedomain']} -D {$settings['ldap_user']} $password -f \"{$settings['ldap_filter']}\" -u {$settings['ldap_userattribute']} -P {$settings['auth_server']}$port\n";
				break;
			case 'radius':
				$port = (isset($settings['auth_server_port']) ? "-p {$settings['auth_server_port']}" : '');
				$conf .= "auth_param basic program ". SQUID_LOCALBASE . "/libexec/squid/basic_radius_auth -w {$settings['radius_secret']} -h {$settings['auth_server']} $port\n";
				break;
			case 'cp':
				$conf .= "external_acl_type check_cp children-startup={$processes} ttl={$auth_ttl} %SRC " . SQUID_BASE . "/bin/check_ip.php\n";
				$conf .= "acl password external check_cp\n";
				break;
			case 'msnt':
				$conf .= "auth_param basic program " . SQUID_LOCALBASE . "/libexec/squid/basic_msnt_auth\n";
				squid_resync_msnt();
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

function squid_resync_users() {
	global $config;

	$users = $config['installedpackages']['squidusers']['config'];
	$contents = '';
	if (is_array($users)) {
		foreach ($users as $user) {
			$contents .= $user['username'] . ':' . crypt($user['password'], base64_encode($user['password'])) . "\n";
		}
	}
	file_put_contents(SQUID_PASSWD, $contents);
	chown(SQUID_PASSWD, SQUID_UID);
	chmod(SQUID_PASSWD, 0600);
}

function squid_resync_msnt() {
	global $config;

	if (is_array($config['installedpackages']['squidauth'])) {
		$settings = $config['installedpackages']['squidauth']['config'][0];
	} else {
		$settings = array();
	}
	$pdcserver = $settings['auth_server'];
	$bdcserver = str_replace(',', ' ', $settings['msnt_secondary']);
	$ntdomain = $settings['auth_ntdomain'];

	file_put_contents(SQUID_CONFBASE . "/msntauth.conf", "server {$pdcserver} {$bdcserver} {$ntdomain}");
	chown(SQUID_CONFBASE . "/msntauth.conf", SQUID_UID);
	chmod(SQUID_CONFBASE . "/msntauth.conf", 0600);
}

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

	log_error("[Squid] - Squid_resync function call pr:" . is_process_running('squid') . " bp:" . isset($boot_process) . " rpc:" . $via_rpc);

	if (is_process_running('squid') && isset($boot_process) && $via_rpc == "no") {
		return;
	}

	conf_mount_rw();
	foreach (array(SQUID_CONFBASE, SQUID_ACLDIR, SQUID_SSL_DB) as $dir) {
		safe_mkdir($dir, 0755);
		squid_chown_recursive($dir, SQUID_UID, SQUID_GID);
	}
	$conf = squid_resync_general() . "\n";
	$conf .= squid_resync_cache() . "\n";
	$conf .= squid_resync_redirector() . "\n";
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

function squid_stop_monitor() {
	/* kill any running proxy alarm scripts */
	if (exec("/bin/ps auxw | /usr/bin/grep '[s]qpmon'")) {
		log_error("Stopping any running proxy monitors");
		mwexec("/usr/local/etc/rc.d/sqp_monitor.sh stop");
	}
	sleep(1);
}

function squid_start_monitor() {
	if (!exec("/bin/ps auxw | /usr/bin/grep '[s]qpmon'")) {
		log_error("Starting a proxy monitor script");
		mwexec_bg("/usr/local/etc/rc.d/sqp_monitor.sh start");
	}
	sleep(1);
}

function squid_restart_services() {
	global $config;
	// reconfigure and (re)start service as needed if enabled, otherwise stop them
	// do not (re)start squid services on boot
	if (platform_booting()) {
		return;
	}
	$squid_enabled = false;
	if (is_array($config['installedpackages']['squid']['config'])) {
		// check if Squid is enabled
		if ($config['installedpackages']['squid']['config'][0]['active_interface'] != "") {
			$squid_enabled = true;
		}
	} elseif (is_array($config['installedpackages']['squidreversegeneral']['config'])) {
		// check if squidreverse is enabled
		if ($config['installedpackages']['squidreversegeneral']['config'][0]['reverse_interface'] != "") {
			$squid_enabled = true;
		}
	}

	if ($squid_enabled) {
		/* kill any running proxy alarm scripts */
		squid_stop_monitor();

		if (!is_service_running('squid')) {
			log_error("Starting Squid");
			mwexec(SQUID_BASE . "/sbin/squid -f " . SQUID_CONFFILE);
		} else {
			log_error("Reloading Squid for configuration sync");
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
			log_error("Stopping Squid");
			stop_service("squid");
		}
	}
}

function squid_print_javascript_auth() {
	global $config;
	$transparent_proxy = ($config['installedpackages']['squid']['config'][0]['transparent_proxy'] == 'on');

	// No authentication for transparent proxy
	if ($transparent_proxy and preg_match("/(local|ldap|radius|msnt|ntlm)/",$config['installedpackages']['squidauth']['config'][0]['auth_method'])) {
		$javascript = <<< EOD
<script type="text/javascript">
<!--
function on_auth_method_changed() {
	document.iform.auth_method.disabled = 1;
	document.iform.auth_server.disabled = 1;
	document.iform.auth_ntdomain.disabled = 1;
	document.iform.auth_server_port.disabled = 1;
	document.iform.ldap_user.disabled = 1;
	document.iform.ldap_version.disabled = 1;
	document.iform.ldap_userattribute.disabled = 1;
	document.iform.ldap_filter.disabled = 1;
	document.iform.ldap_pass.disabled = 1;
	document.iform.ldap_basedomain.disabled = 1;
	document.iform.radius_secret.disabled = 1;
	document.iform.msnt_secondary.disabled = 1;
	document.iform.auth_prompt.disabled = 1;
	document.iform.auth_processes.disabled = 1;
	document.iform.auth_ttl.disabled = 1;
	document.iform.unrestricted_auth.disabled = 1;
	document.iform.no_auth_hosts.disabled = 1;
}
-->
</script>

EOD;
	} else {
		$javascript = <<< EOD
<script type="text/javascript">
<!--
function on_auth_method_changed() {
	var field = document.iform.auth_method;
	var auth_method = field.options[field.selectedIndex].value;

	if (auth_method == 'none') {
		document.iform.auth_server.disabled = 1;
		document.iform.auth_server_port.disabled = 1;
		document.iform.auth_ntdomain.disabled = 1;
		document.iform.ldap_user.disabled = 1;
		document.iform.ldap_version.disabled = 1;
		document.iform.ldap_userattribute.disabled = 1;
		document.iform.ldap_filter.disabled = 1;
		document.iform.ldap_pass.disabled = 1;
		document.iform.ldap_basedomain.disabled = 1;
		document.iform.radius_secret.disabled = 1;
		document.iform.msnt_secondary.disabled = 1;
		document.iform.auth_prompt.disabled = 1;
		document.iform.auth_processes.disabled = 1;
		document.iform.auth_ttl.disabled = 1;
		document.iform.unrestricted_auth.disabled = 1;
		document.iform.no_auth_hosts.disabled = 1;
	} else {
		document.iform.auth_prompt.disabled = 0;
		document.iform.auth_processes.disabled = 0;
		document.iform.auth_ttl.disabled = 0;
		document.iform.unrestricted_auth.disabled = 0;
		document.iform.no_auth_hosts.disabled = 0;
	}

	switch (auth_method) {
	case 'local':
		document.iform.auth_server.disabled = 1;
		document.iform.auth_server_port.disabled = 1;
		document.iform.auth_ntdomain.disabled = 1;
		document.iform.ldap_user.disabled = 1;
		document.iform.ldap_pass.disabled = 1;
		document.iform.ldap_version.disabled = 1;
		document.iform.ldap_userattribute.disabled = 1;
		document.iform.ldap_filter.disabled = 1;
		document.iform.ldap_basedomain.disabled = 1;
		document.iform.radius_secret.disabled = 1;
		document.iform.msnt_secondary.disabled = 1;
		break;
	case 'ldap':
		document.iform.auth_server.disabled = 0;
		document.iform.auth_server_port.disabled = 0;
		document.iform.ldap_user.disabled = 0;
		document.iform.ldap_pass.disabled = 0;
		document.iform.ldap_version.disabled = 0;
		document.iform.ldap_userattribute.disabled = 0;
		document.iform.ldap_filter.disabled = 0;
		document.iform.ldap_basedomain.disabled = 0;
		document.iform.radius_secret.disabled = 1;
		document.iform.msnt_secondary.disabled = 1;
		document.iform.auth_ntdomain.disabled = 1;
		break;
	case 'radius':
		document.iform.auth_server.disabled = 0;
		document.iform.auth_server_port.disabled = 0;
		document.iform.ldap_user.disabled = 1;
		document.iform.ldap_pass.disabled = 1;
		document.iform.ldap_version.disabled = 1;
		document.iform.ldap_userattribute.disabled = 1;
		document.iform.ldap_filter.disabled = 1;
		document.iform.ldap_basedomain.disabled = 1;
		document.iform.radius_secret.disabled = 0;
		document.iform.msnt_secondary.disabled = 1;
		document.iform.auth_ntdomain.disabled = 1;
		break;
	case 'msnt':
		document.iform.auth_server.disabled = 0;
		document.iform.auth_server_port.disabled = 1;
		document.iform.auth_ntdomain.disabled = 0;
		document.iform.ldap_user.disabled = 1;
		document.iform.ldap_pass.disabled = 1;
		document.iform.ldap_version.disabled = 1;
		document.iform.ldap_userattribute.disabled = 1;
		document.iform.ldap_filter.disabled = 1;
		document.iform.ldap_basedomain.disabled = 1;
		document.iform.radius_secret.disabled = 1;
		document.iform.msnt_secondary.disabled = 0;
		break;
	case 'cp':
		document.iform.auth_server.disabled = 1;
		document.iform.auth_server_port.disabled = 1;
		document.iform.auth_ntdomain.disabled = 1;
		document.iform.ldap_user.disabled = 1;
		document.iform.ldap_version.disabled = 1;
		document.iform.ldap_userattribute.disabled = 1;
		document.iform.ldap_filter.disabled = 1;
		document.iform.ldap_pass.disabled = 1;
		document.iform.ldap_basedomain.disabled = 1;
		document.iform.radius_secret.disabled = 1;
		document.iform.msnt_secondary.disabled = 1;
		document.iform.auth_prompt.disabled = 1;
		document.iform.auth_processes.disabled = 0;
		document.iform.auth_ttl.disabled = 0;
		document.iform.unrestricted_auth.disabled = 1;
		document.iform.no_auth_hosts.disabled = 1;
		break;
	}
}
-->
</script>

EOD;
	}

	print($javascript);
}

function squid_print_javascript_auth2() {
	print("<script type=\"text/javascript\">on_auth_method_changed()</script>\n");
}

function squid_generate_rules($type) {
	global $config, $pfs_version;

	$squid_conf = $config['installedpackages']['squid']['config'][0];
	//check captive portal option
	$cp_file = '/etc/inc/captiveportal.inc';
	$port = ($settings['proxy_port'] ? $settings['proxy_port'] : 3128);
	$cp_inc = file($cp_file);
	$new_cp_inc = "";
	$found_rule=0;
	foreach ($cp_inc as $line) {
		$new_line = $line;
		//remove applied squid patch
		if (preg_match('/skipto 65314 ip/', $line)) {
			$found_rule++;
			$new_line = "";
		}

		if (preg_match('/255.255.255.255/', $line) && $squid_conf['patch_cp']) {
			$found_rule++;
			$new_line .= "\t" . '$cprules .= "add {$rulenum} skipto 65314 ip from any to {$ips} ' . $port . ' in\n";' . "\n";
			$new_line .= "\t" . '$cprules .= "add {$rulenum} skipto 65314 ip from {$ips} ' . $port . ' to any out\n";' . "\n";
		}
		$new_cp_inc .= $new_line;
	}
	if (!file_exists('/root/' . $pfs_version . '.captiveportal.inc.backup')) {
		copy($cp_file, '/root/' . $pfs_version . '.captiveportal.inc.backup');
	}
	if ($found_rule > 0) {
		file_put_contents($cp_file, $new_cp_inc, LOCK_EX);
	}
	// normal squid rule check
	if (($squid_conf['transparent_proxy'] != 'on') || ($squid_conf['allow_interface'] != 'on')) {
		return;
	}

	if (!is_service_running('squid')) {
		log_error("Squid is installed but not started. Not installing \"{$type}\" rules.");
		return;
	}
	// Read assigned interfaces
	$proxy_ifaces = explode(",", $squid_conf['active_interface']);
	$proxy_ifaces = array_map('convert_friendly_interface_to_real_interface_name', $proxy_ifaces);
	if ($squid_conf['transparent_proxy'] == "on") {
		$transparent_ifaces = explode(",", $squid_conf['transparent_active_interface']);
		$transparent_ifaces = array_map('convert_friendly_interface_to_real_interface_name', $transparent_ifaces);
	} else {
		$transparent_ifaces=array();
	}
	if ($squid_conf['ssl_proxy'] == "on") {
		$ssl_ifaces = explode(",", $squid_conf['ssl_active_interface']);
		$ssl_ifaces = array_map('convert_friendly_interface_to_real_interface_name', $ssl_ifaces);
	} else {
		$ssl_ifaces=array();
	}

	$port = ($squid_conf['proxy_port'] ? $squid_conf['proxy_port'] : 3128);
	$ssl_port = ($squid_conf['ssl_proxy_port'] ? $squid_conf['ssl_proxy_port'] : 3127);

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
				/* Handle PPTP case */
				if ($config['pptpd']['mode'] == "server" && $config['pptpd']['localip']) {
					$rules .= "no rdr on $PPTP_ALIAS proto tcp from any to { 192.168.0.0/16, 172.16.0.0/12, 10.0.0.0/8 } port {$pf_rule_port}\n";
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
				/* Handle PPTP case */
				if ($config['pptpd']['mode'] == "server" && $config['pptpd']['localip']) {
					$rules .= "no rdr on $PPTP_ALIAS proto tcp from { $exempt_ip } to any port {$pf_rule_port}\n";
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
				/* Handle PPTP case */
				if ($config['pptpd']['mode'] == "server" && $config['pptpd']['localip']) {
					$rules .= "no rdr on $PPTP_ALIAS proto tcp from any to { $exempt_dest } port {$pf_rule_port}\n";
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
			/* Handle PPTP case */
			if ($config['pptpd']['mode'] == "server" && $config['pptpd']['localip']) {
				$rules .= "rdr on $PPTP_ALIAS proto tcp from any to !127.0.0.1 port {$pf_rule_port} -> 127.0.0.1 port {$port}\n";
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
			if ($config['pptpd']['mode'] == "server" && $config['pptpd']['localip']) {
				$rules .= "pass in quick on $PPTP_ALIAS proto tcp from any to !127.0.0.1 port {$port} flags S/SA keep state\n";
			}
			break;
		default:
			break;
	}

	return $rules;
}

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
	$rc['restart'] = <<< EOD
if [ -z "`ps auxw | /usr/bin/grep "[s]quid " | /usr/bin/awk '{print $2}'`" ]; then
		{$squid_base}/sbin/squid -f {$squid_conffile_var}
	else
		{$squid_base}/sbin/squid -k reconfigure -f {$squid_conffile_var}
	fi

EOD;
	conf_mount_rw();
	write_rcfile($rc);
	// force delete the PBI initscript that keeps creeping back
	unlink_if_exists("/usr/local/etc/rc.d/squid");
	conf_mount_ro();
}

function squid_write_cicap_rcfile() {
	$c_icap_rcfile = "c-icap.sh";
	$cicap_libdir = SQUID_LOCALBASE . "/lib";
	$cicap_bin = SQUID_LOCALBASE . "/bin/c-icap";
	$cicap_conf = SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf";
	$cicap_start_cmd = "LD_LIBRARY_PATH={$cicap_libdir} {$cicap_bin} -f {$cicap_conf}";
	$cicap_stop_cmd = '/bin/echo -n "stop" > /var/run/c-icap/c-icap.ctl';
	conf_mount_rw();
	write_rcfile(array(
		"file" => "{$c_icap_rcfile}",
		"start" => "{$cicap_start_cmd}",
		"stop" => "{$cicap_stop_cmd}"
		)
	);
	// force delete the PBI initscript that keeps creeping back
	unlink_if_exists("/usr/local/etc/rc.d/c-icap");
	conf_mount_ro();
}

/* Uses XMLRPC to synchronize the changes to a remote node */
function squid_sync_on_changes() {
	global $config, $g;
	if (is_array($config['installedpackages']['squidsync']['config'])) {
		$squid_sync = $config['installedpackages']['squidsync']['config'][0];
		$synconchanges = $squid_sync['synconchanges'];
		$synctimeout = $squid_sync['synctimeout'];
		switch ($synconchanges) {
			case "manual":
				if (is_array($squid_sync['row'])) {
					$rs = $squid_sync['row'];
				} else {
					log_error("[squid] XMLRPC sync is enabled but there is no hosts to push on Squid config.");
					return;
				}
				break;
			case "auto":
				if (is_array($config['installedpackages']['carpsettings']) && is_array($config['installedpackages']['carpsettings']['config'])) {
					$system_carp = $config['installedpackages']['carpsettings']['config'][0];
					$rs[0]['ipaddress'] = $system_carp['synchronizetoip'];
					$rs[0]['username'] = $system_carp['username'];
					$rs[0]['password'] = $system_carp['password'];
				} else {
					log_error("[squid] XMLRPC sync is enabled but there is no system backup hosts to push Squid config.");
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
				$sync_to_ip = $sh['ipaddress'];
				$password = $sh['password'];
				if ($sh['username']) {
					$username = $sh['username'];
				} else {
					$username = 'admin';
				}
				if ($password && $sync_to_ip) {
					squid_do_xmlrpc_sync($sync_to_ip, $username, $password, $synctimeout);
				}
			}
			log_error("[squid] XMLRPC sync is ending.");
		}
	}
}
/* Do the actual XMLRPC sync */
function squid_do_xmlrpc_sync($sync_to_ip, $username, $password, $synctimeout) {
	global $config, $g;

	if (!$username) {
		return;
	}

	if (!$password) {
		return;
	}

	if (!$sync_to_ip) {
		return;
	}

	if (!$synctimeout) {
		$synctimeout = 250;
	}


	$xmlrpc_sync_neighbor = $sync_to_ip;
	if ($config['system']['webgui']['protocol'] != "") {
		$synchronizetoip = $config['system']['webgui']['protocol'];
		$synchronizetoip .= "://";
	}
	$port = $config['system']['webgui']['port'];
	/* If port is empty let's rely on the protocol selection */
	if ($port == "") {
		if ($config['system']['webgui']['protocol'] == "http")
			$port = "80";
		else
			$port = "443";
	}
	$synchronizetoip .= $sync_to_ip;

	/* XML will hold the sections to sync */
	$xml = array();
	$xml['squid'] = $config['installedpackages']['squid'];
	$xml['squidupstream'] = $config['installedpackages']['squidupstream'];
	$xml['squidcache'] = $config['installedpackages']['squidcache'];
	$xml['squidantivirus'] = $config['installedpackages']['squidantivirus'];
	$xml['squidnac'] = $config['installedpackages']['squidnac'];
	$xml['squidtraffic'] = $config['installedpackages']['squidtraffic'];
	$xml['squidreversegeneral'] = $config['installedpackages']['squidreversegeneral'];
	$xml['squidreversepeer'] = $config['installedpackages']['squidreversepeer'];
	$xml['squidreverseuri'] = $config['installedpackages']['squidreverseuri'];
	$xml['squidauth'] = $config['installedpackages']['squidauth'];
	$xml['squidusers'] = $config['installedpackages']['squidusers'];
	/* Assemble XMLRPC payload */
	$params = array(
		XML_RPC_encode($password),
		XML_RPC_encode($xml)
	);

	/* Set a few variables needed for sync */
	$url = $synchronizetoip;
	log_error("[squid] Beginning Squid XMLRPC sync to {$url}:{$port}.");
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
		$error = "A communication error occurred while attempting Squid XMLRPC sync with {$url}:{$port}.";
		log_error($error);
		file_notice("sync_settings", $error, "Squid Settings Sync", "");
	} elseif ($resp->faultCode()) {
		$cli->setDebug(1);
		$resp = $cli->send($msg, $synctimeout);
		$error = "An error code was received while attempting Squid XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
		log_error($error);
		file_notice("sync_settings", $error, "Squid Settings Sync", "");
	} else {
		log_error("[squid] XMLRPC sync successfully completed with {$url}:{$port}.");
	}

	/* Tell Squid to reload our settings on the destination sync host. */
	$method = 'pfsense.exec_php';
	$execcmd = "require_once('/usr/local/pkg/squid.inc');\n";
	$execcmd .= "squid_resync('yes');";
	/* Assemble XMLRPC payload */
	$params = array(
		XML_RPC_encode($password),
		XML_RPC_encode($execcmd)
	);

	log_error("[squid] XMLRPC reload data {$url}:{$port}.");
	$msg = new XML_RPC_Message($method, $params);
	$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
	$cli->setCredentials($username, $password);
	$resp = $cli->send($msg, $synctimeout);
	if (!$resp) {
		$error = "A communication error occurred while attempting Squid XMLRPC sync with {$url}:{$port} (pfsense.exec_php).";
		log_error($error);
		file_notice("sync_settings", $error, "Squid Settings Sync", "");
	} elseif ($resp->faultCode()) {
		$cli->setDebug(1);
		$resp = $cli->send($msg, $synctimeout);
		$error = "[Squid] An error code was received while attempting Squid XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
		log_error($error);
		file_notice("sync_settings", $error, "Squid Settings Sync", "");
	} else {
		log_error("Squid XMLRPC reload data success with {$url}:{$port} (pfsense.exec_php).");
	}
}

?>
