<?php
include "globals.inc";
include "config.inc";
$page_info = <<<EOD
/*
 * sgerror.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006-2011 Serg Dvoriancev
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

----------------------------------------------------------------------------------------------------------------------
SquidGuard error page generator
----------------------------------------------------------------------------------------------------------------------
This program processes redirection requests to specified URL or generated error page for a standard HTTP error code.
Redirection supports HTTP and HTTPS protocols.
----------------------------------------------------------------------------------------------------------------------
Format:
	sgerror.php?url=[http://myurl]or[https://myurl]or[error_code[space_code]output-message][incoming SquidGuard variables]
Incoming SquidGuard variables:
	a=client_address
	n=client_name
	i=client_user
	s=client_group
	t=target_group
	u=client_url
Example:
	sgerror.php?url=http://myurl.com&a=..&n=..&i=..&s=..&t=..&u=..
	sgerror.php?url=https://myurl.com&a=..&n=..&i=..&s=..&t=..&u=..
	sgerror.php?url=404%20output-message&a=..&n=..&i=..&s=..&t=..&u=..
----------------------------------------------------------------------------------------------------------------------
Tags:
	myurl and output messages can include Tags
		[a] - client address
		[n] - client name
		[i] - client user
		[s] - client group
		[t] - target group
		[u] - client url
Example:
	sgerror.php?url=401 Unauthorized access to URL [u] for client [n]
	sgerror.php?url=http://my_error_page.php?cladr=%5Ba%5D&clname=%5Bn%5D // %5b=[ %d=]
----------------------------------------------------------------------------------------------------------------------
Special Tags:
	blank     - get blank page
	blank_img - get one-pixel transparent image (to replace images such as banners, ads, etc.)
Example:
	sgerror.php?url=blank
	sgerror.php?url=blank_img
----------------------------------------------------------------------------------------------------------------------
EOD;

define('ACTION_URL', 'url');
define('ACTION_RES', 'res');
define('ACTION_MSG', 'msg');

define('TAG_BLANK', 'blank');
define('TAG_BLANK_IMG', 'blank_img');

/* ----------------------------------------------------------------------------------------------------------------------
 * ?url=EMPTY_IMG
 *      Use this option to replace banners/ads with a transparent picture. This is better for web page rendering.
 * ----------------------------------------------------------------------------------------------------------------------
 * NULL GIF file
 * HEX: 47 49 46 38 39 61 - - -
 * SYM: G  I  F  8  9  a  01 00 | 01 00 80 00 00 FF FF FF | 00 00 00 2C 00 00 00 00 | 01 00 01 00 00 02 02 44 | 01 00 3B
 * ----------------------------------------------------------------------------------------------------------------------
 */
define('GIF_BODY', "GIF89a\x01\x00\x01\x00\x80\x00\x00\xFF\xFF\xFF\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");

$url  = '';
$msg  = '';
$cl   = Array(); // squidGuard variables: %a %n %i %s %t %u
$err_code = array();

$err_code[301] = "301 Moved Permanently";
$err_code[302] = "302 Found";
$err_code[303] = "303 See Other";
$err_code[305] = "305 Use Proxy";

$err_code[400] = "400 Bad Request";
$err_code[401] = "401 Unauthorized";
$err_code[402] = "402 Payment Required";
$err_code[403] = "403 Forbidden";
$err_code[404] = "404 Not Found";
$err_code[405] = "405 Method Not Allowed";
$err_code[406] = "406 Not Acceptable";
$err_code[407] = "407 Proxy Authentication Required";
$err_code[408] = "408 Request Time-out";
$err_code[409] = "409 Conflict";
$err_code[410] = "410 Gone";
$err_code[411] = "411 Length Required";
$err_code[412] = "412 Precondition Failed";
$err_code[413] = "413 Request Entity Too Large";
$err_code[414] = "414 Request-URI Too Large";
$err_code[415] = "415 Unsupported Media Type";
$err_code[416] = "416 Requested range not satisfiable";
$err_code[417] = "417 Expectation Failed";

$err_code[500] = "500 Internal Server Error";
$err_code[501] = "501 Not Implemented";
$err_code[502] = "502 Bad Gateway";
$err_code[503] = "503 Service Unavailable";
$err_code[504] = "504 Gateway Time-out";
$err_code[505] = "505 HTTP Version not supported";

/* ----------------------------------------------------------------------------------------------------------------------
 * Functions
 * ----------------------------------------------------------------------------------------------------------------------
 */
function get_page($body) { ?>
<html>
	<body>
<?=$body?>
	</body>
</html>
<?php
}

/*
 * Generate an error page for the user
 */
function get_error_page($er_code_id, $err_msg='') {
	global $err_code, $cl;
	header("HTTP/1.1 " . $err_code[$er_code_id]);

?>
<html>
	<head>
		<title>squidGuard Error page</title>
	</head>
	<body>
	<?php if (config_get_path('installedpackages/squidguarddefault/config/0/deniedmessage')): ?>
		<h3><?= config_get_path('installedpackages/squidguarddefault/config/0/deniedmessage') ?>: <?= htmlspecialchars($err_code[$er_code_id]) ?></h3>;
	<?php else: ?>
		<h3>Request denied by <?= g_get('product_name') ?> proxy: <?= htmlspecialchars($err_code[$er_code_id]) ?></h3>
	<?php endif; ?>

	<?php if ($err_msg): ?>
		<b>Reason:</b> <?= htmlspecialchars($err_msg) ?>
	<?php endif; ?>

		<hr size="1" noshade>
	<?php if ($cl['a']): ?>
		<b> Client address: </b> <?= htmlspecialchars($cl['a']) ?><br/>
	<?php endif; ?>

	<?php if ($cl['n']): ?>
		<b> Client name:    </b> <?= htmlspecialchars($cl['n']) ?><br/>
	<?php endif; ?>

	<?php if ($cl['i']): ?>
		<b> Client user:    </b> <?= htmlspecialchars($cl['i']) ?><br/>
	<?php endif; ?>

	<?php if ($cl['s']): ?>
		<b> Client group:   </b> <?= htmlspecialchars($cl['s']) ?><br/>
	<?php endif; ?>

	<?php if ($cl['t']): ?>
		<b> Target group:   </b> <?= htmlspecialchars($cl['t']) ?><br/>
	<?php endif; ?>

	<?php if ($cl['u']): ?>
		<b> URL:            </b> <?= htmlspecialchars($cl['u']) ?><br/>
	<?php endif; ?>

		<hr size="1" noshade>
	</body>
</html>
<?php
}

function get_about() {
	global $err_code, $page_info; ?>
<?= str_replace("\n", "<br/>", $page_info); ?>
<br/>
<table>
	<tr><th><b>HTTP error codes (ERROR_CODE):</b></th></tr>
	<?php foreach ($err_code as $val): ?>
	<tr><td><?= htmlspecialchars($val) ?></td></tr>
	<?php endforeach; ?>
</table>
<?php
}


/* ----------------------------------------------------------------------------------------------------------------------
 * Check arguments
 * ----------------------------------------------------------------------------------------------------------------------
 */
if (count($_REQUEST)) {
	$url  = trim($_REQUEST['url']);
	$msg  = $_REQUEST['msg'];
	$cl['a'] = $_REQUEST['a'];
	$cl['n'] = $_REQUEST['n'];
	$cl['i'] = $_REQUEST['i'];
	$cl['s'] = $_REQUEST['s'];
	$cl['t'] = $_REQUEST['t'];
	$cl['u'] = $_REQUEST['u'];
} else {
	// Show 'About page'
	echo get_page(get_about());
	exit();
}

/* ----------------------------------------------------------------------------------------------------------------------
 * Process URLs
 * ----------------------------------------------------------------------------------------------------------------------
 */
if ($url) {
	$err_id = 0;

	// Check error code
	foreach ($err_code as $key => $val) {
		if (strpos(strtolower($url), strval($key)) === 0) {
			$err_id = $key;
			break;
		}
	}

	if ($url === TAG_BLANK) {
		// Output a blank page
		echo get_page('');
	} elseif ($url === TAG_BLANK_IMG) {
		// Return a blank image
		header("Content-Type: image/gif;"); // charset=windows-1251");
		echo GIF_BODY;
	} elseif ($err_id !== 0) {
		// Output an error code
		$er_msg = strstr($_GET['url'], ' ');
		echo get_error_page($err_id, $er_msg);
	} elseif ((strpos(strtolower($url), "http://") === 0) or (strpos(strtolower($url), "https://") === 0)) {
		// Redirect to the specified url
		header("HTTP/1.0");
		header("Location: $url", '', 302);
	} else {
		// Output an error
		echo get_page("sgerror: error arguments " . htmlspecialchars($url));
	}
} else {
	echo get_page($_SERVER['QUERY_STRING']); //$url . implode(" ", $_GET));
	// echo get_error_page(500);
}
?>
