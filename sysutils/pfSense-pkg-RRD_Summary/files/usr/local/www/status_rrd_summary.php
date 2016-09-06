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

$rrds = glob("/var/db/rrd/*-traffic.rrd");
$startday = isset($_POST['startday']) ? $_POST['startday'] : "01";
$rrd = isset($_POST['rrd']) ? $_POST['rrd'] : "wan-traffic.rrd";

$start = "00 " . date("m/{$startday}/Y");
$lastmonthstart = "00 " . date("m/d/Y", strtotime("-1 month", strtotime(date("m/{$startday}/Y"))));
$lastmonthend = "00 " . date("m/d/Y", strtotime("-1 second", strtotime(date("m/{$startday}/Y"))));

$thismonth = fetch_rrd_summary($rrd, $start, "now");
$lastmonth = fetch_rrd_summary($rrd, $lastmonthstart, $lastmonthend, 24*60*60);

function fetch_rrd_summary($rrd, $start, $end, $resolution=60*60) {
	$traffic = array();
	$rrd = escapeshellarg("/var/db/rrd/{$rrd}");
	$start = escapeshellarg($start);
	$end = escapeshellarg($end);
	exec("/usr/local/bin/rrdtool fetch {$rrd} AVERAGE -r {$resolution} -s {$start} -e {$end} | grep -v nan | awk '{ sum1 += $2/(1024*1024) + $4/(1024*1024) + $6/(1024*1024) + $8/(1024*1024); sum2 += $3/(1024*1024) + $5/(1024*1024) + $7/(1024*1024) + $9/(1024*1024); } END { printf \"%u|%u\", sum1*{$resolution}, sum2*{$resolution}; }'", $traffic);
	return explode('|', trim($traffic[0]));
}

function print_rrd_summary_table($data) { ?>
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed" id="rrdsummary">
				<thead>
					<tr><th>Direction</th><th>Bandwidth</th></tr>
				</thead>
				<tbody>
					<tr><td>In</td><td align="right"><?=$data[0]; ?> MBytes</td></tr>
					<tr><td>Out</td><td align="right"><?=$data[1]; ?> MBytes</td></tr>
					<tr><td>Total</td><td align="right"><?=$data[0] + $data[1]; ?> MBytes</td></tr>
				</tbody>
			</table>
		</div>
<?php
}

$pgtitle = array("Status", "RRD Summary");

include_once("head.inc");

$form = new Form(false);

$section = new Form_Section('Select RRD Parameters');

$rrd_options = array();
foreach ($rrds as $r) {
	$r = basename($r);
	$rrd_options[$r] = $r;
}

$section->addInput(new Form_Select(
	'rrd',
	'RRD File',
	$rrd,
	$rrd_options
));

$section->addInput(new Form_Select(
	'startday',
	'Start Day',
	$startday,
	array_combine(range(1, 28, 1), range(1, 28, 1))
));

$form->add($section);

print($form);

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("RRD Summary")?></h2></div>
	<div class="panel-body">
		<strong>This Month (to date, does not include this hour, starting at day <?=$startday; ?>):</strong>
		<?php print_rrd_summary_table($thismonth); ?>
		<br/>
		<strong>Last Month:</strong>
		<?php print_rrd_summary_table($lastmonth); ?>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function(){
	$('#rrd, #startday').on('change', function(){
		$(this).parents('form').submit();
	});
});
//]]>
</script>
<?php include_once("foot.inc"); ?>
