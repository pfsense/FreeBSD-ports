<?php
/*
 * traffic_totals.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016 Andrey Nikitin
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
 
$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/traffic_totals.inc");

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {
	
	$ifdescrs = get_configured_interface_with_descr();
	
	print("<thead>");
	print(	"<tr>");
	print(		"<th>" . gettext('Interface') . "</th>");
	print(		"<th>" . gettext('Day In') . "</th>");
	print(		"<th>" . gettext('Day Out') . "</th>");
	print(		"<th>" . gettext('Month In') . "</th>");
	print(		"<th>" . gettext('Month Out') . "</th>");
	print(	"</tr>");
	print("</thead>");
	
	print(	"<tbody>");	
	
	foreach ($ifdescrs as $ifdescr => $ifname) {
		
		$ifinfo = get_interface_info($ifdescr);
		$hwifname = $ifinfo['hwif'];
		
		$exec_out = exec("vnstat --oneline --iface " . $hwifname);
		$out_array = split(";", $exec_out);
		
		print(	"<tr>");
		print(		"<td>" . $ifname . " (" . $hwifname . ")</td>");
		print(		"<td>" . $out_array[3] . "</td>");
		print(		"<td>" . $out_array[4] . "</td>");
		print(		"<td>" . $out_array[8] . "</td>");
		print(		"<td>" . $out_array[9] . "</td>");
		print(	"</tr>");
		
	}
	
	print(	"</tbody>");
	exit;
}

?>
<table id="traffic-totals" class="table table-striped table-hover">
	<tr><td><?=gettext("Retrieving traffic data")?></td></tr>
</table>

<script type="text/javascript">
//<![CDATA[

	function update_traffic_widget() {
		var ajaxRequest;

		ajaxRequest = $.ajax({
				url: "/widgets/widgets/traffic_totals.widget.php",
				type: "post",
				data: { ajax: "ajax"}
			});

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			$('#traffic-totals').html(response);

			// and do it again
			setTimeout(update_traffic_widget, 5000);
		});
	}

	events.push(function(){
		update_traffic_widget();
	});
//]]>
</script>	
