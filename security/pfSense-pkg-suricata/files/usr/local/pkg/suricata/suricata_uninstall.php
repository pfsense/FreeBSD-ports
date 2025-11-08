<?php
/*
 * suricata_uninstall.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2019-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2025 Bill Meeks
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

require_once("/usr/local/pkg/suricata/suricata.inc");

global $g;

$suricatadir = SURICATADIR;
$suricatalogdir = SURICATALOGDIR;
$sidmodspath = SURICATA_SID_MODS_PATH;
$iprep_path = SURICATA_IPREP_PATH;
$rcdir = RCFILEPREFIX;

logger(LOG_NOTICE, localize_text("Suricata package uninstall in progress..."), LOG_PREFIX_PKG_SURICATA);

/* Make sure all active Suricata processes are terminated */
/* Log a message only if a running process is detected */
if (is_service_running("suricata"))
	logger(LOG_NOTICE, localize_text("Stopping Suricata on all configured interfaces..."), LOG_PREFIX_PKG_SURICATA);
killbyname("suricata");
sleep(1);

// Delete any leftover suricata PID or LCK files in /var/run
unlink_if_exists("{$g['varrun_path']}/suricata_*.pid");
unlink_if_exists("{$g['varrun_path']}/suricata*.lck");

/* Make sure all active Barnyard2 processes are terminated */
/* Log a message only if a running process is detected     */
/* Even though Barnyard2 is deprecated, this code remains  */
/* to ensure no active Barnyard2 process remains.          */
if (is_service_running("barnyard2"))
	logger(LOG_NOTICE, localize_text("Stopping Barnyard2 on all configured interfaces..."), LOG_PREFIX_PKG_SURICATA);
killbyname("barnyard2");
sleep(1);

// Delete any leftover barnyard2 PID files in /var/run
unlink_if_exists("{$g['varrun_path']}/barnyard2_*.pid");

/* Remove the Suricata cron jobs. */
install_cron_job("/usr/local/pkg/suricata/suricata_check_for_rule_updates.php", false);
install_cron_job("/usr/local/pkg/suricata/suricata_check_cron_misc.inc", false);
install_cron_job(SURICATA_PF_TABLE, false);
install_cron_job("/usr/local/pkg/suricata/suricata_geoipupdate.php" , false);
install_cron_job("/usr/local/pkg/suricata/suricata_etiqrisk_update.php", false);

/* See if we are to keep Suricata log files on uninstall */
if (config_get_path('installedpackages/suricata/config/0/clearlogs') == 'on') {
	logger(LOG_NOTICE, localize_text("Clearing all Suricata-related log files..."), LOG_PREFIX_PKG_SURICATA);
	unlink_if_exists(SURICATA_RULES_UPD_LOGFILE);
	rmdir_recursive("{$suricatalogdir}");
}

/*********************************************************/
/* Remove files we placed in the Suricata directories.   */
/* pkg will clean up the base install files.             */
/*********************************************************/
unlink_if_exists("{$suricatadir}*.gz.md5");
unlink_if_exists("{$suricatadir}*.ruleslist");
unlink_if_exists("{$suricatadir}gen-msg.map");
unlink_if_exists("{$suricatadir}unicode.map");
unlink_if_exists("{$suricatadir}classification.config");
unlink_if_exists("{$suricatadir}reference.config");
unlink_if_exists("{$suricatadir}rulesupd_status");
unlink_if_exists(SURICATA_RULES_DIR . "*.txt");
unlink_if_exists(SURICATA_RULES_DIR . VRT_FILE_PREFIX . "*.rules");
unlink_if_exists(SURICATA_RULES_DIR . ET_OPEN_FILE_PREFIX . "*.rules");
unlink_if_exists(SURICATA_RULES_DIR . ET_PRO_FILE_PREFIX . "*.rules");
unlink_if_exists(SURICATA_RULES_DIR . GPL_FILE_PREFIX . "*.rules");
unlink_if_exists(SURICATA_RULES_DIR . EXTRARULE_FILE_PREFIX . "*.rules");
unlink_if_exists("/usr/local/share/suricata/GeoLite2/GeoLite2-Country.mmdb");
rmdir_recursive("/usr/local/share/suricata/GeoLite2");

foreach (config_get_path('installedpackages/suricata/rule', []) as $suricatacfg) {
	rmdir_recursive("{$suricatadir}suricata_" . $suricatacfg['uuid'] . "_" . get_real_interface($suricatacfg['interface']));
	unlink_if_exists($g['varrun_path'] . "/suricata-ctrl-socket-" . $suricatacfg['uuid']);
}

/* Remove our associated Dashboard widget config and files. */
/* If "save settings" is enabled, then save old widget      */
/* container settings so we can restore them later.         */
$widgets = config_get_path('widgets/sequence');
if (!empty($widgets)) {
	$widgetlist = explode(",", $widgets);
	foreach ($widgetlist as $key => $widget) {
		if (strstr($widget, "suricata_alerts")) {
			if (config_get_path('installedpackages/suricata/config/0/forcekeepsettings') == 'on') {
				config_set_path('installedpackages/suricata/config/0/dashboard_widget', $widget);
				if (config_get_path('widgets/widget_suricata_display_lines')) {
					config_set_path('installedpackages/suricata/config/0/dashboard_widget_rows', config_get_path('widgets/widget_suricata_display_lines'));
					config_del_path('widgets/widget_suricata_display_lines');
				}
			}
			unset($widgetlist[$key]);
		}
	}
	config_set_path('widgets/sequence', implode(",", $widgetlist));
	write_config("Suricata pkg removed Dashboard Alerts widget.", false);
}

// See if we are to clear blocked hosts on uninstall
if (config_get_path('installedpackages/suricata/config/0/clearblocks') == 'on') {
	logger(LOG_NOTICE, localize_text("Flushing all blocked hosts from <snort2c> table due to package removal..."), LOG_PREFIX_PKG_SURICATA);
	mwexec("/sbin/pfctl -t snort2c -T flush");
}

/* Keep this as the last step of the uninstall procedure */
if (config_get_path('installedpackages/suricata/config/0/forcekeepsettings') != 'on') {
	logger(LOG_NOTICE, localize_text("Not saving Suricata settings... all Suricata configuration info and logs deleted..."), LOG_PREFIX_PKG_SURICATA);
	config_del_path('installedpackages/suricata');
	config_del_path('installedpackages/suricatasync');
	unlink_if_exists(SURICATA_RULES_UPD_LOGFILE);
	rmdir_recursive("{$suricatalogdir}");
	write_config("Deleted the Suricata package and its configuration settings.");
	logger(LOG_NOTICE, localize_text("The package and its configuration settings have been deleted from this system..."), LOG_PREFIX_PKG_SURICATA);
} else {
	write_config("Removed the Suricata package.");
	logger(LOG_NOTICE, localize_text("The package has been removed from this system, but the configuration settings were retained..."), LOG_PREFIX_PKG_SURICATA);
}
return true;
?>
