/*
 * pfBlockerNG.js
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2022 BBcan177@gmail.com
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

// Called by widget (Suppression/Whitelist href links) and CustomList anchors
// Scroll to page anchor and open collapsed window
window.onload = function() {
	if (location.hash) {
		var elId = location.hash.replace('#','');
		var scrollToEl = document.getElementById(elId);
		scrollToEl.scrollIntoView(true);

		// Toggle Collapsible window
		$("[id$='customlist_panel-body']").removeClass('out').addClass('in');
	}
}

var asnarray = [];
// Collect ASN list and format for Autocomplete for Source (URL) field lookup
function pfb_collect_asn_list() {

	$.ajax(
		{
			type: 'GET',
			url: asnlist,
			dataType: 'text',
			success: function(data) {
				var lines = data.split("\n");
				for (var x=0; x < lines.length-1; x++) {
					asnarray.push(lines[x]);
				}
				pfb_autocomplete();
			}
		}
	);
}

// 'GeoIP ISOs'/ASNs Auto-Complete for Source (URL) field lookup
function pfb_autocomplete_function(input_type, destroy, pageload) {

	var rowid = input_type.attr('id').split('-')[1];
	var url_fld = '#url-' + rowid;
	var hdr_fld = '#header-' + rowid;

	if (input_type.val() == 'geoip') {

		// clear source field when clicking 'geoip' selectbox
		if (!pageload) {
			$(url_fld).val('');
			$(hdr_fld).val('');
		}

		$(url_fld).autocomplete( {
			minLength: 0,
			source: geoiparray,
			select: function(event,ui) {
					$(url_fld).val('');
					$(hdr_fld).val('');
				},
			change: function(event,ui) {
					if (ui.item == null) {
						$(url_fld).val('');
						$(hdr_fld).val('');
						$(url_fld).focus();
					}
					else {
						var header = ui.item.label.split(' ')[0];
						$(hdr_fld).val(header);
					}
			}
		})
	}

	else if (input_type.val() == 'asn') {

		$(url_fld).autocomplete( {
			minLength: 3,
			delay: 100,
			source: asnarray,
			select: function(event,ui) {
					$(url_fld).val('');
					$(hdr_fld).val('');
				},
			change: function(event,ui) {
					if (ui.item == null) {
						$(url_fld).val('');
						$(hdr_fld).val('');
						$(url_fld).focus();
					}
					else {
						var header = ui.item.label.split(' ')[0];
						$(hdr_fld).val(header);
					}
				}
		});
	}

	else if (destroy && $(url_fld).data('ui-autocomplete')) {
		$(url_fld).autocomplete('destroy');
		$(url_fld).removeData('autocomplete');
	}
}

// 'GeoIP ISOs'/ASNs Auto-Complete for Source (URL) field lookup
function pfb_autocomplete() {

	$("[id^='format-']").each(function() {
		pfb_autocomplete_function($(this), false, true);		// on page load

		$(this).click(function() {
			pfb_autocomplete_function($(this), true, false);	// on click
		})
	});
}


// Remove label columns that contain 'XXXX' (To gain full page width)
function pfb_remove_label() {

	$('label[class="col-sm-2 control-label"]').each(function() {
		$("label:contains('XXXX')").remove();
	});
}


// Greyout 'Disabled' State fields
function pfb_chg_state_bkgd() {

	$("select[id^='state-']").click(function() {
		if ($(this).val() == 'Disabled') {
			$(this).css('background-color', 'lightgrey');
		} else {
			$(this).css('background-color', '');
		}
	});
	$("select[id^='state-']").each(function() {
		if ($(this).val() == 'Disabled') {
			$(this).css('background-color', 'lightgrey');
		}
	});
}


events.push(function() {

	// Disable the 'Row move anchor' when adding a new Feed or whole Alias/Group; until a config save
	if ((pagetype == 'dnsbl' || pagetype == 'advanced') && disable_move) {
		$('[name^=Lmove]').each(function () {
			$(this).prop('disabled', true);
			$(this).attr('title', 'Save changes before Row move allowed!');
		})
		$('[name^=Xmove]').each(function () {
			$(this).prop('disabled', true);
			$(this).attr('title', '');
		})
	}

	pfb_remove_label();

	// Greyout 'Disabled' Action fields
	$("select[id^='action']").click(function() {
		if ($(this).val() == 'Disabled') {
			$(this).css('background-color', 'lightgrey');
		} else {
			$(this).css('background-color', '');
		}
	});
	$("select[id^='action']").each(function() {
		if ($(this).val() == 'Disabled') {
			$(this).css('background-color', 'lightgrey');
		}
	});

	// Greyout 'Disabled' State fields
	pfb_chg_state_bkgd();
	$('#addrow').click(function() {
		pfb_chg_state_bkgd();
	});

	// Change all 'state' fields to 'Enabled'
	$('#chgstate').click(function() {

		if (action.length > 0) {
			$('#act').val(action);
		}
		if (atype.length > 0) {
			$('#atype').val(atype);
		}
		$('#chgstate').val('Enable All');
	});

	if (pagetype == 'dnsbl') {

		$('#addrow').click(function() {
			$('.repeatable:last').find('sub').each(function() {
				$(this).append('&nbsp; ').text(Number($(this).text()) + 1 );
			});
			pfb_remove_label();
		})
	}
	else if (pagetype == 'advanced') {

		if (geoiparray != 'disabled') {
			pfb_collect_asn_list();
			$('#addrow').click(function() {
				pfb_autocomplete();

				$('.repeatable:last').find('sub').each(function() {
					$(this).append('&nbsp; ').text(Number($(this).text()) + 1 );
				});
				pfb_remove_label();
			})
		}

		// Lock user-input when Disabled
		function enable_change_port_in() {
			var endis = ! $('#autoports_in').prop('checked');
			document.getElementById('aliasports_in').disabled = endis;
		}
		function enable_change_port_out() {
			var endis = ! $('#autoports_out').prop('checked');
			document.getElementById('aliasports_out').disabled = endis;
		}

		function enable_change_in() {
			var endis = ! $('#autoaddr_in').prop('checked');
			document.getElementById('aliasaddr_in').disabled = endis;
			document.getElementById('autonot_in').disabled = endis;
		}
		function enable_change_out() {
			var endis = ! $('#autoaddr_out').prop('checked');
			document.getElementById('aliasaddr_out').disabled = endis;
			document.getElementById('autonot_out').disabled = endis;
		}

		$('#autoports_in').click(function() {
			enable_change_port_in();
		});
		$('#autoports_out').click(function() {
			enable_change_port_out();
		});

		$('#autoaddr_in').click(function() {
			enable_change_in();
		});
		$('#autoaddr_out').click(function() {
			enable_change_out();
		});

		enable_change_in();
		enable_change_out();
		enable_change_port_in();
		enable_change_port_out();

		// Auto-Complete for Adv. In/Out Ports Select boxes
		$('#aliasports_in').autocomplete( {
			source: portsarray
		})
		$('#aliasports_out').autocomplete( {
			source: portsarray
		})

		// Auto-Complete for Adv. In/Out Address Select boxes
		$('#aliasaddr_in').autocomplete( {
			source: networksarray
		})
		$('#aliasaddr_out').autocomplete( {
			source: networksarray
		})
	}
});
