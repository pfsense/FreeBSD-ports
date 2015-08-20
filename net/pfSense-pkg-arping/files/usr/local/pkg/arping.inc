<?php
/*
	arping.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2006 Scott Ullrich
	Copyright (C) 2009-2015 ESF, LLC
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

function arping_package_php_command() {
	require_once("util.inc");
	
	$button = "<br /><br /><input class=\"formbtn\" type=\"button\" value=\"Back to ARPing\" onclick=\"history.back()\" />";
	$int_count = (is_numeric($_POST['count']) ? (abs(intval($_POST['count']))) : 3);
	$arping_options = " -v -c {$int_count}";
	if (!empty($_POST['interface'])) {
		$iface = convert_friendly_interface_to_real_interface_name($_POST['interface']);
		$arping_options .= " -i {$iface}";
	}
	if (is_ipaddr($_POST['pinghost']) || is_hostname($_POST['pinghost']) || is_macaddr($_POST['pinghost'])) {
		$arping_options .= " " . $_POST['pinghost'];
		echo "<strong>arping {$arping_options} output:</strong><br /><br />";
		system("/usr/local/sbin/arping {$arping_options}");
		echo "{$button}";
	} else {
		echo "<div class=\"errmsg\">ERROR: Invalid input. Supplied address must be a valid hostname, IP or MAC address.</div>";
		echo "{$button}";
	}
	exit;
}
?>
