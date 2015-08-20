<?php
/*
	rrd_summary.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2010 Jim Pingle
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
require_once("guiconfig.inc");

$startday = isset($_POST['startday']) ? $_POST['startday'] : "01";
$rrd = isset($_POST['rrd']) ? $_POST['rrd'] : "wan-traffic.rrd";

$start = "00 " . date("m/{$startday}/Y");
$lastmonth = "00 " . date("m/{$startday}/Y", strtotime("-1 month", strtotime(date("m/{$startday}/Y"))));

$thismonth = fetch_rrd_summary($rrd, $start, "now");
$lastmonth = fetch_rrd_summary($rrd, $lastmonth, $start, 720*60);

function fetch_rrd_summary($rrd, $start, $end, $resolution=3600) {
	$traffic = array();
	$rrd = escapeshellarg("/var/db/rrd/{$rrd}");
	$start = escapeshellarg($start);
	$end = escapeshellarg($end);
	exec("/usr/local/bin/rrdtool fetch {$rrd} AVERAGE -r {$resolution} -s {$start} -e {$end} | grep -v nan | awk '{ sum1 += $2/(1024*1024); sum2 += $3/(1024*1024) } END { printf \"%u|%u\", sum1*{$resolution}, sum2*{$resolution}; }'", $traffic);
	return explode('|', trim($traffic[0]));
}

function print_rrd_summary_table($data) { ?>
<table cellspacing="5">
	<tr><th>&nbsp;</th><th>Bandwidth</th></tr>
	<tr><td>In</td><td align="right"><?php echo $data[0]; ?> MBytes</td></tr>
	<tr><td>Out</td><td align="right"><?php echo $data[1]; ?> MBytes</td></tr>
	<tr><td>Total</td><td align="right"><?php echo $data[0] + $data[1]; ?> MBytes</td></tr>
</table>
<?php
}

$pgtitle = "Status: RRD Summary";
include_once("head.inc");
echo "<body link=\"#0000CC\" vlink=\"#0000CC\" alink=\"#0000CC\">";
include_once("fbegin.inc");

$rrds = glob("/var/db/rrd/*-traffic.rrd");

?>
<form name="form1" action="status_rrd_summary.php" method="post">
	RRD Database:&nbsp;
	<select name="rrd" class="formselect" onchange="document.form1.submit()">
	<?php
	foreach ($rrds as $r) {
		$r = basename($r);
		$selected = ($r == $rrd) ? ' selected="selected"' : '';
		print "<option value=\"{$r}\"{$selected}>{$r}</option>";
	} ?>
	</select>
	Start Day:
	<select name="startday" class="formselect" onchange="document.form1.submit()">
	<?php
	for ($day=1; $day < 29; $day++) {
		$selected = ($day == $startday) ? ' selected="selected"' : "";
		print "<option value=\"{$day}\"{$selected}>{$day}</option>";
	} ?>
	</select>
</form>
<br/>
This Month (to date, does not include this hour, starting at day <?php echo $startday; ?>):
<?php print_rrd_summary_table($thismonth); ?>
<br/><br/>
Last Month:
<?php print_rrd_summary_table($lastmonth); ?>

<?php include_once("fend.inc"); ?>
</body>
</html>
