<?php
/*
 * snort_define_servers.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2008-2009 Robert Zelaya.
 * Copyright (c) 2014-2018 Bill Meeks
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

//require_once("globals.inc");
require_once("guiconfig.inc");
require_once("/usr/local/pkg/snort/snort.inc");

global $g, $rebuild_rules;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
	header("Location: /snort/snort_interfaces.php");
	exit;
}

if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}
$a_nat = &$config['installedpackages']['snortglobal']['rule'];

/* NOTE: KEEP IN SYNC WITH SNORT.INC since globals do not work well with package */
/* define servers and ports snortdefservers */
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

/* if user has defined a custom ssh port, use it */
if(is_array($config['system']['ssh']) && isset($config['system']['ssh']['port']))
        $ssh_port = $config['system']['ssh']['port'];
else
        $ssh_port = "22";
$snort_ports = array(
	"dns_ports" => "53", "smtp_ports" => "25", "mail_ports" => "25,465,587,691",
	"http_ports" => "36,80,81,82,83,84,85,86,87,88,89,90,311,383,591,593,631,901,1220,1414,1533,1741,1830,2301,2381,2809,3037,3057,3128,3443,3702,4343,4848,5250,6080,6988,7000,7001,7144,7145,7510,7777,7779,8000,8008,8014,8028,8080,8081,8082,8085,8088,8090,8118,8123,8180,8181,8222,8243,8280,8300,8500,8800,8888,8899,9000,9060,9080,9090,9091,9443,9999,10000,11371,15489,29991,33300,34412,34443,34444,41080,44440,50000,50002,51423,55555,56712", 
	"oracle_ports" => "1024:", "mssql_ports" => "1433",
	"telnet_ports" => "23","snmp_ports" => "161", "ftp_ports" => "21,2100,3535",
	"ssh_ports" => $ssh_port, "pop2_ports" => "109", "pop3_ports" => "110", 
	"imap_ports" => "143", "sip_proxy_ports" => "5060:5090,16384:32768",
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

// Sort our SERVERS and PORTS arrays to make values
// easier to locate for the user.
ksort($snort_servers);
ksort($snort_ports);

$pconfig = $a_nat[$id];

/* convert fake interfaces to real */
$if_real = get_real_interface($pconfig['interface']);
$snort_uuid = $config['installedpackages']['snortglobal']['rule'][$id]['uuid'];

if ($_POST['save']) {

	$natent = array();
	$natent = $pconfig;

	foreach ($snort_servers as $key => $server) {
		if ($_POST["def_{$key}"] && !is_alias($_POST["def_{$key}"]))
			$input_errors[] = "Only aliases are allowed.";
		if ($_POST["def_{$key}"] && is_alias($_POST["def_{$key}"]) && trim(filter_expand_alias($_POST["def_{$key}"])) == "")
			$input_errors[] = "FQDN aliases are not allowed in Snort.";
	}
	foreach ($snort_ports as $key => $server) {
		if ($_POST["def_{$key}"] && !is_alias($_POST["def_{$key}"]))
			$input_errors[] = "Only aliases are allowed.";
		if ($_POST["def_{$key}"] && is_alias($_POST["def_{$key}"]) && trim(filter_expand_alias($_POST["def_{$key}"])) == "")
			$input_errors[] = "FQDN aliases are not allowed in Snort.";
	}
	/* if no errors write to conf */
	if (!$input_errors) {
		/* post new options */
		foreach ($snort_servers as $key => $server) {
			if ($_POST["def_{$key}"])
				$natent["def_{$key}"] = $_POST["def_{$key}"];
			else
				unset($natent["def_{$key}"]);
		}
		foreach ($snort_ports as $key => $server) {
			if ($_POST["def_{$key}"])
				$natent["def_{$key}"] = $_POST["def_{$key}"];
			else
				unset($natent["def_{$key}"]);
		}

		$a_nat[$id] = $natent;

		write_config("Snort pkg: modified settings for VARIABLES tab.");

		/* Update the snort conf file for this interface. */
		$rebuild_rules = false;
		conf_mount_rw();
		snort_generate_conf($a_nat[$id]);
		conf_mount_ro();

		/* Soft-restart Snort to live-load new variables. */
		snort_reload_config($a_nat[$id]);

		/* Sync to configured CARP slaves if any are enabled */
		snort_sync_on_changes();

		/* after click go to this page */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: snort_define_servers.php?id=$id");
		exit;
	}
	else
		$pconfig = $_POST;
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']);
if (empty($if_friendly)) {
	$if_friendly = "None";
}$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Interface Servers and Ports Variables - {$if_friendly}"));
include("head.inc");

/* Display Alert message */
if ($input_errors)
	print_input_errors($input_errors); // TODO: add checks
if ($savemsg)
	print_info_box($savemsg);

$tab_array = array();
$tab_array[] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php?instance={$id}");
$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);
$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
$tab_array = array();
$tab_array[] = array($menu_iface . gettext(" Settings"), false, "/snort/snort_interfaces_edit.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Categories"), false, "/snort/snort_rulesets.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Rules"), false, "/snort/snort_rules.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Variables"), true, "/snort/snort_define_servers.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Preprocs"), false, "/snort/snort_preprocessors.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/snort/snort_barnyard.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/snort/snort_ip_reputation.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
display_top_tabs($tab_array, true);

$form = new Form();

$form->addGlobal(new Form_Input(
	'id',
	'id',
	'hidden',
	$id
));

$section = new Form_Section('Define Servers (IP variables)');

foreach ($snort_servers as $key => $server) {
	if (strlen($server) > 40) {
		$server = substr($server, 0, 40) . "...";
	}

	$name = "def_" . $key;
	$label = strtoupper($key);
	$value = "";
	$title = "";

	if (!empty($pconfig["def_{$key}"])) {
		$value = htmlspecialchars($pconfig["def_{$key}"]);
		$title = trim(filter_expand_alias($pconfig["def_{$key}"]));
	}

	$section->addInput(new Form_Input(
		$name,
		$label,
		'text',
		$pconfig[$name]
	))->setHelp('Default value: ' . $server . '. Leave blank for default value.');
}
$form->add($section);

$section = new Form_Section('Define Ports (port variables)');
foreach ($snort_ports as $key => $server) {
	if (strlen($server) > 40) {
		$server = substr($server, 0, 40) . "...";
	}

	$label = strtoupper($key);
	$name = "def_" . $key;
	$value = "";
	$title = "";

	if (!empty($pconfig["def_{$key}"])) {
		$value = htmlspecialchars($pconfig["def_{$key}"]);
		$title = trim(filter_expand_alias($pconfig["def_{$key}"]));
	}

	$section->addInput(new Form_Input(
		$name,
		$label,
		'text',
		$pconfig[$name]
	))->setHelp('Default value: ' . $server . '. Leave blank for default value.');

}
$form->add($section);

print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var addressarray = <?= json_encode(get_alias_list(array("host", "network"))) ?>;
	var portsarray  = <?= json_encode(get_alias_list("port")) ?>;

	function createAutoSuggest() {
	<?php

		foreach ($snort_servers as $key => $server)
			echo '$("#def_' . $key . '").autocomplete({source: addressarray});' . "\n";

		foreach ($snort_ports as $key => $server)
			echo '$("#def_' . $key . '").autocomplete({source: portsarray});' . "\n";
	?>
	}

	setTimeout(createAutoSuggest, 500);

});
//]]>
</script>

<?php include("foot.inc"); ?>
