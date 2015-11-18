<?php
/*
 * snort_preprocessors.php
 * part of pfSense
 *
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2008-2009 Robert Zelaya.
 * Copyright (C) 2011-2012 Ermal Luci
 * Copyright (C) 2013, 2014 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
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

if (!is_array($config['installedpackages']['snortglobal']))
	$config['installedpackages']['snortglobal'] = array();
if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();

// Initialize multiple config engine arrays for supported preprocessors if necessary
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['frag3_engine']['item']))
	$config['installedpackages']['snortglobal']['rule'][$id]['frag3_engine']['item'] = array();
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['stream5_tcp_engine']['item']))
	$config['installedpackages']['snortglobal']['rule'][$id]['stream5_tcp_engine']['item'] = array();
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['http_inspect_engine']['item']))
	$config['installedpackages']['snortglobal']['rule'][$id]['http_inspect_engine']['item'] = array();
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['ftp_server_engine']['item']))
	$config['installedpackages']['snortglobal']['rule'][$id]['ftp_server_engine']['item'] = array();
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['ftp_client_engine']['item']))
	$config['installedpackages']['snortglobal']['rule'][$id]['ftp_client_engine']['item'] = array();

$a_nat = &$config['installedpackages']['snortglobal']['rule'];

$vrt_enabled = $config['installedpackages']['snortglobal']['snortdownload'];

// Calculate the "next engine ID" to use for the multi-config engine arrays
$frag3_engine_next_id = count($a_nat[$id]['frag3_engine']['item']);
$stream5_tcp_engine_next_id = count($a_nat[$id]['stream5_tcp_engine']['item']);
$http_inspect_engine_next_id = count($a_nat[$id]['http_inspect_engine']['item']);
$ftp_server_engine_next_id = count($a_nat[$id]['ftp_server_engine']['item']);
$ftp_client_engine_next_id = count($a_nat[$id]['ftp_client_engine']['item']);

$pconfig = array();
if (isset($id) && isset($a_nat[$id])) {
	$pconfig = $a_nat[$id];

	// Initialize multiple config engine arrays for supported preprocessors if necessary
	if (!is_array($pconfig['frag3_engine']['item']))
		$pconfig['frag3_engine']['item'] = array();
	if (!is_array($pconfig['stream5_tcp_engine']['item']))
		$pconfig['stream5_tcp_engine']['item'] = array();
	if (!is_array($pconfig['http_inspect_engine']['item']))
		$pconfig['http_inspect_engine']['item'] = array();
	if (!is_array($pconfig['ftp_server_engine']['item']))
		$pconfig['ftp_server_engine']['item'] = array();
	if (!is_array($pconfig['ftp_client_engine']['item']))
		$pconfig['ftp_client_engine']['item'] = array();

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

/* Define the "disabled_preproc_rules.log" file for this interface */
$disabled_rules_log = "{$if_friendly}_disabled_preproc_rules.log";

// Check for returned "selected alias" if action is import
if ($_GET['act'] == "import" && isset($_GET['varname']) && !empty($_GET['varvalue'])) {

	// Retrieve previously typed values we passed to SELECT ALIAS page
	$pconfig['sf_portscan'] = htmlspecialchars($_GET['sf_portscan'])? 'on' : 'off';
	$pconfig['pscan_ignore_scanners'] = htmlspecialchars($_GET['pscan_ignore_scanners']);
	$pconfig['pscan_protocol'] = htmlspecialchars($_GET['pscan_protocol']);
	$pconfig['pscan_type'] = htmlspecialchars($_GET['pscan_type']);
	$pconfig['pscan_memcap'] = htmlspecialchars($_GET['pscan_memcap']);
	$pconfig['pscan_sense_level'] = htmlspecialchars($_GET['pscan_sense_level']);

	// Now retrieve the "selected alias" returned from SELECT ALIAS page
	$pconfig[$_GET['varname']] = htmlspecialchars($_GET['varvalue']);

	// We have made a preproc config change, so set "dirty" flag
	mark_subsystem_dirty('snort_preprocessors');
}

// Handle deleting of any of the multiple configuration engines
if ($_POST['del_http_inspect']) {
	if (isset($_POST['eng_id']) && isset($id) && isset($a_nat[$id])) {
		unset($a_nat[$id]['http_inspect_engine']['item'][$_POST['eng_id']]);
		write_config("Snort pkg: deleted http_inspect engine for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#httpinspect_row");
		exit;
	}
}
elseif ($_POST['del_frag3']) {
	if (isset($_POST['eng_id']) && isset($id) && isset($a_nat[$id])) {
		unset($a_nat[$id]['frag3_engine']['item'][$_POST['eng_id']]);
		write_config("Snort pkg: deleted frag3 engine for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#frag3_row");
		exit;
	}
}
elseif ($_POST['del_stream5_tcp']) {
	if (isset($_POST['eng_id']) && isset($id) && isset($a_nat[$id])) {
		unset($a_nat[$id]['stream5_tcp_engine']['item'][$_POST['eng_id']]);
		write_config("Snort pkg: deleted stream5 engine for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#stream5_row");
		exit;
	}
}
elseif ($_POST['del_ftp_client']) {
	if (isset($_POST['eng_id']) && isset($id) && isset($a_nat[$id])) {
		unset($a_nat[$id]['ftp_client_engine']['item'][$_POST['eng_id']]);
		write_config("Snort pkg: deleted ftp_client engine for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#ftp_telnet_row");
		exit;
	}
}
elseif ($_POST['del_ftp_server']) {
	if (isset($_POST['eng_id']) && isset($id) && isset($a_nat[$id])) {
		unset($a_nat[$id]['ftp_server_engine']['item'][$_POST['eng_id']]);
		write_config("Snort pkg: deleted ftp_server engine for {$a_nat[$id]['interface']}.");
		header("Location: snort_preprocessors.php?id=$id#ftp_telnet_row");
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
	$pconfig['preproc_auto_rule_disable'] = "off";
	$pconfig['protect_preproc_rules'] = "off";
	$pconfig['frag3_detection'] = "on";
	$pconfig['frag3_max_frags'] = "8192";
	$pconfig['frag3_memcap'] = "4194304";

	/* Log a message at the top of the page to inform the user */
	$savemsg = gettext("All preprocessor settings have been reset to their defaults.");
}

if ($_POST['save'] || $_POST['apply']) {
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

	// Validate Portscan Ignore_Scanners parameter
	if ($_POST['sf_portscan'] == 'on' && is_alias($_POST['pscan_ignore_scanners'])) {
		if (trim(filter_expand_alias($_POST["def_{$key}"])) == "")
			$input_errors[] = gettext("FQDN aliases are not supported in Snort for the PORTSCAN IGNORE_SCANNERS parameter.");
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

		// Set SDF inspection types
		$natent['sdf_alert_data_type'] = implode(",",$_POST['sdf_alert_data_type']);

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

		if (isset($id) && isset($a_nat[$id])) {
			$a_nat[$id] = $natent;
			write_config("Snort pkg: saved modified preprocessor settings for {$a_nat[$id]['interface']}.");
		}

		/*************************************************/
		/* Update the snort.conf file and rebuild the    */
		/* rules for this interface.                     */
		/*************************************************/
		$rebuild_rules = true;
		conf_mount_rw();
		snort_generate_conf($natent);
		conf_mount_ro();
		$rebuild_rules = false;

		/* If 'preproc_auto_rule_disable' is off, then clear log file */
		if ($natent['preproc_auto_rule_disable'] == 'off')
			unlink_if_exists("{$snortlogdir}/{$disabled_rules_log}");

		/*******************************************************/
		/* Signal Snort to reload Host Attribute Table if one  */
		/* is configured and saved.                            */
		/*******************************************************/
		if ($natent['host_attribute_table'] == "on" && 
		    !empty($natent['host_attribute_data']))
			snort_reload_config($natent, "SIGURG");

		/* Sync to configured CARP slaves if any are enabled */
		snort_sync_on_changes();

		// We have saved changes, so clear "dirty" flag
		clear_subsystem_dirty('snort_preprocessors');

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

			// We have made a preproc config change, so set "dirty" flag
			mark_subsystem_dirty('snort_preprocessors');

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
		write_config("Snort pkg: modified Host Attribute Table data for {$a_nat[$id]['interface']}.");
		header("Location: snort_edit_hat_data.php?id=$id");
		exit;
	}
}

/* If Host Attribute Table option is enabled, but */
/* no Host Attribute data exists, flag an error.  */
if ($pconfig['host_attribute_table'] == 'on' && empty($pconfig['host_attribute_data']))
	$input_errors[] = gettext("The Host Attribute Table option is enabled, but no Host Attribute data has been loaded.  Data may be entered manually or imported from a suitable file.");

$pgtitle = gettext("Snort: Interface {$if_friendly} - Preprocessors and Flow");
include_once("head.inc");
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="enable_change_all()">

<?php include("fbegin.inc");

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg);
}
?>

<script type="text/javascript" src="/javascript/autosuggest.js">
</script>
<script type="text/javascript" src="/javascript/suggestions.js">
</script>

<form action="snort_preprocessors.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<input name="id" type="hidden" value="<?=$id;?>"/>
<input name="eng_id" id="eng_id" type="hidden" value=""/>

<?php if (is_subsystem_dirty('snort_preprocessors')): ?><p>
<?php print_info_box_np(gettext("A change has been made to the preprocessors configuration.") . "<br/>" . gettext("Click SAVE when finished to apply the change to the Snort configuration."));?>
<?php endif; ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
		$tab_array = array();
		$tab_array[0] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
		$tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
		$tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
		$tab_array[3] = array(gettext("Alerts"), false, "/snort/snort_alerts.php?instance={$id}");
		$tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
		$tab_array[5] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
		$tab_array[6] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
		$tab_array[7] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
		$tab_array[8] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
		$tab_array[9] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
		$tab_array[10] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
		display_top_tabs($tab_array, true);
		echo '</td></tr>';
		echo '<tr><td>';
		$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
		$tab_array = array();
		$tab_array[] = array($menu_iface . gettext("Settings"), false, "/snort/snort_interfaces_edit.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Categories"), false, "/snort/snort_rulesets.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Rules"), false, "/snort/snort_rules.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Variables"), false, "/snort/snort_define_servers.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Preprocs"), true, "/snort/snort_preprocessors.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/snort/snort_barnyard.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/snort/snort_ip_reputation.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
		display_top_tabs($tab_array, true);
?>
</td></tr>
<tr><td><div id="mainarea">
<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" align="left" valign="middle">
		<?php echo gettext("Rules may be dependent on preprocessors!  Disabling preprocessors may result in "); ?>
		<?php echo gettext("Snort start failures unless dependent rules are also disabled."); ?>
		<?php echo gettext("The Auto-Rule Disable feature can be used, but note the warning about compromising protection.  " . 
		"Defaults will be used where no user input is provided."); ?></td>
	</tr>
	<tr>

		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Preprocessors Configuration"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable Performance Stats"); ?></td>
		<td width="78%" class="vtable"><input name="perform_stat" type="checkbox" value="on" 
			<?php if ($pconfig['perform_stat']=="on") echo "checked"; ?>/>
			<?php echo gettext("Collect Performance Statistics for this interface."); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Protect Customized Preprocessor Rules"); ?></td>
		<td width="78%" class="vtable"><input name="protect_preproc_rules" type="checkbox" value="on" 
			<?php if ($pconfig['protect_preproc_rules']=="on") echo "checked "; 
			if ($vrt_enabled <> 'on') echo "disabled"; ?>/>
			<?php echo gettext("Check this box if you maintain customized preprocessor text rules files for this interface."); ?>
			<table width="100%" border="0" cellpadding="2" cellpadding="2">
				<tr>
					<td width="3%">&nbsp;</td>
					<td><?php echo gettext("Enable this only if you use customized preprocessor text rules files and " . 
					"you do not want them overwritten by automatic Snort VRT rule updates.  " . 
					"This option is disabled when Snort VRT rules download is not enabled on the Global Settings tab."); ?><br/><br/>
					<?php echo "<span class=\"red\"><strong>" . gettext("Hint: ") . "</strong></span>" . 
					gettext("Most users should leave this unchecked."); ?></td> 
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Auto Rule Disable"); ?></td>
		<td width="78%" class="vtable"><input name="preproc_auto_rule_disable" type="checkbox" value="on" 
			<?php if ($pconfig['preproc_auto_rule_disable']=="on") echo "checked"; ?>/>
			<?php echo gettext("Auto-disable text rules dependent on disabled preprocessors for this interface.  "); 
			echo gettext("Default is ") . '<strong>' . gettext("Not Checked"); ?></strong>.<br/>
			<table width="100%" border="0" cellpadding="2" cellpadding="2">
				<tr>
					<td width="3%">&nbsp;</td>
					<td><span class="red"><strong><?php echo gettext("Warning:  "); ?></strong></span>
					<?php echo gettext("Enabling this option allows Snort to automatically disable any text rules " . 
					"containing rule options or content modifiers that are dependent upon the preprocessors " . 
					"you have not enabled.  This may facilitate starting Snort without errors related to " . 
					"disabled preprocessors, but can substantially compromise the level of protection by " .
					"automatically disabling detection rules."); ?></td>
				</tr>
			<?php if (file_exists("{$snortlogdir}/{$disabled_rules_log}") && filesize("{$snortlogdir}/{$disabled_rules_log}") > 0): ?>
				<tr>
					<td width="3%">&nbsp;</td>
					<td class="vexpl"><input type="button" class="formbtn" value="View" onclick="wopen('snort_rules_edit.php?id=<?=$id;?>&openruleset=<?=$disabled_rules_log;?>','FileViewer',800,600);">
					&nbsp;&nbsp;&nbsp;<?php echo gettext("Click to view the list of currently auto-disabled rules"); ?></td>
				</tr>
			<?php endif; ?>
			</table>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Host Attribute Table"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable"); ?></td>
		<td width="78%" class="vtable"><input name="host_attribute_table"
			type="checkbox" value="on" id="host_attribute_table" onclick="host_attribute_table_enable_change();" 
			<?php if ($pconfig['host_attribute_table']=="on") echo "checked"; ?>/>
			<?php echo gettext("Use a Host Attribute Table file to auto-configure applicable preprocessors.  " .
				"Default is "); ?><strong><?php echo gettext("Not Checked"); ?></strong>.</td>
	</tr>
	<tr id="host_attrib_table_data_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Host Attribute Data"); ?></td>
		<td width="78%" class="vtable"><strong><?php echo gettext("Import From File"); ?></strong><br/>
			<input name="host_attribute_file" type="file" class="formfld file" value="on" id="host_attribute_file" size="40"/>&nbsp;&nbsp;
			<input type="submit" name="btn_import" id="btn_import" value="Import" class="formbtn"/><br/>
			<?php echo gettext("Choose the Host Attributes file to use for auto-configuration."); ?><br/><br/>
			<span class="red"><strong><?php echo gettext("Warning: "); ?></strong></span>
			<?php echo gettext("The Host Attributes file has a required format.  See the "); ?><a href="http://manual.snort.org/" target="_blank">
			<?php echo gettext("Snort Manual"); ?></a><?php echo gettext(" for details.  " . 
			"An improperly formatted file may cause Snort to crash or fail to start.  The combination of "); ?>
			<a href="http://nmap.org/" target="_blank"><?php echo gettext("NMap"); ?></a><?php echo gettext(" and "); ?>
			<a href="http://code.google.com/p/hogger/" target="_blank"><?php echo gettext("Hogger"); ?></a><?php echo gettext(" or "); ?>
			<a href="http://gamelinux.github.io/prads/" target="_blank"><?php echo gettext("PRADS"); ?></a><?php echo gettext(" can be used to " .
			"scan networks and automatically generate a suitable Host Attribute Table file for import."); ?><br/><br/>
			<input type="submit" id="btn_edit_hat" name="btn_edit_hat" value="<?php if (!empty($pconfig['host_attribute_data'])) {echo gettext(" Edit ");}
			else {echo gettext("Create");} ?>" class="formbtn">&nbsp;&nbsp;
			<?php if (!empty($pconfig['host_attribute_data'])) {echo gettext("Click to View or Edit the Host Attribute data.");}
			 else {echo gettext("Click to Create Host Attribute data manually.");}
			if ($pconfig['host_attribute_table']=="on" && empty($pconfig['host_attribute_data'])){
				echo "<br/><br/><span class=\"red\"><strong>" . gettext("Warning: ") . "</strong></span>" . 
				gettext("No Host Attribute Data loaded - import from a file or enter it manually.");
			} ?></td>
	</tr>
	<tr id="host_attrib_table_maxhosts_row">
		<td valign="top" class="vncell"><?php echo gettext("Maximum Hosts"); ?></td>
		<td class="vtable">
		<table cellpadding="0" cellspacing="0">
			<tr>
				<td><input name="max_attribute_hosts" type="text" class="formfld unknown" id="max_attribute_hosts" size="9" 
				value="<?=htmlspecialchars($pconfig['max_attribute_hosts']);?>"/>&nbsp;&nbsp;
				<?php echo gettext("Max number of hosts to read from the Attribute Table.  Min is ") . 
				"<strong>" . gettext("32") . "</strong>" . gettext(" and Max is ") . "<strong>" . 
				gettext("524288") . "</strong>"; ?>.</td>
			</tr>
		</table>
		<?php echo gettext("Sets a limit on the maximum number of hosts to read from the Attribute Table. If the number of hosts in " .
		"the table exceeds this value, an error is logged and the remainder of the hosts are ignored.  " . 
		"Default is ") . "<strong>" . gettext("10000") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr id="host_attrib_table_maxsvcs_row">
		<td valign="top" class="vncell"><?php echo gettext("Maximum Services Per Host"); ?></td>
		<td class="vtable">
		<table cellpadding="0" cellspacing="0">
			<tr>
				<td><input name="max_attribute_services_per_host" type="text" class="formfld unknown" id="max_attribute_services_per_host" size="9" 
				value="<?=htmlspecialchars($pconfig['max_attribute_services_per_host']);?>"/>&nbsp;&nbsp;
				<?php echo gettext("Max number of  per host services to read from the Attribute Table.  Min is ") . 
				"<strong>" . gettext("1") . "</strong>" . gettext(" and Max is ") . "<strong>" . 
				gettext("65535") . "</strong>"; ?>.</td>
			</tr>
		</table>
		<?php echo gettext("Sets the per host limit of services to read from the Attribute Table. For a given host, if the number of " .
		"services read exceeds this value, an error is logged and the remainder of the services for that host are ignored. " . 
		"Default is ") . "<strong>" . gettext("10") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Protocol Aware Flushing"); ?></td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Protocol Aware Flushing Maximum PDU"); ?></td>
		<td class="vtable">
			<input name="max_paf" type="text" class="formfld unknown" id="max_paf" size="9"
			value="<?=htmlspecialchars($pconfig['max_paf']);?>">&nbsp;
			<?php echo gettext("Max number of PDUs to be reassembled into a single PDU.  Min is ") . 
			"<strong>" . gettext("0") . "</strong>" . gettext(" (off) and Max is ") . "<strong>" . 
			gettext("63780") . "</strong>"; ?>.<br/><br/>
			<?php echo gettext("Multiple PDUs within a single TCP segment, as well as one PDU spanning multiple TCP segments, will be " .
			"reassembled into one PDU per packet for each PDU.  PDUs larger than the configured maximum will be split into multiple packets. " . 
			"Default is ") . "<strong>" . gettext("16000") . "</strong>.  " . gettext("A value of 0 disables Protocol Aware Flushing."); ?>.<br/>
		</td>
	</tr>
	<tr id="httpinspect_row">
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("HTTP Inspect"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable"); ?></td>
		<td width="78%" class="vtable"><input name="http_inspect" 
			type="checkbox" value="on" id="http_inspect" onclick="http_inspect_enable_change();" 
			<?php if ($pconfig['http_inspect']=="on" || empty($pconfig['http_inspect'])) echo "checked";?>/>
			<?php echo gettext("Use HTTP Inspect to Normalize/Decode and detect HTTP traffic and protocol anomalies.  Default is ");?>
			<strong><?php echo gettext("Checked"); ?></strong>.</td>
	</tr>
	<tr id="httpinspect_proxyalert_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Proxy Alert"); ?></td>
		<td width="78%" class="vtable"><input name="http_inspect_proxy_alert" 
			type="checkbox" value="on" id="http_inspect_proxy_alert"  
			<?php if ($pconfig['http_inspect_proxy_alert']=="on") echo "checked";?>/>
			<?php echo gettext("Enable global alerting on HTTP server proxy usage.  Default is ");?>
			<strong><?php echo gettext("Not Checked"); ?></strong>.<br/><br/><span class="red"><strong>
			<?php echo gettext("Note: ") . "</strong></span>" . gettext("By adding Server Configurations below and enabling " . 
			"the 'allow_proxy_use' parameter within them, alerts will be generated for web users that aren't using the configured " .  
			"proxies or are using a rogue proxy server.") . "<br/><br/><span class=\"red\"><strong>" . gettext("Warning: ") . 
			"</strong></span>" . gettext("If users are not required to configure web proxy use, you may get a lot " . 
			"of proxy alerts.  Only use this feature with traditional proxy environments.  Blind firewall proxies don't count!");?>
			</td>
	</tr>
	<tr id="httpinspect_memcap_row">
		<td valign="top" class="vncell"><?php echo gettext("Memory Cap"); ?></td>
		<td class="vtable">
			<input name="http_inspect_memcap" type="text" class="formfld unknown"
			id="http_inspect_memcap" size="9"
			value="<?=htmlspecialchars($pconfig['http_inspect_memcap']);?>">&nbsp;
			<?php echo gettext("Maximum memory in bytes to use for URI and Hostname logging.  The Minimum value is ") . 
			"<strong>" . gettext("2304") . "</strong>" . gettext(" and the Maximum is ") . "<strong>" . 
			gettext("603979776") . "</strong>" . gettext(" (576 MB)"); ?>.<br/><br/>
			<?php echo gettext("Sets the maximum amount of memory the preprocessor will use for logging the URI and Hostname data. The default " .
			"value is ") . "<strong>" . gettext("150,994,944") . "</strong>" . gettext(" (144 MB)."); ?>
			<?php echo gettext("  This option determines the maximum HTTP sessions that will log URI and Hostname data at any given instant. ") . 
			gettext("  Max Logged Sessions = MEMCAP / 2304"); ?>.
		</td>
	</tr>
	<tr id="httpinspect_maxgzipmem_row">
		<td valign="top" class="vncell"><?php echo gettext("Maximum gzip Memory"); ?></td>
		<td class="vtable">
			<input name="http_inspect_max_gzip_mem" type="text" class="formfld unknown" 
			id="http_inspect_memcap" size="9" 
			value="<?=htmlspecialchars($pconfig['http_inspect_max_gzip_mem']);?>">&nbsp;
			<?php echo gettext("Maximum memory in bytes to use for decompression.  The Minimum value is ") . 
			"<strong>" . gettext("3276") . "</strong>";?>.<br/><br/>
			<?php echo gettext("The default value is ") . "<strong>" . gettext("838860") . "</strong>" . gettext(" bytes.");?>
			<?php echo gettext("  This option determines the number of concurrent sessions that can be decompressed at any given instant.");?>
		</td>
	</tr>
	<tr id="httpinspect_engconf_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Server Configuration"); ?></td>
		<td class="vtable">
			<table width="95%" align="left" id="httpinspectEnginesTable" style="table-layout: fixed;" border="0" cellspacing="0" cellpadding="0">
				<colgroup>
					<col width="45%" align="left">
					<col width="45%" align="center">
					<col width="10%" align="right">
				</colgroup>
			   <thead>
				<tr>
					<th class="listhdrr" axis="string"><?php echo gettext("Server Name");?></th>
					<th class="listhdrr" axis="string"><?php echo gettext("Bind-To Address Alias");?></th>
					<th class="list" align="right"><a href="snort_import_aliases.php?id=<?=$id?>&eng=http_inspect_engine">
					<img src="../themes/<?= $g['theme'];?>/images/icons/icon_import_alias.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Import server configuration from existing Aliases");?>"></a>
					<a href="snort_httpinspect_engine.php?id=<?=$id?>&eng_id=<?=$http_inspect_engine_next_id?>">
					<img src="../themes/<?= $g['theme'];?>/images/icons/icon_plus.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Add a new server configuration");?>"></a></th>
				</tr>
			   </thead>
			<?php foreach ($pconfig['http_inspect_engine']['item'] as $f => $v): ?>
				<tr>
					<td class="listlr" align="left"><?=gettext($v['name']);?></td>
					<td class="listbg" align="center"><?=gettext($v['bind_to']);?></td>
					<td class="listt" align="right"><a href="snort_httpinspect_engine.php?id=<?=$id;?>&eng_id=<?=$f;?>">
					<img src="/themes/<?=$g['theme'];?>/images/icons/icon_e.gif"  
					width="17" height="17" border="0" title="<?=gettext("Edit this server configuration");?>"></a>
			<?php if ($v['bind_to'] <> "all") : ?>
					<input type="image" name="del_http_inspect[]" onclick="document.getElementById('eng_id').value='<?=$f;?>'; return confirm('Are you sure you want to delete this entry?');" 
					src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" width="17" height="17" border="0" 
					title="<?=gettext("Delete this server configuration");?>"/>
			<?php else : ?>
					<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x_d.gif" width="17" height="17" border="0" 
					title="<?=gettext("Default server configuration cannot be deleted");?>">
			<?php endif ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<tr id="frag3_row">
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Frag3 Target-Based IP Defragmentation"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable");?></td>
		<td width="78%" class="vtable"><input name="frag3_detection" type="checkbox" value="on" onclick="frag3_enable_change();" 
		<?php if ($pconfig['frag3_detection']=="on") echo "checked";?>/>
		<?php echo gettext("Use Frag3 Engine to detect IDS evasion attempts via target-based IP packet fragmentation.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>.";?></td>
	</tr>
	<tr id="frag3_memcap_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Memory Cap");?></td>
		<td width="78%" class="vtable"><input name="frag3_memcap" type="text" class="formfld unknown" id="frag3_memcap" size="9" value="<?=htmlspecialchars($pconfig['frag3_memcap']);?>">
		<?php echo gettext("Memory cap (in bytes) for self preservation.");?><br/>
		<?php echo gettext("The maximum amount of memory allocated for Frag3 fragment reassembly.  Default value is ") . 
		"<strong>" . gettext("4MB") . "</strong>."; ?>
		</td>
	</tr>
	<tr id="frag3_maxfrags_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Maximum Fragments"); ?></td>
		<td width="78%" class="vtable"><input name="frag3_max_frags" type="text" class="formfld unknown" id="frag3_max_frags" size="9" value="<?=htmlspecialchars($pconfig['frag3_max_frags']);?>">
		<?php echo gettext("Maximum simultaneous fragments to track.");?>.<br/>
		<?php echo gettext("The maximum number of simultaneous fragments to track.  Default value is ") .
		"<strong>8192</strong>.";?>
		</td>
	</tr>
	<tr id="frag3_engconf_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Engine Configuration"); ?></td>
		<td class="vtable">
			<table width="95%" align="left" id="frag3EnginesTable" style="table-layout: fixed;" border="0" cellspacing="0" cellpadding="0">
				<colgroup>
					<col width="45%" align="left">
					<col width="45%" align="center">
					<col width="10%" align="right">
				</colgroup>
			   <thead>
				<tr>
					<th class="listhdrr" axis="string"><?php echo gettext("Engine Name");?></th>
					<th class="listhdrr" axis="string"><?php echo gettext("Bind-To Address Alias");?></th>
					<th class="list" align="right"><a href="snort_import_aliases.php?id=<?=$id?>&eng=frag3_engine">
					<img src="../themes/<?= $g['theme'];?>/images/icons/icon_import_alias.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Import engine configuration from existing Aliases");?>"></a>
					<a href="snort_frag3_engine.php?id=<?=$id?>&eng_id=<?=$frag3_engine_next_id?>">
					<img src="../themes/<?= $g['theme'];?>/images/icons/icon_plus.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Add a new engine configuration");?>"></a></th>
				</tr>
			   </thead>
			<?php foreach ($pconfig['frag3_engine']['item'] as $f => $v): ?>
				<tr>
					<td class="listlr" align="left"><?=gettext($v['name']);?></td>
					<td class="listbg" align="center"><?=gettext($v['bind_to']);?></td>
					<td class="listt" align="right"><a href="snort_frag3_engine.php?id=<?=$id;?>&eng_id=<?=$f;?>">
					<img src="/themes/<?=$g['theme'];?>/images/icons/icon_e.gif"  
					width="17" height="17" border="0" title="<?=gettext("Edit this engine configuration");?>"></a>
			<?php if ($v['bind_to'] <> "all") : ?> 
					<input type="image" name="del_frag3[]" onclick="document.getElementById('eng_id').value='<?=$f;?>'; return confirm('Are you sure you want to delete this entry?');" 
					src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" width="17" height="17" border="0" 
					title="<?=gettext("Delete this engine configuration");?>"/>
			<?php else : ?>
					<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x_d.gif" width="17" height="17" border="0" 
					title="<?=gettext("Default engine configuration cannot be deleted");?>">
			<?php endif ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<tr id="stream5_row">
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Stream5 Target-Based Stream Reassembly"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable"); ?></td>
		<td width="78%" class="vtable"><input name="stream5_reassembly" type="checkbox" value="on" onclick="stream5_enable_change();"  
			<?php if ($pconfig['stream5_reassembly']=="on") echo "checked"; ?>/>
		<?php echo gettext("Use Stream5 session reassembly for TCP, UDP and/or ICMP traffic.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr id="stream5_flushonalert_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Flush On Alert"); ?></td>
		<td width="78%" class="vtable"><input name="stream5_flush_on_alert" type="checkbox" value="on"   
			<?php if ($pconfig['stream5_flush_on_alert']=="on") echo "checked"; ?>/>
		<?php echo gettext("Flush a TCP stream when an alert is generated on that stream.  Default is ") . 
		"<strong>" . gettext("Not Checked") . "</strong><br/><span class=\"red\"><strong>" . 
		gettext("Note:  ") . "</strong></span>" . gettext("This parameter is for backwards compatibility.");?></td>
	</tr>
	<tr id="stream5_prunelogmax_row">
		<td valign="top" class="vncell"><?php echo gettext("Prune Log Max"); ?></td>
		<td class="vtable">
		<input name="stream5_prune_log_max" type="text" class="formfld unknown" id="stream5_prune_log_max" size="9" 
		value="<?=htmlspecialchars($pconfig['stream5_prune_log_max']);?>">
		<?php echo gettext("Prune Log Max Bytes.  Minimum can be either ") . "<strong>0</strong>" . gettext(" (disabled), or if not disabled, ") . 
		"<strong>1024</strong>" . gettext(".  Maximum is ") . "<strong>" . gettext("1073741824") . "</strong>";?>.
		<?php echo gettext("Logs a message when a session terminates that was using more than the specified number of bytes.  Default value is ") .
		"<strong>1048576</strong>" . gettext(" bytes."); ?><br/>
		</td>
	</tr>
	<tr id="stream5_proto_tracking_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Protocol Tracking"); ?></td>
		<td width="78%" class="vtable">
			<input name="stream5_track_tcp" type="checkbox" value="on" id="stream5_track_tcp"  
				<?php if ($pconfig['stream5_track_tcp']=="on") echo "checked"; ?> onclick="stream5_track_tcp_enable_change();">
				<?php echo gettext("Track and reassemble TCP sessions.  Default is ") . 
				"<strong>" . gettext("Checked") . "</strong>."; ?>
				<br/>
			<input name="stream5_track_udp" type="checkbox" value="on" id="stream5_track_udp" 
				<?php if ($pconfig['stream5_track_udp']=="on") echo "checked"; ?> onclick="stream5_track_udp_enable_change();">
				<?php echo gettext("Track and reassemble UDP sessions.  Default is ") . 
				"<strong>" . gettext("Checked") . "</strong>."; ?>
				<br/>
			<input name="stream5_track_icmp" type="checkbox" value="on" id="stream5_track_icmp" 
				<?php if ($pconfig['stream5_track_icmp']=="on") echo "checked"; ?> onclick="stream5_track_icmp_enable_change();">
				<?php echo gettext("Track and reassemble ICMP sessions.  Default is ") . 
				"<strong>" . gettext("Not Checked") . "</strong>."; ?>
		</td>
	</tr>
	<tr id="stream5_maxudp_row">
		<td valign="top" class="vncell"><?php echo gettext("Maximum UDP Sessions"); ?></td>
		<td class="vtable">
			<input name="stream5_max_udp" type="text" class="formfld unknown" id="stream5_max_udp" size="9" 
			value="<?=htmlspecialchars($pconfig['stream5_max_udp']);?>">
			<?php echo gettext("Maximum concurrent UDP sessions.  Min is ") . "<strong>1</strong>" . gettext(" and Max is ") . 
			"<strong>" . gettext("1048576") . "</strong>.";?><br/>
			<?php echo gettext("Sets the maximum number of concurrent UDP sessions that will be tracked.  Default value is ") .
			"<strong>" . gettext("131072") . "</strong>."; ?><br/>
		</td>
	</tr>
	<tr id="stream5_udp_sess_timeout_row">
		<td valign="top" class="vncell"><?php echo gettext("UDP Session Timeout"); ?></td>
		<td class="vtable">
			<input name="stream5_udp_timeout" type="text" class="formfld unknown" id="stream5_udp_timeout" size="9" 
			value="<?=htmlspecialchars($pconfig['stream5_udp_timeout']);?>">
			<?php echo gettext("UDP Session timeout in seconds.  Min is ") . "<strong>1</strong>" . gettext(" and Max is ") . 
			"<strong>" . gettext("86400") . "</strong>" . gettext(" (1 day).");?><br/>
			<?php echo gettext("Sets the session reassembly timeout period for UDP packets.  Default value is ") .
			"<strong>" . gettext("30") . "</strong>" . gettext(" seconds."); ?><br/>
		</td>
	</tr>
	<tr id="stream5_maxicmp_row">
		<td valign="top" class="vncell"><?php echo gettext("Maximum ICMP Sessions"); ?></td>
		<td class="vtable">
			<input name="stream5_max_icmp" type="text" class="formfld unknown" id="stream5_max_icmp" size="9" 
			value="<?=htmlspecialchars($pconfig['stream5_max_icmp']);?>">
			<?php echo gettext("Maximum concurrent ICMP sessions.  Min is ") . "<strong>1</strong>" . gettext(" and Max is ") . 
			"<strong>" . gettext("1048576") . "</strong>.";?><br/>
			<?php echo gettext("Sets the maximum number of concurrent ICMP sessions that will be tracked.  Default value is ") .
			"<strong>" . gettext("65536") . "</strong>."; ?><br/>
		</td>
	</tr>
	<tr id="stream5_icmp_sess_timeout_row">
		<td valign="top" class="vncell"><?php echo gettext("ICMP Session Timeout"); ?></td>
		<td class="vtable">
			<input name="stream5_icmp_timeout" type="text" class="formfld unknown" id="stream5_icmp_timeout" size="9" 
			value="<?=htmlspecialchars($pconfig['stream5_icmp_timeout']);?>">
			<?php echo gettext("ICMP Session timeout in seconds.  Min is ") . "<strong>1</strong>" . gettext(" and Max is ") . 
			"<strong>86400</strong>" . gettext(" (1 day).");?><br/>
			<?php echo gettext("Sets the session reassembly timeout period for ICMP packets.  Default value is ") .
			"<strong>" . gettext("30") . "</strong>" . gettext(" seconds."); ?><br/>
		</td>
	</tr>
	<tr id="stream5_maxtcp_row">
		<td valign="top" class="vncell"><?php echo gettext("Maximum TCP Sessions"); ?></td>
		<td class="vtable">
			<input name="stream5_max_tcp" type="text" class="formfld unknown" id="stream5_max_tcp" size="9" 
			value="<?=htmlspecialchars($pconfig['stream5_max_tcp']);?>">
			<?php echo gettext("Maximum concurrent TCP sessions.  Min is ") . "<strong>1</strong>" . gettext(" and Max is ") . 
			"<strong>" . gettext("1048576") . "</strong>.";?><br/>
			<?php echo gettext("Sets the maximum number of concurrent TCP sessions that will be tracked.  Default value is ") .
			"<strong>" . gettext("262144") . "</strong>."; ?><br/>
		</td>
	</tr>
	<tr id="stream5_tcp_memcap_row">
		<td valign="top" class="vncell"><?php echo gettext("TCP Memory Cap"); ?></td>
		<td class="vtable">
			<input name="stream5_mem_cap" type="text" class="formfld unknown" id="stream5_mem_cap" size="9" 
			value="<?=htmlspecialchars($pconfig['stream5_mem_cap']);?>">
			<?php echo gettext("Memory for TCP packet storage.  Min is ") . "<strong>" . gettext("32768") . "</strong>" .
			gettext(" and Max is ") . "<strong>" . gettext("1073741824") . "</strong>" .
			gettext(" bytes.");?><br/>
			<?php echo gettext("The memory cap in bytes for TCP packet storage " .
			"in RAM. Default value is ") . "<strong>" . gettext("8388608") . "</strong>" . gettext(" (8 MB)"); ?>.<br/>
		</td>
	</tr>
	<tr id="stream5_tcp_engconf_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("TCP Engine Configuration"); ?></td>
		<td class="vtable">
			<table width="95%" align="left" id="stream5EnginesTable" style="table-layout: fixed;" border="0" cellspacing="0" cellpadding="0">
				<colgroup>
					<col width="45%" align="left">
					<col width="45%" align="center">
					<col width="10%" align="right">
				</colgroup>
			   <thead>
				<tr>
					<th class="listhdrr" axis="string"><?php echo gettext("Engine Name");?></th>
					<th class="listhdrr" axis="string"><?php echo gettext("Bind-To Address Alias");?></th>
					<th class="list" align="right"><a href="snort_import_aliases.php?id=<?=$id?>&eng=stream5_tcp_engine">
					<img src="../themes/<?= $g['theme'];?>/images/icons/icon_import_alias.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Import TCP engine configuration from existing Aliases");?>"></a>
					<a href="snort_stream5_engine.php?id=<?=$id?>&eng_id=<?=$stream5_tcp_engine_next_id?>">
					<img src="../themes/<?= $g['theme'];?>/images/icons/icon_plus.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Add a new TCP engine configuration");?>"></a></th>
				</tr>
			   </thead>
			<?php foreach ($pconfig['stream5_tcp_engine']['item'] as $f => $v): ?>
				<tr>
					<td class="listlr" align="left"><?=gettext($v['name']);?></td>
					<td class="listbg" align="center"><?=gettext($v['bind_to']);?></td>
					<td class="listt" align="right"><a href="snort_stream5_engine.php?id=<?=$id;?>&eng_id=<?=$f;?>">
					<img src="/themes/<?=$g['theme'];?>/images/icons/icon_e.gif"  
					width="17" height="17" border="0" title="<?=gettext("Edit this TCP engine configuration");?>"></a>
			<?php if ($v['bind_to'] <> "all") : ?> 
					<input type="image" name="del_stream5_tcp[]" onclick="document.getElementById('eng_id').value='<?=$f;?>'; return confirm('Are you sure you want to delete this entry?');" 
					src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" width="17" height="17" border="0" 
					title="<?=gettext("Delete this TCP engine configuration");?>"/>
			<?php else : ?>
					<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x_d.gif" width="17" height="17" border="0" 
					title="<?=gettext("Default engine configuration cannot be deleted");?>">
			<?php endif ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</table>
		</td>
	</tr>

	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Application ID Detection"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable"); ?></td>
		<td width="78%" class="vtable"><input name="appid_preproc" onclick="appid_preproc_enable_change();" 
			type="checkbox" value="on" id="appid_preproc"  
			<?php if ($pconfig['appid_preproc']=="on") echo "checked"; ?>/>
		<?php echo gettext("Use OpenAppID to detect various applications.  Default is ") . 
		"<strong>" . gettext("Not Checked") . "</strong>"; ?>.</td>
	</tr>
	<tbody id="appid_rows">
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Memory Cap"); ?></td>
		<td class="vtable">
			<input name="sf_appid_mem_cap" type="text" class="formfld unknown" id="sf_appid_mem_cap" size="9" 
			value="<?=htmlspecialchars($pconfig['sf_appid_mem_cap']);?>">
			<?php echo gettext("Memory for App ID structures.  Min is ") . "<strong>" . gettext("32") . "</strong>" .
			gettext(" (32 MB) and Max is ") . "<strong>" . gettext("3000") . "</strong>" .
			gettext(" (3 GB) bytes.");?><br/>
			<?php echo gettext("The memory cap in megabytes used by AppID internal structures " .
			"in RAM. Default value is ") . "<strong>" . gettext("256") . "</strong>" . gettext(" (256 MB)."); ?><br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("AppID Stats Logging"); ?></td>
		<td width="78%" class="vtable">
			<input name="sf_appid_statslog" type="checkbox" value="on" id="sf_appid_statslog" 
			<?php if ($pconfig['sf_appid_statslog']=="on") echo "checked"; ?>/>
		<?php echo gettext("Enable OpenAppID statistics logging.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>" . gettext("."); ?><br/><br/>
		<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . gettext("log size and retention limits for AppID Stats Logging") . 
		gettext(" can be set on the ") . "<a href='/snort/snort_log_mgmt.php'>" . gettext("LOG MGMT") . "</a>" . gettext(" tab.");?> </td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("AppID Stats Period"); ?></td>
		<td class="vtable">
			<input name="sf_appid_stats_period" type="text" class="formfld unknown" id="sf_appid_stats_period" size="9" 
			value="<?=htmlspecialchars($pconfig['sf_appid_stats_period']);?>">
			<?php echo gettext("Bucket size in seconds for AppID stats.  Min is ") . "<strong>" . gettext("60") . "</strong>" .
			gettext(" (1 minute) and Max is ") . "<strong>" . gettext("3600") . "</strong>" . gettext(" (1 hour).");?><br/>
			<?php echo gettext("The bucket size in seconds used to collecxt AppID statistics. " .
			"Default value is ") . "<strong>" . gettext("300") . "</strong>" . gettext(" (5 minutes)."); ?><br/>
		</td>
	</tr>
	</tbody>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Portscan Detection"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable"); ?></td>
		<td width="78%" class="vtable"><input name="sf_portscan" onclick="sf_portscan_enable_change();" 
			type="checkbox" value="on" id="sf_portscan"   
			<?php if ($pconfig['sf_portscan']=="on") echo "checked"; ?>/>
		<?php echo gettext("Use Portscan Detection to detect various types of port scans and sweeps.  Default is ") . 
		"<strong>" . gettext("Not Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr id="portscan_protocol_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Protocol"); ?> </td>
		<td width="78%" class="vtable">
			<select name="pscan_protocol" class="formselect" id="pscan_protocol"> 
			<?php
			$protos = array('all', 'tcp', 'udp', 'icmp', 'ip');
			foreach ($protos as $val): ?>
			<option value="<?=$val;?>"
			<?php if ($val == $pconfig['pscan_protocol']) echo "selected"; ?>> 
				<?=gettext($val);?></option>
				<?php endforeach; ?>
			</select>&nbsp;&nbsp;<?php echo gettext("Choose the Portscan protocol type to alert for (all, tcp, udp, icmp or ip).  Default is ") . 
			"<strong>" . gettext("all") . "</strong>."; ?><br/>
		</td>
	</tr>
	<tr id="portscan_type_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Scan Type"); ?> </td>
		<td width="78%" class="vtable">
			<select name="pscan_type" class="formselect" id="pscan_type"> 
			<?php
			$protos = array('all', 'portscan', 'portsweep', 'decoy_portscan', 'distributed_portscan');
			foreach ($protos as $val): ?>
			<option value="<?=$val;?>"
			<?php if ($val == $pconfig['pscan_type']) echo "selected"; ?>> 
				<?=gettext($val);?></option>
				<?php endforeach; ?>
			</select>&nbsp;&nbsp;<?php echo gettext("Choose the Portscan scan type to alert for.  Default is ") . 
			"<strong>" . gettext("all") . "</strong>."; ?><br/>
			<table width="100%" border="0" cellpadding="0" cellspacing="0">
			  <tr>
				<td><?php echo gettext("PORTSCAN: one->one scan; one host scans multiple ports on another host."); ?></td>
			  </tr>
			  <tr> 
				<td><?php echo gettext("PORTSWEEP: one->many scan; one host scans a single port on multiple hosts."); ?></td>
			  </tr>
			  <tr>
				<td><?php echo gettext("DECOY_PORTSCAN: one->one scan; attacker has spoofed source address inter-mixed with real scanning address."); ?></td>
			  </tr>
			  <tr>
				<td><?php echo gettext("DISTRIBUTED_PORTSCAN: many->one scan; multiple hosts query one host for open services."); ?></td>
			  </tr>
			  <tr>
				<td><?php echo gettext("ALL: alerts for all of the above scan types."); ?></td>
			  </tr>
			</table>
		</td>
	</tr>
	<tr id="portscan_sensitivity_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Sensitivity"); ?> </td>
		<td width="78%" class="vtable">
			<select name="pscan_sense_level" class="formselect" id="pscan_sense_level"> 
			<?php
			$levels = array('low', 'medium', 'high');
			foreach ($levels as $val): ?>
			<option value="<?=$val;?>"
			<?php if ($val == $pconfig['pscan_sense_level']) echo "selected"; ?>> 
				<?=gettext(ucfirst($val));?></option>
				<?php endforeach; ?>
			</select>&nbsp;&nbsp;<?php echo gettext("Choose the Portscan sensitivity level (Low, Medium, High).  Default is ") . 
			"<strong>" . gettext("Medium") . "</strong>."; ?><br/>
			<table width="100%" border="0" cellpadding="0" cellspacing="0">
			  <tr>
				<td><?php echo gettext("LOW: alerts generated on error packets from the target host; "); ?>
				<?php echo gettext("this setting should see few false positives.  "); ?></td>
			  </tr>
			  <tr>
				<td><?php echo gettext("MEDIUM: tracks connection counts, so will generate filtered alerts; may "); ?>
				    <?php echo gettext("false positive on active hosts."); ?></td>
			  </tr>
			  <tr>
				<td><?php echo gettext("HIGH: tracks hosts using a time window; will catch some slow scans, but is "); ?>
				    <?php echo gettext("very sensitive to active hosts."); ?></td>
			  </tr>
			</table>
		</td>
	</tr>
	<tr id="portscan_memcap_row">
		<td valign="top" class="vncell"><?php echo gettext("Memory Cap"); ?></td>
		<td class="vtable">
		<table cellpadding="0" cellspacing="0">
			<tr>
				<td class="vexpl"><input name="pscan_memcap" type="text" class="formfld unknown" 
					id="pscan_memcap" size="9"
					value="<?=htmlspecialchars($pconfig['pscan_memcap']);?>">
				<?php echo gettext("Maximum memory in bytes to allocate for portscan detection.  ") . 
				gettext("Default is ") . "<strong>" . gettext("10000000") . "</strong>" . 
				gettext(" (10 MB)"); ?>.</td>
			</tr>
		</table>
		<?php echo gettext("The maximum number of bytes to allocate for portscan detection.  The higher this number, ") . 
		gettext("the more nodes that can be tracked.  Default is ") . 
		"<strong>10,000,000</strong>" . gettext(" bytes.  (10 MB)"); ?><br/>
		</td>
	</tr>
	<tr id="portscan_ignorescanners_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Ignore Scanners"); ?></td>
		<td width="78%" class="vtable">
			<table width="95%" cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td class="vexpl">
					<input name="pscan_ignore_scanners" type="text" size="25" autocomplete="off"  class="formfldalias" id="pscan_ignore_scanners" 
					value="<?=$pconfig['pscan_ignore_scanners'];?>" title="<?=trim(filter_expand_alias($pconfig['pscan_ignore_scanners']));?>">&nbsp;&nbsp;<?php echo gettext("Leave blank for default.  ") . 
					gettext("Default value is ") . "<strong>" . gettext("\$HOME_NET") . "</strong>"; ?>.</td>
					<td class="vexpl" align="right">
						<input type="button" class="formbtns" value="Aliases" onclick="selectAlias();"  
						title="<?php echo gettext("Select an existing IP alias");?>"/></td>
				</tr>
				<tr>
					<td class="vexpl" colspan="2"><?php echo gettext("Ignores the specified entity as a source of scan alerts.  Entity must be a defined alias."); ?></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr id="ftp_telnet_row">
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("FTP and Telnet Global Options"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable"); ?></td>
		<td width="78%" class="vtable"><input name="ftp_preprocessor" type="checkbox" value="on" 
			<?php if ($pconfig['ftp_preprocessor']=="on") echo "checked"; ?> onclick="ftp_telnet_enable_change();">
		<?php echo gettext("Normalize/Decode FTP and Telnet traffic and protocol anomalies.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr id="ftp_telnet_row_type">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Inspection Type"); ?> </td>
		<td width="78%" class="vtable">
			<select name="ftp_telnet_inspection_type" class="formselect" id="ftp_telnet_inspection_type"> 
			<?php
			$values = array('stateful', 'stateless');
			foreach ($values as $val): ?>
			<option value="<?=$val;?>"
			<?php if ($val == $pconfig['ftp_telnet_inspection_type']) echo "selected"; ?>> 
				<?=gettext($val);?></option>
				<?php endforeach; ?>
			</select>&nbsp;&nbsp;<?php echo gettext("Choose to operate in stateful or stateless mode.  Default is ") . 
			"<strong>" . gettext("stateful") . "</strong>."; ?><br/>
		</td>
		</tr>
	<tr id="ftp_telnet_row_encrypted_check">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Check Encrypted Traffic"); ?></td>
		<td width="78%" class="vtable"><input name="ftp_telnet_check_encrypted" type="checkbox" value="on" 
			<?php if ($pconfig['ftp_telnet_check_encrypted']=="on") echo "checked"; ?>/>
		<?php echo gettext("Continue to check an encrypted session for subsequent command to cease encryption.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr id="ftp_telnet_row_encrypted_alert">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Alert on Encrypted Commands"); ?></td>
		<td width="78%" class="vtable"><input name="ftp_telnet_alert_encrypted" type="checkbox" value="on" 
			<?php if ($pconfig['ftp_telnet_alert_encrypted']=="on") echo "checked"; ?>/>
		<?php echo gettext("Alert on encrypted FTP and Telnet command channels.  Default is ") . 
		"<strong>" . gettext("Not Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr id="ftp_telnet_row_telnet_proto_opts">
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Telnet Protocol Options"); ?></td>
	</tr>
	<tr id="ftp_telnet_row_normalize">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Normalization"); ?></td>
		<td width="78%" class="vtable"><input name="ftp_telnet_normalize" type="checkbox" value="on" 
			<?php if ($pconfig['ftp_telnet_normalize']=="on") echo "checked"; ?>/>
		<?php echo gettext("Normalize Telnet traffic by eliminating Telnet escape sequences.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr id="ftp_telnet_row_detect_anomalies">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Detect Anomalies"); ?></td>
		<td width="78%" class="vtable"><input name="ftp_telnet_detect_anomalies" type="checkbox" value="on" 
			<?php if ($pconfig['ftp_telnet_detect_anomalies']=="on") echo "checked"; ?>/>
		<?php echo gettext("Alert on Telnet subnegotiation begin without corresponding subnegotiation end.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr id="ftp_telnet_row_ayt_threshold">
		<td valign="top" class="vncell"><?php echo gettext("AYT Attack Threshold"); ?></td>
		<td class="vtable">
			<input name="ftp_telnet_ayt_attack_threshold" type="text" class="formfld unknown" id="ftp_telnet_ayt_attack_threshold" size="9" 
			value="<?=htmlspecialchars($pconfig['ftp_telnet_ayt_attack_threshold']);?>">
			<?php echo gettext("Are-You-There (AYT) command alert threshold.  Enter ") . "<strong>" . gettext("0") . "</strong>" . 
			gettext(" to disable.  Default is ") . "<strong>" . gettext("20.") . "</strong>";?><br/>
			<?php echo gettext("Alert when the number of consecutive Telnet AYT commands reaches the number specified.");?><br/>
		</td>
	</tr>
	<tr id="ftp_telnet_row_ftp_proto_opts">
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("FTP Protocol Options"); ?></td>
	</tr>
	<tr id="ftp_telnet_ftp_client_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Client Configuration"); ?></td>
		<td class="vtable">
			<table width="95%" align="left" id="FTPclientEnginesTable" style="table-layout: fixed;" border="0" cellspacing="0" cellpadding="0">
				<colgroup>
					<col width="45%" align="left">
					<col width="45%" align="center">
					<col width="10%" align="right">
				</colgroup>
			   <thead>
				<tr>
					<th class="listhdrr" axis="string"><?php echo gettext("Engine Name");?></th>
					<th class="listhdrr" axis="string"><?php echo gettext("Bind-To Address Alias");?></th>
					<th class="list" align="right"><a href="snort_import_aliases.php?id=<?=$id?>&eng=ftp_client_engine">
					<img src="../themes/<?= $g['theme'];?>/images/icons/icon_import_alias.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Import client configuration from existing Aliases");?>"></a>
					<a href="snort_ftp_client_engine.php?id=<?=$id?>&eng_id=<?=$ftp_client_engine_next_id?>">
					<img src="../themes/<?= $g['theme'];?>/images/icons/icon_plus.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Add a new FTP client configuration");?>"></a></th>
				</tr>
			   </thead>
			<?php foreach ($pconfig['ftp_client_engine']['item'] as $f => $v): ?>
				<tr>
					<td class="listlr" align="left"><?=gettext($v['name']);?></td>
					<td class="listbg" align="center"><?=gettext($v['bind_to']);?></td>
					<td class="listt" align="right"><a href="snort_ftp_client_engine.php?id=<?=$id;?>&eng_id=<?=$f;?>">
					<img src="/themes/<?=$g['theme'];?>/images/icons/icon_e.gif"  
					width="17" height="17" border="0" title="<?=gettext("Edit this FTP client configuration");?>"></a>
			<?php if ($v['bind_to'] <> "all") : ?> 
					<input type="image" name="del_ftp_client[]" onclick="document.getElementById('eng_id').value='<?=$f;?>'; return confirm('Are you sure you want to delete this entry?');" 
					src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" width="17" height="17" border="0" 
					title="<?=gettext("Delete this FTP client configuration");?>"/>
			<?php else : ?>
					<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x_d.gif" width="17" height="17" border="0" 
					title="<?=gettext("Default client configuration cannot be deleted");?>">
			<?php endif ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<tr id="ftp_telnet_ftp_server_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Server Configuration"); ?></td>
		<td class="vtable">
			<table width="95%" align="left" id="FTPserverEnginesTable" style="table-layout: fixed;" border="0" cellspacing="0" cellpadding="0">
				<colgroup>
					<col width="45%" align="left">
					<col width="45%" align="center">
					<col width="10%" align="right">
				</colgroup>
			   <thead>
				<tr>
					<th class="listhdrr" axis="string"><?php echo gettext("Engine Name");?></th>
					<th class="listhdrr" axis="string"><?php echo gettext("Bind-To Address Alias");?></th>
					<th class="list" align="right"><a href="snort_import_aliases.php?id=<?=$id?>&eng=ftp_server_engine">
					<img src="../themes/<?= $g['theme'];?>/images/icons/icon_import_alias.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Import server configuration from existing Aliases");?>"></a>
					<a href="snort_ftp_server_engine.php?id=<?=$id?>&eng_id=<?=$ftp_server_engine_next_id?>">
					<img src="../themes/<?= $g['theme'];?>/images/icons/icon_plus.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Add a new FTP Server configuration");?>"></a></th>
				</tr>
			   </thead>
			<?php foreach ($pconfig['ftp_server_engine']['item'] as $f => $v): ?>
				<tr>
					<td class="listlr" align="left"><?=gettext($v['name']);?></td>
					<td class="listbg" align="center"><?=gettext($v['bind_to']);?></td>
					<td class="listt" align="right"><a href="snort_ftp_server_engine.php?id=<?=$id;?>&eng_id=<?=$f;?>">
					<img src="/themes/<?=$g['theme'];?>/images/icons/icon_e.gif"  
					width="17" height="17" border="0" title="<?=gettext("Edit this FTP server configuration");?>"></a>
			<?php if ($v['bind_to'] <> "all") : ?> 
					<input type="image" name="del_ftp_server[]" onclick="document.getElementById('eng_id').value='<?=$f;?>'; return confirm('Are you sure you want to delete this entry?');" 
					src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" width="17" height="17" border="0" 
					title="<?=gettext("Delete this FTP server configuration");?>"/>
			<?php else : ?>
					<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x_d.gif" width="17" height="17" border="0" 
					title="<?=gettext("Default server configuration cannot be deleted");?>">
			<?php endif ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Sensitive Data Detection"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable"); ?></td>
		<td width="78%" class="vtable">
			<input name="sensitive_data" type="checkbox" value="on" onclick="sensitive_data_enable_change();" 
			<?php if ($pconfig['sensitive_data'] == "on")
				 echo "checked";
			      elseif ($vrt_enabled == "off")
				 echo "disabled";
			?>/>
			<?php echo gettext("Sensitive data searches for credit card numbers, Social Security numbers and e-mail addresses in data."); ?>
		<br/>
		<span class="red"><strong><?php echo gettext("Note: "); ?></strong></span><?php echo gettext("To enable this preprocessor, you must select the Snort VRT rules on the ") . 
		"<a href=\"/snort/snort_interfaces_global.php\" title=\"" . gettext("Modify Snort global settings") . "\">" . gettext("Global Settings") . "</a>" . gettext(" tab."); ?>
		</td>
	</tr>
	<tr id="sdf_alert_data_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Inspect for"); ?> </td>
		<td width="78%" class="vtable">
			<select name="sdf_alert_data_type[]" class="formselect" id="sdf_alert_data_type" size="4" multiple="multiple"> 
			<?php
			$values = array('Credit Card', 'Email Addresses', 'U.S. Phone Numbers', 'U.S. Social Security Numbers');
			foreach ($values as $val): ?>
				<option value="<?=$val;?>"
				<?php if (strpos($pconfig['sdf_alert_data_type'], $val) !== FALSE) echo "selected"; ?>> 
				<?=gettext($val);?></option>
			<?php endforeach; ?>
			</select><br/><?php echo gettext("Choose which types of sensitive data to detect.  Use CTRL + Click for multiple selections."); ?><br/>
		</td>
	</tr>
	<tr id="sdf_alert_threshold_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Alert Threshold"); ?></td>
		<td width="78%" class="vtable"><input name="sdf_alert_threshold" type="text" class="formfld unknown" id="sdf_alert_threshold" size="9" value="<?=htmlspecialchars($pconfig['sdf_alert_threshold']);?>">
		<?php echo gettext("Personally Identifiable Information (PII) combination alert threshold.");?><br/>
		<?php echo gettext("This value sets the number of PII combinations required to trigger an alert.  This should be set higher than the highest individual count in your \"sd_pattern\" rules.  Default value is ") .
		"<strong>" . gettext("25") . "</strong>.";?>
		</td>
	</tr>
	<tr id="sdf_mask_output_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Mask Output"); ?></td>
		<td width="78%" class="vtable">
			<input name="sdf_mask_output" type="checkbox" value="on" 
			<?php if ($pconfig['sdf_mask_output'] == "on")
				 echo "checked";
			?>/>
			<?php echo gettext("Replace all but last 4 digits of PII with \"X\"s on credit card and Social Security Numbers. ") . 
			gettext("Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("POP3 Decoder Settings"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable POP3 Decoder"); ?></td>
		<td width="78%" class="vtable"><input name="pop_preproc" type="checkbox" value="on" 
			<?php if ($pconfig['pop_preproc']=="on") echo "checked"; ?> onclick="pop_enable_change();"/>
		<?php echo gettext("Normalize/Decode POP3 protocol for enforcement and buffer overflows.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>
	</tr>
	<tbody id="pop_setting_rows">
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Memory Cap"); ?></td>
		<td width="78%" class="vtable">
			<input name="pop_memcap" type="text" class="formfld unknown" id="pop_memcap" size="9" 
			value="<?=htmlspecialchars($pconfig['pop_memcap']);?>">
			<?php echo gettext("Maximum memory in bytes to use for decoding attachments.  ") . 
			gettext("Default is ") . "<strong>" . gettext("838860") . "</strong>" . 
			gettext(" bytes."); ?><br/><br/>
			<?php echo gettext("The minimum value is ") . "<strong>" . gettext("3276") . "</strong>" . gettext(" bytes and the maximum is ") . 
			"<strong>" . gettext("100 MB") . "</strong>" . gettext(" (104857600). An IMAP preprocessor alert with sid 3 is ") . 
			gettext("generated (when enabled) if this limit is exceeded."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Base64 Decoding Depth"); ?></td>
		<td width="78%" class="vtable"><input name="pop_b64_decode_depth" type="text" class="formfld unknown" id="pop_b64_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['pop_b64_decode_depth']);?>">
			<?php echo gettext("Depth in bytes to decode base64 encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the base64 decoding of MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the decoding of base64 encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the decoding of base64 MIME attachments, and applies per attachment. A POP preprocessor alert with sid 4 ") . 
			gettext("is generated (if enabled) when the decoding fails.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Quoted Printable Decoding Depth"); ?></td>
		<td width="78%" class="vtable"><input name="pop_qp_decode_depth" type="text" class="formfld unknown" id="pop_qp_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['pop_qp_decode_depth']);?>">
			<?php echo gettext("Byte depth to decode Quoted Printable (QP) encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the QP decoding of MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the decoding of QP encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the decoding of QP MIME attachments, and applies per attachment. A POP preprocessor alert with sid 5 ") . 
			gettext("is generated (if enabled) when the decoding fails.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Non-Encoded MIME Extraction Depth"); ?></td>
		<td width="78%" class="vtable"><input name="pop_bitenc_decode_depth" type="text" class="formfld unknown" id="pop_bitenc_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['pop_bitenc_decode_depth']);?>">
			<?php echo gettext("Depth in bytes to extract non-encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the extraction of non-encoded MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the extraction of non-encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the extraction of non-encoded MIME attachments, and applies per attachment.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Unix-to-Unix Decoding Depth"); ?></td>
		<td width="78%" class="vtable"><input name="pop_uu_decode_depth" type="text" class="formfld unknown" id="pop_uu_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['pop_uu_decode_depth']);?>">
			<?php echo gettext("Depth in bytes to decode Unix-to-Unix (UU) encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the UU decoding of MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the decoding of UU encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the decoding of UU MIME attachments, and applies per attachment. A POP preprocessor alert with sid 7 ") . 
			gettext("is generated (if enabled) when the decoding fails.");?>
		</td>
	</tr>
	</tbody>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("IMAP Decoder Settings"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable IMAP Decoder"); ?></td>
		<td width="78%" class="vtable"><input name="imap_preproc" type="checkbox" value="on" 
			<?php if ($pconfig['imap_preproc']=="on") echo "checked"; ?> onclick="imap_enable_change();"/>
			<?php echo gettext("Normalize/Decode IMAP protocol for enforcement and buffer overflows.  Default is ") . 
			"<strong>" . gettext("Checked") . "</strong>."; ?>
		</td>
	</tr>
	<tbody id="imap_setting_rows">
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Memory Cap"); ?></td>
		<td width="78%" class="vtable">
			<input name="imap_memcap" type="text" class="formfld unknown" id="imap_memcap" size="9" 
			value="<?=htmlspecialchars($pconfig['imap_memcap']);?>">
			<?php echo gettext("Maximum memory in bytes to use for decoding attachments.  ") . 
			gettext("Default is ") . "<strong>" . gettext("838860") . "</strong>" . 
			gettext(" bytes."); ?><br/><br/>
			<?php echo gettext("The minimum value is ") . "<strong>" . gettext("3276") . "</strong>" . gettext(" bytes and the maximum is ") . 
			"<strong>" . gettext("100 MB") . "</strong>" . gettext(" (104857600). An IMAP preprocessor alert with sid 3 is ") . 
			gettext("generated (when enabled) if this limit is exceeded."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Base64 Decoding Depth"); ?></td>
		<td width="78%" class="vtable"><input name="imap_b64_decode_depth" type="text" class="formfld unknown" id="imap_b64_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['imap_b64_decode_depth']);?>">
			<?php echo gettext("Depth in bytes to decode base64 encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the base64 decoding of MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the decoding of base64 encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the decoding of base64 MIME attachments, and applies per attachment. An IMAP preprocessor alert with sid 4 ") . 
			gettext("is generated (if enabled) when the decoding fails.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Quoted Printable Decoding Depth"); ?></td>
		<td width="78%" class="vtable"><input name="imap_qp_decode_depth" type="text" class="formfld unknown" id="imap_qp_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['imap_qp_decode_depth']);?>">
			<?php echo gettext("Byte depth to decode Quoted Printable (QP) encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the QP decoding of MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the decoding of QP encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the decoding of QP MIME attachments, and applies per attachment. An IMAP preprocessor alert with sid 5 ") . 
			gettext("is generated (if enabled) when the decoding fails.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Non-Encoded MIME Extraction Depth"); ?></td>
		<td width="78%" class="vtable"><input name="imap_bitenc_decode_depth" type="text" class="formfld unknown" id="imap_bitenc_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['imap_bitenc_decode_depth']);?>">
			<?php echo gettext("Depth in bytes to extract non-encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the extraction of non-encoded MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the extraction of non-encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the extraction of non-encoded MIME attachments, and applies per attachment.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Unix-to-Unix Decoding Depth"); ?></td>
		<td width="78%" class="vtable"><input name="imap_uu_decode_depth" type="text" class="formfld unknown" id="imap_uu_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['imap_uu_decode_depth']);?>">
			<?php echo gettext("Depth in bytes to decode Unix-to-Unix (UU) encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the UU decoding of MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the decoding of UU encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the decoding of UU MIME attachments, and applies per attachment. An IMAP preprocessor alert with sid 7 ") . 
			gettext("is generated (if enabled) when the decoding fails.");?>
		</td>
	</tr>
	</tbody>

	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("SMTP Decoder Settings"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable SMTP Decoder"); ?></td>
		<td width="78%" class="vtable"><input name="smtp_preprocessor" type="checkbox" value="on" 
			<?php if ($pconfig['smtp_preprocessor']=="on") echo "checked"; ?> onclick="smtp_enable_change();"/>
			<?php echo gettext("Normalize/Decode SMTP protocol for enforcement and buffer overflows.  Default is ") . 
			"<strong>" . gettext("Checked") . "</strong>."; ?>
		</td>
	</tr>
	<tbody id="smtp_setting_rows">
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Memory Cap"); ?></td>
		<td width="78%" class="vtable">
			<input name="smtp_memcap" type="text" class="formfld unknown" id="smtp_memcap" size="9" 
			value="<?=htmlspecialchars($pconfig['smtp_memcap']);?>"/>
			<?php echo gettext("Max memory in bytes used to log filename, addresses and headers.  ") . 
			gettext("Default is ") . "<strong>" . gettext("838860") . "</strong>" . gettext(" bytes."); ?><br/><br/>
			<?php echo gettext("The minimum value is ") . "<strong>" . gettext("3276") . "</strong>" . gettext(" bytes and the maximum is ") . 
			"<strong>" . gettext("100 MB") . "</strong>" . gettext(" (104857600).  When this memcap is reached, ") . 
			gettext("SMTP will stop logging the filename, MAIL FROM address, RCPT TO addresses and email headers until memory becomes available."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Ignore Data"); ?></td>
		<td width="78%" class="vtable"><input name="smtp_ignore_data" type="checkbox" value="on" 
			<?php if ($pconfig['smtp_ignore_data']=="on") echo "checked"; ?>/>
			<?php echo gettext("Ignore data section of mail (except for mail headers) when processing rules.  Default is ") . 
			"<strong>" . gettext("Not Checked") . "</strong>."; ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Ignore TLS Data"); ?></td>
		<td width="78%" class="vtable"><input name="smtp_ignore_tls_data" type="checkbox" value="on" 
			<?php if ($pconfig['smtp_ignore_tls_data']=="on") echo "checked"; ?>/>
			<?php echo gettext("Ignore TLS-encrypted data when processing rules.  Default is ") . 
			"<strong>" . gettext("Checked") . "</strong>."; ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Log Mail From"); ?></td>
		<td width="78%" class="vtable"><input name="smtp_log_mail_from" type="checkbox" value="on" 
			<?php if ($pconfig['smtp_log_mail_from']=="on") echo "checked"; ?>/>
			<?php echo gettext("Log sender email address extracted from MAIL FROM command.  Default is ") . 
			"<strong>" . gettext("Checked") . "</strong>."; ?><br/>
			<span class="red"><strong><?php echo gettext("Note: "); ?></strong></span>
			<?php echo gettext("this is logged only with the unified2 (Barnyard2) output enabled."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Log Receipt To"); ?></td>
		<td width="78%" class="vtable"><input name="smtp_log_rcpt_to" type="checkbox" value="on" 
			<?php if ($pconfig['smtp_log_rcpt_to']=="on") echo "checked"; ?>/>
			<?php echo gettext("Log recipient email addresses extracted from RCPT TO command.  Default is ") . 
			"<strong>" . gettext("Checked") . "</strong>."; ?><br/>
			<span class="red"><strong><?php echo gettext("Note: "); ?></strong></span>
			<?php echo gettext("this is logged only with the unified2 (Barnyard2) output enabled."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Log Filename"); ?></td>
		<td width="78%" class="vtable"><input name="smtp_log_filename" type="checkbox" value="on" 
			<?php if ($pconfig['smtp_log_filename']=="on") echo "checked"; ?>/>
			<?php echo gettext("Log MIME attachment filenames extracted from Content-Disposition header.  Default is ") . 
			"<strong>" . gettext("Checked") . "</strong>."; ?><br/>
			<span class="red"><strong><?php echo gettext("Note: "); ?></strong></span>
			<?php echo gettext("this is logged only with the unified2 (Barnyard2) output enabled."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Log E-Mail Headers"); ?></td>
		<td width="78%" class="vtable"><input name="smtp_log_email_hdrs" type="checkbox" value="on" 
			<?php if ($pconfig['smtp_log_email_hdrs']=="on") echo "checked"; ?>/>
			<?php echo gettext("Log SMTP email headers extracted from SMTP data.  Default is ") . 
			"<strong>" . gettext("Checked") . "</strong>."; ?><br/>
			<span class="red"><strong><?php echo gettext("Note: "); ?></strong></span>
			<?php echo gettext("this is logged only with the unified2 (Barnyard2) output enabled."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("E-Mail Headers Log Depth"); ?></td>
		<td width="78%" class="vtable">
			<input name="smtp_email_hdrs_log_depth" type="text" class="formfld unknown" id="smtp_email_hdrs_log_depth" size="9" 
			value="<?=htmlspecialchars($pconfig['smtp_email_hdrs_log_depth']);?>"/>
			<?php echo gettext("Memory in bytes to use for logging e-mail headers.  ") . 
			gettext("Default is ") . "<strong>" . gettext("1464") . "</strong>" . gettext(" bytes."); ?><br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("0") . "</strong>" . gettext(" to ") . 
			"<strong>" . gettext("20480") . "</strong>" . gettext(".  A value of ") . "<strong>" . gettext("0") . "</strong>" . 
			gettext(" will disable e-mail headers logging."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Maximum MIME Memory"); ?></td>
		<td width="78%" class="vtable">
			<input name="smtp_max_mime_mem" type="text" class="formfld unknown" id="smtp_max_mime_mem" size="9" 
			value="<?=htmlspecialchars($pconfig['smtp_max_mime_mem']);?>"/>
			<?php echo gettext("Maximum memory in bytes to use for decoding attachments.  ") . 
			gettext("Default is ") . "<strong>" . gettext("838860") . "</strong>" . gettext(" bytes."); ?><br/><br/>
			<?php echo gettext("The minimum value is ") . "<strong>" . gettext("3276") . "</strong>" . gettext(" bytes and the maximum is ") . 
			"<strong>" . gettext("100 MB") . "</strong>" . gettext(" (104857600)."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Base64 Decoding Depth"); ?></td>
		<td width="78%" class="vtable"><input name="smtp_b64_decode_depth" type="text" class="formfld unknown" id="smtp_b64_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['smtp_b64_decode_depth']);?>">
			<?php echo gettext("Depth in bytes to decode base64 encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the base64 decoding of MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the decoding of base64 encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the decoding of base64 MIME attachments, and applies per attachment. An SMTP preprocessor alert with sid 10 ") . 
			gettext("is generated when the decoding fails.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Quoted Printable Decoding Depth"); ?></td>
		<td width="78%" class="vtable"><input name="smtp_qp_decode_depth" type="text" class="formfld unknown" id="smtp_qp_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['smtp_qp_decode_depth']);?>">
			<?php echo gettext("Byte depth to decode Quoted Printable (QP) encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the QP decoding of MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the decoding of QP encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the decoding of QP MIME attachments, and applies per attachment. An SMTP preprocessor alert with sid 11 ") . 
			gettext("is generated when the decoding fails.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Non-Encoded MIME Extraction Depth"); ?></td>
		<td width="78%" class="vtable"><input name="smtp_bitenc_decode_depth" type="text" class="formfld unknown" id="smtp_bitenc_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['smtp_bitenc_decode_depth']);?>">
			<?php echo gettext("Depth in bytes to extract non-encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the extraction of non-encoded MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the extraction of non-encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the extraction of non-encoded MIME attachments, and applies per attachment.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Unix-to-Unix Decoding Depth"); ?></td>
		<td width="78%" class="vtable"><input name="smtp_uu_decode_depth" type="text" class="formfld unknown" id="smtp_uu_decode_depth" size="9" value="<?=htmlspecialchars($pconfig['smtp_uu_decode_depth']);?>">
			<?php echo gettext("Depth in bytes to decode Unix-to-Unix (UU) encoded MIME attachments.  Default is ") . "<strong>" . gettext("0") . "</strong>" . gettext(" (unlimited)");?>.<br/><br/>
			<?php echo gettext("Allowable values range from ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . 
			gettext(". A value of ") . "<strong>" . gettext("-1") . "</strong>" . gettext(" turns off the UU decoding of MIME attachments. ") . 
			gettext("A value of ") . "<strong>" . gettext("0") . "</strong>" . gettext(" sets the decoding of UU encoded MIME attachments to unlimited. ") . 
			gettext("A value other than 0 or -1 restricts the decoding of UU MIME attachments, and applies per attachment. An SMTP preprocessor alert with sid 13 ") . 
			gettext("is generated (if enabled) when the decoding fails.");?>
		</td>
	</tr>
	</tbody>


	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("General Preprocessors"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable RPC Decode and Back Orifice detector"); ?></td>
		<td width="78%" class="vtable"><input name="other_preprocs" type="checkbox" value="on" 
			<?php if ($pconfig['other_preprocs']=="on") echo "checked"; ?>/>
		<?php echo gettext("Normalize/Decode RPC traffic and detects Back Orifice traffic on the network.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable DCE/RPC2 Detection"); ?></td>
		<td width="78%" class="vtable"><input name="dce_rpc_2" type="checkbox" value="on" 
			<?php if ($pconfig['dce_rpc_2']=="on") echo "checked"; ?>/>
		<?php echo gettext("The DCE/RPC preprocessor detects and decodes SMB and DCE/RPC traffic.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable SIP Detection"); ?></td>
		<td width="78%" class="vtable"><input name="sip_preproc" type="checkbox" value="on" 
			<?php if ($pconfig['sip_preproc']=="on") echo "checked"; ?>/>
		<?php echo gettext("The SIP preprocessor decodes SIP traffic and detects vulnerabilities.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable GTP Detection"); ?></td>
		<td width="78%" class="vtable"><input name="gtp_preproc" type="checkbox" value="on" 
			<?php if ($pconfig['gtp_preproc']=="on") echo "checked"; ?>/>
		<?php echo gettext("The GTP preprocessor decodes GPRS Tunneling Protocol traffic and detects intrusion attempts."); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable SSH Detection"); ?></td>
		<td width="78%" class="vtable"><input name="ssh_preproc" type="checkbox" value="on" 
			<?php if ($pconfig['ssh_preproc']=="on") echo "checked"; ?>/>
		<?php echo gettext("The SSH preprocessor detects various Secure Shell exploit attempts."); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable DNS Detection"); ?></td>
		<td width="78%" class="vtable"><input name="dns_preprocessor" type="checkbox" value="on" 
			<?php if ($pconfig['dns_preprocessor']=="on") echo "checked"; ?>/>
		<?php echo gettext("The DNS preprocessor decodes DNS response traffic and detects vulnerabilities.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable SSL Data"); ?></td>
		<td width="78%" class="vtable">
			<input name="ssl_preproc" type="checkbox" value="on"  
			<?php if ($pconfig['ssl_preproc']=="on") echo "checked"; ?>/>
		<?php echo gettext("SSL data searches for irregularities during SSL protocol exchange.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>	
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("SCADA Preprocessors"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable Modbus Detection"); ?></td>
		<td width="78%" class="vtable">
			<input name="modbus_preproc" type="checkbox" value="on" 
			<?php if ($pconfig['modbus_preproc']=="on") echo "checked"; ?>/>
			<?php echo gettext("Modbus is a protocol used in SCADA networks.  The default port is TCP 502.") . "<br/>" . 
		  	"<span class=\"red\"><strong>" . gettext("Note: ") . "</strong></span>" . 
			gettext("If your network does not contain Modbus-enabled devices, you can leave this preprocessor disabled."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable DNP3 Detection"); ?></td>
		<td width="78%" class="vtable">
			<input name="dnp3_preproc" type="checkbox" value="on" 
			<?php if ($pconfig['dnp3_preproc']=="on") echo "checked"; ?>/>
			<?php echo gettext("DNP3 is a protocol used in SCADA networks.  The default port is TCP 20000.") . "<br/>" . 
		  	"<span class=\"red\"><strong>" . gettext("Note: ") . "</strong></span>" . 
			gettext("If your network does not contain DNP3-enabled devices, you can leave this preprocessor disabled."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top">&nbsp;</td>
		<td width="78%">
			<input name="save" type="submit" class="formbtn" value="Save" title="<?php echo 
			gettext("Save preprocessor settings"); ?>">
			&nbsp;&nbsp;&nbsp;&nbsp;
			<input name="ResetAll" type="submit" class="formbtn" value="Reset" title="<?php echo 
			gettext("Reset all settings to defaults") . "\" onclick=\"return confirm('" . 
			gettext("WARNING:  This will reset ALL preprocessor settings to their defaults.  Click OK to continue or CANCEL to quit.") . 
			"');\""; ?>/></td>
	</tr>
	<tr>
		<td width="22%" valign="top">&nbsp;</td>
		<td width="78%"><span class="vexpl"><span class="red"><strong><?php echo gettext("Note: "); ?></strong></span></span>
			<?php echo gettext("Please save your settings before you exit.  Preprocessor changes will rebuild the rules file.  This "); ?>
			<?php echo gettext("may take several seconds.  Snort must also be restarted to activate any changes made on this screen."); ?></td>
	</tr>
</table>
</div>
</td></tr></table>
</form>
<script type="text/javascript">
<?php
        $isfirst = 0;
        $aliases = "";
        $addrisfirst = 0;
        $portisfirst = 0;
        $aliasesaddr = "";
        $aliasesports = "";
        if(isset($config['aliases']['alias']) && is_array($config['aliases']['alias']))
                foreach($config['aliases']['alias'] as $alias_name) {
                        if ($alias_name['type'] == "host" || $alias_name['type'] == "network") {
				// Skip any Aliases that resolve to an empty string
				if (trim(filter_expand_alias($alias_name['name'])) == "")
					continue;
				if($addrisfirst == 1) $aliasesaddr .= ",";
				$aliasesaddr .= "'" . $alias_name['name'] . "'";
				$addrisfirst = 1;
			} else if ($alias_name['type'] == "port") {
				if($portisfirst == 1) $aliasesports .= ",";
				$aliasesports .= "'" . $alias_name['name'] . "'";
				$portisfirst = 1;
			}
                }
?>

        var addressarray=new Array(<?php echo $aliasesaddr; ?>);
        var portsarray=new Array(<?php echo $aliasesports; ?>);

function createAutoSuggest() {
<?php
	echo "objAlias = new AutoSuggestControl(document.getElementById('pscan_ignore_scanners'), new StateSuggestions(addressarray));\n";
?>
}

setTimeout("createAutoSuggest();", 500);

function frag3_enable_change() {
	if (!document.iform.frag3_detection.checked) {
		var msg = "WARNING:  Disabling the Frag3 preprocessor is not recommended!\n\n";
		msg = msg + "Snort may fail to start because of other dependent preprocessors or ";
		msg = msg + "rule options.  Are you sure you want to disable it?\n\n";
		msg = msg + "Click OK to disable Frag3, or CANCEL to quit.";
		if (!confirm(msg)) {
			document.iform.frag3_detection.checked=true;
		}
	}
	var endis = !(document.iform.frag3_detection.checked);

	// Hide the "config engines" table if Frag3 disabled
	if (endis) {
		document.getElementById("frag3_engconf_row").style.display="none";
		document.getElementById("frag3_memcap_row").style.display="none";
		document.getElementById("frag3_maxfrags_row").style.display="none";
	}
	else {
		document.getElementById("frag3_engconf_row").style.display="table-row";
		document.getElementById("frag3_memcap_row").style.display="table-row";
		document.getElementById("frag3_maxfrags_row").style.display="table-row";
	}
}

function host_attribute_table_enable_change() {
	var endis = !(document.iform.host_attribute_table.checked);

	// Hide "Host Attribute Table" config rows if HAT disabled
	if (endis) {
		document.getElementById("host_attrib_table_data_row").style.display="none";
		document.getElementById("host_attrib_table_maxhosts_row").style.display="none";
		document.getElementById("host_attrib_table_maxsvcs_row").style.display="none";
	}
	else {
		document.getElementById("host_attrib_table_data_row").style.display="table-row";
		document.getElementById("host_attrib_table_maxhosts_row").style.display="table-row";
		document.getElementById("host_attrib_table_maxsvcs_row").style.display="table-row";
	}
}

function stream5_track_tcp_enable_change() {
	var endis = !(document.iform.stream5_track_tcp.checked);

	// Hide the "tcp_memcap and tcp_engconf" rows if stream5_track_tcp disabled
	if (endis) {
		document.getElementById("stream5_maxtcp_row").style.display="none";
		document.getElementById("stream5_tcp_memcap_row").style.display="none";
		document.getElementById("stream5_tcp_engconf_row").style.display="none";
	}
	else {
		document.getElementById("stream5_maxtcp_row").style.display="table-row";
		document.getElementById("stream5_tcp_memcap_row").style.display="table-row";
		document.getElementById("stream5_tcp_engconf_row").style.display="table-row";
	}
}

function stream5_track_udp_enable_change() {
	var endis = !(document.iform.stream5_track_udp.checked);

	// Hide the "udp session timeout " row if stream5_track_udp disabled
	if (endis) {
		var msg = "WARNING:  Stream5 UDP tracking is required by the Session Initiation Protocol (SIP) preprocessor!  ";
		msg = msg + "The SIP preprocessor will be automatically disabled if Stream5 UDP tracking is disabled.\n\n";
		msg = msg + "Snort may fail to start because of rule options dependent on the SIP preprocessor.  ";
		msg = msg + "Are you sure you want to disable Stream5 UDP tracking?\n\n";
		msg = msg + "Click OK to disable Stream5 UDP tracking, or CANCEL to quit.";
		if (!confirm(msg))
			return;
		document.iform.sip_preproc.checked=false;
		document.getElementById("stream5_maxudp_row").style.display="none";
		document.getElementById("stream5_udp_sess_timeout_row").style.display="none";
	}
	else {
		document.getElementById("stream5_maxudp_row").style.display="table-row";
		document.getElementById("stream5_udp_sess_timeout_row").style.display="table-row";
	}
}

function stream5_track_icmp_enable_change() {
	var endis = !(document.iform.stream5_track_icmp.checked);

	// Hide the "icmp session timeout " row if stream5_track_icmp disabled
	if (endis) {
		document.getElementById("stream5_maxicmp_row").style.display="none";
		document.getElementById("stream5_icmp_sess_timeout_row").style.display="none";
	}
	else {
		document.getElementById("stream5_maxicmp_row").style.display="table-row";
		document.getElementById("stream5_icmp_sess_timeout_row").style.display="table-row";
	}
}

function http_inspect_enable_change() {
	var endis = !(document.iform.http_inspect.checked);
	document.iform.http_inspect_memcap.disabled=endis;

	if (!document.iform.http_inspect.checked) {
		var msg = "WARNING:  Disabling the http_inspect preprocessor is not recommended!\n\n";
		msg = msg + "Snort may fail to start because of other dependent preprocessors or ";
		msg = msg + "rule options.  Are you sure you want to disable it?\n\n";
		msg = msg + "Click OK to disable http_inspect, or CANCEL to quit.";
		if (!confirm(msg)) {
			document.iform.http_inspect.checked=true;
		}
		else {
			document.getElementById("httpinspect_memcap_row").style.display="none";
			document.getElementById("httpinspect_maxgzipmem_row").style.display="none";
			document.getElementById("httpinspect_proxyalert_row").style.display="none";
			document.getElementById("httpinspect_engconf_row").style.display="none";
		}
	}
	else {
		document.getElementById("httpinspect_memcap_row").style.display="table-row";
		document.getElementById("httpinspect_maxgzipmem_row").style.display="table-row";
		document.getElementById("httpinspect_proxyalert_row").style.display="table-row";
		document.getElementById("httpinspect_engconf_row").style.display="table-row";
	}
}

function sf_portscan_enable_change() {
	var endis = !(document.iform.sf_portscan.checked);

	// Hide the portscan configuration rows if sf_portscan disabled
	if (endis) {
		document.getElementById("portscan_protocol_row").style.display="none";
		document.getElementById("portscan_type_row").style.display="none";
		document.getElementById("portscan_sensitivity_row").style.display="none";
		document.getElementById("portscan_memcap_row").style.display="none";
		document.getElementById("portscan_ignorescanners_row").style.display="none";
	}
	else {
		document.getElementById("portscan_protocol_row").style.display="table-row";
		document.getElementById("portscan_type_row").style.display="table-row";
		document.getElementById("portscan_sensitivity_row").style.display="table-row";
		document.getElementById("portscan_memcap_row").style.display="table-row";
		document.getElementById("portscan_ignorescanners_row").style.display="table-row";
	}
}

function appid_preproc_enable_change() {
	var endis = !(document.iform.appid_preproc.checked);

	// Hide the AppID configuration rows if appid_preproc disabled
	if (endis)
		document.getElementById("appid_rows").style.display="none";
	else
		document.getElementById("appid_rows").style.display="";
}

function stream5_enable_change() {
	if (!document.iform.stream5_reassembly.checked) {
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
			document.iform.stream5_reassembly.checked=true;
		}
		else {
			alert("If Snort fails to start with Stream5 disabled, examine the system log for clues.");
			document.iform.smtp_preprocessor.checked=false;
			document.iform.dce_rpc_2.checked=false;
			document.iform.sip_preproc.checked=false;
			document.iform.sensitive_data.checked=false;
			document.iform.imap_preproc.checked=false;
			document.iform.pop_preproc.checked=false;
			document.iform.ssl_preproc.checked=false;
			document.iform.dns_preprocessor.checked=false;
			document.iform.modbus_preproc.checked=false;
			document.iform.dnp3_preproc.checked=false;
			document.iform.appid_preproc.checked=false;
			document.iform.sf_portscan.checked=false;
			sf_portscan_enable_change();
		}
	}

	var endis = !(document.iform.stream5_reassembly.checked);

	// Hide the "stream5 conf" rows if stream5 disabled
	if (endis) {
		document.getElementById("stream5_tcp_memcap_row").style.display="none";
		document.getElementById("stream5_tcp_engconf_row").style.display="none";
		document.getElementById("stream5_udp_sess_timeout_row").style.display="none";
		document.getElementById("stream5_icmp_sess_timeout_row").style.display="none";
		document.getElementById("stream5_proto_tracking_row").style.display="none";
		document.getElementById("stream5_flushonalert_row").style.display="none";
		document.getElementById("stream5_prunelogmax_row").style.display="none";
	}
	else {
		document.getElementById("stream5_tcp_memcap_row").style.display="table-row";
		document.getElementById("stream5_tcp_engconf_row").style.display="table-row";
		document.getElementById("stream5_udp_sess_timeout_row").style.display="table-row";
		document.getElementById("stream5_icmp_sess_timeout_row").style.display="table-row";
		document.getElementById("stream5_proto_tracking_row").style.display="table-row";
		document.getElementById("stream5_flushonalert_row").style.display="table-row";
		document.getElementById("stream5_prunelogmax_row").style.display="table-row";
	}
}

function ftp_telnet_enable_change() {
	var endis = !(document.iform.ftp_preprocessor.checked);

	// Hide the ftp_telnet configuration rows if ftp_telnet disabled
	if (endis) {
		document.getElementById("ftp_telnet_row_type").style.display="none";
		document.getElementById("ftp_telnet_row_encrypted_alert").style.display="none";
		document.getElementById("ftp_telnet_row_encrypted_check").style.display="none";
		document.getElementById("ftp_telnet_row_telnet_proto_opts").style.display="none";
		document.getElementById("ftp_telnet_row_normalize").style.display="none";
		document.getElementById("ftp_telnet_row_detect_anomalies").style.display="none";
		document.getElementById("ftp_telnet_row_ayt_threshold").style.display="none";
		document.getElementById("ftp_telnet_row_ftp_proto_opts").style.display="none";
		document.getElementById("ftp_telnet_ftp_client_row").style.display="none";
		document.getElementById("ftp_telnet_ftp_server_row").style.display="none";
	}
	else {
		document.getElementById("ftp_telnet_row_type").style.display="table-row";
		document.getElementById("ftp_telnet_row_encrypted_alert").style.display="table-row";
		document.getElementById("ftp_telnet_row_encrypted_check").style.display="table-row";
		document.getElementById("ftp_telnet_row_telnet_proto_opts").style.display="table-row";
		document.getElementById("ftp_telnet_row_normalize").style.display="table-row";
		document.getElementById("ftp_telnet_row_detect_anomalies").style.display="table-row";
		document.getElementById("ftp_telnet_row_ayt_threshold").style.display="table-row";
		document.getElementById("ftp_telnet_row_ftp_proto_opts").style.display="table-row";
		document.getElementById("ftp_telnet_ftp_client_row").style.display="table-row";
		document.getElementById("ftp_telnet_ftp_server_row").style.display="table-row";
	}
}

function sensitive_data_enable_change() {
	var endis = !(document.iform.sensitive_data.checked);

	// Hide the sensitive_data configuration rows if sensitive_data disabled
	if (endis) {
		document.getElementById("sdf_alert_threshold_row").style.display="none";
		document.getElementById("sdf_mask_output_row").style.display="none";
		document.getElementById("sdf_alert_data_row").style.display="none";
		
	}
	else {
		document.getElementById("sdf_alert_threshold_row").style.display="table-row";
		document.getElementById("sdf_mask_output_row").style.display="table-row";
		document.getElementById("sdf_alert_data_row").style.display="table-row";
	}
}

function pop_enable_change() {
	var endis = !(document.iform.pop_preproc.checked);

	// Hide POP3 configuration rows if POP preprocessor disabled
	if (endis)
		document.getElementById("pop_setting_rows").style.display = "none";
	else
		document.getElementById("pop_setting_rows").style.display = "";
}

function imap_enable_change() {
	var endis = !(document.iform.imap_preproc.checked);

	// Hide IMAP configuration rows if IMAP preprocessor disabled
	if (endis)
		document.getElementById("imap_setting_rows").style.display = "none";
	else
		document.getElementById("imap_setting_rows").style.display = "";
}

function smtp_enable_change() {
	var endis = !(document.iform.smtp_preprocessor.checked);

	// Hide SMTP configuration rows if SMTP preprocessor disabled
	if (endis)
		document.getElementById("smtp_setting_rows").style.display = "none";
	else
		document.getElementById("smtp_setting_rows").style.display = "";
}

function enable_change_all() {
	http_inspect_enable_change();
	sf_portscan_enable_change();
	appid_preproc_enable_change();

	// -- Enable/Disable Host Attribute Table settings --
	host_attribute_table_enable_change();

	// -- Enable/Disable Frag3 settings --
	var endis = !(document.iform.frag3_detection.checked);
	// Hide the "config engines" table if Frag3 disabled
	if (endis) {
		document.getElementById("frag3_engconf_row").style.display="none";
		document.getElementById("frag3_memcap_row").style.display="none";
		document.getElementById("frag3_maxfrags_row").style.display="none";
	}
	else {
		document.getElementById("frag3_engconf_row").style.display="table-row";
		document.getElementById("frag3_memcap_row").style.display="table-row";
		document.getElementById("frag3_maxfrags_row").style.display="table-row";
	}

	// -- Enable/Disable Stream5 settings --
	endis = !(document.iform.stream5_reassembly.checked);
	// Hide the "stream5 conf" rows if stream5 disabled
	if (endis) {
		document.getElementById("stream5_tcp_memcap_row").style.display="none";
		document.getElementById("stream5_tcp_engconf_row").style.display="none";
		document.getElementById("stream5_udp_sess_timeout_row").style.display="none";
		document.getElementById("stream5_icmp_sess_timeout_row").style.display="none";
		document.getElementById("stream5_proto_tracking_row").style.display="none";
		document.getElementById("stream5_flushonalert_row").style.display="none";
		document.getElementById("stream5_prunelogmax_row").style.display="none";
		document.getElementById("stream5_maxtcp_row").style.display="none";
		document.getElementById("stream5_maxudp_row").style.display="none";
		document.getElementById("stream5_maxicmp_row").style.display="none";
	}
	else {
		document.getElementById("stream5_tcp_memcap_row").style.display="table-row";
		document.getElementById("stream5_tcp_engconf_row").style.display="table-row";
		document.getElementById("stream5_udp_sess_timeout_row").style.display="table-row";
		document.getElementById("stream5_icmp_sess_timeout_row").style.display="table-row";
		document.getElementById("stream5_proto_tracking_row").style.display="table-row";
		document.getElementById("stream5_flushonalert_row").style.display="table-row";
		document.getElementById("stream5_prunelogmax_row").style.display="table-row";
		document.getElementById("stream5_maxtcp_row").style.display="table-row";
		document.getElementById("stream5_maxudp_row").style.display="table-row";
		document.getElementById("stream5_maxicmp_row").style.display="table-row";
	}
	// Set other stream5 initial conditions
	stream5_track_tcp_enable_change();
	stream5_track_udp_enable_change();
	stream5_track_icmp_enable_change();
	ftp_telnet_enable_change();
	sensitive_data_enable_change();
	pop_enable_change();
	imap_enable_change();
	smtp_enable_change();
}

function wopen(url, name, w, h)
{
// Fudge factors for window decoration space.
// In my tests these work well on all platforms & browsers.
    w += 32;
    h += 96;
    var win = window.open(url,
        name, 
       'width=' + w + ', height=' + h + ', ' +
       'location=no, menubar=no, ' +
       'status=no, toolbar=no, scrollbars=yes, resizable=yes');
    win.resizeTo(w, h);
    win.focus();
}

function selectAlias() {

	var loc;
	var fields = [ "sf_portscan", "pscan_protocol", "pscan_type", "pscan_sense_level", "pscan_memcap", "pscan_ignore_scanners" ];

	// Scrape current form field values and add to
	// the select alias URL as a query string.
	var loc = 'snort_select_alias.php?id=<?=$id;?>&act=import&type=host|network';
	loc = loc + '&varname=pscan_ignore_scanners&multi_ip=yes';
	loc = loc + '&returl=<?=urlencode($_SERVER['PHP_SELF']);?>';
	loc = loc + '&uuid=<?=$passlist_uuid;?>';

	// Iterate over just the specific form fields we want to pass to
	// the select alias URL.
	fields.forEach(function(entry) {
		var tmp = $(entry).serialize();
		if (tmp.length > 0)
			loc = loc + '&' + tmp;
	});
	
	window.parent.location = loc; 
}

// Set initial state of form controls
enable_change_all();

</script>
<?php include("fend.inc"); ?>
</body>
</html>
