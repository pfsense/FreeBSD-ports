<?php
/*
 * squid_monitor_data.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2012-2014 Marcello Coutinho
 * Copyright (C) 2012-2014 Carlos Cesario <carloscesario@gmail.com>
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
	global $filter, $program;
	// Actions
	$filter = preg_replace('/(@|!|>|<)/', "", htmlspecialchars($_POST['strfilter']));
	$program = strtolower($_POST['program']);
	$conf_path = 'installedpackages/squid/config/0/log_dir';
	switch ($program) {
		case 'squid':
			// Define log file
			$log = config_get_path($conf_path) . '/access.log';
			// Show table headers
			show_tds(array("Date", "IP", "Status", "Address", "User", "Destination"));
			// Fetch lines
			$logarr = fetch_log($log);
			// Print lines
			foreach ($logarr as $logent) {
				// Split line by space delimiter
				$logline = preg_split("/\s+/", $logent);

				// Word wrap the URL
				$logline[7] = htmlentities($logline[7]);
				$logline[7] = html_autowrap($logline[7]);

				// Remove /(slash) in destination row
				$logline_dest = preg_split("/\//", $logline[9]);

				// Apply filter and color
				// Need validate special chars
				if ($filter != "") {
					$logline = preg_replace("@($filter)@i","<span><font color='red'>$1</font></span>", $logline);
				}

				echo "<tr valign=\"top\">\n";
				echo "<td class=\"listlr\" nowrap=\"nowrap\">{$logline[0]} {$logline[1]}</td>\n";
				echo "<td class=\"listr\">{$logline[3]}</td>\n";
				echo "<td class=\"listr\">{$logline[4]}</td>\n";
				echo "<td class=\"listr\" width=\"*\">{$logline[7]}</td>\n";
				echo "<td class=\"listr\">{$logline[8]}</td>\n";
				echo "<td class=\"listr\">{$logline_dest[1]}</td>\n";
				echo "</tr>\n";
			}
			break;
		case 'squid_cache';
			// Define log file
			$log = config_get_path($conf_path) . '/cache.log';
			// Show table headers
			show_tds(array("Date-Time", "Message"));
			// Fetch lines
			$logarr = fetch_log($log);
			foreach ($logarr as $logent) {
				// Split line by delimiter
				$logline = preg_split("@\|@", $logent);

				// Replace some build host nonsense and apply time format
				$logline[0] = date("d.m.Y H:i:s", strtotime(str_replace("kid1", "", $logline[0])));

				// Word wrap the message
				$logline[1] = htmlentities($logline[1]);
				$logline[1] = html_autowrap($logline[1]);

				echo "<tr>\n";
				echo "<td class=\"listlr\" nowrap=\"nowrap\">{$logline[0]}</td>\n";
				echo "<td class=\"listr\" nowrap=\"nowrap\">{$logline[1]}</td>\n";
				echo "</tr>\n";
			}
			break;
		case 'sguard';
			$log = '/var/squidGuard/log/block.log';
			// Show table headers
			show_tds(array("Date-Time", "ACL", "Address", "Host", "User"));
			// Fetch lines
			$logarr = fetch_log($log);
			foreach ($logarr as $logent) {
				// Split line by space delimiter
				$logline = preg_split("/\s+/", $logent);

				// Apply time format
				$logline[0] = date("d.m.Y", strtotime($logline[0]));

				// Word wrap the URL
				$logline[4] = htmlentities($logline[4]);
				$logline[4] = html_autowrap($logline[4]);

				// Apply filter color
				// Need validate special chars
				if ($filter != "") {
					$logline = preg_replace("@($filter)@i", "<span><font color='red'>$1</font></span>", $logline);
				}

				echo "<tr>\n";
				echo "<td class=\"listlr\" nowrap=\"nowrap\">{$logline[0]} {$logline[1]}</td>\n";
				echo "<td class=\"listr\">{$logline[3]}</td>\n";
				echo "<td class=\"listr\" width=\"*\">{$logline[4]}</td>\n";
				echo "<td class=\"listr\">{$logline[5]}</td>\n";
				echo "<td class=\"listr\">{$logline[6]}</td>\n";
				echo "</tr>\n";
			}
			break;
		case 'cicap_virus';
			// Define log file
			$log = '/var/log/c-icap/virus.log';
			// Show table headers
			show_tds(array("Date-Time", "Message", "Virus", "URL", "Host", "User"));
			// Fetch lines
			$logarr = fetch_log($log);
			foreach ($logarr as $logent) {
				// Split line by delimiter
				$logline = preg_split("/\|/", $logent);

				// Apply time format
				$logline[0] = htmlspecialchars(date("d.m.Y H:i:s", strtotime($logline[0])));

				// Don't trust these fields
				$logline[1] = htmlentities($logline[1]);
				$logline[2] = htmlentities($logline[2]);
				$logline[4] = htmlentities($logline[4]);
				$logline[5] = htmlentities($logline[5]);

				// Word wrap the URL
				$logline[3] = htmlentities($logline[3]);
				$logline[3] = html_autowrap($logline[3]);

				echo "<tr>\n";
				echo "<td class=\"listlr\" nowrap=\"nowrap\">{$logline[0]}</td>\n";
				echo "<td class=\"listr\" nowrap=\"nowrap\">{$logline[1]}</td>\n";
				echo "<td class=\"listr\">{$logline[2]}</td>\n";
				echo "<td class=\"listr\">{$logline[3]}</td>\n";
				echo "<td class=\"listr\">{$logline[4]}</td>\n";
				echo "<td class=\"listr\">{$logline[5]}</td>\n";
				echo "</tr>\n";
			}
			break;
		case 'cicap_access';
			// Define log file
			$log = '/var/log/c-icap/access.log';
			// Show table headers
			show_tds(array("Date-Time", "Message"));
			// Fetch lines
			$logarr = fetch_log($log);
			foreach ($logarr as $logent) {
				// Split line by delimiter
				$logline = preg_split("/,/", $logent);

				// Apply time format
				$logline[0] = date("d.m.Y H:i:s", strtotime($logline[0]));

				// Word wrap the message
				$logline[1] = htmlentities($logline[1]);
				$logline[1] = html_autowrap($logline[1]);

				echo "<tr>\n";
				echo "<td class=\"listlr\" nowrap=\"nowrap\">{$logline[0]}</td>\n";
				echo "<td class=\"listr\" nowrap=\"nowrap\">{$logline[1]}</td>\n";
				echo "</tr>\n";
			}
			break;
		case 'cicap_server';
			// Define log file
			$log = '/var/log/c-icap/server.log';
			// Show table headers
			show_tds(array("Date-Time", "Message"));
			// Fetch lines
			$logarr = fetch_log($log);
			foreach ($logarr as $logent) {
				// Split line by delimiter
				$logline = preg_split("/,/", $logent);

				// Apply time format
				$logline[0] = date("d.m.Y H:i:s", strtotime($logline[0]));

				// Word wrap the message
				$logline[2] = htmlentities($logline[2]);
				$logline[2] = html_autowrap($logline[2]);

				echo "<tr>\n";
				echo "<td class=\"listlr\" nowrap=\"nowrap\">{$logline[0]}</td>\n";
				echo "<td class=\"listr\" nowrap=\"nowrap\">{$logline[2]}</td>\n";
				echo "</tr>\n";
			}
			break;
		case 'freshclam';
			// Define log file
			$log = '/var/log/clamav/freshclam.log';
			// Show table headers
			show_tds(array("Message"));
			// Fetch lines
			$logarr = fetch_log($log);
			foreach ($logarr as $logent) {
				$logline = preg_split("/\n/", $logent);
				// Word wrap the message
				$logline[0] = htmlentities($logline[0]);
				$logline[0] = html_autowrap($logline[0]);

				echo "<tr>\n";
				echo "<td class=\"listlr\" nowrap=\"nowrap\">{$logline[0]}</td>\n";
				echo "</tr>\n";
			}
			break;
		case 'clamd';
			// Define log file
			$log = '/var/log/clamav/clamd.log';
			// Show table headers
			show_tds(array("Message"));
			// Fetch lines
			$logarr = fetch_log($log);
			foreach ($logarr as $logent) {
				$logline = preg_split("/\n/", $logent);
				// Word wrap the message
				$logline[0] = htmlentities($logline[0]);
				$logline[0] = html_autowrap($logline[0]);

				echo "<tr>\n";
				echo "<td class=\"listlr\" nowrap=\"nowrap\">{$logline[0]}</td>\n";
				echo "</tr>\n";
			}
			break;
		}
}

/* Functions */
function html_autowrap($cont) {
	// split strings
	$p = 0;
	$pstep = 25;
	$str = $cont;
	$cont = '';
	for ($p = 0; $p < strlen($str); $p += $pstep) {
		$s = substr($str, $p, $pstep);
		if (!$s) {
			break;
		}
		$cont .= $s . "<wbr />";
	}
	return $cont;
}

// Show Squid Logs
function fetch_log($log) {
	global $filter, $program;
	$log = escapeshellarg($log);
	// Get data from form post
	$lines = escapeshellarg(is_numeric($_POST['maxlines']) ? $_POST['maxlines'] : 50);
	if (preg_match("/!/", htmlspecialchars($_POST['strfilter']))) {
		$grep_arg = "-iv";
	} else {
		$grep_arg = "-i";
	}

	// Check program to execute or no the parser
	if ($program == "squid") {
		$parser = "| /usr/local/bin/php-cgi -q squid_log_parser.php";
	} else {
		$parser = "";
	}

	// Get logs based in filter expression
	if ($filter != "") {
		exec("/usr/bin/tail -n 2000 {$log} | /usr/bin/grep {$grep_arg} " . escapeshellarg($filter). " | /usr/bin/tail -r -n {$lines} {$parser} ", $logarr);
	} else {
		exec("/usr/bin/tail -r -n {$lines} {$log} {$parser}", $logarr);
	}
	// Return logs
	return $logarr;
};

function show_tds($tds) {
	echo "<tr valign='top'>\n";
	foreach ($tds as $td){
		echo "<td class='listhdrr'>" . gettext($td) . "</td>\n";
	}
	echo "</tr>\n";
}

?>
