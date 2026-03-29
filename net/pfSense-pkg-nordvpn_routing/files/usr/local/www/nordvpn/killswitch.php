<?php
/**
 * NordVPN Routing - Kill Switch Page
 * /usr/local/www/nordvpn/killswitch.php
 */
require_once("guiconfig.inc");
require_once("/usr/local/www/nordvpn/nordvpn_routing.inc");
$pgtitle = ['VPN', 'NordVPN Routing', 'Kill Switch'];
$msg_ok = $msg_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'set_killswitch') {
        $enabled = isset($_POST['killswitch_enabled']);
        nvpn_apply_killswitch($enabled);
        $msg_ok = "Kill switch " . ($enabled ? 'enabled.' : 'disabled.');
    }
}
$cfg = nvpn_get_config();
$ks  = $cfg['killswitch']['enabled'] ?? false;
$rules_arr   = is_array($cfg['rules'] ?? null) ? $cfg['rules'] : [];
$routed_rules = array_filter($rules_arr, fn($r) => !empty($r['enabled']) && !empty($r['tunnel_id']));
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
    ['Kill Switch',true,  '/nordvpn/killswitch.php'],
    ['DNS',        false, '/nordvpn/dns.php'],
    ['Status',     false, '/nordvpn/status.php'],
];
display_top_tabs($tab_array);
?>
<div class="col-sm-12">
<?php if ($msg_ok): ?><div class="alert alert-success"><?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>
<?php if ($msg_err): ?><div class="alert alert-danger"><?= htmlspecialchars($msg_err) ?></div><?php endif; ?>
<div class="panel panel-default">
  <div class="panel-heading">
    <h2 class="panel-title">Kill Switch
      <?php if ($ks): ?><span class="label label-warning" style="margin-left:8px;"><i class="fa fa-lock"></i> ACTIVE</span>
      <?php else: ?><span class="label label-default" style="margin-left:8px;">Inactive</span><?php endif; ?>
    </h2>
  </div>
  <div class="panel-body">
    <p>The <strong>kill switch</strong> adds a <code>BLOCK</code> firewall rule below each VPN pass rule. If a tunnel drops unexpectedly, routed devices are blocked from using the regular WAN connection — preventing IP leaks.</p>
    <?php if (empty($cfg['tunnels'])): ?>
    <div class="alert alert-warning">No tunnels configured. <a href="/nordvpn/tunnels.php">Create a tunnel first.</a></div>
    <?php elseif (empty($routed_rules)): ?>
    <div class="alert alert-info">No active routing rules. <a href="/nordvpn/rules.php">Add rules</a> before enabling the kill switch.</div>
    <?php else: ?>
    <div class="alert alert-<?= $ks ? 'warning' : 'info' ?>">
      <?php if ($ks): ?><strong><i class="fa fa-lock"></i> Kill switch is ON.</strong> Routed devices will be blocked if their tunnel drops.
      <?php else: ?><strong>Kill switch is OFF.</strong> If a tunnel drops, affected devices will fall back to the regular WAN connection.<?php endif; ?>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="set_killswitch">
      <div class="form-group">
        <div class="checkbox"><label><input type="checkbox" name="killswitch_enabled" <?= $ks ? 'checked' : '' ?>> Enable kill switch</label></div>
        <span class="help-block">When checked, routed devices are blocked from internet access if their VPN tunnel is down.</span>
      </div>
      <button type="submit" class="btn btn-<?= $ks ? 'danger' : 'primary' ?>">
        <i class="fa fa-<?= $ks ? 'unlock' : 'lock' ?>"></i> <?= $ks ? 'Disable Kill Switch' : 'Enable Kill Switch' ?>
      </button>
    </form>
    <?php if (!empty($routed_rules)): ?>
    <hr><strong>Protected devices / subnets:</strong>
    <table class="table table-condensed" style="margin-top:8px;max-width:600px;">
      <thead><tr><th>Rule</th><th>Source</th><th>Tunnel</th></tr></thead>
      <tbody>
        <?php foreach ($routed_rules as $rule): $tun = $cfg['tunnels'][$rule['tunnel_id']] ?? null; ?>
        <tr><td><?= htmlspecialchars($rule['name']) ?></td><td><code><?= htmlspecialchars($rule['value']) ?></code></td>
        <td><?= $tun ? htmlspecialchars($tun['label']) : '<em class="text-muted">unassigned</em>' ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">How It Works</h2></div>
  <div class="panel-body">
    <p>The kill switch works by inserting pfSense firewall rules in this order:</p>
    <ol>
      <li><strong>PASS</strong> — source → destination via VPN gateway (inserted by routing rules)</li>
      <li><strong>BLOCK</strong> — source → any (inserted by kill switch, below the pass rule)</li>
    </ol>
    <p>When the tunnel is up, traffic matches rule 1 and exits through the VPN. If the tunnel drops, rule 1 has no valid gateway and pfSense skips it; traffic then hits rule 2 and is blocked rather than leaking through WAN.</p>
    <p class="help-block"><strong>Note:</strong> The kill switch only protects devices with an active routing rule. Devices without rules continue using the default WAN connection regardless.</p>
  </div>
</div>
</div></div></div></section>
<?php include("foot.inc"); ?>
</body>
