<?php
/*
 * status_frr.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2010-2015 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2010 Nick Buraglio <nick@buraglio.com>
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

require("guiconfig.inc");
global $config;
$control_script = "/usr/local/bin/frrctl";
$pkg_homedir = "/var/etc/frr";
global $commands;
$commands = array();

/* List all of the commands as an index. */
function listCmds() {
	global $commands; ?>
	<ul width="700">
<?php	foreach ($commands as $idx => $command): ?>
		<li>
			<strong><a href="#<?= htmlspecialchars($command['title']) ?>"><?= htmlspecialchars($command['title']) ?></a></strong>
		</li>
<?php	endforeach; ?>
	</ul>
<?php
}

/* Execute all of the commands which were defined by a call to defCmd. */
function execCmds() {
	global $commands;
	foreach ($commands as $idx => $command) {
		showCmdT($idx, $command);
	}
}

/* Define a command, with a title, to be executed later. */
function defCmdT($idx, $title, $command, $has_filter = false, $header_size = 0) {
	global $commands;
	$title = htmlspecialchars($title, ENT_NOQUOTES);
	$commands[$idx] = array(
		'title' => $title,
		'command' => $command,
		'has_filter' => $has_filter,
		'header_size' => $header_size);
}


function doCmdT($command, $limit = "all", $filter = "", $header_size = 0) {
	$grepline = "";
	if (!empty($filter) && ($filter != "undefined")) {
		$ini = ($header_size > 0 ? $header_size+1 : 1);
		$grepline = " | /usr/bin/sed -e '{$ini},\$ { /" . escapeshellarg(htmlspecialchars($filter)) . "/!d; };'";
	}
	if (is_numeric($limit) && $limit > 0) {
		$limit += $header_size;
		$headline = " | /usr/bin/head -n " . escapeshellarg($limit);
	}

	$fd = popen("{$command}{$grepline}{$headline} 2>&1", "r");
	$ct = 0;
	$result = "";
	while (($line = fgets($fd)) !== FALSE) {
		$result .= htmlspecialchars($line, ENT_NOQUOTES);
		if ($ct++ > 1000) {
			ob_flush();
			$ct = 0;
		}
	}
	pclose($fd);

	return $result;
}

function countCmdT($command) {
	$fd = popen("{$command} 2>&1", "r");
	$c = 0;
	while (fgets($fd) !== FALSE) {
		$c++;
	}
	pclose($fd);

	return $c;
}

function showCmdT($idx, $data) { ?>
	<p>
	<table class="table table-hover table-condensed">
		<tr><td colspan="2" class="listtopic">
			<div name="<?= htmlspecialchars($data['title']) ?>">
				<h5><a name="<?= htmlspecialchars($data['title']) ?>"><?= htmlspecialchars($data['title']) ?></a></h5>
			</div>
		</td></tr>
<?php
	$limit_default = "all";
	if ($data['has_filter']):
		$limit_options = array("10", "50", "100", "200", "500", "1000", "all");
		$limit_default = "100"; ?>

		<tr><td class="listhdr" style="font-weight:bold;">
			Display
			<select onchange="update_filter('<?= $idx ?>','<?= $data['header_size'] ?>');" name="<?= $idx ?>_limit" id="<?= $idx ?>_limit">
		<?php	foreach ($limit_options as $item): ?>
				<option value="<?= $item ?>" <?= ($item == $limit_default ? "selected" : "") ?>><?= $item ?></option>
		<?php	endforeach; ?>
			</select>
			<span name="<?= $idx ?>_count" id="<?= $idx ?>_count">items</span>
		</td>
		<td class="listhdr" align="right" style="font-weight:bold;">
			Filter expression:
			<input type="text" name="<?= $idx ?>_filter" id="<?= $idx ?>_filter" class="formfld search" value="<?= htmlspecialchars($_REQUEST["{$idx}_filter"]) ?>" size="30" />
			<input type="button" class="formbtn" value="Filter" onclick="update_filter('<?= $idx ?>','<?= $data['header_size'] ?>');" />
		</td></tr>
<?php	endif; ?>

	<tr><td colspan=2 class=listlr>
		<pre id=<?= $idx ?>>Gathering data, please wait...</pre>
	</td></tr>
	</table>
<?php
}

/* Load configuration blocks and check which daemons are enabled. */
if (is_array($config['installedpackages']['frr']['config'])) {
	$frr_conf = &$config['installedpackages']['frr']['config'][0];
}
$frr_enabled = (isset($frr_conf) && !empty($frr_conf['enable'])) || !empty($config['installedpackages']['frrglobalraw']['config'][0]['zebra']);

if (is_array($config['installedpackages']['frrbgp']['config'])) {
	$frr_bgp_conf = &$config['installedpackages']['frrbgp']['config'][0];
}
$bgpd_enabled = (isset($frr_bgp_conf) && !empty($frr_bgp_conf['enable'])) || !empty($config['installedpackages']['frrglobalraw']['config'][0]['bgpd']);

if (is_array($config['installedpackages']['frrospfd']['config'])) {
	$ospfd_conf = &$config['installedpackages']['frrospfd']['config'][0];
}
$ospfd_enabled = (isset($ospfd_conf) && !empty($ospfd_conf['enable'])) || !empty($config['installedpackages']['frrglobalraw']['config'][0]['ospfd']);

if (is_array($config['installedpackages']['frrospf6d']['config'])) {
	$ospf6d_conf = &$config['installedpackages']['frrospf6d']['config'][0];
}
$ospf6d_enabled = (isset($ospf6d_conf) && !empty($ospf6d_conf['enable'])) || !empty($config['installedpackages']['frrglobalraw']['config'][0]['ospf6d']);

$pgtitle = array(gettext("Services"),gettext("FRR"),gettext("Status"));

/* General commands for "All" screen or specific protocol pages */
if ((empty($_REQUEST['protocol']) || ($_REQUEST['protocol'] == "zebra")) && $frr_enabled) {
	defCmdT("zebra_routes", "Zebra Routes", "{$control_script} zebra route", true, 5);
}
if ((empty($_REQUEST['protocol']) || ($_REQUEST['protocol'] == "zebra")) && $frr_enabled) {
	defCmdT("zebra_routes6", "Zebra IPv6 Routes", "{$control_script} zebra route6", true, 5);
}

if ((empty($_REQUEST['protocol']) || ($_REQUEST['protocol'] == "bgp")) && $frr_enabled && $bgpd_enabled) {
	defCmdT("bgp_routes", "BGP Routes", "{$control_script} bgp route", true, 6);
	defCmdT("bgp_ipv6_routes", "BGP IPv6 Routes", "{$control_script} bgp6 route", true, 6);
	defCmdT("bgp_summary", "BGP Summary", "{$control_script} bgp sum");
	defCmdT("bgp_neighbors", "BGP Neighbors", "{$control_script} bgp neighbor");
}

if ((empty($_REQUEST['protocol']) || ($_REQUEST['protocol'] == "ospf")) && $frr_enabled && $ospfd_enabled) {
	defCmdT("ospf_general", "OSPF General", "{$control_script} ospf general");
	defCmdT("ospf_neighbors", "OSPF Neighbors", "{$control_script} ospf neighbor");
	defCmdT("ospf_routes", "OSPF Routes", "{$control_script} ospf route", true, 1);
}

if ((empty($_REQUEST['protocol']) || ($_REQUEST['protocol'] == "ospf6")) && $frr_enabled && $ospfd_enabled) {
	defCmdT("ospf6_general", "OSPF6 General", "{$control_script} ospf6 general");
	defCmdT("ospf6_neighbors", "OSPF6 Neighbors", "{$control_script} ospf6 neighbor");
	defCmdT("ospf6_routes", "OSPF6 Routes", "{$control_script} ospf6 route", true, 1);
}

$title_label = "FRR";
$message = "";
switch ($_REQUEST['protocol']) {
	case "zebra":
		$title_label = "Zebra";
		if ($frr_enabled) {
			defCmdT("zebra_cpu", "Zebra CPU", "{$control_script} zebra cpu");
			defCmdT("zebra_interfaces", "Zebra Interfaces", "{$control_script} zebra int");
			defCmdT("zebra_memory", "Zebra Memory", "{$control_script} zebra mem");
		}
		break;
	case "bgp":
		$title_label = "BGP";
		if ($frr_enabled && $bgpd_enabled) {
			defCmdT("bgp_peers", "BGP Peer Groups", "{$control_script} bgp peer");
			defCmdT("bgp_nexthops", "BGP Next Hops", "{$control_script} bgp nexthop");
			defCmdT("bgp_memory", "BGP Memory", "{$control_script} bgp mem");
		} else {
			$message = "BGP is not enabled";
		}
		break;
	case "ospf":
		$title_label = "OSPF";
		if ($frr_enabled && $ospfd_enabled) {
			defCmdT("ospf_db", "OSPF Database", "{$control_script} ospf database");
			defCmdT("ospf_routerdb", "OSPF Router Database", "{$control_script} ospf database router");
			defCmdT("ospf_interfaces", "OSPF Interfaces", "{$control_script} ospf interfaces");
			defCmdT("ospf_cpu", "OSPF CPU Usage", "{$control_script} ospf cpu");
			defCmdT("ospf_memory", "OSPF Memory", "{$control_script} ospf mem");
		} else {
			$message = "OSPF is not enabled";
		}
		break;
	case "ospf6":
		$title_label = "OSPF6";
		if ($frr_enabled && $ospf6d_enabled) {
			defCmdT("ospf6_db", "OSPF6 Database", "{$control_script} ospf6 database");
			defCmdT("ospf6_routerdb", "OSPF6 Router Database", "{$control_script} ospf6 database router");
			defCmdT("ospf6_interfaces", "OSPF6 Interfaces", "{$control_script} ospf6 interfaces");
			defCmdT("ospf6_cpu", "OSPF6 CPU Usage", "{$control_script} ospf6 cpu");
			defCmdT("ospf6_memory", "OSPF6 Memory", "{$control_script} ospf6 mem");
		} else {
			$message = "OSPF6 is not enabled";
		}
		break;
	case "config":
		$title_label = "FRR Configuration";
		$config_files = array(
			'zebra',
			'bgpd',
			'ospfd',
			'ospf6d',
			);
		foreach ($config_files as $cf) {
			if (file_exists("{$pkg_homedir}/{$cf}.conf") &&
			    (filesize("{$pkg_homedir}/{$cf}.conf") > 0)) {
				defCmdT("frr_{$cf}_config", "FRR {$cf}.conf", "/bin/cat {$pkg_homedir}/{$cf}.conf");
			}
		}
		break;
}
if ($title_label != "FRR") {
	$pgtitle[] = $title_label;
}

if (isset($_REQUEST['isAjax'])) {
	if (isset($_REQUEST['cmd']) && isset($commands[$_REQUEST['cmd']])) {
		echo "{$_REQUEST['cmd']}\n";
		if (isset($_REQUEST['count'])) {
			echo " of " . countCmdT($commands[$_REQUEST['cmd']]['command']) . " items";
		} else {
			echo htmlspecialchars_decode(doCmdT($commands[$_REQUEST['cmd']]['command'], $_REQUEST['limit'], $_REQUEST['filter'], $_REQUEST['header_size']));
		}
	}
	exit;
}

$tab_array = array();
$tab_array[] = array(gettext("All"), empty($_REQUEST['protocol']), "/status_frr.php");
$tab_array[] = array(gettext("Zebra "), ($_REQUEST['protocol'] == "zebra"), "/status_frr.php?protocol=zebra");
$tab_array[] = array(gettext("BGP"), ($_REQUEST['protocol'] == "bgp"), "/status_frr.php?protocol=bgp");
$tab_array[] = array(gettext("OSPF"), ($_REQUEST['protocol'] == "ospf"), "/status_frr.php?protocol=ospf");
$tab_array[] = array(gettext("OSPF6 "), ($_REQUEST['protocol'] == "ospf6"), "/status_frr.php?protocol=ospf6");
$tab_array[] = array(gettext("Configuration"), ($_REQUEST['protocol'] == "config"), "/status_frr.php?protocol=config");
$tab_array[] = array(gettext("[Global]"), false, "/pkg_edit.php?xml=frr.xml");
$tab_array[] = array(gettext("[BGP Settings]"), false, "pkg_edit.php?xml=frr/frr_bgp.xml");
$tab_array[] = array(gettext("[OSPF Settings]"), false, "/pkg_edit.php?xml=frr/frr_ospf.xml");
$tab_array[] = array(gettext("[OSPF6 Settings]"), false, "/pkg_edit.php?xml=frr/frr_ospf6.xml");

include("head.inc");
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=sprintf(gettext("Detailed %s Status"), htmlspecialchars($title_label));?></h2>
	</div>
	<div class="panel-body">
		<div id="cmdspace" style="width:95%">
			<?php
			if (empty($message)) {
				listCmds();
			} else {
				print $message;
			}
			?>
		</div>
		<div class="table-responsive">
			<?php execCmds(); ?>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[

function update_count(cmd, header_size) {
	var url = "status_frr.php";
	var params = "isAjax=true&protocol=<?= htmlspecialchars($_REQUEST['protocol']) ?>&count=true&cmd=" + cmd + "&header_size=" + header_size;
	jQuery.ajax(url,
		{
		type: 'post',
		data: params,
		success: update_count_callback
		}
		);
}

function update_count_callback(html) {
	// First line contain field id to be updated
	var responseTextArr = html.split("\n");

	$('#' + responseTextArr[0] + "_count").html(responseTextArr[1]);
}

function update_filter(cmd, header_size) {
	var url = "status_frr.php";
	var filter = "";
	var limit = "all";
	var limit_field = $('#' + cmd + "_limit");
	if (limit_field) {
		limit = limit_field.val();
		filter = $('#' + cmd + "_filter").val();
	}
	var params = "isAjax=true&protocol=<?= htmlspecialchars($_REQUEST['protocol']) ?>&cmd=" + cmd + "&limit=" + limit + "&filter=" + filter + "&header_size=" + header_size;
	jQuery.ajax(url,
		{
		type: 'post',
		data: params,
		success: update_filter_callback
		}
		);
}

function update_filter_callback(html) {
	// First line contain field id to be updated
	var responseTextArr = html.split("\n");
	var id = responseTextArr.shift();
	$('#' + id).html(responseTextArr.join("\n"));
}

function exec_all_cmds() {
<?php
		foreach ($commands as $idx => $command) {
			if ($command['has_filter']) {
				echo "\t\tupdate_count('{$idx}', {$command['header_size']});\n";
			}
			echo "\t\tupdate_filter('{$idx}', {$command['header_size']});\n";
		}
?>
	}

events.push(function(){
	jQuery(document).ready(function(){setTimeout('exec_all_cmds()', 5000);});
});
//]]>
</script>

<?php include("foot.inc");
