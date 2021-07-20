<?php
/*
	sarg_frame.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2012 Marcello Coutinho <marcellocoutinho@gmail.com>
	Copyright (C) 2015 ESF, LLC
	Copyright KONNTROL Tecnologia Epp - 2016-2021
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
require_once("authgui.inc");

session_start();

//Starting Cache Session - export to PDF process
ob_start();


$uname = posix_uname();
if ($uname['machine'] == 'amd64') {
	ini_set('memory_limit', '250M');
}

// local file inclusion check
if(!empty($_REQUEST['file'])){
        $_REQUEST['file']=preg_replace('/(\.+\/|\\\.*|\/{2,})*/',"", $_REQUEST['file']);
}

if (preg_match("/(\S+)\W(\w+.html)/", $_REQUEST['file'], $matches)) {
	// URL format
	// https://192.168.1.1/sarg_reports.php?file=2012Mar30-2012Mar30/index.html
	$url = $matches[2];
	$prefix = $matches[1];
} else {
	$url = "index.html";
	$prefix = "";
}

$url = ($_REQUEST['file'] == "" ? "index.html" : $_REQUEST['file']);
$dir = "/usr/local/sarg-reports";
if ($_REQUEST['dir'] != "") {
    $dsuffix = preg_replace("/\W/", "", $_REQUEST['dir']);
    $dir .= "/" . $dsuffix;
} else {
    $dsuffix = "";
}

$rand = rand(100000000000, 999999999999);
$report = "";

if (file_exists("{$dir}/{$url}")) {
	$report = file_get_contents("{$dir}/{$url}");
} elseif (file_exists("{$dir}/{$url}.gz")) {
	$data = gzfile("{$dir}/{$url}.gz");
	$report = implode($data);
	unset ($data);
}
if ($report != "" ) {
	$pattern[0] = "/href=\W(\S+html)\W/";
	$replace[0] = "href=/sarg_frame.php?dir=" . $dsuffix . "&prevent=" . $rand . "&file=$prefix/$1";
	$pattern[1] = '/img src="\S+\W([a-zA-Z0-9.-]+.png)/';
	$replace[1] = 'img src="/sarg-images/$1';
	$pattern[2] = '@img src="([.a-z/]+)/(\w+\.\w+)@';
	$replace[2] = 'img src="/sarg-images' . $prefix . '/$1/$2';
	$pattern[3] = '/img src="([a-zA-Z0-9.-_]+).png/';
	$replace[3] = 'img src="/sarg-images/temp/$1.' . $rand . '.png';
	$pattern[4] = '/<head>/';
	$replace[4] = '<head><META HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE"><META HTTP-EQUIV="PRAGMA" CONTENT="NO-CACHE">';

	// look for graph files inside reports.
	if (preg_match_all('/img src="([a-zA-Z0-9._-]+).png/', $report, $images)) {
		conf_mount_rw();
		for ($x = 0; $x < count($images[1]); $x++) {
			copy("{$dir}/{$prefix}/{$images[1][$x]}.png", "/usr/local/www/sarg-images/temp/{$images[1][$x]}.{$rand}.png");
		}
		conf_mount_ro();
	}

	print preg_replace($pattern, $replace, $report);



} else {
	print "Error: Could not find report index file.<br />Check and save Sarg settings and try to force Sarg schedule.";
}

$pdf = ob_get_clean();
echo $pdf;
$_SESSION['pdf'] = $pdf;

?>
<button class="btn btn-success" onclick=" window.open('sarg_report_pdf.php','_blank')"> Open FullScreen for PDF</button>
