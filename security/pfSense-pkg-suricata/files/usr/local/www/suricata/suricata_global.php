<?php
/*
 * suricata_global.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2024 Bill Meeks
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
	$pconfig['enable_vrt_rules'] = config_get_path('installedpackages/suricata/config/0/enable_vrt_rules') == "on" ? 'on' : 'off';
	$pconfig['oinkcode'] = htmlentities(config_get_path('installedpackages/suricata/config/0/oinkcode'));
	$pconfig['etprocode'] = htmlentities(config_get_path('installedpackages/suricata/config/0/etprocode'));
	$pconfig['enable_etopen_rules'] = config_get_path('installedpackages/suricata/config/0/enable_etopen_rules') == "on" ? 'on' : 'off';
	$pconfig['enable_etpro_rules'] = config_get_path('installedpackages/suricata/config/0/enable_etpro_rules') == "on" ? 'on' : 'off';
	$pconfig['rm_blocked'] = config_get_path('installedpackages/suricata/config/0/rm_blocked');
	$pconfig['autoruleupdate'] = config_get_path('installedpackages/suricata/config/0/autoruleupdate');
	$pconfig['autoruleupdatetime'] = htmlentities(config_get_path('installedpackages/suricata/config/0/autoruleupdatetime'));
	$pconfig['live_swap_updates'] = config_get_path('installedpackages/suricata/config/0/live_swap_updates') == "on" ? 'on' : 'off';
	$pconfig['log_to_systemlog'] = config_get_path('installedpackages/suricata/config/0/log_to_systemlog') == "on" ? 'on' : 'off';
	$pconfig['update_notify'] = config_get_path('installedpackages/suricata/config/0/update_notify') == "on" ? 'on' : 'off';
	$pconfig['rule_categories_notify'] = config_get_path('installedpackages/suricata/config/0/rule_categories_notify') == "on" ? 'on' : 'off';
	$pconfig['log_to_systemlog_facility'] = config_get_path('installedpackages/suricata/config/0/log_to_systemlog_facility');
	$pconfig['log_to_systemlog_priority'] = config_get_path('installedpackages/suricata/config/0/log_to_systemlog_priority');
	$pconfig['forcekeepsettings'] = config_get_path('installedpackages/suricata/config/0/forcekeepsettings') == "on" ? 'on' : 'off';
	$pconfig['clearblocks'] = config_get_path('installedpackages/suricata/config/0/clearblocks') == "off" ? 'off' : 'on';
	$pconfig['snortcommunityrules'] = config_get_path('installedpackages/suricata/config/0/snortcommunityrules') == "on" ? 'on' : 'off';
	$pconfig['snort_rules_file'] = htmlentities(config_get_path('installedpackages/suricata/config/0/snort_rules_file'));
	$pconfig['autogeoipupdate'] = config_get_path('installedpackages/suricata/config/0/autogeoipupdate') == "on" ? 'on' : 'off';
	$pconfig['maxmind_geoipdb_uid'] = htmlentities(config_get_path('installedpackages/suricata/config/0/maxmind_geoipdb_uid'));
	$pconfig['maxmind_geoipdb_key'] = htmlentities(config_get_path('installedpackages/suricata/config/0/maxmind_geoipdb_key'));
	$pconfig['hide_deprecated_rules'] = config_get_path('installedpackages/suricata/config/0/hide_deprecated_rules') == "on" ? 'on' : 'off';
	$pconfig['enable_etopen_custom_url'] = config_get_path('installedpackages/suricata/config/0/enable_etopen_custom_url') == "on" ? 'on' : 'off';
	$pconfig['enable_etpro_custom_url'] = config_get_path('installedpackages/suricata/config/0/enable_etpro_custom_url') == "on" ? 'on' : 'off';
	$pconfig['enable_snort_custom_url'] = config_get_path('installedpackages/suricata/config/0/enable_snort_custom_url') == "on" ? 'on' : 'off';
	$pconfig['enable_gplv2_custom_url'] = config_get_path('installedpackages/suricata/config/0/enable_gplv2_custom_url') == "on" ? 'on' : 'off';
	$pconfig['etopen_custom_rule_url'] = htmlentities(config_get_path('installedpackages/suricata/config/0/etopen_custom_rule_url'));
	$pconfig['etpro_custom_rule_url'] = htmlentities(config_get_path('installedpackages/suricata/config/0/etpro_custom_rule_url'));
	$pconfig['snort_custom_url'] = htmlentities(config_get_path('installedpackages/suricata/config/0/snort_custom_url'));
	$pconfig['gplv2_custom_url'] = htmlentities(config_get_path('installedpackages/suricata/config/0/gplv2_custom_url'));
	$pconfig['enable_feodo_botnet_c2_rules'] = config_get_path('installedpackages/suricata/config/0/enable_feodo_botnet_c2_rules') == "on" ? 'on' : 'off';
	$pconfig['enable_abuse_ssl_blacklist_rules'] = config_get_path('installedpackages/suricata/config/0/enable_abuse_ssl_blacklist_rules') == "on" ? 'on' : 'off';
	$pconfig['enable_extra_rules'] = config_get_path('installedpackages/suricata/config/0/enable_extra_rules') == "on" ? 'on' : 'off';
	$pconfig['extra_rules'] = config_get_path('installedpackages/suricata/config/0/extra_rules', []);
}

// Do input validation on parameters
if (empty($pconfig['autoruleupdatetime']))
	$pconfig['autoruleupdatetime'] = '00:' . str_pad(strval(random_int(0,59)), 2, "00", STR_PAD_LEFT);

if (empty($pconfig['log_to_systemlog_facility']))
	$pconfig['log_to_systemlog_facility'] = "local1";

if (empty($pconfig['log_to_systemlog_priority']))
	$pconfig['log_to_systemlog_priority'] = "notice";

if ($_POST['autoruleupdatetime']) {
	if (!preg_match('/^([01]?[0-9]|2[0-3]):?([0-5][0-9])$/', $_POST['autoruleupdatetime']))
		$input_errors[] = "Invalid Rule Update Start Time!  Please supply a value in 24-hour format as 'HH:MM'.";
}

if ($_POST['enable_vrt_rules'] == "on" && empty($_POST['snort_rules_file']))
		$input_errors[] = "You must supply a snort rules tarball filename in the box provided in order to enable Snort Subscriber rules!";

if ($_POST['enable_vrt_rules'] == "on" && empty($_POST['oinkcode']))
		$input_errors[] = "You must supply an Oinkmaster code in the box provided in order to enable Snort Subscriber rules!";

if ($_POST['enable_etpro_rules'] == "on" && empty($_POST['etprocode']))
		$input_errors[] = "You must supply a subscription code in the box provided in order to enable Emerging Threats Pro rules!";

if ($_POST['enable_etopen_custom_url'] == "on" && empty(trim(html_entity_decode($_POST['etopen_custom_rule_url']))))
		$input_errors[] = "'Use Custom ET Open Rule download URL' is checked, but the ET Open Custom URL field is blank!";

if ($_POST['enable_etpro_custom_url'] == "on" && empty(trim(html_entity_decode($_POST['etpro_custom_rule_url']))))
		$input_errors[] = "'Use Custom ET Pro Rule download URL' is checked, but the ET Pro Custom URL field is blank!";

if ($_POST['enable_snort_custom_url'] == "on" && empty(trim(html_entity_decode($_POST['snort_custom_url']))))
		$input_errors[] = "'Use Custom Snort Rule download URL' is checked, but the Snort Custom URL field is blank!";

if ($_POST['enable_gplv2_custom_url'] == "on" && empty(trim(html_entity_decode($_POST['gplv2_custom_url']))))
		$input_errors[] = "'Use Custom Snort GPLv2 Rule download URL' is checked, but the Snort GPLv2 Custom URL field is blank!";

if ($_POST['enable_extra_rules']) {
	for ($x = 0; $x < 99; $x++) {
		if (isset($_POST["name{$x}"]) && isset($_POST["url{$x}"])) { 
			$name = trim($_POST["name{$x}"]);
			$url = $_POST["url{$x}"];
			if (preg_match("/[^A-Za-z0-9_]/", $name)) {
				$input_errors[] = gettext("The rules name may only contain the
				    characters A-Z, 0-9 and '-'.");
			}
			if (!is_URL($url) || ((substr($url, strrpos($url, 'rules')) != 'rules') &&
			    !preg_match('/.+\.tar\.gz$/', $url))) { 
				$input_errors[] = sprintf(gettext('%s is not valid rules or tar.gz rules archive URL.'), htmlspecialchars($url));
			}
			$extra_rules['rule'][] = array(
				'name' => $name,
				'url' => $url,
				'md5' => isset($_POST["md5{$x}"]) ? 'on' : 'off'
			);
			$enabled_extra_rules[] = $name;
		}
	}
	$pconfig['extra_rules'] = $extra_rules;
}

/* if no errors move foward with save */
if (!$input_errors) {
	if ($_POST["save"]) {

		config_set_path('installedpackages/suricata/config/0/enable_vrt_rules', $_POST['enable_vrt_rules'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/snortcommunityrules', $_POST['snortcommunityrules'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/enable_etopen_rules', $_POST['enable_etopen_rules'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/enable_etpro_rules', $_POST['enable_etpro_rules'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/autogeoipupdate', $_POST['autogeoipupdate'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/hide_deprecated_rules', $_POST['hide_deprecated_rules'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/enable_etopen_custom_url', $_POST['enable_etopen_custom_url'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/enable_etpro_custom_url', $_POST['enable_etpro_custom_url'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/enable_snort_custom_url', $_POST['enable_snort_custom_url'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/enable_gplv2_custom_url', $_POST['enable_gplv2_custom_url'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/enable_feodo_botnet_c2_rules', $_POST['enable_feodo_botnet_c2_rules'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/enable_abuse_ssl_blacklist_rules', $_POST['enable_abuse_ssl_blacklist_rules'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/enable_extra_rules', $_POST['enable_extra_rules'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/extra_rules', $extra_rules);

		// If any rule sets are being turned off, then remove them
		// from the active rules section of each interface.  Start
		// by building an arry of prefixes for the disabled rules.
		$disabled_rules = array();
		$disable_ips_policy = false;
		if (config_get_path('installedpackages/suricata/config/0/enable_vrt_rules') == 'off') {
			$disabled_rules[] = VRT_FILE_PREFIX;
			$disable_ips_policy = true;
		}
		if (config_get_path('installedpackages/suricata/config/0/snortcommunityrules') == 'off')
			$disabled_rules[] = GPL_FILE_PREFIX;
		if (config_get_path('installedpackages/suricata/config/0/enable_etopen_rules') == 'off')
			$disabled_rules[] = ET_OPEN_FILE_PREFIX;
		if (config_get_path('installedpackages/suricata/config/0/enable_etpro_rules') == 'off')
			$disabled_rules[] = ET_PRO_FILE_PREFIX;

		if (config_get_path('installedpackages/suricata/config/0/enable_feodo_botnet_c2_rules') == 'off')
			$disabled_rules[] = "feodotracker";
		if (config_get_path('installedpackages/suricata/config/0/enable_abuse_ssl_blacklist_rules') == 'off')
			$disabled_rules[] = "sslblacklist_tls_cert";

		if (empty($enabled_extra_rules))
			$disabled_rules[] = EXTRARULE_FILE_PREFIX;

		// Now walk all the configured interface rulesets and remove
		// any matching the disabled ruleset prefixes.
		foreach (config_get_path('installedpackages/suricata/rule', []) as $idx => &$iface) {
			// Disable Snort IPS policy if Snort rules are disabled
			if ($disable_ips_policy) {
				$iface['ips_policy_enable'] = 'off';
				unset($iface['ips_policy']);
			}
			$enabled_rules = explode("||", $iface['rulesets']);
			foreach ($enabled_rules as $k => $v) {
				foreach ($disabled_rules as $d) {
					if (strpos(trim($v), $d) !== false) { 
						unset($enabled_rules[$k]);
						continue;
					} elseif (!empty($enabled_extra_rules)) {
						foreach ($enabled_extra_rules as $exrule) {
							if (strpos(trim($v), EXTRARULE_FILE_PREFIX . $exrule)) {
								unset($enabled_rules[$k]);
								continue 2;
							}
						}
					}
				}
			}
			$iface['rulesets'] = implode("||", $enabled_rules);
			config_set_path("installedpackages/suricata/rule/{$idx}", $iface);
		}
		// Release the config array reference we used
		unset($iface);

		// If deprecated rules should be removed, then do it
		if (config_get_path('installedpackages/suricata/config/0/hide_deprecated_rules') == "on") {
			logger(LOG_NOTICE, localize_text("Hide Deprecated Rules is enabled.  Removing obsoleted rules categories."), LOG_PREFIX_PKG_SURICATA);
			suricata_remove_dead_rules();
		}

		config_set_path('installedpackages/suricata/config/0/snort_rules_file', html_entity_decode($_POST['snort_rules_file']));
		config_set_path('installedpackages/suricata/config/0/oinkcode', trim(html_entity_decode($_POST['oinkcode'])));
		config_set_path('installedpackages/suricata/config/0/etprocode', trim(html_entity_decode($_POST['etprocode'])));
		config_set_path('installedpackages/suricata/config/0/rm_blocked', $_POST['rm_blocked']);
		config_set_path('installedpackages/suricata/config/0/autoruleupdate', $_POST['autoruleupdate']);
		config_set_path('installedpackages/suricata/config/0/etopen_custom_rule_url', trim(html_entity_decode($_POST['etopen_custom_rule_url'])));
		config_set_path('installedpackages/suricata/config/0/etpro_custom_rule_url', trim(html_entity_decode($_POST['etpro_custom_rule_url'])));
		config_set_path('installedpackages/suricata/config/0/snort_custom_url', trim(html_entity_decode($_POST['snort_custom_url'])));
		config_set_path('installedpackages/suricata/config/0/gplv2_custom_url', trim(html_entity_decode($_POST['gplv2_custom_url'])));
		config_set_path('installedpackages/suricata/config/0/maxmind_geoipdb_uid', trim(html_entity_decode($_POST['maxmind_geoipdb_uid'])));
		config_set_path('installedpackages/suricata/config/0/maxmind_geoipdb_key', trim(html_entity_decode($_POST['maxmind_geoipdb_key'])));

		/* Check and adjust format of Rule Update Starttime string to add colon and leading zero if necessary */
		if ($_POST['autoruleupdatetime']) {
			$pos = strpos($_POST['autoruleupdatetime'], ":");
			if ($pos === false) {
				$tmp = str_pad($_POST['autoruleupdatetime'], 4, "0", STR_PAD_LEFT);
				$_POST['autoruleupdatetime'] = substr($tmp, 0, 2) . ":" . substr($tmp, -2);
			}
			config_set_path('installedpackages/suricata/config/0/autoruleupdatetime', str_pad(html_entity_decode($_POST['autoruleupdatetime']), 4, "0", STR_PAD_LEFT));
		}
		config_set_path('installedpackages/suricata/config/0/log_to_systemlog', $_POST['log_to_systemlog'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/update_notify', $_POST['update_notify'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/rule_categories_notify', $_POST['rule_categories_notify'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/log_to_systemlog_facility', $_POST['log_to_systemlog_facility']);
		config_set_path('installedpackages/suricata/config/0/log_to_systemlog_priority', $_POST['log_to_systemlog_priority']);
		config_set_path('installedpackages/suricata/config/0/live_swap_updates', $_POST['live_swap_updates'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/forcekeepsettings', $_POST['forcekeepsettings'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/clearblocks', $_POST['clearblocks'] ? 'on' : 'off');

		$retval = 0;

		write_config("Suricata pkg: modified global settings.");

		/* Toggle cron task for GeoIP database updates if setting was changed */
		if (config_get_path('installedpackages/suricata/config/0/autogeoipupdate') == 'on' && !suricata_cron_job_exists("/usr/local/pkg/suricata/suricata_geoipupdate.php")) {
			include("/usr/local/pkg/suricata/suricata_geoipupdate.php");
			install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_geoipupdate.php", TRUE, 0, 0, 8, "*", "*", "root");
		}
		elseif (config_get_path('installedpackages/suricata/config/0/autogeoipupdate') == 'off' && suricata_cron_job_exists("/usr/local/pkg/suricata/suricata_geoipupdate.php"))
			install_cron_job("/usr/local/pkg/suricata/suricata_geoipupdate.php", FALSE);

		/* create passlist and homenet file, then sync files */
		sync_suricata_package_config();

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

$pglinks = array("", "/suricata/suricata_interfaces.php", "@self");
$pgtitle = array("Services", "Suricata", "Global Settings");
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
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php");
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

$group = new Form_Group('Install ETOpen Emerging Threats rules');
$group->add(new Form_Checkbox(
	'enable_etopen_rules',
	'Install ETOpen Emerging Threats rules',
	'ETOpen is a free open source set of Suricata rules whose coverage is more limited than ETPro.',
	$pconfig['enable_etopen_rules'] == 'on' ? true:false,
	'on'
));
$group->add(new Form_Checkbox(
	'enable_etopen_custom_url',
	'Enable ETOpen Custom Download URL',
	'Use a custom URL for ETOpen downloads',
	$pconfig['enable_etopen_custom_url'] == 'on' ? true:false,
	'on'
));
$group->setHelp('Enabling the custom URL option will force the use of a custom user-supplied URL when downloading ETOpen rules.');
$section->add($group);
$section->addInput(new Form_Input(
	'etopen_custom_rule_url',
	'ETOpen Custom Rule Download URL',
	'text',
	$pconfig['etopen_custom_rule_url']
))->setHelp('You must provide the complete URL including the filename!  The code will assume a matching filename exists at the same URL with an additional extension of ".md5".');

$group = new Form_Group('Install ETPro Emerging Threats rules');
$group->add(new Form_Checkbox(
	'enable_etpro_rules',
	'Install ETPro Emerging Threats rules',
	'ETPro for Suricata offers daily updates and extensive coverage of current malware threats.',
	$pconfig['enable_etpro_rules'] == 'on' ? true:false,
	'on'
));
$group->add(new Form_Checkbox(
	'enable_etpro_custom_url',
	'Enable ETPro Custom Download URL',
	'Use a custom URL for ETPro rule downloads',
	$pconfig['enable_etpro_custom_url'] == 'on' ? true:false,
	'on'
));
$group->setHelp('The ETPro rules contain all of the ETOpen rules, so the ETOpen rules are not required and are disabled when the ETPro rules are selected. ' . 
		'<a href="https://www.proofpoint.com/us/products/et-pro-ruleset">Sign Up for an ETPro Account</a>.  Enabling the custom URL option will force the use of a custom user-supplied URL when downloading ETPro rules.');
$section->add($group);
$section->addInput(new Form_Input(
	'etpro_custom_rule_url',
	'ETPro Custom Rule Download URL',
	'text',
	$pconfig['etpro_custom_rule_url']
))->setHelp('You must provide the complete URL including the filename!  The code will assume a matching filename exists at the same URL with an additional extension of ".md5".');
$section->addInput(new Form_Input(
	'etprocode',
	'ETPro Subscription Configuration Code',
	'text',
	$pconfig['etprocode']
))->setHelp('Obtain an ETPro subscription code and paste it here.');

$group = new Form_Group('Install Snort rules');
$group->add(new Form_Checkbox(
	'enable_vrt_rules',
	'Install Snort rules',
	'Snort free Registered User or paid Subscriber rules',
	$pconfig['enable_vrt_rules'] == 'on' ? true:false,
	'on'
))->setHelp('<a href="https://www.snort.org/users/sign_up">Sign Up for a free Registered User Rules Account</a><br /><a href="https://www.snort.org/products">Sign Up for paid Snort Subscriber Rule Set (by Talos)</a>');
$group->add(new Form_Checkbox(
	'enable_snort_custom_url',
	'Enable Snort Custom Download URL',
	'Use a custom URL for Snort rule downloads',
	$pconfig['enable_snort_custom_url'] == 'on' ? true:false,
	'on'
));
$group->setHelp('Enabling the custom URL option will force the use of a custom user-supplied URL when downloading Snort Subscriber rules.');
$section->add($group);
$section->addInput(new Form_Input(
	'snort_custom_url',
	'Snort Rules Custom Download URL',
	'text',
	$pconfig['snort_custom_url']
))->setHelp('You must provide the complete URL including the filename!  The code will assume a matching filename exists at the same URL with an additional extension of ".md5".');
$section->addInput(new Form_Input(
	'snort_rules_file',
	'Snort Rules Filename',
	'text',
	$pconfig['snort_rules_file']
))->setHelp('Enter the rules tarball filename (filename only, do not include the URL.)<br />Example: snortrules-snapshot-29200.tar.gz<br />DO NOT specify a Snort3 rules file!  Snort3 rules are incompatible with Suricata and will break your installation!');
$section->addInput(new Form_Input(
	'oinkcode',
	'Snort Oinkmaster Code',
	'text',
	$pconfig['oinkcode']
))->setHelp('Obtain a snort.org Oinkmaster code and paste it here.');

$group = new Form_Group('Install Snort GPLv2 Community rules');
$group->add(new Form_Checkbox(
	'snortcommunityrules',
	'Install Snort GPLv2 Community rules',
	'The Snort Community Ruleset is a GPLv2 Talos-certified ruleset that is distributed free of charge without any Snort Subscriber License restrictions.',
	$pconfig['snortcommunityrules'] == 'on' ? true:false,
	'on'
));
$group->add(new Form_Checkbox(
	'enable_gplv2_custom_url',
	'Enable Snort GPLv2 Custom Download URL',
	'Use a custom URL for Snort GPLv2 rule downloads',
	$pconfig['enable_gplv2_custom_url'] == 'on' ? true:false,
	'on'
));
$group->setHelp('This ruleset is updated daily and is a subset of the subscriber ruleset.  If you are a Snort Subscriber Rules customer (paid subscriber), ' .
		'the community ruleset is already built into your download of the Snort Subscriber rules, and there is no benefit in adding this rule set separately.');
$section->add($group);
$section->addInput(new Form_Input(
	'gplv2_custom_url',
	'Snort GPLv2 Custom Rule Download URL',
	'text',
	$pconfig['gplv2_custom_url']
))->setHelp('You must provide the complete URL including the filename!  The code will assume a matching filename exists at the same URL with an additional extension of ".md5".');

$group = new Form_Group('Install Feodo Tracker Botnet C2 IP rules');
$group->add(new Form_Checkbox(
	'enable_feodo_botnet_c2_rules',
	'Install Feodo Tracker Suricata Botnet C2 IP rules',
	'The Feodo Botnet C2 IP Ruleset contains Dridex and Emotet/Heodo botnet command and control servers (C&Cs) tracked by Feodo Tracker.',
	$pconfig['enable_feodo_botnet_c2_rules'] == 'on' ? true:false,
	'on'
));
$section->add($group);

$group = new Form_Group('Install ABUSE.ch SSL Blacklist rules');
$group->add(new Form_Checkbox(
	'enable_abuse_ssl_blacklist_rules',
	'Install ABUSE.ch SSL Blacklist rules',
	'The ABUSE.ch SSL Blacklist Ruleset contains the SSL cert fingerprints of all SSL certs blacklisted by ABUSE.ch.',
	$pconfig['enable_abuse_ssl_blacklist_rules'] == 'on' ? true:false,
	'on'
));
$section->add($group);

$group = new Form_Group('Hide Deprecated Rules Categories');
$group->add(new Form_Checkbox(
	'hide_deprecated_rules',
	'Hide Deprecated Rules Categories',
	'Hide deprecated rules categories in the GUI and remove them from the configuration. Default is Not Checked.',
	$pconfig['hide_deprecated_rules'] == 'on' ? true:false,
	'on'
));
$section->add($group);

$section->addInput(new Form_Checkbox(
	'enable_extra_rules',
	'Download Extra Rules',
	'Download Extra Rules',
	$pconfig['enable_extra_rules'] == 'on' ? true:false,
	'on'
))->setHelp('Download extra rules file or tar.gz archive with rules. If "Check MD5" is set, the code will assume a matching filename exists at the same URL with an additional extension of ".md5".');

$form->add($section);

$section = new Form_Section('Extra rules');
$section->addClass('extra_rules');

if (!$pconfig['extra_rules']) {
	$pconfig['extra_rules'] = array();
	$pconfig['extra_rules']['rule']  = array(array('name' => '', 'url' => '', 'md5' => false));
}

$numrows = count($item) -1;
$counter = 0;

$numrows = count($pconfig['extra_rules']['rule']) -1;

foreach ($pconfig['extra_rules']['rule'] as $rule) {
	$group = new Form_Group(($counter == 0) ? 'Rule':null);
	$group->addClass('repeatable');

	$group->add(new Form_Input(
		'name' . $counter,
		'Name',
		'text',
		$rule['name']
	))->setWidth(2)->setHelp($numrows == $counter ? 'Name':null);

	$group->add(new Form_Input(
		'url' . $counter,
		'URL',
		'text',
		$rule['url']
	))->setWidth(5)->setHelp($numrows == $counter ? 'URL':null);

	$group->add(new Form_Checkbox(
		'md5' . $counter,
		'MD5',
		null,
		$rule['md5'] == 'on' ? true : false,
	))->setHelp($numrows == $counter ? 'Check MD5':null);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-solid fa-trash-can'
	))->addClass('btn-warning');

	$section->add($group);

	$counter++;
}

$section->addInput(new Form_Button(
	'addrow',
	'Add',
	null,
	'fa-solid fa-plus'
))->addClass('btn-success');

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
))->setHelp('Enter the rule update start time in 24-hour format (HH:MM).  Default is 00 hours with a randomly chosen minutes value.  ' . 
			'Rules will update at the interval chosen above starting at the time specified here. ' . 
			'For example, using a start time of 00:08 and choosing 12 Hours for the interval, ' . 
			'the rules will update at 00:08 and 12:08 each day. The randomized minutes value should ' . 
			'be retained to minimize the impact to the rules update site from large numbers of simultaneous requests.');
$section->addInput(new Form_Checkbox(
	'live_swap_updates',
	'Live Rule Swap on Update',
	'Enable "Live Swap" reload of rules after downloading an update. Default is Not Checked',
	$pconfig['live_swap_updates'] == 'on' ? true:false,
	'on'
))->setHelp('When enabled, Suricata will perform a live load of the new rules following an update instead of a hard restart. If issues are encountered with live load, uncheck this option to perform a hard restart of all Suricata instances following an update.');
$section->addInput(new Form_Checkbox(
	'autogeoipupdate',
	'GeoLite2 DB Update',
	'Enable downloading of free GeoLite2 Country IP Database updates. Default is Not Checked',
	$pconfig['autogeoipupdate'] == 'on' ? true:false,
	'on'
))->setHelp('When enabled, Suricata will automatically download updates for the free GeoLite2 country IP database.<br /><br />If you have a subscription for more current GeoIP2 updates, uncheck this option and instead create your own process to place the required database file in /usr/local/share/suricata/GeoLite2/.');
$section->addInput(new Form_Input(
	'maxmind_geoipdb_uid',
	gettext('GeoLite2 DB Account ID'),
	'text',
	$pconfig['maxmind_geoipdb_uid'],
	['placeholder' => 'Enter your MaxMind GeoLite2 Account ID']
))->setHelp('To utilize the free MaxMind GeoLite2 GeoIP functionality, you must <a href="https://www.maxmind.com/en/geolite2/signup" target="_blank">register for a free MaxMind user account</a>. '
	. '<strong>Use the GeoIP Update version 3.1.1 or newer registration option.</strong>')
  ->setAttribute('autocomplete', 'off');
$section->addInput(new Form_Input(
	'maxmind_geoipdb_key',
	gettext('GeoLite2 DB License Key'),
	'text',
	$pconfig['maxmind_geoipdb_key'],
	['placeholder' => 'Enter your MaxMind GeoLite2 License Key']
))->setHelp('To utilize the free MaxMind GeoLite2 GeoIP functionality, you must <a href="https://www.maxmind.com/en/geolite2/signup" target="_blank">register for a free MaxMind user account</a>. '
	. '<strong>Use the GeoIP Update version 3.1.1 or newer registration option.</strong>')
  ->setAttribute('autocomplete', 'off');
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
))->setHelp('Please select the amount of time you would like hosts to be blocked.  Note this setting is only applicable when using Legacy Mode blocking!  This setting is ignored when using Inline IPS Mode.<br /><br />Hint: in most cases, 1 hour is a good choice.');
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

$section->addInput(new Form_Select(
	'log_to_systemlog_priority',
	'Log Priority',
	$pconfig['log_to_systemlog_priority'],
	array( "debug" => "DEBUG", "config" => "CONF", "perf" => "PERF", "error" => "ERR", "warning" => "WARNING", "notice" => "NOTICE", "info" => "INFO" )
))->setHelp('Select system log Priority (Level) to use for reporting. Default is NOTICE.');

$section->addInput(new Form_Checkbox(
	'forcekeepsettings',
	'Keep Suricata Settings After Deinstall',
	'Settings will not be removed during package deinstallation.',
	$pconfig['forcekeepsettings'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'clearblocks',
	'Clear Blocked Hosts After Deinstall',
	'Click to clear all blocked hosts added by Suricata when removing the package.  Default is checked.',
	$pconfig['clearblocks'] == 'on' ? true:false,
	'on'
));
$form->add($section);

$section = new Form_Section('Notifications');

$section->addInput(new Form_StaticText(
	null,
	'E-Mail/Telegram/Pushover notifications. Delivery settings are configured under System -> Advanced, ' .
        'on the Notifications tab.',
));

$section->addInput(new Form_Checkbox(
	'update_notify',
	'Update',
	'Rules, GeoIP and IQRisk update notifications.',
	$pconfig['update_notify'] == 'on' ? true:false,
	'off'
));

$section->addInput(new Form_Checkbox(
	'rule_categories_notify',
	'Rule Categories',
	'Send notifications when new rule categories appear.',
	$pconfig['rule_categories_notify'] == 'on' ? true:false,
	'off'
));

$form->add($section);

print $form;
?>
</div>

<div class="infoblock">
	<?=print_info_box('<strong>Note:</strong> Changing any settings on this page will affect all Suricata-configured interfaces.', 'info')?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

	function enable_snort_vrt() {
		var hide = ! $('#enable_vrt_rules').prop('checked');
		hideInput('snort_rules_file', hide);
		hideInput('oinkcode', hide);
		$('#enable_snort_custom_url').prop('disabled', hide);
		if (!hide && $('#enable_snort_custom_url').prop('checked')) {
			hideInput('snort_custom_url', false);
		}
		else {
			hideInput('snort_custom_url', true);
		}
	}

	function enable_et_rules() {
		var hide = $('#enable_etopen_rules').prop('checked');
		$('#enable_etopen_custom_url').prop('disabled', !hide);
		hideInput('etprocode', true);
		if (hide && $('#enable_etopen_custom_url').prop('checked')) {
			hideInput('etopen_custom_rule_url', false);
		}
		else {
			hideInput('etopen_custom_rule_url', true);
		}
		if (hide && $('#enable_etpro_rules').prop('checked')) {
			hideInput('etprocode', hide);
			hideInput('etpro_custom_rule_url', hide);
			$('#enable_etpro_rules').prop('checked', false);
			$('#enable_etpro_custom_url').prop('disabled', hide);
		}
	}

	function enable_etpro_rules() {
		var hide = ! $('#enable_etpro_rules').prop('checked');
		$('#enable_etpro_custom_url').prop('disabled', hide);
		if (!hide && $('#enable_etpro_custom_url').prop('checked')) {
			hideInput('etpro_custom_rule_url', false);
			hideInput('etprocode', true);
		}
		else {
			hideInput('etpro_custom_rule_url', true);
			hideInput('etprocode', hide);

		}
		if (!hide && $('#enable_etopen_rules').prop('checked')) {
			$('#enable_etopen_rules').prop('checked', false);
			$('#enable_etopen_custom_url').prop('disabled', !hide);
			hideInput('etopen_custom_rule_url', !hide);
			hideInput('etprocode', false);
		}
	}

	function enable_gplv2_rules() {
		var hide = ! $('#snortcommunityrules').prop('checked');
		$('#enable_gplv2_custom_url').prop('disabled', hide);
		if (!hide && $('#enable_gplv2_custom_url').prop('checked')) {
			hideInput('gplv2_custom_url', false);
		}
		else {
			hideInput('gplv2_custom_url', true);
		}
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
		hideInput('log_to_systemlog_priority', hide);
	}

	function enable_geoip2_upd() {
		var hide = ! $('#autogeoipupdate').prop('checked');
		hideInput('maxmind_geoipdb_uid', hide);
		hideInput('maxmind_geoipdb_key', hide);
	}

	function show_extrarules() {
		hide = !$('#enable_extra_rules').prop('checked');
		hideClass('extra_rules', hide);
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------
	// When 'enable_vrt_rules' is clicked, toggle the Oinkmaster text control
	$('#enable_vrt_rules').click(function() {
		enable_snort_vrt();
	});

	// When 'enable_snort_custom_url' is clicked, toggle the custom URL control
	$('#enable_snort_custom_url').click(function() {
		var hide = ! $('#enable_snort_custom_url').prop('checked');
		hideInput('snort_custom_url', hide);
	});

	// When 'enable_etopen_rules' is clicked, uncheck ETPro and hide the ETPro Code text control
	$('#enable_etopen_rules').click(function() {
		enable_et_rules();
	});

	// When 'enable_etopen_custom_url' is clicked, toggle the custom URL control
	$('#enable_etopen_custom_url').click(function() {
		var hide = ! $('#enable_etopen_custom_url').prop('checked');
		hideInput('etopen_custom_rule_url', hide);
	});

	// When 'enable_etpro_rules' is clicked, uncheck ET Open checkbox control and show code
	$('#enable_etpro_rules').click(function() {
		enable_etpro_rules();
	});

	// When 'enable_etpro_custom_url' is clicked, toggle the custom URL control
	$('#enable_etpro_custom_url').click(function() {
		var hide = ! $('#enable_etpro_custom_url').prop('checked');
		hideInput('etpro_custom_rule_url', hide);
		hideInput('etprocode', !hide);
	});

	// When 'snortcommunityrules' is clicked, toggle the custom URL control
	$('#snortcommunityrules').click(function() {
		enable_gplv2_rules();
	});

	// When 'enable_gplv2_custom_url' is clicked, toggle the custom URL control
	$('#enable_gplv2_custom_url').click(function() {
		var hide = ! $('#enable_gplv2_custom_url').prop('checked');
		hideInput('gplv2_custom_url', hide);
	});

	// When 'autoruleupdate' is set to never, disable 'autoruleupdatetime'
	$('#autoruleupdate').on('change', function() {
		enable_change_rules_upd(this.selectedIndex);
	});

	// When 'log_to_systemlog' is clicked, toggle 'log_to_systemlog_facility'
	$('#log_to_systemlog').click(function() {
		toggle_log_to_systemlog();
	});

	// When 'autogeoipupdate' is clicked, toggle 'maxmind_geoipdb_key'
	$('#autogeoipupdate').click(function() {
		enable_geoip2_upd();
	});

	// When 'enable_extra_rules' is clicked, show 'extra_rules' list
	$('#enable_extra_rules').click(function () {
		show_extrarules();
	});

	// ---------- On initial page load ------------------------------------------------------------
	enable_snort_vrt();
	enable_et_rules();
	enable_etpro_rules();
	enable_gplv2_rules();
	enable_geoip2_upd();
	enable_change_rules_upd($('#autoruleupdate').prop('selectedIndex'));
	toggle_log_to_systemlog();
	show_extrarules();
	checkLastRow();

});
//]]>
</script>

<?php
include("foot.inc");
?>
