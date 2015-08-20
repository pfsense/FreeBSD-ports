<?php
/*
	syslog-ng_log_viewer.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2012 Lance Leger
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
require("/usr/local/pkg/syslog-ng.inc");

$objects = $config['installedpackages']['syslogngadvanced']['config'];
$default_logdir = $config['installedpackages']['syslogng']['config'][0]['default_logdir'];
$default_logfile = $config['installedpackages']['syslogng']['config'][0]['default_logfile'];
$compress_archives = $config['installedpackages']['syslogng']['config'][0]['compress_archives'];
$compress_type = $config['installedpackages']['syslogng']['config'][0]['compress_type'];

if ($_POST['logfile']) {
	$logfile = $_POST['logfile'];
} else {
	$logfile = $default_logdir . "/" . $default_logfile;
}

if ($_POST['limit']) {
	$limit = intval($_POST['limit']);
} else {
	$limit = "10";
}

if ($_POST['archives']) {
	$archives = true;
}

if ($_POST['filter']) {
	$filter = $_POST['filter'];
}

if ($_POST['not']) {
	$not = true;
}

$log_messages = array();
if (file_exists($logfile) && (filesize($logfile) > 0)) {
	$grep = "grep -ih";

	if (($compress_archives == 'on') && glob($logfile . "*" . $compress_type) && $archives) {
		if($compress_type == 'bz2') {
			$grep = "bzgrep -ih";
		} else {
			$grep = "zgrep -ih";
		}
	}

	if (isset($filter) && $not) {
		$grepcmd = "$grep -v '$filter' $logfile";
	} else {
		$grepcmd = "$grep '$filter' $logfile";
	}

	if ($archives) {
		$grepcmd = $grepcmd . "*";
	}

	$log_lines = trim(shell_exec("$grepcmd | wc -l"));
	$log_output = trim(shell_exec("$grepcmd | sort -M | tail -n $limit"));

	if (!empty($log_output)) {
		$log_messages = explode("\n", $log_output);
		$log_messages_count = sizeof($log_messages);
	}
}

$pgtitle = "Services: Syslog-ng Log Viewer";
include("head.inc");
?>
<body link="#000000" vlink="#000000" alink="#000000">
<?php include("fbegin.inc"); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<form action="syslog-ng_log_viewer.php" method="post" name="iform">
<table width="99%" border="0" cellpadding="0" cellspacing="0">
	<tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array("General", false, "/pkg_edit.php?xml=syslog-ng.xml&amp;id=0");
	$tab_array[] = array("Advanced", false, "/pkg.php?xml=syslog-ng_advanced.xml");
	$tab_array[] = array("Log Viewer", true, "/syslog-ng_log_viewer.php");
	display_top_tabs($tab_array);
?>
	</td></tr>
	<tr><td>
	<div id="mainarea">
		<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr><td>

			<table>
				<tr><td width="22%">Log File</td><td width="78%"><select name="logfile">
				<?php
				$log_files = syslogng_get_log_files($objects);
				foreach($log_files as $log_file) {
					if($log_file == $logfile) {
						echo "<option value=\"$log_file\" selected=\"selected\">$log_file</option>\n";
					} else {
						echo "<option value=\"$log_file\">$log_file</option>\n";
					}
				}
				?>
				</select></td></tr>
				<tr><td width="22%">Limit</td><td width="78%"><select name="limit">
				<?php
				$limit_options = array("10", "20", "50");
				foreach($limit_options as $limit_option) {
					if($limit_option == $limit) {
						echo "<option value=\"$limit_option\" selected=\"selected\">$limit_option</option>\n";
					} else {
						echo "<option value=\"$limit_option\">$limit_option</option>\n";
					}
				}
				?>
				</select></td></tr>
				<tr><td width="22%">Include Archives</td><td width="78%"><input type="checkbox" name="archives" <?php if($archives) echo " CHECKED"; ?> /></td></tr>
				<tr><td colspan="2">
				<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
				<?php
				if(!empty($log_messages)) {
					echo "<tr><td class=\"listtopic\">Showing $log_messages_count of $log_lines messages</td></tr>\n";
					foreach($log_messages as $log_message) {
						echo "<tr><td class=\"listr\">$log_message</td></tr>\n";
					}
				} else {
					echo "<tr><td><span class=\"red\">No log messages found or log file is empty.</span></td></tr>\n";
				}
				?>
				</table>
				</td></tr>
				<tr><td width="22%">Filter</td><td width="78%"><input name="filter" value="<?=$filter?>" /></td></tr>
				<tr><td width="22%">Inverse Filter (NOT)</td><td width="78%"><input type="checkbox" name="not" <?php if($not) echo " CHECKED"; ?> /></td></tr>
				<tr><td colspan="2"><input type="submit" value="Refresh" /></td></tr>
			</table>

			</td></tr>
		</table>
	</div>
	</td></tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
