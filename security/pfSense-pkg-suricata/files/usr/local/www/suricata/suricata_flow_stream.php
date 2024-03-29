<?php
/*
 * suricata_flow_stream.php
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
	$id=0;

$a_aliases = config_get_path('aliases/alias', []);
$a_nat = config_get_path("installedpackages/suricata/rule/{$id}", []);
$host_os_policy_engine_next_id = count(array_get_path($a_nat, 'host_os_policy/item', []));

// Build a lookup array of currently used engine 'bind_to' Aliases
// so we can screen matching Alias names from the list.
$used = array();
foreach (array_get_path($a_nat, 'host_os_policy/item', []) as $v)
	$used[$v['bind_to']] = true;

$pconfig = array();
if (isset($id) && !empty($a_nat)) {
	/* Get current values from config for page form fields */
	$pconfig = $a_nat;
	if (empty($pconfig['stream_memcap_policy']))
		$pconfig['stream_memcap_policy'] = "ignore";
	if (empty($pconfig['midstream_policy']))
		$pconfig['midstream_policy'] = "ignore";
	if (empty($pconfig['defrag_memcap_policy']))
		$pconfig['defrag_memcap_policy'] = "ignore";
	if (empty($pconfig['reassembly_memcap_policy']))
		$pconfig['reassembly_memcap_policy'] = "ignore";
	if (empty($pconfig['flow_memcap_policy']))
		$pconfig['flow_memcap_policy'] = "ignore";
	if (empty($pconfig['stream_checksum_validation']))
		$pconfig['stream_checksum_validation'] = "on";

	// See if Host-OS policy engine array is configured and use
	// it; otherwise create a default engine configuration.
	if (empty($pconfig['host_os_policy']['item'])) {
		$default = array( "name" => "default", "bind_to" => "all", "policy" => "bsd" );
		$pconfig['host_os_policy']['item'] = array();
		$pconfig['host_os_policy']['item'][] = $default;
		array_init_path($a_nat, 'host_os_policy/item');
		$a_nat['host_os_policy']['item'][] = $default;
		config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
		write_config("Suricata pkg: saved new default Host_OS_Policy engine.");
		$host_os_policy_engine_next_id++;
	}
	else
		$pconfig['host_os_policy'] = $a_nat['host_os_policy'];
}

// Check for "import or select alias mode" and set flags if TRUE.
// "selectalias", when true, displays radio buttons to limit
// multiple selections.
if ($_POST['import_alias']) {
	$eng_id = $host_os_policy_engine_next_id;
	$importalias = true;
	$selectalias = false;
	$title = "Host Operating System Policy";
}
elseif ($_POST['select_alias']) {
	$importalias = true;
	$selectalias = true;
	$title = "Host Operating System Policy";

	// Preserve current OS Policy Engine settings
	$eng_id = $_POST['eng_id'];
	$eng_name = $_POST['policy_name'];
	$eng_bind = $_POST['policy_bind_to'];
	$eng_policy = $_POST['policy'];
	$mode = "add_edit_os_policy";
}

if ($_POST['save_os_policy']) {
	if ($_POST['eng_id'] != "") {
		$eng_id = $_POST['eng_id'];

		// Grab all the POST values and save in new temp array
		$engine = array();
		$policy_name = trim($_POST['policy_name']);
		if ($policy_name) {
			$engine['name'] = $policy_name;
		}
		else {
			$input_errors[] = gettext("The 'Policy Name' value cannot be blank.");
			$add_edit_os_policy = true;
		}
		if ($_POST['policy_bind_to']) {
			if (is_alias($_POST['policy_bind_to']))
				$engine['bind_to'] = $_POST['policy_bind_to'];
			elseif (strtolower(trim($_POST['policy_bind_to'])) == "all")
				$engine['bind_to'] = "all";
			else {
				$input_errors[] = gettext("You must provide a valid Alias or the reserved keyword 'all' for the 'Bind-To IP Address' value.");
				$add_edit_os_policy = true;
			}
		}
		else {
			$input_errors[] = gettext("The 'Bind-To IP Address' value cannot be blank.  Provide a valid Alias or the reserved keyword 'all'.");
			$add_edit_os_policy = true;
		}

		if ($_POST['policy']) { $engine['policy'] = $_POST['policy']; } else { $engine['policy'] = "bsd"; }

		// Can only have one "all" Bind_To address
		if ($engine['bind_to'] == "all" && $engine['name'] != "default") {
			$input_errors[] = gettext("Only one default OS-Policy Engine can be bound to all addresses.");
			$add_edit_os_policy = true;
			$pengcfg = $engine;
		}

		// if no errors, write new entry to conf
		if (!$input_errors) {
			if (isset($eng_id) && array_get_path($a_nat, "host_os_policy/item/{$eng_id}")) {
				$a_nat['host_os_policy']['item'][$eng_id] = $engine;
			}
			else
				$a_nat['host_os_policy']['item'][] = $engine;

			/* Reorder the engine array to ensure the */
			/* 'bind_to=all' entry is at the bottom   */
			/* if it contains more than one entry.	*/
			if (count(array_get_path($a_nat, 'host_os_policy/item', [])) > 1) {
				$i = -1;
				foreach (array_get_path($a_nat, 'host_os_policy/item', []) as $f => $v) {
					if ($v['bind_to'] == "all") {
						$i = $f;
						break;
					}
				}
				/* Only relocate the entry if we  */
				/* found it, and it's not already */
				/* at the end.					*/
				if ($i > -1 && ($i < (count(array_get_path($a_nat, 'host_os_policy/item', [])) - 1))) {
					$tmp = array_get_path($a_nat, "host_os_policy/item/{$i}", []);
					array_del_path($a_nat, "host_os_policy/item/{$i}");
					$a_nat['host_os_policy']['item'][] = $tmp;
				}
			}

			// Now write the new engine array to conf
			config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
			write_config("Suricata pkg: saved new Host_OS_Policy engine.");
			header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
			header( 'Cache-Control: no-store, no-cache, must-revalidate' );
			header( 'Cache-Control: post-check=0, pre-check=0', false );
			header( 'Pragma: no-cache' );
			header("Location: suricata_flow_stream.php?id=$id");
			exit;
		}
	}
}
elseif ($_POST['add_os_policy']) {
	$add_edit_os_policy = true;
	$pengcfg = array( "name" => "engine_{$host_os_policy_engine_next_id}", "bind_to" => "", "policy" => "bsd" );
	$eng_id = $host_os_policy_engine_next_id;
}
elseif ($_POST['edit_os_policy']) {
	if ($_POST['eng_id'] != "") {
		$add_edit_os_policy = true;
		$eng_id = $_POST['eng_id'];
		$pengcfg = $a_nat['host_os_policy']['item'][$eng_id];
	}
}
elseif ($_POST['del_os_policy']) {
	$natent = array();
	$natent = $pconfig;

	if ($_POST['eng_id'] != "") {
		array_del_path($natent, "host_os_policy/item/{$_POST['eng_id']}");
		$pconfig = $natent;
	}
	if (isset($id) && !empty($a_nat)) {
		$a_nat = $natent;
		config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
		write_config("Suricata pkg: deleted a Host_OS_Policy engine.");
	}
	header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
	header( 'Cache-Control: no-store, no-cache, must-revalidate' );
	header( 'Cache-Control: post-check=0, pre-check=0', false );
	header( 'Pragma: no-cache' );
	header("Location: suricata_flow_stream.php?id=$id");
	exit;
}
elseif ($_POST['cancel_os_policy']) {
	$add_edit_os_policy = false;
}
elseif ($_POST['ResetAll']) {

	/* Reset all the settings to defaults */
	$pconfig['defrag_memcap_policy'] = "ignore";
	$pconfig['ip_max_frags'] = "65535";
	$pconfig['ip_frag_timeout'] = "60";
	$pconfig['frag_memcap'] = '33554432';
	$pconfig['ip_max_trackers'] = '65535';
	$pconfig['frag_hash_size'] = '65536';

	$pconfig['flow_memcap'] = '134217728';
	$pconfig['flow_memcap_policy'] = 'ignore';
	$pconfig['flow_prealloc'] = '10000';
	$pconfig['flow_hash_size'] = '65536';
	$pconfig['flow_emerg_recovery'] = '30';
	$pconfig['flow_prune'] = '5';

	$pconfig['flow_tcp_new_timeout'] = '60';
	$pconfig['flow_tcp_established_timeout'] = '3600';
	$pconfig['flow_tcp_closed_timeout'] = '120';
	$pconfig['flow_tcp_emerg_new_timeout'] = '10';
	$pconfig['flow_tcp_emerg_established_timeout'] = '300';
	$pconfig['flow_tcp_emerg_closed_timeout'] = '20';

	$pconfig['flow_udp_new_timeout'] = '30';
	$pconfig['flow_udp_established_timeout'] = '300';
	$pconfig['flow_udp_emerg_new_timeout'] = '10';
	$pconfig['flow_udp_emerg_established_timeout'] = '100';

	$pconfig['flow_icmp_new_timeout'] = '30';
	$pconfig['flow_icmp_established_timeout'] = '300';
	$pconfig['flow_icmp_emerg_new_timeout'] = '10';
	$pconfig['flow_icmp_emerg_established_timeout'] = '100';

	// The default 'stream_memcap' value must be calculated as follows:
	// 216 * prealloc_sessions * number of threads = memory use in bytes
	// 128 MB is a decent all-around default, but some setups need more.
	$pconfig['stream_prealloc_sessions'] = '32768';
	$pconfig['stream_memcap'] = '268435456';
	$pconfig['reassembly_memcap'] = '134217728';
	$pconfig['reassembly_depth'] = '1048576';
	$pconfig['reassembly_to_server_chunk'] = '2560';
	$pconfig['reassembly_to_client_chunk'] = '2560';
	$pconfig['enable_midstream_sessions'] = 'off';
	$pconfig['stream_memcap_policy'] = 'ignore';
	$pconfig['midstream_policy'] = 'ignore';
	$pconfig['reassembly_memcap_policy'] = 'ignore';
	$pconfig['enable_async_sessions'] = 'off';
	$pconfig['max_synack_queued'] = '5';
	$pconfig['stream_bypass'] = "no";
	$pconfig['stream_drop_invalid'] = "no";

	/* Log a message at the top of the page to inform the user */
	$savemsg = gettext("All flow and stream settings have been reset to their defaults.  Click APPLY to save the changes.");
}
elseif ($_POST['save'] || $_POST['apply']) {
	$natent = array();
	$natent = $pconfig;

	// TODO: validate input values

	/* if no errors write to conf */
	if (!$input_errors) {
		if ($_POST['ip_max_frags'] != "") { $natent['ip_max_frags'] = $_POST['ip_max_frags']; }else{ $natent['ip_max_frags'] = "65535"; }
		if ($_POST['ip_frag_timeout'] != "") { $natent['ip_frag_timeout'] = $_POST['ip_frag_timeout']; }else{ $natent['ip_frag_timeout'] = "60"; }
		if ($_POST['frag_memcap'] != "") { $natent['frag_memcap'] = $_POST['frag_memcap']; }else{ $natent['frag_memcap'] = "33554432"; }
		if ($_POST['defrag_memcap_policy']) { $natent['defrag_memcap_policy'] = $_POST['defrag_memcap_policy']; }
		if ($_POST['ip_max_trackers'] != "") { $natent['ip_max_trackers'] = $_POST['ip_max_trackers']; }else{ $natent['ip_max_trackers'] = "65535"; }
		if ($_POST['frag_hash_size'] != "") { $natent['frag_hash_size'] = $_POST['frag_hash_size']; }else{ $natent['frag_hash_size'] = "65536"; }
		if ($_POST['flow_memcap'] != "") { $natent['flow_memcap'] = $_POST['flow_memcap']; }else{ $natent['flow_memcap'] = "134217728"; }
		if ($_POST['flow_memcap_policy']) { $natent['flow_memcap_policy'] = $_POST['flow_memcap_policy']; }
		if ($_POST['flow_prealloc'] != "") { $natent['flow_prealloc'] = $_POST['flow_prealloc']; }else{ $natent['flow_prealloc'] = "10000"; }
		if ($_POST['flow_hash_size'] != "") { $natent['flow_hash_size'] = $_POST['flow_hash_size']; }else{ $natent['flow_hash_size'] = "65536"; }
		if ($_POST['flow_emerg_recovery'] != "") { $natent['flow_emerg_recovery'] = $_POST['flow_emerg_recovery']; }else{ $natent['flow_emerg_recovery'] = "30"; }
		if ($_POST['flow_prune'] != "") { $natent['flow_prune'] = $_POST['flow_prune']; }else{ $natent['flow_prune'] = "5"; }

		if ($_POST['flow_tcp_new_timeout'] != "") { $natent['flow_tcp_new_timeout'] = $_POST['flow_tcp_new_timeout']; }else{ $natent['flow_tcp_new_timeout'] = "60"; }
		if ($_POST['flow_tcp_established_timeout'] != "") { $natent['flow_tcp_established_timeout'] = $_POST['flow_tcp_established_timeout']; }else{ $natent['flow_tcp_established_timeout'] = "3600"; }
		if ($_POST['flow_tcp_closed_timeout'] != "") { $natent['flow_tcp_closed_timeout'] = $_POST['flow_tcp_closed_timeout']; }else{ $natent['flow_tcp_closed_timeout'] = "120"; }
		if ($_POST['flow_tcp_emerg_new_timeout'] != "") { $natent['flow_tcp_emerg_new_timeout'] = $_POST['flow_tcp_emerg_new_timeout']; }else{ $natent['flow_tcp_emerg_new_timeout'] = "10"; }
		if ($_POST['flow_tcp_emerg_established_timeout'] != "") { $natent['flow_tcp_emerg_established_timeout'] = $_POST['flow_tcp_emerg_established_timeout']; }else{ $natent['flow_tcp_emerg_established_timeout'] = "300"; }
		if ($_POST['flow_tcp_emerg_closed_timeout'] != "") { $natent['flow_tcp_emerg_closed_timeout'] = $_POST['flow_tcp_emerg_closed_timeout']; }else{ $natent['flow_tcp_emerg_closed_timeout'] = "20"; }

		if ($_POST['flow_udp_new_timeout'] != "") { $natent['flow_udp_new_timeout'] = $_POST['flow_udp_new_timeout']; }else{ $natent['flow_udp_new_timeout'] = "30"; }
		if ($_POST['flow_udp_established_timeout'] != "") { $natent['flow_udp_established_timeout'] = $_POST['flow_udp_established_timeout']; }else{ $natent['flow_udp_established_timeout'] = "300"; }
		if ($_POST['flow_udp_emerg_new_timeout'] != "") { $natent['flow_udp_emerg_new_timeout'] = $_POST['flow_udp_emerg_new_timeout']; }else{ $natent['flow_udp_emerg_new_timeout'] = "10"; }
		if ($_POST['flow_udp_emerg_established_timeout'] != "") { $natent['flow_udp_emerg_established_timeout'] = $_POST['flow_udp_emerg_established_timeout']; }else{ $natent['flow_udp_emerg_established_timeout'] = "100"; }

		if ($_POST['flow_icmp_new_timeout'] != "") { $natent['flow_icmp_new_timeout'] = $_POST['flow_icmp_new_timeout']; }else{ $natent['flow_icmp_new_timeout'] = "30"; }
		if ($_POST['flow_icmp_established_timeout'] != "") { $natent['flow_icmp_established_timeout'] = $_POST['flow_icmp_established_timeout']; }else{ $natent['flow_icmp_established_timeout'] = "300"; }
		if ($_POST['flow_icmp_emerg_new_timeout'] != "") { $natent['flow_icmp_emerg_new_timeout'] = $_POST['flow_icmp_emerg_new_timeout']; }else{ $natent['flow_icmp_emerg_new_timeout'] = "10"; }
		if ($_POST['flow_icmp_emerg_established_timeout'] != "") { $natent['flow_icmp_emerg_established_timeout'] = $_POST['flow_icmp_emerg_established_timeout']; }else{ $natent['flow_icmp_emerg_established_timeout'] = "100"; }

		if ($_POST['stream_memcap'] != "") { $natent['stream_memcap'] = $_POST['stream_memcap']; }else{ $natent['stream_memcap'] = "268435456"; }
		if ($_POST['stream_memcap_policy']) { $natent['stream_memcap_policy'] = $_POST['stream_memcap_policy']; }
		if ($_POST['stream_prealloc_sessions'] != "") { $natent['stream_prealloc_sessions'] = $_POST['stream_prealloc_sessions']; }else{ $natent['stream_prealloc_sessions'] = "32768"; }
		if ($_POST['enable_midstream_sessions'] == "on") { $natent['enable_midstream_sessions'] = 'on'; }else{ $natent['enable_midstream_sessions'] = 'off'; }
		if ($_POST['stream_checksum_validation'] == "on") { $natent['stream_checksum_validation'] = 'on'; }else{ $natent['stream_checksum_validation'] = 'off'; }
		if ($_POST['midstream_policy']) { $natent['midstream_policy'] = $_POST['midstream_policy']; }
		if ($_POST['enable_async_sessions'] == "on") { $natent['enable_async_sessions'] = 'on'; }else{ $natent['enable_async_sessions'] = 'off'; }
		if ($_POST['stream_bypass'] == "on") { $natent['stream_bypass'] = 'on'; }else{ $natent['stream_bypass'] = 'no'; }
		if ($_POST['stream_drop_invalid'] == "on") { $natent['stream_drop_invalid'] = 'on'; }else{ $natent['stream_drop_invalid'] = 'no'; }
		if ($_POST['reassembly_memcap'] != "") { $natent['reassembly_memcap'] = $_POST['reassembly_memcap']; }else{ $natent['reassembly_memcap'] = "134217728"; }
		if ($_POST['reassembly_memcap_policy']) { $natent['reassembly_memcap_policy'] = $_POST['reassembly_memcap_policy']; }
		if ($_POST['reassembly_depth'] != "") { $natent['reassembly_depth'] = $_POST['reassembly_depth']; }else{ $natent['reassembly_depth'] = "1048576"; }
		if ($_POST['reassembly_to_server_chunk'] != "") { $natent['reassembly_to_server_chunk'] = $_POST['reassembly_to_server_chunk']; }else{ $natent['reassembly_to_server_chunk'] = "2560"; }
		if ($_POST['reassembly_to_client_chunk'] != "") { $natent['reassembly_to_client_chunk'] = $_POST['reassembly_to_client_chunk']; }else{ $natent['reassembly_to_client_chunk'] = "2560"; }
		if ($_POST['max_synack_queued'] != "") { $natent['max_synack_queued'] = $_POST['max_synack_queued']; }else{ $natent['max_synack_queued'] = "5"; }

		/**************************************************/
		/* If we have a valid rule ID, save configuration */
		/* then update the suricata.conf file for this	*/
		/* interface.									 */
		/**************************************************/
		if (isset($id) && !empty($a_nat)) {
			$a_nat = $natent;
			config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
			write_config("Suricata pkg: saved flow or stream configuration changes.");
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
		header("Location: suricata_flow_stream.php?id=$id");
		exit;
	}
}
elseif ($_POST['save_import_alias']) {
	// If saving out of "select alias" mode,
	// then return to Host OS Policy Engine edit
	// page.
	if ($_POST['mode'] =='add_edit_os_policy') {
		$pengcfg = array();
		$eng_id = $_POST['eng_id'];
		$pengcfg['name'] = $_POST['eng_name'];
		$pengcfg['bind_to'] = $_POST['eng_bind'];
		$pengcfg['policy'] = $_POST['eng_policy'];
		$add_edit_os_policy = true;
		$mode = "add_edit_os_policy";

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
			$eng_policy = $_POST['eng_policy'];
		}
	}
	else {
		// Assume we are importing one or more aliases
		// for use in new Host OS Policy engines.
		$engine = array( "name" => "", "bind_to" => "", "policy" => "bsd" );

		// See if anything was checked to import
		if (is_array($_POST['aliastoimport']) && count($_POST['aliastoimport']) > 0) {
			foreach ($_POST['aliastoimport'] as $item) {
				$engine['name'] = strtolower($item);
				$engine['bind_to'] = $item;
				$a_nat['host_os_policy']['item'][] = $engine;
			}
		}
		else {
			$input_errors[] = gettext("No entries were selected for import.  Please select one or more Aliases for import and click SAVE.");
			$importalias = true;
		}

		// if no errors, write new entry to conf
		if (!$input_errors) {
			// Reorder the engine array to ensure the
			// 'bind_to=all' entry is at the bottom if
			// the array contains more than one entry.
			if (count($a_nat['host_os_policy']['item']) > 1) {
				$i = -1;
				foreach ($a_nat['host_os_policy']['item'] as $f => $v) {
					if ($v['bind_to'] == "all") {
						$i = $f;
						break;
					}
				}
				// Only relocate the entry if we
				// found it, and it's not already
				// at the end.
				if ($i > -1 && ($i < (count($a_nat['host_os_policy']['item']) - 1))) {
					$tmp = $a_nat['host_os_policy']['item'][$i];
					unset($a_nat['host_os_policy']['item'][$i]);
					$a_nat['host_os_policy']['item'][] = $tmp;
				}
				$pconfig['host_os_policy']['item'] = $a_nat['host_os_policy']['item'];
			}

			// Write the new engine array to config file
			config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
			write_config("Suricata pkg: saved Host_OS_Policy engine created from a defined firewall alias.");
			$importalias = false;
			$selectalias = false;
			header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
			header( 'Cache-Control: no-store, no-cache, must-revalidate' );
			header( 'Cache-Control: post-check=0, pre-check=0', false );
			header( 'Pragma: no-cache' );
			header("Location: suricata_flow_stream.php?id=$id");
			exit;
		}
	}
}
elseif ($_POST['cancel_import_alias']) {
	$importalias = false;
	$selectalias = false;
	$eng_id = $_POST['eng_id'];

	// If cancelling out of "select alias" mode,
	// then return to Host OS Policy Engine edit
	// page.
	if ($_POST['mode'] == 'add_edit_os_policy') {
		$pengcfg = array();
		$pengcfg['name'] = $_POST['eng_name'];
		$pengcfg['bind_to'] = $_POST['eng_bind'];
		$pengcfg['policy'] = $_POST['eng_policy'];
		$add_edit_os_policy = true;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($pconfig['interface']);
$pglinks = array("", "/suricata/suricata_interfaces.php", "/suricata/suricata_interfaces_edit.php?id={$id}", "@self");
$pgtitle = array("Services", "Suricata", "Interface Settings", "{$if_friendly} - Flow and Stream Engine");

include_once("head.inc");

/* Display error message */
if ($input_errors) {
	print_input_errors($input_errors); // TODO: add checks
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
$tab_array[] = array($menu_iface . gettext("Flow/Stream"), true, "/suricata/suricata_flow_stream.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("App Parsers"), false, "/suricata/suricata_app_parsers.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Variables"), false, "/suricata/suricata_define_vars.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/suricata/suricata_ip_reputation.php?id={$id}");
display_top_tabs($tab_array, true);
?>

<?php
	if ($importalias) {

		print('<form action="suricata_flow_stream.php" method="post" name="iform" id="iform" class="form-horizontal">');
		print('<input type="hidden" name="eng_id" id="eng_id" value="' . $eng_id . '"/>');
		print('<input type="hidden" name="id" id="id" value="' . $id . '"/>');

		if ($selectalias) {
			print('<input type="hidden" name="eng_name" value="' . $eng_name . '"/>');
			print('<input type="hidden" name="eng_bind" value="' . $eng_bind . '"/>');
			print('<input type="hidden" name="eng_policy" value="' . $eng_policy . '"/>');
		}

		include("/usr/local/www/suricata/suricata_import_aliases.php");
		print('</form>');

	} elseif ($add_edit_os_policy) {

		$form = new Form(false);
		include("/usr/local/www/suricata/suricata_os_policy_engine.php");

	} else {
?>

<form action="suricata_flow_stream.php" method="post" name="iform" id="iform" class="form-horizontal">
<input type="hidden" name="eng_id" id="eng_id" value="<?=$eng_id?>"/>
<input type="hidden" name="id" id="id" value="<?=$id?>"/>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Host-Specific Defrag and Stream Settings")?></h2></div>
		<div class="panel-body">
			<div class="form-group">
				<label class="col-sm-2 control-label">
					<?=gettext("Host OS Policy Assignment"); ?>
				</label>
				<div class="col-sm-10">
					<div class="table-responsive">
						<table class="table table-striped table-hover table-condensed">
							<thead>
								<tr>
									<th><?=gettext("Name")?></th>
									<th><?=gettext("Bind-To Address Alias")?></th>
									<th>
										<button type="submit" name="import_alias[]" class="btn btn-sm btn-primary" title="<?=gettext("Import policy configuration from existing Aliases")?>" value="Import">
											<i class="fa-solid fa-upload icon-embed-btn"></i>
											<?=gettext("Import"); ?>
										</button>
										<button type="submit" name="add_os_policy[]" class="btn btn-sm btn-success" title="<?=gettext("Add a new policy configuration")?>" value="Add">
											<i class="fa-solid fa-plus icon-embed-btn"></i>
											<?=gettext("Add"); ?>
										</button>
									</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($pconfig['host_os_policy']['item'] as $f => $v): ?>
									<tr>
										<td><?=gettext($v['name'])?></td>
										<td><?=gettext($v['bind_to'])?></td>
										<td>
											<button type="submit" name="edit_os_policy[]" class="btn btn-sm btn-primary" value="Edit" onclick="document.getElementById('eng_id').value='<?=$f?>'" title="<?=gettext("Edit this policy configuration")?>">
												<i class="fa-solid fa-pencil icon-embed-btn"></i>
												<?=gettext("Edit"); ?>
											</button>
								<?php if ($v['bind_to'] != "all") : ?>
											<button type="submit" name="del_os_policy[]" class="btn btn-sm btn-danger" value="Delete" onclick="document.getElementById('eng_id').value='<?=$f?>';" title="<?=gettext("Delete this policy configuration")?>">
												<i class="fa-solid fa-trash-can icon-embed-btn"></i>
												<?=gettext("Delete"); ?>
											</button>
								<?php else : ?>
											<button type="submit" name="del_os_policy[]" class="btn btn-sm btn-danger" value="Delete" title="<?=gettext("Default policy configuration cannot be deleted")?>" disabled>
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

</form>

<?php

$form = new Form();

$form->addGlobal(new Form_Input(
	'id',
	'id',
	'hidden',
	$id
));

$form->addGlobal(new Form_Input(
	'eng_id',
	'eng_id',
	'hidden',
	$eng_id
));

$section = new Form_Section('IP Defragmentation');
$section->addInput(new Form_Input(
	'frag_memcap',
	'Defrag Memory Cap',
	'text',
	$pconfig['frag_memcap']
))->setHelp('Max memory to be used for defragmentation. Default is 33,554,432 bytes (32 MB). Sets the maximum amount of memory, in bytes, to be used by the IP defragmentation engine.');
$section->addInput(new Form_Select(
	'defrag_memcap_policy',
	'Defrag Memory Cap Exception Policy',
	$pconfig['defrag_memcap_policy'],
	array( "bypass" => "Bypass", "drop-packet" => "Drop Packet", "pass-packet" => "Pass Packet",
		   "reject" => "Reject", "ignore" => "Ignore" )
))->setHelp('Apply selected policy when the memcap limit for defrag is reached and no tracker could be picked up. This policy can only be applied to packets. Default is "Ignore". ' .
			'"Drop Packet" drops the current packet. "Reject" rejects the current packet. "Bypass" will bypass the flow, and no further inspection is done. ' .
			'"Pass Packet" will disable detection, but still does stream updates and app-layer parsing (depending on which policy triggered it). ' .
			'"Ignore" does not apply exception policies.');
$section->addInput(new Form_Input(
	'ip_max_trackers',
	'Max Trackers',
	'text',
	$pconfig['ip_max_trackers']
))->setHelp('Number of defragmented flows to follow. Default is 65,535 fragments. Sets the number of defragmented flows to follow for reassembly.');
$section->addInput(new Form_Input(
	'ip_max_frags',
	'Max Fragments',
	'text',
	$pconfig['ip_max_frags']
))->setHelp('Maximum number of IP fragments to hold. Default is 65,535 fragments. Sets the maximum number of IP fragments to retain in memory while awaiting reassembly. This must be equal to or greater than the Max Trackers value specified above.');
$section->addInput(new Form_Input(
	'frag_hash_size',
	'Fragmentation Hash Table Size',
	'text',
	$pconfig['frag_hash_size']
))->setHelp('Hash Table size. Default is 65,536 entries. Sets the size of the Hash Table used by the defragmentation engine.');
$section->addInput(new Form_Input(
	'ip_frag_timeout',
	'Timeout',
	'text',
	$pconfig['ip_frag_timeout']
))->setHelp('Max seconds to hold an IP fragement. Default is 60 seconds. Sets the number of seconds to hold an IP fragment in memory while awaiting the remainder of the packet to arrive.');
$form->add($section);

$section = new Form_Section('Flow Manager Settings');
$section->addInput(new Form_Input(
	'flow_memcap',
	'Flow Memory Cap',
	'text',
	$pconfig['flow_memcap']
))->setHelp('Max memory, in bytes, to be used by the flow engine. Default is 134,217,728 bytes (128 MB)');
$section->addInput(new Form_Select(
	'flow_memcap_policy',
	'Flow Memory Cap Exception Policy',
	$pconfig['flow_memcap_policy'],
	array( "bypass" => "Bypass", "drop-packet" => "Drop Packet", "pass-packet" => "Pass Packet",
		   "reject" => "Reject", "ignore" => "Ignore" )
))->setHelp('Apply selected policy when the memcap limit for flows is reached and no flow could be freed up. This policy can only be applied to packets. Default is "Ignore". ' .
			'"Drop Packet" drops the current packet. "Reject" rejects the current packet. "Bypass" will bypass the flow, and no further inspection is done. ' .
			'"Pass Packet" will disable detection, but still does stream updates and app-layer parsing (depending on which policy triggered it). ' .
			'"Ignore" does not apply exception policies.');
$section->addInput(new Form_Input(
	'flow_hash_size',
	'Flow Hash Table Size',
	'text',
	$pconfig['flow_hash_size']
))->setHelp('Hash Table size used by the flow engine. Default is 65,536 entries.');
$section->addInput(new Form_Input(
	'flow_prealloc',
	'Preallocated Flows',
	'text',
	$pconfig['flow_prealloc']
))->setHelp('Number of preallocated flows ready for use. Default is 10,000 flows.');
$section->addInput(new Form_Input(
	'flow_emerg_recovery',
	'Emergency Recovery',
	'text',
	$pconfig['flow_emerg_recovery']
))->setHelp('Percentage of preallocated flows to complete before exiting Emergency Mode. Default is 30%.');
$section->addInput(new Form_Input(
	'flow_prune',
	'Prune Flows',
	'text',
	$pconfig['flow_prune']
))->setHelp('Number of flows to prune in Emergency Mode when allocating a new flow. Default is 5 flows.');
$form->add($section);

$section = new Form_Section('Flow Timeout Settings');
$group = new Form_Group('TCP Connections');
$group->add(new Form_Input(
	'flow_tcp_new_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_tcp_new_timeout']
))->setHelp('New TCP connection timeout in seconds. Default is 60.');
$group->add(new Form_Input(
	'flow_tcp_established_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_tcp_established_timeout']
))->setHelp('Established TCP connection timeout in seconds. Default is 3600.');
$group->add(new Form_Input(
	'flow_tcp_closed_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_tcp_closed_timeout']
))->setHelp('Closed TCP connection timeout in seconds. Default is 120.');
$group->add(new Form_Input(
	'flow_tcp_emerg_new_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_tcp_emerg_new_timeout']
))->setHelp('Emergency New TCP connection timeout in seconds. Default is 10.');
$group->add(new Form_Input(
	'flow_tcp_emerg_established_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_tcp_emerg_established_timeout']
))->setHelp('Emergency Established TCP connection timeout in seconds. Default is 300.');
$group->add(new Form_Input(
	'flow_tcp_emerg_closed_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_tcp_emerg_closed_timeout']
))->setHelp('Emergency Closed TCP connection timeout in seconds. Default is 20.');
$section->add($group);

$group = new Form_Group('UDP Connections');
$group->add(new Form_Input(
	'flow_udp_new_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_udp_new_timeout']
))->setHelp('New UDP connection timeout in seconds. Default is 30.');
$group->add(new Form_Input(
	'flow_udp_established_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_udp_established_timeout']
))->setHelp('Established UDP connection timeout in seconds. Default is 300.');
$group->add(new Form_Input(
	'flow_udp_emerg_new_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_udp_emerg_new_timeout']
))->setHelp('Emergency New UDP connection timeout in seconds. Default is 10.');
$group->add(new Form_Input(
	'flow_udp_emerg_established_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_udp_emerg_established_timeout']
))->setHelp('Emergency Established UDP connection timeout in seconds. Default is 100.');
$section->add($group);

$group = new Form_Group('ICMP Connections');
$group->add(new Form_Input(
	'flow_icmp_new_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_icmp_new_timeout']
))->setHelp('New ICMP connection timeout in seconds. Default is 30.');
$group->add(new Form_Input(
	'flow_icmp_established_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_icmp_established_timeout']
))->setHelp('Established ICMP connection timeout in seconds. Default is 300.');
$group->add(new Form_Input(
	'flow_icmp_emerg_new_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_icmp_emerg_new_timeout']
))->setHelp('Emergency New ICMP connection timeout in seconds. Default is 10.');
$group->add(new Form_Input(
	'flow_icmp_emerg_established_timeout',
	'Prune Flows',
	'text',
	$pconfig['flow_icmp_emerg_established_timeout']
))->setHelp('Emergency Established ICMP connection timeout in seconds. Default is 100.');
$section->add($group);
$form->add($section);

$section = new Form_Section('Stream Engine Settings');
$section->addInput(new Form_Input(
	'stream_memcap',
	'Stream Memory Cap',
	'text',
	$pconfig['stream_memcap']
))->setHelp('Max memory to be used by stream engine. Default is 268,435,456 bytes (256MB). Sets the maximum amount of memory, in bytes, to be used by the stream engine. This number will likely need to be increased beyond the default value in systems with more than 4 processor cores. If Suricata fails to start and logs a memory allocation error, increase this value in 4 MB chunks until Suricata starts successfully.');
$section->addInput(new Form_Select(
	'stream_memcap_policy',
	'Memcap Exception Policy',
	$pconfig['stream_memcap_policy'],
	array( "drop-flow" => "Drop Flow", "pass-flow" => "Pass Flow", "bypass" => "Bypass", "drop-packet" => "Drop Packet",
		   "pass-packet" => "Pass Packet", "reject" => "Reject", "ignore" => "Ignore" )
))->setHelp('If a stream memcap limit is reached, apply the selected memcap policy to the packet and/or flow.. Default is "Ignore". ' .
			'"Drop Flow" will disable inspection for the whole flow (packets, payload, and application layer protocol), drop ' .
			'the packet and all future packets in the flow. "Drop Packet" drops the current packet. "Reject" is the same as "Drop Flow" ' .
			'but rejects the current packet as well. "Bypass" will bypass the flow, and no further inspection is done. ' .
			'"Pass Flow" will disable payload and packet detection, but stream reassembly, app-layer parsing and logging still happen. ' .
			'"Pass Packet" will disable detection, but still does stream updates and app-layer parsing (depending on which policy triggered it). ' .
			'"Ignore" does not apply exception policies.');
$section->addInput(new Form_Input(
	'stream_prealloc_sessions',
	'Preallocated Sessions',
	'text',
	$pconfig['stream_prealloc_sessions']
))->setHelp('Number of preallocated stream engine sessions. Default is 32,768 sessions. Sets the number of stream engine sessions to preallocate. This can be a performance enhancement.');
$section->addInput(new Form_Checkbox(
	'enable_midstream_sessions',
	'Enable Mid-Stream Sessions',
	'Suricata will allow midstream session pickups. Default is Not Checked, which will ignore and not scan midstream sessions. When this ' .
	'option is enabled, midstream sessions are subject to the Midstream Exception Policy selected below.',
	$pconfig['enable_midstream_sessions'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Select(
	'midstream_policy',
	'Midstream Exception Policy',
	$pconfig['midstream_policy'],
	array( "drop-flow" => "Drop Flow", "pass-flow" => "Pass Flow", "bypass" => "Bypass", "drop-packet" => "Drop Packet",
		   "pass-packet" => "Pass Packet", "reject" => "Reject", "ignore" => "Ignore" )
))->setHelp('If a session is picked up midstream, apply the selected midstream policy to the flow. Default is "Ignore". ' .
			'"Drop Flow" will disable inspection for the whole flow (packets, payload, and application layer protocol), drop ' .
			'the packet and all future packets in the flow. "Drop Packet" drops the current packet. "Reject" is the same as "Drop Flow" ' .
			'but rejects the current packet as well. "Bypass" will bypass the flow, and no further inspection is done. ' .
			'"Pass Flow" will disable payload and packet detection, but stream reassembly, app-layer parsing and logging still happen. ' .
			'"Pass Packet" will disable detection, but still does stream updates and app-layer parsing (depending on which policy triggered it). ' .
			'"Ignore" does not apply exception policies.');
$section->addInput(new Form_Checkbox(
	'enable_async_sessions',
	'Enable Async Streams',
	'Suricata will track asynchronous one-sided streams. Default is Not Checked.',
	$pconfig['enable_async_sessions'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'stream_checksum_validation',
	'Checksum Validation',
	'Suricata will validate the checksum of received packets. When enabled, packets with invalid checksum values will not be ' . 
	'processed by the engine stream/app layer. Default is Checked.',
	$pconfig['stream_checksum_validation'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'stream_bypass',
	'Bypass Packets',
	'Suricata will bypass packets when stream reassembly depth (configured below) is reached. Default is Not Checked.',
	$pconfig['stream_bypass'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'stream_drop_invalid',
	'Drop Invalid Packets',
	'When using Inline mode, Suricata will drop packets that are invalid with regards to streaming engine. Default is Not Checked.',
	$pconfig['stream_drop_invalid'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Input(
	'reassembly_memcap',
	'Reassembly Memory Cap',
	'text',
	$pconfig['reassembly_memcap']
))->setHelp('Max memory to be used for stream reassembly. Default is 134,217,728 bytes (128MB). Sets the maximum amount of memory, in bytes, to be used for stream reassembly.');
$section->addInput(new Form_Select(
	'reassembly_memcap_policy',
	'Reassembly Memcap Exception Policy',
	$pconfig['reassembly_memcap_policy'],
	array( "drop-flow" => "Drop Flow", "pass-flow" => "Pass Flow", "bypass" => "Bypass", "drop-packet" => "Drop Packet",
		   "pass-packet" => "Pass Packet", "reject" => "Reject", "ignore" => "Ignore" )
))->setHelp('If stream reassembly reaches memcap limit, apply the selected reassembly memcap policy to the flow. Default is "Ignore". ' .
			'"Drop Flow" will disable inspection for the whole flow (packets, payload, and application layer protocol), drop ' .
			'the packet and all future packets in the flow. "Drop Packet" drops the current packet. "Reject" is the same as "Drop Flow" ' .
			'but rejects the current packet as well. "Bypass" will bypass the flow, and no further inspection is done. ' .
			'"Pass Flow" will disable payload and packet detection, but stream reassembly, app-layer parsing and logging still happen. ' .
			'"Pass Packet" will disable detection, but still does stream updates and app-layer parsing (depending on which policy triggered it). ' .
			'"Ignore" does not apply exception policies.');
$section->addInput(new Form_Input(
	'reassembly_depth',
	'Reassembly Depth',
	'text',
	$pconfig['reassembly_depth']
))->setHelp('Amount of a stream to reassemble. Default is 1,048,576 bytes (1MB). Sets the depth, in bytes, of a stream to be reassembled by the stream engine. Set to 0 (unlimited) to reassemble entire stream. This is required for file extraction.');
$section->addInput(new Form_Input(
	'reassembly_to_server_chunk',
	'To-Server Chunk Size',
	'text',
	$pconfig['reassembly_to_server_chunk']
))->setHelp('Size of raw stream chunks to inspect. Default is 2,560 bytes. Sets the chunk size, in bytes, for raw stream inspection performed for \'to-server\' traffic.');
$section->addInput(new Form_Input(
	'reassembly_to_client_chunk',
	'To-Client Chunk Size',
	'text',
	$pconfig['reassembly_to_client_chunk']
))->setHelp('Amount of a stream to reassemble. Default is 2,560 bytes. Sets the chunk size, in bytes, for raw stream inspection performed for \'to-client\' traffic.');
$section->addInput(new Form_Input(
	'max_synack_queued',
	'Max different SYN/ACKs to queue',
	'number',
	$pconfig['max_synack_queued']
))->setHelp('Sets max number of extra SYN/ACKs Suricata will queue and delay judgement on while awaiting proper ACK for 3-way handshake. Default is 5.');
$form->add($section);

print($form);
?>

<div class="infoblock">
	<?=print_info_box('<strong>Note:</strong> Please save your settings before you exit. Changes will rebuild the rules file. This may take several seconds. Suricata must also be restarted to activate any changes made on this screen.', 'info')?>
</div>

<?php } ?>

<?php include("foot.inc"); ?>
