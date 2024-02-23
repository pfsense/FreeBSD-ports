<?php
/*
 * squid_clwarn.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015 Marcello Coutinho
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
require_once("pkg-utils.inc");
$rc = pkg_exec("query '%v' squidclamav", $version, $err);
if ($rc != 0) {
	$VERSION = "N/A";
} else {
	$VERSION = "{$version}";
}
$url = htmlspecialchars($_REQUEST['url']);
$virus = ($_REQUEST['virus'] ? $_REQUEST['virus'] : $_REQUEST['malware']);

// Remove clamd infos
$vp[0]="/stream: /";
$vp[1]="/ FOUND/";
$vr[0]="";
$vr[1]="";

$virus = htmlspecialchars(preg_replace($vp, $vr, $virus));

$source = htmlspecialchars(preg_replace("@/-@", "", $_REQUEST['source']));
$user = htmlspecialchars($_REQUEST['user']);

$TITLE_VIRUS = "SquidClamav $VERSION: Virus detected!";
$subtitle = 'Virus name';
$errorreturn = 'This file cannot be downloaded.';
$urlerror = 'contains a virus';
if (preg_match("/Safebrowsing/", $virus)) {
	$TITLE_VIRUS = "SquidClamav $VERSION: Unsafe Browsing detected";
	$subtitle = 'Malware / phishing type';
	$urlerror = 'is listed as suspicious';
	$errorreturn = 'This page cannot be displayed';
}

error_log(date("Y-m-d H:i:s") . " | VIRUS FOUND | "
	. str_replace('|', '', $virus) . " | "
	. str_replace('|', '', $url) . " | "
	. str_replace('|', '', $source) . " | "
	. str_replace('|', '', $user) . "\n", 3, "/var/log/c-icap/virus.log");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<style type="text/css">
.visu {
	border:1px solid #C0C0C0;
	color:#FFFFFF;
	position: relative;
	min-width: 13em;
	max-width: 52em;
	margin: 4em auto;
	border: 1px solid ThreeDShadow;
	border-radius: 10px;
	padding: 3em;
	-moz-padding-start: 30px;
	background-color: #8B0000;
}
.visu h2, .visu h3, .visu h4 {
	font-size: 130%;
	font-family: "times new roman", times, serif;
	font-style: normal;
	font-weight: bolder;

}
a:link, a:visited {
	color: #FFFFFF;
	text-decoration: underline;
}
</style>
<title><?=$TITLE_VIRUS?></title>
</head>
<body>
<div class="visu">
	<h2><?=$TITLE_VIRUS?></h2>
	<hr />
	<p>
	The requested URL <?=$url?> <?=$urlerror?><br/>
	<?=$subtitle?>: <?=$virus?>
	</p>
	<p><?=$errorreturn?></p>
	<p>Origin: <?=$source?> / <?=$user?></p>
	<hr />
	<p><small>Powered by <a href="http://squidclamav.darold.net/">SquidClamav <?=$VERSION?></a></small></p>
</div>
</body>
</html>
