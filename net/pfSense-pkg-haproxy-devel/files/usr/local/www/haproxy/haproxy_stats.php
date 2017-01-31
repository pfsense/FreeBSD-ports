<?php
/*
 * haproxy_stats.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 PiBa-NL
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
require_once("haproxy/haproxy_socketinfo.inc");

$pconfig = $config['installedpackages']['haproxy'];
if (isset($_GET['haproxystats']) || isset($_GET['scope']) || (isset($_POST) && isset($_POST['action']))){
	if (!(isset($pconfig['enable']) && $pconfig['localstatsport'] && is_numeric($pconfig['localstatsport']))){
		print 'In the "Settings" configure a internal stats port and enable haproxy for this to be functional. Also make sure the service is running.';
		return;
	}
	$fail = false;
	try{
		$request = "";
		if (is_array($_GET)){
			foreach($_GET as $key => $arg) {
				$request .= ";$key=$arg";
			}
		}
		$options = array(
		  'http'=>array(
			'method'=>"POST",
			'header'=>"Accept-language: en\r\n".
			          "Content-type: application/x-www-form-urlencoded\r\n",
			'content'=>http_build_query($_POST)
		));
		$context = stream_context_create($options);
		$response = @file_get_contents("http://127.0.0.1:{$pconfig['localstatsport']}/haproxy/haproxy_stats.php?haproxystats=1".$request, false, $context);
		if (is_array($http_response_header)){
			foreach($http_response_header as $header){
				if (strpos($header,"Refresh: ") == 0) {
					header($header);
				}
			}
		}
		$fail = $response === false;
	} catch (Exception $e) {
		$fail = true;
	}
	if ($fail) {
		$response = "<br/><br/>Make sure HAProxy settings are applied and HAProxy is enabled and running";
	}
	echo $response;
	exit(0);
}
require_once("guiconfig.inc");
if (isset($_GET['showsticktablecontent']) || isset($_GET['showstatresolvers'])) {
	if (is_numeric($pconfig['localstats_sticktable_refreshtime'])) {
		header("Refresh: {$pconfig['localstats_sticktable_refreshtime']}");
	}
}
$shortcut_section = "haproxy";
require_once("certs.inc");
require_once("haproxy/haproxy.inc");
require_once("haproxy/haproxy_utils.inc");
require_once("haproxy/pkg_haproxy_tabs.inc");

if (!is_array($config['installedpackages']['haproxy']['ha_backends']['item'])) {
	$config['installedpackages']['haproxy']['ha_backends']['item'] = array();
}
$a_frontend = &$config['installedpackages']['haproxy']['ha_backends']['item'];

if ($_POST) {
	if ($_POST['apply']) {
		$result = haproxy_check_and_run($savemsg, true);
		if ($result) {
			unlink_if_exists($d_haproxyconfdirty_path);
		}
	}
}

$pgtitle = array("Services", "HAProxy", "Stats");
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
haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "stats");

?>
	<div class="panel panel-default">

	<?
if (isset($_GET['showstatresolvers'])){
	$showstatresolversname = $_GET['showstatresolvers'];
	echo "<td colspan='2'>";
	echo "Resolver statistics: $sticktablename<br/>";
	$res = haproxy_socket_command("show stat resolvers $showstatresolversname");
	foreach($res as $line){
		echo "<br/>".print_r($line,true);
	}
	echo "</td>";
} elseif (isset($_GET['showsticktablecontent'])){
	$sticktablename = $_GET['showsticktablecontent'];
	echo "<td colspan='2'>";
	echo "Contents of the sticktable: $sticktablename<br/>";
	$res = haproxy_socket_command("show table $sticktablename");
	foreach($res as $line){
		echo "<br/>".print_r($line,true);
	}
	echo "</td>";
} else {
?>
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("Stats")?></h2>
		</div>
		<div class="table-responsive panel-body">
			This page contains a 'stats' page available from haproxy accessible through the pfSense gui.<br/>
			<br/>
			As the page is forwarded through the pfSense gui, this might cause some functionality to not work.<br/>
			Though the normal haproxy stats page can be tweaked more, and doesn't use a user/pass from pfSense itself.<br/>
			Some examples are configurable automatic page refresh, only showing certain servers, not providing admin options,<br/>
			and can be accessed from wherever the associated frontend is accessible.(as long as rules permit access)<br/>
			To use this or for simply an example how to use SSL-offloading configure stats on either a real backend while utilizing the 'stats uri'.<br/>
			Or create a backend specifically for serving stats, for that you can start with  the 'stats example' from the template tab.<br/>
		</div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("HAProxy stick-tables")?></h2>
		</div>
		<div class="table-responsive panel-body">
			These tables are used to store information for session persistence and can be used with ssl-session-id information, application-cookies, or other information that is used to persist a user to a server.
			<table  class="table table-hover table-striped table-condensed sortable-theme-bootstrap" data-sortable id="sortabletable">
			<thead>
				<tr>
				<th class="listhdrr">Stick-table</th>
				<th class="listhdrr">Type</th>
				<th class="listhdrr">Size</th>
				<th class="listhdrr">Used</th>
				</tr>
			</thead>
			<tbody>
			<? $tables = haproxy_get_tables();
			foreach($tables as $key => $table) { ?>
			<tr>
				<td class="listlr"><a href="/haproxy/haproxy_stats.php?showsticktablecontent=<?=$key;?>"><?=$key;?></td>
				<td class="listr"><?=$table['type'];?></td>
				<td class="listr"><?=$table['size'];?></td>
				<td class="listr"><?=$table['used'];?></td>
			</tr>
			<? } ?>
			</tbody>
			</table>
		</div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("HAProxy DNS statistics")?></h2>
		</div>
		<div class="table-responsive panel-body">
			<a href="/haproxy/haproxy_stats.php?showstatresolvers=globalresolvers" target="_blank">DNS statistics</a>
		</div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("HAProxy stats")?></h2>
		</div>
		<div class="table-responsive panel-body">
			<a href="/haproxy/haproxy_stats.php?haproxystats=1" target="_blank">Fullscreen stats page</a>
		</div>
		<div class="table-responsive panel-body">
		<? if (isset($pconfig['enable']) && $pconfig['localstatsport'] && is_numeric($pconfig['localstatsport'])){?>
			<iframe id="frame_haproxy_stats" width="1000" height="1500" seamless src="/haproxy/haproxy_stats.php?haproxystats=1<?=$request;?>"></iframe>
		<? } else { ?>
			<br/>
			In the "Settings" configure a internal stats port and enable haproxy for this to be functional. Also make sure the service is running.<br/>
			<br/>
		<? } ?>
		</div>
<?}?>		
	</div>
<?php include("foot.inc");
