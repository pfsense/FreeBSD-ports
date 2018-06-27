<?php
/*
 * suricata_post_install.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2017 Bill Meeks
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

global $config, $g, $rebuild_rules, $pkg_interface, $suricata_gui_include;

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
// Hard kill any running Barnyard2 processes
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

// Mount file system read/write so we can modify some files
conf_mount_rw();

// Remove any previously installed script since we rebuild it
unlink_if_exists("{$rcdir}suricata.sh");

// Create the top-tier log directory
safe_mkdir(SURICATALOGDIR);

// Create the IP Rep and SID Mods lists directory
safe_mkdir(SURICATA_SID_MODS_PATH);
safe_mkdir(SURICATA_IPREP_PATH);

// Make sure config variable is an array (PHP7 likes every level to be created individually )
if (!is_array($config['installedpackages']['suricata'])) {
	$config['installedpackages']['suricata'] = array();
}

if (!is_array($config['installedpackages']['suricata']['rule'])) {
	$config['installedpackages']['suricata']['rule'] = array();
}

if (!is_array($config['installedpackages']['suricata']['config'])) {
	$config['installedpackages']['suricata']['config'] = array();
}

if (!is_array($config['installedpackages']['suricata']['config'][0])) {
	$config['installedpackages']['suricata']['config'][0] = array();
}

// Download the latest GeoIP DB updates and create cron task if the feature is not disabled
if ($config['installedpackages']['suricata']['config'][0]['autogeoipupdate'] != 'off') {
	log_error(gettext("[Suricata] Installing free GeoIP country database files..."));
	include("/usr/local/pkg/suricata/suricata_geoipupdate.php");
	install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_geoipupdate.php", TRUE, 0, 0, 8, "*", "*", "root");
}

// Download the latest ET IQRisk updates and create cron task if the feature is not disabled
if ($config['installedpackages']['suricata']['config'][0]['et_iqrisk_enable'] == 'on') {
	log_error(gettext("[Suricata] Installing Emerging Threats IQRisk IP List..."));
	include("/usr/local/pkg/suricata/suricata_etiqrisk_update.php");
	install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_etiqrisk_update.php", TRUE, 0, "*/6", "*", "*", "*", "root");
}

/*********************************************************/
/* START OF BUG FIX CODE                                 */
/*                                                       */
/* Remove any Suricata cron tasks that may have been     */
/* left from a previous uninstall due to a bug that      */
/* saved edited cron tasks as new ones while still       */
/* leaving the original task.  Correct cron task         */
/* entries will be recreated below if saved settings     */
/* are detected.                                         */
/*********************************************************/
$cron_count = 0;
$suri_pf_table = SURICATA_PF_TABLE;
while (suricata_cron_job_exists($suri_pf_table, FALSE)) {
	install_cron_job($suri_pf_table, false);
	$cron_count++;
}
if ($cron_count > 0)
	log_error(gettext("[Suricata] Removed {$cron_count} duplicate 'remove_blocked_hosts' cron task(s)."));

/*********************************************************/
/* END OF BUG FIX CODE                                   */
/*********************************************************/

// remake saved settings if previously flagged
if ($config['installedpackages']['suricata']['config'][0]['forcekeepsettings'] == 'on') {
	log_error(gettext("[Suricata] Saved settings detected... rebuilding installation with saved settings."));
	update_status(gettext("Saved settings detected...") . "\n");

	/****************************************************************/
	/* Do test and fix for duplicate UUIDs if this install was      */
	/* impacted by the DUP (clone) bug that generated a duplicate   */
	/* UUID for the cloned interface.  Also fix any duplicate       */
	/* entries in ['rulesets'] for "dns-events.rules".              */
	/****************************************************************/
	if (count($config['installedpackages']['suricata']['rule']) > 0) {
		$uuids = array();
		$suriconf = &$config['installedpackages']['suricata']['rule'];
		foreach ($suriconf as &$suricatacfg) {
			// Remove any duplicate ruleset names from earlier bug
			$rulesets = explode("||", $suricatacfg['rulesets']);
			$suricatacfg['rulesets'] = implode("||", array_keys(array_flip($rulesets)));

			// Now check for and fix a duplicate UUID
			$if_real = get_real_interface($suricatacfg['interface']);
			if (!isset($uuids[$suricatacfg['uuid']])) {
				$uuids[$suricatacfg['uuid']] = $if_real;
				continue;
			}
			else {
				// Found a duplicate UUID, so generate a
				// new one for the affected interface.
				$old_uuid = $suricatacfg['uuid'];
				$new_uuid = suricata_generate_id();
				if (file_exists("{$suricatalogdir}suricata_{$if_real}{$old_uuid}/"))
					@rename("{$suricatalogdir}suricata_{$if_real}{$old_uuid}/", "{$suricatalogdir}suricata_{$if_real}{$new_uuid}/");
				$suricatacfg['uuid'] = $new_uuid;
				$uuids[$new_uuid] = $if_real;
				log_error(gettext("[Suricata] updated UUID for interface " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']) . " from {$old_uuid} to {$new_uuid}."));
			}
		}
		unset($uuids, $rulesets);
	}
	/****************************************************************/
	/* End of duplicate UUID and "dns-events.rules" bug fix.        */
	/****************************************************************/

	/* Do one-time settings migration for new version configuration */
	update_status(gettext("Migrating settings to new configuration..."));
	include('/usr/local/pkg/suricata/suricata_migrate_config.php');
	update_status(gettext(" done.") . "\n");
	log_error(gettext("[Suricata] Downloading and updating configured rule types."));
	include('/usr/local/pkg/suricata/suricata_check_for_rule_updates.php');
	update_status(gettext("Generating suricata.yaml configuration file from saved settings.") . "\n");
	$rebuild_rules = true;
	conf_mount_rw();

	// Create the suricata.yaml files for each enabled interface
	$suriconf = $config['installedpackages']['suricata']['rule'];
	foreach ($suriconf as $suricatacfg) {
		$if_real = get_real_interface($suricatacfg['interface']);
		$suricata_uuid = $suricatacfg['uuid'];
		$suricatacfgdir = "{$suricatadir}suricata_{$suricata_uuid}_{$if_real}";
		update_status(gettext("Generating YAML configuration file for " . convert_friendly_interface_to_friendly_descr($suricatacfg['interface']) . "..."));

		// Pull in the PHP code that generates the suricata.yaml file
		// variables that will be substituted further down below.
		include("/usr/local/pkg/suricata/suricata_generate_yaml.php");

		// Pull in the boilerplate template for the suricata.yaml
		// configuration file.  The contents of the template along
		// with substituted variables are stored in $suricata_conf_text
		// (which is defined in the included file).
		include("/usr/local/pkg/suricata/suricata_yaml_template.inc");

		// Now write out the conf file using $suricata_conf_text contents
		@file_put_contents("{$suricatacfgdir}/suricata.yaml", $suricata_conf_text);
		unset($suricata_conf_text);

		// create barnyard2.conf file for interface
		if ($suricatacfg['barnyard_enable'] == 'on')
			suricata_generate_barnyard2_conf($suricatacfg, $if_real);

		update_status(gettext(" done.") . "\n");
	}

	// create Suricata bootup file suricata.sh
	suricata_create_rc();

	// Set Log Limit, Block Hosts Time and Rules Update Time
	suricata_loglimit_install_cron(true);
	suricata_rm_blocked_install_cron($config['installedpackages']['suricata']['config'][0]['rm_blocked'] != "never_b" ? true : false);
	suricata_rules_up_install_cron($config['installedpackages']['suricata']['config'][0]['autoruleupdate'] != "never_up" ? true : false);

	// Restore the Dashboard Widget if it was previously enabled and saved
	if (!empty($config['installedpackages']['suricata']['config'][0]['dashboard_widget']) && !empty($config['widgets']['sequence'])) {
		if (strpos($config['widgets']['sequence'], "suricata_alerts") === FALSE)
			$config['widgets']['sequence'] .= "," . $config['installedpackages']['suricata']['config'][0]['dashboard_widget'];
	}
	if (!empty($config['installedpackages']['suricata']['config'][0]['dashboard_widget_rows']) && !empty($config['widgets'])) {
		if (empty($config['widgets']['widget_suricata_display_lines']))
			$config['widgets']['widget_suricata_display_lines'] = $config['installedpackages']['suricata']['config'][0]['dashboard_widget_rows'];
	}

	$rebuild_rules = false;
	update_status(gettext("Finished rebuilding Suricata configuration from saved settings.") . "\n");
	log_error(gettext("[Suricata] Finished rebuilding installation from saved settings."));
}

// If this is first install and "forcekeepsettings" is empty,
// then default it to 'on'.
if (empty($config['installedpackages']['suricata']['config'][0]['forcekeepsettings']))
	update_status(gettext("Setting up initial configuration.") . "\n");
	$config['installedpackages']['suricata']['config'][0]['forcekeepsettings'] = 'on';

// Finished with file system mods, so remount it read-only
conf_mount_ro();

// Update Suricata package version in configuration
update_status(gettext("Setting package version in configuration file.") . "\n");
$config['installedpackages']['suricata']['config'][0]['suricata_config_ver'] = $config['installedpackages']['package'][get_package_id("suricata")]['version'];

// Debug
$dbgs = "New config: \n" . print_r($config['installedpackages']['suricata'], true);
update_status($dbgs);
print($dbgs);
file_put_contents("/tmp/suricat.conf", $dbgs);

write_config("Suricata pkg v{$config['installedpackages']['package'][get_package_id("suricata")]['version']}: post-install configuration saved.");

// Done with post-install, so clear flag
unset($g['suricata_postinstall']);
log_error(gettext("[Suricata] Package post-installation tasks completed."));
return true;

?>
