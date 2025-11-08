<?php
/*
 * suricata_generate_yaml.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2025 Bill Meeks
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
	$external_net = "[!\$HOME_NET]";
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
	"sip_servers" => "\$HOME_NET", "custom_servers" => ''
);
$addr_vars = "";
foreach ($suricata_servers as $alias => $avalue) {
	if (!empty($suricatacfg["def_{$alias}"]) && is_alias($suricatacfg["def_{$alias}"])) {
		$avalue = trim(filter_expand_alias($suricatacfg["def_{$alias}"]));
		$avalue = preg_replace('/\s+/', ', ', trim($avalue));
	}
	if (!empty($avalue)) {
		$addr_vars .= "    " . strtoupper($alias) . ": \"{$avalue}\"\n";
	}
}
if(config_get_path('system/ssh/port'))
        $ssh_port = config_get_path('system/ssh/port');
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
	"sip_ports" => "5060, 5061, 5600",
	"custom_ports" => ''
);
$port_vars = "";
foreach ($suricata_ports as $alias => $avalue) {
	if (!empty($suricatacfg["def_{$alias}"]) && is_alias($suricatacfg["def_{$alias}"])) {
		$avalue = trim(filter_expand_alias($suricatacfg["def_{$alias}"]));
		$avalue = preg_replace('/\s+/', ', ', trim($avalue));
	}
	if (!empty($avalue)) {
		$port_vars .= "    " . strtoupper($alias) . ": \"{$avalue}\"\n";
	}
}

// Process custom variables
foreach (array_get_path($suricatacfg, 'custom_vars/item', []) as $item) {
	if (empty($item['type']) || empty($item['name']) || empty($item['value'])) {
		continue;
	}
	$rule_string = ('    ' . strtoupper($item['name']) . ': "' .
		preg_replace('/\s+/', ', ', trim(trim(filter_expand_alias($item['value'])))) .
		"\"\n"
	);
	if ($item['type'] == 'server') {
		$addr_vars .= $rule_string;
	} else {
		$port_vars .= $rule_string;
	}
}

$addr_vars = trim($addr_vars);
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
if (!empty($suricatacfg['autofp_scheduler']))
	$autofp_scheduler = $suricatacfg['autofp_scheduler'];
else
	$autofp_scheduler = "hash";
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

if (!empty($suricatacfg['spm_algo']))
	$spm_algo = $suricatacfg['spm_algo'];
else
	$spm_algo = "auto";

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

if ($suricatacfg['passlist_debug_log'] == 'on')
	$suri_passlist_debugging = "yes";
else
	$suri_passlist_debugging = "no";

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

/****************************************/
/* Begin stats collection configuration */
/****************************************/
if ($suricatacfg['enable_stats_collection'] == 'on')
	$stats_collection_enabled = "yes";
else
	$stats_collection_enabled = "no";

if ($suricatacfg['enable_stats_collection'] == 'on' && $suricatacfg['enable_telegraf_stats'] == 'on' && !empty(base64_decode($suricatacfg['suricata_telegraf_unix_socket_name']))) {
	$enable_telegraf_eve = "yes";
	$telegraf_eve_sockname = base64_decode($suricatacfg['suricata_telegraf_unix_socket_name']);
}
else {
	$enable_telegraf_eve = "no";
	$telegraf_eve_sockname = "";
}

if (!empty($suricatacfg['stats_upd_interval']))
	$stats_upd_interval = $suricatacfg['stats_upd_interval'];
else
	$stats_upd_interval = "10";

if ($suricatacfg['append_stats_log'] == 'on')
	$stats_log_append = "yes";
else
	$stats_log_append = "no";

if ($suricatacfg['enable_stats_collection'] == 'on' && $suricatacfg['enable_stats_log'] == 'on') {
	$stats_log_enabled = "yes";
}
else {
	$stats_log_enabled = "no";
}
/****************************************/
/* End stats collection configuration   */
/****************************************/

// HTTP log configuration
if ($suricatacfg['enable_http_log'] == 'on')
	$http_log_enabled = "yes";
else
	$http_log_enabled = "no";

if ($suricatacfg['append_http_log'] == 'on')
	$http_log_append = "yes";
else
	$http_log_append = "no";

if ($suricatacfg['http_log_filetype'] == 'unix_dgram' || $suricatacfg['http_log_filetype'] == 'unix_stream') {
	$http_log_filetype = $suricatacfg['http_log_filetype'];
	$http_log_filename = base64_decode($suricatacfg['http_log_socket']);
} else {
	$http_log_filetype = "regular";
	$http_log_filename = "http.log";
}

if ($suricatacfg['http_log_extended'] == 'on')
	$http_log_extended = "yes";
else
	$http_log_extended = "no";

// TLS log configuration
if ($suricatacfg['enable_tls_log'] == 'on')
	$tls_log_enabled = "yes";
else
	$tls_log_enabled = "no";

if ($suricatacfg['append_tls_log'] == 'on')
	$tls_log_append = "yes";
else
	$tls_log_append = "no";

if ($suricatacfg['tls_log_filetype'] == 'unix_dgram' || $suricatacfg['tls_log_filetype'] == 'unix_stream') {
	$tls_log_filetype = $suricatacfg['tls_log_filetype'];
	$tls_log_filename = base64_decode($suricatacfg['tls_log_socket']);
} else {
	$tls_log_filetype = "regular";
	$tls_log_filename = "tls.log";
}

if ($suricatacfg['tls_log_extended'] == 'on')
	$tls_log_extended = "yes";
else
	$tls_log_extended = "no";

if ($suricatacfg['tls_session_resumption'] == 'on')
	$tls_session_resumption = "yes";
else
	$tls_session_resumption = "no";

// TLS certificate store configuration
if ($suricatacfg['enable_tls_store'] == 'on') {
	$tls_store_enabled = "yes";
	$tls_certs_dir = "{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}/certs";
	safe_mkdir($tls_certs_dir);
} else
	$tls_store_enabled = "no";

// File store configuration
if ($suricatacfg['enable_file_store'] == 'on') {
	$file_store_enabled = "yes";
	if (!empty($suricatacfg['file_store_logdir'])) {
		$file_store_logdir = base64_decode($suricatacfg['file_store_logdir']);
	}
	else {
		$file_store_logdir = "filestore";
	}
}
else {
	$file_store_enabled = "no";
	$file_store_logdir = "filestore";
}

// PCAP logging and capture options
if ($suricatacfg['enable_pcap_log'] == 'on') {
	$pcap_log_enabled = "yes";
	$pcap_log_dir = "{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}/pcaps";
	safe_mkdir($pcap_log_dir);
} else
	$pcap_log_enabled = "no";

if (!empty($suricatacfg['max_pcap_log_size']))
	$pcap_log_limit_size = $suricatacfg['max_pcap_log_size'];
else
	$pcap_log_limit_size = "32";

if (!empty($suricatacfg['max_pcap_log_files']))
	$pcap_log_max_files = $suricatacfg['max_pcap_log_files'];
else
	$pcap_log_max_files = "100";

if ($suricatacfg['pcap_use_stream_depth'] == "on")
	$pcap_use_stream_depth = "yes";
else
	$pcap_use_stream_depth = "no";
	
if ($suricatacfg['pcap_honor_pass_rules'] == "on")
	$pcap_honor_pass_rules = "yes";
else
	$pcap_honor_pass_rules = "no";

if (!empty($suricatacfg['pcap_log_conditional']))
	$pcap_log_conditional = $suricatacfg['pcap_log_conditional'];
else
	$pcap_log_conditional = "alerts";

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

if (!empty($suricatacfg['eve_output_type'])) {
	if ($suricatacfg['eve_output_type'] == 'unix_dgram' || $suricatacfg['eve_output_type'] == 'unix_stream') {
		$eve_output_type = $suricatacfg['eve_output_type'];
		$eve_output_filename = base64_decode($suricatacfg['eve_output_socket']);
	} else {
		$eve_output_type = $suricatacfg['eve_output_type'];
		$eve_output_filename = "eve.json";
	}
} else {
	$eve_output_type = "regular";
	$eve_output_filename = "eve.json";
}

// EVE SYSLOG output settings
if (!empty($suricatacfg['eve_systemlog_facility']))
	$eve_systemlog_facility = $suricatacfg['eve_systemlog_facility'];
else
	$eve_systemlog_facility = "local1";

if (!empty($suricatacfg['eve_systemlog_priority']))
	$eve_systemlog_priority = $suricatacfg['eve_systemlog_priority'];
else
	$eve_systemlog_priority = "info";

// EVE Ethernet headers setting
if (!empty($suricatacfg['eve_log_ethernet']))
	$eve_ethernet_output = $suricatacfg['eve_log_ethernet'];
else
	$eve_ethernet_output = "no";

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

if ($suricatacfg['eve_log_alerts'] == 'on') {
	$eve_out_types .= "\n        - alert:";
	$eve_out_types .= "\n            payload: " . (($suricatacfg['eve_log_alerts_payload'] == 'on' || $suricatacfg['eve_log_alerts_payload'] == 'only-base64') ? 'yes':'no ') . "              # enable dumping payload in Base64";
	$eve_out_types .= "\n            payload-buffer-size: 4kb  # max size of payload buffer to output in eve-log";
	$eve_out_types .= "\n            payload-printable: " . (($suricatacfg['eve_log_alerts_payload'] == 'on' || $suricatacfg['eve_log_alerts_payload'] == 'only-printable') ? 'yes':'no ') . "    # enable dumping payload in printable (lossy) format";
	$eve_out_types .= "\n            packet: " . ($suricatacfg['eve_log_alerts_packet'] == 'on' ? 'yes':'no ') . "               # enable dumping of packet (without stream segments)";
	$eve_out_types .= "\n            http-body: " . (($suricatacfg['eve_log_alerts_payload'] == 'on'|| $suricatacfg['eve_log_alerts_payload'] == 'only-base64') ?'yes':'no ') . "            # enable dumping of http body in Base64";
	$eve_out_types .= "\n            http-body-printable: " . (($suricatacfg['eve_log_alerts_payload'] == 'on' || $suricatacfg['eve_log_alerts_payload'] == 'only-printable') ? 'yes':'no ') . "  # enable dumping of http body in printable format";
	$eve_out_types .= "\n            metadata: " . ($suricatacfg['eve_log_alerts_metadata'] == 'on' ? 'yes':'no ') . "             # enable inclusion of app layer metadata with alert";
	$eve_out_types .= "\n            tagged-packets: " . ($suricatacfg['eve_log_alerts_tagged'] == 'on' ? 'yes':'no ') . "       # enable logging of tagged packets for rules using the 'tag' keyword";
	$eve_out_types .= "\n            verdict: " . ($suricatacfg['eve_log_alerts_verdict'] == 'on' ? 'yes':'no ') . "              # enable logging the final action taken on a packet by the engine";
}

if ($suricatacfg['eve_log_drops'] == 'on' ) {
	$eve_out_types .= "\n        - drop:";
	$eve_out_types .= "\n            alerts: " . ($suricatacfg['eve_log_alert_drops'] == 'on' ? 'yes':'no');
	$eve_out_types .= "\n            verdict: " . ($suricatacfg['eve_log_drops_verdict'] == 'on' ? 'yes':'no');
	$eve_out_types .= "\n            flows: " . ($suricatacfg['eve_log_drops_flows'] == 'start' ? 'start':'all');
}

if (($suricatacfg['eve_log_anomaly'] == 'on')) {
	$eve_out_types .= "\n        - anomaly:";
	$eve_out_types .= "\n            enabled: yes";
	$eve_out_types .= "\n            types:";
	if ($suricatacfg['eve_log_anomaly_type_decode'] == 'on') {
		$eve_out_types .= "\n              decode: yes";
	}
	else {
		$eve_out_types .= "\n              decode: no";
	}
	if ($suricatacfg['eve_log_anomaly_type_stream'] == 'on') {
		$eve_out_types .= "\n              stream: yes";
	}
	else {
		$eve_out_types .= "\n              stream: no";
	}
	if ($suricatacfg['eve_log_anomaly_type_applayer'] == 'on') {
		$eve_out_types .= "\n              applayer: yes";
	}
	else {
		$eve_out_types .= "\n              applayer: no";
	}
	if ($suricatacfg['eve_log_anomaly_packethdr'] == 'on') {
		$eve_out_types .= "\n            packethdr: yes";
	}
	else {
		$eve_out_types .= "\n            packethdr: no";
	}
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
	$eve_out_types .= "\n            requests: yes";
	$eve_out_types .= "\n            responses: yes";
}

if ($suricatacfg['eve_log_tls'] == 'on') {
	$eve_out_types .= "\n        - tls:";
	if ($suricatacfg['eve_log_tls_extended'] == 'on')
		$eve_out_types .= "\n            extended: yes";
	else
		$eve_out_types .= "\n            extended: no";
	if($suricatacfg['eve_log_tls_extended_fields'] != "")
		$eve_out_types .= "\n            custom: [".$suricatacfg['eve_log_tls_extended_fields']."]";
}

if ($suricatacfg['eve_log_files'] == 'on') {
	$eve_out_types .= "\n        - files:";
	if ($suricatacfg['eve_log_files_magic'] == 'on')
		$eve_out_types .= "\n            force-magic: yes";
	else
		$eve_out_types .= "\n            force-magic: no";
	if ($suricatacfg['eve_log_files_hash'] != 'none') {
		$eve_out_types .= "\n            force-hash: {$suricatacfg['eve_log_files_hash']}";
	}
}

if ($suricatacfg['eve_log_bittorrent'] == 'on') {
	$eve_out_types .= "\n        - bittorrent-dht";
}

if ($suricatacfg['eve_log_dhcp'] == 'on') {
	$eve_out_types .= "\n        - dhcp:";
	$eve_out_types .= "\n            enabled: yes";
	if ($suricatacfg['eve_log_dhcp_extended'] == 'on')
		$eve_out_types .= "\n            extended: yes";
	else
		$eve_out_types .= "\n            extended: no";
} else {
	$eve_out_types .= "\n        - dhcp:";
	$eve_out_types .= "\n            enabled: no";
}

if ($suricatacfg['eve_log_ftp'] == 'on') {
	$eve_out_types .= "\n        - ftp";
}

if ($suricatacfg['eve_log_http2'] == 'on') {
	$eve_out_types .= "\n        - http2";
}

if ($suricatacfg['eve_log_ikev2'] == 'on') {
	$eve_out_types .= "\n        - ike";
}

if ($suricatacfg['eve_log_krb5'] == 'on') {
	$eve_out_types .= "\n        - krb5";
}

if ($suricatacfg['eve_log_mqtt'] == 'on') {
	$eve_out_types .= "\n        - mqtt";
}

if ($suricatacfg['eve_log_nfs'] == 'on') {
	$eve_out_types .= "\n        - nfs";
}

if ($suricatacfg['eve_log_pgsql'] == 'on' && $suricatacfg['pgsql_parser'] == 'yes') {
	$eve_out_types .= "\n        - pgsql";
}

if ($suricatacfg['eve_log_quic'] == 'on') {
	$eve_out_types .= "\n        - quic";
}

if ($suricatacfg['eve_log_rdp'] == 'on') {
	$eve_out_types .= "\n        - rdp";
}

if ($suricatacfg['eve_log_rfb'] == 'on') {
	$eve_out_types .= "\n        - rfb";
}

if ($suricatacfg['eve_log_sip'] == 'on') {
	$eve_out_types .= "\n        - sip";
}

if ($suricatacfg['eve_log_smb'] == 'on') {
	$eve_out_types .= "\n        - smb";
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

if ($suricatacfg['eve_log_snmp'] == 'on') {
	$eve_out_types .= "\n        - snmp";
}

if ($suricatacfg['eve_log_ssh'] == 'on') {
	$eve_out_types .= "\n        - ssh";
}

if ($suricatacfg['eve_log_tftp'] == 'on') {
	$eve_out_types .= "\n        - tftp";
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

if (!empty($suricatacfg['defrag_memcap_policy']))
	$defrag_memcap_policy = $suricatacfg['defrag_memcap_policy'];
else
	$defrag_memcap_policy = "ignore";

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
	$flow_memcap = "134217728";

if (!empty($suricatacfg['flow_memcap_policy']))
	$flow_memcap_policy = $suricatacfg['flow_memcap_policy'];
else
	$flow_memcap_policy = "ignore";

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
	$stream_memcap = "268435456";

if (!empty($suricatacfg['stream_memcap_policy']))
	$stream_memcap_policy = $suricatacfg['stream_memcap_policy'];
else
	$stream_memcap_policy = "ignore";

if (!empty($suricatacfg['stream_prealloc_sessions']))
	$stream_prealloc_sessions = $suricatacfg['stream_prealloc_sessions'];
else
	$stream_prealloc_sessions = "32768";

if (!empty($suricatacfg['reassembly_memcap']))
	$reassembly_memcap = $suricatacfg['reassembly_memcap'];
else
	$reassembly_memcap = "131217728";

if (!empty($suricatacfg['reassembly_memcap_policy']))
	$reassembly_memcap_policy = $suricatacfg['reassembly_memcap_policy'];
else
	$reassembly_memcap_policy = "ignore";

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

if (!empty($suricatacfg['midstream_policy']))
	$midstream_policy = $suricatacfg['midstream_policy'];
else
	$midstream_policy = "ignore";

if ($suricatacfg['stream_checksum_validation'] == 'on')
	$stream_checksum_validation = "yes";
else
	$stream_checksum_validation = "no";

if ($suricatacfg['enable_async_sessions'] == 'on')
	$stream_enable_async = "true";
else
	$stream_enable_async = "false";

if ($suricatacfg['stream_bypass'] == 'on' || $suricatacfg['tls_encrypt_handling'] == 'bypass')
	$stream_bypass_enable = "yes";
else
	$stream_bypass_enable = "no";

if ($suricatacfg['stream_drop_invalid'] == 'on')
	$stream_drop_invalid_enable = "yes";
else
	$stream_drop_invalid_enable = "no";

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
						logger(LOG_WARNING, localize_text("invalid IP address value '%s' in Alias %s will be ignored.", $addr, $v['bind_to']), LOG_PREFIX_PKG_SURICATA);
				}
				$engine = trim($engine, ' ,');
				$engine .= "]";
			}
			else {
				logger(LOG_WARNING, localize_text("unable to resolve IP List Alias '%s' for Host OS Policy '%s' ... ignoring this entry.", $v['bind_to'], $v['name']), LOG_PREFIX_PKG_SURICATA);
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
	$http_hosts_default_policy = "personality: IDS\n     request-body-limit: 4096\n     response-body-limit: 4096\n";
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
						logger(LOG_WARNING, localize_text("invalid IP address value '%s' in Alias %s will be ignored.", $addr, $v['bind_to']), LOG_PREFIX_PKG_SURICATA);
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
				logger(LOG_WARNING, localize_text("unable to resolve IP List Alias '%s' for Host OS Policy '%s' ... ignoring this entry.", $v['bind_to'], $v['name']), LOG_PREFIX_PKG_SURICATA);
				continue;
			}
		}
		else {
			$http_hosts_default_policy = "personality: {$v['personality']}\n     request-body-limit: {$v['request-body-limit']}\n";
			$http_hosts_default_policy .= "     response-body-limit: {$v['response-body-limit']}\n";
			$http_hosts_default_policy .= "     meta-field-limit: " . (isset($v['meta-field-limit']) ? $v['meta-field-limit'] : "18432") . "\n";
			$http_hosts_default_policy .= "     double-decode-path: {$v['double-decode-path']}\n";
			$http_hosts_default_policy .= "     double-decode-query: {$v['double-decode-query']}\n";
			$http_hosts_default_policy .= "     uri-include-all: {$v['uri-include-all']}\n";
		}
	}
	// Remove any leading or trailing spaces and newline
	$http_hosts_default_policy = trim($http_hosts_default_policy);
	$http_hosts_policy = trim($http_hosts_policy);
}

// Configure ASN1 max frames value
if (!empty($suricatacfg['asn1_max_frames']))
	$asn1_max_frames = $suricatacfg['asn1_max_frames'];
else
	$asn1_max_frames = "256";

// Configure App-Layer Parsers/Detection
if (!empty($suricatacfg['app_layer_error_policy']))
	$app_layer_error_policy = $suricatacfg['app_layer_error_policy'];
else
	$app_layer_error_policy = "ignore";

if (!empty($suricatacfg['bittorrent_parser']))
	$bittorrent_parser = $suricatacfg['bittorrent_parser'];
else
	$bittorrent_parser = "no";

if (!empty($suricatacfg['dcerpc_parser']))
	$dcerpc_parser = $suricatacfg['dcerpc_parser'];
else
	$dcerpc_parser = "yes";

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

/* ENIP Parser */
if (!empty($suricatacfg['enip_parser']))
	$enip_parser = $suricatacfg['enip_parser'];
else
	$enip_parser = "yes";

/* FTP Parser */
if (!empty($suricatacfg['ftp_parser']))
	$ftp_parser = $suricatacfg['ftp_parser'];
else
	$ftp_parser = "yes";

if ($suricatacfg['ftp_data_parser'] == 'on')
	$ftp_data_parser = "yes";
else
	$ftp_data_parser = "no";

/* HTTP Parser */
if (!empty($suricatacfg['http_parser']))
	$http_parser = $suricatacfg['http_parser'];
else
	$http_parser = "yes";

if (!empty($suricatacfg['http_parser_memcap']))
	$http_parser_memcap = $suricatacfg['http_parser_memcap'];
else
	$http_parser_memcap = "67108864";

if (!empty($suricatacfg['http2_parser']))
	$http2_parser = $suricatacfg['http2_parser'];
else
	$http2_parser = "no";

if (!empty($suricatacfg['ikev2_parser']))
	$ikev2_parser = $suricatacfg['ikev2_parser'];
else
	$ikev2_parser = "yes";

if (!empty($suricatacfg['imap_parser']))
	$imap_parser = $suricatacfg['imap_parser'];
else
	$imap_parser = "detection-only";

if (!empty($suricatacfg['krb5_parser']))
	$krb5_parser = $suricatacfg['krb5_parser'];
else
	$krb5_parser = "yes";

/* MQTT Parser */
if (!empty($suricatacfg['mqtt_parser']))
	$mqtt_parser = $suricatacfg['mqtt_parser'];
else
	$mqtt_parser = "yes";

if (!empty($suricatacfg['msn_parser']))
	$msn_parser = $suricatacfg['msn_parser'];
else
	$msn_parser = "detection-only";

if (!empty($suricatacfg['nfs_parser']))
	$nfs_parser = $suricatacfg['nfs_parser'];
else
	$nfs_parser = "yes";

if (!empty($suricatacfg['ntp_parser']))
	$ntp_parser = $suricatacfg['ntp_parser'];
else
	$ntp_parser = "yes";

/* PostgreSQL Parser */
if (!empty($suricatacfg['pgsql_parser']))
	$pgsql_parser = $suricatacfg['pgsql_parser'];
else
	$pgsql_parser = "no";

/* QUICv1 Parser */
if (!empty($suricatacfg['quic_parser']))
	$quic_parser = $suricatacfg['quic_parser'];
else
	$quic_parser = "yes";

 /* RDP Parser */
if (!empty($suricatacfg['rdp_parser'])) {
	$rdp_parser = $suricatacfg['rdp_parser'];
}
else {
	$rdp_parser = "yes";
}

if (!empty($suricatacfg['rfb_parser']))
	$rfb_parser = $suricatacfg['rfb_parser'];
else
	$rfb_parser = "yes";

/* SIP Parser */
if (!empty($suricatacfg['sip_parser'])) {
	$sip_parser = $suricatacfg['sip_parser'];
}
else {
	$sip_parser = "yes";
}

if (!empty($suricatacfg['smb_parser']))
	$smb_parser = $suricatacfg['smb_parser'];
else
	$smb_parser = "yes";

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

/* SNMP Parser */
if (!empty($suricatacfg['snmp_parser'])) {
	$snmp_parser = $suricatacfg['snmp_parser'];
}
else {
	$snmp_parser = "yes";
}

if (!empty($suricatacfg['ssh_parser']))
	$ssh_parser = $suricatacfg['ssh_parser'];
else
	$ssh_parser = "yes";

if (!empty($suricatacfg['telnet_parser']))
	$telnet_parser = $suricatacfg['telnet_parser'];
else
	$telnet_parser = "yes";

if (!empty($suricatacfg['tftp_parser']))
	$tftp_parser = $suricatacfg['tftp_parser'];
else
	$tftp_parser = "yes";

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
	$tls_ja3 = "auto";
}
if (!empty($suricatacfg['tls_encrypt_handling'])) {
	$tls_encrypt_handling = $suricatacfg['tls_encrypt_handling'];

	// If TLS encryption bypass is enabled, then stream bypass
	// must also be forced to "yes" for bypass to happen.
	if ($tls_encrypt_handling == "bypass") {
		$stream_bypass_enable = "yes";
	}
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
if (file_exists("{$suricatacfgdir}/rules/".SURICATA_ENFORCING_RULES_FILENAME)) {
	if (filesize("{$suricatacfgdir}/rules/".SURICATA_ENFORCING_RULES_FILENAME) > 0)
		$rules_files .= SURICATA_ENFORCING_RULES_FILENAME;
}
if (file_exists("{$suricatacfgdir}/rules/".FLOWBITS_FILENAME)) {
	if (filesize("{$suricatacfgdir}/rules/".FLOWBITS_FILENAME) > 0)
		$rules_files .= "\n - " . FLOWBITS_FILENAME;
}
if (file_exists("{$suricatacfgdir}/rules/custom.rules")) {
	if (filesize("{$suricatacfgdir}/rules/custom.rules") > 0)
		$rules_files .= "\n - custom.rules";
}
$rules_files = ltrim($rules_files, '\n -');

// Add the general logging settings to the configuration (non-interface specific)
if (config_get_path('installedpackages/suricata/config/0/log_to_systemlog') == 'on')
	$suricata_use_syslog = "yes";
else
	$suricata_use_syslog = "no";

if (!empty(config_get_path('installedpackages/suricata/config/0/log_to_systemlog_facility')))
	$suricata_use_syslog_facility = config_get_path('installedpackages/suricata/config/0/log_to_systemlog_facility');
else
	$suricata_use_syslog_facility = "local1";

if (!empty(config_get_path('installedpackages/suricata/config/0/log_to_systemlog_priority')))
	$suricata_use_syslog_priority = config_get_path('installedpackages/suricata/config/0/log_to_systemlog_priority');
else
	$suricata_use_syslog_priority = "notice";

// Configure IPS operational mode
$livedev_tracking = "true";
if ($suricatacfg['ips_mode'] == 'ips_mode_inline' && $suricatacfg['blockoffenders'] == 'on') {
	// Get 'netmap_threads' parameter, if set
	$netmap_threads_param = 'auto';
	if (intval($suricatacfg['ips_netmap_threads']) > 0) {
		$netmap_threads_param = $suricatacfg['ips_netmap_threads'];
	}

	$if_netmap = $if_real;
	$livedev_tracking = "false";

	// For VLAN interfaces, need to actually run Suricata
	// on the parent interface, so override interface name.
	if (interface_is_vlan($if_real)) {
		$intf_list = get_parent_interface($if_real);
		$if_netmap = $intf_list[0];
		logger(LOG_WARNING, localize_text("interface '%s' is a VLAN, so configuring Suricata to run on the parent interface, '%s', instead.", $if_real, $if_netmap), LOG_PREFIX_PKG_SURICATA);
	}

	// Note -- Netmap promiscuous mode logic is backwards from pcap
	$netmap_intf_promisc_mode = $intf_promisc_mode == 'yes' ? 'no' : 'yes';
	$suricata_ips_mode = <<<EOD
# Netmap
netmap:
 - interface: default
   threads: {$netmap_threads_param}
   copy-mode: ips
   disable-promisc: {$netmap_intf_promisc_mode}
   checksum-checks: auto
 - interface: {$if_netmap}
   threads: {$netmap_threads_param}
   copy-mode: ips
   copy-iface: {$if_netmap}^
 - interface: {$if_netmap}^
   threads: {$netmap_threads_param}
   copy-mode: ips
   copy-iface: {$if_netmap}
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

// Create UNIX control socket for Suricata binary
$unix_socket_name = "{$g['varrun_path']}/suricata-ctrl-socket-{$suricata_uuid}";

// Populate optional user configuration if present
if (!empty($suricatacfg['configpassthru']))
	$suricata_config_pass_thru = base64_decode($suricatacfg['configpassthru']);
else
	$suricata_config_pass_thru = "";

return true;
?>
