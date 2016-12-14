<?php
/*
 * snort_generate_conf.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009-2010 Robert Zelaya
 * Copyright (c) 2013-2014 Bill Meeks
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

/**************************************************************************/
/* This code reads the stored Snort configuration and constructs a series */
/* of string variables that are used as values for placeholders in the    */
/* snort_conf_template.inc file.  These strings along with text in the    */
/* template are used to create the snort.conf file for the interface.     */
/**************************************************************************/

/* Custom home nets */
$home_net_list = snort_build_list($snortcfg, $snortcfg['homelistname']);
$home_net = implode(",", $home_net_list);
$home_net = trim($home_net);
$external_net = "";
if (!empty($snortcfg['externallistname']) && $snortcfg['externallistname'] != 'default') {
	$external_net_list = snort_build_list($snortcfg, $snortcfg['externallistname'], false, true);
	$external_net = implode(",", $external_net_list);
	$external_net = "[" . trim($external_net) . "]";
}
else {
	foreach ($home_net_list as $ip)
		$external_net .= "!{$ip},";
	$external_net = trim($external_net, ', ');
}

/* User added custom configuration arguments */
$snort_config_pass_thru = str_replace("\r", "", base64_decode($snortcfg['configpassthru']));
// Remove the trailing newline
$snort_config_pass_thru = rtrim($snort_config_pass_thru);

/* create a few directories and ensure the sample files are in place */
$snort_dirs = array( $snortdir, $snortcfgdir, "{$snortcfgdir}/rules",
	"{$snortlogdir}/snort_{$if_real}{$snort_uuid}",
	"{$snortlogdir}/snort_{$if_real}{$snort_uuid}/barnyard2", 
	"{$snortcfgdir}/preproc_rules", 
	"dynamicrules" => "{$snortlibdir}/snort_dynamicrules",
	"dynamicengine" => "{$snortlibdir}/snort_dynamicengine",
	"dynamicpreprocessor" => "{$snortcfgdir}/snort_dynamicpreprocessor"
);
foreach ($snort_dirs as $dir) {
	if (!is_dir($dir))
		safe_mkdir($dir);
}

/********************************************************************/
/* For fail-safe on an initial startup following installation, and  */
/* before a rules update has occurred, copy the default config      */
/* files to the interface directory.  If files already exist in     */
/* the interface directory, or they are newer, that means a rule    */
/* update has been done and we should leave the customized files    */
/* put in place by the rules update process.                        */
/********************************************************************/
$snort_files = array("gen-msg.map", "classification.config", "reference.config", "attribute_table.dtd", 
		"sid-msg.map", "unicode.map", "file_magic.conf", "threshold.conf", "preproc_rules/preprocessor.rules",
		"preproc_rules/decoder.rules", "preproc_rules/sensitive-data.rules"
	);
foreach ($snort_files as $file) {
	if (file_exists("{$snortdir}/{$file}")) {
		$ftime = filemtime("{$snortdir}/{$file}");
		if (!file_exists("{$snortcfgdir}/{$file}") || ($ftime > filemtime("{$snortcfgdir}/{$file}")))
			@copy("{$snortdir}/{$file}", "{$snortcfgdir}/{$file}");
	}
}

/* define alert log limit */
if (!empty($config['installedpackages']['snortglobal']['alert_log_limit_size']) && $config['installedpackages']['snortglobal']['alert_log_limit_size'] != "0")
	$alert_log_limit_size = $config['installedpackages']['snortglobal']['alert_log_limit_size'] . "K";
else
	$alert_log_limit_size = "";

/* define alertsystemlog */
$alertsystemlog_type = "";
if ($snortcfg['alertsystemlog'] == "on") {
	$alertsystemlog_type = "output alert_syslog: ";
	if (!empty($snortcfg['alertsystemlog_facility']))
		$alertsystemlog_type .= strtoupper($snortcfg['alertsystemlog_facility']) . " ";
	else
		$alertsystemlog_type .= "LOG_AUTH ";
	if (!empty($snortcfg['alertsystemlog_priority']))
		$alertsystemlog_type .= strtoupper($snortcfg['alertsystemlog_priority']) . " ";
	else
		$alertsystemlog_type .= "LOG_ALERT ";
}

/* define snortunifiedlog */
$snortunifiedlog_type = "";
if ($snortcfg['barnyard_enable'] == "on") {
	if (isset($snortcfg['unified2_log_limit']))
		$u2_log_limit = "limit {$snortcfg['unified2_log_limit']}";
	else
		$u2_log_limit = "limit 128K";

	$snortunifiedlog_type = "output unified2: filename snort_{$snort_uuid}_{$if_real}.u2, {$u2_log_limit}";
	if ($snortcfg['barnyard_log_vlan_events'] == 'on')
		$snortunifiedlog_type .= ", vlan_event_types";
	if ($snortcfg['barnyard_log_mpls_events'] == 'on')
		$snortunifiedlog_type .= ", mpls_event_types";

	// If AppID detector is enabled, add it to unified2 logging
	if ($snortcfg['appid_preproc'] == 'on' )
		$snortunifiedlog_type .= ", appid_event_types";
}

/* define spoink */
$spoink_type = "";
if ($snortcfg['blockoffenders7'] == "on") {
	$pfkill = "";
	if ($snortcfg['blockoffenderskill'] == "on")
		$pfkill = "kill";
	$spoink_wlist = snort_build_list($snortcfg, $snortcfg['whitelistname'], true);
	/* write Pass List */
	@file_put_contents("{$snortcfgdir}/{$snortcfg['whitelistname']}", implode("\n", $spoink_wlist));
	$spoink_type = "output alert_pf: {$snortcfgdir}/{$snortcfg['whitelistname']},snort2c,{$snortcfg['blockoffendersip']},{$pfkill}";
}

/* define selected suppress file */
$suppress_file_name = "";
$suppress = snort_find_list($snortcfg['suppresslistname'], 'suppress');
if (!empty($suppress)) {
	$suppress_data = str_replace("\r", "", base64_decode($suppress['suppresspassthru']));
	@file_put_contents("{$snortcfgdir}/supp{$snortcfg['suppresslistname']}", $suppress_data);
	$suppress_file_name = "include {$snortcfgdir}/supp{$snortcfg['suppresslistname']}";
}

/* set the snort performance model */
$snort_performance = "ac-bnfa";
if(!empty($snortcfg['performance']))
	$snort_performance = $snortcfg['performance'];

/* if user has defined a custom ssh port, use it */
if(is_array($config['system']['ssh']) && isset($config['system']['ssh']['port']))
	$ssh_port = $config['system']['ssh']['port'];
else
	$ssh_port = "22";

/* Define an array of default values for the various preprocessor ports */
$snort_ports = array(
	"dns_ports" => "53", "smtp_ports" => "25", "mail_ports" => "25,465,587,691",
	"http_ports" => "36,80,81,82,83,84,85,86,87,88,89,90,311,383,591,593,631,901,1220,1414,1533,1741,1830,2301,2381,2809,3037,3057,3128,3443,3702,4343,4848,5250,6080,6988,7000,7001,7144,7145,7510,7777,7779,8000,8008,8014,8028,8080,8081,8082,8085,8088,8090,8118,8123,8180,8181,8222,8243,8280,8300,8500,8800,8888,8899,9000,9060,9080,9090,9091,9443,9999,10000,11371,15489,29991,33300,34412,34443,34444,41080,44440,50000,50002,51423,55555,56712", 
	"oracle_ports" => "1024:", "mssql_ports" => "1433", "telnet_ports" => "23", 
	"snmp_ports" => "161", "ftp_ports" => "21,2100,3535", "ssh_ports" => $ssh_port, 
	"pop2_ports" => "109", "pop3_ports" => "110", "imap_ports" => "143", 
	"sip_ports" => "5060,5061,5600", "auth_ports" => "113", "finger_ports" => "79", 
	"irc_ports" => "6665,6666,6667,6668,6669,7000", "smb_ports" => "139,445",
	"nntp_ports" => "119", "rlogin_ports" => "513", "rsh_ports" => "514",
	"ssl_ports" => "443,465,563,636,989,992,993,994,995,7801,7802,7900,7901,7902,7903,7904,7905,7906,7907,7908,7909,7910,7911,7912,7913,7914,7915,7916,7917,7918,7919,7920",
	"file_data_ports" => "\$HTTP_PORTS,110,143", "shellcode_ports" => "!80", 
	"sun_rpc_ports" => "111,32770,32771,32772,32773,32774,32775,32776,32777,32778,32779",
	"DCERPC_NCACN_IP_TCP" => "139,445", "DCERPC_NCADG_IP_UDP" => "138,1024:",
	"DCERPC_NCACN_IP_LONG" => "135,139,445,593,1024:", "DCERPC_NCACN_UDP_LONG" => "135,1024:",
	"DCERPC_NCACN_UDP_SHORT" => "135,593,1024:", "DCERPC_NCACN_TCP" => "2103,2105,2107",
	"DCERPC_BRIGHTSTORE" => "6503,6504", "DNP3_PORTS" => "20000", "MODBUS_PORTS" => "502",
	"GTP_PORTS" => "2123,2152,3386"
);

/* Check for defined Aliases that may override default port settings as we build the portvars array */
$portvardef = "";
foreach ($snort_ports as $alias => $avalue) {
	if (!empty($snortcfg["def_{$alias}"]) && is_alias($snortcfg["def_{$alias}"]))
		$snort_ports[$alias] = trim(filter_expand_alias($snortcfg["def_{$alias}"]));
	$snort_ports[$alias] = preg_replace('/\s+/', ',', trim($snort_ports[$alias]));
	$portvardef .= "portvar " . strtoupper($alias) . " [" . $snort_ports[$alias] . "]\n";
}

/* Define the default ports for the Stream5 preprocessor (formatted for easier reading in the snort.conf file) */
$stream5_ports_client = "21 22 23 25 42 53 70 79 109 110 111 113 119 135 136 137 \\\n";
$stream5_ports_client .= "\t             139 143 161 445 513 514 587 593 691 1433 1521 1741 \\\n";
$stream5_ports_client .= "\t             2100 3306 6070 6665 6666 6667 6668 6669 7000 8181 \\\n";
$stream5_ports_client .= "\t             32770 32771 32772 32773 32774 32775 32776 32777 \\\n";
$stream5_ports_client .= "\t             32778 32779";
$stream5_ports_both = "80 81 82 83 84 85 86 87 88 89 90 110 311 383 443 465 563 \\\n";
$stream5_ports_both .= "\t           591 593 631 636 901 989 992 993 994 995 1220 1414 1533 \\\n";
$stream5_ports_both .= "\t           1830 2301 2381 2809 3037 3057 3128 3443 3702 4343 4848 \\\n";
$stream5_ports_both .= "\t           5250 6080 6988 7907 7000 7001 7144 7145 7510 7802 7777 \\\n";
$stream5_ports_both .= "\t           7779 7801 7900 7901 7902 7903 7904 7905 7906 7908 7909 \\\n";
$stream5_ports_both .= "\t           7910 7911 7912 7913 7914 7915 7916 7917 7918 7919 7920 \\\n";
$stream5_ports_both .= "\t           8000 8008 8014 8028 8080 8081 8082 8085 8088 8090 8118 \\\n";
$stream5_ports_both .= "\t           8123 8180 8222 8243 8280 8300 8500 8800 8888 8899 9000 \\\n";
$stream5_ports_both .= "\t           9060 9080 9090 9091 9443 9999 10000 11371 15489 29991 \\\n";
$stream5_ports_both .= "\t           33300 34412 34443 34444 41080 44440 50000 50002 51423 \\\n";
$stream5_ports_both .= "\t           55555 56712";

/*********************/
/* preprocessor code */
/*********************/

/* def perform_stat */

if (!empty($config['installedpackages']['snortglobal']['stats_log_limit_size']) && $config['installedpackages']['snortglobal']['stats_log_limit_size'] != "0")
	$stats_log_limit = "max_file_size " . $config['installedpackages']['snortglobal']['stats_log_limit_size'] * 1000;
else
	$stats_log_limit = "";
$perform_stat = <<<EOD
# Performance Statistics #
preprocessor perfmonitor: time 300 file {$snortlogdir}/snort_{$if_real}{$snort_uuid}/{$if_real}.stats pktcnt 10000 {$stats_log_limit}
	
EOD;

/* def ftp_preprocessor */

$telnet_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['telnet_ports']));
$ftp_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['ftp_ports']));

// Make sure we have port numbers or else use defaults
if (!isset($telnet_ports) || empty($telnet_ports))
	$telnet_ports = "23";
if (!isset($ftp_ports) || empty($ftp_ports))
	$ftp_ports = "21 2100 3535";

// Configure FTP_Telnet global options
$ftp_telnet_globals = "inspection_type ";
if ($snortcfg['ftp_telnet_inspection_type'] != "") { $ftp_telnet_globals .= $snortcfg['ftp_telnet_inspection_type']; }else{ $ftp_telnet_globals .= "stateful"; }
if ($snortcfg['ftp_telnet_alert_encrypted'] == "on")
	$ftp_telnet_globals .= " \\\n\tencrypted_traffic yes";
else
	$ftp_telnet_globals .= " \\\n\tencrypted_traffic no";
if ($snortcfg['ftp_telnet_check_encrypted'] == "on")
	$ftp_telnet_globals .= " \\\n\tcheck_encrypted";

// Configure FTP_Telnet Telnet protocol options
$ftp_telnet_protocol = "ports { {$telnet_ports} }";
if ($snortcfg['ftp_telnet_normalize'] == "on")
	$ftp_telnet_protocol .= " \\\n\tnormalize";
if ($snortcfg['ftp_telnet_detect_anomalies'] == "on")
	$ftp_telnet_protocol .= " \\\n\tdetect_anomalies";
if ($snortcfg['ftp_telnet_ayt_attack_threshold'] <> '0') {
	$ftp_telnet_protocol .= " \\\n\tayt_attack_thresh "; 
	if ($snortcfg['ftp_telnet_ayt_attack_threshold'] != "")
		$ftp_telnet_protocol .= $snortcfg['ftp_telnet_ayt_attack_threshold'];
	else
		$ftp_telnet_protocol .= "20";
}

// Setup the standard FTP commands used for all FTP Server engines
$ftp_cmds = <<<EOD
	ftp_cmds { ABOR ACCT ADAT ALLO APPE AUTH CCC CDUP } \
	ftp_cmds { CEL CLNT CMD CONF CWD DELE ENC EPRT } \
	ftp_cmds { EPSV ESTA ESTP FEAT HELP LANG LIST LPRT } \
	ftp_cmds { LPSV MACB MAIL MDTM MFMT MIC MKD MLSD MLST } \
	ftp_cmds { MODE NLST NOOP OPTS PASS PASV PBSZ PORT } \
	ftp_cmds { PROT PWD QUIT REIN REST RETR RMD RNFR } \
	ftp_cmds { RNTO SDUP SITE SIZE SMNT STAT STOR STOU } \
	ftp_cmds { STRU SYST TEST TYPE USER XCUP XCRC XCWD } \
	ftp_cmds { XMAS XMD5 XMKD XPWD XRCP XRMD XRSQ XSEM } \
	ftp_cmds { XSEN XSHA1 XSHA256 } \
	alt_max_param_len 0 { ABOR CCC CDUP ESTA FEAT LPSV NOOP PASV PWD QUIT REIN STOU SYST XCUP XPWD } \
	alt_max_param_len 200 { ALLO APPE CMD HELP NLST RETR RNFR STOR STOU XMKD } \
	alt_max_param_len 256 { CWD RNTO } \
	alt_max_param_len 400 { PORT } \
	alt_max_param_len 512 { MFMT SIZE } \
	chk_str_fmt { ACCT ADAT ALLO APPE AUTH CEL CLNT CMD } \
	chk_str_fmt { CONF CWD DELE ENC EPRT EPSV ESTP HELP } \
	chk_str_fmt { LANG LIST LPRT MACB MAIL MDTM MIC MKD } \
	chk_str_fmt { MLSD MLST MODE NLST OPTS PASS PBSZ PORT } \
	chk_str_fmt { PROT REST RETR RMD RNFR RNTO SDUP SITE } \
	chk_str_fmt { SIZE SMNT STAT STOR STRU TEST TYPE USER } \
	chk_str_fmt { XCRC XCWD XMAS XMD5 XMKD XRCP XRMD XRSQ } \ 
	chk_str_fmt { XSEM XSEN XSHA1 XSHA256 } \
	cmd_validity ALLO < int [ char R int ] > \    
	cmd_validity EPSV < [ { char 12 | char A char L char L } ] > \
	cmd_validity MACB < string > \
	cmd_validity MDTM < [ date nnnnnnnnnnnnnn[.n[n[n]]] ] string > \
	cmd_validity MODE < char ASBCZ > \
	cmd_validity PORT < host_port > \
	cmd_validity PROT < char CSEP > \
	cmd_validity STRU < char FRPO [ string ] > \    
	cmd_validity TYPE < { char AE [ char NTC ] | char I | char L [ number ] } >

EOD;

// Configure all the FTP_Telnet FTP protocol options
// Iterate and configure the FTP Client engines
$ftp_default_client_engine = array( "name" => "default", "bind_to" => "all", "max_resp_len" => 256, 
				    "telnet_cmds" => "no", "ignore_telnet_erase_cmds" => "yes", 
				    "bounce" => "yes", "bounce_to_net" => "", "bounce_to_port" => "" );

if (!is_array($snortcfg['ftp_client_engine']['item']))
	$snortcfg['ftp_client_engine']['item'] = array();

// If no FTP client engine is configured, use the default
// to keep from breaking Snort.
if (empty($snortcfg['ftp_client_engine']['item']))
		$snortcfg['ftp_client_engine']['item'][] = $ftp_default_client_engine;
$ftp_client_engine = "";

foreach ($snortcfg['ftp_client_engine']['item'] as $f => $v) {
	$buffer = "preprocessor ftp_telnet_protocol: ftp client ";
	if ($v['name'] == "default" && $v['bind_to'] == "all")
		$buffer .= "default \\\n";
	elseif (is_alias($v['bind_to'])) {
		$tmp = trim(filter_expand_alias($v['bind_to']));
		if (!empty($tmp)) {
			$tmp = preg_replace('/\s+/', ' ', $tmp);
			$buffer .= "{$tmp} \\\n";
		}
		else {
			log_error("[snort] ERROR: unable to resolve IP Address Alias '{$v['bind_to']}' for FTP client '{$v['name']}' ... skipping entry.");
			continue;
		}
	}
	else {
			log_error("[snort] ERROR: unable to resolve IP Address Alias '{$v['bind_to']}' for FTP client '{$v['name']}' ... skipping entry.");
			continue;
	}

	if ($v['max_resp_len'] == "")
		$buffer .= "\tmax_resp_len 256 \\\n";
	else
		$buffer .= "\tmax_resp_len {$v['max_resp_len']} \\\n";

	$buffer .= "\ttelnet_cmds {$v['telnet_cmds']} \\\n";
	$buffer .= "\tignore_telnet_erase_cmds {$v['ignore_telnet_erase_cmds']} \\\n";

	if ($v['bounce'] == "yes") {
		if (is_alias($v['bounce_to_net']) && is_alias($v['bounce_to_port'])) {
			$net = trim(filter_expand_alias($v['bounce_to_net']));
			$port = trim(filter_expand_alias($v['bounce_to_port']));
			if (!empty($net) && !empty($port) && 
			     snort_is_single_addr_alias($v['bounce_to_net']) && 
			    (is_port($port) || is_portrange($port))) {
				$port = preg_replace('/\s+/', ',', $port);
				// Change port range delimiter to comma for ftp_telnet client preprocessor
				if (is_portrange($port))
					$port = str_replace(":", ",", $port);
				$buffer .= "\tbounce yes \\\n";
				$buffer .= "\tbounce_to { {$net},{$port} }\n";
			}
			else {
				// One or both of the BOUNCE_TO alias values is not right,
				// so figure out which and log an appropriate error.
				if (empty($net) || !snort_is_single_addr_alias($v['bounce_to_net']))
					log_error("[snort] ERROR: illegal value for bounce_to Address Alias [{$v['bounce_to_net']}] for FTP client engine [{$v['name']}] ... omitting 'bounce_to' option for this client engine.");
				if (empty($port) || !(is_port($port) || is_portrange($port)))
					log_error("[snort] ERROR: illegal value for bounce_to Port Alias [{$v['bounce_to_port']}] for FTP client engine [{$v['name']}] ... omitting 'bounce_to' option for this client engine.");
				$buffer .= "\tbounce yes\n";
			}
		}
		else
			$buffer .= "\tbounce yes\n";
	}
	else
		$buffer .= "\tbounce no\n";

	// Add this FTP client engine to the master string
	$ftp_client_engine .= "{$buffer}\n";
}
// Trim final trailing newline
rtrim($ftp_client_engine);	

// Iterate and configure the FTP Server engines
$ftp_default_server_engine = array( "name" => "default", "bind_to" => "all", "ports" => "default", 
				    "telnet_cmds" => "no", "ignore_telnet_erase_cmds" => "yes", 
				    "ignore_data_chan" => "no", "def_max_param_len" => 100 );

if (!is_array($snortcfg['ftp_server_engine']['item']))
	$snortcfg['ftp_server_engine']['item'] = array();

// If no FTP server engine is configured, use the default
// to keep from breaking Snort.
if (empty($snortcfg['ftp_server_engine']['item']))
		$snortcfg['ftp_server_engine']['item'][] = $ftp_default_server_engine;
$ftp_server_engine = "";

foreach ($snortcfg['ftp_server_engine']['item'] as $f => $v) {
	$buffer = "preprocessor ftp_telnet_protocol: ftp server ";
	if ($v['name'] == "default" && $v['bind_to'] == "all")
		$buffer .= "default \\\n";
	elseif (is_alias($v['bind_to'])) {
		$tmp = trim(filter_expand_alias($v['bind_to']));
		if (!empty($tmp)) {
			$tmp = preg_replace('/\s+/', ' ', $tmp);
			$buffer .= "{$tmp} \\\n";
		}
		else {
			log_error("[snort] ERROR: unable to resolve IP Address Alias '{$v['bind_to']}' for FTP server '{$v['name']}' ... skipping entry.");
			continue;
		}
	}
	else {
			log_error("[snort] ERROR: unable to resolve IP Address Alias '{$v['bind_to']}' for FTP server '{$v['name']}' ... skipping entry.");
			continue;
	}

	if ($v['def_max_param_len'] == "")
		$buffer .= "\tdef_max_param_len 100 \\\n";
	elseif ($v['def_max_param_len'] <> '0')
		$buffer .= "\tdef_max_param_len {$v['def_max_param_len']} \\\n";

	if ($v['ports'] == "default" || !is_alias($v['ports']) || empty($v['ports']))
		$buffer .= "\tports { {$ftp_ports} } \\\n";
	elseif (is_alias($v['ports'])) {
		$tmp = trim(filter_expand_alias($v['ports']));
		if (!empty($tmp)) {
			$tmp = preg_replace('/\s+/', ' ', $tmp);
			$tmp = snort_expand_port_range($tmp, ' ');
			$buffer .= "\tports { {$tmp} } \\\n";
		}
		else {
			log_error("[snort] ERROR: unable to resolve Port Alias '{$v['ports']}' for FTP server '{$v['name']}' ... reverting to defaults.");
			$buffer .= "\tports { {$ftp_ports} } \\\n";
		}
	}

	$buffer .= "\ttelnet_cmds {$v['telnet_cmds']} \\\n";
	$buffer .= "\tignore_telnet_erase_cmds {$v['ignore_telnet_erase_cmds']} \\\n";
	if ($v['ignore_data_chan'] == "yes")
		$buffer .= "\tignore_data_chan yes \\\n";
	$buffer .= "{$ftp_cmds}\n";

	// Add this FTP server engine to the master string
	$ftp_server_engine .= $buffer;
}
// Remove trailing newlines
rtrim($ftp_server_engine);

	$ftp_preprocessor = <<<EOD
# ftp_telnet preprocessor #
preprocessor ftp_telnet: global \
	{$ftp_telnet_globals}

preprocessor ftp_telnet_protocol: telnet \
	{$ftp_telnet_protocol}

{$ftp_server_engine}
{$ftp_client_engine}
EOD;

/* def pop_preprocessor */

$pop_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['pop3_ports']));

// Make sure we have port numbers or else use defaults
if (!isset($pop_ports) || empty($pop_ports))
	$pop_ports = "110";

if (isset($snortcfg['pop_memcap']))
	$pop_memcap = $snortcfg['pop_memcap'];
else
	$pop_memcap = "838860";
if (isset($snortcfg['pop_qp_decode_depth']))
	$pop_qp_decode_depth = $snortcfg['pop_qp_decode_depth'];
else
	$pop_qp_decode_depth = "0";
if (isset($snortcfg['pop_b64_decode_depth']))
	$pop_b64_decode_depth = $snortcfg['pop_b64_decode_depth'];
else
	$pop_b64_decode_depth = "0";
if (isset($snortcfg['pop_bitenc_decode_depth']))
	$pop_bitenc_decode_depth = $snortcfg['pop_bitenc_decode_depth'];
else
	$pop_bitenc_decode_depth = "0";
if (isset($snortcfg['pop_uu_decode_depth']))
	$pop_uu_decode_depth = $snortcfg['pop_uu_decode_depth'];
else
	$pop_uu_decode_depth = "0";
$pop_preproc = <<<EOD
# POP preprocessor #
preprocessor pop: \
	ports { {$pop_ports} } \
	memcap {$pop_memcap} \
	qp_decode_depth {$pop_qp_decode_depth} \
	b64_decode_depth {$pop_b64_decode_depth} \
	bitenc_decode_depth {$pop_bitenc_decode_depth} \
	uu_decode_depth {$pop_uu_decode_depth}

EOD;

/* def imap_preprocessor */

$imap_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['imap_ports']));

// Make sure we have port numbers or else use defaults
if (!isset($imap_ports) || empty($imap_ports))
	$imap_ports = "143";

if (isset($snortcfg['imap_memcap']))
	$imap_memcap = $snortcfg['imap_memcap'];
else
	$imap_memcap = "838860";
if (isset($snortcfg['imap_qp_decode_depth']))
	$imap_qp_decode_depth = $snortcfg['imap_qp_decode_depth'];
else
	$imap_qp_decode_depth = "0";
if (isset($snortcfg['imap_b64_decode_depth']))
	$imap_b64_decode_depth = $snortcfg['imap_b64_decode_depth'];
else
	$imap_b64_decode_depth = "0";
if (isset($snortcfg['imap_bitenc_decode_depth']))
	$imap_bitenc_decode_depth = $snortcfg['imap_bitenc_decode_depth'];
else
	$imap_bitenc_decode_depth = "0";
if (isset($snortcfg['imap_uu_decode_depth']))
	$imap_uu_decode_depth = $snortcfg['imap_uu_decode_depth'];
else
	$imap_uu_decode_depth = "0";
$imap_preproc = <<<EOD
# IMAP preprocessor #
preprocessor imap: \
	ports { {$imap_ports} } \
	memcap {$imap_memcap} \
	qp_decode_depth {$imap_qp_decode_depth} \
	b64_decode_depth {$imap_b64_decode_depth} \
	bitenc_decode_depth {$imap_bitenc_decode_depth} \
	uu_decode_depth {$imap_uu_decode_depth}

EOD;

/* def smtp_preprocessor */

$smtp_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['mail_ports']));

// Make sure we have port numbers or else use defaults
if (!isset($smtp_ports) || empty($smtp_ports))
	$smtp_ports = "25 465 587 691";

if (isset($snortcfg['smtp_memcap']))
	$smtp_memcap = $snortcfg['smtp_memcap'];
else
	$smtp_memcap = "838860";
if (isset($snortcfg['smtp_max_mime_mem']))
	$smtp_max_mime_mem = $snortcfg['smtp_max_mime_mem'];
else
	$smtp_max_mime_mem = "838860";
if (isset($snortcfg['smtp_qp_decode_depth']))
	$smtp_qp_decode_depth = $snortcfg['smtp_qp_decode_depth'];
else
	$smtp_qp_decode_depth = "0";
if (isset($snortcfg['smtp_b64_decode_depth']))
	$smtp_b64_decode_depth = $snortcfg['smtp_b64_decode_depth'];
else
	$smtp_b64_decode_depth = "0";
if (isset($snortcfg['smtp_bitenc_decode_depth']))
	$smtp_bitenc_decode_depth = $snortcfg['smtp_bitenc_decode_depth'];
else
	$smtp_bitenc_decode_depth = "0";
if (isset($snortcfg['smtp_uu_decode_depth']))
	$smtp_uu_decode_depth = $snortcfg['smtp_uu_decode_depth'];
else
	$smtp_uu_decode_depth = "0";
if (isset($snortcfg['smtp_email_hdrs_log_depth']) && $snortcfg['smtp_email_hdrs_log_depth'] != '0')
	$smtp_email_hdrs_log_depth = $snortcfg['smtp_email_hdrs_log_depth'];
else
	$smtp_email_hdrs_log_depth = "0";
$smtp_boolean_params = "";
if ($snortcfg['smtp_ignore_data'] == 'on')
	$smtp_boolean_params .= "\tignore_data \\\n";
if ($snortcfg['smtp_ignore_tls_data'] == 'on')
	$smtp_boolean_params .= "\tignore_tls_data \\\n";
if ($snortcfg['smtp_log_mail_from'] == 'on')
	$smtp_boolean_params .= "\tlog_mailfrom \\\n";
if ($snortcfg['smtp_log_rcpt_to'] == 'on')
	$smtp_boolean_params .= "\tlog_rcptto \\\n";
if ($snortcfg['smtp_log_filename'] == 'on')
	$smtp_boolean_params .= "\tlog_filename \\\n";
if ($snortcfg['smtp_log_email_hdrs'] == 'on')
	$smtp_boolean_params .= "\tlog_email_hdrs\\\n";
$smtp_boolean_params = trim($smtp_boolean_params, "\t\n\\");
$smtp_preprocessor = <<<EOD
# SMTP preprocessor #
preprocessor SMTP: \
	ports { {$smtp_ports} } \
	inspection_type stateful \
	normalize cmds \
	memcap {$smtp_memcap} \
	max_mime_mem {$smtp_max_mime_mem} \
	valid_cmds { MAIL RCPT HELP HELO ETRN EHLO EXPN VRFY ATRN SIZE BDAT DEBUG EMAL ESAM ESND ESOM EVFY IDENT \
		     NOOP RSET SEND SAML SOML AUTH TURN ETRN PIPELINING CHUNKING DATA DSN RSET QUIT ONEX QUEU \
		     STARTTLS TICK TIME TURNME VERB X-EXPS X-LINK2STATE XADR XAUTH XCIR XEXCH50 XGEN XLICENSE \
		     XQUEU XSTA XTRN XUSR } \
	normalize_cmds { MAIL RCPT HELP HELO ETRN EHLO EXPN VRFY ATRN SIZE BDAT DEBUG EMAL ESAM ESND ESOM EVFY \
			 IDENT NOOP RSET SEND SAML SOML AUTH TURN ETRN PIPELINING CHUNKING DATA DSN RSET QUIT \
			 ONEX QUEU STARTTLS TICK TIME TURNME VERB X-EXPS X-LINK2STATE XADR XAUTH XCIR XEXCH50 \
			 XGEN XLICENSE XQUEU XSTA XTRN XUSR } \
	max_header_line_len 1000 \ 
	max_response_line_len 512 \
	alt_max_command_line_len 260 { MAIL } \
	alt_max_command_line_len 300 { RCPT } \
	alt_max_command_line_len 500 { HELP HELO ETRN EHLO } \
	alt_max_command_line_len 255 { EXPN VRFY ATRN SIZE BDAT DEBUG EMAL ESAM ESND ESOM EVFY IDENT NOOP RSET } \
	alt_max_command_line_len 246 { SEND SAML SOML AUTH TURN ETRN PIPELINING CHUNKING DATA DSN RSET QUIT ONEX } \
	alt_max_command_line_len 246 { QUEU STARTTLS TICK TIME TURNME VERB X-EXPS X-LINK2STATE XADR } \
	alt_max_command_line_len 246 { XAUTH XCIR XEXCH50 XGEN XLICENSE XQUEU XSTA XTRN XUSR } \
	xlink2state { enable } \
	{$smtp_boolean_params} \
	email_hdrs_log_depth {$smtp_email_hdrs_log_depth} \
	qp_decode_depth {$smtp_qp_decode_depth} \
	b64_decode_depth {$smtp_b64_decode_depth} \
	bitenc_decode_depth {$smtp_bitenc_decode_depth} \
	uu_decode_depth {$smtp_uu_decode_depth}

EOD;

/* def sf_portscan */

$sf_pscan_protocol = "all";
if (!empty($snortcfg['pscan_protocol']))
	$sf_pscan_protocol = $snortcfg['pscan_protocol'];
$sf_pscan_type = "all";
if (!empty($snortcfg['pscan_type']))
	$sf_pscan_type = $snortcfg['pscan_type'];
$sf_pscan_memcap = "10000000";
if (!empty($snortcfg['pscan_memcap']))
	$sf_pscan_memcap = $snortcfg['pscan_memcap'];
$sf_pscan_sense_level = "medium";
if (!empty($snortcfg['pscan_sense_level']))
	$sf_pscan_sense_level = $snortcfg['pscan_sense_level'];
$sf_pscan_ignore_scanners = "\$HOME_NET";
if (!empty($snortcfg['pscan_ignore_scanners'])) {
	if (is_alias($snortcfg['pscan_ignore_scanners'])) {
		$sf_pscan_ignore_scanners = trim(filter_expand_alias($snortcfg['pscan_ignore_scanners']));
		$sf_pscan_ignore_scanners = preg_replace('/\s+/', ',', trim($sf_pscan_ignore_scanners));
	} else {
        	$sf_pscan_ignore_scanners = $snortcfg['pscan_ignore_scanners'];
        }
}
$sf_pscan_ignore_scanned = "";
if (!empty($snortcfg['pscan_ignore_scanned'])) {
	if (is_alias($snortcfg['pscan_ignore_scanned'])) {
		$sf_pscan_ignore_scanned = trim(filter_expand_alias($snortcfg['pscan_ignore_scanned']));
		$sf_pscan_ignore_scanned = preg_replace('/\s+/', ',', trim($sf_pscan_ignore_scanned));
	} else {
          	$sf_pscan_ignore_scanned = $snortcfg['pscan_ignore_scanned'];
        }
}

$sf_portscan = <<<EOD
# sf Portscan #
preprocessor sfportscan: \
	scan_type { {$sf_pscan_type} } \
	proto  { {$sf_pscan_protocol} } \
	memcap { {$sf_pscan_memcap} } \
	sense_level { {$sf_pscan_sense_level} } \
	ignore_scanners { {$sf_pscan_ignore_scanners} }
EOD;


if (!empty($sf_pscan_ignore_scanned)) {
	$sf_portscan .= <<<EOD
 \
	ignore_scanned { {$sf_pscan_ignore_scanned} }

EOD;
} else {
	$sf_portscan .= <<<EOD


EOD;
}

/* def ssh_preproc */

$ssh_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['ssh_ports']));

// Make sure we have port numbers or else use defaults
if (!isset($ssh_ports) || empty($ssh_ports))
	$ssh_ports = "22";
$ssh_preproc = <<<EOD
# SSH preprocessor #
preprocessor ssh: \
	server_ports { {$ssh_ports} } \
	autodetect \
	max_client_bytes 19600 \
	max_encrypted_packets 20 \
	max_server_version_len 100 \
	enable_respoverflow enable_ssh1crc32 \
	enable_srvoverflow enable_protomismatch

EOD;

/* def other_preprocs */

$sun_rpc_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['sun_rpc_ports']));

// Make sure we have port numbers or else use defaults
if (!isset($sun_rpc_ports) || empty($sun_rpc_ports))
	$sun_rpc_ports = "111 32770 32771 32772 32773 32774 32775 32776 32777 32778 32779";
$other_preprocs = <<<EOD
# Other preprocs #
preprocessor rpc_decode: \
	{$sun_rpc_ports} \
	no_alert_multiple_requests \
	no_alert_large_fragments \
	no_alert_incomplete

# Back Orifice preprocessor #
preprocessor bo
	
EOD;

/* def dce_rpc_2 */

$dce_rpc_2 = <<<EOD
# DCE/RPC 2 #
preprocessor dcerpc2: \
	memcap 102400, \
	events [co]

preprocessor dcerpc2_server: default, \
	policy WinXP, \
	detect [smb [{$snort_ports['smb_ports']}], \
	tcp 135, \
	udp 135, \
	rpc-over-http-server 593], \
	autodetect [tcp 1025:, \
	udp 1025:, \
	rpc-over-http-server 1025:], \
	smb_max_chain 3, smb_invalid_shares ["C$", "D$", "ADMIN$"]
	
EOD;


/* def sip_preprocessor */

$sip_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['sip_ports']));

// Make sure we have port numbers or else use defaults
if (!isset($sip_ports) || empty($sip_ports))
	$sip_ports = "5060 5061 5600";
$sip_preproc = <<<EOD
# SIP preprocessor #
preprocessor sip: \
	max_sessions 40000, \
	ports { {$sip_ports} }, \
	methods { invite \
		  cancel \
		  ack \
		  bye \
		  register \
		  options \
		  refer \
		  subscribe \
		  update \
		  join \
		  info \
		  message \
		  notify \
		  benotify \
		  do \
		  qauth \
		  sprack \
		  publish \
		  service \
		  unsubscribe \
		  prack }, \
	max_call_id_len 80, \
	max_from_len 256, \
	max_to_len 256, \
	max_via_len 1024, \
	max_requestName_len 50, \
	max_uri_len 512, \
	ignore_call_channel, \
	max_content_len 2048, \
	max_contact_len 512

EOD;

/* def dns_preprocessor */

$dns_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['dns_ports']));

// Make sure we have port numbers or else use defaults
if (!isset($dns_ports) || empty($dns_ports))
	$dns_ports = "53";
$dns_preprocessor = <<<EOD
# DNS preprocessor #
preprocessor dns: \
	ports { {$dns_ports} } \
	enable_rdata_overflow
	
EOD;

/* def dnp3_preprocessor */

$dnp3_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['DNP3_PORTS']));

// Make sure we have port numbers or else use defaults
if (!isset($dnp3_ports) || empty($dnp3_ports))
	$dnp3_ports = "20000";
$dnp3_preproc = <<<EOD
# DNP3 preprocessor #
preprocessor dnp3: \
	ports { {$dnp3_ports} } \
	memcap 262144 \
	check_crc
	
EOD;

/* def modbus_preprocessor */

$modbus_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['MODBUS_PORTS']));

// Make sure we have port numbers or else use defaults
if (!isset($modbus_ports) || empty($modbus_ports))
	$modbus_ports = "502";
$modbus_preproc = <<<EOD
# Modbus preprocessor #
preprocessor modbus: \
	ports { {$modbus_ports} }
	
EOD;

/* def gtp_preprocessor */

$gtp_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['GTP_PORTS']));

// Make sure we have port numbers or else use defaults
if (!isset($gtp_ports) || empty($gtp_ports))
	$gtp_ports = "2123 3386 2152";
$gtp_preproc = <<<EOD
# GTP preprocessor #
preprocessor gtp: \
	ports { {$gtp_ports} }
	
EOD;

/* def ssl_preprocessor */

$ssl_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['ssl_ports']));

// Make sure we have port numbers or else use defaults
if (!isset($ssl_ports) || empty($ssl_ports))
	$ssl_ports = "443 465 563 636 989 992 993 994 995 7801 7802 7900 7901 7902 7903 7904 7905 7906 7907 7908 7909 7910 7911 7912 7913 7914 7915 7916 7917 7918 7919 7920";
$ssl_preproc = <<<EOD
# SSL preprocessor #
preprocessor ssl: \
	ports { {$ssl_ports} }, \
	trustservers, \
	noinspect_encrypted 

EOD;

/* def sensitive_data_preprocessor */

if ($snortcfg['sdf_mask_output'] == "on")
	$sdf_mask_output = "\\\n\tmask_output";
else
	$sdf_mask_output = "";
if (empty($snortcfg['sdf_alert_threshold']))
	$snortcfg['sdf_alert_threshold'] = 25;
$sensitive_data = <<<EOD
# SDF preprocessor #
preprocessor sensitive_data: \
	alert_threshold {$snortcfg['sdf_alert_threshold']} {$sdf_mask_output}

EOD;

/* define IP Reputation preprocessor */

if (is_array($snortcfg['blist_files']['item'])) {
	$blist_files = "";
	$bIsFirst = TRUE;
	foreach ($snortcfg['blist_files']['item'] as $blist) {
		if ($bIsFirst) {
			$blist_files .= "blacklist " . SNORT_IPREP_PATH . $blist;
			$bIsFirst = FALSE;
		}
		else
			$blist_files .= ", \\ \n\tblacklist " . SNORT_IPREP_PATH . $blist;    
	}
}
if (is_array($snortcfg['wlist_files']['item'])) {
	$wlist_files = "";
	$bIsFirst = TRUE;
	foreach ($snortcfg['wlist_files']['item'] as $wlist) {
		if ($bIsFirst) {
			$wlist_files .= "whitelist " . SNORT_IPREP_PATH . $wlist;
			$bIsFirst = FALSE;
		}
		else
			$wlist_files .= ", \\ \n\twhitelist " . SNORT_IPREP_PATH . $wlist;    
	}
}
if (!empty($blist_files))
	$ip_lists = ", \\ \n\t" . $blist_files;
if (!empty($wlist_files))
	$ip_lists .= ", \\ \n\t" . $wlist_files;
if ($snortcfg['iprep_scan_local'] == 'on')
	$ip_lists .= ", \\ \n\tscan_local";	

$reputation_preproc = <<<EOD
# IP Reputation preprocessor #
preprocessor reputation: \
	memcap {$snortcfg['iprep_memcap']}, \
	priority {$snortcfg['iprep_priority']}, \
	nested_ip {$snortcfg['iprep_nested_ip']}, \
	white {$snortcfg['iprep_white']}{$ip_lists}

EOD;

/* def AppID preprocessor */
$appid_memcap = $snortcfg['sf_appid_mem_cap'] * 1024 * 1024;
$appid_params = "app_detector_dir " . rtrim(SNORT_APPID_ODP_PATH, '/') . ", \\\n\tmemcap {$appid_memcap}";
if ($snortcfg['sf_appid_statslog'] == "on") {
	if (!file_exists("{$snortlogdir}/snort_{$if_real}{$snort_uuid}/app-stats.log")) {
		touch("{$snortlogdir}/snort_{$if_real}{$snort_uuid}/app-stats.log");
	}
	$appid_params .= ", \\\n\tapp_stats_filename app-stats.log";
	$appid_params .= ", \\\n\tapp_stats_period {$snortcfg['sf_appid_stats_period']}";
	$appid_params .= ", \\\n\tapp_stats_rollover_size " . strval($config['installedpackages']['snortglobal']['appid_stats_log_limit_size'] * 1024);
	$appid_params .= ", \\\n\tapp_stats_rollover_time 86400";
}

$appid_preproc = <<<EOD
# AppID preprocessor #
preprocessor appid: \
	{$appid_params}

EOD;

/***************************************/
/* end of preprocessor string var code */
/***************************************/

/* define servers as IP variables */
$snort_servers = array (
	"dns_servers" => "\$HOME_NET", "smtp_servers" => "\$HOME_NET", "http_servers" => "\$HOME_NET",
	"www_servers" => "\$HOME_NET", "sql_servers" => "\$HOME_NET", "telnet_servers" => "\$HOME_NET",
	"snmp_servers" => "\$HOME_NET", "ftp_servers" => "\$HOME_NET", "ssh_servers" => "\$HOME_NET",
	"pop_servers" => "\$HOME_NET", "imap_servers" => "\$HOME_NET", "sip_proxy_ip" => "\$HOME_NET",
	"sip_servers" => "\$HOME_NET", "rpc_servers" => "\$HOME_NET", "dnp3_server" => "\$HOME_NET",
	"dnp3_client" => "\$HOME_NET", "modbus_server" => "\$HOME_NET", "modbus_client" => "\$HOME_NET",
	"enip_server" => "\$HOME_NET", "enip_client" => "\$HOME_NET",
	"aim_servers" => "64.12.24.0/23,64.12.28.0/23,64.12.161.0/24,64.12.163.0/24,64.12.200.0/24,205.188.3.0/24,205.188.5.0/24,205.188.7.0/24,205.188.9.0/24,205.188.153.0/24,205.188.179.0/24,205.188.248.0/24"
    );

// Change old name from "var" to new name of "ipvar" for IP variables because 
// Snort is deprecating the old "var" name in newer versions.
$ipvardef = "";
foreach ($snort_servers as $alias => $avalue) {
	if (!empty($snortcfg["def_{$alias}"]) && is_alias($snortcfg["def_{$alias}"])) {
		$avalue = trim(filter_expand_alias($snortcfg["def_{$alias}"]));
		$avalue = preg_replace('/\s+/', ',', trim($avalue));
	}
	$ipvardef .= "ipvar " . strtoupper($alias) . " [{$avalue}]\n";
}

$snort_preproc_libs = array(
	"dce_rpc_2" => "dce2_preproc", "dns_preprocessor" => "dns_preproc", "ftp_preprocessor" => "ftptelnet_preproc", "imap_preproc" => "imap_preproc",
	"pop_preproc" => "pop_preproc", "reputation_preproc" => "reputation_preproc", "sensitive_data" => "sdf_preproc", 
	"sip_preproc" => "sip_preproc", "gtp_preproc" => "gtp_preproc", "smtp_preprocessor" => "smtp_preproc", "ssh_preproc" => "ssh_preproc", 
	"ssl_preproc" => "ssl_preproc", "dnp3_preproc" => "dnp3_preproc", "modbus_preproc" => "modbus_preproc", "appid_preproc" => "appid_preproc"
);
$snort_preproc = array (
	"perform_stat", "other_preprocs", "ftp_preprocessor", "smtp_preprocessor", "ssl_preproc", "sip_preproc", "gtp_preproc", "ssh_preproc", "sf_portscan", 
	"dce_rpc_2", "dns_preprocessor", "sensitive_data", "pop_preproc", "imap_preproc", "dnp3_preproc", "modbus_preproc", "reputation_preproc", "appid_preproc"
);
$default_disabled_preprocs = array(
	"sf_portscan", "gtp_preproc", "sensitive_data", "dnp3_preproc", "modbus_preproc", "reputation_preproc", "perform_stat", "appid_preproc"
);
$snort_preprocessors = "";
foreach ($snort_preproc as $preproc) {
	if ($snortcfg[$preproc] == 'on' || empty($snortcfg[$preproc]) ) {

		/* If preprocessor is not explicitly "on" or "off", then default to "off" if in our default disabled list */
		if (empty($snortcfg[$preproc]) && in_array($preproc, $default_disabled_preprocs))
			continue;

		/* NOTE: The $$ is not a bug. It is an advanced feature of php */
		if (!empty($snort_preproc_libs[$preproc])) {
			$preproclib = "libsf_" . $snort_preproc_libs[$preproc];
			if (!file_exists($snort_dirs['dynamicpreprocessor'] . "{$preproclib}.so")) {
				if (file_exists("{$snortlibdir}/snort_dynamicpreprocessor/{$preproclib}.so")) {
					@copy("{$snortlibdir}/snort_dynamicpreprocessor/{$preproclib}.so", "{$snort_dirs['dynamicpreprocessor']}/{$preproclib}.so");
					$snort_preprocessors .= $$preproc;
					$snort_preprocessors .= "\n";
				} else
					log_error("Could not find the {$preproclib} file. Snort might error out!");
			} else {
				$snort_preprocessors .= $$preproc;
				$snort_preprocessors .= "\n";
			}
		} else {
			$snort_preprocessors .= $$preproc;
			$snort_preprocessors .= "\n";
		}
	}
}
// Remove final trailing newline
$snort_preprocessors = rtrim($snort_preprocessors);

$snort_misc_include_rules = "";
if (file_exists("{$snortcfgdir}/reference.config"))
	$snort_misc_include_rules .= "include {$snortcfgdir}/reference.config\n";
if (file_exists("{$snortcfgdir}/classification.config"))
	$snort_misc_include_rules .= "include {$snortcfgdir}/classification.config\n";
if (!file_exists("{$snortcfgdir}/preproc_rules/decoder.rules") || !file_exists("{$snortcfgdir}/preproc_rules/preprocessor.rules")) {
	$snort_misc_include_rules .= "config autogenerate_preprocessor_decoder_rules\n";
	log_error("[Snort] Seems preprocessor and/or decoder rules are missing, enabling autogeneration of them in conf file.");
}

/* generate rule sections to load */
/* The files are always configured so the update process is easier */
$selected_rules_sections = "include \$RULE_PATH/{$snort_enforcing_rules_file}\n";
$selected_rules_sections .= "include \$RULE_PATH/{$flowbit_rules_file}\n";
$selected_rules_sections  .= "include \$RULE_PATH/custom.rules\n";

// Remove trailing newlines
$snort_misc_include_rules = rtrim($snort_misc_include_rules);
$selected_rules_sections = rtrim($selected_rules_sections);

$cksumcheck = "all";
if ($snortcfg['cksumcheck'] == 'on')
	$cksumcheck = "none";

/* Pull in user-configurable detection config options */
$cfg_detect_settings = "search-method {$snort_performance} max-pattern-len 20 max_queue_events 5";
if ($snortcfg['fpm_split_any_any'] == "on")
	$cfg_detect_settings .= " split-any-any";
if ($snortcfg['fpm_search_optimize'] == "on")
	$cfg_detect_settings .= " search-optimize";
if ($snortcfg['fpm_no_stream_inserts'] == "on")
	$cfg_detect_settings .= " no_stream_inserts";

/* Pull in user-configurable options for Frag3 preprocessor settings */
/* Get global Frag3 options first and put into a string */
$frag3_global = "preprocessor frag3_global: ";
if (!empty($snortcfg['frag3_memcap']) || $snortcfg['frag3_memcap'] == "0")
	$frag3_global .= "memcap {$snortcfg['frag3_memcap']}, ";
else
	$frag3_global .= "memcap 4194304, ";
if (!empty($snortcfg['frag3_max_frags']))
	$frag3_global .= "max_frags {$snortcfg['frag3_max_frags']}";
else
	$frag3_global .= "max_frags 8192";
if ($snortcfg['frag3_detection'] == "off")
	$frag3_global .= ", disabled";

$frag3_default_tcp_engine = array( "name" => "default", "bind_to" => "all", "policy" => "bsd", 
				   "timeout" => 60, "min_ttl" => 1, "detect_anomalies" => "on", 
				   "overlap_limit" => 0, "min_frag_len" => 0 );
$frag3_engine = "";

// Now iterate configured Frag3 engines and write them to a string if enabled
if ($snortcfg['frag3_detection'] == "on") {
	if (!is_array($snortcfg['frag3_engine']['item']))
		$snortcfg['frag3_engine']['item'] = array();

	// If no frag3 tcp engine is configured, use the default
	if (empty($snortcfg['frag3_engine']['item']))
		$snortcfg['frag3_engine']['item'][] = $frag3_default_tcp_engine;

	foreach ($snortcfg['frag3_engine']['item'] as $f => $v) {
		$frag3_engine .= "preprocessor frag3_engine: ";
		$frag3_engine .= "policy {$v['policy']}";
		if ($v['bind_to'] <> "all") {
			$tmp = trim(filter_expand_alias($v['bind_to']));
			if (!empty($tmp)) {
				$tmp = preg_replace('/\s+/', ',', $tmp);
				if (strpos($tmp, ",") !== false)
					$frag3_engine .= " \\\n\tbind_to [{$tmp}]";
				else
					$frag3_engine .= " \\\n\tbind_to {$tmp}";
			}
			else
				log_error("[snort] WARNING: unable to resolve IP List Alias '{$v['bind_to']}' for Frag3 engine '{$v['name']}' ... using 0.0.0.0 failsafe.");
		}
		$frag3_engine .= " \\\n\ttimeout {$v['timeout']}";
		$frag3_engine .= " \\\n\tmin_ttl {$v['min_ttl']}";
		if ($v['detect_anomalies'] == "on") {
			$frag3_engine .= " \\\n\tdetect_anomalies";
			$frag3_engine .= " \\\n\toverlap_limit {$v['overlap_limit']}";
			$frag3_engine .= " \\\n\tmin_fragment_length {$v['min_frag_len']}";
		}
		// Add newlines to terminate this engine
		$frag3_engine .= "\n\n";
	}
	// Remove trailing newline
	$frag3_engine = rtrim($frag3_engine);
}

// Grab any user-customized value for Protocol Aware Flushing (PAF) max PDUs
$paf_max_pdu_config = "config paf_max: ";
if (empty($snortcfg['max_paf']) || $snortcfg['max_paf'] == '0')
	$paf_max_pdu_config .= "0";
else
	$paf_max_pdu_config .= $snortcfg['max_paf'];

// Pull in user-configurable options for Stream5 preprocessor settings
// Get global options first and put into a string
$stream5_global = "preprocessor stream5_global: \\\n";
if ($snortcfg['stream5_reassembly'] == "off")
	$stream5_global .= "\tdisabled, \\\n";
if ($snortcfg['stream5_track_tcp'] == "off")
	$stream5_global .= "\ttrack_tcp no,";
else {
	$stream5_global .= "\ttrack_tcp yes,";
	if (!empty($snortcfg['stream5_max_tcp']))
		$stream5_global .= " \\\n\tmax_tcp {$snortcfg['stream5_max_tcp']},";
	else
		$stream5_global .= " \\\n\tmax_tcp 262144,";
}
if ($snortcfg['stream5_track_udp'] == "off")
	$stream5_global .= " \\\n\ttrack_udp no,";
else {
	$stream5_global .= " \\\n\ttrack_udp yes,";
	if (!empty($snortcfg['stream5_max_udp']))
		$stream5_global .= " \\\n\tmax_udp {$snortcfg['stream5_max_udp']},";
	else
		$stream5_global .= " \\\n\tmax_udp 131072,";
}
if ($snortcfg['stream5_track_icmp'] == "on") {
	$stream5_global .= " \\\n\ttrack_icmp yes,";
	if (!empty($snortcfg['stream5_max_icmp']))
		$stream5_global .= " \\\n\tmax_icmp {$snortcfg['stream5_max_icmp']},";
	else
		$stream5_global .= " \\\n\tmax_icmp 65536,";
}
else
	$stream5_global .= " \\\n\ttrack_icmp no,";
if (!empty($snortcfg['stream5_mem_cap']))
	$stream5_global .= " \\\n\tmemcap {$snortcfg['stream5_mem_cap']},";
else
	$stream5_global .= " \\\n\tmemcap 8388608,";

if (!empty($snortcfg['stream5_prune_log_max']) || $snortcfg['stream5_prune_log_max'] == '0')
	$stream5_global .= " \\\n\tprune_log_max {$snortcfg['stream5_prune_log_max']}";
else
	$stream5_global .= " \\\n\tprune_log_max 1048576";
if ($snortcfg['stream5_flush_on_alert'] == "on")
	$stream5_global .= ", \\\n\tflush_on_alert";

$stream5_default_tcp_engine = array( "name" => "default", "bind_to" => "all", "policy" => "bsd", "timeout" => 30, 
				     "max_queued_bytes" => 1048576, "detect_anomalies" => "off", "overlap_limit" => 0, 
				     "max_queued_segs" => 2621, "require_3whs" => "off", "startup_3whs_timeout" => 0, 
				     "no_reassemble_async" => "off", "dont_store_lg_pkts" => "off", "max_window" => 0, 
				     "use_static_footprint_sizes" => "off", "check_session_hijacking" => "off", "ports_client" => "default", 
				     "ports_both" => "default", "ports_server" => "none" );
$stream5_tcp_engine = "";

// Now iterate configured Stream5 TCP engines and write them to a string if enabled
if ($snortcfg['stream5_reassembly'] == "on") {
	if (!is_array($snortcfg['stream5_tcp_engine']['item']))
		$snortcfg['stream5_tcp_engine']['item'] = array();

	// If no stream5 tcp engine is configured, use the default
	if (empty($snortcfg['stream5_tcp_engine']['item']))
		$snortcfg['stream5_tcp_engine']['item'][] = $stream5_default_tcp_engine;

	foreach ($snortcfg['stream5_tcp_engine']['item'] as $f => $v) {
		$buffer = "preprocessor stream5_tcp: ";
		$buffer .= "policy {$v['policy']},";
		if ($v['bind_to'] <> "all") {
			$tmp = trim(filter_expand_alias($v['bind_to']));
			if (!empty($tmp)) {
				$tmp = preg_replace('/\s+/', ',', $tmp);
				if (strpos($tmp, ",") !== false)
					$buffer .= " \\\n\tbind_to [{$tmp}],";
				else
					$buffer .= " \\\n\tbind_to {$tmp},";
			}
			else {
				log_error("[snort] WARNING: unable to resolve IP Address Alias [{$v['bind_to']}] for Stream5 TCP engine '{$v['name']}' ... skipping this engine.");
				continue;
			}
		}
		$stream5_tcp_engine .= $buffer;
		$stream5_tcp_engine .= " \\\n\ttimeout {$v['timeout']},";
		$stream5_tcp_engine .= " \\\n\toverlap_limit {$v['overlap_limit']},";
		$stream5_tcp_engine .= " \\\n\tmax_window {$v['max_window']},";
		$stream5_tcp_engine .= " \\\n\tmax_queued_bytes {$v['max_queued_bytes']},";
		$stream5_tcp_engine .= " \\\n\tmax_queued_segs {$v['max_queued_segs']}";
		if ($v['use_static_footprint_sizes'] == "on")
			$stream5_tcp_engine .= ", \\\n\tuse_static_footprint_sizes";
		if ($v['check_session_hijacking'] == "on")
			$stream5_tcp_engine .= ", \\\n\tcheck_session_hijacking";
		if ($v['dont_store_lg_pkts'] == "on")
			$stream5_tcp_engine .= ", \\\n\tdont_store_large_packets";
		if ($v['no_reassemble_async'] == "on")
			$stream5_tcp_engine .= ", \\\n\tdont_reassemble_async";
		if ($v['detect_anomalies'] == "on")
			$stream5_tcp_engine .= ", \\\n\tdetect_anomalies";
		if ($v['require_3whs'] == "on")
			$stream5_tcp_engine .= ", \\\n\trequire_3whs {$v['startup_3whs_timeout']}";
		if (!empty($v['ports_client'])) {
			$stream5_tcp_engine .= ", \\\n\tports client";
			if ($v['ports_client'] == " all")
				$stream5_tcp_engine .= " all";
			elseif ($v['ports_client'] == "default")
				$stream5_tcp_engine .= " {$stream5_ports_client}";
			else {
				$tmp = trim(filter_expand_alias($v['ports_client']));
				if (!empty($tmp))
					$stream5_tcp_engine .= " " . trim(preg_replace('/\s+/', ' ', $tmp));
				else {
					$stream5_tcp_engine .= " {$stream5_ports_client}";
					log_error("[snort] WARNING: unable to resolve Ports Client Alias [{$v['ports_client']}] for Stream5 TCP engine '{$v['name']}' ... using default value.");
				}
			}
		}
		if (!empty($v['ports_both'])) {
			$stream5_tcp_engine .= ", \\\n\tports both";
			if ($v['ports_both'] == " all")
				$stream5_tcp_engine .= " all";
			elseif ($v['ports_both'] == "default")
				$stream5_tcp_engine .= " {$stream5_ports_both}";
			else {
				$tmp = trim(filter_expand_alias($v['ports_both']));
				if (!empty($tmp))
					$stream5_tcp_engine .= " " . trim(preg_replace('/\s+/', ' ', $tmp));
				else {
					$stream5_tcp_engine .= " {$stream5_ports_both}";
					log_error("[snort] WARNING: unable to resolve Ports Both Alias [{$v['ports_both']}] for Stream5 TCP engine '{$v['name']}' ... using default value.");
				}
			}
		}
		if (!empty($v['ports_server']) && $v['ports_server'] <> "none" && $v['ports_server'] <> "default") {
			if ($v['ports_server'] == " all") {
				$stream5_tcp_engine .= ", \\\n\tports server";
				$stream5_tcp_engine .= " all";
			}
			else {
				$tmp = trim(filter_expand_alias($v['ports_server']));
				if (!empty($tmp)) {
					$stream5_tcp_engine .= ", \\\n\tports server";
					$stream5_tcp_engine .= " " . trim(preg_replace('/\s+/', ' ', $tmp));
				}
				else
					log_error("[snort] WARNING: unable to resolve Ports Server Alias [{$v['ports_server']}] for Stream5 TCP engine '{$v['name']}' ... defaulting to none.");
			}
		}

		// Make sure the "ports" parameter is set, or else default to a safe value
		if (strpos($stream5_tcp_engine, "ports ") === false)
			$stream5_tcp_engine .= ", \\\n\tports both all";

		// Add a pair of newlines to terminate this engine
		$stream5_tcp_engine .= "\n\n";
	}
	// Trim off the final trailing newline
	$stream5_tcp_engine = rtrim($stream5_tcp_engine);
}

// Configure the Stream5 UDP engine if it and Stream5 reassembly are enabled
if ($snortcfg['stream5_track_udp'] == "off" || $snortcfg['stream5_reassembly'] == "off")
	$stream5_udp_engine = "";
else {
	$stream5_udp_engine = "preprocessor stream5_udp: ";
	if (!empty($snortcfg['stream5_udp_timeout'])) 
		$stream5_udp_engine .= "timeout {$snortcfg['stream5_udp_timeout']}";
	else
		$stream5_udp_engine .= "timeout 30";
}

// Configure the Stream5 ICMP engine if it and Stream5 reassembly are enabled
if ($snortcfg['stream5_track_icmp'] == "on" && $snortcfg['stream5_reassembly'] == "on") {
	$stream5_icmp_engine = "preprocessor stream5_icmp: ";
	if (!empty($snortcfg['stream5_icmp_timeout'])) 
		$stream5_icmp_engine .= "timeout {$snortcfg['stream5_icmp_timeout']}";
	else
		$stream5_icmp_engine .= "timeout 30";
}
else
	$stream5_icmp_engine = "";

// Check for and configure Host Attribute Table if enabled
$host_attrib_config = "";
if ($snortcfg['host_attribute_table'] == "on" && !empty($snortcfg['host_attribute_data'])) {
	@file_put_contents("{$snortcfgdir}/host_attributes", base64_decode($snortcfg['host_attribute_data']));
	$host_attrib_config = "# Host Attribute Table #\n";
	$host_attrib_config .= "attribute_table filename {$snortcfgdir}/host_attributes\n";
	if (!empty($snortcfg['max_attribute_hosts']))
		$host_attrib_config .= "config max_attribute_hosts: {$snortcfg['max_attribute_hosts']}\n";
	if (!empty($snortcfg['max_attribute_services_per_host']))
		$host_attrib_config .= "config max_attribute_services_per_host: {$snortcfg['max_attribute_services_per_host']}";
}

// Configure the HTTP_INSPECT preprocessor
// Get global options first and put into a string
$http_inspect_global = "preprocessor http_inspect: global ";
if ($snortcfg['http_inspect'] == "off")
	$http_inspect_global .= "disabled ";
$http_inspect_global .= "\\\n\tiis_unicode_map {$snortdir}/unicode.map 1252 \\\n";
$http_inspect_global .= "\tcompress_depth 65535 \\\n";
$http_inspect_global .= "\tdecompress_depth 65535 \\\n";
if (!empty($snortcfg['http_inspect_memcap']))
	$http_inspect_global .= "\tmemcap {$snortcfg['http_inspect_memcap']} \\\n";
else
	$http_inspect_global .= "\tmemcap 150994944 \\\n";
if (!empty($snortcfg['http_inspect_max_gzip_mem']))
	$http_inspect_global .= "\tmax_gzip_mem {$snortcfg['http_inspect_max_gzip_mem']}";
else
	$http_inspect_global .= "\tmax_gzip_mem 838860";
if ($snortcfg['http_inspect_proxy_alert'] == "on")
	$http_inspect_global .= " \\\n\tproxy_alert";

$http_inspect_default_engine = array( "name" => "default", "bind_to" => "all", "server_profile" => "all", "enable_xff" => "off", 
				      "log_uri" => "off", "log_hostname" => "off", "server_flow_depth" => 65535, "enable_cookie" => "on", 
				      "client_flow_depth" => 1460, "extended_response_inspection" => "on", "no_alerts" => "off", 
				      "unlimited_decompress" => "on", "inspect_gzip" => "on", "normalize_cookies" =>"on", "normalize_headers" => "on", 
				      "normalize_utf" => "on", "normalize_javascript" => "on", "allow_proxy_use" => "off", "inspect_uri_only" => "off", 
				      "max_javascript_whitespaces" => 200, "post_depth" => -1, "max_headers" => 0, "max_spaces" => 0, 
				      "max_header_length" => 0, "ports" => "default", "decompress_swf" => "off", "decompress_pdf" => "off" );
$http_ports = str_replace(",", " ", snort_expand_port_range($snort_ports['http_ports']));
$http_inspect_servers = "";

// Iterate configured HTTP_INSPECT servers and write them to string if HTTP_INSPECT enabled
if ($snortcfg['http_inspect'] <> "off") {
	if (!is_array($snortcfg['http_inspect_engine']['item']))
		$snortcfg['http_inspect_engine']['item'] = array();

	// If no http_inspect_engine is configured, use the default
	if (empty($snortcfg['http_inspect_engine']['item']))
		$snortcfg['http_inspect_engine']['item'][] = $http_inspect_default_engine;

	foreach ($snortcfg['http_inspect_engine']['item'] as $f => $v) {
		$buffer = "preprocessor http_inspect_server: \\\n";
		if ($v['name'] == "default")
			$buffer .= "\tserver default \\\n";
		elseif (is_alias($v['bind_to'])) {
			$tmp = trim(filter_expand_alias($v['bind_to']));
			if (!empty($tmp)) {
				$tmp = preg_replace('/\s+/', ' ', $tmp);
					$buffer .= "\tserver { {$tmp} } \\\n";
			}
			else {
				log_error("[snort] WARNING: unable to resolve IP Address Alias [{$v['bind_to']}] for HTTP_INSPECT server '{$v['name']}' ... skipping this server engine.");
				continue;
			}
		}
		else {
				log_error("[snort] WARNING: unable to resolve IP Address Alias [{$v['bind_to']}] for HTTP_INSPECT server '{$v['name']}' ... skipping this server engine.");
				continue;
		}
		$http_inspect_servers .= $buffer;
		$http_inspect_servers .= "\tprofile {$v['server_profile']} \\\n";

		if ($v['no_alerts'] == "on")
			$http_inspect_servers .= "\tno_alerts \\\n";

		if ($v['ports'] == "default" || empty($v['ports']))
			$http_inspect_servers .= "\tports { {$http_ports} } \\\n";
		elseif (is_alias($v['ports'])) {
			$tmp = trim(filter_expand_alias($v['ports']));
			if (!empty($tmp)) {
				$tmp = preg_replace('/\s+/', ' ', $tmp);
				$tmp = snort_expand_port_range($tmp, ' ');
				$http_inspect_servers .= "\tports { {$tmp} } \\\n";
			}
			else {
				log_error("[snort] WARNING: unable to resolve Ports Alias [{$v['ports']}] for HTTP_INSPECT server '{$v['name']}' ... using safe default instead.");
				$http_inspect_servers .= "\tports { {$http_ports} } \\\n";
			}
		}
		else {
				log_error("[snort] WARNING: unable to resolve Ports Alias [{$v['ports']}] for HTTP_INSPECT server '{$v['name']}' ... using safe default instead.");
				$http_inspect_servers .= "\tports { {$http_ports} } \\\n";
		}

		$http_inspect_servers .= "\tserver_flow_depth {$v['server_flow_depth']} \\\n";
		$http_inspect_servers .= "\tclient_flow_depth {$v['client_flow_depth']} \\\n";
		$http_inspect_servers .= "\thttp_methods { GET POST PUT SEARCH MKCOL COPY MOVE LOCK UNLOCK NOTIFY POLL BCOPY BDELETE BMOVE LINK UNLINK OPTIONS HEAD DELETE TRACE TRACK CONNECT SOURCE SUBSCRIBE UNSUBSCRIBE PROPFIND PROPPATCH BPROPFIND BPROPPATCH RPC_CONNECT PROXY_SUCCESS BITS_POST CCM_POST SMS_POST RPC_IN_DATA RPC_OUT_DATA RPC_ECHO_DATA } \\\n";
		$http_inspect_servers .= "\tpost_depth {$v['post_depth']} \\\n";
		$http_inspect_servers .= "\tmax_headers {$v['max_headers']} \\\n";
		$http_inspect_servers .= "\tmax_header_length {$v['max_header_length']} \\\n";
		$http_inspect_servers .= "\tmax_spaces {$v['max_spaces']}";
		if ($v['enable_xff'] == "on")
			$http_inspect_servers .= " \\\n\tenable_xff";
		if ($v['enable_cookie'] == "on")
			$http_inspect_servers .= " \\\n\tenable_cookie";
		if ($v['normalize_cookies'] == "on")
			$http_inspect_servers .= " \\\n\tnormalize_cookies";
		if ($v['normalize_headers'] == "on")
			$http_inspect_servers .= " \\\n\tnormalize_headers";
		if ($v['normalize_utf'] == "on")
			$http_inspect_servers .= " \\\n\tnormalize_utf";
		if ($v['allow_proxy_use'] == "on")
			$http_inspect_servers .= " \\\n\tallow_proxy_use";
		if ($v['inspect_uri_only'] == "on")
			$http_inspect_servers .= " \\\n\tinspect_uri_only";
		if ($v['extended_response_inspection'] == "on") {
			$http_inspect_servers .= " \\\n\textended_response_inspection";
			if ($v['inspect_gzip'] == "on") {
				$http_inspect_servers .= " \\\n\tinspect_gzip";
				if ($v['unlimited_decompress'] == "on")
					$http_inspect_servers .= " \\\n\tunlimited_decompress";
			}
			if ($v['normalize_javascript'] == "on") {
				$http_inspect_servers .= " \\\n\tnormalize_javascript";
				$http_inspect_servers .= " \\\n\tmax_javascript_whitespaces {$v['max_javascript_whitespaces']}";
			}
		}
		if ($v['log_uri'] == "on")
			$http_inspect_servers .= " \\\n\tlog_uri";
		if ($v['log_hostname'] == "on")
			$http_inspect_servers .= " \\\n\tlog_hostname";
		if ($v['decompress_swf'] == "on")
			$http_inspect_servers .= " \\\n\tdecompress_swf";
		if ($v['decompress_pdf'] == "on")
			$http_inspect_servers .= " \\\n\tdecompress_pdf";

		// Add a pair of trailing newlines to terminate this server config
		$http_inspect_servers .= "\n\n";
	}
	/* Trim off the final trailing newline */
	$http_inspect_server = rtrim($http_inspect_server);
}

?>
