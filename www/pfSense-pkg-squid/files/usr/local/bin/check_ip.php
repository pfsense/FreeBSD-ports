#!/usr/local/bin/php-cgi -q
<?php
/*
	check_ip.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2013-2016 Marcello Coutinho
	Copyright (C) 2016 ESF, LLC
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
require_once("globals.inc");
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
	$check_ip = trim(fgets(STDIN));
	$dbs = glob("{$g['vardb_path']}/captiveportal*.db");

	foreach ($dbs as $db) {
		if(!strpos($db, "_radius")) {
			$status = squid_check_ip($db, $check_ip);
			break;
		}
	}
	if (isset($status)) {
		fwrite(STDOUT, "OK user={$status}\n");
	} else {
		fwrite(STDOUT, "ERR\n");
	}
}

function squid_check_ip($db, $check_ip) {
	exec("sqlite3 {$db} \"SELECT ip FROM captiveportal WHERE ip='{$check_ip}'\"", $ip);
	if ($check_ip == $ip[0]) {
		exec("sqlite3 {$db} \"SELECT username FROM captiveportal WHERE ip='{$check_ip}'\"", $user);
		return $user[0];
	}
}

?>
