<?php
/*
	status_monitoring.php

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

function createOptions($dropdown) {
	echo 'var newOptions = {' . "\n";
	$terms = count($dropdown);
	foreach ($dropdown as $key => $val) {
		$terms--;
		$str = '"' . $key . '" : "' . $val . '"';
		if ($terms) {  $str .= ",\n"; }
		echo "\t\t\t\t\t" . $str;
	}
	echo "\n\t\t\t\t" . '};' . "\n";
}

//grab rrd filenames
$home = getcwd();
$rrddbpath = "/var/db/rrd/";
chdir($rrddbpath);
$databases = glob("*.rrd");
chdir($home);

if($_POST['enable']) {
	if(($_POST['enable'] === 'false')) { 
		unset($config['rrd']['enable']); 
	} else {
		$config['rrd']['enable'] = true;
	}
	write_config();

	$retval = 0;
	$retval = enable_rrd_graphing();
	$savemsg = get_std_save_message($retval); 
}

if ($_POST['ResetRRD']) {
	mwexec('/bin/rm /var/db/rrd/*');
	enable_rrd_graphing();
	setup_gateways_monitor();
	$savemsg = "RRD data has been cleared. New RRD files have been generated.";
}

//old config that needs to be updated
if(strpos($config['rrd']['category'], '&resolution') === false) {
	$config['rrd']['category'] = "left=system-processor&right=&resolution=300&timePeriod=-1d&startDate=&endDate=&startTime=0&endTime=0&graphtype=line&invert=true&autoUpdate=0";
	write_config();
}

//save new defaults
if ($_POST['defaults']) {
	// Time period setting change applies valid resolutions and selects the default resolution of the time period.
	// Time period setting needs to be before resolution so that the time period default doesn't override the page load default resolution.
	// Time period setting needs to be before custom start/end date/time so those will be enabled before applying their setting.
	$config['rrd']['category'] = "left=".$_POST['graph-left']."&right=".$_POST['graph-right']."&timePeriod=".$_POST['time-period']."&resolution=".$_POST['resolution']."&startDate=".$_POST['start-date']."&endDate=".$_POST['end-date']."&startTime=".$_POST['start-time']."&endTime=".$_POST['end-time']."&graphtype=".$_POST['graph-type']."&invert=".$_POST['invert']."&autoUpdate=".$_POST['auto-update'];
	write_config();
	$savemsg = "The changes have been applied successfully.";
}

$pconfig['enable'] = isset($config['rrd']['enable']);
$pconfig['category'] = $config['rrd']['category'];

$system = $packets = $quality = $traffic = $captiveportal = $ntpd = $queues = $queuedrops = $dhcpd = $vpnusers = $wireless = $cellular = [];

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

	if ($db_arr[1] === "wireless") {
		$wireless[$db_name] = convert_friendly_interface_to_friendly_descr($db_arr[0]);
	}

	if ($db_arr[1] === "cellular") {
		$cellular[$db_name] = convert_friendly_interface_to_friendly_descr($db_arr[0]);
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

## Get the configured options for Show/Hide monitoring settings panel.
$monitoring_settings_form_hidden = !$user_settings['webgui']['statusmonitoringsettingspanel'];

if ($monitoring_settings_form_hidden) {
	$panel_state = 'out';
	$panel_body_state = 'in';
} else {
	$panel_state = 'in';
	$panel_body_state = 'in';
}

$status_monitoring = true;

$pgtitle = array(gettext("Status"), gettext("Monitoring"));

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

?>

<script src="/vendor/d3/d3.min.js"></script>
<script src="/vendor/nvd3/nv.d3.js"></script>

<link href="/vendor/nvd3/nv.d3.css" media="screen, projection" rel="stylesheet" type="text/css">

<form class="form-horizontal collapse <?=$panel_state?> auto-submit" method="post" action="/status_monitoring.php" id="monitoring-settings-form">
	<div class="panel panel-default" id="monitoring-settings-panel">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("Settings"); ?>
				<span class="widget-heading-icon">
					<a data-toggle="collapse" href="#monitoring-settings-panel_panel-body">
						<i class="fa fa-plus-circle"></i>
					</a>
				</span>
			</h2>
		</div>
		<div id="monitoring-settings-panel_panel-body" class="panel-body collapse <?=$panel_body_state?>">
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
						if(!empty($cellular)) {
							echo '<option value="cellular">Cellular</option>';
						}
						if(!empty($wireless)) {
							echo '<option value="wireless">Wireless</option>';
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
						if(!empty($cellular)) {
							echo '<option value="cellular">Cellular</option>';
						}
						if(!empty($wireless)) {
							echo '<option value="wireless">Wireless</option>';
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
						<option value="custom">Custom</option>
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

					<span class="help-block">Type (Disabled)</span>
				</div>
				<div class="col-sm-2">
					<select class="form-control" id="invert" name="invert">
						<option value="true" selected>On</option>
						<option value="false">Off</option>
					</select>

					<span class="help-block">Inverse</span>
				</div>
				<div class="col-sm-2">
					<select class="form-control" id="auto-update" name="auto-update">
						<option value="0" selected>Off</option>
						<option value="-1">Settings Change</option>
						<option value="15">15 Seconds</option>
						<option value="60">1 Minute</option>
						<option value="300">5 Minutes</option>
						<option value="600">10 Minutes</option>
					</select>

					<span class="help-block">Auto Update</span>
				</div>
			</div>
			<div class="form-group" id="custom-time" style="display:none;">
				<label class="col-sm-2 control-label">
					Custom Period<br /><span class="badge" title="This feature is in BETA">BETA</span>
				</label>
				<div class="col-sm-2">
					<input type="text" class="form-control" id="start-date" name="start-date" disabled>

					<span class="help-block">Start Date <small>(MM/DD/YYYY)</small></span>
				</div>
				<div class="col-sm-2">
					<input type="number" class="form-control" value="0" id="start-time" name="start-time" min="0" max="23" step="1" disabled>

					<span class="help-block">Start Hour (0-23)</span>
				</div>
				<div class="col-sm-2">
					<input type="text" class="form-control" id="end-date" name="end-date" disabled>

					<span class="help-block">End Date <small>(MM/DD/YYYY)</small></span>
				</div>
				<div class="col-sm-2">
					<input type="number" class="form-control" value="0" id="end-time" name="end-time" min="0" max="23" step="1" disabled>

					<span class="help-block">End Hour (0-23)</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">
					Settings
				</label>
				<div class="col-sm-2">
					<button class="btn btn-sm btn-info" type="button" value="true" name="settings" id="settings"><i class="fa fa-cog fa-lg"></i> Display Advanced</button>
				</div>
				<div class="col-sm-2">
					<button class="btn btn-sm btn-primary" type="button" value="csv" name="export" id="export" style="display:none;"><i class="fa fa-download fa-lg"></i> Export As CSV</button>
				</div>
				<div class="col-sm-2">
					<button class="btn btn-sm btn-primary" type="submit" value="true" name="defaults" id="defaults" style="display:none;"><i class="fa fa-save fa-lg"></i> Save As Defaults</button>
				</div>
				<div class="col-sm-2">
					<?php
					if ($pconfig['enable']) {
						echo '<button class="btn btn-sm btn-danger" type="submit" value="false" name="enable" id="enable" style="display:none;"><i class="fa fa-ban fa-lg"></i> Disable Graphing</button>';
					} else {
						echo '<button class="btn btn-sm btn-success" type="submit" value="true" name="enable" id="enable" style="display:none;"><i class="fa fa-check fa-lg"></i> Enable Graphing</button>';
					}
					?>
				</div>
				<div class="col-sm-2">
					<button class="btn btn-sm btn-danger" type="submit" value="true" name="ResetRRD" id="ResetRRD" style="display:none;"><i class="fa fa-trash fa-lg"></i> Reset Graphing Data</button>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">
					&nbsp;
				</label>
				<div class="col-sm-2">
					<button class="btn btn-sm btn-primary update-graph" type="button"><i class="fa fa-refresh fa-lg"></i> Update Graphs</button>
				</div>
			</div>
		</div>
	</div>
</form>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title" style="display:inline-block">
			Interactive Graph
		</h2>
		<span class="alert" id="loading-msg">Loading Graph...</span>
	</div>
	<div class="panel-body">
		<div id="chart-error" class="alert alert-danger" style="display: none;"></div>
		<div id="monitoring-chart" class="d3-chart">
			<svg></svg>
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
	<div class="alert alert-info clearfix" role="alert">
		<div class="pull-left">
			<p>This tool allows comparison of RRD databases on two different Y axes.</p>

			<p>Graph data series visibility can be toggled by clicking on the labels in the legend. A single click toggles visibility and a double click hides all the others, except for that one.</p>
		</div>
	</div>
</div>

<script type="text/javascript">

//<![CDATA[
events.push(function() {

	//lookup axis labels based on graph name
	var rrdLookup = {
		"states": "States, IP",
		"throughput": "Bits Per Second",
		"cpu": "building",
		"processor": "Utilization, Number",
		"memory": "Utilization, Percent",
		"mbuf": "Utilization, Percent",
		"packets": "Packets Per Second",
		"vpnusers": "Users",
		"quality": "Milliseconds, Percent",
		"traffic": "Bits Per Second",
		"queue" : "Bits Per Second",
		"queuedrops" : "Drops Per Second",
		"wireless" : "snr / channel / rate",
		"cellular" : "Signal"
	};

	//lookup axis formating based on graph name
	var formatLookup = {
		"states": ".2s",
		"throughput": ".2s",
		"cpu": ".2s",
		"processor": ".2f",
		"memory": ".2f",
		"mbuf": ".2s",
		"packets": ".2s",
		"vpnusers": ".2f",
		"quality": ".2f",
		"traffic": ".2s",
		"queue" : ".2s",
		"queuedrops" : ".2s",
		"wireless" : ".2s",
		"cellular" : ".2s"
	};

	//lookup timeformats based on resolution
	var timeLookup = {
		"86400": "%Y-%m-%d",
		"3600": "%m/%d %H:%M",
		"300": "%H:%M:%S",
		"60": "%H:%M:%S"
	};

	//lookup human readable time based on number of seconds
	var stepLookup = {
		"60": "1 Minute",
		"300": "5 Minutes",
		"3600": "1 Hour",
		"86400": "1 Day"
	};

	//UTC offset and client/server timezone handing
	var ServerUTCOffset = <?php echo date('Z') / 3600; ?>;
	var ClientUTC = new Date();
	var ClientUTCOffset = ClientUTC.getTimezoneOffset() / 60;
	var tzOffset = (ClientUTCOffset + ServerUTCOffset) * 3600000;

	/***
	**
	** Control Settings Behavior
	**
	***/

	//create the dropdown options for all the different graph types
	$('select[id^="category-"]').on('change', function() {

		var categoryId = this.id.split("-");

		switch(this.value) {
			case "system":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($system); ?>
				break;
			case "traffic":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($traffic); ?>
				break;
			case "packets":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($packets); ?>
				break;
			case "quality":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($quality); ?>
				break;
			case "captiveportal":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($captiveportal); ?>
				break;
			case "ntpd":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($ntpd); ?>
				break;
			case "queues":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($queues); ?>
				break;
			case "queuedrops":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($queuedrops); ?>
				break;
			case "dhcpd":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($dhcpd); ?>
				break;
			case "vpnusers":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($vpnusers); ?>
				break;
			case "wireless":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($wireless); ?>
				break;
			case "cellular":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", false );
				<?php createOptions($cellular); ?>
				break;
			case "none":
				$("#graph-" + categoryId[1]).empty().prop( "disabled", true );
				break;
		}

		$.each(newOptions, function(value,key) {
			$("#graph-" + categoryId[1]).append('<option value="' + value + '">' + key + '</option>');
		});

		update_graph();
	});

	$('#graph-left').on('change', function() {
		update_graph();
	});

	$('#graph-right').on('change', function() {
		update_graph();
	});

	$('#time-period').on('change', function() {
		$( "#resolution" ).prop( "disabled", false );
		$( "#custom-time" ).hide();
		$( "#start-date" ).prop( "disabled", true );
		$( "#end-date" ).prop( "disabled", true );
		$( "#start-time" ).prop( "disabled", true );
		$( "#end-time" ).prop( "disabled", true );
		valid_resolutions(this.value);
		update_graph();
	});

	$('#resolution').on('change', function() {
		update_graph();
	});

	$('#graph-type').on('change', function() {
		update_graph();
	});

	$('#invert').on('change', function() {
		update_graph();
	});

	$('#auto-update').on('change', function() {
		update_graph();
	});

	$('#start-date').on('change', function() {
		update_graph();
	});

	$('#end-date').on('change', function() {
		update_graph();
	});

	$('#start-time').on('change', function() {
		update_graph();
	});

	$('#end-time').on('change', function() {
		update_graph();
	});

	$( ".update-graph" ).click(function() {
		update_graph(true);
	});

	var auto_update;

	function update_graph(force) {

		clearTimeout(auto_update);

		if ($( "#auto-update" ).val() == "-1") {
			force = true;
		}

		if (force || $( "#auto-update" ).val() > 0) {
			$("#loading-msg").show();
			$("#chart-error").hide();
			draw_graph(getOptions());
		}

		if ( $( "#auto-update" ).val() > 0) {

			update_interval = $( "#auto-update" ).val();

			// Ensure graph update happens at end of the minute so RRD will have a data point for the current minute and graph doesn't end with 0 value.
			// This is a hack that can probably be fix in a better fashion once start and end times are implemented.
			seconds = new Date().getSeconds();
			update_interval -= seconds + 1;

			if (update_interval <= 0 || seconds >= 59) {
				update_interval = $( "#auto-update" ).val();
			}
			// End hack.

			auto_update = setTimeout(update_graph, update_interval * 1000);
		}
	}

	function valid_resolutions(timePeriod) {
		switch(timePeriod) {
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
			case "custom":
				$( "#resolution" ).empty().append('<option value="highest">Highest Available</option>');
				$( "#start-date" ).prop( "disabled", false );
				$( "#end-date" ).prop( "disabled", false );
				$( "#start-time" ).prop( "disabled", false );
				$( "#end-time" ).prop( "disabled", false );
				$( "#custom-time" ).show();
				break;
			default:
				$("#resolution").empty().prop( "disabled", false );
				$("#resolution").append('<option value="86400">1 Day</option>');
				$("#resolution").append('<option value="3600">1 Hour</option>');
				$("#resolution").append('<option value="300" selected>5 Minutes</option>');
				$("#resolution").append('<option value="60">1 Minute</option>');
				break;
			}
	}

	function convertToEpoch(datestring) {
		var parts = datestring.match(/(\d{2})\/(\d{2})\/(\d{4})\:(\d{1,2})/);

		if(!parts || (parts[1].length > 2 || parts[2].length > 2 || parts[3].length > 4 || parts[4].length > 2)) {
			return false;
		}

		return (Date.UTC(parts[3], parts[1]-1, parts[2], parts[4]) / 1000) - (ServerUTCOffset*3600);
	}

	/***
	**
	** Grab graphing options on submit
	**
	***/

	//TODO work in more validation
	function getOptions() {
		var error = "There was an error getting the options.";

		var graphLeft = $( "#graph-left" ).val();
		var graphRight = $( "#graph-right" ).val();
		var startDate = $( "#start-date" ).val();
		var endDate = $( "#end-date" ).val();
		var startTime = $( "#start-time" ).val();
		var endTime = $( "#end-time" ).val();
		var timePeriod = $( "#time-period" ).val();
		var resolution = $( "#resolution" ).val();
		var graphtype = $( "#graph-type" ).val();
		var autoUpdate = $( "#auto-update" ).val();
		var invert = $( "#invert" ).val();
		var start = '';
		var end = '';

		//convert dates to epoch and validate
		if(timePeriod === "custom" && startDate && endDate) { //TODO check if both are valid dates
			start = convertToEpoch(startDate + ":" + startTime);
			end = convertToEpoch(endDate + ":" + endTime);

			if(!start || !end) {
				error = "Invalid Date/Time in Custom Period."
				$("#monitoring-chart").hide();
				$("#chart-error").show().html('<strong>Error</strong>: ' + error);
				console.warn(error);
				return false;
			}
		}

		var graphOptions = 'left=' + graphLeft + '&right=' + graphRight + '&start=' + start + '&end=' + end + '&resolution=' + resolution + '&timePeriod=' + timePeriod + '&graphtype=' + graphtype + '&invert=' + invert + '&autoUpdate=' + autoUpdate ;

		return graphOptions;
	}

	$( "#start-date" ).datepicker({
      defaultDate: "-1w",
      changeMonth: true,
      changeYear: true,
      maxDate: new Date
    });

    $( "#end-date" ).datepicker({
      changeMonth: true,
      changeYear: true,
    });

	function applySettings(defaults) {

		var allOptions = defaults.split("&");

		// Make sure autoUpdate is the last item in the options list so it is last to be processed and potentially enabled before all the other options have been applied.
		// Set the auto update option to 0 so that multiple draw_graph calls are not fired off while other options are being applied/changed.
		$( "#auto-update" ).val("0").change();

		allOptions.forEach(function(entry) {
			
			var currentOption = entry.split("=");

			if(currentOption[0] === "left" || currentOption[0] === "right") {
				
				var rrdDb = currentOption[1].split("-");

				if(rrdDb[0]) {

					if (rrdDb[0] === "system") {
						$( "#category-" + currentOption[0] ).val(rrdDb[0]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

					if (rrdDb[1] === "traffic") {
						$( "#category-" + currentOption[0] ).val(rrdDb[1]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

					if (rrdDb[1] === "packets") {
						$( "#category" + currentOption[0] ).val(rrdDb[1]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

					if (rrdDb[1] === "quality") {
						$( "#category-" + currentOption[0] ).val(rrdDb[1]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

					if (rrdDb[1] === "queues") {
						$( "#category-" + currentOption[0] ).val(rrdDb[1]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

					if (rrdDb[1] === "queuedrops") {
						$( "#category-" + currentOption[0] ).val(rrdDb[1]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

					if (rrdDb[0] === "captiveportal") {
						$( "#category-" + currentOption[0] ).val(rrdDb[0]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

					if (rrdDb[0] === "ntpd") {
						$( "#category-" + currentOption[0] ).val(rrdDb[0]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

					if (rrdDb[1] === "dhcpd") {
						$( "#category-" + currentOption[0] ).val(rrdDb[1]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

					if (rrdDb[1] === "vpnusers") {
						$( "#category-" + currentOption[0] ).val(rrdDb[1]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

					if (rrdDb[1] === "wireless") {
						$( "#category-" + currentOption[0] ).val(rrdDb[1]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

					if (rrdDb[1] === "cellular") {
						$( "#category-" + currentOption[0] ).val(rrdDb[1]).change();
						$( "#graph-" + currentOption[0] ).val(currentOption[1]);
					}

				} else {
					$( "#category-" + currentOption[0] ).val("none").change();
				}

			}

			if(currentOption[0] === "timePeriod") {
				$( "#time-period" ).val(currentOption[1]).change();
			}

			if(currentOption[0] === "startDate") {
				$( "#start-date" ).val(currentOption[1]);
			}

			if(currentOption[0] === "endDate") {
				$( "#end-date" ).val(currentOption[1]);
			}

			if(currentOption[0] === "startTime") {
				$( "#start-time" ).val(currentOption[1]);
			}

			if(currentOption[0] === "endTime") {
				$( "#end-time" ).val(currentOption[1]);
			}

			if(currentOption[0] === "resolution") {
				$( "#resolution" ).val(currentOption[1]);
			}

			if(currentOption[0] === "graphtype") {
				$( "#graph-type" ).val(currentOption[1]);
			}

			if(currentOption[0] === "invert") {
				$( "#invert" ).val(currentOption[1]);
			}

			if(currentOption[0] === "autoUpdate") {
				$( "#auto-update" ).val(currentOption[1]).change();
			}

		}, this);

	}

	applySettings("<?php echo $pconfig['category']; ?>");

	$( "#settings" ).click(function() {
		($(this).text().trim() === 'Display Advanced') ? $(this).html('<i class="fa fa-cog fa-lg"></i> Hide Advanced') : $(this).html('<i class="fa fa-cog fa-lg"></i> Display Advanced');
		$("#export").toggle();
		$("#defaults").toggle();
		$("#enable").toggle();
		$("#ResetRRD").toggle();
	});

	$( "#export" ).click(function() {

		var csv = ","; //skip first csv column in header row
		var csvArray = [];

		d3.json("rrd_fetch_json.php")
			.header("Content-Type", "application/x-www-form-urlencoded")
			.post(getOptions(), function(error, json) {

				if (error) {
					$("#monitoring-chart").hide();
					$("#chart-error").show().html('<strong>Error</strong>: ' + error);
					return console.warn(error);
				}

				if (json.error) {
					$("#monitoring-chart").hide();
					$("#chart-error").show().html('<strong>Error</strong>: ' + json.error);
					return console.warn(json.error);
				}

				var index = 0;
				
				json.forEach(function(event) {
					
					//create header row
					csv += event.key + ",";

					var count = 0;

					event.values.forEach(function(event) {

						if(index > 0) {
							csvArray[count] = csvArray[count] + "," + event[1];
						} else {
							csvArray[count] = event[0] + "," + event[1];
						}

						count++;

					});

					index++;

				});

				//end header row
				csv += "\n";

				//fill with values
				csvArray.forEach(function(event) {
					csv += event + "\n";
				});

				window.open("data:text/csv;charset=utf-8," + escape(csv));

			});

	});

	/***
	**
	** NVD3 graphing
	** Website: http://nvd3.org/
	** Source: https://github.com/novus/nvd3
	** Documentation: https://nvd3-community.github.io/nvd3/examples/documentation.html
	**
	***/

	function draw_graph(options) {

		if(!options) {
			$("#loading-msg").hide();
			return false;
		}

		d3.json("rrd_fetch_json.php")
			.header("Content-Type", "application/x-www-form-urlencoded")
			.post(options, function(error, json) {

			$("#monitoring-chart").show();
			$("#loading-msg").hide();

			d3.select("svg").remove(); //delete previous svg so it can be drawn from scratch
			d3.select("div[id^=nvtooltip-]").remove(); //delete previous tooltip in case it gets hung
			d3.select('#monitoring-chart').append('svg'); //re-add blank svg so it and be drawn on

			if (error) {
				$("#monitoring-chart").hide();
				$("#chart-error").show().html('<strong>Error</strong>: ' + error);
				return console.warn(error);
			}

			if (json.error) {
				$("#monitoring-chart").hide();
				$("#chart-error").show().html('<strong>Error</strong>: ' + json.error);
				return console.warn(json.error);
			}

			var data = json;

			data.map(function(series) {

				series.values = series.values.map(function(d) {
					if (series.invert) {
						return { x: (d[0] + tzOffset), y: 0 - d[1] }
					} else {
						return { x: (d[0] + tzOffset), y: d[1] }
					}
				});

				return series;

			});

			nv.addGraph(function() {

				chart = nv.models.multiChart()
					.color(d3.scale.category20().range())
					.useInteractiveGuideline(true)
					.margin({top: 160, right:100, left:100, bottom: 80});

				var timeFormat = timeLookup[data[0].step];

				chart.xAxis.tickFormat(function(d) {
					return d3.time.format(timeFormat)(new Date(d));
				}).tickPadding(15);

				//TODO add option to match axis scales?

				//y axis description by rrd database
				var gleft = $( "#graph-left" ).val();
				if (gleft) {
					var gLeftSplit = gleft.split("-");
					var leftLabel = rrdLookup[gLeftSplit[1]];
					var leftAxisFormat = formatLookup[gLeftSplit[1]];
				}

				if(!leftAxisFormat) {
					leftAxisFormat = ".2s";
				}

				chart.yAxis1.tickFormat(function(d) {
					return d3.format(leftAxisFormat)(d)
				}).axisLabel(leftLabel).tickPadding(5).showMaxMin(false);

				//add left title
				var leftTitle = $("#category-left option:selected").text() + " -- " + $("#graph-left option:selected").text();
				
				d3.select('#monitoring-chart svg')
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
					var rightAxisFormat = formatLookup[gRightSplit[1]];
				}

				if(!rightAxisFormat) {
					rightAxisFormat = ".2s";
				}

				chart.yAxis2.tickFormat(function(d) {
					return d3.format(rightAxisFormat)(d)
				}).axisLabel(rightLabel).tickPadding(5).showMaxMin(false);

				//add right title
				var rightTitle = $("#category-right option:selected").text() + " -- " + $("#graph-right option:selected").text();
				d3.select('#monitoring-chart svg')
					.append("text")
					.attr("x", 150)
					.attr("y", 28)
					.attr("id", "right-title")
					.text("Right Axis: " + rightTitle);

				//add system name
				var systemName = '<?=htmlspecialchars($config['system']['hostname'] . "." . $config['system']['domain']); ?>';
				d3.select('#monitoring-chart svg')
					.append("text")
					.attr("x", 100)
					.attr("y", 415)
					.attr("id", "system-name")
					.text(systemName);

				//add time period
				var timePeriod = $("#time-period option:selected").text();
				d3.select('#monitoring-chart svg')
					.append("text")
					.attr("x", 330)
					.attr("y", 415)
					.attr("id", "time-period")
					.text("Time Period: " + timePeriod);

				//add resolution
				var Resolution = $("#resolution option:selected").text();
				d3.select('#monitoring-chart svg')
					.append("text")
					.attr("x", 530)
					.attr("y", 415)
					.attr("id", "resolution")
					.text("Resolution: " + stepLookup[data[0].step]);

				//add last updated date
				var currentDate = d3.time.format('%a %b %d %H:%M:%S %Y')(new Date(data[0].last_updated));
				d3.select('#monitoring-chart svg')
					.append("text")
					.attr("x", 755)
					.attr("y", 415)
					.attr("id", "current-date")
					.text(currentDate);

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

						//format number as decimal or SI depending on attributes
						if(localStorage.getItem(data.series[v].key+'-format') === "f") {

							//change decimal places to round to if a really small number
							if(trueValue < .01 && trueValue > 0) {
								var adjustedTrueValue = d3.format('.6f')(trueValue) + ' '; //TODO dynamically calculate number of zeros after decimal and base off that
							} else {
								var adjustedTrueValue = d3.format('.2f')(trueValue) + ' ';
							}

						} else {

							var formatted_value = d3.formatPrefix(trueValue);
							adjustedTrueValue = formatted_value.scale(trueValue).toFixed(2) + ' ' + formatted_value.symbol;

						}

						content += '<tr><td class="legend-color-guide"><div style="background-color: ' + data.series[v].color + '"></div></td><td>' + data.series[v].key + '</td><td class="value"><strong>' + adjustedTrueValue + localStorage.getItem(data.series[v].key+'-unit') + '</strong></td></tr>';

					}

					content += '</tbody></table>';

					return content;

				});

				d3.select('#monitoring-chart svg')
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

	}

	function calculate_summary(data) {

		$('#summary tbody').empty();

		//clear localStorage between graph changes
		localStorage.clear();

		data.forEach (function(d, i) {
			var summary = [];
			var units = "";

			if (d.unit_acronym) {
				units = '<acronym data-toggle="tooltip" title="' + d.unit_desc + '">' + d.unit_acronym + '</acronym>';
			}

			for ( var v = 0; v < d.values.length; v++ ){

				if (d.invert) {
					//flip back to positive
					summary.push(0 - d.values[v].y);
				} else {
					summary.push(d.values[v].y);
				}

			}

			var avg = d.avg;
			var min = d.min;
			var max = d.max;
			var last = summary[summary.length-1];

			if(d.format === "s") {
				var formatted_avg = d3.formatPrefix(avg);
				var formatted_min = d3.formatPrefix(min);
				var formatted_max = d3.formatPrefix(max);
				var formatted_last = d3.formatPrefix(last);

				var avg_value = formatted_avg.scale(avg).toFixed(2) + ' ' + formatted_avg.symbol + units;
				var min_value = formatted_min.scale(min).toFixed(2) + ' ' + formatted_min.symbol + units;
				var max_value = formatted_max.scale(max).toFixed(2) + ' ' + formatted_max.symbol + units;
				var last_value = formatted_last.scale(last).toFixed(2) + ' ' + formatted_last.symbol + units;
			} else {
				var avg_value = d3.format(".2f")(avg) + ' ' + units;
				var min_value = d3.format(".2f")(min) + ' ' + units;
				var max_value = d3.format(".2f")(max) + ' ' + units;
				var last_value = d3.format(".2f")(last) + ' ' + units;
			}

			if (d.ninetyfifth) {
				var ninetyFifth = d3.quantile(summary.sort(function(a,b) { return a - b; }), .95);
				var formatted_95th = d3.formatPrefix(ninetyFifth);

				var ninetyfifthVal = formatted_95th.scale(ninetyFifth).toFixed(2) + ' ' + formatted_95th.symbol + units;
			} else {
				var ninetyfifthVal = "";
			}

			$('#summary tbody').append('<tr><th>' + d.key + '</th><td>' + min_value + '</td><td>' + avg_value + '</td><td>' + max_value + '</td><td>' + last_value + '</td><td>' + ninetyfifthVal + '</td></tr>');

			//store each lines units and format in local storage so it can be accessed in the tooltip
			localStorage.setItem(d.key+'-unit', d.unit_acronym);
			localStorage.setItem(d.key+'-format', d.format);

		});

		$('acronym').tooltip();
	}

	var chart;

	<?php
	if ($pconfig['enable']) { 
		echo 'var rrdEnabled = true;';
	} else {
		echo 'var rrdEnabled = false;';
	}
	?>

	if(!rrdEnabled) {

		$("#loading-msg").hide();
		$("#monitoring-chart").hide();
		$("#chart-error").show().html('<strong>Error</strong>: RRD graphs are not enabled. Enable in the Settings above.');

	} else {

		if ( !$( "#auto-update" ).length || $( "#auto-update" ).val() == 0) {	// If auto-update is enabled then it will draw the graph.  Don't draw the graph twice.
			draw_graph(getOptions());
		}

	}

});
//]]>
</script>

<?php include("foot.inc");
