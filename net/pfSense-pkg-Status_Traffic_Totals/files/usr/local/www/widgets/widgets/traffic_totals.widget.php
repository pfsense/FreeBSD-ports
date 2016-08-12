<?php
/*
 * traffic_totals.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2016 Andrey Nikitin
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
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
