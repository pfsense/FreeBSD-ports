<?php
/*
 * suricata_define_vars.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2024 Bill Meeks
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
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g, $rebuild_rules;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);
if (is_null($id)) {
		header("Location: /suricata/suricata_interfaces.php");
		exit;
}

$a_nat = config_get_path("installedpackages/suricata/rule/{$id}", []);

/* define servers and ports */
$suricata_servers = array (
	"dns_servers" => "\$HOME_NET", "smtp_servers" => "\$HOME_NET", "http_servers" => "\$HOME_NET",
	"sql_servers" => "\$HOME_NET", "telnet_servers" => "\$HOME_NET", "dnp3_server" => "\$HOME_NET",
	"dnp3_client" => "\$HOME_NET", "modbus_server" => "\$HOME_NET", "modbus_client" => "\$HOME_NET",
	"enip_server" => "\$HOME_NET", "enip_client" => "\$HOME_NET", "ftp_servers" => "\$HOME_NET", "ssh_servers" => "\$HOME_NET",
	"aim_servers" => "64.12.24.0/23,64.12.28.0/23,64.12.161.0/24,64.12.163.0/24,64.12.200.0/24,205.188.3.0/24,205.188.5.0/24,205.188.7.0/24,205.188.9.0/24,205.188.153.0/24,205.188.179.0/24,205.188.248.0/24", 
	"sip_servers" => "\$HOME_NET"
);

/* if user has defined a custom ssh port, use it, else default to '22' */
$ssh_port = config_get_path('system/ssh/port', '22');
$suricata_ports = array(
	"ftp_ports" => "21",
	"http_ports" => "80",
	"oracle_ports" => "1521",
	"ssh_ports" => $ssh_port,
	"shellcode_ports" => "!80",
	"DNP3_PORTS" => "20000", 
	"file_data_ports" => "\$HTTP_PORTS,110,143", 
	"sip_ports" => "5060,5061,5600"
);

// Sort our SERVERS and PORTS arrays to make values
// easier to locate by the the user.
ksort($suricata_servers);
ksort($suricata_ports);

$pconfig = $a_nat;

/* convert fake interfaces to real */
$if_real = get_real_interface($pconfig['interface']);
$suricata_uuid = config_get_path("installedpackages/suricata/rule/{$id}/uuid");

if ($_POST) {

	$natent = array();
	$natent = $pconfig;

	foreach ($suricata_servers as $key => $server) {
		if ($_POST["def_{$key}"] && !is_alias($_POST["def_{$key}"]))
			$input_errors[] = "Only aliases are allowed";
		if ($_POST["def_{$key}"] && is_alias($_POST["def_{$key}"]) && trim(filter_expand_alias($_POST["def_{$key}"])) == "")
			$input_errors[] = "FQDN aliases are not allowed for IP variables in Suricata.";
	}
	foreach ($suricata_ports as $key => $server) {
		if ($_POST["def_{$key}"] && !is_alias($_POST["def_{$key}"]))
			$input_errors[] = "Only aliases are allowed";
		if ($_POST["def_{$key}"] && is_alias($_POST["def_{$key}"]) && trim(filter_expand_alias($_POST["def_{$key}"])) == "")
			$input_errors[] = "FQDN aliases are not allowed for port variables in Suricata.";
	}

	if ($_POST['enable_extra_servers_ports']) {
		for ($x = 0; $x < 99; $x++) {
			if (isset($_POST["extra_name{$x}"]) && isset($_POST["extra_value{$x}"])) {
				$type = $_POST["extra_server_or_port{$x}"];
				$name = trim($_POST["extra_name{$x}"]);
				$value = $_POST["extra_value{$x}"];

				if (preg_match("/[^A-Za-z0-9_]/", $name)) {
					$input_errors[] = gettext("The extra variable name may only contain the characters A-Z, 0-9 and '_'.");
				}
				if ("" != $value) {
					if (!is_alias($value)) {
						$input_errors[] = "Only aliases are allowed";
					} elseif (trim(filter_expand_alias($value)) == "") {
						$input_errors[] = "FQDN aliases are not allowed for variables in Suricata.";
					} elseif ($type == 'server' && $suricata_servers[strtolower($name)]) {
						$input_errors[] = "'" . $name . "' is a standard server variable.";
					} elseif ($type == 'port' && $suricata_ports[strtolower($name)]) {
						$input_errors[] = "'" . $name . "' is a standard port variable.";
					}
				}
				$extra_servers_ports['item'][] = array(
					'type' => $type,
					'name' => $name,
					'value' => $value
				);
			}
		}
		$natent['extra_servers_ports'] = $extra_servers_ports;
	}

	/* if no errors write to suricata.yaml */
	if (!$input_errors) {
		/* post new options */
		foreach ($suricata_servers as $key => $server) {
			if ($_POST["def_{$key}"])
				$natent["def_{$key}"] = $_POST["def_{$key}"];
			else
				unset($natent["def_{$key}"]);
		}
		foreach ($suricata_ports as $key => $server) {
			if ($_POST["def_{$key}"])
				$natent["def_{$key}"] = $_POST["def_{$key}"];
			else
				unset($natent["def_{$key}"]);
		}

		// Save the updated interface configuration
		$a_nat = $natent;
		config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
		config_set_path("installedpackages/suricata/rule/{$id}/enable_extra_servers_ports", $_POST['enable_extra_servers_ports'] ? 'on' : 'off');
		config_set_path("installedpackages/suricata/rule/{$id}/extra_servers_ports", $extra_servers_ports);
		write_config("Suricata pkg: saved changes for PORT or IP variables.");

		/* Update the suricata.yaml file for this interface. */
		$rebuild_rules = false;
		suricata_generate_yaml($a_nat);

		/* Soft-restart Suricaa to live-load new variables. */
		suricata_reload_config($a_nat);

		/* Sync to configured CARP slaves if any are enabled */
		suricata_sync_on_changes();

		/* after click go to this page */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: suricata_define_vars.php?id=$id");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($pconfig['interface']);
$pglinks = array("", "/suricata/suricata_interfaces.php", "/suricata/suricata_interfaces_edit.php?id={$id}", "@self");
$pgtitle = array("Services", "Suricata", "Interface Settings", "{$if_friendly} - Server and Port Variables");
include_once("head.inc");

/* Display Alert message */
if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

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

$tab_array = array();
$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
$tab_array[] = array($menu_iface . gettext("Settings"), false, "/suricata/suricata_interfaces_edit.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Categories"), false, "/suricata/suricata_rulesets.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Rules"), false, "/suricata/suricata_rules.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Flow/Stream"), false, "/suricata/suricata_flow_stream.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("App Parsers"), false, "/suricata/suricata_app_parsers.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("Variables"), true, "/suricata/suricata_define_vars.php?id={$id}");
$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/suricata/suricata_ip_reputation.php?id={$id}");
display_top_tabs($tab_array, true);

$form = new Form();

$form->addGlobal(new Form_Input(
	'id',
	'id',
	'hidden',
	$id
));

$section = new Form_Section('Define Servers (IP variables)');

foreach ($suricata_servers as $key => $server) {
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
foreach ($suricata_ports as $key => $server) {
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

$section = new Form_Section('Enable Extra Variables');
$section->addInput(new Form_Checkbox(
	'enable_extra_servers_ports',
	'Enable Extra Variables',
	'Enable Extra Variables',
	$pconfig['enable_extra_servers_ports'] == 'on' ? true:false,
	'on'
))->setHelp('Add extra custom servers and ports');
$form->add($section);

$section = new Form_Section('Define Extras Variables (Custom IP or port variables)');
$section->addClass('extra_servers_ports');

if (!$pconfig['extra_servers_ports']) {
	$pconfig['extra_servers_ports'] = array();
	$pconfig['extra_servers_ports']['item'] = array(array('type' => 'server', 'name' => '', 'value' => ''));
}

$counter = 0;
$numrows = count($pconfig['extra_servers_ports']['item']) - 1;

foreach ($pconfig['extra_servers_ports']['item'] as $item) {
	$group = new Form_Group(($counter == 0) ? 'Variable':null);
	$group->addClass('repeatable');

	$group->add(new Form_Select(
		'extra_server_or_port' . $counter,
		'Server',
		$item['type'],
		['server' => 'Server', 'port' => 'Port']
	))->setWidth(2)->setHelp($numrows == $counter ? 'Server or Port':null);;

	$group->add(new Form_Input(
		'extra_name'. $counter,
		'Name',
		'text',
		$item['name']
	))->setWidth(3)->setHelp($numrows == $counter ? 'Name':null);

	$group->add(new Form_Input(
		'extra_value'. $counter,
		'Value',
		'text',
		$item['value']
	))->setWidth(3)->setHelp($numrows == $counter ? 'Value':null);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->addClass('btn-warning');

	$section->add($group);

	$counter++;
}

$section->addInput(new Form_Button(
	'addrow',
	'Add',
	null,
	'fa-plus'
))->addClass('btn-success');
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

		foreach ($suricata_servers as $key => $server)
			echo '$("#def_' . $key . '").autocomplete({source: addressarray});' . "\n";

		foreach ($suricata_ports as $key => $server)
			echo '$("#def_' . $key . '").autocomplete({source: portsarray});' . "\n";
	?>
	}

	function show_extra_servers_ports() {
		hide = !$('#enable_extra_servers_ports').prop('checked');
		hideClass('extra_servers_ports', hide);
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------
	// When 'enable_extra_servers_ports' is clicked, show 'extra_servers_ports' list
	$('#enable_extra_servers_ports').click(function () {
		show_extra_servers_ports();
	});

	// ---------- On initial page load ------------------------------------------------------------
	show_extra_servers_ports();
	checkLastRow();
	setTimeout(createAutoSuggest, 500);

});
//]]>
</script>

<?php include("foot.inc"); ?>
