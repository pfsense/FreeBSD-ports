<?php
/*
 * arpwatch_database.php
 *
 * Copyright (c) 2018 Julien Le Goff <julego@gmail.com>
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

require_once("guiconfig.inc");
require_once("arpwatch.inc");

$entries = arpwatch_get_database_entries();

$pgtitle = array(gettext("Package"), gettext("Arpwatch"), gettext("Database"));

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "/pkg_edit.php?xml=arpwatch.xml");
$tab_array[] = array(gettext("Database"), true, "/arpwatch_database.php");

add_package_tabs("Arpwatch", $tab_array);
display_top_tabs($tab_array);

?>
<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title"><?=gettext('Database')?></h2></div>
    <div class="panel-body table-responsive">
        <table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
            <thead>
            <tr>
                <th><?=gettext("Interface")?></th>
                <th><?=gettext("IP address")?></th>
                <th><?=gettext("MAC address")?></th>
                <th><?=gettext("Vendor")?></th>
                <th><?=gettext("Hostname")?></th>
                <th><?=gettext("Timestamp")?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($entries)) : ?>
            <?php foreach ($entries as $entry): ?>
            <tr>
                <td><?=htmlspecialchars($entry['ifdescr'])?></td>
                <td><?=htmlspecialchars($entry['ip'])?></td>
                <td><?=htmlspecialchars($entry['mac'])?></td>
                <td><?=htmlspecialchars($entry['vendor'])?></td>
                <td><?=htmlspecialchars($entry['hostname'])?></td>
                <td><?=htmlspecialchars($entry['timestamp'])?></td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
                <td colspan="6"><?=gettext("No entries to display")?></td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
</div>

<?php include("foot.inc"); ?>
