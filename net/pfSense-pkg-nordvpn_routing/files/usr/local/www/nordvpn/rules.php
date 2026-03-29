<?php
/**
 * NordVPN Routing - Rules Page
 * /usr/local/www/nordvpn/rules.php
 */

require_once("guiconfig.inc");
require_once("/usr/local/www/nordvpn/nordvpn_routing.inc");

$pgtitle = ['VPN', 'NordVPN Routing', 'Rules'];
$msg_ok  = '';
$msg_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_rule') {
        $name      = trim($_POST['rule_name']      ?? '');
        $type      = $_POST['rule_type']           ?? 'ip';
        $value     = trim($_POST['rule_value']     ?? '');
        $iface     = $_POST['rule_interface']      ?? 'lan';
        $tunnel_id = $_POST['rule_tunnel']         ?? '';
        if (!$name || !$value || !$tunnel_id) {
            $msg_err = "Name, source, and tunnel are all required.";
        } elseif ($type === 'ip' && !filter_var($value, FILTER_VALIDATE_IP)) {
            $msg_err = "Invalid IP address.";
        } elseif (in_array($type, ['vlan', 'range']) && !preg_match('/^[\d.]+\/\d{1,2}$/', $value)) {
            $msg_err = "Subnet must be in CIDR format (e.g. 192.168.10.0/24).";
        } else {
            $cfg = nvpn_get_config();
            $rule_id = 'rule_' . time() . '_' . rand(100, 999);
            $cfg['rules'][$rule_id] = [
                'id' => $rule_id, 'name' => $name, 'type' => $type,
                'value' => $value, 'interface' => $iface,
                'tunnel_id' => $tunnel_id, 'enabled' => '1',
            ];
            nvpn_save_config($cfg);
            nvpn_apply_rules();
            $msg_ok = "Rule '{$name}' added.";
        }

    } elseif ($action === 'toggle_rule') {
        $rule_id = $_POST['rule_id'] ?? '';
        $cfg = nvpn_get_config();
        if (isset($cfg['rules'][$rule_id])) {
            $cfg['rules'][$rule_id]['enabled'] = (($cfg['rules'][$rule_id]['enabled'] ?? '') === '1') ? '0' : '1';
            nvpn_save_config($cfg);
            nvpn_apply_rules();
            $msg_ok = "Rule " . ($cfg['rules'][$rule_id]['enabled'] ? 'enabled' : 'disabled') . ".";
        }

    } elseif ($action === 'reassign_all') {
        $cfg = nvpn_get_config();
        $first_tunnel = array_key_first($cfg['tunnels'] ?? []);
        if ($first_tunnel) {
            $count = 0;
            foreach ($cfg['rules'] as $rid => &$rule) {
                if (empty($rule['enabled']) || empty($rule['tunnel_id']) || !isset($cfg['tunnels'][$rule['tunnel_id']])) {
                    $rule['tunnel_id'] = $first_tunnel; $rule['enabled'] = '1'; $count++;
                }
            }
            nvpn_save_config($cfg); nvpn_apply_rules();
            $msg_ok = "Re-enabled {$count} rule(s) and assigned to tunnel '" . htmlspecialchars($cfg['tunnels'][$first_tunnel]['label']) . "'.";
        } else {
            $msg_err = "No tunnels available.";
        }

    } elseif ($action === 'reassign_rule') {
        $rule_id   = $_POST['rule_id']    ?? '';
        $tunnel_id = $_POST['new_tunnel'] ?? '';
        $cfg = nvpn_get_config();
        if (isset($cfg['rules'][$rule_id]) && isset($cfg['tunnels'][$tunnel_id])) {
            $cfg['rules'][$rule_id]['tunnel_id'] = $tunnel_id;
            $cfg['rules'][$rule_id]['enabled']   = '1';
            nvpn_save_config($cfg); nvpn_apply_rules();
            $msg_ok = "Rule reassigned to tunnel '" . htmlspecialchars($cfg['tunnels'][$tunnel_id]['label']) . "'.";
        } else {
            $msg_err = "Invalid rule or tunnel.";
        }

    } elseif ($action === 'delete_rule') {
        $rule_id = $_POST['rule_id'] ?? '';
        $cfg = nvpn_get_config();
        if (isset($cfg['rules'][$rule_id])) {
            $rname = $cfg['rules'][$rule_id]['name'];
            unset($cfg['rules'][$rule_id]);
            nvpn_save_config($cfg); nvpn_apply_rules();
            $msg_ok = "Rule '{$rname}' deleted.";
        }
    }
}

$cfg     = nvpn_get_config();
$rules   = is_array($cfg['rules']   ?? null) ? $cfg['rules']   : [];
$tunnels = is_array($cfg['tunnels'] ?? null) ? $cfg['tunnels'] : [];

// Build interface list from pfSense config — include all non-WAN interfaces
$ifaces = [];
global $config;
foreach ($config['interfaces'] ?? [] as $ifname => $iface) {
    if ($ifname === 'wan') continue;
    $label = strtoupper($ifname);
    if (!empty($iface['descr'])) $label .= ' — ' . htmlspecialchars($iface['descr']);
    if (!empty($iface['if']))    $label .= ' (' . htmlspecialchars($iface['if']) . ')';
    $ifaces[$ifname] = $label;
}
if (empty($ifaces)) $ifaces['lan'] = 'LAN';

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
    ['Rules',      true,  '/nordvpn/rules.php'],
    ['Kill Switch',false, '/nordvpn/killswitch.php'],
    ['DNS',        false, '/nordvpn/dns.php'],
    ['Status',     false, '/nordvpn/status.php'],
];
display_top_tabs($tab_array);
?>
<div class="col-sm-12">
<?php if ($msg_ok): ?><div class="alert alert-success"><?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>
<?php if ($msg_err): ?><div class="alert alert-danger"><?= htmlspecialchars($msg_err) ?></div><?php endif; ?>
<?php if (empty($tunnels)): ?>
<div class="alert alert-warning">No tunnels configured. <a href="/nordvpn/tunnels.php">Create a tunnel first.</a></div>
<?php endif; ?>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Routing Rules</h2></div>
  <div class="panel-body">
    <?php
    $orphaned = array_filter($rules, fn($r) => empty($r['enabled']) || empty($r['tunnel_id']) || !isset($tunnels[$r['tunnel_id']]));
    if (!empty($orphaned) && !empty($tunnels)): ?>
    <div class="alert alert-warning" style="margin-bottom:12px;">
      <?= count($orphaned) ?> rule(s) are disabled or point to a deleted tunnel.
      <form method="post" style="display:inline;margin-left:10px;">
        <input type="hidden" name="action" value="reassign_all">
        <button type="submit" class="btn btn-xs btn-warning"><i class="fa fa-refresh"></i> Re-enable &amp; assign to active tunnel</button>
      </form>
    </div>
    <?php endif; ?>
    <?php if (empty($rules)): ?>
    <p class="text-muted">No rules yet. Add a rule below to start routing devices through NordVPN.</p>
    <?php else: ?>
    <table class="table table-striped table-hover">
      <thead><tr><th>Name</th><th>Source</th><th>Interface</th><th>Tunnel</th><th>Status</th><th>Reassign</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rules as $rule_id => $rule): $tun = $tunnels[$rule['tunnel_id']] ?? null; ?>
        <tr class="<?= empty($rule['enabled']) ? 'text-muted' : '' ?>">
          <td><strong><?= htmlspecialchars($rule['name']) ?></strong></td>
          <td><code><?= htmlspecialchars($rule['value']) ?></code><br><small class="text-muted"><?= htmlspecialchars($rule['type']) ?></small></td>
          <td><?= strtoupper(htmlspecialchars($rule['interface'] ?? 'lan')) ?></td>
          <td><?php if ($tun): ?><span class="label label-info"><?= htmlspecialchars($tun['label']) ?></span><?php else: ?><span class="label label-danger">Unassigned</span><?php endif; ?></td>
          <td>
            <form method="post" style="display:inline;">
              <input type="hidden" name="action" value="toggle_rule">
              <input type="hidden" name="rule_id" value="<?= htmlspecialchars($rule_id) ?>">
              <button type="submit" class="btn btn-xs <?= !empty($rule['enabled']) ? 'btn-success' : 'btn-default' ?>"><?= !empty($rule['enabled']) ? '<i class="fa fa-check-circle"></i> Enabled' : '<i class="fa fa-circle-o"></i> Disabled' ?></button>
            </form>
          </td>
          <td>
            <?php if (!empty($tunnels)): ?>
            <form method="post" class="form-inline">
              <input type="hidden" name="action" value="reassign_rule">
              <input type="hidden" name="rule_id" value="<?= htmlspecialchars($rule_id) ?>">
              <select name="new_tunnel" class="form-control input-sm" style="max-width:180px;">
                <?php foreach ($tunnels as $tid => $t): ?>
                <option value="<?= htmlspecialchars($tid) ?>" <?= $tid === ($rule['tunnel_id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($t['label']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-xs btn-default" title="Reassign to selected tunnel"><i class="fa fa-exchange"></i> Reassign</button>
            </form>
            <?php else: ?><span class="text-muted">No tunnels</span><?php endif; ?>
          </td>
          <td>
            <form method="post" onsubmit="return confirm('Delete rule \'<?= htmlspecialchars(addslashes($rule['name'])) ?>\'?');">
              <input type="hidden" name="action" value="delete_rule">
              <input type="hidden" name="rule_id" value="<?= htmlspecialchars($rule_id) ?>">
              <button type="submit" class="btn btn-xs btn-danger" title="Delete this rule"><i class="fa fa-trash"></i> Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($tunnels)): ?>
<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Add New Rule</h2></div>
  <div class="panel-body">
    <form method="post">
      <input type="hidden" name="action" value="add_rule">
      <div class="row">
        <div class="col-sm-2"><div class="form-group"><label>Rule Name <span class="text-danger">*</span></label><input type="text" name="rule_name" class="form-control" placeholder="Apple TV 4K" maxlength="60"></div></div>
        <div class="col-sm-2"><div class="form-group"><label>Source Type</label><select name="rule_type" class="form-control" id="rule_type_sel" onchange="updateSourceHelp()"><option value="ip">Single IP</option><option value="vlan">VLAN / Subnet</option><option value="range">IP Range (CIDR)</option></select></div></div>
        <div class="col-sm-3"><div class="form-group"><label>IP / Subnet <span class="text-danger">*</span></label><input type="text" name="rule_value" class="form-control" id="rule_value_inp" placeholder="192.168.1.20"><span class="help-block" id="rule_value_help">The device's IP address.</span></div></div>
        <div class="col-sm-2"><div class="form-group"><label>Interface</label><select name="rule_interface" class="form-control"><?php foreach ($ifaces as $ifname => $ifdescr): ?><option value="<?= htmlspecialchars($ifname) ?>"><?= $ifdescr ?></option><?php endforeach; ?></select></div></div>
        <div class="col-sm-2"><div class="form-group"><label>Route Through <span class="text-danger">*</span></label><select name="rule_tunnel" class="form-control"><?php foreach ($tunnels as $tid => $t): ?><option value="<?= htmlspecialchars($tid) ?>"><?= htmlspecialchars($t['label']) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-sm-1" style="padding-top:25px;"><button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Add</button></div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title">Example Setup</h2></div>
  <div class="panel-body">
    <table class="table table-condensed"><thead><tr><th>Name</th><th>Source</th><th>Tunnel</th></tr></thead><tbody>
      <tr><td>Apple TV</td><td><code>192.168.1.20</code></td><td>US Streaming</td></tr>
      <tr><td>MacBook</td><td><code>192.168.1.50</code></td><td>EU Privacy</td></tr>
      <tr><td>IoT VLAN</td><td><code>192.168.10.0/24</code></td><td>US Streaming</td></tr>
    </tbody></table>
    <p class="help-block">Multiple rules can route through the same or different tunnels simultaneously.</p>
  </div>
</div>

</div></div></div></section>
<script>
function updateSourceHelp() {
    var type = document.getElementById('rule_type_sel').value;
    var inp  = document.getElementById('rule_value_inp');
    var help = document.getElementById('rule_value_help');
    if (type === 'ip') { inp.placeholder = '192.168.1.20'; help.textContent = "The device's IP address."; }
    else { inp.placeholder = '192.168.10.0/24'; help.textContent = 'Subnet in CIDR notation.'; }
}
</script>
<?php include("foot.inc"); ?>
</body>
