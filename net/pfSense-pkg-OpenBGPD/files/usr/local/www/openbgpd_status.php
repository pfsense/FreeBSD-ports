<?php
/* $Id$ */
/*
	openbgpd_status.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2007 Scott Ullrich (sullrich@gmail.com)
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

$commands = array();

defCmdT("summary",	"OpenBGPD Summary",	"/usr/local/sbin/bgpctl show summary");
defCmdT("interfaces",	"OpenBGPD Interfaces",	"/usr/local/sbin/bgpctl show interfaces");
defCmdT("routing",	"OpenBGPD Routing",	"/usr/local/sbin/bgpctl show rib",		true, 4);
defCmdT("forwarding",	"OpenBGPD Forwarding",	"/usr/local/sbin/bgpctl show fib",		true, 5);
defCmdT("network",	"OpenBGPD Network",	"/usr/local/sbin/bgpctl show network");
defCmdT("nexthops",	"OpenBGPD Nexthops",	"/usr/local/sbin/bgpctl show nexthop");
defCmdT("ip",		"OpenBGPD IP",		"/usr/local/sbin/bgpctl show ip bgp",		true, 4);
defCmdT("neighbors",	"OpenBGPD Neighbors",	"/usr/local/sbin/bgpctl show neighbor");

if (isset($_REQUEST['isAjax'])) {
	if (isset($_REQUEST['cmd']) && isset($commands[$_REQUEST['cmd']])) {
		echo "{$_REQUEST['cmd']}\n";
		if (isset($_REQUEST['count']))
			echo " of " . countCmdT($commands[$_REQUEST['cmd']]['command']) . " items";
		else
			echo htmlspecialchars_decode(doCmdT($commands[$_REQUEST['cmd']]['command'], $_REQUEST['limit'], $_REQUEST['filter'], $_REQUEST['header_size']));
	}
	exit;
}

if ($config['version'] >= 6)
	$pgtitle = array("OpenBGPD", "Status");
else
	$pgtitle = "OpenBGPD: Status";

include("head.inc");

function doCmdT($command, $limit = "all", $filter = "", $header_size = 0) {
	$grepline = "";
	if (!empty($filter)) {
		$ini = ($header_size > 0 ? $header_size+1 : 1);
		$grepline = " | /usr/bin/sed -e '{$ini},\$ { /" . escapeshellarg(htmlspecialchars($filter)) . "/!d; };'";
	}
	if (is_numeric($limit) && $limit > 0) {
		$limit += $header_size;
		$headline = " | /usr/bin/head -n {$limit}";
	}

	$fd = popen("{$command}{$grepline}{$headline} 2>&1", "r");
	$ct = 0;
	$result = "";
	while (($line = fgets($fd)) !== FALSE) {
		$result .= htmlspecialchars($line, ENT_NOQUOTES);
		if ($ct++ > 1000) {
			ob_flush();
			$ct = 0;
		}
	}
	pclose($fd);

	return $result;
}

function countCmdT($command) {
	$fd = popen("{$command} 2>&1", "r");
	$c = 0;
	while (fgets($fd) !== FALSE)
		$c++;

	pclose($fd);

	return $c;
}

function showCmdT($idx, $data) {
	echo "<p>\n";
	echo "<a name=\"" . $data['title'] . "\">&nbsp;</a>\n";
	echo "<table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
	echo "<tr><td colspan=\"2\" class=\"listtopic\">" . $data['title'] . "</td></tr>\n";

	$limit_default = "all";
	if ($data['has_filter']) {
		$limit_options = array("10", "50", "100", "200", "500", "1000", "all");
		$limit_default = "100";

		echo "<tr><td class=\"listhdr\" style=\"font-weight:bold;\">\n";
		echo "Display <select onchange=\"update_filter('{$idx}','{$data['header_size']}');\" name=\"{$idx}_limit\" id=\"{$idx}_limit\">\n";
		foreach ($limit_options as $item)
			echo "<option value='{$item}' " . ($item == $limit_default ? "selected" : "") . ">{$item}</option>\n";
		echo "</select> <span name=\"{$idx}_count\" id=\"{$idx}_count\">items</span></td>\n";
		echo "<td class=\"listhdr\" align=\"right\" style=\"font-weight:bold;\">Filter expression: \n";
		echo "<input type=\"text\" name=\"{$idx}_filter\" id=\"{$idx}_filter\" class=\"formfld search\" value=\"" . htmlspecialchars($_REQUEST["{$idx}_filter"]) . "\" size=\"30\" />\n";
		echo "<input type=\"button\" class=\"formbtn\" value=\"Filter\" onclick=\"update_filter('{$idx}','{$data['header_size']}');\" />\n";
		echo "</td></tr>\n";
	}

	echo "<tr><td colspan=\"2\" class=\"listlr\"><pre id=\"{$idx}\">";	/* no newline after pre */
	echo "Gathering data, please wait...\n";
	echo "</pre></td></tr>\n";
	echo "</table>\n";
}

/* Define a command, with a title, to be executed later. */
function defCmdT($idx, $title, $command, $has_filter = false, $header_size = 0) {
	global $commands;
	$title = htmlspecialchars($title,ENT_NOQUOTES);
	$commands[$idx] = array(
		'title' => $title,
		'command' => $command,
		'has_filter' => $has_filter,
		'header_size' => $header_size);
}

/* List all of the commands as an index. */
function listCmds() {
	global $commands;
	echo "<p>This status page includes the following information:\n";
	echo "<ul width=\"700\">\n";
	foreach ($commands as $idx => $command)
		echo "<li><strong><a href=\"#" . $command['title'] . "\">" . $command['title'] . "</a></strong></li>\n";
	echo "</ul>\n";
}

/* Execute all of the commands which were defined by a call to defCmd. */
function execCmds() {
	global $commands;
	foreach ($commands as $idx => $command)
		showCmdT($idx, $command);
}

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<script type="text/javascript">
//<![CDATA[

	function update_count(cmd, header_size) {
		var url = "openbgpd_status.php";
		var params = "isAjax=true&count=true&cmd=" + cmd + "&header_size=" + header_size;
		var myAjax = new Ajax.Request(
			url,
			{
				method: 'post',
				parameters: params,
				onComplete: update_count_callback
			});
	}

	function update_count_callback(transport) {
		// First line contain field id to be updated
		var responseTextArr = transport.responseText.split("\n");

		document.getElementById(responseTextArr[0] + "_count").innerHTML = responseTextArr[1];
	}

	function update_filter(cmd, header_size) {
		var url = "openbgpd_status.php";
		var filter = "";
		var limit = "all";
		var limit_field = document.getElementById(cmd + "_limit");
		if (limit_field) {
			var index = limit_field.selectedIndex;
			limit = limit_field.options[index].value;
			filter = document.getElementById(cmd + "_filter").value;
		}
		var params = "isAjax=true&cmd=" + cmd + "&limit=" + limit + "&filter=" + filter + "&header_size=" + header_size;
		var myAjax = new Ajax.Request(
			url,
			{
				method: 'post',
				parameters: params,
				onComplete: update_filter_callback
			});
	}

	function update_filter_callback(transport) {
		// First line contain field id to be updated
		var responseTextArr = transport.responseText.split("\n");
		var id = responseTextArr.shift();

		document.getElementById(id).textContent = responseTextArr.join("\n");
	}

//]]>
</script>

<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[

	function exec_all_cmds() {
<?php
		foreach ($commands as $idx => $command) {
			if ($command['has_filter'])
				echo "\t\tupdate_count('{$idx}', {$command['header_size']});\n";
			echo "\t\tupdate_filter('{$idx}', {$command['header_size']});\n";
		}
?>
	}

if (typeof jQuery == 'undefined')
	document.observe('dom:loaded', function(){setTimeout('exec_all_cmds()', 5000);});
else
	jQuery(document).ready(function(){setTimeout('exec_all_cmds()', 5000);});

//]]>
</script>

<?php
	if ($config['version'] < 6)
		echo '<p class="pgtitle">' . $pgtitle . '</font></p>';
?>

<?php if ($savemsg) print_info_box($savemsg); ?>

<div id="mainlevel">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Settings"), false, "/pkg_edit.php?xml=openbgpd.xml&id=0");
	$tab_array[] = array(gettext("Neighbors"), false, "/pkg.php?xml=openbgpd_neighbors.xml");
	$tab_array[] = array(gettext("Groups"), false, "/pkg.php?xml=openbgpd_groups.xml");
	$tab_array[] = array(gettext("Raw config"), false, "/openbgpd_raw.php");
	$tab_array[] = array(gettext("Status"), true, "/openbgpd_status.php");
	display_top_tabs($tab_array);
?>
</table>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td class="tabcont" >

			<div id="cmdspace" style="width:100%">
			<?php listCmds(); ?>

			<?php execCmds(); ?>
			</div>

		</td>
	</tr>
</table>
</div>

<?php include("fend.inc"); ?>

</body>
</html>
