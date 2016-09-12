<?php
/*
 * syslog-ng_log_viewer.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2012 Lance Leger
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

$pgtitle = array(gettext("Package"), gettext("Services: Syslog-ng"), gettext("Logs"));

include("head.inc");
?>

<?php include("fbegin.inc"); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>

<style>

.border-bottom {
	border: 1px solid #F5F5F5; border-width: 0px 0 3px 0px;
}

</style>


<form action="syslog-ng_log_viewer.php" method="post" name="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color: #F5F5F5;">
	<tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array("General", false, "/pkg_edit.php?xml=syslog-ng.xml&amp;id=0");
	$tab_array[] = array("Advanced", false, "/pkg_edit.php?xml=syslog-ng_advanced.xml");
	$tab_array[] = array("Log Viewer", true, "/syslog-ng_log_viewer.php");
	display_top_tabs($tab_array);
?>
	</td></tr>
	<tr><td>
	<div id="mainarea">
		<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr><td>

			<table width="100%" class="panel-default" style="background-color: #FFFFFF;">
				<h2 class="panel-title" style="background-color: #424242; color: #FFFFFF; border: solid 5px #424242;">Syslog-ng Logs</h2>
				<tr><td class="border-bottom" width="22%">Log File</td><td class="border-bottom" width="78%"><select name="logfile">
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
				<tr><td class="border-bottom" width="22%">Limit</td><td class="border-bottom" width="78%"><select name="limit">
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
				<tr><td class="border-bottom" width="22%">Include Archives</td><td class="border-bottom" width="78%"><input type="checkbox" name="archives" <?php if($archives) echo " CHECKED"; ?> /></td></tr>
				<tr><td class="border-bottom" colspan="2">
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
				<tr><td class="border-bottom" width="22%">Filter</td><td class="border-bottom" width="78%"><input name="filter" value="<?=$filter?>" /></td></tr>
				<tr><td class="border-bottom" width="22%">Inverse Filter (NOT)</td><td class="border-bottom" width="78%"><input type="checkbox" name="not" <?php if($not) echo " CHECKED"; ?> /></td></tr>
				<tr><td class="border-bottom" colspan="2"><input type="submit" value="Refresh" /></td></tr>
			</table>

			</td></tr>
		</table>
	</div>
	</td></tr>
</table>
</form>
<?php include("foot.inc"); ?>
