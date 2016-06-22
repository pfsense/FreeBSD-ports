<?php
/*
	status_traffic_totals.php

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

require("guiconfig.inc");

function vnstat_write_conf($startDay = "1") {

	/* overwrite the conf file /usr/loca/etc/vnsat.conf */

	$vnstat_conf_file = <<<EOF
# vnStat 1.13 config file
##

# location of the database directory
DatabaseDir "/var/db/vnstat"

# on which day should months change
MonthRotate $startDay

# vnstati
##

# image colors
CBackground     "FFFFFF"
CEdge           "AEAEAE"
CHeader         "606060"
CHeaderTitle    "FFFFFF"
CHeaderDate     "FFFFFF"
CText           "000000"
CLine           "B0B0B0"
CLineL          "-"
CRx             "92CF00"
CTx             "606060"
CRxD            "-"
CTxD            "-"
EOF;

	$fd = fopen("/usr/local/etc/vnstat.conf", "w");
	if (!$fd) {
		log_error("Could not open /usr/local/etc/vnstat.conf for writing");
		exit;
	}
	fwrite($fd, $vnstat_conf_file);
	fclose($fd);

}

function vnstat_create_nic_dbs($portlist) {

	//TODO code that allows you to just add new interfaces (check exsisting first and compare)

	foreach($portlist as $interface => $details) {

		exec('/usr/local/bin/vnstat -u -i ' . escapeshellarg($interface) . ' --create');

	}

}

function vnstat_delete_nic_dbs($portlist) {

	foreach($portlist as $interface => $details) {

		exec('/usr/local/bin/vnstat -i ' . escapeshellarg($interface) . ' --delete --force');

	}

}

$portlist = get_interface_list();

if($_POST['enable']) {

	//disable vnstat
	if(($_POST['enable'] === 'false')) {

		//remove cron job
		install_cron_job("/usr/local/bin/vnstat -u", false);

		//loop through interfaces and delete databases
		vnstat_delete_nic_dbs($portlist);

	} else { // enable vnstat

		//overwrite vnstat conf
		vnstat_write_conf($_POST['start-day']);

		//make the directory for the nterface databases
		safe_mkdir('/var/db/vnstat');

		//setup cron job 
		install_cron_job("/usr/local/bin/vnstat -u", true, "*/5"); //TODO different intervals (delete old cron and re-add?)

		//loop through interfaces and create databases
		vnstat_create_nic_dbs($portlist);

	}

}

if ($_POST['reset']) {

	//loop through interfaces and delete databases
	vnstat_delete_nic_dbs($portlist);

	//loop through interfaces and re-create databases
	vnstat_create_nic_dbs($portlist);
	
}

//save new defaults
if ($_POST['defaults']) {

	vnstat_write_conf($_POST['start-day']);

	//TODO save defaults to config
	//$monthrotate = $config['installedpackages']['vnstat2']['config'][0]['monthrotate'];
	//write_config();
	//$savemsg = "The changes have been applied successfully.";

}

$pgtitle = array(gettext("Status"), gettext("Traffic Totals"));

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Hourly"), true, "#hour");
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
						<i class="fa fa-plus-circle"></i>
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
					<select class="form-control" id="interfaces" name="interfaces" multiple>
						<?php

						foreach($portlist as $interface => $details) {
							echo '<option value="' . $interface . '" selected>' . $details['friendly'] . "</option>\n";
						}

						?>
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
					<input type="number" class="form-control" value="" id="start-day" name="start-day" min="1" max="28" step="1">

					<span class="help-block">Start Day</span>
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
					<button class="btn btn-sm btn-primary" type="submit" value="true" name="defaults" id="defaults" style="display:none;"><i class="fa fa-save fa-lg"></i> Save Start Day</button>
				</div>
				<div class="col-sm-2">
						<button class="btn btn-sm btn-danger" type="submit" value="false" name="enable" id="enable" style="display:none;"><i class="fa fa-ban fa-lg"></i> Disable Graphing</button>
				</div>
				<div class="col-sm-2">
					<button class="btn btn-sm btn-danger" type="submit" value="true" name="reset" id="reset" style="display:none;"><i class="fa fa-trash fa-lg"></i> Reset Graphing Data</button>
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
		<h2 class="panel-title">Interactive Graph</h2>
	</div>
	<div class="panel-body">
		<div class="alert alert-info" id="loading-msg">Loading Graph...</div>
		<div id="chart-error" class="alert alert-danger" style="display: none;"></div>
		<div id="chart" class="with-3d-shadow with-transitions">
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
				<thead></thead>
				<tbody></tbody>
			</table>
		</div>
	</div>
</div>

<div class="infoblock">
	<div class="alert alert-info clearfix" role="alert">
		<div class="pull-left">
			The data for this page is pulled from vnStat.
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
	//subtracting ServerUTCOffset is a hack until setting local in config is figured out
	var tzOffset = (ClientUTCOffset + (ServerUTCOffset - ServerUTCOffset)) * 3600000;

	function applySettings(defaults) {

		var allOptions = defaults.split("&");

		allOptions.forEach(function(entry) {
			
			var currentOption = entry.split("=");

			//TODO add interfaces

			if(currentOption[0] === "timePeriod") {
				$( "#time-period" ).val(currentOption[1]).change();
			}

			if(currentOption[0] === "graphtype") {
				$( "#graph-type" ).val(currentOption[1]);
			}

			if(currentOption[0] === "invert") {
				$( "#invert" ).val(currentOption[1]);
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

				$("#chart").hide();
				$("#loading-msg").hide();
				
				//check if interface databases don't exist
				if(errorMsg.substring(0,17) === "No database found" || errorMsg.substring(0,33) === "Unable to open database directory" ) {

					//flip enable graphing button
					$( "#enable" ).val('true').html('<i class="fa fa-check fa-lg"></i> Enable Graphing').removeClass('btn-danger').addClass('btn-success');

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

			var json = [];

			$.each(interfaces, function(index, interface) {
				
				var tx_series = [];
				var rx_series = [];
				var interface_index = 0;

				var current_date = new Date();
				var current_year = current_date.getFullYear();
				var current_month = current_date.getMonth();
				var current_day = current_date.getDate();
				var current_hour = current_date.getHours();

				//TODO add tx/rx total line for each interface?
				//     if so, don't do total line for stacked line and stacked bar
				//TODO add bar stacked option

				switch(timePeriod) {
					case "hour":

						/*
						** get raw database dump
						*/

						$.each(raw_json.interfaces, function(index, value) {

							if(value.id === interface) {
								interface_index = index;
							}

						});

						$.each(raw_json.interfaces[interface_index].traffic.hours, function(hour_index, value) {

							var date = Date.UTC(value.date.year, value.date.month-1, value.date.day, value.id);

							tx_series.push([date, value.tx]);
							rx_series.push([date, value.rx]);

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

						for(var t = 0; t < tx_series.length; t++) {	

							//TODO break if length === period interval?

							if(tx_series[t][0]+tzOffset > (current_utc-(3600000*count)+tzOffset)) {

								var index_diff = 0;

								while (tx_series[t][0]+tzOffset > (current_utc-(3600000*count)+tzOffset)) {

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

							if(value.id === interface) {
								interface_index = index;
							}

						});

						$.each(raw_json.interfaces[interface_index].traffic.days, function(index, value) {

							var date = Date.UTC(value.date.year, value.date.month-1, value.date.day);

							tx_series.push([date, value.tx]);
							rx_series.push([date, value.rx]);

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
						var count = 30;

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

							if(value.id === interface) {
								interface_index = index;
							}

						});

						$.each(raw_json.interfaces[interface_index].traffic.months, function(index, value) {

							var date = Date.UTC(value.date.year, value.date.month-1);

							tx_series.push([date, value.tx]);
							rx_series.push([date, value.rx]);

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
						var addDate = new Date(current_utc+tzOffset); //add offset for comparison, but remove it later
						addDate.setMonth(addDate.getMonth() - 12);
						var index_diff = 0;

						$.each(tx_series, function(index, value) {

							//TODO break if length === period interval?

							if(value[0] > addDate.getTime()) {

								var index_diff = 0;

								while (value[0] > addDate.getTime()) {

									tx_series.splice(index+index_diff, 0, [addDate.getTime()-tzOffset,0]);
									rx_series.splice(index+index_diff, 0, [addDate.getTime()-tzOffset,0]);

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

							if(value.id === interface) {
								interface_index = index;
							}

						});

						$.each(raw_json.interfaces[interface_index].traffic.tops, function(index, value) {

							var date = Date.UTC(value.date.year, value.date.month-1, value.date.day, value.time.hour, value.time.minutes);
							
							localStorage.setItem(value.id+1, date);

							tx_series.push([value.id+1, value.tx]);
							rx_series.push([value.id+1, value.rx]);

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

				if(cumulative === "true" && timePeriod != "top") {

					$.each(tx_series, function(index, value) {

						if(index === 0) {

							var tx_previous = 0;
							var rx_previous = 0;

						} else {

							var tx_previous = tx_series[index-1][1];
							var rx_previous = rx_series[index-1][1];

						}

						console.log(tx_previous);

						tx_series[index][1] = tx_series[index][1] + tx_previous;
						rx_series[index][1] = rx_series[index][1] + rx_previous;
						
					});

				}
				
				json[index*2] = {};

				json[index*2]['key'] = interface + " (tx)";
				json[index*2]['type'] = graphtype;
				if(graphtype === "stacked") { json[index*2]['type'] = "bar"; }
				if(graphtype === "line") { json[index*2]['area'] = true; }
				json[index*2]['invert'] = false;
				json[index*2]['values'] = tx_series;
				json[index*2]['yAxis'] = 1;

				json[index*2+1] = {};

				json[index*2+1]['key'] = interface + " (rx)";
				json[index*2+1]['type'] = graphtype;
				if(graphtype === "stacked") { json[index*2+1]['type'] = "bar"; }
				if(graphtype === "line") { json[index*2+1]['area'] = true; }
				if(graphtype === "area") { invert = false; } //don't invert the rx on type of line (stacked)
				json[index*2+1]['invert'] = invert;
				json[index*2+1]['values'] = rx_series;
				json[index*2+1]['yAxis'] = 1;

			});

			$("#chart").show();
			$("#loading-msg").hide();

			d3.select("svg").remove(); //delete previous svg so it can be drawn from scratch
			d3.select("div[id^=nvtooltip-]").remove(); //delete previous tooltip in case it gets hung
			d3.select('#chart').append('svg'); //re-add blank svg so it and be drawn on

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

				chart.yAxis1.tickFormat(function(d) {
					return d3.format('.2s')(d)
				}).axisLabel('Traffic (Bits Per Second)').tickPadding(5).showMaxMin(false);

				if(graphtype === "stacked") {
					chart.bars1.stacked(true);
					chart.bars2.stacked(true);
				}

				//add system name
				var systemName = '<?=htmlspecialchars($config['system']['hostname'] . "." . $config['system']['domain']); ?>';
				d3.select('#chart svg')
					.append("text")
					.attr("x", 225)
					.attr("y", 415)
					.attr("id", "system-name")
					.text(systemName);

				//add time period
				var timePeriod = $( "li.active" ).text();
				d3.select('#chart svg')
					.append("text")
					.attr("x", 480)
					.attr("y", 415)
					.attr("id", "time-period")
					.text("Time Period: " + timePeriod);

				//add current date
				var currentDate = d3.time.format('%a %b %d %H:%M:%S %Y GMT%Z')(new Date());
				d3.select('#chart svg')
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

						if ( ($("#invert").val() === "true" && data.series[v].key.includes('(rx)')) &&  ($("#graph-type").val() != "area" && $("#graph-type").val() != "stacked")) {
							var trueValue = 0 - data.series[v].value;
						} else {
							var trueValue = data.series[v].value;
						}

						var formatted_value = d3.formatPrefix(trueValue);

						content += '<tr><td class="legend-color-guide"><div style="background-color: ' + data.series[v].color + '"></div></td><td>' + data.series[v].key + '</td><td class="value"><strong>' + formatted_value.scale(trueValue).toFixed(2) + ' ' + formatted_value.symbol + 'b/s</strong></td></tr>';
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

				return chart;

			});

			calculate_summary(json, timePeriod);

		})
		.fail(function(error) {
			$("#chart").hide();
			$("#chart-error").show().html('<strong>Error</strong>: ' + error);
			
			console.warn(error);
		});

	}

	function calculate_summary(data, timePeriod) {

		$('#summary tbody').empty();
		$('#summary thead').empty();

		var header = '<tr><th>Time</th>';

		//grab every other key (since there is an RX and TX of each)
		for(var i = 0; i < data.length; i += 2) {
			var key = data[i].key.substring(0,data[i].key.length-5);

			header += '<th>' + key + ' TX</th><th>' + key + ' RX</th><th>' + key + ' Ratio</th><th>' + key + ' Total</th>';
		}

		header += '</tr>';

		$('#summary thead').append(header);

		for ( var v = 0; v < data[0].values.length; v++ ){

			switch(timePeriod) {
				case "hour":
					var date = new Date(data[0].values[v].x);

					var year = date.getFullYear();
					var month = date.getMonth() + 1;
					var day = date.getDate();
					var hours = date.getHours();

					var body = '<tr><th>' + hours + ':00</th>';
					break;
				case "day":
					var date = new Date(data[0].values[v].x);

					var year = date.getFullYear();
					var month = ("0" + (date.getMonth() + 1)).slice(-2);
					var day = ("0" + date.getDate()).slice(-2);
					var hours = ("0" + date.getHours()).slice(-2);

					var body = '<tr><th>' + year + "-" + month + "-" + day + '</th>';
					break;
				case "month":
					var date = new Date(data[0].values[v].x);

					var year = date.getFullYear();
					var month = ("0" + (date.getMonth() + 1)).slice(-2);
					var day = ("0" + date.getDate()).slice(-2);
					var hours = ("0" + date.getHours()).slice(-2);

					var body = '<tr><th>' + month + '/' + year + '</th>';
					break;
				case "top":
					if(parseInt(localStorage.getItem(data[0].values[v].x)) > 1000) {
						var date = new Date(parseInt(localStorage.getItem(data[0].values[v].x)));

						var year = date.getFullYear();
						var month = ("0" + (date.getMonth() + 1)).slice(-2);
						var day = ("0" + date.getDate()).slice(-2);
						var hours = ("0" + date.getHours()).slice(-2);
						var minutes = ("0" + date.getMinutes()).slice(-2);

						var body = '<tr><th>' + year + "-" + month + "-" + day + ' ' + hours + ':' + minutes + '</th>';
					} else {
						var body = '<tr><th>--</th>';
					}
					
					break;
			}

			for(var d = 0; d < data.length; d += 2) {

				var tx = data[d].values[v].y;

				if (data[d+1].invert === "true") {
					var rx = 0 - data[d+1].values[v].y; //flip value back to positive
				} else {
					var rx = data[d+1].values[v].y;
				}

				var formatted_tx = d3.formatPrefix(tx);
				var tx_value = formatted_tx.scale(tx).toFixed(2) + ' ' + formatted_tx.symbol + "b";

				var formatted_rx = d3.formatPrefix(rx);
				var rx_value = formatted_rx.scale(rx).toFixed(2) + ' ' + formatted_rx.symbol + "b";

				if(rx > 0) {
					var ratio = tx / rx;
				} else {
					var ratio = 0;
				}

				var total = tx + rx;

				var formatted_total = d3.formatPrefix(total);
				var total_value = formatted_total.scale(total).toFixed(2) + ' ' + formatted_total.symbol + "b";

				body += '<td>' + tx_value + '</td><td>' + rx_value + '</td><td>' + ratio.toFixed(2) + '</td><td>' + total_value + '</td>';
			}

			body += '</tr>';

			$('#summary tbody').append(body);

		}

		$('acronym').tooltip();
	}

	$( ".update-graph" ).click(function() {
		$("#chart").hide();
		$("#loading-msg").show();
		$("#chart-error").hide();
		draw_graph();
	});

	$( "body > div.container > ul.nav > li" ).click(function() {
		$( "body > div.container > ul.nav li" ).removeClass("active");
		$(this).addClass("active");

		$("#chart").hide();
		$("#loading-msg").show();
		$("#chart-error").hide();
		draw_graph();
		return false;
	});

	$( "#settings" ).click(function() {
		($(this).text().trim() === 'Display Advanced') ? $(this).html('<i class="fa fa-cog fa-lg"></i> Hide Advanced') : $(this).html('<i class="fa fa-cog fa-lg"></i> Display Advanced');
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

	//applySettings("<?php echo $pconfig['category']; ?>");

	draw_graph();

});
//]]>
</script>

<? include("foot.inc");
