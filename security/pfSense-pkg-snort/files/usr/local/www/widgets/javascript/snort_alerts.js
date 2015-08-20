
var snorttimer;
var snortisBusy = false;
var snortisPaused = false;

if (typeof getURL == 'undefined') {
	getURL = function(url, callback) {
		if (!url)
			throw 'No URL for getURL';
		try {
			if (typeof callback.operationComplete == 'function')
				callback = callback.operationComplete;
		} catch (e) {}
			if (typeof callback != 'function')
				throw 'No callback function for getURL';
		var http_request = null;
		if (typeof XMLHttpRequest != 'undefined') {
		    http_request = new XMLHttpRequest();
		}
		else if (typeof ActiveXObject != 'undefined') {
			try {
				http_request = new ActiveXObject('Msxml2.XMLHTTP');
			} catch (e) {
				try {
					http_request = new ActiveXObject('Microsoft.XMLHTTP');
				} catch (e) {}
			}
		}
		if (!http_request)
			throw 'Both getURL and XMLHttpRequest are undefined';
		http_request.onreadystatechange = function() {
			if (http_request.readyState == 4) {
				callback( { success : true,
				  content : http_request.responseText,
				  contentType : http_request.getResponseHeader("Content-Type") } );
			}
		}
		http_request.open('GET', url, true);
		http_request.send(null);
	}
}

function snort_alerts_fetch_new_events_callback(callback_data) {
	var data_split;
	var new_data_to_add = Array();
	var data = callback_data.content;
	data_split = data.split("\n");

	// Loop through rows and generate replacement HTML
	for(var x=0; x<data_split.length-1; x++) {
		row_split = data_split[x].split("||");
		var line = '';
		line =  '<td class="listMRr">' + row_split[0] + '<br/>' + row_split[1] + '</td>';		
		line += '<td class="listMRr" style="overflow: hidden; text-overflow: ellipsis;" nowrap>';
		line += '<div style="display:inline;" title="' + row_split[2] + '">' + row_split[2] + '</div><br/>';
		line += '<div style="display:inline;" title="' + row_split[3] + '">' + row_split[3] + '</div></td>';
		line += '<td class="listMRr"><div style="display: fixed; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.2em; max-height: 2.4em; overflow: hidden; text-overflow: ellipsis;" title="' + row_split[4] + '">' + row_split[4] + '</div></td>';
		new_data_to_add[new_data_to_add.length] = line;
	}
	snort_alerts_update_div_rows(new_data_to_add);
	snortisBusy = false;
}

function snort_alerts_update_div_rows(data) {
	if(snortisPaused)
		return;

	var rows = $$('#snort-alert-entries>tr');

	// Number of rows to move by
	var move = rows.length + data.length - snort_nentries;
	if (move < 0)
		move = 0;

	for (var i = rows.length - 1; i >= move; i--) {
		rows[i].innerHTML = rows[i - move].innerHTML;
	}

	var tbody = $$('#snort-alert-entries');
	for (var i = data.length - 1; i >= 0; i--) {
		if (i < rows.length) {
			rows[i].innerHTML = data[i];
		} else {
			var newRow = document.getElementById('snort-alert-entries').insertRow(0);
			newRow.innerHTML = data[i];
		}
	}

	// Add the even/odd class to each of the rows now
	// they have all been added.
	rows = $$('#snort-alert-entries>tr');
	for (var i = 0; i < rows.length; i++) {
		rows[i].className = i % 2 == 0 ? snortWidgetRowOddClass : snortWidgetRowEvenClass;
	}
}

function fetch_new_snortalerts() {
	if(snortisPaused)
		return;
	if(snortisBusy)
		return;
	snortisBusy = true;
	getURL('/widgets/widgets/snort_alerts.widget.php?getNewAlerts=' + new Date().getTime(), snort_alerts_fetch_new_events_callback);
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
