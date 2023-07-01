<?php
/*
 * snort_interfaces_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2008-2009 Robert Zelaya
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

global $g, $rebuild_rules;

$snortdir = SNORTDIR;
$snortlogdir = SNORTLOGDIR;

$netmapifs = array('cc', 'cxl', 'cxgbe', 'em', 'igb', 'em', 'lem', 'ix', 'ixgbe', 'ixl', 're', 'vtnet');
if (pfs_version_compare(false, 2.4, $g['product_version'])) {
	/* add FreeBSD 12 iflib(4) supported devices */
	$netmapifs = array_merge($netmapifs, array('ena', 'ice', 'igc', 'bnxt', 'vmx'));
	sort($netmapifs);
}

$a_rule = config_get_path('installedpackages/snortglobal/rule', []);

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
        header("Location: /snort/snort_interfaces.php");
        exit;
}

// Load the specified VIEW LIST if requested
if ($_REQUEST['ajax']) {

	$wlist = $_REQUEST['list'];
	$type = $_REQUEST['type'];

	if (isset($id) && isset($wlist)) {
		$rule = config_get_path("installedpackages/snortglobal/rule/{$id}", []);
		if ($rule && $type == "homenet") {
			$list = snort_build_list($rule, empty($wlist) ? 'default' : $wlist);
			$contents = implode("\n", $list);
		}
		elseif ($rule && $type == "passlist") {
			$list = snort_build_list($rule, $wlist, true);
			$contents = implode("\n", $list);
		}
		elseif ($rule && $type == "suppress") {
			$list = snort_find_list($wlist, $type);
			if (!empty($list)) {
				$contents = str_replace("\r", "", base64_decode($list['suppresspassthru']));
			}
			else {
				$contents = gettext("The Suppress List is empty.");
			}
		}
		elseif ($rule && $type == "externalnet") {
			if (empty($wlist) || $wlist == "default") {
				$list = snort_build_list($rule, $rule['homelistname']);
				$contents = "";
				foreach ($list as $ip)
					$contents .= "!{$ip}\n";
				$contents = trim($contents, "\n");
			}
			else {
				$list = snort_build_list($rule, $wlist, false, true);
				$contents = implode("\n", $list);
			}
		}
		else
			$contents = gettext("\n\nERROR -- Requested List Type entity is not valid!");
	}
	else
		$contents = gettext("\n\nERROR -- Supplied interface or List entity is not valid!");

	print($contents);
	exit;
}

if (isset($_POST['action']))
	$action = htmlspecialchars($_POST['action'], ENT_QUOTES | ENT_HTML401);
elseif (isset($_GET['action']))
	$action = htmlspecialchars($_GET['action'], ENT_QUOTES | ENT_HTML401);
else
	$action = "";

$pconfig = array();
if (!config_path_enabled('installedpackages/snortglobal/rule', $id)) {
	/* Adding a new interface, so generate new UUID and flag rules to rebuild. */
	$pconfig['uuid'] = snort_generate_id();
	$rebuild_rules = true;
	$new_interface = true;
}
else {
	$pconfig['uuid'] = $a_rule[$id]['uuid'];
	$pconfig['descr'] = $a_rule[$id]['descr'];
	$rebuild_rules = false;
	$new_interface = false;
}
$snort_uuid = $pconfig['uuid'];

// Get the physical configured interfaces on the firewall
$interfaces = get_configured_interface_with_descr();

// Footnote real interface associated with each configured interface
foreach ($interfaces as $if => $desc) {
	$interfaces[$if] = $interfaces[$if] . " (" . get_real_interface($if) . ")";
}

// Add a special "Unassigned" interface selection at end of list
$interfaces["Unassigned"] = gettext("Unassigned");

// See if interface is already configured, and use its values
if (isset($id) && $a_rule[$id]) {
	/* old options */
	$if_friendly = convert_friendly_interface_to_friendly_descr($a_rule[$id]['interface']);
	$pconfig = $a_rule[$id];
	if (empty($pconfig['uuid']))
		$pconfig['uuid'] = $snort_uuid;
	if (get_real_interface($pconfig['interface']) == "") {
		$pconfig['interface'] = gettext("Unassigned");
		$pconfig['enable'] = "off";
	}
}
// Must be a new interface, so try to pick next available physical interface to use
elseif (isset($id) && !isset($a_rule[$id])) {
	$ifaces = get_configured_interface_list();
	$ifrules = array();
	foreach($a_rule as $r)
		$ifrules[] = $r['interface'];
	foreach ($ifaces as $i) {
		if (!in_array($i, $ifrules)) {
			$pconfig['interface'] = $i;
			$if_friendly = convert_friendly_interface_to_friendly_descr($i);

			// If the interface is a VLAN, use the VLAN description
			// if set, otherwise default to the friendly description.
			if ($vlan = interface_is_vlan(get_real_interface($i))) {
				if (strlen($vlan['descr']) > 0) {
					$pconfig['descr'] = $vlan['descr'];
				}
				else {
					$pconfig['descr'] = convert_friendly_interface_to_friendly_descr($i);
				}
			}
			else {
				$pconfig['descr'] = convert_friendly_interface_to_friendly_descr($i);
			}
			$pconfig['enable'] = 'on';
			break;
		}
	}
	if (count($ifrules) == count($ifaces)) {
		$input_errors[] = "No more available interfaces to configure for Snort!";
		$interfaces = array();
		$pconfig = array();
	}
}

// Set defaults for empty key parameters
if (empty($pconfig['enable_pkt_caps']))
	$pconfig['enable_pkt_caps'] = "off";
if (empty($pconfig['tcpdump_file_size']))
	$pconfig['tcpdump_file_size'] = "128";
if (empty($pconfig['blockoffendersip']))
	$pconfig['blockoffendersip'] = "both";
if (empty($pconfig['blockoffenderskill']))
	$pconfig['blockoffenderskill'] = "on";
if (empty($pconfig['performance']))
	$pconfig['performance'] = "ac-bnfa";
if (empty($pconfig['alertsystemlog_facility']))
	$pconfig['alertsystemlog_facility'] = "log_auth";
if (empty($pconfig['alertsystemlog_priority']))
	$pconfig['alertsystemlog_priority'] = "log_alert";
if (empty($pconfig['snaplen']))
	$pconfig['snaplen'] = 1518;
if (empty($pconfig['ips_mode']))
	$pconfig['ips_mode'] = 'ips_mode_legacy';

// See if creating a new interface by duplicating an existing one
if (strcasecmp($action, 'dup') == 0) {

	// Try to pick the next available physical interface to use
	$ifaces = get_configured_interface_list();
	$ifrules = array();
	foreach($a_rule as $r)
		$ifrules[] = $r['interface'];
	foreach ($ifaces as $i) {
		if (!in_array($i, $ifrules)) {
			$pconfig['interface'] = $i;
			$pconfig['enable'] = 'on';

			// If the interface is a VLAN, use the VLAN description
			// if set, otherwise default to the friendly description.
			if ($vlan = interface_is_vlan(get_real_interface($i))) {
				if (strlen($vlan['descr']) > 0) {
					$pconfig['descr'] = $vlan['descr'];
				}
				else {
					$pconfig['descr'] = convert_friendly_interface_to_friendly_descr($i);
				}
			}
			else {
				$pconfig['descr'] = convert_friendly_interface_to_friendly_descr($i);
			}
			break;
		}
	}
	if (count($ifrules) == count($ifaces)) {
		$input_errors[] = gettext("No more available interfaces to configure for Snort!");
		$interfaces = array();
		$pconfig = array();
	}

	// Set Home Net, External Net, Suppress List and Pass List to defaults
	unset($pconfig['suppresslistname']);
	unset($pconfig['whitelistname']);
	unset($pconfig['homelistname']);
	unset($pconfig['externallistname']);
}

if ($_POST['save'] && !$input_errors) {
	$snort_start = $snort_restart = $snort_reload = $snort_new_passlist = FALSE;
	if (!isset($_POST['interface']))
		$input_errors[] = "Interface is mandatory";

	/* See if assigned interface is already in use */
	if (isset($_POST['interface'])) {
		foreach ($a_rule as $k => $v) {
			if (($v['interface'] == $_POST['interface']) && ($id <> $k)) {
				$input_errors[] = gettext("The '{$_POST['interface']}' interface is already assigned to another Snort instance.");
				break;
			}
		}
	}

	if ($_POST['ips_mode'] == 'ips_mode_inline') {
		$is_netmap = false;
		$realint = get_real_interface($_POST['interface']);
		foreach ($netmapifs as $if) {
			if (substr($realint, 0, strlen($if)) == $if) {
				$is_netmap = true;
				break;
			}
		}
		if (!$is_netmap) {
			$input_errors[] = gettext("The '{$_POST['interface']}' interface do not support Inline Mode.");
		}
	}

	// If Snort is now disabled on this interface, stop any running instance
	// on an active interface, save the change, and exit.
	if ($_POST['enable'] != 'on' && config_path_enabled('installedpackages/snortglobal/rule', $id)) {
		$a_rule[$id]['enable'] = $_POST['enable'] ? 'on' : 'off';
		touch("{$g['varrun_path']}/snort_{$a_rule[$id]['uuid']}.disabled");
		snort_stop($a_rule[$id], get_real_interface($a_rule[$id]['interface']));
		config_set_path('installedpackages/snortglobal/rule', $a_rule);
		write_config("Snort pkg: modified interface configuration for {$a_rule[$id]['interface']}.");
		$rebuild_rules = false;
		sync_snort_package_config();
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_interfaces.php");
		exit;
	}

	/* if no errors, generate and save the interface configuration */
	if (!$input_errors) {
		/* Most changes don't require a rules rebuild, so default to "off" */
		$rebuild_rules = FALSE;
		$natent = array();

		// Grab the existing configuration for modifications if it exists
		if (config_path_enabled('installedpackages/snortglobal/rule', $id)) {
			$natent = $a_rule[$id];
		}

		$natent['interface'] = $_POST['interface'];
		$natent['enable'] = $_POST['enable'] ? 'on' : 'off';
		$natent['uuid'] = $pconfig['uuid'];

		/* See if the HOME_NET, EXTERNAL_NET, or SUPPRESS LIST were changed and flag a reload if "yes" */
		if ($_POST['homelistname'] && ($_POST['homelistname'] <> $natent['homelistname']))
			$snort_reload = true;
		if ($_POST['externallistname'] && ($_POST['externallistname'] <> $natent['externallistname']))
			$snort_reload = true;
		if ($_POST['suppresslistname'] && ($_POST['suppresslistname'] <> $natent['suppresslistname']))
			$snort_reload = true;

		/* See if the Pass List was changed and flag a Snort restart if "yes" */
		if ($_POST['whitelistname'] && ($_POST['whitelistname'] <> $natent['whitelistname']))
			$snort_new_passlist = true;

		/* See if any Blocking settings changed and flag a restart if "yes" */
		if ($_POST['blockoffenders7'] && ($_POST['blockoffenders7'] <> $natent['blockoffenders7']))
			$snort_restart = true;
		if ($_POST['blockoffenderskill'] && ($_POST['blockoffenderskill'] <> $natent['blockoffenderskill']))
			$snort_restart = true;
		if ($_POST['blockoffendersip'] && ($_POST['blockoffendersip'] <> $natent['blockoffendersip']))
			$snort_restart = true;
		if ($_POST['ips_mode'] && ($_POST['ips_mode'] <> $natent['ips_mode']))
			$snort_restart = true;

		if ($_POST['descr']) $natent['descr'] =  $_POST['descr']; else $natent['descr'] = convert_friendly_interface_to_friendly_descr($natent['interface']);
		if ($_POST['performance']) $natent['performance'] = $_POST['performance']; else  unset($natent['performance']);
		if ($_POST['snaplen'] && is_numeric($_POST['snaplen'])) $natent['snaplen'] = $_POST['snaplen'];
		if ($_POST['ips_mode']) $natent['ips_mode'] = $_POST['ips_mode']; else unset($natent['ips_mode']);
		if ($_POST['enable_pkt_caps'] == "on") $natent['enable_pkt_caps'] = 'on'; else $natent['enable_pkt_caps'] = 'off';
		if ($_POST['tcpdump_file_size'] && is_numeric($_POST['tcpdump_file_size'])) $natent['tcpdump_file_size'] = $_POST['tcpdump_file_size'];
		if ($_POST['unified2_logging_enable'] == "on") $natent['unified2_logging_enable'] = 'on'; else $natent['unified2_logging_enable'] = 'off';
		if ($_POST['unified2_log_vlan_events'] == "on") $natent['unified2_log_vlan_events'] = 'on'; else $natent['unified2_log_vlan_events'] = 'off';
		if ($_POST['unified2_log_mpls_events'] == "on") $natent['unified2_log_mpls_events'] = 'on'; else $natent['unified2_log_mpls_events'] = 'off';
		if ($_POST['blockoffenders7'] == "on") $natent['blockoffenders7'] = 'on'; else $natent['blockoffenders7'] = 'off';
		if ($_POST['blockoffenderskill'] == "on") $natent['blockoffenderskill'] = 'on'; else $natent['blockoffenderskill'] = 'off';
		if ($_POST['blockoffendersip']) $natent['blockoffendersip'] = $_POST['blockoffendersip']; else unset($natent['blockoffendersip']);
		if ($_POST['whitelistname']) $natent['whitelistname'] =  $_POST['whitelistname']; else unset($natent['whitelistname']);
		if ($_POST['homelistname']) $natent['homelistname'] =  $_POST['homelistname']; else unset($natent['homelistname']);
		if ($_POST['alert_log_limit']) $natent['alert_log_limit'] =  $_POST['alert_log_limit']; else unset($natent['alert_log_limit']);
		if ($_POST['alert_log_retention']) $natent['alert_log_retention'] =  $_POST['alert_log_retention']; else unset($natent['alert_log_retention']);
		if ($_POST['externallistname']) $natent['externallistname'] =  $_POST['externallistname']; else unset($natent['externallistname']);
		if ($_POST['suppresslistname']) $natent['suppresslistname'] =  $_POST['suppresslistname']; else unset($natent['suppresslistname']);
		if ($_POST['alertsystemlog'] == "on") { $natent['alertsystemlog'] = 'on'; }else{ $natent['alertsystemlog'] = 'off'; }
		if ($_POST['alertsystemlog_facility']) $natent['alertsystemlog_facility'] = $_POST['alertsystemlog_facility'];
		if ($_POST['alertsystemlog_priority']) $natent['alertsystemlog_priority'] = $_POST['alertsystemlog_priority'];
		if ($_POST['configpassthru']) $natent['configpassthru'] = base64_encode(str_replace("\r\n", "\n", $_POST['configpassthru'])); else unset($natent['configpassthru']);
		if ($_POST['cksumcheck']) $natent['cksumcheck'] = 'on'; else $natent['cksumcheck'] = 'off';
		if ($_POST['fpm_split_any_any'] == "on") { $natent['fpm_split_any_any'] = 'on'; }else{ $natent['fpm_split_any_any'] = 'off'; }
		if ($_POST['fpm_search_optimize'] == "on") { $natent['fpm_search_optimize'] = 'on'; }else{ $natent['fpm_search_optimize'] = 'off'; }
		if ($_POST['fpm_no_stream_inserts'] == "on") { $natent['fpm_no_stream_inserts'] = 'on'; }else{ $natent['fpm_no_stream_inserts'] = 'off'; }

		$if_real = get_real_interface($natent['interface']);
		if (isset($id) && $a_rule[$id] && $action == '') {
			// See if moving an existing Snort instance to another physical interface
			if ($natent['interface'] != $a_rule[$id]['interface']) {
				$oif_real = get_real_interface($a_rule[$id]['interface']);
				if (snort_is_running($a_rule[$id]['uuid'])) {
					snort_stop($a_rule[$id], $oif_real);
					$snort_start = true;
				}
				else
					$snort_start = false;
				@rename("{$snortlogdir}/snort_{$oif_real}{$a_rule[$id]['uuid']}", "{$snortlogdir}/snort_{$if_real}{$a_rule[$id]['uuid']}");
				@rename("{$snortdir}/snort_{$a_rule[$id]['uuid']}_{$oif_real}", "{$snortdir}/snort_{$a_rule[$id]['uuid']}_{$if_real}");
			}
			$a_rule[$id] = $natent;
		}
		elseif (strcasecmp($action, 'dup') == 0) {
			// Duplicating a new interface, so set flag to build new rules
			$rebuild_rules = true;

			// Duplicating an interface, so need to generate a new UUID for the cloned interface
			$natent['uuid'] = snort_generate_id();

			// Add the new duplicated interface configuration to the [rule] array in config
			$a_rule[] = $natent;
		}
		else {
			// Adding new interface, so set required interface configuration defaults
			$frag3_eng = array( "name" => "default", "bind_to" => "all", "policy" => "bsd", 
					    "timeout" => 60, "min_ttl" => 1, "detect_anomalies" => "on", 
					    "overlap_limit" => 0, "min_frag_len" => 0 );

			$stream5_eng = array( "name" => "default", "bind_to" => "all", "policy" => "bsd", "timeout" => 30, 
					      "max_queued_bytes" => 1048576, "detect_anomalies" => "off", "overlap_limit" => 0, 
					      "max_queued_segs" => 2621, "require_3whs" => "off", "startup_3whs_timeout" => 0, 
					      "no_reassemble_async" => "off", "max_window" => 0, "use_static_footprint_sizes" => "off", 
					      "check_session_hijacking" => "off", "dont_store_lg_pkts" => "off", "ports_client" => "default", 
					      "ports_both" => "default", "ports_server" => "none" );

			$http_eng = array( "name" => "default", "bind_to" => "all", "server_profile" => "all", "enable_xff" => "off", 
					   "log_uri" => "off", "log_hostname" => "off", "server_flow_depth" => 65535, "enable_cookie" => "on", 
					   "client_flow_depth" => 1460, "extended_response_inspection" => "on", "no_alerts" => "off", 
					   "unlimited_decompress" => "on", "inspect_gzip" => "on", "normalize_cookies" =>"on", 
					   "normalize_headers" => "on", "normalize_utf" => "on", "normalize_javascript" => "on", 
					   "allow_proxy_use" => "off", "inspect_uri_only" => "off", "max_javascript_whitespaces" => 200,
					   "post_depth" => -1, "max_headers" => 0, "max_spaces" => 0, "max_header_length" => 0, "ports" => "default",
					   "decompress_swf" => "off", "decompress_pdf" => "off" );

			$ftp_client_eng = array( "name" => "default", "bind_to" => "all", "max_resp_len" => 256, 
						 "telnet_cmds" => "no", "ignore_telnet_erase_cmds" => "yes", 
						 "bounce" => "yes", "bounce_to_net" => "", "bounce_to_port" => "" );

			$ftp_server_eng = array( "name" => "default", "bind_to" => "all", "ports" => "default", 
						 "telnet_cmds" => "no", "ignore_telnet_erase_cmds" => "yes", 
						 "ignore_data_chan" => "no", "def_max_param_len" => 100 );

			$natent['max_attribute_hosts'] = '10000';
			$natent['max_attribute_services_per_host'] = '10';
			$natent['max_paf'] = '16000';

			$natent['ftp_preprocessor'] = 'on';
			$natent['ftp_telnet_inspection_type'] = "stateful";
			$natent['ftp_telnet_alert_encrypted'] = "off";
			$natent['ftp_telnet_check_encrypted'] = "on";
			$natent['ftp_telnet_normalize'] = "on";
			$natent['ftp_telnet_detect_anomalies'] = "on";
			$natent['ftp_telnet_ayt_attack_threshold'] = "20";
			if (!is_array($natent['ftp_client_engine']['item']))
				$natent['ftp_client_engine']['item'] = array();
			$natent['ftp_client_engine']['item'][] = $ftp_client_eng;
			if (!is_array($natent['ftp_server_engine']['item']))
				$natent['ftp_server_engine']['item'] = array();
			$natent['ftp_server_engine']['item'][] = $ftp_server_eng;

			$natent['smtp_preprocessor'] = 'on';
			$natent['smtp_memcap'] = "838860";
			$natent['smtp_max_mime_mem'] = "838860";
			$natent['smtp_b64_decode_depth'] = "0";
			$natent['smtp_qp_decode_depth'] = "0";
			$natent['smtp_bitenc_decode_depth'] = "0";
			$natent['smtp_uu_decode_depth'] = "0";
			$natent['smtp_email_hdrs_log_depth'] = "1464";
			$natent['smtp_ignore_data'] = 'off';
			$natent['smtp_ignore_tls_data'] = 'on';
			$natent['smtp_log_mail_from'] = 'on';
			$natent['smtp_log_rcpt_to'] = 'on';
			$natent['smtp_log_filename'] = 'on';
			$natent['smtp_log_email_hdrs'] = 'on';

			$natent['dce_rpc_2'] = 'on';
			$natent['dns_preprocessor'] = 'on';
			$natent['ssl_preproc'] = 'on';
			$natent['pop_preproc'] = 'on';
			$natent['pop_memcap'] = "838860";
			$natent['pop_b64_decode_depth'] = "0";
			$natent['pop_qp_decode_depth'] = "0";
			$natent['pop_bitenc_decode_depth'] = "0";
			$natent['pop_uu_decode_depth'] = "0";
			$natent['imap_preproc'] = 'on';
			$natent['imap_memcap'] = "838860";
			$natent['imap_b64_decode_depth'] = "0";
			$natent['imap_qp_decode_depth'] = "0";
			$natent['imap_bitenc_decode_depth'] = "0";
			$natent['imap_uu_decode_depth'] = "0";
			$natent['sip_preproc'] = 'on';
			$natent['other_preprocs'] = 'on';

			$natent['pscan_protocol'] = 'all';
			$natent['pscan_type'] = 'all';
			$natent['pscan_memcap'] = '10000000';
			$natent['pscan_sense_level'] = 'medium';

			$natent['http_inspect'] = "on";
			$natent['http_inspect_proxy_alert'] = "off";
			$natent['http_inspect_memcap'] = "150994944";
			$natent['http_inspect_max_gzip_mem'] = "838860";
			if (!is_array($natent['http_inspect_engine']['item']))
				$natent['http_inspect_engine']['item'] = array();
			$natent['http_inspect_engine']['item'][] = $http_eng;

			$natent['frag3_max_frags'] = '8192';
			$natent['frag3_memcap'] = '4194304';
			$natent['frag3_detection'] = 'on';
			if (!is_array($natent['frag3_engine']['item']))
				$natent['frag3_engine']['item'] = array();
			$natent['frag3_engine']['item'][] = $frag3_eng;

			$natent['stream5_reassembly'] = 'on';
			$natent['stream5_flush_on_alert'] = 'off';
			$natent['stream5_prune_log_max'] = '1048576';
			$natent['stream5_track_tcp'] = 'on';
			$natent['stream5_max_tcp'] = '262144';
			$natent['stream5_track_udp'] = 'on';
			$natent['stream5_max_udp'] = '131072';
			$natent['stream5_udp_timeout'] = '30';
			$natent['stream5_track_icmp'] = 'off';
			$natent['stream5_max_icmp'] = '65536';
			$natent['stream5_icmp_timeout'] = '30';
			$natent['stream5_mem_cap']= '8388608';
			if (!is_array($natent['stream5_tcp_engine']['item']))
				$natent['stream5_tcp_engine']['item'] = array();
			$natent['stream5_tcp_engine']['item'][] = $stream5_eng;

			$natent['alertsystemlog_facility'] = "log_auth";
			$natent['alertsystemlog_priority'] = "log_alert";

			$natent['appid_preproc'] = "off";
			$natent['sf_appid_mem_cap'] = "256";
			$natent['sf_appid_statslog'] = "on";
			$natent['sf_appid_stats_period'] = "300";

			$natent['ssh_preproc_ports'] = '22';
			$natent['ssh_preproc_max_encrypted_packets'] = 20;
			$natent['ssh_preproc_max_client_bytes'] = 19600;
			$natent['ssh_preproc_max_server_version_len'] = 100;
			$natent['ssh_preproc_enable_respoverflow'] = 'on';
			$natent['ssh_preproc_enable_srvoverflow'] = 'on';
			$natent['ssh_preproc_enable_ssh1crc32'] = 'on';
			$natent['ssh_preproc_enable_protomismatch'] = 'on';

			$natent['autoflowbitrules'] = 'on';

			$a_rule[] = $natent;
		}

		/* If Snort is disabled on this interface, stop any running instance */
		if ($natent['enable'] != 'on')
			snort_stop($natent, $if_real);

		/* Save configuration changes */
		config_set_path('installedpackages/snortglobal/rule', $a_rule);
		write_config("Snort pkg: modified interface configuration for {$natent['interface']}.");

		/* Update snort.conf and snort.sh files for this interface */
		sync_snort_package_config();

		/* See if we need to restart Snort after an interface re-assignment */
		if ($snort_start) {
			snort_start($natent, $if_real, TRUE);
			$savemsg = gettext('Starting Snort on interface due to interface re-assignment.');
		}

		/* See if we need to restart Snort to activate changes made */
		if ($snort_restart && !$snort_start && snort_is_running($natent['uuid'])) {
			snort_start($natent, $if_real, TRUE);
			$savemsg = gettext('Restarted Snort on this interface to activate new saved configuration settings.');
		}

		/* See if we need to restart Snort after a Pass List re-assignment, */
		/* but only if we did not restart for any other reason.             */
		if ($snort_new_passlist && !$snort_start && !$snort_restart && snort_is_running($natent['uuid'])) {
			snort_start($natent, $if_real, TRUE);
			$savemsg = gettext('Restarted Snort on this interface due to a Pass List change.');
		}

		/*******************************************************/
		/* Signal Snort to reload configuration if we changed  */
		/* HOME_NET, EXTERNAL_NET or Suppress list values      */
		/* unless we already restarted Snort earlier.  The     */
		/* function only signals a running Snort instance to   */
		/* safely reload these parameters.                     */
		/*******************************************************/
		if ($snort_reload && !$snort_start && !$snort_restart && snort_is_running($natent['uuid'])) {
			snort_reload_config($natent, "SIGHUP");
			$savemsg = gettext('Signaled Snort to reload configuration due to change in HOME_NET, EXTERNAL_NET or Suppress List assignments.');
		}

		/* If using Inline IPS and the current interface is a VLAN, advise  */
		/* advise user that VLAN Hardware Filtering should be disabled.     */
		if ($natent['enable'] == 'on' && $natent['ips_mode'] == 'ips_mode_inline' && interface_is_vlan(get_real_interface($natent['interface']))) {
			$vlan_warn_msg = gettext('NOTICE:  When using Inline IPS Mode with VLAN interfaces, hardware-level VLAN filtering should be disabled with most network cards. Follow the steps in the Netgate documentation ') . 
					 '<a target="_blank" href="https://docs.netgate.com/pfsense/en/latest/hardware/tuning-and-troubleshooting-network-cards.html#intel-ix-4-cards">' . gettext('here') . 
					 '</a>' . gettext(' to disable hardware VLAN filtering.');
		}

		$pconfig = $natent;
		$new_interface = false;
	} else
		$pconfig = $_POST;
}

function snort_get_config_lists($lists) {

	// This returns the array of lists identified by $lists
	// stored in the config file if one exists.  Always
	// return at least the single entry, "default".
	$result = array();
	$result['default'] = gettext("default");
	foreach (config_get_path("installedpackages/snortglobal/{$lists}/item", []) as $v) {
		$result[$v['name']] = gettext($v['name']);
	}
	return $result;
}

$pglinks = array("", "/snort/snort_interfaces.php", "@self");
$pgtitle = array("Services", "Snort", "{$if_friendly} - Interface Settings");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($vlan_warn_msg)
	print_info_box($vlan_warn_msg);

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

// If using Inline IPS, check that CSO, TSO and LRO are all disabled
if ($pconfig['enable'] == 'on' &&
    $pconfig['ips_mode'] == 'ips_mode_inline' &
    (!config_path_enabled('system', 'disablechecksumoffloading') ||
     !config_path_enabled('system', 'disablesegmentationoffloading') ||
     !config_path_enabled('system', 'disablelargereceiveoffloading'))
    ) {
	print_info_box(gettext('WARNING! IPS inline mode requires that Hardware Checksum Offloading, Hardware TCP Segmentation Offloading and Hardware Large Receive Offloading ' .
				'all be disabled for proper operation. This firewall currently has one or more of these Offloading settings NOT disabled. Visit the ') . '<a href="/system_advanced_network.php">' . 
			        gettext('System > Advanced > Networking') . '</a>' . gettext(' tab and ensure all three of these Offloading settings are disabled.'));
}

$form = new Form(new Form_Button(
	'save',
	'Save'
));

$section = new Form_Section('General Settings');
$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable interface',
	$pconfig['enable'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$pconfig['interface'],
	$interfaces
))->setHelp('Choose the interface where this Snort instance will inspect traffic.');
$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('Enter a meaningful description here for your reference.');
$section->addInput(new Form_Input(
	'snaplen',
	'Snap Length',
	'number',
	$pconfig['snaplen']
))->setHelp('Enter the desired interface snaplen value in bytes.  Default is 1518 and is suitable for most applications.');

$form->add($section);

$section = new Form_Section('Alert Settings');
$section->addInput(new Form_Checkbox(
	'alertsystemlog',
	'Send Alerts to System Log',
	'Snort will send Alerts to the firewall\'s system log.  Default is Not Checked.',
	$pconfig['alertsystemlog'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Select(
	'alertsystemlog_facility',
	'System Log Facility',
	$pconfig['alertsystemlog_facility'],
	array(  "log_auth" => gettext("LOG_AUTH"), "log_authpriv" => gettext("LOG_AUTHPRIV"), "log_daemon" => gettext("LOG_DAEMON"), "log_user" => gettext("LOG_USER"), 
		"log_local0" => gettext("LOG_LOCAL0"), "log_local1" => gettext("LOG_LOCAL1"), "log_local2" => gettext("LOG_LOCAL2"), "log_local3" => gettext("LOG_LOCAL3"), 
		"log_local4" => gettext("LOG_LOCAL4"), "log_local5" => gettext("LOG_LOCAL5"), "log_local6" => gettext("LOG_LOCAL6"), "log_local7" => gettext("LOG_LOCAL7") )
))->setHelp('Select system log Facility to use for reporting. Default is LOG_AUTH.');
$section->addInput(new Form_Select(
	'alertsystemlog_priority',
	'System Log Priority',
	$pconfig['alertsystemlog_priority'],
	array(  'log_emerg' => gettext('LOG_EMERG'), 'log_crit' => gettext('LOG_CRIT'), 'log_alert' => gettext('LOG_ALERT'), 'log_err' => gettext('LOG_ERR'), 
		'log_warning' => gettext('LOG_WARNING'), 'log_notice' => gettext('LOG_NOTICE'), 'log_info' => gettext('LOG_INFO'), 'log_debug' => gettext('LOG_DEBUG') )
))->setHelp('Select system log Priority (Level) to use for reporting. Default is LOG_ALERT.');
$section->addInput(new Form_Checkbox(
	'enable_pkt_caps',
	'Enable Packet Captures',
	'Checking this option will automatically capture packets that generate a Snort alert into a tcpdump compatible file',
	$pconfig['enable_pkt_caps'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Input(
	'tcpdump_file_size',
	'Packet Capture File Size',
	'number',
	$pconfig['tcpdump_file_size']
))->setHelp('Enter a value in megabytes for the packet capture file size limit. Default is 128 megabytes. When the limit is reached, the current packet capture file in directory ' . SNORTLOGDIR . '/snort_' . get_real_interface($pconfig['interface']) . $pconfig['uuid'] . ' is rotated and a new file opened.');
$section->addInput(new Form_Checkbox(
	'unified2_logging_enable',
	'Enable Unified2 Logging',
	'Checking this option will cause Snort to simultaneously log alerts to a unified2 binary format log file in the logging subdirectory for this interface. Default is Not Checked.',
	$pconfig['unified2_logging_enable'] == 'on' ? true:false,
	'on'
))->setHelp(' Log size and retention limits for the Unified2 log should be configured on the LOG MGMT tab when this option is enabled.');
$section->addInput(new Form_Checkbox(
	'unified2_log_vlan_events',
	'Log U2 VLAN Events',
	'Checking this option will cause Snort to log VLAN events to the unified2 binary format log for this interface. Default is Not Checked.',
	$pconfig['unified2_log_vlan_events'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'unified2_log_mpls_events',
	'Log U2 MPLS Events',
	'Checking this option will cause Snort to log MPLS events to the unified2 binary format log for this interface. Default is Not Checked.',
	$pconfig['unified2_log_mpls_events'] == 'on' ? true:false,
	'on'
));

$form->add($section);

$section = new Form_Section('Block Settings');
$section->addInput(new Form_Checkbox(
	'blockoffenders7',
	'Block Offenders',
	'Checking this option will automatically block hosts that generate a Snort alert.  Default is Not Checked.',
	$pconfig['blockoffenders7'] == 'on' ? true:false,
	'on'
));
$group = new Form_Group('IPS Mode');
$group->add(new Form_Select(
	'ips_mode',
	'IPS Mode',
	$pconfig['ips_mode'],
	array( "ips_mode_legacy" => "Legacy Mode", "ips_mode_inline" => "Inline Mode" )
))->setHelp('Select blocking mode operation.  Legacy Mode inspects copies of packets while Inline Mode inserts the Snort inspection ' . 
		'engine into the network stack between the NIC and the OS. Default is Legacy Mode.');
$group->setHelp('Legacy Mode uses the PCAP engine to generate copies of packets for inspection as they traverse the interface.  Some "leakage" of packets will occur before ' . 
		'Snort can determine if the traffic matches a rule and should be blocked.  Inline mode instead intercepts and inspects packets before they are handed ' . 
		'off to the host network stack for further processing.  Packets matching DROP rules are simply discarded (dropped) and not passed to the host ' . 
		'network stack.  No leakage of packets occurs with Inline Mode.  WARNING:  Inline Mode only works with NIC drivers which properly support Netmap! ' . 
		'Supported drivers: ' . implode(', ', $netmapifs) . '. If problems are experienced with Inline Mode, switch to Legacy Mode instead.');
$section->add($group);
$section->addInput(new Form_Checkbox(
	'blockoffenderskill',
	'Kill States',
	'Checking this option will kill firewall established states for the blocked IP.  Default is checked.',
	$pconfig['blockoffenderskill'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Select(
	'blockoffendersip',
	'Which IP to Block',
	$pconfig['blockoffendersip'],
	array( 'src' => gettext('SRC'), 'dst' => gettext('DST'), 'both' => gettext('BOTH') )
))->setHelp('Select which IP extracted from the packet you wish to block.  Default is BOTH.');

$form->add($section);

// Add Inline IPS rule edit warning modal pop-up
$modal = new Modal('Important Information About IPS Inline Mode Blocking', 'ips_warn_dlg', 'large', 'Close');

$modal->addInput(new Form_StaticText (
	null,
	'<span class="help-block">' . 
	gettext('When using Inline IPS Mode blocking, you must manually change the rule action ') . 
	gettext('from ALERT to DROP for every rule which you wish to block traffic when triggered.') . 
	'<br/><br/>' . 
	gettext('The default action for rules is ALERT.  This will produce alerts but will not ') . 
	gettext('block traffic when using Inline IPS Mode for blocking. ') . 
	'<br/><br/>' . 
	gettext('Use the "dropsid.conf" feature on the SID MGMT tab to select rules whose action ') . 
	gettext('should be changed from ALERT to DROP.  If you run the Snort Subscriber Rules and have ') . 
	gettext('an IPS policy selected on the CATEGORIES tab, then rules defined as DROP by the ') . 
	gettext('selected IPS policy will have their action automatically changed to DROP when the ') . 
	gettext('"IPS Policy Mode" selector is configured for "Policy".') . 
	'</span>'
));

$form->add($modal);

$section = new Form_Section('Detection Performance Settings');
$section->addInput(new Form_Select(
	'performance',
	'Search Method',
	$pconfig['performance'],
	array('ac-bnfa' => gettext('AC-BNFA'), 'ac-split' => gettext('AC-SPLIT'), 'lowmem' => gettext('LOWMEM'), 'ac-std' => gettext('AC-STD'), 
		  'ac' => gettext('AC'), 'ac-nq' => gettext('AC-NQ'), 'ac-bnfa-nq' => gettext('AC-BNFA-NQ'), 'lowmem-nq' => gettext('LOWMEM-NQ'), 
		  'ac-banded' => gettext('AC-BANDED'), 'ac-sparsebands' => gettext('AC-SPARSEBANDS'), 'acs' => gettext('ACS') )
))->setHelp('Choose a fast pattern matcher algorithm.  Default is AC-BNFA.');
$section->addInput(new Form_Checkbox(
	'fpm_split_any_any',
	'Split ANY-ANY',
	'Enable splitting of ANY-ANY port group.  Default is Not Checked.',
	$pconfig['fpm_split_any_any'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'fpm_search_optimize',
	'Search Optimize',
	'Enable search optimization.  Default is Not Checked.',
	$pconfig['fpm_search_optimize'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'fpm_no_stream_inserts',
	'Stream Inserts',
	'Do not evaluate stream inserted packets against the detection engine.  Default is Not Checked.',
	$pconfig['fpm_no_stream_inserts'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'cksumcheck',
	'Checksum Check Disable',
	'Disable checksum checking within Snort to improve performance.  Default is Not Checked.',
	$pconfig['cksumcheck'] == 'on' ? true:false,
	'on'
));

$form->add($section);

$section = new Form_Section('Choose the Networks Snort Should Inspect and Whitelist');

$group = new Form_Group('Home Net');
$group->add(new Form_Select(
	'homelistname',
	'Home Net',
	$pconfig['homelistname'],
	snort_get_config_lists('whitelist')
))->setHelp('Choose the Home Net you want this interface to use.');
$group->add(new Form_Button(
	'btnHomeNet',
	'View List',
	'#',
	'fa-file-text-o'
))->removeClass('btn-primary')->addClass('btn-info')->addClass('btn-sm')->setAttribute('data-toggle', 'modal')->setAttribute('data-target', '#homenet');
$group->setHelp('Default Home Net adds only local networks, WAN IPs, Gateways, VPNs and VIPs.' . '<br />' .
		'Create an Alias to hold a list of friendly IPs that the firewall cannot see or to customize the default Home Net.');
$section->add($group);

$group = new Form_Group('External Net');
$group->add(new Form_Select(
	'externallistname',
	'External Net',
	$pconfig['externallistname'],
	snort_get_config_lists('whitelist')
))->setHelp('Choose the External Net you want this interface to use.');
$group->add(new Form_Button(
	'btnExternalNet',
	'View List',
	'#',
	'fa-file-text-o'
))->removeClass('btn-primary')->addClass('btn-info')->addClass('btn-sm')->setAttribute('data-target', '#externalnet')->setAttribute('data-toggle', 'modal');
$group->setHelp('External Net is networks that are not Home Net.  Most users should leave this setting at default.' . '<br />' .
		'Create a Pass List and add an Alias to it, and then assign the Pass List here for custom External Net settings.');
$section->add($group);

$group = new Form_Group('Pass List');
$group->addClass('passlist');
$group->add(new Form_Select(
	'whitelistname',
	'Pass List',
	$pconfig['whitelistname'],
	snort_get_config_lists('whitelist')
))->setHelp('Choose the Pass List you want this interface to use.');
$group->add(new Form_Button(
	'btnWhitelist',
	'View List',
	'#',
	'fa-file-text-o'
))->removeClass('btn-primary')->addClass('btn-info')->addClass('btn-sm')->setAttribute('data-target', '#whitelist')->setAttribute('data-toggle', 'modal');
$group->setHelp('The default Pass List adds local networks, WAN IPs, Gateways, VPNs and VIPs.  Create an Alias to customize.' . '<br />' .
		'This option will only be used when block offenders is on and IPS Mode is set to Legacy Mode.');
$section->add($group);

$form->add($section);

// Add view HOME_NET modal pop-up
$modal = new Modal('View HOME_NET', 'homenet', 'large', 'Close');
$modal->addInput(new Form_Textarea (
	'homenet_text',
	'',
	'...Loading...'
))->removeClass('form-control')->addClass('row-fluid col-sm-10')->setAttribute('rows', '10')->setAttribute('wrap', 'off');
$form->add($modal);

// Add view EXTERNAL_NET modal pop-up
$modal = new Modal('View EXTERNAL_NET', 'externalnet', 'large', 'Close');
$modal->addInput(new Form_Textarea (
	'externalnet_text',
	'',
	'...Loading...'
))->removeClass('form-control')->addClass('row-fluid col-sm-10')->setAttribute('rows', '10')->setAttribute('wrap', 'off');
$form->add($modal);

// Add view PASS_LIST modal pop-up
$modal = new Modal('View PASS LIST', 'whitelist', 'large', 'Close');
$modal->addInput(new Form_Textarea (
	'whitelist_text',
	'',
	'...Loading...'
))->removeClass('form-control')->addClass('row-fluid col-sm-10')->setAttribute('rows', '10')->setAttribute('wrap', 'off');
$form->add($modal);


$section = new Form_Section('Choose a Suppression or Filtering List (Optional)');
$group = new Form_Group('Alert Suppression and Filtering');
$group->add(new Form_Select(
	'suppresslistname',
	'Alert Suppression and Filtering',
	$pconfig['suppresslistname'],
	snort_get_config_lists('suppress')
))->setHelp('Choose the suppression or filtering file you want this interface to use.');
$group->add(new Form_Button(
	'btnSuppressList',
	'View List',
	'#',
	'fa-file-text-o'
))->removeClass('btn-primary')->addClass('btn-info')->addClass('btn-sm')->setAttribute('data-target', '#suppresslist')->setAttribute('data-toggle', 'modal');
$section->add($group);

$form->add($section);

// Add view SUPPRESS_LIST modal pop-up
$modal = new Modal('View Suppress List', 'suppresslist', 'large', 'Close');
$modal->addInput(new Form_Textarea (
	'suppresslist_text',
	'',
	'...Loading...'
))->removeClass('form-control')->addClass('row-fluid col-sm-10')->setAttribute('rows', '10')->setAttribute('wrap', 'off');
$form->add($modal);

$section = new Form_Section('Custom Configuration Options');
$section->addInput(new Form_Textarea (
	'configpassthru',
	'Advanced Configuration Pass-Through',
	base64_decode($pconfig['configpassthru'])
))->setHelp('Enter any additional configuration parameters to add to the Snort configuration here, separated by a newline');

$form->add($section);

if (isset($id)) {
	$form->addGlobal(new Form_Input(
		'id',
		'id',
		'hidden',
		$id
	));
}
if (isset($action)) {
	$form->addGlobal(new Form_Input(
		'action',
		'action',
		'hidden',
		$action
	));
}

$tab_array = array();
	$tab_array[] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
	if ($new_interface) {
		$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
	} else {
		$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php?instance={$id}");
	}
	$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
	$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);

$tab_array = array();
	$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
	$tab_array[] = array($menu_iface . gettext("Settings"), true, "/snort/snort_interfaces_edit.php?id={$id}");
	if (!$new_interface) {
		$tab_array[] = array($menu_iface . gettext("Categories"), false, "/snort/snort_rulesets.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Rules"), false, "/snort/snort_rules.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Variables"), false, "/snort/snort_define_servers.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Preprocs"), false, "/snort/snort_preprocessors.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/snort/snort_ip_reputation.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
	}
display_top_tabs($tab_array, true, 'nav nav-tabs');

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

	function enable_blockoffenders() {
		var hide = ! $('#blockoffenders7').prop('checked');
		hideCheckbox('blockoffenderskill', hide);
		hideSelect('blockoffendersip', hide);
		hideSelect('ips_mode', hide);
		hideClass('passlist', hide);
		if ($('#ips_mode').val() == 'ips_mode_inline') {
			hideCheckbox('blockoffenderskill', true);
			hideSelect('blockoffendersip', true);
			hideClass('passlist', true);
		}
	}

	function toggle_system_log() {
		var hide = ! $('#alertsystemlog').prop('checked');
		hideSelect('alertsystemlog_facility', hide);
		hideSelect('alertsystemlog_priority', hide);
	}

	function toggle_enable_pkt_caps() {
		var hide = ! $('#enable_pkt_caps').prop('checked');
		hideInput('tcpdump_file_size', hide);
	}

	function toggle_unified2_events_logging() {
		var hide = ! $('#unified2_logging_enable').prop('checked');
		hideCheckbox('unified2_log_vlan_events', hide);
		hideCheckbox('unified2_log_mpls_events', hide);
	}

	function enable_change() {
		var hide = ! $('#enable').prop('checked');
		disableInput('alertsystemlog', hide);
		disableInput('alertsystemlog_facility', hide);
		disableInput('alertsystemlog_priority', hide);
		disableInput('enable_pkt_caps', hide);
		disableInput('tcpdump_file_size', hide);
		disableInput('unified2_logging_enable', hide);
		disableInput('unified2_log_vlan_events', hide);
		disableInput('unified2_log_mpls_events', hide);
		disableInput('blockoffenders7', hide);
		disableInput('ips_mode', hide);
		disableInput('blockoffenderskill', hide);
		disableInput('blockoffendersip', hide);
		disableInput('performance', hide);
		disableInput('fpm_split_any_any', hide);
		disableInput('fpm_search_optimize', hide);
		disableInput('fpm_no_stream_inserts', hide);
		disableInput('cksumcheck', hide);
		disableInput('externallistname', hide);
		disableInput('homelistname', hide);
		disableInput('suppresslistname', hide);
		disableInput('btnHomeNet', hide);
		disableInput('btnExternalNet', hide);
		disableInput('btnSuppressList', hide);
		disableInput('whitelistname', hide);
		disableInput('btnWhitelist', hide);
		disableInput('configpassthru', hide);
		disableInput('snaplen', hide);
	}

	function getListContents(listName, listType, ctrlID) {
		var ajaxRequest;

		ajaxRequest = $.ajax({
			url: "/snort/snort_interfaces_edit.php",
			type: "post",
			data: { ajax: "ajax", 
			        list: listName, 
				type: listType, 
				id: $('#id').val(), 
				action: $('#action').val()
			}
		});

		// Display the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {

			// Write the list contents to the text control
			$('#' + ctrlID).text(response);
			$('#' + ctrlID).attr('readonly', true);
		});
	}

	// ---------- Event triggers fired after the VIEW LIST modals are shown -----------------------
	$('#homenet').on('shown.bs.modal', function() {
		getListContents($('#homelistname option:selected' ).text(), 'homenet', 'homenet_text');
	});

	$('#externalnet').on('shown.bs.modal', function() {
		getListContents($('#externallistname option:selected' ).text(), 'externalnet', 'externalnet_text');
	});

	$('#whitelist').on('shown.bs.modal', function() {
		getListContents($('#whitelistname option:selected' ).text(), 'passlist', 'whitelist_text');
	});

	$('#suppresslist').on('shown.bs.modal', function() {
		getListContents($('#suppresslistname option:selected' ).text(), 'suppress', 'suppresslist_text');
	});

	// ---------- Click checkbox handlers ---------------------------------------------------------
	// When 'enable' is clicked, disable/enable the form controls
	$('#enable').click(function() {
		enable_change();
	});

	// When 'alertsystemlog' is clicked, disable/enable associated form controls
	$('#alertsystemlog').click(function() {
		toggle_system_log();
	});

	// When 'unified2_logging_enable' is clicked, disable/enable associated form controls
	$('#unified2_logging_enable').click(function() {
		toggle_unified2_events_logging();
	});

	// When 'blockoffenders7' is clicked, disable/enable associated form controls
	$('#blockoffenders7').click(function() {
		enable_blockoffenders();
	});

	// When 'enable_pkt_caps' is clicked, disable/enable associated form controls
	$('#enable_pkt_caps').click(function() {
		toggle_enable_pkt_caps();
	});

	$('#ips_mode').on('change', function() {
		if ($('#ips_mode').val() == 'ips_mode_inline') {
			hideCheckbox('blockoffenderskill', true);
			hideSelect('blockoffendersip', true);
			hideClass('passlist', true);
			$('#ips_warn_dlg').modal('show');
		}
		else {
			hideCheckbox('blockoffenderskill', false);
			hideSelect('blockoffendersip', false);
			hideClass('passlist', false);
			$('#ips_warn_dlg').modal('hide');
		}
	});

	// ---------- On initial page load ------------------------------------------------------------
	enable_change();
	enable_blockoffenders();
	toggle_system_log();
	toggle_unified2_events_logging();
	toggle_enable_pkt_caps();
});
//]]>
</script>

<?php include("foot.inc"); ?>
