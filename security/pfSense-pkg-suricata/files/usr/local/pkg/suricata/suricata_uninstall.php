<?php
/*
 * suricata_uninstall.php
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

require_once("/usr/local/pkg/suricata/suricata.inc");

global $config, $g;

$suricatadir = SURICATADIR;
$suricatalogdir = SURICATALOGDIR;
$sidmodspath = SURICATA_SID_MODS_PATH;
$iprep_path = SURICATA_IPREP_PATH;
$rcdir = RCFILEPREFIX;
$suricata_rules_upd_log = SURICATA_RULES_UPD_LOGFILE;
$suri_pf_table = SURICATA_PF_TABLE;
$mounted_rw = FALSE;

log_error(gettext("[Suricata] Suricata package uninstall in progress..."));

/* Make sure all active Suricata processes are terminated */
/* Log a message only if a running process is detected */
if (is_service_running("suricata"))
	log_error(gettext("[Suricata] Suricata STOP for all interfaces..."));
killbyname("suricata");
sleep(1);

// Delete any leftover suricata PID or LCK files in /var/run
unlink_if_exists("{$g['varrun_path']}/suricata_*.pid");
unlink_if_exists("{$g['varrun_path']}/suricata*.lck");

/* Make sure all active Barnyard2 processes are terminated */
/* Log a message only if a running process is detected     */
if (is_service_running("barnyard2"))
	log_error(gettext("[Suricata] Barnyard2 STOP for all interfaces..."));
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
	log_error(gettext("[Suricata] Clearing all Suricata-related log files..."));
	unlink_if_exists("{$suricata_rules_upd_log}");
	rmdir_recursive("{$suricatalogdir}");
}

/**************************************************/
/* If not already, set Suricata conf partition to */
/* read-write so we can make changes there        */
/**************************************************/
if (!is_subsystem_dirty('mount')) {
	conf_mount_rw();
	$mounted_rw = TRUE;
}

/*********************************************************/
/* Remove files we placed in the Suricata etc directory. */
/* pkgng will clean up the base install files.           */
/*********************************************************/
unlink_if_exists("{$suricatadir}*.gz.md5");
unlink_if_exists("{$suricatadir}gen-msg.map");
unlink_if_exists("{$suricatadir}unicode.map");
unlink_if_exists("{$suricatadir}classification.config");
unlink_if_exists("{$suricatadir}reference.config");
unlink_if_exists("{$suricatadir}rules/" . VRT_FILE_PREFIX . "*.rules");
unlink_if_exists("{$suricatadir}rules/" . ET_OPEN_FILE_PREFIX . "*.rules");
unlink_if_exists("{$suricatadir}rules/" . ET_OPEN_FILE_PREFIX . "*.txt");
unlink_if_exists("{$suricatadir}rules/" . ET_PRO_FILE_PREFIX . "*.rules");
unlink_if_exists("{$suricatadir}rules/" . ET_PRO_FILE_PREFIX . "*.txt");
unlink_if_exists("{$suricatadir}rules/" . GPL_FILE_PREFIX . "*.rules");
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
		if (strstr($widget, "suricata_alerts-container")) {
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
}

/*******************************************************/
/* We're finished with conf partition mods, return to  */
/* read-only if we changed it                          */
/*******************************************************/
if ($mounted_rw == TRUE)
	conf_mount_ro();

/* Keep this as a last step */
if ($config['installedpackages']['suricata']['config'][0]['forcekeepsettings'] != 'on') {
	log_error(gettext("Not saving settings... all Suricata configuration info and logs deleted..."));
	unset($config['installedpackages']['suricata']);
	unset($config['installedpackages']['suricatasync']);
	unlink_if_exists("{$suricata_rules_upd_log}");
	rmdir_recursive("{$suricatalogdir}");
	rmdir_recursive("{$g['vardb_path']}/suricata");
	log_error(gettext("[Suricata] The package has been removed from this system..."));
}

?>
