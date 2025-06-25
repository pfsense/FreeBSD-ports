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

define('NETBIRD_BIN', '/usr/local/bin/netbird');
define('PKG_BIN', '/usr/sbin/pkg');


function netbird_resync_config()
{
    if (netbird_is_connected()) {
        if (!netbird_disconnect()) {
            return;
        }
    }


    $cmd = [NETBIRD_BIN, 'up'];

    if (!empty($_POST['wireguardport'])) {
        $cmd[] = '--wireguard-port=' . escapeshellarg((int)$_POST['wireguardport']);
    }
    if (!empty($_POST['loglevel'])) {
        $cmd[] = '--log-level=' . escapeshellarg($_POST['loglevel']);
    }

    $options = [
        'enablessh'            => ['--allow-server-ssh', true],
        'blockinboundconn'     => ['--block-inbound', true],
        'allowfirewallconfig'    => ['--disable-firewall', false],
        'enabledns'            => ['--disable-dns', false],
        'accesslan'            => ['--block-lan-access', false],
        'allowclientroutes'    => ['--disable-client-routes', false],
        'allowserverroutes'    => ['--disable-server-routes', false],
        'enablerosenpass'      => ['--enable-rosenpass', true],
        'rosenpasspermissive'  => ['--rosenpass-permissive', true],
    ];

    foreach ($options as $key => [$flag, $enabled_value]) {
        $is_checked = ($_POST[$key] ?? '') === 'on';
        $cmd[] = $flag . '=' . ($is_checked === $enabled_value ? 'true' : 'false');
    }

    exec(implode(' ', $cmd));
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
        $message = gettext('NetBird is not connected. Refresh or check the NetBird status page.');
        $type = 'warning';
        $closable = false;
    } else {
        $message = gettext('NetBird is connected.');
        $type = 'info';
        $closable = true;
    }

    print_info_box($message, $type, $closable ? 'close' : false);
}
?>