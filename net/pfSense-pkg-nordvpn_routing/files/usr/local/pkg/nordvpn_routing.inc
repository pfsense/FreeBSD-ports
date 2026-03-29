<?php
/**
 * NordVPN Routing - Package install/deinstall hooks
 * /usr/local/pkg/nordvpn_routing.inc
 *
 * pfSense 2.8+ requires config.lib.inc to be loaded first — it provides
 * g_get() which mwexec() and other util.inc functions depend on internally.
 */

require_once('/etc/inc/config.lib.inc');
require_once('/etc/inc/util.inc');
require_once('/etc/inc/config.inc');
require_once('/etc/inc/auth.inc');
require_once('/usr/local/www/nordvpn/nordvpn_routing.inc');

function nordvpn_routing_install(): void {
    // Register the boot script to run at startup
    nvpn_register_shellcmd(true);

    log_error("NordVPN Routing: package installed successfully.");
}

function nordvpn_routing_deinstall(): void {
    // Stop all tunnels and clean up firewall rules
    $cfg = nvpn_get_config();
    foreach (array_keys($cfg['tunnels'] ?? []) as $tun_id) {
        nvpn_remove_tunnel($tun_id);
    }

    global $config;
    $config['filter']['rule'] = array_values(array_filter(
        $config['filter']['rule'] ?? [],
        fn($r) => strpos($r['descr'] ?? '', '[nordvpn') === false
    ));
    write_config("NordVPN Routing: package removed, firewall rules cleaned up");

    // Remove boot script registration
    nvpn_register_shellcmd(false);

    // Remove package config from config.xml
    config_set_path('installedpackages/nordvpn_routing', null);
    write_config("NordVPN Routing: configuration removed");

    // Remove runtime credential and cache files
    @unlink('/usr/local/etc/nordvpn_credentials.json');
    @unlink('/usr/local/etc/nordvpn_server_cache.json');

    log_error("NordVPN Routing: package deinstalled cleanly.");
}

function nvpn_register_shellcmd(bool $enable): void {
    global $config;
    $boot_script = '/usr/local/etc/nordvpn_boot.php';
    $cmd = "/usr/local/bin/php {$boot_script}";

    if (!isset($config['system']['shellcmd'])) {
        $config['system']['shellcmd'] = [];
    }

    // Remove any existing entry first
    $config['system']['shellcmd'] = array_values(array_filter(
        $config['system']['shellcmd'],
        fn($c) => (is_array($c) ? ($c['cmd'] ?? '') : $c) !== $cmd
    ));

    if ($enable) {
        $config['system']['shellcmd'][] = $cmd;
    }

    write_config("NordVPN Routing: boot script " . ($enable ? 'registered' : 'unregistered'));
}
