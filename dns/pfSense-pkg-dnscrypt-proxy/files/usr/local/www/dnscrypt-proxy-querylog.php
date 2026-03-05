<?php
/*
 * dnscrypt-proxy-querylog.php
 *
 * for pfSense
 * Copyright (c) 2026 nopoz (https://github.com/nopoz)
 * SPDX-License-Identifier: ISC
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

##|+PRIV
##|*IDENT=page-services-dnscryptproxy-querylog
##|*NAME=Services: DNSCrypt Proxy: Query Log
##|*DESCR=Allow access to the DNSCrypt Proxy Query Log page.
##|*MATCH=dnscrypt-proxy-querylog.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/dnscrypt-proxy.inc");

$pgtitle = array(gettext("Services"), gettext("DNSCrypt Proxy"), gettext("Query Log"));
$pglinks = array("", "/pkg_edit.php?xml=dnscrypt-proxy.xml", "@self");
$shortcut_section = "dnscrypt-proxy";

// Query log file path
$query_log_file = '/var/log/dnscrypt-proxy/query.log';

// Get filter parameters
$filter_domain = $_REQUEST['filter_domain'] ?? '';
$filter_type = $_REQUEST['filter_type'] ?? '';
$filter_client = $_REQUEST['filter_client'] ?? '';
$num_entries = $_REQUEST['entries'] ?? 100;
$num_entries = min(max(intval($num_entries), 10), 1000);

// Handle clear log action
if (isset($_POST['clear']) && $_POST['clear']) {
	if (file_exists($query_log_file)) {
		file_put_contents($query_log_file, '');
	}
	header("Location: /dnscrypt-proxy-querylog.php");
	exit;
}

// Check if query logging is enabled
$pkg_config = dnscrypt_proxy_get_config();
$query_log_enabled = isset($pkg_config['query_log']) && $pkg_config['query_log'] == 'on';

include("head.inc");

// Build the tab array
$tab_array = array();
$tab_array[] = array(gettext("General Settings"), false, "/pkg_edit.php?xml=dnscrypt-proxy.xml");
$tab_array[] = array(gettext("Server Selection"), false, "/pkg_edit.php?xml=dnscrypt-proxy-servers.xml");
$tab_array[] = array(gettext("Cache & Filtering"), false, "/pkg_edit.php?xml=dnscrypt-proxy-cache.xml");
$tab_array[] = array(gettext("Logging"), false, "/pkg_edit.php?xml=dnscrypt-proxy-logging.xml");
$tab_array[] = array(gettext("Lists"), false, "/pkg_edit.php?xml=dnscrypt-proxy-lists.xml");
$tab_array[] = array(gettext("Advanced"), false, "/pkg_edit.php?xml=dnscrypt-proxy-advanced.xml");
$tab_array[] = array(gettext("Query Log"), true, "/dnscrypt-proxy-querylog.php");
$tab_array[] = array(gettext("Config"), false, "/dnscrypt-proxy-config.php");
display_top_tabs($tab_array);

?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Query Log Filter")?></h2>
	</div>
	<div class="panel-body">
		<form action="/dnscrypt-proxy-querylog.php" method="get" class="form-inline">
			<div class="form-group" style="margin-right: 10px;">
				<label for="filter_domain" style="margin-right: 5px;">Domain:</label>
				<input type="text" class="form-control" id="filter_domain" name="filter_domain"
					   value="<?=htmlspecialchars($filter_domain)?>" placeholder="e.g., google.com">
			</div>
			<div class="form-group" style="margin-right: 10px;">
				<label for="filter_type" style="margin-right: 5px;">Type:</label>
				<select class="form-control" id="filter_type" name="filter_type">
					<option value="">All</option>
					<option value="A" <?=($filter_type == 'A') ? 'selected' : ''?>>A</option>
					<option value="AAAA" <?=($filter_type == 'AAAA') ? 'selected' : ''?>>AAAA</option>
					<option value="CNAME" <?=($filter_type == 'CNAME') ? 'selected' : ''?>>CNAME</option>
					<option value="MX" <?=($filter_type == 'MX') ? 'selected' : ''?>>MX</option>
					<option value="TXT" <?=($filter_type == 'TXT') ? 'selected' : ''?>>TXT</option>
					<option value="PTR" <?=($filter_type == 'PTR') ? 'selected' : ''?>>PTR</option>
					<option value="SRV" <?=($filter_type == 'SRV') ? 'selected' : ''?>>SRV</option>
					<option value="HTTPS" <?=($filter_type == 'HTTPS') ? 'selected' : ''?>>HTTPS</option>
				</select>
			</div>
			<div class="form-group" style="margin-right: 10px;">
				<label for="filter_client" style="margin-right: 5px;">Client IP:</label>
				<input type="text" class="form-control" id="filter_client" name="filter_client"
					   value="<?=htmlspecialchars($filter_client)?>" placeholder="e.g., 192.168.1.100">
			</div>
			<div class="form-group" style="margin-right: 10px;">
				<label for="entries" style="margin-right: 5px;">Entries:</label>
				<select class="form-control" id="entries" name="entries">
					<option value="50" <?=($num_entries == 50) ? 'selected' : ''?>>50</option>
					<option value="100" <?=($num_entries == 100) ? 'selected' : ''?>>100</option>
					<option value="250" <?=($num_entries == 250) ? 'selected' : ''?>>250</option>
					<option value="500" <?=($num_entries == 500) ? 'selected' : ''?>>500</option>
					<option value="1000" <?=($num_entries == 1000) ? 'selected' : ''?>>1000</option>
				</select>
			</div>
			<button type="submit" class="btn btn-primary">
				<i class="fa fa-filter"></i> Filter
			</button>
			<a href="/dnscrypt-proxy-querylog.php" class="btn btn-default">
				<i class="fa fa-undo"></i> Reset
			</a>
		</form>
	</div>
</div>

<?php if (!$query_log_enabled): ?>
<div class="alert alert-warning">
	<i class="fa fa-exclamation-triangle"></i>
	<?=gettext("Query logging is not enabled. Enable it in the ")?>
	<a href="/pkg_edit.php?xml=dnscrypt-proxy-logging.xml"><?=gettext("Logging")?></a>
	<?=gettext(" tab.")?>
</div>
<?php endif; ?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=gettext("DNS Queries")?>
			<span class="pull-right">
				<form action="/dnscrypt-proxy-querylog.php" method="post" style="display: inline;">
					<button type="submit" name="clear" value="1" class="btn btn-xs btn-danger"
							onclick="return confirm('<?=gettext("Are you sure you want to clear the query log?")?>');">
						<i class="fa fa-trash"></i> <?=gettext("Clear Log")?>
					</button>
				</form>
				<button type="button" class="btn btn-xs btn-info" onclick="location.reload();">
					<i class="fa fa-refresh"></i> <?=gettext("Refresh")?>
				</button>
			</span>
		</h2>
	</div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Time")?></th>
						<th><?=gettext("Client")?></th>
						<th><?=gettext("Domain")?></th>
						<th><?=gettext("Type")?></th>
						<th><?=gettext("Server")?></th>
						<th><?=gettext("Latency")?></th>
						<th><?=gettext("Status")?></th>
					</tr>
				</thead>
				<tbody>
<?php
// Read and parse query log
$entries = array();

if (file_exists($query_log_file) && is_readable($query_log_file)) {
	// Read file in reverse (most recent first)
	$lines = file($query_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($lines !== false) {
		$lines = array_reverse($lines);

		$count = 0;
		foreach ($lines as $line) {
			if ($count >= $num_entries) {
				break;
			}

			// Parse dnscrypt-proxy query log format (tab-separated)
			// Format: timestamp	client_ip	query_name	query_type	resolver	latency_ms	status
			$parts = explode("\t", $line);

			if (count($parts) >= 6) {
				$entry = array(
					'time' => $parts[0] ?? '',
					'client' => $parts[1] ?? '',
					'domain' => $parts[2] ?? '',
					'type' => $parts[3] ?? '',
					'server' => $parts[4] ?? '',
					'latency' => $parts[5] ?? '',
					'status' => $parts[6] ?? 'OK'
				);

				// Apply filters
				if (!empty($filter_domain) && stripos($entry['domain'], $filter_domain) === false) {
					continue;
				}
				if (!empty($filter_type) && $entry['type'] != $filter_type) {
					continue;
				}
				if (!empty($filter_client) && stripos($entry['client'], $filter_client) === false) {
					continue;
				}

				$entries[] = $entry;
				$count++;
			}
		}
	}
}

if (empty($entries)): ?>
					<tr>
						<td colspan="7" class="text-center text-muted">
							<?php if (!file_exists($query_log_file)): ?>
								<?=gettext("Query log file does not exist. Enable query logging and make some DNS queries.")?>
							<?php else: ?>
								<?=gettext("No queries found matching the filter criteria.")?>
							<?php endif; ?>
						</td>
					</tr>
<?php else:
	foreach ($entries as $entry):
		$status_class = (stripos($entry['status'], 'PASS') !== false || $entry['status'] == 'OK') ? 'success' :
		               ((stripos($entry['status'], 'BLOCK') !== false || stripos($entry['status'], 'REJECT') !== false) ? 'danger' : 'default');
?>
					<tr>
						<td><?=htmlspecialchars($entry['time'])?></td>
						<td><?=htmlspecialchars($entry['client'])?></td>
						<td><code><?=htmlspecialchars($entry['domain'])?></code></td>
						<td><span class="label label-primary"><?=htmlspecialchars($entry['type'])?></span></td>
						<td><?=htmlspecialchars($entry['server'])?></td>
						<td><?=htmlspecialchars($entry['latency'])?></td>
						<td><span class="label label-<?=htmlspecialchars($status_class)?>"><?=htmlspecialchars($entry['status'])?></span></td>
					</tr>
<?php
	endforeach;
endif;
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="infoblock">
	<div class="alert alert-info clearfix" role="alert">
		<p><strong><?=gettext("Note:")?></strong>
		<?=gettext("Query logging can impact performance and generate large log files. Use it for troubleshooting purposes.")?>
		</p>
		<p><?=gettext("Service logs are available in ")?><a href="/status_logs_packages.php?pkg=dnscrypt-proxy"><?=gettext("Status > System Logs > Packages")?></a>.</p>
	</div>
</div>

<?php
include("foot.inc");
?>
