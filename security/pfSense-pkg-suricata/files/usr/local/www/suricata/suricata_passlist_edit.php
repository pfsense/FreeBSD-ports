<?php
/*
 * suricata_passlist_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2018 Bill Meeks
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

if ($_POST['cancel']) {
	header("Location: /suricata/suricata_passlist.php");
	exit;
}

if (!is_array($config['installedpackages']['suricata']['passlist']))
	$config['installedpackages']['suricata']['passlist'] = array();
if (!is_array($config['installedpackages']['suricata']['passlist']['item']))
	$config['installedpackages']['suricata']['passlist']['item'] = array();
$a_passlist = &$config['installedpackages']['suricata']['passlist']['item'];

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

/* Should never be called without identifying list index, so bail */
if (is_null($id)) {
	header("Location: /suricata/suricata_interfaces_passlist.php");
	exit;
}

if (isset($id) && isset($a_passlist[$id])) {
	/* Retrieve saved settings */
	$pconfig['name'] = $a_passlist[$id]['name'];
	$pconfig['uuid'] = $a_passlist[$id]['uuid'];
	$pconfig['address'] = $a_passlist[$id]['address'];
	$pconfig['descr'] = html_entity_decode($a_passlist[$id]['descr']);
	$pconfig['localnets'] = $a_passlist[$id]['localnets'];
	$pconfig['wanips'] = $a_passlist[$id]['wanips'];
	$pconfig['wangateips'] = $a_passlist[$id]['wangateips'];
	$pconfig['wandnsips'] = $a_passlist[$id]['wandnsips'];
	$pconfig['vips'] = $a_passlist[$id]['vips'];
	$pconfig['vpnips'] = $a_passlist[$id]['vpnips'];
}

if (isset($id) && !isset($a_passlist[$id])) {
	/* This is a new list, so set sensible defaults */
	$pconfig['localnets'] = 'yes';
	$pconfig['wanips'] = 'yes';
	$pconfig['wangateips'] = 'yes';
	$pconfig['wandnsips'] = 'yes';
	$pconfig['vips'] = 'yes';
	$pconfig['vpnips'] = 'yes';
}

// Check for returned "selected alias" if action is import
if ($_GET['act'] == "import") {

	// Retrieve previously typed values we passed to SELECT ALIAS page
	$pconfig['name'] = htmlspecialchars($_GET['name']);
	$pconfig['uuid'] = htmlspecialchars($_GET['uuid']);
	$pconfig['address'] = htmlspecialchars($_GET['address']);
	$pconfig['descr'] = htmlspecialchars($_GET['descr']);
	$pconfig['localnets'] = htmlspecialchars($_GET['localnets'])? 'yes' : 'no';
	$pconfig['wanips'] = htmlspecialchars($_GET['wanips'])? 'yes' : 'no';
	$pconfig['wangateips'] = htmlspecialchars($_GET['wangateips'])? 'yes' : 'no';
	$pconfig['wandnsips'] = htmlspecialchars($_GET['wandnsips'])? 'yes' : 'no';
	$pconfig['vips'] = htmlspecialchars($_GET['vips'])? 'yes' : 'no';
	$pconfig['vpnips'] = htmlspecialchars($_GET['vpnips'])? 'yes' : 'no';

	// Now retrieve the "selected alias" returned from SELECT ALIAS page
	if ($_GET['varname'] == "address" && isset($_GET['varvalue']))
		$pconfig[$_GET['varname']] = htmlspecialchars($_GET['varvalue']);
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

/* returns true if $name is a valid name for a pass list file name or ip */
function is_validpasslistname($name) {
	if (!is_string($name))
		return false;

	if (!preg_match("/[^a-zA-Z0-9\_\.\/]/", $name))
		return true;

	return false;
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "name");
	$reqdfieldsn = explode(",", "Name");

	$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
	if ($pf_version < 2.1)
		$input_errors = eval('do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors); return $input_errors;');
	else
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if(strtolower($_POST['name']) == "defaultpasslist")
		$input_errors[] = gettext("Pass List file names may not be named defaultpasslist.");

	if (is_validpasslistname($_POST['name']) == false)
		$input_errors[] = gettext("Pass List file name may only consist of the characters \"a-z, A-Z, 0-9 and _\". Note: No Spaces or dashes. Press Cancel to reset.");

	/* check for name conflicts */
	foreach ($a_passlist as $p_list) {
		if (isset($id) && ($a_passlist[$id]) && ($a_passlist[$id] === $p_list))
			continue;

		if ($p_list['name'] == $_POST['name']) {
			$input_errors[] = gettext("A Pass List file name with this name already exists.");
			break;
		}
	}

	if ($_POST['address']) {
		if (!is_alias($_POST['address']))
			$input_errors[] = gettext("A valid alias must be provided");
		if (is_alias($_POST['address']) && trim(filter_expand_alias($_POST['address'])) == "")
			$input_errors[] = gettext("FQDN aliases are not supported in Suricata.");
	}
	if (!$input_errors) {
		$p_list = array();
		/* post user input */
		$p_list['name'] = $_POST['name'];
		$p_list['uuid'] = $passlist_uuid;
		$p_list['localnets'] = $_POST['localnets']? 'yes' : 'no';
		$p_list['wanips'] = $_POST['wanips']? 'yes' : 'no';
		$p_list['wangateips'] = $_POST['wangateips']? 'yes' : 'no';
		$p_list['wandnsips'] = $_POST['wandnsips']? 'yes' : 'no';
		$p_list['vips'] = $_POST['vips']? 'yes' : 'no';
		$p_list['vpnips'] = $_POST['vpnips']? 'yes' : 'no';

		$p_list['address'] = $_POST['address'];
		$p_list['descr']  =  mb_convert_encoding(str_replace("\r\n", "\n", $_POST['descr']),"HTML-ENTITIES","auto");
		$p_list['detail'] = $final_address_details;

		if (isset($id) && $a_passlist[$id])
			$a_passlist[$id] = $p_list;
		else
			$a_passlist[] = $p_list;

		write_config("Suricata pkg: modified PASS LIST {$p_list['name']}.");

		/* create pass list and homenet file, then sync files */
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();

		header("Location: /suricata/suricata_passlist.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Pass List Edit"));
include_once("head.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), true, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$instanceid}");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);

$form = new Form(FALSE);
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
	'Add firewall Locally-Attached Networks to the list (excluding WAN).  Default is checked (but see warning below).',
	$pconfig['localnets'] == 'yes' ? true:false,
	'yes'
))->setHelp('If creating a custom HOME_NET list, then this box should usually be checked.');
$section->addInput(new Form_Checkbox(
	'wanips',
	'WAN IP',
	'Add WAN interface IP to the list.  Default is checked).',
	$pconfig['wanips'] == 'yes' ? true:false,
	'yes'
))->setHelp('If creating a custom HOME_NET list and using NAT, then this box should usually be checked.');
$section->addInput(new Form_Checkbox(
	'wangateips',
	'WAN Gateways',
	'Add WAN Gateways to the list.  Default is checked.',
	$pconfig['wangateips'] == 'yes' ? true:false,
	'yes'
));
$section->addInput(new Form_Checkbox(
	'wandnsips',
	'WAN DNS Servers',
	'Add WAN DNS servers to the list.  Default is checked.',
	$pconfig['wandnsips'] == 'yes' ? true:false,
	'yes'
));
$section->addInput(new Form_Checkbox(
	'vips',
	'Virtual IP Addresses',
	'Add Virtual IP Addresses to the list.  Default is checked.',
	$pconfig['vips'] == 'yes' ? true:false,
	'yes'
));
$section->addInput(new Form_Checkbox(
	'vpnips',
	'VPN Addresses',
	'Add VPN Addresses to the list.  Default is checked.',
	$pconfig['vpnips'] == 'yes' ? true:false,
	'yes'
))->setHelp('If creating a custom HOME_NET list, then this box should usually be checked.');
$form->add($section);

$section = new Form_Section('Custom IP Address from Configured Alias');
$group = new Form_Group('Assigned Alias');
$group->add(new Form_Input(
	'address',
	'Assigned Alias',
	'text',
	$pconfig['address']
))->setHelp('Enter the name of an existing Alias in order to further customize IP addresses included on this Pass List.')->setAttribute('title', trim(filter_expand_alias($pconfig['address'])));
$group->add(new Form_Button(
	'btnSelectAlias',
	'Aliases',
	'javascript:selectAlias();',
	'fa-search-plus'
))->removeClass('btn-default')->addClass('btn-sm btn-success')->setAttribute('title', gettext('View and select from available aliases'));
$section->add($group);
$form->add($section);

$section = new Form_Section('');
$btnsave = new Form_Button(
	'save',
	'Save',
	null,
	'fa-save'
);
$btncancel = new Form_Button(
	'cancel',
	'Cancel'
);
$btnsave->addClass('btn-primary')->addClass('btn-default');
$btncancel->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-warning');

$section->addInput(new Form_StaticText(
	null,
	$btnsave . $btncancel
));
$form->add($section);

// Include the Pass List ID in a hidden form field with any $_POST
if (isset($id)) {
	$form->addGlobal(new Form_Input(
		'id',
		'id',
		'hidden',
		$id
	));
}

print($form);
?>

<script type="text/javascript">
//<![CDATA[

function selectAlias() {

	var loc;
	var fields = [ "name", "descr", "localnets", "wanips", "wangateips", "wandnsips", "vips", "vpnips", "address" ];

	// Scrape current form field values and add to
	// the select alias URL as a query string.
	var loc = '/suricata/suricata_select_alias.php?id=<?=$id;?>&act=import&type=host|network';
	loc = loc + '&varname=address&multi_ip=yes';
	loc = loc + '&returl=<?=urlencode($_SERVER['PHP_SELF']);?>';
	loc = loc + '&uuid=<?=$passlist_uuid;?>';

	// Iterate over just the specific form fields we want to pass to
	// the select alias URL.
	fields.forEach(function(entry) {
		var tmp = $('#' + entry).serialize();
		if (tmp.length > 0)
			loc = loc + '&' + tmp;
	});
	
	window.parent.location = loc; 
}

events.push(function() {

	// ---------- Autocomplete --------------------------------------------------------------------

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn"))) ?>;

	$('#address').autocomplete({
		source: addressarray
	});
});
//]]>
</script>
<?php include("foot.inc"); ?>

