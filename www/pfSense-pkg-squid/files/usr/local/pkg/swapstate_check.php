#!/usr/local/bin/php-cgi -q
<?php
/*
 * swapstate_check.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2025 Rubicon Communications, LLC (Netgate)
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
require_once('util.inc');
require_once('squid.inc');

$settings = config_get_path('installedpackages/squidcache/config/0', []);
// Only check the cache if Squid is actually caching.
// If there is no cache then quietly do nothing.
// If cache dir is located outside of /var/squid hierarchy, log some instructions.
if (isset($settings['harddisk_cache_system']) && $settings['harddisk_cache_system'] != "null") {
	$cachedir = ($settings['harddisk_cache_location'] ? $settings['harddisk_cache_location'] : '/var/squid/cache');
	$swapstate = $cachedir . '/swap.state';
	if (!file_exists($swapstate)) {
		return;
	}
	if (substr($cachedir, 0, 11) !== "/var/squid/") {
		logger(LOG_INFO, localize_text("%s will NOT manage Squid cache dir '%s' since it is not located under %s.", 'swapstate_check.php', $cachedir, '/var/squid'), LOG_PREFIX_PKG_SQUID);
		return;
	}

	$disktotal = disk_total_space(dirname($cachedir));
	$diskfree = disk_free_space(dirname($cachedir));
	$diskusedpct = round((($disktotal - $diskfree) / $disktotal) * 100);
	$swapstate_size = filesize($swapstate);
	$swapstate_pct = round(($swapstate_size / $disktotal) * 100);
	// If the swap.state file is taking up more than 75% of disk space,
	// or the drive is 90% full and swap.state is larger than 1GB,
	// kill it and initiate a rotate to write a fresh copy.
	$rotate_reason = "";
	if ($swapstate_pct > 75) {
		$rotate_reason .= "$cachedir/swap.state file is taking up more than 75% of disk space. ";
	}
	if ($diskusedpct > 90) {
		$rotate_reason .= "$cachedir filesystem is $diskusedpct pct full. ";
	}
	if ($swapstate_size > 1024*1024*1024) {
		$rotate_reason .= "$cachedir/swap.state is larger than 1GB. ";
	}
	if ($argv[1] == "clean") {
		$rotate_reason .= "Clear cache forced by running swapstate_check.php manually with {$argv[1]} argument. ";
	}
	if (($swapstate_pct > 75) || (($diskusedpct > 90) && ($swapstate_size > 1024*1024*1024)) || $argv[1] == "clean") {
		squid_dash_z('clean');
		logger(LOG_INFO, localize_text("%s Removing and rotating. File was %d bytes, %d%% of total disk space.", $rotate_reason, $swapstate_size, $swapstate_pct), LOG_PREFIX_PKG_SQUID);
	}
}
?>
