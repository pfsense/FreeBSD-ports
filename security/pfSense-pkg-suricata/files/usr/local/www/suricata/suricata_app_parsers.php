<?php
/*
 * suricata_app_parsers.php
*  Copyright (c)  2004-2016  Electric Sheep Fencing, LLC. All rights reserved.
*
*  Redistribution and use in source and binary forms, with or without modification,
*  are permitted provided that the following conditions are met:
*
*  1. Redistributions of source code must retain the above copyright notice,
*      this list of conditions and the following disclaimer.
*
*  2. Redistributions in binary form must reproduce the above copyright
*      notice, this list of conditions and the following disclaimer in
*      the documentation and/or other materials provided with the
*      distribution.
*
*  3. All advertising materials mentioning features or use of this software
*      must display the following acknowledgment:
*      "This product includes software developed by the pfSense Project
*       for use in the pfSense software distribution. (http://www.pfsense.org/).
*
*  4. The names "pfSense" and "pfSense Project" must not be used to
*       endorse or promote products derived from this software without
*       prior written permission. For written permission, please contact
*       coreteam@pfsense.org.
*
*  5. Products derived from this software may not be called "pfSense"
*      nor may "pfSense" appear in their names without prior written
*      permission of the Electric Sheep Fencing, LLC.
*
*  6. Redistributions of any form whatsoever must retain the following
*      acknowledgment:
*
*  "This product includes software developed by the pfSense Project
*  for use in the pfSense software distribution (http://www.pfsense.org/).
*
*  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
*  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
*  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
*  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
*  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
*  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
*  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
*  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
*  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
*  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
*  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
*  OF THE POSSIBILITY OF SUCH DAMAGE.
*
*
* Portions of this code are based on original work done for the Snort package for pfSense by the following contributors:
*
* Copyright (C) 2003-2004 Manuel Kasper
* Copyright (C) 2005 Bill Marquette
* Copyright (C) 2006 Scott Ullrich (copyright assigned to ESF)
* Copyright (C) 2009 Robert Zelaya Sr. Developer
* Copyright (C) 2012 Ermal Luci  (copyright assigned to ESF)
* Copyright (C) 2016 Bill Meeks
*
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

if (!is_array($config['installedpackages']['suricata']))
	$config['installedpackages']['suricata'] = array();
if (!is_array($config['installedpackages']['suricata']['rule']))
	$config['installedpackages']['suricata']['rule'] = array();

// Initialize HTTP libhtp engine arrays if necessary
if (!is_array($config['installedpackages']['suricata']['rule'][$id]['libhtp_policy']['item']))
	$config['installedpackages']['suricata']['rule'][$id]['libhtp_policy']['item'] = array();

// Initialize required array variables as necessary
if (!is_array($config['aliases']['alias']))
	$config['aliases']['alias'] = array();
$a_aliases = $config['aliases']['alias'];

$a_nat = &$config['installedpackages']['suricata']['rule'];

$libhtp_engine_next_id = count($a_nat[$id]['libhtp_policy']['item']);

// Build a lookup array of currently used engine 'bind_to' Aliases
// so we can screen matching Alias names from the list.
$used = array();
foreach ($a_nat[$id]['libhtp_policy']['item'] as $v)
	$used[$v['bind_to']] = true;

$pconfig = array();
if (isset($id) && $a_nat[$id]) {
	/* Get current values from config for page form fields */
	$pconfig = $a_nat[$id];

	// See if Host-OS policy engine array is configured and use
	// it; otherwise create a default engine configuration.
	if (empty($pconfig['libhtp_policy']['item'])) {
		$default = array( "name" => "default", "bind_to" => "all", "personality" => "IDS",
				  "request-body-limit" => 4096, "response-body-limit" => 4096,
				  "double-decode-path" => "no", "double-decode-query" => "no",
				  "uri-include-all" => "no" );
		$pconfig['libhtp_policy']['item'] = array();
		$pconfig['libhtp_policy']['item'][] = $default;
		if (!is_array($a_nat[$id]['libhtp_policy']['item']))
			$a_nat[$id]['libhtp_policy']['item'] = array();
		$a_nat[$id]['libhtp_policy']['item'][] = $default;
		write_config("Suricata pkg: created a new default HTTP server configuration for " . convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']));
		$libhtp_engine_next_id++;
	}
	else
		$pconfig['libhtp_policy'] = $a_nat[$id]['libhtp_policy'];
}

// Check for "import or select alias mode" and set flags if TRUE.
// "selectalias", when true, displays radio buttons to limit
// multiple selections.
if ($_POST['import_alias']) {
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

		if ($_POST['enable_double_decode_path']) { $engine['double-decode-path'] = 'yes'; }else{ $engine['double-decode-path'] = 'no'; }
		if ($_POST['enable_double_decode_query']) { $engine['double-decode-query'] = 'yes'; }else{ $engine['double-decode-query'] = 'no'; }
		if ($_POST['enable_uri_include_all']) { $engine['uri-include-all'] = 'yes'; }else{ $engine['uri-include-all'] = 'no'; }

		// Can only have one "all" Bind_To address
		if ($engine['bind_to'] == "all" && $engine['name'] != "default")
			$input_errors[] = gettext("Only one default OS-Policy Engine can be bound to all addresses.");

		// if no errors, write new entry to conf
		if (!$input_errors) {
			if (isset($eng_id) && $a_nat[$id]['libhtp_policy']['item'][$eng_id]) {
				$a_nat[$id]['libhtp_policy']['item'][$eng_id] = $engine;
			}
			else
				$a_nat[$id]['libhtp_policy']['item'][] = $engine;

			/* Reorder the engine array to ensure the */
			/* 'bind_to=all' entry is at the bottom   */
			/* if it contains more than one entry.	  */
			if (count($a_nat[$id]['libhtp_policy']['item']) > 1) {
				$i = -1;
				foreach ($a_nat[$id]['libhtp_policy']['item'] as $f => $v) {
					if ($v['bind_to'] == "all") {
						$i = $f;
						break;
					}
				}
				/* Only relocate the entry if we  */
				/* found it, and it's not already */
				/* at the end.					  */
				if ($i > -1 && ($i < (count($a_nat[$id]['libhtp_policy']['item']) - 1))) {
					$tmp = $a_nat[$id]['libhtp_policy']['item'][$i];
					unset($a_nat[$id]['libhtp_policy']['item'][$i]);
					$a_nat[$id]['libhtp_policy']['item'][] = $tmp;
				}
			}

			// Now write the new engine array to conf
			write_config("Suricata pkg: saved updated HTTP server configuration for " . convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']));
			$pconfig['libhtp_policy']['item'] = $a_nat[$id]['libhtp_policy']['item'];
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
			  "request-body-limit" => "4096", "response-body-limit" => "4096",
			  "double-decode-path" => "no", "double-decode-query" => "no", "uri-include-all" => "no" );
	$eng_id = $libhtp_engine_next_id;
}
elseif ($_POST['edit_libhtp_policy']) {
	if ($_POST['eng_id'] != "") {
		$add_edit_libhtp_policy = true;
		$eng_id = $_POST['eng_id'];
		$pengcfg = $a_nat[$id]['libhtp_policy']['item'][$eng_id];
	}
}
elseif ($_POST['del_libhtp_policy']) {
	$natent = array();
	$natent = $pconfig;

	if ($_POST['eng_id'] != "") {
		unset($natent['libhtp_policy']['item'][$_POST['eng_id']]);
		$pconfig = $natent;
	}
	if (isset($id) && $a_nat[$id]) {
		$a_nat[$id] = $natent;
		write_config("Suricata pkg: deleted a HTTP server configuration for " . convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']));
	}
}
elseif ($_POST['cancel_libhtp_policy']) {
	$add_edit_libhtp_policy = false;
}
elseif ($_POST['ResetAll']) {

	/* Reset all the settings to defaults */
	$pconfig['asn1_max_frames'] = "256";
	$pconfig['dns_global_memcap'] = "16777216";
	$pconfig['dns_state_memcap'] = "524288";
	$pconfig['dns_request_flood_limit'] = "500";
	$pconfig['http_parser_memcap'] = "67108864";
	$pconfig['dns_parser_udp'] = "yes";
	$pconfig['dns_parser_tcp'] = "yes";
	$pconfig['http_parser'] = "yes";
	$pconfig['tls_parser'] = "yes";
	$pconfig['smtp_parser'] = "yes";
	$pconfig['imap_parser'] = "detection-only";
	$pconfig['ssh_parser'] = "yes";
	$pconfig['ftp_parser'] = "yes";
	$pconfig['dcerpc_parser'] = "yes";
	$pconfig['smb_parser'] = "yes";
	$pconfig['msn_parser'] = "detection-only";

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
			$eng_enable_double_decode_path = $_POST['eng_enable_double_decode_path'];
			$eng_enable_double_decode_query = $_POST['eng_enable_double_decode_query'];
			$eng_enable_uri_include_all = $_POST['eng_enable_uri_include_all'];
		}
	}
	else {
		$engine = array( "name" => "", "bind_to" => "", "personality" => "IDS",
				 "request-body-limit" => "4096", "response-body-limit" => "4096",
				 "double-decode-path" => "no", "double-decode-query" => "no", "uri-include-all" => "no" );

		// See if anything was checked to import
		if (is_array($_POST['aliastoimport']) && count($_POST['aliastoimport']) > 0) {
			foreach ($_POST['aliastoimport'] as $item) {
				$engine['name'] = strtolower($item);
				$engine['bind_to'] = $item;
				$a_nat[$id]['libhtp_policy']['item'][] = $engine;
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
			if (count($a_nat[$id]['libhtp_policy']['item']) > 1) {
				$i = -1;
				foreach ($a_nat[$id]['libhtp_policy']['item'] as $f => $v) {
					if ($v['bind_to'] == "all") {
						$i = $f;
						break;
					}
				}
				// Only relocate the entry if we
				// found it, and it's not already
				// at the end.
				if ($i > -1 && ($i < (count($a_nat[$id]['libhtp_policy']['item']) - 1))) {
					$tmp = $a_nat[$id]['libhtp_policy']['item'][$i];
					unset($a_nat[$id]['libhtp_policy']['item'][$i]);
					$a_nat[$id]['libhtp_policy']['item'][] = $tmp;
				}
				$pconfig['libhtp_policy']['item'] = $a_nat[$id]['libhtp_policy']['item'];
			}

			// Write the new engine array to config file
			write_config("Suricata pkg: saved an updated HTTP server configuration for " . convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']));
			$importalias = false;
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

	/* if no errors write to conf */
	if (!$input_errors) {
		if ($_POST['asn1_max_frames'] != "") { $natent['asn1_max_frames'] = $_POST['asn1_max_frames']; }else{ $natent['asn1_max_frames'] = "256"; }
		if ($_POST['dns_global_memcap'] != ""){ $natent['dns_global_memcap'] = $_POST['dns_global_memcap']; }else{ $natent['dns_global_memcap'] = "16777216"; }
		if ($_POST['dns_state_memcap'] != ""){ $natent['dns_state_memcap'] = $_POST['dns_state_memcap']; }else{ $natent['dns_state_memcap'] = "524288"; }
		if ($_POST['dns_request_flood_limit'] != ""){ $natent['dns_request_flood_limit'] = $_POST['dns_request_flood_limit']; }else{ $natent['dns_request_flood_limit'] = "500"; }
		if ($_POST['http_parser_memcap'] != ""){ $natent['http_parser_memcap'] = $_POST['http_parser_memcap']; }else{ $natent['http_parser_memcap'] = "67108864"; }

		$natent['dns_parser_udp'] = $_POST['dns_parser_udp'];
		$natent['dns_parser_tcp'] = $_POST['dns_parser_tcp'];
		$natent['http_parser'] = $_POST['http_parser'];
		$natent['tls_parser'] = $_POST['tls_parser'];
		$natent['smtp_parser'] = $_POST['smtp_parser'];
		$natent['imap_parser'] = $_POST['imap_parser'];
		$natent['ssh_parser'] = $_POST['ssh_parser'];
		$natent['ftp_parser'] = $_POST['ftp_parser'];
		$natent['dcerpc_parser'] = $_POST['dcerpc_parser'];
		$natent['smb_parser'] = $_POST['smb_parser'];
		$natent['msn_parser'] = $_POST['msn_parser'];

		/**************************************************/
		/* If we have a valid rule ID, save configuration */
		/* then update the suricata.conf file for this	  */
		/* interface.									  */
		/**************************************************/
		if (isset($id) && $a_nat[$id]) {
			$a_nat[$id] = $natent;
			write_config("Suricata pkg: saved updated app-layer parser configuration for " . convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']));
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
		header("Location: suricata_app_parsers.php?id=$id");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($pconfig['interface']);
$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Application Layer Parsers - {$if_friendly}"));
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
$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/suricata/suricata_barnyard.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/suricata/suricata_ip_reputation.php?id={$id}");
display_top_tabs($tab_array, true);
?>

<form action="suricata_app_parsers.php" method="post" name="iform" id="iform" class="form-horizontal">
	<input name="id" type="hidden" value="<?=$id?>"/>
	<input type="hidden" name="eng_id" id="eng_id" value="<?=$eng_id?>"/>

<?php

if ($importalias) {

	include("/usr/local/www/suricata/suricata_import_aliases.php");

	if ($selectalias) {
		echo '<input type="hidden" name="eng_name" value="' . $eng_name . '"/>';
		echo '<input type="hidden" name="eng_bind" value="' . $eng_bind . '"/>';
		echo '<input type="hidden" name="eng_personality" value="' . $eng_personality . '"/>';
		echo '<input type="hidden" name="eng_req_body_limit" value="' . $eng_req_body_limit . '"/>';
		echo '<input type="hidden" name="eng_resp_body_limit" value="' . $eng_resp_body_limit . '"/>';
		echo '<input type="hidden" name="eng_enable_double_decode_path" value="' . $eng_enable_double_decode_path . '"/>';
		echo '<input type="hidden" name="eng_enable_double_decode_query" value="' . $eng_enable_double_decode_query . '"/>';
		echo '<input type="hidden" name="eng_enable_uri_include_all" value="' . $eng_enable_uri_include_all . '"/>';
	}

} elseif ($add_edit_libhtp_policy) {

	include("/usr/local/www/suricata/suricata_libhtp_policy_engine.php");

} else {

	$form = new Form(false);

	$section = new Form_Section('Abstract Syntax One Settings');
	$section->addInput(new Form_Input(
		'asn1_max_frames',
		'Asn1 Max Frames',
		'text',
		$pconfig['asn1_max_frames']
	))->setHelp('Limit for max number of asn1 frames to decode. Default is 256 frames. To protect itself, Suricata will inspect only the maximum asn1 frames specified. Application layer protocols such as X.400 electronic mail, X.500 and LDAP directory services, H.323 (VoIP), and SNMP, use ASN.1 to describe the protocol data units (PDUs) they exchange.');
	print($section);

	$section = new Form_Section('DNS App-Layer Parser Settings');
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
	$section->addInput(new Form_Select(
		'dns_parser_udp',
		'UDP Parser',
		$pconfig['dns_parser_udp'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose a fast pattern matcher algorithm.');
	$section->addInput(new Form_Select(
		'dns_parser_tcp',
		'TCP Parser',
		$pconfig['dns_parser_tcp'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose a fast pattern matcher algorithm.');
	print($section);

	$section = new Form_Section('Other App-Layer Parser Settings');
	$section->addInput(new Form_Select(
		'tls_parser',
		'TLS Parser',
		$pconfig['tls_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for TLS. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'smtp_parser',
		'SMTP Parser',
		$pconfig['smtp_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for SMTP. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'imap_parser',
		'IMAP Parser',
		$pconfig['imap_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for IMAP. Default is detection-only. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'ssh_parser',
		'SSH Parser',
		$pconfig['ssh_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for SSH. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'ftp_parser',
		'FTP Parser',
		$pconfig['ftp_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for FTP. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'dcerpc_parser',
		'DCERPC Parser',
		$pconfig['dcerpc_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for DCERPC. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'smb_parser',
		'SMB Parser',
		$pconfig['smb_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for SMB. Default is yes. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
	$section->addInput(new Form_Select(
		'msn_parser',
		'MSN Parser',
		$pconfig['msn_parser'],
		array(  "yes" => "yes", "no" => "no", "detection-only" => "detection-only" )
	))->setHelp('Choose the parser/detection setting for MSN. Default is detection-only. Selecting "yes" enables detection and parser, "no" disables both and "detection-only" disables parser.');
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
											<i class="fa fa-upload icon-embed-btn"></i>
											<?=gettext("Import"); ?>
										</button>
										<button type="submit" name="add_libhtp_policy" class="btn btn-sm btn-success" title="<?=gettext("Add a new server configuration")?>" value="Add">
											<i class="fa fa-plus icon-embed-btn"></i>
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
										<button type="submit" name="edit_libhtp_policy[]" value="Edit" class="btn btn-sm btn-primary" onclick="document.getElementById('eng_id').value='<?=$f?>'" title="<?=gettext("Edit this server configuration")?>">
											<i class="fa fa-pencil icon-embed-btn"></i>
											<?=gettext("Edit"); ?>

										</button>
									<?php if ($v['bind_to'] != "all") : ?>
										<button type="submit" name="del_libhtp_policy[]" value="Delete" class="btn btn-sm btn-danger" onclick="document.getElementById('eng_id').value='<?=$f?>';return confirm('Are you sure you want to delete this entry?');" title="<?=gettext("Delete this server configuration")?>">
											<i class="fa fa-trash icon-embed-btn"></i>
											<?=gettext("Delete"); ?>

										</button>
									<?php else : ?>
										<button type="submit" name="del_libhtp_policy[]" value="Delete" class="btn btn-sm btn-danger" title="<?=gettext("Delete this server configuration")?>" disabled>
											<i class="fa fa-trash icon-embed-btn"></i>
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
			<i class="fa fa-save icon-embed-btn"></i>
			<?=gettext('Save');?>
		</button>
	</div>

<?php } ?>

</form>

<?php include("foot.inc"); ?>

