<?php
/*
	vpn_openvpn_export.php
	part of pfSense (http://www.pfSense.org/)
	Copyright (C) 2008 Shrew Soft Inc.
	Copyright (C) 2010 Ermal Luçi
	Copyright (C) 2011-2015 Jim Pingle
	Copyright (C) 2011-2015 ESF, LLC
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
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.

	DISABLE_PHP_LINT_CHECKING
*/

require("globals.inc");
require("guiconfig.inc");
require("openvpn-client-export.inc");
require("pkg-utils.inc");
require('classes/Form.class.php');

global $current_openvpn_version, $current_openvpn_version_rev;

$pgtitle = array("OpenVPN", "Client Export Utility");

if (!is_array($config['openvpn']['openvpn-server'])) {
	$config['openvpn']['openvpn-server'] = array();
}

$a_server = $config['openvpn']['openvpn-server'];

if (!is_array($config['system']['user'])) {
	$config['system']['user'] = array();
}

$a_user = $config['system']['user'];

if (!is_array($config['cert'])) {
	$config['cert'] = array();
}

$a_cert = $config['cert'];

$ras_server = array();
foreach ($a_server as $server) {
	if (isset($server['disable'])) {
		continue;
	}
	$vpnid = $server['vpnid'];
	$ras_user = array();
	$ras_certs = array();
	if (stripos($server['mode'], "server") === false) {
		continue;
	}
	if (($server['mode'] == "server_tls_user") && ($server['authmode'] == "Local Database")) {
		foreach ($a_user as $uindex => $user) {
			if (!is_array($user['cert'])) {
				continue;
			}
			foreach ($user['cert'] as $cindex => $cert) {
				// If $cert is not an array, it's a certref not a cert.
				if (!is_array($cert)) {
					$cert = lookup_cert($cert);
				}

				if ($cert['caref'] != $server['caref']) {
					continue;
				}
				$ras_userent = array();
				$ras_userent['uindex'] = $uindex;
				$ras_userent['cindex'] = $cindex;
				$ras_userent['name'] = $user['name'];
				$ras_userent['certname'] = $cert['descr'];
				$ras_userent['cert'] = $cert;
				$ras_user[] = $ras_userent;
			}
		}
	} elseif (($server['mode'] == "server_tls") || 
			(($server['mode'] == "server_tls_user") && ($server['authmode'] != "Local Database"))) {
		foreach ($a_cert as $cindex => $cert) {
			if (($cert['caref'] != $server['caref']) || ($cert['refid'] == $server['certref'])) {
				continue;
			}
			$ras_cert_entry['cindex'] = $cindex;
			$ras_cert_entry['certname'] = $cert['descr'];
			$ras_cert_entry['certref'] = $cert['refid'];
			$ras_certs[] = $ras_cert_entry;
		}
	}

	$ras_serverent = array();
	$prot = $server['protocol'];
	$port = $server['local_port'];
	if ($server['description']) {
		$name = "{$server['description']} {$prot}:{$port}";
	} else {
		$name = "Server {$prot}:{$port}";
	}
	$ras_serverent['index'] = $vpnid;
	$ras_serverent['name'] = $name;
	$ras_serverent['users'] = $ras_user;
	$ras_serverent['certs'] = $ras_certs;
	$ras_serverent['mode'] = $server['mode'];
	$ras_serverent['crlref'] = $server['crlref'];
	$ras_serverent['authmode'] = $server['authmode'] != "Local Database" ? 'other' : 'local';
	$ras_server[$vpnid] = $ras_serverent;
}

$id = $_GET['id'];
if (isset($_POST['id'])) {
	$id = $_POST['id'];
}

$act = $_GET['act'];
if (isset($_POST['act'])) {
	$act = $_POST['act'];
}

global $simplefields;
$simplefields = array('server','useaddr','useaddr_hostname','verifyservercn','blockoutsidedns','randomlocalport',
	'usepkcs11','pkcs11providers',
	'usetoken','usepass',
	'useproxy','useproxytype','proxyaddr','proxyport','useproxypass','proxyuser',
	'openvpnmanager');
	//'pass','proxypass','advancedoptions'

$openvpnexportcfg = &$config['installedpackages']['vpn_openvpn_export'];
$ovpnserverdefaults = &$openvpnexportcfg['serverconfig']['item'];
$cfg = &$config['installedpackages']['vpn_openvpn_export']['defaultsettings'];
if (!is_array($ovpnserverdefaults)) {
	$ovpnserverdefaults = array();
}

if (isset($_POST['save'])) {
	$vpnid = $_POST['server'];
	$index = count($ovpnserverdefaults);
	foreach($ovpnserverdefaults as $key => $cfg) {
		if ($cfg['server'] == $vpnid) {
			$index = $key;
			break;
		}
	}
	$cfg = &$ovpnserverdefaults[$index];
	if (!is_array($cfg)) {
		$cfg = array();
	}
	if ($_POST['pass'] <> DMYPWD) {
		if ($_POST['pass'] <> $_POST['pass_confirm']) {
			$input_errors[] = "Different certificate passwords entered.";
		}
		$cfg['pass'] = $_POST['pass'];
	}
	if ($_POST['proxypass'] <> DMYPWD) {
		if ($_POST['proxypass'] <> $_POST['proxypass_confirm']) {
			$input_errors[] = "Different Proxy passwords entered.";
		}
		$cfg['proxypass'] = $_POST['proxypass'];
	}
	
	foreach ($simplefields as $value) {
		$cfg[$value] = $_POST[$value];
	}
	$cfg['advancedoptions'] = base64_encode($_POST['advancedoptions']);
	if (empty($input_errors)) {
		write_config("Save openvpn client export defaults");
	}
}

for($i = 0; $i < count($ovpnserverdefaults); $i++) {
	$ovpnserverdefaults[$i]['advancedoptions'] = base64_decode($ovpnserverdefaults[$i]['advancedoptions']);
}

if (!empty($act)) {

	$srvid = $_GET['srvid'];
	$usrid = $_GET['usrid'];
	$crtid = $_GET['crtid'];
	$srvcfg = get_openvpnserver_by_id($srvid);
	if ($srvid === false) {
		pfSenseHeader("vpn_openvpn_export.php");
		exit;
	} else if (($srvcfg['mode'] != "server_user") &&
		(($usrid === false) || ($crtid === false))) {
		pfSenseHeader("vpn_openvpn_export.php");
		exit;
	}

	if ($srvcfg['mode'] == "server_user") {
		$nokeys = true;
	} else {
		$nokeys = false;
	}

	$useaddr = '';
	if (isset($_GET['useaddr']) && !empty($_GET['useaddr'])) {
		$useaddr = trim($_GET['useaddr']);
	}

	if (!(is_ipaddr($useaddr) || is_hostname($useaddr) ||
		in_array($useaddr, array("serveraddr", "servermagic", "servermagichost", "serverhostname")))) {
		$input_errors[] = "An IP address or hostname must be specified.";
	}

	$advancedoptions = $_GET['advancedoptions'];
	$openvpnmanager = $_GET['openvpnmanager'];

	$verifyservercn = $_GET['verifyservercn'];
	$blockoutsidedns = $_GET['blockoutsidedns'];
	$randomlocalport = $_GET['randomlocalport'];
	$usetoken = $_GET['usetoken'];
	if ($usetoken && (substr($act, 0, 10) == "confinline")) {
		$input_errors[] = "Microsoft Certificate Storage cannot be used with an Inline configuration.";
	}
	if ($usetoken && (($act == "conf_yealink_t28") || ($act == "conf_yealink_t38g") || ($act == "conf_yealink_t38g2") || ($act == "conf_snom"))) {
		$input_errors[] = "Microsoft Certificate Storage cannot be used with a Yealink or SNOM configuration.";
	}
	$usepkcs11 = $_GET['usepkcs11'];
	$pkcs11providers = $_GET['pkcs11providers'];
	if ($usepkcs11 && !$pkcs11providers) {
		$input_errors[] = "You must provide the PKCS#11 providers.";
	}					
	$pkcs11id = $_GET['pkcs11id'];
	if ($usepkcs11 && !$pkcs11id) {
		$input_errors[] = "You must provide the PKCS#11 ID.";
	}					
	$password = "";
	if ($_GET['password']) {
		if ($_GET['password'] != DMYPWD) {
			$password = $_GET['password'];
		} else {
			$password = $cfg['pass'];
		}
	}

	$proxy = "";
	if (!empty($_GET['proxy_addr']) || !empty($_GET['proxy_port'])) {
		$proxy = array();
		if (empty($_GET['proxy_addr'])) {
			$input_errors[] = "An address for the proxy must be specified.";
		} else {
			$proxy['ip'] = $_GET['proxy_addr'];
		}
		if (empty($_GET['proxy_port'])) {
			$input_errors[] = "A port for the proxy must be specified.";
		} else {
			$proxy['port'] = $_GET['proxy_port'];
		}
		$proxy['proxy_type'] = $_GET['proxy_type'];
		$proxy['proxy_authtype'] = $_GET['proxy_authtype'];
		if ($_GET['proxy_authtype'] != "none") {
			if (empty($_GET['proxy_user'])) {
				$input_errors[] = "A username for the proxy configuration must be specified.";
			} else {
				$proxy['user'] = $_GET['proxy_user'];
			}
			if (!empty($_GET['proxy_user']) && empty($_GET['proxy_password'])) {
				$input_errors[] = "A password for the proxy user must be specified.";
			} else {
				if ($_GET['proxy_password'] != DMYPWD) {
					$proxy['password'] = $_GET['proxy_password'];
				} else {
					$proxy['password'] = $cfg['proxypass'];
				}
			}
		}
	}

	$exp_name = openvpn_client_export_prefix($srvid, $usrid, $crtid);

	if (substr($act, 0, 4) == "conf") {
		switch ($act) {
			case "confzip":
				$exp_name = urlencode($exp_name . "-config.zip");
				$expformat = "zip";
				break;
			case "conf_yealink_t28":
				$exp_name = urlencode("client.tar");
				$expformat = "yealink_t28";
				break;
			case "conf_yealink_t38g":
				$exp_name = urlencode("client.tar");
				$expformat = "yealink_t38g";
				break;
			case "conf_yealink_t38g2":
				$exp_name = urlencode("client.tar");
				$expformat = "yealink_t38g2";
				break;
			case "conf_snom":
				$exp_name = urlencode("vpnclient.tar");
				$expformat = "snom";
				break;
			case "confinline":
				$exp_name = urlencode($exp_name . "-config.ovpn");
				$expformat = "inline";
				break;
			case "confinlinedroid":
				$exp_name = urlencode($exp_name . "-android-config.ovpn");
				$expformat = "inlinedroid";
				break;
			case "confinlineios":
				$exp_name = urlencode($exp_name . "-ios-config.ovpn");
				$expformat = "inlineios";
				break;
			case "confinlinevisc":
				$exp_name = urlencode($exp_name . "-viscosity-config.ovpn");
				$expformat = "inlinevisc";
				break;
			default:
				$exp_name = urlencode($exp_name . "-config.ovpn");
				$expformat = "baseconf";
		}
		$exp_path = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $randomlocalport, $usetoken, $nokeys, $proxy, $expformat, $password, false, false, $openvpnmanager, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id);
	}

	if ($act == "visc") {
		$exp_name = urlencode($exp_name . "-Viscosity.visc.zip");
		$exp_path = viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $randomlocalport, $usetoken, $password, $proxy, $openvpnmanager, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id);
	}

	if (substr($act, 0, 4) == "inst") {
		$exp_name = urlencode($exp_name."-install.exe");
		$exp_path = openvpn_client_export_installer($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $randomlocalport, $usetoken, $password, $proxy, $openvpnmanager, $advancedoptions, substr($act, 5), $usepkcs11, $pkcs11providers, $pkcs11id);
	}

	if (!$exp_path) {
		$input_errors[] = "Failed to export config files!";
	}

	if (empty($input_errors)) {
		if (($act == "conf") || (substr($act, 0, 10) == "confinline")) {
			$exp_size = strlen($exp_path);
		} else {
			$exp_size = filesize($exp_path);
		}
		header('Pragma: ');
		header('Cache-Control: ');
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename={$exp_name}");
		header("Content-Length: $exp_size");
		if (($act == "conf") || (substr($act, 0, 10) == "confinline")) {
			echo $exp_path;
		} else {
			readfile($exp_path);
			@unlink($exp_path);
		}
		exit;
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}
$tab_array = array();
$tab_array[] = array(gettext("Server"), false, "vpn_openvpn_server.php");
$tab_array[] = array(gettext("Client"), false, "vpn_openvpn_client.php");
$tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
add_package_tabs("OpenVPN", $tab_array);
display_top_tabs($tab_array);

$form = new Form("Save as default");

$section = new Form_Section('OpenVPN Server');

$serverlist = array();
foreach ($ras_server as $server) {
	$serverlist[$server['index']] = $server['name'];
}

$section->addInput(new Form_Select(
	'server',
	'Remote Access Server',
	$cfg['server'],
	$serverlist
	));

$form->add($section);

$section = new Form_Section('Client Connection Behavior');

$useaddrlist = array(
	"serveraddr" => "Interface IP Address",
	"servermagic" => "Automagic Multi-WAN IPs (port forward targets)",
	"servermagichost" => "Automagic Multi-WAN DDNS Hostnames (port forward targets)",
	"serverhostname" => "Installation hostname"
);

if (is_array($config['dyndnses']['dyndns'])) {
	foreach ($config['dyndnses']['dyndns'] as $ddns) {
		if ($ddns['type'] == 'namecheap') {
			$useaddrlist[$ddns["host"] . '.' . $ddns["domainname"]] = $ddns["host"] . '.' . $ddns["domainname"];
		} else {
			$useaddrlist[$ddns["host"]] = $ddns["host"];
		}
	}
}
if (is_array($config['dnsupdates']['dnsupdate'])) {
	foreach ($config['dnsupdates']['dnsupdate'] as $ddns) {
		$useaddrlist[$ddns["host"]] = $ddns["host"];
	}
}

$useaddrlist["other"] = "Other";

$section->addInput(new Form_Select(
	'useaddr',
	'Host Name Resolution',
	$cfg['useaddr'],
	$useaddrlist
	));

$section->addInput(new Form_Input(
	'useaddr_hostname',
	'Host Name',
	'text',
	$cfg['useaddr_hostname']
))->setHelp('Enter the hostname or IP address the client will use to connect to this server.');


$section->addInput(new Form_Select(
	'verifyservercn',
	'Verify Server CN',
	$cfg['verifyservercn'],
	array(
		"auto" => "Automatic - Use verify-x509-name (OpenVPN 2.3+) where possible",
		"tls-remote" => "Use tls-remote (Deprecated, use only on old clients < OpenVPN 2.2.x)",
		"tls-remote-quote" => "Use tls-remote and quote the server CN",
		"none" => "Do not verify the server CN")
))->setHelp("Optionally verify the server certificate Common Name (CN) when the client connects. Current clients, including the most recent versions of Windows, Viscosity, Tunnelblick, OpenVPN on iOS and Android and so on should all work at the default automatic setting.".
	"<br/><br/>Only use tls-remote if an older client must be used. The option has been deprecated by OpenVPN and will be removed in the next major version.".
	"<br/><br/>With tls-remote the server CN may optionally be enclosed in quotes. This can help if the server CN contains spaces and certain clients cannot parse the server CN. Some clients have problems parsing the CN with quotes. Use only as needed.");

$section->addInput(new Form_Checkbox(
	'blockoutsidedns',
	'Block Outside DNS',
	'Block access to DNS servers except across OpenVPN while connected, forcing clients to use only VPN DNS servers.',
	$cfg['blockoutsidedns']
))->setHelp("Requires Windows 10 and OpenVPN 2.3.9 or later. Only Windows 10 is prone to DNS leakage in this way, other clients will ignore the option as they are not affected.");

$section->addInput(new Form_Checkbox(
	'randomlocalport',
	'Use Random Local Port',
	'Use a random local source port (lport) for traffic from the client. Without this set, two clients may not run concurrently.',
	$cfg['randomlocalport']
));

$form->add($section);

$section = new Form_Section('Certificate Export Options');

$section->addInput(new Form_Checkbox(
	'usepkcs11',
	'PKCS#11 Certificate Storage',
	'Use PKCS#11 storage device (cryptographic token, HSM, smart card) instead of local files.',
	$cfg['usepkcs11']
));

$section->addInput(new Form_Input(
	'pkcs11providers',
	'PKCS#11 Providers',
	'text',
	$cfg['pkcs11providers']
))->setHelp('Enter the client local path to the PKCS#11 provider(s) (DLL, module), multiple separated by a space character.');

$section->addInput(new Form_Input(
	'pkcs11id',
	'PKCS#11 ID',
	'text'
))->setHelp('Enter the object\'s ID on the PKCS#11 device.');

$section->addInput(new Form_Checkbox(
	'usetoken',
	'Microsoft Certificate Storage',
	'Use Microsoft Certificate Storage instead of local files.',
	$cfg['usetoken']
));

$section->addInput(new Form_Checkbox(
	'usepass',
	'Password Protect Certificate',
	'Use a password to protect the pkcs12 file contents or key in Viscosity bundle.',
	$cfg['usepass']
));

$section->addPassword(new Form_Input(
	'pass',
	'Certificate Password',
	'password',
	$cfg['pass']
))->setHelp('Password used to protect the certificate file contents.');

$form->add($section);

$section = new Form_Section('Proxy Options');

$section->addInput(new Form_Checkbox(
	'useproxy',
	'Use A Proxy',
	'Use proxy to communicate with the OpenVPN server.',
	$cfg['useproxy']
));

$section->addInput(new Form_Select(
	'useproxytype',
	'Proxy Type',
	$cfg['useproxytype'],
	array(
		"http" => "HTTP",
		"socks" => "SOCKS")
));

$section->addInput(new Form_Input(
	'proxyaddr',
	'Proxy IP Address',
	'text',
	$cfg['proxyaddr']
))->setHelp('Hostname or IP address of proxy server.');

$section->addInput(new Form_Input(
	'proxyport',
	'Proxy Port',
	'text',
	$cfg['proxyport']
))->setHelp('Port where proxy server is listening.');

$section->addInput(new Form_Select(
	'useproxypass',
	'Proxy Authentication',
	$cfg['useproxypass'],
	array(
		"none" => "None",
		"basic" => "Basic",
		"ntlm" => "NTLM")
))->setHelp('Choose proxy authentication method, if any.');

$section->addInput(new Form_Input(
	'proxyuser',
	'Proxy Username',
	'text',
	$cfg['proxyuser']
))->setHelp('Username for authentication to proxy server.');

$section->addPassword(new Form_Input(
	'proxypass',
	'Proxy Password',
	'password',
	$cfg['proxypass']
))->setHelp('Password for authentication to proxy server.');
$form->add($section);

$section = new Form_Section('Management Interface');

$section->addInput(new Form_Checkbox(
	'openvpnmanager',
	'Management Interface',
	'Use the OpenVPNManager Management Interface.',
	$cfg['openvpnmanager']
))->setHelp("This will activate management interface in the generated .ovpn configuration and ".
	"include the OpenVPNManager program in the Windows Installers. With this management interface, OpenVPN can be used by non-administrator users.".
	"This is also useful for Windows Vista/7/8/10 systems where elevated permissions are needed to add routes to the OS.".
	"<br/><br/>NOTE: This is not currently compatible with the 64-bit OpenVPN installer. It will work with the 32-bit installer on a 64-bit system.");

$form->add($section);

$section = new Form_Section('Advanced');

	$section->addInput(new Form_Textarea(
		'advancedoptions',
		'Additional configuration options',
		$cfg['advancedoptions']
	))->setHelp('Enter any additional options to add to the OpenVPN client export configuration here, separated by a line break or semicolon.<br/><br/>EXAMPLE: remote-random;');

$form->add($section);

print($form);
?>

<div class="panel panel-default" id="search-panel">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=gettext('Search')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#search-panel_panel-body">
					<i class="fa fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="search-panel_panel-body" class="panel-body collapse in">
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?=gettext("Search term")?>
			</label>
			<div class="col-sm-5"><input class="form-control" name="searchstr" id="searchstr" type="text"/></div>
			<div class="col-sm-3">
				<a id="btnsearch" title="<?=gettext("Search")?>" class="btn btn-primary btn-sm"><i class="fa fa-search icon-embed-btn"></i><?=gettext("Search")?></a>
				<a id="btnclear" title="<?=gettext("Clear")?>" class="btn btn-info btn-sm"><i class="fa fa-undo icon-embed-btn"></i><?=gettext("Clear")?></a>
			</div>
			<div class="col-sm-10 col-sm-offset-2">
				<span class="help-block"><?=gettext('Enter a search string or *nix regular expression to search.')?></span>
			</div>
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("OpenVPN Clients")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed" id="users">
				<thead>
					<tr>
						<td width="25%" class="listhdrr"><?=gettext("User")?></td>
						<td width="35%" class="listhdrr"><?=gettext("Certificate Name")?></td>
						<td width="40%" class="listhdrr"><?=gettext("Export")?></td>
					</tr>
				</thead>
				<tbody>
				</tbody>
			</table>
		</div>
	</div>
</div>
<br />
<br />
<?= print_info_box(gettext("The &quot;XP&quot; Windows installers work on Windows XP and later versions. The &quot;win6&quot; Windows installers include a new tap-windows6 driver that works only on Windows Vista and later. " .
"If a client is missing from the list it is usually due to a CA mismatch between the OpenVPN server instance and the client certificate found in the User Manager."), 'info'); ?>

Links to OpenVPN clients for various platforms:<br />
<br />
<a href="http://openvpn.net/index.php/open-source/downloads.html"><?= gettext("OpenVPN Community Client") ?></a> - <?=gettext("Binaries for Windows, Source for other platforms. Packaged above in the Windows Installers")?>
<br/><a href="https://play.google.com/store/apps/details?id=de.blinkt.openvpn"><?= gettext("OpenVPN For Android") ?></a> - <?=gettext("Recommended client for Android")?>
<br/><a href="http://www.featvpn.com/"><?= gettext("FEAT VPN For Android") ?></a> - <?=gettext("For older versions of Android")?>
<br/><?= gettext("OpenVPN Connect") ?>: <a href="https://play.google.com/store/apps/details?id=net.openvpn.openvpn"><?=gettext("Android (Google Play)")?></a> or <a href="https://itunes.apple.com/us/app/openvpn-connect/id590379981"><?=gettext("iOS (App Store)")?></a> - <?= gettext("Recommended client for iOS") ?>
<br/><a href="https://www.sparklabs.com/viscosity/"><?= gettext("Viscosity") ?></a> - <?= gettext("Recommended commercial client for Mac OS X and Windows") ?>
<br/><a href="https://tunnelblick.net"><?= gettext("Tunnelblick") ?></a> - <?= gettext("Free client for OS X") ?>

<script type="text/javascript">
//<![CDATA[
var viscosityAvailable = false;

var servers = new Array();
<?php
foreach ($ras_server as $sindex => $server): ?>
servers[<?=$sindex?>] = new Array();
servers[<?=$sindex?>][0] = '<?=$server['index']?>';
servers[<?=$sindex?>][1] = new Array();
servers[<?=$sindex?>][2] = '<?=$server['mode']?>';
servers[<?=$sindex?>][3] = new Array();
servers[<?=$sindex?>][4] = '<?=$server['authmode']?>';
<?php
	$c=0;
	foreach ($server['users'] as $uindex => $user): ?>
<?php		if (!$server['crlref'] || !is_cert_revoked($user['cert'], $server['crlref'])): ?>
servers[<?=$sindex?>][1][<?=$c?>] = new Array();
servers[<?=$sindex?>][1][<?=$c?>][0] = '<?=$user['uindex']?>';
servers[<?=$sindex?>][1][<?=$c?>][1] = '<?=$user['cindex']?>';
servers[<?=$sindex?>][1][<?=$c?>][2] = '<?=$user['name']?>';
servers[<?=$sindex?>][1][<?=$c?>][3] = '<?=str_replace("'", "\\'", $user['certname'])?>';
<?php	
			$c++;
		endif;
	endforeach;
	$c=0;
	foreach ($server['certs'] as $cert): ?>
<?php
		if (!$server['crlref'] || !is_cert_revoked($config['cert'][$cert['cindex']], $server['crlref'])): ?>
servers[<?=$sindex?>][3][<?=$c?>] = new Array();
servers[<?=$sindex?>][3][<?=$c?>][0] = '<?=$cert['cindex']?>';
servers[<?=$sindex?>][3][<?=$c?>][1] = '<?=str_replace("'", "\\'", $cert['certname'])?>';
<?php
			$c++;
		endif;
	endforeach;
endforeach; 
?>

serverdefaults = <?=json_encode($ovpnserverdefaults)?>;

function download_begin(act, i, j) {

	var index = document.getElementById("server").value;
	var users = servers[index][1];
	var certs = servers[index][3];
	var useaddr;

	var advancedoptions;

	if (document.getElementById("useaddr").value == "other") {
		if (document.getElementById("useaddr_hostname").value == "") {
			alert("Please specify an IP address or hostname.");
			return;
		}
		useaddr = document.getElementById("useaddr_hostname").value;
	} else {
		useaddr = document.getElementById("useaddr").value;
	}

	advancedoptions = document.getElementById("advancedoptions").value;

	var verifyservercn;
	verifyservercn = document.getElementById("verifyservercn").value;

	var blockoutsidedns = 0;
	if (document.getElementById("blockoutsidedns").checked) {
		blockoutsidedns = 1;
	}
	var randomlocalport = 0;
	if (document.getElementById("randomlocalport").checked) {
		randomlocalport = 1;
	}
	var usetoken = 0;
	if (document.getElementById("usetoken").checked) {
		usetoken = 1;
	}
	var usepkcs11 = 0;
	if (document.getElementById("usepkcs11").checked) {
		usepkcs11 = 1;
	}
	var pkcs11providers = document.getElementById("pkcs11providers").value;
	var pkcs11id = document.getElementById("pkcs11id").value;
	var usepass = 0;
	if (document.getElementById("usepass").checked) {
		usepass = 1;
	}
	var openvpnmanager = 0;
	if (document.getElementById("openvpnmanager").checked) {
		openvpnmanager = 1;
	}

	var pass = document.getElementById("pass").value;
	var pass_confirm = document.getElementById("pass_confirm").value;
	if (usepass && (act.substring(0, 4) == "inst")) {
		if (!pass || !pass_confirm) {
			alert("The password or confirm field is empty");
			return;
		}
		if (pass != pass_confirm) {
			alert("The password and confirm fields must match");
			return;
		}
	}

	var useproxy = 0;
	var useproxypass = 0;
	if (document.getElementById("useproxy").checked) {
		useproxy = 1;
	}

	var proxyaddr = document.getElementById("proxyaddr").value;
	var proxyport = document.getElementById("proxyport").value;
	if (useproxy) {
		if (!proxyaddr || !proxyport) {
			alert("The proxy ip and port cannot be empty");
			return;
		}

		if (document.getElementById("useproxypass").value != 'none') {
			useproxypass = 1;
		}

		var proxytype = document.getElementById("useproxytype").value;

		var proxyauth = document.getElementById("useproxypass").value;
		var proxyuser = document.getElementById("proxyuser").value;
		var proxypass = document.getElementById("proxypass").value;
		var proxypass_confirm = document.getElementById("proxypass_confirm").value;
		if (useproxypass) {
			if (!proxyuser) {
				alert("Please fill the proxy username and password.");
				return;
			}
			if (!proxypass || !proxypass_confirm) {
				alert("The proxy password or confirm field is empty");
				return;
			}
			if (proxypass != proxypass_confirm) {
				alert("The proxy password and confirm fields must match");
				return;
			}
		}
	}

	var dlurl;
	dlurl  = "/vpn_openvpn_export.php?act=" + act;
	dlurl += "&srvid=" + encodeURIComponent(servers[index][0]);
	if (users[i]) {
		dlurl += "&usrid=" + encodeURIComponent(users[i][0]);
		dlurl += "&crtid=" + encodeURIComponent(users[i][1]);
	}
	if (certs[j]) {
		dlurl += "&usrid=";
		dlurl += "&crtid=" + encodeURIComponent(certs[j][0]);
	}
	dlurl += "&useaddr=" + encodeURIComponent(useaddr);
	dlurl += "&verifyservercn=" + encodeURIComponent(verifyservercn);
	dlurl += "&blockoutsidedns=" + encodeURIComponent(blockoutsidedns);
	dlurl += "&randomlocalport=" + encodeURIComponent(randomlocalport);
	dlurl += "&openvpnmanager=" + encodeURIComponent(openvpnmanager);
	dlurl += "&usetoken=" + encodeURIComponent(usetoken);
	dlurl += "&usepkcs11=" + escape(usepkcs11);
	dlurl += "&pkcs11providers=" + escape(pkcs11providers);
	dlurl += "&pkcs11id=" + escape(pkcs11id);
	if (usepass) {
		dlurl += "&password=" + encodeURIComponent(pass);
	}
	if (useproxy) {
		dlurl += "&proxy_type=" + encodeURIComponent(proxytype);
		dlurl += "&proxy_addr=" + encodeURIComponent(proxyaddr);
		dlurl += "&proxy_port=" + encodeURIComponent(proxyport);
		dlurl += "&proxy_authtype=" + encodeURIComponent(proxyauth);
		if (useproxypass) {
			dlurl += "&proxy_user=" + encodeURIComponent(proxyuser);
			dlurl += "&proxy_password=" + encodeURIComponent(proxypass);
		}
	}

	dlurl += "&advancedoptions=" + encodeURIComponent(advancedoptions);

	window.open(dlurl, "_self");
}

function server_changed() {

	var table = document.getElementById("users");
	table = table.tBodies[0];
	
	while (table.rows.length > 0 ) {
		table.deleteRow(0);
	}

	function setFieldValue(field, value) {
		checkboxes = $("input[type=checkbox]#"+field);
		checkboxes.prop('checked', value == 'yes').trigger("change");
		
		inputboxes = $("input[type!=checkbox]#"+field);
		inputboxes.val(value);
		
		selectboxes = $("select#"+field);
		selectboxes.val(value);
		
		textareaboxes = $("textarea#"+field);
		textareaboxes.val(value);
	}

	var index = document.getElementById("server").value;
	for(i = 0; i < serverdefaults.length; i++) {
		if (serverdefaults[i]['server'] !== index) {
			continue;
		}
		fields = serverdefaults[i];
		fieldnames = Object.getOwnPropertyNames(fields);
		for (fieldnr = 0; fieldnr < fieldnames.length; fieldnr++) {
			fieldname = fieldnames[fieldnr];
			setFieldValue(fieldname, fields[fieldname]);
		}
		setFieldValue('pass_confirm', fields['pass']);
		setFieldValue('proxypass_confirm', fields['proxypass']);
		break;
	}
	
	
	var users = servers[index][1];
	var certs = servers[index][3];
	for (i = 0; i < users.length; i++) {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		var cell2 = row.insertCell(2);
		cell0.className = "listlr";
		cell0.innerHTML = users[i][2];
		cell1.className = "listr";
		cell1.innerHTML = users[i][3];
		cell2.className = "listr";
		cell2.innerHTML = "- Standard Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confzip\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Archive<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"conf\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Config Only<\/a>";
		cell2.innerHTML += "<br\/>- Inline Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinedroid\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Android<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlineios\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> OpenVPN Connect (iOS/Android)<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinline\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Others<\/a>";
		cell2.innerHTML += "<br\/>- Windows Installers (<?=$current_openvpn_version . '-Ix' . $current_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-xp\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x86-xp<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-xp\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x64-xp<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-win6\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x86-win6<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-win6\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x64-win6<\/a>";
		cell2.innerHTML += "<br\/>- Viscosity (Mac OS X and Windows):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"visc\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Viscosity Bundle<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinevisc\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Viscosity Inline Config<\/a>";
	}
	for (j = 0; j < certs.length; j++) {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		var cell2 = row.insertCell(2);
		cell0.className = "listlr";
		if (servers[index][2] == "server_tls") {
			cell0.innerHTML = "Certificate (SSL/TLS, no Auth)";
		} else {
			cell0.innerHTML = "Certificate with External Auth";
		}
		cell1.className = "listr";
		cell1.innerHTML = certs[j][1];
		cell2.className = "listr";
		cell2.innerHTML = "- Standard Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confzip\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Archive<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"conf\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> File Only<\/a>";
		cell2.innerHTML += "<br\/>- Inline Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinedroid\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Android<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlineios\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> OpenVPN Connect (iOS/Android)<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinline\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Others<\/a>";
		cell2.innerHTML += "<br\/>- Windows Installers (<?=$current_openvpn_version . '-Ix' . $current_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-xp\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x86-xp<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-xp\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x64-xp<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-win6\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x86-win6<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-win6\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x64-win6<\/a>";
		cell2.innerHTML += "<br\/>- Viscosity (Mac OS X and Windows):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"visc\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Viscosity Bundle<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinevisc\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Viscosity Inline Config<\/a>";
		if (servers[index][2] == "server_tls") {
			cell2.innerHTML += "<br\/>- Yealink SIP Handsets: <br\/>";
			cell2.innerHTML += "&nbsp;&nbsp; ";
			cell2.innerHTML += "<a href='javascript:download_begin(\"conf_yealink_t28\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> T28<\/a>";
			cell2.innerHTML += "&nbsp;&nbsp; ";
			cell2.innerHTML += "<a href='javascript:download_begin(\"conf_yealink_t38g\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> T38G (1)<\/a>";
			cell2.innerHTML += "&nbsp;&nbsp; ";
			cell2.innerHTML += "<a href='javascript:download_begin(\"conf_yealink_t38g2\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> T38G (2)<\/a>";
			cell2.innerHTML += "<br\/>";
			cell2.innerHTML += "- <a href='javascript:download_begin(\"conf_snom\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> SNOM SIP Handset<\/a>";
		}
	}
	if (servers[index][2] == 'server_user') {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		var cell2 = row.insertCell(2);
		cell0.className = "listlr";
		cell0.innerHTML = "Authentication Only (No Cert)";
		cell1.className = "listr";
		cell1.innerHTML = "none";
		cell2.className = "listr";
		cell2.innerHTML = "- Standard Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confzip\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Archive<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"conf\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> File Only<\/a>";
		cell2.innerHTML += "<br\/>- Inline Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinedroid\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Android<\a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlineios\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> OpenVPN Connect (iOS/Android)<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinline\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Others<\/a>";
		cell2.innerHTML += "<br\/>- Windows Installers (<?=$current_openvpn_version . '-Ix' . $current_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-xp\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x86-xp<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-xp\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x64-xp<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-win6\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x86-win6<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-win6\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> x64-win6<\/a>";
		cell2.innerHTML += "<br\/>- Viscosity (Mac OS X and Windows):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"visc\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Viscosity Bundle<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinevisc\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Viscosity Inline Config<\/a>";
	}
}

function useaddr_changed() {
	if ($('#useaddr').val() == "other") {
		hideInput('useaddr_hostname', false);
	} else {
		hideInput('useaddr_hostname', true);
	}
}

function usepkcs11_changed() {
	if ($('#usepkcs11').prop('checked')) {
		hideInput('pkcs11id', false);
		hideInput('pkcs11providers', false);
	} else {
		hideInput('pkcs11id', true);
		hideInput('pkcs11providers', true);
	}
}

function usepass_changed() {
	if ($('#usepass').prop('checked')) {
		hideInput('pass', false);
		hideInput('pass_confirm', false);
	} else {
		hideInput('pass', true);
		hideInput('pass_confirm', true);
	}
}

function useproxy_changed() {
	if ($('#useproxy').prop('checked')) {
		hideInput('useproxytype', false);
		hideInput('proxyaddr', false);
		hideInput('proxyport', false);
		hideInput('useproxypass', false);
	} else {
		hideInput('useproxytype', true);
		hideInput('proxyaddr', true);
		hideInput('proxyport', true);
		hideInput('useproxypass', true);
		hideInput('proxyuser', true);
		hideInput('proxypass', true);
		hideInput('proxypass_confirm', true);
	}
	if ($('#useproxy').prop('checked') && ($('#useproxypass').val() != 'none')) {
		hideInput('proxyuser', false);
		hideInput('proxypass', false);
		hideInput('proxypass_confirm', false);
	} else {
		hideInput('proxyuser', true);
		hideInput('proxypass', true);
		hideInput('proxypass_confirm', true);
	}
}

events.push(function(){
	// ---------- OnChange handlers ---------------------------------------------------------

	$('#server').on('change', function() {
		server_changed();
	});
	$('#useaddr').on('change', function() {
		useaddr_changed();
	});
	$('#usepkcs11').on('change', function() {
		usepkcs11_changed();
	});
	$('#usepass').on('change', function() {
		usepass_changed();
	});
	$('#useproxy').on('change', function() {
		useproxy_changed();
	});
	$('#useproxypass').on('change', function() {
		useproxy_changed();
	});

	// Make these controls plain buttons
	$("#btnsearch").prop('type', 'button');
	$("#btnclear").prop('type', 'button');

	// Search for a term in the package name and/or description
	$("#btnsearch").click(function() {
		var searchstr = $('#searchstr').val().toLowerCase();
		var table = $("table tbody");

		table.find('tr').each(function (i) {
			var $tds = $(this).find('td'),
				username = $tds.eq(0).text().trim().toLowerCase(),
				certname = $tds.eq(1).text().trim().toLowerCase();

			regexp = new RegExp(searchstr);
			if (searchstr.length > 0) {
				if (!(regexp.test(username)) && !(regexp.test(certname))) {
					$(this).hide();
				} else {
					$(this).show();
				}
			} else {
				$(this).show();	// A blank search string shows all
			}
		});
	});

	// Clear the search term and unhide all rows (that were hidden during a previous search)
	$("#btnclear").click(function() {
		var table = $("table tbody");

		$('#searchstr').val("");

		table.find('tr').each(function (i) {
			$(this).show();
		});
	});

	// Hitting the enter key will do the same as clicking the search button
	$("#searchstr").on("keyup", function (event) {
	    if (event.keyCode == 13) {
	        $("#btnsearch").get(0).click();
	    }
	});

	// ---------- On initial page load ------------------------------------------------------------

	server_changed();
	useaddr_changed();
	usepkcs11_changed();
	usepass_changed();
	useproxy_changed();
});
//]]>
</script>

<?php
include("foot.inc");