<?php
/* $Id$ */
/*
 * snort_alerts.php
 * part of pfSense
 *
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2012 Ermal Luci
 * Copyright (C) 2014 Jim Pingle jim@pingle.org
 * Copyright (C) 2015 Bill Meeks
 * All rights reserved.
 *
 * Modified for the Pfsense snort package v. 1.8+
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
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

$snortalertlogt = $config['installedpackages']['snortglobal']['snortalertlogtype'];
$supplist = array();
$snortlogdir = SNORTLOGDIR;
$filterlogentries = FALSE;

function snort_is_alert_globally_suppressed($list, $gid, $sid) {

	/************************************************/
	/* Checks the passed $gid:$sid to see if it has */
	/* been globally suppressed.  If true, then any */
	/* "track by_src" or "track by_dst" options are */
	/* disabled since they are overridden by the    */
	/* global suppression of the $gid:$sid.         */
	/************************************************/

	/* If entry has a child array, then it's by src or dst ip. */
	/* So if there is a child array or the keys are not set,   */
	/* then this gid:sid is not globally suppressed.           */
	if (is_array($list[$gid][$sid]))
		return false;
	elseif (!isset($list[$gid][$sid]))
		return false;
	else
		return true;
}

function snort_add_supplist_entry($suppress) {

	/************************************************/
	/* Adds the passed entry to the Suppress List   */
	/* for the active interface.  If a Suppress     */
	/* List is defined for the interface, it is     */
	/* used.  If no list is defined, a new default  */
	/* list is created using the interface name.    */
	/*                                              */
	/* On Entry:                                    */
	/*   $suppress --> suppression entry text       */
	/*                                              */
	/* Returns:                                     */
	/*   TRUE if successful or FALSE on failure     */
	/************************************************/

	global $config, $a_instance, $instanceid;

	if (!is_array($config['installedpackages']['snortglobal']['suppress']))
		$config['installedpackages']['snortglobal']['suppress'] = array();
	if (!is_array($config['installedpackages']['snortglobal']['suppress']['item']))
		$config['installedpackages']['snortglobal']['suppress']['item'] = array();
	$a_suppress = &$config['installedpackages']['snortglobal']['suppress']['item'];

	$found_list = false;

	/* If no Suppress List is set for the interface, then create one with the interface name */
	if (empty($a_instance[$instanceid]['suppresslistname']) || $a_instance[$instanceid]['suppresslistname'] == 'default') {
		$s_list = array();
		$s_list['uuid'] = uniqid();
		$s_list['name'] = $a_instance[$instanceid]['interface'] . "suppress" . "_" . $s_list['uuid'];
		$s_list['descr']  =  "Auto-generated list for Alert suppression";
		$s_list['suppresspassthru'] = base64_encode($suppress);
		$a_suppress[] = $s_list;
		$a_instance[$instanceid]['suppresslistname'] = $s_list['name'];
		$found_list = true;
		$list_name = $s_list['name'];
	} else {
		/* If we get here, a Suppress List is defined for the interface so see if we can find it */
		foreach ($a_suppress as $a_id => $alist) {
			if ($alist['name'] == $a_instance[$instanceid]['suppresslistname']) {
				$found_list = true;
				$list_name = $alist['name'];
				if (!empty($alist['suppresspassthru'])) {
					$tmplist = base64_decode($alist['suppresspassthru']);
					$tmplist .= "\n{$suppress}";
					$alist['suppresspassthru'] = base64_encode($tmplist);
					$a_suppress[$a_id] = $alist;
				}
				else {
					$alist['suppresspassthru'] = base64_encode($suppress);
					$a_suppress[$a_id] = $alist;
				}
			}
		}
	}

	/* If we created a new list or updated an existing one, save the change, */
	/* tell Snort to load it, and return true; otherwise return false.       */
	if ($found_list) {
		write_config("Snort pkg: modified Suppress List {$list_name}.");
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();
		snort_reload_config($a_instance[$instanceid]);
		return true;
	}
	else
		return false;
}

function snort_escape_filter_regex($filtertext) {
	/* If the caller (user) has not already put a backslash before a slash, to escape it in the regex, */
	/* then this will do it. Take out any "\/" already there, then turn all ordinary "/" into "\/".  */
	return str_replace('/', '\/', str_replace('\/', '/', $filtertext));
}

function snort_match_filter_field($flent, $fields) {
	foreach ($fields as $key => $field) {
		if ($field == null)
			continue;
		if ((strpos($field, '!') === 0)) {
			$field = substr($field, 1);
			$field_regex = snort_escape_filter_regex($field);
			if (@preg_match("/{$field_regex}/i", $flent[$key]))
				return false;
		}
		else {
			$field_regex = snort_escape_filter_regex($field);
			if (!@preg_match("/{$field_regex}/i", $flent[$key]))
				return false;
		}
	}
	return true;
}


if (isset($_POST['instance']) && is_numericint($_POST['instance']))
	$instanceid = $_POST['instance'];
elseif (isset($_GET['instance']) && is_numericint($_GET['instance']))
	$instanceid = htmlspecialchars($_GET['instance']);
elseif (isset($_POST['id']))
	$instanceid = $_POST['id'];

if (empty($instanceid) || !is_numericint($instanceid))
	$instanceid = 0;

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
$a_instance = &$config['installedpackages']['snortglobal']['rule'];
$snort_uuid = $a_instance[$instanceid]['uuid'];
$if_real = get_real_interface($a_instance[$instanceid]['interface']);

// Load up the arrays of force-enabled and force-disabled SIDs
$enablesid = snort_load_sid_mods($a_instance[$instanceid]['rule_sid_on']);
$disablesid = snort_load_sid_mods($a_instance[$instanceid]['rule_sid_off']);

$pconfig = array();
$pconfig['instance'] = $instanceid;
if (is_array($config['installedpackages']['snortglobal']['alertsblocks'])) {
	$pconfig['arefresh'] = $config['installedpackages']['snortglobal']['alertsblocks']['arefresh'];
	$pconfig['alertnumber'] = $config['installedpackages']['snortglobal']['alertsblocks']['alertnumber'];
}

if (empty($pconfig['alertnumber']))
	$pconfig['alertnumber'] = '250';
if (empty($pconfig['arefresh']))
	$pconfig['arefresh'] = 'off';
$anentries = $pconfig['alertnumber'];

# --- AJAX REVERSE DNS RESOLVE Start ---
if (isset($_POST['resolve'])) {
	$ip = strtolower($_POST['resolve']);
	$res = (is_ipaddr($ip) ? gethostbyaddr($ip) : '');
	
	if ($res && $res != $ip)
		$response = array('resolve_ip' => $ip, 'resolve_text' => $res);
	else
		$response = array('resolve_ip' => $ip, 'resolve_text' => gettext("Cannot resolve"));
	
	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}
# --- AJAX REVERSE DNS RESOLVE End ---

if ($_POST['filterlogentries_submit']) {
	// Set flag for filtering alert entries
	$filterlogentries = TRUE;

	// -- IMPORTANT --
	// Note the order of these fields must match the order decoded from the alerts log
	$filterfieldsarray = array();
	$filterfieldsarray[0] = $_POST['filterlogentries_time'] ? $_POST['filterlogentries_time'] : null;
	$filterfieldsarray[1] = $_POST['filterlogentries_gid'] ? $_POST['filterlogentries_gid'] : null;
	$filterfieldsarray[2] = $_POST['filterlogentries_sid'] ? $_POST['filterlogentries_sid'] : null;
	$filterfieldsarray[3] = null;
	$filterfieldsarray[4] = $_POST['filterlogentries_description'] ? $_POST['filterlogentries_description'] : null;
	$filterfieldsarray[5] = $_POST['filterlogentries_protocol'] ? $_POST['filterlogentries_protocol'] : null;
	// Remove any zero-length spaces added to the IP address that could creep in from a copy-paste operation
	$filterfieldsarray[6] = $_POST['filterlogentries_sourceipaddress'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_sourceipaddress']) : null;
	$filterfieldsarray[7] = $_POST['filterlogentries_sourceport'] ? $_POST['filterlogentries_sourceport'] : null;
	// Remove any zero-length spaces added to the IP address that could creep in from a copy-paste operation
	$filterfieldsarray[8] = $_POST['filterlogentries_destinationipaddress'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_destinationipaddress']) : null;
	$filterfieldsarray[9] = $_POST['filterlogentries_destinationport'] ? $_POST['filterlogentries_destinationport'] : null;
	$filterfieldsarray[10] = null;
	$filterfieldsarray[11] = $_POST['filterlogentries_classification'] ? $_POST['filterlogentries_classification'] : null;
	$filterfieldsarray[12] = $_POST['filterlogentries_priority'] ? $_POST['filterlogentries_priority'] : null;
}

if ($_POST['filterlogentries_clear']) {
	$filterlogentries = TRUE;
	$filterfieldsarray = array();
}

if ($_POST['save']) {
	if (!is_array($config['installedpackages']['snortglobal']['alertsblocks']))
		$config['installedpackages']['snortglobal']['alertsblocks'] = array();
	$config['installedpackages']['snortglobal']['alertsblocks']['arefresh'] = $_POST['arefresh'] ? 'on' : 'off';
	$config['installedpackages']['snortglobal']['alertsblocks']['alertnumber'] = $_POST['alertnumber'];

	write_config("Snort pkg: updated ALERTS tab settings.");

	header("Location: /snort/snort_alerts.php?instance={$instanceid}");
	exit;
}

if ($_POST['mode'] == 'todelete') {
	$ip = "";
	if($_POST['ip']) {
		$ip = $_POST['ip'];
		if (is_ipaddr($_POST['ip'])) {
			exec("/sbin/pfctl -t snort2c -T delete {$ip}");
			$savemsg = gettext("Host IP address {$ip} has been removed from the Blocked Hosts Table.");
		}
	}
}

if (($_POST['mode'] == 'addsuppress_srcip' || $_POST['mode'] == 'addsuppress_dstip' || $_POST['mode'] == 'addsuppress') && is_numeric($_POST['sidid']) && is_numeric($_POST['gen_id'])) {
	if ($_POST['mode'] == 'addsuppress_srcip')
		$method = "by_src";
	elseif ($_POST['mode'] == 'addsuppress_dstip')
		$method = "by_dst";
	else
		$method ="all";

	// See which kind of Suppress Entry to create
	switch ($method) {
		case "all":
			if (empty($_POST['descr']))
				$suppress = "suppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}\n";
			else
				$suppress = "#{$_POST['descr']}\nsuppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}\n";
			$success = gettext("An entry for 'suppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}' has been added to the Suppress List.");
			break;
		case "by_src":
		case "by_dst":
			// Check for valid IP addresses, exit if not valid
			if (is_ipaddr($_POST['ip'])) {
				if (empty($_POST['descr']))
					$suppress = "suppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}, track {$method}, ip {$_POST['ip']}\n";
				else  
					$suppress = "#{$_POST['descr']}\nsuppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}, track {$method}, ip {$_POST['ip']}\n";
				$success = gettext("An entry for 'suppress gen_id {$_POST['gen_id']}, sig_id {$_POST['sidid']}, track {$method}, ip {$_POST['ip']}' has been added to the Suppress List.");
			}
			else {
				$input_errors[] = gettext("An invalid IP address was passed as a Suppress List parameter.");
			}
			break;
		default:
			header("Location: /snort/snort_alerts.php?instance={$instanceid}");
			exit;
	}

	if (!$input_errors) {
		/* Add the new entry to the Suppress List and signal Snort to reload config */
		if (snort_add_supplist_entry($suppress)) {
			snort_reload_config($a_instance[$instanceid]);
			$savemsg = $success;
			/* Give Snort a couple seconds to reload the configuration */
			sleep(2);
		}
		else
			$input_errors[] = gettext("Suppress List '{$a_instance[$instanceid]['suppresslistname']}' is defined for this interface, but it could not be found!");
	}
}

if ($_POST['mode'] == 'togglesid' && is_numeric($_POST['sidid']) && is_numeric($_POST['gen_id'])) {
	// Get the GID and SID tags embedded in the clicked rule icon.
	$gid = $_POST['gen_id'];
	$sid= $_POST['sidid'];

	// See if the target SID is in our list of modified SIDs,
	// and toggle it if present.
	if (isset($enablesid[$gid][$sid]))
		unset($enablesid[$gid][$sid]);
	if (isset($disablesid[$gid][$sid]))
		unset($disablesid[$gid][$sid]);
	elseif (!isset($disablesid[$gid][$sid]))
		$disablesid[$gid][$sid] = "disablesid";

	// Write the updated enablesid and disablesid values to the config file.
	$tmp = "";
	foreach (array_keys($enablesid) as $k1) {
		foreach (array_keys($enablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_instance[$instanceid]['rule_sid_on'] = $tmp;
	else				
		unset($a_instance[$instanceid]['rule_sid_on']);

	$tmp = "";
	foreach (array_keys($disablesid) as $k1) {
		foreach (array_keys($disablesid[$k1]) as $k2)
			$tmp .= "{$k1}:{$k2}||";
	}
	$tmp = rtrim($tmp, "||");

	if (!empty($tmp))
		$a_instance[$instanceid]['rule_sid_off'] = $tmp;
	else				
		unset($a_instance[$instanceid]['rule_sid_off']);

	/* Update the config.xml file. */
	write_config("Snort pkg: modified state for rule {$gid}:{$sid}");

	/*************************************************/
	/* Update the snort.conf file and rebuild the    */
	/* rules for this interface.                     */
	/*************************************************/
	$rebuild_rules = true;
	conf_mount_rw();
	snort_generate_conf($a_instance[$instanceid]);
	conf_mount_ro();
	$rebuild_rules = false;

	/* Soft-restart Snort to live-load the new rules */
	snort_reload_config($a_instance[$instanceid]);

	/* Give Snort a couple seconds to reload the configuration */
	sleep(2);

	$savemsg = gettext("The state for rule {$gid}:{$sid} has been modified.  Snort is 'live-reloading' the new rules list.  Please wait at least 15 secs for the process to complete before toggling additional rules.");
}

if ($_POST['clear']) {
	snort_post_delete_logs($snort_uuid);
	file_put_contents("{$snortlogdir}/snort_{$if_real}{$snort_uuid}/alert", "");
	/* XXX: This is needed if snort is run as snort user */
	mwexec("/bin/chmod 660 {$snortlogdir}/*", true);
	if (file_exists("{$g['varrun_path']}/snort_{$if_real}{$snort_uuid}.pid"))
		mwexec("/bin/pkill -HUP -F {$g['varrun_path']}/snort_{$if_real}{$snort_uuid}.pid -a");
	header("Location: /snort/snort_alerts.php?instance={$instanceid}");
	exit;
}

if ($_POST['download']) {
	$save_date = date("Y-m-d-H-i-s");
	$file_name = "snort_logs_{$save_date}_{$if_real}.tar.gz";
	exec("cd {$snortlogdir}/snort_{$if_real}{$snort_uuid} && /usr/bin/tar -czf {$g['tmp_path']}/{$file_name} *");

	if (file_exists("{$g['tmp_path']}/{$file_name}")) {
		ob_start(); //important or other posts will fail
		if (isset($_SERVER['HTTPS'])) {
			header('Pragma: ');
			header('Cache-Control: ');
		} else {
			header("Pragma: private");
			header("Cache-Control: private, must-revalidate");
		}
		header("Content-Type: application/octet-stream");
		header("Content-length: " . filesize("{$g['tmp_path']}/{$file_name}"));
		header("Content-disposition: attachment; filename = {$file_name}");
		ob_end_clean(); //important or other post will fail
		readfile("{$g['tmp_path']}/{$file_name}");

		// Clean up the temp file
		unlink_if_exists("{$g['tmp_path']}/{$file_name}");
	}
	else
		$savemsg = gettext("An error occurred while creating archive");
}

// Load up an array with the current Suppression List GID,SID values
$supplist = snort_load_suppress_sigs($a_instance[$instanceid], true);

// Load up an array with the configured Snort interfaces
$interfaces = array();
foreach ($a_instance as $id => $instance) {
	$interfaces[$id] = convert_friendly_interface_to_friendly_descr($instance['interface']);
}

$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Alerts"));
include("head.inc");

/* refresh every 60 secs */
if ($pconfig['arefresh'] == 'on')
	print '<meta http-equiv="refresh" content="60;url=/snort/snort_alerts.php?instance=' . $instanceid . '" />';

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$form = new Form(false);
$form->setAttribute('name', 'formalert')->setAttribute('id', 'formalert');

$section = new Form_Section('Alert Log View Settings');
$group = new Form_Group('Interface to Inspect');
$group->add(new Form_Select(
	'instance',
	'Instance to Inspect',
	$pconfig['instance'],
	$interfaces
))->setHelp('Choose interface..');
$group->add(new Form_Checkbox(
	'arefresh',
	'Refresh',
	'Auto-refresh view',
	$pconfig['arefresh'] == 'on' ? true:false,
	'on'
));
$group->add(new Form_Input(
	'alertnumber',
	'Lines to Display',
	'text',
	$pconfig['alertnumber']
))->setHelp('Alert lines to display.');
$group->add(new Form_Button(
	'save',
	'Save',
	null,
	'fa-save'
))->addClass('btn-primary btn-sm')->setAttribute('title', gettext('Save auto-refresh and view settings'));
$section->add($group);

$btn_dnload = new Form_Button(
	'download',
	'Download',
	null,
	'fa-download'
);
$btn_dnload->removeClass('btn-primary')->addClass('btn-success')->addClass('btn-sm')->setAttribute('title', gettext('Download interface log files as a gzip archive'));
$btn_clear = new Form_Button(
	'clear',
	'Clear',
	null,
	'fa-trash'
);
$btn_clear->removeClass('btn-primary')->addClass('btn-danger')->addClass('btn-sm')->setAttribute('title', gettext('Clear all interface log files')); 

$group = new Form_Group('Alert Log Actions');
$group->add(new Form_StaticText(
	null,
	$btn_dnload . $btn_clear
));
$section->add($group);

$form->add($section);

// ========== BEGIN Log filter Panel =============================================================
if ($filterlogentries) {
	$section = new Form_Section('Alert Log View Filter', 'alertlogfilter', COLLAPSIBLE|SEC_OPEN);
}
else {
	$section = new Form_Section('Alert Log View Filter', 'alertlogfilter', COLLAPSIBLE|SEC_CLOSED);
}

$group = new Form_Group('');
$group->add(new Form_Input(
	'filterlogentries_sourceipaddress',
	null,
	'text',
	$filterfieldsarray[6]
))->setHelp('Source IP Address');
$group->add(new Form_Input(
	'filterlogentries_sourceport',
	null,
	'text',
	$filterfieldsarray[7]
))->setHelp('Source Port');
$group->add(new Form_Input(
	'filterlogentries_destinationipaddress',
	null,
	'text',
	$filterfieldsarray[8]
))->setHelp('Destination IP Address');
$group->add(new Form_Input(
	'filterlogentries_destinationport',
	null,
	'text',
	$filterfieldsarray[9]
))->setHelp('Destination Port');
$group->add(new Form_Input(
	'filterlogentries_protocol',
	null,
	'text',
	$filterfieldsarray[5]
))->setHelp('Protocol');
$section->add($group);

$group = new Form_Group('');
$group->add(new Form_Input(
	'filterlogentries_time',
	null,
	'text',
	$filterfieldsarray[0]
))->setHelp('Date');
$group->add(new Form_Input(
	'filterlogentries_priority',
	null,
	'text',
	$filterfieldsarray[12]
))->setHelp('Priority');
$group->add(new Form_Input(
	'filterlogentries_gid',
	null,
	'text',
	$filterfieldsarray[1]
))->setHelp('GID');
$group->add(new Form_Input(
	'filterlogentries_sid',
	null,
	'text',
	$filterfieldsarray[2]
))->setHelp('SID');
$section->add($group);

$group = new Form_Group('');
$group->add(new Form_Input(
	'filterlogentries_description',
	null,
	'text',
	$filterfieldsarray[4]
))->setHelp('Description');
$group->add(new Form_Input(
	'filterlogentries_classification',
	null,
	'text',
	$filterfieldsarray[11]
))->setHelp('Classification');
$group->add(new Form_Button(
	'filterlogentries_submit',
	' ' . 'Filter',
	null,
	'fa-filter'
))->removeClass('btn-primary')->addClass('btn-success')->addClass('btn-sm');
$group->add(new Form_Button(
	'filterlogentries_clear',
	' ' . 'Clear',
	null,
	'fa-trash-o'
))->removeClass('btn-primary')->addClass('btn-warning')->addClass('btn-sm');

$section->add($group);

$form->add($section);
// ========== END Log filter Panel =============================================================

if (isset($instanceid)) {
	$form->addGlobal(new Form_Input(
		'id',
		'id',
		'hidden',
		$instanceid
	));
}
$form->addGlobal(new Form_Input(
	'mode',
	'mode',
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'sidid',
	'sidid',
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'gen_id',
	'gen_id',
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'ip',
	'ip',
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'descr',
	'descr',
	'hidden',
	''
));

$tab_array = array();
	$tab_array[0] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
	$tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
	$tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
	$tab_array[3] = array(gettext("Alerts"), true, "/snort/snort_alerts.php?instance={$instanceid}");
	$tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[5] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
	$tab_array[6] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
	$tab_array[7] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
	$tab_array[8] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[9] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
	$tab_array[10] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);

print ($form);

?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?php
				if (!$filterfieldsarray)
					printf(gettext("Last %s Alert Log Entries"), $pconfig['alertnumber']);
				else
					print($anentries. ' ' . gettext('Matched Log Entries') . ' ');
			?>
		</h2>
	</div>
	<div class="panel-body">
	   <div class="table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
			   <tr class="sortableHeaderRowIdentifier text-nowrap">
				<th data-sortable-type="date"><?=gettext("Date  "); ?></th>
				<th data-sortable-type="numeric"><?=gettext("Pri   "); ?></th>
				<th><?=gettext("Proto "); ?></th>
				<th><?=gettext("Class "); ?></th>
				<th><?=gettext("Source IP"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("SPort "); ?></th>
				<th><?=gettext("Destination IP"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("DPort "); ?></th>
				<th data-sortable-type="numeric"><?=gettext("SID   "); ?></th>
				<th data-sortable-type="alpha"><?=gettext("Description"); ?></th>
			   </tr>
			</thead>
		<tbody>
	<?php

/* make sure alert file exists */
if (file_exists("{$snortlogdir}/snort_{$if_real}{$snort_uuid}/alert")) {
	exec("tail -{$anentries} -r {$snortlogdir}/snort_{$if_real}{$snort_uuid}/alert > {$g['tmp_path']}/alert_{$snort_uuid}");
	if (file_exists("{$g['tmp_path']}/alert_{$snort_uuid}")) {
		$tmpblocked = array_flip(snort_get_blocked_ips());
		$counter = 0;
		/*                 0         1           2      3      4    5    6    7      8     9    10    11             12    */
		/* File format timestamp,sig_generator,sig_id,sig_rev,msg,proto,src,srcport,dst,dstport,id,classification,priority */
		$fd = fopen("{$g['tmp_path']}/alert_{$snort_uuid}", "r");
		while (($fields = fgetcsv($fd, 1000, ',', '"')) !== FALSE) {
			if(count($fields) < 13)
				continue;

			if ($filterlogentries && !snort_match_filter_field($fields, $filterfieldsarray)) {
				continue;
			}

			/* Time */
			$alert_time = substr($fields[0], strpos($fields[0], '-')+1, -8);
			/* Date */
			$alert_date = substr($fields[0], 0, strpos($fields[0], '-'));
			/* Description */
			$alert_descr = $fields[4];
			$alert_descr_url = urlencode($fields[4]);
			/* Priority */
			$alert_priority = $fields[12];
			/* Protocol */
			$alert_proto = $fields[5];
			/* IP SRC */
			$alert_ip_src = $fields[6];

			/* Add Reverse DNS lookup icons */
			$alert_ip_src .= '<br/>';
			$alert_ip_src .= '<i class="fa fa-search icon-pointer" onclick="javascript:resolve_with_ajax(\'' . $fields[6] . '\');" title="' . gettext("Click to resolve") . '" alt="Reverse Resolve with DNS"></i>';

			/* Add icons for auto-adding to Suppress List if appropriate */
			if (!snort_is_alert_globally_suppressed($supplist, $fields[1], $fields[2]) && 
			    !isset($supplist[$fields[1]][$fields[2]]['by_src'][$fields[6]])) {

				$alert_ip_src .= "&nbsp;&nbsp;<i class=\"fa fa-plus-square-o icon-pointer\" title=\"" . gettext('Add this alert to the Suppress List and track by_src IP') . '"';
				$alert_ip_src .= " onClick=\"encRuleSig('{$fields[1]}','{$fields[2]}','{$fields[6]}','{$alert_descr}');$('#mode').val('addsuppress_srcip');$('#formalert').submit();\"></i>";
			}
			elseif (isset($supplist[$fields[1]][$fields[2]]['by_src'][$fields[6]])) {
				$alert_ip_src .= '&nbsp;&nbsp;<i class="fa fa-info-circle"';
				$alert_ip_src .= ' title="' . gettext("This alert track by_src IP is already in the Suppress List") . '"></i>';	
			}
			/* Add icon for auto-removing from Blocked Table if required */
			if (isset($tmpblocked[$fields[6]])) {
				$alert_ip_src .= "&nbsp;&nbsp;<i class=\"fa fa-times icon-pointer text-danger\" onClick=\"$('#ip').val('{$fields[6]}');$('#mode').val('todelete');$('#formalert').submit();\"";
				$alert_ip_src .= ' title="' . gettext("Remove host from Blocked Table") . '"></i>';
			}
			/* IP SRC Port */
			$alert_src_p = $fields[7];

			/* IP Destination */
			$alert_ip_dst = $fields[8];

			/* Add Reverse DNS lookup icons */
			$alert_ip_dst .= "<br/>";
			$alert_ip_dst .= '<i class="fa fa-search icon-pointer" onclick="javascript:resolve_with_ajax(\'' . $fields[8] . '\');" title="' . gettext("Click to resolve") . '" alt="Reverse Resolve with DNS"></i>';

			/* Add icons for auto-adding to Suppress List if appropriate */
			if (!snort_is_alert_globally_suppressed($supplist, $fields[1], $fields[2]) && 
			    !isset($supplist[$fields[1]][$fields[2]]['by_dst'][$fields[8]])) {
				$alert_ip_dst .= "&nbsp;&nbsp;<i class=\"fa fa-plus-square-o icon-pointer\" onClick=\"encRuleSig('{$fields[1]}','{$fields[2]}','{$fields[8]}','{$alert_descr}');$('#mode').val('addsuppress_dstip');$('#formalert').submit();\"";
				$alert_ip_dst .= ' title="' . gettext("Add this alert to the Suppress List and track by_dst IP") . '"></i>';	
			}
			elseif (isset($supplist[$fields[1]][$fields[2]]['by_dst'][$fields[8]])) {
				$alert_ip_dst .= '&nbsp;&nbsp;<i class="fa fa-info-circle"';
				$alert_ip_dst .= ' title="' . gettext("This alert track by_dst IP is already in the Suppress List") . '"></i>';	
			}
			/* Add icon for auto-removing from Blocked Table if required */
			if (isset($tmpblocked[$fields[8]])) {
				$alert_ip_dst .= "&nbsp;&nbsp;<i name=\"todelete[]\" class=\"fa fa-times icon-pointer text-danger\" onClick=\"$('#ip').val('{$fields[8]}');$('#mode').val('todelete');$('#formalert').submit();\" ";
				$alert_ip_dst .= ' title="' . gettext("Remove host from Blocked Table") . '"></i>';
			}

			/* IP DST Port */
			$alert_dst_p = $fields[9];

			/* SID */
			$alert_sid_str = "{$fields[1]}:{$fields[2]}";
			if (!snort_is_alert_globally_suppressed($supplist, $fields[1], $fields[2])) {
				$sidsupplink = "<i class=\"fa fa-plus-square-o icon-pointer\" onClick=\"encRuleSig('{$fields[1]}','{$fields[2]}','','{$alert_descr}');$('#mode').val('addsuppress');$('#formalert').submit();\"";
				$sidsupplink .= ' title="' . gettext("Add this alert to the Suppress List") . '"></i>';	
			}
			else {
				$sidsupplink = '<i class="fa fa-info-circle"';
				$sidsupplink .= ' title="' . gettext("This alert is already in the Suppress List") . '"></i>';	
			}
			/* Add icon for toggling rule state */
			if (isset($disablesid[$fields[1]][$fields[2]])) {
				$sid_dsbl_link = "<i class=\"fa fa-times-circle icon-pointer text-warning\" onClick=\"encRuleSig('{$fields[1]}','{$fields[2]}','','');$('#mode').val('togglesid');$('#formalert').submit();\"";
				$sid_dsbl_link .= ' title="' . gettext("Rule is forced to a disabled state. Click to remove the force-disable action from this rule.") . '"></i>';
			}
			else {
				$sid_dsbl_link = "<i class=\"fa fa-times icon-pointer text-danger\" onClick=\"encRuleSig('{$fields[1]}','{$fields[2]}','','');$('#mode').val('togglesid');$('#formalert').submit();\"";
				$sid_dsbl_link .= ' title="' . gettext("Force-disable this rule and remove it from current rules set.") . '"></i>';
			}

			/* DESCRIPTION */
			$alert_class = $fields[11];

			/* Write out a table row */
			echo "<tr class=\"text-nowrap\">
				<td>{$alert_date}<br/>{$alert_time}</td>
				<td>{$alert_priority}</td>
				<td style=\"word-wrap:break-word; white-space:normal\">{$alert_proto}</td>
				<td style=\"word-wrap:break-word; white-space:normal\">{$alert_class}</td>
				<td style=\"word-wrap:break-word; white-space:normal\">{$alert_ip_src}</td>
				<td>{$alert_src_p}</td>
				<td style=\"word-wrap:break-word; white-space:normal\">{$alert_ip_dst}</td>
				<td>{$alert_dst_p}</td>
				<td>{$alert_sid_str}<br/>{$sidsupplink}&nbsp;&nbsp;{$sid_dsbl_link}</td>
				<td style=\"word-wrap:break-word; white-space:normal\">{$alert_descr}</td>
				</tr>\n";
			$counter++;
		}
		fclose($fd);
		unlink_if_exists("{$g['tmp_path']}/alert_{$snort_uuid}");
	}
}
?>
		</tbody>
	</table>
</div>
</div>
</div>

<script type="text/javascript">
//<![CDATA[

	//-- This function stuffs the passed GID, SID and other values into
	//-- hidden Form Fields for postback.
	function encRuleSig(rulegid,rulesid,srcip,ruledescr) {
		if (typeof srcipip === 'undefined')
			var srcipip = '';
		if (typeof ruledescr === 'undefined')
			var ruledescr = '';
		$('#sidid').val(rulesid);
		$('#gen_id').val(rulegid);
		$('#ip').val(srcip);
		$('#descr').val(ruledescr);
	}

	//-- The following AJAX code was borrowed from the diag_logs_filter.php --
	//-- file in pfSense.  See copyright info at top of this page.          --
	function resolve_with_ajax(ip_to_resolve) {
		var url = "/snort/snort_alerts.php";

	jQuery.ajax(
		url,
		{
			method: 'post',
			dataType: 'json',
			data: {
				resolve: ip_to_resolve,
				},
			complete: resolve_ip_callback
		});

	}

	function resolve_ip_callback(transport) {
		var response = jQuery.parseJSON(transport.responseText);
		var msg = 'IP address "' + response.resolve_ip + '" resolves to\n';
		alert(msg + 'host "' + htmlspecialchars(response.resolve_text) + '"');
	}

	// From http://stackoverflow.com/questions/5499078/fastest-method-to-escape-html-tags-as-html-entities
	function htmlspecialchars(str) {
		return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
	}

events.push(function() {

	//-- Click handlers ------------------------------------------------------
	$('#instance').on('change', function() {
		$('#formalert').submit();
	});

});
//]]>
</script>

<?php include("foot.inc"); ?>

