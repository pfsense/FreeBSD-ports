<?php
/*
 * haproxy_listeners_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2015 PiBa-NL
 * Copyright (c) 2008 Remco Hoef <remcoverhoef@pfsense.com>
 * Copyright (c) 2013 Marcello Coutinho <marcellocoutinho@gmail.com>
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

$shortcut_section = "haproxy";
require("guiconfig.inc");
require_once("haproxy/haproxy.inc");
require_once("haproxy/haproxy_utils.inc");
require_once("haproxy/haproxy_htmllist.inc");
require_once("haproxy/pkg_haproxy_tabs.inc");

/* Compatibility function for pfSense 2.0 */
if (!function_exists("cert_get_purpose")) {
	function cert_get_purpose(){
		$result = array();
		$result['server'] = "Yes";
		return $result;
	}
}
/**/

haproxy_config_init();

$a_pools = config_get_path('installedpackages/haproxy/ha_pools/item', []);
uasort($a_pools, 'haproxy_compareByName');

global $simplefields;
$simplefields = array('name','descr','status','secondary','primary_frontend','type','forwardfor','httpclose','extaddr','backend_serverpool',
	'max_connections','client_timeout','port','advanced_bind',
	'ssloffloadcert','sslsnifilter','ssl_crtlist_advanced','dcertadv','ssloffload','ssloffloadacl','ssloffloadacl_an','ssloffloadacladditional','ssloffloadacladditional_an',
	'sslclientcert-none','sslclientcert-invalid','sslocsp',
	'socket-stats',
	'dontlognull','dontlog-normal','log-separate-errors','log-detailed');

if (isset($_POST['id'])) {
	$id = $_POST['id'];
} else {
	$id = $_GET['id'];
}

if (isset($_GET['dup'])) {
	$id = $_GET['dup'];
}

$id = get_frontend_id($id);

if (!is_numeric($id))
{
	//default value for new items.
	$pconfig['ssloffloadacl_an'] = "yes";
	$new_item = array();
	$new_item['extaddr'] = "wan_ipv4";
	$new_item['extaddr_port'] = "80";
	$pconfig['a_extaddr'][] = $new_item;
}

$listitem_none['']['name']="None";

// <editor-fold desc="HtmlList field definitions">
$servercerts = haproxy_get_certificates('server,user');
$fields_sslCertificates=array();
$fields_sslCertificates[0]['name']="ssl_certificate";
$fields_sslCertificates[0]['columnheader']="Certificates";
$fields_sslCertificates[0]['colwidth']="95%";
$fields_sslCertificates[0]['type']="select";
$fields_sslCertificates[0]['size']="500px";
$fields_sslCertificates[0]['items']=&$servercerts;

$certs_ca = haproxy_get_certificates('ca');
$fields_caCertificates=array();
$fields_caCertificates[0]['name']="cert_ca";
$fields_caCertificates[0]['columnheader']="Certificates authorities";
$fields_caCertificates[0]['colwidth']="95%";
$fields_caCertificates[0]['type']="select";
$fields_caCertificates[0]['size']="500px";
$fields_caCertificates[0]['items']=&$certs_ca;

$certs_crl = haproxy_get_crls();
//$ca_none['']['name']="None";
//$certs_crl = $ca_none + $certs_crl;
$fields_crlCertificates=array();
$fields_crlCertificates[0]['name']="cert_crl";
$fields_crlCertificates[0]['columnheader']="Certificate revocation lists";
$fields_crlCertificates[0]['colwidth']="95%";
$fields_crlCertificates[0]['type']="select";
$fields_crlCertificates[0]['size']="500px";
$fields_crlCertificates[0]['items']=&$certs_crl;

$fields_aclSelectionList=array();
$fields_aclSelectionList[0]['name']="name";
$fields_aclSelectionList[0]['columnheader']="Name";
$fields_aclSelectionList[0]['colwidth']="30%";
$fields_aclSelectionList[0]['type']="textbox";
$fields_aclSelectionList[0]['size']="20";

$fields_aclSelectionList[1]['name']="expression";
$fields_aclSelectionList[1]['columnheader']="Expression";
$fields_aclSelectionList[1]['colwidth']="30%";
$fields_aclSelectionList[1]['type']="select";
$fields_aclSelectionList[1]['size']="10";
$fields_aclSelectionList[1]['items']=&$a_acltypes;

$fields_aclSelectionList[2]['name']="casesensitive";
$fields_aclSelectionList[2]['columnheader']="CS";
$fields_aclSelectionList[2]['colwidth']="5%";
$fields_aclSelectionList[2]['type']="checkbox";
$fields_aclSelectionList[2]['size']="5";

$fields_aclSelectionList[3]['name']="not";
$fields_aclSelectionList[3]['columnheader']="Not";
$fields_aclSelectionList[3]['colwidth']="5%";
$fields_aclSelectionList[3]['type']="checkbox";
$fields_aclSelectionList[3]['size']="5";

$fields_aclSelectionList[4]['name']="value";
$fields_aclSelectionList[4]['columnheader']="Value";
$fields_aclSelectionList[4]['colwidth']="35%";
$fields_aclSelectionList[4]['type']="textbox";
$fields_aclSelectionList[4]['size']="35";

$interfaces = haproxy_get_bindable_interfaces();
$interfaces_custom['custom']['name']="Use custom address:";
$interfaces = $interfaces_custom + $interfaces;

$fields_externalAddress=array();
$fields_externalAddress[0]['name']="extaddr";
$fields_externalAddress[0]['columnheader']="Listen address";
$fields_externalAddress[0]['colwidth']="25%";
$fields_externalAddress[0]['type']="select";
$fields_externalAddress[0]['size']="200px";
$fields_externalAddress[0]['items']=&$interfaces;
$fields_externalAddress[0]['maxwidth']="200px";
$fields_externalAddress[1]['name']="extaddr_custom";
$fields_externalAddress[1]['columnheader']="Custom address";
$fields_externalAddress[1]['colwidth']="25%";
$fields_externalAddress[1]['type']="textbox";
$fields_externalAddress[1]['size']="30";
$fields_externalAddress[2]['name']="extaddr_port";
$fields_externalAddress[2]['columnheader']="Port";
$fields_externalAddress[2]['colwidth']="5%";
$fields_externalAddress[2]['type']="textbox";
$fields_externalAddress[2]['size']="5";
$fields_externalAddress[3]['name']="extaddr_ssl";
$fields_externalAddress[3]['columnheader']="SSL Offloading";
$fields_externalAddress[3]['colwidth']="10%";
$fields_externalAddress[3]['type']="checkbox";
$fields_externalAddress[3]['size']="50px";
$fields_externalAddress[4]['name']="extaddr_advanced";
$fields_externalAddress[4]['columnheader']="Advanced";
$fields_externalAddress[4]['colwidth']="20%";
$fields_externalAddress[4]['type']="textbox";
$fields_externalAddress[4]['size']="30";

foreach ($a_action as $key => $value) {
	if (!empty($value['usage']) && !stristr('frontend', $value['usage'])) {
		unset($a_action[$key]);
	}
}
$fields_actions=array();
$fields_actions[0]['name']="action";
$fields_actions[0]['columnheader']="Action";
$fields_actions[0]['colwidth']="30%";
$fields_actions[0]['type']="select";
$fields_actions[0]['size']="200px";
$fields_actions[0]['items']=&$a_action;
$fields_actions[1]['name']="parameters";
$fields_actions[1]['columnheader']="Parameters";
$fields_actions[1]['colwidth']="30%";
$fields_actions[1]['type']="fixedtext";
$fields_actions[1]['size']="200px";
$fields_actions[1]['text']="See below";
$fields_actions[2]['name']="acl";
$fields_actions[2]['columnheader']="Condition acl names";
$fields_actions[2]['colwidth']="15%";
$fields_actions[2]['type']="textbox";
$fields_actions[2]['size']="40";

$a_files = haproxy_get_fileslist();
$fields_errorfile = array();
$fields_errorfile[0]['name']="errorcode";
$fields_errorfile[0]['columnheader']="errorcode(s)";
$fields_errorfile[0]['colwidth']="15%";
$fields_errorfile[0]['type']="textbox";
$fields_errorfile[0]['size']="70px";
$fields_errorfile[1]['name']="errorfile";
$fields_errorfile[1]['columnheader']="Error Page";
$fields_errorfile[1]['colwidth']="30%";
$fields_errorfile[1]['type']="select";
$fields_errorfile[1]['size']="170px";
$fields_errorfile[1]['items']=&$a_files;

// </editor-fold>

$backends = get_haproxy_backends();
$a_action['use_backend']['fields']['backend']['items'] = &$backends;
//$a_action['http-request_lua']['fields']['lua-script']['items'] = &$a_files;
//$a_action['tcp-request_content_lua']['fields']['lua-script']['items'] = &$a_files;

$fields_actions_details=array();
foreach($a_action as $key => $action) {
	if (is_array($action['fields'])) {
		foreach($action['fields'] as $field) {
			$item = $field;
			$name = $key . $item['name'];
			$item['name'] = $name;
			$item['columnheader'] = $field['name'];
			$item['customdrawcell'] = 'customdrawcell_actions';
			$fields_actions_details[$name] = $item;
		}
	}
}

$a_acltypes["backendservercount"]['fields']['backend']['items'] = &$backends;
$fields_acl_details=array();
foreach($a_acltypes as $key => $action) {
	if (is_array($action['fields'])) {
		foreach($action['fields'] as $field) {
			$item = $field;
			$name = $key . $item['name'];
			$item['name'] = $name;
			$item['columnheader'] = $field['columnheader'];
			$item['customdrawcell'] = 'customdrawcell_actions';
			$fields_acl_details[$name] = $item;
		}
	}
}

function customdrawcell_actions($object, $item, $itemvalue, $editable, $itemname, $counter) {
	$result = "";
	if ($editable) {
		$result = $object->haproxy_htmllist_drawcell($item, $itemvalue, $editable, $itemname, $counter);
	} else {
		$result = htmlspecialchars($itemvalue, ENT_QUOTES);
	}
	return $result;
}

$htmllist_extaddr = new HaproxyHtmlList("table_extaddr", $fields_externalAddress);
$htmllist_extaddr->editmode = true;

function fields_details_showfieldfunction($items, $action,  $itemname) {
	if (is_array($items[$action]) && is_array($items[$action]['fields'])) {
		foreach($items[$action]['fields'] as $item) {
			if ($action . "" . $item['name'] == $itemname) {
				return true;
			}
		}
	}
	return false;
}
function fields_acls_details_showfieldfunction($htmltable, $itemname, $values) {
	$items = $htmltable->fields[1]['items'];
	$action = $values['expression'];
	return fields_details_showfieldfunction($items, $action, $itemname);
}
$htmllist_acls = new HaproxyHtmlList("table_acls", $fields_aclSelectionList);
$htmllist_acls->fields_details = $fields_acl_details;
$htmllist_acls->fields_details_showfieldfunction = 'fields_acls_details_showfieldfunction';

function fields_actions_details_showfieldfunction($htmltable, $itemname, $values) {
	$items = $htmltable->fields[0]['items'];
	$action = $values['action'];
	return fields_details_showfieldfunction($items, $action, $itemname);
}
$htmllist_actions = new HaproxyHtmlList("table_actions", $fields_actions);
$htmllist_actions->fields_details = $fields_actions_details;
$htmllist_actions->fields_details_showfieldfunction = 'fields_actions_details_showfieldfunction';

$htmllist_sslCertificates = new HaproxyHtmlList("tbl_sslCerts", $fields_sslCertificates);
$htmllist_caCertificates = new HaproxyHtmlList("tbl_caCerts", $fields_caCertificates );
$htmllist_crlCertificates = new HaproxyHtmlList("tbl_crlCerts", $fields_crlCertificates);

$errorfileslist = new HaproxyHtmlList("table_errorfile", $fields_errorfile);
$errorfileslist->keyfield = "errorcode";

if (isset($id) && config_get_path("installedpackages/haproxy/ha_backends/item/{$id}")) {
	$pconfig['a_acl'] = config_get_path("installedpackages/haproxy/ha_backends/item/{$id}/ha_acls/item");
	$pconfig['a_certificates'] = config_get_path("installedpackages/haproxy/ha_backends/item/{$id}/ha_certificates/item");
	$pconfig['clientcert_ca'] = config_get_path("installedpackages/haproxy/ha_backends/item/{$id}/clientcert_ca/item");
	$pconfig['clientcert_crl'] = config_get_path("installedpackages/haproxy/ha_backends/item/{$id}/clientcert_crl/item");
	$pconfig['a_extaddr'] = config_get_path("installedpackages/haproxy/ha_backends/item/{$id}/a_extaddr/item");
	$pconfig['a_actionitems'] = config_get_path("installedpackages/haproxy/ha_backends/item/{$id}/a_actionitems/item");
	$pconfig['a_errorfiles'] = config_get_path("installedpackages/haproxy/ha_backends/item/{$id}/a_errorfiles/item");

	$pconfig['advanced'] = base64_decode(config_get_path("installedpackages/haproxy/ha_backends/item/{$id}/advanced"));
	foreach($simplefields as $stat) {
		$pconfig[$stat] = config_get_path("installedpackages/haproxy/ha_backends/item/{$id}/{$stat}");
	}
}

if (isset($_GET['dup'])) {
	unset($id);
	$pconfig['name'] .= "-copy";
	if ($pconfig['secondary'] != 'yes') {
		$pconfig['primary_frontend'] = $pconfig['name'];
	}
}

$changedesc = "Services: HAProxy: Frontend";
$changecount = 0;

if ($_POST) {
	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;

	if ($pconfig['secondary'] != "yes") {
		$reqdfields = explode(" ", "name type");
		$reqdfieldsn = explode(",", "Name,Type");
	} else {
		$reqdfields = explode(" ", "name");
		$reqdfieldsn = explode(",", "Name");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['name'])) {
		$input_errors[] = gettext("The field 'Name' contains invalid characters.");
	}

	if ($pconfig['secondary'] != "yes") {
		if ($_POST['max_connections'] && !is_numeric($_POST['max_connections'])) {
			$input_errors[] = sprintf(gettext("The value '%s' in field 'Max connections' is not a number."), htmlspecialchars($_POST['max_connections']));
		}

		$ports = explode(",", $_POST['port'] . ",");
		foreach($ports as $port) {
			if ($port && !is_numeric($port) && !is_port_or_alias($port)) {
				$input_errors[] = sprintf(gettext("The value '%s' in field 'Port' is not a number or alias thereof."), htmlspecialchars($port));
			}
		}

		if (!empty($_POST['client_timeout']) && !is_numeric($_POST['client_timeout'])) {
			$input_errors[] = sprintf(gettext("The value '%s' in field 'Client timeout' is not a number."), htmlspecialchars($_POST['client_timeout']));
		}
	}

	/* Ensure that our pool names are unique */
	$a_frontends = config_get_path('installedpackages/haproxy/ha_backends/item', []);
	for ($i=0; isset($a_frontends[$i]); $i++) {
		if (($_POST['name'] == $a_frontends[$i]['name']) && ($i != $id)) {
			$input_errors[] = gettext("This frontend name has already been used. Frontend names must be unique.")." $i != $id";
		}
	}

	$a_actionitems = $htmllist_actions->haproxy_htmllist_get_values();
	$pconfig['a_actionitems'] = $a_actionitems;
	$a_errorfiles = $errorfileslist->haproxy_htmllist_get_values();
	$pconfig['a_errorfiles'] = $a_errorfiles;
	$a_certificates = $htmllist_sslCertificates->haproxy_htmllist_get_values();
	$pconfig['a_certificates'] = $a_certificates;
	$a_clientcert_ca = $htmllist_caCertificates->haproxy_htmllist_get_values();
	$pconfig['clientcert_ca'] = $a_clientcert_ca;
	$a_clientcert_crl = $htmllist_crlCertificates->haproxy_htmllist_get_values();
	$pconfig['clientcert_crl'] = $a_clientcert_crl;

	$a_acl = $htmllist_acls->haproxy_htmllist_get_values();
	$pconfig['a_acl'] = $a_acl;

	$a_extaddr = $htmllist_extaddr->haproxy_htmllist_get_values();
	$pconfig['a_extaddr'] = $a_extaddr;

	foreach($a_acl as $acl) {
		$acl_name = $acl['name'];
		$acl_value = $acl['value'];

		$acltype = haproxy_find_acl($acl['expression']);
		if (preg_match("/[^a-zA-Z0-9\.\-_]/", $acl_name)) {
			$input_errors[] = sprintf(gettext("The acl field 'Name' with value '%s' contains invalid characters."), $acl_name);
		}

		if (!isset($acltype['novalue'])) {
			if (!preg_match("/.{1,}/", $acl_value)) {
				$input_errors[] = sprintf(gettext("The acl field 'Value' for acl '%s' is required."), $acl_name);
			}
		}

		if (!preg_match("/.{2,}/", $acl_name)) {
			$input_errors[] = gettext("The acl field 'Name' is required with at least 2 characters.");
		}
	}
	if ($pconfig['secondary'] != "yes") {
		foreach($a_extaddr as $extaddr) {
			$ports = explode(",",$extaddr['extaddr_port']);
			foreach($ports as $port){
				if ($port && !is_numeric($port) && !is_port_or_alias($port)) {
					$input_errors[] = sprintf(gettext("The external address field 'Port' value '%s' is not a number or alias thereof."), htmlspecialchars($port));
				}
			}

			if ($extaddr['extaddr'] == 'custom') {
				$extaddr_custom = $extaddr['extaddr_custom'];
				if (empty($extaddr_custom) || (!is_ipaddroralias($extaddr_custom))) {
					$input_errors[] = sprintf(gettext("The external address '%s' is not a valid source IP address or alias."), $extaddr_custom);
				}
			}
		}
	}
	if (!$input_errors) {
		$backends_config = config_get_path('installedpackages/haproxy/ha_backends', []);
		if (isset($id)) {
			array_init_path($backends_config, "item/{$id}");
			$backend = &$backends_config['item'][$id];
			$changedesc .= " modified '{$backend['name']}' pool:";
		} else {
			$backends_config['item'][] = [];
			$backend = &$backends_config['item'][array_key_last($backends_config['item'])];
			$backend['name'] = $_POST['name'];
		}

		// update references to this primary frontend
		if ($backend['name'] != $_POST['name']) {
			foreach($backends_config['item'] as &$frontend_config) {
				if (array_get_path($frontend_config, 'primary_frontend') != $backend['name']) {
					continue;
				}
				$frontend_config['primary_frontend'] = $_POST['name'];
			}
		}

		foreach($simplefields as $stat) {
			update_if_changed($stat, $backend[$stat], $_POST[$stat]);
			if (isset($backend[$stat]) && ($backend[$stat] != 0) && empty($backend[$stat])) {
				unset($backend[$stat]);
			}
		}

		update_if_changed("advanced", $backend['advanced'], base64_encode($_POST['advanced']));

		if (!empty($a_acl)) {
			array_set_path($backend,'ha_acls/item', $a_acl);
		} else {
			array_del_path($backend,'ha_acls/item');
		}
		if (!empty($a_certificates)) {
			array_set_path($backend,'ha_certificates/item', $a_certificates);
		} else {
			array_del_path($backend,'ha_certificates/item');
		}
		if (!empty($a_clientcert_ca)) {
			array_set_path($backend,'clientcert_ca/item', $a_clientcert_ca);
		} else {
			array_del_path($backend,'clientcert_ca/item');
		}
		if (!empty($a_clientcert_crl)) {
			array_set_path($backend,'clientcert_crl/item', $a_clientcert_crl);
		} else {
			array_del_path($backend,'clientcert_crl/item');
		}
		if (!empty($a_extaddr)) {
			array_set_path($backend,'a_extaddr/item', $a_extaddr);
		} else {
			array_del_path($backend,'a_extaddr/item');
		}
		if (!empty($a_actionitems)) {
			array_set_path($backend,'a_actionitems/item', $a_actionitems);
		} else {
			array_del_path($backend,'a_actionitems/item');
		}
		if (!empty($a_errorfiles)) {
			array_set_path($backend,'a_errorfiles/item', $a_errorfiles);
		} else {
			array_del_path($backend,'a_errorfiles/item');
		}

		config_set_path('installedpackages/haproxy/ha_backends', $backends_config);

		if ($changecount > 0) {
			touch($d_haproxyconfdirty_path);
			write_config($changedesc);
		}

		header("Location: haproxy_listeners.php");
		exit;
	}
}

$closehead = false;
$pgtitle = array("Services", "HAProxy", "Frontend", "Edit");
$pglinks = array("", "", "/haproxy/haproxy_listeners.php", "@self");
include("head.inc");
haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "frontend");

$counter = 0;

if (!isset($_GET['dup'])) {
	$excludefrontend = $pconfig['name'];
}
$primaryfrontends = get_haproxy_frontends($excludefrontend);

?>
  <style type="text/css">
	.haproxy_mode_http{display:none;}
	.haproxy_ssloffloading_show{display:none;}
	.haproxy_ssloffloading_enabled{display:none;}
	.haproxy_ssl_advanced{display:none;}
	.haproxy_primary{}
	.haproxy_secondary{display:none;}
  </style>
</head>

<script type="text/javascript">
	<?php haproxy_js_css(); ?>

	function htmllist_get_select_options(tableId, fieldname, itemstable) {
		if (tableId === 'table_acls' && fieldname === 'expression') {
			var type;
			var secondary = d.getElementById("secondary");
			var primary_frontend = d.getElementById("primary_frontend");
			if ((secondary !== null) && (secondary.checked))
				type = primaryfrontends[primary_frontend.value]['ref']['type'];
			else
				type = d.getElementById("type").value;

			result = Object.create(null);
			for (var key in itemstable) {
				newitem = itemstable[key];
				if (newitem['mode'] === type || newitem['mode'] === "") {
					result[key] = newitem;
				}
			}
			return result;
		}
		return itemstable;
	}

	function updatevisibility()	{
		d = document;
		ssl = false;
		sslshow = false;
		ssloffload = d.getElementById("ssloffload");
		var type;
		var primary;
		var secondary = d.getElementById("secondary");
		var primary_frontend = d.getElementById("primary_frontend");
		var sslsnifilter = d.getElementById("sslsnifilter");
		if ((secondary !== null) && (secondary.checked)) {
			primary = primaryfrontends[primary_frontend.value];
			type = primary['ref']['type'];
			for (i = 0; i < 99; i++) {
				if (primary['ref']['a_extaddr']['item'][i] && primary['ref']['a_extaddr']['item'][i]['extaddr_ssl'] === 'yes')
					sslshow = true;//ssloffload.checked;
					ssl = ssloffload.checked;
			}
		} else {
			type = d.getElementById("type").value;
			for (i = 0; i < 99; i++) {
				customEdit = document.getElementById("table_extaddr"+"extaddr_ssl"+i);
				if (customEdit && customEdit.checked)
					sslshow = true;
			}
			ssl = sslshow;
		}

		setCSSdisplay(".haproxy_ssloffloading_show", sslshow);
		setCSSdisplay(".haproxy_ssloffloading_enabled", ssl);
		setCSSdisplay(".haproxy_mode_http", type === "http");
		var issecondary = false;
		var hassnifilter = false;
		if (secondary !== null) {
			issecondary = secondary.checked;
			setCSSdisplay(".haproxy_primary", !secondary.checked);
			setCSSdisplay(".haproxy_secondary", secondary.checked);
			hassnifilter = sslsnifilter.value != '';
		}
		//setCSSdisplay(".haproxy_ssl_advanced", ssl && (!issecondary || hassnifilter));

		type_change(type);

		http_close = d.getElementById("httpclose").value;
		http_close_description = d.getElementById("http_close_description");
		http_close_description.innerHTML=closetypes[http_close]["descr"];
		http_close_description.setAttribute('style','padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000; font-size: 8pt; height:30px');
		http_close_description.setAttribute('style','padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000; font-size: 8pt; height:'+http_close_description.scrollHeight+'px');
	}

	function type_change(type) {
		var d, i, j, el, row;
		var count = <?=count($a_acltypes);?>;
		var acl = [ <?php foreach ($a_acltypes as $key => $expr) { echo "'".$key."',"; } ?> ];
		var mode = [ <?php foreach ($a_acltypes as $key => $expr) { echo "'".$expr['mode']."',"; } ?> ];

        d = document;
		for (i = 0; i < 99; i++) {
			el = d.getElementById("table_acls" + "expression" + i);
			row_v = d.getElementById("tr_view_" + i);
			row_e = d.getElementById("tr_edit_" + i);
			row_vd = d.getElementById("tr_viewdetail_" + i);
			if (!el || !row_e)
				continue;
			for (j = 0; j < count; j++) {
				if (acl[j] === el.value) {
					if (mode[j] !== '' && mode[j] !== type) {
						hideElement(row_e, true);
						hideElement(row_v, true);
						hideElement(row_vd, true);
					} else {
						if (!row_v || (row_v && $(row_v).hasClass("hidden"))) {
							// only make the edit row appear if the view row is not still on the screen.
							// (when switching frontend types)
							hideElement(row_e, false);
						}
						hideElement(row_vd, false);
					}
				}
			}
		}
	}
</script>
<?php
if ($input_errors) {
	print_input_errors($input_errors);
}
$form = new Form;

$section = new Form_Section_class("Edit HAProxy Frontend");

$activedisable = array();
$activedisable['active'] = "Active";
$activedisable['disabled'] = "Disabled";

if ($pconfig['status'] == 'disable') {
	$pconfig['status'] = 'disabled';// make checkmark in overview and editpage the same.
}

$section->addInput(new Form_Input('name', 'Name', 'text', $pconfig['name']));
$section->addInput(new Form_Input('descr', 'Description', 'text', $pconfig['descr']));

$section->addInput(new Form_Select(
	'status',
	'Status',
	$pconfig['status'],
	$activedisable
));

if (count($primaryfrontends) > 0){
	$section->addInput(new Form_Checkbox(
		'secondary',
		'Shared Frontend',
		'This can be used to host a second or more website on the same IP:Port combination.',
		$pconfig['secondary']
	))->setHelp("Use this setting to configure multiple backends/accesslists for a single frontend.<br/>
		All settings of which only 1 can exist will be hidden.<br/>
		The frontend settings will be merged into 1 set of frontend configuration.");
}

$section->addInput(new Form_Select(
	'primary_frontend',
	'Primary frontend',
	$pconfig['primary_frontend'],
	haproxy_keyvalue_array($primaryfrontends)
),"haproxy_secondary");

//TODO check frontend extaddr adding works..
$section->addInput(new Form_StaticText(
	'External address',
	"Define what ip:port combinations to listen on for incomming connections.
	<br/>".
	$htmllist_extaddr->Draw($pconfig['a_extaddr'])
),"haproxy_primary")->setHelp(<<<EOT
	<b>NOTE:</b> You must add a firewall rules permitting access to the listen ports above.<br/>

	If you want this rule to apply to another IP address than the IP address of the interface chosen above,
	select it here (you need to define <a href="/firewall_virtual_ip.php">Virtual IP</a> addresses on the first).
	Also note that if you are trying to redirect connections on the LAN select the "any" option.
	In the port to listen to, if you want to specify multiple ports, separate them with a comma (,). EXAMPLE: 80,8000
	Or to listen on both 80 and 443 create 2 rows in the table where for the 443 you would likely want to check the SSL-offloading checkbox.
EOT
);


$section->addInput(new Form_Input('max_connections', 'Max connections', 'text', $pconfig['max_connections']
),"haproxy_primary")->setHelp('Sets the maximum amount of connections this frontend will accept, may be left empty.');

$section->addInput(new Form_Select(
	'type',
	'Type',
	$pconfig['type'],
	haproxy_keyvalue_array($a_frontendmode)
),"haproxy_primary")->setHelp('This defines the processing type of HAProxy, and will determine the availabe options for acl checks and also several other options.<br/>
	Please note that for https encryption/decryption on HAProxy with a certificate the processing type needs to be set to "http".');
$form->add($section);

$section = new Form_Section_class("Default backend, access control lists and actions");

$section->addInput(new Form_StaticText(
	'Access Control lists',
	"Use these to define criteria that will be used with actions defined below to perform them only when certain conditions are met.<br/>".
	$htmllist_acls->Draw($pconfig['a_acl'])
))->setHelp(<<<EOT
	- 'CS' makes the string matches 'Case Sensitive' so www.domain.tld wil not be the same as WWW.domain.TLD<br/>
	- 'Not' makes the match if the value given is not matched<br/>
	Example:
	<table border='1' style='border-collapse:collapse'>
		<tr>
			<td><b>Name</b></td>
			<td><b>Expression</b></td>
			<td><b>CI</b></td>
			<td><b>Not</b></td>
			<td><b>Value</b></td>
		</tr>
		<tr>
			<td>Backend1acl</td>
			<td>Host matches</td>
			<td></td>
			<td></td>
			<td>www.yourdomain.tld</td>
		</tr>
		<tr>
			<td>addHeaderAcl</td>
			<td>SSL Client certificate valid</td>
			<td></td>
			<td></td>
			<td></td>
		</tr>
	</table>
	<br/>
	acl's with the same name will be 'combined' using OR criteria.<br/>
	For more information about ACL's please see <a href='http://cbonte.github.io/haproxy-dconv/2.4/configuration.html#7' target='_blank'>HAProxy Documentation</a> Section 7 - Using ACL's<br/><br/>
	<strong>NOTE Important change in behaviour, since package version 0.32</strong><br/>
	-acl's are no longer combined with logical AND operators, list multiple acl's below where needed.<br/>
	-acl's alone no longer implicitly generate use_backend configuration. Add 'actions' below to accomplish this behaviour.
EOT
);

$section->addInput(new Form_StaticText(
	'Actions',
	"Use these to select the backend to use or perform other actions like calling a lua script, blocking certain requests or others available.<br/>".
	$htmllist_actions->Draw($pconfig['a_actionitems'])
))->setHelp(<<<EOT
	Example:
	<table border='1' style='border-collapse:collapse'>
		<tr>
			<td><b>Action</b></td>
			<td><b>Parameters</b></td>
			<td><b>Condition</b></td>
		</tr>
		<tr>
			<td>Use Backend</td>
			<td>Website1Backend</td>
			<td>Backend1acl</td>
		</tr>
		<tr>
			<td>http-request header set</td>
			<td>Headername: X-HEADER-ClientCertValid<br/>New logformat value: YES</td>
			<td>addHeaderAcl</td>
		</tr>
	</table>
EOT
);

$section->addInput(new Form_Select(
	'backend_serverpool',
	'Default Backend',
	$pconfig['backend_serverpool'],
	haproxy_keyvalue_array($listitem_none + $backends)
))->setHelp('If a backend is selected with actions above or in other shared frontends, no default is needed and this can be left to "None".');


$form->add($section);

$section = new Form_Section_class("Stats options");
$section->addClass("haproxy_primary");
$section->addInput(new Form_Checkbox(
	'socket-stats',
	'Separate sockets',
	'Enable collecting & providing separate statistics for each socket.',
	$pconfig['socket-stats']
));
$form->add($section);


$section = new Form_Section_class("Logging options");
$section->addClass("haproxy_primary");

$section->addInput(new Form_Checkbox(
	'dontlognull',
	"Don't log null",
	'A connection on which no data has been transferred will not be logged.',
	$pconfig['dontlognull']
))->setHelp("To skip logging probes from monitoring systems that otherwise would pollute the logging.
	(It is generally recommended not to use this option in uncontrolled environments (eg: internet),
	otherwise scans and other malicious activities would not be logged.)");

$section->addInput(new Form_Checkbox(
	'dontlog-normal',
	"Don't log normal",
	"Don't log connections in which no anomalies are found.",
	$pconfig['dontlog-normal']
))->setHelp("Setting this option ensures that
	normal connections, those which experience no error, no timeout, no retry nor
	redispatch, will not be logged.");

$section->addInput(new Form_Checkbox(
	'log-separate-errors',
	'Raise level for errors',
	'Change the level from "info" to "err" for potentially interesting information.',
	$pconfig['log-separate-errors']
))->setHelp("This option makes haproxy raise the level of logs containing potentially interesting information such
	as errors, timeouts, retries, redispatches, or HTTP status codes 5xx.");

$section->addInput(new Form_Checkbox(
	'log-detailed',
	'Detailed logging',
	'If checked provides more detailed logging.',
	$pconfig['log-detailed']
))->setHelp("Each log line turns into a much richer format including, but
	not limited to, the connection timers, the session status, the connections
	numbers, the frontend, backend and server name, and of course the source
	address and ports. In http mode also the HTTP request and captured headers and cookies will be logged.");

$form->add($section);

$section = new Form_Section_class("Error files");
$section->addInput(new Form_StaticText(
	'Error files',
	"Use these to replace the error pages that haproxy can generate by custom pages created on the files tab.
	For example haproxy will generate a 503 error page when no backend is available, you can replace that page here.<br/>".
	$errorfileslist->Draw($pconfig['a_errorfiles'])
));
$form->add($section);

$section = new Form_Section_class("Advanced settings");
$section->addClass("haproxy_primary");

$section->addInput(new Form_Input('client_timeout', 'Client timeout', 'text', $pconfig['client_timeout']
))->setHelp('the time (in milliseconds) we accept to wait for data from the client, or for the client to accept data (default 30000).');

$section->addInput(new Form_Checkbox(
	'forwardfor',
	'Use "forwardfor" option',
	'Use "forwardfor" option.',
	$pconfig['forwardfor']
),"haproxy_mode_http")->setHelp("The \"forwardfor\" option creates an HTTP \"X-Forwarded-For\" header which
	contains the client's IP address. This is useful to let the final web server
	know what the client address was. (eg for statistics on domains)<br/>");

$section->addInput(new Form_Select(
		'httpclose',
		'Use "httpclose" option',
		$pconfig['httpclose'],
		haproxy_keyvalue_array($a_closetypes)
))->setHelp('<textarea readonly="readonly" cols="70" rows="3" id="http_close_description" name="http_close_description" style="padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000; font-size: 8pt;"></textarea>');

$section->addInput(new Form_Input('advanced_bind', 'Bind pass thru', 'text', $pconfig['advanced_bind']
))->setHelp('NOTE: paste text into this box that you would like to pass behind each bind option.');

$textrowcount = max(substr_count($pconfig['advanced'],"\n"), 2) + 2;
$section->addInput(new Form_Textarea (
	'advanced',
	'Advanced pass thru',
	$pconfig['advanced']
))->setRows($textrowcount)->setNoWrap()->setHelp('NOTE: paste text into this box that you would like to pass thru in the frontend.');
$form->add($section);

$section = new Form_Section_class("SSL Offloading");
$section->addClass("haproxy_ssloffloading_show");
$section->addInput(new Form_StaticText(
	'Note',
	<<<EOT
	SSL Offloading will reduce web servers load by maintaining and encrypting connection with users on internet while sending and retrieving data without encrytion to internal servers.
	Also more ACL rules and http logging may be configured when this option is used.
	Certificates can be imported into the <a href="/system_camanager.php" target="_blank">pfSense "Certificate Authority Manager"</a>
	Please be aware this possibly will not work with all web applications. Some applications will require setting the SSL checkbox on the backend server configurations so the connection to the webserver will also be a encrypted connection, in that case there will be a slight overall performance loss."
EOT
));
$section->addInput(new Form_Checkbox(
	'ssloffload',
	'Use Offloading',
	'Specify additional certificates for this shared-frontend.',
	$pconfig['ssloffload']
),"haproxy_secondary");

$section->addInput(new Form_Input(
	'sslsnifilter',
	'SNI Filter',
	'text',
	$pconfig['sslsnifilter']
), "haproxy_ssloffloading_enabled"
)->setHelp('Specify a SNI filter to apply below SSL settings to specific domain(s), see the "crt-list" option from haproxy for details. <br/>'.
		'EXAMPLE: *.securedomain.tld !public.securedomain.tld');

$section->addInput(
	new Form_StaticText(
		'Certificate',
	(new Form_Select(
		'ssloffloadcert',
		'',
		$pconfig['ssloffloadcert'],
		haproxy_keyvalue_array($servercerts)
	))."Choose the cert to use on this frontend.".

	(new Form_Checkbox(
		'ssloffloadacl',
		'',
		'Add ACL for certificate CommonName. (host header matches the "CN" of the certificate)',
		$pconfig['ssloffloadacl']
	)).
	(new Form_Checkbox(
		'ssloffloadacl_an',
		'',
		'Add ACL for certificate Subject Alternative Names.',
		$pconfig['ssloffloadacl_an']
	))
),"haproxy_ssloffloading_enabled");

$section->addInput(new Form_Checkbox(
	'sslocsp',
	'OCSP',
	'Load certificate ocsp responses for easy certificate validation by the client.',
	$pconfig['sslocsp']
),"haproxy_ssloffloading_enabled")->setHelp("A cron job wil update the ocsp response every hour.");

$section->addInput(
	new Form_StaticText(
		'Additional certificates',
		"Which of these certificate will be send will be determined by haproxys SNI recognition. If the browser does not send SNI this will not work properly. (IE on XP is one example, possibly also older browsers or mobile devices).<br/>".
		$htmllist_sslCertificates->Draw($pconfig['a_certificates']).
	(new Form_Checkbox(
		'ssloffloadacladditional',
		'',
		'Add ACL for certificate CommonName. (host header matches the "CN" of the certificate)',
		$pconfig['ssloffloadacladditional']
	)).
	(new Form_Checkbox(
		'ssloffloadacladditional_an',
		'',
		'Add ACL for certificate Subject Alternative Names.',
		$pconfig['ssloffloadacladditional_an']
	))
),"haproxy_ssloffloading_enabled");

$section->addInput(new Form_Input('dcertadv', 'Advanced ssl options', 'text', $pconfig['dcertadv']
),"haproxy_ssloffloading_enabled haproxy_primary")->setHelp('NOTE: Paste additional ssl options(without commas) to include on ssl listening options.<br/>
	some options: force-sslv3, force-tlsv10 force-tlsv11 force-tlsv12 no-sslv3 no-tlsv10 no-tlsv11 no-tlsv12 no-tls-tickets<br/>
	Example: no-sslv3 ciphers EECDH+aRSA+AES:TLSv1+kRSA+AES:TLSv1+kRSA+3DES');
// haproxy_ssl_advanced << css class to hide field.?

$section->addInput(new Form_Input('ssl_crtlist_advanced', 'Advanced certificate specific ssl options',
	'text', $pconfig['ssl_crtlist_advanced']
),"haproxy_ssloffloading_enabled")->setHelp('NOTE: Paste additional ssl options(without commas) to include on ssl listening options.<br/>
	some options: alpn, no-ca-names, ecdhe, curves, ciphers, ssl-min-ver and ssl-max-ver<br/>
	Example: alpn h2,http/1.1 ciphers EECDH+aRSA+AES:TLSv1+kRSA+AES:TLSv1+kRSA+3DES ecdhe secp256k1');
$form->add($section);
// options that are in the gui as regular settings: verify, ca-file, crl-file
// deprecated: npn

$section = new Form_Section_class("SSL Offloading - client certificates");
$section->addClass("haproxy_ssloffloading_enabled");
if ($pconfig['secondary'] == 'yes' && haproxy_version() < "1.8") {
	$section->addClass("hidden");
}
$section->addInput(new Form_StaticText(
	'Note',
	"<b>Client certificate verification options, leave all these options empty if you do not want to ask for a client certificate</b><br/>
	The users that visit this site will need to load the client cert signed by one of the ca's listed below imported into their browser."
));
$section->addInput(new Form_Checkbox(
	'sslclientcert-none',
	'Without client cert',
	'Allows clients without a certificate to connect.',
	$pconfig['sslclientcert-none']
))->setHelp("Make sure to add appropriate acl's to check for presence of a user certificate where needed.");
$section->addInput(new Form_Checkbox(
	'sslclientcert-invalid',
	'Allow invalid cert',
	'Allows client with a invalid/expired/revoked or otherwise wrong certificate to connect.',
	$pconfig['sslclientcert-invalid']
))->setHelp(<<<EOD
	<div>Make sure to add appropriate acl's to check for valid certificates and verify errors using codes from the following list.
	<a target="_blank" href="https://www.openssl.org/docs/apps/verify.html#DIAGNOSTICS">https://www.openssl.org/docs/apps/verify.html#DIAGNOSTICS</a></div>
EOD
);
$section->addInput(new Form_StaticText(
	'Client verification CA certificates',
	"Client certificate will be verified against these CA certificates.<br/>".
	$htmllist_caCertificates->Draw($pconfig['clientcert_ca'])
));
$section->addInput(new Form_StaticText(
	'Client verification CRL',
	"Client certificate will be verified against these CRL revocation lists.<br/>".
	$htmllist_crlCertificates->Draw($pconfig['clientcert_crl'])
));

$form->add($section);
print $form;


?>
<script type="text/javascript">
//<![CDATA[

var port_array  = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;
var address_array = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;

events.push(function() {
	$('form').submit(function(event){
		// disable all elements that dont have a value to avoid posting them as it could be sending
		// more than 5000 variables which is the php default max for less than 100 san's which acme does support
		// p.s. the jquery .find(['value'='']) would not find newly added empty items) so we use .filter(...)
		$(this).find(':input').filter(function() { return !this.value }).attr("disabled", "disabled")
		return true;
	});

<?php
	// On gui descriptions when a closetype has been selected..
	phparray_to_javascriptarray($a_closetypes, "closetypes", Array('/*', '/*/name', '/*/descr'));

	// To find 'type' of frontend to show proper acl's ??
	phparray_to_javascriptarray($primaryfrontends,"primaryfrontends",Array('/*',
		'/*/name', '/*/ref', '/*/ref/type', '/*/ref/a_extaddr', '/*/ref/a_extaddr/item', '/*/ref/a_extaddr/item/*',
		'/*/ref/a_extaddr/item/*/extaddr_ssl'));

	phparray_to_javascriptarray($a_action, "showhide_actionfields",
		Array('/*', '/*/fields', '/*/fields/*', '/*/fields/*/name'));
	phparray_to_javascriptarray($a_acltypes, "showhide_aclfields",
		Array('/*', '/*/casesensitive', '/*/fields', '/*/fields/*', '/*/fields/*/name'));

	$htmllist_extaddr->outputjavascript();
	$htmllist_acls->outputjavascript();
	$htmllist_actions->outputjavascript();
	$errorfileslist->outputjavascript();
	$htmllist_sslCertificates->outputjavascript();
	$htmllist_caCertificates->outputjavascript();
	$htmllist_crlCertificates->outputjavascript();
?>
	totalrows =  <?php echo $counter; ?>;

	for(i=0;i < <?=(is_array($a_extaddr) ? count($a_extaddr) : 0)?>;i++){
		$('#table_extaddrextaddr_custom'+i).autocomplete({
			source: address_array
		});
		$('#table_extaddrextaddr_port'+i).autocomplete({
			source: port_array
		});
		// Initially set fields disabled where needed
		table_extaddr_listitem_change('table_extaddr','',i,null);
	}

	$('#secondary').click(function () {
		updatevisibility();
	});
	$('#primary_frontend').on('change', function() {
		updatevisibility();
	});
	$('#type').on('change', function() {
		updatevisibility();
	});
	$('#httpclose').on('change', function() {
		updatevisibility();
	});
	$('#ssloffload').click(function () {
		updatevisibility();
	});
	$('#sslsnifilter').on('change input keyup cut paste', function () {
		updatevisibility();
	});

	d = document;
	// make sure enabled/disabled visable/hidden states of items dependant on these boxes are correct when loading the page.
	$('[id^=table_aclsexpression]').change();
	$('[id^=table_extaddrextaddr]').change();

	updatevisibility();
});

	function sethiddenclass(id,showitem) {
		if (showitem) {
			$("#"+id).removeClass("hidden");
		} else {
			$("#"+id).addClass("hidden");
		}
	}

	function table_acls_listitem_change(tableId, fieldId, rowNr, field) {
		if (fieldId === "toggle_details") {
			fieldId = "expression";
			field = d.getElementById(tableId+"expression"+rowNr);
		}
		if (fieldId === "expression") {
			var acltypeid = field.value;
			var acltype = showhide_aclfields[acltypeid];
			sethiddenclass('table_aclscasesensitive'+rowNr, acltype['casesensitive']);
			sethiddenclass('table_aclscasesensitive'+rowNr+'_disp', acltype['casesensitive']);
			var table = d.getElementById(tableId);

			for(var actionkey in showhide_aclfields) {
				var fields = showhide_aclfields[actionkey]['fields'];
				for(var fieldkey in fields){
					var fieldname = fields[fieldkey]['name'];
					sethiddenclass("tr_edititemdetails_"+rowNr+"_"+actionkey+fieldname, actionkey === acltypeid);
					sethiddenclass(tableId+actionkey+fieldname+rowNr+'_disp', actionkey === acltypeid);
				}
			}
		}
	}

	function table_actions_listitem_change(tableId, fieldId, rowNr, field) {
		if (fieldId === "toggle_details") {
			fieldId = "action";
			field = d.getElementById(tableId+"action"+rowNr);
		}
		if (fieldId === "action") {
			var actiontype = field.value;

			var table = d.getElementById(tableId);

			for(var actionkey in showhide_actionfields) {
				var fields = showhide_actionfields[actionkey]['fields'];
				for(var fieldkey in fields){
					var fieldname = fields[fieldkey]['name'];
					var rowid = "tr_edititemdetails_"+rowNr+"_"+actionkey+fieldname;
					if (actionkey === actiontype) {
						$("#"+rowid).removeClass("hidden");
					} else {
						$("#"+rowid).addClass("hidden");
					}
				}
			}
		}
	}

			function table_extaddr_row_added(tableId, rowId){

				$('#'+tableId+"extaddr_custom"+rowId).autocomplete({
					source: address_array
				});
				$('#'+tableId+"extaddr_port"+rowId).autocomplete({
					source: port_array
				});
				table_extaddr_listitem_change(tableId,"",rowId, null);//disables address when not set to custom.
			}

			function table_extaddr_listitem_change(tableId, fieldId, rowNr, field) {
				if (fieldId === "extaddr" || fieldId === "") {
					field = field || document.getElementById(tableId+"extaddr"+rowNr);
					customEdit = document.getElementById(tableId+"extaddr_custom"+rowNr);
					customdisabled = field.value === "custom" ? 0 : 1;
					customEdit.disabled = customdisabled;
				}
				if (fieldId === "extaddr_ssl") {
					updatevisibility();
				}
			}


//]]>
</script>

<!--
<?php if (isset($id) && config_get_path("installedpackages/haproxy/ha_backends/item/{$id}")): ?>
<input name="id" type="hidden" value="<?=config_get_path("installedpackages/haproxy/ha_backends/item/{$id}/name");?>" />
<?php endif; ?>
-->

<?php
haproxy_htmllist_js();
include("foot.inc");
