<?php
/*
	status_tinc.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2012-2015 ESF, LLC
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
require("guiconfig.inc");

function tinc_status_usr1() {
	exec("/usr/local/sbin/tincd --config=/usr/local/etc/tinc -kUSR1");
	usleep(500000);
	$clog_path = "/usr/local/sbin/clog";
	$result = array();

	exec("{$clog_path} /var/log/tinc.log | /usr/bin/sed -e 's/.*tinc\[.*\]: //'", $result);
	$i = 0;
	foreach ($result as $line) {
		if (preg_match("/Connections:/", $line)) {
			$begin = $i;
		}
		if (preg_match("/End of connections./", $line)) {
			$end = $i;
		}
		$i++;
	}
	$output = "";
	$i = 0;

	foreach ($result as $line) {
		if ($i >= $begin && $i<= $end) {
			$output .= "<tr class=\"text-nowrap\"><td>" . $line . "</td></tr>";
		}
		$i++;
	}
	return $output;

}

function tinc_status_usr2() {
	exec("/usr/local/sbin/tincd --config=/usr/local/etc/tinc -kUSR2");
	usleep(500000);
	$clog_path = "/usr/local/sbin/clog";
	$result = array();

	exec("{$clog_path} /var/log/tinc.log | sed -e 's/.*tinc\[.*\]: //'",$result);
	$i = 0;
	foreach ($result as $line) {
		if (preg_match("/Statistics for Generic BSD tun device/",$line)) {
			$begin = $i;
		}
		if (preg_match("/End of subnet list./",$line)) {
			$end = $i;
		}
		$i++;
	}
	$output="";

        foreach ($result as $line) {
                if ($i >= $begin && $i<= $end) {
			$output .= "<tr class=\"text-nowrap\"><td>" . $line . "</td></tr>";
                }
                $i++;
        }
        return $output;

}

$shortcut_section = "tinc";
$pgtitle = array(gettext("Status"), "tinc");
include("head.inc");
?>

<?php include("fbegin.inc"); ?>

<div class="panel panel-default">
        <div class="panel-heading"><h2 class="panel-title">Connection list</h2></div>
        <div class="panel-body table-responsive">
        <table class="table table-striped table-hover table-condensed">
        <tbody>
		<?php print tinc_status_usr1(); ?>
        </tbody>
        </table>
        </div>
</div>

<div class="panel panel-default">
        <div class="panel-heading"><h2 class="panel-title">Virtual network device statistics, all known nodes, edges and subnets</h2></div>
        <div class="panel-body table-responsive">
        <table class="table table-striped table-hover table-condensed">
        <tbody>
		<?php print tinc_status_usr2(); ?>
        </tbody>
        </table>
        </div>
</div>

<?php include("fend.inc"); ?>
