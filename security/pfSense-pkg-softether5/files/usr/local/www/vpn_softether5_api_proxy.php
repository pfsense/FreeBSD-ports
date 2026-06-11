<?php
/*
 * vpn_softether5_api_proxy.php
 */
error_reporting(0);

// Always HTTP 200 — nginx intercepts 4xx/5xx and replaces the body with
// its own HTML error page, breaking JSON parsing.  Errors go in the body.
function send_json($data) {
    header('Content-Type: application/json');
    $out = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($out === false) {
        $out = json_encode(['error' => 'Response encoding failed', 'detail' => json_last_error_msg()]);
    }
    echo $out;
    exit;
}

// Delete temp files, then bail out with a JSON error.
function send_json_error_cleanup(array $files, $error, $detail = null) {
    foreach ($files as $f) { @unlink($f); }
    $out = ['error' => $error];
    if ($detail !== null) { $out['detail'] = $detail; }
    send_json($out);
}

// Check for remote host/port for cascade connections
$remote_host = trim((string)($_POST['remote_host'] ?? ''));
$remote_port = (int)($_POST['remote_port'] ?? 0);

if ($remote_host !== '') {
    $raw_host = trim($remote_host);
    $url_candidate = preg_match('#^https?://#i', $raw_host) ? $raw_host : ('https://' . ltrim($raw_host, '/'));
    $parsed = @parse_url($url_candidate);
    $parsed_host = is_array($parsed) ? ($parsed['host'] ?? '') : '';
    $parsed_port = is_array($parsed) ? ($parsed['port'] ?? 0) : 0;

    if (!is_string($parsed_host) || trim($parsed_host) === '') {
        send_json(['error' => 'Invalid remote host format']);
    }

    $remote_host = trim($parsed_host);
    if (($remote_port <= 0 || $remote_port > 65535) && is_int($parsed_port) && $parsed_port > 0 && $parsed_port <= 65535) {
        $remote_port = $parsed_port;
    }
    if ($remote_port <= 0 || $remote_port > 65535) {
        $remote_port = 443;
    }

    $softether_url = "https://" . $remote_host . ":" . $remote_port . "/api";
} else {
    $softether_url = "https://127.0.0.1:5555/api";
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    send_json(['error' => 'Method Not Allowed']);
}

$input = $_POST['payload'] ?? file_get_contents('php://input');
$input = is_string($input) ? trim($input) : '';
if ($input === '') {
    send_json(['error' => 'Missing JSON-RPC payload']);
}

$rpc_method = '';
$rpc_data = json_decode($input, true);
if (is_array($rpc_data) && isset($rpc_data['method']) && is_string($rpc_data['method'])) {
    $rpc_method = $rpc_data['method'];
}

$allow_no_auth = ($remote_host !== '' && $rpc_method === 'EnumHub');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($auth === '' && !$allow_no_auth) {
    send_json(['error' => 'Missing Authorization header']);
}

// Extract password from Basic auth for CLI fallback use
$vpncmd_pass = '';
if (preg_match('/^Basic\s+(.+)$/i', $auth, $auth_m)) {
    $decoded_auth = base64_decode($auth_m[1]);
    if ($decoded_auth !== false && strpos($decoded_auth, ':') !== false) {
        [, $vpncmd_pass] = explode(':', $decoded_auth, 2);
    }
}

// --- CLI infrastructure (shared by both fallback paths) ---

$vpncmd_bin = '/usr/local/bin/vpncmd';
if (!file_exists($vpncmd_bin)) {
    $vpncmd_bin = '/usr/local/libexec/softether/vpncmd/vpncmd';
}
$vpncmd_available = file_exists($vpncmd_bin);

// Port 5555 is the SoftEther management TCP port.
// localhost without a port defaults to 443, which is pfSense's web server.
$vpncmd_host = 'localhost:5555';

// CLI command templates, keyed by JSON-RPC method name. Three template forms:
//   'Cmd {Param}'           — server-level command, run as "vpncmd ... /SERVER /CMD <tpl>"
//   'HUB_CTX:{HubName}:Cmd' — hub-context command, piped via stdin as "Hub <name>\n<cmd>\n"
//                             (vpncmd has no command-line flag to pre-select a hub)
//   'CERT_CTX' / 'CERT_REGEN_CTX' — special handlers in run_cli_fallback()
$cli_map = [
    // Server-level commands (no hub context needed)
    'EnumHub'           => 'HubList',
    'CreateHub'         => 'HubCreate {HubName} /PASSWORD:{Password}',
    'DeleteHub'         => 'HubDelete {HubName}',
    'SetHubPassword'    => 'HubPasswordSet {HubName} /PASSWORD:{Password}',
    'GetServerStatus'   => 'ServerStatusGet',
    'SetServerPassword'      => 'ServerPasswordSet {Password}',
    'SetServerAdminPassword' => 'ServerPasswordSet {Password}',
    // Listener/Port commands
    'EnumListener'      => 'ListenerList',
    'AddListener'       => 'ListenerCreate {Port}',
    'CreateListener'    => 'ListenerCreate {Port}',
    'DeleteListener'    => 'ListenerDelete {Port}',
    'EnableListener'    => 'ListenerEnable {Port}',
    'DisableListener'   => 'ListenerDisable {Port}',
    // Local Bridge commands (server-level)
    'EnumLocalBridge'   => 'BridgeList',
    'CreateLocalBridge' => 'BridgeCreate {HubName} /DEVICE:{Device} /TAP:{Mode}',
    'DeleteLocalBridge' => 'BridgeDelete {HubName} /DEVICE:{Device}',
    // General/utility commands
    'Check'             => 'Check',
    'About'             => 'About',
    'VersionGet'        => 'VersionGet',
    'ServerInfoGet'     => 'ServerInfoGet',
    'ServerCertGet'          => 'CERT_CTX',
    'GetServerCertInfo'      => 'CERT_CTX',
    'GetServerCertPem'       => 'CERT_CTX',
    'ServerCertRegenerate'   => 'CERT_REGEN_CTX',
    'GetServerInfo'          => 'ServerInfoGet',
    'ServerCertSet'     => 'ServerCertSet /CERT:{Cert}',
    // Hub-context commands: piped via stdin as "Hub {HubName}\n<cmd>\n"
    'EnumUser'          => 'HUB_CTX:{HubName}:UserList',
    'CreateUser'        => 'HUB_CTX:{HubName}:UserCreate {UserName} /GROUP:{Group} /REALNAME:{RealName} /NOTE:{Note}',
    'DeleteUser'        => 'HUB_CTX:{HubName}:UserDelete {UserName}',
    'SetUserPassword'   => 'HUB_CTX:{HubName}:UserPasswordSet {UserName} /PASSWORD:{Password}',
    'RenameUser'        => 'HUB_CTX:{HubName}:UserRename {UserName} /NEWNAME:{NewName}',
    'EnumGroup'         => 'HUB_CTX:{HubName}:GroupList',
    'ListGroup'         => 'HUB_CTX:{HubName}:GroupList',
    'CreateGroup'       => 'HUB_CTX:{HubName}:GroupCreate {Group}',
    'AddGroup'          => 'HUB_CTX:{HubName}:GroupCreate {Group} /REALNAME:{RealName} /NOTE:{Note}',
    'GetGroup'          => 'HUB_CTX:{HubName}:GroupGet {Group}',
    'DeleteGroup'       => 'HUB_CTX:{HubName}:GroupDelete {Group}',
    'DelGroup'          => 'HUB_CTX:{HubName}:GroupDelete {Group}',
    'EnumSession'       => 'HUB_CTX:{HubName}:SessionList',
    'DisconnectSession' => 'HUB_CTX:{HubName}:SessionDisconnect {SessionId}',
    'EnumIpTable'       => 'HUB_CTX:{HubName}:IpTableList',
    'DeleteIpTable'     => 'HUB_CTX:{HubName}:IpTableDelete {Ip}',
    'EnumAccessList'    => 'HUB_CTX:{HubName}:AccessList',
    'DeleteAccessList'  => 'HUB_CTX:{HubName}:AccessDelete {Id}',
    'EnumCascade'       => 'HUB_CTX:{HubName}:CascadeList',
    'DeleteCascade'     => 'HUB_CTX:{HubName}:CascadeDelete {NewName}',
    // Hub-context: user management (extended)
    'GetUser'           => 'HUB_CTX:{HubName}:UserGet {UserName}',
    'SetUser'           => 'HUB_CTX:{HubName}:UserSet {UserName} /GROUP:{GroupName} /REALNAME:{RealName} /NOTE:{Note}',
    // Hub-context: access list
    'GetAccessList'     => 'HUB_CTX:{HubName}:AccessList',
    'DeleteAccess'      => 'HUB_CTX:{HubName}:AccessDelete {Id}',
    // Hub-context: SecureNAT
    'EnableSecureNat'   => 'HUB_CTX:{HubName}:SecureNatEnable',
    'DisableSecureNat'  => 'HUB_CTX:{HubName}:SecureNatDisable',
    'GetSecureNatStatus'=> 'HUB_CTX:{HubName}:SecureNatStatusGet',
    'GetSecureNatOption'=> 'HUB_CTX:{HubName}:NatGet',
    'SetSecureNatOption'=> 'HUB_CTX:{HubName}:NatSet /USE:{UseNat} /ADDR:{Nat_Ip} /MASK:{Nat_Mask} /LOG:{SaveLog}',
    'EnumNat'           => 'HUB_CTX:{HubName}:NatTable',
    'EnumDhcp'          => 'HUB_CTX:{HubName}:DhcpTable',
    // Hub-context: RADIUS
    'GetHubRadius'      => 'HUB_CTX:{HubName}:RadiusGet',
    // Hub-context: log
    'GetHubLog'         => 'HUB_CTX:{HubName}:LogGet',
    // Hub-context: CA certificates
    'EnumCa'            => 'HUB_CTX:{HubName}:CAList',
    'AddCa'             => 'HUB_CTX:{HubName}:CAAdd {CertFile}',
    'DeleteCa'          => 'HUB_CTX:{HubName}:CADelete {Key}',
    // Hub online/offline (hub context commands)
    'SetHubOnline'      => 'HUB_CTX:{HubName}:Online',
    'SetHubOffline'     => 'HUB_CTX:{HubName}:Offline',
    // Server-level: connection table
    'EnumConnection'    => 'ConnectionList',
    // Hub-context: MAC/IP table
    'DeleteMacTable'    => 'HUB_CTX:{HubName}:MacTableDelete {MacAddress}',
    // Hub-context: missing mappings
    'EnumMacTable'      => 'HUB_CTX:{HubName}:MacTable',
    'SetGroup'          => 'HUB_CTX:{HubName}:GroupSet {Group} /REALNAME:{RealName} /NOTE:{Note}',
    'GroupPolicySet'    => 'HUB_CTX:{HubName}:GroupPolicySet {Group} /NAME:{PolicyName} /VALUE:{PolicyValue}',
    'GroupPolicyRemove' => 'HUB_CTX:{HubName}:GroupPolicyRemove {Group} /NAME:{PolicyName}',
    'KillSession'       => 'HUB_CTX:{HubName}:SessionDisconnect {Name}',
    'AddAccess'         => 'HUB_CTX:{HubName}:AccessAdd /MEMO:{Note} /PRIORITY:{Priority} {Action} /SRCIPMASK:{SrcIpMask} /DSTIPMASK:{DstIpMask} /SRCPORT:{SrcPort} /DSTPORT:{DstPort}',
    'SetHubRadius'      => 'HUB_CTX:{HubName}:RadiusSet {RadiusServer} {RadiusPort} /SECRET:{RadiusSecret} /RETRY:{RadiusRetry}',
    'SetHubLog'         => 'HUB_CTX:{HubName}:LogSet /SECURITY:{Security} /PACKET:{Packet} /SWITCH:{Switch}',
    'GetLink'           => 'HUB_CTX:{HubName}:CascadeGet {AccountName}',
    'EnumLink'          => 'HUB_CTX:{HubName}:CascadeList',
    'CreateLink'        => 'HUB_CTX:{HubName}:CascadeCreate {AccountName} /SERVER:{Hostname}:{Port} /HUB:{HubName_str} /USERNAME:{Username}',
    'SetLink'           => 'HUB_CTX:{HubName}:CascadeSet {AccountName} /SERVER:{Hostname}:{Port} /HUB:{HubName_str} /USERNAME:{Username}',
    'DeleteLink'        => 'HUB_CTX:{HubName}:CascadeDelete {AccountName}',
    'SetLinkOnline'     => 'HUB_CTX:{HubName}:CascadeOnline {AccountName}',
    'SetLinkOffline'    => 'HUB_CTX:{HubName}:CascadeOffline {AccountName}',
    'SetHub'            => 'HUB_CTX:{HubName}:HubEdit /TYPE:{HubType} /MAXSESSION:{MaxSessionCount} /NOENUM:{NoEnum}',
    'GetHub'            => 'HUB_CTX:{HubName}:HubStatus',
    'GetHubStatus'      => 'HUB_CTX:{HubName}:HubStatus',
    'GetHubMsg'         => 'HUB_CTX:{HubName}:MsgGet',
    'SetHubMsg'         => 'HUB_CTX:{HubName}:MsgSet /MSG:{Msg}',
    // Server-level: missing mappings
    'GetIPsecConfig'    => 'IPsecGet',
    'SetIPsecConfig'    => 'IPsecSet /L2TP:{L2TP} /L2TPRAW:{L2TPRaw} /ETHERIP:{EtherIP} /PSK:{PSK} /DEFAULTHUB:{DefaultHub}',
    'EnumEthernet'      => 'NicList',
    'EnumEth'           => 'NicList',
    'AddLocalBridge'    => 'BridgeCreate {HubNameLB} /DEVICE:{DeviceName} /TAP:{TapMode}',
    'GetSysLog'         => 'SyslogGet',
    'SetSysLog'         => 'SyslogSet /TYPE:{SyslogType} /HOST:{Hostname} /PORT:{Port}',
    'GetOpenVpnSstpConfig' => 'OpenVpnSstpGet',
    'SetOpenVpnSstpConfig' => 'OpenVpnSstpSet /OPENVPN:{OpenVPN} /SSTP:{SSTP}',

    'SetDDnsClientConfig' => 'DynamicDnsSetHostname {Hostname}',
    // Server-level: maintenance
    'RebootServer'      => 'Reboot',
    'GetConfig'         => 'ConfigGet',
    'SetConfig'         => 'ConfigSet {ConfigFile}',
    'FlushLog'          => 'LogFlush',
    'GetDDnsClientStatus' => 'DynamicDnsGetStatus',
    // Hub-context: admin/extended options
    'GetHubAdminOptions'    => 'HUB_CTX:{HubName}:AdminOptionList',
    'SetHubAdminOption'     => 'HUB_CTX:{HubName}:AdminOptionSet {Name} /VALUE:{Value}',
    'GetHubExtendedOptions' => 'HUB_CTX:{HubName}:ExtOptionList',
    'SetHubExtendedOption'  => 'HUB_CTX:{HubName}:ExtOptionSet {Name} /VALUE:{Value}',
    // Hub-context: CRL
    'EnumCrl'               => 'HUB_CTX:{HubName}:CrlList',
    'AddCrl'                => 'HUB_CTX:{HubName}:CrlAdd',
    'DeleteCrl'             => 'HUB_CTX:{HubName}:CrlDelete {Key}',
    // Hub-context: user expiry
    'SetUserExpires'        => 'HUB_CTX:{HubName}:UserExpiresSet {UserName} /EXPIRES:{Expires}',
    // Server-level: keep alive
    'GetKeepConfig'         => 'KeepGet',
    'SetKeepConfig'         => 'KeepSet /PROTO:{Protocol} /HOST:{Host} /PORT:{Port} /INTERVAL:{Interval}',
    'KeepEnable'            => 'KeepOn',
    'KeepDisable'           => 'KeepOff',
    // Server-level: VPN Azure
    'GetAzureStatus'        => 'VpnAzureGetStatus',
    'SetAzureEnable'        => 'VpnAzureSetEnable /ENABLE:{Enable}',
    // Server-level: VPN over ICMP/DNS
    'GetVpnOverIcmpDns'     => 'VpnOverIcmpDnsGet',
    'SetVpnOverIcmpDns'     => 'VpnOverIcmpDnsSet /ICMP:{IcmpEnable} /DNS:{DnsEnable}',
    // Server-level: SSL cipher
    'GetServerCipher'       => 'ServerCipherGet',
    'SetServerCipher'       => 'ServerCipherSet {Cipher}',
    // Server-level: log files
    'EnumLogFile'           => 'LogFileList',
    'ReadLogFile'           => 'LogFileGet {Path}',
    // Server-level: L3 switches
    'EnumL3Switch'          => 'RouterList',
    'AddL3Switch'           => 'RouterCreate {SwitchName}',
    'DelL3Switch'           => 'RouterDelete {SwitchName}',
    'StartL3Switch'         => 'RouterStart {SwitchName}',
    'StopL3Switch'          => 'RouterStop {SwitchName}',
    // Server-level: EtherIP
    'EnumEtherIpClient'     => 'EtherIpClientList',
    'AddEtherIpClient'      => 'EtherIpClientAdd /ID:{IpClientId} /HUB:{HubName} /USER:{UserName} /PASSWORD:{Password}',
    'DeleteEtherIpClient'   => 'EtherIpClientDelete /ID:{IpClientId}',
    // Hub-context: access rule enable/disable
    'EnableAccess'          => 'HUB_CTX:{HubName}:AccessEnable {Id}',
    'DisableAccess'         => 'HUB_CTX:{HubName}:AccessDisable {Id}',
    // Hub-context: WireGuard keys (SoftEther 5.x)
    'WgkEnum'               => 'HUB_CTX:{HubName}:WgkList',
    'WgkAdd'                => 'HUB_CTX:{HubName}:WgkAdd /USER:{UserName} /KEY:{PublicKey}',
    'WgkDelete'             => 'HUB_CTX:{HubName}:WgkDelete /KEY:{Key}',
    // Server-level: OpenVPN config generator
    'MakeOpenVpnConfigFile' => 'OpenVpnMakeConfig',
    // Server-level: protocol options
    'GetProtoOptions'       => 'ProtoOptionsGet {ProtoName}',
    'SetProtoOptions'       => 'ProtoOptionsSet {ProtoName} /NAME:{Name} /VALUE:{Value}',
];

// Resolve a template placeholder key against the params array.
// Tries: exact key, key with each type suffix stripped, common field aliases.
function cli_resolve_param($k, $params) {
    if (isset($params[$k])) return $params[$k];
    // Strip SoftEther JSON-RPC type suffixes (_str, _utf, _u32, _u64, _bool, _bin, _ip, _dt)
    $base = preg_replace('/_(?:str|utf|u32|u64|bool|bin|ip|dt)$/i', '', $k);
    if ($base !== $k && isset($params[$base])) return $params[$base];
    // Try common aliases: UserName→Name, HubName→HubName_str, etc.
    static $aliases = [
        'UserName'   => ['Name_str', 'Name', 'Username_str'],
        'HubName'    => ['HubName_str', 'HubNameLB_str', 'HubName'],
        'RealName'   => ['RealName_utf', 'RealName_str', 'Realname_utf'],
        'Note'       => ['Note_utf', 'Note_str'],
        'Group'      => ['GroupName_str', 'Group_str', 'Name_str'],
        'GroupName'  => ['GroupName_str', 'Group_str'],
        'Password'   => ['Password_str', 'PlainPassword_str'],
        'SessionId'  => ['SessionName_str', 'Name_str'],
        'Name'       => ['Name_str', 'SessionName_str', 'AccountName_utf'],
        'AccountName'=> ['AccountName_utf', 'Name_str'],
        'Hostname'   => ['Hostname_str'],
        'Cert'       => ['Cert_bin'],
        'Key'        => ['Key_u32'],
        'Id'         => ['Id_u32'],
        'Port'       => ['Port_u32'],
        'Ip'         => ['IpAddress_str', 'Ip_str'],
        'IpAddress'  => ['IpAddress_str'],
        'MacAddress' => ['MacAddress_bin', 'MacAddress_str'],
        'HubNameLB'  => ['HubNameLB_str', 'HubName_str'],
        'DeviceName' => ['DeviceName_str'],
        'TapMode'    => ['TapMode_bool'],
    ];
    if (isset($aliases[$k])) {
        foreach ($aliases[$k] as $a) {
            if (isset($params[$a])) return $params[$a];
        }
    }
    // Also try with added _str suffix
    if (isset($params[$k . '_str'])) return $params[$k . '_str'];
    if (isset($params[$k . '_utf'])) return $params[$k . '_utf'];
    if (isset($params[$k . '_u32'])) return $params[$k . '_u32'];
    return null;
}

// Replace {Placeholder} tokens with param values, shell-escaped by default.
// Pass $escape = false for vpncmd script content written to a temp file.
function cli_template_substitute($template, $params, $escape = true) {
    return preg_replace_callback('/\{([A-Za-z0-9_]+)\}/', function($m) use ($params, $escape) {
        $v = cli_resolve_param($m[1], $params);
        if ($v === null || $v === '') { return ''; }
        if (is_array($v)) {
            return implode(' ', $escape ? array_map('escapeshellarg', $v) : $v);
        }
        return $escape ? escapeshellarg((string)$v) : (string)$v;
    }, $template);
}

// Run a CLI fallback via vpncmd and send the result as JSON.
// $fallback_error: the original JSON-RPC error array from the HTTP API, if any.
function run_cli_fallback($rpc_data, $cli_map, $vpncmd_pass, $vpncmd_available, $fallback_error = null) {
    global $vpncmd_bin, $vpncmd_host;
    if (!$vpncmd_available) {
        $msg = $fallback_error['message'] ?? 'SoftEther API error (vpncmd not found)';
        send_json(['error' => $msg, 'detail' => $fallback_error]);
    }

    if (!is_array($rpc_data) || !isset($rpc_data['method'])) {
        send_json(['error' => 'Invalid RPC data for CLI fallback']);
    }

    $method = $rpc_data['method'];
    $params = $rpc_data['params'] ?? [];

    // Strip JSON-RPC type suffixes (_str, _u32, _bool, _int, _uint, _int64, _uint64)
    // and apply field renames so CLI templates resolve correctly regardless of
    // the suffix variant the JS sends.
    $normalized = [];
    foreach ($params as $k => $v) {
        $bare = preg_replace('/_(?:str|u32|int|uint|bool|int64|uint64)$/i', '', $k);
        $normalized[$bare] = $v;
        $normalized[$k]    = $v; // keep original too
    }
    // Field renames: JS name → CLI template name
    $renames = [
        'HubNameLB'    => 'HubName',     // AddLocalBridge / DeleteLocalBridge
        'AdminPassword'=> 'Password',    // CreateHub
        'Username'     => 'UserName',    // CreateUser (fallback variant)
        'Name'         => 'UserName',    // DeleteUser / GetUser
        'DeviceName'   => 'Device',      // LocalBridge
        'TapMode'      => 'Mode',        // LocalBridge tap flag
        'SessionName'  => 'SessionId',   // DisconnectSession
        'Ports'        => 'Port',        // EnumListener result field variant
    ];
    foreach ($renames as $from => $to) {
        if (isset($normalized[$from]) && !isset($normalized[$to])) {
            $normalized[$to] = $normalized[$from];
        }
    }
    $params = $normalized;

    if (!isset($cli_map[$method])) {
        $msg = $fallback_error['message'] ?? ('CLI fallback not mapped for: ' . $method);
        send_json(['error' => $msg, 'detail' => $fallback_error]);
    }

    if (!function_exists('exec') || !is_callable('exec')) {
        $msg = $fallback_error['message'] ?? 'SoftEther API error (exec() disabled)';
        send_json(['error' => $msg, 'detail' => $fallback_error]);
    }

    // Methods that are structurally unsupported in this SoftEther build — return empty gracefully.
    static $graceful_empty_methods = ['GetAzureStatus', 'GetDDnsClientStatus', 'EnumEth', 'EnumEthernet', 'WgkEnum'];
    if (in_array($method, $graceful_empty_methods, true)) {
        send_json(['result' => [], 'cli_fallback' => true]);
    }

    $template = $cli_map[$method];
    $pass_esc = escapeshellarg($vpncmd_pass);

    // ServerCertGet saves cert to a file via interactive prompt — handle specially.
    if ($template === 'CERT_CTX') {
        $certfile = tempnam('/tmp', 'se_cert_');
        $stdinfile = tempnam('/tmp', 'se_clin_');
        file_put_contents($stdinfile, $certfile . "\nexit\n");
        $exec_cmd = "timeout 10 $vpncmd_bin $vpncmd_host /SERVER /PASSWORD:$pass_esc /CMD ServerCertGet < " . escapeshellarg($stdinfile) . ' 2>&1';
        exec($exec_cmd, $_out, $_ret);
        @unlink($stdinfile);
        $pem = @file_get_contents($certfile);
        @unlink($certfile);
        if (!$pem) {
            send_json(['error' => 'Could not retrieve server certificate', 'detail' => implode("\n", $_out)]);
        }
        if ($method === 'GetServerCertPem') {
            send_json(['result' => ['Pem_str' => trim($pem)], 'cli_fallback' => true]);
        }
        send_json(['result' => parse_cert_pem($pem), 'cli_fallback' => true]);
    }

    // ServerCertRegenerate with full certificate fields via openssl + ServerCertSet
    if ($template === 'CERT_REGEN_CTX') {
        $cn       = trim($params['CN']       ?? '');
        $o        = trim($params['O']        ?? '');
        $ou       = trim($params['OU']       ?? '');
        $c        = trim($params['C']        ?? '');
        $st       = trim($params['ST']       ?? '');
        $l        = trim($params['L']        ?? '');
        $serial   = trim($params['Serial']   ?? '');
        $days     = max(1, (int)($params['Days'] ?? 3650));
        $bits     = in_array((int)($params['Bits'] ?? 2048), [1024, 2048, 4096], true) ? (int)$params['Bits'] : 2048;
        $signed_by = trim($params['SignedBy'] ?? '');

        // If only CN is provided and no CA signing, use vpncmd directly (simplest path)
        if (!$o && !$ou && !$c && !$st && !$l && !$signed_by) {
            $cn_esc = escapeshellarg($cn);
            $exec_cmd = "timeout 15 $vpncmd_bin $vpncmd_host /SERVER /PASSWORD:$pass_esc /CMD ServerCertRegenerate /CN:$cn_esc 2>&1";
            exec($exec_cmd, $_out, $_ret);
            if ($_ret !== 0) {
                send_json(['error' => 'ServerCertRegenerate failed', 'detail' => implode("\n", $_out)]);
            }
            send_json(['result' => true, 'cli_fallback' => true]);
        }

        // Build openssl subject string
        $subj_parts = [];
        if ($c)  { $subj_parts[] = 'C='  . str_replace('/', '', $c); }
        if ($st) { $subj_parts[] = 'ST=' . str_replace('/', '', $st); }
        if ($l)  { $subj_parts[] = 'L='  . str_replace('/', '', $l); }
        if ($o)  { $subj_parts[] = 'O='  . str_replace('/', '', $o); }
        if ($ou) { $subj_parts[] = 'OU=' . str_replace('/', '', $ou); }
        $subj_parts[] = 'CN=' . str_replace('/', '', $cn);
        $subj = '/' . implode('/', $subj_parts);

        $key_file  = tempnam('/tmp', 'se_key_');
        $csr_file  = tempnam('/tmp', 'se_csr_');
        $cert_file = tempnam('/tmp', 'se_crt_');
        $serial_flag = $serial ? (' -set_serial 0x' . preg_replace('/[^0-9A-Fa-f]/', '', $serial)) : '';
        $subj_esc = escapeshellarg($subj);

        // Generate private key
        exec("openssl genrsa -out " . escapeshellarg($key_file) . " $bits 2>&1", $_ko, $_kr);
        if ($_kr !== 0 || !filesize($key_file)) {
            send_json_error_cleanup([$key_file, $csr_file, $cert_file], 'openssl genrsa failed', implode("\n", $_ko));
        }

        if ($signed_by) {
            // CA-signed: split signing PEM into CA cert + CA key temp files
            $ca_cert_file = tempnam('/tmp', 'se_ca_crt_');
            $ca_key_file  = tempnam('/tmp', 'se_ca_key_');
            // Extract cert block
            preg_match('/(-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----)/s', $signed_by, $cm);
            // Extract key block (RSA or PRIVATE KEY)
            preg_match('/(-----BEGIN (?:RSA )?PRIVATE KEY-----.*?-----END (?:RSA )?PRIVATE KEY-----)/s', $signed_by, $km);
            if (!$cm || !$km) {
                send_json_error_cleanup([$key_file, $csr_file, $cert_file, $ca_cert_file, $ca_key_file],
                    'Signing PEM must contain both a certificate and a private key');
            }
            file_put_contents($ca_cert_file, $cm[1]);
            file_put_contents($ca_key_file,  $km[1]);

            // Generate CSR from new key
            exec("openssl req -new -key " . escapeshellarg($key_file) . " -out " . escapeshellarg($csr_file) . " -subj $subj_esc 2>&1", $_co, $_cr);
            if ($_cr !== 0) {
                send_json_error_cleanup([$key_file, $csr_file, $cert_file, $ca_cert_file, $ca_key_file],
                    'openssl req (CSR) failed', implode("\n", $_co));
            }
            // Sign CSR with CA
            exec("openssl x509 -req -in " . escapeshellarg($csr_file) . " -CA " . escapeshellarg($ca_cert_file) . " -CAkey " . escapeshellarg($ca_key_file) . " -out " . escapeshellarg($cert_file) . " -days $days$serial_flag -CAcreateserial 2>&1", $_co, $_cr);
            @unlink($ca_cert_file); @unlink($ca_key_file); @unlink($csr_file);
            if ($_cr !== 0 || !filesize($cert_file)) {
                send_json_error_cleanup([$key_file, $cert_file], 'openssl x509 signing failed', implode("\n", $_co));
            }
        } else {
            // Self-signed
            exec("openssl req -new -x509 -key " . escapeshellarg($key_file) . " -out " . escapeshellarg($cert_file) . " -days $days -subj $subj_esc$serial_flag 2>&1", $_co, $_cr);
            if ($_cr !== 0 || !filesize($cert_file)) {
                send_json_error_cleanup([$key_file, $cert_file], 'openssl req failed', implode("\n", $_co));
            }
        }

        // Write combined PEM (cert + key) to a temp file for ServerCertSet
        $combined_file = tempnam('/tmp', 'se_comb_');
        $pem_data = file_get_contents($cert_file) . "\n" . file_get_contents($key_file);
        file_put_contents($combined_file, $pem_data);
        @unlink($key_file); @unlink($cert_file);

        // Use ServerCertSet via stdin (requires filename prompt)
        $stdin_file = tempnam('/tmp', 'se_clin_');
        file_put_contents($stdin_file, $combined_file . "\nexit\n");
        $exec_cmd = "timeout 15 $vpncmd_bin $vpncmd_host /SERVER /PASSWORD:$pass_esc /CMD ServerCertSet < " . escapeshellarg($stdin_file) . ' 2>&1';
        exec($exec_cmd, $_out, $_ret);
        @unlink($stdin_file);
        @unlink($combined_file);

        if ($_ret !== 0) {
            send_json(['error' => 'ServerCertSet failed', 'detail' => implode("\n", $_out)]);
        }
        send_json(['result' => true, 'cli_fallback' => true]);
    }

    if (strncmp($template, 'HUB_CTX:', 8) === 0) {
        // Hub-context command: write "Hub <name>\n<cmd>\n" to a temp file and feed via stdin.
        // Format: HUB_CTX:{HubName}:<vpncmd-command>
        $rest    = substr($template, 8);
        $colon   = strpos($rest, ':');
        $hub_raw = cli_template_substitute(substr($rest, 0, $colon), $params, false);
        $cmd_raw = cli_template_substitute(substr($rest, $colon + 1), $params, false);
        $tmpfile = tempnam('/tmp', 'se_cli_');
        file_put_contents($tmpfile, "Hub $hub_raw\n$cmd_raw\nexit\n");
        $cli_cmd = "timeout 10 $vpncmd_bin $vpncmd_host /SERVER /PASSWORD:$pass_esc < " . escapeshellarg($tmpfile);
    } else {
        // Server-level command. /PASSWORD: must appear before /CMD in vpncmd syntax.
        $pass_part = ($vpncmd_pass !== '') ? " /PASSWORD:$pass_esc" : '';
        $cli_cmd   = "timeout 10 $vpncmd_bin $vpncmd_host /SERVER{$pass_part} /CMD "
                   . cli_template_substitute($template, $params) . ' < /dev/null';
    }

    $cli_output = [];
    $cli_ret = -1;
    exec($cli_cmd . ' 2>&1', $cli_output, $cli_ret);
    if (isset($tmpfile)) { @unlink($tmpfile); }

    // Per-method KV field mappings: CLI "Item" text → [js_field, type(bool|int|str)]
    static $cli_kv_field_maps = [
        'GetIPsecConfig' => [
            'L2TP over IPsec Server Function Enabled'              => ['L2TP_bool',           'bool'],
            'Raw L2TP Server Function Enabled'                     => ['L2TP_Raw_bool',        'bool'],
            'EtherIP / L2TPv3 over IPsec Server Function Enabled'  => ['EtherIP_bool',         'bool'],
            'IPsec Pre-Shared Key String'                          => ['IPsec_Secret_str',      'str'],
            'Name of Default Virtual Hub'                          => ['L2TP_DefaultHub_str',   'str'],
        ],
        'GetKeepConfig' => [
            'Host Name'                    => ['KeepConnectHost_str',     'str'],
            'Port Number'                  => ['KeepConnectPort_u32',     'int'],
            'Packet Send Interval (Sec)'   => ['KeepConnectInterval_u32', 'int'],
            'Current Status'               => ['_keepStatusRaw',          'str'],
            'Protocol'                     => ['_keepProtoRaw',           'str'],
        ],
        'GetVpnOverIcmpDns' => [
            'VPN over ICMP Server Enabled' => ['EnableVpnOverIcmp_bool', 'bool'],
            'VPN over DNS Server Enabled'  => ['EnableVpnOverDns_bool',  'bool'],
        ],
        'GetSecureNatOption' => [
            'Use Virtual NAT Function'        => ['UseNat_bool',       'bool'],
            'MTU Value'                       => ['Mtu_u32',           'int'],
            'TCP Session Timeout (Seconds)'   => ['NatTcpTimeout_u32', 'int'],
            'UDP Session Timeout (Seconds)'   => ['NatUdpTimeout_u32', 'int'],
            'Save NAT and DHCP Operation Log' => ['SaveLog_bool',      'bool'],
        ],
        'GetAzureStatus' => [
            'Azure Hostname'    => ['AzureHostName_str', 'str'],
            'VPN Azure Enabled' => ['IsEnabled_bool',    'bool'],
            'Connected'         => ['IsEnabled_bool',    'bool'],
        ],
        'GetServerInfo' => [
            'Product Name'                     => ['ServerProductName_str',  'str'],
            'Version'                          => ['ServerVersionString_str','str'],
            'Build'                            => ['ServerBuildInfoString_str','str'],
            'Host Name'                        => ['HostName_str',           'str'],
            'Server Type'                      => ['ServerType_str',         'str'],
            'Type of Operating System'         => ['OsType_str',             'str'],
            'Product Name of Operating System' => ['OsProductName_str',      'str'],
            'Operating System Vendor'          => ['OsVendor_str',           'str'],
            'Operating System Version'         => ['OsVersion_str',          'str'],
            'Type of OS Kernel'                => ['OsKernelType_str',       'str'],
            'Version of OS Kernel'             => ['OsKernelVersion_str',    'str'],
        ],
        'GetServerStatus' => [
            'Server Type'                                    => ['ServerType_str',                  'str'],
            'Number of Active Sockets'                       => ['NumActiveSockets_u32',             'num'],
            'Number of Virtual Hubs'                         => ['NumHubTotal_u32',                  'num'],
            'Number of Sessions'                             => ['NumSessionsTotal_u32',              'num'],
            'Number of MAC Address Tables'                   => ['NumMacTables_u32',                 'num'],
            'Number of IP Address Tables'                    => ['NumIpTables_u32',                  'num'],
            'Number of Users'                                => ['NumUsers_u32',                     'num'],
            'Number of Groups'                               => ['NumGroups_u32',                    'num'],
            'Using Client Connection Licenses (This Server)' => ['AssignedClientLicenseCount_u32',   'num'],
            'Using Bridge Connection Licenses (This Server)' => ['AssignedBridgeLicenseCount_u32',   'num'],
            'Outgoing Unicast Packets'                       => ['Send.UnicastPackets_u64',           'num'],
            'Outgoing Unicast Total Size'                    => ['Send.UnicastBytes_u64',             'num'],
            'Outgoing Broadcast Packets'                     => ['Send.BroadcastPackets_u64',         'num'],
            'Outgoing Broadcast Total Size'                  => ['Send.BroadcastBytes_u64',           'num'],
            'Incoming Unicast Packets'                       => ['Recv.UnicastPackets_u64',           'num'],
            'Incoming Unicast Total Size'                    => ['Recv.UnicastBytes_u64',             'num'],
            'Incoming Broadcast Packets'                     => ['Recv.BroadcastPackets_u64',         'num'],
            'Incoming Broadcast Total Size'                  => ['Recv.BroadcastBytes_u64',           'num'],
            'Server Started at'                              => ['_ServerStartedAt',                  'str'],
            'Current Time'                                   => ['_CurrentTime',                      'str'],
            '64 bit High-Precision Logical System Clock'     => ['LogicClock_u64',                   'str'],
        ],
        'GetGroup' => [
            'Group Name'                     => ['Name_str',                    'str'],
            'Full Name'                      => ['Realname_utf',                'str'],
            'Description'                    => ['Note_utf',                    'str'],
            'Outgoing Unicast Packets'        => ['Send.UnicastPackets_u64',    'num'],
            'Outgoing Unicast Total Size'     => ['Send.UnicastBytes_u64',      'num'],
            'Outgoing Broadcast Packets'      => ['Send.BroadcastPackets_u64',  'num'],
            'Outgoing Broadcast Total Size'   => ['Send.BroadcastBytes_u64',    'num'],
            'Incoming Unicast Packets'        => ['Recv.UnicastPackets_u64',    'num'],
            'Incoming Unicast Total Size'     => ['Recv.UnicastBytes_u64',      'num'],
            'Incoming Broadcast Packets'      => ['Recv.BroadcastPackets_u64',  'num'],
            'Incoming Broadcast Total Size'   => ['Recv.BroadcastBytes_u64',    'num'],
        ],
    ];

    if ($cli_ret === 0) {
        $option_block_methods = ['GetHubAdminOptions', 'GetHubExtendedOptions'];
        $kv_empty_methods     = ['GetAccessList'];


        if (in_array($method, $option_block_methods, true)) {
            $parsed = vpncmd_parse_option_blocks($cli_output);
        } elseif (in_array($method, $kv_empty_methods, true)) {
            $parsed = [];
        } elseif (array_key_exists($method, $cli_kv_field_maps)) {
            $kv     = vpncmd_parse_kv_flat($cli_output);
            $parsed = vpncmd_apply_kv_map($kv, $cli_kv_field_maps[$method]);
            // Post-process KeepConfig: convert proto/status to typed fields
            if ($method === 'GetKeepConfig') {
                $proto  = strtolower($parsed['_keepProtoRaw']  ?? '');
                $status = strtolower($parsed['_keepStatusRaw'] ?? '');
                $parsed['KeepConnectProtocol_u32'] = (strpos($proto, 'tcp') !== false) ? 0 : 1;
                $parsed['UseKeepConnect_bool']     = (strpos($status, 'enable') !== false || strpos($status, 'on') !== false);
                unset($parsed['_keepProtoRaw'], $parsed['_keepStatusRaw']);
            }
            if ($method === 'GetGroup') {
                // Parse the 3-column policy table appended after the flat KV section
                $policies = [];
                $in_pol = false;
                foreach ($cli_output as $line) {
                    $line = trim($line);
                    if (!$in_pol) {
                        if (strpos($line, 'Policy name') !== false && strpos($line, 'Setting value') !== false) {
                            $in_pol = true;
                        }
                        continue;
                    }
                    if (preg_match('/^[-|]+$/', $line)) { continue; }
                    $cols = explode('|', $line);
                    if (count($cols) >= 3) {
                        $pname = trim($cols[0]);
                        $pval  = trim($cols[2]);
                        if ($pname !== '') { $policies[$pname] = $pval; }
                    }
                }
                if (!empty($policies)) { $parsed['_policies'] = $policies; }
            }
            if ($method === 'GetServerStatus') {
                // Compute uptime from "Server Started at" and "Current Time"
                $started_raw = $parsed['_ServerStartedAt'] ?? '';
                $current_raw = $parsed['_CurrentTime'] ?? '';
                // "2026-06-10 (Wed) 10:08:13" → strip weekday
                $started_clean = trim(preg_replace('/\s*\([^)]+\)\s*/', ' ', $started_raw));
                // "2026-06-11 06:56:24.601" → strip milliseconds
                $current_clean = trim(preg_replace('/\.\d+$/', '', $current_raw));
                $t_start = strtotime($started_clean);
                $t_now   = strtotime($current_clean);
                if ($t_start && $t_now && $t_now >= $t_start) {
                    $parsed['ServerUpTime_u32']    = $t_now - $t_start;
                }
                $parsed['ServerStartedAt_str'] = $started_raw;
                $parsed['CurrentTime_str']     = $current_raw;
                unset($parsed['_ServerStartedAt'], $parsed['_CurrentTime']);
            }
        } else {
            $parsed = vpncmd_parse_table_output($cli_output);
        }
        send_json(['result' => $parsed, 'cli_fallback' => true]);
    } else {
        send_json(['error' => 'CLI fallback failed', 'detail' => implode("\n", $cli_output)]);
    }
}

// Methods for which a JSON-RPC error from the HTTP API triggers CLI fallback.
$cli_fallback_on_api_error = [
    'AddListener', 'CreateListener', 'DeleteListener', 'EnumListener', 'EnableListener', 'DisableListener',
    'GetHubAdminOptions', 'SetHubAdminOption',
    'GetHubExtendedOptions', 'SetHubExtendedOption',
    'GetServerStatus', 'GetServerInfo', 'ServerCertRegenerate',
    'GetIPsecConfig', 'GetDDnsClientStatus', 'GetKeepConfig', 'GetAzureStatus', 'GetVpnOverIcmpDns',
    'GetServerCertInfo', 'GetServerCertPem', 'EnumEth', 'EnumEthernet', 'WgkEnum', 'GetAccessList', 'GetSecureNatOption',
    'EnumEtherIpClient', 'GetProtoOptions',
    'GetGroup', 'SetGroup', 'CreateGroup', 'AddGroup', 'DeleteGroup', 'DelGroup',
    'GroupPolicySet', 'GroupPolicyRemove',
];

// --- Forward request to SoftEther HTTP API ---

$candidate_urls = [$softether_url];
if (substr($softether_url, -1) !== '/') {
    $candidate_urls[] = $softether_url . '/';
}

$response  = false;
$curl_errno = 0;
$curl_error = '';
$last_http_code = 0;
$last_url  = $softether_url;

foreach ($candidate_urls as $candidate_url) {
    $last_url = $candidate_url;
    $ch = curl_init($candidate_url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $headers = ['Content-Type: application/json'];
    if ($auth !== '') {
        $headers[] = 'Authorization: ' . $auth;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response      = curl_exec($ch);
    $curl_errno    = curl_errno($ch);
    $curl_error    = curl_error($ch);
    $last_http_code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response !== false && $curl_errno === 0) {
        $decoded_try = json_decode((string)$response, true);
        if (!($decoded_try === null && json_last_error() !== JSON_ERROR_NONE)) {
            break; // valid JSON received
        }
    }
}

// Path 1: curl connection failure → CLI fallback.
if ($response === false || $curl_errno) {
    run_cli_fallback($rpc_data, $cli_map, $vpncmd_pass, $vpncmd_available);
}

// Validate that the upstream response is JSON.
$decoded = json_decode((string)$response, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    // Non-JSON response (e.g. 401 HTML) — still try CLI fallback if mapped.
    if (in_array($rpc_method, $cli_fallback_on_api_error, true)) {
        run_cli_fallback($rpc_data, $cli_map, $vpncmd_pass, $vpncmd_available);
    }
    send_json([
        'error'               => 'Invalid response from upstream API',
        'detail'              => substr((string)$response, 0, 300),
        'upstream_url'        => $last_url,
        'upstream_http_code'  => $last_http_code,
    ], 502);
}

// Path 2: API returned a JSON-RPC error for a listener method → CLI fallback.
if (is_array($decoded) && isset($decoded['error']) && in_array($rpc_method, $cli_fallback_on_api_error, true)) {
    run_cli_fallback($rpc_data, $cli_map, $vpncmd_pass, $vpncmd_available, $decoded['error']);
}

// Success: relay the upstream response as-is.
send_json($decoded);

// --- Parse vpncmd tabular output into an associative array ---
function parse_cert_pem($pem) {
    $parsed = [];
    $info = openssl_x509_parse($pem);
    if (!$info) { return $parsed; }
    $fmt_dn = function($dn) {
        if (!is_array($dn)) { return (string)$dn; }
        $parts = [];
        foreach (['CN','O','OU','L','ST','C','emailAddress'] as $k) {
            if (!empty($dn[$k])) { $parts[] = "$k=" . $dn[$k]; }
        }
        return implode(', ', $parts) ?: implode(', ', array_values($dn));
    };
    $parsed['SubjectName_utf'] = $fmt_dn($info['subject'] ?? []);
    $parsed['IssuerName_utf']  = $fmt_dn($info['issuer']  ?? []);
    $parsed['NotBefore_dt']    = isset($info['validFrom_time_t']) ? gmdate('Y-m-d\TH:i:s.000\Z', $info['validFrom_time_t']) : '';
    $parsed['NotAfter_dt']     = isset($info['validTo_time_t'])   ? gmdate('Y-m-d\TH:i:s.000\Z', $info['validTo_time_t'])   : '';
    $parsed['Sha1Hash_bin']    = function_exists('openssl_x509_fingerprint') ? openssl_x509_fingerprint($pem, 'sha1') : ($info['hash'] ?? '');
    $parsed['SerialNumber_str']= $info['serialNumberHex'] ?? '';
    return $parsed;
}

function vpncmd_parse_table_output($lines) {
    $result = [];
    $header = [];
    $in_table = false;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '-----') === 0) continue;
        if (!$in_table && preg_match('/^[A-Za-z0-9_ ]+(\s+\|\s+[A-Za-z0-9_ ]+)+$/', $line)) {
            $header   = array_map('trim', explode('|', $line));
            $in_table = true;
            continue;
        }
        if ($in_table && !empty($header)) {
            $cols = array_map('trim', explode('|', $line));
            if (count($cols) === count($header)) {
                $result[] = array_combine($header, $cols);
            }
        }
    }
    return empty($result) ? implode("\n", $lines) : $result;
}

/**
 * Parse the vpncmd AdminOptionList/ExtOptionList "block" format where each
 * option is emitted as two key|value rows:
 *   Item       |option_name
 *   Value      |integer_value
 * separated by horizontal rule lines.
 * Returns array of ['Name_str' => ..., 'Value_u32' => int] objects.
 */
function vpncmd_parse_option_blocks($lines) {
    $options     = [];
    $current_name = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '---') === 0) { continue; }
        if (strpos($line, '|') === false) { continue; }
        [$key, $val] = array_map('trim', explode('|', $line, 2));
        if ($key === 'Item') {
            $current_name = $val;
        } elseif ($key === 'Value' && $current_name !== null) {
            $options[]    = ['Name_str' => $current_name, 'Value_u32' => (int)$val];
            $current_name = null;
        }
    }
    return $options;
}

// Parses a two-column "Item|Value" table (one row per setting) into an assoc array.
function vpncmd_parse_kv_flat($lines) {
    $result = [];
    $header_skipped = false;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '---') === 0) { continue; }
        if (strpos($line, '|') === false) { continue; }
        [$k, $v] = array_map('trim', explode('|', $line, 2));
        if (!$header_skipped && strtolower($k) === 'item') { $header_skipped = true; continue; }
        $result[$k] = $v;
    }
    return $result;
}

// Apply a field map to a KV flat result, producing a JS-compatible dict.
// $field_map: [ 'CLI Item Name' => ['js_field', 'bool'|'int'|'str'] ]
function vpncmd_apply_kv_map($kv, $field_map) {
    $out = [];
    foreach ($field_map as $item => [$field, $type]) {
        if (!array_key_exists($item, $kv)) { continue; }
        $raw = $kv[$item];
        switch ($type) {
            case 'bool': $out[$field] = (bool)preg_match('/^(yes|true|enable|on|1)$/i', $raw); break;
            case 'int':  $out[$field] = (int)$raw; break;
            case 'num':  $out[$field] = (int)preg_replace('/[^0-9]/', '', $raw); break;
            default:     $out[$field] = $raw;
        }
    }
    return $out;
}
