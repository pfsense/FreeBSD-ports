<?php
/*
 * suricata_define_vars.php
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

//require_once("globals.inc");
require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g, $rebuild_rules;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);
if (!is_numericint($id)) {
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

	// Build custom variables
	$custom_variables = [];
	foreach (array_keys($_POST) as $key) {
		if (!str_starts_with($key, 'custom_vars_item_type')) {
			continue;
		}
		$matches = [];
		if (!preg_match('/^custom_vars_item_type(?P<index>[[:digit:]]+)$/', $key, $matches)) {
			continue;
		};
		$custom_variable = [
			'type' => $_POST["custom_vars_item_type{$matches['index']}"],
			'name' => array_key_exists("custom_vars_item_name{$matches['index']}", $_POST) ? strtolower($_POST["custom_vars_item_name{$matches['index']}"]) : null,
			'value' => array_key_exists("custom_vars_item_value{$matches['index']}", $_POST) ? $_POST["custom_vars_item_value{$matches['index']}"] : null,
		];
		if (!in_array($custom_variable['type'], ['server', 'port']) ||
		    empty($custom_variable['name']) || empty($custom_variable['value'])) {
			continue;
		}
		$custom_variables[] = $custom_variable;
	}
	if (!empty($custom_variables)) {
		array_set_path($natent, 'custom_vars/item', $custom_variables);
	}

	// Input validation for custom variables
	$all_variables = $suricata_servers + $suricata_ports;
	foreach ($custom_variables as $item) {
		if (isset($all_variables[$item['name']])) {
			$input_errors[] = "The variable '{$item['name']}' is already defined.";
			continue;
		}
		if (preg_match("/^[[:word:]]$/", $item['name'])) {
			$input_errors[] = gettext('Custom variable names may only contain the characters A-Z, 0-9 and "_".');
		}
		if (!is_alias($item['value'])) {
			$input_errors[] = "Only aliases are allowed";
		} elseif (empty(trim(filter_expand_alias($item['value'])))) {
			$input_errors[] = "FQDN aliases are not allowed for IP variables in Suricata.";
		}
	}

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
	))->setHelp('Default value: ' . (!empty($server) ? " {$server}. Leave blank for default value." : 'not used.'));
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
	))->setHelp('Default value: ' . (!empty($server) ? " {$server}. Leave blank for default value." : 'not used.'));

}
$form->add($section);

$section = new Form_Section('Custom Variables');
$section->addClass('custom_vars');

if (empty(array_get_path($pconfig, 'custom_vars/item'))) {
	$row_last = 0;
	$pconfig['custom_vars'] = [
		'item' => [[
			'type' => 'server',
			'name' => '',
			'value' => ''
		]]
	];
} else {
	$row_last = count($pconfig['custom_vars']['item']) - 1;
}

$row_index = 0;
foreach (array_get_path($pconfig, 'custom_vars/item', []) as $item) {
	$group = new Form_Group((($row_index == 0) ? 'Variable' : null));
	$group->addClass('repeatable');

	$group->add(new Form_Select(
		'custom_vars_item_type' . $row_index,
		'Variable Type',
		$item['type'],
		[
			'server' => 'Server',
			'port' => 'Port'
		]
	))->setWidth(2)->setHelp((($row_index == $row_last) ? 'Type' : null));

	$group->add(new Form_Input(
		'custom_vars_item_name'. $row_index,
		'Variable Name',
		'text',
		$item['name']
	))->setWidth(3)->setHelp((($row_index == $row_last) ? 'Name' : null));

	$group->add(new Form_Input(
		'custom_vars_item_value'. $row_index,
		'Variable Value',
		'text',
		$item['value']
	))->setWidth(3)->setHelp((($row_index == $row_last) ? 'Value' : null));

	$group->add(new Form_Button(
		'deleterow' . $row_index,
		'Delete',
		null,
		'fa-solid fa-trash-can'
	))->addClass('btn-sm btn-warning');

	$section->add($group);
	$row_index++;
}
$section->addInput(new Form_Button(
	'addrow',
	'Add',
	null,
	'fa-solid fa-plus'
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

	checkLastRow();
	setTimeout(createAutoSuggest, 500);

});
//]]>
</script>

<?php include("foot.inc"); ?>
