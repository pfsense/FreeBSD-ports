<?php
/*
 * snort_passlist_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009-2010 Robert Zelaya
 * Copyright (c) 2022 Bill Meeks
 * All rights reserved.
 *
 * originially part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("/usr/local/pkg/snort/snort.inc");

$pconfig = array();

// Arbitrary limit for IP or Alias entries per Pass List.
$max_addresses = 1000;

$a_passlist = config_get_path('installedpackages/snortglobal/whitelist/item', []);

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id'])) {
	$id = htmlspecialchars($_GET['id']);
}

/* Should never be called without identifying list index, so bail */
if (is_null($id)) {
	header("Location: /snort/snort_passlist.php");
	exit;
}

if (isset($id) && isset($a_passlist[$id])) {
	/* Retrieve saved settings */
	$pconfig = $a_passlist[$id];
}

// Set defaults for any non-initialized values
if (!isset($pconfig['localnets'])) {
	$pconfig['localnets'] = "yes";
}
if (!isset($pconfig['wangateips'])) {
	$pconfig['wangateips'] = "yes";
}
if (!isset($pconfig['wandnsips'])) {
	$pconfig['wandnsips'] = "yes";
}
if (!isset($pconfig['vips'])) {
	$pconfig['vips'] = "yes";
}
if (!isset($pconfig['vpnips'])) {
	$pconfig['vpnips'] = "yes";
}
if (!is_array($pconfig['address'])) {
	$pconfig['address'] = array();
	$pconfig['address']['item'] = array();
}

/* If no entry for this passlist, then create a UUID and treat it like a new list */
if (!isset($a_passlist[$id]['uuid']) && empty($pconfig['uuid'])) {
	$passlist_uuid = 0;
	while ($passlist_uuid > 65535 || $passlist_uuid == 0) {
		$passlist_uuid = mt_rand(1, 65535);
		$pconfig['uuid'] = $passlist_uuid;
		$pconfig['name'] = "passlist_{$passlist_uuid}";
	}
}
elseif (!empty($pconfig['uuid'])) {
	$passlist_uuid = $pconfig['uuid'];	
}
else
	$passlist_uuid = $a_passlist[$id]['uuid'];

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = array();
	$p_list = array();

	/* input validation */
	$reqdfields = explode(" ", "name");
	$reqdfieldsn = explode(",", "Name");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if(strtolower($_POST['name']) == "defaultpasslist")
		$input_errors[] = gettext("Pass List file names may not be named 'defaultpasslist' as that is a reserved name.");

	/* check for name conflicts */
	foreach ($a_passlist as $k) {
		if (isset($id) && isset($a_passlist[$id]) && ($a_passlist[$id] === $k))
			continue;

		if ($k['name'] == $_POST['name']) {
			$input_errors[] = gettext("A Pass List with this name already exists.");
			break;
		}
	}

	// Iterate and validate the returned $_POST['address'] values (these will be "addressX" where "X" is a number from 0 - 999).
	$addrs = array();
	for ($x = 0; $x < ($max_addresses - 1); $x++) {
		if ($_POST["address{$x}"] <> "") {

			// Verify the entry is a valid IP address, subnet or alias name
			if (is_ipaddroralias($_POST["address{$x}"]) || is_subnet($_POST["address{$x}"])) {
				$addrs[] = $_POST["address{$x}"];
			} else {
				$input_errors[] = gettext("Custom Address entry '" . $_POST["address{$x}"] . "' is not a valid IP address, IP subnet or Alias name!");
				$addrs[] = $_POST["address{$x}"];
			}
		}
	}

	if (!$input_errors) {

		/* post user input */
		$p_list['name'] = $_POST['name'];
		$p_list['uuid'] = $passlist_uuid;
		$p_list['localnets'] = $_POST['localnets']? 'yes' : 'no';
		$p_list['wangateips'] = $_POST['wangateips']? 'yes' : 'no';
		$p_list['wandnsips'] = $_POST['wandnsips']? 'yes' : 'no';
		$p_list['vips'] = $_POST['vips']? 'yes' : 'no';
		$p_list['vpnips'] = $_POST['vpnips']? 'yes' : 'no';
		$p_list['address']['item'] = $addrs;
		$p_list['descr'] = $_POST['descr'];

		if (isset($id) && isset($a_passlist[$id])) {
			$a_passlist[$id] = $p_list;
		} else {
			$a_passlist[] = $p_list;
		}

		$pconfig = $p_list;
		config_set_path('installedpackages/snortglobal/whitelist/item', $a_passlist);
		write_config("Snort pkg: modified PASS LIST {$p_list['name']}.");

		/* create pass list file, then sync file with configured partners */
		sync_snort_package_config();
	} else {
		$pconfig['name'] = $_POST['name'];
		$pconfig['uuid'] = $passlist_uuid;
		$pconfig['localnets'] = $_POST['localnets']? 'yes' : 'no';
		$pconfig['wangateips'] = $_POST['wangateips']? 'yes' : 'no';
		$pconfig['wandnsips'] = $_POST['wandnsips']? 'yes' : 'no';
		$pconfig['vips'] = $_POST['vips']? 'yes' : 'no';
		$pconfig['vpnips'] = $_POST['vpnips']? 'yes' : 'no';
		$pconfig['descr']  =  mb_convert_encoding($_POST['descr'],"HTML-ENTITIES","auto");
		$pconfig['address']['item'] = $addrs;
	}
}

$pglinks = array("", "/snort/snort_interfaces.php", "/snort/snort_passlist.php", "@self");
$pgtitle = array("Services", "Snort", "Pass List", "Edit");
include_once("head.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$tab_array = array();
$tab_array[] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), true, "/snort/snort_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array,true);

$pattern_str = array(	'network' => '[a-zA-Z0-9_:.-]+(/[0-9]+)?( [a-zA-Z0-9_:.-]+(/[0-9]+)?)*',	// Alias Name, Host Name, IP Address, FQDN, Network or IP Address Range
			'host'	  => '[\pL0-9_:.-]+(/[0-9]+)?( [a-zA-Z0-9_:.-]+(/[0-9]+)?)*'		// Alias Name, Host Name, IP Address, FQDN
);
$help = gettext("Enter as many IP addresses or alias names as desired. Enter ONLY an IP address, IP subnet or alias name! Do NOT enter a FQDN (fully qualified domain name) directly! " . 
		"To use a FQDN, first create the necessary firewall alias, and then provide the alias name here. FQDN aliases are periodically re-resolved and updated by the firewall. " . 
		"You can also provide an IP subnet with a proper netmask of the form network/mask such as 1.2.3.0/24.");

$form = new Form();

// Include the Pass List ID in a hidden form field with any $_POST
if (isset($id)) {
	$form->addGlobal(new Form_Input(
		'id',
		'id',
		'hidden',
		$id
	));
}

$section = new Form_Section('General Information');
$section->addInput(new Form_Input(
	'name',
	'Name',
	'text',
	$pconfig['name']
))->setPattern('[a-zA-Z0-9_]+')->setHelp('The list name may only consist of the characters \'a-z, A-Z, 0-9 and _\'.');
$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference.');
$form->add($section);

$section = new Form_Section('Auto-Generated IP Addresses');
$section->addInput(new Form_Checkbox(
	'localnets',
	'Local Networks',
	'Add firewall Locally-Attached Networks to the list (excluding WAN). Default is Checked.',
	$pconfig['localnets'] == 'yes' ? true:false,
	'yes'
));
$section->addInput(new Form_Checkbox(
	'wangateips',
	'WAN Gateways',
	'Add WAN Gateways to the list. Default is Checked.',
	$pconfig['wangateips'] == 'yes' ? true:false,
	'yes'
));
$section->addInput(new Form_Checkbox(
	'wandnsips',
	'WAN DNS Servers',
	'Add WAN DNS servers to the list. Default is Checked.',
	$pconfig['wandnsips'] == 'yes' ? true:false,
	'yes'
));
$section->addInput(new Form_Checkbox(
	'vips',
	'Virtual IP Addresses',
	'Add Virtual IP Addresses to the list. Default is Checked.',
	$pconfig['vips'] == 'yes' ? true:false,
	'yes'
));
$section->addInput(new Form_Checkbox(
	'vpnips',
	'VPN Addresses',
	'Add VPN Addresses to the list. Default is Checked.',
	$pconfig['vpnips'] == 'yes' ? true:false,
	'yes'
));
$form->add($section);

$section = new Form_Section('Custom IP Addresses and Configured Firewall Aliases');

// Make somewhere to park the help text, and give it a class so we can update it later if desired
$section->addInput(new Form_StaticText(
	'Hint',
	'<span class="helptext">' . $help . '</span>'
));

// Iterate any defined IPs or Aliases defined for this list, otherwise
// create a single empty initial empty row.
if (count($pconfig['address']['item']) > 0) {
	$counter = 0;
	while ($counter < count($pconfig['address']['item'])) {
		$group = new Form_Group('IP or Alias');
		$group->addClass('repeatable');

		$group->add(new Form_IpAddress(
			'address' . $counter,
			'Address',
			$pconfig['address']['item'][$counter],
			'ALIASV4V6'
		));

		$group->add(new Form_Button(
			'deleterow' . $counter,
			'Delete',
			null,
			'fa-solid fa-trash-can'
		))->addClass('btn-warning btn-sm nowarn')->setAttribute('title', "Delete this entry from list");

		$section->add($group);
		$counter++;
	}
} else {
	$group = new Form_Group('IP or Alias');
	$group->addClass('repeatable');

	$group->add(new Form_IpAddress(
		'address0',
		'Address',
		'',
		'ALIASV4V6'
	));

	$group->add(new Form_Button(
		'deleterow0',
		'Delete',
		null,
		'fa-solid fa-trash-can'
	))->addClass('btn-warning btn-sm nowarn')->setAttribute('title', "Delete this entry from list");

	$section->add($group);
}
$form->add($section);

$form->addGlobal(new Form_Button(
	'addrow',
	'Add IP',
	null,
	'fa-solid fa-plus'
))->addClass('btn-success addbtn')->setAttribute('title', "Add new IP address, subnet or alias name row");

print($form);
?>

<script type="text/javascript">
//<![CDATA[
// ---------- Autocomplete --------------------------------------------------------------------
var addressarray = <?= json_encode(get_alias_list(array("host", "network"))) ?>;

events.push(function() {

	// Hide and disable all rows >= that specified
	function hideRowsAfter(row, hide) {
		var idx = 0;

		$('.repeatable').each(function(el) {
			if (idx >= row) {
				hideRow(idx, hide);
			}

			idx++;
		});
	}

	function hideRow(row, hide) {
		if (hide) {
			$('#deleterow' + row).parent('div').parent().addClass('hidden');
		} else {
			$('#deleterow' + row).parent('div').parent().removeClass('hidden');
		}

		// We need to disable the elements so they are not submitted in the POST
		$('#address' + row).prop("disabled", hide);
		$('#deleterow' + row).prop("disabled", hide);
	}

	checkLastRow();

	// Autocomplete
	$('[id^=address]').each(function() {
		if (this.id.substring(0, 8) != "address_") {
			$(this).autocomplete({
				source: addressarray
			});
		}
	});
});
//]]>
</script>
<?php include("foot.inc"); ?>

