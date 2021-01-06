#!/usr/local/bin/php-cgi -q
<?php
/*
 * check_ip.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2016 Marcello Coutinho
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

require_once("config.inc");
require_once("globals.inc");
error_reporting(0);
global $config, $g;
// stdin loop
if (!defined(STDIN)) {
	define("STDIN", fopen("php://stdin", "r"));
}
if (!defined(STDOUT)) {
	define("STDOUT", fopen('php://stdout', 'w'));
}
while (!feof(STDIN)) {
	$check_ip = preg_replace('/[^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}]/', '', fgets(STDIN));

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpzone => $cp) {
			if (isset($cp['enable'])) {
				$db = "{$g['vardb_path']}/captiveportal{$cpzone}.db";
				$status = squid_check_ip($db, $check_ip);
				if (!$status && is_array($cp['allowedip'])) {
					foreach ($cp['allowedip'] as $ipent) {
						if (ip_in_subnet($check_ip, "{$ipent['ip']}/{$ipent['sn']}") &&
						    (($ipent['dir'] == 'from') || ($ipent['dir'] == 'both'))) {
							$status = $check_ip;
							break;
						}
					}
				}
			}
			if (isset($status)) {
				fwrite(STDOUT, "OK user={$status}\n");
				break 2;
			}
		}
	}

	fwrite(STDOUT, "ERR\n");
	break;
}

function squid_check_ip($db, $check_ip) {
	exec("/usr/local/bin/sqlite3 {$db} \"SELECT ip FROM captiveportal WHERE ip='{$check_ip}'\"", $ip);
	if ($check_ip == $ip[0]) {
		exec("/usr/local/bin/sqlite3 {$db} \"SELECT username FROM captiveportal WHERE ip='{$check_ip}'\"", $user);
		return $user[0];
	}
}

?>
