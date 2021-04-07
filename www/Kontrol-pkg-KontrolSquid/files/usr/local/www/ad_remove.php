<?php
/*
 * ad_remove.php
 *
 * KONNTROL TECNOLOGIA EPP - All rights reserved - 2016-2021
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

##|+PRIV
##|*IDENT=ad_remove
##|*NAME=Services: AD_DOMAIN
##|*DESCR=Configure AD/Kontrol-ID features.
##|*MATCH=ad_remove*
##|-PRIV

require_once("guiconfig.inc");
require_once("notices.inc");


$pgtitle = array(gettext("Services"), gettext("KONTROL-ID"));
$shortcut_section = "Remove from a Domain";

include("head.inc");

if ($_POST)
	{
		$pconfig = $_POST;
		if (!empty ($_POST['rad_user']) && ($_POST['rad_pass']))
		{
			$rad_user = $_POST["rad_user"];
			$rad_pass = $_POST["rad_pass"];
			unset ($remove);
			$remove = exec ("net ads leave -U $rad_user%$rad_pass");
			exec ('rm /etc/krb5.keytab 2>/dev/null');
			exec ('killall winbindd 2>&1');
			write_config("KONTROL-ID settings saved");
			$changes_applied = true;
			$retval = 0;
			file_notice("Kontrol-ID",$error,"KontrolSquid - " . gettext($remove), "");
		}
		else
		{
			$input_errors[] = gettext("Please fulfill all empty fields!");
			$changes_applied = false;
		}



	}


if ($input_errors)
	{
	print_input_errors($input_errors);
	unset ($input_errors);
	}

if ($changes_applied)
	{
	print_apply_result_box($retval);
	}



$tab_array = array();
$tab_array[] = array(gettext("Remove KONTROL-UTM from a DOMAIN"), true, "ad_remove.php");
$tab_array[] = array(gettext("Join KONTROL-UTM to a DOMAIN"), false, "ad_join.php");
$tab_array[] = array(gettext("Transparent Proxy Configuration"), false, "ad_transparent.php");
display_top_tabs($tab_array);

$testdomain = exec("net ads testjoin");


# FORM BEGIN ---------------------------------------------------------------------

$form = new Form(false);

$section = new Form_Section('Remove KONTROL-UTM from DOMAIN');

$section->addInput(new Form_Input(
	'rad_user',
	'Username',
	'text',
	$pconfig['rad_user']
))->setHelp('Enter Username/Account with Domain Administrator permissions.');

$section->addInput(new Form_Input(
	'rad_pass',
	'Password',
	'password',
	$pconfig['rad_pass']
));

$form->add($section);

if ($testdomain == "Join is OK")
	{
		echo "<tr><span style='color:#F00;text-align:center;'>This KONTROL-UTM box IS ALREADY part of a DOMAIN</span></tr>";
		$form->addGlobal(new Form_Button(
		'Submit',
		'Submit',
		null,
		'fa-power-off'
		))->addClass('btn-primary');
	}
	else
	{
		echo "<span style='color:#F00;text-align:center;'>This KONTROL-UTM box is NOT yet part of a DOMAIN</span>";
	}

print($form);

include("foot.inc");

?>
