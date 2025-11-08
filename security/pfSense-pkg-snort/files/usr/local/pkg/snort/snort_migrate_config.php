<?php
/*
 * snort_migrate_config.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2019-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2022 Bill Meeks
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

require_once("config.inc");
require_once("functions.inc");

/****************************************************************************/
/* The code in this module is called once during the post-install process   */
/* via an "include" line.  It is used to perform a one-time migration of    */
/* Snort preprocessor configuration parameters into the new format used     */
/* by the multi-engine config feature.  Configuration parameters for the    */
/* multiple configuration engines of some preprocessors are stored as       */
/* array values within the "config.xml" file in the [snortglobals] section. */
/****************************************************************************/

// Just exit if this is a clean install with no saved settings
if (count(config_get_path('installedpackages/snortglobal/rule', [])) < 1)
	return;

/****************************************************************************/
/* Loop through all the <rule> elements in the Snort configuration and      */
/* migrate the relevant preprocessor parameters to the new format.          */
/****************************************************************************/

$updated_cfg = false;
logger(LOG_NOTICE, localize_text("Checking configuration settings version..."), LOG_PREFIX_PKG_SNORT);

// Check the configuration version to see if XMLRPC Sync should
// auto-disabled as part of the upgrade due to config format changes.
if (empty(config_get_path('installedpackages/snortglobal/snort_config_ver')) &&
    (config_get_path('installedpackages/snortsync/config/varsynconchanges') == 'auto' ||
     config_get_path('installedpackages/snortsync/config/varsynconchanges') == 'manual')) {
	config_set_path('installedpackages/snortsync/config/varsynconchanges',	'disabled');
	logger(LOG_NOTICE, localize_text("Turning off Snort Sync on this host due to configuration format changes in this update.  Upgrade all Snort Sync targets to this same Snort package version before re-enabling Snort Sync."), LOG_PREFIX_PKG_SNORT);
	$updated_cfg = true;
}

/**********************************************************/
/* Create new Auto SID Mgmt settings if not set           */
/**********************************************************/
if (empty(config_get_path('installedpackages/snortglobal/auto_manage_sids'))) {
	config_set_path('installedpackages/snortglobal/auto_manage_sids', 'off');
	$updated_cfg = true;
}

/**********************************************************/
/* Create new LOG MGMT settings if not set                */
/**********************************************************/
if (empty(config_get_path('installedpackages/snortglobal/enable_log_mgmt'))) {
	config_set_path('installedpackages/snortglobal/enable_log_mgmt', 'on');
	config_set_path('installedpackages/snortglobal/alert_log_limit_size', "500");
	config_set_path('installedpackages/snortglobal/alert_log_retention', "336");
	config_set_path('installedpackages/snortglobal/appid_alerts_log_retention', "336");
	config_set_path('installedpackages/snortglobal/appid_alerts_log_limit_size', "500");
	config_set_path('installedpackages/snortglobal/appid_stats_log_limit_size', "1000");
	config_set_path('installedpackages/snortglobal/appid_stats_log_retention', "168");
	config_set_path('installedpackages/snortglobal/event_pkts_log_limit_size', "0");
	config_set_path('installedpackages/snortglobal/event_pkts_log_retention', "336");
	config_set_path('installedpackages/snortglobal/sid_changes_log_limit_size', "250");
	config_set_path('installedpackages/snortglobal/sid_changes_log_retention', "336");
	config_set_path('installedpackages/snortglobal/stats_log_limit_size', "500");
	config_set_path('installedpackages/snortglobal/stats_log_retention', "168");
	$updated_cfg = true;
}
if (empty(config_get_path('installedpackages/snortglobal/appid_stats_log_limit_size'))) {
	config_set_path('installedpackages/snortglobal/appid_stats_log_limit_size', '1000');
	$updated_cfg = true;
}
if (empty(config_get_path('installedpackages/snortglobal/appid_stats_log_retention'))) {
	config_set_path('installedpackages/snortglobal/appid_stats_log_retention', '168');
	$updated_cfg = true;
}
if (empty(config_get_path('installedpackages/snortglobal/appid_alerts_log_limit_size'))) {
	config_set_path('installedpackages/snortglobal/appid_alerts_log_limit_size', '500');
	$updated_cfg = true;
}
if (empty(config_get_path('installedpackages/snortglobal/appid_alerts_log_retention'))) {
	config_set_path('installedpackages/snortglobal/appid_alerts_log_retention', '336');
	$updated_cfg = true;
}

/**********************************************************/
/* Create new VERBOSE_LOGGING setting if not set          */
/**********************************************************/
if (empty(config_get_path('installedpackages/snortglobal/verbose_logging'))) {
	config_set_path('installedpackages/snortglobal/verbose_logging', 'off');
	$updated_cfg = true;
}

/**********************************************************/
/* Create new OpenAppID settings if not set               */
/**********************************************************/
if (empty(config_get_path('installedpackages/snortglobal/openappid_detectors'))) {
	config_set_path('installedpackages/snortglobal/openappid_detectors', 'off');
	$updated_cfg = true;
}
if (empty(config_get_path('installedpackages/snortglobal/openappid_rules_detectors'))) {
        config_set_path('installedpackages/snortglobal/openappid_rules_detectors', 'off');
        $updated_cfg = true;
}

/**********************************************************/
/* Create new HIDE_DEPRECATED_RULES setting if not set    */
/**********************************************************/
if (empty(config_get_path('installedpackages/snortglobal/hide_deprecated_rules'))) {
	config_set_path('installedpackages/snortglobal/hide_deprecated_rules', 'off');
	$updated_cfg = true;
}

/**********************************************************/
/* Migrate content of any existing SID Mgmt files in the  */
/* /var/db/snort/sidmods directory to Base64 encoded      */
/* strings in SID_MGMT_LIST array in config.xml.          */
/**********************************************************/
if (!config_path_enabled('installedpackages/snortglobal', 'sid_list_migration') && count(config_get_path('installedpackages/snortglobal/sid_mgmt_lists', [])) < 1) {
	$a_list = config_get_path('installedpackages/snortglobal/sid_mgmt_lists/item', []);
	$sidmodfiles = return_dir_as_array("/var/db/snort/sidmods/");
	foreach ($sidmodfiles as $sidfile) {
		$data = file_get_contents("/var/db/snort/sidmods/" . $sidfile);
		if ($data !== FALSE) {
			$tmp = array();
			$tmp['name'] = basename($sidfile);
			$tmp['modtime'] = filemtime("/var/db/snort/sidmods/" . $sidfile);
			$tmp['content'] = base64_encode($data);
			$a_list[] = $tmp;
		}
	}

	// Write back original array plus any additions from above
	config_set_path('installedpackages/snortglobal/sid_mgmt_lists/item', $a_list);

	// Set a flag to show one-time migration is completed.
	// We can increment this flag in later versions if we
	// need to import additional files as SID_MGMT_LISTS.
	config_set_path('installedpackages/snortglobal/sid_list_migration', '2');
	$updated_cfg = true;
	unset($a_list);
}
elseif (config_get_path('installedpackages/snortglobal/sid_list_migration', '0') < '2') {

	// Import dropsid-sample.conf and rejectsid-sample.conf
	// files if missing from the SID_MGMT_LIST array.
	$sidmodfiles = array( "dropsid-sample.conf", "rejectsid-sample.conf" );
	$a_list = config_get_path('installedpackages/snortglobal/sid_mgmt_lists/item', []);
	foreach ($sidmodfiles as $sidfile) {
		if (!in_array($sidfile, $a_list)) {
			$data = file_get_contents("/var/db/snort/sidmods/" . $sidfile);
			if ($data !== FALSE) {
				$tmp = array();
				$tmp['name'] = basename($sidfile);
				$tmp['modtime'] = filemtime("/var/db/snort/sidmods/" . $sidfile);
				$tmp['content'] = base64_encode($data);
				$a_list[] = $tmp;
			}
		}		
	}

	// Write back original array plus any additions from above
	config_set_path('installedpackages/snortglobal/sid_mgmt_lists/item', $a_list);

	// Set a flag to show this one-time migration is completed
	config_set_path('installedpackages/snortglobal/sid_list_migration', '2');
	$updated_cfg = true;
	unset($a_list);
}

/**********************************************************/
/* Remove the two deprecated Rules Update Status fields   */
/* from the package configuration. The status is now      */
/* stored in a local file.                                */
/**********************************************************/
if (config_path_enabled('installedpackages/snortglobal', 'last_rule_upd_status')) {
	config_del_path('installedpackages/snortglobal/last_rule_upd_status');
	$updated_cfg = true;
}
if (config_path_enabled('installedpackages/snortglobal', 'last_rule_upd_time')) {
	config_del_path('installedpackages/snortglobal/last_rule_upd_time');
	$updated_cfg = true;
}

/**********************************************************/
/* Randomize the Rules Update Start Time minutes field    */
/* per request of Snort.org team to minimize impact of    */
/* large numbers of pfSense users hitting Snort.org at    */
/* the same minute past the hour for rules updates.       */
/**********************************************************/
if (config_get_path('installedpackages/snortglobal/rule_update_starttime', '00:05') == '00:05' ||
    strlen(config_get_path('installedpackages/snortglobal/rule_update_starttime')) < 5 ) {
	config_set_path('installedpackages/snortglobal/rule_update_starttime', "00:" . str_pad(strval(random_int(0,59)), 2, "00", STR_PAD_LEFT));
	$updated_cfg = true;
}

/**********************************************************/
/* Add new multiple alias & custom IP assignment feature  */
/* for Pass Lists by converting existing <address>        */
/* element for existing entries into an array. Migrate    */
/* any existing <address> to the new array structure.     */
/**********************************************************/
if (config_get_path('installedpackages/snortglobal/whitelist/item')) {
	$a_wlist = config_get_path('installedpackages/snortglobal/whitelist/item', []);
	foreach ($a_wlist as &$wlisti) {
		if (!array_get_path($wlisti, 'address/item') && !empty($wlisti['address'])) {
			$tmp = $wlisti['address'];
			$wlisti['address'] = array();
			$wlisti['address']['item'] = array();
			$wlisti['address']['item'][] = $tmp;
			$updated_cfg = true;
		}
	}

	// Store updated whitelist array in configuration
	config_set_path('installedpackages/snortglobal/whitelist/item', $a_wlist);
}

/**********************************************************/
/* Migrate per interface settings if required.            */
/**********************************************************/
$a_rules = config_get_path('installedpackages/snortglobal/rule', []);
foreach ($a_rules as &$rule) {

	// Initialize multiple config engine arrays for supported preprocessors
	array_init_path($rule, 'frag3_engine/item');
	array_init_path($rule, 'ftp_client_engine/item');
	array_init_path($rule, 'ftp_server_engine/item');
	array_init_path($rule, 'http_inspect_engine/item');
	array_init_path($rule, 'stream5_tcp_engine/item');
	array_init_path($rule, 'arp_spoof_engine/item');

	// Create a default "frag3_engine" if none are configured
	if (empty($rule['frag3_engine']['item'])) {
		$updated_cfg = true;
		logger(LOG_NOTICE, localize_text("Migrating Frag3 Engine configuration for interface %s...", $rule['descr']), LOG_PREFIX_PKG_SNORT);
		$default = array( "name" => "default", "bind_to" => "all", "policy" => "bsd", 
				"timeout" => 60, "min_ttl" => 1, "detect_anomalies" => "on", 
				"overlap_limit" => 0, "min_frag_len" => 0 );

		// Ensure sensible default values exist for global Frag3 parameters
		if (empty($rule['frag3_max_frags']))
			$rule['frag3_max_frags'] = '8192';
		if (empty($rule['frag3_memcap']))
			$rule['frag3_memcap'] = '4194304';
		if (empty($rule['frag3_detection']))
			$rule['frag3_detection'] = 'on';

		// Put any old values in new default engine and remove old value
		if (isset($rule['frag3_policy']))
			$default['policy'] = $rule['frag3_policy'];
		unset($rule['frag3_policy']);
		if (isset($rule['frag3_timeout']) && is_numeric($rule['frag3_timeout']))
			$default['timeout'] = $rule['frag3_timeout'];
		unset($rule['frag3_timeout']);
		if (isset($rule['frag3_overlap_limit']) && is_numeric($rule['frag3_overlap_limit']))
			$default['overlap_limit'] = $rule['frag3_overlap_limit'];
		unset($rule['frag3_overlap_limit']);
		if (isset($rule['frag3_min_frag_len']) && is_numeric($rule['frag3_min_frag_len']))
			$default['min_frag_len'] = $rule['frag3_min_frag_len'];
		unset($rule['frag3_min_frag_len']);

		$rule['frag3_engine']['item'] = array();
		$rule['frag3_engine']['item'][] = $default;
	}

	// Create a default Stream5 engine array if none are configured
	if (empty($rule['stream5_tcp_engine']['item'])) {
		$updated_cfg = true;
		logger(LOG_NOTICE, localize_text("Migrating Stream5 Engine configuration for interface %s...", $rule['descr']), LOG_PREFIX_PKG_SNORT);
		$default = array( "name" => "default", "bind_to" => "all", "policy" => "bsd", "timeout" => 30, 
				"max_queued_bytes" => 1048576, "detect_anomalies" => "off", "overlap_limit" => 0, 
				"max_queued_segs" => 2621, "require_3whs" => "off", "startup_3whs_timeout" => 0, 
				"no_reassemble_async" => "off", "max_window" => 0, "use_static_footprint_sizes" => "off", 
				"check_session_hijacking" => "off", "dont_store_lg_pkts" => "off", "ports_client" => "default", 
				"ports_both" => "default", "ports_server" => "none" );

		// Ensure sensible defaults exist for Stream5 global parameters
		if (empty($rule['stream5_reassembly']))
			$rule['stream5_reassembly'] = 'on';
		if (empty($rule['stream5_flush_on_alert']))
			$rule['stream5_flush_on_alert'] = 'off';
		if (empty($rule['stream5_prune_log_max']))
			$rule['stream5_prune_log_max'] = '1048576';
		if (empty($rule['stream5_track_tcp']))
			$rule['stream5_track_tcp'] = 'on';
		if (empty($rule['stream5_max_tcp']))
			$rule['stream5_max_tcp'] = '262144';
		if (empty($rule['stream5_track_udp']))
			$rule['stream5_track_udp'] = 'on';
		if (empty($rule['stream5_max_udp']))
			$rule['stream5_max_udp'] = '131072';
		if (empty($rule['stream5_udp_timeout']))
			$rule['stream5_udp_timeout'] = '30';
		if (empty($rule['stream5_track_icmp']))
			$rule['stream5_track_icmp'] = 'off';
		if (empty($rule['stream5_max_icmp']))
			$rule['stream5_max_icmp'] = '65536';
		if (empty($rule['stream5_icmp_timeout']))
			$rule['stream5_icmp_timeout'] = '30';
		if (empty($rule['stream5_mem_cap']))
			$rule['stream5_mem_cap']= '8388608';

		// Put any old values in new default engine and remove old value
		if (isset($rule['stream5_policy']))
			$default['policy'] = $rule['stream5_policy'];
		unset($rule['stream5_policy']);
		if (isset($rule['stream5_tcp_timeout']) && is_numeric($rule['stream5_tcp_timeout']))
			$default['timeout'] = $rule['stream5_tcp_timeout'];
		unset($rule['stream5_tcp_timeout']);
		if (isset($rule['stream5_overlap_limit']) && is_numeric($rule['stream5_overlap_limit']))
			$default['overlap_limit'] = $rule['stream5_overlap_limit'];
		unset($rule['stream5_overlap_limit']);
		if (isset($rule['stream5_require_3whs']))
			$default['require_3whs'] = $rule['stream5_require_3whs'];
		unset($rule['stream5_require_3whs']);
		if (isset($rule['stream5_no_reassemble_async']))
			$default['no_reassemble_async'] = $rule['stream5_no_reassemble_async'];
		unset($rule['stream5_no_reassemble_async']);
		if (isset($rule['stream5_dont_store_lg_pkts']))
			$default['dont_store_lg_pkts'] = $rule['stream5_dont_store_lg_pkts'];
		unset($rule['stream5_dont_store_lg_pkts']);
		if (isset($rule['max_queued_bytes']) && is_numeric($rule['max_queued_bytes']))
			$default['max_queued_bytes'] = $rule['max_queued_bytes'];
		unset($rule['max_queued_bytes']);
		if (isset($rule['max_queued_segs']) && is_numeric($rule['max_queued_segs']))
			$default['max_queued_segs'] = $rule['max_queued_segs'];
		unset($rule['max_queued_segs']);

		$rule['stream5_tcp_engine']['item'] = array();
		$rule['stream5_tcp_engine']['item'][] = $default;
	}

	// Create a default HTTP_INSPECT engine if none are configured
	if (empty($rule['http_inspect_engine']['item'])) {
		$updated_cfg = true;
		logger(LOG_NOTICE, localize_text("Migrating HTTP_Inspect Engine configuration for interface %s...", $rule['descr']), LOG_PREFIX_PKG_SNORT);
		$default = array( "name" => "default", "bind_to" => "all", "server_profile" => "all", "enable_xff" => "off", 
				"log_uri" => "off", "log_hostname" => "off", "server_flow_depth" => 65535, "enable_cookie" => "on", 
				"client_flow_depth" => 1460, "extended_response_inspection" => "on", "no_alerts" => "off", 
				"unlimited_decompress" => "on", "inspect_gzip" => "on", "normalize_cookies" =>"on", 
				"normalize_headers" => "on", "normalize_utf" => "on", "normalize_javascript" => "on", 
				"allow_proxy_use" => "off", "inspect_uri_only" => "off", "max_javascript_whitespaces" => 200,
				"post_depth" => -1, "max_headers" => 0, "max_spaces" => 0, "max_header_length" => 0, "ports" => "default",
				"decompress_swf" => "off", "decompress_pdf" => "off" );

		// Ensure sensible default values exist for global HTTP_INSPECT parameters
		if (empty($rule['http_inspect']))
			$rule['http_inspect'] = "on";
		if (empty($rule['http_inspect_proxy_alert']))
			$rule['http_inspect_proxy_alert'] = "off";
		if (empty($rule['http_inspect_memcap']))
			$rule['http_inspect_memcap'] = "150994944";
		if (empty($rule['http_inspect_max_gzip_mem']))
			$rule['http_inspect_max_gzip_mem'] = "838860";

		// Put any old values in new default engine and remove old value
		if (isset($rule['server_flow_depth']) && is_numeric($rule['server_flow_depth']))
			$default['server_flow_depth'] = $rule['server_flow_depth'];
		unset($rule['server_flow_depth']);
		if (isset($rule['client_flow_depth']) & is_numeric($rule['client_flow_depth']))
			$default['client_flow_depth'] = $rule['client_flow_depth'];
		unset($rule['client_flow_depth']);
		if (isset($rule['http_server_profile']))
			$default['server_profile'] = $rule['http_server_profile'];
		unset($rule['http_server_profile']);
		if (isset($rule['http_inspect_enable_xff']))
			$default['enable_xff'] = $rule['http_inspect_enable_xff'];
		unset($rule['http_inspect_enable_xff']);
		if (isset($rule['http_inspect_log_uri']))
			$default['log_uri'] = $rule['http_inspect_log_uri'];
		unset($rule['http_inspect_log_uri']);
		if (isset($rule['http_inspect_log_hostname']))
			$default['log_hostname'] = $rule['http_inspect_log_hostname'];
		unset($rule['http_inspect_log_hostname']);
		if (isset($rule['noalert_http_inspect']))
			$default['no_alerts'] = $rule['noalert_http_inspect'];
		unset($rule['noalert_http_inspect']);

		$rule['http_inspect_engine']['item'] = array();
		$rule['http_inspect_engine']['item'][] = $default;
	}

	// Create a default FTP_CLIENT engine if none are configured
	if (empty($rule['ftp_client_engine']['item'])) {
		$updated_cfg = true;
		logger(LOG_NOTICE, localize_text("Migrating FTP Client Engine configuration for interface %s...", $rule['descr']), LOG_PREFIX_PKG_SNORT);
		$default = array( "name" => "default", "bind_to" => "all", "max_resp_len" => 256, 
				  "telnet_cmds" => "no", "ignore_telnet_erase_cmds" => "yes", 
				  "bounce" => "yes", "bounce_to_net" => "", "bounce_to_port" => "" );

		// Set defaults for new FTP_Telnet preprocessor configurable parameters
		if (empty($rule['ftp_telnet_inspection_type']))
			$rule['ftp_telnet_inspection_type'] = 'stateful';
		if (empty($rule['ftp_telnet_alert_encrypted']))
			$rule['ftp_telnet_alert_encrypted'] = 'off';
		if (empty($rule['ftp_telnet_check_encrypted']))
			$rule['ftp_telnet_check_encrypted'] = 'on';
		if (empty($rule['ftp_telnet_normalize']))
			$rule['ftp_telnet_normalize'] = 'on';
		if (empty($rule['ftp_telnet_detect_anomalies']))
			$rule['ftp_telnet_detect_anomalies'] = 'on';
		if (empty($rule['ftp_telnet_ayt_attack_threshold']))
			$rule['ftp_telnet_ayt_attack_threshold'] = '20';

		// Add new FTP_Telnet Client default engine
		$rule['ftp_client_engine']['item'] = array();
		$rule['ftp_client_engine']['item'][] = $default;
	}

	// Create a default FTP_SERVER engine if none are configured
	if (empty($rule['ftp_server_engine']['item'])) {
		$updated_cfg = true;
		logger(LOG_NOTICE, localize_text("Migrating FTP Server Engine configuration for interface %s...", $rule['descr']), LOG_PREFIX_PKG_SNORT);
		$default = array( "name" => "default", "bind_to" => "all", "ports" => "default", 
				  "telnet_cmds" => "no", "ignore_telnet_erase_cmds" => "yes", 
				  "ignore_data_chan" => "no", "def_max_param_len" => 100 );

		// Add new FTP_Telnet Server default engine
		$rule['ftp_server_engine']['item'] = array();
		$rule['ftp_server_engine']['item'][] = $default;
	}

	// Set sensible defaults for new SDF options if SDF is enabled
	if ($rule['sensitive_data'] == 'on') {
		if (empty($rule['sdf_alert_threshold'])) {
			$rule['sdf_alert_threshold'] = 25;
			$updated_cfg = true;
		}
		if (empty($rule['sdf_alert_data_type'])) {
			$rule['sdf_alert_data_type'] = "Credit Card,Email Addresses,U.S. Phone Numbers,U.S. Social Security Numbers";
			$updated_cfg = true;
		}
	}

	// Change any ENABLE_SID settings to new format of GID:SID
	if (!empty($rule['rule_sid_on'])) {
		$tmp = explode("||", $rule['rule_sid_on']);
		$new_tmp = "";
		foreach ($tmp as $v) {
			if (strpos($v, ":") === false) {
				if (preg_match('/(\d+)/', $v, $match))
					$new_tmp .= "1:{$match[1]}||";
			}
		}
		$new_tmp = rtrim($new_tmp, " ||");
		if (!empty($new_tmp)) {
			$rule['rule_sid_on'] = $new_tmp;
			$updated_cfg = true;
		}
	}

	// Change any DISABLE_SID settings to new format of GID:SID
	if (!empty($rule['rule_sid_off'])) {
		$tmp = explode("||", $rule['rule_sid_off']);
		$new_tmp = "";
		foreach ($tmp as $v) {
			if (strpos($v, ":") === false) {
				if (preg_match('/(\d+)/', $v, $match))
					$new_tmp .= "1:{$match[1]}||";
			}
		}
		$new_tmp = rtrim($new_tmp, " ||");
		if (!empty($new_tmp)) {
			$rule['rule_sid_off'] = $new_tmp;
			$updated_cfg = true;
		}
	}

	// Migrate new POP3 preprocessor parameter settings
	if (empty($rule['pop_memcap'])) {
		$rule['pop_memcap'] = "838860";
		$updated_cfg = true;
	}
	if (empty($rule['pop_b64_decode_depth']) && $rule['pop_b64_decode_depth'] != '0') {
		$rule['pop_b64_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($rule['pop_qp_decode_depth']) && $rule['pop_qp_decode_depth'] != '0') {
		$rule['pop_qp_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($rule['pop_bitenc_decode_depth']) && $rule['pop_bitenc_decode_depth'] != '0') {
		$rule['pop_bitenc_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($rule['pop_uu_decode_depth']) && $rule['pop_uu_decode_depth'] != '0') {
		$rule['pop_uu_decode_depth'] = "0";
		$updated_cfg = true;
	}

	// Migrate new IMAP preprocessor parameter settings
	if (empty($rule['imap_memcap'])) {
		$rule['imap_memcap'] = "838860";
		$updated_cfg = true;
	}
	if (empty($rule['imap_b64_decode_depth']) && $rule['imap_b64_decode_depth'] != '0') {
		$rule['imap_b64_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($rule['imap_qp_decode_depth']) && $rule['imap_qp_decode_depth'] != '0') {
		$rule['imap_qp_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($rule['imap_bitenc_decode_depth']) && $rule['imap_bitenc_decode_depth'] != '0') {
		$rule['imap_bitenc_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($rule['imap_uu_decode_depth']) && $rule['imap_uu_decode_depth'] != '0') {
		$rule['imap_uu_decode_depth'] = "0";
		$updated_cfg = true;
	}

	// Migrate new SMTP preprocessor parameter settings
	if (empty($rule['smtp_memcap'])) {
		$rule['smtp_memcap'] = "838860";
		$updated_cfg = true;
	}
	if (empty($rule['smtp_max_mime_mem'])) {
		$rule['smtp_max_mime_mem'] = "838860";
		$updated_cfg = true;
	}
	if (empty($rule['smtp_b64_decode_depth']) && $rule['smtp_b64_decode_depth'] != "0") {
		$rule['smtp_b64_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($rule['smtp_qp_decode_depth']) && $rule['smtp_qp_decode_depth'] != "0") {
		$rule['smtp_qp_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($rule['smtp_bitenc_decode_depth']) && $rule['smtp_bitenc_decode_depth'] != "0") {
		$rule['smtp_bitenc_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($rule['smtp_uu_decode_depth']) && $rule['smtp_uu_decode_depth'] != "0") {
		$rule['smtp_uu_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($rule['smtp_email_hdrs_log_depth'])) {
		$rule['smtp_email_hdrs_log_depth'] = "1464";
		$updated_cfg = true;
	}
	if (empty($rule['smtp_ignore_tls_data'])) {
		$rule['smtp_ignore_tls_data'] = 'on';
		$updated_cfg = true;
	}
	if (empty($rule['smtp_log_mail_from'])) {
		$rule['smtp_log_mail_from'] = 'on';
		$updated_cfg = true;
	}
	if (empty($rule['smtp_log_rcpt_to'])) {
		$rule['smtp_log_rcpt_to'] = 'on';
		$updated_cfg = true;
	}
	if (empty($rule['smtp_log_filename'])) {
		$rule['smtp_log_filename'] = 'on';
		$updated_cfg = true;
	}
	if (empty($rule['smtp_log_email_hdrs'])) {
		$rule['smtp_log_email_hdrs'] = 'on';
		$updated_cfg = true;
	}

	// Default any unconfigured AppID preprocessor settings
	if (empty($rule['appid_preproc'])) {
		$rule['appid_preproc'] = 'off';
		$updated_cfg = true;
	}
	if (empty($rule['sf_appid_mem_cap'])) {
		$rule['sf_appid_mem_cap'] = '256';
		$updated_cfg = true;
	}
	if (empty($rule['sf_appid_statslog'])) {
		$rule['sf_appid_statslog'] = 'on';
		$updated_cfg = true;
	}
	if (empty($rule['sf_appid_stats_period'])) {
		$rule['sf_appid_stats_period'] = '300';
		$updated_cfg = true;
	}

	// Check for and fix an incorrect value for <blockoffendersip>.
	// The value should be a string and not the index of the string.
	// This corrects for the impact of a Bootstrap conversion bug.
	if ($rule['blockoffendersip'] == '0' || $rule['blockoffendersip'] == '1' || $rule['blockoffendersip'] == '2') {
		switch ($rule['blockoffendersip']) {
			case '0':
				$rule['blockoffendersip'] = 'src';
				break;

			case '1':
				$rule['blockoffendersip'] = 'dst';
				break;

			case '2':
				$rule['blockoffendersip'] = 'both';
				break;

			default:
				break;
		}
		$updated_cfg = true;
	}

	// Configure a default interface snaplen if not previously configured
	if (!isset($rule['snaplen'])) {
		$rule['snaplen'] = '1518';
		$updated_cfg = true;
	}

	// Configure new SSH preprocessor parameter defaults if not already set
	if (!isset($rule['ssh_preproc_ports'])) {
		$rule['ssh_preproc_ports'] = '22';
		$updated_cfg = true;
	}
	if (!isset($rule['ssh_preproc_max_encrypted_packets'])) {
		$rule['ssh_preproc_max_encrypted_packets'] = 20;
		$updated_cfg = true;
	}
	if (!isset($rule['ssh_preproc_max_client_bytes'])) {
		$rule['ssh_preproc_max_client_bytes'] = 19600;
		$updated_cfg = true;
	}
	if (!isset($rule['ssh_preproc_max_server_version_len'])) {
		$rule['ssh_preproc_max_server_version_len'] = 100;
		$updated_cfg = true;
	}
	if (!isset($rule['ssh_preproc_enable_respoverflow'])) {
		$rule['ssh_preproc_enable_respoverflow'] = 'on';
		$updated_cfg = true;
	}
	if (!isset($rule['ssh_preproc_enable_srvoverflow'])) {
		$rule['ssh_preproc_enable_srvoverflow'] = 'on';
		$updated_cfg = true;
	}
	if (!isset($rule['ssh_preproc_enable_ssh1crc32'])) {
		$rule['ssh_preproc_enable_ssh1crc32'] = 'on';
		$updated_cfg = true;
	}
	if (!isset($rule['ssh_preproc_enable_protomismatch'])) {
		$rule['ssh_preproc_enable_protomismatch'] = 'on';
		$updated_cfg = true;
	}
	// End new SSH parameters

	/**********************************************************/
	/* Create new interface IPS mode setting if not set       */
	/**********************************************************/
	if (empty($rule['ips_mode'])) {
		$rule['ips_mode'] = 'ips_mode_legacy';
		$updated_cfg = true;
	}

	/**********************************************************/
	/* Migrate any enabled Unified logging from Barnyard2 to  */
	/* the new snort_xxxx.u2 log interface logging.           */
	/**********************************************************/
	if (!isset($rule['unified2_logging_enable'])) {
		// Continue U2 logging if Barnyard2 was enabled
		if (isset($rule['barnyard_enable']) && $rule['barnyard_enable'] == 'on') {
			$rule['unified2_logging_enable'] = 'on';
		}
		else {
			$rule['unified2_logging_enable'] = 'off';
		}

		// Check if VLAN or MPLS events logging is enabled
		if (isset($rule['barnyard_log_vlan_events']) && $rule['barnyard_log_vlan_events'] == 'on') {
			$rule['unified2_log_vlan_events'] = 'on';
		}
		else {
			$rule['unified2_log_vlan_events'] = 'off';
		}
		if (isset($rule['barnyard_log_mpls_events']) && $rule['barnyard_log_mpls_events'] == 'on') {
			$rule['unified2_log_mpls_events'] = 'on';
		}
		else {
			$rule['unified2_log_mpls_events'] = 'off';
		}

		if (!isset($rule['unified2_log_limit'])) {
			$rule['unified2_log_limit'] = '500';
		}
		if (!isset($rule['u2_archived_log_retention'])) {
			$rule['u2_archived_log_retention'] = '336';
		}
		$updated_cfg = true;
	}

	/**********************************************************/
	/* Remove deprecated Barnyard2 configuration parameters   */
	/* from this interface if any are present.                */
	/**********************************************************/
	if (isset($rule['barnyard_enable'])) {
		unset($rule['barnyard_enable']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_show_year'])) {
		unset($rule['barnyard_show_year']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_archive_enable'])) {
		unset($rule['barnyard_archive_enable']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_dump_payload'])) {
		unset($rule['barnyard_dump_payload']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_obfuscate_ip'])) {
		unset($rule['barnyard_obfuscate_ip']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_log_vlan_events'])) {
		unset($rule['barnyard_log_vlan_events']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_log_mpls_events'])) {
		unset($rule['barnyard_log_mpls_events']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_mysql_enable'])) {
		unset($rule['barnyard_mysql_enable']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_syslog_enable'])) {
		unset($rule['barnyard_syslog_enable']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_syslog_local'])) {
		unset($rule['']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_syslog_local'])) {
		unset($rule['']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_bro_ids_enable'])) {
		unset($rule['barnyard_bro_ids_enable']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_disable_sig_ref_tbl'])) {
		unset($rule['barnyard_disable_sig_ref_tbl']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_syslog_opmode'])) {
		unset($rule['barnyard_syslog_opmode']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_syslog_payload_encoding'])) {
		unset($rule['barnyard_syslog_payload_encoding']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_syslog_proto'])) {
		unset($rule['barnyard_syslog_proto']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_sensor_name'])) {
		unset($rule['barnyard_sensor_name']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_dbhost'])) {
		unset($rule['barnyard_dbhost']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_dbname'])) {
		unset($rule['barnyard_dbname']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_dbuser'])) {
		unset($rule['barnyard_dbuser']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_dbpwd'])) {
		unset($rule['barnyard_dbpwd']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_syslog_rhost'])) {
		unset($rule['barnyard_syslog_rhost']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_syslog_dport'])) {
		unset($rule['barnyard_syslog_dport']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_syslog_facility'])) {
		unset($rule['barnyard_syslog_facility']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_syslog_priority'])) {
		unset($rule['barnyard_syslog_priority']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_bro_ids_rhost'])) {
		unset($rule['barnyard_bro_ids_rhost']);
		$updated_cfg = true;
	}
	if (isset($rule['barnyard_bro_ids_dport'])) {
		unset($rule['barnyard_bro_ids_dport']);
		$updated_cfg = true;
	}
	if (isset($rule['barnconfigpassthru'])) {
		unset($rule['barnconfigpassthru']);
		$updated_cfg = true;
	}
	/**********************************************************/
	/* End Barnyard2 parameter removal                        */
	/**********************************************************/
}
// Store updated interface info to configuration
config_set_path('installedpackages/snortglobal/rule', $a_rules);

// Log a message if we changed anything
if ($updated_cfg) {
	logger(LOG_NOTICE, localize_text("Settings successfully migrated to new configuration format..."), LOG_PREFIX_PKG_SNORT);
}
else {
	logger(LOG_NOTICE, localize_text("Configuration version is current..."), LOG_PREFIX_PKG_SNORT);
}

return true;
?>
