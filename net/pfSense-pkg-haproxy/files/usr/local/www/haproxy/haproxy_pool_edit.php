<?php
/*
 * haproxy_pool_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2015 PiBa-NL
 * Copyright (c) 2008 Remco Hoef <remcoverhoef@pfsense.com>
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

if (!is_array($config['installedpackages']['haproxy']['ha_pools']['item'])) {
	$config['installedpackages']['haproxy']['ha_pools']['item'] = array();
}

$a_pools = &$config['installedpackages']['haproxy']['ha_pools']['item'];

$a_files = haproxy_get_fileslist();

if (isset($_POST['id'])) {
	$id = $_POST['id'];
} else {
	$id = $_GET['id'];
}

$tmp = get_backend_id($id);
if (is_numeric($tmp)) {
	$id = $tmp;
}

if (isset($_GET['dup'])) {
	$id = $_GET['dup'];
}

global $simplefields;
$simplefields = array(
"name",
"balance","balance_urilen","balance_uridepth","balance_uriwhole",
"transparent_clientip","transparent_interface",
"check_type","checkinter","log-health-checks","httpcheck_method","monitor_uri","monitor_httpversion","monitor_username","monitor_domain","monitor_agentport",
"agent_check","agent_port","agent_inter",
"connection_timeout","server_timeout","retries",
"stats_enabled","stats_username","stats_password","stats_uri","stats_scope","stats_realm","stats_admin","stats_node","stats_desc","stats_refresh",
"persist_stick_expire","persist_stick_tablesize","persist_stick_length","persist_stick_cookiename","persist_sticky_type",
"persist_cookie_enabled","persist_cookie_name","persist_cookie_mode","persist_cookie_cachable",
"strict_transport_security", "cookie_attribute_secure",
"email_level", "email_to"
);

$primaryfrontends = get_haproxy_frontends();
$none = array();
$none['']['name']="Address+Port:";
$primaryfrontends = $none + $primaryfrontends;

$default = array();
$default['']['name'] = "Default level from global";
$none = array();
$none['dontlog']['name'] = "Dont log";
$a_sysloglevel = $default + $none + $a_sysloglevel;

$fields_servers=array();
$fields_servers[0]['name']="status";
$fields_servers[0]['columnheader']="Mode";
$fields_servers[0]['colwidth']="5%";
$fields_servers[0]['type']="select";
$fields_servers[0]['size']="70px";
$fields_servers[0]['items']=&$a_servermodes;
$fields_servers[1]['name']="name";
$fields_servers[1]['columnheader']="Name";
$fields_servers[1]['colwidth']="20%";
$fields_servers[1]['type']="textbox";
$fields_servers[1]['size']="30";
$fields_servers[2]['name']="forwardto";
$fields_servers[2]['columnheader']="Forwardto";
$fields_servers[2]['colwidth']="15%";
$fields_servers[2]['type']="select";
$fields_servers[2]['size']="100px";
$fields_servers[2]['items']=&$primaryfrontends;
$fields_servers[2]['maxwidth']="100px";
$fields_servers[3]['name']="address";
$fields_servers[3]['columnheader']="Address";
$fields_servers[3]['colwidth']="10%";
$fields_servers[3]['type']="textbox";
$fields_servers[3]['size']="20";
$fields_servers[4]['name']="port";
$fields_servers[4]['columnheader']="Port";
$fields_servers[4]['colwidth']="5%";
$fields_servers[4]['type']="textbox";
$fields_servers[4]['size']="5";
$fields_servers[4]['maxwidth']="50px";
$fields_servers[5]['name']="ssl";
$fields_servers[5]['columnheader']="SSL";
$fields_servers[5]['colwidth']="5%";
$fields_servers[5]['type']="checkbox";
$fields_servers[5]['size']="30";
$fields_servers[6]['name']="weight";
$fields_servers[6]['columnheader']="Weight";
$fields_servers[6]['colwidth']="8%";
$fields_servers[6]['type']="textbox";
$fields_servers[6]['size']="5";
$fields_servers[6]['maxwidth']="50px";

$listitem_none['']['name']="None";

$certs_ca = haproxy_get_certificates('ca');
$certs_ca = $listitem_none + $certs_ca;
$certs_client = haproxy_get_certificates('server,user');
$certs_client = $listitem_none + $certs_client;
$certs_crl = haproxy_get_crls();
$certs_crl = $listitem_none + $certs_crl;

$fields_servers_details=array();
$fields_servers_details[0]['name']="sslserververify";
$fields_servers_details[0]['columnheader']="Check certificate";
$fields_servers_details[0]['description']="SSL servers only, The server certificate will be verified against the CA and CRL certificate configured below.";
$fields_servers_details[0]['colwidth']="5%";
$fields_servers_details[0]['type']="checkbox";
$fields_servers_details[0]['size']="5";
$fields_servers_details[1]['name']="verifyhost";
$fields_servers_details[1]['columnheader']="Certificate check CN";
$fields_servers_details[1]['description']="SSL servers only, when set, must match the hostnames in the subject and subjectAlternateNames of the certificate provided by the server.";
$fields_servers_details[1]['colwidth']="5%";
$fields_servers_details[1]['type']="textbox";
$fields_servers_details[1]['size']="50";
$fields_servers_details[2]['name']="ssl-server-ca";
$fields_servers_details[2]['columnheader']="CA";
$fields_servers_details[2]['description']="SSL servers only, Select the CA authority to check the server certificate against.";
$fields_servers_details[2]['colwidth']="15%";
$fields_servers_details[2]['type']="select";
$fields_servers_details[2]['size']="200px";
$fields_servers_details[2]['items']=$certs_ca;
$fields_servers_details[3]['name']="ssl-server-crl";
$fields_servers_details[3]['columnheader']="CRL";
$fields_servers_details[3]['description']="SSL servers only, Select the CRL to check revoked certificates.";
$fields_servers_details[3]['colwidth']="15%";
$fields_servers_details[3]['type']="select";
$fields_servers_details[3]['size']="200px";
$fields_servers_details[3]['items']=$certs_crl;
$fields_servers_details[4]['name']="ssl-server-clientcert";
$fields_servers_details[4]['columnheader']="Client certificate";
$fields_servers_details[4]['description']="SSL servers only, This certificate will be sent if the server send a client certificate request.";
$fields_servers_details[4]['colwidth']="15%";
$fields_servers_details[4]['type']="select";
$fields_servers_details[4]['size']="200px";
$fields_servers_details[4]['items']=$certs_client;
$fields_servers_details[5]['name']="cookie";
$fields_servers_details[5]['columnheader']="Cookie";
$fields_servers_details[5]['description']="Persistence only, Used to identify server when cookie persistence is configured for the backend.";
$fields_servers_details[5]['colwidth']="10%";
$fields_servers_details[5]['type']="textbox";
$fields_servers_details[5]['size']="10";
$fields_servers_details[6]['name']="maxconn";
$fields_servers_details[6]['columnheader']="Max conn";
$fields_servers_details[6]['description']="Tuning, If the number of incoming concurrent requests goes higher than this value, they will be queued";
$fields_servers_details[6]['colwidth']="15%";
$fields_servers_details[6]['type']="textbox";
$fields_servers_details[6]['size']="10";
$fields_servers_details[7]['name']="advanced";
$fields_servers_details[7]['columnheader']="Advanced";
$fields_servers_details[7]['description']="Advanced, Allows for adding custom HAProxy settings to the server. These are passed as written, use escaping where needed.";
$fields_servers_details[7]['colwidth']="15%";
$fields_servers_details[7]['type']="textbox";
$fields_servers_details[7]['size']="80";

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

$serverslist = new HaproxyHtmlList("tableA_servers", $fields_servers);
$serverslist->keyfield = "name";
$serverslist->fields_details = $fields_servers_details;

$errorfileslist = new HaproxyHtmlList("table_errorfile", $fields_errorfile);
$errorfileslist->keyfield = "errorcode";

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
	$result = "";
	if ($editable) {
		$result = $object->haproxy_htmllist_drawcell($item, $itemvalue, $editable, $itemname, $counter);
	} else {
		$result = $itemvalue;
	}
	return $result;
}

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
$htmllist_acls->fields_details_showfieldfunction = fields_acls_details_showfieldfunction;
$htmllist_acls->editmode = true;

function fields_actions_details_showfieldfunction($htmltable, $itemname, $values) {
	$items = $htmltable->fields[0]['items'];
	$action = $values['action'];
	return fields_details_showfieldfunction($items, $action, $itemname);
}
$htmllist_actions = new HaproxyHtmlList("table_actions", $fields_actions);
$htmllist_actions->fields_details = $fields_actions_details;
$htmllist_actions->fields_details_showfieldfunction = fields_actions_details_showfieldfunction;
$htmllist_actions->keyfield = "name";


if (isset($id) && $a_pools[$id]) {
	$pconfig['a_acl'] = &$a_pools[$id]['a_acl']['item'];
	haproxy_check_isarray($pconfig['a_acl']);
	$pconfig['a_actionitems'] = &$a_pools[$id]['a_actionitems']['item'];
	haproxy_check_isarray($pconfig['a_actionitems']);
	
	$pconfig['advanced'] = base64_decode($a_pools[$id]['advanced']);
	$pconfig['advanced_backend'] = base64_decode($a_pools[$id]['advanced_backend']);
	
	$a_servers = $a_pools[$id]['ha_servers']['item'];	
	
	foreach($simplefields as $stat) {
		$pconfig[$stat] = $a_pools[$id][$stat];
	}
	
	$a_errorfiles = &$a_pools[$id]['errorfiles']['item'];
	if (!is_array($a_errorfiles)) {
		$a_errorfiles = array();
	}
}

if (isset($_GET['dup'])) {
	unset($id);
	$pconfig['name'] .= "-copy";
}
$changedesc = "Services: HAProxy: Backend server pool: ";
$changecount = 0;

if ($_POST) {
	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;
	
	$reqdfields = explode(" ", "name");
	$reqdfieldsn = explode(",", "Name");		

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['stats_enabled']) {
		$reqdfields = explode(" ", "name stats_uri");
		$reqdfieldsn = explode(",", "Name,Stats Uri");		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
		if ($_POST['stats_username']) {
			$reqdfields = explode(" ", "stats_password stats_realm");
			$reqdfieldsn = explode(",", "Stats Password,Stats Realm");		
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
		}
	}
	
	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['name'])) {
		$input_errors[] = "The field 'Name' contains invalid characters.";
	}
	if ($_POST['checkinter'] !== "" && !is_numeric($_POST['checkinter'])) {
		$input_errors[] = "The field 'Check frequency' value is not a number.";
	}
	if ($_POST['connection_timeout'] !== "" && !is_numeric($_POST['connection_timeout'])) {
		$input_errors[] = "The field 'Connection timeout' value is not a number.";
	}
	if ($_POST['server_timeout'] !== "" && !is_numeric($_POST['server_timeout'])) {
		$input_errors[] = "The field 'Server timeout' value is not a number.";
	}
	if ($_POST['retries'] !== "" && !is_numeric($_POST['retries'])) {
		$input_errors[] = "The field 'Retries' value is not a number.";
	}
	// the colon ":" is invalid in the username, other than that pretty much any character can be used.
	if (preg_match("/[^a-zA-Z0-9!-\/;-~ ]/", $_POST['stats_username'])) {
		$input_errors[] = "The field 'Stats Username' contains invalid characters.";
	}
	// the colon ":" can also be used in the password
	if (preg_match("/[^a-zA-Z0-9!-~ ]/", $_POST['stats_password'])) {
		$input_errors[] = "The field 'Stats Password' contains invalid characters.";
	}
	if (preg_match("/[^a-zA-Z0-9\-_]/", $_POST['stats_node'])) {
		$input_errors[] = "The field 'Stats Node' contains invalid characters. Should be a string with digits(0-9), letters(A-Z, a-z), hyphen(-) or underscode(_)";
	}
	/* Ensure that our pool names are unique */
	for ($i=0; isset($config['installedpackages']['haproxy']['ha_pools']['item'][$i]); $i++) {
		if (($_POST['name'] == $config['installedpackages']['haproxy']['ha_pools']['item'][$i]['name']) && ($i != $id)) {
			$input_errors[] = "This pool name has already been used.  Pool names must be unique.";
		}
	}

	$pconfig['a_acl'] = $htmllist_acls->haproxy_htmllist_get_values();
	$pconfig['a_actionitems'] = $htmllist_actions->haproxy_htmllist_get_values();
	$a_servers = $serverslist->haproxy_htmllist_get_values();
	foreach($a_servers as $server){
		$server_name    = $server['name'];
		$server_address = $server['address'];
		$server_port    = $server['port'];
		$server_weight  = $server['weight'];

		if (preg_match("/[^a-zA-Z0-9\.\-_]/", $server_name)) {
			$input_errors[] = "The field 'Name' contains invalid characters.";
		}

		if (!isset($server['forwardto']) || $server['forwardto'] == "") {
			if (!is_ipaddr($server_address) && !is_hostname($server_address) && !haproxy_is_frontendname($server_address)) {
				$input_errors[] = "The field 'Address' for server $server_name is not a valid ip address or hostname." . $server_address;
			}
		} else {
			if ((!empty($server_address)) || ($server_port && !is_numeric($server_port))) {
				$input_errors[] = "'Address' and 'port' should be empty when a 'Forwardto' frontend is chosen other than 'Address+Port'.";
			}
		}

		if (!preg_match("/.{2,}/", $server_name)) {
			$input_errors[] = "The field 'Name' is required (and must be at least 2 characters).";
		}
		if ($server_weight && !is_numeric($server_weight)) {
			$input_errors[] = "The field 'Weight' value is not a number.";
		}
		if ($server_port && !is_numeric($server_port)) {
			$input_errors[] = "The field 'Port' value is not a number.";
		}
	}
	
	$a_errorfiles = $errorfileslist->haproxy_htmllist_get_values();
	
	if ($_POST['strict_transport_security'] !== "" && !is_numeric($_POST['strict_transport_security'])) {
		$input_errors[] = "The field 'Strict-Transport-Security' is not empty or a number.";
	}

	$pool = array();
	if(isset($id) && $a_pools[$id]) {
		$pool = $a_pools[$id];
	}
		
	if (!empty($pool['name']) && ($pool['name'] != $_POST['name'])) {
		//old $pool['name'] can be empty if a new or cloned item is saved, nothing should be renamed then
		// name changed:
		$oldvalue = $pool['name'];
		$newvalue = $_POST['name'];
		
		$a_backend = &$config['installedpackages']['haproxy']['ha_backends']['item'];
		if (!is_array($a_backend)) {
			$a_backend = array();
		}

		for ( $i = 0; $i < count($a_backend); $i++) {
			$backend = &$a_backend[$i];
			if ($a_backend[$i]['backend_serverpool'] == $oldvalue) {
				$a_backend[$i]['backend_serverpool'] = $newvalue;
			}
			if (is_array($backend['a_actionitems']['item'])) {
				foreach($backend['a_actionitems']['item'] as &$item) {
					if ($item['action'] == "use_backend") {
						if ($item['use_backendbackend'] == $oldvalue) {
							$item['use_backendbackend'] = $newvalue;
						}
					}
				}
			}
		}
	}

	if($pool['name'] != "") {
		$changedesc .= " modified pool: '{$pool['name']}'";
	}
	$pool['ha_servers']['item'] = $a_servers;
	$pool['a_acl']['item'] = $pconfig['a_acl'];
	$pool['a_actionitems']['item'] = $pconfig['a_actionitems'];

	update_if_changed("advanced", $pool['advanced'], base64_encode($_POST['advanced']));
	update_if_changed("advanced_backend", $pool['advanced_backend'], base64_encode($_POST['advanced_backend']));

	global $simplefields;
	foreach($simplefields as $stat) {
		update_if_changed($stat, $pool[$stat], $_POST[$stat]);
	}

	if (isset($id) && $a_pools[$id]) {
		$a_pools[$id] = $pool;
	} else {
		$a_pools[] = $pool;
	}
	if (!isset($input_errors)) {
		if ($changecount > 0) {
			touch($d_haproxyconfdirty_path);
			write_config($changedesc);
		}
		header("Location: haproxy_pools.php");
		exit;
	}
}

$closehead = false;
$pgtitle = array("Services", "HAProxy", "Backend server pool: Edit");
include("head.inc");
haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "backend");

// 'processing' done, make all simple fields usable in html.
foreach($simplefields as $field){
	$pconfig[$field] = htmlspecialchars($pconfig[$field]);
}

?>
  <style type="text/css">
	.haproxy_stats_visible{display:none;}
	.haproxy_check_enabled{display:none;}
	.haproxy_check_http{display:none;}
	.haproxy_check_username{display:none;}
	.haproxy_check_smtp{display:none;}
	.haproxy_transparent_clientip{display:none;}
	.haproxy_check_agent{display:none;}
	.haproxy_agent_check{display:none;}
	.haproxy_cookie_visible{display:none;}
	.haproxy_help_serverlist{display:none;}
  </style>
</head>
<script type="text/javascript">
	function clearcombo(){
	  for (var i=document.iform.serversSelect.options.length-1; i>=0; i--){
		document.iform.serversSelect.options[i] = null;
	  }
	  document.iform.serversSelect.selectedIndex = -1;
	}

	function setCSSdisplay(cssID, display)
	{
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
	function toggleCSSdisplay(cssID)
	{
		var ss = document.styleSheets;
		for (var i=0; i<ss.length; i++) {
			var rules = ss[i].cssRules || ss[i].rules;
			for (var j=0; j<rules.length; j++) {
				if (rules[j].selectorText === cssID) {
					rules[j].style.display = rules[j].style.display === "none" ? "" : "none";
				}
			}
		}
	}
	
	function updatevisibility()
	{
		d = document;
		// IE needs components found into javascript variables
		stats_enabled = d.getElementById("stats_enabled");
		persist_cookie_enabled = d.getElementById("persist_cookie_enabled");
		agent_check = d.getElementById("agent_check");
		sticky_type_description = d.getElementById("sticky_type_description");
		
		setCSSdisplay(".haproxy_stats_visible", stats_enabled.checked);
		setCSSdisplay(".haproxy_cookie_visible", persist_cookie_enabled.checked);
		
		check_type = d.getElementById("check_type").value;
		check_type_description = d.getElementById("check_type_description");
		check_type_description.innerHTML=checktypes[check_type]["descr"]; 
		
		persist_cookie_mode = d.getElementById("persist_cookie_mode").value;
		persist_cookie_mode_description = d.getElementById("persist_cookie_mode_description");
		persist_cookie_mode_description.innerHTML=cookiemode[persist_cookie_mode]["descr"]; 
		persist_cookie_mode_description.setAttribute('style','padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000; font-size: 8pt; height:30px');
		persist_cookie_mode_description.setAttribute('style','padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000; font-size: 8pt; height:'+persist_cookie_mode_description.scrollHeight+'px');
		
		setCSSdisplay(".haproxy_check_enabled", check_type !== 'none');
		setCSSdisplay(".haproxy_check_http", check_type === 'HTTP');
		setCSSdisplay(".haproxy_check_username", check_type === 'MySQL' ||  check_type === 'PostgreSQL');
		setCSSdisplay(".haproxy_check_smtp", check_type === 'SMTP' ||  check_type === 'ESMTP');
		setCSSdisplay(".haproxy_check_agent", check_type === 'Agent');
		
		setCSSdisplay(".haproxy_agent_check", agent_check.checked);

		transparent_clientip = d.getElementById("transparent_clientip");
		setCSSdisplay(".haproxy_transparent_clientip", transparent_clientip.checked);
		
		
		persist_sticky_type = d.getElementById("persist_sticky_type").value;
		//hideClass('haproxytestcfg', false);
		hideClass('haproxy_stick_tableused', persist_sticky_type === 'none');
		hideClass('haproxy_stick_cookiename', persist_sticky_type !== 'stick_rdp_cookie' &&  persist_sticky_type !== 'stick_cookie_value');
		
		cookie_example = sticky_type[persist_sticky_type]['cookiedescr'];
		stick_cookiename_description = d.getElementById("stick_cookiename_description");
		stick_cookiename_description.innerHTML = cookie_example;
		sticky_type_description.innerHTML = sticky_type[persist_sticky_type]['descr'];
		
		monitor_username = d.getElementById("monitor_username");
		sqlcheckusername = d.getElementById("sqlcheckusername");
		if(!browser_InnerText_support){
			sqlcheckusername.textContent = monitor_username.value;
		} else{
			sqlcheckusername.innerText = monitor_username.value;
		}
	}
</script>
<?php
if (isset($input_errors)) {
	print_input_errors($input_errors);
}

$counter=0;

//TODO - show 'required' fields (bold in 2.2 ..)  - max length for textinputs? -default selections?

$form = new Form;

$section = new Form_Section('Edit HAProxy Backend server pool');
$section->addInput(new Form_Input('name', 'Name', 'text', $pconfig['name']
))->setHelp('');
$section->addInput(new Form_StaticText(
	'Server list', 
$serverslist->Draw($a_servers).
<<<EOT
	Field explanations: 
	<table class="infoblock" style="border:1px dashed green" cellspacing="0">
	<tr><td class="vncell">
	Mode: </td><td class="vncell">Active: server will be used normally<br/>
	Backup: server is only used in load balancing when all other non-backup servers are unavailable<br/>
	Disabled: server is marked down in maintenance mode<br/>
	Inactive: server will not be available for use
	</td></tr><tr><td class="vncell">
	Name: </td><td class="vncell">Used to as a name for the server in for example the stats<br/>EXAMPLE: MyWebServer
	</td></tr><tr><td class="vncell">
	Address: </td><td class="vncell">IP or hostname(only resolved on start-up.)<br/>EXAMPLE: 192.168.1.22 , fe80::1000:2000:3000:4000%em0 , WebServer1.localdomain
	</td></tr><tr><td class="vncell">
	Port: </td><td class="vncell">The port of the backend.<br/>EXAMPLE: 80 or 443<br/>
	</td></tr><tr><td class="vncell">
	SSL: </td><td class="vncell">Is the backend using SSL (commonly with port 443)<br/>
	</td></tr><tr><td class="vncell">
	Weight: </td><td class="vncell">A weight between 0 and 256, this setting can be used when multiple servers on different hardware need to be balanced with with a different part the traffic. A server with weight 0 wont get new traffic. Default if empty: 1
	</td></tr><tr><td class="vncell">
	Cookie: </td><td class="vncell">the value of the cookie used to identify a server (only when cookie-persistence is enabled below)
	</td></tr><tr><td class="vncell">
	Advanced: </td><td class="vncell">More advanced settings like rise,fall,error-limit,send-proxy and others can be configured here.<br/>For a full list of options see the <a target="_blank" href="http://cbonte.github.io/haproxy-dconv/configuration-1.5.html#5.2">HAProxy manual: Server and default-server options</a>
	</td></tr>
	</table>
EOT
));

$mode = $pconfig['balance'];
$section->addInput(new Form_Checkbox(
	'balance',
	'Balance',
	"None",
	empty($mode),
	''
))->displayAsRadio()->setHelp('This allows writing your own custom balance settings into the advanced section.
	Or when you have no need for balancing with only 1 server.');

$section->addInput(new Form_Checkbox(
	'balance',
	null,
	"Round robin",
	$mode == 'roundrobin',
	'roundrobin'
))->displayAsRadio()->setHelp("Each server is used in turns, according to their weights.
	This is the smoothest and fairest algorithm when the server's
	processing time remains equally distributed. This algorithm
	is dynamic, which means that server weights may be adjusted
	on the fly for slow starts for instance.");

$section->addInput(new Form_Checkbox(
	'balance',
	null,
	"Static Round Robin",
	$mode == 'static-rr',
	'static-rr'
))->displayAsRadio()->setHelp("Each server is used in turns, according to their weights.
	This algorithm is as similar to roundrobin except that it is
	static, which means that changing a server's weight on the
	fly will have no effect. On the other hand, it has no design
	limitation on the number of servers, and when a server goes
	up, it is always immediately reintroduced into the farm, once
	the full map is recomputed. It also uses slightly less CPU to
	run (around -1%).");

$section->addInput(new Form_Checkbox(
	'balance',
	null,
	"Least Connections",
	$mode == 'leastconn',
	'leastconn'
))->displayAsRadio()->setHelp('The server with the lowest number of connections receives the
	connection. Round-robin is performed within groups of servers
	of the same load to ensure that all servers will be used. Use
	of this algorithm is recommended where very long sessions are
	expected, such as LDAP, SQL, TSE, etc... but is not very well
	suited for protocols using short sessions such as HTTP. This
	algorithm is dynamic, which means that server weights may be
	adjusted on the fly for slow starts for instance.');

$section->addInput(new Form_Checkbox(
	'balance',
	null,
	"Source",
	$mode == 'source',
	'source'
))->displayAsRadio()->setHelp("The source IP address is hashed and divided by the total
	weight of the running servers to designate which server will
	receive the request. This ensures that the same client IP
	address will always reach the same server as long as no
	server goes down or up. If the hash result changes due to the
	number of running servers changing, many clients will be
	directed to a different server. This algorithm is generally
	used in TCP mode where no cookie may be inserted. It may also
	be used on the Internet to provide a best-effort stickyness
	to clients which refuse session cookies. This algorithm is
	static, which means that changing a server's weight on the
	fly will have no effect.");

$section->addInput(new Form_Checkbox(
	'balance',
	null,
	"Uri (HTTP backends only)",
	$mode == 'uri',
	'uri'
))->displayAsRadio()->setHelp(
'This algorithm hashes either the left part of the URI (before
	the question mark) or the whole URI (if the "whole" parameter
	is present) and divides the hash value by the total weight of
	the running servers. The result designates which server will
	receive the request. This ensures that the same URI will
	always be directed to the same server as long as no server
	goes up or down. This is used with proxy caches and
	anti-virus proxies in order to maximize the cache hit rate.
	Note that this algorithm may only be used in an HTTP backend.<br/>
	<input name="balance_urilen" size="10" value="'. $pconfig['balance_urilen'] . '" />Len (optional) <br/>
	The "len" parameter
	indicates that the algorithm should only consider that many
	characters at the beginning of the URI to compute the hash.<br/>
	<input name="balance_uridepth" size="10" value="' . $pconfig['balance_uridepth'] .'" />Depth (optional) <br/>
	The "depth" parameter indicates the maximum directory depth
	to be used to compute the hash. One level is counted for each
	slash in the request.<br/>
	<input id="balance_uriwhole" name="balance_uriwhole" type="checkbox" value="yes" '. (($pconfig['balance_uriwhole']=='yes')? "checked" : "") .' />
	Allow using whole URI including url parameters behind a question mark.'
);

$section->addInput(new Form_Input('advanced', 'Per server pass thru', 'text', $pconfig['advanced']
))->setHelp('NOTE: paste text into this box that you would like to pass thru. Applied to each "server" line.');

$textrowcount = max(substr_count($pconfig['advanced_backend'],"\n"), 2) + 2;
$section->addInput(new Form_Textarea (
	'advanced_backend',
	'Backend pass thru',
	$pconfig['advanced_backend']
))->setRows($textrowcount)->setNoWrap()->setHelp('NOTE: paste text into this box that you would like to pass thru. Applied to the backend section.');

$form->add($section);

$section = new Form_Section('Access control lists and actions');
$section->addInput(new Form_StaticText(
	'Access Control lists',
	$htmllist_acls->Draw($pconfig['a_acl']).
<<<EOT
			Acl's with the same name will be 'combined' using OR criteria, use these as condition for actions below. Example:
			<div class="infoblock">
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
			For more information about ACL's please see <a href='http://cbonte.github.io/haproxy-dconv/1.6/configuration.html#7' target='_blank'>HAProxy Documentation</a> Section 7 - Using ACL's<br/>			Actions should be added below to use the result of the acl as a conditional parameter.
			</div>
EOT
));

$section->addInput(new Form_StaticText(
	'Actions',
	$htmllist_actions->Draw($pconfig['a_actionitems']).
<<<EOT
	Example:
	<div class="infoblock">
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
	</div>
EOT
));
$form->add($section);

$section = new Form_Section_class('Health checking');
$section->addInput(new Form_Select(
	'check_type',
	'Health check method',
	$pconfig['check_type']?$pconfig['check_type']:"HTTP",
	haproxy_keyvalue_array($a_checktypes)
))->setHelp('<textarea readonly="yes" cols="60" rows="2" id="check_type_description" name="check_type_description" style="padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000; font-size: 8pt;"></textarea>');

//TODO milliseconds behind field.
$section->addInput(new Form_Input('checkinter', 'Check frequency', 'text', $pconfig['checkinter']
),"haproxy_check_enabled")->setHelp('milliseconds<br/> For HTTP/HTTPS defaults to 1000 if left blank. For TCP no check will be performed if left empty.');
$section->addInput(new Form_Checkbox(
	'log-health-checks',
	'Log checks',
	"When this option is enabled, any change of the health check status or to the server's health will be logged.",
	$pconfig['log-health-checks']
),"haproxy_check_enabled")->setHelp("By default, failed health check are logged if server is UP and successful health checks are logged if server is DOWN, so the amount of additional information is limited.");
$section->addInput(new Form_Select(
	'httpcheck_method',
	'Http check method',
	$pconfig['httpcheck_method'],
	haproxy_keyvalue_array($a_httpcheck_method)
),"haproxy_check_http")->setHelp('OPTIONS is the method usually best to perform server checks, HEAD and GET can also be used.
	If the server gets marked as down in the stats page then changing this to GET usually has the biggest chance of working, but might cause more processing overhead on the websever and is less easy to filter out of its logs.');
$section->addInput(new Form_Input('monitor_uri', 'Url used by http check requests.', 'text', $pconfig['monitor_uri']
),"haproxy_check_http")->setHelp('Defaults to / if left blank.');
$section->addInput(new Form_Input('monitor_httpversion', 'Http check version', 'text', $pconfig['monitor_httpversion']
),"haproxy_check_http")->setHelp(<<<EOT
	Defaults to "HTTP/1.0" if left blank.
	Note that the Host field is mandatory in HTTP/1.1, and as a trick, it is possible to pass it
	after "\\r\\n" following the version string like this:<br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<code>HTTP/1.1\\r\\nHost:\\ www</code><br/>
	Also some hosts might require an accept parameter like this:<br/>
	&nbsp;&nbsp;&nbsp;&nbsp;<code>HTTP/1.0\\r\\nHost:\\ webservername:8080\\r\\nAccept:\\ */*</code>
EOT
);
$section->addInput(new Form_Input('monitor_username', 'Check with Username', 'text', $pconfig['monitor_username']
),"haproxy_check_username")->setHelp(<<<EOT
This is the username which will be used when connecting to MySQL/PostgreSQL server.
				<pre>
USE mysql;
CREATE USER '<span id="sqlcheckusername"></span>'@'&lt;pfSenseIP&gt;';
FLUSH PRIVILEGES;</pre>
EOT
);
$section->addInput(new Form_Input('monitor_domain', 'Domain', 'text', $pconfig['monitor_domain']
),"haproxy_check_smtp")->setHelp('');
$section->addInput(new Form_Input('monitor_agentport', 'Agentport', 'monitor_agentport', $pconfig['name']
),"haproxy_check_agent")->setHelp('Fill in the TCP portnumber the healthcheck should be performed on.');

$form->add($section);

$section = new Form_Section_class('Agent checks');
$section->addInput(new Form_Checkbox(
	'agent_check',
	'Agent checks',
	'Use agent checks',
	$pconfig['agent_check']
))->setHelp("Use a TCP connection to read an ASCII string of the form 100%,75%,drain,down (more about this in the <a href='http://cbonte.github.io/haproxy-dconv/1.6/configuration.html#5.2-agent-check' target='_blank'>haproxy manual</a>)");
$section->addInput(new Form_Input('agent_port', 'Agent port', 'number', $pconfig['agent_port']
),"haproxy_agent_check")->setHelp('Fill in the TCP portnumber the healthcheck should be performed on.');
$section->addInput(new Form_Input('agent_inter', 'Agent interval', 'text', $pconfig['agent_inter']
),"haproxy_agent_check")->setHelp('Interval between two agent checks, defaults to 2000 ms.');

$form->add($section);

$section = new Form_Section('Advanced settings');
$section->addInput(new Form_Input('connection_timeout', 'Connection timeout', 'text', $pconfig['connection_timeout']
))->setHelp('The time (in milliseconds) we give up if the connection does not complete within (default 30000).');
$section->addInput(new Form_Input('server_timeout', 'Server timeout', 'text', $pconfig['server_timeout']
))->setHelp('The time (in milliseconds) we accept to wait for data from the server, or for the server to accept data (default 30000).');
$section->addInput(new Form_Input('retries', 'Retries', 'text', $pconfig['retries']
))->setHelp(<<<EOT
	After a connection failure to a server, it is possible to retry, potentially
	on another server. This is useful if health-checks are too rare and you don't
	want the clients to see the failures. The number of attempts to reconnect is
	set by the "retries" parameter.
EOT
);
$interfaces = get_configured_interface_with_descr();
$section->addInput(new Form_StaticText(
	'Transparent ClientIP', <<<EOT
	<div class="alert alert-warning" role="alert">
		<p>
			WARNING Activating this option will load rules in IPFW and might interfere with CaptivePortal and possibly other services due 
			to the way server return traffic must be 'captured' with a automatically created fwd rule. This also breaks directly accessing 
			the (web)server on the ports configured above. Also a automatic sloppy pf rule is made to allow HAProxy to server traffic.<br/>
			Workaround exists only by configuring a second port or IP on the destination server for direct access of the website.
		</p>
	</div>
EOT
.(new Form_Checkbox(
	'transparent_clientip',
	'',
	"Use Client-IP to connect to backend servers.",
	$pconfig['transparent_clientip']
))->setHelp("By default, failed health check are logged if server is UP and successful health checks are logged if server is DOWN, so the amount of additional information is limited."
)
.
(new Form_Select(
	'transparent_interface',
	'',
	$pconfig['transparent_interface']?$pconfig['transparent_interface']:"lan",
	$interfaces
))->addClass("haproxy_transparent_clientip")->setHelp("Interface that will connect to the backend server. (this will generally be your LAN or OPT1(dmz) interface)")
.
<<<EOT
	
	Connect transparently to the backend server's so the connection seams to come straight from the client ip address.
	To work properly this requires the reply traffic to pass through pfSense by means of correct routing.<br/>
	When using IPv6 only routable ip addresses can be used, host names or link-local addresses (FE80) will not work.<br/>				
	(uses the option "source 0.0.0.0 usesrc clientip" or "source ipv6@ usesrc clientip")
	<br/><br/>
	Note : When this is enabled for any backend HAProxy will run as 'root' instead of chrooting to a lower privileged user, this reduces security in case a vulnerability is found.
EOT
));
$form->add($section);

$section = new Form_Section_class('Cookie persistence');
$section->addInput(new Form_Checkbox(
	'persist_cookie_enabled',
	'Cookie Enabled',
	'Enables cookie based persistence. (only used on "http" frontends)',
	$pconfig['persist_cookie_enabled']
))->setHelp('');
$section->addInput(new Form_StaticText(
	'Server Cookies',
	"<strong>Make sure to configure a different cookie on every server in this backend.</strong>"
),"haproxy_cookie_visible")
->setHelp(''); // TODO why is this needed to get a good screenlayout? (fieldnames of later fields before the inputbox..)

$section->addInput(new Form_Input('persist_cookie_name', 'Cookie Name', 'text', $pconfig['persist_cookie_name']
),"haproxy_cookie_visible")->setHelp('The string name to track in Set-Cookie and Cookie HTTP headers.<br/>
	EXAMPLE: MyLoadBalanceCookie JSESSIONID PHPSESSID ASP.NET_SessionId');
$section->addInput(new Form_Select(
	'persist_cookie_mode',
	'Cookie Mode',
	$pconfig['persist_cookie_mode'],
	haproxy_keyvalue_array($a_cookiemode)
),"haproxy_cookie_visible")->setHelp('Determines how HAProxy inserts/prefixes/replaces or examines cookie and set-cookie headers.<br/>
	EXAMPLE: with an existing PHPSESSIONID you can for example use "Session-prefix" or to create a new cookie use "Insert-silent".<br/>
	<br/>
	<textarea readonly="yes" cols="60" rows="2" id="persist_cookie_mode_description" name="persist_cookie_mode_description" style="padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000; font-size: 8pt;"></textarea>');
$section->addInput(new Form_Checkbox(
	'persist_cookie_cachable',
	'Cookie Cachable',
	'Allows shared caches to cache the server response.',
	$pconfig['persist_cookie_cachable']
),"haproxy_cookie_visible");

$form->add($section);

$section = new Form_Section_class('Stick-table persistence');
$form->add($section);
$section->addInput(new Form_StaticText(
	'',
	"These options are used to make sure seperate requests from a single client go to the same backend. This can be required for servers that keep track of for example a shopping cart."
));
$section->addInput(new Form_Select(
	'persist_sticky_type',
	'Stick tables',
	$pconfig['persist_sticky_type'],
	haproxy_keyvalue_array($a_sticky_type))
)->setHelp('Sticktables that are kept in memory, and when matched make sure the same server will be used.<br/>
	<textarea readonly="yes" cols="60" rows="2" id="sticky_type_description" name="sticky_type_description" style="padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000; font-size: 8pt;"></textarea>');
$section->addInput(new Form_Input('persist_stick_cookiename', 'Stick cookie name', 'text', $pconfig['persist_stick_cookiename']
),"haproxy_stick_cookiename")->setHelp('Cookiename to use for sticktable<br/>
	<span id="stick_cookiename_description"></span>');
$section->addInput(new Form_Input('persist_stick_length', 'Stick cookie length', 'text', $pconfig['persist_stick_length']
),"haproxy_stick_cookiename")->setHelp('The maximum number of characters that will be stored in a "string" type stick-table<br/>
	<span id="stick_cookiename_description"></span>');
$section->addInput(new Form_Input('persist_stick_expire', 'Stick-table expire', 'text', $pconfig['persist_stick_expire']
),"haproxy_stick_tableused")->setHelp('d=days h=hour m=minute s=seconds ms=miliseconds(default)<br/>
	Defines the maximum duration of an entry in the stick-table since it was last created, refreshed or matched.<br/>
	EXAMPLE: 30m ');
$section->addInput(new Form_Input('persist_stick_tablesize', 'Stick-table size', 'text', $pconfig['persist_stick_tablesize']
),"haproxy_stick_tableused")->setHelp('maximum number of entries supports suffixes "k", "m", "g" for 2^10, 2^20 and 2^30 factors.<br/>
	Is the maximum number of entries that can fit in the table. This value directly impacts memory usage. Count approximately
	50 bytes per entry, plus the size of a string if any.<br/>
	EXAMPLE: 50k');

$section = new Form_Section('Email notifications');
$form->add($section);
$section->addInput(new Form_Select(
	'email_level',
	'Mail level',
	$pconfig['email_level'],
	haproxy_keyvalue_array($a_sysloglevel))
)->setHelp('Define the maximum loglevel to send emails for.');

$section->addInput(new Form_Input('email_to', 'Mail to', 'text', $pconfig['email_to']
))->setHelp('Email address to send emails to, defaults to the value set on the global settings tab if left empty.');

$section = new Form_Section_class('Statistics');

$section->addInput(new Form_Checkbox(
	'stats_enabled',
	'Stats Enabled',
	'Enables the haproxy statistics page (only used on "http" frontends)',
	$pconfig['stats_enabled']
))->setHelp('');

$section->addInput(new Form_Input('stats_uri', 'Stats Uri', 'text', $pconfig['stats_uri']
),"haproxy_stats_visible")->setHelp('This url can be used when this same backend is used for passing connections to backends<br/>EXAMPLE: / or /haproxy?stats');

$section->addInput(new Form_Input('stats_scope', 'Stats Scope', 'text', $pconfig['stats_scope']
),"haproxy_stats_visible")->setHelp('Determines which frontends and backends are shown, leave empty to show all.<br/>EXAMPLE: frontendA,backend1,backend2');

$section->addInput(new Form_Input('stats_realm', 'Stats Realm', 'text', $pconfig['stats_realm']
),"haproxy_stats_visible")->setHelp('The realm is shown when authentication is requested by haproxy.<br/>EXAMPLE: haproxystats');

$section->addInput(new Form_Input('stats_username', 'Stats Username', 'text', $pconfig['stats_username']
),"haproxy_stats_visible")->setHelp('EXAMPLE: admin');

//TODO hide password completely from client ? DMYPWD ?
$section->addInput(new Form_Input('stats_password', 'Stats Password', 'text', $pconfig['stats_password']
),"haproxy_stats_visible")->setHelp('EXAMPLE: 1Your2Secret3P@ssword')->setType("password");

$section->addInput(new Form_Input('stats_admin', 'Stats Admin', 'text', $pconfig['stats_admin']
),"haproxy_stats_visible")->setHelp('Makes available the options disable/enable/softstop/softstart/killsessions from the stats page.<br/>
Note: This is not persisted when haproxy restarts. For publicly visible stats pages this should be disabled.');

$section->addInput(new Form_Input('stats_node', 'Stats Nodename', 'text', $pconfig['stats_node']
),"haproxy_stats_visible")->setHelp('The short name is displayed in the stats and helps to differentiate which server in a cluster is actually serving clients.');

$section->addInput(new Form_Input('stats_desc', 'Stats Description', 'text', $pconfig['stats_desc']
),"haproxy_stats_visible")->setHelp('The description is displayed behind the Nodename set above.');

$section->addInput(new Form_Input('stats_refresh', 'Stats Refresh', 'text', $pconfig['stats_refresh']
),"haproxy_stats_visible")->setHelp('Specify the refresh rate of the stats page in seconds, or specified time unit (us, ms, s, m, h, d).');


$form->add($section);
$section = new Form_Section('Error files');
$section->addInput(new Form_StaticText(
	'Error files',
	"Use these to replace the error pages that haproxy can generate by custom pages created on the files tab.
	For example haproxy will generate a 503 error page when no backend is available, you can replace that page here.<br/>".
	$errorfileslist->Draw($a_errorfiles)
));
$form->add($section);

$section = new Form_Section('Advanced');
$field = (new Form_Input(
	'strict_transport_security',
	'',
	'number',
	$pconfig['strict_transport_security'],
	['min' => 1, 'max' => 1000000000]
))->addClass("col-sm-3");
$section->addInput((new Form_StaticText(
	'HSTS Strict-Transport-Security',
	'When configured enables "HTTP Strict Transport Security" leave empty to disable. (only used on "http" frontends)<br/>
	<strong><div class="alert alert-warning" role="alert">'.gettext("WARNING! the domain will only work over https with a valid certificate!<br/>
	Clients will cache this header for the set duration which means removing this header will still require a valid certificate for the set time.").'</div></strong>' .
	"<div class='col-sm-12'>$field Seconds</div>"

))->setHelp(<<<EOT
				If configured clients that requested the page with this setting active will not be able to visit this domain over a unencrypted http connection.
				So make sure you understand the consequence of this setting or start with a really low value.<br/>
				EXAMPLE: 60 for testing if you are absolutely sure you want this 31536000 (12 months) would be good for production.
EOT
));

$section->addInput(new Form_Checkbox(
	'cookie_attribute_secure',
	'Cookie protection',
	'Set "secure" attribure on cookies (only used on "http" frontends)',
	$pconfig['cookie_attribute_secure']
))->setHelp("This configuration option sets up the Secure attribute on cookies if it has not been setup by the application server while the client was browsing the application over a ciphered connection.");

$form->add($section);

print $form;
?>	
				<?php if (isset($id) && $a_pools[$id]): ?>
				<input name="id" type="hidden" value="<?=$id;?>" />
				<?php endif; ?>
	
	</form>
<br/>
<script type="text/javascript">
<?php
	phparray_to_javascriptarray($fields_servers_details,"fields_details_servers",Array('/*','/*/name','/*/type'));
	phparray_to_javascriptarray($a_checktypes,"checktypes",Array('/*','/*/name','/*/descr'));
	phparray_to_javascriptarray($a_cookiemode,"cookiemode",Array('/*','/*/name','/*/descr'));
	phparray_to_javascriptarray($a_sticky_type,"sticky_type",Array('/*','/*/descr','/*/cookiedescr'));
	//phparray_to_javascriptarray($a_files,"a_files",Array('/*','/*/name','/*/descr'));

	phparray_to_javascriptarray($a_action, "showhide_actionfields",
		Array('/*', '/*/fields', '/*/fields/*', '/*/fields/*/name'));
	phparray_to_javascriptarray($a_acltypes, "showhide_aclfields",
		Array('/*', '/*/fields', '/*/fields/*', '/*/fields/*/name'));
		
	$serverslist->outputjavascript();
	$errorfileslist->outputjavascript();
	$htmllist_acls->outputjavascript();
	$htmllist_actions->outputjavascript();
?>
	browser_InnerText_support = (document.getElementsByTagName("body")[0].innerText !== undefined) ? true : false;
	
	totalrows =  <?php echo $counter; ?>;
	
	function table_acls_listitem_change(tableId, fieldId, rowNr, field) {
		if (fieldId === "toggle_details") {
			fieldId = "expression";
			field = d.getElementById(tableId+"expression"+rowNr);
		}
		if (fieldId === "expression") {
			var actiontype = field.value;
			
			var table = d.getElementById(tableId);
			
			for(var actionkey in showhide_aclfields) {
				var fields = showhide_aclfields[actionkey]['fields'];
				for(var fieldkey in fields){
					var fieldname = fields[fieldkey]['name'];
					var rowid = "tr_edititemdetails_"+rowNr+"_"+actionkey+fieldname;
					var element = d.getElementById(rowid);
					
					if (actionkey === actiontype)
						element.style.display = '';
					else
						element.style.display = 'none';
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
					var element = d.getElementById(rowid);
					
					if (actionkey === actiontype)
						element.style.display = '';
					else
						element.style.display = 'none';
				}
			}
		}
	}	
</script>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	$('#transparent_clientip').on('change', function() {
		updatevisibility();
	});
	$('#persist_cookie_enabled').on('change', function() {
		updatevisibility();
	});
	$('#persist_sticky_type').on('change', function() {
		updatevisibility();
	});
	$('#check_type').on('change', function() {
		updatevisibility();
	});
	$('#agent_check').click(function () {
		updatevisibility();
	});
	$('#stats_enabled').click(function () {
		updatevisibility();
	});
	
	updatevisibility();
});
//]]>
</script>

<?php
haproxy_htmllist_js();
include("foot.inc");
