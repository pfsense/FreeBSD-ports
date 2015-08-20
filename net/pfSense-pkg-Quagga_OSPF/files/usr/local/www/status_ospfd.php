<?php
/*
	status_ospfd.php
	Copyright (C) 2010 Nick Buraglio; nick@buraglio.com
	Copyright (C) 2010 Scott Ullrich <sullrich@pfsense.org>
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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

$pgtitle = "Quagga OSPF: Status";
include("head.inc");

$control_script = "/usr/local/bin/quaggactl";
$pkg_homedir	= "/var/etc/quagga";

/* List all of the commands as an index. */
function listCmds() {
	global $commands;
	echo "<br/>This status page includes the following information:\n";
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
	echo "<p>\n";
	echo "<a name=\"" . $title . "\">\n";
	echo "<table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
	echo "<tr><td class=\"listtopic\">" . $title . "</td></tr>\n";
	echo "<tr><td class=\"listlr\"><pre>";		/* no newline after pre */

	$execOutput = "";
	$execStatus = "";
	$fd = popen("{$command} 2>&1", "r");
	while (($line = fgets($fd)) !== FALSE) {
		echo htmlspecialchars($line, ENT_NOQUOTES);
	}
	pclose($fd);
	echo "</pre></tr>\n";
	echo "</table>\n";
}

?>

<html>
	<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
		<?php include("fbegin.inc"); ?>
		<?php if ($savemsg) print_info_box($savemsg); ?>

		<table width="100%" border="0" cellpadding="0" cellspacing="0">
  			<tr><td class="tabnavtbl">
<?php
				$tab_array = array();
				$tab_array[] = array(gettext("Settings"), false, "/pkg_edit.php?xml=quagga_ospfd.xml&id=0");
				$tab_array[] = array(gettext("Interface Settings"), false, "/pkg.php?xml=quagga_ospfd_interfaces.xml");
				$tab_array[] = array(gettext("RAW Config"), false, "/pkg_edit.php?xml=quagga_ospfd_raw.xml&id=0");
				$tab_array[] = array(gettext("Status"), true, "/status_ospfd.php");
				display_top_tabs($tab_array);
			?>
			</td></tr>
			  <tr>
				<td>
				<div id="mainarea">
					<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td>
<?php
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
?>
								<div id="cmdspace" style="width:100%">
									<?php listCmds(); ?>
									<?php execCmds(); ?>
								</div>
							</td>
						</tr>
					</table>
				</div>
				</td>
			   </tr>
		</table>
		<?php include("fend.inc"); ?>
	</body>
</html>
