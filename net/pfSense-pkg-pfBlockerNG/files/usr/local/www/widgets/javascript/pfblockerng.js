/* pfBlockerNG update engine
 * Part of pfBlockerNG by BBCan177@gmail.com (c) 2015-2016
 * Javascript and Integration modifications by J. Nieuwenhuizen and J. Van Breedam
 */

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
				line =  '<td><small>' + row_split[0] + '</small></td>';
				line += '<td><small>' + row_split[1] + '</small></td>';
				line += '<td><small>' + row_split[2] + '</small></td>';
				line += '<td><small>' + row_split[3] + '</small></td>';
				line += '<td>' + row_split[4] + '</td></tr>';
				new_data_to_add[new_data_to_add.length] = line;
			}
		}
		if (new_data_to_add.length > 0) {
			var tbody = jQuery('#pfbNG-entries');
			tbody.html('<tr>' + new_data_to_add + '</tr>');
			$('body').popover({ selector: '[data-popover]', trigger: 'click hover', placement: 'right', delay: {show: 50, hide: 400}});
		}
	}
}

function fetch_new_pfBlockerNGcounts() {
	$.ajax({
		url: '/widgets/widgets/pfblockerng.widget.php?getNewCounts=' + new Date().getTime(),
		type: 'GET',
		dataType: 'text',
		success: function(data) {
			pfBlockerNG_fetch_new_rules_callback(data);
		}
	});
}

/* start local AJAX engine */
pfBlockerNGtimer = setInterval('fetch_new_pfBlockerNGcounts()', pfBlockerNGupdateDelay);
