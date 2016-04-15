<?php
/*
*  suricata_logs_mgmt.php
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

global $g;

$suricatadir = SURICATADIR;

$pconfig = array();

// Grab saved settings from configuration
$pconfig['enable_log_mgmt'] = $config['installedpackages']['suricata']['config'][0]['enable_log_mgmt'] == 'on' ? 'on' : 'off';
$pconfig['clearlogs'] = $config['installedpackages']['suricata']['config'][0]['clearlogs'] == 'on' ? 'on' : 'off';
$pconfig['suricataloglimit'] = $config['installedpackages']['suricata']['config'][0]['suricataloglimit'] == 'on' ? 'on' : 'off';
$pconfig['suricataloglimitsize'] = $config['installedpackages']['suricata']['config'][0]['suricataloglimitsize'];
$pconfig['alert_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['alert_log_limit_size'];
$pconfig['alert_log_retention'] = $config['installedpackages']['suricata']['config'][0]['alert_log_retention'];
$pconfig['block_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['block_log_limit_size'];
$pconfig['block_log_retention'] = $config['installedpackages']['suricata']['config'][0]['block_log_retention'];
$pconfig['files_json_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['files_json_log_limit_size'];
$pconfig['files_json_log_retention'] = $config['installedpackages']['suricata']['config'][0]['files_json_log_retention'];
$pconfig['http_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['http_log_limit_size'];
$pconfig['http_log_retention'] = $config['installedpackages']['suricata']['config'][0]['http_log_retention'];
$pconfig['stats_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['stats_log_limit_size'];
$pconfig['stats_log_retention'] = $config['installedpackages']['suricata']['config'][0]['stats_log_retention'];
$pconfig['tls_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['tls_log_limit_size'];
$pconfig['tls_log_retention'] = $config['installedpackages']['suricata']['config'][0]['tls_log_retention'];
$pconfig['unified2_log_limit'] = $config['installedpackages']['suricata']['config'][0]['unified2_log_limit'];
$pconfig['u2_archive_log_retention'] = $config['installedpackages']['suricata']['config'][0]['u2_archive_log_retention'];
$pconfig['file_store_retention'] = $config['installedpackages']['suricata']['config'][0]['file_store_retention'];
$pconfig['tls_certs_store_retention'] = $config['installedpackages']['suricata']['config'][0]['tls_certs_store_retention'];
$pconfig['dns_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['dns_log_limit_size'];
$pconfig['dns_log_retention'] = $config['installedpackages']['suricata']['config'][0]['dns_log_retention'];
$pconfig['eve_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['eve_log_limit_size'];
$pconfig['eve_log_retention'] = $config['installedpackages']['suricata']['config'][0]['eve_log_retention'];
$pconfig['sid_changes_log_limit_size'] = $config['installedpackages']['suricata']['config'][0]['sid_changes_log_limit_size'];
$pconfig['sid_changes_log_retention'] = $config['installedpackages']['suricata']['config'][0]['sid_changes_log_retention'];

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

// Set sensible defaults for any unset parameters
if (empty($pconfig['enable_log_mgmt']))
	$pconfig['enable_log_mgmt'] = 'on';
if (empty($pconfig['suricataloglimit']))
	$pconfig['suricataloglimit'] = 'on';
if (empty($pconfig['suricataloglimitsize'])) {
	// Set limit to 20% of slice that is unused */
	$pconfig['suricataloglimitsize'] = round(exec('df -k /var | grep -v "Filesystem" | awk \'{print $4}\'') * .20 / 1024);
}

// Set default retention periods for rotated logs
if (!isset($pconfig['alert_log_retention']))
	$pconfig['alert_log_retention'] = "336";
if (!isset($pconfig['block_log_retention']))
	$pconfig['block_log_retention'] = "336";
if (!isset($pconfig['files_json_log_retention']))
	$pconfig['files_json_log_retention'] = "168";
if (!isset($pconfig['http_log_retention']))
	$pconfig['http_log_retention'] = "168";
if (!isset($pconfig['dns_log_retention']))
	$pconfig['dns_log_retention'] = "168";
if (!isset($pconfig['stats_log_retention']))
	$pconfig['stats_log_retention'] = "168";
if (!isset($pconfig['tls_log_retention']))
	$pconfig['tls_log_retention'] = "336";
if (!isset($pconfig['u2_archive_log_retention']))
	$pconfig['u2_archive_log_retention'] = "168";
if (!isset($pconfig['file_store_retention']))
	$pconfig['file_store_retention'] = "168";
if (!isset($pconfig['tls_certs_store_retention']))
	$pconfig['tls_certs_store_retention'] = "168";
if (!isset($pconfig['eve_log_retention']))
	$pconfig['eve_log_retention'] = "168";
if (!isset($pconfig['sid_changes_log_retention']))
	$pconfig['sid_changes_log_retention'] = "336";

// Set default log file size limits
if (!isset($pconfig['alert_log_limit_size']))
	$pconfig['alert_log_limit_size'] = "500";
if (!isset($pconfig['block_log_limit_size']))
	$pconfig['block_log_limit_size'] = "500";
if (!isset($pconfig['files_json_log_limit_size']))
	$pconfig['files_json_log_limit_size'] = "1000";
if (!isset($pconfig['http_log_limit_size']))
	$pconfig['http_log_limit_size'] = "1000";
if (!isset($pconfig['dns_log_limit_size']))
	$pconfig['dns_log_limit_size'] = "750";
if (!isset($pconfig['stats_log_limit_size']))
	$pconfig['stats_log_limit_size'] = "500";
if (!isset($pconfig['tls_log_limit_size']))
	$pconfig['tls_log_limit_size'] = "500";
if (!isset($pconfig['unified2_log_limit']))
	$pconfig['unified2_log_limit'] = "32";
if (!isset($pconfig['eve_log_limit_size']))
	$pconfig['eve_log_limit_size'] = "5000";
if (!isset($pconfig['sid_changes_log_limit_size']))
	$pconfig['sid_changes_log_limit_size'] = "250";

if (isset($_POST['ResetAll'])) {

	// Reset all settings to their defaults
	$pconfig['alert_log_retention'] = "336";
	$pconfig['block_log_retention'] = "336";
	$pconfig['files_json_log_retention'] = "168";
	$pconfig['http_log_retention'] = "168";
	$pconfig['dns_log_retention'] = "168";
	$pconfig['stats_log_retention'] = "168";
	$pconfig['tls_log_retention'] = "336";
	$pconfig['u2_archive_log_retention'] = "168";
	$pconfig['file_store_retention'] = "168";
	$pconfig['tls_certs_store_retention'] = "168";
	$pconfig['eve_log_retention'] = "168";
	$pconfig['sid_changes_log_retention'] = "336";

	$pconfig['alert_log_limit_size'] = "500";
	$pconfig['block_log_limit_size'] = "500";
	$pconfig['files_json_log_limit_size'] = "1000";
	$pconfig['http_log_limit_size'] = "1000";
	$pconfig['dns_log_limit_size'] = "750";
	$pconfig['stats_log_limit_size'] = "500";
	$pconfig['tls_log_limit_size'] = "500";
	$pconfig['unified2_log_limit'] = "32";
	$pconfig['eve_log_limit_size'] = "5000";
	$pconfig['sid_changes_log_limit_size'] = "250";

	/* Log a message at the top of the page to inform the user */
	$savemsg = gettext("All log management settings on this page have been reset to their defaults.  Click APPLY if you wish to keep these new settings.");
}

if (isset($_POST['save']) || isset($_POST['apply'])) {
	if ($_POST['enable_log_mgmt'] != 'on') {
		$config['installedpackages']['suricata']['config'][0]['enable_log_mgmt'] = $_POST['enable_log_mgmt'] ? 'on' :'off';
		write_config("Suricata pkg: saved updated configuration for LOGS MGMT.");
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();

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

	// Validate unified2 log file limit
	if (!is_numericint($_POST['unified2_log_limit']) || $_POST['unified2_log_limit'] < 1)
			$input_errors[] = gettext("The value for 'Unified2 Log Limit' must be an integer value greater than zero.");

	if (!$input_errors) {
		$config['installedpackages']['suricata']['config'][0]['enable_log_mgmt'] = $_POST['enable_log_mgmt'] ? 'on' :'off';
		$config['installedpackages']['suricata']['config'][0]['clearlogs'] = $_POST['clearlogs'] ? 'on' : 'off';
		$config['installedpackages']['suricata']['config'][0]['suricataloglimit'] = $_POST['suricataloglimit'] ? 'on' :'off';
		$config['installedpackages']['suricata']['config'][0]['suricataloglimitsize'] = $_POST['suricataloglimitsize'];
		$config['installedpackages']['suricata']['config'][0]['alert_log_limit_size'] = $_POST['alert_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['alert_log_retention'] = $_POST['alert_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['block_log_limit_size'] = $_POST['block_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['block_log_retention'] = $_POST['block_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['files_json_log_limit_size'] = $_POST['files_json_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['files_json_log_retention'] = $_POST['files_json_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['http_log_limit_size'] = $_POST['http_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['http_log_retention'] = $_POST['http_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['stats_log_limit_size'] = $_POST['stats_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['stats_log_retention'] = $_POST['stats_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['tls_log_limit_size'] = $_POST['tls_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['tls_log_retention'] = $_POST['tls_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['unified2_log_limit'] = $_POST['unified2_log_limit'];
		$config['installedpackages']['suricata']['config'][0]['u2_archive_log_retention'] = $_POST['u2_archive_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['file_store_retention'] = $_POST['file_store_retention'];
		$config['installedpackages']['suricata']['config'][0]['tls_certs_store_retention'] = $_POST['tls_certs_store_retention'];
		$config['installedpackages']['suricata']['config'][0]['dns_log_limit_size'] = $_POST['dns_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['dns_log_retention'] = $_POST['dns_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['eve_log_limit_size'] = $_POST['eve_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['eve_log_retention'] = $_POST['eve_log_retention'];
		$config['installedpackages']['suricata']['config'][0]['sid_changes_log_limit_size'] = $_POST['sid_changes_log_limit_size'];
		$config['installedpackages']['suricata']['config'][0]['sid_changes_log_retention'] = $_POST['sid_changes_log_retention'];

		write_config("Suricata pkg: saved updated configuration for LOGS MGMT.");
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();

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

$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Logs Management"));
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
	'clearlogs',
	'Remove Suricata Logs On Package Uninstall',
	'Suricata log files will be removed when the Suricata package is uninstalled.  Default is not checked.',
	$pconfig['clearlogs'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'enable_log_mgmt',
	'Auto Log Management',
	'Enable automatic unattended management of Suricata logs using parameters specified below.  Default is checked.',
	$pconfig['enable_log_mgmt'] == 'on' ? true:false,
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
))->setHelp('This setting imposes a hard-limit on the combined log directory size of all Suricata interfaces.  When the size limit set is reached, rotated logs for all interfaces will be removed, and any active logs pruned to zero-length.   (default is 20% of available free disk space)');
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

$group = new Form_Group('dns');
$group->add(new Form_Select(
	'dns_log_limit_size',
	'Max Size',
	$pconfig['dns_log_limit_size'],
	$log_sizes
))->setHelp('Max Size. Default is 750 KB.');
$group->add(new Form_Select(
	'dns_log_retention',
	'Retention',
	$pconfig['dns_log_retention'],
	$retentions
))->setHelp('Retention. Default is 7 DAYS.');
$group->setHelp('DNS request/reply details');
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

$group = new Form_Group('files-json');
$group->add(new Form_Select(
	'files_json_log_limit_size',
	'Max Size',
	$pconfig['files_json_log_limit_size'],
	$log_sizes
))->setHelp('Max Size. Default is 1 MB.');
$group->add(new Form_Select(
	'files_json_log_retention',
	'Retention',
	$pconfig['files_json_log_retention'],
	$retentions
))->setHelp('Retention. Default is 7 DAYS.');
$group->setHelp('Captured files info in JSON format');
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
	'Settings will be ignored for any log in the list above not enabled on the Interface Settings tab. When a log reaches the Max Size limit, it will be rotated and tagged with a timestamp. The Retention period determines how long rotated logs are kept before they are automatically deleted.'
));

$section->addInput(new Form_Input(
	'unified2_log_limit',
	'Unified2 Log Limit',
	'text',
	$pconfig['unified2_log_limit']
))->setHelp('Log file size limit in megabytes (MB). Default is 32 MB. This sets the maximum size for a unified2 log file before it is rotated and a new one created.');
$section->addInput(new Form_Select(
	'u2_archive_log_retention',
	'Unified2 Archived Log Retention Period',
	$pconfig['u2_archive_log_retention'],
	$retentions
))->setHelp('Choose retention period for archived Barnyard2 binary log files. Default is 7 days. When file capture and store is enabled, Suricata captures downloaded files from HTTP sessions and stores them, along with metadata, for later analysis. This setting determines how long files remain in the File Store folder before they are automatically deleted.');
$section->addInput(new Form_Select(
	'file_store_retention',
	'Captured Files Retention Period',
	$pconfig['file_store_retention'],
	$retentions
))->setHelp('Choose retention period for captured files in File Store. Default is 7 days. When file capture and store is enabled, Suricata captures downloaded files from HTTP sessions and stores them, along with metadata, for later analysis. This setting determines how long files remain in the File Store folder before they are automatically deleted.');
$section->addInput(new Form_Select(
	'tls_certs_store_retention',
	'Captured TLS Certs Retention Period',
	$pconfig['tls_certs_store_retention'],
	$retentions
))->setHelp('Choose retention period for captured TLS Certs. Default is 7 days. When custom rules with tls.store are enabled, Suricata captures Certificates, along with metadata, for later analysis. This setting determines how long files remain in the Certs folder before they are automatically deleted.');
$form->add($section);

print($form);

?>

<div class="infoblock">
	<?=print_info_box('<strong>Note:</strong> Changing any settings on this page will affect all Suricata-configured interfaces.', info)?>
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
		disableInput('files_json_log_limit_size', hide);
		disableInput('files_json_log_retention', hide);
		disableInput('http_log_limit_size', hide);
		disableInput('http_log_retention', hide);
		disableInput('stats_log_limit_size', hide);
		disableInput('stats_log_retention', hide);
		disableInput('tls_log_limit_size', hide);
		disableInput('tls_log_retention', hide);
		disableInput('unified2_log_limit', hide);
		disableInput('u2_archive_log_retention', hide);
		disableInput('dns_log_retention', hide);
		disableInput('dns_log_limit_size', hide);
		disableInput('eve_log_retention', hide);
		disableInput('eve_log_limit_size', hide);
		disableInput('sid_changes_log_retention', hide);
		disableInput('sid_changes_log_limit_size', hide);
		disableInput('file_store_retention', hide);
		disableInput('tls_certs_store_retention', hide);
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

