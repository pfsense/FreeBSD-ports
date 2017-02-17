<?php
/*
 * cellular.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2016 Voleatech GmbH, Fabian Schweinfurth
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
require_once("pfsense-utils.inc");
require_once("util.inc");
require_once("/usr/local/www/widgets/include/interfaces.inc");

$widget_url = '/widgets/widgets/cellular.widget.php';
define('PYTHON_BIN', '/usr/local/bin/python2.7');
define('INTERFACE_BIN', '/usr/local/sbin/cellular');

if (!file_exists(PYTHON_BIN)) {
	echo "Error: Python not found.";
	return;
}
if (!file_exists(INTERFACE_BIN)) {
	echo "Error: /usr/local/sbin/cellular not found. Reinstall pfSense-pkg-cellular package.";
	return;
}

if (isset($_POST['getsignalstrength'])) {
	exec(PYTHON_BIN . ' ' . INTERFACE_BIN . ' signal', $sig);
	print $sig[0];
} elseif (isset($_POST['getcarrier'])) {
	exec(PYTHON_BIN . ' ' . INTERFACE_BIN . ' carrier', $carrier);
	print $carrier[0];
} elseif (isset($_POST['getinfoex'])) {
	exec(PYTHON_BIN . ' ' . INTERFACE_BIN . ' infoex', $infoex);
	print $infoex[0];
} elseif (isset($_POST['widget'])) {
	exec(PYTHON_BIN . ' ' . INTERFACE_BIN . ' widget', $widget);
	print $widget[0];
} elseif (isset($_POST['getstatus'])) {
	$ifdescrs = get_configured_interface_with_descr();
	foreach ($ifdescrs as $ifdescr => $ifname) {

		$ifinfo = get_interface_info($ifdescr);
		if ($ifinfo['ppplink']) {
			$known_status = true;
			$icon = "";

			// Choose an icon by interface status
			if ($ifinfo['status'] == "up" || $ifinfo['status'] == "associated") {
				$icon = 'arrow-up text-success';
			} elseif ($ifinfo['status'] == "no carrier") {
				$icon = 'times-circle text-danger';
			} elseif ($ifinfo['status'] == "down") {
				$icon = 'arrow-down text-danger';
			} else {
				$known_status = false;
			}
			$macaddr = $ifinfo['macaddr'];
			$status = $ifinfo['status'];
			$ipaddr = htmlspecialchars($ifinfo['ipaddr']);
			$ipaddrv6 = htmlspecialchars($ifinfo['ipaddrv6']);
			$ifname = htmlspecialchars($ifname);
			print "$known_status,$icon,$macaddr,$ifdescr,$ifname,$status,$ipaddr,$ipaddrv6";

			break;
		}
        }

} else {
?>

<table id="modem" class="table table-striped table-hover table-condensed ">
	<thead>
		<tr>
			<th><?=gettext("Reception")?></th>
			<th><?=gettext("Status")?></th>
			<th><?=gettext("Carrier")?></th>
			<th><?=gettext("Address")?></th>
		</tr>
        </thead>
	<tbody>
		<tr>
			<td id="macaddr">
				<div id="signal-strength">
					<div id="signal-strength-bars"></div>
				</div>
				<a id="ifname"></a>
			</td>
			<td id="status"></td>
			<td id="carrier">
				<span id="carrier-span"></span>
				<strong id="modem-mode" class="icn"></strong>
			</td>
			<td id="ipaddr"></td>
		</tr>
	</tbody>
</table>

<script type="text/javascript">
	function widget() {
		var url = <?= '"' . $widget_url . '"' ?>;
		var pars = 'widget';
		var myAjax = $.ajax({
			url: url,
			type: "post",
			data: pars,
			complete: widgetcallback_modem
		});
		}

	function widgetcallback_modem(transport) {
		if (transport.responseText && (transport.responseText !== "ERROR" || transport.responseText !== "DEVICE NOT SUPPORTED")) {
			vals = transport.responseText.split(",");
			var signal = vals[0],
			    carrier = vals[1],
			    mode = vals[2];

			set_signal((signal && signal != "ERROR")? signal : 0);

			$("#modem-mode").html((mode && mode != "ERROR")? mode : "");

			$("#carrier-span").html((carrier && carrier != "ERROR")? carrier : "No Service ...");
		} else {
			set_signal(0);
			$("#modem-mode").html("");
			if (transport.responseText === "ERROR") {
				$("#carrier-span").html("An Error ocurred");
			} else {
				$("#carrier-span").html("Device not supported");
			}
		}

		setTimeout("widget()", 5000);
	}

	function getinfoex_modem() {
		var url = <?= '"' . $widget_url . '"' ?>;
		var pars = 'getinfoex';
		var myAjax = $.ajax({
			url: url,
			type: "post",
			data: pars,
			complete: infoexcallback_modem
		});
	}

	function infoexcallback_modem(transport) {

		// ^SYSINFOEX: <srv_status>,<srv_domain>,<roam_status>,<sim_state>,<lock_state>,<sysmode>,<sysmode_name>,<submode>,<submode_name>
		if (transport.responseText && transport.responseText != "ERROR") {
			var vals = transport.responseText.split(",");
			var srv_status = vals[0],
		    	    // srv_domain = vals[1],
			    // roam_status = vals[2],
			    sim_state = vals[3],
			    // lock_state = vals[4],
			    // sysmode = vals[5],
			    sysmode_name = vals[6];
			    // submode = vals[7],
			    // submode_name = vals[8];

			$("#modem-mode").html(sysmode_name.split('"')[1]);
		} else {
			$("#modem-mode").html("");
		}

		setTimeout("getinfoex_modem()", 5000);
	}

	function getstatus_modem() {
		var url = <?= '"' . $widget_url . '"' ?>;
		var pars = 'getstatus';
		var myAjax = $.ajax({
			url: url,
			type: "post",
			data: pars,
			complete: statuscallback_modem
		});
	}

	function statuscallback_modem(transport) {
		if (transport.responseText && transport.responseText != "ERROR") {
			var vals = transport.responseText.split(",");
			var known_status = vals[0],
			    icon = vals[1],
			    macaddr = vals[2],
			    ifdescr = vals[3],
			    ifname = vals[4],
			    status = vals[5],
			    ipaddr = vals[6],
			    ipaddrv6 = vals[7];
			var href = "/interfaces.php?if=" + ifdescr;

			$("#modem td#macaddr").attr("title", macaddr);
			$("#modem a#ifname").attr("href", href).html(ifname);
			if (known_status) {
				$("#modem #status").html('<i class="fa fa-' + icon + '" title="' + status + '"></i>');
			} else {
				$("#modem #status").html(status);
			}

			var iptext;
			if (!ipaddr && !ipaddrv6) {
				var iptext = "n/a";
			} else {
				var iptext = ipaddr + ((ipaddr && ipaddrv6)? "<br />" : "") + ipaddrv6;
			}
			$("#modem #ipaddr").html(iptext);
		} else {
			$("#modem #ipaddr").html("");
		}
		setTimeout('getstatus_modem()', 5000);
	}

	function set_signal(str) {
		var color = (str <= 1) ? "text-danger" :
		    (str == 2) ? "text-warning" :
		    "text-success";
		$("#signal-strength-bars").html('<i class="fa fa-signal ' + color + '" title="signal strength ' + str + '"></i>');
		var width = 15 + str * 22;
		$("#signal-strength-bars").css("width", width + "%");
	}

	function init_modem() {
		$('#signal-strength').css('width', '18px').css('height', 'auto').css('display', 'inline-block');
		$('.icn').css("font-size", "7pt").css("position", "relative").css("bottom", "7px");
		$("#signal-strength-bars").css('overflow', 'hidden');
	}

	events.push(function(){
		init_modem();
		widget();
		getstatus_modem();
		//set_td_widths();
		//setTimeout("set_td_widths()", 1000);
	});
</script>

<?php } ?>
