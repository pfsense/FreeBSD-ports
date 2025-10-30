<?php
/*
 * squid_antivirus.inc
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

/* Functions for Squid C-ICAP/ClamAV integration */
require_once('config.inc');
require_once('globals.inc');
require_once('services.inc');
require_once('service-utils.inc');
require_once('util.inc');
/* This file is currently only being included in squid.inc and not used separately */
// require_once('squid.inc');

global $clamav_uid, $clamav_gid;
$clamav_uid = "clamav";
$clamav_gid = "clamav";

/* If /var is in RAM, the AV database will not be persistent there and space is
	limited, so relocate it. */
if (config_path_enabled('system', 'use_mfs_tmpvar')) {
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
	if (is_platform_booting()) {
		return;
	}

	$freshclam_cmd = (SQUID_BASE . "/bin/freshclam --config-file=" . SQUID_BASE . "/etc/freshclam.conf");
	if (($should_install) && (squid_enabled())) {
		$antivirus_config = config_get_path('installedpackages/squidantivirus/config/0', []);
		if ($antivirus_config['clamav_update'] != "") {
			logger(LOG_INFO, localize_text("Adding freshclam cronjob."), LOG_PREFIX_PKG_SQUID);
			// Randomize minutes to mitigate mirrors overload issues
			$minutes = rand(0,59);
			$hours = $antivirus_config['clamav_update'];
			install_cron_job("{$freshclam_cmd}", true, "{$minutes}", "*/{$hours}", "*", "*", "*", "clamav");
		} else {
			logger(LOG_INFO, localize_text("Removing freshclam cronjob."), LOG_PREFIX_PKG_SQUID);
			install_cron_job("{$freshclam_cmd}", false);
		}
	} else {
		logger(LOG_INFO, localize_text("Removing freshclam cronjob."), LOG_PREFIX_PKG_SQUID);
		install_cron_job("{$freshclam_cmd}", false);
	}
}

/* Manually update ClamAV virus definitions via the GUI button */
function squid_update_clamav() {
	squid_check_antivirus_dirs();
	logger(LOG_INFO, localize_text("Updating ClamAV definitions now... This will take a while. Check freshclam log on the 'Real Time' tab for progress information."), LOG_PREFIX_PKG_SQUID);
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

	/* SafeBrowsing is outdated, see https://redmine.pfsense.org/issues/11202 */
	unlink_if_exists(CLAMAV_DBDIR . "safebrowsing.cld");
	unlink_if_exists(CLAMAV_DBDIR . "safebrowsing.cvd");
}

/* Run on Squid package uninstall */
function squid_antivirus_deinstall_command() {
	global $keep;
	/* Stop all running services, remove rc scripts and cronjobs */
	squid_stop_antivirus();
	mwexec("/bin/ps awux | /usr/bin/grep '[f]reshclam' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill");

	/* clean up created directories if 'Keep Settings/Data' is disabled */
	$squidsettings = config_get_path('installedpackages/squid/config/0', []);
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
	/* unset broken antivirus settings */
	config_del_path('installedpackages/squidantivirus/config/0/squidclamav');
	config_del_path('installedpackages/squidantivirus/config/0/icap_conf');
	config_del_path('installedpackages/squidantivirus/config/0/icap_magic');
	config_del_path('installedpackages/squidantivirus/config/0/freshclam_conf');
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
	global $antivirus_config;
	$conf_path = 'installedpackages/squidantivirus/config/0';

	$interserver_dbs = array('interserver256.hdb', 'interservertopline.db', 'shell.ldb', 'whitelist.fp');
	$securiteinfo_dbs = array('securiteinfo.hdb', 'securiteinfo.ign2', 'javascript.ndb', 'spam_marketing.ndb',
		'securiteinfohtml.hdb', 'securiteinfoascii.hdb', 'securiteinfoandroid.hdb', 'securiteinfoold.hdb',
		'securiteinfopdf.hdb');
	$securiteinfo_prem_dbs = array('securiteinfo.mdb', 'securiteinfo0hour.hdb');
	$antivirus_config = config_get_path($conf_path, []);

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
			squid_antivirus_put_raw_config(config_get_path($conf_path));
		} else {
			// unset raw configuration options
			squid_antivirus_toggle_raw_config(false);

			// patch sample files to pfsense dirs
			// squidclamav.conf
			$cf = SQUID_LOCALBASE . "/etc/c-icap/squidclamav.conf";
			if (file_exists("{$cf}.pfsense")) {
				$sample_file = file_get_contents("{$cf}.pfsense");
				$squidclamav_m[] = "@#This file was automatically generated by pfSense@";
				$squidclamav_r[] = "#This file was automatically generated by pfSense WebGUI configuration";
				if ($antivirus_config['clamav_url'] != "") {
					$squidclamav_m[] = "@(redirect http.*)@";
					$squidclamav_r[] = "redirect {$antivirus_config['clamav_url']}";
				}
				if (!empty($antivirus_config['clamav_scan_type']) &&
				    ($antivirus_config['clamav_scan_type'] != "all")) {
					$squidclamav_m[] = "@scan_mode ScanAllExcept@";
					$squidclamav_r[] = "scan_mode ScanNothingExcept\n";
				}
				/* Trying to overwrite squidclamav.conf via file_put_contents() may fail when Squid is running */
				if (!file_put_contents("{$cf}", preg_replace($squidclamav_m, $squidclamav_r, $sample_file), LOCK_EX)) {
					logger(LOG_ERR, localize_text("Could not save generated %s file!", $cf), LOG_PREFIX_PKG_SQUID);
				}
				if ($antivirus_config['clamav_scan_type'] == "web") {
					$scan_add = <<< EOF
# Scan web pages, scripts, images and documents
# web:
scan ^.*\.(htm|html|xhtml|xml|xls|css|csv|rtf|txt|text|jar|js|jsp|json|jnlp|php|pl)(\?.*)?$
scancontent ^text\/.*$
scancontent ^font\/.*$
scancontent ^application\/json$
scancontent ^application\/ld+json$
scancontent ^application\/x-httpd-php$
scancontent ^application\/xhtml+xml$
scancontent ^application\/xml$
scancontent ^application\/vnd.ms-fontobject$
scancontent ^application\/x-javascript$
scancontent ^application\/javascript$
scancontent ^application\/ecmascript$
scancontent ^application\/java-archive$
scancontent ^application\/x-java-applet$
# images:
scan ^.*\.(bmp|gif|jpg|jpeg|png|webp|ico|svg)(\?.*)?$
scancontent ^image\/.*$
# documents:
scan ^.*\.(abw|azw|doc|docx|xls|xlxs|ppt|pptx|odp|ods|odt|epub|vsd|pdf|ps)(\?.*)?$
scancontent ^application\/x-abiword$
scancontent ^application\/vnd.amazon.ebook$
scancontent ^application\/msword$
scancontent ^application\/vnd.openxmlformats-officedocument.wordprocessingml.document$
scancontent ^application\/vnd.ms-fontobject$
scancontent ^application\/epub+zip$
scancontent ^application\/pdf$
scancontent ^application\/postscript$
scancontent ^application\/vnd.oasis.opendocument.presentation$
scancontent ^application\/vnd.oasis.opendocument.spreadsheet$
scancontent ^application\/vnd.oasis.opendocument.text$
scancontent ^application\/vnd.ms-powerpoint$
scancontent ^application\/vnd.openxmlformats-officedocument.presentationml.presentation$
scancontent ^application\/rtf$
scancontent ^application\/vnd.visio$
scancontent ^application\/vnd.ms-excel$
scancontent ^application\/vnd.openxmlformats-officedocument.spreadsheetml.sheet$

EOF;
				} elseif ($antivirus_config['clamav_scan_type'] == "app") {
					$scan_add = <<< EOF
# Scan executables, archives, documents and any application/* MIME types
# executables and scripts:
scan ^.*\.(ade|adp|app|asd|asf|asx|bas|bat|cab|chm|cmd|com|cpl|crt|dll|exe|fxp|hlp|hta|hto|inf|ini|ins|isp|jse?|lib|lnk|mdb|mde|msc|msi|msp|mst|ocx|pcd|pif|prg|reg|scr|sct|sh|shb|shs|sys|url|vb|vbe|vbs|vcs|vxd|wmd|wms|wmz|wsc|wsf|wsh|lsp|sh|ksh|tcsh|zsh|pl|py|pyc)(\?.*)$
scan ^.*\.(csh|bin|jar|mpkg|dmg|apk|xul|jnlp)(\?.*)?$
scancontent ^application\/x-sh$
scancontent ^application\/x-csh$
scancontent ^application\/java-archive$
scancontent ^application\/x-java-applet$
scancontent ^application\/vnd.apple.installer+xml$
scancontent ^application\/vnd.mozilla.xul+xml$
scancontent ^application\/vnd.microsoft.portable-executable$
scancontent ^application\/x-msdownload$
scancontent ^application\/mac-binary$
scancontent ^application\/macbinary$
scancontent ^application\/x-macbinary$
scancontent ^application\/x-binary$
scancontent ^application\/x-bytecode.python$
scancontent ^application\/octet-stream$
scancontent ^text\/x-script.sh$
scancontent ^text\/x-script.ksh$
scancontent ^text\/x-script.tcsh$
scancontent ^text\/x-script.zsh$
scancontent ^text\/x-script.perl$
scancontent ^text\/x-script.perl-module$
scancontent ^text\/x-script.phyton$
# documents:
scan ^.*\.(abw|azw|doc|docx|xls|xlxs|ppt|pptx|odp|ods|odt|epub|vsd|pdf|ps)(\?.*)?$
scancontent ^application\/x-abiword$
scancontent ^application\/vnd.amazon.ebook$
scancontent ^application\/msword$
scancontent ^application\/vnd.openxmlformats-officedocument.wordprocessingml.document$
scancontent ^application\/vnd.ms-fontobject$
scancontent ^application\/epub+zip$
scancontent ^application\/pdf$
scancontent ^application\/postscript$
scancontent ^application\/vnd.oasis.opendocument.presentation$
scancontent ^application\/vnd.oasis.opendocument.spreadsheet$
scancontent ^application\/vnd.oasis.opendocument.text$
scancontent ^application\/vnd.ms-powerpoint$
scancontent ^application\/vnd.openxmlformats-officedocument.presentationml.presentation$
scancontent ^application\/rtf$
scancontent ^application\/vnd.visio$
scancontent ^application\/vnd.ms-excel$
scancontent ^application\/vnd.openxmlformats-officedocument.spreadsheetml.sheet$
# archives:
scan ^.*\.(bz|bz2|cpio|gzip|xz|7z|zip|rar|sfx|arj|arc|tar|tgz|zoo)(\?.*)?$
scancontent ^application\/x-bzip$
scancontent ^application\/x-bzip2$
scancontent ^application\/x-gzip$
scancontent ^application\/x-7z-compressed$
scancontent ^application\/zip$
scancontent ^application\/vnd.rar$
scancontent ^application\/arj$
scancontent ^application\/x-tar$
scancontent ^application\/gnutar$
scancontent ^application\/x-compressed$

EOF;
				} elseif ((empty($antivirus_config['clamav_scan_type']) ||
				    ($antivirus_config['clamav_scan_type'] == "all")) &&
				    ($antivirus_config['clamav_disable_stream_scanning'] == "on")) {
					$scan_add = <<< EOF
# Do not scan (streamed) videos and audios
abort ^.*\.(wav|aiff|ogg|flac|opus|flv|f4f|m2a|mjpeg|mov|mp(2|3|4))(\?.*)?$
abort ^.*\.(avi|avs|mpg|asf|mkv|dv|m1v|m2v|m3u|pls|wmx|aac|mpeg|ogm|ogv|ts)(\?.*)?$
abortcontent ^video\/x-flv$
abortcontent ^audio\/aiff$
abortcontent ^video\/mp4$
abortcontent ^audio\/mp4$
abortcontent ^.*audio\/mp4.*$
abortcontent ^video\/webm$
abortcontent ^audio\/webm$
abortcontent ^video\/mp2t$
abortcontent ^audio\/wmx$
abortcontent ^audio\/mpeg$
abortcontent ^audio\/x-mpeg$
abortcontent ^audio\/aac$
abortcontent ^video\/x-msvideo$
abortcontent ^video\/msvideo$
abortcontent ^video\/avi$
abortcontent ^video\/mpeg$
abortcontent ^video\/x-mpeg$
abortcontent ^video\/ogg$
abortcontent ^audio\/ogg$
abortcontent ^audio\/opus$
abortcontent ^video\/mp2t$
abortcontent ^audio\/wav$
abortcontent ^video\/3gpp$
abortcontent ^audio\/3gpp$
abortcontent ^video\/3gpp2$
abortcontent ^audio\/3gpp2$
abortcontent ^video\/x-motion-jpeg$
abortcontent ^video\/x-dv$
abortcontent ^video\/x-ms-asf$
abortcontent ^video\/quicktime$
abortcontent ^.*application\/x-mms-framed.*$

EOF;
				}
				if ($scan_add && !file_put_contents("{$cf}", "{$scan_add}", FILE_APPEND | LOCK_EX)) {
					logger(LOG_ERR, localize_text("Could not add extra scan configuration to %s file!", $cf), LOG_PREFIX_PKG_SQUID);
				}
			} else {
				logger(LOG_ERR, localize_text("Template not found; could not generate '%s' file!", $cf), LOG_PREFIX_PKG_SQUID);
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
					logger(LOG_ERR, localize_text("Could not save generated %s file!", $cf), LOG_PREFIX_PKG_SQUID);
				}
			} else {
				logger(LOG_ERR, localize_text("Template not found; could not generate '%s' file!", $cf), LOG_PREFIX_PKG_SQUID);
			}

			// c-icap.magic
			// just make a copy of pfSense template, nothing configurable via GUI options here
			$cf = SQUID_LOCALBASE . "/etc/c-icap/c-icap.magic";
			if (file_exists("{$cf}.pfsense")) {
				if (!copy("{$cf}.pfsense", "{$cf}")) {
					logger(LOG_ERR, localize_text("Could not save %s file!", $cf), LOG_PREFIX_PKG_SQUID);
				}
			} else {
				logger(LOG_ERR, localize_text("Template not found; could not save '%s' file!", $cf), LOG_PREFIX_PKG_SQUID);
			}

			// freshclam.conf
			$cf = SQUID_LOCALBASE . "/etc/freshclam.conf";
			if (file_exists("{$cf}.pfsense")) {
				$extra_sig = '';
				$sample_file = file_get_contents("{$cf}.pfsense");
				$freshclam_m[] = "@#This file was automatically generated by pfSense@";
				$freshclam_r[] = "#This file was automatically generated by pfSense WebGUI configuration";
				$freshclam_m[] = "@DatabaseDirectory /var/db/clamav@";
				$freshclam_r[] = "DatabaseDirectory " . CLAMAV_DBDIR;
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
					$freshclam_m[] = "@DatabaseMirror database.clamav.net@";
					$freshclam_r[] = "{$clamav_mirrors}";
				}
				if ($antivirus_config['urlhaus_sig'] == "on") {
					$extra_sig .= "\n#URLhaus signatures\n";
					$extra_sig .= "DatabaseCustomURL https://urlhaus.abuse.ch/downloads/urlhaus.ndb\n";
				}
				if ($antivirus_config['interserver_sig'] == "on") {
					$extra_sig .= "\n#InterServer signatures\n";
					foreach ($interserver_dbs as $db) {
						$extra_sig .= "DatabaseCustomURL http://sigs.interserver.net/{$db}\n";
					}
				}
				if ($antivirus_config['securiteinfo_sig'] == "on") {
					$extra_sig .= "\n#SecuriteInfo signatures\n";
					foreach ($securiteinfo_dbs as $db) {
						$extra_sig .= "DatabaseCustomURL https://www.securiteinfo.com/get/signatures/";
						$extra_sig .= $antivirus_config['securiteinfo_id'] . "/" . $db . "\n" ;
					}
					if ($antivirus_config['securiteinfo_premium'] == "on") {
						$extra_sig = "\n#SecuriteInfo Premium signatures\n";
						foreach ($securiteinfo_prem_dbs as $db) {
							$extra_sig .= "DatabaseCustomURL https://www.securiteinfo.com/get/signatures/";
							$extra_sig .= $antivirus_config['securiteinfo_id'] . "/" . $db . "\n";
						}
					}
				}
				if (!file_put_contents("{$cf}", preg_replace($freshclam_m, $freshclam_r, $sample_file) . $extra_sig, LOCK_EX)) {
					logger(LOG_ERR, localize_text("Could not save generated %s file!", $cf), LOG_PREFIX_PKG_SQUID);
				}
			} else {
				logger(LOG_ERR, localize_text("Template not found; could not generate '%s' file!", $cf), LOG_PREFIX_PKG_SQUID);
			}

			// clamd.conf
			$cf = SQUID_LOCALBASE . "/etc/clamd.conf";
			if (file_exists("{$cf}.pfsense")) {
				$sample_file = file_get_contents("{$cf}.pfsense");
				$clamd_m[0] = "@#This file was automatically generated by pfSense@";
				$clamd_r[0] = "#This file was automatically generated by pfSense WebGUI configuration";
				$clamd_m[1] = "@DatabaseDirectory /var/db/clamav@";
				$clamd_r[1] = "DatabaseDirectory " . CLAMAV_DBDIR;
				if ($antivirus_config['clamav_block_pua'] == "on") {
					$clamd_m[2] = "@#DetectPUA yes@";
					$clamd_r[2] = "DetectPUA yes\n";
				}
				if (!file_put_contents("{$cf}", preg_replace($clamd_m, $clamd_r, $sample_file), LOCK_EX)) {
					logger(LOG_ERR, localize_text("Could not save generated %s file!", $cf), LOG_PREFIX_PKG_SQUID);
				}
			} else {
				logger(LOG_ERR, localize_text("Template not found; could not generate '%s' file!", $cf), LOG_PREFIX_PKG_SQUID);
			}
			unset($cf);
		}

		// freshclam cronjob
		squid_install_freshclam_cron(true);

	}
	if ($antivirus_config['urlhaus_sig'] != "on") {
		unlink_if_exists(CLAMAV_DBDIR . "urlhaus.ndb");
	}
	if ($antivirus_config['interserver_sig'] != "on") {
		foreach ($interserver_dbs as $db) {
			unlink_if_exists(CLAMAV_DBDIR . $db);
		}
	}
	if ($antivirus_config['securiteinfo_sig'] != "on") {
		foreach ($securiteinfo_dbs as $db) {
			unlink_if_exists(CLAMAV_DBDIR . $db);
		}
	}
	if ($antivirus_config['securiteinfo_premium'] != "on") {
		foreach ($securiteinfo_prem_dbs as $db) {
			unlink_if_exists(CLAMAV_DBDIR . $db);
		}
	}
	// this will (re)start or stop/disable services as needed
	// depending on whether Squid proxy and/or antivirus features are enabled
	squid_restart_antivirus();

	return $conf;
}

/* Patch paths and settings in configuration files template for pfSense-specific values on install */
function squid_antivirus_install_config_files() {
	global $clamav_uid, $clamav_gid;

	// squidclamav.conf
	// make a backup of default squidclamav.conf.sample first
	$cf = SQUID_LOCALBASE . "/etc/c-icap/squidclamav.conf";
	if (file_exists("{$cf}.sample")) {
		copy("{$cf}.sample", "{$cf}.default");
	} else {
		logger(LOG_ERR, localize_text("Could not backup '%s' sample file!", $cf), LOG_PREFIX_PKG_SQUID);
	}
	if (file_exists("{$cf}.default")) {
		$sample_file = file_get_contents("{$cf}.default");
		$squidclamav_m[0] = "@# SquidClamav default configuration file@";
		$squidclamav_r[0] = "#This file was automatically generated by pfSense";
		$squidclamav_m[1] = "@/var/run/clamav/clamd.ctl@";
		$squidclamav_r[1] = "/var/run/clamav/clamd.sock";
		$squidclamav_m[2] = "@http\://proxy.domain.dom/cgi-bin/clwarn.cgi@";
		$port = config_get_path('system/webgui/port');
		$squidclamav_r[2] = config_get_path('system/webgui/protocol') . '://' .
		    config_get_path('system/hostname') . '.' . config_get_path('system/domain') .
		    (empty($port) ? '' : ":{$port}") . '/squid_clwarn.php';
		$squidclamav_m[3] = "@dnslookup\s1@";
		$squidclamav_r[3] = "dnslookup 0";
		// This should match StreamMaxLength in clamd.conf
		$squidclamav_m[4] = "@maxsize\s+5000000@";
		$squidclamav_r[4] = "maxsize 25000000";
		if (!file_put_contents("{$cf}.pfsense", preg_replace($squidclamav_m, $squidclamav_r, $sample_file), LOCK_EX)) {
			logger(LOG_ERR, localize_text("Could not save patched '%s' template file!", "{$cf}.pfsense"), LOG_PREFIX_PKG_SQUID);
		}
	} else {
		logger(LOG_ERR, localize_text("Could not patch '%s' template file!", $cf), LOG_PREFIX_PKG_SQUID);
	}

	// c-icap.conf
	// make a backup of default c-icap.conf.sample first
	$cf = SQUID_LOCALBASE . "/etc/c-icap/c-icap.conf";
	if (file_exists("{$cf}.sample")) {
		copy("{$cf}.sample", "{$cf}.default");
	} else {
		logger(LOG_ERR, localize_text("Could not backup '%s' sample file!", $cf), LOG_PREFIX_PKG_SQUID);
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
			logger(LOG_ERR, localize_text("Could not save patched '%s' template file!", "{$cf}.pfsense"), LOG_PREFIX_PKG_SQUID);
		}
	} else {
		logger(LOG_ERR, localize_text("Could not patch '%s' template file!", $cf), LOG_PREFIX_PKG_SQUID);
	}

	// c-icap.magic
	// just make a backup and pfSense template copies of default c-icap.magic, we are not patching anything here
	$cf = SQUID_LOCALBASE . "/etc/c-icap/c-icap.magic";
	if (file_exists("{$cf}.sample")) {
		copy("{$cf}.sample", "{$cf}.default");
		copy("{$cf}.sample", "{$cf}.pfsense");
	} else {
		logger(LOG_ERR, localize_text("Could not backup '%s' sample file!", $cf), LOG_PREFIX_PKG_SQUID);
	}

	// clamd.conf
	// make a backup of default clamd.conf.sample first
	$cf = SQUID_LOCALBASE . "/etc/clamd.conf";
	if (file_exists("{$cf}.sample")) {
		copy("{$cf}.sample", "{$cf}.default");
	} else {
		logger(LOG_ERR, localize_text("Could not backup '%s' sample file!", $cf), LOG_PREFIX_PKG_SQUID);
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
			logger(LOG_ERR, localize_text("Could not save patched '%s' template file!", "{$cf}.pfsense"), LOG_PREFIX_PKG_SQUID);
		}
	} else {
		logger(LOG_ERR, localize_text("Could not patch '%s' template file!", $cf), LOG_PREFIX_PKG_SQUID);
	}

	// freshclam.conf
	// make a backup of default freshclam.conf.sample first
	$cf = SQUID_LOCALBASE . "/etc/freshclam.conf";
	if (file_exists("{$cf}.sample")) {
		copy("{$cf}.sample", "{$cf}.default");
	} else {
		logger(LOG_ERR, localize_text("Could not backup '%s' sample file!", $cf), LOG_PREFIX_PKG_SQUID);
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
			logger(LOG_ERR, localize_text("Could not save patched '%s' template file!", "{$cf}.pfsense"), LOG_PREFIX_PKG_SQUID);
		}
	} else {
		logger(LOG_ERR, localize_text("- could not patch '%s' template file!", $cf), LOG_PREFIX_PKG_SQUID);
	}
	unset($cf);
}

/* Get the raw pfSense template files for manual configuration and serialize them to config.xml */
function squid_antivirus_get_raw_config() {
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
			$conf_path = 'installedpackages/squidantivirus/config/0';
			$conffile = SQUID_LOCALBASE . "/etc" . "{$confdir}" . "/{$rawfile}.pfsense";
			if (file_exists($conffile)) {
				if (empty(config_get_path($conf_path . "/{$confopt}"))) {
					config_set_path($conf_path . "/{$confopt}", base64_encode(str_replace("\r", "", file_get_contents("{$conffile}"))));
					logger(LOG_INFO, localize_text("Successfully loaded '%s' configuration file", $conffile), LOG_PREFIX_PKG_SQUID);
					$loaded = true;
				}
			// Just a fallback attempt if people do things in weird order on a completely fresh install perhaps; should not be ever needed
			} else {
				squid_antivirus_install_config_files();
				if (file_exists($conffile)) {
					config_set_path($conf_path . "/{$confopt}", base64_encode(str_replace("\r", "", file_get_contents("{$conffile}"))));
					logger(LOG_INFO, localize_text("Successfully loaded '%s' configuration file", $conffile), LOG_PREFIX_PKG_SQUID);
					$loaded = true;
				} else {
					logger(LOG_ERR, localize_text("'%s' template does not exist; could not load advanced %s configuration!", $conffile, $rawfile), LOG_PREFIX_PKG_SQUID);
				}
			}
		}
	}
	if ($loaded) {
		write_config("Squid - Loaded raw configuration files", false);
		logger(LOG_INFO, localize_text("Successfully loaded raw configuration files"), LOG_PREFIX_PKG_SQUID);
	}
}

/* Toggle the raw config state */
function squid_antivirus_toggle_raw_config($state) {
	$conf_path = 'installedpackages/squidantivirus/config/0';
	if ($state) {
		// manual configuration enabled
		$opts = array("clamav_url", "clamav_dbregion", "clamav_dbservers");
		foreach ($opts as $opt) {
			if (config_path_enabled($conf_path, $opt)) {
				config_del_path($conf_path . "/{$opt}");
				logger(LOG_INFO, localize_text("Loaded '%s' raw configuration file...", $opt), LOG_PREFIX_PKG_SQUID);
			}
		}
		logger(LOG_INFO, localize_text("Loading raw configuration files..."), LOG_PREFIX_PKG_SQUID);
		squid_antivirus_get_raw_config();
	} else {
		// manual configuration disabled
		$opts = array("raw_squidclamav_conf", "raw_cicap_conf", "raw_cicap_magic", "raw_freshclam_conf", "raw_clamd_conf");
		foreach ($opts as $opt) {
			if (config_path_enabled($conf_path, $opt)) {
				config_del_path($conf_path . "/{$opt}");
				logger(LOG_INFO, localize_text("Unloaded '%s' raw configuration.", $opt), LOG_PREFIX_PKG_SQUID);
			}
		}
		config_set_path($conf_path . '/enable_advanced', 'disabled');
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
			logger(LOG_ERR, localize_text("Could not save '%s' configuration file.", $cfgfile), LOG_PREFIX_PKG_SQUID);
		} else {
			logger(LOG_INFO, localize_text("Saved '%s' configuration file.", $cfgfile), LOG_PREFIX_PKG_SQUID);
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

	logger(LOG_INFO, localize_text("Creating '%s' rc script.", 'clamd.sh'), LOG_PREFIX_PKG_SQUID);
	write_rcfile($rc);
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
	logger(LOG_INFO, localize_text("Creating '%s' rc script.", $c_icap_rcfile), LOG_PREFIX_PKG_SQUID);
	write_rcfile(array(
		"file" => "{$c_icap_rcfile}",
		"start" => "{$cicap_start_cmd}",
		"stop" => "{$cicap_stop_cmd}"
		)
	);
}

/* (Re)start antivirus services if AV features are enabled */
function squid_restart_antivirus() {
	$antivirus_config = config_get_path('installedpackages/squidantivirus/config/0', []);

	// reconfigure and (re)start service as needed if enabled, otherwise stop them
	// do not (re)start antivirus services on boot
	if (is_platform_booting()) {
		logger(LOG_INFO, localize_text("Skipping antivirus services (re)start on boot."), LOG_PREFIX_PKG_SQUID);
		return;
	}

	if (squid_enabled() && ($antivirus_config['enable'] == "on")) {
		// Check clamav database
		if (count(glob(CLAMAV_DBDIR . "*d")) == 0) {
			logger(LOG_WARNING, localize_text("Missing %s %s or %s files. Running freshclam in background.", CLAMAV_DBDIR, '*.cvd', '*.cld'), LOG_PREFIX_PKG_SQUID);
			logger(LOG_WARNING, localize_text("Do NOT attempt to start ClamAV service until AV definitions are downloaded."), LOG_PREFIX_PKG_SQUID);
			squid_update_clamav();
		} elseif ($antivirus_config['enable_advanced'] != "enabled") {
			if ($antivirus_config['clamav_safebrowsing'] == "on") {
				if (!is_file(CLAMAV_DBDIR . "safebrowsing.cvd") && !is_file(CLAMAV_DBDIR . "safebrowsing.cld")) {
					logger(LOG_WARNING, localize_text("Google Safe Browsing is enabled but missing %s definitions. Running freshclam in background.", 'safebrowsing.cvd'), LOG_PREFIX_PKG_SQUID);
					logger(LOG_INFO, localize_text("ClamAV will be automatically notified about the new definitions when finished. No manual action necessary."), LOG_PREFIX_PKG_SQUID);
					squid_update_clamav();
				}
			} else {
				unlink_if_exists(CLAMAV_DBDIR . "safebrowsing.cld");
				unlink_if_exists(CLAMAV_DBDIR . "safebrowsing.cvd");
				logger(LOG_INFO, localize_text("Google Safe Browsing is disabled. Removing %s definitions.", 'safebrowsing.c[lv]d'), LOG_PREFIX_PKG_SQUID);
			}
		}

		// start/reload clamav
		$clamd_rcfile = "/usr/local/etc/rc.d/clamd.sh";
		squid_write_clamd_rcfile();
		if (is_process_running("clamd")) {
			logger(LOG_INFO, localize_text("Reloading ClamAV..."), LOG_PREFIX_PKG_SQUID);
			$reload_cmd = SQUID_BASE . "/bin/clamdscan --reload";
			mwexec_bg("{$reload_cmd}");
		} else {
			logger(LOG_INFO, localize_text("Starting ClamAV..."), LOG_PREFIX_PKG_SQUID);
			mwexec_bg("{$clamd_rcfile} start");
		}

		// check c-icap rcfile
		$c_icap_rcfile = "/usr/local/etc/rc.d/c-icap.sh";
		if (!file_exists($c_icap_rcfile)) {
			squid_write_cicap_rcfile();
		}
		if (is_process_running("c-icap")) {
			logger(LOG_INFO, localize_text("Reloading %s...", 'C-ICAP'), LOG_PREFIX_PKG_SQUID);
			if (file_exists("/var/run/c-icap/c-icap.ctl")) {
				mwexec_bg('/bin/echo -n "reconfigure" > /var/run/c-icap/c-icap.ctl');
			} else {
				mwexec_bg("{$c_icap_rcfile} restart");
			}
		} else {
			logger(LOG_INFO, localize_text("Starting %s...", 'C-ICAP'), LOG_PREFIX_PKG_SQUID);
			mwexec_bg("{$c_icap_rcfile} start");
		}
	} else {
		// stop AV services and disable all C-ICAP/AV features
		logger(LOG_INFO, localize_text("Antivirus features disabled."), LOG_PREFIX_PKG_SQUID);
		squid_stop_antivirus();
	}
}

/* Stop AV services and disable all C-ICAP/AV features */
function squid_stop_antivirus() {
	// Stop C-ICAP
	if (is_process_running("c-icap")) {
		logger(LOG_INFO, localize_text("Stopping and disabling %s...", 'C-ICAP'), LOG_PREFIX_PKG_SQUID);
		mwexec("/usr/local/etc/rc.d/c-icap.sh stop");
		if (is_process_running("c-icap")) {
			logger(LOG_INFO, localize_text("%s still running, forcibly killing %s process(es).", 'C-ICAP', 'c-icap'), LOG_PREFIX_PKG_SQUID);
			mwexec("/bin/ps awux | /usr/bin/grep '[c]-icap' | /usr/bin/awk '{ print $2 }' | /usr/bin/xargs kill -9");
			unlink_if_exists("/var/run/c-icap/c-icap.ctl");
		}
	}
	unlink_if_exists("/usr/local/etc/rc.d/c-icap.sh");

	// Stop ClamAV
	if (is_process_running("clamd")) {
		logger(LOG_INFO, localize_text("Stopping and disabling ClamAV..."), LOG_PREFIX_PKG_SQUID);
		mwexec("/usr/bin/killall clamd");
		// sleep for a couple of seconds to give clamd a chance to perform clean exit
		for ($i = 0; $i < 10; $i++) {
			if (is_process_running('clamd')) {
				sleep(1);
			}
		}
	}
	if (is_process_running("clamd")) {
		logger(LOG_INFO, localize_text("ClamAV still running, forcibly killing %s process(es).", 'clamd'), LOG_PREFIX_PKG_SQUID);
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
	/* Manual ClamAV database update */
	if ($post['update_av'] == 'Update AV') {
		squid_update_clamav();
		return;
	}

	/* Load the raw config files if manual configuration is enabled */
	if ($post['load_advanced'] == 'Load Advanced') {
		config_set_path('installedpackages/squidantivirus/config/0/enable_advanced', 'enabled');
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

	if (($post['securiteinfo_sig'] == 'on') && empty($post['securiteinfo_id'])) {
		$input_errors[] = "A valid SecuriteInfo ID required.";
	}
}

?>
