#!/usr/local/bin/php-cgi -q
<?php
/*
	check_ip.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2013-2015 Marcello Coutinho
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
require_once("config.inc");
error_reporting(0);
global $g;
// stdin loop
if (!defined(STDIN)) {
	define("STDIN", fopen("php://stdin", "r"));
}
if (!defined(STDOUT)) {
	define("STDOUT", fopen('php://stdout', 'w'));
}
while (!feof(STDIN)) {
	$line = trim(fgets(STDIN));
}

unset($cp_db);
$files = scandir($g['vardb_path']);
foreach ($files as $file) {
	if (preg_match("/captive.*db/", $file)) {
		$result = squid_cp_read_db("{$g['vardb_path']}/{$file}");
		foreach ($result as $rownum => $row) {
			$cp_db[$rownum] = implode(",", $row);
		}
	}

	$usuario = "";
	//1419045939,1419045939,2000,2000,192.168.10.11,192.168.10.11,08:00:27:5c:e1:ee,08:00:27:5c:e1:ee,marcello,marcello,605a1f46e2d64556,605a1f46e2d64556,,,,,,,,,,,first,first
	if (is_array($cp_db)) {
		foreach ($cp_db as $cpl) {
			$fields = explode(",", $cpl);
			if ($fields[4] != "" && $fields[4] == $line) {
				$usuario = $fields[8];
			}
		}
	}
	if ($usuario != "") {
		$resposta = "OK user={$usuario}";
	} else {
		$resposta = "ERR";
	}
	fwrite(STDOUT, "{$resposta}\n");
	unset($cp_db);
}

/* read captive portal DB into array */
function squid_cp_read_db($file) {
	$cpdb = array();
	$DB = new SQLite3($file);
	if ($DB) {
		$response = $DB->query("SELECT * FROM captiveportal");
		if ($response != FALSE) {
			while ($row = $response->fetchArray()) {
				$cpdb[] = $row;
			}
		}
		$DB->close();
	}
	return $cpdb;
}

?>
