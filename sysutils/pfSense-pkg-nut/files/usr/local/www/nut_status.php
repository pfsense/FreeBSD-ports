<?php
/*
	nut_status.php

	part of pfSense (http://www.pfSense.org/)
	Copyright (C) 2015 ESF, LLC
	Copyright (C) 2016 Denny Page
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/


require("guiconfig.inc");
require("/usr/local/pkg/nut/nut.inc");

function print_row($desc, $value) {
	print '<tr>';
	print '  <td style="width:35%"><b>' . $desc . ':</b></td>';
	print '  <td>' . htmlspecialchars($value) . '</td>';
	print '</tr>';
}

function print_row_pct($desc, $value) {
	print '<tr>';
	print '  <td style="width:35%"><b>' . $desc . ':</b></td>';
	print '  <td>';
	print '    <div class="progress" style="max-width:300px">';
	print '      <div class="progress-bar progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $value . '" style="width: ' . $value . '%">';
	print '	     </div>';
	print '	   </div>';
	print      htmlspecialchars($value) . '%';
	print '  </td>';
	print '</tr>';
}


$pgtitle = array(gettext("Services"), gettext("UPS"), gettext("Status"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("UPS Status"), true, "/nut_status.php");
$tab_array[] = array(gettext("UPS Settings"), false, "/nut_settings.php");
display_top_tabs($tab_array);


$status = nut_ups_status();
if ($status['_alert']) {
	print_info_box("Status Alert: The UPS requires attention", "alert-danger");
}

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("UPS Status")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
<?php
				print_row(gettext("Name"), $status['_name']);
				print_row(gettext("Summary status"), $status['_summary']);
				if (isset($status['ups.alarm'])) {
					print_row(gettext("Alarm"), $status['ups.alarm']);
				}
				if (isset($status['_hms'])) {
					print_row(gettext("Runtime (H:M:S)"), $status['_hms']);
				}
				if (isset($status['ups.load'])) {
					print_row_pct(gettext("UPS Load"), $status['ups.load']);
				}
				if (isset($status['battery.charge'])) {
					print_row_pct(gettext("Battery charge"), $status['battery.charge']);
				}
				if (isset($status['battery.voltage'])) {
					print_row(gettext("Battery voltage"), $status['battery.voltage']);
				}
				if (isset($status['input.voltage'])) {
					print_row(gettext("Input voltage"), $status['input.voltage']);
				}
				if (isset($status['input.frequency'])) {
					print_row(gettext("Input frequency"), $status['input.frequency']);
				}
				if (isset($status['ups.test.result'])) {
					print_row(gettext("Last test result"), $status['ups.test.result']);
				}
?>
			</table>
		</div>
	</div>
</div>


<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("UPS Detail")?></h2></div>
	<div class="panel-body">
		<div class="container-fluid">
			<br>
			This table contains all the variables reported by the UPS via the upsc command. Note that many UPSs report only a subset of the available variables. Also note that some UPSs, particularly those using usbhid, may report an incorrect value for a variable. This is generally not a cause for concern. For additional information, see the <a target="_blank" href="http://networkupstools.org/">Network UPS Tools site</a>
			<br><br>
		</div>
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<tr>
					<td><b>Variable</b></td>
					<td><b>Value</b></td>
				</tr>
<?php
				foreach($status as $key =>  $value) {
					if ($key[0] == '_') {
						continue;
					}
					print '<tr>';
					print '<td style="width:35%">' . htmlspecialchars($key) . '</td>';
					print '<td>' . htmlspecialchars($value) . '</td>';
					print '</tr>';
				}
?>
	
			</table>
		</div>
	</div>
</div>

<?php
/* If there is a status error, reload the page after 10 seconds */
if ($status['_alert'] || count($status) <= 2) {
	print "<script type=\"text/javascript\">\n";
	print "//<![CDATA[\n";
	print "setTimeout(function(){ window.location.reload(1); }, 10000);\n";
	print "//]]>\n";
	print "</script>\n";
}

include("foot.inc");
?>
