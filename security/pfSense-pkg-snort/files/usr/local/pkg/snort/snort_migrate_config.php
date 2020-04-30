<?php
/*
 * snort_migrate_config.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2019-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2020 Bill Meeks
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

global $config;

if (!is_array($config['installedpackages'])) {
	$config['installedpackages'] = array();
}
if (!is_array($config['installedpackages']['snortglobal'])) {
	$config['installedpackages']['snortglobal'] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}

// Just exit if this is a clean install with no saved settings
if (empty($config['installedpackages']['snortglobal']['rule']))
	return;

/****************************************************************************/
/* Loop through all the <rule> elements in the Snort configuration and      */
/* migrate the relevant preprocessor parameters to the new format.          */
/****************************************************************************/

$updated_cfg = false;
syslog(LOG_NOTICE, "[Snort] Checking configuration settings version...");

// Check the configuration version to see if XMLRPC Sync should
// auto-disabled as part of the upgrade due to config format changes.
if (empty($config['installedpackages']['snortglobal']['snort_config_ver']) && 
    ($config['installedpackages']['snortsync']['config']['varsynconchanges'] == 'auto' ||
     $config['installedpackages']['snortsync']['config']['varsynconchanges'] == 'manual')) {
	$config['installedpackages']['snortsync']['config']['varsynconchanges']	= "disabled";
	syslog(LOG_NOTICE, "[Snort] Turning off Snort Sync on this host due to configuration format changes in this update.  Upgrade all Snort Sync targets to this same Snort package version before re-enabling Snort Sync.");
	$updated_cfg = true;
}

/**********************************************************/
/* Create new Auto SID Mgmt settings if not set           */
/**********************************************************/
if (empty($config['installedpackages']['snortglobal']['auto_manage_sids'])) {
	$config['installedpackages']['snortglobal']['auto_manage_sids'] = "off";
	$updated_cfg = true;
}

/**********************************************************/
/* Create new LOG MGMT settings if not set                */
/**********************************************************/
if (empty($config['installedpackages']['snortglobal']['enable_log_mgmt'])) {
	$config['installedpackages']['snortglobal']['enable_log_mgmt'] = "on";
	$config['installedpackages']['snortglobal']['alert_log_limit_size'] = "500";
	$config['installedpackages']['snortglobal']['alert_log_retention'] = "336";
	$config['installedpackages']['snortglobal']['appid_stats_log_limit_size'] = "1000";
	$config['installedpackages']['snortglobal']['appid_stats_log_retention'] = "168";
	$config['installedpackages']['snortglobal']['event_pkts_log_limit_size'] = "0";
	$config['installedpackages']['snortglobal']['event_pkts_log_retention'] = "336";
	$config['installedpackages']['snortglobal']['sid_changes_log_limit_size'] = "250";
	$config['installedpackages']['snortglobal']['sid_changes_log_retention'] = "336";
	$config['installedpackages']['snortglobal']['stats_log_limit_size'] = "500";
	$config['installedpackages']['snortglobal']['stats_log_retention'] = "168";
	$updated_cfg = true;
}
if (empty($config['installedpackages']['snortglobal']['appid_stats_log_limit_size']))
	$config['installedpackages']['snortglobal']['appid_stats_log_limit_size'] = "1000";
if (empty($config['installedpackages']['snortglobal']['appid_stats_log_retention']))
	$config['installedpackages']['snortglobal']['appid_stats_log_retention'] = "168";

/**********************************************************/
/* Create new VERBOSE_LOGGING setting if not set          */
/**********************************************************/
if (empty($config['installedpackages']['snortglobal']['verbose_logging'])) {
	$config['installedpackages']['snortglobal']['verbose_logging'] = "off";
	$updated_cfg = true;
}

/**********************************************************/
/* Create new OpenAppID settings if not set               */
/**********************************************************/
if (empty($config['installedpackages']['snortglobal']['openappid_detectors'])) {
	$config['installedpackages']['snortglobal']['openappid_detectors'] = "off";
	$updated_cfg = true;
}
if (empty($config['installedpackages']['snortglobal']['openappid_rules_detectors'])) {
        $config['installedpackages']['snortglobal']['openappid_rules_detectors'] = "off";
        $updated_cfg = true;
}


/**********************************************************/
/* Create new HIDE_DEPRECATED_RULES setting if not set    */
/**********************************************************/
if (empty($config['installedpackages']['snortglobal']['hide_deprecated_rules'])) {
	$config['installedpackages']['snortglobal']['hide_deprecated_rules'] = "off";
	$updated_cfg = true;
}

/**********************************************************/
/* Migrate content of any existing SID Mgmt files in the  */
/* /var/db/snort/sidmods directory to Base64 encoded      */
/* strings in SID_MGMT_LIST array in config.xml.          */
/**********************************************************/
if (!is_array($config['installedpackages']['snortglobal']['sid_mgmt_lists'])) {
	$config['installedpackages']['snortglobal']['sid_mgmt_lists'] = array();
}
if (empty($config['installedpackages']['snortglobal']['sid_list_migration']) && count($config['installedpackages']['snortglobal']['sid_mgmt_lists']) < 1) {
	if (!is_array($config['installedpackages']['snortglobal']['sid_mgmt_lists']['item'])) {
		$config['installedpackages']['snortglobal']['sid_mgmt_lists']['item'] = array();
	}
	$a_list = &$config['installedpackages']['snortglobal']['sid_mgmt_lists']['item'];
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

	// Set a flag to show one-time migration is completed.
	// We can increment this flag in later versions if we
	// need to import additional files as SID_MGMT_LISTS.
	$config['installedpackages']['snortglobal']['sid_list_migration'] = "2";
	$updated_cfg = true;
	unset($a_list);
}
elseif ($config['installedpackages']['snortglobal']['sid_list_migration'] < "2") {

	// Import dropsid-sample.conf and rejectsid-sample.conf
	// files if missing from the SID_MGMT_LIST array.
	if (!is_array($config['installedpackages']['snortglobal']['sid_mgmt_lists']['item'])) {
		$config['installedpackages']['snortglobal']['sid_mgmt_lists']['item'] = array();
	}
	$sidmodfiles = array( "dropsid-sample.conf", "rejectsid-sample.conf" );
	$a_list = &$config['installedpackages']['snortglobal']['sid_mgmt_lists']['item'];
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

	// Set a flag to show this one-time migration is completed
	$config['installedpackages']['snortglobal']['sid_list_migration'] = "2";
	$updated_cfg = true;
	unset($a_list);
}

/**********************************************************/
/* Remove the two deprecated Rules Update Status fields   */
/* from the package configuration. The status is now      */
/* stored in a local file.                                */
/**********************************************************/
if (isset($config['installedpackages']['snortglobal']['last_rule_upd_status'])) {
	unset($config['installedpackages']['snortglobal']['last_rule_upd_status']);
	$updated_cfg = true;
}
if (isset($config['installedpackages']['snortglobal']['last_rule_upd_time'])) {
	unset($config['installedpackages']['snortglobal']['last_rule_upd_time']);
	$updated_cfg = true;
}

/**********************************************************/
/* Randomize the Rules Update Start Time minutes field    */
/* per request of Snort.org team to minimize impact of    */
/* large numbers of pfSense users hitting Snort.org at    */
/* the same minute past the hour for rules updates.       */
/**********************************************************/
if (empty($config['installedpackages']['snortglobal']['rule_update_starttime']) || 
	  $config['installedpackages']['snortglobal']['rule_update_starttime'] == '00:05' || 
	  strlen($config['installedpackages']['snortglobal']['rule_update_starttime']) < 5 ) {
	$config['installedpackages']['snortglobal']['rule_update_starttime'] = "00:" . str_pad(strval(random_int(0,59)), 2, "00", STR_PAD_LEFT);
	$updated_cfg = true;
}

/**********************************************************/
/* Migrate per interface settings if required.            */
/**********************************************************/
foreach ($config['installedpackages']['snortglobal']['rule'] as &$rule) {
	// Initialize arrays for supported preprocessors if necessary
	if (!is_array($rule['frag3_engine'])) {
		$rule['frag3_engine'] = array();
	}
	if (!is_array($rule['frag3_engine']['item'])) {
		$rule['frag3_engine']['item'] = array();
	}
	if (!is_array($rule['stream5_tcp_engine'])) {
		$rule['stream5_tcp_engine'] = array();
	}
	if (!is_array($rule['stream5_tcp_engine']['item'])) {
		$rule['stream5_tcp_engine']['item'] = array();
	}
	if (!is_array($rule['http_inspect_engine'])) {
		$rule['http_inspect_engine'] = array();
	}
	if (!is_array($rule['http_inspect_engine']['item'])) {
		$rule['http_inspect_engine']['item'] = array();
	}
	if (!is_array($rule['ftp_client_engine'])) {
		$rule['ftp_client_engine'] = array();
	}
	if (!is_array($rule['ftp_client_engine']['item'])) {
		$rule['ftp_client_engine']['item'] = array();
	}
	if (!is_array($rule['ftp_server_engine'])) {
		$rule['ftp_server_engine'] = array();
	}
	if (!is_array($rule['ftp_server_engine']['item'])) {
		$rule['ftp_server_engine']['item'] = array();
	}

	// Create a default "frag3_engine" if none are configured
	if (empty($rule['frag3_engine']['item'])) {
		$updated_cfg = true;
		syslog(LOG_NOTICE, "[Snort] Migrating Frag3 Engine configuration for interface {$rule['descr']}...");
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
		syslog(LOG_NOTICE, "[Snort] Migrating Stream5 Engine configuration for interface {$rule['descr']}...");
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
		syslog(LOG_NOTICE, "[Snort] Migrating HTTP_Inspect Engine configuration for interface {$rule['descr']}...");
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
		syslog(LOG_NOTICE, "[Snort] Migrating FTP Client Engine configuration for interface {$rule['descr']}...");
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
		syslog(LOG_NOTICE, "[Snort] Migrating FTP Server Engine configuration for interface {$rule['descr']}...");
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

	// Migrate any Barnyard2 settings to the new advanced fields.
	// Parse the old DB connect string and find the "host", "user",
	// "dbname" and "password" values and save them in the new
	// MySQL field names in the config file.
	if (!empty($rule['barnyard_mysql'])) {
		if (preg_match_all('/(dbname|host|user|password)\s*\=\s*([^\s]*)/i', $rule['barnyard_mysql'], $matches)) {
			foreach ($matches[1] as $k => $p) {
				if (strcasecmp($p, 'dbname') == 0)
					$rule['barnyard_dbname'] = $matches[2][$k];
				elseif (strcasecmp($p, 'host') == 0)
					$rule['barnyard_dbhost'] = $matches[2][$k];
				elseif (strcasecmp($p, 'user') == 0)
					$rule['barnyard_dbuser'] = $matches[2][$k];
				elseif (strcasecmp($p, 'password') == 0)
					$rule['barnyard_dbpwd'] = base64_encode($matches[2][$k]);
			}
			$rule['barnyard_mysql_enable'] = 'on';
			unset($rule['barnyard_mysql']);
		}
		// Since Barnyard2 was enabled, configure the new archived log settings
		$rule['u2_archived_log_retention'] = '168';
		$rule['barnyard_archive_enable'] = 'on';
		$rule['unified2_log_limit'] = '32M';
		$updated_cfg = true;
	}

	// This setting is deprecated and replaced
	// by 'barnyard_enable' since any Barnyard2
	// chaining requires unified2 logging.
	if (isset($rule['snortunifiedlog'])) {
		unset($rule['snortunifiedlog']);
		$rule['barnyard_enable'] = 'on';
		$updated_cfg = true;
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

	// Migrate any BY2 limit for unified2 logs to new format
	if (!empty($rule['unified2_log_limit']) && 
	    !preg_match('/^\d+[g|k|m|G|K|M]/', $rule['unified2_log_limit'])) {
		$rule['unified2_log_limit'] .= "M";
		$updated_cfg = true;
	}

	// Set new BY2 syslog parameter to default if it is empty
	// and Barnyard2 is enabled.
	if ($rule['barnyard_enable'] == 'on' && empty($rule['barnyard_syslog_payload_encoding'])) {
		$rule['barnyard_syslog_payload_encoding'] = 'hex';
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
}
// Release reference to config array
unset($rule);

// Log a message if we changed anything
if ($updated_cfg) {
	syslog(LOG_NOTICE, "[Snort] Settings successfully migrated to new configuration format...");
}
else {
	syslog(LOG_NOTICE, "[Snort] Configuration version is current...");
}

?>
