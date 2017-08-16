<?php
/*
 * suricata_check_cron_misc.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2014 Bill Meeks
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

global $g, $config;

function suricata_check_dir_size_limit($suricataloglimitsize) {

	/********************************************************
	 * This function checks the total size of the Suricata  *
	 * logging sub-directory structure and prunes the files *
	 * for all Suricata interfaces if the size exceeds the  *
	 * passed limit.                                        *
	 *                                                      *
	 * On Entry: $surictaaloglimitsize = dir size limit     *
	 *                                   in megabytes       *
	 ********************************************************/

	global $config;
	
	// Convert Log Limit Size setting from MB to KB
	$suricataloglimitsizeKB = round($suricataloglimitsize * 1024);
	$suricatalogdirsizeKB = suricata_Getdirsize(SURICATALOGDIR);

	if ($suricatalogdirsizeKB > 0 && $suricatalogdirsizeKB > $suricataloglimitsizeKB) {
		log_error(gettext("[Suricata] Log directory size exceeds configured limit of " . number_format($suricataloglimitsize) . " MB set on Global Settings tab. Starting cleanup of suricata logs."));
		conf_mount_rw();

		// Initialize an array of the log files we want to prune
		$logs = array ( "alerts.log", "block.log", "dns.log", "eve.json", "http.log", "files-json.log", "sid_changes.log", "stats.log", "tls.log" );

		// Clean-up the rotated logs for each configured Suricata instance
		foreach ($config['installedpackages']['suricata']['rule'] as $value) {
			$if_real = get_real_interface($value['interface']);
			$suricata_uuid = $value['uuid'];
			$suricata_log_dir = SURICATALOGDIR . "suricata_{$if_real}{$suricata_uuid}";
			log_error(gettext("[Suricata] Cleaning logs for {$value['descr']} ({$if_real})..."));
			suricata_post_delete_logs($suricata_uuid);

			foreach ($logs as $file) {
				// Cleanup any rotated logs
				log_error(gettext("[Suricata] Deleting rotated log files except last for {$value['descr']} ({$if_real}) $file..."));
				$filelist = glob("{$suricata_log_dir}/{$file}.*");
				// Keep most recent file
				unset($filelist[count($filelist) - 1]);
				foreach ($filelist as $file) {
					unlink_if_exists($file);
				}
				unset($filelist);
			}

			// Check for any captured stored files and clean them up
			unlink_if_exists("{$suricata_log_dir}/files/*");

			// Check for any captured stored TLS certs and clean them up
			unlink_if_exists("{$suricata_log_dir}/certs/*");
		}

		if (suricata_Getdirsize(SURICATALOGDIR) < suricataloglimitsizeKB) {
			goto cleanupExit;
		}

		// Cleanup any rotated logs not caught above
		log_error(gettext("[Suricata] Deleting any additional rotated log files..."));
		unlink_if_exists("{$suricata_log_dir}/suricata_*/*.log.*");
		unlink_if_exists("{$suricata_log_dir}/suricata_*/*.json.*");

		if (suricata_Getdirsize(SURICATALOGDIR) < suricataloglimitsizeKB) {
			goto cleanupExit;
		}

		// Clean-up active logs for each configured Suricata instance
		foreach ($config['installedpackages']['suricata']['rule'] as $value) {
			$if_real = get_real_interface($value['interface']);
			$suricata_uuid = $value['uuid'];
			$suricata_log_dir = SURICATALOGDIR . "suricata_{$if_real}{$suricata_uuid}";
			if (suricata_Getdirsize(SURICATALOGDIR) < suricataloglimitsizeKB) {
				goto cleanupExit;
			}

			foreach ($logs as $file) {
				// Truncate the log file if it exists
				if (file_exists("{$suricata_log_dir}/{$file}")) {
					try {
						file_put_contents("{$suricata_log_dir}/{$file}", "");
					} catch (Exception $e) {
						log_error("[Suricata] Failed to truncate file '{$suricata_log_dir}/{$file}' -- error was {$e->getMessage()}");
					}
				}

				if (suricata_Getdirsize(SURICATALOGDIR) < suricataloglimitsizeKB) {
					goto cleanupExit;
				}
			}

			if (suricata_Getdirsize(SURICATALOGDIR) < suricataloglimitsizeKB) {
				goto cleanupExit;
			}
		}

		// Truncate the Rules Update Log file if it exists
		if (file_exists(SURICATA_RULES_UPD_LOGFILE)) {
			log_error(gettext("[Suricata] Truncating the Rules Update Log file..."));
			@file_put_contents(SURICATA_RULES_UPD_LOGFILE, "");
		}

		cleanupExit:
		// This is needed if suricata is run as suricata user
		mwexec('/bin/chmod 660 /var/log/suricata/*', true);
		conf_mount_ro();
		log_error(gettext("[Suricata] Automatic clean-up of Suricata logs completed."));
	}
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
	 ********************************************************/

	// Check the current log to see if it needs rotating.
	// If it does, rotate it and put the current time
	// on the end of the filename as UNIX timestamp.
	if (!file_exists($log_file))
		return;
	if (($log_limit > 0) && (filesize($log_file) >= $log_limit)) {
		$newfile = $log_file . "." . date('Y_md_Hi');
		try {
			copy($log_file, $newfile);
			file_put_contents($log_file, "");
		} catch (Exception $e) {
			log_error("[Suricata] Failed to rotate file '{$log_file}' -- error was {$e->getMessage()}");
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

// If no interfaces defined, there is nothing to clean up
if (!is_array($config['installedpackages']['suricata']['rule']))
	return;

$logs = array ();

// Build an arry of files to check and limits to check them against from our saved configuration
$logs['alerts.log']['limit'] = $config['installedpackages']['suricata']['config'][0]['alert_log_limit_size'];
$logs['alerts.log']['retention'] = $config['installedpackages']['suricata']['config'][0]['alert_log_retention'];
$logs['block.log']['limit'] = $config['installedpackages']['suricata']['config'][0]['block_log_limit_size'];
$logs['block.log']['retention'] = $config['installedpackages']['suricata']['config'][0]['block_log_retention'];
$logs['dns.log']['limit'] = $config['installedpackages']['suricata']['config'][0]['dns_log_limit_size'];
$logs['dns.log']['retention'] = $config['installedpackages']['suricata']['config'][0]['dns_log_retention'];
$logs['eve.json']['limit'] = $config['installedpackages']['suricata']['config'][0]['eve_log_limit_size'];
$logs['eve.json']['retention'] = $config['installedpackages']['suricata']['config'][0]['eve_log_retention'];
$logs['files-json.log']['limit'] = $config['installedpackages']['suricata']['config'][0]['files_json_log_limit_size'];
$logs['files-json.log']['retention'] = $config['installedpackages']['suricata']['config'][0]['files_json_log_retention'];
$logs['http.log']['limit'] = $config['installedpackages']['suricata']['config'][0]['http_log_limit_size'];
$logs['http.log']['retention'] = $config['installedpackages']['suricata']['config'][0]['http_log_retention'];
$logs['sid_changes.log']['limit'] = $config['installedpackages']['suricata']['config'][0]['sid_changes_log_limit_size'];
$logs['sid_changes.log']['retention'] = $config['installedpackages']['suricata']['config'][0]['sid_changes_log_retention'];
$logs['stats.log']['limit'] = $config['installedpackages']['suricata']['config'][0]['stats_log_limit_size'];
$logs['stats.log']['retention'] = $config['installedpackages']['suricata']['config'][0]['stats_log_retention'];
$logs['tls.log']['limit'] = $config['installedpackages']['suricata']['config'][0]['tls_log_limit_size'];
$logs['tls.log']['retention'] = $config['installedpackages']['suricata']['config'][0]['tls_log_retention'];

// Check log limits and retention in the interface logging directories if enabled
if ($config['installedpackages']['suricata']['config'][0]['enable_log_mgmt'] == 'on') {
	foreach ($config['installedpackages']['suricata']['rule'] as $value) {
		$if_real = get_real_interface($value['interface']);
		$suricata_log_dir = SURICATALOGDIR . "suricata_{$if_real}{$value['uuid']}";
		foreach ($logs as $k => $p)
			suricata_check_rotate_log("{$suricata_log_dir}/{$k}", $p['limit']*1024, $p['retention']);

		// Prune any aged-out Barnyard2 archived logs if any exist
		if (is_dir("{$suricata_log_dir}/barnyard2/archive") &&
		    $config['installedpackages']['suricata']['config'][0]['u2_archive_log_retention'] > 0) {
			$now = time();
			$files = glob("{$suricata_log_dir}/barnyard2/archive/unified2.alert.*");
			$prune_count = 0;
			foreach ($files as $f) {
				if (($now - filemtime($f)) > ($config['installedpackages']['suricata']['config'][0]['u2_archive_log_retention'] * 3600)) {
					$prune_count++;
					unlink_if_exists($f);
				}
			}
			if ($prune_count > 0)
				log_error(gettext("[Suricata] Barnyard2 archived logs cleanup job removed {$prune_count} file(s) from {$suricata_log_dir}/barnyard2/archive/..."));
			unset($files);
		}

		// Prune aged-out File Store files if any exist
		if (is_dir("{$suricata_log_dir}/files") &&
		    $config['installedpackages']['suricata']['config'][0]['file_store_retention'] > 0) {
			$now = time();
			$files = glob("{$suricata_log_dir}/files/file.*");
			$prune_count = 0;
			foreach ($files as $f) {
				if (($now - filemtime($f)) > ($config['installedpackages']['suricata']['config'][0]['file_store_retention'] * 3600)) {
					$prune_count++;
					unlink_if_exists($f);
				}
			}
			if ($prune_count > 0)
				log_error(gettext("[Suricata] File Store cleanup job removed {$prune_count} file(s) from {$suricata_log_dir}/files/..."));
			unset($files);
		}

		// Prune aged-out TLS Certs Store files if any exist
		if (is_dir("{$suricata_log_dir}/certs") &&
		    $config['installedpackages']['suricata']['config'][0]['tls_certs_store_retention'] > 0) {
			$now = time();
			$files = glob("{$suricata_log_dir}/certs/*.*");
			$prune_count = 0;
			foreach ($files as $f) {
				if (($now - filemtime($f)) > ($config['installedpackages']['suricata']['config'][0]['tls_certs_store_retention'] * 3600)) {
					$prune_count++;
					unlink_if_exists($f);
				}
			}
			if ($prune_count > 0)
				log_error(gettext("[Suricata] TLS Certs Store cleanup job removed {$prune_count} file(s) from {$suricata_log_dir}/certs/..."));
			unset($files);
		}

		// Prune any pcap log files over configured limit
		$files = glob("{$suricata_log_dir}/log.pcap.*");
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
				log_error(gettext("[Suricata] Packet Capture log cleanup job removed {$prune_count} file(s) from {$suricata_log_dir}/..."));
			unset($files, $remove_files);
		}
	}
}

// Check the overall log directory limit (if enabled) and prune if necessary
if ($config['installedpackages']['suricata']['config'][0]['suricataloglimit'] == 'on')
	suricata_check_dir_size_limit($config['installedpackages']['suricata']['config'][0]['suricataloglimitsize']);

?>
