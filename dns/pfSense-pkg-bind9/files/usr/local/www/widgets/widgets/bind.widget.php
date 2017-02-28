<?php
/*
 * bind.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2017 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Marcello Coutinho
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

require_once("guiconfig.inc");

function open_table() {
	echo '<div class="table-responsive">';
	echo '<table class="table table-striped table-hover">';
	echo '<tbody>';
	echo '<tr>';
}

function close_table() {
	echo '</tr>';
	echo '</tbody>';
	echo '</table>';
	echo '</div>';
}

?>

<div id="bind">
	<?php
	$rndc_bin = "/usr/local/sbin/rndc";

	if (is_executable($rndc_bin)) {
		exec("$rndc_bin -c /cf/named/etc/namedb/rndc.conf status", $status);
	}

	open_table();
	foreach ($status as $line) {
		$fields = explode(":", $line);
		print '<tr><td><strong>' . ucfirst($fields[0]) . '</strong></td>';
		print '<td>' . $fields[1] . '</td></tr>';
	}
	close_table();
	?>
</div>
<script type="text/javascript">
//<![CDATA[
	function getstatus_bind() {
		var url = "/widgets/widgets/bind.widget.php";
		var pars = 'getupdatestatus=yes';
		var myAjax = $.ajax({
				url: url,
				type: "get",
				data: pars,
				complete: activitycallback_bind
			});
	}

	function activitycallback_bind(transport) {
		$('bind').innerHTML = transport.responseText;
		setTimeout('getstatus_bind()', 5000);
	}
events.push(function(){
	getstatus_bind();
});
//]]>
</script>
