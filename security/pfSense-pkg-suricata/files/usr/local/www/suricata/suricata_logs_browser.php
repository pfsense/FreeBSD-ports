<?php
/*
 * suricata_logs_browser.php
 *
 * Portions of this code are based on original work done for the
 * Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
 * All rights reserved.
 *
 * Adapted for Suricata by:
 * Copyright (C) 2014 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:

 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
*/

require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

if (isset($_POST['instance']) && is_numericint($_POST['instance']))
	$instanceid = $_POST['instance'];
elseif (isset($_GET['instance']) && is_numericint($_GET['instance']))
	$instanceid = htmlspecialchars($_GET['instance']);
if (empty($instanceid))
	$instanceid = 0;

if (!is_array($config['installedpackages']['suricata']['rule'])) {
	$config['installedpackages']['suricata']['rule'] = array();
}

$a_instance = $config['installedpackages']['suricata']['rule'];
$suricata_uuid = $a_instance[$instanceid]['uuid'];
$if_real = get_real_interface($a_instance[$instanceid]['interface']);

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

$pgtitle = gettext("Suricata: Logs Browser");
include_once("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

function build_instance_list() {
	global $a_instance;

	$list = array();

	foreach ($a_instance as $id => $instance) {
		$list[$id] = '(' . convert_friendly_interface_to_friendly_descr($instance['interface']) . ') ' . $instance['descr'];
	}

	return($list);
}

function build_logfile_list() {
	global $suricatalogdir;

	$list = array();

	$logs = array( "alerts.log", "block.log", "dns.log", "eve.json", "files-json.log", "http.log", "sid_changes.log", "stats.log", "suricata.log", "tls.log" );
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
	$id,
	build_instance_list()
))->setHelp('Choose which instance logs you want to view.');

$section->addInput(new Form_Select(
	'logFile',
	'Log file to View',
	basename($logfile),
	build_logfile_list()
))->setHelp('Choose which log you want to view..');


$section->addInput(new Form_StaticText(
	'Log file contents',
	'<span style="display:none; " id="fileStatusBox">' .
	'<strong id="fileStatus"></strong>' .
	'</span>' .
	'<p style="display:none;" id="filePathBox">' .
	'<strong >' . gettext("Log File Path") . '</strong>' . '</p>' .
	'<span style="display:inline;" id="fbTarget"></span>' .
	'<p style="padding-right:15px; display:none;" id="fileRefreshBtn">' . 
		'<input type="button" class="btn btn-sm btn-info" name="refresh" id="refresh" value="Refresh" class="formbtn" onclick="loadFile();" title="<?=gettext("Refresh current display");?>' .
	'</p>'
));

$form->add($section);

print($form);
?>

<script>
//<![CDATA[
events.push(function() {

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
						instance:  $("#instance").val(),
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
			$("#fileStatus").html("<?=gettext("File successfully loaded"); ?>.");
			$("#fbTarget").html(file);
			$("#fileRefreshBtn").show();
			$("#fileContent").prop("disabled", false);
			$("#fileContent").val(fileContent);
		}
		else {
			$("#fileStatus").html(values[0]);
			$("#fbTarget").html("");
			$("#fileRefreshBtn").hide();
			$("#fileContent").val("");
			$("#fileContent").prop("disabled", true);
		}
	}

    $('#logFile').on('change', function() {
        loadFile();
    });

    $('#refresh').on('click', function() {
        loadFile();
    });

});
//]]>
</script>

<div class="panel panel-default" id="fileOutput">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Logs')?></h2></div>
		<div class="panel-body">
			<textarea id="fileContent" name="fileContent" style="width:100%;" rows="30" wrap="off" disabled></textarea>
		</div>
	</div>
</div>

<?php include("foot.inc"); ?>

