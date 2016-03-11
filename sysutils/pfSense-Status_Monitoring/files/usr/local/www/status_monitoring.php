<?php
/*
	status_monitoring.php

	part of pfSense (https://www.pfsense.org)
	Copyright (c) 2005 Bill Marquette
	Copyright (c) 2006 Peter Allgeyer
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

##|+PRIV
###|*IDENT=page-status-monitoring
###|*NAME=WebCfg - Status: Monitoring
###|*DESCR=Allow access to monitoring status page.
###|*MATCH=status_monitoring.php*
###|*MATCH=rrd_fetch_json.php*
###|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require("shaper.inc");

unset($input_errors);

/* if the rrd graphs are not enabled redirect to settings page
if (!isset($config['rrd']['enable'])) {
	//TODO handle this scenario without settings page
	header("Location: status_rrd_graph_settings.php"); //TODO make settings page
}
*/

//grab rrd filenames
$home = getcwd();
$rrddbpath = "/var/db/rrd/";
chdir($rrddbpath);
$databases = glob("*.rrd");
chdir($home);

$system = $packets = $quality = $traffic = $captiveportal = $ntpd = $queues = $queuedrops = $dhcpd = $vpnusers = [];

//populate arrays for dropdowns based on rrd filenames
foreach ($databases as $db) {

	$db_name = substr($db, 0, -4);
	$db_arr = explode("-", $db_name);

	if ($db_arr[0] === "system") {

		switch($db_arr[1]) {
			case "states":
				$system[$db_name] = "States";
				break;
			case "memory":
				$system[$db_name] = "Memory";
				break;
			case "processor":
				$system[$db_name] = "Processor";
				break;
			case "mbuf":
				$system[$db_name] = "Mbuf Clusters";
				break;
			default:
				$system[$db_name] = $db_arr[1];
				break;
		}

	}

	if ($db_arr[1] === "traffic") {

		$friendly = convert_friendly_interface_to_friendly_descr($db_arr[0]);

		if (empty($friendly)) {
			if(substr($db_arr[0], 0, 5) === "ovpns") {
				
				if (is_array($config['openvpn']["openvpn-server"])) {

					foreach ($config['openvpn']["openvpn-server"] as $id => $setting) {

						if($config['openvpn']["openvpn-server"][$id]['vpnid'] === substr($db_arr[0],5)) {
							$friendly = "OpenVPN Server: " . htmlspecialchars($config['openvpn']["openvpn-server"][$id]['description']);
						}

					}

				}

				if (empty($friendly)) { $friendly = "OpenVPN Server: " . $db_arr[0]; }

			} else {
				$friendly = $db_arr[0];
			}
		}

		$traffic[$db_name] = $friendly;

	}

	if ($db_arr[1] === "packets") {

		$friendly = convert_friendly_interface_to_friendly_descr($db_arr[0]);

		if (empty($friendly)) {
			if(substr($db_arr[0], 0, 5) === "ovpns") {

				if (is_array($config['openvpn']["openvpn-server"])) {
				
					foreach ($config['openvpn']["openvpn-server"] as $id => $setting) {

						if($config['openvpn']["openvpn-server"][$id]['vpnid'] === substr($db_arr[0],5)) {
							$friendly = "OpenVPN Server: " . htmlspecialchars($config['openvpn']["openvpn-server"][$id]['description']);
						}

					}

				}

				if (empty($friendly)) { $friendly = "OpenVPN Server: " . $db_arr[0]; }

			} else {
				$friendly = $db_arr[0];
			}
		}

		$packets[$db_name] = $friendly;

	}

	if ($db_arr[1] === "quality") {
		$quality[$db_name] = $db_arr[0];
	}

	if ($db_arr[1] === "queues") {
		$queues[$db_name] = convert_friendly_interface_to_friendly_descr($db_arr[0]);
	}

	if ($db_arr[1] === "queuedrops") {
		$queuedrops[$db_name] = convert_friendly_interface_to_friendly_descr($db_arr[0]);
	}

	if ($db_arr[0] === "captiveportal") {
		$captiveportal[$db_name] = $db_arr[1] . "-" . $db_arr[2]; //TODO make $db_arr[2] pretty
	}

	if ($db_arr[0] === "ntpd") {
		$ntpd[$db_name] = "NTP";
	}

	if ($db_arr[1] === "dhcpd") {
		$dhcpd[$db_name] = convert_friendly_interface_to_friendly_descr($db_arr[0]);
	}

	if ($db_arr[1] === "vpnusers") {

		$friendly = convert_friendly_interface_to_friendly_descr($db_arr[0]);

		if (empty($friendly)) {
			if(substr($db_arr[0], 0, 5) === "ovpns") {
				
				if (is_array($config['openvpn']["openvpn-server"])) {

					foreach ($config['openvpn']["openvpn-server"] as $id => $setting) {

						if($config['openvpn']["openvpn-server"][$id]['vpnid'] === substr($db_arr[0],5)) {
							$friendly = "OpenVPN Server: " . htmlspecialchars($config['openvpn']["openvpn-server"][$id]['description']);
						}

					}

				}

				if (empty($friendly)) { $friendly = "OpenVPN Server: " . $db_arr[0]; }

			} else {
				$friendly = $db_arr[0];
			}
		}

		$vpnusers[$db_name] = $friendly;
	}

}

$pgtitle = array(gettext("Status"), gettext("Monitoring"));

include("head.inc");

?>

<script src="/vendor/d3/d3.min.js"></script>
<script src="/vendor/nvd3/nv.d3.js"></script>

<link href="/vendor/nvd3/nv.d3.css" media="screen, projection" rel="stylesheet" type="text/css">

<form class="form-horizontal auto-submit" method="post" action="/status_graph.php?if=wan"><input type="hidden" name="__csrf_magic" value="sid:1f9306cd710f4110c117a7c9d792153199338441,1456357903">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">Settings</h2>
		</div>
		<div class="panel-body">
			<div class="form-group">
				<label class="col-sm-2 control-label">
					Left Axis
				</label>
				<div class="col-sm-5">
					<select class="form-control" id="category-left" name="category-left">
						<option value="system" selected>System</option>
						<option value="traffic">Traffic</option>
						<option value="packets">Packets</option>
						<option value="quality">Quality</option>
						<?php
						if(!empty($captiveportal)) {
							echo '<option value="captiveportal">Captive Portal</option>';
						}
						if(!empty($ntpd)) {
							echo '<option value="ntpd">NTP</option>';
						}
						if(!empty($queues)) {
							echo '<option value="queues">Queues</option>';
						}
						if(!empty($queuedrops)) {
							echo '<option value="queuedrops">Queuedrops</option>';
						}
						if(!empty($dhcpd)) {
							echo '<option value="dhcpd">DHCP</option>';
						}
						if(!empty($vpnusers)) {
							echo '<option value="vpnusers">VPN Users</option>';
						}
						?>
						<option value="none">None</option>
					</select>

					<span class="help-block">Category</span>
				</div>
				<div class="col-sm-5">
					<select class="form-control" id="graph-left" name="graph-left">
						<option value="system-states">States</option>
						<option value="system-processor" selected>Processes</option>
						<option value="system-memory">Memory</option>
						<option value="system-mbuf">Mbuf Clusters</option>
					</select>

					<span class="help-block">Graph</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">
					Right Axis
				</label>
				<div class="col-sm-5">
					<select class="form-control" id="category-right" name="category-right">
						<option value="system">System</option>
						<option value="traffic">Traffic</option>
						<option value="packets">Packets</option>
						<option value="quality">Quality</option>
						<?php
						if(!empty($captiveportal)) {
							echo '<option value="captiveportal">Captive Portal</option>';
						}
						if(!empty($ntpd)) {
							echo '<option value="ntpd">NTP</option>';
						}
						if(!empty($queues)) {
							echo '<option value="queues">Queues</option>';
						}
						if(!empty($queuedrops)) {
							echo '<option value="queuedrops">Queuedrops</option>';
						}
						if(!empty($dhcpd)) {
							echo '<option value="dhcpd">DHCP</option>';
						}
						if(!empty($vpnusers)) {
							echo '<option value="vpnusers">VPN Users</option>';
						}
						?>
						<option value="none" selected>None</option>
					</select>

					<span class="help-block">Category</span>
				</div>
				<div class="col-sm-5">
					<select class="form-control" id="graph-right" name="graph-right" disabled>
					</select>

					<span class="help-block">Graph</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">
					Options
				</label>
				<div class="col-sm-2">
					<select class="form-control" id="time-period" name="time-period">
						<option value="-4y">4 Years</option>
						<option value="-1y">1 Year</option>
						<option value="-3m">3 Months</option>
						<option value="-1m">1 Month</option>
						<option value="-1w">1 Week</option>
						<option value="-2d">2 Days</option>
						<option value="-1d" selected>1 Day</option>
						<option value="-8h">8 Hours</option>
						<option value="-1h">1 Hour</option>
					</select>

					<span class="help-block">Time Period</span>
				</div>
				<div class="col-sm-2">
					<select class="form-control" id="resolution" name="resolution">
						<option value="86400">1 Day</option>
						<option value="3600">1 Hour</option>
						<option value="300" selected>5 Minutes</option>
						<option value="60" disabled>1 Minute</option>
					</select>

					<span class="help-block">Resolution</span>
				</div>
				<div class="col-sm-2">
					<select class="form-control" id="graph-type" name="graph-type">
						<option value="line" selected>Line</option>
					</select>

					<span class="help-block">Graph Type (Disabled)</span>
				</div>
				<div class="col-sm-2">
					<select class="form-control" id="invert" name="invert">
						<option value="true" selected>On</option>
						<option value="false">Off</option>
					</select>

					<span class="help-block">Inverse</span>
				</div>
			</div>
		</div>
	</div>
</form>

<p>
	<button id="update" class="btn btn-primary">Update</button>
	<span id="loading-msg">Loading Graph...</span>
</p>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">Interactive Graph</h2>
	</div>
	<div class="panel-body">
		<div id="chart-error" class="alert alert-danger" style="display: none;"></div>
		<div id="chart" class="with-3d-shadow with-transitions">
			<svg></svg> <!-- TODO add loading symbol -->
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">Data Summary</h2>
	</div>
	<div class="panel-body">
		<div class="table-responsive">
			<table id="summary" class="table table-striped table-hover">
				<thead>
					<tr>
						<th></th>
						<th>Minimum</th>
						<th>Average</th>
						<th>Maximum</th>
						<th>Last</th>
						<th>95th Percentile</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>
</div>

<div class="infoblock">
	<div class="alert alert-info clearfix" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<div class="pull-left">
			<p>This tool allows you to compare RRD databases on two different Y axes.</p>

			<p>You can click on the line labels in the legend to toggle that lines visability. A single click toggles it's visability and a double click hides all the other lines, except for that one.</p>
		</div>
	</div>
</div>

<script type="text/javascript">

//<![CDATA[
events.push(function() {

	//lookup axis labels based on graph name
	var rrdLookup = {
		"states": "states, ip",
		"throughput": "bits / sec",
		"cpu": "building",
		"processor": "utilization, number",
		"memory": "utilization, percent",
		"mbuf": "utilization, percent",
		"packets": "packets / sec",
		"vpnusers": "drink",
		"quality": "seconds / %",
		"traffic": "bits / sec"
	};

	//lookup timeformats based on time period
	var timeLookup = {
		"-4y": "%Y-%m-%d",
		"-1y": "%Y-%m-%d",
		"-3m": "%Y-%m-%d",
		"-1m": "%Y-%m-%d",
		"-1w": "%m/%d %H:%M",
		"-2d": "%m/%d %H:%M",
		"-1d": "%H:%M:%S",
		"-8h": "%H:%M:%S",
		"-1h": "%H:%M:%S"
	};

	/***
	**
	** Control Settings Behavior
	**
	***/

	//TODO make this a function - call on page load
	$('#category-left').on('change', function() {

		switch(this.value) {
			case "system":
				$("#graph-left").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($system);

					foreach ($system as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "traffic":
				$("#graph-left").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($traffic);

					foreach ($traffic as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "packets":
				$("#graph-left").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($packets);

					foreach ($packets as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "quality":
				$("#graph-left").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($quality);

					foreach ($quality as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "captiveportal":
				$("#graph-left").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($captiveportal);

					foreach ($captiveportal as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "ntpd":
				$("#graph-left").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($ntpd);

					foreach ($ntpd as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "queues":
				$("#graph-left").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($queues);

					foreach ($queues as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "queuedrops":
				$("#graph-left").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($queuedrops);

					foreach ($queuedrops as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "dhcpd":
				$("#graph-left").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($dhcpd);

					foreach ($dhcpd as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "vpnusers":
				$("#graph-left").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($vpnusers);

					foreach ($vpnusers as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "none":
				$("#graph-left").empty().prop( "disabled", true );
				break;
		}

		$.each(newOptions, function(value,key) {
			$("#graph-left").append('<option value="' + value + '">' + key + '</option>');
		});

	});

	$('#category-right').on('change', function() {

		switch(this.value) {
			case "system":
				$("#graph-right").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($system);

					foreach ($system as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "traffic":
				$("#graph-right").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($traffic);

					foreach ($traffic as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "packets":
				$("#graph-right").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($packets);

					foreach ($packets as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "quality":
				$("#graph-right").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($quality);

					foreach ($quality as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "captiveportal":
				$("#graph-right").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($captiveportal);

					foreach ($captiveportal as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "ntpd":
				$("#graph-right").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($ntpd);

					foreach ($ntpd as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "queues":
				$("#graph-right").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($queues);

					foreach ($queues as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "queuedrops":
				$("#graph-right").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($queuedrops);

					foreach ($queuedrops as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "dhcpd":
				$("#graph-right").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($dhcpd);

					foreach ($dhcpd as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "vpnusers":
				$("#graph-right").empty().prop( "disabled", false );
				var newOptions = {
				<?php
					$terms = count($vpnusers);

					foreach ($vpnusers as $key => $val) {

						$terms--;
						$str = '"' . $key . '" : "' . $val . '"';
						if ($terms) {  $str .= ",\n"; }
						echo $str . "\n";

					}
				?>
				};
				break;
			case "none":
				$("#graph-right").empty().prop( "disabled", true );
				break;
		}

		$.each(newOptions, function(value,key) {
			$("#graph-right").append('<option value="' + value + '">' + key + '</option>');
		});

	});

	$('#time-period').on('change', function() {

		switch(this.value) {
			case "-3m":
			case "-1y":
			case "-4y":
				$("#resolution").empty().prop( "disabled", false );
				$("#resolution").append('<option value="86400" selected>1 Day</option>');
				$("#resolution").append('<option value="3600" disabled>1 Hour</option>');
				$("#resolution").append('<option value="300" disabled>5 Minutes</option>');
				$("#resolution").append('<option value="60" disabled>1 Minute</option>');
				break;
			case "-1m":
				$("#resolution").empty().prop( "disabled", false );
				$("#resolution").append('<option value="86400" selected>1 Day</option>');
				$("#resolution").append('<option value="3600">1 Hour</option>');
				$("#resolution").append('<option value="300" disabled>5 Minutes</option>');
				$("#resolution").append('<option value="60" disabled>1 Minute</option>');
				break;
			case "-1w":
				$("#resolution").empty().prop( "disabled", false );
				$("#resolution").append('<option value="86400">1 Day</option>');
				$("#resolution").append('<option value="3600" selected>1 Hour</option>');
				$("#resolution").append('<option value="300" disabled>5 Minutes</option>');
				$("#resolution").append('<option value="60" disabled>1 Minute</option>');
				break;
			case "-1d":
			case "-2d":
				$("#resolution").empty().prop( "disabled", false );
				$("#resolution").append('<option value="86400">1 Day</option>');
				$("#resolution").append('<option value="3600">1 Hour</option>');
				$("#resolution").append('<option value="300" selected>5 Minutes</option>');
				$("#resolution").append('<option value="60" disabled>1 Minute</option>');
				break;
			case "-8h":
				$("#resolution").empty().prop( "disabled", false );
				$("#resolution").append('<option value="86400" disabled>1 Day</option>');
				$("#resolution").append('<option value="3600">1 Hour</option>');
				$("#resolution").append('<option value="300" selected>5 Minutes</option>');
				$("#resolution").append('<option value="60">1 Minute</option>');
				break;
			case "-1h":
				$("#resolution").empty().prop( "disabled", false );
				$("#resolution").append('<option value="86400" disabled>1 Day</option>');
				$("#resolution").append('<option value="3600">1 Hour</option>');
				$("#resolution").append('<option value="300">5 Minutes</option>');
				$("#resolution").append('<option value="60" selected>1 Minute</option>');
				break;
			default:
				$("#resolution").empty().prop( "disabled", false );
				$("#resolution").append('<option value="86400">1 Day</option>');
				$("#resolution").append('<option value="3600">1 Hour</option>');
				$("#resolution").append('<option value="300" selected>5 Minutes</option>');
				$("#resolution").append('<option value="60">1 Minute</option>');
				break;
			}
			
	});

	/***
	**
	** Grab graphing options on submit
	**
	***/

	function getOptions() {
		var graphLeft = $( "#graph-left" ).val();
		var graphRight = $( "#graph-right" ).val();
		var startDate = ""; //$( "#start-date" ).val(); //TODO make human readable and convert to timestamp
		var endDate = ""; //$( "#end-date" ).val(); //TODO make human readable and convert to timestamp
		var timePeriod = $( "#time-period" ).val();
		var resolution = $( "#resolution" ).val();
		var graphtype = $( "#graph-type" ).val();
		var invert = $( "#invert" ).val();

		var graphOptions = 'left=' + graphLeft + '&right=' + graphRight + '&start=' + startDate + '&end=' + endDate + '&timePeriod=' + timePeriod + '&resolution=' + resolution + '&graphtype=' + graphtype + '&invert=' + invert ;

		return graphOptions;
	}

	$( "#update" ).click(function() {
		$("#chart").hide();
		$("#loading-msg").show();
		redraw_graph(getOptions());
	});

	/***
	**
	** NVD3 graphing
	** Website: http://nvd3.org/
	** Source: https://github.com/novus/nvd3
	** Documentation: https://nvd3-community.github.io/nvd3/examples/documentation.html
	**
	***/

	function redraw_graph(options) {

		d3.json("rrd_fetch_json.php")
			.header("Content-Type", "application/x-www-form-urlencoded")
			.post(options, function(error, data) {

			$("#chart").show();
			$("#loading-msg").hide();

			if (error) {
				return console.warn(error);
			}

			if (data.error) {
				$("#chart-error").show().html('<strong>Error</strong>: ' + data.error);
				return console.error(data.error);
			}

			data.map(function(series) {
				series.values = series.values.map(function(d) {
					if (series.invert) {
						return { x: d[0], y: 0 - d[1] }
					} else {
						return { x: d[0], y: d[1] }
					}
				});
				return series;
			});

			var timePeriod = $( "#time-period" ).val();
			var timeFormat = timeLookup[timePeriod];

			chart.xAxis.tickFormat(function(d) {
				return d3.time.format(timeFormat)(new Date(d));
			}).tickPadding(15);

			//y axis description by rrd database
			var gleft = $( "#graph-left" ).val();
			if (gleft) {
				var gLeftSplit = gleft.split("-");
				var leftLabel = rrdLookup[gLeftSplit[1]];
			}

			chart.yAxis1.tickFormat(function(d) {
				return d3.format('.2s')(d)
			}).axisLabel(leftLabel).tickPadding(5).showMaxMin(false);

			//add left title
			d3.select('#chart svg #left-title').remove();
			var leftTitle = $("#category-left option:selected").text() + " -- " + $("#graph-left option:selected").text();
			d3.select('#chart svg')
				.append("text")
				.attr("x", 150)
				.attr("y", 11)
				.attr("id", "left-title")
				.text("Left Axis: " + leftTitle);

			//y axis description by rrd database
			var gright = $( "#graph-right" ).val();
			if (gright) {
				var gRightSplit = gright.split("-");
				var rightLabel = rrdLookup[gRightSplit[1]];
			}

			chart.yAxis2.tickFormat(function(d) {
				return d3.format('.2s')(d)
			}).axisLabel(rightLabel).tickPadding(5).showMaxMin(false);

			//add right title
			d3.select('#chart svg #right-title').remove();
			var rightTitle = $("#category-right option:selected").text() + " -- " + $("#graph-right option:selected").text();
			d3.select('#chart svg')
				.append("text")
				.attr("x", 150)
				.attr("y", 28)
				.attr("id", "right-title")
				.text("Right Axis: " + rightTitle);

			d3.select('#chart svg')
				.datum(data)
				.transition()
				.duration(500)
				.call(chart);

			chart.update();

			calculate_summary(data);

		});

	}

	function calculate_summary(data) {

		$('#summary tbody').empty();

		data.forEach (function(d, i) {
			var summary = [];
			var units = "";

			for ( var v = 0; v < d.values.length; v++ ){

				//account for inversions
				if (d.invert) {
					//flip back to positive
					summary.push(0 - d.values[v].y);
				} else {
					summary.push(d.values[v].y);
				}

			}

			var min = d3.min(summary);
			var max = d3.max(summary);
			var avg = d3.sum(summary)/summary.length;
			var last = summary[summary.length-1];

			// TODO make function
			var formatted_avg = d3.formatPrefix(avg);
			var formatted_min = d3.formatPrefix(min);
			var formatted_max = d3.formatPrefix(max);
			var formatted_last = d3.formatPrefix(last);

			if (d.unit_acronym) {
				units = '<acronym data-toggle="tooltip" title="' + d.unit_desc + '">' + d.unit_acronym + '</acronym>';
			}

			if (d.ninetyfifth) {
				var ninetyFifth = d3.quantile(summary.sort(), .95);
				var formatted_95th = d3.formatPrefix(ninetyFifth);

				var ninetyfifthVal = formatted_95th.scale(ninetyFifth).toFixed() + ' ' + formatted_95th.symbol + units;
			} else {
				var ninetyfifthVal = "";
			}

			$('#summary tbody').append('<tr><th>' + d.key + '</th><td>' + formatted_min.scale(min).toFixed() + ' ' + formatted_min.symbol + units + '</td><td>' + formatted_avg.scale(avg).toFixed() + ' ' + formatted_avg.symbol + units + '</td><td>' + formatted_max.scale(max).toFixed() + ' ' + formatted_max.symbol + units + '</td><td>' + formatted_last.scale(last).toFixed() + ' ' + formatted_last.symbol + units + '</td><td>' + ninetyfifthVal + '</td></tr>');

		});

		$('acronym').tooltip();
	}

	var chart;

	d3.json("rrd_fetch_json.php")
		.header("Content-Type", "application/x-www-form-urlencoded")
		.post(getOptions(), function(error, json) {

		$("#chart").show();
		$("#loading-msg").hide();

		if (error) {
			return console.warn(error);
		}

		if (json.error) {
			return console.warn(json.error);
		}

		var data = json;

		data.map(function(series) {

			series.values = series.values.map(function(d) {
				if (series.invert) {
					return { x: d[0], y: 0 - d[1] }
				} else {
					return { x: d[0], y: d[1] }
				}
			});

			return series;

		});

		nv.addGraph(function() {

			chart = nv.models.multiChart()
				.color(d3.scale.category20().range())
				.useInteractiveGuideline(true)
				.margin({top: 160, right:100, left:100, bottom: 50});

			var timePeriod = $( "#time-period" ).val();
			var timeFormat = timeLookup[timePeriod];

			chart.xAxis.tickFormat(function(d) {
				return d3.time.format(timeFormat)(new Date(d));
			}).tickPadding(15);

			//TODO format y axis by rrd database

			//TODO add option to match axis scales?

			//y axis description by rrd database
			var gleft = $( "#graph-left" ).val();
			if (gleft) {
				var gLeftSplit = gleft.split("-");
				var leftLabel = rrdLookup[gLeftSplit[1]];
			}

			chart.yAxis1.tickFormat(function(d) {
				return d3.format('s')(d)
			}).axisLabel(leftLabel).tickPadding(5).showMaxMin(false);

			//add left title
			var leftTitle = $("#category-left option:selected").text() + " -- " + $("#graph-left option:selected").text();
			
			d3.select('#chart svg')
				.append("text")
				.attr("x", 150)
				.attr("y", 11)
				.attr("id", "left-title")
				.text("Left Axis: " + leftTitle);

			//TODO format y axis by rrd database

			//y axis description by rrd database
			var gright = $( "#graph-right" ).val();
			if (gright) {
				var gRightSplit = gright.split("-");
				var rightLabel = rrdLookup[gRightSplit[1]];
			}

			chart.yAxis2.tickFormat(function(d) {
				return d3.format('s')(d)
			}).axisLabel(rightLabel).tickPadding(5).showMaxMin(false);

			//add right title
			var rightTitle = $("#category-right option:selected").text() + " -- " + $("#graph-right option:selected").text();
			d3.select('#chart svg')
				.append("text")
				.attr("x", 150)
				.attr("y", 28)
				.attr("id", "right-title")
				.text("Right Axis: " + rightTitle);

			//custom tooltip contents
			chart.interactiveLayer.tooltip.contentGenerator(function(data) {

				var totals = false;
				var inboundTotal = [];
				var content = '<h3>' + d3.time.format('%Y-%m-%d %H:%M:%S')(new Date(data.value)) + '</h3><table><tbody>';

				for ( var v = 0; v < data.series.length; v++ ){

					if (data.series[v].key.includes('right axis')) {
						var tempKey = data.series[v].key.slice(0, -13);
					} else {
						var tempKey = data.series[v].key;
					}

					if ( tempKey.includes('inpass') || tempKey.includes('outpass') ) {
						totals = true;
						inboundTotal[tempKey] = v;
					}

					if ( ($("#invert").val() === "true") && (tempKey.includes('outpass') || tempKey.includes('packet loss')) ) {
						var trueValue = 0 - data.series[v].value;
					} else {
						var trueValue = data.series[v].value;
					}

					valueUnits = "";
					if (tempKey.includes('delay')) {
						trueValue *= 1000;	// Show delay values in same units as graph axis.  Fewer digits is human friendlier.
						valueUnits = " ms";
					}

					if (tempKey.includes('packet loss')) {
						valueUnits = " %";
					}

					//change decimal places to round to if a really small number
					if(trueValue < .01) {
						var adjustedTrueValue = d3.format(',')(trueValue.toFixed(6)); //TODO dynamically calculate number of zeros after decimal and base off that
					} else {
						var adjustedTrueValue = d3.format(',')(trueValue.toFixed(2));
					}

					content += '<tr><td class="legend-color-guide"><div style="background-color: ' + data.series[v].color + '"></div></td><td>' + data.series[v].key + '</td><td class="value"><strong>' + adjustedTrueValue + '</strong></td></tr>';
					content += '<tr><td class="legend-color-guide"><div style="background-color: ' + data.series[v].color + '"></div></td><td>' + data.series[v].key + '</td><td class="value"><strong>' + adjustedTrueValue + valueUnits + '</strong></td></tr>';
				}

				content += '</tbody></table>';

				return content;

			});

			d3.select('#chart svg')
			   .datum(data)
			   .transition()
			   .duration(500)
			   .call(chart);

			nv.utils.windowResize(function(){
				chart.update();
			});

			calculate_summary(data);

			return chart;

		});
	});
});
//]]>
</script>

<?php include("foot.inc");
