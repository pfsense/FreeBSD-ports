<?php
/*
	lightsquid.inc
	Copyright (C) 2006 Serg Dvorianceev
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

if (file_exists('squid.inc')) {
  require_once('squid.inc');
}
else update_log("File 'squid.inc' not found.");

global $pfs_version;
$pfs_version = substr(trim(file_get_contents("/etc/version")),0,3);
switch ($pfs_version) {
	case "2.1":
		define('LIGHTSQUID_BASE', '/usr/pbi/lightsquid-' . php_uname("m"));
		break;
	case "2.2":
		define('LIGHTSQUID_BASE', '/usr/pbi/lightsquid-' . php_uname("m") . '/local');
		break;
	default:
		define('LIGHTSQUID_BASE','/usr/local');
		break;
}

define ('CMD_PKGDELETE', 'pkg_delete lightsquid-1.7.1');

// enable GUI debug
define('LS_GUI_DEBUG', 'on');
define('LS_LOG_FILE',  '/tmp/lightsquid_gui.log');

// configuration settings !-- CHECK THIS --!
define('LS_CONFIGPATH',			LIGHTSQUID_BASE . '/etc/lightsquid');
define('LS_CONFIGFILE',			'lightsquid.cfg');
define('LS_CONFIGFILE_DIST',		'lightsquid.cfg.dist');
define('LS_WWWPATH',			LIGHTSQUID_BASE . '/www/lightsquid');
define('LS_TEMPLATEPATH',		LS_WWWPATH . '/tpl');
define('LS_LANGPATH',			LIGHTSQUID_BASE . '/share/lightsquid/lang');
define('LS_REPORTPATH',			'/var/lightsquid/report');

global $config;
if (isset($config['installedpackages']['squid']['config'][0])) {
	if (!empty($config['installedpackages']['squid']['config'][0]['log_dir']))
		define('LS_SQUIDLOGPATH', $config['installedpackages']['squid']['config'][0]['log_dir']);
	else
		define('LS_SQUIDLOGPATH', '/var/squid/logs');
}
define('LS_SQUIDLOG',			'access.log');
define('LS_IP2NAMEPATH',		LIGHTSQUID_BASE . '/libexec/lightsquid');

define('CRONTAB_FILE',			'/var/cron/tabs/root');
define('CRONTAB_LS_TEMPLATE',		'/usr/bin/perl ' . LIGHTSQUID_BASE . '/www/lightsquid/lightparser.pl');
define('CRONTAB_LS_JOBKEY',		'/lightparser.pl');
define('CRONTAB_SQUID_TEMPLATE',	'/usr/local/sbin/squid -k rotate > /dev/null');
define('CRONTAB_SQUID_JOBKEY',		'/squid -k rotate');

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

// xml variables
define('LS_XML_LANG',			'lightsquid_lang');
define('LS_XML_SKIPURL',		'lightsquid_skipurl');
define('LS_XML_IP2NAME',		'lightsquid_ip2name');
define('LS_XML_TEMPLATE',		'lightsquid_template');
define('LS_XML_BARCOLOR',		'lightsquid_barcolor');
define('LS_XML_SHEDULERTIME',		'lightsquid_refreshsheduler_time');
define('LS_XML_SQUID_SHEDULERTIME',	'lightsquid_squidrotatelog_sheduler_time');

function lightsquid_install() {
	global $pfs_version;

	update_log("lightsquid_install: started");

	// create lightsquid report catalog
	if (!file_exists(LS_REPORTPATH)) {
		update_log("lightsquid_install: Create report dir " . LS_REPORTPATH);
		mwexec("/bin/mkdir -p " . LS_REPORTPATH);
	}

	if ($pfs_version == "2.1" || $pfs_version == "2.2") {
		# check perl
		$perl_path = '/usr/bin/perl';

		/* Clean up a bad perl link. */
		ls_cleanup_bad_link($perl_path);

		if (!file_exists("/usr/bin/perl")) {
			if (is_executable('/usr/local/bin/perl')) {
				symlink('/usr/local/bin/perl', '/usr/bin/perl');
			} elseif (is_executable(LIGHTSQUID_BASE . '/bin/perl')) {
				symlink(LIGHTSQUID_BASE . '/bin/perl', '/usr/bin/perl');
			}
		}
		if (!is_dir('/usr/local/lib/perl5') && is_dir(LIGHTSQUID_BASE . '/lib/perl5')) {
			symlink(LIGHTSQUID_BASE . '/lib/perl5', '/usr/local/lib/perl5');
		}
		if (!is_dir('/usr/local/etc/lightsquid') && is_dir(LS_CONFIGPATH)) {
			symlink(LS_CONFIGPATH, '/usr/local/etc/lightsquid');
		}

		if (is_dir('/usr/local/www/lightsquid'))
			$_gc = exec('rm -rf /usr/local/www/lightsquid');

		if (is_dir(LS_WWWPATH)) {
			symlink(LS_WWWPATH, '/usr/local/www/lightsquid');
		}
	}

	foreach (array('novopf', 'novosea') as $tpl) {
		if (file_exists(LS_TEMPLATEPATH . '/' . $tpl))
			$_gc = exec('rm -rf ' . LS_TEMPLATEPATH . '/' . $tpl);
		symlink('/usr/local/share/lightsquid/tpl/' . $tpl, LS_TEMPLATEPATH . '/' . $tpl);
	}

	update_log("lightsquid_install: stopped");
}

function lightsquid_deinstall() {
	update_log("lightsquid_deinstall: started");

	// delete cron task's
	ls_setup_cron("lightsquid_squid_rotate", "", "", false);
	ls_setup_cron("lightsquid_parser", "", "", false);

	update_log("lightsquid_deinstall: stopped");
}

function lightsquid_resync() {
	global $config, $pfs_version;
	$tm = '';
	$tm_squid = '';

	if ($pfs_version == "2.1" || $pfs_version == "2.2") {
		# check perl
		if (!file_exists("/usr/bin/perl"))
			mwexec("ln -s /usr/local/bin/perl /usr/bin/perl");

		// Fixup library path so GD can find its libraries for graphs.
		mwexec("/sbin/ldconfig -m " . LIGHTSQUID_BASE . "/lib/");
	}

	// create lightsquid report catalog
	if (!file_exists(LS_REPORTPATH)) {
		update_log("lightsquid_install: Create report dir " . LS_REPORTPATH);
		mwexec("mkdir -p " . LS_REPORTPATH);
	}

	mwexec("/bin/chmod -R u+w " . LIGHTSQUID_BASE . "/etc/lightsquid");

	// debug
	$light_test = array();
	if (($_POST['Submit'] === 'Save') or !isset($_POST['Submit'])) {
		$lsconf_var = array();

		// variables for update
		$lsconf_var[LS_VAR_CFGPATH]	= "\"" . LS_CONFIGPATH . "\"";
		if (isset($config['installedpackages']['squid']['config'][0])) {
			$lsconf_var[LS_VAR_LOGPATH]	= "\"" . LS_SQUIDLOGPATH . "\"";
		}
		$lsconf_var[LS_VAR_TPLPATH]	= "\"" . LS_TEMPLATEPATH . "\"";
		$lsconf_var[LS_VAR_LANGPATH]	= "\"" . LS_LANGPATH . "\"";
		$lsconf_var[LS_VAR_REPORTPATH]	= "\"" . LS_REPORTPATH . "\"";
		$lsconf_var[LS_VAR_IP2NAMEPATH]	= "\"" . LS_IP2NAMEPATH . "\"";

		$lsconf_var[LS_VAR_LANG]	= "\"" . LS_DEF_LANG . "\"";
		$lsconf_var[LS_VAR_TEMPLATE]	= "\"" . LS_DEF_TEMPLATE . "\"";
		$lsconf_var[LS_VAR_IP2NAME]	= "\"" . LS_DEF_IP2NAME . "\"";
		$lsconf_var[LS_VAR_SKIPURL]	= "\"" . LS_DEF_SKIPURL . "\"";
		$lsconf_var[LS_VAR_SQUIDLOGTYPE]= LS_DEF_SQUIDLOGTYPE;

		// update variables from package GUI config
		if (isset($config['installedpackages']['lightsquid']['config'][0])) {
			$cfg = $config['installedpackages']['lightsquid']['config'][0];

			$tm = $cfg[LS_XML_SHEDULERTIME];
			$tm_squid = $cfg[LS_XML_SQUID_SHEDULERTIME];

			if (isset($cfg[LS_XML_LANG]) and !empty($cfg[LS_XML_LANG]))
				$lsconf_var[LS_VAR_LANG] = "\"" . $cfg[LS_XML_LANG] . "\";";

			if (isset($cfg[LS_XML_SKIPURL]) and !empty($cfg[LS_XML_SKIPURL]))
				$lsconf_var[LS_VAR_SKIPURL] = "\"" . $cfg[LS_XML_SKIPURL] . "\";";

			if (isset($cfg[LS_XML_IP2NAME]) and !empty($cfg[LS_XML_IP2NAME] ))
				$lsconf_var[LS_VAR_IP2NAME] = "\"" . $cfg[LS_XML_IP2NAME] . "\";";

			if (isset($cfg[LS_XML_TEMPLATE]) and !empty($cfg[LS_XML_TEMPLATE])) {
				$tpl_val = $cfg[LS_XML_TEMPLATE];
				// check template path
				if (!file_exists(LS_TEMPLATEPATH."/$tpl_val")) $tpl_val = 'base';
				$lsconf_var[LS_VAR_TEMPLATE] = "\"" . $tpl_val . "\";";
			}

			if (isset($cfg[LS_XML_BARCOLOR]) and !empty($cfg[LS_XML_BARCOLOR]))
				$lsconf_var[LS_VAR_BARCOLOR] = "\"" . $cfg[LS_XML_BARCOLOR] . "\";";
		}

		$lsconf = "";
		$lsconf_file = LS_CONFIGPATH . "/" . LS_CONFIGFILE;
		// open lightsquid config
		if (file_exists($lsconf_file)) {
			$lsconf = file_get_contents($lsconf_file);
			update_log("Load config file $lsconf_file");
		} else {
			update_log("Error loading config file $lsconf_file");
			// or open from 'lightsquid.cfg.dist'
			$lsconf_dist_file = LS_CONFIGPATH . "/" . LS_CONFIGFILE_DIST;
			if (file_exists($lsconf_dist_file)) {
				$lsconf = file_get_contents($lsconf_dist_file);
				update_log("Load config dist. file $lsconf_dist_file");
			} else update_log("Error loading config dist. file $lsconf_dist_file");
		}

		// update lightsquid config
		if (!empty($lsconf)) {
			$lsconf = explode("\n", $lsconf);
			foreach ($lsconf_var as $key => $val) {
				for($i = 0; $i < count($lsconf); $i++) {
					$s = trim($lsconf[$i]);
					$e_key = "/^[$]" . $key . "[ ]*[=]+/i";
#					update_log("Regular: preg_match(\"$e_key," . "'$s')"); // debug regular template
					if (preg_match($e_key, $s)) {
#					update_log("Regular PASSED: preg_match(\"$e_key," . "'$s')"); // debug regular template
						$lsconf[$i] = '$' . "$key = $val;";
						update_log("Update config: $key=$val");
					}
				}
			}

			$lsconf = implode("\n", $lsconf);
			$fl = file_put_contents($lsconf_file, $lsconf);
			update_log("Save config file $lsconf_file ($fl)");
		}

		// set shedule - refresh data job
		if ($tm) {
			$on = false;
			$opt = array("*", "*", "*", "*", "*", "root", CRONTAB_LS_TEMPLATE . " today");
			switch($tm) {
				case 'lhp_none': $on = false; break;
				case 'lhp_10m':  $on = true; $opt[0]= "*/10"; break;
				case 'lhp_20m':  $on = true; $opt[0]= "*/20"; break;
				case 'lhp_30m':  $on = true; $opt[0]= "*/30"; break;
				case 'lhp_40m':  $on = true; $opt[0]= "*/40"; break;
				case 'lhp_50m':  $on = true; $opt[0]= "*/50"; break;
				case 'lhp_60m':  $on = true; $opt[0]= "*/60"; break;
				case 'lhp_2h':   $on = true; $opt[0]= "0";  $opt[1]= "*/2";  break;
				case 'lhp_3h':   $on = true; $opt[0]= "0";  $opt[1]= "*/3";  break;
				case 'lhp_4h':   $on = true; $opt[0]= "0";  $opt[1]= "*/4";  break;
				case 'lhp_6h':   $on = true; $opt[0]= "0";  $opt[1]= "*/6";  break;
				case 'lhp_8h':   $on = true; $opt[0]= "0";  $opt[1]= "*/8";  break;
				case 'lhp_12h':  $on = true; $opt[0]= "0";  $opt[1]= "*/12"; break;
				case 'lhp_24h':  $on = true; $opt[0]= "45"; $opt[1]= "23";   break; # dayly at 23:45
			}
			ls_setup_cron("lightsquid_parser_today", $opt, CRONTAB_LS_JOBKEY, $on);
	
			# fix possible data lost with 00:00 script start - rescan yesterday
			$opt = array("15", "0", "*", "*", "*", "root", CRONTAB_LS_TEMPLATE . " yesterday");
			ls_setup_cron("lightsquid_parser_yesterday", $opt, CRONTAB_LS_JOBKEY, $on);
	
		}  else {
			ls_setup_cron("lightsquid_parser_today", "", "", false);
			ls_setup_cron("lightsquid_parser_yesterday", "", "", false);
		}
	
		ls_setup_cron("lightsquid_squid_rotate", "", "", false);

		// update squid conf
		if (isset($config['installedpackages']['squid']['config'][0])) {
			$squid_settings = $config['installedpackages']['squid']['config'][0];
			$squid_settings['log_enabled'] = 'on';
			if (empty($squid_settings['log_dir']))
				$squid_settings['log_dir'] = LS_SQUIDLOGPATH;
			
			# sqstat
                     $ifmgr = "127.0.0.1;";
			$iface = ($squid_settings['active_interface'] ? $squid_settings['active_interface'] : 'lan');
			$iface = explode(",", $iface);
			foreach ($iface as $i => $if) {
				$realif = ls_get_real_interface_address($if);
                            if ($realif[0]) 
					$ifmgr = $ifmgr . $realif[0] . ";";
			}

			/* Only save and resync if we're actually making any changes. */
			if (strpos($config['installedpackages']['squidnac']['config'][0]['ext_cachemanager'], $ifmgr) === FALSE) {
				$config['installedpackages']['squidnac']['config'][0]['ext_cachemanager'] = $ifmgr;
				write_config();
				if (function_exists('squid_resync'))
					squid_resync();
				else
					update_log("Function 'squid_resync' not found.");
			}
		}
	}

	if ($_POST['Submit'] === 'Refresh now')  refresh_now();
	if ($_POST['Submit'] === 'Refresh full') refresh_full();
}

// setup cron tasks
// original source from '/etc/inc/pfsense-utils.inc' function 'tdr_install_cron'
// this function safe for other tasks
// *****************************************************************************
// - $task_name: cron task name (for config identification) /for searching my cron tasks/
// - $options:   array=[0:minute][1:hour][2:mday][3:month][4:wday][5:who][6:cmd]
// - $task_key:  cron command key for searching
// - $on_off:	true-'on task', false-'off' task
// required: $task_nameand $on_off
// *****************************************************************************
define('FIELD_TASKNAME', 'task_name');

function ls_setup_cron($task_name, $options, $task_key, $on_off) {
	global $config;
	update_log("ls_setup_cron: start task_name=$task_name, task_key=$task_key, on_off=$on_off");

	// check input params
	if(!$task_name) {
		update_log("ls_setup_cron: exit - uncomplete input params.");
		return;
	}

	// delete old task(s)
	if (is_array($config['cron']['item'])) {
		foreach ($config['cron']['item'] as $key => $item) {
		# unset crontask by name
		if (!empty($task_name) && ($item[FIELD_TASKNAME] == $task_name)) {
			unset($config['cron']['item'][$key]);
		} else
		# unset crontask by cmd
		if ($options[6] && (strpos($item['command'], $options[6]) !== false)) {
			unset($config['cron']['item'][$key]);
		}
		}
	}

	# install cron task
	if ($on_off) {
		if ($task_key) {
		if (is_array($options)) {
			# add new
			$cron_item = array();
			$cron_item[FIELD_TASKNAME]	= $task_name;
			$cron_item['minute']		= $options[0];
			$cron_item['hour']		= $options[1];
			$cron_item['mday']		= $options[2];
			$cron_item['month']		= $options[3];
			$cron_item['wday']		= $options[4];
			$cron_item['who']		= $options[5];
			$cron_item['command']		= $options[6];

			# check options
			if (!$cron_item['who']) $cron_item['who'] = "nobody";

			$config['cron']['item'][] = $cron_item;
			update_log("ls_setup_cron: add cron task '{$task_name}'='{$cron_item['command']}'");
		}
		} else
		// log
		update_log("ls_setup_cron: input prm 'task_key' not defined");
	}

	write_config("Installed cron task '$task_name' for 'lightsquid' package");
	configure_cron();
	update_log("ls_setup_cron: Apply new cron settings.");
}

function update_log($log) {
	if (LS_GUI_DEBUG === 'on') {
		$t_ls_log = '';
		if (file_exists(LS_LOG_FILE))
			$t_ls_log = file_get_contents(LS_LOG_FILE);
		$t_ls_log .= "\n$log";
		file_put_contents(LS_LOG_FILE, $t_ls_log);
	}
}

function refresh_now() {
	$cmd = CRONTAB_LS_TEMPLATE . " today";
	update_log("refresh_now: execute command '$cmd'");
	// create lightsquid report catalog
	if (!file_exists(LS_REPORTPATH)) {
		update_log("lightsquid_install: Create report dir " . LS_REPORTPATH);
		mwexec("mkdir -p " . LS_REPORTPATH);
	}
	mwexec_bg($cmd);
}

function refresh_full() {
	$cmd = CRONTAB_LS_TEMPLATE;
	$log_name = LS_SQUIDLOG;
	update_log("refresh_full: start");

	// create lightsquid report catalog
	if (!file_exists(LS_REPORTPATH)) {
		update_log("lightsquid_install: Create report dir " . LS_REPORTPATH);
		mwexec("mkdir -p " . LS_REPORTPATH);
	}

	// parse access.log
	update_log("refresh_full: execute command '$cmd'");
	mwexec_bg("$cmd $log_name");
	// parse access.log.x
	if (isset($config['installedpackages']['squid']['config'][0])) {
		for ($i=0; $i<100; $i++) {
			$lg = LS_SQUIDLOG . ".$i";
			if (file_exists(LS_SQUIDLOGPATH . "/$lg")) {
				update_log("refresh_full: execute command '$cmd $lg'");
				mwexec_bg("$cmd $lg");
			} else
				// really go <= 10 cycles
				break;
		}
	}
	update_log("refresh_full: stop");
}

function ls_get_real_interface_address($iface)
{
	global $config;

	$iface = convert_friendly_interface_to_real_interface_name($iface);
	$line = trim(shell_exec("ifconfig $iface | grep inet | grep -v inet6"));
	list($dummy, $ip, $dummy2, $netmask) = explode(" ", $line);

	return array($ip, long2ip(hexdec($netmask)));
}

/* If a path is a symlink but the target is missing, remove the link. */
function ls_cleanup_bad_link($link) {
	if (file_exists($link) && is_link($link)) {
		$target = readlink($link);
		if (!file_exists($target) || !is_executable($target)) {
			unlink($link);
		}
	}
}

?>
