<?php
/*
 * status_rrd_summary.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2010-2015 Rubicon Communications, LLC (Netgate)
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
require_once("guiconfig.inc");

$rrds = glob("/var/db/rrd/*-traffic.rrd");
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
