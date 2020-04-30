<?php 
/*
 * haproxy.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 PiBa-NL
 * Copyright (c) 2011 Thomas Schaefer - Tomschaefer.org
 * Copyright (c) 2011 Marcello Coutinho
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

/*
	Some mods made from pfBlocker widget to make this for HAProxy on Pfsense
	Copyleft 2012 by jvorhees
*/

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("haproxy/haproxy_socketinfo.inc");
require_once("haproxy/haproxy_gui.inc");

$first_time = false;
if (!is_array($config["widgets"]["haproxy"])) {
	$first_time = true;
	$config["widgets"]["haproxy"] = array();
}
$a_config = &$config["widgets"]["haproxy"];

$getupdatestatus=false;
if(!empty($_REQUEST['getupdatestatus'])) {
	$getupdatestatus=true;
	header("Content-Type: application/octet-stream");
}

#Backends/Servers Actions if asked
if(!empty($_GET['act']) and !empty($_GET['be']) and !empty($_GET['srv'])) {
	if (!session_id()) {
		session_start();
	}
	$user = getUserEntry($_SESSION['Username']);
	if (!(userHasPrivilege($user, "page-service-haproxy") || userHasPrivilege($user, "page-all"))) {
		echo "Privilege Denied";
		return;
	}
	$backend = $_GET['be'];
	$server =  $_GET['srv'];
	$enable = $_GET['act'] == 'start' ? true : false;
	haproxy_set_server_enabled($backend, $server, $enable);
	return;
}

$simplefields = array("haproxy_widget_timer","haproxy_widget_showfrontends","haproxy_widget_showclients","haproxy_widget_showclienttraffic");
if ($_POST['submit']) {
	foreach($simplefields as $fieldname) {
		$a_config[$fieldname] = $_POST[$fieldname];
	}
	write_config("Services: HAProxy: Widget: Updated settings via dashboard.");
	header("Location: /");
	exit(0);
}

if (!session_id()) {
	session_start();
}
$user = getUserEntry($_SESSION['Username']);

// Set default values
if (!$a_config['haproxy_widget_timer']) {
	$a_config['haproxy_widget_timer'] = 5000;
	$a_config['haproxy_widget_showfrontends'] = 'no';
	$a_config['haproxy_widget_showclients'] = 'yes';
	$a_config['haproxy_widget_showclienttraffic'] = 'no';
}

$refresh_rate = $a_config['haproxy_widget_timer'];
$show_frontends = $a_config['haproxy_widget_showfrontends']=='yes';
$show_clients = $a_config['haproxy_widget_showclients']=='yes';
$show_clients_traffic = $a_config['haproxy_widget_showclienttraffic']=='yes';
			
$out = haproxyicon("down", "");
$in = haproxyicon("up", "");
$running = haproxyicon("enabled", "");
$stopped = haproxyicon("disabled", "");
$log = haproxyicon("resolvedns", "");
$start = haproxyicon("start","Enable this backend/server");
$stop = haproxyicon("stop","Disable this backend/server");

$clients=array();
$clientstraffic=array();

$statistics = haproxy_get_statistics();
$frontends = $statistics['frontends'];
$backends = $statistics['backends'];
$servers = $statistics['servers'];

if ($show_clients == "YES") {
	$clients = haproxy_get_clients($show_clients_traffic == "YES");
}
if (!$getupdatestatus) {
?>
<div id="haproxy_content">
<?php
}

echo "<table style=\"padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">";
#Frontends
if ($show_frontends == "YES") {
	print "<tr><td class=\"widgetsubheader\" colspan=\"4\"><strong>FrontEnd(s)</strong></td></tr>";
		print "<tr><td class=\"listlr\"><strong>Name</strong></td>";
		print "<td class=\"listlr\"><strong>Sessions</strong><br>(cur/max)</td>";
		print "<td class=\"listlr\" colspan=\"2\"><strong><center>Status</center></strong></td></tr>"; 

	foreach ($frontends as $fe => $fedata){
		print "<tr><td class=\"listlr\">".$fedata['pxname']."</td>";
		print "<td class=\"listlr\">".$fedata['scur']." / ".$fedata['slim']."</td>";
		if ($fedata['status'] == "OPEN") {
			$fedata['status'] = $running." ".$fedata['status'];
		} else {
			$fedata['status'] = $stopped." ".$fedata['status'];
		}
		print "<td class=\"listlr\" colspan=\"2\"><center>".$fedata['status']."</center></td></tr>";      
	}

	print "<tr height=\"6\"><td colspan=\"4\"></td></tr>";
}

#Backends/Servers w/o clients
print "<tr><td class=\"widgetsubheader\" colspan=\"4\"><strong>Backend(s)/Server(s)</strong></td></tr>";
print "<tr><td class=\"listlr\"><strong>Backend(s)</strong><br>&nbsp;Server(s)";
if ($show_clients == "YES") {
	print "<br><div class='text-success'>&nbsp;&nbsp;<i>Client(s) addr:port</i></div>";
}
print "</td>";
print "<td class=\"listlr\"><strong>Sessions</strong><br>(cur/max)<br>";
if ($show_clients == "YES" and $show_clients_traffic != "YES") {
	print "<div class='text-success'>age/id</div>";
} elseif ($show_clients == "YES" and $show_clients_traffic == "YES") {
	print "<div class='text-success'>age/traffic i/o</div>";
}
print "</td>";
print "<td class=\"listlr\" colspan=\"2\"><strong><center>Status<br>/<br>Actions</center></strong></td>";

foreach ($backends as $be => $bedata) {
	if ($bedata['status'] == "UP") {
		$statusicon = $in;
		$besess = $bedata['scur']." / ".$bedata['slim'];
		$bename = $bedata['pxname'];
	} else {
		$statusicon = $out;
		$besess = "<strong><div class='text-danger'>".$bedata['status']."</div></strong>";
		$bename = "<div class='text-danger'>".$bedata['pxname']."</div>";
	}
	$icondetails = " onmouseover=\"this.title='".$bedata['status']."'\"";
	print "<tr height=\"4\"><td bgcolor=\"#B1B1B1\" colspan=\"4\"></td></tr>";
        print "<tr><td class=\"listlr\"><strong>".$bename."</strong></td>";
        print "<td class=\"listlr\">".$besess."</td>";
        print "<td class=\"listlr\"$icondetails><center>".$statusicon."</center></td>";
	print "<td class=\"listlr\">&nbsp;</td></tr>";

	foreach ($servers as $srv => $srvdata) {
		if ($srvdata['pxname'] == $bedata['pxname']) {
			if ($srvdata['status'] == "UP") {
				$nextaction = "stop";
				$statusicon = $in;
				$acticon = $stop;
				$srvname = $srvdata['svname'];
			} elseif ($srvdata['status'] == "no check") {
				$nextaction = "stop";
				$statusicon = $in;
				$acticon = $stop;
				$srvname = $srvdata['svname'];
				$srvdata['scur'] = "<div class='text-success'>no check</div>";
			} elseif ($srvdata['status'] == "MAINT") {
				$nextaction = "start";
				$statusicon = $out;
				$acticon = $start;
				$srvname = "<div class='text-success'>".$srvdata['svname']."</div>";
				$srvdata['scur'] = "<div class='text-success'>".$srvdata['status']."</div>";
			} else {
				$nextaction = "stop";
				$statusicon = $out;
				$acticon = $stop;
				$srvname = "<div class='text-danger'>".$srvdata['svname']."</div>";
				$srvdata['scur'] = "<div class='text-danger'>".$srvdata['status']."</div>";
			}
			$icondetails = " onmouseover=\"this.title='".$srvdata['status']."'\"";
			print "<tr><td class=\"listlr \" style='padding-left:10px;'>".$srvname."</td>";
			print "<td class=\"listlr\">".$srvdata['scur']."</td>";
			print "<td class=\"listlr\"$icondetails><center>".$statusicon."</center></td>";
			
			if ((userHasPrivilege($user, "page-service-haproxy") || userHasPrivilege($user, "page-all"))) {
				print "<td class=\"listlr\"><center><a  onclick=\"control_haproxy('".$nextaction."','".$bedata['pxname']."','".$srvdata['svname']."');\">".$acticon."</a></center></td></tr>";
			}
			if ($show_clients == "YES") {
				foreach ($clients as $cli => $clidata) {
					if ($clidata['be'] == $bedata['pxname'] && $clidata['srv'] == $srvdata['svname']) {
						print "<tr><td class=\"listlr\"><div class='text-success'>&nbsp;&nbsp;<i>".$clidata['src']."</i>&nbsp;<a href=\"diag_dns.php?host=".$clidata['srcip']."\" title=\"Reverse Resolve with DNS\">".$log."</a></div></td>";
						if ($show_clients_traffic == "YES") {
							$clientstraffic[0] = format_bytes($clidata['session_datareq']);
							$clientstraffic[1] = format_bytes($clidata['session_datares']);
							print "<td class=\"listlr\" colspan=\"3\"><div class='text-success'>".$clidata['age']." / ".$clientstraffic[0]." / ".$clientstraffic[1]."</div></td></tr>";
						} else {
							print "<td class=\"listlr\" colspan=\"3\"><div class='text-success'>".$clidata['age']." / ".$clidata['sessid']."</div></td></tr>";
						}
					}
				}
			}
		}
	}
}

echo "</table>";
if (!$getupdatestatus)
{
	echo "</div>";
}

if ($getupdatestatus) {
	exit;
}
?>
</div>

<script type="text/javascript">
	function getstatusgetupdate() {
		$.ajax({
			url: "/widgets/widgets/haproxy.widget.php",
			data: { 
				getupdatestatus: "yes"
			},
			success: function(data, textStatus, response){
				activitycallback_haproxy(response);
			}
		})
	}
	function getstatus_haproxy() {
		setTimeout(getstatus_haproxy, <?= $refresh_rate ?>);
		getstatusgetupdate();

	}
	function activitycallback_haproxy(transport) {
		if (transport.getResponseHeader("content-type") != "application/octet-stream") {
			transport.responseText = "Stats not available";
		} 
		if ($('haproxy_content').innerHTML) {
			$('haproxy_content').innerHTML = transport.responseText;
		} else {
			$('#haproxy_content').html(transport.responseText);
		}
	}
	setTimeout(getstatus_haproxy, <?= $refresh_rate ?>);
	
	function control_haproxy(act, be, srv) {
			$.ajax({
				url: "/widgets/widgets/haproxy.widget.php",
				data: { 'act': act, 'be': be, 'srv': srv },
				success: function(){
					getstatusgetupdate();
				}
			})
	}
</script>
<div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">
<form action="/widgets/widgets/haproxy.widget.php" method="post" name="iform" id="iform">
	<table>
	<tr><td>
	Refresh Interval:</td><td>
	<input id="haproxy_widget_timer" name="haproxy_widget_timer" type="text" value="<?=$a_config['haproxy_widget_timer']?>"/></td>
	</tr><tr>
	<td>Show frontends:</td><td>
	<input id="haproxy_widget_showfrontends" name="haproxy_widget_showfrontends" type="checkbox" value="yes" <?php if ($a_config['haproxy_widget_showfrontends']=='yes') echo "checked"; ?>/></td>
	</tr><tr>
	<td>Show clients:</td>
	<td><input id="haproxy_widget_showclients" name="haproxy_widget_showclients" type="checkbox" value="yes" <?php if ($a_config['haproxy_widget_showclients']=='yes') echo "checked"; ?>/>
	Note: showing clients increases CPU/memory usage.
	</td>
	</tr><tr>
	<td>Show client traffic:</td>
	<td><input id="haproxy_widget_showclienttraffic" name="haproxy_widget_showclienttraffic" type="checkbox" value="yes" <?php if ($a_config['haproxy_widget_showclienttraffic']=='yes') echo "checked"; ?>/>
	Note: showing client traffic considerably increases CPU/memory usage.
	</td>
	</tr></table>
	<br> 
	<input id="submit" name="submit" type="submit" onclick="return updatePref();" class="formbtn" value="Save Settings" />
</form>
