<?php
/*
 * suricata_alerts.php
 * part of pfSense
 *
 * Significant portions of this code are based on original work done
 * for the Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
 * Copyright (C) 2014 Jim Pingle jim@pingle.org
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

	if (!is_array($config['installedpackages']['suricata']['suppress']))
		$config['installedpackages']['suricata']['suppress'] = array();
	if (!is_array($config['installedpackages']['suricata']['suppress']['item']))
		$config['installedpackages']['suricata']['suppress']['item'] = array();
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
	$filterfieldsarray['time'] = $_POST['filterlogentries_time'] ? $_POST['filterlogentries_time'] : null;
	$filterfieldsarray['action'] = null;
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

if (($_POST['addsuppress_srcip'] || $_POST['addsuppress_dstip'] || $_POST['addsuppress']) && is_numeric($_POST['sidid']) && is_numeric($_POST['gen_id'])) {
	if ($_POST['addsuppress_srcip'])
		$method = "by_src";
	elseif ($_POST['addsuppress_dstip'])
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

if ($_POST['togglesid'] && is_numeric($_POST['sidid']) && is_numeric($_POST['gen_id'])) {
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

if ($_POST['delete']) {
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

$pgtitle = gettext("Suricata: Alerts");
include_once("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include_once("fbegin.inc");

/* refresh every 60 secs */
if ($pconfig['arefresh'] == 'on')
	echo "<meta http-equiv=\"refresh\" content=\"60;url=/suricata/suricata_alerts.php?instance={$instanceid}\" />\n";
?>

<form action="/suricata/suricata_alerts.php" method="post" id="formalert">
<input type="hidden" name="sidid" id="sidid" value=""/>
<input type="hidden" name="gen_id" id="gen_id" value=""/>
<input type="hidden" name="ip" id="ip" value=""/>
<input type="hidden" name="descr" id="descr" value=""/>

<?php
/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tbody>
<tr><td>
<?php
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
?>
</td></tr>
<tr>
	<td><div id="mainarea">
		<table id="maintable" class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6">
			<tbody>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("Alert Log View Settings"); ?></td>
			</tr>
			<tr>
				<td width="22%" class="vncell"><?php echo gettext('Instance to Inspect'); ?></td>
				<td width="78%" class="vtable">
					<select name="instance" id="instance" class="formselect" onChange="document.getElementById('formalert').method='post';document.getElementById('formalert').submit()">
			<?php
				foreach ($a_instance as $id => $instance) {
					$selected = "";
					if ($id == $instanceid)
						$selected = "selected";
					echo "<option value='{$id}' {$selected}> (" . convert_friendly_interface_to_friendly_descr($instance['interface']) . ") {$instance['descr']}</option>\n";
				}
			?>
					</select>&nbsp;&nbsp;<?php echo gettext('Choose which instance alerts you want to inspect.'); ?>
				</td>
			<tr>
				<td width="22%" class="vncell"><?php echo gettext('Save or Remove Logs'); ?></td>
				<td width="78%" class="vtable">
					<input name="download" type="submit" class="formbtns" value="Download" 
					title="<?=gettext("Download interface log files as a gzip archive");?>"/>
					&nbsp;<?php echo gettext('All log files will be saved.');?>&nbsp;&nbsp;
					<input name="delete" type="submit" class="formbtns" value="Clear" 
					onclick="return confirm('Do you really want to remove all instance logs?')" title="<?=gettext("Clear all interface log files");?>"/>
					&nbsp;<span class="red"><strong><?php echo gettext('Warning:'); ?></strong></span>&nbsp;<?php echo gettext('all log files will be deleted.'); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" class="vncell"><?php echo gettext('Auto Refresh and Log View'); ?></td>
				<td width="78%" class="vtable">
					<input name="save" type="submit" class="formbtns" value=" Save " title="<?=gettext("Save auto-refresh and view settings");?>"/>
					&nbsp;<?php echo gettext('Refresh');?>&nbsp;&nbsp;<input name="arefresh" type="checkbox" value="on" 
					<?php if ($config['installedpackages']['suricata']['alertsblocks']['arefresh']=="on") echo "checked"; ?>/>
					<?php printf(gettext('%sDefault%s is %sON%s.'), '<strong>', '</strong>', '<strong>', '</strong>'); ?>&nbsp;&nbsp;
					<input name="alertnumber" type="text" class="formfld unknown" id="alertnumber" size="5" value="<?=htmlspecialchars($anentries);?>"/>
					&nbsp;<?php printf(gettext('Enter number of log entries to view. %sDefault%s is %s250%s.'), '<strong>', '</strong>', '<strong>', '</strong>'); ?>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("Alert Log View Filter"); ?></td>
			</tr>
			<tr id="filter_enable_row" style="display:<?php if (!$filterlogentries) {echo "table-row;";} else {echo "none;";} ?>">
				<td width="22%" class="vncell"><?php echo gettext('Alert Log Filter Options'); ?></td>
				<td width="78%" class="vtable">
					<input name="show_filter" id="show_filter" type="button" class="formbtns" value="<?=gettext("Show Filter");?>" onclick="enable_showFilter();" />
					&nbsp;&nbsp;<?=gettext("Click to display advanced filtering options dialog");?>
				</td>
			</tr>
			<tr id="filter_options_row" style="display:<?php if (!$filterlogentries) {echo "none;";} else {echo "table-row;";} ?>">
				<td colspan="2">
					<table width="100%" border="0" cellpadding="0" cellspacing="1" summary="action">
						<tr>
							<td valign="top">
								<div align="center"><?=gettext("Date");?></div>
								<div align="center"><input id="filterlogentries_time" name="filterlogentries_time" class="formfld search" type="text" size="10" value="<?= $filterfieldsarray['time'] ?>" /></div>
							</td>
							<td valign="top">
								<div align="center"><?=gettext("Source IP Address");?></div>
								<div align="center"><input id="filterlogentries_sourceipaddress" name="filterlogentries_sourceipaddress" class="formfld search" type="text" size="28" value="<?= $filterfieldsarray['src'] ?>" /></div>
							</td>
							<td valign="top">
								<div align="center"><?=gettext("Source Port");?></div>
								<div align="center"><input id="filterlogentries_sourceport" name="filterlogentries_sourceport" class="formfld search" type="text" size="5" value="<?= $filterfieldsarray['sport'] ?>" /></div>
							</td>
							<td valign="top">
								<div align="center"><?=gettext("Description");?></div>
								<div align="center"><input id="filterlogentries_description" name="filterlogentries_description" class="formfld search" type="text" size="28" value="<?= $filterfieldsarray['msg'] ?>" /></div>
							</td>
							<td valign="top">
								<div align="center"><?=gettext("GID");?></div>
								<div align="center"><input id="filterlogentries_gid" name="filterlogentries_gid" class="formfld search" type="text" size="6" value="<?= $filterfieldsarray['gid'] ?>" /></div>
							</td>
						</tr>
						<tr>
							<td valign="top">
								<div align="center"><?=gettext("Priority");?></div>
								<div align="center"><input id="filterlogentries_priority" name="filterlogentries_priority" class="formfld search" type="text" size="10" value="<?= $filterfieldsarray['priority'] ?>" /></div>
							</td>
							<td valign="top">
								<div align="center"><?=gettext("Destination IP Address");?></div>
								<div align="center"><input id="filterlogentries_destinationipaddress" name="filterlogentries_destinationipaddress" class="formfld search" type="text" size="28" value="<?= $filterfieldsarray['dst'] ?>" /></div>
							</td>
							<td valign="top">
								<div align="center"><?=gettext("Destination Port");?></div>
								<div align="center"><input id="filterlogentries_destinationport" name="filterlogentries_destinationport" class="formfld search" type="text" size="5" value="<?= $filterfieldsarray['dport'] ?>" /></div>
							</td>
							<td valign="top">
								<div align="center"><?=gettext("Classification");?></div>
								<div align="center"><input id="filterlogentries_classification" name="filterlogentries_classification" class="formfld search" type="text" size="28" value="<?= $filterfieldsarray['class'] ?>" /></div>
							</td>
							<td valign="top">
								<div align="center"><?=gettext("SID");?></div>
								<div align="center"><input id="filterlogentries_sid" name="filterlogentries_sid" class="formfld search" type="text" size="6" value="<?= $filterfieldsarray['sid'] ?>" /></div>
							</td>
						</tr>
						<tr>
							<td valign="top">
								<div align="center"><?=gettext("Protocol");?></div>
								<div align="center"><input id="filterlogentries_protocol" name="filterlogentries_protocol" class="formfld search" type="text" size="10" value="<?= $filterfieldsarray['proto'] ?>" /></div>
							</td>
							<td valign="top">
							</td>
							<td valign="top">
							</td>
							<td colspan="2" style="vertical-align:bottom">
								<div align="right"><input id="filterlogentries_submit" name="filterlogentries_submit" type="submit" class="formbtns" value="<?=gettext("Filter");?>" title="<?=gettext("Apply filter"); ?>" />
								&nbsp;&nbsp;&nbsp;<input id="filterlogentries_clear" name="filterlogentries_clear" type="submit" class="formbtns" value="<?=gettext("Clear");?>" title="<?=gettext("Remove filter");?>" />
								&nbsp;&nbsp;&nbsp;<input id="filterlogentries_hide" name="filterlogentries_hide" type="button" class="formbtns" value="<?=gettext("Hide");?>" onclick="enable_hideFilter();" title="<?=gettext("Hide filter options");?>" /></div>
							</td>
						</tr>
						<tr>
							<td colspan="5" style="vertical-align:bottom">
								&nbsp;<?printf(gettext('Matches %1$s regular expression%2$s.'), '<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">', '</a>');?>&nbsp;&nbsp;
								<?=gettext("Precede with exclamation (!) as first character to exclude match.");?>&nbsp;&nbsp;
							</td>
						</tr>
					</table>
				</td>
			</tr>
		<?php if ($filterlogentries && count($filterfieldsarray)) : ?>
			<tr>
				<td colspan="2" class="listtopic"><?php printf(gettext("Last %s Alert Entries"), $anentries); ?>&nbsp;&nbsp;
				<?php echo gettext("(Most recent listed first)  ** FILTERED VIEW **  clear filter to see all entries"); ?></td>
			</tr>
		<?php else: ?>
			<tr>
				<td colspan="2" class="listtopic"><?php printf(gettext("Last %s Alert Entries"), $anentries); ?>&nbsp;&nbsp;
				<?php echo gettext("(Most recent entries are listed first)"); ?></td>
			</tr>
		<?php endif; ?>
	<tr>
	<td width="100%" colspan="2">
	<table id="myTable" style="table-layout: fixed;" width="100%" class="sortable" border="0" cellpadding="0" cellspacing="0">
		<colgroup>
			<col width="10%" align="center" axis="date">
			<col width="40" align="center" axis="number">
			<col width="52" align="center" axis="string">
			<col width="10%" axis="string">
			<col width="13%" align="center" axis="string">
			<col width="7%" align="center" axis="string">
			<col width="13%" align="center" axis="string">
			<col width="7%" align="center" axis="string">
			<col width="10%" align="center" axis="number">
			<col axis="string">
		</colgroup>
		<thead>
		   <tr class="sortableHeaderRowIdentifier">
			<th class="listhdrr" axis="date"><?php echo gettext("Date"); ?></th>
			<th class="listhdrr" axis="number"><?php echo gettext("Pri"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("Proto"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("Class"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("Src"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("SPort"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("Dst"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("DPort"); ?></th>
			<th class="listhdrr" axis="number"><?php echo gettext("GID:SID"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("Description"); ?></th>
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

			// Field 1 is currently not used, so set to NULL
			$fields['action'] = null;

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

			$fields['time'] = date_format($event_tm, "m/d/Y") . " " . date_format($event_tm, "H:i:s");
			if ($filterlogentries && !suricata_match_filter_field($fields, $filterfieldsarray)) {
				continue;
			}

			/* Time */
			$alert_time = date_format($event_tm, "H:i:s");
			/* Date */
			$alert_date = date_format($event_tm, "m/d/Y");
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
				$alert_ip_src .= "<br/><img onclick=\"javascript:resolve_with_ajax('{$fields['src']}');\" title=\"";
				$alert_ip_src .= gettext("Resolve host via reverse DNS lookup") . "\" border=\"0\" src=\"/themes/{$g['theme']}/images/icons/icon_log.gif\" alt=\"Icon Reverse Resolve with DNS\" ";
				$alert_ip_src .= " style=\"cursor: pointer;\"/>";
				/* Add icons for auto-adding to Suppress List if appropriate */
				if (!suricata_is_alert_globally_suppressed($supplist, $fields['gid'], $fields['sid']) && 
				    !isset($supplist[$fields['gid']][$fields['sid']]['by_src'][$fields['src']])) {
					$alert_ip_src .= "&nbsp;&nbsp;<input type='image' name='addsuppress_srcip[]' onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','{$fields['src']}','{$alert_descr}');\" ";
					$alert_ip_src .= "src='../themes/{$g['theme']}/images/icons/icon_plus.gif' width='12' height='12' border='0' ";
					$alert_ip_src .= "title='" . gettext("Add this alert to the Suppress List and track by_src IP") . "'/>";	
				}
				elseif (isset($supplist[$fields['gid']][$fields['sid']]['by_src'][$fields['src']])) {
					$alert_ip_src .= "&nbsp;&nbsp;<img src='../themes/{$g['theme']}/images/icons/icon_plus_d.gif' width='12' height='12' border='0' ";
					$alert_ip_src .= "title='" . gettext("This alert track by_src IP is already in the Suppress List") . "'/>";	
				}
				/* Add icon for auto-removing from Blocked Table if required */
				if (isset($tmpblocked[$fields['src']])) {
					$alert_ip_src .= "&nbsp;<input type='image' name='unblock[]' onClick=\"document.getElementById('ip').value='{$fields['src']}';\" ";
					$alert_ip_src .= "title='" . gettext("Remove host from Blocked Table") . "' border='0' width='12' height='12' src=\"../themes/{$g['theme']}/images/icons/icon_x.gif\"/>"; 
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
				$alert_ip_dst .= "<br/><img onclick=\"javascript:resolve_with_ajax('{$fields['dst']}');\" title=\"";
				$alert_ip_dst .= gettext("Resolve host via reverse DNS lookup") . "\" border=\"0\" src=\"/themes/{$g['theme']}/images/icons/icon_log.gif\" alt=\"Icon Reverse Resolve with DNS\" ";
				$alert_ip_dst .= " style=\"cursor: pointer;\"/>";
				/* Add icons for auto-adding to Suppress List if appropriate */
				if (!suricata_is_alert_globally_suppressed($supplist, $fields['gid'], $fields['sid']) && 
				    !isset($supplist[$fields['gid']][$fields['sid']]['by_dst'][$fields['dst']])) {
					$alert_ip_dst .= "&nbsp;&nbsp;<input type='image' name='addsuppress_dstip[]' onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','{$fields['dst']}','{$alert_descr}');\" ";
					$alert_ip_dst .= "src='../themes/{$g['theme']}/images/icons/icon_plus.gif' width='12' height='12' border='0' ";
					$alert_ip_dst .= "title='" . gettext("Add this alert to the Suppress List and track by_dst IP") . "'/>";	
				}
				elseif (isset($supplist[$fields['gid']][$fields['sid']]['by_dst'][$fields['dst']])) {
					$alert_ip_dst .= "&nbsp;&nbsp;<img src='../themes/{$g['theme']}/images/icons/icon_plus_d.gif' width='12' height='12' border='0' ";
					$alert_ip_dst .= "title='" . gettext("This alert track by_dst IP is already in the Suppress List") . "'/>";	
				}

				/* Add icon for auto-removing from Blocked Table if required */
				if (isset($tmpblocked[$fields['dst']])) {
					$alert_ip_dst .= "&nbsp;<input type='image' name='unblock[]' onClick=\"document.getElementById('ip').value='{$fields['dst']}';\" ";
					$alert_ip_dst .= "title='" . gettext("Remove host from Blocked Table") . "' border='0' width='12' height='12' src=\"../themes/{$g['theme']}/images/icons/icon_x.gif\"/>"; 
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
				$sidsupplink = "<input type='image' name='addsuppress[]' onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','','{$alert_descr}');\" ";
				$sidsupplink .= "src='../themes/{$g['theme']}/images/icons/icon_plus.gif' width='12' height='12' border='0' ";
				$sidsupplink .= "title='" . gettext("Add this alert to the Suppress List") . "'/>";	
			}
			else {
				$sidsupplink = "<img src='../themes/{$g['theme']}/images/icons/icon_plus_d.gif' width='12' height='12' border='0' ";
				$sidsupplink .= "title='" . gettext("This alert is already in the Suppress List") . "'/>";	
			}
			/* Add icon for toggling rule state */
			if (isset($disablesid[$fields['gid']][$fields['sid']])) {
				$sid_dsbl_link = "<input type='image' name='togglesid[]' onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','','');\" ";
				$sid_dsbl_link .= "src='../themes/{$g['theme']}/images/icons/icon_reject.gif' width='11' height='11' border='0' ";
				$sid_dsbl_link .= "title='" . gettext("Rule is forced to a disabled state. Click to remove the force-disable action from this rule.") . "'/>";
			}
			else {
				$sid_dsbl_link = "<input type='image' name='togglesid[]' onClick=\"encRuleSig('{$fields['gid']}','{$fields['sid']}','','');\" ";
				$sid_dsbl_link .= "src='../themes/{$g['theme']}/images/icons/icon_block.gif' width='11' height='11' border='0' ";
				$sid_dsbl_link .= "title='" . gettext("Force-disable this rule and remove it from current rules set.") . "'/>";
			}
			/* DESCRIPTION */
			$alert_class = $fields['class'];

			echo "<tr>
				<td class='listr' align='center'>{$alert_date}<br/>{$alert_time}</td>
				<td class='listr' align='center'>{$alert_priority}</td>
				<td class='listr' align='center'>{$alert_proto}</td>
				<td class='listr' style=\"word-wrap:break-word;\">{$alert_class}</td>
				<td class='listr' style=\"sorttable_customkey:{$fields['src']};\" sorttable_customkey=\"{$fields['src']}\" align='center'>{$alert_ip_src}</td>
				<td class='listr' align='center'>{$alert_src_p}</td>
				<td class='listr' align='center' style=\"sorttable_customkey:{$fields['dst']};\" sorttable_customkey=\"{$fields['dst']}\">{$alert_ip_dst}</td>
				<td class='listr' align='center'>{$alert_dst_p}</td>
				<td class='listr' align='center' style=\"sorttable_customkey:{$fields['sid']};\" sorttable_customkey=\"{$fields['sid']}\">{$alert_sid_str}<br/>{$sidsupplink}&nbsp;&nbsp;{$sid_dsbl_link}</td>
				<td class='listbg' style=\"word-wrap:break-word;\">{$alert_descr}</td>
				</tr>\n";

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
	</td>
</tr></tbody>
</table>
</div>
</td></tr></tbody>
</table>
</form>
<?php
include("fend.inc");
?>
<script type="text/javascript">
function encRuleSig(rulegid,rulesid,srcip,ruledescr) {

	// This function stuffs the passed GID, SID
	// and other values into hidden Form Fields
	// for postback.
	if (typeof srcipip == "undefined")
		var srcipip = "";
	if (typeof ruledescr == "undefined")
		var ruledescr = "";
	document.getElementById("sidid").value = rulesid;
	document.getElementById("gen_id").value = rulegid;
	document.getElementById("ip").value = srcip;
	document.getElementById("descr").value = ruledescr;
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

	jQuery.ajax(
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
	var response = jQuery.parseJSON(transport.responseText);
	var msg = 'IP address "' + response.resolve_ip + '" resolves to\n';
	alert(msg + 'host "' + htmlspecialchars(response.resolve_text) + '"');
}

// From http://stackoverflow.com/questions/5499078/fastest-method-to-escape-html-tags-as-html-entities
function htmlspecialchars(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
}
//]]>
</script>

</body>
</html>
