<?php
/*
 * status_chrony.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2026 Denny Page
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


// Handle request for chronyc data
if ($_REQUEST['data']) {
	$request = $_REQUEST['data'];
	switch ($request) {
		case 'tracking':
			$data = `/usr/local/bin/chronyc tracking -v`;
			break;
		case 'sources':
			$data = `/usr/local/bin/chronyc sources -v`;
			break;
		case 'sourcestats':
			$data = `/usr/local/bin/chronyc sourcestats -v`;
			break;
		case 'selectdata':
			$data = `/usr/local/bin/chronyc selectdata -v`;
			break;
		case 'authdata':
			$data = `/usr/local/bin/chronyc authdata -v`;
			break;
		case 'ntpdata':
			$data = `/usr/local/bin/chronyc ntpdata`;
			break;
		case 'clients':
			$data = `/usr/local/bin/chronyc clients -k`;
			break;
		}

	echo $request . "\n";
	echo trim($data, "\n");
	exit;
}

require_once("guiconfig.inc");

$shortcut_section = 'chrony';

$pgtitle = array(gettext('Status'), gettext('Chrony Status'));
include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[
	refresh_id = 0;
	refresh_lock = false;

	// Sort a data array leaving headers in place
	function sortDataArray(dataArray, headerCount) {
		var headerArray = [];
		for (var i = 0; i < headerCount; i++) {
			headerArray.unshift(dataArray.shift());
		}
		dataArray.sort();
		for (var i = 0; i < headerCount; i++) {
			dataArray.unshift(headerArray[i]);
		}
		return dataArray;
	}

	// Handle a data response
	function handleResponse(data) {
		var dataArray = data.split("\n");
		var request = dataArray.shift();
		var panel = '#chrony_' + request + '_data';

		if (request == 'clients') {
			dataArray = sortDataArray(dataArray, 2);
		}
		$(panel).html(dataArray.join("\n"));
	}

	// Make a data request
	function reqestData(request) {
		$.ajax(
			'/status_chrony.php',
			{
				type: 'post',
				data: {
					data: request
				},
				success: function(data) {
					handleResponse(data);
				},
			}
		);
	}

	// Refresh all uncollapsed panels
	function refreshData() {
		if (refresh_lock) {
			return;
		}
		refresh_lock = true;
		if ($('#chrony_tracking_body').hasClass('in')) {
			reqestData('tracking');
		}
		if ($('#chrony_sources_body').hasClass('in')) {
			reqestData('sources');
		}
		if ($('#chrony_sourcestats_body').hasClass('in')) {
			reqestData('sourcestats');
		}
		if ($('#chrony_selectdata_body').hasClass('in')) {
			reqestData('selectdata');
		}
		if ($('#chrony_authdata_body').hasClass('in')) {
			reqestData('authdata');
		}
		if ($('#chrony_ntpdata_body').hasClass('in')) {
			reqestData('ntpdata');
		}
		if ($('#chrony_clients_body').hasClass('in')) {
			reqestData('clients');
		}
		refresh_lock = false;
	}

	// Set the refresh interval and perform an immediate refresh if appropriate
	function setRefreshInterval(force) {
		if (refresh_id) {
			clearInterval(refresh_id);
			refresh_id = 0;
		}
		var interval = $("#refreshinterval").val();
		if (force|| interval > 0) {
			refreshData();
		}
		if (interval > 0) {
			refresh_id = setInterval('refreshData()', interval);
		}
	}

	events.push(function() {
		// Set the initial refresh interval
		setRefreshInterval(true);

		// Refresh control
		$('#refreshinterval').on('change', function() {
			setRefreshInterval(false);
		});
		$( ".update-now" ).click(function() {
			setRefreshInterval(true);
		});

		// When a panel is un-collapsed, request data immediately
		$('#chrony_tracking_body').on('show.bs.collapse', function () {
			reqestData('tracking');
		});
		$('#chrony_sources_body').on('show.bs.collapse', function () {
			reqestData('sources');
		});
		$('#chrony_sourcestats_body').on('show.bs.collapse', function () {
			reqestData('sourcestats');
		});
		$('#chrony_selectdata_body').on('show.bs.collapse', function () {
			reqestData('selectdata');
		});
		$('#chrony_authdata_body').on('show.bs.collapse', function () {
			reqestData('authdata');
		});
		$('#chrony_ntpdata_body').on('show.bs.collapse', function () {
			reqestData('ntpdata');
		});
		$('#chrony_clients_body').on('show.bs.collapse', function () {
			reqestData('clients');
		});
	});
//]]>
</script>

<div class="panel panel-default">
<div class="panel-heading"><h2 class="panel-title"><?=gettext("Refresh"); ?></h2></div>
	<div class="panel-body table-responsive">
		<div class="form-group" style="height: 45px;">
			<div class="col-sm-1">
				<label class="control-label">Automatic:</label>
			</div>
			<div class="col-sm-3">
				<select id="refreshinterval" class="form-control">
					<option value="0"><?=gettext("Disabled");?></option>
					<option value="5000">5 <?=gettext("seconds");?></option>
					<option value="10000" selected>10 <?=gettext("seconds");?></option>
					<option value="15000">15 <?=gettext("seconds");?></option>
					<option value="20000">20 <?=gettext("seconds");?></option>
					<option value="30000">30 <?=gettext("seconds");?></option>
					<option value="60000">60 <?=gettext("seconds");?></option>
				</select>
			</div>
			<div class="col-sm-1">
				<button class="btn btn-sm btn-primary update-now" type="button"><i class="fa-solid fa-arrows-rotate fa-lg"></i>Refresh Now</button>
			</div>
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('Tracking')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#chrony_tracking_body">
					<i class="fa-solid fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="chrony_tracking_body" class="panel panel-body collapse in">
		<pre id="chrony_tracking_data"><?=gettext("Gathering information, please wait...")?></pre>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('Sources')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#chrony_sources_body">
					<i class="fa-solid fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="chrony_sources_body" class="panel panel-body collapse in">
		<pre id="chrony_sources_data"><?=gettext("Gathering information, please wait...")?></pre>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('Source Stats')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#chrony_sourcestats_body">
					<i class="fa-solid fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="chrony_sourcestats_body" class="panel panel-body collapse in">
		<pre id="chrony_sourcestats_data"><?=gettext("Gathering information, please wait...")?></pre>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('Select Data')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#chrony_selectdata_body">
					<i class="fa-solid fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="chrony_selectdata_body" class="panel panel-body collapse in">
		<pre id="chrony_selectdata_data"><?=gettext("Gathering information, please wait...")?></pre>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('Auth Data')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#chrony_authdata_body">
					<i class="fa-solid fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="chrony_authdata_body" class="panel panel-body collapse">
		<pre id="chrony_authdata_data"><?=gettext("Gathering information, please wait...")?></pre>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('NTP Data')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#chrony_ntpdata_body">
					<i class="fa-solid fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="chrony_ntpdata_body" class="panel panel-body collapse">
		<pre id="chrony_ntpdata_data"><?=gettext("Gathering information, please wait...")?></pre>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('Clients')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#chrony_clients_body">
					<i class="fa-solid fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="chrony_clients_body" class="panel panel-body collapse">
		<pre id="chrony_clients_data"><?=gettext("Gathering information, please wait...")?></pre>
	</div>
</div>

<?php include("foot.inc");
