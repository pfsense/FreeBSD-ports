<?php
/* $Id: load_balancer_pool_edit.php,v 1.24.2.23 2007/03/03 00:07:09 smos Exp $ */
/*
	haproxy_listeners_edit.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2009 Scott Ullrich <sullrich@pfsense.com>
	Copyright (C) 2008 Remco Hoef <remcoverhoef@pfsense.com>
	Copyright (C) 2013 PiBa-NL merging (some of the) "haproxy-devel" changes from: Marcello Coutinho <marcellocoutinho@gmail.com>
	Copyright (C) 2013-2015 PiBa-NL
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
$shortcut_section = "haproxy";
require("guiconfig.inc");
require_once("haproxy.inc");
require_once("haproxy_utils.inc");
require_once("haproxy_htmllist.inc");
require_once("pkg_haproxy_tabs.inc");

/* Compatibility function for pfSense 2.0 */
if (!function_exists("cert_get_purpose")) {	
	function cert_get_purpose(){
		$result = array();
		$result['server'] = "Yes";
		return $result;
	}
}
/**/

if (!is_array($config['installedpackages']['haproxy']['ha_backends']['item'])) {
	$config['installedpackages']['haproxy']['ha_backends']['item'] = array();
}

$a_backend = &$config['installedpackages']['haproxy']['ha_backends']['item'];
$a_pools = &$config['installedpackages']['haproxy']['ha_pools']['item'];
if (!is_array($a_pools))
	$a_pools = array();
uasort($a_pools, haproxy_compareByName);

global $simplefields;
$simplefields = array('name','desc','status','secondary','primary_frontend','type','forwardfor','httpclose','extaddr','backend_serverpool',
	'max_connections','client_timeout','port','advanced_bind',
	'ssloffloadcert','dcertadv','ssloffload','ssloffloadacl','ssloffloadacl_an','ssloffloadacladditional','ssloffloadacladditional_an',
	'sslclientcert-none','sslclientcert-invalid','sslocsp',
	'socket-stats',
	'dontlognull','dontlog-normal','log-separate-errors','log-detailed');

if (isset($_POST['id']))
	$id = $_POST['id'];
else
	$id = $_GET['id'];

if (isset($_GET['dup']))
	$id = $_GET['dup'];

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

$fields_aclSelectionList[2]['name']="not";
$fields_aclSelectionList[2]['columnheader']="Not";
$fields_aclSelectionList[2]['colwidth']="5%";
$fields_aclSelectionList[2]['type']="checkbox";
$fields_aclSelectionList[2]['size']="5";

$fields_aclSelectionList[3]['name']="value";
$fields_aclSelectionList[3]['columnheader']="Value";
$fields_aclSelectionList[3]['colwidth']="35%";
$fields_aclSelectionList[3]['type']="textbox";
$fields_aclSelectionList[3]['size']="35";

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
			$item['customdrawcell'] = customdrawcell_actions;
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
			$item['columnheader'] = $field['name'];
			$item['customdrawcell'] = customdrawcell_actions;
			$fields_acl_details[$name] = $item;
		}
	}
}

function customdrawcell_actions($object, $item, $itemvalue, $editable, $itemname, $counter) {
	if ($editable) {
		$object->haproxy_htmllist_drawcell($item, $itemvalue, $editable, $itemname, $counter);
	} else {
		//TODO hide fields not applicable.?.
		echo $itemvalue;
	}
}

$htmllist_extaddr = new HaproxyHtmlList("table_extaddr", $fields_externalAddress);
$htmllist_extaddr->editmode = true;

$htmllist_acls = new HaproxyHtmlList("table_acls", $fields_aclSelectionList);
$htmllist_acls->fields_details = $fields_acl_details;
//$htmllist_acls->editmode = true;

$htmllist_actions = new HaproxyHtmlList("table_actions", $fields_actions);
$htmllist_actions->fields_details = $fields_actions_details;
//$htmllist_actions->keyfield = "name";
//$htmllist_actions->editmode = true;

$htmllist_sslCertificates = new HaproxyHtmlList("tbl_sslCerts", $fields_sslCertificates);
$htmllist_caCertificates = new HaproxyHtmlList("tbl_caCerts", $fields_caCertificates );
$htmllist_crlCertificates = new HaproxyHtmlList("tbl_crlCerts", $fields_crlCertificates);

$errorfileslist = new HaproxyHtmlList("table_errorfile", $fields_errorfile);
$errorfileslist->keyfield = "errorcode";

if (isset($id) && $a_backend[$id]) {
	$pconfig['a_acl']=&$a_backend[$id]['ha_acls']['item'];	
	$pconfig['a_certificates']=&$a_backend[$id]['ha_certificates']['item'];
	$pconfig['clientcert_ca']=&$a_backend[$id]['clientcert_ca']['item'];
	$pconfig['clientcert_crl']=&$a_backend[$id]['clientcert_crl']['item'];
	$pconfig['a_extaddr']=&$a_backend[$id]['a_extaddr']['item'];
	$pconfig['a_actionitems']=&$a_backend[$id]['a_actionitems']['item'];
	$pconfig['a_errorfiles']=&$a_backend[$id]['a_errorfiles']['item'];
	
	$pconfig['advanced'] = base64_decode($a_backend[$id]['advanced']);
	foreach($simplefields as $stat)
		$pconfig[$stat] = $a_backend[$id][$stat];
}

if (isset($_GET['dup'])) {
	unset($id);
	if ($pconfig['secondary'] != 'yes')
		$pconfig['primary_frontend'] = $pconfig['name'];
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
	
	$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
	if ($pf_version < 2.1)
		$input_errors = eval('do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors); return $input_errors;');
	else
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['name']))
		$input_errors[] = "The field 'Name' contains invalid characters.";

	if ($pconfig['secondary'] != "yes") {
		if ($_POST['max_connections'] && !is_numeric($_POST['max_connections']))
			$input_errors[] = "The field 'Max connections' value is not a number.";

		$ports = split(",", $_POST['port'] . ",");
		foreach($ports as $port)
			if ($port && !is_numeric($port) && !is_portoralias($port))
				$input_errors[] = "The field 'Port' value '".htmlspecialchars($port)."' is not a number or alias thereof.";

		if ($_POST['client_timeout'] !== "" && !is_numeric($_POST['client_timeout']))
			$input_errors[] = "The field 'Client timeout' value is not a number.";
	}

	/* Ensure that our pool names are unique */
	for ($i=0; isset($config['installedpackages']['haproxy']['ha_backends']['item'][$i]); $i++)
		if (($_POST['name'] == $config['installedpackages']['haproxy']['ha_backends']['item'][$i]['name']) && ($i != $id))
			$input_errors[] = "This frontend name has already been used. Frontend names must be unique. $i != $id";

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
		if (preg_match("/[^a-zA-Z0-9\.\-_]/", $acl_name))
			$input_errors[] = "The field 'Name' contains invalid characters.";

		if (!isset($acltype['novalue']))
			if (!preg_match("/.{1,}/", $acl_value))
				$input_errors[] = "The field 'Value' is required.";

		if (!preg_match("/.{2,}/", $acl_name))
			$input_errors[] = "The field 'Name' is required with at least 2 characters.";
	}
	foreach($a_extaddr as $extaddr) {
		$ports = explode(",",$extaddr['extaddr_port']);
		foreach($ports as $port){
			if ($port && !is_numeric($port) && !is_portoralias($port))
				$input_errors[] = "The field 'Port' value '".htmlspecialchars($port)."' is not a number or alias thereof.";
		}
	
		if ($extaddr['extaddr'] == 'custom') {
			$extaddr_custom = $extaddr['extaddr_custom'];
			if (empty($extaddr_custom) || (!is_ipaddroralias($extaddr_custom)))
				$input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."),$extaddr_custom);
		}
	}
	if (!$input_errors) {
		$backend = array();
		if(isset($id) && $a_backend[$id])
			$backend = $a_backend[$id];
			
		if($backend['name'] != "")
			$changedesc .= " modified '{$backend['name']}' pool:";
			
		// update references to this primary frontend
		if ($backend['name'] != $_POST['name']) {
			foreach($a_backend as &$frontend) {
				if ($frontend['primary_frontend'] == $backend['name']) {
					$frontend['primary_frontend'] = $_POST['name'];
				}
			}
		}
		
		foreach($simplefields as $stat)
			update_if_changed($stat, $backend[$stat], $_POST[$stat]);
		
		update_if_changed("advanced", $backend['advanced'], base64_encode($_POST['advanced']));
		$backend['ha_acls']['item'] = $a_acl;
		$backend['ha_certificates']['item'] = $a_certificates;
		$backend['clientcert_ca']['item'] = $a_clientcert_ca;
		$backend['clientcert_crl']['item'] = $a_clientcert_crl;
		$backend['a_extaddr']['item'] = $a_extaddr;
		$backend['a_actionitems']['item'] = $a_actionitems;
		$backend['a_errorfiles']['item'] = $a_errorfiles;

		if (isset($id) && $a_backend[$id]) {
			$a_backend[$id] = $backend;
		} else {
			$a_backend[] = $backend;
		}

		if ($changecount > 0) {
			touch($d_haproxyconfdirty_path);
			write_config($changedesc);
		}

		header("Location: haproxy_listeners.php");
		exit;
	}
}

$closehead = false;
$pgtitle = "HAProxy: Frontend: Edit";
include("head.inc");
haproxy_css();

if (!isset($_GET['dup']))
	$excludefrontend = $pconfig['name'];
$primaryfrontends = get_haproxy_frontends($excludefrontend);

?>
  <style type="text/css">
	.haproxy_mode_http{display:none;}
	.haproxy_ssloffloading_show{display:none;}
	.haproxy_ssloffloading_enabled{display:none;}
	.haproxy_primary{}
	.haproxy_secondary{display:none;}
  </style>
  <script type="text/javascript" src="/javascript/suggestions.js"></script>
  <script type="text/javascript" src="/javascript/autosuggest.js"></script>
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<script type="text/javascript">
	function htmllist_get_select_options(tableId, fieldname, itemstable) {
		if (tableId == 'table_acls' && fieldname == 'expression') {
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
				if (newitem['mode'] == type || newitem['mode'] == "") {
					result[key] = newitem;
					result[key]['name'] = result[key]['name'];
				}
			}
			return result;
		}
		return itemstable;
	}

	function setCSSdisplay(cssID, display) {
		var ss = document.styleSheets;
		for (var i=0; i<ss.length; i++) {
			var rules = ss[i].cssRules || ss[i].rules;
			for (var j=0; j<rules.length; j++) {
				if (rules[j].selectorText === cssID) {
					rules[j].style.display = display ? "" : "none";
				}
			}
		}
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
		if ((secondary !== null) && (secondary.checked)) {
			primary = primaryfrontends[primary_frontend.value];
			type = primary['ref']['type'];
			for (i = 0; i < 99; i++) {
				if (primary['ref']['a_extaddr']['item'][i] && primary['ref']['a_extaddr']['item'][i]['extaddr_ssl'] == 'yes')
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
		setCSSdisplay(".haproxy_mode_http", type == "http");
		if (secondary !== null) {
			setCSSdisplay(".haproxy_primary", !secondary.checked);
			setCSSdisplay(".haproxy_secondary", secondary.checked);
		}
		
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
		var acl = [ <?php foreach ($a_acltypes as $key => $expr) echo "'".$key."'," ?> ];
		var mode = [ <?php foreach ($a_acltypes as $key => $expr) echo "'".$expr['mode']."'," ?> ];

        d = document;
		for (i = 0; i < 99; i++) {
			el = d.getElementById("table_acls" + "expression" + i);
			row_e = d.getElementById("tr_edit_" + i);
			row_v = d.getElementById("tr_viewdetail_" + i);
			if (!el || !row_e)
				continue;
			for (j = 0; j < count; j++) {
				if (acl[j] == el.value) {
					if (mode[j] != '' && mode[j] != type) {
						Effect.Fade(row_e,{ duration: 1.0 });
						if (row_v) {
							Effect.Fade(row_v,{ duration: 1.0 });
						}
					} else {
						Effect.Appear(row_e,{ duration: 1.0 });
						if (row_v) {
							Effect.Appear(row_v,{ duration: 1.0 });
						}
					}
				}
			}
		}
	}
</script>
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<form action="haproxy_listeners_edit.php" method="post" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <?php
	haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "frontend");
  ?>
  </td></tr>
  <tr>
    <td>
	<div class="tabcont">
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic">Edit haproxy listener</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Name</td>
			<td width="78%" class="vtable" colspan="2">
				<input name="name" type="text" <?if(isset($pconfig['name'])) echo "value=\"{$pconfig['name']}\"";?> size="25" maxlength="25" />
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncell">Description</td>
			<td width="78%" class="vtable" colspan="2">
				<input name="desc" type="text" <?if(isset($pconfig['desc'])) echo "value=\"{$pconfig['desc']}\"";?> size="64" />
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq">Status</td>
			<td width="78%" class="vtable" colspan="2">
				<select name="status" id="status">
					<option value="active"<?php if($pconfig['status'] == "active") echo " SELECTED"; ?>>Active</option>
					<option value="disabled"<?php if($pconfig['status'] == "disabled") echo " SELECTED"; ?>>Disabled</option>
				</select>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncell">Shared Frontend</td>
			<td width="78%" class="vtable" colspan="2">
				<?if (count($primaryfrontends)==0){ ?>
				<b>At least 1 primary frontend is needed.</b><br/><br/>
				<? } else{ ?>
				<input id="secondary" name="secondary" type="checkbox" value="yes" <?php if ($pconfig['secondary']=='yes') echo "checked"; ?> onclick="updatevisibility();" />
				<? } ?>
				This can be used to host a second or more website on the same IP:Port combination.<br/>
				Use this setting to configure multiple backends/accesslists for a single frontend.<br/>
				All settings of which only 1 can exist will be hidden.<br/>
				The frontend settings will be merged into 1 set of frontend configuration.
			</td>
		</tr>
		<tr class="haproxy_secondary" align="left">
			<td width="22%" valign="top" class="vncellreq">Primary frontend</td>
			<td width="78%" class="vtable" colspan="2">
				<?
				echo_html_select('primary_frontend',$primaryfrontends, $pconfig['primary_frontend'],"You must first create a 'primary' frontend.","updatevisibility();");
				?>
			</td>
		</tr>
		<tr class="haproxy_primary">
			  <td width="22%" valign="top" class="vncellreq">External address</td>
			  <td width="78%" class="vtable">
			<?
			$counter=0;
			$a_extaddr = $pconfig['a_extaddr'];
			$htmllist_extaddr->Draw($a_extaddr);
			?>
			<script type="text/javascript">
			function table_extaddr_row_added(tableId, rowId){
				new AutoSuggestControl(document.getElementById(tableId+"extaddr_custom"+rowId), new StateSuggestions(address_array));
				new AutoSuggestControl(document.getElementById(tableId+"extaddr_port"+rowId), new StateSuggestions(port_array));
				table_extaddr_listitem_change(tableId,"",rowId, null);//disables address when not set to custom.
			}
			
			function table_extaddr_listitem_change(tableId, fieldId, rowNr, field) {
				if (fieldId == "extaddr" || fieldId == "") {
					field = field || document.getElementById(tableId+"extaddr"+rowNr);
					customEdit = document.getElementById(tableId+"extaddr_custom"+rowNr);
					customdisabled = field.value == "custom" ? 0 : 1;
					customEdit.disabled = customdisabled;
				}
				if (fieldId == "extaddr_ssl") {
					updatevisibility();
				}
			}
			
			</script>
				<br />
				<span class="vexpl">
					If you want this rule to apply to another IP address than the IP address of the interface chosen above,
					select it here (you need to define <a href="firewall_virtual_ip.php">Virtual IP</a> addresses on the first).  
					Also note that if you are trying to redirect connections on the LAN select the "any" option.

					In the port to listen to, if you want to specify multiple ports, separate them with a comma (,). EXAMPLE: 80,8000
					Or to listen on both 80 and 443 create 2 rows in the table.
				</span>
			  </td>
		</tr>
		<tr class="haproxy_primary" align="left">
			<td width="22%" valign="top" class="vncell">Max connections</td>
			<td width="78%" class="vtable" colspan="2">
				<input name="max_connections" type="text" <?if(isset($pconfig['max_connections'])) echo "value=\"{$pconfig['max_connections']}\"";?> size="10" maxlength="10" />
			</td>
		</tr>	
		<tr class="haproxy_primary" align="left">
			<td width="22%" valign="top" class="vncellreq">Type</td>
			<td width="78%" class="vtable" colspan="2">
				<select name="type" id="type" onchange="updatevisibility();">
					<option value="http"<?php if($pconfig['type'] == "http") echo " SELECTED"; ?>>HTTP / HTTPS(offloading)</option>
					<option value="https"<?php if($pconfig['type'] == "https") echo " SELECTED"; ?>>SSL / HTTPS(TCP mode)</option>
					<option value="tcp"<?php if($pconfig['type'] == "tcp") echo " SELECTED"; ?>>TCP</option>
					<option value="health"<?php if($pconfig['type'] == "health") echo " SELECTED"; ?>>Health</option>
				</select><br/>
				<span class="vexpl">
					This defines the processing type of HAProxy, and will determine the availabe options for acl checks and also several other options.<br/>
					Please note that for https encryption/decryption on HAProxy with a certificate the processing type needs to be set to 'http'.
				</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Access Control lists</td>
			<td width="78%" class="vtable" colspan="2" valign="top">
			<?
			$a_acl = $pconfig['a_acl'];
			$htmllist_acls->Draw($a_acl);
			?>
			<br/>
				Example:
				<table border='1' style='border-collapse:collapse'>
					<tr>
						<td><b>Name</b></td>
						<td><b>Expression</b></td>
						<td><b>Not</b></td>
						<td><b>Value</b></td>
					</tr>
					<tr>
						<td>Backend1acl</td>
						<td>Host matches</td>
						<td></td>
						<td>www.yourdomain.tld</td>
					</tr>
					<tr>
						<td>addHeaderAcl</td>
						<td>SSL Client certificate valid</td>
						<td></td>
						<td></td>
					</tr>
				</table>
			<br/>
			acl's with the same name will be 'combined' using OR criteria.<br/>
			For more information about ACL's please see <a href='http://haproxy.1wt.eu/download/1.5/doc/configuration.txt' target='_blank'>HAProxy Documentation</a> Section 7 - Using ACL's<br/><br/>
			<strong>NOTE Important change in behaviour, since package version 0.32</strong><br/>
			-acl's are no longer combined with logical AND operators, list multiple acl's below where needed.<br/>
			-acl's alone no longer implicitly generate use_backend configuration. Add 'actions' below to accomplish this behaviour.
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Actions</td>
			<td width="78%" class="vtable" colspan="2" valign="top">
				<?
				$a_actionitems = $pconfig['a_actionitems'];
				$htmllist_actions->Draw($a_actionitems);
				?>
				<br/>
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
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Default Backend</td>
			<td width="78%" class="vtable">
				<?php
				$listitem_none['']['name']="None";
				$backends = $listitem_none + $backends;
				echo_html_select("backend_serverpool", $backends, $pconfig['backend_serverpool'] ? $pconfig['backend_serverpool'] : "none", "", "updatevisibility();");
				?>
			</td>
		</tr>
		<tr class="haproxy_primary"><td>&nbsp;</td></tr>
		<tr class="haproxy_primary">
			<td colspan="2" valign="top" class="listtopic">Stats options</td>
		</tr>
		<tr class="haproxy_primary" align="left">
			<td width="22%" valign="top" class="vncell">Separate sockets</td>
			<td width="78%" class="vtable" colspan="2">
				<input id="socket-stats" name="socket-stats" type="checkbox" value="yes" <?php if ($pconfig['socket-stats']=='yes') echo "checked"; ?> onclick='updatevisibility();' />
				Enable collecting &amp; providing separate statistics for each socket.
			</td>
		</tr>
		<tr class="haproxy_primary"><td>&nbsp;</td></tr>
		<tr class="haproxy_primary">
			<td colspan="2" valign="top" class="listtopic">Logging options</td>
		</tr>
		<tr class="haproxy_primary" align="left">
			<td width="22%" valign="top" class="vncell">Dont log null</td>
			<td width="78%" class="vtable" colspan="2">
				<input id="dontlognull" name="dontlognull" type="checkbox" value="yes" <?php if ($pconfig['dontlognull']=='yes') echo "checked"; ?> onclick='updatevisibility();' />
				A connection on which no data has been transferred will not be logged.
				<div>To skip logging probes from monitoring systems that otherwise would pollute the logging. (It is generally recommended not to use this option in uncontrolled environments (eg: internet), otherwise scans and other malicious activities would not be logged.)</div>
			</td>
		</tr>
		<tr class="haproxy_primary" align="left">
			<td width="22%" valign="top" class="vncell">Dont log normal</td>
			<td width="78%" class="vtable" colspan="2">
				<input id="dontlog-normal" name="dontlog-normal" type="checkbox" value="yes" <?php if ($pconfig['dontlog-normal']=='yes') echo "checked"; ?> onclick='updatevisibility();' />
				Don't log connections in which no anomalies are found.
				<div>Setting this option ensures that
				normal connections, those which experience no error, no timeout, no retry nor
				redispatch, will not be logged.</div>
			</td>
		</tr>
		<tr class="haproxy_primary" align="left">
			<td width="22%" valign="top" class="vncell">Raise level for errors</td>
			<td width="78%" class="vtable" colspan="2">
				<input id="log-separate-errors" name="log-separate-errors" type="checkbox" value="yes" <?php if ($pconfig['log-separate-errors']=='yes') echo "checked"; ?> onclick='updatevisibility();' />
				Change the level changes from "info" to "err" for potentially interesting information.
				<div>This option makes haproxy raise the level of logs containing potentially interesting information such
				as errors, timeouts, retries, redispatches, or HTTP status codes 5xx. </div>
			</td>
		</tr>
		<tr class="haproxy_primary" align="left">
			<td width="22%" valign="top" class="vncell">Detailed logging</td>
			<td width="78%" class="vtable" colspan="2">
				<input id="log-detailed" name="log-detailed" type="checkbox" value="yes" <?php if ($pconfig['log-detailed']=='yes') echo "checked"; ?> onclick='updatevisibility();' />
				If checked provides more detailed logging.
				<div>Each log line turns into a much richer format including, but
				not limited to, the connection timers, the session status, the connections
				numbers, the frontend, backend and server name, and of course the source
				address and ports. In http mode also the HTTP request and captured headers and cookies will be logged.</div>
			</td>
		</tr>
		<tr><td>&nbsp;</td></tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic">Error files</td>
		</tr>
		<tr class="" align="left" id='errorfiles'>
			<td colspan="2" valign="top" class="vtable">
			Use these to replace the error pages that haproxy can generate by custom pages created on the files tab.
			For example haproxy will generate a 503 error page when no backend is available, you can replace that page here.
			<br/>
			<br/>
			<?
			$a_errorfiles = $pconfig['a_errorfiles'];
			$errorfileslist->Draw($a_errorfiles);
			?>
			</td>
		</tr>
		<tr><td>&nbsp;</td></tr>
	</table>
	<br/>&nbsp;<br/>
	<table class="haproxy_primary" width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic">Advanced settings</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncell">Client timeout</td>
			<td width="78%" class="vtable" colspan="2">
				<input name="client_timeout" type="text" <?if(isset($pconfig['client_timeout'])) echo "value=\"{$pconfig['client_timeout']}\"";?> size="10" maxlength="10" />
				<div>the time (in milliseconds) we accept to wait for data from the client, or for the client to accept data (default 30000).</div>
			</td>
		</tr>
		<tr align="left" class="haproxy_mode_http">
			<td width="22%" valign="top" class="vncell">Use 'forwardfor' option</td>
			<td width="78%" class="vtable" colspan="2">
				<input id="forwardfor" name="forwardfor" type="checkbox" value="yes" <?php if ($pconfig['forwardfor']=='yes') echo "checked"; ?> />
				<br/>
				The 'forwardfor' option creates an HTTP 'X-Forwarded-For' header which
				contains the client's IP address. This is useful to let the final web server
				know what the client address was. (eg for statistics on domains)<br/>
			</td>
		</tr>
		<tr align="left" class="haproxy_mode_http">
			<td width="22%" valign="top" class="vncell">Use 'httpclose' option</td>
			<td width="78%" class="vtable" colspan="2">
				<?
					echo_html_select("httpclose",$a_closetypes,$pconfig['httpclose']?$pconfig['httpclose']:"none","","updatevisibility();");
				?><br/>
				<textarea readonly="yes" cols="70" rows="3" id="http_close_description" name="http_close_description" style="padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000; font-size: 8pt;"></textarea>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncell">Bind pass thru</td>
			<td width="78%" class="vtable" colspan="2">
				<input name="advanced_bind" type="text" <?if(isset($pconfig['advanced_bind'])) echo "value=\"".htmlspecialchars($pconfig['advanced_bind'])."\"";?> size="64" />
				<br/>
				NOTE: paste text into this box that you would like to pass behind the bind option.
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncell">Advanced pass thru</td>
			<td width="78%" class="vtable" colspan="2">
				<? $textrowcount = max(substr_count($pconfig['advanced'],"\n"), 2) + 2; ?>
				<textarea name='advanced' rows="<?=$textrowcount;?>" cols="70" id='advanced'><?php echo htmlspecialchars($pconfig['advanced']); ?></textarea>
				<br/>
				NOTE: paste text into this box that you would like to pass thru.
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
	</table>
	<table class="haproxy_ssloffloading_show" width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic">SSL Offloading</td>
		</tr>
		<tr align="left">
			<td width="78%" class="vtable" colspan="2">
				SSL Offloading will reduce web servers load by maintaining and encrypting connection with users on internet while sending and retrieving data without encrytion to internal servers.
				Also more ACL rules and http logging may be configured when this option is used. 
				Certificates can be imported into the <a href="/system_camanager.php" target="_blank">pfSense "Certificate Authority Manager"</a>
				Please be aware this possibly will not work with all web applications. Some applications will require setting the SSL checkbox on the backend server configurations so the connection to the webserver will also be a encrypted connection, in that case there will be a slight overall performance loss.
			</td>
		</tr>
		<tr align="left" class="haproxy_secondary" >
			<td width="22%" valign="top" class="vncell">Use Offloading</td>
			<td width="78%" class="vtable" colspan="2">
				<input id="ssloffload" name="ssloffload" type="checkbox" value="yes" <?php if ($pconfig['ssloffload']=='yes') echo "checked";?> onclick="updatevisibility();" /><strong>Specify additional certificates for this shared-frontend.</strong>
			</td>
		</tr>
		<tr class="haproxy_ssloffloading_enabled" align="left">
			<td width="22%" valign="top" class="vncell">Certificate</td>
			<td width="78%" class="vtable" colspan="2">
				<?  
					echo_html_select("ssloffloadcert", $servercerts, $pconfig['ssloffloadcert'], '<b>No Certificates defined.</b> <br/>Create one under <a href="system_certmanager.php">System &gt; Cert Manager</a>.');
				?>
				<br/>
				Choose the cert to use on this frontend.
				<br/>
				<input id="ssloffloadacl" name="ssloffloadacl" type="checkbox" value="yes" <?php if ($pconfig['ssloffloadacl']=='yes') echo "checked";?> onclick="updatevisibility();" />Add ACL for certificate CommonName. (host header matches the 'CN' of the certificate)<br/>
				<input id="ssloffloadacl_an" name="ssloffloadacl_an" type="checkbox" value="yes" <?php if ($pconfig['ssloffloadacl_an']=='yes') echo "checked";?> onclick="updatevisibility();" />Add ACL for certificate Subject Alternative Names.<br/>
			</td>
		</tr>
		<tr class="haproxy_ssloffloading_enabled" align="left">
			<td width="22%" valign="top" class="vncell">OCSP</td>
			<td width="78%" class="vtable" colspan="2">
				<input id="sslocsp" name="sslocsp" type="checkbox" value="yes" <?php if ($pconfig['sslocsp']=='yes') echo "checked";?> onclick="updatevisibility();" />Load certificate ocsp responses for easy certificate validation by the client.<br/>
			</td>
		</tr>
		<tr class="haproxy_ssloffloading_enabled">
			<td width="22%" valign="top" class="vncell">Additional certificates</td>
			<td width="78%" class="vtable" colspan="2" valign="top">
			Which of these certificate will be send will be determined by haproxys SNI recognition. If the browser does not send SNI this will not work properly. (IE on XP is one example, possibly also older browsers or mobile devices)
			<?
			$a_certificates = $pconfig['a_certificates'];
			//haproxy_htmllist("tableA_sslCertificates", $a_certificates, $fields_sslCertificates);
			$htmllist_sslCertificates->Draw($a_certificates);
			?>
				<br/>
				<input id="ssloffloadacladditional" name="ssloffloadacladditional" type="checkbox" value="yes" <?php if ($pconfig['ssloffloadacladditional']=='yes') echo "checked";?> onclick="updatevisibility();" />Add ACL for certificate CommonName. (host header matches the 'CN' of the certificate)<br/>
				<input id="ssloffloadacladditional_an" name="ssloffloadacladditional_an" type="checkbox" value="yes" <?php if ($pconfig['ssloffloadacladditional_an']=='yes') echo "checked";?> onclick="updatevisibility();" />Add ACL for certificate Subject Alternative Names.<br/>
			</td>
		</tr>
		<tr class="haproxy_ssloffloading_enabled haproxy_primary" align="left">
			<td width="22%" valign="top" class="vncell">Advanced ssl options</td>
			<td width="78%" class="vtable" colspan="2">
				<input type='text' name='dcertadv' size="64" id='dcertadv' <?if(isset($pconfig['dcertadv'])) echo 'value="'.htmlspecialchars($pconfig['dcertadv']).'"';?> />
				<br/>
				NOTE: Paste additional ssl options(without commas) to include on ssl listening options.<br/>
				some options: force-sslv3, force-tlsv10 force-tlsv11 force-tlsv12 no-sslv3 no-tlsv10 no-tlsv11 no-tlsv12 no-tls-tickets<br/>
				Example: no-sslv3 ciphers EECDH+aRSA+AES:TLSv1+kRSA+AES:TLSv1+kRSA+3DES
			</td>
		</tr>
		<tr class="haproxy_ssloffloading_enabled haproxy_primary">
			<td class="vncell" colspan="2"><b>Client certificate verification options, leave all these options empty if you do not want to ask for a client certificate</b><br/>
			The users that visit this site will need to load the client cert signed by one of the ca's listed below imported into their browser.</td>
		</tr>
		<tr class="haproxy_ssloffloading_enabled haproxy_primary" align="left">
			<td width="22%" valign="top" class="vncell">Without client cert</td>
			<td width="78%" class="vtable" colspan="2">
				<input id="sslclientcert-none" name="sslclientcert-none" type="checkbox" value="yes" <?php if ($pconfig['sslclientcert-none']=='yes') echo "checked"; ?> onclick='updatevisibility();' />
				Allows clients without a certificate to connect.
				<div>Make sure to add appropriate acl's to check for presence of a user certificate where needed.</div>
			</td>
		</tr>
		<tr class="haproxy_ssloffloading_enabled haproxy_primary" align="left">
			<td width="22%" valign="top" class="vncell">Allow invalid cert</td>
			<td width="78%" class="vtable" colspan="2">
				<input id="sslclientcert-invalid" name="sslclientcert-invalid" type="checkbox" value="yes" <?php if ($pconfig['sslclientcert-invalid']=='yes') echo "checked"; ?> onclick='updatevisibility();' />
				Allows client with a invalid/expired/revoked or otherwise wrong certificate to connect.
				<div>Make sure to add appropriate acl's to check for valid certificates and verify errors using codes from the following list.
				<a target="_blank" href="https://www.openssl.org/docs/apps/verify.html#DIAGNOSTICS">https://www.openssl.org/docs/apps/verify.html#DIAGNOSTICS</a></div>
				
			</td>
		</tr>
		<tr class="haproxy_ssloffloading_enabled haproxy_primary">
			<td width="22%" valign="top" class="vncell">Client verification CA certificates</td>
			<td width="78%" class="vtable" colspan="2" valign="top">
			Client certificate will be verified against these CA certificates.
			<?
			$a_certificates = $pconfig['clientcert_ca'];
			$htmllist_caCertificates->Draw($a_certificates);
			?>
			</td>
		</tr>
		<tr class="haproxy_ssloffloading_enabled haproxy_primary">
			<td width="22%" valign="top" class="vncell">Client verification CRL</td>
			<td width="78%" class="vtable" colspan="2" valign="top">
			Client certificate will be verified against these CRL revocation lists.
			<?
			$a_certificates = $pconfig['clientcert_crl'];
			$htmllist_crlCertificates->Draw($a_certificates);
			?>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
	</table>
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr align="left">
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="Save" />  
				<input type="button" class="formbtn" value="Cancel" onclick="history.back()" />
				<?php if (isset($id) && $a_backend[$id]): ?>
				<input name="id" type="hidden" value="<?=$a_backend[$id]['name'];?>" />
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<td colspan='3'>
					<span class="vexpl"><b>NOTE:</b> You must add a firewall rule permitting access to this frontend!</span>
			</td>
		</tr>
	</table>
	</div></td></tr></table>
	</form>
<br/>
<script type="text/javascript">
<?
	// On gui descriptions when a closetype has been selected..
	phparray_to_javascriptarray($a_closetypes, "closetypes", Array('/*', '/*/name', '/*/descr'));
	
	// To find 'type' of frontend to show proper acl's ??
	phparray_to_javascriptarray($primaryfrontends,"primaryfrontends",Array('/*',
		'/*/name', '/*/ref', '/*/ref/type', '/*/ref/a_extaddr', '/*/ref/a_extaddr/item', '/*/ref/a_extaddr/item/*',
		'/*/ref/a_extaddr/item/*/extaddr_ssl'));

	phparray_to_javascriptarray($a_action, "showhide_actionfields",
		Array('/*', '/*/fields', '/*/fields/*', '/*/fields/*/name'));
	phparray_to_javascriptarray($a_acltypes, "showhide_aclfields",
		Array('/*', '/*/fields', '/*/fields/*', '/*/fields/*/name'));

	$htmllist_extaddr->outputjavascript();
	$htmllist_acls->outputjavascript();
	$htmllist_actions->outputjavascript();
	$errorfileslist->outputjavascript();
	$htmllist_sslCertificates->outputjavascript();
	$htmllist_caCertificates->outputjavascript();
	$htmllist_crlCertificates->outputjavascript();
?>
</script>
<script type="text/javascript">
	totalrows =  <?php echo $counter; ?>;
	
	var port_array  = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;
	var address_array = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;

	
	for(i=0;i < <?=count($a_extaddr)?>;i++){
		new AutoSuggestControl(document.getElementById('table_extaddrextaddr_custom'+i), new StateSuggestions(address_array));
		new AutoSuggestControl(document.getElementById('table_extaddrextaddr_port'+i), new StateSuggestions(port_array));
		// Initially set fields disabled where needed
		table_extaddr_listitem_change('table_extaddr','',i,null);
	}
	
	function table_acls_listitem_change(tableId, fieldId, rowNr, field) {
		if (fieldId = "toggle_details") {
			fieldId = "expression";
			field = d.getElementById(tableId+"expression"+rowNr);
		}
		if (fieldId = "expression") {
			var actiontype = field.value;
			
			var table = d.getElementById(tableId);
			
			for(var actionkey in showhide_aclfields) {
				var fields = showhide_aclfields[actionkey]['fields'];
				for(var fieldkey in fields){
					var fieldname = fields[fieldkey]['name'];
					var rowid = "tr_edititemdetails_"+rowNr+"_"+actionkey+fieldname;
					var element = d.getElementById(rowid);
					
					if (actionkey == actiontype)
						element.style.display = '';
					else
						element.style.display = 'none';
				}
			}
		}
	}
	
	function table_actions_listitem_change(tableId, fieldId, rowNr, field) {
		if (fieldId = "toggle_details") {
			fieldId = "action";
			field = d.getElementById(tableId+"action"+rowNr);
		}
		if (fieldId = "action") {
			var actiontype = field.value;
			
			var table = d.getElementById(tableId);
			
			for(var actionkey in showhide_actionfields) {
				var fields = showhide_actionfields[actionkey]['fields'];
				for(var fieldkey in fields){
					var fieldname = fields[fieldkey]['name'];
					var rowid = "tr_edititemdetails_"+rowNr+"_"+actionkey+fieldname;
					var element = d.getElementById(rowid);
					
					if (actionkey == actiontype)
						element.style.display = '';
					else
						element.style.display = 'none';
				}
			}
		}
	}
	
	updatevisibility();
</script>
<?php 
haproxy_htmllist_js();
include("fend.inc"); ?>
</body>
</html>
