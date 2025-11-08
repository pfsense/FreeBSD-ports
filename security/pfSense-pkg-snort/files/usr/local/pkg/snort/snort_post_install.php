<?php
/*
 * snort_post_install.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
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
/* This module is called once during the Snort package installation to      */
/* perform required post-installation setup.  It should only be executed    */
/* from the Package Manager process via the custom-post-install hook in     */
/* the snort.xml package configuration file.                                */
/****************************************************************************/

require_once("config.inc");
require_once("functions.inc");
require_once("service-utils.inc"); // Need this to get RCFILEPREFIX constant
require_once("/usr/local/pkg/snort/snort.inc");
require("/usr/local/pkg/snort/snort_defs.inc");

global $g, $rebuild_rules, $pkg_interface, $snort_gui_include, $static_output;

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

// Remove any LCK files for Snort that might have been left behind
unlink_if_exists("{$g['varrun_path']}/snort_pkg_starting.lck");

/* Set flag for post-install in progress */
$g['snort_postinstall'] = true;

/*****************************************************************/
/* In the event this is a reinstall (or update), then recreate   */
/* critical map, config and preprocessor rules files from the    */
/* package sample templates.                                     */
/*****************************************************************/
$map_files = array("/unicode.map", "/gen-msg.map", "/classification.config", "/reference.config", 
		   "/attribute_table.dtd", "/preproc_rules/preprocessor.rules", 
		   "/preproc_rules/decoder.rules" , "/preproc_rules/sensitive-data.rules" );
foreach ($map_files as $f) {
	if (file_exists(SNORTDIR .  $f . "-sample") && !file_exists(SNORTDIR . $f)) {
		copy(SNORTDIR .  $f . "-sample", SNORTDIR . $f);
	}
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
$pkgid = get_package_id("Dashboard Widget: Snort");
if ($pkgid >= 0) {
	logger(LOG_NOTICE, localize_text("Removing legacy 'Dashboard Widget: Snort' package because the widget is now part of the Snort package."), LOG_PREFIX_PKG_SNORT);
	config_del_path("installedpackages/package/{$pkgid}");
	unlink_if_exists("/usr/local/pkg/widget-snort.xml");
}

/* Define a default Dashboard Widget Container for Snort */
$snort_widget_container = "snort_alerts:col2:open";

/* Remake saved settings if detected */
if (config_get_path('installedpackages/snortglobal/forcekeepsettings') == 'on') {
	logger(LOG_NOTICE, localize_text("Saved settings detected... rebuilding installation with saved settings."), LOG_PREFIX_PKG_SNORT);
	update_status(gettext("Saved settings detected.") . "\n");

	/* Do any required settings migration for new configurations */
	update_status(gettext("Migrating settings to new configuration..."));
	include '/usr/local/pkg/snort/snort_migrate_config.php';
	update_status(gettext(" done.") . "\n");
	if (!is_platform_booting()) {
		logger(LOG_NOTICE, localize_text("Downloading and updating configured rule sets."), LOG_PREFIX_PKG_SNORT);
		update_status(gettext("Downloading configured rule sets. This may take some time...") . "\n");
		include '/usr/local/pkg/snort/snort_check_for_rule_updates.php';
		update_status(gettext("Generating snort.conf configuration file from saved settings.") . "\n");
		$rebuild_rules = true;
	}

	/* Create the snort.conf files for each enabled interface */
	foreach (config_get_path('installedpackages/snortglobal/rule', []) as $snortcfg) {
		$if_real = get_real_interface($snortcfg['interface']);

		/* Skip instance if its real interface is missing in pfSense */
		if ($if_real == "") {
			continue;
		}
		$snort_uuid = $snortcfg['uuid'];
		$snortcfgdir = "{$snortdir}/snort_{$snort_uuid}_{$if_real}";
		update_status(gettext("Generating configuration for " . convert_friendly_interface_to_friendly_descr($snortcfg['interface']) . "..."));

		// Remove any existing dynamic preprocessor library files from
		// the snort_dynamicpreprocessor directory for the interface.
		// The snort.conf file generation code farther down will copy
		// in new ones from '/usr/local/lib/snort_dynamicpreprocessor'.
		mwexec("/bin/rm -rf {$snortcfgdir}/snort_dynamicpreprocessor/*.so");

		// Pull in the PHP code that generates the snort.conf file
		// variables that will be substituted further down below.
		include '/usr/local/pkg/snort/snort_generate_conf.php';

		// Pull in the boilerplate template for the snort.conf
		// configuration file.  The contents of the template along
		// with substituted variables are stored in $snort_conf_text
		// (which is defined in the included file).
		include '/usr/local/pkg/snort/snort_conf_template.inc';

		// Now write out the conf file using $snort_conf_text contents
		@file_put_contents("{$snortcfgdir}/snort.conf", $snort_conf_text); 
		unset($snort_conf_text);

		// Create the actual rules files and save them in the interface directory
		snort_prepare_rule_files($snortcfg, $snortcfgdir);

		// Clean up variables we no longer need and free memory
		unset($selected_rules_sections, $suppress_file_name, $snort_misc_include_rules, $spoink_type, $snortunifiedlog_type, $alertsystemlog_type);
		unset($home_net, $external_net, $ipvardef, $portvardef);
		update_status(gettext(" done.") . "\n");
	}

	/* create snort bootup file snort.sh */
	update_status(gettext("Generating snort.sh script in {$rcdir}..."));
	snort_create_rc();
	update_status(gettext(" done.") . "\n");

	/* Set Log Limit, Block Hosts Time and Rules Update Time */
	snort_snortloglimit_install_cron(true);
	snort_rm_blocked_install_cron(config_get_path('installedpackages/snortglobal/rm_blocked') != "never_b" ? true : false);
	snort_rules_up_install_cron(config_get_path('installedpackages/snortglobal/autorulesupdate7') != "never_up" ? true : false);

	/* Restore the last Snort Dashboard Widget setting if none is set */
	if (!empty(config_get_path('installedpackages/snortglobal/dashboard_widget')) &&
	    stristr(config_get_path('widgets/sequence'), "snort_alerts") === FALSE)
		config_set_path('widgets/sequence', config_get_path('widgets/sequence') . "," . config_get_path('installedpackages/snortglobal/dashboard_widget'));

	$rebuild_rules = false;
	update_status(gettext("Finished rebuilding Snort configuration files.") . "\n");
	logger(LOG_NOTICE, localize_text("Finished rebuilding installation from saved settings."), LOG_PREFIX_PKG_SNORT);
}

/* Default the 'Save settings on deinstall' option to 'on' if not set (as in a green field install) */
if (!config_get_path('installedpackages/snortglobal/forcekeepsettings')) {
	config_set_path('installedpackages/snortglobal/forcekeepsettings', 'on');
}

/* If an existing Snort Dashboard Widget container is not found, */
/* then insert our default Widget Dashboard container.           */
if (stristr(config_get_path('widgets/sequence'), "snort_alerts") === FALSE)
	config_set_path('widgets/sequence', config_get_path('widgets/sequence') . ",{$snort_widget_container}");

/* Update Snort package version in configuration */
config_set_path('installedpackages/snortglobal/snort_config_ver', config_get_path('installedpackages/package/' . get_package_id("snort") . '/version'));
write_config("Snort pkg v" . config_get_path('installedpackages/package/' . get_package_id("snort") . '/version') . ": post-install configuration saved.");

/* Done with post-install, so clear flag */
unset($g['snort_postinstall']);
logger(LOG_NOTICE, localize_text("Package post-installation tasks completed..."), LOG_PREFIX_PKG_SNORT);
return true;

?>
