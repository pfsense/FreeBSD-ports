<?php
/*
 * snort_interfaces_edit.php
 *
 * Copyright (C) 2008-2009 Robert Zelaya.
 * Copyright (C) 2011-2012 Ermal Luci
 * Copyright (C) 2014 Bill Meeks
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

global $g, $config, $rebuild_rules;

$snortdir = SNORTDIR;
$snortlogdir = SNORTLOGDIR;

if (!is_array($config['installedpackages']['snortglobal']))
	$config['installedpackages']['snortglobal'] = array();
$snortglob = $config['installedpackages']['snortglobal'];

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
$a_rule = &$config['installedpackages']['snortglobal']['rule'];

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
        header("Location: /snort/snort_interfaces.php");
        exit;
}

if (isset($_POST['action']))
	$action = htmlspecialchars($_POST['action'], ENT_QUOTES | ENT_HTML401);
elseif (isset($_GET['action']))
	$action = htmlspecialchars($_GET['action'], ENT_QUOTES | ENT_HTML401);
else
	$action = "";

$pconfig = array();
if (empty($snortglob['rule'][$id]['uuid'])) {
	/* Adding new interface, so flag rules to build. */
	$pconfig['uuid'] = snort_generate_id();
	$rebuild_rules = true;
}
else {
	$pconfig['uuid'] = $a_rule[$id]['uuid'];
	$pconfig['descr'] = $a_rule[$id]['descr'];
	$rebuild_rules = false;
}
$snort_uuid = $pconfig['uuid'];

// Get the physical configured interfaces on the firewall
$interfaces = get_configured_interface_with_descr();

// See if interface is already configured, and use its values
if (isset($id) && $a_rule[$id]) {
	/* old options */
	$pconfig = $a_rule[$id];
	if (!empty($pconfig['configpassthru']))
		$pconfig['configpassthru'] = base64_decode($pconfig['configpassthru']);
	if (empty($pconfig['uuid']))
		$pconfig['uuid'] = $snort_uuid;
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
			$pconfig['descr'] = convert_friendly_interface_to_friendly_descr($i);
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
if (empty($pconfig['blockoffendersip']))
	$pconfig['blockoffendersip'] = "both";
if (empty($pconfig['performance']))
	$pconfig['performance'] = "ac-bnfa";
if (empty($pconfig['alertsystemlog_facility']))
	$pconfig['alertsystemlog_facility'] = "log_auth";
if (empty($pconfig['alertsystemlog_priority']))
	$pconfig['alertsystemlog_priority'] = "log_alert";

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
			$pconfig['descr'] = convert_friendly_interface_to_friendly_descr($i);
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

if ($_POST["save"] && !$input_errors) {
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

	// If Snort is disabled on this interface, stop any running instance,
	// save the change, and exit.
	if ($_POST['enable'] != 'on') {
		$a_rule[$id]['enable'] = $_POST['enable'] ? 'on' : 'off';
		touch("{$g['varrun_path']}/snort_{$a_rule[$id]['uuid']}.disabled");
		touch("{$g['varrun_path']}/barnyard2_{$a_rule[$id]['uuid']}.disabled");
		snort_stop($a_rule[$id], get_real_interface($a_rule[$id]['interface']));
		write_config("Snort pkg: modified interface configuration for {$a_rule[$id]['interface']}.");
		$rebuild_rules = false;
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_interfaces.php");
		exit;
	}

	/* if no errors write to conf */
	if (!$input_errors) {
		/* Most changes don't require a rules rebuild, so default to "off" */
		$rebuild_rules = false;

		$natent = $a_rule[$id];
		$natent['interface'] = $_POST['interface'];
		$natent['enable'] = $_POST['enable'] ? 'on' : 'off';
		$natent['uuid'] = $pconfig['uuid'];

		/* See if the HOME_NET, EXTERNAL_NET, or SUPPRESS LIST values were changed */
		$snort_reload = false;
		if ($_POST['homelistname'] && ($_POST['homelistname'] <> $natent['homelistname']))
			$snort_reload = true;
		if ($_POST['externallistname'] && ($_POST['externallistname'] <> $natent['externallistname']))
			$snort_reload = true;
		if ($_POST['suppresslistname'] && ($_POST['suppresslistname'] <> $natent['suppresslistname']))
			$snort_reload = true;

		if ($_POST['descr']) $natent['descr'] =  $_POST['descr']; else $natent['descr'] = convert_friendly_interface_to_friendly_descr($natent['interface']);
		if ($_POST['performance']) $natent['performance'] = $_POST['performance']; else  unset($natent['performance']);
		/* if post = on use on off or rewrite the conf */
		if ($_POST['blockoffenders7'] == "on") $natent['blockoffenders7'] = 'on'; else $natent['blockoffenders7'] = 'off';
		if ($_POST['blockoffenderskill'] == "on") $natent['blockoffenderskill'] = 'on'; else unset($natent['blockoffenderskill']);
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
				if (snort_is_running($a_rule[$id]['uuid'], $oif_real)) {
					snort_stop($a_rule[$id], $oif_real);
					$snort_start = true;
				}
				else
					$snort_start = false;
				@rename("{$snortlogdir}/snort_{$oif_real}{$a_rule[$id]['uuid']}", "{$snortlogdir}/snort_{$if_real}{$a_rule[$id]['uuid']}");
				conf_mount_rw();
				@rename("{$snortdir}/snort_{$a_rule[$id]['uuid']}_{$oif_real}", "{$snortdir}/snort_{$a_rule[$id]['uuid']}_{$if_real}");
				conf_mount_ro();
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
					   "post_depth" => -1, "max_headers" => 0, "max_spaces" => 0, "max_header_length" => 0, "ports" => "default" );

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

			$a_rule[] = $natent;
		}

		/* If Snort is disabled on this interface, stop any running instance */
		if ($natent['enable'] != 'on')
			snort_stop($natent, $if_real);

		/* Save configuration changes */
		write_config("Snort pkg: modified interface configuration for {$natent['interface']}.");

		/* Update snort.conf and snort.sh files for this interface */
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();

		/* See if we need to restart Snort after an interface re-assignment */
		if ($snort_start == true) {
			snort_start($natent, $if_real);
		}

		/*******************************************************/
		/* Signal Snort to reload configuration if we changed  */
		/* HOME_NET, EXTERNAL_NET or Suppress list values.     */
		/* The function only signals a running Snort instance  */
		/* to safely reload these parameters.                  */
		/*******************************************************/
		if ($snort_reload == true)
			snort_reload_config($natent, "SIGHUP");

		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_interfaces.php");
		exit;
	} else
		$pconfig = $_POST;
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_rule[$id]['interface']);
$pgtitle = gettext("Snort: Interface {$if_friendly} - Edit Settings");
include_once("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc");

	/* Display Alert message */
	if ($input_errors) {
		print_input_errors($input_errors);
	}

	if ($savemsg) {
		print_info_box($savemsg);
	}
?>

<form action="snort_interfaces_edit.php" method="post" name="iform" id="iform">
<input name="id" type="hidden" value="<?=$id;?>"/>
<input name="action" type="hidden" value="<?=$action;?>"/>
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
	echo '<tr><td class="tabnavtbl">';
	$tab_array = array();
	$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
	$tab_array[] = array($menu_iface . gettext("Settings"), true, "/snort/snort_interfaces_edit.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Categories"), false, "/snort/snort_rulesets.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Rules"), false, "/snort/snort_rules.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Variables"), false, "/snort/snort_define_servers.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Preprocs"), false, "/snort/snort_preprocessors.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/snort/snort_barnyard.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/snort/snort_ip_reputation.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
	display_top_tabs($tab_array, true);
?>
</td></tr>
<tr><td><div id="mainarea">
<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("General Settings"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><?php echo gettext("Enable"); ?></td>
		<td width="78%" valign="top" class="vtable">&nbsp;
	<?php
		if ($pconfig['enable'] == "on")
			$checked = "checked";
		echo "
			<input name=\"enable\" type=\"checkbox\" value=\"on\" $checked onClick=\"enable_change(false)\"/>
			&nbsp;&nbsp;" . gettext("Enable or Disable") . "\n";
	?>
		<br/>
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
			<span class="vexpl"><?php echo gettext("Choose which interface this Snort instance applies to."); ?><br/>
				<span class="red"><?php echo gettext("Hint:"); ?></span>&nbsp;<?php echo gettext("In most cases, you'll want to use WAN here."); ?></span><br/></td>
	</tr>
	<tr>
				<td width="22%" valign="top" class="vncellreq"><?php echo gettext("Description"); ?></td>
				<td width="78%" class="vtable"><input name="descr" type="text" 
				class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']); ?>"/><br/>
				<span class="vexpl"><?php echo gettext("Enter a meaningful description here for your reference."); ?></span><br/></td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Alert Settings"); ?></td>
	</tr>
	<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Send Alerts to System Logs"); ?></td>
				<td width="78%" class="vtable"><input name="alertsystemlog" type="checkbox" value="on" onclick="toggle_system_log();" <?php if ($pconfig['alertsystemlog'] == "on") echo " checked"; ?>/>
				<?php echo gettext("Snort will send Alerts to the firewall's system logs."); ?></td>
	</tr>
	<tbody id="alertsystemlog_rows">
		<tr>
			<td width="22%" valign="top" class="vncell"><?php echo gettext("System Log Facility"); ?></td>
			<td width="78%" class="vtable">
				<select name="alertsystemlog_facility" id="alertsystemlog_facility" class="formselect">
				<?php
					$log_facility = array(  "log_auth", "log_authpriv", "log_daemon", "log_user", "log_local0", "log_local1",
								"log_local2", "log_local3", "log_local4", "log_local5", "log_local6", "log_local7" );
					foreach ($log_facility as $facility) {
						$selected = "";
						if ($facility == $pconfig['alertsystemlog_facility'])
							$selected = " selected";
						echo "<option value='{$facility}'{$selected}>" . $facility . "</option>\n";
					}
				?></select>&nbsp;&nbsp;
				<?php echo gettext("Select system log Facility to use for reporting.  Default is ") . "<strong>" . gettext("log_auth") . "</strong>."; ?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?php echo gettext("System Log Priority"); ?></td>
			<td width="78%" class="vtable">
				<select name="alertsystemlog_priority" id="alertsystemlog_priority" class="formselect">
				<?php
					$log_priority = array( "log_emerg", "log_crit", "log_alert", "log_err", "log_warning", "log_notice", "log_info", "log_debug" );
					foreach ($log_priority as $priority) {
						$selected = "";
						if ($priority == $pconfig['alertsystemlog_priority'])
							$selected = " selected";
						echo "<option value='{$priority}'{$selected}>" . $priority . "</option>\n";
					}
				?></select>&nbsp;&nbsp;
				<?php echo gettext("Select system log Priority (Level) to use for reporting.  Default is ") . "<strong>" . gettext("log_alert") . "</strong>."; ?>
			</td>
		</tr>
	</tbody>
	<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Block Offenders"); ?></td>
				<td width="78%" class="vtable">
					<input name="blockoffenders7" id="blockoffenders7" type="checkbox" value="on"
					<?php if ($pconfig['blockoffenders7'] == "on") echo "checked"; ?>
					onClick="enable_blockoffenders();" />
				<?php echo gettext("Checking this option will automatically block hosts that generate a " .
				"Snort alert."); ?></td>
	</tr>
	<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Kill States"); ?></td>
				<td width="78%" class="vtable">
					<input name="blockoffenderskill" id="blockoffenderskill" type="checkbox" value="on" <?php if ($pconfig['blockoffenderskill'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Checking this option will kill firewall states for the blocked IP"); ?>
				</td>
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
				<?php echo gettext("Select which IP extracted from the packet you wish to block"); ?><br/>
				<span class="red"><?php echo gettext("Hint:") . "</span>&nbsp;" . gettext("Choosing BOTH is suggested, and it is the default value."); ?><br/>
				</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Detection Performance Settings"); ?></td>
	</tr>
	<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Search Method"); ?></td>
				<td width="78%" class="vtable">
					<select name="performance" class="formselect" id="performance">
					<?php
					$interfaces2 = array('ac-bnfa' => 'AC-BNFA', 'ac-split' => 'AC-SPLIT', 'lowmem' => 'LOWMEM', 'ac-std' => 'AC-STD', 'ac' => 'AC',
					'ac-nq' => 'AC-NQ', 'ac-bnfa-nq' => 'AC-BNFA-NQ', 'lowmem-nq' => 'LOWMEM-NQ', 'ac-banded' => 'AC-BANDED', 
					'ac-sparsebands' => 'AC-SPARSEBANDS', 'acs' => 'ACS');
					foreach ($interfaces2 as $iface2 => $ifacename2): ?>
					<option value="<?=$iface2;?>"
					<?php if ($iface2 == $pconfig['performance']) echo "selected"; ?>>
					<?=htmlspecialchars($ifacename2);?></option>
					<?php endforeach; ?>
					</select>&nbsp;&nbsp;
				<?php echo gettext("Choose a fast pattern matcher algorithm. ") . "<strong>" . gettext("Default") . 
				"</strong>" . gettext(" is ") . "<strong>" . gettext("AC-BNFA") . "</strong>"; ?>.<br/><br/>
				<span class="vexpl"><?php echo gettext("LOWMEM and AC-BNFA are recommended for low end " .
				"systems, AC-SPLIT: low memory, high performance, short-hand for search-method ac split-any-any, AC: high memory, " .
				"best performance, -NQ: the -nq option specifies that matches should not be queued and evaluated as they are found," . 
				" AC-STD: moderate memory, high performance, ACS: small memory, moderate performance, " .
				"AC-BANDED: small memory,moderate performance, AC-SPARSEBANDS: small memory, high performance."); ?>
				</span><br/></td>
	</tr>
	<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Split ANY-ANY"); ?></td>
				<td width="78%" class="vtable">
					<input name="fpm_split_any_any" id="fpm_split_any_any" type="checkbox" value="on" <?php if ($pconfig['fpm_split_any_any'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Enable splitting of ANY-ANY port group.") . " <strong>" . gettext("Default") . "</strong>" . gettext(" is ") . 
					"<strong>" . gettext("Not Checked") . "</strong>"; ?>.<br/>
					<br/><?php echo gettext("This setting is a memory/performance trade-off.  It reduces memory footprint by not " . 
					"putting the ANY-ANY port group into every port group, but instead splits these rules off into a single port group. " . 
					"But doing so may require two port group evaluations per packet - one for the specific port group and one for the ANY-ANY " . 
					"port group, thus potentially reducing performance."); ?>
				</td>
	</tr>
	<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Search Optimize"); ?></td>
				<td width="78%" class="vtable">
					<input name="fpm_search_optimize" id="fpm_search_optimize" type="checkbox" value="on" <?php if ($pconfig['fpm_search_optimize'] == "on" || empty($pconfig['fpm_search_optimize'])) echo "checked"; ?>/>
					<?php echo gettext("Enable search optimization.") . " <strong>" . gettext("Default") . "</strong>" . gettext(" is ") . 
					"<strong>" . gettext("Checked") . "</strong>"; ?>.<br/>
					<br/><?php echo gettext("This setting optimizes fast pattern memory when used with search-methods AC or AC-SPLIT " . 
					"by dynamically determining the size of a state based on the total number of states. When used with AC-BNFA, " . 
					"some fail-state resolution will be attempted, potentially increasing performance."); ?>
				</td>
	</tr>
	<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Stream Inserts"); ?></td>
				<td width="78%" class="vtable">
					<input name="fpm_no_stream_inserts" id="fpm_no_stream_inserts" type="checkbox" value="on" <? if ($pconfig['fpm_no_stream_inserts'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Do not evaluate stream inserted packets against the detection engine.") . " <strong>" . gettext("Default") . "</strong>" . gettext(" is ") . 
					"<strong>" . gettext("Not Checked") . "</strong>"; ?>.<br/>
					<br/><?php echo gettext("This is a potential performance improvement based on the idea the stream rebuilt packet " . 
					"will contain the payload in the inserted one, so the stream inserted packet does not need to be evaluated."); ?> 
				</td>
	</tr>
	<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Checksum Check Disable"); ?></td>
				<td width="78%" class="vtable">
					<input name="cksumcheck" id="cksumcheck" type="checkbox" value="on" <?php if ($pconfig['cksumcheck'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Disable checksum checking within Snort to improve performance."); ?>
					<br><span class="red"><?php echo gettext("Hint: ") . "</span>" . 
					gettext("Most of this is already done at the firewall/filter level, so it is usually safe to check this box."); ?>
				</td>
	</tr>
	<tr>
				<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Choose the networks Snort should inspect and whitelist"); ?></td>
	</tr>
	<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("Home Net"); ?></td>
				<td width="78%" class="vtable">

					<select name="homelistname" class="formselect" id="homelistname">
					<?php
						echo "<option value='default' >default</option>";
						/* find whitelist names and filter by type */
						if (is_array($snortglob['whitelist']['item'])) {
							foreach ($snortglob['whitelist']['item'] as $value) {
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
					<span class="vexpl"><?php echo gettext("Choose the Home Net you want this interface to use."); ?></span>
				 	<br/><br/>
					<span class="red"><?php echo gettext("Note:"); ?></span>&nbsp;<?php echo gettext("Default Home " .
					"Net adds only local networks, WAN IPs, Gateways, VPNs and VIPs."); ?><br/>
					<span class="red"><?php echo gettext("Hint:"); ?></span>&nbsp;<?php echo gettext("Create an Alias to hold a list of " .
					"friendly IPs that the firewall cannot see or to customize the default Home Net."); ?><br/>
				</td>
	</tr>
	<tr>
				<td width="22%" valign="top" class="vncell"><?php echo gettext("External Net"); ?></td>
				<td width="78%" class="vtable">
					<select name="externallistname" class="formselect" id="externallistname">
					<?php
						echo "<option value='default' >default</option>";
						/* find whitelist names and filter by type */
						if (is_array($snortglob['whitelist']['item'])) {
							foreach ($snortglob['whitelist']['item'] as $value) {
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
					"External Net is networks that are not Home Net.  Most users should leave this setting at default."); ?><br/>
					<span class="red"><?php echo gettext("Hint:"); ?></span>&nbsp;
					<?php echo gettext("Create a Pass List and add an Alias to it, and then assign the Pass List here for custom External Net settings."); ?><br/>
				</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Pass List"); ?></td>
		<td width="78%" class="vtable">
			<select name="whitelistname" class="formselect" id="whitelistname">
			<?php
				/* find whitelist (Pass List) names and filter by type, make sure to track by uuid */
				echo "<option value='default' >default</option>\n";
				if (is_array($snortglob['whitelist']['item'])) {
					foreach ($snortglob['whitelist']['item'] as $value) {
						if ($value['name'] == $pconfig['whitelistname'])
							echo "<option value='{$value['name']}' selected>";
						else
							echo "<option value='{$value['name']}'>";
						echo htmlspecialchars($value['name']) . '</option>';
					}
				}
			?>
			</select>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="button" class="formbtns" value="View List" onclick="viewList('<?=$id;?>','whitelistname','passlist')" 
			id="btnWhitelist" title="<?php echo gettext("Click to view currently selected Pass List contents"); ?>"/>
			<br/>
			<span class="vexpl"><?php echo gettext("Choose the Pass List you want this interface to " .
			"use."); ?> </span><br/><br/>
			<span class="red"><?php echo gettext("Note:"); ?></span>&nbsp;<?php echo gettext("This option will only be used when block offenders is on."); ?><br/>
			<span class="red"><?php echo gettext("Hint:"); ?></span>&nbsp;<?php echo gettext("The default " .
			"Pass List adds local networks, WAN IPs, Gateways, VPNs and VIPs.  Create an Alias to customize."); ?>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Choose a suppression or filtering file if desired"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Alert Suppression and Filtering"); ?></td>
		<td width="78%" class="vtable">
			<select name="suppresslistname" class="formselect" id="suppresslistname">
		<?php
			echo "<option value='default' >default</option>\n";
			if (is_array($snortglob['suppress']['item'])) {
				$slist_select = $snortglob['suppress']['item'];
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
		<span class="vexpl"><?php echo gettext("Choose the suppression or filtering file you " .
		"want this interface to use."); ?> </span><br/>&nbsp;<br/><span class="red"><?php echo gettext("Note: ") . "</span>" . 
		gettext("Default option disables suppression and filtering."); ?>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Arguments here will " .
		"be automatically inserted into the Snort configuration."); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Advanced configuration pass-through"); ?></td>
		<td width="78%" class="vtable">
			<textarea style="width:98%; height:100%;" wrap="off" name="configpassthru" cols="60" rows="8" id="configpassthru"><?=htmlspecialchars($pconfig['configpassthru']);?></textarea>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top"></td>
		<td width="78%"><input name="save" type="submit" class="formbtn" value="Save" title="<?php echo 
			gettext("Click to save settings and exit"); ?>"/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top">&nbsp;</td>
		<td width="78%"><span class="vexpl"><span class="red"><strong><?php echo gettext("Note: ") . "</strong></span></span>" . 
			gettext("Please save your settings before you attempt to start Snort."); ?>	
		</td>
	</tr>
</table>
</div>
</td></tr>
</table>
</form>
<script language="JavaScript">
<!--
function enable_blockoffenders() {
	var endis = !(document.iform.blockoffenders7.checked);
	document.iform.blockoffenderskill.disabled=endis;
	document.iform.blockoffendersip.disabled=endis;
	document.iform.whitelistname.disabled=endis;
	document.iform.btnWhitelist.disabled=endis;
}

function toggle_system_log() {
	var endis = !(document.iform.alertsystemlog.checked);
	if (endis)
		document.getElementById("alertsystemlog_rows").style.display="none";
	else
		document.getElementById("alertsystemlog_rows").style.display="";
}

function enable_change(enable_change) {
	endis = !(document.iform.enable.checked || enable_change);
	// make sure a default answer is called if this is invoked.
	endis2 = (document.iform.enable);
	document.iform.performance.disabled = endis;
	document.iform.blockoffenders7.disabled = endis;
	document.iform.blockoffendersip.disabled=endis;
	document.iform.blockoffenderskill.disabled=endis;
	document.iform.alertsystemlog.disabled = endis;
	document.iform.externallistname.disabled = endis;
	document.iform.cksumcheck.disabled = endis;
	document.iform.homelistname.disabled = endis;
	document.iform.whitelistname.disabled=endis;
	document.iform.suppresslistname.disabled = endis;
	document.iform.configpassthru.disabled = endis;
	document.iform.btnHomeNet.disabled=endis;
	document.iform.btnWhitelist.disabled=endis;
	document.iform.btnSuppressList.disabled=endis;
	document.iform.fpm_split_any_any.disabled=endis;
	document.iform.fpm_search_optimize.disabled=endis;
	document.iform.fpm_no_stream_inserts.disabled=endis;
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

function viewList(id, elemID, elemType) {
	if (typeof elemType == "undefined") {
		elemType = "passlist";
	}
	var url = "snort_list_view.php?id=" + id + "&wlist=";
	url = url + getSelectedValue(elemID) + "&type=" + elemType;
	url = url + "&time=" + new Date().getTime();
	wopen(url, 'PassListViewer', 640, 480);
}

enable_change(false);
enable_blockoffenders();
toggle_system_log();

//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
