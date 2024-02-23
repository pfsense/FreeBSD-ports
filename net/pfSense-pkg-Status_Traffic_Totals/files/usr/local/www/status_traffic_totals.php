<?php
/*
 * status_traffic_totals.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2024 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

require("guiconfig.inc");
require_once("ipsec.inc");
require_once("status_traffic_totals.inc");

/* TODOs */
//fix broken table sort by blowing away and creating new (initializing) each time?
//show current or unused databases somehow
//update timestamp with last updated date
//make Save as Defaults AJAX


/*
//grab vnStat filenames
$home = getcwd();
$rrddbpath = "/var/db/vnstat/";
chdir($rrddbpath);
$databases = glob(".*");
unset($databases[0]);
unset($databases[1]);
chdir($home);

print_r($databases);
*/
global $config;

$vnscfg = config_get_path('installedpackages/traffictotals/config/0', []);
$portlist = vnstat_portlist();

if (isset($_POST['enable']) && !empty($_POST['enable'])) {
	if ($_POST['enable'] === 'true') {
		$state = gettext('Enabled');
		$vnscfg['enabled'] = true;
	} elseif ($_POST['enable'] === 'false') {
		$state = gettext('Disabled');
		unset($vnscfg['enabled']);
	} else {
		// Bad data
		exit;
	}
	config_set_path('installedpackages/traffictotals/config/0', $vnscfg);
	write_config(sprintf(gettext('%s Graphing for Status > Traffic Totals'), $state));
	vnstat_sync();
	// Give the service time to start/stop
	sleep(5);
}

if ($_POST['reset']) {
	vnstat_reset();
}

//save new defaults
if ($_POST['defaults']) {
	//TODO clean inputs
	$timePeriod = $_POST['time-period'];
	$interfaces = json_encode($_POST['interfaces']);
	$graphtype = $_POST['graph-type'];
	$invert = $_POST['invert'];
	$cumulative = $_POST['cumulative'];
	$startDay = $_POST['start-day'];

	$vnscfg['timeperiod'] = $timePeriod;
	$vnscfg['interfaces'] = $interfaces;
	$vnscfg['graphtype'] = $graphtype;
	$vnscfg['invert'] = $invert;
	$vnscfg['cumulative'] = $cumulative;
	$vnscfg['startday'] = $startDay;

	config_set_path('installedpackages/traffictotals/config/0', $vnscfg);
	write_config('Save default settings for Status > Traffic Totals');
	vnstat_sync();
	$savemsg = "The changes have been applied successfully.";
}

if (isset($vnscfg['startday'])) {
	$ifArray = json_decode($vnscfg['interfaces']);
	$interfaces = "";

	foreach($ifArray as $interface) {
		$interfaces .= 'interfaces[]=' . $interface . '&';
	}

	$timePeriod = $vnscfg['timeperiod'];
	$graphtype = $vnscfg['graphtype'];
	$invert = $vnscfg['invert'];
	$cumulative = $vnscfg['cumulative'];
	$startDay = $vnscfg['startday'];
} else {
	$interfaces = "";

	foreach($portlist as $interface => $details) {
		$interfaces .= 'interfaces[]=' . $details['if'] . '&';
	}

	$timePeriod = "day";
	$graphtype = "line";
	$invert = "true";
	$cumulative = "false";
	$startDay = 1;
}

$defaults = $interfaces . 'time-period=' . $timePeriod . '&graph-type=' . $graphtype . '&invert=' . $invert . '&cumulative=' . $cumulative . '&start-day=' . $startDay;

$pgtitle = array(gettext("Status"), gettext("Traffic Totals"));
$shortcut_section = "vnstat";

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Hourly"), false, "#hour");
$tab_array[] = array(gettext("Daily"), false, "#day");
$tab_array[] = array(gettext("Monthly"), false, "#month");
$tab_array[] = array(gettext("Top 10 Days"), false, "#top");
display_top_tabs($tab_array);

?>

<script src="/vendor/d3/d3.min.js"></script>
<script src="/vendor/nvd3/nv.d3.js"></script>

<link href="/vendor/nvd3/nv.d3.css" media="screen, projection" rel="stylesheet" type="text/css">

<form class="form-horizontal in auto-submit" method="post" action="/status_traffic_totals.php" id="traffic-totals-settings-form">
	<div class="panel panel-default" id="traffic-totals-settings-panel">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("Settings"); ?>
				<span class="widget-heading-icon">
					<a data-toggle="collapse" href="#traffic-totals-settings-panel_panel-body">
						<i class="fa-solid fa-plus-circle"></i>
					</a>
				</span>
			</h2>
		</div>
		<div id="traffic-totals-settings-panel_panel-body" class="panel-body in">
			<div class="form-group">
				<label class="col-sm-2 control-label">
					Options
				</label>
				<div class="col-sm-2">
					<select class="form-control" id="interfaces" name="interfaces[]" multiple>
						<?php foreach($portlist as $interface => $details): ?>
							<option value="<?= $details['if'] ?>" selected><?= htmlspecialchars($details['descr']) ?></option>;
						<?php endforeach; ?>
					</select>

					<span class="help-block">Interface(s)</span>
				</div>
				<div class="col-sm-2">
					<select class="form-control" id="graph-type" name="graph-type">
						<option value="line" selected>Line</option>
						<option value="bar">Bar</option>
						<option value="area">Line (Stacked)</option>
						<option value="stacked">Bar (Stacked)</option>
					</select>

					<span class="help-block">Type</span>
				</div>
				<div class="col-sm-2">
					<select class="form-control" id="invert" name="invert">
						<option value="true" selected>On</option>
						<option value="false">Off</option>
					</select>

					<span class="help-block">Inverse</span>
				</div>
				<div class="col-sm-2">
					<select class="form-control" id="cumulative" name="cumulative">
						<option value="true">On</option>
						<option value="false" selected>Off</option>
					</select>

					<span class="help-block">Cumulative</span>
				</div>
				<div class="col-sm-2">
					<input type="number" class="form-control" value="<?=$startDay?>" id="start-day" name="start-day" min="1" max="28" step="1">

					<span class="help-block">Start Day</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">
					Settings
				</label>
				<div class="col-sm-2">
					<button class="btn btn-sm btn-info" type="button" value="true" name="settings" id="settings"><i class="fa-solid fa-cog fa-lg"></i> Display Advanced</button>
				</div>
				<div class="col-sm-2">
					<button class="btn btn-sm btn-primary" type="button" value="csv" name="export" id="export" style="display:none;"><i class="fa-solid fa-download fa-lg"></i> Export As CSV</button>
				</div>
				<div class="col-sm-2">
					<button class="btn btn-sm btn-primary" type="submit" value="true" name="defaults" id="defaults" style="display:none;"><i class="fa-solid fa-save fa-lg"></i> Save As Defaults</button>
				</div>
				<div class="col-sm-2">
<?php if (isset($vnscfg['enabled'])): ?>
					<button class="btn btn-sm btn-danger" type="submit" value="false" name="enable" id="enable" style="display:none;"><i class="fa-solid fa-ban fa-lg"></i> Disable Graphing</button>
<?php else:?>
					<button class="btn btn-sm btn-success" type="submit" value="true" name="enable" id="enable" style="display:none;"><i class="fa-solid fa-check fa-lg"></i> Enable Graphing</button>
<?php endif; ?>
				</div>
				<div class="col-sm-2">
					<button class="btn btn-sm btn-danger" type="submit" value="true" name="reset" id="reset" style="display:none;"><i class="fa-solid fa-trash-can fa-lg"></i> Reset Graphing Data</button>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">
					&nbsp;
				</label>
				<div class="col-sm-2">
					<button class="btn btn-sm btn-primary update-graph" type="button"><i class="fa-solid fa-arrows-rotate fa-lg"></i> Update Graphs</button>
				</div>
			</div>
		</div>
	</div>
	<input type="hidden" id="time-period" name="time-period" value="hour">
</form>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">Interactive Graph</h2>
	</div>
	<div class="panel-body">
		<div class="alert alert-info" id="loading-msg">Loading Graph...</div>
		<div id="chart-error" class="alert alert-danger" style="display: none;"></div>
		<div id="traffic-totals-chart" class="d3-chart">
			<svg id="traffic-totals-svg"></svg>
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">Data Summary</h2>
	</div>
	<div class="panel-body">
		<div class="table-responsive">
			<table id="summary" class="table table-striped table-hover sortable-theme-bootstrap" data-sortable>
				<thead></thead>
				<tbody></tbody>
			</table>
		</div>
	</div>
</div>

<div class="infoblock">
	<div class="alert alert-info clearfix" role="alert">
		<div class="pull-left">
			The data for this page is pulled from vnStat and updated every five minutes.
		</div>
	</div>
</div>

<script type="text/javascript">

//<![CDATA[
events.push(function() {

	//lookup timeformats based on time period
	var timeLookup = {
		'hour':  '%H:00',
		'day':   '%m/%d',
		'month': '%m/%Y',
		'top':  '%Y-%m-%d',
		'uptime': 'd.m.Y, H:i'
	};

	//UTC offset and client/server timezone handing
	var ServerUTCOffset = <?php echo date('Z') / 3600; ?>;
	var utc = new Date();
	var ClientUTCOffset = utc.getTimezoneOffset() / 60;
	var tzOffset = (ClientUTCOffset + (ServerUTCOffset)) * 3600000;

	function applySettings(defaults) {

		var allOptions = defaults.split("&");

		//clear interface selections before resetting them
		$("#interfaces > option").each(function() {
			$(this).prop("selected", false);
		});

		allOptions.forEach(function(entry) {

			var currentOption = entry.split("=");

			if(currentOption[0] === "interfaces[]") {
				$("#interfaces option[value='" + currentOption[1] + "']").prop("selected", true);
			}

			if(currentOption[0] === "time-period") {

				//remove active class before resetting it
				$( "body > div.container > ul.nav > li" ).each(function(entry) {
					$(this).removeClass('active');
				});

				//reset active class
				$( "body > div.container > ul.nav > li > a" ).each(function(entry) {
					if($(this).attr('href').substring(1) === currentOption[1]) {
						$(this).parent().addClass('active');
					}
				});

			}

			if(currentOption[0] === "graph-type") {
				$( "#graph-type" ).val(currentOption[1]);
			}

			if(currentOption[0] === "invert") {
				$( "#invert" ).val(currentOption[1]);
			}

			if(currentOption[0] === "cumulative") {
				$( "#cumulative" ).val(currentOption[1]).change();
			}

			if(currentOption[0] === "start-day") {
				$( "#start-day" ).val(currentOption[1]);
			}

		}, this);

	}

	/***
	**
	** NVD3 graphing
	** Website: http://nvd3.org/
	** Source: https://github.com/novus/nvd3
	** Documentation: https://nvd3-community.github.io/nvd3/examples/documentation.html
	**
	***/

	function draw_graph() {

		$.getJSON( "vnstat_fetch_json.php", function(raw_json) {

			if (raw_json.error) {

				var errorMsg = raw_json.error;

				$("#traffic-totals-chart").hide();
				$("#loading-msg").hide();

				//check if interface databases don't exist
				if(errorMsg.substring(0,17) === "No database found" || errorMsg.substring(0,23) === "Unable to open database" || errorMsg.substring(0,23) === "Failed to open database" ) {

					//flip enable graphing button
					$( "#enable" ).val('true').html('<i class="fa-solid fa-check fa-lg"></i> Enable Graphing').removeClass('btn-danger').addClass('btn-success');

					errorMsg = "Graphing is not enabled, Enable Graphing in the Advanced Settings above.";

				}


				$("#chart-error").show().html('<strong>Error</strong>: ' + errorMsg);

				return console.warn(raw_json.error);
			}

			var interfaces = $( "#interfaces" ).val();
			var timePeriod = $( "li.active a:first" ).attr('href').substring(1);
			var graphtype = $( "#graph-type" ).val();
			var invert = $( "#invert" ).val();
			var cumulative = $( "#cumulative" ).val();

			//The Top 10 logic doesn't work with more than one interface
			if(timePeriod === "top" && interfaces.length > 1) {

				$("#traffic-totals-chart").hide();
				$("#loading-msg").hide();
				$('#summary tbody').empty();
				$('#summary thead').empty();

				$("#chart-error").show().html('<strong>Error</strong>: Top 10 doesn\'t allow for more than one interface to be selected.');

				return console.warn("Top 10 doesn't allow for more than one interface to be selected.");

			}

			var json = [];

			var current_date = new Date();
			var current_year = current_date.getFullYear();
			var current_month = current_date.getMonth();
			var current_day = current_date.getDate();
			var current_hour = current_date.getHours();

			$.each(interfaces, function(index, interface) {

				var tx_series = [];
				var rx_series = [];
				var interface_index = 0;

				switch(timePeriod) {
					case "hour":

						/*
						** get raw database dump
						*/

						$.each(raw_json.interfaces, function(index, value) {

							if(value.name === interface) {
								interface_index = index;
							}

						});

						$.each(raw_json.interfaces[interface_index].traffic.hour, function(hour_index, value) {

							var date = Date.UTC(value.date.year, value.date.month-1, value.date.day, value.time.hour);

							tx_series.push([date+((0-ServerUTCOffset)*3600000), value.tx]);
							rx_series.push([date+((0-ServerUTCOffset)*3600000), value.rx]);

						});

						/*
						** sort each series by date
						*/

						tx_series.sort(function(a,b){
							return a[0] - b[0];
						});

						rx_series.sort(function(a,b){
							return a[0] - b[0];
						});

						/*
						** get first timestamp and extroplate based on period interval, filling in the blanks
						*/

						var current_utc = Date.UTC(current_year, current_month, current_day, current_hour);
						var count = 23;

						current_utc = current_utc+(ClientUTCOffset*3600000);

						for(var t = 0; t < tx_series.length; t++) {

							//TODO break if length === period interval?

							if(tx_series[t][0] > (current_utc-(3600000*count))) {

								var index_diff = 0;

								while (tx_series[t][0] > (current_utc-(3600000*count))) {

									tx_series.splice(t+index_diff, 0, [current_utc-(3600000*count),0]);
									rx_series.splice(t+index_diff, 0, [current_utc-(3600000*count),0]);

									count--;
									index_diff++;

								}

							} else {

								count--;

							}

						}

						break;
					case "day":

						/*
						** get raw database dump
						*/

						$.each(raw_json.interfaces, function(index, value) {

							if(value.name === interface) {
								interface_index = index;
							}

						});

						$.each(raw_json.interfaces[interface_index].traffic.day, function(index, value) {

							var date = Date.UTC(value.date.year, value.date.month-1, value.date.day);

							tx_series.push([date+((0-ServerUTCOffset)*3600000), value.tx]);
							rx_series.push([date+((0-ServerUTCOffset)*3600000), value.rx]);

						});

						/*
						** sort each series by date
						*/

						tx_series.sort(function(a,b){
							return a[0] - b[0];
						});

						rx_series.sort(function(a,b){
							return a[0] - b[0];
						});

						/*
						** get first timestamp and extroplate based on period interval, filling in the blanks
						*/

						var current_utc = Date.UTC(current_year, current_month, current_day);
						var count = 29;

						current_utc = current_utc+(ClientUTCOffset*3600000);

						$.each(tx_series, function(index, value) {

							//TODO break if length === period interval?

							for(var t = 0; t < tx_series.length; t++) {

								if(tx_series[t][0]+tzOffset > (current_utc-(86400000*count)+tzOffset)) {

									var index_diff = 0;

									while (tx_series[t][0]+tzOffset > (current_utc-(86400000*count)+tzOffset)) {

										tx_series.splice(t+index_diff, 0, [current_utc-(86400000*count),0]);
										rx_series.splice(t+index_diff, 0, [current_utc-(86400000*count),0]);

										count--;
										index_diff++;

									}

								} else {

									count--;

								}

							}

						});

						break;
					case "month":

						/*
						** get raw database dump
						*/

						$.each(raw_json.interfaces, function(index, value) {

							if(value.name === interface) {
								interface_index = index;
							}

						});

						$.each(raw_json.interfaces[interface_index].traffic.month, function(index, value) {

							var date = Date.UTC(value.date.year, value.date.month-1);

							tx_series.push([date+((0-ServerUTCOffset)*3600000), value.tx]);
							rx_series.push([date+((0-ServerUTCOffset)*3600000), value.rx]);

						});

						/*
						** sort each series by date
						*/

						tx_series.sort(function(a,b){
							return a[0] - b[0];
						});

						rx_series.sort(function(a,b){
							return a[0] - b[0];
						});

						/*
						** get first timestamp and extroplate based on period interval, filling in the blanks
						*/

						var current_utc = Date.UTC(current_year, current_month);
						var addDate = new Date(current_utc+(ClientUTCOffset*3600000));
						addDate.setMonth(addDate.getMonth() - 11);
						var index_diff = 0;

						$.each(tx_series, function(index, value) {

							//TODO break if length === period interval?

							if(value[0] > addDate.getTime()) {

								var index_diff = 0;

								while (value[0] > addDate.getTime()) {

									tx_series.splice(index+index_diff, 0, [addDate.getTime(),0]);
									rx_series.splice(index+index_diff, 0, [addDate.getTime(),0]);

									addDate.setMonth(addDate.getMonth() + 1);
									index_diff++;

								}

							} else {

								addDate.setMonth(addDate.getMonth() + 1);

							}

						});

						break;
					case "top":

						/*
						** get raw database dump
						*/

						localStorage.clear();

						$.each(raw_json.interfaces, function(index, value) {

							if(value.name === interface) {
								interface_index = index;
							}

						});

						$.each(raw_json.interfaces[interface_index].traffic.top, function(index, value) {

							var date = Date.UTC(value.date.year, value.date.month-1, value.date.day);

							localStorage.setItem(index, date);

							tx_series.push([index, value.tx]);
							rx_series.push([index, value.rx]);

						});

						/*
						** fill to 10 places
						*/

						for(var i = tx_series.length+1; i <= 10; i++ ) {
							tx_series.push([i, 0]);
							rx_series.push([i, 0]);
						}

						break;
				}

				//cumulate the data over time if option selected
				if(cumulative === "true" && timePeriod != "top") {

					$.each(tx_series, function(index, value) {

						if(index === 0) {

							var tx_previous = 0;
							var rx_previous = 0;

						} else {

							var tx_previous = tx_series[index-1][1];
							var rx_previous = rx_series[index-1][1];

						}

						tx_series[index][1] = tx_series[index][1] + tx_previous;
						rx_series[index][1] = rx_series[index][1] + rx_previous;

					});

				}

				json[index*2] = {};

				var ifNick = interface;

				$.each(raw_json.interfaces, function(index, value) {

					if(value.name === interface) {
						ifNick = value.alias;
					}

				});

				json[index*2]['key'] = ifNick + " (tx)";
				json[index*2]['type'] = graphtype;
				if(graphtype === "stacked") { json[index*2]['type'] = "bar"; }
				if(graphtype === "line") { json[index*2]['area'] = true; }
				json[index*2]['invert'] = false;
				json[index*2]['values'] = tx_series;
				json[index*2]['yAxis'] = 1;

				json[index*2+1] = {};

				json[index*2+1]['key'] = ifNick + " (rx)";
				json[index*2+1]['type'] = graphtype;
				if(graphtype === "stacked") { json[index*2+1]['type'] = "bar"; }
				if(graphtype === "line") { json[index*2+1]['area'] = true; }
				if(graphtype === "area") { invert = false; } //don't invert the rx on type of line (stacked)
				json[index*2+1]['invert'] = invert;
				json[index*2+1]['values'] = rx_series;
				json[index*2+1]['yAxis'] = 1;

			});

			$("#traffic-totals-chart").show();
			$("#loading-msg").hide();

			d3.select("#traffic-totals-svg").remove(); //delete previous svg so it can be drawn from scratch
			d3.select("div[id^=nvtooltip-]").remove(); //delete previous tooltip in case it gets hung
			d3.select('#traffic-totals-chart').append('svg').attr('id', 'traffic-totals-svg'); //re-add blank svg so it and be drawn on

			var data = json;
			var offset = 0;

			//skip top, because not a date format
			if(timePeriod != "top") {
				offset = tzOffset;
			}

			data.map(function(series) {

				series.values = series.values.map(function(d) {
					if (series.invert === "true") {
						return { x: (d[0] + offset), y: 0 - d[1] }
					} else {
						return { x: (d[0] + offset), y: d[1] }
					}
				});

				return series;

			});

			nv.addGraph(function() {

				chart = nv.models.multiChart()
					.color(d3.scale.category20().range())
					.useInteractiveGuideline(true)
					.margin({top: 160, right:100, left:100, bottom: 80});

				var timePeriod = $( "li.active a:first" ).attr('href').substring(1);
				var timeFormat = timeLookup[timePeriod];

				if( timePeriod === "top" ) {
					chart.xAxis.tickFormat(d3.format('r')).tickPadding(15);
				} else {
					chart.xAxis.tickFormat(function(d) {
						return d3.time.format(timeFormat)(new Date(d));
					}).tickPadding(15);
				}

				//TODO units changes based on period?
				chart.yAxis1.tickFormat(function(d) {

					var dUnit = 'B';

					if(d >= 1000 || d <= -1000) {
						d = d / 1024;
						dUnit = 'K';
					}

					if(d >= 1000 || d <= -1000) {
						d = d / 1024;
						dUnit = 'M';
					}

					if(d >= 1000 || d <= -1000) {
						d = d / 1024;
						dUnit = 'G';
					}

					if(d >= 1000 || d <= -1000) {
						d = d / 1024;
						dUnit = 'T';
					}

					return parseFloat(d).toFixed(1) + dUnit;

				}).axisLabel('Total Traffic (Bytes)').showMaxMin(false);

				if(graphtype === "stacked") {
					chart.bars1.stacked(true);
					chart.bars2.stacked(true);
				}

				//add system name
				var systemName = '<?=htmlspecialchars($config['system']['hostname'] . "." . $config['system']['domain']); ?>';
				d3.select('#traffic-totals-chart svg')
					.append("text")
					.attr("x", 225)
					.attr("y", 415)
					.attr("id", "system-name")
					.text(systemName);

				//add time period
				var timePeriod = $( "li.active" ).text();
				d3.select('#traffic-totals-chart svg')
					.append("text")
					.attr("x", 480)
					.attr("y", 415)
					.attr("id", "time-period")
					.text("Time Period: " + timePeriod);

				//add current date
				//TODO change to updated date
				//console.log(raw_json.interfaces[0].updated);
				var currentDate = d3.time.format('%b %d, %Y %H:%M')(new Date());
				d3.select('#traffic-totals-chart svg')
					.append("text")
					.attr("x", 680)
					.attr("y", 415)
					.attr("id", "current-date")
					.text(currentDate);

				//custom tooltip contents
				chart.interactiveLayer.tooltip.contentGenerator(function(data) {

					//if Top 10 chart
					if(data.value < 1000) {
						var date = '-- --';
						if(localStorage.getItem(data.value)) {
							date = d3.time.format('%Y-%m-%d %H:%M:%S')(new Date(parseInt(localStorage.getItem(data.value))));
						}
					} else {
						var date = d3.time.format('%Y-%m-%d %H:%M:%S')(new Date(data.value));
					}

					var content = '<h3>' + date + '</h3><table><tbody>';

					for ( var v = 0; v < data.series.length; v++ ){

						var unit = 'B';

						if ( ($("#invert").val() === "true" && data.series[v].key.includes('(rx)')) &&  ($("#graph-type").val() != "area" && $("#graph-type").val() != "stacked")) {
							var trueValue = 0 - data.series[v].value;
						} else {
							var trueValue = data.series[v].value;
						}

						if(trueValue >= 1000) {
							trueValue = trueValue / 1024;
							unit = 'KiB';
						}

						if(trueValue >= 1000) {
							trueValue = trueValue / 1024;
							unit = 'MiB';
						}

						if(trueValue >= 1000) {
							trueValue = trueValue / 1024;
							unit = 'GiB';
						}

						if(trueValue >= 1000) {
							trueValue = trueValue / 1024;
							unit = 'TiB';
						}

						content += '<tr><td class="legend-color-guide"><div style="background-color: ' + data.series[v].color + '"></div></td><td>' + data.series[v].key + '</td><td class="value"><strong>' + trueValue.toFixed(2) + ' ' + unit + '</strong></td></tr>';
					}

					content += '</tbody></table>';

					return content;

				});

				d3.select('#traffic-totals-chart svg')
				   .datum(data)
				   .transition()
				   .duration(500)
				   .call(chart);

				nv.utils.windowResize(function(){
					chart.update();
				});

				return chart;

			});

			calculate_summary(json, timePeriod);

		})
		.fail(function(error) {
			$("#traffic-totals-chart").hide();
			$("#chart-error").show().html('<strong>Error</strong>: ' + error);

			console.warn(error);
		});

	}

	function calculate_summary(data, timePeriod) {

		$('#summary tbody').empty();
		$('#summary thead').empty();

		var header = '<tr><th></th><th>Time</th>';

		//grab every other key (since there is an RX and TX of each)
		for(var i = 0; i < data.length; i += 2) {
			var key = data[i].key.substring(0,data[i].key.length-5);

			header += '<th>' + key + ' TX</th><th>' + key + ' RX</th><th>' + key + ' Ratio</th><th>' + key + ' Total</th>';
		}

		header += '</tr>';

		$('#summary thead').append(header);

		//TOP 10 is displayed in a different order than the rest of the periods
		if(timePeriod === "top") {

			for ( var v = 0; v < data[0].values.length; v++ ) {

				if(parseInt(localStorage.getItem(data[0].values[v].x)) > 1000) {
					var date = new Date(parseInt(localStorage.getItem(data[0].values[v].x)));

					var year = date.getFullYear();
					var month = ("0" + (date.getMonth() + 1)).slice(-2);
					var day = ("0" + date.getDate()).slice(-2);
					var hours = ("0" + date.getHours()).slice(-2);
					var minutes = ("0" + date.getMinutes()).slice(-2);

					var body = '<tr><td>' + (v + 1) + '</td><td>' + year + "-" + month + "-" + day + ' ' + hours + ':' + minutes + '</td>';
				} else {
					var body = '<tr><td>' + (v + 1) + '</td><td>--</td>';
				}

				for(var d = 0; d < data.length; d += 2) {

					var tx = data[d].values[v].y;

					if (data[d+1].invert === "true") {
						var rx = 0 - data[d+1].values[v].y; //flip value back to positive
					} else {
						var rx = data[d+1].values[v].y;
					}

					if(rx > 0) {
						var ratio = tx / rx;
					} else {
						var ratio = 0;
					}

					var total = tx + rx;
					var txUnit = 'B';
					var rxUnit = 'B';
					var totalUnit = 'B';

					if(tx >= 1000) {
						tx = tx / 1024;
						txUnit = 'KiB';
					}

					if(tx >= 1000) {
						tx = tx / 1024;
						txUnit = 'MiB';
					}

					if(tx >= 1000) {
						tx = tx / 1024;
						txUnit = 'GiB';
					}

					if(tx >= 1000) {
						tx = tx / 1024;
						txUnit = 'TiB';
					}

					if(rx >= 1000) {
						rx = rx / 1024;
						rxUnit = 'KiB';
					}

					if(rx >= 1000) {
						rx = rx / 1024;
						rxUnit = 'MiB';
					}

					if(rx >= 1000) {
						rx = rx / 1024;
						rxUnit = 'GiB';
					}

					if(rx >= 1000) {
						rx = rx / 1024;
						rxUnit = 'TiB';
					}

					if(total >= 1000) {
						total = total / 1024;
						totalUnit = 'KiB';
					}

					if(total >= 1000) {
						total = total / 1024;
						totalUnit = 'MiB';
					}

					if(total >= 1000) {
						total = total / 1024;
						totalUnit = 'GiB';
					}

					if(total >= 1000) {
						total = total / 1024;
						totalUnit = 'TiB';
					}

					body += '<td>' + tx.toFixed(2) + ' ' + txUnit + '</td><td>' + rx.toFixed(2) + ' ' + rxUnit + '</td><td>' + ratio.toFixed(2) + '</td><td>' + total.toFixed(2) + ' ' + totalUnit + '</td>';

				}

				body += '</tr>';

				$('#summary tbody').append(body);

			}

		} else {

			for ( var v = data[0].values.length-1; v >= 0; v-- ) {

				switch(timePeriod) {
					case "hour":
						var date = new Date(data[0].values[v].x);

						var year = date.getFullYear();
						var month = date.getMonth() + 1;
						var day = date.getDate();
						var hours = date.getHours();

						var body = '<tr><td>' + (data[0].values.length - v) + '</td><td>' + hours + ':00</td>';
						break;
					case "day":
						var date = new Date(data[0].values[v].x);

						var year = date.getFullYear();
						var month = ("0" + (date.getMonth() + 1)).slice(-2);
						var day = ("0" + date.getDate()).slice(-2);
						var hours = ("0" + date.getHours()).slice(-2);

						var body = '<tr><td>' + (data[0].values.length - v) + '</td><td>' + year + "-" + month + "-" + day + '</td>';
						break;
					case "month":
						var date = new Date(data[0].values[v].x);

						var year = date.getFullYear();
						var month = ("0" + (date.getMonth() + 1)).slice(-2);
						var day = ("0" + date.getDate()).slice(-2);
						var hours = ("0" + date.getHours()).slice(-2);

						var body = '<tr><td>' + (data[0].values.length - v) + '</td><td>' + month + '/' + year + '</td>';
						break;
				}

				for(var d = 0; d < data.length; d += 2) {

					var tx = data[d].values[v].y;

					if (data[d+1].invert === "true") {
						var rx = 0 - data[d+1].values[v].y; //flip value back to positive
					} else {
						var rx = data[d+1].values[v].y;
					}

					if(rx > 0) {
						var ratio = tx / rx;
					} else {
						var ratio = 0;
					}

					var total = tx + rx;
					var txUnit = 'B';
					var rxUnit = 'B';
					var totalUnit = 'B';

					if(tx >= 1000) {
						tx = tx / 1024;
						txUnit = 'KiB';
					}

					if(tx >= 1000) {
						tx = tx / 1024;
						txUnit = 'MiB';
					}

					if(tx >= 1000) {
						tx = tx / 1024;
						txUnit = 'GiB';
					}

					if(tx >= 1000) {
						tx = tx / 1024;
						txUnit = 'TiB';
					}

					if(rx >= 1000) {
						rx = rx / 1024;
						rxUnit = 'KiB';
					}

					if(rx >= 1000) {
						rx = rx / 1024;
						rxUnit = 'MiB';
					}

					if(rx >= 1000) {
						rx = rx / 1024;
						rxUnit = 'GiB';
					}

					if(rx >= 1000) {
						rx = rx / 1024;
						rxUnit = 'TiB';
					}

					if(total >= 1000) {
						total = total / 1024;
						totalUnit = 'KiB';
					}

					if(total >= 1000) {
						total = total / 1024;
						totalUnit = 'MiB';
					}

					if(total >= 1000) {
						total = total / 1024;
						totalUnit = 'GiB';
					}

					if(total >= 1000) {
						total = total / 1024;
						totalUnit = 'TiB';
					}

					body += '<td>' + tx.toFixed(2) + ' ' + txUnit + '</td><td>' + rx.toFixed(2) + ' ' + rxUnit + '</td><td>' + ratio.toFixed(2) + '</td><td>' + total.toFixed(2) + ' ' + totalUnit + '</td>';
				}

				body += '</tr>';

				$('#summary tbody').append(body);

			}

		}

		//exampleTable = document.querySelector('#summary')
		//Sortable.initTable(exampleTable);
	}

	$( ".update-graph" ).click(function() {
		$("#traffic-totals-chart").hide();
		$("#loading-msg").show();
		$("#chart-error").hide();
		draw_graph();
	});

	$( "body > div.container > ul.nav > li" ).click(function() {
		$( "body > div.container > ul.nav li" ).removeClass("active");
		$(this).addClass("active");

		$("#time-period").val($(this).find("a").attr('href').substring(1));

		$("#traffic-totals-chart").hide();
		$("#loading-msg").show();
		$("#chart-error").hide();
		draw_graph();
		return false;
	});

	$( "#settings" ).click(function() {
		($(this).text().trim() === 'Display Advanced') ? $(this).html('<i class="fa-solid fa-cog fa-lg"></i> Hide Advanced') : $(this).html('<i class="fa-solid fa-cog fa-lg"></i> Display Advanced');
		$("#export").toggle();
		$("#defaults").toggle();
		$("#enable").toggle();
		$("#reset").toggle();
	});

	$( "#export" ).click(function() {

        var $rows = $( "#summary" ).find('tr');

        // Temporary delimiter characters unlikely to be typed by keyboard
        // This is to avoid accidentally splitting the actual contents
        var tmpColDelim = String.fromCharCode(11); // vertical tab character
        var tmpRowDelim = String.fromCharCode(0); // null character

        // actual delimiter characters for CSV format
        var colDelim = '","';
        var rowDelim = '"\r\n"';

        // Grab text from table into CSV formatted string
        var csv = '"' + $rows.map(function (i, row) {
            var $row = $(row),
                $cols = $row.find('td, th');

            return $cols.map(function (j, col) {
                var $col = $(col),
                    text = $col.text();

                return text.replace(/"/g, '""'); // escape double quotes

            }).get().join(tmpColDelim);

        }).get().join(tmpRowDelim)
            .split(tmpRowDelim).join(rowDelim)
            .split(tmpColDelim).join(colDelim) + '"';

		window.open("data:text/csv;charset=utf-8," + escape(csv));

	});

	applySettings("<?=$defaults?>");

	draw_graph();

});
//]]>
</script>

<? include("foot.inc");
