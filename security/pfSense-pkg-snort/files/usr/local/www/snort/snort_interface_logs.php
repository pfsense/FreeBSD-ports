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
$pgtitle = gettext("Snort: {$if_friendly} Logs");
include_once("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000">

<?php
include_once("fbegin.inc");
if ($input_errors) {
	print_input_errors($input_errors);
}

?>
<script type="text/javascript" src="/javascript/base64.js"></script>
<script type="text/javascript">	
	function loadFile() {
		jQuery("#fileStatus").html("<?=gettext("Loading file"); ?> ...");
		jQuery("#fileStatusBox").show(250);
		jQuery("#filePathBox").show(250);
		jQuery("#fbTarget").html("");

		jQuery.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>", {
				type: 'POST',
				data: "id=" + jQuery("#id").val() + "&action=load&file=" + jQuery("#logFile").val(),
				complete: loadComplete
			}
		);
	}

	function loadComplete(req) {
		jQuery("#fileContent").show(250);
		var values = req.responseText.split("|");
		values.shift(); values.pop();

		if(values.shift() == "0") {
			var file = values.shift();
			var fileContent = Base64.decode(values.join("|"));
			jQuery("#fileStatus").html("<?=gettext("File successfully loaded"); ?>.");
			jQuery("#fbTarget").html(file);
			jQuery("#fileRefreshBtn").show();
			jQuery("#fileContent").prop("disabled", false);
			jQuery("#fileContent").val(fileContent);
		}
		else {
			jQuery("#fileStatus").html(values[0]);
			jQuery("#fbTarget").html("");
			jQuery("#fileRefreshBtn").hide();
			jQuery("#fileContent").val("");
			jQuery("#fileContent").prop("disabled", true);
		}
	}

</script>

<form action="/snort/snort_interface_logs.php" method="post" id="formbrowse">
<input type="hidden" id="id" value="<?=$id;?>"/>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tbody>
	<tr><td>
	<?php
		$tab_array = array();
		$tab_array[0] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
		$tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
		$tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
		$tab_array[3] = array(gettext("Alerts"), false, "/snort/snort_alerts.php?instance={$id}");
		$tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
		$tab_array[5] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
		$tab_array[6] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
		$tab_array[7] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
		$tab_array[8] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
		$tab_array[9] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
		$tab_array[10] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
		display_top_tabs($tab_array, true);
		echo '</td></tr>';
		echo '<tr><td>';
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
	?>
	</td>
	</tr>
	<tr>
	<td><div id="mainarea">
		<table id="maintable" class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6">
			<tbody>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("Log File Selections"); ?></td>
			</tr>
			<tr>
				<td width="22%" class="vncell"><?php echo gettext('Log File to View'); ?></td>
				<td width="78%" class="vtable">
					<select name="logFile" id="logFile" class="formselect" onChange="loadFile();">
			<?php
				$logs = array( "alert", "app-stats.log", "{$if_real}.stats" , "sid_changes.log" );
				foreach ($logs as $log) {
					$selected = "";
					if ($log == basename($logfile))
						$selected = "selected";
					echo "<option value='{$snortlogdir}{$log}' {$selected}>" . $log . "</option>\n";
				}
			?>
					</select>&nbsp;&nbsp;<?php echo gettext('Choose which log you want to view.'); ?>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("Log File Contents"); ?></td>
			</tr>
			<tr>
				<td colspan="2">
					<table width="100%">
						<tbody>
						<tr>
							<td width="75%">
								<div style="display:none; " id="fileStatusBox">
									<div class="list" style="padding-left:15px;">
									<strong id="fileStatus"></strong>
									</div>
								</div>
								<div style="padding-left:15px; display:none;" id="filePathBox">
									<strong><?=gettext("Log File Path"); ?>:</strong>
									<div class="list" style="display:inline;" id="fbTarget"></div>
								</div>
							</td>
							<td align="right">
								<div style="padding-right:15px; display:none;" id="fileRefreshBtn">
									<input type="button" name="refresh" id="refresh" value="Refresh" class="formbtn" onclick="loadFile();" title="<?=gettext("Refresh current display");?>" />
								</div>
							</td>
						</tr>
						</tbody>
					</table>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<table width="100%">
						<tbody>
						<tr>
							<td valign="top" class="label">
							<div style="background:#eeeeee;" id="fileOutput">
							<textarea id="fileContent" name="fileContent" style="width:100%;" rows="30" wrap="off" disabled></textarea>
							</div>
							</td>
						</tr>
						</tbody>
					</table>
				</td>
			</tr>
			</tbody>
		</table>
	</div>
	</td>
	</tr>
	</tbody>
</table>
</form>

<?php if(empty($_POST['file'])): ?>
<script type="text/javascript">
	document.getElementById("logFile").selectedIndex=-1;
</script>
<?php endif; ?>

<?php include("fend.inc"); ?>
</body>
</html>
