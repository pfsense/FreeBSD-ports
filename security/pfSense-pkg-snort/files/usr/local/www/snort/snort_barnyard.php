<?php
/*
 * snort_barnyard.php
 * part of pfSense
 *
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2008-2009 Robert Zelaya.
 * Copyright (C) 2014 Bill Meeks
 * All rights reserved.
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

global $g, $rebuild_rules;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
        header("Location: /snort/snort_interfaces.php");
        exit;
}

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
$a_nat = &$config['installedpackages']['snortglobal']['rule'];

$pconfig = array();

// The keys in the $retentions array are the retention period
// converted to hours. 
$retentions = array( '0' => gettext('KEEP ALL'), '24' => gettext('1 DAY'), '168' => gettext('7 DAYS'), '336' => gettext('14 DAYS'), 
		     '720' => gettext('30 DAYS'), '1080' => gettext("45 DAYS"), '2160' => gettext('90 DAYS'), '4320' => gettext('180 DAYS'), 
		     '8766' => gettext('1 YEAR'), '26298' => gettext("3 YEARS") );

$log_sizes = array( '0' => gettext('NO LIMIT'), '128K' => '128 KB', '256K' => '256 KB', '512K' => '512 KB', '1M' => '1 MB', '4M' => '4 MB', '8M' => gettext('8 MB'), 
		    '16M' => gettext('16 MB'), '32M' => gettext('32 MB'), '64M' => gettext('64 MB'), '128M' => gettext('128 MB'), '256M' => gettext('256 MB') );

if (isset($id) && $a_nat[$id]) {
	$pconfig = $a_nat[$id];
	if (!empty($a_nat[$id]['barnconfigpassthru']))
		$pconfig['barnconfigpassthru'] = base64_decode($a_nat[$id]['barnconfigpassthru']);
	if (!empty($a_nat[$id]['barnyard_dbpwd']))
		$pconfig['barnyard_dbpwd'] = base64_decode($a_nat[$id]['barnyard_dbpwd']);
	if (empty($a_nat[$id]['barnyard_show_year']))
		$pconfig['barnyard_show_year'] = "on";
	if (empty($a_nat[$id]['unified2_log_limit']))
		$pconfig['unified2_log_limit'] = "128K";
	if (empty($a_nat[$id]['barnyard_archive_enable']))
		$pconfig['barnyard_archive_enable'] = "on";
	if (empty($a_nat[$id]['u2_archived_log_retention']))
		$pconfig['u2_archived_log_retention'] = "168";
	if (empty($a_nat[$id]['barnyard_obfuscate_ip']))
		$pconfig['barnyard_obfuscate_ip'] = "off";
	if (empty($a_nat[$id]['barnyard_syslog_dport']))
		$pconfig['barnyard_syslog_dport'] = "514";
	if (empty($a_nat[$id]['barnyard_syslog_proto']))
		$pconfig['barnyard_syslog_proto'] = "udp";
	if (empty($a_nat[$id]['barnyard_syslog_opmode']))
		$pconfig['barnyard_syslog_opmode'] = "default";
	if (empty($a_nat[$id]['barnyard_syslog_facility']))
		$pconfig['barnyard_syslog_facility'] = "LOG_USER";
	if (empty($a_nat[$id]['barnyard_syslog_priority']))
		$pconfig['barnyard_syslog_priority'] = "LOG_INFO";
	if (empty($a_nat[$id]['barnyard_bro_ids_dport']))
		$pconfig['barnyard_bro_ids_dport'] = "47760";
}

if ($_POST['save']) {

	// If disabling Barnyard2 on the interface, stop any
	// currently running instance, then save the disabled
	// state and exit.
	if ($_POST['barnyard_enable'] != 'on') {
		$a_nat[$id]['barnyard_enable'] = 'off';
		write_config("Snort pkg: modified Barnyard2 settings.");
		touch("{$g['varrun_path']}/barnyard2_{$uuid}.disabled");
		snort_barnyard_stop($a_nat[$id], get_real_interface($a_nat[$id]['interface']));

		// No need to rebuild rules for Barnyard2 changes
		$rebuild_rules = false;
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_barnyard.php");
		exit;
	}

	// Check that at least one output plugin is enabled
	if ($_POST['barnyard_mysql_enable'] != 'on' && $_POST['barnyard_syslog_enable'] != 'on' &&
	    $_POST['barnyard_bro_ids_enable'] != 'on' && $_POST['barnyard_enable'] == "on")
		$input_errors[] = gettext("You must enable at least one output option when using Barnyard2.");

	// Validate inputs if MySQL database loggging enabled
	if ($_POST['barnyard_mysql_enable'] == 'on' && $_POST['barnyard_enable'] == "on") {
		if (empty($_POST['barnyard_dbhost']))
			$input_errors[] = gettext("Please provide a valid hostname or IP address for the MySQL database host.");
		if (empty($_POST['barnyard_dbname']))
			$input_errors[] = gettext("You must provide a DB instance name when logging to a MySQL database.");
		if (empty($_POST['barnyard_dbuser']))
			$input_errors[] = gettext("You must provide a DB user login name when logging to a MySQL database.");
	}

	// Validate Sensor Name contains no spaces
	if ($_POST['barnyard_enable'] == 'on') {
		if (!empty($_POST['barnyard_sensor_name']) && strpos($_POST['barnyard_sensor_name'], " ") !== FALSE)
			$input_errors[] = gettext("The value for 'Sensor Name' cannot contain spaces.");
	}

	// Validate inputs if syslog output enabled
	if ($_POST['barnyard_syslog_enable'] == 'on' && $_POST['barnyard_enable'] == "on") {
		if ($_POST['barnyard_log_vlan_events'] == 'on' || $_POST['barnyard_log_mpls_events'] == 'on')
			$input_errors[] = gettext("Logging of VLAN or MPLS events is not compatible with syslog output.  You must disable VLAN and MPLS event type logging when using the syslog output option.");
	}
	if ($_POST['barnyard_syslog_enable'] == 'on' && $_POST['barnyard_syslog_local'] <> 'on' &&
	    $_POST['barnyard_enable'] == "on") {
		if (empty($_POST['barnyard_syslog_dport']) || !is_numeric($_POST['barnyard_syslog_dport']))
			$input_errors[] = gettext("Please provide a valid number between 1 and 65535 for the Syslog Remote Port.");
		if (empty($_POST['barnyard_syslog_rhost']))
			$input_errors[] = gettext("Please provide a valid hostname or IP address for the Syslog Remote Host.");
	}

	// Validate inputs if Bro-IDS output enabled
	if ($_POST['barnyard_bro_ids_enable'] == 'on' && $_POST['barnyard_enable'] == "on") {
		if (empty($_POST['barnyard_bro_ids_dport']) || !is_numeric($_POST['barnyard_bro_ids_dport']))
			$input_errors[] = gettext("Please provide a valid number between 1 and 65535 for the Bro-IDS Remote Port.");
		if (empty($_POST['barnyard_bro_ids_rhost']))
			$input_errors[] = gettext("Please provide a valid hostname or IP address for the Bro-IDS Remote Host.");
	}

	// if no errors write to conf
	if (!$input_errors) {
		$natent = array();
		/* repost the options already in conf */
		$natent = $pconfig;

		$natent['barnyard_enable'] = $_POST['barnyard_enable'] ? 'on' : 'off';
		$natent['barnyard_show_year'] = $_POST['barnyard_show_year'] ? 'on' : 'off';
		$natent['barnyard_archive_enable'] = $_POST['barnyard_archive_enable'] ? 'on' : 'off';
		$natent['barnyard_dump_payload'] = $_POST['barnyard_dump_payload'] ? 'on' : 'off';
		$natent['barnyard_obfuscate_ip'] = $_POST['barnyard_obfuscate_ip'] ? 'on' : 'off';
		$natent['barnyard_log_vlan_events'] = $_POST['barnyard_log_vlan_events'] ? 'on' : 'off';
		$natent['barnyard_log_mpls_events'] = $_POST['barnyard_log_mpls_events'] ? 'on' : 'off';
		$natent['barnyard_mysql_enable'] = $_POST['barnyard_mysql_enable'] ? 'on' : 'off';
		$natent['barnyard_syslog_enable'] = $_POST['barnyard_syslog_enable'] ? 'on' : 'off';
		$natent['barnyard_syslog_local'] = $_POST['barnyard_syslog_local'] ? 'on' : 'off';
		$natent['barnyard_bro_ids_enable'] = $_POST['barnyard_bro_ids_enable'] ? 'on' : 'off';
		$natent['barnyard_disable_sig_ref_tbl'] = $_POST['barnyard_disable_sig_ref_tbl'] ? 'on' : 'off';
		$natent['barnyard_syslog_opmode'] = $_POST['barnyard_syslog_opmode'];
		$natent['barnyard_syslog_proto'] = $_POST['barnyard_syslog_proto'];

		if ($_POST['unified2_log_limit']) $natent['unified2_log_limit'] = $_POST['unified2_log_limit']; else unset($natent['unified2_log_limit']);
		if ($_POST['u2_archived_log_retention']) $natent['u2_archived_log_retention'] = $_POST['u2_archived_log_retention']; else unset($natent['u2_archived_log_retention']);
		if ($_POST['barnyard_sensor_name']) $natent['barnyard_sensor_name'] = $_POST['barnyard_sensor_name']; else unset($natent['barnyard_sensor_name']);
		if ($_POST['barnyard_dbhost']) $natent['barnyard_dbhost'] = $_POST['barnyard_dbhost']; else unset($natent['barnyard_dbhost']);
		if ($_POST['barnyard_dbname']) $natent['barnyard_dbname'] = $_POST['barnyard_dbname']; else unset($natent['barnyard_dbname']);
		if ($_POST['barnyard_dbuser']) $natent['barnyard_dbuser'] = $_POST['barnyard_dbuser']; else unset($natent['barnyard_dbuser']);
		if ($_POST['barnyard_dbpwd']) $natent['barnyard_dbpwd'] = base64_encode($_POST['barnyard_dbpwd']); else unset($natent['barnyard_dbpwd']);
		if ($_POST['barnyard_syslog_rhost']) $natent['barnyard_syslog_rhost'] = $_POST['barnyard_syslog_rhost']; else unset($natent['barnyard_syslog_rhost']);
		if ($_POST['barnyard_syslog_dport']) $natent['barnyard_syslog_dport'] = $_POST['barnyard_syslog_dport']; else $natent['barnyard_syslog_dport'] = '514';
		if ($_POST['barnyard_syslog_facility']) $natent['barnyard_syslog_facility'] = $_POST['barnyard_syslog_facility']; else $natent['barnyard_syslog_facility'] = 'LOG_USER';
		if ($_POST['barnyard_syslog_priority']) $natent['barnyard_syslog_priority'] = $_POST['barnyard_syslog_priority']; else $natent['barnyard_syslog_priority'] = 'LOG_INFO';
		if ($_POST['barnyard_bro_ids_rhost']) $natent['barnyard_bro_ids_rhost'] = $_POST['barnyard_bro_ids_rhost']; else unset($natent['barnyard_bro_ids_rhost']);
		if ($_POST['barnyard_bro_ids_dport']) $natent['barnyard_bro_ids_dport'] = $_POST['barnyard_bro_ids_dport']; else $natent['barnyard_bro_ids_dport'] = '47760';
		if ($_POST['barnconfigpassthru']) $natent['barnconfigpassthru'] = base64_encode(str_replace("\r\n", "\n", $_POST['barnconfigpassthru'])); else unset($natent['barnconfigpassthru']);

		$a_nat[$id] = $natent;
		write_config("Snort pkg: modified Barnyard2 settings.");

		// No need to rebuild rules for Barnyard2 changes
		$rebuild_rules = false;
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();

		// If disabling Barnyard2 on the interface, stop any
		// currently running instance.  If an instance is
		// running, signal it to reload the configuration.
		// If Barnyard2 is enabled but not running, notify the
		// user to restart Snort to enable Unified2 output.
		if ($a_nat[$id]['barnyard_enable'] == "off") {
			snort_barnyard_stop($a_nat[$id], get_real_interface($a_nat[$id]['interface']));
		}
		elseif ($a_nat[$id]['barnyard_enable'] == "on") {
			if (snort_is_running($a_nat[$id]['uuid'], get_real_interface($a_nat[$id]['interface']), "barnyard2"))
				snort_barnyard_reload_config($a_nat[$id], "HUP");
			else {
				// Notify user a Snort restart is required if enabling Barnyard2 for the first time	
				$savemsg = gettext("NOTE: you must restart Snort on this interface to activate unified2 logging for Barnyard2.");
			}
		}
		$pconfig = $natent;
	}
	else {
		// We had errors, so save previous field data to prevent retyping
		$pconfig = $_POST;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']);
$pgtitle = gettext("Snort: Interface {$if_friendly} - Barnyard2 Settings");
include_once("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>


<?php
	/* Display Alert message */
	if ($input_errors) {
		print_input_errors($input_errors); // TODO: add checks
	}

	if ($savemsg) {
		print_info_box($savemsg);
	}

?>

<form action="snort_barnyard.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<input name="id" type="hidden" value="<?=$id;?>" /> </td>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
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
		$tab_array[] = array($menu_iface . gettext("Barnyard2"), true, "/snort/snort_barnyard.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/snort/snort_ip_reputation.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
		display_top_tabs($tab_array, true);
?>
</td></tr>
	<tr>
		<td><div id="mainarea">
		<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?php echo gettext("General Barnyard2 " .
				"Settings"); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq"><?php echo gettext("Enable"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_enable" type="checkbox" value="on" <?php if ($pconfig['barnyard_enable'] == "on") echo "checked"; ?>  onClick="enable_change(false)"/>
					<strong><?php echo gettext("Enable Barnyard2"); ?></strong><br/>
					<?php echo gettext("This will enable barnyard2 for this interface. You will also to enable at least one logging destination below."); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Show Year"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_show_year" type="checkbox" value="on" <?php if ($pconfig['barnyard_show_year'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Enable the year being shown in timestamps.  Default value is ") . "<strong>" . gettext("Checked") . "</strong>"; ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Unified2 Log Limit"); ?></td>
				<td width="78%" class="vtable"><select name="unified2_log_limit" class="formselect" id="unified2_log_limit">
					<?php foreach ($log_sizes as $k => $p): ?>
						<option value="<?=$k;?>"
						<?php if ($k == $pconfig['unified2_log_limit']) echo "selected"; ?>>
							<?=htmlspecialchars($p);?></option>
					<?php endforeach; ?>
					</select>&nbsp;<?php echo gettext("Choose a Unified2 Log file size limit. Default is "); ?><strong><?=gettext("128 KB.");?></strong><br/><br/>
					<?php echo gettext("This sets the maximum size for a Unified2 Log file before it is rotated and a new one created."); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Archive Unified2 Logs"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_archive_enable" type="checkbox" value="on" <?php if ($pconfig['barnyard_archive_enable'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Enable the archiving of processed unified2 log files.  Default value is ") . "<strong>" . gettext("Checked") . "</strong>"; ?><br/>
					<?php echo gettext("Unified2 log files will be moved to an archive folder for subsequent cleanup when processed."); ?>
				</td>
			</tr>
			<tr>
				<td class="vncell" width="22%" valign="top"><?=gettext("Unified2 Archived Log Retention Period");?></td>
				<td width="78%" class="vtable"><select name="u2_archived_log_retention" class="formselect" id="u2_archived_log_retention">
					<?php foreach ($retentions as $k => $p): ?>
						<option value="<?=$k;?>"
						<?php if ($k == $pconfig['u2_archived_log_retention']) echo "selected"; ?>>
								<?=htmlspecialchars($p);?></option>
					<?php endforeach; ?>
					</select>&nbsp;<?=gettext("Choose retention period for archived Barnyard2 binary log files. Default is ") . "<strong>" . gettext("7 days."). "</strong>";?><br/><br/>
					<?=gettext("When Barnyard2 output is enabled, Snort writes event data to a binary format file that Barnyard2 reads and processes. ") . 
					gettext("When finished processing a file, Barnyard2 moves it to an archive folder.  This setting determines how long files ") . 
					gettext("remain in the archive folder before they are automatically deleted.");?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Dump Payload"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_dump_payload" type="checkbox" value="on" <?php if ($pconfig['barnyard_dump_payload'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Enable dumping of application data from unified2 files.  Default value is ") . "<strong>" . gettext("Not Checked") . "</strong>"; ?><br/>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Obfuscate IP Addresses"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_obfuscate_ip" type="checkbox" value="on" <?php if ($pconfig['barnyard_obfuscate_ip'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Enable obfuscation of logged IP addresses.  Default value is ") . "<strong>" . gettext("Not Checked") . "</strong>"; ?>
				</td>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Log VLAN Events"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_log_vlan_events" type="checkbox" value="on" <?php if ($pconfig['barnyard_log_vlan_events'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Enable logging of VLAN event types in unified2 files.  Default value is ") . "<strong>" . gettext("Not Checked") . "</strong>"; ?><br/>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Log MPLS Events"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_log_mpls_events" type="checkbox" value="on" <?php if ($pconfig['barnyard_log_mpls_events'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Enable logging of MPLS event types in unified2 files.  Default value is ") . "<strong>" . gettext("Not Checked") . "</strong>"; ?><br/>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Sensor Name"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_sensor_name" type="text" class="formfld unknown" 
					id="barnyard_sensor_name" size="25" value="<?=htmlspecialchars($pconfig['barnyard_sensor_name']);?>"/>
					&nbsp;<?php echo gettext("Unique name for this sensor.  Leave blank to use internal default."); ?>
				</td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?php echo gettext("MySQL Database Output Settings"); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable MySQL Database"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_mysql_enable" type="checkbox" value="on" <?php if ($pconfig['barnyard_mysql_enable'] == "on") echo "checked"; ?> 
					onClick="toggle_mySQL()"/><?php echo gettext("Enable logging of alerts to a MySQL database instance"); ?><br/>
					<?php echo gettext("You will also have to provide the database credentials in the fields below."); ?></td>
			</tr>
			<tbody id="mysql_config_rows">
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Database Host"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_dbhost" type="text" class="formfld host" 
					id="barnyard_dbhost" size="25" value="<?=htmlspecialchars($pconfig['barnyard_dbhost']);?>"/>
					&nbsp;<?php echo gettext("Hostname or IP address of the MySQL database server"); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Database Name"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_dbname" type="text" class="formfld unknown" 
					id="barnyard_dbname" size="25" value="<?=htmlspecialchars($pconfig['barnyard_dbname']);?>"/>
					&nbsp;<?php echo gettext("Instance or DB name of the MySQL database"); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Database User Name"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_dbuser" type="text" class="formfld user" 
					id="barnyard_dbuser" size="25" value="<?=htmlspecialchars($pconfig['barnyard_dbuser']);?>"/>
					&nbsp;<?php echo gettext("Username for the MySQL database"); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Database User Password"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_dbpwd" type="password" class="formfld pwd" 
					id="barnyard_dbpwd" size="25" value="<?=htmlspecialchars($pconfig['barnyard_dbpwd']);?>"/>
					&nbsp;<?php echo gettext("Password for the MySQL database user"); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Disable Signature Reference Table"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_disable_sig_ref_tbl" type="checkbox" value="on" <?php if ($pconfig['barnyard_disable_sig_ref_tbl'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Disable synchronization of sig_reference table in schema.  Default value is ") . "<strong>" . gettext("Not Checked") . "</strong>"; ?><br/>
					<br/><?php echo gettext("This option will speedup the process when checked, plus it can help work around a 'duplicate entry' error when running multiple Snort instances."); ?>
				</td>
			</tr>
			</tbody>
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Syslog Output Settings"); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable Syslog"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_syslog_enable" type="checkbox" value="on" <?php if ($pconfig['barnyard_syslog_enable'] == "on") echo "checked"; ?> 
					onClick="toggle_syslog()"/>
					<?php echo gettext("Enable logging of alerts to a syslog receiver"); ?><br/>
					<?php echo gettext("This will send alert data to either a local or remote syslog receiver."); ?></td>
			</tr>
			<tbody id="syslog_config_rows">
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Operation Mode"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_syslog_opmode" type="radio" id="barnyard_syslog_opmode_default"  
					value="default" <?php if ($pconfig['barnyard_syslog_opmode'] == 'default') echo "checked";?>/>
					<?php echo gettext("DEFAULT"); ?>&nbsp;<input name="barnyard_syslog_opmode" type="radio" id="barnyard_syslog_opmode_complete" 
					value="complete" <?php if ($pconfig['barnyard_syslog_opmode'] == 'complete') echo "checked";?>/>
					<?php echo gettext("COMPLETE"); ?>&nbsp;&nbsp;
					<?php echo gettext("Select the level of detail to include when reporting"); ?><br/><br/>
					<?php echo gettext("DEFAULT mode is compatible with the standard Snort syslog format.  COMPLETE mode includes additional information such as the raw packet data (displayed in hex format)."); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Local Only"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_syslog_local" type="checkbox" value="on" <?php if ($pconfig['barnyard_syslog_local'] == "on") echo "checked"; ?> 
					onClick="toggle_local_syslog()"/>
					<?php echo gettext("Enable logging of alerts to the local system only"); ?><br/>
					<?php echo gettext("This will send alert data to the local system only and overrides the host, port, and protocol values below."); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Remote Host"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_syslog_rhost" type="text" class="formfld host" 
					id="barnyard_syslog_rhost" size="25" value="<?=htmlspecialchars($pconfig['barnyard_syslog_rhost']);?>"/>
					&nbsp;<?php echo gettext("Hostname or IP address of remote syslog host"); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Remote Port"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_syslog_dport" type="text" class="formfld unknown" 
					id="barnyard_syslog_dport" size="25" value="<?=htmlspecialchars($pconfig['barnyard_syslog_dport']);?>"/>
					&nbsp;<?php echo gettext("Port number for syslog on remote host.  Default is ") . "<strong>" . gettext("514") . "</strong>."; ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Protocol"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_syslog_proto" type="radio" id="barnyard_syslog_proto_udp"  
					value="udp" <?php if ($pconfig['barnyard_syslog_proto'] == 'udp') echo "checked";?>/>
					<?php echo gettext("UDP"); ?>&nbsp;<input name="barnyard_syslog_proto" type="radio" id="barnyard_syslog_proto_tcp" 
					value="tcp" <?php if ($pconfig['barnyard_syslog_proto'] == 'tcp') echo "checked";?>/>
					<?php echo gettext("TCP"); ?>&nbsp;&nbsp;
					<?php echo gettext("Select IP protocol to use for remote reporting.  Default is ") . "<strong>" . gettext("UDP") . "</strong>."; ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Log Facility"); ?></td>
				<td width="78%" class="vtable">
					<select name="barnyard_syslog_facility" id="barnyard_syslog_facility" class="formselect">
					<?php
						$log_facility = array(  "LOG_AUTH", "LOG_AUTHPRIV", "LOG_DAEMON", "LOG_KERN", "LOG_SYSLOG", "LOG_USER", "LOG_LOCAL1",
									"LOG_LOCAL2", "LOG_LOCAL3", "LOG_LOCAL4", "LOG_LOCAL5", "LOG_LOCAL6", "LOG_LOCAL7" );
						foreach ($log_facility as $facility) {
							$selected = "";
							if ($facility == $pconfig['barnyard_syslog_facility'])
								$selected = " selected";
							echo "<option value='{$facility}'{$selected}>" . $facility . "</option>\n";
						}
					?></select>&nbsp;&nbsp;
					<?php echo gettext("Select Syslog Facility to use for reporting.  Default is ") . "<strong>" . gettext("LOG_USER") . "</strong>."; ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Log Priority"); ?></td>
				<td width="78%" class="vtable">
					<select name="barnyard_syslog_priority" id="barnyard_syslog_priority" class="formselect">
					<?php
						$log_priority = array( "LOG_EMERG", "LOG_ALERT", "LOG_CRIT", "LOG_ERR", "LOG_WARNING", "LOG_NOTICE", "LOG_INFO" );
						foreach ($log_priority as $priority) {
							$selected = "";
							if ($priority == $pconfig['barnyard_syslog_priority'])
								$selected = " selected";
							echo "<option value='{$priority}'{$selected}>" . $priority . "</option>\n";
						}
					?></select>&nbsp;&nbsp;
					<?php echo gettext("Select Syslog Priority (Level) to use for reporting.  Default is ") . "<strong>" . gettext("LOG_INFO") . "</strong>."; ?>
				</td>
			</tr>
			</tbody>
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Bro-IDS Output Settings"); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable Bro-IDS"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_bro_ids_enable" type="checkbox" value="on" <?php if ($pconfig['barnyard_bro_ids_enable'] == "on") echo "checked"; ?> 
					onClick="toggle_bro_ids()"/>
					<?php echo gettext("Enable logging of alerts to a Bro-IDS receiver"); ?><br/>
					<?php echo gettext("This will send alert data to either a local or remote Bro-IDS receiver."); ?></td>
			</tr>
			<tbody id="bro_ids_config_rows">
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Remote Host"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_bro_ids_rhost" type="text" class="formfld host" 
					id="barnyard_bro_ids_rhost" size="25" value="<?=htmlspecialchars($pconfig['barnyard_bro_ids_rhost']);?>"/>
					&nbsp;<?php echo gettext("Hostname or IP address of remote Bro-IDS host"); ?>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Remote Port"); ?></td>
				<td width="78%" class="vtable">
					<input name="barnyard_bro_ids_dport" type="text" class="formfld unknown" 
					id="barnyard_bro_ids_dport" size="25" value="<?=htmlspecialchars($pconfig['barnyard_bro_ids_dport']);?>"/>
					&nbsp;<?php echo gettext("Port number for Bro-IDS instance on remote host.  Default is ") . "<strong>" . gettext("47760") . "</strong>."; ?>
				</td>
			</tr>
			</tbody>
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Advanced Settings"); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Advanced configuration " .
				"pass-through"); ?></td>
				<td width="78%" class="vtable"><textarea name="barnconfigpassthru" style="width:95%;"
					cols="65" rows="7" id="barnconfigpassthru" ><?=htmlspecialchars($pconfig['barnconfigpassthru']);?></textarea>
				<br/>
				<?php echo gettext("Arguments entered here will be automatically inserted into the running " .
				"barnyard2 configuration."); ?></td>
			</tr>
			<tr>
				<td width="22%" valign="top">&nbsp;</td>
				<td width="78%">
					<input name="save" type="submit" class="formbtn" value="Save" title="<?=gettext("Save Barnyard2 configuration");?>" />
			</tr>
			<tr>
				<td width="22%" valign="top">&nbsp;</td>
				<td width="78%"><span class="vexpl"><span class="red"><strong><?php echo gettext("Note:"); ?></strong></span></span>
				<br/>
				<?php echo gettext("Remember to save your settings before you leave this tab."); ?> </td>
			</tr>
		</table>
		</div>
		</td>
	</tr>
</table>
</form>

<script language="JavaScript">
function toggle_mySQL() {
	var endis = !document.iform.barnyard_mysql_enable.checked;

	document.iform.barnyard_dbhost.disabled = endis;
	document.iform.barnyard_dbname.disabled = endis;
	document.iform.barnyard_dbuser.disabled = endis;
	document.iform.barnyard_dbpwd.disabled = endis;
	document.iform.barnyard_disable_sig_ref_tbl.disabled = endis;

	if (endis)
		document.getElementById("mysql_config_rows").style.display = "none";
	else
		document.getElementById("mysql_config_rows").style.display = "";
}

function toggle_syslog() {
	var endis = !document.iform.barnyard_syslog_enable.checked;

	document.iform.barnyard_syslog_opmode_default.disabled = endis;
	document.iform.barnyard_syslog_opmode_complete.disabled = endis;
	document.iform.barnyard_syslog_local.disabled = endis;
	document.iform.barnyard_syslog_rhost.disabled = endis;
	document.iform.barnyard_syslog_dport.disabled = endis;
	document.iform.barnyard_syslog_proto_udp.disabled = endis;
	document.iform.barnyard_syslog_proto_tcp.disabled = endis;
	document.iform.barnyard_syslog_facility.disabled = endis;
	document.iform.barnyard_syslog_priority.disabled = endis;

	if (endis)
		document.getElementById("syslog_config_rows").style.display = "none";
	else
		document.getElementById("syslog_config_rows").style.display = "";
}

function toggle_local_syslog() {
	var endis = document.iform.barnyard_syslog_local.checked;

	if (document.iform.barnyard_syslog_enable.checked) {
		document.iform.barnyard_syslog_rhost.disabled = endis;
		document.iform.barnyard_syslog_dport.disabled = endis;
		document.iform.barnyard_syslog_proto_udp.disabled = endis;
		document.iform.barnyard_syslog_proto_tcp.disabled = endis;
	}
}

function toggle_bro_ids() {
	var endis = !document.iform.barnyard_bro_ids_enable.checked;

	document.iform.barnyard_bro_ids_rhost.disabled = endis;
	document.iform.barnyard_bro_ids_dport.disabled = endis;

	if (endis)
		document.getElementById("bro_ids_config_rows").style.display = "none";
	else
		document.getElementById("bro_ids_config_rows").style.display = "";
}

function enable_change(enable_change) {
	endis = !(document.iform.barnyard_enable.checked || enable_change);
	// make sure a default answer is called if this is invoked.
	endis2 = (document.iform.barnyard_enable);
	document.iform.unified2_log_limit.disabled = endis;
	document.iform.barnyard_archive_enable.disabled = endis;
	document.iform.u2_archived_log_retention.disabled = endis;
	document.iform.barnyard_show_year.disabled = endis;
	document.iform.barnyard_dump_payload.disabled = endis;
	document.iform.barnyard_obfuscate_ip.disabled = endis;
	document.iform.barnyard_log_vlan_events.disabled = endis;
	document.iform.barnyard_log_mpls_events.disabled = endis;
	document.iform.barnyard_sensor_name.disabled = endis;
	document.iform.barnyard_mysql_enable.disabled = endis;
	document.iform.barnyard_dbhost.disabled = endis;
	document.iform.barnyard_dbname.disabled = endis;
	document.iform.barnyard_dbuser.disabled = endis;
	document.iform.barnyard_dbpwd.disabled = endis;
	document.iform.barnyard_disable_sig_ref_tbl.disabled = endis;
	document.iform.barnyard_syslog_enable.disabled = endis;
	document.iform.barnyard_syslog_local.disabled = endis;
	document.iform.barnyard_syslog_opmode_default.disabled = endis;
	document.iform.barnyard_syslog_opmode_complete.disabled = endis;
	document.iform.barnyard_syslog_rhost.disabled = endis;
	document.iform.barnyard_syslog_dport.disabled = endis;
	document.iform.barnyard_syslog_proto_udp.disabled = endis;
	document.iform.barnyard_syslog_proto_tcp.disabled = endis;
	document.iform.barnyard_syslog_facility.disabled = endis;
	document.iform.barnyard_syslog_priority.disabled = endis;
	document.iform.barnyard_bro_ids_enable.disabled = endis;
	document.iform.barnyard_bro_ids_rhost.disabled = endis;
	document.iform.barnyard_bro_ids_dport.disabled = endis;
	document.iform.barnconfigpassthru.disabled = endis;
}

enable_change(false);
toggle_mySQL();
toggle_syslog();
toggle_local_syslog();
toggle_bro_ids();
</script>
<?php include("fend.inc"); ?>
</body>
</html>
