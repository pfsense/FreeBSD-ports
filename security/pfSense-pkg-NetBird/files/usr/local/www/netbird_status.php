<?php
/*
 * netbird_status.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2022-2025 Rubicon Communications, LLC (Netgate)
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

require_once('guiconfig.inc');
require_once('util.inc');
require_once('netbird/netbird_status.inc');

$tabs = [
    [gettext('Authentication'), false, 'pkg_edit.php?xml=netbird/netbird_auth.xml'],
    [gettext('Settings'), false, 'pkg_edit.php?xml=netbird.xml'],
    [gettext('Status'), true, '/netbird_status.php'],
];
$pgtitle = [gettext('Status'), gettext('NetBird')];
$pglinks = ['', '@self'];
$field_map = ['name' => '%n', 'version' => '%v', 'comment' => '%c'];
$packages = ['pfSense-pkg-netBird', 'netbird'];

include('head.inc');

netbird_display_connection_info();

display_top_tabs($tabs);

if (netbird_is_running()):
?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h2 class="panel-title"><?= gettext("NetBird Status") ?>
            </h2>
        </div>
        <div class="table-responsive">
            <?php netbird_display_peer_connection_status(); ?>
            <?php
            if (netbird_is_connected()){
                netbird_display_peers_details_status();
            }
            ?>
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
                <?php foreach (netbird_get_pkg_info($field_map, $packages) as $package): ?>
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


