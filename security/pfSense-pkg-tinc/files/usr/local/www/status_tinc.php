<?php
/*
 * status_tinc.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
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

function tinc_status_usr1() {
	exec("/usr/local/sbin/tincd --config=/usr/local/etc/tinc -kUSR1");
	usleep(500000);
	$clog_path = "/usr/local/sbin/clog";
	$result = array();

	exec("{$clog_path} /var/log/tinc.log | /usr/bin/sed -e 's/.*tinc\[.*\]: //'", $result);
	$i = 0;
	foreach ($result as $line) {
		if (preg_match("/Connections:/", $line)) {
			$begin = $i;
		}
		if (preg_match("/End of connections./", $line)) {
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

function tinc_status_usr2() {
	exec("/usr/local/sbin/tincd --config=/usr/local/etc/tinc -kUSR2");
	usleep(500000);
	$clog_path = "/usr/local/sbin/clog";
	$result = array();

	exec("{$clog_path} /var/log/tinc.log | sed -e 's/.*tinc\[.*\]: //'",$result);
	$i = 0;
	foreach ($result as $line) {
		if (preg_match("/Statistics for Generic BSD (tun|tap) device/",$line)) {
			$begin = $i;
		}
		if (preg_match("/End of subnet list./",$line)) {
			$end = $i;
		}
		$i++;
	}
	$output="";
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
include("head.inc");
?>

<?php include("fbegin.inc"); ?>

<div class="panel panel-default">
        <div class="panel-heading"><h2 class="panel-title">Connection list</h2></div>
        <div class="panel-body table-responsive">
        <table class="table table-striped table-hover table-condensed">
        <tbody>
		<?php print tinc_status_usr1(); ?>
        </tbody>
        </table>
        </div>
</div>

<div class="panel panel-default">
        <div class="panel-heading"><h2 class="panel-title">Virtual network device statistics, all known nodes, edges and subnets</h2></div>
        <div class="panel-body table-responsive">
        <table class="table table-striped table-hover table-condensed">
        <tbody>
		<?php print tinc_status_usr2(); ?>
        </tbody>
        </table>
        </div>
</div>

<?php include("fend.inc"); ?>
