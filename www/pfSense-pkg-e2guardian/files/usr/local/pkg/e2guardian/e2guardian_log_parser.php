#!/usr/local/bin/php-cgi -q
<?php
/*
 * e2guardian_log_parser.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (C) 2012-2017 Marcello Coutinho
 * Copyright (C) 2012-2014 Carlos Cesario <carloscesario@gmail.com>
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
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

/*
* Simple Squid Log parser to rewrite line with date/time human readable
* Usage:  cat /var/log/e2guardian/access.log | parser_squid_log.php
*/

$logline = fopen("php://stdin", "r");
while (!feof($logline)) {
	$line = fgets($logline);
	$line = rtrim($line);
	if ($line != "") {
		$fields = explode(' ', $line);
		// Apply date format
		$fields[0] = date("d.m.Y H:i:s", $fields[0]);
		foreach ($fields as $field) {
			// Write Squid log line with human readable date/time
			echo "{$field} ";
		}
		echo "\n";
	}
}
fclose($logline);
?>
