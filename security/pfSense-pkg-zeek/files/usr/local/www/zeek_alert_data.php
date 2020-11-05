<?php
/*
* zeek_alert_data.php
* part of pfSense (https://www.pfSense.org/)
* Copyright (c) 2018-2020 Prosper Doko
* Copyright (c) 2020 Mark Overholser
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

require_once("guiconfig.inc");

/* Requests */
if ($_POST) {
	global $program, $logfile;

	$program = strtolower($_POST['program']);
	$logfile = $_POST['logfile'];

	if ($program == "zeek") {
		// Define log file
		$log = '/usr/local/spool/zeek/'.$logfile;
		$loghead = fetch_head($log);
		echo "<thead>";

		foreach ($loghead as $value) {
			if (preg_match("/\bfields\b/", $value)) {
				$headfield = preg_split("/[\t,]/", $value);
				show_tds($headfield);
				break;
			}
		}
		echo "</thead>";
		// Fetch lines
		$logarr = fetch_log($log);
		// Print lines
		foreach ($logarr as $logent) {
			if (!is_numeric($logent[0])) {
				continue;
			}
			// Split line by space delimiter
			$logline = preg_split("/[\t,]/", $logent);
			$logline[0] = date("d.m.Y H:i:s", $logline[0]);

			echo "<tr>";
			foreach ($logline as $value) {
				echo "<td class=\"col-md-4\">{$value}</td>\t";
			}
			echo "</tr>\n";
			echo "<tr><td></td></tr>";
		}
	}
}

/* Functions */
function fetch_head($log) {
	$log = escapeshellarg($log);
	// Get logs first 10 lines
	exec("/usr/bin/head -n 10 {$log}", $loghead);
	// Return logs head
	return $loghead;
}

// Show zeek Logs
function fetch_log($log) {
	$log = escapeshellarg($log);
	// Get data from form post
	$lines = escapeshellarg(is_numeric($_POST['maxlines']) ? $_POST['maxlines'] : 50);
	// Get logs based in filter expression
	exec("/usr/bin/tail -r -n {$lines} {$log}", $logarr);
	// Return logs
	return $logarr;
}

function show_tds($tds) {
	echo "<tr>";
	$index = 0;
	foreach ($tds as $td) {
		if ($index != 0) {
			echo "<th class=\"col-md-4\">{$td}</th>";
		}
		$index = $index + 1 ;
	}
	echo "</tr>";
}
?>
