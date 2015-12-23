<?php
/*
	status_ospfd.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2010 Nick Buraglio <nick@buraglio.com>
	Copyright (C) 2010 Scott Ullrich <sullrich@pfsense.org>
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
defCmdT("Quagga ospfd.conf", "/bin/cat {$pkg_homedir}/ospfd.conf");
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
