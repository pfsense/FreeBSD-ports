<?php
/*
 * suricata_interfaces_edit.php
 *
 * Significant portions of this code are based on original work done
 * for the Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
 * All rights reserved.
 *
 * Adapted for Suricata by:
 * Copyright (C) 2014 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:

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

if ($_POST["save"] && !$input_errors) {
	if (!isset($_POST['interface']))
		$input_errors[] = gettext("Choosing an Interface is mandatory!");

	/* See if assigned interface is already in use */
	if (isset($_POST['interface'])) {
		foreach ($a_rule as $k => $v) {
			if (($v['interface'] == $_POST['interface']) && ($id <> $k)) {
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
		if ($_POST['blockoffenderskill'] == "on") $natent['blockoffenderskill'] = 'on'; else unset($natent['blockoffenderskill']);
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

			$natent['stream_memcap'] = '33554432';
			$natent['stream_prealloc_sessions'] = '32768';
			$natent['reassembly_memcap'] = '67108864';
			$natent['reassembly_depth'] = '1048576';
			$natent['reassembly_to_server_chunk'] = '2560';
			$natent['reassembly_to_client_chunk'] = '2560';
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

$if_friendly = convert_friendly_interface_to_friendly_descr($pconfig['interface']);
$pgtitle = gettext("Suricata: Interface {$if_friendly} - Edit Settings");
include_once("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc");?>

<form action="suricata_interfaces_edit.php<?php echo "?id=$id";?>" method="post" name="iform" id="iform">
<input name="id" type="hidden" value="<?=$id;?>"/>
<input name="action" type="hidden" value="<?=$action;?>"/>

<?php
/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tbody>
<tr><td>
<?php
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
	echo '</td></tr>';
	echo '<tr><td class="tabnavtbl">';
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
?>
</td></tr>
<tr><td><div id="mainarea">
<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
	<tbody>
	<tr>
		<td colspan="2" class="listtopic"><?php echo gettext("General Settings"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?php echo gettext("Enable"); ?></td>
		<td width="78%" class="vtable">
			<input name="enable" type="checkbox" value="on" <?php if ($pconfig['enable'] == "on") echo "checked"; ?> onClick="enable_change(false)"/> 
			<?php echo gettext("Checking this box enables Suricata inspection on the interface."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?php echo gettext("Interface"); ?></td>
		<td width="78%" class="vtable">
			<select name="interface" class="formselect" tabindex="0">
		<?php
			foreach ($interfaces as $iface => $ifacename): ?>
				<option value="<?=$iface;?>"
			<?php if ($iface == $pconfig['interface']) echo " selected"; ?>><?=htmlspecialchars($ifacename);?>
				</option>
			<?php endforeach; ?>
			</select>&nbsp;&nbsp;
			<span class="vexpl"><?php echo gettext("Choose which interface this Suricata instance applies to."); ?><br/>
			<span class="red"><?php echo gettext("Hint:"); ?></span>&nbsp;<?php echo gettext("In most cases, you'll want to use WAN here if this is the first Suricata-configured interface."); ?></span><br/></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?php echo gettext("Description"); ?></td>
		<td width="78%" class="vtable"><input name="descr" type="text" 
		class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']); ?>"/> <br/>
		<span class="vexpl"><?php echo gettext("Enter a meaningful description here for your reference.  The default is the interface name."); ?></span><br/></td>
	</tr>
<tr>
	<td colspan="2" class="listtopic"><?php echo gettext("Logging Settings"); ?></td>
</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Send Alerts to System Log"); ?></td>
		<td width="78%" class="vtable"><input name="alertsystemlog" type="checkbox" value="on" onclick="toggle_system_log();" <?php if ($pconfig['alertsystemlog'] == "on") echo "checked"; ?>/>
			<?php echo gettext("Suricata will send Alerts from this interface to the firewall's system log."); ?></td>
	</tr>
	<tbody id="alertsystemlog_rows">
		<tr>
			<td width="22%" valign="top" class="vncell"><?php echo gettext("Log Facility"); ?></td>
			<td width="78%" class="vtable">
				<select name="alertsystemlog_facility" id="alertsystemlog_facility" class="formselect">
				<?php
					$log_facility = array(  "auth", "authpriv", "daemon", "kern", "security", "syslog", "user", "local0",
								"local1", "local2", "local3", "local4", "local5", "local6", "local7" );
					foreach ($log_facility as $facility) {
						$selected = "";
						if ($facility == $pconfig['alertsystemlog_facility'])
							$selected = " selected";
						echo "<option value='{$facility}'{$selected}>" . $facility . "</option>\n";
					}
				?></select>&nbsp;&nbsp;
				<?php echo gettext("Select system log Facility to use for reporting.  Default is ") . "<strong>" . gettext("local1") . "</strong>."; ?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?php echo gettext("Log Priority"); ?></td>
			<td width="78%" class="vtable">
				<select name="alertsystemlog_priority" id="alertsystemlog_priority" class="formselect">
				<?php
					$log_priority = array( "emerg", "crit", "alert", "err", "warning", "notice", "info" );
					foreach ($log_priority as $priority) {
						$selected = "";
						if ($priority == $pconfig['alertsystemlog_priority'])
							$selected = " selected";
						echo "<option value='{$priority}'{$selected}>" . $priority . "</option>\n";
					}
				?></select>&nbsp;&nbsp;
				<?php echo gettext("Select system log Priority (Level) to use for reporting.  Default is ") . "<strong>" . gettext("notice") . "</strong>."; ?>
			</td>
		</tr>
	</tbody>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable DNS Log"); ?></td>
		<td width="78%" class="vtable"><input name="enable_dns_log" type="checkbox" value="on" <?php if ($pconfig['enable_dns_log'] == "on") echo "checked"; ?> 
			onClick="toggle_dns_log();" id="enable_dns_log"/>
			<?php echo gettext("Suricata will log DNS requests and replies for the interface.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?>
		</td>
	</tr>
	<tr id="dns_log_append_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Append DNS Log"); ?></td>
		<td width="78%" class="vtable"><input name="append_dns_log" type="checkbox" value="on" <?php if ($pconfig['append_dns_log'] == "on") echo "checked"; ?>/>
			<?php echo gettext("Suricata will append-to instead of clearing DNS log file when restarting.  Default is ") . "<strong>" . gettext("Checked") . "</strong>."; ?></td>
	</tr>

	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable Stats Log"); ?></td>
		<td width="78%" class="vtable"><input name="enable_stats_log" type="checkbox" value="on" <?php if ($pconfig['enable_stats_log'] == "on") echo "checked"; ?> 
			onClick="toggle_stats_log();" id="enable_stats_log"/>
			<?php echo gettext("Suricata will periodically log statistics for the interface.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?>
			<div id="stats_log_warning" style="display: none;"><br/><span class="red"><strong><?php echo gettext("Warning: ") . "</strong></span>" . 
			gettext("The stats log file can become quite large, especially when append mode is enabled!"); ?></div></td>
	</tr>
	<tr id="stats_interval_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Stats Update Interval"); ?></td>
		<td width="78%" class="vtable"><input name="stats_upd_interval" type="text" 
			class="formfld unknown" id="stats_upd_interval" size="8" value="<?=htmlspecialchars($pconfig['stats_upd_interval']); ?>"/>&nbsp;
			<?php echo gettext("Enter the update interval in ") . "<strong>" . gettext("seconds") . "</strong>" . gettext(" for stats updating.  Default is ") . "<strong>" . 
			gettext("10") . "</strong>."; ?><br/><?php echo gettext("Sets the update interval, in seconds, for the collection and logging of statistics.") ?></td>
	</tr>
	<tr id="stats_log_append_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Append Stats Log"); ?></td>
		<td width="78%" class="vtable"><input name="append_stats_log" type="checkbox" value="on" <?php if ($pconfig['append_stats_log'] == "on") echo "checked"; ?>/>
			<?php echo gettext("Suricata will append-to instead of clearing statistics log file when restarting.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable HTTP Log"); ?></td>
		<td width="78%" class="vtable"><input name="enable_http_log" type="checkbox" value="on" <?php if ($pconfig['enable_http_log'] == "on") echo "checked"; ?> 
			onClick="toggle_http_log()" id="enable_http_log"/>
			<?php echo gettext("Suricata will log decoded HTTP traffic for the interface.  Default is ") . "<strong>" . gettext("Checked") . "</strong>."; ?></td>
	</tr>
	<tr id="http_log_append_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Append HTTP Log"); ?></td>
		<td width="78%" class="vtable"><input name="append_http_log" type="checkbox" value="on" <?php if ($pconfig['append_http_log'] == "on") echo "checked"; ?>/>
			<?php echo gettext("Suricata will append-to instead of clearing HTTP log file when restarting.  Default is ") . "<strong>" . gettext("Checked") . "</strong>."; ?></td>
	</tr>
	<tr id="http_log_extended_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Log Extended HTTP Info"); ?></td>
		<td width="78%" class="vtable"><input name="http_log_extended" type="checkbox" value="on" <?php if ($pconfig['http_log_extended'] == "on") echo "checked"; ?>/>
			<?php echo gettext("Suricata will log extended HTTP information.  Default is ") . "<strong>" . gettext("Checked") . "</strong>."; ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable TLS Log"); ?></td>
		<td width="78%" class="vtable"><input name="enable_tls_log" type="checkbox" value="on" <?php if ($pconfig['enable_tls_log'] == "on") echo "checked"; ?> 
			onClick="toggle_tls_log()" id="enable_tls_log"/>
			<?php echo gettext("Suricata will log TLS handshake traffic for the interface.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?></td>
	</tr>
	<tr id="tls_log_extended_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Log Extended TLS Info"); ?></td>
		<td width="78%" class="vtable"><input name="tls_log_extended" type="checkbox" value="on" <?php if ($pconfig['tls_log_extended'] == "on") echo "checked"; ?>/>
			<?php echo gettext("Suricata will log extended TLS info such as fingerprint.  Default is ") . "<strong>" . gettext("Checked") . "</strong>."; ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable Tracked-Files Log"); ?></td>
		<td width="78%" class="vtable"><input name="enable_json_file_log" type="checkbox" value="on" <?php if ($pconfig['enable_json_file_log'] == "on") echo "checked"; ?> 
			onClick="toggle_json_file_log()" id="enable_json_file_log"/>
			<?php echo gettext("Suricata will log tracked files in JavaScript Object Notation (JSON) format.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?></td>
	</tr>
	<tr id="tracked_files_append_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Append Tracked-Files Log"); ?></td>
		<td width="78%" class="vtable"><input name="append_json_file_log" type="checkbox" value="on" <?php if ($pconfig['append_json_file_log'] == "on") echo "checked"; ?> 
			id="append_json_file_log"/>
			<?php echo gettext("Suricata will append-to instead of clearing Tracked Files log file when restarting.  Default is ") . "<strong>" . gettext("Checked") . "</strong>."; ?></td>
	</tr>
	<tr id="tracked_files_magic_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable Logging Magic for Tracked-Files"); ?></td>
		<td width="78%" class="vtable"><input name="enable_tracked_files_magic" type="checkbox" value="on" <?php if ($pconfig['enable_tracked_files_magic'] == "on") echo "checked"; ?> 
			id="enable_tracked_files_magic"/>
			<?php echo gettext("Suricata will force logging magic on all logged Tracked Files.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?></td>
	</tr>
	<tr id="tracked_files_md5_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable MD5 for Tracked-Files"); ?></td>
		<td width="78%" class="vtable"><input name="enable_tracked_files_md5" type="checkbox" value="on" <?php if ($pconfig['enable_tracked_files_md5'] == "on") echo "checked"; ?> 
			id="enable_tracked_files_md5"/>
			<?php echo gettext("Suricata will generate MD5 checksums for all logged Tracked Files.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable File-Store"); ?></td>
		<td width="78%" class="vtable"><input name="enable_file_store" type="checkbox" value="on" <?php if ($pconfig['enable_file_store'] == "on") echo "checked"; ?> 
		onClick="toggle_file_store()" id="enable_file_store"/>
			<?php echo gettext("Suricata will extract and store files from application layer streams.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?>
			<div id="file_store_warning" style="display: none;"><br/><span class="red"><strong><?php echo gettext("Warning: ") . "</strong></span>" . 
			gettext("This will consume a significant amount of disk space on a busy network when enabled!"); ?></div>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable Packet Log"); ?></td>
		<td width="78%" class="vtable"><input name="enable_pcap_log" id="enable_pcap_log" type="checkbox" value="on" <?php if ($pconfig['enable_pcap_log'] == "on") echo "checked"; ?> 
			onClick="toggle_pcap_log()"/>
			<?php echo gettext("Suricata will log decoded packets for the interface in pcap-format.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?>
			<div id="file_pcap_warning" style="display: none;"><br/><span class="red"><strong><?php echo gettext("Warning: ") . "</strong></span>" . 
			gettext("This can consume a significant amount of disk space when enabled!"); ?></div>
		</td>
	</tr>
	<tr id="pcap_log_size_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Max Packet Log File Size"); ?></td>
		<td width="78%" class="vtable"><input name="max_pcap_log_size" type="text" 
			class="formfld unknown" id="max_pcap_log_size" size="8" value="<?=htmlspecialchars($pconfig['max_pcap_log_size']); ?>"/>&nbsp;
			<?php echo gettext("Enter maximum size in ") . "<strong>" . gettext("MB") . "</strong>" . gettext(" for a packet log file.  Default is ") . "<strong>" . 
			gettext("32") . "</strong>."; ?><br/><br/><?php echo gettext("When the packet log file size reaches the set limit, it will be rotated and a new one created.") ?></td>
	</tr>
	<tr id="pcap_log_max_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Max Packet Log Files"); ?></td>
		<td width="78%" class="vtable"><input name="max_pcap_log_files" type="text" 
			class="formfld unknown" id="max_pcap_log_files" size="8" value="<?=htmlspecialchars($pconfig['max_pcap_log_files']); ?>"/>&nbsp;
			<?php echo gettext("Enter maximum number of packet log files to maintain.  Default is ") . "<strong>" . 
			gettext("1000") . "</strong>."; ?><br/><br/><?php echo gettext("When the number of packet log files reaches the set limit, the oldest file will be overwritten.") ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("EVE JSON Log"); ?></td>
		<td width="78%" class="vtable"><input name="enable_eve_log" id="enable_eve_log" type="checkbox" value="on" <?php if ($pconfig['enable_eve_log'] == "on") echo "checked"; ?> 
			onClick="toggle_eve_log()"/>
			<?php echo gettext("Suricata will output selected info in JSON format to a single file or to syslog.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?>
			<div id="file_eve_warning" style="display: none;"><br/><span class="red"><strong><?php echo gettext("Warning: ") . "</strong></span>" . 
			gettext("This can consume a significant amount of disk space when enabled!"); ?></div>
		</td>
	</tr>
	<tbody id="eve_log_option_rows">
		<tr>
			<td width="22%" valign="top" class="vncell"><?php echo gettext("EVE Output Type"); ?></td>
			<td width="78%" class="vtable">
				<select name="eve_output_type" class="formselect" id="eve_output_type"  onChange="eveOutSelect();" >
				<?php
					foreach (array("file", "syslog") as $btype) {
						if ($btype == $pconfig['eve_output_type'])
							echo "<option value='{$btype}' selected>";
						else
							echo "<option value='{$btype}'>";
						echo htmlspecialchars($btype) . '</option>';
					}
				?> 
				</select>&nbsp;&nbsp;
				<?php echo gettext("Select EVE log output destination."); ?><br/>
				<span class="red"><?php echo gettext("Hint:") . "</span>&nbsp;" . gettext("Choosing FILE is suggested, and is the default value."); ?><br/>
			</td>
		</tr>
		<tr id="eve_systemlog_facility_row">
			<td width="22%" valign="top" class="vncell"><?php echo gettext("EVE Syslog Facility"); ?></td>
			<td width="78%" class="vtable">
				<select name="eve_systemlog_facility" id="eve_systemlog_facility" class="formselect">
				<?php
					$log_facility = array(  "auth", "authpriv", "daemon", "kern", "security", "syslog", "user", "local0",
								"local1", "local2", "local3", "local4", "local5", "local6", "local7" );
					foreach ($log_facility as $facility) {
						$selected = "";
						if ($facility == $pconfig['eve_systemlog_facility'])
							$selected = " selected";
						echo "<option value='{$facility}'{$selected}>" . $facility . "</option>\n";
					}
				?></select>&nbsp;&nbsp;
				<?php echo gettext("Select system log Facility to use for reporting by EVE.  Default is ") . "<strong>" . gettext("local1") . "</strong>."; ?>
			</td>
		</tr>
		<tr id="eve_systemlog_priority_row">
			<td width="22%" valign="top" class="vncell"><?php echo gettext("EVE Syslog Priority"); ?></td>
			<td width="78%" class="vtable">
				<select name="eve_systemlog_priority" id="eve_systemlog_priority" class="formselect">
				<?php
					$log_priority = array( "emerg", "crit", "alert", "err", "warning", "notice", "info" );
					foreach ($log_priority as $priority) {
						$selected = "";
						if ($priority == $pconfig['eve_systemlog_priority'])
							$selected = " selected";
						echo "<option value='{$priority}'{$selected}>" . $priority . "</option>\n";
					}
				?></select>&nbsp;&nbsp;
				<?php echo gettext("Select system log Priority (Level) to use for reporting by EVE.  Default is ") . "<strong>" . gettext("notice") . "</strong>."; ?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?php echo gettext("EVE Logged Info"); ?></td>
			<td width="78%" class="vtable"><?php echo gettext("Choose the information to log via EVE JSON output.  Default is ") . "<strong>" . gettext("All Checked") . "</strong>."; ?><br/>
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tbody>
					<tr>
						<td class="vexpl"><input name="eve_log_alerts" id="eve_log_alerts" type="checkbox" value="on" 
							<?php if ($pconfig['eve_log_alerts'] == "on") echo "checked"; ?>/>Alerts
						</td>
						<td class="vexpl"><input name="eve_log_http" id="eve_log_http" type="checkbox" value="on" 
							<?php if ($pconfig['eve_log_http'] == "on") echo "checked"; ?>/>HTTP Traffic
						</td>
						<td class="vexpl"><input name="eve_log_dns" id="eve_log_dns" type="checkbox" value="on" 
							<?php if ($pconfig['eve_log_dns'] == "on") echo "checked"; ?>/>DNS Requests/Replies
						</td>
					</tr>
					<tr>
						<td class="vexpl"><input name="eve_log_tls" id="eve_log_tls" type="checkbox" value="on" onClick="toggle_eve_tls();" 
							<?php if ($pconfig['eve_log_tls'] == "on") echo "checked"; ?>/>TLS Handshakes
						</td>
						<td class="vexpl"><input name="eve_log_files" id="eve_log_files" type="checkbox" value="on" 
							<?php if ($pconfig['eve_log_files'] == "on") echo "checked"; ?>/>Tracked Files
						</td>
						<td class="vexpl"><input name="eve_log_ssh" id="eve_log_ssh" type="checkbox" value="on" 
							<?php if ($pconfig['eve_log_ssh'] == "on") echo "checked"; ?>/>SSH Handshakes
						</td>
					</tr>
					</tbody>
				</table>
			</td>
		</tr>
	</tbody>
<tr>
	<td colspan="2" class="listtopic"><?php echo gettext("Alert Settings"); ?></td>
</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Block Offenders"); ?></td>
		<td width="78%" class="vtable">
			<input name="blockoffenders" id="blockoffenders" type="checkbox" value="on"
			<?php if ($pconfig['blockoffenders'] == "on") echo "checked"; ?>
			onClick="enable_blockoffenders()"/>
			<?php echo gettext("Checking this option will automatically block hosts that generate a " . "Suricata alert."); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Kill States"); ?></td>
		<td width="78%" class="vtable">
			<input name="blockoffenderskill" id="blockoffenderskill" type="checkbox" value="on" <?php if ($pconfig['blockoffenderskill'] == "on") echo "checked"; ?>/>
			<?php echo gettext("Checking this option will kill firewall states for the blocked IP."); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Which IP to Block"); ?></td>
		<td width="78%" class="vtable">
			<select name="blockoffendersip" class="formselect" id="blockoffendersip">
			<?php
				foreach (array("src", "dst", "both") as $btype) {
					if ($btype == $pconfig['blockoffendersip'])
						echo "<option value='{$btype}' selected>";
					else
						echo "<option value='{$btype}'>";
					echo htmlspecialchars($btype) . '</option>';
				}
			?>
			</select>&nbsp;&nbsp;
			<?php echo gettext("Select which IP extracted from the packet you wish to block."); ?><br/>
			<span class="red"><?php echo gettext("Hint:") . "</span>&nbsp;" . gettext("Choosing BOTH is suggested, and it is the default value."); ?><br/>
		</td>
	</tr>
<tr>
	<td colspan="2" class="listtopic"><?php echo gettext("Detection Engine Settings"); ?></td>
</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Max Pending Packets"); ?></td>
		<td width="78%" class="vtable"><input name="max_pending_packets" type="text" 
			class="formfld unknown" id="max_pending_packets" size="8" value="<?=htmlspecialchars($pconfig['max_pending_packets']); ?>"/>&nbsp;
			<?php echo gettext("Enter number of simultaneous packets to process.  Default is ") . "<strong>" . 
			gettext("1024") . "</strong>."; ?><br/><br/><?php echo gettext("This controls the number simultaneous packets the engine can handle. ") . 
			gettext("Setting this higher generally keeps the threads more busy. The minimum value is 1 and the maximum value is 65,000. ") . "<br/><span class='red'><strong>" . 
			gettext("Warning: ") . "</strong></span>" . gettext("Setting this too high can lead to degradation and a possible system crash by exhausting available memory.") ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Detect-Engine Profile"); ?></td>
		<td width="78%" class="vtable">
			<select name="detect_eng_profile" class="formselect" id="detect_eng_profile">
				<?php
					$interfaces2 = array('low' => 'Low', 'medium' => 'Medium', 'high' => 'High');
					foreach ($interfaces2 as $iface2 => $ifacename2): ?>
					<option value="<?=$iface2;?>"
					<?php if ($iface2 == $pconfig['detect_eng_profile']) echo "selected"; ?>>
					<?=htmlspecialchars($ifacename2);?></option>
					<?php endforeach; ?>
			</select>&nbsp;&nbsp;
			<?php echo gettext("Choose a detection engine profile. ") . "<strong>" . gettext("Default") . 
			"</strong>" . gettext(" is ") . "<strong>" . gettext("Medium") . "</strong>"; ?>.<br/><br/>
			<?php echo gettext("MEDIUM is recommended for most systems because it offers a good " . 
			"balance between memory consumption and performance.  LOW uses less memory, but it offers lower performance.  " . 
			"HIGH consumes a large amount of memory, but it offers the highest performance."); ?>
			<br/></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Pattern Matcher Algorithm"); ?></td>
		<td width="78%" class="vtable">
			<select name="mpm_algo" class="formselect" id="mpm_algo">
				<?php
					$interfaces2 = array('ac' => 'AC', 'ac-gfbs' => 'AC-GFBS', 'b2g' => 'B2G',
							     'b2gc' => 'B2GC', 'b2gm' => 'B2GM', 'b3g' => 'B3G', 'wumanber' => 'WUMANBER');
					foreach ($interfaces2 as $iface2 => $ifacename2): ?>
					<option value="<?=$iface2;?>"
					<?php if ($iface2 == $pconfig['mpm_algo']) echo "selected"; ?>>
					<?=htmlspecialchars($ifacename2);?></option>
				<?php endforeach; ?>
			</select>&nbsp;&nbsp;
			<?php echo gettext("Choose a multi-pattern matcher (MPM) algorithm. ") . "<strong>" . gettext("Default") . 
			"</strong>" . gettext(" is ") . "<strong>" . gettext("AC") . "</strong>"; ?>.<br/><br/>
			<?php echo gettext("AC is the default, and is the best choice for almost all systems."); ?>
			<br/></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Signature Group Header MPM Context"); ?></td>
		<td width="78%" class="vtable">
			<select name="sgh_mpm_context" class="formselect" id="sgh_mpm_context">
				<?php
					$interfaces2 = array('auto' => 'Auto', 'full' => 'Full', 'single' => 'Single');
					foreach ($interfaces2 as $iface2 => $ifacename2): ?>
					<option value="<?=$iface2;?>"
					<?php if ($iface2 == $pconfig['sgh_mpm_context']) echo "selected"; ?>>
					<?=htmlspecialchars($ifacename2);?></option>
					<?php endforeach; ?>
			</select>&nbsp;&nbsp;
			<?php echo gettext("Choose a Signature Group Header multi-pattern matcher context. ") . "<strong>" . gettext("Default") . 
			"</strong>" . gettext(" is ") . "<strong>" . gettext("Auto") . "</strong>"; ?>.<br/><br/>
			<?php echo gettext("AUTO means Suricata selects between Full and Single based on the MPM algorithm " . 
			"chosen.  FULL means every Signature Group has its own MPM context.  SINGLE means all Signature Groups share a single MPM " . 
			"context.  Using FULL can improve performance at the expense of significant memory consumption."); ?>
			<br/></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Inspection Recursion Limit"); ?></td>
		<td width="78%" class="vtable"><input name="inspect_recursion_limit" type="text" 
			class="formfld unknown" id="inspect_recursion_limit" size="8" value="<?=htmlspecialchars($pconfig['inspect_recursion_limit']); ?>"/>&nbsp;
			<?php echo gettext("Enter limit for recursive calls in content inspection code.  Default is ") . "<strong>" . 
			gettext("3000") . "</strong>."; ?><br/><br/><?php echo gettext("When set to 0 an internal default is used.  When left blank there is no recursion limit.") ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Delayed Detect"); ?></td>
		<td width="78%" class="vtable">
			<input name="delayed_detect" id="delayed_detect" type="checkbox" value="on"
			<?php if ($pconfig['delayed_detect'] == "on") echo " checked"; ?>/>
			<?php echo gettext("Suricata will build list of signatures after packet capture threads have started.  Default is ") . 
			"<strong>" . gettext("Not Checked") . "</strong>."; ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Promiscuous Mode"); ?></td>
		<td width="78%" class="vtable">
			<input name="intf_promisc_mode" id="intf_promisc_mode" type="checkbox" value="on"
			<?php if ($pconfig['intf_promisc_mode'] == "on") echo " checked"; ?>/>
			<?php echo gettext("Suricata will place the monitored interface in promiscuous mode when checked.  Default is ") . 
			"<strong>" . gettext("Checked") . "</strong>."; ?></td>
	</tr>
	<tr>
		<td colspan="2" class="listtopic"><?php echo gettext("Networks " . "Suricata Should Inspect and Protect"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Home Net"); ?></td>
		<td width="78%" class="vtable">
			<select name="homelistname" class="formselect" id="homelistname">
				<?php
					echo "<option value='default' >default</option>";
					/* find Pass List names and filter by type */
					if (is_array($suricataglob['passlist']['item'])) {
						foreach ($suricataglob['passlist']['item'] as $value) {
							$ilistname = $value['name'];
							if ($ilistname == $pconfig['homelistname'])
								echo "<option value='$ilistname' selected>";
							else
								echo "<option value='$ilistname'>";
							echo htmlspecialchars($ilistname) . '</option>';
						}
					}
				?>
			</select>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="button" class="formbtns" value="View List"  
			onclick="viewList('<?=$id;?>','homelistname','homenet')" id="btnHomeNet" 
			title="<?php echo gettext("Click to view currently selected Home Net contents"); ?>"/>
			<br/>
			<span class="vexpl"><?php echo gettext("Choose the Home Net you want this interface to use.  Most users should choose 'default'."); ?></span>
		 	<br/><br/>
			<span class="red"><?php echo gettext("Note:"); ?></span>&nbsp;<?php echo gettext("Default Home " .
			"Net adds only local networks, WAN IPs, Gateways, VPNs and VIPs."); ?><br/>
			<span class="red"><?php echo gettext("Hint:"); ?></span>&nbsp;<?php echo gettext("Create an Alias to hold a list of " .
			"friendly IPs that the firewall cannot see or to customize the default Home Net.  Assign the Alias to a Pass List, and " . 
			"then assign that Pass List to Home Net."); ?><br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("External Net"); ?></td>
		<td width="78%" class="vtable">
			<select name="externallistname" class="formselect" id="externallistname">
				<?php
					echo "<option value='default' >default</option>";
					/* find Pass List names and filter by type */
					if (is_array($suricataglob['passlist']['item'])) {
						foreach ($suricataglob['passlist']['item'] as $value) {
							$ilistname = $value['name'];
							if ($ilistname == $pconfig['externallistname'])
								echo "<option value='$ilistname' selected>";
							else
								echo "<option value='$ilistname'>";
							echo htmlspecialchars($ilistname) . '</option>';
						}
					}
				?>
			</select>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="button" class="formbtns" value="View List"  
			onclick="viewList('<?=$id;?>','externallistname','externalnet')" id="btnExternalNet" 
			title="<?php echo gettext("Click to view currently selected External Net contents"); ?>"/>
			<br/>
			<?php echo gettext("Choose the External Net you want this interface " .
			"to use."); ?>&nbsp;<br/><br/>
			<span class="red"><?php echo gettext("Note:"); ?></span>&nbsp;<?php echo gettext("Default " .
			"External Net is networks that are not Home Net."); ?><br/>
			<span class="red"><?php echo gettext("Hint:"); ?></span>&nbsp;<?php echo gettext("Most users should leave this " .
			"setting at default.  Create an Alias for custom External Net settings."); ?><br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Pass List"); ?></td>
		<td width="78%" class="vtable">
			<select name="passlistname" class="formselect" id="passlistname">
			<?php
				/* find passlist names and filter by type, make sure to track by uuid */
				echo "<option value='default' >default</option>\n";
				if (is_array($suricataglob['passlist']['item'])) {
					foreach ($suricataglob['passlist']['item'] as $value) {
						if ($value['name'] == $pconfig['passlistname'])
							echo "<option value='{$value['name']}' selected>";
						else
							echo "<option value='{$value['name']}'>";
						echo htmlspecialchars($value['name']) . '</option>';
					}
				}
			?>
			</select>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="button" class="formbtns" value="View List" onclick="viewList('<?=$id;?>','passlistname','passlist')" 
			id="btnPasslist" title="<?php echo gettext("Click to view currently selected Pass List contents"); ?>"/>
			<br/>
			<?php echo gettext("Choose the Pass List you want this interface to use.  Addresses in a Pass List are never blocked."); ?> <br/><br/>
			<span class="red"><?php echo gettext("Note:"); ?></span>&nbsp;<?php echo gettext("This option will only be used when block offenders is on."); ?><br/>
			<span class="red"><?php echo gettext("Hint:"); ?></span>&nbsp;<?php echo gettext("Default " .
			"Pass List adds local networks, WAN IPs, Gateways, VPNs and VIPs.  Create an Alias to customize."); ?>
		</td>
	</tr>
<tr>
	<td colspan="2" class="listtopic"><?php echo gettext("Alert Suppression and Filtering"); ?></td>
</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Alert Suppression"); ?></td>
		<td width="78%" class="vtable">
			<select name="suppresslistname" class="formselect" id="suppresslistname">
		<?php
			echo "<option value='default' >default</option>\n";
			if (is_array($suricataglob['suppress']['item'])) {
				$slist_select = $suricataglob['suppress']['item'];
				foreach ($slist_select as $value) {
					$ilistname = $value['name'];
					if ($ilistname == $pconfig['suppresslistname'])
						echo "<option value='$ilistname' selected>";
					else
						echo "<option value='$ilistname'>";
					echo htmlspecialchars($ilistname) . '</option>';
				}
			}
		?>
		</select>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="button" class="formbtns" value="View List" onclick="viewList('<?=$id;?>','suppresslistname', 'suppress')" 
		id="btnSuppressList" title="<?php echo gettext("Click to view currently selected Suppression List contents"); ?>"/>
		<br/>
		<?php echo gettext("Choose the suppression or filtering file you " .
		"want this interface to use."); ?> <br/>&nbsp;<br/><span class="red"><?php echo gettext("Note: ") . "</span>" . 
		gettext("Default option disables suppression and filtering."); ?>
		</td>
	</tr>
<tr>
	<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Arguments here will " .
	"be automatically inserted into the Suricata configuration"); ?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Advanced configuration pass-through"); ?></td>
	<td width="78%" class="vtable">
		<textarea style="width:98%; height:100%;" wrap="off" name="configpassthru" cols="60" rows="8" id="configpassthru"><?=htmlspecialchars($pconfig['configpassthru']);?></textarea>
	</td>
</tr>
<tr>
	<td colspan="2" align="center" valign="middle"><input name="save" type="submit" class="formbtn" value="Save" title="<?php echo 
			gettext("Click to save settings and exit"); ?>"/>
	</td>
</tr>
<tr>
	<td colspan="2" align="center" valign="middle"><span class="vexpl"><span class="red"><strong><?php echo gettext("Note: ") . "</strong></span></span>" . 
		gettext("Please save your settings before you attempt to start Suricata."); ?>	
	</td>
</tr>
</tbody>
</table>
</div>
</td></tr>
</tbody>
</table>
</form>

<script language="JavaScript">

function toggle_system_log() {
	var endis = !(document.iform.alertsystemlog.checked);
	if (endis)
		document.getElementById("alertsystemlog_rows").style.display="none";
	else
		document.getElementById("alertsystemlog_rows").style.display="";
}

function toggle_dns_log() {
	var endis = !(document.iform.enable_dns_log.checked);
	if (endis)
		document.getElementById("dns_log_append_row").style.display="none";
	else
		document.getElementById("dns_log_append_row").style.display="table-row";
}

function enable_blockoffenders() {
	var endis = !(document.iform.blockoffenders.checked);
	document.iform.blockoffenderskill.disabled=endis;
	document.iform.blockoffendersip.disabled=endis;
	document.iform.passlistname.disabled=endis;
	document.iform.btnPasslist.disabled=endis;
}

function toggle_stats_log() {
	var endis = !(document.iform.enable_stats_log.checked);
	if (endis) {
		document.getElementById("stats_log_append_row").style.display="none";
		document.getElementById("stats_interval_row").style.display="none";
		document.getElementById("stats_log_warning").style.display="none";
	}
	else {
		document.getElementById("stats_log_append_row").style.display="table-row";
		document.getElementById("stats_interval_row").style.display="table-row";
		document.getElementById("stats_log_warning").style.display="inline";
	}
}

function toggle_http_log() {
	var endis = !(document.iform.enable_http_log.checked);
	if (endis) {
		document.getElementById("http_log_append_row").style.display="none";
		document.getElementById("http_log_extended_row").style.display="none";
	}
	else {
		document.getElementById("http_log_append_row").style.display="table-row";
		document.getElementById("http_log_extended_row").style.display="table-row";
	}
}

function toggle_tls_log() {
	var endis = !(document.iform.enable_tls_log.checked);
	if (endis)
		document.getElementById("tls_log_extended_row").style.display="none";
	else {
		document.getElementById("tls_log_extended_row").style.display="table-row";
		if (document.iform.enable_eve_log.checked && document.iform.eve_log_tls.checked) {
			alert('Only one TLS log instance permitted...removing TLS log from EVE JSON output in order to enable standalone TLS logging.');
			document.iform.eve_log_tls.checked = false;
		}
	}
}

function toggle_json_file_log() {
	var endis = !(document.iform.enable_json_file_log.checked);
	if (endis) {
		document.getElementById("tracked_files_append_row").style.display="none";
		document.getElementById("tracked_files_magic_row").style.display="none";
		document.getElementById("tracked_files_md5_row").style.display="none";
	}
	else {
		document.getElementById("tracked_files_append_row").style.display="table-row";
		document.getElementById("tracked_files_magic_row").style.display="table-row";
		document.getElementById("tracked_files_md5_row").style.display="table-row";
	}
}

function toggle_file_store() {
	var endis = !(document.iform.enable_file_store.checked);
	if (endis) {
		document.getElementById("file_store_warning").style.display="none";
	}
	else {
		document.getElementById("file_store_warning").style.display="inline";
	}
}

function toggle_pcap_log() {
	var endis = !(document.iform.enable_pcap_log.checked);
	if (endis) {
		document.getElementById("pcap_log_size_row").style.display="none";
		document.getElementById("pcap_log_max_row").style.display="none";
		document.getElementById("file_pcap_warning").style.display="none";
	}
	else {
		document.getElementById("pcap_log_size_row").style.display="table-row";
		document.getElementById("pcap_log_max_row").style.display="table-row";
		document.getElementById("file_pcap_warning").style.display="inline";
	}
}

function toggle_eve_log() {
	var endis = !(document.iform.enable_eve_log.checked);
	if (endis) {
		document.getElementById("eve_log_option_rows").style.display = "none";
	}
	else {
		document.getElementById("eve_log_option_rows").style.display = "";
		if (document.iform.enable_tls_log.checked)
			document.iform.eve_log_tls.checked = false;
	}
}

function toggle_eve_tls() {
	if (document.iform.enable_tls_log.checked) {
		alert('Only one TLS log instance permitted...removing standalone TLS output in order to add EVE JSON TLS output.');
		document.iform.enable_tls_log.checked = false;
	}
}

function enable_change(enable_change) {
	endis = !(document.iform.enable.checked || enable_change);
	// make sure a default answer is called if this is invoked.
	endis2 = (document.iform.enable);
	document.iform.enable_stats_log.disabled = endis;
	document.iform.stats_upd_interval.disabled = endis;
	document.iform.append_stats_log.disabled = endis;
	document.iform.enable_http_log.disabled = endis;
	document.iform.append_http_log.disabled = endis;
	document.iform.http_log_extended.disabled = endis;
	document.iform.enable_tls_log.disabled = endis;
	document.iform.tls_log_extended.disabled = endis;
	document.iform.enable_json_file_log.disabled = endis;
	document.iform.append_json_file_log.disabled = endis;
	document.iform.enable_tracked_files_magic.disabled = endis;
	document.iform.enable_tracked_files_md5.disabled = endis;
	document.iform.enable_file_store.disabled = endis;
	document.iform.enable_pcap_log.disabled = endis;
	document.iform.max_pcap_log_size.disabled = endis;
	document.iform.max_pcap_log_files.disabled = endis;
	document.iform.eve_output_type.disabled = endis;
	document.iform.enable_eve_log.disabled = endis;
	document.iform.eve_log_alerts.disabled = endis;
	document.iform.eve_log_http.disabled = endis;
	document.iform.eve_log_dns.disabled = endis;
	document.iform.eve_log_tls.disabled = endis;
	document.iform.eve_log_files.disabled = endis;
	document.iform.eve_log_ssh.disabled = endis;
	document.iform.max_pending_packets.disabled = endis;
	document.iform.detect_eng_profile.disabled = endis;
	document.iform.mpm_algo.disabled = endis;
	document.iform.sgh_mpm_context.disabled = endis;
	document.iform.inspect_recursion_limit.disabled = endis;
	document.iform.blockoffenders.disabled = endis;
	document.iform.blockoffendersip.disabled=endis;
	document.iform.blockoffenderskill.disabled=endis;
	document.iform.alertsystemlog.disabled = endis;
	document.iform.externallistname.disabled = endis;
	document.iform.homelistname.disabled = endis;
	document.iform.passlistname.disabled=endis;
	document.iform.suppresslistname.disabled = endis;
	document.iform.configpassthru.disabled = endis;
	document.iform.btnHomeNet.disabled=endis;
	document.iform.btnPasslist.disabled=endis;
	document.iform.btnSuppressList.disabled=endis;
	document.iform.delayed_detect.disabled=endis;
	document.iform.intf_promisc_mode.disabled=endis;
}

function wopen(url, name, w, h) {
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

function getSelectedValue(elemID) {
	var ctrl = document.getElementById(elemID);
	return ctrl.options[ctrl.selectedIndex].value;
}

function eveOutSelect() {
	var ctrl = document.getElementById("eve_output_type");
	if (ctrl.options[ctrl.selectedIndex].value == 'syslog') {
		document.getElementById("eve_systemlog_facility_row").style.display = "table-row";
		document.getElementById("eve_systemlog_priority_row").style.display = "table-row";
	}
	else {
		document.getElementById("eve_systemlog_facility_row").style.display = "none";
		document.getElementById("eve_systemlog_priority_row").style.display = "none";
	}
}

function viewList(id, elemID, elemType) {
	if (typeof elemType == "undefined") {
		elemType = "passlist";
	}
	var url = "suricata_list_view.php?id=" + id + "&wlist=";
	url = url + getSelectedValue(elemID) + "&type=" + elemType;
	url = url + "&time=" + new Date().getTime();
	wopen(url, 'PassListViewer', 640, 480);
}

enable_change(false);
//enable_blockoffenders();
toggle_system_log();
toggle_dns_log();
toggle_stats_log();
toggle_http_log();
toggle_tls_log();
toggle_json_file_log();
toggle_file_store();
toggle_pcap_log();
toggle_eve_log();
eveOutSelect();

</script>
<?php include("fend.inc"); ?>
</body>
</html>
