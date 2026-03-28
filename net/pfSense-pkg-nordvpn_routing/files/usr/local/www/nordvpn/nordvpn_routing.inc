<?php
/**
 * NordVPN Selective Routing - Core Library v2.0.2
 * /usr/local/www/nordvpn/nordvpn_routing.inc
 *
 * Multi-tunnel architecture. Each tunnel = one OpenVPN client +
 * one pfSense interface + one gateway (NORDVPN_GW_<vpnid>).
 * Rules reference tunnels by ID. Multiple tunnels run simultaneously.
 *
 * Config storage:
 *   Tunnel/rule metadata: config.xml installedpackages/nordvpn_routing
 *   Credentials:          /usr/local/etc/nordvpn_credentials.json
 *   Server cache:         /usr/local/etc/nordvpn_server_cache.json
 *   (Nested arrays are stored as JSON files to avoid XML parser corruption.)
 */

require_once('/etc/inc/config.lib.inc');

if (!function_exists('config_get_path')) {
    function config_get_path($path, $default = null) {
        global $config;
        $parts = explode('/', trim($path, '/'));
        $node  = $config;
        foreach ($parts as $part) {
            if (!isset($node[$part])) return $default;
            $node = $node[$part];
        }
        return $node;
    }
    function config_set_path($path, $value) {
        global $config;
        $parts = explode('/', trim($path, '/'));
        $node  = &$config;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) { $node[$part] = $value; break; }
            if (!isset($node[$part])) $node[$part] = [];
            $node = &$node[$part];
        }
    }
}

define('NVPN_CFG',         'installedpackages/nordvpn_routing');
define('NVPN_CREDS_FILE',  '/usr/local/etc/nordvpn_credentials.json');
define('NVPN_CACHE_FILE',  '/usr/local/etc/nordvpn_server_cache.json');

// ── Config helpers ────────────────────────────────────────────────────────────

function nvpn_get_config() {
    return config_get_path(NVPN_CFG, [
        'tunnels'    => [],
        'rules'      => [],
        'killswitch' => ['enabled' => '0', 'dns_ip' => '103.86.96.100'],
        'dns'        => ['mode' => 'off', 'custom_ip' => ''],
    ]);
}

function nvpn_save_config($cfg) {
    config_set_path(NVPN_CFG, $cfg);
    write_config("NordVPN Routing: configuration saved");
}

function nvpn_get_credentials(): array {
    if (!file_exists(NVPN_CREDS_FILE)) return ['user' => '', 'pass' => ''];
    $data = json_decode(file_get_contents(NVPN_CREDS_FILE), true);
    return is_array($data) ? $data : ['user' => '', 'pass' => ''];
}

function nvpn_save_credentials(string $user, string $pass): void {
    file_put_contents(NVPN_CREDS_FILE, json_encode(['user' => $user, 'pass' => $pass]));
    chmod(NVPN_CREDS_FILE, 0600);
}

function nvpn_get_server_cache(): array {
    if (!file_exists(NVPN_CACHE_FILE)) return [];
    $data = json_decode(file_get_contents(NVPN_CACHE_FILE), true);
    return is_array($data) ? $data : [];
}

function nvpn_save_server_cache(array $servers): void {
    file_put_contents(NVPN_CACHE_FILE, json_encode($servers));
}

// ── Server list helpers ───────────────────────────────────────────────────────

function nvpn_fetch_servers($country_code = '', $limit = 30) {
    $url = "https://api.nordvpn.com/v1/servers/recommendations?limit={$limit}&filters[servers_technologies][identifier]=openvpn_udp";
    if ($country_code) {
        $url .= "&filters[country_id]=" . nvpn_country_id($country_code);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'pfSense-NordVPN-pkg/2.0',
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return [];
    $data = json_decode($resp, true);
    if (!is_array($data)) return [];
    $out = [];
    foreach ($data as $s) {
        $out[] = [
            'name'     => $s['name']     ?? '',
            'hostname' => $s['hostname'] ?? '',
            'country'  => $s['locations'][0]['country']['name'] ?? '',
            'city'     => $s['locations'][0]['country']['city']['name'] ?? '',
            'load'     => $s['load']     ?? 0,
        ];
    }
    return $out;
}

function nvpn_country_id($code) {
    $map = [
        'US' => 228, 'GB' => 227, 'DE' => 81,  'FR' => 74,
        'CA' => 38,  'AU' => 13,  'NL' => 153, 'JP' => 108,
        'SE' => 208, 'CH' => 209, 'SG' => 195, 'NO' => 163,
        'DK' => 58,  'FI' => 73,  'IT' => 106, 'ES' => 202,
        'PL' => 174, 'RO' => 179, 'AT' => 14,  'BE' => 21,
        'BR' => 30,  'MX' => 140, 'IN' => 101, 'KR' => 114,
        'HK' => 97,  'NZ' => 156, 'ZA' => 200, 'UA' => 225,
    ];
    return $map[strtoupper($code)] ?? 228;
}

function nvpn_download_ovpn($hostname, $proto = 'udp') {
    $url = "https://downloads.nordcdn.com/configs/files/ovpn_{$proto}/servers/{$hostname}.{$proto}.ovpn";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'pfSense-NordVPN-pkg/2.0',
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $content = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && $content) ? $content : false;
}

// ── Tunnel management ─────────────────────────────────────────────────────────

function nvpn_create_tunnel($label, $hostname, $proto = 'udp') {
    $creds = nvpn_get_credentials();
    $user  = $creds['user'];
    $pass  = $creds['pass'];
    if (!$user || !$pass) return false;

    $ovpn = nvpn_download_ovpn($hostname, $proto);
    if (!$ovpn) return false;

    $parsed = nvpn_parse_ovpn($ovpn);
    if (!$parsed) return false;

    global $config;
    $vpnid      = nvpn_next_vpnid();
    $tun_id     = 'nvpn_' . $vpnid;
    $gw_name    = 'NORDVPN_GW_' . $vpnid;
    $label_safe = substr(preg_replace('/[^a-zA-Z0-9 _\-]/', '', $label), 0, 40);

    $client = [
        'vpnid'              => $vpnid,
        'auth_user'          => $user,
        'auth_pass'          => $pass,
        'mode'               => 'p2p',
        'protocol'           => strtoupper($proto) === 'UDP' ? 'UDP4' : 'TCP4',
        'dev_mode'           => 'tun',
        'interface'          => 'wan',
        'server_addr'        => $parsed['remote_host'],
        'server_port'        => $parsed['remote_port'],
        'description'        => "[nordvpn:{$tun_id}] {$label_safe}",
        'custom_options'     => nvpn_build_custom_opts($parsed),
        'caref'              => nvpn_import_ca($parsed['ca'] ?? '', $hostname),
        'crypto'             => $parsed['cipher']                ?? 'AES-256-CBC',
        'digest'             => $parsed['auth']                  ?? 'SHA512',
        'tunnel_network'     => '',
        'remote_network'     => '',
        'gwredir'            => '',
        'verbosity_level'    => '1',
        'ping_method'        => 'keepalive',
        'keepalive_interval' => '10',
        'keepalive_timeout'  => '60',
        'dns_add'            => '',
        'topology'           => 'subnet',
        'tls'                => $parsed['tls_auth']              ?? '',
        'tls_type'           => isset($parsed['tls_auth']) ? 'auth' : '',
        'tls_ver'            => $parsed['tls_version_min']       ?? '',
        'ncp_ciphers'        => $parsed['ncp_ciphers']           ?? '',
        'data_ciphers'       => $parsed['data_ciphers_fallback'] ?? '',
    ];

    if (!isset($config['openvpn'])) $config['openvpn'] = [];
    if (!isset($config['openvpn']['openvpn-client'])) $config['openvpn']['openvpn-client'] = [];
    $config['openvpn']['openvpn-client'][] = $client;

    // Interface description must not contain colons or spaces (becomes pf macro name)
    $ifdescr = 'NordVPN_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $label_safe);
    $ifname  = nvpn_ensure_interface("ovpnc{$vpnid}", $ifdescr);
    nvpn_create_gateway($ifname, $gw_name, "NordVPN {$label_safe}");

    write_config("NordVPN: created tunnel {$tun_id} -> {$hostname}");

    if (function_exists('openvpn_resync')) {
        openvpn_resync('client', $client);
    } else {
        mwexec("/etc/rc.d/openvpn restart " . escapeshellarg("client{$vpnid}"));
    }

    $tunnel = [
        'id'       => $tun_id,
        'label'    => $label_safe,
        'hostname' => $hostname,
        'proto'    => $proto,
        'vpnid'    => $vpnid,
        'ifname'   => $ifname,
        'gw_name'  => $gw_name,
        'created'  => time(),
    ];

    $cfg = nvpn_get_config();
    $cfg['tunnels'][$tun_id] = $tunnel;
    nvpn_save_config($cfg);
    return $tunnel;
}

function nvpn_switch_tunnel_server($tun_id, $new_hostname, $proto = null) {
    $cfg    = nvpn_get_config();
    $tunnel = $cfg['tunnels'][$tun_id] ?? null;
    if (!$tunnel) return false;

    $proto = $proto ?? $tunnel['proto'];
    $vpnid = $tunnel['vpnid'];

    $ovpn = nvpn_download_ovpn($new_hostname, $proto);
    if (!$ovpn) return false;
    $parsed = nvpn_parse_ovpn($ovpn);
    if (!$parsed) return false;

    global $config;
    foreach ($config['openvpn']['openvpn-client'] as &$c) {
        if ((int)$c['vpnid'] === (int)$vpnid) {
            $c['server_addr']    = $parsed['remote_host'];
            $c['server_port']    = $parsed['remote_port'];
            $c['protocol']       = strtoupper($proto) === 'UDP' ? 'UDP4' : 'TCP4';
            $c['caref']          = nvpn_import_ca($parsed['ca'] ?? '', $new_hostname);
            $c['crypto']         = $parsed['cipher']   ?? $c['crypto'];
            $c['digest']         = $parsed['auth']     ?? $c['digest'];
            $c['custom_options'] = nvpn_build_custom_opts($parsed);
            $c['tls']            = $parsed['tls_auth'] ?? '';
            $c['tls_type']       = isset($parsed['tls_auth']) ? 'auth' : '';
            $c['description']    = "[nordvpn:{$tun_id}] {$tunnel['label']} -> {$new_hostname}";
            if (function_exists('openvpn_resync')) {
                openvpn_resync('client', $c);
            } else {
                mwexec("/etc/rc.d/openvpn restart " . escapeshellarg("client{$vpnid}"));
            }
            break;
        }
    }

    $cfg['tunnels'][$tun_id]['hostname'] = $new_hostname;
    $cfg['tunnels'][$tun_id]['proto']    = $proto;
    nvpn_save_config($cfg);
    write_config("NordVPN: switched tunnel {$tun_id} to {$new_hostname}");
    return true;
}

function nvpn_remove_tunnel($tun_id) {
    $cfg    = nvpn_get_config();
    $tunnel = $cfg['tunnels'][$tun_id] ?? null;
    if (!$tunnel) return false;

    global $config;
    $vpnid = $tunnel['vpnid'];

    mwexec("/etc/rc.d/openvpn stop " . escapeshellarg("client{$vpnid}"));

    $config['openvpn']['openvpn-client'] = array_values(array_filter(
        $config['openvpn']['openvpn-client'] ?? [],
        fn($c) => (int)$c['vpnid'] !== (int)$vpnid
    ));
    $config['gateways']['gateway_item'] = array_values(array_filter(
        $config['gateways']['gateway_item'] ?? [],
        fn($g) => $g['name'] !== $tunnel['gw_name']
    ));
    foreach ($config['interfaces'] as $ifname => $iface) {
        if (($iface['if'] ?? '') === "ovpnc{$vpnid}") {
            unset($config['interfaces'][$ifname]);
            break;
        }
    }

    foreach ($cfg['rules'] as &$rule) {
        if (($rule['tunnel_id'] ?? '') === $tun_id) {
            $rule['tunnel_id'] = '';
            $rule['enabled']   = '0';
        }
    }
    unset($cfg['tunnels'][$tun_id]);
    nvpn_save_config($cfg);
    write_config("NordVPN: removed tunnel {$tun_id}");
    nvpn_apply_rules();
    return true;
}

// ── OpenVPN helpers ───────────────────────────────────────────────────────────

function nvpn_parse_ovpn($content) {
    $lines  = explode("\n", $content);
    $result = [];
    $in_ca  = $in_tls = false;
    $ca_buf = $tls_buf = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line || $line[0] === '#' || $line[0] === ';') continue;

        if ($line === '<ca>')        { $in_ca  = true;  continue; }
        if ($line === '</ca>')       { $in_ca  = false; $result['ca'] = $ca_buf; continue; }
        if ($line === '<tls-auth>')  { $in_tls = true;  continue; }
        if ($line === '</tls-auth>') { $in_tls = false; $result['tls_auth'] = $tls_buf; continue; }
        if ($in_ca)  { $ca_buf  .= $line . "\n"; continue; }
        if ($in_tls) { $tls_buf .= $line . "\n"; continue; }

        if      (preg_match('/^remote\s+(\S+)\s+(\d+)/',       $line, $m)) { $result['remote_host'] = $m[1]; $result['remote_port'] = $m[2]; }
        elseif  (preg_match('/^cipher\s+(\S+)/',                $line, $m)) { $result['cipher'] = $m[1]; }
        elseif  (preg_match('/^auth\s+(\S+)/',                  $line, $m)) { $result['auth']   = $m[1]; }
        elseif  (preg_match('/^tls-version-min\s+(\S+)/',       $line, $m)) { $result['tls_version_min'] = $m[1]; }
        elseif  (preg_match('/^data-ciphers-fallback\s+(\S+)/', $line, $m)) { $result['data_ciphers_fallback'] = $m[1]; }
        elseif  (preg_match('/^data-ciphers\s+(.+)/',           $line, $m)) { $result['ncp_ciphers'] = $m[1]; }
    }
    return isset($result['remote_host']) ? $result : false;
}

function nvpn_build_custom_opts($parsed) {
    $opts = ['persist-tun', 'persist-key'];
    if (!empty($parsed['tls_version_min'])) $opts[] = "tls-version-min {$parsed['tls_version_min']}";
    return implode(';', $opts);
}

function nvpn_import_ca($ca_pem, $hostname) {
    global $config;
    if (!isset($config['ca'])) $config['ca'] = [];
    $refid = 'nordvpn_ca';
    foreach ($config['ca'] as &$ca) {
        if ($ca['refid'] === $refid) {
            if ($ca_pem) $ca['crt'] = base64_encode($ca_pem);
            return $refid;
        }
    }
    $config['ca'][] = ['refid' => $refid, 'descr' => 'NordVPN CA', 'crt' => base64_encode($ca_pem), 'prv' => ''];
    return $refid;
}

function nvpn_next_vpnid() {
    global $config;
    $ids = [0];
    foreach (($config['openvpn']['openvpn-client'] ?? []) as $c) $ids[] = (int)$c['vpnid'];
    return max($ids) + 1;
}

function nvpn_ensure_interface($if_device, $descr) {
    global $config;
    if (!isset($config['interfaces'])) $config['interfaces'] = [];
    foreach ($config['interfaces'] as $ifname => $iface) {
        if (($iface['if'] ?? '') === $if_device) return $ifname;
    }
    $n = 1;
    while (isset($config['interfaces']["opt{$n}"])) $n++;
    $ifname = "opt{$n}";
    $config['interfaces'][$ifname] = ['if' => $if_device, 'descr' => $descr, 'enable' => '', 'ipaddr' => 'dhcp'];
    return $ifname;
}

function nvpn_create_gateway($ifname, $gw_name, $descr) {
    global $config;
    if (!isset($config['gateways']['gateway_item'])) $config['gateways']['gateway_item'] = [];
    foreach ($config['gateways']['gateway_item'] as &$gw) {
        if ($gw['name'] === $gw_name) { $gw['interface'] = $ifname; return; }
    }
    $config['gateways']['gateway_item'][] = [
        'interface'       => $ifname,
        'gateway'         => 'dynamic',
        'name'            => $gw_name,
        'weight'          => '1',
        'ipprotocol'      => 'inet',
        'descr'           => $descr . ' (nordvpn-managed)',
        'monitor_disable' => '1',
    ];
}

// ── Firewall rules ────────────────────────────────────────────────────────────

function nvpn_apply_rules() {
    global $config;
    $cfg     = nvpn_get_config();
    $tunnels = $cfg['tunnels'] ?? [];

    if (!isset($config['filter']['rule'])) $config['filter']['rule'] = [];

    $config['filter']['rule'] = array_values(array_filter(
        $config['filter']['rule'],
        fn($r) => strpos($r['descr'] ?? '', '[nordvpn-managed]') === false
    ));

    foreach ($cfg['rules'] as $rule) {
        if (empty($rule['enabled']) || $rule['enabled'] === '0') continue;
        $tun_id = $rule['tunnel_id'] ?? '';
        if (!$tun_id || !isset($tunnels[$tun_id])) continue;

        $tunnel = $tunnels[$tun_id];
        [$src_type, $src_val] = nvpn_rule_source($rule);

        $fr = [
            'type'        => 'pass',
            'interface'   => $rule['interface'] ?? 'lan',
            'ipprotocol'  => 'inet',
            'protocol'    => 'any',
            'source'      => $src_type === 'network' ? ['network' => $src_val] : ['address' => $src_val],
            'destination' => ['any' => ''],
            'gateway'     => $tunnel['gw_name'],
            'descr'       => "[nordvpn-managed] {$rule['name']} -> {$tunnel['label']}",
            'tracker'     => (string)(time() + rand(1, 99999)),
            'created'     => ['time' => time(), 'username' => 'admin'],
        ];
        array_unshift($config['filter']['rule'], $fr);
    }

    write_config("NordVPN: applied routing rules");
    nvpn_run_filter_configure();
}

function nvpn_rule_source($rule) {
    return $rule['type'] === 'ip' ? ['address', $rule['value']] : ['network', $rule['value']];
}

/**
 * filter_configure() does not work from PHP CLI context (e.g. boot script).
 * Spawn a child process via mwexec() so it runs under php-fpm where it works.
 */
function nvpn_run_filter_configure(): void {
    if (php_sapi_name() === 'cli') {
        mwexec('/etc/rc.filter_configure');
    } elseif (function_exists('filter_configure')) {
        filter_configure();
    } else {
        mwexec('/etc/rc.filter_configure');
    }
}

// ── Kill switch ───────────────────────────────────────────────────────────────

function nvpn_apply_killswitch($enabled, $dns_ip = '103.86.96.100') {
    global $config;
    $cfg = nvpn_get_config();
    $config['filter']['rule'] = array_values(array_filter(
        $config['filter']['rule'] ?? [],
        fn($r) => strpos($r['descr'] ?? '', '[nordvpn-killswitch]') === false
    ));
    if ($enabled) {
        foreach ($cfg['rules'] as $rule) {
            if (empty($rule['enabled']) || $rule['enabled'] === '0') continue;
            [$src_type, $src_val] = nvpn_rule_source($rule);
            $config['filter']['rule'][] = [
                'type'        => 'block',
                'interface'   => $rule['interface'] ?? 'lan',
                'ipprotocol'  => 'inet',
                'protocol'    => 'any',
                'source'      => $src_type === 'network' ? ['network' => $src_val] : ['address' => $src_val],
                'destination' => ['any' => ''],
                'descr'       => "[nordvpn-killswitch] {$rule['name']}",
                'tracker'     => (string)(time() + rand(100000, 999999)),
            ];
        }
    }
    $cfg['killswitch'] = ['enabled' => $enabled ? '1' : '0', 'dns_ip' => $dns_ip];
    nvpn_save_config($cfg);
    write_config("NordVPN: kill switch " . ($enabled ? 'enabled' : 'disabled'));
    nvpn_run_filter_configure();
}

// ── Status ────────────────────────────────────────────────────────────────────

function nvpn_tunnel_statuses() {
    $cfg = nvpn_get_config();
    $out = [];
    foreach ($cfg['tunnels'] as $tun_id => $tunnel) {
        $vpnid      = $tunnel['vpnid'];
        $iface      = shell_exec("ifconfig ovpnc{$vpnid} 2>/dev/null") ?: '';
        $connected  = strpos($iface, 'inet ') !== false;
        $rule_count = count(array_filter($cfg['rules'],
            fn($r) => ($r['tunnel_id'] ?? '') === $tun_id && !empty($r['enabled']) && $r['enabled'] !== '0'));
        $out[$tun_id] = compact('tunnel', 'connected', 'iface', 'rule_count');
    }
    return $out;
}
