<?php
/*
 * squid_antivirus_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2017 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2010 Serg Dvoriancev <dv_serg@mail.ru>
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

require_once("functions.inc");
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");
require_once("service-utils.inc");
if (file_exists("/usr/local/pkg/squid.inc")) {
	require_once("/usr/local/pkg/squid.inc");
} else {
	echo "No squid.inc found. You must have Squid package installed to use this widget.";
}

if (isset($config['system']['use_mfs_tmpvar'])) {
	define('PATH_CLAMDB', '/usr/local/share/clamav-db/');
} else {
	define('PATH_CLAMDB', '/var/db/clamav/');
}
define('PATH_SQUID', SQUID_BASE . '/bin/squid');
define('PATH_AVLOG', '/var/log/c-icap/virus.log');
global $clamd_path, $img;
$clamd_path = SQUID_BASE . "/sbin/clamd";
$img = array();
$img['up'] = '<i class="fa fa-level-up text-success" title="Service running"></i>';
$img['down'] = '<i class="fa fa-level-down text-danger" title="Service not running"></i>';

function squid_avdb_info($filename) {
	$stl = "style='padding-top: 0px; padding-bottom: 0px; padding-left: 4px; padding-right: 4px; border-left: 1px solid #999999;'";
	$r = '';
	$path = PATH_CLAMDB . "/{$filename}";
	if (file_exists($path)) {
		$handle = '';
		if ($handle = fopen($path, "r")) {
			$s = fread($handle, 1024);
			$s = explode(':', $s);
			// datetime
			$dt = explode(" ", $s[1]);
			$s[1] = strftime("%Y.%m.%d", strtotime("{$dt[0]} {$dt[1]} {$dt[2]}"));
			if ($s[0] == 'ClamAV-VDB') {
				$r .= "<tr><td>{$filename}</td><td {$stl}>{$s[1]}</td><td {$stl}>{$s[2]}</td><td $stl>{$s[7]}</td></tr>";
			}
			fclose($handle);
		}
		return $r;
	}
}

function squid_antivirus_bases_info() {
	$db = '<table class="table table-striped table-hover table-condensed"><tbody>';
	$db .= '<tr><th>Database</th><th>Date</th><th>Version</th><th>Builder</th></tr>';
	$avdbs = array("daily.cvd", "daily.cld", "bytecode.cvd", "bytecode.cld", "main.cvd", "main.cld", "safebrowsing.cvd", "safebrowsing.cld");
	foreach ($avdbs as $avdb) {
		$db .= squid_avdb_info($avdb);
	}
	$db .= '</tbody></table>';
	return $db;
}

function squid_clamav_version() {
	global $img;
	// ClamAV status and version
	$s = (is_service_running("clamd") ? $img['up'] : $img['down']);
	$rc = pkg_exec("query '%v' clamav", $version, $err);
	$version = (($rc != 0) ? "N/A" : $version);
	$s .= "&nbsp;&nbsp;ClamAV {$version}";
	$s .= "&nbsp;&nbsp;";
	// C-ICAP status and version
	$s .= (is_service_running("c-icap") ? $img['up'] : $img['down']);
	$rc = pkg_exec("query '%v' c-icap", $version, $err);
	$version = (($rc != 0) ? "N/A" : $version);
	$s .= "&nbsp;&nbsp;C-ICAP {$version}";
	// SquidClamav version
	$rc = pkg_exec("query '%v' squidclamav", $version, $err);
	$version = (($rc != 0) ? "N/A" : $version);
	$s .= "+&nbsp;&nbsp;SquidClamav {$version}";
	return $s;
}

function squid_avupdate_status() {
	global $clamd_path;
	$s = "N/A";
	if (is_executable($clamd_path)) {
		$lastupd = preg_split("@/@", shell_exec("{$clamd_path} -V"));
		$s = $lastupd[2];
	}
	return $s;
}

function squid_antivirus_statistics() {
	$s = "Unknown (no log exists)";
	if (file_exists(PATH_AVLOG)) {
		$log = file_get_contents(PATH_AVLOG);
		$count = substr_count(strtolower($log), "virus found");
		$s = "Found {$count} virus(es) total.";
	}
	return $s;
}

?>

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
	<tbody>
		<tr>
			<th>Squid Version</th>
			<td width="75%">
			<?php
				$updown = (is_service_running("squid") ? $img['up'] : $img['down']);
				$rc = pkg_exec("query '%v' squid", $version, $err);
				$version = (($rc != 0) ? "N/A" : $version);
				echo "{$updown}&nbsp;&nbsp;${version}";
			?>
			</td>
		</tr>
		<tr>
			<th>Antivirus Scanner</th>
			<td width="75%">
				<?php echo squid_clamav_version(); ?>
			</td>
		</tr>
		<tr>
			<th style="vertical-align:middle;">Antivirus Bases</th>
			<td width="75%">
				<?php echo squid_antivirus_bases_info(); ?>
			</td>
		</tr>
		<tr>
			<th>Last Update</th>
			<td width="75%">
				<?php echo squid_avupdate_status(); ?>
			</td>
		</tr>
		<tr>
			<th>Statistics</th>
			<td width="75%">
				<?php echo squid_antivirus_statistics(); ?>
			</td>
		</tr>
	</tbody>
	</table>
</div>
