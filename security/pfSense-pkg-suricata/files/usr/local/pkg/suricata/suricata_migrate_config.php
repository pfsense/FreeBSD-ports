<?php
/*
 * suricata_migrate_config.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2019-2025 Rubicon Communications, LLC (Netgate)
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

require_once("config.inc");
require_once("functions.inc");

/****************************************************************************/
/* The code in this module is called once during the post-install process   */
/* via an "include" line.  It is used to perform a one-time migration of    */
/* Suricata configuration parameters to any new format required by the      */
/* latest package version.                                                  */
/****************************************************************************/

// Just exit if this is a clean install with no saved settings
if (count(config_get_path('installedpackages/suricata/rule', [])) < 1)
	return;

/****************************************************************************/
/* Loop through all the <rule> elements in the Suricata configuration and   */
/* migrate relevant parameters to the new format.                           */
/****************************************************************************/

$updated_cfg = false;
logger(LOG_NOTICE, localize_text("Checking configuration settings version..."), LOG_PREFIX_PKG_SURICATA);

// Check the configuration version to see if XMLRPC Sync should be
// auto-disabled as part of the upgrade due to config format changes.
if (config_get_path('installedpackages/suricata/config/0/suricata_config_ver') < 2 &&
    (config_get_path('installedpackages/suricata/config/0/varsynconchanges') == 'auto' ||
     config_get_path('installedpackages/suricata/config/0/varsynconchanges') == 'manual')
   ) {
	config_set_path('installedpackages/suricata/config/0/varsynconchanges', "disabled");
	logger(LOG_NOTICE, localize_text("Turning off Suricata Sync on this host due to configuration format changes in this update.  Upgrade all Suricata Sync targets to this same Suricata package version before re-enabling Suricata Sync."), LOG_PREFIX_PKG_SURICATA);
	$updated_cfg = true;
}

/**********************************************************/
/* Create new Auto SID Mgmt settings if not set           */
/**********************************************************/
if (config_get_path('installedpackages/suricata/config/0/auto_manage_sids') === null) {
	config_set_path('installedpackages/suricata/config/0/auto_manage_sids', "off");
	config_set_path('installedpackages/suricata/config/0/sid_changes_log_limit_size', "250");
	config_set_path('installedpackages/suricata/config/0/sid_changes_log_retention', "336");
	$updated_cfg = true;
}

/**********************************************************/
/* Migrate content of any existing SID Mgmt files in the  */
/* /var/db/suricata/sidmods directory to Base64 encoded   */
/* strings in SID_MGMT_LIST array in config.xml.          */
/**********************************************************/
if (config_get_path('installedpackages/suricata/config/0/sid_list_migration') === null && count(config_get_path('installedpackages/suricata/sid_mgmt_lists', [])) < 1) {
	$a_list = config_get_path('installedpackages/suricata/sid_mgmt_lists/item', []);
	$sidmodfiles = return_dir_as_array("/var/db/suricata/sidmods/");
	foreach ($sidmodfiles as $sidfile) {
		$data = file_get_contents("/var/db/suricata/sidmods/" . $sidfile);
		if ($data !== FALSE) {
			$tmp = array();
			$tmp['name'] = basename($sidfile);
			$tmp['modtime'] = filemtime("/var/db/suricata/sidmods/" . $sidfile);
			$tmp['content'] = base64_encode($data);
			$a_list[] = $tmp;
		}
	}
	config_set_path('installedpackages/suricata/sid_mgmt_lists/item', $a_list);
	config_set_path('installedpackages/suricata/config/0/sid_list_migration', "1");
	$updated_cfg = true;
}

/**********************************************************/
/* Default Auto GeoLite2 DB update setting to "off" when  */
/* no GeoLite2 DB password is configured due to recent    */
/* MaxMind changes to the GeoLite2 database download      */
/* permissions.                                           */
/**********************************************************/
if (config_get_path('installedpackages/suricata/config/0/autogeoipupdate') === "on" && config_get_path('installedpackages/suricata/config/0/maxmind_geoipdb_key') === null) {
	config_set_path('installedpackages/suricata/config/0/autogeoipupdate', "off");
	$updated_cfg = true;
}

/**********************************************************/
/* Create new ET IQRisk IP Reputation setting if not set  */
/**********************************************************/
if (config_get_path('installedpackages/suricata/config/0/et_iqrisk_enable') === null) {
	config_set_path('installedpackages/suricata/config/0/et_iqrisk_enable', "off");
	$updated_cfg = true;
}

/**********************************************************/
/* Create new HIDE_DEPRECATED_RULES setting if not set    */
/**********************************************************/
if (config_get_path('installedpackages/suricata/config/0/hide_deprecated_rules') === null) {
	config_set_path('installedpackages/suricata/config/0/hide_deprecated_rules', "off");
	$updated_cfg = true;
}

/**********************************************************/
/* Remove the two deprecated Rules Update Status fields   */
/* from the package configuration. The status is now      */
/* stored in a local file.                                */
/**********************************************************/
if (config_path_enabled('installedpackages/suricata/config/0', 'last_rule_upd_status')) {
	config_del_path('installedpackages/suricata/config/0/last_rule_upd_status');
	$updated_cfg = true;
}
if (config_path_enabled('installedpackages/suricata/config/0', 'last_rule_upd_time')) {
	config_del_path('installedpackages/suricata/config/0/last_rule_upd_time');
	$updated_cfg = true;
}

/**********************************************************/
/* Randomize the Rules Update Start Time minutes field    */
/* per request of Snort.org team to minimize impact of    */
/* large numbers of pfSense users hitting Snort.org at    */
/* the same minute past the hour for rules updates.       */
/**********************************************************/
if (config_get_path('installedpackages/suricata/config/0/autoruleupdatetime') === null ||
	config_get_path('installedpackages/suricata/config/0/autoruleupdatetime') == '00:05' ||
	strlen(config_get_path('installedpackages/suricata/config/0/autoruleupdatetime')) < 5) {
	config_set_path('installedpackages/suricata/config/0/autoruleupdatetime', "00:" . str_pad(strval(random_int(0,59)), 2, "00", STR_PAD_LEFT));
	$updated_cfg = true;
}

/**********************************************************/
/* Set default log size and retention limits if not set   */
/**********************************************************/
if (config_get_path('installedpackages/suricata/config/0/alert_log_retention') === null) {
	config_set_path('installedpackages/suricata/config/0/alert_log_retention', "336");
	$updated_cfg = true;
}
if (config_get_path('installedpackages/suricata/config/0/alert_log_limit_size') === null) {
	config_set_path('installedpackages/suricata/config/0/alert_log_limit_size', "500");
	$updated_cfg = true;
}

if (config_get_path('installedpackages/suricata/config/0/block_log_retention') === null) {
	config_set_path('installedpackages/suricata/config/0/block_log_retention', "336");
	$updated_cfg = true;
}
if (config_get_path('installedpackages/suricata/config/0/block_log_limit_size') === null) {
	config_set_path('installedpackages/suricata/config/0/block_log_limit_size', "500");
	$updated_cfg = true;
}

if (config_get_path('installedpackages/suricata/config/0/eve_log_retention') === null) {
	config_set_path('installedpackages/suricata/config/0/eve_log_retention', "168");
	$updated_cfg = true;
}
if (config_get_path('installedpackages/suricata/config/0/eve_log_limit_size') === null) {
	config_set_path('installedpackages/suricata/config/0/eve_log_limit_size', "5000");
	$updated_cfg = true;
}

if (config_get_path('installedpackages/suricata/config/0/http_log_retention') === null) {
	config_set_path('installedpackages/suricata/config/0/http_log_retention', "168");
	$updated_cfg = true;
}
if (config_get_path('installedpackages/suricata/config/0/http_log_limit_size') === null) {
	config_set_path('installedpackages/suricata/config/0/http_log_limit_size', "1000");
	$updated_cfg = true;
}

if (config_get_path('installedpackages/suricata/config/0/stats_log_retention') === null) {
	config_set_path('installedpackages/suricata/config/0/stats_log_retention', "168");
	$updated_cfg = true;
}
if (config_get_path('installedpackages/suricata/config/0/stats_log_limit_size') === null) {
	config_set_path('installedpackages/suricata/config/0/stats_log_limit_size', "500");
	$updated_cfg = true;
}

if (config_get_path('installedpackages/suricata/config/0/tls_log_retention') === null) {
	config_set_path('installedpackages/suricata/config/0/tls_log_retention', "336");
	$updated_cfg = true;
}
if (config_get_path('installedpackages/suricata/config/0/tls_log_limit_size') === null) {
	config_set_path('installedpackages/suricata/config/0/tls_log_limit_size', "500");
	$updated_cfg = true;
}
if (config_get_path('installedpackages/suricata/config/0/tls_certs_store_retention') === null) {
	config_set_path('installedpackages/suricata/config/0/tls_certs_store_retention', "168");
	$updated_cfg = true;
}

if (config_get_path('installedpackages/suricata/config/0/file_store_retention') === null) {
	config_set_path('installedpackages/suricata/config/0/file_store_retention', "168");
	$updated_cfg = true;
}

if (config_get_path('installedpackages/suricata/config/0/pkt_capture_file_retention') === null) {
	config_set_path('installedpackages/suricata/config/0/pkt_capture_file_retention', "168");
	$updated_cfg = true;
}

/**********************************************************/
/* Remove deprecated file-log settings from LOGS MGMT     */
/**********************************************************/
if (config_get_path('installedpackages/suricata/config/0/files_json_log_retention') !== null) {
	config_del_path('installedpackages/suricata/config/0/files_json_log_retention');
	$updated_cfg = true;
}
if (config_get_path('installedpackages/suricata/config/0/files_json_log_limit_size') !== null) {
	config_del_path('installedpackages/suricata/config/0/files_json_log_limit_size');
	$updated_cfg = true;
}

/**********************************************************/
/* Add new multiple alias & custom IP assignment feature  */
/* for Pass Lists by converting existing <address>        */
/* element for existing entries into an array. Migrate    */
/* any existing <address> to the new array structure.     */
/**********************************************************/
foreach (config_get_path('installedpackages/suricata/passlist/item', []) as $idx => $wlisti) {
	if (!is_array($wlisti['address']) && !empty($wlisti['address'])) {
		$tmp = $wlisti['address'];
		$wlisti['address'] = array();
		$wlisti['address']['item'] = array();
		$wlisti['address']['item'][] = $tmp;
		config_set_path("installedpackages/suricata/passlist/item/{$idx}", $wlisti);
		$updated_cfg = true;
	}
}

/***********************************************************/
/* Add new 'clear blocks' setting to remove blocked hosts  */
/* from the snort2c pf table that were added by the Legacy */
/* Blocking custom plugin. Default empty value to 'yes'.   */
/***********************************************************/
if (config_get_path('installedpackages/suricata/config/0/clearblocks') === null) {
	config_set_path('installedpackages/suricata/config/0/clearblocks', 'on');
	$updated_cfg = true;
}

/***********************************************************/
/* Process the interface-specific settings migration.      */
/***********************************************************/
foreach (config_get_path('installedpackages/suricata/rule', []) as $idx => &$pconfig) {
	$updated_intf_cfg = false;

	/***********************************************************/
	/* Add new run mode value and default it to 'autofp'.      */
	/***********************************************************/
	if (empty($pconfig['runmode'])) {
		$pconfig['runmode'] = "autofp";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/***********************************************************/
	/* Add new interface promisc mode value and default 'on'.  */
	/***********************************************************/
	if (empty($pconfig['intf_promisc_mode'])) {
		$pconfig['intf_promisc_mode'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/***********************************************************/
	/* Add new HTTP Log Extended Info setting if not present   */
	/***********************************************************/
	if (!isset($pconfig['http_log_extended'])) {
		$pconfig['http_log_extended'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/***********************************************************/
	/* Add new EVE logging settings if not present             */
	/***********************************************************/
	if (!isset($pconfig['eve_output_type'])) {
		$pconfig['eve_output_type'] = "regular";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_alerts_xff'])) {
		$pconfig['eve_log_alerts_xff'] = "off";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_alerts_xff_mode'])) {
		$pconfig['eve_log_alerts_xff_mode'] = "extra-data";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_alerts_xff_deployment'])) {
		$pconfig['eve_log_alerts_xff_deployment'] = "reverse";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_alerts_xff_header'])) {
		$pconfig['eve_log_alerts_xff_header'] = "X-Forwarded-For";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['eve_systemlog_facility'])) {
		$pconfig['eve_systemlog_facility'] = "local1";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['eve_systemlog_priority'])) {
		$pconfig['eve_systemlog_priority'] = "info";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_alerts'])) {
		$pconfig['eve_log_alerts'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_alerts_metadata'])) {
		$pconfig['eve_log_alerts_metadata'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_http'])) {
		$pconfig['eve_log_http'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_nfs'])) {
		$pconfig['eve_log_nfs'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_smb'])) {
		$pconfig['eve_log_smb'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_krb5'])) {
		$pconfig['eve_log_krb5'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_ikev2'])) {
		$pconfig['eve_log_ikev2'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_tftp'])) {
		$pconfig['eve_log_tftp'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_dns'])) {
		$pconfig['eve_log_dns'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_tls'])) {
		$pconfig['eve_log_tls'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_ftp'])) {
		$pconfig['eve_log_ftp'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_http2'])) {
		$pconfig['eve_log_http2'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_rfb'])) {
		$pconfig['eve_log_rfb'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_dhcp'])) {
		$pconfig['eve_log_dhcp'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_dhcp_extended'])) {
		$pconfig['eve_log_dhcp_extended'] = "off";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_files'])) {
		$pconfig['eve_log_files'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_ssh'])) {
		$pconfig['eve_log_ssh'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_smtp'])) {
		$pconfig['eve_log_smtp'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['eve_log_flow'])) {
		$pconfig['eve_log_flow'] = "off";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}    
	if (!isset($pconfig['eve_log_drop'])) {
		$pconfig['eve_log_drop'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	if (!isset($pconfig['eve_log_http_extended_headers'])) {
		$pconfig['eve_log_http_extended_headers'] = "accept, accept-charset, accept-datetime, accept-encoding, accept-language, accept-range, age, allow, authorization, cache-control, ";
		$pconfig['eve_log_http_extended_headers'] .= "connection, content-encoding, content-language, content-length, content-location, content-md5, content-range, content-type, cookie, ";
		$pconfig['eve_log_http_extended_headers'] .= "date, dnt, etags, from, last-modified, link, location, max-forwards, origin, pragma, proxy-authenticate, proxy-authorization, range, ";
		$pconfig['eve_log_http_extended_headers'] .= "referrer, refresh, retry-after, server, set-cookie, te, trailer, transfer-encoding, upgrade, vary, via, warning, www-authenticate, ";
		$pconfig['eve_log_http_extended_headers'] .= "x-authenticated-user, x-flash-version, x-forwarded-proto, x-requested-with";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	if (!isset($pconfig['eve_log_smtp_extended_fields'])) {
		$pconfig['eve_log_smtp_extended_fields'] = "received, x-mailer, x-originating-ip, relays, reply-to, bcc";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/************************************************************/
	/* Add new App-Layer parser exception policy if not set     */
	/************************************************************/
	if (empty($pconfig['app_layer_error_policy'])) {
		$pconfig['app_layer_error_policy'] = "ignore";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/************************************************************/
	/* Create new DNS App-Layer parser settings if not set      */
	/************************************************************/
	if (empty($pconfig['dns_global_memcap'])) {
		$pconfig['dns_global_memcap'] = "16777216";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['dns_state_memcap'])) {
		$pconfig['dns_state_memcap'] = "524288";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['dns_request_flood_limit'])) {
		$pconfig['dns_request_flood_limit'] = "500";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['dns_parser_udp'])) {
		$pconfig['dns_parser_udp'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['dns_parser_tcp'])) {
		$pconfig['dns_parser_tcp'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['dns_parser_udp_ports'])) {
		$pconfig['dns_parser_udp_ports'] = "53";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['dns_parser_tcp_ports'])) {
		$pconfig['dns_parser_tcp_ports'] = "53";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/***********************************************************/
	/* Create new HTTP App-Layer parser settings if not set    */
	/***********************************************************/
	if (empty($pconfig['http_parser'])) {
		$pconfig['http_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['http_parser_memcap'])) {
		$pconfig['http_parser_memcap'] = "67108864";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/***********************************************************/
	/* Create new SMTP App-Layer parser settings if not set    */
	/***********************************************************/
	if (empty($pconfig['smtp_parser_decode_mime'])) {
		$pconfig['smtp_parser_decode_mime'] = "off";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_parser_decode_base64'])) {
		$pconfig['smtp_parser_decode_base64'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_parser_decode_quoted_printable'])) {
		$pconfig['smtp_parser_decode_quoted_printable'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_parser_extract_urls'])) {
		$pconfig['smtp_parser_extract_urls'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_parser_compute_body_md5'])) {
		$pconfig['smtp_parser_compute_body_md5'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/***********************************************************/
	/* Create new TLS App-Layer parser settings if not set    */
	/***********************************************************/
	if (empty($pconfig['tls_parser'])) {
		$pconfig['tls_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['tls_detect_ports'])) {
		$pconfig['tls_detect_ports'] = "443";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['tls_encrypt_handling'])) {
		$pconfig['tls_encrypt_handling'] = "default";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['tls_ja3_fingerprint'])) {
		$pconfig['tls_ja3_fingerprint'] = "auto";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/**********************************************************/
	/* Create other App-Layer parser settings if not set      */
	/**********************************************************/
	if (empty($pconfig['bittorrent_parser'])) {
		$pconfig['bittorrent_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['dcerpc_parser'])) {
		$pconfig['dcerpc_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['dhcp_parser'])) {
		$pconfig['dhcp_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['enip_parser'])) {
		$pconfig['enip_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['ftp_parser'])) {
		$pconfig['ftp_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['http2_parser'])) {
		$pconfig['http2_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['ikev2_parser'])) {
		$pconfig['ikev2_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['imap_parser'])) {
		$pconfig['imap_parser'] = "detection-only";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['mqtt_parser'])) {
		$pconfig['mqtt_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['msn_parser'])) {
		$pconfig['msn_parser'] = "detection-only";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['nfs_parser'])) {
		$pconfig['nfs_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['ntp_parser'])) {
		$pconfig['ntp_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['pgsql_parser'])) {
		$pconfig['pgsql_parser'] = "no";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['quic_parser'])) {
		$pconfig['quic_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['rdp_parser'])) {
		$pconfig['rdp_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['rfb_parser'])) {
		$pconfig['rfb_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['sip_parser'])) {
		$pconfig['sip_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['smb_parser'])) {
		$pconfig['smb_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['smtp_parser'])) {
		$pconfig['smtp_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['snmp_parser'])) {
		$pconfig['snmp_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['ssh_parser'])) {
		$pconfig['ssh_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['telnet_parser'])) {
		$pconfig['telnet_parser'] = "yes";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/**********************************************************/
	/* Create interface IP Reputation settings if not set     */
	/**********************************************************/
	if (empty($pconfig['enable_iprep'])) {
		$pconfig['enable_iprep'] = "off";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['host_memcap'])) {
		$pconfig['host_memcap'] = "16777216";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['host_hash_size'])) {
		$pconfig['host_hash_size'] = "4096";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['host_prealloc'])) {
		$pconfig['host_prealloc'] = "1000";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/**********************************************************/
	/* Create new interface flow/stream settings if not set   */
	/**********************************************************/
	if (!isset($pconfig['defrag_memcap_policy'])) {
		$pconfig['defrag_memcap_policy'] = "ignore";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['flow_memcap_policy'])) {
		$pconfig['flow_memcap_policy'] = "ignore";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (empty($pconfig['max_synack_queued'])) {
		$pconfig['max_synack_queued'] = "5";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['stream_bypass'])) {
		$pconfig['stream_bypass'] = "no";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['stream_drop_invalid'])) {
		$pconfig['stream_drop_invalid'] = "no";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['stream_memcap_policy'])) {
		$pconfig['stream_memcap_policy'] = "ignore";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['reassembly_memcap_policy'])) {
		$pconfig['reassembly_memcap_policy'] = "ignore";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['midstream_policy'])) {
		$pconfig['midstream_policy'] = "ignore";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (!isset($pconfig['stream_checksum_validation'])) {
		$pconfig['stream_checksum_validation'] = "on";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/**********************************************************/
	/* Create new interface IPS mode setting if not set       */
	/**********************************************************/
	if (empty($pconfig['ips_mode'])) {
		$pconfig['ips_mode'] = "ips_mode_legacy";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/**********************************************************/
	/* Create new interface block DROPs only mode setting if  */
	/* not already configured.  Default to "off".             */
	/**********************************************************/
	if (empty($pconfig['block_drops_only'])) {
		$pconfig['block_drops_only'] = "no";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/**********************************************************/
	/* Set default value for new interface snaplen parameter  */
	/* if one has not been previously configured.             */
	/**********************************************************/
	if (empty($pconfig['intf_snaplen'])) {
		$pconfig['intf_snaplen'] = "1518";
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/**********************************************************/
	/* Migrate old performance stats logging option to new    */
	/* control parameter.                                     */
	/**********************************************************/
	if (!isset($pconfig['enable_stats_collection'])) {
		$updated_intf_cfg = true;
		$updated_cfg = true;
		if ($pconfig['enable_stats_log'] == "on") {
			$pconfig['enable_stats_collection'] = "on";
		}
		else {
			$pconfig['enable_stats_collection'] = "off";
		}
	}

	/**********************************************************/
	/* Remove deprecated file-log configuration parameters.   */
	/* This functionality has been migrated into EVE logging. */
	/**********************************************************/
	if (isset($pconfig['enable_json_file_log'])) {
		unset($pconfig['enable_json_file_log']);
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (isset($pconfig['append_json_file_log'])) {
		unset($pconfig['append_json_file_log']);
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (isset($pconfig['enable_tracked_files_magic'])) {
		unset($pconfig['enable_tracked_files_magic']);
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}
	if (isset($pconfig['tracked_files_hash'])) {
		unset($pconfig['tracked_files_hash']);
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/**********************************************************/
	/* Remove deprecated Barnyard2 configuration parameters   */
	/* from this interface if any are present.                */
	/**********************************************************/
	$barnyard_params = array( 'barnyard_enable', 'barnyard_dump_payload', 'barnyard_mysql_enable',
							  'barnyard_syslog_enable', 'barnyard_syslog_local', 'barnyard_syslog_rhost',
							  'barnyard_syslog_dport', 'barnyard_syslog_proto', 'barnyard_syslog_opmode',
							  'barnyard_syslog_facility', 'barnyard_syslog_priority', 'barnyard_disable_sig_ref_tbl',
							  'barnyard_sensor_id', 'barnyard_sensor_name', 'barnyard_dbhost', 'barnyard_dbname',
							  'barnyard_dbuser', 'barnyard_bro_ids_enable', 'barnyard_bro_ids_rhost',
							  'barnyard_bro_ids_dport', 'barnconfigpassthru', 'barnyard_dbpwd', 'barnyard_show_year',
							  'barnyard_archive_enable', 'barnyard_archive_enable', 'barnyard_obfuscate_ip',
							  'barnyard_xff_logging', 'barnyard_xff_mode', 'barnyard_xff_deployment',
							  'barnyard_xff_header', 'unified2_log_limit', 'u2_archive_log_retention' );
	foreach ($barnyard_params as $param) {
		if (isset($pconfig[$param])) {
			unset($pconfig[$param]);
			$updated_intf_cfg = true;
			$updated_cfg = true;
		}
	}

	/**********************************************************/
	/* Add new 'netmap_threads' parameter for the interface   */
	/* when using Inline IPS Mode. Default is 'auto'.         */
	/**********************************************************/
	if (!isset($pconfig['ips_netmap_threads'])) {
		$updated_intf_cfg = true;
		$updated_cfg = true;
		$pconfig['ips_netmap_threads'] = 'auto';
	}

	/**********************************************************/
	/* Add new 'autofp-scheduler' parameter for the interface */
	/* when using 'autofp" runmode.                           */
	/**********************************************************/
	if (!isset($pconfig['autofp_scheduler'])) {
		$updated_intf_cfg = true;
		$updated_cfg = true;
		$pconfig['autofp_scheduler'] = 'hash';
	}

	/**********************************************************/
	/* Set new minimum TCP Stream_Memcap value to 256 MB.     */
	/* Increase existing value to at least 256 MB, but leave  */
	/* larger values untouched.                               */
	/**********************************************************/
	if ((int)$pconfig['stream_memcap'] < 268435456) {
		$pconfig['stream_memcap'] = '268435456';
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/**********************************************************/
	/* Set new minimum Flow_Memcap value to 128 MB. Increase  */
	/* existing value to at least 128 MB, but leave larger    */
	/* values untouched.                                      */
	/**********************************************************/
	if ((int)$pconfig['flow_memcap'] < 134217728) {
		$pconfig['flow_memcap'] = '134217728';
		$updated_intf_cfg = true;
		$updated_cfg = true;
	}

	/**********************************************************/
	/* If we updated this interface, write it to config array */
	/**********************************************************/
	if ($updated_intf_cfg === true) {
		config_set_path("installedpackages/suricata/rule/{$idx}", $pconfig);
	}
}

// Release prior config array reference as we are done with it
unset($pconfig);

// Log a message indicating what we did
if ($updated_cfg === true) {
	write_config("Updated Suricata package settings to new configuration format.");
	logger(LOG_NOTICE, localize_text("Package settings successfully migrated to new configuration format."), LOG_PREFIX_PKG_SURICATA);
}
else {
	logger(LOG_NOTICE, localize_text("Package settings configuration format is current."), LOG_PREFIX_PKG_SURICATA);
}
return true;
?>
