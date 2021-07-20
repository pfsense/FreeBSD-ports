<?php
/*
 * ad_transparent.php
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
##|*IDENT=ad_transparent
##|*NAME=Services: AD_DOMAIN
##|*DESCR=Configure AD/Kontrol-ID features.
##|*MATCH=ad_transparent*
##|-PRIV

require_once("guiconfig.inc");
#require_once("functions.inc");

$pgtitle = array(gettext("Services"), gettext("KONTROL-ID"));
$shortcut_section = "Transparent Proxy Config";

include("head.inc");


$tab_array = array();
$tab_array[] = array(gettext("Remove KONTROL-UTM from a DOMAIN"), false, "ad_remove.php");
$tab_array[] = array(gettext("Join KONTROL-UTM to a DOMAIN"), false, "ad_join.php");
$tab_array[] = array(gettext("Transparent Proxy Configuration"), true, "ad_transparent.php");
display_top_tabs($tab_array);


#Setting Variables
$domain = (exec ('hostname -d'));
$host_var = exec ("hostname");
$smb_workgroup = exec('hostname -d | cut -d. -f1');
$smb_workgroup_upper = strtoupper($smb_workgroup);

$file = "/usr/local/www/kontrolhelper.config";

if ($_POST)
	{
		$pconfig = $_POST;
		if (!empty($_POST['tad_server']))
		{
			$tad_server = $_POST['tad_server'];
			exec ("sed -i \"\" \"9s/.*/define('DOMAIN_FQDN', '$domain');/\" /usr/local/www/login2.php");
			exec ("sed -i \"\" \"10s/.*/define('LDAP_SERVER', '$tad_server');/\" /usr/local/www/login2.php");
			if (!file_exists($file))
			{
			touch($file);
			}
			file_put_contents($file, $tad_server);     // Save our content to the file.
			write_config("KONTROL-ID settings saved");
			$changes_applied = true;
			$retval = 0;
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


# FORM BEGIN ---------------------------------------------------------------------

$form = new Form(false);

$section = new Form_Section('Transparent Proxy Configuration');

$section->addInput(new Form_Input(
	'tad_server',
	'Server IP Address',
	'text',
	($pconfig['tad_server'] ? $pconfig['tad_server']:'')
))->setHelp('Enter AD-Server or Kontrol-Master IP Address - eg: 192.168.0.10');

$form->add($section);

if (!file_exists($file))
	{
	echo "<tr><span style='color:#F00;text-align:center;'>The configuration file does not exist. Create one below: </span></tr>";
	}
	else
	{
		$f = fopen($file, 'r');
		$line = fgets($f);
		fclose($f);
		echo "<span style='color:#F00;text-align:center;'>The configuration file exists and it points to: -  $line </span>";
	}


$form->addGlobal(new Form_Button(
	'Submit',
	'Submit',
	null,
	'fa-power-off'
))->addClass('btn-primary');


print($form);

include("foot.inc");

?>
