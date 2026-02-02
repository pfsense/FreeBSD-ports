<?php
/*
 * snort_preprocessors.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2008-2009 Robert Zelaya
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

require_once("guiconfig.inc");
require_once("/usr/local/pkg/snort/snort.inc");

global $g, $rebuild_rules;
$snortlogdir = SNORTLOGDIR;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
        header("Location: /snort/snort_interfaces.php");
        exit;
}

// Initialize multiple config engine arrays for supported preprocessors if necessary
config_init_path("installedpackages/snortglobal/rule/{$id}/frag3_engine/item");
config_init_path("installedpackages/snortglobal/rule/{$id}/stream5_tcp_engine/item");
config_init_path("installedpackages/snortglobal/rule/{$id}/http_inspect_engine/item");
config_init_path("installedpackages/snortglobal/rule/{$id}/ftp_server_engine/item");
config_init_path("installedpackages/snortglobal/rule/{$id}/ftp_client_engine/item");
config_init_path("installedpackages/snortglobal/rule/{$id}/arp_spoof_engine/item");

$a_nat = config_get_path('installedpackages/snortglobal/rule', []);

// Calculate the "next engine ID" to use for the multi-config engine arrays
$frag3_engine_next_id = count($a_nat[$id]['frag3_engine']['item']);
$stream5_tcp_engine_next_id = count($a_nat[$id]['stream5_tcp_engine']['item']);
$http_inspect_engine_next_id = count($a_nat[$id]['http_inspect_engine']['item']);
$ftp_server_engine_next_id = count($a_nat[$id]['ftp_server_engine']['item']);
$ftp_client_engine_next_id = count($a_nat[$id]['ftp_client_engine']['item']);
$arp_spoof_engine_next_id = count($a_nat[$id]['arp_spoof_engine']['item']);

$pconfig = array();
if (isset($id) && isset($a_nat[$id])) {
	$pconfig = $a_nat[$id];

	// Initialize multiple config engine arrays for supported preprocessors if necessary
	array_init_path($pconfig, 'frag3_engine/item');
	array_init_path($pconfig, 'stream5_tcp_engine/item');
	array_init_path($pconfig, 'http_inspect_engine/item');
	array_init_path($pconfig, 'ftp_server_engine/item');
	array_init_path($pconfig, 'ftp_client_engine/item');
	array_init_path($pconfig, 'arp_spoof_engine/item');

	/************************************************************/
	/* To keep new users from shooting themselves in the foot   */
	/* enable the most common required preprocessors by default */
	/* and set reasonable values for any options.               */
	/************************************************************/
	if (empty($pconfig['max_attribute_hosts']))
		$pconfig['max_attribute_hosts'] = '10000';
	if (empty($pconfig['max_attribute_services_per_host']))
		$pconfig['max_attribute_services_per_host'] = '10';

	if (empty($pconfig['max_paf']) && $pconfig['max_paf'] <> 0)
		$pconfig['max_paf'] = '16000';

	if (empty($pconfig['ftp_preprocessor']))
		$pconfig['ftp_preprocessor'] = 'on';
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
	if (empty($pconfig['ftp_telnet_ayt_attack_threshold']) && $pconfig['ftp_telnet_ayt_attack_threshold'] <> 0)
		$pconfig['ftp_telnet_ayt_attack_threshold'] = '20';

	if (empty($pconfig['sdf_alert_data_type']))
		$pconfig['sdf_alert_data_type'] = "Credit Card,Email Addresses,U.S. Phone Numbers,U.S. Social Security Numbers";
	if (empty($pconfig['sdf_alert_threshold']))
		$pconfig['sdf_alert_threshold'] = '25';
	if (empty($pconfig['sdf_mask_output']))
		$pconfig['sdf_mask_output'] = 'off';

	if (empty($pconfig['smtp_preprocessor']))
		$pconfig['smtp_preprocessor'] = 'on';
	if (empty($pconfig['smtp_memcap']))
		$pconfig['smtp_memcap'] = "838860";
	if (empty($pconfig['smtp_max_mime_mem']))
		$pconfig['smtp_max_mime_mem'] = "838860";
	if (empty($pconfig['smtp_b64_decode_depth']))
		$pconfig['smtp_b64_decode_depth'] = "0";
	if (empty($pconfig['smtp_qp_decode_depth']))
		$pconfig['smtp_qp_decode_depth'] = "0";
	if (empty($pconfig['smtp_bitenc_decode_depth']))
		$pconfig['smtp_bitenc_decode_depth'] = "0";
	if (empty($pconfig['smtp_uu_decode_depth']))
		$pconfig['smtp_uu_decode_depth'] = "0";
	if (empty($pconfig['smtp_email_hdrs_log_depth']) && $pconfig['smtp_email_hdrs_log_depth'] != '0')
		$pconfig['smtp_email_hdrs_log_depth'] = "1464";
	if (empty($pconfig['smtp_ignore_tls_data']))
		$pconfig['smtp_ignore_tls_data'] = 'on';
	if (empty($pconfig['smtp_log_mail_from']))
		$pconfig['smtp_log_mail_from'] = 'on';
	if (empty($pconfig['smtp_log_rcpt_to']))
		$pconfig['smtp_log_rcpt_to'] = 'on';
	if (empty($pconfig['smtp_log_filename']))
		$pconfig['smtp_log_filename'] = 'on';
	if (empty($pconfig['smtp_log_email_hdrs']))
		$pconfig['smtp_log_email_hdrs'] = 'on';

	if (empty($pconfig['dce_rpc_2']))
		$pconfig['dce_rpc_2'] = 'on';
	if (empty($pconfig['dns_preprocessor']))
		$pconfig['dns_preprocessor'] = 'on';
	if (empty($pconfig['ssl_preproc']))
		$pconfig['ssl_preproc'] = 'on';

	if (empty($pconfig['pop_preproc']))
		$pconfig['pop_preproc'] = 'on';
	if (empty($pconfig['pop_memcap']))
		$pconfig['pop_memcap'] = "838860";
	if (empty($pconfig['pop_b64_decode_depth']))
		$pconfig['pop_b64_decode_depth'] = "0";
	if (empty($pconfig['pop_qp_decode_depth']))
		$pconfig['pop_qp_decode_depth'] = "0";
	if (empty($pconfig['pop_bitenc_decode_depth']))
		$pconfig['pop_bitenc_decode_depth'] = "0";
	if (empty($pconfig['pop_uu_decode_depth']))
		$pconfig['pop_uu_decode_depth'] = "0";

	if (empty($pconfig['imap_preproc']))
		$pconfig['imap_preproc'] = 'on';
	if (empty($pconfig['imap_memcap']))
		$pconfig['imap_memcap'] = "838860";
	if (empty($pconfig['imap_b64_decode_depth']))
		$pconfig['imap_b64_decode_depth'] = "0";
	if (empty($pconfig['imap_qp_decode_depth']))
		$pconfig['imap_qp_decode_depth'] = "0";
	if (empty($pconfig['imap_bitenc_decode_depth']))
		$pconfig['imap_bitenc_decode_depth'] = "0";
	if (empty($pconfig['imap_uu_decode_depth']))
		$pconfig['imap_uu_decode_depth'] = "0";

	if (empty($pconfig['sip_preproc']))
		$pconfig['sip_preproc'] = 'on';
	if (empty($pconfig['other_preprocs']))
		$pconfig['other_preprocs'] = 'on';
	if (empty($pconfig['ssh_preproc']))
		$pconfig['ssh_preproc'] = 'on';
	if (!isset($pconfig['ssh_preproc_ports']))
		$pconfig['ssh_preproc_ports'] = '22';
	if (!isset($pconfig['ssh_preproc_max_encrypted_packets']))
		$pconfig['ssh_preproc_max_encrypted_packets'] = 20;
	if (!isset($pconfig['ssh_preproc_max_client_bytes']))
		$pconfig['ssh_preproc_max_client_bytes'] = 19600;
	if (!isset($pconfig['ssh_preproc_max_server_version_len']))
		$pconfig['ssh_preproc_max_server_version_len'] = 100;
	if (!isset($pconfig['ssh_preproc_enable_respoverflow']))
		$pconfig['ssh_preproc_enable_respoverflow'] = 'on';
	if (!isset($pconfig['ssh_preproc_enable_srvoverflow']))
		$pconfig['ssh_preproc_enable_srvoverflow'] = 'on';
	if (!isset($pconfig['ssh_preproc_enable_ssh1crc32']))
		$pconfig['ssh_preproc_enable_ssh1crc32'] = 'on';
	if (!isset($pconfig['ssh_preproc_enable_protomismatch']))
		$pconfig['ssh_preproc_enable_protomismatch'] = 'on';

	if (empty($pconfig['http_inspect']))
		$pconfig['http_inspect'] = "on";
	if (empty($pconfig['http_inspect_proxy_alert']))
		$pconfig['http_inspect_proxy_alert'] = "off";
	if (empty($pconfig['http_inspect_memcap']))
		$pconfig['http_inspect_memcap'] = "150994944";
	if (empty($pconfig['http_inspect_max_gzip_mem']))
		$pconfig['http_inspect_max_gzip_mem'] = "838860";

	if (empty($pconfig['frag3_max_frags']))
		$pconfig['frag3_max_frags'] = '8192';
	if (empty($pconfig['frag3_memcap']))
		$pconfig['frag3_memcap'] = '4194304';
	if (empty($pconfig['frag3_detection']))
		$pconfig['frag3_detection'] = 'on';

	if (empty($pconfig['stream5_reassembly']))
		$pconfig['stream5_reassembly'] = 'on';
	if (empty($pconfig['stream5_flush_on_alert']))
		$pconfig['stream5_flush_on_alert'] = 'off';
	if (empty($pconfig['stream5_prune_log_max']) && $pconfig['stream5_prune_log_max'] <> 0)
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

	if (empty($pconfig['pscan_protocol']))
		$pconfig['pscan_protocol'] = 'all';
	if (empty($pconfig['pscan_type']))
		$pconfig['pscan_type'] = 'all';
	if (empty($pconfig['pscan_memcap']))
		$pconfig['pscan_memcap'] = '10000000';
	if (empty($pconfig['pscan_sense_level']))
		$pconfig['pscan_sense_level'] = 'medium';
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']);

// Define the "disabled_preproc_rules.log" file for this interface
$disabled_rules_log = "{$if_friendly}_disabled_preproc_rules.log";

// Load the AUTO-DISABLED RULES file if requested
if ($_REQUEST['ajax']) {
	$contents = file_get_contents("{$snortlogdir}/{$disabled_rules_log}");
	print($contents);
	exit;
}

// Check for returned "selected alias" if action is import
if ($_GET['act'] == "import" && isset($_GET['varname']) && !empty($_GET['varvalue'])) {

	// Retrieve previously typed values we passed to SELECT ALIAS page
	$pconfig['sf_portscan'] = htmlspecialchars($_GET['sf_portscan'])? 'on' : 'off';
	$pconfig['pscan_ignore_scanners'] = htmlspecialchars($_GET['pscan_ignore_scanners']);
	$pconfig['pscan_ignore_scanned'] = htmlspecialchars($_GET['pscan_ignore_scanned']);
	$pconfig['pscan_protocol'] = htmlspecialchars($_GET['pscan_protocol']);
	$pconfig['pscan_type'] = htmlspecialchars($_GET['pscan_type']);
	$pconfig['pscan_memcap'] = htmlspecialchars($_GET['pscan_memcap']);
	$pconfig['pscan_sense_level'] = htmlspecialchars($_GET['pscan_sense_level']);

	// Now retrieve the "selected alias" returned from SELECT ALIAS page
	$pconfig[$_GET['varname']] = htmlspecialchars($_GET['varvalue']);
}

// Handle saving of ARP Spoofing MAC-to-IP address pairs
if ($_POST['arp_spoof_save']) {

	// Validate IP and MAC address values first
	if (filter_var($_POST['arp_spoof_ip_addr'], FILTER_VALIDATE_IP) && 
	   filter_var($_POST['arp_spoof_mac_addr'], FILTER_VALIDATE_MAC)) {
		// Set new or updated ARP Spoof engine values
		$engine = array();
		$engine['ip_addr'] = $_POST['arp_spoof_ip_addr'];
		$engine['mac_addr'] = $_POST['arp_spoof_mac_addr'];

		// See if editing an existing entry or adding a new one
		if (isset($_POST['eng_id']) && isset($a_nat[$id]['arp_spoof_engine']['item'][$_POST['eng_id']])) {
			$a_nat[$id]['arp_spoof_engine']['item'][$_POST['eng_id']] = $engine;
		}
		else {
			$a_nat[$id]['arp_spoof_engine']['item'][] = $engine;
		}

		// Save the updates to the Snort configuration
		config_set_path('installedpackages/snortglobal/rule', $a_nat);
		write_config("Snort pkg: Updated ARP Spoofing engine address pairs for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#preproc_arp_spoof_row");
		exit;
	}
	else {
		$input_errors[] = gettext("The IP address or MAC address failed to validate!  Change was discarded.");
	}
}

// Handle deleting of any of the multiple configuration engines.
// jQuery code is called dynamically from the page to set flags
// to indicate which multiple configuration engine to delete.
if ($_POST['del_http_inspect']) {
	if (isset($_POST['eng_id']) && isset($id) && isset($a_nat[$id])) {
		unset($a_nat[$id]['http_inspect_engine']['item'][$_POST['eng_id']]);
		config_set_path('installedpackages/snortglobal/rule', $a_nat);
		write_config("Snort pkg: deleted http_inspect engine for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#httpinspect_row");
		exit;
	}
}
elseif ($_POST['del_frag3']) {
	if (isset($_POST['eng_id']) && isset($id) && isset($a_nat[$id])) {
		unset($a_nat[$id]['frag3_engine']['item'][$_POST['eng_id']]);
		config_set_path('installedpackages/snortglobal/rule', $a_nat);
		write_config("Snort pkg: deleted frag3 engine for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#frag3_row");
		exit;
	}
}
elseif ($_POST['del_stream5_tcp']) {
	if (isset($_POST['eng_id']) && isset($id) && isset($a_nat[$id])) {
		unset($a_nat[$id]['stream5_tcp_engine']['item'][$_POST['eng_id']]);
		config_set_path('installedpackages/snortglobal/rule', $a_nat);
		write_config("Snort pkg: deleted stream5 engine for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#stream5_row");
		exit;
	}
}
elseif ($_POST['del_ftp_client']) {
	if (isset($_POST['eng_id']) && isset($id) && isset($a_nat[$id])) {
		unset($a_nat[$id]['ftp_client_engine']['item'][$_POST['eng_id']]);
		config_set_path('installedpackages/snortglobal/rule', $a_nat);
		write_config("Snort pkg: deleted ftp_client engine for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#ftp_telnet_row");
		exit;
	}
}
elseif ($_POST['del_ftp_server']) {
	if (isset($_POST['eng_id']) && isset($id) && isset($a_nat[$id])) {
		unset($a_nat[$id]['ftp_server_engine']['item'][$_POST['eng_id']]);
		config_set_path('installedpackages/snortglobal/rule', $a_nat);
		write_config("Snort pkg: deleted ftp_server engine for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#ftp_telnet_row");
		exit;
	}
}
elseif ($_POST['del_arp_spoof_engine']) {
	if (isset($_POST['eng_id']) && isset($id) && isset($a_nat[$id])) {
		unset($a_nat[$id]['arp_spoof_engine']['item'][$_POST['eng_id']]);
		config_set_path('installedpackages/snortglobal/rule', $a_nat);
		write_config("Snort pkg: deleted ARP spoof host address pair for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#preproc_arp_spoof_row");
		exit;
	}
}

if ($_POST['ResetAll']) {

	/* Reset all the preprocessor settings to defaults */
	$pconfig['perform_stat'] = "off";
	$pconfig['host_attribute_table'] = "off";
	$pconfig['max_attribute_hosts'] = '10000';
	$pconfig['max_attribute_services_per_host'] = '10';
	$pconfig['max_paf'] = '16000';
	$pconfig['stream5_reassembly'] = "on";
	$pconfig['stream5_flush_on_alert'] = 'off';
	$pconfig['stream5_prune_log_max'] = '1048576';
	$pconfig['stream5_track_tcp'] = "on";
	$pconfig['stream5_max_tcp'] = "262144";
	$pconfig['stream5_track_udp'] = "on";
	$pconfig['stream5_max_udp'] = "131072";
	$pconfig['stream5_track_icmp'] = "off";
	$pconfig['stream5_max_icmp'] = "65536";
	$pconfig['stream5_mem_cap'] = "8388608";
	$pconfig['stream5_udp_timeout'] = "30";
	$pconfig['stream5_icmp_timeout'] = "30";
	$pconfig['http_inspect'] = "on";
	$pconfig['http_inspect_proxy_alert'] = "off";
	$pconfig['http_inspect_memcap'] = "150994944";
	$pconfig['http_inspect_max_gzip_mem'] = "838860";
	$pconfig['other_preprocs'] = "on";
	$pconfig['ftp_preprocessor'] = "on";
	$pconfig['ftp_telnet_inspection_type'] = "stateful";
	$pconfig['ftp_telnet_alert_encrypted'] = "off";
	$pconfig['ftp_telnet_check_encrypted'] = "on";
	$pconfig['ftp_telnet_normalize'] = "on";
	$pconfig['ftp_telnet_detect_anomalies'] = "on";
	$pconfig['ftp_telnet_ayt_attack_threshold'] = "20";
	$pconfig['smtp_preprocessor'] = "on";
	$pconfig['smtp_memcap'] = "838860";
	$pconfig['smtp_max_mime_mem'] = "838860";
	$pconfig['smtp_b64_decode_depth'] = "0";
	$pconfig['smtp_qp_decode_depth'] = "0";
	$pconfig['smtp_bitenc_decode_depth'] = "0";
	$pconfig['smtp_uu_decode_depth'] = "0";
	$pconfig['smtp_email_hdrs_log_depth'] = "1464";
	$pconfig['smtp_ignore_data'] = 'off';
	$pconfig['smtp_ignore_tls_data'] = 'on';
	$pconfig['smtp_log_mail_from'] = 'on';
	$pconfig['smtp_log_rcpt_to'] = 'on';
	$pconfig['smtp_log_filename'] = 'on';
	$pconfig['smtp_log_email_hdrs'] = 'on';
	$pconfig['appid_preproc'] = "off";
	$pconfig['sf_appid_mem_cap'] = "256";
	$pconfig['sf_appid_statslog'] = "on";
	$pconfig['sf_appid_stats_period'] = "300";
	$pconfig['sf_portscan'] = "off";
	$pconfig['pscan_protocol'] = "all";
	$pconfig['pscan_type'] = "all";
	$pconfig['pscan_sense_level'] = "medium";
	$pconfig['pscan_ignore_scanners'] = "";
	$pconfig['pscan_ignore_scanned'] = "";
	$pconfig['pscan_memcap'] = '10000000';
	$pconfig['dce_rpc_2'] = "on";
	$pconfig['dns_preprocessor'] = "on";
	$pconfig['sensitive_data'] = "off";
	$pconfig['sdf_alert_data_type'] = "Credit Card,Email Addresses,U.S. Phone Numbers,U.S. Social Security Numbers";
	$pconfig['sdf_alert_threshold'] = "25";
	$pconfig['sdf_mask_output'] = "off";
	$pconfig['ssl_preproc'] = "on";
	$pconfig['pop_preproc'] = "on";
	$pconfig['pop_memcap'] = "838860";
	$pconfig['pop_b64_decode_depth'] = "0";
	$pconfig['pop_qp_decode_depth'] = "0";
	$pconfig['pop_bitenc_decode_depth'] = "0";
	$pconfig['pop_uu_decode_depth'] = "0";
	$pconfig['imap_preproc'] = "on";
	$pconfig['imap_memcap'] = "838860";
	$pconfig['imap_b64_decode_depth'] = "0";
	$pconfig['imap_qp_decode_depth'] = "0";
	$pconfig['imap_bitenc_decode_depth'] = "0";
	$pconfig['imap_uu_decode_depth'] = "0";
	$pconfig['sip_preproc'] = "on";
	$pconfig['dnp3_preproc'] = "off";
	$pconfig['modbus_preproc'] = "off";
	$pconfig['gtp_preproc'] = "off";
	$pconfig['ssh_preproc'] = "on";
	$pconfig['ssh_preproc_ports'] = '22';
	$pconfig['ssh_preproc_max_encrypted_packets'] = 20;
	$pconfig['ssh_preproc_max_client_bytes'] = 19600;
	$pconfig['ssh_preproc_max_server_version_len'] = 100;
	$pconfig['ssh_preproc_enable_respoverflow'] == 'on';
	$pconfig['ssh_preproc_enable_srvoverflow'] == 'on';
	$pconfig['ssh_preproc_enable_ssh1crc32'] == 'on';
	$pconfig['ssh_preproc_enable_protomismatch'] == 'on';
	$pconfig['preproc_auto_rule_disable'] = "off";
	$pconfig['protect_preproc_rules'] = "off";
	$pconfig['frag3_detection'] = "on";
	$pconfig['frag3_max_frags'] = "8192";
	$pconfig['frag3_memcap'] = "4194304";

	/* Log a message at the top of the page to inform the user */
	$savemsg = gettext("All preprocessor settings have been reset to their defaults.");
}

if ($_POST['save']) {
	$natent = array();
	$natent = $pconfig;

	// Validate SDF alert threshold and alert data type values if SDF is enabled
	if ($_POST['sensitive_data'] == 'on') {
		if ($_POST['sdf_alert_threshold'] < 1 || $_POST['sdf_alert_threshold'] > 65535)
			$input_errors[] = gettext("The value for Sensitive_Data_Alert_Threshold must be between 1 and 65,535.");
		if (empty($_POST['sdf_alert_data_type']))
			$input_errors[] = gettext("You must select at least one sensitive data type to inspect for when Sensitive Data detection is enabled.");
	}

	// Validate POP3 parameter values if POP3 Decoder is enabled
	if ($_POST['pop_preproc'] == 'on') {
		if ($_POST['pop_memcap'] < 3276 || $_POST['pop_memcap'] > 104857600)
			$input_errors[] = gettext("The value for POP3 Decoder Memory Cap must be between 3,276 and 104,857,600.");
		if ($_POST['pop_b64_decode_depth'] < -1 || $_POST['pop_b64_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for POP3 Decoder Base64 Decode Depth must be between -1 and 65,535.");
		if ($_POST['pop_qp_decode_depth'] < -1 || $_POST['pop_qp_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for POP3 Decoder Quoted-Printable (QP) Decode Depth must be between -1 and 65,535.");
		if ($_POST['pop_bitenc_decode_depth'] < -1 || $_POST['pop_bitenc_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for POP3 Decoder Non-Encoded MIME Extraction Depth must be between -1 and 65,535.");
		if ($_POST['pop_uu_decode_depth'] < -1 || $_POST['pop_uu_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for POP3 Decoder Unix-to-Unix (UU) Decode Depth must be between -1 and 65,535.");
	}

	// Validate IMAP parameter values if IMAP Decoder is enabled
	if ($_POST['imap_preproc'] == 'on') {
		if ($_POST['imap_memcap'] < 3276 || $_POST['imap_memcap'] > 104857600)
			$input_errors[] = gettext("The value for IMAP Decoder Memory Cap must be between 3,276 and 104,857,600.");
		if ($_POST['imap_b64_decode_depth'] < -1 || $_POST['imap_b64_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for IMAP Decoder Base64 Decode Depth must be between -1 and 65,535.");
		if ($_POST['imap_qp_decode_depth'] < -1 || $_POST['imap_qp_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for IMAP Decoder Quoted-Printable (QP) Decode Depth must be between -1 and 65,535.");
		if ($_POST['imap_bitenc_decode_depth'] < -1 || $_POST['imap_bitenc_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for IMAP Decoder Non-Encoded MIME Extraction Depth must be between -1 and 65,535.");
		if ($_POST['imap_uu_decode_depth'] < -1 || $_POST['imap_uu_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for IMAP Decoder Unix-to-Unix (UU) Decode Depth must be between -1 and 65,535.");
	}

	// Validate SMTP parameter values if SMTP Decoder is enabled
	if ($_POST['smtp_preprocessor'] == 'on') {
		if ($_POST['smtp_memcap'] < 3276 || $_POST['smtp_memcap'] > 104857600)
			$input_errors[] = gettext("The value for SMTP Decoder Memory Cap must be between 3,276 and 104,857,600.");
		if ($_POST['smtp_max_mime_mem'] < 3276 || $_POST['smtp_max_mime_mem'] > 104857600)
			$input_errors[] = gettext("The value for SMTP Decoder Maximum MIME Memory must be between 3,276 and 104,857,600.");
		if ($_POST['smtp_b64_decode_depth'] < -1 || $_POST['smtp_b64_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for SMTP Decoder Base64 Decode Depth must be between -1 and 65,535.");
		if ($_POST['smtp_qp_decode_depth'] < -1 || $_POST['smtp_qp_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for SMTP Decoder Quoted-Printable (QP) Decode Depth must be between -1 and 65,535.");
		if ($_POST['smtp_bitenc_decode_depth'] < -1 || $_POST['smtp_bitenc_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for SMTP Decoder Non-Encoded MIME Extraction Depth must be between -1 and 65,535.");
		if ($_POST['smtp_uu_decode_depth'] < -1 || $_POST['smtp_uu_decode_depth'] > 65535)
			$input_errors[] = gettext("The value for SMTP Decoder Unix-to-Unix (UU) Decode Depth must be between -1 and 65,535.");
		if ($_POST['smtp_email_hdrs_log_depth'] < 0 || $_POST['smtp_email_hdrs_log_depth'] > 20480)
			$input_errors[] = gettext("The value for SMTP Decoder E-Mail Headers Log Depth must be between 0 and 20,480.");
	}

	// Validate AppID parameter values if AppID Detector is enabled
	if ($_POST['appid_preproc'] == 'on') {
		if ($_POST['sf_appid_mem_cap'] < 32 || $_POST['sf_appid_mem_cap'] > 3000)
			$input_errors[] = gettext("The value for Application ID Memory Cap must be between 32 and 3000.");
		if ($_POST['sf_appid_stats_period'] < 60 || $_POST['sf_appid_stats_period'] > 3600)
			$input_errors[] = gettext("The value for Application ID Stats Period must be between 60 and 3600.");
	}

	// Validate Portscan Ignore_Scanners/Scanned parameters
	if ($_POST['sf_portscan'] == 'on') {
        	if (is_alias($_POST['pscan_ignore_scanners']) && (trim(filter_expand_alias($_POST['pscan_ignore_scanners'])) == ""))
			$input_errors[] = gettext("FQDN aliases are not supported in Snort for the PORTSCAN IGNORE_SCANNERS parameter.");
		if (is_alias($_POST['pscan_ignore_scanned']) && (trim(filter_expand_alias($_POST['pscan_ignore_scanned'])) == ""))
			$input_errors[] = gettext("FQDN aliases are not supported in Snort for the PORTSCAN IGNORE_SCANNED parameter.");
	}

	/* if no errors write to conf */
	if (!$input_errors) {
		/* post new options */
		if ($_POST['max_attribute_hosts'] != "") { $natent['max_attribute_hosts'] = $_POST['max_attribute_hosts']; }else{ $natent['max_attribute_hosts'] = "10000"; }
		if ($_POST['max_attribute_services_per_host'] != "") { $natent['max_attribute_services_per_host'] = $_POST['max_attribute_services_per_host']; }else{ $natent['max_attribute_services_per_host'] = "10"; }
		if ($_POST['max_paf'] != "") { $natent['max_paf'] = $_POST['max_paf']; }else{ $natent['max_paf'] = "16000"; }
		if ($_POST['http_inspect_memcap'] != "") { $natent['http_inspect_memcap'] = $_POST['http_inspect_memcap']; }else{ $natent['http_inspect_memcap'] = "150994944"; }
		if ($_POST['http_inspect_max_gzip_mem'] != "") { $natent['http_inspect_max_gzip_mem'] = $_POST['http_inspect_max_gzip_mem']; }else{ $natent['http_inspect_max_gzip_mem'] = "838860"; }
		if ($_POST['stream5_mem_cap'] != "") { $natent['stream5_mem_cap'] = $_POST['stream5_mem_cap']; }else{ $natent['stream5_mem_cap'] = "8388608"; }
		if ($_POST['stream5_prune_log_max'] != "") { $natent['stream5_prune_log_max'] = $_POST['stream5_prune_log_max']; }else{ $natent['stream5_prune_log_max'] = "1048576"; }
		if ($_POST['stream5_udp_timeout'] != "") { $natent['stream5_udp_timeout'] = $_POST['stream5_udp_timeout']; }else{ $natent['stream5_udp_timeout'] = "30"; }
		if ($_POST['stream5_icmp_timeout'] != "") { $natent['stream5_icmp_timeout'] = $_POST['stream5_icmp_timeout']; }else{ $natent['stream5_icmp_timeout'] = "30"; }
		if ($_POST['stream5_max_tcp'] != "") { $natent['stream5_max_tcp'] = $_POST['stream5_max_tcp']; }else{ $natent['stream5_max_tcp'] = "262144"; }
		if ($_POST['stream5_max_udp'] != "") { $natent['stream5_max_udp'] = $_POST['stream5_max_udp']; }else{ $natent['stream5_max_udp'] = "131072"; }
		if ($_POST['stream5_max_icmp'] != "") { $natent['stream5_max_icmp'] = $_POST['stream5_max_icmp']; }else{ $natent['stream5_max_icmp'] = "65536"; }
		if ($_POST['pscan_protocol'] != "") { $natent['pscan_protocol'] = $_POST['pscan_protocol']; }else{ $natent['pscan_protocol'] = "all"; }
		if ($_POST['pscan_type'] != "") { $natent['pscan_type'] = $_POST['pscan_type']; }else{ $natent['pscan_type'] = "all"; }
		if ($_POST['pscan_memcap'] != "") { $natent['pscan_memcap'] = $_POST['pscan_memcap']; }else{ $natent['pscan_memcap'] = "10000000"; }
		if ($_POST['pscan_sense_level'] != "") { $natent['pscan_sense_level'] = $_POST['pscan_sense_level']; }else{ $natent['pscan_sense_level'] = "medium"; }
		if ($_POST['pscan_ignore_scanners'] != "") { $natent['pscan_ignore_scanners'] = $_POST['pscan_ignore_scanners']; }else{ $natent['pscan_ignore_scanners'] = ""; }
		if ($_POST['pscan_ignore_scanned'] != "") { $natent['pscan_ignore_scanned'] = $_POST['pscan_ignore_scanned']; }else{ $natent['pscan_ignore_scanned'] = ""; }
		if ($_POST['frag3_max_frags'] != "") { $natent['frag3_max_frags'] = $_POST['frag3_max_frags']; }else{ $natent['frag3_max_frags'] = "8192"; }
		if ($_POST['frag3_memcap'] != "") { $natent['frag3_memcap'] = $_POST['frag3_memcap']; }else{ $natent['frag3_memcap'] = "4194304"; }
		if ($_POST['ftp_telnet_inspection_type'] != "") { $natent['ftp_telnet_inspection_type'] = $_POST['ftp_telnet_inspection_type']; }else{ $natent['ftp_telnet_inspection_type'] = "stateful"; }
		if ($_POST['ftp_telnet_ayt_attack_threshold'] != "") { $natent['ftp_telnet_ayt_attack_threshold'] = $_POST['ftp_telnet_ayt_attack_threshold']; }else{ $natent['ftp_telnet_ayt_attack_threshold'] = "20"; }
		if ($_POST['sdf_alert_threshold'] != "") { $natent['sdf_alert_threshold'] = $_POST['sdf_alert_threshold']; }else{ $natent['sdf_alert_threshold'] = "25"; }
		if ($_POST['pop_memcap'] != "") { $natent['pop_memcap'] = $_POST['pop_memcap']; }else{ $natent['pop_memcap'] = "838860"; }
		if ($_POST['pop_b64_decode_depth'] != "") { $natent['pop_b64_decode_depth'] = $_POST['pop_b64_decode_depth']; }else{ $natent['pop_b64_decode_depth'] = "0"; }
		if ($_POST['pop_qp_decode_depth'] != "") { $natent['pop_qp_decode_depth'] = $_POST['pop_qp_decode_depth']; }else{ $natent['pop_qp_decode_depth'] = "0"; }
		if ($_POST['pop_bitenc_decode_depth'] != "") { $natent['pop_bitenc_decode_depth'] = $_POST['pop_bitenc_decode_depth']; }else{ $natent['pop_bitenc_decode_depth'] = "0"; }
		if ($_POST['pop_uu_decode_depth'] != "") { $natent['pop_uu_decode_depth'] = $_POST['pop_uu_decode_depth']; }else{ $natent['pop_uu_decode_depth'] = "0"; }
		if ($_POST['imap_memcap'] != "") { $natent['imap_memcap'] = $_POST['imap_memcap']; }else{ $natent['imap_memcap'] = "838860"; }
		if ($_POST['imap_b64_decode_depth'] != "") { $natent['imap_b64_decode_depth'] = $_POST['imap_b64_decode_depth']; }else{ $natent['imap_b64_decode_depth'] = "0"; }
		if ($_POST['imap_qp_decode_depth'] != "") { $natent['imap_qp_decode_depth'] = $_POST['imap_qp_decode_depth']; }else{ $natent['imap_qp_decode_depth'] = "0"; }
		if ($_POST['imap_bitenc_decode_depth'] != "") { $natent['imap_bitenc_decode_depth'] = $_POST['imap_bitenc_decode_depth']; }else{ $natent['imap_bitenc_decode_depth'] = "0"; }
		if ($_POST['imap_uu_decode_depth'] != "") { $natent['imap_uu_decode_depth'] = $_POST['imap_uu_decode_depth']; }else{ $natent['imap_uu_decode_depth'] = "0"; }
		if ($_POST['smtp_memcap'] != "") { $natent['smtp_memcap'] = $_POST['smtp_memcap']; }else{ $natent['smtp_memcap'] = "838860"; }
		if ($_POST['smtp_max_mime_mem'] != "") { $natent['smtp_max_mime_mem'] = $_POST['smtp_max_mime_mem']; }else{ $natent['smtp_max_mime_mem'] = "838860"; }
		if ($_POST['smtp_b64_decode_depth'] != "") { $natent['smtp_b64_decode_depth'] = $_POST['smtp_b64_decode_depth']; }else{ $natent['smtp_b64_decode_depth'] = "0"; }
		if ($_POST['smtp_qp_decode_depth'] != "") { $natent['smtp_qp_decode_depth'] = $_POST['smtp_qp_decode_depth']; }else{ $natent['smtp_qp_decode_depth'] = "0"; }
		if ($_POST['smtp_bitenc_decode_depth'] != "") { $natent['smtp_bitenc_decode_depth'] = $_POST['smtp_bitenc_decode_depth']; }else{ $natent['smtp_bitenc_decode_depth'] = "0"; }
		if ($_POST['smtp_uu_decode_depth'] != "") { $natent['smtp_uu_decode_depth'] = $_POST['smtp_uu_decode_depth']; }else{ $natent['smtp_uu_decode_depth'] = "0"; }
		if ($_POST['smtp_email_hdrs_log_depth'] != "") { $natent['smtp_email_hdrs_log_depth'] = $_POST['smtp_email_hdrs_log_depth']; }else{ $natent['smtp_email_hdrs_log_depth'] = "1464"; }
		if ($_POST['sf_appid_mem_cap'] != "") { $natent['sf_appid_mem_cap'] = $_POST['sf_appid_mem_cap']; }else{ $natent['sf_appid_mem_cap'] = "256"; }
		if ($_POST['sf_appid_stats_period'] != "") { $natent['sf_appid_stats_period'] = $_POST['sf_appid_stats_period']; }else{ $natent['sf_appid_stats_period'] = "300"; }
		if ($_POST['ssh_preproc_ports'] != "") { $natent['ssh_preproc_ports'] = $_POST['ssh_preproc_ports']; }else{ $natent['ssh_preproc_ports'] = "22"; }
		if ($_POST['ssh_preproc_max_encrypted_packets'] != "") { $natent['ssh_preproc_max_encrypted_packets'] = $_POST['ssh_preproc_max_encrypted_packets']; }else{ $natent['ssh_preproc_max_encrypted_packets'] = 20; }
		if ($_POST['ssh_preproc_max_client_bytes'] != "") { $natent['ssh_preproc_max_client_bytes'] = $_POST['ssh_preproc_max_client_bytes']; }else{ $natent['ssh_preproc_max_client_bytes'] = 19600; }
		if ($_POST['ssh_preproc_max_server_version_len'] != "") { $natent['ssh_preproc_max_server_version_len'] = $_POST['ssh_preproc_max_server_version_len']; }else{ $natent['ssh_preproc_max_server_version_len'] = 100; }

		// Set SDF inspection types
		if (!empty($_POST['sdf_alert_data_type'])) {
			$natent['sdf_alert_data_type'] = implode(",",$_POST['sdf_alert_data_type']);
		}

		$natent['perform_stat'] = $_POST['perform_stat'] ? 'on' : 'off';
		$natent['host_attribute_table'] = $_POST['host_attribute_table'] ? 'on' : 'off';
		$natent['http_inspect'] = $_POST['http_inspect'] ? 'on' : 'off';
		$natent['http_inspect_proxy_alert'] = $_POST['http_inspect_proxy_alert'] ? 'on' : 'off';
		$natent['other_preprocs'] = $_POST['other_preprocs'] ? 'on' : 'off';
		$natent['ftp_preprocessor'] = $_POST['ftp_preprocessor'] ? 'on' : 'off';
		$natent['ftp_telnet_alert_encrypted'] = $_POST['ftp_telnet_alert_encrypted'] ? 'on' : 'off';
		$natent['ftp_telnet_check_encrypted'] = $_POST['ftp_telnet_check_encrypted'] ? 'on' : 'off';
		$natent['ftp_telnet_normalize'] = $_POST['ftp_telnet_normalize'] ? 'on' : 'off';
		$natent['ftp_telnet_detect_anomalies'] = $_POST['ftp_telnet_detect_anomalies'] ? 'on' : 'off';
		$natent['smtp_preprocessor'] = $_POST['smtp_preprocessor'] ? 'on' : 'off';
		$natent['smtp_ignore_data'] = $_POST['smtp_ignore_data'] ? 'on' : 'off';
		$natent['smtp_ignore_tls_data'] = $_POST['smtp_ignore_tls_data'] ? 'on' : 'off';
		$natent['smtp_log_mail_from'] = $_POST['smtp_log_mail_from'] ? 'on' : 'off';
		$natent['smtp_log_rcpt_to'] = $_POST['smtp_log_rcpt_to'] ? 'on' : 'off';
		$natent['smtp_log_filename'] = $_POST['smtp_log_filename'] ? 'on' : 'off';
		$natent['smtp_log_email_hdrs'] = $_POST['smtp_log_email_hdrs'] ? 'on' : 'off';
		$natent['sf_portscan'] = $_POST['sf_portscan'] ? 'on' : 'off';
		$natent['dce_rpc_2'] = $_POST['dce_rpc_2'] ? 'on' : 'off';
		$natent['dns_preprocessor'] = $_POST['dns_preprocessor'] ? 'on' : 'off';
		$natent['sensitive_data'] = $_POST['sensitive_data'] ? 'on' : 'off';
		$natent['sdf_mask_output'] = $_POST['sdf_mask_output'] ? 'on' : 'off';
		$natent['ssl_preproc'] = $_POST['ssl_preproc'] ? 'on' : 'off';
		$natent['pop_preproc'] = $_POST['pop_preproc'] ? 'on' : 'off';
		$natent['imap_preproc'] = $_POST['imap_preproc'] ? 'on' : 'off';
		$natent['dnp3_preproc'] = $_POST['dnp3_preproc'] ? 'on' : 'off';
		$natent['modbus_preproc'] = $_POST['modbus_preproc'] ? 'on' : 'off';
		$natent['sip_preproc'] = $_POST['sip_preproc'] ? 'on' : 'off';
		$natent['modbus_preproc'] = $_POST['modbus_preproc'] ? 'on' : 'off';
		$natent['gtp_preproc'] = $_POST['gtp_preproc'] ? 'on' : 'off';
		$natent['ssh_preproc'] = $_POST['ssh_preproc'] ? 'on' : 'off';
		$natent['ssh_preproc_enable_respoverflow'] = $_POST['ssh_preproc_enable_respoverflow'] ? 'on' : 'off';
		$natent['ssh_preproc_enable_srvoverflow'] = $_POST['ssh_preproc_enable_srvoverflow'] ? 'on' : 'off';
		$natent['ssh_preproc_enable_ssh1crc32'] = $_POST['ssh_preproc_enable_ssh1crc32'] ? 'on' : 'off';
		$natent['ssh_preproc_enable_protomismatch'] = $_POST['ssh_preproc_enable_protomismatch'] ? 'on' : 'off';
		$natent['preproc_auto_rule_disable'] = $_POST['preproc_auto_rule_disable'] ? 'on' : 'off';
		$natent['protect_preproc_rules'] = $_POST['protect_preproc_rules'] ? 'on' : 'off';
		$natent['frag3_detection'] = $_POST['frag3_detection'] ? 'on' : 'off';
		$natent['stream5_reassembly'] = $_POST['stream5_reassembly'] ? 'on' : 'off';
		$natent['stream5_flush_on_alert'] = $_POST['stream5_flush_on_alert'] ? 'on' : 'off';
		$natent['stream5_track_tcp'] = $_POST['stream5_track_tcp'] ? 'on' : 'off';
		$natent['stream5_track_udp'] = $_POST['stream5_track_udp'] ? 'on' : 'off';
		$natent['stream5_track_icmp'] = $_POST['stream5_track_icmp'] ? 'on' : 'off';
		$natent['appid_preproc'] = $_POST['appid_preproc'] ? 'on' : 'off';
		$natent['sf_appid_statslog'] = $_POST['sf_appid_statslog'] ? 'on' : 'off';
		$natent['arpspoof_preproc'] = $_POST['arpspoof_preproc'] ? 'on' : 'off';
		$natent['arp_unicast_detection'] = $_POST['arp_unicast_detection'] ? 'on' : 'off';

		if (isset($id) && isset($a_nat[$id])) {
			$a_nat[$id] = $natent;
			config_set_path('installedpackages/snortglobal/rule', $a_nat);
			write_config("Snort pkg: saved modified preprocessor settings for {$a_nat[$id]['interface']}.");
		}

		/*************************************************/
		/* Update the snort.conf file and rebuild the    */
		/* rules for this interface.                     */
		/*************************************************/
		$rebuild_rules = true;
		snort_generate_conf($natent);
		$rebuild_rules = false;

		/* If 'preproc_auto_rule_disable' is off, then clear log file */
		if ($natent['preproc_auto_rule_disable'] == 'off')
			unlink_if_exists("{$snortlogdir}/{$disabled_rules_log}");

		/* If Snort is running, a restart is required   */
		/* in order to pick up any preprocessor setting */
		/* changes.                                     */
		$if_real = get_real_interface($a_nat[$id]['interface']);
		if (snort_is_running($a_nat[$id]['uuid'])) {
			logger(LOG_NOTICE, localize_text("restarting on interface %s due to Preprocessor configuration change.", convert_real_interface_to_friendly_descr($if_real)), LOG_PREFIX_PKG_SNORT);
			snort_stop($a_nat[$id], $if_real);
			snort_start($a_nat[$id], $if_real, TRUE);
			$savemsg = gettext("Snort has been restarted on interface " . convert_real_interface_to_friendly_descr($if_real) . " because Preprocessor changes require a restart.");
		}

		/* Sync to configured CARP slaves if any are enabled */
		snort_sync_on_changes();

		/* after click go to this page */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: snort_preprocessors.php?id=$id");
		exit;
	}
	else
		$pconfig = $_POST;
}

if ($_POST['btn_import']) {
	if (is_uploaded_file($_FILES['host_attribute_file']['tmp_name'])) {
		$data = file_get_contents($_FILES['host_attribute_file']['tmp_name']);
		if ($data === false) {
			$input_errors[] = gettext("Error uploading file {$_FILES['host_attribute_file']}!");
			$pconfig = $_POST;
		}
		else {
			if (isset($id) && isset($a_nat[$id])) {
				$a_nat[$id]['host_attribute_table'] = "on";
				$a_nat[$id]['host_attribute_data'] = base64_encode($data);
				$pconfig['host_attribute_data'] = $a_nat[$id]['host_attribute_data'];
				$a_nat[$id]['max_attribute_hosts'] = $pconfig['max_attribute_hosts'];
				$a_nat[$id]['max_attribute_services_per_host'] = $pconfig['max_attribute_services_per_host'];
				write_config("Snort pkg: imported Host Attribute Table data for {$a_nat[$id]['interface']}.");
			}
			header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
			header( 'Cache-Control: no-store, no-cache, must-revalidate' );
			header( 'Cache-Control: post-check=0, pre-check=0', false );
			header( 'Pragma: no-cache' );
			header("Location: snort_preprocessors.php?id=$id");
			exit;
		}
	}
	else {
		$input_errors[] = gettext("No filename specified for import!");
		$pconfig = $_POST;
	}
}

if ($_POST['btn_edit_hat']) {
	if (isset($id) && isset($a_nat[$id])) {
		$a_nat[$id]['host_attribute_table'] = "on";
		$a_nat[$id]['max_attribute_hosts'] = $pconfig['max_attribute_hosts'];
		$a_nat[$id]['max_attribute_services_per_host'] = $pconfig['max_attribute_services_per_host'];
		config_set_path('installedpackages/snortglobal/rule', $a_nat);
		write_config("Snort pkg: modified Host Attribute Table data for {$a_nat[$id]['interface']}.");
		header("Location: snort_edit_hat_data.php?id=$id");
		exit;
	}
}

/* If Host Attribute Table option is enabled, but */
/* no Host Attribute data exists, flag an error.  */
if ($pconfig['host_attribute_table'] == 'on' && empty($pconfig['host_attribute_data']))
	$input_errors[] = gettext("The Host Attribute Table option is enabled, but no Host Attribute data has been loaded.  Data may be entered manually or imported from a suitable file.");

if (empty($if_friendly)) {
	$if_friendly = "None";
}
$pglinks = array("", "/snort/snort_interfaces.php", "/snort/snort_interfaces_edit.php?id={$id}", "@self");
$pgtitle = array("Services", "Snort", "Interface Settings", "{$if_friendly} - Preprocessors and Flow");
include("head.inc");

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg);
}
$tab_array = array();
	$tab_array[] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
	$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php?instance={$id}");
	$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
	$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);
$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
$tab_array = array();
	$tab_array[] = array($menu_iface . gettext("Settings"), false, "/snort/snort_interfaces_edit.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Categories"), false, "/snort/snort_rulesets.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Rules"), false, "/snort/snort_rules.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Variables"), false, "/snort/snort_define_servers.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Preprocs"), true, "/snort/snort_preprocessors.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/snort/snort_ip_reputation.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
display_top_tabs($tab_array, true);

print_callout('<p>' . gettext("Rules may be dependent on enabled preprocessors!  Disabling preprocessors may result in Snort startup failure unless ") . 
		gettext("all of the corresponding preprocessor-dependent rules are also disabled.  ") . gettext("Do not disable any default-enabled ") . 
		gettext("preprocessors on this page unless you are very skilled with using Snort.  If you experience Snort start-up errors or failures ") . 
		gettext("after making changes to preprocessors, trying resetting all preprocessor configurations to their defaults, and then ") . 
		gettext("attempt to start Snort.") . '</p>', 'info', 'Important Preprocessor Information');
?>

<form action="snort_preprocessors.php" method="post" enctype="multipart/form-data" class="form-horizontal" name="iform" id="iform">
<input id="id" name="id" type="hidden" value="<?=$id;?>"/>
<input name="eng_id" id="eng_id" type="hidden" value=""/>

<?php
	//----- START General Preprocessor settings -----
	$section = new Form_Section('Preprocessors Basic Configuration Settings', 'preproc_basic', COLLAPSIBLE|SEC_OPEN);
	$group = new Form_Group('Enable Performance Stats');
	$group->add(new Form_Checkbox(
		'perform_stat',
		'',
		'Collect Performance Statistics for this interface. Default is Not Checked.',
		$pconfig['perform_stat'] == 'on' ? true:false,
		'on'
	))->setHelp('Snort will automatically generate performance statistics for this interface.  Enabling this option may have a slight negative ' . 
		    'performance impact.  Statistics may be viewed on the LOGS tab for this interface.  Performance Statistics are disabled by default.');
	$section->add($group);
	$group = new Form_Group('Protect Customized Preprocessor Rules');
	$group->add(new Form_Checkbox(
		'protect_preproc_rules',
		'',
		'Enable this only if you maintain customized preprocessor text rules files for this interface. Default is Not Checked.',
		$pconfig['protect_preproc_rules'] == 'on' ? true:false,
		'on'
	))->setHelp('Enable this only if you use customized preprocessor text rules files and you do not want them overwritten by automatic Snort Subscriber Rules updates.  ' . 
		    'This option is disabled when Snort Subscriber Rules download is not enabled on the Global Settings tab.  Most users should leave this option unchecked.');
	$section->add($group);
	$group = new Form_Group('Auto Rule Disable');
	$group->add(new Form_Checkbox(
		'preproc_auto_rule_disable',
		'',
		'Auto-disable text rules dependent on disabled preprocessors for this interface. Default is Not Checked.',
		$pconfig['preproc_auto_rule_disable'] == 'on' ? true:false,
		'on'
	));
	if (file_exists("{$snortlogdir}/{$disabled_rules_log}") && filesize("{$snortlogdir}/{$disabled_rules_log}") > 0) {
		$btnview = new Form_Button(
			'view',
			'View',
			'#',
			'fa-regular fa-file-lines'
		);
		$btnview->removeClass('btn-primary')->addClass('btn-info')->addClass('btn-sm');
		$btnview->setAttribute('data-target', '#rulesviewer')->setAttribute('data-toggle', 'modal');
		$btnview->setAttribute('title', gettext('View rules auto-disabled due to dependencies on disabled preprocessors'));
		$group->add($btnview);
	}
	$group->setHelp('Enabling this option allows Snort to automatically disable any text rules containing rule options or content modifiers that are dependent upon the preprocessors ' . 
			'you have not enabled.  This may facilitate starting Snort without errors related to disabled preprocessors, but can substantially compromise the level of protection ' . 
			'by automatically disabling detection rules.  Enabling this feature will result in decreased protection from Snort.');
	$section->add($group);
	$section->addInput(new Form_Checkbox(
		'other_preprocs',
		'Enable RPC Decode and Back Orifice Detector',
		'Normalize/Decode RPC traffic and detects Back Orifice traffic on the network.  Default is Checked.',
		$pconfig['other_preprocs'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'dce_rpc_2',
		'Enable DCE/RPC2 Detection',
		'The DCE/RPC preprocessor detects and decodes SMB and DCE/RPC traffic.  Default is Checked.',
		$pconfig['dce_rpc_2'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'sip_preproc',
		'Enable SIP Detection',
		'The SIP preprocessor decodes SIP traffic and detects vulnerabilities.  Default is Checked.',
		$pconfig['sip_preproc'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'gtp_preproc',
		'Enable GTP Detection',
		'The GTP preprocessor decodes GPRS Tunneling Protocol traffic and detects intrusion attempts.  Default is Not Checked.',
		$pconfig['gtp_preproc'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'dns_preprocessor',
		'Enable DNS Detection',
		'The DNS preprocessor decodes DNS response traffic and detects vulnerabilities.  Default is Checked.',
		$pconfig['dns_preprocessor'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'ssl_preproc',
		'Enable SSL Data',
		'SSL data searches for irregularities during SSL protocol exchange.  Default is Checked.',
		$pconfig['ssl_preproc'] == 'on' ? true:false,
		'on'
	));
	print($section);
	//----- END General Preprocessor settings -----

	//----- START Host Attribute Table settings -----
	if ($pconfig['host_attribute_table']=="on") {
		$section = new Form_Section('Host Attribute Table', 'preproc_hat', COLLAPSIBLE|SEC_OPEN);
	} else {
		$section = new Form_Section('Host Attribute Table', 'preproc_hat', COLLAPSIBLE|SEC_CLOSED);
	}
	$section->addInput(new Form_Checkbox(
		'host_attribute_table',
		'Enable',
		'Use a Host Attribute Table file to auto-configure applicable preprocessors.  Default is Not Checked.',
		$pconfig['host_attribute_table'] == 'on' ? true:false,
		'on'
	));
	$group = new Form_Group('Host Attribute Data');
	$group->add(new Form_Input(
		'host_attribute_file',
		'Import from File',
		'file',
		$pconfig['host_attribute_file']
	))->setHelp('Choose a Host Attributes file.');
	$group->add(new Form_Button(
		'btn_import',
		' Import',
		null,
		'fa-solid fa-upload'
	))->removeClass('btn-primary')->addClass('btn-info')->addClass('btn-sm');
	$group->add(new Form_Button(
		'btn_edit_hat',
		empty($pconfig['host_attribute_data']) ? 'Create' : 'Edit',
		null,
		empty($pconfig['host_attribute_data']) ? 'fa-plus' : 'fa-solid fa-pencil'
	))->removeClass('btn-primary')->addClass('btn-success')->addClass('btn-sm');
	$group->setHelp('The Host Attribute Data file has a required specific format.  See the Snort manual for details.');
	$section->add($group);
	$group = new Form_Group('Maximum Hosts');
	$group->add(new Form_Input(
		'max_attribute_hosts',
		'',
		'number',
		$pconfig['max_attribute_hosts']
	))->setAttribute('min', '32')->setAttribute('max', '524288')->setHelp('Max number of hosts to read from the Attribute Table.  Minimum is 32 and maximum is 524288.  Default is 10000.');
	$group->setHelp('Sets a limit on the maximum number of hosts to read from the Attribute Table. If the number of hosts in ' . 
			'the table exceeds this value, an error is logged and the remainder of the hosts are ignored.');
	$section->add($group);
	$group = new Form_Group('Maximum Services Per Host');
	$group->add(new Form_Input(
		'max_attribute_services_per_host',
		'',
		'number',
		$pconfig['max_attribute_services_per_host']
	))->setAttribute('min', '1')->setAttribute('max', '65535')->setHelp('Max number of  per host services to read from the Attribute Table.  Minimum is 1 and maximum is 65535.  Default is 10.');
	$group->setHelp('Sets the per host limit of services to read from the Attribute Table. For a given host, if the number of services ' . 
			'read exceeds this value, an error is logged and the remainder of the services for that host are ignored.');
	$section->add($group);
	print($section);
	//----- END Host Atttribute Table settings -----

	//----- START Protocol Aware Flushing settings -----
	$section = new Form_Section('Protocol Aware Flushing', 'preproc_paf', COLLAPSIBLE|SEC_OPEN);
	$group = new Form_Group('Protocol Aware Flushing Maximum PDU');
	$group->add(new Form_Input(
		'max_paf',
		'',
		'number',
		$pconfig['max_paf']
	))->setAttribute('min', '0')->setAttribute('max', '63780')->setHelp('Max number of PDUs to be reassembled into a single PDU.  Minimum is 0 and maximum is 63780.  Default is 16000.');
	$group->setHelp('Multiple PDUs within a single TCP segment, as well as one PDU spanning multiple TCP segments, will be reassembled into ' . 
			'one PDU per packet for each PDU.  PDUs larger than the configured maximum will be split into multiple packets.');
	$section->add($group);
	print($section);
	//----- END Protocol Aware Flusing settings -----

	//-----	START SSH preproc settings -----
	if ($pconfig['ssh_preproc']=="on") {
		$section = new Form_Section('SSH Detection', 'preproc_ssh', COLLAPSIBLE|SEC_OPEN);
	} else {
		$section = new Form_Section('SSH Detection', 'preproc_ssh', COLLAPSIBLE|SEC_CLOSED);
	}
	$section->addInput(new Form_Checkbox(
		'ssh_preproc',
		'Enable SSH Detection',
		'The SSH preprocessor detects various Secure Shell exploit attempts.  Default is Checked.',
		$pconfig['ssh_preproc'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Input(
		'ssh_preproc_ports',
		'Server Ports',
		'text',
		$pconfig['ssh_preproc_ports']
	))->setHelp('Specifies which ports the SSH preprocessor should inspect traffic to.  For multiple ports, separate values with commas.  A configured Port Alias may also be specified.  Default port is 22.')->setAttribute('title', trim(filter_expand_alias($pconfig['ssh_preproc_ports'])));
	$section->addInput(new Form_Input(
		'ssh_preproc_max_encrypted_packets',
		'Max Encrypted Packets',
		'number',
		$pconfig['ssh_preproc_max_encrypted_packets']
	))->setHelp('Specifies the number of stream reassembled encrypted packets that Snort will inspect before ignoring a given SSH session.  ' . 
		'Once max_encrypted_packets packets have been seen, Snort ignores the session to increase performance.  Default is 20.');
	$section->addInput(new Form_Input(
		'ssh_preproc_max_client_bytes',
		'Max Client Bytes',
		'number',
		$pconfig['ssh_preproc_max_client_bytes']
	))->setHelp('Specifies the number of unanswered bytes allowed to be transferred before alerting on Challenge-Response Overflow or CRC 32.  ' . 
		'This number must be hit before max_encrypted_packets packets are sent, or else Snort will ignore the traffic.  Default is 19600.');
	$section->addInput(new Form_Input(
		'ssh_preproc_max_server_version_len',
		'Max Server Version Length',
		'number',
		$pconfig['ssh_preproc_max_server_version_len']
	))->setHelp('Specifies the maximum number of bytes allowed in the SSH server version string before alerting on the Secure CRT server version string overflow.  ' . 
		'Default is 100.');
	$section->addInput(new Form_Checkbox(
		'ssh_preproc_enable_respoverflow',
		'Enable Challenge-Response Overflow',
		'Enable checking for the Challenge-Response Overflow exploit.  Default is Checked.',
		$pconfig['ssh_preproc_enable_respoverflow'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'ssh_preproc_enable_srvoverflow',
		'Enable Secure CRT Exploit',
		'Enable checking for the Secure CRT exploit.  Default is Checked.',
		$pconfig['ssh_preproc_enable_srvoverflow'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'ssh_preproc_enable_ssh1crc32',
		'Enable CRC 32 Exploit',
		'Enable checking for the CRC 32 exploit.  Default is Checked.',
		$pconfig['ssh_preproc_enable_ssh1crc32'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'ssh_preproc_enable_protomismatch',
		'Enable Protocol Mismatch Exploit',
		'Enable checking for the Protocol Mismatch exploit.  Default is Checked.',
		$pconfig['ssh_preproc_enable_protomismatch'] == 'on' ? true:false,
		'on'
	));
	print($section);
?>
<!--	END SSH preproc settings  -->

<!--	START HTTP Inspect settings   -->
	<div class="panel panel-default" id="preproc_http">
		<div class="panel-heading">
			<h2 class="panel-title">HTTP Inspect<span class="widget-heading-icon"><a data-toggle="collapse" href="#preproc_http_panel-body"><i class="fa-solid fa-plus-circle"></i></a></span></h2>
		</div>
		<div id="preproc_http_panel-body" class="panel-body collapse in">
<?php
			$group = new Form_Group('Enable');
			$group->add(new Form_Checkbox(
				'http_inspect',
				'Enable',
				'Use HTTP Inspect to Normalize/Decode and detect HTTP traffic and protocol anomalies.  Default is Checked.',
				$pconfig['http_inspect'] == 'on' ? true:false,
				'on'
			));
			print($group);
			$group = new Form_Group('Proxy Alert');
			$group->add(new Form_Checkbox(
				'http_inspect_proxy_alert',
				'',
				'Enable global alerting on HTTP server proxy usage. Default is Not Checked.',
				$pconfig['http_inspect_proxy_alert'] == 'on' ? true:false,
				'on'
			))->setHelp('By adding Server Configurations below and enabling the <em>allow_proxy_use</em> parameter ' . 
				    'within them, alerts will be generated for web users that are not using the configured proxies ' . 
				    'or are using a rogue proxy server.  If users are not required to configure web proxy use, ' . 
				    'you may get a lot of proxy alerts.  Only use this feature with traditional proxy environments.  ' . 
				    'Blind firewall proxies do not count!');
			print($group);
			$group = new Form_Group('Memory Cap');
			$group->add(new Form_Input(
				'http_inspect_memcap',
				'',
				'number',
				$pconfig['http_inspect_memcap']
			))->setAttribute('min', '2304')->setAttribute('max', '603979776')->setHelp('Maximum memory in bytes to use for URI and Hostname logging.  Minimum is 2304 and maximum is 603979776 (576 MB).  Default is 150,994,944 (144 MB).');
			$group->setHelp('Sets the maximum amount of memory the preprocessor will use for logging the URI and Hostname data.  ' . 
					'This option determines the maximum HTTP sessions that will log URI and Hostname data at any given instant.  ' . 
					'Max Logged Sessions = MEMCAP / 2304');
			print($group);
			$group = new Form_Group('Maximum gzip Memory');
			$group->add(new Form_Input(
				'http_inspect_max_gzip_mem',
				'',
				'number',
				$pconfig['http_inspect_max_gzip_mem']
			))->setAttribute('min', '2304')->setAttribute('max', '603979776')->setHelp('Maximum memory in bytes to use for decompression.  Minimum is 3276.  Default is 838860.');
			$group->setHelp('This option determines the number of concurrent sessions that can be decompressed at any given instant.');
			print($group);
?>
			<div class="form-group">
				<label class="col-sm-2 control-label">
					<?=gettext("Server Configurations"); ?>
				</label>
				<div class="col-sm-10">
					<div class="table-responsive">
						<table class="table table-striped table-hover table-condensed">
							<thead>
								<tr>
									<th style="width:35%;"><?=gettext("Name")?></th>
									<th style="width:35%;"><?=gettext("Bind-To Address Alias")?></th>
									<th>
										<a href="snort_import_aliases.php?id=<?=$id?>&eng=http_inspect_engine" class="btn btn-sm btn-info" role="button" title="<?=gettext("Import server configuration from existing Aliases")?>">
											<i class="fa-solid fa-upload icon-embed-btn"></i>
											<?=gettext(' Import');?>
										</a>
										<a href="snort_httpinspect_engine.php?id=<?=$id?>&eng_id=<?=$http_inspect_engine_next_id?>" class="btn btn-sm btn-success" role="button" title="<?=gettext("Add a new server configuration")?>">
											<i class="fa-solid fa-plus icon-embed-btn"></i>
											<?=gettext(' Add');?>
										</a>
									</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($a_nat[$id]['http_inspect_engine']['item'] as $f => $v): ?>
								<tr>
									<td><?=gettext($v['name'])?></td>
									<td title="<?=trim(filter_expand_alias($v['bind_to']));?>"><?=gettext($v['bind_to'])?></td>
									<td>
										<a href="snort_httpinspect_engine.php?id=<?=$id;?>&eng_id=<?=$f;?>" class="fa-solid fa-pencil" title="<?=gettext("Edit this server configuration")?>"></a>
									<?php if ($v['bind_to'] != "all") : ?>
										<a href="#" class="fa-solid fa-trash-can no-confirm" onclick="del_eng('del_http_inspect', '<?=$f;?>');" title="<?=gettext("Delete this server configuration")?>"></a>
									<?php else : ?>
										<i class="fa-regular fa-trash-can text-muted" title="<?=gettext("Default server configuration cannot be deleted")?>"></i>
									<?php endif ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
<!--	END HTTP Inspect settings  -->

<!--	START Frag3 settings       -->
	<div class="panel panel-default" id="preproc_frag3">
		<div class="panel-heading">
			<h2 class="panel-title">Frag3 Target-Based IP Defragmentation<span class="widget-heading-icon"><a data-toggle="collapse" href="#preproc_frag3_panel-body"><i class="fa-solid fa-plus-circle"></i></a></span></h2>
		</div>
		<div id="preproc_frag3_panel-body" class="panel-body collapse in">
<?php
			$group = new Form_Group('Enable');
			$group->add(new Form_Checkbox(
				'frag3_detection',
				'Enable',
				'Use Frag3 Engine to detect IDS evasion attempts via target-based IP packet fragmentation.  Default is Checked.',
				$pconfig['frag3_detection'] == 'on' ? true:false,
				'on'
			));
			print($group);
			$group = new Form_Group('Memory Cap');
			$group->add(new Form_Input(
				'frag3_memcap',
				'Memory Cap',
				'number',
				$pconfig['frag3_memcap']
			))->setAttribute('min', '0')->setHelp('Memory cap (max memory in bytes) allocated for Frag3 fragment reassembly.  Default is 4194304 (4 MB).');
			print($group);
			$group = new Form_Group('Maximum Fragments');
			$group->add(new Form_Input(
				'frag3_max_frags',
				'Maximum Fragments',
				'number',
				$pconfig['frag3_max_frags']
			))->setAttribute('min', '0')->setHelp('Maximum number of simultaneous fragments to track.  Default is 8192.');
			print($group);
?>
			<div class="form-group">
				<label class="col-sm-2 control-label">
					<?=gettext("Server Configurations"); ?>
				</label>
				<div class="col-sm-10">
					<div class="table-responsive">
						<table class="table table-striped table-hover table-condensed">
							<thead>
								<tr>
									<th style="width:35%;"><?=gettext("Name")?></th>
									<th style="width:35%;"><?=gettext("Bind-To Address Alias")?></th>
									<th>
										<a href="snort_import_aliases.php?id=<?=$id?>&eng=frag3_engine" class="btn btn-sm btn-info" role="button" title="<?=gettext("Import server configuration from existing Aliases")?>">
											<i class="fa-solid fa-upload icon-embed-btn"></i>
											<?=gettext(' Import');?>
										</a>
										<a href="snort_frag3_engine.php?id=<?=$id?>&eng_id=<?=$frag3_engine_next_id?>" class="btn btn-sm btn-success" role="button" title="<?=gettext("Add a new server configuration")?>">
											<i class="fa-solid fa-plus icon-embed-btn"></i>
											<?=gettext(' Add');?>
										</a>
									</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($a_nat[$id]['frag3_engine']['item'] as $f => $v): ?>
								<tr>
									<td><?=gettext($v['name'])?></td>
									<td title="<?=trim(filter_expand_alias($v['bind_to']));?>"><?=gettext($v['bind_to'])?></td>
									<td>
										<a href="snort_frag3_engine.php?id=<?=$id;?>&eng_id=<?=$f;?>" class="fa-solid fa-pencil" title="<?=gettext("Edit this server configuration")?>"></a>
									<?php if ($v['bind_to'] != "all") : ?>
										<a href="#" class="fa-solid fa-trash-can no-confirm" onclick="del_eng('del_frag3', '<?=$f;?>');" title="<?=gettext("Delete this server configuration")?>"></a>
									<?php else : ?>
										<i class="fa-regular fa-trash-can text-muted" title="<?=gettext("Default server configuration cannot be deleted")?>"></i>
									<?php endif ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
<!--	END Frag3 settings     -->

<!--	START Stream5 settings -->
	<div class="panel panel-default" id="preproc_stream5">
		<div class="panel-heading">
			<h2 class="panel-title">Stream5 Target-Based Stream Reassembly<span class="widget-heading-icon"><a data-toggle="collapse" href="#preproc_stream5_panel-body"><i class="fa-solid fa-plus-circle"></i></a></span></h2>
		</div>
		<div id="preproc_stream5_panel-body" class="panel-body collapse in">
<?php
			$group = new Form_Group('Enable');
			$group->add(new Form_Checkbox(
				'stream5_reassembly',
				'Enable',
				'Use Stream5 session reassembly for TCP, UDP and/or ICMP traffic.  Default is Checked.',
				$pconfig['stream5_reassembly'] == 'on' ? true:false,
				'on'
			));
			print($group);
			$group = new Form_Group('Flush On Alert');
			$group->add(new Form_Checkbox(
				'stream5_flush_on_alert',
				'Flush On Alert',
				'Flush a TCP stream when an alert is generated on that stream (for backwards compatibility).  Default is Not Checked.',
				$pconfig['stream5_flush_on_alert'] == 'on' ? true:false,
				'on'
			));
			print($group);
			$group = new Form_Group('Prune Log Max');
			$group->add(new Form_Input(
				'stream5_prune_log_max',
				'',
				'number',
				$pconfig['stream5_prune_log_max']
			))->setAttribute('min', '0')->setAttribute('max', '1073741824')->setHelp('Prune Log Max Bytes.  Minimum is 0 (disabled), or if not disabled, 1024.  Maximum is 1073741824.  Default is 1048576 (1 MB).');
			$group->setHelp('Logs a message when a session terminates that was using more than the specified number of bytes.');
			print($group);
			$group = new Form_MultiCheckboxGroup('Protocol Tracking');
			$group->add(new Form_MultiCheckbox(
				'stream5_track_tcp',
				'',
				'Track and reassemble TCP sessions.  Default is Checked.',
				$pconfig['stream5_track_tcp'] === 'on' ? true:false,
				'on'
			));
			$group->add(new Form_MultiCheckbox(
				'stream5_track_udp',
				'',
				'Track and reassemble UDP sessions.  Default is Checked.',
				$pconfig['stream5_track_udp'] === 'on' ? true:false,
				'on'
			));
			$group->add(new Form_MultiCheckbox(
				'stream5_track_icmp',
				'',
				'Track and reassemble ICMP sessions.  Default is Not Checked.',
				$pconfig['stream5_track_icmp'] === 'on' ? true:false,
				'on'
			));
			print($group);
			$group = new Form_Group('Maximum TCP Sessions');
			$group->add(new Form_Input(
				'stream5_max_tcp',
				'Maximum TCP Sessions',
				'number',
				$pconfig['stream5_max_tcp']
			))->setAttribute('min', '1')->setAttribute('max', '1048576')->setHelp('Maximum number of concurrent TCP sessions that will be tracked.  Min is 1 and max is 1048576.  Default is 262144.');
			print($group);
			$group = new Form_Group('TCP Memory Cap');
			$group->add(new Form_Input(
				'stream5_mem_cap',
				'TCP Memory Cap',
				'number',
				$pconfig['stream5_mem_cap']
			))->setAttribute('min', '32768')->setAttribute('max', '1073741824')->setHelp('Memory (in bytes) for TCP packet storage.  Min is 32768 and max is 1073741824 (1 GB).  Default is 8388608 (8 MB).');
			print($group);
			$group = new Form_Group('Maximum UDP Sessions');
			$group->add(new Form_Input(
				'stream5_max_udp',
				'Maximum UDP Sessions',
				'number',
				$pconfig['stream5_max_udp']
			))->setAttribute('min', '1')->setAttribute('max', '131072')->setHelp('Maximum number of concurrent UDP sessions that will be tracked.  Min is 1 and max is 131072.  Default is 131072.');
			print($group);
			$group = new Form_Group('UDP Session Timeout');
			$group->add(new Form_Input(
				'stream5_udp_timeout',
				'UDP Session Timeout',
				'number',
				$pconfig['stream5_udp_timeout']
			))->setAttribute('min', '1')->setAttribute('max', '86400')->setHelp('UDP Session timeout in seconds.  Min is 1 and max is 86400 (1 day).  Default is 30.');
			print($group);
			$group = new Form_Group('Maximum ICMP Sessions');
			$group->add(new Form_Input(
				'stream5_max_icmp',
				'Maximum ICMP Sessions',
				'number',
				$pconfig['stream5_max_icmp']
			))->setAttribute('min', '1')->setAttribute('max', '131072')->setHelp('Maximum number of concurrent ICMP sessions that will be tracked.  Min is 1 and max is 131072.  Default is 65536.');
			print($group);
			$group = new Form_Group('ICMP Session Timeout');
			$group->add(new Form_Input(
				'stream5_icmp_timeout',
				'ICMP Session Timeout',
				'number',
				$pconfig['stream5_icmp_timeout']
			))->setAttribute('min', '1')->setAttribute('max', '86400')->setHelp('UDP Session timeout in seconds.  Min is 1 and max is 86400 (1 day).  Default is 30.');
			print($group);
?>
			<div class="form-group">
				<label class="col-sm-2 control-label">
					<?=gettext("Server Configurations"); ?>
				</label>
				<div class="col-sm-10">
					<div class="table-responsive">
						<table class="table table-striped table-hover table-condensed">
							<thead>
								<tr>
									<th style="width:35%;"><?=gettext("Name")?></th>
									<th style="width:35%;"><?=gettext("Bind-To Address Alias")?></th>
									<th>
										<a href="snort_import_aliases.php?id=<?=$id?>&eng=stream5_tcp_engine" class="btn btn-sm btn-info" role="button" title="<?=gettext("Import server configuration from existing Aliases")?>">
											<i class="fa-solid fa-upload icon-embed-btn"></i>
											<?=gettext(' Import');?>
										</a>
										<a href="snort_stream5_engine.php?id=<?=$id?>&eng_id=<?=$stream5_tcp_engine_next_id?>" class="btn btn-sm btn-success" role="button" title="<?=gettext("Add a new server configuration")?>">
											<i class="fa-solid fa-plus icon-embed-btn"></i>
											<?=gettext(' Add');?>
										</a>
									</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($a_nat[$id]['stream5_tcp_engine']['item'] as $f => $v): ?>
								<tr>
									<td><?=gettext($v['name'])?></td>
									<td title="<?=trim(filter_expand_alias($v['bind_to']));?>"><?=gettext($v['bind_to'])?></td>
									<td>
										<a href="snort_stream5_engine.php?id=<?=$id;?>&eng_id=<?=$f;?>" class="fa-solid fa-pencil" title="<?=gettext("Edit this server configuration")?>"></a>
									<?php if ($v['bind_to'] != "all") : ?>
										<a href="#" class="fa-solid fa-trash-can no-confirm" onclick="del_eng('del_stream5_tcp', '<?=$f;?>');" title="<?=gettext("Delete this server configuration")?>"></a>
									<?php else : ?>
										<i class="fa-regular fa-trash-can text-muted" title="<?=gettext("Default server configuration cannot be deleted")?>"></i>
									<?php endif ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php	//----- END Stream5 settings -----

	//----- START AppID settings -----
	if ($pconfig['appid_preproc']=="on") {
		$section = new Form_Section('Application ID Detection', 'preproc_appid', COLLAPSIBLE|SEC_OPEN);
	} else {
		$section = new Form_Section('Application ID Detection', 'preproc_appid', COLLAPSIBLE|SEC_CLOSED);
	}
	$section->addInput(new Form_Checkbox(
		'appid_preproc',
		'Enable',
		'Use OpenAppID to detect various applications.  Default is Not Checked.',
		$pconfig['appid_preproc'] == 'on' ? true:false,
		'on'
	));
	$group = new Form_Group('Memory Cap');
	$group->add(new Form_Input(
		'sf_appid_mem_cap',
		'',
		'number',
		$pconfig['sf_appid_mem_cap']
	))->setAttribute('min', '32')->setAttribute('max', '3000')->setHelp('Memory (in MB) for App ID structures.  Minimum is 32 and maximum is 3000 (3 GB).  Default is 256 (256 MB).');
	$group->setHelp('The memory cap in megabytes used by AppID internal structures in RAM.');
	$section->add($group);
	$section->addInput(new Form_Checkbox(
		'sf_appid_statslog',
		'AppID Stats Logging',
		'Enable OpenAppID statistics logging.  Default is Checked.  Log size and retention limits for AppID Stats Logging can be set on the LOG MGMT tab.',
		$pconfig['sf_appid_statslog'] == 'on' ? true:false,
		'on'
	));
	$group = new Form_Group('AppID Stats Period');
	$group->add(new Form_Input(
		'sf_appid_stats_period',
		'',
		'number',
		$pconfig['sf_appid_stats_period']
	))->setAttribute('min', '60')->setAttribute('max', '3600')->setHelp('Bucket size in seconds for AppID stats.  Minimum is 60 (1 min) and maximum is 3600 (1 hr).  Default is 300 (5 mins).');
	$group->setHelp('The bucket size in seconds used to collect AppID statistics.');
	$section->add($group);
	print($section);
	//----- END AppID settings -----

	//----- START Portscan setttings -----
	if ($pconfig['sf_portscan']=="on") {
		$section = new Form_Section('Portscan Detection', 'preproc_pscan', COLLAPSIBLE|SEC_OPEN);
	} else {
		$section = new Form_Section('Portscan Detection', 'preproc_pscan', COLLAPSIBLE|SEC_CLOSED);
	}
	$section->addInput(new Form_Checkbox(
		'sf_portscan',
		'Enable',
		'Use Portscan Detection to detect various types of port scans and sweeps.  Default is Not Checked.',
		$pconfig['sf_portscan'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Select(
		'pscan_protocol',
		'Protocol',
		$pconfig['pscan_protocol'],
		array( 'all' => 'all', 'tcp' => 'tcp', 'udp' => 'udp', 'icmp' => 'icmp', 'ip' => 'ip' )
	))->setHelp('Choose the Portscan protocol type to alert for (all, tcp, udp, icmp or ip).  The default is <em>all</em>.');
	$group = new Form_Group('Scan Type');
	$group->add(new Form_Select(
		'pscan_type',
		'',
		$pconfig['pscan_type'],
		array( 'all' => 'all', 'portscan' => 'portscan', 'portsweep' => 'portsweep', 'decoy_portscan' => 'decoy_portscan', 'distributed_portscan' => 'distributed_portscan' )
	))->setHelp('Choose the Portscan scan type to alert for.  The default is <em>all</em>.');
	$group->setHelp('PORTSCAN: one->one scan; one host scans multiple ports on another host.<br/>' . 
			'PORTSWEEP: one->many scan; one host scans a single port on multiple hosts.<br/>' . 
			'DECOY_PORTSCAN: one->one scan; attacker has spoofed source address inter-mixed with real scanning address.<br/>' . 
			'DISTRIBUTED_PORTSCAN: many->one scan; multiple hosts query one host for open services.<br/>' . 
			'ALL: alerts for all of the above scan types.');
	$section->add($group);
	$group = new Form_Group('Sensitivity');
	$group->add(new Form_Select(
		'pscan_sense_level',
		'',
		$pconfig['pscan_sense_level'],
		array( 'low' => 'low', 'medium' => 'medium', 'high' => 'high' )
	))->setHelp('Choose the Portscan sensitivity level (Low, Medium, High).  The default is <em>medium</em>.');
	$group->setHelp('LOW: alerts generated on error packets from the target host; this setting should see few false positives.<br/>' . 
			'MEDIUM: tracks connection counts, so will generate filtered alerts; may false positive on active hosts.<br/>' . 
			'HIGH: tracks hosts using a time window; will catch some slow scans, but is very sensitive to active hosts.');
	$section->add($group);
	$group = new Form_Group('Memory Cap');
	$group->add(new Form_Input(
		'pscan_memcap',
		'',
		'number',
		$pconfig['pscan_memcap']
	))->setAttribute('min', '0')->setHelp('Maximum memory in bytes to allocate for portscan detection.  Default is 10000000 (10 MB).');
	$group->setHelp('The maximum number of bytes to allocate for portscan detection.  The higher this number, the more nodes that can be tracked.');
	$section->add($group);
	$bind_to = new Form_Input(
		'pscan_ignore_scanners',
		'',
		'text',
		$pconfig['pscan_ignore_scanners']
	);
	$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['pscan_ignore_scanners'])));
	$bind_to->setHelp('Leave blank for default.  Default value is <em>$HOME_NET</em>');
	$btnaliases = new Form_Button(
		'btnSelectAlias',
		' ' . 'Aliases',
		'#',
		'fa-solid fa-search-plus'
	);
	$btnaliases->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-success')->addClass('btn-sm');
	$btnaliases->setAttribute('title', gettext("Select an existing IP alias"));
	$btnaliases->setAttribute('onclick', 'selectAlias(\'pscan_ignore_scanners\');');
	$group = new Form_Group('Ignore Scanners');
	$group->add($bind_to);
	$group->add($btnaliases);
	$group->setHelp('Ignores the specified entity as a source of scan alerts.  Entity must be either a defined alias, or a comma separated list of addresses with optional ports as ip[/cidr][port1 port2-port3].');
	$section->add($group);
	$bind_to = new Form_Input(
		'pscan_ignore_scanned',
		'',
		'text',
		$pconfig['pscan_ignore_scanned']
	);
	$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['pscan_ignore_scanned'])));
	$bind_to->setHelp('Leave blank for default.  Default value is <em>blank</em>, meaning ignore none.');
	$btnaliases = new Form_Button(
		'btnSelectAlias',
		' ' . 'Aliases',
		'#',
		'fa-solid fa-search-plus'
	);
	$btnaliases->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-success')->addClass('btn-sm');
	$btnaliases->setAttribute('title', gettext("Select an existing IP alias"));
	$btnaliases->setAttribute('onclick', 'selectAlias(\'pscan_ignore_scanned\');');
	$group = new Form_Group('Ignore Scanned');
	$group->add($bind_to);
	$group->add($btnaliases);
	$group->setHelp('Ignores the specified entity as a destination of scan alerts.  Entity must be either a defined alias, or a comma separated list of addresses with optional ports as ip[/cidr][port1 port2-port3].');
	$section->add($group);
	print($section);
	//----- END Portscan settings -----

	//----- START FTP/Telnet Global setttings -----
	if ($pconfig['ftp_preprocessor'] == "on") {
		$section = new Form_Section('FTP and Telnet Global Options', 'preproc_ftpglobal', COLLAPSIBLE|SEC_OPEN);
	} else {
		$section = new Form_Section('FTP and Telnet Global Options', 'preproc_ftpglobal', COLLAPSIBLE|SEC_CLOSED);
	}
	$section->addInput(new Form_Checkbox(
		'ftp_preprocessor',
		'Enable',
		'Normalize/Decode FTP and Telnet traffic and protocol anomalies.  Default is Checked.',
		$pconfig['ftp_preprocessor'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Select(
		'ftp_telnet_inspection_type',
		'Inspection Type',
		$pconfig['ftp_telnet_inspection_type'],
		array( 'stateful' => 'stateful', 'stateless' => 'stateless' )
	))->setHelp('Choose to operate in stateful or stateless mode.  The default is <em>stateful</em>.');
	$section->addInput(new Form_Checkbox(
		'ftp_telnet_check_encrypted',
		'Check Encrypted Traffic',
		'Continue to check an encrypted session for subsequent command to cease encryption.  Default is Checked.',
		$pconfig['ftp_telnet_check_encrypted'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'ftp_telnet_alert_encrypted',
		'Alert on Encrypted Commands',
		'Alert on encrypted FTP and Telnet command channels.  Default is Not Checked.',
		$pconfig['ftp_telnet_alert_encrypted'] == 'on' ? true:false,
		'on'
	));
	print($section);
	//----- END FTP/Telnet Global settings -----

	//----- START Telnet Protocol setttings -----
	if ($pconfig['ftp_preprocessor'] == "on" && $pconfig['ftp_telnet_normalize'] == "on") {
		$section = new Form_Section('Telnet Protocol Options', 'preproc_telnet', COLLAPSIBLE|SEC_OPEN);
	}
	else {
		$section = new Form_Section('Telnet Protocol Options', 'preproc_telnet', COLLAPSIBLE|SEC_CLOSED);
	}
	$section->addInput(new Form_Checkbox(
		'ftp_telnet_normalize',
		'Normalization',
		'Normalize Telnet traffic by eliminating Telnet escape sequences.  Default is Checked.',
		$pconfig['ftp_telnet_normalize'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'ftp_telnet_detect_anomalies',
		'Detect Anomalies',
		'Alert on Telnet subnegotiation begin without corresponding subnegotiation end.  Default is Checked.',
		$pconfig['ftp_telnet_detect_anomalies'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Input(
		'ftp_telnet_ayt_attack_threshold',
		'AYT Attack Threshold',
		'number',
		$pconfig['ftp_telnet_ayt_attack_threshold']
	))->setAttribute('min', '0')->setHelp('Are-You-There (AYT) command alert threshold.  Enter 0 to disable.  Default is 20.  Alert when the count of consecutive Telnet AYT commands reaches the value specified.');
	print($section);
	//----- END Telnet Protocol settings -----
?>
<!--	START FTP Protocol setttings -->
	<div class="panel panel-default" id="preproc_ftp">
		<div class="panel-heading">
			<h2 class="panel-title">FTP Protocol Options<span class="widget-heading-icon"><a data-toggle="collapse" href="#preproc_ftp_panel-body"><i class="fa-solid fa-plus-circle"></i></a></span></h2>
		</div>
		<div id="preproc_ftp_panel-body" class="panel-body collapse in">
			<div class="form-group">
				<label class="col-sm-2 control-label">
					<?=gettext("Client Configurations"); ?>
				</label>
				<div class="col-sm-10">
					<div class="table-responsive">
						<table class="table table-striped table-hover table-condensed">
							<thead>
								<tr>
									<th style="width:35%;"><?=gettext("Name")?></th>
									<th style="width:35%;"><?=gettext("Bind-To Address Alias")?></th>
									<th>
										<a href="snort_import_aliases.php?id=<?=$id?>&eng=ftp_client_engine" class="btn btn-sm btn-info" role="button" title="<?=gettext("Import client configuration from existing Aliases")?>">
											<i class="fa-solid fa-upload icon-embed-btn"></i>
											<?=gettext(' Import');?>
										</a>
										<a href="snort_ftp_client_engine.php?id=<?=$id?>&eng_id=<?=$ftp_client_engine_next_id?>" class="btn btn-sm btn-success" role="button" title="<?=gettext("Add a new client configuration")?>">
											<i class="fa-solid fa-plus icon-embed-btn"></i>
											<?=gettext(' Add');?>
										</a>
									</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($a_nat[$id]['ftp_client_engine']['item'] as $f => $v): ?>
								<tr>
									<td><?=gettext($v['name'])?></td>
									<td title="<?=trim(filter_expand_alias($v['bind_to']));?>"><?=gettext($v['bind_to'])?></td>
									<td>
										<a href="snort_ftp_client_engine.php?id=<?=$id;?>&eng_id=<?=$f;?>" class="fa-solid fa-pencil" title="<?=gettext("Edit this client configuration")?>"></a>
									<?php if ($v['bind_to'] != "all") : ?>
										<a href="#" class="fa-solid fa-trash-can no-confirm" onclick="del_eng('del_ftp_client', '<?=$f;?>');" title="<?=gettext("Delete this client configuration")?>"></a>
									<?php else : ?>
										<i class="fa-regular fa-trash-can text-muted" title="<?=gettext("Default client configuration cannot be deleted")?>"></i>
									<?php endif ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">
					<?=gettext("Server Configurations"); ?>
				</label>
				<div class="col-sm-10">
					<div class="table-responsive">
						<table class="table table-striped table-hover table-condensed">
							<thead>
								<tr>
									<th style="width:35%;"><?=gettext("Name")?></th>
									<th style="width:35%;"><?=gettext("Bind-To Address Alias")?></th>
									<th>
										<a href="snort_import_aliases.php?id=<?=$id?>&eng=ftp_server_engine" class="btn btn-sm btn-info" role="button" title="<?=gettext("Import server configuration from existing Aliases")?>">
											<i class="fa-solid fa-upload icon-embed-btn"></i>
											<?=gettext(' Import');?>
										</a>
										<a href="snort_ftp_server_engine.php?id=<?=$id?>&eng_id=<?=$ftp_server_engine_next_id?>" class="btn btn-sm btn-success" role="button" title="<?=gettext("Add a new server configuration")?>">
											<i class="fa-solid fa-plus icon-embed-btn"></i>
											<?=gettext(' Add');?>
										</a>
									</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($a_nat[$id]['ftp_server_engine']['item'] as $f => $v): ?>
								<tr>
									<td><?=gettext($v['name'])?></td>
									<td title="<?=trim(filter_expand_alias($v['bind_to']));?>"><?=gettext($v['bind_to'])?></td>
									<td>
										<a href="snort_ftp_server_engine.php?id=<?=$id;?>&eng_id=<?=$f;?>" class="fa-solid fa-pencil" title="<?=gettext("Edit this server configuration")?>"></a>
									<?php if ($v['bind_to'] != "all") : ?>
										<a href="#" class="fa-solid fa-trash-can no-confirm" onclick="del_eng('del_ftp_server', '<?=$f;?>');" title="<?=gettext("Delete this server configuration")?>"></a>
									<?php else : ?>
										<i class="fa-regular fa-trash-can text-muted" title="<?=gettext("Default server configuration cannot be deleted")?>"></i>
									<?php endif ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
<!--	END FTP Protocol settings  -->

<?php	//----- START Sensitive Data setttings -----
	if ($pconfig['sensitive_data']=="on") {
		$section = new Form_Section('Sensitive Data Detection', 'preproc_sdf', COLLAPSIBLE|SEC_OPEN);
	} else {
		$section = new Form_Section('Sensitive Data Detection', 'preproc_sdf', COLLAPSIBLE|SEC_CLOSED);
	}
	$section->addInput(new Form_Checkbox(
		'sensitive_data',
		'Enable',
		'Sensitive data searches for credit card numbers, Social Security numbers and e-mail addresses in data.  Default is Not Checked.' . 
		'To enable this preprocessor, you must enable the Snort Subscriber Rules on the GLOBAL SETTINGS tab.',
		$pconfig['sensitive_data'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Select(
		'sdf_alert_data_type',
		'Inspect For',
		explode(',', (string)$pconfig['sdf_alert_data_type']),
		array( 'Credit Card' => 'Credit Card', 'Email Addresses' => 'Email Addresses', 'U.S. Phone Numbers' => 'U.S. Phone Numbers', 'U.S. Social Security Numbers' => 'U.S. Social Security Numbers' ),
		true
	))->setHelp('Choose which types of sensitive data to detect.  Use CTRL + Click for multiple selections.');
	$group = new Form_Group('Alert Threshold');
	$group->add(new Form_Input(
		'sdf_alert_threshold',
		'',
		'number',
		$pconfig['sdf_alert_threshold']
	))->setAttribute('min', '0')->setHelp('Personally Identifiable Information (PII) combination alert threshold.  Default is 25.');
	$group->setHelp('This value sets the number of PII combinations required to trigger an alert.  This should be set higher than the highest individual count in any of your sd_pattern rules.');
	$section->add($group);
	$section->addInput(new Form_Checkbox(
		'sdf_mask_output',
		'Mask Output',
		'Replace all but last 4 digits of credit card and Social Security Numbers with X.  Default is Not Checked.',
		$pconfig['sdf_mask_output'] == 'on' ? true:false,
		'on'
	));
	print($section);
	//----- END Sensitive Data settings -----

	//----- START ARP Spoof Detection settings -----
?>
	<div class="panel panel-default" id="preproc_arp_spoof_row">
		<div class="panel-heading">
			<h2 class="panel-title">ARP Spoof Detection<span class="widget-heading-icon"><a data-toggle="collapse" href="#preproc_arp_panel-body"><i class="fa-solid fa-plus-circle"></i></a></span></h2>
		</div>
		<div id="preproc_arp_panel-body" class="panel-body collapse in">
<?php
			$group = new Form_Group('Enable ARP Spoof Detection');
			$group->add(new Form_Checkbox(
				'arpspoof_preproc',
				'Enable ARP Spoof Detection',
				'Detects ARP attacks and inconsistent Ethernet to IP mapping.  Default is Not Checked.',
				$pconfig['arpspoof_preproc'] == 'on' ? true:false,
				'on'
			));
			print($group);
			$group = new Form_Group('Enable Unicast ARP Checks');
			$group->add(new Form_Checkbox(
				'arp_unicast_detection',
				'Enable Unicast ARP Checks',
				'Checks for unicast ARP requests.  Default is Not Checked.',
				$pconfig['arp_unicast_detection'] == 'on' ? true:false,
				'on'
			));
			print($group);
?>
			<!--  Populate MAC-to-IP Address pairs table  -->
			<div class="form-group">
				<label class="col-sm-2 control-label">
					<?=gettext("MAC-to-IP Address Pairings"); ?>
				</label>
				<div class="col-sm-10">
					<div class="table-responsive">
						<table class="table table-striped table-hover table-condensed">
							<thead>
								<tr>
									<th style="width:35%;"><?=gettext("MAC Address")?></th>
									<th style="width:35%;"><?=gettext("IP Address")?></th>
									<th>
										<a href="#" data-toggle="modal" data-target="#arp_spoof_addr_pair" data-eng_id="<?=$arp_spoof_engine_next_id;?>" class="btn btn-sm btn-success" role="button" title="<?=gettext("Add a new address pair entry")?>">
											<i class="fa-solid fa-plus icon-embed-btn"></i>
											<?=gettext(' Add');?>
										</a>
									</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($a_nat[$id]['arp_spoof_engine']['item'] as $f => $v): ?>
								<tr>
									<td><?=gettext($v['mac_addr'])?></td>
									<td><?=gettext($v['ip_addr'])?></td>
									<td>
										<a href="#" data-toggle="modal" data-target="#arp_spoof_addr_pair" data-eng_id="<?=$f;?>" data-arp_spoof_mac="<?=$v['mac_addr'];?>" data-arp_spoof_ip="<?=$v['ip_addr'];?>" class="fa-solid fa-pencil" title="<?=gettext("Edit this address pair entry")?>"></a>
										<a href="#" class="fa-solid fa-trash-can no-confirm" onclick="del_eng('del_arp_spoof_engine', '<?=$f;?>');" title="<?=gettext("Delete this adress pair entry")?>"></a>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php
	//----- END ARP Spoof Detection settings -----

	//----- START POP3 Decoder settings -----
	if ($pconfig['pop_preproc']=="on") {
		$section = new Form_Section('POP3 Decoder Settings', 'preproc_pop3', COLLAPSIBLE|SEC_OPEN);
	} else {
		$section = new Form_Section('POP3 Decoder Settings', 'preproc_pop3', COLLAPSIBLE|SEC_CLOSED);
	}
	$section->addInput(new Form_Checkbox(
		'pop_preproc',
		'Enable POP3 Decoder',
		'Normalize/Decode POP3 protocol for enforcement and buffer overflows.  Default is Checked.',
		$pconfig['pop_preproc'] == 'on' ? true:false,
		'on'
	));
	$group = new Form_Group('Memory Cap');
	$group->add(new Form_Input(
		'pop_memcap',
		'',
		'number',
		$pconfig['pop_memcap']
	))->setAttribute('min', '3276')->setAttribute('max', '104857600')->setHelp('Maximum memory in bytes to use for decoding attachments.  Default is 838860.');
	$group->setHelp('The minimum value is 3276, and the maximum value is 104857600 (100 MB).  ' . 
			'A POP preprocessor alert with sid 3 is generated (when enabled) if this limit is exceeded.');
	$section->add($group);
	$group = new Form_Group('Base64 Decoding Depth');
	$group->add(new Form_Input(
		'pop_b64_decode_depth',
		'',
		'number',
		$pconfig['pop_b64_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Depth in bytes to decode base64 encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 turns off the base64 decoding of MIME attachments.  ' . 
			'A value of 0 sets the decoding of base64 encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the decoding of base64 MIME attachments, and applies per attachment.  ' . 
			'A POP preprocessor alert with sid 4 is generated (if enabled) when the decoding fails');
	$section->add($group);
	$group = new Form_Group('Quoted Printable Decoding Depth');
	$group->add(new Form_Input(
		'pop_qp_decode_depth',
		'',
		'number',
		$pconfig['pop_qp_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Byte depth to decode Quoted Printable (QP) encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 turns off the QP decoding of MIME attachments.  ' . 
			'A value of 0 sets the decoding of QP encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the decoding of QP MIME attachments, and applies per attachment.  ' . 
			'A POP preprocessor alert with sid 5 is generated (if enabled) when the decoding fails');
	$section->add($group);
	$group = new Form_Group('Non-Encoded MIME Extraction Depth');
	$group->add(new Form_Input(
		'pop_bitenc_decode_depth',
		'',
		'number',
		$pconfig['pop_bitenc_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Depth in bytes to extract non-encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 turns off the extraction of non-encoded of MIME attachments.  ' . 
			'A value of 0 sets the extraction of non-encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the extraction of non-encoded MIME attachments, and applies per attachment.');
	$section->add($group);

	$group = new Form_Group('Unix-to-Unix Decoding Depth');
	$group->add(new Form_Input(
		'pop_uu_decode_depth',
		'',
		'number',
		$pconfig['pop_uu_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Depth in bytes to decode Unix-to-Unix (UU) encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 the UU decoding of MIME attachments.  ' . 
			'A value of 0 sets the decoding of UU encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the decoding of UU MIME attachments, and applies per attachment.  ' . 
			'A POP preprocessor alert with sid 7 is generated (if enabled) when the decoding fails');
	$section->add($group);
	print($section);
	//----- END POP3 Decoder settings -----

	//----- START IMAP Decoder settings -----
	if ($pconfig['imap_preproc']=="on") {
		$section = new Form_Section('IMAP Decoder Settings', 'preproc_imap', COLLAPSIBLE|SEC_OPEN);
	} else {
		$section = new Form_Section('IMAP Decoder Settings', 'preproc_imap', COLLAPSIBLE|SEC_CLOSED);
	}
	$section->addInput(new Form_Checkbox(
		'imap_preproc',
		'Enable IMAP Decoder',
		'Normalize/Decode IMAP protocol for enforcement and buffer overflows.  Default is Checked.',
		$pconfig['imap_preproc'] == 'on' ? true:false,
		'on'
	));
	$group = new Form_Group('Memory Cap');
	$group->add(new Form_Input(
		'imap_memcap',
		'',
		'number',
		$pconfig['imap_memcap']
	))->setAttribute('min', '3276')->setAttribute('max', '104857600')->setHelp('Maximum memory in bytes to use for decoding attachments.  Default is 838860.');
	$group->setHelp('The minimum value is 3276, and the maximum value is 104857600 (100 MB).  ' . 
			'An IMAP preprocessor alert with sid 3 is generated (when enabled) if this limit is exceeded.');
	$section->add($group);
	$group = new Form_Group('Base64 Decoding Depth');
	$group->add(new Form_Input(
		'imap_b64_decode_depth',
		'',
		'number',
		$pconfig['imap_b64_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Depth in bytes to decode base64 encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 turns off the base64 decoding of MIME attachments.  ' . 
			'A value of 0 sets the decoding of base64 encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the decoding of base64 MIME attachments, and applies per attachment.  ' . 
			'An IMAP preprocessor alert with sid 4 is generated (if enabled) when the decoding fails');
	$section->add($group);
	$group = new Form_Group('Quoted Printable Decoding Depth');
	$group->add(new Form_Input(
		'imap_qp_decode_depth',
		'',
		'number',
		$pconfig['imap_qp_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Byte depth to decode Quoted Printable (QP) encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 turns off the QP decoding of MIME attachments.  ' . 
			'A value of 0 sets the decoding of QP encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the decoding of QP MIME attachments, and applies per attachment.  ' . 
			'An IMAP preprocessor alert with sid 5 is generated (if enabled) when the decoding fails');
	$section->add($group);
	$group = new Form_Group('Non-Encoded MIME Extraction Depth');
	$group->add(new Form_Input(
		'imap_bitenc_decode_depth',
		'',
		'number',
		$pconfig['imap_bitenc_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Depth in bytes to extract non-encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 turns off the extraction of non-encoded of MIME attachments.  ' . 
			'A value of 0 sets the extraction of non-encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the extraction of non-encoded MIME attachments, and applies per attachment.');
	$section->add($group);

	$group = new Form_Group('Unix-to-Unix Decoding Depth');
	$group->add(new Form_Input(
		'imap_uu_decode_depth',
		'',
		'number',
		$pconfig['imap_uu_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Depth in bytes to decode Unix-to-Unix (UU) encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 the UU decoding of MIME attachments.  ' . 
			'A value of 0 sets the decoding of UU encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the decoding of UU MIME attachments, and applies per attachment.  ' . 
			'An IMAP preprocessor alert with sid 7 is generated (if enabled) when the decoding fails');
	$section->add($group);
	print($section);
	//----- END IMAP Decoder settings -----

	//----- START SMTP Decoder settings -----
	if ($pconfig['smtp_preprocessor']=="on") {
		$section = new Form_Section('SMTP Decoder Settings', 'preproc_smtp', COLLAPSIBLE|SEC_OPEN);
	} else {
		$section = new Form_Section('SMTP Decoder Settings', 'preproc_smtp', COLLAPSIBLE|SEC_CLOSED);
	}
	$section->addInput(new Form_Checkbox(
		'smtp_preprocessor',
		'Enable SMTP Decoder',
		'Normalize/Decode SMTP protocol for enforcement and buffer overflows.  Default is Checked.',
		$pconfig['smtp_preprocessor'] == 'on' ? true:false,
		'on'
	));
	$group = new Form_Group('Memory Cap');
	$group->add(new Form_Input(
		'smtp_memcap',
		'',
		'number',
		$pconfig['smtp_memcap']
	))->setAttribute('min', '3276')->setAttribute('max', '104857600')->setHelp('Max memory in bytes used to log filename, addresses and headers.  Default is 838860.');
	$group->setHelp('The minimum value is 3276, and the maximum value is 104857600 (100 MB).  ' . 
			'When this memory cap is reached, SMTP will stop logging the filename, MAIL FROM address, RCPT TO addresses and email headers until memory becomes available.');
	$section->add($group);
	$section->addInput(new Form_Checkbox(
		'smtp_ignore_data',
		'Ignore Data',
		'Ignore data section of mail (except for mail headers) when processing rules.  Default is Not Checked.',
		$pconfig['smtp_ignore_data'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'smtp_ignore_tls_data',
		'Ignore TLS Data',
		'Ignore TLS-encrypted data when processing rules.  Default is Checked.',
		$pconfig['smtp_ignore_tls_data'] == 'on' ? true:false,
		'on'
	));
	$group = new Form_Group('Log Mail From');
	$group->add(new Form_Checkbox(
		'smtp_log_mail_from',
		'',
		'Log sender email address extracted from MAIL FROM command.  Default is Checked.',
		$pconfig['smtp_log_mail_from'] == 'on' ? true:false,
		'on'
	));
	$group->setHelp('<b>Note: </b>this is logged only when unified2 logging output is enabled.');
	$section->add($group);
	$group = new Form_Group('Log Receipt To');
	$group->add(new Form_Checkbox(
		'smtp_log_rcpt_to',
		'',
		'Log recipient email addresses extracted from RCPT TO command.  Default is Checked.',
		$pconfig['smtp_log_rcpt_to'] == 'on' ? true:false,
		'on'
	));
	$group->setHelp('<b>Note: </b>this is logged only when unified2 logging output is enabled.');
	$section->add($group);
	$group = new Form_Group('Log Filename');
	$group->add(new Form_Checkbox(
		'smtp_log_filename',
		'',
		'Log MIME attachment filenames extracted from Content-Disposition header.  Default is Checked.',
		$pconfig['smtp_log_filename'] == 'on' ? true:false,
		'on'
	));
	$group->setHelp('<b>Note: </b>this is logged only when unified2 logging output is enabled.');
	$section->add($group);
	$group = new Form_Group('Log E-Mail Headers');
	$group->add(new Form_Checkbox(
		'smtp_log_email_hdrs',
		'',
		'Log SMTP email headers extracted from SMTP data.  Default is Checked.',
		$pconfig['smtp_log_email_hdrs'] == 'on' ? true:false,
		'on'
	));
	$group->setHelp('<b>Note: </b>this is logged only when unified2 logging output is enabled.');
	$section->add($group);
	$group = new Form_Group('E-Mail Headers Log Depth');
	$group->add(new Form_Input(
		'smtp_email_hdrs_log_depth',
		'',
		'number',
		$pconfig['smtp_email_hdrs_log_depth']
	))->setAttribute('min', '0')->setAttribute('max', '20480')->setHelp('Memory in bytes to use for logging e-mail headers.  Default is 1464.');
	$group->setHelp('Allowable values range from 0 to 20480.  A value of 0 disables e-mail header logging.');
	$section->add($group);
	$section->addInput(new Form_Input(
		'smtp_max_mime_mem',
		'Maximum MIME Memory',
		'number',
		$pconfig['smtp_max_mime_mem']
	))->setAttribute('min', '3276')->setAttribute('max', '104857600')->setHelp('Maximum memory in bytes to use for decoding attachments.  Default is 838860.  Minimum is 3276 and the maximum is 104857600 (100 MB).');

	$group = new Form_Group('Base64 Decoding Depth');
	$group->add(new Form_Input(
		'smtp_b64_decode_depth',
		'',
		'number',
		$pconfig['smtp_b64_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Depth in bytes to decode base64 encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 turns off the base64 decoding of MIME attachments.  ' . 
			'A value of 0 sets the decoding of base64 encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the decoding of base64 MIME attachments, and applies per attachment.  ' . 
			'An SMTP preprocessor alert with sid 10 is generated (if enabled) when the decoding fails');
	$section->add($group);
	$group = new Form_Group('Quoted Printable Decoding Depth');
	$group->add(new Form_Input(
		'smtp_qp_decode_depth',
		'',
		'number',
		$pconfig['smtp_qp_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Byte depth to decode Quoted Printable (QP) encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 turns off the QP decoding of MIME attachments.  ' . 
			'A value of 0 sets the decoding of QP encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the decoding of QP MIME attachments, and applies per attachment.  ' . 
			'An SMTP preprocessor alert with sid 11 is generated (if enabled) when the decoding fails');
	$section->add($group);
	$group = new Form_Group('Non-Encoded MIME Extraction Depth');
	$group->add(new Form_Input(
		'smtp_bitenc_decode_depth',
		'',
		'number',
		$pconfig['smtp_bitenc_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Depth in bytes to extract non-encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 turns off the extraction of non-encoded of MIME attachments.  ' . 
			'A value of 0 sets the extraction of non-encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the extraction of non-encoded MIME attachments, and applies per attachment.');
	$section->add($group);

	$group = new Form_Group('Unix-to-Unix Decoding Depth');
	$group->add(new Form_Input(
		'smtp_uu_decode_depth',
		'',
		'number',
		$pconfig['smtp_uu_decode_depth']
	))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Depth in bytes to decode Unix-to-Unix (UU) encoded MIME attachments.  Default is 0 (unlimited).');
	$group->setHelp('Allowable values range from -1 to 65535.  A value of -1 the UU decoding of MIME attachments.  ' . 
			'A value of 0 sets the decoding of UU encoded MIME attachments to unlimited.  ' . 
			'A value other than 0 or -1 restricts the decoding of UU MIME attachments, and applies per attachment.  ' . 
			'An SMTP preprocessor alert with sid 13 is generated (if enabled) when the decoding fails');
	$section->add($group);
	print($section);
	//----- END SMTP Decoder settings -----

	//----- START SCADA preprocessors -----
	$section = new Form_Section('SCADA Preprocessors', 'preproc_scada', COLLAPSIBLE|SEC_OPEN);
	$group = new Form_Group('Enable Modbus Detection');
	$group->add(new Form_Checkbox(
		'modbus_preproc',
		'',
		'Modbus is a protocol used in SCADA networks.  The default port is TCP 502.  Default is Not Checked.',
		$pconfig['modbus_preproc'] == 'on' ? true:false,
		'on'
	));
	$group->setHelp('<b>Note: </b>if your network does not contain Modbus-enabled devices, you can leave this preprocessor disabled.');
	$section->add($group);
	$group = new Form_Group('Enable DNP3 Detection');
	$group->add(new Form_Checkbox(
		'dnp3_preproc',
		'',
		'DNP3 is a protocol used in SCADA networks.  The default port is TCP 20000.  Default is Not Checked.',
		$pconfig['dnp3_preproc'] == 'on' ? true:false,
		'on'
	));
	$group->setHelp('<b>Note: </b>if your network does not contain DNP3-enabled devices, you can leave this preprocessor disabled.');
	$section->add($group);
	print($section);
	//----- END SCADA preprocessors -----
?>

<div class="form-group">
	<label class="col-sm-2 control-label"></label>
	<div class="col-sm-10">
<?php
	//----- START SAVE and RESET form buttons -----
	$btnsave = new Form_Button(
		'save',
		'Save',
		null,
		'fa-solid fa-save'
	);
	$btnreset = new Form_Button(
		'ResetAll',
		'Reset',
		null,
		'fa-solid fa-arrow-rotate-right'
	);
	$btnsave->addClass('btn-primary')->addClass('btn-default')->setAttribute('title', 'Save preprocessor settings');
	$btnreset->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-warning')->setAttribute('title', 'Reset all preprocessors to their defaults');
	$btnreset->setAttribute('onclick', 'return confirm("WARNING:  This will reset all preprocessor settings to their defaults.  Click OK to continue or CANCEL to quit.")');
	print($btnsave . '&nbsp;&nbsp;' . $btnreset);
	//----- END SAVE and RESET form buttons -----
?>
	</div>
</div>

<?php
// Add view file modal pop-up
$modal = new Modal('View Auto-Disabled Rules', 'rulesviewer', 'large', 'Close');
$modal->addInput(new Form_Textarea (
	'rulesviewer_text',
	'',
	'...Loading...'
))->removeClass('form-control')->addClass('row-fluid col-sm-12')->setAttribute('rows', '20')->setAttribute('wrap', 'off');
$modal->addInput(new Form_StaticText(
	null,
	'<span class="text-info"><b>' . gettext('Note: ') . '</b>' . 
		gettext('all of the rules shown have been auto-disabled because they require one or more of the preprocessors that have been disabled.') . '</span>'
));
print($modal);


// Add ARP Spoofing MAC-IP address entry modal pop-up
$modal = new Modal('Add/Edit MAC-to-IP Address Pair', 'arp_spoof_addr_pair', true);
$modal->addInput(new Form_Input(
	'arp_spoof_mac_addr',
	'MAC Address',
	''
))->setHelp('Enter MAC address of host.');
$modal->addInput(new Form_IpAddress(
	'arp_spoof_ip_addr',
	'IP Address',
	'',
	'BOTH'
))->setHelp('Enter IP address of host.');
$btnsave = new Form_Button(
	'arp_spoof_save',
	'Save',
	null,
	'fa-solid fa-save'
);
$btncancel = new Form_Button(
	'arp_spoof_cancel',
	'Cancel'
);
$btnsave->addClass('btn-primary')->addClass('btn-default')->setAttribute('title', 'Save changes and return to Preprocessors tab');
$btncancel->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-warning');
$btncancel->setAttribute('data-dismiss', 'modal');
$btncancel->setAttribute('title', 'Cancel changes and return to Preprocessors tab');

$modal->addInput(new Form_StaticText(
	null,
	$btnsave . $btncancel
));



print($modal);
?>

</form>

<?php
print_callout('<p>' . gettext("Remember to save your changes before you exit this page.  Preprocessor changes will rebuild the rules file.  This ") . 
		gettext("may take several seconds to complete.  Snort must also be restarted on the interface to activate any changes made on this screen.") . '</p>', 
		'info', 'NOTE:');
?>

<script type="text/javascript">
//<![CDATA[

	function host_attribute_table_enable_change() {
		// Hide Host Attribute Table section if preprocessor is disabled
		if (!($('#host_attribute_table').prop('checked'))) {
			$('#preproc_hat_panel-body').collapse('toggle');
		}
	}

	function http_inspect_enable_change() {
		if (!($('#http_inspect').prop('checked'))) {
			var msg = "WARNING:  Disabling the http_inspect preprocessor is not recommended!\n\n";
			msg = msg + "Snort may fail to start because of other dependent preprocessors or ";
			msg = msg + "rule options.  Are you sure you want to disable it?\n\n";
			msg = msg + "Click OK to disable http_inspect, or CANCEL to quit.";
			if (!confirm(msg)) {
				$('#http_inspect').prop('checked', true);
			} 
		}

		// Collapse the section if HTTP_Inspect disabled
		if (!($('#http_inspect').prop('checked'))) {
			$('#preproc_http_panel-body').collapse('toggle');
		}
	}

	function frag3_enable_change() {
		if (!($('#frag3_detection').prop('checked'))) {
			var msg = "WARNING:  Disabling the Frag3 preprocessor is not recommended!\n\n";
			msg = msg + "Snort may fail to start because of other dependent preprocessors or ";
			msg = msg + "rule options.  Are you sure you want to disable it?\n\n";
			msg = msg + "Click OK to disable Frag3, or CANCEL to quit.";
			if (!confirm(msg)) {
				$('#frag3_detection').prop('checked', true);
			}
		}

		// Collapse the section if Frag3 disabled
		if (!($('#frag3_detection').prop('checked'))) {
			$('#preproc_frag3_panel-body').collapse('toggle');
		}
	}

	function stream5_enable_change() {
		if (!($('#stream5_reassembly').prop('checked'))) {
			var msg = "WARNING:  Stream5 is a critical preprocessor, and disabling it is not recommended!  ";
			msg = msg + "The following preprocessors require Stream5 and will be automatically disabled if currently enabled:\n\n";
			msg = msg + "    SMTP\t\tPOP\t\tSIP\n";
			msg = msg + "    SENSITIVE_DATA\tSF_PORTSCAN\tDCE/RPC 2\n";
			msg = msg + "    IMAP\t\tDNS\t\tSSL\n";
			msg = msg + "    GTP\t\tDNP3\t\tMODBUS\n";
			msg = msg + "    APP_ID\n\n";
			msg = msg + "Snort may fail to start because of other preprocessors or rule options dependent on Stream5.  ";
			msg = msg + "Are you sure you want to disable it?\n\n";
			msg = msg + "Click OK to disable Stream5, or CANCEL to quit.";
			if (!confirm(msg)) {
				$('#stream5_reassembly').prop('checked', true);
			}
		}

		// Collapse the section if Stream5 disabled
		if (!($('#stream5_reassembly').prop('checked'))) {
			$('#preproc_stream5_panel-body').collapse('toggle');
		}
	}

	function stream5_track_udp_enable_change() {
		// Warn if stream5_track_udp is being disabled
		if (!($('#stream5_track_udp').prop('checked'))) {
			var msg = "WARNING:  Stream5 UDP tracking is required by the Session Initiation Protocol (SIP) preprocessor!  ";
			msg = msg + "The SIP preprocessor will be automatically disabled if Stream5 UDP tracking is disabled.\n\n";
			msg = msg + "Snort may fail to start because of rule options dependent on the SIP preprocessor.  ";
			msg = msg + "Are you sure you want to disable Stream5 UDP tracking?\n\n";
			msg = msg + "Click OK to disable Stream5 UDP tracking, or CANCEL to quit.";
			if (confirm(msg)) {
				$('#sip_preproc').prop('checked', false);
				return;
			}
			else {
				$('#stream5_track_udp').prop('checked', true);
			}
		}
	}

	function sf_portscan_enable_change() {
		// Collapse the section if Portscan disabled
		if (!($('#sf_portscan').prop('checked'))) {
			$('#preproc_pscan_panel-body').collapse('toggle');
		}
	}

	function appid_preproc_enable_change() {
		// Collapse the section if AppID disabled
		if (!($('#appid_preproc').prop('checked'))) {
			$('#preproc_appid_panel-body').collapse('toggle');
		}
	}

	function ftp_telnet_enable_change() {
		// Hide FTP-Telnet sections if FTP preprocessor is disabled
		if (!($('#ftp_preprocessor').prop('checked'))) {
			$('#preproc_telnet_panel-body').collapse('hide');
			$('#preproc_ftp_panel-body').collapse('hide');
			$('#preproc_ftpglobal_panel-body').collapse('hide');
		}
		else {
			$('#preproc_ftpglobal_panel-body').collapse('show');
			$('#preproc_ftp_panel-body').collapse('show');
			if ($('#ftp_telnet_normalize').prop('checked')) {
				$('#preproc_telnet_panel-body').collapse('show');
			}
			else {
				$('#preproc_telnet_panel-body').collapse('hide');
			}
		}
	}

	function sensitive_data_enable_change() {
		// Hide Sensitive Date section if SDF preprocessor is disabled
		if (!($('#sensitive_data').prop('checked'))) {
			$('#preproc_sdf_panel-body').collapse('toggle');
		}
	}

	function arp_spoof_enable_change() {
		// Hide ARP Spoofing Detection section if preprocessor is disabled
		if (!($('#arpspoof_preproc').prop('checked'))) {
			$('#preproc_arp_panel-body').collapse('toggle');
		}
	}

	function pop_enable_change() {
		// Hide POP3 section if preprocessor is disabled
		if (!($('#pop_preproc').prop('checked'))) {
			$('#preproc_pop3_panel-body').collapse('toggle');
		}
	}

	function imap_enable_change() {
		// Hide IMAP section if preprocessor is disabled
		if (!($('#imap_preproc').prop('checked'))) {
			$('#preproc_imap_panel-body').collapse('toggle');
		}
	}

	function smtp_enable_change() {
		// Hide SMTP section if preprocessor is disabled
		if (!($('#smtp_preprocessor').prop('checked'))) {
			$('#preproc_smtp_panel-body').collapse('toggle');
		}
	}

	function ssh_preproc_enable_change() {
		// Hide SSH section if SSH preprocessor is disabled
		if (!($('#ssh_preproc').prop('checked'))) {
			$('#preproc_ssh_panel-body').collapse('toggle');
		}
	}

	function enable_change_all() {
		// -- Collapse HTTP Inspect section if disabled --
		if (!($('#http_inspect').prop('checked'))) {
			$('#preproc_http_panel-body').collapse('toggle');
		}

		// -- Collapse Frag3 section if disabled --
		if (!($('#frag3_detection').prop('checked'))) {
			$('#preproc_frag3_panel-body').collapse('toggle');
		}

		// -- Collapse Stream5 section if disabled --
		if (!($('#stream5_reassembly').prop('checked'))) {
			$('#preproc_stream5_panel-body').collapse('toggle');
		}

		ftp_telnet_enable_change();
		arp_spoof_enable_change();
	}

	function selectAlias(targetVar) {

		var loc;
		var fields = [ "#sf_portscan", "#pscan_protocol", "#pscan_type", "#pscan_sense_level", "#pscan_memcap", "#pscan_ignore_scanners", "#pscan_ignore_scanned" ];

		// Scrape current form field values and add to
		// the select alias URL as a query string.
		var loc = 'snort_select_alias.php?id=<?=$id;?>&act=import&type=host|network';
		loc = loc + '&varname=' + targetVar + '&multi_ip=yes';
		loc = loc + '&returl=<?=urlencode($_SERVER['PHP_SELF']);?>';

		// Iterate over just the specific form fields we want to pass to
		// the select alias URL.
		fields.forEach(function(entry) {
			var tmp = $(entry).serialize();
			if (tmp.length > 0)
				loc = loc + '&' + tmp;
		});
	
		window.parent.location = loc; 
	}

	function del_eng(eng, engid) {
		$('#eng_id').val(engid);
		if (confirm('Are you sure you want to delete this entry?')) {
			$('#iform').append('<input type="hidden" id="' + eng + '" name="' + eng + '" value="1">').submit();
		}
	}

	function getFileContents() {
		var ajaxRequest;

		ajaxRequest = $.ajax({
			url: "/snort/snort_preprocessors.php",
			type: "post",
			data: { ajax: "ajax", 
				id: $('#id').val()
			}
		});

		// Display the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {

			// Write the list contents to the text control
			$('#rulesviewer_text').text(response);
			$('#rulesviewer_text').attr('readonly', true);
		});
	}



events.push(function(){

	// ---------- Autocomplete --------------------------------------------

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn"))) ?>;
	var portsarray = <?= json_encode(get_alias_list(array("port"))) ?>;

	$('#pscan_ignore_scanners').autocomplete({
		source: addressarray
	});

	$('#pscan_ignore_scanned').autocomplete({
		source: addressarray
	});

	$('#ssh_preproc_ports').autocomplete({
		source: portsarray
	});

	// ---------- Click handlers ------------------------------------------

	$('#host_attribute_table').click(function() {
		host_attribute_table_enable_change();
	});

	$('#http_inspect').click(function() {
		http_inspect_enable_change();
	});

	$('#frag3_detection').click(function() {
		frag3_enable_change();
	});

	$('#stream5_reassembly').click(function() {
		stream5_enable_change();
	});

	$('#appid_preproc').click(function() {
		appid_preproc_enable_change();
	});

	$('#sf_portscan').click(function() {
		sf_portscan_enable_change();
	});

	$('#stream5_track_udp').click(function() {
		stream5_track_udp_enable_change();
	});

	$('#ftp_preprocessor').click(function() {
		ftp_telnet_enable_change();
	});

	$('#ftp_telnet_normalize').click(function() {
		ftp_telnet_enable_change();
	});

	$('#sensitive_data').click(function() {
		sensitive_data_enable_change();
	});

	$('#arpspoof_preproc').click(function() {
		arp_spoof_enable_change();
	});

	$('#pop_preproc').click(function() {
		pop_enable_change();
	});

	$('#imap_preproc').click(function() {
		imap_enable_change();
	});

	$('#smtp_preprocessor').click(function() {
		smtp_enable_change();
	});

	$('#ssh_preproc').click(function() {
		ssh_preproc_enable_change();
	});

	$('#rulesviewer').on('shown.bs.modal', function() {
		getFileContents();
	});

	// Open ARP Spoofing address pairs Modal and init its data fields
	$('#arp_spoof_addr_pair').on('show.bs.modal', function(e) {
		$('#eng_id').val($(e.relatedTarget).data('eng_id'));
		$('#arp_spoof_ip_addr').val($(e.relatedTarget).data('arp_spoof_ip'));
		$('#arp_spoof_mac_addr').val($(e.relatedTarget).data('arp_spoof_mac'));
	});

	// Set initial state of form controls
	enable_change_all();

});
//]]>
</script>
<?php include("foot.inc"); ?>

