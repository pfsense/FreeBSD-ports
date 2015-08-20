<?php
/*
	status_ladvd.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2014 Andrea Tuccia
	Copyright (C) 2014 Ermal Luçi
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

$pgtitle = "LADVD: Status";
include("head.inc");

$control_script = "/usr/local/sbin/ladvdc";

/* List all of the commands as an index. */
function listCmds() {
	global $commands;
	echo "<br/>This status page includes the following information:\n";
	echo "<ul>\n";
	for ($i = 0; isset($commands[$i]); $i++ ) {
		echo "<li><strong><a href=\"#" . str_replace(' ', '_', $commands[$i][0]) . "\">" . $commands[$i][0] . "</a></strong></li>\n";
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
	echo "<div>\n";
	echo "<a name=\"" . str_replace(' ', '_', $title) . "\" />\n";
	echo "<table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
	echo "<tr><td class=\"listtopic\">" . $title . "</td></tr>\n";
	/* no newline after pre */
	echo "<tr><td class=\"listlr\"><pre>";

	$execOutput = "";
	$execStatus = "";
	$fd = popen("{$command} 2>&1", "r");
	while (($line = fgets($fd)) !== FALSE) {
		echo htmlspecialchars($line, ENT_NOQUOTES);
	}
	pclose($fd);
	echo "</pre></td></tr>\n";
	echo "</table>\n";
	echo "</div>\n";
}

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=ladvd.xml&amp;id=0");
	$tab_array[] = array(gettext("Status"), true, "/status_ladvd.php");
	display_top_tabs($tab_array);
?>
	</td></tr>
	<tr><td>
		<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
			<tr><td>
<?php
				defCmdT("LADVD Devices", "{$control_script}");
				defCmdT("LADVD Detailed decode", "{$control_script} -f");
?>
			<div id="cmdspace" style="width:100%">
				<?php listCmds(); ?>
				<?php execCmds(); ?>
			</div>
			</td></tr>
		</table>
		</div>
	</td></tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
