<?php
/*
 * status_tailscale.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2022-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-status-tailscale
##|*NAME=Status: Tailscale
##|*DESCR=Allow access to the 'Status: Tailscale' page.
##|*MATCH=status_tailscale.php*
##|-PRIV

require_once('guiconfig.inc');
require_once('util.inc');

require_once('tailscale/tailscale_common.inc');

/*
 * Here we setup the commands we want to display as status sections
 * Similar to the FRR package, but does not use global state...
 */
$cmds = [];
if ($is_enabled_and_running = (tailscale_is_enabled() && tailscale_is_running())) {
	tailscale_status_define_cmd($cmds, 'tailscale_status', gettext('Tailscale Status'), '/usr/local/bin/tailscale status');
	tailscale_status_define_cmd($cmds, 'tailscale_ip', gettext('Tailscale IP'), '/usr/local/bin/tailscale ip');
	tailscale_status_define_cmd($cmds, 'tailscale_interface', gettext('Tailscale Interface'), '/sbin/ifconfig tailscale0');
	tailscale_status_define_cmd($cmds, 'tailscale_netcheck', gettext('Tailscale Netcheck'), '/usr/local/bin/tailscale netcheck');
}

# here we define what packages to query for the package version section
$pkg_field_map = ['name' => '%n', 'version' => '%v', 'comment' => '%c'];
$pkg_packages = ['pfSense-pkg-Tailscale', 'tailscale'];

$shortcut_section = "tailscale";

$pgtitle = [gettext('Status'), gettext('Tailscale')];
$pglinks = ['', '@self'];

$tab_array = [];
$tab_array[] = [gettext('Authentication'), false, 'pkg_edit.php?xml=tailscale/tailscale_auth.xml'];
$tab_array[] = [gettext('Settings'), false, 'pkg_edit.php?xml=tailscale.xml'];
$tab_array[] = [gettext('Status'), true, '/status_tailscale.php'];

include('head.inc');

# bring in the service status notices above the top tabs
tailscale_common_after_head_hook();

display_top_tabs($tab_array);

# we only care about this status if tailscale is enabled and running
if ($is_enabled_and_running):
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('Tailscale Status')?></h2>
	</div>
	<div class="panel-body">
		<div id="cmdspace">
			<?php tailscale_status_print_cmd_list($cmds); ?>
		</div>
		<div class="table-responsive">
			<?php tailscale_status_print_cmds($cmds); ?>
		</div>
	</div>
</div>
<?php endif; ?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('Package Versions')?></h2>
	</div>
	<div class="table-responsive panel-body">
		<table class="table table-hover table-striped table-condensed">
			<thead>
				<tr>
					<th><?=gettext('Name')?></th>
					<th><?=gettext('Version')?></th>
					<th><?=gettext('Comment')?></th>
				</tr>
			</thead>
			<tbody>
<?php foreach (tailscale_get_pkg_info($pkg_field_map, $pkg_packages) as $package): ?>
				<tr>
					<td><?=htmlspecialchars($package['name'])?></td>
					<td><?=htmlspecialchars($package['version'])?></td>
					<td><?=htmlspecialchars($package['comment'])?></td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<?php
include('foot.inc');
