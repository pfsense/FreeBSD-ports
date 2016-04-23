<?php
/*
	ups_status.widget.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2015 SunStroke <andrey.b.nikitin@gmail.com>
	Copyright (C) 2015 ESF, LLC
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
require_once("guiconfig.inc"); // NOTE: maybe not needed (no GUI settings)? Remove if so.
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
	// "Model" field - upsdata_array[1]
	$data .= ":" . (($ups['ups.model'] != "") ? $ups['ups.model'] : gettext("n/a"));
	// "Status" field - upsdata_array[2]
	$disp_status = nut_status_to_text($ups['ups.status']);
	$data .= ":" . $disp_status;
	// "Battery Charge" bars and field - upsdata_array[3]
	$data .= ":" . $ups['battery.charge'];
	// "Time Remaning" field - upsdata_array[4]
	$secs = $ups['battery.runtime'];
	if ($secs < 0 || $secs == "") {
		$data .= ":" . gettext("n/a");
	} else {
		$m = (int)($secs / 60);
		$h = (int)($m / 60) % 24;
		$m = $m % 60;
		$s = $secs % 60;
		$data .= ":" . $h."h " . $m."m " . $s."s";
	}
	// "Battery Voltage or Battery Temp" field - upsdata_array[5]
	if($ups['battery.voltage'] > 0) {
		$data .= ":" . $ups['battery.voltage'] . "&nbsp;V";
	} elseif ($ups['ups.temperature'] > 0) {
		$data .= ":" . $ups['ups.temperature'] . "&#38;#176;C";
	} else {
		$data .= ":" . "";
	}
	// "Load" bars and field - upsdata_array[6]
	$data .= ":" . $ups['ups.load'];
	// "Input Voltage" field - upsdata_array[7]
	$data .= ":" . $ups['input.voltage'] . "&nbsp;V";
	// "Output Voltage" field - upsdata_array[8]
	$data .= ":" . $ups['output.voltage'] . "&nbsp;V";
	$data .= ":" . $nut['status'];
	$data .= ":" . $nut['error'];

	return $data;

}
?>

<script type="text/javascript">
//<![CDATA[
	//start showing ups data
	//NOTE: the refresh interval will be reset to a proper value in showUPSData() (ups_status.js).
	events.push(function() {
		showUPSData();
	});
//]]>
</script>

<div id="UPSWidgetContainer">
	<table width="100%" id="ups_widget" summary="UPS status">
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
				<div style="background-color: <?=$color?>;padding:1px;" class="progress">
					<div id="ups_chargePB" class="progress-bar progress-bar-striped" 
						 role="progressbar" aria-valuenow="0" aria-valuemin="0" 
						 aria-valuemax="100" style="width: 0%;">
					</div>
				</div>
				<span id="ups_chargemeter"><?=gettext('(Updating in 10 seconds)')?></span>
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
				<div style="background-color: <?=$color?>;padding:1px;" class="progress">
					<div id="ups_loadPB" class="progress-bar progress-bar-striped" 
						 role="progressbar" aria-valuenow="0" aria-valuemin="0" 
						 aria-valuemax="100" style="width: 0%;">
					</div>
				</div>
				<span id="ups_loadmeter"><?=gettext('(Updating in 10 seconds)')?></span>
			</td>
			<td class="listr" id="ups_inputv"></td>
			<td class="listr" id="ups_outputv"></td>
		</tr>
	</table>
	<span id="ups_error_description"></span>
</div>
