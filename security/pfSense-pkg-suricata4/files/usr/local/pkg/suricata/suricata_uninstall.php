<?php
/*
 * suricata_uninstall.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2019-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2019 Bill Meeks
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

global $config, $g;

$suricatadir = SURICATADIR;
$suricatalogdir = SURICATALOGDIR;
$sidmodspath = SURICATA_SID_MODS_PATH;
$iprep_path = SURICATA_IPREP_PATH;
$rcdir = RCFILEPREFIX;
$suricata_rules_upd_log = SURICATA_RULES_UPD_LOGFILE;
$suri_pf_table = SURICATA_PF_TABLE;

syslog(LOG_NOTICE, gettext("[Suricata] Suricata package uninstall in progress..."));

/* Make sure all active Suricata processes are terminated */
/* Log a message only if a running process is detected */
if (is_service_running("suricata"))
	syslog(LOG_NOTICE, gettext("[Suricata] Stopping Suricata on all configured interfaces..."));
killbyname("suricata");
sleep(1);

// Delete any leftover suricata PID or LCK files in /var/run
unlink_if_exists("{$g['varrun_path']}/suricata_*.pid");
unlink_if_exists("{$g['varrun_path']}/suricata*.lck");

/* Make sure all active Barnyard2 processes are terminated */
/* Log a message only if a running process is detected     */
if (is_service_running("barnyard2"))
	syslog(LOG_NOTICE, gettext("[Suricata] Stopping Barnyard2 on all configured interfaces..."));
killbyname("barnyard2");
sleep(1);

// Delete any leftover barnyard2 PID files in /var/run
unlink_if_exists("{$g['varrun_path']}/barnyard2_*.pid");

/* Remove the Suricata cron jobs. */
install_cron_job("suricata_check_for_rule_updates.php", false);
install_cron_job("suricata_check_cron_misc.inc", false);
install_cron_job("{$suri_pf_table}" , false);
install_cron_job("suricata_geoipupdate.php" , false);
install_cron_job("suricata_etiqrisk_update.php", false);

/* See if we are to keep Suricata log files on uninstall */
if ($config['installedpackages']['suricata']['config'][0]['clearlogs'] == 'on') {
	syslog(LOG_NOTICE, gettext("[Suricata] Clearing all Suricata-related log files..."));
	unlink_if_exists("{$suricata_rules_upd_log}");
	rmdir_recursive("{$suricatalogdir}");
}

/*********************************************************/
/* Remove files we placed in the Suricata directories.   */
/* pkg will clean up the base install files.             */
/*********************************************************/
unlink_if_exists("{$suricatadir}*.gz.md5");
unlink_if_exists("{$suricatadir}gen-msg.map");
unlink_if_exists("{$suricatadir}classification.config");
unlink_if_exists("{$suricatadir}reference.config");
unlink_if_exists("{$suricatadir}rulesupd_status");
unlink_if_exists(SURICATA_RULES_DIR . "*.txt");
unlink_if_exists(SURICATA_RULES_DIR . VRT_FILE_PREFIX . "*.rules");
unlink_if_exists(SURICATA_RULES_DIR . ET_OPEN_FILE_PREFIX . "*.rules");
unlink_if_exists(SURICATA_RULES_DIR . ET_PRO_FILE_PREFIX . "*.rules");
unlink_if_exists(SURICATA_RULES_DIR . GPL_FILE_PREFIX . "*.rules");
unlink_if_exists("/usr/local/share/suricata/GeoLite2/GeoLite2-Country.mmdb");
rmdir_recursive("/usr/local/share/suricata/GeoLite2");

if (is_array($config['installedpackages']['suricata']['rule'])) {
	foreach ($config['installedpackages']['suricata']['rule'] as $suricatacfg) {
		rmdir_recursive("{$suricatadir}suricata_" . $suricatacfg['uuid'] . "_" . get_real_interface($suricatacfg['interface']));
	}
}

/* Remove our associated Dashboard widget config and files. */
/* If "save settings" is enabled, then save old widget      */
/* container settings so we can restore them later.         */
$widgets = $config['widgets']['sequence'];
if (!empty($widgets)) {
	$widgetlist = explode(",", $widgets);
	foreach ($widgetlist as $key => $widget) {
		if (strstr($widget, "suricata_alerts")) {
			if ($config['installedpackages']['suricata']['config'][0]['forcekeepsettings'] == 'on') {
				$config['installedpackages']['suricata']['config'][0]['dashboard_widget'] = $widget;
				if ($config['widgets']['widget_suricata_display_lines']) {
					$config['installedpackages']['suricata']['config'][0]['dashboard_widget_rows'] = $config['widgets']['widget_suricata_display_lines'];
					unset($config['widgets']['widget_suricata_display_lines']);
				}
			}
			unset($widgetlist[$key]);
		}
	}
	$config['widgets']['sequence'] = implode(",", $widgetlist);
	write_config("Suricata pkg removed Dashboard Alerts widget.");
}

/* Keep this as a last step */
if ($config['installedpackages']['suricata']['config'][0]['forcekeepsettings'] != 'on') {
	syslog(LOG_NOTICE, gettext("Not saving settings... all Suricata configuration info and logs deleted..."));
	unset($config['installedpackages']['suricata']);
	unset($config['installedpackages']['suricatasync']);
	unlink_if_exists("{$suricata_rules_upd_log}");
	rmdir_recursive("{$suricatalogdir}");
	rmdir_recursive("{$g['vardb_path']}/suricata");
	write_config("Removing Suricata configuration");
	syslog(LOG_NOTICE, gettext("[Suricata] The package has been removed from this system..."));
}

?>
