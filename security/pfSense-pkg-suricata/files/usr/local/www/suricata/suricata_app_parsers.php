<?php
/*
 * suricata_app_parsers.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
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

require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g, $rebuild_rules;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id))
	$id = 0;

// Initialize Suricata interface and HTTP libhtp engine arrays if necessary
init_config_arr( array( 'installedpackages' , 'suricata' , 'rule') );
init_config_arr( array('installedpackages', 'suricata', 'rule', $id, 'libhtp_policy', 'item') );

// Initialize required array variables as necessary
init_config_arr( array('aliases', 'alias') );
$a_aliases = config_get_path('aliases/alias', []);

$a_nat = config_get_path("installedpackages/suricata/rule/{$id}", []);

$libhtp_engine_next_id = count(array_get_path($a_nat, 'libhtp_policy/item', []));

// Build a lookup array of currently used engine 'bind_to' Aliases
// so we can screen matching Alias names from the list.
$used = array();
foreach (array_get_path($a_nat, 'libhtp_policy/item', []) as $v)
	$used[$v['bind_to']] = true;

$pconfig = array();
if (isset($id) && !empty($a_nat)) {
	/* Get current values from config for page form fields */
	$pconfig = $a_nat;
	if (empty($pconfig['app_layer_error_policy']))
		$pconfig['app_layer_error_policy'] = "ignore";

	// See if Host-OS policy engine array is configured and use
	// it; otherwise create a default engine configuration.
	if (!array_get_path($pconfig, 'libhtp_policy/item')) {
		$default = array( "name" => "default", "bind_to" => "all", "personality" => "IDS",
				  "request-body-limit" => 4096, "response-body-limit" => 4096,
				  "double-decode-path" => "no", "double-decode-query" => "no",
				  "uri-include-all" => "no", "meta-field-limit" => 18432 );
		array_init_path($pconfig, 'libhtp_policy/item');
		$pconfig['libhtp_policy']['item'][] = $default;
		array_init_path($a_nat, 'libhtp_policy/item');
		$a_nat['libhtp_policy']['item'][] = $default;
		config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
		write_config("Suricata pkg: created a new default HTTP server configuration for " . convert_friendly_interface_to_friendly_descr($a_nat['interface']));
		$libhtp_engine_next_id++;
	}
	else
		$pconfig['libhtp_policy'] = $a_nat['libhtp_policy'];
}

// Check for "import or select alias mode" and set flags if TRUE.
// "selectalias", when true, displays radio buttons to limit
// multiple selections.
if ($_POST['import_alias']) {
	$eng_id = $libhtp_engine_next_id;
	$importalias = true;
	$selectalias = false;
	$title = "HTTP Server Policy";
}
elseif ($_POST['select_alias']) {
	$importalias = true;
	$selectalias = true;
	$title = "HTTP Server Policy";

	// Preserve current Libhtp Policy Engine settings
	$eng_id = $_POST['eng_id'];
	$eng_name = $_POST['policy_name'];
	$eng_bind = $_POST['policy_bind_to'];
	$eng_personality = $_POST['personality'];
	$eng_req_body_limit = $_POST['req_body_limit'];
	$eng_resp_body_limit = $_POST['resp_body_limit'];
	$eng_meta_field_limit = $_POST['meta_field_limit'];
	$eng_enable_double_decode_path = $_POST['enable_double_decode_path'];
	$eng_enable_double_decode_query = $_POST['enable_double_decode_query'];
	$eng_enable_uri_include_all = $_POST['enable_uri_include_all'];
	$mode = "add_edit_libhtp_policy";
}

if ($_POST['save_libhtp_policy']) {
	if ($_POST['eng_id'] != "") {
		$eng_id = $_POST['eng_id'];

		// Grab all the POST values and save in new temp array
		$engine = array();
		$policy_name = trim($_POST['policy_name']);
		if ($policy_name) {
			$engine['name'] = $policy_name;
		}
		else
			$input_errors[] = gettext("The 'Policy Name' value cannot be blank.");

		if ($_POST['policy_bind_to']) {
			if (is_alias($_POST['policy_bind_to']))
				$engine['bind_to'] = $_POST['policy_bind_to'];
			elseif (strtolower(trim($_POST['policy_bind_to'])) == "all")
				$engine['bind_to'] = "all";
			else
				$input_errors[] = gettext("You must provide a valid Alias or the reserved keyword 'all' for the 'Bind-To IP Address' value.");
		}
		else
			$input_errors[] = gettext("The 'Bind-To IP Address' value cannot be blank.  Provide a valid Alias or the reserved keyword 'all'.");

		if ($_POST['personality']) { $engine['personality'] = $_POST['personality']; } else { $engine['personality'] = "bsd"; }

		if (is_numeric($_POST['req_body_limit']) && $_POST['req_body_limit'] >= 0)
			$engine['request-body-limit'] = $_POST['req_body_limit'];
		else
			$input_errors[] = gettext("The value for 'Request Body Limit' must be all numbers and greater than or equal to zero.");

		if (is_numeric($_POST['resp_body_limit']) && $_POST['resp_body_limit'] >= 0)
			$engine['response-body-limit'] = $_POST['resp_body_limit'];
		else
			$input_errors[] = gettext("The value for 'Response Body Limit' must be all numbers and greater than or equal to zero.");

		if (is_numeric($_POST['meta_field_limit']) && $_POST['meta_field_limit'] >= 0)
			$engine['meta-field-limit'] = $_POST['meta_field_limit'];
		else
			$input_errors[] = gettext("The value for 'Meta-Field Limit' must be all numbers and greater than or equal to zero.");

		if ($_POST['enable_double_decode_path']) { $engine['double-decode-path'] = 'yes'; }else{ $engine['double-decode-path'] = 'no'; }
		if ($_POST['enable_double_decode_query']) { $engine['double-decode-query'] = 'yes'; }else{ $engine['double-decode-query'] = 'no'; }
		if ($_POST['enable_uri_include_all']) { $engine['uri-include-all'] = 'yes'; }else{ $engine['uri-include-all'] = 'no'; }

		// Can only have one "all" Bind_To address
		if ($engine['bind_to'] == "all" && $engine['name'] != "default")
			$input_errors[] = gettext("Only one default OS-Policy Engine can be bound to all addresses.");

		// if no errors, write new entry to conf
		if (!$input_errors) {
			if (isset($eng_id) && isset($a_nat['libhtp_policy']['item'][$eng_id])) {
				$a_nat['libhtp_policy']['item'][$eng_id] = $engine;
			}
			else
				$a_nat['libhtp_policy']['item'][] = $engine;

			/* Reorder the engine array to ensure the */
			/* 'bind_to=all' entry is at the bottom   */
			/* if it contains more than one entry.	  */
			if (count($a_nat['libhtp_policy']['item']) > 1) {
				$i = -1;
				foreach ($a_nat['libhtp_policy']['item'] as $f => $v) {
					if ($v['bind_to'] == "all") {
						$i = $f;
						break;
					}
				}
				/* Only relocate the entry if we  */
				/* found it, and it's not already */
				/* at the end.					  */
				if ($i > -1 && ($i < (count($a_nat['libhtp_policy']['item']) - 1))) {
					$tmp = $a_nat['libhtp_policy']['item'][$i];
					unset($a_nat['libhtp_policy']['item'][$i]);
					$a_nat['libhtp_policy']['item'][] = $tmp;
				}
			}

			// Now write the new engine array to conf
			config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
			write_config("Suricata pkg: saved updated HTTP server configuration for " . convert_friendly_interface_to_friendly_descr($a_nat['interface']));
			$add_edit_libhtp_policy = false;
			header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
			header( 'Cache-Control: no-store, no-cache, must-revalidate' );
			header( 'Cache-Control: post-check=0, pre-check=0', false );
			header( 'Pragma: no-cache' );
			header("Location: suricata_app_parsers.php?id=$id");
			exit;
		}
		else {
			$add_edit_libhtp_policy = true;
			$pengcfg = $engine;
		}
	}
}
elseif ($_POST['add_libhtp_policy']) {
	$add_edit_libhtp_policy = true;
	$pengcfg = array( "name" => "engine_{$libhtp_engine_next_id}", "bind_to" => "", "personality" => "IDS",
			  "request-body-limit" => "4096", "response-body-limit" => "4096", "meta-field-limit" => 18432, 
			  "double-decode-path" => "no", "double-decode-query" => "no", "uri-include-all" => "no" );
	$eng_id = $libhtp_engine_next_id;
}
elseif ($_POST['edit_libhtp_policy']) {
	if ($_POST['eng_id'] != "") {
		$add_edit_libhtp_policy = true;
		$eng_id = $_POST['eng_id'];
		$pengcfg = $a_nat['libhtp_policy']['item'][$eng_id];
	}
}
elseif ($_POST['del_libhtp_policy']) {
	$natent = array();
	$natent = $pconfig;

	if ($_POST['eng_id'] != "") {
		unset($natent['libhtp_policy']['item'][$_POST['eng_id']]);
		$pconfig = $natent;
	}
	if (isset($id) && isset($a_nat)) {
		$a_nat = $natent;
		config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
		write_config("Suricata pkg: deleted a HTTP server configuration for " . convert_friendly_interface_to_friendly_descr($a_nat['interface']));
	}
	$add_edit_libhtp_policy = false;
	header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
	header( 'Cache-Control: no-store, no-cache, must-revalidate' );
	header( 'Cache-Control: post-check=0, pre-check=0', false );
	header( 'Pragma: no-cache' );
	header("Location: suricata_app_parsers.php?id=$id");
	exit;
}
elseif ($_POST['cancel_libhtp_policy']) {
	$add_edit_libhtp_policy = false;
}
elseif ($_POST['ResetAll']) {

	/* Reset all the settings to defaults */
	$pconfig['app_layer_error_policy'] = "ignore";
	$pconfig['asn1_max_frames'] = "256";
	$pconfig['bittorrent_parser'] = "yes";
	$pconfig['dcerpc_parser'] = "yes";
	$pconfig['dhcp_parser'] = "yes";
	$pconfig['dns_global_memcap'] = "16777216";
	$pconfig['dns_state_memcap'] = "524288";
	$pconfig['dns_request_flood_limit'] = "500";
	$pconfig['dns_parser_udp'] = "yes";
	$pconfig['dns_parser_udp_ports'] = "53";
	$pconfig['dns_parser_tcp'] = "yes";
	$pconfig['dns_parser_tcp_ports'] = "53";
	$pconfig['enip_parser'] = "yes";
	$pconfig['ftp_parser'] = "yes";
	$pconfig['ftp_data_parser'] = "on";
	$pconfig['http_parser'] = "yes";
	$pconfig['http_parser_memcap'] = "67108864";
	$pconfig['http2_parser'] = "yes";
	$pconfig['ikev2_parser'] = "yes";
	$pconfig['imap_parser'] = "detection-only";
	$pconfig['krb5_parser'] = "yes";
	$pconfig['mqtt_parser'] = "yes";
	$pconfig['msn_parser'] = "detection-only";
	$pconfig['nfs_parser'] = "yes";
	$pconfig['ntp_parser'] = "yes";
	$pconfig['pgsql_parser'] = "no";
	$pconfig['quic_parser'] = "yes";
	$pconfig['rfb_parser'] = "yes";
	$pconfig['rdp_parser'] = "yes";
	$pconfig['sip_parser'] = "yes";
	$pconfig['smb_parser'] = "yes";
	$pconfig['smtp_parser'] = "yes";
	$pconfig['smtp_parser_decode_mime'] = "off";
	$pconfig['smtp_parser_decode_base64'] = "on";
	$pconfig['smtp_parser_decode_quoted_printable'] = "on";
	$pconfig['smtp_parser_extract_urls'] = "on";
	$pconfig['smtp_parser_compute_body_md5'] = "off";
	$pconfig['snmp_parser'] = "yes";
	$pconfig['ssh_parser'] = "yes";
	$pconfig['tftp_parser'] = "yes";
	$pconfig['tls_parser'] = "yes";
	$pconfig['tls_detect_ports'] = "443";
	$pconfig['tls_encrypt_handling'] = "default";
	$pconfig['tls_ja3_fingerprint'] = "auto";
	$pconfig['telnet_parser'] = "yes";

	/* Log a message at the top of the page to inform the user */
	$savemsg = gettext("All flow and stream settings on this page have been reset to their defaults.  Click APPLY if you wish to keep these new settings.");
}
elseif ($_POST['save_import_alias']) {
	// If saving out of "select alias" mode,
	// then return to Libhtp Policy Engine edit
	// page.
	if ($_POST['mode'] == 'add_edit_libhtp_policy') {
		$pengcfg = array();
		$eng_id = $_POST['eng_id'];
		$pengcfg['name'] = $_POST['eng_name'];
		$pengcfg['bind_to'] = $_POST['eng_bind'];
		$pengcfg['personality'] = $_POST['eng_personality'];
		$pengcfg['request-body-limit'] = $_POST['eng_req_body_limit'];
		$pengcfg['response-body-limit'] = $_POST['eng_resp_body_limit'];
		$pengcfg['meta-field-limit'] = $_POST['eng_meta_field_limit'];
		$pengcfg['double-decode-path'] = $_POST['eng_enable_double_decode_path'];
		$pengcfg['double-decode-query'] = $_POST['eng_enable_double_decode_query'];
		$pengcfg['uri-include-all'] = $_POST['eng_enable_uri_include_all'];
		$add_edit_libhtp_policy = true;
		$mode = "add_edit_libhtp_policy";

		if (is_array($_POST['aliastoimport']) && count($_POST['aliastoimport']) == 1) {
			$pengcfg['bind_to'] = $_POST['aliastoimport'][0];
			$importalias = false;
			$selectalias = false;
		}
		else {
			$input_errors[] = gettext("No Alias is selected for import.  Nothing to SAVE.");
			$importalias = true;
			$selectalias = true;
			$eng_id = $_POST['eng_id'];
			$eng_name = $_POST['eng_name'];
			$eng_bind = $_POST['eng_bind'];
			$eng_personality = $_POST['eng_personality'];
			$eng_req_body_limit = $_POST['eng_req_body_limit'];
			$eng_resp_body_limit = $_POST['eng_resp_body_limit'];
			$eng_meta_field_limit = $_POST['eng_meta_field_limit'];
			$eng_enable_double_decode_path = $_POST['eng_enable_double_decode_path'];
			$eng_enable_double_decode_query = $_POST['eng_enable_double_decode_query'];
			$eng_enable_uri_include_all = $_POST['eng_enable_uri_include_all'];
		}
	}
	else {
		$engine = array( "name" => "", "bind_to" => "", "personality" => "IDS",
				 "request-body-limit" => "4096", "response-body-limit" => "4096", "meta-field-limit" => 18432, 
				 "double-decode-path" => "no", "double-decode-query" => "no", "uri-include-all" => "no" );

		// See if anything was checked to import
		if (is_array($_POST['aliastoimport']) && count($_POST['aliastoimport']) > 0) {
			foreach ($_POST['aliastoimport'] as $item) {
				$engine['name'] = strtolower($item);
				$engine['bind_to'] = $item;
				$a_nat['libhtp_policy']['item'][] = $engine;
			}
		}
		else {
			$input_errors[] = gettext("No entries were selected for import. Please select one or more Aliases for import and click SAVE.");
			$importalias = true;
		}

		// if no errors, write new entry to conf
		if (!$input_errors) {
			// Reorder the engine array to ensure the
			// 'bind_to=all' entry is at the bottom if
			// the array contains more than one entry.
			if (count($a_nat['libhtp_policy']['item']) > 1) {
				$i = -1;
				foreach ($a_nat['libhtp_policy']['item'] as $f => $v) {
					if ($v['bind_to'] == "all") {
						$i = $f;
						break;
					}
				}
				// Only relocate the entry if we
				// found it, and it's not already
				// at the end.
				if ($i > -1 && ($i < (count($a_nat['libhtp_policy']['item']) - 1))) {
					$tmp = $a_nat['libhtp_policy']['item'][$i];
					unset($a_nat['libhtp_policy']['item'][$i]);
					$a_nat['libhtp_policy']['item'][] = $tmp;
				}
				$pconfig['libhtp_policy']['item'] = $a_nat['libhtp_policy']['item'];
			}

			// Write the new engine array to config file
			config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
			write_config("Suricata pkg: saved an updated HTTP server configuration for " . convert_friendly_interface_to_friendly_descr($a_nat['interface']));
			$importalias = false;
			header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
			header( 'Cache-Control: no-store, no-cache, must-revalidate' );
			header( 'Cache-Control: post-check=0, pre-check=0', false );
			header( 'Pragma: no-cache' );
			header("Location: suricata_app_parsers.php?id=$id");
			exit;
		}
	}
}
elseif ($_POST['cancel_import_alias']) {
	$importalias = false;
	$selectalias = false;
	$eng_id = $_POST['eng_id'];

	// If cancelling out of "select alias" mode,
	// then return to Libhtp Policy Engine edit
	// page.
	if ($_POST['mode'] == 'add_edit_libhtp_policy') {
		$pengcfg = array();
		$pengcfg['name'] = $_POST['eng_name'];
		$pengcfg['bind_to'] = $_POST['eng_bind'];
		$pengcfg['personality'] = $_POST['eng_personality'];
		$pengcfg['request-body-limit'] = $_POST['eng_req_body_limit'];
		$pengcfg['response-body-limit'] = $_POST['eng_resp_body_limit'];
		$pengcfg['meta-field-limit'] = $_POST['eng_meta_field_limit'];
		$pengcfg['double-decode-path'] = $_POST['eng_enable_double_decode_path'];
		$pengcfg['double-decode-query'] = $_POST['eng_enable_double_decode_query'];
		$pengcfg['uri-include-all'] = $_POST['eng_enable_uri_include_all'];
		$add_edit_libhtp_policy = true;
	}
}
elseif ($_POST['save'] || $_POST['apply']) {
	$natent = array();
	$natent = $pconfig;

	// TODO: validate input values
	if (!is_numeric($_POST['asn1_max_frames'] ) || $_POST['asn1_max_frames'] < 1)
		$input_errors[] = gettext("The value for 'ASN1 Max Frames' must be all numbers and greater than 0.");

	if (!is_numeric($_POST['dns_global_memcap'] ) || $_POST['dns_global_memcap'] < 1)
		$input_errors[] = gettext("The value for 'DNS Global Memcap' must be all numbers and greater than 0.");

	if (!is_numeric($_POST['dns_state_memcap'] ) || $_POST['dns_state_memcap'] < 1)
		$input_errors[] = gettext("The value for 'DNS Flow/State Memcap' must be all numbers and greater than 0.");

	if (!is_numeric($_POST['dns_request_flood_limit'] ) || $_POST['dns_request_flood_limit'] < 1)
		$input_errors[] = gettext("The value for 'DNS Request Flood Limit' must be all numbers and greater than 0.");

	if (!is_numeric($_POST['http_parser_memcap'] ) || $_POST['http_parser_memcap'] < 1)
		$input_errors[] = gettext("The value for 'HTTP Memcap' must be all numbers and greater than 0.");

	if (is_alias($_POST['tls_detect_ports']) && trim(filter_expand_alias($_POST['tls_detect_ports'])) == "") {
		$input_errors[] = gettext("An invalid Port alias was specified for TLS Detect Ports.");
	}

	if (is_alias($_POST['dns_parser_udp_ports']) && trim(filter_expand_alias($_POST['dns_parser_udp_ports'])) == "") {
		$input_errors[] = gettext("An invalid Port alias was specified for DNS Parser UDP Detect Ports.");
	}

	if (is_alias($_POST['dns_parser_tcp_ports']) && trim(filter_expand_alias($_POST['dns_parser_tcp_ports'])) == "") {
		$input_errors[] = gettext("An invalid Port alias was specified for DNS Parser TCP Detect Ports.");
	}

	/* if no errors write to conf */
	if (!$input_errors) {
		if ($_POST['app_layer_error_policy'] != "") { $natent['app_layer_error_policy'] = $_POST['app_layer_error_policy']; }
		if ($_POST['asn1_max_frames'] != "") { $natent['asn1_max_frames'] = $_POST['asn1_max_frames']; }else{ $natent['asn1_max_frames'] = "256"; }
		if ($_POST['dns_global_memcap'] != ""){ $natent['dns_global_memcap'] = $_POST['dns_global_memcap']; }else{ $natent['dns_global_memcap'] = "16777216"; }
		if ($_POST['dns_state_memcap'] != ""){ $natent['dns_state_memcap'] = $_POST['dns_state_memcap']; }else{ $natent['dns_state_memcap'] = "524288"; }
		if ($_POST['dns_request_flood_limit'] != ""){ $natent['dns_request_flood_limit'] = $_POST['dns_request_flood_limit']; }else{ $natent['dns_request_flood_limit'] = "500"; }
		if ($_POST['http_parser_memcap'] != ""){ $natent['http_parser_memcap'] = $_POST['http_parser_memcap']; }else{ $natent['http_parser_memcap'] = "67108864"; }

		$natent['dns_parser_udp'] = $_POST['dns_parser_udp'];
		$natent['dns_parser_tcp'] = $_POST['dns_parser_tcp'];
		$natent['dns_parser_udp_ports'] = $_POST['dns_parser_udp_ports'];
		$natent['dns_parser_tcp_ports'] = $_POST['dns_parser_tcp_ports'];
		$natent['http_parser'] = $_POST['http_parser'];
		$natent['tls_parser'] = $_POST['tls_parser'];
		$natent['tls_detect_ports'] = $_POST['tls_detect_ports'];
		$natent['tls_encrypt_handling'] = $_POST['tls_encrypt_handling'];
		$natent['tls_ja3_fingerprint'] = $_POST['tls_ja3_fingerprint'];
		$natent['smtp_parser'] = $_POST['smtp_parser'];
		$natent['smtp_parser_decode_mime'] = $_POST['smtp_parser_decode_mime'];
		$natent['smtp_parser_decode_base64'] = $_POST['smtp_parser_decode_base64'];
		$natent['smtp_parser_decode_quoted_printable'] = $_POST['smtp_parser_decode_quoted_printable'];
		$natent['smtp_parser_extract_urls'] = $_POST['smtp_parser_extract_urls'];
		$natent['smtp_parser_compute_body_md5'] = $_POST['smtp_parser_compute_body_md5'];
		$natent['imap_parser'] = $_POST['imap_parser'];
		$natent['ssh_parser'] = $_POST['ssh_parser'];
		$natent['ftp_parser'] = $_POST['ftp_parser'];
		$natent['ftp_data_parser'] = $_POST['ftp_data_parser'];
		$natent['dcerpc_parser'] = $_POST['dcerpc_parser'];
		$natent['smb_parser'] = $_POST['smb_parser'];
		$natent['msn_parser'] = $_POST['msn_parser'];
		$natent['krb5_parser'] = $_POST['krb5_parser'];
		$natent['ikev2_parser'] = $_POST['ikev2_parser'];
		$natent['nfs_parser'] = $_POST['nfs_parser'];
		$natent['tftp_parser'] = $_POST['tftp_parser'];
		$natent['ntp_parser'] = $_POST['ntp_parser'];
		$natent['dhcp_parser'] = $_POST['dhcp_parser'];
		$natent['rdp_parser'] = $_POST['rdp_parser'];
		$natent['sip_parser'] = $_POST['sip_parser'];
		$natent['snmp_parser'] = $_POST['snmp_parser'];
		$natent['http2_parser'] = $_POST['http2_parser'];
		$natent['rfb_parser'] = $_POST['rfb_parser'];
		$natent['enip_parser'] = $_POST['enip_parser'];
		$natent['mqtt_parser'] = $_POST['mqtt_parser'];
		$natent['bittorrent_parser'] = $_POST['bittorrent_parser'];
		$natent['pgsql_parser'] = $_POST['pgsql_parser'];
		$natent['quic_parser'] = $_POST['quic_parser'];

		/**************************************************/
		/* If we have a valid rule ID, save configuration */
		/* then update the suricata.conf file for this	  */
		/* interface.									  */
		/**************************************************/
		if (isset($id) && $a_nat) {
			$a_nat = $natent;
			config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
			write_config("Suricata pkg: saved updated app-layer parser configuration for " . convert_friendly_interface_to_friendly_descr($a_nat['interface']));
			$rebuild_rules = false;
			suricata_generate_yaml($natent);

			// Sync to configured CARP slaves if any are enabled
			suricata_sync_on_changes();
		}

		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: suricata_app_parsers.php?id=$id");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($pconfig['interface']);
$pglinks = array("", "/suricata/suricata_interfaces.php", "/suricata/suricata_interfaces_edit.php?id={$id}", "@self");
$pgtitle = array("Services", "Suricata", "Interface Settings", "{$if_friendly} - App Layer Parsers");
include_once("head.inc");

/* Display error message */
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	/* Display save message */
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), true, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php?instance={$id}");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php?instance={$id}");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$id}");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
$tab_array = array();
$tab_array[] = array($menu_iface . gettext("Settings"), false, "/suricata/suricata_interfaces_edit.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Categories"), false, "/suricata/suricata_rulesets.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Rules"), false, "/suricata/suricata_rules.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Flow/Stream"), false, "/suricata/suricata_flow_stream.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("App Parsers"), true, "/suricata/suricata_app_parsers.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Variables"), false, "/suricata/suricata_define_vars.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/suricata/suricata_ip_reputation.php?id={$id}");
display_top_tabs($tab_array, true);
?>

<?php

if ($importalias) {

	print('<form action="suricata_app_parsers.php" method="post" name="iform" id="iform" class="form-horizontal">');
	print('<input name="id" type="hidden" value="' . $id . '"/>');
	print('<input type="hidden" name="eng_id" id="eng_id" value="' . $eng_id . '"/>');

	if ($selectalias) {
		print('<input type="hidden" name="eng_name" value="' . $eng_name . '"/>');
		print('<input type="hidden" name="eng_bind" value="' . $eng_bind . '"/>');
		print('<input type="hidden" name="eng_personality" value="' . $eng_personality . '"/>');
		print('<input type="hidden" name="eng_req_body_limit" value="' . $eng_req_body_limit . '"/>');
		print('<input type="hidden" name="eng_resp_body_limit" value="' . $eng_resp_body_limit . '"/>');
		print('<input type="hidden" name="eng_meta_field_limit" value="' . $eng_meta_field_limit . '"/>');
		print('<input type="hidden" name="eng_enable_double_decode_path" value="' . $eng_enable_double_decode_path . '"/>');
		print('<input type="hidden" name="eng_enable_double_decode_query" value="' . $eng_enable_double_decode_query . '"/>');
		print('<input type="hidden" name="eng_enable_uri_include_all" value="' . $eng_enable_uri_include_all . '"/>');
	}

	include("/usr/local/www/suricata/suricata_import_aliases.php");
	print('</form>');

} elseif ($add_edit_libhtp_policy) {

	include("/usr/local/www/suricata/suricata_libhtp_policy_engine.php");

} else {

	print('<form action="suricata_app_parsers.php" method="post" name="iform" id="iform" class="form-horizontal">');
	print('<input name="id" type="hidden" value="' . $id . '"/>');
	print('<input type="hidden" name="eng_id" id="eng_id" value=""/>');

	$section= new Form_Section('App-Layer Error Policy Settings');
	$section->addInput(new Form_Select(
		'app_layer_error_policy',
		'Application Layer Parser Exception Policy',
		$pconfig['app_layer_error_policy'],
		array( "drop-flow" => "Drop Flow", "pass-flow" => "Pass Flow", "bypass" => "Bypass", "drop-packet" => "Drop Packet",
			   "pass-packet" => "Pass Packet", "reject" => "Reject", "ignore" => "Ignore" )
	))->setHelp('Apply selected policy if an application layer parser reaches an error state. Default is "Ignore". ' .
				'"Drop Flow" will disable inspection for the whole flow (packets, payload, and application layer protocol), drop ' .
				'the packet and all future packets in the flow. "Drop Packet" drops the current packet. "Reject" is the same as "Drop Flow" ' .
				'but rejects the current packet as well. "Bypass" will bypass the flow, and no further inspection is done. ' .
				'"Pass Flow" will disable payload and packet detection, but stream reassembly, app-layer parsing and logging still happen. ' .
				'"Pass Packet" will disable detection, but still does stream updates and app-layer parsing (depending on which policy triggered it). ' .
				'"Ignore" does not apply exception policies.');
	print($section);

	$section = new Form_Section('Abstract Syntax One App-Layer Parser Settings');
	$section->addInput(new Form_Input(
		'asn1_max_frames',
		'Asn1 Max Frames',
		'text',
		$pconfig['asn1_max_frames']
	))->setHelp('Limit for max number of asn1 frames to decode. Default is 256 frames. To protect itself, Suricata will inspect only the maximum asn1 frames specified. Application layer protocols such as X.400 electronic mail, X.500 and LDAP directory services, H.323 (VoIP), and SNMP, use ASN.1 to describe the protocol data units (PDUs) they exchange.');
	print($section);

	$section = new Form_Section('DNS App-Layer Parser Settings');
	$section->addInput(new Form_Select(
		'dns_parser_udp',
		'UDP Parser',
		$pconfig['dns_parser_udp'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'dns_parser_tcp',
		'TCP Parser',
		$pconfig['dns_parser_tcp'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Input(
		'dns_parser_udp_ports',
		'UDP Detection Port',
		'text',
		$pconfig['dns_parser_udp_ports']
	))->setHelp('Enter comma-separated list (or a Port alias) of ports for the DNS UDP parser. Default is 53.');
	$section->addInput(new Form_Input(
		'dns_parser_tcp_ports',
		'TCP Detection Port',
		'text',
		$pconfig['dns_parser_tcp_ports']
	))->setHelp('Enter comma-separated list (or a Port alias) of ports for the DNS TCP parser. Default is 53.');
	$section->addInput(new Form_Input(
		'dns_global_memcap',
		'Global Memcap',
		'text',
		$pconfig['dns_global_memcap']
	))->setHelp('Sets the global memcap limit for the DNS parser. Default is 16777216 bytes (16MB).');
	$section->addInput(new Form_Input(
		'dns_state_memcap',
		'Flow/State Memcap',
		'text',
		$pconfig['dns_state_memcap']
	))->setHelp('Sets per flow/state memcap limit for the DNS parser. Default is 524288 bytes (512KB).');
	$section->addInput(new Form_Input(
		'dns_request_flood_limit',
		'Request Flood Limit',
		'text',
		$pconfig['dns_request_flood_limit']
	))->setHelp('How many unreplied DNS requests are considered a flood. Default is 500 requests. If this limit is reached, \'app-layer-event:dns.flooded\' will match and alert.');
	print($section);

	$section = new Form_Section('SMTP App-Layer Parser Settings');
	$section->addInput(new Form_Select(
		'smtp_parser',
		'SMTP Parser',
		$pconfig['smtp_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for SMTP. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Checkbox(
		'smtp_parser_decode_mime',
		'Enable MIME Decoding',
		'Suricata will decode MIME messages from SMTP transactions.  Note this may be resource intensive! Default is Not Checked.',
		$pconfig['smtp_parser_decode_mime'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'smtp_parser_decode_base64',
		'Base64 MIME Decoding',
		'Suricata will decode Base64 MIME entity bodies. Default is Checked.',
		$pconfig['smtp_parser_decode_base64'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'smtp_parser_decode_quoted_printable',
		'Quoted-Printable MIME Decoding',
		'Suricata will decode quoted-printable MIME entity bodies. Default is Checked.',
		$pconfig['smtp_parser_decode_quoted_printable'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'smtp_parser_extract_urls',
		'MIME URL Extraction',
		'Suricata will Extract URLs and save in state data structure. Default is Checked.',
		$pconfig['smtp_parser_extract_urls'] == 'on' ? true:false,
		'on'
	));
	$section->addInput(new Form_Checkbox(
		'smtp_parser_compute_body_md5',
		'MIME Body MD5 Calculation',
		'Suricata will compute the md5 of the mail body so it can be journalized. Default is Not Checked.',
		$pconfig['smtp_parser_compute_body_md5'] == 'on' ? true:false,
		'on'
	));
	print($section);

	$section = new Form_Section('TLS App-Layer Parser Settings');
	$section->addInput(new Form_Select(
		'tls_parser',
		'TLS Parser',
		$pconfig['tls_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for TLS. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Input(
		'tls_detect_ports',
		'Detection Ports',
		'text',
		$pconfig['tls_detect_ports']
	))->setHelp('Enter a comma-separated list of ports (or port alias) to examine for TLS traffic (e.g., 443, 8443). Default is 443.');
	$section->addInput(new Form_Select(
		'tls_encrypt_handling',
		'Encryption Handling',
		$pconfig['tls_encrypt_handling'],
		array(  "default" => "Default", "bypass" => "Bypass", "full" => "Full" )
	))->setHelp('What to do when the encrypted communications start. "Default" keeps tracking the TLS session to check for protocol anomalies and inspect tls_* keywords; "Bypass" stops ' . 
		    'processing this flow as much as possible; and "Full" keeps tracking and inspection as normal including unmodified content keyword signatures.  For best performance, select "Bypass".');
	$section->addInput(new Form_Checkbox(
		'tls_ja3_fingerprint',
		'JA3/JA3S Fingerprint',
		'Suricata will generate JA3/JA3S fingerprint from client hello. Default is Not Checked, which disables fingerprinting unless required by the rules.',
		$pconfig['tls_ja3_fingerprint'] == 'on' ? true:false,
		'on'
	));
	print($section);

	$section = new Form_Section('FTP App-Layer Parser Settings');
	$section->addInput(new Form_Select(
		'ftp_parser',
		'FTP Parser',
		$pconfig['ftp_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for FTP. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Checkbox(
		'ftp_data_parser',
		'FTP DATA parser',
		'Suricata will process FTP DATA port transfers. This feature is needed to save FTP uploads/download when File Store feature is enabled.',
		$pconfig['ftp_data_parser'] == 'on' ? true:false,
		'on'
	));
	print($section);

	$section = new Form_Section('Other App-Layer Parser Settings');
	$section->addInput(new Form_Select(
		'bittorrent_parser',
		'BitTorrent-DHT Parser',
		$pconfig['bittorrent_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for BitTorrent-DHT. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'dcerpc_parser',
		'DCERPC Parser',
		$pconfig['dcerpc_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for DCERPC. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'dhcp_parser',
		'DHCP Parser',
		$pconfig['dhcp_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for DHCP. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'enip_parser',
		'ENIP Parser',
		$pconfig['enip_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for ENIP. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'http2_parser',
		'HTTP2 Parser',
		$pconfig['http2_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for HTTP2. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'ikev2_parser',
		'IKE Parser',
		$pconfig['ikev2_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for IKE. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'imap_parser',
		'IMAP Parser',
		$pconfig['imap_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for IMAP. Default is detection-only. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'krb5_parser',
		'Kerberos Parser',
		$pconfig['krb5_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for Kerberos. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'mqtt_parser',
		'MQTT Parser',
		$pconfig['mqtt_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for MQTT. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'msn_parser',
		'MSN Parser',
		$pconfig['msn_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for MSN. Default is detection-only. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'nfs_parser',
		'NFS Parser',
		$pconfig['nfs_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for NFS. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'ntp_parser',
		'NTP Parser',
		$pconfig['ntp_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for NTP. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'pgsql_parser',
		'PostgreSQL Parser',
		$pconfig['pgsql_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for PostgreSQL. Default is "no". Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'quic_parser',
		'QUICv1 Parser',
		$pconfig['quic_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for QUICv1. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'rdp_parser',
		'RDP Parser',
		$pconfig['rdp_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for RDP. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'rfb_parser',
		'RFB Parser',
		$pconfig['rfb_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for RFB. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'ssh_parser',
		'SSH Parser',
		$pconfig['ssh_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for SSH. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'sip_parser',
		'SIP Parser',
		$pconfig['sip_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for SIP. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'smb_parser',
		'SMB Parser',
		$pconfig['smb_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for SMB. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'snmp_parser',
		'SNMP Parser',
		$pconfig['snmp_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for SNMP. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'telnet_parser',
		'Telnet Parser',
		$pconfig['telnet_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for Telnet. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'tftp_parser',
		'TFTP Parser',
		$pconfig['tftp_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for TFTP. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');

	print($section);

?>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('HTTP App-Layer Parser Settings');?></h2></div>
		<div class="panel-body">
			<div class="form-group">
				<label class="col-sm-2 control-label">
					<?=gettext("Memcap"); ?>
				</label>
				<div class="col-sm-10">
					<input name="http_parser_memcap" type="text" class="form-control" id="http_parser_memcap" size="9" value="<?=htmlspecialchars($pconfig['http_parser_memcap'])?>">
					<span class="help-block">Sets the memcap limit for the HTTP parser. Default is 67108864 bytes (64MB).</span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">
					<?=gettext("HTTP Parser"); ?>
				</label>
				<div class="col-sm-10">
					<select name="http_parser" id="http_parser" class="form-control">
						<?php
							$opt = array(  "yes", "no", "detection-only" );
							foreach ($opt as $val) {
								$selected = "";
								if ($val == $pconfig['http_parser'])
									$selected = " selected";
								echo "<option value='{$val}'{$selected}>" . $val . "</option>\n";
							}
						?>
					</select>
					<span class="help-block">Choose the parser/detection setting for HTTP. Default is yes. electing "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.</span>
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
									<th><?=gettext("Name")?></th>
									<th><?=gettext("Bind-To Address Alias")?></th>
									<th>
										<button type="submit" name="import_alias" class="btn btn-sm btn-primary" title="<?=gettext("Import server configuration from existing Aliases")?>" value="Import">
											<i class="fa-solid fa-upload icon-embed-btn"></i>
											<?=gettext("Import"); ?>
										</button>
										<button type="submit" name="add_libhtp_policy" class="btn btn-sm btn-success" title="<?=gettext("Add a new server configuration")?>" value="Add">
											<i class="fa-solid fa-plus icon-embed-btn"></i>
											<?=gettext("Add"); ?>
										</button>
									</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($pconfig['libhtp_policy']['item'] as $f => $v): ?>
								<tr>
									<td><?=gettext($v['name'])?></td>
									<td class="text-center"><?=gettext($v['bind_to'])?></td>
									<td class="text-right">
										<button type="submit" name="edit_libhtp_policy" value="Edit" class="btn btn-sm btn-primary" onclick="$('#eng_id').val('<?=$f?>')" title="<?=gettext("Edit this server configuration")?>">
											<i class="fa-solid fa-pencil icon-embed-btn"></i>
											<?=gettext("Edit"); ?>
										</button>
									<?php if ($v['bind_to'] != "all") : ?>
										<button type="submit" name="del_libhtp_policy" value="Delete" class="btn btn-sm btn-danger" onclick="$('#eng_id').val('<?=$f?>');" title="<?=gettext("Delete this server configuration")?>">
											<i class="fa-solid fa-trash-can icon-embed-btn"></i>
											<?=gettext("Delete"); ?>
										</button>
									<?php else : ?>
										<button type="submit" name="del_libhtp_policy" value="Delete" class="btn btn-sm btn-danger" title="<?=gettext("Delete this server configuration")?>" disabled>
											<i class="fa-solid fa-trash-can icon-embed-btn"></i>
											<?=gettext("Delete"); ?>
										</button>
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

	<div class="col-sm-10 col-sm-offset-2">
		<button type="submit" id="save" name="save" value="Save" class="btn btn-primary" title="<?=gettext('Save App Parsers settings');?>">
			<i class="fa-solid fa-save icon-embed-btn"></i>
			<?=gettext('Save');?>
		</button>
	</div>

</form>

<?php } ?>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

	function toggle_smtp_mime_decoding() {
		if ($('#smtp_parser').val() == 'yes') {
			hideCheckbox('smtp_parser_decode_mime', false);
			var hide = ! ($('#smtp_parser_decode_mime').prop('checked'));
			hideCheckbox('smtp_parser_decode_base64',hide);
			hideCheckbox('smtp_parser_decode_quoted_printable',hide);
			hideCheckbox('smtp_parser_extract_urls',hide);
			hideCheckbox('smtp_parser_compute_body_md5',hide);
		}
		else {
			hideCheckbox('smtp_parser_decode_mime', true);
			hideCheckbox('smtp_parser_decode_base64',true);
			hideCheckbox('smtp_parser_decode_quoted_printable',true);
			hideCheckbox('smtp_parser_extract_urls',true);
			hideCheckbox('smtp_parser_compute_body_md5',true);
		}
	}

	function toggle_tls_parser() {
		if ($('#tls_parser').val() == 'yes') {
			hideInput('tls_detect_ports', false);
			hideSelect('tls_encrypt_handling', false);
			hideCheckbox('tls_ja3_fingerprint', false);
		}
		else {
			hideInput('tls_detect_ports', true);
			hideSelect('tls_encrypt_handling', true);
			hideCheckbox('tls_ja3_fingerprint', true);
		}
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------
	// When form control id is clicked, disable/enable it's associated form controls

	$('#smtp_parser_decode_mime').click(function() {
		toggle_smtp_mime_decoding();
	});

	// ---------- Selection control handlers ---------------------------------------------------------
	// When form control selection changes, disable/enable it's associated form controls
	$('#smtp_parser').on('change', function() {
		toggle_smtp_mime_decoding();
	});

	$('#tls_parser').on('change', function() {
		toggle_tls_parser();
	});

	// ---------- On initial page load ------------------------------------------------------------
	var portsarray = <?= json_encode(get_alias_list(array("port"))) ?>;

	$('#tls_detect_ports').autocomplete({
		source: portsarray
	});
	$('#dns_parser_udp_ports').autocomplete({
		source: portsarray
	});
	$('#dns_parser_tcp_ports').autocomplete({
		source: portsarray
	});

	toggle_smtp_mime_decoding();
	toggle_tls_parser();

});
//]]>
</script>

<?php include("foot.inc"); ?>
