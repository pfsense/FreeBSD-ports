<?php
/*
 * acme_accountkeys_edit.php
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

if ($_POST['action'] == "createkey") {
	$caname = $_POST['caname'];
	$ca = $a_acmeserver[$caname]['url'];
	echo generateAccountKey("_createkey", $ca);
	exit;
}
if ($_POST['action'] == "registerkey") {
	$caname = $_POST['caname'];
	$key = $_POST['key'];
	$ca = $a_acmeserver[$caname]['url'];
	echo "Register key at ca: {$ca}\n";
	echo registerAcmeAccountKey("_registerkey", $ca, $key);
	exit;
}

if (!is_array($config['installedpackages']['acme']['accountkeys']['item'])) {
	$config['installedpackages']['acme']['accountkeys']['item'] = array();
}
$a_accountkeys = &$config['installedpackages']['acme']['accountkeys']['item'];

if (isset($_POST['id'])) {
	$id = $_POST['id'];
} else {
	$id = $_GET['id'];
}

if (isset($_GET['dup'])) {
	$id = $_GET['dup'];
}

$id = get_accountkey_id($id);
if (!is_numeric($id))
{
	//default value for new items.
	$isnewitem = true;
} else {
	$isnewitem = false;
}

global $simplefields;
$simplefields = array(
	"name","desc",
	"acmeserver","renewafter"
);

function customdrawcell_actions($object, $item, $itemvalue, $editable, $itemname, $counter) {
	if ($editable) {
		$object->acme_htmllist_drawcell($item, $itemvalue, $editable, $itemname, $counter);
	} else {
		echo $itemvalue;
	}
}

if (isset($id) && $a_accountkeys[$id]) {
	$pconfig['accountkey'] = base64_decode($a_accountkeys[$id]['accountkey']);
	foreach($simplefields as $stat) {
		$pconfig[$stat] = $a_accountkeys[$id][$stat];
	}
	
	$a_errorfiles = &$a_accountkeys[$id]['errorfiles']['item'];
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
	
	/* Ensure that our account key names are unique */
	for ($i=0; isset($config['installedpackages']['acme']['accountkeys']['item'][$i]); $i++) {
		if (($_POST['name'] == $config['installedpackages']['acme']['accountkeys']['item'][$i]['name']) && ($i != $id)) {
			$input_errors[] = "This name has already been used. Names must be unique.";
		}
	}

	$accountkey = array();
	if(isset($id) && $a_accountkeys[$id]) {
		$accountkey = $a_accountkeys[$id];
	}

	if (!empty($accountkey['name']) && ($accountkey['name'] != $_POST['name'])) {
		//old $accountkey['name'] can be empty if a new or cloned item is saved, nothing should be renamed then
		// name changed:
		$oldvalue = $accountkey['name'];
		$newvalue = $_POST['name'];
		
		$a_accountkeys = &$config['installedpackages']['acme']['accountkeys']['item'];
		if (!is_array($a_accountkeys)) {
			$a_accountkeys = array();
		}
	}

	if($accountkey['name'] != "") {
		$changedesc .= " modified account key: '{$accountkey['name']}'";
	}

	$accountkey['accountkey'] = base64_encode($_POST['accountkey']);
	global $simplefields;
	foreach($simplefields as $stat) {
		update_if_changed($stat, $accountkey[$stat], $_POST[$stat]);
	}
	
	if (isset($id) && $a_accountkeys[$id]) {
		$a_accountkeys[$id] = $accountkey;
	} else {
		$a_accountkeys[] = $accountkey;
	}
	if (!isset($input_errors)) {
		if ($changecount > 0) {
			touch($d_acmeconfdirty_path);
			write_config($changedesc);
		}
		echo "<pre/>";
		//print_r($config['installedpackages']['acme']);
		header("Location: acme_accountkeys.php");
		exit;
	}
}

//$closehead = false;
$pgtitle = array("Services", "Acme", "Certificate options: Edit");
include("head.inc");
display_top_tabs_active($acme_tab_array['acme'], "accountkeys");

// 'processing' done, make all simple fields usable in html.
foreach($simplefields as $field){
	$pconfig[$field] = htmlspecialchars($pconfig[$field]);
}

?>
<!--/head-->
<?php
if (isset($input_errors)) {
	print_input_errors($input_errors);
}

$counter=0;

$form = new \Form;

$section = new \Form_Section('Edit Certificate options');
$section->addInput(new \Form_Input('name', 'Name', 'text', $pconfig['name']
))->setHelp('');
$section->addInput(new \Form_Input('desc', 'Description', 'text', $pconfig['desc']));

$section->addInput(new \Form_Select(
	'acmeserver',
	'Acme Server',
	$pconfig['acmeserver'],
	form_keyvalue_array($a_acmeserver)
));

$section->addInput(new \Form_Textarea(
	'accountkey',
	'Account key',
	$pconfig['accountkey']
))->setNoWrap();

$section->addInput(new \Form_StaticText(
	'', 
	"<a id='btncreatekey' class='btn btn-sm btn-primary'>"
		. "<i id='btncreatekeyicon' class='fa fa-plus'></i> Create new account key</a>"
));

$section->addInput(new \Form_StaticText(
	'Acme account registration',
	"<a id='btnregisterkey' class='btn btn-sm btn-primary'>"
		. "<i id='btnregisterkeyicon' class='fa fa-key'></i> Register acme account key</a>"
))->setHelp("Before using a accountkey it must first be registered at the chosen CA.");

$form->add($section);

print $form;
?>	
	<?php if (isset($id) && $a_certificates[$id]): ?>
	<input name="id" type="hidden" value="<?=$id;?>" />
	<?php endif; ?>
<br/>
<script type="text/javascript">
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

	function setTest(data){
		$("#accountkey").val(data);
	}
	function createkey() {
		$("#btncreatekeyicon").removeClass("fa-check").addClass("fa-cog fa-spin");
		ajaxRequest = $.ajax({
			type: "post",
			data: { action: "createkey" },
			success: function(data) {
				setTest(data);
				$("#btncreatekeyicon").removeClass("fa-cog fa-spin").addClass("fa-check");
			}
		});
	}
events.push(function() {
	$('#btnregisterkey').click(function() {
		$("#btnregisterkeyicon").removeClass("fa-key").addClass("fa-cog fa-spin");
		var key = $("#accountkey").val();
		var caname = $("#acmeserver").val();
		ajaxRequest = $.ajax({
			type: "post",
			data: { action: "registerkey", caname: caname, key: key },
			success: function(data) {
				$("#btnregisterkeyicon").removeClass("fa-cog fa-spin").addClass("fa-check");
			}
		});
	});
	
	$('#btncreatekey').click(function() {
		$("#btncreatekeyicon").removeClass("fa-plus").addClass("fa-cog fa-spin");
		var caname = $("#acmeserver").val();
		ajaxRequest = $.ajax({
			type: "post",
			data: { action: "createkey", caname: caname },
			success: function(data) {
				setTest(data);
				$("#btncreatekeyicon").removeClass("fa-cog fa-spin").addClass("fa-check");
			}
		});
		
	});
	
	/*
	$('#stats_enabled').click(function () {
		updatevisibility();
	});
	*/
	updatevisibility();
});
//]]>
</script>

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
<?php
acme_htmllist_js();
include("foot.inc");