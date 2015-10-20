<?php
/*
	lightsquid.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2006-2012 Sergey Dvoriancev <dv_serg@mail.ru>
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
require_once('filter.inc');
require_once('service-utils.inc');

if (file_exists('/usr/local/pkg/squid.inc')) {
	require_once('/usr/local/pkg/squid.inc');
} else {
	echo "No squid.inc found. You must have Squid/Squid3 package installed to use LightSquid.";
}

global $pfs_version;
$pfs_version = substr(trim(file_get_contents("/etc/version")), 0, 3);
switch ($pfs_version) {
	case "2.1":
		define('LIGHTSQUID_BASE', '/usr/pbi/lightsquid-' . php_uname("m"));
		break;
	case "2.2":
		define('LIGHTSQUID_BASE', '/usr/pbi/lightsquid-' . php_uname("m") . '/local');
		break;
	default:
		define('LIGHTSQUID_BASE', '/usr/local');
		break;
}

// configuration settings !-- CHECK THIS --!
define('LS_CONFIGPATH',			LIGHTSQUID_BASE . '/etc/lightsquid');
define('LS_CONFIGFILE',			'lightsquid.cfg');
define('LS_CONFIGFILE_DIST',		'lightsquid.cfg.dist');
define('LS_WWWPATH',			LIGHTSQUID_BASE . '/www/lightsquid');
define('LS_TEMPLATEPATH',		LS_WWWPATH . '/tpl');
define('LS_LANGPATH',			LIGHTSQUID_BASE . '/share/lightsquid/lang');
define('LS_REPORTPATH',			'/var/lightsquid/report');

global $config;
if (is_array($config['installedpackages']['squid']['config'][0]) && $config['installedpackages']['squid']['config'][0]['log_dir'] != "") {
	define('LS_SQUIDLOGPATH',	$config['installedpackages']['squid']['config'][0]['log_dir']);
} else {
	define('LS_SQUIDLOGPATH',	'/var/squid/logs');
}
		
define('LS_SQUIDLOG',			'access.log');
define('LS_IP2NAMEPATH',		LIGHTSQUID_BASE . '/libexec/lightsquid');
define('CRONTAB_LS_TEMPLATE',		'/usr/bin/perl ' . LIGHTSQUID_BASE . '/www/lightsquid/lightparser.pl');

// default values
define('LS_DEF_IP2NAME',		'dns');
define('LS_DEF_SQUIDLOGTYPE',		'0');
define('LS_DEF_SKIPURL',		'zzz\.zzz');
define('LS_DEF_LANG',			'eng');
define('LS_DEF_TEMPLATE',		'base');
define('LS_DEF_BARCOLOR',		'orange');

// variable names
define('LS_VAR_CFGPATH',		'cfgpath');
define('LS_VAR_LOGPATH',		'logpath');
define('LS_VAR_TPLPATH',		'tplpath');
define('LS_VAR_LANGPATH',		'langpath');
define('LS_VAR_LANG',			'lang');
define('LS_VAR_REPORTPATH',		'reportpath');
define('LS_VAR_SQUIDLOGTYPE',		'squidlogtype');
define('LS_VAR_SKIPURL',		'skipurl');
define('LS_VAR_IP2NAMEPATH',		'ip2namepath');
define('LS_VAR_IP2NAME',		'ip2name');
define('LS_VAR_TEMPLATE',		'templatename');
define('LS_VAR_BARCOLOR',		'barcolor');

// XML GUI variables
define('LS_XML_LANG',			'lightsquid_lang');
define('LS_XML_SKIPURL',		'lightsquid_skipurl');
define('LS_XML_IP2NAME',		'lightsquid_ip2name');
define('LS_XML_TEMPLATE',		'lightsquid_template');
define('LS_XML_BARCOLOR',		'lightsquid_barcolor');
define('LS_XML_SHEDULERTIME',		'lightsquid_refreshsheduler_time');

/*
 * Package install/uninstall
 */

function lightsquid_install() {
	// create lightsquid reports directory
	lightsquid_create_reportdir();

	// ugly PBI hacks
	if (LIGHTSQUID_BASE != '/usr/local') {
		// check and fix perl paths
		lightsquid_fix_perl();

		if (!is_dir('/usr/local/etc/lightsquid') && is_dir(LS_CONFIGPATH)) {
			symlink(LS_CONFIGPATH, '/usr/local/etc/lightsquid');
		}

		if (is_dir('/usr/local/www/lightsquid'))
			$_gc = exec('rm -rf /usr/local/www/lightsquid');

		if (is_dir(LS_WWWPATH)) {
			symlink(LS_WWWPATH, '/usr/local/www/lightsquid');
		}
	}

	// template symlinks
	foreach (array('novopf', 'novosea') as $tpl) {
		if (is_dir(LS_TEMPLATEPATH . '/' . $tpl)) {
			$_gc = exec('rm -rf ' . LS_TEMPLATEPATH . '/' . $tpl);
		}
		symlink('/usr/local/share/lightsquid/tpl/' . $tpl, LS_TEMPLATEPATH . '/' . $tpl);
	}
	symlink("/usr/local/www/themes", "/usr/local/www/sqstat/themes");
}

function lightsquid_deinstall() {
	// remove cronjobs
	lightsquid_setup_cron(false);
	// undo PBI hacks
	lightsquid_unfix_perl();
	if (is_dir("/usr/local/www/sqstat/")) {
		mwexec("/bin/rm -rf /usr/local/www/sqstat/");
	}
}

/*
 * Ugly PBI hacks around perl paths; only needed for pfSense <2.3
 */
/* Create perl links on package install */
function lightsquid_fix_perl() {
	if (LIGHTSQUID_BASE != '/usr/local') {
		/* Clean up a broken perl link first if needed. */
		$perl_path = '/usr/bin/perl';
		if (file_exists($perl_path) && is_link($perl_path)) {
			$target = readlink($perl_path);
			if (!file_exists($target) || !is_executable($target)) {
				unlink($target);
			}
		}
		/* Find usable perl and create perl symlink to $perl_path */
		if (!file_exists($perl_path)) {
			if (is_executable("/usr/local/bin/perl")) {
				symlink("/usr/local/bin/perl", "{$perl_path}");
			} elseif (is_executable(LIGHTSQUID_BASE . "/bin/perl")) {
				symlink(LIGHTSQUID_BASE . "/bin/perl", "{$perl_path}");
			}
		}
		if (!is_dir("/usr/local/lib/perl5") && is_dir(LIGHTSQUID_BASE . "/lib/perl5")) {
			symlink(LIGHTSQUID_BASE . "/lib/perl5", "/usr/local/lib/perl5");
		}
	}
}
/* Remove perl links on package uninstall */
function lightsquid_unfix_perl() {
	if (LIGHTSQUID_BASE != '/usr/local') {
		$perl_path = '/usr/bin/perl';
		if (file_exists($perl_path) && is_link($perl_path)) {
			$target = readlink($perl_path);
			$ls_target = LIGHTSQUID_BASE . "/bin/perl";
			if ($target === $ls_target) {
				unlink($perl_path);
			}
		}
		$perl_libpath = "/usr/local/lib/perl5";
		if (is_dir($perl_libpath) && is_link($perl_libpath)) {
			$target = readlink($perl_libpath);
			$ls_target = LIGHTSQUID_BASE . "/lib/perl5";
			if ($target === $ls_target) {
				unlink($perl_libpath);
			}
		}
	}
}

/*
 * Package configuration routines
 */
function lightsquid_resync() {
	global $config, $pfs_version;

	// Ugly PBI hacks
	if (LIGHTSQUID_BASE != '/usr/local') {
		// check perl paths
		if (!file_exists("/usr/bin/perl")) {
			lightsquid_fix_perl();
		}

		// Fixup library path so GD can find its libraries for graphs.
		mwexec("/sbin/ldconfig -m " . LIGHTSQUID_BASE . "/lib/");
	}

	lightsquid_create_reportdir();
	mwexec("/bin/chmod -R u+w " . LIGHTSQUID_BASE . "/etc/lightsquid");

	// Set up variables for configuration update
	$lsconf_var = array();
	$lsconf_var[LS_VAR_CFGPATH]	= "\"" . LS_CONFIGPATH . "\"";
	$lsconf_var[LS_VAR_LOGPATH]	= "\"" . LS_SQUIDLOGPATH . "\"";

	$lsconf_var[LS_VAR_TPLPATH]	= "\"" . LS_TEMPLATEPATH . "\"";
	$lsconf_var[LS_VAR_LANGPATH]	= "\"" . LS_LANGPATH . "\"";
	$lsconf_var[LS_VAR_REPORTPATH]	= "\"" . LS_REPORTPATH . "\"";
	$lsconf_var[LS_VAR_IP2NAMEPATH]	= "\"" . LS_IP2NAMEPATH . "\"";

	$lsconf_var[LS_VAR_LANG]	= "\"" . LS_DEF_LANG . "\"";
	$lsconf_var[LS_VAR_TEMPLATE]	= "\"" . LS_DEF_TEMPLATE . "\"";
	$lsconf_var[LS_VAR_IP2NAME]	= "\"" . LS_DEF_IP2NAME . "\"";
	$lsconf_var[LS_VAR_SKIPURL]	= "'" . LS_DEF_SKIPURL . "'";
	$lsconf_var[LS_VAR_SQUIDLOGTYPE]= LS_DEF_SQUIDLOGTYPE;

	// Update variables from package GUI config
	if (is_array($config['installedpackages']['lightsquid']['config'][0])) {
		$lightsquid_config = $config['installedpackages']['lightsquid']['config'][0];

		if (isset($lightsquid_config[LS_XML_LANG]) and !empty($lightsquid_config[LS_XML_LANG])) {
			$lsconf_var[LS_VAR_LANG] = "\"" . $lightsquid_config[LS_XML_LANG] . "\"";
		}

		if (isset($lightsquid_config[LS_XML_SKIPURL]) and !empty($lightsquid_config[LS_XML_SKIPURL])) {
			$lsconf_var[LS_VAR_SKIPURL] = "'" . str_replace(".", "\\.", $lightsquid_config[LS_XML_SKIPURL]) . "'";
		}

		if (isset($lightsquid_config[LS_XML_IP2NAME]) and !empty($lightsquid_config[LS_XML_IP2NAME] )) {
			$lsconf_var[LS_VAR_IP2NAME] = "\"" . $lightsquid_config[LS_XML_IP2NAME] . "\"";
		}

		if (isset($lightsquid_config[LS_XML_TEMPLATE]) and !empty($lightsquid_config[LS_XML_TEMPLATE])) {
			$tpl_val = $lightsquid_config[LS_XML_TEMPLATE];
			// check template path
			if (!file_exists(LS_TEMPLATEPATH."/$tpl_val")) {
				$tpl_val = 'base';
			}
			$lsconf_var[LS_VAR_TEMPLATE] = "\"" . $tpl_val . "\"";
		}

		if (isset($lightsquid_config[LS_XML_BARCOLOR]) and !empty($lightsquid_config[LS_XML_BARCOLOR])) {
			$lsconf_var[LS_VAR_BARCOLOR] = "\"" . $lightsquid_config[LS_XML_BARCOLOR] . "\"";
		}
	}

	// Create or update lightsquid.cfg
	$lsconf = "";
	$lsconf_file = LS_CONFIGPATH . "/" . LS_CONFIGFILE;
	// Always use the lightsquid.cfg.dist template to avoid issues with GUI values reconfiguration
	$lsconf_dist_file = LS_CONFIGPATH . "/" . LS_CONFIGFILE_DIST;
	if (file_exists($lsconf_dist_file)) {
		$lsconf = file_get_contents($lsconf_dist_file);
		log_error("[lightsquid] Loaded default '{$lsconf_dist_file}' configuration file.");
	} else {
		log_error("[lightsquid] Error: Could not load default '{$lsconf_dist_file}' configuration file.");
	}

	// Update lightsquid.cfg
	if (!empty($lsconf)) {
		$lsconf = explode("\n", $lsconf);
		foreach ($lsconf_var as $key => $val) {
			for ($i = 0; $i < count($lsconf); $i++) {
				$s = trim($lsconf[$i]);
				$e_key = "/^[$]" . $key . "[ ]*[=]+/i";
				if (preg_match($e_key, $s)) {
					$lsconf[$i] = '$' . "$key = $val;";
				}
			}
		}

		$lsconf = implode("\n", $lsconf);
		if (file_put_contents($lsconf_file, $lsconf)) {
			log_error("[lightsquid] Successfully created '{$lsconf_file}' configuration file.");
		} else {
			log_error("[lightsquid] Error: Could not create '{$lsconf_file}' configuration file.");
		}
	} else {
		log_error("[lightsquid] Error: Could not create '{$lsconf_file}' configuration file.");
	}

	// Set up scheduled reports updates
	lightsquid_setup_cron(true);
}

/*
 * Reports
 */

/* Configure scheduled reports updates via cron */
function lightsquid_setup_cron($active=false) {
	global $config;
	if (is_array($config['installedpackages']['lightsquid']['config'][0])) {
		$cron_schedule = $config['installedpackages']['lightsquid']['config'][0][LS_XML_SHEDULERTIME];
	} else {
		$cron_schedule = '';
	}
	$lightsquid_parser_today = CRONTAB_LS_TEMPLATE . " today";
	$lightsquid_parser_yesterday = CRONTAB_LS_TEMPLATE . " yesterday";

	if ($active && $cron_schedule) {
		$on = false;
		$opt = array("*", "*", "*", "*", "*", "root");
		// remove old cronjobs first ...
		log_error("[lightsquid] Removing old cronjobs...");
		install_cron_job($lightsquid_parser_today, false);
		install_cron_job($lightsquid_parser_yesterday, false);
		// ... and configure updated cronjobs if needed
		switch($cron_schedule) {
			case 'lhp_none': $on = false; break;
			case 'lhp_10m':  $on = true; $opt[0]= "*/10"; break;
			case 'lhp_20m':  $on = true; $opt[0]= "*/20"; break;
			case 'lhp_30m':  $on = true; $opt[0]= "*/30"; break;
			case 'lhp_40m':  $on = true; $opt[0]= "*/40"; break;
			case 'lhp_50m':  $on = true; $opt[0]= "*/50"; break;
			case 'lhp_60m':  $on = true; $opt[0]= "*/60"; break;
			case 'lhp_2h':   $on = true; $opt[0]= "0"; $opt[1]= "*/2";  break;
			case 'lhp_3h':   $on = true; $opt[0]= "0"; $opt[1]= "*/3";  break;
			case 'lhp_4h':   $on = true; $opt[0]= "0"; $opt[1]= "*/4";  break;
			case 'lhp_6h':   $on = true; $opt[0]= "0"; $opt[1]= "*/6";  break;
			case 'lhp_8h':   $on = true; $opt[0]= "0"; $opt[1]= "*/8";  break;
			case 'lhp_12h':  $on = true; $opt[0]= "0"; $opt[1]= "*/12"; break;
			case 'lhp_24h':  $on = true; $opt[0]= "45"; $opt[1]= "23";   break; // daily at 23:45
		}
		if ($on) {
			log_error("[lightsquid] Updating cronjobs...");
			install_cron_job($lightsquid_parser_today, $on, $opt[0], $opt[1], $opt[2], $opt[3], $opt[4], $opt[5]);
			// fix possible data lost with 00:00 script start - rescan yesterday
			install_cron_job($lightsquid_parser_yesterday, true, "15", "0", "*", "*", "*", "root");
		}
	} else {
		log_error("[lightsquid] Removing all cronjobs...");
		install_cron_job($lightsquid_parser_today, false);
		install_cron_job($lightsquid_parser_yesterday, false);
	}
}

/* Parse today's entires only in access.log via the GUI button */
function lightsquid_refresh_now() {
	$cmd = CRONTAB_LS_TEMPLATE . " today";
	$lg = LS_SQUIDLOG;
	lightsquid_create_reportdir();

	if (file_exists(LS_SQUIDLOGPATH . "/{$lg}")) {
		log_error("[lightsquid] Parsing today's entries in access.log using '{$cmd}'");
		mwexec_bg($cmd);
	} else {
		log_error("[lightsquid] Could not parse '{$lg}' - log does not exist!");
	}
}

/* Parse all entries in all access logs, including the rotated ones via the GUI button */
function lightsquid_refresh_full() {
	$cmd = CRONTAB_LS_TEMPLATE;
	lightsquid_create_reportdir();

	log_error("[lightsquid] Parsing all access log(s) entries using '{$cmd}'...");
	// parse access.log
	$lg = LS_SQUIDLOG;
	if (file_exists(LS_SQUIDLOGPATH . "/{$lg}")) {
		log_error("[lightsquid] Parsing log entries in '{$lg}'...");
		mwexec_bg("{$cmd} {$lg}");
	} else {
		log_error("[lightsquid] Could not parse '{$lg}' - log does not exist!");
	}
	// parse access.log.x; if user configured some insane amount of rotated logs, only parse the first 100 of them
	for ($i = 0; $i < 100; $i++) {
		$lg = LS_SQUIDLOG . ".{$i}";
		if (file_exists(LS_SQUIDLOGPATH . "/{$lg}")) {
			log_error("[lightsquid] Parsing log entries in '{$lg}'...");
			mwexec_bg("{$cmd} {$lg}");
		} else {
			break;
		}
	}
}

/* Helper function to create lightsquid reports directory if needed */
function lightsquid_create_reportdir() {
	if (!is_dir(LS_REPORTPATH)) {
		log_error("[lightsquid] Creating report dir " . LS_REPORTPATH);
		safe_mkdir(LS_REPORTPATH);
	}
}


/*
 * Input validation
 * Check required Squid configuration and provide instructions to users,
 * instead of trying to mess with Squid's settings directly.
 */
function lightsquid_validate_input($post, &$input_errors) {
	global $config;
	/* Manual reports refresh; only allow to run if the configuration file exists already! */
	if ($post['refreshnow'] == 'Refresh now' || $post['refreshfull'] == 'Refresh full') {
		$lsconf_file = LS_CONFIGPATH . "/" . LS_CONFIGFILE;
		if (file_exists($lsconf_file)) {
			if ($post['refreshnow'] == 'Refresh now') {
				lightsquid_refresh_now();
				return;
			} elseif ($post['refreshfull'] == 'Refresh full') {
				lightsquid_refresh_full();
				return;
			}
		} else {
			$input_errors[] = "Please, configure LightSquid package first and Save settings before trying to manually refresh reports.";
		}
	}

	/* Check whether Squid is configured at all */
	if (is_array($config['installedpackages']['squid']['config'][0])) {
		$squid_settings = $config['installedpackages']['squid']['config'][0];
	} else {
		$input_errors[] = "Please, configure Squid package 'General' settings first.";
		$squid_settings = array();
	}

	/* Check whether logging is enabled in Squid */
	if ($squid_settings['log_enabled'] != "on") {
		$input_errors[] = "Please, enable Access Logging in Squid package 'General' settings first.";
	}

	/* Check whether log directory is configured in Squid */
	if ($squid_settings['log_dir'] == "") {
		$input_errors[] = "Please, configure Log Store Directory in Squid package 'General' settings first.";
	}

	/* SqStat - check external cache managers
	 * This check is only needed for Squid 2.7 packages, any Squid 3.x package allows localhost as cache manager by default
	 */
	$ifmgr = "127.0.0.1";
	if (get_pkg_id("squid") !== -1) {
		if (is_array($config['installedpackages']['squidnac']['config'])) {
			$ext_cachemanager = ($config['installedpackages']['squidnac']['config'][0]['ext_cachemanager'] ?: "");
		} else {
			$ext_cachemanager = "";
		}
		if (strpos($ext_cachemanager, $ifmgr) === false) {
			$input_errors[] = "Please, add '{$ifmgr}' to Squid - Access Control - External Cache-Managers first.";
		}
	}
	/* SqStat - check that Squid listens on loopback unless the proxy is set as transparent (which makes it listen on localhost automatically) */
	if (is_array($config['installedpackages']['squid']['config'])) {
		$active_interfaces = ($config['installedpackages']['squid']['config'][0]['active_interface'] ?: "");
		$transparent = ($config['installedpackages']['squid']['config'][0]['transparent_proxy'] == "on" ? true : false);
	} else {
		$active_interfaces = "";
		$transparent = false;
	}
	if (!$transparent) {
		if (strpos($active_interfaces, "lo0") === false) {
			$input_errors[] = "Please, configure Squid - General - Proxy Interface(s) to include 'loopback' interface.";
		}
	}
	
	/* 'Skip URL(s)' validation */
	if ($post['lightsquid_skipurl'] != "") {
		$hosts = explode("|", $post['lightsquid_skipurl']);
		foreach ($hosts as $host) {
			$host = trim($host);
			if (!is_ipaddr($host) && !is_hostname($host) && !is_domain($host)) {
				$input_errors[] = "'Skip URL(s)' entry '{$host}' is not a valid IP address, hostname, domain or subnet.";
			}
		}
	}
}

?>
