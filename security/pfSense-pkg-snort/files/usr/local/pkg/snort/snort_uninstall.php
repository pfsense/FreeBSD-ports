<?php
/*
 * snort_uninstall.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009-2010 Robert Zelaya
 * Copyright (c) 2013-2016 Bill Meeks
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
$snortlibdir = SNORT_PBI_BASEDIR . "lib";
$snortlogdir = SNORTLOGDIR;
$rcdir = RCFILEPREFIX;
$snort_rules_upd_log = SNORT_RULES_UPD_LOGFILE;
$mounted_rw = FALSE;

log_error(gettext("[Snort] Snort package uninstall in progress..."));

// Remove our rc.d startup shell script
unlink_if_exists("{$rcdir}snort.sh");

// Make sure all active Snort processes are terminated
// Log a message only if a running process is detected
if (is_process_running("snort")) {
	log_error(gettext("[Snort] Snort STOP on all interfaces..."));
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
	log_error(gettext("[Snort] Barnyard2 STOP on all interfaces..."));
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
$widgets = $config['widgets']['sequence'];
if (!empty($widgets)) {
	$widgetlist = explode(",", $widgets);
	foreach ($widgetlist as $key => $widget) {
		if (strstr($widget, "snort_alerts")) {
			if ($config['installedpackages']['snortglobal']['forcekeepsettings'] == 'on') {
				$config['installedpackages']['snortglobal']['dashboard_widget'] = $widget;
			}
			unset($widgetlist[$key]);
			break;
		}
	}
	$config['widgets']['sequence'] = implode(",", $widgetlist);
	write_config("Snort pkg uninstall removed Dashboard widget.");
}

// See if we are to clear blocked hosts on uninstall
if ($config['installedpackages']['snortglobal']['clearblocks'] == 'on') {
	log_error(gettext("[Snort] Removing all blocked hosts from <snort2c> table..."));
	mwexec("/sbin/pfctl -t snort2c -T flush");
}

// See if we are to clear Snort log files on uninstall
if ($config['installedpackages']['snortglobal']['clearlogs'] == 'on') {
	log_error(gettext("[Snort] Clearing all Snort-related log files..."));
	unlink_if_exists("{$snort_rules_upd_log}");
	rmdir_recursive($snortlogdir);
}

/**********************************************************/
/* If not already, set Snort conf partition to read-write */
/* so we can make changes there                           */
/**********************************************************/
if (!is_subsystem_dirty('mount')) {
	conf_mount_rw();
	$mounted_rw = TRUE;
}

/**********************************************************/
/* Remove files and directories that pkg will not because */
/* we changed or created them post-install.               */
/**********************************************************/
log_error(gettext("[Snort] Removing package files..."));
if (is_dir("{$snortdir}/appid")) {
	rmdir_recursive("{$snortdir}/appid");
}
if (is_dir("{$snortdir}/rules")) {
	rmdir_recursive("{$snortdir}/rules");
}
if (is_dir("{$snortdir}/signatures")) {
	rmdir_recursive("{$snortdir}/signatures");
}
unlink_if_exists("{$snortdir}/*.md5");
unlink_if_exists("{$snortdir}/*.conf");
unlink_if_exists("{$snortdir}/*.map");
unlink_if_exists("{$snortdir}/*.config");
if (is_array($config['installedpackages']['snortglobal']['rule']) && count($config['installedpackages']['snortglobal']['rule']) > 0) {
	foreach ($config['installedpackages']['snortglobal']['rule'] as $snortcfg) {
		$if_real = get_real_interface($snortcfg['interface']);
		$snort_uuid = $snortcfg['uuid'];
		if (is_dir("{$snortdir}/snort_{$snort_uuid}_{$if_real}")) {
			rmdir_recursive("{$snortdir}/snort_{$snort_uuid}_{$if_real}");
		}
	}
}

/**********************************************************/
/* Keep this as a last step because it is the total       */
/* removal of the configuration settings when the user    */
/* has elected to not retain the package configuration.   */
/**********************************************************/
if ($config['installedpackages']['snortglobal']['forcekeepsettings'] != 'on') {
	log_error(gettext("[Snort] Not saving settings... all Snort configuration info and logs will be deleted..."));
	unset($config['installedpackages']['snortglobal']);
	unset($config['installedpackages']['snortsync']);
	unlink_if_exists("{$snort_rules_upd_log}");
	log_error(gettext("[Snort] Flushing <snort2c> firewall table to remove addresses blocked by Snort..."));
	mwexec("/sbin/pfctl -t snort2c -T flush");
	rmdir_recursive("{$snortlogdir}");
	rmdir_recursive("{$g['vardb_path']}/snort");
	log_error(gettext("[Snort] The package has been completely removed from this system."));
}
else {
	log_error(gettext("[Snort] Package files removed but all Snort configuration info has been retained."));
}

/**********************************************************/
/* We're finished with conf partition mods, return to     */
/* read-only if we changed it.                            */
/**********************************************************/
if ($mounted_rw == TRUE) {
	conf_mount_ro();
}
return true;
?>
