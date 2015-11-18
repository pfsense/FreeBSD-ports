<?php
/*
 * snort_post_install.php
 *
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009-2010 Robert Zelaya
 * Copyright (C) 2011-2012 Ermal Luci
 * Copyright (C) 2013-2014 Bill Meeks
 * part of pfSense
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

/****************************************************************************/
/* This module is called once during the Snort package installation to      */
/* perform required post-installation setup.  It should only be executed    */
/* from the Package Manager process via the custom-post-install hook in     */
/* the snort.xml package configuration file.                                */
/****************************************************************************/

require_once("config.inc");
require_once("functions.inc");
require_once("/usr/local/pkg/snort/snort.inc");
require("/usr/local/pkg/snort/snort_defs.inc");

global $config, $g, $rebuild_rules, $pkg_interface, $snort_gui_include, $static_output;

$snortdir = SNORTDIR;
$snortlogdir = SNORTLOGDIR;
$rcdir = RCFILEPREFIX;
$flowbit_rules_file = FLOWBITS_FILENAME;
$snort_enforcing_rules_file = SNORT_ENFORCING_RULES_FILENAME;

/* Hard kill any running Snort processes that may have been started by any   */
/* of the pfSense scripts such as check_reload_status() or rc.start_packages */
if(is_process_running("snort")) {
	exec("/usr/bin/killall -z snort");
	sleep(2);
	// Delete any leftover snort PID files in /var/run
	unlink_if_exists("{$g['varrun_path']}/snort_*.pid");
}
// Hard kill any running Barnyard2 processes
if(is_process_running("barnyard")) {
	exec("/usr/bin/killall -z barnyard2");
	sleep(2);
	// Delete any leftover barnyard2 PID files in /var/run
	unlink_if_exists("{$g['varrun_path']}/barnyard2_*.pid");
}

/* Set flag for post-install in progress */
$g['snort_postinstall'] = true;

/* Set conf partition to read-write so we can make changes there */
conf_mount_rw();

/* cleanup default files */
@rename("{$snortdir}/snort.conf-sample", "{$snortdir}/snort.conf");
@rename("{$snortdir}/threshold.conf-sample", "{$snortdir}/threshold.conf");
@rename("{$snortdir}/sid-msg.map-sample", "{$snortdir}/sid-msg.map");
@rename("{$snortdir}/unicode.map-sample", "{$snortdir}/unicode.map");
@rename("{$snortdir}/file_magic.conf-sample", "{$snortdir}/file_magic.conf");
@rename("{$snortdir}/classification.config-sample", "{$snortdir}/classification.config");
@rename("{$snortdir}/generators-sample", "{$snortdir}/generators");
@rename("{$snortdir}/reference.config-sample", "{$snortdir}/reference.config");
@rename("{$snortdir}/gen-msg.map-sample", "{$snortdir}/gen-msg.map");
//@rename("{$snortdir}/attribute_table.dtd-sample", "{$snortdir}/attribute_table.dtd");

/* Move deprecated_rules file to SNORTDIR/rules directory */
@rename("/usr/local/pkg/snort/deprecated_rules", "{$snortdir}/rules/deprecated_rules");

/* fix up the preprocessor rules filenames from a PBI package install */
$preproc_rules = array("decoder.rules", "preprocessor.rules", "sensitive-data.rules");
foreach ($preproc_rules as $file) {
	if (file_exists("{$snortdir}/preproc_rules/{$file}-sample"))
		@rename("{$snortdir}/preproc_rules/{$file}-sample", "{$snortdir}/preproc_rules/{$file}");
}

/* Remove any previously installed scripts since we rebuild them */
unlink_if_exists("{$snortdir}/sid");
unlink_if_exists("{$rcdir}snort.sh");
unlink_if_exists("{$rcdir}barnyard2");

/* Create required log and db directories in /var */
safe_mkdir(SNORTLOGDIR);
safe_mkdir(SNORT_IPREP_PATH);
safe_mkdir(SNORT_SID_MODS_PATH);
safe_mkdir(SNORT_APPID_ODP_PATH);

/* If installed, absorb the Snort Dashboard Widget into this package */
/* by removing it as a separately installed package.                 */
$pkgid = get_pkg_id("Dashboard Widget: Snort");
if ($pkgid >= 0) {
	log_error(gettext("[Snort] Removing legacy 'Dashboard Widget: Snort' package because the widget is now part of the Snort package."));
	unset($config['installedpackages']['package'][$pkgid]);
	unlink_if_exists("/usr/local/pkg/widget-snort.xml");
}

/* Define a default Dashboard Widget Container for Snort */
$snort_widget_container = "snort_alerts-container:col2:close";

/*********************************************************/
/* START OF BUG FIX CODE                                 */
/*                                                       */
/* Remove any Snort cron tasks that may have been left   */
/* from a previous uninstall due to a bug that saved     */
/* edited cron tasks as new ones while still leaving     */
/* the original task.  Correct cron task entries will    */
/* be recreated below if saved settings are detected.    */
/*********************************************************/
$cron_count = 0;
while (snort_cron_job_exists("snort2c", FALSE)) {
	install_cron_job("snort2c", false);
	$cron_count++;
}
if ($cron_count > 0)
	log_error(gettext("[Snort] Removed {$cron_count} duplicate 'remove_blocked_hosts' cron task(s)."));

/*********************************************************/
/* END OF BUG FIX CODE                                   */
/*********************************************************/

/* remake saved settings */
if ($config['installedpackages']['snortglobal']['forcekeepsettings'] == 'on') {
	log_error(gettext("[Snort] Saved settings detected... rebuilding installation with saved settings..."));
	update_status(gettext("Saved settings detected..."));

	/****************************************************************/
	/* Do test and fix for duplicate UUIDs if this install was      */
	/* impacted by the DUP (clone) bug that generated a duplicate   */
	/* UUID for the cloned interface.                               */
	/****************************************************************/
	if (count($config['installedpackages']['snortglobal']['rule']) > 0) {
		$uuids = array();
		$fixed_duplicate = FALSE;
		$snortconf = &$config['installedpackages']['snortglobal']['rule'];
		foreach ($snortconf as &$snortcfg) {
			// Check for and fix a duplicate UUID
			$if_real = get_real_interface($snortcfg['interface']);
			if (!isset($uuids[$snortcfg['uuid']])) {
				$uuids[$snortcfg['uuid']] = $if_real;
				continue;
			}
			else {
				// Found a duplicate UUID, so generate a
				// new one for the affected interface.
				$old_uuid = $snortcfg['uuid'];
				$new_uuid = snort_generate_id();
				if (file_exists("{$snortlogdir}snort_{$if_real}{$old_uuid}/"))
					@rename("{$snortlogdir}snort_{$if_real}{$old_uuid}/", "{$snortlogdir}snort_{$if_real}{$new_uuid}/");
				$snortcfg['uuid'] = $new_uuid;
				$uuids[$new_uuid] = $if_real;
				log_error(gettext("[Snort] updated UUID for interface " . convert_friendly_interface_to_friendly_descr($snortcfg['interface']) . " from {$old_uuid} to {$new_uuid}."));
				$fixed_duplicate = TRUE;
			}
		}
		unset($uuids);
	}
	/****************************************************************/
	/* End of duplicate UUID bug fix.                               */
	/****************************************************************/

	/* Do one-time settings migration for new multi-engine configurations */
	$static_output .= gettext("\nMigrating settings to new configuration...");
	update_output_window($static_output);
	include('/usr/local/pkg/snort/snort_migrate_config.php');
	$static_output .= gettext(" done.\n");
	update_output_window($static_output);
	log_error(gettext("[Snort] Downloading and updating configured rule sets..."));
	if ($pkg_interface <> "console")
		$snort_gui_include = true;
	include('/usr/local/pkg/snort/snort_check_for_rule_updates.php');
	update_status(gettext("Generating snort.conf configuration file from saved settings..."));
	$rebuild_rules = true;
	conf_mount_rw();

	/* Create the snort.conf files for each enabled interface */
	$snortconf = $config['installedpackages']['snortglobal']['rule'];
	foreach ($snortconf as $snortcfg) {
		$if_real = get_real_interface($snortcfg['interface']);
		$snort_uuid = $snortcfg['uuid'];
		$snortcfgdir = "{$snortdir}/snort_{$snort_uuid}_{$if_real}";
		$static_output .= gettext("Generating configuration for " . convert_friendly_interface_to_friendly_descr($snortcfg['interface']) . "...");
		update_output_window($static_output);

		// Pull in the PHP code that generates the snort.conf file
		// variables that will be substituted further down below.
		include("/usr/local/pkg/snort/snort_generate_conf.php");

		// Pull in the boilerplate template for the snort.conf
		// configuration file.  The contents of the template along
		// with substituted variables are stored in $snort_conf_text
		// (which is defined in the included file).
		include("/usr/local/pkg/snort/snort_conf_template.inc");

		// Now write out the conf file using $snort_conf_text contents
		@file_put_contents("{$snortcfgdir}/snort.conf", $snort_conf_text); 
		unset($snort_conf_text);

		// Create the actual rules files and save them in the interface directory
		snort_prepare_rule_files($snortcfg, $snortcfgdir);

		// Clean up variables we no longer need and free memory
		unset($snort_conf_text, $selected_rules_sections, $suppress_file_name, $snort_misc_include_rules, $spoink_type, $snortunifiedlog_type, $alertsystemlog_type);
		unset($home_net, $external_net, $ipvardef, $portvardef);

		// Create barnyard2.conf file for interface
		if ($snortcfg['barnyard_enable'] == 'on')
			snort_generate_barnyard2_conf($snortcfg, $if_real);

		$static_output .= gettext(" done.\n");
		update_output_window($static_output);
	}

	/* create snort bootup file snort.sh */
	$static_output .= gettext("Generating snort.sh script in {$rcdir}...");
	update_output_window($static_output);
	snort_create_rc();
	$static_output .= gettext(" done.\n");
	update_output_window($static_output);

	/* Set Log Limit, Block Hosts Time and Rules Update Time */
	snort_snortloglimit_install_cron(true);
	snort_rm_blocked_install_cron($config['installedpackages']['snortglobal']['rm_blocked'] != "never_b" ? true : false);
	snort_rules_up_install_cron($config['installedpackages']['snortglobal']['autorulesupdate7'] != "never_up" ? true : false);

	/* Restore the last Snort Dashboard Widget setting if none is set */
	if (!empty($config['installedpackages']['snortglobal']['dashboard_widget']) && 
	    stristr($config['widgets']['sequence'], "snort_alerts-container") === FALSE)
		$config['widgets']['sequence'] .= "," . $config['installedpackages']['snortglobal']['dashboard_widget'];

	$rebuild_rules = false;
	if ($pkg_interface <> "console")
		update_output_window(gettext("Finished rebuilding Snort configuration files..."));
	log_error(gettext("[Snort] Finished rebuilding installation from saved settings..."));

	/* Only try to start Snort if not in reboot */
	if (!($g['booting'])) {
		if ($pkg_interface <> "console") {
			update_status(gettext("Starting Snort using rebuilt configuration..."));
			$static_output .= gettext("Starting Snort as a background task using the rebuilt configuration... ");
			mwexec_bg("{$rcdir}snort.sh start");
			update_output_window($static_output);
		}
		else
			mwexec_bg("{$rcdir}snort.sh start");
	}
	update_status("");
}

/* We're finished with conf partition mods, return to read-only */
conf_mount_ro();

/* If an existing Snort Dashboard Widget container is not found, */
/* then insert our default Widget Dashboard container.           */
if (stristr($config['widgets']['sequence'], "snort_alerts-container") === FALSE)
	$config['widgets']['sequence'] .= ",{$snort_widget_container}";

/* Update Snort package version in configuration */
$config['installedpackages']['snortglobal']['snort_config_ver'] = $config['installedpackages']['package'][get_pkg_id("snort")]['version'];
write_config("Snort pkg v{$config['installedpackages']['package'][get_pkg_id("snort")]['version']}: post-install configuration saved.");

/* Done with post-install, so clear flag */
unset($g['snort_postinstall']);
log_error(gettext("[Snort] Package post-installation tasks completed..."));
return true;

?>
