<?php
/*
* honeytrap_logs.php
*
* part of pfSense (http://www.pfsense.org)
* Copyright (c) 2019 DutchSec (https://dutchsec.com/)
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
require_once('globals.inc');

require_once('honeytrap/honeytrap-plugin.inc');

global $config, $g;

$gconfig = &$config['installedpackages']['honeytrap']['config'][0];

$config_file =  $gconfig['config_file'];
$path_parts = pathinfo($config_file);

$logfile = "{$g['varlog_path']}/honeytrap/{$path_parts['filename']}.log";

$pgtitle = array(gettext("Service"), gettext("HoneyTrap"), gettext('Logs'));
$pglinks = array('', '/honeytrap/honeytrap_settings.php', '@self');
$shortcut_section = 'honeytrap';
include_once('head.inc');

if (!realpath($logfile)) {
    print_info_box('Logfile doesn\'t seem to exist');
} else {
    $logfile_contents = file_get_contents($logfile);

    ?>
<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">HoneyTrap service log</h2></div>
    <div class="panel-body">
        <pre>
<?php
    echo htmlspecialchars($logfile_contents, ENT_SUBSTITUTE) . PHP_EOL;

    ?>
        </pre>
    </div>
</div>
<?php

}

include_once('foot.inc');
?>
