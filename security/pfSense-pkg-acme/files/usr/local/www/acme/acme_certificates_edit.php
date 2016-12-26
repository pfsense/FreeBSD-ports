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
require_once("acme/acme.inc");
require_once("acme/acme_utils.inc");
require_once("acme/acme_htmllist.inc");
require_once("acme/pkg_acme_tabs.inc");

if (!is_array($config['installedpackages']['acme']['certificates']['item'])) {
	$config['installedpackages']['acme']['certificates']['item'] = array();
}
$a_certificates = &$config['installedpackages']['acme']['certificates']['item'];

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
	"name","desc","status",
	"acmeaccount","keylength","renewafter"
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
$fields_domains[2]['items']=&$acme_domain_validation_method;

$fields_domains_details=array();
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
}
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
if (isset($id) && $a_certificates[$id]) {
	$a_domains = &$a_certificates[$id]['a_domainlist']['item'];
	$a_actions = &$a_certificates[$id]['a_actions']['item'];

	$pconfig["lastrenewal"] = $a_certificates[$id]["lastrenewal"];
	foreach($simplefields as $stat) {
		$pconfig[$stat] = $a_certificates[$id][$stat];
	}
	
	$a_errorfiles = &$a_certificates[$id]['errorfiles']['item'];
	if (!is_array($a_errorfiles)) {
		$a_errorfiles = array();
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
	
	/* Ensure that our pool names are unique */
	for ($i=0; isset($config['installedpackages']['acme']['certificates']['item'][$i]); $i++) {
		if (($_POST['name'] == $config['installedpackages']['acme']['certificates']['item'][$i]['name']) && ($i != $id)) {
			$input_errors[] = "This pool name has already been used.  Pool names must be unique.";
		}
	}
	$a_domains = $domainslist->acme_htmllist_get_values();
	foreach($a_domains as $server){
		$domain_name    = $server['name'];
		if (!is_hostname($domain_name)) {
			$input_errors[] = "The field 'Domainname' does not contain a valid hostname.";
		}
	}
	$a_actions = $actionslist->acme_htmllist_get_values();

	$certificate = array();
	if(isset($id) && $a_certificates[$id]) {
		$certificate = $a_certificates[$id];
	}
		
//	echo "newname id:$id";
	if (!empty($certificate['name']) && ($certificate['name'] != $_POST['name'])) {
		//old $certificate['name'] can be empty if a new or cloned item is saved, nothing should be renamed then
		// name changed:
		$oldvalue = $certificate['name'];
		$newvalue = $_POST['name'];
		
		$a_certificates = &$config['installedpackages']['acme']['certificates']['item'];
		if (!is_array($a_certificates)) {
			$a_certificates = array();
		}
	}

	if($certificate['name'] != "") {
		$changedesc .= " modified pool: '{$certificate['name']}'";
	}
	$certificate['a_domainlist']['item'] = $a_domains;
	$certificate['a_actionlist']['item'] = $a_actions;

	global $simplefields;
	foreach($simplefields as $stat) {
		update_if_changed($stat, $certificate[$stat], $_POST[$stat]);
	}
	if (isset($id) && $a_certificates[$id]) {
		$a_certificates[$id] = $certificate;
	} else {
		$a_certificates[] = $certificate;
	}
	if (!isset($input_errors)) {
		if ($changecount > 0) {
			touch($d_acmeconfdirty_path);
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
))->setHelp('The name set here will also be used to create or overwrite a certificate that might already exist with this name in the pfSense certificate m.');
$section->addInput(new \Form_Input('desc', 'Description', 'text', $pconfig['desc']));
$activedisable = array();
$activedisable['active'] = "Active";
$activedisable['disabled'] = "Disabled";
$section->addInput(new \Form_Select(
	'status',
	'Status',
	$pconfig['status'],
	$activedisable
));
$a_accountkeys = &$config['installedpackages']['acme']['accountkeys']['item'];
$section->addInput(new \Form_Select(
	'acmeaccount',
	'Acme Account',
	$pconfig['acmeaccount'],
	form_name_array($a_accountkeys)
));

$a_accountkeys = &$config['installedpackages']['acme']['accountkeys']['item'];
$section->addInput(new \Form_Select(
	'keylength',
	'Key Size',
	$pconfig['keylength'],
	form_name_array($a_keylength)
));

$section->addInput(new \Form_StaticText(
	'Domain SAN list', 
	"List all domain names that should be included in the certificate here, and how to validate ownership by use of a webroot or dns challenge<br/>"
	. "Examples:<br/>"
	. "/usr/local/www/.well-known/acme-challenge/<br/>"
	. "/tmp/haproxy_chroot/.well-known/acme-challenge/"
	. $domainslist->Draw($a_domains)
));

$section->addInput(new \Form_StaticText(
	'Actions list', 
	"Used to restart webserver processes this certificate has been renewed<br/>Examples:<br/>/etc/rc.restart_webgui<br/>/usr/local/etc/rc.d/haproxy.sh restart".
	$actionslist->Draw($a_actions)
));

$section->addInput(new \Form_Input('', 'Last renewal', 'text', 
		date('d-m-Y H:i:s', $pconfig['lastrenewal'])
))->setReadonly()->setHelp('The last time this certificate was renewed');

$section->addInput(new \Form_Input('renewafter', 'Certificate renewal after', 'text', $pconfig['renewafter']
))->setHelp('After how many days the certicicate should be renewed, defaults to 60');

$form->add($section);

print $form;
?>	
	<?php if (isset($id) && $a_certificates[$id]): ?>
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
		if (fieldId === "toggle_details") {
			fieldId = "method";
			field = d.getElementById(tableId+fieldId+rowNr);
		}
		if (fieldId === "method") {
			var actiontype = field.value;
			
			var table = d.getElementById(tableId);
			
			for(var actionkey in showhide_domainfields) {
				var fields = showhide_domainfields[actionkey]['fields'];
				for(var fieldkey in fields){
					var fieldname = fields[fieldkey]['name'];
					var rowid = "tr_edititemdetails_"+rowNr+"_"+actionkey+fieldname;
					var element = d.getElementById(rowid);
					if (element) {
						if (actionkey === actiontype) {
							element.style.display = '';
						} else {
							element.style.display = 'none';
						}
					}
				}
			}
		}
	}	
</script>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	/*
	$('#stats_enabled').click(function () {
		updatevisibility();
	});
	*/
	updatevisibility();
});
//]]>
</script>

<?php
acme_htmllist_js();
include("foot.inc");