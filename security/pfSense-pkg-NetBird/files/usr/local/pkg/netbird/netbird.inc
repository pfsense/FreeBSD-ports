<?php
/*
 * netbird.xml
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

require_once('service-utils.inc');
require_once("config.inc");
require_once("util.inc");

define('NETBIRD_BIN', '/usr/local/bin/netbird');
define('NETBIRD_CONFIG', '/var/db/netbird/config.json');
define('PKG_BIN', '/usr/sbin/pkg');


function netbird_resync_config()
{
    $json = file_get_contents(NETBIRD_CONFIG);
    $config = json_decode($json, true);

    if (!is_array($config)) {
        log_error("Invalid netbird configuration");
        return;
    }

    if (!empty($_POST['wireguardport'])) {
        $config['WgPort'] = (int)$_POST['wireguardport'];
    }

    $config_map = [
        'enablessh' => ['ServerSSHAllowed', true],
        'blockinboundconn' => ['BlockInbound', true],
        'allowfirewallconfig' => ['DisableFirewall', false],
        'enabledns' => ['DisableDNS', false],
        'accesslan' => ['BlockLANAccess', false],
        'allowclientroutes' => ['DisableClientRoutes', false],
        'allowserverroutes' => ['DisableServerRoutes', false],
        'enablerosenpass' => ['RosenpassEnabled', true],
        'rosenpasspermissive' => ['RosenpassPermissive', true],
    ];

    foreach ($config_map as $post_key => [$json_key, $enabled_val]) {
        $checked = ($_POST[$post_key] ?? '') === 'on';
        $config[$json_key] = ($checked === $enabled_val);
    }

    file_put_contents(NETBIRD_CONFIG, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


    if (netbird_is_connected()) {
        if (!netbird_disconnect()) {
            return;
        }

        $cmd = implode(' ', [NETBIRD_BIN, 'up']);
        exec($cmd);
    }
}


function netbird_validate_input($post, &$input_errors)
{
    $wireguard_port = $post['wireguard_port'];
    if (!empty($wireguard_port) && (!ctype_digit($wireguard_port) || !is_port($wireguard_port))){
        $input_errors[] = sprintf(gettext('WireGuard port (%s) is not a valid port.'), $wireguard_port);
    }
}

function netbird_is_connected(): bool
{
    $status = netbird_get_status();
    return !empty($status['management']['connected']);
}

function netbird_is_running(): bool
{
    return is_service_running('netbird');
}

function netbird_disconnect(): bool
{
    $cmd = implode(' ', [NETBIRD_BIN, 'down']);
    exec($cmd, $out, $result_code);

    return $result_code === 0 && !empty($out);
}

function netbird_get_status()
{
    $cmd = implode(' ', [NETBIRD_BIN, 'status', '--json']);
    exec($cmd, $out, $rc);

    if ($rc !==0 || empty($out)) {
        return false;
    }

    $status = json_decode(implode('', $out), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($status)) {
        return false;
    }
    return $status;
}

function netbird_display_connection_info(): void
{
    if (!netbird_is_running()) {
        $message = gettext('NetBird is not running.');
        $type = 'danger';
        $closable = false;
    } elseif (!netbird_is_connected()) {
        $message = gettext('NetBird is not connected.');
        $type = 'warning';
        $closable = false;
    } else {
        $message = gettext('NetBird is connected.');
        $type = 'info';
        $closable = true;
    }

    print_info_box($message, $type, $closable ? 'close' : false);
}

function netbird_write_rcfile() {
    $rc['file'] = 'netbird.sh';
    $rc['start'] .= "/usr/local/bin/netbird service start\n\t";
    $rc['stop'] .= "/usr/local/bin/netbird service stop\n\t";
    $rc['restart'] .= "/usr/local/bin/netbird service restart\n\t";
    write_rcfile($rc);
}

function netbird_install()
{
    netbird_write_rcfile();

    if (!netbird_is_running()){
        $cmd = implode(' ', [NETBIRD_BIN, 'service', 'start']);
        exec($cmd);
    }
}

function netbird_deinstall()
{
    global $config;

    if (netbird_is_running()) {
        stop_service("netbird");
    }

    unlink_if_exists(NETBIRD_CONFIG);

    if (isset($config['installedpackages']['netbird'])) {
        unset($config['installedpackages']['netbird']);
        write_config("Removed netbird configuration");
    }
}
?>