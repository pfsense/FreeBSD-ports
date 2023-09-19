<?php
/*
	sfpnfo.php
	pfSense package (https://www.pfSense.org/)
	Copyright (C) 2023 Marco Goetze
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
require_once("sfpnfo.inc.php");

$pgtitle = array(gettext("Services"), gettext("sfpnfo"));
include("head.inc");
?>

<div class="infoblock blockopen">
	<?php
	print_info_box(gettext('sfpinfo displays information of used SFP modules on your system. All ix* interfaces are parsed and displayed.'), 'info', false);
	?>
</div>

<div class="panel panel-default">
	<?php
	// Retrieve available IX Interfaces
	$interfaces = sfpnfo_get_ix_interfaces();
	// if the $interfaces array countains 0 we output an error message
	if(empty($interfaces)) {
		print_info_box(gettext('No IX interfaces found!'), 'warning', false);
	} else {
		foreach($interfaces as $interface) {
			sfpnfo_display_interface($interface);
		}
	}
	?>
</div>

<?php

function sfpnfo_display_interface($interface) {
	// Print Header
	echo '<div class="panel-heading"><h2 class="panel-title">SFP '.$interface.'</h2></div>';
	echo '<div class="panel-body"><p>';
	$tmp_nfo = sfpnfo_runIfConfig($interface);
	if(empty($tmp_nfo)) {
		echo "<ul>".gettext("Interface does not exist!")."</ul>";
	} elseif($tmp_nfo == -1) {
		echo "<ul>".gettext("Invalid Interface Name or query not possible!")."</ul>";
	} else {
		$interface_nfo = sfpnfo_structure_output($tmp_nfo);
		if ($interface_nfo == -2) {
			echo "<ul>.".gettext("Invalid or Empty ifconfig response for Interface!")."</ul>";
		} elseif ($interface_nfo == -3) {
			echo "<ul>".gettext("Interface is not plugged! No SFP?")."</ul>";
		} else {
			echo "<table class='table table-striped'>";
			display_row_if_not_empty($interface_nfo, "plugged", "SFP");
			display_row_if_not_empty($interface_nfo, "vendor", gettext("Vendor"));
			display_row_if_not_empty($interface_nfo, "pn", gettext("Product Number"));
			display_row_if_not_empty($interface_nfo, "sn", gettext("Serial Number"));
			display_row_if_not_empty($interface_nfo, "date", gettext("Date"));
			display_row_if_not_empty($interface_nfo, "temp", gettext("Temperature"));
			display_row_if_not_empty($interface_nfo, "voltage", gettext("Voltage"));
			display_row_if_not_empty($interface_nfo, "rx", gettext("Laser Power RX"));
			display_row_if_not_empty($interface_nfo, "tx", gettext("Laser Power TX"));
			echo "</table>";
		}
	}
	echo '</p></div>';
}



include("foot.inc");


