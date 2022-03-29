/*
 * pfblockerng.js
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2022 BBcan177@gmail.com
 * All rights reserved.
 *
 * Javascript and Integration modifications by J. Nieuwenhuizen and J. Van Breedam
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

var pfBlockerNGFailedTimer;
var pfBlockerNGWidgetTimer;

/* update timers (10000 ms = 10 seconds, 60000 ms = 1 minute, 300000 ms = 5 mins) */
var pfBlockerNGupdateFailedDelay	= 300000;
var pfBlockerNGupdateWidgetDelay	= 10000;

function pfBlockerNG_fetch_new_failed_callback(callback_data) {
	if (callback_data.length > 0) {
		var divfailed = jQuery('#pfBNG-failed');
		divfailed.html(callback_data);
	}
}

function pfBlockerNG_fetch_new_widget_callback(callback_data) {
	var data_split;
	var data = callback_data;
	data_split = data.split("\n");
	var new_data_to_add = Array();

	// Loop through rows and generate replacement HTML
	if (data_split.length > 1) {
		for (var x=0; x < data_split.length-1; x++) {
			row_split = data_split[x].split("||");
			var row_cnt = row_split.length;
			if (row_cnt < 4) {
				if (row_split[2] == '-') {
					$('.pfb_' + row_split[0]).html(row_split[1]);
				} else if (row_split[2] == 'title') {
					row_split[1] = row_split[1].replaceAll('_BR_', '\n');
					$('.pfb_title_' + row_split[0]).attr('title', row_split[1]);
				} else if (row_split[0] == 'PFBSTATUS') {
					$('.PFBSTATUS').attr('class', row_split[1]).prop('title', row_split[2]);
				} else if (row_split[0] == 'DNSBLSTATUS') {
					$('.DNSBLSTATUS').attr('class', row_split[1]).prop('title', row_split[2]);
				}
			}
			else if (row_cnt > 4) {
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
			var tbody = $('#pfBNG-table');
			tbody.html('<tr>' + new_data_to_add + '</tr>');
			$('body').popover({ selector: '[data-popover]', trigger: 'click hover', placement: 'right', delay: {show: 50, hide: 400}});
			$('#pfB_col1, #pfB_col2, #pfB_col3, #pfB_col4').attr("data-sorted", false);
		}
	}
}


function fetch_new_pfBlockerNG_failed() {
	$.ajax({
		url: '/widgets/widgets/pfblockerng.widget.php?getNewFailed=' + new Date().getTime(),
		type: 'GET',
		dataType: 'text',
		success: function(data) {
			pfBlockerNG_fetch_new_failed_callback(data);

			$('[id^=pfblockerngackicon]').click(function(event) {
				$('#pfblockerngack').val('true');
				$('#formicons').submit();
			});
		}
	});
}

function fetch_new_pfBlockerNG_widget() {
	$.ajax({
		url: '/widgets/widgets/pfblockerng.widget.php?getNewWidget=' + new Date().getTime(),
		type: 'GET',
		dataType: 'text',
		success: function(data) {
			pfBlockerNG_fetch_new_widget_callback(data);
		}
	});
}

/* start local AJAX engine */
pfBlockerNGFailedTimer	= setInterval('fetch_new_pfBlockerNG_failed()', pfBlockerNGupdateFailedDelay);
pfBlockerNGWidgetTimer	= setInterval('fetch_new_pfBlockerNG_widget()', pfBlockerNGupdateWidgetDelay);

events.push(function() {

	// Keep popover open on mouseover
	// Reference: http://jsfiddle.net/wojtekkruszewski/zf3m7/22/
	var originalLeave = $.fn.popover.Constructor.prototype.leave;
	$.fn.popover.Constructor.prototype.leave = function(obj) {
		var self = obj instanceof this.constructor ?
			obj : $(obj.currentTarget)[this.type](this.getDelegateOptions()).data('bs.' + this.type)
		var container, timeout;

		originalLeave.call(this, obj);

		if (obj.currentTarget) {
			container = $(obj.currentTarget).siblings('.popover')
			timeout = self.timeout;
			container.one('mouseenter', function() {

				// We entered the actual popover - call off the dogs
				clearTimeout(timeout);

				// Increase pfBNG refresh intervals
				var pfBlockerNGupdateFailedDelay	= 90000;
				var pfBlockerNGupdateWidgetDelay	= 90000;

				clearInterval(pfBlockerNGFailedTimer);
				clearInterval(pfBlockerNGWidgetTimer);

				// Let's monitor popover content instead
				container.one('mouseleave', function(){
					$.fn.popover.Constructor.prototype.leave.call(self, self);

					// Reset pfBNG refresh intervals
					var pfBlockerNGupdateFailedDelay	= 300000;
					var pfBlockerNGupdateWidgetDelay	= 10000;
	
					clearInterval(pfBlockerNGFailedTimer);
					clearInterval(pfBlockerNGWidgetTimer);

					pfBlockerNGFailedTimer	= setInterval('fetch_new_pfBlockerNG_failed()', pfBlockerNGupdateFailedDelay);
					pfBlockerNGWidgetTimer	= setInterval('fetch_new_pfBlockerNG_widget()', pfBlockerNGupdateWidgetDelay);
				});
			})
		}
	};
	$('body').popover({ selector: '[data-popover]', trigger: 'click hover', placement: 'right', delay: {show: 50, hide: 400}});

	$('[id^=pfblockerngackicon]').click(function(event) {
		$('#pfblockerngack').val('true');
		$('#formicons').submit();
	});

	$('[id^=pfblockerngclearicon]').click(function(event) {
		$('<div></div>').appendTo('body')
		.html('<div><h6>Select which Packet Counts to clear:</h6><small>Note: Selecting \'IP\' will clear all pfSense counters.</small></div>')
		.dialog({
			modal: true,
			autoOpen: true,
			resizable: false,
			closeOnEscape: true,
			width: 'auto',
			title: 'Clear Packet Counts:',
			position: { my: 'top+50px', at: 'top' },
			buttons: {
				All: function () {
					$('#pfblockerngclearall').val(true);
					$(this).dialog("close");
					$('#formicons').submit();
				},
				IP: function () {
					$('#pfblockerngclearip').val(true);
					$(this).dialog("close");
					$('#formicons').submit();
				},
				DNSBL: function () {
					$('#pfblockerngcleardnsbl').val(true);
					$(this).dialog("close");
					$('#formicons').submit();
				},
				Cancel: function (event, ui) {
					$(this).dialog("close");
				}
			}
		}).css('background-color','#ffd700');
		$("div[role=dialog]").find('button').addClass('btn-info btn-xs');
	});
});
