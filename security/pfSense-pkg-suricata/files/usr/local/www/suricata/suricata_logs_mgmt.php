<?php
/*
 * suricata_logs_mgmt.php
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

$suricatadir = SURICATADIR;

$pconfig = array();

// Grab saved settings from configuration
$pconfig['enable_log_mgmt'] = config_get_path('installedpackages/suricata/config/0/enable_log_mgmt') == 'off' ? 'off' : 'on';
$pconfig['clearlogs'] = config_get_path('installedpackages/suricata/config/0/clearlogs') == 'on' ? 'on' : 'off';
$pconfig['suricataloglimit'] = config_get_path('installedpackages/suricata/config/0/suricataloglimit') == 'on' ? 'on' : 'off';
$pconfig['suricataloglimitsize'] = htmlentities(config_get_path('installedpackages/suricata/config/0/suricataloglimitsize'));
$pconfig['alert_log_limit_size'] = config_get_path('installedpackages/suricata/config/0/alert_log_limit_size', 500);
$pconfig['alert_log_retention'] = config_get_path('installedpackages/suricata/config/0/alert_log_retention', 336);
$pconfig['block_log_limit_size'] = config_get_path('installedpackages/suricata/config/0/block_log_limit_size', 500);
$pconfig['block_log_retention'] = config_get_path('installedpackages/suricata/config/0/block_log_retention', 336);
$pconfig['http_log_limit_size'] = config_get_path('installedpackages/suricata/config/0/http_log_limit_size', 1000);
$pconfig['http_log_retention'] = config_get_path('installedpackages/suricata/config/0/http_log_retention', 168);
$pconfig['stats_log_limit_size'] = config_get_path('installedpackages/suricata/config/0/stats_log_limit_size', 500);
$pconfig['stats_log_retention'] = config_get_path('installedpackages/suricata/config/0/stats_log_retention', 168);
$pconfig['tls_log_limit_size'] = config_get_path('installedpackages/suricata/config/0/tls_log_limit_size', 500);
$pconfig['tls_log_retention'] = config_get_path('installedpackages/suricata/config/0/tls_log_retention', 336);
$pconfig['file_store_retention'] = config_get_path('installedpackages/suricata/config/0/file_store_retention', 168);
$pconfig['file_store_limit_size'] = config_get_path('installedpackages/suricata/config/0/file_store_limit_size');
$pconfig['tls_certs_store_retention'] = config_get_path('installedpackages/suricata/config/0/tls_certs_store_retention', 168);
$pconfig['eve_log_limit_size'] = config_get_path('installedpackages/suricata/config/0/eve_log_limit_size', 5000);
$pconfig['eve_log_retention'] = config_get_path('installedpackages/suricata/config/0/eve_log_retention', 168);
$pconfig['sid_changes_log_limit_size'] = config_get_path('installedpackages/suricata/config/0/sid_changes_log_limit_size', 250);
$pconfig['sid_changes_log_retention'] = config_get_path('installedpackages/suricata/config/0/sid_changes_log_retention', 336);
$pconfig['pkt_capture_file_retention'] = config_get_path('installedpackages/suricata/config/0/pkt_capture_file_retention', 168);

// Load up some arrays with selection values (we use these later).
// The keys in the $retentions array are the retention period
// converted to hours.  The keys in the $log_sizes array are
// the file size limits in KB.
$retentions = array( '0' => gettext('KEEP ALL'), '24' => gettext('1 DAY'), '168' => gettext('7 DAYS'), '336' => gettext('14 DAYS'),
			 '720' => gettext('30 DAYS'), '1080' => gettext("45 DAYS"), '2160' => gettext('90 DAYS'), '4320' => gettext('180 DAYS'),
			 '8766' => gettext('1 YEAR'), '26298' => gettext("3 YEARS") );
$log_sizes = array( '0' => gettext('NO LIMIT'), '50' => gettext('50 KB'), '150' => gettext('150 KB'), '250' => gettext('250 KB'),
			'500' => gettext('500 KB'), '750' => gettext('750 KB'), '1000' => gettext('1 MB'), '2000' => gettext('2 MB'),
			'5000' => gettext("5 MB"), '10000' => gettext("10 MB") );

// Set sensible default for Suricata logging directory size limit
if (empty($pconfig['suricataloglimitsize'])) {
	// Set limit to 20% of slice that is unused */
	$pconfig['suricataloglimitsize'] = round(exec('df -k /var | grep -v "Filesystem" | awk \'{print $4}\'') * .20 / 1024);
}

// Set a default file store size limit
if (!isset($pconfig['file_store_limit_size']))
	$pconfig['file_store_limit_size'] = intval($pconfig['suricataloglimitsize'] * 0.60);

if (isset($_POST['ResetAll'])) {

	// Reset all settings to their defaults
	$pconfig['alert_log_retention'] = "336";
	$pconfig['block_log_retention'] = "336";
	$pconfig['http_log_retention'] = "168";
	$pconfig['stats_log_retention'] = "168";
	$pconfig['tls_log_retention'] = "336";
	$pconfig['file_store_retention'] = "168";
	$pconfig['tls_certs_store_retention'] = "168";
	$pconfig['eve_log_retention'] = "168";
	$pconfig['sid_changes_log_retention'] = "336";
	$pconfig['pkt_capture_file_retention'] = "168";

	$pconfig['alert_log_limit_size'] = "500";
	$pconfig['block_log_limit_size'] = "500";
	$pconfig['http_log_limit_size'] = "1000";
	$pconfig['stats_log_limit_size'] = "500";
	$pconfig['tls_log_limit_size'] = "500";
	$pconfig['eve_log_limit_size'] = "5000";
	$pconfig['sid_changes_log_limit_size'] = "250";
	$pconfig['file_store_limit_size'] = intval($pconfig['suricataloglimitsize'] * 0.60);

	/* Log a message at the top of the page to inform the user */
	$savemsg = gettext("All log management settings on this page have been reset to their defaults.  Click APPLY if you wish to keep these new settings.");
}

if (isset($_POST['save']) || isset($_POST['apply'])) {
	if ($_POST['enable_log_mgmt'] != 'on') {
		config_set_path('installedpackages/suricata/config/0/enable_log_mgmt', $_POST['enable_log_mgmt'] ? 'on' :'off');
		write_config("Suricata pkg: saved updated configuration for LOGS MGMT.");
		sync_suricata_package_config();

		/* forces page to reload new settings */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /suricata/suricata_logs_mgmt.php");
		exit;
	}

	if ($_POST['suricataloglimit'] == 'on') {
		if (!is_numericint($_POST['suricataloglimitsize']) || $_POST['suricataloglimitsize'] < 1)
			$input_errors[] = gettext("The 'Log Directory Size Limit' must be an integer value greater than zero.");
	}

	if (!$input_errors) {
		config_set_path('installedpackages/suricata/config/0/enable_log_mgmt', $_POST['enable_log_mgmt'] ? 'on' :'off');
		config_set_path('installedpackages/suricata/config/0/clearlogs', $_POST['clearlogs'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/suricataloglimit', $_POST['suricataloglimit'] ? 'on' :'off');
		config_set_path('installedpackages/suricata/config/0/suricataloglimitsize', html_entity_decode($_POST['suricataloglimitsize']));
		config_set_path('installedpackages/suricata/config/0/alert_log_limit_size', $_POST['alert_log_limit_size']);
		config_set_path('installedpackages/suricata/config/0/alert_log_retention', $_POST['alert_log_retention']);
		config_set_path('installedpackages/suricata/config/0/block_log_limit_size', $_POST['block_log_limit_size']);
		config_set_path('installedpackages/suricata/config/0/block_log_retention', $_POST['block_log_retention']);
		config_set_path('installedpackages/suricata/config/0/http_log_limit_size', $_POST['http_log_limit_size']);
		config_set_path('installedpackages/suricata/config/0/http_log_retention', $_POST['http_log_retention']);
		config_set_path('installedpackages/suricata/config/0/stats_log_limit_size', $_POST['stats_log_limit_size']);
		config_set_path('installedpackages/suricata/config/0/stats_log_retention', $_POST['stats_log_retention']);
		config_set_path('installedpackages/suricata/config/0/tls_log_limit_size', $_POST['tls_log_limit_size']);
		config_set_path('installedpackages/suricata/config/0/tls_log_retention', $_POST['tls_log_retention']);
		config_set_path('installedpackages/suricata/config/0/file_store_retention', $_POST['file_store_retention']);
		config_set_path('installedpackages/suricata/config/0/file_store_limit_size', $_POST['file_store_limit_size']);
		config_set_path('installedpackages/suricata/config/0/tls_certs_store_retention', $_POST['tls_certs_store_retention']);
		config_set_path('installedpackages/suricata/config/0/eve_log_limit_size', $_POST['eve_log_limit_size']);
		config_set_path('installedpackages/suricata/config/0/eve_log_retention', $_POST['eve_log_retention']);
		config_set_path('installedpackages/suricata/config/0/sid_changes_log_limit_size', $_POST['sid_changes_log_limit_size']);
		config_set_path('installedpackages/suricata/config/0/sid_changes_log_retention', $_POST['sid_changes_log_retention']);
		config_set_path('installedpackages/suricata/config/0/pkt_capture_file_retention', $_POST['pkt_capture_file_retention']);

		write_config("Suricata pkg: saved updated configuration for LOGS MGMT.");
		sync_suricata_package_config();

		/* forces page to reload new settings */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /suricata/suricata_logs_mgmt.php");
		exit;
	}
}

$pglinks = array("", "/suricata/suricata_interfaces.php", "@self");
$pgtitle = array("Services", "Suricata", "Logs Management");
include_once("head.inc");

/* Display Alert message, under form tag or no refresh */
if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg) {
	/* Display save message */
	print_info_box($savemsg);
}

	$tab_array = array();
	$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
	$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
	$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
	$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php");
	$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
	$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php");
	$tab_array[] = array(gettext("Logs Mgmt"), true, "/suricata/suricata_logs_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
	$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
	display_top_tabs($tab_array, true);

$form = new Form;

$section = new Form_Section('General Settings');
$section->addInput(new Form_Checkbox(
	'enable_log_mgmt',
	'Auto Log Management',
	'Enable automatic unattended management of Suricata logs using parameters specified below.  Default is checked.',
	$pconfig['enable_log_mgmt'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'clearlogs',
	'Remove Suricata Logs On Package Uninstall',
	'Suricata log files will be removed when the Suricata package is uninstalled.  Default is not checked.',
	$pconfig['clearlogs'] == 'on' ? true:false,
	'on'
));
$form->add($section);

$section = new Form_Section("Log Directory Size Limit");
$section->addInput(new Form_Checkbox(
	'suricataloglimit',
	'Log Directory Size Limit',
	'Enable Directory Size Limit',
	$pconfig['suricataloglimit'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Input(
	'suricataloglimitsize',
	'Log Limit Size in MB',
	'text',
	$pconfig['suricataloglimitsize']
))->setHelp('This setting imposes a hard-limit on the combined log directory size of all Suricata interfaces. '.
			'When the size limit set is reached, rotated logs for all interfaces will be removed, and any active '.
			'logs pruned to zero-length.   (default is 20% of available free disk space)');
$form->add($section);

$section = new Form_Section("Log Size and Retention Limits");
$group = new Form_Group('alert');
$group->add(new Form_Select(
	'alert_log_limit_size',
	'Max Size',
	$pconfig['alert_log_limit_size'],
	$log_sizes
))->setHelp('Max Size. Default is 500 KB.');
$group->add(new Form_Select(
	'alert_log_retention',
	'Retention',
	$pconfig['alert_log_retention'],
	$retentions
))->setHelp('Retention. Default is 14 DAYS.');
$group->setHelp('Suricata alerts and event details');
$section->add($group);

$group = new Form_Group('block');
$group->add(new Form_Select(
	'block_log_limit_size',
	'Max Size',
	$pconfig['block_log_limit_size'],
	$log_sizes
))->setHelp('Max Size. Default is 500 KB.');
$group->add(new Form_Select(
	'block_log_retention',
	'Retention',
	$pconfig['block_log_retention'],
	$retentions
))->setHelp('Retention. Default is 14 DAYS.');
$group->setHelp('Suricata blocked IPs and event details');
$section->add($group);

$group = new Form_Group('eve-json');
$group->add(new Form_Select(
	'eve_log_limit_size',
	'Max Size',
	$pconfig['eve_log_limit_size'],
	$log_sizes
))->setHelp('Max Size. Default is 5 MB.');
$group->add(new Form_Select(
	'eve_log_retention',
	'Retention',
	$pconfig['eve_log_retention'],
	$retentions
))->setHelp('Retention. Default is 7 DAYS.');
$group->setHelp('Eve-JSON (JavaScript Object Notation) data');
$section->add($group);

$group = new Form_Group('http');
$group->add(new Form_Select(
	'http_log_limit_size',
	'Max Size',
	$pconfig['http_log_limit_size'],
	$log_sizes
))->setHelp('Max Size. Default is 1 MB.');
$group->add(new Form_Select(
	'http_log_retention',
	'Retention',
	$pconfig['http_log_retention'],
	$retentions
))->setHelp('Retention. Default is 7 DAYS.');
$group->setHelp('Captured HTTP events and session info');
$section->add($group);

$group = new Form_Group('sid_changes');
$group->add(new Form_Select(
	'sid_changes_log_limit_size',
	'Max Size',
	$pconfig['sid_changes_log_limit_size'],
	$log_sizes
))->setHelp('Max Size. Default is 250 KB.');
$group->add(new Form_Select(
	'sid_changes_log_retention',
	'Retention',
	$pconfig['sid_changes_log_retention'],
	$retentions
))->setHelp('Retention. Default is 14 DAYS.');
$group->setHelp('Log of SID changes made by SID Mgmt conf files');
$section->add($group);

$group = new Form_Group('stats');
$group->add(new Form_Select(
	'stats_log_limit_size',
	'Max Size',
	$pconfig['stats_log_limit_size'],
	$log_sizes
))->setHelp('Max Size. Default is 500 KB.');
$group->add(new Form_Select(
	'stats_log_retention',
	'Retention',
	$pconfig['stats_log_retention'],
	$retentions
))->setHelp('Retention. Default is 7 DAYS.');
$group->setHelp('Suricata performance statistics');
$section->add($group);

$group = new Form_Group('tls');
$group->add(new Form_Select(
	'tls_log_limit_size',
	'Max Size',
	$pconfig['tls_log_limit_size'],
	$log_sizes
))->setHelp('Max Size. Default is 500 KB.');
$group->add(new Form_Select(
	'tls_log_retention',
	'Retention',
	$pconfig['tls_log_retention'],
	$retentions
))->setHelp('Retention. Default is 14 DAYS.');
$group->setHelp('SMTP TLS handshake details');
$section->add($group);

$section->addInput(new Form_StaticText(
	'',
	'Settings will be ignored for any log in the list above not enabled on the Interface Settings tab. When a log reaches the Max Size limit, '.
	'it will be rotated and tagged with a timestamp. The Retention period determines how long rotated logs are kept before they are automatically deleted.'
));

$section->addInput(new Form_Input(
	'file_store_limit_size',
	'Captured Files Storage Limit',
	'text',
	$pconfig['file_store_limit_size']
))->setHelp('File Store captured files storage limit in megabytes (MB). Initial default value is 60% of the Log Directory Size Limit '.
			'parameter configured above. This sets the maximum storage limit (disk utilization) for captured files. '.
			'When this limit is reached, older files will purged to reduce disk consumption below the configured limit. '.
			'Entering zero disables this check and allows unlimited storage.');
$section->addInput(new Form_Select(
	'file_store_retention',
	'Captured Files Retention Period',
	$pconfig['file_store_retention'],
	$retentions
))->setHelp('Choose retention period for captured files in File Store. Default is 7 days. When file capture and store is enabled, '.
			'Suricata captures downloaded files from HTTP sessions and stores them, along with metadata, for later analysis. '.
			'This setting determines how long files remain in the File Store folder before they are automatically deleted.');
$section->addInput(new Form_Select(
	'tls_certs_store_retention',
	'Captured TLS Certs Retention Period',
	$pconfig['tls_certs_store_retention'],
	$retentions
))->setHelp('Choose retention period for captured TLS Certs. Default is 7 days. When custom rules with tls.store are enabled, Suricata captures Certificates, '.
			'along with metadata, for later analysis. This setting determines how long files remain in the Certs folder before they are automatically deleted.');
$section->addInput(new Form_Select(
	'pkt_capture_file_retention',
	'Packet Capture Files Retention Period',
	$pconfig['pkt_capture_file_retention'],
	$retentions
))->setHelp('Choose retention period for PCAP files. Default is 7 days. When Packet Capture is enabled, Suricata captures packets/flows in PCAP format. '.
			'This setting determines how long files remain in the "pcaps" sub-folder in the log directory of the interface before they are automatically deleted.');
$form->add($section);

print($form);

?>

<div class="infoblock">
	<?=print_info_box('<strong>Note:</strong> Changing any settings on this page will affect all Suricata-configured interfaces.', 'info')?>
</div>

<script language="JavaScript">
//<![CDATA[
events.push(function(){

	function enable_change() {
		var hide = ! $('#enable_log_mgmt').prop('checked');
		disableInput('alert_log_limit_size', hide);
		disableInput('alert_log_retention', hide);
		disableInput('block_log_limit_size', hide);
		disableInput('block_log_retention', hide);
		disableInput('http_log_limit_size', hide);
		disableInput('http_log_retention', hide);
		disableInput('stats_log_limit_size', hide);
		disableInput('stats_log_retention', hide);
		disableInput('tls_log_limit_size', hide);
		disableInput('tls_log_retention', hide);
		disableInput('eve_log_retention', hide);
		disableInput('eve_log_limit_size', hide);
		disableInput('sid_changes_log_retention', hide);
		disableInput('sid_changes_log_limit_size', hide);
		disableInput('file_store_retention', hide);
		disableInput('file_store_limit_size', hide);
		disableInput('tls_certs_store_retention', hide);
		disableInput('pkt_capture_file_retention', hide);
	}

	function enable_change_dirSize() {
		var hide = ! $('#suricataloglimit').prop('checked');
		disableInput('suricataloglimitsize', hide);
	}

	// ---------- Click checkbox handlers -------------------------------------------------------
	// When 'enable_log_mgmt' is clicked, disable/enable the other page form controls
	$('#enable_log_mgmt').click(function() {
		enable_change();
	});

	// When 'suricataloglimit_on' is clicked, disable/enable the other page form controls
	$('#suricataloglimit').click(function() {
		enable_change_dirSize();
	});

	enable_change();
	enable_change_dirSize();

});
//]]>
</script>

<?php include("foot.inc"); ?>

