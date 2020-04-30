<?php
/*
 * suricata_post_install.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2019-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2020 Bill Meeks
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

// Download the latest GeoIP DB updates and create cron task if the feature is enabled
if ($config['installedpackages']['suricata']['config'][0]['autogeoipupdate'] == 'on' && !empty($config['installedpackages']['suricata']['config'][0]['maxmind_geoipdb_key'])) {
	syslog(LOG_NOTICE, gettext("[Suricata] Installing free GeoLite2 country IP database file in /usr/local/share/suricata/GeoLite2/..."));
	include("/usr/local/pkg/suricata/suricata_geoipupdate.php");
	install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_geoipupdate.php", TRUE, 0, 6, "*", "*", "*", "root");
}

// Download the latest ET IQRisk updates and create cron task if the feature is not disabled
if ($config['installedpackages']['suricata']['config'][0]['et_iqrisk_enable'] == 'on') {
	syslog(LOG_NOTICE, gettext("[Suricata] Installing Emerging Threats IQRisk IP List..."));
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

if ($cron_count > 0) {
	syslog(LOG_NOTICE, gettext("[Suricata] Removed {$cron_count} duplicate 'remove_blocked_hosts' cron task(s)."));
}

/*********************************************************/
/* END OF BUG FIX CODE                                   */
/*********************************************************/

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

// remake saved settings if previously flagged
if ($config['installedpackages']['suricata']['config'][0]['forcekeepsettings'] == 'on') {
	syslog(LOG_NOTICE, gettext("[Suricata] Saved settings detected... rebuilding installation with saved settings."));
	update_status(gettext("Saved settings detected...") . "\n");

	/****************************************************************/
	/* Add all the new built-in events rules to each configured     */
	/* interface.                                                   */
	/****************************************************************/
	if (count($config['installedpackages']['suricata']['rule']) > 0) {

		// Array of default events rules for Suricata
		$builtin_rules = array( "app-layer-events.rules", "decoder-events.rules", "dnp3-events.rules", "dns-events.rules", "files.rules", "http-events.rules", "ipsec-events.rules", "kerberos-events.rules", 
					"modbus-events.rules", "nfs-events.rules", "ntp-events.rules", "smb-events.rules", "smtp-events.rules", "stream-events.rules", "tls-events.rules" );

		foreach ($config['installedpackages']['suricata']['rule'] as &$suricatacfg) {
			$rulesets = explode("||", $suricatacfg['rulesets']);
			foreach ($builtin_rules as $name) {
				if (in_array($name, $rulesets)) {
					continue;
				}
				else {
					$rulesets[] = $name;
				}
			}
			// Remove any duplicate ruleset names from earlier bug
			$suricatacfg['rulesets'] = implode("||", array_keys(array_flip($rulesets)));
		}

		// Release our config array iterator and other memory
		unset($suricatacfg, $builtin_rules, $rulesets);
	}
	/****************************************************************/
	/* End of built-in events rules fix.                            */
	/****************************************************************/

	/* Do one-time settings migration for new version configuration */
	update_status(gettext("Migrating settings to new configuration..."));
	include('/usr/local/pkg/suricata/suricata_migrate_config.php');
	update_status(gettext(" done.") . "\n");
	syslog(LOG_NOTICE, gettext("[Suricata] Downloading and updating configured rule types."));
	include('/usr/local/pkg/suricata/suricata_check_for_rule_updates.php');
	update_status(gettext("Generating suricata.yaml configuration file from saved settings.") . "\n");
	$rebuild_rules = true;

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

	// Create the suricata.yaml files for each enabled interface
	foreach ($config['installedpackages']['suricata']['rule'] as $suricatacfg) {
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
	syslog(LOG_NOTICE, gettext("[Suricata] Finished rebuilding installation from saved settings."));
}

// If this is first install and "forcekeepsettings" is empty,
// then default it to 'on'.
if (empty($config['installedpackages']['suricata']['config'][0]['forcekeepsettings'])) {
	update_status("   " . gettext("\n  Setting up initial configuration.") . "\n");
	$config['installedpackages']['suricata']['config'][0]['forcekeepsettings'] = 'on';
}

/**********************************************************/
/* Incorporate content of SID Mgmt example files in the   */
/* /var/db/suricata/sidmods directory to Base64 encoded   */
/* strings in SID_MGMT_LIST array in config.xml if this   */
/* is a first-time green field install of Suricata.       */
/**********************************************************/
if (!is_array($config['installedpackages']['suricata']['sid_mgmt_lists'])) {
	$config['installedpackages']['suricata']['sid_mgmt_lists'] = array();
}
if (empty($config['installedpackages']['suricata']['config'][0]['sid_list_migration']) && count($config['installedpackages']['suricata']['sid_mgmt_lists']) < 1) {
	if (!is_array($config['installedpackages']['suricata']['sid_mgmt_lists']['item'])) {
		$config['installedpackages']['suricata']['sid_mgmt_lists']['item'] = array();
	}
	$a_list = &$config['installedpackages']['suricata']['sid_mgmt_lists']['item'];
	$sidmodfiles = array("disablesid-sample.conf", "dropsid-sample.conf", "enablesid-sample.conf", "modifysid-sample.conf");
	foreach ($sidmodfiles as $sidfile) {
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
	$config['installedpackages']['suricata']['config'][0]['sid_list_migration'] = "1";
}

// Update Suricata package version in configuration
update_status(gettext("  " . "Setting package version in configuration file.") . "\n");
$config['installedpackages']['suricata']['config'][0]['suricata_config_ver'] = $config['installedpackages']['package'][get_package_id("suricata")]['version'];

write_config("Suricata pkg v{$config['installedpackages']['package'][get_package_id("suricata")]['version']}: post-install configuration saved.");

// Done with post-install, so clear flag
unset($g['suricata_postinstall']);
syslog(LOG_NOTICE, gettext("[Suricata] Package post-installation tasks completed."));
return true;

?>
