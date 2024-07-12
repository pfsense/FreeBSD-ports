<?php
/*
 * suricata_logs_browser.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2023 Bill Meeks
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

require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

if (isset($_POST['instance']) && is_numericint($_POST['instance']))
	$instanceid = $_POST['instance'];
elseif (isset($_GET['instance']) && is_numericint($_GET['instance']))
	$instanceid = htmlspecialchars($_GET['instance']);
if (empty($instanceid))
	$instanceid = 0;

$a_instance = config_get_path('installedpackages/suricata/rule', []);
$suricata_uuid = config_get_path("installedpackages/suricata/rule/{$instanceid}/uuid", '');
$if_real = get_real_interface(config_get_path("installedpackages/suricata/rule/{$instanceid}/interface", ''));

// Construct a pointer to the instance's logging subdirectory
$suricatalogdir = SURICATALOGDIR . "suricata_{$if_real}{$suricata_uuid}/";

// Limit all file access to just the currently selected interface's logging subdirectory
$logfile = htmlspecialchars($suricatalogdir . basename($_POST['file']));

if ($_POST['action'] == 'load') {
	if(!is_file($logfile)) {
		echo "|3|" . gettext("Log file does not exist or that logging feature is not enabled") . ".|";
	} else {
		$data = file_get_contents($logfile);
		if($data === false) {
			echo "|1|" . gettext("Failed to read log file") . ".|";
		} else {
			$data = base64_encode($data);
			echo "|0|{$logfile}|{$data}|";
		}
	}

	exit;
}

if ($_POST['action'] == 'clear') {
	if (basename($logfile) == "sid_changes.log") {
		file_put_contents($logfile, "");
	}

	exit;
}

$pglinks = array("", "/suricata/suricata_interfaces.php", "@self");
$pgtitle = array("Services", "Suricata", "Logs View");
include_once("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

function build_instance_list() {
	$list = array();

	foreach (config_get_path('installedpackages/suricata/rule', []) as $id => $instance) {
		$list[$id] = '(' . convert_friendly_interface_to_friendly_descr($instance['interface']) . ') ' . $instance['descr'];
	}

	return($list);
}

function build_logfile_list() {
	global $suricatalogdir;

	$list = array();

	$logs = array( "alerts.log", "block.log", "eve.json", "files-json.log", "http.log", "sid_changes.log", "stats.log", "suricata.log", "tls.log" );
	foreach ($logs as $log) {
		$list[$suricatalogdir . $log] = $log;
	}

	return($list);
}

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php?instance={$instanceid}");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), true, "/suricata/suricata_logs_browser.php");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

$form = new Form(false);

$section = new Form_Section('Logs Browser Selections');

$section->addInput(new Form_Select(
	'instance',
	'Instance to View',
	$instanceid,
	build_instance_list()
))->setHelp('Choose which instance logs you want to view.');

$section->addInput(new Form_Select(
	'logFile',
	'Log File to View',
	basename($logfile),
	build_logfile_list()
))->setHelp('Choose which log you want to view..');

// Build the HTML text to display in the StaticText control
$staticContent = '<span style="display:none; " id="fileStatusBox">' .
		'<strong id="fileStatus"></strong>' .
		'</span>' .
		'<p style="display:none;" id="filePathBox">' .
		'<strong>' . gettext("Log File Path: ") . '</strong>' . '<span style="display:inline;" id="fbTarget"></span>' . '</p>' . 
		'<p style="padding-right:15px; display:none;" id="fileRefreshBtn">' . 
		'<button type="button" class="btn btn-sm btn-info" name="refresh" id="refresh" onclick="loadFile();" title="' . 
		gettext("Refresh current display") . '"><i class="fa-solid fa-arrow-rotate-right icon-embed-btn"></i>' . gettext("Refresh") . '</button>&nbsp;&nbsp;' . 
		'<button type="button" class="btn btn-sm btn-danger hidden no-confirm" name="fileClearBtn" id="fileClearBtn" ' . 
		'onclick="clearFile();" title="' . gettext("Clear selected log file contents") . '"><i class="fa-solid fa-trash-can icon-embed-btn"></i>' . 
		gettext("Clear") . '</button></p>';

$section->addInput(new Form_StaticText(
	'Status/Result',
	$staticContent
));

$form->add($section);

print($form);
?>

<script>
//<![CDATA[
	function loadFile() {
		$("#fileStatus").html("<?=gettext("Loading file"); ?> ...");
		$("#fileStatusBox").show(250);
		$("#filePathBox").show(250);
		$("#fbTarget").html("");

		$.ajax(
				"<?=$_SERVER['SCRIPT_NAME'];?>",
				{
					type: 'post',
					data: {
						instance:  $("#instance").find('option:selected').val(),
						action:    'load',
						file: $("#logFile").val()
					},
					complete: loadComplete
				}
		);
	}

	function loadComplete(req) {
		$("#fileContent").show(250);
		var values = req.responseText.split("|");
		values.shift(); values.pop();

		if(values.shift() == "0") {
			var file = values.shift();
			var fileContent = atob(values.join("|"));
			$("#fileStatus").removeClass("text-danger");
			$("#fileStatus").addClass("text-success");
			$("#fileStatus").html("<?=gettext("File successfully loaded"); ?>.");
			$("#fbTarget").removeClass("text-danger");
			$("#fbTarget").html(file);
			$("#fileRefreshBtn").show();
			if (basename(file) == "sid_changes.log") {
				$("#fileClearBtn").removeClass("hidden");
			}
			else {
				$("#fileClearBtn").addClass("hidden");
			}
			$("#fileContent").prop("disabled", false);
			$("#fileContent").val(fileContent);
		}
		else {
			$("#fileStatus").addClass("text-danger");
			$("#fileStatus").html(values[0]);
			$("#fbTarget").addClass("text-danger");
			$("#fbTarget").html("<?=gettext("Not Available"); ?>");
			$("#fileRefreshBtn").hide();
			$("#fileContent").val("");
			$("#fileContent").prop("disabled", true);
		}
	}

	function clearFile() {
		if (confirm("<?=gettext('Are you sure want to erase the log contents?'); ?>")) {
			$.ajax(
				"<?=$_SERVER['SCRIPT_NAME'];?>",
				{
					type: 'post',
					data: {
						instance:  $("#instance").find('option:selected').val(),
						action:    'clear',
						file: $("#logFile").val()
					},
				}
			);
			$("#fileContent").val("");
		}
	}

	function basename(path) {
		return path.replace( /\\/g, '/' ).replace( /.*\//, '' );
	}

events.push(function() {

    //-- Click handlers -----------------------------
    $('#logFile').on('change', function() {
	$("#fbTarget").html("");
        loadFile();
    });

    $('#instance').on('change', function() {
	$("#fbTarget").html("");
        loadFile();
    });

    $('#refresh').on('click', function() {
        loadFile();
    });

    //-- Show nothing on initial page load -----------
<?php if(empty($_POST['file'])): ?>
	document.getElementById("logFile").selectedIndex=-1;
<?php endif; ?>

});
//]]>
</script>

<div class="panel panel-default" id="fileOutput">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Log Contents')?></h2></div>
		<div class="panel-body">
			<textarea id="fileContent" name="fileContent" style="width:100%;" rows="20" wrap="off" disabled></textarea>
		</div>
</div>

<?php include("foot.inc"); ?>

