<?php
/*
	rrd_fetch_json.php

	part of pfSense (https://www.pfsense.org)
	Copyright (c) 2008-2016 Electric Sheep Fencing, LLC. All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in
	   the documentation and/or other materials provided with the
	   distribution.

	3. All advertising materials mentioning features or use of this software
	   must display the following acknowledgment:
	   "This product includes software developed by the pfSense Project
	   for use in the pfSense® software distribution. (http://www.pfsense.org/).

	4. The names "pfSense" and "pfSense Project" must not be used to
	   endorse or promote products derived from this software without
	   prior written permission. For written permission, please contact
	   coreteam@pfsense.org.

	5. Products derived from this software may not be called "pfSense"
	   nor may "pfSense" appear in their names without prior written
	   permission of the Electric Sheep Fencing, LLC.

	6. Redistributions of any form whatsoever must retain the following
	   acknowledgment:

	"This product includes software developed by the pfSense Project
	for use in the pfSense software distribution (http://www.pfsense.org/).

	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
	OF THE POSSIBILITY OF SUCH DAMAGE.
*/

$nocsrf = true;

require("guiconfig.inc");

$rrd_location = "/var/db/rrd/";

//lookup end time based on resolution (ensure resolution interval)
$resolutionLookup = array(
	"60"    => "1min",
	"300"   => "5min",
	"3600"  => "1hour",
	"86400" => "1day"
);

//TODO security/validation checks
$left = $_POST['left'];
$right = $_POST['right'];
$start = $_POST['start'];
$end = $_POST['end'];
$timePeriod = $_POST['timePeriod'];
$resolution = $_POST['resolution'];
$graphtype = $_POST['graphtype'];
$invert_graph = ($_POST['invert'] === 'true');

//Figure out the type of information stored in RRD database
$left_pieces = explode("-", $left);
$right_pieces = explode("-", $right);

$rrd_info_array = rrd_info($rrd_location . $left . ".rrd");
$left_last_updated = $rrd_info_array['last_update'];

$rrd_info_array = rrd_info($rrd_location . $right . ".rrd");
$right_last_updated = $rrd_info_array['last_update'];

//grab the older last updated time of the two databases
if(empty($right_last_updated)) {

	$last_updated = $left_last_updated;

} elseif(empty($left_last_updated)) {

	$last_updated = $right_last_updated;

} else {

	$last_updated = min($left_last_updated, $right_last_updated);

}

if ($timePeriod === "custom") {
	// Determine highest resolution available for requested time period
	// Should be possible to determine programmaticly from the RRD header info array (rrd_info).
	$rrd_options = array( 'AVERAGE', '-a', '-s', $start, '-e', $start );
	$left_rrd_array  = rrd_fetch($rrd_location . $left  . ".rrd", $rrd_options);
	$right_rrd_array = rrd_fetch($rrd_location . $right . ".rrd", $rrd_options);
	$resolution = max($left_rrd_array['step'], $right_rrd_array['step']);

	// make sure end time isn't later than last updated time entry
	if( $end > $last_updated ) { $end = $last_updated; }

	// Minus resolution to prevent last value 0 (zero).
	$end -= $resolution;

	// make sure start time isn't later than end time
	if ($start > $end) { $start = $end; }
} else {
	// Use end time reference in 'start' to retain time period length.
	$start = 'end' . $timePeriod . '+'.$resolutionLookup[$resolution];
	// Use the RRD last updated time as end, minus resolution to prevent last value 0 (zero).
	$end = $last_updated . '-'.$resolutionLookup[$resolution];
}

$rrd_options = array( 'AVERAGE', '-a', '-r', $resolution, '-s', $start, '-e', $end );

//Initialze
$left_unit_acronym = $right_unit_acronym = "";

//Set units based on RRD database name
$graph_unit_lookup = array(
	"traffic"    => "b/s",
	"packets"    => "pps",
	"states"     => "cps",
	"quality"    => "ms",
	"processor"  => "%",
	"memory"     => "%",
	"wireless"   => "dBi",
	"mbuf"       => "",
	"dhcpd"      => "",
	"ntpd"       => "",
	"vpnusers"   => "",
	"queues"     => "b/s",
	"queuedrops" => "drops",
	"cellular"   => "dB"
);

$left_unit_acronym = $graph_unit_lookup[$left_pieces[1]];
$right_unit_acronym = $graph_unit_lookup[$right_pieces[1]];

//Overrides units based on line name
$line_unit_lookup = array(
	"Packet Loss" => "%",
	"Processes"   => ""
);

//lookup table for acronym to full description
$unit_desc_lookup = array(
	"b/s" => "Bits Per Second",
	"pps" => "Packets Per Second",
	"cps" => "Changes Per Second",
	"ms"  => "Milliseconds",
	"%"   => "Percent",
	"Mb"  => "Megabit",
	"dBi" => "Decibels Relative to Isotropic",
	""    => ""
);

//TODO make this a function for left and right
if ($left != "null") {

	$rrd_array = rrd_fetch($rrd_location . $left . ".rrd", $rrd_options);

	if (!($rrd_array)) {
		die ('{ "error" : "' . rrd_error() . '" }');
	}

	$ds_list = array_keys ($rrd_array['data']);
	$step = $rrd_array['step'];
	$ignored_left = 0;

	foreach ($ds_list as $ds_key_left => $ds) {

		$data_list = $rrd_array['data'][$ds];
		$ignore = $invert = $ninetyfifth = false;
		$graph_type = $graphtype;
		$unit_acronym = $left_unit_acronym;
		$multiplier = 1;
		$format = "f";

		//Overrides based on line name
		switch($ds) {
			case "user":
				$ds = "user util.";
				break;
			case "nice":
				$ds = "nice util.";
				break;
			case "system":
				$ds = "system util.";
				break;
			case "stddev":
				$ds = "delay std. dev.";
				$multiplier = 1000;
				break;
			case "delay":
				$ds = "delay average";
				$multiplier = 1000;
				break;
			case "loss":
				$ds = "packet loss";
				$unit_acronym = "%";
				$invert = $invert_graph;
				break;
			case "processes":
				$unit_acronym = "";
				break;
			case "pfstates":
				$unit_acronym = "";
				$ds = "filter states";
				break;
			case "srcip":
				$unit_acronym = "";
				$ds = "source addr.";
				break;
			case "dstip":
				$unit_acronym = "";
				$ds = "dest. addr.";
				break;
			case "pfrate":
				$ds = "state changes";
				break;
			case "pfnat":
				$ignored_left++;
				$ignore = true;
				break;
			case "inpass":
				$ninetyfifth = true;
				$multiplier = 8;
				$format = "s";
				break;
			case "max":
				$format = "s";
				break;
			case "inpass6":
				$ninetyfifth = true;
				$multiplier = 8;
				$format = "s";
				break;
			case "outpass":
				$invert = $invert_graph;
				$ninetyfifth = true;
				$multiplier = 8;
				$format = "s";
				break;
			case "outpass6":
				$invert = $invert_graph;
				$ninetyfifth = true;
				$multiplier = 8;
				$format = "s";
				break;
			case "rate":
				$unit_acronym = "Mb";
				break;
			case "channel":
				$unit_acronym = "";
				break;
			case "concurrentusers":
				$unit_acronym = "";
				break;
			case "loggedinusers":
				$unit_acronym = "";
				break;
			case "offset":
			case "sjit":
			case "cjit":
			case "wander":
			case "disp":
				$unit_acronym = "ms";
				break;
			case "freq":
				$unit_acronym = "";
				break;
		}

		if (!$ignore) {
			$ds_key_left_adjusted = $ds_key_left - $ignored_left;

			$obj[$ds_key_left_adjusted]['key'] = $ds;
			$obj[$ds_key_left_adjusted]['step'] = $step;
			$obj[$ds_key_left_adjusted]['last_updated'] = $last_updated*1000;
			$obj[$ds_key_left_adjusted]['type'] = $graph_type;
			$obj[$ds_key_left_adjusted]['format'] = $format;
			$obj[$ds_key_left_adjusted]['yAxis'] = 1;
			$obj[$ds_key_left_adjusted]['unit_acronym'] = $unit_acronym;
			$obj[$ds_key_left_adjusted]['unit_desc'] = $unit_desc_lookup[$unit_acronym];
			$obj[$ds_key_left_adjusted]['invert'] = $invert;
			$obj[$ds_key_left_adjusted]['ninetyfifth'] = $ninetyfifth;

			$data = array();
			$raw_data = array();
			$stats = array();

			foreach ($data_list as $time => $value) {

				$raw_data[] = array($time*1000, $value*$multiplier);

				if(is_nan($value)) {

					$data[] = array($time*1000, 0);

				} else {

					$data[] = array($time*1000, $value*$multiplier);
					$stats[] = $value*$multiplier;

				}
				
			}

			$obj[$ds_key_left_adjusted]['values'] = $data;
			$obj[$ds_key_left_adjusted]['raw'] = $raw_data;

			if(count($stats)) {

				$obj[$ds_key_left_adjusted]['min'] = min($stats);
				$obj[$ds_key_left_adjusted]['max'] = max($stats);
				$obj[$ds_key_left_adjusted]['avg'] = array_sum($stats) / count($stats);

			} else {

				$obj[$ds_key_left_adjusted]['min'] = 0;
				$obj[$ds_key_left_adjusted]['max'] = 0;
				$obj[$ds_key_left_adjusted]['avg'] = 0;

			}

		}
	}

	/* calulate the total lines */
	if ( ($left_pieces[1] === "traffic") || ($left_pieces[1] === "packets") ) {

		foreach ($obj as $key => $value) {

			//grab inpass and outpass attributes and values
			if ($value['key'] === "inpass") {

				$inpass_array = array();

				//loop through values and use time
				foreach ($value['raw'] as $datapoint) {

					$inpass_array[$datapoint[0]/1000] = $datapoint[1]; //divide by thousand to avoid key size limitations

				}

			}

			if ($value['key'] === "inpass6") {

				$inpass6_array = [];

				//loop through values and use time
				foreach ($value['raw'] as $datapoint6) {

					$inpass6_array[$datapoint6[0]/1000] = $datapoint6[1]; //divide by thousand to avoid key size limitations

				}

			}

			if ($value['key'] === "outpass") {

				$outpass_array = [];

				//loop through values and use time
				foreach ($value['raw'] as $datapoint) {

					$outpass_array[$datapoint[0]/1000] = $datapoint[1]; //divide by thousand to avoid key size limitations

				}

			}

			if ($value['key'] === "outpass6") {

				$outpass6_array = [];

				//loop through values and use time
				foreach ($value['raw'] as $datapoint6) {

					$outpass6_array[$datapoint6[0]/1000] = $datapoint6[1]; //divide by thousand to avoid key size limitations

				}

			}

		}

		/* add v4 and v6 together */
		$inpass_total = [];
		$outpass_total = [];
		$inpass_stats = [];
		$outpass_stats = [];

		foreach ($inpass_array as $key => $value) {

			if(is_nan($value)) {

				$inpass_total[] = array($key*1000, 0);

			} else {

				$inpass_total[] = array($key*1000, $value + $inpass6_array[$key]);
				$inpass_stats[] = $value + $inpass6_array[$key];

			}

		}

		foreach ($outpass_array as $key => $value) {

			if(is_nan($value)) {

				$outpass_total[] = array($key*1000, 0);

			} else {

				$outpass_total[] = array($key*1000, $value + $outpass6_array[$key]);
				$outpass_stats[] = $value + $outpass6_array[$key];

			}

		}

		$ds_key_left_adjusted += 1;

		//add the new total lines to array
		$obj[$ds_key_left_adjusted]['key'] = "inpass total";
		$obj[$ds_key_left_adjusted]['type'] = $graphtype;
		$obj[$ds_key_left_adjusted]['format'] = "s";
		$obj[$ds_key_left_adjusted]['yAxis'] = 1;
		$obj[$ds_key_left_adjusted]['unit_acronym'] = $left_unit_acronym;
		$obj[$ds_key_left_adjusted]['unit_desc'] = $unit_desc_lookup[$left_unit_acronym];
		$obj[$ds_key_left_adjusted]['invert'] = false;
		$obj[$ds_key_left_adjusted]['ninetyfifth'] = true;
		$obj[$ds_key_left_adjusted]['min'] = min($inpass_stats);
		$obj[$ds_key_left_adjusted]['max'] = max($inpass_stats);
		$obj[$ds_key_left_adjusted]['avg'] = array_sum($inpass_stats) / count($inpass_stats);
		$obj[$ds_key_left_adjusted]['values'] = $inpass_total;

		$ds_key_left_adjusted += 1;

		$obj[$ds_key_left_adjusted]['key'] = "outpass total";
		$obj[$ds_key_left_adjusted]['type'] = $graphtype;
		$obj[$ds_key_left_adjusted]['format'] = "s";
		$obj[$ds_key_left_adjusted]['yAxis'] = 1;
		$obj[$ds_key_left_adjusted]['unit_acronym'] = $left_unit_acronym;
		$obj[$ds_key_left_adjusted]['unit_desc'] = $unit_desc_lookup[$left_unit_acronym];
		$obj[$ds_key_left_adjusted]['invert'] = $invert_graph;
		$obj[$ds_key_left_adjusted]['ninetyfifth'] = true;
		$obj[$ds_key_left_adjusted]['min'] = min($outpass_stats);
		$obj[$ds_key_left_adjusted]['max'] = max($outpass_stats);
		$obj[$ds_key_left_adjusted]['avg'] = array_sum($outpass_stats) / count($outpass_stats);
		$obj[$ds_key_left_adjusted]['values'] = $outpass_total;

	}

	foreach ($obj as $raw_left_key => &$raw_left_value) {
		unset($raw_left_value['raw']);
	}

}

if ($right != "null") {

	$rrd_array = rrd_fetch($rrd_location . $right . ".rrd", $rrd_options);

	if (!($rrd_array)) {
		die ('{ "error" : "' . rrd_error() . '" }');
	}

	$ds_list = array_keys ($rrd_array['data']);
	$step = $rrd_array['step'];
	$ignored_right = 0;

	foreach ($ds_list as $ds_key_right => $ds) {
		$last_left_key = 0;

		if ($left != "null") {
			//TODO make sure subtracting ignored_left is correct
			$last_left_key = 1 + $ds_key_left_adjusted;
		}

		$data_list = $rrd_array['data'][$ds];
		$ignore = $invert = $ninetyfifth = false;
		$graph_type = $graphtype;
		$unit_acronym = $right_unit_acronym;
		$multiplier = 1;
		$format = "f";

		//Override acronym based on line name
		switch($ds) {
			case "user":
				$ds = "user util.";
				break;
			case "nice":
				$ds = "nice util.";
				break;
			case "system":
				$ds = "system util.";
				break;
			case "stddev":
				$ds = "delay std. dev.";
				$multiplier = 1000;
				break;
			case "delay":
				$ds = "delay average";
				$multiplier = 1000;
				break;
			case "loss":
				$ds = "packet loss";
				$unit_acronym = "%";
				$invert = $invert_graph;
				break;
			case "processes":
				$unit_acronym = "";
				break;
			case "pfstates":
				$unit_acronym = "";
				$ds = "filter states";
				break;
			case "srcip":
				$unit_acronym = "";
				$ds = "source addr.";
				break;
			case "dstip":
				$unit_acronym = "";
				$ds = "dest. addr.";
				break;
			case "pfrate":
				$ds = "state changes";
				break;
			case "pfnat":
				$ignored_right++;
				$ignore = true;
				break;
			case "inpass":
				$ninetyfifth = true;
				$multiplier = 8;
				$format = "s";
				break;
			case "max":
				$format = "s";
				break;
			case "inpass6":
				$ninetyfifth = true;
				$multiplier = 8;
				$format = "s";
				break;
			case "outpass":
				$invert = $invert_graph;
				$ninetyfifth = true;
				$multiplier = 8;
				$format = "s";
				break;
			case "outpass6":
				$invert = $invert_graph;
				$ninetyfifth = true;
				$multiplier = 8;
				$format = "s";
				break;
			case "rate":
				$unit_acronym = "Mb";
				break;
			case "channel":
				$unit_acronym = "";
				break;
			case "concurrentusers":
				$unit_acronym = "";
				break;
			case "loggedinusers":
				$unit_acronym = "";
				break;
			case "offset":
			case "sjit":
			case "cjit":
			case "wander":
			case "disp":
				$unit_acronym = "ms";
				break;
			case "freq":
				$unit_acronym = "";
				break;
		}

		if (!$ignore) {
			$ds_key_right_adjusted = $last_left_key + $ds_key_right - $ignored_right;

			$obj[$ds_key_right_adjusted]['key'] = $ds;
			$obj[$ds_key_right_adjusted]['step'] = $step;
			$obj[$ds_key_right_adjusted]['last_updated'] = $last_updated*1000;
			$obj[$ds_key_right_adjusted]['type'] = $graph_type;
			$obj[$ds_key_right_adjusted]['format'] = $format;
			$obj[$ds_key_right_adjusted]['yAxis'] = 2;
			$obj[$ds_key_right_adjusted]['unit_acronym'] = $unit_acronym;
			$obj[$ds_key_right_adjusted]['unit_desc'] = $unit_desc_lookup[$unit_acronym];
			$obj[$ds_key_right_adjusted]['invert'] = $invert;
			$obj[$ds_key_right_adjusted]['ninetyfifth'] = $ninetyfifth;

			$raw_data = array();
			$data = array();
			$stats = array();

			foreach ($data_list as $time => $value) {

				$raw_data[] = array($time*1000, $value*$multiplier);

				if(is_nan($value)) {

					$data[] = array($time*1000, 0);

				} else {

					$data[] = array($time*1000, $value*$multiplier);
					$stats[] = $value*$multiplier;

				}
				
			}

			$obj[$ds_key_right_adjusted]['values'] = $data;
			$obj[$ds_key_right_adjusted]['raw'] = $raw_data;

			if(count($stats)) {

				$obj[$ds_key_right_adjusted]['min'] = min($stats);
				$obj[$ds_key_right_adjusted]['max'] = max($stats);
				$obj[$ds_key_right_adjusted]['avg'] = array_sum($stats) / count($stats);

			} else {

				$obj[$ds_key_right_adjusted]['min'] = 0;
				$obj[$ds_key_right_adjusted]['max'] = 0;
				$obj[$ds_key_right_adjusted]['avg'] = 0;

			}

		}

	}

	/* calculate the total lines */
	if ( ($right_pieces[1] === "traffic") || ($right_pieces[1] === "packets") ) {

		foreach ($obj as $key => $value) {

			//grab inpass and outpass attributes and values
			if ($value['key'] === "inpass" && $value['yAxis'] === 2) {

				$inpass_array = [];

				//loop through values and use time
				foreach ($value['raw'] as $datapoint) {

					$inpass_array[$datapoint[0]/1000] = $datapoint[1]; //divide by thousand to avoid key size limitations

				}

			}

			if ($value['key'] === "inpass6" && $value['yAxis'] === 2) {

				$inpass6_array = [];

				//loop through values and use time
				foreach ($value['raw'] as $datapoint6) {

					$inpass6_array[$datapoint6[0]/1000] = $datapoint6[1]; //divide by thousand to avoid key size limitations

				}
			}

			if ($value['key'] === "outpass" && $value['yAxis'] === 2) {

				$outpass_array = [];

				//loop through values and use time
				foreach ($value['raw'] as $datapoint) {

					$outpass_array[$datapoint[0]/1000] = $datapoint[1]; //divide by thousand to avoid key size limitations

				}
			}

			if ($value['key'] === "outpass6" && $value['yAxis'] === 2) {

				$outpass6_array = [];

				//loop through values and use time
				foreach ($value['raw'] as $datapoint6) {

					$outpass6_array[$datapoint6[0]/1000] = $datapoint6[1]; //divide by thousand to avoid key size limitations

				}
			}
		}

		/* add v4 and v6 together */
		$inpass_total = [];
		$outpass_total = [];
		$inpass_stats = [];
		$outpass_stats = [];

		foreach ($inpass_array as $key => $value) {

			if(is_nan($value)) {

				$inpass_total[] = array($key*1000, 0);

			} else {

				$inpass_total[] = array($key*1000, $value + $inpass6_array[$key]);
				$inpass_stats[] = $value + $inpass6_array[$key];

			}

		}

		
		foreach ($outpass_array as $key => $value) {

			if(is_nan($value)) {

				$outpass_total[] = array($key*1000, 0);

			} else {

				$outpass_total[] = array($key*1000, $value + $outpass6_array[$key]);
				$outpass_stats[] = $value + $outpass6_array[$key];

			}

		}

		$ds_key_right_adjusted += 1;

		//add the new total lines to array
		$obj[$ds_key_right_adjusted]['key'] = "inpass total";
		$obj[$ds_key_right_adjusted]['type'] = $graphtype;
		$obj[$ds_key_right_adjusted]['format'] = "s";
		$obj[$ds_key_right_adjusted]['yAxis'] = 2;
		$obj[$ds_key_right_adjusted]['unit_acronym'] = $right_unit_acronym;
		$obj[$ds_key_right_adjusted]['unit_desc'] = $unit_desc_lookup[$right_unit_acronym];
		$obj[$ds_key_right_adjusted]['invert'] = false;
		$obj[$ds_key_right_adjusted]['ninetyfifth'] = true;
		$obj[$ds_key_right_adjusted]['min'] = min($inpass_stats);
		$obj[$ds_key_right_adjusted]['max'] = max($inpass_stats);
		$obj[$ds_key_right_adjusted]['avg'] = array_sum($inpass_stats) / count($inpass_stats);
		$obj[$ds_key_right_adjusted]['values'] = $inpass_total;

		$ds_key_right_adjusted += 1;

		$obj[$ds_key_right_adjusted]['key'] = "outpass total";
		$obj[$ds_key_right_adjusted]['type'] = $graphtype;
		$obj[$ds_key_right_adjusted]['format'] = "s";
		$obj[$ds_key_right_adjusted]['yAxis'] = 2;
		$obj[$ds_key_right_adjusted]['unit_acronym'] = $right_unit_acronym;
		$obj[$ds_key_right_adjusted]['unit_desc'] = $unit_desc_lookup[$right_unit_acronym];
		$obj[$ds_key_right_adjusted]['invert'] = $invert_graph;
		$obj[$ds_key_right_adjusted]['ninetyfifth'] = true;
		$obj[$ds_key_right_adjusted]['min'] = min($outpass_stats);
		$obj[$ds_key_right_adjusted]['max'] = max($outpass_stats);
		$obj[$ds_key_right_adjusted]['avg'] = array_sum($outpass_stats) / count($outpass_stats);
		$obj[$ds_key_right_adjusted]['values'] = $outpass_total;

	}

	foreach ($obj as $raw_right_key => &$raw_right_value) {
		unset($raw_right_value['raw']);
	}

}

header('Content-Type: application/json');
echo json_encode($obj,JSON_PRETTY_PRINT|JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_NUMERIC_CHECK);

?>
