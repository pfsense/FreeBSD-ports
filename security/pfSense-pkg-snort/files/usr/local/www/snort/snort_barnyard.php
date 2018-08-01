<?php
/*
 * snort_barnyard.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>
 * Copyright (c) 2008-2009 Robert Zelaya
 * Copyright (c) 2014-2018 Bill Meeks
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

if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}
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
	if (empty($a_nat[$id]['barnyard_syslog_payload_encoding']))
		$pconfig['barnyard_syslog_payload_encoding'] = "hex";
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
		if ($_POST['barnyard_dbpwd'] != $_POST['barnyard_dbpwd_confirm'])
			$input_errors[] = gettext("The MySQL database passwords do not match!");
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
		$natent['barnyard_syslog_payload_encoding'] = $_POST['barnyard_syslog_payload_encoding'];
		$natent['barnyard_syslog_proto'] = $_POST['barnyard_syslog_proto'];

		if ($_POST['unified2_log_limit']) $natent['unified2_log_limit'] = $_POST['unified2_log_limit']; else unset($natent['unified2_log_limit']);
		if ($_POST['u2_archived_log_retention']) $natent['u2_archived_log_retention'] = $_POST['u2_archived_log_retention']; else unset($natent['u2_archived_log_retention']);
		if ($_POST['barnyard_sensor_name']) $natent['barnyard_sensor_name'] = $_POST['barnyard_sensor_name']; else unset($natent['barnyard_sensor_name']);
		if ($_POST['barnyard_dbhost']) $natent['barnyard_dbhost'] = $_POST['barnyard_dbhost']; else unset($natent['barnyard_dbhost']);
		if ($_POST['barnyard_dbname']) $natent['barnyard_dbname'] = $_POST['barnyard_dbname']; else unset($natent['barnyard_dbname']);
		if ($_POST['barnyard_dbuser']) $natent['barnyard_dbuser'] = $_POST['barnyard_dbuser']; else unset($natent['barnyard_dbuser']);

		// The password field will return '********' if no changes are made and needs to be escaped.
		// Because of the base64 encoding/decoding, in the case of a valid value that hasn't changed, it will need to be re-encoded to base64.
		if ($_POST['barnyard_dbpwd'] && ($_POST['barnyard_dbpwd'] != DMYPWD)) $natent['barnyard_dbpwd'] = base64_encode($_POST['barnyard_dbpwd']); else 
			if ($_POST['barnyard_dbpwd'] != DMYPWD) unset($natent['barnyard_dbpwd']); else $natent['barnyard_dbpwd'] = base64_encode($natent['barnyard_dbpwd']); 
		
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
if (empty($if_friendly)) {
	$if_friendly = "None";
}$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Barnyard2 Settings"), gettext("{$if_friendly}"));
include_once("head.inc");

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors); // TODO: add checks
}

if ($savemsg) {
	print_info_box($savemsg);
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
$tab_array[] = array($menu_iface . gettext("Barnyard2"), true, "/snort/snort_barnyard.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/snort/snort_ip_reputation.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
display_top_tabs($tab_array, true);

$form = new Form(new Form_Button(
	'save',
	'Save'
));

$section = new Form_Section('General Barnyard2 Settings');
$section->addInput(new Form_Checkbox(
	'barnyard_enable',
	'Enable Barnyard2',
	'Enable barnyard2 for this interface. You will also need to enable at least one logging destination below.',
	$pconfig['barnyard_enable'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'barnyard_show_year',
	'Show Year',
	'Enable the year being shown in timestamps.  Default value is checked.',
	$pconfig['barnyard_show_year'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Select(
	'unified2_log_limit',
	'Unified2 Log Limit',
	$pconfig['unified2_log_limit'],
	$log_sizes
))->setHelp('Choose a Unified2 Log file size limit. Default is 128K. This sets the maximum size for a Unified2 Log file before it is rotated and a new one created.');
$section->addInput(new Form_Checkbox(
	'barnyard_archive_enable',
	'Archive Unified2 Logs',
	'Enable the archiving of processed unified2 log files.  Default value is checked.',
	$pconfig['barnyard_archive_enable'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Select(
	'u2_archived_log_retention',
	'Unified2 Archived Log Retention Period',
	$pconfig['u2_archived_log_retention'],
	$retentions
))->setHelp('Choose retention period for archived Barnyard2 binary log files. Default is 7 days.  When finished processing a file, Barnyard2 moves it to an archive folder.  This setting determines how long files remain in the archive folder before they are automatically deleted.');
$section->addInput(new Form_Checkbox(
	'barnyard_dump_payload',
	'Dump Payload',
	'Enable dumping of application data from unified2 files.  Default value is Not Checked.',
	$pconfig['barnyard_dump_payload'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'barnyard_obfuscate_ip',
	'Obfuscate IP Addresses',
	'Enable obfuscation of logged IP addresses.  Default value is Not Checked.',
	$pconfig['barnyard_obfuscate_ip'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'barnyard_log_vlan_events',
	'Log VLAN Events',
	'Enable logging of VLAN event types in unified2 files.  Default value is Not Checked.',
	$pconfig['barnyard_log_vlan_events'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'barnyard_log_mpls_events',
	'Log MPLS Events',
	'Enable logging of MPLS event types in unified2 files.  Default value is Not Checked.',
	$pconfig['barnyard_log_mpls_events'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Input(
	'barnyard_sensor_name',
	'Sensor Name',
	'text',
	$pconfig['barnyard_sensor_name']
))->setHelp('Unique name for this sensor.  Leave blank to use internal default.');
$form->add($section);

$section = new Form_Section('MySQL Database Output Settings');
$section->addInput(new Form_Checkbox(
	'barnyard_mysql_enable',
	'Enable MySQL Database',
	'Enable logging of alerts to a MySQL database instance. You will also have to provide the database credentials in the fields below.',
	$pconfig['barnyard_mysql_enable'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Input(
	'barnyard_dbhost',
	'Database Host',
	'text',
	$pconfig['barnyard_dbhost']
))->setHelp('Hostname or IP address of the MySQL database server.');
$section->addInput(new Form_Input(
	'barnyard_dbname',
	'Database Name',
	'text',
	$pconfig['barnyard_dbname']
))->setHelp('Instance or DB name of the MySQL database.');
$section->addInput(new Form_Input(
	'barnyard_dbuser',
	'Database User Name',
	'text',
	$pconfig['barnyard_dbuser']
))->setHelp('Username for the MySQL database.');
$section->addPassword(new Form_Input(
	'barnyard_dbpwd',
	'Database User Password',
	'text',
	$pconfig['barnyard_dbpwd']
))->setHelp('Password for the MySQL database user.');
$section->addInput(new Form_Checkbox(
	'barnyard_disable_sig_ref_tbl',
	'Disable Signature Reference Table',
	'Disable synchronization of sig_reference table in schema.  Default value is Not Checked.  This option will speedup the process when checked, plus it can help work around a "duplicate entry" error when running multiple Snort instances.',
	$pconfig['barnyard_disable_sig_ref_tbl'] == 'on' ? true:false,
	'on'
));
$form->add($section);

$section = new Form_Section('Syslog Output Settings');
$section->addInput(new Form_Checkbox(
	'barnyard_syslog_enable',
	'Enable Syslog',
	'Enable logging of alerts to a local or remote syslog receiver.',
	$pconfig['barnyard_syslog_enable'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'barnyard_syslog_local',
	'Local Only',
	'Enable logging of alerts to the local system only. This will send alert data (without payload) to the local system using the facility and priority values selected below.',
	$pconfig['barnyard_syslog_local'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Select(
	'barnyard_syslog_opmode',
	'Operation Mode',
	$pconfig['barnyard_syslog_opmode'],
	array( "default" => "DEFAULT", "complete" => "COMPLETE" )
))->setHelp('Select the level of detail to include when reporting. DEFAULT mode is compatible with the standard Snort syslog format. COMPLETE mode includes additional information such as the raw packet data.');
$section->addInput(new Form_Select(
	'barnyard_syslog_payload_encoding',
	'Payload Encoding',
	$pconfig['barnyard_syslog_payload_encoding'],
	array( "hex" => "Hex", "ascii" => "ASCII", "base64" => "Base64" )
))->setHelp('Select the encoding method to use for logging raw packet data.');
$section->addInput(new Form_Input(
	'barnyard_syslog_rhost',
	'Remote Host',
	'text',
	$pconfig['barnyard_syslog_rhost']
))->setHelp('Hostname or IP address of remote syslog host');
$section->addInput(new Form_Input(
	'barnyard_syslog_dport',
	'Remote Port',
	'text',
	$pconfig['barnyard_syslog_dport']
))->setHelp('Port number for syslog on remote host. Default is 514.');
$section->addInput(new Form_Select(
	'barnyard_syslog_proto',
	'Protocol',
	$pconfig['barnyard_syslog_proto'],
	array( "udp" => "UDP", "tcp" => "TCP" )
))->setHelp('Select IP protocol to use for remote reporting. Default is UDP.');
$section->addInput(new Form_Select(
	'barnyard_syslog_facility',
	'Log Facility',
	$pconfig['barnyard_syslog_facility'],
	array(  "LOG_AUTH" => "LOG_AUTH", "LOG_AUTHPRIV" => "LOG_AUTHPRIV", "LOG_DAEMON" => "LOG_DAEMON", "LOG_KERN" => "LOG_KERN", "LOG_SYSLOG" => "LOG_SYSLOG", "LOG_USER" => "LOG_USER", "LOG_LOCAL0" => "LOG_LOCAL0", "LOG_LOCAL1" => "LOG_LOCAL1", "LOG_LOCAL2" => "LOG_LOCAL2", "LOG_LOCAL3" => "LOG_LOCAL3", "LOG_LOCAL4" => "LOG_LOCAL4", "LOG_LOCAL5" => "LOG_LOCAL5", "LOG_LOCAL6" => "LOG_LOCAL6", "LOG_LOCAL7" => "LOG_LOCAL7" )
))->setHelp('Select Syslog Facility to use for reporting. Default is LOG_LOCAL1.');
$section->addInput(new Form_Select(
	'barnyard_syslog_priority',
	'Log Priority',
	$pconfig['barnyard_syslog_priority'],
	array( "LOG_EMERG" => "LOG_EMERG", "LOG_CRIT" => "LOG_CRIT", "LOG_ALERT" => "LOG_ALERT", "LOG_ERR" => "LOG_ERR", "LOG_WARNING" => "LOG_WARNING", "LOG_NOTICE" => "LOG_NOTICE", "LOG_INFO" => "LOG_INFO" )
))->setHelp('Select Syslog Priority (Level) to use for reporting. Default is LOG_INFO.');
$form->add($section);

$section = new Form_Section('Bro-IDS Output Settings');
$section->addInput(new Form_Checkbox(
	'barnyard_bro_ids_enable',
	'Enable Bro-IDS',
	'Enable logging of alerts to a local or remote Bro-IDS receiver.',
	$pconfig['barnyard_bro_ids_enable'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Input(
	'barnyard_bro_ids_rhost',
	'Remote Host',
	'text',
	$pconfig['barnyard_bro_ids_rhost']
))->setHelp('Hostname or IP address of remote Bro-IDS host');
$section->addInput(new Form_Input(
	'barnyard_bro_ids_dport',
	'Remote Port',
	'text',
	$pconfig['barnyard_bro_ids_dport']
))->setHelp('Port number for Bro-IDS instance on remote host. Default is 47760.');
$form->add($section);

$section = new Form_Section('Advanced Settings');
$section->addInput(new Form_Textarea (
	'barnconfigpassthru',
	'Advanced Configuration Pass-Through',
	$pconfig['barnconfigpassthru']
))->setHelp('Arguments entered here will be automatically inserted into the running barnyard2 configuration.');
$form->add($section);

print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

	function toggle_mySQL() {
		var hide = ! $('#barnyard_mysql_enable').prop('checked');
		hideInput('barnyard_dbhost', hide);
		hideInput('barnyard_dbname', hide);
		hideInput('barnyard_dbuser', hide);
		hideInput('barnyard_dbpwd', hide);
		hideCheckbox('barnyard_disable_sig_ref_tbl', hide);
	}

	function toggle_syslog() {
		var hide = ! $('#barnyard_syslog_enable').prop('checked');
		if (hide) {
			hideCheckbox('barnyard_syslog_local', hide);
			hideSelect('barnyard_syslog_opmode', hide);
			hideSelect('barnyard_syslog_payload_encoding', hide);
			hideInput('barnyard_syslog_rhost', hide);
			hideInput('barnyard_syslog_dport', hide);
			hideSelect('barnyard_syslog_proto', hide);
			hideSelect('barnyard_syslog_facility', hide);
			hideSelect('barnyard_syslog_priority', hide);
		}
		else {
			hideCheckbox('barnyard_syslog_local', hide);
			hideSelect('barnyard_syslog_facility', hide);
			hideSelect('barnyard_syslog_priority', hide);
			toggle_local_syslog();
		}
	}

	function toggle_local_syslog() {
		var hide = $('#barnyard_syslog_local').prop('checked');
		hideSelect('barnyard_syslog_opmode', hide);
		hideSelect('barnyard_syslog_payload_encoding', hide);
		hideInput('barnyard_syslog_rhost', hide);
		hideInput('barnyard_syslog_dport', hide);
		hideSelect('barnyard_syslog_proto', hide);
	}

	function toggle_bro_ids() {
		var hide = ! $('#barnyard_bro_ids_enable').prop('checked');
		hideInput('barnyard_bro_ids_rhost', hide);
		hideInput('barnyard_bro_ids_dport', hide);
	}

	function enable_change() {
		var hide = ! $('#barnyard_enable').prop('checked');
		disableInput('barnyard_archive_enable', hide);
		disableInput('barnyard_show_year', hide);
		disableInput('barnyard_dump_payload', hide);
		disableInput('barnyard_obfuscate_ip', hide);
		disableInput('barnyard_sensor_id', hide);
		disableInput('barnyard_sensor_name', hide);
		disableInput('barnyard_log_vlan_events', hide);
		disableInput('barnyard_log_mpls_events', hide);
		disableInput('barnyard_mysql_enable', hide);
		disableInput('barnyard_dbhost', hide);
		disableInput('barnyard_dbname', hide);
		disableInput('barnyard_dbuser', hide);
		disableInput('barnyard_dbpwd', hide);
		disableInput('barnyard_disable_sig_ref_tbl', hide);
		disableInput('barnyard_syslog_enable', hide);
		disableInput('barnyard_syslog_local', hide);
		disableInput('barnyard_syslog_rhost', hide);
		disableInput('barnyard_syslog_dport', hide);
		disableInput('barnyard_bro_ids_enable', hide);
		disableInput('barnyard_bro_ids_rhost', hide);
		disableInput('barnyard_bro_ids_dport', hide);
		disableInput('barnconfigpassthru', hide);
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------
	
	/* When form control id is clicked, disable/enable it's associated form controls */
	
	$('#barnyard_enable').click(function() {
		enable_change();
	});

	$('#barnyard_mysql_enable').click(function() {
		toggle_mySQL();
	});
	
	$('#barnyard_syslog_enable').click(function() {
		toggle_syslog();
	});

	$('#barnyard_syslog_local').click(function() {
		toggle_local_syslog();
	});

	$('#barnyard_bro_ids_enable').click(function() {
		toggle_bro_ids();
	});

	// ---------- On initial page load ------------------------------------------------------------
	enable_change();
	
	toggle_mySQL();
	toggle_syslog();
	toggle_bro_ids();
	
});

//]]>
</script>
<?php include("foot.inc"); ?>

