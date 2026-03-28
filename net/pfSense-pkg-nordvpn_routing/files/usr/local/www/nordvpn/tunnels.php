<?php
/**
 * NordVPN Routing - Tunnels Page
 * /usr/local/www/nordvpn/tunnels.php
 */
require_once("guiconfig.inc");
require_once("/usr/local/www/nordvpn/nordvpn_routing.inc");
$pgtitle = ['VPN', 'NordVPN Routing', 'Tunnels'];
$msg_ok = $msg_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_creds') {
        $user = trim($_POST['cred_user'] ?? '');
        $pass = trim($_POST['cred_pass'] ?? '');
        if (!$user || !$pass) {
            $msg_err = "Both service username and password are required.";
        } else {
            nvpn_save_credentials($user, $pass);
            $msg_ok = "Service credentials saved.";
            $saved_user = $user;
        }

    } elseif ($action === 'fetch_servers') {
        $cc = strtoupper(trim($_POST['country_code'] ?? 'US'));
        $fetched = nvpn_fetch_servers($cc, 100);
        if (empty($fetched)) {
            global $nvpn_last_error;
            $diag = $nvpn_last_error ? " Diagnostic: {$nvpn_last_error}" : " Check WAN internet access from pfSense.";
            $msg_err = "Could not fetch servers for '{$cc}'.{$diag}";
        } else {
            nvpn_write_server_cache($fetched);
            $msg_ok = "Fetched " . count($fetched) . " servers for '{$cc}'.";
            $fetched_servers = $fetched;
        }

    } elseif ($action === 'diag_curl') {
        $diag_results = [];
        $test_url = 'https://api.nordvpn.com/v1/servers/recommendations?limit=1';
        $ip = gethostbyname('api.nordvpn.com');
        $diag_results[] = ($ip !== 'api.nordvpn.com') ? "DNS: OK — api.nordvpn.com resolves to {$ip}" : "DNS: FAILED";
        if (function_exists('curl_init')) {
            $ch = curl_init($test_url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'pfSense-NordVPN-pkg/2.0.1', CURLOPT_CAINFO => '/etc/ssl/cert.pem', CURLOPT_SSL_VERIFYPEER => true]);
            $r = curl_exec($ch); $e = curl_error($ch); $en = curl_errno($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            $diag_results[] = ($r !== false && $http === 200) ? "curl+SSL: OK — HTTP {$http}, " . strlen($r) . " bytes" : "curl+SSL: FAILED — errno={$en} {$e} HTTP={$http}";
            $ch2 = curl_init($test_url);
            curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'pfSense-NordVPN-pkg/2.0.1', CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]);
            $r2 = curl_exec($ch2); $e2 = curl_error($ch2); $http2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE); curl_close($ch2);
            $diag_results[] = ($r2 !== false && $http2 === 200) ? "curl (no SSL): OK — HTTP {$http2}" : "curl (no SSL): FAILED — {$e2} HTTP={$http2}";
        } else {
            $diag_results[] = "curl: NOT AVAILABLE";
        }
        $msg_ok = implode(" | ", $diag_results);

    } elseif ($action === 'create_tunnel') {
        $label    = trim($_POST['tunnel_label']  ?? '');
        $hostname = trim($_POST['tunnel_server'] ?? '');
        $proto    = $_POST['tunnel_proto'] === 'tcp' ? 'tcp' : 'udp';
        if (!$label || !$hostname) {
            $msg_err = "Tunnel label and server are required.";
        } else {
            $creds = nvpn_get_credentials();
            if (!$creds['user'] || !$creds['pass']) {
                $msg_err = "Save your NordVPN service credentials first.";
            } else {
                $result = nvpn_create_tunnel($label, $hostname, $proto);
                if ($result) {
                    $msg_ok = "Tunnel '{$label}' created and connecting to {$hostname}.";
                    $just_created = true;
                } else {
                    global $nvpn_last_error;
                    $diag = $nvpn_last_error ? " Detail: {$nvpn_last_error}" : "";
                    $msg_err = "Failed to create tunnel '{$label}'.{$diag}";
                }
            }
        }

    } elseif ($action === 'switch_server') {
        $tun_id       = $_POST['tun_id']       ?? '';
        $new_hostname = trim($_POST['new_hostname'] ?? '');
        if (!$tun_id || !$new_hostname) {
            $msg_err = "Invalid switch request.";
        } else {
            $ok = nvpn_switch_tunnel_server($tun_id, $new_hostname);
            $msg_ok  = $ok ? "Tunnel switched to {$new_hostname}." : '';
            $msg_err = $ok ? '' : "Failed to switch server.";
        }

    } elseif ($action === 'remove_tunnel') {
        $tun_id = $_POST['tun_id'] ?? '';
        if ($tun_id) {
            $ok = nvpn_remove_tunnel($tun_id);
            $msg_ok  = $ok ? "Tunnel removed." : '';
            $msg_err = $ok ? '' : "Tunnel not found.";
        }
    }
}

$cfg     = nvpn_get_config();
$creds   = $cfg['credentials'];
$cred_user_display = $saved_user ?? $creds['user'];
$servers  = $fetched_servers ?? $cfg['server_cache'];
$statuses = nvpn_tunnel_statuses();

include("head.inc");
?>
<?php if (!empty($just_created)): ?>
<meta http-equiv="refresh" content="30;url=/nordvpn/tunnels.php">
<?php endif; ?>
<body>
<?php include("fbegin.inc"); ?>
<section class="page-content-main">
<div class="container-fluid">
<div class="row">
<?php
$tab_array = [
    ['Tunnels',    true,  '/nordvpn/tunnels.php'],
    ['Rules',      false, '/nordvpn/rules.php'],
    ['Kill Switch',false, '/nordvpn/killswitch.php'],
    ['DNS',        false, '/nordvpn/dns.php'],
    ['Status',     false, '/nordvpn/status.php'],
];
display_top_tabs($tab_array);
?>
<div class="col-sm-12">
<?php if ($msg_ok): ?>
<div class="alert alert-success">
  <?= htmlspecialchars($msg_ok) ?>
  <?php if (!empty($just_created)): ?>
  <div style="margin-top:10px;">
    <strong>Connecting...</strong> Page will refresh automatically in <span id="nvpn_countdown">30</span> seconds.
    <div style="margin-top:6px;background:#d4edda;border-radius:4px;height:8px;width:100%;">
      <div id="nvpn_progress" style="background:#28a745;height:8px;border-radius:4px;width:100%;transition:width 1s linear;"></div>
    </div>
  </div>
  <script>
  var secs = 30;
  var interval = setInterval(function() {
    secs--;
    var el = document.getElementById('nvpn_countdown');
    var bar = document.getElementById('nvpn_progress');
    if (el) el.textContent = secs;
    if (bar) bar.style.width = (secs / 30 * 100) + '%';
    if (secs <= 0) clearInterval(interval);
  }, 1000);
  </script>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($msg_err): ?><div class="alert alert-danger"><?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">NordVPN Service Credentials</h2></div>
  <div class="panel-body">
    <p class="help-block">These are your NordVPN <strong>service credentials</strong> — different from your account email/password.<br>Find them at <a href="https://nordvpn.com/dashboard" target="_blank">nordvpn.com → Dashboard → Manual Setup → OpenVPN</a>.</p>
    <form method="post">
      <input type="hidden" name="action" value="save_creds">
      <div class="form-group"><label>Service Username</label><input type="text" name="cred_user" class="form-control" style="max-width:340px" value="<?= htmlspecialchars($cred_user_display) ?>" autocomplete="off" placeholder="e.g. Ab1234567"></div>
      <div class="form-group"><label>Service Password</label><input type="password" name="cred_pass" class="form-control" style="max-width:340px" value="" autocomplete="off" placeholder="<?= $cred_user_display ? '(saved — enter to change)' : 'long random string' ?>"></div>
      <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Credentials</button>
      <?php if ($cred_user_display): ?><span class="label label-success" style="margin-left:10px;vertical-align:middle;"><i class="fa fa-check"></i> Credentials saved</span><?php endif; ?>
    </form>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Browse NordVPN Servers</h2></div>
  <div class="panel-body">
    <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px;">
      <form method="post" class="form-inline">
        <input type="hidden" name="action" value="fetch_servers">
        <label style="margin-right:8px;">Country Code:</label>
        <input type="text" name="country_code" class="form-control" style="width:80px;margin-right:8px;" value="US" maxlength="2" placeholder="US">
        <button type="submit" class="btn btn-default"><i class="fa fa-refresh"></i> Fetch Servers</button>
        <span class="help-block" style="display:inline;margin-left:12px;">e.g. US, GB, DE, FR, CA, AU, NL, JP, SE, CH, SG</span>
      </form>
      <form method="post"><input type="hidden" name="action" value="diag_curl"><button type="submit" class="btn btn-default btn-sm"><i class="fa fa-stethoscope"></i> Test Connectivity</button></form>
    </div>
    <?php if (!empty($servers)): ?>
    <div style="max-height:320px;overflow-y:auto;">
    <table class="table table-striped table-hover table-condensed">
      <thead><tr><th>Server Name</th><th>Hostname</th><th>Country / City</th><th>Load</th></tr></thead>
      <tbody>
        <?php foreach ($servers as $s):
          $load = (int)$s['load'];
          $cls  = $load < 30 ? 'success' : ($load < 70 ? 'warning' : 'danger');
        ?>
        <tr>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><code><?= htmlspecialchars($s['hostname']) ?></code></td>
          <td><?= htmlspecialchars($s['country']) ?><?= $s['city'] ? ' / ' . htmlspecialchars($s['city']) : '' ?></td>
          <td><span class="label label-<?= $cls ?>"><?= $load ?>%</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?>
    <p class="text-muted">No servers fetched yet. Enter a country code and click Fetch.</p>
    <?php endif; ?>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Create New Tunnel</h2></div>
  <div class="panel-body">
    <div class="alert alert-info" style="margin-bottom:14px;">
      <strong>Connection limit:</strong> NordVPN limits simultaneous connections based on your plan. Most plans allow 10 devices. Each tunnel counts as one connection. If a tunnel fails with AUTH_FAILED, you may have reached your limit — disconnect other devices or remove unused tunnels.
    </div>
    <form method="post">
      <input type="hidden" name="action" value="create_tunnel">
      <div class="row">
        <div class="col-sm-3"><div class="form-group"><label>Tunnel Label <span class="text-danger">*</span></label><input type="text" name="tunnel_label" class="form-control" placeholder="e.g. US Streaming" maxlength="40"><span class="help-block">A friendly name for this tunnel.</span></div></div>
        <div class="col-sm-4"><div class="form-group"><label>Server Hostname <span class="text-danger">*</span></label><input type="text" name="tunnel_server" class="form-control" placeholder="e.g. us8734.nordvpn.com"><span class="help-block">Copy hostname from the server list above.</span></div></div>
        <div class="col-sm-2"><div class="form-group"><label>Protocol</label><select name="tunnel_proto" class="form-control"><option value="udp">UDP (faster)</option><option value="tcp">TCP (firewall-friendly)</option></select></div></div>
        <div class="col-sm-2" style="padding-top:25px;"><button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Create Tunnel</button></div>
      </div>
    </form>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Active Tunnels</h2></div>
  <div class="panel-body">
    <?php if (empty($statuses)): ?>
    <p class="text-muted">No tunnels configured. Create your first tunnel above.</p>
    <?php else: ?>
    <table class="table table-striped table-hover">
      <thead><tr><th>Label</th><th>Server</th><th>Protocol</th><th>Status</th><th>Rules</th><th>Switch Server</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($statuses as $tun_id => $st): $t = $st['tunnel']; ?>
        <tr>
          <td><strong><?= htmlspecialchars($t['label']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($tun_id) ?></small></td>
          <td><code><?= htmlspecialchars($t['hostname']) ?></code></td>
          <td><?= strtoupper(htmlspecialchars($t['proto'])) ?></td>
          <td>
            <?php if ($st['connected']): ?>
            <span class="label label-success"><i class="fa fa-circle"></i> Connected</span>
            <?php if ($st['tun_ip']): ?><br><small class="text-muted"><?= htmlspecialchars($st['tun_ip']) ?></small><?php endif; ?>
            <?php else: ?><span class="label label-danger"><i class="fa fa-circle-o"></i> Down</span><?php endif; ?>
          </td>
          <td><?php if ($st['rule_count'] > 0): ?><a href="/nordvpn/rules.php" class="label label-info"><?= $st['rule_count'] ?> rule<?= $st['rule_count'] > 1 ? 's' : '' ?></a><?php else: ?><span class="text-muted">none</span><?php endif; ?></td>
          <td>
            <form method="post" class="form-inline">
              <input type="hidden" name="action" value="switch_server">
              <input type="hidden" name="tun_id" value="<?= htmlspecialchars($tun_id) ?>">
              <?php if (!empty($servers)): ?>
              <select name="new_hostname" class="form-control input-sm" style="max-width:220px;">
                <?php foreach ($servers as $s): ?><option value="<?= htmlspecialchars($s['hostname']) ?>" <?= $s['hostname'] === $t['hostname'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?><?= !empty($s['city']) ? ' — ' . htmlspecialchars($s['city']) : '' ?> (<?= (int)$s['load'] ?>%)</option><?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-xs btn-default" title="Switch to selected server"><i class="fa fa-exchange"></i> Switch</button>
              <?php else: ?><span class="text-muted">Fetch servers first</span><?php endif; ?>
            </form>
          </td>
          <td>
            <form method="post" onsubmit="return confirm('Remove tunnel \'<?= htmlspecialchars(addslashes($t['label'])) ?>\'?');">
              <input type="hidden" name="action" value="remove_tunnel">
              <input type="hidden" name="tun_id" value="<?= htmlspecialchars($tun_id) ?>">
              <button type="submit" class="btn btn-xs btn-danger" title="Remove this tunnel"><i class="fa fa-trash"></i> Remove</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

</div></div></div></section>
<?php include("foot.inc"); ?>
</body>
