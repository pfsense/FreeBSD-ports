<?php
/*
 * snort_interfaces_global.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2008-2009 Robert Zelaya
 * Copyright (c) 2022 Bill Meeks
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
	$pconfig['snortdownload'] = config_get_path('installedpackages/snortglobal/snortdownload') == "on" ? 'on' : 'off';
	$pconfig['oinkmastercode'] = htmlentities(config_get_path('installedpackages/snortglobal/oinkmastercode'));
	$pconfig['etpro_code'] = htmlentities(config_get_path('installedpackages/snortglobal/etpro_code'));
	$pconfig['emergingthreats'] = config_get_path('installedpackages/snortglobal/emergingthreats') == "on" ? 'on' : 'off';
	$pconfig['emergingthreats_pro'] = config_get_path('installedpackages/snortglobal/emergingthreats_pro') == "on" ? 'on' : 'off';
	$pconfig['rm_blocked'] = config_get_path('installedpackages/snortglobal/rm_blocked');
	$pconfig['autorulesupdate7'] = config_get_path('installedpackages/snortglobal/autorulesupdate7');
	$pconfig['rule_update_starttime'] = htmlentities(config_get_path('installedpackages/snortglobal/rule_update_starttime', '00:' . str_pad(strval(random_int(0,59)), 2, "00", STR_PAD_LEFT)));
	$pconfig['forcekeepsettings'] = config_get_path('installedpackages/snortglobal/forcekeepsettings') == "on" ? 'on' : 'off';
	$pconfig['snortcommunityrules'] = config_get_path('installedpackages/snortglobal/snortcommunityrules') == "on" ? 'on' : 'off';
	$pconfig['clearblocks'] = config_get_path('installedpackages/snortglobal/clearblocks') == "on" ? 'on' : 'off';
	$pconfig['verbose_logging'] = config_get_path('installedpackages/snortglobal/verbose_logging') == "on" ? 'on' : 'off';
	$pconfig['openappid_detectors'] = config_get_path('installedpackages/snortglobal/openappid_detectors') == "on" ? 'on' : 'off';
	$pconfig['openappid_rules_detectors'] = config_get_path('installedpackages/snortglobal/openappid_rules_detectors') == "on" ? 'on' : 'off';
	$pconfig['hide_deprecated_rules'] = config_get_path('installedpackages/snortglobal/hide_deprecated_rules') == "on" ? 'on' : 'off';
	$pconfig['curl_no_verify_ssl_peer'] = config_get_path('installedpackages/snortglobal/curl_no_verify_ssl_peer') == "on" ? 'on' : 'off';
	$pconfig['enable_feodo_botnet_c2_rules'] = config_get_path('installedpackages/snortglobal/enable_feodo_botnet_c2_rules') == "on" ? 'on' : 'off';
}

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
		$input_errors[] = "You must supply an Oinkmaster code in the box provided in order to enable Snort Subscriber rules!";

if ($_POST['emergingthreats_pro'] == "on" && empty($_POST['etpro_code']))
		$input_errors[] = "You must supply a subscription code in the box provided in order to enable Emerging Threats Pro rules!";

/* if no errors move foward with save */
if (!$input_errors) {
	if ($_POST["save"]) {

		config_set_path('installedpackages/snortglobal/snortdownload', $_POST['snortdownload'] ? 'on' : 'off');
		config_set_path('installedpackages/snortglobal/snortcommunityrules', $_POST['snortcommunityrules'] ? 'on' : 'off');
		config_set_path('installedpackages/snortglobal/emergingthreats', $_POST['emergingthreats'] ? 'on' : 'off');
		config_set_path('installedpackages/snortglobal/emergingthreats_pro', $_POST['emergingthreats_pro'] ? 'on' : 'off');
		config_set_path('installedpackages/snortglobal/enable_feodo_botnet_c2_rules', $_POST['enable_feodo_botnet_c2_rules'] ? 'on' : 'off');
		config_set_path('installedpackages/snortglobal/clearblocks', $_POST['clearblocks'] ? 'on' : 'off');
		config_set_path('installedpackages/snortglobal/verbose_logging', $_POST['verbose_logging'] ? 'on' : 'off');
		config_set_path('installedpackages/snortglobal/openappid_detectors', $_POST['openappid_detectors'] ? 'on' : 'off');
		config_set_path('installedpackages/snortglobal/openappid_rules_detectors',  $_POST['openappid_rules_detectors'] ? 'on' : 'off');
		config_set_path('installedpackages/snortglobal/hide_deprecated_rules', $_POST['hide_deprecated_rules'] ? 'on' : 'off');
		config_set_path('installedpackages/snortglobal/curl_no_verify_ssl_peer', $_POST['curl_no_verify_ssl_peer'] ? 'on' : 'off');

		// If any rule sets are being turned off, then remove them
		// from the active rules section of each interface.  Start
		// by building an arry of prefixes for the disabled rules.
		$disabled_rules = array();
		$disable_ips_policy = false;
		if (config_get_path('installedpackages/snortglobal/snortdownload') == 'off') {
			$disabled_rules[] = VRT_FILE_PREFIX;
			$disable_ips_policy = true;
		}
		if (config_get_path('installedpackages/snortglobal/snortcommunityrules') == 'off')
			$disabled_rules[] = GPL_FILE_PREFIX;
		if (config_get_path('installedpackages/snortglobal/emergingthreats') == 'off')
			$disabled_rules[] = ET_OPEN_FILE_PREFIX;
		if (config_get_path('installedpackages/snortglobal/emergingthreats_pro') == 'off')
			$disabled_rules[] = ET_PRO_FILE_PREFIX;
		if (config_get_path('installedpackages/snortglobal/openappid_rules_detectors') == 'off')
			$disabled_rules[] = OPENAPPID_FILE_PREFIX;
		if (config_get_path('installedpackages/snortglobal/enable_feodo_botnet_c2_rules') == 'off')
			$disabled_rules[] = "feodotracker";

		// Now walk all the configured interface rulesets and remove
		// any matching the disabled ruleset prefixes.
		$a_rules = config_get_path('installedpackages/snortglobal/rule', []);
		foreach ($a_rules as &$iface) {
			// Disable Snort IPS policy if Snort Subscriber rules are disabled
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

		// Save the updated interface ruleset to the configuration
		config_set_path('installedpackages/snortglobal/rule', $a_rules);

		// If deprecated rules should be removed, then do it
		if (config_get_path('installedpackages/snortglobal/hide_deprecated_rules') == "on") {
			logger(LOG_NOTICE, localize_text("Hide Deprecated Rules is enabled.  Removing obsoleted rules categories."), LOG_PREFIX_PKG_SNORT);
			snort_remove_dead_rules();
		}

		config_set_path('installedpackages/snortglobal/oinkmastercode', trim(html_entity_decode($_POST['oinkmastercode'])));
		config_set_path('installedpackages/snortglobal/etpro_code', trim(html_entity_decode($_POST['etpro_code'])));
		config_set_path('installedpackages/snortglobal/rm_blocked', $_POST['rm_blocked']);
		config_set_path('installedpackages/snortglobal/autorulesupdate7', $_POST['autorulesupdate7']);

		/* Check and adjust format of Rule Update Starttime string to add colon and leading zero if necessary */
		if ($_POST['rule_update_starttime']) {
			$pos = strpos($_POST['rule_update_starttime'], ":");
			if ($pos === false) {
				$tmp = str_pad($_POST['rule_update_starttime'], 4, "0", STR_PAD_LEFT);
				$_POST['rule_update_starttime'] = substr($tmp, 0, 2) . ":" . substr($tmp, -2);
			}
			config_set_path('installedpackages/snortglobal/rule_update_starttime', str_pad(html_entity_decode($_POST['rule_update_starttime']), 4, "0", STR_PAD_LEFT));
		}

		config_set_path('installedpackages/snortglobal/forcekeepsettings', $_POST['forcekeepsettings'] ? 'on' : 'off');
		$retval = 0;
		write_config("Snort pkg: modified global settings.");

		/* create whitelist and homenet file, then sync files */
		sync_snort_package_config();

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

$pglinks = array("", "/snort/snort_interfaces.php", "@self");
$pgtitle = array("Services", "Snort", "Global Settings");
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

$section = new Form_Section('Snort Subscriber Rules');
$section->addInput(new Form_Checkbox(
	'snortdownload',
	'Enable Snort VRT',
	'Click to enable download of Snort free Registered User or paid Subscriber rules',
	$pconfig['snortdownload'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_StaticText(
	null,
	'<a href="https://www.snort.org/users/sign_up" target="_blank">' . 'Sign Up for a free Registered User Rules Account' . '</a><br/><a href="https://www.snort.org/products" target="_blank">' . 'Sign Up for paid Snort Subscriber Rule Set (by Talos)' . '</a>'
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
	'The Snort Community Ruleset is a GPLv2 Talos certified ruleset that is distributed free of charge without any Snort Subscriber License restrictions.  This ruleset is updated daily and is a subset of the subscriber ruleset.'
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
	'The OpenAppID Detectors package contains the application signatures required by the AppID preprocessor and the OpenAppID text rules.'
));
$section->addInput(new Form_StaticText(
	'OpenAppID Version',
	$openappid_ver
));
$group = new Form_Group('Enable AppID Open Text Rules');
$group->add(new Form_Checkbox(
        'openappid_rules_detectors',
        'Enable RULES OpenAppID',
        'Click to enable download of the AppID Open Text Rules',
        $pconfig['openappid_rules_detectors'] == 'on' ? true:false,
        'on'
));
$group->setHelp('Note - the AppID Open Text Rules file is maintained by a volunteer contributor and hosted by the pfSense team.  ' . 
'The URL for the file ' . 'is <a href="' . SNORT_OPENAPPID_RULES_URL . SNORT_OPENAPPID_RULES_FILENAME . '" target="_blank">' . 
SNORT_OPENAPPID_RULES_URL . SNORT_OPENAPPID_RULES_FILENAME . '</a>.');
$section->add($group);
$form->add($section);

$section = new Form_Section('FEODO Tracker Botnet C2 IP Rules');
$section->addInput(new Form_Checkbox(
	'enable_feodo_botnet_c2_rules',
	'Enable FEODO Tracker Botnet C2 IP Rules',
	'Click to enable download of FEODO Tracker Botnet C2 IP rules',
	$pconfig['enable_feodo_botnet_c2_rules'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_StaticText(
	null,
	'Feodo Tracker tracks certain families that are related to, or that evolved from, Feodo. Originally, Feodo was an ebanking Trojan used by cybercriminals to commit ebanking fraud. Since 2010, various malware families evolved from Feodo, such as Cridex, Dridex, Geodo, Heodo and Emotet.'
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
))->setHelp('Enter the rule update start time in 24-hour format (HH:MM).  Default is 00 hours with a randomly chosen minutes value.  ' . 
			'Rules will update at the interval chosen above starting at the time specified here. ' . 
			'For example, using a start time of 00:08 and choosing 12 Hours for the interval, ' . 
			'the rules will update at 00:08 and 12:08 each day. The randomized minutes value should ' . 
			'be retained to minimize the impact to the rules update site from large numbers of simultaneous requests.');
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
	'Click to clear all blocked hosts added by Snort when removing the package.  Default is checked.',
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
