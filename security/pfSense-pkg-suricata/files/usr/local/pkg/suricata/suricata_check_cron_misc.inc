<?php
/*
 * suricata_check_cron_misc.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

function suricata_folderSize($dir) {

	/********************************************************
	 * This function returns the size, in bytes, of the     *
	 * content (including any sub-folders) stored under the *
	 * passed directory.                                    *
	 ********************************************************/

	$size = 0;
	foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
		$size += is_file($each) ? filesize($each) : suricata_folderSize($each);
	}

	return $size;
}

function suricata_check_dir_size_limit($suricataloglimitsize) {

	/********************************************************
	 * This function checks the total size of the Suricata  *
	 * logging sub-directory structure and prunes the files *
	 * for all Suricata interfaces if the size exceeds the  *
	 * passed limit. Pruning stops when the directory size  *
	 * is below the configured limit.                       *
	 *                                                      *
	 * On Entry: $surictaaloglimitsize = dir size limit     *
	 *                                   in megabytes       *
	 ********************************************************/

	global $g;

	// Convert Log Limit Size setting from MB to KB
	$suricataloglimitsizeKB = round($suricataloglimitsize * 1024);
	$suricatalogdirsizeKB = suricata_Getdirsize(SURICATALOGDIR);

	if ($suricatalogdirsizeKB > 0 && $suricatalogdirsizeKB > $suricataloglimitsizeKB) {
		logger(LOG_NOTICE, localize_text("Log directory size exceeds configured limit of %s MB set on Global Settings tab. Starting cleanup of Suricata logs.", number_format($suricataloglimitsize)), LOG_PREFIX_PKG_SURICATA);

		// Initialize an array of the log files we want to prune
		$logs = array ( "alerts.log", "block.log", "dns.log", "eve.json", "http.log", "sid_changes.log", "stats.log", "tls.log" );

		// Clean-up the rotated logs for each configured Suricata instance
		foreach (config_get_path('installedpackages/suricata/rule', []) as $value) {
			$if_real = get_real_interface($value['interface']);

			// Skip instances where pfSense physical interface
			// has been removed.
			if ($if_real == "") {
				continue;
			}
			$suricata_uuid = $value['uuid'];
			$suricata_log_dir = SURICATALOGDIR . "suricata_{$if_real}{$suricata_uuid}";
			logger(LOG_NOTICE, localize_text("Cleaning logs for %s (%s)...", $value['descr'], $if_real), LOG_PREFIX_PKG_SURICATA);

			// Clean-up packet capture files if any exist
			$filelist = glob("{$suricata_log_dir}/pcaps/log.pcap.*");

			// Keep most recent file
			unset($filelist[count($filelist) - 1]);
			foreach ($filelist as $file) {
				unlink_if_exists($file);
			}
			unset($filelist);

			// Cleanup any rotated logs
			foreach ($logs as $file) {
				logger(LOG_NOTICE, localize_text("Deleting rotated log files except last for %s (%s) %s...", $value['descr'], $if_real, $file), LOG_PREFIX_PKG_SURICATA);
				$filelist = glob("{$suricata_log_dir}/{$file}.*");
				// Keep most recent file
				unset($filelist[count($filelist) - 1]);
				foreach ($filelist as $file) {
					unlink_if_exists($file);
				}
				unset($filelist);
			}

			// Check for any captured stored files and clean them up
			unlink_if_exists("{$suricata_log_dir}/filestore/*");

			// Check for any captured stored TLS certs and clean them up
			unlink_if_exists("{$suricata_log_dir}/certs/*");
		}

		// If we are now below the configured Directory Size Limit, then skip
		// deleting the active logs and just exit.
		if (suricata_Getdirsize(SURICATALOGDIR) < $suricataloglimitsizeKB) {
			goto cleanupExit;
		}

		// Continue by cleaning up any other rotated logs not handled above
		logger(LOG_NOTICE, localize_text("Deleting any additional rotated log files..."), LOG_PREFIX_PKG_SURICATA);
		unlink_if_exists("{$suricata_log_dir}/suricata_*/*.log.*");
		unlink_if_exists("{$suricata_log_dir}/suricata_*/*.json.*");

		// If we are now below the configured Directory Size Limit, then skip
		// deleting the active logs and just exit.
		if (suricata_Getdirsize(SURICATALOGDIR) < $suricataloglimitsizeKB) {
			goto cleanupExit;
		}

		// Clean-up active logs for each configured Suricata instance
		// until we get below the configured Directory Size Limit.
		foreach (config_get_path('installedpackages/suricata/rule', []) as $value) {
			$if_real = get_real_interface($value['interface']);

			// Skip instances where pfSense physical interface
			// has been removed.
			if ($if_real == "") {
				continue;
			}
			$suricata_uuid = $value['uuid'];
			$suricata_log_dir = SURICATALOGDIR . "suricata_{$if_real}{$suricata_uuid}";
			foreach ($logs as $file) {
				// Truncate the log file if it exists
				if (file_exists("{$suricata_log_dir}/{$file}")) {
					try {
						fclose(fopen("{$suricata_log_dir}/{$file}", 'w'));
					} catch (Exception $e) {
						logger(LOG_ERR, localize_text("Failed to truncate file '%s' -- error was %s", "{$suricata_log_dir}/{$file}", $e->getMessage()), LOG_PREFIX_PKG_SURICATA);
					}
				}

				// Stop deleting logs when directory size is below configured limit
				if (suricata_Getdirsize(SURICATALOGDIR) < $suricataloglimitsizeKB) {
					break;
				}
			}

			// Signal Suricata on this interface that log files have been truncated
			suricata_reload_config($value, SIGHUP);

			// Stop deleting logs when directory size is below configured limit
			if (suricata_Getdirsize(SURICATALOGDIR) < $suricataloglimitsizeKB) {
				break;
			}
		}

		// Truncate the Rules Update Log file if it exists
		if (file_exists(SURICATA_RULES_UPD_LOGFILE)) {
			logger(LOG_NOTICE, localize_text("Truncating the Rules Update Log file..."), LOG_PREFIX_PKG_SURICATA);
			try {
				fclose(fopen(SURICATA_RULES_UPD_LOGFILE, 'w'));
			} catch (Exception $e) {
				logger(LOG_ERR, localize_text("Failed to truncate Rules Update Log '%s' -- error was %s", SURICATA_RULES_UPD_LOGFILE, $e->getMessage()), LOG_PREFIX_PKG_SURICATA);
			}
		}

		cleanupExit:
		logger(LOG_NOTICE, localize_text("Automatic clean-up of Suricata logs completed."), LOG_PREFIX_PKG_SURICATA);
	}
}

function suricata_check_filestore_limit_size($store_path, $store_limit) {

	/***********************************************************
	 * This recursive function checks the passed File Store    *
	 * path to see if it is consuming more than the configured *
	 * disk space limit. If it is, stored captured files are   *
	 * are purged to reduce the storage below the limit.       *
	 *                                                         *
	 * On Entry: $store_path   -> full pathname to check       *
	 *           $store_limit  -> size limit in MB             *
	 *                                                         *
	 * On Exit:  returns number of removed files               *
	 ***********************************************************/

	$removed = 0;

	if (!is_dir($store_path)) {
		return $removed;
	}

	if (intval(suricata_folderSize($store_path)/1000000) >= $store_limit) {
		$msg = localize_text("Automatic prune of File Store path '%s' started due to exceeding configured Captured Files storage size limit of %d Megabytes.", $store_path, $store_limit);
		logger(LOG_NOTICE, $msg, LOG_PREFIX_PKG_SURICATA);

		// Get captured file names into array and sort by
		// modified date/time ascending.
		$files = glob(rtrim($store_path, '/').'/*');
		array_multisort(array_map('filemtime', $files), SORT_NUMERIC, SORT_ASC, $files);

		// Start deleting older files until we are
		// under the passed limit by at least 10%.
		for ($file_index = 0; ($file_index < count($files)) && (intval(suricata_folderSize($store_path)/1000000) > intval($store_limit * 0.9)); $file_index++) {
			if (is_dir($files[$file_index])) {
				$removed += suricata_check_filestore_limit_size($files[$file_index], $store_limit);
			}
			else {
				unlink_if_exists($files[$file_index]);
				$removed++;
			}
			$file_index--;
		}

		$msg = localize_text("Automatic prune of File Store path '%s' completed. Removed %d captured files.", $store_path, $removed);
		logger(LOG_NOTICE, $msg, LOG_PREFIX_PKG_SURICATA);
	}
	return $removed;
}

function suricata_check_prune_filestore_files($store_path, $retention = 0) {

	/********************************************************
	 * This recursive function checks the passed File Store * 
	 * path to see if any stored captured files are         *
	 * exceedubg the retention limit. Files exceeding the   *
	 * limit are removed.                                   *
	 *                                                      *
	 * On Entry: $store_path -> full pathname to check      *
	 *           $retention  -> retention period in hours   *
	 *                          for captured files. Zero    *
	 *                          means never remove.         *
	 *                                                      *
	 * On Exit:  returns number of pruned files             *
	 ********************************************************/

	$prune_count = 0;

	if ($retention < 1) {
		return $prune_count;
	}

	// Get captured files names into array.
	$files = glob(rtrim($store_path, '/').'/*');
	$now = time();
	foreach ($files as $f) {

		// If this is a directory, execute
		// a recursive call to enumerate and
		// prune its contents.
		if (is_dir($f)) {
			$prune_count += suricata_check_prune_filestore_files($f, $retention);
		}
		else {
			if (($now - filemtime($f)) > ($retention * 3600)) {
				$prune_count++;
				unlink_if_exists($f);
			}
		}
	}

	unset($files);
	return $prune_count;
}

function suricata_check_rotate_log($log_file, $log_limit, $retention) {

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
	 * On Exit:  returns number of rotated files            *
	 ********************************************************/

	$rotated_count = 0;

	// Check the current log to see if it needs rotating.
	// If it does, rotate it and put the current time
	// on the end of the filename as UNIX timestamp.
	if (!file_exists($log_file))
		return $rotated_count;
	if (($log_limit > 0) && (filesize($log_file) >= $log_limit)) {
		$newfile = $log_file . "." . date('Y_md_Hi');
		try {
			rename($log_file, $newfile);
			touch($log_file);
			$rotated_count++;
		} catch (Exception $e) {
			logger(LOG_ERR, localize_text("Failed to rotate file '%s' -- error was %s", $log_file, $e->getMessage()), LOG_PREFIX_PKG_SURICATA);
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

	return $rotated_count;
}

/*************************
 * Start of main code    *
 *************************/

// If firewall is booting, do nothing
if (is_platform_booting())
	return;

// If no interfaces defined, there is nothing to clean up
if (!config_get_path('installedpackages/suricata/rule'))
	return;

$logs = array ();

// Build an arry of files to check and limits to check them against from our saved configuration
$logs['alerts.log']['limit'] = config_get_path('installedpackages/suricata/config/0/alert_log_limit_size');
$logs['alerts.log']['retention'] = config_get_path('installedpackages/suricata/config/0/alert_log_retention');
$logs['block.log']['limit'] = config_get_path('installedpackages/suricata/config/0/block_log_limit_size');
$logs['block.log']['retention'] = config_get_path('installedpackages/suricata/config/0/block_log_retention');
$logs['eve.json']['limit'] = config_get_path('installedpackages/suricata/config/0/eve_log_limit_size');
$logs['eve.json']['retention'] = config_get_path('installedpackages/suricata/config/0/eve_log_retention');
$logs['http.log']['limit'] = config_get_path('installedpackages/suricata/config/0/http_log_limit_size');
$logs['http.log']['retention'] = config_get_path('installedpackages/suricata/config/0/http_log_retention');
$logs['sid_changes.log']['limit'] = config_get_path('installedpackages/suricata/config/0/sid_changes_log_limit_size');
$logs['sid_changes.log']['retention'] = config_get_path('installedpackages/suricata/config/0/sid_changes_log_retention');
$logs['stats.log']['limit'] = config_get_path('installedpackages/suricata/config/0/stats_log_limit_size');
$logs['stats.log']['retention'] = config_get_path('installedpackages/suricata/config/0/stats_log_retention');
$logs['tls.log']['limit'] = config_get_path('installedpackages/suricata/config/0/tls_log_limit_size');
$logs['tls.log']['retention'] = config_get_path('installedpackages/suricata/config/0/tls_log_retention');

// Check log limits and retention in the interface logging directories if enabled
if (config_get_path('installedpackages/suricata/config/0/enable_log_mgmt') == 'on') {
	foreach (config_get_path('installedpackages/suricata/rule', []) as $value) {
		$if_real = get_real_interface($value['interface']);

		// Skip instances where pfSense physical interface
		// has been removed.
		if ($if_real == "") {
			continue;
		}
		$suricata_log_dir = SURICATALOGDIR . "suricata_{$if_real}{$value['uuid']}";
		$rotated = 0;
		foreach ($logs as $k => $p) {
			$rotated += suricata_check_rotate_log("{$suricata_log_dir}/{$k}", $p['limit']*1024, $p['retention']);
		}
		if ($rotated > 0) {
			// Send the running Suricata instance on this interface a SIGHUP signal
			// so it will re-open the log files we rotated and truncated.
			suricata_reload_config($value, SIGHUP);
			logger(LOG_NOTICE, localize_text("Logs Mgmt job rotated %s file(s) in '%s' ...", $rotated, "{$suricata_log_dir}/"), LOG_PREFIX_PKG_SURICATA);
		}

		// Prune aged-out File Store captured files if any exist
		if (is_dir("{$suricata_log_dir}/filestore") &&
		    config_get_path('installedpackages/suricata/config/0/file_store_retention') > 0) {

			$prune_count = suricata_check_prune_filestore_files("{$suricata_log_dir}/filestore", config_get_path('installedpackages/suricata/config/0/file_store_retention'));
			if ($prune_count > 0)
				logger(LOG_NOTICE, localize_text("File Store captured files cleanup job removed %s file(s) from '%s' path...", $prune_count, "{$suricata_log_dir}/filestore/"), LOG_PREFIX_PKG_SURICATA);
		}

		// Check File Store captured files storage limit and prune if necessary
		if (is_dir("{$suricata_log_dir}/filestore") &&
		    config_get_path('installedpackages/suricata/config/0/file_store_limit_size') > 0 &&
		    (intval(suricata_folderSize("{$suricata_log_dir}/filestore")/1000000) >= config_get_path('installedpackages/suricata/config/0/file_store_limit_size'))
		   ) {
			suricata_check_filestore_limit_size("{$suricata_log_dir}/filestore", config_get_path('installedpackages/suricata/config/0/file_store_limit_size'));
		}

		// If a user-customized file store directory is set, check it, too
		if (isset($value['file_store_logdir'])) {
			if (is_dir($value['file_store_logdir']) &&
			    config_get_path('installedpackages/suricata/config/0/file_store_limit_size') > 0 &&
			    (intval(suricata_folderSize($value['file_store_logdir'])/1000000) >= (int)config_get_path('installedpackages/suricata/config/0/file_store_limit_size'))) {
				suricata_check_filestore_limit_size($value['file_store_logdir'], config_get_path('installedpackages/suricata/config/0/file_store_limit_size'));
			}
		}

		// Prune aged-out TLS Certs Store files if any exist
		if (is_dir("{$suricata_log_dir}/certs") &&
		    config_get_path('installedpackages/suricata/config/0/tls_certs_store_retention') > 0) {
			$now = time();
			$files = glob("{$suricata_log_dir}/certs/*.*");
			$prune_count = 0;
			foreach ($files as $f) {
				if (($now - filemtime($f)) > (int)(config_get_path('installedpackages/suricata/config/0/tls_certs_store_retention') * 3600)) {
					$prune_count++;
					unlink_if_exists($f);
				}
			}
			if ($prune_count > 0)
				logger(LOG_NOTICE, localize_text("TLS Certs Store cleanup job removed %s file(s) from %s...", $prune_count, "{$suricata_log_dir}/certs/"), LOG_PREFIX_PKG_SURICATA);
			unset($files);
		}

		// Prune aged-out PCAP log files if any exist
		if (is_dir("{$suricata_log_dir}/pcaps") &&
		    config_get_path('installedpackages/suricata/config/0/pkt_capture_file_retention') > 0) {
			$now = time();
			$files = glob("{$suricata_log_dir}/pcaps/*.*");
			$prune_count = 0;
			foreach ($files as $f) {
				if (($now - filemtime($f)) > (int)(config_get_path('installedpackages/suricata/config/0/pkt_capture_file_retention') * 3600)) {
					$prune_count++;
					unlink_if_exists($f);
				}
			}
			if ($prune_count > 0)
				logger(LOG_NOTICE, localize_text("PCAP files retention cleanup job removed %s file(s) from %s whose creation date exceeded the retention limit.", $prune_count, "{$suricata_log_dir}/pcaps/"), LOG_PREFIX_PKG_SURICATA);
			unset($files);
		}

		// Prune any pcap log files over configured limit
		$files = glob("{$suricata_log_dir}/pcaps/log.pcap.*");
		if (count($files) > $value['max_pcap_log_files']) {
			$over = count($files) - $value['max_pcap_log_files'];
			$remove_files = array();
			while ($over > 0) {
				$remove_files[] = array_shift($files);
				$over--;
			}
			$prune_count = 0;
			foreach ($remove_files as $f) {
				$prune_count++;
				unlink_if_exists($f);
			}
			if ($prune_count > 0)
				logger(LOG_NOTICE, localize_text("Packet Capture log cleanup job removed %s file(s) from %s to stay below the configured max files limit.", $prune_count, "{$suricata_log_dir}/pcaps/"), LOG_PREFIX_PKG_SURICATA);
			unset($files, $remove_files);
		}
	}
}

// Check the overall log directory limit (if enabled) and prune if necessary
if (config_get_path('installedpackages/suricata/config/0/suricataloglimit') == 'on') {
	suricata_check_dir_size_limit(config_get_path('installedpackages/suricata/config/0/suricataloglimitsize'));
}
return true;
?>
