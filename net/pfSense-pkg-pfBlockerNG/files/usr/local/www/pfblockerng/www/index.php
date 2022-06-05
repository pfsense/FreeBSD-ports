<?php
/*
 * index.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2016 BBcan177@gmail.com
 * All rights reserved.
 *
 * Portions of this code are based on original work done for
 * pfSense from the following contributors:
 *
 * pkg_mgr_install.php
 * Part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2005 Colin Smith
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

require_once('globals.inc');
require_once('util.inc');

header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 2014 05:00:00 GMT");
header("Content-Type: image/gif");
echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');

// Record DNSBL HTTP Alert to logfile
$datereq = date('M d H:i:s', time());
$req_agent = str_replace(',', '', "{$_SERVER['HTTP_REFERER']} | {$_SERVER['REQUEST_URI']} | {$_SERVER['HTTP_USER_AGENT']}");
$log = htmlspecialchars("DNSBL Reject,{$datereq},{$_SERVER['HTTP_HOST']},{$_SERVER['REMOTE_ADDR']},{$req_agent}\n");
if (!empty($log)) {
	@file_put_contents('/var/log/pfblockerng/dnsbl.log', "{$log}", FILE_APPEND | LOCK_EX);
}

// Query DNSBL Alias for Domain List.
$query = str_replace('.', '\.', htmlspecialchars($_SERVER['HTTP_HOST']));
exec("/usr/bin/grep -l " . escapeshellarg("\"{$query} 60 IN A") . " /var/db/pfblockerng/dnsblalias/*", $match);
$pfb_query = strstr($match[0], 'DNSBL', FALSE);

// Query for a TLD Block
if (empty($pfb_query)) {
	$idparts	= explode('.', $query);
	$idcnt		= (count($idparts) -1);

	for ($i=1; $i <= $idcnt; $i++) {
		$d_query = implode('.', array_slice($idparts, -$i, $i, TRUE));
		exec("/usr/bin/grep -l '^{$d_query}$' /var/db/pfblockerng/dnsblalias/DNSBL_TLD", $match);

		if (!empty($match[0])) {
			$pfb_query = 'DNSBL_TLD';
			break;
		}
	}
}

// Increment DNSBL Alias counter
if (!empty($pfb_query)) {
	$pfb_found = FALSE;

	$dnsbl_info = '/var/db/pfblockerng/dnsbl_info';
	$lock_handle = @try_lock("dnsbl_info", 5);
	if ($lock_handle) {
		if (($handle = @fopen("{$dnsbl_info}", 'r')) !== FALSE) {
			if (($pfb_output = @fopen("{$dnsbl_info}.bk", 'w')) !== FALSE) {
				$pfb_found = TRUE;

				// Find line with corresponding DNSBL Aliasname
				while (($line = @fgetcsv($handle)) !== FALSE) {
					if ($line[0] == $pfb_query) {
						$line[3] += 1;
					}
					@fputcsv($pfb_output, $line);
				}
				@fclose($pfb_output);
			}
			@fclose($handle);
		}

		if ($pfb_found) {
			@rename("{$dnsbl_info}.bk", "{$dnsbl_info}");
		}
		@unlock($lock_handle);
	}
}
?>
