<?php
/*
 * snort_check_cron_misc.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009-2010 Robert Zelaya
 * Copyright (c) 2018 Bill Meeks
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

require_once("/usr/local/pkg/snort/snort.inc");

$snortlogdir = SNORTLOGDIR;
global $g, $config;

function snort_check_dir_size_limit($snortloglimitsize) {

	/********************************************************
	 * This function checks the total size of the Snort     *
	 * logging sub-directory structure and prunes the files *
	 * for all Snort interfaces if the size exceeds the     *
	 * passed limit.                                        *
	 *                                                      *
	 * On Entry: $snortloglimitsize = dir size limit in     *
	 *                                in megabytes          *
	 ********************************************************/

	global $g, $config;

	// Convert Log Limit Size setting from MB to KB
	$snortloglimitsizeKB = round($snortloglimitsize * 1024);
	$snortlogdirsizeKB = snort_Getdirsize(SNORTLOGDIR);
	if ($snortlogdirsizeKB > 0 && $snortlogdirsizeKB > $snortloglimitsizeKB) {
		log_error(gettext("[Snort] Log directory size exceeds configured limit of " . number_format($snortloglimitsize) . " MB set on Global Settings tab. All Snort log files will be truncated."));
		conf_mount_rw();

		// Truncate the Rules Update Log file if it exists
		if (file_exists(SNORT_RULES_UPD_LOGFILE)) {
			log_error(gettext("[Snort] Truncating the Rules Update Log file..."));
			@file_put_contents(SNORT_RULES_UPD_LOGFILE, "");
		}

		// Clean-up the logs for each configured Snort instance
		foreach ($config['installedpackages']['snortglobal']['rule'] as $value) {
			$if_real = get_real_interface($value['interface']);
			$snort_uuid = $value['uuid'];
			$snort_log_dir = SNORTLOGDIR . "/snort_{$if_real}{$snort_uuid}";
			log_error(gettext("[Snort] Truncating logs for {$value['descr']} ({$if_real})..."));
			snort_post_delete_logs($snort_uuid);

			// Truncate the alert log file if it exists
			if (file_exists("{$snort_log_dir}/alert")) {
				@file_put_contents("{$snort_log_dir}/alert", "");
			}

			// Cleanup any perfmon stats logs
			$files = array();
			$list = glob("{$snort_log_dir}/*");
			foreach ($list as $file) {
				if (preg_match('/(^\d{4}-\d{2}-\d{2}[\.\d+]*)/', basename($file), $matches))
					$files[] = $snort_log_dir . "/" . $matches[1];
			}
			foreach ($files as $file)
				unlink_if_exists($file);

			// Cleanup any AppID stats logs
			$files = glob("{$snort_log_dir}/appid-stats.log.*");
			foreach ($files as $file)
				unlink_if_exists($file);

			// This is needed if snort is run as snort user
			mwexec('/bin/chmod 660 {$snort_log_dir}/*', true);

			// Soft-restart Snort process to resync logging
			if (file_exists("{$g['varrun_path']}/snort_{$if_real}{$snort_uuid}.pid")) {
				log_error(gettext("[Snort] Restarting logging on {$value['descr']} ({$if_real})..."));
				mwexec("/bin/pkill -HUP -F {$g['varrun_path']}/snort_{$if_real}{$snort_uuid}.pid -a");
			}
		}
		conf_mount_ro();
		log_error(gettext("[Snort] Automatic clean-up of Snort logs completed."));
	}
}

function snort_check_rotate_log($log_file, $log_limit, $retention) {

	/********************************************************
	 * This function checks the passed log file against     *
	 * the passed size limit and rotates the log file if    *
	 * necessary.  It also checks the age of previously     *
	 * rotated logs and removes those older than the        *
	 * rentention  parameter.                               *
	 *                                                      *
	 * On Entry: $log_file  -> full pathname/filename of    *
	 *                         log file to check            *
	 *           $log_limit -> size of file in bytes to     *
	 *                         trigger rotation. Zero       *
	 *                         means no rotation.           *
	 *           $retention -> retention period in hours    *
	 *                         for rotated logs. Zero       *
	 *                         means never remove.          *
	 ********************************************************/

	// Check the current log to see if it needs rotating.
	// If it does, rotate it and put the current time
	// on the end of the filename as UNIX timestamp.
	if (!file_exists($log_file))
		return;
	if (($log_limit > 0) && (filesize($log_file) >= $log_limit)) {
		$newfile = $log_file . "." . strval(time());
		try {
			copy($log_file, $newfile);
			file_put_contents($log_file, "");
		} catch (Exception $e) {
			log_error("[Snort] Failed to rotate file '{$log_file}' -- error was {$e->getMessage()}");
		}
	}

	// Check previously rotated logs to see if time to
	// delete any older than the retention period.
	// Rotated logs have a UNIX timestamp appended to
	// filename.
	if ($retention > 0) {
		$now = time();
		$rotated_files = glob("{$log_file}.*");
		foreach ($rotated_files as $file) {
			if (($now - filemtime($file)) > ($retention * 3600))
				unlink_if_exists($file);
		}
		unset($rotated_files);
	}
}


/*************************
 * Start of main code    *
 *************************/

// If firewall is booting, do nothing
if ($g['booting'] == true)
	return;

$logs = array ();

// Build an array of files to check and limits to check them against from our saved configuration
$logs['alert'] = array();
$logs['alert']['limit'] = $config['installedpackages']['snortglobal']['alert_log_limit_size'];
$logs['alert']['retention'] = $config['installedpackages']['snortglobal']['alert_log_retention'];
$logs['sid_changes.log'] = array();
$logs['sid_changes.log']['limit'] = $config['installedpackages']['snortglobal']['sid_changes_log_limit_size'];
$logs['sid_changes.log']['retention'] = $config['installedpackages']['snortglobal']['sid_changes_log_retention'];
$logs['app-stats.log'] = array();
$logs['app-stats.log']['limit'] = $config['installedpackages']['snortglobal'][''appid_stats_log_limit_size'];
$logs['app-stats.log']['retention'] = $config['installedpackages']['snortglobal']['appid_stats_log_retention'];

// If no interfaces defined, there is nothing to clean up
if (!is_array($config['installedpackages']['snortglobal']['rule']))
	return;

// Check log limits and retention in the interface logging directories if enabled
if ($config['installedpackages']['snortglobal']['enable_log_mgmt'] == 'on') {
	foreach ($config['installedpackages']['snortglobal']['rule'] as $value) {
		$if_real = get_real_interface($value['interface']);
		$snort_log_dir = SNORTLOGDIR . "/snort_{$if_real}{$value['uuid']}";
		foreach ($logs as $k => $p) {
			snort_check_rotate_log("{$snort_log_dir}/{$k}", $p['limit']*1024, $p['retention']);
		}

		// Prune aged-out event packet capture files if any exist
		if ($config['installedpackages']['snortglobal']['event_pkts_log_retention'] > 0) {
			$now = time();
			$rotated_files = glob("{$snort_log_dir}/snort.log.*");
			$prune_count = 0;
			foreach ($rotated_files as $file) {
				if (($now - filemtime($file)) > ($config['installedpackages']['snortglobal']['event_pkts_log_retention'] * 3600)) {
					$prune_count++;
					unlink_if_exists($file);
				}
			}
			unset($rotated_files);
			if ($prune_count > 0)
				log_error(gettext("[Snort] Alert pcap file cleanup job removed {$prune_count} pcap file(s) from {$snort_log_dir}/..."));
		}

		// Prune any aged-out Barnyard2 archived logs if any exist
		if (is_dir("{$snort_log_dir}/barnyard2/archive") && $value['u2_archived_log_retention'] > 0) {
			$now = time();
			$files = glob("{$snort_log_dir}/barnyard2/archive/snort_{$value['uuid']}_{$if_real}.u2.*");
			$prune_count = 0;
			foreach ($files as $f) {
				if (($now - filemtime($f)) > ($value['u2_archived_log_retention'] * 3600)) {
					$prune_count++;
					unlink_if_exists($f);
				}
			}
			unset($files);
			if ($prune_count > 0)
				log_error(gettext("[Snort] Barnyard2 archived logs cleanup job removed {$prune_count} file(s) from {$snort_log_dir}/barnyard2/archive/..."));
		}

		// Prune aged-out perfmon stats logs if any exist
		if ($config['installedpackages']['snortglobal']['stats_log_retention'] > 0) {
			$now = time();
			$files = array();
			$list = glob("{$snort_log_dir}/*");
			foreach ($list as $file) {
				if (preg_match('/(^\d{4}-\d{2}-\d{2}[\.\d+]*)/', basename($file), $matches))
					$files[] = $snort_log_dir . "/" . $matches[1];
			}
			$prune_count = 0;
			foreach ($files as $f) {
				if (($now - filemtime($f)) > ($config['installedpackages']['snortglobal']['stats_log_retention'] * 3600)) {
					$prune_count++;
					unlink_if_exists($f);
				}
			}
			unset($list, $files);
			if ($prune_count > 0)
				log_error(gettext("[Snort] perfmon stats logs cleanup job removed {$prune_count} file(s) from {$snort_log_dir}/..."));
		}

		// Prune any aged-out AppID stats logs if any exist
		if ($value['appid_stats_log_retention'] > 0) {
			$now = time();
			$files = glob("{$snort_log_dir}/app-stats.log.*");
			$prune_count = 0;
			foreach ($files as $f) {
				if (($now - filemtime($f)) > ($value['appid_stats_log_retention'] * 3600)) {
					$prune_count++;
					unlink_if_exists($f);
				}
			}
			unset($files);
			if ($prune_count > 0)
				log_error(gettext("[Snort] AppID stats logs cleanup job removed {$prune_count} file(s) from {$snort_log_dir}/..."));
		}
	}
}

// Check the overall log directory limit (if enabled) and prune if necessary
if ($config['installedpackages']['snortglobal']['snortloglimit'] == 'on')
	snort_check_dir_size_limit($config['installedpackages']['snortglobal']['snortloglimitsize']);

return;

?>
