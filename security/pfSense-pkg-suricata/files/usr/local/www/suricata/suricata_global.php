<?php
/*
 * suricata_global.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2016 Bill Meeks
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

// If doing a postback, used typed values, else load from stored config
if (!empty($_POST)) {
	$pconfig = $_POST;
}
else {
	$pconfig['enable_vrt_rules'] = $config['installedpackages']['suricata']['config'][0]['enable_vrt_rules'] == "on" ? 'on' : 'off';
	$pconfig['oinkcode'] = $config['installedpackages']['suricata']['config'][0]['oinkcode'];
	$pconfig['etprocode'] = $config['installedpackages']['suricata']['config'][0]['etprocode'];
	$pconfig['enable_etopen_rules'] = $config['installedpackages']['suricata']['config'][0]['enable_etopen_rules'] == "on" ? 'on' : 'off';
	$pconfig['enable_etpro_rules'] = $config['installedpackages']['suricata']['config'][0]['enable_etpro_rules'] == "on" ? 'on' : 'off';
	$pconfig['rm_blocked'] = $config['installedpackages']['suricata']['config'][0]['rm_blocked'];
	$pconfig['autoruleupdate'] = $config['installedpackages']['suricata']['config'][0]['autoruleupdate'];
	$pconfig['autoruleupdatetime'] = $config['installedpackages']['suricata']['config'][0]['autoruleupdatetime'];
	$pconfig['live_swap_updates'] = $config['installedpackages']['suricata']['config'][0]['live_swap_updates'] == "on" ? 'on' : 'off';
	$pconfig['log_to_systemlog'] = $config['installedpackages']['suricata']['config'][0]['log_to_systemlog'] == "on" ? 'on' : 'off';
	$pconfig['log_to_systemlog_facility'] = $config['installedpackages']['suricata']['config'][0]['log_to_systemlog_facility'];
	$pconfig['forcekeepsettings'] = $config['installedpackages']['suricata']['config'][0]['forcekeepsettings'] == "on" ? 'on' : 'off';
	$pconfig['snortcommunityrules'] = $config['installedpackages']['suricata']['config'][0]['snortcommunityrules'] == "on" ? 'on' : 'off';
	$pconfig['snort_rules_file'] = $config['installedpackages']['suricata']['config'][0]['snort_rules_file'];
	$pconfig['autogeoipupdate'] = $config['installedpackages']['suricata']['config'][0]['autogeoipupdate'] == "off" ? 'off' : 'on';
	$pconfig['hide_deprecated_rules'] = $config['installedpackages']['suricata']['config'][0]['hide_deprecated_rules'] == "on" ? 'on' : 'off';
}

// Do input validation on parameters
if (empty($pconfig['autoruleupdatetime']))
	$pconfig['autoruleupdatetime'] = '00:30';

if (empty($pconfig['log_to_systemlog_facility']))
	$pconfig['log_to_systemlog_facility'] = "local1";

if ($_POST['autoruleupdatetime']) {
	if (!preg_match('/^([01]?[0-9]|2[0-3]):?([0-5][0-9])$/', $_POST['autoruleupdatetime']))
		$input_errors[] = "Invalid Rule Update Start Time!  Please supply a value in 24-hour format as 'HH:MM'.";
}

if ($_POST['enable_vrt_rules'] == "on" && empty($_POST['snort_rules_file']))
		$input_errors[] = "You must supply a snort rules tarball filename in the box provided in order to enable Snort VRT rules!";

if ($_POST['enable_vrt_rules'] == "on" && empty($_POST['oinkcode']))
		$input_errors[] = "You must supply an Oinkmaster code in the box provided in order to enable Snort VRT rules!";

if ($_POST['enable_etpro_rules'] == "on" && empty($_POST['etprocode']))
		$input_errors[] = "You must supply a subscription code in the box provided in order to enable Emerging Threats Pro rules!";

/* if no errors move foward with save */
if (!$input_errors) {
	if ($_POST["save"]) {

		$config['installedpackages']['suricata']['config'][0]['enable_vrt_rules'] = $_POST['enable_vrt_rules'] ? 'on' : 'off';
		$config['installedpackages']['suricata']['config'][0]['snortcommunityrules'] = $_POST['snortcommunityrules'] ? 'on' : 'off';
		$config['installedpackages']['suricata']['config'][0]['enable_etopen_rules'] = $_POST['enable_etopen_rules'] ? 'on' : 'off';
		$config['installedpackages']['suricata']['config'][0]['enable_etpro_rules'] = $_POST['enable_etpro_rules'] ? 'on' : 'off';
		$config['installedpackages']['suricata']['config'][0]['autogeoipupdate'] = $_POST['autogeoipupdate'] ? 'on' : 'off';
		$config['installedpackages']['suricata']['config'][0]['hide_deprecated_rules'] = $_POST['hide_deprecated_rules'] ? 'on' : 'off';

		// If any rule sets are being turned off, then remove them
		// from the active rules section of each interface.  Start
		// by building an arry of prefixes for the disabled rules.
		$disabled_rules = array();
		$disable_ips_policy = false;
		if ($config['installedpackages']['suricata']['config'][0]['enable_vrt_rules'] == 'off') {
			$disabled_rules[] = VRT_FILE_PREFIX;
			$disable_ips_policy = true;
		}
		if ($config['installedpackages']['suricata']['config'][0]['snortcommunityrules'] == 'off')
			$disabled_rules[] = GPL_FILE_PREFIX;
		if ($config['installedpackages']['suricata']['config'][0]['enable_etopen_rules'] == 'off')
			$disabled_rules[] = ET_OPEN_FILE_PREFIX;
		if ($config['installedpackages']['suricata']['config'][0]['enable_etpro_rules'] == 'off')
			$disabled_rules[] = ET_PRO_FILE_PREFIX;

		// Now walk all the configured interface rulesets and remove
		// any matching the disabled ruleset prefixes.
		if (is_array($config['installedpackages']['suricata']['rule'])) {
			foreach ($config['installedpackages']['suricata']['rule'] as &$iface) {
				// Disable Snort IPS policy if VRT rules are disabled
				if ($disable_ips_policy) {
					$iface['ips_policy_enable'] = 'off';
					unset($iface['ips_policy']);
				}
				$enabled_rules = explode("||", $iface['rulesets']);
				foreach ($enabled_rules as $k => $v) {
					foreach ($disabled_rules as $d)
						if (strpos(trim($v), $d) !== false)
							unset($enabled_rules[$k]);
				}
				$iface['rulesets'] = implode("||", $enabled_rules);
			}
		}

		// If deprecated rules should be removed, then do it
		if ($config['installedpackages']['suricata']['config'][0]['hide_deprecated_rules'] == "on") {
			log_error(gettext("[Suricata] Hide Deprecated Rules is enabled.  Removing obsoleted rules categories."));
			suricata_remove_dead_rules();
		}

		$config['installedpackages']['suricata']['config'][0]['snort_rules_file'] = $_POST['snort_rules_file'];
		$config['installedpackages']['suricata']['config'][0]['oinkcode'] = $_POST['oinkcode'];
		$config['installedpackages']['suricata']['config'][0]['etprocode'] = $_POST['etprocode'];
		$config['installedpackages']['suricata']['config'][0]['rm_blocked'] = $_POST['rm_blocked'];
		$config['installedpackages']['suricata']['config'][0]['autoruleupdate'] = $_POST['autoruleupdate'];

		/* Check and adjust format of Rule Update Starttime string to add colon and leading zero if necessary */
		if ($_POST['autoruleupdatetime']) {
			$pos = strpos($_POST['autoruleupdatetime'], ":");
			if ($pos === false) {
				$tmp = str_pad($_POST['autoruleupdatetime'], 4, "0", STR_PAD_LEFT);
				$_POST['autoruleupdatetime'] = substr($tmp, 0, 2) . ":" . substr($tmp, -2);
			}
			$config['installedpackages']['suricata']['config'][0]['autoruleupdatetime'] = str_pad($_POST['autoruleupdatetime'], 4, "0", STR_PAD_LEFT);
		}
		$config['installedpackages']['suricata']['config'][0]['log_to_systemlog'] = $_POST['log_to_systemlog'] ? 'on' : 'off';
		$config['installedpackages']['suricata']['config'][0]['log_to_systemlog_facility'] = $_POST['log_to_systemlog_facility'];
		$config['installedpackages']['suricata']['config'][0]['live_swap_updates'] = $_POST['live_swap_updates'] ? 'on' : 'off';
		$config['installedpackages']['suricata']['config'][0]['forcekeepsettings'] = $_POST['forcekeepsettings'] ? 'on' : 'off';

		$retval = 0;

		write_config("Suricata pkg: modified global settings.");

		/* Toggle cron task for GeoIP database updates if setting was changed */
		if ($config['installedpackages']['suricata']['config'][0]['autogeoipupdate'] == 'on' && !suricata_cron_job_exists("/usr/local/pkg/suricata/suricata_geoipupdate.php")) {
			include("/usr/local/pkg/suricata/suricata_geoipupdate.php");
			install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_geoipupdate.php", TRUE, 0, 0, 8, "*", "*", "root");
		}
		elseif ($config['installedpackages']['suricata']['config'][0]['autogeoipupdate'] == 'off' && suricata_cron_job_exists("/usr/local/pkg/suricata/suricata_geoipupdate.php"))
			install_cron_job("/usr/local/pkg/suricata/suricata_geoipupdate.php", FALSE);

		/* create passlist and homenet file, then sync files */
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();

		/* forces page to reload new settings */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /suricata/suricata_global.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Global Settings"));
include_once("head.inc");

/* Display Alert message, under form tag or no refresh */
if ($input_errors)
	print_input_errors($input_errors);

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), true, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);
?>

<div id="container">

<?php

$form = new Form;
$section = new Form_Section('Please Choose The Type Of Rules You Wish To Download');
$section->addInput(new Form_Checkbox(
	'enable_etopen_rules',
	'Install ETOpen Emerging Threats rules',
	'ETOpen is an open source set of Suricata rules whose coverage is more limited than ETPro.',
	$pconfig['enable_etopen_rules'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'enable_etpro_rules',
	'Install ETPro Emerging Threats rules',
	'ETPro for Suricata offers daily updates and extensive coverage of current malware threats.',
	$pconfig['enable_etpro_rules'] == 'on' ? true:false,
	'on'
))->setHelp('The ETPro rules contain all of the ETOpen rules, so the ETOpen rules are not required and are disabled when the ETPro rules are selected. <a href="https://www.proofpoint.com/us/products/et-pro-ruleset">Sign Up for an ETPro Account</a>');
$section->addInput(new Form_Input(
	'etprocode',
	'ETPro Subscription Configuration Code',
	'text',
	$pconfig['etprocode']
))->setHelp('Obtain an ETPro subscription code and paste it here.');
$section->addInput(new Form_Checkbox(
	'enable_vrt_rules',
	'Install Snort VRT rules',
	'Snort VRT free Registered User or paid Subscriber rules',
	$pconfig['enable_vrt_rules'] == 'on' ? true:false,
	'on'
))->setHelp('<a href="https://www.snort.org/users/sign_up">Sign Up for a free Registered User Rule Account</a><br /><a href="https://www.snort.org/products">Sign Up for paid Sourcefire VRT Certified Subscriber Rules</a>');
$section->addInput(new Form_Input(
	'snort_rules_file',
	'Snort VRT Rules Filename',
	'text',
	$pconfig['snort_rules_file']
))->setHelp('Enter the rules tarball filename (filename only, do not include the URL.)<br />Example: snortrules-snapshot-2990.tar.gz');
$section->addInput(new Form_Input(
	'oinkcode',
	'Snort VRT Oinkmaster Code',
	'text',
	$pconfig['oinkcode']
))->setHelp('Obtain a snort.org Oinkmaster code and paste it here.');
$section->addInput(new Form_Checkbox(
	'snortcommunityrules',
	'Install Snort Community rules',
	'The Snort Community Ruleset is a GPLv2 VRT certified ruleset that is distributed free of charge without any VRT License restrictions. This ruleset is updated daily and is a subset of the subscriber ruleset.',
	$pconfig['snortcommunityrules'] == 'on' ? true:false,
	'on'
))->setHelp('If you are a Snort VRT Paid Subscriber, the community ruleset is already built into your download of the Snort VRT rules, and there is no benefit in adding this rule set.');
$section->addInput(new Form_Checkbox(
	'hide_deprecated_rules',
	'Hide Deprecated Rules Categories',
	'Hide deprecated rules categories in the GUI and remove them from the configuration. Default is Not Checked.',
	$pconfig['hide_deprecated_rules'] == 'on' ? true:false,
	'on'
));
$form->add($section);

$section = new Form_Section('Rules Update Settings');
$section->addInput(new Form_Select(
	'autoruleupdate',
	'Update Interval',
	$pconfig['autoruleupdate'],
	array('never_up' => gettext('NEVER'), '6h_up' => gettext('6 HOURS'), '12h_up' => gettext('12 HOURS'),
		  '1d_up' => gettext('1 DAY'), '4d_up' => gettext('4 DAYS'), '7d_up' => gettext('7 DAYS'), '28d_up' => gettext('28 DAYS'))
))->setHelp('Please select the interval for rule updates. Choosing NEVER disables auto-updates.<br /><br />Hint: In most cases, every 12 hours is a good choice.');
$section->addInput(new Form_Input(
	'autoruleupdatetime',
	'Update Start Time',
	'text',
	$pconfig['autoruleupdatetime']
))->setHelp('Enter the rule update start time in 24-hour format (HH:MM). Default is 00:30.<br /><br />Rules will update at the interval chosen above starting at the time specified here. For example, using the default start time of 00:30 and choosing 12 Hours for the interval, the rules will update at 00:03 and 12:03 each day.');
$section->addInput(new Form_Checkbox(
	'live_swap_updates',
	'Live Rule Swap on Update',
	'Enable "Live Swap" reload of rules after downloading an update. Default is Not Checked',
	$pconfig['live_swap_updates'] == 'on' ? true:false,
	'on'
))->setHelp('When enabled, Suricata will perform a live load of the new rules following an update instead of a hard restart. If issues are encountered with live load, uncheck this option to perform a hard restart of all Suricata instances following an update.');
$section->addInput(new Form_Checkbox(
	'autogeoipupdate',
	'GeoIP DB Update',
	'Enable downloading of free GeoIP Country Database updates. Default is Checked',
	$pconfig['autogeoipupdate'] == 'on' ? true:false,
	'on'
))->setHelp('When enabled, Suricata will automatically download updates for the free legacy GeoIP country database on the 8th of each month at midnight.<br /><br />If you have a subscription for more current GeoIP updates, uncheck this option and instead create your own process to place the required database files in /usr/local/share/GeoIP/.');
$form->add($section);

$section = new Form_Section('General Settings');
$section->addInput(new Form_Select(
	'rm_blocked',
	'Remove Blocked Hosts Interval',
	$pconfig['rm_blocked'],
	array('never_b' => gettext('NEVER'), '15m_b' => gettext('15 MINS'), '30m_b' => gettext('30 MINS'),
		  '1h_b' => gettext('1 HOUR'), '3h_b' => gettext('3 HOURS'), '6h_b' => gettext('6 HOURS'),
		  '12h_b' => gettext('12 HOURS'), '1d_b' => gettext('1 DAY'), '4d_b' => gettext('4 DAYS'),
		  '7d_b' => gettext('7 DAYS'), '28d_b' => gettext('28 DAYS'))
))->setHelp('Please select the amount of time you would like hosts to be blocked.<br /><br />Hint: in most cases, 1 hour is a good choice.');
$section->addInput(new Form_Checkbox(
	'log_to_systemlog',
	'Log to System Log',
	'Copy Suricata messages to the firewall system log.',
	$pconfig['log_to_systemlog'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Select(
	'log_to_systemlog_facility',
	'Log Facility',
	$pconfig['log_to_systemlog_facility'],
	array('authpriv' => gettext('AUTHPRIV'), 'daemon' => gettext('DAEMON'), 'kern' => gettext('KERN'),
		'security' => gettext('SECURITY'), 'syslog' => gettext('SYSLOG'), 'user' => gettext('USER'), 'local0' => gettext('LOCAL0'),
		'local1' => gettext('LOCAL1'), 'local2' => gettext('LOCAL2'), 'local3' => gettext('LOCAL3'), 'local4' => gettext('LOCAL4'),
		'local5' => gettext('LOCAL5'), 'local6' => gettext('LOCAL6'), 'local7' => gettext('LOCAL7'))
))->setHelp('Select system log facility to use for reporting. Default is LOCAL1.');
$section->addInput(new Form_Checkbox(
	'forcekeepsettings',
	'Keep Suricata Settings After Deinstall',
	'Settings will not be removed during package deinstallation.',
	$pconfig['forcekeepsettings'] == 'on' ? true:false,
	'on'
));
$form->add($section);
print $form;
?>
</div>

<div class="infoblock">
	<?=print_info_box('<strong>Note:</strong> Changing any settings on this page will affect all Suricata-configured interfaces.', info)?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

	function enable_snort_vrt() {
		var hide = ! $('#enable_vrt_rules').prop('checked');
		hideInput('snort_rules_file', hide);
		hideInput('oinkcode', hide);
	}

	function enable_et_rules() {
		var hide = $('#enable_etopen_rules').prop('checked');
		if (hide && $('#enable_etpro_rules').prop('checked')) {
			hideInput('etprocode', hide);
			$('#enable_etpro_rules').prop('checked', false);
		}
	}

	function enable_etpro_rules() {
		var hide = ! $('#enable_etpro_rules').prop('checked');
		hideInput('etprocode', hide);
		if (!hide && $('#enable_etopen_rules').prop('checked'))
			$('#enable_etopen_rules').prop('checked', false);
	}

	function enable_change_rules_upd(val) {
		if (val == 0)
			disableInput('autoruleupdatetime', true);
		else
			disableInput('autoruleupdatetime', false);
	}

	function toggle_log_to_systemlog() {
		var hide = ! $('#log_to_systemlog').prop('checked');
		hideInput('log_to_systemlog_facility', hide);
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------
	// When 'enable_vrt_rules' is clicked, toggle the Oinkmaster text control
	$('#enable_vrt_rules').click(function() {
		enable_snort_vrt();
	});

	// When 'enable_etopen_rules' is clicked, uncheck ETPro and hide the ETPro Code text control
	$('#enable_etopen_rules').click(function() {
		enable_et_rules();
	});

	// When 'enable_etpro_rules' is clicked, uncheck ET Open checkbox control and show code
	$('#enable_etpro_rules').click(function() {
		enable_etpro_rules();
	});

	// When 'autoruleupdate' is set to never, disable 'autoruleupdatetime'
	$('#autoruleupdate').on('change', function() {
		enable_change_rules_upd(this.selectedIndex);
	});

	// When 'log_to_systemlog' is clicked, toggle 'log_to_systemlog_facility'
	$('#log_to_systemlog').click(function() {
		toggle_log_to_systemlog();
	});

	// ---------- On initial page load ------------------------------------------------------------
	enable_snort_vrt();
	enable_et_rules();
	enable_etpro_rules();
	enable_change_rules_upd($('#autoruleupdate').prop('selectedIndex'));
	toggle_log_to_systemlog();

});
//]]>
</script>

<?php
include("foot.inc");
?>
