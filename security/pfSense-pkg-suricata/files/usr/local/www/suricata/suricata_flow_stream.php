<?php
/*
 * suricata_flow_stream.php
 * part of pfSense
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
 * Copyright (C) 2015 Bill Meeks
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

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);
if (is_null($id))
	$id=0;

if (!is_array($config['installedpackages']['suricata']))
	$config['installedpackages']['suricata'] = array();
if (!is_array($config['installedpackages']['suricata']['rule']))
	$config['installedpackages']['suricata']['rule'] = array();

// Initialize required array variables as necessary
if (!is_array($config['aliases']['alias']))
	$config['aliases']['alias'] = array();
$a_aliases = $config['aliases']['alias'];

// Initialize Host-OS Policy engine arrays if necessary
if (!is_array($config['installedpackages']['suricata']['rule'][$id]['host_os_policy']['item']))
	$config['installedpackages']['suricata']['rule'][$id]['host_os_policy']['item'] = array();

$a_nat = &$config['installedpackages']['suricata']['rule'];

$host_os_policy_engine_next_id = count($a_nat[$id]['host_os_policy']['item']);

// Build a lookup array of currently used engine 'bind_to' Aliases 
// so we can screen matching Alias names from the list.
$used = array();
foreach ($a_nat[$id]['host_os_policy']['item'] as $v)
	$used[$v['bind_to']] = true;

$pconfig = array();
if (isset($id) && $a_nat[$id]) {
	/* Get current values from config for page form fields */
	$pconfig = $a_nat[$id];

	// See if Host-OS policy engine array is configured and use
	// it; otherwise create a default engine configuration.
	if (empty($pconfig['host_os_policy']['item'])) {
		$default = array( "name" => "default", "bind_to" => "all", "policy" => "bsd" );
		$pconfig['host_os_policy']['item'] = array();
		$pconfig['host_os_policy']['item'][] = $default;
		if (!is_array($a_nat[$id]['host_os_policy']['item']))
			$a_nat[$id]['host_os_policy']['item'] = array();
		$a_nat[$id]['host_os_policy']['item'][] = $default;
		write_config();
		$host_os_policy_engine_next_id++;
	}
	else
		$pconfig['host_os_policy'] = $a_nat[$id]['host_os_policy'];
}

// Check for "import or select alias mode" and set flags if TRUE.
// "selectalias", when true, displays radio buttons to limit
// multiple selections.
if ($_POST['import_alias']) {
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
		if ($engine['bind_to'] == "all" && $engine['name'] <> "default") {
			$input_errors[] = gettext("Only one default OS-Policy Engine can be bound to all addresses.");
			$add_edit_os_policy = true;
			$pengcfg = $engine;
		}

		// if no errors, write new entry to conf
		if (!$input_errors) {
			if (isset($eng_id) && $a_nat[$id]['host_os_policy']['item'][$eng_id]) {
				$a_nat[$id]['host_os_policy']['item'][$eng_id] = $engine;
			}
			else
				$a_nat[$id]['host_os_policy']['item'][] = $engine;

			/* Reorder the engine array to ensure the */
			/* 'bind_to=all' entry is at the bottom   */
			/* if it contains more than one entry.    */
			if (count($a_nat[$id]['host_os_policy']['item']) > 1) {
				$i = -1;
				foreach ($a_nat[$id]['host_os_policy']['item'] as $f => $v) {
					if ($v['bind_to'] == "all") {
						$i = $f;
						break;
					}
				}
				/* Only relocate the entry if we  */
				/* found it, and it's not already */
				/* at the end.                    */
				if ($i > -1 && ($i < (count($a_nat[$id]['host_os_policy']['item']) - 1))) {
					$tmp = $a_nat[$id]['host_os_policy']['item'][$i];
					unset($a_nat[$id]['host_os_policy']['item'][$i]);
					$a_nat[$id]['host_os_policy']['item'][] = $tmp;
				}
			}

			// Now write the new engine array to conf
			write_config();
			$pconfig['host_os_policy']['item'] = $a_nat[$id]['host_os_policy']['item'];
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
		$pengcfg = $a_nat[$id]['host_os_policy']['item'][$eng_id];
	}
}
elseif ($_POST['del_os_policy']) {
	$natent = array();
	$natent = $pconfig;

	if ($_POST['eng_id'] != "") {
		unset($natent['host_os_policy']['item'][$_POST['eng_id']]);
		$pconfig = $natent;
	}
	if (isset($id) && $a_nat[$id]) {
		$a_nat[$id] = $natent;
		write_config();
	}
}
elseif ($_POST['cancel_os_policy']) {
	$add_edit_os_policy = false;
}
elseif ($_POST['ResetAll']) {

	/* Reset all the settings to defaults */
	$pconfig['ip_max_frags'] = "65535";
	$pconfig['ip_frag_timeout'] = "60";
	$pconfig['frag_memcap'] = '33554432';
	$pconfig['ip_max_trackers'] = '65535';
	$pconfig['frag_hash_size'] = '65536';

	$pconfig['flow_memcap'] = '33554432';
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
	// 64 MB is a decent all-around default, but some setups need more. 
	$pconfig['stream_prealloc_sessions'] = '32768';
	$pconfig['stream_memcap'] = '67108864';
	$pconfig['reassembly_memcap'] = '67108864';
	$pconfig['reassembly_depth'] = '1048576';
	$pconfig['reassembly_to_server_chunk'] = '2560';
	$pconfig['reassembly_to_client_chunk'] = '2560';
	$pconfig['enable_midstream_sessions'] = 'off';
	$pconfig['enable_async_sessions'] = 'off';

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
		if ($_POST['ip_max_trackers'] != "") { $natent['ip_max_trackers'] = $_POST['ip_max_trackers']; }else{ $natent['ip_max_trackers'] = "65535"; }
		if ($_POST['frag_hash_size'] != "") { $natent['frag_hash_size'] = $_POST['frag_hash_size']; }else{ $natent['frag_hash_size'] = "65536"; }
		if ($_POST['flow_memcap'] != "") { $natent['flow_memcap'] = $_POST['flow_memcap']; }else{ $natent['flow_memcap'] = "33554432"; }
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

		if ($_POST['stream_memcap'] != "") { $natent['stream_memcap'] = $_POST['stream_memcap']; }else{ $natent['stream_memcap'] = "67108864"; }
		if ($_POST['stream_prealloc_sessions'] != "") { $natent['stream_prealloc_sessions'] = $_POST['stream_prealloc_sessions']; }else{ $natent['stream_prealloc_sessions'] = "32768"; }
		if ($_POST['enable_midstream_sessions'] == "on") { $natent['enable_midstream_sessions'] = 'on'; }else{ $natent['enable_midstream_sessions'] = 'off'; }
		if ($_POST['enable_async_sessions'] == "on") { $natent['enable_async_sessions'] = 'on'; }else{ $natent['enable_async_sessions'] = 'off'; }
		if ($_POST['reassembly_memcap'] != "") { $natent['reassembly_memcap'] = $_POST['reassembly_memcap']; }else{ $natent['reassembly_memcap'] = "67108864"; }
		if ($_POST['reassembly_depth'] != "") { $natent['reassembly_depth'] = $_POST['reassembly_depth']; }else{ $natent['reassembly_depth'] = "1048576"; }
		if ($_POST['reassembly_to_server_chunk'] != "") { $natent['reassembly_to_server_chunk'] = $_POST['reassembly_to_server_chunk']; }else{ $natent['reassembly_to_server_chunk'] = "2560"; }
		if ($_POST['reassembly_to_client_chunk'] != "") { $natent['reassembly_to_client_chunk'] = $_POST['reassembly_to_client_chunk']; }else{ $natent['reassembly_to_client_chunk'] = "2560"; }

		/**************************************************/
		/* If we have a valid rule ID, save configuration */
		/* then update the suricata.conf file for this    */
		/* interface.                                     */
		/**************************************************/
		if (isset($id) && $a_nat[$id]) {
			$a_nat[$id] = $natent;
			write_config();
			$rebuild_rules = false;
			conf_mount_rw();
			suricata_generate_yaml($natent);
			conf_mount_ro();

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
				$a_nat[$id]['host_os_policy']['item'][] = $engine;
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
			if (count($a_nat[$id]['host_os_policy']['item']) > 1) {
				$i = -1;
				foreach ($a_nat[$id]['host_os_policy']['item'] as $f => $v) {
					if ($v['bind_to'] == "all") {
						$i = $f;
						break;
					}
				}
				// Only relocate the entry if we 
				// found it, and it's not already 
				// at the end.
				if ($i > -1 && ($i < (count($a_nat[$id]['host_os_policy']['item']) - 1))) {
					$tmp = $a_nat[$id]['host_os_policy']['item'][$i];
					unset($a_nat[$id]['host_os_policy']['item'][$i]);
					$a_nat[$id]['host_os_policy']['item'][] = $tmp;
				}
				$pconfig['host_os_policy']['item'] = $a_nat[$id]['host_os_policy']['item'];
			}

			// Write the new engine array to config file
			write_config();
			$importalias = false;
			$selectalias = false;
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
$pgtitle = gettext("Suricata: Interface {$if_friendly} - Flow and Stream");
include_once("head.inc");
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc");
/* Display error message */
if ($input_errors) {
	print_input_errors($input_errors); // TODO: add checks
}
?>

<form action="suricata_flow_stream.php" method="post" name="iform" id="iform">
<input type="hidden" name="eng_id" id="eng_id" value="<?=$eng_id;?>"/>
<input type="hidden" name="id" id="id" value="<?=$id;?>"/>

<?php
if ($savemsg) {
	/* Display save message */
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
	echo '<tr><td>';
	$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
	$tab_array = array();
	$tab_array[] = array($menu_iface . gettext("Settings"), false, "/suricata/suricata_interfaces_edit.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Categories"), false, "/suricata/suricata_rulesets.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Rules"), false, "/suricata/suricata_rules.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Flow/Stream"), true, "/suricata/suricata_flow_stream.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("App Parsers"), false, "/suricata/suricata_app_parsers.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Variables"), false, "/suricata/suricata_define_vars.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/suricata/suricata_barnyard.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/suricata/suricata_ip_reputation.php?id={$id}");
	display_top_tabs($tab_array, true);
?>
</td></tr>
<tr><td><div id="mainarea">

<?php if ($importalias) : ?>
	<?php include("/usr/local/www/suricata/suricata_import_aliases.php");
		if ($selectalias) {
			echo '<input type="hidden" name="eng_name" value="' . $eng_name . '"/>';
			echo '<input type="hidden" name="eng_bind" value="' . $eng_bind . '"/>';
			echo '<input type="hidden" name="eng_policy" value="' . $eng_policy . '"/>';
		}
	 ?>

<?php elseif ($add_edit_os_policy) : ?>
	<?php include("/usr/local/www/suricata/suricata_os_policy_engine.php"); ?>

<?php else: ?>

<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
	<tbody>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Host-Specific Defrag and Stream Settings"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Host OS Policy Assignment"); ?></td>
		<td width="78%" class="vtable">
			<table width="95%" align="left" id="hostOSEnginesTable" style="table-layout: fixed;" border="0" cellspacing="0" cellpadding="0">
				<colgroup>
					<col width="45%" align="left">
					<col width="45%" align="center">
					<col width="10%" align="right">
				</colgroup>
			   <thead>
				<tr>
					<th class="listhdrr" axis="string"><?php echo gettext("Name");?></th>
					<th class="listhdrr" axis="string"><?php echo gettext("Bind-To Address Alias");?></th>
					<th class="list" align="right"><input type="image" name="import_alias[]" src="../themes/<?= $g['theme'];?>/images/icons/icon_import_alias.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Import policy configuration from existing Aliases");?>"/>
					<input type="image" name="add_os_policy[]" src="../themes/<?= $g['theme'];?>/images/icons/icon_plus.gif" width="17" 
					height="17" border="0" title="<?php echo gettext("Add a new policy configuration");?>"/></th>
				</tr>
			   </thead>
				<tbody>
			<?php foreach ($pconfig['host_os_policy']['item'] as $f => $v): ?>
				<tr>
					<td class="listlr" align="left"><?=gettext($v['name']);?></td>
					<td class="listbg" align="center"><?=gettext($v['bind_to']);?></td>
					<td class="listt" align="right"><input type="image" name="edit_os_policy[]" value="<?=$f;?>" onclick="document.getElementById('eng_id').value='<?=$f;?>'" 
					src="/themes/<?=$g['theme'];?>/images/icons/icon_e.gif" 
					width="17" height="17" border="0" title="<?=gettext("Edit this policy configuration");?>"/>
			<?php if ($v['bind_to'] <> "all") : ?> 
					<input type="image" name="del_os_policy[]" value="<?=$f;?>" onclick="document.getElementById('eng_id').value='<?=$f;?>';return confirm('Are you sure you want to delete this entry?');" 
					src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" width="17" height="17" border="0" 
					title="<?=gettext("Delete this policy configuration");?>"/>
			<?php else : ?>
					<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x_d.gif" width="17" height="17" border="0" 
					title="<?=gettext("Default policy configuration cannot be deleted");?>">
			<?php endif ?>
					</td>
				</tr>
			<?php endforeach; ?>
				</tbody>
			</table>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("IP Defragmentation"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Fragmentation Memory Cap"); ?></td>
		<td width="78%" class="vtable">
			<input name="frag_memcap" type="text" class="formfld unknown" id="frag_memcap" size="9"
			value="<?=htmlspecialchars($pconfig['frag_memcap']);?>">&nbsp;
			<?php echo gettext("Max memory to be used for defragmentation.  Default is ") . 
			"<strong>" . gettext("33,554,432") . "</strong>" . gettext(" bytes (32 MB)."); ?><br/><br/>
			<?php echo gettext("Sets the maximum amount of memory, in bytes, to be used by the IP defragmentation engine."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Max Trackers");?></td>
		<td width="78%" class="vtable"><input name="ip_max_trackers" type="text" class="formfld unknown" id="ip_max_trackers" size="9" value="<?=htmlspecialchars($pconfig['ip_max_trackers']);?>">&nbsp;
		<?php echo gettext("Number of defragmented flows to follow.  Default is ") . 
		"<strong>" . gettext("65,535") . "</strong>" . gettext(" fragments.");?><br/><br/>
		<?php echo gettext("Sets the number of defragmented flows to follow for reassembly."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Max Fragments");?></td>
		<td width="78%" class="vtable"><input name="ip_max_frags" type="text" class="formfld unknown" id="ip_max_frags" size="9" value="<?=htmlspecialchars($pconfig['ip_max_frags']);?>">&nbsp;
		<?php echo gettext("Maximum number of IP fragments to hold.  Default is ") . "<strong>" . gettext("65,535") . "</strong>" . gettext(" fragments.");?><br/><br/>
		<?php echo gettext("Sets the maximum number of IP fragments to retain in memory while awaiting reassembly."); ?><br/><br/>
		<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . gettext("This must be equal to or greater than the Max Trackers value specified above."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Fragmentation Hash Table Size"); ?></td>
		<td width="78%" class="vtable">
			<input name="frag_hash_size" type="text" class="formfld unknown" id="frag_hash_size" size="9"
			value="<?=htmlspecialchars($pconfig['frag_hash_size']);?>">&nbsp;
			<?php echo gettext("Hash Table size.  Default is ") . "<strong>" . gettext("65,536") . "</strong>" . gettext(" entries."); ?><br/><br/>
			<?php echo gettext("Sets the size of the Hash Table used by the defragmentation engine."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Timeout");?></td>
		<td width="78%" class="vtable"><input name="ip_frag_timeout" type="text" class="formfld unknown" id="ip_frag_timeout" size="9" value="<?=htmlspecialchars($pconfig['ip_frag_timeout']);?>">&nbsp;
		<?php echo gettext("Max seconds to hold an IP fragement.  Default is ") . 
		"<strong>" . gettext("60") . "</strong>" . gettext(" seconds.");?><br/><br/>
		<?php echo gettext("Sets the number of seconds to hold an IP fragment in memory while awaiting the remainder of the packet to arrive."); ?>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Flow Manager Settings"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Flow Memory Cap"); ?></td>
		<td width="78%" class="vtable">
			<input name="flow_memcap" type="text" class="formfld unknown" id="flow_memcap" size="9"
			value="<?=htmlspecialchars($pconfig['flow_memcap']);?>">&nbsp;
			<?php echo gettext("Max memory, in bytes, to be used by the flow engine.  Default is ") . 
			"<strong>" . gettext("33,554,432") . "</strong>" . gettext(" bytes (32 MB)"); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Flow Hash Table Size"); ?></td>
		<td width="78%" class="vtable">
			<input name="flow_hash_size" type="text" class="formfld unknown" id="flow_hash_size" size="9"
			value="<?=htmlspecialchars($pconfig['flow_hash_size']);?>">&nbsp;
			<?php echo gettext("Hash Table size used by the flow engine.  Default is ") . 
			"<strong>" . gettext("65,536") . "</strong>" . gettext(" entries."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Preallocated Flows"); ?></td>
		<td width="78%" class="vtable">
			<input name="flow_prealloc" type="text" class="formfld unknown" id="flow_prealloc" size="9"
			value="<?=htmlspecialchars($pconfig['flow_prealloc']);?>">&nbsp;
			<?php echo gettext("Number of preallocated flows ready for use.  Default is ") . 
			"<strong>" . gettext("10,000") . "</strong>" . gettext(" flows."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Emergency Recovery"); ?></td>
		<td width="78%" class="vtable">
			<input name="flow_emerg_recovery" type="text" class="formfld unknown" id="flow_emerg_recovery" size="9"
			value="<?=htmlspecialchars($pconfig['flow_emerg_recovery']);?>">&nbsp;
			<?php echo gettext("Percentage of preallocated flows to complete before exiting Emergency Mode.  Default is ") . 
			"<strong>" . gettext("30%") . "</strong>."; ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Prune Flows"); ?></td>
		<td width="78%" class="vtable">
			<input name="flow_prune" type="text" class="formfld unknown" id="flow_prune" size="9"
			value="<?=htmlspecialchars($pconfig['flow_prune']);?>">&nbsp;
			<?php echo gettext("Number of flows to prune in Emergency Mode when allocating a new flow.  Default is ") . 
			"<strong>" . gettext("5") . "</strong>" . gettext(" flows."); ?>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Flow Timeout Settings"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("TCP Connections"); ?></td>
		<td width="78%" class="vtable">
			<table width="100%" cellspacing="4" cellpadding="0" border="0">
				<tbody>
				<tr>
					<td class="vexpl"><input name="flow_tcp_new_timeout" type="text" class="formfld unknown" id="flow_tcp_new_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_tcp_new_timeout']);?>">&nbsp;
					<?php echo gettext("New TCP connection timeout in seconds.  Default is ") . "<strong>" . gettext("60") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><input name="flow_tcp_established_timeout" type="text" class="formfld unknown" id="flow_tcp_established_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_tcp_established_timeout']);?>">&nbsp;
					<?php echo gettext("Established TCP connection timeout in seconds.  Default is ") . "<strong>" . gettext("3600") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><input name="flow_tcp_closed_timeout" type="text" class="formfld unknown" id="flow_tcp_closed_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_tcp_closed_timeout']);?>">&nbsp;
					<?php echo gettext("Closed TCP connection timeout in seconds.  Default is ") . "<strong>" . gettext("120") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><input name="flow_tcp_emerg_new_timeout" type="text" class="formfld unknown" id="flow_tcp_emerg_new_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_tcp_emerg_new_timeout']);?>">&nbsp;
					<?php echo gettext("Emergency New TCP connection timeout in seconds.  Default is ") . "<strong>" . gettext("10") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><input name="flow_tcp_emerg_established_timeout" type="text" class="formfld unknown" id="flow_tcp_emerg_established_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_tcp_emerg_established_timeout']);?>">&nbsp;
					<?php echo gettext("Emergency Established TCP connection timeout in seconds.  Default is ") . "<strong>" . gettext("300") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><input name="flow_tcp_emerg_closed_timeout" type="text" class="formfld unknown" id="flow_tcp_emerg_closed_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_tcp_emerg_closed_timeout']);?>">&nbsp;
					<?php echo gettext("Emergency Closed TCP connection timeout in seconds.  Default is ") . "<strong>" . gettext("20") . "</strong>."; ?>
					</td>
				</tr>
				</tbody>
			</table>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("UDP Connections"); ?></td>
		<td width="78%" class="vtable">
			<table width="100%" cellspacing="4" cellpadding="0" border="0">
				<tbody>
				<tr>
					<td class="vexpl"><input name="flow_udp_new_timeout" type="text" class="formfld unknown" id="flow_udp_new_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_udp_new_timeout']);?>">&nbsp;
					<?php echo gettext("New UDP connection timeout in seconds.  Default is ") . "<strong>" . gettext("30") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><input name="flow_udp_established_timeout" type="text" class="formfld unknown" id="flow_udp_established_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_udp_established_timeout']);?>">&nbsp;
					<?php echo gettext("Established UDP connection timeout in seconds.  Default is ") . "<strong>" . gettext("300") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><input name="flow_udp_emerg_new_timeout" type="text" class="formfld unknown" id="flow_udp_emerg_new_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_udp_emerg_new_timeout']);?>">&nbsp;
					<?php echo gettext("Emergency New UDP connection timeout in seconds.  Default is ") . "<strong>" . gettext("10") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><input name="flow_udp_emerg_established_timeout" type="text" class="formfld unknown" id="flow_udp_emerg_established_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_udp_emerg_established_timeout']);?>">&nbsp;
					<?php echo gettext("Emergency Established UDP connection timeout in seconds.  Default is ") . "<strong>" . gettext("100") . "</strong>."; ?>
					</td>
				</tr>
				</tbody>
			</table>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("ICMP Connections"); ?></td>
		<td width="78%" class="vtable">
			<table width="100%" cellspacing="4" cellpadding="0" border="0">
				<tbody>
				<tr>
					<td class="vexpl"><input name="flow_icmp_new_timeout" type="text" class="formfld unknown" id="flow_icmp_new_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_icmp_new_timeout']);?>">&nbsp;
					<?php echo gettext("New ICMP connection timeout in seconds.  Default is ") . "<strong>" . gettext("30") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><input name="flow_icmp_established_timeout" type="text" class="formfld unknown" id="flow_icmp_established_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_icmp_established_timeout']);?>">&nbsp;
					<?php echo gettext("Established ICMP connection timeout in seconds.  Default is ") . "<strong>" . gettext("300") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><input name="flow_icmp_emerg_new_timeout" type="text" class="formfld unknown" id="flow_icmp_emerg_new_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_icmp_emerg_new_timeout']);?>">&nbsp;
					<?php echo gettext("Emergency New ICMP connection timeout in seconds.  Default is ") . "<strong>" . gettext("10") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td class="vexpl"><input name="flow_icmp_emerg_established_timeout" type="text" class="formfld unknown" id="flow_icmp_emerg_established_timeout" 
					size="9" value="<?=htmlspecialchars($pconfig['flow_icmp_emerg_established_timeout']);?>">&nbsp;
					<?php echo gettext("Emergency Established ICMP connection timeout in seconds.  Default is ") . "<strong>" . gettext("100") . "</strong>."; ?>
					</td>
				</tr>
				</tbody>
			</table>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Stream Engine Settings"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Stream Memory Cap"); ?></td>
		<td width="78%" class="vtable">
			<input name="stream_memcap" type="text" class="formfld unknown" id="stream_memcap" size="9"
			value="<?=htmlspecialchars($pconfig['stream_memcap']);?>">&nbsp;
			<?php echo gettext("Max memory to be used by stream engine.  Default is ") . 
			"<strong>" . gettext("67,108,864") . "</strong>" . gettext(" bytes (64MB)"); ?><br/><br/>
			<?php echo gettext("Sets the maximum amount of memory, in bytes, to be used by the stream engine.  ");?><br/>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . 
			gettext("This number will likely need to be increased beyond the default value in systems with more than 4 processor cores.  " . 
			"If Suricata fails to start and logs a memory allocation error, increase this value in 4 MB chunks until Suricata starts successfully."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Preallocated Sessions"); ?></td>
		<td width="78%" class="vtable">
			<input name="stream_prealloc_sessions" type="text" class="formfld unknown" id="stream_prealloc_sessions" size="9"
			value="<?=htmlspecialchars($pconfig['stream_prealloc_sessions']);?>">&nbsp;
			<?php echo gettext("Number of preallocated stream engine sessions.  Default is ") . 
			"<strong>" . gettext("32,768") . "</strong>" . gettext(" sessions."); ?><br/><br/>
			<?php echo gettext("Sets the number of stream engine sessions to preallocate.  This can be a performance enhancement."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable Mid-Stream Sessions"); ?></td>
		<td width="78%" class="vtable"><input name="enable_midstream_sessions" type="checkbox" value="on" <?php if ($pconfig['enable_midstream_sessions'] == "on") echo "checked"; ?>>
			<?php echo gettext("Suricata will pick up and track sessions mid-stream.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Enable Async Streams"); ?></td>
		<td width="78%" class="vtable"><input name="enable_async_sessions" type="checkbox" value="on" <?php if ($pconfig['enable_async_sessions'] == "on") echo "checked"; ?>>
			<?php echo gettext("Suricata will track asynchronous one-sided streams.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Reassembly Memory Cap"); ?></td>
		<td width="78%" class="vtable">
			<input name="reassembly_memcap" type="text" class="formfld unknown" id="reassembly_memcap" size="9"
			value="<?=htmlspecialchars($pconfig['reassembly_memcap']);?>">&nbsp;
			<?php echo gettext("Max memory to be used for stream reassembly.  Default is ") . 
			"<strong>" . gettext("67,108,864") . "</strong>" . gettext(" bytes (64MB)."); ?><br/><br/>
			<?php echo gettext("Sets the maximum amount of memory, in bytes, to be used for stream reassembly."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Reassembly Depth"); ?></td>
		<td width="78%" class="vtable">
			<input name="reassembly_depth" type="text" class="formfld unknown" id="reassembly_depth" size="9"
			value="<?=htmlspecialchars($pconfig['reassembly_depth']);?>">&nbsp;
			<?php echo gettext("Amount of a stream to reassemble.  Default is ") . 
			"<strong>" . gettext("1,048,576") . "</strong>" . gettext(" bytes (1MB)."); ?><br/><br/>
			<?php echo gettext("Sets the depth, in bytes, of a stream to be reassembled by the stream engine.") . "<br/>" . 
			"<span class=\"red\"><strong>" . gettext("Note: ") . "</strong></span>" . gettext("Set to 0 (unlimited) to reassemble entire stream.  This is required for file extraction."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("To-Server Chunk Size"); ?></td>
		<td width="78%" class="vtable">
			<input name="reassembly_to_server_chunk" type="text" class="formfld unknown" id="reassembly_to_server_chunk" size="9"
			value="<?=htmlspecialchars($pconfig['reassembly_to_server_chunk']);?>">&nbsp;
			<?php echo gettext("Size of raw stream chunks to inspect.  Default is ") . 
			"<strong>" . gettext("2,560") . "</strong>" . gettext(" bytes."); ?><br/><br/>
			<?php echo gettext("Sets the chunk size, in bytes, for raw stream inspection performed for 'to-server' traffic."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("To-Client Chunk Size"); ?></td>
		<td width="78%" class="vtable">
			<input name="reassembly_to_client_chunk" type="text" class="formfld unknown" id="reassembly_to_client_chunk" size="9"
			value="<?=htmlspecialchars($pconfig['reassembly_to_client_chunk']);?>">&nbsp;
			<?php echo gettext("Amount of a stream to reassemble.  Default is ") . 
			"<strong>" . gettext("2,560") . "</strong>" . gettext(" bytes."); ?><br/><br/>
			<?php echo gettext("Sets the chunk size, in bytes, for raw stream inspection performed for 'to-client' traffic."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top">&nbsp;</td>
		<td width="78%">
			<input name="save" type="submit" class="formbtn" value="Save" title="<?php echo 
			gettext("Save flow and stream settings"); ?>">
			<input name="id" type="hidden" value="<?=$id;?>">&nbsp;&nbsp;&nbsp;&nbsp;
			<input name="ResetAll" type="submit" class="formbtn" value="Reset" title="<?php echo 
			gettext("Reset all settings to defaults") . "\" onclick=\"return confirm('" . 
			gettext("WARNING:  This will reset ALL flow and stream settings to their defaults.  Click OK to continue or CANCEL to quit.") . 
			"');\""; ?>></td>
	</tr>
	<tr>
		<td width="22%" valign="top">&nbsp;</td>
		<td width="78%"><span class="vexpl"><span class="red"><strong><?php echo gettext("Note: "); ?></strong></span></span>
			<?php echo gettext("Please save your settings before you exit.  Changes will rebuild the rules file.  This "); ?>
			<?php echo gettext("may take several seconds.  Suricata must also be restarted to activate any changes made on this screen."); ?></td>
	</tr>
	</tbody>
</table>

<?php endif; ?>

</div>
</td></tr></tbody></table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
