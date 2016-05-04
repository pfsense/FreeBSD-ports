<?php
/*
	status_nut.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2007 Ryan Wagoner <rswagoner@gmail.com>.
	Copyright (C) 2015 ESF, LLC
	Copyright (C) 2016 PiBa-NL
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
require("nut.inc");
global $nut_config;
$nut_config = $config['installedpackages']['nut']['config'][0];

/* functions */

function secs2hms($secs) {
	if (empty($secs) || $secs < 0 ) {
		return false;
	}
	$m = (int)($secs / 60); $s = $secs % 60;
	$h = (int)($m / 60); $m = $m % 60;
	return "{$h}h {$m}m {$s}s";
}

function tblopen () {
	print('<table class="table">'."\n");
}

function tblclose () {
	print("</table>\n");
}

function tblrow ($name, $value, $symbol = null) {
	if (!$value) {
		return;
	}
	if ($symbol == '&deg;') {
		$value = sprintf("%.1f", $value);
	}
	if ($symbol == 'Hz') {
		$value = sprintf("%d", $value);
	}

	print(<<<EOD
<tr>
	<td class="vncellreq" width="100px">{$name}</td>
	<td class="vtable">{$value}{$symbol}</td>
<tr>
EOD
	."\n");
}

function tblrowbar ($id, $name, $value, $symbol, $red, $yellow, $green) {
	if (!$value) {
		return;
	}

	$value = sprintf("%.1f", $value);

	$red = explode('-', $red);
	$yellow = explode('-', $yellow);
	$green = explode('-', $green);

	sort($red);
	sort($yellow);
	sort($green);

	if ($value >= $red[0] && $value <= ($red[0]+9)) {
		$color = 'black';
		$bgcolor = 'red';
	}
	if ($value >= ($red[0]+10) && $value <= $red[1]) {
		$color = 'white';
		$bgcolor = 'red';
	}
	if ($value >= $yellow[0] && $value <= $yellow[1]) {
		$color = 'black';
		$bgcolor = 'yellow';
	}
	if ($value >= $green[0] && $value <= ($green[0]+9)) {
		$color = 'black';
		$bgcolor = 'green';
	}	
	if ($value >= ($green[0]+10) && $value <= $green[1]) {
		$color = 'white';
		$bgcolor = 'green';
	}
?>
<tr>
	<td class="vncellreq" width="100px"><?=$name?></td>
	<td class="vtable">
		<div style="background-color: <?=$color?>;padding:1px;" class="progress">
			<div id="<?=$id?>PB" class="progress-bar progress-bar-striped" 
				 role="progressbar" aria-valuenow="0" aria-valuemin="0" 
				 aria-valuemax="100" style="width: 0%;background-color: <?=$bgcolor?>;">
			</div>
		</div>
		<span id="<?=$id?>meter"><?=gettext('(Updating in 10 seconds)')?></span>
	</td>
<tr>
<?php
}

/* defaults to this page but if no settings are present, redirect to setup page */
if (!$nut_config['monitor']) {
	Header("Location: /pkg_edit.php?xml=nut.xml&id=0");
}

$pgtitle = array(gettext("Services"), gettext("Nut"), gettext("Status"));
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg); 
}
?>
<div id="mainlevel">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("NUT Status "), true, "/status_nut.php");
	$tab_array[] = array(gettext("NUT Settings "), false, "/pkg_edit.php?xml=nut.xml&id=0");
	display_top_tabs($tab_array);
?>
</table>
	
<table width="100%">
	<tr>
		<td width="50%" >
<div class="panel panel-default" >
	<div class="panel-heading">
		<h2 class="panel-title">
			Status
		</h2>
	</div>
	<div class="panel-body">
	
<table class="table">
<tr>
	<td  style="min-width:300px">
<?php
	tblopen();

	$ups = nut_get_data();
	tblrow('Monitoring:', $ups['monitoring']);

	if (isset($ups['error'])) {
		tblrow('ERROR:', $ups['error']);
	}
	if ($ups) {
		tblrow('Model:', $ups['status']['ups.model']);

		$disp_status = nut_status_to_text($ups['status']['ups.status']);
		tblrow('Status:', $disp_status);

		tblrowbar('ups_load','Load:', $ups['status']['ups.load'], '%', '100-80', '79-60', '59-0');
		tblrowbar('ups_charge','Battery Charge:', $ups['status']['battery.charge'], '%', '0-29' ,'30-79', '80-100');

		tblclose();
		tblopen();

		tblrow('Runtime Remaining:', secs2hms($ups['status']['battery.runtime']), '');
		tblrow('Battery Voltage:', $ups['status']['battery.voltage'], 'V');
		tblrow('Input Voltage:', $ups['status']['input.voltage'], 'V');
		tblrow('Input Frequency:', $ups['status']['input.frequency'], 'Hz');
		tblrow('Output Voltage:', $ups['status']['output.voltage'], 'V');
		tblrow('Temperature:', $ups['status']['ups.temperature'], '&deg;');
	}

	tblclose();
?>
	</td>
</tr>
</table>
</div>
</div>
</div>
</td>
<td>
</td>
</table>

<script>
	
events.push(function() {
	function setProgress(barName, percent) {
		$('#' + barName + 'PB').css({width: percent + '%'}).attr('aria-valuenow', percent);
		if ($('#' + barName + 'meter')) {
			$('#' + barName + 'meter').html(percent + '%');
		}
	}	
	<?php 
	$upssatus = $ups['status'];
	if ($upssatus['ups.load']) {
		echo "\nsetProgress('ups_load', {$upssatus['ups.load']});";
	}
	if ($upssatus['battery.charge']) {
		echo "\nsetProgress('ups_charge', {$upssatus['battery.charge']});";
	}
	?>
});

</script>
	
<?php include("foot.inc");