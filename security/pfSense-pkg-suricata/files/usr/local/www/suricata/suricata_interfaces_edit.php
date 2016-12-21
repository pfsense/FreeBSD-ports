<?php
/*
 * suricata_interfaces_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2016 Bill Meeks
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

global $g, $rebuild_rules;

$suricatadir = SURICATADIR;
$suricatalogdir = SURICATALOGDIR;

if (!is_array($config['installedpackages']['suricata']))
	$config['installedpackages']['suricata'] = array();
$suricataglob = $config['installedpackages']['suricata'];

if (!is_array($config['installedpackages']['suricata']['rule']))
	$config['installedpackages']['suricata']['rule'] = array();
$a_rule = &$config['installedpackages']['suricata']['rule'];

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']));
	$id = htmlspecialchars($_GET['id'], ENT_QUOTES | ENT_HTML401);

if (is_null($id)) {
		header("Location: /suricata/suricata_interfaces.php");
		exit;
}

if (isset($_POST['action']))
	$action = htmlspecialchars($_POST['action'], ENT_QUOTES | ENT_HTML401);
elseif (isset($_GET['action']))
	$action = htmlspecialchars($_GET['action'], ENT_QUOTES | ENT_HTML401);
else
	$action = "";

$pconfig = array();
if (empty($suricataglob['rule'][$id]['uuid'])) {
	/* Adding new interface, so generate a new UUID and flag rules to build. */
	$pconfig['uuid'] = suricata_generate_id();
	$rebuild_rules = true;
}
else {
	$pconfig['uuid'] = $a_rule[$id]['uuid'];
	$pconfig['descr'] = $a_rule[$id]['descr'];
	$rebuild_rules = false;
}
$suricata_uuid = $pconfig['uuid'];

// Get the physical configured interfaces on the firewall
$interfaces = get_configured_interface_with_descr();

// See if interface is already configured, and use its values
if (isset($id) && $a_rule[$id]) {
	/* old options */
	$pconfig = $a_rule[$id];
	if (!empty($pconfig['configpassthru']))
		$pconfig['configpassthru'] = base64_decode($pconfig['configpassthru']);
	if (empty($pconfig['uuid']))
		$pconfig['uuid'] = $suricata_uuid;
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
			$pconfig['enable'] = 'on';
			$pconfig['descr'] = strtoupper($i);
			$pconfig['inspect_recursion_limit'] = '3000';
			break;
		}
	}
	if (count($ifrules) == count($ifaces)) {
		$input_errors[] = gettext("No more available interfaces to configure for Suricata!");
		$interfaces = array();
		$pconfig = array();
	}
}

// Set defaults for any empty key parameters
if (empty($pconfig['blockoffendersip']))
	$pconfig['blockoffendersip'] = "both";
if (empty($pconfig['blockoffenderskill']))
	$pconfig['blockoffenderskill'] = "on";
if (empty($pconfig['ips_mode']))
	$pconfig['ips_mode'] = 'ips_mode_legacy';
if (empty($pconfig['max_pending_packets']))
	$pconfig['max_pending_packets'] = "1024";
if (empty($pconfig['detect_eng_profile']))
	$pconfig['detect_eng_profile'] = "medium";
if (empty($pconfig['mpm_algo']))
	$pconfig['mpm_algo'] = "ac";
if (empty($pconfig['sgh_mpm_context']))
	$pconfig['sgh_mpm_context'] = "auto";
if (empty($pconfig['enable_http_log']))
	$pconfig['enable_http_log'] = "on";
if (empty($pconfig['append_http_log']))
	$pconfig['append_http_log'] = "on";
if (empty($pconfig['http_log_extended']))
	$pconfig['http_log_extended'] = "on";
if (empty($pconfig['tls_log_extended']))
	$pconfig['tls_log_extended'] = "on";
if (empty($pconfig['stats_upd_interval']))
	$pconfig['stats_upd_interval'] = "10";
if (empty($pconfig['append_dns_log']))
	$pconfig['append_dns_log'] = "on";
if (empty($pconfig['append_json_file_log']))
	$pconfig['append_json_file_log'] = "on";
if (empty($pconfig['max_pcap_log_size']))
	$pconfig['max_pcap_log_size'] = "32";
if (empty($pconfig['max_pcap_log_files']))
	$pconfig['max_pcap_log_files'] = "1000";
if (empty($pconfig['alertsystemlog_facility']))
	$pconfig['alertsystemlog_facility'] = "local1";
if (empty($pconfig['alertsystemlog_priority']))
	$pconfig['alertsystemlog_priority'] = "notice";
if (empty($pconfig['eve_output_type']))
	$pconfig['eve_output_type'] = "file";
if (empty($pconfig['eve_systemlog_facility']))
	$pconfig['eve_systemlog_facility'] = "local1";
if (empty($pconfig['eve_systemlog_priority']))
	$pconfig['eve_systemlog_priority'] = "notice";
if (empty($pconfig['eve_log_alerts']))
	$pconfig['eve_log_alerts'] = "on";
if (empty($pconfig['eve_log_alerts_payload']))
	$pconfig['eve_log_alerts_payload'] = "on";
if (empty($pconfig['eve_log_http']))
	$pconfig['eve_log_http'] = "on";
if (empty($pconfig['eve_log_dns']))
	$pconfig['eve_log_dns'] = "on";
if (empty($pconfig['eve_log_tls']))
	$pconfig['eve_log_tls'] = "on";
if (empty($pconfig['eve_log_files']))
	$pconfig['eve_log_files'] = "on";
if (empty($pconfig['eve_log_ssh']))
	$pconfig['eve_log_ssh'] = "on";
if (empty($pconfig['intf_promisc_mode']))
	$pconfig['intf_promisc_mode'] = "on";

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
			$pconfig['descr'] = strtoupper($i);
			$pconfig['inspect_recursion_limit'] = '3000';
			break;
		}
	}

	if (count($ifrules) == count($ifaces)) {
		$input_errors[] = gettext("No more available interfaces to configure for Suricata!");
		$interfaces = array();
		$pconfig = array();
	}

	// Set Home Net, External Net, Suppress List and Pass List to defaults
	unset($pconfig['suppresslistname']);
	unset($pconfig['passlistname']);
	unset($pconfig['homelistname']);
	unset($pconfig['externallistname']);
}

if ($_REQUEST['ajax'] == 'ajax') {
	print("At least we got that straight!");
	exit;
}

if (isset($_POST["save"]) && !$input_errors) {
	if (!isset($_POST['interface']))
		$input_errors[] = gettext("Choosing an Interface is mandatory!");

	/* See if assigned interface is already in use */
	if (isset($_POST['interface'])) {
		foreach ($a_rule as $k => $v) {
			if (($v['interface'] == $_POST['interface']) && ($id != $k)) {
				$input_errors[] = gettext("The '{$_POST['interface']}' interface is already assigned to another Suricata instance.");
				break;
			}
		}
	}

	// If Suricata is disabled on this interface, stop any running instance,
	// save the change and exit.
	if ($_POST['enable'] != 'on') {
		$a_rule[$id]['enable'] = $_POST['enable'] ? 'on' : 'off';
		suricata_stop($a_rule[$id], get_real_interface($a_rule[$id]['interface']));
		write_config("Suricata pkg: disabled Suricata on " . convert_friendly_interface_to_friendly_descr($a_rule[$id]['interface']));
		$rebuild_rules = false;
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /suricata/suricata_interfaces.php");
		exit;
	}

	// Validate inputs
	if (isset($_POST['stats_upd_interval']) && !is_numericint($_POST['stats_upd_interval']))
		$input_errors[] = gettext("The value for Stats Update Interval must contain only digits and evaluate to an integer.");

	if ($_POST['max_pending_packets'] < 1 || $_POST['max_pending_packets'] > 65000)
		$input_errors[] = gettext("The value for Maximum-Pending-Packets must be between 1 and 65,000!");

	if (isset($_POST['max_pcap_log_size']) && !is_numeric($_POST['max_pcap_log_size']))
		$input_errors[] = gettext("The value for 'Max Packet Log Size' must be numbers only.  Do not include any alphabetic characters.");

	if (isset($_POST['max_pcap_log_files']) && !is_numeric($_POST['max_pcap_log_files']))
		$input_errors[] = gettext("The value for 'Max Packet Log Files' must be numbers only.");

	if (!empty($_POST['inspect_recursion_limit']) && !is_numeric($_POST['inspect_recursion_limit']))
		$input_errors[] = gettext("The value for Inspect Recursion Limit can either be blank or contain only digits evaluating to an integer greater than or equal to 0.");

	// if no errors write to suricata.yaml
	if (!$input_errors) {
		$natent = $a_rule[$id];
		$natent['interface'] = $_POST['interface'];
		$natent['enable'] = $_POST['enable'] ? 'on' : 'off';
		$natent['uuid'] = $pconfig['uuid'];

		if ($_POST['descr']) $natent['descr'] =  htmlspecialchars($_POST['descr']); else $natent['descr'] = strtoupper($natent['interface']);
		if ($_POST['max_pcap_log_size']) $natent['max_pcap_log_size'] = $_POST['max_pcap_log_size']; else unset($natent['max_pcap_log_size']);
		if ($_POST['max_pcap_log_files']) $natent['max_pcap_log_files'] = $_POST['max_pcap_log_files']; else unset($natent['max_pcap_log_files']);
		if ($_POST['enable_stats_log'] == "on") { $natent['enable_stats_log'] = 'on'; }else{ $natent['enable_stats_log'] = 'off'; }
		if ($_POST['append_stats_log'] == "on") { $natent['append_stats_log'] = 'on'; }else{ $natent['append_stats_log'] = 'off'; }
		if ($_POST['stats_upd_interval'] >= 1) $natent['stats_upd_interval'] = $_POST['stats_upd_interval']; else $natent['stats_upd_interval'] = "10";
		if ($_POST['enable_http_log'] == "on") { $natent['enable_http_log'] = 'on'; }else{ $natent['enable_http_log'] = 'off'; }
		if ($_POST['append_http_log'] == "on") { $natent['append_http_log'] = 'on'; }else{ $natent['append_http_log'] = 'off'; }
		if ($_POST['enable_tls_log'] == "on") { $natent['enable_tls_log'] = 'on'; }else{ $natent['enable_tls_log'] = 'off'; }
		if ($_POST['http_log_extended'] == "on") { $natent['http_log_extended'] = 'on'; }else{ $natent['http_log_extended'] = 'off'; }
		if ($_POST['tls_log_extended'] == "on") { $natent['tls_log_extended'] = 'on'; }else{ $natent['tls_log_extended'] = 'off'; }
		if ($_POST['enable_pcap_log'] == "on") { $natent['enable_pcap_log'] = 'on'; }else{ $natent['enable_pcap_log'] = 'off'; }
		if ($_POST['enable_json_file_log'] == "on") { $natent['enable_json_file_log'] = 'on'; }else{ $natent['enable_json_file_log'] = 'off'; }
		if ($_POST['append_json_file_log'] == "on") { $natent['append_json_file_log'] = 'on'; }else{ $natent['append_json_file_log'] = 'off'; }
		if ($_POST['enable_tracked_files_magic'] == "on") { $natent['enable_tracked_files_magic'] = 'on'; }else{ $natent['enable_tracked_files_magic'] = 'off'; }
		if ($_POST['enable_tracked_files_md5'] == "on") { $natent['enable_tracked_files_md5'] = 'on'; }else{ $natent['enable_tracked_files_md5'] = 'off'; }
		if ($_POST['enable_file_store'] == "on") { $natent['enable_file_store'] = 'on'; }else{ $natent['enable_file_store'] = 'off'; }
		if ($_POST['enable_eve_log'] == "on") { $natent['enable_eve_log'] = 'on'; }else{ $natent['enable_eve_log'] = 'off'; }
		if ($_POST['max_pending_packets']) $natent['max_pending_packets'] = $_POST['max_pending_packets']; else unset($natent['max_pending_packets']);
		if ($_POST['inspect_recursion_limit'] >= '0') $natent['inspect_recursion_limit'] = $_POST['inspect_recursion_limit']; else unset($natent['inspect_recursion_limit']);
		if ($_POST['detect_eng_profile']) $natent['detect_eng_profile'] = $_POST['detect_eng_profile']; else unset($natent['detect_eng_profile']);
		if ($_POST['mpm_algo']) $natent['mpm_algo'] = $_POST['mpm_algo']; else unset($natent['mpm_algo']);
		if ($_POST['sgh_mpm_context']) $natent['sgh_mpm_context'] = $_POST['sgh_mpm_context']; else unset($natent['sgh_mpm_context']);
		if ($_POST['blockoffenders'] == "on") $natent['blockoffenders'] = 'on'; else $natent['blockoffenders'] = 'off';
		if ($_POST['ips_mode']) $natent['ips_mode'] = $_POST['ips_mode']; else unset($natent['ips_mode']);
		if ($_POST['blockoffenderskill'] == "on") $natent['blockoffenderskill'] = 'on'; else $natent['blockoffenderskill'] = 'off';
		if ($_POST['blockoffendersip']) $natent['blockoffendersip'] = $_POST['blockoffendersip']; else unset($natent['blockoffendersip']);
		if ($_POST['passlistname']) $natent['passlistname'] =  $_POST['passlistname']; else unset($natent['passlistname']);
		if ($_POST['homelistname']) $natent['homelistname'] =  $_POST['homelistname']; else unset($natent['homelistname']);
		if ($_POST['externallistname']) $natent['externallistname'] =  $_POST['externallistname']; else unset($natent['externallistname']);
		if ($_POST['suppresslistname']) $natent['suppresslistname'] =  $_POST['suppresslistname']; else unset($natent['suppresslistname']);
		if ($_POST['alertsystemlog'] == "on") { $natent['alertsystemlog'] = 'on'; }else{ $natent['alertsystemlog'] = 'off'; }
		if ($_POST['alertsystemlog_facility']) $natent['alertsystemlog_facility'] = $_POST['alertsystemlog_facility'];
		if ($_POST['alertsystemlog_priority']) $natent['alertsystemlog_priority'] = $_POST['alertsystemlog_priority'];
		if ($_POST['enable_dns_log'] == "on") { $natent['enable_dns_log'] = 'on'; }else{ $natent['enable_dns_log'] = 'off'; }
		if ($_POST['append_dns_log'] == "on") { $natent['append_dns_log'] = 'on'; }else{ $natent['append_dns_log'] = 'off'; }
		if ($_POST['enable_eve_log'] == "on") { $natent['enable_eve_log'] = 'on'; }else{ $natent['enable_eve_log'] = 'off'; }
		if ($_POST['eve_output_type']) $natent['eve_output_type'] = $_POST['eve_output_type'];
		if ($_POST['eve_systemlog_facility']) $natent['eve_systemlog_facility'] = $_POST['eve_systemlog_facility'];
		if ($_POST['eve_systemlog_priority']) $natent['eve_systemlog_priority'] = $_POST['eve_systemlog_priority'];
		if ($_POST['eve_log_alerts'] == "on") { $natent['eve_log_alerts'] = 'on'; }else{ $natent['eve_log_alerts'] = 'off'; }
		if ($_POST['eve_log_alerts_payload'] == "on") { $natent['eve_log_alerts_payload'] = 'on'; }else{ $natent['eve_log_alerts_payload'] = 'off'; }
		if ($_POST['eve_log_http'] == "on") { $natent['eve_log_http'] = 'on'; }else{ $natent['eve_log_http'] = 'off'; }
		if ($_POST['eve_log_dns'] == "on") { $natent['eve_log_dns'] = 'on'; }else{ $natent['eve_log_dns'] = 'off'; }
		if ($_POST['eve_log_tls'] == "on") { $natent['eve_log_tls'] = 'on'; }else{ $natent['eve_log_tls'] = 'off'; }
		if ($_POST['eve_log_files'] == "on") { $natent['eve_log_files'] = 'on'; }else{ $natent['eve_log_files'] = 'off'; }
		if ($_POST['eve_log_ssh'] == "on") { $natent['eve_log_ssh'] = 'on'; }else{ $natent['eve_log_ssh'] = 'off'; }
		if ($_POST['delayed_detect'] == "on") { $natent['delayed_detect'] = 'on'; }else{ $natent['delayed_detect'] = 'off'; }
		if ($_POST['intf_promisc_mode'] == "on") { $natent['intf_promisc_mode'] = 'on'; }else{ $natent['intf_promisc_mode'] = 'off'; }
		if ($_POST['configpassthru']) $natent['configpassthru'] = base64_encode(str_replace("\r\n", "\n", $_POST['configpassthru'])); else unset($natent['configpassthru']);

		// Check if EVE OUTPUT TYPE is 'syslog' and auto-enable Suricata syslog output if true.
		if ($natent['eve_output_type'] == "syslog" && $natent['alertsystemlog'] == "off") {
			$natent['alertsystemlog'] = "on";
			$savemsg = gettext("EVE Output to syslog requires Suricata alerts to be copied to the system log, so 'Send Alerts to System Log' has been auto-enabled.");
		}

		$if_real = get_real_interface($natent['interface']);
		if (isset($id) && $a_rule[$id] && $action == '') {
			// See if moving an existing Suricata instance to another physical interface
			if ($natent['interface'] != $a_rule[$id]['interface']) {
				$oif_real = get_real_interface($a_rule[$id]['interface']);
				if (suricata_is_running($a_rule[$id]['uuid'], $oif_real)) {
					suricata_stop($a_rule[$id], $oif_real);
					$suricata_start = true;
				}
				else
					$suricata_start = false;
				@rename("{$suricatalogdir}suricata_{$oif_real}{$a_rule[$id]['uuid']}", "{$suricatalogdir}suricata_{$if_real}{$a_rule[$id]['uuid']}");
				conf_mount_rw();
				@rename("{$suricatadir}suricata_{$a_rule[$id]['uuid']}_{$oif_real}", "{$suricatadir}suricata_{$a_rule[$id]['uuid']}_{$if_real}");
				conf_mount_ro();
			}
			$a_rule[$id] = $natent;
		}
		elseif (strcasecmp($action, 'dup') == 0) {
			// Duplicating an existing interface to a new interface, so set flag to build new rules
			$rebuild_rules = true;

			// Duplicating an interface, so need to generate a new UUID for the cloned interface
			$natent['uuid'] = suricata_generate_id();

			// Add the new duplicated interface configuration to the [rule] array in config
			$a_rule[] = $natent;
		}
		else {
			// Adding new interface, so set interface configuration parameter defaults
			$natent['ip_max_frags'] = "65535";
			$natent['ip_frag_timeout'] = "60";
			$natent['frag_memcap'] = '33554432';
			$natent['ip_max_trackers'] = '65535';
			$natent['frag_hash_size'] = '65536';

			$natent['flow_memcap'] = '33554432';
			$natent['flow_prealloc'] = '10000';
			$natent['flow_hash_size'] = '65536';
			$natent['flow_emerg_recovery'] = '30';
			$natent['flow_prune'] = '5';

			$natent['flow_tcp_new_timeout'] = '60';
			$natent['flow_tcp_established_timeout'] = '3600';
			$natent['flow_tcp_closed_timeout'] = '120';
			$natent['flow_tcp_emerg_new_timeout'] = '10';
			$natent['flow_tcp_emerg_established_timeout'] = '300';
			$natent['flow_tcp_emerg_closed_timeout'] = '20';

			$natent['flow_udp_new_timeout'] = '30';
			$natent['flow_udp_established_timeout'] = '300';
			$natent['flow_udp_emerg_new_timeout'] = '10';
			$natent['flow_udp_emerg_established_timeout'] = '100';

			$natent['flow_icmp_new_timeout'] = '30';
			$natent['flow_icmp_established_timeout'] = '300';
			$natent['flow_icmp_emerg_new_timeout'] = '10';
			$natent['flow_icmp_emerg_established_timeout'] = '100';

			$natent['stream_memcap'] = '67108864';
			$natent['stream_prealloc_sessions'] = '32768';
			$natent['reassembly_memcap'] = '67108864';
			$natent['reassembly_depth'] = '1048576';
			$natent['reassembly_to_server_chunk'] = '2560';
			$natent['reassembly_to_client_chunk'] = '2560';
			$natent['max_synack_queued'] = '5';
			$natent['enable_midstream_sessions'] = 'off';
			$natent['enable_async_sessions'] = 'off';
			$natent['delayed_detect'] = 'off';
			$natent['intf_promisc_mode'] = 'on';

			$natent['asn1_max_frames'] = '256';
			$natent['dns_global_memcap'] = "16777216";
			$natent['dns_state_memcap'] = "524288";
			$natent['dns_request_flood_limit'] = "500";
			$natent['http_parser_memcap'] = "67108864";
			$natent['dns_parser_udp'] = "yes";
			$natent['dns_parser_tcp'] = "yes";
			$natent['http_parser'] = "yes";
			$natent['tls_parser'] = "yes";
			$natent['smtp_parser'] = "yes";
			$natent['imap_parser'] = "detection-only";
			$natent['ssh_parser'] = "yes";
			$natent['ftp_parser'] = "yes";
			$natent['dcerpc_parser'] = "yes";
			$natent['smb_parser'] = "yes";
			$natent['msn_parser'] = "detection-only";

			$natent['enable_iprep'] = "off";
			$natent['host_memcap'] = "16777216";
			$natent['host_hash_size'] = "4096";
			$natent['host_prealloc'] = "1000";

			$default = array( "name" => "default", "bind_to" => "all", "policy" => "bsd" );
			if (!is_array($natent['host_os_policy']['item']))
				$natent['host_os_policy']['item'] = array();
			$natent['host_os_policy']['item'][] = $default;

			$default = array( "name" => "default", "bind_to" => "all", "personality" => "IDS",
					  "request-body-limit" => 4096, "response-body-limit" => 4096,
					  "double-decode-path" => "no", "double-decode-query" => "no",
					  "uri-include-all" => "no" );
			if (!is_array($natent['libhtp_policy']['item']))
				$natent['libhtp_policy']['item'] = array();
			$natent['libhtp_policy']['item'][] = $default;

			// Enable the basic default rules for the interface
			$natent['rulesets'] = "decoder-events.rules||dns-events.rules||files.rules||http-events.rules||smtp-events.rules||stream-events.rules||tls-events.rules";

			// Adding a new interface, so set flag to build new rules
			$rebuild_rules = true;

			// Add the new interface configuration to the [rule] array in config
			$a_rule[] = $natent;
		}

		// If Suricata is disabled on this interface, stop any running instance
		if ($natent['enable'] != 'on')
			suricata_stop($natent, $if_real);

		// Save configuration changes
		write_config("Suricata pkg: modified interface configuration for " . convert_friendly_interface_to_friendly_descr($natent['interface']));

		// Update suricata.conf and suricata.sh files for this interface
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();

		// Refresh page fields with just-saved values
		$pconfig = $natent;
	} else
		$pconfig = $_POST;
}

function suricata_get_config_lists($lists) {
	global $suricataglob;

	$list = array();

	if (is_array($suricataglob[$lists]['item'])) {
		$slist_select = $suricataglob[$lists]['item'];
		foreach ($slist_select as $value) {
			$ilistname = $value['name'];
			$list[$ilistname] = htmlspecialchars($ilistname);
		}
	}

	return(['default' => 'default'] + $list);
}

$if_friendly = convert_friendly_interface_to_friendly_descr($pconfig['interface']);

$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Edit Interface Settings - {$if_friendly}"));
include_once("head.inc");

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}

if ($pconfig['enable'] == 'on' && $pconfig['ips_mode'] == 'ips_mode_inline' && (!isset($config['system']['disablechecksumoffloading']) || !isset($config['system']['disablesegmentationoffloading']) || !isset($config['system']['disablelargereceiveoffloading']))) {
	print_info_box(gettext('IPS inline mode requires that Hardware Checksum, Hardware TCP Segmentation and Hardware Large Receive Offloading ' .
				'all be disabled on the ') . '<b>' . gettext('System > Advanced > Networking ') . '</b>' . gettext('tab.'));
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), true, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php?instance={$id}");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$id}");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

$tab_array = array();
$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
$tab_array[] = array($menu_iface . gettext("Settings"), true, "/suricata/suricata_interfaces_edit.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Categories"), false, "/suricata/suricata_rulesets.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Rules"), false, "/suricata/suricata_rules.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Flow/Stream"), false, "/suricata/suricata_flow_stream.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("App Parsers"), false, "/suricata/suricata_app_parsers.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Variables"), false, "/suricata/suricata_define_vars.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/suricata/suricata_barnyard.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/suricata/suricata_ip_reputation.php?id={$id}");
display_top_tabs($tab_array, true);

$form = new Form;

$section = new Form_Section('General Settings');
$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Checking this box enables Suricata inspection on the interface.',
	$pconfig['enable'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$pconfig['interface'],
	$interfaces
))->setHelp('Choose which interface this Suricata instance applies to. In most cases, you will want to use WAN here if this is the first Suricata-configured interface.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('Enter a meaningful description here for your reference. The default is the interface name.');

$form->add($section);

$section = new Form_Section('Logging Settings');

$section->addInput(new Form_Checkbox(
	'alertsystemlog',
	'Send Alerts to System Log',
	'Suricata will send Alerts from this interface to the firewall\'s system log.',
	$pconfig['alertsystemlog'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Select(
	'alertsystemlog_facility',
	'Log Facility',
	$pconfig['alertsystemlog_facility'],
	array(  "auth" => "AUTH", "authpriv" => "AUTHPRIV", "daemon" => "DAEMON", "kern" => "KERN", "security" => "SECURITY", 
		"syslog" => "SYSLOG", "user" => "USER", "local0" => "LOCAL0", "local1" => "LOCAL1", "local2" => "LOCAL2", 
		"local3" => "LOCAL3", "local4" => "LOCAL4", "local5" => "LOCAL5", "local6" => "LOCAL6", "local7" => "LOCAL7" )
))->setHelp('Select system log Facility to use for reporting. Default is LOCAL1.');

$section->addInput(new Form_Select(
	'alertsystemlog_priority',
	'Log Priority',
	$pconfig['alertsystemlog_priority'],
	array( "emerg" => "EMERG", "crit" => "CRIT", "alert" => "ALERT", "err" => "ERR", "warning" => "WARNING", "notice" => "NOTICE", "info" => "INFO" )
))->setHelp('Select system log Priority (Level) to use for reporting. Default is NOTICE.');

$section->addInput(new Form_Checkbox(
	'enable_dns_log',
	'Enable DNS Log',
	'Suricata will log DNS requests and replies for the interface. Default is Not Checked.',
	$pconfig['enable_dns_log'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'append_dns_log',
	'Append DNS Log',
	'Suricata will append-to instead of clearing DNS log file when restarting. Default is Checked.',
	$pconfig['append_dns_log'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'enable_stats_log',
	'Enable Stats Log',
	'Suricata will periodically log statistics for the interface. Default is Not Checked.',
	$pconfig['enable_stats_log'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Input(
	'stats_upd_interval',
	'Stats Update Interval',
	'text',
	$pconfig['stats_upd_interval']
))->setHelp('Enter the update interval in seconds for collection and logging of statistics. Default is 10.');

$section->addInput(new Form_Checkbox(
	'append_stats_log',
	'Append Stats Log',
	'Suricata will append-to instead of clearing statistics log file when restarting. Default is Not Checked.',
	$pconfig['append_stats_log'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'enable_http_log',
	'Enable HTTP Log',
	'Suricata will log decoded HTTP traffic for the interface. Default is Checked.',
	$pconfig['enable_http_log'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'append_http_log',
	'Append HTTP Log',
	'Suricata will append-to instead of clearing HTTP log file when restarting. Default is Checked.',
	$pconfig['append_http_log'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'http_log_extended',
	'Log Extended HTTP Info',
	'Suricata will log extended HTTP information. Default is Checked.',
	$pconfig['http_log_extended'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'enable_tls_log',
	'Enable TLS Log',
	'Suricata will log TLS handshake traffic for the interface. Default is Not Checked.',
	$pconfig['enable_tls_log'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'tls_log_extended',
	'Log Extended TLS Info',
	'Suricata will log extended TLS info such as fingerprint. Default is Checked.',
	$pconfig['tls_log_extended'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'enable_json_file_log',
	'Enable Tracked-Files Log',
	'Suricata will log tracked files in JavaScript Object Notation (JSON) format. Default is Not Checked.',
	$pconfig['enable_json_file_log'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'append_json_file_log',
	'Append Tracked-Files Log',
	'Suricata will append-to instead of clearing Tracked Files log file when restarting. Default is Checked.',
	$pconfig['append_json_file_log'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'enable_tracked_files_magic',
	'Enable Logging Magic for Tracked-Files',
	'Suricata will force logging magic on all logged Tracked Files. Default is Not Checked.',
	$pconfig['enable_tracked_files_magic'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'enable_tracked_files_md5',
	'Enable MD5 for Tracked-Files',
	'Suricata will generate MD5 checksums for all logged Tracked Files. Default is Not Checked.',
	$pconfig['enable_tracked_files_md5'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'enable_file_store',
	'Enable File-Store',
	'Suricata will extract and store files from application layer streams. Default is Not Checked. Warning: This will consume a significant amount of disk space on a busy network when enabled.',
	$pconfig['enable_file_store'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'enable_pcap_log',
	'Enable Packet Log',
	'Suricata will log decoded packets for the interface in pcap-format. Default is Not Checked. This can consume a significant amount of disk space when enabled.',
	$pconfig['enable_pcap_log'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Input(
	'max_pcap_log_size',
	'Max Packet Log File Size',
	'text',
	$pconfig['max_pcap_log_size']
))->setHelp('Enter maximum size in MB for a packet log file. Default is 32. When the packet log file size reaches the set limit, it will be rotated and a new one created.');

$section->addInput(new Form_Input(
	'max_pcap_log_files',
	'Max Packet Log Files',
	'text',
	$pconfig['max_pcap_log_files']
))->setHelp('Enter maximum number of packet log files to maintain. Default is 1000. When the number of packet log files reaches the set limit, the oldest file will be overwritten.');

$section->addInput(new Form_Checkbox(
	'enable_eve_log',
	'EVE JSON Log',
	'Suricata will output selected info in JSON format to a single file or to syslog. Default is Not Checked.',
	$pconfig['enable_eve_log'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Select(
	'eve_output_type',
	'EVE Output Type',
	$pconfig['eve_output_type'],
	array("file" => "FILE", "syslog" => "SYSLOG")
))->setHelp('Select EVE log output destination. Choosing FILE is suggested, and is the default value.');

$group = new Form_Group('EVE Logged Info');

$group->add(new Form_Checkbox(
	'eve_log_alerts',
	'Alerts',
	'Alerts',
	$pconfig['eve_log_alerts'] == 'on' ? true:false,
	'on'
));

$group->add(new Form_Checkbox(
	'eve_log_alerts_payload',
	'Alert Payloads',
	'Suricata will log additional payload data with alerts.',
	$pconfig['eve_log_alerts_payload'] == 'on' ? true:false,
	'on'
));

$group->add(new Form_Checkbox(
	'eve_log_http',
	'HTTP Traffic',
	'HTTP Traffic',
	$pconfig['eve_log_http'] == 'on' ? true:false,
	'on'
));

$group->add(new Form_Checkbox(
	'eve_log_dns',
	'DNS Traffic',
	'DNS Traffic',
	$pconfig['eve_log_dns'] == 'on' ? true:false,
	'on'
));

$group->add(new Form_Checkbox(
	'eve_log_tls',
	'TLS Handshakes',
	'TLS Handshakes',
	$pconfig['eve_log_tls'] == 'on' ? true:false,
	'on'
));

$group->add(new Form_Checkbox(
	'eve_log_files',
	'Tracked Files',
	'Tracked Files',
	$pconfig['eve_log_files'] == 'on' ? true:false,
	'on'
));

$group->add(new Form_Checkbox(
	'eve_log_ssh',
	'SSH Handshakes',
	'SSH Handshakes',
	$pconfig['eve_log_ssh'] == 'on' ? true:false,
	'on'
));

$group->setHelp('Choose the information to log via EVE JSON output. Default is All Checked.');

$section->add($group)->addClass('eve_log_info');

$form->add($section);

$section = new Form_Section('Alert and Block Settings');

$section->addInput(new Form_Checkbox(
	'blockoffenders',
	'Block Offenders',
	'Checking this option will automatically block hosts that generate a Suricata alert.',
	$pconfig['blockoffenders'] == 'on' ? true:false,
	'on'
));

$group = new Form_Group('IPS Mode');
$group->add(new Form_Select(
	'ips_mode',
	'IPS Mode',
	$pconfig['ips_mode'],
	array( "ips_mode_legacy" => "Legacy Mode", "ips_mode_inline" => "Inline Mode" )
))->setHelp('Select blocking mode operation.  Legacy Mode inspects copies of packets while Inline Mode inserts the Suricata inspection engine ' . 
		'into the network stack between the NIC and the OS. Default is Legacy Mode.');
$group->setHelp('Legacy Mode uses the PCAP engine to generate copies of packets for inspection as they traverse the interface.  Some "leakage" of packets will occur before ' . 
		'Suricata can determine if the traffic matches a rule and should be blocked.  Inline mode instead intercepts and inspects packets before they are handed ' . 
		'off to the host network stack for further processing.  Packets matching DROP rules are simply discarded (dropped) and not passed to the host ' . 
		'network stack.  No leakage of packets occurs with Inline Mode.  Note that Inline Mode only works with NIC drivers which support Netmap.');
$section->add($group);

$section->addInput(new Form_Checkbox(
	'blockoffenderskill',
	'Kill States',
	'Checking this option will kill firewall states for the blocked IP.  Default is Checked.',
	$pconfig['blockoffenderskill'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Select(
	'blockoffendersip',
	'Which IP to Block',
	$pconfig['blockoffendersip'],
	array( 'src' => 'SRC', 'dst' => 'DST', 'both' => 'BOTH' )
))->setHelp('Select which IP extracted from the packet you wish to block. Choosing BOTH is suggested, and it is the default value.');

$form->add($section);

$section = new Form_Section('Detection Engine Settings');
$section->addInput(new Form_Input(
	'max_pending_packets',
	'Max Pending Packets',
	'text',
	$pconfig['max_pending_packets']
))->setHelp('Enter number of simultaneous packets to process. Default is 1024.<br/>This controls the number simultaneous packets the engine can handle. ' .
			'Setting this higher generally keeps the threads more busy. The minimum value is 1 and the maximum value is 65,000.<br />' .
			'Warning: Setting this too high can lead to degradation and a possible system crash by exhausting available memory.');

$section->addInput(new Form_Select(
	'detect_eng_profile',
	'Detect-Engine Profile',
	$pconfig['detect_eng_profile'],
	array('low' => 'Low', 'medium' => 'Medium', 'high' => 'High')
))->setHelp('Choose a detection engine profile. Default is Medium.<br />MEDIUM is recommended for most systems because it offers a good balance between memory consumption and performance. ' .
			'LOW uses less memory, but it offers lower performance. HIGH consumes a large amount of memory, but it offers the highest performance.');

$section->addInput(new Form_Select(
	'mpm_algo',
	'Pattern Matcher Algorithm',
	$pconfig['mpm_algo'],
	array('ac' => 'AC', 'ac-gfbs' => 'AC-GFBS', 'b2g' => 'B2G', 'b2gc' => 'B2GC', 'b2gm' => 'B2GM', 'b3g' => 'B3G', 'wumanber' => 'WUMANBER')
))->setHelp('Choose a multi-pattern matcher (MPM) algorithm. AC is the default, and is the best choice for almost all systems.	');

$section->addInput(new Form_Select(
	'sgh_mpm_context',
	'Signature Group Header MPM Context',
	$pconfig['sgh_mpm_context'],
	array('auto' => 'Auto', 'full' => 'Full', 'single' => 'Single')
))->setHelp('Choose a Signature Group Header multi-pattern matcher context. Default is Auto.<br />AUTO means Suricata selects between Full and Single based on the MPM algorithm chosen. ' .
			'FULL means every Signature Group has its own MPM context. SINGLE means all Signature Groups share a single MPM context. Using FULL can improve performance at the expense of significant memory consumption.');

$section->addInput(new Form_Input(
	'inspect_recursion_limit',
	'Inspection Recursion Limit',
	'text',
	$pconfig['inspect_recursion_limit']
))->setHelp('Enter limit for recursive calls in content inspection code. Default is 3000.<br />When set to 0 an internal default is used. When left blank there is no recursion limit.');

$section->addInput(new Form_Checkbox(
	'delayed_detect',
	'Delayed Detect',
	'Suricata will build list of signatures after packet capture threads have started. Default is Not Checked.',
	$pconfig['delayed_detect'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'intf_promisc_mode',
	'Promiscuous Mode',
	'Suricata will place the monitored interface in promiscuous mode when checked. Default is Checked.',
	$pconfig['intf_promisc_mode'] == 'on' ? true:false,
	'on'
));

$form->add($section);

$section = new Form_Section('Networks Suricata Should Inspect and Protect');

$group = new Form_Group('Home Net');

$group->add(new Form_Select(
	'homelistname',
	'Home Net',
	$pconfig['homelistname'],
	suricata_get_config_lists('passlist')
))->setHelp('Choose the Home Net you want this interface to use.');

$group->add(new Form_Button(
	'btnHomeNet',
	' ' . 'View List',
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
	suricata_get_config_lists('passlist')
))->setHelp('Choose the External Net you want this interface to use.');

$group->add(new Form_Button(
	'btnExternalNet',
	' ' . 'View List',
	'#',
	'fa-file-text-o'
))->removeClass('btn-primary')->addClass('btn-info')->addClass('btn-sm')->setAttribute('data-target', '#externalnet')->setAttribute('data-toggle', 'modal');

$group->setHelp('External Net is networks that are not Home Net.  Most users should leave this setting at default.' . '<br />' .
		'Create a Pass List and add an Alias to it, and then assign the Pass List here for custom External Net settings.');

$section->add($group);

$group = new Form_Group('Pass List');

$group->addClass('passlist');

$group->add(new Form_Select(
	'passlistname',
	'Pass List',
	$pconfig['passlistname'],
	suricata_get_config_lists('passlist')
))->setHelp('Choose the Pass List you want this interface to use. Addresses in a Pass List are never blocked. ');

$group->add(new Form_Button(
	'btnPasslist',
	' ' . 'View List',
	'#',
	'fa-file-text-o'
))->removeClass('btn-primary')->addClass('btn-info')->addClass('btn-sm')->setAttribute('data-target', '#passlist')->setAttribute('data-toggle', 'modal');

$group->setHelp('The default Pass List adds local networks, WAN IPs, Gateways, VPNs and VIPs.  Create an Alias to customize.' . '<br />' .
				'This option will only be used when block offenders is on.');

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
))->removeClass('form-control')
  ->addClass('row-fluid col-sm-10')
  ->setAttribute('rows', '10')
  ->setAttribute('wrap', 'off');

$form->add($modal);

// Add view PASS_LIST modal pop-up
$modal = new Modal('View PASS LIST', 'passlist', 'large', 'Close');

$modal->addInput(new Form_Textarea (
	'passlist_text',
	'',
	'...Loading...'
))->removeClass('form-control')
  ->addClass('row-fluid col-sm-10')
  ->setAttribute('rows', '10')
  ->setAttribute('wrap', 'off');

$form->add($modal);

$section = new Form_Section('Alert Suppression and Filtering');
$group = new Form_Group('Alert Suppression and Filtering');
$group->add(new Form_Select(
	'suppresslistname',
	'Alert Suppression and Filtering',
	$pconfig['suppresslistname'],
	suricata_get_config_lists('suppress')
))->setHelp('Choose the suppression or filtering file you want this interface to use. Default option disables suppression and filtering.');

$group->add(new Form_Button(
	'btnSuppressList',
	' ' . 'View List',
	'#',
	'fa-file-text-o'
))->removeClass('btn-primary')
  ->addClass('btn-info btn-sm')
  ->setAttribute('data-target', '#suppresslist')
  ->setAttribute('data-toggle', 'modal');

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

$section = new Form_Section('Arguments here will be automatically inserted into the Suricata configuration');
$section->addInput(new Form_Textarea (
	'configpassthru',
	'Advanced Configuration Pass-Through',
	$pconfig['configpassthru']
))->setHelp('Enter any additional configuration parameters to add to the Suricata configuration here, separated by a newline');

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
print($form);
?>

<div class="infoblock">
	<?=print_info_box('<strong>Note:</strong> Please save your settings before you attempt to start Suricata.', info)?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

	function enable_blockoffenders() {
		var hide = ! $('#blockoffenders').prop('checked');
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

	function toggle_dns_log() {
		var hide = ! $('#enable_dns_log').prop('checked');
		hideCheckbox('append_dns_log', hide);
	}

	function toggle_stats_log() {
		var hide = ! $('#enable_stats_log').prop('checked');
		hideInput('stats_upd_interval', hide);
		hideCheckbox('append_stats_log', hide);
	}

	function toggle_http_log() {
		var hide = ! $('#enable_http_log').prop('checked');
		hideCheckbox('append_http_log', hide);
		hideCheckbox('http_log_extended', hide);
	}

	function toggle_tls_log() {
		var hide = ! $('#enable_tls_log').prop('checked');
		hideCheckbox('tls_log_extended', hide);
	}

	function toggle_json_file_log() {
		var hide = ! $('#enable_json_file_log').prop('checked');
		hideCheckbox('append_json_file_log', hide);
		hideCheckbox('enable_tracked_files_magic', hide);
		hideCheckbox('enable_tracked_files_md5', hide);
	}

	function toggle_pcap_log() {
		var hide = ! $('#enable_pcap_log').prop('checked');
		hideInput('max_pcap_log_size', hide);
		hideInput('max_pcap_log_files', hide);
	}

	function toggle_eve_log() {
		var hide = ! $('#enable_eve_log').prop('checked');
		hideSelect('eve_output_type', hide);
		hideClass('eve_log_info',hide);
	}

	function enable_change() {
		var disable = ! $('#enable').prop('checked');

		disableInput('alertsystemlog', disable);
		disableInput('alertsystemlog_facility', disable);
		disableInput('alertsystemlog_priority', disable);
		disableInput('blockoffenders', disable);
		disableInput('ips_mode', disable);
		disableInput('blockoffenderskill', disable);
		disableInput('blockoffendersip', disable);
		disableInput('performance', disable);
		disableInput('max_pending_packets', disable);
		disableInput('detect_eng_profile', disable);
		disableInput('inspect_recursion_limit', disable);
		disableInput('mpm_algo', disable);
		disableInput('sgh_mpm_context', disable);
		disableInput('delayed_detect', disable);
		disableInput('intf_promisc_mode', disable);
		disableInput('fpm_split_any_any', disable);
		disableInput('fpm_search_optimize', disable);
		disableInput('fpm_no_stream_inserts', disable);
		disableInput('cksumcheck', disable);
		disableInput('externallistname', disable);
		disableInput('homelistname', disable);
		disableInput('suppresslistname', disable);
		disableInput('btnHomeNet', disable);
		disableInput('btnExternalNet', disable);
		disableInput('btnSuppressList', disable);
		disableInput('passlistname', disable);
		disableInput('btnPasslist', disable);
		disableInput('configpassthru', disable);
		disableInput('enable_dns_log', disable);
		disableInput('append_dns_log', disable);
		disableInput('enable_stats_log', disable);
		disableInput('stats_upd_interval', disable);
		disableInput('append_stats_log', disable);
		disableInput('enable_http_log', disable);
		disableInput('append_http_log', disable);
		disableInput('http_log_extended', disable);
		disableInput('enable_tls_log', disable);
		disableInput('tls_log_extended', disable);
		disableInput('enable_json_file_log', disable);
		disableInput('append_json_file_log', disable);
		disableInput('enable_tracked_files_magic', disable);
		disableInput('enable_tracked_files_md5', disable);
		disableInput('enable_file_store', disable);
		disableInput('enable_pcap_log', disable);
		disableInput('max_pcap_log_size', disable);
		disableInput('max_pcap_log_files', disable);
		disableInput('enable_eve_log', disable);
		disableInput('eve_output_type', disable);
		disableInput('eve_log_info', disable);
		disableInput('eve_log_alerts', disable);
		disableInput('eve_log_alerts_payload', disable);
		disableInput('eve_log_http', disable);
		disableInput('eve_log_dns', disable);
		disableInput('eve_log_tls', disable);
		disableInput('eve_log_files', disable);
		disableInput('eve_log_ssh', disable);
	}

	// Call the list viewing page and write what it returns to the modal text area
	function getListContents(listName, listType, ctrlID) {
		var ajaxRequest;

		ajaxRequest = $.ajax({
			url: "/suricata/suricata_list_view.php",
			type: "post",
			data: { ajax: 	"ajax",
			        wlist: 	listName,
					type: 	listType,
					id: 	"<?=$id?>"
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

	$('#passlist').on('shown.bs.modal', function() {
		getListContents($('#passlistname option:selected' ).text(), 'passlist', 'passlist_text');
	});

	$('#suppresslist').on('shown.bs.modal', function() {
		getListContents($('#suppresslistname option:selected').text(), 'suppress', 'suppresslist_text');
	});

	// ---------- Click checkbox handlers ---------------------------------------------------------

	/* When form control id is clicked, disable/enable it's associated form controls */

	$('#enable').click(function() {
		enable_change();
	});

	$('#alertsystemlog').click(function() {
		toggle_system_log();
	});

	$('#enable_dns_log').click(function() {
		toggle_dns_log();
	});

	$('#enable_stats_log').click(function() {
		toggle_stats_log();
	});

	$('#enable_http_log').click(function() {
		toggle_http_log();
	});

	$('#enable_tls_log').click(function() {
		toggle_tls_log();
	});

	$('#enable_json_file_log').click(function() {
		toggle_json_file_log();
	});

	$('#enable_pcap_log').click(function() {
		toggle_pcap_log();
	});

	$('#enable_eve_log').click(function() {
		toggle_eve_log();
	});

	$('#blockoffenders').click(function() {
		enable_blockoffenders();
	});

	$('#ips_mode').on('change', function() {
		if ($('#ips_mode').val() == 'ips_mode_inline') {
			hideCheckbox('blockoffenderskill', true);
			hideSelect('blockoffendersip', true);
			hideClass('passlist', true);
		}
		else {
			hideCheckbox('blockoffenderskill', false);
			hideSelect('blockoffendersip', false);
			hideClass('passlist', false);
		}
	});

	// ---------- On initial page load ------------------------------------------------------------
	enable_change();
	enable_blockoffenders();
	toggle_system_log();
	toggle_dns_log();
	toggle_stats_log();
	toggle_http_log();
	toggle_tls_log();
	toggle_json_file_log();
	toggle_pcap_log();
	toggle_eve_log();

});
//]]>
</script>

<?php include("foot.inc"); ?>
