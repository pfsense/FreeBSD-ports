<?php
/**
 * NordVPN Routing - Status Page
 * /usr/local/www/nordvpn/status.php
 */
require_once("guiconfig.inc");
require_once("/usr/local/www/nordvpn/nordvpn_routing.inc");
$pgtitle = ['VPN', 'NordVPN Routing', 'Status'];
$msg_ok = $msg_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'probe_mgmt') {
        $tun_id = $_POST['tun_id'] ?? '';
        $cfg    = nvpn_get_config();
        $tunnel = $cfg['tunnels'][$tun_id] ?? null;
        $lines  = [];
        if ($tunnel) {
            $vpnid    = (int)$tunnel['vpnid'];
            $log_file = "/var/log/openvpn/openvpn_client{$vpnid}.log";
            if (file_exists($log_file)) {
                $log = shell_exec("tail -40 " . escapeshellarg($log_file) . " 2>&1");
                $lines[] = "=== LOG (last 40 lines) ===\n" . $log;
            }
            $sock = "/var/etc/openvpn/client{$vpnid}/sock";
            if (file_exists($sock)) {
                $fp = @fsockopen("unix://{$sock}", -1, $errno, $errstr, 3);
                if ($fp) {
                    fwrite($fp, "state\n");
                    $resp = ''; $deadline = time() + 3;
                    while (!feof($fp) && time() < $deadline) {
                        $resp .= fgets($fp, 256);
                        if (strpos($resp, 'END') !== false) break;
                    }
                    fwrite($fp, "quit\n"); fclose($fp);
                    $lines[] = "=== MANAGEMENT SOCKET STATE ===\n" . $resp;
                } else {
                    $lines[] = "Management socket exists but could not connect: {$errstr}";
                }
            } else {
                $lines[] = "Management socket not found at {$sock}";
            }
            $pid_file = "/var/run/openvpn_client{$vpnid}.pid";
            if (file_exists($pid_file)) {
                $pid     = trim(file_get_contents($pid_file));
                $running = shell_exec("ps -p {$pid} 2>/dev/null | tail -1");
                $lines[] = "=== PROCESS (PID {$pid}) ===\n" . ($running ?: "Not found in process list");
            } else {
                $running = shell_exec("ps aux 2>/dev/null | grep openvpn | grep -v grep");
                $lines[] = "=== OPENVPN PROCESSES ===\n" . ($running ?: "None found");
            }
        }
        $msg_ok = implode("\n", array_filter($lines));

    } elseif ($action === 'restart_tunnel') {
        $tun_id = $_POST['tun_id'] ?? '';
        $cfg    = nvpn_get_config();
        $tunnel = $cfg['tunnels'][$tun_id] ?? null;
        if ($tunnel) {
            $vpnid = (int)$tunnel['vpnid'];
            global $config;
            nvpn_ensure_config_array('openvpn', 'openvpn-client');
            $found = false; $c = null;
            foreach ($config['openvpn']['openvpn-client'] as $cc) {
                if ((int)($cc['vpnid'] ?? 0) === $vpnid) { $found = true; $c = $cc; break; }
            }
            if ($found) {
                $creds = nvpn_get_credentials();
                $c['auth_user'] = $creds['user'];
                $c['auth_pass'] = $creds['pass'];
                nvpn_stop_openvpn_client($vpnid);
                sleep(1);
                nvpn_start_openvpn_client($c, $vpnid, $tunnel['ifname'], $tunnel['gw_name']);
                $msg_ok = "Tunnel '{$tunnel['label']}' restarted. Allow 15-20 seconds then refresh.";
            } else {
                $msg_err = "OpenVPN client config not found for tunnel '{$tunnel['label']}'. Remove and recreate it on the Tunnels page.";
            }
        }

    } elseif ($action === 'probe_system') {
        $lines = [];
        $lines[] = "openvpn binary: " . (file_exists('/usr/local/sbin/openvpn') ? 'YES' : 'NO');
        foreach (['/usr/local/etc/rc.d/openvpn', '/etc/rc.d/openvpn'] as $rc) {
            $lines[] = $rc . ': ' . (file_exists($rc) ? 'EXISTS' : 'not found');
        }
        $lines[] = "/etc/inc/openvpn.inc: " . (file_exists('/etc/inc/openvpn.inc') ? 'EXISTS' : 'not found');
        $lines[] = "/var/etc/openvpn/client1/config.ovpn: " . (file_exists('/var/etc/openvpn/client1/config.ovpn') ? 'EXISTS' : 'NOT FOUND');
        if (!function_exists('openvpn_resync')) @require_once('/etc/inc/openvpn.inc');
        $lines[] = "openvpn_resync() available: " . (function_exists('openvpn_resync') ? 'YES' : 'NO');
        $msg_ok = implode(' | ', $lines);
    }
}

$cfg      = nvpn_get_config();
$statuses = nvpn_tunnel_statuses();
$rules    = is_array($cfg['rules'] ?? null) ? $cfg['rules'] : [];
$ks       = $cfg['killswitch']['enabled'] ?? false;

include("head.inc");
?>
<body>
<?php include("fbegin.inc"); ?>
<section class="page-content-main">
<div class="container-fluid">
<div class="row">
<?php
$tab_array = [
    ['Tunnels',    false, '/nordvpn/tunnels.php'],
    ['Rules',      false, '/nordvpn/rules.php'],
    ['Kill Switch',false, '/nordvpn/killswitch.php'],
    ['DNS',        false, '/nordvpn/dns.php'],
    ['Status',     true,  '/nordvpn/status.php'],
];
display_top_tabs($tab_array);
?>
<div class="col-sm-12">
<?php if ($msg_ok): ?>
<div class="alert alert-success"><pre style="margin:0;white-space:pre-wrap;font-size:11px;background:transparent;border:none;padding:0;"><?= htmlspecialchars($msg_ok) ?></pre></div>
<?php endif; ?>
<?php if ($msg_err): ?><div class="alert alert-danger"><?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Overview</h2></div>
  <div class="panel-body">
    <?php $connected_count = count(array_filter($statuses, fn($s) => $s['connected'])); ?>
    <table class="table table-condensed" style="width:auto;">
      <tr><td><strong>Tunnels configured</strong></td><td><?= count($statuses) ?></td></tr>
      <tr><td><strong>Tunnels connected</strong></td>
        <td><?php
          if ($connected_count === count($statuses) && $connected_count > 0) echo '<span class="label label-success">';
          elseif ($connected_count > 0) echo '<span class="label label-warning">';
          else echo '<span class="label label-' . (count($statuses) > 0 ? 'danger' : 'default') . '">';
          echo "{$connected_count} / " . count($statuses) . "</span>"; ?></td></tr>
      <tr><td><strong>Active routing rules</strong></td><td><?= count(array_filter($rules, fn($r) => !empty($r['enabled']))) ?></td></tr>
      <tr><td><strong>Kill switch</strong></td><td><?= $ks ? '<span class="label label-warning"><i class="fa fa-lock"></i> Enabled</span>' : '<span class="label label-default">Disabled</span>' ?></td></tr>
    </table>
    <a href="/nordvpn/status.php" class="btn btn-xs btn-default" style="margin-top:6px;"><i class="fa fa-refresh"></i> Refresh</a>
    <form method="post" style="display:inline;margin-left:8px;"><input type="hidden" name="action" value="probe_system"><button type="submit" class="btn btn-xs btn-default"><i class="fa fa-stethoscope"></i> Probe System</button></form>
  </div>
</div>

<?php if (empty($statuses)): ?>
<div class="alert alert-info">No tunnels configured. <a href="/nordvpn/tunnels.php">Go to Tunnels to get started.</a></div>
<?php endif; ?>

<?php foreach ($statuses as $tun_id => $st): $t = $st['tunnel']; $tun_rules = array_filter($rules, fn($r) => ($r['tunnel_id'] ?? '') === $tun_id); ?>
<div class="panel panel-<?= $st['connected'] ? 'success' : 'danger' ?>">
  <div class="panel-heading" style="display:flex;align-items:center;justify-content:space-between;">
    <h2 class="panel-title">
      <i class="fa fa-circle<?= $st['connected'] ? '' : '-o' ?>" style="color:<?= $st['connected'] ? '#22d3a0' : '#cc0000' ?>;margin-right:6px;"></i>
      <?= htmlspecialchars($t['label']) ?> <small style="font-weight:normal;margin-left:10px;"><?= htmlspecialchars($t['hostname']) ?></small>
    </h2>
    <div style="display:flex;gap:6px;">
      <form method="post" style="margin:0;"><input type="hidden" name="action" value="restart_tunnel"><input type="hidden" name="tun_id" value="<?= htmlspecialchars($tun_id) ?>"><button type="submit" class="btn btn-xs btn-default"><i class="fa fa-refresh"></i> Restart</button></form>
      <form method="post" style="margin:0;"><input type="hidden" name="action" value="probe_mgmt"><input type="hidden" name="tun_id" value="<?= htmlspecialchars($tun_id) ?>"><button type="submit" class="btn btn-xs btn-info"><i class="fa fa-terminal"></i> Full Log</button></form>
    </div>
  </div>
  <div class="panel-body">
    <div class="row">
      <div class="col-sm-4">
        <strong>Connection</strong>
        <table class="table table-condensed" style="margin-top:6px;">
          <tr><td>Status</td><td><?= $st['connected'] ? '<span class="text-success"><strong>Connected</strong></span>' : '<span class="text-danger"><strong>Down</strong></span>' ?></td></tr>
          <tr><td>Process running</td><td><?php
            if ($st['proc_running']) echo '<span class="label label-success">Yes (PID ' . htmlspecialchars($st['pid']) . ')</span>';
            elseif ($st['pid']) echo '<span class="label label-danger">PID ' . htmlspecialchars($st['pid']) . ' not found</span>';
            else echo '<span class="label label-danger">Not started</span>';
          ?></td></tr>
          <tr><td>In pfSense config</td><td><?= $st['client_in_config'] ? '<span class="label label-success">Yes</span>' : '<span class="label label-danger">Missing — recreate tunnel</span>' ?></td></tr>
          <tr><td>Tunnel IP</td><td><?= $st['tun_ip'] ? '<code>' . htmlspecialchars($st['tun_ip']) . '</code>' : '<span class="text-muted">—</span>' ?></td></tr>
          <tr><td>Protocol</td><td><?= strtoupper(htmlspecialchars($t['proto'])) ?></td></tr>
          <tr><td>Gateway</td><td><code><?= htmlspecialchars($t['gw_name']) ?></code></td></tr>
          <tr><td>VPN ID</td><td><?= htmlspecialchars($t['vpnid']) ?></td></tr>
        </table>
        <?php if (!$st['connected']): ?>
        <div class="alert alert-warning" style="font-size:12px;padding:8px 12px;">
          <?php if (!$st['client_in_config']): ?><strong>Client config missing.</strong> Remove this tunnel and recreate it.
          <?php elseif (!$st['proc_running']): ?><strong>Process not running.</strong> Click Restart above.
          <?php else: ?><strong>Process running but not connected.</strong> Check log below.<?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="col-sm-3">
        <strong>Devices routed through this tunnel</strong>
        <?php if (empty($tun_rules)): ?>
        <p class="text-muted" style="margin-top:6px;font-size:12px;">No rules assigned. <a href="/nordvpn/rules.php">Add rules.</a></p>
        <?php else: ?>
        <table class="table table-condensed" style="margin-top:6px;">
          <thead><tr><th>Rule</th><th>Source</th><th>On</th></tr></thead>
          <tbody>
            <?php foreach ($tun_rules as $rule): ?>
            <tr><td><?= htmlspecialchars($rule['name']) ?></td><td><code style="font-size:11px;"><?= htmlspecialchars($rule['value']) ?></code></td>
            <td><?= !empty($rule['enabled']) ? '<span class="label label-success">On</span>' : '<span class="label label-default">Off</span>' ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
        <?php if ($st['connected']): ?>
        <p class="help-block" style="font-size:11px;margin-top:8px;">Verify: from a routed device visit<br><a href="https://nordvpn.com/what-is-my-ip/" target="_blank">nordvpn.com/what-is-my-ip</a></p>
        <?php endif; ?>
      </div>
      <div class="col-sm-5">
        <strong>OpenVPN Log <small class="text-muted">(last 10 lines)</small></strong>
        <?php if (!empty($st['log_lines'])): ?>
        <pre style="font-size:11px;max-height:200px;overflow-y:auto;margin-top:6px;background:#1a1a2e;color:#e2e8f8;padding:10px;border-radius:4px;"><?php
          foreach ($st['log_lines'] as $line) {
              $line = htmlspecialchars($line);
              if (stripos($line, 'Initialization Sequence Completed') !== false) echo '<span style="color:#22d3a0;font-weight:bold;">' . $line . '</span>';
              elseif (stripos($line, 'AUTH_FAILED') !== false || stripos($line, 'auth-failure') !== false) echo '<span style="color:#ff4f6a;font-weight:bold;">' . $line . '</span>';
              elseif (stripos($line, 'error') !== false || stripos($line, 'failed') !== false) echo '<span style="color:#f5c542;">' . $line . '</span>';
              else echo $line;
              echo "\n";
          }
        ?></pre>
        <?php else: ?>
        <p class="text-muted" style="margin-top:6px;font-size:12px;">No log yet. Try Restart then refresh in 15 seconds.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">DNS Leak Prevention</h2></div>
  <div class="panel-body">
    <p>VPN routing alone does not prevent DNS leaks. Use the <a href="/nordvpn/dns.php">DNS tab</a> to push NordVPN's DNS servers to VPN-routed interfaces via DHCP.</p>
    <p class="help-block">Verify at <a href="https://dnsleaktest.com" target="_blank">dnsleaktest.com</a> from a routed device.</p>
  </div>
</div>

</div></div></div></section>
<?php include("foot.inc"); ?>
</body>
