<?php
/*
 * snort_migrate_config.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2013, 2014 Bill Meeks
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

if (!is_array($config['installedpackages']['snortglobal']))
	$config['installedpackages']['snortglobal'] = array();
if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();

// Just exit if this is a clean install with no saved settings
if (empty($config['installedpackages']['snortglobal']['rule']))
	return;

$rule = &$config['installedpackages']['snortglobal']['rule'];

/****************************************************************************/
/* Loop through all the <rule> elements in the Snort configuration and      */
/* migrate the relevant preprocessor parameters to the new format.          */
/****************************************************************************/

$updated_cfg = false;
log_error("[Snort] Checking configuration settings version...");

// Check the configuration version to see if XMLRPC Sync should
// auto-disabled as part of the upgrade due to config format changes.
if (empty($config['installedpackages']['snortglobal']['snort_config_ver']) && 
    ($config['installedpackages']['snortsync']['config']['varsynconchanges'] == 'auto' ||
     $config['installedpackages']['snortsync']['config']['varsynconchanges'] == 'manual')) {
	$config['installedpackages']['snortsync']['config']['varsynconchanges']	= "disabled";
	log_error("[Snort] Turning off Snort Sync on this host due to configuration format changes in this update.  Upgrade all Snort Sync targets to this same Snort package version before re-enabling Snort Sync.");
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
/* Migrate per interface settings if required.            */
/**********************************************************/
foreach ($rule as &$r) {
	// Initialize arrays for supported preprocessors if necessary
	if (!is_array($r['frag3_engine']['item']))
		$r['frag3_engine']['item'] = array();
	if (!is_array($r['stream5_tcp_engine']['item']))
		$r['stream5_tcp_engine']['item'] = array();
	if (!is_array($r['http_inspect_engine']['item']))
		$r['http_inspect_engine']['item'] = array();
	if (!is_array($r['ftp_client_engine']['item']))
		$r['ftp_client_engine']['item'] = array();
	if (!is_array($r['ftp_server_engine']['item']))
		$r['ftp_server_engine']['item'] = array();

	$pconfig = array();
	$pconfig = $r;

	// Create a default "frag3_engine" if none are configured
	if (empty($pconfig['frag3_engine']['item'])) {
		$updated_cfg = true;
		log_error("[Snort] Migrating Frag3 Engine configuration for interface {$pconfig['descr']}...");
		$default = array( "name" => "default", "bind_to" => "all", "policy" => "bsd", 
				"timeout" => 60, "min_ttl" => 1, "detect_anomalies" => "on", 
				"overlap_limit" => 0, "min_frag_len" => 0 );

		// Ensure sensible default values exist for global Frag3 parameters
		if (empty($pconfig['frag3_max_frags']))
			$pconfig['frag3_max_frags'] = '8192';
		if (empty($pconfig['frag3_memcap']))
			$pconfig['frag3_memcap'] = '4194304';
		if (empty($pconfig['frag3_detection']))
			$pconfig['frag3_detection'] = 'on';

		// Put any old values in new default engine and remove old value
		if (isset($pconfig['frag3_policy']))
			$default['policy'] = $pconfig['frag3_policy'];
		unset($pconfig['frag3_policy']);
		if (isset($pconfig['frag3_timeout']) && is_numeric($pconfig['frag3_timeout']))
			$default['timeout'] = $pconfig['frag3_timeout'];
		unset($pconfig['frag3_timeout']);
		if (isset($pconfig['frag3_overlap_limit']) && is_numeric($pconfig['frag3_overlap_limit']))
			$default['overlap_limit'] = $pconfig['frag3_overlap_limit'];
		unset($pconfig['frag3_overlap_limit']);
		if (isset($pconfig['frag3_min_frag_len']) && is_numeric($pconfig['frag3_min_frag_len']))
			$default['min_frag_len'] = $pconfig['frag3_min_frag_len'];
		unset($pconfig['frag3_min_frag_len']);

		$pconfig['frag3_engine']['item'] = array();
		$pconfig['frag3_engine']['item'][] = $default;
	}

	// Create a default Stream5 engine array if none are configured
	if (empty($pconfig['stream5_tcp_engine']['item'])) {
		$updated_cfg = true;
		log_error("[Snort] Migrating Stream5 Engine configuration for interface {$pconfig['descr']}...");
		$default = array( "name" => "default", "bind_to" => "all", "policy" => "bsd", "timeout" => 30, 
				"max_queued_bytes" => 1048576, "detect_anomalies" => "off", "overlap_limit" => 0, 
				"max_queued_segs" => 2621, "require_3whs" => "off", "startup_3whs_timeout" => 0, 
				"no_reassemble_async" => "off", "max_window" => 0, "use_static_footprint_sizes" => "off", 
				"check_session_hijacking" => "off", "dont_store_lg_pkts" => "off", "ports_client" => "default", 
				"ports_both" => "default", "ports_server" => "none" );

		// Ensure sensible defaults exist for Stream5 global parameters
		if (empty($pconfig['stream5_reassembly']))
			$pconfig['stream5_reassembly'] = 'on';
		if (empty($pconfig['stream5_flush_on_alert']))
			$pconfig['stream5_flush_on_alert'] = 'off';
		if (empty($pconfig['stream5_prune_log_max']))
			$pconfig['stream5_prune_log_max'] = '1048576';
		if (empty($pconfig['stream5_track_tcp']))
			$pconfig['stream5_track_tcp'] = 'on';
		if (empty($pconfig['stream5_max_tcp']))
			$pconfig['stream5_max_tcp'] = '262144';
		if (empty($pconfig['stream5_track_udp']))
			$pconfig['stream5_track_udp'] = 'on';
		if (empty($pconfig['stream5_max_udp']))
			$pconfig['stream5_max_udp'] = '131072';
		if (empty($pconfig['stream5_udp_timeout']))
			$pconfig['stream5_udp_timeout'] = '30';
		if (empty($pconfig['stream5_track_icmp']))
			$pconfig['stream5_track_icmp'] = 'off';
		if (empty($pconfig['stream5_max_icmp']))
			$pconfig['stream5_max_icmp'] = '65536';
		if (empty($pconfig['stream5_icmp_timeout']))
			$pconfig['stream5_icmp_timeout'] = '30';
		if (empty($pconfig['stream5_mem_cap']))
			$pconfig['stream5_mem_cap']= '8388608';

		// Put any old values in new default engine and remove old value
		if (isset($pconfig['stream5_policy']))
			$default['policy'] = $pconfig['stream5_policy'];
		unset($pconfig['stream5_policy']);
		if (isset($pconfig['stream5_tcp_timeout']) && is_numeric($pconfig['stream5_tcp_timeout']))
			$default['timeout'] = $pconfig['stream5_tcp_timeout'];
		unset($pconfig['stream5_tcp_timeout']);
		if (isset($pconfig['stream5_overlap_limit']) && is_numeric($pconfig['stream5_overlap_limit']))
			$default['overlap_limit'] = $pconfig['stream5_overlap_limit'];
		unset($pconfig['stream5_overlap_limit']);
		if (isset($pconfig['stream5_require_3whs']))
			$default['require_3whs'] = $pconfig['stream5_require_3whs'];
		unset($pconfig['stream5_require_3whs']);
		if (isset($pconfig['stream5_no_reassemble_async']))
			$default['no_reassemble_async'] = $pconfig['stream5_no_reassemble_async'];
		unset($pconfig['stream5_no_reassemble_async']);
		if (isset($pconfig['stream5_dont_store_lg_pkts']))
			$default['dont_store_lg_pkts'] = $pconfig['stream5_dont_store_lg_pkts'];
		unset($pconfig['stream5_dont_store_lg_pkts']);
		if (isset($pconfig['max_queued_bytes']) && is_numeric($pconfig['max_queued_bytes']))
			$default['max_queued_bytes'] = $pconfig['max_queued_bytes'];
		unset($pconfig['max_queued_bytes']);
		if (isset($pconfig['max_queued_segs']) && is_numeric($pconfig['max_queued_segs']))
			$default['max_queued_segs'] = $pconfig['max_queued_segs'];
		unset($pconfig['max_queued_segs']);

		$pconfig['stream5_tcp_engine']['item'] = array();
		$pconfig['stream5_tcp_engine']['item'][] = $default;
	}

	// Create a default HTTP_INSPECT engine if none are configured
	if (empty($pconfig['http_inspect_engine']['item'])) {
		$updated_cfg = true;
		log_error("[Snort] Migrating HTTP_Inspect Engine configuration for interface {$pconfig['descr']}...");
		$default = array( "name" => "default", "bind_to" => "all", "server_profile" => "all", "enable_xff" => "off", 
				"log_uri" => "off", "log_hostname" => "off", "server_flow_depth" => 65535, "enable_cookie" => "on", 
				"client_flow_depth" => 1460, "extended_response_inspection" => "on", "no_alerts" => "off", 
				"unlimited_decompress" => "on", "inspect_gzip" => "on", "normalize_cookies" =>"on", 
				"normalize_headers" => "on", "normalize_utf" => "on", "normalize_javascript" => "on", 
				"allow_proxy_use" => "off", "inspect_uri_only" => "off", "max_javascript_whitespaces" => 200,
				"post_depth" => -1, "max_headers" => 0, "max_spaces" => 0, "max_header_length" => 0, "ports" => "default",
				"decompress_swf" => "off", "decompress_pdf" => "off" );

		// Ensure sensible default values exist for global HTTP_INSPECT parameters
		if (empty($pconfig['http_inspect']))
			$pconfig['http_inspect'] = "on";
		if (empty($pconfig['http_inspect_proxy_alert']))
			$pconfig['http_inspect_proxy_alert'] = "off";
		if (empty($pconfig['http_inspect_memcap']))
			$pconfig['http_inspect_memcap'] = "150994944";
		if (empty($pconfig['http_inspect_max_gzip_mem']))
			$pconfig['http_inspect_max_gzip_mem'] = "838860";

		// Put any old values in new default engine and remove old value
		if (isset($pconfig['server_flow_depth']) && is_numeric($pconfig['server_flow_depth']))
			$default['server_flow_depth'] = $pconfig['server_flow_depth'];
		unset($pconfig['server_flow_depth']);
		if (isset($pconfig['client_flow_depth']) & is_numeric($pconfig['client_flow_depth']))
			$default['client_flow_depth'] = $pconfig['client_flow_depth'];
		unset($pconfig['client_flow_depth']);
		if (isset($pconfig['http_server_profile']))
			$default['server_profile'] = $pconfig['http_server_profile'];
		unset($pconfig['http_server_profile']);
		if (isset($pconfig['http_inspect_enable_xff']))
			$default['enable_xff'] = $pconfig['http_inspect_enable_xff'];
		unset($pconfig['http_inspect_enable_xff']);
		if (isset($pconfig['http_inspect_log_uri']))
			$default['log_uri'] = $pconfig['http_inspect_log_uri'];
		unset($pconfig['http_inspect_log_uri']);
		if (isset($pconfig['http_inspect_log_hostname']))
			$default['log_hostname'] = $pconfig['http_inspect_log_hostname'];
		unset($pconfig['http_inspect_log_hostname']);
		if (isset($pconfig['noalert_http_inspect']))
			$default['no_alerts'] = $pconfig['noalert_http_inspect'];
		unset($pconfig['noalert_http_inspect']);

		$pconfig['http_inspect_engine']['item'] = array();
		$pconfig['http_inspect_engine']['item'][] = $default;
	}

	// Create a default FTP_CLIENT engine if none are configured
	if (empty($pconfig['ftp_client_engine']['item'])) {
		$updated_cfg = true;
		log_error("[Snort] Migrating FTP Client Engine configuration for interface {$pconfig['descr']}...");
		$default = array( "name" => "default", "bind_to" => "all", "max_resp_len" => 256, 
				  "telnet_cmds" => "no", "ignore_telnet_erase_cmds" => "yes", 
				  "bounce" => "yes", "bounce_to_net" => "", "bounce_to_port" => "" );

		// Set defaults for new FTP_Telnet preprocessor configurable parameters
		if (empty($pconfig['ftp_telnet_inspection_type']))
			$pconfig['ftp_telnet_inspection_type'] = 'stateful';
		if (empty($pconfig['ftp_telnet_alert_encrypted']))
			$pconfig['ftp_telnet_alert_encrypted'] = 'off';
		if (empty($pconfig['ftp_telnet_check_encrypted']))
			$pconfig['ftp_telnet_check_encrypted'] = 'on';
		if (empty($pconfig['ftp_telnet_normalize']))
			$pconfig['ftp_telnet_normalize'] = 'on';
		if (empty($pconfig['ftp_telnet_detect_anomalies']))
			$pconfig['ftp_telnet_detect_anomalies'] = 'on';
		if (empty($pconfig['ftp_telnet_ayt_attack_threshold']))
			$pconfig['ftp_telnet_ayt_attack_threshold'] = '20';

		// Add new FTP_Telnet Client default engine
		$pconfig['ftp_client_engine']['item'] = array();
		$pconfig['ftp_client_engine']['item'][] = $default;
	}

	// Create a default FTP_SERVER engine if none are configured
	if (empty($pconfig['ftp_server_engine']['item'])) {
		$updated_cfg = true;
		log_error("[Snort] Migrating FTP Server Engine configuration for interface {$pconfig['descr']}...");
		$default = array( "name" => "default", "bind_to" => "all", "ports" => "default", 
				  "telnet_cmds" => "no", "ignore_telnet_erase_cmds" => "yes", 
				  "ignore_data_chan" => "no", "def_max_param_len" => 100 );

		// Add new FTP_Telnet Server default engine
		$pconfig['ftp_server_engine']['item'] = array();
		$pconfig['ftp_server_engine']['item'][] = $default;
	}

	// Set sensible defaults for new SDF options if SDF is enabled
	if ($pconfig['sensitive_data'] == 'on') {
		if (empty($pconfig['sdf_alert_threshold'])) {
			$pconfig['sdf_alert_threshold'] = 25;
			$updated_cfg = true;
		}
		if (empty($pconfig['sdf_alert_data_type'])) {
			$pconfig['sdf_alert_data_type'] = "Credit Card,Email Addresses,U.S. Phone Numbers,U.S. Social Security Numbers";
			$updated_cfg = true;
		}
	}

	// Change any ENABLE_SID settings to new format of GID:SID
	if (!empty($pconfig['rule_sid_on'])) {
		$tmp = explode("||", $pconfig['rule_sid_on']);
		$new_tmp = "";
		foreach ($tmp as $v) {
			if (strpos($v, ":") === false) {
				if (preg_match('/(\d+)/', $v, $match))
					$new_tmp .= "1:{$match[1]}||";
			}
		}
		$new_tmp = rtrim($new_tmp, " ||");
		if (!empty($new_tmp)) {
			$pconfig['rule_sid_on'] = $new_tmp;
			$updated_cfg = true;
		}
	}

	// Change any DISABLE_SID settings to new format of GID:SID
	if (!empty($pconfig['rule_sid_off'])) {
		$tmp = explode("||", $pconfig['rule_sid_off']);
		$new_tmp = "";
		foreach ($tmp as $v) {
			if (strpos($v, ":") === false) {
				if (preg_match('/(\d+)/', $v, $match))
					$new_tmp .= "1:{$match[1]}||";
			}
		}
		$new_tmp = rtrim($new_tmp, " ||");
		if (!empty($new_tmp)) {
			$pconfig['rule_sid_off'] = $new_tmp;
			$updated_cfg = true;
		}
	}

	// Migrate any Barnyard2 settings to the new advanced fields.
	// Parse the old DB connect string and find the "host", "user",
	// "dbname" and "password" values and save them in the new
	// MySQL field names in the config file.
	if (!empty($pconfig['barnyard_mysql'])) {
		if (preg_match_all('/(dbname|host|user|password)\s*\=\s*([^\s]*)/i', $pconfig['barnyard_mysql'], $matches)) {
			foreach ($matches[1] as $k => $p) {
				if (strcasecmp($p, 'dbname') == 0)
					$pconfig['barnyard_dbname'] = $matches[2][$k];
				elseif (strcasecmp($p, 'host') == 0)
					$pconfig['barnyard_dbhost'] = $matches[2][$k];
				elseif (strcasecmp($p, 'user') == 0)
					$pconfig['barnyard_dbuser'] = $matches[2][$k];
				elseif (strcasecmp($p, 'password') == 0)
					$pconfig['barnyard_dbpwd'] = base64_encode($matches[2][$k]);
			}
			$pconfig['barnyard_mysql_enable'] = 'on';
			unset($pconfig['barnyard_mysql']);
		}
		// Since Barnyard2 was enabled, configure the new archived log settings
		$pconfig['u2_archived_log_retention'] = '168';
		$pconfig['barnyard_archive_enable'] = 'on';
		$pconfig['unified2_log_limit'] = '32M';
		$updated_cfg = true;
	}

	// This setting is deprecated and replaced
	// by 'barnyard_enable' since any Barnyard2
	// chaining requires unified2 logging.
	if (isset($pconfig['snortunifiedlog'])) {
		unset($pconfig['snortunifiedlog']);
		$pconfig['barnyard_enable'] = 'on';
		$updated_cfg = true;
	}

	// Migrate new POP3 preprocessor parameter settings
	if (empty($pconfig['pop_memcap'])) {
		$pconfig['pop_memcap'] = "838860";
		$updated_cfg = true;
	}
	if (empty($pconfig['pop_b64_decode_depth']) && $pconfig['pop_b64_decode_depth'] != '0') {
		$pconfig['pop_b64_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($pconfig['pop_qp_decode_depth']) && $pconfig['pop_qp_decode_depth'] != '0') {
		$pconfig['pop_qp_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($pconfig['pop_bitenc_decode_depth']) && $pconfig['pop_bitenc_decode_depth'] != '0') {
		$pconfig['pop_bitenc_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($pconfig['pop_uu_decode_depth']) && $pconfig['pop_uu_decode_depth'] != '0') {
		$pconfig['pop_uu_decode_depth'] = "0";
		$updated_cfg = true;
	}

	// Migrate new IMAP preprocessor parameter settings
	if (empty($pconfig['imap_memcap'])) {
		$pconfig['imap_memcap'] = "838860";
		$updated_cfg = true;
	}
	if (empty($pconfig['imap_b64_decode_depth']) && $pconfig['imap_b64_decode_depth'] != '0') {
		$pconfig['imap_b64_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($pconfig['imap_qp_decode_depth']) && $pconfig['imap_qp_decode_depth'] != '0') {
		$pconfig['imap_qp_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($pconfig['imap_bitenc_decode_depth']) && $pconfig['imap_bitenc_decode_depth'] != '0') {
		$pconfig['imap_bitenc_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($pconfig['imap_uu_decode_depth']) && $pconfig['imap_uu_decode_depth'] != '0') {
		$pconfig['imap_uu_decode_depth'] = "0";
		$updated_cfg = true;
	}

	// Migrate new SMTP preprocessor parameter settings
	if (empty($pconfig['smtp_memcap'])) {
		$pconfig['smtp_memcap'] = "838860";
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_max_mime_mem'])) {
		$pconfig['smtp_max_mime_mem'] = "838860";
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_b64_decode_depth']) && $pconfig['smtp_b64_decode_depth'] != "0") {
		$pconfig['smtp_b64_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_qp_decode_depth']) && $pconfig['smtp_qp_decode_depth'] != "0") {
		$pconfig['smtp_qp_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_bitenc_decode_depth']) && $pconfig['smtp_bitenc_decode_depth'] != "0") {
		$pconfig['smtp_bitenc_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_uu_decode_depth']) && $pconfig['smtp_uu_decode_depth'] != "0") {
		$pconfig['smtp_uu_decode_depth'] = "0";
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_email_hdrs_log_depth'])) {
		$pconfig['smtp_email_hdrs_log_depth'] = "1464";
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_ignore_tls_data'])) {
		$pconfig['smtp_ignore_tls_data'] = 'on';
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_log_mail_from'])) {
		$pconfig['smtp_log_mail_from'] = 'on';
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_log_rcpt_to'])) {
		$pconfig['smtp_log_rcpt_to'] = 'on';
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_log_filename'])) {
		$pconfig['smtp_log_filename'] = 'on';
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_log_email_hdrs'])) {
		$pconfig['smtp_log_email_hdrs'] = 'on';
		$updated_cfg = true;
	}

	// Migrate any BY2 limit for unified2 logs to new format
	if (!empty($pconfig['unified2_log_limit']) && 
	    !preg_match('/^\d+[g|k|m|G|K|M]/', $pconfig['unified2_log_limit'])) {
		$pconfig['unified2_log_limit'] .= "M";
		$updated_cfg = true;
	}

	// Default any unconfigured AppID preprocessor settings
	if (empty($pconfig['appid_preproc'])) {
		$pconfig['appid_preproc'] = 'off';
		$updated_cfg = true;
	}
	if (empty($pconfig['sf_appid_mem_cap'])) {
		$pconfig['sf_appid_mem_cap'] = '256';
		$updated_cfg = true;
	}
	if (empty($pconfig['sf_appid_statslog'])) {
		$pconfig['sf_appid_statslog'] = 'on';
		$updated_cfg = true;
	}
	if (empty($pconfig['sf_appid_stats_period'])) {
		$pconfig['sf_appid_stats_period'] = '300';
		$updated_cfg = true;
	}

	// Check for and fix an incorrect value for <blockoffendersip>.
	// The value should be a string and not the index of the string.
	// This corrects for the impact of a Bootstrap conversion bug.
	if ($pconfig['blockoffendersip'] == '0' || $pconfig['blockoffendersip'] == '1' || $pconfig['blockoffendersip'] == '2') {
		switch ($pconfig['blockoffendersip']) {
			case '0':
				$pconfig['blockoffendersip'] = 'src';
				break;

			case '1':
				$pconfig['blockoffendersip'] = 'dst';
				break;

			case '2':
				$pconfig['blockoffendersip'] = 'both';
				break;

			default:
				break;
		}
		$updated_cfg = true;
	}

	// Save the new configuration data into the $config array pointer
	$r = $pconfig;
}
// Release reference to final array element
unset($r);

// Log a message if we changed anything
if ($updated_cfg) {
	log_error("[Snort] Settings successfully migrated to new configuration format...");
}
else {
	log_error("[Snort] Configuration version is current...");
}

?>
