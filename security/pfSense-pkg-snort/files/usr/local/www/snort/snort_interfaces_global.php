<?php
/*
 * snort_interfaces_global.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2015 Bill Meeks
 * Copyright (c) 2008-2009 Robert Zelaya
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

global $g;

$snortdir = SNORTDIR;
$snort_openappdir = SNORT_APPID_ODP_PATH;

// Grab any previous input values if doing a SAVE operation
if ($_POST['save'])
	$pconfig = $_POST;
else {
	$pconfig['snortdownload'] = $config['installedpackages']['snortglobal']['snortdownload'] == "on" ? 'on' : 'off';
	$pconfig['oinkmastercode'] = $config['installedpackages']['snortglobal']['oinkmastercode'];
	$pconfig['etpro_code'] = $config['installedpackages']['snortglobal']['etpro_code'];
	$pconfig['emergingthreats'] = $config['installedpackages']['snortglobal']['emergingthreats'] == "on" ? 'on' : 'off';
	$pconfig['emergingthreats_pro'] = $config['installedpackages']['snortglobal']['emergingthreats_pro'] == "on" ? 'on' : 'off';
	$pconfig['rm_blocked'] = $config['installedpackages']['snortglobal']['rm_blocked'];
	$pconfig['autorulesupdate7'] = $config['installedpackages']['snortglobal']['autorulesupdate7'];
	$pconfig['rule_update_starttime'] = $config['installedpackages']['snortglobal']['rule_update_starttime'];
	$pconfig['forcekeepsettings'] = $config['installedpackages']['snortglobal']['forcekeepsettings'] == "on" ? 'on' : 'off';
	$pconfig['snortcommunityrules'] = $config['installedpackages']['snortglobal']['snortcommunityrules'] == "on" ? 'on' : 'off';
	$pconfig['clearblocks'] = $config['installedpackages']['snortglobal']['clearblocks'] == "on" ? 'on' : 'off';
	$pconfig['verbose_logging'] = $config['installedpackages']['snortglobal']['verbose_logging'] == "on" ? 'on' : 'off';
	$pconfig['openappid_detectors'] = $config['installedpackages']['snortglobal']['openappid_detectors'] == "on" ? 'on' : 'off';
	$pconfig['openappid_rules_detectors'] = $config['installedpackages']['snortglobal']['openappid_rules_detectors'] == "on" ? 'on' : 'off';
	$pconfig['hide_deprecated_rules'] = $config['installedpackages']['snortglobal']['hide_deprecated_rules'] == "on" ? 'on' : 'off';
	$pconfig['curl_no_verify_ssl_peer'] = $config['installedpackages']['snortglobal']['curl_no_verify_ssl_peer'] == "on" ? 'on' : 'off';
}

/* Set sensible values for any empty default params */
if (!isset($pconfig['rule_update_starttime']))
	$pconfig['rule_update_starttime'] = '00:05';
if (!isset($config['installedpackages']['snortglobal']['forcekeepsettings']))
	$pconfig['forcekeepsettings'] = 'on';
if (!isset($config['installedpackages']['snortglobal']['curl_no_verify_ssl_peer']))
	$pconfig['curl_no_verify_ssl_peer'] = 'off';

/* Grab OpenAppID version info if enabled and downloaded */
if ($pconfig['openappid_detectors'] == "on") {
	if (file_exists("{$snort_openappdir}odp/version.conf")) {
		$openappid_ver = gettext("Installed Detection Package ");
		$openappid_ver .= gettext(ucfirst(strtolower(file_get_contents("{$snort_openappdir}odp/version.conf"))));
	}
	else
		$openappid_ver = gettext("N/A (Not Downloaded)");
}

if ($_POST['rule_update_starttime']) {
	if (!preg_match('/^([01]?[0-9]|2[0-3]):?([0-5][0-9])$/', $_POST['rule_update_starttime']))
		$input_errors[] = "Invalid Rule Update Start Time!  Please supply a value in 24-hour format as 'HH:MM'.";
}

if ($_POST['snortdownload'] == "on" && empty($_POST['oinkmastercode']))
		$input_errors[] = "You must supply an Oinkmaster code in the box provided in order to enable Snort VRT rules!";

if ($_POST['emergingthreats_pro'] == "on" && empty($_POST['etpro_code']))
		$input_errors[] = "You must supply a subscription code in the box provided in order to enable Emerging Threats Pro rules!";

/* if no errors move foward with save */
if (!$input_errors) {
	if ($_POST["save"]) {

		$config['installedpackages']['snortglobal']['snortdownload'] = $_POST['snortdownload'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['snortcommunityrules'] = $_POST['snortcommunityrules'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['emergingthreats'] = $_POST['emergingthreats'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['emergingthreats_pro'] = $_POST['emergingthreats_pro'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['clearblocks'] = $_POST['clearblocks'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['verbose_logging'] = $_POST['verbose_logging'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['openappid_detectors'] = $_POST['openappid_detectors'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['openappid_rules_detectors'] = $_POST['openappid_rules_detectors'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['hide_deprecated_rules'] = $_POST['hide_deprecated_rules'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['curl_no_verify_ssl_peer'] = $_POST['curl_no_verify_ssl_peer'] ? 'on' : 'off';

		// If any rule sets are being turned off, then remove them
		// from the active rules section of each interface.  Start
		// by building an arry of prefixes for the disabled rules.
		$disabled_rules = array();
		$disable_ips_policy = false;
		if ($config['installedpackages']['snortglobal']['snortdownload'] == 'off') {
			$disabled_rules[] = VRT_FILE_PREFIX;
			$disable_ips_policy = true;
		}
		if ($config['installedpackages']['snortglobal']['snortcommunityrules'] == 'off')
			$disabled_rules[] = GPL_FILE_PREFIX;
		if ($config['installedpackages']['snortglobal']['emergingthreats'] == 'off')
			$disabled_rules[] = ET_OPEN_FILE_PREFIX;
		if ($config['installedpackages']['snortglobal']['emergingthreats_pro'] == 'off')
			$disabled_rules[] = ET_PRO_FILE_PREFIX;
		if ($config['installedpackages']['snortglobal']['openappid_rules_detectors'] == 'off')
			$disabled_rules[] = OPENAPPID_FILE_PREFIX;

		// Now walk all the configured interface rulesets and remove
		// any matching the disabled ruleset prefixes.
		if (is_array($config['installedpackages']['snortglobal']['rule'])) {
			foreach ($config['installedpackages']['snortglobal']['rule'] as &$iface) {
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
		if ($config['installedpackages']['snortglobal']['hide_deprecated_rules'] == "on") {
			log_error(gettext("[Snort] Hide Deprecated Rules is enabled.  Removing obsoleted rules categories."));
			snort_remove_dead_rules();
		}

		$config['installedpackages']['snortglobal']['oinkmastercode'] = $_POST['oinkmastercode'];
		$config['installedpackages']['snortglobal']['etpro_code'] = $_POST['etpro_code'];

		$config['installedpackages']['snortglobal']['rm_blocked'] = $_POST['rm_blocked'];
		$config['installedpackages']['snortglobal']['autorulesupdate7'] = $_POST['autorulesupdate7'];

		/* Check and adjust format of Rule Update Starttime string to add colon and leading zero if necessary */
		if ($_POST['rule_update_starttime']) {
			$pos = strpos($_POST['rule_update_starttime'], ":");
			if ($pos === false) {
				$tmp = str_pad($_POST['rule_update_starttime'], 4, "0", STR_PAD_LEFT);
				$_POST['rule_update_starttime'] = substr($tmp, 0, 2) . ":" . substr($tmp, -2);
			}
			$config['installedpackages']['snortglobal']['rule_update_starttime'] = str_pad($_POST['rule_update_starttime'], 4, "0", STR_PAD_LEFT);
		}

		$config['installedpackages']['snortglobal']['forcekeepsettings'] = $_POST['forcekeepsettings'] ? 'on' : 'off';

		$retval = 0;

		write_config("Snort pkg: modified global settings.");

		/* create whitelist and homenet file, then sync files */
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();

		/* forces page to reload new settings */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_interfaces_global.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Global Settings"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$form = new Form(new Form_Button(
	'save',
	'Save'
));

$section = new Form_Section('Snort Vulnerability Research Team (VRT) Rules');
$section->addInput(new Form_Checkbox(
	'snortdownload',
	'Enable Snort VRT',
	'Click to enable download of Snort VRT free Registered User or paid Subscriber rules',
	$pconfig['snortdownload'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_StaticText(
	null,
	'<a href="https://www.snort.org/users/sign_up" target="_blank">' . 'Sign Up for a free Registered User Rule Account' . '</a><br/><a href="https://www.snort.org/products" target="_blank">' . 'Sign Up for paid Sourcefire VRT Certified Subscriber Rules' . '</a>'
));
$section->addInput(new Form_Input(
	'oinkmastercode',
	'Snort Oinkmaster Code',
	'text',
	$pconfig['oinkmastercode']
))->setHelp('Obtain a snort.org Oinkmaster code and paste it here. (Paste the code only and not the URL!)');

$form->add($section);

$section = new Form_Section('Snort GPLv2 Community Rules');
$section->addInput(new Form_Checkbox(
	'snortcommunityrules',
	'Enable Snort GPLv2',
	'Click to enable download of Snort GPLv2 Community rules',
	$pconfig['snortcommunityrules'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_StaticText(
	null,
	'The Snort Community Ruleset is a GPLv2 VRT certified ruleset that is distributed free of charge without any VRT License restrictions.  This ruleset is updated daily and is a subset of the subscriber ruleset.'
));

$form->add($section);


$section = new Form_Section('Emerging Threats (ET) Rules');
$section->addInput(new Form_Checkbox(
	'emergingthreats',
	'Enable ET Open',
	'Click to enable download of Emerging Threats Open rules',
	$pconfig['emergingthreats'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_StaticText(
	null,
	'ETOpen is an open source set of Snort rules whose coverage is more limited than ETPro.'
));

$section->addInput(new Form_Checkbox(
	'emergingthreats_pro',
	'Enable ET Pro',
	'Click to enable download of Emerging Threats Pro rules',
	$pconfig['emergingthreats_pro'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_StaticText(
	null,
	'<a href="https://www.proofpoint.com/us/solutions/products/threat-intelligence/ET-Pro-Ruleset" target="_blank">' . 'Sign Up for an ETPro Account' . '</a><br/>' . 'ETPro for Snort offers daily updates and extensive coverage of current malware threats.'
));
$section->addInput(new Form_Input(
	'etpro_code',
	'ETPro Code',
	'text',
	$pconfig['etpro_code']
))->setHelp('Obtain an ETPro subscription code and paste it here. (Paste the code only and not the URL!)');

$form->add($section);

$section = new Form_Section('Sourcefire OpenAppID Detectors');
$section->addInput(new Form_Checkbox(
	'openappid_detectors',
	'Enable OpenAppID',
	'Click to enable download of Sourcefire OpenAppID Detectors',
	$pconfig['openappid_detectors'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_StaticText(
	null,
	'The OpenAppID package contains the application signatures required by the AppID preprocessor.'
));
$section->addInput(new Form_StaticText(
	'OpenAppID Version',
	$openappid_ver
));
$section->addInput(new Form_Checkbox(
        'openappid_rules_detectors',
        'Enable RULES OpenAppID',
        'Click to enable download of APPID Open rules',
        $pconfig['openappid_rules_detectors'] == 'on' ? true:false,
        'on'
));

$form->add($section);

$section = new Form_Section('Rules Update Settings');
$section->addInput(new Form_Select(
	'autorulesupdate7',
	'Update Interval',
	$pconfig['autorulesupdate7'],
	array('never_up' => gettext('NEVER'), '6h_up' => gettext('6 HOURS'), '12h_up' => gettext('12 HOURS'), 
		  '1d_up' => gettext('1 DAY'), '4d_up' => gettext('4 DAYS'), '7d_up' => gettext('7 DAYS'), '28d_up' => gettext('28 DAYS'))
))->setHelp('Please select the interval for rule updates. Choosing NEVER disables auto-updates.');
$section->addInput(new Form_Input(
	'rule_update_starttime',
	'Update Start Time',
	'text',
	$pconfig['rule_update_starttime']
))->setHelp('Enter the rule update start time in 24-hour format (HH:MM).  Default is 00:05.  ' . 
			'Rules will update at the interval chosen above starting at the time specified here. ' . 
			'For example, using the default start time of 00:05 and choosing 12 Hours for the interval, ' . 
			'the rules will update at 00:05 and 12:05 each day.');
$section->addInput(new Form_Checkbox(
	'hide_deprecated_rules',
	'Hide Deprecated Rules Categories',
	'Click to hide deprecated rules categories in the GUI and remove them from the configuration.  ' . 
	'Default is not checked.',
	$pconfig['hide_deprecated_rules'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'curl_no_verify_ssl_peer',
	'Disable SSL Peer Verification',
	'Click to disable verification of SSL peers during rules updates.  This is commonly needed only for self-signed certificates.  ' . 
	'Default is not checked.',
	$pconfig['curl_no_verify_ssl_peer'] == 'on' ? true:false,
	'on'
));

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
))->setHelp('Please select the amount of time you would like hosts to be blocked.  In most cases, one hour is a good choice.');
$section->addInput(new Form_Checkbox(
	'clearblocks',
	'Remove Blocked Hosts After Deinstall',
	'Click to clear all blocked hosts added by Snort when removing the package.',
	$pconfig['clearblocks'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'forcekeepsettings',
	'Keep Snort Settings After Deinstall',
	'Click to retain Snort settings after package removal.',
	$pconfig['forcekeepsettings'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'verbose_logging',
	'Startup/Shutdown Logging',
	'Click to output detailed messages to the system log when Snort is starting and stopping.  Default is not checked.',
	$pconfig['verbose_logging'] == 'on' ? true:false,
	'on'
));

$form->add($section);


$tab_array = array();
	$tab_array[] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), true, "/snort/snort_interfaces_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
	$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
	$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
	$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

	function enable_snort_vrt() {
		var hide = ! $('#snortdownload').prop('checked');
		hideInput('oinkmastercode', hide);
	}

	function enable_et_rules() {
		var hide = $('#emergingthreats').prop('checked');
		if (hide && $('#emergingthreats_pro').prop('checked')) {
			hideInput('etpro_code', hide);
			$('#emergingthreats_pro').prop('checked', false);
		}
	}

	function enable_etpro_rules() {
		var hide = ! $('#emergingthreats_pro').prop('checked');
		hideInput('etpro_code', hide);
		if (!hide && $('#emergingthreats').prop('checked'))
			$('#emergingthreats').prop('checked', false);
	}

	function enable_openappid_dnload() {
		var hide = ! $('#openappid_detectors').prop('checked');
	}

	function enable_change_rules_upd(val) {
		if (val == 0)
			disableInput('rule_update_starttime', true);
		else
			disableInput('rule_update_starttime', false);
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------
	// When 'enable' is clicked, disable/enable the Oinkmaster text control
	$('#snortdownload').click(function() {
		enable_snort_vrt();
	});

	// When 'emergingthreats' is clicked, uncheck ETPro and disable the ETPro Code text control
	$('#emergingthreats').click(function() {
		enable_et_rules();
	});

	// When 'emergingthreats_pro' is clicked, uncheck ET checkbox control
	$('#emergingthreats_pro').click(function() {
		enable_etpro_rules();
	});

	// When 'openappid_detectors' is clicked, toggle hidden state of version static text control
	$('#openappid_detectors').click(function() {
		enable_etpro_rules();
	});

	$('#autorulesupdate7').on('change', function() {
		enable_change_rules_upd(this.selectedIndex);
	});

	// ---------- On initial page load ------------------------------------------------------------
	enable_snort_vrt();
	enable_et_rules();
	enable_etpro_rules();
	enable_change_rules_upd($('#autorulesupdate7').prop('selectedIndex'));
	enable_openappid_dnload();

});
//]]>
</script>

<?php include("foot.inc"); ?>
