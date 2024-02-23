<?php
/*
 * snort_uninstall.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009-2010 Robert Zelaya
 * Copyright (c) 2013-2022 Bill Meeks
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

/****************************************************************************/
/* This module is called once during the Snort package deinstallation to    */
/* remove Snort components created/modified outside of the pkg install      */
/* process.  It is called via the custom-pre-deinstall hook in the          */
/* the snort.xml package configuration file.                                */
/****************************************************************************/

require_once("config.inc");
require_once("functions.inc");
require_once("service-utils.inc"); // Need this to get RCFILEPREFIX constant
require_once("/usr/local/pkg/snort/snort.inc");
require("/usr/local/pkg/snort/snort_defs.inc");

global $config, $g;

$snortdir = SNORTDIR;
$snortlibdir = SNORT_BASEDIR . "lib";
$snortlogdir = SNORTLOGDIR;
$rcdir = RCFILEPREFIX;
$snort_rules_upd_log = SNORT_RULES_UPD_LOGFILE;

syslog(LOG_NOTICE, gettext("[Snort] Snort package uninstall in progress..."));

// Remove our rc.d startup shell script
unlink_if_exists("{$rcdir}snort.sh");

// Make sure all active Snort processes are terminated
// Log a message only if a running process is detected
if (is_process_running("snort")) {
	syslog(LOG_NOTICE, gettext("[Snort] Snort STOP on all interfaces..."));
	snort_stop_all_interfaces();
}
sleep(2);
mwexec('/usr/bin/killall -z snort', true);
sleep(2);
mwexec('/usr/bin/killall -9 snort', true);
sleep(2);

// Delete any leftover snort PID files in /var/run
unlink_if_exists("{$g['varrun_path']}/snort_*.pid");

// Make sure all active Barnyard2 processes are terminated
// Log a message only if a running process is detected
if (is_process_running("barnyard2")) {
	syslog(LOG_NOTICE, gettext("[Snort] Barnyard2 STOP on all interfaces..."));
}
mwexec('/usr/bin/killall -z barnyard2', true);
sleep(2);
mwexec('/usr/bin/killall -9 barnyard2', true);
sleep(2);

// Delete any leftover barnyard2 PID files in /var/run
unlink_if_exists("{$g['varrun_path']}/barnyard2_*.pid");

// Remove any LCK files for Snort that might have been left behind
unlink_if_exists("{$g['varrun_path']}/snort_pkg_starting.lck");

// Remove all the existing Snort cron jobs.
if (snort_cron_job_exists("snort2c", FALSE)) {
	install_cron_job("snort2c", false);
}
if (snort_cron_job_exists("snort_check_for_rule_updates.php", FALSE)) {
	install_cron_job("snort_check_for_rule_updates.php", false);
}
if (snort_cron_job_exists("snort_check_cron_misc.inc", FALSE)) {
	install_cron_job("snort_check_cron_misc.inc", false);
}

/**********************************************************/
/* Remove our associated Dashboard widget config.  If     */
/* "save settings" is enabled, then save old widget       */
/* container settings so we can restore them later.       */
/**********************************************************/
$widgets = config_get_path('widgets/sequence');
if (!empty($widgets)) {
	$widgetlist = explode(",", $widgets);
	foreach ($widgetlist as $key => $widget) {
		if (strstr($widget, "snort_alerts")) {
			if (config_get_path('installedpackages/snortglobal/forcekeepsettings') == 'on') {
				config_set_path('installedpackages/snortglobal/dashboard_widget', $widget);
			}
			unset($widgetlist[$key]);
			break;
		}
	}
	config_set_path('widgets/sequence', implode(",", $widgetlist));
	write_config("Snort pkg uninstall removed Dashboard widget.");
}

// See if we are to clear blocked hosts on uninstall
if (config_get_path('installedpackages/snortglobal/clearblocks') == 'on') {
	syslog(LOG_NOTICE, gettext("[Snort] Removing all blocked hosts from <snort2c> table..."));
	mwexec("/sbin/pfctl -t snort2c -T flush");
}

// See if we are to clear Snort log files on uninstall
if (config_get_path('installedpackages/snortglobal/clearlogs') == 'on') {
	syslog(LOG_NOTICE, gettext("[Snort] Clearing all Snort-related log files..."));
	unlink_if_exists("{$snort_rules_upd_log}");
	rmdir_recursive($snortlogdir);
}

/**********************************************************/
/* Remove files and directories that pkg will not because */
/* we changed or created them post-install.               */
/**********************************************************/
syslog(LOG_NOTICE, gettext("[Snort] Removing GUI package-modified files..."));
if (is_dir(SNORT_APPID_ODP_PATH)) {
	rmdir_recursive(SNORT_APPID_ODP_PATH);
}
if (is_dir("/usr/local/lib/snort_dynamicrules")) {
	rmdir_recursive("/usr/local/lib/snort_dynamicrules");
}
if (is_dir(SNORTDIR . "/signatures")) {
	rmdir_recursive(SNORTDIR . "/signatures");
}
unlink_if_exists(SNORTDIR . "/*.md5");
unlink_if_exists(SNORTDIR . "/rules/*.txt");
unlink_if_exists(SNORTDIR . "/classification.config");
unlink_if_exists(SNORTDIR . "/reference.config");
unlink_if_exists(SNORTDIR . "/unicode.map");
unlink_if_exists(SNORTDIR . "/rulesupd_status");
unlink_if_exists(SNORTDIR . "/preproc_rules/*.rules");
unlink_if_exists(SNORTDIR . "/rules/" . VRT_FILE_PREFIX . "*.rules");
unlink_if_exists(SNORTDIR . "/rules/" . ET_OPEN_FILE_PREFIX . "*.rules");
unlink_if_exists(SNORTDIR . "/rules/" . ET_PRO_FILE_PREFIX . "*.rules");
unlink_if_exists(SNORTDIR . "/rules/" . GPL_FILE_PREFIX . "*.rules");
unlink_if_exists(SNORTDIR . "/rules/" . "appid.rules");
unlink_if_exists(SNORT_APPID_RULES_PATH . OPENAPPID_FILE_PREFIX . "*.rules");

foreach (config_get_path('installedpackages/snortglobal/rule', []) as $snortcfg) {
	$if_real = get_real_interface($snortcfg['interface']);
	$snort_uuid = $snortcfg['uuid'];
	if (is_dir("{$snortdir}/snort_{$snort_uuid}_{$if_real}")) {
		rmdir_recursive("{$snortdir}/snort_{$snort_uuid}_{$if_real}");
	}
}

/**********************************************************/
/* Clear IP addresses we placed in <snort2c> pf table if  */
/* that option is enabled on GLOBAL SETTINGS tab or if    */
/* the package and its configuration are being removed.   */
/**********************************************************/
if ((config_get_path('installedpackages/snortglobal/clearblocks') != 'off') ||
    (config_get_path('installedpackages/snortglobal/forcekeepsettings') != 'on')) {
	syslog(LOG_NOTICE, gettext("[Snort] Flushing <snort2c> firewall table to remove addresses blocked by Snort..."));
	mwexec("/sbin/pfctl -t snort2c -T flush");
}

/**********************************************************/
/* Keep this as a last step because it is the total       */
/* removal of the configuration settings when the user    */
/* has elected to not retain the package configuration.   */
/**********************************************************/
if (config_get_path('installedpackages/snortglobal/forcekeepsettings') != 'on') {
	syslog(LOG_NOTICE, gettext("[Snort] Not saving settings... all Snort configuration info and logs will be deleted..."));
	config_del_path('installedpackages/snortglobal');
	config_del_path('installedpackages/snortsync');
	unlink_if_exists("{$snort_rules_upd_log}");
	rmdir_recursive("{$snortlogdir}");
	write_config("Removing Snort configuration");
	syslog(LOG_NOTICE, gettext("[Snort] The package and its configuration has been completely removed from this system."));
}
else {
	syslog(LOG_NOTICE, gettext("[Snort] Package files removed but all Snort configuration info has been retained."));
}

return true;
?>
