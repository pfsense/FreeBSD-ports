<?php
/*
 * squid_antivirus.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2017 Rubicon Communications, LLC (Netgate)
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

/* Functions for Squid C-ICAP/ClamAV integration */
require_once('config.inc');
require_once('globals.inc');
require_once('services.inc');
require_once('service-utils.inc');
require_once('util.inc');
/* This file is currently only being included in squid.inc and not used separately */
// require_once('squid.inc');

global $clamav_uid, $clamav_gid, $config;
$clamav_uid = "clamav";
$clamav_gid = "clamav";

/* If /var is in RAM, the AV database will not be persistent there and space is
	limited, so relocate it. */
if (isset($config['system']['use_mfs_tmpvar'])) {
	define('CLAMAV_DBDIR', '/usr/local/share/clamav-db/');
} else {
	define('CLAMAV_DBDIR', '/var/db/clamav/');
}

/*
 * Utility functions
 */

/* check and fix ClamAV/C-ICAP antivirus dirs and permissions if needed */
function squid_check_antivirus_dirs() {
	global $clamav_uid, $clamav_gid;
	$dirs = array(
		"/var/log/clamav",
		"/var/run/clamav",
		CLAMAV_DBDIR,
		"/var/run/c-icap",
		"/var/log/c-icap"
	);
	foreach ($dirs as $dir_path) {
		safe_mkdir($dir_path, 0755);
		squid_chown_recursive($dir_path, $clamav_uid, $clamav_gid);
	}
}

/* Antivirus definitions updates via cron */
function squid_install_freshclam_cron($should_install) {
	global $config;

	if (platform_booting()) {
		return;
	}

	$freshclam_cmd = (SQUID_BASE . "/bin/freshclam --config-file=" . SQUID_BASE . "/etc/freshclam.conf");
	if (($should_install) && (squid_enabled())) {
		if (is_array($config['installedpackages']['squidantivirus'])) {
			$antivirus_config = $config['installedpackages']['squidantivirus']['config'][0];
		} else {
			$antivirus_config = array();
		}
		if ($antivirus_config['clamav_update'] != "") {
			log_error("[squid] Adding freshclam cronjob.");
			$minutes = ($antivirus_config['clamav_update'] * 60);
			install_cron_job("{$freshclam_cmd}", true, "*/{$minutes}", "*", "*", "*", "*", "clamav");
		} else {
			log_error("[squid] Removing freshclam cronjob.");
			install_cron_job("{$freshclam_cmd}", false);
		}
	} else {
		log_error("[squid] Removing freshclam cronjob.");
		install_cron_job("{$freshclam_cmd}", false);
	}
}

/* Manually update ClamAV virus definitions via the GUI button */
function squid_update_clamav() {
	squid_check_antivirus_dirs();
	log_error("[squid] Updating ClamAV definitions now... This will take a while. Check freshclam log on the 'Real Time' tab for progress information.");
	mwexec_bg(SQUID_BASE . "/bin/freshclam --config-file=" . SQUID_BASE . "/etc/freshclam.conf");
}

/*
 * Squid package install/uninstall
 */

/* Run on Squid package install */
function squid_antivirus_install_command() {
	// antivirus rc scripts
	squid_write_cicap_rcfile();
	squid_write_clamd_rcfile();

	// antivirus config files
	squid_antivirus_install_config_files();

	// remove dirs with possibly broken file permissions after previous versions
	$dirs = array("/var/run/c-icap", "/var/log/c-icap");
	foreach ($dirs as $dir) {
		if (is_dir("{$dir}")) {
			mwexec("/bin/rm -rf {$dir}");
		}
	}

	// create required dirs if needed
	squid_check_antivirus_dirs();

	// remove unwanted rc scripts
	unlink_if_exists("/usr/local/etc/rc.d/c-icap");
	unlink_if_exists("/usr/local/etc/rc.d/clamav-clamd");
	unlink_if_exists("/usr/local/etc/rc.d/clamav-freshclam");
}

/* Run on Squid package uninstall */
function squid_antivirus_deinstall_command() {
	global $config, $keep;
	/* Stop all running services, remove rc scripts and cronjobs */
	squid_stop_antivirus();
	mwexec("/bin/ps awux | /usr/bin/grep '[f]reshclam' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");

	/* clean up created directories if 'Keep Settings/Data' is disabled */
	if (is_array($config['installedpackages']['squid'])) {
		$squidsettings = $config['installedpackages']['squid']['config'][0];
	} else {
		$squidsettings = array();
	}
	$keep = ($squidsettings['keep_squid_data'] ? true : false);

	if (!$keep) {
		$dirs = array("/var/run/c-icap", "/var/log/c-icap", "/var/log/clamav", "/var/run/clamav", CLAMAV_DBDIR);
		foreach ($dirs as $dir) {
			if (is_dir("{$dir}")) {
				mwexec("/bin/rm -rf {$dir}");
			}
		}
	}
}

/* Migrate configuration from old Squid package versions */
function squid_antivirus_upgrade_config() {
	global $config;
	/* unset broken antivirus settings */
	if (is_array($config['installedpackages']['squidantivirus'])) {
		unset($config['installedpackages']['squidantivirus']['config'][0]['squidclamav']);
		unset($config['installedpackages']['squidantivirus']['config'][0]['c-icap_conf']);
		unset($config['installedpackages']['squidantivirus']['config'][0]['c-icap_magic']);
		unset($config['installedpackages']['squidantivirus']['config'][0]['freshclam_conf']);
	}
}

/*
 * Antivirus features configuration
 *
 * <file>.conf is the actual configuration file used for services.
 * <file>.conf.pfsense is a template file patched for pfSense; should be never altered beyond initial install.
 * <file>.conf.{sample,default} are templates distributed directly with FreeBSD packages;
 * If <file>.conf.default does not exist, a backup copy is made from another distributed files before patching it for pfSense.
 *
 * Configuration via the GUI options:
 * <file>.conf is always (re)generated from the <file>.conf.pfsense on package resync,
 * with additional patches depending on the GUI configuration options configured by user.
 * Directly editing files via 'Advanced Features' is disabled in the GUI.
 *
 * Manual Configuration
 * When the user enables 'Manual Configuration' for the first time, the config.xml settings are
 * serialized from <file>.conf.pfsense template patched for pfSense. After this initial configuration,
 * <file>.conf is always (re)generated from config.xml as long as 'Manual Configuration' is enabled in settings.
 * In this case, any additional configuration made in the Antivirus GUI outside of 'Advanced Features'
 * is unset on saving settings; after that, those options are disabled in the GUI and have no effect any more.
 */

/* Proxy Server: Antivirus configuration handler */
function squid_resync_antivirus() {
	global $config, $antivirus_config;

	if (is_array($config['installedpackages']['squidantivirus'])) {
		$antivirus_config = $config['installedpackages']['squidantivirus']['config'][0];
	} else {
		$antivirus_config = array();
	}

	// squid.conf antivirus integration
	if (squid_enabled() && ($antivirus_config['enable'] == "on")) {
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

icap_service service_avi_req reqmod_precache icap://127.0.0.1:1344/squid_clamav bypass=off
adaptation_access service_avi_req allow all
icap_service service_avi_resp respmod_precache icap://127.0.0.1:1344/squid_clamav bypass=on
adaptation_access service_avi_resp allow all

EOF;

		// check clamav dirs
		squid_check_antivirus_dirs();

		if ($antivirus_config['enable_advanced'] == "enabled") {
			// User is managing raw configuration, so we unset the configuration options set via GUI
			squid_antivirus_toggle_raw_config(true);
			// Generate the raw configuration if missing
			$rawopts = array("raw_squidclamav_conf", "raw_cicap_conf", "raw_cicap_magic", "raw_freshclam_conf", "raw_clamd_conf");
			foreach ($rawopts as $rawopt) {
				if ($antivirus_config[$rawopt] == "") {
					squid_antivirus_get_raw_config();
				}
			}
			// Create configuration files
			squid_antivirus_put_raw_config($config['installedpackages']['squidantivirus']['config'][0]);
		} else {
			// unset raw configuration options
			squid_antivirus_toggle_raw_config(false);

			// patch sample files to pfsense dirs
			// squidclamav.conf
			$cf = SQUID_LOCALBASE . "/etc/c-icap/squidclamav.conf";
			if (file_exists("{$cf}.pfsense")) {
				$sample_file = file_get_contents("{$cf}.pfsense");
				$squidclamav_m[0] = "@#This file was automatically generated by pfSense@";
				$squidclamav_r[0] = "#This file was automatically generated by pfSense WebGUI configuration";
				if ($antivirus_config['clamav_safebrowsing'] == "on") {
					$squidclamav_m[1] = "@safebrowsing\s0@";
					$squidclamav_r[1] = "safebrowsing 1";
				}
				if ($antivirus_config['clamav_url'] != "") {
					$squidclamav_m[2] = "@(redirect http.*)@";
					$squidclamav_r[2] = "redirect {$antivirus_config['clamav_url']}";
				}
				/* Trying to overwrite squidclamav.conf via file_put_contents() may fail when Squid is running */
				if (!file_put_contents("{$cf}", preg_replace($squidclamav_m, $squidclamav_r, $sample_file), LOCK_EX)) {
					log_error("[squid] Could not save generated {$cf} file!");
				}
				if ($antivirus_config['clamav_disable_stream_scanning'] == "on") {
					$stream_exclude = <<< EOF
# Do not scan (streamed) videos and audios
abort ^.*\.(flv|f4f|mp(3|4))(\?.*)?$
abort ^.*\.(m3u|pls|wmx|aac|mpeg)(\?.*)?$
abortcontent ^video\/x-flv$
abortcontent ^video\/mp4$
abortcontent ^audio\/mp4$
abortcontent ^.*audio\/mp4.*$
abortcontent ^video\/webm$
abortcontent ^audio\/webm$
abortcontent ^video\/MP2T$
abortcontent ^audio\/wmx$
abortcontent ^audio\/mpeg$
abortcontent ^audio\/aac$
abortcontent ^.*application\/x-mms-framed.*$

EOF;
					if (!file_put_contents("{$cf}", "{$stream_exclude}", FILE_APPEND | LOCK_EX)) {
						log_error("[squid] Could not add streaming exclusions to {$cf} file!");
					}
				}
			} else {
				log_error("[squid] Template not found; could not generate '{$cf}' file!");
			}

			// c-icap.conf
			$cf = SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf";
			if (file_exists("{$cf}.pfsense")) {
				$sample_file = file_get_contents("{$cf}.pfsense");
				if (!preg_match("/squid_clamav/", $sample_file)) {
					$sample_file .= "\nService squid_clamav squidclamav.so\n";
				}
				$cicap_m[0] = "@#This file was automatically generated by pfSense@";
				$cicap_r[0] = "#This file was automatically generated by pfSense WebGUI configuration";
				if (!file_put_contents("{$cf}", preg_replace($cicap_m, $cicap_r, $sample_file), LOCK_EX)) {
					log_error("[squid] Could not save generated {$cf} file!");
				}
			} else {
				log_error("[squid] Template not found; could not generate '{$cf}' file!");
			}

			// c-icap.magic
			// just make a copy of pfSense template, nothing configurable via GUI options here
			$cf = SQUID_LOCALBASE . "/etc/c-icap/c-icap.magic";
			if (file_exists("{$cf}.pfsense")) {
				if (!copy("{$cf}.pfsense", "{$cf}")) {
					log_error("[squid] Could not save {$cf} file!");
				}
			} else {
				log_error("[squid] Template not found; could not save '{$cf}' file!");
			}

			// freshclam.conf
			$cf = SQUID_LOCALBASE . "/etc/freshclam.conf";
			if (file_exists("{$cf}.pfsense")) {
				$sample_file = file_get_contents("{$cf}.pfsense");
				$freshclam_m[0] = "@#This file was automatically generated by pfSense@";
				$freshclam_r[0] = "#This file was automatically generated by pfSense WebGUI configuration";
				$freshclam_m[1] = "@DatabaseDirectory /var/db/clamav@";
				$freshclam_r[1] = "DatabaseDirectory " . CLAMAV_DBDIR;
				$clamav_mirrors = "";
				if ($antivirus_config['clamav_dbservers'] != "") {
					foreach (explode(";", $antivirus_config['clamav_dbservers']) as $dbserver) {
						$clamav_mirrors .= "DatabaseMirror {$dbserver}\n";
					}
				}
				if ($antivirus_config['clamav_dbregion'] != "") {
					$clamav_mirrors .= "DatabaseMirror db.{$antivirus_config['clamav_dbregion']}.clamav.net\n";
				}
				if ($clamav_mirrors != "") {
					$freshclam_m[2] = "@#DatabaseMirror db.XY.clamav.net@";
					$freshclam_r[2] = "{$clamav_mirrors}";
				}
				if ($antivirus_config['clamav_safebrowsing'] == "on") {
					$freshclam_m[3] = "@#SafeBrowsing yes@";
					$freshclam_r[3] = "SafeBrowsing yes";
				}
				if (!file_put_contents("{$cf}", preg_replace($freshclam_m, $freshclam_r, $sample_file), LOCK_EX)) {
					log_error("[squid] Could not save generated {$cf} file!");
				}
			} else {
				log_error("[squid] Template not found; could not generate '{$cf}' file!");
			}

			// clamd.conf
			$cf = SQUID_LOCALBASE . "/etc/clamd.conf";
			if (file_exists("{$cf}.pfsense")) {
				$sample_file = file_get_contents("{$cf}.pfsense");
				$clamd_m[0] = "@#This file was automatically generated by pfSense@";
				$clamd_r[0] = "#This file was automatically generated by pfSense WebGUI configuration";
				$clamd_m[1] = "@DatabaseDirectory /var/db/clamav@";
				$clamd_r[1] = "DatabaseDirectory " . CLAMAV_DBDIR;
				if (!file_put_contents("{$cf}", preg_replace($clamd_m, $clamd_r, $sample_file), LOCK_EX)) {
					log_error("[squid] Could not save generated {$cf} file!");
				}
			} else {
				log_error("[squid] Template not found; could not generate '{$cf}' file!");
			}
			unset($cf);
		}

		// freshclam cronjob
		squid_install_freshclam_cron(true);

	}
	// this will (re)start or stop/disable services as needed
	// depending on whether Squid proxy and/or antivirus features are enabled
	squid_restart_antivirus();

	return $conf;
}

/* Patch paths and settings in configuration files template for pfSense-specific values on install */
function squid_antivirus_install_config_files() {
	global $config, $clamav_uid, $clamav_gid;
	if (is_array($config['installedpackages']['squid'])) {
		$squidsettings = $config['installedpackages']['squid']['config'][0];
	} else {
		$squidsettings = array();
	}
	// squidclamav.conf
	// make a backup of default squidclamav.conf.sample first
	$cf = SQUID_LOCALBASE . "/etc/c-icap/squidclamav.conf";
	if (file_exists("{$cf}.sample")) {
		copy("{$cf}.sample", "{$cf}.default");
	} else {
		log_error("[squid] Could not backup '{$cf}' sample file!");
	}
	if (file_exists("{$cf}.default")) {
		$sample_file = file_get_contents("{$cf}.default");
		$squidclamav_m[0] = "@# SquidClamav default configuration file@";
		$squidclamav_r[0] = "#This file was automatically generated by pfSense";
		$squidclamav_m[1] = "@/var/run/clamav/clamd.ctl@";
		$squidclamav_r[1] = "/var/run/clamav/clamd.sock";
		$squidclamav_m[2] = "@http\://proxy.domain.dom/cgi-bin/clwarn.cgi@";
		$port = $config['system']['webgui']['port'];
		if ($port == "") {
			$squidclamav_r[2] = "{$config['system']['webgui']['protocol']}://{$config['system']['hostname']}.{$config['system']['domain']}/squid_clwarn.php";
		} else {
			$squidclamav_r[2] = "{$config['system']['webgui']['protocol']}://{$config['system']['hostname']}.{$config['system']['domain']}:{$port}/squid_clwarn.php";
		}
		$squidclamav_m[3] = "@dnslookup\s1@";
		$squidclamav_r[3] = "dnslookup 0";
		// This should match StreamMaxLength in clamd.conf
		$squidclamav_m[4] = "@maxsize\s+5000000@";
		$squidclamav_r[4] = "maxsize 25000000";
		if (!file_put_contents("{$cf}.pfsense", preg_replace($squidclamav_m, $squidclamav_r, $sample_file), LOCK_EX)) {
			log_error("[squid] Could not save patched '{$cf}.pfsense' template file!");
		}
	} else {
		log_error("[squid] Could not patch '{$cf}' template file!");
	}

	// c-icap.conf
	// make a backup of default c-icap.conf.sample first
	$cf = SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf";
	if (file_exists("{$cf}.sample")) {
		copy("{$cf}.sample", "{$cf}.default");
	} else {
		log_error("[squid] Could not backup '{$cf}' sample file!");
	}
	if (file_exists("{$cf}.default")) {
		// http://wiki.squid-cache.org/ConfigExamples/ContentAdaptation/C-ICAP#Antivirus_checking_with_C-ICAP.2C_ClamAV_daemon_and_Squidclamav
		$sample_file = file_get_contents("{$cf}.default");
		if (!preg_match("/squid_clamav/", $sample_file)) {
			$sample_file .= "\nService squid_clamav squidclamav.so\n";
		}
		$sample_file .= "User {$clamav_uid}\n";
		$sample_file .= "Group {$clamav_gid}\n";
		$cicap_m[0] = "@# This file contains the default settings for c-icap@";
		$cicap_r[0] = "#This file was automatically generated by pfSense";
		$cicap_m[1] = "@StartServers\s+3@";
		$cicap_r[1] = "StartServers 1";
		$cicap_m[2] = "@MaxServers\s+10@";
		$cicap_r[2] = "MaxServers 20";
		$cicap_m[3] = "@MaxRequestsPerChild\s+0@";
		$cicap_r[3] = "MaxRequestsPerChild 1000";
		/* XXX: Bug #4615
		 * Do NOT move the C-ICAP log anywhere, ever! It breaks C-ICAP in completely inexplicable ways,
		 * such as Error: [No Error] or 500 response codes.
		 */
		$cicap_m[4] = "@DebugLevel\s1@";
		$cicap_r[4] = "DebugLevel 0";
		if (!file_put_contents("{$cf}.pfsense", preg_replace($cicap_m, $cicap_r, $sample_file), LOCK_EX)) {
			log_error("[squid] Could not save patched '{$cf}.pfsense' template file!");
		}
	} else {
		log_error("[squid] Could not patch '{$cf}' template file!");
	}

	// c-icap.magic
	// just make a backup and pfSense template copies of default c-icap.magic, we are not patching anything here
	$cf = SQUID_LOCALBASE . "/etc/c-icap/c-icap.magic";
	if (file_exists("{$cf}.sample")) {
		copy("{$cf}.sample", "{$cf}.default");
		copy("{$cf}.sample", "{$cf}.pfsense");
	} else {
		log_error("[squid] Could not backup '{$cf}' sample file!");
	}

	// clamd.conf
	// make a backup of default clamd.conf.sample first
	$cf = SQUID_LOCALBASE . "/etc/clamd.conf";
	if (file_exists("{$cf}.sample")) {
		copy("{$cf}.sample", "{$cf}.default");
	} else {
		log_error("[squid] Could not backup '{$cf}' sample file!");
	}
	if (file_exists("{$cf}.default")) {
		$sample_file = file_get_contents("{$cf}.default");
		$clamd_m[0] = "@## Example config file for the Clam AV daemon@";
		$clamd_r[0] = "#This file was automatically generated by pfSense";
		$clamd_m[1] = "@# Comment or remove the line below.@";
		$clamd_r[1] = "";
		$clamd_m[2] = "@#Example@";
		$clamd_r[2] = "";
		if (!file_put_contents("{$cf}.pfsense", preg_replace($clamd_m, $clamd_r, $sample_file), LOCK_EX)) {
			log_error("[squid] Could not save patched '{$cf}.pfsense' template file!");
		}
	} else {
		log_error("[squid] Could not patch '{$cf}' template file!");
	}

	// freshclam.conf
	// make a backup of default freshclam.conf.sample first
	$cf = SQUID_LOCALBASE . "/etc/freshclam.conf";
	if (file_exists("{$cf}.sample")) {
		copy("{$cf}.sample", "{$cf}.default");
	} else {
		log_error("[squid] Could not backup '{$cf}' sample file!");
	}
	if (file_exists("{$cf}.default")) {
		$sample_file = file_get_contents("{$cf}.default");
		$freshclam_m[0] = "@## Example config file for freshclam@";
		$freshclam_r[0] = "#This file was automatically generated by pfSense";
		$freshclam_m[1] = "@# Comment or remove the line below.@";
		$freshclam_r[1] = "";
		$freshclam_m[2] = "@#Example@";
		$freshclam_r[2] = "";
		if (!file_put_contents("{$cf}.pfsense", preg_replace($freshclam_m, $freshclam_r, $sample_file), LOCK_EX)) {
			log_error("[squid] Could not save patched '{$cf}.pfsense' template file!");
		}
	} else {
		log_error("[squid] - could not patch '{$cf}' template file!");
	}
	unset($cf);
}

/* Get the raw pfSense template files for manual configuration and serialize them to config.xml */
function squid_antivirus_get_raw_config() {
	global $config;
	$loaded = false;
	$rawfiles = array("squidclamav.conf", "c-icap.conf", "c-icap.magic", "freshclam.conf", "clamd.conf");

	foreach ($rawfiles as $rawfile) {
		switch ($rawfile) {
			case 'squidclamav.conf':
				$confdir = "/c-icap";
				$confopt = "raw_squidclamav_conf";
				break;
			case 'c-icap.conf':
				$confdir = "/c-icap";
				$confopt = "raw_cicap_conf";
				break;
			case 'c-icap.magic':
				$confdir = "/c-icap";
				$confopt = "raw_cicap_magic";
				break;
			case 'freshclam.conf':
				$confdir = "";
				$confopt = "raw_freshclam_conf";
				break;
			case 'clamd.conf':
				$confdir = "";
				$confopt = "raw_clamd_conf";
				break;
			default:
				$confdir = "";
				$confopt = "";
				break;
		}
		// get the config from the files if not set (yet) in config.xml
		if ($confopt) {
			$conffile = SQUID_LOCALBASE . "/etc" . "{$confdir}" . "/{$rawfile}.pfsense";
			if (file_exists($conffile)) {
				if ($config['installedpackages']['squidantivirus']['config'][0][$confopt] == "") {
					$config['installedpackages']['squidantivirus']['config'][0][$confopt] =  base64_encode(str_replace("\r", "", file_get_contents("{$conffile}")));
					log_error("[squid] Successfully loaded '{$conffile}' configuration file");
					$loaded = true;
				}
			// Just a fallback attempt if people do things in weird order on a completely fresh install perhaps; should not be ever needed
			} else {
				squid_antivirus_install_config_files();
				if (file_exists($conffile)) {
					$config['installedpackages']['squidantivirus']['config'][0][$confopt] =  base64_encode(str_replace("\r", "", file_get_contents("{$conffile}")));
					log_error("[squid] Successfully loaded '{$conffile}' configuration file");
					$loaded = true;
				} else {
					log_error("[squid] '{$conffile}' template does not exist; could not load advanced {$rawfile} configuration!");
				}
			}
		}
	}
	if ($loaded) {
		write_config("Squid - Loaded raw configuration files", false);
		log_error("[squid] Successfully loaded raw configuration files");
	}
}

/* Toggle the raw config state */
function squid_antivirus_toggle_raw_config($state) {
	global $config;
	if ($state) {
		// manual configuration enabled
		$opts = array("clamav_url", "clamav_safebrowsing", "clamav_dbregion", "clamav_dbservers");
		foreach ($opts as $opt) {
			if (isset($config['installedpackages']['squidantivirus']['config'][0][$opt])) {
				unset($config['installedpackages']['squidantivirus']['config'][0][$opt]);
				log_error("[squid] Loaded '{$opt}' raw configuration file...");
			}
		}
		log_error("[squid] Loading raw configuration files...");
		squid_antivirus_get_raw_config();
	} else {
		// manual configuration disabled
		$opts = array("raw_squidclamav_conf", "raw_cicap_conf", "raw_cicap_magic", "raw_freshclam_conf", "raw_clamd_conf");
		foreach ($opts as $opt) {
			if (isset($config['installedpackages']['squidantivirus']['config'][0][$opt])) {
				unset($config['installedpackages']['squidantivirus']['config'][0][$opt]);
				log_error("[squid] Unloaded '{$opt}' raw configuration.");
			}
		}
		$config['installedpackages']['squidantivirus']['config'][0]['enable_advanced'] = "disabled";
	}
}

/* Write the raw config files to disk from config.xml configuration */
function squid_antivirus_put_raw_config($rawfiles) {
	if (is_array($rawfiles)) {
		foreach ($rawfiles as $rawfile => $rawconfig) {
			switch ($rawfile) {
				case 'raw_squidclamav_conf':
					$confdir = "/c-icap";
					$conffile = "/squidclamav.conf";
					break;
				case 'raw_cicap_conf':
					$confdir = "/c-icap";
					$conffile = "/c-icap.conf";
					break;
				case 'raw_cicap_magic':
					$confdir = "/c-icap";
					$conffile = "/c-icap.magic";
					break;
				case 'raw_freshclam_conf':
					$confdir = "";
					$conffile = "/freshclam.conf";
					break;
				case 'raw_clamd_conf':
					$confdir = "";
					$conffile = "/clamd.conf";
					break;
				default:
					$confdir = "";
					$conffile = "";
					break;
			}
			if ($conffile && $rawconfig) {
				squid_antivirus_write_conffile($confdir, $conffile, $rawconfig);
			}
		}
	}
}

/* Helper function for squid_antivirus_put_raw_config() */
function squid_antivirus_write_conffile($dir, $file, $text) {
	if ($file && $text) {
		$cfgfile = SQUID_LOCALBASE . "/etc" . "{$dir}" . "{$file}";
		if (!file_put_contents("{$cfgfile}", preg_replace("/\r\n/", "\n", base64_decode($text)), LOCK_EX)) {
			log_error("[squid] Could not save '{$cfgfile}' configuration file.");
		} else {
			log_error("[squid] Saved '{$cfgfile}' configuration file.");
		}
	}
}

/*
 * rc scripts and services
 */

/* Create clamd.sh rc script */
function squid_write_clamd_rcfile() {
	$squid_base = SQUID_BASE;
	$clamav_dbdir = CLAMAV_DBDIR;
	$rc = array();
	$rc['file'] = 'clamd.sh';
	$rc['start'] = <<< EOD

if [ ! -f {$clamav_dbdir}main.cvd -a ! -f {$clamav_dbdir}main.cld ]; then
	echo "Missing {$clamav_dbdir}*.cvd or *.cld files. You must run freshclam first!"
        exit 1
fi

{$squid_base}/sbin/clamd --config-file="{$squid_base}/etc/clamd.conf"

EOD;

	$rc['stop'] = <<< EOD

/usr/bin/killall clamd 2>/dev/null
# Just to be sure...
sleep 5
if [ -n "`/bin/ps auxw | /usr/bin/grep "[c]lamd" | /usr/bin/awk '{print $2}'`" ]; then
	/usr/bin/killall -9 clamd 2>/dev/null
fi

EOD;

	conf_mount_rw();
	log_error("[squid] Creating 'clamd.sh' rc script.");
	write_rcfile($rc);
	conf_mount_ro();
}

/* Create c-icap.sh rc script */
function squid_write_cicap_rcfile() {
	$c_icap_rcfile = "c-icap.sh";
	$cicap_bin = SQUID_LOCALBASE . "/bin/c-icap";
	$cicap_conf = SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf";
	$cicap_pipe = "/var/run/c-icap/c-icap.ctl";
	$cicap_start_cmd = "{$cicap_bin} -f {$cicap_conf}";
	$cicap_stop_cmd = <<< EOF

if [ -p "{$cicap_pipe}" ]; then
	/bin/echo -n "stop" > /var/run/c-icap/c-icap.ctl
else
	/usr/bin/killall -9 c-icap 2>/dev/null
fi
sleep 5
/bin/rm -f {$cicap_pipe}
EOF;
	conf_mount_rw();
	log_error("[squid] Creating '{$c_icap_rcfile}' rc script.");
	write_rcfile(array(
		"file" => "{$c_icap_rcfile}",
		"start" => "{$cicap_start_cmd}",
		"stop" => "{$cicap_stop_cmd}"
		)
	);
	conf_mount_ro();
}

/* (Re)start antivirus services if AV features are enabled */
function squid_restart_antivirus() {
	global $config;
	if (is_array($config['installedpackages']['squidantivirus'])) {
		$antivirus_config = $config['installedpackages']['squidantivirus']['config'][0];
	} else {
		$antivirus_config = array();
	}

	// reconfigure and (re)start service as needed if enabled, otherwise stop them
	// do not (re)start antivirus services on boot
	if (platform_booting()) {
		log_error("[squid] Skipping antivirus services (re)start on boot.");
		return;
	}

	if (squid_enabled() && ($antivirus_config['enable'] == "on")) {
		// Check clamav database
		if (count(glob(CLAMAV_DBDIR . "*d")) == 0) {
			log_error("[squid] Missing " . CLAMAV_DBDIR . "*.cvd or *.cld files. Running freshclam in background.");
			log_error("[squid] Do NOT attempt to start ClamAV service until AV definitions are downloaded.");
			squid_update_clamav();
		} elseif ($antivirus_config['clamav_safebrowsing'] == "on" && !is_file(CLAMAV_DBDIR . "safebrowsing.cvd")) {
			log_error("[squid] Google Safe Browsing is enabled but missing safebrowsing.cvd definitions. Running freshclam in background.");
			log_error("[squid] ClamAV will be automatically notified about the new definitions when finished. No manual action necessary.");
			squid_update_clamav();
		} elseif ($antivirus_config['clamav_safebrowsing'] != "on" && is_file(CLAMAV_DBDIR . "safebrowsing.cvd")) {
			log_error("[squid] Google Safe Browsing is disabled. Removing safebrowsing.cvd definitions.");
			mwexec("/bin/rm -f " . CLAMAV_DBDIR . "safebrowsing.cvd");
		}

		// start/reload clamav
		$clamd_rcfile = "/usr/local/etc/rc.d/clamd.sh";
		squid_write_clamd_rcfile();
		if (is_process_running("clamd")) {
			log_error("[squid] Reloading ClamAV...");
			$reload_cmd = SQUID_BASE . "/bin/clamdscan --reload";
			mwexec_bg("{$reload_cmd}");
		} else {
			log_error("[squid] Starting ClamAV...");
			mwexec_bg("{$clamd_rcfile} start");
		}

		// check c-icap rcfile
		$c_icap_rcfile = "/usr/local/etc/rc.d/c-icap.sh";
		if (!file_exists($c_icap_rcfile)) {
			squid_write_cicap_rcfile();
		}
		if (is_process_running("c-icap")) {
			log_error("[squid] Reloading C-ICAP...");
			if (file_exists("/var/run/c-icap/c-icap.ctl")) {
				mwexec_bg('/bin/echo -n "reconfigure" > /var/run/c-icap/c-icap.ctl');
			} else {
				mwexec_bg("{$c_icap_rcfile} restart");
			}
		} else {
			log_error("[squid] Starting C-ICAP...");
			mwexec_bg("{$c_icap_rcfile} start");
		}
	} else {
		// stop AV services and disable all C-ICAP/AV features
		log_error("[squid] Antivirus features disabled.");
		squid_stop_antivirus();
	}
}

/* Stop AV services and disable all C-ICAP/AV features */
function squid_stop_antivirus() {
	// Stop C-ICAP
	if (is_process_running("c-icap")) {
		log_error("[squid] Stopping and disabling C-ICAP...");
		mwexec("/usr/local/etc/rc.d/c-icap.sh stop");
		if (is_process_running("c-icap")) {
			log_error("[squid] C-ICAP still running, forcibly killing c-icap process(es).");
			mwexec("/bin/ps awux | /usr/bin/grep '[c]-icap' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill -9");
			unlink_if_exists("/var/run/c-icap/c-icap.ctl");
		}
	}
	unlink_if_exists("/usr/local/etc/rc.d/c-icap.sh");

	// Stop ClamAV
	if (is_process_running("clamd")) {
		log_error("[squid] Stopping and disabling ClamAV...");
		mwexec("/usr/bin/killall clamd");
		// sleep for a couple of seconds to give clamd a chance to perform clean exit
		for ($i = 0; $i < 10; $i++) {
			if (is_process_running('clamd')) {
				sleep(1);
			}
		}
	}
	if (is_process_running("clamd")) {
		log_error("[squid] ClamAV still running, forcibly killing clamd process(es).");
		mwexec("/usr/bin/killall -9 clamd");
	}
	unlink_if_exists("/usr/local/etc/rc.d/clamd.sh");

	// Remove freshclam cronjob
	squid_install_freshclam_cron(false);
}


/*
 * Input validation
 */

/* Proxy server: Antivirus input validation */
/* Also handles manual AV updates and switching 'Manual Configuration' on/off */
function squid_validate_antivirus($post, &$input_errors) {
	global $config;
	if (is_array($config['installedpackages']['squidantivirus'])) {
		$antivirus_config = $config['installedpackages']['squidantivirus']['config'][0];
	} else {
		$antivirus_config = array();
	}

	/* Manual ClamAV database update */
	if ($post['update_av'] == 'Update AV') {
		squid_update_clamav();
		return;
	}

	/* Load the raw config files if manual configuration is enabled */
	if ($post['load_advanced'] == 'Load Advanced') {
		$config['installedpackages']['squidantivirus']['config'][0]['enable_advanced'] = "enabled";
		squid_antivirus_toggle_raw_config(true);
		return;
	}

	if ($post['raw_squidclamav_conf'] && preg_match("/(\S+proxy.domain\S+)/", $post['raw_squidclamav_conf'], $a_match)) {
		$input_errors[] = "SquidClamav warnings redirect points to sample config domain ({$a_match[1]})";
		$input_errors[] = "Change redirect info on 'squidclamav.conf' field to pfSense GUI or an external host.";
	}
	if ($post['raw_cicap_conf']) {
		if (!preg_match("/squid_clamav/", $post['raw_cicap_conf'])) {
			$input_errors[] = "c-icap Squidclamav service definition is not present.";
			$input_errors[] = "Add 'Service squid_clamav squidclamav.so'(without quotes) to 'c-icap.conf' field in order to get it working.";
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

	if ($post['clamav_url']) {
		if (!filter_var($post['clamav_url'], FILTER_VALIDATE_URL)) {
			$input_errors[] = "'Redirect URL' is not a valid URL.";
		}
	}
}

?>
