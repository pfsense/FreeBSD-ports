<?php
/*
* suricata_alerts.php
*
*  Copyright (c)  2004-2016  Electric Sheep Fencing, LLC. All rights reserved.
*
*  Redistribution and use in source and binary forms, with or without modification,
*  are permitted provided that the following conditions are met:
*
*  1. Redistributions of source code must retain the above copyright notice,
*      this list of conditions and the following disclaimer.
*
*  2. Redistributions in binary form must reproduce the above copyright
*      notice, this list of conditions and the following disclaimer in
*      the documentation and/or other materials provided with the
*      distribution.
*
*  3. All advertising materials mentioning features or use of this software
*      must display the following acknowledgment:
*      "This product includes software developed by the pfSense Project
*       for use in the pfSense software distribution. (http://www.pfsense.org/).
*
*  4. The names "pfSense" and "pfSense Project" must not be used to
*       endorse or promote products derived from this software without
*       prior written permission. For written permission, please contact
*       coreteam@pfsense.org.
*
*  5. Products derived from this software may not be called "pfSense"
*      nor may "pfSense" appear in their names without prior written
*      permission of the Electric Sheep Fencing, LLC.
*
*  6. Redistributions of any form whatsoever must retain the following
*      acknowledgment:
*
*  "This product includes software developed by the pfSense Project
*  for use in the pfSense software distribution (http://www.pfsense.org/).
*
*  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
*  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
*  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
*  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
*  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
*  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
*  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
*  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
*  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
*  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
*  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
*  OF THE POSSIBILITY OF SUCH DAMAGE.
*
*
* Portions of this code are based on original work done for the Snort package for pfSense by the following contributors:
*
* Copyright (C) 2003-2004 Manuel Kasper
* Copyright (C) 2005 Bill Marquette
* Copyright (C) 2006 Scott Ullrich (copyright assigned to ESF)
* Copyright (C) 2009 Robert Zelaya Sr. Developer
* Copyright (C) 2012 Ermal Luci  (copyright assigned to ESF)
* Copyright (C) 2016 Bill Meeks
*
*/

require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g, $config;
$supplist = array();
$suri_pf_table = SURICATA_PF_TABLE;
$filterlogentries = FALSE;

function suricata_is_alert_globally_suppressed($list, $gid, $sid) {

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

function suricata_add_supplist_entry($suppress) {

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

	if (!is_array($config['installedpackages']['suricata']['suppress'])) {
		$config['installedpackages']['suricata']['suppress'] = array();
	}

	if (!is_array($config['installedpackages']['suricata']['suppress']['item'])) {
		$config['installedpackages']['suricata']['suppress']['item'] = array();
	}

	$a_suppress = &$config['installedpackages']['suricata']['suppress']['item'];

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
	} else {
		/* If we get here, a Suppress List is defined for the interface so see if we can find it */
		foreach ($a_suppress as $a_id => $alist) {
			if ($alist['name'] == $a_instance[$instanceid]['suppresslistname']) {
				$found_list = true;
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

	/* If we created a new list or updated an existing one, save the change */
	/* and return true; otherwise return false.                             */
	if ($found_list) {
		write_config();
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();
		return true;
	}
	else
		return false;
}

function suricata_escape_filter_regex($filtertext) {
	/* If the caller (user) has not already put a backslash before a slash, to escape it in the regex, */
	/* then this will do it. Take out any "\/" already there, then turn all ordinary "/" into "\/".  */
	return str_replace('/', '\/', str_replace('\/', '/', $filtertext));
}

function suricata_match_filter_field($flent, $fields) {
	foreach ($fields as $key => $field) {
		if ($field == null)
			continue;
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

if (!is_array($config['installedpackages']['suricata']['rule']))
	$config['installedpackages']['suricata']['rule'] = array();
$a_instance = &$config['installedpackages']['suricata']['rule'];
$suricata_uuid = $a_instance[$instanceid]['uuid'];
$if_real = get_real_interface($a_instance[$instanceid]['interface']);
$suricatalogdir = SURICATALOGDIR;

// Load up the arrays of force-enabled and force-disabled SIDs
$enablesid = suricata_load_sid_mods($a_instance[$instanceid]['rule_sid_on']);
$disablesid = suricata_load_sid_mods($a_instance[$instanceid]['rule_sid_off']);

$pconfig = array();
if (is_array($config['installedpackages']['suricata']['alertsblocks'])) {
	$pconfig['arefresh'] = $config['installedpackages']['suricata']['alertsblocks']['arefresh'];
	$pconfig['alertnumber'] = $config['installedpackages']['suricata']['alertsblocks']['alertnumber'];
}

if (empty($pconfig['alertnumber']))
	$pconfig['alertnumber'] = 250;
if (empty($pconfig['arefresh']))
	$pconfig['arefresh'] = 'on';
$anentries = $pconfig['alertnumber'];
if (!is_numeric($anentries)) {
	$anentries = 250;
}	

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
	$filterfieldsarray['time'] = $_POST['filterlogentries_time'] ? $_POST['filterlogentries_time'] : null;
	if ($a_instance[$instanceid]['ips_mode'] == 'ips_mode_inline') {
		$filterfieldsarray['action'] = $_POST['filterlogentries_action'] ? $_POST['filterlogentries_action'] : null;
	}
	else {
		$filterfieldsarray['action'] = null;
	}
	$filterfieldsarray['gid'] = $_POST['filterlogentries_gid'] ? $_POST['filterlogentries_gid'] : null;
	$filterfieldsarray['sid'] = $_POST['filterlogentries_sid'] ? $_POST['filterlogentries_sid'] : null;
	$filterfieldsarray['rev'] = null;
	$filterfieldsarray['msg'] = $_POST['filterlogentries_description'] ? $_POST['filterlogentries_description'] : null;
	$filterfieldsarray['class'] = $_POST['filterlogentries_classification'] ? $_POST['filterlogentries_classification'] : null;
	$filterfieldsarray['priority'] = $_POST['filterlogentries_priority'] ? $_POST['filterlogentries_priority'] : null;
	$filterfieldsarray['proto'] = $_POST['filterlogentries_protocol'] ? $_POST['filterlogentries_protocol'] : null;
	// Remove any zero-length spaces added to the IP address that could creep in from a copy-paste operation
	$filterfieldsarray['src'] = $_POST['filterlogentries_sourceipaddress'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_sourceipaddress']) : null;
	$filterfieldsarray['sport'] = $_POST['filterlogentries_sourceport'] ? $_POST['filterlogentries_sourceport'] : null;
	// Remove any zero-length spaces added to the IP address that could creep in from a copy-paste operation
	$filterfieldsarray['dst'] = $_POST['filterlogentries_destinationipaddress'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_destinationipaddress']) : null;
	$filterfieldsarray['dport'] = $_POST['filterlogentries_destinationport'] ? $_POST['filterlogentries_destinationport'] : null;
}

if ($_POST['filterlogentries_clear']) {
	$filterfieldsarray = array();
	$filterlogentries = TRUE;
}

if ($_POST['save']) {
	if (!is_array($config['installedpackages']['suricata']['alertsblocks']))
		$config['installedpackages']['suricata']['alertsblocks'] = array();
	$config['installedpackages']['suricata']['alertsblocks']['arefresh'] = $_POST['arefresh'] ? 'on' : 'off';
	$config['installedpackages']['suricata']['alertsblocks']['alertnumber'] = $_POST['alertnumber'];

	write_config();

	header("Location: /suricata/suricata_alerts.php?instance={$instanceid}");
	exit;
}

if ($_POST['unblock'] && $_POST['ip']) {
	if (is_ipaddr($_POST['ip'])) {
		exec("/sbin/pfctl -t {$suri_pf_table} -T delete {$_POST['ip']}");
		$savemsg = gettext("Host IP address {$_POST['ip']} has been removed from the Blocked Table.");
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
				header("Location: /suricata/suricata_alerts.php");
				exit;
			}
			break;
		default:
			header("Location: /suricata/suricata_alerts.php");
			exit;
	}

	/* Add the new entry to the Suppress List and signal Suricata to reload config */
	if (suricata_add_supplist_entry($suppress)) {
		suricata_reload_config($a_instance[$instanceid]);
		$savemsg = $success;

		// Sync to configured CARP slaves if any are enabled
		suricata_sync_on_changes();
		sleep(2);
	}
	else
		$input_errors[] = gettext("Suppress List '{$a_instance[$instanceid]['suppresslistname']}' is defined for this interface, but it could not be found!");
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
	write_config();

	/*************************************************/
	/* Update the suricata.yaml file and rebuild the */
	/* rules for this interface.                     */
	/*************************************************/
	$rebuild_rules = true;
	conf_mount_rw();
	suricata_generate_yaml($a_instance[$instanceid]);
	conf_mount_ro();
	$rebuild_rules = false;

	/* Signal Suricata to live-load the new rules */
	suricata_reload_config($a_instance[$instanceid]);

	// Sync to configured CARP slaves if any are enabled
	suricata_sync_on_changes();
	sleep(2);

	$savemsg = gettext("The state for rule {$gid}:{$sid} has been modified.  Suricata is 'live-reloading' the new rules list.  Please wait at least 15 secs for the process to complete before toggling additional rules.");
}

if ($_POST['clear']) {
	suricata_post_delete_logs($suricata_uuid);
	$fd = @fopen("{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}/alerts.log", "w+");
	if ($fd)
		fclose($fd);
	/* XXX: This is needed if suricata is run as suricata user */
	mwexec('/bin/chmod 660 {$suricatalogdir}*', true);
	header("Location: /suricata/suricata_alerts.php?instance={$instanceid}");
	exit;
}

if ($_POST['download']) {
	$save_date = date("Y-m-d-H-i-s");
	$file_name = "suricata_logs_{$save_date}_{$if_real}.tar.gz";
	exec("cd {$suricatalogdir}suricata_{$if_real}{$suricata_uuid} && /usr/bin/tar -czf {$g['tmp_path']}/{$file_name} *");

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

/* Load up an array with the current Suppression List GID,SID values */
$supplist = suricata_load_suppress_sigs($a_instance[$instanceid], true);

$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Alerts"));

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

include_once("head.inc");


/* refresh every 60 secs */
if ($pconfig['arefresh'] == 'on')
	print '<meta http-equiv="refresh" content="60;url=/suricata/suricata_alerts.php?instance=' . $instanceid . '" />';

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), true, "/suricata/suricata_alerts.php");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$instanceid}");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

$form = new Form(false);
$form->setAttribute('name', 'formalert')->setAttribute('id', 'formalert');

$section = new Form_Section('Alert Log View Settings');

$section->addInput(new Form_Select(
	'instance',
	'Instance to View',
	$instanceid,
	build_instance_list()
))->setHelp('Choose which instance alerts you want to inspect.');

$group = new Form_Group('Save or Remove Hosts');

$group->add(new Form_Button(
	'download',
	'Download',
	null,
	'fa-download'
))->removeClass('btn-default')->addClass('btn-info btn-sm')
  ->setHelp('All log files will be saved');

$group->add(new Form_Button(
	'clear',
	'Clear',
	null,
	'fa-trash'
))->removeClass('btn-default')->addClass('btn-danger btn-sm')
  ->setHelp('All log files will be cleared');

$section->add($group);

$group = new Form_Group('Save or Remove Hosts');

$group->add(new Form_Button(
	'save',
	'Save',
	null,
	'fa-save'
))->removeClass('btn-default')->addClass('btn-success btn-sm')
  ->setHelp('Save auto-refresh and view settings');

$group->add(new Form_Checkbox(
	'arefresh',
	null,
	'Refresh',
	($config['installedpackages']['suricata']['alertsblocks']['arefresh'] == "on"),
	'on'
))->setHelp('Default is ON');

$group->add(new Form_Input(
	'alertnumber',
	'Alert Entries',
	'number',
	$anentries
	))->setHelp('Number of alerts to display. Default is 250');

$section->add($group);

$form->add($section);

// ========== Log filter Panel =============================================================
if ($filterlogentries && count($filterfieldsarray)) {
	$section = new Form_Section("Alert Log View Filter", "alertfilter", COLLAPSIBLE|SEC_OPEN);
}
else {
	$section = new Form_Section("Alert Log View Filter", "alertfilter", COLLAPSIBLE|SEC_CLOSED);
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
	$filterfieldsarray['src']
))->setHelp("Source IP Address");

$group->add(new Form_Input(
	'filterlogentries_sourceport',
	'Source Port',
	'text',
	$filterfieldsarray['sport']
))->setHelp("Source Port");

$group->add(new Form_Input(
	'filterlogentries_description',
	'Description',
	'text',
	$filterfieldsarray['msg']
))->setHelp("Description");

$group->add(new Form_Input(
	'filterlogentries_gid',
	'GID',
	'text',
	$filterfieldsarray['gid']
))->setHelp("GID");

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Input(
	'filterlogentries_priority',
	'Priority',
	'text',
	$filterfieldsarray['priority']
))->setHelp("Priority");

$group->add(new Form_Input(
	'filterlogentries_destinationipaddress',
	'Destination IP Address',
	'text',
	$filterfieldsarray['dst']
))->setHelp("Destination IP Address");

$group->add(new Form_Input(
	'filterlogentries_destinationport',
	'Port',
	'text',
	$filterfieldsarray['dport']
))->setHelp("Destination Port");

$group->add(new Form_Input(
	'filterlogentries_classification',
	'Classification',
	'text',
	$filterfieldsarray['class']
))->setHelp("Classification");

$group->add(new Form_Input(
	'filterlogentries_sid',
	'SID',
	'text',
	$filterfieldsarray['sid']
))->setHelp("SID");

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Input(
	'filterlogentries_protocol',
	'Protocol',
	'text',
	$filterfieldsarray['proto']
))->setHelp("Protocol");

if ($a_instance[$instanceid]['ips_mode'] == 'ips_mode_inline') {
	$group->add(new Form_Checkbox(
		'filterlogentries_action',
		'Dropped',
		null,
		$filterfieldsarray['action'] == "Drop" ? true:false,
		'Drop'
	))->setHelp('Dropped');
}

$group->add(new Form_Button(
	'filterlogentries_submit',
	'Filter',
	null,
	'fa-filter'
))->setHelp("Apply filter")
  ->removeClass("btn-primary")
  ->addClass("btn-success");

$group->add(new Form_Button(
	'filterlogentries_clear',
	'Clear',
	null,
	'fa-trash'
))->setHelp("Remove all filters")
  ->removeclass("btn-primary")
  ->addClass("btn-danger");

$section->add($group);

$form->add($section);

// ========== Hidden controls ==============
$form->addGlobal(new Form_Input(
	'sidid',
	null,
	'hidden',
	''
));

$form->addGlobal(new Form_Input(
	'ip',
	null,
	'hidden',
	''
));

$form->addGlobal(new Form_Input(
	'gen_id',
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

$form->addGlobal(new Form_Input(
	'descr',
	null,
	'hidden',
	''
));

print($form);

if ($filterlogentries && count($filterfieldsarray)) {
	$sectitle = "Last %s Alert Entries. (Most recent entries are listed first)  ** FILTERED VIEW **  clear filter to see all entries";
} else {
	$sectitle = "Last %s Alert Entries. (Most recent entries are listed first)";
}

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=sprintf($sectitle, $anentries)?></h2></div>
	<div class="panel-body table-responsive">
	<?php if ($a_instance[$instanceid]['ips_mode'] == 'ips_mode_inline') : ?>
		<div class="content table-responsive">
			<span class="text-info"><b><?=gettext('Note: ');?></b><?=gettext('Alerts triggered by DROP rules that resulted in dropped (blocked) packets are shown with ');?>
			<span class="text-danger"><?=gettext('highlighted ');?></span><?=gettext('rows below.');?><span>
		</div>
	<?php endif; ?>
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
			   <tr class="sortableHeaderRowIdentifier text-nowrap">
				<th data-sortable-type="date"><?=gettext("Date"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("Pri"); ?></th>
				<th><?=gettext("Proto"); ?></th>
				<th><?=gettext("Class"); ?></th>
				<th><?=gettext("Src"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("SPort"); ?></th>
				<th><?=gettext("Dst"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("DPort"); ?></th>
				<th data-sortable-type="numeric"><?=gettext("GID:SID"); ?></th>
				<th data-sortable-type="alpha"><?=gettext("Description"); ?></th>
			   </tr>
			</thead>
			<tbody>
	<?php

/* make sure alert file exists */
if (file_exists("{$g['varlog_path']}/suricata/suricata_{$if_real}{$suricata_uuid}/alerts.log")) {
	exec("tail -{$anentries} -r {$g['varlog_path']}/suricata/suricata_{$if_real}{$suricata_uuid}/alerts.log > {$g['tmp_path']}/alerts_suricata{$suricata_uuid}");
	if (file_exists("{$g['tmp_path']}/alerts_suricata{$suricata_uuid}")) {
		$tmpblocked = array_flip(suricata_get_blocked_ips());
		$counter = 0;

		/*************** FORMAT without CSV patch -- ALERT -- ***********************************************************************************/
		/* Line format: timestamp  action[**] [gid:sid:rev] msg [**] [Classification: class] [Priority: pri] {proto} src:srcport -> dst:dstport */
		/*             0          1           2   3   4    5                         6                 7     8      9   10         11  12       */
		/****************************************************************************************************************************************/

		/**************** FORMAT without CSV patch -- DECODER EVENT -- **************************************************************************/
		/* Line format: timestamp  action[**] [gid:sid:rev] msg [**] [Classification: class] [Priority: pri] [**] [Raw pkt: ...]                */
		/*              0          1           2   3   4    5                         6                 7                                       */
		/************** *************************************************************************************************************************/

		$fd = fopen("{$g['tmp_path']}/alerts_suricata{$suricata_uuid}", "r");
		$buf = "";
		while (($buf = fgets($fd)) !== FALSE) {
			$fields = array();
			$tmp = array();
			$decoder_event = FALSE;

			/**************************************************************/
			/* Parse alert log entry to find the parts we want to display */
			/**************************************************************/

			// Field 0 is the event timestamp
			$fields['time'] = substr($buf, 0, strpos($buf, '  '));

			// Field 1 is the rule action (value is '**' when mode is not inline IPS)
			if ($a_instance[$instanceid]['ips_mode'] == 'ips_mode_inline' && preg_match('/\[([A-Z]+)\]\s/i', $buf, $tmp)) {
				$fields['action'] = trim($tmp[1]);
			}
			else {
				$fields['action'] = null;
			}

			// The regular expression match below returns an array as follows:
			// [2] => GID, [3] => SID, [4] => REV, [5] => MSG, [6] => CLASSIFICATION, [7] = PRIORITY
			preg_match('/\[\*{2}\]\s\[((\d+):(\d+):(\d+))\]\s(.*)\[\*{2}\]\s\[Classification:\s(.*)\]\s\[Priority:\s(\d+)\]\s/', $buf, $tmp);
			$fields['gid'] = trim($tmp[2]);
			$fields['sid'] = trim($tmp[3]);
			$fields['rev'] = trim($tmp[4]);
			$fields['msg'] = trim($tmp[5]);
			$fields['class'] = trim($tmp[6]);
			$fields['priority'] = trim($tmp[7]);

			// The regular expression match below looks for the PROTO, SRC and DST fields
			// and returns an array as follows:
			// [1] = PROTO, [2] => SRC:SPORT [3] => DST:DPORT
			if (preg_match('/\{(.*)\}\s(.*)\s->\s(.*)/', $buf, $tmp)) {
				// Get PROTO
				$fields['proto'] = trim($tmp[1]);

				// Get SRC
				$fields['src'] = trim(substr($tmp[2], 0, strrpos($tmp[2], ':')));
				if (is_ipaddrv6($fields['src']))
					$fields['src'] = inet_ntop(inet_pton($fields['src']));

				// Get SPORT
				$fields['sport'] = trim(substr($tmp[2], strrpos($tmp[2], ':') + 1));

				// Get DST
				$fields['dst'] = trim(substr($tmp[3], 0, strrpos($tmp[3], ':')));
				if (is_ipaddrv6($fields['dst']))
					$fields['dst'] = inet_ntop(inet_pton($fields['dst']));

				// Get DPORT
				$fields['dport'] = trim(substr($tmp[3], strrpos($tmp[3], ':') + 1));
			}
			else {
				// If no PROTO nor IP ADDR, then this is a DECODER EVENT
				$decoder_event = TRUE;
				$fields['proto'] = gettext("n/a");
				$fields['sport'] = gettext("n/a");
				$fields['dport'] = gettext("n/a");
			}

			// Create a DateTime object from the event timestamp that
			// we can use to easily manipulate output formats.
			$event_tm = date_create_from_format("m/d/Y-H:i:s.u", $fields['time']);

			// Check the 'CATEGORY' field for the text "(null)" and
			// substitute "Not Assigned".
			if ($fields['class'] == "(null)")
				$fields['class'] = gettext("Not Assigned");

			// PHP date_format issues a bogus warning even though $event_tm really is an object
			// Suppress it with @
			@$fields['time'] = date_format($event_tm, "m/d/Y") . " " . date_format($event_tm, "H:i:s");

			if ($filterlogentries && !suricata_match_filter_field($fields, $filterfieldsarray)) {
				continue;
			}

			/* Time */
			@$alert_time = date_format($event_tm, "H:i:s");
			/* Date */
			@$alert_date = date_format($event_tm, "m/d/Y");
			/* Description */
			$alert_descr = $fields['msg'];
			$alert_descr_url = urlencode($fields['msg']);
			/* Priority */
			$alert_priority = $fields['priority'];
			/* Protocol */
			$alert_proto = $fields['proto'];

			/* IP SRC */
			if ($decoder_event == FALSE) {
				$alert_ip_src = $fields['src'];
				/* Add zero-width space as soft-break opportunity after each colon if we have an IPv6 address */
				$alert_ip_src = str_replace(":", ":&#8203;", $alert_ip_src);
				/* Add Reverse DNS lookup icon */
				$alert_ip_src .= '<br /><i class="fa fa-search" onclick="javascript:resolve_with_ajax(\'' . $fields['src'] . '\');" title="';
				$alert_ip_src .= gettext("Resolve host via reverse DNS lookup") . "\"  alt=\"Icon Reverse Resolve with DNS\" ";
				$alert_ip_src .= " style=\"cursor: pointer;\"></i>";
				/* Add icons for auto-adding to Suppress List if appropriate */
				if (!suricata_is_alert_globally_suppressed($supplist, $fields['gid'], $fields['sid']) &&
				    !isset($supplist[$fields['gid']][$fields['sid']]['by_src'][$fields['src']])) {
					$alert_ip_src .= "&nbsp;&nbsp;<i class=\"fa fa-plus-square-o icon-pointer\" title=\"" . gettext('Add this alert to the Suppress List and track by_src IP') . '"';
					$alert_ip_src .= " onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','{$fields['src']}','{$alert_descr}');$('#mode').val('addsuppress_srcip');$('#formalert').submit();\"></i>";
				}
				elseif (isset($supplist[$fields['gid']][$fields['sid']]['by_src'][$fields['src']])) {
					$alert_ip_src .= '&nbsp;<i class="fa fa-info-circle" ';
					$alert_ip_src .= 'title="' . gettext("This alert track by_src IP is already in the Suppress List") . '"></i>';
				}
				/* Add icon for auto-removing from Blocked Table if required */
				if (isset($tmpblocked[$fields['src']])) {
					$alert_ip_src .= "&nbsp;&nbsp;<i class=\"fa fa-times icon-pointer text-danger\" onClick=\"$('#ip').val('{$fields['src']}');$('#mode').val('todelete');$('#formalert').submit();\"";
					$alert_ip_src .= ' title="' . gettext("Remove host from Blocked Table") . '"></i>';
				}
			}
			else {
				if (preg_match('/\s\[Raw pkt:(.*)\]/', $buf, $tmp))
					$alert_ip_src = "<div title='[Raw pkt: {$tmp[1]}]'>" . gettext("Decoder Event") . "</div>";
				else
					$alert_ip_src = gettext("Decoder Event");
			}

			/* IP SRC Port */
			$alert_src_p = $fields['sport'];

			/* IP DST */
			if ($decoder_event == FALSE) {
				$alert_ip_dst = $fields['dst'];
				/* Add zero-width space as soft-break opportunity after each colon if we have an IPv6 address */
				$alert_ip_dst = str_replace(":", ":&#8203;", $alert_ip_dst);
				/* Add Reverse DNS lookup icons */
				$alert_ip_dst .= "<br /><i class=\"fa fa-search\" onclick=\"javascript:resolve_with_ajax('{$fields['dst']}');\" title=\"";
				$alert_ip_dst .= gettext("Resolve host via reverse DNS lookup") . "\" alt=\"Icon Reverse Resolve with DNS\" ";
				$alert_ip_dst .= " style=\"cursor: pointer;\"></i>";

				/* Add icons for auto-adding to Suppress List if appropriate */
				if (!suricata_is_alert_globally_suppressed($supplist, $fields['gid'], $fields['sid']) &&
				    !isset($supplist[$fields['gid']][$fields['sid']]['by_dst'][$fields['dst']])) {
					$alert_ip_dst .= "&nbsp;&nbsp;<i class=\"fa fa-plus-square-o icon-pointer\" onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','{$fields['dst']}','{$alert_descr}');$('#mode').val('addsuppress_dstip');$('#formalert').submit();\"";
					$alert_ip_dst .= ' title="' . gettext("Add this alert to the Suppress List and track by_dst IP") . '"></i>';
				}
				elseif (isset($supplist[$fields['gid']][$fields['sid']]['by_dst'][$fields['dst']])) {
					$alert_ip_dst .= '&nbsp;<i class="fa fa-info-circle" ';
					$alert_ip_dst .= 'title="' . gettext("This alert track by_dst IP is already in the Suppress List") . '"></i>';
				}

				/* Add icon for auto-removing from Blocked Table if required */
				if (isset($tmpblocked[$fields['dst']])) {
					$alert_ip_dst .= '&nbsp;&nbsp;<i name="todelete[]" class="fa fa-times icon-pointer text-danger" onClick="$(\'#ip\').val(\'' . $fields['dst'] . '\');$(\'#mode\').val(\'todelete\');$(\'#formalert\').submit();" ';
					$alert_ip_dst .= ' title="' . gettext("Remove host from Blocked Table") . '"></i>';
				}
			}
			else {
				$alert_ip_dst = gettext("n/a");
			}

			/* IP DST Port */
			$alert_dst_p = $fields['dport'];

			/* SID */
			$alert_sid_str = "{$fields['gid']}:{$fields['sid']}";
			if (!suricata_is_alert_globally_suppressed($supplist, $fields['gid'], $fields['sid'])) {
				$sidsupplink = "<i class=\"fa fa-plus-square-o icon-pointer\" onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','','{$alert_descr}');$('#mode').val('addsuppress');$('#formalert').submit();\"";
				$sidsupplink .= ' title="' . gettext("Add this alert to the Suppress List") . '"></i>';
			}
			else {
				$sidsupplink = '&nbsp;<i class="fa fa-info-circle" ';
				$sidsupplink .= "title='" . gettext("This alert is already in the Suppress List") . "'></i>";
			}
			/* Add icon for toggling rule state */
			if (isset($disablesid[$fields['gid']][$fields['sid']])) {
				$sid_dsbl_link = "<i class=\"fa fa-times-circle icon-pointer text-warning\" onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','','');$('#mode').val('togglesid');$('#formalert').submit();\"";
				$sid_dsbl_link .= ' title="' . gettext("Rule is forced to a disabled state. Click to remove the force-disable action from this rule.") . '"></i>';
			}
			else {
				$sid_dsbl_link = "<i class=\"fa fa-times icon-pointer text-danger\" onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','','');$('#mode').val('togglesid');$('#formalert').submit();\"";
				$sid_dsbl_link .= ' title="' . gettext("Force-disable this rule and remove it from current rules set.") . '"></i>';
			}
			/* DESCRIPTION */
			$alert_class = $fields['class'];
	?>
	<?php if ($fields['action']) : ?>
			<tr class="text-danger">
	<?php else : ?>
			<tr>
	<?php endif; ?>
				<td><?=$alert_date;?><br/><?=$alert_time;?></td>
				<td><?=$alert_priority;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_proto;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_class;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_ip_src;?></td>
				<td><?=$alert_src_p;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_ip_dst;?></td>
				<td><?=$alert_dst_p;?></td>
				<td><?=$alert_sid_str;?><br/><?=$sidsupplink;?>&nbsp;&nbsp;<?=$sid_dsbl_link;?></td>
				<td style="word-wrap:break-word; white-space:normal"><?=$alert_descr;?></td>
			</tr>
	<?php
			$counter++;
		}
		unset($fields, $buf, $tmp);
		fclose($fd);
		unlink_if_exists("{$g['tmp_path']}/alerts_suricata{$suricata_uuid}");
	}
}
	?>
			</tbody>
		</table>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[

//-- This function stuffs the passed GID, SID and other values into
//-- hidden Form Fields for postback.
function encRuleSig(rulegid,rulesid,srcip,ruledescr) {

	if (typeof srcipip === 'undefined') {
		var srcipip = '';
	}

	if (typeof ruledescr === 'undefined'){
		var ruledescr = '';
	}

	$('#sidid').val(rulesid);
	$('#gen_id').val(rulegid);
	$('#ip').val(srcip);
	$('#descr').val(ruledescr);
}

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
	var url = "/suricata/suricata_alerts.php";

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
<?php
include("foot.inc");
?>

