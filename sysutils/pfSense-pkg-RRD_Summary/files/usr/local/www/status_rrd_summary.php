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

// Using too small a divisor for traffic sums will likely exceed PHP_MAX_INT
$unitlist = array("MiB" => 2, "GiB" => 3, "TiB" => 4, "PiB" => 5);

// validate interface
$iflist = get_configured_interface_list();
$interface = filter_input(INPUT_POST, "interface", FILTER_SANITIZE_STRING);
$interface = in_array($interface, $iflist) ? $interface : "wan";

// get rrd bounds for interface
$rrd = "{$interface}-traffic.rrd";
$data = fetch_rrd_summary($rrd, "-10y", "now");
$firstyear = date("Y", ($data["first"] > 0) ? $data["first"] : time() );
$lastyear = date("Y", ($data["last"] > 0) ? $data["last"] : time() );

// validate year
$startyear = filter_input(INPUT_POST, "startyear", FILTER_VALIDATE_INT,
	array("options" => array("min_range" => 0, "max_range" => $lastyear)) );
$startyear = ($startyear >= $firstyear || $startyear == "0") ? $startyear : $lastyear;
// validate startday
$startday = filter_input(INPUT_POST, "startday", FILTER_VALIDATE_INT,
	array("options" => array("min_range" => 1, "max_range" => 28)) );
$startday = ($startday > 0) ? $startday : 1;
// validate units
$units = filter_input(INPUT_POST, "units", FILTER_VALIDATE_INT,
	array("options" => array("min_range" => min(array_values($unitlist)), "max_range" => max(array_values($unitlist)) )) );
$units = in_array($units, $unitlist) ? $units : $unitlist["GiB"];

// 1:timestamp 2:inpass 3:outpass 4:inblock 5:outblock 6:inpass6 7:outpass6 8:inblock6 9:outblock6
// total_in = $2 + $4 + $6 + $8; total_out = $3 + $7 (blocked outbound traffic is excluded)
function fetch_rrd_summary($rrd, $start, $end, $units=2, $resolution=24*60*60) {
	$traffic = array();
	$rrd = escapeshellarg("/var/db/rrd/{$rrd}");
	$start = escapeshellarg($start);
	$end = escapeshellarg($end);
	exec("/usr/local/bin/rrdtool fetch {$rrd} AVERAGE -r {$resolution} -s {$start} -e {$end}", $traffic);
	$divisor = 1024 ** $units;
	$t_keys = preg_split("/\s+/", $traffic[0]);	// grab keys
	//print "time=$t_first st=$start end=$end div=$divisor res=$resolution";
	$traffic = preg_grep("/^[0-9]+:/", $traffic);	// select data rows
	$traffic = preg_grep("/nan/", $traffic, PREG_GREP_INVERT);	// filter nan rows
	$data = array( "first" => time() );
	foreach ( $traffic as $t ) {
		$t = preg_split("/[\s:]+/", $t);
		if (count($t) != count($t_keys)) continue;	// error: field mismatch
		$data["first"] = min($t[0], $data["first"]);
		$data["last"] = max($t[0], $data["last"]);
		foreach ($t_keys as $i => $k) { $data[$k] += ($t[$i] / $divisor) * $resolution; }
	}
	// Adjust for resolution
	foreach ($t_keys as $k) {
		if (preg_match("/^outblock/", $k)) continue;
		$data["total_in"] += preg_match("/^in/", $k) ? $data[$k] : 0;
		$data["total_out"] += preg_match("/^out/", $k) ? $data[$k] : 0;
	}
	return $data;
}

function print_rrd_summary($rrd, $units, $startyear, $startday) {
	$data = fetch_rrd_summary($rrd, "-10y", "now");
	$first = $data["first"];
	$last = $data["last"];
	global $unitlist; $u = array_flip($unitlist);
	foreach (range(date("Y", $last), date("Y", $first), 1) as $year) {
		if ($startyear > 0 && $startyear != $year) continue;
		foreach (range(12, 1, -1) as $month) {
			$start = strtotime(date("{$month}/{$startday}/{$year}"));
			$end = strtotime("-1 second", strtotime("+1 month", $start));
			if ($start > $last || $end < $first) continue;
			if ($start < $first) $start = $first;
			if ($end > $last) $end = $last;
			$data = fetch_rrd_summary($rrd, "epoch+{$start}s", "epoch+{$end}s", $units, 24*60*60);
			?>
				<tr>
					<td><?=date("Y-m-d", $start); ?> to <?=date("m-d-Y", $end); ?></td>
					<td><?=sprintf("%0.2f %s", $data["total_in"], $u[$units]); ?></td>
					<td><?=sprintf("%0.2f %s", $data["total_out"], $u[$units]); ?></td>
					<td><?=sprintf("%0.2f %s", $data["total_in"] + $data["total_out"], $u[$units]); ?></td>
				</tr>
			<?php
	  }
	}
}

$pgtitle = array("Status", "RRD Summary");

include_once("head.inc");

$form = new Form(false);

$section = new Form_Section('Select RRD Parameters');

$if_options = array();
foreach ($iflist as $i) {
	$if_options[$i] = $i;
}

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$interface,
	$if_options
));

$section->addInput(new Form_Select(
	'units',
	'Units',
	$units,
	array_flip($unitlist)
));

$section->addInput(new Form_Select(
	'startyear',
	'Year',
	$startyear,
	array("0" => "All") + array_combine(range($lastyear, $firstyear, -1), range($lastyear, $firstyear, -1))
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
		<div class="table-responsive">
			<strong>Estimated monthly traffic for given time period beginning on day <?=$startday; ?> of each month</strong>
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable id="rrdsummary">
				<thead>
					<tr>
						<th data-sortable-type="alpha"><?=gettext("Period")?></th>
						<th data-sortable-type="numeric"><?=gettext("Avg Traffic In")?></th>
						<th data-sortable-type="numeric"><?=gettext("Avg Traffic Out")?></th>
						<th data-sortable-type="numeric"><?=gettext("Est Total Traffic")?></th>
					</tr>
				</thead>
				<tbody>
					<?php print_rrd_summary($rrd, $units, $startyear, $startday); ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function(){
	$('#interface, #units, #startyear, #startday').on('change', function(){
		$(this).parents('form').submit();
	});
});
//]]>
</script>
<?php include_once("foot.inc"); ?>
