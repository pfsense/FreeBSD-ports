<?php
/*
 * siproxd_registered_phones.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2010-2024 Rubicon Communications, LLC (Netgate)
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

##|+PRIV
##|*IDENT=page-status-siproxd
##|*NAME=Status: siproxd registered phones
##|*DESCR=Allow access to the 'Status: siproxd registered phones' page.
##|*MATCH=siproxd_registered_phones.php*
##|-PRIV

require_once("guiconfig.inc");

if (file_exists("/var/siproxd/siproxd_registrations")) {
	$phonetext = file_get_contents("/var/siproxd/siproxd_registrations");
	$phonedata = explode("\n", $phonetext);
}

if (!is_array($phonedata)) {
	$phonedata = array();
}

$activephones = array();
for ($i = 0; $i < count($phonedata); $i++) {
	list($stars, $active, $expires) = explode(":", $phonedata[$i]);
	if ($active == "1") {
		$phone = array();
		$phone["expires"] = $expires;

		list($type, $user_host, $port_tags) = explode (":", $phonedata[++$i]);
		list($user, $host) = explode("@", $user_host);
		list($port, $tags) = explode(";", $port_tags);
		$phone["real"]["type"] = $type;
		$phone["real"]["user"] = $user;
		$phone["real"]["host"] = $host;
		$phone["real"]["port"] = $port;

		list($type, $user_host, $port_tags) = explode (":", $phonedata[++$i]);
		list($user, $host) = explode("@", $user_host);
		list($port, $tags) = explode(";", $port_tags);
		$phone["nat"]["type"] = $type;
		$phone["nat"]["user"] = $user;
		$phone["nat"]["host"] = $host;
		$phone["nat"]["port"] = $port;

		list($type, $user_host, $port_tags) = explode (":", $phonedata[++$i]);
		list($user, $host) = explode("@", $user_host);
		list($port, $tags) = explode(";", $port_tags);
		$phone["registered"]["type"] = $type;
		$phone["registered"]["user"] = $user;
		$phone["registered"]["host"] = $host;
		$phone["registered"]["port"] = $port;

		$activephones[] = $phone;
	}
}

$pgtitle = array(gettext("Package"), gettext("siproxd"), gettext("Registered Phones"));
require("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "pkg_edit.php?xml=siproxd.xml");
$tab_array[] = array(gettext("Users"), false, "pkg.php?xml=siproxdusers.xml");
$tab_array[] = array(gettext("Registered Phones"), true, "siproxd_registered_phones.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Currently Registered Phones") . " (" . count($activephones) . ")"; ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th colspan="5">Real Phone</th>
						<th colspan="5">NAT Address</th>
						<th colspan="4">Registered With</th>
						<th colspan="2">&nbsp;</th>
					</tr>
					<tr>
						<th>Type</th>
						<th>User</th>
						<th>Host</th>
						<th>Port</th>
						<th>&nbsp;</th>
						<th>Type</th>
						<th>User</th>
						<th>Host</th>
						<th>Port</th>
						<th>&nbsp;</th>
						<th>Type</th>
						<th>User</th>
						<th>Host</th>
						<th>Port</th>
						<th>&nbsp;</th>
						<th>Expires</th>
					</tr>
				</thead>
				<tbody>
					<?php if (count($phonedata) == 0): ?>
					<tr><td colspan="16" align="center">No Phone Data Found</td></tr>
					<? elseif (count($activephones) == 0): ?>
					<tr><td colspan="16" align="center">No Active Phones</td></tr>
					<? else: ?>
					<? foreach ($activephones as $phone): ?>
					<tr>
						<td align="center" class="listlr"><? echo ($phone['real']['type']) ? $phone['real']['type'] : "sip"; ?></td>
						<td align="center" class="listr"><? echo ($phone['real']['user']) ? $phone['real']['user'] : "&nbsp;"; ?></td>
						<td align="center" class="listr"><? echo ($phone['real']['host']) ? $phone['real']['host'] : "&nbsp;"; ?></td>
						<td align="center" class="listr"><? echo ($phone['real']['port']) ? $phone['real']['port'] : "5060"; ?></td>

						<td align="center" class="list">&nbsp;</td>
						<td align="center" class="listlr"><? echo ($phone['nat']['type']) ? $phone['nat']['type'] : "sip"; ?></td>
						<td align="center" class="listr"><? echo ($phone['nat']['user']) ? $phone['nat']['user'] : "&nbsp;"; ?></td>
						<td align="center" class="listr"><? echo ($phone['nat']['host']) ? $phone['nat']['host'] : "&nbsp;"; ?></td>
						<td align="center" class="listr"><? echo ($phone['nat']['port']) ? $phone['nat']['port'] : "5060"; ?></td>

						<td align="center" class="list">&nbsp;</td>
						<td align="center" class="listlr"><? echo ($phone['registered']['type']) ? $phone['registered']['type'] : "sip"; ?></td>
						<td align="center" class="listr"><? echo ($phone['registered']['user']) ? $phone['registered']['user'] : "&nbsp;"; ?></td>
						<td align="center" class="listr"><? echo ($phone['registered']['host']) ? $phone['registered']['host'] : "&nbsp;"; ?></td>
						<td align="center" class="listr"><? echo ($phone['registered']['port']) ? $phone['registered']['port'] : "5060"; ?></td>

						<td align="center" class="list">&nbsp;</td>
						<td align="center" class="listlr"><? echo date("m/d/Y h:i:sa", $phone['expires']); ?></td>
					</tr>
					<? endforeach; ?>
					<? endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php include("foot.inc"); ?>
