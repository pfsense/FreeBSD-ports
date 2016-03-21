<?php
/*
	index.php

	pfBlockerNG (DNSBL)
	Copyright (c) 2015-2016 BBcan177@gmail.com
	All rights reserved.
*/
header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 2014 05:00:00 GMT");
header("Content-Type: image/gif");
echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');

// Record DNSBL HTTP Alert to logfile
$datereq = date('M d H:i:s', $_SERVER['REQUEST_TIME']);
$req_agent = str_replace(',', '', "{$_SERVER['HTTP_REFERER']} | {$_SERVER['REQUEST_URI']} | {$_SERVER['HTTP_USER_AGENT']}");
$log = htmlspecialchars("DNSBL Reject,{$datereq},{$_SERVER['HTTP_HOST']},{$_SERVER['REMOTE_ADDR']},{$req_agent}\n");
if (!empty($log)) {
	@file_put_contents('/var/log/pfblockerng/dnsbl.log', "{$log}", FILE_APPEND | LOCK_EX);
}

// Query DNSBL Alias for Domain List.
$query = str_replace('.', '\.', htmlspecialchars($_SERVER['HTTP_HOST']));
exec("/usr/bin/grep -l ' \"{$query} 60 IN A' /var/db/pfblockerng/dnsblalias/*", $match);
$pfb_query = strstr($match[0], 'DNSBL', FALSE);

if (!empty($pfb_query)) {
	// Increment DNSBL Alias Counter
	$dnsbl_info = '/var/db/pfblockerng/dnsbl_info';
	if (($handle = @fopen("{$dnsbl_info}", 'r')) !== FALSE) {
		flock($handle, LOCK_EX);
		$pfb_output = @fopen("{$dnsbl_info}.bk", 'w');
		flock($pfb_output, LOCK_EX);

		// Find line with corresponding DNSBL Aliasname
		while (($line = @fgetcsv($handle)) !== FALSE) {
			if ($line[0] == $pfb_query) {
				$line[3] += 1;
			}
			@fputcsv($pfb_output, $line);
		}

		@fclose($pfb_output);
		@fclose($handle);
		@rename("{$dnsbl_info}.bk", "{$dnsbl_info}");
	}
}
?>
