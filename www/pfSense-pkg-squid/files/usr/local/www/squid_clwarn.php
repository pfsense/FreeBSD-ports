<?php
/*
	squid_clwarn.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2015 Marcello Coutinho
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
$VERSION = '6.10';
$url = $_REQUEST['url'];
$virus = ($_REQUEST['virus'] ? $_REQUEST['virus'] : $_REQUEST['malware']);
$source = preg_replace("@/-@", "", $_REQUEST['source']);
$user = $_REQUEST['user'];

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

// Remove clamd infos
$vp[0]="/stream: /";
$vp[1]="/ FOUND/";
$vr[0]="";
$vr[1]="";

$virus = preg_replace($vp, $vr, $virus);
error_log(date("Y-m-d H:i:s") . " | VIRUS FOUND | " . $virus . " | " . $url . " | " . $source . " | " . $user . "\n", 3, "/var/log/c-icap/virus.log");

?>
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
	background-color: #8b0000;
}
.visu h2, .visu h3, .visu h4 {
	font-size:130%;
	font-family:"times new roman", times, serif;
	font-style:normal;
	font-weight:bolder;
}
</style>
<div class="visu">
	<h2><?=$TITLE_VIRUS?></h2>
	<hr />
	<p>
	The requested URL <?=$url?> <?=$urlerror?><br/>
	<?=$subtitle?>: <?=$virus?>
	</p><p>
	<?=$errorreturn?>
	</p><p>
	Origin: <?=$source?> / <?=$user?>
	</p><p>
	<hr />
	<font color="blue"> Powered by <a href="http://squidclamav.darold.net/">SquidClamav <?=$VERSION?></a>.</font>
	</p>
</div>
