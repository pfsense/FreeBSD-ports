<?php
/*
 * pfblockerng_log.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2019 BBcan177@gmail.com
 * All rights reserved.
 *
 * Portions of this code are based on original work done for the
 * Snort package for pfSense from the following contributors:
 *
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2004 Manuel Kasper (BSD 2 clause)
 * All rights reserved.
 *
 * Adapted for Suricata by:
 * Copyright (c) 2016 Bill Meeks
 * All rights reserved.
 *
 * Javascript and Integration modifications by J. Nieuwenhuizen and J. Van Breedam
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
	
	asort($log_filenames);
	return $log_filenames;
}

/*	Define logtypes:
		name	=>	Displayname of the type
		ext	=>	Log extentions (array for multiple extentions)
		logdir	=>	Log directory
		clear	=>	Add clear button (TRUE/FALSE)
		download=>	Add download button (TRUE/FALSE)	*/

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
			'GeoIP'		=> array('name'		=> 'Country Files',
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


// Function to escape Log viewer output
function pfb_htmlspecialchars($line) {
	return htmlspecialchars($line, ENT_NOQUOTES);
}

// Function to validate file/path
function pfb_validate_filepath($validate, $pfb_logtypes) {

	$allowed_path = array();
	foreach ($pfb_logtypes as $type) {
		$allowed_path[$type['logdir']] = '';
	}

	$path = pathinfo($validate, PATHINFO_DIRNAME) . '/';
	$file = basename($validate);

	if ($path == '/var/unbound/' && $file != 'pfb_dnsbl.conf') {
		return FALSE;
	}
	return isset($allowed_path[$path]);
}

$pconfig = array();
if ($_POST) {
	$pconfig = $_POST;
}

// Send logfile to screen
if ($_REQUEST['ajax']) {
	clearstatcache();
	$pfb_logfilename = htmlspecialchars($_REQUEST['file']);
	if (!pfb_validate_filepath($pfb_logfilename, $pfb_logtypes)) {
		print ("|0|" . gettext('Invalid filename/path') . ".|");
		exit;
	}

	// Load log
	if ($_REQUEST['action'] == 'load') {
		if (!file_exists($pfb_logfilename)) {
			print ("|3|" . gettext('Log file is empty or does not exist') . ".|");
		} else {
			$data = implode(array_map('pfb_htmlspecialchars', @file($pfb_logfilename)));
			if ($data === false) {
				print ("|1|" . gettext('Failed to read log file') . ".|");
			} else {
				$data = base64_encode($data);
				print ("|0|" . $pfb_logfilename . "|" . $data . "|");
			}
		}
		exit;
	}
}

// Download/Clear logfile
if ($pconfig['logFile'] && ($pconfig['download'] || $pconfig['clear'])) {
	$s_logfile = htmlspecialchars($pconfig['logFile']);
	if (!pfb_validate_filepath($s_logfile, $pfb_logtypes)) {
		print ("|0|" . gettext('Invalid filename/path') . ".|");
		exit;
	}

	// Clear selected file
	if ($pconfig['clear']) {
		unlink_if_exists($s_logfile);
	}

	// Download log
	if ($pconfig['download']) {
		if (file_exists($s_logfile)) {
			session_cache_limiter('public');
			$fd = @fopen($s_logfile, "rb");
			header("Content-Type: application/octet-stream");
			header("Content-Length: " . filesize($s_logfile));
			header("Content-Disposition: attachment; filename=\"" .
				trim(htmlentities(basename($s_logfile))) . "\"");
			if (isset($_SERVER['HTTPS'])) {
				header('Pragma: ');
				header('Cache-Control: ');
			} else {
				header("Pragma: private");
				header("Cache-Control: private, must-revalidate");
			}
			@fpassthru($fd);
			@fclose($fd);
			exit;
		}
	}
} else {
	$s_logfile = '';
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('Log Browser'));
include_once('head.inc');

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array	= array();
$tab_array[]	= array(gettext("General"), false, "/pkg_edit.php?xml=pfblockerng.xml");
$tab_array[]	= array(gettext("Update"), false, "/pfblockerng/pfblockerng_update.php");
$tab_array[]	= array(gettext("Alerts"), false, "/pfblockerng/pfblockerng_alerts.php");
$tab_array[]	= array(gettext("Reputation"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_reputation.xml");
$tab_array[]	= array(gettext("IPv4"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v4lists.xml");
$tab_array[]	= array(gettext("IPv6"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v6lists.xml");
$tab_array[]	= array(gettext("DNSBL"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_dnsbl.xml");
$tab_array[]	= array(gettext("GeoIP"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_TopSpammers.xml");
$tab_array[]	= array(gettext("Logs"), true, "/pfblockerng/pfblockerng_log.php");
$tab_array[]	= array(gettext("Sync"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_sync.xml");
display_top_tabs($tab_array, true);

// Create Form
$form = new Form(false);
$form->setAction('/pfblockerng/pfblockerng_log.php');

// Build 'Shortcut Links' section
$section = new Form_Section('Log/File Browser selections');
$section->addInput(new Form_StaticText(
	NULL,
	'<small>'
	. '<a href="/firewall_aliases.php" target="_blank">Firewall Alias</a>&emsp;'
	. '<a href="/firewall_rules.php" target="_blank">Firewall Rules</a>&emsp;'
	. '<a href="/status_logs_filter.php" target="_blank">Firewall Logs</a></small>'
));

// Collect main logtypes
$options = array();
foreach ($pfb_logtypes as $type => $logtype) {
	$options[$type] = $logtype['name'];
}

$section->addInput(new Form_Select(
	'logtype',
	'Log/File type:',
	$pconfig['logtype'],
	$options
))->setHelp('Choose which type of log/file you want to view.');

// Collect selected logs
$logs = array();
$clearable = $downloadable = FALSE;
$selected = $pconfig['logtype'] ?: 'defaultlogs';
$pfb_sel = $pfb_logtypes[$selected];

if (isset($pfb_sel['logs'])) {
	$logs = $pfb_sel['logs'];
} else {
	$logs = getlogs($pfb_sel['logdir'], $pfb_sel['ext']);
}

$logdir		= $pfb_sel['logdir'] ?: '/var/db/pfblockerng';
$clearable	= $pfb_sel['clear'] ?: FALSE;
$downloadable	= $pfb_sel['download'] ?: FALSE;

// Add filepath to selected logs
$options = array();
foreach ($logs as $id => $log) {
	if ($id == 'logs' && is_array($log)) {
		foreach ($log as $opt) {
			$options["{$logdir}" . "{$opt}"] = $opt;
		}
	} else {
		$options["{$logdir}" . "{$log}"] = $log;
	}
}

$section->addInput(new Form_Select(
	'logFile',
	'Log/File selection:',
	$pconfig['logFile'],
	$options
))->setHelp('Choose which log/file you want to view.');
$form->add($section);


// Add appropriate buttons for logfile
$logbtns = '&emsp;&nbsp;<i class="fa fa-refresh icon-pointer icon-primary" onclick="loadFile()" title="Refresh current logfile."></i>';
if ($downloadable) {
	$logbtns .= '&emsp;<i class="fa fa-download icon-pointer icon-primary" name="download[]" id="downloadicon" title="Download current logfile."></i>';
}
if ($clearable) {
	$logbtns .= '&emsp;<i class="fa fa-trash icon-pointer icon-primary" name="clear[]" id="clearicon" title="Clear selected logfile."></i>';
}

$section = new Form_Section('Log/File Contents');
$section->addInput(new Form_StaticText(
	NULL,
	'<div style="display:none;" id="fileStatusBox"><strong id="fileStatus"></strong></div>'
	. '<div style="display:none;" id="filePathBox"><strong>Log/File Path:&emsp;</strong>'
	. '<div style="display:inline;" id="fbTarget"></div>'
	. '<div style="display:inline; margin-right:10px;" class="pull-right" id="fileRefreshBtn">'
	. $logbtns . '</div></div>'
));
$form->add($section);


$section = new Form_Section('Log');
$section->addInput(new Form_Textarea(
	'fileContent',
	NULL,
	''
))->removeClass('form-control')->addClass('row-fluid col-sm-12')->setAttribute('rows', '30')->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa;');
$form->add($section);

$form->addGlobal(new Form_Input('download', 'download', 'hidden', ''));
$form->addGlobal(new Form_Input('clear', 'clear', 'hidden', ''));

$form->addGlobal(new Form_Input('action', 'action', 'hidden', ''));
$form->addGlobal(new Form_Input('load', 'load', 'hidden', ''));
$form->addGlobal(new Form_Input('file', 'file', 'hidden', ''));

$form->addGlobal(new Form_Input('fileStatus', 'fileStatus', 'hidden', ''));
$form->addGlobal(new Form_Input('fileStatusBox', 'fileStatusBox', 'hidden', ''));
$form->addGlobal(new Form_Input('filePathBox', 'filePathBox', 'hidden', ''));
$form->addGlobal(new Form_Input('fbTarget', 'fbTarget', 'hidden', ''));
$form->addGlobal(new Form_Input('fileRefreshBtn', 'fileRefreshBtn', 'hidden', ''));

print($form);
?>

<script type="text/javascript">	
//<![CDATA[

var toggle = false;

function loadFile() {
	$("#fileStatus").html("<?=gettext("Loading file"); ?> ...");
	$("#fileStatusBox").show(250);
	$("#filePathBox").show(250);
	$("#fbTarget").html("");
	var ajaxRequest

	ajaxRequest = $.ajax({
			url: "/pfblockerng/pfblockerng_log.php",
			type: "post",
			data: { ajax: "ajax",
					action: "load",
					file: $("#logFile").val()
				},
			complete: loadComplete
	});
}

function loadComplete(req) {
	$("#fileContent").show(250);
	var values = req.responseText.split("|");
	values.shift(); values.pop();

	if(values.shift() == "0") {
		var file = values.shift();
		var fileContent = window.atob(values.join("|"));
		$("#fileStatus").html("<?=gettext("File successfully loaded"); ?>.");
		$("#fbTarget").html(file);
		$("#fileRefreshBtn").show();
		$("#fileContent").prop("disabled", false);
		$("#fileContent").val(fileContent);
	} else {
		$("#fileStatus").html(values[0]);
		$("#fbTarget").html("");
		$("#fileRefreshBtn").hide();
		$("#fileContent").val("");
		$("#fileContent").prop("disabled", true);
	}
}

events.push(function() {

	// Select log type and clear download variable
	$('#logtype').on('click', function() {
		$('#download').val('');
	});
	$('#logtype').on('change', function() {
		$('form').submit();
	});

	// Open selected logfile
	$('#logFile').on('click', function() {
		// Toggle used to prevent opening the logfile on first click of dropdown menu
		if (toggle) {
			loadFile();
			// Scroll to the bottom of the page
			$("html, body").animate({ scrollTop: $(document).height() }, 1000);
		}
		toggle = ! toggle
	});

	// Download selected logfile 
	$('[id^=downloadicon]').click(function(event) {
		$('#download').val('download');
		$('#fileContent').val('');
		$('form').submit();
	});

	// Clear selected logfile
	$('[id^=clearicon]').click(function(event) {
		$('#clear').val('clear');
		$('#fileContent').val('');
		$('form').submit();
	});
});
//]]>
</script>
<?php include("foot.inc"); ?>
