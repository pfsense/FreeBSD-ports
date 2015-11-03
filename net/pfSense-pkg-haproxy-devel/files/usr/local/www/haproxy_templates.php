<?php
/*
	haproxy_templates.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2014 PiBa-NL
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
*/
require_once("authgui.inc");
require_once("config.inc");

$pconfig = $config['installedpackages']['haproxy'];
require_once("guiconfig.inc");
$shortcut_section = "haproxy";
require_once("haproxy.inc");
require_once("certs.inc");
require_once("haproxy_utils.inc");
require_once("pkg_haproxy_tabs.inc");

if (!is_array($config['installedpackages']['haproxy']['ha_backends']['item'])) {
	$config['installedpackages']['haproxy']['ha_backends']['item'] = array();
}
$a_frontend = &$config['installedpackages']['haproxy']['ha_backends']['item'];

function haproxy_add_stats_example() {
	global $config, $d_haproxyconfdirty_path;
	$a_backends = &$config['installedpackages']['haproxy']['ha_pools']['item'];
	$a_frontends = &$config['installedpackages']['haproxy']['ha_backends']['item'];
	$webcert = haproxy_find_create_certificate("HAProxy stats default");
	
	$backend = array();
	$backend["name"] = "HAProxy_stats_ssl_backend";
	$backend["stats_enabled"] = "yes";
	$backend["stats_uri"] = "/";
	$backend["stats_refresh"] = "10";
	$a_backends[] = $backend;
	$changecount++;
	
	$frontend = array();
	$frontend["name"] = "HAProxy_stats_ssl_frontend";
	$frontend["status"] = "active";
	$frontend["type"] = "http";
	$frontend["a_extaddr"]["item"]["stats_name"]["extaddr"] = "lan_ipv4";
	$frontend["a_extaddr"]["item"]["stats_name"]["extaddr_port"] = "444";
	$frontend["a_extaddr"]["item"]["stats_name"]["extaddr_ssl"] = "yes";
	$frontend["ssloffloadcert"] = $webcert['refid'];
	$frontend["backend_serverpool"] = $backend["name"];
	$a_frontends[] = $frontend;
	$changecount++;
	$changedesc = "add new HAProxy stats example";
	
	if ($changecount > 0) {
		header("Location: haproxy_listeners.php");
		echo "touching: $d_haproxyconfdirty_path";
		touch($d_haproxyconfdirty_path);
		write_config($changedesc);
		exit;
	}
}

function template_errorfile() {
	global $config, $d_haproxyconfdirty_path, $savemsg;

	$a_files = &$config['installedpackages']['haproxy']['files']['item'];
	if (!is_array($a_files)) $a_files = array();

	$a_files_cache = haproxy_get_fileslist();
	if (!isset($a_files_cache["ExampleErrorfile"])) {
		$errorfile = <<<EOD
HTTP/1.0 503 Service Unavailable
Cache-Control: no-cache
Connection: close
Content-Type: text/html

<html> 
  <head>
    <title>Sorry the webserver you are trying to contact is currently not available.</title>
  </head> 
  <body style="font-family:Arial,Helvetica,sans-serif;">
    <div style="margin: 0 auto; width: 960px;"> 
          <h2>Sorry the webserver you are trying to contact is currently not available.</h2>
    </div>
The error returned is [<i>{errorcode} {errormsg}</i>] please try again later.
  </body> 
</html>
EOD;
		$newfile = array();
		$newfile['name'] = "ExampleErrorfile";
		$newfile['content'] = base64_encode($errorfile);
		$a_files[] = $newfile;
		$changecount++;
		$changedesc = "Errorfile added from template";
	} else {
		$savemsg = "File 'ExampleErrorfile' is already configured on the Files tab.";
	}
	
	$changedesc = "haproxy, add template errorfile";
	if ($changecount > 0) {
		header("Location: haproxy_files.php");
		echo "touching: $d_haproxyconfdirty_path";
		touch($d_haproxyconfdirty_path);
		write_config($changedesc);
		exit;
	}
}

function haproxy_template_multipledomains() {
	global $config, $d_haproxyconfdirty_path;
	$a_backends = &$config['installedpackages']['haproxy']['ha_pools']['item'];
	$a_frontends = &$config['installedpackages']['haproxy']['ha_backends']['item'];
	
	$backend = array();
	$backend["name"] = "example_backend1";
	$backend["stats_enabled"] = "yes";
	$backend["stats_uri"] = "/";
	$backend["stats_refresh"] = "10";
	$backend["stats_scope"] = ".";
	$backend["stats_node"] = "NODE1";
	$a_backends[] = $backend;
	
	$backend = array();
	$backend["name"] = "example_backend2";
	$backend["stats_enabled"] = "yes";
	$backend["stats_uri"] = "/";
	$backend["stats_refresh"] = "10";
	$backend["stats_scope"] = ".";
	$backend["stats_node"] = "NODE2";
	$a_backends[] = $backend;
	
	$backend = array();
	$backend["name"] = "example_backend3";
	$backend["stats_enabled"] = "yes";
	$backend["stats_uri"] = "/";
	$backend["stats_refresh"] = "10";
	$backend["stats_scope"] = ".";
	$backend["stats_node"] = "NODE3";
	$a_backends[] = $backend;
	
	$frontend = array();
	$frontend["name"] = "example_multipledomains";
	$frontend["status"] = "active";
	$frontend["type"] = "http";
	$frontend["a_extaddr"]["item"]["stats_name"]["extaddr"] = "wan_ipv4";
	$frontend["a_extaddr"]["item"]["stats_name"]["extaddr_port"] = "80";
	$frontend["backend_serverpool"] = "example_backend1";
	$acl = array();
	$acl["name"] = "mail_acl";
	$acl["expression"] = "host_matches";
	$acl["value"] = "mail.domain.tld";
	$frontend["ha_acls"]["item"][] = $acl;
	$action = array();
	$action["action"] = "use_backend";
	$action["use_backendbackend"] = "example_backend2";
	$action["acl"] = "mail_acl";
	$frontend["a_actionitems"]["item"][] = $action;
	$a_frontends[] = $frontend;
	
	$frontend = array();
	$frontend["name"] = "example_multipledomains_forum";
	$frontend["status"] = "active";
	$frontend["secondary"] = "yes";
	$frontend["primary_frontend"] = "example_multipledomains";
	$acl = array();
	$acl["name"] = "forum_acl";
	$acl["expression"] = "host_matches";
	$acl["value"] = "forum.domain.tld";
	$frontend["ha_acls"]["item"][] = $acl;
	$action = array();
	$action["action"] = "use_backend";
	$action["use_backendbackend"] = "example_backend3";
	$action["acl"] = "forum_acl";
	$frontend["a_actionitems"]["item"][] = $action;
	$a_frontends[] = $frontend;
	
	$changedesc = "haproxy, add multi domain example";
	header("Location: haproxy_listeners.php");
	echo "touching: $d_haproxyconfdirty_path";
	touch($d_haproxyconfdirty_path);
	write_config($changedesc);
	exit;
}

if (isset($_GET['add_stats_example'])) {
	$templateid = $_GET['add_stats_example'];
	switch ($templateid) {
		case "1":
			haproxy_add_stats_example();
			break;
		case "2":
			template_errorfile();
			break;
		case "3":
			haproxy_template_multipledomains();
			break;
	}
}

if ($_POST) {
	if ($_POST['apply']) {
		$result = haproxy_check_and_run($savemsg, true);
		if ($result)
			unlink_if_exists($d_haproxyconfdirty_path);
	}
}

$pgtitle = "Services: HAProxy: Templates";
include("head.inc");
haproxy_css();

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="haproxy_templates.php" method="post">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_haproxyconfdirty_path)): ?>
<?php print_info_box_np("The haproxy configuration has been changed.<br/>You must apply the changes in order for them to take effect.");?><br/>
<?php endif; ?>
</form>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <?php
	haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "templates");
  ?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
		<table class="tabcont" width="100%" height="100%" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic">Templates</td>
		</tr>
		<tr>
			<td colspan="2">This page contains some templates that can be added into the haproxy configuration to possible ways to configure haproxy using this the webgui from this package.</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic">Serving multiple domains from 1 frontend.</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">
				<a href="haproxy_templates.php?add_stats_example=3">Create configuration</a>
			</td>
			<td class="vtable">
				As an basic example of how to serve multiple domains on 1 listening ip:port.
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic">Stats SSL frontent+backend</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">
				<a href="haproxy_templates.php?add_stats_example=1">Create configuration</a>
			</td>
			<td class="vtable">
				As an basic example you can use the link below to create a 'stats' frontend/backend page which offers with more options like setting user/password and 'admin mode' when you go to the backend settings.<br/>
				TEMPLATE: Create stats example configuration using a frontend/backend combination with ssl<br/>
				<br/>
				After applying the changes made by the template use this link to visit the stats page: <a target="_blank" href="https://<?=get_interface_ip("lan");?>:444">https://pfSense-LAN-ip:444/</a>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic">Errorfile</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">
				<a href="haproxy_templates.php?add_stats_example=2">Create configuration</a>
			</td>
			<td class="vtable">
				As an basic example of an errorfile with name 'ExampleErrorfile' will be added if it does not exist.
				This file can then be used in the 'Error files' in the backend settings.
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
		</table>
	</div>
	</td>
	</tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
