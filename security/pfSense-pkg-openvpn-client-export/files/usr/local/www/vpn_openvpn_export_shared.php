<?php
/*
 * vpn_openvpn_export_shared.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2015 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2008 Shrew Soft Inc
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

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("openvpn-client-export.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");
require_once("classes/Form.class.php");

$pgtitle = array("OpenVPN", "Shared Key Export");

if (!is_array($config['openvpn'])) {
	$config['openvpn'] = array();
}
if (!is_array($config['openvpn']['openvpn-server'])) {
	$config['openvpn']['openvpn-server'] = array();
}

$a_server = $config['openvpn']['openvpn-server'];

$ras_server = array();
foreach ($a_server as $server) {
	if (isset($server['disable'])) {
		continue;
	}
	$ras_user = array();
	if ($server['mode'] != "p2p_shared_key") {
		continue;
	}
	$vpnid = $server['vpnid'];
	$ras_serverent = array();
	$prot = $server['protocol'];
	$port = $server['local_port'];
	if ($server['description']) {
		$name = "{$server['description']} {$prot}:{$port}";
	} else {
		$name = "Shared Key Server {$prot}:{$port}";
	}
	$ras_serverent['index'] = $vpnid;
	$ras_serverent['name'] = $name;
	$ras_serverent['mode'] = $server['mode'];
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

$error = false;

if (($act == "skconfinline") || ($act == "skconf") || ($act == "skzipconf")) {
	$srvid = $_GET['srvid'];
	$srvcfg = get_openvpnserver_by_id($srvid);
	if (($srvid === false) || ($srvcfg['mode'] != "p2p_shared_key")) {
		pfSenseHeader("vpn_openvpn_export_shared.php");
		exit;
	}

	if (empty($_GET['useaddr'])) {
		$error = true;
		$input_errors[] = "An IP address or hostname must be specified.";
	} else {
		$useaddr = $_GET['useaddr'];
	}

	$proxy = "";
	if (!empty($_GET['proxy_addr']) || !empty($_GET['proxy_port'])) {
		$proxy = array();
		if (empty($_GET['proxy_addr'])) {
			$error = true;
			$input_errors[] = "An address for the proxy must be specified.";
		} else {
			$proxy['ip'] = $_GET['proxy_addr'];
		}
		if (empty($_GET['proxy_port'])) {
			$error = true;
			$input_errors[] = "A port for the proxy must be specified.";
		} else {
			$proxy['port'] = $_GET['proxy_port'];
		}
		$proxy['proxy_type'] = $_GET['proxy_type'];
		$proxy['proxy_authtype'] = $_GET['proxy_authtype'];
		if ($_GET['proxy_authtype'] != "none") {
			if (empty($_GET['proxy_user'])) {
				$error = true;
				$input_errors[] = "A username for the proxy configuration must be specified.";
			} else {
				$proxy['user'] = $_GET['proxy_user'];
			}
			if (!empty($_GET['proxy_user']) && empty($_GET['proxy_password'])) {
				$error = true;
				$input_errors[] = "A password for the proxy user must be specified.";
			} else {
				$proxy['password'] = $_GET['proxy_password'];
			}
		}
	}

	$exp_name = openvpn_client_export_prefix($srvid);
	if ($act == "skconfinline") {
		$nokeys = false;
	} elseif ($act == "skconf") {
		$nokeys = true;
	} elseif ($act == "skzipconf") {
		$zipconf = true;
	}
	$exp_data = openvpn_client_export_sharedkey_config($srvid, $useaddr, $proxy, $nokeys, $zipconf);
	if (!$exp_data) {
		$input_errors[] = "Failed to export config files!";
		$error = true;
	}
	if (!$error) {
		if ($zipconf) {
			$exp_name = urlencode($exp_data);
			$exp_size = filesize("{$g['tmp_path']}/{$exp_data}");
		} else {
			$exp_name = urlencode($exp_name."-config.ovpn");
			$exp_size = strlen($exp_data);
		}

		header('Pragma: ');
		header('Cache-Control: ');
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename={$exp_name}");
		header("Content-Length: $exp_size");
		if ($zipconf) {
			readfile("{$g['tmp_path']}/{$exp_data}");
		} else {
			echo $exp_data;
		}

		@unlink("{$g['tmp_path']}/{$exp_data}");
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

$form = new Form(false);

$section = new Form_Section('OpenVPN Server');

$serverlist = array();
foreach ($ras_server as $server) {
	$serverlist[$server['index']] = $server['name'];
}

$section->addInput(new Form_Select(
	'server',
	'Shared Key Server',
	null,
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
		$useaddrlist[$ddns["host"]] = $ddns["host"];
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
	null,
	$useaddrlist
	));

$section->addInput(new Form_Input(
	'useaddr_hostname',
	'Host Name'
))->setHelp('Enter the hostname or IP address the client will use to connect to this server.');

$form->add($section);

$section = new Form_Section('Proxy Options');

$section->addInput(new Form_Checkbox(
	'useproxy',
	'Use A Proxy',
	'Use proxy to communicate with the OpenVPN server.',
	false
));

$section->addInput(new Form_Select(
	'useproxytype',
	'Proxy Type',
	null,
	array(
		"http" => "HTTP",
		"socks" => "SOCKS")
));

$section->addInput(new Form_Input(
	'proxyaddr',
	'Proxy IP Address'
))->setHelp('Hostname or IP address of proxy server.');

$section->addInput(new Form_Input(
	'proxyport',
	'Proxy Port'
))->setHelp('Port where proxy server is listening.');

$section->addInput(new Form_Select(
	'useproxypass',
	'Proxy Authentication',
	null,
	array(
		"none" => "None",
		"basic" => "Basic",
		"ntlm" => "NTLM")
))->setHelp('Choose proxy authentication method, if any.');

$section->addInput(new Form_Input(
	'proxyuser',
	'Proxy Username'
))->setHelp('Username for authentication to proxy server.');

$section->addInput(new Form_Input(
	'proxypass',
	'Proxy Password',
	'password'
))->setHelp('Password for authentication to proxy server.');

$section->addInput(new Form_Input(
	'proxyconf',
	'Proxy Password (Confirm)',
	'password'
))->setHelp('Password for authentication to proxy server.');

$form->add($section);

print($form);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("OpenVPN Shared Key Clients")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed" id="clients">
				<thead>
					<tr>
						<td width="25%" class="listhdrr"><?=gettext("Client Type")?></td>
						<td width="50%" class="listhdrr"><?=gettext("Export")?></td>
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
<?= print_info_box(gettext("These are shared key configurations for use in site-to-site tunnels with other routers. Shared key tunnels are not normally used for remote access connections to end users."), 'info'); ?>

<script type="text/javascript">
//<![CDATA[
var viscosityAvailable = false;

var servers = new Array();
<?php	foreach ($ras_server as $sindex => $server): ?>
servers[<?=$sindex?>] = new Array();
servers[<?=$sindex?>][0] = '<?=$server['index']?>';
servers[<?=$sindex?>][1] = new Array();
servers[<?=$sindex?>][2] = '<?=$server['mode']?>';
<?php	endforeach; ?>

function download_begin(act) {

	var index = document.getElementById("server").value;
	var useaddr;

	if (document.getElementById("useaddr").value == "other") {
		if (document.getElementById("useaddr_hostname").value == "") {
			alert("Please specify an IP address or hostname.");
			return;
		}
		useaddr = document.getElementById("useaddr_hostname").value;
	} else {
		useaddr = document.getElementById("useaddr").value;
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
		var proxyconf = document.getElementById("proxyconf").value;
		if (useproxypass) {
			if (!proxyuser) {
				alert("Please fill the proxy username and password.");
				return;
			}
			if (!proxypass || !proxyconf) {
				alert("The proxy password or confirm field is empty");
				return;
			}
			if (proxypass != proxyconf) {
				alert("The proxy password and confirm fields must match");
				return;
			}
		}
	}

	var dlurl;
	dlurl  = "/vpn_openvpn_export_shared.php?act=" + act;
	dlurl += "&srvid=" + servers[index][0];
	dlurl += "&useaddr=" + useaddr;
	if (useproxy) {
		dlurl += "&proxy_type=" + escape(proxytype);
		dlurl += "&proxy_addr=" + proxyaddr;
		dlurl += "&proxy_port=" + proxyport;
		dlurl += "&proxy_authtype=" + proxyauth;
		if (useproxypass) {
			dlurl += "&proxy_user=" + proxyuser;
			dlurl += "&proxy_password=" + proxypass;
		}
	}

	window.open(dlurl, "_self");
}

function server_changed() {

	var table = document.getElementById("clients");
	while (table.rows.length > 1 ) {
		table.deleteRow(1);
	}

	var index = document.getElementById("server").value;

	if (servers[index][2] == 'p2p_shared_key') {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		cell0.className = "listlr";
		cell0.innerHTML = "Other Shared Key OS Client";
		cell1.className = "listr";
		cell1.innerHTML = "<a href='javascript:download_begin(\"skconfinline\")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Inline Configuration<\/a>";
		cell1.innerHTML += "&nbsp;&nbsp;";
		cell1.innerHTML += "<a href='javascript:download_begin(\"skconf\")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Configuration Only<\/a>";
		cell1.innerHTML += "&nbsp;&nbsp;";
		cell1.innerHTML += "<a href='javascript:download_begin(\"skzipconf\")' class=\"btn btn-sm btn-primary\"><i class=\"fa fa-download\"></i> Configuration archive<\/a>";
	}
}

function useaddr_changed() {
	if ($('#useaddr').val() == "other") {
		hideInput('useaddr_hostname', false);
	} else {
		hideInput('useaddr_hostname', true);
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
		hideInput('proxyconf', true);
	}
	if ($('#useproxy').prop('checked') && ($('#useproxypass').val() != 'none')) {
		hideInput('proxyuser', false);
		hideInput('proxypass', false);
		hideInput('proxyconf', false);
	} else {
		hideInput('proxyuser', true);
		hideInput('proxypass', true);
		hideInput('proxyconf', true);
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
	$('#useproxy').on('change', function() {
		useproxy_changed();
	});
	$('#useproxypass').on('change', function() {
		useproxy_changed();
	});

	// ---------- On initial page load ------------------------------------------------------------

	server_changed();
	useaddr_changed();
	useproxy_changed();
});

//]]>
</script>
<?php include("foot.inc"); ?>
