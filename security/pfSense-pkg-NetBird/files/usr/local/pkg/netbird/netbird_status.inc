<?php
/*
 * netbird_status.inc
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

require_once('vendor/autoload.php');

use Nette\Utils\Html;

require_once('netbird/netbird.inc');

function netbird_get_pkg_info(array $field_map, array $packages)
{
    $delimiter = "\t";
    $format = implode($delimiter, $field_map);
    $package_list = implode(' ', $packages);

    $cmd = implode(' ', [PKG_BIN, 'query', escapeshellarg($format), $package_list]);
    exec($cmd, $out, $result_code);

    if ($result_code !== 0 || empty($out)) {
        return false;
    }

    $return_keys = array_values(array_flip($field_map));
    foreach ($out as $row_idx => $row_string){
        foreach (explode($delimiter, $row_string) as $field_idx => $field_string){
            $result[$row_idx][$return_keys[$field_idx]] = $field_string;
        }
    }

    return $result;
}

function netbird_display_peer_connection_status()
{
    $summary = netbird_get_peer_connection_status();
    echo netbird_render_status_html_table('Connection Status', $summary);
}

function netbird_display_peers_details_status(): void
{
    $status = netbird_get_status();
    $peers = $status['peers'] ?? [];

    $summary = netbird_get_peers_detail(
        $peers,
        $status['quantumResistance'] ?? false,
        $status['quantumResistancePermissive'] ?? false
    );
    echo netbird_render_status_html_table('Peers Detail', $summary);
}

function netbird_render_status_html_table(string $name, string $summary): string
{
    $table = Html::el('table')
        ->class('table table-hover table-condensed')->addHtml(
            Html::el('tbody')->addHtml(
                Html::el('tr')->addHtml(
                    Html::el('td')->addHtml(
                        Html::el('h5')->addHtml(
                            Html::el('a')->setText($name)
                        )
                    )
                )
            )->addHtml(Html::el('tr')->addHtml(
                Html::el('td')->addHtml(
                    Html::el('pre')->addAttributes([
                        'style' => 'max-height: 500px; overflow-y: auto;'
                    ])->setText($summary)
                )
            ))
        );

    return $table->toHtml();
}

function netbird_get_peer_connection_status(): string
{
    $status = netbird_get_status();
    if (!$status) {
        return 'No status available.';
    }

    // Management
    $management = $status['management'] ?? [];
    if (!empty($management['connected'])) {
        $management_str = "Connected";
        if (!empty($management['url'])) {
            $management_str .= " to {$management['url']}";
        }
    } else {
        $management_str = "Disconnected";
        if (!empty($management['error'])) {
            $management_str .= ", reason: {$management['error']}";
        }
    }

    // Signal
    $signal = $status['signal'] ?? [];
    if (!empty($signal['connected'])) {
        $signal_str = "Connected";
        if (!empty($signal['url'])) {
            $signal_str .= " to {$signal['url']}";
        }
    } else {
        $signal_str = "Disconnected";
        if (!empty($signal['error'])) {
            $signal_str .= ", reason: {$signal['error']}";
        }
    }


    // Interface
    $interface_type = !empty($status['kernelInterface']) ? "Kernel" : "Userspace";
    $interface_ip = $status['netbirdIp'] ?? 'N/A';
    if (empty($interface_ip)) {
        $interface_type = "N/A";
        $interface_ip = "N/A";
    }

    // Relays
    $relays_str = '';
    if (!empty($status['relays']['details'])) {
        foreach ($status['relays']['details'] as $relay) {
            $available = !empty($relay['available']) ? "Available" : "Unavailable";
            $reason = !empty($relay['error']) ? ", reason: {$relay['error']}" : "";
            $relays_str .= "\n  [{$relay['uri']}] is {$available}{$reason}";
        }
    } else {
        $relays_str = sprintf("%d/%d Available", $status['relays']['available'] ?? 0, $status['relays']['total'] ?? 0);
    }

    // DNS/Nameservers
    $dnsStr = '';
    if (!empty($status['dnsServers'])) {
        foreach ($status['dnsServers'] as $group) {
            $enabled = !empty($group['enabled']) ? "Available" : "Unavailable";
            $reason = !empty($group['error']) ? ", reason: {$group['error']}" : "";
            $domains = !empty($group['domains']) ? implode(", ", $group['domains']) : ".";
            $servers = implode(", ", $group['servers']);
            $dnsStr .= "\n  [{$servers}] for [{$domains}] is {$enabled}{$reason}";
        }
    } else {
        $enabled_dns_count = 0;
        foreach ($status['dnsServers'] ?? [] as $group) {
            if (!empty($group['enabled'])) {
                $enabled_dns_count++;
            }
        }
        $dnsStr = sprintf("%d/%d Available", $enabled_dns_count, count($status['dnsServers'] ?? []));
    }

    // Quantum resistance
    $quantum = "false";
    if (!empty($status['rosenpassEnabled'])) {
        $quantum = "true" . (!empty($status['rosenpassPermissive']) ? " (permissive)" : "");
    }

    // Lazy connection
    $lazy = !empty($status['lazyConnectionEnabled']) ? "true" : "false";

    // Networks
    $networks = !empty($status['networks']) ? implode(", ", $status['networks']) : "-";

    // Peers
    $peers_count = sprintf("%d/%d Connected", $status['peers']['connected'] ?? 0, $status['peers']['total'] ?? 0);


    $info_lines = [
        'Daemon version'       => $status['daemonVersion'],
        'CLI version'          => $status['cliVersion'],
        'Management'           => $management_str,
        'Signal'               => $signal_str,
        'Relays'               => $relays_str,
        'Nameservers'          => $dnsStr,
        'FQDN'                 => $status['fqdn'],
        'NetBird IP'           => $interface_ip,
        'Interface type'       => $interface_type,
        'Quantum resistance'   => $quantum,
        'Lazy connection'      => $lazy,
        'Networks'             => $networks,
        'Forwarding rules'     => $status['forwardingRules'],
        'Peers count'          => $peers_count,
    ];

    $lines = [];
    foreach ($info_lines as $key => $value) {
        $lines[] = "{$key}: {$value}";
    }

   return implode("\n", $lines);
}

function netbird_get_peers_detail(array $peers, bool $rosenpass_enabled, bool $rosenpass_permissive): string {
    $peers_str = '';

    $details = !empty($peers['details']) ? $peers['details'] : [];
    foreach ($details as $peer_state) {
        $local_ice = !empty($peer_state['iceCandidateType']['local']) ? $peer_state['iceCandidateType']['local'] : '-';
        $remote_ice = !empty($peer_state['iceCandidateType']['remote']) ? $peer_state['iceCandidateType']['remote'] : '-';

        $local_ice_endpoint = !empty($peer_state['iceCandidateType']['local']) ? $peer_state['iceCandidateType']['local'] : '-';
        $remote_ice_endpoint = !empty($peer_state['iceCandidateType']['remote']) ? $peer_state['iceCandidateType']['remote'] : '-';

        $rosenpass_enabled_status = 'false';
        if ($rosenpass_enabled) {
            if (!empty($peer_state['quantumResistance'])) {
                $rosenpass_enabled_status = 'true';
            } else {
                if ($rosenpass_permissive) {
                    $rosenpass_enabled_status = "false (remote didn't enable quantum resistance)";
                } else {
                    $rosenpass_enabled_status = "false (connection won't work without a permissive mode)";
                }
            }
        } else {
            if (!empty($peer_state['quantumResistance'])) {
                $rosenpass_enabled_status = "false (connection might not work without a remote permissive mode)";
            }
        }

        $networks = '-';
        if (!empty($peer_state['networks']) && is_array($peer_state['networks'])) {
            sort($peer_state['networks']);
            $networks = implode(', ', $peer_state['networks']);
        }

        $lastStatus_update = (!empty($peer_state['lastStatus_update'])) ? new DateTimeImmutable($peer_state['lastStatus_update']) : new DateTimeImmutable('@0');
        $last_wireguard_handshake = (!empty($peer_state['lastStatus_update'])) ? new DateTimeImmutable($peer_state['lastStatus_update']) : new DateTimeImmutable('@0');

        $latency = isset($peer_state['latency']) ? floatval($peer_state['latency']) / 1_000_000 . ' ms' : '-';

        $peer_str = sprintf(
            "\n %s:\n".
            "  NetBird IP: %s\n".
            "  Public key: %s\n".
            "  Status: %s\n".
            "  -- detail --\n".
            "  Connection type: %s\n".
            "  ICE candidate (Local/Remote): %s/%s\n".
            "  ICE candidate endpoints (Local/Remote): %s/%s\n".
            "  Relay server address: %s\n".
            "  Last connection update: %s\n".
            "  Last WireGuard handshake: %s\n".
            "  Transfer status (received/sent) %s/%s\n".
            "  Quantum resistance: %s\n".
            "  Networks: %s\n".
            "  Latency: %s\n",
            // FQDN safe string fallback
            $peer_state['fqdn'],
            $peer_state['netbirdIp'],
            $peer_state['publicKey'] ,
            $peer_state['status'],
            $peer_state['connectionType'] ?? '-',
            $local_ice,
            $remote_ice,
            $local_ice_endpoint,
            $remote_ice_endpoint,
            $peer_state['relayAddress'] ?? '-',
            netbird_get_elapsed_time($lastStatus_update),
            netbird_get_elapsed_time($last_wireguard_handshake),
            netbird_format_bytes((int)($peer_state['transferReceived'] ?? 0)),
            netbird_format_bytes((int)($peer_state['transferSent'] ?? 0)),
            $rosenpass_enabled_status,
            $networks,
            $latency
        );

        $peers_str .= $peer_str;
    }

    return $peers_str;
}

function netbird_get_elapsed_time(DateTimeInterface $dt): string {
    if ($dt->getTimestamp() === 0) {
        return '-';
    }

    $units = ['y' => 'year', 'm' => 'month', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
    $diff = (new DateTimeImmutable())->diff($dt);

    foreach ($units as $key => $label) {
        $value = $diff->$key;
        if ($value > 0) {
            return "$value $label" . ($value > 1 ? 's' : '') . " ago";
        }
    }

    return 'Now';
}

function netbird_format_bytes(int $bytes): string {
    $unit = 1024;

    if ($bytes < $unit) {
        return $bytes . ' B';
    }

    $units = ['Ki', 'Mi', 'Gi', 'Ti', 'Pi', 'Ei'];
    $exp = (int) (log($bytes) / log($unit));
    $prefix = $units[$exp - 1];
    $value = $bytes / ($unit ** $exp);

    return sprintf('%.1f %sB', $value, $prefix);
}

?>