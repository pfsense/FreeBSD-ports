<?php
/*
 * pfb_unbound_include.inc
 * pfBlockerNG - Unbound resolver python integration
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2024 BBcan177@gmail.com
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

require_once('config.inc');
require_once('globals.inc');

define('LOG_PREFIX_PKG_PFBLOCKERNG', 'pfBlockerNG');

global $g;

config_read_file(false, true);

// Enable logging if called via pfBlockerNG
$verbose = FALSE;
if ($g['pfblockerng_include_verbose']) {
	$verbose = TRUE;
	require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');
}

// Ensure log file permissions are set as 'unbound:unbound'
foreach (array('dnsbl.log', 'dns_reply.log', 'unified.log') as $logfile) {

	if (!file_exists("/var/log/pfblockerng/{$logfile}")) {
		touch("/var/log/pfblockerng/{$logfile}");
	}

	@chown("/var/log/pfblockerng/{$logfile}", 'unbound');
	@chgrp("/var/log/pfblockerng/{$logfile}", 'unbound');
}

$python_mode = FALSE;
if (config_path_enabled('unbound', 'python') && !empty(config_get_path('unbound/python_script'))) {
	$python_mode = TRUE;
}

// Function to mount/unmount required folders into Unbound chroot
function pfb_python_mount($python_mode, $verbose, $type_mount, $type_umount, $type_perms, $folder, $grep_string) {
	global $g;
	$found = FALSE;

	// Force Resolver path
	$unbound_chroot_path = '/var/unbound';

	exec("/sbin/mount | /usr/bin/grep '{$grep_string}' 2>&1", $validate, $retval);

	// Temporary workaround for Redmine: https://redmine.pfsense.org/issues/11456
	if ($type_umount == 'devfs' && is_array($validate) && count($validate) > 1) {
		if ($verbose) {
			pfb_logger("\n   Removing duplicate mounts (" . count($validate) . "): /{$folder}", 1);
		}

		foreach ($validate as $v) {
			if (!empty($v)) {
				exec("/sbin/umount -t {$type_umount} '{$unbound_chroot_path}/{$folder}' 2>&1", $output, $retval);
				if ($retval != 0) {
					break;
				}
			}
		}
		$validate = '';
	}

	if ($python_mode) {
		// Add DNS Resolver python integration
		if (empty($validate)) {
			if ($verbose) {
				pfb_logger("\n   Mounting: /{$folder}", 1);
				$found = TRUE;
			}

			if (!is_dir("{$unbound_chroot_path}/{$folder}")) {
				safe_mkdir("{$unbound_chroot_path}/{$folder}");
			}

			$output = $retval = '';
			if ($type_mount == 'nullfs') {
				exec("/sbin/mount_nullfs -o {$type_perms} '/{$folder}' '{$unbound_chroot_path}/{$folder}' 2>&1", $output, $retval);
			} else {
				exec("/sbin/mount -t {$type_mount} '{$unbound_chroot_path}/{$folder}' 2>&1", $output, $retval);
			}

			if ($retval != 0) {
				$log_err = "Failed to mount /{$folder}";
				logger(LOG_ERR, "[Unbound-pymod]: {$log_err}", LOG_PREFIX_PKG_PFBLOCKERNG);
				if ($verbose) {
					pfb_logger("\n   {$log_err}", 1);
				}
			}
			else {
				if ($type_perms == 'rw') {
					@chown("{$unbound_chroot_path}/{$folder}", 'unbound');
					@chgrp("{$unbound_chroot_path}/{$folder}", 'unbound');
				}
			}
		}
	}

	// Remove DNS Resolver python integration
	elseif (!empty($validate)) {
		$found = TRUE;
		exec("/sbin/umount -t {$type_umount} '{$unbound_chroot_path}/{$folder}' 2>&1", $output, $retval);
		if ($retval == 0) {
			if (is_dir("{$unbound_chroot_path}/{$folder}")) {
				if ($verbose) {
					pfb_logger("\n  Unmounting: /{$folder}", 1);
				}
				if (strpos($folder, '/') !== FALSE) {
					$folder_ex = explode('/', $folder);
					if (!empty($folder_ex)) {
						foreach ($folder_ex as $folder_remove) {
							$folder_remove = implode('/', $folder_ex);
							if ($folder_remove == 'usr/local') {
								break;
							}

							if (!empty($folder_remove) && is_dir("{$unbound_chroot_path}/{$folder_remove}")) {
								@rmdir("{$unbound_chroot_path}/{$folder_remove}");
							}
							array_pop($folder_ex);
						}
					}
				}
				else {
					@rmdir("{$unbound_chroot_path}/{$folder}");
				}
			}
		}
		else {
			$log_err = "Failed to unmount /{$folder}";
			logger(LOG_ERR, "[Unbound-pymod]: {$log_err}", LOG_PREFIX_PKG_PFBLOCKERNG);
			if ($verbose) {
				pfb_logger("\n   {$log_err}", 1);
			}
		}
	}
	return $found;
}

// Force Resolver path
$unbound_chroot_path = '/var/unbound';

$f1 = pfb_python_mount($python_mode, $verbose, 'nullfs', 'nullfs', 'ro', 'lib', "on {$unbound_chroot_path}/lib (nullfs");
$f2 = pfb_python_mount($python_mode, $verbose, 'devfs devfs', 'devfs', '', 'dev', "devfs on {$unbound_chroot_path}/dev");
$f3 = pfb_python_mount($python_mode, $verbose, 'nullfs', 'nullfs', 'rw', 'var/log/pfblockerng', "on {$unbound_chroot_path}/var/log/pfblockerng (nullfs");
$f4 = pfb_python_mount($python_mode, $verbose, 'nullfs', 'nullfs', 'ro', 'usr/local/share/GeoIP', "on {$unbound_chroot_path}/usr/local/share/GeoIP (nullfs");

// Ensure log file permissions are set as 'unbound:unbound'
foreach (array('dnsbl.log', 'dns_reply.log') as $logfile) {
	if (file_exists("/var/log/pfblockerng/{$logfile}")) {
		@chown("/var/log/pfblockerng/{$logfile}", 'unbound');
		@chgrp("/var/log/pfblockerng/{$logfile}", 'unbound');
	}
}

if ($verbose && !$f1 && !$f2 && !$f3 && !$f4) {
	pfb_logger("\n  No changes required.", 1);
}
