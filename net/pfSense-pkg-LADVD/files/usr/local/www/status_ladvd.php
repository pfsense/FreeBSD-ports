<?php
/*
 * status_ladvd.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014 Andrea Tuccia
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
	echo "<table class=\"table table-striped table-hover\">\n";
	echo "<tr><td>" . $title . "</td></tr>\n";
	/* no newline after pre */
	echo "<tr><td><pre>";

	$execOutput = "";
	$execStatus = "";
	$fd = popen("{$command} 2>&1", "r");
	while (($line = fgets($fd)) !== FALSE) {
		echo wordwrap(htmlspecialchars($line, ENT_NOQUOTES), 130);
	}
	pclose($fd);
	echo "</pre></td></tr>\n";
	echo "</table>\n";
	echo "</div>\n";
}

$shortcut_section = 'ladvd';

$pgtitle = array(gettext('Status'), gettext('LADVD'));
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext('Settings'), false, "/pkg_edit.php?xml=ladvd.xml");
$tab_array[] = array(gettext('Status'), true, "/status_ladvd.php");
display_top_tabs($tab_array);

defCmdT("LADVD Devices", "{$control_script}");
defCmdT("LADVD Detailed decode", "{$control_script} -f");

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("LADVD Status Output"); ?></h2></div>
	<div class="panel-body">
		<div id="cmdspace" style="width:100%">
			<?php listCmds(); ?>
			<?php execCmds(); ?>
		</div>
	</div>
</div>
<?php include("foot.inc"); ?>
