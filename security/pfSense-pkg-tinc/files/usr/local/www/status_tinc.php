<?php
/*
 * status_tinc.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2024 Rubicon Communications, LLC (Netgate)
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

require("guiconfig.inc");

function tinc_status_usr($usr) {
	global $g;
	exec("/usr/local/sbin/tincd --config=/usr/local/etc/tinc -k{$usr}");
	usleep(500000);
	$result = array();
	$logfile = "/var/log/tinc.log";
	$clog_path = "/usr/local/sbin/clog";
	
	if (pfs_version_compare(false, 2.4, $g['product_version'])) {
			exec(system_log_get_cat() . ' ' . sort_related_log_files($logfile, true, true) . "| /usr/bin/sed -e 's/.*tinc\[.*\]: //'", $result);
		} else {
			exec("{$clog_path} {$logfile} | /usr/bin/sed -e 's/.*tinc\[.*\]: //'", $result);
	}
	
	$i = 0;
	$matchbegin = ($usr == 'USR1') ? "Connections:" : "Statistics for Generic BSD (tun|tap) device";
	$matchend = ($usr == 'USR1') ? "End of connections." : "End of subnet list.";
	foreach ($result as $line) {
		if (preg_match("/{$matchbegin}/", $line)) {
			$begin = $i;
		}
		if (preg_match("/{$matchend}/", $line)) {
			$end = $i;
		}
		$i++;
	}
	
	$output = "";
	$i = 0;
	
	foreach ($result as $line) {
		if ($i >= $begin && $i<= $end) {
			$output .= "<tr class=\"text-nowrap\"><td>" . $line . "</td></tr>";
		}
		$i++;
	}
	return $output;
	
}

$shortcut_section = "tinc";
$pgtitle = array(gettext("Status"), "tinc");
require_once("head.inc");
?>

<div class="panel panel-default">
        <div class="panel-heading"><h2 class="panel-title">Connection list</h2></div>
        <div class="panel-body table-responsive">
        <table class="table table-striped table-hover table-condensed">
        <tbody>
		<?php print tinc_status_usr('USR1'); ?>
        </tbody>
        </table>
        </div>
</div>

<div class="panel panel-default">
        <div class="panel-heading"><h2 class="panel-title">Virtual network device statistics, all known nodes, edges and subnets</h2></div>
        <div class="panel-body table-responsive">
        <table class="table table-striped table-hover table-condensed">
        <tbody>
		<?php print tinc_status_usr('USR2'); ?>
        </tbody>
        </table>
        </div>
</div>

<?php require_once("foot.inc"); ?>
