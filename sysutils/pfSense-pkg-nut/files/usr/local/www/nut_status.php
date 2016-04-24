<?php
/*
	nut_status.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2007 Ryan Wagoner <rswagoner@gmail.com>.
	Copyright (C) 2016 ESF, LLC
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

require_once("/usr/local/pkg/nut.inc");
require("guiconfig.inc");

global $nut_config;
$nut_config = $config['installedpackages']['nut']['config'][0];

function secs_to_hms($secs) {
	if ($secs < 0 ) {
		return false;
	}
	$m = (int)($secs / 60); $s = $secs % 60;
	$h = (int)($m / 60); $m = $m % 60;
	return "{$h}h {$m}m {$s}s";
}

function status_to_display_text($status) {
	$ups_status_text = '';	
	foreach($status as $condition) {
		if ($ups_status_text != '') {
			$ups_status_text .= ', ';
		}
		switch ($condition) {
			case 'WAIT':
				$ups_status_text .= 'Waiting';
				break;
			case 'OFF':
				$ups_status_text .= 'Off Line';
				break;
			case 'OL':
				$ups_status_text .= 'On Line';
				break;
			case 'OB':
				$ups_status_text .= 'On Battery';
				break;
			case 'TRIM':
				$ups_status_text .= 'SmartTrim';
				break;
			case 'BOOST':
				$ups_status_text .= 'SmartBoost';
				break;
			case 'OVER':
				$ups_status_text .= 'Overload';
				break;
			case 'LB':
				$ups_status_text .= 'Battery Low';
				break;
			case 'RB':
				$ups_status_text .= 'Replace Battery';
				break;
			case 'CAL':
				$ups_status_text .= 'Calibration';
				break;
			default:
				$ups_status_text .= $condition;
				break;
		}
	}	
	return $ups_status_text;
}

function add_ups_value_to_section($section, $label, $value, $value_suffix='') {
	if(empty($value)) {
		return;
	}
	
	$section->addInput(new Form_StaticText(
			$label,
			$value . $value_suffix
	));
}

function add_ups_value_with_bar_to_section($section, $label, $value, $value_suffix, $red, $yellow, $green) {
	if(empty($value)) {
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
	
	$html_content = '<div style="width: 125px; height: 17px; border-top: thin solid gray; border-bottom: thin solid gray;">' . PHP_EOL;
	$html_content .= '	<div style="width: {$value}{$symbol}; height: 17px; background-color: ' . $bgcolor . '; font-size: 11px;">' . PHP_EOL;
	$html_content .= '		<div style="text-align: center; color: ' . $color . '">' . $value . $value_suffix . '</div>' . PHP_EOL;
	$html_content .= '	</div>' . PHP_EOL;
	$html_content .= '</div>' . PHP_EOL;

	$section->addInput(new Form_StaticText(
			$label,
			$html_content
	));
}

/* defaults to this page but if no settings are present, redirect to setup page */
if (!$nut_config['monitor']) {
	Header("Location: /pkg_edit.php?xml=nut.xml&id=0");
}

$pgtitle = array(gettext("Package"), gettext("Services: NUT"), gettext("NUT Status"));
include("head.inc");
?>
<style>
.ups-status-form .control-label {
	padding-top: 0px;
}
</style>

<?php

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("NUT Status"), true, "/nut_status.php");
$tab_array[] = array(gettext("NUT Settings"), false, "/pkg_edit.php?xml=nut.xml&id=0");
display_top_tabs($tab_array);

$running = ((int)exec('/bin/pgrep upsmon | /usr/bin/wc -l') > 0) ? true : false;

$form = new Form(false);
$form->addClass('ups-status-form');

$section = new Form_Section('NUT Status');

// UPS type row
if ($nut_config['monitor'] == 'local') {
	$ups_type_text = 'Local UPS';
	$cmd = "/usr/local/bin/upsc {$nut_config['name']}@localhost";
} elseif ($nut_config['monitor'] == 'remote') {
	$ups_type_text = 'Remote UPS';
	$cmd = "/usr/local/bin/upsc {$nut_config['remotename']}@{$nut_config['remoteaddr']}";
} elseif ($nut_config['monitor'] == 'snmp') {
	$ups_type_text = 'SNMP UPS';
	$cmd = "/usr/local/bin/upsc {$nut_config['snmpname']}@localhost";
}

$section->addInput(new Form_StaticText(
		'Monitoring',
		$ups_type_text
));

// UPS status row
if ($running) {
	$handle = popen($cmd, 'r');
	
	if($handle) {
		$read = fread($handle, 4096);
		pclose($handle);
		
		$lines = explode("\n", $read);
		$ups = array();
		foreach($lines as $line) {
			$line = explode(':', $line);
			$ups[$line[0]] = trim($line[1]);
		}
	}
	
	if($handle && count($lines) > 1) {
		$status = explode(' ', $ups['ups.status']);
		$ups_status_text = status_to_display_text($status);		
	} else {
		$ups_status_text = 'ERROR: failed to retrieve UPS status!';		
	}	
} elseif ($nut_config['monitor'] == 'snmp') {
	$ups_status_text = 'ERROR: NUT is enabled, however the service is not running! The SNMP UPS may be unreachable.';
} else {
	$ups_status_text = 'ERROR: NUT is enabled, however the service is not running!';
}
$section->addInput(new Form_StaticText(
		'Status',
		$ups_status_text
));

// All other UPS detail rows
if($handle && count($lines) > 1)
{
	add_ups_value_to_section($section, 'Model', $ups['ups.model']);
	add_ups_value_with_bar_to_section($section, 'Load', $ups['ups.load'], '%', '100-80', '79-60', '59-0');
	add_ups_value_with_bar_to_section($section, 'Battery Charge', $ups['battery.charge'], '%', '0-29' ,'30-79', '80-100');
	add_ups_value_to_section($section, 'Runtime Remaining', secs_to_hms($ups['battery.runtime']));
	add_ups_value_to_section($section, 'Battery Voltage', $ups['battery.voltage'], 'V');
	add_ups_value_to_section($section, 'Input Voltage', $ups['input.voltage'], 'V');
	add_ups_value_to_section($section, 'Input Frequency', $ups['input.frequency'], 'Hz');
	add_ups_value_to_section($section, 'Output Voltage', $ups['output.voltage'], 'V');
	add_ups_value_to_section($section, 'Temperature', $ups['ups.temperature'], '&deg;');
}	

$form->add($section);

print $form;

include("foot.inc");
?>
