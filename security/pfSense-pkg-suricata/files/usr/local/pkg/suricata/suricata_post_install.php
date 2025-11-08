<?php
/*
 * suricata_post_install.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2019-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2023 Bill Meeks
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
/* This module is called once during the Suricata package installation to   */
/* perform required post-installation setup.  It should only be executed    */
/* from the Package Manager process via the custom-post-install hook in     */
/* the suricata.xml package configuration file.                                */
/****************************************************************************/

require_once("config.inc");
require_once("functions.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");
require("/usr/local/pkg/suricata/suricata_defs.inc");

global $g, $rebuild_rules, $pkg_interface, $suricata_gui_include;

// Initialize some common values from defined constants
$suricatadir = SURICATADIR;
$suricatalogdir = SURICATALOGDIR;
$flowbit_rules_file = FLOWBITS_FILENAME;
$suricata_enforcing_rules_file = SURICATA_ENFORCING_RULES_FILENAME;
$rcdir = RCFILEPREFIX;

// Hard kill any running Suricata process that may have been started by any
// of the pfSense scripts such as check_reload_status() or rc.start_packages
if(is_process_running("suricata")) {
	killbyname("suricata");
	sleep(2);
	// Delete any leftover suricata PID files in /var/run
	unlink_if_exists("{$g['varrun_path']}/suricata_*.pid");
}
/******************************************************************/
/* Hard kill any running Barnyard2 processes. Barnyard2 is        */
/* deprecated, but this code is left to ensure no active          */
/* Barnyard2 process remains.                                     */
/******************************************************************/
if(is_process_running("barnyard")) {
	killbyname("barnyard2");
	sleep(2);
	// Delete any leftover barnyard2 PID files in /var/run
	unlink_if_exists("{$g['varrun_path']}/barnyard2_*.pid");
}

// Set flag for post-install in progress
$g['suricata_postinstall'] = true;

// Remove any LCK files for Suricata that might have been left behind
unlink_if_exists("{$g['varrun_path']}/suricata_pkg_starting.lck");

// Remove any previously installed script since we rebuild it
unlink_if_exists("{$rcdir}suricata.sh");

// Create the top-tier log directory
safe_mkdir(SURICATALOGDIR);

// Create the IP Rep and SID Mods lists directory
safe_mkdir(SURICATA_SID_MODS_PATH);
safe_mkdir(SURICATA_IPREP_PATH);

/*****************************************************************/
/* In the event this is a reinstall (or update), then recreate   */
/* critical config files from the package sample templates.      */
/*****************************************************************/
$map_files = array( "classification.config", "reference.config", "threshold.config" );
foreach ($map_files as $f) {
	if (file_exists(SURICATADIR . $f . ".sample") && !file_exists(SURICATADIR . $f)) {
		copy(SURICATADIR . $f . ".sample", SURICATADIR . $f);
	}
}

// Download latest GeoIP DB updates and create cron task if feature is enabled and have a user key
if (config_get_path('installedpackages/suricata/config/0/autogeoipupdate') == 'on' && config_get_path('installedpackages/suricata/config/0/maxmind_geoipdb_key') !== null) {
	logger(LOG_NOTICE, localize_text("Installing free GeoLite2 country IP database file in %s...", '/usr/local/share/suricata/GeoLite2/'), LOG_PREFIX_PKG_SURICATA);
	include '/usr/local/pkg/suricata/suricata_geoipupdate.php';
	install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_geoipupdate.php", TRUE, 0, 6, "*", "*", "*", "root");
}

// Download latest ET IQRisk updates and create cron task if feature is enabled and have user key
if (config_get_path('installedpackages/suricata/config/0/et_iqrisk_enable') == 'on' && config_get_path('installedpackages/suricata/config/0/iqrisk_code') !== null) {
	logger(LOG_NOTICE, localize_text("Installing Emerging Threats IQRisk IP List..."), LOG_PREFIX_PKG_SURICATA);
	include '/usr/local/pkg/suricata/suricata_etiqrisk_update.php';
	install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_etiqrisk_update.php", TRUE, 0, "*/6", "*", "*", "*", "root");
}

// Remake saved settings if previously flagged to restore them
if (config_get_path('installedpackages/suricata/config/0/forcekeepsettings') == 'on') {
	logger(LOG_NOTICE, localize_text("Saved settings detected... rebuilding installation with saved settings."), LOG_PREFIX_PKG_SURICATA);
	update_status(gettext("Saved settings detected...") . "\n");

	/****************************************************************/
	/* Add any new built-in events rules to each configured         */
	/* interface.                                                   */
	/****************************************************************/
	if (count(config_get_path('installedpackages/suricata/rule', [])) > 0) {

		// Add default events rules for Suricata. This array constant
		// is defined in 'suricata_defs.inc' and must be kept in sync
		// with the content of the '/rules' directory in the Suricata
		// binary source tarball.
		$builtin_rules = SURICATA_DEFAULT_RULES;
		foreach (config_get_path('installedpackages/suricata/rule', []) as $idx => &$suricatacfg) {
			$iface_rules_upd = false;

			// Convert delimited string into array and remove any
			// duplicate ruleset names from earlier bug.
			$rulesets = array_keys(array_flip(explode("||", $suricatacfg['rulesets'])));
			foreach ($builtin_rules as $name) {
				if (in_array($name, $rulesets)) {
					continue;
				} else {
					$rulesets[] = $name;
					$iface_rules_upd = true;
				}
			}
			// If we updated the rules list, save the change
			if ($iface_rules_upd) {
				$suricatacfg['rulesets'] = implode("||", $rulesets);
				config_set_path("installedpackages/suricata/rule/{$idx}", $suricatacfg);
			}
		}
		// Done with the config array reference, so release it
		unset($suricatacfg);
	}
	/****************************************************************/
	/* End of built-in events rules fix.                            */
	/****************************************************************/

	// Do one-time settings migration for new version configuration
	update_status(gettext("Migrating settings to new configuration..."));
	include '/usr/local/pkg/suricata/suricata_migrate_config.php';
	update_status(gettext(" done.") . "\n");

	if (!is_platform_booting()) {
		// Update configured rules archives with a fresh download
		logger(LOG_NOTICE, localize_text("Downloading and updating configured rule types."), LOG_PREFIX_PKG_SURICATA);
		include '/usr/local/pkg/suricata/suricata_check_for_rule_updates.php';
		update_status(gettext("Generating suricata.yaml configuration file from saved settings.") . "\n");
		$rebuild_rules = true;
	}

	// Create the suricata.yaml file for each enabled interface
	foreach (config_get_path('installedpackages/suricata/rule', []) as $suricatacfg) {
		$if_real = get_real_interface($suricatacfg['interface']);

		/* Skip instance if its real interface is missing in pfSense */
		if ($if_real == "") {
			continue;
		}
		$suricata_uuid = $suricatacfg['uuid'];
		$suricatacfgdir = "{$suricatadir}suricata_{$suricata_uuid}_{$if_real}";
		update_status(gettext("Generating YAML configuration file for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']) . "..."));

		// Pull in the PHP code that generates the suricata.yaml file
		// variables that will be substituted further down below.
		include '/usr/local/pkg/suricata/suricata_generate_yaml.php';

		// Pull in the boilerplate template for the suricata.yaml
		// configuration file.  The contents of the template along
		// with substituted variables are stored in $suricata_conf_text
		// (which is defined in the included file).
		include '/usr/local/pkg/suricata/suricata_yaml_template.inc';

		// Now write out the conf YAML file using $suricata_conf_text contents
		@file_put_contents("{$suricatacfgdir}/suricata.yaml", $suricata_conf_text);
		unset($suricata_conf_text);
		update_status(gettext(" done.") . "\n");
	}

	// create Suricata bootup shell script file suricata.sh
	suricata_create_rc();

	// Set Log Limit, Block Hosts Removal Interval, and Rules Update Time cron jobs
	suricata_loglimit_install_cron(true);
	suricata_rm_blocked_install_cron(config_get_path('installedpackages/suricata/config/0/rm_blocked') != "never_b" ? true : false);
	suricata_rules_up_install_cron(config_get_path('installedpackages/suricata/config/0/autoruleupdate') != "never_up" ? true : false);

	// Restore the Dashboard Widget if it was previously enabled and saved
	if (!empty(config_get_path('installedpackages/suricata/config/0/dashboard_widget')) && !empty(config_get_path('widgets/sequence'))) {
		if (strpos(config_get_path('widgets/sequence', ''), "suricata_alerts") === FALSE)
			config_set_path('widgets/sequence', config_get_path('widgets/sequence') . "," . config_get_path('installedpackages/suricata/config/0/dashboard_widget'));
	}
	if (!empty(config_get_path('installedpackages/suricata/config/0/dashboard_widget_rows')) && !empty(config_get_path('widgets'))) {
		if (empty(config_get_path('widgets/widget_suricata_display_lines')))
			config_set_path('widgets/widget_suricata_display_lines', config_get_path('installedpackages/suricata/config/0/dashboard_widget_rows'));
	}

	$rebuild_rules = false;
	update_status(gettext("Finished rebuilding Suricata configuration from saved settings.") . "\n");
	logger(LOG_NOTICE, localize_text("Finished rebuilding installation from saved settings."), LOG_PREFIX_PKG_SURICATA);
}

// If this is first install and "forcekeepsettings" is empty,
// then default it to 'on'.
if (config_get_path('installedpackages/suricata/config/0/forcekeepsettings') === null) {
	update_status("   " . gettext("\n  Setting up initial configuration.") . "\n");
	config_set_path('installedpackages/suricata/config/0/forcekeepsettings', 'on');
}

/**********************************************************/
/* Incorporate content of SID Mgmt example files in the   */
/* /var/db/suricata/sidmods directory to Base64 encoded   */
/* strings in SID_MGMT_LIST array in config.xml if this   */
/* is a first-time green field install of Suricata.       */
/**********************************************************/
if (config_get_path('installedpackages/suricata/config/0/sid_list_migration') === null && count(config_get_path('installedpackages/suricata/sid_mgmt_lists', [])) < 1) {
	$a_list = config_get_path('installedpackages/suricata/sid_mgmt_lists/item', []);
	foreach (array("disablesid-sample.conf", "dropsid-sample.conf", "enablesid-sample.conf", "modifysid-sample.conf") as $sidfile) {
		if (file_exists(SURICATA_SID_MODS_PATH . $sidfile)) {
			$data = file_get_contents(SURICATA_SID_MODS_PATH . $sidfile);
			if ($data !== FALSE) {
				$tmp = array();
				$tmp['name'] = basename($sidfile);
				$tmp['modtime'] = filemtime(SURICATA_SID_MODS_PATH . $sidfile);
				$tmp['content'] = base64_encode($data);
				$a_list[] = $tmp;
			}
		}
	}
	config_set_path('installedpackages/suricata/sid_mgmt_lists/item', $a_list);
	config_set_path('installedpackages/suricata/config/0/sid_list_migration', "1");
}

// Update Suricata package version in configuration
update_status(gettext("  " . "Setting package version in configuration file.") . "\n");
config_set_path('installedpackages/suricata/config/0/suricata_config_ver', config_get_path('installedpackages/package/' . get_package_id("suricata") . '/version'));

write_config("Suricata pkg v" . config_get_path('installedpackages/package/' . get_package_id('suricata') . '/version') . ": post-install configuration saved.");

// Done with post-install, so clear flag
unset($g['suricata_postinstall']);
logger(LOG_NOTICE, localize_text("Package post-installation tasks completed."), LOG_PREFIX_PKG_SURICATA);
return true;

?>
