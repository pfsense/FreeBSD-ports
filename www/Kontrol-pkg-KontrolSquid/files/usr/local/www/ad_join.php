<?php
/*
 * ad_join.php
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
##|*IDENT=ad_join
##|*NAME=Services: AD_DOMAIN
##|*DESCR=Configure AD/Kontrol-ID features.
##|*MATCH=ad_join*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("interfaces.inc");
require_once("notices.inc");


$pgtitle = array(gettext("Services"), gettext("KONTROL-ID"));
$shortcut_section = "Join to a Domain";

include("head.inc");

#Setting Variables
$domain = (exec ('hostname -d'));
$ad_domain = strtoupper(exec ('hostname -d'));
$host_var = exec ("hostname");
$smb_workgroup = exec('hostname -d | cut -d. -f1');
$smb_workgroup_upper = strtoupper($smb_workgroup);



if ($_POST)
	{
		$pconfig = $_POST;
		if (!empty ($_POST['jad_user']) && ($_POST['jad_pass']) && ($_POST['interface']) && ($_POST['jad_server']))
		{
			$jad_user = $_POST["jad_user"];
			$jad_pass = $_POST["jad_pass"];
			$jad_server = $_POST["jad_server"];
			$rface = array();
			if (is_array($pconfig['interface']))
			{
				foreach ($pconfig['interface'] as $iface_alias)
				{
					$rface_name = get_real_interface($iface_alias);
					$rface[] = $rface_name;
				}

				$interface = implode(" ", $rface);
				unset($rface);
			}
			else
			{
				$interface = $pconfig["interface"];
			}
			exec ("sed -i \"\" \"9s/.*/define('DOMAIN_FQDN', '$domain');/\" /usr/local/www/login2.php");
			exec ("sed -i \"\" \"10s/.*/define('LDAP_SERVER', '$jad_server');/\" /usr/local/www/login2.php");
			exec ("sed -i \"\" \"s/^interfaces.*/interfaces = lo0 $interface /\" /usr/local/etc/smb4.conf");
			exec ("sed -i \"\" \"s/^workgroup.*/workgroup = $smb_workgroup_upper /\" /usr/local/etc/smb4.conf");
			exec ("sed -i \"\" \"s/^realm.*/realm  = $ad_domain /\" /usr/local/etc/smb4.conf");
			exec ("sed -i \"\" \"7s/.*/idmap config $smb_workgroup_upper : backend = rid /\" /usr/local/etc/smb4.conf");
			exec ("sed -i \"\" \"8s/.*/idmap config $smb_workgroup_upper : range = 10000-20000 /\" /usr/local/etc/smb4.conf");
			exec ("sed -i \"\" \"2s/.*/default_realm = $ad_domain /\" /usr/local/etc/krb5.conf");
			exec ("sed -i \"\" \"14s/.*/   $ad_domain = { /\" /usr/local/etc/krb5.conf");
			exec ("sed -i \"\" \"15s/.*/   kdc = $jad_server /\" /usr/local/etc/krb5.conf");
			exec ("sed -i \"\" \"16s/.*/   admin_server = $jad_server /\" /usr/local/etc/krb5.conf");
			exec ("sed -i \"\" \"17s/.*/   default_domain = $domain /\" /usr/local/etc/krb5.conf");
			exec ("sed -i \"\" \"21s/.*/  .$domain = $ad_domain /\" /usr/local/etc/krb5.conf");
			exec ("sed -i \"\" \"22s/.*/   $domain = $ad_domain /\" /usr/local/etc/krb5.conf");
			exec ('kdestroy 2>&1');
			exec ("echo $jad_pass | kinit $jad_user 2>&1");
				if (file_exists("/etc/krb5.keytab"))
				{
					exec ('rm /etc/krb5.keytab 2>&1');
				}
			unset ($join );
			unset ($msg );
			$join = exec ('net ads join createupn=HTTP/$host_var@$ad_domain -k');
			exec ('net ads keytab add HTTP');
			exec ('net ads keytab create -k');
			exec ('chown root:proxy /var/db/samba4/winbindd_privileged');
			exec ('chmod -R 0750 /var/db/samba4/winbindd_privileged');
			exec ('killall winbindd');
			exec ('/usr/local/sbin/winbindd --daemon --configfile=/usr/local/etc/smb4.conf 2>&1');
			exec ('chown root:proxy /etc/krb5.keytab');
			exec ('chmod 0440 /etc/krb5.keytab 2>&1');
			exec ('ktutil -k /etc/krb5.keytab list 2>&1');
			exec ('/usr/local/sbin/pfSsh.php playback svc restart squid');
			exec ('chown root:proxy /var/db/samba4/winbindd_privileged');
			$msg = exec ('wbinfo -t');
			write_config("KONTROL-ID settings saved");
			$changes_applied = true;
			$retval = 0;
			file_notice("Kontrol-ID",$error,"KontrolSquid - " . gettext($join), "");
			file_notice("Kontrol-ID",$error,"KontrolSquid - " . gettext($msg), "");
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
$tab_array[] = array(gettext("Remove KONTROL-UTM from a DOMAIN"), false, "ad_remove.php");
$tab_array[] = array(gettext("Join KONTROL-UTM to a DOMAIN"), true, "ad_join.php");
$tab_array[] = array(gettext("Transparent Proxy Configuration"), false, "ad_transparent.php");
display_top_tabs($tab_array);

$testdomain = exec("net ads testjoin");

#Start Function GET INTERFACES
$pconfig['interface'] = array();
function build_interface_list() {
	global $pconfig;
	$iflist = array('options' => array(), 'selected' => array());
	$interfaces = get_configured_interface_with_descr();
	$interfaces['lo0'] = "Localhost";

	foreach ($interfaces as $iface => $ifacename) {
		if (!is_ipaddr(get_interface_ip($iface)) &&
		    !is_ipaddrv6(get_interface_ipv6($iface))) {
			continue;
		}
		$iflist['options'][$iface] = $ifacename;
		if (in_array($iface, $pconfig['interface'])) {
			array_push($iflist['selected'], $iface);
		}
	}

	return($iflist);
}




# FORM BEGIN ---------------------------------------------------------------------

$form = new Form(false);

$section = new Form_Section('Join KONTROL-UTM to DOMAIN');

#Set Server AD Name
$section->addInput(new Form_Input(
	'jad_server',
	'Servername',
	'text',
	($pconfig['jad_server'] ? $pconfig['jad_server']:'')
))->setHelp('Enter AD Server FQDN - eg: server01.domain.corp.');


$iflist = build_interface_list();
$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$iflist['selected'],
	$iflist['options'],
	true
))->setHelp('Choose the Interface(s) you want to use to bind to the Domain *NORMALLY LAN ONLY!*', '<br />');


#Set Username
$section->addInput(new Form_Input(
	'jad_user',
	'Username',
	'text',
	($pconfig['rad_user'] ? $pconfig['rad_user']:'')
))->setHelp('Enter Username/Account with Domain Administrator permissions.');

#Set Password
$section->addInput(new Form_Input(
	'jad_pass',
	'Password',
	'password',
	$pconfig['rad_pass']
));

$form->add($section);

if ($testdomain == "Join is OK")
	{
		echo "<tr><span style='color:#F00;text-align:center;'>This KONTROL-UTM box IS ALREADY part of a DOMAIN</span></tr>";
	}
	else
	{
		echo "<span style='color:#F00;text-align:center;'>This KONTROL-UTM box is NOT yet part of a DOMAIN</span>";
		$form->addGlobal(new Form_Button(
		'Submit',
		'Submit',
		null,
		'fa-power-off'
		))->addClass('btn-primary');
	}


print($form);

include("foot.inc");

?>
