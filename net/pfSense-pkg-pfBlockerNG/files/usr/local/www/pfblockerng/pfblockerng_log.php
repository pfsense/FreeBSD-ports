<?php
/*
	pfBlockerNG_Log.php

	pfBlockerNG
	Copyright (c) 2015 BBcan177@gmail.com
	All rights reserved.

	Portions of this code are based on original work done for the
	Snort package for pfSense from the following contributors:

	Copyright (c) 2015 Electric Sheep Fencing, LLC. All rights reserved.
	Copyright (c) 2009 Robert Zelaya Sr. Developer
	Copyright (c) 2005 Bill Marquette
	Copyright (c) 2004-2005 Scott Ullrich
	Copyright (c) 2004 Manuel Kasper (BSD 2 clause)

	All rights reserved.

	Adapted for Suricata by:
	Copyright (c) 2015 Bill Meeks
	All rights reserved.

	Javascript and Integration modifications by J. Nieuwenhuizen

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

require_once('guiconfig.inc');
require_once('globals.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');

pfb_global();

// Get log files from directory
function getlogs($logdir, $log_extentions = array('log')) {
	if (!is_array($log_extentions)) {
		$log_extentions = array($log_extentions);
	}

	// Get logfiles
	$log_filenames = array();
	foreach ($log_extentions as $extention) {
		if ($extention != '*') {
			$log_filenames = array_merge($log_filenames, glob($logdir . '*.' . $extention));
		} else {
			$log_filenames = array_merge($log_filenames, glob($logdir . '*'));
		}
	}

	// Convert to filenames only
	if (count($log_filenames) > 0) {
		$log_totalfiles = count($log_filenames);
		for ($cnt = 0; $cnt < $log_totalfiles; $cnt++) {
			$log_filenames[$cnt] = basename($log_filenames[$cnt]);
		}
	}
	
	// Sort the filename
	asort($log_filenames);
	
	// Done
	return $log_filenames;
}

/*	Define logtypes:
		name	=>	Displayname of the type
		ext	=>	Log extentions (array for multiple extentions)
		logdir	=>	Log directory
		clear	=>	Add clear button (TRUE/FALSE)	*/

$pfb_logtypes = array(	'defaultlogs'	=> array('name'		=> 'Log Files',
						'logdir'	=> "{$pfb['logdir']}/",
						'logs'		=> array('pfblockerng.log', 'error.log', 'dnsbl.log', 'extras.log', 'maxmind_ver'),
						'download'	=> TRUE,
						'clear'		=> TRUE
						),
			'masterfiles'	=> array('name'		=> 'Masterfiles',
						'logdir'	=> "{$pfb['dbdir']}/",
						'logs'		=> array('masterfile', 'mastercat'),
						'download'	=> TRUE,
						'clear'		=> FALSE
						),
			'originallogs'	=> array('name'		=> 'Original IP Files',
						'ext'		=> array('orig', 'raw'),
						'logdir'	=> "{$pfb['origdir']}/",
						'download'	=> TRUE,
						'clear'		=> TRUE
						),
			'origdnslogs'	=> array('name'		=> 'Original DNS Files',
						'ext'		=> array('orig', 'raw'),
						'logdir'	=> "{$pfb['dnsorigdir']}/",
						'download'	=> TRUE,
						'clear'		=> TRUE
						),	
			'denylogs'	=> array('name'		=> 'Deny Files',
						'ext'		=> 'txt',
						'txt'		=> 'deny',
						'logdir'	=> "{$pfb['denydir']}/",
						'download'	=> TRUE,
						'clear'		=> TRUE
						),
			'dnsbl'		=> array('name'		=> 'DNSBL Files',
						'ext'		=> array('txt', 'ip'),
						'txt'		=> 'dnsbl',
						'logdir'	=> "{$pfb['dnsdir']}/",
						'download'	=> TRUE,
						'clear'		=> TRUE
						),
			'permitlogs'	=> array('name'		=> 'Permit Files',
						'ext'		=> 'txt',
						'txt'		=> 'permit',
						'logdir'	=> "{$pfb['permitdir']}/",
						'download'	=> TRUE,
						'clear'		=> TRUE
						),
			'matchlogs'	=> array('name'		=> 'Match Files',
						'ext'		=> 'txt',
						'txt'		=> 'match',
						'logdir'	=> "{$pfb['matchdir']}/",
						'download'	=> TRUE,
						'clear'		=> TRUE
						),
			'nativelogs'	=> array('name'		=> 'Native Files',
						'ext'		=> 'txt',
						'logdir'	=> "{$pfb['nativedir']}/",
						'download'	=> TRUE,
						'clear'		=> TRUE
						),
			'aliaslogs'	=> array('name'		=> 'Alias Files',
						'ext'		=> 'txt',
						'logdir'	=> "{$pfb['aliasdir']}/",
						'download'	=> TRUE,
						'clear'		=> FALSE
						),
			'etiprep'	=> array('name'		=> 'ET IPRep Files',
						'ext'		=> '*',
						'logdir'	=> "{$pfb['etdir']}/",
						'download'	=> TRUE,
						'clear'		=> FALSE
						),
			'country'	=> array('name'		=> 'Country Files',
						'ext'		=> 'txt',
						'logdir'	=> "{$pfb['ccdir']}/",
						'download'	=> TRUE,
						'clear'		=> FALSE
						),
			'unbound'	=> array('name'		=> 'Unbound',
						'ext'		=> 'conf',
						'logdir'	=> "{$pfb['dnsbldir']}/",
						'download'	=> TRUE,
						'clear'		=> FALSE
						)
		);

// Check logtypes
$logtypeid = 'defaultlogs';
if (isset($_POST['logtype'])) {
	$logtypeid = htmlspecialchars($_POST['logtype']);
} elseif (isset($_GET['logtype'])) {
	$logtypeid = htmlspecialchars($_GET['logtype']);
}

// Check if POST has been set
if (isset($_POST['file'])) {
	clearstatcache();
	$pfb_logfilename = htmlspecialchars($_POST['file']);
	$pfb_ext = pathinfo($pfb_logfilename, PATHINFO_EXTENSION);

	// Load log
	if ($_POST['action'] == 'load') {
		if (!is_file($pfb_logfilename)) {
			echo "|3|" . gettext('Log file is empty or does not exist') . ".|";
		} else {
			$data = file_get_contents($pfb_logfilename);
			if ($data === false) {
				echo "|1|" . gettext('Failed to read log file') . ".|";
			} else {
				$data = base64_encode($data);
				echo "|0|" . $pfb_logfilename . "|" . $data . "|";
			}
		}
		exit;
	}
}

if (isset($_POST['logFile'])) {
	$s_logfile = htmlspecialchars($_POST['logFile']);

	// Clear selected file
	if (isset($_POST['clear'])) {
		unlink_if_exists($s_logfile);
	}

	// Download log
	if (isset($_POST['download'])) {
		if (file_exists($s_logfile)) {
			ob_start(); //important or other posts will fail
			if (isset($_SERVER['HTTPS'])) {
				header('Pragma: ');
				header('Cache-Control: ');
			} else {
				header('Pragma: private');
				header('Cache-Control: private, must-revalidate');
			}
			header('Content-Type: application/octet-stream');
			header('Content-length: ' . filesize($s_logfile));
			header('Content-disposition: attachment; filename = ' . basename($s_logfile));
			ob_end_clean(); //important or other post will fail
			readfile($s_logfile);
		}
	}
} else {
	$s_logfile = '';
}

$pgtitle = gettext('pfBlockerNG: Log Browser');
include_once('head.inc');
?>

<body link="#000000" vlink="#0000CC" alink="#000000">

<?php
include_once('fbegin.inc');
if ($input_errors) {
	print_input_errors($input_errors);
}
?>

<script type="text/javascript" src="/javascript/base64.js"></script>
<script type="text/javascript">	
//<![CDATA[

	function loadFile() {
		jQuery("#fileStatus").html("<?=gettext("Loading file"); ?> ...");
		jQuery("#fileStatusBox").show(250);
		jQuery("#filePathBox").show(250);
		jQuery("#fbTarget").html("");

		jQuery.ajax(
			"/pfblockerng/pfblockerng_log.php", {
				type: 'POST',
				data: "instance=" + jQuery("#instance").val() + "&action=load&file=" + jQuery("#logFile").val(),
				complete: loadComplete
			}
		)
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
		} else {
			jQuery("#fileStatus").html(values[0]);
			jQuery("#fbTarget").html("");
			jQuery("#fileRefreshBtn").hide();
			jQuery("#fileContent").val("");
			jQuery("#fileContent").prop("disabled", true);
		}
	}
//]]>
</script>

<?php
echo("<form action='/pfblockerng/pfblockerng_log.php' method='post' id='formbrowse'>");
if ($savemsg) {
	print_info_box($savemsg);
}
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
	<?php
		$tab_array = array();
		$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=pfblockerng.xml");
		$tab_array[] = array(gettext("Update"), false, "/pfblockerng/pfblockerng_update.php");
		$tab_array[] = array(gettext("Alerts"), false, "/pfblockerng/pfblockerng_alerts.php");
		$tab_array[] = array(gettext("Reputation"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_reputation.xml");
		$tab_array[] = array(gettext("IPv4"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v4lists.xml");
		$tab_array[] = array(gettext("IPv6"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v6lists.xml");
		$tab_array[] = array(gettext("DNSBL"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_dnsbl.xml");
		$tab_array[] = array(gettext("Country"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_top20.xml");
		$tab_array[] = array(gettext("Logs"), true, "/pfblockerng/pfblockerng_log.php");
		$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_sync.xml");
		display_top_tabs($tab_array, true);
	?>
		</td>
	</tr>
	<tr>
		<td>
		<div id="mainarea">
		<table id="maintable" class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6">
			<tbody>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("Log/File Browser Selections"); ?></td>
			</tr>
			<tr>
				<td colspan="3" class="vncell" align="left"><?php echo gettext("LINKS :"); ?>&emsp;
				<a href='/firewall_aliases.php' target="_blank"><?php echo gettext("Firewall Alias"); ?></a>&emsp;
				<a href='/firewall_rules.php' target="_blank"><?php echo gettext("Firewall Rules"); ?></a>&emsp;
				<a href='/diag_logs_filter.php' target="_blank"><?php echo gettext("Firewall Logs"); ?></a><br /></td>
			</tr>
			<tr>
				<td width="22%" class="vncell"><?php echo gettext('Log/File type:'); ?></td>
				<td width="78%" class="vtable">
					<select name="logtype" id="logtype" class="formselect" onChange="document.getElementById('formbrowse').method='post';document.getElementById('formbrowse').submit()">
			<?php
				$clearable = FALSE;
				$downloadable = FALSE;
				foreach ($pfb_logtypes as $id => $logtype) {
					$selected = '';
					if ($id == $logtypeid) {
						$selected = ' selected';
						$clearable = $logtype['clear'];
						$downloadable = $logtype['download'];
					}
					echo("<option value='" . $id . "'" . $selected . ">" . $logtype['name'] . "</option>\n");
				}
			?>
					</select>&emsp;<?php echo gettext('Choose which type of log/file you want to view.'); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" class="vncell"><?php echo gettext('Log/File selection:'); ?></td>
				<td width="78%" class="vtable">
					<select name="logFile" id="logFile" class="formselect" onChange="loadFile();">
			<?php
				if (isset($pfb_logtypes[$logtypeid]['logs'])) {
					$logs = $pfb_logtypes[$logtypeid]['logs'];
				} else {
					$logs = getlogs($pfb_logtypes[$logtypeid]['logdir'], $pfb_logtypes[$logtypeid]['ext']);
				}
				foreach ($logs as $log) {
					$selected = '';
					if ($log == $pfb_logfilename) {
						$selected = ' selected';
					}
					echo("<option value='" . $pfb_logtypes[$logtypeid]['logdir'] . $log . "'" . $selected . ">" . $log . "</option>\n");
				}
			?>
					</select>&emsp;<?php echo gettext('Choose which log/file you want to view.'); ?>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("Log/File Contents"); ?></td>
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
									<strong><?=gettext("Log/File Path"); ?>:</strong>
									<div class="list" style="display:inline;" id="fbTarget"></div>
								</div>
							</td>
							<td align="right">
								<div style="padding-right:15px; display:none;" id="fileRefreshBtn">
									<?php
										echo("<img src='../tree/page-file.png' onclick='loadFile()' title='" . gettext("Refresh current display") . "' alt='refresh' width='17' height='17' border='0' /> &nbsp;");
										if ($downloadable) {
											echo("<input type='image' src='../tree/page-file_play.gif' name='download[]' id='download' value='Download' title='" . gettext("Download current logfile") . "' alt='download' width='17' height='17' border='0' /> &nbsp;");
										}
										if ($clearable) {
											echo("<input type='image' src='../tree/page-file_x.gif' name='clear[]' id='clear' value='Clear' title='" . gettext("Clear current logfile") . "' alt='clear' width='17' height='17' border='0' />");
										}
									?>
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
</table>
</form>

<?php if (!isset($_POST['file'])): ?>
<script type="text/javascript">
//<![CDATA[
	document.getElementById("logFile").selectedIndex=-1;
//]]>
</script>
<?php endif; ?>
<?php include('fend.inc'); ?>
</body>
</html>