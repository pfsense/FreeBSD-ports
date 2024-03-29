/*
 * snort_alerts.js
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2014-2016 Bill Meeks
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

var snorttimer;
var snortisBusy = false;
var snortisPaused = false;

function snort_alerts_fetch_new_events_callback(callback_data) {
	var data_split;
	var new_data_to_add = Array();
	data_split = callback_data.split("\n");

	// Loop through rows and generate replacement HTML
	for(var x=0; x<data_split.length-1; x++) {
		row_split = data_split[x].split("||");
		var line = '';
		line =  '<td style="overflow: hidden; text-overflow: ellipsis;" nowrap>';
		line += row_split[0] + '<br/>' + row_split[1] + '</td>';		
		line += '<td style="overflow: hidden; text-overflow: ellipsis;" nowrap>';
		line += '<div style="display:inline;" title="' + row_split[2] + '">' + row_split[2] + '</div><br/>';
		line += '<div style="display:inline;" title="' + row_split[3] + '">' + row_split[3] + '</div></td>';
		line += '<td><div style="display: fixed; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.2em; max-height: 2.4em; overflow: hidden; text-overflow: ellipsis;" title="' + row_split[4] + '">' + row_split[4] + '</div></td>';
		new_data_to_add[new_data_to_add.length] = line;
	}
	snort_alerts_update_div_rows(new_data_to_add);
	snortisBusy = false;
}

function snort_alerts_update_div_rows(data) {
	if(snortisPaused)
		return;

	var rows = $('#snort-alert-entries>tr');

	// Number of rows to move by
	var move = rows.length + data.length - snort_nentries;
	if (move < 0)
		move = 0;

	for (var i = rows.length - 1; i >= move; i--) {
		rows[i].innerHTML = rows[i - move].innerHTML;
	}

	var tbody = $('#snort-alert-entries');
	for (var i = data.length - 1; i >= 0; i--) {
		if (i < rows.length) {
			rows[i].innerHTML = data[i];
		} else {
			var newRow = document.getElementById('snort-alert-entries').insertRow(0);
			newRow.innerHTML = data[i];
		}
	}
}

function fetch_new_snortalerts() {
	if(snortisPaused)
		return;
	if(snortisBusy)
		return;
	snortisBusy = true;

	$.ajax({
		url: '/widgets/widgets/snort_alerts.widget.php',
		type: 'GET',
		data: {
			getNewAlerts: new Date().getTime()
		      },
		success: snort_alerts_fetch_new_events_callback
	});
}

function snort_alerts_toggle_pause() {
	if(snortisPaused) {
		snortisPaused = false;
		fetch_new_snortalerts();
	} else {
		snortisPaused = true;
	}
}
/* start local AJAX engine */
snorttimer = setInterval('fetch_new_snortalerts()', snortupdateDelay);
