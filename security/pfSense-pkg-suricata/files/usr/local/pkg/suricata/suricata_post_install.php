<?php
/*
 * suricata_post_install.php
 *
 * Significant portions of this code are based on original work done
 * for the Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
 * All rights reserved.
 *
 * Adapted for Suricata by:
 * Copyright (C) 2016 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:

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
/* This module is called once during the Suricata package installation to   */
/* perform required post-installation setup.  It should only be executed    */
/* from the Package Manager process via the custom-post-install hook in     */
/* the snort.xml package configuration file.                                */
/****************************************************************************/

require_once("config.inc");
require_once("functions.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");
require("/usr/local/pkg/suricata/suricata_defs.inc");

global $config, $g, $rebuild_rules, $pkg_interface, $suricata_gui_include;

/****************************************
 * Define any new constants here that   *
 * may not be yet defined in the old    *
 * "suricata_defs.inc" include file     *
 * that might be cached and used by     *
 * the package manager installation     *
 * code.                                *
 *                                      *
 * This is a hack to work around the    *
 * fact the old version of the inc file *
 * is cached and used instead of the    *
 * updated version included with the    *
 * updated GUI package.                 *
 ****************************************/
if (!defined('SURICATA_PBI_BASEDIR'))
	define('SURICATA_PBI_BASEDIR', '/usr/pbi/suricata-' . php_uname("m"));

/****************************************
 * End of PHP caching workaround        *
 ****************************************/

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

// Make sure config variable is an array
if (!is_array($config['installedpackages']['suricata']['config'][0]))
	$config['installedpackages']['suricata']['config'][0] = array();

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
	update_status(gettext("Migrating settings to new configuration...") . "\n");
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
		if (strpos($config['widgets']['sequence'], "suricata_alerts-container") === FALSE)
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
	$config['installedpackages']['suricata']['config'][0]['forcekeepsettings'] = 'on';

// Finished with file system mods, so remount it read-only
conf_mount_ro();

// Update Suricata package version in configuration
$config['installedpackages']['suricata']['config'][0]['suricata_config_ver'] = $config['installedpackages']['package'][get_package_id("suricata")]['version'];
write_config("Suricata pkg v{$config['installedpackages']['package'][get_package_id("suricata")]['version']}: post-install configuration saved.");

// Done with post-install, so clear flag
unset($g['suricata_postinstall']);
log_error(gettext("[Suricata] Package post-installation tasks completed."));
return true;

?>
