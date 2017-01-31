<?php
/*
 * syslog-ng_log_viewer.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2017 Rubicon Communications, LLC (Netgate)
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
	$limit = "50";
}

if ($_POST['archives']) {
	$archives = true;
}

if ($_POST['filter']) {
	$filter = htmlspecialchars($_POST['filter']);
}

if ($_POST['not']) {
	$not = true;
}

$log_messages = array();
if (file_exists($logfile) && (filesize($logfile) > 0)) {
	$grep = "/usr/bin/grep -ih";

	if (($compress_archives == 'on') && glob($logfile . "*" . $compress_type) && $archives) {
		if($compress_type == 'bz2') {
			$grep = "/usr/bin/bzgrep -ih";
		} else {
			$grep = "/usr/bin/zgrep -ih";
		}
	}

	if (isset($filter) && $not) {
		$grepcmd = "$grep -v " . escapeshellarg($filter) . " $logfile";
	} else {
		$grepcmd = "$grep  " . escapeshellarg($filter) . " $logfile";
	}

	if ($archives) {
		$grepcmd = $grepcmd . "*";
	}

	$log_lines = trim(shell_exec("$grepcmd | /usr/bin/wc -l"));
	$log_output = trim(shell_exec("$grepcmd | /usr/bin/sort -M | /usr/bin/tail -n $limit"));

	if (!empty($log_output)) {
		$log_messages = explode("\n", $log_output);
		$log_messages_count = sizeof($log_messages);
	}
}

$pgtitle = array("Package", "Services: Syslog-ng", "Logs");
require_once("head.inc");
if ($savemsg) {
	print_info_box($savemsg);
}
$tab_array = array();
$tab_array[] = array("General", false, "/pkg_edit.php?xml=syslog-ng.xml&amp;id=0");
$tab_array[] = array("Advanced", false, "/pkg.php?xml=syslog-ng_advanced.xml");
$tab_array[] = array("Log Viewer", true, "/syslog-ng_log_viewer.php");
display_top_tabs($tab_array);
?>

<form action="syslog-ng_log_viewer.php" method="post" name="iform">
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Syslog-ng Log Viewer Settings")?></h2></div>
	<div class="panel-body table-responsive">
	<table class="table table-condensed">
		<tbody><tr><td>
			<table class="table table-condensed">
				<tr><th width="22%">Log File</th><td width="78%"><select name="logfile">
					<?php
					$log_files = syslogng_get_log_files($objects);
					foreach ($log_files as $log_file) {
						if ($log_file == $logfile) {
							echo "<option value=\"$log_file\" selected=\"selected\">$log_file</option>\n";
						} else {
							echo "<option value=\"$log_file\">$log_file</option>\n";
						}
					}
					?>
				</select></td></tr>
				<tr><th width="22%">Limit</th><td width="78%"><select name="limit">
					<?php
					$limit_options = array("10", "20", "50", "100", "250", "500");
					foreach ($limit_options as $limit_option) {
						if ($limit_option == $limit) {
							echo "<option value=\"$limit_option\" selected=\"selected\">$limit_option</option>\n";
						} else {
							echo "<option value=\"$limit_option\">$limit_option</option>\n";
						}
					}
					?>
				</select></td></tr>
				<tr><th width="22%">Include Archives</th><td width="78%"><input type="checkbox" name="archives" <?php if($archives) echo " CHECKED"; ?> /></td></tr>
				<tr><th width="22%">Filter</th><td width="78%"><input name="filter" value="<?=$filter?>" /></td></tr>
				<tr><th width="22%">Inverse Filter (NOT)</th><td width="78%"><input type="checkbox" name="not" <?php if($not) echo " CHECKED"; ?> /></td></tr>
				<tr><td colspan="2">
					<button type="submit" class="btn btn-primary" name="refresh" id="refresh" value="Refresh"><i class="fa fa-refresh icon-embed-btn"></i>Refresh</button>
				</td></tr>
			</table>
		</td></tr></tbody>
	</table>
	</div>
	<div class="panel-heading">
		<h2 class="panel-title">
		<?php
		if (!empty($log_messages)) {
			echo "Showing last {$log_messages_count} of {$log_lines} messages in {$log_file}\n";
		} else {
			echo "No messages found in {$log_file}.\n";
		}
		?>
		</h2>
	</div>
	<table class="table table-striped table-hover table-condensed">
		<tbody>
			<?php
			if (!empty($log_messages)) {
				foreach ($log_messages as $log_message) {
					echo "<tr><td class=\"listr\">$log_message</td></tr>\n";
				}
			}
			?>
		</tbody>
	</table>
</div>
</form>

<?php require_once("foot.inc"); ?>
