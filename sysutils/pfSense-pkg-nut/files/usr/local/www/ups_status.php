<?php
/*
	ups_status.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2007 Ryan Wagoner <rswagoner@gmail.com>.
	Copyright (C) 2015 ESF, LLC
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

require_once("/usr/local/pkg/nut.inc");
require("guiconfig.inc");

function add_ups_value_to_section($section, $label, $value, $value_suffix='') {
	if(empty($value)) {
		return;
	}
	
	$section->addInput(new Form_StaticText(
			$label,
			$value . $value_suffix
	));
}

function add_ups_value_with_bar_to_section($section, $label, $value, $value_suffix) {
	if(empty($value)) {
		return;
	}
		
	$html_content = '<div class="progress" style="max-width:200px;">' . PHP_EOL;
	$html_content .= '	<div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: ' . $value . '%;">' . PHP_EOL;
	$html_content .= '	</div>' . PHP_EOL;
	$html_content .= '</div>' . PHP_EOL;
	$html_content .= '<span>' . sprintf("%.1f", $value) . $value_suffix . '</span>' . PHP_EOL;
	
	$section->addInput(new Form_StaticText(
			$label,
			$html_content
	));
}

/* defaults to this page but if no settings are present, redirect to setup page */
if (empty($config['installedpackages']['nut']['config'][0]['monitor'])) {
	header("Location: /pkg_edit.php?xml=nut.xml&id=0");
}

$pgtitle = array(gettext("Package"), gettext("Services: NUT"), gettext("UPS Status"));
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
$tab_array[] = array(gettext("UPS Status"), true, "/ups_status.php");
$tab_array[] = array(gettext("NUT Settings"), false, "/pkg_edit.php?xml=nut.xml&id=0");
display_top_tabs($tab_array);

$nut = nut_get_data();
$ups = $nut['status'];

$form = new Form(false);
$form->addClass('ups-status-form');

$section = new Form_Section('UPS Status');

// UPS type row
$section->addInput(new Form_StaticText(
		'Monitoring',
		$nut['monitoring']
));

// UPS status / error row
$section->addInput(new Form_StaticText(
		'Status',
		empty($nut['error']) 
		? nut_status_to_display_text($ups['ups.status']) 
		: 'ERROR: ' . $nut['error'] 
));

// All other UPS detail rows
if(empty($nut['error']))
{
	add_ups_value_to_section($section, 'Model', $ups['ups.model']);
	add_ups_value_with_bar_to_section($section, 'Load', $ups['ups.load'], '%');
	add_ups_value_with_bar_to_section($section, 'Battery Charge', $ups['battery.charge'], '%');
	add_ups_value_to_section($section, 'Runtime Remaining', nut_secs_to_hms($ups['battery.runtime']));
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
