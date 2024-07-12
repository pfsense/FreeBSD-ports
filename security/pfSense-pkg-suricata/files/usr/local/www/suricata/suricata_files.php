<?php
/*
 * suricata_files.php
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

global $g;

function suricata_escape_filter_regex($filtertext) {
	/* If the caller (user) has not already put a backslash before a slash, to escape it in the regex, */
	/* then this will do it. Take out any "\/" already there, then turn all ordinary "/" into "\/".  */
	return str_replace('/', '\/', str_replace('\/', '/', $filtertext));
}

function suricata_match_filter_field($flent, $fields, $exact_match = FALSE) {
	foreach ($fields as $key => $field) {
		if ($field == null)
			continue;

		// Only match whole field string when
		// performing an exact match.
		if ($exact_match) {
			if ($flent[$key] == $field) {
				return true;
			}
			else {
				return false;
			}
		}

		if ((strpos($field, '!') === 0)) {
			$field = substr($field, 1);
			$field_regex = suricata_escape_filter_regex($field);
			if (@preg_match("/{$field_regex}/i", $flent[$key]))
				return false;
		}
		else {
			$field_regex = suricata_escape_filter_regex($field);
			if (!@preg_match("/{$field_regex}/i", $flent[$key]))
				return false;
		}
	}
	return true;
}

if (isset($_POST['instance']) && is_numericint($_POST['instance']))
	$instanceid = $_POST['instance'];
// This is for the auto-refresh so we can  stay on the same interface
elseif (isset($_GET['instance']) && is_numericint($_GET['instance']))
	$instanceid = $_GET['instance'];

if (is_null($instanceid))
	$instanceid = 0;

$a_instance = config_get_path("installedpackages/suricata/rule/{$id}", []);
$suricata_uuid = $a_instance['uuid'];
$if_real = get_real_interface($a_instance['interface']);
$suricatalogdir = SURICATALOGDIR;

$pconfig = array();
$pconfig['frefresh'] = config_get_path('installedpackages/suricata/fileblocks/frefresh', 'on');
$pconfig['filenumber'] = config_get_path('installedpackages/suricata/fileblocks/filenumber', 250);
$fnentries = $pconfig['filenumber'];
if (!is_numeric($fnentries)) {
	$fnentries = 250;
}	

# --- AJAX REVERSE DNS RESOLVE Start ---
if (isset($_POST['resolve'])) {
	$ip = strtolower($_POST['resolve']);
	$res = (is_ipaddr($ip) ? gethostbyaddr($ip) : '');
	if (strpos($res, 'xn--') !== false) {
		$res = idn_to_utf8($res);
	}

	if ($res && $res != $ip)
		$response = array('resolve_ip' => $ip, 'resolve_text' => $res);
	else
		$response = array('resolve_ip' => $ip, 'resolve_text' => gettext("Cannot resolve"));

	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}
# --- AJAX REVERSE DNS RESOLVE End ---

# --- AJAX GEOIP CHECK Start ---
if (isset($_POST['geoip'])) {
	$ip = strtolower($_POST['geoip']);
	if (is_ipaddr($ip)) {
		$url = "https://api.hackertarget.com/geoip/?q={$ip}";
		$conn = curl_init("https://api.hackertarget.com/geoip/?q={$ip}");
		curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($conn, CURLOPT_FRESH_CONNECT,  true);
		curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
		set_curlproxy($conn);
		$res = curl_exec($conn);
		curl_close($conn);
	} else {
		$res = '';
	}

	if ($res && $res != $ip && !preg_match('/error/', $res))
		$response = array('geoip_text' => $res);
	else
		$response = array('geoip_text' => gettext("Cannot check {$ip}"));

	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}
# --- AJAX GEOIP CHECK End ---

# Check for persisted filtering of alerts log entries and populate
# the required $filterfieldsarray when persisting filtered entries.
if ($_POST['persist_filter'] == "yes" && !empty($_POST['persist_filter_content'])) {
	$filterlogentries = TRUE;
	$persist_filter_log_entries = "yes";
	$filterlogentries_exact_match = $_POST['persist_filter_exact_match'];
	$filterfieldsarray = json_decode($_POST['persist_filter_content'], TRUE);
}
else {
	$filterlogentries = FALSE;
	$persist_filter_log_entries = "";
	$filterfieldsarray = array();
}

if ($_POST['filterlogentries_submit']) {
	// Set flags for filtering alert log entries
	$filterlogentries = TRUE;
	$persist_filter_log_entries = "yes";

	// Set 'exact match only' flag if enabled
	if ($_POST['filterlogentries_exact_match'] == 'on') {
		$filterlogentries_exact_match = TRUE;
	} else {
		$filterlogentries_exact_match = FALSE;
	}

	// -- IMPORTANT --
	// Note the order of these fields must match the order decoded from the alerts log
	$filterfieldsarray = array();
	$filterfieldsarray['time'] = $_POST['filterlogentries_time'] ? $_POST['filterlogentries_time'] : null;
	$filterfieldsarray['proto'] = $_POST['filterlogentries_protocol'] ? $_POST['filterlogentries_protocol'] : null;
	$filterfieldsarray['app_proto'] = null;
	// Remove any zero-length spaces added to the IP address that could creep in from a copy-paste operation
	$filterfieldsarray['src_ip'] = $_POST['filterlogentries_sourceipaddress'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_sourceipaddress']) : null;
	$filterfieldsarray['src_port'] = $_POST['filterlogentries_sourceport'] ? $_POST['filterlogentries_sourceport'] : null;
	// Remove any zero-length spaces added to the IP address that could creep in from a copy-paste operation
	$filterfieldsarray['dest_ip'] = $_POST['filterlogentries_destinationipaddress'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_destinationipaddress']) : null;
	$filterfieldsarray['dest_port'] = $_POST['filterlogentries_destinationport'] ? $_POST['filterlogentries_destinationport'] : null;
	$filterfieldsarray['size'] = $_POST['filterlogentries_size'] ? $_POST['filterlogentries_size'] : null;
	$filterfieldsarray['filename'] = $_POST['filterlogentries_filename'] ? $_POST['filterlogentries_filename'] : null;
}

if ($_POST['filterlogentries_clear']) {
	$filterfieldsarray = array();
	$filterlogentries = TRUE;
	$persist_filter_log_entries = "";
}

if ($_POST['save']) {
	config_set_path('installedpackages/suricata/fileblocks/frefresh', $_POST['frefresh'] ? 'on' : 'off');
	config_set_path('installedpackages/suricata/fileblocks/filenumber', $_POST['filenumber']);
	write_config("Suricata pkg: saved change to FILES tab configuration.");
	header("Location: /suricata/suricata_files.php?instance={$instanceid}");
	exit;
}

if ($_POST['mode']=='unblock' && $_POST['ip']) {
	if (is_ipaddr($_POST['ip'])) {
		exec("/sbin/pfctl -t {$suri_pf_table} -T delete {$_POST['ip']}");
		$savemsg = gettext("Host IP address {$_POST['ip']} has been removed from the Blocked Table.");
	}
}

function build_instance_list() {

	$list = array();

	foreach (config_get_path('installedpackages/suricata/rule', []) as $id => $instance) {
		$list[$id] = '(' . convert_friendly_interface_to_friendly_descr($instance['interface']) . ') ' . $instance['descr'];
	}

	return($list);
}

$pglinks = array("", "/suricata/suricata_interfaces.php", "@self");
$pgtitle = array("Services", "Suricata", "Files");
include_once("head.inc");

/* refresh every 60 secs */
if ($pconfig['frefresh'] == 'on')
	print '<meta http-equiv="refresh" content="60;url=/suricata/suricata_files.php?instance=' . $instanceid . '" />';

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Files"), true, "/suricata/suricata_files.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$instanceid}");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

$form = new Form(false);
$form->setAttribute('name', 'formfile')->setAttribute('id', 'formfile');

$section = new Form_Section('Files Log View Settings');

$section->addInput(new Form_Select(
	'instance',
	'Instance to View',
	$instanceid,
	build_instance_list()
))->setHelp('Choose which instance alerts you want to inspect.');

$section->addInput(new Form_StaticText(
	'NOTE',
	'For this feature to work, the EVE JSON log with the FILE Output Type and ' .
	'the Tracked-Files Checksum must be enabled. Optionally, File-Store can be enabled to download the captured files. ' .
	'Supported protocols are: HTTP, SMTP, FTP, NFS, SMB'
));

$group = new Form_Group('Save Settings');

$group->add(new Form_Button(
	'save',
	'Save',
	null,
	'fa-solid fa-save'
))->removeClass('btn-default')->addClass('btn-success btn-sm')
  ->setHelp('Save auto-refresh and view settings');

$group->add(new Form_Checkbox(
	'frefresh',
	null,
	'Refresh',
	$pconfig['frefresh'] == 'on' ? true:false,
	'on'
))->setHelp('Default is ON');

$group->add(new Form_Input(
	'filenumber',
	'File Entries',
	'number',
	$fnentries
	))->setHelp('Number of files to display. Default is 250');

$section->add($group);

$form->add($section);

// ========== Log filter Panel =============================================================
if ($filterlogentries && count($filterfieldsarray)) {
	$section = new Form_Section("Files Log View Filter", "filefilter", COLLAPSIBLE|SEC_OPEN);
}
else {
	$section = new Form_Section("Files Log View Filter", "filefilter", COLLAPSIBLE|SEC_CLOSED);
}

$group = new Form_Group('');

$group->add(new Form_Input(
	'filterlogentries_time',
	'Date',
	'text',
	$filterfieldsarray['time']
))->setHelp("Date");

$group->add(new Form_Input(
	'filterlogentries_sourceipaddress',
	'Source IP Address',
	'text',
	$filterfieldsarray['src_ip']
))->setHelp("Source IP Address");

$group->add(new Form_Input(
	'filterlogentries_sourceport',
	'Source Port',
	'text',
	$filterfieldsarray['src_port']
))->setHelp("Source Port");

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Input(
	'filterlogentries_size',
	'Size',
	'text',
	$filterfieldsarray['size']
))->setHelp("Size");

$group->add(new Form_Input(
	'filterlogentries_destinationipaddress',
	'Destination IP Address',
	'text',
	$filterfieldsarray['dest_ip']
))->setHelp("Destination IP Address");

$group->add(new Form_Input(
	'filterlogentries_destinationport',
	'Port',
	'text',
	$filterfieldsarray['dest_port']
))->setHelp("Destination Port");

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Input(
	'filterlogentries_protocol',
	'Protocol',
	'text',
	$filterfieldsarray['proto']
))->setHelp("Protocol");

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Input(
	'filterlogentries_filename',
	'Filename',
	'text',
	$filterfieldsarray['filename']
))->setHelp("Filename");

$group->add(new Form_Checkbox(
	'filterlogentries_exact_match',
	'Exact Match Only',
	null,
	$filterlogentries_exact_match == "on" ? true:false,
	'on'
))->setHelp('Exact Match');

$section->add($group);

$group = new Form_Group('');
$group->add(new Form_Button(
	'filterlogentries_submit',
	'Apply Filter',
	null,
	'fa-solid fa-filter'
))->removeClass("btn-primary btn-default")
  ->addClass("btn-success btn-sm");

$group->add(new Form_Button(
	'filterlogentries_clear',
	'Clear Filter',
	null,
	'fa-regular fa-trash-can'
))->removeclass("btn-primary btn-default")
  ->addClass("btn-danger no-confirm btn-sm");

$section->add($group);

$form->add($section);

// ========== Hidden controls ==============
$form->addGlobal(new Form_Input(
	'ip',
	null,
	'hidden',
	''
));

$form->addGlobal(new Form_Input(
	'mode',
	'mode',
	'hidden',
	''
));

if ($persist_filter_log_entries == "yes") {
	$form->addGlobal(new Form_Input(
		'persist_filter',
		'persist_filter',
		'hidden',
		$persist_filter_log_entries
	));

	$form->addGlobal(new Form_Input(
		'persist_filter_exact_match',
		'persist_filter_exact_match',
		'hidden',
		$filterlogentries_exact_match
	));

	// Pass the $filterfieldsarray variable as serialized data
	$form->addGlobal(new Form_Input(
		'persist_filter_content',
		'persist_filter_content',
		'hidden',
		json_encode($filterfieldsarray)
	));
}

print($form);

if ($filterlogentries && count($filterfieldsarray)) {
	$sectitle = sprintf("Last %s File Entries. (Most recent entries are listed first)  ** FILTERED VIEW **  clear filter to see all entries", $fnentries);
} else {
	$sectitle = sprintf("Last %s File Entries. (Most recent entries are listed first)", $fnentries);
}

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=sprintf($sectitle)?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
			   <tr class="sortableHeaderRowIdentifier text-nowrap">
				<th data-sortable-type="date"><?=gettext("Date"); ?></th>
				<th><?=gettext("Proto"); ?></th>
				<th><?=gettext("App"); ?></th>
				<th><?=gettext("Src"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("SPort"); ?></th>
				<th><?=gettext("Dst"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("DPort"); ?></th>
				<th><?=gettext("Size"); ?></th>
				<th data-sortable-type="alpha"><?=gettext("Filename"); ?></th>
			   </tr>
			</thead>
			<tbody>
	<?php

/* make sure alert file exists */
if (file_exists("{$g['varlog_path']}/suricata/suricata_{$if_real}{$suricata_uuid}/eve.json")) {
	exec("/usr/bin/grep filename {$g['varlog_path']}/suricata/suricata_{$if_real}{$suricata_uuid}/eve.json | /usr/bin/tail -{$fnentries} -r > {$g['tmp_path']}/files_suricata{$suricata_uuid}");
	if (file_exists("{$g['tmp_path']}/files_suricata{$suricata_uuid}")) {
		$tmpblocked = array_flip(suricata_get_blocked_ips());
		$counter = 0;

		$fd = fopen("{$g['tmp_path']}/files_suricata{$suricata_uuid}", "r");
		$buf = "";
		while (($buf = fgets($fd)) !== FALSE) {
			$fields = array();
			$tmp = array();

			$fields = json_decode($buf, true);

			/**************************************************************/
			/* Parse eve.json log entry to find the parts we want to display */
			/**************************************************************/

			// Create a DateTime object from the event timestamp that
			// we can use to easily manipulate output formats.
			$event_tm = date_create_from_format("Y-m-d\TH:i:s.uP", $fields['timestamp']);

			// PHP date_format issues a bogus warning even though $event_tm really is an object
			// Suppress it with @
			@$fields['timestamp'] = date_format($event_tm, "m/d/Y") . " " . date_format($event_tm, "H:i:s");
			$fields['filename'] = $fields['fileinfo']['filename'];

			if ($filterlogentries && !suricata_match_filter_field($fields, $filterfieldsarray, $filterlogentries_exact_match)) {
				continue;
			}

			/* Time */
			@$file_time = date_format($event_tm, "H:i:s");
			/* Date */
			@$file_date = date_format($event_tm, "m/d/Y");

			/* Size */
			if ($fields['fileinfo']['size'] > 1048576) {
				$file_size = round(intval($fields['fileinfo']['size'])/1048576) . ' M';
			} elseif ($fields['fileinfo']['size'] > 1024) {
				$file_size = round(intval($fields['fileinfo']['size'])/1024) . ' K';
			} else {
				$file_size = $fields['fileinfo']['size'] . ' B';
			}

			/* Protocol */
			$file_proto = $fields['proto'];

			/* App level protocol */
			$file_app = strtoupper($fields['app_proto']);

			/* IP SRC */
			$file_ip_src = $fields['src_ip'];
			/* Add zero-width space as soft-break opportunity after each colon if we have an IPv6 address */
			$file_ip_src = str_replace(":", ":&#8203;", $file_ip_src);
			/* Add Reverse DNS lookup icon */
			$file_ip_src .= '<br /><i class="fa-solid fa-search" onclick="javascript:resolve_with_ajax(\'' . $fields['src_ip'] . '\');" title="';
			$file_ip_src .= gettext("Resolve host via reverse DNS lookup") . "\"  alt=\"Icon Reverse Resolve with DNS\" ";
			$file_ip_src .= " style=\"cursor: pointer;\"></i>";
			/* Add GeoIP check icon */
			if (!is_private_ip($fields['src_ip']) && (substr($fields['src_ip'], 0, 2) != 'fc') &&
			    (substr($fields['src_ip'], 0, 2) != 'fd')) {
				$file_ip_src .= '&nbsp;&nbsp;<i class="fa-solid fa-globe" onclick="javascript:geoip_with_ajax(\'' . $fields['src_ip'] . '\');" title="';
				$file_ip_src .= gettext("Check host GeoIP data") . "\"  alt=\"Icon Check host GeoIP\" ";
				$file_ip_src .= " style=\"cursor: pointer;\"></i>";
			}

			/* Add icon for auto-removing from Blocked Table if required */
			if (isset($tmpblocked[$fields['src_ip']])) {
				$file_ip_src .= "&nbsp;&nbsp;<i class=\"fa-solid fa-times icon-pointer text-danger\" onClick=\"$('#ip').val('{$fields['src_ip']}');$('#mode').val('unblock');$('#formfile').submit();\"";
				$file_ip_src .= ' title="' . gettext("Remove host from Blocked Table") . '"></i>';
			}

			/* IP SRC Port */
			$file_src_p = $fields['src_port'];

			/* IP DST */
			$file_ip_dst = $fields['dest_ip'];
			/* Add zero-width space as soft-break opportunity after each colon if we have an IPv6 address */
			$file_ip_dst = str_replace(":", ":&#8203;", $file_ip_dst);
			/* Add Reverse DNS lookup icons */
			$file_ip_dst .= "<br /><i class=\"fa-solid fa-search\" onclick=\"javascript:resolve_with_ajax('{$fields['dest_ip']}');\" title=\"";
			$file_ip_dst .= gettext("Resolve host via reverse DNS lookup") . "\" alt=\"Icon Reverse Resolve with DNS\" ";
			$file_ip_dst .= " style=\"cursor: pointer;\"></i>";
			/* Add GeoIP check icon */
			if (!is_private_ip($fields['dest_ip']) && (substr($fields['dest_ip'], 0, 2) != 'fc') &&
			    (substr($fields['dest_ip'], 0, 2) != 'fd')) {
				$file_ip_dst .= '&nbsp;&nbsp;<i class="fa-solid fa-globe" onclick="javascript:geoip_with_ajax(\'' . $fields['dest_ip'] . '\');" title="';
				$file_ip_dst .= gettext("Check host GeoIP data") . "\"  alt=\"Icon Check host GeoIP\" ";
				$file_ip_dst .= " style=\"cursor: pointer;\"></i>";
			}

			/* Add icon for auto-removing from Blocked Table if required */
			if (isset($tmpblocked[$fields['dest_ip']])) {
				$file_ip_dst .= '&nbsp;&nbsp;<i name="todelete[]" class="fa-solid fa-times icon-pointer text-danger" onClick="$(\'#ip\').val(\'' . $fields['dest_ip'] . '\');$(\'#mode\').val(\'unblock\');$(\'#formfile\').submit();" ';
				$file_ip_dst .= ' title="' . gettext("Remove host from Blocked Table") . '"></i>';
			}

			/* IP DST Port */
			$file_dst_p = $fields['dest_port'];

			/* Filename */
			$file_name = $fields['fileinfo']['filename'];

			/* File Hash */
			if (isset($fields['fileinfo']['sha256']) && !empty($fields['fileinfo']['sha256'])) {
				$file_hash = $fields['fileinfo']['sha256'];
			} elseif (isset($fields['fileinfo']['sha1']) && !empty($fields['fileinfo']['sha1'])) {
				$file_hash = $fields['fileinfo']['sha1'];
			} elseif (isset($fields['fileinfo']['md5']) && !empty($fields['fileinfo']['md5'])) {
				$file_hash = $fields['fileinfo']['md5'];
			} else {
				$file_hash = 'none';
			}

			$file_check = '<a class="fa-solid fa-info icon-pointer icon-primary" title="Click for File Check."' .
				    'target="_blank" href="/suricata/suricata_filecheck.php?filehash=' . $file_hash .
				    '&uuid=' . $suricata_uuid . '&filename=' . urlencode($file_name) . 
				    '&filesize=' . urlencode($file_size) . '"></a>';
	?>
			<tr>
				<td><?=$file_date;?><br/><?=$file_time;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$file_proto;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$file_app;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$file_ip_src;?></td>
				<td><?=$file_src_p;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$file_ip_dst;?></td>
				<td><?=$file_dst_p;?></td>
				<td><?=$file_size;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=htmlspecialchars($file_name);?>&nbsp;<?=$file_check;?></td>
			</tr>
	<?php
			$counter++;
		}
		unset($fields, $buf, $tmp);
		fclose($fd);
		unlink_if_exists("{$g['tmp_path']}/files_suricata{$suricata_uuid}");
	}
}
	?>
			</tbody>
		</table>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
function enable_showFilter() {
	document.getElementById("filter_enable_row").style.display="none";
	document.getElementById("filter_options_row").style.display="table-row";
}

function enable_hideFilter() {
	document.getElementById("filter_enable_row").style.display="table-row";
	document.getElementById("filter_options_row").style.display="none";
}

</script>

<!-- The following AJAX code was borrowed from the diag_logs_filter.php -->
<!-- file in pfSense.  See copyright info at top of this page.          -->
<script type="text/javascript">
//<![CDATA[

function resolve_with_ajax(ip_to_resolve) {
	var url = "/suricata/suricata_files.php";

	$.ajax(
		url,
		{
			type: 'post',
			dataType: 'json',
			data: {
				resolve: ip_to_resolve,
			      },
			complete: resolve_ip_callback
		});
}

function resolve_ip_callback(transport) {
	var response = $.parseJSON(transport.responseText);
	var msg = 'IP address "' + response.resolve_ip + '" resolves to\n';
	alert(msg + 'host "' + htmlspecialchars(response.resolve_text) + '"');
}

function geoip_with_ajax(ip_to_check) {
	var url = "/suricata/suricata_files.php";

	$.ajax(
		url,
		{
			type: 'post',
			dataType: 'json',
			data: {
				geoip: ip_to_check,
			      },
			complete: geoip_callback
		});
}

function geoip_callback(transport) {
	var response = $.parseJSON(transport.responseText);
	alert(htmlspecialchars(response.geoip_text));
}

// From http://stackoverflow.com/questions/5499078/fastest-method-to-escape-html-tags-as-html-entities
function htmlspecialchars(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
}

events.push(function() {

	//-- Click handlers ------------------------------------------------------
	$('#instance').on('change', function() {
		$('#formfile').submit();
	});

});

//]]>
</script>
<?php
include("foot.inc");
?>

