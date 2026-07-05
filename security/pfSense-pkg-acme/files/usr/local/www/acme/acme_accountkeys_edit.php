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
	$email = $_POST['email'];
	$ca = $a_acmeserver[$caname]['url'];
	$eabkid = (!empty($_POST['eabkid'])) ? $_POST['eabkid'] : "";
	$eabhmac = (!empty($_POST['eabhmac'])) ? $_POST['eabhmac'] : "";
	echo "Register key at CA: {$ca}\n";
	echo (registerAcmeAccountKey("_registerkey", $ca, $key, $email, $eabkid, $eabhmac)) ? "reg-ok" : "reg-fail" ;
	exit;
}

$id = $_REQUEST['id'];

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
	"name", "descr", "email", "eabkid", "eabhmac", "acmeserver"
);

function customdrawcell_actions($object, $item, $itemvalue, $editable, $itemname, $counter) {
	if ($editable) {
		$object->acme_htmllist_drawcell($item, $itemvalue, $editable, $itemname, $counter);
	} else {
		echo $itemvalue;
	}
}

if (isset($id) && config_get_path("installedpackages/acme/accountkeys/item/{$id}")) {
	$pconfig['accountkey'] = base64_decode(config_get_path("installedpackages/acme/accountkeys/item/{$id}/accountkey"));
	foreach($simplefields as $stat) {
		$pconfig[$stat] = config_get_path("installedpackages/acme/accountkeys/item/{$id}/{$stat}");
	}
}

if (isset($_GET['dup'])) {
	unset($id);
	$pconfig['name'] .= "-copy";
}
$changedesc = "Services: ACME: Account Keys: Edit: ";
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

	if (!empty($_POST['email'])) {
		/* Validate e-mail address */
		if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $_POST['email'])) {
			$input_errors[] = gettext("The supplied e-mail address contains invalid characters.");
		}
	}

	if (!array_key_exists($_POST['acmeserver'], $a_acmeserver)) {
		$input_errors[] = gettext("The supplied ACME Server does not exist.");
	}

	/* Ensure that our account key names are unique */
	foreach (config_get_path("installedpackages/acme/accountkeys/item", []) as $i => $item) {
		if (($i != $id) && ($_POST['name'] == $item['name'])) {
			$input_errors[] = "This name has already been used. Names must be unique.";
		}
	}

	$accountkey = array();
	if(isset($id)) {
		$accountkey = config_get_path("installedpackages/acme/accountkeys/item/{$id}", $accountkey);
	}

	if (!empty($accountkey['name']) && ($accountkey['name'] != $_POST['name'])) {
		//old $accountkey['name'] can be empty if a new or cloned item is saved, nothing should be renamed then
		// name changed:
		$oldvalue = $accountkey['name'];
		$newvalue = $_POST['name'];
		$configured_certificates = config_get_path('installedpackages/acme/certificates/item', []);
		$certificates_changed = false;
		foreach ($configured_certificates as &$configured_certificate) {
			if ($configured_certificate['acmeaccount'] == $oldvalue) {
				$configured_certificate['acmeaccount'] = $newvalue;
				$certificates_changed = true;
			}
		}
		if ($certificates_changed) {
			config_set_path('installedpackages/acme/certificates/item', $configured_certificates);
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

	if (isset($id) && config_get_path("installedpackages/acme/accountkeys/item/{$id}")) {
		config_set_path("installedpackages/acme/accountkeys/item/{$id}", $accountkey);
	} else {
		config_set_path('installedpackages/acme/accountkeys/item/', $accountkey);
	}
	if (!isset($input_errors)) {
		if ($changecount > 0) {
			write_config($changedesc);
		}
		echo "<pre/>";
		//print_r($config['installedpackages']['acme']);
		header("Location: acme_accountkeys.php");
		exit;
	}
}

//$closehead = false;
$pgtitle = array("Services", "ACME", "Account Keys", "Edit");
include("head.inc");
display_top_tabs_active($acme_tab_array['acme'], "accountkeys");

?>
<!--/head-->
<?php
if (!empty($pconfig['acmeserver']) &&
    !array_key_exists($pconfig['acmeserver'], $a_acmeserver)) {
	$input_errors[] = gettext("The ACME Server stored on this key no longer exists and the " .
				"field has been reset to the default value. This key, and any " .
				"certificates using this key, will not function until this key " .
				"is configured with a functional ACME Server value.");
}

if (isset($input_errors)) {
	print_input_errors($input_errors);
}

$counter=0;

$form = new \Form;

$section = new \Form_Section('Identification');
$section->addInput(new \Form_Input(
	'name',
	'Name',
	'text',
	$pconfig['name']
))->setHelp('Short name for this Account Key.');

$section->addInput(new \Form_Input(
	'descr',
	'Description',
	'text', $pconfig['descr']
))->setHelp('Longer text description of this Account Key and its purpose.');

$section->addInput(new \Form_Input(
	'email',
	'E-Mail Address',
	'text',
	$pconfig['email']
))->setHelp('The e-mail address to associate with this key. ' .
	'The CA may use this address to send important notices.');

$form->add($section);
$section = new \Form_Section('ACME Server');

$section->addInput(new \Form_Select(
	'acmeserver',
	'ACME Server',
	$pconfig['acmeserver'],
	form_keyvalue_array($a_acmeserver)
))->setHelp('The Certificate Authority/ACME server which will issue certificates for this key.%1$s' .
	'Use a staging or testing server, if available, until certificate validation works, ' .
	'then switch to a production server.%1$s%1$s', '<br/>');

$section->addInput(new \Form_Input(
	'eabkid',
	'EAB Key ID',
	'text',
	$pconfig['eabkid']
))->setHelp('External Account Binding Key ID. Optional. Leave blank unless required by the CA.%1$s' .
	'Registers this Account Key with a specific account at the CA.%1$s%1$s' .
	'Check with the CA to determine if this is required and for information on how to generate the value.', '<br/>');

$section->addInput(new \Form_Textarea(
	'eabhmac',
	'EAB HMAC Key',
	$pconfig['eabhmac']
))->setHelp('External Account Binding HMAC Key. Optional. Leave blank unless required by the CA.%1$s' .
	'Registers this Account Key with a specific account at the CA.%1$s%1$s' .
	'Check with the CA to determine if this is required and for information on how to generate the value.', '<br/>');

$form->add($section);
$section = new \Form_Section('Account Key');

$section->addInput(new \Form_Textarea(
	'accountkey',
	'Account Key',
	$pconfig['accountkey']
))->setNoWrap()->setHelp('Key that uniquely identifies and authorizes the account.%1$s' .
	'If empty, click %2$sGenerate New Account Key%3$s to create a new key.',
	'<br/>', '<b>', '</b>');

$section->addInput(new \Form_StaticText(
	'',
	"<a id='btncreatekey' class='btn btn-sm btn-primary'>"
		. "<i id='btncreatekeyicon' class='fa-solid fa-plus'></i> Generate New Account Key</a>"
));

$form->add($section);
$section = new \Form_Section('Registration');

$section->addInput(new \Form_StaticText(
	'Account Key Registration',
	"<a id='btnregisterkey' class='btn btn-sm btn-primary'>"
		. "<i id='btnregisterkeyicon' class='fa-solid fa-key'></i> Register ACME Account Key</a>"
))->setHelp('Before using an Account Key, it must first be registered with the chosen ACME Server.%1$s' .
	'Click %5$sRegister ACME Account Key%6$s to register this Account Key%1$s%1$s' .
	'%2$s indicates a successful registration, %3$s indicates a failure. ' .
	'%1$s In the case of a failure, check %4$s for more information.',
	'<br/>',
	'<i class="fa-solid fa-check"></i>',
	'<i class="fa-solid fa-times"></i>',
	'<tt>/tmp/acme/_registerkey/acme_issuecert.log</tt>',
	'<b>', '</b>');

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
		$("#btncreatekeyicon").removeClass("fa-check").addClass("fa-cog fa-solid fa-spin");
		ajaxRequest = $.ajax({
			type: "post",
			data: { action: "createkey" },
			success: function(data) {
				setTest(data);
				$("#btncreatekeyicon").removeClass("fa-cog fa-spin").addClass("fa-solid fa-check");
			}
		});
	}
events.push(function() {
	$('#btnregisterkey').click(function() {
		$("#btnregisterkeyicon").removeClass("fa-key").addClass("fa-cog fa-solid fa-spin");
		var key = $("#accountkey").val();
		var caname = $("#acmeserver").val();
		var email = $("#email").val();
		var eabkid = $("#eabkid").val();
		var eabhmac = $("#eabhmac").val();
		ajaxRequest = $.ajax({
			type: "post",
			data: { action: "registerkey", caname: caname, key: key, email: email, eabkid: eabkid, eabhmac: eabhmac },
			success: function(data) {
				if (data.toLowerCase().indexOf("reg-ok") > -1 ) {
					$("#btnregisterkeyicon").removeClass("fa-cog fa-spin").addClass("fa-solid fa-check");
				} else {
					$("#btnregisterkeyicon").removeClass("fa-cog fa-spin").addClass("fa-solid fa-times");
				}
			}
		});
	});

	$('#btncreatekey').click(function() {
		$("#btncreatekeyicon").removeClass("fa-plus").addClass("fa-cog fa-solid fa-spin");
		var caname = $("#acmeserver").val();
		ajaxRequest = $.ajax({
			type: "post",
			data: { action: "createkey", caname: caname },
			success: function(data) {
				setTest(data);
				$("#btncreatekeyicon").removeClass("fa-cog fa-spin").addClass("fa-solid fa-check");
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
acme_htmllist_js("account_keys");
include("foot.inc");
