<?php
/**
 * NordVPN boot script - re-applies tunnels and firewall rules after reboot.
 * Called from pfSense shellcmd on startup.
 * /usr/local/etc/nordvpn_boot.php
 */
require_once('/etc/inc/config.lib.inc');
require_once('/etc/inc/config.inc');
require_once('/etc/inc/util.inc');
require_once('/etc/inc/interfaces.inc');
require_once('/etc/inc/gwlb.inc');
require_once('/etc/inc/filter.inc');
require_once('/usr/local/www/nordvpn/nordvpn_routing.inc');

$log    = '/var/log/openvpn/nordvpn_boot.log';
$logdir = '/var/log/openvpn';
if (!is_dir($logdir)) mkdir($logdir, 0755, true);

function boot_log($msg) {
    global $log;
    file_put_contents($log, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

boot_log("NordVPN boot script starting");

$cfg     = nvpn_get_config();
$tunnels = $cfg['tunnels'] ?? [];

if (empty($tunnels)) {
    boot_log("No tunnels configured, nothing to do");
    exit(0);
}

$creds = nvpn_get_credentials();
if (empty($creds['user']) || empty($creds['pass'])) {
    boot_log("ERROR: No credentials found at " . nvpn_credentials_path());
    exit(1);
}

boot_log("Found " . count($tunnels) . " tunnel(s), credentials present");

// Wait for WAN to be available before launching tunnels
$wan_up = false;
for ($i = 0; $i < 30; $i++) {
    $gw = shell_exec("route -n get -inet default 2>/dev/null | grep gateway");
    if ($gw && trim($gw) !== '') { $wan_up = true; break; }
    boot_log("Waiting for WAN... ({$i}s)");
    sleep(2);
}
if (!$wan_up) {
    boot_log("WARNING: WAN not available after 60s, launching tunnels anyway");
} else {
    boot_log("WAN is up, proceeding");
}

global $config;

foreach ($tunnels as $tun_id => $tunnel) {
    $vpnid   = (int)$tunnel['vpnid'];
    $ifname  = $tunnel['ifname'];
    $gw_name = $tunnel['gw_name'];
    $label   = $tunnel['label'] ?? $tun_id;

    boot_log("Restoring tunnel {$tun_id}: {$tunnel['hostname']} vpnid={$vpnid} ifname={$ifname}");

    // Find the OpenVPN client config from config.xml
    $client = null;
    foreach (($config['openvpn']['openvpn-client'] ?? []) as $c) {
        if ((int)$c['vpnid'] === $vpnid) { $client = $c; break; }
    }

    if (!$client) {
        boot_log("WARNING: OpenVPN client vpnid={$vpnid} not found in config.xml, skipping");
        continue;
    }

    // Inject current credentials
    $client['auth_user'] = $creds['user'];
    $client['auth_pass'] = $creds['pass'];

    // Ensure interface and gateway exist
    nvpn_ensure_interface("ovpnc{$vpnid}", "NordVPN_{$vpnid}");
    nvpn_create_gateway($ifname, $gw_name, "NordVPN {$label}");

    // Write config files and launch OpenVPN
    nvpn_start_openvpn_client($client, $vpnid, $ifname, $gw_name);
    boot_log("OpenVPN client{$vpnid} launched");
    sleep(5);
}

// Write firewall/PBR rules to config.xml — pfSense boot loads them into pf after shellcmds
boot_log("Writing firewall and NAT rules to config");
nvpn_apply_rules();

boot_log("NordVPN boot script complete");
exit(0);
