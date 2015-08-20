/* pfBlockerNG update engine */

// Part of pfBlockerNG by BBCan177@gmail.com (c) 2015
//
// Javascript and Integration modifications by J. Nieuwenhuizen


var pfBlockerNGtimer;

function pfBlockerNG_fetch_new_rules_callback(callback_data) {
	var data_split;
	var new_data_to_add = Array();
	var data = callback_data;

	data_split = data.split("\n");

	// Loop through rows and generate replacement HTML
	if (data_split.length > 1) {
		for(var x=0; x<data_split.length-1; x++) {
			row_split = data_split[x].split("||");
			if (row_split.length > 3) {
				var line = '';
				line =  '<td class="listMRr ellipsis">' + row_split[0] + '</td>';
				line += '<td class="listMRr" align="center">' + row_split[1] + '</td>';
				line += '<td class="listMRr" align="center">' + row_split[2] + '</td>';
				line += '<td class="listMRr" align="center">' + row_split[3] + '</td>';
				line += '<td class="listMRr" align="center">' + row_split[4] + '</td>';
				new_data_to_add[new_data_to_add.length] = line;
			}
		}
		if (new_data_to_add.length > 0) {
			pfBlockerNG_update_div_rows(new_data_to_add);
		}
	}
}


function pfBlockerNG_update_div_rows(data) {
	var rows = jQuery('#pfbNG-entries>tr');

	// Number of rows to move by
	var move = rows.length + data.length;
	if (move < 0)
		move = 0;

	for (var i = rows.length - 1; i >= move; i--) {
		jQuery(rows[i]).html(jQuery(rows[i - move]).html());
	}

	var tbody = jQuery('#pfbNG-entries');
	for (var i = data.length - 1; i >= 0; i--) {
		if (i < rows.length) {
			jQuery(rows[i]).html(data[i]);
		} else {
			jQuery(tbody).prepend('<tr>' + data[i] + '</tr>');
		}
	}

	// Add the even/odd class to each of the rows now
	// they have all been added.
	rows = jQuery('#pfbNG-entries>tr');
	for (var i = 0; i < rows.length; i++) {
		rows[i].className = i % 2 == 0 ? 'listMRodd' : 'listMReven';
	}
}


function fetch_new_pfBlockerNGcounts() {
	jQuery.ajax('/widgets/widgets/pfblockerng.widget.php?getNewCounts=' + new Date().getTime(), {
		type: 'GET',
		dataType: 'text',
		success: function(data) {
			pfBlockerNG_fetch_new_rules_callback(data);
		}
	});
}

/* start local AJAX engine */
pfBlockerNGtimer = setInterval('fetch_new_pfBlockerNGcounts()', pfBlockerNGupdateDelay);