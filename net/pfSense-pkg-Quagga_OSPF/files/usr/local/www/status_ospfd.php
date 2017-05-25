<?php
/*
 * status_ospfd.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2010-2015 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2010 Nick Buraglio <nick@buraglio.com>
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

require("guiconfig.inc");

$control_script = "/usr/local/bin/quaggactl";
$pkg_homedir = "/var/etc/quagga";

/* List all of the commands as an index. */
function listCmds() {
	global $commands;
	echo "<ul width=\"100%\">\n";
	for ($i = 0; isset($commands[$i]); $i++ ) {
		echo "<li><strong><a href=\"#" . $commands[$i][0] . "\">" . $commands[$i][0] . "</a></strong></li>\n";
	}
	echo "</ul>\n";
}

function execCmds() {
	global $commands;
	for ($i = 0; isset($commands[$i]); $i++ ) {
		doCmdT($commands[$i][0], $commands[$i][1]);
	}
}

/* Define a command, with a title, to be executed later. */
function defCmdT($title, $command) {
	global $commands;
	$title = htmlspecialchars($title,ENT_NOQUOTES);
	$commands[] = array($title, $command);
}

function doCmdT($title, $command) {
	echo "<tr><td>";
		echo "<div name=\"" . $title . "\"><h5><a name=\"" . $title . "\">". $title . "</a></h5>";
			echo "<pre>";
				$execOutput = "";
				$execStatus = "";
				$fd = popen("{$command} 2>&1", "r");
				while (($line = fgets($fd)) !== FALSE) {
					echo htmlspecialchars($line, ENT_NOQUOTES);
				}
				pclose($fd);
			echo "</pre><br />" ;
		echo "</div>" ;
	echo "</tr></td>";
}

defCmdT("Quagga OSPF General", "{$control_script} ospf general");
defCmdT("Quagga OSPF Neighbors", "{$control_script} ospf neighbor");
defCmdT("Quagga OSPF Database", "{$control_script} ospf database");
defCmdT("Quagga OSPF Router Database", "{$control_script} ospf database router");
defCmdT("Quagga OSPF Routes", "{$control_script} ospf route");
defCmdT("Quagga Zebra Routes", "{$control_script} zebra route");
defCmdT("Quagga OSPF Interfaces", "{$control_script} ospf interfaces");
defCmdT("Quagga OSPF CPU Usage", "{$control_script} ospf cpu");
defCmdT("Quagga OSPF Memory", "{$control_script} ospf mem");
defCmdT("Quagga BGP Routes", "{$control_script} bgp route");
defCmdT("Quagga BGP IPv6 Routes", "{$control_script} bgp6 route");
defCmdT("Quagga BGP Neighbors", "{$control_script} bgp neighbor");
defCmdT("Quagga BGP Summary", "{$control_script} bgp sum");
defCmdT("Quagga ospfd.conf", "/bin/cat {$pkg_homedir}/ospfd.conf");
defCmdT("Quagga bgpd.conf", "/bin/cat {$pkg_homedir}/bgpd.conf");
defCmdT("Quagga zebra.conf", "/bin/cat {$pkg_homedir}/zebra.conf");

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "/pkg_edit.php?xml=quagga_ospfd.xml&id=0");
$tab_array[] = array(gettext("Interface Settings"), false, "/pkg.php?xml=quagga_ospfd_interfaces.xml");
$tab_array[] = array(gettext("RAW Config"), false, "/pkg_edit.php?xml=quagga_ospfd_raw.xml&id=0");
$tab_array[] = array(gettext("Status"), true, "/status_ospfd.php");

$pgtitle = array(gettext("Services"),gettext("Quagga OSPF"),gettext("Status"));
include("head.inc");
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Detailled OSPF status Information.")?></h2></div>
		<div class="table-responsive">
			<table class="table table-hover table-condensed" >
				<tr>
					<td>
						<?php listCmds(); ?>
					</td>
				</tr>
				<?php execCmds(); ?>
			</table>
		</div>
		
<?php include("foot.inc");
