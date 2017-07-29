<?php
/*
 * suricata_barnyard.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2014 Bill Meeks
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

global $g, $rebuild_rules;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
		header("Location: /suricata/suricata_interfaces.php");
		exit;
}

if (!is_array($config['installedpackages']['suricata']['rule']))
	$config['installedpackages']['suricata']['rule'] = array();
$a_nat = &$config['installedpackages']['suricata']['rule'];

$pconfig = array();

if (isset($id) && $a_nat[$id]) {
	$pconfig = $a_nat[$id];
	if (!empty($a_nat[$id]['barnconfigpassthru']))
		$pconfig['barnconfigpassthru'] = base64_decode($a_nat[$id]['barnconfigpassthru']);
	if (!empty($a_nat[$id]['barnyard_dbpwd']))
		$pconfig['barnyard_dbpwd'] = base64_decode($a_nat[$id]['barnyard_dbpwd']);
	if (empty($a_nat[$id]['barnyard_show_year']))
		$pconfig['barnyard_show_year'] = "on";
	if (empty($a_nat[$id]['barnyard_archive_enable']))
		$pconfig['barnyard_archive_enable'] = "on";
	if (empty($a_nat[$id]['barnyard_obfuscate_ip']))
		$pconfig['barnyard_obfuscate_ip'] = "off";
	if (empty($a_nat[$id]['barnyard_syslog_dport']))
		$pconfig['barnyard_syslog_dport'] = "514";
	if (empty($a_nat[$id]['barnyard_syslog_proto']))
		$pconfig['barnyard_syslog_proto'] = "udp";
	if (empty($a_nat[$id]['barnyard_syslog_opmode']))
		$pconfig['barnyard_syslog_opmode'] = "default";
	if (empty($a_nat[$id]['barnyard_syslog_facility']))
		$pconfig['barnyard_syslog_facility'] = "LOG_LOCAL1";
	if (empty($a_nat[$id]['barnyard_syslog_priority']))
		$pconfig['barnyard_syslog_priority'] = "LOG_INFO";
	if (empty($a_nat[$id]['barnyard_bro_ids_dport']))
		$pconfig['barnyard_bro_ids_dport'] = "47760";
	if (empty($a_nat[$id]['barnyard_sensor_id']))
		$pconfig['barnyard_sensor_id'] = "0";
	if (empty($pconfig['barnyard_xff_logging']))
		$pconfig['barnyard_xff_logging'] = "off";
	if (empty($pconfig['barnyard_xff_mode']))
		$pconfig['barnyard_xff_mode'] = "extra-data";
	if (empty($pconfig['barnyard_xff_deployment']))
		$pconfig['barnyard_xff_deployment'] = "reverse";
	if (empty($pconfig['barnyard_xff_header']))
		$pconfig['barnyard_xff_header'] = "X-Forwarded-For";
}

if ($_POST['save']) {

	// If disabling Barnyard2 on the interface, stop any
	// currently running instance, then save the disabled
	// state and exit so as to preserve settings.
	if ($_POST['barnyard_enable'] != 'on') {
		$a_nat[$id]['barnyard_enable'] = 'off';
		write_config("Suricata pkg: modified Barnyard2 settings.");
		suricata_barnyard_stop($a_nat[$id], get_real_interface($a_nat[$id]['interface']));

		// No need to rebuild rules for Barnyard2 changes
		$rebuild_rules = false;
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /suricata/suricata_barnyard.php");
		exit;
	}

	// Check that at least one output plugin is enabled
	if ($_POST['barnyard_mysql_enable'] != 'on' && $_POST['barnyard_syslog_enable'] != 'on' &&
		$_POST['barnyard_bro_ids_enable'] != 'on' && $_POST['barnyard_enable'] == "on")
		$input_errors[] = gettext("You must enable at least one output option when using Barnyard2.");

	// Validate Sensor Name contains no spaces
	if ($_POST['barnyard_enable'] == 'on') {
		if (!empty($_POST['barnyard_sensor_name']) && strpos($_POST['barnyard_sensor_name'], " ") !== FALSE)
			$input_errors[] = gettext("The value for 'Sensor Name' cannot contain spaces.");
	}

	// Validate Sensor ID is a valid integer
	if ($_POST['barnyard_enable'] == 'on') {
		if (!is_numericint($_POST['barnyard_sensor_id']) || $_POST['barnyard_sensor_id'] < 0)
			$input_errors[] = gettext("The value for 'Sensor ID' must be a valid positive integer.");
	}

	if (empty($_POST['barnyard_xff_header']) && $_POST['barnyard_xff_logging'] == "on")
		$input_errors[] = gettext("The value for the X-Forwarded-For Header cannot be blank when X-Forwarded-For logging is enabled.");

	// Validate inputs if MySQL database loggging enabled
	if ($_POST['barnyard_mysql_enable'] == 'on' && $_POST['barnyard_enable'] == "on") {
		if (empty($_POST['barnyard_dbhost']))
			$input_errors[] = gettext("Please provide a valid hostname or IP address for the MySQL database host.");
		if (empty($_POST['barnyard_dbname']))
			$input_errors[] = gettext("You must provide a DB instance name when logging to a MySQL database.");
		if (empty($_POST['barnyard_dbuser']))
			$input_errors[] = gettext("You must provide a DB user login name when logging to a MySQL database.");
	}

	// Validate inputs if syslog output enabled
	if ($_POST['barnyard_syslog_enable'] == 'on' && $_POST['barnyard_syslog_local'] != 'on' &&
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
		$natent['barnyard_mysql_enable'] = $_POST['barnyard_mysql_enable'] ? 'on' : 'off';
		$natent['barnyard_syslog_enable'] = $_POST['barnyard_syslog_enable'] ? 'on' : 'off';
		$natent['barnyard_syslog_local'] = $_POST['barnyard_syslog_local'] ? 'on' : 'off';
		$natent['barnyard_bro_ids_enable'] = $_POST['barnyard_bro_ids_enable'] ? 'on' : 'off';
		$natent['barnyard_disable_sig_ref_tbl'] = $_POST['barnyard_disable_sig_ref_tbl'] ? 'on' : 'off';
		$natent['barnyard_xff_logging'] = $_POST['barnyard_xff_logging'] ? 'on' : 'off';
		$natent['barnyard_syslog_opmode'] = $_POST['barnyard_syslog_opmode'];
		$natent['barnyard_syslog_proto'] = $_POST['barnyard_syslog_proto'];

		if ($_POST['barnyard_sensor_id']) $natent['barnyard_sensor_id'] = $_POST['barnyard_sensor_id']; else $natent['barnyard_sensor_id'] = '0';
		if ($_POST['barnyard_sensor_name']) $natent['barnyard_sensor_name'] = $_POST['barnyard_sensor_name']; else unset($natent['barnyard_sensor_name']);
		if ($_POST['barnyard_xff_header']) $natent['barnyard_xff_header'] = $_POST['barnyard_xff_header']; else $natent['barnyard_xff_header'] = 'X-Forwarded-For';
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
		write_config("Suricata pkg: modified Barnyard2 settings.");

		// No need to rebuild rules for Barnyard2 changes
		$rebuild_rules = false;
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();

		// If disabling Barnyard2 on the interface, stop any
		// currently running instance.  If an instance is
		// running, signal it to reload the configuration.
		// If Barnyard2 is enabled but not running, start it.
		if ($a_nat[$id]['barnyard_enable'] == "off") {
			suricata_barnyard_stop($a_nat[$id], get_real_interface($a_nat[$id]['interface']));
		}
		elseif ($a_nat[$id]['barnyard_enable'] == "on") {
			if (suricata_is_running($a_nat[$id]['uuid'], get_real_interface($a_nat[$id]['interface']), "barnyard2"))
				suricata_barnyard_reload_config($a_nat[$id], "HUP");
			else {
				// Notify user a Suricata restart is required if enabling Barnyard2 for the first time
				$savemsg = gettext("NOTE: you must restart Suricata on this interface to activate unified2 logging for Barnyard2.");
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
$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Interface Barnyard2 Settings - {$if_friendly}"));
include_once("head.inc");

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), true, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php?instance={$id}");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$id}");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

$tab_array = array();
$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
$tab_array[] = array($menu_iface . gettext("Settings"), false, "/suricata/suricata_interfaces_edit.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Categories"), false, "/suricata/suricata_rulesets.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Rules"), false, "/suricata/suricata_rules.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Flow/Stream"), false, "/suricata/suricata_flow_stream.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("App Parsers"), false, "/suricata/suricata_app_parsers.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Variables"), false, "/suricata/suricata_define_vars.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Barnyard2"), true, "/suricata/suricata_barnyard.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/suricata/suricata_ip_reputation.php?id={$id}");
display_top_tabs($tab_array, true);

$form = new Form();

$form->addGlobal(new Form_Input(
	'id',
	'id',
	'hidden',
	$id
));

$section = new Form_Section('General Barnyard2 Settings');
$section->addInput(new Form_Checkbox(
	'barnyard_enable',
	'Enable',
	'Enable Barnyard2. This will enable barnyard2 for this interface. You will also to enable at least one logging destination below.',
	$pconfig['barnyard_enable'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'barnyard_show_year',
	'Show Year',
	'Enable the year being shown in timestamps. Default value is Checked',
	$pconfig['barnyard_show_year'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'barnyard_archive_enable',
	'Archive Unified2 Logs',
	'Enable the archiving of processed unified2 log files. Default value is Checked. Unified2 log files will be moved to an archive folder for subsequent cleanup when processed.',
	$pconfig['barnyard_archive_enable'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'barnyard_dump_payload',
	'Dump Payload',
	'Enable dumping of application data from unified2 files. Default value is Not Checked',
	$pconfig['barnyard_dump_payload'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'barnyard_obfuscate_ip',
	'Obfuscate IP Addresses',
	'Enable obfuscation of logged IP addresses. Default value is Not Checked',
	$pconfig['barnyard_obfuscate_ip'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Input(
	'barnyard_sensor_id',
	'Sensor ID',
	'text',
	$pconfig['barnyard_sensor_id']
))->setHelp('Sensor ID to use for this sensor. Default is 0');
$section->addInput(new Form_Input(
	'barnyard_sensor_name',
	'Sensor Name',
	'text',
	$pconfig['barnyard_sensor_name']
))->setHelp('Unique name to use for this sensor. (Optional).');
$section->addInput(new Form_Checkbox(
	'barnyard_xff_logging',
	'X-Forwarded-For Logging',
	'Enable logging of X-Forwarded-For IP addresses. Default value is Not Checked',
	$pconfig['barnyard_xff_logging'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Select(
	'barnyard_xff_mode',
	'X-Forwarded-For Mode',
	$pconfig['barnyard_xff_mode'],
	array( "extra-data" => "extra-data", "overwrite" => "overwrite" )
))->setHelp('Select HTTP X-Forwarded-For Operation Mode. Default is extra-data.');
$section->addInput(new Form_Select(
	'barnyard_xff_deployment',
	'X-Forwarded-For Deployment',
	$pconfig['barnyard_xff_deployment'],
	array( "reverse" => "reverse", "forward" => "forward" )
))->setHelp('Select HTTP X-Forwarded-For Deployment. Default is reverse.');
$section->addInput(new Form_Input(
	'barnyard_xff_header',
	'X-Forwarded-For Header',
	'text',
	$pconfig['barnyard_xff_header']
))->setHelp('Enter header where actual IP address is reported. Default is X-Forwarded-For. If more than one IP address is present, the last one will be used.');
$form->add($section);

$section = new Form_Section('MySQL Database Output Settings');
$section->addInput(new Form_Checkbox(
	'barnyard_mysql_enable',
	'Enable MySQL Database',
	'Enable logging of alerts to a MySQL database instance.',
	$pconfig['barnyard_mysql_enable'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Input(
	'barnyard_dbhost',
	'Database Host',
	'text',
	$pconfig['barnyard_dbhost']
))->setHelp('Hostname or IP address of the MySQL database server');
$section->addInput(new Form_Input(
	'barnyard_dbname',
	'Database Name',
	'text',
	$pconfig['barnyard_dbname']
))->setHelp('Instance or DB name of the MySQL database');
$section->addInput(new Form_Input(
	'barnyard_dbuser',
	'Database User Name',
	'text',
	$pconfig['barnyard_dbuser']
))->setHelp('Username for the MySQL database');
$section->addPassword(new Form_Input(
	'barnyard_dbpwd',
	'Database User Password',
	'text',
	$pconfig['barnyard_dbpwd']
))->setHelp('Password for the MySQL database user');
$section->addInput(new Form_Checkbox(
	'barnyard_disable_sig_ref_tbl',
	'Disable Signature Reference Table',
	'Disable synchronization of sig_reference table in schema. Default value is Not Checked. This option will speedup the process when checked, plus it can help work around a "duplicate entry" error when running multiple Suricata instances.',
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

$section->addInput(new Form_Select(
	'barnyard_syslog_opmode',
	'Operation Mode',
	$pconfig['barnyard_syslog_opmode'],
	array( "default" => "DEFAULT", "complete" => "COMPLETE" )
))->setHelp('Select the level of detail to include when reporting. DEFAULT mode is compatible with the standard Snort syslog format. COMPLETE mode includes additional information such as the raw packet data (displayed in hex format).');
$section->addInput(new Form_Checkbox(
	'barnyard_syslog_local',
	'Local Only',
	'Enable logging of alerts to the local system only. This will send alert data to the local system only and overrides the host, port and protocol values below.',
	$pconfig['barnyard_syslog_local'] == 'on' ? true:false,
	'on'
));
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
))->setHelp('Select Syslog Facility to use for remote reporting. Default is LOG_LOCAL1.');
$section->addInput(new Form_Select(
	'barnyard_syslog_priority',
	'Log Priority',
	$pconfig['barnyard_syslog_priority'],
	array( "LOG_EMERG" => "LOG_EMERG", "LOG_CRIT" => "LOG_CRIT", "LOG_ALERT" => "LOG_ALERT", "LOG_ERR" => "LOG_ERR", "LOG_WARNING" => "LOG_WARNING", "LOG_NOTICE" => "LOG_NOTICE", "LOG_INFO" => "LOG_INFO" )
))->setHelp('Select Syslog Priority (Level) to use for remote reporting. Default is LOG_INFO.');
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

<div class="infoblock">
	<?=print_info_box('<strong>Note:</strong> Please save your settings before you click start.', info)?>
</div>

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
		hideSelect('barnyard_syslog_opmode', hide);
		hideCheckbox('barnyard_syslog_local', hide);
		hideInput('barnyard_syslog_rhost', hide);
		hideInput('barnyard_syslog_dport', hide);
		hideSelect('barnyard_syslog_proto', hide);
		hideSelect('barnyard_syslog_facility', hide);
		hideSelect('barnyard_syslog_priority', hide);
	}

	function toggle_local_syslog() {
		var hide = $('#barnyard_syslog_local').prop('checked');
		disableInput('barnyard_syslog_rhost', hide);
		disableInput('barnyard_syslog_dport', hide);
		disableInput('barnyard_syslog_proto', hide);
	}

	function toggle_bro_ids() {
		var hide = ! $('#barnyard_bro_ids_enable').prop('checked');
		hideInput('barnyard_bro_ids_rhost', hide);
		hideInput('barnyard_bro_ids_dport', hide);
	}

	function toggle_xff_log_options() {
		var hide = ! $('#barnyard_xff_logging').prop('checked');
		hideSelect('barnyard_xff_mode', hide);
		hideSelect('barnyard_xff_deployment', hide);
		hideInput('barnyard_xff_header', hide);
	}

	function enable_change() {
		var hide = ! $('#barnyard_enable').prop('checked');
		disableInput('barnyard_archive_enable', hide);
		disableInput('barnyard_show_year', hide);
		disableInput('barnyard_dump_payload', hide);
		disableInput('barnyard_obfuscate_ip', hide);
		disableInput('barnyard_sensor_id', hide);
		disableInput('barnyard_sensor_name', hide);
		disableInput('barnyard_xff_logging', hide);
		disableInput('barnyard_xff_mode', hide);
		disableInput('barnyard_xff_deployment', hide);
		disableInput('barnyard_xff_header', hide);
		disableInput('barnyard_mysql_enable', hide);
		disableInput('barnyard_dbhost', hide);
		disableInput('barnyard_dbname', hide);
		disableInput('barnyard_dbuser', hide);
		disableInput('barnyard_dbpwd', hide);
		disableInput('barnyard_disable_sig_ref_tbl', hide);
		disableInput('barnyard_syslog_enable', hide);
		disableInput('barnyard_syslog_opmode', hide);
		disableInput('barnyard_syslog_local', hide);
		disableInput('barnyard_syslog_rhost', hide);
		disableInput('barnyard_syslog_dport', hide);
		disableInput('barnyard_syslog_proto', hide);
		disableInput('barnyard_syslog_facility', hide);
		disableInput('barnyard_syslog_priority', hide);
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

	$('#barnyard_xff_logging').click(function() {
		toggle_xff_log_options();
	});

	// ---------- On initial page load ------------------------------------------------------------
	enable_change();

	toggle_mySQL();
	toggle_syslog();
	toggle_local_syslog();
	toggle_bro_ids();
	toggle_xff_log_options();

});
//]]>
</script>

<?php include("foot.inc"); ?>
