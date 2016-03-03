<?php
/*
 * snort_interface_logs.php
 *
 * Portions of this code are based on original work done for the
 * Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
 * Copyright (C) 2014-2016 Bill Meeks
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
require_once("/usr/local/pkg/snort/snort.inc");

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);
if (empty($id))
	$id = 0;

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
$a_instance = $config['installedpackages']['snortglobal']['rule'];
$snort_uuid = $a_instance[$id]['uuid'];
$if_real = get_real_interface($a_instance[$id]['interface']);

// Construct a pointer to the instance's logging subdirectory
$snortlogdir = SNORTLOGDIR . "/snort_{$if_real}{$snort_uuid}/";

// Construct a pointer to the PBI_BIN directory
$snortbindir = SNORT_PBI_BINDIR;

// Limit all file access to just the currently selected interface's logging subdirectory
$logfile = htmlspecialchars($snortlogdir . basename($_POST['file']));

if ($_POST['action'] == 'load') {
	// If viewing the app-stats log, then grab only the most recent one
	if (strpos(basename($logfile), "app-stats.log") !== FALSE) {
		$appid_statlogs = glob("{$snortlogdir}app-stats.log.*");
		$logfile = array_pop($appid_statlogs);
	}

	if(!is_file($logfile)) {
		echo "|3|" . gettext("Log file does not exist or that logging feature is not enabled") . ".|";
	}
	else {
		// Test for special unified2 format app-stats file because
		// we have to use a Snort binary tool to display its contents.
		if (strpos(basename($_POST['file']), "app-stats.log") !== FALSE)
			$data = shell_exec("{$snortbindir}u2openappid {$logfile} 2>&1");
		else 
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

$if_friendly = convert_friendly_interface_to_friendly_descr($a_instance[$id]['interface']);
$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Interface Logs"), gettext("{$if_friendly}"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg)
	print_info_box($savemsg);

function build_logfile_list() {
	global $snortlogdir, $if_real;

	$list = array();

	$logs = array( "alert", "app-stats.log", "{$if_real}.stats" , "sid_changes.log" );
	foreach ($logs as $log) {
		$list[$snortlogdir . $log] = $log;
	}

	return($list);
}

$tab_array = array();
$tab_array[] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php?instance={$id}");
$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);
$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
$tab_array = array();
$tab_array[] = array($menu_iface . gettext("Settings"), false, "/snort/snort_interfaces_edit.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Categories"), false, "/snort/snort_rulesets.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Rules"), false, "/snort/snort_rules.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Variables"), false, "/snort/snort_define_servers.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Preprocs"), false, "/snort/snort_preprocessors.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/snort/snort_barnyard.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/snort/snort_ip_reputation.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Logs"), true, "/snort/snort_interface_logs.php?id={$id}");
display_top_tabs($tab_array, true);

$form = new Form(false);

$section = new Form_Section('Log File Selection');
$section->addInput(new Form_Select(
	'logFile',
	'Log File to View',
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

if (isset($id)) {
	$form->addGlobal(new Form_Input(
		'id',
		'id',
		'hidden',
		$id
	));
}

print($form);
?>

<script type="text/javascript">
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
						id:  $("#id").val(),
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

<?php if(empty($_POST['file'])): ?>
<script type="text/javascript">
//<![CDATA[
	document.getElementById("logFile").selectedIndex=-1;
//]]>
</script>
<?php endif; ?>

<div class="panel panel-default" id="fileOutput">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Log Contents')?></h2></div>
		<div class="panel-body">
			<textarea id="fileContent" name="fileContent" style="width:100%;" rows="30" wrap="off" disabled></textarea>
		</div>
	</div>
</div>

<?php include("foot.inc"); ?>

