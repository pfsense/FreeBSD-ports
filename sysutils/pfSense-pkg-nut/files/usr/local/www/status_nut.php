<?php
/*
	status_nut.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2007 Ryan Wagoner <rswagoner@gmail.com>.
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

require("guiconfig.inc");
global $nut_config;
$nut_config = $config['installedpackages']['nut']['config'][0];

/* functions */

function secs2hms($secs) {
	if ($secs < 0 ) {
		return false;
	}
	$m = (int)($secs / 60); $s = $secs % 60;
	$h = (int)($m / 60); $m = $m % 60;
	return "{$h}h {$m}m {$s}s";
}

function tblopen () {
	print('<table width="100%" class="tabcont" cellspacing="0" cellpadding="6">'."\n");
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

function tblrowbar ($name, $value, $symbol, $red, $yellow, $green) {
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

	print(<<<EOD
<tr>
	<td class="vncellreq" width="100px">{$name}</td>
	<td class="vtable">
	<div style="width: 125px; height: 12px; border-top: thin solid gray; border-bottom: thin solid gray;">
		<div style="width: {$value}{$symbol}; height: 12px; background-color: {$bgcolor};">
			<div style="text-align: center; color: {$color}">{$value}{$symbol}</div>
		</div>
	</div>
	</td>
<tr>
EOD
	."\n");
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
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
	<td>
<?php
	tblopen();

	$running = ((int)exec('/bin/pgrep upsmon | /usr/bin/wc -l') > 0) ? true : false;

	if ($nut_config['monitor'] == 'local') {
		tblrow('Monitoring:','Local UPS'); 
		$cmd = "/usr/local/bin/upsc {$nut_config['name']}@localhost";
	} elseif ($nut_config['monitor'] == 'remote') {
		tblrow('Monitoring:','Remote UPS');
		$cmd = "/usr/local/bin/upsc {$nut_config['remotename']}@{$nut_config['remoteaddr']}";
	} elseif ($nut_config['monitor'] == 'snmp') {
		tblrow('Monitoring:','SNMP UPS');
		$cmd = "/usr/local/bin/upsc {$nut_config['snmpname']}@localhost";
	}

	if ($running) {
		$handle = popen($cmd, 'r');
	} elseif ($nut_config['monitor'] == 'snmp') {
		tblrow('ERROR:','NUT is enabled, however the service is not running! The SNMP UPS may be unreachable.');
	} else {
		tblrow('ERROR:','NUT is enabled, however the service is not running!');
	}
	
	if ($handle) {
		$read = fread($handle, 4096);
		pclose($handle);

		$lines = explode("\n", $read);
		$ups = array();
		foreach($lines as $line) {
			$line = explode(':', $line);
			$ups[$line[0]] = trim($line[1]);
		}

		if (count($lines) == 1) {
			tblrow('ERROR:', 'Data stale!');
		}

		tblrow('Model:', $ups['ups.model']);

		$status = explode(' ', $ups['ups.status']);
		foreach($status as $condition) {
			if ($disp_status) {
				$disp_status .= ', ';
			}
			switch ($condition) {
				case 'WAIT':
					$disp_status .= 'Waiting';
					break;
				case 'OFF':
					$disp_status .= 'Off Line';
					break;
				case 'OL':
					$disp_status .= 'On Line';
					break;
				case 'OB':
					$disp_status .= 'On Battery';
					break;
				case 'TRIM':
					$disp_status .= 'SmartTrim';
					break;
				case 'BOOST':
					$disp_status .= 'SmartBoost';
					break;
				case 'OVER':
					$disp_status .= 'Overload';
					break;
				case 'LB':
					$disp_status .= 'Battery Low';
					break;
				case 'RB':
					$disp_status .= 'Replace Battery';
					break;
				case 'CAL':
					$disp_status .= 'Calibration';
					break;
				default:
					$disp_status .= $condition;
					break;
			}
		}
		tblrow('Status:', $disp_status);

		tblrowbar('Load:', $ups['ups.load'], '%', '100-80', '79-60', '59-0');
		tblrowbar('Battery Charge:', $ups['battery.charge'], '%', '0-29' ,'30-79', '80-100');

		tblclose();
		tblopen();

		tblrow('Runtime Remaining:', secs2hms($ups['battery.runtime']), '');
		tblrow('Battery Voltage:', $ups['battery.voltage'], 'V');
		tblrow('Input Voltage:', $ups['input.voltage'], 'V');
		tblrow('Input Frequency:', $ups['input.frequency'], 'Hz');
		tblrow('Output Voltage:', $ups['output.voltage'], 'V');
		tblrow('Temperature:', $ups['ups.temperature'], '&deg;');
	}

	tblclose();
?>
	</td>
</tr>
</table>
</div>

<?php include("foot.inc");