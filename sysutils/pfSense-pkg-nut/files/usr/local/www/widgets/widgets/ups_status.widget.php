<?php
/*
	ups_status.widget.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2015 SunStroke <andrey.b.nikitin@gmail.com>
	Copyright (C) 2015 ESF, LLC
	Copyright (C) 2016 PiBa-NL
	Copyright (C) 2016 Sander Peterse <sander.peterse88@gmail.com>
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
require_once("guiconfig.inc");
require_once("/usr/local/www/widgets/include/ups_status.inc");
require_once('nut.inc');

//called by showUPSData() (jQuery Ajax call) in ups_status.js
if (isset($_GET["getUPSData"])) {
	//get UPS data and return it in ajax response
	echo getUPSData();
	return;
}

function getUPSData() {
	$nut = nut_get_data();
	$data = "";
	$ups = $nut['status'];
	$data = $nut['monitoring'];
	// "Model" field - upsdata_array[1]
	$data .= ":" . (($ups['ups.model'] != "") ? str_replace(":", " ", $ups['ups.model']) : gettext("n/a"));
	// "Status" field - upsdata_array[2]
	$disp_status = nut_status_to_display_text($ups['ups.status']);
	if (empty($disp_status)) {
		$disp_status = "n/a";
	}
	$data .= ":" . $disp_status;
	// "Battery Charge" bars and field - upsdata_array[3]
	$data .= ":" . sprintf("%.1f", $ups['battery.charge']);
	// "Time Remaning" field - upsdata_array[4]
	$secs = $ups['battery.runtime'];
	if ($secs < 0 || $secs == "") {
		$data .= ":" . gettext("n/a");
	} else {
		$data .= ":" . nut_secs_to_hms($secs);
	}
	// "Battery Voltage or Battery Temp" field - upsdata_array[5]
	if($ups['battery.voltage'] > 0) {
		$data .= ":" . $ups['battery.voltage'] . "&nbsp;V";
	} elseif ($ups['ups.temperature'] > 0) {
		$data .= ":" . $ups['ups.temperature'] . "&nbsp;C";
	} else {
		$data .= ":" . "";
	}
	// "Load" bars and field - upsdata_array[6]
	$data .= ":" . sprintf("%.1f", $ups['ups.load']);
	// "Input Voltage" field - upsdata_array[7]
	if ($ups['input.voltage']) {
		$data .= ":" . $ups['input.voltage'] . "&nbsp;V";
	} else {
		$data .= ":" . "n/a";
	}
	// "Output Voltage" field - upsdata_array[8]
	if ($ups['output.voltage']) {
		$data .= ":" . $ups['output.voltage'] . "&nbsp;V";
	} else {
		$data .= ":" . "n/a";
	}
	$data .= ":" . $nut['status'];
	$data .= ":" . $nut['error'];

	return $data;
}
?>

<style>
#UPSWidgetContainer th, #UPSWidgetContainer td {
	padding-left: 8px;
	padding-right: 8px;
}
</style>

<script type="text/javascript">
	//start showing ups data
	//NOTE: the refresh interval will be reset to a proper value in showUPSData() (ups_status.js).
	events.push(function() {
		showUPSData();
	});
</script>

<div id="UPSWidgetContainer" style="padding: 2px">
	<table width="100%" id="ups_widget" summary="UPS status">
		<tbody>
			<tr>
				<th><?php echo gettext("Monitoring"); ?></th>
				<th><?php echo gettext("Model"); ?></th>
				<th><?php echo gettext("Status"); ?></th>
			</tr>
			<tr>
				<td class="listlr" id="ups_monitoring"></td>
				<td class="listr" id="ups_model"></td>
				<td class="listr" id="ups_status"></td>
			</tr>
			<tr>
				<th><?php echo gettext("Battery Charge"); ?></th>
				<th><?php echo gettext("Time Remain"); ?></th>
				<th id="ups_celltitle_VT"></th>
			</tr>
			<tr>
				<td class="listlr" id="ups_charge">
					<div class="progress">
						<div id="ups_chargePB" class="progress-bar progress-bar-striped" 
							 role="progressbar" aria-valuenow="0" aria-valuemin="0" 
							 aria-valuemax="100" style="width: 0%;">
						</div>
					</div>
					<span id="ups_chargemeter"></span>
				</td>
				<td class="listr" id="ups_runtime"></td>
				<td class="listr" id="ups_bvoltage"></td>
			</tr>
			<tr>
				<th><?php echo gettext("Load"); ?></th>
				<th><?php echo gettext("Input Voltage"); ?></th>
				<th><?php echo gettext("Output Voltage"); ?></th>
			</tr>
			<tr>
				<td class="listlr" id="ups_load">
					<div class="progress">
						<div id="ups_loadPB" class="progress-bar progress-bar-striped" 
							 role="progressbar" aria-valuenow="0" aria-valuemin="0" 
							 aria-valuemax="100" style="width: 0%;">
						</div>
					</div>
					<span id="ups_loadmeter"></span>
				</td>
				<td class="listr" id="ups_inputv"></td>
				<td class="listr" id="ups_outputv"></td>
			</tr>
		</tbody>
	</table>
	<span id="ups_error_description"></span>
</div>
