<?php
/*
 * status_wireguard.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 R. Christian McDonald
 * Copyright (c) 2020 Ascrod
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
##|*IDENT=page-vpn-wg-status
##|*NAME=Status: WireGuard
##|*DESCR=Allow access to the 'Status: WireGuard' page.
##|*MATCH=status_wireguard.php*
##|-PRIV

// pfSense includes
require_once('guiconfig.inc');
require_once('functions.inc');
require_once('shortcuts.inc');

// WireGuard includes
require_once('wireguard/wg.inc');

$shortcut_section = "wireguard";
$pgtitle = array(gettext("Status"), "WireGuard");

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Tunnels"), false, "vpn_wg.php");
$tab_array[] = array(gettext("Settings"), false, "vpn_wg_settings.php");
$tab_array[] = array(gettext("Status"), true, "status_wireguard.php");

add_package_tabs("wireguard", $tab_array);
display_top_tabs($tab_array);

?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">Connection Status</h2>
	</div>
	<div class="panel-body">
		<dl class="dl-horizontal">
			<pre><?=wg_status(); ?></pre>
		</dl>
    </div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">Interface Status</h2>
	</div>
	<div class="panel-body">
		<dl class="dl-horizontal">
			<pre><?=wg_interface_status(); ?></pre>
		</dl>
    </div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">Package Versions</h2>
	</div>
	<div class="table-responsive panel-body">
		<table class="table table-hover table-striped table-condensed">
			<thead>
				<tr>
					<th>Name</th>
					<th>Version</th>
    					<th>Comment</th>
				</tr>
			</thead>
			<tbody>
<?php

			$a_packages = wg_pkg_info();

			foreach ($a_packages as $package):

?>
    				<tr>
        				<td><?=$package[0]?></td>
    					<td><?=$package[1]?></td>
					<td><?=$package[2]?></td>

				</tr>
<?php
			endforeach;
?>

			</tbody>
		</table>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">Kernel Module Status</h2>
	</div>
	<div class="panel-body">
		<dl class="dl-horizontal">
			<pre><?=wg_kmod_status(); ?></pre>
		</dl>
    </div>
</div>

<?php include("foot.inc"); ?>
