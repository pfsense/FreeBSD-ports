<?php
/*
 * suricata_generate_yaml.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2020 Bill Meeks
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

// Create required Suricata directories if they don't exist
$suricata_dirs = array( $suricatadir, $suricatacfgdir, "{$suricatacfgdir}/rules",
	"{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}" );
foreach ($suricata_dirs as $dir) {
	if (!is_dir($dir))
		safe_mkdir($dir);
}

// Copy required generic files to the interface sub-directory
$config_files = array( "classification.config", "reference.config", "gen-msg.map", "unicode.map" );
foreach ($config_files as $file) {
	if (file_exists("{$suricatadir}{$file}"))
		@copy("{$suricatadir}{$file}", "{$suricatacfgdir}/{$file}");
}

// Read the configuration parameters for the passed interface
// and construct appropriate string variables for use in the
// suricata.yaml template include file.

// Set HOME_NET and EXTERNAL_NET for the interface
$home_net_list = suricata_build_list($suricatacfg, $suricatacfg['homelistname']);
$home_net = implode(", ", $home_net_list);
$home_net = trim($home_net);
$external_net = "";
if (!empty($suricatacfg['externallistname']) && $suricatacfg['externallistname'] != 'default') {
	$external_net_list = suricata_build_list($suricatacfg, $suricatacfg['externallistname'], false, true);
	$external_net = implode(", ", $external_net_list);
	$external_net = "[" . trim($external_net) . "]";
}
else {
	$external_net = "[";
	foreach ($home_net_list as $ip)
		$external_net .= "!{$ip}, ";
	$external_net = trim($external_net, ', ') . "]";
}

// Set the PASS LIST and write its contents to disk,
// but only if using Legacy Mode blocking. Otherwise,
// just create an empty placeholder file.
unlink_if_exists("{$suricatacfgdir}/rules/passlist.rules");
$suri_passlist = "{$suricatacfgdir}/passlist";
if ($suricatacfg['ips_mode'] == 'ips_mode_legacy' && $suricatacfg['blockoffenders'] == 'on' && $suricatacfg['passlistname'] != 'none') {
	$plist = suricata_build_list($suricatacfg, $suricatacfg['passlistname'], true);
	@file_put_contents("{$suricatacfgdir}/passlist", implode("\n", $plist));
}
else {
	file_put_contents("{$suricatacfgdir}/passlist", '');
}

// Set default and user-defined variables for SERVER_VARS and PORT_VARS
$suricata_servers = array (
	"dns_servers" => "\$HOME_NET", "smtp_servers" => "\$HOME_NET", "http_servers" => "\$HOME_NET",
	"sql_servers" => "\$HOME_NET", "telnet_servers" => "\$HOME_NET", "dnp3_server" => "\$HOME_NET",
	"dnp3_client" => "\$HOME_NET", "modbus_server" => "\$HOME_NET", "modbus_client" => "\$HOME_NET",
	"enip_server" => "\$HOME_NET", "enip_client" => "\$HOME_NET", "ftp_servers" => "\$HOME_NET", "ssh_servers" => "\$HOME_NET", 
	"aim_servers" => "64.12.24.0/23, 64.12.28.0/23, 64.12.161.0/24, 64.12.163.0/24, 64.12.200.0/24, 205.188.3.0/24, 205.188.5.0/24, 205.188.7.0/24, 205.188.9.0/24, 205.188.153.0/24, 205.188.179.0/24, 205.188.248.0/24", 
	"sip_servers" => "\$HOME_NET"
);
$addr_vars = "";
	foreach ($suricata_servers as $alias => $avalue) {
		if (!empty($suricatacfg["def_{$alias}"]) && is_alias($suricatacfg["def_{$alias}"])) {
			$avalue = trim(filter_expand_alias($suricatacfg["def_{$alias}"]));
			$avalue = preg_replace('/\s+/', ', ', trim($avalue));
		}
		$addr_vars .= "    " . strtoupper($alias) . ": \"{$avalue}\"\n";
	}
$addr_vars = trim($addr_vars);
if(is_array($config['system']['ssh']) && isset($config['system']['ssh']['port']))
        $ssh_port = $config['system']['ssh']['port'];
else
        $ssh_port = "22";
$suricata_ports = array(
	"ftp_ports" => "21", 
	"http_ports" => "80", 
	"oracle_ports" => "1521", 
	"ssh_ports" => $ssh_port, 
	"shellcode_ports" => "!80", 
	"DNP3_PORTS" => "20000", 
	"file_data_ports" => "\$HTTP_PORTS, 110, 143", 
	"sip_ports" => "5060, 5061, 5600"
);
$port_vars = "";
	foreach ($suricata_ports as $alias => $avalue) {
		if (!empty($suricatacfg["def_{$alias}"]) && is_alias($suricatacfg["def_{$alias}"])) {
			$avalue = trim(filter_expand_alias($suricatacfg["def_{$alias}"]));
			$avalue = preg_replace('/\s+/', ', ', trim($avalue));
		}
		$port_vars .= "    " . strtoupper($alias) . ": \"{$avalue}\"\n";
	}
$port_vars = trim($port_vars);

// Define a Suppress List (Threshold) if one is configured
$suppress = suricata_find_list($suricatacfg['suppresslistname'], 'suppress');
if (!empty($suppress)) {
	$suppress_data = str_replace("\r", "", base64_decode($suppress['suppresspassthru']));
	@file_put_contents("{$suricatacfgdir}/threshold.config", $suppress_data);
}
else
	@file_put_contents("{$suricatacfgdir}/threshold.config", "");

// Add interface-specific performance and detection engine settings
if (!empty($suricatacfg['runmode']))
	$runmode = $suricatacfg['runmode'];
else
	$runmode = "autofp";

if (!empty($suricatacfg['max_pending_packets']))
	$max_pend_pkts = $suricatacfg['max_pending_packets'];
else
	$max_pend_pkts = 1024;

if (!empty($suricatacfg['detect_eng_profile']))
	$detect_eng_profile = $suricatacfg['detect_eng_profile'];
else
	$detect_eng_profile = "medium";

if (!empty($suricatacfg['sgh_mpm_context']))
	$sgh_mpm_ctx = $suricatacfg['sgh_mpm_context'];
else
	$sgh_mpm_ctx = "auto";

if (!empty($suricatacfg['mpm_algo']))
	$mpm_algo = $suricatacfg['mpm_algo'];
else
	$mpm_algo = "auto";

if (!empty($suricatacfg['inspect_recursion_limit']) || $suricatacfg['inspect_recursion_limit'] == '0')
	$inspection_recursion_limit = $suricatacfg['inspect_recursion_limit'];
else
	$inspection_recursion_limit = "";

if ($suricatacfg['delayed_detect'] == 'on')
	$delayed_detect = "yes";
else
	$delayed_detect = "no";

if ($suricatacfg['intf_promisc_mode'] == 'on')
	$intf_promisc_mode = "yes";
else
	$intf_promisc_mode = "no";

if (!empty($suricatacfg['intf_snaplen'])) {
	$intf_snaplen = $suricatacfg['intf_snaplen'];
}
else {
	$intf_snaplen = "1518";
}

// Add interface-specific blocking settings
if ($suricatacfg['blockoffenders'] == 'on' && $suricatacfg['ips_mode'] == 'ips_mode_legacy')
	$suri_blockoffenders = "yes";
else
	$suri_blockoffenders = "no";

if ($suricatacfg['blockoffenderskill'] == 'on')
	$suri_killstates = "yes";
else
	$suri_killstates = "no";

if ($suricatacfg['block_drops_only'] == 'on')
	$suri_blockdrops = "yes";
else
	$suri_blockdrops = "no";

if ($suricatacfg['blockoffendersip'] == 'src')
	$suri_blockip = 'SRC';
elseif ($suricatacfg['blockoffendersip'] == 'dst')
	$suri_blockip = 'DST';
else
	$suri_blockip = 'BOTH';

$suri_pf_table = SURICATA_PF_TABLE;

// Add interface-specific logging settings
if ($suricatacfg['alertsystemlog'] == 'on')
	$alert_syslog = "yes";
else
	$alert_syslog = "no";

if (!empty($suricatacfg['alertsystemlog_facility']))
	$alert_syslog_facility = $suricatacfg['alertsystemlog_facility'];
else
	$alert_syslog_facility = "local5";

if (!empty($suricatacfg['alertsystemlog_priority']))
	$alert_syslog_priority = $suricatacfg['alertsystemlog_priority'];
else
	$alert_syslog_priority = "Info";

if ($suricatacfg['enable_stats_log'] == 'on')
	$stats_log_enabled = "yes";
else
	$stats_log_enabled = "no";

if (!empty($suricatacfg['stats_upd_interval']))
	$stats_upd_interval = $suricatacfg['stats_upd_interval'];
else
	$stats_upd_interval = "10";

if ($suricatacfg['append_stats_log'] == 'on')
	$stats_log_append = "yes";
else
	$stats_log_append = "no";

if ($suricatacfg['enable_http_log'] == 'on')
	$http_log_enabled = "yes";
else
	$http_log_enabled = "no";

if ($suricatacfg['append_http_log'] == 'on')
	$http_log_append = "yes";
else
	$http_log_append = "no";

if ($suricatacfg['http_log_extended'] == 'on')
	$http_log_extended = "yes";
else
	$http_log_extended = "no";

if ($suricatacfg['enable_tls_log'] == 'on')
	$tls_log_enabled = "yes";
else
	$tls_log_enabled = "no";

if ($suricatacfg['enable_tls_store'] == 'on')
	$tls_store_enabled = "yes";
else
	$tls_store_enabled = "no";

if ($suricatacfg['tls_log_extended'] == 'on')
	$tls_log_extended = "yes";
else
	$tls_log_extended = "no";

if ($suricatacfg['enable_json_file_log'] == 'on')
	$json_log_enabled = "yes";
else
	$json_log_enabled = "no";

if ($suricatacfg['append_json_file_log'] == 'on')
	$json_log_append = "yes";
else
	$json_log_append = "no";

if ($suricatacfg['enable_tracked_files_magic'] == 'on')
	$json_log_magic = "yes";
else
	$json_log_magic = "no";

if ($suricatacfg['tracked_files_hash'] != 'none')
	$json_log_hash = "force-hash: [{$suricatacfg['tracked_files_hash']}]";
else
	$json_log_hash = "#force-hash: [md5]";
	
if ($suricatacfg['enable_file_store'] == 'on') {
	$file_store_enabled = "yes";
	if (!file_exists("{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}/file.waldo"))
		@file_put_contents("{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}/file.waldo", "");
	$file_store_waldo = "waldo: file.waldo";
	if (!empty($suricatacfg['file_store_logdir'])) {
		$file_store_logdir = base64_decode($suricatacfg['file_store_logdir']);
	}
	else {
		$file_store_logdir = "filestore";
	}
}
else {
	$file_store_enabled = "no";
	$file_store_waldo = "#waldo: file.waldo";
	$file_store_logdir = "filestore";
}

if ($suricatacfg['enable_pcap_log'] == 'on')
	$pcap_log_enabled = "yes";
else
	$pcap_log_enabled = "no";

if (!empty($suricatacfg['max_pcap_log_size']))
	$pcap_log_limit_size = $suricatacfg['max_pcap_log_size'];
else
	$pcap_log_limit_size = "32";

if (!empty($suricatacfg['max_pcap_log_files']))
	$pcap_log_max_files = $suricatacfg['max_pcap_log_files'];
else
	$pcap_log_max_files = "1000";

// Unified2 Alert Log Settings
if ($suricatacfg['barnyard_enable'] == 'on')
	$barnyard2_enabled = "yes";
else
	$barnyard2_enabled = "no";

if (isset($config['installedpackages']['suricata']['config'][0]['unified2_log_limit']))
	$unified2_log_limit = "{$config['installedpackages']['suricata']['config'][0]['unified2_log_limit']}mb";
else
	$unified2_log_limit = "32mb";

if (isset($suricatacfg['barnyard_sensor_id']))
	$unified2_sensor_id = $suricatacfg['barnyard_sensor_id'];
else
	$unified2_sensor_id = "0";

// Unified2 X-Forwarded-For logging options
if ($suricatacfg['barnyard_xff_logging'] == 'on') {
	$unified2_xff_output = "xff:";
	$unified2_xff_output .= "\n        enabled: yes";
	if (!empty($suricatacfg['barnyard_xff_mode']))
		$unified2_xff_output .= "\n        mode: {$suricatacfg['barnyard_xff_mode']}";
	else
		$unified2_xff_output .= "\n        mode: extra-data";
	if (!empty($suricatacfg['barnyard_xff_deployment']))
		$unified2_xff_output .= "\n        deployment: {$suricatacfg['barnyard_xff_deployment']}";
	else
		$unified2_xff_output .= "\n        deployment: reverse";
	if (!empty($suricatacfg['barnyard_xff_header']))
		$unified2_xff_output .= "\n        header: {$suricatacfg['barnyard_xff_header']}";
	else
		$unified2_xff_output .= "\n        header: X-Forwarded-For";
}
else {
	$unified2_xff_output = "xff:";
	$unified2_xff_output .= "\n        enabled: no";
}

// EVE JSON log output settings
if ($suricatacfg['enable_eve_log'] == 'on')
	$enable_eve_log = "yes";
else
	$enable_eve_log = "no";

if (!empty($suricatacfg['eve_output_type']))
	$eve_output_type = $suricatacfg['eve_output_type'];
else
	$eve_output_type = "regular";

// EVE SYSLOG output settings
if (!empty($suricatacfg['eve_systemlog_facility']))
	$eve_systemlog_facility = $suricatacfg['eve_systemlog_facility'];
else
	$eve_systemlog_facility = "local1";

if (!empty($suricatacfg['eve_systemlog_priority']))
	$eve_systemlog_priority = $suricatacfg['eve_systemlog_priority'];
else
	$eve_systemlog_priority = "info";

// EVE REDIS output settings
if (!empty($suricatacfg['eve_redis_server']))
	$eve_redis_output = "\n        server: ". $suricatacfg['eve_redis_server'];
else
	$eve_redis_output = "\n        server: 127.0.0.1";

if (!empty($suricatacfg['eve_redis_port']))
	$eve_redis_output .= "\n        port: " . $suricatacfg['eve_redis_port'];

if (!empty($suricatacfg['eve_redis_mode']))
	$eve_redis_output .= "\n        mode: " . $suricatacfg['eve_redis_mode'];

if (!empty($suricatacfg['eve_redis_key']))
	$eve_redis_output .= "\n        key: \"" . $suricatacfg['eve_redis_key'] ."\"";

// EVE X-Forwarded-For settings
if ($suricatacfg['eve_log_alerts_xff'] == 'on'){
	$eve_xff_enabled = "yes";
	$eve_xff_mode = $suricatacfg['eve_log_alerts_xff_mode'];
	$eve_xff_deployment = $suricatacfg['eve_log_alerts_xff_deployment'];
	$eve_xff_header = $suricatacfg['eve_log_alerts_xff_header'];
}
else {
	$eve_xff_enabled = "no";
	$eve_xff_mode = $suricatacfg['eve_log_alerts_xff_mode'];
	$eve_xff_deployment = $suricatacfg['eve_log_alerts_xff_deployment'];
	$eve_xff_header = $suricatacfg['eve_log_alerts_xff_header'];
}

// EVE log output included information
$eve_out_types = "";

if (($suricatacfg['eve_log_alerts'] == 'on')) {
	$eve_out_types .= "\n        - alert:";
	$eve_out_types .= "\n            payload: ".($suricatacfg['eve_log_alerts_payload'] == 'on' || $suricatacfg['eve_log_alerts_payload'] == 'only-base64' ?'yes':'no ')."              # enable dumping payload in Base64";
	$eve_out_types .= "\n            payload-buffer-size: 4kb  # max size of payload buffer to output in eve-log";
	$eve_out_types .= "\n            payload-printable: ".($suricatacfg['eve_log_alerts_payload'] == 'on' || $suricatacfg['eve_log_alerts_payload'] == 'only-printable' ?'yes':'no ')."    # enable dumping payload in printable (lossy) format";
	$eve_out_types .= "\n            packet: ".($suricatacfg['eve_log_alerts_packet'] == 'on'?'yes':'no ')."               # enable dumping of packet (without stream segments)";
	$eve_out_types .= "\n            http-body: ".($suricatacfg['eve_log_alerts_payload'] == 'on'?'yes':'no ' || $suricatacfg['eve_log_alerts_payload'] == 'only-base64' ?'yes':'no ')."            # enable dumping of http body in Base64";
	$eve_out_types .= "\n            http-body-printable: ".($suricatacfg['eve_log_alerts_payload'] == 'on' || $suricatacfg['eve_log_alerts_payload'] == 'only-printable' ?'yes':'no ')."  # enable dumping of http body in printable format";
	$eve_out_types .= "\n            metadata: ".($suricatacfg['eve_log_alerts_metadata'] == 'on'?'yes':'no ')."             # enable inclusion of app layer metadata with alert";
	$eve_out_types .= "\n            tagged-packets: yes       # enable logging of tagged packets for rules using the 'tag' keyword";
}

if ($suricatacfg['eve_log_http'] == 'on') {
	$eve_out_types .= "\n        - http:";
	if ($suricatacfg['eve_log_http_extended'] == 'on') {
		$eve_out_types .= "\n            extended: yes";
		if ($suricatacfg['eve_log_http_extended_headers'] != "")
			$eve_out_types .= "\n            custom: [".$suricatacfg['eve_log_http_extended_headers']."]";
         } else {
                $eve_out_types .= "\n            extended: no";
         }
}

if ($suricatacfg['eve_log_dns'] == 'on') {
	$eve_out_types .= "\n        - dns:";
	$eve_out_types .= "\n            version: 2";
	$eve_out_types .= "\n            query: yes";
	$eve_out_types .= "\n            answer: yes";
}

if ($suricatacfg['eve_log_tls'] == 'on') {
	$eve_out_types .= "\n        - tls:";
	if ($suricatacfg['eve_log_tls_extended'] == 'on')
		$eve_out_types .= "\n            extended: yes";
	else
		$eve_out_types .= "\n            extended: no";
}

if ($suricatacfg['eve_log_dhcp'] == 'on') {
	$eve_out_types .= "\n        - dhcp:";
	if ($suricatacfg['eve_log_dhcp_extended'] == 'on')
		$eve_out_types .= "\n            extended: yes";
	else
		$eve_out_types .= "\n            extended: no";
}

if ($suricatacfg['eve_log_files'] == 'on') {
	$eve_out_types .= "\n        - files:";
	if ($suricatacfg['eve_log_files_magic'] == 'on')
		$eve_out_types .= "\n            force-magic: yes";
	else
		$eve_out_types .= "\n            force-magic: no";
	if ($suricatacfg['eve_log_files_hash'] != 'none') {
		$eve_out_types .= "\n            force-hash: [{$suricatacfg['eve_log_files_hash']}]";
	}
}

if ($suricatacfg['eve_log_ssh'] == 'on') {
	$eve_out_types .= "\n        - ssh";
}

if ($suricatacfg['eve_log_nfs'] == 'on') {
	$eve_out_types .= "\n        - nfs";
}

if ($suricatacfg['eve_log_smb'] == 'on') {
	$eve_out_types .= "\n        - smb";
}

if ($suricatacfg['eve_log_krb5'] == 'on') {
	$eve_out_types .= "\n        - krb5";
}

if ($suricatacfg['eve_log_ikev2'] == 'on') {
	$eve_out_types .= "\n        - ikev2";
}

if ($suricatacfg['eve_log_tftp'] == 'on') {
	$eve_out_types .= "\n        - tftp";
}

if ($suricatacfg['eve_log_smtp'] == 'on') {
	$eve_out_types .= "\n        - smtp:";
	if ($suricatacfg['eve_log_smtp_extended'] == 'on')
		$eve_out_types .= "\n            extended: yes";
	else
		$eve_out_types .= "\n            extended: no";
	if($suricatacfg['eve_log_smtp_extended_fields'] != "")
		$eve_out_types .= "\n            custom: [".$suricatacfg['eve_log_smtp_extended_fields']."]";

	$eve_out_types .= "\n            md5: [subject]";
}

if ($suricatacfg['eve_log_drop'] == 'on' && $suricatacfg['ips_mode'] == "ips_mode_inline") {
	$eve_out_types .= "\n        - drop:";
	$eve_out_types .= "\n            alerts: yes";
	$eve_out_types .= "\n            flows: all";
}

if ($suricatacfg['eve_log_stats'] == 'on'){
	$eve_out_types .= "\n        - stats:";
	$eve_out_types .= "\n            totals: ".($suricatacfg['eve_log_stats_totals'] == 'on'?'yes':'no');
	$eve_out_types .= "\n            deltas: ".($suricatacfg['eve_log_stats_deltas'] == 'on'?'yes':'no');
	$eve_out_types .= "\n            threads: ".($suricatacfg['eve_log_stats_threads'] == 'on'?'yes':'no');
}

if ($suricatacfg['eve_log_flow'] == 'on') {
	$eve_out_types .= "\n        - flow                        # Bi-directional flows";
}

if ($suricatacfg['eve_log_netflow'] == 'on') {
	$eve_out_types .= "\n        - netflow                     # Uni-directional flows";
}

// Add interface-specific IP defrag settings
if (!empty($suricatacfg['frag_memcap']))
	$frag_memcap = $suricatacfg['frag_memcap'];
else
	$frag_memcap = "33554432";

if (!empty($suricatacfg['ip_max_trackers']))
	$ip_max_trackers = $suricatacfg['ip_max_trackers'];
else
	$ip_max_trackers = "65535";

if (!empty($suricatacfg['ip_max_frags']))
	$ip_max_frags = $suricatacfg['ip_max_frags'];
else
	$ip_max_frags = "65535";

if (!empty($suricatacfg['frag_hash_size']))
	$frag_hash_size = $suricatacfg['frag_hash_size'];
else
	$frag_hash_size = "65536";

if (!empty($suricatacfg['ip_frag_timeout']))
	$ip_frag_timeout = $suricatacfg['ip_frag_timeout'];
else
	$ip_frag_timeout = "60";

// Add interface-specific flow manager setttings
if (!empty($suricatacfg['flow_memcap']))
	$flow_memcap = $suricatacfg['flow_memcap'];
else
	$flow_memcap = "33554432";

if (!empty($suricatacfg['flow_hash_size']))
	$flow_hash_size = $suricatacfg['flow_hash_size'];
else
	$flow_hash_size = "65536";

if (!empty($suricatacfg['flow_prealloc']))
	$flow_prealloc = $suricatacfg['flow_prealloc'];
else
	$flow_prealloc = "10000";

if (!empty($suricatacfg['flow_emerg_recovery']))
	$flow_emerg_recovery = $suricatacfg['flow_emerg_recovery'];
else
	$flow_emerg_recovery = "30";

if (!empty($suricatacfg['flow_prune']))
	$flow_prune = $suricatacfg['flow_prune'];
else
	$flow_prune = "5";

// Add interface-specific flow timeout setttings
if (!empty($suricatacfg['flow_tcp_new_timeout']))
	$flow_tcp_new_timeout = $suricatacfg['flow_tcp_new_timeout'];
else
	$flow_tcp_new_timeout = "60";

if (!empty($suricatacfg['flow_tcp_established_timeout']))
	$flow_tcp_established_timeout = $suricatacfg['flow_tcp_established_timeout'];
else
	$flow_tcp_established_timeout = "3600";

if (!empty($suricatacfg['flow_tcp_closed_timeout']))
	$flow_tcp_closed_timeout = $suricatacfg['flow_tcp_closed_timeout'];
else
	$flow_tcp_closed_timeout = "120";

if (!empty($suricatacfg['flow_tcp_emerg_new_timeout']))
	$flow_tcp_emerg_new_timeout = $suricatacfg['flow_tcp_emerg_new_timeout'];
else
	$flow_tcp_emerg_new_timeout = "10";

if (!empty($suricatacfg['flow_tcp_emerg_established_timeout']))
	$flow_tcp_emerg_established_timeout = $suricatacfg['flow_tcp_emerg_established_timeout'];
else
	$flow_tcp_emerg_established_timeout = "300";

if (!empty($suricatacfg['flow_tcp_emerg_closed_timeout']))
	$flow_tcp_emerg_closed_timeout = $suricatacfg['flow_tcp_emerg_closed_timeout'];
else
	$flow_tcp_emerg_closed_timeout = "20";

if (!empty($suricatacfg['flow_udp_new_timeout']))
	$flow_udp_new_timeout = $suricatacfg['flow_udp_new_timeout'];
else
	$flow_udp_new_timeout = "30";

if (!empty($suricatacfg['flow_udp_established_timeout']))
	$flow_udp_established_timeout = $suricatacfg['flow_udp_established_timeout'];
else
	$flow_udp_established_timeout = "300";

if (!empty($suricatacfg['flow_udp_emerg_new_timeout']))
	$flow_udp_emerg_new_timeout = $suricatacfg['flow_udp_emerg_new_timeout'];
else
	$flow_udp_emerg_new_timeout = "10";

if (!empty($suricatacfg['flow_udp_emerg_established_timeout']))
	$flow_udp_emerg_established_timeout = $suricatacfg['flow_udp_emerg_established_timeout'];
else
	$flow_udp_emerg_established_timeout = "100";

if (!empty($suricatacfg['flow_icmp_new_timeout']))
	$flow_icmp_new_timeout = $suricatacfg['flow_icmp_new_timeout'];
else
	$flow_icmp_new_timeout = "30";

if (!empty($suricatacfg['flow_icmp_established_timeout']))
	$flow_icmp_established_timeout = $suricatacfg['flow_icmp_established_timeout'];
else
	$flow_icmp_established_timeout = "300";

if (!empty($suricatacfg['flow_icmp_emerg_new_timeout']))
	$flow_icmp_emerg_new_timeout = $suricatacfg['flow_icmp_emerg_new_timeout'];
else
	$flow_icmp_emerg_new_timeout = "10";

if (!empty($suricatacfg['flow_icmp_emerg_established_timeout']))
	$flow_icmp_emerg_established_timeout = $suricatacfg['flow_icmp_emerg_established_timeout'];
else
	$flow_icmp_emerg_established_timeout = "100";

// Add interface-specific stream settings
if (!empty($suricatacfg['stream_memcap']))
	$stream_memcap = $suricatacfg['stream_memcap'];
else
	$stream_memcap = "67108864";

if (!empty($suricatacfg['stream_prealloc_sessions']))
	$stream_prealloc_sessions = $suricatacfg['stream_prealloc_sessions'];
else
	$stream_prealloc_sessions = "32768";

if (!empty($suricatacfg['reassembly_memcap']))
	$reassembly_memcap = $suricatacfg['reassembly_memcap'];
else
	$reassembly_memcap = "67108864";

if (!empty($suricatacfg['reassembly_depth']) || $suricatacfg['reassembly_depth'] == '0')
	$reassembly_depth = $suricatacfg['reassembly_depth'];
else
	$reassembly_depth = "1048576";

if (!empty($suricatacfg['reassembly_to_server_chunk']))
	$reassembly_to_server_chunk = $suricatacfg['reassembly_to_server_chunk'];
else
	$reassembly_to_server_chunk = "2560";

if (!empty($suricatacfg['reassembly_to_client_chunk']))
	$reassembly_to_client_chunk = $suricatacfg['reassembly_to_client_chunk'];
else
	$reassembly_to_client_chunk = "2560";

if (!empty($suricatacfg['max_synack_queued']))
	$max_synack_queued = $suricatacfg['max_synack_queued'];
else
	$max_synack_queued = "5";

if ($suricatacfg['enable_midstream_sessions'] == 'on')
	$stream_enable_midstream = "true";
else
	$stream_enable_midstream = "false";

if ($suricatacfg['enable_async_sessions'] == 'on')
	$stream_enable_async = "true";
else
	$stream_enable_async = "false";

// Add the OS-specific host policies if configured, otherwise
// just set default to BSD for all networks.
$host_os_policy = "";
if (!is_array($suricatacfg['host_os_policy']))
	$suricatacfg['host_os_policy'] = array();
if (!is_array($suricatacfg['host_os_policy']['item']))
	$suricatacfg['host_os_policy']['item'] = array();
if (count($suricatacfg['host_os_policy']['item']) < 1)
	$host_os_policy = "bsd: [0.0.0.0/0]";
else {
	foreach ($suricatacfg['host_os_policy']['item'] as $k => $v) {
		$engine = "{$v['policy']}: ";
		if ($v['bind_to'] <> "all") {
			$tmp = trim(filter_expand_alias($v['bind_to']));
			if (!empty($tmp)) {
				$engine .= "[";
				$tmp = preg_replace('/\s+/', ',', $tmp);
				$list = explode(',', $tmp);
				foreach ($list as $addr) {
					if (is_ipaddrv6($addr) || is_subnetv6($addr))
						$engine .= "\"{$addr}\", ";
					elseif (is_ipaddrv4($addr) || is_subnetv4($addr))
						$engine .= "{$addr}, ";
					else
						syslog(LOG_WARN, "[suricata] WARNING: invalid IP address value '{$addr}' in Alias {$v['bind_to']} will be ignored.");
				}
				$engine = trim($engine, ' ,');
				$engine .= "]";
			}
			else {
				syslog(LOG_WARN, "[suricata] WARNING: unable to resolve IP List Alias '{$v['bind_to']}' for Host OS Policy '{$v['name']}' ... ignoring this entry.");
				continue;
			}
		}
		else
			$engine .= "[0.0.0.0/0]";

		$host_os_policy .= "  {$engine}\n";
	}
	// Remove trailing newline
	$host_os_policy = trim($host_os_policy);
}

// Add the HTTP Server-specific policies if configured, otherwise
// just set default to IDS for all networks.
$http_hosts_policy = "";
$http_hosts_default_policy = "";
if (!is_array($suricatacfg['libhtp_policy']))
	$suricatacfg['libhtp_policy'] = array();
if (!is_array($suricatacfg['libhtp_policy']['item']))
	$suricatacfg['libhtp_policy']['item'] = array();
if (count($suricatacfg['libhtp_policy']['item']) < 1) {
	$http_hosts_default_policy = "     personality: IDS\n     request-body-limit: 4096\n     response-body-limit: 4096\n";
	$http_hosts_default_policy .= "     double-decode-path: no\n     double-decode-query: no\n     uri-include-all: no\n";
}
else {
	foreach ($suricatacfg['libhtp_policy']['item'] as $k => $v) {
		if ($v['bind_to'] <> "all") {
			$engine = "server-config:\n     - {$v['name']}:\n";
			$tmp = trim(filter_expand_alias($v['bind_to']));
			if (!empty($tmp)) {
				$engine .= "         address: [";
				$tmp = preg_replace('/\s+/', ',', $tmp);
				$list = explode(',', $tmp);
				foreach ($list as $addr) {
					if (is_ipaddrv6($addr) || is_subnetv6($addr))
						$engine .= "\"{$addr}\", ";
					elseif (is_ipaddrv4($addr) || is_subnetv4($addr))
						$engine .= "{$addr}, ";
					else {
						syslog(LOG_WARN, "[suricata] WARNING: invalid IP address value '{$addr}' in Alias {$v['bind_to']} will be ignored.");
						continue;
					}
				}
				$engine = trim($engine, ' ,');
				$engine .= "]\n";
				$engine .= "         personality: {$v['personality']}\n         request-body-limit: {$v['request-body-limit']}\n";
				$engine .= "         response-body-limit: {$v['response-body-limit']}\n";
				$engine .= "         meta-field-limit: " . (isset($v['meta-field-limit']) ? $v['meta-field-limit'] : "18432") . "\n";
				$engine .= "         double-decode-path: {$v['double-decode-path']}\n";
				$engine .= "         double-decode-query: {$v['double-decode-query']}\n";
				$engine .= "         uri-include-all: {$v['uri-include-all']}\n";
				$http_hosts_policy .= "   {$engine}\n";
			}
			else {
				syslog(LOG_WARN, "[suricata] WARNING: unable to resolve IP List Alias '{$v['bind_to']}' for Host OS Policy '{$v['name']}' ... ignoring this entry.");
				continue;
			}
		}
		else {
			$http_hosts_default_policy = "     personality: {$v['personality']}\n     request-body-limit: {$v['request-body-limit']}\n";
			$http_hosts_default_policy .= "     response-body-limit: {$v['response-body-limit']}\n";
			$http_hosts_default_policy .= "     meta-field-limit: " . (isset($v['meta-field-limit']) ? $v['meta-field-limit'] : "18432") . "\n";
			$http_hosts_default_policy .= "     double-decode-path: {$v['double-decode-path']}\n";
			$http_hosts_default_policy .= "     double-decode-query: {$v['double-decode-query']}\n";
			$http_hosts_default_policy .= "     uri-include-all: {$v['uri-include-all']}\n";
		}
	}
	// Remove trailing newline
	$http_hosts_default_policy = trim($http_hosts_default_policy);
	$http_hosts_policy = trim($http_hosts_policy);
}

// Configure ASN1 max frames value
if (!empty($suricatacfg['asn1_max_frames']))
	$asn1_max_frames = $suricatacfg['asn1_max_frames'];
else
	$asn1_max_frames = "256";

// Configure App-Layer Parsers/Detection
if (!empty($suricatacfg['dcerpc_parser']))
	$dcerpc_parser = $suricatacfg['dcerpc_parser'];
else
	$dcerpc_parser = "yes";
if (!empty($suricatacfg['ftp_parser']))
	$ftp_parser = $suricatacfg['ftp_parser'];
else
	$ftp_parser = "yes";
if (!empty($suricatacfg['ssh_parser']))
	$ssh_parser = $suricatacfg['ssh_parser'];
else
	$ssh_parser = "yes";
if (!empty($suricatacfg['imap_parser']))
	$imap_parser = $suricatacfg['imap_parser'];
else
	$imap_parser = "detection-only";
if (!empty($suricatacfg['msn_parser']))
	$msn_parser = $suricatacfg['msn_parser'];
else
	$msn_parser = "detection-only";
if (!empty($suricatacfg['smb_parser']))
	$smb_parser = $suricatacfg['smb_parser'];
else
	$smb_parser = "yes";
if (!empty($suricatacfg['krb5_parser']))
	$krb5_parser = $suricatacfg['krb5_parser'];
else
	$krb5_parser = "yes";
if (!empty($suricatacfg['ikev2_parser']))
	$ikev2_parser = $suricatacfg['ikev2_parser'];
else
	$ikev2_parser = "yes";
if (!empty($suricatacfg['nfs_parser']))
	$nfs_parser = $suricatacfg['nfs_parser'];
else
	$nfs_parser = "yes";
if (!empty($suricatacfg['tftp_parser']))
	$tftp_parser = $suricatacfg['tftp_parser'];
else
	$tftp_parser = "yes";
if (!empty($suricatacfg['ntp_parser']))
	$ntp_parser = $suricatacfg['ntp_parser'];
else
	$ntp_parser = "yes";
if (!empty($suricatacfg['dhcp_parser']))
	$dhcp_parser = $suricatacfg['dhcp_parser'];
else
	$dhcp_parser = "yes";

/* DNS Parser */
if (!empty($suricatacfg['dns_parser_tcp']))
	$dns_parser_tcp = $suricatacfg['dns_parser_tcp'];
else
	$dns_parser_tcp = "yes";
if (!empty($suricatacfg['dns_parser_udp']))
	$dns_parser_udp = $suricatacfg['dns_parser_udp'];
else
	$dns_parser_udp = "yes";
if (!empty($suricatacfg['dns_parser_udp_ports'])) {
	if (is_alias($suricatacfg['dns_parser_udp_ports'])) {
		$dns_parser_udp_port = trim(filter_expand_alias($suricatacfg['dns_parser_udp_ports']));
		$dns_parser_udp_port = preg_replace('/\s+/', ', ', trim($dns_parser_udp_port));
	}
	else {
		$dns_parser_udp_port = $suricatacfg['dns_parser_udp_ports'];
	}
}
else {
	$dns_parser_udp_port = "443";
}
if (!empty($suricatacfg['dns_parser_tcp_ports'])) {
	if (is_alias($suricatacfg['dns_parser_tcp_ports'])) {
		$dns_parser_tcp_port = trim(filter_expand_alias($suricatacfg['dns_parser_tcp_ports']));
		$dns_parser_tcp_port = preg_replace('/\s+/', ', ', trim($dns_parser_tcp_port));
	}
	else {
		$dns_parser_tcp_port = $suricatacfg['dns_parser_tcp_ports'];
	}
}
else {
	$dns_parser_tcp_port = "443";
}
if (!empty($suricatacfg['dns_global_memcap']))
	$dns_global_memcap = $suricatacfg['dns_global_memcap'];
else
	$dns_global_memcap = "16777216";
if (!empty($suricatacfg['dns_state_memcap']))
	$dns_state_memcap = $suricatacfg['dns_state_memcap'];
else
	$dns_state_memcap = "524288";
if (!empty($suricatacfg['dns_request_flood_limit']))
	$dns_request_flood_limit = $suricatacfg['dns_request_flood_limit'];
else
	$dns_request_flood_limit = "500";

/* HTTP Parser */
if (!empty($suricatacfg['http_parser']))
	$http_parser = $suricatacfg['http_parser'];
else
	$http_parser = "yes";
if (!empty($suricatacfg['http_parser_memcap']))
	$http_parser_memcap = $suricatacfg['http_parser_memcap'];
else
	$http_parser_memcap = "67108864";

/* SMTP Parser */
if (!empty($suricatacfg['smtp_parser'])) {
	$smtp_parser = $suricatacfg['smtp_parser'];
}
else {
	$smtp_parser = "yes";
}
if ($suricatacfg['smtp_parser_decode_mime'] == "on") {
	$smtp_decode_mime = "yes";
}
else {
	$smtp_decode_mime = "no";
}
if ($suricatacfg['smtp_parser_decode_base64'] == "on") {
	$smtp_decode_base64 = "yes";
}
else {
	$smtp_decode_base64 = "no";
}
if ($suricatacfg['smtp_parser_decode_quoted_printable'] == "on") {
	$smtp_decode_quoted_printable = "yes";
}
else {
	$smtp_decode_quoted_printable = "no";
}
if ($suricatacfg['smtp_parser_extract_urls'] == "on") {
	$smtp_extract_urls = "yes";
}
else {
	$smtp_extract_urls = "no";
}
if ($suricatacfg['smtp_parser_compute_body_md5'] == "on") {
	$smtp_body_md5 = "yes";
}
else {
	$smtp_body_md5 = "no";
}

/* TLS Parser */
if (!empty($suricatacfg['tls_parser'])) {
	$tls_parser = $suricatacfg['tls_parser'];
}
else {
	$tls_parser = "yes";
}
if (!empty($suricatacfg['tls_detect_ports'])) {
	if (is_alias($suricatacfg['tls_detect_ports'])) {
		$tls_detect_port = trim(filter_expand_alias($suricatacfg['tls_detect_ports']));
		$tls_detect_port = preg_replace('/\s+/', ', ', trim($tls_detect_port));
	}
	else {
		$tls_detect_port = $suricatacfg['tls_detect_ports'];
	}
}
else {
	$tls_detect_port = "443";
}
if (!empty($suricatacfg['tls_ja3_fingerprint'])) {
	$tls_ja3 = $suricatacfg['tls_ja3_fingerprint'];
}
else {
	$tls_ja3 = "no";
}
if (!empty($suricatacfg['tls_encrypt_handling'])) {
	$tls_encrypt_handling = $suricatacfg['tls_encrypt_handling'];
}
else {
	$tls_encrypt_handling = "default";
}

/* Configure the IP REP section */
$iprep_path = rtrim(SURICATA_IPREP_PATH, '/');
$iprep_config = "# IP Reputation\n";
if ($suricatacfg['enable_iprep'] == "on") {
	$iprep_config .= "default-reputation-path: {$iprep_path}\n";
	$iprep_config .= "reputation-categories-file: {$iprep_path}/{$suricatacfg['iprep_catlist']}\n";
	$iprep_config .= "reputation-files:";

	if (!is_array($suricatacfg['iplist_files']))
		$suricatacfg['iplist_files'] = array();
	if (!is_array($suricatacfg['iplist_files']['item']))
		$suricatacfg['iplist_files']['item'] = array();

	foreach ($suricatacfg['iplist_files']['item'] as $f)
		$iprep_config .= "\n  - $f";
}

/* Configure Host Table settings */
if (!empty($suricatacfg['host_memcap']))
	$host_memcap = $suricatacfg['host_memcap'];
else
	$host_memcap = "33554432";
if (!empty($suricatacfg['host_hash_size']))
	$host_hash_size = $suricatacfg['host_hash_size'];
else
	$host_hash_size = "4096";
if (!empty($suricatacfg['host_prealloc']))
	$host_prealloc = $suricatacfg['host_prealloc'];
else
	$host_prealloc = "1000";

// Create the rules files and save in the interface directory
suricata_prepare_rule_files($suricatacfg, $suricatacfgdir);

// Check and configure only non-empty rules files for the interface
$rules_files = "";
if (filesize("{$suricatacfgdir}/rules/".SURICATA_ENFORCING_RULES_FILENAME) > 0)
	$rules_files .= SURICATA_ENFORCING_RULES_FILENAME;
if (filesize("{$suricatacfgdir}/rules/".FLOWBITS_FILENAME) > 0)
	$rules_files .= "\n - " . FLOWBITS_FILENAME;
if (filesize("{$suricatacfgdir}/rules/custom.rules") > 0)
	$rules_files .= "\n - custom.rules";
$rules_files = ltrim($rules_files, '\n -');

// Add the general logging settings to the configuration (non-interface specific)
if ($config['installedpackages']['suricata']['config'][0]['log_to_systemlog'] == 'on')
	$suricata_use_syslog = "yes";
else
	$suricata_use_syslog = "no";

if (!empty($config['installedpackages']['suricata']['config'][0]['log_to_systemlog']))
	$suricata_use_syslog_facility = $config['installedpackages']['suricata']['config'][0]['log_to_systemlog'];
else
	$suricata_use_syslog_facility = "local1";

// Configure IPS operational mode
if ($suricatacfg['ips_mode'] == 'ips_mode_inline' && $suricatacfg['blockoffenders'] == 'on') {
	// Note -- Netmap promiscuous mode logic is backwards from pcap
	$netmap_intf_promisc_mode = $intf_promisc_mode == 'yes' ? 'no' : 'yes';
	$suricata_ips_mode = <<<EOD
# Netmap
netmap:
 - interface: default
   threads: auto
   copy-mode: ips
   disable-promisc: {$netmap_intf_promisc_mode}
   checksum-checks: auto
 - interface: {$if_real}
   copy-iface: {$if_real}^
 - interface: {$if_real}^
   copy-iface: {$if_real}
EOD;
}
else {
	$suricata_ips_mode = <<<EOD
# PCAP
pcap:
  - interface: {$if_real}
    checksum-checks: auto
    promisc: {$intf_promisc_mode}
    snaplen: {$intf_snaplen}
EOD;
}

$suricata_config_pass_thru = base64_decode($suricatacfg['configpassthru']);

?>
