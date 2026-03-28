<?php
/**
 * NordVPN Routing - DNS Leak Prevention Page
 * /usr/local/www/nordvpn/dns.php
 */

require_once("guiconfig.inc");
require_once("/usr/local/www/nordvpn/nordvpn_routing.inc");

$pgtitle = ['VPN', 'NordVPN Routing', 'DNS'];
$msg_ok  = '';
$msg_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_dns') {
        global $config;
        require_once('/etc/inc/services.inc');

        $enabled_ifaces = $_POST['dns_ifaces'] ?? [];
        $nord_dns1      = trim($_POST['nord_dns1'] ?? '103.86.96.100');
        $nord_dns2      = trim($_POST['nord_dns2'] ?? '103.86.99.100');

        if (!filter_var($nord_dns1, FILTER_VALIDATE_IP)) {
            $msg_err = "Invalid primary NordVPN DNS IP.";
        } elseif ($nord_dns2 && !filter_var($nord_dns2, FILTER_VALIDATE_IP)) {
            $msg_err = "Invalid secondary NordVPN DNS IP.";
        } else {
            // Apply DNS settings to each DHCP-enabled interface
            foreach ($config['dhcpd'] ?? [] as $ifname => $dhcpcfg) {
                $iface = $config['interfaces'][$ifname] ?? null;
                if (!$iface) continue;
                $gw_ip = $iface['ipaddr'] ?? '';

                $mode = $_POST['dns_mode_' . $ifname] ?? 'none';
                if ($mode === 'nordvpn_only') {
                    $dns = array_filter([$nord_dns1, $nord_dns2]);
                    $config['dhcpd'][$ifname]['dnsserver'] = array_values($dns);
                } elseif ($mode === 'pfsense_nord') {
                    $dns = array_filter([$gw_ip, $nord_dns1, $nord_dns2]);
                    $config['dhcpd'][$ifname]['dnsserver'] = array_values($dns);
                } else {
                    $config['dhcpd'][$ifname]['dnsserver'] = [];
                }   
            }
            write_config("NordVPN: updated DHCP DNS settings");
            services_dhcpd_configure();
            $msg_ok = "DNS settings saved. Devices must renew their DHCP lease to get the new settings.";
        }
    }
}

// Build interface list
global $config;
$iface_list = [];
foreach ($config['interfaces'] ?? [] as $ifname => $iface) {
    if ($ifname === 'wan') continue;
    if (!isset($config['dhcpd'][$ifname])) continue; // skip non-DHCP interfaces
    $ip = $iface['ipaddr'] ?? '';
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) continue; // skip VPN tunnel ifaces
    $dns     = $config['dhcpd'][$ifname]['dnsserver'] ?? [];
    $dns     = is_array($dns) ? $dns : [];
    $has_nord = !empty(array_filter($dns, fn($d) => strpos($d, '103.86.') === 0));
    $has_gw   = !empty(array_filter($dns, fn($d) => $d === $ip));
    if ($has_nord && $has_gw)   $mode = 'pfsense_nord';
    elseif ($has_nord)          $mode = 'nordvpn_only';
    else                        $mode = 'none';
    $iface_list[$ifname] = [
        'descr' => $iface['descr'] ?? strtoupper($ifname),
        'ip'    => $ip,
        'dns'   => $dns,
        'mode'  => $mode,
    ];
}

// Get current NordVPN DNS values from first configured interface, or use defaults
$current_nord_dns1 = '103.86.96.100';
$current_nord_dns2 = '103.86.99.100';

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
    ['DNS',        true,  '/nordvpn/dns.php'],
    ['Status',     false, '/nordvpn/status.php'],
];
display_top_tabs($tab_array);
?>
<div class="col-sm-12">

<?php if ($msg_ok): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg_ok) ?></div>
<?php endif; ?>
<?php if ($msg_err): ?>
<div class="alert alert-danger"><?= htmlspecialchars($msg_err) ?></div>
<?php endif; ?>

<!-- ── Overview ── -->
<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">DNS Leak Prevention</h2></div>
  <div class="panel-body">
    <p>
      By default, devices use pfSense as their DNS server, which resolves queries using whatever
      upstream DNS is configured — potentially leaking DNS through your ISP.
      This page lets you push NordVPN's DNS servers directly to selected interfaces via DHCP,
      so VPN-routed devices resolve DNS through NordVPN rather than your ISP.
    </p>
    <div class="alert alert-info" style="margin-top:12px;">
      <strong>How it works:</strong> Each enabled interface will have three DNS servers pushed via DHCP:
      <ol style="margin:8px 0 0 16px;">
        <li>The interface gateway (e.g. 192.168.1.1) — for local hostname resolution</li>
        <li>NordVPN primary DNS — for external queries through NordVPN</li>
        <li>NordVPN secondary DNS — fallback</li>
      </ol>
    </div>
    <div class="alert alert-warning" style="margin-top:8px;">
      <strong>Cellular / mobile WAN users:</strong> If your WAN connection is via a cellular modem or SIM,
      NordVPN's DNS servers may not be directly reachable from your WAN IP. In this case, ensure
      <strong>DNS Server Override</strong> is enabled in System → General Setup so your ISP's DNS
      remains available for non-VPN traffic. VPN-routed devices will still use NordVPN DNS directly.
    </div>
    <div class="alert alert-warning" style="margin-top:8px;">
      <strong>Static IP devices:</strong> Devices configured with a static IP address do not receive
      DHCP assignments and will not get these DNS settings automatically. Configure their DNS manually
      to use <code>103.86.96.100</code> as primary and their gateway IP as secondary.
    </div>
  </div>
</div>

<!-- ── NordVPN DNS Servers ── -->
<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">NordVPN DNS Servers</h2></div>
  <div class="panel-body">
    <form method="post">
      <input type="hidden" name="action" value="save_dns">
      <div class="row">
        <div class="col-sm-3">
          <div class="form-group">
            <label>Primary NordVPN DNS</label>
            <input type="text" name="nord_dns1" class="form-control"
                   value="<?= htmlspecialchars($current_nord_dns1) ?>">
            <span class="help-block">Default: 103.86.96.100</span>
          </div>
        </div>
        <div class="col-sm-3">
          <div class="form-group">
            <label>Secondary NordVPN DNS</label>
            <input type="text" name="nord_dns2" class="form-control"
                   value="<?= htmlspecialchars($current_nord_dns2) ?>">
            <span class="help-block">Default: 103.86.99.100</span>
          </div>
        </div>
      </div>

      <!-- ── Interface Table ── -->
      <table class="table table-striped table-hover" style="margin-top:8px;">
        <thead>
          <tr>
            <th>DNS Mode</th>
            <th>Interface</th>
            <th>Subnet</th>
            <th>Current DNS Assignment</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($iface_list as $ifname => $idata): ?>
          <tr>
            <td>
                <select name="dns_mode_<?= htmlspecialchars($ifname) ?>" class="form-control input-sm" style="min-width:160px;">
                <option value="none"         <?= $idata['mode'] === 'none'         ? 'selected' : '' ?>>Default (pfSense)</option>
                <option value="pfsense_nord" <?= $idata['mode'] === 'pfsense_nord' ? 'selected' : '' ?>>pfSense + NordVPN</option>
                <option value="nordvpn_only" <?= $idata['mode'] === 'nordvpn_only' ? 'selected' : '' ?>>NordVPN Only</option>
              </select>
            </td>
            <td>
              <strong><?= htmlspecialchars($idata['descr']) ?></strong>
              <br><small class="text-muted"><?= htmlspecialchars($ifname) ?></small>
            </td>
            <td><code><?= htmlspecialchars($idata['ip']) ?></code></td>
            <td>
              <?php if (empty($idata['dns'])): ?>
              <span class="text-muted">Default (pfSense)</span>
              <?php else: ?>
              <?php foreach ($idata['dns'] as $d): ?><code><?= htmlspecialchars($d) ?></code><br><?php endforeach; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($idata['mode'] === 'nordvpn_only'): ?>
              <span class="label label-success"><i class="fa fa-shield"></i> NordVPN Only</span>
              <?php elseif ($idata['mode'] === 'pfsense_nord'): ?>
              <span class="label label-info"><i class="fa fa-shield"></i> pfSense + NordVPN</span>
              <?php else: ?>
              <span class="label label-default">Default DNS</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <button type="submit" class="btn btn-primary" style="margin-top:8px;">
        <i class="fa fa-save"></i> Save DNS Settings
      </button>
      <span class="help-block" style="display:inline;margin-left:12px;">
        Devices must reconnect or renew their DHCP lease to receive updated DNS settings.
      </span>
    </form>
  </div>
</div>

<!-- ── Verification ── -->
<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Verification</h2></div>
  <div class="panel-body">
    <p>After enabling NordVPN DNS and reconnecting your device, verify at:</p>
    <ul>
      <li><a href="https://dnsleaktest.com" target="_blank">dnsleaktest.com</a> — should show NordVPN DNS servers, not your ISP</li>
      <li><a href="https://nordvpn.com/what-is-my-ip/" target="_blank">nordvpn.com/what-is-my-ip</a> — should show a NordVPN exit IP</li>
    </ul>
    <p class="help-block">
      If dnsleaktest.com shows your ISP's DNS servers, your device may still be using a cached DNS
      assignment. Try: <strong>ipconfig /flushdns</strong> (Windows) or reconnect to the network.
    </p>
    <div class="alert alert-warning" style="margin-top:8px;">
      <strong>Windows DHCP renewal:</strong> After saving DNS settings, Windows devices must renew their DHCP lease to receive the updated DNS servers. Run <strong>ipconfig /release</strong> then <strong>ipconfig /renew</strong> in an elevated command prompt, or disconnect and reconnect from the network.
    </div>
  </div>
</div>

</div>
</div>
</div>
</section>
<?php include("foot.inc"); ?>
</body>