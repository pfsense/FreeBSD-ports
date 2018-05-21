<?php
/*
 * index.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2018 BBcan177@gmail.com
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

$ptype = array();
$ptype['REQUEST_URI'] = htmlspecialchars(str_replace('|', '--', $_SERVER['REQUEST_URI']));
foreach (array('HTTP_HOST', 'HTTP_REFERER', 'HTTP_USER_AGENT', 'REMOTE_ADDR') as $server_type) {
	$ptype[$server_type] = htmlspecialchars($_SERVER[$server_type]) ?: 'Unknown';
}

if (pathinfo($ptype['REQUEST_URI'], PATHINFO_EXTENSION) == 'js') {
	$type = 'DNSBL-JS';
	?>
	<script type="text/javascript">
		var dnsbl = "DNSBL : <?=$ptype['HTTP_HOST'];?> (JS)";
	</script>
	<?php
}
else {
	header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
	header("Expires: Sat, 26 Jul 2014 05:00:00 GMT");

	if (empty($ptype['REQUEST_URI']) || $ptype['REQUEST_URI'] != '/') {
		$type = 'DNSBL-1x1';
		header("Content-Type: image/gif");
		echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
	}
	else {
		$type = 'DNSBL-Full';
	}
}

// Send blocked domain message for root domain requests only
if ($type == 'DNSBL-Full' && file_exists('/usr/local/www/pfblockerng/www/dnsbl_active.php')) {
	include('/usr/local/www/pfblockerng/www/dnsbl_active.php');
}
?>
