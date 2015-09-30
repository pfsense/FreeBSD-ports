#!/usr/local/bin/php-cgi -q
<?php
/*
	swapstate_check.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2011 Jim Pingle
	Copyright (C) 2015 ESF, LLC
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
require_once('config.inc');
require_once('util.inc');
require_once('squid.inc');
global $config;

$settings = $config['installedpackages']['squidcache']['config'][0];
// Only check the cache if Squid is actually caching.
// If there is no cache then quietly do nothing.
if (isset($settings['harddisk_cache_system']) && $settings['harddisk_cache_system'] != "null") {
	$cachedir = ($settings['harddisk_cache_location'] ? $settings['harddisk_cache_location'] : '/var/squid/cache');
	$swapstate = $cachedir . '/swap.state';
	if (!file_exists($swapstate)) {
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
	if ($settings['clear_cache'] == 'on') {
		$rotate_reason .= "'Clear Cache on Log Rotate' is enabled in 'Local Cache' settings. ";
	}
	if ($argv[1] == "clean") {
		$rotate_reason .= "Clear cache forced by cronjob. ";
	}
	if (($swapstate_pct > 75) || (($diskusedpct > 90) && ($swapstate_size > 1024*1024*1024)) || $argv[1] == "clean") {
		squid_dash_z('clean');
		log_error(gettext(sprintf("$rotate_reason Removing and rotating. File was %d bytes, %d%% of total disk space.", $swapstate_size, $swapstate_pct)));
	}
}
?>
