<?php
/*
 * status_dhcp_lease_manager.php
 *
 * Copyright (c) 2026 Branson Matheson
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

require_once("guiconfig.inc");
require_once("services_dhcp.inc");
require_once("/usr/local/pkg/dhcp_lease_manager.inc");

function dlm_json_reply($payload, $code = 200) {
	http_response_code($code);
	header('Content-Type: application/json');
	header('Cache-Control: no-store');
	echo json_encode($payload);
	exit;
}

/* Same test write_config() applies: "User - Config: Deny Config Write". */
function dlm_user_can_write_config() {
	if (empty($_SESSION['Username']) || ($_SESSION['Username'] == 'admin')) {
		return true;
	}
	$user = getUserEntry($_SESSION['Username']);
	return !(is_array($user['item'] ?? null) && userHasPrivilege($user['item'], 'user-config-readonly'));
}

/*
 * Viewing needs only this page's privilege. Changing device types writes the
 * configuration; removing static mappings and applying DHCP changes also need
 * the core privilege for editing DHCP static mappings.
 */
$dlm_perms = array('write' => dlm_user_can_write_config());
$dlm_perms['static'] = $dlm_perms['write'] && isAllowedPage('services_dhcp_edit.php');

/* AJAX actions. POSTs are CSRF-checked by guiconfig.inc (csrf-magic). */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dlm_action'])) {
	$need = ($_POST['dlm_action'] === 'delstatic') ? 'static' : 'write';
	if (!$dlm_perms[$need]) {
		dlm_json_reply(array('ok' => false, 'error' => gettext('Permission denied.')), 403);
	}
	switch ($_POST['dlm_action']) {
		case 'settype':
			$macs = json_decode($_POST['macs'] ?? '[]', true);
			$err = dlm_set_type_override(is_array($macs) ? $macs : array(), (string)($_POST['type'] ?? ''));
			dlm_json_reply($err ? array('ok' => false, 'error' => $err) : array('ok' => true), $err ? 400 : 200);
		case 'delstatic':
			$items = json_decode($_POST['items'] ?? '[]', true);
			list($n, $errors) = dlm_delete_static_maps(is_array($items) ? $items : array());
			dlm_json_reply(array('ok' => $n > 0, 'removed' => $n, 'errors' => $errors), $n > 0 ? 200 : 400);
		default:
			dlm_json_reply(array('ok' => false, 'error' => gettext('Unknown action.')), 400);
	}
}

/* pfSense "Apply Changes" banner posts here, same as services_dhcp.php. */
$changes_applied = false;
if (isset($_POST['apply']) && $dlm_perms['static']) {
	$changes_applied = true;
	$retval = dhcp_apply_changes();
}

$data = dlm_get_leases();
$data['perms'] = $dlm_perms;

/* JSON endpoint used by the page's auto-refresh. */
if (($_GET['format'] ?? '') === 'json') {
	dlm_json_reply($data);
}

$pgtitle = array(gettext("Status"), gettext("DHCP Lease Manager"));
$shortcut_section = "dhcp";
include("head.inc");

$backend_label = array('kea' => 'Kea DHCP', 'isc' => 'ISC DHCP');

if ($changes_applied) {
	print_apply_result_box($retval);
}
if (is_subsystem_dirty('dhcpd') && $dlm_perms['static']) {
	print_apply_box(gettext('The DHCP Server configuration has changed.') . '<br />' .
		gettext('The changes must be applied for them to take effect.'));
}
?>
<link rel="stylesheet" href="/dhcp_lease_manager/lease_manager.css" />

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
			<span class="dlm-chart-tabs">
				<a href="#" data-chart="type" class="active"><?=gettext("Device types")?></a> |
				<a href="#" data-chart="vendor"><?=gettext("Manufacturers")?></a>
			</span>
			<small class="dlm-meta">
				<?=htmlspecialchars($backend_label[$data['backend']] ?? $data['backend'])?>
				&middot; <span id="dlm-count"></span>
			</small>
		</h2>
	</div>
	<div class="panel-body">
		<div id="dlm-vendors" class="dlm-vendors"></div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Leases")?></h2>
	</div>
	<div class="panel-body">
		<form class="form-inline dlm-toolbar" onsubmit="return false;">
			<input type="search" id="dlm-search" class="form-control input-sm"
				placeholder="<?=gettext("Filter IP, MAC, hostname, vendor…")?>" />
			<select id="dlm-iface" class="form-control input-sm">
				<option value=""><?=gettext("All interfaces")?></option>
			</select>
			<select id="dlm-type" class="form-control input-sm">
				<option value=""><?=gettext("All device types")?></option>
			</select>
			<select id="dlm-state" class="form-control input-sm">
				<option value=""><?=gettext("All states")?></option>
				<option value="active"><?=gettext("Active")?></option>
				<option value="static"><?=gettext("Static")?></option>
				<option value="expired"><?=gettext("Expired")?></option>
				<option value="online"><?=gettext("Online now")?></option>
				<option value="dynamic"><?=gettext("Dynamic only")?></option>
			</select>
			<select id="dlm-refresh" class="form-control input-sm" title="<?=gettext("Auto refresh")?>">
				<option value="0"><?=gettext("No auto refresh")?></option>
				<option value="15"><?=gettext("Refresh 15s")?></option>
				<option value="60"><?=gettext("Refresh 60s")?></option>
			</select>
			<span class="text-muted dlm-hint"><?=gettext("Click a column to sort; right-click a row for actions.")?></span>
		</form>
		<div id="dlm-bulk" class="alert alert-info dlm-bulk" style="display:none"></div>
		<div id="dlm-msg"></div>
	</div>
	<div class="table-responsive">
		<table class="table table-striped table-hover table-condensed dlm-table" id="dlm-table">
			<thead>
				<tr>
					<th class="dlm-narrow"><input type="checkbox" id="dlm-all" title="<?=gettext("Select all shown")?>" /></th>
					<th data-key="online" class="dlm-narrow" title="<?=gettext("Online")?>"></th>
					<th data-key="ip"><?=gettext("IP address")?></th>
					<th data-key="mac"><?=gettext("MAC address")?></th>
					<th data-key="type_label"><?=gettext("Type")?></th>
					<th data-key="vendor"><?=gettext("Manufacturer")?></th>
					<th data-key="hostname"><?=gettext("Hostname")?></th>
					<th data-key="descr"><?=gettext("Description")?></th>
					<th data-key="if_descr"><?=gettext("Interface")?></th>
					<th data-key="age"><?=gettext("Lease age")?></th>
					<th data-key="ends"><?=gettext("Expires")?></th>
					<th data-key="state"><?=gettext("State")?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
</div>

<ul id="dlm-menu" class="dropdown-menu dlm-menu" role="menu"></ul>

<script type="application/json" id="dlm-data"><?=json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?></script>
<script src="/dhcp_lease_manager/lease_manager.js"></script>

<?php include("foot.inc"); ?>
