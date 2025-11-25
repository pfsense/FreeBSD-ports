<?php
/*
 * acme_certificates_edit.php
 * 
 * part of pfSense (https://www.pfsense.org/)
 * Copyright (c) 2016 PiBa-NL
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

namespace pfsense_pkg\acme;

$shortcut_section = "acme";
require("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("acme/acme.inc");
require_once("acme/acme_utils.inc");
require_once("acme/acme_htmllist.inc");
require_once("acme/pkg_acme_tabs.inc");

if (isset($_POST['id'])) {
	$id = $_POST['id'];
} else {
	$id = $_GET['id'];
}

if (isset($_GET['dup'])) {
	$id = $_GET['dup'];
}

$id = get_certificate_id($id);
if (!is_numeric($id))
{
	//default value for new items.
	$isnewitem = true;
	$a_domains[] = array();
} else {
	$isnewitem = false;
}

global $simplefields;
$simplefields = array(
	"name","descr","status",
	"acmeaccount","keylength",
	"preferredchain", "dnssleep","renewafter"
);


// <editor-fold desc="domain edit HtmlList">
$fields_domains=array();
$fields_domains[0]['name']="status";
$fields_domains[0]['columnheader']="Mode";
$fields_domains[0]['colwidth']="5%";
$fields_domains[0]['type']="select";
$fields_domains[0]['size']="70px";
$fields_domains[0]['items']=&$a_enabledisable;
$fields_domains[1]['name']="name";
$fields_domains[1]['columnheader']="Domainname";
$fields_domains[1]['colwidth']="20%";
$fields_domains[1]['type']="textbox";
$fields_domains[1]['size']="30";
$fields_domains[2]['name']="method";
$fields_domains[2]['columnheader']="Method";
$fields_domains[2]['colwidth']="15%";
$fields_domains[2]['type']="select";
$fields_domains[2]['size']="100px";

$fields_domains_details = array();
$methods = array();
foreach($acme_domain_validation_method as $key => $action) {
	if (is_array($action['fields'])) {
		foreach($action['fields'] as $field) {
			$item = $field;
			$name = $key . $item['name'];
			$item['name'] = $name;
			//$item['customdrawcell'] = customdrawcell_actions;
			$fields_domains_details[$name] = $item;
		}
	}
	if ($action['name'] != 'notforuser') {
		$methods[$key] = array();
		$methods[$key]['name'] = $action['name'];
	}
}
$fields_domains[2]['items'] = $methods;

$domainslist = new HtmlList("table_domains", $fields_domains);
$domainslist->keyfield = "name";
$domainslist->fields_details = $fields_domains_details;
$domainslist->editmode = $isnewitem;

// </editor-fold>

// <editor-fold desc="action edit HtmlList">
$fields_actions=array();
$fields_actions[0]['name']="status";
$fields_actions[0]['columnheader']="Mode";
$fields_actions[0]['colwidth']="5%";
$fields_actions[0]['type']="select";
$fields_actions[0]['size']="70px";
$fields_actions[0]['items']=&$a_enabledisable;
$fields_actions[1]['name']="command";
$fields_actions[1]['columnheader']="Command";
$fields_actions[1]['colwidth']="20%";
$fields_actions[1]['type']="textbox";
$fields_actions[1]['size']="30";
$fields_actions[2]['name']="method";
$fields_actions[2]['columnheader']="Method";
$fields_actions[2]['colwidth']="15%";
$fields_actions[2]['type']="select";
$fields_actions[2]['size']="100px";
$fields_actions[2]['items']=&$acme_newcertificateactions;

$fields_actions_details=array();
foreach($acme_newcertificateactions as $key => $action) {
	if (is_array($action['fields'])) {
		foreach($action['fields'] as $field) {
			$item = $field;
			$name = $key . $item['name'];
			$item['name'] = $name;
			//$item['customdrawcell'] = customdrawcell_actions;
			$fields_actions_details[$name] = $item;
		}
	}
}
$actionslist = new HtmlList("table_actions", $fields_actions);
$actionslist->keyfield = "name";
//$actionslist->fields_details = $fields_actions_details;
$actionslist->editmode = $isnewitem;

// </editor-fold>

function customdrawcell_actions($object, $item, $itemvalue, $editable, $itemname, $counter) {
	if ($editable) {
		$object->acme_htmllist_drawcell($item, $itemvalue, $editable, $itemname, $counter);
	} else {
		echo $itemvalue;
	}
}

if (isset($id) && config_get_path("installedpackages/acme/certificates/item/{$id}")) {
	$a_domains = config_get_path("installedpackages/acme/certificates/item/{$id}/a_domainlist/item", []);
	$a_actions = config_get_path("installedpackages/acme/certificates/item/{$id}/a_actionlist/item", []);

	$pconfig["lastrenewal"] = config_get_path("installedpackages/acme/certificates/item/{$id}/lastrenewal");
	$pconfig['keypaste'] = base64_decode(config_get_path("installedpackages/acme/certificates/item/{$id}/keypaste"));
	foreach($simplefields as $stat) {
		$pconfig[$stat] = config_get_path("installedpackages/acme/certificates/item/{$id}/{$stat}");
	}
}

if (isset($_GET['dup'])) {
	unset($id);
	$pconfig['name'] .= "-copy";
}
$changedesc = "Services: Acme: Certificate options: ";
$changecount = 0;

if ($_POST) {
	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;
	
	$reqdfields = explode(" ", "name");
	$reqdfieldsn = explode(",", "Name");		

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['name'])) {
		$input_errors[] = "The field 'Name' contains invalid characters.";
	}
	
	// If the "Custom..." option was selected in the "Private Key" dropdown...
	if ($_POST['keylength'] == 'custom') {
		// ...then the "Custom Private Key" field is required.
		$reqdfields = explode(' ', 'keypaste');
		$reqdfieldsn = explode(',', 'Custom Private Key');
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		if (   isset($_POST['keypaste'])
			&& (   strpos($_POST['keypaste'], 'BEGIN PRIVATE KEY') === false
			    || strpos($_POST['keypaste'], 'END PRIVATE KEY') === false)) {
			$input_errors[] = "The Custom Private Key does not appear to be valid.";
		}
	} else {
		// ...otherwise, the "Custom Private Key" field will be ignored, so
		// clear its contents to avoid triggering update_if_changed() below.
		$_POST['keypaste'] = '';
	}

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
	
	/* Ensure that our certificate names are unique */
	for ($i=0; config_get_path("installedpackages/acme/certificates/item/{$i}") !== null; $i++) {
		if (($_POST['name'] == config_get_path("installedpackages/acme/certificates/item/{$i}/name")) && ($i != $id)) {
			$input_errors[] = "This name has already been used. Names must be unique.";
		}
	}
	$a_domains = $domainslist->acme_htmllist_get_values();
	foreach($a_domains as $server){
		$domain_name = $server['name'];
		if (!is_hostname($domain_name, true)) {
			$input_errors[] = "The field 'Domainname' does not contain a valid hostname.";
		} elseif (!is_hostname($domain_name)) {
			if (strtolower(substr($server['method'], 0, 3)) != "dns") {
				$input_errors[] = "Wildcard 'Domainname' validation requires a DNS-based method.";
			}
		}
	}
	$a_actions = $actionslist->acme_htmllist_get_values();

	$certificate = array();
	if(isset($id)) {
		$certificate = config_get_path("installedpackages/acme/certificates/item/{$id}", $certificate);
	}
		
//	echo "newname id:$id";
	if (!empty($certificate['name']) && ($certificate['name'] != $_POST['name'])) {
		//old $certificate['name'] can be empty if a new or cloned item is saved, nothing should be renamed then
		// name changed:
		$oldvalue = $certificate['name'];
		$newvalue = $_POST['name'];
	}

	if($certificate['name'] != "") {
		$changedesc .= " modified certificate: '{$certificate['name']}'";
	}
	getarraybyref($certificate, 'a_domainlist')['item'] = $a_domains;
	getarraybyref($certificate, 'a_actionlist')['item'] = $a_actions;

	$certificate['keypaste'] = base64_encode($_POST['keypaste']);
	global $simplefields;
	foreach($simplefields as $stat) {
		update_if_changed($stat, $certificate[$stat], $_POST[$stat]);
	}

	if (isset($id) && config_get_path("installedpackages/acme/certificates/item/{$id}")) {
		config_set_path("installedpackages/acme/certificates/item/{$id}", $certificate);
	} else {
		config_set_path('installedpackages/acme/certificates/item/', $certificate);
	}
	if (!isset($input_errors)) {
		if ($changecount > 0) {
			write_config($changedesc);
		}
		header("Location: acme_certificates.php");
		exit;
	}
}

$closehead = false;
$pgtitle = array("Services", "Acme", "Certificate options: Edit");
include("head.inc");
display_top_tabs_active($acme_tab_array['acme'], "certificates");

// 'processing' done, make all simple fields usable in html.
foreach($simplefields as $field){
	$pconfig[$field] = htmlspecialchars($pconfig[$field]);
}

?>
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
	}
</script>
</head>
<?php
if (isset($input_errors)) {
	print_input_errors($input_errors);
}

$counter=0;

$form = new \Form;

$section = new \Form_Section('Edit Certificate options');
$section->addInput(new \Form_Input('name', 'Name', 'text', $pconfig['name']
))->setHelp('The name set here will also be used to create or overwrite a certificate that might already exist with this name in the pfSense Certificate Manager.');
$section->addInput(new \Form_Input('descr', 'Description', 'text', $pconfig['descr']));
$activedisable = array();
$activedisable['active'] = "Active";
$activedisable['disabled'] = "Disabled";
$section->addInput(new \Form_Select(
	'status',
	'Status',
	$pconfig['status'],
	$activedisable
));
$section->addInput(new \Form_Select(
	'acmeaccount',
	'Acme Account',
	$pconfig['acmeaccount'],
	form_name_array(config_get_path('installedpackages/acme/accountkeys/item', []))
));

$section->addInput(new \Form_Select(
	'keylength',
	'Private Key',
	$pconfig['keylength'],
	form_keyvalue_array($a_keylength)
));

$section->addInput(new \Form_Textarea(
	'keypaste',
	'Custom Private Key',
	$pconfig['keypaste']
))->setNoWrap()
	->setAttribute('placeholder', "-----BEGIN PRIVATE KEY-----\nBASE64-ENCODED DATA\n-----END PRIVATE KEY-----")
	->setHelp('Paste a private key in X.509 PEM format here.');

$section->addInput(new \Form_Input(
	'preferredchain',
	'Preferred Chain',
	'text',
	$pconfig['preferredchain']
))->setHelp('If the ACME CA provides multiple trust chains, this field chooses an alternate %1$spreferred chain%2$s (uses a case-insensitive substring match).',
	'<a href="https://github.com/acmesh-official/acme.sh/wiki/Preferred-Chain">', '</a>');

$section->addInput(new \Form_StaticText(
	'Domain SAN list', 
	"List all domain names that should be included in the certificate here, and how to validate ownership by use of a webroot or dns challenge<br/>"
	. "Examples:<br/>"
	. "Domainname: www.example.com<br/>"
	. "Method: Webroot, Rootfolder: /usr/local/www/.well-known/acme-challenge/<br/>"
	. "Method: Webroot, Rootfolder: /tmp/haproxy_chroot/haproxywebroot/.well-known/acme-challenge/"
	. $domainslist->Draw($a_domains)
));

$section->addInput(new \Form_Input(
	'dnssleep',
	'DNS-Sleep',
	'number',
	$pconfig['dnssleep'],
	['min' => '1', 'max' => '3600']
))->setHelp('When using a DNS validation method this option disables automatic DNS polling and configures ' .
	'a specific amount of time, in seconds, to wait before attempting verification after adding TXT records. ' .
	'%1$sThe default behavior is to automatically poll public DNS servers for the records until ' .
	'they are found, rather than waiting a set amount of time.', '<br/><br/>');

$section->addInput(new \Form_StaticText(
	'Actions list', 
	"Used to restart webserver processes after this certificate has been renewed<br/>" .
	"Examples:<br/>" .
	"<br/>Restart the GUI on this firewall: Select \"Shell Command\" and enter <i>/etc/rc.restart_webgui</i>" .
	"<br/>Restart HAProxy on this firewall: Select \"Shell Command\" and enter <i>/usr/local/etc/rc.d/haproxy.sh restart</i>" .
	"<br/>Restart a local captive portal instance: Select \"Restart Local Service\" and enter <i>captiveportal zonename</i> replacing <i>zonename</i> with the zone to restart." .
	"<br/>Restart the GUI of an HA peer: Select \"Restart Remote Service\" and enter <i>webgui</i>. This utilizes the system default HA XMLRPC Sync Settings." .
	$actionslist->Draw($a_actions)
));

$section->addInput(new \Form_Input('', 'Last renewal', 'text', 
		date('d-m-Y H:i:s', $pconfig['lastrenewal'])
))->setReadonly()->setHelp('The last time this certificate was renewed');

$section->addInput(new \Form_Input('renewafter', 'Certificate renewal after', 'text', $pconfig['renewafter']
))->setHelp('After how many days the certificate should be renewed, defaults to 60');

$form->add($section);

if (!is_array(config_get_path('installedpackages/acme/accountkeys/item')) || count(config_get_path('installedpackages/acme/accountkeys/item')) == 0) {
	$form = new \Form;
	$section = new \Form_Section('Edit Certificate options');
	$section->addInput(new \Form_StaticText(
		'Accountkey required', 
		"An account key should be created and registered before configuring certificates."
	));
	$form->add($section);
}
print $form;
?>	
	<?php if (isset($id) && config_get_path("installedpackages/acme/certificates/item/{$id}")): ?>
	<input name="id" type="hidden" value="<?=$id;?>" />
	<?php endif; ?>
<br/>
<script type="text/javascript">
<?php
	phparray_to_javascriptarray($fields_domains_details,"fields_details_domains",Array('/*','/*/name','/*/type'));
	phparray_to_javascriptarray($acme_domain_validation_method, "showhide_domainfields",
		Array('/*', '/*/fields', '/*/fields/*', '/*/fields/*/name'));
	$domainslist->outputjavascript();
	phparray_to_javascriptarray($fields_actions_details,"fields_details_actions",Array('/*','/*/name','/*/type'));
	phparray_to_javascriptarray($acme_newcertificateactions, "showhide_actionfields",
		Array('/*', '/*/fields', '/*/fields/*', '/*/fields/*/name'));
	$actionslist->outputjavascript();
?>
	
	browser_InnerText_support = (document.getElementsByTagName("body")[0].innerText !== undefined) ? true : false;
	
	totalrows =  <?php echo $counter; ?>;
	
	function table_domains_listitem_change(tableId, fieldId, rowNr, field) {
		d = document;
		if (fieldId === "toggle_details") {
			fieldId = "method";
			field = d.getElementById(tableId+fieldId+rowNr);
		}
		if (fieldId === "method") {
			var actiontype = field.value;
			
			var table = d.getElementById(tableId);
			
			for(var actionkey in showhide_domainfields) {
				var showfield = actionkey === actiontype ? '' : 'none';
				if (actiontype.startsWith('dns_') && actionkey === 'anydns') {
					showfield = '';
				}
				var fields = showhide_domainfields[actionkey]['fields'];
				for(var fieldkey in fields){
					var fieldname = fields[fieldkey]['name'];
					var rowid = "tr_edititemdetails_"+rowNr+"_"+actionkey+fieldname;
					var element = d.getElementById(rowid);
					if (element) {
						element.style.display = showfield;
					}
				}
			}
		}
	}	
</script>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('form').submit(function(event){
		// disable all elements that dont have a value to avoid posting them as it could be sending 
		// more than 5000 variables which is the php default max for less than 100 san's which acme does support
		// p.s. the jquery .find(['value'='']) would not find newly added empty items) so we use .filter(...)
		$(this).find(':input').filter(function() { return !this.value }).attr("disabled", "disabled")
		return true;
	});
	
	/*
	$('#stats_enabled').click(function () {
		updatevisibility();
	});
	*/
	$('[id^=table_domainsmethod]').change();
	updatevisibility();

	// Update visibility of Custom Private Key field,
	// based upon selection in Private Key drop-down
	function keylength_change() {
		hideInput('keypaste', $('#keylength').val() != "custom");
	}

	// Update page display state on keylength selection change
	$('#keylength').change(function () {
		keylength_change();
	});

	// Set initial page display state
	keylength_change();
});
//]]>
</script>

<?php
acme_htmllist_js("table_domains");
include("foot.inc");
