<?php
/*
 * dnscrypt-proxy-config.php
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
##|*IDENT=page-services-dnscryptproxy-config
##|*NAME=Services: DNSCrypt Proxy: Config
##|*DESCR=Allow access to the DNSCrypt Proxy Config page.
##|*MATCH=dnscrypt-proxy-config.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/dnscrypt-proxy.inc");

// Handle download request
if (isset($_GET['download'])) {
	if (file_exists(DNSCRYPT_PROXY_CONFIG)) {
		$config_content = file_get_contents(DNSCRYPT_PROXY_CONFIG);
		header('Content-Type: application/toml');
		header('Content-Disposition: attachment; filename="dnscrypt-proxy.toml"');
		header('Content-Length: ' . strlen($config_content));
		echo $config_content;
		exit;
	}
}

$input_errors = array();
$savemsg = '';

// Handle import POST
if ($_POST && isset($_POST['import_toml'])) {
	$import_content = $_POST['import_toml'];

	if (empty(trim($import_content))) {
		$input_errors[] = gettext("No TOML content provided. Paste a configuration or use the file picker.");
	} else {
		$import_errors = dnscrypt_proxy_import_toml($import_content);
		if (!empty($import_errors)) {
			$input_errors = $import_errors;
		} else {
			$savemsg = gettext("Configuration imported successfully. All GUI tabs now reflect the imported settings.");
		}
	}
}

// Handle reset POST
if ($_POST && isset($_POST['reset_defaults']) && $_POST['reset_defaults'] === 'yes') {
	dnscrypt_proxy_reset_config();
	dnscrypt_proxy_sync();
	$savemsg = gettext("All settings have been reset to defaults. The service has been restarted.");
}

$pgtitle = array(gettext("Services"), gettext("DNSCrypt Proxy"), gettext("Config"));
$pglinks = array("", "/pkg_edit.php?xml=dnscrypt-proxy.xml", "@self");
$shortcut_section = "dnscrypt-proxy";

include("head.inc");

// Build the tab array
$tab_array = array();
$tab_array[] = array(gettext("General Settings"), false, "/pkg_edit.php?xml=dnscrypt-proxy.xml");
$tab_array[] = array(gettext("Server Selection"), false, "/pkg_edit.php?xml=dnscrypt-proxy-servers.xml");
$tab_array[] = array(gettext("Cache & Filtering"), false, "/pkg_edit.php?xml=dnscrypt-proxy-cache.xml");
$tab_array[] = array(gettext("Logging"), false, "/pkg_edit.php?xml=dnscrypt-proxy-logging.xml");
$tab_array[] = array(gettext("Lists"), false, "/pkg_edit.php?xml=dnscrypt-proxy-lists.xml");
$tab_array[] = array(gettext("Advanced"), false, "/pkg_edit.php?xml=dnscrypt-proxy-advanced.xml");
$tab_array[] = array(gettext("Query Log"), false, "/dnscrypt-proxy-querylog.php");
$tab_array[] = array(gettext("Config"), true, "/dnscrypt-proxy-config.php");
display_top_tabs($tab_array);

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$config_exists = file_exists(DNSCRYPT_PROXY_CONFIG);
$config_content = $config_exists ? file_get_contents(DNSCRYPT_PROXY_CONFIG) : '';

?>

<?php if (!$config_exists): ?>
<div class="alert alert-info">
	<i class="fa fa-info-circle"></i>
	<?=gettext("No configuration file found. Enable the service and save settings to generate the config.")?>
</div>
<?php else: ?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=gettext("DNSCrypt Proxy Configuration")?>
			<span class="pull-right">
				<button type="button" id="btn-copy" class="btn btn-xs btn-info">
					<i class="fa fa-clipboard"></i> <?=gettext("Copy to Clipboard")?>
				</button>
				<a href="/dnscrypt-proxy-config.php?download=1" class="btn btn-xs btn-primary">
					<i class="fa fa-download"></i> <?=gettext("Download")?>
				</a>
			</span>
		</h2>
	</div>
	<div class="panel-body">
		<textarea id="config-content" class="form-control" rows="30" readonly
			style="font-family: monospace; font-size: 13px; resize: vertical;"><?=htmlspecialchars($config_content)?></textarea>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
document.getElementById('btn-copy').addEventListener('click', function() {
	var textarea = document.getElementById('config-content');
	navigator.clipboard.writeText(textarea.value).then(function() {
		var btn = document.getElementById('btn-copy');
		var origHTML = btn.innerHTML;
		btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
		setTimeout(function() { btn.innerHTML = origHTML; }, 2000);
	});
});
//]]>
</script>
<?php endif; ?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Import Configuration")?></h2>
	</div>
	<div class="panel-body">
		<form method="post" id="import-form" style="padding: 7px;">
			<div class="form-group">
				<label><?=gettext("Paste TOML configuration or load from file:")?></label>
				<textarea id="import-toml" name="import_toml" class="form-control" rows="12"
					style="font-family: monospace; font-size: 13px; resize: vertical;"
					placeholder="# Paste dnscrypt-proxy TOML configuration here..."><?=htmlspecialchars($_POST['import_toml'] ?? '')?></textarea>
			</div>
			<div class="form-group">
				<label class="btn btn-default btn-file">
					<i class="fa fa-folder-open"></i> <?=gettext("Load from file")?>&hellip;
					<input type="file" id="import-file" accept=".toml,.txt" style="display: none;">
				</label>
				<span id="import-filename" class="text-muted" style="margin-left: 8px;"></span>
			</div>
			<button type="submit" id="btn-import" class="btn btn-warning">
				<i class="fa fa-upload"></i> <?=gettext("Import")?>
			</button>
			<span class="help-block">
				<?=gettext("Importing will overwrite current settings (except enable/disable state, listen interfaces, and list file contents). The service will be restarted with the imported configuration.")?>
			</span>
		</form>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
// File picker: read file into textarea
document.getElementById('import-file').addEventListener('change', function(e) {
	var file = e.target.files[0];
	if (!file) return;
	document.getElementById('import-filename').textContent = file.name;
	var reader = new FileReader();
	reader.onload = function(ev) {
		document.getElementById('import-toml').value = ev.target.result;
	};
	reader.readAsText(file);
});

// Confirmation before import
document.getElementById('import-form').addEventListener('submit', function(e) {
	var content = document.getElementById('import-toml').value.trim();
	if (!content) {
		e.preventDefault();
		alert('<?=gettext("Please paste a TOML configuration or load a file first.")?>');
		return;
	}
	if (!confirm('<?=gettext("This will overwrite your current DNSCrypt Proxy settings with the imported configuration. Are you sure?")?>')) {
		e.preventDefault();
	}
});
//]]>
</script>

<div class="panel panel-danger">
	<div class="panel-heading">
		<h2 class="panel-title"><i class="fa fa-exclamation-triangle"></i> <?=gettext("Reset to Defaults")?></h2>
	</div>
	<div class="panel-body">
		<form method="post" id="reset-form" style="padding: 7px;">
			<input type="hidden" name="reset_defaults" value="yes">
			<p><?=gettext("This will reset all DNSCrypt Proxy settings across all tabs to their default values.")?></p>
			<p class="text-danger"><strong><?=gettext("Warning:")?></strong>
			<?=gettext("This action cannot be undone. Your current configuration will be lost.")?></p>
			<button type="submit" id="btn-reset" class="btn btn-danger">
				<i class="fa fa-undo"></i> <?=gettext("Reset All Settings")?>
			</button>
		</form>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
document.getElementById('reset-form').addEventListener('submit', function(e) {
	if (!confirm('<?=gettext("Are you sure you want to reset ALL DNSCrypt Proxy settings to defaults? This cannot be undone.")?>')) {
		e.preventDefault();
	}
});
//]]>
</script>

<div class="infoblock">
	<div class="alert alert-info clearfix" role="alert">
		<p><strong><?=gettext("Note:")?></strong>
		<?=gettext("This is the generated TOML configuration file used by dnscrypt-proxy. Changes should be made through the GUI tabs above, or by importing a complete TOML configuration.")?>
		</p>
	</div>
</div>

<?php
include("foot.inc");
?>
