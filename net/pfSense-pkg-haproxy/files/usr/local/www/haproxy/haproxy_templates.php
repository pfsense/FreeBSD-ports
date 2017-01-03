<?php
/*
 * haproxy_templates.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2014 PiBa-NL
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

require_once("authgui.inc");
require_once("config.inc");

$pconfig = $config['installedpackages']['haproxy'];
require_once("guiconfig.inc");
require_once("certs.inc");
$shortcut_section = "haproxy";
require_once("haproxy/haproxy.inc");
require_once("haproxy/haproxy_utils.inc");
require_once("haproxy/pkg_haproxy_tabs.inc");

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
	
	$changedesc = "add new HAProxy stats example";
	
	header("Location: haproxy_listeners.php");
	echo "touching: $d_haproxyconfdirty_path";
	touch($d_haproxyconfdirty_path);
	write_config($changedesc);
	exit;
}

function template_errorfile() {
	global $config, $d_haproxyconfdirty_path, $savemsg;

	$a_files = &$config['installedpackages']['haproxy']['files']['item'];
	if (!is_array($a_files)) {
		$a_files = array();
	}
	$a_files_cache = haproxy_get_fileslist();
	$changecount = 0;
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

if ($_POST) {
	if ($_POST['apply']) {
		$result = haproxy_check_and_run($savemsg, true);
		if ($result) {
			unlink_if_exists($d_haproxyconfdirty_path);
		}
	} elseif ($_POST['createexample_mutipledomain']) {
		haproxy_template_multipledomains();
	} elseif ($_POST['createexample_ssl']) {
		haproxy_add_stats_example();
	} elseif ($_POST['createexample_errorfile']) {
		template_errorfile();
	}
}

$pgtitle = array("Services", "HAProxy", "Templates");
include("head.inc");
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}
if (file_exists($d_haproxyconfdirty_path)) {
	print_apply_box(sprintf(gettext("The haproxy configuration has been changed.%sYou must apply the changes in order for them to take effect."), "<br/>"));
}
haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "templates");
?>
<form action="haproxy_templates.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("Templates")?></h2>
		</div>
		<div class="table-responsive panel-body">
			This page contains some templates that can be added into the haproxy configuration to possible ways to configure haproxy using this the webgui from this package.
		</div>
		<br/>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("Serving multiple domains from 1 frontend")?></h2>
		</div>
		<div class="table-responsive panel-body">
			<div class="col-sm-3">
				<br/><?=new Form_Button('createexample_mutipledomain','Create configuration');?>
			</div>
			<div class="col-sm-8">
				As an basic example of how to serve multiple domains on 1 listening ip:port.
				No actual backend servers are used in the example, in the created example only stats pages are enabled on each backend.
				So to make it 'functional' for a real scenario some servers should be added and stats move to a different 'Stats Uri' or disabled.
			</div>
		</div>
		<br/>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("Stats SSL frontent+backend")?></h2>
		</div>
		<div class="table-responsive panel-body">
			<div class="col-sm-3">
				<br/><?=new Form_Button('createexample_ssl','Create configuration');?>
			</div>
			<div class="col-sm-8">
				As an basic example you can use the link below to create a 'stats' frontend/backend page which offers with more options like setting user/password and 'admin mode' when you go to the backend settings.<br/>
				TEMPLATE: Create stats example configuration using a frontend/backend combination with ssl<br/>
				<br/>
				After applying the changes made by the template use this link to visit the stats page: <a target="_blank" href="https://<?=get_interface_ip("lan");?>:444">https://pfSense-LAN-ip:444/</a>
			</div>
		</div>	
		<br/>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("Errorfile")?></h2>
		</div>
		<div class="table-responsive panel-body">
			<div class="col-sm-3">
				<?=new Form_Button('createexample_errorfile','Create configuration');?>
			</div>
			<div class="col-sm-8">
				As an basic example of an errorfile with name 'ExampleErrorfile' will be added if it does not exist.
				This file can then be used in the 'Error files' in the backend settings.
			</div>
		</div>
		<br/>
	</div>
</form>
<?php include("foot.inc");
