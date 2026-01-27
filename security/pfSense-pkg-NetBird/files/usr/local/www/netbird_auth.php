<?php
/*
 * netbird_auth.php
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
require_once("classes/autoload.inc.php");
require_once('netbird/netbird.inc');

$shortcut_section = 'netbird';

$auth_config = &$config['installedpackages']['netbird']['auth'];
if (!is_array($auth_config)) {
    $auth_config = [];
}

if ($_POST) {
    if (isset($_POST['connect'])) {
        unset($input_errors);

        $management_url = $_POST['managementurl'];
        $setup_key = $_POST['setupkey'];

        if (!empty($management_url) && !is_URL($management_url)) {
            $input_errors[] = sprintf(gettext('Management URL (%s) is not a valid URL.'), $management_url);
        }
        if (empty($setup_key)) {
            $input_errors[] = gettext('Setup Key is required.');
        }


        if (empty($input_errors)) {
            if ($setup_key !== $auth_config['setupkey']) {
                $auth_config['setupkey'] = $setup_key;
            }
            $auth_config['managementurl'] = $management_url;

            if (netbird_is_connected() && !netbird_disconnect()) {
                return;
            }

            $cmd = sprintf(
                '%s up -m %s -k %s',
                NETBIRD_BIN,
                escapeshellarg($management_url),
                escapeshellarg($setup_key)
            );
            exec($cmd, $out, $return_code);

            $joined_output = implode("\n", $out);
            if ($return_code !== 0 || stripos($joined_output, 'connected') === false) {
                $input_errors[] = "Failed to connect to the management service.";
                if (!empty($joined_output)) {
                    $input_errors[] = "Output: " . htmlspecialchars($joined_output);
                }
            }

            write_config("NetBird connected");
            header("Location: netbird_auth.php");
            exit;
        }

    } elseif (isset($_POST['disconnect'])) {
        netbird_disconnect();
        header("Location: netbird_auth.php");
        exit;
    }
}


$tabs = [
    [gettext('Authentication'), true, '/netbird_auth.php'],
    [gettext('Settings'), false, 'pkg_edit.php?xml=netbird.xml'],
    [gettext('Status'), false, '/netbird_status.php'],
];
$pgtitle = [gettext('VPN'), gettext('NetBird'), gettext('Authentication')];
$pglinks = ['', '@self'];

include('head.inc');

if ($input_errors) {
    print_input_errors($input_errors);
}

netbird_display_connection_info();
display_top_tabs($tabs);

$management_url = $auth_config['managementurl'] ?? 'https://api.netbird.io:443';
$setup_key = $auth_config['setupkey'] ?? '';

$masked_key = '';
if (!empty($setup_key)) {
    $visible_part = substr($setup_key, 0, 4);
    $masked_key = $visible_part . str_repeat('*', max(4, strlen($setup_key) - 4));
}


$form = new Form(false);
$section = new Form_Section('Authentication');

$section->addInput(new Form_Input(
    'managementurl',
    'Management URL',
    'text',
    $management_url
))->setHelp('Base URL of the management service');

$section->addInput(new Form_Input(
    'setupkey',
    'Setup Key',
    'text',
    $masked_key
))->setHelp('Set the authentication setup key');

if (netbird_is_connected()) {
    $button = new Form_Button(
        'disconnect',
        'Disconnect',
        null,
        'fa-solid fa-right-from-bracket'
    );
    $button->setAttribute('type', 'submit')->addClass('btn-danger');

    $section->addInput(new Form_StaticText(
        null,
        $button
    ))->setHelp('Disconnect from the management service');
} else {
    $button = new Form_Button(
        'connect',
        'Connect',
        null,
        'fa-solid fa-right-to-bracket'
    );
    $button->setAttribute('type', 'submit')->addClass('btn-primary');

    $section->addInput(new Form_StaticText(
        null,
        $button
    ))->setHelp('Connect to the management service');
}

$form->add($section);
print $form;

include('foot.inc');


