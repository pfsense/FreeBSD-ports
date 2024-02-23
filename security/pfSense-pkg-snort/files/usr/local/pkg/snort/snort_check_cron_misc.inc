<?php
/*
 * snort_check_cron_misc.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2019-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009-2010 Robert Zelaya
 * Copyright (c) 2022 Bill Meeks
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
global $g;

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

	global $g;

	// Convert Log Limit Size setting from MB to KB
	$snortloglimitsizeKB = round($snortloglimitsize * 1024);
	$snortlogdirsizeKB = snort_Getdirsize(SNORTLOGDIR);
	if ($snortlogdirsizeKB > 0 && $snortlogdirsizeKB > $snortloglimitsizeKB) {
		syslog(LOG_NOTICE, gettext("[Snort] Log directory size exceeds configured limit of " . number_format($snortloglimitsize) . " MB set on Global Settings tab. All Snort log files will be truncated."));

		// Truncate the Rules Update Log file if it exists
		if (file_exists(SNORT_RULES_UPD_LOGFILE)) {
			syslog(LOG_NOTICE, gettext("[Snort] Truncating the Rules Update Log file..."));
			@file_put_contents(SNORT_RULES_UPD_LOGFILE, "");
		}

		// Clean-up the logs for each configured Snort instance
		foreach (config_get_path('installedpackages/snortglobal/rule', []) as $value) {
			$if_real = get_real_interface($value['interface']);

			// Skip instances where pfSense physical interface has been removed
			if ($if_real == "") {
				continue;
			}

			$snort_uuid = $value['uuid'];
			$snort_log_dir = SNORTLOGDIR . "/snort_{$if_real}{$snort_uuid}";
			syslog(LOG_NOTICE, gettext("[Snort] Truncating logs for {$value['descr']} ({$if_real})..."));
			snort_post_delete_logs($snort_uuid);

			// Truncate the alert log file if it exists
			if (file_exists("{$snort_log_dir}/alert")) {
				try {
					fclose(fopen("{$snort_log_dir}/alert", 'w'));
				} catch (Exception $e) {
					syslog(LOG_ERR, gettext("[Snort] ERROR: Failed to truncate file '{$snort_log_dir}/alert' -- error was {$e->getMessage()}"));
				}
			}

			// Now cleanup any existing rotated alert log files
			$list = array();
			$list = glob("{$snort_log_dir}/alert.*");
			foreach ($list as $file) {
				unlink_if_exists($file);
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

			// Cleanup any rotated AppID alerts logs
			$files = glob("{$snort_log_dir}/appid.alerts.*");
			foreach ($files as $file)
				unlink_if_exists($file);

			// Cleanup any rotated AppID stats logs
			$files = glob("{$snort_log_dir}/app-stats.log.*");
			foreach ($files as $file)
				unlink_if_exists($file);

			// Cleanup any packet capture logs
			$files = glob("{$snort_log_dir}/snort.log.*");
			foreach ($files as $file)
				unlink_if_exists($file);

			// This is needed if snort is run as snort user
			mwexec('/bin/chmod 660 {$snort_log_dir}/*', true);

			// Soft-restart Snort process to resync logging
			if (file_exists("{$g['varrun_path']}/snort_{$snort_uuid}.pid")) {
				syslog(LOG_NOTICE, gettext("[Snort] Restarting logging on {$value['descr']} ({$if_real}) because configured logging directory size limit was exceeded and log files truncated to recover space."));
				mwexec("/bin/pkill -HUP -F {$g['varrun_path']}/snort_{$snort_uuid}.pid -a");
			}
		}
		syslog(LOG_NOTICE, gettext("[Snort] Automatic clean-up of Snort logs completed."));
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
	 *                                                      *
	 * Returns: TRUE if log was rotated, FALSE if not.      *
	 ********************************************************/

	$rotated = FALSE;

	// Check the current log to see if it needs rotating.
	// If it does, rotate it and put the current time
	// on the end of the filename as UNIX timestamp.
	if (!file_exists($log_file))
		return $rotated;
	if (($log_limit > 0) && (filesize($log_file) >= $log_limit)) {
		$newfile = $log_file . "." . strval(time());
		try {
			rename($log_file, $newfile);
			touch($log_file);
			$rotated = TRUE;
		} catch (Exception $e) {
			syslog(LOG_ERR, "[Snort] Failed to rotate file '{$log_file}' -- error was {$e->getMessage()}");
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

	return $rotated;
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
$logs['alert']['limit'] = config_get_path('installedpackages/snortglobal/alert_log_limit_size');
$logs['alert']['retention'] = config_get_path('installedpackages/snortglobal/alert_log_retention');
$logs['sid_changes.log'] = array();
$logs['sid_changes.log']['limit'] = config_get_path('installedpackages/snortglobal/sid_changes_log_limit_size');
$logs['sid_changes.log']['retention'] = config_get_path('installedpackages/snortglobal/sid_changes_log_retention');
$logs['app-stats.log'] = array();
$logs['app-stats.log']['limit'] = config_get_path('installedpackages/snortglobal/appid_stats_log_limit_size');
$logs['app-stats.log']['retention'] = config_get_path('installedpackages/snortglobal/appid_stats_log_retention');

// If no interfaces defined, there is nothing to clean up
if (!config_get_path('installedpackages/snortglobal/rule'))
	return;

// Check log limits and retention in the interface logging directories if enabled
if (config_get_path('installedpackages/snortglobal/enable_log_mgmt') == 'on') {
	foreach (config_get_path('installedpackages/snortglobal/rule', []) as $value) {
		$warm_restart = FALSE;
		$if_real = get_real_interface($value['interface']);

		// Skip instances where pfSense physical interface has been removed
		if ($if_real == "") {
			continue;
		}

		$snort_log_dir = SNORTLOGDIR . "/snort_{$if_real}{$value['uuid']}";

		// Check if any logs need to be rotated
		foreach ($logs as $k => $p) {
			$warm_restart |= snort_check_rotate_log("{$snort_log_dir}/{$k}", $p['limit']*1024, $p['retention']);
		}

		// Prune aged-out rotated alert log files if any exist
		if (config_get_path('installedpackages/snortglobal/alert_log_retention') > 0) {
			$now = time();
			$rotated_files = glob("{$snort_log_dir}/alert.*");
			$prune_count = 0;
			foreach ($rotated_files as $file) {
				if (($now - filemtime($file)) > (int)(config_get_path('installedpackages/snortglobal/alert_log_retention') * 3600)) {
					$prune_count++;
					unlink_if_exists($file);
				}
			}
			unset($rotated_files);
			if ($prune_count > 0)
				syslog(LOG_NOTICE, gettext("[Snort] Alerts log file cleanup job removed {$prune_count} rotated alert log file(s) from {$snort_log_dir}/..."));
		}

		// Prune aged-out event packet capture files if any exist
		if (config_get_path('installedpackages/snortglobal/event_pkts_log_retention') > 0) {
			$now = time();
			$rotated_files = glob("{$snort_log_dir}/snort.log.*");
			$prune_count = 0;
			foreach ($rotated_files as $file) {
				if (($now - filemtime($file)) > (int)(config_get_path('installedpackages/snortglobal/event_pkts_log_retention') * 3600)) {
					$prune_count++;
					unlink_if_exists($file);
				}
			}
			unset($rotated_files);
			if ($prune_count > 0)
				syslog(LOG_NOTICE, gettext("[Snort] Alert tcpdump packet capture file cleanup job removed {$prune_count} tcpdump packet capture file(s) from {$snort_log_dir}/..."));
		}

		// Prune any aged-out unified2 archived logs if any exist
		if (is_dir("{$snort_log_dir}") && $value['u2_archived_log_retention'] > 0) {
			$now = time();
			$files = glob("{$snort_log_dir}/snort_{$value['uuid']}_{$if_real}.u2.*");
			$prune_count = 0;
			foreach ($files as $f) {
				if (($now - filemtime($f)) > ($value['u2_archived_log_retention'] * 3600)) {
					$prune_count++;
					unlink_if_exists($f);
				}
			}
			unset($files);
			if ($prune_count > 0)
				syslog(LOG_NOTICE, gettext("[Snort] Unified2 archived logs cleanup job removed {$prune_count} file(s) from {$snort_log_dir}/..."));
		}

		// Prune aged-out perfmon stats logs if any exist
		if (config_get_path('installedpackages/snortglobal/stats_log_retention') > 0) {
			$now = time();
			$files = array();
			$list = glob("{$snort_log_dir}/*");
			foreach ($list as $file) {
				if (preg_match('/(^\d{4}-\d{2}-\d{2}[\.\d+]*)/', basename($file), $matches))
					$files[] = $snort_log_dir . "/" . $matches[1];
			}
			$prune_count = 0;
			foreach ($files as $f) {
				if (($now - filemtime($f)) > (int)(config_get_path('installedpackages/snortglobal/stats_log_retention') * 3600)) {
					$prune_count++;
					unlink_if_exists($f);
				}
			}
			unset($list, $files);
			if ($prune_count > 0)
				syslog(LOG_NOTICE, gettext("[Snort] perfmon stats logs cleanup job removed {$prune_count} file(s) from {$snort_log_dir}/..."));
		}

		// Prune any aged-out rotated AppID alerts logs if any exist
		if ($value['appid_alerts_log_retention'] > 0) {
			$now = time();
			$files = glob("{$snort_log_dir}/appid.alerts.*");
			$prune_count = 0;
			foreach ($files as $f) {
				if (($now - filemtime($f)) > ($value['appid_alerts_log_retention'] * 3600)) {
					$prune_count++;
					unlink_if_exists($f);
				}
			}
			unset($files);
			if ($prune_count > 0)
				syslog(LOG_NOTICE, gettext("[Snort] AppID alerts logs cleanup job removed {$prune_count} appid.alerts file(s) from {$snort_log_dir}/..."));
		}

		// Prune any aged-out rotated AppID stats logs if any exist
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
				syslog(LOG_NOTICE, gettext("[Snort] AppID stats logs cleanup job removed {$prune_count} file(s) from {$snort_log_dir}/..."));
		}

		// If we rotated any logs for this interface, signal the Snort instance to
		// reload its log files by sending the instance a "warm restart" command.
		if ($warm_restart) {
			if (file_exists("{$g['varrun_path']}/snort_{$value['uuid']}.pid")) {
				syslog(LOG_NOTICE, gettext("[Snort] Restarting logging on {$value['descr']} ({$if_real}) because a log file was rotated."));
				mwexec("/bin/pkill -HUP -F {$g['varrun_path']}/snort_{$value['uuid']}.pid -a");
			}
		}
	}
}

// Check the overall log directory limit (if enabled) and prune if necessary
if (config_get_path('installedpackages/snortglobal/snortloglimit') == 'on')
	snort_check_dir_size_limit(config_get_path('installedpackages/snortglobal/snortloglimitsize'));

return;

?>
